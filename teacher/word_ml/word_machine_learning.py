#!/usr/bin/env python3
"""Run ML estimation for word-level hesitation and store to temporary_results_word."""
import argparse
import mysql.connector
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import StratifiedKFold, cross_val_score

from word_sample_shap import generate_hesitation_feedback

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "Koihaki5143910",
    "database": "2019su1",
    "port": 3306,
}
FEATURES = [
    "word_length",
    "position_ratio",
    "label_hit_count",
    "hlabel_hit_count",
    "drag_count",
    "drop_count",
    "dwell_time",
    "total_time",
]


def ensure_table(conn):
    sql = """
    CREATE TABLE IF NOT EXISTS temporary_results_word (
      UID VARCHAR(64) NOT NULL,
      WID INT NOT NULL,
      WWID INT NOT NULL,
      Understand INT NOT NULL,
      teacher_id VARCHAR(64) NOT NULL,
      attempt INT NOT NULL,
      predicted_probability DOUBLE NULL,
      feedback_text TEXT NULL,
      model_name VARCHAR(128) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (UID, WID, WWID, teacher_id, attempt),
      KEY idx_teacher (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    """
    cur = conn.cursor()
    cur.execute(sql)

    cur.execute("SHOW COLUMNS FROM temporary_results_word LIKE 'feedback_text'")
    if cur.fetchone() is None:
        cur.execute(
            "ALTER TABLE temporary_results_word "
            "ADD COLUMN feedback_text TEXT NULL AFTER predicted_probability"
        )

    conn.commit()
    cur.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("teacher_id")
    args = parser.parse_args()

    conn = mysql.connector.connect(**DB_CONFIG)
    try:
        ensure_table(conn)
        df = pd.read_sql("SELECT * FROM test_featurevalue_word", conn)
        if df.empty:
            print("No rows found in test_featurevalue_word")
            return

        train_df = df.dropna(subset=["is_hesitate_label"]).copy()
        if train_df["is_hesitate_label"].nunique() < 2:
            print("Not enough class variety for classification")
            return

        X = train_df[FEATURES]
        y = train_df["is_hesitate_label"].astype(int)

        model = RandomForestClassifier(n_estimators=200, random_state=42)

        cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
        scores = cross_val_score(model, X, y, cv=cv, scoring="accuracy")
        print(f"CV accuracy mean={scores.mean():.4f} std={scores.std():.4f}")

        model.fit(X, y)
        all_X = df[FEATURES]
        probs = model.predict_proba(all_X)[:, 1]
        preds = (probs >= 0.5).astype(int)

        baseline = X.mean()
        pseudo_shap = all_X.subtract(baseline, axis=1).values * model.feature_importances_

        cur = conn.cursor()
        cur.execute("DELETE FROM temporary_results_word WHERE teacher_id = %s", (str(args.teacher_id),))

        insert_sql = """
        INSERT INTO temporary_results_word
          (UID, WID, WWID, Understand, teacher_id, attempt, predicted_probability, feedback_text, model_name)
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
        ON DUPLICATE KEY UPDATE
          Understand=VALUES(Understand),
          predicted_probability=VALUES(predicted_probability),
          feedback_text=VALUES(feedback_text),
          model_name=VALUES(model_name),
          created_at=CURRENT_TIMESTAMP
        """

        rows = []
        for i, row in df.iterrows():
            understand = 2 if int(preds[i]) == 1 else 4
            feedback_text = generate_hesitation_feedback(pseudo_shap[i], FEATURES)
            rows.append(
                (
                    str(row["UID"]),
                    int(row["WID"]),
                    int(row["WWID"]),
                    understand,
                    str(args.teacher_id),
                    int(row["attempt"]),
                    float(probs[i]),
                    feedback_text,
                    "RandomForestClassifier",
                )
            )

        cur.executemany(insert_sql, rows)
        conn.commit()
        print(f"Inserted/updated {len(rows)} rows into temporary_results_word")
        cur.close()
    finally:
        conn.close()


if __name__ == "__main__":
    main()

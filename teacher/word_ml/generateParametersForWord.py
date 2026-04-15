#!/usr/bin/env python3
"""Generate word-level features and save into test_featurevalue_word."""
import argparse
import re
import mysql.connector

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "Koihaki5143910",
    "database": "2019su1",
    "port": 3306,
}

TOKEN_SPLIT_PATTERN = re.compile(r"[\s#]+")


def normalize_tokens(raw: str):
    if not raw:
        return []
    return [t.strip().lower() for t in TOKEN_SPLIT_PATTERN.split(raw) if t.strip()]


def ensure_table(conn):
    sql = """
    CREATE TABLE IF NOT EXISTS test_featurevalue_word (
        UID VARCHAR(64) NOT NULL,
        WID INT NOT NULL,
        WWID INT NOT NULL,
        attempt INT NOT NULL,
        word_text VARCHAR(255) NOT NULL,
        word_length INT NOT NULL,
        position_ratio DOUBLE NOT NULL,
        label_hit_count INT NOT NULL,
        hlabel_hit_count INT NOT NULL,
        drag_count INT NOT NULL,
        drop_count INT NOT NULL,
        dwell_time DOUBLE NOT NULL,
        total_time DOUBLE NOT NULL,
        is_hesitate_label TINYINT(1) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (UID, WID, WWID, attempt),
        KEY idx_wid_attempt (WID, attempt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    """
    cur = conn.cursor()
    cur.execute(sql)
    conn.commit()
    cur.close()


def fetch_attempt_rows(conn):
    query = """
    SELECT l.UID, l.WID, l.attempt, l.Time, l.DD, l.Label, l.hLabel, l.hesitate,
           qi.Sentence
    FROM linedata l
    LEFT JOIN question_info qi ON qi.WID = l.WID
    ORDER BY l.UID, l.WID, l.attempt, l.Time
    """
    cur = conn.cursor(dictionary=True)
    cur.execute(query)
    rows = cur.fetchall()
    cur.close()
    return rows


def build_records(rows):
    grouped = {}
    for row in rows:
        key = (str(row["UID"]), int(row["WID"]), int(row["attempt"]))
        grouped.setdefault(key, []).append(row)

    records = []
    for (uid, wid, attempt), events in grouped.items():
        sentence = (events[0].get("Sentence") or "").strip()
        words = [w for w in sentence.split() if w]
        if not words:
            continue

        first_time = float(events[0]["Time"] or 0)
        last_time = float(events[-1]["Time"] or first_time)
        total_time = max(0.0, last_time - first_time)

        hesitate_tokens = set(normalize_tokens(events[0].get("hesitate") or ""))

        prepared_events = []
        for idx, event in enumerate(events):
            label_tokens = set(normalize_tokens(event.get("Label") or ""))
            hlabel_tokens = set(normalize_tokens(event.get("hLabel") or ""))
            current_time = float(event.get("Time") or 0)
            next_time = current_time
            if idx + 1 < len(events):
                next_time = float(events[idx + 1].get("Time") or current_time)
            prepared_events.append(
                {
                    "dd": int(event.get("DD") or 0),
                    "label_tokens": label_tokens,
                    "hlabel_tokens": hlabel_tokens,
                    "delta": max(0.0, next_time - current_time),
                }
            )

        word_count = len(words)
        for wwid, word in enumerate(words, start=1):
            key = word.lower()
            label_hit = 0
            hlabel_hit = 0
            drag = 0
            drop = 0
            dwell = 0.0

            for ev in prepared_events:
                in_label = key in ev["label_tokens"]
                in_hlabel = key in ev["hlabel_tokens"]
                if in_label:
                    label_hit += 1
                    dwell += ev["delta"]
                    if ev["dd"] == 2:
                        drag += 1
                    elif ev["dd"] == 1:
                        drop += 1
                if in_hlabel:
                    hlabel_hit += 1

            records.append(
                (
                    uid,
                    wid,
                    wwid,
                    attempt,
                    word,
                    len(word),
                    float(wwid) / float(word_count),
                    label_hit,
                    hlabel_hit,
                    drag,
                    drop,
                    dwell,
                    total_time,
                    1 if key in hesitate_tokens else 0,
                )
            )

    return records


def upsert_records(conn, records):
    if not records:
        return 0
    sql = """
    INSERT INTO test_featurevalue_word (
      UID, WID, WWID, attempt, word_text, word_length, position_ratio,
      label_hit_count, hlabel_hit_count, drag_count, drop_count, dwell_time,
      total_time, is_hesitate_label
    ) VALUES (
      %s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s
    ) ON DUPLICATE KEY UPDATE
      word_text=VALUES(word_text),
      word_length=VALUES(word_length),
      position_ratio=VALUES(position_ratio),
      label_hit_count=VALUES(label_hit_count),
      hlabel_hit_count=VALUES(hlabel_hit_count),
      drag_count=VALUES(drag_count),
      drop_count=VALUES(drop_count),
      dwell_time=VALUES(dwell_time),
      total_time=VALUES(total_time),
      is_hesitate_label=VALUES(is_hesitate_label)
    """
    cur = conn.cursor()
    cur.executemany(sql, records)
    count = cur.rowcount
    conn.commit()
    cur.close()
    return count


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--uid", default=None)
    parser.add_argument("--wid", type=int, default=None)
    args = parser.parse_args()

    conn = mysql.connector.connect(**DB_CONFIG)
    try:
        ensure_table(conn)
        rows = fetch_attempt_rows(conn)
        if args.uid:
            rows = [r for r in rows if str(r["UID"]) == str(args.uid)]
        if args.wid is not None:
            rows = [r for r in rows if int(r["WID"]) == int(args.wid)]

        records = build_records(rows)
        updated = upsert_records(conn, records)
        print(f"Generated/updated word-level features: {updated} rows")
    finally:
        conn.close()


if __name__ == "__main__":
    main()

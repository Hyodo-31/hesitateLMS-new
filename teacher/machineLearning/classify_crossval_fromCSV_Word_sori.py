#!/usr/bin/env python3
"""単語単位特徴量から迷い度(Understand=2/4)を推定しCSVを出力する。"""
from __future__ import annotations

import csv
import os
import time
from typing import List

import mysql.connector

try:
    from sklearn.ensemble import RandomForestClassifier
    from sklearn.model_selection import train_test_split
    SKLEARN_AVAILABLE = True
except Exception:  # noqa: BLE001
    SKLEARN_AVAILABLE = False

FEATURES = [
    "Time", "distance", "averageSpeed", "maxSpeed", "thinkingTime", "answeringTime", "totalStopTime",
    "maxStopTime", "totalDDIntervalTime", "maxDDIntervalTime", "maxDDTime", "minDDTime", "DDCount",
    "groupingDDCount", "groupingCountbool", "xUTurnCount", "yUTurnCount", "stopcount", "xUTurnCountDD",
    "yUTurnCountDD", "FromlastdropToanswerTime",
]


def connect_db():
    return mysql.connector.connect(
        host=os.getenv("LMS_DB_HOST", "127.0.0.1"),
        user=os.getenv("LMS_DB_USER", "root"),
        password=os.getenv("LMS_DB_PASSWORD", "8181saisaI"),
        database=os.getenv("LMS_DB_NAME", "2019su1"),
    )


def to_float(v):
    try:
        return float(v) if v is not None else 0.0
    except Exception:  # noqa: BLE001
        return 0.0


def median(values: List[float]) -> float:
    if not values:
        return 0.0
    s = sorted(values)
    n = len(s)
    m = n // 2
    if n % 2 == 1:
        return s[m]
    return (s[m - 1] + s[m]) / 2.0


def main():
    conn = connect_db()
    cur = conn.cursor(dictionary=True)

    cur.execute(
        f"SELECT UID, WID, WWID, COALESCE(attempt,1) AS attempt, COALESCE(Understand,0) AS Understand, {','.join(FEATURES)} FROM test_featurevalue_word"
    )
    rows = cur.fetchall()
    if not rows:
        print("test_featurevalue_word にデータがありません。")
        cur.close()
        conn.close()
        return

    labeled_X, labeled_y = [], []
    all_X = []
    for r in rows:
        feat = [to_float(r.get(c)) for c in FEATURES]
        all_X.append(feat)
        if int(r["Understand"]) in (2, 4):
            labeled_X.append(feat)
            labeled_y.append(int(r["Understand"]))

    predictions: List[int]
    if SKLEARN_AVAILABLE and len(labeled_X) >= 20 and len(set(labeled_y)) >= 2:
        x_train, _, y_train, _ = train_test_split(labeled_X, labeled_y, test_size=0.2, random_state=42, stratify=labeled_y)
        model = RandomForestClassifier(n_estimators=200, random_state=42, class_weight="balanced")
        model.fit(x_train, y_train)
        predictions = [int(x) for x in model.predict(all_X)]
        mode = "RandomForest"
    else:
        # フォールバック: 代表特徴量の中央値ベース
        times = [x[0] for x in all_X]
        dists = [x[1] for x in all_X]
        ddcnt = [x[12] for x in all_X]
        th_time = median(times)
        th_dist = median(dists)
        th_dd = median(ddcnt)
        predictions = []
        for x in all_X:
            score = int(x[0] > th_time) + int(x[1] > th_dist) + int(x[12] > th_dd)
            predictions.append(2 if score >= 2 else 4)
        mode = "HeuristicFallback"

    ts = int(time.time())
    out_dir = os.path.dirname(__file__)
    out_csv = os.path.join(out_dir, f"results_actual_word_{ts}.csv")
    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(["UID", "WID", "WWID", "attempt", "Understand"])
        for r, pred in zip(rows, predictions):
            writer.writerow([r["UID"], r["WID"], r["WWID"], r["attempt"], pred])

    print(f"単語迷い推定完了: {len(rows)} 件 / mode={mode}")
    print(f"出力CSV: {out_csv}")

    cur.close()
    conn.close()


if __name__ == "__main__":
    main()

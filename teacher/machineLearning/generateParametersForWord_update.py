#!/usr/bin/env python3
"""単語単位特徴量を `test_featurevalue_word` に生成するスクリプト。

優先的に `linedata_word` を利用し、存在しない場合は `linedata` + `question_info.wordnum`
から簡易特徴量を展開して作成する。
"""
from __future__ import annotations

import os
from typing import Dict, Iterable, List, Tuple

import mysql.connector


DEST_COLUMNS = [
    "UID", "WID", "WWID", "Understand", "attempt", "date", "check", "Time", "distance",
    "averageSpeed", "maxSpeed", "thinkingTime", "answeringTime", "totalStopTime", "maxStopTime",
    "totalDDIntervalTime", "maxDDIntervalTime", "maxDDTime", "minDDTime", "DDCount",
    "groupingDDCount", "groupingCountbool", "xUTurnCount", "yUTurnCount", "register_move_count1",
    "register_move_count2", "register_move_count3", "register_move_count4", "register01count1",
    "register01count2", "register01count3", "register01count4", "registerDDCount", "stopcount",
    "xUTurnCountDD", "yUTurnCountDD", "FromlastdropToanswerTime",
]


def connect_db():
    candidates = [
        (os.getenv("LMS_DB_HOST", "127.0.0.1"), os.getenv("LMS_DB_USER", "root"), os.getenv("LMS_DB_PASSWORD", "8181saisaI"), os.getenv("LMS_DB_NAME", "2019su1")),
        ("127.0.0.1", "root", "Z3%k-W3udc", "2019su1"),
    ]
    last_error = None
    for host, user, password, database in candidates:
        try:
            return mysql.connector.connect(host=host, user=user, password=password, database=database)
        except Exception as exc:  # noqa: BLE001
            last_error = exc
    raise RuntimeError(f"DB接続に失敗しました: {last_error}")


def table_exists(cur, table_name: str) -> bool:
    cur.execute("SHOW TABLES LIKE %s", (table_name,))
    return cur.fetchone() is not None


def safe_num(value, default=0.0):
    try:
        if value is None:
            return default
        return float(value)
    except Exception:  # noqa: BLE001
        return default


def make_feature_row(uid: int, wid: int, wwid: int, attempt: int, understand: int, date_value: int, data: Dict[str, float]) -> Dict[str, float]:
    time_spent = max(safe_num(data.get("max_time")) - safe_num(data.get("min_time")), 0.0)
    distance = safe_num(data.get("distance"))
    avg_speed = distance / time_spent if time_spent > 0 else 0.0
    return {
        "UID": uid,
        "WID": wid,
        "WWID": wwid,
        "Understand": understand,
        "attempt": attempt,
        "date": int(date_value),
        "check": int(data.get("check", 0)),
        "Time": time_spent,
        "distance": distance,
        "averageSpeed": avg_speed,
        "maxSpeed": safe_num(data.get("max_speed")),
        "thinkingTime": time_spent * 0.5,
        "answeringTime": time_spent * 0.5,
        "totalStopTime": safe_num(data.get("stop_time")),
        "maxStopTime": safe_num(data.get("max_stop_time")),
        "totalDDIntervalTime": safe_num(data.get("dd_interval_sum")),
        "maxDDIntervalTime": safe_num(data.get("dd_interval_max")),
        "maxDDTime": safe_num(data.get("dd_time_max")),
        "minDDTime": safe_num(data.get("dd_time_min")),
        "DDCount": int(data.get("dd_count", 0)),
        "groupingDDCount": int(data.get("grouping_dd", 0)),
        "groupingCountbool": 1 if int(data.get("grouping_dd", 0)) > 0 else 0,
        "xUTurnCount": int(data.get("x_uturn", 0)),
        "yUTurnCount": int(data.get("y_uturn", 0)),
        "register_move_count1": 0,
        "register_move_count2": 0,
        "register_move_count3": 0,
        "register_move_count4": 0,
        "register01count1": 0,
        "register01count2": 0,
        "register01count3": 0,
        "register01count4": 0,
        "registerDDCount": int(data.get("dd_count", 0)),
        "stopcount": int(data.get("stop_count", 0)),
        "xUTurnCountDD": int(data.get("x_uturn_dd", 0)),
        "yUTurnCountDD": int(data.get("y_uturn_dd", 0)),
        "FromlastdropToanswerTime": safe_num(data.get("from_last_drop", 0.0)),
    }


def load_source_rows(cur) -> List[Tuple[int, int, int, int, int, Dict[str, float]]]:
    if table_exists(cur, "linedata_word"):
        cur.execute(
            """
            SELECT UID, WID, WWID, COALESCE(attempt,1), COALESCE(Understand,0), COALESCE(`check`,0),
                   MIN(Time) AS min_time, MAX(Time) AS max_time,
                   SUM(COALESCE(distance,0)) AS distance,
                   MAX(COALESCE(maxSpeed,0)) AS max_speed,
                   SUM(COALESCE(totalStopTime,0)) AS stop_time,
                   MAX(COALESCE(maxStopTime,0)) AS max_stop_time,
                   SUM(COALESCE(totalDDIntervalTime,0)) AS dd_interval_sum,
                   MAX(COALESCE(maxDDIntervalTime,0)) AS dd_interval_max,
                   MAX(COALESCE(maxDDTime,0)) AS dd_time_max,
                   MIN(NULLIF(COALESCE(minDDTime,0),0)) AS dd_time_min,
                   SUM(COALESCE(DDCount,0)) AS dd_count,
                   SUM(COALESCE(groupingDDCount,0)) AS grouping_dd,
                   SUM(COALESCE(xUTurnCount,0)) AS x_uturn,
                   SUM(COALESCE(yUTurnCount,0)) AS y_uturn,
                   SUM(COALESCE(stopcount,0)) AS stop_count,
                   SUM(COALESCE(xUTurnCountDD,0)) AS x_uturn_dd,
                   SUM(COALESCE(yUTurnCountDD,0)) AS y_uturn_dd,
                   MAX(COALESCE(FromlastdropToanswerTime,0)) AS from_last_drop,
                   MAX(COALESCE(Date,0)) AS date_value
            FROM linedata_word
            GROUP BY UID, WID, WWID, attempt, Understand, `check`
            """
        )
        out = []
        for row in cur.fetchall():
            uid, wid, wwid, attempt, understand, check, *vals = row
            data_keys = [
                "min_time", "max_time", "distance", "max_speed", "stop_time", "max_stop_time", "dd_interval_sum",
                "dd_interval_max", "dd_time_max", "dd_time_min", "dd_count", "grouping_dd", "x_uturn", "y_uturn",
                "stop_count", "x_uturn_dd", "y_uturn_dd", "from_last_drop", "date_value",
            ]
            data = {"check": check}
            data.update({k: v for k, v in zip(data_keys, vals)})
            out.append((uid, wid, wwid, attempt, understand, data))
        return out

    cur.execute(
        """
        SELECT l.UID, l.WID, COALESCE(l.attempt,1) AS attempt, COALESCE(l.Understand,0) AS Understand,
               COALESCE(q.wordnum, 1) AS wordnum, COALESCE(l.`check`,0) AS `check`,
               MIN(l.Time) AS min_time, MAX(l.Time) AS max_time,
               SUM(COALESCE(ABS(l.X),0) + COALESCE(ABS(l.Y),0)) AS distance_proxy,
               MAX(COALESCE(l.Date,0)) AS date_value
        FROM linedata l
        LEFT JOIN question_info q ON l.WID = q.WID
        GROUP BY l.UID, l.WID, l.attempt, l.Understand, q.wordnum, l.`check`
        """
    )

    out = []
    for uid, wid, attempt, understand, wordnum, check, min_t, max_t, distance_proxy, date_value in cur.fetchall():
        word_count = max(int(wordnum or 1), 1)
        base_data = {
            "check": check,
            "min_time": min_t,
            "max_time": max_t,
            "distance": safe_num(distance_proxy) / word_count,
            "max_speed": 0,
            "stop_time": 0,
            "max_stop_time": 0,
            "dd_interval_sum": 0,
            "dd_interval_max": 0,
            "dd_time_max": 0,
            "dd_time_min": 0,
            "dd_count": 0,
            "grouping_dd": 0,
            "x_uturn": 0,
            "y_uturn": 0,
            "stop_count": 0,
            "x_uturn_dd": 0,
            "y_uturn_dd": 0,
            "from_last_drop": 0,
            "date_value": date_value,
        }
        for wwid in range(1, word_count + 1):
            out.append((uid, wid, wwid, attempt, understand, base_data))
    return out


def upsert_features(cur, rows: Iterable[Dict[str, float]]) -> int:
    placeholders = ",".join(["%s"] * len(DEST_COLUMNS))
    updates = ",".join([f"{c}=VALUES({c})" for c in DEST_COLUMNS if c not in {"UID", "WID", "WWID", "attempt"}])
    sql = f"INSERT INTO test_featurevalue_word ({','.join(DEST_COLUMNS)}) VALUES ({placeholders}) ON DUPLICATE KEY UPDATE {updates}"

    count = 0
    for row in rows:
        cur.execute(sql, tuple(row[col] for col in DEST_COLUMNS))
        count += 1
    return count


def main():
    conn = connect_db()
    cur = conn.cursor()

    source_rows = load_source_rows(cur)
    feature_rows = []
    for uid, wid, wwid, attempt, understand, data in source_rows:
        feature_rows.append(
            make_feature_row(
                int(uid), int(wid), int(wwid), int(attempt or 1), int(understand or 0), int(data.get("date_value") or 0), data
            )
        )

    inserted = upsert_features(cur, feature_rows)
    conn.commit()

    print(f"単語特徴量生成完了: {inserted} 件を test_featurevalue_word に反映しました。")

    cur.close()
    conn.close()


if __name__ == "__main__":
    main()

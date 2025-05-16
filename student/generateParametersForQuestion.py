import numpy as np
import mysql.connector
import re
import argparse
from datetime import datetime

# データベース接続情報を定数として設定
DB_HOST = 'localhost'          # 例: 'localhost'
DB_PORT = 3306                 # デフォルトのMySQLポート
DB_USER = 'root'      # MySQLユーザー名
DB_PASSWORD = 'Z3%k-W3udc'  # MySQLユーザーパスワード
DB_DATABASE = '2019su1'  # データベース名

def compute_features(data_rows, attempt):
    """
    データ行から特徴量を計算します。
    
    Args:
        data_rows (list of dict): SQLクエリから取得したデータ行のリスト。
        attempt (int): 試行回数。
        
    Returns:
        list: 計算された特徴量のリスト。
    """
    if not data_rows:
        print("データが存在しません。")
        return []

    # データをユーザーと質問ごとに整理
    data = {}
    for row in data_rows:
        user = row['UID']
        question = row['WID']
        if user not in data:
            data[user] = {}
        if question not in data[user]:
            data[user][question] = []
        data[user][question].append({
            'time': row['Time'],
            'x': row['X'],
            'y': row['Y'],
            'dd': row['DD'],
            'dpos': row['DPos'],
            'hLabel': row['hLabel'],
            'label': row['Label'],
            'hesitate': row['hesitate'],
            'understand': row['Understand'],
            'date': row['Date'],
            'check': row['check']
        })

    parametersPerQuestion = []

    for user in data.keys():
        for question in data[user].keys():
            params = sorted(data[user][question], key=lambda x: x['time'])  # 時間でソート

            # 初期値の設定
            understand = params[0]['understand']
            # date = params[0]['date']  # 修正前: dateを使用
            date = 0  # 修正箇所1: dateを0に設定
            check = params[0]['check']
            time_spent = params[-1]['time'] - params[0]['time']
            distance = 0
            averageSpeed = 0
            maxSpeed = 0
            answeringTime = 0
            thinkingTime = 0
            totalStopTime = 0
            maxStopTime = 0
            stopcount = 0
            totalDDIntervalTime = 0
            maxDDIntervalTime = 0
            maxDDTime = 0
            minDDTime = float('inf')
            DDCount = 0
            groupingDDCount = 0
            groupingCountbool = 0
            xUTurnCount = 0
            yUTurnCount = 0
            register_move_count1 = 0
            register_move_count2 = 0
            register_move_count3 = 0
            register_move_count4 = 0
            register01count1 = 0
            register01count2 = 0
            register01count3 = 0
            register01count4 = 0
            registerDDCount = 0
            xUTurnCountDD = 0
            yUTurnCountDD = 0
            FromlastdropToanswerTime = -1

            hesiwordnum = int(len(params[0]['hesitate'].split('#')))

            # 補助変数の初期化
            startTime = -1
            lastDragTime = -1
            lastDropTime = -1
            lastXDirection = 0
            lastYDirection = 0
            lastXDirectionDD = 0
            lastYDirectionDD = 0
            DDstartend = 0
            continuingStopTime = 0
            lastDragTime_anspara = 0

            for i in range(len(params)-1):
                current = params[i]
                next_p = params[i+1]

                # 距離の計算
                currentCoord = np.array([current['x'], current['y']])
                nextCoord = np.array([next_p['x'], next_p['y']])
                distance += np.linalg.norm(currentCoord - nextCoord)

                # 速度の計算
                delta_time = next_p['time'] - current['time']
                if delta_time > 0:
                    speed = np.linalg.norm(currentCoord - nextCoord) / delta_time
                    if speed > maxSpeed:
                        maxSpeed = speed

                # 解答開始時刻の取得
                if startTime == -1 and current['dd'] == 2:
                    startTime = current['time']

                # 静止時間の計算
                stopdistance = np.linalg.norm(np.array([next_p['x'] - current['x'], next_p['y'] - current['y']]))
                if stopdistance < 5:
                    stopTime = delta_time
                    totalStopTime += stopTime
                    continuingStopTime += stopTime
                    if continuingStopTime > 500:
                        stopcount += 1
                    if continuingStopTime > maxStopTime:
                        maxStopTime = continuingStopTime
                else:
                    continuingStopTime = 0

                # DD間時間の計算
                if current['dd'] == 2:  # drag時
                    lastDragTime = current['time']
                    if lastDropTime != -1:
                        DDIntervalTime = current['time'] - lastDropTime
                        if DDIntervalTime > maxDDIntervalTime:
                            maxDDIntervalTime = DDIntervalTime
                        totalDDIntervalTime += DDIntervalTime
                    if 215 < current['y'] <= 375 or current['y'] > 375:
                        register_drag_flag = 1
                    else:
                        register_drag_flag = 0

                # DD時間の計算
                if current['dd'] == 1:  # drop時
                    lastDropTime = current['time']
                    DDTime = current['time'] - lastDragTime
                    if DDTime > maxDDTime:
                        maxDDTime = DDTime
                    if DDTime < minDDTime:
                        minDDTime = DDTime
                    DDCount += 1

                    if register_drag_flag == 1:
                        if 215 < current['y'] <= 375 or current['y'] > 375:
                            register_move_count1 += 1
                        else:
                            register_move_count2 += 1
                    else:
                        if 215 < current['y'] <= 375 or current['y'] > 375:
                            register_move_count3 += 1
                        else:
                            register_move_count4 += 1

                # グルーピング回数の計算
                if current['dd'] == 2 and '#' in current['label']:
                    groupingDDCount += 1

                # Uターンの計算
                xUTurnDist = next_p['x'] - current['x']
                yUTurnDist = next_p['y'] - current['y']

                # マウスをドラッグ中のUターン判定
                if current['dd'] == 2:
                    DDstartend = 1
                elif current['dd'] == 1:
                    DDstartend = 0

                # X方向のUターン
                if xUTurnDist < -5 or xUTurnDist > 5:
                    if next_p['x'] - current['x'] < 0:
                        xDirection = 1
                    elif next_p['x'] - current['x'] > 0:
                        xDirection = -1
                    else:
                        xDirection = 0

                    if ((xDirection == 1 and lastXDirection == -1) or (xDirection == -1 and lastXDirection == 1)) and DDstartend == 0:
                        xUTurnCount += 1
                    if xDirection != 0:
                        lastXDirection = xDirection

                # Y方向のUターン
                if yUTurnDist < -5 or yUTurnDist > 5:
                    if next_p['y'] - current['y'] < 0:
                        yDirection = 1
                    elif next_p['y'] - current['y'] > 0:
                        yDirection = -1
                    else:
                        yDirection = 0

                    if ((yDirection == 1 and lastYDirection == -1) or (yDirection == -1 and lastYDirection == 1)) and DDstartend == 0:
                        yUTurnCount += 1
                    if yDirection != 0:
                        lastYDirection = yDirection

                # D&D中のUターンX
                if DDstartend == 1:
                    if next_p['x'] - current['x'] < 0:
                        xDirectionDD = 1
                    elif next_p['x'] - current['x'] > 0:
                        xDirectionDD = -1
                    else:
                        xDirectionDD = 0

                    if ((xDirectionDD == 1 and lastXDirectionDD == -1) or (xDirectionDD == -1 and lastXDirectionDD == 1)) and (xUTurnDist < -5 or xUTurnDist > 5):
                        xUTurnCountDD += 1
                    if xDirectionDD != 0:
                        lastXDirectionDD = xDirectionDD

                    # D&D中のUターンY
                    if next_p['y'] - current['y'] < 0:
                        yDirectionDD = 1
                    elif next_p['y'] - current['y'] > 0:
                        yDirectionDD = -1
                    else:
                        yDirectionDD = 0

                    if ((yDirectionDD == 1 and lastYDirectionDD == -1) or (yDirectionDD == -1 and lastYDirectionDD == 1)) and (yUTurnDist < -5 or yUTurnDist > 5):
                        yUTurnCountDD += 1
                    if yDirectionDD != 0:
                        lastYDirectionDD = yDirectionDD

            # 最後の行の処理
            last = params[-1]
            if last['dd'] == 2:
                if 215 < last['y'] <= 375 or last['y'] > 375:
                    register_drag_flag = 1
                else:
                    register_drag_flag = 0

            if last['dd'] == 1:
                DDTime = last['time'] - lastDragTime
                if DDTime > maxDDTime:
                    maxDDTime = DDTime
                if DDTime < minDDTime:
                    minDDTime = DDTime
                DDCount += 1

                if register_drag_flag == 1:
                    if 215 < last['y'] <= 375 or last['y'] > 375:
                        register_move_count1 += 1
                    else:
                        register_move_count2 += 1
                else:
                    if 215 < last['y'] <= 375 or last['y'] > 375:
                        register_move_count3 += 1
                    else:
                        register_move_count4 += 1

            if groupingDDCount != 0:
                groupingCountbool = 1
            else:
                groupingCountbool = 0

            if DDCount < 0:
                DDCount = 0

            if time_spent > 0:
                averageSpeed = distance / time_spent

            thinkingTime = startTime - params[0]['time'] if startTime != -1 else 0
            answeringTime = params[-1]['time'] - startTime if startTime != -1 else 0

            if register_move_count1 != 0:
                register01count1 = 1
            if register_move_count2 != 0:
                register01count2 = 1
            if register_move_count3 != 0:
                register01count3 = 1
            if register_move_count4 != 0:
                register01count4 = 1

            registerDDCount = register_move_count1 + register_move_count2 + register_move_count3 + register_move_count4

            FromlastdropToanswerTime = params[-1]['time'] - lastDropTime if lastDropTime != -1 else -1

            # minDDTimeの無限大を0に置き換え
            minDDTime = minDDTime if minDDTime != float('inf') else 0

            # 特徴量のリストを作成
            feature = [
                user,
                question,
                understand,
                attempt,
                date,  # 修正箇所1: dateを0に設定
                check,
                time_spent,
                distance,
                averageSpeed,
                maxSpeed,
                thinkingTime,
                answeringTime,
                totalStopTime,
                maxStopTime,
                totalDDIntervalTime,
                maxDDIntervalTime,
                maxDDTime,
                minDDTime,
                DDCount,
                groupingDDCount,
                groupingCountbool,
                xUTurnCount,
                yUTurnCount,
                register_move_count1,
                register_move_count2,
                register_move_count3,
                register_move_count4,
                register01count1,
                register01count2,
                register01count3,
                register01count4,
                registerDDCount,
                stopcount,
                xUTurnCountDD,
                yUTurnCountDD,
                FromlastdropToanswerTime
            ]

            # 修正箇所2: フィーチャーリストの長さチェックを36に修正
            if len(feature) != 36:
                print(f"Feature length mismatch for UID={user}, WID={question}: {len(feature)}")
                print(feature)
                continue  # このレコードをスキップするか、適切に処理してください
            else:
                # 修正箇所3: デバッグ用にフィーチャーの内容を表示
                print(f"Feature for UID={user}, WID={question}:")
                print(f"UID: {user}, WID: {question}, Understand: {understand}, Attempt: {attempt}")
                print(f"Date: {date}, Check: {check}, Time_spent: {time_spent}, Distance: {distance}")
                print(f"AverageSpeed: {averageSpeed}, MaxSpeed: {maxSpeed}, ThinkingTime: {thinkingTime}")
                print(f"AnsweringTime: {answeringTime}, TotalStopTime: {totalStopTime}, MaxStopTime: {maxStopTime}")
                print(f"TotalDDIntervalTime: {totalDDIntervalTime}, MaxDDIntervalTime: {maxDDIntervalTime}")
                print(f"MaxDDTime: {maxDDTime}, MinDDTime: {minDDTime}, DDCount: {DDCount}")
                print(f"GroupingDDCount: {groupingDDCount}, GroupingCountbool: {groupingCountbool}")
                print(f"xUTurnCount: {xUTurnCount}, yUTurnCount: {yUTurnCount}")
                print(f"Register_move_count1: {register_move_count1}, Register_move_count2: {register_move_count2}")
                print(f"Register_move_count3: {register_move_count3}, Register_move_count4: {register_move_count4}")
                print(f"Register01count1: {register01count1}, Register01count2: {register01count2}")
                print(f"Register01count3: {register01count3}, Register01count4: {register01count4}")
                print(f"RegisterDDCount: {registerDDCount}, Stopcount: {stopcount}")
                print(f"xUTurnCountDD: {xUTurnCountDD}, yUTurnCountDD: {yUTurnCountDD}")
                print(f"FromlastdropToanswerTime: {FromlastdropToanswerTime}")

            parametersPerQuestion.append(feature)

    return parametersPerQuestion

def insert_features(db_conn, features):
    """
    計算された特徴量をtest_featurevalueテーブルに挿入します。
    
    Args:
        db_conn (mysql.connector.connection_cext.CMySQLConnection): データベース接続オブジェクト。
        features (list): 挿入する特徴量のリスト。
    """
    if not features:
        print("挿入するデータがありません。")
        return

    cursor = db_conn.cursor()

    # 修正箇所4: INSERT文の予約語をバックティックでエスケープ
    insert_query = """
    INSERT INTO test_featurevalue (
        `UID`, `WID`, `Understand`, `attempt`, `date`, `check`, `Time`, `distance`, `averageSpeed`, `maxSpeed`,
        `thinkingTime`, `answeringTime`, `totalStopTime`, `maxStopTime`, `totalDDIntervalTime`,
        `maxDDIntervalTime`, `maxDDTime`, `minDDTime`, `DDCount`, `groupingDDCount`,
        `groupingCountbool`, `xUTurnCount`, `yUTurnCount`, `register_move_count1`, `register_move_count2`,
        `register_move_count3`, `register_move_count4`, `register01count1`, `register01count2`,
        `register01count3`, `register01count4`, `registerDDCount`, `stopcount`, `xUTurnCountDD`,
        `yUTurnCountDD`, `FromlastdropToanswerTime`
    ) VALUES (
        %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
        %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
        %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
        %s, %s, %s, %s, %s, %s
    )
    """

    # 挿入前に各レコードの長さを確認
    for idx, feature in enumerate(features):
        if len(feature) != 36:
            print(f"Error: Feature at index {idx} has {len(feature)} elements. Expected 36.")
            print(feature)
            continue

    try:
        # 修正箇所5: 挿入前にfeatureリストの内容とデータ型を出力
        for feature in features:
            print("Inserting feature:")
            for idx, value in enumerate(feature):
                print(f"Column {idx + 1}: {value} (Type: {type(value)})")
        
        cursor.executemany(insert_query, features)
        db_conn.commit()
        print(f"{len(features)} レコードを挿入しました。")
    except mysql.connector.Error as err:
        print(f"エラーが発生しました: {err}")
        db_conn.rollback()
    finally:
        cursor.close()

def fetch_data(db_conn, uid, wid, attempt):
    """
    指定されたuid, wid, attemptに基づいてデータを取得します。
    
    Args:
        db_conn (mysql.connector.connection_cext.CMySQLConnection): データベース接続オブジェクト。
        uid (str): ユーザーID。
        wid (str): 質問ID。
        attempt (int): 試行回数。
        
    Returns:
        list of dict: 取得したデータ行のリスト。
    """
    cursor = db_conn.cursor(dictionary=True)
    query = """
    SELECT 
        linedatamouse.UID,
        linedatamouse.WID,
        linedatamouse.Time,
        linedatamouse.X,
        linedatamouse.Y,
        linedatamouse.DD,
        linedatamouse.DPos,
        linedatamouse.hLabel,
        linedatamouse.Label,
        linedata.hesitate,
        linedata.Understand,
        linedata.Date,
        linedata.check 
    FROM 
        linedatamouse
    JOIN 
        linedata 
    ON 
        linedatamouse.UID = linedata.UID AND 
        linedatamouse.WID = linedata.WID AND 
        linedatamouse.attempt = linedata.attempt
    WHERE 
        linedatamouse.UID = %s AND 
        linedatamouse.WID = %s AND 
        linedatamouse.attempt = %s
    ORDER BY 
        linedatamouse.uid, linedatamouse.wid, linedatamouse.time;
    """

    try:
        cursor.execute(query, (uid, wid, attempt))
        rows = cursor.fetchall()
        return rows
    except mysql.connector.Error as err:
        print(f"データ取得中にエラーが発生しました: {err}")
        return []
    finally:
        cursor.close()

def main():
    parser = argparse.ArgumentParser(description="LMSの特徴量を計算してデータベースに挿入します。")
    parser.add_argument('--uid', required=True, help='ユーザーID')
    parser.add_argument('--wid', required=True, help='質問ID')
    parser.add_argument('--attempt', type=int, required=True, help='試行回数')
    args = parser.parse_args()

    uid = args.uid
    wid = args.wid
    attempt = args.attempt

    # データベースに接続
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            port=DB_PORT,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_DATABASE
        )
        print(f"データベースに接続しました: {DB_HOST}:{DB_PORT}/{DB_DATABASE}")
    except mysql.connector.Error as e:
        print(f"データベース接続エラー: {e}")
        return

    # データを取得
    data_rows = fetch_data(conn, uid, wid, attempt)
    print(f"取得したデータ行数: {len(data_rows)}")

    # 特徴量を計算
    features = compute_features(data_rows, attempt)

    # 特徴量を挿入
    insert_features(conn, features)

    # 接続を閉じる
    conn.close()
    print("データベース接続を閉じました。")

if __name__ == "__main__":
    main()

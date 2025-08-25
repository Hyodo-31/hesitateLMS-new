<?php
// feature_calculator.php

/**
 * 特定の解答セッションから特徴量を計算し、データベースに保存する。
 *
 * @param mysqli $conn データベース接続オブジェクト
 * @param int $uid ユーザーID
 * @param int $wid 問題ID
 * @return bool 成功した場合はtrue、失敗した場合はfalse
 */
function calculateAndSaveFeatures(mysqli $conn, int $uid, int $wid, int $attempt): bool
{
    // 1. 必要なデータをデータベースから取得
    // linedataから解答時間や迷い度などを取得
    $stmt_linedata = $conn->prepare("SELECT Time, Understand, attempt, Date FROM linedata WHERE UID = ? AND WID = ? AND attempt = ?");
    if (!$stmt_linedata) {
        error_log("Prepare failed (linedata): " . $conn->error);
        return false;
    }
    $stmt_linedata->bind_param("iii", $uid, $wid, $attempt);
    $stmt_linedata->execute();
    $result_linedata = $stmt_linedata->get_result();
    $linedata = $result_linedata->fetch_assoc();
    $stmt_linedata->close();

    if (!$linedata) {
        error_log("No data found in linedata for UID: $uid, WID: $wid");
        return false; // データがない場合は処理を中断
    }

    // ★競合状態の対策として、1秒待機する処理を追加
    sleep(2);

    // linedatamouseからマウス軌跡データを取得
    $stmt_mouse = $conn->prepare("SELECT Time, X, Y, DD, hLabel, Label FROM linedatamouse WHERE UID = ? AND WID = ? AND attempt = ? ORDER BY Time ASC");
     if (!$stmt_mouse) {
        error_log("Prepare failed (linedatamouse): " . $conn->error);
        return false;
    }
    $stmt_mouse->bind_param("iii", $uid, $wid, $attempt);
    $stmt_mouse->execute();
    $mouse_data_result = $stmt_mouse->get_result();
    $mouse_data = [];
    while ($row = $mouse_data_result->fetch_assoc()) {
        $mouse_data[] = $row;
    }
    $stmt_mouse->close();

    if (empty($mouse_data)) {
        error_log("No mouse data found for UID: $uid, WID: $wid");
        return false; // マウスデータがない場合は中断
    }

    // 2. 特徴量の計算 (generateParametersForQuestion.pyのロジックをPHPで再現)
    
    // 変数の初期化
    $time = $linedata['Time'];
    $understand = $linedata['Understand'];
    $attempt = $linedata['attempt'];
    $date = $linedata['Date'];
    $check = 0; // 0: 未処理
    
    $distance = 0;
    $maxSpeed = 0;
    $totalStopTime = 0;
    $maxStopTime = 0;
    $stopCount = 0;
    $totalDDIntervalTime = 0;
    $maxDDIntervalTime = 0;
    $DDCount = 0;
    $totalDDTime = 0;
    $maxDDTime = 0;
    $minDDTime = 99999;
    $groupingDDCount = 0;
    $groupingCountbool = 0;
    $xUTurnCount = 0;
    $yUTurnCount = 0;
    $xUTurnCountDD = 0;
    $yUTurnCountDD = 0;
    $register_move_count1 = 0;
    $register_move_count2 = 0;
    $register_move_count3 = 0;
    $register_move_count4 = 0;
    $register01count1 = 0;
    $register01count2 = 0;
    $register01count3 = 0;
    $register01count4 = 0;
    $registerDDCount = 0;
    $FromlastdropToanswerTime = 0;

    $prev_x = null;
    $prev_y = null;
    $prev_time = null;
    $prev_dd = 0;
    $dragStartTime = 0;
    $lastDropTime = 0;
    $startTime = 0;
    
    $x_direction = 0;
    $y_direction = 0;
    $prev_x_direction = 0;
    $prev_y_direction = 0;
    
    $drag_start_y_area = -1;

    foreach ($mouse_data as $i => $row) {
        if ($i === 0) {
            $prev_x = $row['X'];
            $prev_y = $row['Y'];
            $prev_time = $row['Time'];
            $prev_dd = $row['DD'];
            continue;
        }

        $current_x = $row['X'];
        $current_y = $row['Y'];
        $current_time = $row['Time'];
        $current_dd = $row['DD'];

        $time_diff = $current_time - $prev_time;
        $dist_diff = sqrt(pow($current_x - $prev_x, 2) + pow($current_y - $prev_y, 2));
        $distance += $dist_diff;

        // 速度計算
        if ($time_diff > 0) {
            $speed = $dist_diff / $time_diff;
            if ($speed > $maxSpeed) {
                $maxSpeed = $speed;
            }
        } else {
            $speed = 0;
        }

        // 静止時間計算
        if ($speed < 0.01) {
             $totalStopTime += $time_diff;
             if($time_diff > $maxStopTime) {
                 $maxStopTime = $time_diff;
             }
        }
        if($speed == 0){
            $stopCount++;
        }

        // DD関連の計算
        if ($prev_dd == 0 && $current_dd == 2) { // Drag開始
            $dragStartTime = $current_time;
            if ($startTime == 0) {
                $startTime = $current_time;
            }
            if ($lastDropTime != 0) {
                $dd_interval = $current_time - $lastDropTime;
                $totalDDIntervalTime += $dd_interval;
                if ($dd_interval > $maxDDIntervalTime) {
                    $maxDDIntervalTime = $dd_interval;
                }
            }
            // Drag開始時のY座標エリアを判定
            if ($current_y <= 130) $drag_start_y_area = 0; // 問題提示欄
            else if ($current_y <= 215) $drag_start_y_area = 4; // 解答欄
            else if ($current_y <= 295) $drag_start_y_area = 1; // レジスタ1
            else if ($current_y <= 375) $drag_start_y_area = 2; // レジスタ2
            else $drag_start_y_area = 3; // レジスタ3
        }
        
        if ($prev_dd == 2 && $current_dd == 1) { // Drop
            $DDCount++;
            $dd_time = $current_time - $dragStartTime;
            $totalDDTime += $dd_time;
            if ($dd_time > $maxDDTime) $maxDDTime = $dd_time;
            if ($dd_time < $minDDTime) $minDDTime = $dd_time;
            $lastDropTime = $current_time;

            // グループ化DDのカウント
            if (strpos($row['Label'], '#') !== false) {
                $groupingDDCount++;
                $groupingCountbool = 1;
            }

            // レジスタ移動のカウント
            $drop_y_area = -1;
            if ($current_y <= 130) $drop_y_area = 0;
            else if ($current_y <= 215) $drop_y_area = 4;
            else if ($current_y <= 295) $drop_y_area = 1;
            else if ($current_y <= 375) $drop_y_area = 2;
            else $drop_y_area = 3;

            if ($drag_start_y_area >= 1 && $drag_start_y_area <= 3 && $drop_y_area >= 1 && $drop_y_area <= 3) $register_move_count1++; // レジスタ -> レジスタ
            if ($drag_start_y_area >= 1 && $drag_start_y_area <= 3 && ($drop_y_area == 0 || $drop_y_area == 4)) $register_move_count2++; // レジスタ -> レジスタ外
            if (($drag_start_y_area == 0 || $drag_start_y_area == 4) && $drop_y_area >= 1 && $drop_y_area <= 3) $register_move_count3++; // レジスタ外 -> レジスタ
        }

        // Uターンカウント
        $x_direction = ($current_x > $prev_x) ? 1 : (($current_x < $prev_x) ? -1 : 0);
        $y_direction = ($current_y > $prev_y) ? 1 : (($current_y < $prev_y) ? -1 : 0);

        if ($prev_x_direction != 0 && $x_direction != 0 && $prev_x_direction != $x_direction) {
            $xUTurnCount++;
            if ($current_dd == 2) $xUTurnCountDD++;
        }
        if ($prev_y_direction != 0 && $y_direction != 0 && $prev_y_direction != $y_direction) {
            $yUTurnCount++;
            if ($current_dd == 2) $yUTurnCountDD++;
        }

        if ($x_direction != 0) $prev_x_direction = $x_direction;
        if ($y_direction != 0) $prev_y_direction = $y_direction;
        
        $prev_x = $current_x;
        $prev_y = $current_y;
        $prev_time = $current_time;
        $prev_dd = $current_dd;
    }

    // その他の最終計算
    $averageSpeed = ($time > 0) ? $distance / $time : 0;
    $thinkingTime = ($startTime != 0) ? $startTime - $mouse_data[0]['Time'] : $time;
    $answeringTime = ($startTime != 0) ? $mouse_data[count($mouse_data) - 1]['Time'] - $startTime : 0;
    $FromlastdropToanswerTime = ($lastDropTime != 0) ? $mouse_data[count($mouse_data) - 1]['Time'] - $lastDropTime : 0;
    if ($minDDTime == 99999) $minDDTime = 0;
    
    if ($register_move_count1 != 0) $register01count1 = 1;
    if ($register_move_count2 != 0) $register01count2 = 1;
    if ($register_move_count3 != 0) $register01count3 = 1;
    $registerDDCount = $register_move_count1 + $register_move_count2 + $register_move_count3;


    // 3. 計算した特徴量を `test_featurevalue` テーブルに挿入
    $sql_insert = "INSERT INTO test_featurevalue (UID, WID, Understand, attempt, date, check, Time, distance, averageSpeed, maxSpeed, thinkingTime, answeringTime, totalStopTime, maxStopTime, totalDDIntervalTime, maxDDIntervalTime, maxDDTime, minDDTime, DDCount, groupingDDCount, groupingCountbool, xUTurnCount, yUTurnCount, register_move_count1, register_move_count2, register_move_count3, register_move_count4, register01count1, register01count2, register01count3, register01count4, registerDDCount, xUTurnCountDD, yUTurnCountDD, FromlastdropToanswerTime) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_insert = $conn->prepare($sql_insert);
    if (!$stmt_insert) {
        error_log("Prepare failed (insert): " . $conn->error);
        return false;
    }
    
    // 型を指定: i = integer, d = double, s = string
    $stmt_insert->bind_param("iiiisiidddiiiiiiiiiiiiiiiiiiiiiiii",
        $uid, $wid, $understand, $attempt, $date, $check, $time, $distance, $averageSpeed, $maxSpeed,
        $thinkingTime, $answeringTime, $totalStopTime, $maxStopTime, $totalDDIntervalTime,
        $maxDDIntervalTime, $maxDDTime, $minDDTime, $DDCount, $groupingDDCount, $groupingCountbool,
        $xUTurnCount, $yUTurnCount, $register_move_count1, $register_move_count2, $register_move_count3,
        $register_move_count4, $register01count1, $register01count2, $register01count3, $register01count4,
        $registerDDCount, $xUTurnCountDD, $yUTurnCountDD, $FromlastdropToanswerTime
    );

    $success = $stmt_insert->execute();
    if (!$success) {
        error_log("Execute failed (insert): " . $stmt_insert->error);
    }
    $stmt_insert->close();

    return $success;
}

/*
// --- 使用例 ---
// このファイルが直接呼び出された場合にテスト実行する
// 例: http://localhost/teacher/feature_calculator.php?uid=30914025&wid=22

if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    
    require "../dbc.php"; // データベース接続情報

    header('Content-Type: text/plain; charset=utf-8');

    if (isset($_GET['uid']) && isset($_GET['wid'])) {
        $test_uid = (int)$_GET['uid'];
        $test_wid = (int)$_GET['wid'];

        echo "特徴量計算を開始します...\n";
        echo "UID: $test_uid, WID: $test_wid\n\n";

        $result = calculateAndSaveFeatures($conn, $test_uid, $test_wid);

        if ($result) {
            echo "特徴量の計算と保存に成功しました。\n";
        } else {
            echo "エラーが発生しました。詳細はサーバーのログを確認してください。\n";
        }
    } else {
        echo "UIDとWIDをGETパラメータで指定してください。\n";
        echo "例: feature_calculator.php?uid=12345&wid=67\n";
    }

    $conn->close();
}
*/
?>
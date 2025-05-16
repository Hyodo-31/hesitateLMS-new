<?php
// error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時

session_start();
$_SESSION["examflag"] = 0;
require "../dbc.php";

$MemberID = $_SESSION["MemberID"];

// セキュリティ: MemberIDが整数であることを確認
$MemberID = intval($MemberID);

$file_name = sys_get_temp_dir() . "/tem" . $MemberID . ".tmp";

// 追加: 「今回INSERTした (UID,WID,attempt)」 を覚えておく配列
$newAttempts = [];

if (is_file($file_name)) { 
    $text = fopen($file_name, 'r'); 
    if (!$text) {
        echo 'ファイルを開くことができませんでした';
        exit;
    }

    while (($lines = fgets($text)) !== false) {
        if ($lines) {
            // SQLクエリの実行
            $res = mysqli_query($conn, $lines);
            if (!$res) {
                // エラーメッセージの出力とログ保存
                $errorMsg = mysqli_error($conn);
                echo "データ追加エラー: " . htmlspecialchars($errorMsg) . "<br>";
                // ログファイルにエラーを記録する場合
                // file_put_contents('/path/to/error.log', "Error: $errorMsg\nQuery: $lines\n", FILE_APPEND);
                continue;
            }

            // (UID, WID, attempt) の抽出
            if (preg_match('/VALUES\s*\(\s*(\d+)\s*,\s*(\d+)\s*,.*,\s*(\d+)\s*\)$/i', $lines, $matches)) {
                $uid = intval($matches[1]);      // UID
                $wid = intval($matches[2]);      // WID
                $attempt = intval($matches[3]);  // attempt

                // 重複排除しながら格納
                $key = "{$uid}-{$wid}-{$attempt}";
                $newAttempts[$key] = ['uid' => $uid, 'wid' => $wid, 'attempt' => $attempt];
            }
        }
    }

    fclose($text);

    // ループ終了後
    if (!empty($newAttempts)) {
        foreach ($newAttempts as $info) {
            $uid     = $info['uid'];
            $wid     = $info['wid'];
            $attempt = $info['attempt'];

            // Pythonスクリプトのパス設定
            $pythonPath = 'python3'; // 実際のPythonインタプリタのパスに置き換えてください
            $scriptPath = 'generateParametersForQuestion.py'; // 実際のスクリプトのパスに置き換えてください

            // コマンドの組み立て
            $cmd = escapeshellcmd($pythonPath) . ' ' . escapeshellarg($scriptPath) . 
                   ' --uid ' . escapeshellarg($uid) . 
                   ' --wid ' . escapeshellarg($wid) . 
                   ' --attempt ' . escapeshellarg($attempt) . ' 2>&1';
            
            // Pythonスクリプトの実行
            $output = shell_exec($cmd);

            // ログ出力やエラーチェック
            if ($output === null) {
                echo "Pythonスクリプトの実行に失敗しました: UID={$uid}, WID={$wid}, Attempt={$attempt}<br>";
            } else {
                // 出力内容に基づいて成功/失敗を判断
                // 必要に応じて条件を追加
                echo "Pythonスクリプト実行結果 for UID={$uid}, WID={$wid}, Attempt={$attempt}:<br>";
                echo nl2br(htmlspecialchars($output)) . "<br>";
            }

            // ログファイルに出力を記録する場合
            // file_put_contents('/path/to/script.log', "Command: $cmd\nOutput: $output\n", FILE_APPEND);
        }
    }

    echo "正常にデータを書き終えました";
    
    // 一時ファイルの削除
    if (!unlink($file_name)) {
        echo "一時ファイルの削除に失敗しました。";
    }

    // 同時にuser_progressに書き込み（具体的な処理内容が不明なためコメント）
    // 例:
    // $progress = "some progress data";
    // mysqli_query($conn, "UPDATE user_progress SET progress='$progress' WHERE MemberID=$MemberID");
} else {
    echo 'データがありませんでした';
    exit;
}
?>

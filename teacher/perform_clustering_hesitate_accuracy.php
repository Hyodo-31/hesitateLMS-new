<?php
// エラーレポートの設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// lang.phpをインクルードして多言語対応機能を利用可能にする
include '../lang.php'; 

require "../dbc.php";
require "log_write.php";

// POSTデータを取得
$features = explode(',', $_POST['features']);
$clusterCount = intval($_POST['clusterCount']);
$studentData = json_decode($_POST['studentData'], true);

// クラスタリング用のCSVデータを一時ファイルに保存
$csvFile = tempnam(sys_get_temp_dir(), 'clustering_') . '.csv';
$file = fopen($csvFile, 'w');

// CSVのヘッダーを書き込む
fputcsv($file, array_merge(['uid'], $features));

// データを書き込む
foreach ($studentData as $student) {
    $row = ['uid' => $student['uid']];
    foreach ($features as $feature) {
        $row[] = $student[$feature];
    }
    fputcsv($file, $row);
}
fclose($file);

// Pythonスクリプトを呼び出してクラスタリングを実行
$pythonScript = './perform_clustering.py';
$command = escapeshellcmd("python $pythonScript $csvFile $clusterCount");
$output = shell_exec($command);

// 結果をJSONとして返す
if ($output) {
    // クラスタリング成功時にログを記録
    logActivity($conn, $_SESSION['MemberID'], 'clustering_completed', ['features' => $features, 'cluster_count' => $clusterCount], $output);
    echo $output;
} else {
    // エラーメッセージを多言語対応させる
    echo json_encode(['error' => translate('perform_clustering_hesitate_accuracy.php_38行目_クラスタリング処理中にエラーが発生しました')]);
}

// 一時ファイルを削除
unlink($csvFile);
?>
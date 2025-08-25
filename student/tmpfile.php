<?php
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時

// データベース接続を読み込む
require "../dbc.php";

session_start();

// セッションやGETパラメータから必要な情報を取得
$MemberID = $_SESSION["MemberID"];
$attempt = $_SESSION["attempt"];
$WID = $_GET['param1'];
$Time = $_GET['param2'];
$X = $_GET['param3'];
$Y = $_GET['param4'];
$DD = $_GET['param5'];
$DPos = $_GET['param6'];
$hLabel = $_GET['param7'];
$Label = $_GET['param8'];

// linedatamouseテーブルに直接データを挿入する
$sql = "INSERT INTO linedatamouse (UID, WID, Time, X, Y, DD, DPos, hLabel, Label, attempt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if ($stmt) {
    // プレースホルダに値をバインド (s=string, i=integer, d=double)
    // データの型に合わせて修正
    $stmt->bind_param("iiiiiiissi", 
        $MemberID, 
        $WID, 
        $Time, 
        $X, 
        $Y, 
        $DD, 
        $DPos, 
        $hLabel, 
        $Label, 
        $attempt
    );

    // SQLを実行
    $stmt->execute();
    $stmt->close();
} else {
    // エラーハンドリング (開発中はエラー内容を確認すると良い)
    // error_log("DB prepare error: " . $conn->error);
}

// データベース接続を閉じる
$conn->close();

?>
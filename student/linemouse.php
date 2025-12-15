<?php
/**
 * Error reporting level
 */
error_reporting(E_ALL);   // デバッグ時
//error_reporting(0);   // 運用時

include '../lang.php'; // 多言語対応ファイルをインクルード
//session_start(); // lang.phpで開始済み

require "../dbc.php";

$MemberID = $_SESSION["MemberID"];
$FName4 = "linedatamouse";

// ▼▼▼ 追加：一時ファイルの初期化処理 ▼▼▼ このプログラムで使ってる部分ここだけ
// ページが読み込まれた時点で、そのユーザーの一時ファイルを空にする
$TempFileName = sys_get_temp_dir() . "/tem" . $MemberID . ".tmp";
file_put_contents($TempFileName, "");
// ▲▲▲ 追加ここまで ▲▲▲

$sql = "show tables like '".$FName4."'";
$result = mysqli_query($conn, $sql);
if(!$result) {
    die(translate('linemouse.php_15行目_テーブル存在確認エラー') . mysqli_error($conn));
}

if (mysqli_num_rows($result)) {
    echo sprintf(translate('linemouse.php_18行目_こんにちは'), htmlspecialchars($_SESSION["MemberName"], ENT_QUOTES, 'UTF-8'));
} else {
    $Question = "CREATE TABLE ".$FName4." ( AID int(5) foreign key references linedata(AID), Time int(8), X int(4), Y int(4), DD int(1), DPos int(1),hLabel varchar(2), Label varchar(50), addk int(1));";
    $res = mysqli_query($conn,$Question) or die(translate('linemouse.php_20行目_テーブル作成失敗エラー'));

	// echo sprintf(translate('linemouse.php_22行目_初回アクセス'), $FName4);
}
?>
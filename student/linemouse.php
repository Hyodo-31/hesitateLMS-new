<?php
/**
 * Error reporting level
 */
error_reporting(E_ALL);   // デバッグ時
//error_reporting(0);   // 運用時
session_start();

require "../dbc.php";

$MemberID = $_SESSION["MemberID"];
$FName4 = "linedatamouse";

$sql = "show tables like '".$FName4."'";
$result = mysqli_query($conn,$sql);
if(!$result) {
    die("linedatamouseテーブルの存在確認エラー".mysqli_error($conn));
}
if (mysqli_num_rows($result)) {
    echo 'こんにちは'.$_SESSION["MemberName"].'さん';
} else {
    $Question = "CREATE TABLE ".$FName4." ( AID int(5) foreign key references linedata(AID), Time int(8), X int(4), Y int(4), DD int(1), DPos int(1),hLabel varchar(2), Label varchar(50), addk int(1));";
    $res = mysqli_query($conn,$Question) or die("linedatamouseテーブル作成失敗エラー");

	//echo "はじめてのアクセスなので、".$FName4."をつくりました。";
}
?>
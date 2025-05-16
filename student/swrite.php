<?php
//DB書き込み
/**
 * Error reporting level
 */
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時

session_start();
$_SESSION["examflag"] = 0;
require"../dbc.php";

$MemberID = $_SESSION["MemberID"];

$file_name = sys_get_temp_dir()."/tem".$MemberID.".tmp";

print 'こんにちは'.$_SESSION["MemberName"].'さん';
if(is_file($file_name)){
    unlink($file_name);
}
?>
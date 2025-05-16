<?php
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時
session_start();

//linedatamouseへの書き込み
$FName2 = "linedatamouse";
$MemberID = $_SESSION["MemberID"];

$str = "INSERT INTO ".$FName2." VALUES(".$MemberID.",".$_GET['param1'].",".$_GET['param2'].",".$_GET['param3'].
	",".$_GET['param4'].",".$_GET['param5'].",".$_GET['param6'].",\"".$_GET['param7']."\",\"".
	$_GET['param8'].",".$_GET['param9']."\",NULL,NULL)";

//ファイル書き込みコード
$TempFileName = sys_get_temp_dir()."/tem".$MemberID.".tmp";
file_put_contents($TempFileName,$str."\n",FILE_APPEND);
echo file_get_contents($TempFileName);

?>
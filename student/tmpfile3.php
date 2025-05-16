<?php

error_reporting(E_ALL);   // デバッグ時
//error_reporting(0);   // 運用時

//lastdayへの書き込み
session_start();
$FName3 = "lastday";
$MemberID = $_SESSION["MemberID"];

if($_GET['param1'] == 1){
$str = "INSERT INTO ".$FName3." VALUES(".$MemberID.",\"".$_GET['param3']."\",".$_GET['param2'].")";
}
else{
$str = "UPDATE ".$FName3." SET Time = ".$_GET['param2']." WHERE Date = \"".$_GET['param3']."\"";
}

//ファイル書き込みコード
$TempFileName = sys_get_temp_dir()."/tem".$MemberID.".tmp";
file_put_contents($TempFileName,$str."\n",FILE_APPEND);
echo file_get_contents($TempFileName);

?>
<?php
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時


require "../dbc.php";

//linedataへの書き込み
session_start();
$FName = "linedata";
$MemberID = $_SESSION["MemberID"];
$AccessDate = $_SESSION["AccessDate"];
$attempt = $_SESSION["attempt"];
//ここまで

//$str = "INSERT IGNORE INTO ".$FName." (UID,WID,Date,TF,Time,Understand,EndSentence,hesitate,comments,check) VALUES(".$MemberID.",".$_GET['param1'].",\"".$_GET['param2']."\",".$_GET['param3'].",".$_GET['param4'].",".$_GET['param5'].",\"".$_GET['param6']."\",\"".$_GET['param7']."\",\"".$_GET['param8']."\",".$_GET['param9'].")";

$str = "INSERT INTO ".$FName." (UID, WID, Date, TF, Time, Understand, EndSentence, hesitate, hesitate1, hesitate2, comments, `check`, test_id,attempt)"
        ." VALUES(".$MemberID.",".$_GET['param1'].",\"".$_GET['param2']."\",".$_GET['param3'].",".$_GET['param4'].",".$_GET['param5'].",\"".$_GET['param6']."\",\"".$_GET['param7']."\",\"".$_GET['param8']."\",\"".$_GET['param9']."\",\"".$_GET['param10']."\",".$_GET['param11'].",".$_SESSION['Qid'].",".$attempt.")";

//ファイル書き込みコード
$TempFileName = sys_get_temp_dir()."/tem".$MemberID.".tmp";
file_put_contents($TempFileName,$str."\n",FILE_APPEND);
echo file_get_contents($TempFileName);

?>
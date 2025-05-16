<?php
   
error_reporting(E_ALL);
session_start();
if(!isset($_SESSION["MemberName"])){
require"notlogin";
session_destroy();
exit;
}
if($_SESSION["examflag"] == 1){
	require"overlap.php";
	exit;
}else{
$_SESSION["examflag"] = 2;
$_SESSION["page"] = "ques";
}



require "dbc.php";

$FName2 = "hearing";
$UID = $_SESSION["MemberID"];

$sql = "INSERT INTO ".$FName2." VALUES(".$UID.",".$_GET['param1'].",\"".$_GET['param2']."\",\"".$_GET['param3']."\")";

//$res = mysql_query($sql, $conn) or die("Member抽出エラー");
/*
//header('Content-Type: application/json; charset=utf-8');
//echo json_encode($sql);
//echo json_encode($res);
//echo $sql;
*/
echo $sql;
$TempFileName = sys_get_temp_dir()."/tem".$UID.".tmp";
file_put_contents($TempFileName,$sql."\n",FILE_APPEND);
echo file_get_contents($TempFileName);


?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<?php
session_start();
if(!isset($_SESSION["MemberName"])){ //ログインしていない場合
	require"notlogin.html";
	session_destroy();
	exit;
}
$_SESSION["examflag"] = 0;
?>


<html>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<head>
<link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
	<title>問題新規登録</title>
    <style type="text/css">

input {
font-size: 100%;
}

</style>

</head>

<body>
<div align="center">
	<FONT size="6">問題新規登録</FONT>
	</br>
<?php
session_start();
require "../../dbc.php";
$sql = "select MAX(WID) as maxwid from question_info";
$res = mysqli_query($conn,$sql) or die("接続エラー");
$row = mysqli_fetch_array($res);

$mode = $_GET["mode"];
$_SESSION["mode"] = $mode;

//echo $mode."<br>";

if($_SESSION["mode"] == 0){
$dtcnt = $row["maxwid"];
$dtcnt = $dtcnt + 1;
$_SESSION["dtcnt"] = $dtcnt;
}else{
$_SESSION["dtcnt"] = $_SESSION["dtcnt"];
}


if(isset($_POST["Japanese"])){
	$Japanese = $_SESSION["Japanese"];
}
if(isset($_POST["Sentence"])){
	$Sentence = $_SESSION["Sentence"];
}

//echo $_SESSION["mode"];

?>
<form action = "entry.php" method="post">
      <b>**　日本語文　**</b><br>※日本語で入力して下さい。<br><br><input type="text" name="Japanese" size="50" class="input"><br><br>
<b>**　英　文　**</b><br>※英語（半角）で入力して下さい。<br><br><input type="text" name="Sentence" size="50" style="ime-mode:disabled;" class="input"><br><br>
　　　<input type="submit" name="exe" value="登録" class="button">
　　　<input type="reset" name="exe" value="リセット" class="button">
</form>
<br><br>
<form action = "stop.php" method="post">
　　　<input type="submit" name="exe" value="登録を中止する" class="btn_mini">
</form>
</br>
<font size="4" color="red"><u>問題登録</u></font>
	＞区切り決定＞固定ラベル決定＞初期順序決定＞登録
</br>

</div>
</body>
</html>

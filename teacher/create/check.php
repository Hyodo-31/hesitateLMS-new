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
	<title>登録</title>
	<link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>
<div align="center">
	<FONT size="6">登録画面</FONT>
	</br>
<?php
session_start();
require "../../dbc.php";

$Japanese = $_SESSION["Japanese"];
$Sentence = $_SESSION["Sentence"];
$divide2 =$_SESSION["divide2"];
$dtcnt = $_SESSION["dtcnt"];
$start = $_SESSION["start"];
$fix = $_SESSION["fix"];
$view_Sentence = $_SESSION["view_Sentence"];
$num = $_SESSION["num"];
$level = $_SESSION["level"];
$pro = $_SESSION["pro"];

if(strstr($Sentence,"\'")){
}else if(strstr($Sentence,"'")){
    $_SESSION["Sentence"] = str_replace("'","\'",$Sentence);
}
if(strstr($start,"\'")){
}else if(strstr($start,"'")){
    $_SESSION["start"] = str_replace("'","\'",$start);
}
if(strstr($divide2,"\'")){
}else if(strstr($divide2,"'")){
    $_SESSION["divide2"] = str_replace("'","\'",$divide2);
}
?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 5>
<b>問題番号</b>：<?php echo $dtcnt; ?></br>
<b>日本文</b>：<?php echo $Japanese; ?></br>
<b>問題文</b></b>：<?php echo $view_Sentence; ?></br>
<b>初期順序</b>：<?php echo $start; ?></br>

</font>
</td></tr></table><br>
<?php
$property = "#".$level.$pro;
$_SESSION["property"] = $property;
?>
<b>登録を行いますか？</br></br></b>
</font>
<form method="post" action="insert.php">
</br>
<input type="submit" value="登録" class="button"/><br><br>
<input type="button" value="1つ前に戻る" onclick="history.back();"class="btn_mini">
</form>
<br><br>
<form action = "stop.php" method="post">
<input type="submit" name="exe" value="登録を中止する" class="button">
</form>	

<a href="javascript:history.go(-8);">問題登録</a>
＞
<a href="javascript:history.go(-6);">区切り決定</a>
＞
<a href="javascript:history.go(-4);">固定ラベル決定</a>
＞
<a href="javascript:history.go(-2);">初期順序決定</a>	
＞
<font size="4" color="red"><u>登録</u></font>
</br>

</div>
</body>
</html>
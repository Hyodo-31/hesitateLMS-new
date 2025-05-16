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
	<title>区切り決定</title>
    <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>
<div align="center">
	<FONT size="6">区切り決定</FONT>
	</br>
<?php
session_start();
require "../../dbc.php";
$Japanese = $_SESSION["Japanese"];
$Sentence = $_SESSION["Sentence"];
$dtcnt = $_SESSION["dtcnt"];
echo "<br>";

$divide = str_replace(".","",$Sentence);
$divide = str_replace("?","",$divide);  
$divide = str_replace("!","",$divide); //英文の末尾(.?!)を取る
$divide = str_replace(" ","|",$divide);  //区切るところに|を入れる
$_SESSION["divide"] = $divide;
?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 4>
<b>日本文</b>：<?php echo $Japanese; ?></br>
<b>問題文</b>：<?php echo $Sentence; ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b>単語を区切る場所にチェックを入れてください。</br></br></b>
</font>



<?php
$a = explode("|",$divide);
$len = count($a);
//echo $divide."<br><br>";
?>



<form method="post" action="divide_rec.php">
<?php
echo $a[0];
for ($i = 1; $i < $len; $i++){
?>
<input type="checkbox" name="check[]" value="<?php echo $i; ?>" checked><?php echo $a[$i]; ?>
<?php
}
echo "<br><br>";
?>
<input type="submit" value="決定" class="button"/><br>
</form>

<form action = "stop.php" method="post">
<input type="submit" name="exe" value="登録を中止する" class="button">
</form>
<br>
<br>
<a href="javascript:history.go(-2);">問題登録</a>
＞
<font size="4" color="red"><u>区切り決定</u></font>
＞固定ラベル決定＞初期順序決定＞登録
</br>

</div>
</body>
</html>
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
	<title>固定ラベル決定</title>
	<link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />   
</head>

<body>
<div align="center">
	<FONT size="6">固定ラベル決定</FONT>
	</br>
<?php
session_start();
require "../../dbc.php";
$Japanese = $_SESSION["Japanese"];
$Sentence = $_SESSION["Sentence"];
$divide2 = $_SESSION["divide2"];
//$start = $_SESSION["start"];
echo "<br>";

?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 4>
<b>日本文</b>：<?php echo $Japanese; ?></br>
<b>問題文</b>：<?php echo $Sentence; ?></br>
<b>区切り</b>：<?php echo $divide2; ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b>単語を固定する場所にチェックを入れてください。</br></br></b>
</font>



<?php
$a = explode("|",$divide2);
$len = count($a);
//echo $divide."<br><br>";
?>

<form method="post" action="fix_rec.php">

<?php
for ($i = 0; $i < $len; $i++){
?>
<input type="checkbox" name="rock[]" value="<?php echo $i; ?>"><?php echo $a[$i]; ?>
	
<?php
}
?>
<br><br>
<input type="submit" value="決定" class="button"/>
</form>
<br><br>
<form action = "stop.php" method="post">
<input type="submit" name="exe" value="登録を中止する" class="btn_mini">
</form>

<a href="javascript:history.go(-4);">問題登録</a>
＞
<a href="javascript:history.go(-2);">区切り決定</a>
＞
<font size="4" color="red"><u>固定ラベル決定</u></font>
＞初期順序決定＞登録
</br>

</div>
</body>
</html>
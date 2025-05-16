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
$divide =$_SESSION["divide"];
$dtcnt = $_SESSION["dtcnt"];

echo "<br>";

?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 4>
<b>日本文</b>：<?php echo $Japanese; ?></br>
<b>問題文</b>：<?php echo $Sentence; ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b>区切りを以下のように決定しました。</br></br></b>
</font>

<?php
$a =array();
$a = explode("|",$divide);
$len = count($a);
$divide2 = $a[0];
//echo $divide."<br><br>";
if(isset($_POST["check"])){
$check = $_POST["check"];
}
//echo $check[0]."eee";
$j=0;
for ($i = 1; $i < $len; $i++){
	if($check[$j] == $i){
		$divide2 = $divide2."|".$a[$i];
		$j++;
	}else{
		$divide2 = $divide2." ".$a[$i];
	}
}
$array_divide2=explode("|",$divide2);
$_SESSION["divide2"]=$divide2;
$_SESSION["wordnum"] = count($array_divide2);
echo $divide2;
//echo $_SESSION["wordnum"];
echo "<br><br>";
?>



<form method="post" action="fix.php">
<input type="submit" value="固定ラベル決定画面へ" class="button"/><br><br>
<input type="button" value="戻る" onclick="history.back(); "class="btn_mini">
</form>
	

<a href="javascript:history.go(-3);">問題登録</a>
＞
<font size="4" color="red"><u>区切り決定</u></font>
＞固定ラベル決定＞初期順序決定＞登録
</br>

</div>
</body>
</html>

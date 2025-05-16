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
	<title>初期順序決定</title>
  <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
<script type="text/javascript">
<!--
function gopage(){//ラジオボタンでの指定先のphpファイルにジャンプするための関数
  for(var i=0; i<document.forms[0].group.length; i++){
    if(document.forms[0].group[i].checked == true){
         url = document.forms[0].group[i].value;
         location.href = url;
    }
  }
}
 
// -->
</script>
</head>

<body>
<div align="center">
	<FONT size="6">初期順序決定</FONT>
	</br>
<?php
session_start();
require "../../dbc.php";
$Japanese = $_SESSION["Japanese"];
$Sentence = $_SESSION["Sentence"];
$dtcnt = $_SESSION["dtcnt"];
$divide2 = $_SESSION["divide2"];
$view_Sentence = $_SESSION["view_Sentence"];
echo "<br>";

?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 4>
<b>日本文</b>：<?php echo $Japanese; ?></br>
<b>問題文</b>：<?php echo $Sentence; ?></br>
<b>区切り</b>：<?php echo $view_Sentence; ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b>初期順序を決める方法を選択して下さい。</br></br></b>
</font>

<form>
    <input type="radio" name="group" value="ques.php" checked="true">任意指定
    <input type="radio" name="group" value="alpsort.php">アルファベット順
 　 <input type="radio" name="group" value="randsort.php">ランダム
 　 <br><br>
    <input type="button" value="選択" onclick="gopage()" class="button">
  </form>

<br><br>
<form action = "stop.php" method="post">
<input type="submit" name="exe" value="登録を中止する" class="btn_mini">
</form>


<a href="javascript:history.go(-6);">問題登録</a>
＞
<a href="javascript:history.go(-4);">区切り決定</a>
＞
<a href="javascript:history.go(-2);">固定ラベル決定</a>
＞
<font size="4" color="red"><u>初期順序決定</u></font>
＞登録
</br>

</div>
</body>
</html>
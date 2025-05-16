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
$divide2 =$_SESSION["divide2"];
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
<b>固定ラベルを以下のように決定しました。</br></br></b>


<?php
$a = explode("|",$divide2);
$len = count($a);
//echo $divide."<br><br>";
if(isset($_POST["rock"])){
$rock = $_POST["rock"];
}
if(isset($rock)){
	$_SESSION["rock"] = $rock;
}

if(isset($rock)){
	if($rock[0] ==""){//固定ラベルがないときは-1を代入しておく（後の処理分岐用に用意）
		$rock[0] =-1;
	}
}
//echo "固定ラベル".$rock[0]."<br>";
$j=0;
$k=0;
$view_Sentence ="";
for ($i = 0; $i < $len; $i++){
	if(isset($rock[$j]) && $rock[$j] == $i){
		if($k==0){
		$fix = $rock[$j];
		//$fixlabel = $a[$i];
		$k++;
		}else{
		$fix = $fix."#".$rock[$j];
		//$fixlabel = $fixlabel.",".$a[$i];
		}

		if($i == 0){
			$view_Sentence = "[".$a[$i]."]";
		}else{
			$view_Sentence = $view_Sentence."|[".$a[$i]."]";
		} 
		$j++;
	}else{
        if($i ==0){
            $view_Sentence = $a[$i];
        }else{
            $view_Sentence = $view_Sentence."|".$a[$i];
        }
    }
}

echo $view_Sentence."<br>";
if(isset($fix)){
}else{
$fix=-1;
}
//echo "<br>";
//echo $fix;
echo "<br><br>";
$_SESSION["fix"] = $fix;
$_SESSION["view_Sentence"]=$view_Sentence;
?>
</font>


<form method="post" action="start.php">

<input type="submit" value="決定"  class="button"/><br><br>
<input type="button" value="戻る" onclick="history.back();" class="btn_mini">
</form>


<a href="javascript:history.go(-5);">問題登録</a>
＞
<a href="javascript:history.go(-3);">区切り決定</a>
＞
<font size="4" color="red"><u>固定ラベル決定</u></font>
＞初期順序決定＞登録
</br>

</div>
</body>
</html>
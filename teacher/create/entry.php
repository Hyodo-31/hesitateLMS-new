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
	<title>確認</title>
    <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>
<div align="center">
	<FONT size="6">確認</FONT>
	</br>
<?php
session_start();
require "../../dbc.php";

$Japanese = $_POST["Japanese"];
$Sentence = $_POST["Sentence"];
$dtcnt = $_SESSION["dtcnt"];
$Sentence = str_replace("'","\'",$Sentence);  //'のSQL用変換処理
$Sentence = str_replace("\"","\\\"",$Sentence); //"のSQL用変換処理
//エラー処理のためのフラグ
$flag = array();
$flagmessage = array("<b>※日本文は「。」または「?」で終了してください。</b><br><br>",
					"<b>※英文は「.」または「?」または「!」で終了してください。</b><br><br>",
					"<b>※値を入力してください。</b><br><br>"
						);
$i = 0;
$error = 0;

if (preg_match("/。$/u", $Japanese) || preg_match("/\?$/u", $Japanese) || preg_match("/？$/u", $Japanese)) { // 日本文の末尾が.か?で終わっていなかったら
} else {
    $flag[0] = 1;
}

if (preg_match("/\.$/", $Sentence) || preg_match("/\?$/", $Sentence) || preg_match("/!$/", $Sentence)) { // 英文の終わりが.か?か!で終わっていなかったら
} else {
    $flag[1] = 1;
}
if(empty($Japanese) || empty($Sentence)){
	$flag[2] = 1;
	$flag[0] = 0;
	$flag[1] = 0;
}

//エラーメッセージ出力
for($i=0; $i<=2; $i++){
	if(isset($flag[$i]) && $flag[$i] == 1){
		$error = 1; echo $flagmessage[$i];
	}
}
//エラーが出ていたら戻るボタン表示
if($error == 1){echo '<a href="new.php">戻る</a>';}


?>
<p style="width:50%; margin-left:auto;margin-right:auto;text-align:left;">
<?php
if($error == 0){
	//確認画面
	echo "<b>**以下の内容で登録します**</b><br><br>";
	echo "<b>問題番号</b>:".$dtcnt."<br>";
	echo "<b>問題文</b>：".$Japanese."<br>";
	echo "<b>英文</b>：".$Sentence."<br>";
	
?>
</p>
<form method="post" action="divide.php">
<?php
$_SESSION["Japanese"] = $Japanese;
$_SESSION["Sentence"] = $Sentence;
?>
<input type="submit" value="決定（区切り決定画面へ）" class="button"/><br><br>
<input type="button" value="戻る" onclick="history.back();" class="btn_mini">
</form>

<?php
}
?>
</br>
<font size="4" color="red"><u>問題登録</u></font>
	＞区切り決定＞固定ラベル決定＞初期順序決定＞登録
</br>


</div>
</body>
</html>
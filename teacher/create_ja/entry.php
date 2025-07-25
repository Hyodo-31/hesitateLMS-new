<?php
// session_start(); lang.phpでセッションスタート
require "../../lang.php";

if(!isset($_SESSION["MemberName"])){ //ログインしていない場合
	require"notlogin.html";
	//session_destroy();
	exit;
}
$_SESSION["examflag"] = 0;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title><?= translate('entry.php_14行目_確認') ?></title>
    <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>
<div align="center">
	<FONT size="6"><?= translate('entry.php_20行目_確認') ?></FONT>
	</br>
<?php
// session_start(); lang.phpで処理済み
require "../../dbc.php";

$English = $_POST["English"];
$Sentence = $_POST["Sentence"];
$dtcnt = $_SESSION["dtcnt"];
$Sentence_escaped_sql = str_replace("'","\'",$Sentence);  //'のSQL用変換処理
$Sentence_escaped_sql = str_replace("\"","\\\"",$Sentence_escaped_sql); //"のSQL用変換処理

//エラー処理のためのフラグ
$flag = array();
$flagmessage = array(
	"<b>※" . translate('entry.php_31行目_英文は...で終了') . "</b><br><br>",
    "<b>※" . translate('entry.php_30行目_日本文は...で終了') . "</b><br><br>",
    "<b>※" . translate('entry.php_32行目_値を入力してください') . "</b><br><br>"
);
$i = 0;
$error = 0;

if (preg_match("/\.$/", $English) || preg_match("/\?$/", $English) || preg_match("/!$/", $English)) { // 英文の終わりが.か?か!で終わっていなかったら
} else {
    $flag[0] = 1;
}

if (preg_match("/。$/u", $Sentence) || preg_match("/\?$/u", $Sentence) || preg_match("/？$/u", $Sentence)) { // 日本文の末尾が.か?で終わっていなかったら
} else {
    $flag[1] = 1;
}
if(empty($English) || empty($Sentence)){
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
if($error == 1){echo '<a href="new.php">' . translate('entry.php_52行目_戻る') . '</a>';}


?>
<p style="width:50%; margin-left:auto;margin-right:auto;text-align:left;">
<?php
if($error == 0){
	//確認画面
	echo "<b>**" . translate('entry.php_58行目_以下の内容で登録します') . "**</b><br><br>";
	echo "<b>" . translate('entry.php_59行目_問題番号') . "</b>:" . htmlspecialchars($dtcnt, ENT_QUOTES, 'UTF-8') . "<br>";
	echo "<b>" . translate('entry.php_60行目_問題文') . "</b>：" . htmlspecialchars($English, ENT_QUOTES, 'UTF-8') . "<br>";
	echo "<b>" . translate('entry.php_61行目_日本文') . "</b>：" . htmlspecialchars($Sentence, ENT_QUOTES, 'UTF-8') . "<br>";
	
?>
</p>
<form method="post" action="divide.php">
<?php
$_SESSION["English"] = $English;
$_SESSION["Sentence"] = $Sentence_escaped_sql;
?>
<input type="submit" value="<?= translate('entry.php_69行目_決定_区切り決定画面へ') ?>" class="button"/><br><br>
<input type="button" value="<?= translate('entry.php_70行目_戻る') ?>" onclick="history.back();" class="btn_mini">
</form>

<?php
}
?>
</br>
<font size="4" color="red"><u><?= translate('entry.php_76行目_問題登録') ?></u></font>
	＞<?= translate('entry.php_77行目_区切り決定') ?>＞<?= translate('entry.php_77行目_固定ラベル決定') ?>＞<?= translate('entry.php_77行目_初期順序決定') ?>＞<?= translate('entry.php_77行目_登録') ?>
</br>


</div>
</body>
</html>
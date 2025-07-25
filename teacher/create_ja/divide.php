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
	<title><?= translate('divide.php_14行目_区切り決定') ?></title>
    <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>
<div align="center">
	<FONT size="6"><?= translate('divide.php_20行目_区切り決定') ?></FONT>
	</br>
<?php
// session_start(); // lang.phpで処理済み
require "../../dbc.php";
$English = $_SESSION["English"];
$Sentence = $_SESSION["Sentence"];
$dtcnt = $_SESSION["dtcnt"];
echo "<br>";

$divide = str_replace("。","",$Sentence);
$divide = str_replace("?","",$divide);  
$divide = str_replace("!","",$divide); //日本文の末尾(。?!？！)を取る
$divide = str_replace("？","",$divide);
$divide = str_replace("！","",$divide);
$divide = str_replace(" ","|",$divide);  //区切るところに|を入れる
$_SESSION["divide"] = $divide;
?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 4>
<b><?= translate('divide.php_38行目_英文') ?></b>：<?php echo htmlspecialchars($English, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('divide.php_39行目_問題文') ?></b>：<?php echo htmlspecialchars($Sentence, ENT_QUOTES, 'UTF-8'); ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b><?= translate('divide.php_44行目_単語を区切る場所にチェック') ?><br><br></b>
</font>



<?php
$a = explode("|",$divide);
$len = count($a);
//echo $divide."<br><br>";
?>



<form method="post" action="divide_rec.php">
<?php
echo htmlspecialchars($a[0], ENT_QUOTES, 'UTF-8');
for ($i = 1; $i < $len; $i++){
?>
<input type="checkbox" name="check[]" value="<?php echo $i; ?>" checked><?php echo htmlspecialchars($a[$i], ENT_QUOTES, 'UTF-8'); ?>
<?php
}
echo "<br><br>";
?>
<input type="submit" value="<?= translate('divide.php_61行目_決定') ?>" class="button"/><br>
</form>

<form action = "stop.php" method="post">
<input type="submit" name="exe" value="<?= translate('divide.php_65行目_登録を中止する') ?>" class="button">
</form>
<br>
<br>
<a href="javascript:history.go(-2);"><?= translate('divide.php_69行目_問題登録') ?></a>
＞
<font size="4" color="red"><u><?= translate('divide.php_71行目_区切り決定') ?></u></font>
＞<?= translate('divide.php_72行目_固定ラベル決定') ?>＞<?= translate('divide.php_72行目_初期順序決定') ?>＞<?= translate('divide.php_72行目_登録') ?>
</br>

</div>
</body>
</html>
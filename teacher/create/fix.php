<?php
// session_start(); lang.phpでセッションスタート
require "../../lang.php";

if(!isset($_SESSION["MemberName"])){ //ログインしていない場合
	require"notlogin.html";
	session_destroy();
	exit;
}
$_SESSION["examflag"] = 0;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title><?= translate('fix.php_15行目_固定ラベル決定') ?></title>
	<link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />   
</head>

<body>
<div align="center">
	<FONT size="6"><?= translate('fix.php_21行目_固定ラベル決定') ?></FONT>
	</br>
<?php
// session_start(); // lang.phpで処理済み
require "../../dbc.php";
$Japanese = $_SESSION["Japanese"];
$Sentence = $_SESSION["Sentence"];
$divide2 = $_SESSION["divide2"];
//$start = $_SESSION["start"];
echo "<br>";

?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 4>
<b><?= translate('fix.php_36行目_日本文') ?></b>：<?php echo htmlspecialchars($Japanese, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('fix.php_37行目_問題文') ?></b>：<?php echo htmlspecialchars($Sentence, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('fix.php_38行目_区切り') ?></b>：<?php echo htmlspecialchars($divide2, ENT_QUOTES, 'UTF-8'); ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b><?= translate('fix.php_43行目_単語を固定する場所') ?><br><br></b>
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
<input type="checkbox" name="rock[]" value="<?php echo $i; ?>"><?php echo htmlspecialchars($a[$i], ENT_QUOTES, 'UTF-8'); ?>
	
<?php
}
?>
<br><br>
<input type="submit" value="<?= translate('fix.php_63行目_決定') ?>" class="button"/>
</form>
<br><br>
<form action = "stop.php" method="post">
<input type="submit" name="exe" value="<?= translate('fix.php_67行目_登録を中止する') ?>" class="btn_mini">
</form>

<a href="javascript:history.go(-4);"><?= translate('fix.php_70行目_問題登録') ?></a>
＞
<a href="javascript:history.go(-2);"><?= translate('fix.php_72行目_区切り決定') ?></a>
＞
<font size="4" color="red"><u><?= translate('fix.php_74行目_固定ラベル決定') ?></u></font>
＞<?= translate('fix.php_75行目_初期順序決定') ?>＞<?= translate('fix.php_75行目_登録') ?>
</br>

</div>
</body>
</html>
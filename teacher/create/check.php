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
	<title><?= translate('check.php_14行目_登録') ?></title>
	<link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>
<div align="center">
	<FONT size="6"><?= translate('check.php_20行目_登録画面') ?></FONT>
	</br>
<?php
// session_start(); // lang.phpで処理済み
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
<b><?= translate('check.php_50行目_問題番号') ?></b>：<?php echo htmlspecialchars($dtcnt, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('check.php_51行目_日本文') ?></b>：<?php echo htmlspecialchars($Japanese, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('check.php_52行目_問題文') ?></b>：<?php echo htmlspecialchars($view_Sentence, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('check.php_53行目_初期順序') ?></b>：<?php echo htmlspecialchars($start, ENT_QUOTES, 'UTF-8'); ?></br>

</font>
</td></tr></table><br>
<?php
$property = "#".$level.$pro;
$_SESSION["property"] = $property;
?>
<b><?= translate('check.php_60行目_登録を行いますか') ?><br><br></b>
</font>
<form method="post" action="insert.php">
</br>
<input type="submit" value="<?= translate('check.php_64行目_登録') ?>" class="button"/><br><br>
<input type="button" value="<?= translate('check.php_65行目_1つ前に戻る') ?>" onclick="history.back();"class="btn_mini">
</form>
<br><br>
<form action = "stop.php" method="post">
<input type="submit" name="exe" value="<?= translate('check.php_69行目_登録を中止する') ?>" class="button">
</form>	

<a href="javascript:history.go(-8);"><?= translate('check.php_72行目_問題登録') ?></a>
＞
<a href="javascript:history.go(-6);"><?= translate('check.php_74行目_区切り決定') ?></a>
＞
<a href="javascript:history.go(-4);"><?= translate('check.php_76行目_固定ラベル決定') ?></a>
＞
<a href="javascript:history.go(-2);"><?= translate('check.php_78行目_初期順序決定') ?></a>	
＞
<font size="4" color="red"><u><?= translate('check.php_80行目_登録') ?></u></font>
</br>

</div>
</body>
</html>
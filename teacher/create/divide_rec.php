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
<title><?= translate('divide_rec.php_14行目_区切り決定') ?></title>
<link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>
<div align="center">
	<FONT size="6"><?= translate('divide_rec.php_20行目_区切り決定') ?></FONT>
	</br>
<?php
// session_start(); // lang.phpで処理済み
require "../../dbc.php";
$Japanese = $_SESSION["Japanese"];
$Sentence = $_SESSION["Sentence"];
$divide =$_SESSION["divide"];
$dtcnt = $_SESSION["dtcnt"];

echo "<br>";

?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 4>
<b><?= translate('divide_rec.php_35行目_日本文') ?></b>：<?php echo htmlspecialchars($Japanese, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('divide_rec.php_36行目_問題文') ?></b>：<?php echo htmlspecialchars($Sentence, ENT_QUOTES, 'UTF-8'); ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b><?= translate('divide_rec.php_41行目_区切りを決定しました') ?><br><br></b>
</font>

<?php
$a =array();
$a = explode("|",$divide);
$len = count($a);
$divide2 = $a[0];
//echo $divide."<br><br>";
$check = [];
if(isset($_POST["check"])){
    $check = $_POST["check"];
}
//echo $check[0]."eee";
$j=0;
for ($i = 1; $i < $len; $i++){
    if(isset($check[$j]) && $check[$j] == $i){
		$divide2 = $divide2."|".$a[$i];
		$j++;
	}else{
		$divide2 = $divide2." ".$a[$i];
	}
}
$array_divide2=explode("|",$divide2);
$_SESSION["divide2"]=$divide2;
$_SESSION["wordnum"] = count($array_divide2);
echo htmlspecialchars($divide2, ENT_QUOTES, 'UTF-8');
//echo $_SESSION["wordnum"];
echo "<br><br>";
?>



<form method="post" action="fix.php">
<input type="submit" value="<?= translate('divide_rec.php_80行目_固定ラベル決定画面へ') ?>" class="button"/><br><br>
<input type="button" value="<?= translate('divide_rec.php_81行目_戻る') ?>" onclick="history.back(); "class="btn_mini">
</form>
	

<a href="javascript:history.go(-3);"><?= translate('divide_rec.php_86行目_問題登録') ?></a>
＞
<font size="4" color="red"><u><?= translate('divide_rec.php_88行目_区切り決定') ?></u></font>
＞<?= translate('divide_rec.php_89行目_固定ラベル決定') ?>＞<?= translate('divide_rec.php_89行目_初期順序決定') ?>＞<?= translate('divide_rec.php_89行目_登録') ?>
</br>

</div>
</body>
</html>
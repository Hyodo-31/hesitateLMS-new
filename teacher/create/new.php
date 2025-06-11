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
    <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
	<title><?= translate('new.php_16行目_問題新規登録') ?></title>
    <style type="text/css">

    input {
    font-size: 100%;
    }

    </style>
</head>

<body>
<div align="center">
	<FONT size="6"><?= translate('new.php_28行目_問題新規登録') ?></FONT>
	</br>
<?php
// session_start(); // lang.phpで処理済み
require "../../dbc.php";
$sql = "select MAX(WID) as maxwid from question_info";
$res = mysqli_query($conn,$sql) or die("接続エラー");
$row = mysqli_fetch_array($res);

$mode = $_GET["mode"];
$_SESSION["mode"] = $mode;

//echo $mode."<br>";

if($_SESSION["mode"] == 0){
$dtcnt = $row["maxwid"];
$dtcnt = $dtcnt + 1;
$_SESSION["dtcnt"] = $dtcnt;
}else{
$_SESSION["dtcnt"] = $_SESSION["dtcnt"];
}


if(isset($_POST["Japanese"])){
	$Japanese = $_SESSION["Japanese"];
}
if(isset($_POST["Sentence"])){
	$Sentence = $_SESSION["Sentence"];
}

//echo $_SESSION["mode"];

?>
<form action = "entry.php" method="post">
      <b>**　<?= translate('new.php_66行目_日本語文') ?>　**</b><br><?= translate('new.php_66行目_日本語で入力') ?><br><br><input type="text" name="Japanese" size="50" class="input"><br><br>
<b>**　<?= translate('new.php_67行目_英文') ?>　**</b><br><?= translate('new.php_67行目_英語で入力') ?><br><br><input type="text" name="Sentence" size="50" style="ime-mode:disabled;" class="input"><br><br>
　　　<input type="submit" name="exe" value="<?= translate('new.php_68行目_登録') ?>" class="button">
　　　<input type="reset" name="exe" value="<?= translate('new.php_69行目_リセット') ?>" class="button">
</form>
<br><br>
<form action = "stop.php" method="post">
　　　<input type="submit" name="exe" value="<?= translate('new.php_73行目_登録を中止する') ?>" class="btn_mini">
</form>
</br>
<font size="4" color="red"><u><?= translate('new.php_76行目_問題登録') ?></u></font>
	＞<?= translate('new.php_77行目_区切り決定') ?>＞<?= translate('new.php_77行目_固定ラベル決定') ?>＞<?= translate('new.php_77行目_初期順序決定') ?>＞<?= translate('new.php_77行目_登録') ?>
</br>

</div>
</body>
</html>
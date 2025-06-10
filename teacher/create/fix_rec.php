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
	<title><?= translate('fix_rec.php_14行目_固定ラベル決定') ?></title>
    <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>
<div align="center">
	<FONT size="6"><?= translate('fix_rec.php_20行目_固定ラベル決定') ?></FONT>
	</br>
<?php
// session_start(); // lang.phpで処理済み
require "../../dbc.php";
$Japanese = $_SESSION["Japanese"];
$Sentence = $_SESSION["Sentence"];
$divide2 =$_SESSION["divide2"];
//$start = $_SESSION["start"];
echo "<br>";

?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 4>
<b><?= translate('fix_rec.php_35行目_日本文') ?></b>：<?php echo htmlspecialchars($Japanese, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('fix_rec.php_36行目_問題文') ?></b>：<?php echo htmlspecialchars($Sentence, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('fix_rec.php_37行目_区切り') ?></b>：<?php echo htmlspecialchars($divide2, ENT_QUOTES, 'UTF-8'); ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b><?= translate('fix_rec.php_42行目_固定ラベルを決定しました') ?><br><br></b>


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

echo htmlspecialchars($view_Sentence, ENT_QUOTES, 'UTF-8') . "<br>";
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

<input type="submit" value="<?= translate('fix_rec.php_121行目_決定') ?>"  class="button"/><br><br>
<input type="button" value="<?= translate('fix_rec.php_122行目_戻る') ?>" onclick="history.back();" class="btn_mini">
</form>


<a href="javascript:history.go(-5);"><?= translate('fix_rec.php_127行目_問題登録') ?></a>
＞
<a href="javascript:history.go(-3);"><?= translate('fix_rec.php_129行目_区切り決定') ?></a>
＞
<font size="4" color="red"><u><?= translate('fix_rec.php_131行目_固定ラベル決定') ?></u></font>
＞<?= translate('fix_rec.php_132行目_初期順序決定') ?>＞<?= translate('fix_rec.php_132行目_登録') ?>
</br>

</div>
</body>
</html>
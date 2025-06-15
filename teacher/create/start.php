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
	<title><?= translate('start.php_14行目_初期順序決定') ?></title>
  <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
<script type="text/javascript">
	function gopage() {
  // 'group'という名前のラジオボタン要素をすべて取得します
  const radios = document.getElementsByName('group');
  let selectedPage = '';

  // ラジオボタンをループして、チェックされているものを探します
  for (let i = 0; i < radios.length; i++) {
    if (radios[i].checked) {
      selectedPage = radios[i].value; // チェックされたボタンのvalue値（ファイル名）を取得
      break; // 目的の要素が見つかったのでループを終了
    }
  }

  // 選択されたページがあれば、そのページに移動します
  if (selectedPage) {
    window.location.href = selectedPage;
  }
}
</script>
</head>

<body>
<div align="center">
	<FONT size="6"><?= translate('start.php_34行目_初期順序決定') ?></FONT>
	</br>
<?php
// session_start(); // lang.phpで処理済み
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
<b><?= translate('start.php_50行目_日本文') ?></b>：<?php echo htmlspecialchars($Japanese, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('start.php_51行目_問題文') ?></b>：<?php echo htmlspecialchars($Sentence, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('start.php_52行目_区切り') ?></b>：<?php echo htmlspecialchars($view_Sentence, ENT_QUOTES, 'UTF-8'); ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b><?= translate('start.php_57行目_初期順序の決定方法を選択') ?><br><br></b>
</font>

<form>
    <input type="radio" name="group" value="ques.php" checked="true"><?= translate('start.php_62行目_任意指定') ?>
    <input type="radio" name="group" value="alpsort.php"><?= translate('start.php_63行目_アルファベット順') ?>
 　 <input type="radio" name="group" value="randsort.php"><?= translate('start.php_64行目_ランダム') ?>
 　 <br><br>
    <input type="button" value="<?= translate('start.php_66行目_選択') ?>" onclick="gopage()" class="button">
  </form>

<br><br>
<form action = "stop.php" method="post">
<input type="submit" name="exe" value="<?= translate('start.php_71行目_登録を中止する') ?>" class="btn_mini">
</form>


<a href="javascript:history.go(-6);"><?= translate('start.php_75行目_問題登録') ?></a>
＞
<a href="javascript:history.go(-4);"><?= translate('start.php_77行目_区切り決定') ?></a>
＞
<a href="javascript:history.go(-2);"><?= translate('start.php_79行目_固定ラベル決定') ?></a>
＞
<font size="4" color="red"><u><?= translate('start.php_81行目_初期順序決定') ?></u></font>
＞<?= translate('start.php_82行目_登録') ?>
</br>

</div>
</body>
</html>
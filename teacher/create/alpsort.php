<?php
// session_start(); // lang.phpでセッションスタート
require "../../lang.php";

if(!isset($_SESSION["MemberName"])){ //ログインしていない場合
	require"notlogin.html";
	// session_destroy(); // lang.phpで処理されるため、ここでの個別破棄は不要な場合が多い
	exit;
}
$_SESSION["examflag"] = 0;
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title><?= translate('alpsort.php_12行目_初期順序決定') ?></title>
    <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>

<script>

    function attention_equal() {
        //var res = confirm("問題の初期順序が正解と同一です。任意指定に移動します");
       // 選択結果で分岐
       alert(<?= json_encode(translate('alpsort.php_24行目_初期順序が正解と同じ')) ?>);
       window.location = "./ques.php";
       /*
       if( res == true ) {
          // OKなら移動
          window.location = "http://www.nishishi.com/";
       }
       else {
          // キャンセルならダイアログ表示
          alert("移動をやめまーす。");
       }
       */
    }

    function attention_near() {
        var res = confirm(<?= json_encode(translate('alpsort.php_38行目_初期順序が正解に近い')) ?>);
       // 選択結果で分岐
       
       
       if( res == true ) {
          // OKなら移動
          window.location = "./ques.php";
       }
       else {
          // キャンセルならダイアログ表示
          alert(<?= json_encode(translate('alpsort.php_45行目_移動をキャンセルしました')) ?>);
       }
       
    }

</script>
<div align="center">
	<FONT size="6"><?= translate('alpsort.php_50行目_初期順序決定') ?></FONT>
	</br>
<?php
// session_start(); // lang.phpで処理済み
require "../../dbc.php";
$Japanese = $_SESSION["Japanese"];
$Sentence = $_SESSION["Sentence"];
$dtcnt = $_SESSION["dtcnt"];
$divide2 = $_SESSION["divide2"];
$view_Sentence = $_SESSION["view_Sentence"];
$rock =$_SESSION["rock"];
echo "<br>";

?>

<table style="border:3px dotted red;" cellpadding="5"><tr><td>
<font size = 4>
<b><?= translate('alpsort.php_66行目_日本文') ?></b>：<?php echo htmlspecialchars($Japanese, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('alpsort.php_67行目_問題文') ?></b>：<?php echo htmlspecialchars($Sentence, ENT_QUOTES, 'UTF-8'); ?></br>
<b><?= translate('alpsort.php_68行目_区切り') ?></b>：<?php echo htmlspecialchars($view_Sentence, ENT_QUOTES, 'UTF-8'); ?></br>
</font>
</td></tr></table><br>

<font size = 4>
<b><?= translate('alpsort.php_73行目_初期順序をアルファベット順にしました') ?><br><br></b>
</font>

<?php
$a = explode("|",$divide2);
$sub_a =array();//固定ラベルを除いたラベルの保存用
$i =0;
$j =0;

foreach($a as $key => $value){//固定されていないラベルのみを取り出す。
    if(isset($rock[$j])){
        if($key == $rock[$j] and $rock[$j] == "0"){
            $j++;
        }else if($key == $rock[$j] and $key != "0"){
            $j++;
        }else{
          $sub_a[$i] = $value;
          $i++;
        }
    } else {
        // $rock[$j] が存在しない場合のフォールバック
        $sub_a[$i] = $value;
        $i++;
    }
}


foreach($sub_a as $key => $value){
    if($key ==0){
        $test1 = $value;
    }else{
        $test1 = $test1."|".$value;
    }
}
//echo "固定ラベルを抜いたもの:".$test1."<br>";//固定ラベルを抜いた問題文の単語の並び順(比較対象A)

//$len = count($a);
$start ="";
$j =0;

$sub_b = array();
$sub_b =$sub_a;
natcasesort($sub_b);//key情報を維持したままアルファベット順にソート


$near_flag = 0;//類似判定用フラグ


$i =0;
foreach($sub_b as $key => $value){//完全一致かどうかの判定
    if($i == 0){
        $test2 = $value;
    }else{
        $test2 = $test2."|".$value;
    }
    $i++;
}
//echo "アルファベットソート:".$test2."<br>";//固定ラベルを抜いた問題文の単語の並び順(比較対象B)

if(isset($test1) && isset($test2) && $test1 == $test2){
    //echo "初期順序と正答文が一致<br>";
    echo '<script type = "text/javascript">';
    echo 'attention_equal()';
    echo '</script>';
}


$i = 0;
foreach($sub_b as $key => $value){//完全一致かどうかの判定
    if($i==0){
    }else if($i == 1){
        $test2 = $value;
    }else{
        $test2 = $test2."|".$value;
    }
    $i++;
}


//echo $test2."<br>";//固定ラベルを抜いた問題文の単語の並び順(比較対象B)
if (isset($test1) && isset($test2) && strstr($test1,$test2)) {
    //echo "含んでいます(1-f)<br>";
    $near_flag = 1;
}


$i = 0;
foreach($sub_b as $key => $value){//完全一致かどうかの判定
    if($i==0){
        $test2 = $value;
    }else if($i==(count($sub_a)-1)){
        
    }else{
        $test2 = $test2."|".$value;
    }
    $i++;
}
//echo $test2."<br>";//固定ラベルを抜いた問題文の単語の並び順(比較対象B)
if (isset($test1) && isset($test2) && strstr($test1,$test2)) {
    //echo "含んでいます(1-e)<br>";
    $near_flag = 1;
}

$i = 0;
foreach($sub_b as $key => $value){//完全一致かどうかの判定
    if($i>=(count($sub_a)-2)){
    }else if($i == 0){
        $test2 = $value;
    }else{
        $test2 = $test2."|".$value;
    }
    $i++;
}
//echo "[A]".$test2."<br>";//固定ラベルを抜いた問題文の単語の並び順(比較対象B)
if (isset($test1) && isset($test2) && strstr($test1,$test2)) {
    //echo "含んでいます(2-f)<br>";
    $near_flag = 1;
}






$i = 0;
foreach($sub_b as $key => $value){//完全一致かどうかの判定
    if($i==0 or $i==(count($sub_a)-1)){
    }else if($i == 1){
        $test2 = $value;
    }else{
        $test2 = $test2."|".$value;
    }
    $i++;
}
//echo "[B]".$test2."<br>";//固定ラベルを抜いた問題文の単語の並び順(比較対象B)
if (isset($test1) && isset($test2) && strstr($test1,$test2)) {
    //echo "含んでいます(2-m)<br>";
    $near_flag = 1;
}

$i = 0;
foreach($sub_b as $key => $value){//完全一致かどうかの判定
    if($i<=1){
    }else if($i == 2){
        $test2 = $value;
    }else{
        $test2 = $test2."|".$value;
    }
    $i++;
}
//echo "[C]".$test2."<br>";//固定ラベルを抜いた問題文の単語の並び順(比較対象B)
if (isset($test1) && isset($test2) && strstr($test1,$test2)) {
    //echo "含んでいます(2-f)<br>";
    $near_flag = 1;
    
}

if($near_flag ==1){
    echo '<script type = "text/javascript">';
    echo 'attention_near()';
    echo '</script>';
}


$al_num =0;
$change = 0;
$i=0;
$alp_array = [];
foreach($sub_b as $key => $value){
    //echo $key.">".$value."<br>";
    $alp_array[$al_num] = $value;
    $al_num++;
    
    /*
    if($i>0){
        echo "前".$before_key."後".$key;
        if($key < $before_key){
            $change++;
            echo "入れ替え<br>";
        }else{
            $before_key = $key;
            echo "そのまま<br>";
        }
    }else{
        $before_key = $key;
    }
    
    $i++;
    */
}

//echo "入れ替え回数".$change."<br>";




foreach ($a as $key => $val) {

    if(isset($rock[$j])){
        if($key == $rock[$j]){
            if($key ==0){
                $start = $a[$rock[$j]];
            }else{
                $start =$start."|".$a[$rock[$j]];
            }
            $j++;
        }else{
            if($key == 0){
                $start = $alp_array[0];
            }else{
                $start = $start."|".$alp_array[$key-$j];
            }
        }
    }else{
        if(isset($alp_array[$key-$j])){
            if($key == 0){
                $start = $alp_array[0];
            }else{
                $start = $start."|".$alp_array[$key-$j];
            }
        }
    }
}
echo "<br>";
if (isset($start)) {
    echo htmlspecialchars($start, ENT_QUOTES, 'UTF-8') . "<br><br><br>";
    $_SESSION["start"] = $start;
}
?>



<form method="post" action="check.php">
<br>
<input type="submit" value="<?= translate('alpsort.php_310行目_決定') ?>" class="button"/>
</form>


<a href="javascript:history.go(-7);"><?= translate('alpsort.php_314行目_問題登録') ?></a>
＞
<a href="javascript:history.go(-5);"><?= translate('alpsort.php_316行目_区切り決定') ?></a>
＞
<a href="javascript:history.go(-3);"><?= translate('alpsort.php_318行目_固定ラベル決定') ?></a>
＞
<font size="4" color="red"><u><?= translate('alpsort.php_320行目_初期順序決定') ?></u></font>
＞<?= translate('alpsort.php_321行目_登録') ?>
</br>


</div>
</body>
</html>
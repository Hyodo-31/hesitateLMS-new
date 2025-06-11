<?php
/**
 * Error reporting level
 */
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時
include '../lang.php';
// session_start(); // lang.phpで開始済み
require"../dbc.php";
$MemberID = $_SESSION["MemberID"];

//解答系文のの参照
if($_GET['param2']=="q"){
	$Question = "SELECT Sentence FROM question_info WHERE (WID= ".$_GET['param1'].")";//ＤＢから英文を得る
	$res = mysqli_query($conn,$Question) or die(translate('dbsyori.php_13行目_英文抽出エラー'));
	$count = mysqli_num_rows($res);

	//データが抽出できたとき
	if(mysqli_num_rows($res) > 0){
		$row = mysqli_fetch_array($res);
		echo $row['Sentence'];
		mysqli_free_result($res);
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}
}

//開始英文のの参照
if($_GET['param2']=="q1"){
	$Question = "SELECT start FROM question_info WHERE (WID= ".$_GET['param1'].")";//ＤＢから英文を得る
	$res = mysqli_query($conn,$Question) or die(translate('dbsyori.php_13行目_英文抽出エラー'));
	$count = mysqli_num_rows($res);

	//データが抽出できたとき
	if(mysqli_num_rows($res) > 0){
		$row = mysqli_fetch_array($res);
		echo $row['start'];
		mysqli_free_result($res);
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}
}

//divideの参照
if($_GET['param2']=="d"){
	$Divide = "SELECT divide FROM question_info WHERE (WID= ".$_GET['param1'].")";//ＤＢから英文を得る
	$res = mysqli_query($conn,$Divide) or die(translate('dbsyori.php_13行目_英文抽出エラー'));
	$count = mysqli_num_rows($res);

	//データが抽出できたとき
	if(mysqli_num_rows($res) > 0){
		$row = mysqli_fetch_array($res);
		echo $row['divide'];
		mysqli_free_result($res);
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}
}
//-------------------------------------------------------------------------------------
//日本語の参照
else if($_GET['param2']=="j"){
	$JP = "SELECT Japanese FROM question_info WHERE (WID= ".$_GET['param1'].")";//日本文取得
	$res = mysqli_query($conn,$JP) or die(translate('dbsyori.php_58行目_日本文抽出エラー'));
	$count = mysqli_num_rows($res);
	//データが抽出できたとき
	if(mysqli_num_rows($res) > 0){
		$row = mysqli_fetch_array($res);
		echo $row['Japanese'];
		mysqli_free_result($res);
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}	
}
//----------------------------------------------------------------
//Fixの参照
else if($_GET['param2']=="f"){
	$Fix = "SELECT Fix FROM question_info WHERE (WID= ".$_GET['param1'].")";
	$res = mysqli_query($conn,$Fix) or die(translate('dbsyori.php_72行目_固定抽出エラー'));
	$count = mysqli_num_rows($res);
	//データが抽出できたとき
	if(mysqli_num_rows($res) > 0){
		$row = mysqli_fetch_array($res);
		echo mb_convert_encoding($row['Fix'],"UTF-8","EUC-JP");
		mysqli_free_result($res);
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}
}
//----------------------------------------------------------------
//別解１の参照
else if($_GET['param2']=="s1"){
	$Question = "SELECT PartSentence FROM partans WHERE Point = 10 and (WID= ".$_GET['param1'].")";//ＤＢから英文を得る
	$res = mysqli_query($conn,$Question) or die(translate('dbsyori.php_13行目_英文抽出エラー'));
	$count = mysqli_num_rows($res);

	//データが抽出できたとき
	if(mysqli_num_rows($res) > 0){
		$row = mysqli_fetch_array($res);
		echo $row['PartSentence'];
		mysqli_free_result($res);
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}
}
//---------------------------------------------
//別解2の参照
else if($_GET['param2']=="s2"){
	$Question = "SELECT Sentence FROM question_info WHERE (WID= ".$_GET['param1'].")";//ＤＢから英文を得る
	$res = mysqli_query($conn,$Question) or die(translate('dbsyori.php_13行目_英文抽出エラー'));
	$count = mysqli_num_rows($res);

	//データが抽出できたとき
	if(mysqli_num_rows($res) > 0){
		$row = mysqli_fetch_array($res);
		echo $row['Sentence2'];
		mysqli_free_result($res);
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}
}
//---------------------------------------------------
//部分点検索
else if($_GET['param2']=="p"){
	$Question = "SELECT PartSentence FROM PartAns WHERE  WID = ".$_GET['param1']; //DBから英文
	$res = mysqli_query($conn,$Question) or die(translate('dbsyori.php_13行目_英文抽出エラー'));
	$count = mysqli_num_rows($res);
	
	//データが抽出できたとき
	if(mysqli_num_rows($res) > 0){
		$row = mysqli_fetch_array($res);
		echo $row['PartSentence'];
		mysqli_free_result($res);
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}
}
//-----------------------------------------------------
//問題を決めた個数
else if($_GET['param2']=="count"){
	$Question = "SELECT count(*) FROM test_questions WHERE test_id = ".$_SESSION["Qid"].""; //DBから英文
	$res = mysqli_query($Question, $conn) or die(translate('dbsyori.php_132行目_個数抽出エラー'));
	$count = mysqli_num_rows($res);
	
	//データが抽出できたとき
	if(mysqli_num_rows($res) > 0){
		$row = mysqli_fetch_array($res);
		echo $row['count(*)'];
		mysqli_free_result($res);
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}
}
//問題を決めての検索
else if($_GET['param2']=="load"){
	$Question = "SELECT WID FROM test_questions WHERE OID = ? AND test_id = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('ii', $_GET['param1'], $_SESSION["Qid"]);
	$stmt->execute();
	$res = $stmt->get_result();
	
	//データが抽出できたとき
	if($res->num_rows > 0){
		$row = $res -> fetch_assoc();
		echo $row['WID'];
	}else{
		echo translate('dbsyori.php_21行目_エラー');
	}
	$stmt->close();
}

//20250514追加
//user_progress追加
else if($_GET['param2']=="u"){
	$insert_SQL = "INSERT INTO user_progress (uid, test_id, current_oid) VALUES (?, ?, ?)";
	$stmt = $conn->prepare($insert_SQL);
	$stmt->bind_param('iii', $MemberID, $_SESSION["Qid"], $_GET['param1']);
	$stmt->execute();
	$stmt->close();
}
?>
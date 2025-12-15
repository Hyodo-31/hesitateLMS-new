<?php
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時


require "../dbc.php";

//linedataへの書き込み
session_start();
$FName = "linedata";
$MemberID = $_SESSION["MemberID"];
$AccessDate = $_SESSION["AccessDate"];
$attempt = $_SESSION["attempt"];
//ここまで

//$str = "INSERT IGNORE INTO ".$FName." (UID,WID,Date,TF,Time,Understand,EndSentence,hesitate,comments,check) VALUES(".$MemberID.",".$_GET['param1'].",\"".$_GET['param2']."\",".$_GET['param3'].",".$_GET['param4'].",".$_GET['param5'].",\"".$_GET['param6']."\",\"".$_GET['param7']."\",\"".$_GET['param8']."\",".$_GET['param9'].")";

$str = "INSERT INTO ".$FName." (UID, WID, Date, TF, Time, Understand, EndSentence, hesitate, hesitate1, hesitate2, comments, `check`, test_id,attempt)"
        ." VALUES(".$MemberID.",".$_GET['param1'].",\"".$_GET['param2']."\",".$_GET['param3'].",".$_GET['param4'].",".$_GET['param5'].",\"".$_GET['param6']."\",\"".$_GET['param7']."\",\"".$_GET['param8']."\",\"".$_GET['param9']."\",\"".$_GET['param10']."\",".$_GET['param11'].",".$_SESSION['Qid'].",".$attempt.")";

//ファイル書き込みコード

$TempFileName = sys_get_temp_dir()."/tem".$MemberID.".tmp";
file_put_contents($TempFileName,$str."\n",FILE_APPEND | LOCK_EX);
echo file_get_contents($TempFileName);

//echo $str;
//$res = mysqli_query($conn,$str) or die("linedata error");

// --- ここからが追加した処理 ---

// 特徴量計算スクリプトを読み込む
// studentディレクトリからteacherディレクトリにあるファイルを呼び出すため、パスを調整
//require_once '../teacher/feature_calculator.php';

// 解答したUIDとWIDをセッションとGETパラメータから取得
//$answered_uid = (int)$MemberID;
//$answered_wid = (int)$_GET['param1'];
//$answered_attempt = (int)$attempt; // attempt変数を取得

// 特徴量を計算してtest_featurevalueテーブルに保存
// $connは冒頭のdbc.phpで定義されたデータベース接続オブジェクト
// if (function_exists('calculateAndSaveFeatures')) {
	//calculateAndSaveFeatures($conn, $answered_uid, $answered_wid, $answered_attempt); // 第3引数を追加
//}

// --- 追加処理ここまで --- 

?>
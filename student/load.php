<?php
/**
 * Error reporting level
 */
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時
include '../lang.php';
// session_start(); // lang.phpで開始済み

require "../dbc.php";

$linedataTableName = "linedata";
$linedatamouseTableName = "linedatamouse";
$MemberID = $_SESSION["MemberID"];
$Qid = $_SESSION["Qid"];

/*
if($_GET['param1']=="a")
{
    //ユーザが解答した問題のOIDの最大値を算出 linedata
	$SQLForMaxOIDFromLineData = "SELECT MAX(tq.OID) as MAX 
								FROM test_questions tq
								JOIN linedata ld ON tq.WID = ld.WID
								WHERE tq.test_id = ? and ld.UID = ?";
	$stmt = $conn->prepare($SQLForMaxOIDFromLineData);
	$stmt-> bind_param('ii', $Qid, $MemberID);
	$stmt->execute();
	$tableMaxOIDFromLineData = $stmt->get_result();
	//データが抽出できたとき
	if($tableMaxOIDFromLineData->num_rows > 0){
		$row = $tableMaxOIDFromLineData -> fetch_assoc();
		$maxOIDInLineData = $row['MAX'];
		//mysql_free_result($tableMaxOID)
	}
	else
	{
		echo "OIDエラー";
	}
	//ユーザが解答した問題のOIDの最大値を算出 linedatamouse
	$SQLForMaxOIDFromLineDataMouse = "SELECT MAX(tq.OID) as MAX 
								FROM test_questions tq
								JOIN linedatamouse ldm ON tq.OID = ldm.OID
								WHERE tq.test_id = ? and ldm.UID = ?";
	$stmt = $conn->prepare($SQLForMaxOIDFromLineDataMouse);
	$stmt-> bind_param('ii', $Qid, $MemberID);
	$stmt->execute();
	$tableMaxOIDFromLineDataMouse = $stmt->get_result();
	//データが抽出できたとき
	if($tableMaxOIDFromLineDataMouse->num_rows > 0){
	    $row = $tableMaxOIDFromLineDataMouse -> fetch_assoc();
		$maxOIDInLineDataMouse = $row['MAX']; 
	}
	else
	{
		echo "OIDエラー";
	}

	if($maxOIDInLineData < $maxOIDInLineDataMouse)
	{
		echo $maxOIDInLineDataMouse;
	}
	else
	{
		echo $maxOIDInLineDataMouse;
    }
}
else if($_GET['param1']=="w")
{
    $sql_wid="select WID from test_questions where oid=".$_GET['param2']."";
    $res_wid = mysql_query($sql_wid, $conn) or die("WID抽出エラー");
    $cnt_wid = mysql_num_rows($res_wid);
    if($cnt_wid ==1)
    {
        $row_wid = mysql_fetch_array($res_wid);
		$WID = $row_wid['WID'];
        echo $WID;
    }
    else 
    {
        echo "WID抽出エラー";
    }
}*/
if($_GET['param1']=="a"){

	//ユーザが解答した問題のOIDの最大値を算出 
	$sql = "SELECT MAX(uq.current_oid) AS max_oid
		FROM user_progress uq
		JOIN test_questions tq ON uq.test_id = tq.test_id AND uq.current_oid = tq.OID
		WHERE uq.uid = ? AND uq.test_id = ?";
	$stmt = $conn->prepare($sql);
	$stmt->bind_param('ii', $MemberID, $Qid);
	$stmt->execute();
	$tableMaxOID = $stmt->get_result();

	// データが抽出できたとき
	if ($tableMaxOID) { // クエリ自体が成功したか
        $row = $tableMaxOID->fetch_assoc();
        $maxOID = $row['max_oid'];

        if (is_null($maxOID)) {
            echo 0; // 初回プレイ時は 0 を返す
        } else {
			echo $maxOID; // 解答済みの最大のOIDを返す
		}
	} else {
	    echo -1; // エラー時は -1 を返す
	}
	$stmt->close();

	
}else if($_GET['param1']=="w"){
    // mysql_*関数は非推奨のため、mysqli_*関数に置き換えることを推奨します。
    // ここでは文字列の国際化のみ行います。
	$sql_wid="select WID from test_questions where oid=".$_GET['param2']."";
    $res_wid = mysqli_query($conn, $sql_wid) or die(translate('load.php_116行目_WID抽出エラー'));
    $cnt_wid = mysqli_num_rows($res_wid);
    if($cnt_wid ==1){
        $row_wid = mysqli_fetch_array($res_wid);
		$WID = $row_wid['WID'];
        echo $WID;
    }
    else {
        echo translate('load.php_116行目_WID抽出エラー');
	}
}
?>

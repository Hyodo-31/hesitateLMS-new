<?php
// lang.phpでセッションが開始されるため、個別のsession_startは不要
require_once "../lang.php";

//ログイン関連
error_reporting(E_ALL);
// session_start();
if(!isset($_SESSION["MemberName"])){
    require "notlogin.html"; // 拡張子を補完
    //session_destroy();
    exit;
}
if(isset($_SESSION["examflag"]) && $_SESSION["examflag"] == 1){
	require "overlap.php";
	exit;
}else{
    $_SESSION["examflag"] = 2;
    $_SESSION["page"] = "ques";
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?= translate('result.php_24行目_解答結果') ?></title>
<link rel="stylesheet" href="../style/StyleSheet.css" type="text/css" />  
</head>
<body>
<span style="line-height:20px"> 
<div align="center">
<img src="<?= translate('logo_image_path2') ?>">
</div>
<div align="right">
    </div>
<div align="center">
<br><br>
<?= translate('result.php_43行目_おつかれさまでした') ?><br>
<?= translate('result.php_44行目_以下解答結果') ?><br>
<br>
<br>
<?php

require_once "../dbc.php";
$uid=$_SESSION["MemberID"];
$Name=$_SESSION["MemberName"];
if (isset($_GET["Qid"])) {
    $Qid = $_GET["Qid"];
} else {
    // Qidが設定されていない場合のエラーハンドリング
    die("Qidが指定されていません。");
}

echo htmlspecialchars($Name, ENT_QUOTES, 'UTF-8') . translate('result.php_54行目_さんの解答結果') . "<br><br>";

$sql = "SELECT 
            SUM(TF) as correct_count,
            count(*) as total_count,
            (SUM(TF)/count(*))*100 as accuracy_rate
        FROM linedata
        WHERE UID = ? and test_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $uid, $Qid);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();


if (isset($row['accuracy_rate'])) {
    echo "<font size=12pt color=red>" . translate('result.php_78行目_正解率') . "：" . round($row['accuracy_rate'], 2) . "%</font><br><br>";
} else {
    echo "<font size=12pt color=red>" . translate('result.php_78行目_正解率') . "：0%</font><br><br>";
}



$sql_2 = "select tq.OID,l.WID,l.TF,l.EndSentence,q.Sentence
            from test_questions tq,linedata l,question_info q
            where tq.WID=l.WID and l.WID=q.WID and tq.test_id= ? and l.uid= ?
            order by tq.OID";
$stmt_2 = $conn->prepare($sql_2);
$stmt_2->bind_param('ii', $Qid, $uid);
$stmt_2->execute();
$res_2 = $stmt_2->get_result();


echo "<table class='table_1'><tr><th>" . translate('result.php_104行目_問題番号') . "</th><th>" . translate('result.php_104行目_解答') . "</th><th>" . translate('result.php_104行目_正答') . "</th><th>" . translate('result.php_104行目_正誤') . "</th></tr>";

while($row_2 = mysqli_fetch_array($res_2)){
    echo "<tr><td>".(intval($row_2['OID']))."</td>";
    echo "<td>".htmlspecialchars($row_2['EndSentence'], ENT_QUOTES, 'UTF-8')."</td>";
    echo "<td>".htmlspecialchars($row_2['Sentence'], ENT_QUOTES, 'UTF-8')."</td><td>";
    $tf=$row_2['TF'];
    if($tf=='1') echo "〇</td>";
    else echo "×</td>";

    //echo "<td>".$row_2['point']."</td></tr>";
}
echo "</table>";


mysqli_close($conn);

?>

<br>

</span>
</div>
</body>
</html>
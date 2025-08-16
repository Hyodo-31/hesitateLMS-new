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

// ======================= ▼▼▼ 修正点 1/3 ▼▼▼ =======================
// 今回解いたテストの最新の解答のみを対象に正解率を計算するように修正
$sql = "SELECT 
            SUM(l.TF) as correct_count,
            COUNT(l.WID) as total_count
        FROM linedata l
        INNER JOIN (
            SELECT WID, MAX(Date) AS MaxDate
            FROM linedata
            WHERE uid = ? AND test_id = ?
            GROUP BY WID
        ) AS latest ON l.WID = latest.WID AND l.Date = latest.MaxDate
        WHERE l.uid = ? AND l.test_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $uid, $Qid, $uid, $Qid);
// ======================= ▲▲▲ 修正点 1/3 ▲▲▲ =======================

$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// 正解率を計算して表示
$accuracy_rate = 0;
if ($row && $row['total_count'] > 0) {
    $accuracy_rate = ($row['correct_count'] / $row['total_count']) * 100;
}
echo "<font size=12pt color=red>" . translate('result.php_78行目_正解率') . "：" . round($accuracy_rate, 2) . "%</font><br><br>";


// ======================= ▼▼▼ 修正点 2/3 ▼▼▼ =======================
// テストの言語タイプ（'ja' or 'en'）を取得
$lang_sql = "SELECT lang_type FROM tests WHERE id = ?";
$lang_stmt = $conn->prepare($lang_sql);
$lang_stmt->bind_param('i', $Qid);
$lang_stmt->execute();
$lang_result = $lang_stmt->get_result();
$test_info = $lang_result->fetch_assoc();
$lang_type = ($test_info && !empty($test_info['lang_type'])) ? $test_info['lang_type'] : 'en';
$lang_stmt->close();

// 言語タイプに応じて、参照する問題テーブルを動的に決定
$question_table = ($lang_type === 'ja') ? 'question_info_ja' : 'question_info';
// どちらのテーブルでも正解文は 'Sentence' カラムに入っている
$correct_answer_column = 'Sentence';

// 今回のテストの最新の解答結果のみを取得し、正しい言語の正解文と結合するSQLに修正
$sql_2 = "
    SELECT
        tq.OID,
        l.WID,
        l.TF,
        l.EndSentence,
        q.{$correct_answer_column} AS CorrectSentence
    FROM
        linedata l
    INNER JOIN (
        SELECT WID, MAX(Date) AS MaxDate
        FROM linedata
        WHERE uid = ? AND test_id = ?
        GROUP BY WID
    ) AS latest ON l.WID = latest.WID AND l.Date = latest.MaxDate
    JOIN test_questions tq ON l.WID = tq.WID AND l.test_id = tq.test_id
    JOIN {$question_table} q ON l.WID = q.WID
    WHERE
        l.uid = ? AND l.test_id = ?
    ORDER BY
        tq.OID
";

$stmt_2 = $conn->prepare($sql_2);
$stmt_2->bind_param('iiii', $uid, $Qid, $uid, $Qid);
// ======================= ▲▲▲ 修正点 2/3 ▲▲▲ =======================

$stmt_2->execute();
$res_2 = $stmt_2->get_result();


echo "<table class='table_1'><tr><th>" . translate('result.php_104行目_問題番号') . "</th><th>" . translate('result.php_104行目_解答') . "</th><th>" . translate('result.php_104行目_正答') . "</th><th>" . translate('result.php_104行目_正誤') . "</th></tr>";

while($row_2 = mysqli_fetch_array($res_2)){
    echo "<tr><td>".(intval($row_2['OID']))."</td>";
    echo "<td>".htmlspecialchars($row_2['EndSentence'], ENT_QUOTES, 'UTF-8')."</td>";
    // ======================= ▼▼▼ 修正点 3/3 ▼▼▼ =======================
    // 動的に取得した正しい正解文を表示する
    echo "<td>".htmlspecialchars($row_2['CorrectSentence'], ENT_QUOTES, 'UTF-8')."</td><td>";
    // ======================= ▲▲▲ 修正点 3/3 ▲▲▲ =======================
    $tf=$row_2['TF'];
    if($tf=='1') echo "〇</td>";
    else echo "×</td>";
}
echo "</table>";


mysqli_close($conn);

?>

<br>

</span>
</div>
</body>
</html>
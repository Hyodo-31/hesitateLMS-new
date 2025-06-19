<?php
include '../lang.php';
require "../dbc.php";
//GET受け取り
$lang = $_SESSION['lang'] ?? 'ja';

$uid = $_GET['uid'] ? $_GET['uid'] : null;
//ユーザーの基本情報取得
$getUIDinfoQuery = "SELECT s.Name, s.ClassID, s.toeic_level, s.eiken_level
                        FROM students s
                        WHERE s.uid = ?;
                        ";
$stmt = $conn->prepare($getUIDinfoQuery);
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
//$userinfoに結果を格納
$userinfo = [];
while ($row = $result->fetch_assoc()) {
    $userinfo = [
        'Name' => $row['Name'],
        'ClassID' => $row['ClassID'],
        'toeic_level' => $row['toeic_level'],
        'eiken_level' => $row['eiken_level'],
    ];
}
$stmt->close();
$result->free();

//ユーザーの全体の解答数を取得
$getUIDinfoQuery = "SELECT COUNT(tr.WID) AS total_answers,
                        SUM(CASE WHEN l.TF = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(l.WID) AS accuracy,
                        SUM(CASE WHEN tr.Understand = 2 THEN 1 ELSE 0 END) *100.0 / COUNT(tr.WID)AS hesitation_rate
                        FROM temporary_results tr
                        LEFT JOIN linedata l ON tr.uid = l.uid AND tr.WID = l.WID
                        WHERE tr.uid = ? AND tr.teacher_id = ?
                        GROUP BY tr.uid;";
$stmt = $conn->prepare($getUIDinfoQuery);
$stmt->bind_param("ii", $uid, $_SESSION['MemberID']);
$stmt->execute();
$result = $stmt->get_result();
//$userinfoに結果を追加．
while ($row = $result->fetch_assoc()) {
    $userinfo['total_answers'] = $row['total_answers'];
    $userinfo['accuracy'] = round($row['accuracy'], 2);
    $userinfo['hesitation_rate'] = round($row['hesitation_rate'], 2);
}

$stmt->close();

//文法項目ごとの解答数を取得
$grammarStats = [];
// grammar テーブルを事前に取得してキャッシュ
//$grammarCache = [];
//$getAllGrammarQuery = "SELECT GID, Item FROM grammar";
//$resultCache = $conn->query($getAllGrammarQuery);
//while ($row = $resultCache->fetch_assoc()) {
//   $grammarCache[$row['GID']] = $row['Item'];
//}
//$resultCache->free();
// ★★★★★★★★★★★★★★★★★★★ 修正箇所 ★★★★★★★★★★★★★★★★★★★
// grammer_translations テーブルから現在の言語の文法名を取得してキャッシュ
$grammarCache = [];

// ↓↓↓↓ このシンプルなSQLを使用します ↓↓↓↓
$sql = "SELECT GID, Item FROM grammar_translations WHERE language = ?";

// テーブル名がもし 'grammar_translations' (ar) なら、上の行を修正してください

$stmtCache = $conn->prepare($sql);
if (!$stmtCache) {
    // SQLの準備に失敗した場合、エラーメッセージを表示
    die("SQL prepare failed: " . $conn->error);
}

$stmtCache->bind_param("s", $lang);
$stmtCache->execute();
$resultCache = $stmtCache->get_result();

while ($row = $resultCache->fetch_assoc()) {
    $grammarCache[$row['GID']] = $row['Item'];
}
$stmtCache->close();
// ★★★★★★★★★★★★★★★★★★★ 修正はここまで ★★★★★★★★★★★★★★★★★★
$getGrammarStatsQuery = "SELECT tr.UID, tr.WID,qi.grammar, l.TF, tr.Understand
                            FROM temporary_results tr
                            LEFT JOIN linedata l ON tr.uid = l.uid AND tr.WID = l.WID
                            JOIN question_info qi ON tr.WID = qi.WID
                            WHERE tr.uid = ? AND tr.teacher_id = ?;";
$stmt = $conn->prepare($getGrammarStatsQuery);
$stmt->bind_param("ii", $uid, $_SESSION['MemberID']);
$stmt->execute();
$result = $stmt->get_result();
//データを一行ずつ処理
while ($row = $result->fetch_assoc()) {
    //grammarを分割して個別の文法項目を取得
    $grammars = array_filter(explode('#', trim($row['grammar'], '#')), function ($g) {
        return $g !== ''; // 空文字を除外
    });

    //文法項目ごとの解答数を格納
    foreach ($grammars as $grammar) {
        // キャッシュから文法名を取得
        $grammarName = $grammarCache[$grammar] ?? translate('不明な文法項目');
        if (!isset($grammarStats[$grammar])) {
            $grammarStats[$grammar] = [
                'GID' => $grammar,
                'grammar' => $grammarName,
                'total_answers' => 0,
                'correct_answers' => 0,
                'hesitate_count' => 0,
            ];
        }
        //解答数のカウント
        $grammarStats[$grammar]['total_answers']++;
        //正解数のカウント
        if ($row['TF'] == 1) {
            $grammarStats[$grammar]['correct_answers']++;
        }
        //迷いのカウント
        if ($row['Understand'] == 2) {
            $grammarStats[$grammar]['hesitate_count']++;
        }
    }
}
$stmt->close();

//各文法項目の正解率を計算
foreach ($grammarStats as $grammar => $stats) {
    $grammarStats[$grammar]['accuracy'] = $stats['total_answers'] > 0
        ? round($stats['correct_answers'] / $stats['total_answers'] * 100, 2) : 0;

    $grammarStats[$grammar]['hesitation_rate'] = $stats['total_answers'] > 0
        ? round($stats['hesitate_count'] / $stats['total_answers'] * 100, 2) : 0;
}
// 正解率でソート
uasort($grammarStats, function ($a, $b) {
    return $a['accuracy'] <=> $b['accuracy']; // 昇順
});
// 配列形式に変換して順序を保持
$grammarStats = array_values($grammarStats);




$response = [
    'userinfo' => $userinfo,
    'grammarStats' => $grammarStats
];

header('Content-Type: application/json');
echo json_encode($response);
?>
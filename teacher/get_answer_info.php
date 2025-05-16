<?php
require "../dbc.php";
//GET受け取り
$uid = $_GET['uid'] ? $_GET['uid'] : null;
$wid = $_GET['wid'] ? $_GET['wid'] : null;

$grammarMap = [
    '1'  => '仮定法，命令法',
    '2'  => 'It, There',
    '3'  => '無生物主語',
    '4'  => '接続詞',
    '5'  => '倒置',
    '6'  => '関係詞',
    '7'  => '間接話法',
    '8'  => '前置詞(句)',
    '9'  => '分詞',
    '10' => '動名詞',
    '11' => '不定詞',
    '12' => '受動態',
    '13' => '助動詞',
    '14' => '比較',
    '15' => '否定',
    '16' => '後置修飾',
    '17' => '完了形，時制',
    '18' => '句動詞(群動詞)',
    '19' => '挿入',
    '20' => '使役',
    '21' => '補語/二重目的語',
];

// WIDに対応するstart列を取得
$getStartQuery = "SELECT start FROM question_info WHERE WID = ?";
$getStart = $conn->prepare($getStartQuery);
$getStart->bind_param('i', $wid);
$getStart->execute();
$startResult = $getStart->get_result();
$startRow = $startResult->fetch_assoc();
$getStart->close();

$startWords = [];
if ($startRow && isset($startRow['start'])) {
    // "apple|banana|orange" → ['apple', 'banana', 'orange']
    $startWords = explode('|', $startRow['start']);
}

//解答情報の取得
// ---------------------------------------------------------
// ★修正箇所ここから
// ---------------------------------------------------------
// 変更点1: linedatamouse を LEFT JOIN し、GROUP_CONCAT で Label をまとめて取得
// 変更点2: GROUP BY を追加
$getwidinfoQuery = "SELECT 
        l.UID,
        l.WID,
        l.Date,
        l.TF,
        l.Time,
        tr.Understand,
        l.EndSentence,
        l.attempt,
        qi.Japanese,
        qi.Sentence,
        qi.level,
        qi.grammar,
        qi.wordnum,

        /* linedatamouse から label をまとめて取得 */
        GROUP_CONCAT(DISTINCT lm.Label ORDER BY lm.Label SEPARATOR '|') AS label_group

    FROM linedata l
    LEFT JOIN temporary_results tr
        ON l.UID = tr.UID
       AND l.WID = tr.WID
       AND l.attempt = tr.attempt
    LEFT JOIN question_info qi
        ON l.WID = qi.WID

    /* linedatamouse を (UID, WID, attempt) で LEFT JOIN */
    LEFT JOIN linedatamouse lm
        ON l.UID = lm.UID
       AND l.WID = lm.WID
       AND l.attempt = lm.attempt
       AND lm.Label LIKE '%#%'

    WHERE l.UID = ?
      AND l.WID = ?
      AND tr.teacher_id = ?

    /* GROUP_CONCAT を使うので、すべての取得カラムを GROUP BY */
    GROUP BY
        l.UID,
        l.WID,
        l.Date,
        l.TF,
        l.Time,
        tr.Understand,
        l.EndSentence,
        l.attempt,
        qi.Japanese,
        qi.Sentence,
        qi.level,
        qi.grammar,
        qi.wordnum
";
// ---------------------------------------------------------
// ★修正箇所ここまで
// ---------------------------------------------------------

$getwidinfo = $conn->prepare($getwidinfoQuery);
$getwidinfo->bind_param('iii',$uid,$wid,$_SESSION['MemberID']);
$getwidinfo->execute();
$result = $getwidinfo->get_result();

// $widinfo の初期化
$widinfo = [];

while($row = $result->fetch_assoc()){
    //TFとUnderstandの値を日本語に変換
    $tfText = $row['TF'] == 1 ? '正解' : '不正解';
    $understandText = $row['Understand'] == 4 ? '迷い無し' 
                    : ($row['Understand'] == 2 ? '迷い有り' : '不明');

    // grammar の #区切り → 日本語ラベル 例: "#6#7#" → "6#7" → ["6","7"] → ["関係詞","間接話法"]
    $rawGrammar = trim($row['grammar'], '#');   
    $grammarIds = explode('#', $rawGrammar);
    $translatedGrammarList = [];
    foreach ($grammarIds as $gid) {
        if ($gid === '') {
            continue;
        }
        if (isset($grammarMap[$gid])) {
            $translatedGrammarList[] = $grammarMap[$gid];
        } else {
            $translatedGrammarList[] = '未定義:' . $gid;
        }
    }
    $grammarText = implode('，', $translatedGrammarList);

    // ---------------------------------------------------------
    // ★修正箇所ここから (Label を英単語に変換する処理)
    // ---------------------------------------------------------
    $labelsEnglish = ''; // 初期値
    if (!empty($row['label_group'])) {
        // label_group は "10#12|8#9" のように '|' 区切りで複数ラベルがまとまっている
        $labelGroups = explode('|', $row['label_group']);
        $convertedAllLabels = [];
        foreach ($labelGroups as $oneLabel) {
            // 例: "10#12"
            $indices = explode('#', trim($oneLabel, '#')); 
            $convertedOneGroup = [];
            foreach ($indices as $idxStr) {
                if ($idxStr === '') {
                    continue;
                }
                $idx = (int)$idxStr; 
                if (isset($startWords[$idx])) {
                    $convertedOneGroup[] = $startWords[$idx];
                } else {
                    // インデックスが範囲外の場合の処理(??にするなど)
                    $convertedOneGroup[] = '??';
                }
            }
            // 例: ["apple","banana"] → "apple banana"
            $convertedAllLabels[] = implode(' ', $convertedOneGroup);
        }
        // 複数ラベルがある場合は " | " でつなぐ
        $labelsEnglish = implode(' | ', $convertedAllLabels);
    }
    // ---------------------------------------------------------
    // ★修正箇所ここまで
    // ---------------------------------------------------------

    // 1レコード分の情報を配列に格納
    $widinfo[] = [
        'UID'         => $row['UID'],
        'WID'         => $row['WID'],
        'Date'        => $row['Date'],
        'TF'          => $tfText,
        'Time'        => round($row['Time'] / 1000, 2),
        'Understand'  => $understandText,
        'attempt'     => $row['attempt'],
        'Japanese'    => $row['Japanese'],
        'EndSentence' => $row['EndSentence'],
        'Sentence'    => $row['Sentence'],
        'level'       => $row['level'],
        'grammar'     => $grammarText,
        'wordnum'     => $row['wordnum'],

        // ★修正: label_group を英単語にした文字列を追加
        'Label'       => $labelsEnglish
    ];
}
$getwidinfo->close();
$result->free();


//その問題の正解率と迷い率を取得
$getAnswerRateQuery = "SELECT 
                        SUM(CASE WHEN linedata.TF = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(linedata.WID) AS quesaccuracy,
                        SUM(CASE WHEN temporary_results.Understand = 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(linedata.WID) AS queshesitation_rate
                    FROM linedata
                    LEFT JOIN temporary_results 
                        ON linedata.WID = temporary_results.WID 
                        AND linedata.attempt = temporary_results.attempt
                    WHERE linedata.WID = ?";
$getAnswerRate = $conn->prepare($getAnswerRateQuery);
$getAnswerRate->bind_param('i',$wid);
$getAnswerRate->execute();
$result = $getAnswerRate->get_result();
$quesaccuracy = 0;
$queshesitation_rate = 0;
while($row = $result->fetch_assoc()){
    $quesaccuracy = round($row['quesaccuracy'],2);
    $queshesitation_rate = round($row['queshesitation_rate'],2);
}
$getAnswerRate->close();
$result->free();


//その問題のLabelを取得 (既存の集計ロジック)
$getLabelQuery = "SELECT 
                    unique_data.Label,
                    SUM(CASE WHEN unique_data.TF = 1 THEN 1 ELSE 0 END) AS TF_1_Count,
                    SUM(CASE WHEN unique_data.TF = 0 THEN 1 ELSE 0 END) AS TF_0_Count,
                    SUM(CASE WHEN unique_data.Understand = 4 THEN 1 ELSE 0 END) AS Understand_4_Count,
                    SUM(CASE WHEN unique_data.Understand = 2 THEN 1 ELSE 0 END) AS Understand_2_Count
                FROM (
                    SELECT DISTINCT 
                        lm.Label, -- Label を明示的に選択
                        lm.UID, 
                        lm.WID, 
                        l.TF, 
                        tr.Understand
                    FROM linedatamouse lm
                    LEFT JOIN linedata l 
                        ON lm.UID = l.UID 
                        AND lm.WID = l.WID 
                        AND lm.attempt = l.attempt
                    LEFT JOIN temporary_results tr
                        ON lm.UID = tr.UID 
                        AND lm.WID = tr.WID 
                        AND lm.attempt = tr.attempt
                    WHERE lm.Label LIKE '%#%'
                    AND lm.WID = ?
                    AND tr.Understand IS NOT NULL
                ) AS unique_data
                GROUP BY unique_data.Label
                ORDER BY unique_data.Label ASC";
$getLabel = $conn->prepare($getLabelQuery);
$getLabel->bind_param('i',$wid);
$getLabel->execute();
$result = $getLabel->get_result();

$labelinfo = [];
while($row = $result->fetch_assoc()){
    // Labelを分割し、対応する英単語を取得
    $labelParts = explode('#', trim($row['Label'], '#')); 
    $convertedLabel = [];
    foreach ($labelParts as $part) {
        $index = (int)$part;
        if (isset($startWords[$index])) {
            $convertedLabel[] = $startWords[$index];
        }
    }
    // 英単語をスペース区切りに変換
    $labelinfo[] = [
        'Label'               => implode(' ', $convertedLabel),
        'TF_1_Count'          => $row['TF_1_Count'],
        'TF_0_Count'          => $row['TF_0_Count'],
        'Understand_4_Count'  => $row['Understand_4_Count'],
        'Understand_2_Count'  => $row['Understand_2_Count']
    ];
}
$getLabel->close();
$result->free();


$finalData = [
    'widinfo'              => $widinfo,
    'quesaccuracy'         => $quesaccuracy,
    'queshesitation_rate'  => $queshesitation_rate,
    'labelinfo'            => $labelinfo
];

echo json_encode($finalData);
?>

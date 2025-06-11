<?php
require_once "../../lang.php"; // 多言語対応ファイルを先に読み込む
require_once "../../dbc.php";

//GET受け取り
$uid = isset($_GET['uid']) ? $_GET['uid'] : null;
$wid = isset($_GET['wid']) ? $_GET['wid'] : null;

if ($uid === null || $wid === null) {
    // エラーメッセージも多言語化
    echo json_encode(['error' => translate('UIDまたはWIDが指定されていません。')]);
    exit;
}

// 翻訳されたテキストで文法マップを動的に生成
$grammarMap = [
    '1'  => translate('仮定法，命令法'),
    '2'  => translate('It, There'),
    '3'  => translate('無生物主語'),
    '4'  => translate('接続詞'),
    '5'  => translate('倒置'),
    '6'  => translate('関係詞'),
    '7'  => translate('間接話法'),
    '8'  => translate('前置詞(句)'),
    '9'  => translate('分詞'),
    '10' => translate('動名詞'),
    '11' => translate('不定詞'),
    '12' => translate('受動態'),
    '13' => translate('助動詞'),
    '14' => translate('比較'),
    '15' => translate('否定'),
    '16' => translate('後置修飾'),
    '17' => translate('完了形，時制'),
    '18' => translate('句動詞(群動詞)'),
    '19' => translate('挿入'),
    '20' => translate('使役'),
    '21' => translate('補語/二重目的語'),
];

//解答情報の取得
$getwidinfoQuery = "SELECT l.UID,l.WID,l.Date,l.TF,l.Time,l.EndSentence,l.attempt,qi.Japanese,qi.Sentence,qi.level,qi.grammar,qi.wordnum, trfs.Understand
                    FROM linedata l
                    LEFT JOIN question_info qi ON l.WID = qi.WID
                    LEFT JOIN temporary_results_forstu trfs ON l.WID = trfs.WID AND l.UID = trfs.UID AND l.attempt = trfs.attempt
                    WHERE l.UID = ? AND l.WID = ?;";
$getwidinfo = $conn->prepare($getwidinfoQuery);
$getwidinfo->bind_param('ii',$uid,$wid);
$getwidinfo->execute();
$result = $getwidinfo->get_result();
$widinfo = [];
while($row = $result->fetch_assoc()){
    //TFとUnderstandの値を、日本語をキーとして翻訳
    $tfText = $row['TF'] == 1 ? translate('正解') : translate('不正解');
    $understandText = $row['Understand'] == 4 ? translate('迷い無し') : 
                        ($row['Understand'] == 2 ? translate('迷い有り') : translate('不明'));
                        
    $rawGrammar = trim($row['grammar'] ?? '', '#');
    $grammarIds = empty($rawGrammar) ? [] : explode('#', $rawGrammar);
    $translatedGrammarList = [];
    foreach ($grammarIds as $gid) {
        if (isset($grammarMap[$gid])) {
            $translatedGrammarList[] = $grammarMap[$gid];
        } else {
            $translatedGrammarList[] = translate('未定義') . ':' . $gid;
        }
    }
    $grammarText = implode('，', $translatedGrammarList);

    //レベルを、日本語をキーとして翻訳
    $levelText = '';
    if($row['level'] == 1){
        $levelText = translate('初級');
    }elseif($row['level'] == 2){
        $levelText = translate('中級');
    }elseif($row['level'] == 3){
        $levelText = translate('上級');
    }

    $widinfo[] = [
        'UID' => $row['UID'],
        'WID' => $row['WID'],
        'Date' => $row['Date'],
        'TF' => $tfText,
        'Time' => round($row['Time'] / 1000, 2),
        'attempt' => $row['attempt'],
        'Japanese' => $row['Japanese'],
        'EndSentence' => $row['EndSentence'],
        'Sentence' => $row['Sentence'],
        'level' => $levelText,
        'grammar' => $grammarText,
        'wordnum' => $row['wordnum'],
        'Understand' => $understandText
    ];
}
$getwidinfo->close();
$result->free();

//問題情報取得，正解率計算,平均解答時間取得
$getaccuracyQuery = "SELECT 
                        SUM(TF) as correct_count,
                        count(*) as total_count,
                        round((SUM(TF)/count(*))*100,2) as accuracy_rate,
                        round(AVG(Time),2) as avg_time
                    FROM linedata
                    WHERE WID = ?";
$getaccuracy = $conn->prepare($getaccuracyQuery);
$getaccuracy->bind_param('i',$wid);
$getaccuracy->execute();
$result = $getaccuracy->get_result();
$accuracy_rate = 0;
$ave_time = 0;
if($row = $result->fetch_assoc()){
    $accuracy_rate = $row['accuracy_rate'];
    $ave_time = round(($row['avg_time']/1000),2);
}

//jsonをまとめる
$widinfoall = [
    'ave_time' => $ave_time,
    'accuracy_rate' => $accuracy_rate,
    'widinfo' => $widinfo
];

echo json_encode($widinfoall, JSON_UNESCAPED_UNICODE);

$conn->close();
?>
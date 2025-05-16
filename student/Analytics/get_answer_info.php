<?php
    require "../../dbc.php";
    //GET受け取り
    $uid = isset($_GET['uid']) ? $_GET['uid'] : null;
    $wid = isset($_GET['wid']) ? $_GET['wid'] : null;
    
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

    //解答情報の取得
    $getwidinfoQuery = "SELECT l.UID,l.WID,l.Date,l.TF,l.Time,l.EndSentence,l.attempt,qi.Japanese,qi.Sentence,qi.level,qi.grammar,qi.wordnum, trfs.Understand
                        FROM linedata l
                        LEFT JOIN question_info qi ON l.WID = qi.WID
                        LEFT JOIN temporary_results_forstu trfs ON l.WID = trfs.WID
                        WHERE l.UID = ? AND l.WID = ?;";
    $getwidinfo = $conn->prepare($getwidinfoQuery);
    $getwidinfo->bind_param('ii',$uid,$wid);
    $getwidinfo->execute();
    $result = $getwidinfo->get_result();
    while($row = $result->fetch_assoc()){
        //TFとUnderstandの値を日本語に変換
        $tfText = $row['TF'] == 1 ? '正解' : '不正解';
        $understandText = $row['Understand'] == 4 ? '迷い無し' : 
                            ($row['Understand'] == 2 ? '迷い有り' : '不明');
                            
        $rawGrammar = $row['grammar'];          // 例: "#6#7#"
        $rawGrammar = trim($rawGrammar, '#');   // "6#7" 先頭・末尾の # を除去
        $grammarIds = explode('#', $rawGrammar); // [ "6", "7" ]
        $translatedGrammarList = [];
        foreach ($grammarIds as $gid) {
            if (isset($grammarMap[$gid])) {
                $translatedGrammarList[] = $grammarMap[$gid];
            } else {
                // 対応表になければそのままか「未定義」など
                $translatedGrammarList[] = '未定義:' . $gid;
            }
        }

        // 「関係詞，間接話法」のようにつなぐ
        $grammarText = implode('，', $translatedGrammarList);
        if($row['level'] == 1){
            $levelText = '初級';
        }elseif($row['level'] == 2){
            $levelText = '中級';
        }elseif($row['level'] == 3){
            $levelText = '上級';
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
    while($row = $result->fetch_assoc()){
        $accuracy_rate = $row['accuracy_rate'];
        $ave_time = round(($row['avg_time']/1000),2);
    }
    //Label
    
    //jsonをまとめる
    $widinfoall = [
        'ave_time' => $ave_time,
        'accuracy_rate' => $accuracy_rate,
        'widinfo' => $widinfo
    ];


    echo json_encode($widinfoall);
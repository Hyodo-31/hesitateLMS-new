<?php
    include '../lang.php';
    require "../dbc.php";
    //GET受け取り
    $uid = $_GET['uid'] ? $_GET['uid'] : null;
    //WIDを取得
    $getWIDQuery = "SELECT DISTINCT tr.WID,qi.Sentence,qi.level,qi.grammar,tr.Understand FROM temporary_results tr
                        JOIN question_info qi ON qi.WID = tr.WID
                        WHERE UID = ?";
    $stmt = $conn->prepare($getWIDQuery);
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $WID = [];
    while($row = $result->fetch_assoc()){
        if($row['Understand'] == 4){
            $row['Understand'] = translate('get_wid.php_16行目_迷い無し');
        }else if($row['Understand'] == 2){
            $row['Understand'] = translate('get_wid.php_18行目_迷い有り');
        }else{
            $row['Understand'] = translate('get_wid.php_20行目_不明');
        }

        if($row['level'] == 1){
            $row['level'] = translate('get_wid.php_23行目_初級');
        }else if($row['level'] == 2){
            $row['level'] = translate('get_wid.php_25行目_中級');
        }else{
            $row['level'] = translate('get_wid.php_27行目_上級');
        }
        $WID[] = [
            'WID' => $row['WID'],
            'Sentence' => $row['Sentence'],
            'level' => $row['level'],
            'grammar' => $row['grammar'],
            'Understand' => $row['Understand'],
        ];
    }
    $stmt->close();
    $result->free();
    echo json_encode($WID);
?>
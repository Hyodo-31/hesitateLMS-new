<?php
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
            $row['Understand'] = '迷い無し';
        }else if($row['Understand'] == 2){
            $row['Understand'] = '迷い有り';
        }else{
            $row['Understand'] = '不明';
        }

        if($row['level'] == 1){
            $row['level'] = '初級';
        }else if($row['level'] == 2){
            $row['level'] = '中級';
        }else{
            $row['level'] = '上級';
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
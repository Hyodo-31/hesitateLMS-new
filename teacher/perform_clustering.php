
<?php
require "../dbc.php";
//require "log_write.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $features = isset($_POST['features']) ? explode(',', $_POST['features']) : [];
    $studentIDs = isset($_POST['studentIDs']) ? explode(',', $_POST['studentIDs']) : [];
    $clusterCount = isset($_POST['clusterCount']) ? intval($_POST['clusterCount']) : 2;
    // ★ 追加: method
    $method = isset($_POST['method']) ? $_POST['method'] : 'kmeans';
    


    if (empty($features) || empty($studentIDs)) {
        echo json_encode(['error' => '特徴量または学生IDが不足しています。']);
        exit();
    }

    $hasNotAccuracy = in_array('notaccuracy', $features);
    $filteredFeatures = array_filter($features, fn($feature) => $feature !== 'notaccuracy');

    // 学生データを取得
    $placeholders = implode(',', array_fill(0, count($studentIDs), '?'));
    $stmt_students = $conn->prepare("SELECT uid, name FROM students WHERE uid IN ($placeholders)");
    $stmt_students->bind_param(str_repeat('i', count($studentIDs)), ...$studentIDs);
    $stmt_students->execute();
    $result_students = $stmt_students->get_result();

    $students_data = [];
    while ($row = $result_students->fetch_assoc()) {
        $students_data[$row['uid']] = ['name' => trim(preg_replace('/\s+/', ' ', $row['name']))];
    }
    $result_students->free();
    $stmt_students->close();


    $data = [];

    // featurevalueテーブルから特徴量を取得
    if (!empty($filteredFeatures)) {
        $stmt_features = $conn->prepare("SELECT uid, wid, " . implode(',', $filteredFeatures) . " FROM featurevalue WHERE uid IN ($placeholders)");
        $stmt_features->bind_param(str_repeat('i', count($studentIDs)), ...$studentIDs);
        $stmt_features->execute();
        $result_features = $stmt_features->get_result();

        while ($row = $result_features->fetch_assoc()) {
            $uid = $row['uid'];
            if (isset($students_data[$uid])) {
                $entry = [
                    'uid' => $uid,
                    'name' => $students_data[$uid]['name'],
                    'wid' => $row['wid'],
                ];
                foreach ($filteredFeatures as $feature) {
                    $entry[$feature] = $row[$feature];
                }
                $data[] = $entry;
            }
        }
        $result_features->free();
        $stmt_features->close();
    }

    // linedataテーブルからnotaccuracyを取得
    if ($hasNotAccuracy) {
        $stmt_notaccuracy = $conn->prepare("SELECT uid, wid, TF FROM linedata WHERE uid IN ($placeholders)");
        $stmt_notaccuracy->bind_param(str_repeat('i', count($studentIDs)), ...$studentIDs);
        $stmt_notaccuracy->execute();
        $result_notaccuracy = $stmt_notaccuracy->get_result();

        while ($row = $result_notaccuracy->fetch_assoc()) {
            $uid = $row['uid'];
            $entry = [
                'uid' => $uid,
                'name' => $students_data[$uid]['name'],
                'wid' => $row['wid'],
                'notaccuracy' => $row['TF'],
            ];
            $data[] = $entry;
        }
        $result_notaccuracy->free();
        $stmt_notaccuracy->close();
    }

    // CSVファイルの作成
    $inputFile = './clustering_input.csv';
    $outputFile = './clustering_output.json';
    $fp = fopen($inputFile, 'w');

    // ヘッダーの作成
    $header = ['uid', 'name', 'wid'];
    $header = array_merge($header, $filteredFeatures);
    if ($hasNotAccuracy) {
        $header[] = 'notaccuracy';
    }
    fputcsv($fp, $header);

    // データの書き込み
    foreach ($data as $row) {
        $csvRow = [];
        foreach ($header as $col) {
            $csvRow[] = $row[$col] ?? '';
        }
        fputcsv($fp, $csvRow);
    }
    fclose($fp);
    $command = escapeshellcmd("python clustering_script.py $inputFile $outputFile 2 xmeans"); //python3をpythonに変更
    //exec($command, $output, $status);
    //2025/01/25 修正
    //exec("python3 clustering_script.py $inputFile $outputFile $clusterCount", $output, $status);
    //exec("python3 clustering_script.py $inputFile $outputFile $clusterCount $method", $output, $status);
    #$command = "python3 clustering_script.py $inputFile $outputFile $clusterCount 2>&1";
    exec($command, $output, $status);

    if ($status !== 0) {
        // エラー出力を表示
        echo "Error occurred:\n";
        echo implode("\n", $output);
        exit();
    }


    $clusters = json_decode(file_get_contents($outputFile), true);
    // クラスタリング成功時にログを記録
    //logActivity($conn, $_SESSION['MemberID'], 'clustering_completed', ['features' => $features, 'cluster_count' => $clusterCount], $clusters);

    echo json_encode(['clusters' => $clusters]);

    // 最後にデータベース接続をクローズ
    $conn->close();
}
?>

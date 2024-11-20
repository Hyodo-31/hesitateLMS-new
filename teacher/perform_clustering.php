<?php
    require "../dbc.php";

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $features = isset($_POST['features']) ? explode(',', $_POST['features']) : [];
        $studentIDs = isset($_POST['studentIDs']) ? explode(',', $_POST['studentIDs']) : [];

        if (empty($features) || empty($studentIDs)) {
            echo json_encode(['error' => '特徴量または学生IDが不足しています。']);
            exit();
        }

        // 学生データを取得
        $placeholders = implode(',', array_fill(0, count($studentIDs), '?'));

        // UIDとnameをstudentsテーブルから取得
        $stmt_students = $conn->prepare("SELECT uid, name FROM students WHERE uid IN ($placeholders)");
        $stmt_students->bind_param(str_repeat('i', count($studentIDs)), ...$studentIDs);
        $stmt_students->execute();
        $result_students = $stmt_students->get_result();

        // UIDとnameを保存
        $students_data = [];
        while ($row = $result_students->fetch_assoc()) {
            $students_data[$row['uid']] = $row['name'];
        }

        // 特徴量をfeaturevalueテーブルから取得
        $stmt_features = $conn->prepare("SELECT uid, " . implode(',', $features) . " FROM featurevalue WHERE uid IN ($placeholders)");
        $stmt_features->bind_param(str_repeat('i', count($studentIDs)), ...$studentIDs);
        $stmt_features->execute();
        $result_features = $stmt_features->get_result();

        $data = [];
        while ($row = $result_features->fetch_assoc()) {
            $uid = $row['uid'];
            if (isset($students_data[$uid])) {
                $row['name'] = $students_data[$uid]; // nameを追加
                $data[] = $row;
            }
        }

        // Pythonスクリプトでクラスタリング実行
        $inputFile = './clustering_input.csv';
        $outputFile = './clustering_output.json';
        $fp = fopen($inputFile, 'w');
        fputcsv($fp, array_keys($data[0])); // ヘッダー
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        exec("py clustering_script.py $inputFile $outputFile", $output, $status);

        if ($status !== 0) {
            echo json_encode(['error' => 'クラスタリング実行中にエラーが発生しました。']);
            exit();
        }

        $clusters = json_decode(file_get_contents($outputFile), true);
        echo json_encode(['clusters' => $clusters]);
    }
?>

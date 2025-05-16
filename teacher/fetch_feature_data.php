<?php

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require "../dbc.php";

// POSTデータを受け取る
$features = explode(',', $_POST['features']); // 特徴量を配列に変換
$studentIDs = explode(',', $_POST['studentIDs']); // 学生IDを配列に変換
$response = [];

// デバッグ用：データを出力
/*
echo "<pre>";
echo "Features:\n";
print_r($features);
echo "Student IDs:\n";
print_r($studentIDs);
echo "</pre>";
*/


if (empty($features) || count($features) !== 2 && count($features) !== 1) {
    echo json_encode(['error' => 'Invalid features']);
    exit;
}

if (empty($studentIDs)) {
    echo json_encode(['error' => 'No student IDs provided']);
    exit;
}

foreach ($studentIDs as $id) {
    $stmt_name = $conn->prepare("SELECT Name FROM students WHERE uid = ?");
    $stmt_name->bind_param("i", $id);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    $name_data = $result_name->fetch_assoc();
    $student_name = isset($name_data['Name']) ? $name_data['Name'] : 'Unknown';
    $stmt_name->close();

    $feature_values = [];
    foreach ($features as $feature) {
        // 特徴量（カラム名）を直接変数に代入し、SQLインジェクションを防ぎます。
        $feature = $conn->real_escape_string($feature);
        $stmt = $conn->prepare("SELECT AVG($feature) AS average FROM test_featurevalue WHERE UID = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $feature_values[$feature] = $row['average'] !== null ? (float) $row['average'] : 0;
            }
            $stmt->close();
        } else {
            echo json_encode(['error' => "Failed to prepare statement for feature: $feature"]);
            exit;
        }
    }


    // 特徴量が1つの場合は 'featureA_avg' のみ含める
    if (count($features) === 1) {
        $response[] = [
            'student_id' => $id,
            'name' => $student_name,
            'featureA_avg' => $feature_values[$features[0]]
        ];
    } else {
        // 特徴量が2つの場合は 'featureA_avg' と 'featureB_avg' を含める
        $response[] = [
            'student_id' => $id,
            'name' => $student_name,
            'featureA_avg' => $feature_values[$features[0]],
            'featureB_avg' => $feature_values[$features[1]]
        ];
    }
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
?>

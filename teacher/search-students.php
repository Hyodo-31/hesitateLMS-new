<?php
include '../lang.php'; // 多言語対応
// session_start(); // lang.phpでセッションが開始されるため不要
require "../dbc.php";

// フォームからの入力を取得（GETメソッド）
$uids = $_POST['uid'] ?? []; // 配列として受け取る
$accuracy_min = $_POST['accuracy_min'] ?? 0;
$accuracy_max = $_POST['accuracy_max'] ?? 100;
$total_answers_min = $_POST['total_answers_min'] ?? 0;
$total_answers_max = $_POST['total_answers_max'] ?? 99999999;

// SQLクエリの基本部分
$sql = "SELECT 
            s.uid,
            s.Name,
            COALESCE(acc.accuracy, 0) AS accuracy,
            COALESCE(acc.total_answers, 0) AS total_answers
        FROM students s
        LEFT JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
        LEFT JOIN (
            SELECT 
                uid, 
                (SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS accuracy,
                COUNT(*) AS total_answers
            FROM linedata
            GROUP BY uid
        ) acc ON s.uid = acc.uid
        WHERE ct.TID = ?";

// パラメータの準備
$params = [$_SESSION['MemberID']];
$types = 'i';

// UIDによる絞り込み
if (!empty($uids)) {
    $placeholders = implode(',', array_fill(0, count($uids), '?'));
    $sql .= " AND s.uid IN ($placeholders)";
    $params = array_merge($params, $uids);
    $types .= str_repeat('i', count($uids)); // UIDは整数型
}

// 正解率での絞り込み
$sql .= " AND COALESCE(acc.accuracy, 0) BETWEEN ? AND ?";
$params[] = $accuracy_min;
$params[] = $accuracy_max;
$types .= 'dd'; // 小数点を含む可能性があるため 'd' (double) を使用

// 回答数での絞り込み
$sql .= " AND COALESCE(acc.total_answers, 0) BETWEEN ? AND ?";
$params[] = $total_answers_min;
$params[] = $total_answers_max;
$types .= 'ii';

// クエリの実行
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    // prepare() が失敗した場合のエラーハンドリング
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// HTMLを生成して返す
while ($row = $result->fetch_assoc()) {
    $name_label = translate('search-students.php_71行目_名前');
    $accuracy_label = translate('search-students.php_72行目_正解率');
    $answers_label = translate('search-students.php_73行目_回答数');

    echo "<li class='student-item'>
            <label>
                <input type='checkbox' name='students[]' value='{$row['uid']}'> 
                <p class='student-detail'><span class='label'>{$name_label}:</span> {$row['Name']}</p>
                <p class='student-detail'><span class='label'>{$accuracy_label}:</span> " . round($row['accuracy'], 2) . "%</p>
                <p class='student-detail'><span class='label'>{$answers_label}:</span> {$row['total_answers']}</p>
            </label>
          </li>";
}

$stmt->close();
$conn->close();
?>
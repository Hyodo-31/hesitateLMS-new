<?php
include '../lang.php';
require "../dbc.php";
require_once __DIR__ . "/student-feature-tooltip.php";

$uids = $_POST['uid'] ?? [];
$accuracy_min = $_POST['accuracy_min'] ?? 0;
$accuracy_max = $_POST['accuracy_max'] ?? 100;
$hesitation_rate_min = $_POST['hesitation_rate_min'] ?? 0;
$hesitation_rate_max = $_POST['hesitation_rate_max'] ?? 100;
$total_answers_min = $_POST['total_answers_min'] ?? 0;
$total_answers_max = $_POST['total_answers_max'] ?? 99999999;
$feature_select_sql = student_feature_average_select_sql($conn);
$feature_join_sql = student_feature_average_join_sql($conn);

$sql = "SELECT
            s.uid,
            s.Name,
            COALESCE(acc.accuracy, 0) AS accuracy,
            COALESCE(acc.total_answers, 0) AS total_answers,
            COALESCE(hes.hesitation_rate, 0) AS hesitation_rate,
            {$feature_select_sql}
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
        LEFT JOIN (
            SELECT
                uid,
                (SUM(CASE WHEN Understand = 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS hesitation_rate
            FROM temporary_results
            GROUP BY uid
        ) hes ON s.uid = hes.uid
        {$feature_join_sql}
        WHERE ct.TID = ?";

$params = [$_SESSION['MemberID']];
$types = 'i';

if (!empty($uids)) {
    $placeholders = implode(',', array_fill(0, count($uids), '?'));
    $sql .= " AND s.uid IN ($placeholders)";
    $params = array_merge($params, $uids);
    $types .= str_repeat('i', count($uids));
}

$sql .= " AND COALESCE(acc.accuracy, 0) BETWEEN ? AND ?";
$params[] = $accuracy_min;
$params[] = $accuracy_max;
$types .= 'dd';

$sql .= " AND COALESCE(hes.hesitation_rate, 0) BETWEEN ? AND ?";
$params[] = $hesitation_rate_min;
$params[] = $hesitation_rate_max;
$types .= 'dd';

$sql .= " AND COALESCE(acc.total_answers, 0) BETWEEN ? AND ?";
$params[] = $total_answers_min;
$params[] = $total_answers_max;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$name_label = translate('search-students.php_71行目_名前');
$accuracy_label = translate('search-students.php_72行目_正解率');
$hesitation_label = translate('create-student-group.php_135行目_迷い率:');
$answers_label = translate('search-students.php_73行目_回答数');

while ($row = $result->fetch_assoc()) {
    $uid = htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
    $student_tooltip = render_student_tooltip($row, "{$accuracy_label}:", "{$hesitation_label}", "{$answers_label}:");

    echo "<li class='student-item'>
            <label class='student-choice'>
                <input type='checkbox' name='students[]' value='{$uid}'>
                <p class='student-detail student-name'><span class='label'>{$name_label}:</span> {$name}</p>
                {$student_tooltip}
            </label>
          </li>";
}

$stmt->close();
$conn->close();
?>

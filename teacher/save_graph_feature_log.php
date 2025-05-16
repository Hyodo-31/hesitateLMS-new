<?php
require '../dbc.php';
require 'log_write.php';

session_start();
$teacherId = $_SESSION['MemberID'] ?? null;
$activityType = 'graph_features_change';
$details = $_POST['details'] ?? '';
$results = $_POST['results'] ?? '';

// エラーハンドリングを追加
if (!$teacherId) {
    echo json_encode(['status' => 'error', 'message' => 'Teacher ID is missing']);
    exit;
}

if (!$details) {
    echo json_encode(['status' => 'error', 'message' => 'Details are missing']);
    exit;
}

if (!$results) {
    echo json_encode(['status' => 'error', 'message' => 'Results are missing']);
    exit;
}

$result = logActivity($conn, $teacherId, $activityType, $details, $results);

if ($result === true) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $result]);
}

$conn->close();
?>

<?php
require "../dbc.php";

function logActivity($conn, $userId, $actionType, $details, $result = null) {
    $detailsJson = json_encode($details);

    // $resultがnullの場合、空文字に設定
    $resultJson = $result !== null ? json_encode($result) : '';

    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action_type, details, result) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $userId, $actionType, $detailsJson, $resultJson);

    if (!$stmt->execute()) {
        error_log("Failed to log activity: " . $stmt->error);
    }

    $stmt->close();
}
?>

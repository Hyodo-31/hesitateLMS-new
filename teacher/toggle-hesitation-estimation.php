<?php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require __DIR__ . '/../dbc.php';
require_once __DIR__ . '/hesitation-estimation-state.php';

function hesitation_toggle_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hesitation_toggle_response(['ok' => false, 'error' => 'POSTリクエストが必要です。'], 405);
}

$teacherId = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;
$csrfToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['teacher_hesitation_toggle_csrf'] ?? '';

if (!is_scalar($teacherId) || trim((string)$teacherId) === '') {
    hesitation_toggle_response(['ok' => false, 'error' => '教師としてログインしてください。'], 401);
}
if (!is_string($csrfToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
    hesitation_toggle_response(['ok' => false, 'error' => '操作を確認できませんでした。画面を再読み込みしてください。'], 403);
}

$teacherId = trim((string)$teacherId);
$stmt = $conn->prepare('SELECT 1 FROM teachers WHERE TID = ? LIMIT 1');
if (!$stmt) {
    hesitation_toggle_response(['ok' => false, 'error' => '教師情報を確認できませんでした。'], 500);
}
$stmt->bind_param('s', $teacherId);
$stmt->execute();
$stmt->store_result();
$isTeacher = $stmt->num_rows === 1;
$stmt->close();

if (!$isTeacher) {
    hesitation_toggle_response(['ok' => false, 'error' => '教師としてログインしてください。'], 403);
}

$suppressed = !teacher_hesitation_is_suppressed();
teacher_hesitation_set_suppressed($suppressed);

hesitation_toggle_response([
    'ok' => true,
    'suppressed' => $suppressed,
    'label' => $suppressed ? '未推定状態を解除' : '未推定状態にする',
]);


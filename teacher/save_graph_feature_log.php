<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/hesitation-estimation-state.php';
require_once __DIR__ . '/usage-log-diagnostics.php';

function graph_feature_log_respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function graph_feature_log_positive_int($value, string $label): int
{
    if (is_int($value)) {
        $number = $value;
    } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
        $number = (int)$value;
    } else {
        throw new InvalidArgumentException($label . 'が不正です。');
    }

    if ($number <= 0) {
        throw new InvalidArgumentException($label . 'が不正です。');
    }

    return $number;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    graph_feature_log_respond(405, [
        'status' => 'error',
        'message' => 'POSTで送信してください。',
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$teacherId = trim((string)($_SESSION['MemberID'] ?? $_SESSION['TID'] ?? ''));
if ($teacherId === '' || preg_match('/^\d+$/', $teacherId) !== 1) {
    graph_feature_log_respond(401, [
        'status' => 'error',
        'message' => '教師のログイン情報を確認できません。',
    ]);
}

try {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('送信内容が不正です。');
    }

    $groupId = graph_feature_log_positive_int($payload['group_id'] ?? null, 'グループID');
    $displayedStudentCount = graph_feature_log_positive_int(
        $payload['displayed_student_count'] ?? null,
        '表示人数'
    );

    $selectedFeatures = $payload['selected_features'] ?? null;
    if (!is_array($selectedFeatures) || count($selectedFeatures) !== 2) {
        throw new InvalidArgumentException('特徴量は2つ選択してください。');
    }

    $allowedFeatures = [
        'notaccuracy',
        'Time',
        'distance',
        'averageSpeed',
        'maxSpeed',
        'thinkingTime',
        'answeringTime',
        'totalStopTime',
        'maxStopTime',
        'totalDDIntervalTime',
        'maxDDIntervalTime',
        'maxDDTime',
        'minDDTime',
        'DDCount',
        'groupingDDCount',
        'groupingCountbool',
        'xUturnCount',
        'yUturnCount',
        'register_move_count1',
        'register_move_count2',
        'register_move_count3',
        'register01count1',
        'register01count2',
        'register01count3',
        'registerDDCount',
        'xUturnCountDD',
        'yUturnCountDD',
        'FromlastdropToanswerTime',
    ];

    foreach ($selectedFeatures as $feature) {
        if (!is_string($feature) || !in_array($feature, $allowedFeatures, true)) {
            throw new InvalidArgumentException('選択された特徴量が不正です。');
        }
    }
    if ($selectedFeatures[0] === $selectedFeatures[1]) {
        throw new InvalidArgumentException('異なる特徴量を2つ選択してください。');
    }
} catch (JsonException | InvalidArgumentException $e) {
    graph_feature_log_respond(400, [
        'status' => 'error',
        'message' => $e instanceof JsonException ? 'JSON形式が不正です。' : $e->getMessage(),
    ]);
}

try {
    require '../dbc.php';
    ini_set('display_errors', '0');
} catch (Throwable $e) {
    ini_set('display_errors', '0');
    usage_log_report_database_error('graph_feature_selection', 'connection', $e);
    graph_feature_log_respond(500, [
        'status' => 'error',
        'message' => 'ログ保存に失敗しました。',
    ]);
}

$diagnosticTable = 'teachers';

try {
    $teacherStatement = $conn->prepare('SELECT 1 FROM teachers WHERE TID = ? LIMIT 1');
    $teacherStatement->bind_param('s', $teacherId);
    $teacherStatement->execute();
    $teacherExists = (bool)$teacherStatement->get_result()->fetch_row();
    $teacherStatement->close();

    if (!$teacherExists) {
        graph_feature_log_respond(401, [
            'status' => 'error',
            'message' => '教師のログイン情報が不正です。',
        ]);
    }

    $diagnosticTable = 'groups';
    $groupStatement = $conn->prepare(
        'SELECT g.group_name, COUNT(gm.uid) AS member_count
         FROM `groups` g
         LEFT JOIN group_members gm ON gm.group_id = g.group_id
         WHERE g.group_id = ? AND g.TID = ?
         GROUP BY g.group_id, g.group_name
         LIMIT 1'
    );
    $groupStatement->bind_param('is', $groupId, $teacherId);
    $groupStatement->execute();
    $group = $groupStatement->get_result()->fetch_assoc();
    $groupStatement->close();

    if (!$group) {
        graph_feature_log_respond(403, [
            'status' => 'error',
            'message' => '対象グループを利用できません。',
        ]);
    }

    $memberCount = (int)$group['member_count'];
    if ($memberCount <= 0 || $displayedStudentCount !== $memberCount) {
        graph_feature_log_respond(400, [
            'status' => 'error',
            'message' => '表示人数が対象グループと一致しません。',
        ]);
    }

    $selectedFeaturesJson = json_encode(
        array_values($selectedFeatures),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $groupName = (string)$group['group_name'];
    $ml = teacher_hesitation_ml_flag($conn, $teacherId);

    $diagnosticTable = 'feacherml';
    $insertStatement = $conn->prepare(
        'INSERT INTO feacherml (
             teacher_id, group_id, group_name, selected_features, displayed_student_count, ML
         ) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insertStatement->bind_param(
        'sissii',
        $teacherId,
        $groupId,
        $groupName,
        $selectedFeaturesJson,
        $displayedStudentCount,
        $ml
    );
    $insertStatement->execute();
    $insertStatement->close();

    graph_feature_log_respond(200, ['status' => 'success']);
} catch (Throwable $e) {
    usage_log_report_database_error('graph_feature_selection', $diagnosticTable, $e, $conn);
    graph_feature_log_respond(500, [
        'status' => 'error',
        'message' => 'ログ保存に失敗しました。',
    ]);
}

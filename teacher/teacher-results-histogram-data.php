<?php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require '../dbc.php';
ob_start();
require_once __DIR__ . '/student-feature-tooltip.php';
ob_end_clean();

function result_histogram_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}

function result_histogram_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('データ取得の準備に失敗しました。');
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('データ取得に失敗しました。');
    }

    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    $stmt->close();
    return $rows;
}

function result_histogram_append_in(
    string &$sql,
    string $column,
    array $values,
    string &$types,
    array &$params
): void {
    if (empty($values)) {
        $sql .= ' AND 1 = 0';
        return;
    }
    $sql .= ' AND ' . $column . ' IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
    $types .= str_repeat('i', count($values));
    $params = array_merge($params, array_map('intval', $values));
}

function result_histogram_teacher_students(mysqli $conn, string $teacherId): array
{
    return result_histogram_rows(
        $conn,
        'SELECT DISTINCT s.uid, s.Name, s.ClassID, c.ClassName
         FROM students s
         JOIN classes c ON s.ClassID = c.ClassID
         JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
         WHERE ct.TID = ?
         ORDER BY c.ClassName, s.uid',
        's',
        [$teacherId]
    );
}

function result_histogram_test_context(mysqli $conn, string $teacherId, int $testId): array
{
    $testRows = result_histogram_rows(
        $conn,
        'SELECT id, target_type, target_group FROM tests WHERE id = ? AND teacher_id = ? LIMIT 1',
        'is',
        [$testId, $teacherId]
    );
    if (empty($testRows)) {
        result_histogram_response(['ok' => false, 'message' => '対象テストを確認できません。'], 403);
    }

    $test = $testRows[0];
    if ($test['target_type'] === 'class') {
        $students = result_histogram_rows(
            $conn,
            'SELECT DISTINCT s.uid, s.Name, s.ClassID, c.ClassName
             FROM students s
             JOIN classes c ON s.ClassID = c.ClassID
             JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
             WHERE s.ClassID = ? AND ct.TID = ?
             ORDER BY s.uid',
            'is',
            [(int)$test['target_group'], $teacherId]
        );
    } else {
        $students = result_histogram_rows(
            $conn,
            'SELECT DISTINCT s.uid, s.Name, s.ClassID, c.ClassName
             FROM `groups` g
             JOIN group_members gm ON g.group_id = gm.group_id
             JOIN students s ON gm.uid = s.uid
             JOIN classes c ON s.ClassID = c.ClassID
             JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
             WHERE g.group_id = ? AND g.TID = ? AND ct.TID = ?
             ORDER BY c.ClassName, s.uid',
            'iss',
            [(int)$test['target_group'], $teacherId, $teacherId]
        );
    }

    $wids = result_histogram_rows(
        $conn,
        'SELECT tq.WID, qi.Sentence
         FROM test_questions tq
         LEFT JOIN question_info qi ON tq.WID = qi.WID
         WHERE tq.test_id = ?
         ORDER BY tq.OID, tq.WID',
        'i',
        [$testId]
    );
    return [$students, $wids];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    result_histogram_response(['ok' => false, 'message' => 'POSTでアクセスしてください。'], 405);
}

$teacherId = trim((string)($_SESSION['TID'] ?? $_SESSION['MemberID'] ?? ''));
if ($teacherId === '' || empty($_SESSION['MemberID'])) {
    result_histogram_response(['ok' => false, 'message' => 'ログイン情報を確認できません。'], 401);
}

$scope = trim((string)($_POST['scope'] ?? ''));
if (!in_array($scope, ['class', 'test', 'student'], true)) {
    result_histogram_response(['ok' => false, 'message' => '対象範囲が不正です。'], 400);
}

$testId = filter_var($_POST['test_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($scope === 'test' && $testId === false) {
    result_histogram_response(['ok' => false, 'message' => 'テストを選択してください。'], 400);
}

try {
    if ($scope === 'test') {
        [$studentRows, $widRows] = result_histogram_test_context($conn, $teacherId, (int)$testId);
    } else {
        $studentRows = result_histogram_teacher_students($conn, $teacherId);
        $widSql = 'SELECT DISTINCT l.WID, qi.Sentence
                   FROM linedata l
                   JOIN students s ON l.UID = s.uid
                   JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                   LEFT JOIN question_info qi ON l.WID = qi.WID
                   WHERE ct.TID = ?';
        $widTypes = 's';
        $widParams = [$teacherId];
        if ($scope === 'student' && $studentId !== false && $studentId !== null) {
            $widSql .= ' AND l.UID = ?';
            $widTypes .= 'i';
            $widParams[] = (int)$studentId;
        }
        $widSql .= ' ORDER BY l.WID';
        $widRows = result_histogram_rows($conn, $widSql, $widTypes, $widParams);
    }

    $allowedUids = array_map(static fn(array $row): int => (int)$row['uid'], $studentRows);
    $allowedWids = array_map(static fn(array $row): int => (int)$row['WID'], $widRows);
    if ($scope === 'student' && $studentId !== false && $studentId !== null
        && !in_array((int)$studentId, $allowedUids, true)) {
        result_histogram_response(['ok' => false, 'message' => '対象学習者を確認できません。'], 403);
    }
    if ($scope === 'student' && $studentId !== false && $studentId !== null) {
        $studentRows = array_values(array_filter(
            $studentRows,
            static fn(array $row): bool => (int)$row['uid'] === (int)$studentId
        ));
        $allowedUids = [(int)$studentId];
    }

    $featurePairs = [];
    if (!empty($allowedUids) && !empty($allowedWids) && student_feature_table_exists($conn)) {
        $featureColumns = student_feature_columns();
        $featureSelects = ['tf.UID', 'tf.WID'];
        foreach ($featureColumns as $column => $_label) {
            $featureSelects[] = "AVG(tf.`{$column}`) AS pair_avg_{$column}";
            $featureSelects[] = "COUNT(tf.`{$column}`) AS pair_count_{$column}";
        }

        $featureSql = 'SELECT ' . implode(', ', $featureSelects) . '
                       FROM test_featurevalue tf
                       JOIN students s ON tf.UID = s.uid
                       JOIN ClassTeacher ct ON s.ClassID = ct.ClassID';
        $featureTypes = 's';
        $featureParams = [$teacherId];
        if ($scope === 'test') {
            $featureSql .= ' JOIN linedata l
                                ON tf.UID = l.UID AND tf.WID = l.WID AND tf.attempt = l.attempt';
        }
        $featureSql .= ' WHERE ct.TID = ?';
        if ($scope === 'test') {
            $featureSql .= ' AND l.test_id = ?';
            $featureTypes .= 'i';
            $featureParams[] = (int)$testId;
        }
        if ($scope === 'student' && $studentId !== false && $studentId !== null) {
            $featureSql .= ' AND tf.UID = ?';
            $featureTypes .= 'i';
            $featureParams[] = (int)$studentId;
        }
        result_histogram_append_in($featureSql, 'tf.UID', $allowedUids, $featureTypes, $featureParams);
        result_histogram_append_in($featureSql, 'tf.WID', $allowedWids, $featureTypes, $featureParams);
        $featureSql .= ' GROUP BY tf.UID, tf.WID ORDER BY tf.UID, tf.WID';

        foreach (result_histogram_rows($conn, $featureSql, $featureTypes, $featureParams) as $row) {
            $features = [];
            $featureCounts = [];
            foreach ($featureColumns as $column => $_label) {
                $value = $row["pair_avg_{$column}"] ?? null;
                $features[$column] = $value === null || $value === '' ? null : (float)$value;
                $featureCounts[$column] = (int)($row["pair_count_{$column}"] ?? 0);
            }
            $featurePairs[] = [
                'uid' => (string)$row['UID'],
                'wid' => (string)$row['WID'],
                'features' => $features,
                'featureCounts' => $featureCounts,
            ];
        }
    }

    $metricAttempts = [];
    if (!empty($allowedUids) && !empty($allowedWids)) {
        $metricSql = 'SELECT l.UID, l.WID, l.attempt, l.TF, latest_hesitation.Understand
                      FROM linedata l
                      JOIN students s ON l.UID = s.uid
                      JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                      LEFT JOIN (
                          SELECT tr.UID, tr.WID, tr.attempt, tr.Understand
                          FROM temporary_results tr
                          JOIN (
                              SELECT UID, WID, attempt, MAX(id) AS latest_id
                              FROM temporary_results
                              WHERE teacher_id = ?
                              GROUP BY UID, WID, attempt
                          ) latest ON tr.id = latest.latest_id
                      ) latest_hesitation
                        ON l.UID = latest_hesitation.UID
                        AND l.WID = latest_hesitation.WID
                        AND l.attempt = latest_hesitation.attempt
                      WHERE ct.TID = ?';
        $metricTypes = 'ss';
        $metricParams = [$teacherId, $teacherId];
        if ($scope === 'test') {
            $metricSql .= ' AND l.test_id = ?';
            $metricTypes .= 'i';
            $metricParams[] = (int)$testId;
        }
        if ($scope === 'student' && $studentId !== false && $studentId !== null) {
            $metricSql .= ' AND l.UID = ?';
            $metricTypes .= 'i';
            $metricParams[] = (int)$studentId;
        }
        result_histogram_append_in($metricSql, 'l.UID', $allowedUids, $metricTypes, $metricParams);
        result_histogram_append_in($metricSql, 'l.WID', $allowedWids, $metricTypes, $metricParams);
        $metricSql .= ' ORDER BY l.UID, l.WID, l.attempt';

        foreach (result_histogram_rows($conn, $metricSql, $metricTypes, $metricParams) as $row) {
            $metricAttempts[] = [
                'uid' => (string)$row['UID'],
                'wid' => (string)$row['WID'],
                'attempt' => (string)$row['attempt'],
                'correctness' => $row['TF'] === null ? null : (int)$row['TF'],
                'hesitation' => $row['Understand'] === null ? null : (int)$row['Understand'],
            ];
        }
    }

    $students = array_map(static fn(array $row): array => [
        'uid' => (string)$row['uid'],
        'Name' => (string)$row['Name'],
        'ClassID' => (string)$row['ClassID'],
        'ClassName' => (string)$row['ClassName'],
    ], $studentRows);
    $wids = array_map(static fn(array $row): array => [
        'WID' => (string)$row['WID'],
        'Sentence' => (string)($row['Sentence'] ?? ''),
    ], $widRows);

    $conn->close();
    result_histogram_response([
        'ok' => true,
        'scope' => $scope,
        'students' => $students,
        'wids' => $wids,
        'featurePairs' => $featurePairs,
        'metricAttempts' => $metricAttempts,
    ]);
} catch (Throwable $error) {
    error_log('teacher-results-histogram-data.php: ' . $error->getMessage());
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    result_histogram_response(['ok' => false, 'message' => 'ヒストグラムデータの取得に失敗しました。'], 500);
}

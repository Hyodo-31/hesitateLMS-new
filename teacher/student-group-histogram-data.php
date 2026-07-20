<?php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require '../dbc.php';
ob_start();
require_once __DIR__ . '/student-feature-tooltip.php';
ob_end_clean();

ini_set('display_errors', '0');
$lang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? 'ja';

function histogram_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function histogram_normalize_integer_list($rawValues): array
{
    if (!is_array($rawValues)) {
        return [];
    }

    $values = [];
    foreach ($rawValues as $rawValue) {
        if (!is_scalar($rawValue) || !preg_match('/^-?\d+$/', trim((string)$rawValue))) {
            continue;
        }
        $values[] = (int)$rawValue;
    }

    return array_values(array_unique($values));
}

function histogram_filter_allowed_ids(array $requestedIds, array $allowedIds): array
{
    $allowedLookup = array_fill_keys(array_map('strval', $allowedIds), true);
    return array_values(array_filter($requestedIds, static function (int $id) use ($allowedLookup): bool {
        return isset($allowedLookup[(string)$id]);
    }));
}

function histogram_query_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('ヒストグラム用クエリの準備に失敗しました。');
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('ヒストグラム用クエリの実行に失敗しました。');
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

function histogram_allowed_uids(mysqli $conn, string $teacherId): array
{
    $rows = histogram_query_rows(
        $conn,
        'SELECT DISTINCT s.uid
         FROM students s
         JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
         WHERE ct.TID = ?
         ORDER BY s.uid',
        's',
        [$teacherId]
    );

    return array_map(static fn(array $row): int => (int)$row['uid'], $rows);
}

function histogram_allowed_wids(mysqli $conn, string $teacherId): array
{
    if (!student_feature_table_exists($conn)) {
        return [];
    }

    $rows = histogram_query_rows(
        $conn,
        'SELECT DISTINCT tf.WID
         FROM test_featurevalue tf
         JOIN students s ON tf.UID = s.uid
         JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
         WHERE ct.TID = ?
         ORDER BY tf.WID',
        's',
        [$teacherId]
    );

    return array_map(static fn(array $row): int => (int)$row['WID'], $rows);
}

function histogram_scoped_wid_count(mysqli $conn, string $teacherId, array $uids): int
{
    if (empty($uids) || !student_feature_table_exists($conn)) {
        return 0;
    }

    $types = 's';
    $params = [$teacherId];
    $sql = 'SELECT COUNT(DISTINCT tf.WID) AS wid_count
            FROM test_featurevalue tf
            JOIN students s ON tf.UID = s.uid
            JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
            WHERE ct.TID = ?';
    histogram_append_in_condition($sql, 'tf.UID', $uids, $types, $params);
    $rows = histogram_query_rows($conn, $sql, $types, $params);

    return (int)($rows[0]['wid_count'] ?? 0);
}

function histogram_append_in_condition(
    string &$sql,
    string $column,
    array $values,
    string &$types,
    array &$params
): void {
    if (empty($values)) {
        return;
    }

    $sql .= ' AND ' . $column . ' IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
    $types .= str_repeat('i', count($values));
    $params = array_merge($params, $values);
}

function histogram_series_from_rows(array $rows, string $splitLabel): array
{
    $series = [];
    foreach ($rows as $row) {
        if ($row['value'] === null || $row['value'] === '' || !is_numeric($row['value'])) {
            continue;
        }

        $key = (string)($row['series_key'] ?? 'all');
        if (!isset($series[$key])) {
            $series[$key] = [
                'key' => $key,
                'label' => $key === 'all' ? '統合' : $splitLabel . ':' . $key,
                'values' => [],
            ];
        }
        $series[$key]['values'][] = (float)$row['value'];
    }

    return array_values($series);
}

if (empty($_SESSION['MemberID']) || !is_scalar($_SESSION['MemberID'])) {
    histogram_json_response(['ok' => false, 'message' => 'ログイン情報を確認できません。'], 401);
}

$teacherId = trim((string)$_SESSION['MemberID']);
if ($teacherId === '') {
    histogram_json_response(['ok' => false, 'message' => 'ログイン情報を確認できません。'], 401);
}
$mode = trim((string)($_POST['mode'] ?? ''));
$feature = trim((string)($_POST['feature'] ?? ''));
$uidScope = trim((string)($_POST['uid_scope'] ?? 'all'));
$comparison = trim((string)($_POST['comparison'] ?? 'combined'));
$requestedUids = histogram_normalize_integer_list($_POST['uid'] ?? []);
$requestedWids = histogram_normalize_integer_list($_POST['wid'] ?? []);

$allowedModes = [
    'feature_uid_all' => true,
    'feature_uid_selected' => true,
    'feature_uid_selected_wid_all' => true,
    'feature_uid_selected_wid_selected' => true,
    'hesitation_uid' => true,
    'accuracy_uid' => true,
    'feature_wid_selected_uid' => true,
];
$featureModes = [
    'feature_uid_all' => true,
    'feature_uid_selected' => true,
    'feature_uid_selected_wid_all' => true,
    'feature_uid_selected_wid_selected' => true,
    'feature_wid_selected_uid' => true,
];

if (!isset($allowedModes[$mode])) {
    histogram_json_response(['ok' => false, 'message' => '表示パターンが不正です。'], 400);
}
if (!in_array($comparison, ['combined', 'split'], true)) {
    histogram_json_response(['ok' => false, 'message' => '比較方法が不正です。'], 400);
}
if (!in_array($uidScope, ['all', 'selected'], true)) {
    histogram_json_response(['ok' => false, 'message' => 'UID範囲が不正です。'], 400);
}

$availableFeatures = student_feature_columns();
if (isset($featureModes[$mode]) && !isset($availableFeatures[$feature])) {
    histogram_json_response(['ok' => false, 'message' => '特徴量が不正です。'], 400);
}

try {
    $allowedUids = histogram_allowed_uids($conn, $teacherId);
    $selectedUids = histogram_filter_allowed_ids($requestedUids, $allowedUids);
    $allowedWids = isset($featureModes[$mode]) ? histogram_allowed_wids($conn, $teacherId) : [];
    $selectedWids = histogram_filter_allowed_ids($requestedWids, $allowedWids);

    $usesSelectedUids = in_array($mode, [
        'feature_uid_selected',
        'feature_uid_selected_wid_selected',
        'feature_wid_selected_uid',
    ], true) || (in_array($mode, ['hesitation_uid', 'accuracy_uid'], true) && $uidScope === 'selected');
    $usesSelectedWids = in_array($mode, [
        'feature_uid_selected_wid_all',
        'feature_uid_selected_wid_selected',
    ], true);

    $targetUidCount = $usesSelectedUids ? count($selectedUids) : count($allowedUids);
    $targetWidCount = isset($featureModes[$mode])
        ? ($usesSelectedWids ? count($selectedWids) : count($allowedWids))
        : null;
    if (isset($featureModes[$mode]) && $usesSelectedUids && !$usesSelectedWids) {
        $targetWidCount = histogram_scoped_wid_count($conn, $teacherId, $selectedUids);
    }

    if (isset($featureModes[$mode])) {
        $meta = feature_display_metadata([$feature])[$feature];
        $metric = [
            'key' => $feature,
            'label' => feature_display_label($feature, $availableFeatures[$feature]),
            'unit' => $meta['unit'],
            'displayScale' => $meta['displayScale'],
        ];
    } else {
        $metric = [
            'key' => $mode === 'hesitation_uid' ? 'hesitation_rate' : 'accuracy_rate',
            'label' => $mode === 'hesitation_uid' ? '迷い率' : '正答率',
            'unit' => '%',
            'displayScale' => 1,
        ];
    }

    $countUnit = $mode === 'feature_wid_selected_uid' ? 'wid' : 'uid';
    $basePayload = [
        'ok' => true,
        'metric' => $metric,
        'countUnit' => $countUnit,
        'selection' => [
            'uidCount' => $targetUidCount,
            'widCount' => $targetWidCount,
        ],
        'series' => [],
    ];

    if ($usesSelectedUids && empty($selectedUids)) {
        $basePayload['message'] = 'UIDが選択されていません。';
        histogram_json_response($basePayload);
    }
    if ($usesSelectedWids && empty($selectedWids)) {
        $basePayload['message'] = 'WIDが選択されていません。';
        histogram_json_response($basePayload);
    }
    if (isset($featureModes[$mode]) && !student_feature_table_exists($conn)) {
        $basePayload['message'] = '特徴量テーブルがありません。';
        histogram_json_response($basePayload);
    }

    $types = 's';
    $params = [$teacherId];
    $rows = [];
    $splitLabel = '';

    if (isset($featureModes[$mode])) {
        $quotedFeature = '`' . str_replace('`', '``', $feature) . '`';
        if ($mode === 'feature_wid_selected_uid') {
            $seriesSql = $comparison === 'split' ? 'tf.UID' : "'all'";
            $sql = "SELECT {$seriesSql} AS series_key,
                           CONCAT(tf.UID, ':', tf.WID) AS sample_key,
                           AVG(tf.{$quotedFeature}) AS value
                    FROM test_featurevalue tf
                    JOIN students s ON tf.UID = s.uid
                    JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                    WHERE ct.TID = ?";
            histogram_append_in_condition($sql, 'tf.UID', $selectedUids, $types, $params);
            $sql .= ' GROUP BY tf.UID, tf.WID ORDER BY tf.UID, tf.WID';
            $splitLabel = 'UID';
        } else {
            $canSplitByWid = in_array($mode, [
                'feature_uid_selected_wid_all',
                'feature_uid_selected_wid_selected',
            ], true);
            $splitByWid = $comparison === 'split' && $canSplitByWid;
            $seriesSql = $splitByWid ? 'tf.WID' : "'all'";
            $groupBy = $splitByWid ? 'tf.WID, tf.UID' : 'tf.UID';
            $orderBy = $splitByWid ? 'tf.WID, tf.UID' : 'tf.UID';
            $sql = "SELECT {$seriesSql} AS series_key,
                           tf.UID AS sample_key,
                           AVG(tf.{$quotedFeature}) AS value
                    FROM test_featurevalue tf
                    JOIN students s ON tf.UID = s.uid
                    JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                    WHERE ct.TID = ?";
            if ($usesSelectedUids) {
                histogram_append_in_condition($sql, 'tf.UID', $selectedUids, $types, $params);
            }
            if ($usesSelectedWids) {
                histogram_append_in_condition($sql, 'tf.WID', $selectedWids, $types, $params);
            }
            $sql .= " GROUP BY {$groupBy} ORDER BY {$orderBy}";
            $splitLabel = 'WID';
        }
        $rows = histogram_query_rows($conn, $sql, $types, $params);
    } elseif ($mode === 'accuracy_uid') {
        $sql = "SELECT 'all' AS series_key,
                       ld.UID AS sample_key,
                       (SUM(CASE WHEN ld.TF = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS value
                FROM linedata ld
                JOIN students s ON ld.UID = s.uid
                JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                WHERE ct.TID = ?";
        if ($usesSelectedUids) {
            histogram_append_in_condition($sql, 'ld.UID', $selectedUids, $types, $params);
        }
        $sql .= ' GROUP BY ld.UID ORDER BY ld.UID';
        $rows = histogram_query_rows($conn, $sql, $types, $params);
    } else {
        $sql = "SELECT 'all' AS series_key,
                       tr.UID AS sample_key,
                       (SUM(CASE WHEN tr.Understand = 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS value
                FROM temporary_results tr
                JOIN students s ON tr.UID = s.uid
                JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                WHERE ct.TID = ?";
        if ($usesSelectedUids) {
            histogram_append_in_condition($sql, 'tr.UID', $selectedUids, $types, $params);
        }
        $sql .= ' GROUP BY tr.UID ORDER BY tr.UID';
        $rows = histogram_query_rows($conn, $sql, $types, $params);
    }

    $basePayload['series'] = histogram_series_from_rows($rows, $splitLabel);
    if (empty($basePayload['series'])) {
        $basePayload['message'] = '対象となる分布データがありません。';
    }

    $conn->close();
    histogram_json_response($basePayload);
} catch (Throwable $error) {
    error_log('student-group-histogram-data.php: ' . $error->getMessage());
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    histogram_json_response(['ok' => false, 'message' => 'ヒストグラムデータの取得に失敗しました。'], 500);
}

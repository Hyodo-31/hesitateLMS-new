<?php
include '../lang.php';
require '../dbc.php';

if (empty($_SESSION['MemberID'])) {
    http_response_code(401);
    echo 'ログイン情報が見つかりません。';
    exit;
}

$fallbackFeatureColumns = [
    'Time', 'distance', 'averageSpeed', 'maxSpeed', 'thinkingTime', 'answeringTime', 'totalStopTime', 'maxStopTime',
    'stopcount', 'totalDDIntervalTime', 'maxDDIntervalTime', 'maxDDTime', 'minDDTime', 'DDCount', 'groupingDDCount',
    'groupingCountbool', 'xUTurnCount', 'yUTurnCount', 'xUTurnCountDD', 'yUTurnCountDD', 'register_move_count1',
    'register_move_count2', 'register_move_count3', 'register_move_count4', 'register01count1', 'register01count2',
    'register01count3', 'register01count4', 'registerDDCount', 'register_notDDCount', 'register_fix_count1',
    'register_fix_count2', 'register_fix_count3', 'register_fix_count4', 'register_delete_count1',
    'register_delete_count2', 'register_delete_count3', 'register_delete_count4', 'register_allDelete_count1',
    'register_allDelete_count2', 'register_allDelete_count3', 'register_allDelete_count4', 'register_notallDelete_count1',
    'register_notallDelete_count2', 'register_notallDelete_count3', 'register_notallDelete_count4',
    'FromlastdropToanswerTime'
];

function quoteIdentifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function getFeatureColumns(mysqli $conn, array $fallbackFeatureColumns): array
{
    $excludedColumns = [
        'UID' => true,
        'WID' => true,
        'Understand' => true,
        'attempt' => true,
        'date' => true,
        'check' => true,
    ];
    $featureColumns = [];
    $result = $conn->query('SHOW COLUMNS FROM test_featurevalue');

    if ($result) {
        $collectFromTime = false;
        while ($row = $result->fetch_assoc()) {
            $field = $row['Field'] ?? '';
            $type = strtolower($row['Type'] ?? '');

            if ($field === 'Time') {
                $collectFromTime = true;
            }

            if (!$collectFromTime || $field === '' || isset($excludedColumns[$field])) {
                continue;
            }

            if (preg_match('/\b(int|float|double|decimal|real)\b/', $type)) {
                $featureColumns[] = $field;
            }
        }
        $result->close();
    }

    if (!empty($featureColumns)) {
        return $featureColumns;
    }

    return $fallbackFeatureColumns;
}

function pearsonCorrelationFromValues(array $xValues, array $yValues): ?float
{
    $n = count($xValues);
    if ($n < 2) {
        return null;
    }

    $sumX = array_sum($xValues);
    $sumY = array_sum($yValues);
    $sumXY = 0.0;
    $sumX2 = 0.0;
    $sumY2 = 0.0;

    for ($i = 0; $i < $n; $i++) {
        $sumXY += $xValues[$i] * $yValues[$i];
        $sumX2 += $xValues[$i] * $xValues[$i];
        $sumY2 += $yValues[$i] * $yValues[$i];
    }

    return pearsonCorrelationFromSums($n, $sumX, $sumY, $sumXY, $sumX2, $sumY2);
}

function pearsonCorrelationFromSums(int $n, float $sumX, float $sumY, float $sumXY, float $sumX2, float $sumY2): ?float
{
    if ($n < 2) {
        return null;
    }

    $numerator = ($n * $sumXY) - ($sumX * $sumY);
    $denominator = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));

    if ($denominator <= 0) {
        return null;
    }

    return $numerator / $denominator;
}

function jsonResponse(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getSelectedStudentIdsFromPost(array $allowedStudentIds): array
{
    $selectedStudents = json_decode($_POST['student_ids'] ?? '[]', true);
    if (!is_array($selectedStudents)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $selectedStudents), function ($uid) use ($allowedStudentIds) {
        return in_array($uid, $allowedStudentIds, true);
    }));
}

function getSelectedWidsFromPost(): array
{
    $selectedWids = json_decode($_POST['wids'] ?? '[]', true);
    if (!is_array($selectedWids)) {
        return [];
    }

    $selectedWids = array_filter($selectedWids, function ($wid) {
        return is_numeric($wid);
    });

    return array_values(array_unique(array_map('intval', $selectedWids)));
}

function appendWidFilter(string $sql, array $selectedWids, string &$types, array &$params): string
{
    if (empty($selectedWids)) {
        return $sql;
    }

    $widPlaceholders = implode(',', array_fill(0, count($selectedWids), '?'));
    $types .= str_repeat('i', count($selectedWids));
    $params = array_merge($params, $selectedWids);

    return $sql . " AND WID IN ({$widPlaceholders})";
}

$featureColumns = getFeatureColumns($conn, $fallbackFeatureColumns);
$teacherId = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;
$teacherClasses = [];
$studentsByClass = [];
$teacherGroups = [];
$studentsByGroup = [];
$studentsByClassId = [];
$allowedStudentIds = [];
$studentIndex = [];

if ($teacherId) {
    $stmtClasses = $conn->prepare(
        "SELECT c.ClassID, c.ClassName
         FROM classteacher ct
         JOIN classes c ON ct.ClassID = c.ClassID
         WHERE ct.TID = ?
         ORDER BY c.ClassName"
    );
    if ($stmtClasses) {
        $stmtClasses->bind_param('s', $teacherId);
        $stmtClasses->execute();
        $resultClasses = $stmtClasses->get_result();
        while ($row = $resultClasses->fetch_assoc()) {
            $teacherClasses[] = $row;
        }
        $stmtClasses->close();
    }

    if (!empty($teacherClasses)) {
        $classIds = array_column($teacherClasses, 'ClassID');
        $placeholders = implode(',', array_fill(0, count($classIds), '?'));
        $types = str_repeat('i', count($classIds));
        $stmtStudents = $conn->prepare(
            "SELECT s.uid, s.Name, s.ClassID, c.ClassName
             FROM students s
             JOIN classes c ON s.ClassID = c.ClassID
             WHERE s.ClassID IN ({$placeholders})
             ORDER BY c.ClassName, s.uid"
        );
        if ($stmtStudents) {
            $stmtStudents->bind_param($types, ...$classIds);
            $stmtStudents->execute();
            $resultStudents = $stmtStudents->get_result();
            while ($row = $resultStudents->fetch_assoc()) {
                $uid = (string)$row['uid'];
                if (!isset($studentIndex[$uid])) {
                    $studentsByClass[$row['ClassName']][] = $row;
                    $studentIndex[$uid] = true;
                }
                $classKey = (string)$row['ClassID'];
                if (!isset($studentsByClassId[$classKey])) {
                    $studentsByClassId[$classKey] = [];
                }
                if (!in_array($uid, $studentsByClassId[$classKey], true)) {
                    $studentsByClassId[$classKey][] = $uid;
                }
                $allowedStudentIds[] = $uid;
            }
            $stmtStudents->close();
        }
    }

    $stmtGroups = $conn->prepare(
        "SELECT group_id, group_name
         FROM `groups`
         WHERE TID = ?
         ORDER BY created_at DESC, group_id DESC"
    );
    if ($stmtGroups) {
        $stmtGroups->bind_param('s', $teacherId);
        $stmtGroups->execute();
        $resultGroups = $stmtGroups->get_result();
        while ($row = $resultGroups->fetch_assoc()) {
            $teacherGroups[] = $row;
            $studentsByGroup[(string)$row['group_id']] = [];
        }
        $stmtGroups->close();
    }

    if (!empty($teacherGroups)) {
        $groupIds = array_map('intval', array_column($teacherGroups, 'group_id'));
        $groupPlaceholders = implode(',', array_fill(0, count($groupIds), '?'));
        $groupTypes = str_repeat('i', count($groupIds));
        $stmtGroupStudents = $conn->prepare(
            "SELECT gm.group_id, gm.uid, COALESCE(s.Name, '') AS Name, s.ClassID, COALESCE(c.ClassName, 'グループ(クラス)未設定') AS ClassName
             FROM group_members gm
             LEFT JOIN students s ON gm.uid = s.uid
             LEFT JOIN classes c ON s.ClassID = c.ClassID
             WHERE gm.group_id IN ({$groupPlaceholders})
             ORDER BY gm.group_id, c.ClassName, gm.uid"
        );
        if ($stmtGroupStudents) {
            $stmtGroupStudents->bind_param($groupTypes, ...$groupIds);
            $stmtGroupStudents->execute();
            $resultGroupStudents = $stmtGroupStudents->get_result();
            while ($row = $resultGroupStudents->fetch_assoc()) {
                $groupId = (string)$row['group_id'];
                $uid = (string)$row['uid'];
                if (!in_array($uid, $studentsByGroup[$groupId], true)) {
                    $studentsByGroup[$groupId][] = $uid;
                }
                if (!isset($studentIndex[$uid])) {
                    $studentName = $row['Name'] !== '' ? $row['Name'] : $uid;
                    $className = $row['ClassName'] !== '' ? $row['ClassName'] : 'グループ(クラス)未設定';
                    $studentsByClass[$className][] = [
                        'uid' => $uid,
                        'Name' => $studentName,
                        'ClassID' => $row['ClassID'] ?? '',
                        'ClassName' => $className,
                    ];
                    $studentIndex[$uid] = true;
                }
                $allowedStudentIds[] = $uid;
            }
            $stmtGroupStudents->close();
        }
    }
}
$allowedStudentIds = array_values(array_unique($allowedStudentIds));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? '';
    $featureMap = array_fill_keys($featureColumns, true);

    if ($action === 'get_wids_for_students') {
        $selectedStudents = getSelectedStudentIdsFromPost($allowedStudentIds);
        if (empty($selectedStudents)) {
            jsonResponse(['items' => []]);
        }

        $studentPlaceholders = implode(',', array_fill(0, count($selectedStudents), '?'));
        $studentType = str_repeat('s', count($selectedStudents));
        $stmt = $conn->prepare(
            "SELECT DISTINCT tf.WID, qi.Sentence
             FROM test_featurevalue tf
             LEFT JOIN question_info qi ON tf.WID = qi.WID
             WHERE tf.UID IN ({$studentPlaceholders})
             ORDER BY tf.WID"
        );
        if (!$stmt) {
            jsonResponse(['error' => '問題リストの取得に失敗しました。']);
        }
        $stmt->bind_param($studentType, ...$selectedStudents);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $result->close();
        $stmt->close();
        jsonResponse(['items' => $items]);
    }

    if ($action === 'get_correlation_data') {
        $mode = $_POST['mode'] ?? 'understand';
        $xFeature = $_POST['feature_x'] ?? ($_POST['feature'] ?? '');
        $yFeature = $_POST['feature_y'] ?? '';

        if (!isset($featureMap[$xFeature])) {
            jsonResponse(['error' => '無効な特徴量です。']);
        }

        $selectedStudents = getSelectedStudentIdsFromPost($allowedStudentIds);
        $selectedWids = getSelectedWidsFromPost();

        if (empty($selectedStudents) || (($_POST['wid_filter_enabled'] ?? '') === '1' && empty($selectedWids))) {
            jsonResponse([
                'mode' => $mode,
                'feature_x' => $xFeature,
                'feature_y' => $mode === 'feature_pair' ? $yFeature : null,
                'x_label' => $xFeature,
                'y_label' => $mode === 'feature_pair' ? $yFeature : 'Understand(迷い度)',
                'count' => 0,
                'correlation' => null,
                'points' => [],
            ]);
        }

        $studentPlaceholders = implode(',', array_fill(0, count($selectedStudents), '?'));
        $studentType = str_repeat('s', count($selectedStudents));
        $queryTypes = $studentType;
        $queryParams = $selectedStudents;

        if ($mode === 'feature_pair') {
            if (!isset($featureMap[$yFeature])) {
                jsonResponse(['error' => '比較する特徴量を選択してください。']);
            }

            $xSql = quoteIdentifier($xFeature);
            $ySql = quoteIdentifier($yFeature);
            $sql = "SELECT UID, WID, attempt, Understand, {$xSql} AS x_value, {$ySql} AS y_value
                    FROM test_featurevalue
                    WHERE {$xSql} IS NOT NULL AND {$ySql} IS NOT NULL AND UID IN ({$studentPlaceholders})";
            $xLabel = $xFeature;
            $yLabel = $yFeature;
        } else {
            $xSql = quoteIdentifier($xFeature);
            $sql = "SELECT UID, WID, attempt, Understand, {$xSql} AS x_value, Understand AS y_value
                    FROM test_featurevalue
                    WHERE Understand IS NOT NULL AND {$xSql} IS NOT NULL AND UID IN ({$studentPlaceholders})";
            $xLabel = $xFeature;
            $yLabel = 'Understand(迷い度)';
            $mode = 'understand';
        }

        $sql = appendWidFilter($sql, $selectedWids, $queryTypes, $queryParams);
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            jsonResponse(['error' => 'データ取得に失敗しました。']);
        }
        $stmt->bind_param($queryTypes, ...$queryParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $points = [];
        $xValues = [];
        $yValues = [];

        while ($row = $result->fetch_assoc()) {
            if (!is_numeric($row['x_value']) || !is_numeric($row['y_value'])) {
                continue;
            }

            $x = (float)$row['x_value'];
            $y = (float)$row['y_value'];
            $xValues[] = $x;
            $yValues[] = $y;
            $points[] = [
                'x' => $x,
                'y' => $y,
                'uid' => $row['UID'],
                'wid' => $row['WID'],
                'attempt' => $row['attempt'],
                'understand' => is_numeric($row['Understand']) ? (float)$row['Understand'] : null,
            ];
        }
        $result->close();
        $stmt->close();

        jsonResponse([
            'mode' => $mode,
            'feature_x' => $xFeature,
            'feature_y' => $mode === 'feature_pair' ? $yFeature : null,
            'x_label' => $xLabel,
            'y_label' => $yLabel,
            'count' => count($points),
            'correlation' => pearsonCorrelationFromValues($xValues, $yValues),
            'points' => $points,
        ]);
    }


    if ($action === 'get_understand_correlation_list') {
        $selectedStudents = getSelectedStudentIdsFromPost($allowedStudentIds);
        $selectedWids = getSelectedWidsFromPost();
        if (empty($selectedStudents) || (($_POST['wid_filter_enabled'] ?? '') === '1' && empty($selectedWids))) {
            jsonResponse(['items' => []]);
        }

        $selectParts = ['Understand AS understand_value'];
        foreach ($featureColumns as $feature) {
            $selectParts[] = quoteIdentifier($feature);
        }

        $studentPlaceholders = implode(',', array_fill(0, count($selectedStudents), '?'));
        $studentType = str_repeat('s', count($selectedStudents));
        $queryTypes = $studentType;
        $queryParams = $selectedStudents;
        $sql = 'SELECT ' . implode(', ', $selectParts) . " FROM test_featurevalue WHERE Understand IS NOT NULL AND UID IN ({$studentPlaceholders})";
        $sql = appendWidFilter($sql, $selectedWids, $queryTypes, $queryParams);
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            jsonResponse(['error' => '迷い度との相関一覧の取得に失敗しました。']);
        }
        $stmt->bind_param($queryTypes, ...$queryParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $stats = [];
        foreach ($featureColumns as $feature) {
            $stats[$feature] = [
                'n' => 0,
                'sumX' => 0.0,
                'sumY' => 0.0,
                'sumXY' => 0.0,
                'sumX2' => 0.0,
                'sumY2' => 0.0,
            ];
        }

        while ($row = $result->fetch_assoc()) {
            if (!is_numeric($row['understand_value'])) {
                continue;
            }

            $x = (float)$row['understand_value'];
            foreach ($featureColumns as $feature) {
                if (!isset($row[$feature]) || !is_numeric($row[$feature])) {
                    continue;
                }

                $y = (float)$row[$feature];
                $stats[$feature]['n']++;
                $stats[$feature]['sumX'] += $x;
                $stats[$feature]['sumY'] += $y;
                $stats[$feature]['sumXY'] += $x * $y;
                $stats[$feature]['sumX2'] += $x * $x;
                $stats[$feature]['sumY2'] += $y * $y;
            }
        }
        $result->close();
        $stmt->close();

        $items = [];
        foreach ($stats as $feature => $values) {
            $correlation = pearsonCorrelationFromSums(
                $values['n'],
                $values['sumX'],
                $values['sumY'],
                $values['sumXY'],
                $values['sumX2'],
                $values['sumY2']
            );
            $items[] = [
                'feature' => $feature,
                'correlation' => $correlation,
                'count' => $values['n'],
            ];
        }

        usort($items, function ($a, $b) {
            $aValue = $a['correlation'] === null ? -1 : abs($a['correlation']);
            $bValue = $b['correlation'] === null ? -1 : abs($b['correlation']);
            return $bValue <=> $aValue;
        });

        jsonResponse(['items' => $items]);
    }

    if ($action === 'get_feature_correlation_list') {
        $xFeature = $_POST['feature_x'] ?? '';
        if (!isset($featureMap[$xFeature])) {
            jsonResponse(['error' => '無効な特徴量です。']);
        }

        $selectedStudents = getSelectedStudentIdsFromPost($allowedStudentIds);
        $selectedWids = getSelectedWidsFromPost();
        if (empty($selectedStudents) || (($_POST['wid_filter_enabled'] ?? '') === '1' && empty($selectedWids))) {
            jsonResponse(['feature_x' => $xFeature, 'items' => []]);
        }

        $comparisonFeatures = array_values(array_filter($featureColumns, function ($feature) use ($xFeature) {
            return $feature !== $xFeature;
        }));

        if (empty($comparisonFeatures)) {
            jsonResponse(['feature_x' => $xFeature, 'items' => []]);
        }

        $selectParts = [quoteIdentifier($xFeature) . ' AS base_value'];
        foreach ($comparisonFeatures as $feature) {
            $selectParts[] = quoteIdentifier($feature);
        }

        $baseSql = quoteIdentifier($xFeature);
        $studentPlaceholders = implode(',', array_fill(0, count($selectedStudents), '?'));
        $studentType = str_repeat('s', count($selectedStudents));
        $queryTypes = $studentType;
        $queryParams = $selectedStudents;
        $sql = 'SELECT ' . implode(', ', $selectParts) . " FROM test_featurevalue WHERE {$baseSql} IS NOT NULL AND UID IN ({$studentPlaceholders})";
        $sql = appendWidFilter($sql, $selectedWids, $queryTypes, $queryParams);
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            jsonResponse(['error' => '相関一覧の取得に失敗しました。']);
        }
        $stmt->bind_param($queryTypes, ...$queryParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $stats = [];
        foreach ($comparisonFeatures as $feature) {
            $stats[$feature] = [
                'n' => 0,
                'sumX' => 0.0,
                'sumY' => 0.0,
                'sumXY' => 0.0,
                'sumX2' => 0.0,
                'sumY2' => 0.0,
            ];
        }

        while ($row = $result->fetch_assoc()) {
            if (!is_numeric($row['base_value'])) {
                continue;
            }

            $x = (float)$row['base_value'];
            foreach ($comparisonFeatures as $feature) {
                if (!isset($row[$feature]) || !is_numeric($row[$feature])) {
                    continue;
                }

                $y = (float)$row[$feature];
                $stats[$feature]['n']++;
                $stats[$feature]['sumX'] += $x;
                $stats[$feature]['sumY'] += $y;
                $stats[$feature]['sumXY'] += $x * $y;
                $stats[$feature]['sumX2'] += $x * $x;
                $stats[$feature]['sumY2'] += $y * $y;
            }
        }
        $result->close();
        $stmt->close();

        $items = [];
        foreach ($stats as $feature => $values) {
            $correlation = pearsonCorrelationFromSums(
                $values['n'],
                $values['sumX'],
                $values['sumY'],
                $values['sumXY'],
                $values['sumX2'],
                $values['sumY2']
            );
            $items[] = [
                'feature_x' => $xFeature,
                'feature_y' => $feature,
                'correlation' => $correlation,
                'count' => $values['n'],
            ];
        }

        usort($items, function ($a, $b) {
            $aValue = $a['correlation'] === null ? -1 : abs($a['correlation']);
            $bValue = $b['correlation'] === null ? -1 : abs($b['correlation']);
            return $bValue <=> $aValue;
        });

        jsonResponse(['feature_x' => $xFeature, 'items' => $items]);
    }

    jsonResponse(['error' => '無効な操作です。']);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>特徴量相関分析</title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .feature-correlation-page {
            max-width: 1280px;
            margin: 0 auto;
            padding: calc(var(--header-height) + 24px) 24px 48px;
        }

        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .page-heading h1 {
            margin: 0;
            color: #243447;
            font-size: 1.75rem;
        }

        .home-link {
            color: #0969da;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .home-link:hover {
            text-decoration: underline;
        }

        .analysis-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 14px;
            padding: 16px;
            margin-bottom: 18px;
            background: #ffffff;
            border: 1px solid #d8dee4;
            border-radius: 8px;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .control-group label,
        .mode-label {
            color: #3b4754;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .control-group select {
            min-width: 220px;
        }

        .mode-toggle {
            display: inline-flex;
            overflow: hidden;
            border: 1px solid #b7c3d0;
            border-radius: 8px;
            background: #f6f8fa;
        }

        .mode-toggle label {
            position: relative;
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 0 14px;
            color: #334155;
            cursor: pointer;
            border-right: 1px solid #d8dee4;
            font-weight: 700;
        }

        .mode-toggle label:last-child {
            border-right: 0;
        }

        .mode-toggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .mode-toggle label:has(input:checked) {
            color: #ffffff;
            background: #2563eb;
        }

        #load-btn {
            min-height: 40px;
            padding: 0 18px;
            border: 0;
            border-radius: 6px;
            color: #ffffff;
            background: #0f766e;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
        }

        #load-btn:hover {
            background: #115e59;
        }

        #load-btn:disabled {
            cursor: wait;
            background: #94a3b8;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .stat-box {
            min-height: 70px;
            padding: 12px 14px;
            background: #ffffff;
            border: 1px solid #d8dee4;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .stat-value {
            margin-top: 6px;
            color: #172033;
            font-size: 1.3rem;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .analysis-layout {
            display: grid;
            grid-template-columns: minmax(260px, 340px) minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        .correlation-list-panel,
        .chart-panel {
            background: #ffffff;
            border: 1px solid #d8dee4;
            border-radius: 8px;
        }

        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 48px;
            padding: 0 14px;
            border-bottom: 1px solid #d8dee4;
        }

        .panel-heading h2 {
            margin: 0;
            color: #243447;
            font-size: 1rem;
        }

        .panel-subtle {
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .correlation-table-wrap {
            max-height: 520px;
            overflow: auto;
        }

        .correlation-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 0.9rem;
        }

        .correlation-table th,
        .correlation-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            vertical-align: middle;
        }

        .correlation-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            color: #334155;
            background: #f8fafc;
            font-size: 0.82rem;
        }

        .correlation-table td:first-child {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .correlation-table tbody tr {
            cursor: pointer;
        }

        .correlation-table tbody tr:hover,
        .correlation-table tbody tr.is-selected {
            background: #eaf4ff;
        }

        .correlation-table .numeric {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .chart-wrap {
            position: relative;
            height: 560px;
            padding: 18px;
            box-sizing: border-box;
        }

        .empty-list {
            padding: 18px 14px;
            color: #64748b;
        }

        .hidden {
            display: none;
        }
        .filter-section { margin:0 0 18px; background:#fff; border:1px solid #d8dee4; border-radius:8px; overflow:visible; }
        .filter-section-header { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; padding:12px 14px; border:0; background:#f8fafc; color:#243447; cursor:pointer; text-align:left; }
        .filter-section-header:hover { background:#eef6ff; }
        .filter-section-title { margin:0; color:#243447; font-size:1rem; }
        .filter-section-body { padding:14px; border-top:1px solid #d8dee4; }
        .filter-section.is-collapsed .filter-section-body { display:none; }
        .filter-title-wrap { display:flex; align-items:center; gap:8px; }
        .filter-help { position:relative; display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border:1px solid #94a3b8; border-radius:50%; background:#fff; color:#2563eb; font-weight:800; font-size:0.9rem; cursor:help; }
        .filter-help-popup { position:absolute; left:50%; top:calc(100% + 10px); z-index:10; display:none; width:min(380px, 82vw); transform:translateX(-50%); padding:12px 14px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#243447; box-shadow:0 12px 24px rgba(15, 23, 42, 0.18); font-size:0.88rem; line-height:1.6; text-align:left; font-weight:500; }
        .filter-help:hover .filter-help-popup { display:block; }
        .filter-parts { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-bottom:10px; }
        .filter-parts button { min-height:34px; padding:0 12px; border:1px solid #b7c3d0; border-radius:6px; background:#fff; color:#243447; font-weight:700; cursor:pointer; }
        .filter-parts button:hover { background:#eef6ff; }
        .filter-insert-control { display:flex; align-items:center; gap:6px; color:#475569; font-size:0.9rem; font-weight:700; }
        .filter-insert-control select { min-height:34px; border:1px solid #b7c3d0; border-radius:6px; background:#fff; color:#243447; }
        .filter-builder { display:flex; flex-wrap:wrap; align-items:center; gap:8px; min-height:52px; margin-bottom:12px; padding:10px; background:#f8fafc; border:1px solid #d8dee4; border-radius:8px; }
        .filter-token { display:inline-flex; align-items:center; gap:8px; min-height:36px; padding:4px 6px 4px 10px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#243447; font-weight:800; }
        .filter-token select { min-height:30px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; }
        .filter-token-kind { min-width:86px; }
        .filter-target { min-width:220px; }
        .filter-token-operator { background:#ecfeff; border-color:#99f6e4; color:#0f766e; }
        .filter-token-not { background:#fff7ed; border-color:#fed7aa; color:#c2410c; }
        .filter-token-paren { background:#f1f5f9; font-size:1rem; }
        .filter-token-remove { width:26px; height:26px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#334155; font-size:1rem; font-weight:800; cursor:pointer; line-height:1; }
        .filter-token-remove:hover { background:#fee2e2; border-color:#fca5a5; color:#991b1b; }
        .filter-actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:12px; }
        .filter-actions button { min-height:36px; padding:0 14px; border:1px solid #b7c3d0; border-radius:6px; background:#fff; color:#243447; font-weight:700; cursor:pointer; }
        .filter-actions button:hover { background:#eef6ff; }
        .filter-summary { margin:0; color:#475569; font-size:0.9rem; font-weight:700; }
        .filter-summary.is-error { color:#b91c1c; }
        .filter-field { display:flex; align-items:center; gap:10px; }
        .filter-field select { min-width:160px; }
        .collapsible-filter { margin-top:12px; border:1px solid #d8dee4; border-radius:8px; overflow:hidden; }
        .collapsible-header { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; padding:10px 12px; border:0; background:#f8fafc; color:#243447; font-weight:800; text-align:left; cursor:pointer; }
        .collapsible-header:hover { background:#eef6ff; }
        .toggle-mark { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; background:#2563eb; color:#fff; font-size:1.2rem; line-height:1; }
        .collapsible-filter.is-collapsed .collapsible-body { display:none; }
        .collapsible-body { padding:12px; border-top:1px solid #d8dee4; }
        .checkbox-controls { margin-bottom:10px; }
        .checkbox-list { max-height:280px; overflow:auto; padding:10px; background:#f3f4f6; border-radius:6px; }
        .class-group-header { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:8px 10px; margin-top:8px; background:#e5e7eb; border-radius:6px; }
        .class-group-header h5 { margin:0; font-size:1rem; }
        .checkbox-item { display:inline-block; width:24%; min-width:220px; padding:6px 4px; vertical-align:top; }
        .question-checkbox-list .checkbox-item { width:48%; }
        .loading-text, .empty-filter-message { margin:0; color:#64748b; font-weight:700; }

        @media (max-width: 900px) {
            .feature-correlation-page {
                padding: calc(var(--header-height) + 18px) 14px 36px;
            }

            .page-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats-grid,
            .analysis-layout {
                grid-template-columns: 1fr;
            }

            .chart-wrap {
                height: 420px;
                padding: 12px;
            }

            .control-group,
            .control-group select {
                width: 100%;
            }

            .mode-toggle {
                width: 100%;
            }

            .mode-toggle label {
                flex: 1;
                justify-content: center;
                padding: 0 8px;
            }

            .filter-field {
                width: 100%;
            }

            .filter-field select {
                flex: 1;
                min-width: 0;
            }

            .filter-token,
            .filter-token select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php
    $teacher_page_title = '特徴量相関分析';
    include __DIR__ . '/teacher-menu.php';
?>
<div class="main-content">
    <main class="feature-correlation-page">
        <section class="analysis-controls" aria-label="相関分析条件">
            <div class="control-group">
                <span class="mode-label">表示対象</span>
                <div class="mode-toggle" role="group" aria-label="表示対象">
                    <label>
                        <input type="radio" name="correlation-mode" value="understand" checked>
                        迷い度
                    </label>
                    <label>
                        <input type="radio" name="correlation-mode" value="feature_pair">
                        特徴量同士
                    </label>
                </div>
            </div>

            <div class="control-group">
                <label for="feature-x-select">特徴量X</label>
                <select id="feature-x-select">
                    <?php foreach ($featureColumns as $col): ?>
                        <option value="<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="control-group hidden" id="feature-y-control">
                <label for="feature-y-select">特徴量Y</label>
                <select id="feature-y-select">
                    <?php foreach ($featureColumns as $col): ?>
                        <option value="<?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($col, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button id="load-btn" type="button">相関を表示</button>
        </section>

        <section class="filter-section" id="filter-section" aria-label="絞り込み条件">
            <button class="filter-section-header" id="filter-section-toggle" type="button" aria-expanded="true" aria-controls="filter-section-body">
                <span class="filter-title-wrap">
                    <h2 class="filter-section-title">絞り込み条件</h2>
                    <span class="filter-help" aria-label="論理式の説明">ⓘ
                        <span class="filter-help-popup">
                            論理式は、条件を AND・OR・NOT・括弧で組み合わせて対象を絞り込む書き方です。AND は両方、OR はどちらか、NOT はその条件に含まれない学習者を選びます。括弧を使うと先に計算する範囲を指定できます。例: (グループ(クラス)A OR グループ(クラス)B) AND NOT グループ1
                        </span>
                    </span>
                </span>
                <span class="toggle-mark" id="filter-section-mark" aria-hidden="true">-</span>
            </button>
            <div class="filter-section-body" id="filter-section-body">
                <div class="filter-parts" aria-label="論理式パーツ">
                    <button type="button" id="add-filter-condition">対象を追加</button>
                    <button type="button" id="add-filter-and">AND</button>
                    <button type="button" id="add-filter-or">OR</button>
                    <button type="button" id="add-filter-not">NOT</button>
                    <button type="button" id="add-filter-open">(</button>
                    <button type="button" id="add-filter-close">)</button>
                    <span class="filter-insert-control">
                        <label for="filter-insert-position">追加位置</label>
                        <select id="filter-insert-position"></select>
                    </span>
                </div>
                <div class="filter-builder" id="filter-builder"></div>
                <div class="filter-actions">
                    <button type="button" id="apply-filter-conditions">絞り込みを適用</button>
                    <button type="button" id="reset-filter-conditions">条件をリセット</button>
                    <button type="button" id="trim-filter-expression">追加位置から後ろを削除</button>
                    <button type="button" id="clear-filter-expression">式を空にする</button>
                    <p class="filter-summary" id="filter-summary">すべての学習者を表示しています。</p>
                </div>

            <div class="collapsible-filter" id="student-filter-panel">
                <button class="collapsible-header" type="button" aria-expanded="true" aria-controls="student-filter-body">
                    <span>学習者(UID)で絞り込み</span>
                    <span class="toggle-mark" aria-hidden="true">−</span>
                </button>
                <div class="collapsible-body" id="student-filter-body">
                    <div class="checkbox-controls">
                        <label><input type="checkbox" id="select-all-visible" checked> 全ての表示中学習者を 選択 / 解除</label>
                    </div>
                    <div class="checkbox-list" id="student-checkbox-list">
                        <?php foreach ($studentsByClass as $className => $students): ?>
                            <?php $classId = $students[0]['ClassID']; ?>
                            <div class="class-group-header" data-class-id="<?= htmlspecialchars($classId, ENT_QUOTES, 'UTF-8') ?>">
                                <h5><?= htmlspecialchars($className, ENT_QUOTES, 'UTF-8') ?></h5>
                                <label><input type="checkbox" class="select-all-class" data-class-id="<?= htmlspecialchars($classId, ENT_QUOTES, 'UTF-8') ?>" checked> このグループ(クラス)を全て選択 / 解除</label>
                            </div>
                            <?php foreach ($students as $student): ?>
                                <label class="checkbox-item" data-class-id="<?= htmlspecialchars($student['ClassID'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="checkbox" value="<?= htmlspecialchars($student['uid'], ENT_QUOTES, 'UTF-8') ?>" checked>
                                    <?= htmlspecialchars($student['Name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($student['uid'], ENT_QUOTES, 'UTF-8') ?>)
                                </label>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="collapsible-filter" id="question-filter-panel">
                <button class="collapsible-header" type="button" aria-expanded="true" aria-controls="question-filter-body">
                    <span>問題(WID)で絞り込み</span>
                    <span class="toggle-mark" aria-hidden="true">−</span>
                </button>
                <div class="collapsible-body" id="question-filter-body">
                    <div class="checkbox-controls">
                        <label><input type="checkbox" id="select-all-questions" checked> 全ての問題を 選択 / 解除</label>
                    </div>
                    <div class="checkbox-list question-checkbox-list" id="question-checkbox-list">
                        <p class="loading-text">問題リストを読み込んでいます...</p>
                    </div>
                </div>
            </div>
            </div>
        </section>

        <section class="stats-grid" aria-live="polite">
            <div class="stat-box">
                <div class="stat-label">相関係数 (Pearson r)</div>
                <div class="stat-value" id="correlation-value">-</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">データ件数</div>
                <div class="stat-value" id="count-value">-</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">表示中の組み合わせ</div>
                <div class="stat-value" id="pair-value">-</div>
            </div>
        </section>

        <section class="analysis-layout">
            <aside class="correlation-list-panel hidden" id="correlation-list-panel">
                <div class="panel-heading">
                    <h2 id="ranking-title">相関ランキング</h2>
                    <span class="panel-subtle" id="ranking-base-label"></span>
                </div>
                <div class="correlation-table-wrap">
                    <table class="correlation-table">
                        <thead>
                            <tr>
                                <th>比較特徴量</th>
                                <th class="numeric">r</th>
                                <th class="numeric">件数</th>
                            </tr>
                        </thead>
                        <tbody id="correlation-table-body"></tbody>
                    </table>
                    <div class="empty-list hidden" id="empty-list">相関を算出できる特徴量がありません。</div>
                </div>
            </aside>

            <section class="chart-panel">
                <div class="panel-heading">
                    <h2 id="chart-title">散布図</h2>
                    <span class="panel-subtle" id="chart-subtitle"></span>
                </div>
                <div class="chart-wrap">
                    <canvas id="scatterChart"></canvas>
                </div>
            </section>
        </section>
    </main>
</div>

<script>
const featureColumns = <?= json_encode($featureColumns, JSON_UNESCAPED_UNICODE) ?>;
const classStudentIdsByClass = <?= json_encode((object)$studentsByClassId, JSON_UNESCAPED_UNICODE) ?>;
const groupStudentIdsByGroup = <?= json_encode((object)$studentsByGroup, JSON_UNESCAPED_UNICODE) ?>;
const classFilterOptions = <?= json_encode($teacherClasses, JSON_UNESCAPED_UNICODE) ?>;
const groupFilterOptions = <?= json_encode($teacherGroups, JSON_UNESCAPED_UNICODE) ?>;
let scatterChart;
let currentRanking = [];

const modeInputs = document.querySelectorAll('input[name="correlation-mode"]');
const featureXSelect = document.getElementById('feature-x-select');
const featureYSelect = document.getElementById('feature-y-select');
const featureYControl = document.getElementById('feature-y-control');
const loadButton = document.getElementById('load-btn');
const correlationValue = document.getElementById('correlation-value');
const countValue = document.getElementById('count-value');
const pairValue = document.getElementById('pair-value');
const chartTitle = document.getElementById('chart-title');
const chartSubtitle = document.getElementById('chart-subtitle');
const rankingPanel = document.getElementById('correlation-list-panel');
const rankingTitle = document.getElementById('ranking-title');
const rankingBaseLabel = document.getElementById('ranking-base-label');
const rankingBody = document.getElementById('correlation-table-body');
const emptyList = document.getElementById('empty-list');
const filterSection = document.getElementById('filter-section');
const filterSectionToggle = document.getElementById('filter-section-toggle');
const filterSectionMark = document.getElementById('filter-section-mark');
const filterBuilder = document.getElementById('filter-builder');
const filterSummary = document.getElementById('filter-summary');
const addFilterConditionButton = document.getElementById('add-filter-condition');
const addFilterAndButton = document.getElementById('add-filter-and');
const addFilterOrButton = document.getElementById('add-filter-or');
const addFilterNotButton = document.getElementById('add-filter-not');
const addFilterOpenButton = document.getElementById('add-filter-open');
const addFilterCloseButton = document.getElementById('add-filter-close');
const filterInsertPosition = document.getElementById('filter-insert-position');
const applyFilterConditionsButton = document.getElementById('apply-filter-conditions');
const resetFilterConditionsButton = document.getElementById('reset-filter-conditions');
const trimFilterExpressionButton = document.getElementById('trim-filter-expression');
const clearFilterExpressionButton = document.getElementById('clear-filter-expression');
const studentCheckboxList = document.getElementById('student-checkbox-list');
const selectAllVisible = document.getElementById('select-all-visible');
const questionCheckboxList = document.getElementById('question-checkbox-list');
const selectAllQuestions = document.getElementById('select-all-questions');

function getStudentCheckboxItems() {
    return Array.from(studentCheckboxList.querySelectorAll('.checkbox-item'));
}

function getSelectedStudentIds() {
    return Array.from(studentCheckboxList.querySelectorAll('.checkbox-item input:checked')).map((input) => input.value);
}

function getSelectedWids() {
    return Array.from(questionCheckboxList.querySelectorAll('.question-checkbox-item input:checked')).map((input) => input.value);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function setupCollapsibleFilters() {
    filterSectionToggle.addEventListener('click', () => {
        const collapsed = filterSection.classList.toggle('is-collapsed');
        filterSectionToggle.setAttribute('aria-expanded', String(!collapsed));
        filterSectionMark.textContent = collapsed ? '+' : '-';
    });

    document.querySelectorAll('.collapsible-filter').forEach((panel) => {
        const button = panel.querySelector('.collapsible-header');
        const mark = panel.querySelector('.toggle-mark');
        button.addEventListener('click', () => {
            const collapsed = panel.classList.toggle('is-collapsed');
            button.setAttribute('aria-expanded', String(!collapsed));
            mark.textContent = collapsed ? '+' : '−';
        });
    });
}

function getAllStudentIds() {
    return getStudentCheckboxItems().map((item) => item.querySelector('input').value);
}

function getFilterTargetOptions() {
    const classTargets = classFilterOptions.map((option) => ({
        type: 'class',
        value: `class:${option.ClassID}`,
        label: `グループ(クラス): ${option.ClassName}`,
    }));
    const groupTargets = groupFilterOptions.map((option) => ({
        type: 'group',
        value: `group:${option.group_id}`,
        label: `グループ: ${option.group_name}`,
    }));
    return [...classTargets, ...groupTargets];
}

function createFilterTargetOptionsHtml(selectedValue = '') {
    const options = getFilterTargetOptions();
    if (options.length === 0) {
        return '<option value="">選択肢がありません</option>';
    }

    return options.map((option) => {
        const selected = option.value === String(selectedValue) ? ' selected' : '';
        return `<option value="${escapeHtml(option.value)}"${selected}>${escapeHtml(option.label)}</option>`;
    }).join('');
}

function createFilterKindSelectHtml(selectedType) {
    const options = [
        { value: 'condition', label: '対象' },
        { value: 'and', label: 'AND' },
        { value: 'or', label: 'OR' },
        { value: 'not', label: 'NOT' },
        { value: 'open', label: '(' },
        { value: 'close', label: ')' },
    ];

    return options.map((option) => {
        const selected = option.value === selectedType ? ' selected' : '';
        return `<option value="${option.value}"${selected}>${option.label}</option>`;
    }).join('');
}

function getKindFromToken(type, value = '') {
    if (type === 'condition') {
        return 'condition';
    }
    if (type === 'operator') {
        return String(value).toLowerCase();
    }
    return value === '(' ? 'open' : 'close';
}

function getTokenSpecFromKind(kind) {
    if (kind === 'condition') {
        return { type: 'condition', value: '' };
    }
    if (kind === 'and') {
        return { type: 'operator', value: 'AND' };
    }
    if (kind === 'or') {
        return { type: 'operator', value: 'OR' };
    }
    if (kind === 'not') {
        return { type: 'operator', value: 'NOT' };
    }
    return { type: 'paren', value: kind === 'open' ? '(' : ')' };
}

function createFilterToken(type, value = '') {
    const token = document.createElement('div');
    token.className = 'filter-token';
    token.dataset.tokenType = type;
    const kind = getKindFromToken(type, value);
    const kindSelect = `<select class="filter-token-kind" aria-label="部品の種類">${createFilterKindSelectHtml(kind)}</select>`;

    if (type === 'condition') {
        token.classList.add('filter-token-condition');
        token.innerHTML = `
            ${kindSelect}
            <select class="filter-target" aria-label="絞り込み対象">
                ${createFilterTargetOptionsHtml(value)}
            </select>
            <button type="button" class="filter-token-remove" aria-label="部品を削除">×</button>
        `;
    } else if (type === 'operator') {
        token.classList.add('filter-token-operator');
        token.classList.toggle('filter-token-not', value === 'NOT');
        token.dataset.operator = value;
        token.innerHTML = `
            ${kindSelect}
            <button type="button" class="filter-token-remove" aria-label="部品を削除">×</button>
        `;
    } else {
        token.classList.add('filter-token-paren');
        token.dataset.paren = value;
        token.innerHTML = `
            ${kindSelect}
            <button type="button" class="filter-token-remove" aria-label="部品を削除">×</button>
        `;
    }

    const insertedIndex = insertFilterToken(token);
    updateFilterInsertOptions();
    if (insertedIndex !== null) {
        filterInsertPosition.value = String(insertedIndex + 1);
    }
    return token;
}

function insertFilterToken(token) {
    const tokens = Array.from(filterBuilder.querySelectorAll('.filter-token'));
    const position = filterInsertPosition.value === '' ? tokens.length : Number(filterInsertPosition.value);
    if (!Number.isInteger(position) || position >= tokens.length) {
        filterBuilder.appendChild(token);
        return null;
    }
    const safePosition = Math.max(position, 0);
    filterBuilder.insertBefore(token, tokens[safePosition]);
    return safePosition;
}

function updateFilterInsertOptions() {
    const tokens = Array.from(filterBuilder.querySelectorAll('.filter-token'));
    const current = filterInsertPosition.value;
    const options = ['<option value="">末尾に追加</option>'];
    tokens.forEach((token, index) => {
        const label = getTokenDisplayLabel(token);
        options.push(`<option value="${index}">${index + 1}個目の前 (${escapeHtml(label)})</option>`);
    });
    filterInsertPosition.innerHTML = options.join('');
    if (current !== '' && Number(current) < tokens.length) {
        filterInsertPosition.value = current;
    }
}

function getTokenDisplayLabel(token) {
    const type = token.dataset.tokenType;
    if (type === 'condition') {
        const select = token.querySelector('.filter-target');
        return select.options[select.selectedIndex]?.textContent || '対象';
    }
    if (type === 'operator') {
        return token.dataset.operator || '演算子';
    }
    return token.dataset.paren || '括弧';
}

function replaceFilterToken(token, nextKind) {
    const { type, value } = getTokenSpecFromKind(nextKind);
    token.className = 'filter-token';
    token.dataset.tokenType = type;
    delete token.dataset.operator;
    delete token.dataset.paren;

    const kindSelect = `<select class="filter-token-kind" aria-label="部品の種類">${createFilterKindSelectHtml(nextKind)}</select>`;
    if (type === 'condition') {
        token.classList.add('filter-token-condition');
        token.innerHTML = `
            ${kindSelect}
            <select class="filter-target" aria-label="絞り込み対象">
                ${createFilterTargetOptionsHtml()}
            </select>
            <button type="button" class="filter-token-remove" aria-label="部品を削除">×</button>
        `;
        return;
    }

    if (type === 'operator') {
        token.classList.add('filter-token-operator');
        token.classList.toggle('filter-token-not', value === 'NOT');
        token.dataset.operator = value;
    } else {
        token.classList.add('filter-token-paren');
        token.dataset.paren = value;
    }
    token.innerHTML = `
        ${kindSelect}
        <button type="button" class="filter-token-remove" aria-label="部品を削除">×</button>
    `;
}

function getFilterExpressionTokens() {
    return Array.from(filterBuilder.querySelectorAll('.filter-token')).map((token) => {
        const type = token.dataset.tokenType;
        if (type === 'condition') {
            const value = token.querySelector('.filter-target').value;
            const [targetType, targetId] = value.split(':');
            return { type, targetType, targetId, value };
        }
        if (type === 'operator') {
            return { type, operator: token.dataset.operator };
        }
        return { type: 'paren', paren: token.dataset.paren };
    });
}

function unionSets(left, right) {
    const result = new Set(left);
    right.forEach((uid) => result.add(uid));
    return result;
}

function intersectSets(left, right) {
    return new Set(Array.from(left).filter((uid) => right.has(uid)));
}

function complementSet(source) {
    const selected = new Set(source);
    return new Set(getAllStudentIds().filter((uid) => !selected.has(uid)));
}

function getConditionStudentSet(type, value) {
    const source = type === 'group' ? groupStudentIdsByGroup : classStudentIdsByClass;
    return new Set((source[String(value)] || []).map(String));
}

function validateFilterExpression(tokens) {
    if (tokens.length === 0) {
        return;
    }

    let expectsOperand = true;
    let depth = 0;
    tokens.forEach((token) => {
        if (token.type === 'condition') {
            if (!expectsOperand) {
                throw new Error('条件の間には AND または OR を入れてください。');
            }
            if (!token.value || !token.targetType || !token.targetId) {
                throw new Error('対象が選択されていない条件があります。');
            }
            expectsOperand = false;
            return;
        }

        if (token.type === 'operator') {
            if (token.operator === 'NOT') {
                if (!expectsOperand) {
                    throw new Error('NOT の前には AND または OR を入れてください。');
                }
                return;
            }
            if (expectsOperand) {
                throw new Error('AND または OR の前に条件を置いてください。');
            }
            expectsOperand = true;
            return;
        }

        if (token.paren === '(') {
            if (!expectsOperand) {
                throw new Error('括弧の前には AND または OR を入れてください。');
            }
            depth++;
            return;
        }

        if (depth === 0) {
            throw new Error('閉じ括弧が多すぎます。');
        }
        if (expectsOperand) {
            throw new Error('括弧の中に条件を入れてください。');
        }
        depth--;
        expectsOperand = false;
    });

    if (depth > 0) {
        throw new Error('閉じていない括弧があります。');
    }
    if (expectsOperand) {
        throw new Error('式の最後は条件または閉じ括弧にしてください。');
    }
}

function evaluateFilterConditions() {
    const tokens = getFilterExpressionTokens();
    if (tokens.length === 0) {
        return new Set(getAllStudentIds());
    }

    validateFilterExpression(tokens);
    let index = 0;

    function parsePrimary() {
        const token = tokens[index];
        if (!token) {
            throw new Error('式が途中で終わっています。');
        }

        if (token.type === 'operator' && token.operator === 'NOT') {
            index++;
            return complementSet(parsePrimary());
        }

        if (token.type === 'condition') {
            index++;
            return getConditionStudentSet(token.targetType, token.targetId);
        }

        if (token.type === 'paren' && token.paren === '(') {
            index++;
            const result = parseOr();
            if (!tokens[index] || tokens[index].type !== 'paren' || tokens[index].paren !== ')') {
                throw new Error('閉じていない括弧があります。');
            }
            index++;
            return result;
        }

        throw new Error('条件または開き括弧を置いてください。');
    }

    function parseAnd() {
        let result = parsePrimary();
        while (tokens[index] && tokens[index].type === 'operator' && tokens[index].operator === 'AND') {
            index++;
            result = intersectSets(result, parsePrimary());
        }
        return result;
    }

    function parseOr() {
        let result = parseAnd();
        while (tokens[index] && tokens[index].type === 'operator' && tokens[index].operator === 'OR') {
            index++;
            result = unionSets(result, parseAnd());
        }
        return result;
    }

    const result = parseOr();
    if (index !== tokens.length) {
        throw new Error('式の並びが正しくありません。');
    }
    return result;
}

function updateVisibleStudents() {
    getStudentCheckboxItems().forEach((item) => {
        item.style.display = 'inline-block';
    });
    studentCheckboxList.querySelectorAll('.class-group-header').forEach((header) => {
        header.style.display = 'flex';
    });
    syncStudentControlStates();
}

async function applyFilterConditions() {
    let selectedSet;
    try {
        selectedSet = evaluateFilterConditions();
    } catch (error) {
        filterSummary.textContent = error.message || '論理式が正しくありません。';
        filterSummary.classList.add('is-error');
        return;
    }

    getStudentCheckboxItems().forEach((item) => {
        const input = item.querySelector('input');
        input.checked = selectedSet.has(input.value);
    });
    syncStudentControlStates();
    filterSummary.textContent = `${selectedSet.size}名の学習者を選択しています。`;
    filterSummary.classList.remove('is-error');
    await refreshQuestionFilters();
    loadData(true);
}

function resetFilterConditions() {
    filterBuilder.innerHTML = '';
    updateFilterInsertOptions();
    createFilterToken('condition');
    getStudentCheckboxItems().forEach((item) => {
        item.querySelector('input').checked = true;
    });
    updateVisibleStudents();
    filterSummary.textContent = 'すべての学習者を表示しています。';
    filterSummary.classList.remove('is-error');
}

function clearFilterExpression() {
    filterBuilder.innerHTML = '';
    updateFilterInsertOptions();
    filterSummary.textContent = '論理式を空にしました。空のまま適用すると、すべての学習者が対象になります。';
    filterSummary.classList.remove('is-error');
}

function trimFilterExpressionFromPosition() {
    const tokens = Array.from(filterBuilder.querySelectorAll('.filter-token'));
    if (tokens.length === 0) {
        filterSummary.textContent = '削除する部品がありません。';
        filterSummary.classList.remove('is-error');
        return;
    }

    if (filterInsertPosition.value === '') {
        filterSummary.textContent = '削除を開始する位置を「追加位置」から選んでください。';
        filterSummary.classList.add('is-error');
        return;
    }

    const startIndex = Number(filterInsertPosition.value);
    tokens.forEach((token, index) => {
        if (index >= startIndex) {
            token.remove();
        }
    });
    updateFilterInsertOptions();
    filterSummary.textContent = `${startIndex + 1}個目以降の部品を削除しました。`;
    filterSummary.classList.remove('is-error');
}

function syncStudentControlStates() {
    const studentItems = getStudentCheckboxItems();
    const visibleItems = studentItems.filter((item) => item.style.display !== 'none');

    selectAllVisible.checked = visibleItems.length > 0
        && visibleItems.every((item) => item.querySelector('input').checked);

    studentCheckboxList.querySelectorAll('.select-all-class').forEach((input) => {
        const classId = input.dataset.classId;
        const classItems = studentItems.filter((item) => item.dataset.classId === classId);
        input.checked = classItems.length > 0
            && classItems.every((item) => item.querySelector('input').checked);
    });
}

function renderQuestionCheckboxes(questions) {
    if (!Array.isArray(questions) || questions.length === 0) {
        questionCheckboxList.innerHTML = '<p class="empty-filter-message">選択中の学習者に紐づく問題はありません。</p>';
        selectAllQuestions.checked = false;
        return;
    }

    questionCheckboxList.innerHTML = questions.map((question) => {
        const wid = escapeHtml(question.WID);
        const sentence = question.Sentence ? ` : ${escapeHtml(question.Sentence)}` : '';
        return `<label class="checkbox-item question-checkbox-item"><input type="checkbox" value="${wid}" checked> WID:${wid}${sentence}</label>`;
    }).join('');
    selectAllQuestions.checked = true;
}

async function refreshQuestionFilters() {
    const selectedStudents = getSelectedStudentIds();
    if (selectedStudents.length === 0) {
        renderQuestionCheckboxes([]);
        return;
    }

    questionCheckboxList.innerHTML = '<p class="loading-text">問題リストを読み込んでいます...</p>';
    const body = new URLSearchParams({
        action: 'get_wids_for_students',
        student_ids: JSON.stringify(selectedStudents),
    });

    const response = await fetch('feature_correlation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
    });
    const data = await response.json();
    if (data.error) {
        throw new Error(data.error);
    }
    renderQuestionCheckboxes(data.items || []);
}

function getMode() {
    const checked = document.querySelector('input[name="correlation-mode"]:checked');
    return checked ? checked.value : 'understand';
}

function formatValue(value, fractionDigits = 3) {
    const number = Number(value);
    if (!Number.isFinite(number)) {
        return '-';
    }
    return number.toLocaleString('ja-JP', {
        maximumFractionDigits: fractionDigits,
    });
}

function formatCorrelation(value) {
    const number = Number(value);
    if (!Number.isFinite(number)) {
        return '算出不可';
    }
    return number.toFixed(4);
}

function ensureDifferentFeaturePair() {
    if (featureColumns.length < 2) {
        return;
    }

    if (featureXSelect.value !== featureYSelect.value) {
        return;
    }

    const alternative = featureColumns.find((feature) => feature !== featureXSelect.value);
    if (alternative) {
        featureYSelect.value = alternative;
    }
}

function syncControls() {
    const mode = getMode();
    const isFeaturePair = mode === 'feature_pair';
    featureYControl.classList.toggle('hidden', !isFeaturePair);
    rankingPanel.classList.toggle('hidden', !(mode === 'understand' || isFeaturePair));
    rankingTitle.textContent = mode === 'understand' ? '迷い度との相関ランキング' : '相関ランキング';
    if (isFeaturePair) {
        ensureDifferentFeaturePair();
    }
}

function setLoading(isLoading) {
    loadButton.disabled = isLoading;
    loadButton.textContent = isLoading ? '読み込み中' : '相関を表示';
}

function renderStats(data) {
    correlationValue.textContent = formatCorrelation(data.correlation);
    countValue.textContent = formatValue(data.count, 0);
    pairValue.textContent = `${data.x_label} × ${data.y_label}`;
    chartTitle.textContent = `${data.x_label} × ${data.y_label}`;
    chartSubtitle.textContent = `r = ${formatCorrelation(data.correlation)}`;
}

function calculateAxisOptions(points, key) {
    if (!Array.isArray(points) || points.length === 0) {
        return {};
    }

    const values = points
        .map((point) => Number(point[key]))
        .filter((value) => Number.isFinite(value));

    if (values.length === 0) {
        return {};
    }

    const min = Math.min(...values);
    const max = Math.max(...values);

    if (min === max) {
        const padding = Math.max(Math.abs(min) * 0.1, 1);
        return {
            min: min - padding,
            max: max + padding,
        };
    }

    const range = max - min;
    const padding = range * 0.05;
    return {
        min: min - padding,
        max: max + padding,
    };
}

function renderChart(points, xLabel, yLabel, mode) {
    const ctx = document.getElementById('scatterChart').getContext('2d');
    if (scatterChart) {
        scatterChart.destroy();
    }

    const xAxis = calculateAxisOptions(points, 'x');
    const yAxis = calculateAxisOptions(points, 'y');

    scatterChart = new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: `${xLabel} × ${yLabel}`,
                data: points,
                backgroundColor: mode === 'feature_pair' ? 'rgba(20, 184, 166, 0.82)' : 'rgba(225, 29, 72, 0.82)',
                borderColor: mode === 'feature_pair' ? 'rgba(15, 118, 110, 0.95)' : 'rgba(190, 18, 60, 0.95)',
                borderWidth: 1,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            parsing: false,
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const point = context.raw;
                            const lines = [
                                `UID:${point.uid} WID:${point.wid} attempt:${point.attempt}`,
                                `${xLabel}: ${formatValue(point.x, 4)}`,
                                `${yLabel}: ${formatValue(point.y, 4)}`,
                            ];
                            if (mode === 'feature_pair' && point.understand !== null) {
                                lines.push(`Understand(迷い度): ${formatValue(point.understand, 0)}`);
                            }
                            return lines;
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: { display: true, text: xLabel, color: '#334155', font: { weight: 'bold' } },
                    grid: { color: '#d8dee4' },
                    ticks: { color: '#334155' },
                    ...xAxis,
                },
                y: {
                    title: { display: true, text: yLabel, color: '#334155', font: { weight: 'bold' } },
                    grid: { color: '#d8dee4' },
                    ticks: { color: '#334155' },
                    ...yAxis,
                }
            }
        }
    });
}

function renderRanking(items) {
    const mode = getMode();
    const isUnderstandMode = mode === 'understand';
    rankingBody.innerHTML = '';
    emptyList.classList.toggle('hidden', items.length > 0);
    rankingBaseLabel.textContent = isUnderstandMode ? 'Understand(迷い度)' : featureXSelect.value;

    const selectedFeature = isUnderstandMode ? featureXSelect.value : featureYSelect.value;
    items.forEach((item) => {
        const feature = isUnderstandMode ? item.feature : item.feature_y;
        const row = document.createElement('tr');
        row.dataset.feature = feature;
        row.classList.toggle('is-selected', feature === selectedFeature);

        const featureCell = document.createElement('td');
        featureCell.textContent = feature;
        featureCell.title = feature;

        const correlationCell = document.createElement('td');
        correlationCell.className = 'numeric';
        correlationCell.textContent = formatCorrelation(item.correlation);

        const countCell = document.createElement('td');
        countCell.className = 'numeric';
        countCell.textContent = formatValue(item.count, 0);

        row.append(featureCell, correlationCell, countCell);
        row.addEventListener('click', () => {
            if (isUnderstandMode) {
                featureXSelect.value = feature;
            } else {
                featureYSelect.value = feature;
            }
            loadData(false);
        });
        rankingBody.appendChild(row);
    });
}

function updateRankingSelection() {
    const mode = getMode();
    const selectedFeature = mode === 'understand' ? featureXSelect.value : featureYSelect.value;
    rankingBody.querySelectorAll('tr').forEach((row) => {
        row.classList.toggle('is-selected', row.dataset.feature === selectedFeature);
    });
}

async function loadRanking() {
    const body = new URLSearchParams({
        action: 'get_feature_correlation_list',
        feature_x: featureXSelect.value,
        student_ids: JSON.stringify(getSelectedStudentIds()),
        wids: JSON.stringify(getSelectedWids()),
        wid_filter_enabled: '1',
    });

    const response = await fetch('feature_correlation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
    });
    const data = await response.json();

    if (data.error) {
        throw new Error(data.error);
    }

    currentRanking = data.items || [];
    renderRanking(currentRanking);
}

async function loadUnderstandRanking() {
    const body = new URLSearchParams({
        action: 'get_understand_correlation_list',
        student_ids: JSON.stringify(getSelectedStudentIds()),
        wids: JSON.stringify(getSelectedWids()),
        wid_filter_enabled: '1',
    });

    const response = await fetch('feature_correlation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
    });
    const data = await response.json();

    if (data.error) {
        throw new Error(data.error);
    }

    currentRanking = data.items || [];
    renderRanking(currentRanking);
}

async function loadData(refreshRanking = true) {
    syncControls();
    setLoading(true);

    try {
        const mode = getMode();
        const body = new URLSearchParams({
            action: 'get_correlation_data',
            mode,
            feature: featureXSelect.value,
            feature_x: featureXSelect.value,
            feature_y: featureYSelect.value,
            student_ids: JSON.stringify(getSelectedStudentIds()),
            wids: JSON.stringify(getSelectedWids()),
            wid_filter_enabled: '1',
        });

        const response = await fetch('feature_correlation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        });
        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        renderStats(data);
        renderChart(data.points || [], data.x_label, data.y_label, data.mode);

        if (mode === 'understand') {
            if (refreshRanking) {
                await loadUnderstandRanking();
            } else {
                updateRankingSelection();
            }
        } else if (mode === 'feature_pair') {
            if (refreshRanking) {
                await loadRanking();
            } else {
                updateRankingSelection();
            }
        }
    } catch (error) {
        alert(error.message || 'データの読み込みに失敗しました。');
    } finally {
        setLoading(false);
    }
}

modeInputs.forEach((input) => {
    input.addEventListener('change', () => loadData(true));
});
featureXSelect.addEventListener('change', () => loadData(true));
featureYSelect.addEventListener('change', () => loadData(false));
loadButton.addEventListener('click', () => loadData(true));
addFilterConditionButton.addEventListener('click', () => {
    createFilterToken('condition');
});
addFilterAndButton.addEventListener('click', () => createFilterToken('operator', 'AND'));
addFilterOrButton.addEventListener('click', () => createFilterToken('operator', 'OR'));
addFilterNotButton.addEventListener('click', () => createFilterToken('operator', 'NOT'));
addFilterOpenButton.addEventListener('click', () => createFilterToken('paren', '('));
addFilterCloseButton.addEventListener('click', () => createFilterToken('paren', ')'));
applyFilterConditionsButton.addEventListener('click', applyFilterConditions);
resetFilterConditionsButton.addEventListener('click', async () => {
    resetFilterConditions();
    await refreshQuestionFilters();
    loadData(true);
});
trimFilterExpressionButton.addEventListener('click', trimFilterExpressionFromPosition);
clearFilterExpressionButton.addEventListener('click', clearFilterExpression);
filterBuilder.addEventListener('change', (event) => {
    const token = event.target.closest('.filter-token');
    if (!token) {
        return;
    }
    if (event.target.classList.contains('filter-token-kind')) {
        replaceFilterToken(token, event.target.value);
    }
    updateFilterInsertOptions();
});
filterBuilder.addEventListener('click', (event) => {
    if (!event.target.classList.contains('filter-token-remove')) {
        return;
    }
    event.target.closest('.filter-token').remove();
    updateFilterInsertOptions();
});
selectAllVisible.addEventListener('change', async (event) => {
    getStudentCheckboxItems()
        .filter((item) => item.style.display !== 'none')
        .forEach((item) => { item.querySelector('input').checked = event.target.checked; });
    syncStudentControlStates();
    await refreshQuestionFilters();
    loadData(true);
});
studentCheckboxList.addEventListener('change', async (event) => {
    if (event.target.classList.contains('select-all-class')) {
        const classId = event.target.dataset.classId;
        getStudentCheckboxItems()
            .filter((item) => item.dataset.classId === classId)
            .filter((item) => item.style.display !== 'none')
            .forEach((item) => { item.querySelector('input').checked = event.target.checked; });
    }
    syncStudentControlStates();
    await refreshQuestionFilters();
    loadData(true);
});
selectAllQuestions.addEventListener('change', (event) => {
    questionCheckboxList.querySelectorAll('.question-checkbox-item input')
        .forEach((input) => { input.checked = event.target.checked; });
    loadData(true);
});
questionCheckboxList.addEventListener('change', (event) => {
    if (event.target.matches('.question-checkbox-item input')) {
        loadData(true);
    }
});

setupCollapsibleFilters();
resetFilterConditions();
syncControls();
updateVisibleStudents();
syncStudentControlStates();
refreshQuestionFilters()
    .then(() => loadData(true))
    .catch((error) => alert(error.message || '問題リストの読み込みに失敗しました。'));
</script>
</body>
</html>

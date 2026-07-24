<?php
include '../lang.php';
require "../dbc.php";
require_once __DIR__ . "/student-feature-tooltip.php";

function student_feature_avg_column_sql(string $column): string
{
    return "feat.`avg_" . str_replace('`', '``', $column) . "`";
}

function feature_filter_error_response(string $message): void
{
    http_response_code(400);
    $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo "<li class='student-list-status student-list-error'>{$safe_message}</li>";
    exit;
}

function normalize_feature_filter_expression(string $json, array $available_feature_columns): array
{
    $json = trim($json);
    if ($json === '' || $json === '[]') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        throw new InvalidArgumentException('特徴量の論理式を読み込めませんでした。');
    }

    $tokens = [];
    foreach ($decoded as $token) {
        if (!is_array($token)) {
            throw new InvalidArgumentException('特徴量の論理式が正しくありません。');
        }

        $type = $token['type'] ?? '';
        if ($type === 'condition') {
            $feature = (string)($token['feature'] ?? '');
            if (!isset($available_feature_columns[$feature])) {
                throw new InvalidArgumentException('無効な特徴量が含まれています。');
            }
            $tokens[] = ['type' => 'condition', 'feature' => $feature];
            continue;
        }
        if ($type === 'operator') {
            $operator = strtoupper((string)($token['operator'] ?? ''));
            if (!in_array($operator, ['AND', 'OR', 'NOT'], true)) {
                throw new InvalidArgumentException('無効な論理演算子が含まれています。');
            }
            $tokens[] = ['type' => 'operator', 'operator' => $operator];
            continue;
        }
        if ($type === 'paren') {
            $paren = (string)($token['paren'] ?? '');
            if (!in_array($paren, ['(', ')'], true)) {
                throw new InvalidArgumentException('無効な括弧が含まれています。');
            }
            $tokens[] = ['type' => 'paren', 'paren' => $paren];
            continue;
        }
        throw new InvalidArgumentException('特徴量の論理式が正しくありません。');
    }
    return $tokens;
}

function validate_feature_filter_expression(array $tokens): void
{
    if (empty($tokens)) {
        return;
    }

    $expects_operand = true;
    $depth = 0;
    foreach ($tokens as $token) {
        if ($token['type'] === 'condition') {
            if (!$expects_operand) {
                throw new InvalidArgumentException('条件の間には AND または OR を入れてください。');
            }
            $expects_operand = false;
            continue;
        }
        if ($token['type'] === 'operator') {
            if ($token['operator'] === 'NOT') {
                if (!$expects_operand) {
                    throw new InvalidArgumentException('NOT の前には AND または OR を入れてください。');
                }
                continue;
            }
            if ($expects_operand) {
                throw new InvalidArgumentException('AND または OR の前に条件を置いてください。');
            }
            $expects_operand = true;
            continue;
        }
        if ($token['paren'] === '(') {
            if (!$expects_operand) {
                throw new InvalidArgumentException('括弧の前には AND または OR を入れてください。');
            }
            $depth++;
            continue;
        }
        if ($depth === 0) {
            throw new InvalidArgumentException('閉じ括弧が多すぎます。');
        }
        if ($expects_operand) {
            throw new InvalidArgumentException('括弧の中に条件を入れてください。');
        }
        $depth--;
        $expects_operand = false;
    }
    if ($depth > 0) {
        throw new InvalidArgumentException('閉じていない括弧があります。');
    }
    if ($expects_operand) {
        throw new InvalidArgumentException('式の最後は条件または閉じ括弧にしてください。');
    }
}

function feature_filter_range_values(array $posted_feature_filters, string $feature): array
{
    $condition = $posted_feature_filters[$feature] ?? [];
    if (!is_array($condition)) {
        return [null, null];
    }
    $min = trim((string)($condition['min'] ?? ''));
    $max = trim((string)($condition['max'] ?? ''));
    $min_value = $min !== '' && is_numeric($min) ? feature_storage_numeric_value($feature, $min) : null;
    $max_value = $max !== '' && is_numeric($max) ? feature_storage_numeric_value($feature, $max) : null;
    return [$min_value, $max_value];
}

function build_feature_condition_sql(string $feature, array $posted_feature_filters, string &$types, array &$params): string
{
    $column_sql = student_feature_avg_column_sql($feature);
    $conditions = ["{$column_sql} IS NOT NULL"];
    [$min_value, $max_value] = feature_filter_range_values($posted_feature_filters, $feature);
    if ($min_value !== null) {
        $conditions[] = "{$column_sql} >= ?";
        $params[] = $min_value;
        $types .= 'd';
    }
    if ($max_value !== null) {
        $conditions[] = "{$column_sql} <= ?";
        $params[] = $max_value;
        $types .= 'd';
    }
    return '(' . implode(' AND ', $conditions) . ')';
}

function build_feature_filter_expression_sql(array $tokens, array $posted_feature_filters, string &$types, array &$params): string
{
    if (empty($tokens)) {
        return '';
    }
    $parts = [];
    foreach ($tokens as $token) {
        if ($token['type'] === 'condition') {
            $parts[] = build_feature_condition_sql($token['feature'], $posted_feature_filters, $types, $params);
        } elseif ($token['type'] === 'operator') {
            $parts[] = $token['operator'];
        } else {
            $parts[] = $token['paren'];
        }
    }
    return '(' . implode(' ', $parts) . ')';
}

function normalize_filter_id_list($value): array
{
    if (!is_array($value)) {
        return [];
    }
    $normalized = [];
    foreach ($value as $item) {
        if (!is_scalar($item)) {
            continue;
        }
        $id = trim((string)$item);
        if ($id !== '') {
            $normalized[$id] = $id;
        }
    }
    return array_values($normalized);
}

$uids = normalize_filter_id_list($_POST['uid'] ?? []);
$wids = normalize_filter_id_list($_POST['wid'] ?? []);
$uid_selection_present = (string)($_POST['uid_selection_present'] ?? '') === '1';
$wid_selection_present = (string)($_POST['wid_selection_present'] ?? '') === '1';
$average_scope = (string)($_POST['average_scope'] ?? 'selected');
$average_scope = in_array($average_scope, ['all', 'selected'], true) ? $average_scope : 'selected';
$hesitation_filter = (string)($_POST['hesitation_filter'] ?? '');
$correctness_filter = (string)($_POST['correctness_filter'] ?? '');
$accuracy_min = is_numeric($_POST['accuracy_min'] ?? null) ? (float)$_POST['accuracy_min'] : 0;
$accuracy_max = is_numeric($_POST['accuracy_max'] ?? null) ? (float)$_POST['accuracy_max'] : 100;
$hesitation_rate_min = is_numeric($_POST['hesitation_rate_min'] ?? null) ? (float)$_POST['hesitation_rate_min'] : 0;
$hesitation_rate_max = is_numeric($_POST['hesitation_rate_max'] ?? null) ? (float)$_POST['hesitation_rate_max'] : 100;
$total_answers_min = is_numeric($_POST['total_answers_min'] ?? null) ? (int)$_POST['total_answers_min'] : 0;
$total_answers_max = is_numeric($_POST['total_answers_max'] ?? null) ? (int)$_POST['total_answers_max'] : 99999999;
$feature_join_sql = student_feature_pair_average_join_sql($conn);
$available_feature_columns = student_feature_columns();
$feature_table_exists = student_feature_table_exists($conn);
$posted_feature_filters = is_array($_POST['feature_filters'] ?? null) ? $_POST['feature_filters'] : [];
$posted_feature_expression = is_string($_POST['feature_filter_expression'] ?? null) ? $_POST['feature_filter_expression'] : '';

if (($uid_selection_present && empty($uids)) || ($wid_selection_present && empty($wids))) {
    echo "<li class='student-list-status'>条件に該当する学習者はいません。</li>";
    $conn->close();
    exit;
}

try {
    $feature_expression_tokens = normalize_feature_filter_expression($posted_feature_expression, $available_feature_columns);
    validate_feature_filter_expression($feature_expression_tokens);
} catch (InvalidArgumentException $error) {
    feature_filter_error_response($error->getMessage());
}

$feature_expression_sql = '';
$feature_expression_types = '';
$feature_expression_params = [];
if (!empty($feature_expression_tokens) && $feature_table_exists) {
    $feature_expression_sql = build_feature_filter_expression_sql(
        $feature_expression_tokens,
        $posted_feature_filters,
        $feature_expression_types,
        $feature_expression_params
    );
}

$sql = "SELECT DISTINCT s.uid, s.Name
        FROM students s
        JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
        LEFT JOIN (
            SELECT uid,
                   (SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS accuracy,
                   COUNT(*) AS total_answers
            FROM linedata
            GROUP BY uid
        ) acc ON s.uid = acc.uid
        LEFT JOIN (
            SELECT uid,
                   (SUM(CASE WHEN Understand = 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS hesitation_rate
            FROM temporary_results
            GROUP BY uid
        ) hes ON s.uid = hes.uid
        {$feature_join_sql}
        LEFT JOIN linedata ld
            ON s.uid = ld.UID
            AND feat.WID = ld.WID
            AND feat.attempt = ld.attempt
        LEFT JOIN temporary_results tr
            ON s.uid = tr.UID
            AND feat.WID = tr.WID
            AND feat.attempt = tr.attempt
            AND tr.teacher_id = ct.TID
        WHERE ct.TID = ? AND feat.WID IS NOT NULL";

$params = [(string)($_SESSION['MemberID'] ?? '')];
$types = 's';
if ($uid_selection_present) {
    $placeholders = implode(',', array_fill(0, count($uids), '?'));
    $sql .= " AND s.uid IN ({$placeholders})";
    $params = array_merge($params, $uids);
    $types .= str_repeat('s', count($uids));
}
if ($wid_selection_present) {
    $placeholders = implode(',', array_fill(0, count($wids), '?'));
    $sql .= " AND feat.WID IN ({$placeholders})";
    $params = array_merge($params, $wids);
    $types .= str_repeat('s', count($wids));
}
if ($hesitation_filter === 'hesitated') {
    $sql .= ' AND tr.Understand = 2';
} elseif ($hesitation_filter === 'not_hesitated') {
    $sql .= ' AND tr.Understand = 4';
}
if ($correctness_filter === 'correct') {
    $sql .= ' AND ld.TF = 1';
} elseif ($correctness_filter === 'incorrect') {
    $sql .= ' AND ld.TF = 0';
}

$sql .= ' AND COALESCE(acc.accuracy, 0) BETWEEN ? AND ?';
$params[] = $accuracy_min;
$params[] = $accuracy_max;
$types .= 'dd';
$sql .= ' AND COALESCE(hes.hesitation_rate, 0) BETWEEN ? AND ?';
$params[] = $hesitation_rate_min;
$params[] = $hesitation_rate_max;
$types .= 'dd';
$sql .= ' AND COALESCE(acc.total_answers, 0) BETWEEN ? AND ?';
$params[] = $total_answers_min;
$params[] = $total_answers_max;
$types .= 'ii';
if ($feature_expression_sql !== '') {
    $sql .= " AND {$feature_expression_sql}";
    $params = array_merge($params, $feature_expression_params);
    $types .= $feature_expression_types;
}
$sql .= ' ORDER BY s.uid';

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    feature_filter_error_response('学習者の検索条件を準備できませんでした。');
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$matched_students = [];
while ($row = $result->fetch_assoc()) {
    $matched_students[(string)$row['uid']] = [
        'uid' => (string)$row['uid'],
        'Name' => (string)$row['Name'],
    ];
}
$result->free();
$stmt->close();

if (empty($matched_students)) {
    echo "<li class='student-list-status'>条件に該当する学習者はいません。</li>";
    $conn->close();
    exit;
}

$feature_averages = [];
if ($feature_table_exists) {
    $matched_uids = array_keys($matched_students);
    $average_selects = ['UID', 'COUNT(*) AS feature_record_count'];
    foreach ($available_feature_columns as $column => $_label) {
        $safe_column = str_replace('`', '``', $column);
        $average_selects[] = "AVG(`{$safe_column}`) AS avg_{$column}";
    }
    $uid_placeholders = implode(',', array_fill(0, count($matched_uids), '?'));
    $average_sql = 'SELECT ' . implode(', ', $average_selects)
        . " FROM test_featurevalue WHERE UID IN ({$uid_placeholders})";
    $average_params = $matched_uids;
    $average_types = str_repeat('s', count($matched_uids));
    if ($average_scope === 'selected' && $wid_selection_present) {
        $wid_placeholders = implode(',', array_fill(0, count($wids), '?'));
        $average_sql .= " AND WID IN ({$wid_placeholders})";
        $average_params = array_merge($average_params, $wids);
        $average_types .= str_repeat('s', count($wids));
    }
    $average_sql .= ' GROUP BY UID';
    $average_stmt = $conn->prepare($average_sql);
    if ($average_stmt) {
        $average_stmt->bind_param($average_types, ...$average_params);
        $average_stmt->execute();
        $average_result = $average_stmt->get_result();
        while ($average_row = $average_result->fetch_assoc()) {
            $feature_averages[(string)$average_row['UID']] = $average_row;
        }
        $average_result->free();
        $average_stmt->close();
    }
}

foreach ($matched_students as $uid_value => $student) {
    $row = array_merge(['feature_record_count' => 0], $feature_averages[$uid_value] ?? []);
    $uid = htmlspecialchars($uid_value, ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($student['Name'], ENT_QUOTES, 'UTF-8');
    $scope_title = $average_scope === 'all'
        ? '学習者ごとの全解答問題における特徴量平均'
        : '学習者ごとの選択した問題における特徴量平均';
    $student_tooltip = render_feature_average_tooltip($row, $scope_title, true);

    echo "<li class='student-item student-result-item' data-uid='{$uid}'>
            <label class='student-choice student-result-choice click-tooltip-choice'>
                <input type='checkbox' name='students[]' value='{$uid}'>
                <span class='student-result-identity'>
                    <strong>UID: {$uid}</strong>
                    <span>名前: {$name}</span>
                </span>
                <button type='button' class='student-info-button' aria-label='学習者ごとの特徴量平均を表示'>ⓘ</button>
                {$student_tooltip}
            </label>
          </li>";
}

$conn->close();
?>

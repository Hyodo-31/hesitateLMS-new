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
    echo "<li class='student-item'><p class='student-detail'>{$safe_message}</p></li>";
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
        throw new InvalidArgumentException('特徴量の論理式を読み取れませんでした。');
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
            if ($paren !== '(' && $paren !== ')') {
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
            continue;
        }

        if ($token['type'] === 'operator') {
            $parts[] = $token['operator'];
            continue;
        }

        $parts[] = $token['paren'];
    }

    return '(' . implode(' ', $parts) . ')';
}

$uids = $_POST['uid'] ?? [];
$wids = $_POST['wid'] ?? [];
$accuracy_min = $_POST['accuracy_min'] ?? 0;
$accuracy_max = $_POST['accuracy_max'] ?? 100;
$hesitation_rate_min = $_POST['hesitation_rate_min'] ?? 0;
$hesitation_rate_max = $_POST['hesitation_rate_max'] ?? 100;
$total_answers_min = $_POST['total_answers_min'] ?? 0;
$total_answers_max = $_POST['total_answers_max'] ?? 99999999;
$feature_select_sql = student_feature_average_select_sql($conn);
$feature_join_sql = student_feature_pair_average_join_sql($conn);
$available_feature_columns = student_feature_columns();
$feature_filters = [];
$feature_table_exists = student_feature_table_exists($conn);
$feature_expression_sql = '';
$feature_expression_types = '';
$feature_expression_params = [];
$posted_feature_filters = is_array($_POST['feature_filters'] ?? null) ? $_POST['feature_filters'] : [];
$posted_feature_expression = is_string($_POST['feature_filter_expression'] ?? null) ? $_POST['feature_filter_expression'] : '';

try {
    $feature_expression_tokens = normalize_feature_filter_expression($posted_feature_expression, $available_feature_columns);
    validate_feature_filter_expression($feature_expression_tokens);
} catch (InvalidArgumentException $error) {
    feature_filter_error_response($error->getMessage());
}

if (!empty($feature_expression_tokens)) {
    if ($feature_table_exists) {
        $feature_expression_sql = build_feature_filter_expression_sql(
            $feature_expression_tokens,
            $posted_feature_filters,
            $feature_expression_types,
            $feature_expression_params
        );
    }
} else {
    foreach (($_POST['feature_filter_rows'] ?? []) as $condition) {
        $column = $condition['column'] ?? '';
        if (!$feature_table_exists || !isset($available_feature_columns[$column])) {
            continue;
        }

        $min = trim((string)($condition['min'] ?? ''));
        $max = trim((string)($condition['max'] ?? ''));
        $min_value = $min !== '' && is_numeric($min) ? feature_storage_numeric_value($column, $min) : null;
        $max_value = $max !== '' && is_numeric($max) ? feature_storage_numeric_value($column, $max) : null;
        if ($min_value === null && $max_value === null) {
            continue;
        }

        $feature_filters[] = [
            'column' => $column,
            'min' => $min_value,
            'max' => $max_value,
        ];
    }
    foreach ($posted_feature_filters as $column => $condition) {
        if (!$feature_table_exists || !isset($available_feature_columns[$column]) || !is_array($condition) || empty($condition['enabled'])) {
            continue;
        }

        $min = trim((string)($condition['min'] ?? ''));
        $max = trim((string)($condition['max'] ?? ''));
        $min_value = $min !== '' && is_numeric($min) ? feature_storage_numeric_value($column, $min) : null;
        $max_value = $max !== '' && is_numeric($max) ? feature_storage_numeric_value($column, $max) : null;
        if ($min_value === null && $max_value === null) {
            continue;
        }

        $feature_filters[] = [
            'column' => $column,
            'min' => $min_value,
            'max' => $max_value,
        ];
    }
}

$sql = "SELECT
            s.uid,
            s.Name,
            feat.WID,
            feat.attempt,
            COALESCE(ld.test_id, feat.test_id) AS test_id,
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
        LEFT JOIN linedata ld ON s.uid = ld.UID AND feat.WID = ld.WID AND feat.attempt = ld.attempt
        WHERE ct.TID = ? AND feat.WID IS NOT NULL";

$params = [$_SESSION['MemberID']];
$types = 'i';

if (!empty($uids)) {
    $placeholders = implode(',', array_fill(0, count($uids), '?'));
    $sql .= " AND s.uid IN ($placeholders)";
    $params = array_merge($params, $uids);
    $types .= str_repeat('s', count($uids));
}

if (!empty($wids)) {
    $placeholders = implode(',', array_fill(0, count($wids), '?'));
    $sql .= " AND feat.WID IN ($placeholders)";
    $params = array_merge($params, $wids);
    $types .= str_repeat('i', count($wids));
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

if ($feature_expression_sql !== '') {
    $sql .= " AND {$feature_expression_sql}";
    $params = array_merge($params, $feature_expression_params);
    $types .= $feature_expression_types;
} elseif (!empty($feature_filters)) {
    $feature_conditions = [];
    foreach ($feature_filters as $feature_filter) {
        $column_sql = student_feature_avg_column_sql($feature_filter['column']);
        $single_conditions = ["{$column_sql} IS NOT NULL"];

        if ($feature_filter['min'] !== null) {
            $single_conditions[] = "{$column_sql} >= ?";
            $params[] = $feature_filter['min'];
            $types .= 'd';
        }

        if ($feature_filter['max'] !== null) {
            $single_conditions[] = "{$column_sql} <= ?";
            $params[] = $feature_filter['max'];
            $types .= 'd';
        }

        $feature_conditions[] = '(' . implode(' AND ', $single_conditions) . ')';
    }

    if (!empty($feature_conditions)) {
        $sql .= ' AND (' . implode(' AND ', $feature_conditions) . ')';
    }
}

$sql .= " ORDER BY s.uid, feat.WID, feat.attempt";

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
    $wid = htmlspecialchars($row['WID'], ENT_QUOTES, 'UTF-8');
    $attempt = htmlspecialchars($row['attempt'], ENT_QUOTES, 'UTF-8');
    $test_id = htmlspecialchars($row['test_id'] ?? '', ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
    $student_tooltip = render_feature_average_tooltip($row, 'UID/WID/Attemptの特徴量', false);
    $feature_values = [];
    foreach ($available_feature_columns as $column => $_label) {
        $feature_values[$column] = $row["avg_{$column}"] ?? null;
    }
    $feature_json = htmlspecialchars(json_encode($feature_values, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

    $trajectory_url = "../mousemove/mousemove.php?UID={$uid}&WID={$wid}&test_id={$test_id}&LogID={$attempt}";

    echo "<li class='student-item student-pair-item' data-uid='{$uid}' data-wid='{$wid}' data-attempt='{$attempt}' data-features='{$feature_json}'>
            <label class='student-choice click-tooltip-choice'>
                <input type='checkbox' name='students[]' value='{$uid}'>
                <p class='student-detail student-name'><span class='label'>UID:</span> {$uid}</p>
                <p class='student-detail'><span class='label'>WID:</span> {$wid}</p>
                <p class='student-detail'><span class='label'>Attempt:</span> {$attempt}</p>
                <p class='student-detail student-name-row'><span><span class='label'>{$name_label}:</span> {$name}</span><button type='button' class='student-info-button' aria-label='UID/WID/Attemptの特徴量を表示'>ⓘ</button></p>
                {$student_tooltip}
            </label>
            <a href='{$trajectory_url}' target='_blank' class='student-trajectory-link'>軌跡再現</a>
          </li>";
}

$stmt->close();
$conn->close();
?>

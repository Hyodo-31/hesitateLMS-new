<?php

function grouping_usage_payload(): array
{
    $raw = $_POST['payload'] ?? '';
    if (!is_string($raw) || $raw === '') {
        throw new InvalidArgumentException('ログ内容がありません。');
    }
    try {
        $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new InvalidArgumentException('ログ内容のJSONが不正です。', 0, $e);
    }
    if (!is_array($payload)) {
        throw new InvalidArgumentException('ログ内容が不正です。');
    }
    return $payload;
}

function grouping_usage_json(array $value): string
{
    try {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new InvalidArgumentException('ログ内容をJSONへ変換できません。', 0, $e);
    }
}

function grouping_usage_insert(mysqli $conn, string $sql, array $params): void
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('ログ保存の準備に失敗しました。');
    }
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        throw new RuntimeException('ログ保存に失敗しました: ' . $message);
    }
    $stmt->close();
}

function grouping_usage_enum(array $payload, string $key, array $allowed): string
{
    $value = is_scalar($payload[$key] ?? null) ? (string)$payload[$key] : '';
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException($key . ' が不正です。');
    }
    return $value;
}

function grouping_usage_normalize_ids($values, string $label, bool $allow_empty = false): array
{
    if (!is_array($values) || count($values) > 10000) {
        throw new InvalidArgumentException($label . ' が不正です。');
    }
    $normalized = [];
    foreach ($values as $value) {
        if (!is_scalar($value) || !preg_match('/^\d+$/', trim((string)$value))) {
            throw new InvalidArgumentException($label . ' が不正です。');
        }
        $number = (int)$value;
        $normalized[(string)$number] = true;
    }
    $ids = array_keys($normalized);
    sort($ids, SORT_NUMERIC);
    if (!$allow_empty && empty($ids)) {
        throw new InvalidArgumentException($label . ' が空です。');
    }
    return $ids;
}

function grouping_usage_same_ids(array $requested, array $allowed, string $label): array
{
    $requested = grouping_usage_normalize_ids($requested, $label);
    $allowed = grouping_usage_normalize_ids($allowed, $label, true);
    if ($requested !== $allowed) {
        throw new InvalidArgumentException($label . ' に担当外または存在しない値が含まれています。');
    }
    return array_map('intval', $requested);
}

function grouping_usage_validate_teacher(mysqli $conn, string $teacher_id): void
{
    if ($teacher_id === '') {
        throw new InvalidArgumentException('教師のログイン情報がありません。');
    }
    $stmt = $conn->prepare('SELECT 1 FROM teachers WHERE TID = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('教師情報の確認に失敗しました。');
    }
    $stmt->bind_param('s', $teacher_id);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$exists) {
        throw new InvalidArgumentException('教師のログイン情報が不正です。');
    }
}

function grouping_usage_teacher_students(mysqli $conn, string $teacher_id): array
{
    $stmt = $conn->prepare(
        'SELECT DISTINCT s.uid FROM students s JOIN classteacher ct ON s.ClassID = ct.ClassID WHERE ct.TID = ?'
    );
    if (!$stmt) {
        throw new RuntimeException('学習者(UID)の確認に失敗しました。');
    }
    $stmt->bind_param('s', $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $raw_uid = $row['uid'] ?? null;
        if (!is_scalar($raw_uid) || !preg_match('/^\d+$/', trim((string)$raw_uid))) {
            continue;
        }
        $students[(string)((int)$raw_uid)] = true;
    }
    $stmt->close();
    $student_ids = array_keys($students);
    sort($student_ids, SORT_NUMERIC);
    return $student_ids;
}

function grouping_usage_class_wids(mysqli $conn, string $teacher_id, array $requested_wids): array
{
    if (empty($requested_wids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($requested_wids), '?'));
    $params = array_merge([$teacher_id], array_map('intval', $requested_wids));
    $types = 's' . str_repeat('i', count($requested_wids));
    $stmt = $conn->prepare(
        "SELECT DISTINCT l.WID
         FROM linedata l
         JOIN students s ON l.UID = s.uid
         JOIN classteacher ct ON s.ClassID = ct.ClassID
         WHERE ct.TID = ? AND l.WID IN ($placeholders)"
    );
    if (!$stmt) {
        throw new RuntimeException('問題(WID)の確認に失敗しました。');
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed = [];
    while ($row = $result->fetch_assoc()) {
        if (teacher_analysis_wid_is_allowed($row['WID'])) {
            $allowed[] = (string)$row['WID'];
        }
    }
    $stmt->close();
    return grouping_usage_normalize_ids($allowed, '選択WID', true);
}

function grouping_usage_allowed_features(): array
{
    $allowed = array_fill_keys(array_keys(student_feature_columns()), true);
    $allowed['__accuracy'] = true;
    $allowed['__hesitation'] = true;
    return $allowed;
}

function grouping_usage_histogram(
    array $payload,
    string $selection_method,
    array $allowed_features,
    array $allowed_member_ids,
    array $selected_ids,
    bool $selected_may_be_subset
): array {
    if ($selection_method !== 'histogram') {
        return ['features' => null, 'conditions' => null, 'bin_width_changed' => null];
    }

    $raw_conditions = $payload['histogram_conditions'] ?? null;
    if (!is_array($raw_conditions) || empty($raw_conditions) || count($raw_conditions) > 1000) {
        throw new InvalidArgumentException('ヒストグラム条件が不正です。');
    }
    $allowed_members = array_fill_keys(array_map('strval', $allowed_member_ids), true);
    $selected_lookup = array_fill_keys(array_map('strval', $selected_ids), true);
    $union_members = [];
    $features = [];
    $conditions = [];
    $changed = false;

    foreach ($raw_conditions as $raw_condition) {
        if (!is_array($raw_condition)) {
            throw new InvalidArgumentException('ヒストグラム条件が不正です。');
        }
        $feature = is_scalar($raw_condition['feature'] ?? null) ? (string)$raw_condition['feature'] : '';
        if (!isset($allowed_features[$feature])) {
            throw new InvalidArgumentException('許可されていない特徴量です。');
        }
        $mode = is_scalar($raw_condition['bin_width_mode'] ?? null) ? (string)$raw_condition['bin_width_mode'] : '';
        if (!in_array($mode, ['auto', 'manual'], true)) {
            throw new InvalidArgumentException('階級幅の指定方法が不正です。');
        }
        foreach (['bin_start', 'bin_end', 'bin_width'] as $number_key) {
            if (!array_key_exists($number_key, $raw_condition) || !is_numeric($raw_condition[$number_key])) {
                throw new InvalidArgumentException('ヒストグラムの階級値が不正です。');
            }
        }
        $bin_start = (float)$raw_condition['bin_start'];
        $bin_end = (float)$raw_condition['bin_end'];
        $bin_width = (float)$raw_condition['bin_width'];
        if (!is_finite($bin_start) || !is_finite($bin_end) || !is_finite($bin_width)
            || $bin_end < $bin_start || $bin_width < 0 || ($mode === 'manual' && $bin_width <= 0)) {
            throw new InvalidArgumentException('ヒストグラムの階級値が不正です。');
        }
        $members = grouping_usage_normalize_ids(
            $raw_condition['selected_ids'] ?? null,
            'ヒストグラムの選択対象'
        );
        foreach ($members as $member) {
            if (!isset($allowed_members[$member])) {
                throw new InvalidArgumentException('ヒストグラム条件に担当外の対象が含まれています。');
            }
            $union_members[$member] = true;
        }
        $features[$feature] = true;
        $changed = $changed || $mode === 'manual';
        $conditions[] = [
            'feature' => $feature,
            'bin_start' => $bin_start,
            'bin_end' => $bin_end,
            'bin_width_mode' => $mode,
            'bin_width' => $bin_width,
            'selected_ids' => array_map('intval', $members),
        ];
    }

    if ($selected_may_be_subset) {
        foreach ($selected_lookup as $selected => $_) {
            if (!isset($union_members[$selected])) {
                throw new InvalidArgumentException('選択UIDとヒストグラム条件が一致しません。');
            }
        }
    } else {
        ksort($selected_lookup, SORT_NUMERIC);
        ksort($union_members, SORT_NUMERIC);
        if (array_keys($selected_lookup) !== array_keys($union_members)) {
            throw new InvalidArgumentException('選択対象とヒストグラム条件が一致しません。');
        }
    }

    return [
        'features' => array_keys($features),
        'conditions' => $conditions,
        'bin_width_changed' => $changed ? 1 : 0,
    ];
}

function grouping_usage_group_sources(mysqli $conn, string $teacher_id): array
{
    $stmt = $conn->prepare(
        "SELECT CONCAT('class:', ClassID) AS source_value FROM classteacher WHERE TID = ?
         UNION
         SELECT CONCAT('group:', group_id) AS source_value FROM `groups` WHERE TID = ?"
    );
    if (!$stmt) {
        throw new RuntimeException('グループ条件の確認に失敗しました。');
    }
    $stmt->bind_param('ss', $teacher_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sources = [];
    while ($row = $result->fetch_assoc()) {
        $sources[(string)$row['source_value']] = true;
    }
    $stmt->close();
    return $sources;
}

function grouping_usage_group_tokens(mysqli $conn, string $teacher_id, $raw_tokens): array
{
    if (!is_array($raw_tokens) || empty($raw_tokens) || count($raw_tokens) > 200) {
        throw new InvalidArgumentException('グループ条件式が不正です。');
    }
    $allowed_kinds = ['condition', 'and', 'or', 'not', 'open', 'close'];
    $allowed_sources = grouping_usage_group_sources($conn, $teacher_id);
    $tokens = [];
    foreach ($raw_tokens as $raw_token) {
        if (!is_array($raw_token)) {
            throw new InvalidArgumentException('グループ条件式が不正です。');
        }
        $kind = is_scalar($raw_token['kind'] ?? null) ? (string)$raw_token['kind'] : '';
        if (!in_array($kind, $allowed_kinds, true)) {
            throw new InvalidArgumentException('グループ条件式が不正です。');
        }
        $value = '';
        if ($kind === 'condition') {
            $value = is_scalar($raw_token['value'] ?? null) ? (string)$raw_token['value'] : '';
            if (!preg_match('/^(class|group):\d+$/', $value) || !isset($allowed_sources[$value])) {
                throw new InvalidArgumentException('グループ条件の対象が不正です。');
            }
        }
        $tokens[] = ['kind' => $kind, 'value' => $value];
    }
    return $tokens;
}

function grouping_usage_filters(array $payload): array
{
    $correctness = grouping_usage_enum($payload, 'correctness_filter', ['all', 'correct', 'incorrect']);
    $hesitation = grouping_usage_enum(
        $payload,
        'hesitation_filter',
        ['all', 'hesitated', 'not_hesitated', 'not_estimated']
    );
    return [
        'correctness' => $correctness,
        'correctness_used' => $correctness === 'all' ? 0 : 1,
        'hesitation' => $hesitation,
        'hesitation_used' => $hesitation === 'all' ? 0 : 1,
    ];
}

function grouping_usage_reflected_uids(
    $raw_reflected,
    array $teacher_students,
    array $selected_uids,
    array $filters
): array {
    $reflected = grouping_usage_normalize_ids($raw_reflected, '反映候補UID', true);
    $teacher_lookup = array_fill_keys($teacher_students, true);
    $selected_lookup = array_fill_keys(array_map('strval', $selected_uids), true);
    foreach ($reflected as $uid) {
        if (!isset($teacher_lookup[$uid]) || !isset($selected_lookup[$uid])) {
            throw new InvalidArgumentException('反映候補UIDに担当外または未選択の値が含まれています。');
        }
    }
    if (!$filters['correctness_used'] && !$filters['hesitation_used']) {
        $selected = array_keys($selected_lookup);
        sort($selected, SORT_NUMERIC);
        if ($reflected !== $selected) {
            throw new InvalidArgumentException('選択UIDと反映候補UIDが一致しません。');
        }
    }
    return array_map('intval', $reflected);
}

function grouping_usage_log_wid_select(mysqli $conn, string $teacher_id, array $payload): void
{
    grouping_usage_validate_teacher($conn, $teacher_id);
    $method = grouping_usage_enum($payload, 'selection_method', ['checkbox', 'histogram']);
    $requested_wids = grouping_usage_normalize_ids($payload['selected_wids'] ?? null, '選択WID');
    $wids = grouping_usage_same_ids(
        $requested_wids,
        grouping_usage_class_wids($conn, $teacher_id, $requested_wids),
        '選択WID'
    );
    $histogram = grouping_usage_histogram(
        $payload,
        $method,
        grouping_usage_allowed_features(),
        $wids,
        $wids,
        false
    );
    grouping_usage_insert(
        $conn,
        'INSERT INTO Grouping_WIDselect
         (teacher_id, selection_method, selected_wids, histogram_features, histogram_conditions, histogram_bin_width_changed)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
            $teacher_id,
            $method,
            grouping_usage_json($wids),
            $histogram['features'] === null ? null : grouping_usage_json($histogram['features']),
            $histogram['conditions'] === null ? null : grouping_usage_json($histogram['conditions']),
            $histogram['bin_width_changed'],
        ]
    );
}

function grouping_usage_log_uid_select(mysqli $conn, string $teacher_id, array $payload): void
{
    grouping_usage_validate_teacher($conn, $teacher_id);
    $method = grouping_usage_enum($payload, 'selection_method', ['checkbox', 'histogram']);
    $teacher_students = grouping_usage_teacher_students($conn, $teacher_id);
    $requested_uids = grouping_usage_normalize_ids($payload['selected_uids'] ?? null, '選択UID');
    $uids = grouping_usage_same_ids($requested_uids, array_values(array_intersect($teacher_students, $requested_uids)), '選択UID');
    $requested_wids = grouping_usage_normalize_ids($payload['selected_wids'] ?? null, '選択WID');
    $wids = grouping_usage_same_ids(
        $requested_wids,
        grouping_usage_class_wids($conn, $teacher_id, $requested_wids),
        '選択WID'
    );
    $filters = grouping_usage_filters($payload);
    $reflected_uids = grouping_usage_reflected_uids(
        $payload['reflected_candidate_uids'] ?? null,
        $teacher_students,
        $uids,
        $filters
    );
    $histogram = grouping_usage_histogram(
        $payload,
        $method,
        grouping_usage_allowed_features(),
        $teacher_students,
        $uids,
        true
    );

    $group_used = null;
    $group_expression = null;
    $group_tokens = null;
    if ($method === 'checkbox') {
        $group_used = !empty($payload['group_condition_used']) ? 1 : 0;
        if ($group_used) {
            $group_expression = is_scalar($payload['group_expression'] ?? null)
                ? trim((string)$payload['group_expression'])
                : '';
            if ($group_expression === '' || mb_strlen($group_expression) > 4000) {
                throw new InvalidArgumentException('グループ条件式が不正です。');
            }
            $group_tokens = grouping_usage_group_tokens(
                $conn,
                $teacher_id,
                $payload['group_expression_tokens'] ?? null
            );
        }
    }

    grouping_usage_insert(
        $conn,
        'INSERT INTO Grouping_UIDselect
         (teacher_id, selection_method, selected_uids, reflected_candidate_uids, selected_wids,
          group_condition_used, group_expression, group_expression_tokens, histogram_features,
          histogram_conditions, histogram_bin_width_changed, correctness_filter, correctness_filter_used,
          hesitation_filter, hesitation_filter_used)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $teacher_id,
            $method,
            grouping_usage_json($uids),
            grouping_usage_json($reflected_uids),
            grouping_usage_json($wids),
            $group_used,
            $group_expression,
            $group_tokens === null ? null : grouping_usage_json($group_tokens),
            $histogram['features'] === null ? null : grouping_usage_json($histogram['features']),
            $histogram['conditions'] === null ? null : grouping_usage_json($histogram['conditions']),
            $histogram['bin_width_changed'],
            $filters['correctness'],
            $filters['correctness_used'],
            $filters['hesitation'],
            $filters['hesitation_used'],
        ]
    );
}

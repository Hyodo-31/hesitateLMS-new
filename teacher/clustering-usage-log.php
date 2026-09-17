<?php

require_once __DIR__ . '/hesitation-estimation-state.php';

function clustering_usage_payload(): array
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

function clustering_usage_json(array $value): string
{
    try {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (JsonException $e) {
        throw new InvalidArgumentException('ログ内容をJSONへ変換できません。', 0, $e);
    }
}

function clustering_usage_insert(mysqli $conn, string $sql, array $params): void
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

function clustering_usage_enum(array $payload, string $key, array $allowed): string
{
    $value = is_scalar($payload[$key] ?? null) ? (string)$payload[$key] : '';
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException($key . ' が不正です。');
    }
    return $value;
}

function clustering_usage_normalize_ids($values, string $label, bool $allowEmpty = false): array
{
    if (!is_array($values) || count($values) > 10000) {
        throw new InvalidArgumentException($label . 'が不正です。');
    }
    $normalized = [];
    foreach ($values as $value) {
        if (!is_scalar($value) || !preg_match('/^\d+$/', trim((string)$value))) {
            throw new InvalidArgumentException($label . 'が不正です。');
        }
        $normalized[(string)((int)$value)] = true;
    }
    $ids = array_keys($normalized);
    sort($ids, SORT_NUMERIC);
    if (!$allowEmpty && empty($ids)) {
        throw new InvalidArgumentException($label . 'が空です。');
    }
    return $ids;
}

function clustering_usage_same_ids(array $requested, array $allowed, string $label): array
{
    $requested = clustering_usage_normalize_ids($requested, $label);
    $allowed = clustering_usage_normalize_ids($allowed, $label, true);
    if ($requested !== $allowed) {
        throw new InvalidArgumentException($label . 'に担当外または存在しない値が含まれています。');
    }
    return array_map('intval', $requested);
}

function clustering_usage_validate_teacher(mysqli $conn, string $teacherId): void
{
    if ($teacherId === '') {
        throw new InvalidArgumentException('教師のログイン情報がありません。');
    }
    $stmt = $conn->prepare('SELECT 1 FROM teachers WHERE TID = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('教師情報の確認に失敗しました。');
    }
    $stmt->bind_param('s', $teacherId);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$exists) {
        throw new InvalidArgumentException('教師のログイン情報が不正です。');
    }
}

function clustering_usage_teacher_students(mysqli $conn, string $teacherId): array
{
    $stmt = $conn->prepare(
        'SELECT DISTINCT s.uid
         FROM students s
         JOIN classteacher ct ON s.ClassID = ct.ClassID
         WHERE ct.TID = ?'
    );
    if (!$stmt) {
        throw new RuntimeException('学習者(UID)の確認に失敗しました。');
    }
    $stmt->bind_param('s', $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[(string)((int)$row['uid'])] = true;
    }
    $stmt->close();
    $ids = array_keys($students);
    sort($ids, SORT_NUMERIC);
    return $ids;
}

function clustering_usage_class_wids(mysqli $conn, string $teacherId, array $requestedWids): array
{
    if (empty($requestedWids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($requestedWids), '?'));
    $params = array_merge([$teacherId], array_map('intval', $requestedWids));
    $types = 's' . str_repeat('i', count($requestedWids));
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
    return clustering_usage_normalize_ids($allowed, '選択WID', true);
}

function clustering_usage_allowed_histogram_features(): array
{
    $allowed = array_fill_keys(array_keys(feature_display_histogram_feature_labels()), true);
    $allowed['__accuracy'] = true;
    $allowed['__hesitation'] = true;
    return $allowed;
}

function clustering_usage_histogram(
    array $payload,
    string $selectionMethod,
    array $allowedFeatures,
    array $allowedMemberIds,
    array $selectedIds,
    bool $selectedMayBeSubset
): array {
    if ($selectionMethod !== 'histogram') {
        return ['features' => null, 'conditions' => null, 'bin_width_changed' => null];
    }

    $rawConditions = $payload['histogram_conditions'] ?? null;
    if (!is_array($rawConditions) || empty($rawConditions) || count($rawConditions) > 1000) {
        throw new InvalidArgumentException('ヒストグラム条件が不正です。');
    }
    $allowedMembers = array_fill_keys(array_map('strval', $allowedMemberIds), true);
    $selectedLookup = array_fill_keys(array_map('strval', $selectedIds), true);
    $unionMembers = [];
    $features = [];
    $conditions = [];
    $changed = false;

    foreach ($rawConditions as $rawCondition) {
        if (!is_array($rawCondition)) {
            throw new InvalidArgumentException('ヒストグラム条件が不正です。');
        }
        $feature = is_scalar($rawCondition['feature'] ?? null) ? (string)$rawCondition['feature'] : '';
        if (!isset($allowedFeatures[$feature])) {
            throw new InvalidArgumentException('許可されていない特徴量です。');
        }
        $mode = is_scalar($rawCondition['bin_width_mode'] ?? null)
            ? (string)$rawCondition['bin_width_mode']
            : '';
        if (!in_array($mode, ['auto', 'manual'], true)) {
            throw new InvalidArgumentException('階級幅の指定方法が不正です。');
        }
        foreach (['bin_start', 'bin_end', 'bin_width'] as $numberKey) {
            if (!array_key_exists($numberKey, $rawCondition) || !is_numeric($rawCondition[$numberKey])) {
                throw new InvalidArgumentException('ヒストグラムの階級値が不正です。');
            }
        }
        $binStart = (float)$rawCondition['bin_start'];
        $binEnd = (float)$rawCondition['bin_end'];
        $binWidth = (float)$rawCondition['bin_width'];
        if (!is_finite($binStart) || !is_finite($binEnd) || !is_finite($binWidth)
            || $binEnd < $binStart || $binWidth < 0 || ($mode === 'manual' && $binWidth <= 0)) {
            throw new InvalidArgumentException('ヒストグラムの階級値が不正です。');
        }
        $members = clustering_usage_normalize_ids(
            $rawCondition['selected_ids'] ?? null,
            'ヒストグラムの選択対象'
        );
        foreach ($members as $member) {
            if (!isset($allowedMembers[$member])) {
                throw new InvalidArgumentException('ヒストグラム条件に担当外の対象が含まれています。');
            }
            $unionMembers[$member] = true;
        }
        $features[$feature] = true;
        $changed = $changed || $mode === 'manual';
        $conditions[] = [
            'feature' => $feature,
            'bin_start' => $binStart,
            'bin_end' => $binEnd,
            'bin_width_mode' => $mode,
            'bin_width' => $binWidth,
            'selected_ids' => array_map('intval', $members),
        ];
    }

    if ($selectedMayBeSubset) {
        foreach ($selectedLookup as $selected => $_) {
            if (!isset($unionMembers[$selected])) {
                throw new InvalidArgumentException('選択UIDとヒストグラム条件が一致しません。');
            }
        }
    } else {
        ksort($selectedLookup, SORT_NUMERIC);
        ksort($unionMembers, SORT_NUMERIC);
        if (array_keys($selectedLookup) !== array_keys($unionMembers)) {
            throw new InvalidArgumentException('選択対象とヒストグラム条件が一致しません。');
        }
    }

    return [
        'features' => array_keys($features),
        'conditions' => $conditions,
        'bin_width_changed' => $changed ? 1 : 0,
    ];
}

function clustering_usage_group_sources(mysqli $conn, string $teacherId): array
{
    $stmt = $conn->prepare(
        "SELECT CONCAT('class:', ClassID) AS source_value FROM classteacher WHERE TID = ?
         UNION
         SELECT CONCAT('group:', group_id) AS source_value FROM `groups` WHERE TID = ?"
    );
    if (!$stmt) {
        throw new RuntimeException('グループ条件の確認に失敗しました。');
    }
    $stmt->bind_param('ss', $teacherId, $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sources = [];
    while ($row = $result->fetch_assoc()) {
        $sources[(string)$row['source_value']] = true;
    }
    $stmt->close();
    return $sources;
}

function clustering_usage_group_tokens(mysqli $conn, string $teacherId, $rawTokens): array
{
    if (!is_array($rawTokens) || empty($rawTokens) || count($rawTokens) > 200) {
        throw new InvalidArgumentException('グループ条件式が不正です。');
    }
    $allowedKinds = ['condition', 'and', 'or', 'not', 'open', 'close'];
    $allowedSources = clustering_usage_group_sources($conn, $teacherId);
    $tokens = [];
    foreach ($rawTokens as $rawToken) {
        if (!is_array($rawToken)) {
            throw new InvalidArgumentException('グループ条件式が不正です。');
        }
        $kind = is_scalar($rawToken['kind'] ?? null) ? (string)$rawToken['kind'] : '';
        if (!in_array($kind, $allowedKinds, true)) {
            throw new InvalidArgumentException('グループ条件式が不正です。');
        }
        $value = '';
        if ($kind === 'condition') {
            $value = is_scalar($rawToken['value'] ?? null) ? (string)$rawToken['value'] : '';
            if (!preg_match('/^(class|group):\d+$/', $value) || !isset($allowedSources[$value])) {
                throw new InvalidArgumentException('グループ条件の対象が不正です。');
            }
        }
        $tokens[] = ['kind' => $kind, 'value' => $value];
    }
    return $tokens;
}

function clustering_usage_log_wid_select(mysqli $conn, string $teacherId, array $payload): void
{
    clustering_usage_validate_teacher($conn, $teacherId);
    $method = clustering_usage_enum($payload, 'selection_method', ['checkbox', 'histogram']);
    $requestedWids = clustering_usage_normalize_ids($payload['selected_wids'] ?? null, '選択WID');
    $wids = clustering_usage_same_ids(
        $requestedWids,
        clustering_usage_class_wids($conn, $teacherId, $requestedWids),
        '選択WID'
    );
    $histogram = clustering_usage_histogram(
        $payload,
        $method,
        clustering_usage_allowed_histogram_features(),
        $wids,
        $wids,
        false
    );
    $ml = teacher_hesitation_ml_flag($conn, $teacherId);
    clustering_usage_insert(
        $conn,
        'INSERT INTO clustering_WIDselect
         (teacher_id, selection_method, selected_wids, histogram_features,
          histogram_conditions, histogram_bin_width_changed, ML)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $teacherId,
            $method,
            clustering_usage_json($wids),
            $histogram['features'] === null ? null : clustering_usage_json($histogram['features']),
            $histogram['conditions'] === null ? null : clustering_usage_json($histogram['conditions']),
            $histogram['bin_width_changed'],
            $ml,
        ]
    );
}

function clustering_usage_log_uid_select(mysqli $conn, string $teacherId, array $payload): void
{
    clustering_usage_validate_teacher($conn, $teacherId);
    $method = clustering_usage_enum($payload, 'selection_method', ['checkbox', 'histogram']);
    $teacherStudents = clustering_usage_teacher_students($conn, $teacherId);
    $requestedUids = clustering_usage_normalize_ids($payload['selected_uids'] ?? null, '選択UID');
    $uids = clustering_usage_same_ids(
        $requestedUids,
        array_values(array_intersect($teacherStudents, $requestedUids)),
        '選択UID'
    );
    $requestedWids = clustering_usage_normalize_ids($payload['selected_wids'] ?? null, '選択WID');
    $wids = clustering_usage_same_ids(
        $requestedWids,
        clustering_usage_class_wids($conn, $teacherId, $requestedWids),
        '選択WID'
    );
    $histogram = clustering_usage_histogram(
        $payload,
        $method,
        clustering_usage_allowed_histogram_features(),
        $teacherStudents,
        $uids,
        true
    );

    $groupUsed = null;
    $groupExpression = null;
    $groupTokens = null;
    if ($method === 'checkbox') {
        $groupUsed = !empty($payload['group_condition_used']) ? 1 : 0;
        if ($groupUsed) {
            $groupExpression = is_scalar($payload['group_expression'] ?? null)
                ? trim((string)$payload['group_expression'])
                : '';
            if ($groupExpression === '' || mb_strlen($groupExpression) > 4000) {
                throw new InvalidArgumentException('グループ条件式が不正です。');
            }
            $groupTokens = clustering_usage_group_tokens(
                $conn,
                $teacherId,
                $payload['group_expression_tokens'] ?? null
            );
        }
    }

    $ml = teacher_hesitation_ml_flag($conn, $teacherId);
    clustering_usage_insert(
        $conn,
        'INSERT INTO clustering_UIDselect
         (teacher_id, selection_method, selected_uids, selected_wids, group_condition_used,
          group_expression, group_expression_tokens, histogram_features,
          histogram_conditions, histogram_bin_width_changed, ML)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $teacherId,
            $method,
            clustering_usage_json($uids),
            clustering_usage_json($wids),
            $groupUsed,
            $groupExpression,
            $groupTokens === null ? null : clustering_usage_json($groupTokens),
            $histogram['features'] === null ? null : clustering_usage_json($histogram['features']),
            $histogram['conditions'] === null ? null : clustering_usage_json($histogram['conditions']),
            $histogram['bin_width_changed'],
            $ml,
        ]
    );
}

function clustering_usage_available_features(mysqli $conn): array
{
    $excluded = array_fill_keys(['UID', 'WID', 'Understand', 'attempt', 'date', 'check'], true);
    $result = $conn->query('SHOW COLUMNS FROM test_featurevalue');
    if (!$result) {
        throw new RuntimeException('特徴量の確認に失敗しました。');
    }
    $collect = false;
    $features = [];
    while ($row = $result->fetch_assoc()) {
        $field = (string)($row['Field'] ?? '');
        $type = strtolower((string)($row['Type'] ?? ''));
        if ($field === 'Time') {
            $collect = true;
        }
        if ($collect && $field !== '' && !isset($excluded[$field])
            && preg_match('/\b(int|float|double|decimal|real)\b/', $type)) {
            $features[$field] = true;
        }
    }
    $result->free();
    return $features;
}

function clustering_usage_result_features(mysqli $conn, array $rawFeatures): array
{
    if (empty($rawFeatures) || count($rawFeatures) > 100) {
        throw new InvalidArgumentException('選択特徴量が不正です。');
    }
    $allowed = clustering_usage_available_features($conn);
    $features = [];
    foreach ($rawFeatures as $rawFeature) {
        if (!is_scalar($rawFeature)) {
            throw new InvalidArgumentException('選択特徴量が不正です。');
        }
        $feature = trim((string)$rawFeature);
        if (!isset($allowed[$feature])) {
            throw new InvalidArgumentException('許可されていない特徴量が含まれています。');
        }
        $features[$feature] = true;
    }
    return array_keys($features);
}

function clustering_usage_transition_count($rawCount): int
{
    if (!is_scalar($rawCount) || !preg_match('/^\d+$/', trim((string)$rawCount))) {
        throw new InvalidArgumentException('相関画面からの移動回数が不正です。');
    }
    $count = (int)$rawCount;
    if ($count < 0 || $count > 10000) {
        throw new InvalidArgumentException('相関画面からの移動回数が不正です。');
    }
    return $count;
}

function clustering_usage_transition_snapshot(
    $rawTotal,
    array $rawModeCounts,
    bool $modeCountsProvided
): array {
    $snapshot = [
        'total' => clustering_usage_transition_count($rawTotal),
        'understand' => 0,
        'hesitation_degree' => 0,
        'feature_pair' => 0,
    ];
    if (!$modeCountsProvided) {
        return $snapshot;
    }

    foreach (['understand', 'hesitation_degree', 'feature_pair'] as $mode) {
        $snapshot[$mode] = clustering_usage_transition_count($rawModeCounts[$mode] ?? null);
    }
    if ($snapshot['total'] !== $snapshot['understand']
        + $snapshot['hesitation_degree']
        + $snapshot['feature_pair']) {
        throw new InvalidArgumentException('相関画面からの総移動回数とモード別回数が一致しません。');
    }

    return $snapshot;
}

function clustering_usage_log_result(
    mysqli $conn,
    string $teacherId,
    array $rawFeatures,
    string $method,
    ?int $requestedClusterCount,
    int $actualClusterCount,
    array $rawTargetUids,
    array $rawTargetWids,
    int $studentCount,
    array $transitionSnapshot
): void {
    clustering_usage_validate_teacher($conn, $teacherId);
    $features = clustering_usage_result_features($conn, $rawFeatures);
    if (!in_array($method, ['kmeans', 'xmeans', 'gmeans'], true)) {
        throw new InvalidArgumentException('クラスタリング手法が不正です。');
    }
    if ($method === 'kmeans') {
        if ($requestedClusterCount === null || $requestedClusterCount < 2 || $requestedClusterCount > 10) {
            throw new InvalidArgumentException('指定クラスタ数が不正です。');
        }
    } elseif ($requestedClusterCount !== null) {
        throw new InvalidArgumentException('自動クラスタリングの指定クラスタ数が不正です。');
    }
    if ($actualClusterCount < 1 || $actualClusterCount > 1000) {
        throw new InvalidArgumentException('実クラスタ数が不正です。');
    }
    $teacherStudents = clustering_usage_teacher_students($conn, $teacherId);
    $requestedUids = clustering_usage_normalize_ids($rawTargetUids, '処理対象UID');
    $uids = clustering_usage_same_ids(
        $requestedUids,
        array_values(array_intersect($teacherStudents, $requestedUids)),
        '処理対象UID'
    );
    if ($studentCount !== count($uids)) {
        throw new InvalidArgumentException('処理対象学習者数がUID一覧と一致しません。');
    }
    $requestedWids = clustering_usage_normalize_ids($rawTargetWids, '処理対象WID');
    $wids = clustering_usage_same_ids(
        $requestedWids,
        clustering_usage_class_wids($conn, $teacherId, $requestedWids),
        '処理対象WID'
    );
    foreach (['total', 'understand', 'hesitation_degree', 'feature_pair'] as $transitionKey) {
        if (!isset($transitionSnapshot[$transitionKey])
            || !is_int($transitionSnapshot[$transitionKey])
            || $transitionSnapshot[$transitionKey] < 0
            || $transitionSnapshot[$transitionKey] > 10000) {
            throw new InvalidArgumentException('相関画面からの移動回数が不正です。');
        }
    }
    $transitionCount = $transitionSnapshot['total'];
    $fromCorrelation = $transitionCount > 0 ? 1 : 0;
    $ml = teacher_hesitation_ml_flag($conn, $teacherId);

    clustering_usage_insert(
        $conn,
        'INSERT INTO clustering_result
         (teacher_id, selected_features, clustering_method, requested_cluster_count,
          actual_cluster_count, target_uids, target_wids, student_count,
          from_feature_correlation, feature_correlation_transition_count,
          feature_correlation_understand_transition_count,
          feature_correlation_hesitation_degree_transition_count,
          feature_correlation_feature_pair_transition_count, ML)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $teacherId,
            clustering_usage_json($features),
            $method,
            $requestedClusterCount,
            $actualClusterCount,
            clustering_usage_json($uids),
            clustering_usage_json($wids),
            $studentCount,
            $fromCorrelation,
            $transitionCount,
            $transitionSnapshot['understand'],
            $transitionSnapshot['hesitation_degree'],
            $transitionSnapshot['feature_pair'],
            $ml,
        ]
    );
}

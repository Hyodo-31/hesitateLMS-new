<?php

require_once __DIR__ . '/hesitation-estimation-state.php';

function machine_learning_usage_json(array $value): string
{
    try {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (JsonException $e) {
        throw new InvalidArgumentException('機械学習ログをJSONへ変換できません。', 0, $e);
    }
}

function machine_learning_usage_normalize_ids($values, string $label, bool $allowEmpty = false): array
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

function machine_learning_usage_validate_teacher(mysqli $conn, string $teacherId): void
{
    if ($teacherId === '') {
        throw new InvalidArgumentException('教師のログイン情報がありません。');
    }

    $stmt = $conn->prepare('SELECT 1 FROM teachers WHERE TID = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('教師情報の確認準備に失敗しました。');
    }
    $stmt->bind_param('s', $teacherId);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    if (!$exists) {
        throw new InvalidArgumentException('教師のログイン情報が不正です。');
    }
}

function machine_learning_usage_allowed_features(mysqli $conn): array
{
    $formFeatures = [
        'time', 'distance', 'averageSpeed', 'maxSpeed', 'totalStopTime', 'maxStopTime',
        'stopcount', 'FromlastdropToanswerTime', 'xUTurnCount', 'yUTurnCount',
        'xUTurnCountDD', 'yUTurnCountDD', 'thinkingTime', 'answeringTime',
        'maxDDTime', 'minDDTime', 'DDCount', 'maxDDIntervalTime', 'totalDDIntervalTime',
        'groupingDDCount', 'groupingCountbool',
        'register_move_count1', 'register_move_count2', 'register_move_count3', 'register_move_count4',
        'register01count1', 'register01count2', 'register01count3', 'register01count4',
        'registerDDCount',
    ];

    $result = $conn->query(
        "SELECT LOWER(COLUMN_NAME) AS column_name
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME IN ('featurevalue', 'test_featurevalue')
         GROUP BY LOWER(COLUMN_NAME)
         HAVING COUNT(DISTINCT TABLE_NAME) = 2"
    );
    if (!$result) {
        throw new RuntimeException('特徴量定義の確認に失敗しました。');
    }

    $commonColumns = [];
    while ($row = $result->fetch_assoc()) {
        $commonColumns[(string)$row['column_name']] = true;
    }
    $result->free();

    $allowed = [];
    foreach ($formFeatures as $feature) {
        if (isset($commonColumns[strtolower($feature)])) {
            $allowed[$feature] = true;
        }
    }
    return $allowed;
}

function machine_learning_usage_features(mysqli $conn, $rawFeatures): array
{
    if (!is_array($rawFeatures) || empty($rawFeatures) || count($rawFeatures) > 100) {
        throw new InvalidArgumentException('選択特徴量が不正です。');
    }

    $allowed = machine_learning_usage_allowed_features($conn);
    $features = [];
    foreach ($rawFeatures as $rawFeature) {
        if (!is_scalar($rawFeature)) {
            throw new InvalidArgumentException('選択特徴量が不正です。');
        }
        $feature = trim((string)$rawFeature);
        if (!isset($allowed[$feature])) {
            throw new InvalidArgumentException('許可されていない特徴量が含まれています。');
        }
        if (!isset($features[$feature])) {
            $features[$feature] = true;
        }
    }

    return array_keys($features);
}

function machine_learning_usage_allowed_classification_uids(mysqli $conn, string $teacherId): array
{
    $stmt = $conn->prepare(
        "SELECT DISTINCT s.uid
         FROM students s
         WHERE EXISTS (
               SELECT 1 FROM test_featurevalue tfv WHERE tfv.UID = s.uid
           )
           AND (
               EXISTS (
                   SELECT 1 FROM classteacher ct
                   WHERE ct.ClassID = s.ClassID AND ct.TID = ?
               )
               OR EXISTS (
                   SELECT 1
                   FROM `groups` g
                   JOIN group_members gm ON gm.group_id = g.group_id
                   WHERE g.TID = ? AND gm.uid = s.uid
               )
           )"
    );
    if (!$stmt) {
        throw new RuntimeException('分類対象学習者の確認準備に失敗しました。');
    }
    $stmt->bind_param('ss', $teacherId, $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    $uids = [];
    while ($row = $result->fetch_assoc()) {
        $uids[(string)((int)$row['uid'])] = true;
    }
    $stmt->close();

    $ids = array_keys($uids);
    sort($ids, SORT_NUMERIC);
    return $ids;
}

function machine_learning_usage_validate_classification_uids(
    mysqli $conn,
    string $teacherId,
    array $selectedUids
): array {
    $selected = machine_learning_usage_normalize_ids($selectedUids, '分類対象UID');
    $allowed = array_fill_keys(
        machine_learning_usage_allowed_classification_uids($conn, $teacherId),
        true
    );
    foreach ($selected as $uid) {
        if (!isset($allowed[$uid])) {
            throw new InvalidArgumentException('分類対象に担当外または存在しないUIDが含まれています。');
        }
    }
    return array_map('intval', $selected);
}

function machine_learning_usage_training_data(
    mysqli $conn,
    string $teacherId,
    array $post
): array {
    $rawSource = is_scalar($post['useData'] ?? null) ? (string)$post['useData'] : '';
    if ($rawSource === '' || $rawSource === 'alalldata') {
        return [
            'source' => $rawSource === 'alalldata' ? 'a_university_2019' : 'default_all',
            'group_id' => null,
            'group_name' => null,
            'uids' => null,
        ];
    }
    if ($rawSource !== 'groupdata') {
        throw new InvalidArgumentException('学習データの指定が不正です。');
    }

    $rawGroupId = $post['selectedGroup'] ?? null;
    if (!is_scalar($rawGroupId) || !preg_match('/^\d+$/', trim((string)$rawGroupId))) {
        throw new InvalidArgumentException('学習グループが不正です。');
    }
    $groupId = (int)$rawGroupId;

    $stmt = $conn->prepare('SELECT group_name FROM `groups` WHERE group_id = ? AND TID = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('学習グループの確認準備に失敗しました。');
    }
    $stmt->bind_param('is', $groupId, $teacherId);
    $stmt->execute();
    $groupRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$groupRow) {
        throw new InvalidArgumentException('学習グループが存在しないか、ログイン教師のグループではありません。');
    }

    $stmt = $conn->prepare(
        'SELECT DISTINCT gm.uid
         FROM group_members gm
         JOIN featurevalue fv ON fv.UID = gm.uid
         WHERE gm.group_id = ?
         ORDER BY gm.uid'
    );
    if (!$stmt) {
        throw new RuntimeException('学習グループのUID確認準備に失敗しました。');
    }
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $uids = [];
    while ($row = $result->fetch_assoc()) {
        $uids[] = (int)$row['uid'];
    }
    $stmt->close();

    return [
        'source' => 'created_group',
        'group_id' => $groupId,
        'group_name' => (string)$groupRow['group_name'],
        'uids' => $uids,
    ];
}

function machine_learning_usage_classification_groups(
    mysqli $conn,
    string $teacherId,
    $rawGroupIds
): array {
    $groupIds = machine_learning_usage_normalize_ids(
        is_array($rawGroupIds) ? $rawGroupIds : [],
        '分類対象グループ',
        true
    );
    if (empty($groupIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $params = array_merge([$teacherId], array_map('intval', $groupIds));
    $types = 's' . str_repeat('i', count($groupIds));
    $stmt = $conn->prepare(
        "SELECT group_id, group_name
         FROM `groups`
         WHERE TID = ? AND group_id IN ($placeholders)
         ORDER BY group_id"
    );
    if (!$stmt) {
        throw new RuntimeException('分類対象グループの確認準備に失敗しました。');
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];
    $foundIds = [];
    while ($row = $result->fetch_assoc()) {
        $groupId = (int)$row['group_id'];
        $foundIds[] = (string)$groupId;
        $groups[] = [
            'group_id' => $groupId,
            'group_name' => (string)$row['group_name'],
        ];
    }
    $stmt->close();

    sort($foundIds, SORT_NUMERIC);
    if ($foundIds !== $groupIds) {
        throw new InvalidArgumentException('分類対象に他教師または存在しないグループが含まれています。');
    }
    return $groups;
}

function machine_learning_usage_preset(array $post, array $features): array
{
    $preset = is_scalar($post['classifierPreset'] ?? null)
        ? trim((string)$post['classifierPreset'])
        : '';
    $modifiedRaw = is_scalar($post['classifierPresetModified'] ?? null)
        ? (string)$post['classifierPresetModified']
        : '0';
    if (!in_array($modifiedRaw, ['0', '1'], true)) {
        throw new InvalidArgumentException('分類器セットの変更状態が不正です。');
    }
    $modified = (int)$modifiedRaw;

    if ($preset === '') {
        if ($modified !== 0) {
            throw new InvalidArgumentException('分類器セットの変更状態が不正です。');
        }
        return ['used' => 0, 'preset' => null, 'modified' => 0];
    }
    if (!in_array($preset, ['A', 'B', 'C'], true)) {
        throw new InvalidArgumentException('分類器セットが不正です。');
    }

    if ($modified === 0) {
        $presetA = [
            'time', 'distance', 'averageSpeed', 'maxSpeed', 'thinkingTime', 'answeringTime',
            'maxStopTime', 'xUTurnCount', 'yUTurnCount', 'DDCount', 'maxDDTime',
            'maxDDIntervalTime', 'totalDDIntervalTime',
        ];
        $expected = $presetA;
        if ($preset === 'B') {
            $expected = array_merge($expected, ['groupingDDCount', 'groupingCountbool']);
        } elseif ($preset === 'C') {
            $expected = array_merge(
                $expected,
                ['register_move_count1', 'register01count1', 'register_move_count2', 'register01count2']
            );
        }
        $actualSorted = $features;
        $expectedSorted = $expected;
        sort($actualSorted, SORT_STRING);
        sort($expectedSorted, SORT_STRING);
        if ($actualSorted !== $expectedSorted) {
            throw new InvalidArgumentException('分類器セットと選択特徴量が一致しません。');
        }
    }

    return ['used' => 1, 'preset' => $preset, 'modified' => $modified];
}

function machine_learning_usage_log_execution(
    mysqli $conn,
    string $teacherId,
    array $post,
    array $selectedClassificationUids,
    array $selectedClassificationGroupIds
): void {
    machine_learning_usage_validate_teacher($conn, $teacherId);
    $training = machine_learning_usage_training_data($conn, $teacherId, $post);
    $classificationUids = machine_learning_usage_validate_classification_uids(
        $conn,
        $teacherId,
        $selectedClassificationUids
    );
    $classificationGroups = machine_learning_usage_classification_groups(
        $conn,
        $teacherId,
        $selectedClassificationGroupIds
    );
    $features = machine_learning_usage_features($conn, $post['featureLabel'] ?? null);
    $preset = machine_learning_usage_preset($post, $features);

    $trainingGroupId = $training['group_id'];
    $trainingGroupName = $training['group_name'];
    $trainingUidsJson = $training['uids'] === null
        ? null
        : machine_learning_usage_json($training['uids']);
    $classificationUidsJson = machine_learning_usage_json($classificationUids);
    $classificationGroupsJson = machine_learning_usage_json($classificationGroups);
    $featuresJson = machine_learning_usage_json($features);
    $presetName = $preset['preset'];
    $ml = teacher_hesitation_ml_flag($conn, $teacherId);

    $stmt = $conn->prepare(
        'INSERT INTO hesitate_estimate_pre (
             teacher_id, training_data_source, training_group_id, training_group_name,
             training_uids, classification_uids, classification_groups, selected_features,
             classifier_preset_used, classifier_preset, classifier_preset_modified, ML
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('機械学習ログ保存の準備に失敗しました。');
    }
    $stmt->bind_param(
        'ssisssssisii',
        $teacherId,
        $training['source'],
        $trainingGroupId,
        $trainingGroupName,
        $trainingUidsJson,
        $classificationUidsJson,
        $classificationGroupsJson,
        $featuresJson,
        $preset['used'],
        $presetName,
        $preset['modified'],
        $ml
    );
    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        throw new RuntimeException('機械学習ログの保存に失敗しました: ' . $message);
    }
    $stmt->close();
}

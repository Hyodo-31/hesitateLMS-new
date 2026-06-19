<?php
include '../lang.php';
require '../dbc.php';

if (empty($_SESSION['MemberID']) && empty($_SESSION['TID'])) {
    http_response_code(401);
    echo 'ログイン情報が見つかりません。';
    exit;
}

$teacherId = $_SESSION['TID'] ?? $_SESSION['MemberID'];

$fallbackFeatureColumns = [
    'Time', 'distance', 'averageSpeed', 'maxSpeed', 'thinkingTime', 'answeringTime',
    'totalStopTime', 'maxStopTime', 'stopcount', 'totalDDIntervalTime',
    'maxDDIntervalTime', 'maxDDTime', 'minDDTime', 'DDCount', 'groupingDDCount',
    'groupingCountbool', 'xUTurnCount', 'yUTurnCount', 'xUTurnCountDD',
    'yUTurnCountDD', 'register_move_count1', 'register_move_count2',
    'register_move_count3', 'register_move_count4', 'register01count1',
    'register01count2', 'register01count3', 'register01count4', 'registerDDCount',
    'register_notDDCount', 'register_fix_count1', 'register_fix_count2',
    'register_fix_count3', 'register_fix_count4', 'register_delete_count1',
    'register_delete_count2', 'register_delete_count3', 'register_delete_count4',
    'register_allDelete_count1', 'register_allDelete_count2', 'register_allDelete_count3',
    'register_allDelete_count4', 'register_notallDelete_count1',
    'register_notallDelete_count2', 'register_notallDelete_count3',
    'register_notallDelete_count4', 'FromlastdropToanswerTime',
];

$featureLabels = [
    'Time' => '解答時間',
    'distance' => '距離',
    'averageSpeed' => '平均速度',
    'maxSpeed' => '最大速度',
    'thinkingTime' => '第一ドラッグ前時間',
    'answeringTime' => '第一ドラッグ後時間',
    'totalStopTime' => '合計静止時間',
    'maxStopTime' => '最大静止時間',
    'stopcount' => '静止回数',
    'totalDDIntervalTime' => '合計D&D間時間',
    'maxDDIntervalTime' => '最大D&D間時間',
    'maxDDTime' => '合計D&D時間',
    'minDDTime' => '最小D&D時間',
    'DDCount' => '合計D&D回数',
    'groupingDDCount' => 'グループ化回数',
    'groupingCountbool' => 'グループ化有無',
    'xUTurnCount' => 'x軸Uターン回数',
    'yUTurnCount' => 'y軸Uターン回数',
    'xUTurnCountDD' => 'x軸UターンD&D回数',
    'yUTurnCountDD' => 'y軸UターンD&D回数',
    'register_move_count1' => 'レジスタからレジスタへの移動回数',
    'register_move_count2' => 'レジスタからレジスタ外への移動回数',
    'register_move_count3' => 'レジスタ外からレジスタへの移動回数',
    'register_move_count4' => 'レジスタ外からレジスタ外への移動回数',
    'register01count1' => 'レジスタからレジスタへの移動有無',
    'register01count2' => 'レジスタからレジスタ外への移動有無',
    'register01count3' => 'レジスタ外からレジスタへの移動有無',
    'register01count4' => 'レジスタ外からレジスタ外への移動有無',
    'registerDDCount' => 'レジスタに関する移動回数',
    'register_notDDCount' => 'レジスタ外の移動回数',
    'register_fix_count1' => 'レジスタ修正回数1',
    'register_fix_count2' => 'レジスタ修正回数2',
    'register_fix_count3' => 'レジスタ修正回数3',
    'register_fix_count4' => 'レジスタ修正回数4',
    'register_delete_count1' => 'レジスタ削除回数1',
    'register_delete_count2' => 'レジスタ削除回数2',
    'register_delete_count3' => 'レジスタ削除回数3',
    'register_delete_count4' => 'レジスタ削除回数4',
    'register_allDelete_count1' => 'レジスタ全削除回数1',
    'register_allDelete_count2' => 'レジスタ全削除回数2',
    'register_allDelete_count3' => 'レジスタ全削除回数3',
    'register_allDelete_count4' => 'レジスタ全削除回数4',
    'register_notallDelete_count1' => 'レジスタ部分削除回数1',
    'register_notallDelete_count2' => 'レジスタ部分削除回数2',
    'register_notallDelete_count3' => 'レジスタ部分削除回数3',
    'register_notallDelete_count4' => 'レジスタ部分削除回数4',
    'FromlastdropToanswerTime' => '最終ドロップ後時間',
];

function getClusteringFeatureColumns(mysqli $conn, array $fallbackFeatureColumns): array
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

    return !empty($featureColumns) ? $featureColumns : $fallbackFeatureColumns;
}

$featureColumns = getClusteringFeatureColumns($conn, $fallbackFeatureColumns);
$studentsByClass = [];
$studentsByClassId = [];
$teacherClasses = [];
$teacherGroups = [];
$studentsByGroup = [];

$stmtClasses = $conn->prepare(
    'SELECT c.ClassID, c.ClassName
     FROM classteacher ct
     JOIN classes c ON ct.ClassID = c.ClassID
     WHERE ct.TID = ?
     ORDER BY c.ClassName'
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
            $studentsByClass[$row['ClassName']][] = $row;
            $classKey = (string)$row['ClassID'];
            if (!isset($studentsByClassId[$classKey])) {
                $studentsByClassId[$classKey] = [];
            }
            $studentsByClassId[$classKey][] = (string)$row['uid'];
        }
        $stmtStudents->close();
    }
}

$stmtGroups = $conn->prepare(
    'SELECT group_id, group_name
     FROM `groups`
     WHERE TID = ?
     ORDER BY created_at DESC, group_id DESC'
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
    $stmtGroupStudents = $conn->prepare("SELECT group_id, uid FROM group_members WHERE group_id IN ({$groupPlaceholders})");
    if ($stmtGroupStudents) {
        $stmtGroupStudents->bind_param($groupTypes, ...$groupIds);
        $stmtGroupStudents->execute();
        $resultGroupStudents = $stmtGroupStudents->get_result();
        while ($row = $resultGroupStudents->fetch_assoc()) {
            $studentsByGroup[(string)$row['group_id']][] = (string)$row['uid'];
        }
        $stmtGroupStudents->close();
    }
}

$defaultSelectedFeatures = array_flip(['Time', 'distance']);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'ja', ENT_QUOTES, 'UTF-8') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>クラスタリング</title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .clustering-layout {
            display: grid;
            grid-template-columns: minmax(280px, 1fr) minmax(320px, 1.3fr);
            gap: 20px;
            align-items: start;
        }

        .clustering-panel {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 18px;
            background: #fff;
        }

        .clustering-panel h3 {
            margin: 0 0 14px;
            font-size: 1.1rem;
            color: #2c3e50;
        }

        .student-selector,
        .feature-selector {
            max-height: 390px;
            overflow-y: auto;
        }

        .feature-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 8px 14px;
        }

        .feature-selector label,
        .student-selector label {
            display: block;
            padding: 5px 6px;
            border-radius: 4px;
            cursor: pointer;
        }

        .feature-selector label:hover,
        .student-selector label:hover {
            background: #f4f7f6;
        }

        .class-heading {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin: 12px 0 6px;
            padding: 8px 10px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }

        .class-heading:first-child {
            margin-top: 0;
        }

        .class-heading h4 {
            margin: 0;
            font-size: 1rem;
        }

        .class-heading label {
            font-size: .9rem;
            white-space: nowrap;
        }

        .clustering-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 16px;
            margin-top: 18px;
        }

        .control-field {
            display: grid;
            gap: 6px;
        }

        .control-field input,
        .control-field select {
            min-width: 150px;
        }

        .secondary-button {
            background: #eef2f7;
            color: #2c3e50;
            border: 1px solid #d5dce6;
            border-radius: 4px;
            padding: 9px 12px;
            cursor: pointer;
        }

        .secondary-button:hover {
            background: #e3e9f2;
        }

        .clustering-status {
            min-height: 24px;
            margin-top: 12px;
            color: #555;
        }

        .clustering-status.error {
            color: #e74c3c;
            text-align: left;
            padding: 0;
        }

        .logic-filter-panel {
            margin: 14px 0;
            padding: 12px;
            border: 1px solid #d8dee4;
            border-radius: 8px;
            background: #f8fafc;
        }
        .logic-filter-panel h4 { margin: 0 0 10px; color: #243447; }
        .logic-filter-parts,
        .logic-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }
        .logic-filter-parts button,
        .logic-filter-actions button {
            min-height: 34px;
            padding: 0 12px;
            border: 1px solid #b7c3d0;
            border-radius: 6px;
            background: #fff;
            color: #243447;
            font-weight: 700;
            cursor: pointer;
        }
        .logic-filter-insert-control {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #475569;
            font-size: .9rem;
            font-weight: 700;
        }
        .logic-filter-builder {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-height: 48px;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #d8dee4;
            border-radius: 8px;
            background: #fff;
        }
        .logic-filter-token {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            min-height: 34px;
            padding: 4px 6px 4px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            font-weight: 700;
        }
        .logic-filter-token select {
            min-height: 28px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
        }
        .logic-filter-kind { min-width: 76px; }
        .logic-filter-token.operator { background: #ecfeff; border-color: #99f6e4; color: #0f766e; }
        .logic-filter-token.not { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
        .logic-filter-token.paren { background: #f1f5f9; }
        .logic-filter-remove {
            width: 26px;
            height: 26px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            font-weight: 800;
            line-height: 1;
        }
        .logic-filter-summary { margin: 0; color: #475569; font-weight: 700; }
        .logic-filter-summary.is-error { color: #b91c1c; }

        .cluster-chart-wrap {
            min-height: 360px;
            margin-top: 14px;
        }

        #cluster-chart {
            width: 100%;
            max-height: 440px;
        }

        .cluster-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .cluster-group {
            border: 1px solid #dfe6ee;
            border-radius: 6px;
            padding: 12px;
            background: #fbfcfd;
        }

        .cluster-group h4 {
            margin: 0 0 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
        }

        .cluster-name-field {
            display: grid;
            gap: 6px;
            margin-bottom: 10px;
            color: #475569;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .cluster-name-input {
            width: 100%;
            min-height: 38px;
            box-sizing: border-box;
            padding: 7px 9px;
            border: 1px solid #b7c3d0;
            border-radius: 6px;
            background: #fff;
            color: #243447;
            font: inherit;
        }

        .cluster-name-input:focus {
            border-color: #2563eb;
            outline: 2px solid rgba(37, 99, 235, 0.18);
        }

        .cluster-group ul {
            margin: 0;
            padding-left: 18px;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 900px) {
            .clustering-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php
    $teacher_page_title = 'クラスタリング';
    include __DIR__ . '/teacher-menu.php';
    ?>

    <div class="main-content">
        <main class="page-content">
            <section class="card">
                <h2>クラスタリング</h2>

                <div class="clustering-layout">
                    <section class="clustering-panel">
                        <h3>対象学習者</h3>
                        <?php if (empty($studentsByClass)): ?>
                            <p>担当グループ(クラス)に学習者が登録されていません。</p>
                        <?php else: ?>
                            <div class="controls">
                                <button type="button" class="secondary-button" id="select-all-students">全選択</button>
                                <button type="button" class="secondary-button" id="clear-all-students">全解除</button>
                            </div>
                            <div class="logic-filter-panel" id="student-logic-filter-panel">
                                <h4>論理式で学習者を絞り込み</h4>
                                <div class="logic-filter-parts">
                                    <button type="button" data-add-filter="condition">対象を追加</button>
                                    <button type="button" data-add-filter="and">AND</button>
                                    <button type="button" data-add-filter="or">OR</button>
                                    <button type="button" data-add-filter="not">NOT</button>
                                    <button type="button" data-add-filter="open">(</button>
                                    <button type="button" data-add-filter="close">)</button>
                                    <span class="logic-filter-insert-control">
                                        <label>追加位置</label>
                                        <select class="logic-filter-insert-position"></select>
                                    </span>
                                </div>
                                <div class="logic-filter-builder" id="student-logic-filter-builder"></div>
                                <div class="logic-filter-actions">
                                    <button type="button" id="apply-student-logic-filter">絞り込みを適用</button>
                                    <button type="button" id="reset-student-logic-filter">リセット</button>
                                    <button type="button" class="logic-filter-trim">追加位置から後ろを削除</button>
                                    <button type="button" class="logic-filter-clear">式を空にする</button>
                                    <p class="logic-filter-summary" id="student-logic-filter-summary">すべての学習者を対象にしています。</p>
                                </div>
                            </div>
                            <div class="student-selector checkbox-section">
                                <?php foreach ($studentsByClass as $className => $students): ?>
                                    <?php $classId = $students[0]['ClassID']; ?>
                                    <div class="class-heading">
                                        <h4><?= htmlspecialchars($className, ENT_QUOTES, 'UTF-8') ?></h4>
                                        <label>
                                            <input type="checkbox" class="select-class-students" data-class-id="<?= htmlspecialchars($classId, ENT_QUOTES, 'UTF-8') ?>" checked>
                                            このグループ(クラス)を選択
                                        </label>
                                    </div>
                                    <?php foreach ($students as $student): ?>
                                        <label>
                                            <input type="checkbox" class="student-checkbox" data-class-id="<?= htmlspecialchars($student['ClassID'], ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($student['uid'], ENT_QUOTES, 'UTF-8') ?>" checked>
                                            <?= htmlspecialchars($student['Name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($student['uid'], ENT_QUOTES, 'UTF-8') ?>)
                                        </label>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="clustering-panel">
                        <h3>特徴量</h3>
                        <?php if (empty($featureColumns)): ?>
                            <p>利用できる特徴量がありません。</p>
                        <?php else: ?>
                            <div class="controls">
                                <button type="button" class="secondary-button" id="select-all-features">全選択</button>
                                <button type="button" class="secondary-button" id="clear-all-features">全解除</button>
                            </div>
                            <div class="feature-selector checkbox-section">
                                <?php foreach ($featureColumns as $feature): ?>
                                    <label title="<?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="checkbox" class="feature-checkbox" value="<?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?>" <?= isset($defaultSelectedFeatures[$feature]) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($featureLabels[$feature] ?? $feature, ENT_QUOTES, 'UTF-8') ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <section class="clustering-panel" style="margin-top: 20px;">
                    <h3>条件</h3>
                    <div class="clustering-controls">
                        <label class="control-field" id="cluster-count-field">
                            <span>クラスタ数</span>
                            <input type="number" id="cluster-count" min="2" max="10" value="2">
                        </label>
                        <label class="control-field">
                            <span>手法</span>
                            <select id="cluster-method">
                                <option value="kmeans">K-Means</option>
                                <option value="xmeans">X-Means</option>
                                <option value="gmeans">G-Means</option>
                            </select>
                        </label>
                        <p class="clustering-status" id="cluster-method-note" style="margin: 0;"></p>
                        <button type="button" class="action-button" id="run-clustering">クラスタリング実行</button>
                    </div>
                    <div class="clustering-status" id="clustering-status"></div>
                </section>

                <section class="clustering-panel hidden" id="clustering-results" style="margin-top: 20px;">
                    <h3>結果</h3>
                    <div id="result-summary"></div>
                    <div class="cluster-chart-wrap">
                        <canvas id="cluster-chart"></canvas>
                    </div>
                    <div class="cluster-list" id="cluster-list"></div>
                    <button type="button" class="action-button hidden" id="save-clusters">選択したクラスタをグループ化</button>
                </section>
            </section>
        </main>
    </div>

    <script>
        const featureLabels = <?= json_encode($featureLabels, JSON_UNESCAPED_UNICODE) ?>;
        const statusNode = document.getElementById('clustering-status');
        const resultsNode = document.getElementById('clustering-results');
        const summaryNode = document.getElementById('result-summary');
        const clusterListNode = document.getElementById('cluster-list');
        const saveClustersButton = document.getElementById('save-clusters');
        const runButton = document.getElementById('run-clustering');
        const clusterCountField = document.getElementById('cluster-count-field');
        const clusterCountInput = document.getElementById('cluster-count');
        const clusterMethodSelect = document.getElementById('cluster-method');
        const clusterMethodNote = document.getElementById('cluster-method-note');
        let clusterChart = null;
        let latestClusters = {};
        const studentIdsByClass = <?= json_encode((object)$studentsByClassId, JSON_UNESCAPED_UNICODE) ?>;
        const studentIdsByGroup = <?= json_encode((object)$studentsByGroup, JSON_UNESCAPED_UNICODE) ?>;
        const classFilterOptions = <?= json_encode($teacherClasses, JSON_UNESCAPED_UNICODE) ?>;
        const groupFilterOptions = <?= json_encode($teacherGroups, JSON_UNESCAPED_UNICODE) ?>;

        function selectedValues(selector) {
            return Array.from(document.querySelectorAll(selector + ':checked')).map((input) => input.value);
        }

        function setupStudentLogicFilter() {
            const panel = document.getElementById('student-logic-filter-panel');
            const builder = document.getElementById('student-logic-filter-builder');
            const summary = document.getElementById('student-logic-filter-summary');
            if (!panel || !builder || !summary) return;
            const insertPosition = panel.querySelector('.logic-filter-insert-position');
            const trimButton = panel.querySelector('.logic-filter-trim');
            const clearButton = panel.querySelector('.logic-filter-clear');
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
            const allStudentIds = () => Array.from(document.querySelectorAll('.student-checkbox')).map(input => input.value);
            const targetOptions = () => [
                ...classFilterOptions.map(item => ({ value: `class:${item.ClassID}`, label: `グループ(クラス): ${item.ClassName}` })),
                ...groupFilterOptions.map(item => ({ value: `group:${item.group_id}`, label: `グループ: ${item.group_name}` }))
            ];
            const optionHtml = () => {
                const options = targetOptions();
                return options.length === 0 ? '<option value="">対象がありません</option>' : options.map(option => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join('');
            };
            const kindOptionsHtml = selectedKind => [
                ['condition', '対象'], ['and', 'AND'], ['or', 'OR'], ['not', 'NOT'], ['open', '('], ['close', ')']
            ].map(([value, label]) => `<option value="${value}"${value === selectedKind ? ' selected' : ''}>${label}</option>`).join('');
            const tokenLabel = token => {
                const kind = token.dataset.kind;
                if (kind === 'condition') {
                    const select = token.querySelector('.logic-filter-target');
                    return select?.options[select.selectedIndex]?.textContent || '対象';
                }
                if (kind === 'open') return '(';
                if (kind === 'close') return ')';
                return token.dataset.operator || kind.toUpperCase();
            };
            const updateInsertOptions = () => {
                const current = insertPosition.value;
                const tokens = Array.from(builder.querySelectorAll('.logic-filter-token'));
                insertPosition.innerHTML = ['<option value="">末尾に追加</option>']
                    .concat(tokens.map((token, index) => `<option value="${index}">${index + 1}個目の前 (${escapeHtml(tokenLabel(token))})</option>`))
                    .join('');
                if (current !== '' && Number(current) < tokens.length) insertPosition.value = current;
            };
            const renderToken = (token, kind) => {
                token.className = 'logic-filter-token';
                token.dataset.kind = kind;
                delete token.dataset.operator;
                const kindSelect = `<select class="logic-filter-kind">${kindOptionsHtml(kind)}</select>`;
                if (kind === 'condition') {
                    token.innerHTML = `${kindSelect}<select class="logic-filter-target">${optionHtml()}</select><button type="button" class="logic-filter-remove">x</button>`;
                } else if (kind === 'open' || kind === 'close') {
                    token.classList.add('paren');
                    token.innerHTML = `${kindSelect}<span>${kind === 'open' ? '(' : ')'}</span><button type="button" class="logic-filter-remove">x</button>`;
                } else {
                    token.classList.add('operator');
                    if (kind === 'not') token.classList.add('not');
                    token.dataset.operator = kind.toUpperCase();
                    token.innerHTML = `${kindSelect}<span>${kind.toUpperCase()}</span><button type="button" class="logic-filter-remove">x</button>`;
                }
            };
            const addToken = kind => {
                const token = document.createElement('span');
                renderToken(token, kind);
                const tokens = Array.from(builder.querySelectorAll('.logic-filter-token'));
                const position = insertPosition.value !== '' ? Number(insertPosition.value) : tokens.length;
                if (Number.isInteger(position) && position >= 0 && position < tokens.length) {
                    builder.insertBefore(token, tokens[position]);
                    insertPosition.value = String(position + 1);
                } else {
                    builder.appendChild(token);
                }
                updateInsertOptions();
            };
            const getTokens = () => Array.from(builder.querySelectorAll('.logic-filter-token')).map(token => {
                const kind = token.dataset.kind;
                if (kind === 'condition') {
                    const [targetType, targetId] = token.querySelector('.logic-filter-target').value.split(':');
                    return { type: 'condition', targetType, targetId };
                }
                if (kind === 'open' || kind === 'close') return { type: 'paren', paren: kind === 'open' ? '(' : ')' };
                return { type: 'operator', operator: token.dataset.operator };
            });
            const setForCondition = (type, id) => {
                const available = new Set(allStudentIds());
                const source = type === 'group' ? studentIdsByGroup : studentIdsByClass;
                return new Set((source[String(id)] || []).map(String).filter(uid => available.has(uid)));
            };
            const complement = source => {
                const selected = new Set(source);
                return new Set(allStudentIds().filter(uid => !selected.has(uid)));
            };
            const evaluate = () => {
                const list = getTokens();
                if (list.length === 0) return new Set(allStudentIds());
                let index = 0;
                const primary = () => {
                    const token = list[index];
                    if (!token) throw new Error('条件が途中で終わっています。');
                    if (token.type === 'operator' && token.operator === 'NOT') {
                        index++;
                        return complement(primary());
                    }
                    if (token.type === 'paren' && token.paren === '(') {
                        index++;
                        const result = orExpr();
                        if (!list[index] || list[index].type !== 'paren' || list[index].paren !== ')') throw new Error('閉じ括弧を置いてください。');
                        index++;
                        return result;
                    }
                    if (token.type === 'condition') {
                        index++;
                        if (!token.targetType || !token.targetId) throw new Error('対象を選択してください。');
                        return setForCondition(token.targetType, token.targetId);
                    }
                    throw new Error('条件または括弧を置いてください。');
                };
                const andExpr = () => {
                    let result = primary();
                    while (list[index]?.type === 'operator' && list[index].operator === 'AND') {
                        index++;
                        const right = primary();
                        result = new Set([...result].filter(uid => right.has(uid)));
                    }
                    return result;
                };
                const orExpr = () => {
                    let result = andExpr();
                    while (list[index]?.type === 'operator' && list[index].operator === 'OR') {
                        index++;
                        result = new Set([...result, ...andExpr()]);
                    }
                    return result;
                };
                const result = orExpr();
                if (index !== list.length) throw new Error('式の並びを確認してください。');
                return result;
            };
            panel.querySelectorAll('[data-add-filter]').forEach(button => button.addEventListener('click', () => addToken(button.dataset.addFilter)));
            builder.addEventListener('click', event => {
                if (event.target.classList.contains('logic-filter-remove')) {
                    event.target.closest('.logic-filter-token').remove();
                    updateInsertOptions();
                }
            });
            builder.addEventListener('change', event => {
                const token = event.target.closest('.logic-filter-token');
                if (!token) return;
                if (event.target.classList.contains('logic-filter-kind')) renderToken(token, event.target.value);
                updateInsertOptions();
            });
            trimButton.addEventListener('click', () => {
                if (insertPosition.value === '') {
                    summary.textContent = '削除を始める追加位置を選択してください。';
                    summary.classList.add('is-error');
                    return;
                }
                const start = Number(insertPosition.value);
                Array.from(builder.querySelectorAll('.logic-filter-token')).forEach((token, index) => { if (index >= start) token.remove(); });
                updateInsertOptions();
                summary.textContent = `${start + 1}個目以降の部品を削除しました。`;
                summary.classList.remove('is-error');
            });
            clearButton.addEventListener('click', () => {
                builder.innerHTML = '';
                updateInsertOptions();
                summary.textContent = '式を空にしました。空のまま適用すると、すべての学習者が対象になります。';
                summary.classList.remove('is-error');
            });
            document.getElementById('apply-student-logic-filter')?.addEventListener('click', () => {
                try {
                    const selected = evaluate();
                    document.querySelectorAll('.student-checkbox').forEach(input => { input.checked = selected.has(input.value); });
                    document.querySelectorAll('.select-class-students').forEach(input => {
                        const items = Array.from(document.querySelectorAll(`.student-checkbox[data-class-id="${input.dataset.classId}"]`));
                        input.checked = items.length > 0 && items.every(item => item.checked);
                    });
                    summary.textContent = `${selected.size}名の学習者を選択しています。`;
                    summary.classList.remove('is-error');
                } catch (error) {
                    summary.textContent = error.message || '論理式を確認してください。';
                    summary.classList.add('is-error');
                }
            });
            document.getElementById('reset-student-logic-filter')?.addEventListener('click', () => {
                builder.innerHTML = '';
                setCheckboxes('.student-checkbox, .select-class-students', true);
                summary.textContent = 'すべての学習者を対象にしています。';
                summary.classList.remove('is-error');
                addToken('condition');
            });
            addToken('condition');
        }

        function setStatus(message, isError = false) {
            statusNode.textContent = message;
            statusNode.classList.toggle('error', isError);
        }

        function setCheckboxes(selector, checked) {
            document.querySelectorAll(selector).forEach((input) => {
                input.checked = checked;
            });
        }

        function updateClusterCountControl() {
            const method = clusterMethodSelect?.value || 'kmeans';
            const usesFixedClusterCount = method === 'kmeans';
            clusterCountField?.classList.toggle('hidden', !usesFixedClusterCount);
            if (clusterCountInput) {
                clusterCountInput.disabled = !usesFixedClusterCount;
            }
            if (clusterMethodNote) {
                clusterMethodNote.textContent = usesFixedClusterCount
                    ? ''
                    : 'X-Means / G-Means はデータに応じてクラスタ数を自動決定します。';
            }
        }

        document.getElementById('select-all-students')?.addEventListener('click', () => {
            setCheckboxes('.student-checkbox, .select-class-students', true);
        });

        document.getElementById('clear-all-students')?.addEventListener('click', () => {
            setCheckboxes('.student-checkbox, .select-class-students', false);
        });

        document.getElementById('select-all-features')?.addEventListener('click', () => {
            setCheckboxes('.feature-checkbox', true);
        });

        document.getElementById('clear-all-features')?.addEventListener('click', () => {
            setCheckboxes('.feature-checkbox', false);
        });

        document.querySelectorAll('.select-class-students').forEach((input) => {
            input.addEventListener('change', () => {
                document.querySelectorAll('.student-checkbox').forEach((studentInput) => {
                    if (studentInput.dataset.classId !== input.dataset.classId) {
                        return;
                    }
                    studentInput.checked = input.checked;
                });
            });
        });

        document.querySelectorAll('.student-checkbox').forEach((input) => {
            input.addEventListener('change', () => {
                const classInputs = Array.from(document.querySelectorAll('.student-checkbox')).filter((studentInput) => {
                    return studentInput.dataset.classId === input.dataset.classId;
                });
                const classToggle = Array.from(document.querySelectorAll('.select-class-students')).find((toggle) => {
                    return toggle.dataset.classId === input.dataset.classId;
                });
                if (classToggle) {
                    classToggle.checked = classInputs.every((studentInput) => studentInput.checked);
                }
            });
        });

        setupStudentLogicFilter();
        clusterMethodSelect?.addEventListener('change', updateClusterCountControl);
        updateClusterCountControl();

        runButton?.addEventListener('click', async () => {
            const studentIDs = selectedValues('.student-checkbox');
            const features = selectedValues('.feature-checkbox');
            const method = clusterMethodSelect?.value || 'kmeans';
            const clusterCount = method === 'kmeans' ? (clusterCountInput?.value || '2') : '';

            if (studentIDs.length < 2) {
                setStatus('学習者を2名以上選択してください。', true);
                return;
            }

            if (features.length === 0) {
                setStatus('特徴量を1つ以上選択してください。', true);
                return;
            }

            setStatus('クラスタリングを実行しています...');
            runButton.disabled = true;

            try {
                const response = await fetch('perform_clustering.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        features: features.join(','),
                        studentIDs: studentIDs.join(','),
                        clusterCount,
                        method
                    }).toString()
                });
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (error) {
                    throw new Error(text.trim() || 'レスポンスの解析に失敗しました。');
                }

                if (!response.ok || data.error) {
                    throw new Error(data.error || 'クラスタリングに失敗しました。');
                }

                latestClusters = data.clusters || {};
                renderResults(latestClusters, features, data);
                setStatus('クラスタリングが完了しました。');
            } catch (error) {
                setStatus(error.message, true);
            } finally {
                runButton.disabled = false;
            }
        });

        saveClustersButton?.addEventListener('click', async () => {
            const selectedCheckboxes = Array.from(document.querySelectorAll('.cluster-checkbox:checked'));
            if (selectedCheckboxes.length === 0) {
                setStatus('グループ化するクラスタを選択してください。', true);
                return;
            }

            const payload = [];
            for (const checkbox of selectedCheckboxes) {
                const clusterKey = checkbox.value;
                const nameInput = checkbox.closest('.cluster-group')?.querySelector('.cluster-name-input');
                const groupName = nameInput?.value.trim() || '';
                if (groupName === '') {
                    setStatus(`クラスタ ${clusterKey}のクラスタ名を入力してください。`, true);
                    nameInput?.focus();
                    return;
                }
                payload.push({
                    group_name: groupName,
                    students: (latestClusters[clusterKey] || []).map((student) => student.id)
                });
            }

            setStatus('グループを作成しています...');
            saveClustersButton.disabled = true;

            try {
                const response = await fetch('group_students.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const text = await response.text();
                if (!response.ok) {
                    throw new Error(text.trim() || 'グループ化に失敗しました。');
                }
                setStatus('選択したクラスタをグループ化しました。');
            } catch (error) {
                setStatus(error.message, true);
            } finally {
                saveClustersButton.disabled = false;
            }
        });

        function renderResults(clusters, features, data) {
            resultsNode.classList.remove('hidden');
            clusterListNode.innerHTML = '';
            const clusterKeys = Object.keys(clusters).sort((a, b) => Number(a) - Number(b));
            const totalStudents = clusterKeys.reduce((sum, key) => sum + clusters[key].length, 0);
            const featureText = features.map((feature) => featureLabels[feature] || feature).join('、');
            summaryNode.textContent = `対象 ${totalStudents}名 / ${clusterKeys.length}クラスタ / 特徴量: ${featureText}`;

            clusterKeys.forEach((clusterKey) => {
                const group = document.createElement('section');
                group.className = 'cluster-group';
                const heading = document.createElement('h4');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'cluster-checkbox';
                checkbox.value = clusterKey;
                heading.appendChild(checkbox);
                heading.appendChild(document.createTextNode(`クラスタ ${clusterKey} (${clusters[clusterKey].length}名)`));
                group.appendChild(heading);

                const nameLabel = document.createElement('label');
                nameLabel.className = 'cluster-name-field';
                nameLabel.appendChild(document.createTextNode('クラスタ名'));
                const nameInput = document.createElement('input');
                nameInput.type = 'text';
                nameInput.className = 'cluster-name-input';
                nameInput.value = `クラスタ ${clusterKey}`;
                nameInput.placeholder = 'クラスタ名を入力';
                nameInput.required = true;
                nameInput.dataset.clusterKey = clusterKey;
                nameLabel.appendChild(nameInput);
                group.appendChild(nameLabel);

                const list = document.createElement('ul');
                clusters[clusterKey].forEach((student) => {
                    const item = document.createElement('li');
                    item.textContent = `${student.name || student.id} (${student.id})`;
                    list.appendChild(item);
                });
                group.appendChild(list);
                clusterListNode.appendChild(group);
            });

            saveClustersButton.classList.toggle('hidden', clusterKeys.length === 0);
            drawClusterChart(clusters);
        }

        function drawClusterChart(clusters) {
            const ctx = document.getElementById('cluster-chart').getContext('2d');
            if (clusterChart) {
                clusterChart.destroy();
            }

            const colors = [
                'rgba(37, 99, 235, 0.78)',
                'rgba(220, 38, 38, 0.78)',
                'rgba(5, 150, 105, 0.78)',
                'rgba(217, 119, 6, 0.78)',
                'rgba(124, 58, 237, 0.78)',
                'rgba(8, 145, 178, 0.78)',
                'rgba(190, 24, 93, 0.78)',
                'rgba(82, 82, 91, 0.78)'
            ];

            const datasets = Object.keys(clusters).sort((a, b) => Number(a) - Number(b)).map((clusterKey, index) => ({
                label: `クラスタ ${clusterKey}`,
                data: clusters[clusterKey].map((student) => ({
                    x: Number(student.pca1),
                    y: Number(student.pca2),
                    label: `${student.name || student.id} (${student.id})`
                })),
                backgroundColor: colors[index % colors.length],
                borderColor: 'rgba(17, 24, 39, 0.86)',
                borderWidth: 1,
                pointRadius: 6,
                pointHoverRadius: 8
            }));

            clusterChart = new Chart(ctx, {
                type: 'scatter',
                data: { datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return context.raw.label;
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'クラスタリング結果 (PCA)'
                        }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: '次元1' }
                        },
                        y: {
                            title: { display: true, text: '次元2' }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>

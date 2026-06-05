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
$teacherClasses = [];

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
        }
        $stmtStudents->close();
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
                            <p>担当クラスに学習者が登録されていません。</p>
                        <?php else: ?>
                            <div class="controls">
                                <button type="button" class="secondary-button" id="select-all-students">全選択</button>
                                <button type="button" class="secondary-button" id="clear-all-students">全解除</button>
                            </div>
                            <div class="student-selector checkbox-section">
                                <?php foreach ($studentsByClass as $className => $students): ?>
                                    <?php $classId = $students[0]['ClassID']; ?>
                                    <div class="class-heading">
                                        <h4><?= htmlspecialchars($className, ENT_QUOTES, 'UTF-8') ?></h4>
                                        <label>
                                            <input type="checkbox" class="select-class-students" data-class-id="<?= htmlspecialchars($classId, ENT_QUOTES, 'UTF-8') ?>" checked>
                                            このクラスを選択
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
                        <label class="control-field">
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
        let clusterChart = null;
        let latestClusters = {};

        function selectedValues(selector) {
            return Array.from(document.querySelectorAll(selector + ':checked')).map((input) => input.value);
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

        runButton?.addEventListener('click', async () => {
            const studentIDs = selectedValues('.student-checkbox');
            const features = selectedValues('.feature-checkbox');
            const clusterCount = document.getElementById('cluster-count').value;
            const method = document.getElementById('cluster-method').value;

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
            const selectedClusters = Array.from(document.querySelectorAll('.cluster-checkbox:checked')).map((input) => input.value);
            if (selectedClusters.length === 0) {
                setStatus('グループ化するクラスタを選択してください。', true);
                return;
            }

            const payload = selectedClusters.map((clusterKey) => ({
                group_name: `クラスタ ${clusterKey}`,
                students: (latestClusters[clusterKey] || []).map((student) => student.id)
            }));

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

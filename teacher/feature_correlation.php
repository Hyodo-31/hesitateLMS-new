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

$featureColumns = getFeatureColumns($conn, $fallbackFeatureColumns);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? '';
    $featureMap = array_fill_keys($featureColumns, true);

    if ($action === 'get_correlation_data') {
        $mode = $_POST['mode'] ?? 'understand';
        $xFeature = $_POST['feature_x'] ?? ($_POST['feature'] ?? '');
        $yFeature = $_POST['feature_y'] ?? '';

        if (!isset($featureMap[$xFeature])) {
            jsonResponse(['error' => '無効な特徴量です。']);
        }

        if ($mode === 'feature_pair') {
            if (!isset($featureMap[$yFeature])) {
                jsonResponse(['error' => '比較する特徴量を選択してください。']);
            }

            $xSql = quoteIdentifier($xFeature);
            $ySql = quoteIdentifier($yFeature);
            $sql = "SELECT UID, WID, attempt, Understand, {$xSql} AS x_value, {$ySql} AS y_value
                    FROM test_featurevalue
                    WHERE {$xSql} IS NOT NULL AND {$ySql} IS NOT NULL";
            $xLabel = $xFeature;
            $yLabel = $yFeature;
        } else {
            $xSql = quoteIdentifier($xFeature);
            $sql = "SELECT UID, WID, attempt, Understand, {$xSql} AS x_value, Understand AS y_value
                    FROM test_featurevalue
                    WHERE Understand IS NOT NULL AND {$xSql} IS NOT NULL";
            $xLabel = $xFeature;
            $yLabel = 'Understand(迷い度)';
            $mode = 'understand';
        }

        $result = $conn->query($sql);
        if (!$result) {
            jsonResponse(['error' => 'データ取得に失敗しました。']);
        }

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

    if ($action === 'get_feature_correlation_list') {
        $xFeature = $_POST['feature_x'] ?? '';
        if (!isset($featureMap[$xFeature])) {
            jsonResponse(['error' => '無効な特徴量です。']);
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
        $sql = 'SELECT ' . implode(', ', $selectParts) . " FROM test_featurevalue WHERE {$baseSql} IS NOT NULL";
        $result = $conn->query($sql);
        if (!$result) {
            jsonResponse(['error' => '相関一覧の取得に失敗しました。']);
        }

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
            padding: 30px 24px 48px;
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

        @media (max-width: 900px) {
            .feature-correlation-page {
                padding: 22px 14px 36px;
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
        }
    </style>
</head>
<body>
<div class="main-content">
    <main class="feature-correlation-page">
        <div class="page-heading">
            <h1>特徴量相関分析</h1>
            <a class="home-link" href="teachertrue.php">← ホームへ戻る</a>
        </div>

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
                    <h2>相関ランキング</h2>
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
const rankingBaseLabel = document.getElementById('ranking-base-label');
const rankingBody = document.getElementById('correlation-table-body');
const emptyList = document.getElementById('empty-list');

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
    const isFeaturePair = getMode() === 'feature_pair';
    featureYControl.classList.toggle('hidden', !isFeaturePair);
    rankingPanel.classList.toggle('hidden', !isFeaturePair);
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
    rankingBody.innerHTML = '';
    emptyList.classList.toggle('hidden', items.length > 0);
    rankingBaseLabel.textContent = featureXSelect.value;

    const selectedY = featureYSelect.value;
    items.forEach((item) => {
        const row = document.createElement('tr');
        row.dataset.featureY = item.feature_y;
        row.classList.toggle('is-selected', item.feature_y === selectedY);

        const featureCell = document.createElement('td');
        featureCell.textContent = item.feature_y;
        featureCell.title = item.feature_y;

        const correlationCell = document.createElement('td');
        correlationCell.className = 'numeric';
        correlationCell.textContent = formatCorrelation(item.correlation);

        const countCell = document.createElement('td');
        countCell.className = 'numeric';
        countCell.textContent = formatValue(item.count, 0);

        row.append(featureCell, correlationCell, countCell);
        row.addEventListener('click', () => {
            featureYSelect.value = item.feature_y;
            loadData(false);
        });
        rankingBody.appendChild(row);
    });
}

function updateRankingSelection() {
    rankingBody.querySelectorAll('tr').forEach((row) => {
        row.classList.toggle('is-selected', row.dataset.featureY === featureYSelect.value);
    });
}

async function loadRanking() {
    const body = new URLSearchParams({
        action: 'get_feature_correlation_list',
        feature_x: featureXSelect.value,
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

        if (mode === 'feature_pair') {
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

syncControls();
loadData(true);
</script>
</body>
</html>
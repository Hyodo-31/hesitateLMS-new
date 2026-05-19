<?php
include '../lang.php';
require '../dbc.php';

if (empty($_SESSION['MemberID'])) {
    http_response_code(401);
    echo 'ログイン情報が見つかりません。';
    exit;
}

$featureColumns = [
    'Time','distance','averageSpeed','maxSpeed','thinkingTime','answeringTime','totalStopTime','maxStopTime',
    'totalDDIntervalTime','maxDDIntervalTime','maxDDTime','minDDTime','DDCount','groupingDDCount','groupingCountbool',
    'xUTurnCount','yUTurnCount','xUTurnCountDD','yUTurnCountDD','register_move_count1','register_move_count2',
    'register_move_count3','register_move_count4','register01count1','register01count2','register01count3','register01count4',
    'registerDDCount','register_notDDCount','register_fix_count1','register_fix_count2','register_fix_count3','register_fix_count4',
    'register_delete_count1','register_delete_count2','register_delete_count3','register_delete_count4','register_allDelete_count1',
    'register_allDelete_count2','register_allDelete_count3','register_allDelete_count4','register_notallDelete_count1',
    'register_notallDelete_count2','register_notallDelete_count3','register_notallDelete_count4'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_correlation_data') {
    header('Content-Type: application/json');
    $feature = $_POST['feature'] ?? '';
    if (!in_array($feature, $featureColumns, true)) {
        echo json_encode(['error' => '無効な特徴量です。']);
        exit;
    }

    $sql = "SELECT UID, WID, attempt, Understand, `$feature` AS feature_value FROM test_featurevalue WHERE Understand IS NOT NULL AND `$feature` IS NOT NULL";
    $result = $conn->query($sql);
    $points = [];
    $x = [];
    $y = [];

    while ($row = $result->fetch_assoc()) {
        $fx = (float)$row['feature_value'];
        $fy = (float)$row['Understand'];
        $x[] = $fx;
        $y[] = $fy;
        $points[] = [
            'x' => $fx,
            'y' => $fy,
            'uid' => $row['UID'],
            'wid' => $row['WID'],
            'attempt' => $row['attempt']
        ];
    }

    $n = count($x);
    $r = null;
    if ($n > 1) {
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0.0;
        $sumX2 = 0.0;
        $sumY2 = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }
        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));
        if ($denominator > 0) {
            $r = $numerator / $denominator;
        }
    }

    echo json_encode([
        'feature' => $feature,
        'count' => $n,
        'correlation' => $r,
        'points' => $points
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>特徴量と迷い度の相関</title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="main-content" style="padding-top:30px;">
    <h1>特徴量と迷い度（Understand）の相関</h1>
    <p><a href="teachertrue.php">← ホームへ戻る</a></p>

    <div class="controls">
        <label for="feature-select">特徴量を選択:</label>
        <select id="feature-select">
            <?php foreach ($featureColumns as $col): ?>
                <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
            <?php endforeach; ?>
        </select>
        <button id="load-btn">相関を表示</button>
    </div>

    <p id="stats">データを読み込んでください。</p>
    <canvas id="scatterChart" height="120"></canvas>
</div>

<script>
let scatterChart;

function renderChart(points, feature) {
    const ctx = document.getElementById('scatterChart').getContext('2d');
    if (scatterChart) scatterChart.destroy();

    scatterChart = new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: `${feature} × Understand`,
                data: points,
                backgroundColor: 'rgba(255, 99, 132, 0.8)',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const p = context.raw;
                            return `UID:${p.uid} WID:${p.wid} attempt:${p.attempt} / ${feature}:${p.x.toFixed(3)} / Understand:${p.y}`;
                        }
                    }
                }
            },
            scales: {
                x: { title: { display: true, text: feature } },
                y: { title: { display: true, text: 'Understand(迷い度)' } }
            }
        }
    });
}

async function loadData() {
    const feature = document.getElementById('feature-select').value;
    const body = new URLSearchParams({ action: 'get_correlation_data', feature });
    const response = await fetch('feature_correlation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    });
    const data = await response.json();

    if (data.error) {
        alert(data.error);
        return;
    }

    const rText = (data.correlation === null) ? '算出不可' : data.correlation.toFixed(4);
    document.getElementById('stats').textContent = `件数: ${data.count} / 相関係数 (Pearson r): ${rText}`;
    renderChart(data.points, feature);
}

document.getElementById('load-btn').addEventListener('click', loadData);
loadData();
</script>
</body>
</html>

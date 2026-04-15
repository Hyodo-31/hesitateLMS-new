<?php
include '../lang.php';
require '../dbc.php';

if (empty($_SESSION['MemberID'])) {
    http_response_code(401);
    exit('ログイン情報が見つかりません。');
}

$teacherId = (string)$_SESSION['MemberID'];
$messages = [];
$errors = [];

function runPythonCommand(string $scriptPath, array $args = []): array
{
    $pythonBin = null;
    foreach (['python', 'python3'] as $candidate) {
        $checkOutput = [];
        $checkStatus = 0;
        exec($candidate . ' --version 2>&1', $checkOutput, $checkStatus);
        if ($checkStatus === 0) {
            $pythonBin = $candidate;
            break;
        }
    }

    if ($pythonBin === null) {
        return [
            'status' => 127,
            'output' => [
                'python / python3 コマンドが見つかりません。',
                'サーバーに Python をインストールし、PATH に追加してください。',
            ],
        ];
    }

    $cmdParts = [$pythonBin, escapeshellarg($scriptPath)];
    foreach ($args as $arg) {
        $cmdParts[] = escapeshellarg((string)$arg);
    }
    $output = [];
    $status = 0;
    $cmd = implode(' ', $cmdParts) . ' 2>&1';
    exec($cmd, $output, $status);

    return ['status' => $status, 'output' => $output];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_word_features'])) {
        $result = runPythonCommand(__DIR__ . '/word_ml/word_feature_calculator.py');
        $output = $result['output'];
        $status = $result['status'];
        if ($status === 0) {
            $messages[] = implode("\n", $output);
        } else {
            $errors[] = "特徴量生成に失敗しました。\n" . implode("\n", $output);
        }
    }

    if (isset($_POST['run_word_estimation'])) {
        $result = runPythonCommand(__DIR__ . '/word_ml/word_machine_learning.py', [$teacherId]);
        $output = $result['output'];
        $status = $result['status'];
        if ($status === 0) {
            $messages[] = implode("\n", $output);
        } else {
            $errors[] = "機械学習による推定に失敗しました。\n" . implode("\n", $output);
        }
    }
}

$featureCount = 0;
$resultCount = 0;
$latestResults = [];

if ($stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM test_featurevalue_word')) {
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $featureCount = (int)($res['cnt'] ?? 0);
    $stmt->close();
}
if ($stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM temporary_results_word WHERE teacher_id = ?')) {
    $stmt->bind_param('s', $teacherId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $resultCount = (int)($res['cnt'] ?? 0);
    $stmt->close();
}
if ($stmt = $conn->prepare('SELECT tr.UID, tr.WID, tr.WWID, tr.attempt, tr.Understand, tfw.word_text FROM temporary_results_word tr LEFT JOIN test_featurevalue_word tfw ON tfw.UID = tr.UID AND tfw.WID = tr.WID AND tfw.WWID = tr.WWID AND tfw.attempt = tr.attempt WHERE tr.teacher_id = ? ORDER BY tr.created_at DESC')) {
    $stmt->bind_param('s', $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $latestResults[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'ja', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>単語単位 迷い推定・機械学習</title>
    <link rel="stylesheet" href="../style/machineLearning_styles.css">
    <style>
        .word-ml-container { padding: 20px; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .btn { background:#2276d2;color:#fff;padding:10px 14px;border:none;border-radius:6px;cursor:pointer; }
        .btn + .btn { margin-left: 8px; }
        .msg { white-space: pre-wrap; background:#eef9ee; border:1px solid #afd6af; padding:8px; }
        .err { white-space: pre-wrap; background:#fff0f0; border:1px solid #db9b9b; padding:8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .status-hesitate { color: #c62828; font-weight: 700; }
        .status-no-hesitate { color: #2e7d32; font-weight: 700; }
        .table-scroll-wrapper { max-height: 70vh; overflow: auto; border: 1px solid #ddd; border-radius: 6px; }
        .table-scroll-wrapper table { min-width: 1000px; }
        .table-scroll-wrapper thead th { position: sticky; top: 0; z-index: 1; }
    </style>
</head>
<body>
<div class="container">
    <aside>
        <ul>
            <li><a href="teachertrue.php">ホーム</a></li>
            <li><a href="machineLearning_sample.php">問題単位 迷い推定</a></li>
            <li><a href="machineLearning_word.php">単語単位 迷い推定</a></li>
        </ul>
    </aside>
    <main class="word-ml-container">
        <h1>単語単位の特徴量生成・迷い推定</h1>

        <div class="card">
            <p>test_featurevalue_word 保存件数: <strong><?= $featureCount ?></strong></p>
            <p>temporary_results_word 保存件数: <strong><?= $resultCount ?></strong></p>
            <form method="post">
                <button class="btn" type="submit" name="generate_word_features" value="1">単語単位の特徴量を生成</button>
                <button class="btn" type="submit" name="run_word_estimation" value="1">機械学習で迷い度を推定</button>
            </form>
        </div>

        <?php foreach ($messages as $msg): ?>
            <div class="card msg"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $err): ?>
            <div class="card err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <div class="card">
            <h2>推定結果一覧 (全件)</h2>
            <div class="table-scroll-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>UID</th><th>WID</th><th>WWID</th><th>単語</th><th>attempt</th><th>迷い推定結果</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($latestResults)): ?>
                        <tr><td colspan="6">データがありません。</td></tr>
                    <?php else: ?>
                        <?php foreach ($latestResults as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$r['UID'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int)$r['WID'] ?></td>
                                <td><?= (int)$r['WWID'] ?></td>
                                <td><?= htmlspecialchars((string)($r['word_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= (int)$r['attempt'] ?></td>
                                <td>
                                    <?php if ((int)$r['Understand'] === 2): ?>
                                        <span class="status-hesitate">迷い有り</span>
                                    <?php else: ?>
                                        <span class="status-no-hesitate">迷い無し</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>

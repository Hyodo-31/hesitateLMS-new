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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate_word_features'])) {
        $cmd = 'python3 ' . escapeshellarg(__DIR__ . '/word_ml/generateParametersForWord.py') . ' 2>&1';
        exec($cmd, $output, $status);
        if ($status === 0) {
            $messages[] = implode("\n", $output);
        } else {
            $errors[] = "特徴量生成に失敗しました。\n" . implode("\n", $output);
        }
    }

    if (isset($_POST['run_word_estimation'])) {
        $cmd = 'python3 ' . escapeshellarg(__DIR__ . '/word_ml/classify_crossval_fromCSV_Word.py') . ' ' . escapeshellarg($teacherId) . ' 2>&1';
        exec($cmd, $output, $status);
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
if ($stmt = $conn->prepare('SELECT UID, WID, WWID, attempt, Understand, predicted_probability, created_at FROM temporary_results_word WHERE teacher_id = ? ORDER BY created_at DESC LIMIT 50')) {
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
            <p>temporary_results_word 保存件数(あなた): <strong><?= $resultCount ?></strong></p>
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
            <h2>最新の推定結果 (50件)</h2>
            <table>
                <thead>
                <tr>
                    <th>UID</th><th>WID</th><th>WWID</th><th>attempt</th><th>Understand</th><th>推定確率</th><th>更新日時</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($latestResults)): ?>
                    <tr><td colspan="7">データがありません。</td></tr>
                <?php else: ?>
                    <?php foreach ($latestResults as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$r['UID'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int)$r['WID'] ?></td>
                            <td><?= (int)$r['WWID'] ?></td>
                            <td><?= (int)$r['attempt'] ?></td>
                            <td><?= (int)$r['Understand'] === 2 ? '迷い有り' : '迷い無し' ?></td>
                            <td><?= is_null($r['predicted_probability']) ? '-' : number_format((float)$r['predicted_probability'], 4) ?></td>
                            <td><?= htmlspecialchars((string)$r['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>

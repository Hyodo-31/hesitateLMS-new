<?php
include '../lang.php';
require '../dbc.php';

if (!isset($_SESSION['MemberID'])) {
    header('Location: ../login.php');
    exit;
}

$teacherId = (int)$_SESSION['MemberID'];
$messages = [];
$errors = [];

function tableHasColumn(mysqli $conn, string $table, string $column): bool
{
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function latestWordResultCsv(string $directory): ?string
{
    $files = glob($directory . '/results_actual_word_*.csv');
    if (!$files) {
        return null;
    }

    usort($files, static function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    return $files[0] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $python = 'python';
    $scriptDir = __DIR__ . '/machineLearning';

    if ($action === 'generate_word_features') {
        $featureScript = $scriptDir . '/generateParametersForWord_update.py';
        if (!file_exists($featureScript)) {
            $errors[] = '特徴量生成スクリプトが見つかりません: ' . htmlspecialchars($featureScript, ENT_QUOTES, 'UTF-8');
        } else {
            $command = escapeshellcmd($python) . ' ' . escapeshellarg($featureScript) . ' 2>&1';
            $output = [];
            $status = 0;
            exec($command, $output, $status);

            if ($status !== 0) {
                $errors[] = '特徴量生成に失敗しました。終了コード: ' . $status;
                $errors[] = implode("\n", $output);
            } else {
                $messages[] = '単語単位の特徴量生成が完了しました。';
                if (!empty($output)) {
                    $messages[] = implode("\n", $output);
                }
            }
        }
    }

    if ($action === 'estimate_word_hesitation') {
        $classifyScript = $scriptDir . '/classify_crossval_fromCSV_Word_sori.py';
        if (!file_exists($classifyScript)) {
            $errors[] = '機械学習スクリプトが見つかりません: ' . htmlspecialchars($classifyScript, ENT_QUOTES, 'UTF-8');
        } else {
            $command = escapeshellcmd($python) . ' ' . escapeshellarg($classifyScript) . ' 2>&1';
            $output = [];
            $status = 0;
            exec($command, $output, $status);

            if ($status !== 0) {
                $errors[] = '迷い度推定に失敗しました。終了コード: ' . $status;
                $errors[] = implode("\n", $output);
            } else {
                $csvPath = latestWordResultCsv($scriptDir);
                if ($csvPath === null || !file_exists($csvPath)) {
                    $errors[] = '推定結果CSV (results_actual_word_*.csv) が見つかりません。';
                } else {
                    $hasAttempt = tableHasColumn($conn, 'temporary_results_word', 'attempt');
                    $hasTeacherId = tableHasColumn($conn, 'temporary_results_word', 'teacher_id');

                    $deleteSql = $hasTeacherId
                        ? 'DELETE FROM temporary_results_word WHERE teacher_id = ?'
                        : 'DELETE FROM temporary_results_word';

                    $deleteStmt = $conn->prepare($deleteSql);
                    if (!$deleteStmt) {
                        $errors[] = 'ログ初期化に失敗しました: ' . $conn->error;
                    } else {
                        if ($hasTeacherId) {
                            $deleteStmt->bind_param('i', $teacherId);
                        }
                        $deleteStmt->execute();
                        $deleteStmt->close();

                        $columns = ['UID', 'WID', 'WWID', 'Understand'];
                        $types = 'iiii';
                        if ($hasTeacherId) {
                            $columns[] = 'teacher_id';
                            $types .= 'i';
                        }
                        if ($hasAttempt) {
                            $columns[] = 'attempt';
                            $types .= 'i';
                        }

                        $placeholders = implode(',', array_fill(0, count($columns), '?'));
                        $insertSql = 'INSERT INTO temporary_results_word (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
                        $insertStmt = $conn->prepare($insertSql);

                        if (!$insertStmt) {
                            $errors[] = 'temporary_results_wordへの保存準備に失敗しました: ' . $conn->error;
                        } else {
                            $handle = fopen($csvPath, 'r');
                            if ($handle === false) {
                                $errors[] = '推定結果CSVを開けませんでした。';
                            } else {
                                $header = fgetcsv($handle);
                                $headerMap = [];
                                if (is_array($header)) {
                                    foreach ($header as $index => $name) {
                                        $headerMap[trim($name)] = $index;
                                    }
                                }

                                $required = ['UID', 'WID', 'WWID', 'Understand'];
                                $missing = array_filter($required, static fn($col) => !array_key_exists($col, $headerMap));
                                if (!empty($missing)) {
                                    $errors[] = '推定結果CSVに必須列がありません: ' . implode(', ', $missing);
                                } else {
                                    $insertCount = 0;
                                    while (($row = fgetcsv($handle)) !== false) {
                                        $uid = (int)$row[$headerMap['UID']];
                                        $wid = (int)$row[$headerMap['WID']];
                                        $wwid = (int)$row[$headerMap['WWID']];
                                        $understand = (int)$row[$headerMap['Understand']];
                                        $attempt = isset($headerMap['attempt']) ? (int)$row[$headerMap['attempt']] : 1;

                                        if ($hasTeacherId && $hasAttempt) {
                                            $insertStmt->bind_param($types, $uid, $wid, $wwid, $understand, $teacherId, $attempt);
                                        } elseif ($hasTeacherId) {
                                            $insertStmt->bind_param($types, $uid, $wid, $wwid, $understand, $teacherId);
                                        } elseif ($hasAttempt) {
                                            $insertStmt->bind_param($types, $uid, $wid, $wwid, $understand, $attempt);
                                        } else {
                                            $insertStmt->bind_param($types, $uid, $wid, $wwid, $understand);
                                        }

                                        if ($insertStmt->execute()) {
                                            $insertCount++;
                                        }
                                    }

                                    $messages[] = '単語単位の迷い度推定が完了しました。保存件数: ' . $insertCount;
                                    $messages[] = '利用した推定結果CSV: ' . basename($csvPath);
                                }

                                fclose($handle);
                            }
                            $insertStmt->close();
                        }
                    }
                }
            }
        }
    }
}

$wordFeatureCount = 0;
$wordResultRows = [];

$countResult = $conn->query('SELECT COUNT(*) AS cnt FROM test_featurevalue_word');
if ($countResult && ($row = $countResult->fetch_assoc())) {
    $wordFeatureCount = (int)$row['cnt'];
}

$hasTeacherId = tableHasColumn($conn, 'temporary_results_word', 'teacher_id');
$hasAttemptColumn = tableHasColumn($conn, 'temporary_results_word', 'attempt');
$selectAttempt = $hasAttemptColumn ? 'attempt' : '1 AS attempt';
$orderAttempt = $hasAttemptColumn ? ', attempt' : '';
$query = $hasTeacherId
    ? "SELECT UID, WID, WWID, Understand, {$selectAttempt} FROM temporary_results_word WHERE teacher_id = ? ORDER BY UID, WID, WWID{$orderAttempt} LIMIT 200"
    : "SELECT UID, WID, WWID, Understand, {$selectAttempt} FROM temporary_results_word ORDER BY UID, WID, WWID{$orderAttempt} LIMIT 200";

$stmt = $conn->prepare($query);
if ($stmt) {
    if ($hasTeacherId) {
        $stmt->bind_param('i', $teacherId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) {
        $wordResultRows[] = $r;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>単語単位 迷い推定</title>
    <link rel="stylesheet" href="../style/machineLearning_styles.css">
    <style>
        .word-page {
            max-width: 1100px;
            margin: 20px auto;
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .actions form {
            margin: 0;
        }

        .actions button {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            background: #0077b6;
            color: #fff;
            cursor: pointer;
        }

        .actions button:hover {
            background: #005f8f;
        }

        .status-block {
            white-space: pre-wrap;
            font-size: 14px;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .status-success {
            background: #e8f5e9;
            border: 1px solid #81c784;
        }

        .status-error {
            background: #ffebee;
            border: 1px solid #e57373;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f5f5f5;
        }
    </style>
</head>

<body>
    <div class="word-page">
        <h1>単語単位の特徴量生成・迷い度推定</h1>
        <p>UID(学習者番号)・WID(問題番号)・WWID(問題内単語番号)をキーに、<code>test_featurevalue_word</code> と <code>temporary_results_word</code> を操作します。</p>

        <div class="actions">
            <form method="post">
                <input type="hidden" name="action" value="generate_word_features">
                <button type="submit">単語特徴量を生成 (generateParametersForWord_update.py)</button>
            </form>

            <form method="post">
                <input type="hidden" name="action" value="estimate_word_hesitation">
                <button type="submit">単語迷い度を推定 (classify_crossval_fromCSV_Word_sori.py)</button>
            </form>

            <form action="teachertrue.php" method="get">
                <button type="submit">教師メインページへ戻る</button>
            </form>
        </div>

        <?php foreach ($messages as $message): ?>
            <div class="status-block status-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <?php foreach ($errors as $error): ?>
            <div class="status-block status-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>

        <h2>現状サマリ</h2>
        <ul>
            <li>test_featurevalue_word レコード数: <strong><?= $wordFeatureCount ?></strong></li>
            <li>temporary_results_word 表示件数: <strong><?= count($wordResultRows) ?></strong> (最大200件)</li>
        </ul>

        <h2>temporary_results_word（最新200件）</h2>
        <table>
            <thead>
                <tr>
                    <th>UID</th>
                    <th>WID</th>
                    <th>WWID</th>
                    <th>Understand</th>
                    <th>attempt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($wordResultRows)): ?>
                    <tr>
                        <td colspan="5">まだ推定結果がありません。</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($wordResultRows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$row['UID'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$row['WID'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$row['WWID'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$row['Understand'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$row['attempt'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>

</html>

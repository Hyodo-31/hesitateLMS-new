<?php
include '../lang.php';
require '../dbc.php';

header('Content-Type: application/json; charset=utf-8');

function clusteringJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function clusteringQuoteIdentifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function clusteringFeatureColumns(mysqli $conn): array
{
    $excludedColumns = [
        'UID' => true,
        'WID' => true,
        'Understand' => true,
        'attempt' => true,
        'date' => true,
        'check' => true,
    ];
    $columns = [];
    $result = $conn->query('SHOW COLUMNS FROM test_featurevalue');

    if (!$result) {
        return [];
    }

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
            $columns[] = $field;
        }
    }
    $result->close();

    return $columns;
}

function clusteringAllowedStudentIds(mysqli $conn, string $teacherId): array
{
    $allowed = [];
    $stmtClasses = $conn->prepare('SELECT ClassID FROM classteacher WHERE TID = ?');
    if (!$stmtClasses) {
        return [];
    }

    $stmtClasses->bind_param('s', $teacherId);
    $stmtClasses->execute();
    $resultClasses = $stmtClasses->get_result();
    $classIds = [];
    while ($row = $resultClasses->fetch_assoc()) {
        $classIds[] = (int)$row['ClassID'];
    }
    $stmtClasses->close();

    if (empty($classIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $types = str_repeat('i', count($classIds));
    $stmtStudents = $conn->prepare("SELECT uid FROM students WHERE ClassID IN ({$placeholders})");
    if (!$stmtStudents) {
        return [];
    }

    $stmtStudents->bind_param($types, ...$classIds);
    $stmtStudents->execute();
    $resultStudents = $stmtStudents->get_result();
    while ($row = $resultStudents->fetch_assoc()) {
        $allowed[] = (string)$row['uid'];
    }
    $stmtStudents->close();

    return array_values(array_unique($allowed));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clusteringJsonResponse(['error' => 'POSTでリクエストしてください。'], 405);
}

if (empty($_SESSION['MemberID']) && empty($_SESSION['TID'])) {
    clusteringJsonResponse(['error' => 'ログイン情報が見つかりません。'], 401);
}

$teacherId = (string)($_SESSION['TID'] ?? $_SESSION['MemberID']);
$features = array_values(array_filter(array_map('trim', explode(',', $_POST['features'] ?? ''))));
$studentIds = array_values(array_unique(array_filter(array_map('trim', explode(',', $_POST['studentIDs'] ?? '')))));
$clusterCount = max(2, min(10, (int)($_POST['clusterCount'] ?? 2)));
$method = $_POST['method'] ?? 'kmeans';
$allowedMethods = ['kmeans' => true, 'xmeans' => true, 'gmeans' => true];

if (empty($features) || empty($studentIds)) {
    clusteringJsonResponse(['error' => '特徴量または学習者IDが不足しています。'], 400);
}

if (!isset($allowedMethods[$method])) {
    clusteringJsonResponse(['error' => '無効なクラスタリング手法です。'], 400);
}

$availableFeatures = clusteringFeatureColumns($conn);
$featureMap = array_fill_keys($availableFeatures, true);
foreach ($features as $feature) {
    if (!isset($featureMap[$feature])) {
        clusteringJsonResponse(['error' => "無効な特徴量です: {$feature}"], 400);
    }
}

$allowedStudentIds = clusteringAllowedStudentIds($conn, $teacherId);
$studentIds = array_values(array_filter($studentIds, function ($uid) use ($allowedStudentIds) {
    return in_array((string)$uid, $allowedStudentIds, true);
}));

if (count($studentIds) < 2) {
    clusteringJsonResponse(['error' => '担当クラス内の学習者を2名以上選択してください。'], 400);
}

$studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
$studentTypes = str_repeat('s', count($studentIds));
$stmtStudents = $conn->prepare("SELECT uid, Name FROM students WHERE uid IN ({$studentPlaceholders})");
if (!$stmtStudents) {
    clusteringJsonResponse(['error' => '学習者情報の取得に失敗しました。'], 500);
}

$stmtStudents->bind_param($studentTypes, ...$studentIds);
$stmtStudents->execute();
$resultStudents = $stmtStudents->get_result();
$studentNames = [];
while ($row = $resultStudents->fetch_assoc()) {
    $studentNames[(string)$row['uid']] = trim((string)$row['Name']);
}
$stmtStudents->close();

$selectParts = ['UID', 'WID'];
foreach ($features as $feature) {
    $selectParts[] = clusteringQuoteIdentifier($feature);
}

$sql = 'SELECT ' . implode(', ', $selectParts) . " FROM test_featurevalue WHERE UID IN ({$studentPlaceholders})";
$stmtFeatures = $conn->prepare($sql);
if (!$stmtFeatures) {
    clusteringJsonResponse(['error' => '特徴量データの取得に失敗しました。'], 500);
}

$stmtFeatures->bind_param($studentTypes, ...$studentIds);
$stmtFeatures->execute();
$resultFeatures = $stmtFeatures->get_result();

$rows = [];
$studentsWithData = [];
while ($row = $resultFeatures->fetch_assoc()) {
    $uid = (string)$row['UID'];
    $csvRow = [
        'uid' => $uid,
        'name' => ($studentNames[$uid] ?? '') !== '' ? $studentNames[$uid] : $uid,
        'wid' => $row['WID'],
    ];

    $hasNumericFeature = false;
    foreach ($features as $feature) {
        $value = $row[$feature] ?? null;
        if ($value !== null && $value !== '' && is_numeric($value)) {
            $csvRow[$feature] = (float)$value;
            $hasNumericFeature = true;
        } else {
            $csvRow[$feature] = '';
        }
    }

    if ($hasNumericFeature) {
        $rows[] = $csvRow;
        $studentsWithData[$uid] = true;
    }
}
$stmtFeatures->close();

if (count($studentsWithData) < 2) {
    clusteringJsonResponse(['error' => '選択した学習者の特徴量データが不足しています。'], 400);
}

$clusterCount = min($clusterCount, count($studentsWithData));
$inputFile = tempnam(sys_get_temp_dir(), 'clustering_input_');
$outputFile = tempnam(sys_get_temp_dir(), 'clustering_output_');

if ($inputFile === false || $outputFile === false) {
    clusteringJsonResponse(['error' => '一時ファイルの作成に失敗しました。'], 500);
}

$fp = fopen($inputFile, 'w');
if (!$fp) {
    clusteringJsonResponse(['error' => '入力ファイルを作成できません。'], 500);
}

$header = array_merge(['uid', 'name', 'wid'], $features);
fputcsv($fp, $header);
foreach ($rows as $row) {
    $csvRow = [];
    foreach ($header as $column) {
        $csvRow[] = $row[$column] ?? '';
    }
    fputcsv($fp, $csvRow);
}
fclose($fp);

$scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'clustering_script.py';
$command = 'python ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($inputFile) . ' ' . escapeshellarg($outputFile) . ' ' . (int)$clusterCount . ' ' . escapeshellarg($method) . ' 2>&1';
exec($command, $output, $status);

if ($status !== 0) {
    @unlink($inputFile);
    @unlink($outputFile);
    clusteringJsonResponse([
        'error' => "クラスタリング処理中にエラーが発生しました。\n" . implode("\n", $output),
    ], 500);
}

$clustersJson = json_decode((string)file_get_contents($outputFile), true);
@unlink($inputFile);
@unlink($outputFile);

if (!is_array($clustersJson) || !isset($clustersJson['clusters']) || !is_array($clustersJson['clusters'])) {
    clusteringJsonResponse(['error' => 'クラスタリング結果を読み込めませんでした。'], 500);
}

clusteringJsonResponse([
    'clusters' => $clustersJson['clusters'],
    'cluster_count' => count($clustersJson['clusters']),
    'student_count' => count($studentsWithData),
    'features' => $features,
    'method' => $method,
]);

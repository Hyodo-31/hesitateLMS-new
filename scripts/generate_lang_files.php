<?php

// --- 設定 ---
$csvFilePath = __DIR__ . '/../translations/translations.csv';
$outputDir = __DIR__ . '/../lang/';
// CSVがShift_JISなど、UTF-8以外の文字コードの場合のみ設定を変更します。
// UTF-8 または UTF-8(BOM付き) の場合は 'UTF-8' のままで問題ありません。
$csvEncoding = 'UTF-8';

// --- スクリプト本体 ---

echo "言語ファイルの生成を開始します...\n";

if (!file_exists($csvFilePath) || !is_readable($csvFilePath)) {
    die("エラー: CSVファイルが見つからないか、読み込めません。\nパス: {$csvFilePath}\n");
}

$handle = fopen($csvFilePath, 'r');
if ($handle === false) {
    die("エラー: CSVファイルを開けませんでした。\n");
}

// ヘッダー行（言語コード）を取得
$headers = fgetcsv($handle);

//【BOM対策】ヘッダーの最初の要素からBOM（\xEF\xBB\xBF）を検知して取り除く
if (isset($headers[0])) {
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
}

if ($headers === false || $headers[0] !== 'key') {
    die("エラー: CSVのヘッダーが不正です。1列目は 'key' である必要があります。\n");
}

//【改良点】ヘッダーから言語リストを取得する際に、空の値をフィルタリングで除去する
$languages = array_filter(array_slice($headers, 1));

if (empty($languages)) {
    die("エラー: 有効な言語がヘッダーに見つかりませんでした。\n");
}
echo "対象言語: " . implode(', ', $languages) . "\n";

$translations = [];
foreach ($languages as $lang) {
    $translations[$lang] = [];
}

$lineNum = 1;
while (($row = fgetcsv($handle)) !== false) {
    $lineNum++;
    
    // CSVがUTF-8でない場合の保険
    if ($csvEncoding !== 'UTF-8') {
        foreach ($row as $i => $col) {
            $row[$i] = mb_convert_encoding($col, 'UTF-8', $csvEncoding);
        }
    }

    $key = $row[0];
    if (empty($key)) {
        echo "警告: {$lineNum}行目のキーが空です。スキップします。\n";
        continue;
    }

    foreach ($languages as $index => $lang) {
        $headerIndex = array_search($lang, $headers);
        $translationText = $row[$headerIndex] ?? '';
        $translations[$lang][$key] = $translationText;
    }
}
fclose($handle);

foreach ($translations as $lang => $messages) {
    $phpCode = "<?php\n\nreturn " . var_export($messages, true) . ";\n";
    $outputFilePath = $outputDir . $lang . '.php';

    if (file_put_contents($outputFilePath, $phpCode)) {
        echo "-> '{$outputFilePath}' を正常に生成しました。\n";
    } else {
        echo "-> エラー: '{$outputFilePath}' の書き込みに失敗しました。\n";
    }
}

echo "すべての処理が完了しました。\n";
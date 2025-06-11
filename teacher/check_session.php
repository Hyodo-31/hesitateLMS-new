<?php
// セッションを開始して中身を確認する
session_start();

// 画面を分かりやすくするためにヘッダーを設定
header('Content-Type: text/plain; charset=utf-8');

echo "現在のセッション情報を確認します。\n";
echo "================================\n\n";

if (empty($_SESSION)) {
    echo "セッションは空です。何も保存されていません。\n";
} else {
    echo "セッションの内容:\n";
    print_r($_SESSION);
}

echo "\n================================\n";
if (isset($_SESSION['MemberName'])) {
    echo "結果: MemberName は「" . $_SESSION['MemberName'] . "」として正しくセットされています。\n";
} else {
    echo "結果: MemberName がセットされていません。これがリダイレクトループの原因です。\n";
}
?>
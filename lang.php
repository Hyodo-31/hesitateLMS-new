<?php
session_start();

// 言語選択（URLパラメータから）
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('lang', $_GET['lang'], time() + 60 * 60 * 24 * 30); // 30日
}elseif (!isset($_SESSION['lang']) && isset($_COOKIE['lang'])) {
    $_SESSION['lang'] = $_COOKIE['lang'];
}

// デフォルト言語：日本語
define('DEFAULT_LANG', 'ja');
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? $_COOKIE['lang'] ?? DEFAULT_LANG;

// 言語ファイル読み込み
$lang_file = __DIR__ . "/lang/$lang.php";  //jaかenの読み込み
$messages = file_exists($lang_file) ? include($lang_file) : include(__DIR__ . "/lang/ja.php");

// 翻訳関数
function translate($key)
{
    global $messages;
    return $messages[$key] ?? $key;
}
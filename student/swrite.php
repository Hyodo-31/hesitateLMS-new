<?php
// DB書き込み
/**
 * Error reporting level
 */
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時

// lang.phpでセッションが開始されるため、個別のsession_startは不要
require_once "../lang.php";
require_once "../dbc.php";

$_SESSION["examflag"] = 0;

$MemberID = $_SESSION["MemberID"];

$file_name = sys_get_temp_dir()."/tem".$MemberID.".tmp";

print translate('swrite.php_14行目_こんにちは') . (isset($_SESSION["MemberName"]) ? htmlspecialchars($_SESSION["MemberName"], ENT_QUOTES, 'UTF-8') : '') . translate('swrite.php_14行目_さん');
if(is_file($file_name)){
    unlink($file_name);
}
?>
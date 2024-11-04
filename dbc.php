<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);   // 運用晁E
if(!isset($_SESSION)){ session_start(); }
?>
<?php


error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);  
//require "dbc_copy.php";
$dbnl="2019su1";  
$sv = "127.0.0.1";
$user = "root"; 
$pass = "8181saisaI";
$conn = mysqli_connect($sv,$user,$pass,$dbnl) or die("接続エラー1");

if(!$conn){
    echo "データベース接続失敗".PHP_EOL;
    echo "errno:". mysqli_connect_errno().PHP_EOL;
    echo "error:" . mysqli_connect_error().PHP_EOL;
}

//echo "データベース接続成功";

return $conn;

?>

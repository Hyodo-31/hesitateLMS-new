<?php
//error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
error_reporting(E_ALL);   // 運用晁E
ini_set('display_errors', 1); // エラーを画面に表示

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<?php

$dbnl="2019su1";  
$sv = "localhost";
$user = "root"; 
$pass = "Koihaki5143910";
$conn = mysqli_connect($sv,$user,$pass,$dbnl) or die("接続エラー1");

if(!$conn){
    die("データベース接続失敗:" . mysqli_connect_error());
}

//echo "データベース接続成功";

return $conn;


?>

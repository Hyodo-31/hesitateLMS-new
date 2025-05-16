<?php
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時


require "../dbc.php";

//linedataへの書き込み
//session_start();
$FName = "linedata";
$MemberID = $_SESSION["MemberID"];

//attempt取得
$attempt_query = "SELECT MAX(attempt) AS max_attempt FROM " . $FName . " WHERE UID = ? AND WID = ?";
$stmt = $conn->prepare($attempt_query);
$stmt->bind_param("ii", $MemberID, $_GET['param1']);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$max_attempt = $row['max_attempt'];
$attempt = $max_attempt ? $max_attempt + 1 : 1;
$_SESSION["attempt"] = $attempt;
$stmt->close();

echo $attempt;

?>
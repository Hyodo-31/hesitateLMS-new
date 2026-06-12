<?php
include '../lang.php';
require '../dbc.php';

if (!isset($_SESSION['MemberID'])) {
    echo 'ログインしてください。';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '無効なリクエストです。';
    exit();
}

$teacher_id = $_SESSION['MemberID'];
$student_uid = $_POST['student_uid'] ?? '';
$new_class_id = $_POST['class_id'] ?? '';

if ($student_uid === '' || $new_class_id === '') {
    echo '学習者と変更先グループ(クラス)を選択してください。';
    exit();
}

$stmt_allowed_student = $conn->prepare(
    "SELECT s.uid
     FROM students s
     JOIN classteacher ct ON s.ClassID = ct.ClassID
     WHERE s.uid = ? AND ct.TID = ?"
);
if (!$stmt_allowed_student) {
    echo '学習者確認の準備に失敗しました。';
    exit();
}
$stmt_allowed_student->bind_param("ss", $student_uid, $teacher_id);
$stmt_allowed_student->execute();
$result_allowed_student = $stmt_allowed_student->get_result();
$is_allowed_student = $result_allowed_student->num_rows > 0;
$stmt_allowed_student->close();

if (!$is_allowed_student) {
    echo '担当グループ(クラス)外の学習者は変更できません。';
    exit();
}

$stmt_allowed_class = $conn->prepare(
    "SELECT ClassID
     FROM classteacher
     WHERE ClassID = ? AND TID = ?"
);
if (!$stmt_allowed_class) {
    echo '変更先グループ(クラス)確認の準備に失敗しました。';
    exit();
}
$stmt_allowed_class->bind_param("is", $new_class_id, $teacher_id);
$stmt_allowed_class->execute();
$result_allowed_class = $stmt_allowed_class->get_result();
$is_allowed_class = $result_allowed_class->num_rows > 0;
$stmt_allowed_class->close();

if (!$is_allowed_class) {
    echo '担当していないグループ(クラス)には変更できません。';
    exit();
}

$stmt_update = $conn->prepare("UPDATE students SET ClassID = ? WHERE uid = ?");
if (!$stmt_update) {
    echo '所属グループ(クラス)変更の準備に失敗しました。';
    exit();
}

$stmt_update->bind_param("is", $new_class_id, $student_uid);
if ($stmt_update->execute()) {
    echo '所属グループ(クラス)を変更しました。<br>';
    echo '<a href="create-student-group.php">学習者グループ作成へ戻る</a>';
} else {
    echo '所属グループ(クラス)変更中にエラーが発生しました: ' . htmlspecialchars($stmt_update->error, ENT_QUOTES, 'UTF-8');
}

$stmt_update->close();
$conn->close();

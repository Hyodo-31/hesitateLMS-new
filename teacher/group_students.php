<?php
include '../lang.php';
require "../dbc.php";
// session_start(); // lang.phpでセッションは開始済み

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacher_id = $_SESSION["MemberID"];
    $clustersData = json_decode(file_get_contents('php://input'), true);

    if (empty($clustersData)) {
        echo translate('group_students.php_11行目_グループ化するデータがありません');
        exit();
    }

    $stmt_group = $conn->prepare("INSERT INTO `groups` (group_name, TID) VALUES (?, ?)");
    $stmt_member = $conn->prepare("INSERT INTO group_members (group_id, uid) VALUES (?, ?)");

    foreach ($clustersData as $cluster) {
        $group_name = $cluster['group_name'];
        $students = $cluster['students'];

        // グループを作成
        // echo $teacher_id;
        $stmt_group->bind_param("ss", $group_name, $teacher_id);
        if ($stmt_group->execute()) {
            echo sprintf(translate('group_students.php_24行目_グループ正常に作成されました'), htmlspecialchars($group_name)) . "<br>";
            $group_id = $stmt_group->insert_id;

            // 学生をグループに追加
            foreach ($students as $student_id) {
                $stmt_member->bind_param("ii", $group_id, $student_id);
                if ($stmt_member->execute()) {
                    echo sprintf(translate('group_students.php_30行目_学生がグループに追加されました'), htmlspecialchars($student_id), htmlspecialchars($group_name)) . "<br>";
                } else {
                    echo translate('group_students.php_32行目_学生追加に失敗しました') . $stmt_member->error . "<br>";
                }
            }
        } else {
            echo translate('group_students.php_36行目_グループ作成に失敗しました') . $stmt_group->error . "<br>";
        }
    }

    $stmt_group->close();
    $stmt_member->close();
    $conn->close();
}
?>
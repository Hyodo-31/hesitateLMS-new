<?php
include '../lang.php';
require "../dbc.php";
// session_start(); // lang.phpでセッションは開始済み

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $teacher_id = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;
    if (!$teacher_id) {
        http_response_code(401);
        echo 'ログイン情報が見つかりません。';
        exit();
    }

    $clustersData = json_decode(file_get_contents('php://input'), true);

    if (!is_array($clustersData) || empty($clustersData)) {
        http_response_code(400);
        echo translate('group_students.php_11行目_グループ化するデータがありません');
        exit();
    }

    $validatedClusters = [];
    foreach ($clustersData as $cluster) {
        if (!is_array($cluster)) {
            http_response_code(400);
            echo 'クラスタのデータ形式が正しくありません。';
            exit();
        }

        $group_name = trim((string)($cluster['group_name'] ?? ''));
        $students = $cluster['students'] ?? [];
        if ($group_name === '') {
            http_response_code(400);
            echo 'クラスタ名を入力してください。';
            exit();
        }
        if (!is_array($students) || empty($students)) {
            http_response_code(400);
            echo 'クラスタに学習者が登録されていません。';
            exit();
        }

        $validatedClusters[] = [
            'group_name' => $group_name,
            'students' => array_values(array_unique($students)),
        ];
    }

    $stmt_group = $conn->prepare("INSERT INTO `groups` (group_name, TID) VALUES (?, ?)");
    $stmt_member = $conn->prepare("INSERT INTO group_members (group_id, uid) VALUES (?, ?)");

    foreach ($validatedClusters as $cluster) {
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

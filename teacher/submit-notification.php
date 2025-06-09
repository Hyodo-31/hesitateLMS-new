<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('submit-notification.php_5行目_教師用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        // session_start(); // lang.phpでセッションスタート
        require "../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo"><?= translate('submit-notification.php_18行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <li><a href="teachertrue.php"><?= translate('submit-notification.php_21行目_ホーム') ?></a></li>
                <li><a href="#"><?= translate('submit-notification.php_22行目_コース管理') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('submit-notification.php_23行目_迷い推定・機械学習') ?></a></li>
                <li><a href="Analytics/studentAnalytics.php"><?= translate('submit-notification.php_24行目_学生分析') ?></a></li>
                <li><a href="Analytics/questionAnalytics.php"><?= translate('submit-notification.php_25行目_問題分析') ?></a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('submit-notification.php_32行目_ホーム') ?></a></li>
                <li><a href="#"><?= translate('submit-notification.php_33行目_コース管理') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('submit-notification.php_34行目_迷い推定・機械学習') ?></a></li>
                <li><a href="Analytics/studentAnalytics.php"><?= translate('submit-notification.php_35行目_学生分析') ?></a></li>
                <li><a href="Analytics/questionAnalytics.php"><?= translate('submit-notification.php_36行目_問題分析') ?></a></li>
            </ul>
        </aside>
        <main>
            <?php

                if ($_SERVER["REQUEST_METHOD"] == "POST") {

                    // フォームからデータを取得
                    $subject = $_POST['subject'];
                    $content = $_POST['content'];
                    $recipient_type = $_POST['recipient-type'];

                    // ログイン中のユーザーIDをセッションから取得
                    if (isset($_SESSION["MemberID"])) {
                        $sender_id = $_SESSION["MemberID"];  // ログイン中のユーザーID
                    } else {
                        die(translate('submit-notification.php_49行目_ログインしていません'));
                    }

                    // お知らせを通知テーブルに保存
                    $stmt = $conn->prepare("INSERT INTO notifications (subject, content, recipient_type, sender_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $subject, $content, $recipient_type, $sender_id);
                    $stmt->execute();

                    // 挿入された通知IDを取得
                    $notification_id = $stmt->insert_id;
                    $stmt->close();
                    // 2. 宛先に応じて notification_recipients にINSERTするための学生ID一覧を作る
                    $recipient_students = []; // ここに学生IDを集める

                    if ($recipient_type === "all") {
                        // 全学生を取得 (例)
                        $sqlAll = "SELECT uid FROM students";
                        $resAll = $conn->query($sqlAll);
                        while ($row = $resAll->fetch_assoc()) {
                            $recipient_students[] = $row['uid'];
                        }
                    } elseif ($recipient_type === "class" && isset($_POST['students'])) {
                        // 選択されたクラスIDをもとに、所属する学生IDを取得
                        $class_ids = $_POST['students'];  // 例: [1, 2] など
                        foreach ($class_ids as $class_id) {
                            // studentsテーブルや中間テーブルから「クラスIDが $class_id の学生ID」を取得
                            $sqlClass = "
                                SELECT s.uid
                                FROM students s
                                WHERE classID= ?";
                            $stmtClass = $conn->prepare($sqlClass);
                            $stmtClass->bind_param("i", $class_id);
                            $stmtClass->execute();
                            $resClass = $stmtClass->get_result();
                            while ($row = $resClass->fetch_assoc()) {
                                $recipient_students[] = $row['uid'];
                            }
                            $stmtClass->close();
                        }

                    } elseif ($recipient_type === "group" && isset($_POST['students'])) {
                        // 選択されたグループIDをもとに、所属する学生IDを取得
                        $group_ids = $_POST['students'];
                        foreach ($group_ids as $group_id) {
                            // こちらもDB設計に合わせて書き換え
                            $sqlGroup = "
                                SELECT uid
                                FROM group_members
                                WHERE group_id = ?";
                            $stmtGroup = $conn->prepare($sqlGroup);
                            $stmtGroup->bind_param("i", $group_id);
                            $stmtGroup->execute();
                            $resGroup = $stmtGroup->get_result();
                            while ($row = $resGroup->fetch_assoc()) {
                                $recipient_students[] = $row['uid'];
                            }
                            $stmtGroup->close();
                        }

                    } elseif ($recipient_type === "specific" && isset($_POST['students'])) {
                        // 特定の学生が配列でPOSTされる
                        $recipient_students = $_POST['students']; 
                    }

                    // 3. 重複を取り除く（同じ学生が複数クラスやグループに所属する場合など）
                    // 必要に応じて
                    $recipient_students = array_unique($recipient_students);

                    // 4. notification_recipients にINSERT
                    if (!empty($recipient_students)) {
                        $stmtRecipient = $conn->prepare("
                            INSERT INTO notification_recipients (notification_id, student_id)
                            VALUES (?, ?)");
                        foreach ($recipient_students as $student_id) {
                            $stmtRecipient->bind_param("ii", $notification_id, $student_id);
                            $stmtRecipient->execute();
                        }
                        $stmtRecipient->close();
                    }

                    echo translate('submit-notification.php_137行目_お知らせが保存されました');
                    $conn->close();
                }
            ?>

        </main>
    </div>
</body>
</html>
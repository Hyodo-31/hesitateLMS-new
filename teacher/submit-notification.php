<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教師用ダッシュボード</title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        session_start();
        require "../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo">英単語並べ替え問題LMS</div>
        <nav>
            <ul>
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="#">コース管理</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="#">コース管理</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
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
                        die("ログインしていません。");
                    }

                    // お知らせを通知テーブルに保存
                    $stmt = $conn->prepare("INSERT INTO notifications (subject, content, recipient_type, sender_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $subject, $content, $recipient_type, $sender_id);
                    $stmt->execute();

                    // 挿入された通知IDを取得
                    $notification_id = $stmt->insert_id;
                    $stmt->close();

                    // 特定の学生向けの場合
                    if ($recipient_type == "specific" && !empty($_POST['students'])) {
                        $students = $_POST['students'];

                        // 宛先情報を保存
                        $stmt = $conn->prepare("INSERT INTO notification_recipients (notification_id, student_id) VALUES (?, ?)");
                        foreach ($students as $student_id) {
                            $stmt->bind_param("ii", $notification_id, $student_id);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }

                    echo "お知らせが保存されました！";
                    $conn->close();
                }
            ?>

        </main>
    </div>
</body>
</html>

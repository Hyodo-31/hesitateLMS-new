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
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="#">コース管理</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
                <li><a href="register-student.php">新規学生登録</a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="#">コース管理</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
            </ul>
        </aside>
        <main>
            <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    //フォームから要素を取得
                    $student_id = $_POST['student_id'];
                    $password = $_POST['password'];
                    $username = $_POST['username'];
                    $class_id = $_POST['class_id'];
                    $toeic_level = $_POST['toeic_level'];
                    $eiken_level = $_POST['eiken_level'];

                    //SQL文を作成   
                    $stmt = $conn->prepare("INSERT INTO students (uid, Pass, Name, ClassID, toeic_level, eiken_level) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $student_id, $password, $username, $class_id, $toeic_level, $eiken_level);
                    // クエリ実行と結果表示
                    if ($stmt->execute()) {
                        echo "学生登録が完了しました";
                    } else {
                        echo "登録中にエラーが発生しました: " . $stmt->error;
                    }

                    $stmt->close();
                }
            ?>

        </main>
    </div>
</body>
</html>

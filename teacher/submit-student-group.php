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
                <li><a href="register-student.php">新規学生登録</a></li>
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
                // フォームデータを取得
                $group_name = $_POST['group_name'];
                $teacher_id = $_SESSION["MemberID"];
                $selected_students = $_POST['students'];
                echo "グループ名: " . $group_name . "<br>";
                echo "学生リスト: " . implode(", ", $selected_students) . "<br>";
                echo "教師ID: " . $teacher_id . "<br>";
                
                // グループを作成
                $stmt = $conn->prepare("INSERT INTO groups (group_name, TID) VALUES (?, ?)");
                $stmt->bind_param("ss", $group_name, $teacher_id);
                if($stmt->execute()) {
                    echo "グループが正常に作成されました<br>";                    
                }else{
                    echo "グループ作成に失敗しました．" . $stmt->error."<br>";
                }
                $group_id = $stmt->insert_id; // 作成したグループのIDを取得
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO group_members (group_id, uid) VALUES (?, ?)");
                foreach ($selected_students as $student_id) {
                    echo $student_id;
                    $stmt->bind_param("is", $group_id, $student_id);
                    if($stmt->execute()) {
                        echo $student_id . "がグループに追加されました。<br>";
                    }else{
                        echo "学生追加に失敗しました。" . $stmt->error . "<br>";
                    }
                }
                $stmt->close();
                // データベース接続を閉じる
                $conn->close();
            ?>

        </main>
    </div>
</body>
</html>

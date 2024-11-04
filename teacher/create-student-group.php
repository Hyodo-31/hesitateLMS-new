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
            <div class="content-class">
            <h2>学生グループ作成</h2>
            <form action="submit-student-group.php" method="post">
                <label for="group_name">グループ名:</label>
                    <input type="text" id="group_name" name="group_name" required>
                    <br><br>
                    
                    <label>学生リスト:</label>
                    <ul class="student-list">
                        <!-- PHPで学生リストを取得して表示 -->
                        <?php
                            $result = $conn->query("SELECT uid, Name FROM students");
                            while ($row = $result->fetch_assoc()) {
                                echo "<li><label><input type='checkbox' name='students[]' value='{$row['uid']}'> {$row['Name']}</label></li>";
                            }

                            $result->free();
                        ?>
                    </ul>
                    
                <button type="submit">グループを作成</button>
            </form>
            </div>
            <div class = "content-class">
            <h2>現在のグループ</h2>
            <ul class="group-list">
                <?php
                    // 現在のグループを取得して表示
                    $group_result = $conn->query("SELECT group_id, group_name FROM groups WHERE TID = '{$_SESSION['MemberID']}'");
                    while ($group = $group_result->fetch_assoc()) {
                        echo "<li>";
                        echo "<strong>{$group['group_name']}</strong>";
                        
                        // メンバーリストの取得
                        $member_result = $conn->query("SELECT students.Name FROM group_members JOIN students ON group_members.uid = students.uid WHERE group_members.group_id = {$group['group_id']}");
                        echo "<ul>";
                        while ($member = $member_result->fetch_assoc()) {
                            echo "<li>{$member['Name']}</li>";
                        }
                        echo "</ul>";
                        $member_result->free();

                        // 削除ボタンを表示
                        echo "<form action='delete-student-group.php' method='post' style='display:inline;'>
                                <input type='hidden' name='group_id' value='{$group['group_id']}'>
                                <button type='submit' onclick='return confirm(\"このグループを削除してよろしいですか？\");'>削除</button>
                              </form>";
                        echo "</li>";
                    }
                    $group_result->free();
                    $conn->close();
                ?>
            </ul>
            </div>
        </main>
    </div>
</body>
</html>

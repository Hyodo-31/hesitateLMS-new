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
                <li><a href="#">ホーム</a></li>
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
            <div class="notifications">
                <div class="content-title">
                    <h2>お知らせ</h2>
                </div>
                <div class = "notify">
                    <div class = "notify-title">
                        お知らせ一覧
                    </div>
                    <p>コースAの新しい課題が提出されました。</p>
                </div>
                <div  id = "notify-form">
                    <!--ここに件名とお知らせ内容を登録できるフォームを作成-->
                    <h2>お知らせ登録フォーム</h2>
                    <form action="submit-notification.php" method="POST">
                        <!-- 宛先選択 -->
                        <div>
                            <label for="recipient-type">宛先:</label>
                            <select id="recipient-type" name="recipient-type" onchange="toggleStudentSelection()" required>
                                <option value="all">全員</option>
                                <option value="specific">特定の学生</option>
                            </select>
                        </div>

                        <!-- 特定の学生を選択するためのチェックボックス (デフォルトで非表示) -->
                        <div id="student-selection" style="display: none;">
                            <label>特定の学生を選択:</label><br>
                            <input type="checkbox" id="student1" name="students[]" value="student1">
                            <label for="student1">学生1</label><br>
                            <input type="checkbox" id="student2" name="students[]" value="student2">
                            <label for="student2">学生2</label><br>
                            <input type="checkbox" id="student3" name="students[]" value="student3">
                            <label for="student3">学生3</label><br>
                            <!-- 必要に応じて学生リストを追加 -->
                        </div>

                        <div>
                            <label for="subject">件名:</label>
                            <input type="text" id="subject" name="subject" required placeholder="件名を入力してください">
                        </div>
                        <div>
                            <label for="content">お知らせ内容:</label>
                            <textarea id="content" name="content" rows="4" required placeholder="お知らせ内容を入力してください"></textarea>
                        </div>
                        <div>
                            <button type="submit">お知らせを登録</button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                function toggleStudentSelection() {
                    const recipientType = document.getElementById('recipient-type').value;
                    const studentSelection = document.getElementById('student-selection');
                    
                    // 「特定の学生」を選んだ場合、学生選択を表示
                    if (recipientType === 'specific') {
                        studentSelection.style.display = 'block';
                    } else {
                        studentSelection.style.display = 'none';
                    }
                }
                </script>

        </main>
    </div>
</body>
</html>

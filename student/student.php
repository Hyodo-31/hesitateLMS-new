<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教師用ダッシュボード</title>
    <!--
    ここにcssのスタイルシートを入れる
-->
    <link rel="stylesheet" href="../style/student_style.css">

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
                <li><a href="../logout.php">ログアウト</a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="#">ホーム</a></li>
                <li><a href="../logout.php">ログアウト</a></li>
            </ul>
        </aside>
        <main>
            <!-- ここにコンテンツを入れる -->
            <h1>学生用LMS</h1>
            <!--お知らせのコンテンツを入れる^-^-->
            <div class = "news">
                <h2>お知らせ一覧</h2>
                <?php
                    $studentId = $_SESSION["MemberID"];

                    // 指定ユーザが受信する通知を取得
                    $sql = "
                    SELECT n.id, n.subject, n.created_at
                    FROM notifications n
                    JOIN notification_recipients nr ON n.id = nr.notification_id
                    WHERE nr.student_id = ?
                    ORDER BY n.created_at DESC
                    ";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('i', $studentId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                ?>
                <!-- 件名だけをスクロール表示する領域 -->
                <div class="news-scroll">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <div class="news-item" data-id="<?php echo $row['id']; ?>">
                                <!-- 件名を表示 -->
                                <h3 class="news-title">
                                    <?php echo htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8'); ?>
                                </h3>
                                <!-- 日付などを表示したい場合はここに追記できます -->
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>現在お知らせはありません。</p>
                    <?php endif; ?>
                    <?php $stmt->close(); ?>
                </div>
            </div>
            
            <div class = "content">
                <!--課題ページへのリンク-->
                <!--
                <form action = "./assignment.php" method = "get">
                    <button type= "submit">課題</button>
                </form>
                    -->
                <!--テストページへのリンク-->
                <form action = "./test.php" method = "get">
                    <button type= "submit">テスト</button>
                </form>
                <!--成績管理ページへのリンク-->
                <form action = "./Analytics/analytics.php" method = "get">
                    <button type= "submit">成績管理</button>
                </form>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const newsItems = document.querySelectorAll('.news-item');
                    newsItems.forEach(item => {
                        item.addEventListener('dblclick', function() {
                            const id = this.getAttribute('data-id');
                            // notification_detail.php に遷移して詳細を表示
                            window.location.href = 'notification_detail.php?id=' + id;
                        });
                    });
                });
            </script>

        </main>
    </div>
</body>
</html>

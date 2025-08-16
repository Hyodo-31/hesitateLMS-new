<?php
// lang.phpでセッションが開始されるため、個別のsession_startは不要
include '../lang.php';
require "../dbc.php";
// セッション変数をクリアする（必要に応じて）
unset($_SESSION['conditions']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('student.php_5行目_学生用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/student_style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <header>
        <div class="logo"><?= translate('student.php_23行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="#"><?= translate('student.php_26行目_ホーム') ?></a></li> -->
                <li><a href="../logout.php"><?= translate('student.php_27行目_ログアウト') ?></a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="#"><?= translate('student.php_26行目_ホーム') ?></a></li>
                <li><a href="../logout.php"><?= translate('student.php_27行目_ログアウト') ?></a></li>
            </ul>
        </aside>
        <main>
            <h1><?= translate('student.php_38行目_学生用LMS') ?></h1>
            <div class = "news">
                <h2><?= translate('student.php_41行目_お知らせ一覧') ?></h2>
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
                <div class="news-scroll">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <div class="news-item" data-id="<?php echo $row['id']; ?>">
                                <h3 class="news-title">
                                    <?php echo htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8'); ?>
                                </h3>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p><?= translate('student.php_66行目_現在お知らせはありません') ?></p>
                    <?php endif; ?>
                    <?php $stmt->close(); ?>
                </div>
            </div>
            
            <div class = "content">
                <form action = "./test.php" method = "get">
                    <button type= "submit"><?= translate('student.php_76行目_テスト') ?></button>
                </form>
                <form action = "Analytics/analytics.php" method = "get">
                    <button type= "submit"><?= translate('student.php_80行目_成績管理') ?></button>
                </form>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const newsItems = document.querySelectorAll('.news-item');
                    newsItems.forEach(item => {
                        item.addEventListener('dblclick', function() {
                            const id = this.getAttribute('data-id');
                            window.location.href = 'notification_detail.php?id=' + id;
                        });
                    });
                });
            </script>

        </main>
    </div>
</body>
</html>
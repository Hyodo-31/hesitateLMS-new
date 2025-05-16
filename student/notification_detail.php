<?php
session_start();
require '../dbc.php';

// id が渡されていない場合のエラー処理
if (!isset($_GET['id'])) {
    echo "お知らせIDが指定されていません。";
    exit;
}

$notification_id = (int)$_GET['id'];

// DBから該当するお知らせ情報を取得
$sql = "
    SELECT id, subject, content, created_at
    FROM notifications
    WHERE id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $notification_id);
$stmt->execute();
$result = $stmt->get_result();
$detail = $result->fetch_assoc();

$stmt->close();
$conn->close();

// 取得できなかった場合のエラー処理
if (!$detail) {
    echo "該当するお知らせが見つかりません。";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>お知らせ詳細</title>
    <link rel="stylesheet" href="../style/student_style.css">
</head>
<body>
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
        <h1>お知らせ詳細</h1>
        <div class="notify">
            <h2 class="notification-title">
                <?php echo htmlspecialchars($detail['subject'], ENT_QUOTES, 'UTF-8'); ?>
            </h2>
            <p class="notification-content">
                <?php
                  // 改行を<br>に変換 & HTMLエスケープ
                  echo nl2br(htmlspecialchars($detail['content'], ENT_QUOTES, 'UTF-8'));
                ?>
            </p>
            <p class="notification-time">
                投稿日時: <?php echo htmlspecialchars($detail['created_at'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
    </main>
</div>

</body>
</html>

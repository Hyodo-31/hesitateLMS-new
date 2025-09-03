<?php
// lang.phpでセッションが開始されるため、個別のsession_startは不要
include '../lang.php';
require '../dbc.php';

// id が渡されていない場合のエラー処理
if (!isset($_GET['id'])) {
    echo translate('notification_detail.php_6行目_お知らせIDが指定されていません');
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
    echo translate('notification_detail.php_28行目_該当するお知らせが見つかりません');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= translate('notification_detail.php_33行目_お知らせ詳細') ?></title>
    <link rel="stylesheet" href="../style/student_style.css">
</head>
<body>
<header>
    <div class="logo"><?= translate('notification_detail.php_38行目_英単語並べ替え問題LMS') ?></div>
    <nav>
        <ul>
            <!-- <li><a href="teachertrue.php"><?= translate('notification_detail.php_41行目_ホーム') ?></a></li> -->
            <!-- <li><a href="#"><?= translate('notification_detail.php_42行目_コース管理') ?></a></li> -->
            <!-- <li><a href="machineLearning_sample.php"><?= translate('notification_detail.php_43行目_迷い推定・機械学習') ?></a></li> -->
            <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('notification_detail.php_44行目_学生分析') ?></a></li> -->
            <!-- 
            <li><a href="Analytics/questionAnalytics.php"><?= translate('notification_detail.php_45行目_問題分析') ?></a></li> -->
            <li><a href="../logout.php"><?= translate('student.php_27行目_ログアウト') ?></a></li>
        </ul>
    </nav>
</header>

<div class="container">
    <aside>
        <ul>
            <li><a href="student.php"><?= translate('notification_detail.php_41行目_ホーム') ?></a></li>
            <!-- <li><a href="#"><?= translate('notification_detail.php_42行目_コース管理') ?></a></li> -->
            <!-- <li><a href="machineLearning_sample.php"><?= translate('notification_detail.php_43行目_迷い推定・機械学習') ?></a></li> -->
            <li><a href="Analytics/analytics.php"><?= translate('test.php_56行目_成績管理') ?></a></li>
            <li><a href="test.php"><?= translate('student.php_76行目_テスト') ?></a></li>
        </ul>
    </aside>

    <main>
        <h1><?= translate('notification_detail.php_60行目_お知らせ詳細') ?></h1>
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
                <?= translate('notification_detail.php_68行目_投稿日時') ?>: <?php echo htmlspecialchars($detail['created_at'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
    </main>
</div>

</body>
</html>
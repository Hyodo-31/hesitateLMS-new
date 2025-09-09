<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('notification-detail.php_5行目_教師用ダッシュボード') ?></title>
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
        <div class="logo"><?= translate('notification-detail.php_18行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="teachertrue.php"><?= translate('notification-detail.php_21行目_ホーム') ?></a></li> -->
                <!-- <li><a href="#"><?= translate('notification-detail.php_22行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('notification-detail.php_23行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('notification-detail.php_24行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('notification-detail.php_25行目_問題分析') ?></a></li> -->
                <!-- <li><a href="register-student.php"><?= translate('notification-detail.php_26行目_新規学生登録') ?></a></li> -->
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('notification-detail.php_33行目_ホーム') ?></a></li>
                <!-- <li><a href="#"><?= translate('notification-detail.php_34行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('notification-detail.php_35行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('notification-detail.php_36行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('notification-detail.php_37行目_問題分析') ?></a></li> -->
            </ul>
        </aside>
        <main>
            <div class="notifications">
                <h2><?= translate('notification-detail.php_41行目_お知らせ') ?></h2>
                    <div class = "notify">
                    <?php
                        // URLから通知IDを取得
                        $notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

                        // お知らせが見つからない場合のメッセージ
                        if ($notification_id === 0) {
                            echo "<p>" . translate('notification-detail.php_48行目_お知らせが見つかりません') . "</p>";
                            exit;
                        }

                        // データベースからお知らせの詳細を取得
                        $stmt = $conn->prepare("SELECT subject, content, created_at FROM notifications WHERE id = ?");
                        $stmt->bind_param("i", $notification_id);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $notification = $result->fetch_assoc();
                            echo "<h2>" . translate('notification-detail.php_59行目_件名') . ":{$notification['subject']}</h2>";
                            echo "<p>" . translate('notification-detail.php_60行目_日時') . ": {$notification['created_at']}</p>";
                            echo "<h2>" . translate('notification-detail.php_61行目_メッセージ本文') . "</h2>";
                            echo "<p>{$notification['content']}</p>";
                        } else {
                            echo "<p>" . translate('notification-detail.php_64行目_お知らせが見つかりません') . "</p>";
                        }

                        // データベース接続を閉じる
                        $stmt->close();
                        $conn->close();
                    ?>  
                </div>
            </div>
            

        </main>
    </div>
</body>
</html>
<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('delete-student-group.php_5行目_教師用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        // session_start(); // lang.phpでセッションは開始済み
        require "../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo"><?= translate('delete-student-group.php_18行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <li><a href="teachertrue.php"><?= translate('delete-student-group.php_21行目_ホーム') ?></a></li>
                <li><a href="#"><?= translate('delete-student-group.php_22行目_コース管理') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('delete-student-group.php_23行目_迷い推定・機械学習') ?></a></li>
                <li><a href="Analytics/studentAnalytics.php"><?= translate('delete-student-group.php_24行目_学生分析') ?></a></li>
                <li><a href="Analytics/questionAnalytics.php"><?= translate('delete-student-group.php_25行目_問題分析') ?></a></li>
                <li><a href="register-student.php"><?= translate('delete-student-group.php_26行目_新規学生登録') ?></a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('delete-student-group.php_32行目_ホーム') ?></a></li>
                <li><a href="#"><?= translate('delete-student-group.php_33行目_コース管理') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('delete-student-group.php_34行目_迷い推定・機械学習') ?></a></li>
                <li><a href="Analytics/studentAnalytics.php"><?= translate('delete-student-group.php_35行目_学生分析') ?></a></li>
                <li><a href="Analytics/questionAnalytics.php"><?= translate('delete-student-group.php_36行目_問題分析') ?></a></li>
            </ul>
        </aside>
        <main>
            <?php
                // 削除対象のグループIDを取得
                $group_id = $_POST['group_id'];

                // `group_members`から該当するグループのメンバーを削除
                $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();

                // `groups`から該当するグループを削除
                $stmt = $conn->prepare("DELETE FROM `groups` WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();

                $conn->close();

                // 削除後のリダイレクト
                header("Location: teachertrue.php");
                exit;
            ?>
        </main>
    </div>
</body>
</html>
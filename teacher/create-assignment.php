<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('create-assignment.php_7行目_教育用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        //session_start();
        require "../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo"><?= translate('create-assignment.php_20行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <li><a href="teachertrue.php"><?= translate('create-assignment.php_23行目_ホーム') ?></a></li>
                <li><a href="#"><?= translate('create-assignment.php_24行目_コース管理') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('create-assignment.php_25行目_迷い推定・機械学習') ?></a></li>
                <li><a href="Analytics/studentAnalytics.php"><?= translate('create-assignment.php_26行目_学生分析') ?></a></li>
                <li><a href="Analytics/questionAnalytics.php"><?= translate('create-assignment.php_27行目_問題分析') ?></a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('create-assignment.php_34行目_ホーム') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('create-assignment.php_35行目_迷い推定・機械学習') ?></a></li>
                <li><a href="Analytics/studentAnalytics.php"><?= translate('create-assignment.php_36行目_学生分析') ?></a></li>
                <li><a href="Analytics/questionAnalytics.php"><?= translate('create-assignment.php_37行目_問題分析') ?></a></li>
            </ul>
        </aside>
        <main>
            <!-- ここにコンテンツを入れる -->
             <div class = "create-assignment">
                <?= translate('create-assignment.php_42行目_ここに課題を作成するコンテンツをいれる') ?>
             </div>
        </main>
    </div>
</body>
</html>

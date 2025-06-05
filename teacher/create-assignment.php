<?php include 'lang.php'; ?>
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
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
            </ul>
        </aside>
        <main>
            <!-- ここにコンテンツを入れる -->
             <div class = "create-assignment">
                ここに課題を作成するコンテンツをいれる
             </div>
        </main>
    </div>
</body>
</html>

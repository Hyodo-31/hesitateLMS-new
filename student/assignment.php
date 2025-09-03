<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('assignment.php_5行目_教師用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/student_style.css">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        // session_start(); // lang.phpでセッション開始済み
        require "../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo"><?= translate('assignment.php_21行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <li><a href="student.php"><?= translate('assignment.php_24行目_ホーム') ?></a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="student.php"><?= translate('assignment.php_30行目_ホーム') ?></a></li>
            </ul>
        </aside>
        <main>
            <?= translate('assignment.php_34行目_ここは課題のページ') ?>
        </main>
    </div>
</body>
</html>
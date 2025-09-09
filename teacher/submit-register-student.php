<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('submit-register-student.php_5行目_教師用ダッシュボード') ?></title>
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
        <div class="logo"><?= translate('submit-register-student.php_18行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="teachertrue.php"><?= translate('submit-register-student.php_21行目_ホーム') ?></a></li> -->
                <!-- <li><a href="#"><?= translate('submit-register-student.php_23行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('submit-register-student.php_24行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('submit-register-student.php_25行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('submit-register-student.php_26行目_問題分析') ?></a></li> -->
                <!-- <li><a href="register-student.php"><?= translate('submit-register-student.php_27行目_新規学生登録') ?></a></li> -->
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('submit-register-student.php_33行目_ホーム') ?></a></li>
                <li><a href="register-classteacher.php"><?= translate('register-student.php_30行目_教師-クラス登録') ?></a></li>
                <!-- <li><a href="#"><?= translate('submit-register-student.php_35行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('submit-register-student.php_36行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('submit-register-student.php_37行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('submit-register-student.php_38行目_問題分析') ?></a></li> -->
            </ul>
        </aside>
        <main>
            <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    //フォームから要素を取得
                    $student_id = $_POST['student_id'];
                    $password = $_POST['password'];
                    $username = $_POST['username'];
                    $class_id = $_POST['class_id'];
                    $toeic_level = $_POST['toeic_level'];
                    $eiken_level = $_POST['eiken_level'];

                    //SQL文を作成   
                    $stmt = $conn->prepare("INSERT INTO students (uid, Pass, Name, ClassID, toeic_level, eiken_level) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $student_id, $password, $username, $class_id, $toeic_level, $eiken_level);
                    // クエリ実行と結果表示
                    if ($stmt->execute()) {
                        echo translate('submit-register-student.php_57行目_学生登録が完了しました');
                    } else {
                        echo translate('submit-register-student.php_59行目_登録中にエラーが発生しました') . ": " . $stmt->error;
                    }

                    $stmt->close();
                }
            ?>

        </main>
    </div>
</body>
</html>
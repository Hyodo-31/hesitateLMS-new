<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('submit-student-group.php_5行目_教師用ダッシュボード') ?></title>
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
        <div class="logo"><?= translate('submit-student-group.php_18行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="teachertrue.php"><?= translate('submit-student-group.php_21行目_ホーム') ?></a></li> -->
                <!-- <li><a href="#"><?= translate('submit-student-group.php_22行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('submit-student-group.php_23行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('submit-student-group.php_24行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('submit-student-group.php_25行目_問題分析') ?></a></li> -->
                <!-- <li><a href="register-student.php"><?= translate('submit-student-group.php_26行目_新規学生登録') ?></a></li> -->
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('submit-student-group.php_32行目_ホーム') ?></a></li>
                <!-- <li><a href="#"><?= translate('submit-student-group.php_33行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('submit-student-group.php_34行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('submit-student-group.php_35行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('submit-student-group.php_36行目_問題分析') ?></a></li> -->
            </ul>
        </aside>
        <main>
            <?php
                // フォームデータを取得
                $group_name = $_POST['group_name'];
                $teacher_id = $_SESSION["MemberID"];
                $selected_students = $_POST['students'];
                echo translate('submit-student-group.php_44行目_グループ名') . ": " . htmlspecialchars($group_name, ENT_QUOTES, 'UTF-8') . "<br>";
                echo translate('submit-student-group.php_45行目_学生リスト') . ": " . htmlspecialchars(implode(", ", $selected_students), ENT_QUOTES, 'UTF-8') . "<br>";
                echo translate('submit-student-group.php_46行目_教師ID') . ": " . htmlspecialchars($teacher_id, ENT_QUOTES, 'UTF-8') . "<br>";
                
                // グループを作成
                $stmt = $conn->prepare("INSERT INTO `groups` (group_name, TID) VALUES (?, ?)");
                $stmt->bind_param("ss", $group_name, $teacher_id);
                if($stmt->execute()) {
                    echo translate('submit-student-group.php_51行目_グループが正常に作成されました') . "<br>";                    
                }else{
                    echo translate('submit-student-group.php_53行目_グループ作成に失敗しました') . $stmt->error."<br>";
                }
                $group_id = $stmt->insert_id; // 作成したグループのIDを取得
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO group_members (group_id, uid) VALUES (?, ?)");
                foreach ($selected_students as $student_id) {
                    echo htmlspecialchars($student_id, ENT_QUOTES, 'UTF-8');
                    $stmt->bind_param("is", $group_id, $student_id);
                    if($stmt->execute()) {
                        echo " " . translate('submit-student-group.php_61行目_がグループに追加されました') . "<br>";
                    }else{
                        echo " " . translate('submit-student-group.php_63行目_学生追加に失敗しました') . $stmt->error . "<br>";
                    }
                }
                $stmt->close();
                // データベース接続を閉じる
                $conn->close();
            ?>

        </main>
    </div>
</body>
</html>
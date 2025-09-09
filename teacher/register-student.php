<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('register-student.php_5行目_教師用ダッシュボード') ?></title>
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
        <div class="logo"><?= translate('register-student.php_18行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="teachertrue.php"><?= translate('register-student.php_21行目_ホーム') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('register-student.php_22行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="register-student.php"><?= translate('register-student.php_23行目_新規学生登録') ?></a></li> -->
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('register-student.php_29行目_ホーム') ?></a></li>
                <li><a href="register-classteacher.php"><?= translate('register-student.php_30行目_教師-クラス登録') ?></a></li>
                <!-- <li><a href="register-student.php"><?= translate('register-student.php_31行目_新規学生登録') ?></a></li> -->
            </ul>
        </aside>
        <main>
            <div class = "content-class">
                <form action="submit-register-student.php" method="post">
                    <!-- ID（8桁の一意の番号） -->
                    <div id = "studentID">
                        <label for="student_id"><?= translate('register-student.php_37行目_学生ID(8桁)') ?></label>
                        <input type="text" id="student_id" name="student_id" maxlength="8" required>
                    </div>

                    <!-- パスワード -->
                    <div id = "password">
                        <label for="password"><?= translate('register-student.php_42行目_パスワード') ?></label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <!-- ユーザー名 -->
                    <div id = "username">
                        <label for="username"><?= translate('register-student.php_47行目_ユーザー名') ?></label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <!-- 授業クラス（教師のデータベースと連動した授業ID） -->
                    <div id = "class">
                        <label for="class_id"><?= translate('register-student.php_52行目_授業クラス') ?></label>
                        <select id="class_id" name="class_id" required>
                            <!-- ここに教師のデータベースから取得した授業IDを表示する -->
                            <?php 
                                $teacher_id = $_SESSION['MemberID'];
                                $sql = "SELECT ct.ClassID,c.ClassName
                                        FROM ClassTeacher ct
                                        JOIN classes c ON ct.ClassID = c.ClassID
                                        WHERE ct.TID = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("s", $teacher_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    $class_id = $row['ClassID'];
                                    $class_name = $row['ClassName'];
                                    echo "<option value='{$class_id}'>" . translate('register-student.php_57行目_授業ID') . ": {$class_id} - {$class_name}</option>";
                                }
                                $stmt->close();
                            ?>
                            <!-- 他のクラスも追加 -->
                            </select>
                    </div>
                    
                    <!-- TOEICレベル（任意選択） -->
                    <div id = "toeic_level">
                        <label for="toeic_level"><?= translate('register-student.php_66行目_TOEICレベル(任意選択)') ?></label>
                        <select id="toeic_level" name="toeic_level">
                            <option value=""><?= translate('register-student.php_68行目_選択しない') ?></option>
                            <option value="400"><?= translate('register-student.php_69行目_400点台') ?></option>
                            <option value="500"><?= translate('register-student.php_70行目_500点台') ?></option>
                            <option value="600"><?= translate('register-student.php_71行目_600点台') ?></option>
                            <option value="700"><?= translate('register-student.php_72行目_700点台') ?></option>
                            <option value="800"><?= translate('register-student.php_73行目_800点以上') ?></option>
                        </select>
                    </div>
                    
                    <!-- 英検レベル（任意選択） -->
                    <div id = "eiken_level">
                        <label for="eiken_level"><?= translate('register-student.php_79行目_英検レベル(任意選択)') ?></label>
                        <select id="eiken_level" name="eiken_level">
                            <option value=""><?= translate('register-student.php_81行目_選択しない') ?></option>
                            <option value="pre2"><?= translate('register-student.php_82行目_英検準二級') ?></option>
                            <option value="2"><?= translate('register-student.php_83行目_英検二級') ?></option>
                            <option value="pre1"><?= translate('register-student.php_84行目_英検準一級') ?></option>
                            <option value="1"><?= translate('register-student.php_85行目_英検一級') ?></option>
                        </select>
                    </div>
                    
                    <input type="submit" value="<?= translate('register-student.php_89行目_登録') ?>">
                </form>
            </div>
        </main>
    </div>
</body>
</html>
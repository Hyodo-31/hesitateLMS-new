<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('register-classteacher.php_5行目_クラス管理') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
</head>
<body>
    <?php
        // session_start(); は lang.php で実行済み
        require "../dbc.php";
        // 教師がログインしているか確認
        if (!isset($_SESSION['MemberID'])) {
            // ログインページにリダイレクトするか、エラーを表示
            // header("Location: ../login.php"); // 仮のログインページ
            echo translate('register-classteacher.php_15行目_ログインしてください。'); // 動作確認用
            exit();
        }
        $teacher_id = $_SESSION['MemberID'];
    ?>
    <header>
        <div class="logo"><?= translate('register-classteacher.php_21行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="teachertrue.php"><?= translate('register-classteacher.php_24行目_ホーム') ?></a></li> -->
                <!-- <li><a href="register-student.php"><?= translate('register-classteacher.php_25行目_新規学生登録') ?></a></li> -->
                <!-- <li><a href="register-classteacher.php"><?= translate('register-classteacher.php_26行目_クラス管理') ?></a></li> -->
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('register-classteacher.php_32行目_ホーム') ?></a></li>
                <li><a href="register-student.php"><?= translate('teachertrue.php_45行目_新規学生登録') ?></a></li>
                <!-- <li><a href="register-classteacher.php"><?= translate('register-classteacher.php_34行目_クラス管理') ?></a></li> -->
            </ul>
        </aside>
        <main>
            <div class = "content-class">
                <h2><?= translate('register-classteacher.php_38行目_クラス管理') ?></h2>
                <p><?= translate('register-classteacher.php_39行目_ログイン中の教師ID') ?>: <?= htmlspecialchars($teacher_id, ENT_QUOTES, 'UTF-8') ?></p>
                
                <form action="submit-register-classteacher.php" method="post">
                    <!-- 新しいクラスを作成 -->
                    <div id="create_class">
                        <h3><?= translate('register-classteacher.php_44行目_新しいクラスを作成') ?></h3>
                        <label for="new_class_name"><?= translate('register-classteacher.php_45行目_クラス名') ?></label>
                        <input type="text" id="new_class_name" name="new_class_name" placeholder="<?= translate('register-classteacher.php_46行目_クラス名を入力してください') ?>">
                    </div>

                    <hr style="margin: 20px 0;">

                    <!-- 担当クラスの選択 -->
                    <div id="class_selection">
                        <h3><?= translate('register-classteacher.php_52行目_担当クラスの選択') ?></h3>
                        <p><?= translate('register-classteacher.php_53行目_担当する全てのクラスにチェックを入れてください。') ?></p>
                        <div class="checkbox-group">
                            <?php
                                // この教師に既に割り当てられているクラスIDを取得
                                $assigned_classes = [];
                                $sql_assigned = "SELECT ClassID FROM classteacher WHERE TID = ?";
                                $stmt_assigned = $conn->prepare($sql_assigned);
                                $stmt_assigned->bind_param("s", $teacher_id);
                                $stmt_assigned->execute();
                                $result_assigned = $stmt_assigned->get_result();
                                while ($row_assigned = $result_assigned->fetch_assoc()) {
                                    $assigned_classes[] = $row_assigned['ClassID'];
                                }
                                $stmt_assigned->close();

                                // classesテーブルから全てのクラスを取得
                                $sql = "SELECT ClassID, ClassName FROM classes ORDER BY ClassID";
                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $checked = in_array($row['ClassID'], $assigned_classes) ? 'checked' : '';
                                        echo "<div><input type='checkbox' id='class_{$row['ClassID']}' name='class_ids[]' value='{$row['ClassID']}' {$checked}>";
                                        echo "<label for='class_{$row['ClassID']}'>{$row['ClassName']} (ID: {$row['ClassID']})</label></div>";
                                    }
                                } else {
                                    echo "<p>" . translate('register-classteacher.php_80行目_登録されているクラスがありません。') . "</p>";
                                }
                            ?>
                        </div>
                    </div>
                    
                    <input type="submit" value="<?= translate('register-classteacher.php_85行目_更新') ?>">
                </form>
            </div>
        </main>
    </div>
</body>
</html>
<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('submit-register-classteacher.php_5行目_クラス管理処理') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
</head>
<body>
    <?php
        // session_start(); は lang.php で実行済み
        require "../dbc.php";
    ?>
    <header>
        <div class="logo"><?= translate('submit-register-classteacher.php_14行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="teachertrue.php"><?= translate('submit-register-classteacher.php_17行目_ホーム') ?></a></li> -->
                <!-- <li><a href="register-student.php"><?= translate('submit-register-classteacher.php_18行目_新規学生登録') ?></a></li> -->
                <!-- <li><a href="register-classteacher.php"><?= translate('submit-register-classteacher.php_19行目_クラス管理') ?></a></li> -->
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('submit-register-classteacher.php_25行目_ホーム') ?></a></li>
                <li><a href="register-student.php"><?= translate('teachertrue.php_45行目_新規学生登録') ?></a></li>
                <!-- <li><a href="register-classteacher.php"><?= translate('submit-register-classteacher.php_27行目_クラス管理') ?></a></li> -->
            </ul>
        </aside>
        <main>
            <div class="content-class">
                <h2><?= translate('submit-register-classteacher.php_31行目_登録結果') ?></h2>
                <?php
                    // 教師がログインしているか確認
                    if (!isset($_SESSION['MemberID'])) {
                        echo "<p>" . translate('submit-register-classteacher.php_34行目_エラー: ログインしていません。') . "</p>";
                    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $teacher_id = $_SESSION['MemberID'];
                        $new_class_name = trim($_POST['new_class_name']);
                        $selected_class_ids = isset($_POST['class_ids']) ? $_POST['class_ids'] : [];

                        $conn->begin_transaction(); // トランザクション開始

                        try {
                            // 1. 新しいクラスの作成処理
                            if (!empty($new_class_name)) {
                                $stmt_create = $conn->prepare("INSERT INTO classes (ClassName, TID) VALUES (?, ?)");
                                $stmt_create->bind_param("ss", $new_class_name, $teacher_id);
                                if ($stmt_create->execute()) {
                                    $new_class_id = $conn->insert_id;
                                    // 作成したクラスを自動的に担当クラスのリストに追加
                                    $selected_class_ids[] = $new_class_id;
                                    echo "<p>" . translate('submit-register-classteacher.php_51行目_新しいクラス') . "「" . htmlspecialchars($new_class_name, ENT_QUOTES, 'UTF-8') . "」" . translate('submit-register-classteacher.php_51行目_を作成しました。') . "</p>";
                                } else {
                                    throw new Exception(translate('submit-register-classteacher.php_53行目_新しいクラスの作成に失敗しました: ') . $conn->error);
                                }
                                $stmt_create->close();
                            }

                            // 2. この教師の既存のクラス関連付けを一旦すべて削除
                            $stmt_delete = $conn->prepare("DELETE FROM classteacher WHERE TID = ?");
                            $stmt_delete->bind_param("s", $teacher_id);
                            if (!$stmt_delete->execute()) {
                                throw new Exception(translate('submit-register-classteacher.php_62行目_既存のクラス関連付けの削除に失敗しました: ') . $conn->error);
                            }
                            $stmt_delete->close();
                            
                            // 3. 選択されたクラスの関連付けを登録
                            if (!empty($selected_class_ids)) {
                                // 重複を除外
                                $final_class_ids = array_unique($selected_class_ids);
                                
                                $stmt_insert = $conn->prepare("INSERT INTO classteacher (TID, ClassID) VALUES (?, ?)");
                                $success_count = 0;
                                foreach ($final_class_ids as $class_id) {
                                    $stmt_insert->bind_param("ss", $teacher_id, $class_id);
                                    if ($stmt_insert->execute()) {
                                        $success_count++;
                                    } else {
                                        // エラーが発生したらcatchブロックに移動し、ロールバック
                                        throw new Exception(translate('submit-register-classteacher.php_79行目_クラスの関連付けに失敗しました (ClassID: ') . $class_id . '): ' . $conn->error);
                                    }
                                }
                                echo "<p>{$success_count}" . translate('submit-register-classteacher.php_82行目_件のクラスを') . " " . htmlspecialchars($teacher_id, ENT_QUOTES, 'UTF-8') . " " . translate('submit-register-classteacher.php_82行目_先生に関連付けました。') . "</p>";
                                $stmt_insert->close();
                            } else {
                                echo "<p>" . translate('submit-register-classteacher.php_85行目_すべてのクラスの関連付けを解除しました。') . "</p>";
                            }

                            $conn->commit(); // トランザクションをコミット
                            echo "<p style='color: green; font-weight: bold;'>" . translate('submit-register-classteacher.php_89行目_クラス情報が正常に更新されました。') . "</p>";

                        } catch (Exception $e) {
                            $conn->rollback(); // エラー発生時にロールバック
                            echo "<p style='color: red; font-weight: bold;'>" . translate('submit-register-classteacher.php_93行目_エラーが発生したため、処理を中断しました。') . "</p>";
                            echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
                        }
                    } else {
                        echo "<p>" . translate('submit-register-classteacher.php_97行目_無効なリクエストです。') . "</p>";
                    }
                ?>
                <a href="teachertrue.php"><?= translate('submit-register-classteacher.php_100行目_ホーム画面に戻る') ?></a>
            </div>
        </main>
    </div>
</body>
</html>


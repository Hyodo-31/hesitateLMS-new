<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('submit-test.php_5行目_教師用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        // session_start(); // lang.phpでセッションスタート
        require "../dbc.php";
    ?>
    <header>
        <div class="logo"><?= translate('submit-test.php_18行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="teachertrue.php"><?= translate('submit-test.php_21行目_ホーム') ?></a></li> -->
                <!-- <li><a href="#"><?= translate('submit-test.php_22行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('submit-test.php_23行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('submit-test.php_24行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('submit-test.php_25行目_問題分析') ?></a></li> -->
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('submit-test.php_31行目_ホーム') ?></a></li>
                <li><a href="./create/new.php?mode=0"><?= translate('teachertrue.php_1077行目_新規英語問題作成') ?></a></li>
                <!-- <li><a href="#"><?= translate('submit-test.php_32行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('submit-test.php_33行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('submit-test.php_34行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('submit-test.php_35行目_問題分析') ?></a></li> -->
            </ul>
        </aside>
        <main>
            <?php
                // POSTリクエストが送信されたか確認
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // セッションから教師IDを取得
                    $teacher_id = $_SESSION['MemberID'];
                    // テスト名を取得
                    $test_name = $_POST['test_name'];

                    // ★ 追加：対象の種類を取得（'class'または'group'）
                    $target_type = $_POST['target_type'];

                    // ★ 修正：選択された対象に応じて、対象IDを決定する
                    if ($target_type == 'class') {
                        $target_group = $_POST['class_id'];
                    } elseif ($target_type == 'group') {
                        $target_group = $_POST['group_id'];
                    } else {
                        $target_group = null; // エラー処理など
                    }
                    
                    $selected_questions = isset($_POST['WID']) ? $_POST['WID'] : [];

                    // テスト名、対象ID、及び選択された問題があるかチェック
                    if (!empty($test_name) && !empty($selected_questions) && $target_group !== null) {

                        // ★ 修正：テスト作成時に対象の種類も一緒に保存するため、INSERT文にtarget_typeを追加
                        // ※ これに伴い、testsテーブルには target_type カラム（例：ENUM('class','group')）を追加しておく必要があります。
                        $stmt = $conn->prepare("INSERT INTO tests (test_name, teacher_id, target_group, target_type) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param('ssis', $test_name, $teacher_id, $target_group, $target_type);
                        $stmt->execute();
                        $test_id = $stmt->insert_id; // 挿入されたテストIDを取得
                        $stmt->close();

                        // 2. 選択された問題を`test_questions`に保存
                        foreach ($selected_questions as $WID) {
                            // 2.1. 現在の test_id における最大 OID を取得
                            $query = "SELECT MAX(OID) AS max_oid FROM test_questions WHERE test_id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param('i', $test_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $row = $result->fetch_assoc();
                            $max_oid = $row['max_oid'];
                            $stmt->close();

                            // 2.2. 次の OID を決定（最大OIDがあれば+1、なければ1から開始）
                            $next_oid = ($max_oid !== null) ? $max_oid + 1 : 1;

                            // 2.3. test_questions テーブルにデータを挿入
                            $insert_query = "INSERT INTO test_questions (test_id, OID, WID) VALUES (?, ?, ?)";
                            $insert_stmt = $conn->prepare($insert_query);
                            $insert_stmt->bind_param('iii', $test_id, $next_oid, $WID);
                            $insert_stmt->execute();
                            $insert_stmt->close();
                        }

                        // 完了メッセージ
                        echo translate('submit-test.php_103行目_テストが正常に作成されました');
                    } else {
                        // エラーメッセージ
                        echo translate('submit-test.php_106行目_テスト名対象または問題が選択されていません');
                    }
                } else {
                    // 無効なリクエストへの対応
                    echo translate('submit-test.php_109行目_無効なリクエストです');
                }

                // データベース接続を閉じる
                $conn->close();
            ?>
        </main>

    </div>
</body>
</html>
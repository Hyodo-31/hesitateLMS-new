<?php
// lang.phpでセッションが開始されるため、個別のsession_startは不要
include '../lang.php';
require "../dbc.php";
// セッション変数をクリアする（必要に応じて）
unset($_SESSION['conditions']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('test.php_5行目_テスト') ?></title>
    <link rel="stylesheet" href="../style/student_style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .status-unanswered {
            color: red;
            font-weight: bold;
        }
        .status-in_progress { /* CSSクラス名を修正 */
            color: orange;
            font-weight: bold;
        }
        .status-completed {
            color: black;
            font-weight: bold;
        }
    </style>
</head>
<script>
    function openwin(Qid){
        window.open("ques.php?Qid="+Qid, "new", "width=861,height=700,resizable=0,menubar=0");
    }
</script>
<body>
    <header>
        <div class="logo"><?= translate('test.php_45行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="student.php"><?= translate('test.php_48行目_ホーム') ?></a></li> -->
                <li><a href="../logout.php"><?= translate('test.php_49行目_ログアウト') ?></a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="student.php"><?= translate('test.php_48行目_ホーム') ?></a></li>
                <li><a href="Analytics/analytics.php"><?= translate('test.php_56行目_成績管理') ?></a></li>
            </ul>
        </aside>
        <main>
            <?= translate('test.php_61行目_テストページ') ?>
            <?php
                $group_ids_str = implode(",", $_SESSION['GroupIDs']);
                
                $student_id = $_SESSION['MemberID'];
                $class_id   = $_SESSION['ClassID'];
                
                // SQLを修正し、言語に依存しないステータスキーを返すように変更
                $sql = "SELECT t.id, t.test_name, t.created_at,
                            (CASE
                                WHEN MAX(uq.current_oid) IS NULL THEN 'unanswered'
                                WHEN MAX(uq.current_oid) = (
                                    SELECT MAX(tq.OID) 
                                    FROM test_questions tq 
                                    WHERE tq.test_id = t.id
                                ) THEN 'completed'
                                ELSE 'in_progress'
                            END) AS status
                        FROM tests t
                        LEFT JOIN user_progress uq ON t.id = uq.test_id AND uq.uid = ?
                        WHERE (t.target_type = 'class' AND t.target_group = ?)
                        OR (t.target_type = 'group' AND t.target_group IN ($group_ids_str))
                        GROUP BY t.id, t.test_name, t.created_at
                        ORDER BY t.created_at DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $student_id, $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
            ?>
            <h1><?= translate('test.php_99行目_登録されたテスト一覧') ?></h1>
            <?php if ($result->num_rows > 0): ?>
                <table border="1" class="test-table">
                    <thead>
                        <tr>
                            <th><?= translate('test.php_103行目_テスト名') ?></th>
                            <th><?= translate('test.php_104行目_作成日時') ?></th>
                            <th><?= translate('test.php_105行目_解答状況') ?></th>
                            <th><?= translate('test.php_106行目_解答') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            while ($row = $result->fetch_assoc()) {
                                $status_key = $row['status']; // e.g., 'unanswered'
                                $status_class = 'status-' . str_replace(' ', '_', $status_key); // CSSクラス用にスペースをアンダースコアに
                                
                                // ステータスキーを翻訳
                                $translated_status = translate('status_' . $status_key);

                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['test_name']) . "</td>";
                                echo "<td>" . $row['created_at'] . "</td>";
                                echo "<td class='$status_class'>" . htmlspecialchars($translated_status) . "</td>";
                                echo "<td><a href='javascript:openwin(" . $row['id'] . ")'>" . translate('test.php_106行目_解答') . "</a></td>";
                                echo "</tr>";
                            }
                        ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?= translate('test.php_125行目_テストはありません') ?></p>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
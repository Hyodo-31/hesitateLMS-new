<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('delete-student-group.php_5行目_教師用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        // session_start(); // lang.phpでセッションは開始済み
        require "../dbc.php";
        require_once __DIR__ . '/hesitation-estimation-state.php';
        require_once __DIR__ . '/usage-log-diagnostics.php';
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo"><?= translate('delete-student-group.php_18行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="teachertrue.php"><?= translate('delete-student-group.php_21行目_ホーム') ?></a></li> -->
                <!-- <li><a href="#"><?= translate('delete-student-group.php_22行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('delete-student-group.php_23行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('delete-student-group.php_24行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('delete-student-group.php_25行目_問題分析') ?></a></li> -->
                <!-- <li><a href="register-student.php"><?= translate('delete-student-group.php_26行目_新規学生登録') ?></a></li> -->
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('delete-student-group.php_32行目_ホーム') ?></a></li>
                <!-- <li><a href="#"><?= translate('delete-student-group.php_33行目_コース管理') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('delete-student-group.php_34行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="Analytics/studentAnalytics.php"><?= translate('delete-student-group.php_35行目_学生分析') ?></a></li> -->
                <!-- <li><a href="Analytics/questionAnalytics.php"><?= translate('delete-student-group.php_36行目_問題分析') ?></a></li> -->
            </ul>
        </aside>
        <main>
            <?php
                // 削除対象のグループIDと、削除ログに残す削除前の名称を取得
                $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
                $teacher_id = (string)($_SESSION['MemberID'] ?? '');
                $deleted_group_name = null;
                try {
                    $group_name_stmt = $conn->prepare('SELECT group_name FROM `groups` WHERE group_id = ? LIMIT 1');
                    if ($group_name_stmt) {
                        $group_name_stmt->bind_param('i', $group_id);
                        $group_name_stmt->execute();
                        $group_name_row = $group_name_stmt->get_result()->fetch_assoc();
                        $deleted_group_name = $group_name_row ? (string)$group_name_row['group_name'] : null;
                        $group_name_stmt->close();
                    }
                } catch (Throwable $e) {
                    error_log('[Grouping delete log] 削除前のグループ名を取得できませんでした: ' . $e->getMessage());
                }

                // `group_members`から該当するグループのメンバーを削除
                $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();

                // `groups`から該当するグループを削除
                $stmt = $conn->prepare("DELETE FROM `groups` WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $group_delete_succeeded = $stmt->affected_rows === 1;
                $stmt->close();

                // グループ本体の削除が成功した場合のみ記録する。ログ失敗は削除処理を妨げない。
                if ($group_delete_succeeded && $teacher_id !== '' && $deleted_group_name !== null) {
                    try {
                        $ml = teacher_hesitation_ml_flag($conn, $teacher_id);
                        $log_stmt = $conn->prepare(
                            'INSERT INTO delete_groups (teacher_id, group_id, group_name, ML) VALUES (?, ?, ?, ?)'
                        );
                        if (!$log_stmt) {
                            usage_log_throw_statement_error(
                                'student_grouping_delete',
                                'delete_groups',
                                $conn->errno,
                                $conn->sqlstate,
                                $conn->error
                            );
                        }
                        $log_stmt->bind_param('sisi', $teacher_id, $group_id, $deleted_group_name, $ml);
                        if (!$log_stmt->execute()) {
                            $errno = $log_stmt->errno;
                            $sqlState = $log_stmt->sqlstate;
                            $message = $log_stmt->error;
                            $log_stmt->close();
                            usage_log_throw_statement_error(
                                'student_grouping_delete',
                                'delete_groups',
                                $errno,
                                $sqlState,
                                $message
                            );
                        }
                        $log_stmt->close();
                    } catch (mysqli_sql_exception $e) {
                        usage_log_report_database_error('student_grouping_delete', 'delete_groups', $e, $conn);
                    } catch (Throwable $e) {
                        error_log('[Grouping delete log] 削除ログを保存できませんでした: ' . $e->getMessage());
                    }
                }

                $conn->close();

                // 削除後のリダイレクト
                header("Location: teachertrue.php");
                exit;
            ?>
        </main>
    </div>
</body>
</html>

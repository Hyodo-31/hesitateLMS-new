<?php
// すべての処理の前にセッションと多言語対応を読み込む
include '../lang.php';

// データベース接続を一度だけ行い、変数に保持する
require "../dbc.php";

// ログインしている教員のIDを取得
if (isset($_SESSION['MemberID'])) {
    $teacher_id = $_SESSION['MemberID'];
} elseif (isset($_SESSION['TID'])) {
    $teacher_id = $_SESSION['TID'];
} else {
    // ログインページにリダイレクトするなどのエラー処理
    // header('Location: ../login.php');
    // exit;
    $teacher_id = null;
}

// ログから最新のCSVファイルパスを取得
$results_csv_path = null;
if ($teacher_id) {
    // activity_logsテーブルから最新の機械学習結果のファイルパスを取得
    $stmt_log = $conn->prepare("SELECT result FROM activity_logs WHERE user_id = ? AND action_type = 'machine_learning_completed' ORDER BY created_at DESC LIMIT 1");
    if ($stmt_log) {
        $stmt_log->bind_param("s", $teacher_id);
        $stmt_log->execute();
        $result_log = $stmt_log->get_result();

        if ($row_log = $result_log->fetch_assoc()) {
            // resultカラムのJSONをデコード
            $result_json = json_decode($row_log['result'], true);
            // JSONからcsv_fileのパスを取得し、正しい相対パスに整形
            if (isset($result_json['csv_file']) && is_string($result_json['csv_file'])) {
                $results_csv_path = './machineLearning/' . basename($result_json['csv_file']);
            }
        }
        $stmt_log->close();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('teachertrue.php_7行目_教育用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="notification-script.js"></script>
</head>

<body>
    <header>
        <div class="logo"><?= translate('teachertrue.php_21行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <li><a href="../logout.php"><?= translate('teachertrue.php_31行目_ログアウト') ?></a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="#"><?= translate('teachertrue.php_39行目_ホーム') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('teachertrue.php_40行目_迷い推定・機械学習') ?></a></li>
                <li><a href="register-student.php"><?= translate('teachertrue.php_45行目_新規学生登録') ?></a></li>
            </ul>
        </aside>
        <main>

            <div class="notifications">
                <h2><?= translate('teachertrue.php_51行目_お知らせ') ?></h2>
                <div class="notify-scroll">
                    <?php
                    // お知らせを取得 (接続は維持されている)
                    $result = $conn->query("SELECT id, subject, content FROM notifications ORDER BY created_at DESC");
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<div class='notification-item' data-id='{$row['id']}'>";
                            echo "<h3 class='notification-title'>{$row['subject']}</h3>";
                            echo "<p class='notification-content' style='display: none;'>{$row['content']}</p>";
                            echo "</div>";
                        }
                        $result->free();
                    }
                    ?>
                </div>
                <div id="notifymake-botton" class="button1">
                    <a href='create-notification.php'><?= translate('teachertrue.php_67行目_お知らせ作成') ?></a>
                </div>
            </div>

            <div class="class-overview">
                <h2><?= translate('teachertrue.php_72行目_グループ別データ') ?></h2>
                <div id="button-groupstudent-making" class="button1">
                    <a href='create-student-group.php'><?= translate('teachertrue.php_74行目_学習者グルーピング作成') ?></a>
                </div>
                <div class="class-data">
                    <?php
                    $groups = [];
                    if ($teacher_id) {
                        $stmt = $conn->prepare("SELECT * FROM `groups` WHERE TID = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $teacher_id);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $group_id = $row['group_id'];
                                    $group_name = $row['group_name'];

                                    $stmt_groupmember = $conn->prepare("SELECT * FROM group_members WHERE group_id = ?");
                                    $stmt_groupmember->bind_param("i", $group_id);
                                    $stmt_groupmember->execute();
                                    $result_groupmember = $stmt_groupmember->get_result();
                                    $group_students = [];
                                    while ($member = $result_groupmember->fetch_assoc()) {
                                        $students_id = $member['uid'];
                                        $stmt_scores = $conn->prepare("SELECT COUNT(*) AS total_answers, SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) AS correct_answers, SUM(Time) AS total_time FROM linedata WHERE uid = ?");
                                        $stmt_scores->bind_param("i", $students_id);
                                        $stmt_scores->execute();
                                        $score_data = $stmt_scores->get_result()->fetch_assoc();
                                        $correct_answers = $score_data['correct_answers'] ?? 0;
                                        $total_answers = $score_data['total_answers'] ?? 0;
                                        $total_time = $score_data['total_time'] ?? 0;
                                        $accuracy_rate = $total_answers > 0 ? number_format(($correct_answers / $total_answers) * 100, 2) : 0;
                                        $notaccuracy_rate = 100 - $accuracy_rate;
                                        $accuracy_time = $total_answers > 0 ? number_format(($total_time / 1000) / $total_answers, 2) : 0;
                                        $stmt_scores->close();

                                        $stmt_name = $conn->prepare("SELECT Name FROM students WHERE uid = ?");
                                        $stmt_name->bind_param("i", $students_id);
                                        $stmt_name->execute();
                                        $name_data = $stmt_name->get_result()->fetch_assoc();
                                        $name = $name_data['Name'] ?? 'Unknown';
                                        $stmt_name->close();

                                        $group_students[] = ['student_id' => $students_id, 'name' => $name, 'accuracy' => $accuracy_rate, 'notaccuracy' => $notaccuracy_rate, 'time' => $accuracy_time];
                                    }
                                    $groups[] = ['group_name' => $group_name, 'group_id' => $group_id, 'students' => $group_students];
                                    $stmt_groupmember->close();
                                }
                            } else {
                                echo "<p>" . translate('teachertrue.php_156行目_学習者グループがありません') . "</p>";
                            }
                            $stmt->close();
                        }
                    ?>
                        <script>
                            const groupData = <?php echo json_encode($groups); ?>;
                        </script>

                        <div class="class-data" id="group-data-container"></div>
                </div>
            </div>

            <section class="previous-results">
                <h2><?= translate('teachertrue.php_前回の迷い推定結果') ?></h2>
                <?php
                        $student_uids = [];
                        if ($teacher_id) {
                            $class_stmt = $conn->prepare("SELECT ClassID FROM ClassTeacher WHERE TID = ?");
                            $class_stmt->bind_param("i", $teacher_id);
                            $class_stmt->execute();
                            $class_result = $class_stmt->get_result();
                            $class_ids = [];
                            while ($row = $class_result->fetch_assoc()) {
                                $class_ids[] = $row['ClassID'];
                            }
                            $class_stmt->close();

                            if (!empty($class_ids)) {
                                $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
                                $types = str_repeat('i', count($class_ids));
                                $student_stmt = $conn->prepare("SELECT uid FROM students WHERE ClassID IN ($placeholders)");
                                $student_stmt->bind_param($types, ...$class_ids);
                                $student_stmt->execute();
                                $student_result = $student_stmt->get_result();
                                while ($row = $student_result->fetch_assoc()) {
                                    $student_uids[] = $row['uid'];
                                }
                                $student_stmt->close();
                            }
                        }

                        $tf_lookup_map = [];
                        if (!empty($student_uids)) {
                            $placeholders = implode(',', array_fill(0, count($student_uids), '?'));
                            $types = str_repeat('i', count($student_uids));
                            $get_all_tf_query = "SELECT UID, WID, TF FROM linedata WHERE UID IN ($placeholders)";
                            $stmt = $conn->prepare($get_all_tf_query);
                            if ($stmt) {
                                $stmt->bind_param($types, ...$student_uids);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($db_row = $result->fetch_assoc()) {
                                    $key = "{$db_row['UID']}-{$db_row['WID']}";
                                    $tf_lookup_map[$key] = $db_row['TF'];
                                }
                                $stmt->close();
                            }
                        }

                        $ml_results = [];
                        if ($teacher_id) {
                            $ml_stmt = $conn->prepare("SELECT UID, WID, Understand, attempt FROM temporary_results WHERE teacher_id = ? ORDER BY UID, WID, attempt");
                            $ml_stmt->bind_param("i", $teacher_id);
                            $ml_stmt->execute();
                            $result = $ml_stmt->get_result();
                            $ml_results = $result->fetch_all(MYSQLI_ASSOC);
                            $ml_stmt->close();
                        }
                ?>

                <div id="table-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; margin-top: 10px;">
                    <h3><?= translate('teachertrue.php_100行目_迷い推定結果') ?></h3>
                    <?php if (!empty($ml_results)): ?>
                        <table id="results-table" class="results-table">
                            <thead>
                                <tr>
                                    <th>UID</th>
                                    <th>WID</th>
                                    <th><?= translate('machineLearning_sample.php_1188行目_迷いの有無') ?></th>
                                    <th><?= translate('machineLearning_sample.php_1195行目_正誤') ?></th>
                                    <th><?= translate('teachertrue.php_軌跡再現') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($ml_results as $row):
                                    $uid = $row['UID'];
                                    $wid = $row['WID'];
                                    $understand = $row['Understand'];
                                    $attempt = $row['attempt'];
                                    $lookup_key = "{$uid}-{$wid}";
                                    $tf_value = $tf_lookup_map[$lookup_key] ?? null;
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($uid) ?></td>
                                        <td><?= htmlspecialchars($wid) ?>-<?= htmlspecialchars($attempt) ?></td>
                                        <td>
                                            <?php
                                            if ($understand == 4) {
                                                echo translate('machineLearning_sample.php_1213行目_迷い無し');
                                            } elseif ($understand == 2) {
                                                echo "<span style='color: red; font-weight: bold;'>" . translate('machineLearning_sample.php_1215行目_迷い有り') . "</span>";
                                            } else {
                                                echo htmlspecialchars($understand);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($tf_value === '1' || $tf_value === 1) {
                                                echo translate('machineLearning_sample.php_1222行目_正解');
                                            } elseif ($tf_value === '0' || $tf_value === 0) {
                                                echo "<span style='color: red; font-weight: bold;'>" . translate('machineLearning_sample.php_1224行目_不正解') . "</span>";
                                            } else {
                                                echo "N/A";
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="./mousemove/mousemove.php?UID=<?= urlencode($uid) ?>&WID=<?= urlencode($wid) ?>&LogID=<?= urlencode($attempt) ?>" target="_blank">
                                                <?= translate('teachertrue.php_軌跡再現') ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?= translate('teachertrue.php_前回の推定結果はありません。') ?><a href="machinelearning_sample.php"><?= translate('teachertrue.php_こちら') ?></a><?= translate('teachertrue.php_から推定を実行してください。') ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="detail-info" class="class-card">
                <h2><?= translate('machineLearning_sample.php_1464行目_学習者の詳細情報') ?></h2>
                <label for="uid-select"><?= translate('machineLearning_sample.php_1466行目_学習者名UID') ?></label>
                <select id="uid-select">
                    <option value=""><?= translate('machineLearning_sample.php_1468行目_選択してください') ?></option>
                    <?php
                        if ($teacher_id) {
                            $getUsersQuery = "SELECT DISTINCT tr.uid,s.Name FROM temporary_results tr 
                                                LEFT JOIN students s ON tr.uid = s.uid 
                                                WHERE teacher_id = ?";
                            $stmt_users = $conn->prepare($getUsersQuery);
                            $stmt_users->bind_param("i", $teacher_id);
                            $stmt_users->execute();
                            $result_users = $stmt_users->get_result();

                            while ($row_user = $result_users->fetch_assoc()) {
                                echo '<option value="' . $row_user['uid'] . '">' . $row_user['Name'] . ' (' . $row_user['uid'] . ')</option>';
                            }
                            $stmt_users->close();
                        }
                    ?>
                </select>
                <div id="student-details">
                    <div id="student-details-maininfo"></div>
                    <div id="student-details-grammar"></div>
                </div>
                <label for="wid-select"></label>
                <select id="wid-select">
                    <option value=""><?= translate('machineLearning_sample.php_1483行目_選択してください') ?></option>
                </select>
                <div id="wid-details">
                    <div id="wid-details-maininfo-stu"></div>
                    <div id="wid-details-maininfo-all"></div>
                </div>
            </section>

            <div class="all-overview">
                <h2><?= translate('teachertrue.php_671行目_クラス単位のデータ') ?></h2>
                <div class="class-data">
                    <?php
                        $classes = [];
                        if ($teacher_id) {
                            $stmt = $conn->prepare("SELECT * FROM ClassTeacher WHERE TID = ?");
                            if ($stmt) {
                                $stmt->bind_param("i", $teacher_id);
                                $stmt->execute();
                                $result = $stmt->get_result();

                                if ($result->num_rows > 0) {
                                    while ($row_class = $result->fetch_assoc()) {
                                        $class_id = $row_class['ClassID'];
                                        $stmt_classname = $conn->prepare("SELECT ClassName FROM classes WHERE ClassID = ?");
                                        $stmt_classname->bind_param("i", $class_id);
                                        $stmt_classname->execute();
                                        $class_name_data = $stmt_classname->get_result()->fetch_assoc();
                                        $class_name = $class_name_data['ClassName'] ?? 'Unknown';
                                        $stmt_classname->close();

                                        $stmt_classstu = $conn->prepare("SELECT * FROM students WHERE ClassID = ?");
                                        $stmt_classstu->bind_param("i", $class_id);
                                        $stmt_classstu->execute();
                                        $result_classstu = $stmt_classstu->get_result();
                                        $class_students = [];

                                        if ($result_classstu->num_rows > 0) {
                                            while ($row_student = $result_classstu->fetch_assoc()) {
                                                $student_id = $row_student['uid'];
                                                $stmt_scores = $conn->prepare("SELECT COUNT(*) AS total_answers, SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) AS correct_answers, SUM(Time) AS total_time FROM linedata WHERE uid = ?");
                                                $stmt_scores->bind_param("i", $student_id);
                                                $stmt_scores->execute();
                                                $score_data = $stmt_scores->get_result()->fetch_assoc();

                                                if ($score_data) {
                                                    $correct_answers = $score_data['correct_answers'] ?? 0;
                                                    $total_answers = $score_data['total_answers'] ?? 0;
                                                    $total_time = $score_data['total_time'] ?? 0;
                                                    $accuracy_rate = $total_answers > 0 ? number_format(($correct_answers / $total_answers) * 100, 2) : 0;
                                                    $notaccuracy_rate = 100 - $accuracy_rate;
                                                    $accuracy_time = $total_answers > 0 ? number_format(($total_time / 1000) / $total_answers, 2) : 0;
                                                    $name = $row_student['Name'];
                                                    $class_students[] = ['student_id' => $student_id, 'name' => $name, 'accuracy' => $accuracy_rate, 'notaccuracy' => $notaccuracy_rate, 'time' => $accuracy_time];
                                                }
                                                $stmt_scores->close();
                                            }
                                        }
                                        $stmt_classstu->close();
                                        $classes[] = ['class_name' => $class_name, 'class_students' => $class_students];
                                    }
                                }
                                $stmt->close();
                            }
                        }
                    ?>
                    <div class="class-data" id="class-data-container"></div>
                    <script>
                        const classData = <?php echo json_encode($classes); ?>;
                    </script>
                </div>
                <div id="cluster-data"></div>
            </div>

            <div class="create-new">
                <h2><?= translate('teachertrue.php_1075行目_新規問題・テスト作成') ?></h2>
                <div id="createassignment-botton" class="button1">
                    <a href='./create/new.php?mode=0'><?= translate('teachertrue.php_1077行目_新規英語問題作成') ?></a>
                </div>
                <div id="createassignment-ja-botton" class="button1">
                    <a href='./create_ja/new.php?mode=0'><?= translate('teachertrue.php_1078行目_新規日本語問題作成') ?></a>
                </div>
                <div id="createtest-botton" class="button1">
                    <a href='create-test.php'><?= translate('teachertrue.php_1080行目_新規英語テスト作成') ?></a>
                </div>
                <div id="createtest-botton" class="button1">
                    <a href='create-test-ja.php'><?= translate('teachertrue.php_1081行目_新規日本語テスト作成') ?></a>
                </div>
            </div>

        </main>
    </div>

    <div id="feature-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeFeatureModal()">&times;</span>
            <h3><?= translate('teachertrue.php_175行目_特徴量を選択してください') ?></h3>
            <form id="feature-form">
                <label><input type="checkbox" name="feature" value="notaccuracy"><?= translate('teachertrue.php_177行目_不正解率 (%)') ?><span class="info-icon" data-feature-name="notaccuracy">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="Time"><?= translate('teachertrue.php_178行目_解答時間 (秒)') ?><span class="info-icon" data-feature-name="Time">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="distance"><?= translate('teachertrue.php_179行目_距離') ?><span class="info-icon" data-feature-name="distance">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="averageSpeed"><?= translate('teachertrue.php_180行目_平均速度') ?><span class="info-icon" data-feature-name="averageSpeed">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="maxSpeed"><?= translate('teachertrue.php_181行目_最高速度') ?><span class="info-icon" data-feature-name="maxSpeed">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="thinkingTime"><?= translate('teachertrue.php_182行目_考慮時間') ?><span class="info-icon" data-feature-name="thinkingTime">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="answeringTime"><?= translate('teachertrue.php_183行目_第一ドロップ後解答時間') ?><span class="info-icon" data-feature-name="answeringTime">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="totalStopTime"><?= translate('teachertrue.php_184行目_合計静止時間') ?><span class="info-icon" data-feature-name="totalStopTime">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="maxStopTime"><?= translate('teachertrue.php_185行目_最大静止時間') ?><span class="info-icon" data-feature-name="maxStopTime">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="totalDDIntervalTime"><?= translate('teachertrue.php_186行目_合計DD間時間') ?><span class="info-icon" data-feature-name="totalDDIntervalTime">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="maxDDIntervalTime"><?= translate('teachertrue.php_187行目_最大DD間時間') ?><span class="info-icon" data-feature-name="maxDDIntervalTime">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="maxDDTime"><?= translate('teachertrue.php_188行目_合計DD時間') ?><span class="info-icon" data-feature-name="maxDDTime">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="minDDTime"><?= translate('teachertrue.php_189行目_最小DD時間') ?><span class="info-icon" data-feature-name="minDDTime">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="DDCount"><?= translate('teachertrue.php_190行目_合計DD回数') ?><span class="info-icon" data-feature-name="DDCount">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="groupingDDCount"><?= translate('teachertrue.php_191行目_グループ化DD回数') ?><span class="info-icon" data-feature-name="groupingDDCount">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="groupingCountbool"><?= translate('teachertrue.php_192行目_グループ化有無') ?><span class="info-icon" data-feature-name="groupingCountbool">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="xUturnCount"><?= translate('teachertrue.php_193行目_x軸Uターン回数') ?><span class="info-icon" data-feature-name="xUturnCount">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="yUturnCount"><?= translate('teachertrue.php_194行目_y軸Uターン回数') ?><span class="info-icon" data-feature-name="yUturnCount">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="register_move_count1"><?= translate('teachertrue.php_195行目_レジスタ➡レジスタへの移動回数') ?><span class="info-icon" data-feature-name="register_move_count1">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="register_move_count2"><?= translate('teachertrue.php_196行目_レジスタ➡レジスタ外への移動回数') ?><span class="info-icon" data-feature-name="register_move_count2">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="register_move_count3"><?= translate('teachertrue.php_197行目_レジスタ外➡レジスタへの移動回数') ?><span class="info-icon" data-feature-name="register_move_count3">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="register01count1"><?= translate('teachertrue.php_198行目_レジスタ➡レジスタへの移動有無') ?><span class="info-icon" data-feature-name="register01count1">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="register01count2"><?= translate('teachertrue.php_199行目_レジスタ➡レジスタ外への移動有無') ?><span class="info-icon" data-feature-name="register01count2">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="register01count3"><?= translate('teachertrue.php_200行目_レジスタ外➡レジスタへの移動有無') ?><span class="info-icon" data-feature-name="register01count3">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="registerDDCount"><?= translate('teachertrue.php_201行目_レジスタに関する合計の移動回数') ?><span class="info-icon" data-feature-name="registerDDCount">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="xUturnCountDD"><?= translate('teachertrue.php_202行目_x軸UターンD&D回数') ?><span class="info-icon" data-feature-name="xUturnCountDD">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="yUturnCountDD"><?= translate('teachertrue.php_203行目_y軸UターンD&D回数') ?><span class="info-icon" data-feature-name="yUturnCountDD">ⓘ</span></label><br>
                <label><input type="checkbox" name="feature" value="FromlastdropToanswerTime"><?= translate('teachertrue.php_204行目_最終ドロップ後時間') ?><span class="info-icon" data-feature-name="FromlastdropToanswerTime">ⓘ</span></label><br>
                <button type="button" id="apply-features-btn"><?= translate('teachertrue.php_205行目_適用') ?></button>
            </form>
        </div>
    </div>
    <div id="clustering-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeClusteringModal()">&times;</span>
            <form id="clustering-feature-form">
                <h3 style="display : none;"><?= translate('teachertrue.php_214行目_クラスタ数を入力してください') ?></h3>
                <input type="number" id="clustering-input" min="1" max="10" value="2" style="display: none;">
                <h3 style="display : none;"><?= translate('teachertrue.php_216行目_クラスタリング手法を選択してください') ?></h3>
                <label for="clustering-method" style="display: none;"><?= translate('teachertrue.php_217行目_クラスタリング手法') ?></label>
                <select id="clustering-method" style="display: none;">
                    <option value="kmeans">K-Means</option>
                    <option value="xmeans">X-Means</option>
                    <option value="gmeans">G-Means</option>
                </select>
                <h3><?= translate('teachertrue.php_223行目_クラスタリング特徴量を選択してください') ?></h3>
                <label title="問題解答にかかった時間"><input type="checkbox" name="feature" value="Time"><?= translate('teachertrue.php_226行目_解答時間 (秒)') ?><span class="info-icon" data-feature-name="Time">ⓘ</span></label><br>
                <label title="問題解答中にマウスカーソルを移動した距離（ピクセル単位）"><input type="checkbox" name="feature" value="distance"><?= translate('teachertrue.php_227行目_距離') ?><span class="info-icon" data-feature-name="distance">ⓘ</span></label><br>
                <label title="問題解答中のマウスカーソルの速度の平均"><input type="checkbox" name="feature" value="averageSpeed"><?= translate('teachertrue.php_228行目_平均速度') ?><span class="info-icon" data-feature-name="averageSpeed">ⓘ</span></label><br>
                <label title="問題解答中のマウスカーソルの速度の最大値"><input type="checkbox" name="feature" value="maxSpeed"><?= translate('teachertrue.php_229行目_最大速度') ?><span class="info-icon" data-feature-name="maxSpeed">ⓘ</span></label><br>
                <label title="解答開始から最初のドラッグが行われるまでの時間"><input type="checkbox" name="feature" value="thinkingTime"><?= translate('teachertrue.php_230行目_第一ドラッグ前時間') ?><span class="info-icon" data-feature-name="thinkingTime">ⓘ</span></label><br>
                <label title="最初のドラッグから解答終了までの時間"><input type="checkbox" name="feature" value="answeringTime"><?= translate('teachertrue.php_231行目_第一ドラッグ後時間') ?><span class="info-icon" data-feature-name="answeringTime">ⓘ</span></label><br>
                <label title="マウスカーソルが静止していた時間の合計値"><input type="checkbox" name="feature" value="totalStopTime"><?= translate('teachertrue.php_232行目_合計静止時間') ?><span class="info-icon" data-feature-name="totalStopTime">ⓘ</span></label><br>
                <label title="マウスカーソルが静止していた時間の最大値"><input type="checkbox" name="feature" value="maxStopTime"><?= translate('teachertrue.php_233行目_最大静止時間') ?><span class="info-icon" data-feature-name="maxStopTime">ⓘ</span></label><br>
                <label title="D&Dから次のD&Dまでの時間の合計値"><input type="checkbox" name="feature" value="totalDDIntervalTime"><?= translate('teachertrue.php_234行目_合計D&D間時間') ?><span class="info-icon" data-feature-name="totalDDIntervalTime">ⓘ</span></label><br>
                <label title="D&Dから次のD&Dまでの時間の最大値"><input type="checkbox" name="feature" value="maxDDIntervalTime"><?= translate('teachertrue.php_235行目_最大D&D間時間') ?><span class="info-icon" data-feature-name="maxDDIntervalTime">ⓘ</span></label><br>
                <label title="D&D中の時間の合計値"><input type="checkbox" name="feature" value="maxDDTime"><?= translate('teachertrue.php_236行目_合計D&D時間') ?><span class="info-icon" data-feature-name="maxDDTime">ⓘ</span></label><br>
                <label title="D&D中の時間の最小値"><input type="checkbox" name="feature" value="minDDTime"><?= translate('teachertrue.php_237行目_最小D&D時間') ?><span class="info-icon" data-feature-name="minDDTime">ⓘ</span></label><br>
                <label title="D&Dが行われた回数"><input type="checkbox" name="feature" value="DDCount"><?= translate('teachertrue.php_238行目_合計D&D回数') ?><span class="info-icon" data-feature-name="DDCount">ⓘ</span></label><br>
                <label title="グルーピングが使用された回数"><input type="checkbox" name="feature" value="groupingDDCount"><?= translate('teachertrue.php_239行目_グループ化回数') ?><span class="info-icon" data-feature-name="groupingDDCount">ⓘ</span></label><br>
                <label title="グルーピング機能の使用の有無"><input type="checkbox" name="feature" value="groupingCountbool"><?= translate('teachertrue.php_240行目_グループ化有無') ?><span class="info-icon" data-feature-name="groupingCountbool">ⓘ</span></label><br>
                <label title="横軸方向にUターンが行われた回数"><input type="checkbox" name="feature" value="xUturnCount"><?= translate('teachertrue.php_241行目_x軸Uターン回数') ?><span class="info-icon" data-feature-name="xUturnCount">ⓘ</span></label><br>
                <label title="縦軸方向にUターンが行われた回数"><input type="checkbox" name="feature" value="yUturnCount"><?= translate('teachertrue.php_242行目_y軸Uターン回数') ?><span class="info-icon" data-feature-name="yUturnCount">ⓘ</span></label><br>
                <label title="マウスカーソルがレジスタからレジスタに移動した回数"><input type="checkbox" name="feature" value="register_move_count1"><?= translate('teachertrue.php_243行目_レジスタ➡レジスタへの移動回数') ?><span class="info-icon" data-feature-name="register_move_count1">ⓘ</span></label><br>
                <label title="マウスカーソルがレジスタからレジスタ外に移動した回数"><input type="checkbox" name="feature" value="register_move_count2"><?= translate('teachertrue.php_244行目_レジスタ➡レジスタ外への移動回数') ?><span class="info-icon" data-feature-name="register_move_count2">ⓘ</span></label><br>
                <label title="マウスカーソルがレジスタ外からレジスタに移動した回数"><input type="checkbox" name="feature" value="register_move_count3"><?= translate('teachertrue.php_245行目_レジスタ外➡レジスタへの移動回数') ?><span class="info-icon" data-feature-name="register_move_count3">ⓘ</span></label><br>
                <label title="マウスカーソルがレジスタからレジスタに移動したかの有無"><input type="checkbox" name="feature" value="register01count1"><?= translate('teachertrue.php_246行目_レジスタ➡レジスタへの移動有無') ?><span class="info-icon" data-feature-name="register01count1">ⓘ</span></label><br>
                <label title="マウスカーソルがレジスタからレジスタ外に移動したかの有無"><input type="checkbox" name="feature" value="register01count2"><?= translate('teachertrue.php_247行目_レジスタ➡レジスタ外への移動有無') ?><span class="info-icon" data-feature-name="register01count2">ⓘ</span></label><br>
                <label title="マウスカーソルがレジスタ外からレジスタに移動したかの有無"><input type="checkbox" name="feature" value="register01count3"><?= translate('teachertrue.php_248行目_レジスタ外➡レジスタへの移動有無') ?><span class="info-icon" data-feature-name="register01count3">ⓘ</span></label><br>
                <label title="レジスタを触れた移動回数の合計"><input type="checkbox" name="feature" value="registerDDCount"><?= translate('teachertrue.php_249行目_レジスタに関する合計の移動回数') ?><span class="info-icon" data-feature-name="registerDDCount">ⓘ</span></label><br>
                <label title="D&D中に横軸方向にUターンが行われた回数"><input type="checkbox" name="feature" value="xUturnCountDD"><?= translate('teachertrue.php_250行目_x軸UターンD&D回数') ?><span class="info-icon" data-feature-name="xUturnCountDD">ⓘ</span></label><br>
                <label title="D&D中に縦軸方向にUターンが行われた回数"><input type="checkbox" name="feature" value="yUturnCountDD"><?= translate('teachertrue.php_251行目_y軸UターンD&D回数') ?><span class="info-icon" data-feature-name="yUturnCountDD">ⓘ</span></label><br>
                <label title="最終ドロップから解答終了までの時間"><input type="checkbox" name="feature" value="FromlastdropToanswerTime"><?= translate('teachertrue.php_252行目_最終ドロップ後時間') ?><span class="info-icon" data-feature-name="FromlastdropToanswerTime">ⓘ</span></label><br>
                <button type="button" id="apply-clustering-btn"><?= translate('teachertrue.php_254行目_適用') ?></button>
            </form>
        </div>
    </div>

    <div id="feature-detail-modal" class="feature-detail-modal">
        <div class="modal-content feature-detail-modal-content">
            <span class="close-detail-modal">&times;</span>
            <h3 id="detail-feature-title"></h3>
            <p id="detail-feature-description"></p>
        </div>
    </div>

    <script>
        // ======================= ▼▼▼ ここから修正 ▼▼▼ =======================
        // 翻訳テキストを一元管理するオブジェクト (グローバルスコープ)
        const translations = {
            // グラフ・ボタン関連
            btnFeature: <?= json_encode(translate('teachertrue.php_404行目_グラフ描画特徴量')) ?>,
            btnEstimate: <?= json_encode(translate('teachertrue.php_405行目_迷い推定')) ?>,
            chartLabelIncorrect: <?= json_encode(translate('teachertrue.php_429行目_不正解率(%)')) ?>,
            chartLabelTime: <?= json_encode(translate('teachertrue.php_430行目_解答時間(秒)')) ?>,
            btnClustering: <?= json_encode(translate('teachertrue.php_771行目_クラスタリング')) ?>,
            username: <?= json_encode(translate('teachertrue.php_339行目_ユーザー名')) ?>,
            average: <?= json_encode(translate('teachertrue.php_567行目_平均')) ?>,

            // 詳細情報セクション関連
            loading: <?= json_encode(translate('machineLearning_sample.php_1498行目_ロード中')) ?>,
            selectStudent: <?= json_encode(translate('machineLearning_sample.php_1501行目_学習者を選択してください')) ?>,
            selectStudentInfo: <?= json_encode(translate('machineLearning_sample.php_1502行目_学習者情報を選択してください')) ?>,
            pleaseSelect: <?= json_encode(translate('machineLearning_sample.php_1468行目_選択してください')) ?>,
            difficulty: <?= json_encode(translate('machineLearning_sample.php_1513行目_難易度')) ?>,
            hesitation: <?= json_encode(translate('machineLearning_sample.php_1513行目_迷い')) ?>,
            studentName: <?= json_encode(translate('machineLearning_sample.php_1528行目_学習者名')) ?>,
            className: <?= json_encode(translate('machineLearning_sample.php_1529行目_クラス名')) ?>,
            toeicLevel: <?= json_encode(translate('machineLearning_sample.php_1530行目_TOEICレベル')) ?>,
            eikenLevel: <?= json_encode(translate('machineLearning_sample.php_1531行目_英検レベル')) ?>,
            totalAnswers: <?= json_encode(translate('machineLearning_sample.php_1534行目_総解答数')) ?>,
            accuracy: <?= json_encode(translate('machineLearning_sample.php_1535行目_正解率')) ?>,
            hesitationRate: <?= json_encode(translate('machineLearning_sample.php_1536行目_迷い率')) ?>,
            error: <?= json_encode(translate('dbsyori.php_21行目_エラー')) ?>,
            problemInfo: <?= json_encode(translate('machineLearning_sample.php_1570行目_問題情報')) ?>,
            correctSentence: <?= json_encode(translate('machineLearning_sample.php_1575行目_正解文')) ?>,
            japaneseSentence: <?= json_encode(translate('machineLearning_sample.php_1578行目_日本語文')) ?>,
            grammarItem: <?= json_encode(translate('machineLearning_sample.php_1579行目_文法項目')) ?>,
            wordCount: <?= json_encode(translate('machineLearning_sample.php_1580行目_単語数')) ?>,
            noInitialData: <?= json_encode(translate('machineLearning_sample.php_1623行目_初期表示用のデータが見つかりません')) ?>,
            noGroupingPerformed: <?= json_encode(translate('machineLearning_sample.php_1641行目_グルーピングが行われていません')) ?>,
            date: <?= json_encode(translate('machineLearning_sample.php_1644行目_回答日時')) ?>,
            finalAnswer: <?= json_encode(translate('machineLearning_sample.php_1645行目_最終回答文')) ?>,
            time: <?= json_encode(translate('machineLearning_sample.php_1646行目_解答時間')) ?>,
            correctIncorrect: <?= json_encode(translate('machineLearning_sample.php_1647行目_正誤')) ?>,
            replayTrace: <?= json_encode(translate('teachertrue.php_軌跡再現')) ?>,
            label: <?= json_encode(translate('machineLearning_sample.php_1649行目_Label')) ?>,
            attemptInfoNotFound: <?= json_encode(translate('machineLearning_sample.php_1662行目_選択された試行回数の情報が見つかりません')) ?>,
            dataFetchFailed: <?= json_encode(translate('machineLearning_sample.php_1667行目_データの取得に失敗しました')) ?>,
            grammarItemStatsTitle: <?= json_encode(translate('machineLearning_sample.php_1750行目_文法項目ごとの正解率と迷い率')) ?>,
            correctAnswers: <?= json_encode(translate('machineLearning_sample.php_1684行目_正解数')) ?>,
            hesitations: <?= json_encode(translate('machineLearning_sample.php_1685行目_迷い数')) ?>,
            incorrectRate: <?= json_encode(translate('machineLearning_sample.php_1686行目_不正解率')) ?>,
            chartYAxisLabel: <?= json_encode(translate('machineLearning_sample.php_1780行目_割合(%)')) ?>
        };
        // ======================= ▲▲▲ 修正はここまで ▲▲▲ =======================

        document.addEventListener('DOMContentLoaded', function() {
            
            // 特徴量ごとの説明データを定義
            const featureDescriptions = {
                "notaccuracy": "<?= translate('teachertrue.php_description_notaccuracy', '解答全体のうち、正解しなかった問題の割合。') ?>",
                "Time": "<?= translate('teachertrue.php_description_Time', '問題が表示されてから解答が終了するまでの時間（秒）。') ?>",
                "distance": "<?= translate('teachertrue.php_description_distance', '問題解答中にマウスカーソルが移動した総距離（ピクセル単位）。') ?>",
                "averageSpeed": "<?= translate('teachertrue.php_description_averageSpeed', '問題解答中のマウスカーソルの平均速度。') ?>",
                "maxSpeed": "<?= translate('teachertrue.php_description_maxSpeed', '問題解答中のマウスカーソルの最大速度。') ?>",
                "thinkingTime": "<?= translate('teachertrue.php_description_thinkingTime', '問題が表示されてから最初の単語をドラッグするまでの時間。') ?>",
                "answeringTime": "<?= translate('teachertrue.php_description_answeringTime', '最初の単語をドロップしてから解答が終了するまでの時間。') ?>",
                "totalStopTime": "<?= translate('teachertrue.php_description_totalStopTime', 'マウスカーソルの動きが一定時間停止していた合計時間。') ?>",
                "maxStopTime": "<?= translate('teachertrue.php_description_maxStopTime', 'マウスカーソルの動きが停止していた時間のうち最も長いもの。') ?>",
                "totalDDIntervalTime": "<?= translate('teachertrue.php_description_totalDDIntervalTime', '単語のドラッグ＆ドロップ操作間の合計時間。') ?>",
                "maxDDIntervalTime": "<?= translate('teachertrue.php_description_maxDDIntervalTime', '単語のドラッグ＆ドロップ操作間の時間のうち最も長いもの。') ?>",
                "maxDDTime": "<?= translate('teachertrue.php_description_maxDDTime', '単語をドラッグしてからドロップするまでの合計時間。') ?>",
                "minDDTime": "<?= translate('teachertrue.php_description_minDDTime', '単語をドラッグしてからドロップするまでの時間のうち最も短いもの。') ?>",
                "DDCount": "<?= translate('teachertrue.php_description_DDCount', 'ドラッグ＆ドロップ操作の総回数。') ?>",
                "groupingDDCount": "<?= translate('teachertrue.php_description_groupingDDCount', '単語をグループ化する操作（単語を別の単語の上にドロップ）が行われた回数。') ?>",
                "groupingCountbool": "<?= translate('teachertrue.php_description_groupingCountbool', '単語のグループ化操作が一度でも行われたかどうか（0:なし, 1:あり）。') ?>",
                "xUturnCount": "<?= translate('teachertrue.php_description_xUturnCount', 'マウスカーソルの動きが水平方向（X軸）で反転した回数。') ?>",
                "yUturnCount": "<?= translate('teachertrue.php_description_yUturnCount', 'マウスカーソルの動きが垂直方向（Y軸）で反転した回数。') ?>",
                "register_move_count1": "<?= translate('teachertrue.php_description_register_move_count1', 'マウスカーソルが単語の選択肢エリア（レジスタ）から別の選択肢エリアに移動した回数。') ?>",
                "register_move_count2": "<?= translate('teachertrue.php_description_register_move_count2', 'マウスカーソルが単語の選択肢エリア（レジスタ）から解答欄エリアに移動した回数。') ?>",
                "register_move_count3": "<?= translate('teachertrue.php_description_register_move_count3', 'マウスカーソルが解答欄エリアから単語の選択肢エリア（レジスタ）に移動した回数。') ?>",
                "register01count1": "<?= translate('teachertrue.php_description_register01count1', 'レジスタからレジスタへの移動が一度でもあったか（0:なし, 1:あり）。') ?>",
                "register01count2": "<?= translate('teachertrue.php_description_register01count2', 'レジスタからレジスタ外への移動が一度でもあったか（0:なし, 1:あり）。') ?>",
                "register01count3": "<?= translate('teachertrue.php_description_register01count3', 'レジスタ外からレジスタへの移動が一度でもあったか（0:なし, 1:あり）。') ?>",
                "registerDDCount": "<?= translate('teachertrue.php_description_registerDDCount', '単語の選択肢エリア（レジスタ）に関連する移動の総回数。') ?>",
                "xUturnCountDD": "<?= translate('teachertrue.php_description_xUturnCountDD', 'ドラッグ＆ドロップ中にマウスの動きが水平方向（X軸）で反転した回数。') ?>",
                "yUturnCountDD": "<?= translate('teachertrue.php_description_yUturnCountDD', 'ドラッグ＆ドロップ中にマウスの動きが垂直方向（Y軸）で反転した回数。') ?>",
                "FromlastdropToanswerTime": "<?= translate('teachertrue.php_description_FromlastdropToanswerTime', '最後の単語をドロップしてから解答が終了するまでの時間。') ?>"
            };

            const infoIcons = document.querySelectorAll('.info-icon');
            const detailModal = document.getElementById('feature-detail-modal');
            const detailTitle = document.getElementById('detail-feature-title');
            const detailDescription = document.getElementById('detail-feature-description');
            const closeDetailModal = document.querySelector('#feature-detail-modal .close-detail-modal');

            infoIcons.forEach(icon => {
                icon.addEventListener('click', function(event) {
                    event.stopPropagation(); 
                    event.preventDefault(); 

                    const featureName = this.dataset.featureName;
                    const description = featureDescriptions[featureName] || "<?= translate('teachertrue.php_description_not_found', 'この特徴量の説明はまだありません。') ?>";

                    let featureLabelText = "";
                    const parentLabel = this.closest('label');
                    if (parentLabel) {
                        const inputElement = parentLabel.querySelector('input[type="checkbox"]');
                        if (inputElement && inputElement.nextSibling) {
                            featureLabelText = inputElement.nextSibling.textContent.trim();
                        } else {
                            featureLabelText = featureName;
                        }
                    } else {
                        featureLabelText = featureName;
                    }

                    detailTitle.textContent = featureLabelText;
                    detailDescription.textContent = description;
                    detailModal.style.display = 'block';
                });
            });

            if(closeDetailModal) {
                closeDetailModal.addEventListener('click', function() {
                    detailModal.style.display = 'none';
                });
            }

            window.addEventListener('click', function(event) {
                if (event.target == detailModal) {
                    detailModal.style.display = 'none';
                }
            });

            // --- グループ別グラフの描画 ---
            const groupContainer = document.getElementById('group-data-container');
            if (groupContainer && typeof groupData !== 'undefined') {
                groupData.forEach((group, index) => {
                    const container = document.createElement('div');
                    container.classList.add('class-card');
                    container.innerHTML = `
                    <h3>${group.group_name}
                        <button onclick="openFeatureModal(${index}, false)">${translations.btnFeature}</button>
                        <button onclick="openEstimatePage(${index})">${translations.btnEstimate}</button>
                    </h3>
                    <div class="chart-row"><canvas id="dual-axis-chart-${index}"></canvas></div>`;
                    groupContainer.appendChild(container);
                    const labels = group.students.map(s => s.name);
                    const notaccuracyData = group.students.map(s => s.notaccuracy);
                    const timeData = group.students.map(s => s.time);
                    createDualAxisChart(
                        document.getElementById(`dual-axis-chart-${index}`).getContext('2d'),
                        labels,
                        notaccuracyData,
                        timeData,
                        translations.chartLabelIncorrect,
                        translations.chartLabelTime,
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        translations.chartLabelIncorrect,
                        translations.chartLabelTime,
                        existingClassCharts,
                        index
                    );
                });
            }

            // --- クラス別グラフの描画 ---
            const classContainer = document.getElementById('class-data-container');
            if (classContainer && typeof classData !== 'undefined') {
                classData.forEach((classInfo, index) => {
                    const container = document.createElement('div');
                    container.classList.add('class-card');
                    container.innerHTML = `
                    <h3>${classInfo.class_name}
                        <button onclick="openClassFeatureModal(${index})">${translations.btnFeature}</button>
                        <button onclick="openClassEstimatePage(${index})">${translations.btnEstimate}</button>
                        <button onclick="openClusteringModal(${index})">${translations.btnClustering}</button>
                    </h3>
                    <div class="chart-row"><canvas id="class-dual-axis-chart-${index}"></canvas></div>`;
                    classContainer.appendChild(container);
                    const labels = classInfo.class_students.map(s => s.name);
                    const notaccuracyData = classInfo.class_students.map(s => s.notaccuracy);
                    const timeData = classInfo.class_students.map(s => s.time);
                    createDualAxisChart(
                        document.getElementById(`class-dual-axis-chart-${index}`).getContext('2d'),
                        labels,
                        notaccuracyData,
                        timeData,
                        translations.chartLabelIncorrect,
                        translations.chartLabelTime,
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        translations.chartLabelIncorrect,
                        translations.chartLabelTime,
                        existingOverallCharts,
                        index
                    );
                });
            }

            // クラスタリングボタンのイベントリスナー
            document.getElementById('apply-clustering-btn').onclick = function() {
                const selectedFeatures = Array.from(document.querySelectorAll('#clustering-feature-form input[type="checkbox"]:checked')).map(input => input.value);
                if (selectedFeatures.length === 0) {
                    alert(<?= json_encode(translate('teachertrue.php_822行目_少なくとも1つの特徴量を選択してください。')) ?>);
                    return;
                }
                const clusterCount = document.getElementById('clustering-input').value;
                const method = document.getElementById('clustering-method').value;
                const classInfo = classData[selectedClassIndex];
                const studentIds = classInfo.class_students.map(student => student.student_id).join(',');
                const params = new URLSearchParams({
                    features: selectedFeatures.join(','),
                    studentIDs: studentIds,
                    clusterCount: clusterCount,
                    method: method
                });

                fetch('perform_clustering.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params.toString()
                    })
                    .then(response => response.text())
                    .then(data => {
                        try {
                            const jsonData = JSON.parse(data);
                            if (jsonData.error) {
                                alert(jsonData.error);
                                return;
                            }
                            closeClusteringModal();
                            displayClusteringResultsFromJSON(jsonData);
                            displayClusteringResults_groupFromJSON(jsonData);
                        } catch (e) {
                            console.error('JSON 解析エラー:', e, 'レスポンス:', data);
                        }
                    })
                    .catch(error => console.error('エラー:', error));
            };

            // ======================= ▼▼▼ ここから修正 ▼▼▼ =======================
            // 学習者詳細情報関連のコード
            const uidSelect = document.getElementById('uid-select');
            const widSelect = document.getElementById('wid-select');
            const studentDetailsmaininfo = document.getElementById('student-details-maininfo');
            const widDetailsmaininfostu = document.getElementById('wid-details-maininfo-stu');
            const widDetailsmaininfoall = document.getElementById('wid-details-maininfo-all');

            if (uidSelect) {
                uidSelect.addEventListener('change', async function() {
                    const selectedUid = uidSelect.value;
                    widSelect.innerHTML = `<option value="">${translations.loading}</option>`;
                    if (!selectedUid) {
                        widSelect.innerHTML = `<option value="">${translations.selectStudent}</option>`;
                        studentDetailsmaininfo.innerHTML = `<p>${translations.selectStudentInfo}</p>`;
                        return;
                    }
                    try {
                        const widResponse = await fetch(`get_wid.php?uid=${selectedUid}`);
                        if (!widResponse.ok) throw new Error(`HTTP error! status: ${widResponse.status}`);
                        const widData = await widResponse.json();
                        widSelect.innerHTML = `<option value="">${translations.pleaseSelect}</option>`;
                        widData.forEach(wid => {
                            widSelect.innerHTML += `<option value="${wid.WID}">
                                                    ${wid.WID}: ${wid.Sentence}: ${translations.difficulty}${wid.level}: ${translations.hesitation}:${wid.Understand} 
                                                    ${wid.Understand === '迷い有り' ? '(★)' : ''}
                                                </option>`;
                        });

                        const studentResponse = await fetch(`get_student_info.php?uid=${selectedUid}`);
                        if (!studentResponse.ok) throw new Error(`HTTP error! status: ${studentResponse.status}`);
                        const studentData = await studentResponse.json();
                        const studentDatainfo = studentData.userinfo;

                        studentDetailsmaininfo.innerHTML = `
                                    <div id="student-info-title" style="display:flex; gap: 10px;">
                                    <h3>${translations.studentName}:${studentDatainfo.Name}</h3>
                                    <h3>${translations.className}:${studentDatainfo.ClassID}</h3>
                                    <h3>${translations.toeicLevel}:${studentDatainfo.toeic_level}</h3>
                                    <h3>${translations.eikenLevel}:${studentDatainfo.eiken_level}</h3>
                                    </div>
                                    <div id="student-info-accuracy" style="display:flex; gap: 10px;">
                                    <p>${translations.totalAnswers}:${studentDatainfo.total_answers}</p>
                                    <p>${translations.accuracy}:${studentDatainfo.accuracy}%</p>
                                    <p>${translations.hesitationRate}:${studentDatainfo.hesitation_rate}%</p>
                                    </div>`;
                        displayGrammarStats(studentData.grammarStats);
                    } catch (error) {
                        widSelect.innerHTML = `<option value="">${translations.error}</option>`;
                        console.error(error);
                    }
                });
            }
            if (widSelect) {
                widSelect.addEventListener('change', async function() {
                    const selectedWid = this.value;
                    const selectedUid = uidSelect.value;
                    if (!selectedWid || !selectedUid) {
                        widDetailsmaininfostu.innerHTML = `<p>${translations.selectStudentInfo}</p>`;
                        return;
                    }
                    try {
                        const answerResponse = await fetch(`get_answer_info.php?uid=${selectedUid}&wid=${selectedWid}`);
                        if (!answerResponse.ok) throw new Error(`HTTP error! status:${answerResponse.status}`);
                        const answerDetails = await answerResponse.json();
                        const quesaccuracy = answerDetails.quesaccuracy ?? "N/A";
                        const queshesitation_rate = answerDetails.queshesitation_rate ?? "N/A";
                        const attempt1 = answerDetails.widinfo.find(detail => detail.attempt == 1);
                        const attemptSelect = document.createElement('select');
                        attemptSelect.id = 'attempt-select';
                        attemptSelect.innerHTML = `<option value="">${translations.pleaseSelect}</option>`;
                        answerDetails.widinfo.forEach(detail => {
                            const option = document.createElement('option');
                            option.value = detail.attempt;
                            option.textContent = `Attempt ${detail.attempt}`;
                            attemptSelect.appendChild(option);
                        });

                        if (attempt1) {
                            widDetailsmaininfoall.innerHTML = `
                                <div style="border: 1px solid #ccc; padding: 15px; border-radius: 8px; background-color: #f9f9f9;">
                                    <h3 style="color: #333; text-align: center; margin-bottom: 20px;">${translations.problemInfo}</h3>
                                    <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                                        <div style="flex: 1; min-width: 250px;">
                                            <p><strong>${translations.accuracy}:</strong> ${quesaccuracy}%</p>
                                            <p><strong>${translations.hesitationRate}:</strong> ${queshesitation_rate}%</p>
                                            <p><strong>${translations.correctSentence}:</strong> ${attempt1.Sentence}</p>
                                        </div>
                                        <div style="flex: 1; min-width: 250px;">
                                            <p><strong>${translations.japaneseSentence}:</strong> ${attempt1.Japanese}</p>
                                            <p><strong>${translations.grammarItem}:</strong> ${attempt1.grammar}</p>
                                            <p><strong>${translations.wordCount}:</strong> ${attempt1.wordnum}</p>
                                        </div>
                                    </div>
                                </div>`;
                        } else {
                            widDetailsmaininfoall.innerHTML = `<p>${translations.noInitialData}</p>`;
                        }

                        widDetailsmaininfostu.innerHTML = '';
                        widDetailsmaininfostu.appendChild(attemptSelect);
                        const attemptDetailsContainer = document.createElement('div');
                        attemptDetailsContainer.id = 'attempt-details';
                        widDetailsmaininfostu.appendChild(attemptDetailsContainer);

                        function getAttemptDetailHTML(detail) {
                            const labelText = detail.Label ? detail.Label : translations.noGroupingPerformed;
                            const traceLink = `./mousemove/mousemove.php?UID=${encodeURIComponent(selectedUid)}&WID=${encodeURIComponent(selectedWid)}&LogID=${encodeURIComponent(detail.attempt)}`;
                            return `<p>${translations.date}: ${detail.Date}</p>
                                <p>${translations.finalAnswer}: ${detail.EndSentence}</p>
                                <p>${translations.time}: ${detail.Time}s</p>
                                <p>${translations.correctIncorrect}: ${detail.TF}</p>
                                <p>${translations.hesitation}: ${detail.Understand}</p>
                                <p>${translations.replayTrace}: <a href="${traceLink}" target="_blank">${translations.replayTrace}</a></p>
                                <p>${translations.label}: ${labelText}</p>`;
                        }

                        if (attempt1) {
                            attemptSelect.value = 1;
                            attemptDetailsContainer.innerHTML = getAttemptDetailHTML(attempt1);
                        }

                        attemptSelect.addEventListener('change', function() {
                            const selectedAttempt = this.value;
                            const selectedDetail = answerDetails.widinfo.find(detail => detail.attempt == selectedAttempt);
                            if (selectedDetail) {
                                attemptDetailsContainer.innerHTML = getAttemptDetailHTML(selectedDetail);
                            } else {
                                attemptDetailsContainer.innerHTML = `<p>${translations.attemptInfoNotFound}</p>`;
                            }
                        });
                    } catch (error) {
                        console.error(error);
                        widDetailsmaininfostu.innerHTML = `<p>${translations.dataFetchFailed}</p>`;
                    }
                });
            }

            function displayGrammarStats(grammarStats) {
                const grammarStatsDiv = document.getElementById('student-details-grammar');
                grammarStatsDiv.style.display = 'flex';
                grammarStatsDiv.style.flexDirection = 'row';
                grammarStatsDiv.style.justifyContent = 'space-between';
                grammarStatsDiv.style.alignItems = 'flex-start';

                let tableHTML = `<div style="flex: 1; padding-right: 20px;"> <table class = "table2">
                            <thead><tr>
                                <th>${translations.grammarItem}</th>
                                <th>${translations.totalAnswers}</th>
                                <th>${translations.correctAnswers}</th>
                                <th>${translations.hesitations}</th>
                                <th>${translations.incorrectRate}</th>
                                <th>${translations.hesitationRate}</th>
                            </tr></thead><tbody>`;
                const labels = [];
                const accuracyData = [];
                const hesitationData = [];
                for (const [grammar, stats] of Object.entries(grammarStats)) {
                    notaccuracy_grammar = (100 - stats.accuracy).toFixed(2);
                    tableHTML += `<tr>
                            <td>${stats.grammar}</td>
                            <td>${stats.total_answers}</td>
                            <td>${stats.correct_answers}</td>
                            <td>${stats.hesitate_count}</td>
                            <td>${notaccuracy_grammar}%</td>
                            <td>${stats.hesitation_rate}%</td>
                        </tr>`;
                    labels.push(stats.grammar);
                    accuracyData.push(notaccuracy_grammar);
                    hesitationData.push(stats.hesitation_rate);
                }
                tableHTML += `</tbody></table></div>`;
                const chartHTML = `<div style="flex: 1;"> <canvas id="grammarChart"></canvas></div>`;
                grammarStatsDiv.innerHTML = tableHTML + chartHTML;

                const ctx = document.getElementById('grammarChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                                label: `${translations.incorrectRate} (%)`,
                                data: accuracyData,
                                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            },
                            {
                                label: `${translations.hesitationRate} (%)`,
                                data: hesitationData,
                                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                            }
                        ]
                    },
                    options: {
                        plugins: {
                            title: {
                                display: true,
                                text: translations.grammarItemStatsTitle
                            }
                        },
                        scales: {
                            x: {
                                title: { display: true, text: translations.grammarItem }
                            },
                            y: {
                                title: { display: true, text: translations.chartYAxisLabel }
                            }
                        }
                    }
                });
            }
            // ======================= ▲▲▲ 修正はここまで ▲▲▲ =======================
        });

        // --- グローバル関数定義 ---
        let existingClassCharts = [];
        let existingOverallCharts = [];
        let selectedGroupIndex;
        let selectedClassIndex;
        let currentChart = null;

        function openEstimatePage(groupIndex) {
            const group = groupData[groupIndex];
            if (!group || !group.students) {
                alert(<?= json_encode(translate('teachertrue.php_265行目_グループに学習者が登録されていません。')) ?>);
                return;
            }
            const studentIds = group.students.map(student => student.student_id).join(',');
            window.location.href = `machineLearning_sample.php?students=${encodeURIComponent(studentIds)}`;
        }

        function openClassEstimatePage(classIndex) {
            const classInfo = classData[classIndex];
            if (!classInfo || !classInfo.class_students) {
                alert(<?= json_encode(translate('teachertrue.php_283行目_クラスに学習者が登録されていません。')) ?>);
                return;
            }
            const studentIds = classInfo.class_students.map(student => student.student_id).join(',');
            window.location.href = `machineLearning_sample.php?students=${encodeURIComponent(studentIds)}`;
        }

        function createDualAxisChart(ctx, labels, data1, data2, label1, label2, color1, color2, yText1, yText2, chartArray, chartIndex) {
            if (chartArray[chartIndex]) {
                chartArray[chartIndex].destroy();
            }
            chartArray[chartIndex] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label1,
                        data: data1,
                        backgroundColor: color1,
                        yAxisID: 'y1'
                    }, {
                        label: label2,
                        data: data2,
                        backgroundColor: color2,
                        yAxisID: 'y2'
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: translations.username,
                                font: { size: 20 }
                            },
                            ticks: { font: { size: 16 } }
                        },
                        y1: {
                            title: {
                                display: true,
                                text: yText1,
                                font: { size: 20 }
                            },
                            ticks: { font: { size: 16 } },
                            position: 'left',
                            beginAtZero: true
                        },
                        y2: {
                            title: {
                                display: true,
                                text: yText2,
                                font: { size: 20 }
                            },
                            ticks: { font: { size: 16 } },
                            position: 'right',
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            labels: { font: { size: 20 } }
                        }
                    }
                }
            });
        }

        function openFeatureModal(index, isOverall) {
            selectedGroupIndex = index;
            document.getElementById('feature-modal').style.display = 'block';
            document.getElementById('apply-features-btn').onclick = () => applySelectedFeatures(isOverall ? existingOverallCharts : existingClassCharts, index, isOverall);
        }

        function openClassFeatureModal(index) {
            openFeatureModal(index, true);
        }

        function closeFeatureModal() {
            document.getElementById('feature-modal').style.display = 'none';
            document.getElementById('feature-form').reset();
        }

        function applySelectedFeatures(chartArray, chartIndex, isOverall) {
            const selectedFeatures = Array.from(document.querySelectorAll('#feature-form input[type="checkbox"]:checked')).map(input => input.value);
            if (selectedFeatures.includes('notaccuracy')) {
                const otherFeature = selectedFeatures.find(feature => feature !== 'notaccuracy');
                let group = isOverall ? classData[chartIndex] : groupData[chartIndex];
                const students = isOverall ? group.class_students : group.students;
                const labels = students.map(student => student.name);
                const notaccuracyData = students.map(student => student.notaccuracy);
                if (!otherFeature) {
                    alert(<?= json_encode(translate('teachertrue.php_489行目_不正解率と一緒にもう1つの特徴量を選択してください。')) ?>);
                    return;
                }
                const studentIDs = students.map(student => student.student_id).join(',');
                fetch('fetch_feature_data.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            features: otherFeature,
                            studentIDs: studentIDs
                        })
                    })
                    .then(response => response.json()).then(data => {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }
                        const otherFeatureData = data.map(item => item.featureA_avg);
                        const canvasId = isOverall ? `class-dual-axis-chart-${chartIndex}` : `dual-axis-chart-${chartIndex}`;
                        const label1 = translations.chartLabelIncorrect;
                        const label2 = `${otherFeature} ${translations.average}`;
                        createDualAxisChart(document.getElementById(canvasId).getContext('2d'), labels, notaccuracyData, otherFeatureData, label1, label2, 'rgba(54, 162, 235, 0.6)', 'rgba(255, 99, 132, 0.6)', label1, label2, chartArray, chartIndex);
                        closeFeatureModal();
                    }).catch(error => console.error('エラー:', error));
            } else {
                if (selectedFeatures.length !== 2) {
                    alert(<?= json_encode(translate('teachertrue.php_598行目_2つの特徴量を選択してください。')) ?>);
                    return;
                }
                let group = isOverall ? classData[chartIndex] : groupData[chartIndex];
                const studentIDs = (isOverall ? group.class_students : group.students).map(student => student.student_id).join(',');
                fetch('fetch_feature_data.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            features: selectedFeatures.join(','),
                            studentIDs: studentIDs
                        })
                    })
                    .then(response => response.json()).then(data => {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }
                        const labels = data.map(item => item.name);
                        const featureAData = data.map(item => item.featureA_avg);
                        const featureBData = data.map(item => item.featureB_avg);
                        const canvasId = isOverall ? `class-dual-axis-chart-${chartIndex}` : `dual-axis-chart-${chartIndex}`;
                        const label1 = `${selectedFeatures[0]} ${translations.average}`;
                        const label2 = `${selectedFeatures[1]} ${translations.average}`;
                        createDualAxisChart(document.getElementById(canvasId).getContext('2d'), labels, featureAData, featureBData, label1, label2, 'rgba(54, 162, 235, 0.6)', 'rgba(255, 99, 132, 0.6)', label1, label2, chartArray, chartIndex);
                        closeFeatureModal();
                    }).catch(error => console.error('エラー:', error));
            }
        }

        function openClusteringModal(index) {
            selectedClassIndex = index;
            document.getElementById('clustering-modal').style.display = 'block';
        }

        function closeClusteringModal() {
            document.getElementById('clustering-modal').style.display = 'none';
            document.getElementById('clustering-feature-form').reset();
        }

        function displayClusteringResults_groupFromJSON(jsonData) {
            const container = document.getElementById('cluster-data');
            if (!container) return;
            const clusters = jsonData.clusters?.clusters;
            if (!clusters) return;
            Object.keys(clusters).forEach(clusterKey => {
                const clusterDiv = document.createElement('div');
                clusterDiv.className = 'cluster-group';
                const clusterHeader = document.createElement('h3');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = clusterKey;
                checkbox.className = 'cluster-checkbox';
                clusterHeader.textContent = `${translations.btnClustering} ${clusterKey}`;
                clusterHeader.prepend(checkbox);
                clusterDiv.appendChild(clusterHeader);
                const studentList = document.createElement('ul');
                Object.keys(clusters[clusterKey]).forEach(groupIndex => {
                    const listItem = document.createElement('li');
                    listItem.textContent = `${translations.studentName}:${clusters[clusterKey][groupIndex].name}`;
                    studentList.appendChild(listItem);
                });
                clusterDiv.appendChild(studentList);
                container.appendChild(clusterDiv);
            });
            const groupButton = document.createElement('button');
            groupButton.textContent = <?= json_encode(translate('teachertrue.php_929行目_グループ化')) ?>;
            groupButton.onclick = () => groupSelectedClusters(clusters);
            container.appendChild(groupButton);
        }

        function groupSelectedClusters(clusters) {
            const selectedCheckboxes = document.querySelectorAll('.cluster-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                alert(<?= json_encode(translate('teachertrue.php_941行目_少なくとも1つのクラスタを選択してください。')) ?>);
                return;
            }
            const clustersData = [];
            selectedCheckboxes.forEach(checkbox => {
                const clusterKey = checkbox.value;
                const clusterName = `${translations.btnClustering} ${clusterKey}`;
                const studentIds = clusters[clusterKey].map(student => student.id);
                clustersData.push({
                    group_name: clusterName,
                    students: studentIds
                });
            });
            fetch('group_students.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(clustersData)
                })
                .then(response => response.text()).then(data => {
                    alert(<?= json_encode(translate('teachertrue.php_971行目_選択されたクラスタのグループ化が完了しました。')) ?>);
                    window.location.reload();
                }).catch(error => console.error('エラー:', error));
        }

        function displayClusteringResultsFromJSON(jsonData) {
            const container = document.getElementById('cluster-data');
            if (!container) return;
            container.innerHTML = '';
            alert(<?= json_encode(translate('teachertrue.php_990行目_クラスタリング機能によりグルーピングされた学習者群は...')) ?>);
            const canvas = document.createElement('canvas');
            canvas.id = 'cluster-visualization';
            canvas.width = 800;
            canvas.height = 400;
            container.appendChild(canvas);
            const ctx = canvas.getContext('2d');
            const clusterData = jsonData.clusters;
            const clusterColors = ['rgba(255, 0, 0, 0.7)', 'rgba(0, 255, 0, 0.7)', 'rgba(0, 0, 255, 0.7)', 'rgba(255, 255, 0, 0.7)', 'rgba(255, 0, 255, 0.7)'];
            const datasets = Object.keys(clusterData).flatMap(clusterKey => clusterData[clusterKey].flatMap((pointGroup, groupIndex) => pointGroup.map((point) => ({
                label: `Cluster ${groupIndex}`,
                data: [{
                    x: point.pca1,
                    y: point.pca2
                }],
                backgroundColor: clusterColors[groupIndex] || `rgba(${Math.random() * 255}, ${Math.random() * 255}, ${Math.random() * 255}, 0.7)`,
                borderColor: 'rgba(0, 0, 0, 1)',
                borderWidth: 1,
                pointRadius: 5
            }))));
            if (currentChart) currentChart.destroy();
            currentChart = new Chart(ctx, {
                type: 'scatter',
                data: {
                    datasets: datasets
                },
                options: {
                    plugins: {
                        title: {
                            display: true,
                            text: <?= json_encode(translate('teachertrue.php_1049行目_クラスタリング結果 (PCA可視化)')) ?>
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: <?= json_encode(translate('teachertrue.php_1056行目_次元1')) ?>
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: <?= json_encode(translate('teachertrue.php_1062行目_次元2')) ?>
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>
<?php
                        // すべての処理の最後に一度だけ接続を閉じる
                        if (isset($conn) && $conn instanceof mysqli) {
                            $conn->close();
                        }
                    }
?>
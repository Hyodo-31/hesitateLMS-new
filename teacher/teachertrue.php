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
                <!-- <li><a href="#"><?= translate('teachertrue.php_24行目_ホーム') ?></a></li> -->
                <!-- <li><a href="machineLearning_sample.php"><?= translate('teachertrue.php_25行目_迷い推定・機械学習') ?></a></li> -->
                <!-- <li><a href="register-student.php"><?= translate('teachertrue.php_30行目_新規学生登録') ?></a></li> -->
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
                // === 新しい実装方針 ===
                // 1. 教員の担当学生リストを取得
                // 2. 学生たちの正誤情報を `linedata` から取得し、検索用マップを作成
                // 3. 学生たちの迷い情報を `temporary_results` から取得
                // 4. 迷い情報と正誤情報を組み合わせてテーブルを表示

                // --- ステップ1: 教員の担当学生リストを取得 ---
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

                // --- ステップ2: `linedata` から正誤情報の検索用マップを作成 ---
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

                // --- ステップ3: `temporary_results` から迷い情報を取得 ---
                $ml_results = [];
                if ($teacher_id) {
                    // teacher_id に紐づく最新の迷い推定結果を取得
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
                                // --- ステップ4: 迷い情報と正誤情報を組み合わせてテーブルを表示 ---
                                foreach ($ml_results as $row):
                                    $uid = $row['UID'];
                                    $wid = $row['WID'];
                                    $understand = $row['Understand'];
                                    $attempt = $row['attempt'];

                                    // マップから対応する正誤情報を検索
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

            <div id="feature-modal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeFeatureModal()">&times;</span>
                    <h3><?= translate('teachertrue.php_175行目_特徴量を選択してください') ?></h3>
                    <form id="feature-form">
                        <label><input type="checkbox" name="feature"
                                value="notaccuracy"><?= translate('teachertrue.php_177行目_不正解率 (%)') ?><span
                                class="info-icon" data-feature-name="notaccuracy">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="Time"><?= translate('teachertrue.php_178行目_解答時間 (秒)') ?><span class="info-icon"
                                data-feature-name="Time">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="distance"><?= translate('teachertrue.php_179行目_距離') ?><span class="info-icon"
                                data-feature-name="distance">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="averageSpeed"><?= translate('teachertrue.php_180行目_平均速度') ?><span
                                class="info-icon" data-feature-name="averageSpeed">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="maxSpeed"><?= translate('teachertrue.php_181行目_最高速度') ?><span class="info-icon"
                                data-feature-name="maxSpeed">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="thinkingTime"><?= translate('teachertrue.php_182行目_考慮時間') ?><span
                                class="info-icon" data-feature-name="thinkingTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="answeringTime"><?= translate('teachertrue.php_183行目_第一ドロップ後解答時間') ?><span
                                class="info-icon" data-feature-name="answeringTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="totalStopTime"><?= translate('teachertrue.php_184行目_合計静止時間') ?><span
                                class="info-icon" data-feature-name="totalStopTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="maxStopTime"><?= translate('teachertrue.php_185行目_最大静止時間') ?><span
                                class="info-icon" data-feature-name="maxStopTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="totalDDIntervalTime"><?= translate('teachertrue.php_186行目_合計DD間時間') ?><span
                                class="info-icon" data-feature-name="totalDDIntervalTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="maxDDIntervalTime"><?= translate('teachertrue.php_187行目_最大DD間時間') ?><span
                                class="info-icon" data-feature-name="maxDDIntervalTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="maxDDTime"><?= translate('teachertrue.php_188行目_合計DD時間') ?><span
                                class="info-icon" data-feature-name="maxDDTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="minDDTime"><?= translate('teachertrue.php_189行目_最小DD時間') ?><span
                                class="info-icon" data-feature-name="minDDTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="DDCount"><?= translate('teachertrue.php_190行目_合計DD回数') ?><span class="info-icon"
                                data-feature-name="DDCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="groupingDDCount"><?= translate('teachertrue.php_191行目_グループ化DD回数') ?><span
                                class="info-icon" data-feature-name="groupingDDCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="groupingCountbool"><?= translate('teachertrue.php_192行目_グループ化有無') ?><span
                                class="info-icon" data-feature-name="groupingCountbool">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="xUturnCount"><?= translate('teachertrue.php_193行目_x軸Uターン回数') ?><span
                                class="info-icon" data-feature-name="xUturnCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="yUturnCount"><?= translate('teachertrue.php_194行目_y軸Uターン回数') ?><span
                                class="info-icon" data-feature-name="yUturnCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register_move_count1"><?= translate('teachertrue.php_195行目_レジスタ➡レジスタへの移動回数') ?><span
                                class="info-icon" data-feature-name="register_move_count1">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register_move_count2"><?= translate('teachertrue.php_196行目_レジスタ➡レジスタ外への移動回数') ?><span
                                class="info-icon" data-feature-name="register_move_count2">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register_move_count3"><?= translate('teachertrue.php_197行目_レジスタ外➡レジスタへの移動回数') ?><span
                                class="info-icon" data-feature-name="register_move_count3">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register01count1"><?= translate('teachertrue.php_198行目_レジスタ➡レジスタへの移動有無') ?><span
                                class="info-icon" data-feature-name="register01count1">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register01count2"><?= translate('teachertrue.php_199行目_レジスタ➡レジスタ外への移動有無') ?><span
                                class="info-icon" data-feature-name="register01count2">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register01count3"><?= translate('teachertrue.php_200行目_レジスタ外➡レジスタへの移動有無') ?><span
                                class="info-icon" data-feature-name="register01count3">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="registerDDCount"><?= translate('teachertrue.php_201行目_レジスタに関する合計の移動回数') ?><span
                                class="info-icon" data-feature-name="registerDDCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="xUturnCountDD"><?= translate('teachertrue.php_202行目_x軸UターンD&D回数') ?><span
                                class="info-icon" data-feature-name="xUturnCountDD">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="yUturnCountDD"><?= translate('teachertrue.php_203行目_y軸UターンD&D回数') ?><span
                                class="info-icon" data-feature-name="yUturnCountDD">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="FromlastdropToanswerTime"><?= translate('teachertrue.php_204行目_最終ドロップ後時間') ?><span
                                class="info-icon" data-feature-name="FromlastdropToanswerTime">ⓘ</span></label><br>
                        <button type="button"
                            id="apply-features-btn"><?= translate('teachertrue.php_205行目_適用') ?></button>
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
                        <label for="clustering-method"
                            style="display: none;"><?= translate('teachertrue.php_217行目_クラスタリング手法') ?></label>
                        <select id="clustering-method" style="display: none;">
                            <option value="kmeans">K-Means</option>
                            <option value="xmeans">X-Means</option>
                            <option value="gmeans">G-Means</option>
                        </select>
                        <h3><?= translate('teachertrue.php_223行目_クラスタリング特徴量を選択してください') ?></h3>

                        <label><input type="checkbox" name="feature"
                                value="Time"><?= translate('teachertrue.php_226行目_解答時間 (秒)') ?><span class="info-icon"
                                data-feature-name="Time">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="distance"><?= translate('teachertrue.php_227行目_距離') ?><span class="info-icon"
                                data-feature-name="distance">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="averageSpeed"><?= translate('teachertrue.php_228行目_平均速度') ?><span
                                class="info-icon" data-feature-name="averageSpeed">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="maxSpeed"><?= translate('teachertrue.php_229行目_最大速度') ?><span class="info-icon"
                                data-feature-name="maxSpeed">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="thinkingTime"><?= translate('teachertrue.php_230行目_第一ドラッグ前時間') ?><span
                                class="info-icon" data-feature-name="thinkingTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="answeringTime"><?= translate('teachertrue.php_231行目_第一ドラッグ後時間') ?><span
                                class="info-icon" data-feature-name="answeringTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="totalStopTime"><?= translate('teachertrue.php_232行目_合計静止時間') ?><span
                                class="info-icon" data-feature-name="totalStopTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="maxStopTime"><?= translate('teachertrue.php_233行目_最大静止時間') ?><span
                                class="info-icon" data-feature-name="maxStopTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="totalDDIntervalTime"><?= translate('teachertrue.php_234行目_合計D&D間時間') ?><span
                                class="info-icon" data-feature-name="totalDDIntervalTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="maxDDIntervalTime"><?= translate('teachertrue.php_235行目_最大D&D間時間') ?><span
                                class="info-icon" data-feature-name="maxDDIntervalTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="maxDDTime"><?= translate('teachertrue.php_236行目_合計D&D時間') ?><span
                                class="info-icon" data-feature-name="maxDDTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="minDDTime"><?= translate('teachertrue.php_237行目_最小D&D時間') ?><span
                                class="info-icon" data-feature-name="minDDTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="DDCount"><?= translate('teachertrue.php_238行目_合計D&D回数') ?><span class="info-icon"
                                data-feature-name="DDCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="groupingDDCount"><?= translate('teachertrue.php_239行目_グループ化回数') ?><span
                                class="info-icon" data-feature-name="groupingDDCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="groupingCountbool"><?= translate('teachertrue.php_240行目_グループ化有無') ?><span
                                class="info-icon" data-feature-name="groupingCountbool">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="xUturnCount"><?= translate('teachertrue.php_241行目_x軸Uターン回数') ?><span
                                class="info-icon" data-feature-name="xUturnCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="yUturnCount"><?= translate('teachertrue.php_242行目_y軸Uターン回数') ?><span
                                class="info-icon" data-feature-name="yUturnCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register_move_count1"><?= translate('teachertrue.php_243行目_レジスタ➡レジスタへの移動回数') ?><span
                                class="info-icon" data-feature-name="register_move_count1">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register_move_count2"><?= translate('teachertrue.php_244行目_レジスタ➡レジスタ外への移動回数') ?><span
                                class="info-icon" data-feature-name="register_move_count2">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register_move_count3"><?= translate('teachertrue.php_245行目_レジスタ外➡レジスタへの移動回数') ?><span
                                class="info-icon" data-feature-name="register_move_count3">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register01count1"><?= translate('teachertrue.php_246行目_レジスタ➡レジスタへの移動有無') ?><span
                                class="info-icon" data-feature-name="register01count1">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register01count2"><?= translate('teachertrue.php_247行目_レジスタ➡レジスタ外への移動有無') ?><span
                                class="info-icon" data-feature-name="register01count2">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="register01count3"><?= translate('teachertrue.php_248行目_レジスタ外➡レジスタへの移動有無') ?><span
                                class="info-icon" data-feature-name="register01count3">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="registerDDCount"><?= translate('teachertrue.php_249行目_レジスタに関する合計の移動回数') ?><span
                                class="info-icon" data-feature-name="registerDDCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="xUturnCountDD"><?= translate('teachertrue.php_250行目_x軸UターンD&D回数') ?><span
                                class="info-icon" data-feature-name="xUturnCountDD">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="yUturnCountDD"><?= translate('teachertrue.php_251行目_y軸UターンD&D回数') ?><span
                                class="info-icon" data-feature-name="yUturnCountDD">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="FromlastdropToanswerTime"><?= translate('teachertrue.php_252行目_最終ドロップ後時間') ?><span
                                class="info-icon" data-feature-name="FromlastdropToanswerTime">ⓘ</span></label><br>
                        <button type="button"
                            id="apply-clustering-btn"><?= translate('teachertrue.php_254行目_適用') ?></button>
                    </form>
                </div>
            </div>

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

            <div id="feature-detail-modal-teachertrue" class="feature-detail-modal">
                <div class="modal-content">
                    <span class="close-detail-modal">&times;</span>
                    <h3 id="detail-feature-title-teachertrue"></h3>
                    <p id="detail-feature-description-teachertrue"></p>
                </div>
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

    <script>
        // ページ全体のJavaScriptコード
        document.addEventListener('DOMContentLoaded', function() {
            // --- 共通の関数定義 ---
            function createDualAxisChart(ctx, labels, data1, data2, label1, label2, color1, color2, yText1, yText2, chartArray, chartIndex) {
                if (chartArray[chartIndex]) chartArray[chartIndex].destroy();
                chartArray[chartIndex] = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                                label: label1,
                                data: data1,
                                backgroundColor: color1,
                                borderColor: color1,
                                yAxisID: 'y1',
                                borderWidth: 1
                            },
                            {
                                label: label2,
                                data: data2,
                                backgroundColor: color2,
                                borderColor: color2,
                                yAxisID: 'y2',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: '<?= json_encode(translate('teachertrue.php_339行目_ユーザー名')) ?>',
                                    font: {
                                        size: 20
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 16
                                    }
                                }
                            },
                            y1: {
                                title: {
                                    display: true,
                                    text: yText1,
                                    font: {
                                        size: 20
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 16
                                    }
                                },
                                position: 'left',
                                beginAtZero: true
                            },
                            y2: {
                                title: {
                                    display: true,
                                    text: yText2,
                                    font: {
                                        size: 20
                                    }
                                },
                                ticks: {
                                    font: {
                                        size: 16
                                    }
                                },
                                position: 'right',
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    font: {
                                        size: 20
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // --- グループ別グラフの描画 ---
            const groupContainer = document.getElementById('group-data-container');
            if (groupContainer && typeof groupData !== 'undefined') {
                groupData.forEach((group, index) => {
                    const container = document.createElement('div');
                    container.classList.add('class-card');
                    container.innerHTML = `
                    <h3>${group.group_name}
                        <button onclick="openFeatureModal(${index}, false)">${'<?= json_encode(translate('teachertrue.php_404行目_グラフ描画特徴量')) ?>'}</button>
                        <button onclick="openEstimatePage(${index})">${'<?= json_encode(translate('teachertrue.php_405行目_迷い推定')) ?>'}</button>
                    </h3>
                    <div class="chart-row"><canvas id="dual-axis-chart-${index}"></canvas></div>`;
                    groupContainer.appendChild(container);
                    const labels = group.students.map(s => s.name);
                    const notaccuracyData = group.students.map(s => s.notaccuracy);
                    const timeData = group.students.map(s => s.time);
                    createDualAxisChart(document.getElementById(`dual-axis-chart-${index}`).getContext('2d'), labels, notaccuracyData, timeData, '<?= json_encode(translate('teachertrue.php_429行目_不正解率(%)')) ?>', '<?= json_encode(translate('teachertrue.php_430行目_解答時間(秒)')) ?>', 'rgba(54, 162, 235, 0.6)', 'rgba(255, 99, 132, 0.6)', '<?= json_encode(translate('teachertrue.php_433行目_不正解率(%)')) ?>', '<?= json_encode(translate('teachertrue.php_434行目_解答時間(秒)')) ?>', existingClassCharts, index);
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
                        <button onclick="openClassFeatureModal(${index})">${'<?= json_encode(translate('teachertrue.php_769行目_グラフ描画特徴量')) ?>'}</button>
                        <button onclick="openClassEstimatePage(${index})">${'<?= json_encode(translate('teachertrue.php_770行目_迷い推定')) ?>'}</button>
                        <button onclick="openClusteringModal(${index})">${'<?= json_encode(translate('teachertrue.php_771行目_クラスタリング')) ?>'}</button>
                    </h3>
                    <div class="chart-row"><canvas id="class-dual-axis-chart-${index}"></canvas></div>`;
                    classContainer.appendChild(container);
                    const labels = classInfo.class_students.map(s => s.name);
                    const notaccuracyData = classInfo.class_students.map(s => s.notaccuracy);
                    const timeData = classInfo.class_students.map(s => s.time);
                    createDualAxisChart(document.getElementById(`class-dual-axis-chart-${index}`).getContext('2d'), labels, notaccuracyData, timeData, '<?= json_encode(translate('teachertrue.php_791行目_不正解率(%)')) ?>', '<?= json_encode(translate('teachertrue.php_792行目_解答時間(秒)')) ?>', 'rgba(54, 162, 235, 0.6)', 'rgba(255, 99, 132, 0.6)', '<?= json_encode(translate('teachertrue.php_795行目_不正解率(%)')) ?>', '<?= json_encode(translate('teachertrue.php_796行目_解答時間(秒)')) ?>', existingOverallCharts, index);
                });
            }
        });

        // --- モーダルや動的処理のためのグローバル関数 ---
        let existingClassCharts = [];
        let existingOverallCharts = [];
        let selectedGroupIndex;
        let selectedClassIndex;
        let currentChart = null;

        function openEstimatePage(groupIndex) {
            const group = groupData[groupIndex];
            if (!group || !group.students) {
                alert('<?= json_encode(translate('teachertrue.php_265行目_グループに学習者が登録されていません。')) ?>');
                return;
            }
            const studentIds = group.students.map(student => student.student_id).join(',');
            window.location.href = `machineLearning_sample.php?students=${encodeURIComponent(studentIds)}`;
        }

        function openClassEstimatePage(classIndex) {
            const classInfo = classData[classIndex];
            if (!classInfo || !classInfo.class_students) {
                alert('<?= json_encode(translate('teachertrue.php_283行目_クラスに学習者が登録されていません。')) ?>');
                return;
            }
            const studentIds = classInfo.class_students.map(student => student.student_id).join(',');
            window.location.href = `machineLearning_sample.php?students=${encodeURIComponent(studentIds)}`;
        }

        function openFeatureModal(index, isOverall) {
            selectedGroupIndex = index;
            document.getElementById('feature-modal').style.display = 'block';
            document.getElementById('apply-features-btn').onclick = function() {
                applySelectedFeatures(isOverall ? existingOverallCharts : existingClassCharts, index, isOverall);
            };
        }

        function openClassFeatureModal(index) {
            openFeatureModal(index, true);
        }

        function closeFeatureModal() {
            document.getElementById('feature-modal').style.display = 'none';
            document.getElementById('feature-form').reset();
        }

        function openClusteringModal(index) {
            selectedClassIndex = index;
            document.getElementById('clustering-modal').style.display = 'block';
        }

        function closeClusteringModal() {
            document.getElementById('clustering-modal').style.display = 'none';
            document.getElementById('clustering-feature-form').reset();
        }

        // 他のJavaScript関数 (applySelectedFeatures, groupSelectedClusters, displayClusteringResultsFromJSON など) はここに配置
    </script>
</body>

</html>
<?php
// すべての処理の最後に一度だけ接続を閉じる
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
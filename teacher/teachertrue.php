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
            
            <!-- ======================= ▼▼▼ ここから追加 ▼▼▼ ======================= -->
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
            <!-- ======================= ▲▲▲ 追加はここまで ▲▲▲ ======================= -->


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

            // ======================= ▼▼▼ ここから修正 ▼▼▼ =======================
            // 学習者詳細情報表示のためのJavaScript
            const uidSelect = document.getElementById('uid-select');
            const widSelect = document.getElementById('wid-select');
            const studentDetailsmaininfo = document.getElementById('student-details-maininfo');
            const widDetailsmaininfostu = document.getElementById('wid-details-maininfo-stu');
            const widDetailsmaininfoall = document.getElementById('wid-details-maininfo-all');

            //学習者選択時の処理
            if(uidSelect) {
                uidSelect.addEventListener('change', async function () {
                    const selectedUid = uidSelect.value;

                    //プルダウンのリセット
                    widSelect.innerHTML = `<option value = "">${'<?= json_encode(translate('machineLearning_sample.php_1498行目_ロード中')) ?>'}</option>`;
                    if (!selectedUid) {
                        //学習者が選択されていない場合
                        widSelect.innerHTML = `<option value = "">${'<?= json_encode(translate('machineLearning_sample.php_1501行目_学習者を選択してください')) ?>'}</option>`;
                        studentDetailsmaininfo.innerHTML = `<p>${'<?= json_encode(translate('machineLearning_sample.php_1502行目_学習者情報を選択してください')) ?>'}</p>`;
                        return;
                    }
                    try {
                        //サーバーからデータを取得
                        //問題データの取得
                        const widResponse = await fetch(`get_wid.php?uid=${selectedUid}`);
                        if (!widResponse.ok) {
                            throw new Error(`HTTP error! status: ${widResponse.status}`);
                        }
                        const widData = await widResponse.json();
                        //プルダウンメニューを更新
                        widSelect.innerHTML = `<option value = "">${'<?= json_encode(translate('machineLearning_sample.php_1511行目_選択してください')) ?>'}</option>`;
                        widData.forEach(wid => {
                            widSelect.innerHTML += `<option value="${wid.WID}">
                                                    ${wid.WID}: ${wid.Sentence}: ${'<?= json_encode(translate('machineLearning_sample.php_1513行目_難易度')) ?>'}${wid.level}: ${'<?= json_encode(translate('machineLearning_sample.php_1513行目_迷い')) ?>'}:${wid.Understand} 
                                                    ${wid.Understand === '迷い有り' ? '(★)' : ''}
                                                </option>`;
                        });

                        //**学習者情報の取得 */
                        const studentResponse = await fetch(`get_student_info.php?uid=${selectedUid}`);
                        if (!studentResponse.ok) {
                            throw new Error(`HTTP error! status: ${studentResponse.status}`);
                        }
                        const studentData = await studentResponse.json();
                        const studentDatainfo = studentData.userinfo;

                        // 学習者情報の表示/
                        studentDetailsmaininfo.innerHTML = `
                                    <div id = "student-info-title" style = "display:flex; gap: 10px;">
                                    <h3>${'<?= json_encode(translate('machineLearning_sample.php_1528行目_学習者名')) ?>'}:${studentDatainfo.Name}</h3>
                                    <h3>${'<?= json_encode(translate('machineLearning_sample.php_1529行目_クラス名')) ?>'}:${studentDatainfo.ClassID}</h3>
                                    <h3>${'<?= json_encode(translate('machineLearning_sample.php_1530行目_TOEICレベル')) ?>'}:${studentDatainfo.toeic_level}</h3>
                                    <h3>${'<?= json_encode(translate('machineLearning_sample.php_1531行目_英検レベル')) ?>'}:${studentDatainfo.eiken_level}</h3>
                                    </div>

                                    <div id = "student-info-accuracy" style = "display:flex; gap: 10px;">
                                    <p>${'<?= json_encode(translate('machineLearning_sample.php_1534行目_総解答数')) ?>'}:${studentDatainfo.total_answers}</p>
                                    <p>${'<?= json_encode(translate('machineLearning_sample.php_1535行目_正解率')) ?>'}:${studentDatainfo.accuracy}%</p>
                                    <p>${'<?= json_encode(translate('machineLearning_sample.php_1536行目_迷い率')) ?>'}:${studentDatainfo.hesitation_rate}%</p>
                                    </div>
                                    `;
                        //文法項目データを表示する関数
                        displayGrammarStats(studentData.grammarStats);
                    } catch (error) {
                        widSelect.innerHTML = '<option value = "">エラー</option>';
                        console.error(error);
                    }
                });
            }

            //問題選択時の処理
            if(widSelect) {
                widSelect.addEventListener('change', async function () {
                    const selectedWid = this.value;
                    const selectedUid = uidSelect.value;

                    if (!selectedWid || !selectedUid) {
                        widDetailsmaininfostu.innerHTML = `<p>${'<?= json_encode(translate('machineLearning_sample.php_1544行目_学習者情報を選択してください')) ?>'}</p>`;
                        return;
                    }

                    try {
                        // 解答情報の取得
                        const answerResponse = await fetch(`get_answer_info.php?uid=${selectedUid}&wid=${selectedWid}`);
                        if (!answerResponse.ok) {
                            throw new Error(`HTTP error! status:${answerResponse.status}`);
                        }
                        const answerDetails = await answerResponse.json();

                        const quesaccuracy = answerDetails.quesaccuracy ?? "N/A";
                        const queshesitation_rate = answerDetails.queshesitation_rate ?? "N/A";
                        const labelinfo = answerDetails.labelinfo;
                        const attempt1 = answerDetails.widinfo.find(detail => detail.attempt == 1);

                        // attempt選択用のselect要素を作成
                        const attemptSelect = document.createElement('select');
                        attemptSelect.id = 'attempt-select';
                        attemptSelect.innerHTML = '<option value="">選択してください</option>';
                        answerDetails.widinfo.forEach(detail => {
                            const option = document.createElement('option');
                            option.value = detail.attempt;
                            option.textContent = `Attempt ${detail.attempt}`;
                            attemptSelect.appendChild(option);
                        });

                        // 全体表示の設定
                        if (attempt1) {
                            widDetailsmaininfoall.innerHTML = `
                                <div style="border: 1px solid #ccc; padding: 15px; border-radius: 8px; background-color: #f9f9f9;">
                                    <h3 style="color: #333; text-align: center; margin-bottom: 20px;">${'<?= json_encode(translate('machineLearning_sample.php_1570行目_問題情報')) ?>'}</h3>
                                    <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                                        <div style="flex: 1; min-width: 250px;">
                                            <p><strong>${'<?= json_encode(translate('machineLearning_sample.php_1573行目_正解率')) ?>'}:</strong> ${quesaccuracy}%</p>
                                            <p><strong>${'<?= json_encode(translate('machineLearning_sample.php_1574行目_迷い率')) ?>'}:</strong> ${queshesitation_rate}%</p>
                                            <p><strong>${'<?= json_encode(translate('machineLearning_sample.php_1575行目_正解文')) ?>'}:</strong> ${attempt1.Sentence}</p>
                                        </div>
                                        <div style="flex: 1; min-width: 250px;">
                                            <p><strong>${'<?= json_encode(translate('machineLearning_sample.php_1578行目_日本語文')) ?>'}:</strong> ${attempt1.Japanese}</p>
                                            <p><strong>${'<?= json_encode(translate('machineLearning_sample.php_1579行目_文法項目')) ?>'}:</strong> ${attempt1.grammar}</p>
                                            <p><strong>${'<?= json_encode(translate('machineLearning_sample.php_1580行目_単語数')) ?>'}:</strong> ${attempt1.wordnum}</p>
                                        </div>
                                    </div>
                                </div>
                            `;

                            if (labelinfo && Array.isArray(labelinfo) && labelinfo.length > 0) {
                                // ... (Label情報テーブルの描画ロジックは省略)
                            }
                        } else {
                            widDetailsmaininfoall.innerHTML = `<p>${'<?= json_encode(translate('machineLearning_sample.php_1623行目_初期表示用のデータが見つかりません')) ?>'}</p>`;
                        }

                        widDetailsmaininfostu.innerHTML = '';
                        widDetailsmaininfostu.appendChild(attemptSelect);
                        const attemptDetailsContainer = document.createElement('div');
                        attemptDetailsContainer.id = 'attempt-details';
                        widDetailsmaininfostu.appendChild(attemptDetailsContainer);

                        function getAttemptDetailHTML(detail) {
                            const labelText = detail.Label ? detail.Label : '<?= json_encode(translate('machineLearning_sample.php_1641行目_グルーピングが行われていません')) ?>';
                            return `
                                <p>${'<?= json_encode(translate('machineLearning_sample.php_1644行目_回答日時')) ?>'}: ${detail.Date}</p>
                                <p>${'<?= json_encode(translate('machineLearning_sample.php_1645行目_最終回答文')) ?>'}: ${detail.EndSentence}</p>
                                <p>${'<?= json_encode(translate('machineLearning_sample.php_1646行目_解答時間')) ?>'}: ${detail.Time}秒</p>
                                <p>${'<?= json_encode(translate('machineLearning_sample.php_1647行目_正誤')) ?>'}: ${detail.TF}</p>
                                <p>${'<?= json_encode(translate('machineLearning_sample.php_1648行目_迷い')) ?>'}: ${detail.Understand}</p>
                                <p>${'<?= json_encode(translate('machineLearning_sample.php_1649行目_Label')) ?>'}: ${labelText}</p>
                            `;
                        }

                        if (attempt1) {
                            attemptSelect.value = 1;
                            attemptDetailsContainer.innerHTML = getAttemptDetailHTML(attempt1);
                        }

                        attemptSelect.addEventListener('change', function () {
                            const selectedAttempt = this.value;
                            const selectedDetail = answerDetails.widinfo.find(detail => detail.attempt == selectedAttempt);
                            if (selectedDetail) {
                                attemptDetailsContainer.innerHTML = getAttemptDetailHTML(selectedDetail);
                            } else {
                                attemptDetailsContainer.innerHTML = `<p>${'<?= json_encode(translate('machineLearning_sample.php_1662行目_選択された試行回数の情報が見つかりません')) ?>'}</p>`;
                            }
                        });

                    } catch (error) {
                        console.error(error);
                        widDetailsmaininfostu.innerHTML = `<p>${'<?= json_encode(translate('machineLearning_sample.php_1667行目_データの取得に失敗しました')) ?>'}</p>`;
                    }
                });
            }

            function displayGrammarStats(grammarStats) {
                const grammarStatsDiv = document.getElementById('student-details-grammar');
                grammarStatsDiv.style.display = 'flex';
                grammarStatsDiv.style.flexDirection = 'row';
                grammarStatsDiv.style.justifyContent = 'space-between';
                grammarStatsDiv.style.alignItems = 'flex-start';

                let tableHTML = `
                    <div style="flex: 1; padding-right: 20px;"> <table class = "table2">
                            <thead>
                                <tr>
                                    <th>${'<?= json_encode(translate('machineLearning_sample.php_1682行目_文法項目')) ?>'}</th>
                                    <th>${'<?= json_encode(translate('machineLearning_sample.php_1683行目_総解答数')) ?>'}</th>
                                    <th>${'<?= json_encode(translate('machineLearning_sample.php_1684行目_正解数')) ?>'}</th>
                                    <th>${'<?= json_encode(translate('machineLearning_sample.php_1685行目_迷い数')) ?>'}</th>
                                    <th>${'<?= json_encode(translate('machineLearning_sample.php_1686行目_不正解率')) ?>'}</th>
                                    <th>${'<?= json_encode(translate('machineLearning_sample.php_1687行目_迷い率')) ?>'}</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                const labels = [];
                const accuracyData = [];
                const hesitationData = [];

                for (const [grammar, stats] of Object.entries(grammarStats)) {
                    notaccuracy_grammar = (100 - stats.accuracy).toFixed(2);
                    tableHTML += `
                        <tr>
                            <td>${stats.grammar}</td>
                            <td>${stats.total_answers}</td>
                            <td>${stats.correct_answers}</td>
                            <td>${stats.hesitate_count}</td>
                            <td>${notaccuracy_grammar}%</td>
                            <td>${stats.hesitation_rate}%</td>
                        </tr>
                    `;
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
                            label: '<?= json_encode(translate('machineLearning_sample.php_1744行目_不正解率(%)')) ?>',
                            data: accuracyData,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                        },
                        {
                            label: '<?= json_encode(translate('machineLearning_sample.php_1745行目_迷い率(%)')) ?>',
                            data: hesitationData,
                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                            borderColor: 'rgba(255,99,132,1)',
                            borderWidth: 1,
                        }]
                    },
                    options: { /* ... chart options ... */ }
                });
            }
            // ======================= ▲▲▲ 修正はここまで ▲▲▲ =======================

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

    </script>
</body>

</html>
<?php
// すべての処理の最後に一度だけ接続を閉じる
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
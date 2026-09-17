<?php
include '../lang.php';
require_once __DIR__ . '/feature_display.php';

$featureDisplayFeatureKeys = [
    'notaccuracy', 'notAccuracy', 'Time', 'time', 'distance', 'averageSpeed', 'maxSpeed',
    'thinkingTime', 'answeringTime', 'totalStopTime', 'maxStopTime',
    'totalDDIntervalTime', 'maxDDIntervalTime', 'maxDDTime', 'minDDTime',
    'DDCount', 'groupingDDCount', 'groupingCountbool', 'stopcount',
    'xUturnCount', 'yUturnCount', 'xUTurnCount', 'yUTurnCount',
    'xUturnCountDD', 'yUturnCountDD', 'xUTurnCountDD', 'yUTurnCountDD',
    'register_move_count1', 'register_move_count2', 'register_move_count3', 'register_move_count4',
    'register01count1', 'register01count2', 'register01count3', 'register01count4',
    'registerDDCount', 'register_notDDCount',
    'register_fix_count1', 'register_fix_count2', 'register_fix_count3', 'register_fix_count4',
    'register_delete_count1', 'register_delete_count2', 'register_delete_count3', 'register_delete_count4',
    'register_allDelete_count1', 'register_allDelete_count2', 'register_allDelete_count3', 'register_allDelete_count4',
    'register_notallDelete_count1', 'register_notallDelete_count2', 'register_notallDelete_count3', 'register_notallDelete_count4',
    'FromlastdropToanswerTime',
];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>迷い推定・機械学習（問題単位）</title>
    <link rel="stylesheet" href="../style/machineLearning_styles.css?v=<?= filemtime(__DIR__ . '/../style/machineLearning_styles.css') ?>">
    <link rel="stylesheet" href="../style/teachertrue_styles.css?v=<?= filemtime(__DIR__ . '/../style/teachertrue_styles.css') ?>">
    <link rel="stylesheet" href="../style/machineLearning_sample_styles.css?v=<?= filemtime(__DIR__ . '/../style/machineLearning_sample_styles.css') ?>">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        window.featureDisplayMeta = <?= json_encode(feature_display_metadata($featureDisplayFeatureKeys), JSON_UNESCAPED_UNICODE) ?>;
    </script>
</head>

<body class="machine-learning-sample-page">
    <style>
        /* テーブルのスクロール表示設定 */
        #table-container {
            max-height: 400px;
            /* 表示領域の高さを指定 */
            overflow-y: auto;
            /* 縦スクロールを有効にする */
            border: 1px solid #ccc;
            /* 境界線 */
        }

        /* テーブルのスタイル */
        #results-table {
            width: 100%;
            /* テーブル幅を100%に */
            border-collapse: collapse;
        }

        #results-table th,
        #results-table td {
            padding: 8px;
            border: 1px solid #ddd;
            /* セルの境界線 */
        }

        .classification-target-controls {
            margin-bottom: 8px;
            font-weight: bold;
        }

        .classification-group-selector {
            margin-bottom: 12px;
            padding: 10px;
            border: 1px solid #b8cbe0;
            background: #f4f8fc;
        }

        .classification-group-selector h4 {
            margin: 0 0 4px;
        }

        .classification-group-help {
            margin: 0 0 8px;
            color: #475569;
            font-size: 0.9rem;
            font-weight: normal;
        }

        .classification-group-options {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 16px;
        }

        .classification-target-list {
            max-height: 260px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ccc;
            background: #fff;
        }

        .classification-class-group {
            margin: 0 0 10px;
            padding: 8px;
            border: 1px solid #ddd;
        }

        .classification-class-group:last-child {
            margin-bottom: 0;
        }

        .classification-class-group legend {
            padding: 0 6px;
            font-weight: bold;
        }

        .classification-student-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 16px;
            margin-top: 8px;
        }

        .classification-empty-message {
            margin: 0;
            color: #b91c1c;
        }
    </style>

    <?php
    require "../dbc.php";
    require_once __DIR__ . '/hesitation-estimation-state.php';
    require "log_write.php";
    // セッション変数をクリアする（必要に応じて）
    unset($_SESSION['conditions']);
    // GET パラメータが指定されている場合のみセッションに保存または上書き
    if (isset($_GET['students']) && !empty($_GET['students'])) {
        $_SESSION['group_students'] = $_GET['students'];
    }
    // ユニークなIDを生成
    $uniqueId = uniqid(bin2hex(random_bytes(4)));
    $timestamp = date('YmdHis');
    $teacher_page_title = '迷い推定・機械学習（問題単位）';
    include __DIR__ . '/teacher-menu.php';
    ?>
    <div class="main-content">
        <main class="page-content machine-learning-page">
            <?php
            require "../dbc.php";
            $teacher_id = $_SESSION['MemberID'];

            $stmt = $conn->prepare("SELECT * FROM `groups` WHERE TID = ?");
            if (!$stmt) {
                die("prepare() failed: " . $conn->error);
            }
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            if (!$stmt) {
                die("prepare() failed: " . $conn->error);
            }
            $result = $stmt->get_result();

            $groups = [];
            if ($result->num_rows > 0) {
                //学習者グループがある場合
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
                        //学生ごとの正解数と解答数を取得
                        $stmt_scores = $conn->prepare("SELECT 
                                                            COUNT(*) AS total_answers,
                                                            SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) AS correct_answers,
                                                            SUM(Time) AS total_time
                                                            FROM linedata WHERE uid = ?");
                        $stmt_scores->bind_param("i", $students_id);
                        $stmt_scores->execute();
                        $result_scores = $stmt_scores->get_result();
                        $score_data = $result_scores->fetch_assoc();
                        $correct_answers = $score_data['correct_answers'];
                        $total_answers = $score_data['total_answers'];
                        $total_time = $score_data['total_time'];
                        $accuracy_rate = $total_answers > 0 ? number_format(($correct_answers / $total_answers) * 100, 2) : 0;
                        $notaccuracy_rate = 100 - $accuracy_rate;
                        $accuracy_time = $total_answers > 0 ? number_format(($total_time / 1000) / $total_answers, 2) : 0;

                        $stmt_scores->close();
                        $result_scores->free(); // メモリ解放

                        //学生ごとの名前を取得
                        $stmt_name = $conn->prepare("SELECT Name FROM students WHERE uid = ?");
                        $stmt_name->bind_param("i", $students_id);
                        $stmt_name->execute();
                        $result_name = $stmt_name->get_result();
                        $name_data = $result_name->fetch_assoc();
                        $name = $name_data['Name'];
                        $stmt_name->close();
                        $result_name->free();

                        //学生ごとの正解数を格納
                        $group_students[] = [
                            'student_id' => $students_id,
                            'name' => $name,
                            'accuracy' => $accuracy_rate,
                            'notaccuracy' => $notaccuracy_rate,
                            'time' => $accuracy_time
                        ];
                    }
                    // グループデータを配列に追加
                    $groups[] = [
                        'group_name' => $group_name,
                        'group_id' => $group_id,
                        'students' => $group_students
                    ];
                    $stmt_groupmember->close();
                    $result_groupmember->free();
                }
            } else {
                // 学習者グループがない場合
                $groups = [];
            }

            $stmt->close();
            $conn->close();

            ?>
            <?php
            require "../dbc.php";

            // 分類対象として選択できる、担当クラスまたは作成済みグループ内の学習者を取得
            $classificationStudentsByClass = [];
            $allowedClassificationStudentUids = [];
            $classificationTeacherId = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;

            if ($classificationTeacherId) {
                $stmtClassificationStudents = $conn->prepare(
                    "SELECT DISTINCT
                         s.uid AS UID,
                         s.Name,
                         s.ClassID,
                         COALESCE(c.ClassName, 'グループ(クラス)未設定') AS ClassName
                     FROM students s
                     LEFT JOIN classes c ON s.ClassID = c.ClassID
                     WHERE EXISTS (
                           SELECT 1
                           FROM test_featurevalue tfv
                           WHERE tfv.UID = s.uid
                       )
                       AND (
                           EXISTS (
                               SELECT 1
                               FROM classteacher ct
                               WHERE ct.ClassID = s.ClassID
                                 AND ct.TID = ?
                           )
                           OR EXISTS (
                               SELECT 1
                               FROM `groups` g
                               JOIN group_members gm ON gm.group_id = g.group_id
                               WHERE g.TID = ?
                                 AND gm.uid = s.uid
                           )
                       )
                     ORDER BY ClassName, s.Name, s.uid"
                );
                if ($stmtClassificationStudents) {
                    $stmtClassificationStudents->bind_param(
                        "ss",
                        $classificationTeacherId,
                        $classificationTeacherId
                    );
                    $stmtClassificationStudents->execute();
                    $classificationStudentResult = $stmtClassificationStudents->get_result();
                    while ($classificationStudent = $classificationStudentResult->fetch_assoc()) {
                        $classId = (string)$classificationStudent['ClassID'];
                        $uid = (string)$classificationStudent['UID'];
                        if (!isset($classificationStudentsByClass[$classId])) {
                            $classificationStudentsByClass[$classId] = [
                                'ClassID' => $classId,
                                'ClassName' => $classificationStudent['ClassName'],
                                'students' => [],
                            ];
                        }
                        $classificationStudentsByClass[$classId]['students'][] = [
                            'UID' => $uid,
                            'Name' => $classificationStudent['Name'],
                        ];
                        $allowedClassificationStudentUids[] = $uid;
                    }
                    $stmtClassificationStudents->close();
                }
            }

            $allowedClassificationStudentUids = array_values(array_unique($allowedClassificationStudentUids));
            $allowedClassificationStudentUidLookup = array_fill_keys($allowedClassificationStudentUids, true);
            $classificationGroups = [];

            // 作成済みグループのうち、現在分類可能な学習者だけをグループ選択肢にする
            foreach ($groups as $group) {
                $groupId = (string)$group['group_id'];
                $groupMemberUids = [];
                foreach ($group['students'] as $groupStudent) {
                    $uid = (string)$groupStudent['student_id'];
                    if (isset($allowedClassificationStudentUidLookup[$uid])) {
                        $groupMemberUids[$uid] = $uid;
                    }
                }
                $groupMemberUids = array_values($groupMemberUids);
                if (!empty($groupMemberUids)) {
                    $classificationGroups[$groupId] = [
                        'group_id' => $groupId,
                        'group_name' => $group['group_name'],
                        'member_uids' => $groupMemberUids,
                    ];
                }
            }

            $allowedClassificationGroupLookup = array_fill_keys(array_keys($classificationGroups), true);
            $selectedClassificationStudentUids = [];
            $selectedClassificationGroupIds = [];

            if ($_SERVER["REQUEST_METHOD"] === "POST") {
                $postedClassificationStudentUids = $_POST['classificationUIDs'] ?? [];
                if (is_array($postedClassificationStudentUids)) {
                    foreach ($postedClassificationStudentUids as $postedUid) {
                        if (!is_scalar($postedUid)) {
                            continue;
                        }
                        $uid = (string)$postedUid;
                        if (isset($allowedClassificationStudentUidLookup[$uid])) {
                            $selectedClassificationStudentUids[$uid] = $uid;
                        }
                    }
                }

                // グループIDはログイン中の教師が作成したものだけを受け付け、所属UIDへ展開する
                $postedClassificationGroupIds = $_POST['classificationGroupIds'] ?? [];
                if (is_array($postedClassificationGroupIds)) {
                    foreach ($postedClassificationGroupIds as $postedGroupId) {
                        if (!is_scalar($postedGroupId)) {
                            continue;
                        }
                        $groupId = (string)$postedGroupId;
                        if (!isset($allowedClassificationGroupLookup[$groupId])) {
                            continue;
                        }
                        $selectedClassificationGroupIds[$groupId] = $groupId;
                        foreach ($classificationGroups[$groupId]['member_uids'] as $groupMemberUid) {
                            $selectedClassificationStudentUids[$groupMemberUid] = $groupMemberUid;
                        }
                    }
                }

                $selectedClassificationStudentUids = array_values($selectedClassificationStudentUids);
                $selectedClassificationGroupIds = array_values($selectedClassificationGroupIds);
            } else {
                $selectedClassificationStudentUids = $allowedClassificationStudentUids;
            }
            $selectedClassificationStudentUidLookup = array_fill_keys($selectedClassificationStudentUids, true);

            $machineLearningRunReady = false;
            $machineLearningInputError = '';
            $machineLearningTransitionAcknowledgement = null;

            // フォームからの入力を受け取る
            $UIDrange = isset($_POST['UIDrange']) ? $_POST['UIDrange'] : null;
            $WIDrange = isset($_POST['WIDrange']) ? $_POST['WIDrange'] : null;
            $UIDsearch = isset($_POST['UID']) ? $_POST['UID'] : null; // 配列として受け取る
            $WIDsearch = isset($_POST['WID']) ? $_POST['WID'] : null; // 配列として受け取る
            $TFsearch = isset($_POST['TFsearch']) ? $_POST['TFsearch'] : null;
            $TimeRange = isset($_POST['TimeRange']) ? $_POST['TimeRange'] : null;
            $Timesearch = isset($_POST['Timesearch']) ? $_POST['Timesearch'] : null;
            $TimesearchMin = isset($_POST['Timesearch-min']) ? $_POST['Timesearch-min'] : null;
            $TimesearchMax = isset($_POST['Timesearch-max']) ? $_POST['Timesearch-max'] : null;

            $useData = isset($_POST['useData']) ? $_POST['useData'] : "";
            $selectedGroup = isset($_POST['selectedGroup']) ? $_POST['selectedGroup'] : "";


            $sql = "SELECT * FROM linedata";
            // WHERE 句の条件を保持する配列
            $conditions = [];
            // UIDの条件を追加
            if ($useData === 'groupdata') {
                if (empty($selectedGroup)) {
                    // グループが選択されていない場合の処理
                    echo "<script>alert('" . translate('machineLearning_sample.php_225行目_作成したグループを選択してください') . "');</script>";
                } else {
                    // グループが選択されている場合の処理
                    echo translate('machineLearning_sample.php_223行目_選択されたグループID') . ": " . htmlspecialchars($selectedGroup, ENT_QUOTES, 'UTF-8');
                    // ここで、データベースクエリや他の処理を追加
                    $sql_getUID = "SELECT uid FROM group_members WHERE group_id = ?";
                    $stmt = $conn->prepare($sql_getUID);
                    $stmt->bind_param("i", $selectedGroup);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $UIDs = [];
                    while ($row = $result->fetch_assoc()) {
                        $UIDs[] = $row['uid'];
                    }
                    $stmt->close();
                    $result->free();
                }
                // UID配列をカンマ区切りの文字列に変換
                $UIDlist = implode("','", array_map(function ($uid) use ($conn) {
                    return mysqli_real_escape_string($conn, $uid);
                }, $UIDs));
                $conditions[] = "UID IN ('" . $UIDlist . "')";
            } elseif ($useData === 'alalldata') {
                // 2019年度のA大学全データが選択された場合の処理
                echo translate('machineLearning_sample.php_236行目_2019年度のA大学全データが選択されました');
            } else {
                // その他の場合
                // echo translate('machineLearning_sample.php_239行目_選択が無効です'); // POST時以外も表示されてしまうためコメントアウト
            }
            //$conditionの中身を確認
            // echo "conditions: " . implode(", ", $conditions);
            /*
            if (!empty($UIDsearch)) {
                // UID配列をカンマ区切りの文字列に変換
                $UIDlist = implode("','", array_map(function($uid) use ($conn) {
                    return mysqli_real_escape_string($conn, $uid);
                }, $UIDsearch));

                if ($UIDrange === 'not') {
                    $conditions[] = "UID NOT IN ('" . $UIDlist . "')";
                } else {
                    $conditions[] = "UID IN ('" . $UIDlist . "')";
                }
            }

            // WIDの条件を追加
            if (!empty($WIDsearch)) {
                // WID配列をカンマ区切りの文字列に変換
                $WIDlist = implode("','", array_map(function($wid) use ($conn) {
                    return mysqli_real_escape_string($conn, $wid);
                }, $WIDsearch));

                if ($WIDrange === 'not') {
                    $conditions[] = "WID NOT IN ('" . $WIDlist . "')";
                } else {
                    $conditions[] = "WID IN ('" . $WIDlist . "')";
                }
            }
                */
            // 正誤の条件を追加
            if (isset($TFsearch)) {
                $conditions[] = "TF = '" . mysqli_real_escape_string($conn, $TFsearch) . "'";
            }
            // 解答時間の条件を追加
            if (!empty($TimeRange) && !empty($Timesearch)) {
                switch ($TimeRange) {
                    case 'above':
                        $conditions[] = "Time >= '" . mysqli_real_escape_string($conn, $Timesearch) . "'";
                        break;
                    case 'below':
                        $conditions[] = "Time <= '" . mysqli_real_escape_string($conn, $Timesearch) . "'";
                        break;
                    case 'range':
                        if (!empty($TimesearchMin) && !empty($TimesearchMax)) {
                            $conditions[] = "Time BETWEEN '" . mysqli_real_escape_string($conn, $TimesearchMin) . "' AND '" . mysqli_real_escape_string($conn, $TimesearchMax) . "'";
                        }
                        break;
                }
            }

            // 条件が一つでもあればWHERE句を追加&SQLと条件をsessionに保存
            if (!empty($conditions)) {
                $sql .= " WHERE " . join(" AND ", $conditions);
                $_SESSION['conditions'] = $conditions;
                //echo $_SESSION['conditions'];
                //echo "!emptyの条件を満たしています．<br>";
            } else {
                //echo "emptyの条件を満たしていません。<br>";
            }
            // $_SESSION['conditions']が設定されているかどうかを確認します
            /*
            if (isset($_SESSION['conditions']) && !empty($_SESSION['conditions'])) {
                //echo '$_SESSION["conditions"]が設定されています．<br>';
                // ここに$_SESSION['conditions']を使用するコードを追加します
            } else {
                //echo '$_SESSION["conditions"]は設定されていません．<br>';
            }
                */
            $_SESSION['sql'] = $sql;
            // echo $_SESSION['sql'];



            // SQL実行  
            $result = mysqli_query($conn, $sql);


            ?>
            <?php
            //デバッグ用のコード
            // フォームがPOSTされた場合
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                // echo "<h2>POSTされたデータ:</h2>";


                // UIDの選択値を表示
                /*
                if (isset($_POST['UIDrange'])) {
                    //echo "UID範囲: " . htmlspecialchars($_POST['UIDrange']) . "<br>";
                }

                if (isset($_POST['UID'])) {
                    echo "選択されたUID:<br>";
                    foreach ($_POST['UID'] as $uid) {
                        //echo htmlspecialchars($uid) . "<br>";
                    }
                }
                    */

                // WIDの選択値を表示
                /*
                if (isset($_POST['WIDrange'])) {
                    //echo "WID範囲: " . htmlspecialchars($_POST['WIDrange']) . "<br>";
                }

                if (isset($_POST['WID'])) {
                    echo "選択されたWID:<br>";
                    foreach ($_POST['WID'] as $wid) {
                        //echo htmlspecialchars($wid) . "<br>";
                    }
                }
                    */

                // 正誤の選択値を表示
                if (isset($_POST['TFsearch'])) {
                    //echo "正誤: " . htmlspecialchars($_POST['TFsearch']) . "<br>";
                }

                // 解答時間の選択値を表示
                if (isset($_POST['TimeRange'])) {
                    //echo "解答時間の範囲: " . htmlspecialchars($_POST['TimeRange']) . "<br>";
                }

                if (isset($_POST['Timesearch'])) {
                    //echo "解答時間: " . htmlspecialchars($_POST['Timesearch']) . "<br>";
                }

                if (isset($_POST['Timesearch-min']) && isset($_POST['Timesearch-max'])) {
                    //echo "解答時間の範囲: " . htmlspecialchars($_POST['Timesearch-min']) . " ～ " . htmlspecialchars($_POST['Timesearch-max']) . "<br>";
                }
            }

            ?>
            <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                if (!isset($_POST['featureLabel']) || !is_array($_POST['featureLabel']) || empty($_POST['featureLabel'])) {
                    $machineLearningInputError = translate('machineLearning_sample.php_424行目_データを選択してください');
                } elseif (empty($selectedClassificationStudentUids)) {
                    $machineLearningInputError = '分類する学習者を1名以上選択してください。';
                } else {

                    // データベース接続とセッション開始
                    require "../dbc.php";
                    if (session_status() == PHP_SESSION_NONE) {
                        session_start();
                    }

                    // CSVファイル名を事前に定義
                    $uniqueId = session_id();
                    $timestamp = time();
                    $test_filename = "./pydata/test_{$uniqueId}_{$timestamp}.csv";      // 教師データ用
                    $testdata_filename = "./pydata/testdata_{$uniqueId}_{$timestamp}.csv"; // テストデータ用

                    // 画面で選択され、かつ担当クラスに所属する学習者だけを分類対象にする
                    $selectedClassificationStudentUidsForSql = array_map(function ($uid) use ($conn) {
                        return "'" . $conn->real_escape_string($uid) . "'";
                    }, $selectedClassificationStudentUids);
                    $uid_list_str_for_sql = implode(',', $selectedClassificationStudentUidsForSql);

                    // 元のコードの変数定義
                    $allresult = array();
                    $tempwhere = array();
                    $sql = "SELECT UID,WID,Understand,attempt,";
                    $sql_test = "SELECT UID,WID,Understand,attempt,";
                    $selectcolumn = implode(",", $_POST['featureLabel']);
                    $sql .= $selectcolumn . " FROM featurevalue";    // 教師データSQL (ベース)
                    $sql_test .= $selectcolumn . " FROM test_featurevalue"; // テストデータSQL (ベース)
                    $column_name = "UID,WID,Understand,attempt," . $selectcolumn;

                    // クラスタを教師データにする場合の処理 (元の実装を維持)
                    if (isset($_SESSION['group_students']) && !empty($_SESSION['group_students'])) {
                        // ★★★★★【重要】ここを修正します ★★★★★
                        $group_students_list = $_SESSION['group_students'];

                        // featurevalue1用のSELECT文 (attemptカラムをNULLとして補う)
                        $select_fv1 = "SELECT UID,WID,Understand,NULL AS attempt," . $selectcolumn;

                        // test_featurevalue用のSELECT文 (attemptカラムをそのまま使用)
                        $select_tfv = "SELECT UID,WID,Understand,attempt," . $selectcolumn;

                        $tempgroupsql = "($select_fv1 FROM featurevalue1 WHERE UID IN ($group_students_list))";
                        //UNION ALLなので二つのテーブルからの重複があったとしても許している。許さない場合はUNIONを使う。
                        $tempgroupsql .= " UNION ALL ";
                        $tempgroupsql .= "($select_tfv FROM test_featurevalue WHERE UID IN ($group_students_list))";
                        // ★★★★★ 修正ここまで ★★★★★
                        $result_groupsql = mysqli_query($conn, $tempgroupsql);
                        $allresult_group = [];
                        while ($row = mysqli_fetch_assoc($result_groupsql)) {
                            $allresult_group[] = $row;
                        }
                        $filename = "/xampp/htdocs/hesitateLMS/teacher/pydata/testdata_{$uniqueId}_{$timestamp}.csv";
                        $fp_group = fopen($filename, 'w');
                        if ($fp_group) {
                            fputcsv($fp_group, explode(',', $column_name));
                            foreach ($allresult_group as $row) {
                                fputcsv($fp_group, $row);
                            }
                            fclose($fp_group);
                        }
                    }

                    // 【教師データSQLの最終調整】(元の実装を維持)
                    if (isset($_SESSION['conditions']) && !empty($_SESSION['conditions'])) {
                        $tempwhere = $_SESSION['conditions'];
                    }
                    if (!empty($tempwhere)) {
                        $sql .= " WHERE " . implode(" AND ", $tempwhere);
                    }

                    // 分類対象として選択された学習者でテストデータを絞り込む
                    $sql_test .= " WHERE UID IN (" . $uid_list_str_for_sql . ")";

                    // --- この後のCSVファイル生成とPython実行部分は元のコードのまま ---

                    // 教師データ(featurevalue)の取得とCSV書き出し
                    $result = mysqli_query($conn, $sql);
                    while ($row = mysqli_fetch_assoc($result)) {
                        $allresult[] = $row;
                    }
                    $fp = fopen($test_filename, 'w');
                    fputcsv($fp, explode(',', $column_name));
                    foreach ($allresult as $row) {
                        fputcsv($fp, $row);
                    }
                    fclose($fp);

                    // テストデータ(test_featurevalue)の取得とCSV書き出し
                    $allresult_test = [];
                    $result_test = mysqli_query($conn, $sql_test);
                    while ($row = mysqli_fetch_assoc($result_test)) {
                        $allresult_test[] = $row;
                    }
                    $fp_test = fopen($testdata_filename, 'w');
                    fputcsv($fp_test, explode(',', $column_name));
                    foreach ($allresult_test as $row) {
                        fputcsv($fp_test, $row);
                    }
                    fclose($fp_test);
                    $machineLearningRunReady = true;

                    // 機械学習が実行可能になった時点の設定を、実行結果とは分けて記録する。
                    // ログ失敗は既存の機械学習処理を妨げない。
                    try {
                        require_once __DIR__ . '/machine-learning-usage-log.php';
                        $machineLearningTransitionAcknowledgement = machine_learning_usage_log_execution(
                            $conn,
                            (string)($_SESSION['MemberID'] ?? ''),
                            $_POST,
                            $selectedClassificationStudentUids,
                            $selectedClassificationGroupIds
                        );
                    } catch (Throwable $usageLogError) {
                        error_log('[machineLearning_sample usage log] ' . $usageLogError->getMessage());
                    }
                }

                if ($machineLearningInputError !== '') {
                    echo '<script type="text/javascript">alert('
                        . json_encode($machineLearningInputError, JSON_UNESCAPED_UNICODE)
                        . ');</script>';
                }
            }
            ?>
            <!--
            <section id = "class-overview" class="overview">
                <div align ="center">
                    <h2>学習者グループ概要</h2>
                </div>
                <font size = "5">
                    <div class="overview-contents">
                        <div id = "groupstu-info">
                            <h3>■グルーピング学習者数:
                                <?php
                                // URLに学習者IDが含まれているか確認
                                /*
                                if (isset($_SESSION['group_students']) && !empty($_SESSION['group_students'])) {
                                    // `students`パラメータから学習者IDを取得して配列に変換
                                    $student_ids = explode(',', $_SESSION['group_students']);

                                    // 学習者IDをカウント
                                    $student_count = count($student_ids);

                                    // 学習者数を表示
                                    echo $student_count . "人";
                                } else {
                                    // URLに学習者情報が含まれていない場合のメッセージ
                                    echo "学習者グループはありません";
                                }
                            ?>
                        </h3>
                    </div>
                    <div id = "groupques-info">
                        <h3>■全データ数:
                            <?php
                                // データベースからデータ数を取得
                                // URLに学習者IDが含まれているか確認
                                if (isset($_SESSION['group_students']) && !empty($_SESSION['group_students'])) {
                                    // `students`パラメータから学習者IDを取得して配列に変換
                                    $student_ids = explode(',', $_SESSION['group_students']);

                                    // `UID`リストをSQLクエリ用の文字列に変換
                                    $uid_list = implode("','", array_map('intval', $student_ids));

                                    // データベースから指定されたUIDに基づいて行数を取得
                                    $query = "SELECT COUNT(*) AS data_count FROM featurevalue1 WHERE UID IN ('$uid_list')";
                                    $result = mysqli_query($conn, $query);


                                    // データ数を取得して表示
                                    if ($result) {
                                        $row = mysqli_fetch_assoc($result);
                                        $data_count = $row['data_count'];
                                        echo $data_count . "件";
                                    } else {
                                        echo "データがありません";
                                    }
                                } else {
                                    // URLに学習者情報が含まれていない場合のメッセージ
                                    echo "データがありません";
                                }
                                    */
                                ?>
                            </h3>
                        </div>
                    </div>
                </font>
            </section>
                                -->
            <section class="group-chart card ml-card">
                <h2><?= translate('machineLearning_sample.php_569行目_作成したグループの成績') ?></h2>
                <?php if (empty($groups)): ?>
                    <p class="ml-empty-state"><?= htmlspecialchars(translate('machineLearning_sample.php_196行目_学習者グループがありません'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <div id="group-chart-container"></div>
            </section>

            <script>
                function openFeatureModalgraph(index, isOverall) {
                    console.log('index:', index);
                    selectedGroupIndex = index;
                    document.getElementById('feature-modal-graph').style.display = 'block';

                    // 特徴量選択後の適用ボタンに対して適切な配列とインデックスを設定
                    document.getElementById('apply-features-btn').onclick = function() {
                        void applySelectedFeatures(isOverall ? existingOverallCharts : existingClassCharts, index, isOverall);
                    };
                }
                //モーダルを閉じる
                function closeFeatureModalgraph() {
                    document.getElementById('feature-modal-graph').style.display = 'none';
                    document.getElementById('feature-form').reset();
                }
                const groupData = <?php echo json_encode($groups); ?>;
                console.log(groupData);

                function getFeatureDisplayMeta(feature) {
                    return window.featureDisplayMeta?.[feature] || { displayScale: 1, unit: '' };
                }

                function toFeatureDisplayValue(feature, value) {
                    const number = Number(value);
                    if (!Number.isFinite(number)) {
                        return value;
                    }
                    const scale = Number(getFeatureDisplayMeta(feature).displayScale || 1);
                    return number * scale;
                }

                function featureLabelHasUnit(label, unit) {
                    if (!unit) {
                        return true;
                    }
                    const lowerLabel = String(label).toLowerCase();
                    const lowerUnit = String(unit).toLowerCase();
                    return lowerLabel.includes(`（${lowerUnit}）`) ||
                        lowerLabel.includes(`(${lowerUnit})`) ||
                        (lowerUnit.length > 1 && lowerLabel.includes(lowerUnit));
                }

                function appendFeatureUnit(label, feature) {
                    const unit = getFeatureDisplayMeta(feature).unit || '';
                    if (!unit || featureLabelHasUnit(label, unit)) {
                        return label;
                    }
                    return `${label}（${unit}）`;
                }

                function getFeatureLabelFromInput(feature) {
                    const input = Array.from(document.querySelectorAll('input[name="feature"], input[name="featureLabel[]"]'))
                        .find((candidate) => candidate.value === feature || candidate.dataset.featureName === feature);
                    const label = input?.closest('label');
                    if (!label) {
                        return appendFeatureUnit(feature, feature);
                    }

                    const clone = label.cloneNode(true);
                    clone.querySelectorAll('input, .info-icon').forEach((node) => node.remove());
                    const text = clone.textContent.trim();
                    return appendFeatureUnit(text || feature, feature);
                }

                function applyFeatureUnitsToLabels() {
                    document.querySelectorAll('input[name="feature"], input[name="featureLabel[]"]').forEach((input) => {
                        const label = input.closest('label');
                        if (!label) {
                            return;
                        }

                        const feature = input.dataset.featureName || label.querySelector('.info-icon')?.dataset.featureName || input.value;
                        const unit = getFeatureDisplayMeta(feature).unit || '';
                        if (!unit) {
                            return;
                        }

                        Array.from(label.childNodes).some((node) => {
                            if (node.nodeType !== Node.TEXT_NODE || node.textContent.trim() === '') {
                                return false;
                            }
                            node.textContent = appendFeatureUnit(node.textContent.trim(), feature);
                            return true;
                        });
                    });
                }

                document.addEventListener("DOMContentLoaded", function() {
                    applyFeatureUnitsToLabels();
                    const container = document.getElementById('group-chart-container');

                    groupData.forEach((group, index) => {
                        const groupContainer = document.createElement('div');
                        groupContainer.classList.add('class-card');
                        groupContainer.innerHTML = `
                <h3>${group.group_name}
                    <button type="button" class="graph-feature-button" onclick="openFeatureModalgraph(${index}, false)"><?= translate('machineLearning_sample.php_584行目_グラフ描画特徴量') ?></button>
                </h3>
                <div class="chart-row">
                    <canvas id="dual-axis-chart-${index}"></canvas>
                </div>
            `;

                        container.appendChild(groupContainer);

                        const labels = group.students.map(student => student.name);
                        const notaccuracyData = group.students.map(student => student.notaccuracy);
                        const timeData = group.students.map(student => student.time);
                        //console.log(labels);
                        //console.log(notaccuracyData);
                        //console.log(timeData);

                        createDualAxisChart(
                            document.getElementById(`dual-axis-chart-${index}`).getContext('2d'),
                            labels,
                            notaccuracyData,
                            timeData,
                            <?= json_encode(translate('machineLearning_sample.php_600行目_不正解率(%)')) ?>,
                            <?= json_encode(translate('machineLearning_sample.php_601行目_解答時間(秒)')) ?>,
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            <?= json_encode(translate('machineLearning_sample.php_604行目_不正解率(%)')) ?>,
                            <?= json_encode(translate('machineLearning_sample.php_605行目_解答時間(秒)')) ?>,
                            existingClassCharts, // クラス別グラフ用の配列
                            index
                        );
                    });
                });
            </script>
            <script>
                // クラス別グラフを管理する配列
                let existingClassCharts = [];

                function createDualAxisChart(ctx, labels, data1, data2, label1, label2, color1, color2, yText1, yText2, chartArray, chartIndex) {
                    // 既存のチャートがある場合は破棄
                    if (chartArray[chartIndex]) {
                        chartArray[chartIndex].destroy();
                    }

                    // 新しいチャートを作成し、指定された配列に保存
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
                                        text: <?= json_encode(translate('machineLearning_sample.php_630行目_ユーザー名')) ?>,
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

                    return chartArray[chartIndex];
                }

                async function fetchGraphFeatureData(params) {
                    const response = await fetch('fetch_feature_data.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: params.toString()
                    });
                    const data = await response.json();
                    if (!response.ok || !Array.isArray(data) || data.error) {
                        throw new Error(data?.error || '特徴量データを取得できませんでした。');
                    }
                    if (data.length === 0) {
                        throw new Error('表示できる特徴量データがありません。');
                    }
                    return data;
                }

                function graphFeatureRenderSucceeded(chart, canvas, labels, data1, data2) {
                    return Boolean(
                        chart &&
                        canvas &&
                        canvas.isConnected &&
                        canvas.clientWidth > 0 &&
                        canvas.clientHeight > 0 &&
                        chart.canvas === canvas &&
                        Array.isArray(labels) &&
                        labels.length > 0 &&
                        Array.isArray(data1) &&
                        Array.isArray(data2) &&
                        data1.length === labels.length &&
                        data2.length === labels.length &&
                        data1.every(value => value !== null && value !== undefined && Number.isFinite(Number(value))) &&
                        data2.every(value => value !== null && value !== undefined && Number.isFinite(Number(value))) &&
                        chart.data?.labels?.length === labels.length &&
                        chart.data?.datasets?.length === 2 &&
                        chart.data.datasets.every(dataset => dataset.data.length === labels.length)
                    );
                }

                async function recordGraphFeatureUsage(group, selectedFeatures, displayedStudentCount) {
                    const response = await fetch('save_graph_feature_log.php', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            group_id: group.group_id,
                            selected_features: selectedFeatures,
                            displayed_student_count: displayedStudentCount
                        })
                    });
                    const result = await response.json();
                    if (!response.ok || result.status !== 'success') {
                        throw new Error(result.message || 'グラフ描画特徴量の利用ログを保存できませんでした。');
                    }
                }

                async function applySelectedFeatures(chartArray, chartIndex, isOverall) {
                    const selectedFeatures = Array.from(document.querySelectorAll('#feature-form input[type="checkbox"]:checked'))
                        .map(input => input.value);
                    if (selectedFeatures.length !== 2) {
                        alert(<?= json_encode(translate('machineLearning_sample.php_752行目_2つの特徴量を選択してください')) ?>);
                        return;
                    }

                    const group = isOverall && typeof classData !== 'undefined' ?
                        classData[chartIndex] : groupData[chartIndex];
                    const students = isOverall ? group?.class_students : group?.students;
                    if (!group || !Array.isArray(students) || students.length === 0) {
                        console.error('グラフ表示対象の学習者が見つかりません。');
                        return;
                    }

                    const applyButton = document.getElementById('apply-features-btn');
                    if (!applyButton || applyButton.disabled) {
                        return;
                    }
                    applyButton.disabled = true;

                    try {
                        const studentIDs = students.map(student => student.student_id).join(',');
                        const includesNotAccuracy = selectedFeatures.includes('notaccuracy');
                        const requestedFeatures = includesNotAccuracy ?
                            [selectedFeatures.find(feature => feature !== 'notaccuracy')] : selectedFeatures;
                        if (!requestedFeatures[0]) {
                            throw new Error(<?= json_encode(translate('machineLearning_sample.php_710行目_不正解率と一緒にもう1つの特徴量を選択してください')) ?>);
                        }

                        const params = new URLSearchParams({
                            features: requestedFeatures.join(','),
                            studentIDs: studentIDs
                        });
                        const data = await fetchGraphFeatureData(params);
                        const labels = data.map(item => item.name);
                        let data1;
                        let data2;
                        let label1;
                        let label2;

                        if (includesNotAccuracy) {
                            const notAccuracyByStudent = new Map(
                                students.map(student => [String(student.student_id), student.notaccuracy])
                            );
                            data1 = data.map(item => notAccuracyByStudent.get(String(item.student_id)));
                            data2 = data.map(item => item.featureA_avg);
                            label1 = <?= json_encode(translate('machineLearning_sample.php_734行目_不正解率(%)')) ?>;
                            label2 = `${getFeatureLabelFromInput(requestedFeatures[0])} ` + <?= json_encode(translate('machineLearning_sample.php_735行目_平均')) ?>;
                        } else {
                            data1 = data.map(item => item.featureA_avg);
                            data2 = data.map(item => item.featureB_avg);
                            label1 = `${getFeatureLabelFromInput(selectedFeatures[0])} ` + <?= json_encode(translate('machineLearning_sample.php_777行目_平均')) ?>;
                            label2 = `${getFeatureLabelFromInput(selectedFeatures[1])} ` + <?= json_encode(translate('machineLearning_sample.php_778行目_平均')) ?>;
                        }

                        const canvasId = isOverall ?
                            `class-dual-axis-chart-${chartIndex}` :
                            `dual-axis-chart-${chartIndex}`;
                        const canvas = document.getElementById(canvasId);
                        if (!canvas) {
                            throw new Error('グラフ描画領域が見つかりません。');
                        }

                        const chart = createDualAxisChart(
                            canvas.getContext('2d'),
                            labels,
                            data1,
                            data2,
                            label1,
                            label2,
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            label1,
                            label2,
                            chartArray,
                            chartIndex
                        );

                        await new Promise(resolve => window.requestAnimationFrame(resolve));
                        if (!graphFeatureRenderSucceeded(chart, canvas, labels, data1, data2)) {
                            throw new Error('グラフを正しく描画できませんでした。');
                        }

                        closeFeatureModalgraph();
                        if (!isOverall && group.group_id) {
                            void recordGraphFeatureUsage(group, selectedFeatures, labels.length)
                                .catch(error => console.warn('グラフ描画特徴量の利用ログを保存できませんでした。', error));
                        }
                    } catch (error) {
                        console.error('グラフ描画特徴量の適用に失敗しました。', error);
                    } finally {
                        applyButton.disabled = false;
                    }
                }
            </script>


            <section class="progress-chart card ml-card">
                <h2><?= translate('machineLearning_sample.php_794行目_特徴量選択') ?></h2>
                <div id="feature-modal-area">
                    <button class="feature-button" onclick="openFeatureModal()">
                        <span class="icon">🔍</span> <?= translate('machineLearning_sample.php_797行目_特徴量を選択') ?>
                    </button>
                </div>
            </section>


            <script>
                const machineLearningTransitionTeacherId = <?= json_encode((string)($_SESSION['TID'] ?? $_SESSION['MemberID'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
                const machineLearningTransitionMarkerKey = `hesitateLms:correlationToClustering:${machineLearningTransitionTeacherId}`;
                const machineLearningTransitionCountKey = `hesitateLms:correlationToMachineLearningCounts:${machineLearningTransitionTeacherId}`;
                const machineLearningTransitionStateVersionKey = `${machineLearningTransitionCountKey}:version`;
                const machineLearningTransitionStateVersion = '2';
                const machineLearningTransitionModes = ['understand', 'hesitation_degree', 'feature_pair'];
                const machineLearningTransitionMarkerLifetimeMs = 10000;
                let pendingMachineLearningCorrelationTransition = null;

                function emptyMachineLearningTransitionSnapshot() {
                    return {
                        total: 0,
                        understand: 0,
                        hesitation_degree: 0,
                        feature_pair: 0,
                    };
                }

                function normalizeMachineLearningTransitionCount(value) {
                    const count = Number(value);
                    return Number.isInteger(count) && count > 0 ? Math.min(count, 10000) : 0;
                }

                function ensureMachineLearningTransitionStateVersion() {
                    if (!machineLearningTransitionTeacherId) return;
                    try {
                        if (localStorage.getItem(machineLearningTransitionStateVersionKey)
                            === machineLearningTransitionStateVersion) {
                            return;
                        }
                        localStorage.removeItem(machineLearningTransitionCountKey);
                        localStorage.setItem(
                            machineLearningTransitionStateVersionKey,
                            machineLearningTransitionStateVersion
                        );
                    } catch (error) {
                        // ブラウザー保存が利用できない場合は移動回数0として継続する。
                    }
                }

                function normalizeMachineLearningTransitionSnapshot(rawSnapshot) {
                    const snapshot = emptyMachineLearningTransitionSnapshot();
                    let remaining = 10000;
                    machineLearningTransitionModes.forEach((mode) => {
                        snapshot[mode] = Math.min(
                            remaining,
                            normalizeMachineLearningTransitionCount(rawSnapshot?.[mode] || 0)
                        );
                        remaining -= snapshot[mode];
                    });
                    snapshot.total = 10000 - remaining;
                    return snapshot;
                }

                function machineLearningMarkerSnapshot(marker) {
                    if (marker?.correlation_counts && typeof marker.correlation_counts === 'object') {
                        return normalizeMachineLearningTransitionSnapshot(marker.correlation_counts);
                    }
                    const legacySnapshot = emptyMachineLearningTransitionSnapshot();
                    if (machineLearningTransitionModes.includes(marker?.analysis_mode)) {
                        legacySnapshot[marker.analysis_mode] = 1;
                        legacySnapshot.total = 1;
                    }
                    return legacySnapshot;
                }

                function addMachineLearningTransitionSnapshots(currentSnapshot, addedSnapshot) {
                    const combined = normalizeMachineLearningTransitionSnapshot(currentSnapshot);
                    let remaining = Math.max(0, 10000 - combined.total);
                    machineLearningTransitionModes.forEach((mode) => {
                        if (remaining <= 0) return;
                        const added = Math.min(
                            remaining,
                            normalizeMachineLearningTransitionCount(addedSnapshot?.[mode] || 0)
                        );
                        combined[mode] += added;
                        remaining -= added;
                    });
                    combined.total = combined.understand
                        + combined.hesitation_degree
                        + combined.feature_pair;
                    return combined;
                }

                function readMachineLearningTransitionSnapshot() {
                    if (!machineLearningTransitionTeacherId) return emptyMachineLearningTransitionSnapshot();
                    try {
                        const parsed = JSON.parse(localStorage.getItem(machineLearningTransitionCountKey) || '{}');
                        const snapshot = emptyMachineLearningTransitionSnapshot();
                        let remaining = 10000;
                        machineLearningTransitionModes.forEach((mode) => {
                            snapshot[mode] = Math.min(
                                remaining,
                                normalizeMachineLearningTransitionCount(parsed?.[mode] || 0)
                            );
                            remaining -= snapshot[mode];
                        });
                        snapshot.total = 10000 - remaining;
                        return snapshot;
                    } catch (error) {
                        return emptyMachineLearningTransitionSnapshot();
                    }
                }

                function writeMachineLearningTransitionSnapshot(snapshot) {
                    if (!machineLearningTransitionTeacherId) return;
                    try {
                        const normalized = emptyMachineLearningTransitionSnapshot();
                        let remaining = 10000;
                        machineLearningTransitionModes.forEach((mode) => {
                            normalized[mode] = Math.min(
                                remaining,
                                normalizeMachineLearningTransitionCount(snapshot?.[mode] || 0)
                            );
                            remaining -= normalized[mode];
                        });
                        normalized.total = 10000 - remaining;
                        localStorage.setItem(machineLearningTransitionCountKey, JSON.stringify(normalized));
                    } catch (error) {
                        // ブラウザー保存が利用できない場合は移動回数0として継続する。
                    }
                }

                function syncMachineLearningTransitionInputs() {
                    const snapshot = readMachineLearningTransitionSnapshot();
                    const values = {
                        'ml-correlation-transition-total': snapshot.total,
                        'ml-correlation-transition-understand': snapshot.understand,
                        'ml-correlation-transition-hesitation-degree': snapshot.hesitation_degree,
                        'ml-correlation-transition-feature-pair': snapshot.feature_pair,
                    };
                    Object.entries(values).forEach(([id, value]) => {
                        const input = document.getElementById(id);
                        if (input) input.value = String(value);
                    });
                    return snapshot;
                }

                function consumeMachineLearningCorrelationMarker() {
                    if (!machineLearningTransitionTeacherId || document.hidden) return;
                    try {
                        const rawMarker = localStorage.getItem(machineLearningTransitionMarkerKey);
                        if (!rawMarker) return;
                        localStorage.removeItem(machineLearningTransitionMarkerKey);
                        const marker = JSON.parse(rawMarker);
                        const age = Date.now() - Number(marker?.hidden_at || 0);
                        const markerSnapshot = machineLearningMarkerSnapshot(marker);
                        if (marker?.teacher_id !== machineLearningTransitionTeacherId
                            || marker?.source !== 'feature_correlation.php'
                            || markerSnapshot.total === 0
                            || age < 0
                            || age > machineLearningTransitionMarkerLifetimeMs) {
                            return;
                        }
                        pendingMachineLearningCorrelationTransition = markerSnapshot;
                    } catch (error) {
                        pendingMachineLearningCorrelationTransition = null;
                        try {
                            localStorage.removeItem(machineLearningTransitionMarkerKey);
                        } catch (storageError) {
                            // 保存領域へアクセスできなくても画面操作は継続する。
                        }
                    }
                }

                function commitMachineLearningCorrelationTransition() {
                    const markerSnapshot = pendingMachineLearningCorrelationTransition;
                    pendingMachineLearningCorrelationTransition = null;
                    if (!markerSnapshot || markerSnapshot.total === 0) {
                        syncMachineLearningTransitionInputs();
                        return;
                    }
                    writeMachineLearningTransitionSnapshot(addMachineLearningTransitionSnapshots(
                        readMachineLearningTransitionSnapshot(),
                        markerSnapshot
                    ));
                    syncMachineLearningTransitionInputs();
                }

                function acknowledgeMachineLearningCorrelationTransition(usedSnapshot) {
                    const current = readMachineLearningTransitionSnapshot();
                    machineLearningTransitionModes.forEach((mode) => {
                        current[mode] = Math.max(
                            0,
                            current[mode] - normalizeMachineLearningTransitionCount(usedSnapshot?.[mode] || 0)
                        );
                    });
                    writeMachineLearningTransitionSnapshot(current);
                    syncMachineLearningTransitionInputs();
                }

                window.MachineLearningCorrelationTransition = {
                    getSnapshot: syncMachineLearningTransitionInputs,
                    acknowledge: acknowledgeMachineLearningCorrelationTransition,
                };

                function openFeatureModal() {
                    document.getElementById("feature-modal").style.display = "block";
                    consumeMachineLearningCorrelationMarker();
                    commitMachineLearningCorrelationTransition();
                }

                function closeFeatureModal() {
                    document.getElementById("feature-modal").style.display = "none";
                }

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        pendingMachineLearningCorrelationTransition = null;
                        return;
                    }
                    consumeMachineLearningCorrelationMarker();
                    if (document.getElementById('feature-modal')?.style.display === 'block') {
                        commitMachineLearningCorrelationTransition();
                    }
                });
                window.addEventListener('focus', () => {
                    consumeMachineLearningCorrelationMarker();
                    if (document.getElementById('feature-modal')?.style.display === 'block') {
                        commitMachineLearningCorrelationTransition();
                    }
                });
                document.addEventListener('DOMContentLoaded', () => {
                    ensureMachineLearningTransitionStateVersion();
                    consumeMachineLearningCorrelationMarker();
                    syncMachineLearningTransitionInputs();
                    const form = document.getElementById('machineLearningForm');
                    form?.addEventListener('submit', syncMachineLearningTransitionInputs);

                    const acknowledgedSnapshot = <?= json_encode($machineLearningTransitionAcknowledgement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                    if (acknowledgedSnapshot) {
                        acknowledgeMachineLearningCorrelationTransition(acknowledgedSnapshot);
                    }
                });
            </script>

            <div id="feature-modal-graph" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeFeatureModalgraph()">&times;</span>
                    <h3><?= translate('machineLearning_sample.php_810行目_特徴量を選択してください') ?></h3>
                    <form id="feature-form">
                        <label><input type="checkbox" name="feature" value="notaccuracy">
                            <?= translate('machineLearning_sample.php_812行目_不正解率(%)') ?><span class="info-icon"
                                data-feature-name="notaccuracy">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="Time">
                            <?= translate('machineLearning_sample.php_813行目_解答時間(秒)') ?><span class="info-icon"
                                data-feature-name="Time">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="distance">
                            <?= translate('machineLearning_sample.php_814行目_距離') ?><span class="info-icon"
                                data-feature-name="distance">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="averageSpeed">
                            <?= translate('machineLearning_sample.php_815行目_平均速度') ?><span class="info-icon"
                                data-feature-name="averageSpeed">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="maxSpeed">
                            <?= translate('machineLearning_sample.php_816行目_最高速度') ?><span class="info-icon"
                                data-feature-name="maxSpeed">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="thinkingTime">
                            <?= translate('machineLearning_sample.php_817行目_考慮時間') ?><span class="info-icon"
                                data-feature-name="thinkingTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="answeringTime">
                            <?= translate('machineLearning_sample.php_818行目_第一ドロップ後解答時間') ?><span class="info-icon"
                                data-feature-name="answeringTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="totalStopTime">
                            <?= translate('machineLearning_sample.php_819行目_合計静止時間') ?><span class="info-icon"
                                data-feature-name="totalStopTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="maxStopTime">
                            <?= translate('machineLearning_sample.php_820行目_最大静止時間') ?><span class="info-icon"
                                data-feature-name="maxStopTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="totalDDIntervalTime">
                            <?= translate('machineLearning_sample.php_821行目_合計DD間時間') ?><span class="info-icon"
                                data-feature-name="totalDDIntervalTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="maxDDIntervalTime">
                            <?= translate('machineLearning_sample.php_822行目_最大DD間時間') ?><span class="info-icon"
                                data-feature-name="maxDDIntervalTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="maxDDTime">
                            <?= translate('machineLearning_sample.php_823行目_合計DD時間') ?><span class="info-icon"
                                data-feature-name="maxDDTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="minDDTime">
                            <?= translate('machineLearning_sample.php_824行目_最小DD時間') ?><span class="info-icon"
                                data-feature-name="minDDTime">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="DDCount">
                            <?= translate('machineLearning_sample.php_825行目_合計DD回数') ?><span class="info-icon"
                                data-feature-name="DDCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="groupingDDCount">
                            <?= translate('machineLearning_sample.php_826行目_グループ化DD回数') ?><span class="info-icon"
                                data-feature-name="groupingDDCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="groupingCountbool">
                            <?= translate('machineLearning_sample.php_827行目_グループ化有無') ?><span class="info-icon"
                                data-feature-name="groupingCountbool">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="xUturnCount">
                            <?= translate('machineLearning_sample.php_828行目_x軸Uターン回数') ?><span class="info-icon"
                                data-feature-name="xUturnCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="yUturnCount">
                            <?= translate('machineLearning_sample.php_829行目_y軸Uターン回数') ?><span class="info-icon"
                                data-feature-name="yUturnCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="register_move_count1">
                            <?= translate('machineLearning_sample.php_830行目_レジスタ→レジスタへの移動回数') ?><span class="info-icon"
                                data-feature-name="register_move_count1">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="register_move_count2">
                            <?= translate('machineLearning_sample.php_831行目_レジスタ→レジスタ外への移動回数') ?><span class="info-icon"
                                data-feature-name="register_move_count2">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="register_move_count3">
                            <?= translate('machineLearning_sample.php_832行目_レジスタ外→レジスタへの移動回数') ?><span class="info-icon"
                                data-feature-name="register_move_count3">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="register01count1">
                            <?= translate('machineLearning_sample.php_833行目_レジスタ→レジスタへの移動有無') ?><span class="info-icon"
                                data-feature-name="register01count1">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="register01count2">
                            <?= translate('machineLearning_sample.php_834行目_レジスタ→レジスタ外への移動有無') ?><span class="info-icon"
                                data-feature-name="register01count2">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="register01count3">
                            <?= translate('machineLearning_sample.php_835行目_レジスタ外→レジスタへの移動有無') ?><span class="info-icon"
                                data-feature-name="register01count3">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="registerDDCount">
                            <?= translate('machineLearning_sample.php_836行目_レジスタに関する合計の移動回数') ?><span class="info-icon"
                                data-feature-name="registerDDCount">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="xUturnCountDD">
                            <?= translate('machineLearning_sample.php_837行目_x軸UターンD&D回数') ?><span class="info-icon"
                                data-feature-name="xUturnCountDD">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature"
                                value="yUturnCountDD"><?= translate('machineLearning_sample.php_838行目_y軸UターンD&D回数') ?><span
                                class="info-icon" data-feature-name="yUturnCountDD">ⓘ</span></label><br>
                        <label><input type="checkbox" name="feature" value="FromlastdropToanswerTime">
                            <?= translate('machineLearning_sample.php_839行目_最終ドロップ後時間') ?><span class="info-icon"
                                data-feature-name="FromlastdropToanswerTime">ⓘ</span></label><br>
                        <button type="button"
                            id="apply-features-btn"><?= translate('machineLearning_sample.php_840行目_適用') ?></button>
                    </form>
                </div>
            </div>



            <div id="feature-modal" class="modal">
                <div class="moda-content-machineLearning">
                    <span class="close" onclick="closeFeatureModal()">&times;</span>
                    <form action="machineLearning_sample.php" id="machineLearningForm" method="post" target="_blank">
                        <table class="table2">
                            <tr>
                                <th><?= translate('machineLearning_sample.php_848行目_使用データ') ?></th>
                                <td>
                                    <label for="groupdata">
                                        <input type="radio" class="feature-modal-checkbox" id="groupdata" name="useData"
                                            value="groupdata">
                                        <?= translate('machineLearning_sample.php_851行目_作成したグループデータのみ') ?>
                                    </label>
                                    <select id="selectedGroup" name="selectedGroup" style="display: none;">
                                        <option value=""><?= translate('machineLearning_sample.php_856行目_選択してください') ?>
                                        </option>
                                        <?php

                                        $sql = "SELECT g.group_id, g.group_name
                                                    FROM `groups` g
                                                    WHERE g.TID = ?";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->bind_param('s', $_SESSION['MemberID']);

                                        $stmt->execute();
                                        $result = $stmt->get_result();

                                        while ($row = $result->fetch_assoc()) {
                                            echo "<option value = '{$row['group_id']}'>{$row['group_name']}</option>";
                                        }
                                        $stmt->close();
                                        ?>
                                    </select>

                                </td>
                                <script>
                                    document.addEventListener('DOMContentLoaded', () => {
                                        const groupDataRadio = document.getElementById('groupdata');
                                        const groupDropdown = document.getElementById('selectedGroup');
                                        const form = document.getElementById('machineLearningForm');
                                        const classificationSelectAll = document.getElementById('classification-select-all');
                                        const classificationStudentCheckboxes = Array.from(
                                            document.querySelectorAll('.classification-student-checkbox')
                                        );
                                        const classificationClassToggles = Array.from(
                                            document.querySelectorAll('.classification-class-toggle')
                                        );
                                        const classificationGroupToggles = Array.from(
                                            document.querySelectorAll('.classification-group-toggle')
                                        );

                                        function getClassificationGroupMemberUids(toggle) {
                                            try {
                                                const memberUids = JSON.parse(toggle.dataset.memberUids || '[]');
                                                return new Set(memberUids.map(String));
                                            } catch (error) {
                                                return new Set();
                                            }
                                        }

                                        function syncClassificationSelectionControls() {
                                            const selectedCount = classificationStudentCheckboxes.filter(
                                                checkbox => checkbox.checked
                                            ).length;

                                            if (classificationSelectAll) {
                                                classificationSelectAll.checked =
                                                    classificationStudentCheckboxes.length > 0 &&
                                                    selectedCount === classificationStudentCheckboxes.length;
                                                classificationSelectAll.indeterminate =
                                                    selectedCount > 0 &&
                                                    selectedCount < classificationStudentCheckboxes.length;
                                            }

                                            classificationClassToggles.forEach(toggle => {
                                                const classCheckboxes = classificationStudentCheckboxes.filter(
                                                    checkbox => checkbox.dataset.classId === toggle.dataset.classId
                                                );
                                                const selectedClassCount = classCheckboxes.filter(
                                                    checkbox => checkbox.checked
                                                ).length;
                                                toggle.checked =
                                                    classCheckboxes.length > 0 &&
                                                    selectedClassCount === classCheckboxes.length;
                                                toggle.indeterminate =
                                                    selectedClassCount > 0 &&
                                                    selectedClassCount < classCheckboxes.length;
                                            });

                                            classificationGroupToggles.forEach(toggle => {
                                                const memberUids = getClassificationGroupMemberUids(toggle);
                                                const groupCheckboxes = classificationStudentCheckboxes.filter(
                                                    checkbox => memberUids.has(String(checkbox.value))
                                                );
                                                const selectedGroupCount = groupCheckboxes.filter(
                                                    checkbox => checkbox.checked
                                                ).length;
                                                toggle.checked =
                                                    groupCheckboxes.length > 0 &&
                                                    selectedGroupCount === groupCheckboxes.length;
                                                toggle.indeterminate =
                                                    selectedGroupCount > 0 &&
                                                    selectedGroupCount < groupCheckboxes.length;
                                            });
                                        }

                                        // ラジオボタンのクリックイベント
                                        groupDataRadio.addEventListener('change', () => {
                                            if (groupDataRadio.checked) {
                                                groupDropdown.style.display = 'block'; // プルダウンを表示
                                            }

                                        });

                                        // プルダウンの選択イベント
                                        groupDropdown.addEventListener('change', () => {
                                            console.log("選択された値:", groupDropdown.value);
                                        });

                                        // 他のラジオボタンが選択された場合にプルダウンを隠す（他のラジオボタンの例）
                                        document.querySelectorAll('input[name="useData"]').forEach(radio => {
                                            if (radio.id !== 'groupdata') {
                                                radio.addEventListener('change', () => {
                                                    groupDropdown.style.display = 'none'; // プルダウンを非表示
                                                });
                                            }
                                        });

                                        if (classificationSelectAll) {
                                            classificationSelectAll.addEventListener('change', () => {
                                                classificationStudentCheckboxes.forEach(checkbox => {
                                                    checkbox.checked = classificationSelectAll.checked;
                                                });
                                                syncClassificationSelectionControls();
                                            });
                                        }

                                        classificationClassToggles.forEach(toggle => {
                                            toggle.addEventListener('change', () => {
                                                classificationStudentCheckboxes
                                                    .filter(checkbox => checkbox.dataset.classId === toggle.dataset.classId)
                                                    .forEach(checkbox => {
                                                        checkbox.checked = toggle.checked;
                                                    });
                                                syncClassificationSelectionControls();
                                            });
                                        });

                                        classificationGroupToggles.forEach(toggle => {
                                            toggle.addEventListener('change', () => {
                                                const memberUids = getClassificationGroupMemberUids(toggle);
                                                classificationStudentCheckboxes
                                                    .filter(checkbox => memberUids.has(String(checkbox.value)))
                                                    .forEach(checkbox => {
                                                        checkbox.checked = toggle.checked;
                                                    });
                                                syncClassificationSelectionControls();
                                            });
                                        });

                                        classificationStudentCheckboxes.forEach(checkbox => {
                                            checkbox.addEventListener('change', syncClassificationSelectionControls);
                                        });

                                        syncClassificationSelectionControls();

                                        // フォーム送信時のバリデーション
                                        form.addEventListener('submit', (e) => {
                                            if (groupDataRadio.checked && groupDropdown.value === '') {
                                                e.preventDefault();
                                                alert(<?= json_encode(translate('machineLearning_sample.php_896行目_作成したグループを選択してください')) ?>);
                                                groupDropdown.focus();
                                                return;
                                            }

                                            if (!classificationStudentCheckboxes.some(checkbox => checkbox.checked)) {
                                                e.preventDefault();
                                                alert('分類する学習者を1名以上選択してください。');
                                            }
                                        });
                                    });
                                </script>
                                <td>
                                    <label for="alldata">
                                        <input type="radio" class="feature-modal-checkbox" id="alldata" name="useData"
                                            value="alalldata">
                                        <?= translate('machineLearning_sample.php_903行目_2019年度のA大学全データ') ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>分類するデータ</th>
                                <td colspan="2">
                                    <?php if (empty($classificationStudentsByClass)): ?>
                                        <p class="classification-empty-message">
                                            担当クラスまたは作成済みグループ内に分類可能な学習者データがありません。
                                        </p>
                                    <?php else: ?>
                                        <?php
                                        $allClassificationStudentsSelected =
                                            count($selectedClassificationStudentUids) === count($allowedClassificationStudentUids);
                                        ?>
                                        <?php if (!empty($classificationGroups)): ?>
                                            <div class="classification-group-selector">
                                                <h4>作成したグループから選択</h4>
                                                <p class="classification-group-help">
                                                    グループを選択すると、所属する学習者のデータが分類対象になります。個別選択と併用できます。
                                                </p>
                                                <div class="classification-group-options">
                                                    <?php foreach ($classificationGroups as $classificationGroup): ?>
                                                        <?php
                                                        $selectedGroupMemberUids = array_filter(
                                                            $classificationGroup['member_uids'],
                                                            function ($uid) use ($selectedClassificationStudentUidLookup) {
                                                                return isset($selectedClassificationStudentUidLookup[$uid]);
                                                            }
                                                        );
                                                        $allClassificationGroupMembersSelected =
                                                            count($selectedGroupMemberUids) === count($classificationGroup['member_uids']);
                                                        $groupMemberUidsJson = json_encode(
                                                            $classificationGroup['member_uids'],
                                                            JSON_UNESCAPED_UNICODE
                                                        );
                                                        ?>
                                                        <label>
                                                            <input type="checkbox" name="classificationGroupIds[]"
                                                                class="classification-group-toggle"
                                                                value="<?= htmlspecialchars($classificationGroup['group_id'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-member-uids="<?= htmlspecialchars($groupMemberUidsJson, ENT_QUOTES, 'UTF-8') ?>"
                                                                <?= $allClassificationGroupMembersSelected ? 'checked' : '' ?>>
                                                            <?= htmlspecialchars($classificationGroup['group_name'], ENT_QUOTES, 'UTF-8') ?>
                                                            (<?= count($classificationGroup['member_uids']) ?>名)
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="classification-target-controls">
                                            <label>
                                                <input type="checkbox" id="classification-select-all"
                                                    <?= $allClassificationStudentsSelected ? 'checked' : '' ?>>
                                                分類可能な学習者を全て選択 / 解除
                                            </label>
                                        </div>
                                        <div class="classification-target-list">
                                            <?php foreach ($classificationStudentsByClass as $classificationClass): ?>
                                                <?php
                                                $classificationClassStudentUids = array_column($classificationClass['students'], 'UID');
                                                $selectedClassificationClassStudentUids = array_filter(
                                                    $classificationClassStudentUids,
                                                    function ($uid) use ($selectedClassificationStudentUidLookup) {
                                                        return isset($selectedClassificationStudentUidLookup[$uid]);
                                                    }
                                                );
                                                $allClassificationClassStudentsSelected =
                                                    count($selectedClassificationClassStudentUids) === count($classificationClassStudentUids);
                                                ?>
                                                <fieldset class="classification-class-group">
                                                    <legend>
                                                        <?= htmlspecialchars($classificationClass['ClassName'], ENT_QUOTES, 'UTF-8') ?>
                                                    </legend>
                                                    <label>
                                                        <input type="checkbox" class="classification-class-toggle"
                                                            data-class-id="<?= htmlspecialchars($classificationClass['ClassID'], ENT_QUOTES, 'UTF-8') ?>"
                                                            <?= $allClassificationClassStudentsSelected ? 'checked' : '' ?>>
                                                        このグループ(クラス)を全て選択 / 解除
                                                    </label>
                                                    <div class="classification-student-list">
                                                        <?php foreach ($classificationClass['students'] as $classificationStudent): ?>
                                                            <label>
                                                                <input type="checkbox" name="classificationUIDs[]"
                                                                    class="classification-student-checkbox"
                                                                    data-class-id="<?= htmlspecialchars($classificationClass['ClassID'], ENT_QUOTES, 'UTF-8') ?>"
                                                                    value="<?= htmlspecialchars($classificationStudent['UID'], ENT_QUOTES, 'UTF-8') ?>"
                                                                    <?= isset($selectedClassificationStudentUidLookup[$classificationStudent['UID']]) ? 'checked' : '' ?>>
                                                                <?= htmlspecialchars($classificationStudent['Name'], ENT_QUOTES, 'UTF-8') ?>
                                                                (<?= htmlspecialchars($classificationStudent['UID'], ENT_QUOTES, 'UTF-8') ?>)
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </fieldset>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!--20250117消去-->
                            <!--ここから
                            <tr>
                                <th>UID</th>
                                <td>
                                    <select name="UIDrange">
                                        <option value = "include">含む</option>
                                        <option value = "not">以外</option>
                                    </select>
                                </td>
                                <td>
                                   ここにfeaturevalueテーブルのUIDをチェックボックスで表示
                                    <?php
                                    /*
                                        $sql = "SELECT distinct UID FROM featurevalue";
                                        $res = $conn->query($sql);
                                        $counter = 0; // カウンタを初期化
                                        while($rows = $res -> fetch_assoc()){
                                            echo "<input type='checkbox' name='UID[]' value = '{$rows['UID']}'>{$rows['UID']}";
                                            $counter++; // カウンタをインクリメント
                                            // カウンタが4の倍数になった時に改行を挿入
                                            if($counter % 4 == 0){
                                                echo "<br>";
                                            }
                                        }
                                        */
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>WID</th>
                                <td>
                                    <select name="WIDrange">
                                        <option value = "include">含む</option>
                                        <option value = "not">以外</option>
                                    </select>
                                </td>
                                <td>
                                    <?php
                                    /*
                                        $sql = "SELECT distinct WID FROM featurevalue";
                                        $res = $conn->query($sql);
                                        $counter = 0;
                                        while($rows = $res -> fetch_assoc()){
                                            echo "<input type='checkbox' name='WID[]' value = '{$rows['WID']}'>{$rows['WID']}";
                                            $counter++;
                                            if($counter % 10 == 0){
                                                echo "<br>";
                                            }
                                        }
                                        */
                                    ?>
                                </td>
                            </tr>
                            ここまで-->
                            <!-- 分類器選択ボタン -->
                            <tr>
                                <th><?= translate('machineLearning_sample.php_951行目_分類器選択') ?></th>
                                <td colspan="2">
                                    <button type="button"
                                        onclick="selectClassifier('A')"><?= translate('machineLearning_sample.php_953行目_分類器A') ?></button>
                                    <button type="button"
                                        onclick="selectClassifier('B')"><?= translate('machineLearning_sample.php_954行目_分類器B') ?></button>
                                    <button type="button"
                                        onclick="selectClassifier('C')"><?= translate('machineLearning_sample.php_955行目_分類器C') ?></button>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_958行目_解答全体') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="featuretime"><input type="checkbox"
                                                    class="feature-modal-checkbox" id="featuretime"
                                                    name="featureLabel[]"
                                                    value="time"><?= translate('machineLearning_sample.php_961行目_解答時間') ?><span
                                                    class="info-icon" data-feature-name="Time">ⓘ</span></label>
                                        </li>
                                        <li><label for="featuredistance"><input type="checkbox"
                                                    class="feature-modal-checkbox" id="featuredistance"
                                                    name="featureLabel[]"
                                                    value="distance"><?= translate('machineLearning_sample.php_962行目_移動距離') ?><span
                                                    class="info-icon" data-feature-name="distance">ⓘ</span></label>
                                        </li>
                                        <li><label for="featurespeed"><input type="checkbox"
                                                    class="feature-modal-checkbox" id="featurespeed"
                                                    name="featureLabel[]"
                                                    value="averageSpeed"><?= translate('machineLearning_sample.php_963行目_平均速度') ?><span
                                                    class="info-icon" data-feature-name="averageSpeed">ⓘ</span></label>
                                        </li>
                                        <li><label for="featuremaxspeed"><input type="checkbox"
                                                    class="feature-modal-checkbox" id="featuremaxspeed"
                                                    name="featureLabel[]"
                                                    value="maxSpeed"><?= translate('machineLearning_sample.php_964行目_最大速度') ?><span
                                                    class="info-icon" data-feature-name="maxSpeed">ⓘ</span></label>
                                        </li>
                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="totalstoptime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="totalStopTime"><?= translate('machineLearning_sample.php_967行目_合計静止時間') ?><span
                                                    class="info-icon" data-feature-name="totalStopTime">ⓘ</span></label>
                                        </li>
                                        <li><label for="maxstoptime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="maxStopTime"><?= translate('machineLearning_sample.php_968行目_最大静止時間') ?><span
                                                    class="info-icon" data-feature-name="maxStopTime">ⓘ</span></label>
                                        </li>

                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="stopcount"><input type="checkbox" class="feature-modal-checkbox"
                                                    name="featureLabel[]"
                                                    value="stopcount"><?= translate('machineLearning_sample.php_972行目_静止回数') ?><span
                                                    class="info-icon" data-feature-name="stopcount">ⓘ</span></label>
                                        </li>
                                        <li><label for="FromlastdropToanswerTime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="FromlastdropToanswerTime"><?= translate('machineLearning_sample.php_973行目_最終dropから解答終了までの時間') ?><span
                                                    class="info-icon"
                                                    data-feature-name="FromlastdropToanswerTime">ⓘ</span></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_977行目_Uターン') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="xUturncount"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="xUTurnCount"><?= translate('machineLearning_sample.php_980行目_X軸Uターン回数') ?><span
                                                    class="info-icon" data-feature-name="xUturnCount">ⓘ</span></label>
                                        </li>
                                        <li><label for="yUturncount"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="yUTurnCount"><?= translate('machineLearning_sample.php_981行目_Y軸Uターン回数') ?><span
                                                    class="info-icon" data-feature-name="yUturnCount">ⓘ</span></label>
                                        </li>
                                        <li><label for="xUturncountDD"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="xUTurnCountDD"><?= translate('machineLearning_sample.php_982行目_次回DragまでのX軸Uターン回数') ?><span
                                                    class="info-icon" data-feature-name="xUturnCountDD">ⓘ</span></label>
                                        </li>
                                        <li><label for="yUturncountDD"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="yUTurnCountDD"><?= translate('machineLearning_sample.php_983行目_次回DragまでのY軸Uターン回数') ?><span
                                                    class="info-icon" data-feature-name="yUturnCountDD">ⓘ</span></label>
                                        </li>
                                    </ul>
                                </td>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_987行目_第一ドラッグ') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="featurethinkingtime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="thinkingTime"><?= translate('machineLearning_sample.php_990行目_第一ドラッグ前時間') ?><span
                                                    class="info-icon" data-feature-name="thinkingTime">ⓘ</span></label>
                                        </li>
                                        <li><label for="answeringtime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="answeringTime"><?= translate('machineLearning_sample.php_991行目_第一ドロップ後から解答終了を押すまでの時間') ?><span
                                                    class="info-icon" data-feature-name="answeringTime">ⓘ</span></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_995行目_DD') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="maxDDtime"><input type="checkbox" class="feature-modal-checkbox"
                                                    name="featureLabel[]"
                                                    value="maxDDTime"><?= translate('machineLearning_sample.php_999行目_最大DD時間') ?><span
                                                    class="info-icon" data-feature-name="maxDDTime">ⓘ</span></label>
                                        </li>
                                        <li><label for="minDDtime"><input type="checkbox" class="feature-modal-checkbox"
                                                    name="featureLabel[]"
                                                    value="minDDTime"><?= translate('machineLearning_sample.php_1000行目_最小DD時間') ?><span
                                                    class="info-icon" data-feature-name="minDDTime">ⓘ</span></label>
                                        </li>
                                        <li><label for="DDcount"><input type="checkbox" class="feature-modal-checkbox"
                                                    name="featureLabel[]"
                                                    value="DDCount"><?= translate('machineLearning_sample.php_1001行目_DD回数') ?><span
                                                    class="info-icon" data-feature-name="DDCount">ⓘ</span></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_1005行目_DD間') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="maxDDintervaltime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="maxDDIntervalTime"><?= translate('machineLearning_sample.php_1008行目_最大DD間時間') ?><span
                                                    class="info-icon"
                                                    data-feature-name="maxDDIntervalTime">ⓘ</span></label>
                                        </li>
                                        <li><label for="totalDDintervaltime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="totalDDIntervalTime"><?= translate('machineLearning_sample.php_1010行目_合計DD間時間') ?><span
                                                    class="info-icon"
                                                    data-feature-name="totalDDIntervalTime">ⓘ</span></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_1014行目_グループ化') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="groupingDDcount"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="groupingDDCount"><?= translate('machineLearning_sample.php_1017行目_グループ化中にDDした回数') ?><span
                                                    class="info-icon"
                                                    data-feature-name="groupingDDCount">ⓘ</span></label>
                                        </li>
                                        <li><label for="groupingDDcountbool"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="groupingCountbool"><?= translate('machineLearning_sample.php_1018行目_グループ化の有無') ?><span
                                                    class="info-icon"
                                                    data-feature-name="groupingCountbool">ⓘ</span></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_1022行目_レジスタ') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="register_move_count1"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register_move_count1"><?= translate('machineLearning_sample.php_1025行目_レジスタ移動回数1') ?><span
                                                    class="info-icon"
                                                    data-feature-name="register_move_count1">ⓘ</span></label>
                                        </li>
                                        <li><label for="register_move_count2"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register_move_count2"><?= translate('machineLearning_sample.php_1026行目_レジスタ移動回数2') ?><span
                                                    class="info-icon"
                                                    data-feature-name="register_move_count2">ⓘ</span></label>
                                        </li>
                                        <li><label for="register_move_count3"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register_move_count3"><?= translate('machineLearning_sample.php_1027行目_レジスタ移動回数3') ?><span
                                                    class="info-icon"
                                                    data-feature-name="register_move_count3">ⓘ</span></label>
                                        </li>
                                        <li><label for="register_move_count4"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register_move_count4"><?= translate('machineLearning_sample.php_1028行目_レジスタ移動回数4') ?><span
                                                    class="info-icon"
                                                    data-feature-name="register_move_count4">ⓘ</span></label>
                                        </li>
                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="register01count1"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register01count1"><?= translate('machineLearning_sample.php_1031行目_レジスタ使用回数1') ?><span
                                                    class="info-icon"
                                                    data-feature-name="register01count1">ⓘ</span></label>
                                        </li>
                                        <li><label for="register01count2"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register01count2"><?= translate('machineLearning_sample.php_1032行目_レジスタ使用回数2') ?><span
                                                    class="info-icon"
                                                    data-feature-name="register01count2">ⓘ</span></label>
                                        </li>
                                        <li><label for="register01count3"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register01count3"><?= translate('machineLearning_sample.php_1033行目_レジスタ使用回数3') ?><span
                                                    class="info-icon"
                                                    data-feature-name="register01count3">ⓘ</span></label>
                                        </li>
                                        <li><label for="register01count4"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register01count4"><?= translate('machineLearning_sample.php_1034行目_レジスタ使用回数4') ?><span
                                                    class="info-icon"
                                                    data-feature-name="register01count4">ⓘ</span></label>
                                        </li>
                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="registerDDcount"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="registerDDCount"><?= translate('machineLearning_sample.php_1037行目_レジスタ内DD回数') ?><span
                                                    class="info-icon"
                                                    data-feature-name="registerDDCount">ⓘ</span></label>
                                        </li>
                                    </ul>
                                </td>
                                <!-- <th><?= translate('machineLearning_sample.php_958行目_解答全体') ?></th> 
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="featuretime"><input type="checkbox"
                                                    class="feature-modal-checkbox" id="featuretime"
                                                    name="featureLabel[]"
                                                    value="time"><?= translate('machineLearning_sample.php_961行目_解答時間') ?></label>
                                        </li>
                                        <li><label for="featuredistance"><input type="checkbox"
                                                    class="feature-modal-checkbox" id="featuredistance"
                                                    name="featureLabel[]"
                                                    value="distance"><?= translate('machineLearning_sample.php_962行目_移動距離') ?></label>
                                        </li>
                                        <li><label for="featurespeed"><input type="checkbox"
                                                    class="feature-modal-checkbox" id="featurespeed"
                                                    name="featureLabel[]"
                                                    value="averageSpeed"><?= translate('machineLearning_sample.php_963行目_平均速度') ?></label>
                                        </li>
                                        <li><label for="featuremaxspeed"><input type="checkbox"
                                                    class="feature-modal-checkbox" id="featuremaxspeed"
                                                    name="featureLabel[]"
                                                    value="maxSpeed"><?= translate('machineLearning_sample.php_964行目_最大速度') ?></label>
                                        </li>
                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="totalstoptime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="totalStopTime"><?= translate('machineLearning_sample.php_967行目_合計静止時間') ?></label>
                                        </li>
                                        <li><label for="maxstoptime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="maxStopTime"><?= translate('machineLearning_sample.php_968行目_最大静止時間') ?></label>
                                        </li>

                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="stopcount"><input type="checkbox" class="feature-modal-checkbox"
                                                    name="featureLabel[]"
                                                    value="stopcount"><?= translate('machineLearning_sample.php_972行目_静止回数') ?></label>
                                        </li>
                                        <li><label for="FromlastdropToanswerTime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="FromlastdropToanswerTime"><?= translate('machineLearning_sample.php_973行目_最終dropから解答終了までの時間') ?></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_977行目_Uターン') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="xUturncount"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="xUTurnCount"><?= translate('machineLearning_sample.php_980行目_X軸Uターン回数') ?></label>
                                        </li>
                                        <li><label for="yUturncount"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="yUTurnCount"><?= translate('machineLearning_sample.php_981行目_Y軸Uターン回数') ?></label>
                                        </li>
                                        <li><label for="xUturncountDD"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="xUTurnCountDD"><?= translate('machineLearning_sample.php_982行目_次回DragまでのX軸Uターン回数') ?></label>
                                        </li>
                                        <li><label for="yUturncountDD"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="yUTurnCountDD"><?= translate('machineLearning_sample.php_983行目_次回DragまでのY軸Uターン回数') ?></label>
                                        </li>
                                    </ul>
                                </td>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_987行目_第一ドラッグ') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="featurethinkingtime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="thinkingTime"><?= translate('machineLearning_sample.php_990行目_第一ドラッグ前時間') ?></label>
                                        </li>
                                        <li><label for="answeringtime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="answeringTime"><?= translate('machineLearning_sample.php_991行目_第一ドロップ後から解答終了を押すまでの時間') ?></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_995行目_DD') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="maxDDtime"><input type="checkbox" class="feature-modal-checkbox"
                                                    name="featureLabel[]"
                                                    value="maxDDTime"><?= translate('machineLearning_sample.php_999行目_最大DD時間') ?></label>
                                        </li>
                                        <li><label for="minDDtime"><input type="checkbox" class="feature-modal-checkbox"
                                                    name="featureLabel[]"
                                                    value="minDDTime"><?= translate('machineLearning_sample.php_1000行目_最小DD時間') ?></label>
                                        </li>
                                        <li><label for="DDcount"><input type="checkbox" class="feature-modal-checkbox"
                                                    name="featureLabel[]"
                                                    value="DDCount"><?= translate('machineLearning_sample.php_1001行目_DD回数') ?></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_1005行目_DD間') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="maxDDintervaltime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="maxDDIntervalTime"><?= translate('machineLearning_sample.php_1008行目_最大DD間時間') ?></label>
                                        </li>
                                        <li><label for="totalDDintervaltime"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="totalDDIntervalTime"><?= translate('machineLearning_sample.php_1010行目_合計DD間時間') ?></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_1014行目_グループ化') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="groupingDDcount"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="groupingDDCount"><?= translate('machineLearning_sample.php_1017行目_グループ化中にDDした回数') ?></label>
                                        </li>
                                        <li><label for="groupingDDcountbool"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="groupingCountbool"><?= translate('machineLearning_sample.php_1018行目_グループ化の有無') ?></label>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th><?= translate('machineLearning_sample.php_1022行目_レジスタ') ?></th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="register_move_count1"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register_move_count1"><?= translate('machineLearning_sample.php_1025行目_レジスタ移動回数1') ?></label>
                                        </li>
                                        <li><label for="register_move_count2"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register_move_count2"><?= translate('machineLearning_sample.php_1026行目_レジスタ移動回数2') ?></label>
                                        </li>
                                        <li><label for="register_move_count3"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register_move_count3"><?= translate('machineLearning_sample.php_1027行目_レジスタ移動回数3') ?></label>
                                        </li>
                                        <li><label for="register_move_count4"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register_move_count4"><?= translate('machineLearning_sample.php_1028行目_レジスタ移動回数4') ?></label>
                                        </li>
                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="register01count1"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register01count1"><?= translate('machineLearning_sample.php_1031行目_レジスタ使用回数1') ?></label>
                                        </li>
                                        <li><label for="register01count2"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register01count2"><?= translate('machineLearning_sample.php_1032行目_レジスタ使用回数2') ?></label>
                                        </li>
                                        <li><label for="register01count3"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register01count3"><?= translate('machineLearning_sample.php_1033行目_レジスタ使用回数3') ?></label>
                                        </li>
                                        <li><label for="register01count4"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="register01count4"><?= translate('machineLearning_sample.php_1034行目_レジスタ使用回数4') ?></label>
                                        </li>
                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="registerDDcount"><input type="checkbox"
                                                    class="feature-modal-checkbox" name="featureLabel[]"
                                                    value="registerDDCount"><?= translate('machineLearning_sample.php_1037行目_レジスタ内DD回数') ?></label>
                                        </li>
                                    </ul>
                                </td> -->
                            </tr>
                        </table>
                        <input type="hidden" id="classifier-preset" name="classifierPreset" value="">
                        <input type="hidden" id="classifier-preset-modified" name="classifierPresetModified" value="0">
                        <input type="hidden" id="ml-correlation-transition-total" name="featureCorrelationTransitionCount" value="0">
                        <input type="hidden" id="ml-correlation-transition-understand" name="featureCorrelationUnderstandTransitionCount" value="0">
                        <input type="hidden" id="ml-correlation-transition-hesitation-degree" name="featureCorrelationHesitationDegreeTransitionCount" value="0">
                        <input type="hidden" id="ml-correlation-transition-feature-pair" name="featureCorrelationFeaturePairTransitionCount" value="0">
                        <input type="submit" id="machineLearningcons"
                            value="<?= translate('machineLearning_sample.php_1054行目_機械学習') ?>">
                        <button type="button" id="reset-button"
                            onclick="resetCheckboxes()"><?= translate('machineLearning_sample.php_1055行目_リセット') ?></button>
                    </form>
                </div>
            </div>
            <script>
                let classifierFeatureChangeIsProgrammatic = false;

                function setClassifierUsageState(preset, modified) {
                    const presetInput = document.getElementById('classifier-preset');
                    const modifiedInput = document.getElementById('classifier-preset-modified');
                    if (presetInput) {
                        presetInput.value = preset;
                    }
                    if (modifiedInput) {
                        modifiedInput.value = modified ? '1' : '0';
                    }
                }

                // 機械学習に使用する特徴量の選択をリセット
                function resetCheckboxes(clearClassifierUsage = true) {
                    const checkboxes = document.querySelectorAll('#feature-modal input[name="featureLabel[]"]');
                    checkboxes.forEach(checkbox => checkbox.checked = false);
                    if (clearClassifierUsage) {
                        setClassifierUsageState('', false);
                    }
                }

                // 分類器を選択した時に該当する特徴量をチェックする関数
                function selectClassifier(classifier) {
                    if (!['A', 'B', 'C'].includes(classifier)) {
                        return;
                    }

                    classifierFeatureChangeIsProgrammatic = true;
                    resetCheckboxes(false); // 特徴量だけをリセット

                    // feature-modal内のチェックボックスを特定
                    const modalCheckboxes = document.querySelectorAll("#feature-modal .feature-modal-checkbox");

                    function checkFeature(value) {
                        modalCheckboxes.forEach(checkbox => {
                            if (checkbox.value === value) {
                                checkbox.checked = true;
                            }
                        });
                    }

                    // 分類器Aの特徴量（B/Cもこの組み合わせを基礎とする）
                    [
                        'time', 'distance', 'averageSpeed', 'maxSpeed', 'thinkingTime',
                        'answeringTime', 'maxStopTime', 'xUTurnCount', 'yUTurnCount',
                        'DDCount', 'maxDDTime', 'maxDDIntervalTime', 'totalDDIntervalTime'
                    ].forEach(checkFeature);

                    // 分類器Bの特徴量（分類器Aに追加する特徴量）
                    if (classifier === 'B') {
                        checkFeature('groupingDDCount'); // グループ化中にDDした回数
                        checkFeature('groupingCountbool'); // グループ化の有無
                    }

                    // 分類器Cの特徴量（分類器Aに追加する特徴量）
                    if (classifier === 'C') {
                        checkFeature('register_move_count1'); // レジスタ移動回数1
                        checkFeature('register01count1'); // レジスタ使用回数1
                        checkFeature('register_move_count2'); // レジスタ移動回数2
                        checkFeature('register01count2'); // レジスタ使用回数2
                    }

                    setClassifierUsageState(classifier, false);
                    classifierFeatureChangeIsProgrammatic = false;
                }

                document.addEventListener('DOMContentLoaded', () => {
                    document.querySelectorAll('#feature-modal input[name="featureLabel[]"]').forEach(checkbox => {
                        checkbox.addEventListener('change', () => {
                            const presetInput = document.getElementById('classifier-preset');
                            if (!classifierFeatureChangeIsProgrammatic && presetInput?.value) {
                                setClassifierUsageState(presetInput.value, true);
                            }
                        });
                    });
                });
            </script>

            <section class="individual-details card ml-card">
                <div class="machinelearning-result">
                    <h2><?= translate('machineLearning_sample.php_1110行目_機械学習結果') ?></h2>
                    <div class="contents">
                        <h3><?= translate('machineLearning_sample.php_1112行目_解答情報') ?></h3>
                        <?php
                        require "../dbc.php";
                        if ($_SERVER["REQUEST_METHOD"] == "POST" && $machineLearningRunReady) {
                            // (...既存のPythonスクリプト実行とCSVファイル読み込み処理はそのまま...)
                            // この部分は変更しないでください
                            $pyscript = "./machineLearning/sampleSHAP.py";
                            $csvFile = "./machineLearning/results_actual_{$uniqueId}_{$timestamp}.csv";
                            $metricsFile = "./machineLearning/evaluation_metrics_{$uniqueId}_{$timestamp}.json";
                            exec("python {$pyscript} {$test_filename} {$testdata_filename} {$csvFile} {$metricsFile} 2>&1", $output, $status);

                            if ($status != 0) {
                                echo "実行エラー: ステータスコード " . $status;
                                echo "エラーメッセージ:\n" . implode("\n", $output);
                            } else {
                                // (...既存のCSV読み込みとtemporary_resultsテーブルへの保存処理...)
                                // この部分も変更しないでください
                                $metrics = null;
                                if (file_exists($metricsFile)) {
                                    $decodedMetrics = json_decode(file_get_contents($metricsFile), true);
                                    if (is_array($decodedMetrics)) {
                                        $metrics = $decodedMetrics;
                                    }
                                }

                                $accuracyAvailable = is_array($metrics)
                                    && ($metrics['available'] ?? false) === true
                                    && isset($metrics['mean_accuracy'])
                                    && is_numeric($metrics['mean_accuracy'])
                                    && (float)$metrics['mean_accuracy'] >= 0
                                    && (float)$metrics['mean_accuracy'] <= 1;

                                echo '<section class="ml-accuracy-summary" aria-labelledby="ml-accuracy-title">';
                                echo '<h3 id="ml-accuracy-title">'
                                    . htmlspecialchars(translate('machineLearning_sample.php_迷い推定精度'), ENT_QUOTES, 'UTF-8')
                                    . '</h3>';
                                if ($accuracyAvailable) {
                                    $accuracyPercent = number_format((float)$metrics['mean_accuracy'] * 100, 2);
                                    echo '<p class="ml-accuracy-value">' . $accuracyPercent . '%</p>';
                                } else {
                                    $accuracyMessageKey = (($metrics['unavailable_reason'] ?? '') === 'insufficient_class_samples')
                                        ? 'machineLearning_sample.php_精度算出データ不足'
                                        : 'machineLearning_sample.php_精度算出不可';
                                    echo '<p class="ml-accuracy-unavailable">'
                                        . htmlspecialchars(translate($accuracyMessageKey), ENT_QUOTES, 'UTF-8')
                                        . '</p>';
                                }
                                echo '</section>';
                                if (($handle = fopen($csvFile, "r")) !== FALSE) {
                                    $header = fgetcsv($handle, 1000, ",");
                                    $deleteQuery = "DELETE FROM temporary_results WHERE teacher_id = ?";
                                    $stmtDelete = $conn->prepare($deleteQuery);
                                    $stmtDelete->bind_param("i", $_SESSION['MemberID']);
                                    $stmtDelete->execute();
                                    $stmtDelete->close();
                                    $insertquery = "INSERT INTO temporary_results (UID,WID,Understand,teacher_id,attempt) VALUES (?,?,?,?,?)";
                                    $csvData = [];
                                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                                        $csvData[] = $data;
                                        $stmt = $conn->prepare($insertquery);
                                        $stmt->bind_param("iiisi", $data[0], $data[1], $data[2], $_SESSION['MemberID'], $data[3]);
                                        $stmt->execute();
                                    }
                                    fclose($handle);
                                    $stmt->close();
                                    $topData = $csvData;

                                    // ===== ここからが修正箇所です =====

                                    // ---------------------------------------------------------------------
                                    // 表示に必要な情報を事前に一括取得する (teachertrue.phpを参考)
                                    // ---------------------------------------------------------------------
                                    $student_names_map = []; // UIDをキー、名前を値とする連想配列
                                    $tf_lookup_map = [];      // 正誤情報格納用の連想配列
                                    $date_lookup_map = [];    // 解答日時格納用の連想配列

                                    // CSVからUIDのリストを重複なく取得
                                    $uids_from_csv = array_unique(array_column($csvData, 0));

                                    if (!empty($uids_from_csv)) {
                                        // 学習者名を取得
                                        $placeholders = implode(',', array_fill(0, count($uids_from_csv), '?'));
                                        $types = str_repeat('i', count($uids_from_csv));
                                        $name_stmt = $conn->prepare("SELECT UID, Name FROM students WHERE UID IN ($placeholders)");
                                        if ($name_stmt) {
                                            $name_stmt->bind_param($types, ...$uids_from_csv);
                                            $name_stmt->execute();
                                            $name_result = $name_stmt->get_result();
                                            while ($row = $name_result->fetch_assoc()) {
                                                $student_names_map[$row['UID']] = $row['Name'];
                                            }
                                            $name_stmt->close();
                                        }

                                        // 正誤(TF)と解答日時(Date)をlinedataから一括取得
                                        $tf_stmt = $conn->prepare("SELECT UID, WID, TF, Date, attempt FROM linedata WHERE UID IN ($placeholders)");
                                        if ($tf_stmt) {
                                            $tf_stmt->bind_param($types, ...$uids_from_csv);
                                            $tf_stmt->execute();
                                            $tf_result = $tf_stmt->get_result();
                                            while ($db_row = $tf_result->fetch_assoc()) {
                                                $key = "{$db_row['UID']}-{$db_row['WID']}-{$db_row['attempt']}";
                                                $tf_lookup_map[$key] = $db_row['TF'];
                                                $date_lookup_map[$key] = $db_row['Date'];
                                            }
                                            $tf_stmt->close();
                                        }
                                    }
                        ?>
                                    <div class="table-responsive" style="max-height: 450px; overflow-y: auto; border: 1px solid #ddd; border-radius: .25rem; margin-top: 1em;">
                                        <table id="results-table" class="table table-striped table-hover table-bordered mb-0" style="width: 100%;">
                                            <thead class="thead-light" style="position: sticky; top: 0; z-index: 1; background-color: #f8f9fa;">
                                                <tr>
                                                    <th><?= translate('teachertrue.php_学習者ID') ?></th>
                                                    <th><?= translate('teachertrue.php_学習者名') ?></th>
                                                    <th><?= translate('teachertrue.php_問題ID-～回目の解答') ?></th>
                                                    <th><?= translate('machineLearning_sample.php_1188行目_迷いの有無') ?></th>
                                                    <th><?= translate('machineLearning_sample.php_1195行目_正誤') ?></th>
                                                    <th><?= translate('teachertrue.php_解答日時') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($topData as $data) :
                                                    $uid = $data[0];
                                                    $wid = $data[1];
                                                    $understand = $data[2];
                                                    $attempt = $data[3];
                                                    $lookup_key = "{$uid}-{$wid}-{$attempt}";

                                                    // マップから情報を取得
                                                    $student_name = $student_names_map[$uid] ?? 'N/A';
                                                    $tf_value = $tf_lookup_map[$lookup_key] ?? null;
                                                    $answer_date = $date_lookup_map[$lookup_key] ?? 'N/A';
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($uid) ?></td>
                                                        <td><?= htmlspecialchars($student_name) ?></td>
                                                        <td><?= htmlspecialchars($wid) ?>-<?= htmlspecialchars($attempt) ?></td>
                                                        <td>
                                                            <?php
                                                            if ($understand == 4) {
                                                                echo htmlspecialchars(translate('machineLearning_sample.php_1213行目_迷い無し'));
                                                            } elseif ($understand == 2) {
                                                                echo "<span style='color: red; font-weight: bold;'>" . htmlspecialchars(translate('machineLearning_sample.php_1215行目_迷い有り')) . "</span>";
                                                            } else {
                                                                echo htmlspecialchars(translate('machineLearning_sample.php_1217行目_不明'));
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            if ($tf_value === '1' || $tf_value === 1) {
                                                                echo htmlspecialchars(translate('machineLearning_sample.php_1222行目_正解'));
                                                            } elseif ($tf_value === '0' || $tf_value === 0) {
                                                                echo "<span style='color: red; font-weight: bold;'>" . htmlspecialchars(translate('machineLearning_sample.php_1224行目_不正解')) . "</span>";
                                                            } else {
                                                                echo "N/A";
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($answer_date) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                        <?php
                                    // ===== 修正箇所ここまで =====
                                } else {
                                    echo translate('machineLearning_sample.php_1233行目_結果のCSVファイルを読み込めませんでした');
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div id="feature-detail-modal" class="feature-detail-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="detail-feature-title">
        <div class="feature-detail-modal-content">
            <span class="close-detail-modal" role="button" tabindex="0" aria-label="閉じる">&times;</span>
            <h3 id="detail-feature-title"></h3>
            <p id="detail-feature-description"></p>
        </div>
    </div>

    <script>
        // 特徴量ごとの説明データを定義 (machineLearning_sample.php 用)
        const featureDescriptions = {
            "notAccuracy": "<?= translate('machineLearning_sample.php_description_notAccuracy') ?>",
            "notaccuracy": "<?= translate('machineLearning_sample.php_description_notaccuracy') ?>",
            "stopcount": "<?= translate('machineLearning_sample.php_description_stopcount') ?>",
            "Time": "<?= translate('machineLearning_sample.php_description_Time') ?>",
            "distance": "<?= translate('machineLearning_sample.php_description_distance') ?>",
            "averageSpeed": "<?= translate('machineLearning_sample.php_description_averageSpeed') ?>",
            "maxSpeed": "<?= translate('machineLearning_sample.php_description_maxSpeed') ?>",
            "thinkingTime": "<?= translate('machineLearning_sample.php_description_thinkingTime') ?>",
            "answeringTime": "<?= translate('machineLearning_sample.php_description_answeringTime') ?>",
            "totalStopTime": "<?= translate('machineLearning_sample.php_description_totalStopTime') ?>",
            "maxStopTime": "<?= translate('machineLearning_sample.php_description_maxStopTime') ?>",
            "totalDDIntervalTime": "<?= translate('machineLearning_sample.php_description_totalDDIntervalTime') ?>",
            "maxDDIntervalTime": "<?= translate('machineLearning_sample.php_description_maxDDIntervalTime') ?>",
            "maxDDTime": "<?= translate('machineLearning_sample.php_description_maxDDTime') ?>",
            "minDDTime": "<?= translate('machineLearning_sample.php_description_minDDTime') ?>",
            "DDCount": "<?= translate('machineLearning_sample.php_description_DDCount') ?>",
            "groupingDDCount": "<?= translate('machineLearning_sample.php_description_groupingDDCount') ?>",
            "groupingCountbool": "<?= translate('machineLearning_sample.php_description_groupingCountbool') ?>",
            "xUturnCount": "<?= translate('machineLearning_sample.php_description_xUturnCount') ?>",
            "yUturnCount": "<?= translate('machineLearning_sample.php_description_yUturnCount') ?>",
            "register_move_count1": "<?= translate('machineLearning_sample.php_description_register_move_count1') ?>",
            "register_move_count2": "<?= translate('machineLearning_sample.php_description_register_move_count2') ?>",
            "register_move_count3": "<?= translate('machineLearning_sample.php_description_register_move_count3') ?>",
            "register01count1": "<?= translate('machineLearning_sample.php_description_register01count1') ?>",
            "register01count2": "<?= translate('machineLearning_sample.php_description_register01count2') ?>",
            "register01count3": "<?= translate('machineLearning_sample.php_description_register01count3') ?>",
            "registerDDCount": "<?= translate('machineLearning_sample.php_description_registerDDCount') ?>",
            "xUturnCountDD": "<?= translate('machineLearning_sample.php_description_xUturnCountDD') ?>",
            "yUturnCountDD": "<?= translate('machineLearning_sample.php_description_yUturnCountDD') ?>",
            "FromlastdropToanswerTime": "<?= translate('machineLearning_sample.php_description_FromlastdropToanswerTime') ?>",
            "hesitation": "<?= translate('machineLearning_sample.php_description_hesitation') ?>"
        };

        document.addEventListener('DOMContentLoaded', function() {
            const infoIcons = document.querySelectorAll('.info-icon');
            const detailModal = document.getElementById('feature-detail-modal');
            const detailTitle = document.getElementById('detail-feature-title');
            const detailDescription = document.getElementById('detail-feature-description');
            const closeDetailModal = document.querySelector('#feature-detail-modal .close-detail-modal');
            let lastFeatureInfoTrigger = null;

            function hideFeatureDetailModal() {
                detailModal.style.display = 'none';
                detailModal.setAttribute('aria-hidden', 'true');
                if (lastFeatureInfoTrigger) {
                    lastFeatureInfoTrigger.focus();
                    lastFeatureInfoTrigger = null;
                }
            }

            infoIcons.forEach(icon => {
                icon.setAttribute('role', 'button');
                icon.setAttribute('tabindex', '0');
                icon.setAttribute('aria-label', '特徴量の説明を表示');

                function showFeatureDetailModal(event) {
                    event.stopPropagation(); // 親要素へのイベント伝播を停止
                    event.preventDefault(); // デフォルトの動作（ここではlabelのinputへのクリック伝播）をキャンセル
                    lastFeatureInfoTrigger = this;

                    const featureName = this.dataset.featureName;
                    const description = featureDescriptions[featureName] || "<?= translate('machineLearning_sample.php_2000行目_この特徴量の説明はまだありません') ?>";

                    let featureLabelText = "";
                    const parentLabel = this.closest('label');
                    if (parentLabel) {
                        // labelの子要素からinputとinfo-iconを除外し、残りのテキストを取得
                        // input要素を見つけてその次のテキストノードがラベルテキストであると仮定
                        const inputElement = parentLabel.querySelector('input[type="checkbox"]');
                        if (inputElement && inputElement.nextSibling) {
                            featureLabelText = inputElement.nextSibling.textContent.trim();
                        } else {
                            // fallback to data-feature-name if text not found
                            featureLabelText = featureName;
                        }
                    } else {
                        featureLabelText = featureName;
                    }

                    detailTitle.textContent = featureLabelText;
                    detailDescription.textContent = description;
                    detailModal.style.display = 'block';
                    detailModal.setAttribute('aria-hidden', 'false');
                    closeDetailModal.focus();
                }

                icon.addEventListener('click', showFeatureDetailModal);
                icon.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        showFeatureDetailModal.call(this, event);
                    }
                });
            });

            closeDetailModal.addEventListener('click', hideFeatureDetailModal);
            closeDetailModal.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    hideFeatureDetailModal();
                }
            });

            window.addEventListener('click', function(event) {
                if (event.target == detailModal) {
                    hideFeatureDetailModal();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && detailModal.getAttribute('aria-hidden') === 'false') {
                    event.preventDefault();
                    hideFeatureDetailModal();
                }
            });
        });
    </script>
</body>

</html>

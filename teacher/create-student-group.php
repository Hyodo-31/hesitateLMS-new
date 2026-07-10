<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教師用ダッシュボード</title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <link rel="stylesheet" href="../style/teacher_form_styles.css?v=<?= filemtime(__DIR__ . '/../style/teacher_form_styles.css') ?>">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        //session_start();
        require "../dbc.php";
        require_once __DIR__ . "/student-feature-tooltip.php";
        // 繧ｻ繝・す繝ｧ繝ｳ螟画焚繧偵け繝ｪ繧｢縺吶ｋ・亥ｿ・ｦ√↓蠢懊§縺ｦ・・
        unset($_SESSION['conditions']);
        $teacher_id = $_SESSION['MemberID'] ?? '';
        $student_feature_columns_for_filter = student_feature_columns();
        $logic_filter_groups = [];
        $logic_filter_students_by_group = [];
        $stmt_logic_groups = $conn->prepare("SELECT group_id, group_name FROM `groups` WHERE TID = ? ORDER BY group_name");
        if ($stmt_logic_groups) {
            $stmt_logic_groups->bind_param("s", $teacher_id);
            $stmt_logic_groups->execute();
            $result_logic_groups = $stmt_logic_groups->get_result();
            while ($group_row = $result_logic_groups->fetch_assoc()) {
                $group_id = (string)$group_row['group_id'];
                $logic_filter_groups[] = $group_row;
                $logic_filter_students_by_group[$group_id] = [];
            }
            $stmt_logic_groups->close();
        }
        if (!empty($logic_filter_students_by_group)) {
            $group_ids = array_keys($logic_filter_students_by_group);
            $group_placeholders = implode(',', array_fill(0, count($group_ids), '?'));
            $group_types = str_repeat('i', count($group_ids));
            $stmt_logic_members = $conn->prepare("SELECT group_id, uid FROM group_members WHERE group_id IN ({$group_placeholders})");
            if ($stmt_logic_members) {
                $stmt_logic_members->bind_param($group_types, ...$group_ids);
                $stmt_logic_members->execute();
                $result_logic_members = $stmt_logic_members->get_result();
                while ($member_row = $result_logic_members->fetch_assoc()) {
                    $logic_filter_students_by_group[(string)$member_row['group_id']][] = (string)$member_row['uid'];
                }
                $stmt_logic_members->close();
            }
        }
        $student_feature_global_averages = [];
        $student_feature_global_distributions = array_fill_keys(array_keys($student_feature_columns_for_filter), []);
        if (student_feature_table_exists($conn)) {
            $uid_average_selects = ['tf.UID'];
            foreach ($student_feature_columns_for_filter as $column => $_label) {
                $uid_average_selects[] = "AVG(tf.`{$column}`) AS uid_avg_{$column}";
            }
            $uid_feature_distribution_sql = "SELECT uid_feat.*
                FROM (
                    SELECT " . implode(", ", $uid_average_selects) . "
                    FROM test_featurevalue tf
                    JOIN students s ON tf.UID = s.uid
                    JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                    WHERE ct.TID = ?
                    GROUP BY tf.UID
                ) uid_feat
                ORDER BY uid_feat.UID";
            $uid_feature_distribution_stmt = $conn->prepare($uid_feature_distribution_sql);
            if ($uid_feature_distribution_stmt) {
                $uid_feature_distribution_stmt->bind_param("s", $teacher_id);
                $uid_feature_distribution_stmt->execute();
                $uid_feature_distribution_result = $uid_feature_distribution_stmt->get_result();
                while ($uid_feature_row = $uid_feature_distribution_result->fetch_assoc()) {
                    foreach ($student_feature_columns_for_filter as $column => $_label) {
                        $value = $uid_feature_row["uid_avg_{$column}"] ?? null;
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $student_feature_global_distributions[$column][] = (float)$value;
                    }
                }
                $uid_feature_distribution_stmt->close();
                foreach ($student_feature_columns_for_filter as $column => $_label) {
                    $values = $student_feature_global_distributions[$column];
                    $student_feature_global_averages["avg_{$column}"] = count($values) > 0
                        ? array_sum($values) / count($values)
                        : null;
                }
            }
        }
        $teacher_page_title = '学生グループ作成';
        include __DIR__ . '/teacher-menu.php';
    ?>
    <div class="main-content">
        <main class="page-content teacher-form-page">
            <section class="card teacher-form-card teacher-wide-card">


            <div class="content-class">
            <h2>学生グループ作成</h2>
                <form id="search-form" method="GET">
                    <div class="filter-form-title">絞り込みフォーム</div>
                    <label class="uid-label">UID:</label>
                    <div id="uid-logic-filter-panel" class="logic-filter-panel">
                        <strong>論理式でUIDを絞り込み</strong>
                        <div class="logic-filter-toolbar">
                            <button type="button" data-add-uid-filter="condition">対象を追加</button>
                            <button type="button" data-add-uid-filter="and">AND</button>
                            <button type="button" data-add-uid-filter="or">OR</button>
                            <button type="button" data-add-uid-filter="not">NOT</button>
                            <button type="button" data-add-uid-filter="open">(</button>
                            <button type="button" data-add-uid-filter="close">)</button>
                            <label>追加位置
                                <select id="uid-logic-filter-insert-position" class="logic-filter-insert-position"></select>
                            </label>
                        </div>
                        <div id="uid-logic-filter-builder" class="logic-filter-builder"></div>
                        <div class="logic-filter-actions">
                            <button type="button" id="apply-uid-logic-filter">絞り込みを適用</button>
                            <button type="button" id="reset-uid-logic-filter">リセット</button>
                            <button type="button" id="trim-uid-logic-filter" class="logic-filter-trim">追加位置から後ろを削除</button>
                            <button type="button" id="clear-uid-logic-filter" class="logic-filter-clear">式を空にする</button>
                            <span id="uid-logic-filter-summary" class="logic-filter-summary">すべての学習者を対象にしています。</span>
                        </div>
                    </div>
                    <div id="uid-checkbox-list" class="checkbox-section">
                        <div class="checkbox-controls">
                            <label><input type="checkbox" class="select-all" checked> 全ての表示中学習者を 選択 / 解除</label>
                        </div>
                        <div class="checkbox-list">
                        <?php
                        $feature_select_sql = student_feature_average_select_sql($conn);
                        $feature_join_sql = student_feature_average_join_sql($conn);
                        $feature_pair_join_sql = student_feature_pair_average_join_sql($conn);
                        $sql_getuid = "SELECT
                                        s.uid,
                                        s.Name,
                                        s.ClassID,
                                        c.ClassName,
                                        COALESCE(acc.accuracy, 0) AS accuracy,
                                        COALESCE(acc.total_answers, 0) AS total_answers,
                                        COALESCE(hes.hesitation_rate, 0) AS hesitation_rate,
                                        {$feature_select_sql}
                                    FROM students s
                                    JOIN classes c ON s.ClassID = c.ClassID
                                    LEFT JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                                    LEFT JOIN (
                                        SELECT
                                            uid,
                                            (SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS accuracy,
                                            COUNT(*) AS total_answers
                                        FROM linedata
                                        GROUP BY uid
                                    ) acc ON s.uid = acc.uid
                                    LEFT JOIN (
                                        SELECT
                                            uid,
                                            (SUM(CASE WHEN Understand = 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS hesitation_rate
                                        FROM temporary_results
                                        GROUP BY uid
                                    ) hes ON s.uid = hes.uid
                                    {$feature_join_sql}
                                    WHERE ct.TID = ?
                                    ORDER BY c.ClassName, s.uid";
                        $stmt = $conn->prepare($sql_getuid);
                        if (!$stmt) {
                            echo "<p class='error'>UID一覧の取得に失敗しました。</p>";
                        } else {
                        $stmt->bind_param("i", $_SESSION['MemberID']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $current_class_id = null;
                        while ($row = $result->fetch_assoc()) {
                            if ($current_class_id !== (string)$row['ClassID']) {
                                $current_class_id = (string)$row['ClassID'];
                                $safe_class_id = htmlspecialchars($row['ClassID'], ENT_QUOTES, 'UTF-8');
                                $safe_class_name = htmlspecialchars($row['ClassName'], ENT_QUOTES, 'UTF-8');
                                echo "<div class='class-group-header' data-class-id='{$safe_class_id}'>
                                        <h5>{$safe_class_name}</h5>
                                        <label><input type='checkbox' class='select-all-class' data-class-id='{$safe_class_id}' checked> このグループ(クラス)を全て選択 / 解除</label>
                                    </div>";
                            }
                            $safe_class_id = htmlspecialchars($row['ClassID'], ENT_QUOTES, 'UTF-8');
                            $uid = htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8');
                            $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
                            $student_tooltip = render_student_tooltip(
                                $row,
                                    '正解率:',
                                    '迷い率:',
                                    '解答数:'
                            );
                            echo "<label class='checkbox-item uid-filter-item student-choice uid-filter-choice' data-class-id='{$safe_class_id}'>
                                        <input type='checkbox' class='uid-checkbox' name='uid[]' value='{$uid}' checked>
                                        <span class='student-name'><span class='label-text'>名前:</span> {$name}</span>
                                        <button type='button' class='student-info-button' aria-label='学習者ごとの特徴量の平均を表示'>ⓘ</button>
                                        {$student_tooltip}
                                    </label>";
                        }
                        $result->free();
                        $stmt->close();
                        }
                        ?>
                        </div>
                    </div>
                    <label class="uid-label">WID:</label>
                    <div class="button-container" style="margin-bottom: 10px; display: flex; gap: 10px;">
                        <button type="button" id="select-all-wid-btn">すべて選択</button>
                        <button type="button" id="deselect-all-wid-btn">すべて解除</button>
                    </div>
                    <div id="wid-checkbox-list" class="list-container">
                        <?php
                        $sql_getwid = "SELECT DISTINCT
                                        feat.WID,
                                        qi.Sentence
                                    FROM students s
                                    JOIN classes c ON s.ClassID = c.ClassID
                                    LEFT JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                                    {$feature_pair_join_sql}
                                    LEFT JOIN question_info qi ON feat.WID = qi.WID
                                    WHERE ct.TID = ? AND feat.WID IS NOT NULL
                                    ORDER BY feat.WID";
                        $stmt = $conn->prepare($sql_getwid);
                        if ($stmt) {
                            $stmt->bind_param("i", $_SESSION['MemberID']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                $wid = htmlspecialchars($row['WID'], ENT_QUOTES, 'UTF-8');
                                $sentence = htmlspecialchars($row['Sentence'] ?? '', ENT_QUOTES, 'UTF-8');
                                $wid_label = "WID:{$wid}" . ($sentence !== '' ? " : {$sentence}" : '');
                                echo "<div class='list-item wid-list-item'>
                                        <label class='student-choice'>
                                            <input type='checkbox' class='wid-checkbox' name='wid[]' value='{$wid}' checked>
                                            <span class='student-name'>{$wid_label}</span>
                                        </label>
                                    </div>";
                            }
                            $result->free();
                            $stmt->close();
                        }
                        ?>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            const selectAllWidBtn = document.getElementById('select-all-wid-btn');
                            const deselectAllWidBtn = document.getElementById('deselect-all-wid-btn');
                            const widCheckboxes = document.querySelectorAll('.wid-checkbox');

                            selectAllWidBtn.addEventListener('click', () => {
                                widCheckboxes.forEach(checkbox => checkbox.checked = true);
                            });

                            deselectAllWidBtn.addEventListener('click', () => {
                                widCheckboxes.forEach(checkbox => checkbox.checked = false);
                            });
                        });
                    </script>

                    <fieldset class="basic-filter-fieldset">
                        <legend>基本情報での絞り込み</legend>
                        <div class="basic-filter-grid">
                            <div class="basic-filter-item">
                                <label for="accuracy_min">正解率 (%):</label>
                                <div class="basic-filter-range">
                                    <input type="number" id="accuracy_min" name="accuracy_min" placeholder="最小値">
                                    <input type="number" id="accuracy_max" name="accuracy_max" placeholder="最大値">
                                </div>
                            </div>

                            <div class="basic-filter-item">
                                <label for="hesitation_rate_min">迷い率:</label>
                                <div class="basic-filter-range">
                                    <input type="number" id="hesitation_rate_min" name="hesitation_rate_min" placeholder="最小値">
                                    <input type="number" id="hesitation_rate_max" name="hesitation_rate_max" placeholder="最大値">
                                </div>
                            </div>

                            <div class="basic-filter-item">
                                <label for="total_answers_min">問題解答数:</label>
                                <div class="basic-filter-range">
                                    <input type="number" id="total_answers_min" name="total_answers_min" placeholder="最小値">
                                    <input type="number" id="total_answers_max" name="total_answers_max" placeholder="最大値">
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="feature-filter-fieldset">
                        <legend>特徴量による絞り込み</legend>
                        <input type="hidden" id="feature-filter-expression" name="feature_filter_expression" value="[]">
                        <div class="feature-filter-help">
                            特徴量を AND・OR・NOT・括弧で組み合わせて学習者を絞り込みます。選択した特徴量の最小値・最大値は、下の設定欄で指定できます。
                        </div>
                        <div class="feature-filter-parts" aria-label="特徴量論理式パーツ">
                            <button type="button" id="add-feature-filter-condition" class="teacher-secondary-button">特徴量を追加</button>
                            <button type="button" id="add-feature-filter-and">AND</button>
                            <button type="button" id="add-feature-filter-or">OR</button>
                            <button type="button" id="add-feature-filter-not">NOT</button>
                            <button type="button" id="add-feature-filter-open">(</button>
                            <button type="button" id="add-feature-filter-close">)</button>
                            <span class="feature-filter-insert-control">
                                <label for="feature-filter-insert-position">追加位置</label>
                                <select id="feature-filter-insert-position"></select>
                            </span>
                        </div>
                        <div class="feature-filter-builder" id="feature-filter-builder"></div>
                        <div class="feature-filter-actions">
                            <button type="button" id="reset-feature-filter-expression">条件をリセット</button>
                            <button type="button" id="trim-feature-filter-expression">追加位置から後ろを削除</button>
                            <button type="button" id="clear-feature-filter-expression">式を空にする</button>
                            <p class="feature-filter-summary" id="feature-filter-summary">特徴量条件は未設定です。</p>
                        </div>
                        <div class="feature-filter-settings-wrap">
                            <h3>選択した特徴量の設定</h3>
                            <div class="feature-filter-settings" id="feature-filter-settings">
                                <p class="feature-filter-empty">論理式に特徴量を追加すると、ここに最小値・最大値の設定欄が表示されます。</p>
                            </div>
                            <div class="feature-global-average-box">
                                <label for="feature-global-average-select">各特徴量の全UIDの平均値・分布</label>
                                <select id="feature-global-average-select"></select>
                                <p id="feature-global-average-value" class="feature-global-average-value">特徴量を選択してください。</p>
                                <div class="feature-global-histogram">
                                    <canvas id="feature-global-histogram-chart" aria-label="各特徴量の全UID平均値の分布"></canvas>
                                </div>
                                <p id="feature-global-histogram-summary" class="feature-global-histogram-summary">特徴量を選択すると分布を表示します。</p>
                            </div>
                        </div>
                    </fieldset>

                    <button type="button" id="search-button">検索</button>
                </form>
                <form action="submit-student-group.php" method="post" class="student-group-create-form">
                    <label for="group_name">グループ名:</label>
                        <input type="text" id="group_name" name="group_name" required>
                        <label>学習者リスト:</label>
                        <ul class="student-list" id="student-list">
                            <!-- PHPで全学習者を取得して初期表示 -->
                            <?php
                            $sql_getstudent = "SELECT
                                            s.uid,
                                            s.Name,
                                            feat.WID,
                                            feat.attempt,
                                            COALESCE(ld.test_id, feat.test_id) AS test_id,
                                            COALESCE(acc.accuracy, 0) AS accuracy,
                                            COALESCE(acc.total_answers, 0) AS total_answers,
                                            COALESCE(hes.hesitation_rate, 0) AS hesitation_rate,
                                            {$feature_select_sql}
                                        FROM students s
                                        LEFT JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                                        LEFT JOIN (
                                            SELECT
                                                uid,
                                                (SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS accuracy,
                                                COUNT(*) AS total_answers
                                            FROM linedata
                                            GROUP BY uid
                                        ) acc ON s.uid = acc.uid
                                        LEFT JOIN (
                                            SELECT
                                                uid,
                                                (SUM(CASE WHEN Understand = 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) AS hesitation_rate
                                            FROM temporary_results
                                            GROUP BY uid
                                        ) hes ON s.uid = hes.uid
                                        {$feature_pair_join_sql}
                                        LEFT JOIN linedata ld ON s.uid = ld.UID AND feat.WID = ld.WID AND feat.attempt = ld.attempt
                                        WHERE ct.TID = ? AND feat.WID IS NOT NULL
                                        ORDER BY s.uid, feat.WID, feat.attempt;

                            ";
                            $stmt = $conn->prepare($sql_getstudent);
                            $stmt->bind_param("i", $_SESSION['MemberID']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                $uid = htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8');
                                $wid = htmlspecialchars($row['WID'], ENT_QUOTES, 'UTF-8');
                                $attempt = htmlspecialchars($row['attempt'], ENT_QUOTES, 'UTF-8');
                                $test_id = htmlspecialchars($row['test_id'] ?? '', ENT_QUOTES, 'UTF-8');
                                $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
                                $student_tooltip = render_feature_average_tooltip($row, 'UID/WID/Attemptの特徴量', false);
                                $feature_values = [];
                                foreach ($student_feature_columns_for_filter as $column => $_label) {
                                    $feature_values[$column] = $row["avg_{$column}"] ?? null;
                                }
                                $feature_json = htmlspecialchars(json_encode($feature_values, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                $trajectory_url = "../mousemove/mousemove.php?UID={$uid}&WID={$wid}&test_id={$test_id}&LogID={$attempt}";
                                echo "<li class='student-item student-pair-item' data-uid='{$uid}' data-wid='{$wid}' data-attempt='{$attempt}' data-features='{$feature_json}'>
                                        <label class='student-choice click-tooltip-choice'>
                                            <input type='checkbox' name='students[]' value='{$uid}'>
                                            <p class='student-detail student-name'><span class='label'>UID:</span> {$uid}</p>
                                            <p class='student-detail'><span class='label'>WID:</span> {$wid}</p>
                                            <p class='student-detail'><span class='label'>Attempt:</span> {$attempt}</p>
                                        <button type='button' class='student-info-button' aria-label='UID/WID/Attemptの特徴量を表示'>ⓘ</button>
                                            {$student_tooltip}
                                        </label>
                                        <a href='{$trajectory_url}' target='_blank' class='student-trajectory-link'>軌跡再現</a>
                                    </li>";
                            }
                            $result->free();
                            ?>
                        </ul>
                    <button type="button" id="show-student-feature-averages" class="teacher-secondary-button">学習者ごとの選択した問題における各特徴量の平均表示</button>
                    <div id="student-feature-average-list" class="student-feature-average-list"></div>
                    <button type="submit">グループを作成</button>
                </form>
            </div>
            </section>
            <section class="card teacher-form-card teacher-wide-card">
            <div class="content-class">
            <h2>学習者の所属グループ(クラス)変更</h2>
                <p>学習者グループ作成とは別の処理として、学習者が所属するグループ(クラス)を変更します。</p>
                <?php
                    $assigned_classes_for_move = [];
                    $stmt_classes_for_move = $conn->prepare(
                        "SELECT c.ClassID, c.ClassName
                         FROM classteacher ct
                         JOIN classes c ON ct.ClassID = c.ClassID
                         WHERE ct.TID = ?
                         ORDER BY c.ClassName"
                    );
                    if ($stmt_classes_for_move) {
                        $stmt_classes_for_move->bind_param("s", $teacher_id);
                        $stmt_classes_for_move->execute();
                        $result_classes_for_move = $stmt_classes_for_move->get_result();
                        while ($class_for_move = $result_classes_for_move->fetch_assoc()) {
                            $assigned_classes_for_move[] = $class_for_move;
                        }
                        $stmt_classes_for_move->close();
                    }

                    $assigned_students_for_move = [];
                    $stmt_students_for_move = $conn->prepare(
                        "SELECT s.uid, s.Name, s.ClassID, c.ClassName
                         FROM students s
                         JOIN classes c ON s.ClassID = c.ClassID
                         JOIN classteacher ct ON s.ClassID = ct.ClassID
                         WHERE ct.TID = ?
                         ORDER BY c.ClassName, s.uid"
                    );
                    if ($stmt_students_for_move) {
                        $stmt_students_for_move->bind_param("s", $teacher_id);
                        $stmt_students_for_move->execute();
                        $result_students_for_move = $stmt_students_for_move->get_result();
                        while ($student_for_move = $result_students_for_move->fetch_assoc()) {
                            $assigned_students_for_move[] = $student_for_move;
                        }
                        $stmt_students_for_move->close();
                    }
                ?>
                <?php if (empty($assigned_classes_for_move)): ?>
                    <p>担当グループ(クラス)が登録されていません。先に<a href="register-classteacher.php">担当グループ(クラス)登録</a>を行ってください。</p>
                <?php elseif (empty($assigned_students_for_move)): ?>
                    <p>担当グループ(クラス)内に変更対象の学習者がいません。</p>
                <?php else: ?>
                    <form action="submit-update-student-class.php" method="post">
                        <label for="move_student_uid">変更する学習者</label>
                        <select id="move_student_uid" name="student_uid" required>
                            <?php foreach ($assigned_students_for_move as $student_for_move): ?>
                                <option value="<?= htmlspecialchars($student_for_move['uid'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($student_for_move['Name'], ENT_QUOTES, 'UTF-8') ?>
                                    (UID: <?= htmlspecialchars($student_for_move['uid'], ENT_QUOTES, 'UTF-8') ?> / 現在: <?= htmlspecialchars($student_for_move['ClassName'], ENT_QUOTES, 'UTF-8') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <br><br>
                        <label for="move_class_id">変更先グループ(クラス)</label>
                        <select id="move_class_id" name="class_id" required>
                            <?php foreach ($assigned_classes_for_move as $class_for_move): ?>
                                <option value="<?= htmlspecialchars($class_for_move['ClassID'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($class_for_move['ClassName'], ENT_QUOTES, 'UTF-8') ?>
                                    (ID: <?= htmlspecialchars($class_for_move['ClassID'], ENT_QUOTES, 'UTF-8') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <br><br>
                        <button type="submit">所属グループ(クラス)を変更</button>
                    </form>
                <?php endif; ?>
            </div>
            </section>
            <section class="card teacher-form-card teacher-wide-card">
            <div class = "content-class">
            <h2>現在のグループ</h2>
            <ul class="group-list">
                <?php
                    // 迴ｾ蝨ｨ縺ｮ繧ｰ繝ｫ繝ｼ繝励ｒ蜿門ｾ励＠縺ｦ陦ｨ遉ｺ
                    $group_result = $conn->query("SELECT group_id, group_name FROM `groups` WHERE TID = '{$_SESSION['MemberID']}'");
                    while ($group = $group_result->fetch_assoc()) {
                        echo "<li>";
                        echo "<strong>{$group['group_name']}</strong>";

                        // 繝｡繝ｳ繝舌・繝ｪ繧ｹ繝医・蜿門ｾ・
                        $member_result = $conn->query("SELECT students.Name FROM group_members JOIN students ON group_members.uid = students.uid WHERE group_members.group_id = {$group['group_id']}");
                        echo "<ul>";
                        while ($member = $member_result->fetch_assoc()) {
                            echo "<li>{$member['Name']}</li>";
                        }
                        echo "</ul>";
                        $member_result->free();

                        // 蜑企勁繝懊ち繝ｳ繧定｡ｨ遉ｺ
                        echo "<form action='delete-student-group.php' method='post' style='display:inline;'>
                                <input type='hidden' name='group_id' value='{$group['group_id']}'>
                                <button type='submit' onclick='return confirm(\"このグループを削除してよろしいですか？\");'>削除</button>
                              </form>";
                        echo "</li>";
                    }
                    $group_result->free();
                    $conn->close();
                ?>
            </ul>
            </div>
            </section>
        </main>
    </div>
    <script>
        window.studentGroupFeatureColumns = <?= json_encode(feature_display_labels($student_feature_columns_for_filter), JSON_UNESCAPED_UNICODE) ?>;
        window.studentGroupFeatureDisplayMeta = <?= json_encode(feature_display_metadata(array_keys($student_feature_columns_for_filter)), JSON_UNESCAPED_UNICODE) ?>;
        window.studentGroupLogicFilterGroups = <?= json_encode($logic_filter_groups, JSON_UNESCAPED_UNICODE) ?>;
        window.studentGroupLogicFilterStudentsByGroup = <?= json_encode((object)$logic_filter_students_by_group, JSON_UNESCAPED_UNICODE) ?>;
        window.studentGroupFeatureGlobalAverages = <?= json_encode(array_reduce(array_keys($student_feature_columns_for_filter), function ($carry, $column) use ($student_feature_global_averages) {
            $carry[$column] = $student_feature_global_averages["avg_{$column}"] ?? null;
            return $carry;
        }, []), JSON_UNESCAPED_UNICODE) ?>;
        window.studentGroupFeatureGlobalDistributions = <?= json_encode($student_feature_global_distributions, JSON_UNESCAPED_UNICODE) ?>;
        window.studentGroupFeatureFilterPlaceholders = {
            min: '最小値',
            max: '最大値'
        };
    </script>
    <script src="search_studentlist.js?v=<?= filemtime(__DIR__ . '/search_studentlist.js') ?>"></script>
</body>
</html>

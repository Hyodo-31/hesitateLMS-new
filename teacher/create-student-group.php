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
        $student_filter_rows = [];
        $student_filter_wids = [];
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
        $student_histogram_feature_pairs = [];
        $student_histogram_metric_attempts = [];
        if (student_feature_table_exists($conn)) {
            $pair_average_selects = ['tf.UID', 'tf.WID'];
            foreach ($student_feature_columns_for_filter as $column => $_label) {
                $pair_average_selects[] = "AVG(tf.`{$column}`) AS pair_avg_{$column}";
                $pair_average_selects[] = "COUNT(tf.`{$column}`) AS pair_count_{$column}";
            }
            $pair_feature_sql = "SELECT " . implode(", ", $pair_average_selects) . "
                FROM test_featurevalue tf
                JOIN students s ON tf.UID = s.uid
                JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                WHERE ct.TID = ?
                GROUP BY tf.UID, tf.WID
                ORDER BY tf.UID, tf.WID";
            $pair_feature_stmt = $conn->prepare($pair_feature_sql);
            if ($pair_feature_stmt) {
                $pair_feature_stmt->bind_param("s", $teacher_id);
                $pair_feature_stmt->execute();
                $pair_feature_result = $pair_feature_stmt->get_result();
                while ($pair_feature_row = $pair_feature_result->fetch_assoc()) {
                    $feature_values = [];
                    $feature_counts = [];
                    foreach ($student_feature_columns_for_filter as $column => $_label) {
                        $value = $pair_feature_row["pair_avg_{$column}"] ?? null;
                        $feature_values[$column] = $value === null || $value === '' ? null : (float)$value;
                        $feature_counts[$column] = (int)($pair_feature_row["pair_count_{$column}"] ?? 0);
                    }
                    $student_histogram_feature_pairs[] = [
                        'uid' => (string)$pair_feature_row['UID'],
                        'wid' => (string)$pair_feature_row['WID'],
                        'features' => $feature_values,
                        'featureCounts' => $feature_counts,
                    ];
                }
                $pair_feature_stmt->close();
            }
        }

        $metric_attempt_sql = "SELECT
                l.UID,
                l.WID,
                l.attempt,
                l.TF,
                latest_hesitation.Understand
            FROM linedata l
            JOIN students s ON l.UID = s.uid
            JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
            LEFT JOIN (
                SELECT tr.UID, tr.WID, tr.attempt, tr.teacher_id, tr.Understand
                FROM temporary_results tr
                JOIN (
                    SELECT UID, WID, attempt, teacher_id, MAX(id) AS latest_id
                    FROM temporary_results
                    WHERE teacher_id = ?
                    GROUP BY UID, WID, attempt, teacher_id
                ) latest ON tr.id = latest.latest_id
            ) latest_hesitation
                ON l.UID = latest_hesitation.UID
                AND l.WID = latest_hesitation.WID
                AND l.attempt = latest_hesitation.attempt
                AND latest_hesitation.teacher_id = ct.TID
            WHERE ct.TID = ?
            ORDER BY l.UID, l.WID, l.attempt";
        $metric_attempt_stmt = $conn->prepare($metric_attempt_sql);
        if ($metric_attempt_stmt) {
            $metric_attempt_stmt->bind_param("ss", $teacher_id, $teacher_id);
            $metric_attempt_stmt->execute();
            $metric_attempt_result = $metric_attempt_stmt->get_result();
            while ($metric_attempt_row = $metric_attempt_result->fetch_assoc()) {
                $student_histogram_metric_attempts[] = [
                    'uid' => (string)$metric_attempt_row['UID'],
                    'wid' => (string)$metric_attempt_row['WID'],
                    'correctness' => $metric_attempt_row['TF'] === null ? null : (int)$metric_attempt_row['TF'],
                    'hesitation' => $metric_attempt_row['Understand'] === null ? null : (int)$metric_attempt_row['Understand'],
                ];
            }
            $metric_attempt_stmt->close();
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
                    <section class="filter-conditions-accordion" aria-label="チェックボックスでの絞り込み">
                        <button type="button" class="filter-conditions-toggle" id="filter-conditions-toggle" aria-expanded="false" aria-controls="filter-conditions-panel">
                            <span>
                                <span class="filter-conditions-title">チェックボックスでの絞り込み</span>
                                <span class="filter-conditions-description">UID・WID・迷いの有無・特徴量などをまとめて設定できます。</span>
                            </span>
                            <span class="filter-conditions-icon" aria-hidden="true"></span>
                        </button>
                        <div class="filter-conditions-panel" id="filter-conditions-panel" hidden>
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
                            $student_filter_rows[] = $row;
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
                            echo "<label class='checkbox-item uid-filter-item student-choice uid-filter-choice' data-class-id='{$safe_class_id}' data-student-name='{$name}'>
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
                                $student_filter_wids[] = $row;
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
                    <fieldset class="basic-filter-fieldset">
                        <legend>UID/WID選択結果への追加条件</legend>
                        <div class="basic-filter-grid">
                            <div class="basic-filter-item">
                                <label for="hesitation_filter">迷いの有無:</label>
                                <select id="hesitation_filter" name="hesitation_filter">
                                    <option value="">すべて</option>
                                    <option value="not_hesitated">迷い無し</option>
                                    <option value="hesitated">迷い有り</option>
                                </select>
                            </div>
                            <div class="basic-filter-item">
                                <label for="correctness_filter">正誤:</label>
                                <select id="correctness_filter" name="correctness_filter">
                                    <option value="">すべて</option>
                                    <option value="correct">正解</option>
                                    <option value="incorrect">不正解</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

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
                        </div>
                    </fieldset>

                        </div>
                    </section>
                </form>

                    <section class="filter-conditions-accordion histogram-filter-accordion" aria-label="ヒストグラムでの絞り込み">
                        <button type="button" class="filter-conditions-toggle" id="histogram-conditions-toggle" aria-expanded="false" aria-controls="histogram-conditions-panel">
                            <span>
                                <span class="filter-conditions-title">ヒストグラムでの絞り込み</span>
                                <span class="filter-conditions-description">専用のUID・WIDと縦棒の論理式からグループ候補を作成します。</span>
                            </span>
                            <span class="filter-conditions-icon" aria-hidden="true"></span>
                        </button>
                        <div class="filter-conditions-panel" id="histogram-conditions-panel" hidden>
                    <section class="histogram-dashboard" aria-labelledby="histogram-dashboard-title">
                        <div class="histogram-dashboard-heading">
                            <div>
                                <h3 id="histogram-dashboard-title">ヒストグラムでの絞り込み</h3>
                                <p>特徴量・正答率・迷い率の散らばりを、UIDまたはWID単位で確認できます。</p>
                            </div>
                            <details class="histogram-axis-details">
                                <summary>軸の自動調整について</summary>
                                <p>横軸は表示対象の最小値・最大値、四分位範囲、件数から階級幅を毎回計算します。縦軸は通常すべての度数を表示し、1本だけ極端に高い場合は他の棒に合わせて上限を調整し、突出棒に実際の度数を表示します。</p>
                            </details>
                        </div>

                        <div class="histogram-source-selectors">
                            <section class="histogram-source-panel" aria-labelledby="histogram-uid-source-title">
                                <h4 id="histogram-uid-source-title">ヒストグラム対象UID</h4>
                                <div id="histogram-uid-logic-filter-panel" class="logic-filter-panel">
                                    <strong>論理式で対象UIDを選択</strong>
                                    <div class="logic-filter-toolbar">
                                        <button type="button" data-add-histogram-uid-filter="condition">対象を追加</button>
                                        <button type="button" data-add-histogram-uid-filter="and">AND</button>
                                        <button type="button" data-add-histogram-uid-filter="or">OR</button>
                                        <button type="button" data-add-histogram-uid-filter="not">NOT</button>
                                        <button type="button" data-add-histogram-uid-filter="open">(</button>
                                        <button type="button" data-add-histogram-uid-filter="close">)</button>
                                        <label>追加位置
                                            <select id="histogram-uid-logic-filter-insert-position" class="logic-filter-insert-position"></select>
                                        </label>
                                    </div>
                                    <div id="histogram-uid-logic-filter-builder" class="logic-filter-builder"></div>
                                    <div class="logic-filter-actions">
                                        <button type="button" id="reset-histogram-uid-logic-filter">リセット</button>
                                        <button type="button" id="trim-histogram-uid-logic-filter" class="logic-filter-trim">追加位置から後ろを削除</button>
                                        <button type="button" id="clear-histogram-uid-logic-filter" class="logic-filter-clear">式を空にする</button>
                                        <span id="histogram-uid-logic-filter-summary" class="logic-filter-summary">すべての学習者を対象にしています。</span>
                                    </div>
                                </div>
                                <div id="histogram-uid-checkbox-list" class="checkbox-section histogram-checkbox-section">
                                    <div class="checkbox-controls">
                                        <label><input type="checkbox" class="histogram-select-all" checked> 全ての表示中学習者を 選択 / 解除</label>
                                    </div>
                                    <div class="checkbox-list">
                                    <?php
                                    $histogram_current_class_id = null;
                                    foreach ($student_filter_rows as $row) {
                                        if ($histogram_current_class_id !== (string)$row['ClassID']) {
                                            $histogram_current_class_id = (string)$row['ClassID'];
                                            $safe_class_id = htmlspecialchars($row['ClassID'], ENT_QUOTES, 'UTF-8');
                                            $safe_class_name = htmlspecialchars($row['ClassName'], ENT_QUOTES, 'UTF-8');
                                            echo "<div class='class-group-header' data-class-id='{$safe_class_id}'>
                                                    <h5>{$safe_class_name}</h5>
                                                    <label><input type='checkbox' class='histogram-select-all-class' data-class-id='{$safe_class_id}' checked> このグループ(クラス)を全て選択 / 解除</label>
                                                </div>";
                                        }
                                        $safe_class_id = htmlspecialchars($row['ClassID'], ENT_QUOTES, 'UTF-8');
                                        $uid = htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8');
                                        $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
                                        echo "<label class='checkbox-item histogram-uid-filter-item student-choice' data-class-id='{$safe_class_id}' data-student-name='{$name}'>
                                                <input type='checkbox' class='histogram-uid-checkbox' value='{$uid}' checked>
                                                <span class='student-name'><span class='label-text'>UID:</span> {$uid} / <span class='label-text'>名前:</span> {$name}</span>
                                            </label>";
                                    }
                                    ?>
                                    </div>
                                </div>
                            </section>

                            <section class="histogram-source-panel" aria-labelledby="histogram-wid-source-title">
                                <h4 id="histogram-wid-source-title">ヒストグラム対象WID</h4>
                                <div id="histogram-wid-bar-logic-panel" class="logic-filter-panel histogram-bar-logic-panel">
                                    <strong>選択したWID縦棒の論理式</strong>
                                    <p class="histogram-logic-help">縦棒を条件としてWID集合を演算し、下のチェックボックスへ反映します。式が空の場合はORで結合します。チェック結果は「検索」を押した時点でヒストグラムと特徴量平均へ反映されます。</p>
                                    <div class="logic-filter-toolbar">
                                        <button type="button" data-add-histogram-bar-logic="wid-condition">縦棒を追加</button>
                                        <button type="button" data-add-histogram-bar-logic="wid-and">AND</button>
                                        <button type="button" data-add-histogram-bar-logic="wid-or">OR</button>
                                        <button type="button" data-add-histogram-bar-logic="wid-not">NOT</button>
                                        <button type="button" data-add-histogram-bar-logic="wid-open">(</button>
                                        <button type="button" data-add-histogram-bar-logic="wid-close">)</button>
                                        <label>追加位置
                                            <select id="histogram-wid-bar-logic-insert-position" class="logic-filter-insert-position"></select>
                                        </label>
                                    </div>
                                    <div id="histogram-wid-bar-logic-builder" class="logic-filter-builder"></div>
                                    <div class="logic-filter-actions">
                                        <button type="button" id="clear-histogram-wid-bar-logic" class="logic-filter-clear">式を空にする</button>
                                        <span id="histogram-wid-bar-logic-summary" class="logic-filter-summary">WIDの縦棒は選択されていません。</span>
                                    </div>
                                    <div class="histogram-saved-wid-bars">
                                        <div class="histogram-saved-wid-bars-heading">
                                            <strong>保存したWID縦棒</strong>
                                            <button type="button" id="clear-saved-histogram-wid-bars" class="logic-filter-clear">保存情報をすべて削除</button>
                                        </div>
                                        <div id="histogram-saved-wid-bars-list" class="histogram-saved-wid-bars-list" aria-live="polite">
                                            <span class="histogram-saved-wid-bars-empty">保存したWID縦棒はありません。</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="button-container histogram-wid-buttons">
                                    <button type="button" id="histogram-select-all-wid-btn">すべて選択</button>
                                    <button type="button" id="histogram-deselect-all-wid-btn">すべて解除</button>
                                </div>
                                <div id="histogram-wid-checkbox-list" class="list-container histogram-checkbox-section">
                                <?php foreach ($student_filter_wids as $row): ?>
                                    <?php
                                        $histogram_wid = htmlspecialchars($row['WID'], ENT_QUOTES, 'UTF-8');
                                        $histogram_sentence = htmlspecialchars($row['Sentence'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $histogram_wid_label = "WID:{$histogram_wid}" . ($histogram_sentence !== '' ? " : {$histogram_sentence}" : '');
                                    ?>
                                    <div class="list-item histogram-wid-list-item">
                                        <label class="student-choice">
                                            <input type="checkbox" class="histogram-wid-checkbox" value="<?= $histogram_wid ?>" checked>
                                            <span class="student-name"><?= $histogram_wid_label ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <div class="histogram-wid-apply-actions">
                                    <button type="button" id="apply-histogram-wid-filter">検索</button>
                                    <span id="histogram-wid-filter-apply-summary" class="histogram-wid-filter-apply-summary" aria-live="polite">現在のWID選択をヒストグラムへ反映しています。</span>
                                </div>
                            </section>
                        </div>

                        <div id="histogram-uid-bar-logic-panel" class="logic-filter-panel histogram-bar-logic-panel">
                            <strong>選択したUID縦棒の論理式</strong>
                            <p class="histogram-logic-help">UID特徴量・正答率・迷い率の縦棒を横断して集合演算します。式が空の場合はORで結合します。</p>
                            <div class="logic-filter-toolbar">
                                <button type="button" data-add-histogram-bar-logic="uid-condition">縦棒を追加</button>
                                <button type="button" data-add-histogram-bar-logic="uid-and">AND</button>
                                <button type="button" data-add-histogram-bar-logic="uid-or">OR</button>
                                <button type="button" data-add-histogram-bar-logic="uid-not">NOT</button>
                                <button type="button" data-add-histogram-bar-logic="uid-open">(</button>
                                <button type="button" data-add-histogram-bar-logic="uid-close">)</button>
                                <label>追加位置
                                    <select id="histogram-uid-bar-logic-insert-position" class="logic-filter-insert-position"></select>
                                </label>
                            </div>
                            <div id="histogram-uid-bar-logic-builder" class="logic-filter-builder"></div>
                            <div class="logic-filter-actions">
                                <button type="button" id="reset-histogram-uid-bar-logic">OR式に戻す</button>
                                <button type="button" id="clear-histogram-uid-bar-logic" class="logic-filter-clear">式を空にする</button>
                                <span id="histogram-uid-bar-logic-summary" class="logic-filter-summary">UIDの縦棒は選択されていません。</span>
                            </div>
                        </div>

                        <article class="histogram-card" aria-labelledby="uid-feature-histogram-title">
                            <h4 id="uid-feature-histogram-title">UIDごとの特徴量分布</h4>
                            <p class="histogram-card-description">各UIDについて、対象WIDにおける特徴量平均を比較します。縦棒をクリックすると、その範囲のUIDをグループ候補として選択できます。</p>
                            <div class="histogram-controls">
                                <label>特徴量
                                    <select id="uid-feature-histogram-feature"></select>
                                </label>
                                <label>対象UID
                                    <select id="uid-feature-histogram-uid-scope">
                                        <option value="all">全UID</option>
                                        <option value="checked">選択UID</option>
                                    </select>
                                </label>
                                <label>対象WID
                                    <select id="uid-feature-histogram-wid-scope">
                                        <option value="all">全WID</option>
                                        <option value="checked">選択WID</option>
                                    </select>
                                </label>
                            </div>
                            <div class="histogram-chart-wrap">
                                <canvas id="uid-feature-histogram-chart" aria-label="UIDごとの特徴量分布"></canvas>
                            </div>
                            <p id="uid-feature-histogram-summary" class="histogram-summary" aria-live="polite"></p>
                        </article>

                        <article class="histogram-card" aria-labelledby="wid-feature-histogram-title">
                            <h4 id="wid-feature-histogram-title">WIDごとの特徴量分布</h4>
                            <p class="histogram-card-description">各WIDについて、対象UIDにおける特徴量平均を比較します。縦棒をクリックすると、その範囲のWIDが専用チェック欄へ反映されます。</p>
                            <div class="histogram-controls">
                                <label>特徴量
                                    <select id="wid-feature-histogram-feature"></select>
                                </label>
                                <label>対象UID
                                    <select id="wid-feature-histogram-uid-scope">
                                        <option value="all">全UID</option>
                                        <option value="checked">選択UID</option>
                                    </select>
                                </label>
                                <label>対象WID
                                    <select id="wid-feature-histogram-wid-scope">
                                        <option value="all">全WID</option>
                                        <option value="checked">選択WID</option>
                                    </select>
                                </label>
                            </div>
                            <div class="histogram-chart-wrap">
                                <canvas id="wid-feature-histogram-chart" aria-label="WIDごとの特徴量分布"></canvas>
                            </div>
                            <p id="wid-feature-histogram-summary" class="histogram-summary" aria-live="polite"></p>
                        </article>

                        <article class="histogram-card" aria-labelledby="metric-histogram-title">
                            <h4 id="metric-histogram-title">正答率・迷い率の分布</h4>
                            <p class="histogram-card-description">ヒストグラム専用のUIDとWIDを対象に、UIDまたはWIDごとの率を比較します。縦棒をクリックすると表示単位に応じた集合を選択できます。</p>
                            <div class="histogram-controls histogram-controls-metric">
                                <label>指標
                                    <select id="metric-histogram-metric">
                                        <option value="hesitation" selected>迷い率</option>
                                        <option value="accuracy">正答率</option>
                                    </select>
                                </label>
                                <label>表示単位
                                    <select id="metric-histogram-entity">
                                        <option value="uid" selected>UID</option>
                                        <option value="wid">WID</option>
                                    </select>
                                </label>
                                <label>対象UID
                                    <select id="metric-histogram-uid-scope">
                                        <option value="all">全UID</option>
                                        <option value="checked">選択UID</option>
                                    </select>
                                </label>
                                <label>対象WID
                                    <select id="metric-histogram-wid-scope">
                                        <option value="all">全WID</option>
                                        <option value="checked">選択WID</option>
                                    </select>
                                </label>
                            </div>
                            <div class="histogram-chart-wrap">
                                <canvas id="metric-histogram-chart" aria-label="正答率または迷い率の分布"></canvas>
                            </div>
                            <p id="metric-histogram-summary" class="histogram-summary" aria-live="polite"></p>
                        </article>
                    </section>
                        </div>
                    </section>
                <form action="submit-student-group.php" method="post" class="student-group-create-form">
                    <label for="group_name">グループ名:</label>
                        <input type="text" id="group_name" name="group_name" required>
                        <div class="student-result-toolbar">
                            <span class="student-result-title">学習者リスト:</span>
                            <label for="student-list-display-mode" class="student-list-display-mode-label">
                                表示方式
                                <select id="student-list-display-mode">
                                    <option value="filter" selected>絞り込み検索結果</option>
                                    <option value="histogram">ヒストグラム選択結果</option>
                                </select>
                            </label>
                            <label for="filter-feature-average-scope" class="student-list-display-mode-label average-scope-control" data-average-scope-mode="filter">
                                特徴量平均の範囲
                                <select id="filter-feature-average-scope">
                                    <option value="selected" selected>選択した問題</option>
                                    <option value="all">全解答問題</option>
                                </select>
                            </label>
                            <label for="histogram-feature-average-scope" class="student-list-display-mode-label average-scope-control" data-average-scope-mode="histogram" hidden>
                                特徴量平均の範囲
                                <select id="histogram-feature-average-scope">
                                    <option value="selected" selected>選択した問題</option>
                                    <option value="all">全解答問題</option>
                                </select>
                            </label>
                            <p id="histogram-student-list-summary" class="histogram-student-list-summary" aria-live="polite" hidden>選択中の縦棒: 0本 / 対象UID: 0人</p>
                        </div>
                        <ul class="student-list" id="student-list">
                            <li class="student-list-status">学習者を読み込んでいます。</li>
                        </ul>
                    <ul class="student-list histogram-student-list" id="histogram-student-list" aria-label="ヒストグラムで選択した学習者" hidden>
                        <li class="histogram-student-list-empty">UIDを含む縦棒をクリックすると、ここにグループ候補が表示されます。</li>
                    </ul>
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
        window.studentGroupHistogramData = <?= json_encode([
            'featurePairs' => $student_histogram_feature_pairs,
            'metricAttempts' => $student_histogram_metric_attempts,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.studentGroupFeatureFilterPlaceholders = {
            min: '最小値',
            max: '最大値'
        };
    </script>
    <script src="search_studentlist.js?v=<?= filemtime(__DIR__ . '/search_studentlist.js') ?>"></script>
</body>
</html>

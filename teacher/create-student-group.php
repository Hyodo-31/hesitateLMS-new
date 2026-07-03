<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('create-student-group.php_6行目_教師用ダッシュボード') ?></title>
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
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
        $teacher_id = $_SESSION['MemberID'] ?? '';
        $student_feature_columns_for_filter = student_feature_columns();
        $teacher_page_title = translate('create-student-group.php_37行目_学生グループ作成');
        include __DIR__ . '/teacher-menu.php';
    ?>
    <div class="main-content">
        <main class="page-content teacher-form-page">
            <section class="card teacher-form-card teacher-wide-card">


            <div class="content-class">
            <h2><?= translate('create-student-group.php_37行目_学生グループ作成') ?></h2>
                <!-- 検索フォーム -->
                <form id="search-form" method="GET">
                    <!--太文字で大きく中央に表示-->
                    <div class="filter-form-title"><?= translate('create-student-group.php_41行目_絞り込みフォーム') ?></div>
                    <label class="uid-label">UID:</label>
                    <!-- すべて選択 / すべて解除ボタン -->
                    <!--横並びにして間にスペースを入れる-->
                    <div class="button-container" style="margin-bottom: 10px; display: flex; gap: 10px;">
                        <!-- PHPでUIDリストを動的に生成 -->
                        <button type="button" id="select-all-btn"><?= translate('create-student-group.php_46行目_すべて選択') ?></button>
                        <button type="button" id="deselect-all-btn"><?= translate('create-student-group.php_47行目_すべて解除') ?></button>
                    </div>
                    <div id="uid-checkbox-list" class="list-container">
                        <?php
                        $feature_select_sql = student_feature_average_select_sql($conn);
                        $feature_join_sql = student_feature_average_join_sql($conn);
                        $sql_getuid = "SELECT
                                        s.uid,
                                        s.Name,
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
                                    {$feature_join_sql}
                                    WHERE ct.TID = ?";
                        $stmt = $conn->prepare($sql_getuid);
                        $stmt->bind_param("i", $_SESSION['MemberID']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $uid = htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8');
                            $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
                            $student_tooltip = render_student_tooltip(
                                $row,
                                translate('create-student-group.php_134行目_正解率:'),
                                translate('create-student-group.php_135行目_迷い率:'),
                                translate('create-student-group.php_136行目_解答数:')
                            );
                            echo "<div class='list-item'>
                                    <label class='student-choice'>
                                        <input type='checkbox' class='uid-checkbox' name='uid[]' value='{$uid}'>
                                        <span class='student-name'><span class='label-text'>" . translate('create-student-group.php_61行目_名前:') . "</span> {$name}</span>
                                        {$student_tooltip}
                                    </label>
                                </div>";
                        }
                        $result->free();
                        ?>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            const selectAllBtn = document.getElementById('select-all-btn');
                            const deselectAllBtn = document.getElementById('deselect-all-btn');
                            const checkboxes = document.querySelectorAll('.uid-checkbox');

                            // すべて選択
                            selectAllBtn.addEventListener('click', () => {
                                checkboxes.forEach(checkbox => checkbox.checked = true);
                            });

                            // すべて解除
                            deselectAllBtn.addEventListener('click', () => {
                                checkboxes.forEach(checkbox => checkbox.checked = false);
                            });
                        });
                    </script>

                    <fieldset class="basic-filter-fieldset">
                        <legend>基本情報での絞り込み</legend>
                        <div class="basic-filter-grid">
                            <div class="basic-filter-item">
                                <label for="accuracy_min"><?= translate('create-student-group.php_85行目_正解率 (%):') ?></label>
                                <div class="basic-filter-range">
                                    <input type="number" id="accuracy_min" name="accuracy_min" placeholder="<?= translate('create-student-group.php_86行目_最小値') ?>">
                                    <input type="number" id="accuracy_max" name="accuracy_max" placeholder="<?= translate('create-student-group.php_87行目_最大値') ?>">
                                </div>
                            </div>

                            <div class="basic-filter-item">
                                <label for="hesitation_rate_min"><?= translate('create-student-group.php_89行目_迷い率:') ?></label>
                                <div class="basic-filter-range">
                                    <input type="number" id="hesitation_rate_min" name="hesitation_rate_min" placeholder="<?= translate('create-student-group.php_90行目_最小値') ?>">
                                    <input type="number" id="hesitation_rate_max" name="hesitation_rate_max" placeholder="<?= translate('create-student-group.php_91行目_最大値') ?>">
                                </div>
                            </div>

                            <div class="basic-filter-item">
                                <label for="total_answers_min"><?= translate('create-student-group.php_94行目_問題解答数:') ?></label>
                                <div class="basic-filter-range">
                                    <input type="number" id="total_answers_min" name="total_answers_min" placeholder="<?= translate('create-student-group.php_95行目_最小値') ?>">
                                    <input type="number" id="total_answers_max" name="total_answers_max" placeholder="<?= translate('create-student-group.php_96行目_最大値') ?>">
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

                    <button type="button" id="search-button"><?= translate('create-student-group.php_99行目_検索') ?></button>
                </form>
                <form action="submit-student-group.php" method="post" class="student-group-create-form">
                    <label for="group_name"><?= translate('create-student-group.php_102行目_グループ名:') ?></label>
                        <input type="text" id="group_name" name="group_name" required>
                        <label><?= translate('create-student-group.php_105行目_学生リスト:') ?></label>
                        <ul class="student-list" id="student-list">
                            <!-- PHPで全学生を取得して初期表示 -->
                            <?php
                            $sql_getstudent = "SELECT
                                            s.uid,
                                            s.Name,
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
                                        {$feature_join_sql}
                                        WHERE ct.TID = ?;

                            ";
                            $stmt = $conn->prepare($sql_getstudent);
                            $stmt->bind_param("i", $_SESSION['MemberID']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                $uid = htmlspecialchars($row['uid'], ENT_QUOTES, 'UTF-8');
                                $name = htmlspecialchars($row['Name'], ENT_QUOTES, 'UTF-8');
                                $student_tooltip = render_student_tooltip(
                                    $row,
                                    translate('create-student-group.php_134行目_正解率:'),
                                    translate('create-student-group.php_135行目_迷い率:'),
                                    translate('create-student-group.php_136行目_解答数:')
                                );
                                echo "<li class='student-item'>
                                        <label class='student-choice'>
                                            <input type='checkbox' name='students[]' value='{$uid}'>
                                            <p class='student-detail student-name'><span class='label'>" . translate('create-student-group.php_133行目_名前:') . "</span> {$name}</p>
                                            {$student_tooltip}
                                        </label>
                                    </li>";
                            }
                            $result->free();
                            ?>
                        </ul>
                    <button type="submit"><?= translate('create-student-group.php_141行目_グループを作成') ?></button>
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
            <h2><?= translate('create-student-group.php_144行目_現在のグループ') ?></h2>
            <ul class="group-list">
                <?php
                    // 現在のグループを取得して表示
                    $group_result = $conn->query("SELECT group_id, group_name FROM `groups` WHERE TID = '{$_SESSION['MemberID']}'");
                    while ($group = $group_result->fetch_assoc()) {
                        echo "<li>";
                        echo "<strong>{$group['group_name']}</strong>";

                        // メンバーリストの取得
                        $member_result = $conn->query("SELECT students.Name FROM group_members JOIN students ON group_members.uid = students.uid WHERE group_members.group_id = {$group['group_id']}");
                        echo "<ul>";
                        while ($member = $member_result->fetch_assoc()) {
                            echo "<li>{$member['Name']}</li>";
                        }
                        echo "</ul>";
                        $member_result->free();

                        // 削除ボタンを表示
                        echo "<form action='delete-student-group.php' method='post' style='display:inline;'>
                                <input type='hidden' name='group_id' value='{$group['group_id']}'>
                                <button type='submit' onclick='return confirm(" . json_encode(translate('create-student-group.php_161行目_このグループを削除してよろしいですか？')) . ");'>" . translate('create-student-group.php_161行目_削除') . "</button>
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
        window.studentGroupFeatureColumns = <?= json_encode($student_feature_columns_for_filter, JSON_UNESCAPED_UNICODE) ?>;
        window.studentGroupFeatureFilterPlaceholders = {
            min: <?= json_encode(translate('create-student-group.php_86行目_最小値'), JSON_UNESCAPED_UNICODE) ?>,
            max: <?= json_encode(translate('create-student-group.php_87行目_最大値'), JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="search_studentlist.js?v=<?= filemtime(__DIR__ . '/search_studentlist.js') ?>"></script>
</body>
</html>

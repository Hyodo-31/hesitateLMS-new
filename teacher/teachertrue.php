<?php
// セッションを開始し、多言語対応とデータベース接続を読み込みます
include '../lang.php';
require "../dbc.php";

// ログイン中の教師IDを取得します
$teacher_id = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;
$teacher_name = "先生"; // デフォルト名

if ($teacher_id) {
    $stmt_teacher = $conn->prepare("SELECT TName FROM teachers WHERE TID = ?");
    if ($stmt_teacher) {
        $stmt_teacher->bind_param("s", $teacher_id);
        $stmt_teacher->execute();
        $result_teacher = $stmt_teacher->get_result();
        if ($row_teacher = $result_teacher->fetch_assoc()) {
            $teacher_name = htmlspecialchars($row_teacher['TName']);
        }
        $stmt_teacher->close();
    }
}

// --- AJAXリクエストの処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = [];

    // 【新規追加】アクション: 担当クラスの全学習者の結果を取得
    try {
        if ($_POST['action'] === 'get_class_results' && isset($_POST['student_ids'])) {
            $student_ids = json_decode($_POST['student_ids']);
            if (!empty($student_ids) && is_array($student_ids)) {
                $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                $types = 's' . str_repeat('s', count($student_ids));
                $params = array_merge([$teacher_id], $student_ids);

                // SQLを修正: classesテーブルをJOINしてクラス名を取得し、ORDER BY句を変更
                $stmt = $conn->prepare(
                    "SELECT 
                        l.UID as student_id, s.Name as student_name, c.ClassName, l.WID, l.Date as date, l.attempt, l.test_id,
                        COALESCE(t.test_name, '（不明なテスト）') as test_name,
                        CASE WHEN l.TF = 1 THEN '正解' ELSE '不正解' END as correctness,
                        CASE tr.Understand WHEN 2 THEN '迷い有り' WHEN 4 THEN '迷い無し' ELSE '未推定' END as hesitation
                     FROM linedata l
                     JOIN students s ON l.UID = s.uid
                     JOIN classes c ON s.ClassID = c.ClassID
                     LEFT JOIN tests t ON l.test_id = t.id
                     LEFT JOIN temporary_results tr ON l.UID = tr.UID AND l.WID = tr.WID AND l.attempt = tr.attempt AND tr.teacher_id = ?
                     WHERE l.UID IN ($placeholders)
                     ORDER BY c.ClassID, s.uid, l.WID, l.attempt"
                );
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) $response[] = $row;
                $stmt->close();
            }
        }
        // 【機能修正】アクション: 指定されたテストの受験対象者全員と、その解答状況を取得
        elseif ($_POST['action'] === 'get_students_for_test' && isset($_POST['test_id'])) {
            $test_id = $_POST['test_id'];
            $assigned_students = [];

            // 1. テストの対象（クラスかグループか）を取得
            $stmt_test = $conn->prepare("SELECT target_type, target_group FROM tests WHERE id = ?");
            $stmt_test->bind_param("i", $test_id);
            $stmt_test->execute();
            $test_info = $stmt_test->get_result()->fetch_assoc();
            $stmt_test->close();

            // 2. 対象に応じて、出題された学習者全員のリストを取得
            if ($test_info) {
                if ($test_info['target_type'] === 'class') {
                    $stmt_assigned = $conn->prepare("SELECT uid, Name FROM students WHERE ClassID = ? ORDER BY uid");
                    $stmt_assigned->bind_param("i", $test_info['target_group']);
                } else { // group
                    $stmt_assigned = $conn->prepare(
                        "SELECT s.uid, s.Name FROM group_members gm JOIN students s ON gm.uid = s.uid WHERE gm.group_id = ? ORDER BY s.uid"
                    );
                    $stmt_assigned->bind_param("i", $test_info['target_group']);
                }
                $stmt_assigned->execute();
                $result_assigned = $stmt_assigned->get_result();
                while ($row = $result_assigned->fetch_assoc()) {
                    $assigned_students[$row['uid']] = ['uid' => $row['uid'], 'Name' => $row['Name'], 'is_unanswered' => true];
                }
                $stmt_assigned->close();
            }

            // 3. 解答済みの学習者リストを取得
            $stmt_answered = $conn->prepare("SELECT DISTINCT UID FROM linedata WHERE test_id = ?");
            $stmt_answered->bind_param("i", $test_id);
            $stmt_answered->execute();
            $result_answered = $stmt_answered->get_result();
            while ($row = $result_answered->fetch_assoc()) {
                if (isset($assigned_students[$row['UID']])) {
                    $assigned_students[$row['UID']]['is_unanswered'] = false;
                }
            }
            $stmt_answered->close();

            $response = array_values($assigned_students);
        }

        // アクション: 指定されたテストに含まれる問題リストを取得
        elseif ($_POST['action'] === 'get_questions_for_test' && isset($_POST['test_id'])) {
            $stmt = $conn->prepare(
                "SELECT tq.WID, qi.Sentence 
                 FROM test_questions tq
                 LEFT JOIN question_info qi ON tq.WID = qi.WID
                 WHERE tq.test_id = ? ORDER BY tq.OID, tq.WID"
            );
            $stmt->bind_param("i", $_POST['test_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) $response[] = $row;
            $stmt->close();
        }

        // アクション: 指定された学習者が解いた問題リストを取得（「学習者ごとの詳細」用）
        elseif ($_POST['action'] === 'get_questions_for_student' && isset($_POST['student_id'])) {
            $stmt = $conn->prepare(
                "SELECT DISTINCT l.WID, q.Sentence 
                 FROM linedata l
                 LEFT JOIN question_info q ON l.WID = q.WID
                 WHERE l.UID = ? ORDER BY l.WID"
            );
            $stmt->bind_param("i", $_POST['student_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) $response[] = $row;
            $stmt->close();
        }

        // 【機能修正】アクション: 未解答者を含めてテスト結果を生成
        elseif ($_POST['action'] === 'get_test_results' && isset($_POST['test_id'], $_POST['student_ids'], $_POST['wids'])) {
            $student_ids = json_decode($_POST['student_ids']);
            $wids = json_decode($_POST['wids']);

            if (!empty($student_ids) && is_array($student_ids) && !empty($wids) && is_array($wids)) {
                $results_map = [];
                $student_names_map = [];

                // 1. 実際の解答データを取得し、マップに格納
                $placeholders_students = implode(',', array_fill(0, count($student_ids), '?'));
                $placeholders_wids = implode(',', array_fill(0, count($wids), '?'));
                $types = 's' . 'i' . str_repeat('i', count($student_ids)) . str_repeat('i', count($wids));
                $params = array_merge([$teacher_id, $_POST['test_id']], $student_ids, $wids);

                $stmt = $conn->prepare(
                    "SELECT l.UID as student_id, s.Name as student_name, l.WID, l.Date as date, l.attempt,
                            CASE WHEN l.TF = 1 THEN '正解' ELSE '不正解' END as correctness,
                            CASE tr.Understand WHEN 2 THEN '迷い有り' WHEN 4 THEN '迷い無し' ELSE '未推定' END as hesitation
                     FROM linedata l
                     JOIN students s ON l.UID = s.uid
                     LEFT JOIN temporary_results tr ON l.UID = tr.UID AND l.WID = tr.WID AND l.attempt = tr.attempt AND tr.teacher_id = ?
                     WHERE l.test_id = ? AND l.UID IN ($placeholders_students) AND l.WID IN ($placeholders_wids)
                     ORDER BY l.UID, l.WID, l.attempt"
                );
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $results_map[$row['student_id'] . '-' . $row['WID']] = $row; // 最後のattemptを格納
                }
                $stmt->close();

                // 2. 選択された全学習者の名前を取得
                $stmt_names = $conn->prepare("SELECT uid, Name FROM students WHERE uid IN ($placeholders_students)");
                $name_types = str_repeat('i', count($student_ids));
                $stmt_names->bind_param($name_types, ...$student_ids);
                $stmt_names->execute();
                $result_names = $stmt_names->get_result();
                while ($row = $result_names->fetch_assoc()) {
                    $student_names_map[$row['uid']] = $row['Name'];
                }
                $stmt_names->close();

                // 3. 全ての組み合わせを生成し、解答がない場合は「未解答」で埋める
                foreach ($student_ids as $sid) {
                    foreach ($wids as $wid) {
                        $key = $sid . '-' . $wid;
                        if (isset($results_map[$key])) {
                            $response[] = $results_map[$key];
                        } else {
                            $response[] = [
                                'student_id' => $sid,
                                'student_name' => $student_names_map[$sid] ?? '不明',
                                'WID' => $wid,
                                'correctness' => '未解答',
                                'hesitation' => '-',
                                'date' => '-',
                                'attempt' => '-'
                            ];
                        }
                    }
                }
            }
        }

        // アクション: 学習者詳細を、選択された問題IDで絞り込んで取得（「学習者ごとの詳細」用）
        // 【ここを修正】: 学習者ごとの詳細結果に、文法項目分析を追加
        // 【ここを修正】: 学習者ごとの詳細結果に、文法項目分析を追加 + 初期表示用の全問題リスト取得を追加
        elseif ($_POST['action'] === 'get_student_details' && isset($_POST['student_id'])) {
            $student_id = $_POST['student_id'];
            $wids = isset($_POST['wids']) ? json_decode($_POST['wids']) : [];
            $summary = ['total_attempts' => 0, 'accuracy' => 'N/A', 'hesitation_rate' => 'N/A'];
            $attempts = [];
            $grammar_stats = [];
            $all_questions = []; // 初期表示用に全問題リストを格納する配列

            // --- 1. 選択された問題に対するサマリーと解答履歴 (widsが指定されている場合のみ) ---
            if (!empty($wids) && is_array($wids)) {
                $placeholders = implode(',', array_fill(0, count($wids), '?'));
                $types = 's' . 's' . str_repeat('i', count($wids));
                $params = array_merge([$teacher_id, $student_id], $wids);

                $stmt_stats = $conn->prepare("SELECT COUNT(l.WID) as selected_total, SUM(CASE WHEN l.TF = 1 THEN 1 ELSE 0 END) as selected_correct, SUM(CASE WHEN tr.Understand = 2 THEN 1 ELSE 0 END) as hesitated_count, SUM(CASE WHEN tr.Understand IN (2, 4) THEN 1 ELSE 0 END) as estimated_count FROM linedata l LEFT JOIN temporary_results tr ON l.UID = tr.UID AND l.WID = tr.WID AND l.attempt = tr.attempt AND tr.teacher_id = ? WHERE l.UID = ? AND l.WID IN ($placeholders)");
                $stmt_stats->bind_param($types, ...$params);
                $stmt_stats->execute();
                $stats_result = $stmt_stats->get_result()->fetch_assoc();
                if ($stats_result) {
                    $summary['total_attempts'] = $stats_result['selected_total'] ?? 0;
                    if ($stats_result['selected_total'] > 0) $summary['accuracy'] = round(($stats_result['selected_correct'] / $stats_result['selected_total']) * 100, 1) . '%';
                    if ($stats_result['estimated_count'] > 0) $summary['hesitation_rate'] = round(($stats_result['hesitated_count'] / $stats_result['estimated_count']) * 100, 1) . '%';
                }
                $stmt_stats->close();

                $stmt_attempts = $conn->prepare("SELECT l.WID, l.Date as date, l.attempt, l.test_id, t.test_name, CASE WHEN l.TF = 1 THEN '正解' ELSE '不正解' END as correctness, CASE tr.Understand WHEN 2 THEN '迷い有り' WHEN 4 THEN '迷い無し' ELSE '未推定' END as hesitation FROM linedata l LEFT JOIN temporary_results tr ON l.UID = tr.UID AND l.WID = tr.WID AND l.attempt = tr.attempt AND tr.teacher_id = ? LEFT JOIN tests t ON l.test_id = t.id WHERE l.UID = ? AND l.WID IN ($placeholders) ORDER BY l.WID, l.attempt");
                $stmt_attempts->bind_param($types, ...$params);
                $stmt_attempts->execute();
                $result_attempts = $stmt_attempts->get_result();
                while ($row = $result_attempts->fetch_assoc()) $attempts[] = $row;
                $stmt_attempts->close();
            } else {
                // widsが指定されていない場合（＝学習者選択時）は、チェックボックス表示用に全問題リストを取得
                $stmt_all_q = $conn->prepare("SELECT DISTINCT l.WID, q.Sentence FROM linedata l LEFT JOIN question_info q ON l.WID = q.WID WHERE l.UID = ? ORDER BY l.WID");
                $stmt_all_q->bind_param("s", $student_id);
                $stmt_all_q->execute();
                $result_all_q = $stmt_all_q->get_result();
                while ($row = $result_all_q->fetch_assoc()) $all_questions[] = $row;
                $stmt_all_q->close();
            }

            // --- 2. 文法項目ごとの分析 (常に学習者の全解答履歴を対象とする) ---
            $gid_map = [];
            $stmt_gid = $conn->prepare("SELECT GID, Item FROM grammar_translations WHERE language = 'ja'");
            $stmt_gid->execute();
            $gid_result = $stmt_gid->get_result();
            while ($row = $gid_result->fetch_assoc()) $gid_map[$row['GID']] = $row['Item'];
            $stmt_gid->close();

            $raw_data_stmt = $conn->prepare(
                "SELECT l.WID, l.TF, l.attempt, qi.grammar, tr.Understand 
         FROM linedata l 
         JOIN question_info qi ON l.WID = qi.WID 
         LEFT JOIN temporary_results tr ON l.UID = tr.UID AND l.WID = tr.WID AND l.attempt = tr.attempt AND tr.teacher_id = ?
         WHERE l.UID = ?"
            );
            $raw_data_stmt->bind_param("ss", $teacher_id, $student_id);
            $raw_data_stmt->execute();
            $all_attempts = $raw_data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $raw_data_stmt->close();

            $temp_grammar_stats = [];
            foreach ($all_attempts as $attempt) {
                if (!empty($attempt['grammar'])) {
                    $grammar_ids = explode('#', trim($attempt['grammar'], '#'));
                    foreach ($grammar_ids as $gid) {
                        if (empty($gid) || !isset($gid_map[$gid])) continue;
                        $grammar_name = $gid_map[$gid];

                        if (!isset($temp_grammar_stats[$grammar_name])) {
                            $temp_grammar_stats[$grammar_name] = ['total' => 0, 'correct' => 0, 'hesitated' => 0, 'estimated' => 0];
                        }
                        $temp_grammar_stats[$grammar_name]['total']++;
                        if ($attempt['TF'] == 1) $temp_grammar_stats[$grammar_name]['correct']++;
                        if ($attempt['Understand'] == 2) $temp_grammar_stats[$grammar_name]['hesitated']++;
                        if (in_array($attempt['Understand'], [2, 4])) $temp_grammar_stats[$grammar_name]['estimated']++;
                    }
                }
            }

            foreach ($temp_grammar_stats as $name => $stats) {
                $grammar_stats[] = [
                    'grammar_name' => $name,
                    'total_attempts' => $stats['total'],
                    'correct_count' => $stats['correct'],
                    'hesitated_count' => $stats['hesitated'],
                    'correct_rate' => ($stats['total'] > 0) ? round(($stats['correct'] / $stats['total']) * 100, 2) : 0,
                    'hesitation_rate' => ($stats['estimated'] > 0) ? round(($stats['hesitated'] / $stats['estimated']) * 100, 2) : 0,
                ];
            }

            // all_questions をレスポンスに追加
            $response = ['summary' => $summary, 'attempts' => $attempts, 'grammar_stats' => $grammar_stats, 'all_questions' => $all_questions];
        }
    } catch (Exception $e) {
        http_response_code(500);
        $response = ['error' => $e->getMessage()];
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?? 'ja' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS 教師用ホーム画面</title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <h3>メニュー</h3>
            <button id="sidebar-close" class="sidebar-close-button">&times;</button>
        </div>
        <ul>
            <li><a href="#">ホーム</a></li>
            <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
            <li><a href="register-student.php">新規学生登録</a></li>
            <li><a href="register-classteacher.php">教師-クラス登録</a></li>
            <li><a href="./create/new.php?mode=0">新規英語問題作成</a></li>
            <li><a href="create-test.php">新規英語テスト作成</a></li>
            <li><a href='create-student-group.php'>学習者グルーピング作成</a></li>
            <li><a href='create-notification.php'>お知らせ作成</a></li>
        </ul>
    </div>
    <div id="sidebar-backdrop" class="sidebar-backdrop"></div>

    <div class="main-content">
        <header class="fixed-header">
            <div class="header-left">
                <button id="menu-toggle" class="menu-button">☰</button>
                <h1>LMS 先生用ホーム画面</h1>
            </div>
            <div class="header-right">
                <span class="user-name"><?= $teacher_name ?> がログイン中</span>
                <a href="../logout.php" class="logout-link">ログアウト</a>
            </div>
        </header>

        <main class="page-content">
            <section class="card">
                <h2>お知らせ一覧</h2>
                <div class="announcements-list">
                    <?php
                    $result = $conn->query("SELECT id, subject, content FROM notifications ORDER BY created_at DESC LIMIT 5");
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<a href='notification_detail.php?id=" . $row['id'] . "' class='announcement-link'>";
                            echo "<div class='announcement-item'>";
                            echo "<h3 class='announcement-title'>" . htmlspecialchars($row['subject']) . "</h3>";
                            $content_preview = mb_substr(strip_tags($row['content']), 0, 50);
                            echo "<p class='announcement-content'>" . nl2br(htmlspecialchars($content_preview)) . (mb_strlen($row['content']) > 50 ? '...' : '') . "</p>";
                            echo "</div>";
                            echo "</a>";
                        }
                    } else {
                        echo "<p>現在お知らせはありません</p>";
                    }
                    ?>
                </div>
            </section>

            <section class="card">
                <h2>成績情報 (担当クラスのみ)
                    <span class="info-icon">i
                        <div class="info-popup">
                            学習者の解答時の下記のような詳細な情報は、学習者の結果表示後に出現する"表示"リンクから飛べるマウス軌跡再現ページにて表示しております。<br>
                            「解答中のマウスの軌跡再現」、「最終解答文や正解文、訳文」、「解答時間」... 等
                        </div>
                    </span>
                </h2>

                <div class="grades-section">
                    <h3>担当クラス学習者の結果表示</h3>
                    <div class="controls">
                        <p>表示したい学習者を選択してください。</p>
                    </div>
                    <div id="class-student-checkbox-container" class="checkbox-section">
                        <?php
                        if ($teacher_id) {
                            $class_ids = [];
                            $stmt_classes = $conn->prepare("SELECT ClassID FROM classteacher WHERE TID = ?");
                            if ($stmt_classes) {
                                $stmt_classes->bind_param("s", $teacher_id);
                                $stmt_classes->execute();
                                $class_result = $stmt_classes->get_result();
                                while ($row = $class_result->fetch_assoc()) $class_ids[] = $row['ClassID'];
                                $stmt_classes->close();
                            }

                            if (!empty($class_ids)) {
                                echo '<div class="checkbox-controls"><label><input type="checkbox" class="select-all" checked> 全て選択 / 解除</label></div>';
                                echo '<div class="checkbox-list">';
                                $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
                                $types = str_repeat('i', count($class_ids));
                                $stmt_students = $conn->prepare("SELECT s.uid, s.Name, c.ClassName FROM students s JOIN classes c ON s.ClassID = c.ClassID WHERE s.ClassID IN ($placeholders) ORDER BY c.ClassName, s.uid");
                                if ($stmt_students) {
                                    $stmt_students->bind_param($types, ...$class_ids);
                                    $stmt_students->execute();
                                    $student_result = $stmt_students->get_result();
                                    while ($row = $student_result->fetch_assoc()) {
                                        echo '<label class="checkbox-item"><input type="checkbox" value="' . htmlspecialchars($row['uid']) . '" checked> ' . htmlspecialchars($row['Name']) . ' (クラス: ' . htmlspecialchars($row['ClassName']) . ')</label>';
                                    }
                                    echo '</div>';
                                    $stmt_students->close();
                                }
                            } else {
                                echo '<p>担当しているクラスに学習者がいません。もしくは担当しているクラスがありません。</p>';
                                echo '<p><a href="register-classteacher.php">教師-クラス登録</a>からクラスの作成や登録、<a href="register-student.php">新規学習者登録</a>からクラスに学習者の登録を行ってください。</p>';
                            }
                        }
                        ?>
                    </div>
                    <div class="controls">
                        <button id="show-class-results-btn" class="action-button">選択した学習者の結果を表示</button>
                    </div>
                    <div id="class-results-container" class="results-container">
                        <p>学習者を選択して結果を表示してください。</p>
                    </div>
                </div>

                <div class="grades-section">
                    <h3>テストごとの結果表示</h3>
                    <?php
                    $tests_list = [];
                    if ($teacher_id) {
                        $stmt_tests = $conn->prepare("SELECT id, test_name FROM tests WHERE teacher_id = ? ORDER BY id DESC");
                        if ($stmt_tests) {
                            $stmt_tests->bind_param("s", $teacher_id);
                            $stmt_tests->execute();
                            $result_tests = $stmt_tests->get_result();
                            while ($row_test = $result_tests->fetch_assoc()) {
                                $tests_list[] = $row_test;
                            }
                            $stmt_tests->close();
                        }
                    }

                    if (empty($tests_list)):
                    ?>
                        <p>テスト作成がまだ行われていません。<a href="create-test.php">新規英語テスト作成</a>もしくは<a href="create-test-ja.php">新規日本語テスト作成</a>からテストを作成してください</p>
                    <?php else: ?>
                        <div class="controls">
                            <label for="test-select">1. テストを選択:</label>
                            <select id="test-select" name="test-select">
                                <option value="">-- 選択してください --</option>
                                <?php foreach ($tests_list as $test): ?>
                                    <option value="<?= htmlspecialchars($test['id']) ?>"><?= htmlspecialchars($test['test_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="student-checkbox-container" class="checkbox-section"></div>
                        <div id="test-question-checkbox-container" class="checkbox-section" style="display:none;"></div>
                        <div id="test-controls" style="display:none;">
                            <button id="show-test-results-btn" class="action-button">結果を表示</button>
                        </div>
                        <div id="test-results-container" class="results-container">
                            <p>テストを選択してください。</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grades-section">
                    <h3>学習者ごとの詳細結果</h3>
                    <div class="controls">
                        <label for="student-select">学習者を選択:</label>
                        <select id="student-select" name="student-select">
                            <option value="">-- 選択してください --</option>
                            <?php
                            if ($teacher_id) {
                                $class_ids = [];
                                $stmt_classes = $conn->prepare("SELECT ClassID FROM classteacher WHERE TID = ?");
                                if ($stmt_classes) {
                                    $stmt_classes->bind_param("s", $teacher_id);
                                    $stmt_classes->execute();
                                    $result_classes = $stmt_classes->get_result();
                                    while ($row_class = $result_classes->fetch_assoc()) $class_ids[] = $row_class['ClassID'];
                                    $stmt_classes->close();
                                }
                                if (!empty($class_ids)) {
                                    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
                                    $stmt_students = $conn->prepare("SELECT uid, Name FROM students WHERE ClassID IN ($placeholders) ORDER BY Name");
                                    if ($stmt_students) {
                                        $types = str_repeat('i', count($class_ids));
                                        $stmt_students->bind_param($types, ...$class_ids);
                                        $stmt_students->execute();
                                        $result_students = $stmt_students->get_result();
                                        while ($row_student = $result_students->fetch_assoc()) {
                                            echo "<option value='" . $row_student['uid'] . "'>" . htmlspecialchars($row_student['Name']) . "</option>";
                                        }
                                        $stmt_students->close();
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div id="question-checkbox-container-student" class="checkbox-section"></div>
                    <div id="student-controls" style="display:none;">
                        <button id="show-student-details-btn" class="action-button">選択した問題の結果を表示</button>
                    </div>
                    <div id="student-details-container" class="results-container">
                        <p>学習者を選択すると、解答した問題リストが表示されます。</p>
                    </div>
                    <div id="grammar-analysis-wrapper" style="display: none; margin-top: 25px;">
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 要素の取得
            const menuToggle = document.getElementById('menu-toggle');
            const sidebarClose = document.getElementById('sidebar-close');
            const backdrop = document.getElementById('sidebar-backdrop');
            const body = document.body;

            // 「担当クラス」の要素
            const classStudentCheckboxContainer = document.getElementById('class-student-checkbox-container');
            const showClassResultsBtn = document.getElementById('show-class-results-btn');
            const classResultsContainer = document.getElementById('class-results-container');

            // 「テストごと」の要素
            const testSelect = document.getElementById('test-select');
            const studentCheckboxContainer = document.getElementById('student-checkbox-container');
            const testQuestionCheckboxContainer = document.getElementById('test-question-checkbox-container');
            const testControls = document.getElementById('test-controls');
            const showTestResultsBtn = document.getElementById('show-test-results-btn');
            const testResultsContainer = document.getElementById('test-results-container');

            // 「学習者ごと」の要素
            const studentSelect = document.getElementById('student-select');
            const questionCheckboxContainerStudent = document.getElementById('question-checkbox-container-student');
            const studentControls = document.getElementById('student-controls');
            const showStudentDetailsBtn = document.getElementById('show-student-details-btn');
            const studentDetailsContainer = document.getElementById('student-details-container');
            const grammarAnalysisWrapper = document.getElementById('grammar-analysis-wrapper'); // 新しく追加

            // --- 1. サイドバーの開閉処理 ---
            function openSidebar() {
                body.classList.add('sidebar-open');
            }

            function closeSidebar() {
                body.classList.remove('sidebar-open');
            }
            menuToggle.addEventListener('click', openSidebar);
            sidebarClose.addEventListener('click', closeSidebar);
            backdrop.addEventListener('click', closeSidebar);

            // --- 2. 担当クラスの結果表示 ---
            if (classStudentCheckboxContainer) {
                const selectAllClassStudents = classStudentCheckboxContainer.querySelector('.select-all');
                if (selectAllClassStudents) {
                    selectAllClassStudents.addEventListener('change', (e) => {
                        classStudentCheckboxContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = e.target.checked);
                    });
                }
                showClassResultsBtn.addEventListener('click', async () => {
                    const selectedStudents = Array.from(classStudentCheckboxContainer.querySelectorAll('input:checked:not(.select-all)')).map(cb => cb.value);
                    if (selectedStudents.length === 0) return alert('学習者を1名以上選択してください。');
                    classResultsContainer.innerHTML = '<p class="loading">結果を読み込んでいます...</p>';
                    try {
                        const results = await fetchData({
                            action: 'get_class_results',
                            student_ids: JSON.stringify(selectedStudents)
                        });
                        renderClassResults(results);
                    } catch (error) {
                        console.error('Error fetching class results:', error);
                        classResultsContainer.innerHTML = '<p class="error">結果の読み込みに失敗しました。</p>';
                    }
                });
            }

            // --- 3. テストごとの結果表示 ---
            if (testSelect) {
                testSelect.addEventListener('change', async function() {
                    const testId = this.value;
                    studentCheckboxContainer.innerHTML = '';
                    testQuestionCheckboxContainer.innerHTML = '';
                    testControls.style.display = 'none';
                    testResultsContainer.innerHTML = '<p>テストを選択してください。</p>';
                    if (!testId) return;
                    studentCheckboxContainer.innerHTML = '<p class="loading">受験者を読み込んでいます...</p>';
                    try {
                        const students = await fetchData({
                            action: 'get_students_for_test',
                            test_id: testId
                        });
                        renderCheckboxes(studentCheckboxContainer, students, 'student', '2. 学習者を選択:');
                        studentCheckboxContainer.addEventListener('change', handleStudentSelectionChangeForTest);
                    } catch (error) {
                        studentCheckboxContainer.innerHTML = '<p class="error">受験者の読み込みに失敗しました。</p>';
                    }
                });

                showTestResultsBtn.addEventListener('click', async () => {
                    const testId = testSelect.value;
                    const selectedStudents = Array.from(studentCheckboxContainer.querySelectorAll('input:checked:not(.select-all)')).map(cb => cb.value);
                    const selectedWids = Array.from(testQuestionCheckboxContainer.querySelectorAll('input:checked:not(.select-all)')).map(cb => cb.value);
                    if (selectedStudents.length === 0) return alert('学習者を1名以上選択してください。');
                    if (selectedWids.length === 0) return alert('問題を1つ以上選択してください。');
                    testResultsContainer.innerHTML = '<p class="loading">結果を読み込んでいます...</p>';
                    try {
                        const results = await fetchData({
                            action: 'get_test_results',
                            test_id: testId,
                            student_ids: JSON.stringify(selectedStudents),
                            wids: JSON.stringify(selectedWids)
                        });
                        renderTestResults(results);
                    } catch (error) {
                        testResultsContainer.innerHTML = '<p class="error">結果の読み込みに失敗しました。</p>';
                    }
                });
            }

            // --- 4. 学習者ごとの詳細結果 ---
            if (studentSelect) {
                studentSelect.addEventListener('change', async function() {
                    const studentId = this.value;
                    // 各コンテナを初期化
                    studentDetailsContainer.innerHTML = '<p>学習者を選択すると、解答した問題リストが表示されます。</p>';
                    questionCheckboxContainerStudent.innerHTML = '';
                    grammarAnalysisWrapper.style.display = 'none';
                    grammarAnalysisWrapper.innerHTML = '';
                    studentControls.style.display = 'none';

                    if (!studentId) return;

                    questionCheckboxContainerStudent.innerHTML = '<p class="loading">問題リストと分析データを読み込んでいます...</p>';
                    try {
                        const data = await fetchData({
                            action: 'get_student_details',
                            student_id: studentId
                        });
                        renderGrammarAnalysis(data.grammar_stats); // 文法分析を先に描画
                        renderCheckboxes(questionCheckboxContainerStudent, data.all_questions, 'question', '問題');
                        if (data.all_questions && data.all_questions.length > 0) {
                            studentControls.style.display = 'block';
                        } else {
                            questionCheckboxContainerStudent.innerHTML = '<p>この学習者の解答履歴はありません。</p>';
                        }
                    } catch (error) {
                        console.error('Error fetching initial student data:', error);
                        questionCheckboxContainerStudent.innerHTML = '<p class="error">データ読み込みに失敗しました。</p>';
                        grammarAnalysisWrapper.innerHTML = '<p class="error">分析データの読み込みに失敗しました。</p>';
                        grammarAnalysisWrapper.style.display = 'block';
                    }
                });

                showStudentDetailsBtn.addEventListener('click', async () => {
                    const studentId = studentSelect.value;
                    const selectedWids = Array.from(questionCheckboxContainerStudent.querySelectorAll('input:checked:not(.select-all)')).map(cb => cb.value);
                    if (selectedWids.length === 0) return alert('問題を1つ以上選択してください。');
                    studentDetailsContainer.innerHTML = '<p class="loading">詳細を読み込んでいます...</p>';
                    try {
                        const data = await fetchData({
                            action: 'get_student_details',
                            student_id: studentId,
                            wids: JSON.stringify(selectedWids)
                        });
                        renderStudentProblemResults(data, studentId); // 問題ごとの結果のみを描画
                    } catch (error) {
                        console.error('Error fetching student details:', error);
                        studentDetailsContainer.innerHTML = '<p class="error">詳細の読み込みに失敗しました。</p>';
                    }
                });
            }

            // --- 5. 共通の描画・補助関数 ---
            async function fetchData(bodyObj) {
                const formData = new FormData();
                for (const key in bodyObj) formData.append(key, bodyObj[key]);
                const response = await fetch('teachertrue.php', {
                    method: 'POST',
                    body: formData
                });
                if (!response.ok) throw new Error(`Network response was not ok, status: ${response.status}`);
                return await response.json();
            }

            async function handleStudentSelectionChangeForTest() {
                const selectedStudents = studentCheckboxContainer.querySelectorAll('input:checked');
                const testId = testSelect.value;
                if (selectedStudents.length > 0) {
                    testQuestionCheckboxContainer.style.display = 'block';
                    testQuestionCheckboxContainer.innerHTML = '<p class="loading">問題リストを読み込んでいます...</p>';
                    try {
                        const questions = await fetchData({
                            action: 'get_questions_for_test',
                            test_id: testId
                        });
                        renderCheckboxes(testQuestionCheckboxContainer, questions, 'question', '3. 問題を選択:');
                        testControls.style.display = 'block';
                    } catch (error) {
                        testQuestionCheckboxContainer.innerHTML = '<p class="error">問題リストの読み込みに失敗しました。</p>';
                    }
                } else {
                    testQuestionCheckboxContainer.style.display = 'none';
                    testQuestionCheckboxContainer.innerHTML = '';
                    testControls.style.display = 'none';
                }
            }

            function renderCheckboxes(container, items, type, title) {
                if (!items || items.length === 0) {
                    container.innerHTML = `<p>対象の${type === 'student' ? '学習者' : '問題'}はありません。</p>`;
                    return;
                }
                const idKey = type === 'student' ? 'uid' : 'WID';
                const nameKey = type === 'student' ? 'Name' : 'Sentence';
                let checkboxesHtml = `<h4>${title}</h4>
            <div class="checkbox-controls"><label><input type="checkbox" class="select-all" checked> 全て選択 / 解除</label></div>
            <div class="checkbox-list">`;
                items.forEach(item => {
                    const displayName = type === 'student' ?
                        `${item[nameKey]} (${item[idKey]})` : `WID:${item[idKey]}` + (item[nameKey] ? ` : ${item[nameKey]}` : '');
                    const asterisk = (item.is_unanswered) ? ' <span style="color: red; font-weight: bold;">*</span>' : '';
                    checkboxesHtml += `<label class="checkbox-item"><input type="checkbox" value="${item[idKey]}" checked> ${displayName} ${asterisk}</label>`;
                });
                checkboxesHtml += '</div>';
                container.innerHTML = checkboxesHtml;

                container.querySelector('.select-all').addEventListener('change', function(e) {
                    container.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = e.target.checked);
                    if (container === studentCheckboxContainer) handleStudentSelectionChangeForTest();
                });
                if (container === studentCheckboxContainer) handleStudentSelectionChangeForTest();
            }

            function renderClassResults(data) {
                if (!data || data.length === 0) return classResultsContainer.innerHTML = '<p>選択された学習者の解答結果はありません。</p>';
                let tableHtml = `<table><thead><tr><th>クラス名</th><th>学習者名 (ID)</th><th>テスト名</th><th>問題ID (回数)</th><th>正誤</th><th>迷い推定</th><th>解答日時</th><th>軌跡再現</th></tr></thead><tbody>`;
                data.forEach(row => {
                    tableHtml += `<tr>
                <td>${row.ClassName}</td>
                <td>${row.student_name} (${row.student_id})</td>
                <td>${row.test_name}</td>
                <td>${row.WID} (${row.attempt}回目)</td>
                <td class="${row.correctness === '不正解' ? 'incorrect' : ''}">${row.correctness}</td>
                <td class="${row.hesitation === '迷い有り' ? 'hesitation-yes' : ''}">${row.hesitation}</td>
                <td>${row.date}</td>
                <td><a href="./mousemove/mousemove.php?UID=${row.student_id}&WID=${row.WID}&test_id=${row.test_id}&LogID=${row.attempt}" target="_blank" class="link-button">表示</a></td>
            </tr>`;
                });
                classResultsContainer.innerHTML = tableHtml + '</tbody></table>';
            }

            function renderTestResults(data) {
                if (!data || data.length === 0) return testResultsContainer.innerHTML = '<p>該当する解答結果はありません。</p>';
                let tableHtml = `<table><thead><tr><th>学習者名 (ID)</th><th>問題ID</th><th>正誤</th><th>迷い推定</th><th>解答日時</th><th>軌跡再現</th></tr></thead><tbody>`;
                data.forEach(row => {
                    const isUnanswered = row.correctness === '未解答';
                    tableHtml += `<tr>
                <td>${row.student_name} (${row.student_id})</td>
                <td>${row.WID}</td>
                <td class="${isUnanswered ? '' : (row.correctness === '不正解' ? 'incorrect' : '')}">${row.correctness}</td>
                <td class="${isUnanswered ? '' : (row.hesitation === '迷い有り' ? 'hesitation-yes' : '')}">${row.hesitation}</td>
                <td>${row.date}</td>
                <td>${isUnanswered ? '-' : `<a href="./mousemove/mousemove.php?UID=${row.student_id}&WID=${row.WID}&test_id=${testSelect.value}&LogID=${row.attempt}" target="_blank" class="link-button">表示</a>`}</td>
            </tr>`;
                });
                testResultsContainer.innerHTML = tableHtml + '</tbody></table>';
            }

            // ▼▼▼【ここから修正】▼▼▼
            // 「問題ごとの結果」を描画する関数（サマリー + 解答履歴）
            function renderStudentProblemResults(data, studentId) {
                if (!data || !data.summary) {
                    studentDetailsContainer.innerHTML = '<p>この学習者のデータはありません。</p>';
                    return;
                }
                const infoPopupHtml = `<span class="info-icon">i<div class="info-popup"><strong>各指標の説明</strong><ul><li><strong>総解答数:</strong> 選択された問題において、この学習者が解答した総数です。</li><li><strong>正答率:</strong> 選択された問題における正解の割合です。</li><li><strong>迷い率:</strong> 選択された問題のうち、推定結果が「迷い有り」または「迷い無し」の問題における「迷い有り」の割合です。（「未推定」は計算から除外）</li></ul></div></span>`;
                let detailsHtml = `<div class="student-summary"><h4>総合評価 ${infoPopupHtml}</h4><p><strong>総解答数 (選択問題):</strong> ${data.summary.total_attempts}</p><p><strong>正答率 (選択問題):</strong> ${data.summary.accuracy}</p><p><strong>迷い率 (選択問題):</strong> ${data.summary.hesitation_rate}</p></div><h4>問題ごとの結果</h4>`;

                if (!data.attempts || data.attempts.length === 0) {
                    detailsHtml += '<p>選択された問題の解答履歴はありません。</p>';
                } else {
                    detailsHtml += `<table><thead><tr><th>問題ID</th><th>テスト名</th><th>正誤</th><th>迷い推定</th><th>解答日時</th><th>軌跡再現</th></tr></thead><tbody>`;
                    data.attempts.forEach(attempt => {
                        const testName = attempt.test_name || '（不明なテスト）';
                        detailsHtml += `<tr>
                    <td>${attempt.WID} (${attempt.attempt}回目)</td>
                    <td>${testName}</td>
                    <td class="${attempt.correctness === '不正解' ? 'incorrect' : ''}">${attempt.correctness}</td>
                    <td class="${attempt.hesitation === '迷い有り' ? 'hesitation-yes' : ''}">${attempt.hesitation}</td>
                    <td>${attempt.date}</td>
                    <td><a href="./mousemove/mousemove.php?UID=${studentId}&WID=${attempt.WID}&test_id=${attempt.test_id}&LogID=${attempt.attempt}" target="_blank" class="link-button">表示</a></td>
                </tr>`;
                    });
                    detailsHtml += '</tbody></table>';
                }
                studentDetailsContainer.innerHTML = detailsHtml;
            }

            // 「文法項目ごとの分析」を描画する関数
            function renderGrammarAnalysis(grammarStats) {
                grammarAnalysisWrapper.style.display = 'block'; // コンテナを表示
                if (!grammarStats || grammarStats.length === 0) {
                    grammarAnalysisWrapper.innerHTML = '<h4>文法項目ごとの分析</h4><p>この学習者の文法分析データはありません。</p>';
                    return;
                }

                let grammarHtml = `<h4>文法項目ごとの分析</h4><div class="grammar-analysis-container"><div class="grammar-table-container">
            <table><thead><tr><th>文法項目</th><th>総解答数</th><th>正解数</th><th>迷い数</th><th>不正解率</th><th>迷い率</th></tr></thead><tbody>`;
                grammarStats.forEach(stat => {
                    const incorrectRate = (100 - stat.correct_rate).toFixed(2);
                    grammarHtml += `<tr>
                <td>${stat.grammar_name}</td>
                <td>${stat.total_attempts}</td>
                <td>${stat.correct_count}</td>
                <td>${stat.hesitated_count}</td>
                <td>${incorrectRate}%</td>
                <td>${stat.hesitation_rate.toFixed(2)}%</td>
            </tr>`;
                });
                grammarHtml += `</tbody></table></div><div class="grammar-chart-container"><canvas id="grammarAnalysisChart"></canvas></div></div>`;
                grammarAnalysisWrapper.innerHTML = grammarHtml;

                // グラフを描画
                const ctx = document.getElementById('grammarAnalysisChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: grammarStats.map(s => s.grammar_name),
                        datasets: [{
                            label: '不正解率 (%)',
                            data: grammarStats.map(s => (100 - s.correct_rate).toFixed(2)),
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        }, {
                            label: '迷い率 (%)',
                            data: grammarStats.map(s => s.hesitation_rate.toFixed(2)),
                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: '文法項目ごとの不正解率と迷い率'
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: '文法項目'
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: '割合 (%)'
                                },
                                min: 0,
                                max: 100
                            }
                        }
                    }
                });
            }
            // ▲▲▲【ここまで修正】▲▲▲
        });
    </script>
</body>

</html>
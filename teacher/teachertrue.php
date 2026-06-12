<?php
// セッションを開始し、多言語対応とデータベース接続を読み込みます
include '../lang.php';
require "../dbc.php";

// ログイン中の教師IDを取得します
$teacher_id = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;

//不正侵入対策
if (empty($_SESSION['MemberID'])) {
    http_response_code(401);
    echo "<p>ログイン情報が見つかりません。</p>";
    exit;
}

$logic_filter_groups = [];
$logic_filter_students_by_group = [];
if ($teacher_id) {
    $stmt_logic_groups = $conn->prepare("SELECT group_id, group_name FROM `groups` WHERE TID = ? ORDER BY created_at DESC, group_id DESC");
    if ($stmt_logic_groups) {
        $stmt_logic_groups->bind_param("s", $teacher_id);
        $stmt_logic_groups->execute();
        $logic_group_result = $stmt_logic_groups->get_result();
        while ($group_row = $logic_group_result->fetch_assoc()) {
            $logic_filter_groups[] = $group_row;
            $logic_filter_students_by_group[(string)$group_row['group_id']] = [];
        }
        $stmt_logic_groups->close();
    }

    if (!empty($logic_filter_groups)) {
        $logic_group_ids = array_map('intval', array_column($logic_filter_groups, 'group_id'));
        $logic_group_placeholders = implode(',', array_fill(0, count($logic_group_ids), '?'));
        $logic_group_types = str_repeat('i', count($logic_group_ids));
        $stmt_logic_members = $conn->prepare("SELECT group_id, uid FROM group_members WHERE group_id IN ($logic_group_placeholders)");
        if ($stmt_logic_members) {
            $stmt_logic_members->bind_param($logic_group_types, ...$logic_group_ids);
            $stmt_logic_members->execute();
            $logic_member_result = $stmt_logic_members->get_result();
            while ($member_row = $logic_member_result->fetch_assoc()) {
                $logic_filter_students_by_group[(string)$member_row['group_id']][] = (string)$member_row['uid'];
            }
            $stmt_logic_members->close();
        }
    }
}

// --- AJAXリクエストの処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = [];

    try {
        // 【新規追加】アクション: 担当クラスの全学習者の結果を取得
        if ($_POST['action'] === 'get_class_results' && isset($_POST['student_ids'])) {
            $student_ids = json_decode($_POST['student_ids']);
            $wids = isset($_POST['wids']) && !empty($_POST['wids']) ? json_decode($_POST['wids']) : [];
            // ★★★ 新機能: 絞り込み条件を取得 ★★★
            $correctness_filter = $_POST['correctness'] ?? 'all';
            $hesitation_filter = $_POST['hesitation'] ?? 'all';


            if (!empty($student_ids) && is_array($student_ids)) {
                $params = [$teacher_id];
                $types = 's';
                
                $sql = "SELECT 
                    l.UID as student_id, s.Name as student_name, c.ClassID, c.ClassName, l.WID, l.Date as date, l.attempt, l.test_id,
                    COALESCE(t.test_name, '（不明なテスト）') as test_name,
                    CASE WHEN l.TF = 1 THEN '正解' ELSE '不正解' END as correctness,
                    CASE tr.Understand WHEN 2 THEN '迷い有り' WHEN 4 THEN '迷い無し' ELSE '未推定' END as hesitation
                 FROM linedata l
                 JOIN students s ON l.UID = s.uid
                 JOIN classes c ON s.ClassID = c.ClassID
                 LEFT JOIN tests t ON l.test_id = t.id
                 LEFT JOIN temporary_results tr ON l.UID = tr.UID AND l.WID = tr.WID AND l.attempt = tr.attempt AND tr.teacher_id = ?
                 WHERE ";

                $conditions = [];
                // Student IDs
                $placeholders_students = implode(',', array_fill(0, count($student_ids), '?'));
                $conditions[] = "l.UID IN ($placeholders_students)";
                $types .= str_repeat('s', count($student_ids));
                $params = array_merge($params, $student_ids);

                // WIDs (if any)
                if (!empty($wids) && is_array($wids)) {
                    $placeholders_wids = implode(',', array_fill(0, count($wids), '?'));
                    $conditions[] = "l.WID IN ($placeholders_wids)";
                    $types .= str_repeat('i', count($wids));
                    $params = array_merge($params, $wids);
                }

                // ★★★ 新機能: SQLに絞り込み条件を追加 ★★★
                if ($correctness_filter === 'correct') {
                    $conditions[] = "l.TF = 1";
                } elseif ($correctness_filter === 'incorrect') {
                    $conditions[] = "l.TF = 0";
                }

                if ($hesitation_filter === 'hesitated') {
                    $conditions[] = "tr.Understand = 2";
                } elseif ($hesitation_filter === 'not_hesitated') {
                    $conditions[] = "tr.Understand = 4";
                } elseif ($hesitation_filter === 'not_estimated') {
                    $conditions[] = "(tr.Understand IS NULL OR tr.Understand NOT IN (2, 4))";
                }
                
                $sql .= implode(' AND ', $conditions);
                $sql .= " ORDER BY c.ClassID, s.uid, l.WID, l.attempt";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $response[] = $row;
                }
                $stmt->close();
            }
        }
        
        elseif ($_POST['action'] === 'get_wids_for_students' && isset($_POST['student_ids'])) {
            $student_ids = json_decode($_POST['student_ids']);
            if (!empty($student_ids) && is_array($student_ids)) {
                $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                $types = str_repeat('s', count($student_ids));
                $stmt = $conn->prepare(
                    "SELECT DISTINCT l.WID, qi.Sentence 
             FROM linedata l
             LEFT JOIN question_info qi ON l.WID = qi.WID
             WHERE l.UID IN ($placeholders) ORDER BY l.WID"
                );
                $stmt->bind_param($types, ...$student_ids);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $response[] = $row;
                }
                $stmt->close();
            }
        }
        elseif ($_POST['action'] === 'get_students_for_test' && isset($_POST['test_id'])) {
            $test_id = $_POST['test_id'];
            $assigned_students = [];

            $stmt_test = $conn->prepare("SELECT target_type, target_group FROM tests WHERE id = ?");
            $stmt_test->bind_param("i", $test_id);
            $stmt_test->execute();
            $test_info = $stmt_test->get_result()->fetch_assoc();
            $stmt_test->close();

            if ($test_info) {
                if ($test_info['target_type'] === 'class') {
                    $stmt_assigned = $conn->prepare("SELECT uid, Name FROM students WHERE ClassID = ? ORDER BY uid");
                    $stmt_assigned->bind_param("i", $test_info['target_group']);
                } else { 
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
            while ($row = $result->fetch_assoc())
                $response[] = $row;
            $stmt->close();
        }

        elseif ($_POST['action'] === 'get_questions_for_student' && isset($_POST['student_id'])) {
            $stmt = $conn->prepare(
                "SELECT DISTINCT l.WID, q.Sentence 
                 FROM linedata l
                 LEFT JOIN question_info q ON l.WID = q.WID
                 WHERE l.UID = ? ORDER BY l.WID"
            );
            $stmt->bind_param("s", $_POST['student_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc())
                $response[] = $row;
            $stmt->close();
        }

        elseif ($_POST['action'] === 'get_test_results' && isset($_POST['test_id'], $_POST['student_ids'], $_POST['wids'])) {
            $student_ids = json_decode($_POST['student_ids']);
            $wids = json_decode($_POST['wids']);
            // ★★★ 新機能: 絞り込み条件を取得 ★★★
            $correctness_filter = $_POST['correctness'] ?? 'all';
            $hesitation_filter = $_POST['hesitation'] ?? 'all';


            if (!empty($student_ids) && is_array($student_ids) && !empty($wids) && is_array($wids)) {
                $results_map = [];
                $student_names_map = [];
                $temp_response = []; // 一時的なレスポンス配列

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
                    $results_map[$row['student_id'] . '-' . $row['WID']] = $row;
                }
                $stmt->close();

                $stmt_names = $conn->prepare("SELECT uid, Name FROM students WHERE uid IN ($placeholders_students)");
                $name_types = str_repeat('i', count($student_ids));
                $stmt_names->bind_param($name_types, ...$student_ids);
                $stmt_names->execute();
                $result_names = $stmt_names->get_result();
                while ($row = $result_names->fetch_assoc()) {
                    $student_names_map[$row['uid']] = $row['Name'];
                }
                $stmt_names->close();

                foreach ($student_ids as $sid) {
                    foreach ($wids as $wid) {
                        $key = $sid . '-' . $wid;
                        if (isset($results_map[$key])) {
                            $temp_response[] = $results_map[$key];
                        } else {
                            $temp_response[] = [
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
                // ★★★ 新機能: PHP側でフィルタリングを実行 ★★★
                foreach ($temp_response as $item) {
                    $correct_match = false;
                    switch ($correctness_filter) {
                        case 'correct': $correct_match = ($item['correctness'] === '正解'); break;
                        case 'incorrect': $correct_match = ($item['correctness'] === '不正解'); break;
                        case 'unanswered': $correct_match = ($item['correctness'] === '未解答'); break;
                        default: $correct_match = true; break;
                    }

                    $hesitation_match = false;
                     switch ($hesitation_filter) {
                        case 'hesitated': $hesitation_match = ($item['hesitation'] === '迷い有り'); break;
                        case 'not_hesitated': $hesitation_match = ($item['hesitation'] === '迷い無し'); break;
                        case 'not_estimated': $hesitation_match = ($item['hesitation'] === '未推定'); break;
                        case 'na': $hesitation_match = ($item['hesitation'] === '-'); break;
                        default: $hesitation_match = true; break;
                    }

                    if ($correct_match && $hesitation_match) {
                        $response[] = $item;
                    }
                }
            }
        }
        elseif ($_POST['action'] === 'get_student_details' && isset($_POST['student_id'])) {
            $student_id = $_POST['student_id'];
            $wids = isset($_POST['wids']) ? json_decode($_POST['wids']) : [];
            // ★★★ 新機能: 絞り込み条件を取得 ★★★
            $correctness_filter = $_POST['correctness'] ?? 'all';
            $hesitation_filter = $_POST['hesitation'] ?? 'all';

            $summary = ['total_attempts' => 0, 'accuracy' => 'N/A', 'hesitation_rate' => 'N/A'];
            $attempts = [];
            $grammar_stats = [];
            $all_questions = [];
            $student_levels = ['toeic' => null, 'eiken' => null];

            $stmt_levels = $conn->prepare("SELECT toeic_level, eiken_level FROM students WHERE uid = ?");
            if ($stmt_levels) {
                $stmt_levels->bind_param("s", $student_id);
                $stmt_levels->execute();
                $result_levels = $stmt_levels->get_result()->fetch_assoc();
                if ($result_levels) {
                    $student_levels['toeic'] = $result_levels['toeic_level'];
                    $student_levels['eiken'] = $result_levels['eiken_level'];
                }
                $stmt_levels->close();
            }

            if (!empty($wids) && is_array($wids)) {
                $placeholders = implode(',', array_fill(0, count($wids), '?'));
                $base_params = [$teacher_id, $student_id];
                $base_types = 'ss';
                
                $summary_params = array_merge($base_params, $wids);
                $summary_types = $base_types . str_repeat('i', count($wids));

                $stmt_stats = $conn->prepare("SELECT COUNT(l.WID) as selected_total, SUM(CASE WHEN l.TF = 1 THEN 1 ELSE 0 END) as selected_correct, SUM(CASE WHEN tr.Understand = 2 THEN 1 ELSE 0 END) as hesitated_count, SUM(CASE WHEN tr.Understand IN (2, 4) THEN 1 ELSE 0 END) as estimated_count FROM linedata l LEFT JOIN temporary_results tr ON l.UID = tr.UID AND l.WID = tr.WID AND l.attempt = tr.attempt AND tr.teacher_id = ? WHERE l.UID = ? AND l.WID IN ($placeholders)");
                $stmt_stats->bind_param($summary_types, ...$summary_params);
                $stmt_stats->execute();
                $stats_result = $stmt_stats->get_result()->fetch_assoc();
                if ($stats_result) {
                    $summary['total_attempts'] = $stats_result['selected_total'] ?? 0;
                    if ($stats_result['selected_total'] > 0)
                        $summary['accuracy'] = round(($stats_result['selected_correct'] / $stats_result['selected_total']) * 100, 1) . '%';
                    if ($stats_result['estimated_count'] > 0)
                        $summary['hesitation_rate'] = round(($stats_result['hesitated_count'] / $stats_result['estimated_count']) * 100, 1) . '%';
                }
                $stmt_stats->close();
                
                // ★★★ 新機能: SQLに絞り込み条件を追加 ★★★
                $sql_attempts = "SELECT l.WID, l.Date as date, l.attempt, l.test_id, t.test_name, CASE WHEN l.TF = 1 THEN '正解' ELSE '不正解' END as correctness, CASE tr.Understand WHEN 2 THEN '迷い有り' WHEN 4 THEN '迷い無し' ELSE '未推定' END as hesitation FROM linedata l LEFT JOIN temporary_results tr ON l.UID = tr.UID AND l.WID = tr.WID AND l.attempt = tr.attempt AND tr.teacher_id = ? LEFT JOIN tests t ON l.test_id = t.id WHERE l.UID = ? AND l.WID IN ($placeholders)";
                $attempt_params = array_merge($base_params, $wids);
                $attempt_types = $base_types . str_repeat('i', count($wids));
                
                if ($correctness_filter === 'correct') {
                    $sql_attempts .= " AND l.TF = 1";
                } elseif ($correctness_filter === 'incorrect') {
                    $sql_attempts .= " AND l.TF = 0";
                }

                if ($hesitation_filter === 'hesitated') {
                    $sql_attempts .= " AND tr.Understand = 2";
                } elseif ($hesitation_filter === 'not_hesitated') {
                    $sql_attempts .= " AND tr.Understand = 4";
                } elseif ($hesitation_filter === 'not_estimated') {
                    $sql_attempts .= " AND (tr.Understand IS NULL OR tr.Understand NOT IN (2, 4))";
                }
                $sql_attempts .= " ORDER BY l.WID, l.attempt";

                $stmt_attempts = $conn->prepare($sql_attempts);
                $stmt_attempts->bind_param($attempt_types, ...$attempt_params);
                $stmt_attempts->execute();
                $result_attempts = $stmt_attempts->get_result();
                while ($row = $result_attempts->fetch_assoc())
                    $attempts[] = $row;
                $stmt_attempts->close();
            } else {
                $stmt_all_q = $conn->prepare("SELECT DISTINCT l.WID, q.Sentence FROM linedata l LEFT JOIN question_info q ON l.WID = q.WID WHERE l.UID = ? ORDER BY l.WID");
                $stmt_all_q->bind_param("s", $student_id);
                $stmt_all_q->execute();
                $result_all_q = $stmt_all_q->get_result();
                while ($row = $result_all_q->fetch_assoc())
                    $all_questions[] = $row;
                $stmt_all_q->close();
            }

            $gid_map = [];
            $stmt_gid = $conn->prepare("SELECT GID, Item FROM grammar_translations WHERE language = 'ja'");
            $stmt_gid->execute();
            $gid_result = $stmt_gid->get_result();
            while ($row = $gid_result->fetch_assoc())
                $gid_map[$row['GID']] = $row['Item'];
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
                        if (empty($gid) || !isset($gid_map[$gid]))
                            continue;
                        $grammar_name = $gid_map[$gid];
                        if (!isset($temp_grammar_stats[$grammar_name])) {
                            $temp_grammar_stats[$grammar_name] = ['total' => 0, 'correct' => 0, 'hesitated' => 0, 'estimated' => 0];
                        }
                        $temp_grammar_stats[$grammar_name]['total']++;
                        if ($attempt['TF'] == 1)
                            $temp_grammar_stats[$grammar_name]['correct']++;
                        if ($attempt['Understand'] == 2)
                            $temp_grammar_stats[$grammar_name]['hesitated']++;
                        if (in_array($attempt['Understand'], [2, 4]))
                            $temp_grammar_stats[$grammar_name]['estimated']++;
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
            $response = ['summary' => $summary, 'attempts' => $attempts, 'grammar_stats' => $grammar_stats, 'all_questions' => $all_questions, 'student_levels' => $student_levels];
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
    <style>
        /* フィルター用の追加スタイル */
        .filter-group {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .filter-group label {
            font-weight: normal;
        }

        .logic-filter-panel {
            margin: 14px 0;
            padding: 12px;
            border: 1px solid #d8dee4;
            border-radius: 8px;
            background: #f8fafc;
        }
        .logic-filter-panel h4 { margin: 0 0 10px; color: #243447; }
        .logic-filter-parts,
        .logic-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }
        .logic-filter-parts button,
        .logic-filter-actions button {
            min-height: 34px;
            padding: 0 12px;
            border: 1px solid #b7c3d0;
            border-radius: 6px;
            background: #fff;
            color: #243447;
            font-weight: 700;
            cursor: pointer;
        }
        .logic-filter-insert-control {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #475569;
            font-size: .9rem;
            font-weight: 700;
        }
        .logic-filter-builder {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-height: 48px;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #d8dee4;
            border-radius: 8px;
            background: #fff;
        }
        .logic-filter-token {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            min-height: 34px;
            padding: 4px 6px 4px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            font-weight: 700;
        }
        .logic-filter-token select {
            min-height: 28px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
        }
        .logic-filter-kind { min-width: 76px; }
        .logic-filter-token.operator { background: #ecfeff; border-color: #99f6e4; color: #0f766e; }
        .logic-filter-token.not { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
        .logic-filter-token.paren { background: #f1f5f9; }
        .logic-filter-remove {
            width: 26px;
            height: 26px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            font-weight: 800;
            line-height: 1;
        }
        .logic-filter-summary { margin: 0; color: #475569; font-weight: 700; }
        .logic-filter-summary.is-error { color: #b91c1c; }
    </style>
</head>

<body>
    <?php
    $teacher_page_title = 'LMS 先生用ホーム画面';
    include __DIR__ . '/teacher-menu.php';
    ?>

    <div class="main-content">
        <main class="page-content">
            <section class="card">
                <h2>お知らせ一覧</h2>
                <div class="announcements-list">
                    <?php
                    $result = $conn->query("SELECT id, subject, content FROM notifications ORDER BY created_at DESC");
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<a href='notification-detail.php?id=" . $row['id'] . "' class='announcement-link'>";
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
                <h2>成績情報 (担当グループ(クラス)のみ)
                    <span class="info-icon">i
                        <div class="info-popup">
                            学習者の解答時の下記のような詳細な情報は、学習者の結果表示後に出現する"表示"リンクから飛べるマウス軌跡再現ページにて表示しております。<br>
                            「解答中のマウスの軌跡再現」、「最終解答文や正解文、訳文」、「解答時間」... 等
                        </div>
                    </span>
                </h2>

                <div class="grades-section">
                    <h3>担当グループ(クラス)学習者の結果表示</h3>
                    <?php
                    if ($teacher_id) {
                        $teacher_classes = [];
                        $stmt_classes = $conn->prepare("SELECT c.ClassID, c.ClassName FROM classteacher ct JOIN classes c ON ct.ClassID = c.ClassID WHERE ct.TID = ? ORDER BY c.ClassName");
                        if ($stmt_classes) {
                            $stmt_classes->bind_param("s", $teacher_id);
                            $stmt_classes->execute();
                            $class_result = $stmt_classes->get_result();
                            while ($row = $class_result->fetch_assoc()) {
                                $teacher_classes[] = $row;
                            }
                            $stmt_classes->close();
                        }

                        if (!empty($teacher_classes)) {
                            ?>
                            <div class="controls">
                                <label for="class-filter-select">グループ(クラス)で絞り込み:</label>
                                <select id="class-filter-select">
                                    <option value="">全てのグループ(クラス)</option>
                                    <?php foreach ($teacher_classes as $class): ?>
                                        <option value="<?= htmlspecialchars($class['ClassID']) ?>">
                                            <?= htmlspecialchars($class['ClassName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="logic-filter-panel" id="class-logic-filter-panel">
                                <h4>論理式で学習者を絞り込み</h4>
                                <div class="logic-filter-parts">
                                    <button type="button" data-add-filter="condition">対象を追加</button>
                                    <button type="button" data-add-filter="and">AND</button>
                                    <button type="button" data-add-filter="or">OR</button>
                                    <button type="button" data-add-filter="not">NOT</button>
                                    <button type="button" data-add-filter="open">(</button>
                                    <button type="button" data-add-filter="close">)</button>
                                    <span class="logic-filter-insert-control">
                                        <label>追加位置</label>
                                        <select class="logic-filter-insert-position"></select>
                                    </span>
                                </div>
                                <div class="logic-filter-builder" id="class-logic-filter-builder"></div>
                                <div class="logic-filter-actions">
                                    <button type="button" id="apply-class-logic-filter">絞り込みを適用</button>
                                    <button type="button" id="reset-class-logic-filter">リセット</button>
                                    <button type="button" class="logic-filter-trim">追加位置から後ろを削除</button>
                                    <button type="button" class="logic-filter-clear">式を空にする</button>
                                    <p class="logic-filter-summary" id="class-logic-filter-summary">すべての学習者を対象にしています。</p>
                                </div>
                            </div>
                            <div id="class-student-checkbox-container" class="checkbox-section">
                                <div class="checkbox-controls">
                                    <label><input type="checkbox" class="select-all" checked> 全ての表示中学習者を 選択 / 解除</label>
                                </div>
                                <div class="checkbox-list">
                                    <?php
                                    $class_ids = array_column($teacher_classes, 'ClassID');
                                    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
                                    $types = str_repeat('i', count($class_ids));
                                    $stmt_students = $conn->prepare("SELECT s.uid, s.Name, s.ClassID, c.ClassName FROM students s JOIN classes c ON s.ClassID = c.ClassID WHERE s.ClassID IN ($placeholders) ORDER BY c.ClassName, s.uid");
                                    if ($stmt_students) {
                                        $stmt_students->bind_param($types, ...$class_ids);
                                        $stmt_students->execute();
                                        $student_result = $stmt_students->get_result();

                                        $students_by_class = [];
                                        while ($row = $student_result->fetch_assoc()) {
                                            $students_by_class[$row['ClassName']][] = $row;
                                        }
                                        $stmt_students->close();

                                        foreach ($students_by_class as $class_name => $students) {
                                            $class_id = $students[0]['ClassID'];

                                            echo '<div class="class-group-header">';
                                            echo '<h5>' . htmlspecialchars($class_name) . '</h5>';
                                            echo '<label><input type="checkbox" class="select-all-class" data-class-id="' . $class_id . '" checked> このグループ(クラス)を全て選択 / 解除</label>';
                                            echo '</div>';

                                            foreach ($students as $student) {
                                                echo '<label class="checkbox-item" data-class-id="' . htmlspecialchars($student['ClassID']) . '"><input type="checkbox" value="' . htmlspecialchars($student['uid']) . '" checked> ' . htmlspecialchars($student['Name']) . '</label>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <div id="class-question-checkbox-container" class="checkbox-section" style="display:none; margin-top: 15px;"></div>
                            
                            <div id="class-filters" class="filter-group">
                                <label for="class-correctness-filter">正誤で絞り込み:</label>
                                <select id="class-correctness-filter">
                                    <option value="all">すべて</option>
                                    <option value="correct">正解</option>
                                    <option value="incorrect">不正解</option>
                                </select>
                                <label for="class-hesitation-filter">迷い推定結果で絞り込み:</label>
                                <select id="class-hesitation-filter">
                                    <option value="all">すべて</option>
                                    <option value="hesitated">迷い有り</option>
                                    <option value="not_hesitated">迷い無し</option>
                                    <option value="not_estimated">未推定</option>
                                </select>
                            </div>

                            <div class="controls">
                                <button id="show-class-results-btn" class="action-button">選択した学習者の結果を表示</button>
                            </div>
                            <?php
                        } else {
                            echo '<p>担当しているグループ(クラス)に学習者がいません。もしくは担当しているグループ(クラス)がありません。</p>';
                            echo '<p><a href="register-classteacher.php">グループ(クラス)登録</a>からグループ(クラス)の作成や登録、<a href="register-student.php">新規学習者登録</a>からグループ(クラス)に学習者の登録を行ってください。</p>';
                        }
                    }
                    ?>
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
                        <p>テスト作成がまだ行われていません。<a href="create-test.php">新規英語テスト作成</a>もしくは<a
                                href="create-test-ja.php">新規日本語テスト作成</a>からテストを作成してください</p>
                    <?php else: ?>
                        <div class="controls">
                            <label for="test-select">1. テストを選択:</label>
                            <select id="test-select" name="test-select">
                                <option value="">-- 選択してください --</option>
                                <?php foreach ($tests_list as $test): ?>
                                    <option value="<?= htmlspecialchars($test['id']) ?>">
                                        <?= htmlspecialchars($test['test_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="student-checkbox-container" class="checkbox-section"></div>
                        <div id="test-question-checkbox-container" class="checkbox-section" style="display:none;"></div>
                        
                        <div id="test-filters" class="filter-group" style="display:none;">
                            <label for="test-correctness-filter">正誤で絞り込み:</label>
                            <select id="test-correctness-filter">
                                <option value="all">すべて</option>
                                <option value="correct">正解</option>
                                <option value="incorrect">不正解</option>
                                <option value="unanswered">未解答</option>
                            </select>
                            <label for="test-hesitation-filter">迷い推定結果で絞り込み:</label>
                            <select id="test-hesitation-filter">
                                <option value="all">すべて</option>
                                <option value="hesitated">迷い有り</option>
                                <option value="not_hesitated">迷い無し</option>
                                <option value="not_estimated">未推定</option>
                                <option value="na">-(該当なし)</option>
                            </select>
                        </div>

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
                                    while ($row_class = $result_classes->fetch_assoc())
                                        $class_ids[] = $row_class['ClassID'];
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

                     <div id="student-filters" class="filter-group" style="display:none;">
                        <label for="student-correctness-filter">正誤で絞り込み:</label>
                        <select id="student-correctness-filter">
                            <option value="all">すべて</option>
                            <option value="correct">正解</option>
                            <option value="incorrect">不正解</option>
                        </select>
                        <label for="student-hesitation-filter">迷い推定結果で絞り込み:</label>
                        <select id="student-hesitation-filter">
                            <option value="all">すべて</option>
                            <option value="hesitated">迷い有り</option>
                            <option value="not_hesitated">迷い無し</option>
                            <option value="not_estimated">未推定</option>
                        </select>
                    </div>

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
        document.addEventListener('DOMContentLoaded', function () {
            // 要素の取得
            // 「担当クラス」の要素
            const classStudentCheckboxContainer = document.getElementById('class-student-checkbox-container');
            const classQuestionCheckboxContainer = document.getElementById('class-question-checkbox-container');
            const showClassResultsBtn = document.getElementById('show-class-results-btn');
            const classResultsContainer = document.getElementById('class-results-container');
            const classCorrectnessFilter = document.getElementById('class-correctness-filter');
            const classHesitationFilter = document.getElementById('class-hesitation-filter');

            // 「テストごと」の要素
            const testSelect = document.getElementById('test-select');
            const studentCheckboxContainer = document.getElementById('student-checkbox-container');
            const testQuestionCheckboxContainer = document.getElementById('test-question-checkbox-container');
            const testControls = document.getElementById('test-controls');
            const showTestResultsBtn = document.getElementById('show-test-results-btn');
            const testResultsContainer = document.getElementById('test-results-container');
            const testFilters = document.getElementById('test-filters');
            const testCorrectnessFilter = document.getElementById('test-correctness-filter');
            const testHesitationFilter = document.getElementById('test-hesitation-filter');

            // 「学習者ごと」の要素
            const studentSelect = document.getElementById('student-select');
            const questionCheckboxContainerStudent = document.getElementById('question-checkbox-container-student');
            const studentControls = document.getElementById('student-controls');
            const showStudentDetailsBtn = document.getElementById('show-student-details-btn');
            const studentDetailsContainer = document.getElementById('student-details-container');
            const grammarAnalysisWrapper = document.getElementById('grammar-analysis-wrapper');
            const studentFilters = document.getElementById('student-filters');
            const studentCorrectnessFilter = document.getElementById('student-correctness-filter');
            const studentHesitationFilter = document.getElementById('student-hesitation-filter');

            const classFilterSelect = document.getElementById('class-filter-select');
            let currentClassData = [];
            let currentClassSort = { column: null, direction: 'asc' };

            let currentTestData = [];
            let currentTestSort = { column: null, direction: 'asc' };

            let currentStudentDetailsData = [];
            let currentStudentDetailsSort = { column: null, direction: 'asc' };
            let classWIDFetchDebounceTimer;
            const logicFilterGroups = <?= json_encode($logic_filter_groups, JSON_UNESCAPED_UNICODE) ?>;
            const logicFilterStudentsByGroup = <?= json_encode((object)$logic_filter_students_by_group, JSON_UNESCAPED_UNICODE) ?>;

            function setupStudentLogicFilter({ panel, builder, summary, studentContainer, onApplied }) {
                if (!panel || !builder || !summary || !studentContainer) return;
                const insertPosition = panel.querySelector('.logic-filter-insert-position');
                const trimButton = panel.querySelector('.logic-filter-trim');
                const clearButton = panel.querySelector('.logic-filter-clear');
                const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
                const getStudentItems = () => Array.from(studentContainer.querySelectorAll('.checkbox-item'));
                const allStudentIds = () => getStudentItems().map(item => item.querySelector('input[type="checkbox"]').value);
                const classOptions = () => Array.from(studentContainer.querySelectorAll('.select-all-class')).map(input => {
                    const heading = input.closest('.class-group-header');
                    return { id: String(input.dataset.classId), label: heading?.querySelector('h5')?.textContent?.trim() || `Class ${input.dataset.classId}` };
                });
                const classMap = () => {
                    const map = {};
                    getStudentItems().forEach(item => {
                        const classId = String(item.dataset.classId);
                        if (!map[classId]) map[classId] = [];
                        map[classId].push(item.querySelector('input[type="checkbox"]').value);
                    });
                    return map;
                };
                const targetOptions = () => [
                    ...classOptions().map(item => ({ value: `class:${item.id}`, label: `グループ(クラス): ${item.label}` })),
                    ...logicFilterGroups.map(item => ({ value: `group:${item.group_id}`, label: `グループ: ${item.group_name}` }))
                ];
                const optionHtml = () => {
                    const options = targetOptions();
                    return options.length === 0 ? '<option value="">対象がありません</option>' : options.map(option => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join('');
                };
                const kindOptionsHtml = selectedKind => [
                    ['condition', '対象'], ['and', 'AND'], ['or', 'OR'], ['not', 'NOT'], ['open', '('], ['close', ')']
                ].map(([value, label]) => `<option value="${value}"${value === selectedKind ? ' selected' : ''}>${label}</option>`).join('');
                const tokenLabel = token => {
                    const kind = token.dataset.kind;
                    if (kind === 'condition') {
                        const select = token.querySelector('.logic-filter-target');
                        return select?.options[select.selectedIndex]?.textContent || '対象';
                    }
                    if (kind === 'open') return '(';
                    if (kind === 'close') return ')';
                    return token.dataset.operator || kind.toUpperCase();
                };
                const updateInsertOptions = () => {
                    const current = insertPosition.value;
                    const tokens = Array.from(builder.querySelectorAll('.logic-filter-token'));
                    insertPosition.innerHTML = ['<option value="">末尾に追加</option>']
                        .concat(tokens.map((token, index) => `<option value="${index}">${index + 1}個目の前 (${escapeHtml(tokenLabel(token))})</option>`))
                        .join('');
                    if (current !== '' && Number(current) < tokens.length) insertPosition.value = current;
                };
                const renderToken = (token, kind) => {
                    token.className = 'logic-filter-token';
                    token.dataset.kind = kind;
                    delete token.dataset.operator;
                    const kindSelect = `<select class="logic-filter-kind">${kindOptionsHtml(kind)}</select>`;
                    if (kind === 'condition') {
                        token.innerHTML = `${kindSelect}<select class="logic-filter-target">${optionHtml()}</select><button type="button" class="logic-filter-remove">x</button>`;
                    } else if (kind === 'open' || kind === 'close') {
                        token.classList.add('paren');
                        token.innerHTML = `${kindSelect}<span>${kind === 'open' ? '(' : ')'}</span><button type="button" class="logic-filter-remove">x</button>`;
                    } else {
                        token.classList.add('operator');
                        if (kind === 'not') token.classList.add('not');
                        token.dataset.operator = kind.toUpperCase();
                        token.innerHTML = `${kindSelect}<span>${kind.toUpperCase()}</span><button type="button" class="logic-filter-remove">x</button>`;
                    }
                };
                const addToken = kind => {
                    const token = document.createElement('span');
                    renderToken(token, kind);
                    const tokens = Array.from(builder.querySelectorAll('.logic-filter-token'));
                    const position = insertPosition.value !== '' ? Number(insertPosition.value) : tokens.length;
                    if (Number.isInteger(position) && position >= 0 && position < tokens.length) {
                        builder.insertBefore(token, tokens[position]);
                        insertPosition.value = String(position + 1);
                    } else {
                        builder.appendChild(token);
                    }
                    updateInsertOptions();
                };
                const getTokens = () => Array.from(builder.querySelectorAll('.logic-filter-token')).map(token => {
                    const kind = token.dataset.kind;
                    if (kind === 'condition') {
                        const [targetType, targetId] = token.querySelector('.logic-filter-target').value.split(':');
                        return { type: 'condition', targetType, targetId };
                    }
                    if (kind === 'open' || kind === 'close') return { type: 'paren', paren: kind === 'open' ? '(' : ')' };
                    return { type: 'operator', operator: token.dataset.operator };
                });
                const setForCondition = (type, id) => {
                    const available = new Set(allStudentIds());
                    const source = type === 'group' ? logicFilterStudentsByGroup : classMap();
                    return new Set((source[String(id)] || []).map(String).filter(uid => available.has(uid)));
                };
                const complement = source => {
                    const selected = new Set(source);
                    return new Set(allStudentIds().filter(uid => !selected.has(uid)));
                };
                const evaluate = () => {
                    const list = getTokens();
                    if (list.length === 0) return new Set(allStudentIds());
                    let index = 0;
                    const primary = () => {
                        const token = list[index];
                        if (!token) throw new Error('条件が途中で終わっています。');
                        if (token.type === 'operator' && token.operator === 'NOT') {
                            index++;
                            return complement(primary());
                        }
                        if (token.type === 'paren' && token.paren === '(') {
                            index++;
                            const result = orExpr();
                            if (!list[index] || list[index].type !== 'paren' || list[index].paren !== ')') throw new Error('閉じ括弧を置いてください。');
                            index++;
                            return result;
                        }
                        if (token.type === 'condition') {
                            index++;
                            if (!token.targetType || !token.targetId) throw new Error('対象を選択してください。');
                            return setForCondition(token.targetType, token.targetId);
                        }
                        throw new Error('条件または括弧を置いてください。');
                    };
                    const andExpr = () => {
                        let result = primary();
                        while (list[index]?.type === 'operator' && list[index].operator === 'AND') {
                            index++;
                            const right = primary();
                            result = new Set([...result].filter(uid => right.has(uid)));
                        }
                        return result;
                    };
                    const orExpr = () => {
                        let result = andExpr();
                        while (list[index]?.type === 'operator' && list[index].operator === 'OR') {
                            index++;
                            result = new Set([...result, ...andExpr()]);
                        }
                        return result;
                    };
                    const result = orExpr();
                    if (index !== list.length) throw new Error('式の並びを確認してください。');
                    return result;
                };
                panel.querySelectorAll('[data-add-filter]').forEach(button => button.addEventListener('click', () => addToken(button.dataset.addFilter)));
                builder.addEventListener('click', event => {
                    if (event.target.classList.contains('logic-filter-remove')) {
                        event.target.closest('.logic-filter-token').remove();
                        updateInsertOptions();
                    }
                });
                builder.addEventListener('change', event => {
                    const token = event.target.closest('.logic-filter-token');
                    if (!token) return;
                    if (event.target.classList.contains('logic-filter-kind')) renderToken(token, event.target.value);
                    updateInsertOptions();
                });
                trimButton.addEventListener('click', () => {
                    if (insertPosition.value === '') {
                        summary.textContent = '削除を始める追加位置を選択してください。';
                        summary.classList.add('is-error');
                        return;
                    }
                    const start = Number(insertPosition.value);
                    Array.from(builder.querySelectorAll('.logic-filter-token')).forEach((token, index) => { if (index >= start) token.remove(); });
                    updateInsertOptions();
                    summary.textContent = `${start + 1}個目以降の部品を削除しました。`;
                    summary.classList.remove('is-error');
                });
                clearButton.addEventListener('click', () => {
                    builder.innerHTML = '';
                    updateInsertOptions();
                    summary.textContent = '式を空にしました。空のまま適用すると、すべての学習者が対象になります。';
                    summary.classList.remove('is-error');
                });
                panel.querySelector('[id^="apply-"]').addEventListener('click', async () => {
                    try {
                        const selected = evaluate();
                        getStudentItems().forEach(item => { item.querySelector('input[type="checkbox"]').checked = selected.has(item.querySelector('input[type="checkbox"]').value); });
                        studentContainer.querySelectorAll('.select-all-class').forEach(input => {
                            const items = getStudentItems().filter(item => item.dataset.classId === input.dataset.classId);
                            input.checked = items.length > 0 && items.every(item => item.querySelector('input[type="checkbox"]').checked);
                        });
                        const selectAll = studentContainer.querySelector('.select-all');
                        if (selectAll) selectAll.checked = getStudentItems().every(item => item.querySelector('input[type="checkbox"]').checked);
                        summary.textContent = `${selected.size}名の学習者を選択しています。`;
                        summary.classList.remove('is-error');
                        if (onApplied) await onApplied();
                    } catch (error) {
                        summary.textContent = error.message || '論理式を確認してください。';
                        summary.classList.add('is-error');
                    }
                });
                panel.querySelector('[id^="reset-"]').addEventListener('click', async () => {
                    builder.innerHTML = '';
                    studentContainer.querySelectorAll('.checkbox-item input[type="checkbox"], .select-all-class, .select-all').forEach(input => { input.checked = true; });
                    studentContainer.querySelectorAll('.checkbox-item').forEach(item => { item.style.display = 'block'; });
                    studentContainer.querySelectorAll('.class-group-header').forEach(item => { item.style.display = 'flex'; });
                    const classSelect = document.getElementById('class-filter-select');
                    if (classSelect) classSelect.value = '';
                    summary.textContent = 'すべての学習者を対象にしています。';
                    summary.classList.remove('is-error');
                    addToken('condition');
                    if (onApplied) await onApplied();
                });
                addToken('condition');
            }

            // --- 2. 担当クラスの結果表示 ---
            if (classStudentCheckboxContainer) {
                async function updateWIDListForClassSection() {
                    const selectedStudents = Array.from(classStudentCheckboxContainer.querySelectorAll('.checkbox-item input[type="checkbox"]:checked'))
                        .filter(cb => cb.closest('.checkbox-item').style.display !== 'none')
                        .map(cb => cb.value);

                    if (selectedStudents.length > 0) {
                        classQuestionCheckboxContainer.style.display = 'block';
                        classQuestionCheckboxContainer.innerHTML = '<p class="loading">関連する問題リストを読み込んでいます...</p>';
                        try {
                            const wids = await fetchData({ action: 'get_wids_for_students', student_ids: JSON.stringify(selectedStudents) });
                            renderCheckboxes(classQuestionCheckboxContainer, wids, 'question', '問題で絞り込み (任意):');
                        } catch (error) {
                            classQuestionCheckboxContainer.innerHTML = '<p class="error">問題リストの読み込みに失敗しました。</p>';
                        }
                    } else {
                        classQuestionCheckboxContainer.style.display = 'none';
                        classQuestionCheckboxContainer.innerHTML = '';
                    }
                }

                setupStudentLogicFilter({
                    panel: document.getElementById('class-logic-filter-panel'),
                    builder: document.getElementById('class-logic-filter-builder'),
                    summary: document.getElementById('class-logic-filter-summary'),
                    studentContainer: classStudentCheckboxContainer,
                    onApplied: updateWIDListForClassSection
                });

                classStudentCheckboxContainer.addEventListener('change', e => {
                    const target = e.target;
                    if (target.classList.contains('select-all-class')) {
                        const classId = target.dataset.classId;
                        const isChecked = target.checked;
                        classStudentCheckboxContainer.querySelectorAll(`.checkbox-item[data-class-id="${classId}"] input[type="checkbox"]`).forEach(cb => { cb.checked = isChecked; });
                    } else if (target.classList.contains('select-all')) {
                        const isChecked = target.checked;
                        classStudentCheckboxContainer.querySelectorAll('.checkbox-item').forEach(label => {
                            if (label.style.display !== 'none') {
                                label.querySelector('input[type="checkbox"]').checked = isChecked;
                            }
                        });
                    }
                    clearTimeout(classWIDFetchDebounceTimer);
                    classWIDFetchDebounceTimer = setTimeout(updateWIDListForClassSection, 500);
                });

                if (classFilterSelect) {
                    classFilterSelect.addEventListener('change', () => {
                        const selectedClassId = classFilterSelect.value;
                        const studentItems = classStudentCheckboxContainer.querySelectorAll('.checkbox-item');
                        const classHeaders = classStudentCheckboxContainer.querySelectorAll('.class-group-header');

                        studentItems.forEach(label => {
                            const shouldShow = (selectedClassId === '' || label.dataset.classId === selectedClassId);
                            label.style.display = shouldShow ? 'block' : 'none';
                        });

                        classHeaders.forEach(header => {
                            const classId = header.querySelector('.select-all-class').dataset.classId;
                            const shouldShow = (selectedClassId === '' || classId === selectedClassId);
                            header.style.display = shouldShow ? 'flex' : 'none';
                        });

                        clearTimeout(classWIDFetchDebounceTimer);
                        classWIDFetchDebounceTimer = setTimeout(updateWIDListForClassSection, 500);
                    });
                }

                showClassResultsBtn.addEventListener('click', async () => {
                    const selectedStudents = Array.from(classStudentCheckboxContainer.querySelectorAll('.checkbox-item input[type="checkbox"]:checked'))
                        .filter(cb => cb.closest('.checkbox-item').style.display !== 'none')
                        .map(cb => cb.value);

                    const selectedWids = Array.from(classQuestionCheckboxContainer.querySelectorAll('.checkbox-item input[type="checkbox"]:checked')).map(cb => cb.value);

                    if (selectedStudents.length === 0) return alert('学習者を1名以上選択してください。');

                    classResultsContainer.innerHTML = '<p class="loading">結果を読み込んでいます...</p>';
                    try {
                        const results = await fetchData({
                            action: 'get_class_results',
                            student_ids: JSON.stringify(selectedStudents),
                            wids: JSON.stringify(selectedWids),
                            correctness: classCorrectnessFilter.value,
                            hesitation: classHesitationFilter.value
                        });
                        renderClassResults(results);
                    } catch (error) {
                        console.error('Error fetching class results:', error);
                        classResultsContainer.innerHTML = '<p class="error">結果の読み込みに失敗しました。</p>';
                    }
                });
                updateWIDListForClassSection();
            }

            // --- 3. テストごとの結果表示 ---
            if (testSelect) {
                testSelect.addEventListener('change', async function () {
                    const testId = this.value;
                    studentCheckboxContainer.innerHTML = '';
                    testQuestionCheckboxContainer.innerHTML = '';
                    testControls.style.display = 'none';
                    testFilters.style.display = 'none';
                    testResultsContainer.innerHTML = '<p>テストを選択してください。</p>';
                    currentTestData = [];
                    if (!testId) return;
                    studentCheckboxContainer.innerHTML = '<p class="loading">受験者を読み込んでいます...</p>';
                    try {
                        const students = await fetchData({ action: 'get_students_for_test', test_id: testId });
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
                            wids: JSON.stringify(selectedWids),
                            correctness: testCorrectnessFilter.value,
                            hesitation: testHesitationFilter.value
                        });
                        renderTestResults(results);
                    } catch (error) {
                        testResultsContainer.innerHTML = '<p class="error">結果の読み込みに失敗しました。</p>';
                    }
                });
            }

            // --- 4. 学習者ごとの詳細結果 ---
            if (studentSelect) {
                studentSelect.addEventListener('change', async function () {
                    const studentId = this.value;
                    studentDetailsContainer.innerHTML = '<p>学習者を選択すると、解答した問題リストが表示されます。</p>';
                    questionCheckboxContainerStudent.innerHTML = '';
                    grammarAnalysisWrapper.style.display = 'none';
                    grammarAnalysisWrapper.innerHTML = '';
                    studentControls.style.display = 'none';
                    studentFilters.style.display = 'none';
                    currentStudentDetailsData = [];

                    if (!studentId) return;

                    questionCheckboxContainerStudent.innerHTML = '<p class="loading">問題リストと分析データを読み込んでいます...</p>';
                    try {
                        const data = await fetchData({ action: 'get_student_details', student_id: studentId });
                        renderGrammarAnalysis(data.grammar_stats, data.student_levels);
                        renderCheckboxes(questionCheckboxContainerStudent, data.all_questions, 'question', '問題');
                        if (data.all_questions && data.all_questions.length > 0) {
                            studentControls.style.display = 'block';
                            studentFilters.style.display = 'flex';
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
                            wids: JSON.stringify(selectedWids),
                            correctness: studentCorrectnessFilter.value,
                            hesitation: studentHesitationFilter.value
                        });
                        renderStudentProblemResults(data, studentId);
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
                const response = await fetch('teachertrue.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`Network response was not ok, status: ${response.status}`);
                return await response.json();
            }

            async function handleStudentSelectionChangeForTest() {
                const selectedStudents = studentCheckboxContainer.querySelectorAll('input:checked');
                const testId = testSelect.value;
                if (selectedStudents.length > 0) {
                    testQuestionCheckboxContainer.style.display = 'block';
                    testFilters.style.display = 'flex';
                    testQuestionCheckboxContainer.innerHTML = '<p class="loading">問題リストを読み込んでいます...</p>';
                    try {
                        const questions = await fetchData({ action: 'get_questions_for_test', test_id: testId });
                        renderCheckboxes(testQuestionCheckboxContainer, questions, 'question', '3. 問題を選択:');
                        testControls.style.display = 'block';
                    } catch (error) {
                        testQuestionCheckboxContainer.innerHTML = '<p class="error">問題リストの読み込みに失敗しました。</p>';
                    }
                } else {
                    testQuestionCheckboxContainer.style.display = 'none';
                    testQuestionCheckboxContainer.innerHTML = '';
                    testControls.style.display = 'none';
                    testFilters.style.display = 'none';
                }
            }

            function renderCheckboxes(container, items, type, title) {
                if (!items || items.length === 0) {
                    container.innerHTML = `<p>対象の${type === 'student' ? '学習者' : '問題'}はありません。</p>`;
                    return;
                }
                let infoPopupHtml = '';
                if (title.includes('学習者を選択')) {
                    infoPopupHtml = `
                <span class="info-icon">i
                    <div class="info-popup" style="width: 280px; text-align: left;">
                        <strong>アスタリスク（*）について</strong>
                        <p style="margin: 5px 0 0 0; font-style: normal;">
                            学習者名の横にアスタリスクが表示されている場合、その学習者はまだこのテストを解答していません。
                        </p>
                    </div>
                </span>`;
                }
                const idKey = type === 'student' ? 'uid' : 'WID';
                const nameKey = type === 'student' ? 'Name' : 'Sentence';
                let checkboxesHtml = `<h4>${title} ${infoPopupHtml}</h4>
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

                container.querySelector('.select-all').addEventListener('change', function (e) {
                    container.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = e.target.checked);
                    if (container === studentCheckboxContainer) handleStudentSelectionChangeForTest();
                });
                if (container === studentCheckboxContainer) handleStudentSelectionChangeForTest();
            }

            function sortData(data, column, direction) {
                const sortedData = [...data].sort((a, b) => {
                    let valA = a[column];
                    let valB = b[column];
                    let result = 0;

                    switch (column) {
                        case 'hesitation':
                            const hesitationOrder = { '迷い有り': 1, '迷い無し': 2, '未推定': 3, '-': 4 };
                            result = (hesitationOrder[valA] || 99) - (hesitationOrder[valB] || 99);
                            break;
                        case 'correctness':
                            const correctnessOrder = { '正解': 1, '不正解': 2, '未解答': 3 };
                            result = (correctnessOrder[valA] || 99) - (correctnessOrder[valB] || 99);
                            break;
                        case 'date':
                            const isValADate = valA !== '-';
                            const isValBDate = valB !== '-';
                            if (isValADate && !isValBDate) { result = -1; }
                            else if (!isValADate && isValBDate) { result = 1; }
                            else if (!isValADate && !isValBDate) { result = 0; }
                            else { result = new Date(valB) - new Date(valA); }
                            break;
                        case 'test_id':
                        case 'test_name':
                            result = b.test_id - a.test_id;
                            break;
                        case 'ClassName':
                            result = (a.ClassID || 0) - (b.ClassID || 0);
                            break;
                        case 'student_name':
                            result = (a.student_id || 0) - (b.student_id || 0);
                            break;
                        case 'WID':
                            result = (a.WID || 0) - (b.WID || 0);
                            break;
                        default:
                            if (valA < valB) result = -1;
                            if (valA > valB) result = 1;
                            break;
                    }
                    return result * (direction === 'asc' ? 1 : -1);
                });
                return sortedData;
            }

            function renderClassResults(data) {
                currentClassData = data;
                currentClassSort = { column: null, direction: 'asc' };
                renderClassTable();
            }
            function renderClassTable() {
                const container = classResultsContainer;
                const dataToSort = currentClassSort.column ? sortData(currentClassData, currentClassSort.column, currentClassSort.direction) : currentClassData;

                if (!dataToSort || dataToSort.length === 0) {
                    container.innerHTML = '<p>選択された条件に合致する解答結果はありません。</p>'; return;
                }

                let tableHtml = `<table><thead><tr>
                <th data-sort="ClassName">グループ(クラス)名</th>
                <th data-sort="student_name">学習者名 (ID)</th>
                <th data-sort="test_name">テスト名</th>
                <th data-sort="WID">問題ID (回数)</th>
                <th data-sort="correctness">正誤</th>
                <th data-sort="hesitation">迷い推定</th>
                <th data-sort="date">解答日時</th>
                <th>軌跡再現</th>
            </tr></thead><tbody>`;

                dataToSort.forEach(row => {
                    tableHtml += `<tr>
                    <td>${row.ClassName}</td>
                    <td>${row.student_name} (${row.student_id})</td>
                    <td>${row.test_name}</td>
                    <td>${row.WID} (${row.attempt}回目)</td>
                    <td class="${row.correctness === '不正解' ? 'incorrect' : ''}">${row.correctness}</td>
                    <td class="${row.hesitation === '迷い有り' ? 'hesitation-yes' : ''}">${row.hesitation}</td>
                    <td>${row.date}</td>
                    <td><a href="../mousemove/mousemove.php?UID=${row.student_id}&WID=${row.WID}&test_id=${row.test_id}&LogID=${row.attempt}" target="_blank" class="link-button">表示</a></td>
                </tr>`;
                });
                container.innerHTML = tableHtml + '</tbody></table>';
                updateSortHeaders(container, currentClassSort);
            }

            function renderTestResults(data) {
                currentTestData = data;
                currentTestSort = { column: null, direction: 'asc' };
                renderTestTable();
            }
            function renderTestTable() {
                const container = testResultsContainer;
                const dataToSort = currentTestSort.column ? sortData(currentTestData, currentTestSort.column, currentTestSort.direction) : currentTestData;

                if (!dataToSort || dataToSort.length === 0) {
                    container.innerHTML = '<p>該当する解答結果はありません。</p>';
                    return;
                }

                let tableHtml = `<table><thead><tr>
            <th data-sort="student_name">学習者名 (ID)</th>
            <th data-sort="WID">問題ID</th>
            <th data-sort="correctness">正誤</th>
            <th data-sort="hesitation">迷い推定</th>
            <th data-sort="date">解答日時</th>
            <th>軌跡再現</th>
        </tr></thead><tbody>`;

                dataToSort.forEach(row => {
                    const isUnanswered = row.correctness === '未解答';
                    tableHtml += `<tr>
                <td>${row.student_name} (${row.student_id})</td>
                <td>${row.WID}</td>
                <td class="${isUnanswered ? '' : (row.correctness === '不正解' ? 'incorrect' : '')}">${row.correctness}</td>
                <td class="${isUnanswered ? '' : (row.hesitation === '迷い有り' ? 'hesitation-yes' : '')}">${row.hesitation}</td>
                <td>${row.date}</td>
                <td>${isUnanswered ? '-' : `<a href="../mousemove/mousemove.php?UID=${row.student_id}&WID=${row.WID}&test_id=${testSelect.value}&LogID=${row.attempt}" target="_blank" class="link-button">表示</a>`}</td>
            </tr>`;
                });
                container.innerHTML = tableHtml + '</tbody></table>';
                updateSortHeaders(container, currentTestSort);
            }

            function renderStudentProblemResults(data, studentId) {
                currentStudentDetailsData = data.attempts || [];
                currentStudentDetailsSort = { column: null, direction: 'asc' };
                renderStudentDetailsTable(data, studentId);
            }
            function renderStudentDetailsTable(data = null, studentId = null) {
                const container = studentDetailsContainer;
                const dataToSort = currentStudentDetailsSort.column ? sortData(currentStudentDetailsData, currentStudentDetailsSort.column, currentStudentDetailsSort.direction) : currentStudentDetailsData;

                let detailsHtml = '';
                if (data && data.summary) {
                    const infoPopupHtml = `<span class="info-icon">i<div class="info-popup"><strong>各指標の説明</strong><ul><li><strong>総解答数:</strong> 選択された問題において、この学習者が解答した総数です。</li><li><strong>正答率:</strong> 選択された問題における正解の割合です。</li><li><strong>迷い率:</strong> 選択された問題のうち、推定結果が「迷い有り」または「迷い無し」の問題における「迷い有り」の割合です。（「未推定」は計算から除外）</li></ul></div></span>`;
                    detailsHtml += `<div class="student-summary"><h4>総合評価 ${infoPopupHtml}</h4><p><strong>総解答数 (選択問題):</strong> ${data.summary.total_attempts}</p><p><strong>正答率 (選択問題):</strong> ${data.summary.accuracy}</p><p><strong>迷い率 (選択問題):</strong> ${data.summary.hesitation_rate}</p></div><h4>問題ごとの結果</h4>`;
                } else {
                    const summaryNode = container.querySelector('.student-summary');
                    if (summaryNode) {
                        detailsHtml += summaryNode.outerHTML + '<h4>問題ごとの結果</h4>';
                    }
                }

                if (!dataToSort || dataToSort.length === 0) {
                    detailsHtml += '<p>選択された条件に合致する解答履歴はありません。</p>';
                } else {
                    detailsHtml += `<table><thead><tr>
                <th data-sort="WID">問題ID</th>
                <th data-sort="test_name">テスト名</th>
                <th data-sort="correctness">正誤</th>
                <th data-sort="hesitation">迷い推定</th>
                <th data-sort="date">解答日時</th>
                <th>軌跡再現</th>
            </tr></thead><tbody>`;
                    dataToSort.forEach(attempt => {
                        const testName = attempt.test_name || '（不明なテスト）';
                        const currentStudentId = studentId || studentSelect.value;
                        detailsHtml += `<tr>
                    <td>${attempt.WID} (${attempt.attempt}回目)</td>
                    <td>${testName}</td>
                    <td class="${attempt.correctness === '不正解' ? 'incorrect' : ''}">${attempt.correctness}</td>
                    <td class="${attempt.hesitation === '迷い有り' ? 'hesitation-yes' : ''}">${attempt.hesitation}</td>
                    <td>${attempt.date}</td>
                    <td><a href="../mousemove/mousemove.php?UID=${currentStudentId}&WID=${attempt.WID}&test_id=${attempt.test_id}&LogID=${attempt.attempt}" target="_blank" class="link-button">表示</a></td>
                </tr>`;
                    });
                    detailsHtml += '</tbody></table>';
                }
                container.innerHTML = detailsHtml;
                updateSortHeaders(container, currentStudentDetailsSort);
            }

            function updateSortHeaders(container, sortState) {
                container.querySelectorAll('th[data-sort]').forEach(th => {
                    th.classList.remove('sort-asc', 'sort-desc');
                    if (th.dataset.sort === sortState.column) {
                        th.classList.add(`sort-${sortState.direction}`);
                    }
                });
            }

            function handleSort(e, sortState, renderFunc) {
                const th = e.target.closest('th[data-sort]');
                if (!th) return;

                const column = th.dataset.sort;
                let direction = 'asc';

                if (sortState.column === column) {
                    direction = sortState.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    direction = ['date', 'test_name', 'test_id'].includes(column) ? 'desc' : 'asc';
                }

                sortState.column = column;
                sortState.direction = direction;

                renderFunc();
            }

            classResultsContainer.addEventListener('click', (e) => handleSort(e, currentClassSort, renderClassTable));
            testResultsContainer.addEventListener('click', (e) => handleSort(e, currentTestSort, renderTestTable));
            studentDetailsContainer.addEventListener('click', (e) => handleSort(e, currentStudentDetailsSort, () => renderStudentDetailsTable()));

            function renderGrammarAnalysis(grammarStats, studentLevels) {
                grammarAnalysisWrapper.style.display = 'block';

                const grammarInfoPopupHtml = `
            <span class="info-icon">i
                <div class="info-popup">
                    <strong>各指標の説明</strong>
                    <ul>
                        <li><strong>迷い率:</strong> 推定結果が「迷い有り」または「迷い無し」の問題における「迷い有り」の割合です。（「未推定」は計算から除外）</li>
                        <li><strong>総解答数について:</strong> 1つの問題に複数の文法項目が関連付けられている場合があるため、この表の総解答数の合計は、問題ごとの解答総数と一致しないことがあります。</li>
                    </ul>
                </div>
            </span>`;

                let levelsHtml = '';
                if (studentLevels && (studentLevels.toeic || studentLevels.eiken)) {
                    const eikenMap = { '1': '1級', 'pre1': '準1級', '2': '2級', 'pre2': '準2級', '3': '3級', '4': '4級', '5': '5級' };
                    levelsHtml += '<div class="student-levels">';
                    if (studentLevels.toeic) levelsHtml += `<span class="level-item"><strong>TOEIC:</strong> ${studentLevels.toeic}点台</span>`;
                    if (studentLevels.eiken) levelsHtml += `<span class="level-item"><strong>英検:</strong> ${eikenMap[studentLevels.eiken] || studentLevels.eiken}</span>`;
                    levelsHtml += '</div>';
                }

                let grammarHtml = `<h4>文法項目ごとの分析 ${grammarInfoPopupHtml}</h4>${levelsHtml}`;

                if (!grammarStats || grammarStats.length === 0) {
                    grammarAnalysisWrapper.innerHTML = grammarHtml + '<p>この学習者の文法分析データはありません。</p>';
                    return;
                }

                grammarHtml += `<div class="grammar-analysis-container"><div class="grammar-table-container">
                <table><thead><tr><th>文法項目</th><th>総解答数</th><th>正解数</th><th>迷い数</th><th>正解率</th><th>迷い率</th></tr></thead><tbody>`;
                grammarStats.forEach(stat => {
                    grammarHtml += `<tr>
                    <td>${stat.grammar_name}</td>
                    <td>${stat.total_attempts}</td>
                    <td>${stat.correct_count}</td>
                    <td>${stat.hesitated_count}</td>
                    <td>${stat.correct_rate.toFixed(2)}%</td>
                    <td>${stat.hesitation_rate.toFixed(2)}%</td>
                </tr>`;
                });
                grammarHtml += `</tbody></table></div><div class="grammar-chart-container"><canvas id="grammarAnalysisChart"></canvas></div></div>`;
                grammarAnalysisWrapper.innerHTML = grammarHtml;

                const ctx = document.getElementById('grammarAnalysisChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: grammarStats.map(s => s.grammar_name),
                        datasets: [{
                            label: '正解率 (%)',
                            data: grammarStats.map(s => s.correct_rate.toFixed(2)),
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
                        plugins: { title: { display: true, text: '文法項目ごとの正解率と迷い率' } },
                        scales: {
                            x: { title: { display: true, text: '文法項目' } },
                            y: { title: { display: true, text: '割合 (%)' }, min: 0, max: 100 }
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>

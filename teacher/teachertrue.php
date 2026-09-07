<?php
// セッションを開始し、多言語対応とデータベース接続を読み込みます
include '../lang.php';
require "../dbc.php";
ob_start();
require_once __DIR__ . '/student-feature-tooltip.php';
ob_end_clean();

// ログイン中の教師IDを取得します
$teacher_id = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;

function teacher_result_normalize_ids(array $values): array
{
    $normalized = [];
    foreach ($values as $value) {
        if (!is_scalar($value) || !preg_match('/^\d+$/', trim((string)$value))) {
            continue;
        }
        $normalized[] = (string)((int)$value);
    }
    return array_values(array_unique($normalized));
}

function teacher_result_filter_students(mysqli $conn, string $teacher_id, array $student_ids): array
{
    $normalized = teacher_result_normalize_ids($student_ids);
    if (empty($normalized)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($normalized), '?'));
    $types = 's' . str_repeat('i', count($normalized));
    $params = array_merge([$teacher_id], array_map('intval', $normalized));
    $stmt = $conn->prepare("SELECT DISTINCT s.uid FROM students s JOIN ClassTeacher ct ON s.ClassID = ct.ClassID WHERE ct.TID = ? AND s.uid IN ($placeholders)");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed = [];
    while ($row = $result->fetch_assoc()) {
        $allowed[] = (string)$row['uid'];
    }
    $stmt->close();
    return $allowed;
}

function teacher_result_test_context(mysqli $conn, string $teacher_id, int $test_id): ?array
{
    $stmt = $conn->prepare('SELECT id, target_type, target_group FROM tests WHERE id = ? AND teacher_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $test_id, $teacher_id);
    $stmt->execute();
    $test = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $test;
}

function teacher_result_test_students(mysqli $conn, string $teacher_id, array $test, array $requested_ids = []): array
{
    $target_group = (int)$test['target_group'];
    if ($test['target_type'] === 'class') {
        $stmt = $conn->prepare('SELECT DISTINCT s.uid FROM students s JOIN ClassTeacher ct ON s.ClassID = ct.ClassID WHERE s.ClassID = ? AND ct.TID = ?');
        $stmt->bind_param('is', $target_group, $teacher_id);
    } else {
        $stmt = $conn->prepare('SELECT DISTINCT s.uid FROM `groups` g JOIN group_members gm ON g.group_id = gm.group_id JOIN students s ON gm.uid = s.uid JOIN ClassTeacher ct ON s.ClassID = ct.ClassID WHERE g.group_id = ? AND g.TID = ? AND ct.TID = ?');
        $stmt->bind_param('iss', $target_group, $teacher_id, $teacher_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed = [];
    while ($row = $result->fetch_assoc()) {
        $allowed[] = (string)$row['uid'];
    }
    $stmt->close();
    if (empty($requested_ids)) {
        return $allowed;
    }
    $lookup = array_fill_keys($allowed, true);
    return array_values(array_filter(teacher_result_normalize_ids($requested_ids), static fn(string $uid): bool => isset($lookup[$uid])));
}

function teacher_result_test_wids(mysqli $conn, int $test_id, array $requested_wids = []): array
{
    $stmt = $conn->prepare('SELECT DISTINCT WID FROM test_questions WHERE test_id = ? ORDER BY OID, WID');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $test_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed = [];
    while ($row = $result->fetch_assoc()) {
        $allowed[] = (string)$row['WID'];
    }
    $stmt->close();
    if (empty($requested_wids)) {
        return $allowed;
    }
    $lookup = array_fill_keys($allowed, true);
    return array_values(array_filter(teacher_result_normalize_ids($requested_wids), static fn(string $wid): bool => isset($lookup[$wid])));
}

function teacher_result_student_wids(mysqli $conn, string $student_id, array $requested_wids): array
{
    if (empty($requested_wids)) {
        return [];
    }
    $stmt = $conn->prepare('SELECT DISTINCT WID FROM linedata WHERE UID = ?');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed = [];
    while ($row = $result->fetch_assoc()) {
        $allowed[(string)$row['WID']] = true;
    }
    $stmt->close();
    return array_values(array_filter(teacher_result_normalize_ids($requested_wids), static fn(string $wid): bool => isset($allowed[$wid])));
}

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
            $student_ids = is_array($student_ids) ? teacher_result_filter_students($conn, (string)$teacher_id, $student_ids) : [];
            $wids = isset($_POST['wids']) && !empty($_POST['wids']) ? json_decode($_POST['wids']) : [];
            $wids = is_array($wids) ? teacher_result_normalize_ids($wids) : [];
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
            $student_ids = is_array($student_ids) ? teacher_result_filter_students($conn, (string)$teacher_id, $student_ids) : [];
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
            $test_id = (int)$_POST['test_id'];
            $assigned_students = [];

            $test_info = teacher_result_test_context($conn, (string)$teacher_id, $test_id);

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
            $test_id = (int)$_POST['test_id'];
            if (teacher_result_test_context($conn, (string)$teacher_id, $test_id)) {
                $stmt = $conn->prepare(
                    "SELECT tq.WID, qi.Sentence
                     FROM test_questions tq
                     LEFT JOIN question_info qi ON tq.WID = qi.WID
                     WHERE tq.test_id = ? ORDER BY tq.OID, tq.WID"
                );
                $stmt->bind_param("i", $test_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc())
                    $response[] = $row;
                $stmt->close();
            }
        }

        elseif ($_POST['action'] === 'get_questions_for_student' && isset($_POST['student_id'])) {
            $allowed_students = teacher_result_filter_students($conn, (string)$teacher_id, [$_POST['student_id']]);
            if (!empty($allowed_students)) {
                $student_id = $allowed_students[0];
                $stmt = $conn->prepare(
                    "SELECT DISTINCT l.WID, q.Sentence
                     FROM linedata l
                     LEFT JOIN question_info q ON l.WID = q.WID
                     WHERE l.UID = ? ORDER BY l.WID"
                );
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc())
                    $response[] = $row;
                $stmt->close();
            }
        }

        elseif ($_POST['action'] === 'get_test_results' && isset($_POST['test_id'], $_POST['student_ids'], $_POST['wids'])) {
            $student_ids = json_decode($_POST['student_ids']);
            $wids = json_decode($_POST['wids']);
            $test_id = (int)$_POST['test_id'];
            $test_context = teacher_result_test_context($conn, (string)$teacher_id, $test_id);
            $student_ids = $test_context && is_array($student_ids)
                ? teacher_result_test_students($conn, (string)$teacher_id, $test_context, $student_ids)
                : [];
            $wids = $test_context && is_array($wids) ? teacher_result_test_wids($conn, $test_id, $wids) : [];
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
                $params = array_merge([$teacher_id, $test_id], $student_ids, $wids);

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
            $allowed_students = teacher_result_filter_students($conn, (string)$teacher_id, [$_POST['student_id']]);
            if (empty($allowed_students)) {
                http_response_code(403);
                echo json_encode(['error' => '対象学習者を確認できません。'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $student_id = $allowed_students[0];
            $wids = isset($_POST['wids']) ? json_decode($_POST['wids']) : [];
            $wids = is_array($wids) ? teacher_result_student_wids($conn, $student_id, $wids) : [];
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
            $all_attempts = [];
            if (!empty($wids)) {
                $grammar_placeholders = implode(',', array_fill(0, count($wids), '?'));
                $grammar_sql = "SELECT l.WID, l.TF, l.attempt, l.test_id, qi.grammar, tr.Understand
                    FROM linedata l
                    JOIN question_info qi ON l.WID = qi.WID
                    LEFT JOIN temporary_results tr ON l.UID = tr.UID AND l.WID = tr.WID AND l.attempt = tr.attempt AND tr.teacher_id = ?
                    WHERE l.UID = ? AND l.WID IN ($grammar_placeholders)";
                $grammar_params = array_merge([$teacher_id, $student_id], $wids);
                $grammar_types = 'ss' . str_repeat('i', count($wids));
                if ($correctness_filter === 'correct') {
                    $grammar_sql .= ' AND l.TF = 1';
                } elseif ($correctness_filter === 'incorrect') {
                    $grammar_sql .= ' AND l.TF = 0';
                }
                if ($hesitation_filter === 'hesitated') {
                    $grammar_sql .= ' AND tr.Understand = 2';
                } elseif ($hesitation_filter === 'not_hesitated') {
                    $grammar_sql .= ' AND tr.Understand = 4';
                } elseif ($hesitation_filter === 'not_estimated') {
                    $grammar_sql .= ' AND (tr.Understand IS NULL OR tr.Understand NOT IN (2, 4))';
                }
                $grammar_sql .= ' ORDER BY l.WID, l.attempt';
                $raw_data_stmt = $conn->prepare($grammar_sql);
                $raw_data_stmt->bind_param($grammar_types, ...$grammar_params);
                $raw_data_stmt->execute();
                $all_attempts = $raw_data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $raw_data_stmt->close();
            }
            $temp_grammar_stats = [];
            foreach ($all_attempts as $attempt) {
                if (!empty($attempt['grammar'])) {
                    $grammar_ids = explode('#', trim($attempt['grammar'], '#'));
                    foreach ($grammar_ids as $gid) {
                        if (empty($gid) || !isset($gid_map[$gid]))
                            continue;
                        $grammar_name = $gid_map[$gid];
                        if (!isset($temp_grammar_stats[$grammar_name])) {
                            $temp_grammar_stats[$grammar_name] = [
                                'total' => 0,
                                'correct' => 0,
                                'hesitated' => 0,
                                'estimated' => 0,
                                'correct_hesitated_attempts' => [],
                                'incorrect_not_hesitated_attempts' => [],
                                'incorrect_hesitated_attempts' => []
                            ];
                        }
                        $temp_grammar_stats[$grammar_name]['total']++;
                        if ($attempt['TF'] == 1)
                            $temp_grammar_stats[$grammar_name]['correct']++;
                        if ($attempt['Understand'] == 2) {
                            $temp_grammar_stats[$grammar_name]['hesitated']++;
                        }
                        if (in_array($attempt['Understand'], [2, 4]))
                            $temp_grammar_stats[$grammar_name]['estimated']++;
                        $attempt_link_data = [
                            'WID' => $attempt['WID'],
                            'attempt' => $attempt['attempt'],
                            'test_id' => $attempt['test_id']
                        ];
                        if ((int)$attempt['TF'] === 1 && (int)$attempt['Understand'] === 2) {
                            $temp_grammar_stats[$grammar_name]['correct_hesitated_attempts'][] = $attempt_link_data;
                        } elseif ((int)$attempt['TF'] === 0 && (int)$attempt['Understand'] === 4) {
                            $temp_grammar_stats[$grammar_name]['incorrect_not_hesitated_attempts'][] = $attempt_link_data;
                        } elseif ((int)$attempt['TF'] === 0 && (int)$attempt['Understand'] === 2) {
                            $temp_grammar_stats[$grammar_name]['incorrect_hesitated_attempts'][] = $attempt_link_data;
                        }
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
                    'correct_hesitated_attempts' => $stats['correct_hesitated_attempts'],
                    'incorrect_not_hesitated_attempts' => $stats['incorrect_not_hesitated_attempts'],
                    'incorrect_hesitated_attempts' => $stats['incorrect_hesitated_attempts'],
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
    <link rel="stylesheet" href="../style/teachertrue_styles.css?v=<?= filemtime(__DIR__ . '/../style/teachertrue_styles.css') ?>">
    <link rel="stylesheet" href="../style/teacher_results_histogram.css?v=<?= filemtime(__DIR__ . '/../style/teacher_results_histogram.css') ?>">
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

                <details class="grades-section grades-section-collapsible" id="class-results-section">
                    <summary class="grades-section-summary">担当グループ(クラス)学習者の結果表示</summary>
                    <div class="grades-section-collapsible-body">
                    <div id="class-results-histogram" aria-label="担当グループ（クラス）の問題(WID)・学習者(UID)検索"></div>
                    <div id="class-results-container" class="results-container">
                        <p>問題(WID)、学習者(UID)の順に選択して結果を表示してください。</p>
                    </div>
                    <section id="class-student-details-workspace" class="student-detail-workspace" hidden>
                        <div class="student-detail-workspace-heading">
                            <div>
                                <h4>絞り込んだ学習者の詳細結果</h4>
                                <p>学習者を選び、問題(WID)をチェックボックスまたはヒストグラムで選択してください。表示欄は追加して並べられます。</p>
                            </div>
                            <button type="button" id="add-student-detail" class="action-button">＋ 学習者表示を追加</button>
                        </div>
                        <div id="student-detail-slots" class="student-detail-slots"></div>
                    </section>
                    </div>
                </details>

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
                        <div id="test-results-histogram" aria-label="テスト結果の問題(WID)・学習者(UID)検索"></div>
                        <div id="test-results-container" class="results-container">
                            <p>テストを選択してください。</p>
                        </div>
                    <?php endif; ?>
                </div>

            </section>
        </main>
    </div>

    <script src="teacher-results-histogram.js?v=<?= filemtime(__DIR__ . '/teacher-results-histogram.js') ?>"></script>
    <script src="teacher-student-wid-analysis.js?v=<?= filemtime(__DIR__ . '/teacher-student-wid-analysis.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // 要素の取得
            // 「担当クラス」の要素
            const classResultsContainer = document.getElementById('class-results-container');

            // 「テストごと」の要素
            const testSelect = document.getElementById('test-select');
            const testResultsContainer = document.getElementById('test-results-container');

            // 絞り込み結果に統合した学習者詳細
            const studentDetailWorkspace = document.getElementById('class-student-details-workspace');
            const studentDetailSlots = document.getElementById('student-detail-slots');
            const addStudentDetailButton = document.getElementById('add-student-detail');
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            }[character]));

            let currentClassData = [];
            let currentClassSort = { column: null, direction: 'asc' };

            let currentTestData = [];
            let currentTestSort = { column: null, direction: 'asc' };

            let detailSlotSequence = 0;
            let detailCandidates = [];
            const detailAnalyses = new Map();
            const detailCharts = new Map();
            const logicFilterGroups = <?= json_encode($logic_filter_groups, JSON_UNESCAPED_UNICODE) ?>;
            const logicFilterStudentsByGroup = <?= json_encode((object)$logic_filter_students_by_group, JSON_UNESCAPED_UNICODE) ?>;
            const resultHistogramFeatures = <?= json_encode(student_feature_columns(), JSON_UNESCAPED_UNICODE) ?>;
            const resultHistogramFeatureMeta = <?= json_encode(feature_display_metadata(array_keys(student_feature_columns())), JSON_UNESCAPED_UNICODE) ?>;
            const histogramBaseOptions = {
                features: resultHistogramFeatures,
                featureMeta: resultHistogramFeatureMeta,
                groups: logicFilterGroups,
                groupStudents: logicFilterStudentsByGroup
            };

            const classResultsHistogram = window.TeacherResultsHistogram?.create({
                ...histogramBaseOptions,
                root: '#class-results-histogram',
                scope: 'class',
                onSubmit: async ({ uids, wids, correctness, hesitation }) => {
                    clearStudentDetailWorkspace();
                    classResultsContainer.innerHTML = '<p class="loading">選択条件の結果を読み込んでいます...</p>';
                    try {
                        const results = await fetchData({
                            action: 'get_class_results',
                            student_ids: JSON.stringify(uids),
                            wids: JSON.stringify(wids),
                            correctness,
                            hesitation
                        });
                        renderClassResults(results);
                    } catch (error) {
                        classResultsContainer.innerHTML = '<p class="error">結果の読み込みに失敗しました。</p>';
                    }
                }
            });

            const testResultsHistogram = window.TeacherResultsHistogram?.create({
                ...histogramBaseOptions,
                root: '#test-results-histogram',
                scope: 'test',
                getTestId: () => testSelect?.value || '',
                onSubmit: async ({ uids, wids, correctness, hesitation }) => {
                    const testId = testSelect?.value || '';
                    if (!testId) return alert('テストを選択してください。');
                    testResultsContainer.innerHTML = '<p class="loading">選択条件の結果を読み込んでいます...</p>';
                    try {
                        const results = await fetchData({
                            action: 'get_test_results',
                            test_id: testId,
                            student_ids: JSON.stringify(uids),
                            wids: JSON.stringify(wids),
                            correctness,
                            hesitation
                        });
                        renderTestResults(results);
                    } catch (error) {
                        testResultsContainer.innerHTML = '<p class="error">結果の読み込みに失敗しました。</p>';
                    }
                }
            });

            // --- 2. テストごとの結果表示 ---
            if (testSelect) {
                testSelect.addEventListener('change', function () {
                    const testId = this.value;
                    testResultsHistogram?.resetContext();
                    testResultsContainer.innerHTML = testId
                        ? '<p>問題(WID)、学習者(UID)の順に選択して結果を表示してください。</p>'
                        : '<p>テストを選択してください。</p>';
                    currentTestData = [];
                });
            }

            function clearStudentDetailWorkspace() {
                detailAnalyses.forEach((analysis) => analysis?.clear());
                detailCharts.forEach((chart) => chart?.destroy());
                detailAnalyses.clear();
                detailCharts.clear();
                detailCandidates = [];
                if (studentDetailSlots) studentDetailSlots.innerHTML = '';
                if (studentDetailWorkspace) studentDetailWorkspace.hidden = true;
            }

            function removeStudentDetailSlot(slotId) {
                const slotCards = studentDetailSlots ? studentDetailSlots.querySelectorAll('[data-slot-id]') : [];
                if (slotCards.length <= 1) {
                    const card = studentDetailSlots?.querySelector(`[data-slot-id="${slotId}"]`);
                    detailAnalyses.get(slotId)?.clear();
                    detailCharts.get(slotId)?.destroy();
                    detailCharts.delete(slotId);
                    const select = card?.querySelector('[data-role="student-detail-select"]');
                    const root = card?.querySelector('[data-role="student-detail-analysis"]');
                    if (select) select.value = '';
                    if (root) {
                        root.hidden = false;
                        root.innerHTML = '<p>学習者を選択してください。</p>';
                    }
                    return;
                }
                detailAnalyses.get(slotId)?.clear();
                detailCharts.get(slotId)?.destroy();
                detailAnalyses.delete(slotId);
                detailCharts.delete(slotId);
                studentDetailSlots?.querySelector(`[data-slot-id="${slotId}"]`)?.remove();
            }

            function addStudentDetailSlot(preferredStudentId = '') {
                if (!studentDetailSlots || !detailCandidates.length) return;
                const slotId = `student-detail-${++detailSlotSequence}`;
                const initialId = String(preferredStudentId || '');
                const card = document.createElement('article');
                card.className = 'student-detail-card';
                card.dataset.slotId = slotId;
                card.innerHTML = `
                    <div class="student-detail-card-heading">
                        <label>詳細に表示する学習者(UID)
                            <select data-role="student-detail-select">
                                <option value="">-- 選択してください --</option>
                                ${detailCandidates.map((student) => `<option value="${escapeHtml(student.uid)}"${String(student.uid) === initialId ? ' selected' : ''}>${escapeHtml(student.name)}（学習者(UID): ${escapeHtml(student.uid)}）</option>`).join('')}
                            </select>
                        </label>
                        <button type="button" class="student-detail-remove" data-action="remove-detail" aria-label="この学習者表示を削除">×</button>
                    </div>
                    <div class="student-detail-analysis trh-root swha-root" data-role="student-detail-analysis"><p>学習者を選択してください。</p></div>`;
                studentDetailSlots.appendChild(card);

                const analysisRoot = card.querySelector('[data-role="student-detail-analysis"]');
                const analysis = window.StudentWidHistogramAnalysis?.create({
                    root: analysisRoot,
                    features: resultHistogramFeatures,
                    featureMeta: resultHistogramFeatureMeta,
                    onResolve: ({ studentId, wids, correctness, hesitation }) => fetchData({
                        action: 'get_student_details',
                        student_id: studentId,
                        wids: JSON.stringify(wids),
                        correctness,
                        hesitation
                    }),
                    onRender: ({ target, data, studentId, wids }) => renderStudentDetailResults(target, data, studentId, wids, slotId)
                });
                detailAnalyses.set(slotId, analysis);

                const select = card.querySelector('[data-role="student-detail-select"]');
                select.addEventListener('change', () => {
                    detailCharts.get(slotId)?.destroy();
                    detailCharts.delete(slotId);
                    analysis?.load(select.value);
                });
                card.querySelector('[data-action="remove-detail"]').addEventListener('click', () => removeStudentDetailSlot(slotId));
                if (initialId) analysis?.load(initialId);
            }

            function resetStudentDetailWorkspace(data) {
                clearStudentDetailWorkspace();
                const directory = new Map();
                (Array.isArray(data) ? data : []).forEach((row) => {
                    const uid = String(row.student_id ?? '');
                    if (uid && !directory.has(uid)) directory.set(uid, { uid, name: String(row.student_name || '氏名未登録') });
                });
                detailCandidates = [...directory.values()].sort((left, right) => left.uid.localeCompare(right.uid, 'ja', { numeric: true }));
                if (!detailCandidates.length || !studentDetailWorkspace) return;
                studentDetailWorkspace.hidden = false;
                addStudentDetailSlot();
            }

            addStudentDetailButton?.addEventListener('click', () => addStudentDetailSlot());
            
            // --- 4. 共通の描画・補助関数 ---
            async function fetchData(bodyObj) {
                const formData = new FormData();
                for (const key in bodyObj) formData.append(key, bodyObj[key]);
                const response = await fetch('teachertrue.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`Network response was not ok, status: ${response.status}`);
                return await response.json();
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
                resetStudentDetailWorkspace(data);
            }
            function renderClassTable() {
                const container = classResultsContainer;
                const dataToSort = currentClassSort.column ? sortData(currentClassData, currentClassSort.column, currentClassSort.direction) : currentClassData;

                if (!dataToSort || dataToSort.length === 0) {
                    container.innerHTML = '<p>選択された条件に合致する解答結果はありません。</p>'; return;
                }

                let tableHtml = `<table><thead><tr>
                <th data-sort="ClassName">グループ(クラス)名</th>
                <th data-sort="student_name">学習者名 / 学習者(UID)</th>
                <th data-sort="test_name">テスト名</th>
                <th data-sort="WID">問題(WID) (回数)</th>
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
            <th data-sort="student_name">学習者名 / 学習者(UID)</th>
            <th data-sort="WID">問題(WID)</th>
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

            function trajectoryLinks(attempts, studentId) {
                const rows = Array.isArray(attempts) ? attempts : [];
                if (!rows.length) return '<span class="grammar-category-empty">0問</span>';
                const questionCount = new Set(rows.map((attempt) => String(attempt.WID))).size;
                return `<div class="grammar-category-links"><strong>${questionCount}問</strong><div>${rows.map((attempt) => {
                    const params = new URLSearchParams({
                        UID: studentId,
                        WID: attempt.WID ?? '',
                        test_id: attempt.test_id ?? '',
                        LogID: attempt.attempt ?? ''
                    });
                    return `<a href="../mousemove/mousemove.php?${escapeHtml(params.toString())}" target="_blank" rel="noopener noreferrer" class="link-button">問題(WID): ${escapeHtml(attempt.WID)}（${escapeHtml(attempt.attempt)}回目）</a>`;
                }).join('')}</div></div>`;
            }

            function renderStudentDetailResults(target, data, studentId, selectedWids, slotId) {
                detailCharts.get(slotId)?.destroy();
                detailCharts.delete(slotId);
                const attempts = Array.isArray(data?.attempts) ? data.attempts : [];
                const grammarStats = Array.isArray(data?.grammar_stats) ? data.grammar_stats : [];
                const summary = data?.summary || {};
                const levels = data?.student_levels || {};
                const eikenMap = { '1': '1級', 'pre1': '準1級', '2': '2級', 'pre2': '準2級', '3': '3級', '4': '4級', '5': '5級' };
                const widLabel = selectedWids.length <= 20 ? selectedWids.join(', ') : `${selectedWids.slice(0, 20).join(', ')} ほか${selectedWids.length - 20}件`;
                let html = `<div class="student-summary"><h4>総合評価</h4>
                    <p><strong>対象問題(WID):</strong> ${escapeHtml(widLabel)}</p>
                    <p><strong>総解答数:</strong> ${escapeHtml(summary.total_attempts ?? 0)}</p>
                    <p><strong>正答率:</strong> ${escapeHtml(summary.accuracy ?? 'N/A')}</p>
                    <p><strong>迷い率:</strong> ${escapeHtml(summary.hesitation_rate ?? 'N/A')}</p></div>
                    <section class="student-problem-results"><h4>問題ごとの結果</h4>`;
                if (!attempts.length) {
                    html += '<p>選択された条件に合致する解答履歴はありません。</p>';
                } else {
                    html += `<div class="student-detail-table-wrap"><table><thead><tr><th>問題(WID)</th><th>テスト名</th><th>正誤</th><th>迷い推定</th><th>解答日時</th><th>軌跡再現</th></tr></thead><tbody>${attempts.map((attempt) => {
                        const params = new URLSearchParams({ UID: studentId, WID: attempt.WID ?? '', test_id: attempt.test_id ?? '', LogID: attempt.attempt ?? '' });
                        return `<tr><td>${escapeHtml(attempt.WID)}（${escapeHtml(attempt.attempt)}回目）</td><td>${escapeHtml(attempt.test_name || '（不明なテスト）')}</td><td class="${attempt.correctness === '不正解' ? 'incorrect' : ''}">${escapeHtml(attempt.correctness || '-')}</td><td class="${attempt.hesitation === '迷い有り' ? 'hesitation-yes' : ''}">${escapeHtml(attempt.hesitation || '-')}</td><td>${escapeHtml(attempt.date || '-')}</td><td><a href="../mousemove/mousemove.php?${escapeHtml(params.toString())}" target="_blank" rel="noopener noreferrer" class="link-button">表示</a></td></tr>`;
                    }).join('')}</tbody></table></div>`;
                }
                html += '</section><section class="student-grammar-results"><h4>文法項目ごとの分析</h4>';
                if (levels.toeic || levels.eiken) {
                    html += `<div class="student-levels">${levels.toeic ? `<span class="level-item"><strong>TOEIC:</strong> ${escapeHtml(levels.toeic)}点台</span>` : ''}${levels.eiken ? `<span class="level-item"><strong>英検:</strong> ${escapeHtml(eikenMap[levels.eiken] || levels.eiken)}</span>` : ''}</div>`;
                }
                if (!grammarStats.length) {
                    html += '<p>選択した問題には文法分析データがありません。</p></section>';
                    target.innerHTML = html;
                    return;
                }

                html += `<div class="grammar-analysis-container"><div class="grammar-table-container"><table><thead><tr>
                    <th>文法項目</th><th>総解答数</th><th>正解数</th><th>迷い数</th>
                    <th>正解かつ迷い有り</th><th>不正解かつ迷い無し</th><th>不正解かつ迷い有り</th>
                    <th>正解率</th><th>迷い率</th></tr></thead><tbody>${grammarStats.map((stat) => `<tr>
                    <td>${escapeHtml(stat.grammar_name)}</td><td>${escapeHtml(stat.total_attempts)}</td><td>${escapeHtml(stat.correct_count)}</td><td>${escapeHtml(stat.hesitated_count)}</td>
                    <td>${trajectoryLinks(stat.correct_hesitated_attempts, studentId)}</td>
                    <td>${trajectoryLinks(stat.incorrect_not_hesitated_attempts, studentId)}</td>
                    <td>${trajectoryLinks(stat.incorrect_hesitated_attempts, studentId)}</td>
                    <td>${Number(stat.correct_rate || 0).toFixed(2)}%</td><td>${Number(stat.hesitation_rate || 0).toFixed(2)}%</td></tr>`).join('')}</tbody></table></div>
                    <div class="grammar-chart-container"><canvas data-role="grammar-chart"></canvas></div></div></section>`;
                target.innerHTML = html;
                const canvas = target.querySelector('[data-role="grammar-chart"]');
                if (!canvas || typeof window.Chart === 'undefined') return;
                const chart = new window.Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: grammarStats.map((stat) => stat.grammar_name),
                        datasets: [
                            { label: '正解率 (%)', data: grammarStats.map((stat) => Number(stat.correct_rate || 0)), backgroundColor: 'rgba(54, 162, 235, 0.6)' },
                            { label: '迷い率 (%)', data: grammarStats.map((stat) => Number(stat.hesitation_rate || 0)), backgroundColor: 'rgba(255, 99, 132, 0.6)' }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { title: { display: true, text: '文法項目ごとの正解率と迷い率' } },
                        scales: { x: { title: { display: true, text: '文法項目' } }, y: { title: { display: true, text: '割合 (%)' }, min: 0, max: 100 } }
                    }
                });
                detailCharts.set(slotId, chart);
            }
        });
    </script>
</body>

</html>

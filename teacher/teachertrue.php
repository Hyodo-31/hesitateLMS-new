<?php
// セッションを開始し、多言語対応とデータベース接続を読み込みます
include '../lang.php';
require "../dbc.php";

// ログイン中の教師IDを取得します
$teacher_id = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;
$teacher_name = "先生"; // デフォルト名

if ($teacher_id) {
    // データベース定義に合わせて、teachersテーブルからTNameを取得します
    $stmt_teacher = $conn->prepare("SELECT TName FROM teachers WHERE TID = ?");
    if ($stmt_teacher) {
        // TIDはvarchar型なので、型指定を "s" (string) に修正
        $stmt_teacher->bind_param("s", $teacher_id);
        $stmt_teacher->execute();
        $result_teacher = $stmt_teacher->get_result();
        if ($row_teacher = $result_teacher->fetch_assoc()) {
            $teacher_name = htmlspecialchars($row_teacher['TName']);
        }
        $stmt_teacher->close();
    }
} else {
    // ログインしていない場合はログインページにリダイレクト
    // header('Location: ../login.php');
    // exit;
}

// --- AJAXリクエストの処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = [];

    // アクション: テストごとの結果を取得
    if ($_POST['action'] === 'get_test_results' && isset($_POST['test_id'])) {
        $test_id = $_POST['test_id'];
        
        // この例ではダミーデータを返します。
        // TODO: 将来的には、ここでデータベースから実際のテスト結果を取得する処理を実装する必要があります。
        $response = [
            ['student_id' => 101, 'student_name' => '学習者A', 'score' => 85, 'correctness' => '正解', 'hesitation' => '迷い有り', 'date' => '2025-09-26 10:30'],
            ['student_id' => 102, 'student_name' => '学習者B', 'score' => 92, 'correctness' => '正解', 'hesitation' => '迷い無し', 'date' => '2025-09-26 10:32'],
            ['student_id' => 103, 'student_name' => '学習者C', 'score' => 78, 'correctness' => '不正解', 'hesitation' => '未推定', 'date' => '2025-09-26 10:35'],
        ];
    }
    // アクション: 学習者ごとの詳細情報を取得
    elseif ($_POST['action'] === 'get_student_details' && isset($_POST['student_id'])) {
        $student_id = $_POST['student_id'];
        
        // この例ではダミーデータを返します。
        // TODO: 将来的には、ここでデータベースから実際の学習者データを取得する処理を実装する必要があります。
        $response = [
            'summary' => ['total_attempts' => 50, 'accuracy' => '88%', 'hesitation_rate' => '15%'],
            'attempts' => [
                ['wid' => 1, 'correctness' => '正解', 'hesitation' => '迷い無し', 'date' => '2025-09-25'],
                ['wid' => 2, 'correctness' => '不正解', 'hesitation' => '迷い有り', 'date' => '2025-09-24'],
                ['wid' => 3, 'correctness' => '正解', 'hesitation' => '未推定', 'date' => '2025-09-23'],
            ]
        ];
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
                            // contentの表示文字数を制限するなど、必要に応じて調整してください
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
                <h2>成績情報 (担当クラスのみ)</h2>
                
                <div class="grades-section">
                    <h3>テストごとの結果表示</h3>
                    <div class="controls">
                        <label for="test-select">テストを選択:</label>
                        <select id="test-select" name="test-select">
                            <option value="">-- 選択してください --</option>
                            <?php
                            if ($teacher_id) {
                                // 【修正点 1】: SQLクエリをデータベースの定義に合わせました。
                                // (変更前) SELECT id, testname FROM tests WHERE TID = ?
                                // (変更後) SELECT id, test_name FROM tests WHERE teacher_id = ?
                                $stmt_tests = $conn->prepare("SELECT id, test_name FROM tests WHERE teacher_id = ? ORDER BY id");
                                if($stmt_tests){
                                    $stmt_tests->bind_param("s", $teacher_id);
                                    $stmt_tests->execute();
                                    $result_tests = $stmt_tests->get_result();
                                    while ($row_test = $result_tests->fetch_assoc()) {
                                        // 【修正点 2】: 取得したカラム名に合わせてキーを変更しました。
                                        // (変更前) value='...[TestID]' > ...[testname]
                                        // (変更後) value='...[id]' > ...[test_name]
                                        echo "<option value='" . $row_test['id'] . "'>" . htmlspecialchars($row_test['test_name']) . "</option>";
                                    }
                                    $stmt_tests->close();
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div id="test-results-container" class="results-container">
                        <p>テストを選択すると、学習者の結果が表示されます。</p>
                    </div>
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
                                    // 【修正点 3】: SQLクエリをデータベースの定義に合わせました。
                                    // (変更前) SELECT UID, Name FROM students
                                    // (変更後) SELECT uid, Name FROM students
                                    $stmt_students = $conn->prepare("SELECT uid, Name FROM students WHERE ClassID IN ($placeholders) ORDER BY Name");
                                    if ($stmt_students) {
                                        $types = str_repeat('i', count($class_ids));
                                        $stmt_students->bind_param($types, ...$class_ids);
                                        $stmt_students->execute();
                                        $result_students = $stmt_students->get_result();
                                        while ($row_student = $result_students->fetch_assoc()) {
                                            // 【修正点 4】: 取得したカラム名に合わせてキーを変更しました。
                                            // (変更前) value='...[UID]'
                                            // (変更後) value='...[uid]'
                                            echo "<option value='" . $row_student['uid'] . "'>" . htmlspecialchars($row_student['Name']) . "</option>";
                                        }
                                        $stmt_students->close();
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div id="student-details-container" class="results-container">
                        <p>学習者を選択すると、総合評価と問題ごとの結果が表示されます。</p>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebarClose = document.getElementById('sidebar-close');
    const backdrop = document.getElementById('sidebar-backdrop');
    const body = document.body;

    function openSidebar() {
        body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        body.classList.remove('sidebar-open');
    }

    menuToggle.addEventListener('click', openSidebar);
    sidebarClose.addEventListener('click', closeSidebar);
    backdrop.addEventListener('click', closeSidebar);

    const testSelect = document.getElementById('test-select');
    const testResultsContainer = document.getElementById('test-results-container');
    const studentSelect = document.getElementById('student-select');
    const studentDetailsContainer = document.getElementById('student-details-container');

    testSelect.addEventListener('change', async function () {
        const testId = this.value;
        if (!testId) {
            testResultsContainer.innerHTML = '<p>テストを選択すると、学習者の結果が表示されます。</p>';
            return;
        }
        testResultsContainer.innerHTML = '<p class="loading">結果を読み込んでいます...</p>';
        const formData = new FormData();
        formData.append('action', 'get_test_results');
        formData.append('test_id', testId);

        try {
            const response = await fetch('teachertrue.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();
            renderTestResults(data);
        } catch (error) {
            console.error('Error fetching test results:', error);
            testResultsContainer.innerHTML = '<p class="error">結果の読み込みに失敗しました。</p>';
        }
    });

    studentSelect.addEventListener('change', async function () {
        const studentId = this.value;
        if (!studentId) {
            studentDetailsContainer.innerHTML = '<p>学習者を選択すると、総合評価と問題ごとの結果が表示されます。</p>';
            return;
        }
        studentDetailsContainer.innerHTML = '<p class="loading">詳細を読み込んでいます...</p>';
        const formData = new FormData();
        formData.append('action', 'get_student_details');
        formData.append('student_id', studentId);

        try {
            const response = await fetch('teachertrue.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();
            renderStudentDetails(data);
        } catch (error) {
            console.error('Error fetching student details:', error);
            studentDetailsContainer.innerHTML = '<p class="error">詳細の読み込みに失敗しました。</p>';
        }
    });

    function renderTestResults(data) {
        if (!data || data.length === 0) {
            testResultsContainer.innerHTML = '<p>このテストの解答結果はありません。</p>';
            return;
        }
        let tableHtml = `
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all-students"></th>
                        <th>学習者名</th>
                        <th>正誤</th>
                        <th>迷い推定</th>
                        <th>解答日時</th>
                        <th>軌跡再現</th>
                    </tr>
                </thead>
                <tbody>`;
        data.forEach(row => {
            const hesitationClass = row.hesitation === '迷い有り' ? 'hesitation-yes' : '';
            const correctnessClass = row.correctness === '不正解' ? 'incorrect' : '';
            tableHtml += `
                <tr>
                    <td><input type="checkbox" class="student-checkbox" value="${row.student_id}"></td>
                    <td>${row.student_name}</td>
                    <td class="${correctnessClass}">${row.correctness}</td>
                    <td class="${hesitationClass}">${row.hesitation}</td>
                    <td>${row.date}</td>
                    <td><a href="./mousemove/mousemove.php?UID=${row.student_id}&WID=DUMMY_WID" target="_blank" class="link-button">表示</a></td>
                </tr>`;
        });
        tableHtml += '</tbody></table>';
        testResultsContainer.innerHTML = tableHtml;
    }

    function renderStudentDetails(data) {
        if (!data) {
            studentDetailsContainer.innerHTML = '<p>この学習者のデータはありません。</p>';
            return;
        }
        let detailsHtml = `
            <div class="student-summary">
                <h4>総合評価</h4>
                <p><strong>総解答数:</strong> ${data.summary.total_attempts}</p>
                <p><strong>正答率:</strong> ${data.summary.accuracy}</p>
                <p><strong>迷い率:</strong> ${data.summary.hesitation_rate}</p>
            </div>
            <h4>問題ごとの結果</h4>
            <table>
                <thead>
                    <tr>
                        <th>問題ID</th>
                        <th>正誤</th>
                        <th>迷い推定</th>
                        <th>解答日時</th>
                    </tr>
                </thead>
                <tbody>`;
        data.attempts.forEach(attempt => {
            const hesitationClass = attempt.hesitation === '迷い有り' ? 'hesitation-yes' : '';
            const correctnessClass = attempt.correctness === '不正解' ? 'incorrect' : '';
            detailsHtml += `
                <tr>
                    <td>${attempt.wid}</td>
                    <td class="${correctnessClass}">${attempt.correctness}</td>
                    <td class="${hesitationClass}">${attempt.hesitation}</td>
                    <td>${attempt.date}</td>
                </tr>`;
        });
        detailsHtml += '</tbody></table>';
        studentDetailsContainer.innerHTML = detailsHtml;
    }
});
</script>
</body>
</html>
<?php
// lang.phpでセッションが開始されるため、個別のsession_startは不要
include '../lang.php';
require "../dbc.php";
// セッション変数をクリアする（必要に応じて）
unset($_SESSION['conditions']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('student.php_5行目_学生用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/student_style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<script>
    // ▼▼▼ ここから追加 ▼▼▼
    // PHPの言語設定をJavaScriptの変数に保存します
    var currentLang = "<?php echo $lang; ?>";
    // ▲▲▲ ここまで追加 ▲▲▲

    function openwin(Qid) {
        // ▼▼▼ ここを修正 ▼▼▼
        // URLの末尾に言語情報を追加します
        window.open("ques.php?Qid=" + Qid + "&lang=" + currentLang, "new", "width=861,height=700,resizable=0,menubar=0");
    }
</script>

<body>
    <header>
        <div class="logo"><?= translate('student.php_23行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="#"><?= translate('student.php_26行目_ホーム') ?></a></li> -->
                <li><a href="../logout.php"><?= translate('student.php_27行目_ログアウト') ?></a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="#"><?= translate('student.php_26行目_ホーム') ?></a></li>
                <li><a href="../logout.php"><?= translate('student.php_27行目_ログアウト') ?></a></li>
            </ul>
        </aside>
        <main>
            <h1><?= translate('student.php_38行目_学生用LMS') ?></h1>
            <div class="news">
                <h2><?= translate('student.php_41行目_お知らせ一覧') ?></h2>
                <!-- <a href="notification_detail.php">もっとみる</a> -->
                <?php
                $studentId = $_SESSION["MemberID"];

                // 指定ユーザが受信する通知を取得
                $sql = "
                    SELECT n.id, n.subject, n.created_at
                    FROM notifications n
                    JOIN notification_recipients nr ON n.id = nr.notification_id
                    WHERE nr.student_id = ?
                    ORDER BY n.created_at DESC
                    ";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $studentId);
                $stmt->execute();
                $result = $stmt->get_result();
                ?>
                <div class="news-scroll">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="news-item" data-id="<?php echo $row['id']; ?>">
                                <h3 class="news-title">
                                    <?php echo htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8'); ?>
                                </h3>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p><?= translate('student.php_66行目_現在お知らせはありません') ?></p>
                    <?php endif; ?>
                    <?php $stmt->close(); ?>
                </div>
            </div>

            <div class="content">
                <form action="./test.php" method="get">
                    <button type="submit"><?= translate('student.php_76行目_テスト') ?></button>
                </form>
                <form action="Analytics/analytics.php" method="get">
                    <button type="submit"><?= translate('student.php_80行目_成績管理') ?></button>
                </form>
            </div>

            <div class="test-list section-box">
                <h2>最新のテスト(最新3件)</h2>
                <a href="./test.php" class="more-btn">もっとみる</a>
                <?php
                $group_ids_str = implode(",", $_SESSION['GroupIDs']);
                $student_id = $_SESSION['MemberID'];
                $class_id = $_SESSION['ClassID'];
                $sql = "SELECT t.id, t.test_name, t.created_at,
                            (CASE
                                WHEN MAX(uq.current_oid) IS NULL THEN '未回答'
                                WHEN MAX(uq.current_oid) = (
                                    SELECT MAX(tq.OID) 
                                    FROM test_questions tq 
                                    WHERE tq.test_id = t.id
                                ) THEN '解答済み'
                                ELSE '解答途中'
                            END) AS status
                        FROM tests t
                        LEFT JOIN user_progress uq ON t.id = uq.test_id AND uq.uid = ?
                        WHERE (t.target_type = 'class' AND t.target_group = ?)
                        OR (t.target_type = 'group' AND t.target_group IN ($group_ids_str))
                        GROUP BY t.id, t.test_name, t.created_at
                        ORDER BY t.created_at DESC
                        LIMIT 3";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $student_id, $class_id);
                $stmt->execute();
                $testsResult = $stmt->get_result();
                ?>
                <?php if ($testsResult->num_rows > 0): ?>
                    <table class="test-table" border="1">
                        <thead>
                            <tr>
                                <th>テスト名</th>
                                <th>作成日時</th>
                                <th>解答状況</th>
                                <th>解答</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $testsResult->fetch_assoc()): ?>
                                <?php
                                $status_class =
                                    ($row['status'] === '未回答') ? 'status-unanswered' : (($row['status'] === '解答途中') ? 'status-in-progress' :
                                        'status-completed');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['test_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= $row['created_at']; ?></td>
                                    <td class="<?= $status_class; ?>"><?= $row['status']; ?></td>
                                    <td>
                                        <?php if ($row['status'] === '解答済み'): ?>

                                            <a href="result.php?Qid=<?= $row['id']; ?>&lang=<?= $lang; ?>" target="_blank"><?= translate('test.php_view_results') ?></a>

                                        <?php else: ?>

                                            <a href="javascript:openwin(<?= $row['id']; ?>)"><?= translate('test.php_106行目_解答') ?></a>

                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>登録されたテストはありません。</p>
                <?php endif;
                $stmt->close(); ?>
            </div>

            <div class="summary section-box">
                <div class="section-header">
                    <h2>成績ダッシュボード</h2>
                    <a href="./Analytics/analytics.php" class="more-btn">解いた問題ごとに確認</a>
                </div>

                <div class="select-container">
                    <p>-解いたテストごとに確認-</p>
                    <label for="testSelect">テストを選択:</label>
                    <select id="testSelect">
                        <option value="">選択してください</option>
                        <?php
                        // ▼ 上段で $testsResult を consume しているため再クエリ
                        $group_ids_str = implode(",", $_SESSION['GroupIDs']);
                        $student_id = $_SESSION['MemberID'];
                        $class_id = $_SESSION['ClassID'];
                        $sqlSel = "SELECT t.id, t.test_name, t.created_at
                   FROM tests t
                   WHERE (t.target_type = 'class' AND t.target_group = ?)
                      OR (t.target_type = 'group' AND t.target_group IN ($group_ids_str))
                   ORDER BY t.created_at DESC";
                        $stmt2 = $conn->prepare($sqlSel);
                        $stmt2->bind_param("i", $class_id);
                        $stmt2->execute();
                        $res2 = $stmt2->get_result();
                        while ($opt = $res2->fetch_assoc()):
                        ?>
                            <option value="<?= (int) $opt['id']; ?>">
                                <?= htmlspecialchars($opt['test_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endwhile;
                        $stmt2->close(); ?>
                    </select>
                </div>

                <!-- ▼ 解答情報（WID / 迷い / 正誤 / 軌跡）をここに描画 ▼ -->
                <div id="answerInfo" style="margin-top:16px; display:none;">
                    <table class="table2" id="answerTable" style="width:100%;">
                        <thead>
                            <tr>
                                <th>WID</th>
                                <th>迷い</th>
                                <th>正誤</th>
                                <th>軌跡</th>
                            </tr>
                        </thead>
                        <tbody><!-- ここにJSで行を挿入 --></tbody>
                    </table>
                    <div id="noDataMsg" style="display:none; color:#666;">データがありません。</div>
                </div>
            </div>

            <script>
                const testSel = document.getElementById('testSelect');
                const answerInfo = document.getElementById('answerInfo');
                const answerTbody = document.querySelector('#answerTable tbody');
                const noDataMsg = document.getElementById('noDataMsg');

                function mapUnderstandLabel(code) {
                    if (code === 2 || code === '2') return '迷い有り';
                    if (code === 4 || code === '4') return '迷い無し';
                    return '不明';
                }

                function mapTfLabel(code) {
                    if (code === 1 || code === '1') return '正解';
                    if (code === 0 || code === '0') return '不正解';
                    return '未解答';
                }

                function renderRows(rows) {
                    answerTbody.innerHTML = '';
                    if (!rows || rows.length === 0) {
                        noDataMsg.style.display = 'block';
                        return;
                    }
                    noDataMsg.style.display = 'none';
                    rows.forEach(r => {
                        const tr = document.createElement('tr');

                        // WID
                        const tdW = document.createElement('td');
                        tdW.textContent = r.WID;
                        tr.appendChild(tdW);

                        // 迷い（2=有り, 4=無し, 他=不明）
                        const tdU = document.createElement('td');
                        tdU.textContent = mapUnderstandLabel(r.understand_code);
                        if (r.understand_code === 2 || r.understand_code === '2') {
                            tdU.style.color = 'red';
                            tdU.style.fontWeight = 'bold';
                        }
                        tr.appendChild(tdU);

                        // 正誤（1=正解, 0=不正解, null=未解答）
                        const tdTF = document.createElement('td');
                        tdTF.textContent = mapTfLabel(r.tf_code);
                        if (r.tf_code === 0 || r.tf_code === '0') {
                            tdTF.style.color = 'red';
                            tdTF.style.fontWeight = 'bold';
                        }
                        tr.appendChild(tdTF);

                        // 軌跡リンク
                        const tdL = document.createElement('td');
                        if (r.link) {
                            const a = document.createElement('a');
                            a.href = r.link;
                            a.target = '_blank';
                            a.rel = 'noopener noreferrer';
                            a.textContent = '軌跡再現';
                            tdL.appendChild(a);
                        } else {
                            tdL.textContent = '-';
                        }
                        tr.appendChild(tdL);

                        answerTbody.appendChild(tr);
                    });
                }

                testSel.addEventListener('change', async () => {
                    const tid = testSel.value;
                    if (!tid) {
                        answerInfo.style.display = 'none';
                        return;
                    }
                    try {
                        const res = await fetch(`Analytics/test_answers.php?test_id=${encodeURIComponent(tid)}`);
                        const js = await res.json();
                        if (!js.ok) throw new Error(js.msg || '取得に失敗しました');
                        renderRows(js.rows);
                        answerInfo.style.display = 'block';
                    } catch (e) {
                        alert('解答情報の取得に失敗しました: ' + e.message);
                        answerInfo.style.display = 'none';
                    }
                });
            </script>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const newsItems = document.querySelectorAll('.news-item');
                    newsItems.forEach(item => {
                        item.addEventListener('dblclick', function() {
                            const id = this.getAttribute('data-id');
                            window.location.href = 'notification_detail.php?id=' + id;
                        });
                    });
                });
            </script>
        </main>
    </div>
</body>

</html>
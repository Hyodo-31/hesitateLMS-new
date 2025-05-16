<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教師用ダッシュボード</title>
    <!--
    ここにcssのスタイルシートを入れる
-->
    <link rel="stylesheet" href="../style/student_style.css">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .status-unanswered {
            color: red;
            font-weight: bold;
        }

        .status-in-progress {
            color: orange;
            font-weight: bold;
        }

        .status-completed {
            color: black;
            font-weight: bold;
        }
    </style>
</head>
<script>
    function openwin(Qid){
        window.open("ques.php?Qid="+Qid, "new", "width=861,height=700,resizable=0,menubar=0");
    }
</script>
<body>
    <?php
        session_start();
        require "../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo">英単語並べ替え問題LMS</div>
        <nav>
            <ul>
                <li><a href="student.php">ホーム</a></li>
                <li><a href="../logout.php">ログアウト</a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="student.php">ホーム</a></li>
                <li><a href="/Analytics/analytics.php">成績管理</a></li>
            </ul>
        </aside>
        <main>
            <!-- ここにコンテンツを入れる -->
            ここはテストのページ
            <?php
                // セッションに格納された複数のグループID（例：[3,5,7]）をカンマ区切りの文字列に変換
                $group_ids_str = implode(",", $_SESSION['GroupIDs']);
                
                // 対象のテストを取得
                $student_id = $_SESSION['MemberID'];
                $class_id   = $_SESSION['ClassID'];
                
                // SQL文の変更点：
                // ・対象がクラスの場合： t.target_type = 'class' AND t.target_group = ?
                // ・対象がグループの場合： t.target_type = 'group' AND t.target_group IN ($group_ids_str)
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
                        ORDER BY t.created_at DESC";
                
                // 準備：プレースホルダーは学習者のIDとクラスIDの2つなのでbind_paramは"ii"でOK
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $student_id, $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
            ?>
            <h1>登録されたテスト一覧</h1>
            <?php if ($result->num_rows > 0): ?>
                <table border="1" class="test-table">
                    <thead>
                        <tr>
                            <th>テスト名</th>
                            <th>作成日時</th>
                            <th>解答状況</th>
                            <th>解答</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            while ($row = $result->fetch_assoc()) {
                                $status_class = '';
                                if ($row['status'] === '未回答') {
                                    $status_class = 'status-unanswered';
                                } elseif ($row['status'] === '解答途中') {
                                    $status_class = 'status-in-progress';
                                } elseif ($row['status'] === '解答済み') {
                                    $status_class = 'status-completed';
                                }
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['test_name']) . "</td>";
                                echo "<td>" . $row['created_at'] . "</td>";
                                echo "<td class='$status_class'>" . htmlspecialchars($row['status']) . "</td>";
                                echo "<td><a href='javascript:openwin(" . $row['id'] . ")'>解答</a></td>";
                                echo "</tr>";
                            }
                        ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>登録されたテストはありません。</p>
            <?php endif; ?>
        </main>

    </div>
</body>
</html>

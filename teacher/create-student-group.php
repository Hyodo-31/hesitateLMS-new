<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教師用ダッシュボード</title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
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
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
            </ul>
        </aside>
        <main>
            

            <div class="content-class">
            <h2>学生グループ作成</h2>
                <!-- 検索フォーム -->
                <form id="search-form" method="GET">
                    <!--太文字で大きく中央に表示-->
                    <div class="filter-form-title">絞り込みフォーム</div>
                    <label class="uid-label">UID:</label>
                    <!-- すべて選択 / すべて解除ボタン -->
                    <!--横並びにして間にスペースを入れる-->
                    <div class="button-container" style="margin-bottom: 10px; display: flex; gap: 10px;">
                        <button type="button" id="select-all-btn">すべて選択</button>
                        <button type="button" id="deselect-all-btn">すべて解除</button>
                    </div>
                    <div id="uid-checkbox-list" class="list-container">
                        <!-- PHPでUIDリストを動的に生成 -->
                        <?php
                        $sql_getuid = "SELECT s.uid, s.Name 
                                    FROM students s
                                    LEFT JOIN ClassTeacher ct ON s.ClassID = ct.ClassID
                                    WHERE ct.TID = ?";
                        $stmt = $conn->prepare($sql_getuid);
                        $stmt->bind_param("i", $_SESSION['MemberID']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            echo "<div class='list-item'>
                                    <label>
                                        <input type='checkbox' class='uid-checkbox' name='uid[]' value='{$row['uid']}'> 
                                        <span class='label-text'>名前:</span> {$row['Name']}
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

                    <label for="accuracy">正解率 (%):</label>
                    <input type="number" id="accuracy_min" name="accuracy_min" placeholder="最小値">
                    <input type="number" id="accuracy_max" name="accuracy_max" placeholder="最大値">
                    <br>
                    <label for = "hesitation_rate">迷い率:</label>
                    <input type="number" id="hesitation_rate" name="hesitation_rate_min" placeholder="最小値">
                    <input type="number" id="hesitation_rate" name="hesitation_rate_max" placeholder="最大値">
                    <br>

                    <label for = "total_answers">問題解答数:</label>
                    <input type="number" id="total_answers" name="total_answers_min" placeholder="最小値">
                    <input type="number" id="total_answers" name="total_answers_max" placeholder="最大値">
                    <br>

                    <button type="button" id="search-button">検索</button>
                </form>
                <form action="submit-student-group.php" method="post">
                    <label for="group_name">グループ名:</label>
                        <input type="text" id="group_name" name="group_name" required>
                        <br><br>
                        <label>学生リスト:</label>
                        <ul class="student-list" id="student-list">
                            <!-- PHPで全学生を取得して初期表示 -->
                            <?php
                            $sql_getstudent = "SELECT 
                                            s.uid,
                                            s.Name,
                                            COALESCE(acc.accuracy, 0) AS accuracy,
                                            COALESCE(acc.total_answers, 0) AS total_answers,
                                            COALESCE(hes.hesitation_rate, 0) AS hesitation_rate
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
                                        WHERE ct.TID = ?;

                            ";
                            $stmt = $conn->prepare($sql_getstudent);
                            $stmt->bind_param("i", $_SESSION['MemberID']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                echo "<li class='student-item'>
                                        <label>
                                            <input type='checkbox' name='students[]' value='{$row['uid']}'>
                                            <p class='student-detail'><span class='label'>名前:</span> {$row['Name']}</p>
                                            <p class='student-detail'><span class='label'>正解率:</span> {$row['accuracy']}%</p>
                                            <p class='student-detail'><span class='label'>迷い率:</span> {$row['hesitation_rate']}%</p>
                                            <p class='student-detail'><span class='label'>解答数:</span> {$row['total_answers']}</p>
                                        </label>
                                    </li>";
                            }
                            $result->free();
                            ?>
                        </ul>
                    <button type="submit">グループを作成</button>
                </form>
            </div>
            <div class = "content-class">
            <h2>現在のグループ</h2>
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
                                <button type='submit' onclick='return confirm(\"このグループを削除してよろしいですか？\");'>削除</button>
                              </form>";
                        echo "</li>";
                    }
                    $group_result->free();
                    $conn->close();
                ?>
            </ul>
            </div>
        </main>
    </div>
    <script src = "search_studentlist.js"></script>
</body>
</html>

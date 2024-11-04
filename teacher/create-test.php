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
    <script>
        function openFilterModal(){
            document.getElementById("filter-modal").style.display = "block";
        }

        function closeFilterModal(){
            document.getElementById("filter-modal").style.display = "none";
        }
    </script>
    <header>
        <div class="logo">英単語並べ替え問題LMS</div>
        <nav>
            <ul>
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="#">コース管理</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
            </ul>
        </nav>
    </header>
    <?php
        // grammar対応表を配列にする
        $grammar_map = [
            1 => "仮定法，命令法",
            2 => "It,There",
            3 => "無生物主語",
            4 => "接続詞",
            5 => "倒置",
            6 => "関係詞",
            7 => "間接話法",
            8 => "前置詞句",
            9 => "分詞",
            10 => "動名詞",
            11 => "不定詞",
            12 => "受動態",
            13 => "助動詞",
            14 => "比較",
            15 => "否定",
            16 => "後置修飾",
            17 => "完了形，時制",
            18 => "句動詞",
            19 => "挿入",
            20 => "使役",
            21 => "補語，二重目的語",
            22 => "補語，二重目的語"
        ];

        // 検索条件を初期化
        $conditions = [];

        // 検索フォームから送信されたデータがあるかチェック
        if (isset($_GET['level'])) {
            // 難易度の条件を追加
            $level = implode(",", array_map('intval', $_GET['level']));
            $conditions[] = "level IN ($level)";
        }

        if (isset($_GET['grammar'])) {
            // 文法項目の条件を追加（#で囲まれたものをLIKEで検索）
            $grammar_conditions = [];
            foreach ($_GET['grammar'] as $grammar_value) {
                $grammar_conditions[] = "grammar LIKE '%#" . intval($grammar_value) . "#%'";
            }
            // 複数の文法項目を選択された場合はORで繋ぐ
            $conditions[] = "(" . implode(" OR ", $grammar_conditions) . ")";
        }

        if (!empty($_GET['word_count_min'])) {
            // 語数の最小値を追加
            $word_count_min = intval($_GET['word_count_min']);
            $conditions[] = "wordnum >= $word_count_min";
        }

        if (!empty($_GET['word_count_max'])) {
            // 語数の最大値を追加
            $word_count_max = intval($_GET['word_count_max']);
            $conditions[] = "wordnum <= $word_count_max";
        }

        // 基本クエリ
        $query = "SELECT * FROM question_info";

        // 検索条件がある場合はクエリに条件を追加
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $result = $conn->query($query);

    ?>

    <div id = "filter-modal" class = "modal">
        <div class = "modal-content">
        <!--&times;は×記号-->
            <span class = "close" onclick="closeFilterModal()">&times;</span>
            <h3>フィルタ条件を選択して下さい</h3>
            <form action = "create-test.php" method = "GET">
                <div class = "create-test-table-content">
                    <table border="1" class = "table1">
                        <tr>
                            <th>難易度</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="level[]" value="1">初級
                                </label>
                                <label>
                                    <input type="checkbox" name="level[]" value="2">中級
                                </label>
                                <label>
                                    <input type="checkbox" name="level[]" value="3">上級
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>文法項目</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="1"> 仮定法，命令法
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="2"> It,There
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="3"> 無生物主語
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="4"> 接続詞
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="5"> 倒置
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="6"> 関係詞
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="7"> 間接話法
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="8"> 前置詞句
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="9"> 分詞
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="10"> 動名詞
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="11"> 不定詞
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="12"> 受動態
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="13"> 助動詞
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="14"> 比較
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="15"> 否定
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="16"> 後置修飾
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="17"> 完了形，時制
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="18"> 句動詞
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="19"> 挿入
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="20"> 使役
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="21"> 補語，二重目的語
                                </label><br>
                            </td>
                        </tr>
                        <tr>
                            <th>語数</th>
                            <td>
                                <input type="number" name="word_count_min" placeholder="最小語数" min="1">
                                ～ 
                                <input type="number" name="word_count_max" placeholder="最大語数" min="1">
                            </td>
                        </tr>
                    </table>
                    <button type="submit">検索</button>
                </div>
            </form>
        </div>
    </div>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
            </ul>
        </aside>
        <main>
            <!-- ここにコンテンツを入れる -->
            <div class = "search" align = "center">
                <h2 onclick="openFilterModal()">検索フィルタ</h2>
            </div>
             <div class = "create-test">
                <h2>テストに追加する問題を選択してください</h2>
                <form action="submit-test.php" method="POST">
                    <label for="test_name">テスト名</label>
                    <input type="text" name="test_name" required><br>
                    <table>
                        <tr>
                            <th>選択</th>
                            <th>日本語</th>
                            <th>英文</th>
                            <th>難易度</th>
                            <th>文法項目</th>
                            <th>語数</th>
                        </tr>
                        <!-- サンプルデータ行 -->
                        <?php
                            // 初期クエリ
                            if($result->num_rows > 0) {
                                $count = 0; // カウンター
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td><input type='checkbox' name='WID[]' value='" . $row['WID'] . "'></td>";
                                    echo "<td>" . htmlspecialchars($row['Japanese']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['Sentence']) . "</td>";
                                    if($row['level'] == 1) {
                                        echo "<td>初級</td>";
                                    } else if($row['level'] == 2) {
                                        echo "<td>中級</td>";
                                    } else if($row['level'] == 3) {
                                        echo "<td>上級</td>";
                                    }else{
                                        echo "<td>難易度設定なし</td>";
                                    }
                                    // grammar列の表示処理
                                    $grammar_values = explode('#', $row['grammar']); // #で分割
                                    $grammar_display = [];
                                    $unknown_count = 0; // 対応表にない番号をカウントする

                                    // 分割された番号を対応表で確認
                                    foreach ($grammar_values as $value) {
                                        if (!empty($value)) {
                                            $value = (int)$value; // 整数に変換
                                            if (isset($grammar_map[$value])) {
                                                // 対応する文法項目があれば追加
                                                $grammar_display[] = $grammar_map[$value];
                                            } else {
                                                // 対応がない場合に「該当なし」とカウント
                                                $unknown_count++;
                                            }
                                        }
                                    }

                                    // 文法項目をカンマで結合して表示、もし対応がないものがあれば「該当なし」を表示
                                    if (!empty($grammar_display)) {
                                        echo "<td>" . htmlspecialchars(implode(', ', $grammar_display)) . "</td>";
                                    } else {
                                        // すべての番号が対応していなかった場合
                                        echo "<td>該当なし</td>";
                                    }
                                    echo "<td>" . htmlspecialchars($row['wordnum']) . "</td>";
                                    echo "</tr>";

                                    $count++;
                                }
                            }
                        ?>
                    </table>
                    <button type="submit">テストを作成する</button>
                    <?php 
                        //デバッグ用
                        //echo $count . "件の問題を選択しました。";
                    ?>
                </form>

             </div>
        </main>
    </div>
</body>
</html>

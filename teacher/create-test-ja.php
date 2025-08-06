<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('create-test.php_6行目_教師用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        //session_start(); // lang.phpでセッションは開始済み
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
        <div class="logo"><?= translate('create-test.php_29行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <li><a href="teachertrue.php"><?= translate('create-test.php_32行目_ホーム') ?></a></li>
                <li><a href="#"><?= translate('create-test.php_33行目_コース管理') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('create-test.php_34行目_迷い推定・機械学習') ?></a></li>
                <!-- この下二つの参照先が無い！ -->
                <li><a href="Analytics/studentAnalytics.php"><?= translate('create-test.php_35行目_学生分析') ?></a></li> 
                <li><a href="Analytics/questionAnalytics.php"><?= translate('create-test.php_36行目_問題分析') ?></a></li>
            </ul>
        </nav>
    </header>
    <?php
        // grammar対応表を配列にする
        $grammar_map = [
            1 => translate('create-test.php_57行目_仮定法, 命令法'),
            2 => translate('create-test.php_58行目_It,There'),
            3 => translate('create-test.php_59行目_無生物主語'),
            4 => translate('create-test.php_60行目_接続詞'),
            5 => translate('create-test.php_61行目_倒置'),
            6 => translate('create-test.php_62行目_関係詞'),
            7 => translate('create-test.php_63行目_間接話法'),
            8 => translate('create-test.php_64行目_前置詞句'),
            9 => translate('create-test.php_65行目_分詞'),
            10 => translate('create-test.php_66行目_動名詞'),
            11 => translate('create-test.php_67行目_不定詞'),
            12 => translate('create-test.php_68行目_受動態'),
            13 => translate('create-test.php_69行目_助動詞'),
            14 => translate('create-test.php_70行目_比較'),
            15 => translate('create-test.php_71行目_否定'),
            16 => translate('create-test.php_72行目_後置修飾'),
            17 => translate('create-test.php_73行目_完了形, 時制'),
            18 => translate('create-test.php_74行目_句動詞'),
            19 => translate('create-test.php_75行目_挿入'),
            20 => translate('create-test.php_76行目_使役'),
            21 => translate('create-test.php_77行目_補語, 二重目的語'),
            22 => translate('create-test.php_78行目_補語, 二重目的語_2')
        ];

        // 検索条件を初期化
        $conditions = [];
        if(isset($_GET['WID'])) {
            $WID = implode(",", array_map('intval', $_GET['WID']));
            $conditions[] = "WID IN ($WID)";
        }

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
        $query = "SELECT * FROM question_info_ja";

        // 検索条件がある場合はクエリに条件を追加
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $result = $conn->query($query);

    ?>

    <div id = "filter-modal" class = "modal">
        <div class = "modal-content">
        <span class = "close" onclick="closeFilterModal()">&times;</span>
            <h3><?= translate('create-test.php_123行目_フィルタ条件を選択して下さい') ?></h3>
            <form action = "create-test-ja.php" method = "GET">
                <div class = "create-test-table-content">
                    <table border="1" class = "table1">
                        <tr>
                            <th><?= translate('create-test.php_127行目_問題ID') ?></th>
                            <?php
                                $sql = "SELECT WID FROM question_info_ja";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute();
                                $result_WID = $stmt->get_result();
                                echo "<td>";
                                while($row = $result_WID->fetch_assoc()) {
                                    echo "<label><input type='checkbox' name='WID[]' value='" . $row['WID'] . "'>" . $row['WID'] . "</label>";
                                }
                                echo "</td>";
                            ?>
                        <tr>
                            <th><?= translate('create-test.php_140行目_難易度') ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="level[]" value="1"><?= translate('create-test.php_143行目_初級') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="level[]" value="2"><?= translate('create-test.php_146行目_中級') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="level[]" value="3"><?= translate('create-test.php_149行目_上級') ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?= translate('create-test.php_153行目_文法項目') ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="1"><?= translate('create-test.php_156行目_仮定法，命令法') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="2"><?= translate('create-test.php_159行目_It,There') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="3"><?= translate('create-test.php_162行目_無生物主語') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="4"><?= translate('create-test.php_165行目_接続詞') ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="5"><?= translate('create-test.php_167行目_倒置') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="6"><?= translate('create-test.php_170行目_関係詞') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="7"><?= translate('create-test.php_173行目_間接話法') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="8"><?= translate('create-test.php_176行目_前置詞句') ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="9"><?= translate('create-test.php_178行目_分詞') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="10"><?= translate('create-test.php_181行目_動名詞') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="11"><?= translate('create-test.php_184行目_不定詞') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="12"><?= translate('create-test.php_187行目_受動態') ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="13"><?= translate('create-test.php_189行目_助動詞') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="14"><?= translate('create-test.php_192行目_比較') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="15"><?= translate('create-test.php_195行目_否定') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="16"><?= translate('create-test.php_198行目_後置修飾') ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="17"><?= translate('create-test.php_200行目_完了形, 時制') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="18"><?= translate('create-test.php_203行目_句動詞') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="19"><?= translate('create-test.php_206行目_挿入') ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="20"><?= translate('create-test.php_209行目_使役') ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="grammar[]" value="21"><?= translate('create-test.php_211行目_補語, 二重目的語') ?>
                                </label><br>
                            </td>
                        </tr>
                        <tr>
                            <th><?= translate('create-test.php_215行目_語数') ?></th>
                            <td>
                                <input type="number" name="word_count_min" placeholder="<?= translate('create-test.php_217行目_最小語数') ?>" min="1">
                                ～ 
                                <input type="number" name="word_count_max" placeholder="<?= translate('create-test.php_219行目_最大語数') ?>" min="1">
                            </td>
                        </tr>
                    </table>
                    <button type="submit"><?= translate('create-test.php_223行目_検索') ?></button>
                </div>
            </form>
        </div>
    </div>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('create-test.php_230行目_ホーム') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('create-test.php_231行目_迷い推定・機械学習') ?></a></li>
                <!-- 参照先が無い二つ！ -->
                <li><a href="Analytics/studentAnalytics.php"><?= translate('create-test.php_232行目_学生分析') ?></a></li>
                <li><a href="Analytics/questionAnalytics.php"><?= translate('create-test.php_233行目_問題分析') ?></a></li>
            </ul>
        </aside>
        <main>
            <div class = "search" align = "center">
                <h2 onclick="openFilterModal()"><?= translate('create-test.php_237行目_検索フィルタ') ?></h2>
            </div>
            <div class = "create-test">
                <h2><?= translate('create-test.php_240行目_テストに追加する問題を選択してください') ?></h2>
                <form action="submit-test-ja.php" method="POST">
                    <div class = "create-test-table-content">
                        <label for="test_name"><?= translate('create-test.php_243行目_テスト名') ?></label>
                        <input type="text" name="test_name" required><br>
                    </div>
                    <div class = "create-test-table-content">
                        <label><?= translate('create-test.php_247行目_対象選択：') ?></label>
                        <input type="radio" id="class_radio" name="target_type" value="class" checked>
                        <label for="class_radio"><?= translate('create-test.php_249行目_クラス') ?></label>
                        <input type="radio" id="group_radio" name="target_type" value="group">
                        <label for="group_radio"><?= translate('create-test.php_251行目_グループ') ?></label>
                    </div>
                    <div class = "create-test-table-content" id = "class_select_wrapper">
                        <label for = "class_select"><?= translate('create-test.php_254行目_対象クラス') ?></label>
                        <select id = "class_select" name = "class_id" required>
                            <?php
                                
                                //教師のTIDを取得
                                $teacher_id = $_SESSION['MemberID'];
                                //ClassTeacherとclassesを結合して対象クラス名を取得
                                $sql = "SELECT c.ClassID, c.ClassName 
                                        FROM ClassTeacher ct JOIN classes c ON ct.ClassID = c.ClassID
                                        WHERE ct.TID = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("s", $teacher_id);
                                $stmt->execute();
                                $result_class = $stmt->get_result();
                                
                                //結果をプルダウンに表示
                                while($row = $result_class->fetch_assoc()) {
                                    echo "<option value = '{$row['ClassID']}'>{$row['ClassName']}</option>";
                                }
                                    
                            ?>
                        </select>
                    </div>
                    <div class="create-test-table-content" id="group_select_wrapper" style="display:none;">
                        <label for="group_select"><?= translate('create-test.php_282行目_対象グループ') ?></label>
                        <select id="group_select" name="group_id">
                            <?php
                            // groupsテーブルから、ログイン中の教師に紐づくグループを取得
                            $sql_group = "SELECT groups.group_id,groups.group_name
                                FROM `groups`
                                WHERE TID = ?
                            ";
                            $stmt_group = $conn->prepare($sql_group);
                            $stmt_group->bind_param("s", $teacher_id);
                            $stmt_group->execute();
                            $result_group = $stmt_group->get_result();
                            
                            while($row = $result_group->fetch_assoc()) {
                                echo "<option value='{$row['group_id']}'>{$row['group_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <script>
                        const classRadio = document.getElementById('class_radio');
                        const groupRadio = document.getElementById('group_radio');
                        const classSelectWrapper = document.getElementById('class_select_wrapper');
                        const groupSelectWrapper = document.getElementById('group_select_wrapper');

                        // ラジオボタンが切り替わったときに呼ばれる関数
                        function toggleSelectBox() {
                            if (classRadio.checked) {
                                classSelectWrapper.style.display = 'block';
                                groupSelectWrapper.style.display = 'none';
                            } else {
                                classSelectWrapper.style.display = 'none';
                                groupSelectWrapper.style.display = 'block';
                            }
                        }

                        // 初期ロード時にも反映させる
                        toggleSelectBox();

                        // ラジオボタン変更時のイベント
                        classRadio.addEventListener('change', toggleSelectBox);
                        groupRadio.addEventListener('change', toggleSelectBox);
                    </script>
                    <table>
                        <tr>
                            <th><?= translate('create-test.php_326行目_選択') ?></th>
                            <th>WID</th>
                            <th><?= translate('create-test.php_329行目_英文') ?></th>
                            <th><?= translate('create-test.php_328行目_日本語') ?></th>
                            <th><?= translate('create-test.php_330行目_難易度') ?></th>
                            <th><?= translate('create-test.php_331行目_文法項目') ?></th>
                            <th><?= translate('create-test.php_332行目_語数') ?></th>
                        </tr>
                        <?php
                            // 初期クエリ
                            if($result->num_rows > 0) {
                                $count = 0; // カウンター
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td><input type='checkbox' name='WID[]' value='" . $row['WID'] . "'></td>";
                                    echo "<td>" . htmlspecialchars($row['WID']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['English']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['Sentence']) . "</td>";
                                    if($row['level'] == 1) {
                                        echo "<td>" . translate('create-test.php_342行目_初級') . "</td>";
                                    } else if($row['level'] == 2) {
                                        echo "<td>" . translate('create-test.php_344行目_中級') . "</td>";
                                    } else if($row['level'] == 3) {
                                        echo "<td>" . translate('create-test.php_346行目_上級') . "</td>";
                                    }else{
                                        echo "<td>" . translate('create-test.php_348行目_難易度設定なし') . "</td>";
                                    }
                                    // grammar列の表示処理
                                    //$grammar_values = explode('#', $row['grammar']); // #で分割　文法登録で使う部分、必要に応じてコメントアウト解除
                                    $grammar_display = [];
                                    $unknown_count = 0; // 対応表にない番号をカウントする

                                    // 分割された番号を対応表で確認   文法登録で使う部分、必要に応じてコメントアウト解除
                                    /*foreach ($grammar_values as $value) {
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
                                    }*/

                                    // 文法項目をカンマで結合して表示、もし対応がないものがあれば「該当なし」を表示
                                    if (!empty($grammar_display)) {
                                        echo "<td>" . htmlspecialchars(implode(', ', $grammar_display)) . "</td>";
                                    } else {
                                        // すべての番号が対応していなかった場合
                                        echo "<td>" . translate('create-test.php_369行目_該当なし') . "</td>";
                                    }
                                    echo "<td>" . htmlspecialchars($row['wordnum']) . "</td>";
                                    echo "</tr>";

                                    $count++;
                                }
                            }
                        ?>
                    </table>
                    <button type="submit"><?= translate('create-test.php_378行目_テストを作成する') ?></button>
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
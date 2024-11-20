<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教師用ダッシュボード</title>
    <link rel="stylesheet" href="../style/machineLearning_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <style>
        /* テーブルのスクロール表示設定 */
        #table-container {
            max-height: 400px; /* 表示領域の高さを指定 */
            overflow-y: auto;  /* 縦スクロールを有効にする */
            border: 1px solid #ccc; /* 境界線 */
        }

        /* テーブルのスタイル */
        #results-table {
            width: 100%; /* テーブル幅を100%に */
            border-collapse: collapse;
        }

        #results-table th, #results-table td {
            padding: 8px;
            border: 1px solid #ddd; /* セルの境界線 */
        }
    </style>

    <?php
        require "../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
        // GET パラメータが指定されている場合のみセッションに保存または上書き
        if (isset($_GET['students']) && !empty($_GET['students'])) {
            $_SESSION['group_students'] = $_GET['students'];
        }
    ?>
    <header>
        <div class="logo">データ分析ページ</div>
        <nav>
            <ul>
                <li><a href="teachertrue.php">ホーム</a></li>
                <li><a href="#">学習履歴</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="#">苦手分野</a></li>
                <li><a href="#">設定</a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="#">ダッシュボード</a></li>
                <li><a href="#">クラス管理</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="#">学習履歴</a></li>
                <li><a href="#">苦手分野</a></li>
            </ul>
        </aside>
        <main>
        <script>
            window.addEventListener('load', function() {
                var loadTime = performance.now();
                console.log('ページの表示時間: ' + loadTime.toFixed(2) + 'ミリ秒');
                document.getElementById('loadTime').textContent = 'ページの表示時間: ' + loadTime.toFixed(2) + 'ミリ秒';
            });
        </script>
            <?php
                // フォームからの入力を受け取る
                $UIDrange = isset($_POST['UIDrange']) ? $_POST['UIDrange'] : null;
                $WIDrange = isset($_POST['WIDrange']) ? $_POST['WIDrange'] : null;
                $UIDsearch = isset($_POST['UID']) ? $_POST['UID'] : null; // 配列として受け取る
                $WIDsearch = isset($_POST['WID']) ? $_POST['WID'] : null; // 配列として受け取る
                $TFsearch = isset($_POST['TFsearch']) ? $_POST['TFsearch'] : null;
                $TimeRange = isset($_POST['TimeRange']) ? $_POST['TimeRange'] : null;
                $Timesearch = isset($_POST['Timesearch']) ? $_POST['Timesearch'] : null;
                $TimesearchMin = isset($_POST['Timesearch-min']) ? $_POST['Timesearch-min'] : null;
                $TimesearchMax = isset($_POST['Timesearch-max']) ? $_POST['Timesearch-max'] : null;


                $sql = "SELECT * FROM linedata";
                // WHERE 句の条件を保持する配列
                $conditions = [];
                // UIDの条件を追加
                if (!empty($UIDsearch)) {
                    // UID配列をカンマ区切りの文字列に変換
                    $UIDlist = implode("','", array_map(function($uid) use ($conn) {
                        return mysqli_real_escape_string($conn, $uid);
                    }, $UIDsearch));
                    
                    if ($UIDrange === 'not') {
                        $conditions[] = "UID NOT IN ('" . $UIDlist . "')";
                    } else {
                        $conditions[] = "UID IN ('" . $UIDlist . "')";
                    }
                }

                // WIDの条件を追加
                if (!empty($WIDsearch)) {
                    // WID配列をカンマ区切りの文字列に変換
                    $WIDlist = implode("','", array_map(function($wid) use ($conn) {
                        return mysqli_real_escape_string($conn, $wid);
                    }, $WIDsearch));
                    
                    if ($WIDrange === 'not') {
                        $conditions[] = "WID NOT IN ('" . $WIDlist . "')";
                    } else {
                        $conditions[] = "WID IN ('" . $WIDlist . "')";
                    }
                }
                // 正誤の条件を追加
                if (isset($TFsearch)) {
                    $conditions[] = "TF = '" . mysqli_real_escape_string($conn, $TFsearch) . "'";
                }
                // 解答時間の条件を追加
                if (!empty($TimeRange) && !empty($Timesearch)) {
                    switch ($TimeRange) {
                        case 'above':
                            $conditions[] = "Time >= '" . mysqli_real_escape_string($conn, $Timesearch) . "'";
                            break;
                        case 'below':
                            $conditions[] = "Time <= '" . mysqli_real_escape_string($conn, $Timesearch) . "'";
                            break;
                        case 'range':
                            if (!empty($TimesearchMin) && !empty($TimesearchMax)) {
                                $conditions[] = "Time BETWEEN '" . mysqli_real_escape_string($conn, $TimesearchMin) . "' AND '" . mysqli_real_escape_string($conn, $TimesearchMax) . "'";
                            }
                            break;
                    }
                }

                // 条件が一つでもあればWHERE句を追加&SQLと条件をsessionに保存
                if (!empty($conditions)) {
                    $sql .= " WHERE " . join(" AND ", $conditions);
                    $_SESSION['conditions'] = $conditions;
                    echo $_SESSION['conditions'];
                    //echo "!emptyの条件を満たしています．<br>";
                }else{
                    //echo "emptyの条件を満たしていません。<br>";
                }
                // $_SESSION['conditions']が設定されているかどうかを確認します
                /*
                if (isset($_SESSION['conditions']) && !empty($_SESSION['conditions'])) {
                    //echo '$_SESSION["conditions"]が設定されています．<br>';
                    // ここに$_SESSION['conditions']を使用するコードを追加します
                } else {
                    //echo '$_SESSION["conditions"]は設定されていません．<br>';
                }
                    */
                $_SESSION['sql'] = $sql;
                echo $_SESSION['sql'];



                // SQL実行  
                $result = mysqli_query($conn, $sql);


            ?>
            <?php
                //デバッグ用のコード
                // フォームがPOSTされた場合
                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    echo "<h2>POSTされたデータ:</h2>";
                    
                    // UIDの選択値を表示
                    if (isset($_POST['UIDrange'])) {
                        //echo "UID範囲: " . htmlspecialchars($_POST['UIDrange']) . "<br>";
                    }

                    if (isset($_POST['UID'])) {
                        echo "選択されたUID:<br>";
                        foreach ($_POST['UID'] as $uid) {
                            //echo htmlspecialchars($uid) . "<br>";
                        }
                    }

                    // WIDの選択値を表示
                    if (isset($_POST['WIDrange'])) {
                        //echo "WID範囲: " . htmlspecialchars($_POST['WIDrange']) . "<br>";
                    }

                    if (isset($_POST['WID'])) {
                        echo "選択されたWID:<br>";
                        foreach ($_POST['WID'] as $wid) {
                            //echo htmlspecialchars($wid) . "<br>";
                        }
                    }

                    // 正誤の選択値を表示
                    if (isset($_POST['TFsearch'])) {
                        //echo "正誤: " . htmlspecialchars($_POST['TFsearch']) . "<br>";
                    }

                    // 解答時間の選択値を表示
                    if (isset($_POST['TimeRange'])) {
                        //echo "解答時間の範囲: " . htmlspecialchars($_POST['TimeRange']) . "<br>";
                    }

                    if (isset($_POST['Timesearch'])) {
                        //echo "解答時間: " . htmlspecialchars($_POST['Timesearch']) . "<br>";
                    }

                    if (isset($_POST['Timesearch-min']) && isset($_POST['Timesearch-max'])) {
                        //echo "解答時間の範囲: " . htmlspecialchars($_POST['Timesearch-min']) . " ～ " . htmlspecialchars($_POST['Timesearch-max']) . "<br>";
                    }
                }

            ?>
            <?php
                if($_SERVER["REQUEST_METHOD"] == "POST") {
                    if(isset($_POST['featureLabel'])) {
                        $allresult = array();
                        //取得したデータに応じてSQLを生成
                        $tempwhere = array();
                        $sql = "SELECT UID,WID,Understand,";
                        $selectcolumn = implode(",", $_POST['featureLabel']);
                        $sql.= $selectcolumn." FROM featurevalue";   //データベースの列名が入っている．
                        //csvfileに書く用の変数
                        $column_name = "UID,WID,Understand,";
                        $column_name.= $selectcolumn;
                        //デバッグ
                        echo "生成されたSQLは",$sql,"です<br>";
                        if(isset($_SESSION['group_students']) && !empty($_SESSION['group_students'])) {
                            $tempgroupsql = "";
                            $tempgroupsql .=  "SELECT UID,WID,Understand," . $selectcolumn . " FROM featurevalue1 WHERE UID IN (" . $_SESSION['group_students'] . ")";
                            echo "グループSQLは",$tempgroupsql,"です<br>";
                            $result_groupsql = mysqli_query($conn, $tempgroupsql);
                            while($row = mysqli_fetch_assoc($result_groupsql)){
                                $allresult_group[] = $row;
                            }
                            //csvfileに記述
                            $fp_group = fopen('./pydata/testdata.csv', 'w');
                            fputcsv($fp_group, explode(',', $column_name));
                            foreach($allresult_group as $row) {
                                fputcsv($fp_group, $row);
                            }
                            fclose($fp_group);
                            echo "csvファイル_groupを生成しました";

                        }

                        if (!empty($UIDsearch)) {
                            if ($UIDrange === 'not') {
                                $tempwhere[] = "UID NOT IN ('" . $UIDlist . "')";
                            } else {
                                $tempwhere[] = "UID IN ('" . $UIDlist . "')";
                            }
                        }
        
                        // WIDの条件を追加
                        if (!empty($WIDsearch)) {
                            if ($WIDrange === 'not') {
                                $tempwhere[] = "WID NOT IN ('" . $WIDlist . "')";
                            } else {
                                $tempwhere[] = "WID IN ('" . $WIDlist . "')";
                            }
                        }

                        // WHERE句の追加
                        if (!empty($tempwhere)) {
                            $sql .= " WHERE " . implode(" AND ", $tempwhere);
                        }

                        // 最終的なSQLをデバッグ用に出力
                        //echo "最終的な生成されたSQLは " . $sql . " です<br>";
                        // ここでSQLを実行する
                        $result = mysqli_query($conn, $sql);
                        //データベースの行数取得
                        $rows = mysqli_num_rows($result);
                        echo "抽出したデータ数は",$rows,"件です<br>";

                        while($row = mysqli_fetch_assoc($result)){
                            $allresult[] = $row;
                        }
                        //csvfileに記述
                        //カラム名のみ先にcsvに記述
                        $fp = fopen('./pydata/test.csv', 'w');
                        fputcsv($fp, explode(',', $column_name));
                        foreach($allresult as $row){
                            fputcsv($fp, $row);
                        }
                        fclose($fp);
                    }else{
                        //javascriptでアラートを出す．
                        echo '<script type="text/javascript">alert("データを選択してください");</script>';
                    }


                }
                
                
            ?>
            <section class="overview">
                <div align ="center">
                    <h2>クラス全体の概要</h2>
                </div>
                <font size = "5">
                    <div class="overview-contents">
                        <div id = "allstu-info">
                            <h3>■教師データ人数:
                                <?php
                                    // データベースから学生数を取得
                                    $Studentconut = "SELECT count(distinct UID) FROM featurevalue";
                                    if (!empty($conditions)) {
                                        $Studentconut .= " WHERE " . join(" AND ", $conditions);
                                    }
                                    $StudentResult = mysqli_query($conn, $Studentconut);
                                    echo $StudentResult->fetch_row()[0];
                                ?>
                                人
                            </h3>
                        </div>
                        <div id = "allques-info">
                            <h3>■全データ数:
                                <?php
                                    // データベースからデータ数を取得
                                    echo mysqli_num_rows($result);
                                ?>
                                件
                            </h3>  <!-- データベースからデータ数を取得-->
                        </div>
                    </div>
                </font>
            </section>
            <section id = "class-overview" class="overview">
                <div align ="center">
                    <h2>学習者グループ概要</h2>
                </div>
                <font size = "5">
                    <div class="overview-contents">
                        <div id = "groupstu-info">
                            <h3>■グルーピング学習者数:
                                <?php
                                    // URLに学習者IDが含まれているか確認
                                    if (isset($_SESSION['group_students']) && !empty($_SESSION['group_students'])) {
                                        // `students`パラメータから学習者IDを取得して配列に変換
                                        $student_ids = explode(',', $_SESSION['group_students']);

                                        // 学習者IDをカウント
                                        $student_count = count($student_ids);

                                        // 学習者数を表示
                                        echo $student_count . "人";
                                    } else {
                                        // URLに学習者情報が含まれていない場合のメッセージ
                                        echo "学習者グループはありません";
                                    }
                                ?>
                            </h3>
                        </div>
                        <div id = "groupques-info">
                            <h3>■全データ数:
                                <?php
                                    // データベースからデータ数を取得
                                    // URLに学習者IDが含まれているか確認
                                    if (isset($_SESSION['group_students']) && !empty($_SESSION['group_students'])) {
                                        // `students`パラメータから学習者IDを取得して配列に変換
                                        $student_ids = explode(',', $_SESSION['group_students']);

                                        // `UID`リストをSQLクエリ用の文字列に変換
                                        $uid_list = implode("','", array_map('intval', $student_ids));

                                        // データベースから指定されたUIDに基づいて行数を取得
                                        $query = "SELECT COUNT(*) AS data_count FROM featurevalue1 WHERE UID IN ('$uid_list')";
                                        $result = mysqli_query($conn, $query);


                                        // データ数を取得して表示
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $data_count = $row['data_count'];
                                            echo $data_count . "件";
                                        } else {
                                            echo "データがありません";
                                        }
                                    } else {
                                        // URLに学習者情報が含まれていない場合のメッセージ
                                        echo "データがありません";
                                    }
                                ?>
                            </h3>
                        </div>
                    </div>
                </font>
            </section>


            <section class="progress-chart">
                <h2>特徴量選択ボタン</h2>
                <div id = "feature-modal-area">
                    <h2 onclick="openFeatureModal()">特徴量</h2>
                </div>

            </section>
            
            <script>
                function openFeatureModal(){
                    document.getElementById("feature-modal").style.display = "block";
                }

                function closeFeatureModal(){
                    document.getElementById("feature-modal").style.display = "none";
                }
            </script>
        



            <div id = "feature-modal" class = "modal">
                <div class = "moda-content-machineLearning">
                    <span class = "close" onclick="closeFeatureModal()">&times;</span>
                    <form action="machineLearning_sample.php" method="post" target="_blank">
                        <table class="table2">
                            <tr>
                                <th>UID</th>
                                <td>
                                    <select name="UIDrange">
                                        <option value = "include">含む</option>
                                        <option value = "not">以外</option>
                                    </select>
                                </td>
                                <td>
                                    <!--ここにfeaturevalueテーブルのUIDをチェックボックスで表示-->
                                    <?php
                                        $sql = "SELECT distinct UID FROM featurevalue";
                                        $res = $conn->query($sql);
                                        $counter = 0; // カウンタを初期化
                                        while($rows = $res -> fetch_assoc()){
                                            echo "<input type='checkbox' name='UID[]' value = '{$rows['UID']}'>{$rows['UID']}";
                                            $counter++; // カウンタをインクリメント
                                            // カウンタが4の倍数になった時に改行を挿入
                                            if($counter % 4 == 0){
                                                echo "<br>";
                                            }
                                        }
                                        
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>WID</th>
                                <td>
                                    <select name="WIDrange">
                                        <option value = "include">含む</option>
                                        <option value = "not">以外</option>
                                    </select>
                                </td>
                                <td>
                                    <?php
                                        $sql = "SELECT distinct WID FROM featurevalue";
                                        $res = $conn->query($sql);
                                        $counter = 0;
                                        while($rows = $res -> fetch_assoc()){
                                            echo "<input type='checkbox' name='WID[]' value = '{$rows['WID']}'>{$rows['WID']}";
                                            $counter++;
                                            if($counter % 10 == 0){
                                                echo "<br>";
                                            }
                                        }
                                        
                                    ?>
                                </td>
                            </tr>
                            <!-- 分類器選択ボタン -->
                            <tr>
                                <th>分類器選択</th>
                                <td colspan="2">
                                    <button type="button" onclick="selectClassifier('A')">分類器A</button>
                                    <button type="button" onclick="selectClassifier('B')">分類器B</button>
                                    <button type="button" onclick="selectClassifier('C')">分類器C</button>
                                </td>
                            </tr>
                            <tr>
                                <th>解答全体</th>
                                <td colspan="2">
                                    <ul class = "itemgroup">
                                        <li><label for="featuretime"><input type = "checkbox" id = "featuretime" name = "featureLabel[]" value = "time">解答時間</label></li>
                                        <li><label for="featuredistance"><input type = "checkbox" id = "featuredistance" name = "featureLabel[]" value = "distance">移動距離</label></li>
                                        <li><label for="featurespeed"><input type = "checkbox" id ="featurespeed"  name = "featureLabel[]" value = "averageSpeed">平均速度</label></li>
                                        <li><label for="featuremaxspeed"><input type = "checkbox" id ="featuremaxspeed" name = "featureLabel[]" value = "maxSpeed">最大速度</label></li>
                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="totalstoptime"><input type = "checkbox" name = "featureLabel[]" value = "totalStopTime">合計静止時間</label></li>
                                        <li><label for="maxstoptime"><input type = "checkbox" name = "featureLabel[]" value = "maxStopTime">最大静止時間</label></li>

                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="stopcount"><input type = "checkbox" name = "featureLabel[]" value = "stopcount">静止回数</label></li>
                                        <li><label for="FromlastdropToanswerTime"><input type = "checkbox" name = "featureLabel[]" value = "FromlastdropToanswerTime">最終dropから解答終了までの時間</label></li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th>Uターン</th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="xUturncount"><input type = "checkbox" name = "featureLabel[]" value = "xUTurnCount">X軸Uターン回数</label></li>
                                        <li><label for="yUturncount"><input type = "checkbox" name = "featureLabel[]" value = "yUTurnCount">Y軸Uターン回数</label></li>
                                        <li><label for="xUturncountDD"><input type = "checkbox" name = "featureLabel[]" value = "xUTurnCountDD">次回DragまでのX軸Uターン回数</label></li>
                                        <li><label for="yUturncountDD"><input type = "checkbox" name = "featureLabel[]" value = "yUTurnCountDD">次回DragまでのY軸Uターン回数</label></li>
                                    </ul>
                                </td>
                            <tr>
                                <th>第一ドラッグ</th>
                                <td colspan="2">
                                    <ul class = "itemgroup">
                                        <li><label for="featurethinkingtime"><input type = "checkbox" name = "featureLabel[]" value = "thinkingTime">第一ドラッグ前時間</label></li>
                                        <li><label for="answeringtime"><input type = "checkbox" name = "featureLabel[]" value = "answeringTime">第一ドロップ後から解答終了を押すまでの時間</label></li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th>DD</th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="totalDDtime"><input type = "checkbox" name = "featureLabel[]" value = "totalDDTime">合計DD時間</label></li>
                                        <li><label for="maxDDtime"><input type = "checkbox" name = "featureLabel[]" value = "maxDDTime">最大DD時間</label></li>
                                        <li><label for="minDDtime"><input type = "checkbox" name = "featureLabel[]" value = "minDDTime">最小DD時間</label></li>
                                        <li><label for="DDcount"><input type = "checkbox" name = "featureLabel[]" value = "DDCount">DD回数</label></li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th>DD間</th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="maxDDintervaltime"><input type = "checkbox" name = "featureLabel[]" value = "maxDDIntervalTime">最大DD間時間</label></li>
                                        <li><label for="minDDintervaltime"><input type = "checkbox" name = "featureLabel[]" value = "minDDIntervalTime">最小DD間時間</label></li>
                                        <li><label for="totalDDintervaltime"><input type = "checkbox" name = "featureLabel[]" value = "totalDDIntervalTime">合計DD間時間</label></li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th>グループ化</th>
                                <td colspan="2">
                                    <ul class="itemgroup">
                                        <li><label for="groupingDDcount"><input type = "checkbox" name = "featureLabel[]" value = "groupingDDCount">グループ化中にDDした回数</label></li>
                                        <li><label for="groupingDDcountbool"><input type = "checkbox" name = "featureLabel[]" value = "groupingCountbool">グループ化の有無</label></li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <th>レジスタ</th>
                                <td colspan="2">
                                    <ul class="itemgroup">        
                                        <li><label for="register_move_count1"><input type = "checkbox" name = "featureLabel[]" value = "register_move_count1">レジスタ移動回数1</label></li>
                                        <li><label for="register_move_count2"><input type = "checkbox" name = "featureLabel[]" value = "register_move_count2">レジスタ移動回数2</label></li>
                                        <li><label for="register_move_count3"><input type = "checkbox" name = "featureLabel[]" value = "register_move_count3">レジスタ移動回数3</label></li>
                                        <li><label for="register_move_count4"><input type = "checkbox" name = "featureLabel[]" value = "register_move_count4">レジスタ移動回数4</label></li>
                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="register01count1"><input type = "checkbox" name = "featureLabel[]" value = "register01count1">レジスタ使用回数1</label></li>
                                        <li><label for="register01count2"><input type = "checkbox" name = "featureLabel[]" value = "register01count2">レジスタ使用回数2</label></li>
                                        <li><label for="register01count3"><input type = "checkbox" name = "featureLabel[]" value = "register01count3">レジスタ使用回数3</label></li>
                                        <li><label for="register01count4"><input type = "checkbox" name = "featureLabel[]" value = "register01count4">レジスタ使用回数4</label></li>
                                    </ul>
                                    <ul class="itemgroup">
                                        <li><label for="registerDDcount"><input type = "checkbox" name = "featureLabel[]" value = "registerDDCount">レジスタ内DD回数</label></li>
                                    </ul>
                                </td>
                            </tr>
                        <!--</div>-->
                        </table>
                        <input type="submit" id="machineLearningcons" value="機械学習">
                    </form>
                </div>
            </div>
            <script>
                // 全てのチェックボックスをリセット（選択を解除）
                function resetCheckboxes() {
                    const checkboxes = document.querySelectorAll("input[type='checkbox']");
                    checkboxes.forEach(checkbox => checkbox.checked = false);
                }

                // 分類器を選択した時に該当する特徴量をチェックする関数
                function selectClassifier(classifier) {
                    resetCheckboxes();  // まず全てのチェックボックスをリセット
                    
                    // 分類器Aの特徴量
                    if (classifier === 'A') {
                        document.querySelector("input[value='time']").checked = true;            // 解答時間
                        document.querySelector("input[value='distance']").checked = true;        // 移動距離
                        document.querySelector("input[value='averageSpeed']").checked = true;    // 平均速度
                        document.querySelector("input[value='maxSpeed']").checked = true;        // 最大速度
                        document.querySelector("input[value='thinkingTime']").checked = true;    // 第一ドラッグ前時間
                        document.querySelector("input[value='answeringTime']").checked = true;   // 第一ドロップ後から解答終了までの時間
                        document.querySelector("input[value='maxStopTime']").checked = true;     // 最大静止時間
                        document.querySelector("input[value='xUTurnCount']").checked = true;     // X軸Uターン回数
                        document.querySelector("input[value='yUTurnCount']").checked = true;     // Y軸Uターン回数
                        document.querySelector("input[value='DDCount']").checked = true;         // D&D回数
                        document.querySelector("input[value='maxDDTime']").checked = true;       // 最大D&D時間
                        document.querySelector("input[value='maxDDIntervalTime']").checked = true; // 最大D&D前時間
                        document.querySelector("input[value='totalDDIntervalTime']").checked = true; // 合計D&D間時間
                    }

                    // 分類器Bの特徴量（分類器Aに追加する特徴量）
                    if (classifier === 'B') {
                        selectClassifier('A');  // 分類器Aを選択
                        document.querySelector("input[value='groupingDDCount']").checked = true;  // グループ化中にDDした回数
                        document.querySelector("input[value='groupingCountbool']").checked = true;  // グループ化の有無
                    }

                    // 分類器Cの特徴量（分類器Aに追加する特徴量）
                    if (classifier === 'C') {
                        selectClassifier('A');  // 分類器Aを選択
                        document.querySelector("input[value='register_move_count1']").checked = true;  // レジスタ移動回数1
                        document.querySelector("input[value='register01count1']").checked = true;      // レジスタ使用回数1
                        document.querySelector("input[value='register_move_count2']").checked = true;  // レジスタ移動回数2
                        document.querySelector("input[value='register01count2']").checked = true;      // レジスタ使用回数2
                    }
                }
            </script>

            <section class="individual-details">
                <div class="machinelearning-result">
                    <h2>機械学習結果</h2>
                    <div class="contents">
                        <h3>各学習者の正解率</h3>
                        <?php
                            // データベース接続 (PDOを使用)
                            $dsn = 'mysql:host=127.0.0.1;dbname=2019su1;charset=utf8';
                            $user = 'root';
                            $password = '8181saisaI';
                            try {
                                $pdo = new PDO($dsn, $user, $password);
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            } catch (PDOException $e) {
                                echo "データベース接続エラー: " . $e->getMessage();
                                exit();
                            }
                            if($_SERVER["REQUEST_METHOD"] == "POST"){
                                $pyscript = "./machineLearning/sampleSHAP.py";
                                $countF = 0;
                                exec("py ".$pyscript." 2>&1", $output, $status);

                                if($status != 0){
                                    echo "実行エラー: ステータスコード " . $status;
                                } else {
                                    // Pythonの実行が成功したら、結果のCSVを読み込む
                                    $csvFile = './machineLearning/results_actual.csv';
                                    $metrics_file = './machineLearning/evaluation_metrics.json';
                                    if (file_exists($metrics_file)) {
                                        $metrics = json_decode(file_get_contents($metrics_file), true);
                                    } else {
                                        echo "評価指標のデータが見つかりません。";
                                    }
                                    if (($handle = fopen($csvFile, "r")) !== FALSE) {
                                        // CSVファイル全体を読み込む
                                        $csvData = [];
                                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                                            $csvData[] = $data; // 全てのデータを配列に保存
                                        }
                                        fclose($handle);

                                        // 最初の1行はヘッダーとして取得
                                        $header = array_shift($csvData); // 1行目（ヘッダー）を取り出す

                                        // 全データを取得
                                        $topData = $csvData;  // 全データを $topData に割り当て
                                        // 正解率、不正解率を保存している配列
                                        $studentStats = []; // UIDをキーにしたデータ構造
                                        // UIDごとの迷い率を計算するためにデータを集計
                                        $uidData = []; // UIDごとのデータを格納
                                        foreach ($csvData as $data) {
                                            $uid = $data[0];
                                            $understand = $data[2]; // Predictes_Understand カラム

                                            if (!isset($uidData[$uid])) {
                                                $uidData[$uid] = [
                                                    'total' => 0,
                                                    'hesitate' => 0,
                                                ];
                                            }

                                            $uidData[$uid]['total']++;
                                            if ($understand == 2) { // 迷い有り
                                                $uidData[$uid]['hesitate']++;
                                            }
                                        }

                                        // データベースから名前や正解率、不正解率を取得し、迷い率を追加
                                        foreach ($uidData as $uid => $counts) {
                                            // 名前を取得
                                            $stmt = $pdo->prepare('SELECT Name FROM students WHERE UID = :uid');
                                            $stmt->execute(['uid' => $uid]);
                                            $nameResult = $stmt->fetch(PDO::FETCH_ASSOC);
                                            $name = $nameResult ? $nameResult['Name'] : "Unknown";

                                            // 正解率、不正解率を計算 (linedataテーブルを使用)
                                            $stmt = $pdo->prepare('SELECT 
                                                COUNT(*) AS total_answers,
                                                SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) AS correct_answers
                                                FROM linedata WHERE UID = :uid');
                                            $stmt->execute(['uid' => $uid]);
                                            $scoreData = $stmt->fetch(PDO::FETCH_ASSOC);

                                            $totalAnswers = $scoreData['total_answers'];
                                            $correctAnswers = $scoreData['correct_answers'];
                                            $accuracyRate = $totalAnswers > 0 ? ($correctAnswers / $totalAnswers) * 100 : 0;
                                            $notAccuracyRate = 100 - $accuracyRate;

                                            // 迷い率を計算
                                            $total = $counts['total'];
                                            $hesitate = $counts['hesitate'];
                                            $hesitationRate = ($total > 0) ? ($hesitate / $total) * 100 : 0;

                                            // 配列にデータを追加
                                            $studentStats[$uid] = [
                                                'uid' => $uid,
                                                'name' => $name,
                                                'accuracy' => number_format($accuracyRate, 2),
                                                'notAccuracy' => number_format($notAccuracyRate, 2),
                                                'hesitation' => number_format($hesitationRate, 2),
                                            ];
                                        }


                                        // 以下、データ表示の処理
                                        ?>
                                        <div id = "table-container">
                                            <table border="1" id="results-table" class="table2">
                                                <tr>
                                        <?php
                                        foreach ($header as $col_name) {
                                            if($col_name == "Predicted_Understand"){
                                                echo "<th>迷いの有無</th>";
                                            }else{
                                                echo "<th>" . htmlspecialchars($col_name) . "</th>";
                                            }
                                            
                                        }
                                        echo "<th>正誤</th>";
                                        echo '</tr>';

                                        foreach ($topData as $data) {
                                            $uid = $data[0];
                                            $wid = $data[1];
                                            $understand = $data[2];

                                            // linedata テーブルから該当する UID と WID に基づいて TF を取得
                                            $stmt = $pdo->prepare('SELECT TF FROM linedata WHERE UID = :uid AND WID = :wid');
                                            $stmt->execute(['uid' => $uid, 'wid' => $wid]);
                                            $tf_result = $stmt->fetch(PDO::FETCH_ASSOC);

                                            $tf_value = $tf_result ? $tf_result['TF'] : 'N/A';

                                            // HTMLテーブルに行を追加
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($uid) . "</td>";
                                            echo "<td>" . htmlspecialchars($wid) . "</td>";
                                            echo "<td>";
                                            if ($understand == 4) {
                                                echo "迷い無し";
                                            } elseif ($understand == 2) {
                                                echo "<span style='color: red; font-weight: bold;'>迷い有り</span>";
                                            } else {
                                                echo "不明";
                                            }
                                            echo "</td>";

                                            echo "<td>";
                                            if ($tf_value === '1') {
                                                echo "正解";
                                            } elseif ($tf_value === '0') {
                                                echo "<span style='color: red; font-weight: bold;'>不正解</span>";
                                            } else {
                                                echo "N/A";
                                            }
                                            echo "</tr>";
                                        }
                                        echo '</table>';
                                    } else {
                                        echo "結果のCSVファイルを読み込めませんでした。";
                                    }
                                }
                            }
                        ?>
                        </div>
                    </div>
                </div>
                <div class="class-data" id="group-data-container">
                    <div class="class-card">
                        <div class="chart-row">
                            <canvas id="result-Chart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="class-row-feature">
                    <div id = "feature-importance">
                        <h2>特徴量の重要度</h2>
                        <canvas id = "feature-Chart" width="300px",height = "300px"></canvas>
                        <!--ここに円グラフ-->
                        <?php
                        /*
                        $lately_mlresul_sql = "SELECT * FROM ml_results ORDER BY id DESC LIMIT 1";
                        $lately_mlresul_res = mysqli_query($conn, $lately_mlresul_sql);
                        while($lately_mlresul_rows = $lately_mlresul_res -> fetch_assoc()){
                            $lately_mlresul_id = $lately_mlresul_rows['id'];
                            $lately_mlresul_modelname = $lately_mlresul_rows['model_name'];
                            $lately_mlresul_featurename = $lately_mlresul_rows['featurename'];
                            $lately_mlresul_gini_results = $lately_mlresul_rows['gini_results'];
                            $lately_mlresul_acc_result = $lately_mlresul_rows['acc_result'];
                            $lately_mlresul_pre_result_y = $lately_mlresul_rows['pre_result_y'];
                            $lately_mlresul_pre_result_n = $lately_mlresul_rows['pre_result_n'];
                            $lately_mlresul_rec_result_y = $lately_mlresul_rows['rec_result_y'];
                            $lately_mlresul_rec_result_n = $lately_mlresul_rows['rec_result_n'];
                            $lately_mlresul_f1_score_y = $lately_mlresul_rows['f1_score_y'];
                            $lately_mlresul_f1_score_n = $lately_mlresul_rows['f1_score_n'];
                        }
                            */

                        ?>
                        <script>
                            /*
                            const labelColorMap = {
                                //基本情報（赤，黄色）
                                'time': 'red',
                                "distance": 'orange',
                                "averageSpeed": 'gold', // 他のラベルの場合
                                "maxSpeed": 'yellow',
                                "totalStopTime":'peru',
                                "maxStopTime":'darkgoldenrod',
                                "stopcount":'chocolate',
                                "FromlastdropToanswerTime":'orengered',
                                //Uターン（青）
                                "xUTurnCount":'blue',
                                "yUTurnCount":'aqua',
                                "xUTurnCountDD":'dodgerblue',
                                "yUTurnCountDD":'turquoise',
                                //第一ドラッグ（ピンク）
                                "thinkingTime":'pink',
                                "answeringTime":'magenta',
                                //DD関連（紫）
                                "totalDDTime" :'purple',
                                "maxDDTime" :'indigo',
                                "minDDTime" :'bluevoilet',
                                "DDcount" :'mediumorchid',
                                "maxDDIntervalTime" :'violet',
                                "minDDIntervalTime" :'orchid',
                                "totalDDIntervalTime" :'slateblue',
                                "registerDDCount" :'darkslateblue',
                                //グルーピング関連(黒)
                                "groupingDDCount" :'dimgray',
                                "groupingCountbool" :'silver',
                                //レジスタ関連(緑)
                                "register_move_count1" :'green',
                                "register_move_count2" :'forestgreen',
                                "register01count1" :'seagreen',
                                "register01count2" :'mediumseagreen',
                            };
                            const ctx = document.getElementById('feature-Chart');
                            const lately_mlresul_gini_results = '<?php echo $lately_mlresul_gini_results; ?>'
                            console.log(lately_mlresul_gini_results);
                            console.log(typeof lately_mlresul_gini_results);
                            const jsonlately = JSON.parse(lately_mlresul_gini_results);
                            // 1. ラベルとデータを取得してペアにする
                            const entries = Object.entries(jsonlately);

                            // 2. データの値に基づいて昇順にソートする
                            entries.sort((a, b) => b[1] - a[1]);

                            // 3. ソートされたペアをラベルとデータに分離する
                            const sortedLabels = entries.map(entry => entry[0]);
                            const sortedData = entries.map(entry => entry[1]);
                            const myPieChart = new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: sortedLabels,
                                datasets: [{
                                    data: sortedData,
                                    backgroundColor: function(context) {
                                        const label = context.chart.data.labels[context.dataIndex];
                                        return labelColorMap[label] || 'grey'; // デフォルト色は灰色
                                    },
                                }],
                            },
                            options: {
                                plugins: {
                                    title: {
                                        display: true,
                                        text: '81,35%'
                                    }
                                }
                            }
                        });
                        */
                        </script>
                    </div>
                    <div id = "table-content">
                        <table class="table2">
                            <thead>
                                <tr>
                                    <th>評価指標</th>
                                    <th>迷い有り</th>
                                    <th>迷い無し</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>適合率 (Precision)</td>
                                    <td><?php echo isset($metrics['precision_y']) ? number_format($metrics['precision_y'] * 100, 2) . '%' : 'N/A'; ?></td>
                                    <td><?php echo isset($metrics['precision_n']) ? number_format($metrics['precision_n'] * 100, 2) . '%' : 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td>再現率 (Recall)</td>
                                    <td><?php echo isset($metrics['recall_y']) ? number_format($metrics['recall_y'] * 100, 2) . '%' : 'N/A'; ?></td>
                                    <td><?php echo isset($metrics['recall_n']) ? number_format($metrics['recall_n'] * 100, 2) . '%' : 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td>F値 (F1 Score)</td>
                                    <td><?php echo isset($metrics['f1score_y']) ? number_format($metrics['f1score_y'] * 100, 2) . '%' : 'N/A'; ?></td>
                                    <td><?php echo isset($metrics['f1score_n']) ? number_format($metrics['f1score_n'] * 100, 2) . '%' : 'N/A'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                        <div id="attention-value">
                        適合率:作成したモデルで推定された結果が実際にその通りである割合<br>
                        再現率:実際の結果のうち，正しくモデルが推定した割合<br>
                        F値:適合率と再現率の調和平均<br>
                        
                        </div>
                    </div>
                    
                </div>
            </section>
            
            <script>
                function createDualAxisChart(ctx, labels, data1, data2, label1, label2, color1, color2, yText1, yText2, chartArray, chartIndex) {
                    // 既存のチャートがある場合は破棄
                    if (chartArray[chartIndex]) {
                        chartArray[chartIndex].destroy();
                    }

                    // 新しいチャートを作成し、指定された配列に保存
                    chartArray[chartIndex] = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: label1,
                                    data: data1,
                                    backgroundColor: color1,
                                    borderColor: color1,
                                    yAxisID: 'y1',
                                    borderWidth: 1
                                },
                                {
                                    label: label2,
                                    data: data2,
                                    backgroundColor: color2,
                                    borderColor: color2,
                                    yAxisID: 'y2',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            maintainAspectRatio: false,
                            responsive: true,
                            scales: {
                                x: {
                                    title: {
                                        display: true,
                                        text: 'ユーザー名',
                                        font: {
                                            size: 20
                                        }
                                    },
                                    ticks: {
                                        font: {
                                            size: 16
                                        }
                                    }
                                },
                                y1: {
                                    title: {
                                        display: true,
                                        text: yText1,
                                        font: {
                                            size: 20
                                        }
                                    },
                                    ticks: {
                                        font: {
                                            size: 16
                                        }
                                    },
                                    position: 'left',
                                    beginAtZero: true
                                },
                                y2: {
                                    title: {
                                        display: true,
                                        text: yText2,
                                        font: {
                                            size: 20
                                        }
                                    },
                                    ticks: {
                                        font: {
                                            size: 16
                                        }
                                    },
                                    position: 'right',
                                    beginAtZero: true
                                }
                            },
                            plugins: {
                                legend: {
                                    labels: {
                                        font: {
                                            size: 20
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                // PHPからstudentStatsを取得
                const studentData = <?php echo json_encode(array_values($studentStats)); ?>;
                console.log(studentData); // デバッグ用

                if (studentData.length > 0) {
                    const labels = studentData.map(data => data.name);
                    const notAccuracyRates = studentData.map(data => parseFloat(data.notAccuracy));
                    const hesitationRates = studentData.map(data => parseFloat(data.hesitation));

                    const ctx = document.getElementById('result-Chart').getContext('2d');
                    const chartArray = []; // チャート配列を管理
                    createDualAxisChart(
                        ctx,
                        labels,
                        notAccuracyRates,
                        hesitationRates,
                        '不正解率 (%)',
                        '迷い率 (%)',
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        '不正解率 (%)',
                        '迷い率 (%)',
                        chartArray,
                        0 // インデックスは0で管理
                    );
                } else {
                    document.getElementById('result-Chart').textContent = "まだ迷い推定が行われていません";
                }
            </script>
        </main>
    </div>
</body>
</html>

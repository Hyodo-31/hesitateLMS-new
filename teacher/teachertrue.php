<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('teachertrue.php_7行目_教育用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="notification-script.js"></script>
</head>
<body>
    <?php
        //session_start();  これもlang.phpでセッションスタートしてるから
        require "../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo"><?= translate('teachertrue.php_21行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <li><a href="#"><?= translate('teachertrue.php_24行目_ホーム') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('teachertrue.php_25行目_迷い推定・機械学習') ?></a></li>
                <!--
                <li><a href="Analytics/studentAnalytics.php"><?= translate('teachertrue.php_27行目_学生分析') ?></a></li>
                <li><a href="Analytics/questionAnalytics.php"><?= translate('teachertrue.php_28行目_問題分析') ?></a></li>
-->
                <li><a href="register-student.php"><?= translate('teachertrue.php_30行目_新規学生登録') ?></a></li>
                <li><a href="../logout.php"><?= translate('teachertrue.php_31行目_ログアウト') ?></a></li>

            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="#"><?= translate('teachertrue.php_39行目_ホーム') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('teachertrue.php_40行目_迷い推定・機械学習') ?></a></li>
                <!--
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
-->
                <li><a href="register-student.php"><?= translate('teachertrue.php_45行目_新規学生登録') ?></a></li>
            </ul>
        </aside>
        <main>

        <div class="notifications">
            <h2><?= translate('teachertrue.php_51行目_お知らせ') ?></h2>
            <div class="notify-scroll">
                <?php
                    // 最新5件ではなく、すべてのお知らせを取得
                    $result = $conn->query("SELECT id, subject, content FROM notifications ORDER BY created_at DESC");
                    while ($row = $result->fetch_assoc()) {
                        echo "<div class='notification-item' data-id='{$row['id']}'>";
                        echo "<h3 class='notification-title'>{$row['subject']}</h3>";
                        echo "<p class='notification-content' style='display: none;'>{$row['content']}</p>";
                        echo "</div>";
                    }
                    $result->free();
                    $conn->close();
                ?>
            </div>
            <div id="notifymake-botton" class="button1">
                <a href='create-notification.php'><?= translate('teachertrue.php_67行目_お知らせ作成') ?></a>
            </div>
        </div>

            <div class="class-overview">
                <h2><?= translate('teachertrue.php_72行目_グループ別データ') ?></h2>
                <div id= "button-groupstudent-making" class="button1">
                    <a href='create-student-group.php'><?= translate('teachertrue.php_74行目_学習者グルーピング作成') ?></a>
                </div>
                <div class="class-data">
                    <?php
                        require "../dbc.php";
                        $teacher_id = $_SESSION['MemberID'];

                        $stmt = $conn->prepare("SELECT * FROM `groups` WHERE TID = ?");
                        if (!$stmt) {
                            die("prepare() failed: " . $conn->error);
                        }
                        $stmt->bind_param("i", $teacher_id);
                        $stmt->execute();
                        if (!$stmt) {
                            die("prepare() failed: " . $conn->error);
                        }
                        $result = $stmt->get_result();
                        
                        $groups = [];
                        if($result->num_rows > 0) {
                            //学習者グループがある場合
                            while($row = $result->fetch_assoc()) {
                                $group_id = $row['group_id'];
                                $group_name = $row['group_name'];

                                $stmt_groupmember = $conn->prepare("SELECT * FROM group_members WHERE group_id = ?");
                                $stmt_groupmember->bind_param("i", $group_id);
                                $stmt_groupmember->execute();
                                $result_groupmember = $stmt_groupmember->get_result();
                                $group_students = [];
                                while($member = $result_groupmember->fetch_assoc()) {
                                    $students_id = $member['uid'];
                                    //学生ごとの正解数と解答数を取得
                                    $stmt_scores = $conn -> prepare("SELECT 
                                                            COUNT(*) AS total_answers,
                                                            SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) AS correct_answers,
                                                            SUM(Time) AS total_time
                                                            FROM linedata WHERE uid = ?");
                                    $stmt_scores->bind_param("i", $students_id);
                                    $stmt_scores->execute();
                                    $result_scores = $stmt_scores->get_result();
                                    $score_data = $result_scores->fetch_assoc();
                                    $correct_answers = $score_data['correct_answers'];
                                    $total_answers = $score_data['total_answers'];
                                    $total_time = $score_data['total_time'];
                                    $accuracy_rate = $total_answers > 0 ? number_format(($correct_answers / $total_answers) * 100,2) : 0;
                                    $notaccuracy_rate = 100 - $accuracy_rate;
                                    $accuracy_time = $total_answers > 0 ? number_format(($total_time / 1000) / $total_answers,2) : 0;

                                    $stmt_scores->close();
                                    $result_scores->free(); // メモリ解放

                                    //学生ごとの名前を取得
                                    $stmt_name = $conn->prepare("SELECT Name FROM students WHERE uid = ?");
                                    $stmt_name->bind_param("i", $students_id);
                                    $stmt_name->execute();
                                    $result_name = $stmt_name->get_result();
                                    $name_data = $result_name->fetch_assoc();
                                    $name = $name_data['Name'];
                                    $stmt_name->close();
                                    $result_name->free();

                                    //学生ごとの正解数を格納
                                    $group_students[] = [
                                        'student_id' => $students_id,
                                        'name' => $name,
                                        'accuracy' => $accuracy_rate,
                                        'notaccuracy' => $notaccuracy_rate,
                                        'time' => $accuracy_time
                                    ];
                                }
                                // グループデータを配列に追加
                                $groups[] = [
                                    'group_name' => $group_name,
                                    'group_id' => $group_id,
                                    'students' => $group_students
                                ];
                                $stmt_groupmember->close();
                                $result_groupmember->free();
                            }
                        }else{
                            // 学習者グループがない場合
                            echo "<p>><?= translate('teachertrue.php_156行目_学習者グループがありません') ?><</p>";
                        }

                        $stmt->close();
                        $conn->close();
                       
                    ?>
                    <!-- PHPからJavaScriptへグループデータを渡す -->
                    <script>
                        const groupData = <?php echo json_encode($groups); ?>;
                    </script>
                    
                    <div class="class-data" id="group-data-container"></div>
                </div>
            </div>
            <!-- 特徴量選択モーダル -->
            <div id="feature-modal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeFeatureModal()">&times;</span>
                    <h3><?= translate('teachertrue.php_175行目_特徴量を選択してください') ?></h3>
                    <form id="feature-form">
                        <label><input type="checkbox" name="feature" value="notaccuracy">><?= translate('teachertrue.php_177行目_不正解率 (%)') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="Time">><?= translate('teachertrue.php_178行目_解答時間 (秒)') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="distance">><?= translate('teachertrue.php_179行目_距離') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="averageSpeed">><?= translate('teachertrue.php_180行目_平均速度') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="maxSpeed">><?= translate('teachertrue.php_181行目_最高速度') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="thinkingTime">><?= translate('teachertrue.php_182行目_考慮時間') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="answeringTime">><?= translate('teachertrue.php_183行目_第一ドロップ後解答時間') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="totalStopTime">><?= translate('teachertrue.php_184行目_合計静止時間') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="maxStopTime">><?= translate('teachertrue.php_185行目_最大静止時間') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="totalDDIntervalTime">><?= translate('teachertrue.php_186行目_合計DD間時間') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="maxDDIntervalTime">><?= translate('teachertrue.php_187行目_最大DD間時間') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="maxDDTime">><?= translate('teachertrue.php_188行目_合計DD時間') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="minDDTime">><?= translate('teachertrue.php_189行目_最小DD時間') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="DDCount">><?= translate('teachertrue.php_190行目_合計DD回数') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="groupingDDCount">><?= translate('teachertrue.php_191行目_グループ化DD回数') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="groupingCountbool">><?= translate('teachertrue.php_192行目_グループ化有無') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="xUturnCount">><?= translate('teachertrue.php_193行目_x軸Uターン回数') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="yUturnCount">><?= translate('teachertrue.php_194行目_y軸Uターン回数') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="register_move_count1">><?= translate('teachertrue.php_195行目_レジスタ➡レジスタへの移動回数') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="register_move_count2">><?= translate('teachertrue.php_196行目_レジスタ➡レジスタ外への移動回数') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="register_move_count3">><?= translate('teachertrue.php_197行目_レジスタ外➡レジスタへの移動回数') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="register01count1">><?= translate('teachertrue.php_198行目_レジスタ➡レジスタへの移動有無') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="register01count2">><?= translate('teachertrue.php_199行目_レジスタ外➡レジスタへの移動有無') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="register01count3">><?= translate('teachertrue.php_200行目_レジスタ外➡レジスタへの移動有無') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="registerDDCount">><?= translate('teachertrue.php_201行目_レジスタ外➡レジスタへの移動有無') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="xUturnCountDD">><?= translate('teachertrue.php_202行目_x軸UターンDD回数') ?><</label><br>
                        <label><input type="checkbox" name="feature" value="yUturnCountDD"><?= translate('teachertrue.php_203行目_y軸UターンDD回数') ?></label><br>
                        <label><input type="checkbox" name="feature" value="FromlastdropToanswerTime">><?= translate('teachertrue.php_204行目_レジスタ外➡レジスタへの移動有無DD') ?><</label><br>
                        <button type="button" id="apply-features-btn"><?= translate('teachertrue.php_205行目_適用') ?></button>
                    </form>
                </div>
            </div>
            <!-- クラスタリング特徴量選択モーダル -->
            <div id="clustering-modal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeClusteringModal()">&times;</span>
                    <form id="clustering-feature-form">
                    <h3 style = "display : none;"><?= translate('teachertrue.php_214行目_クラスタ数を入力してください') ?></h3>
                        <input type="number" id="clustering-input" min="1" max="10" value="2" style = "display: none;">
                    <h3 style = "display : none;"><?= translate('teachertrue.php_216行目_クラスタリング手法を選択してください') ?></h3>
                    <label for="clustering-method" style = "display: none;"><?= translate('teachertrue.php_217行目_クラスタリング手法:') ?></label>
                        <select id="clustering-method" style = "display: none;">
                            <option value="kmeans">K-Means</option>
                            <option value="xmeans">X-Means</option>
                            <option value="gmeans">G-Means</option>
                        </select>
                    <h3><?= translate('teachertrue.php_223行目_クラスタリング特徴量を選択してください') ?></h3>
                    
                        <!--<label><input type="checkbox" name="feature" value="notaccuracy">><?= translate('teachertrue.php_225行目_不正解率 (%)') ?><</label><br>-->
                        <label title = "問題解答にかかった時間"><input type="checkbox" name="feature" value="Time">><?= translate('teachertrue.php_226行目_解答時間 (秒)') ?><</label><br>
                        <label title = "問題解答中にマウスカーソルを移動した距離（ピクセル単位）"><input type="checkbox" name="feature" value="distance">><?= translate('teachertrue.php_227行目_距離') ?><</label><br>
                        <label title = "問題解答中のマウスカーソルの速度の平均"><input type="checkbox" name="feature" value="averageSpeed">><?= translate('teachertrue.php_228行目_平均速度') ?><</label><br>
                        <label title = "問題解答中のマウスカーソルの速度の最大値"><input type="checkbox" name="feature" value="maxSpeed">><?= translate('teachertrue.php_229行目_最大速度') ?><</label><br>
                        <label title = "解答開始から最初のドラッグが行われるまでの時間"><input type="checkbox" name="feature" value="thinkingTime">><?= translate('teachertrue.php_230行目_第一ドラッグ前時間') ?><</label><br>
                        <label title = "最初のドラッグから解答終了までの時間"><input type="checkbox" name="feature" value="answeringTime">><?= translate('teachertrue.php_231行目_第一ドラッグ後時間') ?><</label><br>
                        <label title = "マウスカーソルが静止していた時間の合計値"><input type="checkbox" name="feature" value="totalStopTime">><?= translate('teachertrue.php_232行目_合計静止時間') ?><</label><br>
                        <label title = "マウスカーソルが静止していた時間の最大値"><input type="checkbox" name="feature" value="maxStopTime">><?= translate('teachertrue.php_233行目_最大静止時間') ?><</label><br>
                        <label title = "D&Dから次のD&Dまでの時間の合計値"><input type="checkbox" name="feature" value="totalDDIntervalTime">><?= translate('teachertrue.php_234行目_合計D&D間時間') ?><</label><br>
                        <label title = "D&Dから次のD&Dまでの時間の最大値"><input type="checkbox" name="feature" value="maxDDIntervalTime">><?= translate('teachertrue.php_235行目_最大D&D間時間') ?><</label><br>
                        <label title = "D&D中の時間の合計値"><input type="checkbox" name="feature" value="maxDDTime">><?= translate('teachertrue.php_236行目_合計D&D時間') ?><</label><br>
                        <label title = "D&D中の時間の最小値"><input type="checkbox" name="feature" value="minDDTime">><?= translate('teachertrue.php_237行目_最小D&D時間') ?><</label><br>
                        <label title = "D&Dが行われた回数"><input type="checkbox" name="feature" value="DDCount">><?= translate('teachertrue.php_238行目_合計D&D回数') ?><</label><br>
                        <label title = "グルーピングが使用された回数"><input type="checkbox" name="feature" value="groupingDDCount">><?= translate('teachertrue.php_239行目_グループ化回数') ?><</label><br>
                        <label title = "グルーピング機能の使用の有無"><input type="checkbox" name="feature" value="groupingCountbool">><?= translate('teachertrue.php_240行目_グループ化有無') ?><</label><br>
                        <label title = "横軸方向にUターンが行われた回数"><input type="checkbox" name="feature" value="xUturnCount">><?= translate('teachertrue.php_241行目_x軸Uターン回数') ?><</label><br>
                        <label title = "縦軸方向にUターンが行われた回数"><input type="checkbox" name="feature" value="yUturnCount">><?= translate('teachertrue.php_242行目_y軸Uターン回数') ?><</label><br>
                        <label title = "マウスカーソルがレジスタからレジスタに移動した回数"><input type="checkbox" name="feature" value="register_move_count1">><?= translate('teachertrue.php_243行目_レジスタ➡レジスタへの移動回数') ?><</label><br>
                        <label title = "マウスカーソルがレジスタからレジスタ外に移動した回数"><input type="checkbox" name="feature" value="register_move_count2">><?= translate('teachertrue.php_244行目_レジスタ➡レジスタ外への移動回数') ?><</label><br>
                        <label title = "マウスカーソルがレジスタ外からレジスタに移動した回数"><input type="checkbox" name="feature" value="register_move_count3">><?= translate('teachertrue.php_245行目_レジスタ外➡レジスタへの移動回数') ?><</label><br>
                        <label title = "マウスカーソルがレジスタからレジスタに移動したかの有無"><input type="checkbox" name="feature" value="register01count1">><?= translate('teachertrue.php_246行目_レジスタ➡レジスタへの移動有無') ?><</label><br>
                        <label title = "マウスカーソルがレジスタからレジスタ外に移動したかの有無"><input type="checkbox" name="feature" value="register01count2">><?= translate('teachertrue.php_247行目_レジスタ➡レジスタ外への移動有無') ?><</label><br>
                        <label title = "マウスカーソルがレジスタ外からレジスタに移動したかの有無"><input type="checkbox" name="feature" value="register01count3">><?= translate('teachertrue.php_248行目_レジスタ外➡レジスタへの移動有無') ?><</label><br>
                        <label title = "レジスタを触れた移動回数の合計"><input type="checkbox" name="feature" value="registerDDCount">><?= translate('teachertrue.php_249行目_レジスタに関する合計の移動回数') ?><</label><br>
                        <label title = "D&D中に横軸方向にUターンが行われた回数"><input type="checkbox" name="feature" value="xUturnCountDD">><?= translate('teachertrue.php_250行目_x軸UターンD&D回数') ?><</label><br>
                        <label title = "D&D中に縦軸方向にUターンが行われた回数"><input type="checkbox" name="feature" value="yUturnCountDD"><?= translate('teachertrue.php_251行目_y軸UターンD&D回数') ?></label><br>
                        <label title = "最終ドロップから解答終了までの時間"><input type="checkbox" name="feature" value="FromlastdropToanswerTime">><?= translate('teachertrue.php_252行目_最終ドロップ後時間') ?><</label><br>
                        <!-- 必要な特徴量を追加 -->
                        <button type="button" id="apply-clustering-btn"><?= translate('teachertrue.php_254行目_適用') ?></button>
                    </form>
                </div>
            </div>


            <script>
                // グループの学習者情報をURLパラメータとして渡す
                function openEstimatePage(groupIndex) {
                    const group = groupData[groupIndex];
                    if (!group || !group.students) {
                        alert("<?= translate('teachertrue.php_265行目_グループに学習者が登録されていません。') ?>");
                        return;
                    }

                    // 学習者IDをカンマ区切りで結合してURLパラメータとして追加
                    const studentIds = group.students.map(student => student.student_id).join(',');
                    const url = `machineLearning_sample.php?students=${encodeURIComponent(studentIds)}`;

                    // machineLearning_sample.php にリダイレクト
                    window.location.href = url;
                }
                // 全体の成績グラフの学習者情報をURLパラメータとして渡す
                function openClassEstimatePage(classIndex) {
                    const classInfo = classData[classIndex];
                    if (!classInfo || !classInfo.class_students) {
                        alert("クラスに学習者が登録されていません。");
                        return;
                    }

                    // 学習者IDをカンマ区切りで結合してURLパラメータとして追加
                    const studentIds = classInfo.class_students.map(student => student.student_id).join(',');
                    const url = `machineLearning_sample.php?students=${encodeURIComponent(studentIds)}`;

                    // machineLearning_sample.php にリダイレクト
                    window.location.href = url;
                }

                
                // クラス別グラフを管理する配列
                let existingClassCharts = [];
                // 全体の成績グラフを管理する配列
                let existingOverallCharts = [];

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




                // 各グループのデータに基づいてグラフを作成
                document.addEventListener("DOMContentLoaded", function () {
                    const container = document.getElementById('group-data-container');

                    groupData.forEach((group, index) => {
                        const groupContainer = document.createElement('div');
                        groupContainer.classList.add('class-card');
                        groupContainer.innerHTML = `
                            <h3>${group.group_name}
                                <button onclick="openFeatureModal(${index}, false)"><?= translate('teachertrue.php_404行目_グラフ描画特徴量') ?></button>
                                <button onclick="openEstimatePage(${index})"><?= translate('teachertrue.php_405行目_迷い推定') ?></button>

                            </h3>
                            <div class="chart-row">
                                <canvas id="dual-axis-chart-${index}"></canvas>
                            </div>
                        `;
                        

                        container.appendChild(groupContainer);

                        const labels = group.students.map(student => student.name);
                        const notaccuracyData = group.students.map(student => student.notaccuracy);
                        const timeData = group.students.map(student => student.time);

                        createDualAxisChart(
                            document.getElementById(`dual-axis-chart-${index}`).getContext('2d'),
                            labels,
                            notaccuracyData,
                            timeData,
                            '不正解率(%)',
                            '解答時間(秒)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 99, 132, 0.6)',
                            '不正解率(%)',
                            '解答時間(秒)',
                            existingClassCharts,  // クラス別グラフ用の配列
                            index
                        );
                    });
                });



                let selectedGroupIndex;
                //モーダルを開く
                // モーダルを開く - クラス別データ用
                function openFeatureModal(index, isOverall) {
                    console.log('index:', index);
                    selectedGroupIndex = index;
                    document.getElementById('feature-modal').style.display = 'block';

                    // 特徴量選択後の適用ボタンに対して適切な配列とインデックスを設定
                    document.getElementById('apply-features-btn').onclick = function() {
                        applySelectedFeatures(isOverall ? existingOverallCharts : existingClassCharts, index, isOverall);
                    };
                }

                // 全体の成績に対しても `openClassFeatureModal` 関数を同様に適用
                function openClassFeatureModal(index) {
                    openFeatureModal(index, true);
                }

                //モーダルを閉じる
                function closeFeatureModal() {
                    document.getElementById('feature-modal').style.display = 'none';
                    document.getElementById('feature-form').reset();
                }

                function applySelectedFeatures(chartArray, chartIndex, isOverall) {
                    const selectedFeatures = Array.from(document.querySelectorAll('#feature-form input[type="checkbox"]:checked'))
                        .map(input => input.value);
                    console.log("applySelectedFeatures:", selectedFeatures);
                    console.log("ChartArray:", chartArray);
                    console.log("ChartIndex:", chartIndex);

                    // `notaccuracy`が選択されているか確認
                    if (selectedFeatures.includes('notaccuracy')) {
                        const otherFeature = selectedFeatures.find(feature => feature !== 'notaccuracy');

                        // クライアント側のデータから不正解率データを取得
                        let group = isOverall ? classData[chartIndex] : groupData[chartIndex];
                        //追加分
                        const groupId = group.group_id;  // グループIDを取得
                        console.log("groupId:", groupId);
                        const labels = group.students.map(student => student.name);
                        const notaccuracyData = group.students.map(student => student.notaccuracy);

                        if (!otherFeature) {
                            alert("不正解率と一緒にもう1つの特徴量を選択してください。");
                            return;
                        }

                        // サーバーにリクエストするパラメータを設定（`notaccuracy`は含めない）
                        const studentIDs = isOverall
                            ? group.class_students.map(student => student.student_id).join(',')
                            : group.students.map(student => student.student_id).join(',');

                        const params = new URLSearchParams({
                            features: otherFeature,
                            studentIDs: studentIDs
                        });
                        // ログ用のデータを準備
                        //追加分
                        /*
                        const logDetails = JSON.stringify(selectedFeatures);
                        const logResults = JSON.stringify({
                            groupId: groupId,
                            chartIndex: chartIndex,
                            isOverall: isOverall
                        });
                        */
                        //ここまで
                        // ログ保存用のfetchリクエスト
                        /*
                        fetch('save_graph_feature_log.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({
                                details: logDetails,
                                results: logResults
                            })
                        })
                        .then(response => response.text())
                        .then(data => {
                            if (data.status === 'success') {
                                console.log('ログ保存成功:', data);
                            } else {
                                console.error('ログ保存エラー:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('ログ保存中にエラーが発生しました:', error);
                        });
                        */

                        // もう1つの特徴量のデータをfetchで取得
                        fetch('fetch_feature_data.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: params.toString()
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                console.error('サーバーエラー:', data.error);
                                alert(data.error);
                                return;
                            }

                            const otherFeatureData = data.map(item => item.featureA_avg);

                            const canvasId = isOverall
                                ? `class-dual-axis-chart-${chartIndex}`
                                : `dual-axis-chart-${chartIndex}`;

                            createDualAxisChart(
                                document.getElementById(canvasId).getContext('2d'),
                                labels,
                                notaccuracyData,
                                otherFeatureData,
                                '不正解率(%)',
                                `${otherFeature} 平均`,
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 99, 132, 0.6)',
                                '不正解率(%)',
                                `${otherFeature} 平均`,
                                chartArray,
                                chartIndex
                            );

                            closeFeatureModal();
                        })
                        .catch(error => {
                            console.error('エラー:', error);
                        });
                    } else {
                        // 通常の2つの特徴量での処理
                        if (selectedFeatures.length !== 2) {
                            alert("2つの特徴量を選択してください。");
                            return;
                        }

                        let group = isOverall ? classData[chartIndex] : groupData[chartIndex];

                        const studentIDs = isOverall
                            ? group.class_students.map(student => student.student_id).join(',')
                            : group.students.map(student => student.student_id).join(',');

                        const params = new URLSearchParams({
                            features: selectedFeatures.join(','),
                            studentIDs: studentIDs
                        });

                        fetch('fetch_feature_data.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: params.toString()
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                console.error('サーバーエラー:', data.error);
                                
                                alert(data.error);
                                return;
                            }

                            const labels = data.map(item => item.name);
                            const featureAData = data.map(item => item.featureA_avg);
                            const featureBData = data.map(item => item.featureB_avg);

                            const canvasId = isOverall
                                ? `class-dual-axis-chart-${chartIndex}`
                                : `dual-axis-chart-${chartIndex}`;

                            createDualAxisChart(
                                document.getElementById(canvasId).getContext('2d'),
                                labels,
                                featureAData,
                                featureBData,
                                `${selectedFeatures[0]} 平均`,
                                `${selectedFeatures[1]} 平均`,
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 99, 132, 0.6)',
                                `${selectedFeatures[0]} 平均`,
                                `${selectedFeatures[1]} 平均`,
                                chartArray,
                                chartIndex
                            );

                            closeFeatureModal();
                        })
                        .catch(error => {
                            console.error('エラー:', error);
                        });
                    }
                }





            </script>

            <div class = "all-overview">
                <h2><?= translate('teachertrue.php_646行目_クラス単位のデータ') ?></h2>
                <div class = "class-data">
                    <!--ここは自身が受け持つクラスの全ての学習者のデータ表示-->
                    <?php
                        require "../dbc.php";
                        $stmt = $conn->prepare("SELECT * FROM ClassTeacher WHERE TID = ?");
                        $stmt->bind_param("i", $teacher_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $classes = [];
                        if ($result->num_rows > 0) {
                            while ($row_class = $result->fetch_assoc()) {
                                $class_id = $row_class['ClassID'];
                                // ClassNameを取得するためにclassesテーブルを参照
                                $stmt_classname = $conn->prepare("SELECT ClassName FROM classes WHERE ClassID = ?");
                                $stmt_classname->bind_param("i", $class_id);
                                $stmt_classname->execute();
                                $result_classname = $stmt_classname->get_result();
                                $class_name_data = $result_classname->fetch_assoc();
                                $class_name = $class_name_data['ClassName'] ?? 'Unknown';
                                $stmt_classname->close();

                                //クラスごとの学生データを取得
                                $stmt_classstu = $conn->prepare("SELECT * FROM students WHERE ClassID = ?");
                                $stmt_classstu->bind_param("i", $class_id);
                                $stmt_classstu->execute();
                                $result_classstu = $stmt_classstu->get_result();
                                $class_students = []; // 各クラスごとの学生データを初期化
                        
                                if ($result_classstu->num_rows > 0) {
                                    while ($row_student = $result_classstu->fetch_assoc()) {
                                        $student_id = $row_student['uid'];
                        
                                        // 学生ごとの正解数と解答数を取得
                                        $stmt_scores = $conn->prepare("SELECT 
                                                                        COUNT(*) AS total_answers,
                                                                        SUM(CASE WHEN TF = 1 THEN 1 ELSE 0 END) AS correct_answers,
                                                                        SUM(Time) AS total_time
                                                                        FROM linedata WHERE uid = ?");
                                        $stmt_scores->bind_param("i", $student_id);
                                        $stmt_scores->execute();
                                        $result_scores = $stmt_scores->get_result();
                                        $score_data = $result_scores->fetch_assoc();
                        
                                        if ($score_data) {
                                            $correct_answers = $score_data['correct_answers'];
                                            $total_answers = $score_data['total_answers'];
                                            $total_time = $score_data['total_time'];
                                            $accuracy_rate = $total_answers > 0 ? number_format(($correct_answers / $total_answers) * 100, 2) : 0;
                                            $notaccuracy_rate = 100 - $accuracy_rate;
                                            $accuracy_time = $total_answers > 0 ? number_format(($total_time / 1000) / $total_answers, 2) : 0;
                        
                                            // 学生ごとの名前を取得
                                            $name = $row_student['Name'];
                        
                                            // 学生ごとの正解率データを追加
                                            $class_students[] = [
                                                'student_id' => $student_id,  // student_id を uid に対応させる
                                                'name' => $name,
                                                'accuracy' => $accuracy_rate,
                                                'notaccuracy' => $notaccuracy_rate,
                                                'time' => $accuracy_time
                                            ];
                                        }
                                        $stmt_scores->close();
                                    }
                                }
                                $stmt_classstu->close();
                        
                                // クラスごとの学生データを追加
                                $classes[] = [
                                    'class_name' => $class_name,
                                    'class_students' => $class_students
                                ];
                            }
                        }
                        $stmt->close();
                        $conn->close();
                    ?>
                    <div class="class-data" id="class-data-container"></div>
                    <script>
                        const classData = <?php echo json_encode($classes); ?>;
                    </script>
                </div>
                <div id = "cluster-data"></div>
            </div>
                    <script>
                        // 現在のChartインスタンスを管理する変数
                        let currentChart = null;

                        const class_container = document.getElementById('class-data-container');

                        // コンテナの生成
                        classData.forEach((classInfo, index) => {
                            const classContainer = document.createElement('div');
                            classContainer.classList.add('class-card');
                            classContainer.innerHTML = `
                                <h3>${classInfo.class_name}
                                    <button onclick="openClassFeatureModal(${index})"><?= translate('teachertrue.php_744行目_グラフ描画特徴量') ?></button>
                                    <button onclick="openClassEstimatePage(${index})"><?= translate('teachertrue.php_745行目_迷い推定') ?></button>
                                    <button onclick="openClusteringModal(${index})"><?= translate('teachertrue.php_746行目_クラスタリング') ?></button>
                                </h3>

                                <div class="chart-row">
                                    <canvas id="class-dual-axis-chart-${index}"></canvas>
                                </div>
                            `;
                            class_container.appendChild(classContainer);

                            const class_labels = classInfo.class_students.map(student => student.name);
                            const class_accuracyData = classInfo.class_students.map(student => student.accuracy);
                            const class_notaccuracyData = classInfo.class_students.map(student => student.notaccuracy);
                            const class_timeData = classInfo.class_students.map(student => student.time);

                            createDualAxisChart(
                                document.getElementById(`class-dual-axis-chart-${index}`).getContext('2d'),
                                class_labels,
                                class_notaccuracyData,
                                class_timeData,
                                '不正解率(%)',
                                '解答時間(秒)',
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 99, 132, 0.6)',
                                '不正解率(%)',
                                '解答時間(秒)',
                                existingOverallCharts,  // 全体の成績グラフ用の配列
                                index
                            );
                        });

                        //クラスタリング関連
                        let selectedClassIndex; // 現在選択中のクラスインデックス

                        // クラスタリングモーダルを開く
                        function openClusteringModal(index) {
                            selectedClassIndex = index;
                            document.getElementById('clustering-modal').style.display = 'block';
                        }

                        // クラスタリングモーダルを閉じる
                        function closeClusteringModal() {
                            document.getElementById('clustering-modal').style.display = 'none';
                            document.getElementById('clustering-feature-form').reset();
                        }
                        // 特徴量を送信してクラスタリングを実行
                        document.getElementById('apply-clustering-btn').onclick = function () {
                            const selectedFeatures = Array.from(document.querySelectorAll('#clustering-feature-form input[type="checkbox"]:checked'))
                                .map(input => input.value);
                            if (selectedFeatures.length === 0) {
                                alert("少なくとも1つの特徴量を選択してください。");
                                return;
                            }
                            // クラスタ数を取得
                            const clusterCount = document.getElementById('clustering-input').value;
                            // ★ ここで手法を取得
                            const method = document.getElementById('clustering-method').value; 

                            const classInfo = classData[selectedClassIndex];
                            const studentIds = classInfo.class_students.map(student => student.student_id).join(',');

                            const params = new URLSearchParams({
                                features: selectedFeatures.join(','),
                                studentIDs: studentIds,
                                clusterCount: clusterCount,  // クラスタ数を追加
                                method : method             // クラスタリング手法を追加
                            });

                            let jsonData

                            fetch('perform_clustering.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: params.toString()
                            })
                                /*
                                .then(response => response.json())
                                .then(data => {
                                    if (data.error) {
                                        alert(data.error);
                                        return;
                                    }
                                    console.log("サーバーからのレスポンス:", data); // 詳細なデバッグログ
                                    closeClusteringModal();
                                    displayClusteringResultsFromJSON(data);
                                })
                                    */
                                    .then(response => response.text()) // JSON の代わりにテキストとして受け取る
                                    .then(data => {
                                        console.log("サーバーからのレスポンス:", data); // レスポンスを確認
                                        try {
                                            jsonData = JSON.parse(data); // JSON に変換
                                            if (jsonData.error) {
                                                alert(jsonData.error);
                                                return;
                                            }
                                            closeClusteringModal();
                                            displayClusteringResultsFromJSON(jsonData);
                                            displayClusteringResults_groupFromJSON(jsonData);
                                        } catch (e) {
                                            console.error('JSON 解析エラー:', e);
                                            console.error('レスポンス内容:', data);
                                        }
                                    })
                                .catch(error => console.error('エラー:', error));
                        };
                        // JSONファイルのデータを可視化

                        function displayClusteringResults_groupFromJSON(jsonData) {
                            const container = document.getElementById('cluster-data');
                            if (!container) {
                                console.error('クラスタコンテナが見つかりません。');
                                return;
                            }
                            //clustersヲ取得
                            const clusters = jsonData.clusters?.clusters;
                            if (!clusters) {
                                console.error('クラスターデータが見つかりません。');
                                return;
                            }


                            // クラスタごとに表示
                            Object.keys(clusters).forEach(clusterKey => {
                                console.log('clusterKey:', clusterKey);
                                const clusterPoints = clusters[clusterKey];

                                // クラスタ情報のコンテナを作成
                                const clusterDiv = document.createElement('div');
                                clusterDiv.className = 'cluster-group';
                                // チェックボックスとクラスタタイトル
                                const clusterHeader = document.createElement('h3');
                                const checkbox = document.createElement('input');
                                checkbox.type = 'checkbox';
                                checkbox.value = clusterKey;
                                checkbox.className = 'cluster-checkbox';

                                clusterHeader.textContent = `クラスタ ${clusterKey}`;
                                clusterHeader.prepend(checkbox);

                                clusterDiv.appendChild(clusterHeader);

                                // 学生リストを表示
                                const studentList = document.createElement('ul');
                                console.log('clusterPointsの型:', typeof clusterPoints);
                                Object.keys(clusterPoints).forEach(groupIndex => {
                                    console.log('groupIndex:', groupIndex);
                                    const listItem = document.createElement('li');
                                    listItem.textContent = `学生:${clusterPoints[groupIndex].name}`;
                                    console.log('listItem:', listItem);
                                    studentList.appendChild(listItem);
                                });
                                clusterDiv.appendChild(studentList);
                                
                                /*
                                // グループ化ボタンを追加
                                const groupButton = document.createElement('button');
                                groupButton.textContent = 'グループ化';
                                groupButton.onclick = () => {
                                    groupStudents(clusterKey, clusterPoints);
                                };
                                clusterDiv.appendChild(groupButton);
                                */

                                // コンテナにクラスタ情報を追加
                                container.appendChild(clusterDiv);
                            });
                            // グループ化ボタンを作成
                            const groupButton = document.createElement('button');
                            groupButton.textContent = 'グループ化';
                            groupButton.onclick = () => {
                                groupSelectedClusters(clusters);
                            };
                            container.appendChild(groupButton);
                            
                        }
                        function groupSelectedClusters(clusters) {
                            const selectedCheckboxes = document.querySelectorAll('.cluster-checkbox:checked');

                            if (selectedCheckboxes.length === 0) {
                                alert('少なくとも1つのクラスタを選択してください。');
                                return;
                            }

                            // 選択されたクラスタごとのデータを収集
                            const clustersData = [];
                            selectedCheckboxes.forEach(checkbox => {
                                const clusterKey = checkbox.value;
                                const clusterName = `クラスタ ${clusterKey}`;
                                const clusterData = clusters[clusterKey];
                                const studentIds = clusterData.map(student => student.id);

                                clustersData.push({
                                    group_name: clusterName,
                                    students: studentIds
                                });
                            });

                            // サーバーにリクエストを送信
                            fetch('group_students.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify(clustersData)  // JSON形式で送信
                            })
                            .then(response => response.text())
                            .then(data => {
                                alert('選択されたクラスタのグループ化が完了しました。');
                                console.log(data);
                                //ページ再読み込み
                                window.location.reload();
                            })
                            .catch(error => {
                                console.error('エラー:', error);
                            });
                        }


                        
                        function displayClusteringResultsFromJSON(jsonData) {
                            const container = document.getElementById('cluster-data');
                            if (!container) {
                                console.error('cluster-data コンテナが見つかりません。');
                                return;
                            }
                            container.innerHTML = ''; // 前の内容をクリア
                            // *** ポップアップメッセージの追加 ***
                            alert("クラスタリング機能によりグルーピングされた学習者群は、ベータ機能のため誤ったグルーピングが行われている可能性があります。クラスタリング結果のグラフを確認し，明らかに誤ったグルーピングがある場合は，特徴量を変更し，再度クラスタリングを実行してください。");

                            // 新しい Canvas を作成
                            const canvas = document.createElement('canvas');
                            canvas.id = 'cluster-visualization';
                            canvas.width = 800;
                            canvas.height = 400;
                            container.appendChild(canvas);

                            

                            const ctx = canvas.getContext('2d');

                            // clusters を取得
                            const clusterData = jsonData.clusters;
                            //console.log('Cluster Data:', clusterData);
                            // データセットを生成
                            const clusterColors = [
                                'rgba(255, 0, 0, 0.7)',  // クラスタ0の色(赤)
                                'rgba(0, 255, 0, 0.7)', // クラスタ1の色（青）
                                'rgba(0, 0, 255, 0.7)', // クラスタ2の色（緑）
                                'rgba(255, 255, 0, 0.7)', // クラスタ3の色（黄）
                                'rgba(255, 0, 255, 0.7)', // クラスタ4の色（紫）
                                // 必要に応じて色を追加
                            ];
                            
                            const datasets = Object.keys(clusterData).flatMap(clusterKey => {
                                const clusterPoints = clusterData[clusterKey]; // クラスタごとのデータポイントを取得
                                //console.log(`Cluster ${clusterKey} Points:`, clusterPoints);

                                // クラスタの色を取得
                                const color = clusterColors[parseInt(clusterKey)] || `rgba(${Math.random() * 255}, ${Math.random() * 255}, ${Math.random() * 255}, 0.7)`;

                                // クラスタ内の各ポイントを処理
                                return clusterPoints.flatMap((pointGroup, groupIndex) =>
                                    pointGroup.map((point, pointIndex) => {
                                        //console.log(`Cluster ${clusterKey}, Group ${groupIndex}, Point ${pointIndex}:`, point);

                                        return {
                                            label: `Cluster ${groupIndex}`, // クラスタ名
                                            data: [{ x: point.pca1, y: point.pca2 }], // 散布図データ形式
                                            backgroundColor: clusterColors[groupIndex] || `rgba(${Math.random() * 255}, ${Math.random() * 255}, ${Math.random() * 255}, 0.7)`, // クラスタの色を設定
                                            borderColor: 'rgba(0, 0, 0, 1)',
                                            borderWidth: 1,
                                            pointRadius: 5
                                        };
                                    })
                                );
                            });
                        

                            //console.log('Datasets:', datasets);


                            // 既存のチャートを破棄
                            if (currentChart) {
                                currentChart.destroy();
                            }

                            // Chart.js を使って散布図を作成
                            currentChart = new Chart(ctx, {
                                type: 'scatter',
                                data: {
                                    datasets: datasets,
                                },
                                options: {
                                    plugins: {
                                        title: {
                                            display: true,
                                            text: 'クラスタリング結果 (PCA可視化)',
                                        },
                                    },
                                    scales: {
                                        x: {
                                            title: {
                                                display: true,
                                                text: '次元1',
                                            },
                                        },
                                        y: {
                                            title: {
                                                display: true,
                                                text: '次元2',
                                            },
                                        },
                                    },
                                },
                            });
                            
                        }
                        
 



                        
                    </script>
                </div>
            </div>
            <div class = "create-new">
                <h2><?= translate('teachertrue.php_1075行目_新規問題・テスト作成') ?></h2>
                <div id = "createassignment-botton" class = "button1">
                    <a href='./create/new.php?mode=0'><?= translate('teachertrue.php_1077行目_新規問題作成') ?></a>
                </div>
                <div id = "createtest-botton" class = "button1">
                    <a href='create-test.php'><?= translate('teachertrue.php_1080行目_新規テスト作成') ?></a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
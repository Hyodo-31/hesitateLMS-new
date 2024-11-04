<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教師用ダッシュボード</title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="notification-script.js"></script>
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
                <li><a href="#">ホーム</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
                <li><a href="register-student.php">新規学生登録</a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="#">ホーム</a></li>
                <li><a href="machineLearning_sample.php">迷い推定・機械学習</a></li>
                <li><a href="Analytics/studentAnalytics.php">学生分析</a></li>
                <li><a href="Analytics/questionAnalytics.php">問題分析</a></li>
                <li><a href="register-student.php">新規学生登録</a></li>
            </ul>
        </aside>
        <main>

            <div class="notifications">
                <h2>お知らせ</h2>
                <div class = "notify">
                    <?php
                        $result = $conn->query("SELECT id, subject, content FROM notifications ORDER BY created_at DESC LIMIT 5");
                        while ($row = $result->fetch_assoc()) {
                            echo "<div class='notification-item' data-id='{$row['id']}'>";
                            echo "<p>{$row['subject']}</p>";
                            echo "</div>";
                        }
                        $result->free();
                        $conn->close();
                    ?>
                </div>
                <script src="notification-script.js"></script>
                <button id="loadMore">さらに読み込む</button>
                <div id = "notifymake-botton" class = "button1">
                    <a href='create-notification.php'>お知らせ作成</a>
                </div>
            </div>

            <div class="class-overview">
                <h2>クラス別データ</h2>
                <div id= "button-groupstudent-making" class="button1">
                    <a href='create-student-group.php'>学習者グルーピング作成</a>
                </div>
                <div class="class-data">
                    <?php
                        require "../dbc.php";
                        $teacher_id = $_SESSION['MemberID'];
                        $stmt = $conn->prepare("SELECT * FROM groups WHERE TID = ?");
                        $stmt->bind_param("i", $teacher_id);
                        $stmt->execute();
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

                                    //学生ごとの名前を取得
                                    $stmt_name = $conn->prepare("SELECT Name FROM students WHERE uid = ?");
                                    $stmt_name->bind_param("i", $students_id);
                                    $stmt_name->execute();
                                    $result_name = $stmt_name->get_result();
                                    $name_data = $result_name->fetch_assoc();
                                    $name = $name_data['Name'];
                                    $stmt_name->close();

                                    //学生ごとの正解数を格納
                                    $group_students[] = [
                                        'name' => $name,
                                        'accuracy' => $accuracy_rate,
                                        'notaccuracy' => $notaccuracy_rate,
                                        'time' => $accuracy_time
                                    ];
                                    $stmt_scores->close();
                                }
                                // グループデータを配列に追加
                                $groups[] = [
                                    'group_name' => $group_name,
                                    'students' => $group_students
                                ];
                                $stmt_groupmember->close();
                            }
                        }else{
                            // 学習者グループがない場合
                            echo "<p>学習者グループがありません</p>";
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
                    <h3>特徴量を選択してください</h3>
                    <form id="feature-form">
                        <label><input type="checkbox" name="feature" value="notaccuracy"> 不正解率 (%)</label><br>
                        <label><input type="checkbox" name="feature" value="Time"> 解答時間 (秒)</label><br>
                        <label><input type="checkbox" name="feature" value="distance"> 距離</label><br>
                        <label><input type="checkbox" name="feature" value="averageSpeed"> 平均速度</label><br>
                        <label><input type="checkbox" name="feature" value="maxSpeed"> 最高速度</label><br>
                        <label><input type="checkbox" name="feature" value="thinkingTime"> 考慮時間</label><br>
                        <label><input type="checkbox" name="feature" value="answeringTime"> 第一ドロップ後解答時間</label><br>
                        <label><input type="checkbox" name="feature" value="totalStopTime"> 合計静止時間</label><br>
                        <label><input type="checkbox" name="feature" value="maxStopTime"> 最大静止時間</label><br>
                        <label><input type="checkbox" name="feature" value="totalDDIntervalTime"> 合計DD間時間</label><br>
                        <label><input type="checkbox" name="feature" value="maxDDIntervalTime"> 最大DD間時間</label><br>
                        <label><input type="checkbox" name="feature" value="maxDDTime"> 合計DD時間</label><br>
                        <label><input type="checkbox" name="feature" value="minDDTime"> 最小DD時間</label><br>
                        <label><input type="checkbox" name="feature" value="DDCount"> 合計DD回数</label><br>
                        <label><input type="checkbox" name="feature" value="groupingDDCount"> グループ化DD回数</label><br>
                        <label><input type="checkbox" name="feature" value="groupingCountbool"> グループ化有無</label><br>
                        <label><input type="checkbox" name="feature" value="xUturnCount"> x軸Uターン回数</label><br>
                        <label><input type="checkbox" name="feature" value="yUturnCount"> y軸Uターン回数</label><br>
                        <label><input type="checkbox" name="feature" value="register_move_count1"> レジスタ➡レジスタへの移動回数</label><br>
                        <label><input type="checkbox" name="feature" value="register_move_count2"> レジスタ➡レジスタ外への移動回数</label><br>
                        <label><input type="checkbox" name="feature" value="register_move_count3"> レジスタ外➡レジスタへの移動回数</label><br>
                        <label><input type="checkbox" name="feature" value="register01count1"> レジスタ➡レジスタへの移動有無</label><br>
                        <label><input type="checkbox" name="feature" value="register01count2"> レジスタ外➡レジスタへの移動有無</label><br>
                        <label><input type="checkbox" name="feature" value="register01count3"> レジスタ外➡レジスタへの移動有無</label><br>
                        <label><input type="checkbox" name="feature" value="registerDDCount"> レジスタ外➡レジスタへの移動有無</label><br>
                        <label><input type="checkbox" name="feature" value="xUturnCountDD"> x軸UターンDD回数</label><br>
                        <label><input type="checkbox" name="feature" value="yUturnCountDD">y軸UターンDD回数</label><br>
                        <label><input type="checkbox" name="feature" value="FromlastdropToanswerTime"> レジスタ外➡レジスタへの移動有無DD</label><br>
                        <button type="button" onclick="applySelectedFeatures()">適用</button>
                    </form>
                </div>
            </div>

            <script>

                // 正解率グラフ生成関数
                
                function createChart(ctx, labels, data, label, color, ytext) {
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: label,
                                data: data,
                                backgroundColor: color,
                                borderColor: color,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                x: {
                                    title: {
                                        display: true,
                                        text: 'ユーザー名',
                                        font: {
                                            size: 30 // x軸ラベルの文字サイズ
                                        }
                                    },
                                    ticks: {
                                        font: {
                                            size: 26 // x軸目盛の文字サイズ
                                        }
                                    }
                                },
                                y: {
                                    title: {
                                        display: true,
                                        text: ytext,
                                        font: {
                                            size: 30 // y軸ラベルの文字サイズ
                                        }
                                    },
                                    ticks: {
                                        font: {
                                            size: 26 // y軸目盛の文字サイズ
                                        }
                                    },
                                    beginAtZero: true
                                }
                            },
                            plugins: {
                                legend: {
                                    labels: {
                                        font: {
                                            size: 30 // 凡例の文字サイズ
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                function createDualAxisChart(ctx, labels, data1, data2, label1, label2, color1, color2, yText1, yText2) {
                    new Chart(ctx, {
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
                        // グループのコンテナ要素を生成
                        const groupContainer = document.createElement('div');
                        groupContainer.classList.add('class-card');
                        groupContainer.innerHTML = `
                            <h3>${group.group_name}
                                <button onclic = "openFeatureModal(${index})">グラフ描画特徴量</button></h3>
                            <div class="chart-row">
                                <canvas id="dual-axis-chart-${index}"></canvas>
                            </div>
                        `;
                        container.appendChild(groupContainer);

                        // 正解率のデータを取得
                        const labels = group.students.map(student => student.name);
                        const accuracyData = group.students.map(student => student.accuracy);
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
                            '解答時間(秒)'
                        );
                    });
                });

                let selectedGroupIndex;
                //モーダルを開く
                function openFeatureModal(index) {
                    selectedGroupIndex = index;
                    document.getElementById('feature-modal').style.display = 'block';
                }
                //モーダルを閉じる
                function closeFeatureModal() {
                    document.getElementById('feature-modal').style.display = 'none';
                    document.getElementById('feature-form').reset();
                }

                //選択した特徴量でグラフを再描画
                function applySelectedFeatures() {
                    const selectedFeatures = Array.from(document.querySelectorAll('#feature-form input[type="checkbox"]:checked'))
                        .map(input => input.value);

                    if (selectedFeatures.length === 2) {
                        const group = groupData[selectedGroupIndex];
                        renderChart(selectedGroupIndex, group, selectedFeatures[0], selectedFeatures[1]);
                        closeFeatureModal();
                    } else {
                        alert("2つの特徴量を選択してください。");
                    }
                }
            </script>

            <div class = "all-overview">
                <h2>全体の成績</h2>
                <div class = "class-data">
                    <!--ここは自身が受け持つクラスの全ての学習者のデータ表示-->
                    <?php
                        require "../dbc.php";
                        $stmt = $conn->prepare("SELECT * FROM classes WHERE TID = ?");
                        $stmt->bind_param("i", $teacher_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $classes = [];
                        if ($result->num_rows > 0) {
                            while ($row_class = $result->fetch_assoc()) {
                                $class_id = $row_class['ClassID'];
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
                                            $stmt_name = $conn->prepare("SELECT Name FROM students WHERE uid = ?");
                                            $stmt_name->bind_param("i", $student_id);
                                            $stmt_name->execute();
                                            $result_name = $stmt_name->get_result();
                                            $name_data = $result_name->fetch_assoc();
                                            $name = $name_data['Name'];
                        
                                            // 学生ごとの正解率データを追加
                                            $class_students[] = [
                                                'name' => $name,
                                                'accuracy' => $accuracy_rate,
                                                'notaccuracy' => $notaccuracy_rate,
                                                'time' => $accuracy_time
                                            ];
                        
                                            $stmt_name->close();
                                        }
                                        $stmt_scores->close();
                                    }
                                }
                                $stmt_classstu->close();
                        
                                // クラスごとの学生データを追加
                                $classes[] = [
                                    'class_name' => $row_class['ClassName'],
                                    'class_students' => $class_students
                                ];
                            }
                        }
                        
                    ?>
                    <div class="class-data" id="class-data-container"></div>
                    <script>
                        const classData = <?php echo json_encode($classes); ?>;
                    </script>
                </div>
            </div>
                    <script>
                        const class_container = document.getElementById('class-data-container');

                        // コンテナの生成
                        classData.forEach((classInfo, index) => {
                            // クラスのコンテナ要素を生成
                            const classContainer = document.createElement('div');
                            classContainer.classList.add('class-card');
                            classContainer.innerHTML = `
                                <h3>${classInfo.class_name}</h3>
                                <div class="chart-row">
                                    <canvas id="class-dual-axis-chart-${index}"></canvas>
                                </div>
                            `;
                            class_container.appendChild(classContainer);

                            // クラスのデータを取得
                            const class_labels = classInfo.class_students.map(student => student.name);
                            const class_accuracyData = classInfo.class_students.map(student => student.accuracy);
                            const class_notaccuracyData = classInfo.class_students.map(student => student.notaccuracy);
                            const class_timeData = classInfo.class_students.map(student => student.time);

                            // クラスのグラフを生成
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
                                '解答時間(秒)'
                            )
                        });
                    </script>
                </div>
            </div>
            <div class = "create-new">
                <h2>新規課題・テスト作成</h2>
                <div id = "createassignment-botton" class = "button1">
                    <a href='create-assignment.php'>新規課題作成</a>
                </div>
                <div id = "createtest-botton" class = "button1">
                    <a href='create-test.php'>新規テスト作成</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

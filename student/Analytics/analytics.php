<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学習者用ダッシュボード</title>
    <link rel="stylesheet" href="../../style/student_style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        session_start();
        require "../../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo">英単語並べ替え問題LMS</div>
        <nav>
            <ul>
                <li><a href="../student.php">ホーム</a></li>
                <li><a href="../../logout.php">ログアウト</a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="../student.php">ホーム</a></li>
            </ul>
        </aside>
        <main>
            <!-- ここにコンテンツを入れる 
             ここは，学習者用ダッシュボード
             解答した問題一覧と，成績管理-->
             <section class="stats">
                <h2>成績概要</h2>
                <div class="stats-info">
                    <p>正解率: <span id="accuracyRate">
                    <?php 
                        $student_id = $_SESSION['MemberID'];
                        $class_id = $_SESSION['ClassID'];
                        $sql = "SELECT 
                                SUM(TF) as correct_count,
                                count(*) as total_count,
                                (SUM(TF)/count(*))*100 as accuracy_rate
                            FROM linedata
                            WHERE UID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $student_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $accuracy_rate = $row['accuracy_rate'];
                        $total_count = $row['total_count'];
                        //小数点二桁まで表示
                        echo number_format($accuracy_rate, 2);
                        $stmt->close();
                    ?>

                    <?php
                        //データを固定して迷い推定を行う
                        // ユニークなIDを生成
                        $uniqueId = uniqid(bin2hex(random_bytes(4)));
                        $timestamp = date('YmdHis');
                        //教師データとテストデータを取得
                        $allresult = array();
                        $allresult_test = array();

                        $sql = "SELECT UID,WID,Understand,attempt,time,distance,averageSpeed,maxSpeed,
                                    maxStopTime,xUTurnCount,yUTurnCount,thinkingTime,answeringTime,maxDDTime,
                                    DDCount,maxDDIntervalTime,totalDDIntervalTime FROM featurevalue";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $allresult[] = $row;
                        }

                        $sql_test = "SELECT UID,WID,Understand,attempt,time,distance,averageSpeed,maxSpeed,
                                    maxStopTime,xUTurnCount,yUTurnCount,thinkingTime,answeringTime,maxDDTime,
                                    DDCount,maxDDIntervalTime,totalDDIntervalTime FROM test_featurevalue WHERE UID = ?";
                        $stmt1 = $conn->prepare($sql_test);
                        $stmt1->bind_param("i", $student_id);
                        $stmt1->execute();
                        $result_test = $stmt1->get_result();
                        while ($row = $result_test->fetch_assoc()) {
                            $allresult_test[] = $row;
                        }


                        

                        //教師データ取得
                        $test_filename = "./pydata/test_{$uniqueId}_{$timestamp}.csv";          #教師データ
                        $fp = fopen($test_filename, 'w');
                        $testdata_filename = "./pydata/testdata_{$uniqueId}_{$timestamp}.csv";  #テストデータ
                        $fp_test = fopen($testdata_filename, 'w');

                        $column_name = "UID,WID,Understand,attempt,time,distance,averageSpeed,maxSpeed,maxStopTime,xUTurnCount,yUTurnCount,thinkingTime,answeringTime,maxDDTime,DDCount,maxDDIntervalTime,totalDDIntervalTime";
                        fputcsv($fp, explode(',', $column_name));
                        foreach($allresult as $row){
                            fputcsv($fp, $row);
                        }
                        fclose($fp);

                        fputcsv($fp_test, explode(',', $column_name));
                        foreach($allresult_test as $row){
                            fputcsv($fp_test, $row);
                        }
                        fclose($fp_test);
                        //echo "csvファイルを生成しました" . "ファイル名:" . $test_filename;
                        $pyscript = "./machineLearning/sampleSHAP.py";
                        $countF = 0;
                        $csvFile = "./machineLearning/results_actual_{$uniqueId}_{$timestamp}.csv";
                        $metricsFile = "./machineLearning/evaluation_metrics_{$uniqueId}_{$timestamp}.json";

                        exec("python3 {$pyscript} {$test_filename} {$testdata_filename} {$csvFile} {$metricsFile} 2>&1", $output, $status);
                        //ここまでで迷いの結果が格納されている．
                    ?>
                    </span>
                    %</p>
                    <p>迷い率: <span id="hesitationRate">
                        <?php
                            //csvを読み取って迷い率を計算する
                            if($status != 0){
                                echo "実行エラー: ステータスコード " . $status;
                                echo "エラーメッセージ:\n" . implode("\n", $output);
                            } else {
                                // Pythonの実行が成功したら、結果のCSVをテーブルtemporary_results_forstuに格納
                                /*
                                $selectedFeatures = $_POST["featureLabel"];
                                $details = [
                                    'selectedFeatures' => $selectedFeatures
                                ];
                                */
                                $resultPaths = [
                                    'csv_file' => $csvFile,
                                    'metrics_file' => $metricsFile,
                                ];
                                //logActivity($conn, $_SESSION['MemberID'], 'machine_learning_completed', $details, $resultPaths);
                                if (file_exists($metricsFile)) {
                                    $metrics = json_decode(file_get_contents($metricsFile), true);
                                } else {
                                    //echo "評価指標のデータが見つかりません。";
                                }
                                if (($handle = fopen($csvFile, "r")) !== FALSE) {
                                    // CSVファイル全体を読み込む
                                    // 最初の行はヘッダーとして取得
                                    $header = fgetcsv($handle, 1000, ",");
                                    // 既存データを削除
                                    $deleteQuery = "DELETE FROM temporary_results_forstu WHERE make_id = ?";
                                    $stmtDelete = $conn->prepare($deleteQuery);
                                    $stmtDelete->bind_param("i", $_SESSION['MemberID']);
                                    $stmtDelete->execute();
                                    $stmtDelete->close();
                                    //挿入用クエリを準備
                                    $insertquery = "INSERT INTO temporary_results_forstu (UID,WID,Understand,make_id,attempt)
                                                    VALUES (?,?,?,?,?)";
                                    $csvData = [];
                                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                                        $csvData[] = $data; // 全てのデータを配列に保存
                                        //データベースに書き込む
                                        $stmt = $conn->prepare($insertquery);
                                        $stmt->bind_param("iiisi", $data[0], $data[1], $data[2], $_SESSION['MemberID'], $data[3]);
                                        $stmt->execute();

                                    }
                                    fclose($handle);
                                    $stmt->close();
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
                                    //$uidDataで迷い率を計算
                                    $hesitateRate = [];
                                    foreach ($uidData as $uid => $data) {
                                        $rate = $data['hesitate'] / $data['total'] * 100;
                                        $hesitateRate[$uid] = $rate;
                                    }
                                }
                            }
                            //学習者の迷い率を表示
                            echo number_format($hesitateRate[$student_id], 2);
                                ?>
                                </span>%</p>
                    <p>解答した問題数: <span id="questionsAnswered">
                    <?php echo $total_count; ?>
                    </span></p>
                </div>
            </section>

            <section class="question-list">
                <h2>解答済み問題一覧</h2>
                <!-- フォームをやめて、単に <select> だけにする or フォームの onsubmit を無効化 -->
                <label for="questionSelect">解答済みの問題を選択してください:</label>
                <!-- 横並びにしたいので、divなどでまとめ、CSSで調整 -->
                <div style="display: inline-flex; gap: 10px;">
                <select id="questionSelect" required>
                    <option value="">選択してください</option>
                    <?php
                        // 学習者UIDは $student_id などで取得済み
                        $sql = "SELECT DISTINCT q.WID, q.Sentence
                                FROM question_info q
                                WHERE q.WID IN (
                                    SELECT WID FROM linedata WHERE UID = ?
                                )";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $student_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value='" . htmlspecialchars($row['WID']) . "'>" 
                                . htmlspecialchars($row['Sentence']) . "</option>";
                        }
                        $stmt->close();
                    ?>
                </select>
                <!-- Attemptセレクトをあらかじめここに用意しておく -->
                <select id="attempt-select" style="display: none;">
                    <!-- 初期状態は空または非表示 -->
                </select>
                </div>
            </section>

            <!-- 以下は表示用の領域 -->
            <!-- 問題情報: カード風 -->
            <div id="wid-details-maininfo-all"></div>
            <div id="wid-details-maininfo-stu"></div>

            <script>
            const student_id = <?php echo json_encode($student_id); ?>;
            const questionSelect = document.getElementById('questionSelect');
            const attemptSelect  = document.getElementById('attempt-select');
            const widDetailsmaininfoall = document.getElementById('wid-details-maininfo-all');
            const widDetailsmaininfostu = document.getElementById('wid-details-maininfo-stu');

            questionSelect.addEventListener('change', async function() {
                const selectedWid = this.value;
                if(!selectedWid){
                    widDetailsmaininfoall.innerHTML = '';
                    widDetailsmaininfostu.innerHTML = '<p>問題を選択してください。</p>';
                    attemptSelect.style.display = 'none';
                    return;
                }
                
                try {
                    // データ取得
                    const answerResponse = await fetch(`get_answer_info.php?uid=${student_id}&wid=${selectedWid}`);
                    if (!answerResponse.ok) {
                        throw new Error(`HTTP error! status: ${answerResponse.status}`);
                    }
                    /*これがえってくる
                    $widinfoall[] = [
                            'accuracy_rate' => $accuracy_rate,
                            'widinfo' => $widinfo
                        ];
                        */
                    const answerDetailsresponse = await answerResponse.json();
                    answerDetails = answerDetailsresponse.widinfo;
                    answeraccuracy = answerDetailsresponse.accuracy_rate;
                    ave_time = answerDetailsresponse.ave_time;
                    console.log("answeraccuracy", answeraccuracy);

                    console.log("answerDetails", answerDetails);

                    // attempt=1 のデータを探す
                    const attempt1 = answerDetails.find(detail => detail.attempt == 1);

                    // attemptSelect の中身を作り直す
                    attemptSelect.innerHTML = '<option value="">試行回数を選択</option>';
                    answerDetails.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.attempt;
                        option.textContent = `Attempt ${detail.attempt}`;
                        attemptSelect.appendChild(option);
                    });
                    // 表示する
                    attemptSelect.style.display = 'inline-block';

                    // 問題情報（日本語文・文法など）
                    if (attempt1) {
                        widDetailsmaininfoall.innerHTML = `
                        <div class="card">
                            <div class="card-title">問題情報</div>
                            <div class="card-body">
                            <div><strong>正解率:</strong> ${answeraccuracy}%</div>
                            <div><strong>正解文:</strong> ${attempt1.Sentence}</div>
                            <div><strong>日本語文:</strong> ${attempt1.Japanese}</div>
                            <div><strong>文法項目:</strong> ${attempt1.grammar}</div>
                            <div><strong>平均解答時間:</strong> ${ave_time}秒</div>
                            <div><strong>難易度:</strong> ${attempt1.level}</div>
                            <div><strong>単語数:</strong> ${attempt1.wordnum}</div>
                            <div><strong>正解者が行っているグルーピング:</strong> narrowly escaped</div>
                            </div>
                        </div>
                        `;
                    } else {
                        widDetailsmaininfoall.innerHTML = `<div class="card"><p>データが見つかりません。</p></div>`;
                    }

                    // 学習者の回答情報カード
                    if (attempt1) {
                        widDetailsmaininfostu.innerHTML = `
                        <div class="card">
                            <div class="card-title">解答情報</div>
                            <div class="card-body">
                                <div><strong>回答日時:</strong> ${attempt1.Date}</div>
                                <div><strong>最終回答文:</strong> ${attempt1.EndSentence}</div>
                                <div><strong>解答時間:</strong> ${attempt1.Time}秒</div>
                                <div>
                                    <strong>正誤:</strong> 
                                    <span style="
                                        background-color: ${attempt1.TF === '不正解' ? 'red' : 'transparent'}; 
                                        color: ${attempt1.TF === '不正解' ? 'white' : 'black'}; 
                                        padding: 2px 4px; 
                                        border-radius: 3px; 
                                        font-weight: ${attempt1.TF === '不正解' ? 'bold' : 'normal'};">
                                        ${attempt1.TF}
                                    </span>
                                </div>
                                <div>
                                    <strong>迷い:</strong> 
                                    <span style="
                                        background-color: ${attempt1.Understand === '迷い有り' ? 'red' : 'transparent'}; 
                                        color: ${attempt1.Understand === '迷い有り' ? 'white' : 'black'}; 
                                        padding: 2px 4px; 
                                        border-radius: 3px; 
                                        font-weight: ${attempt1.Understand === '迷い有り' ? 'bold' : 'normal'};">
                                        ${attempt1.Understand}
                                    </span>
                                </div>
                            </div>
                        </div>
                        `;
                        attemptSelect.value = 1;
                    }else {
                        widDetailsmaininfostu.innerHTML = `<div class="card"><p>Attempt=1が見つかりません。</p></div>`;
                    }

                    // attemptSelectのchange
                    attemptSelect.addEventListener('change', function() {
                        const selectedAttempt = this.value;
                        const selectedDetail = answerDetails.find(d => d.attempt == selectedAttempt);
                        if (selectedDetail) {
                            widDetailsmaininfostu.innerHTML = `
                            <div class="card">
                                <div class="card-title">解答情報</div>
                                <div class="card-body">
                                <div><strong>回答日時:</strong> ${selectedDetail.Date}</div>
                                <div><strong>最終回答文:</strong> ${selectedDetail.EndSentence}</div>
                                <div><strong>解答時間:</strong> ${selectedDetail.Time}秒</div>
                                <div><strong>正誤:</strong> ${selectedDetail.TF}</div>
                                </div>
                            </div>
                            `;
                        } else {
                            widDetailsmaininfostu.innerHTML = `<div class="card"><p>選択された試行回数の情報が見つかりません。</p></div>`;
                        }
                    });
                } catch (error) {
                    console.error(error);
                    widDetailsmaininfostu.innerHTML = '<p>データの取得に失敗しました。</p>';
                    attemptSelect.style.display = 'none';
                }
            });
            </script>

        </main>
    </div>
</body>
</html>

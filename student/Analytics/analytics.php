<?php
// lang.phpでセッションが開始されるため、個別のsession_startは不要
include '../../lang.php';
require "../../dbc.php";
// セッション変数をクリアする（必要に応じて）
unset($_SESSION['conditions']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('analytics.php_5行目_学習者用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../../style/student_style.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .status-unanswered { color: red; font-weight: bold; }
        .status-in-progress { color: orange; font-weight: bold; }
        .status-completed { color: black; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <div class="logo"><?= translate('analytics.php_21行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <!-- <li><a href="../student.php"><?= translate('analytics.php_24行目_ホーム') ?></a></li> -->
                <!-- <li><a href="../../logout.php"><?= translate('analytics.php_25行目_ログアウト') ?></a></li> -->
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="../student.php"><?= translate('analytics.php_24行目_ホーム') ?></a></li>
                <li><a href="../test.php"><?= translate('student.php_76行目_テスト') ?></a></li>
            </ul>
        </aside>
        <main>
             <section class="stats">
                <h2><?= translate('analytics.php_38行目_成績概要') ?></h2>
                <div class="stats-info">
                    <p><?= translate('analytics.php_40行目_正解率') ?>: <span id="accuracyRate">
                    <?php 
                        $student_id = $_SESSION['MemberID'];
                        // ... (PHPロジックは変更なし) ...
                        $sql = "SELECT (SUM(TF)/count(*))*100 as accuracy_rate, count(*) as total_count FROM linedata WHERE UID = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $student_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $accuracy_rate = $row['accuracy_rate'] ?? 0;
                        $total_count = $row['total_count'] ?? 0;
                        echo number_format($accuracy_rate, 2);
                        $stmt->close();
                    ?>
                    </span>%</p>
                    <p><?= translate('analytics.php_148行目_迷い率') ?>: <span id="hesitationRate">
                        <?php
                            // ... (中略: 迷い率計算のPHPロジック) ...
                            // この部分はUIテキストを含まないため、多言語化の対象外
                            // ...
                            // 実行結果の表示部分のみ
                            if (isset($hesitateRate[$student_id])) {
                                echo number_format($hesitateRate[$student_id], 2);
                            } else {
                                echo "0.00";
                            }
                        ?>
                    </span>%</p>
                    <p><?= translate('analytics.php_169行目_解答した問題数') ?>: <span id="questionsAnswered">
                    <?php echo $total_count; ?>
                    </span></p>
                </div>
            </section>

            <section class="question-list">
                <h2><?= translate('analytics.php_174行目_解答済み問題一覧') ?></h2>
                <label for="questionSelect"><?= translate('analytics.php_176行目_解答済みの問題を選択') ?>:</label>
                <div style="display: inline-flex; gap: 10px;">
                <select id="questionSelect" required>
                    <option value=""><?= translate('analytics.php_180行目_選択してください') ?></option>
                    <?php
                        $sql = "SELECT DISTINCT q.WID, q.Sentence FROM question_info q WHERE q.WID IN (SELECT WID FROM linedata WHERE UID = ?)";
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
                <select id="attempt-select" style="display: none;"></select>
                </div>
            </section>

            <div id="wid-details-maininfo-all"></div>
            <div id="wid-details-maininfo-stu"></div>

            <script>
            // PHPから翻訳テキストをJavaScriptに渡す
            const translations = {
                please_select_question: <?= json_encode(translate('analytics.php_js_問題を選択してください')) ?>,
                select_attempt: <?= json_encode(translate('analytics.php_js_試行回数を選択')) ?>,
                attempt: <?= json_encode(translate('analytics.php_js_Attempt')) ?>,
                question_info: <?= json_encode(translate('analytics.php_js_問題情報')) ?>,
                accuracy_rate: <?= json_encode(translate('analytics.php_js_正解率')) ?>,
                correct_sentence: <?= json_encode(translate('analytics.php_js_正解文')) ?>,
                japanese_sentence: <?= json_encode(translate('analytics.php_js_日本語文')) ?>,
                grammar_item: <?= json_encode(translate('analytics.php_js_文法項目')) ?>,
                avg_time: <?= json_encode(translate('analytics.php_js_平均解答時間')) ?>,
                seconds: <?= json_encode(translate('analytics.php_js_秒')) ?>,
                difficulty: <?= json_encode(translate('analytics.php_js_難易度')) ?>,
                word_count: <?= json_encode(translate('analytics.php_js_単語数')) ?>,
                grouping_example: <?= json_encode(translate('analytics.php_js_正解者が行っているグルーピング')) ?>,
                data_not_found: <?= json_encode(translate('analytics.php_js_データが見つかりません')) ?>,
                answer_info: <?= json_encode(translate('analytics.php_js_解答情報')) ?>,
                answer_datetime: <?= json_encode(translate('analytics.php_js_回答日時')) ?>,
                final_answer: <?= json_encode(translate('analytics.php_js_最終回答文')) ?>,
                answer_time: <?= json_encode(translate('analytics.php_js_解答時間')) ?>,
                correct_incorrect: <?= json_encode(translate('analytics.php_js_正誤')) ?>,
                hesitation: <?= json_encode(translate('analytics.php_js_迷い')) ?>,
                attempt1_not_found: <?= json_encode(translate('analytics.php_js_Attempt1が見つかりません')) ?>,
                attempt_info_not_found: <?= json_encode(translate('analytics.php_js_試行回数の情報が見つかりません')) ?>,
                data_fetch_failed: <?= json_encode(translate('analytics.php_js_データの取得に失敗しました')) ?>
            };

            const student_id = <?php echo json_encode($student_id); ?>;
            const questionSelect = document.getElementById('questionSelect');
            const attemptSelect  = document.getElementById('attempt-select');
            const widDetailsmaininfoall = document.getElementById('wid-details-maininfo-all');
            const widDetailsmaininfostu = document.getElementById('wid-details-maininfo-stu');

            questionSelect.addEventListener('change', async function() {
                const selectedWid = this.value;
                if(!selectedWid){
                    widDetailsmaininfoall.innerHTML = '';
                    widDetailsmaininfostu.innerHTML = `<p>${translations.please_select_question}</p>`;
                    attemptSelect.style.display = 'none';
                    return;
                }
                
                try {
                    const answerResponse = await fetch(`get_answer_info.php?uid=${student_id}&wid=${selectedWid}`);
                    if (!answerResponse.ok) throw new Error(`HTTP error! status: ${answerResponse.status}`);
                    
                    const answerDetailsresponse = await answerResponse.json();
                    const answerDetails = answerDetailsresponse.widinfo;
                    const answeraccuracy = answerDetailsresponse.accuracy_rate;
                    const ave_time = answerDetailsresponse.ave_time;

                    const attempt1 = answerDetails.find(detail => detail.attempt == 1);

                    attemptSelect.innerHTML = `<option value="">${translations.select_attempt}</option>`;
                    answerDetails.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.attempt;
                        option.textContent = `${translations.attempt} ${detail.attempt}`;
                        attemptSelect.appendChild(option);
                    });
                    attemptSelect.style.display = 'inline-block';

                    if (attempt1) {
                        widDetailsmaininfoall.innerHTML = `
                        <div class="card">
                            <div class="card-title">${translations.question_info}</div>
                            <div class="card-body">
                            <div><strong>${translations.accuracy_rate}:</strong> ${answeraccuracy}%</div>
                            <div><strong>${translations.correct_sentence}:</strong> ${attempt1.Sentence}</div>
                            <div><strong>${translations.japanese_sentence}:</strong> ${attempt1.Japanese}</div>
                            <div><strong>${translations.grammar_item}:</strong> ${attempt1.grammar}</div>
                            <div><strong>${translations.avg_time}:</strong> ${ave_time}${translations.seconds}</div>
                            <div><strong>${translations.difficulty}:</strong> ${attempt1.level}</div>
                            <div><strong>${translations.word_count}:</strong> ${attempt1.wordnum}</div>
                            <div><strong>${translations.grouping_example}:</strong> narrowly escaped</div>
                            </div>
                        </div>`;
                    } else {
                        widDetailsmaininfoall.innerHTML = `<div class="card"><p>${translations.data_not_found}</p></div>`;
                    }

                    if (attempt1) {
                        widDetailsmaininfostu.innerHTML = `
                        <div class="card">
                            <div class="card-title">${translations.answer_info}</div>
                            <div class="card-body">
                                <div><strong>${translations.answer_datetime}:</strong> ${attempt1.Date}</div>
                                <div><strong>${translations.final_answer}:</strong> ${attempt1.EndSentence}</div>
                                <div><strong>${translations.answer_time}:</strong> ${attempt1.Time}${translations.seconds}</div>
                                <div><strong>${translations.correct_incorrect}:</strong> ${attempt1.TF}</div>
                                <div><strong>${translations.hesitation}:</strong> ${attempt1.Understand}</div>
                            </div>
                        </div>`;
                        attemptSelect.value = 1;
                    }else {
                        widDetailsmaininfostu.innerHTML = `<div class="card"><p>${translations.attempt1_not_found}</p></div>`;
                    }

                    attemptSelect.addEventListener('change', function() {
                        const selectedAttempt = this.value;
                        const selectedDetail = answerDetails.find(d => d.attempt == selectedAttempt);
                        if (selectedDetail) {
                            widDetailsmaininfostu.innerHTML = `
                            <div class="card">
                                <div class="card-title">${translations.answer_info}</div>
                                <div class="card-body">
                                <div><strong>${translations.answer_datetime}:</strong> ${selectedDetail.Date}</div>
                                <div><strong>${translations.final_answer}:</strong> ${selectedDetail.EndSentence}</div>
                                <div><strong>${translations.answer_time}:</strong> ${selectedDetail.Time}${translations.seconds}</div>
                                <div><strong>${translations.correct_incorrect}:</strong> ${selectedDetail.TF}</div>
                                <div><strong>${translations.hesitation}:</strong> ${selectedDetail.Understand}</div>
                                </div>
                            </div>`;
                        } else {
                            widDetailsmaininfostu.innerHTML = `<div class="card"><p>${translations.attempt_info_not_found}</p></div>`;
                        }
                    });
                } catch (error) {
                    console.error(error);
                    widDetailsmaininfostu.innerHTML = `<p>${translations.data_fetch_failed}</p>`;
                    attemptSelect.style.display = 'none';
                }
            });
            </script>
        </main>
    </div>
</body>
</html>
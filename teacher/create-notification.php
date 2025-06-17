<?php include '../lang.php'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('create-notification.php_5行目_教師用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php
        //session_start();
        require "../dbc.php";
        // セッション変数をクリアする（必要に応じて）
        unset($_SESSION['conditions']);
    ?>
    <header>
        <div class="logo"><?= translate('create-notification.php_19行目_英単語並べ替え問題LMS') ?></div>
        <nav>
            <ul>
                <li><a href="teachertrue.php"><?= translate('create-notification.php_22行目_ホーム') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('create-notification.php_23行目_迷い推定・機械学習') ?></a></li>
            </ul>
        </nav>
    </header>
    <div class="container">
        <aside>
            <ul>
                <li><a href="teachertrue.php"><?= translate('create-notification.php_30行目_ホーム') ?></a></li>
                <li><a href="machineLearning_sample.php"><?= translate('create-notification.php_31行目_迷い推定・機械学習') ?></a></li>
            </ul>
        </aside>
        <main>
            <div class="notifications">
                <div  id = "notify-form">
                    <!--ここに件名とお知らせ内容を登録できるフォームを作成-->
                    <h2><?= translate('create-notification.php_38行目_お知らせ登録フォーム') ?></h2>
                    <form action="submit-notification.php" method="POST">
                        <!-- 宛先選択 -->
                        <div>
                            <label for="recipient-type"><?= translate('create-notification.php_42行目_宛先:') ?></label>
                            <select id="recipient-type" name="recipient-type" onchange="updateStudentList()" required>
                                <option value="all"><?= translate('create-notification.php_44行目_全員') ?></option>
                                <option value = "class"><?= translate('create-notification.php_45行目_特定のクラス') ?></option>
                                <option value = "group"><?= translate('create-notification.php_46行目_特定のグループ') ?></option>
                                <option value="specific"><?= translate('create-notification.php_47行目_特定の学生') ?></option>
                            </select>
                        </div>

                        <!-- 学生リスト -->
                        <div id="student-selection" style="display: none;">
                            <label><?= translate('create-notification.php_53行目_対象を選択:') ?></label><br>
                            <div id = "student-list"></div>
                        </div>

                        <div>

                        <div>
                            <label for="subject"><?= translate('create-notification.php_60行目_件名:') ?></label>
                            <input type="text" id="subject" name="subject" required placeholder="<?= translate('create-notification.php_61行目_件名を入力してください') ?>">
                        </div>
                        <div>
                            <label for="content"><?= translate('create-notification.php_64行目_お知らせ内容:') ?></label>
                            <textarea id="content" name="content" rows="4" required placeholder="<?= translate('create-notification.php_65行目_お知らせ内容を入力してください') ?>"></textarea>
                        </div>
                        <div>
                            <button type="submit"><?= translate('create-notification.php_68行目_お知らせを登録') ?></button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                function updateStudentList() {
                    const recipientType = document.getElementById('recipient-type').value;
                    const studentSelection = document.getElementById('student-selection');
                    const studentList = document.getElementById('student-list');

                    // 全員の場合はリスト非表示
                    if (recipientType === 'all') {
                        studentSelection.style.display = 'none';
                        studentList.innerHTML = '';
                        return;
                    }

                    // その他の場合はリストを表示
                    studentSelection.style.display = 'block';

                    // Ajaxでリストを動的に取得
                    fetch(`get_studentlist.php?type=${recipientType}`)
                        .then(response => response.text())
                        .then(data => {
                            studentList.innerHTML = data;
                        })
                        .catch(error => {
                            console.error('エラー:', error);
                            studentList.innerHTML = '<p>'+<?= json_encode(translate('create-notification.php_97行目_学生リストを取得できませんでした。')) ?>+'</p>';
                        });
                }
            </script>

        </main>
    </div>
</body>
</html>

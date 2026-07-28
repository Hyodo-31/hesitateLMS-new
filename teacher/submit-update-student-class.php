<?php
include '../lang.php';
require '../dbc.php';

$isPostRequest = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$teacherId = (string)($_SESSION['MemberID'] ?? '');
$studentUidValue = $_POST['student_uid'] ?? '';
$newClassIdValue = $_POST['class_id'] ?? '';
$studentUid = is_scalar($studentUidValue) ? trim((string)$studentUidValue) : '';
$newClassId = is_scalar($newClassIdValue) ? trim((string)$newClassIdValue) : '';

$resultStatus = 'error';
$resultTitle = '所属クラスを変更できませんでした';
$resultMessage = '';
$studentName = '';
$oldClassId = '';
$oldClassName = '';
$newClassName = '';
$updateCompleted = false;
$studentFound = false;
$newClassAllowed = false;

if ($teacherId === '') {
    $resultMessage = 'ログイン情報を確認できません。もう一度ログインしてください。';
} elseif (!$isPostRequest) {
    $resultMessage = 'このページは、学習者グループ作成画面から操作してください。';
} elseif ($studentUid === '' || $newClassId === '') {
    $resultMessage = '学習者と変更先グループ(クラス)を選択してください。';
} else {
    $stmtAllowedStudent = $conn->prepare(
        "SELECT s.uid, s.Name, s.ClassID, COALESCE(c.ClassName, '未設定') AS ClassName
         FROM students s
         JOIN classteacher ct ON s.ClassID = ct.ClassID
         LEFT JOIN classes c ON s.ClassID = c.ClassID
         WHERE s.uid = ? AND ct.TID = ?
         LIMIT 1"
    );

    if (!$stmtAllowedStudent) {
        $resultMessage = '学習者確認の準備に失敗しました。';
    } else {
        $stmtAllowedStudent->bind_param('ss', $studentUid, $teacherId);
        if (!$stmtAllowedStudent->execute()) {
            $resultMessage = '学習者情報を確認できませんでした。';
        } else {
            $allowedStudentResult = $stmtAllowedStudent->get_result();
            $allowedStudent = $allowedStudentResult->fetch_assoc();
            if (!$allowedStudent) {
                $resultMessage = '担当グループ(クラス)外の学習者は変更できません。';
            } else {
                $studentFound = true;
                $studentName = (string)$allowedStudent['Name'];
                $oldClassId = (string)$allowedStudent['ClassID'];
                $oldClassName = (string)$allowedStudent['ClassName'];
            }
        }
        $stmtAllowedStudent->close();
    }

    if ($studentFound) {
        $stmtAllowedClass = $conn->prepare(
            "SELECT ct.ClassID, c.ClassName
             FROM classteacher ct
             JOIN classes c ON ct.ClassID = c.ClassID
             WHERE ct.ClassID = ? AND ct.TID = ?
             LIMIT 1"
        );

        if (!$stmtAllowedClass) {
            $resultMessage = '変更先グループ(クラス)確認の準備に失敗しました。';
        } else {
            $stmtAllowedClass->bind_param('ss', $newClassId, $teacherId);
            if (!$stmtAllowedClass->execute()) {
                $resultMessage = '変更先グループ(クラス)を確認できませんでした。';
            } else {
                $allowedClassResult = $stmtAllowedClass->get_result();
                $allowedClass = $allowedClassResult->fetch_assoc();
                if (!$allowedClass) {
                    $resultMessage = '担当していないグループ(クラス)には変更できません。';
                } else {
                    $newClassAllowed = true;
                    $newClassName = (string)$allowedClass['ClassName'];
                }
            }
            $stmtAllowedClass->close();
        }
    }

    if ($studentFound && $newClassAllowed) {
        if ($oldClassId === $newClassId) {
            $resultStatus = 'success';
            $resultTitle = '所属クラスに変更はありません';
            $resultMessage = '選択された学習者は、すでに指定したグループ(クラス)に所属しています。';
            $updateCompleted = true;
        } else {
            $stmtUpdate = $conn->prepare('UPDATE students SET ClassID = ? WHERE uid = ?');
            if (!$stmtUpdate) {
                $resultMessage = '所属グループ(クラス)変更の準備に失敗しました。';
            } else {
                $stmtUpdate->bind_param('ss', $newClassId, $studentUid);
                if ($stmtUpdate->execute()) {
                    $resultStatus = 'success';
                    $resultTitle = '所属クラスを変更しました';
                    $resultMessage = '学習者の所属グループ(クラス)を更新しました。';
                    $updateCompleted = true;
                } else {
                    $resultMessage = '所属グループ(クラス)変更中にエラーが発生しました：'
                        . $stmtUpdate->error;
                }
                $stmtUpdate->close();
            }
        }
    }
}

$statusIcon = $resultStatus === 'success' ? '✓' : '×';
$teacher_page_title = '学習者所属クラス変更結果';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学習者所属クラス変更結果</title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <link rel="stylesheet" href="../style/teacher_submit_result_styles.css">
</head>
<body>
    <?php
    include __DIR__ . '/teacher-menu.php';
    $conn->close();
    ?>

    <div class="main-content">
        <main class="page-content submit-result-page">
            <section class="card submit-result-card is-<?= htmlspecialchars($resultStatus, ENT_QUOTES, 'UTF-8') ?>">
                <header class="submit-result-hero">
                    <span class="submit-result-icon" aria-hidden="true"><?= $statusIcon ?></span>
                    <div class="submit-result-heading">
                        <h2><?= htmlspecialchars($resultTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <p><?= htmlspecialchars($resultMessage, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </header>

                <div class="submit-result-body">
                    <dl class="submit-result-summary-grid">
                        <div class="submit-result-summary-item">
                            <dt>学習者</dt>
                            <dd><?= htmlspecialchars($studentFound ? ($studentName !== '' ? $studentName : '名称未設定') : '確認できません', ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="submit-result-summary-item">
                            <dt>UID</dt>
                            <dd><?= htmlspecialchars($studentUid !== '' ? $studentUid : '未指定', ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </dl>

                    <?php if ($studentFound && $newClassAllowed): ?>
                        <section class="class-transfer-section" aria-labelledby="class-transfer-title">
                            <h3 id="class-transfer-title">所属グループ(クラス)</h3>
                            <div class="class-transfer-flow">
                                <div class="class-transfer-card">
                                    <span class="class-transfer-label">変更前</span>
                                    <span class="class-transfer-name">
                                        <?= htmlspecialchars($oldClassName !== '' ? $oldClassName : '未設定', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="class-transfer-id">
                                        ID: <?= htmlspecialchars($oldClassId !== '' ? $oldClassId : '未設定', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <span class="class-transfer-arrow" aria-hidden="true">→</span>

                                <div class="class-transfer-card is-destination">
                                    <span class="class-transfer-label">変更後</span>
                                    <span class="class-transfer-name">
                                        <?= htmlspecialchars($newClassName, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="class-transfer-id">
                                        ID: <?= htmlspecialchars($newClassId, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($updateCompleted): ?>
                        <p class="submit-result-note">
                            変更内容は、学習者グループ作成画面の所属情報にも反映されます。
                        </p>
                    <?php endif; ?>

                    <nav class="submit-result-actions" aria-label="次の操作">
                        <a class="submit-result-button" href="create-student-group.php">学習者グループ作成へ戻る</a>
                        <a class="submit-result-button is-secondary" href="teachertrue.php">ホームへ戻る</a>
                    </nav>
                </div>
            </section>
        </main>
    </div>
</body>
</html>

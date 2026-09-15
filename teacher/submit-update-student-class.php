<?php
include '../lang.php';
require '../dbc.php';

$isPostRequest = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$teacherId = (string)($_SESSION['MemberID'] ?? '');
$studentUidValues = $_POST['student_uids'] ?? ($_POST['student_uid'] ?? []);
$newClassIdValue = $_POST['class_id'] ?? '';
$newClassId = is_scalar($newClassIdValue) ? trim((string)$newClassIdValue) : '';

if (!is_array($studentUidValues)) {
    $studentUidValues = [$studentUidValues];
}

$studentUids = [];
$hasInvalidStudentUid = false;
foreach ($studentUidValues as $studentUidValue) {
    if (!is_scalar($studentUidValue)) {
        $hasInvalidStudentUid = true;
        continue;
    }

    $studentUid = trim((string)$studentUidValue);
    if ($studentUid !== '') {
        $studentUids[$studentUid] = $studentUid;
    }
}
$studentUids = array_values($studentUids);

$resultStatus = 'error';
$resultTitle = '所属クラスを変更できませんでした';
$resultMessage = '';
$newClassName = '';
$updateCompleted = false;
$studentsVerified = false;
$newClassAllowed = false;
$selectedStudents = [];
$updatedCount = 0;
$unchangedCount = 0;
$transactionStarted = false;

if ($teacherId === '') {
    $resultMessage = 'ログイン情報を確認できません。もう一度ログインしてください。';
} elseif (!$isPostRequest) {
    $resultMessage = 'このページは、学習者グループ作成画面から操作してください。';
} elseif ($hasInvalidStudentUid || empty($studentUids) || $newClassId === '') {
    $resultMessage = '学習者を1人以上選択し、変更先グループ(クラス)を指定してください。';
} else {
    try {
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

        if ($newClassAllowed) {
            if (!$conn->begin_transaction()) {
                $resultMessage = '一括変更の準備に失敗しました。';
            } else {
                $transactionStarted = true;
                $studentPlaceholders = implode(',', array_fill(0, count($studentUids), '?'));
                $stmtAllowedStudents = $conn->prepare(
                    "SELECT s.uid, s.Name, s.ClassID, COALESCE(c.ClassName, '未設定') AS ClassName
                     FROM students s
                     JOIN classteacher ct ON s.ClassID = ct.ClassID
                     LEFT JOIN classes c ON s.ClassID = c.ClassID
                     WHERE s.uid IN ({$studentPlaceholders}) AND ct.TID = ?
                     FOR UPDATE"
                );

                if (!$stmtAllowedStudents) {
                    $resultMessage = '学習者確認の準備に失敗しました。';
                } else {
                    $allowedStudentTypes = str_repeat('s', count($studentUids) + 1);
                    $allowedStudentParams = array_merge($studentUids, [$teacherId]);
                    $stmtAllowedStudents->bind_param($allowedStudentTypes, ...$allowedStudentParams);
                    if (!$stmtAllowedStudents->execute()) {
                        $resultMessage = '学習者情報を確認できませんでした。';
                    } else {
                        $allowedStudentResult = $stmtAllowedStudents->get_result();
                        $allowedStudentsByUid = [];
                        while ($allowedStudent = $allowedStudentResult->fetch_assoc()) {
                            $allowedStudentsByUid[(string)$allowedStudent['uid']] = $allowedStudent;
                        }

                        if (count($allowedStudentsByUid) !== count($studentUids)) {
                            $resultMessage = '担当グループ(クラス)外、または存在しない学習者が含まれているため変更できません。';
                        } else {
                            foreach ($studentUids as $studentUid) {
                                $student = $allowedStudentsByUid[$studentUid];
                                $selectedStudents[] = [
                                    'uid' => (string)$student['uid'],
                                    'name' => (string)$student['Name'],
                                    'oldClassId' => (string)$student['ClassID'],
                                    'oldClassName' => (string)$student['ClassName'],
                                ];
                            }
                            $studentsVerified = true;
                        }
                    }
                    $stmtAllowedStudents->close();
                }

                if ($studentsVerified) {
                    $stmtUpdate = $conn->prepare('UPDATE students SET ClassID = ? WHERE uid = ?');
                    if (!$stmtUpdate) {
                        $resultMessage = '所属グループ(クラス)変更の準備に失敗しました。';
                    } else {
                        $updateUid = '';
                        $stmtUpdate->bind_param('ss', $newClassId, $updateUid);
                        $allUpdatesSucceeded = true;

                        foreach ($selectedStudents as $student) {
                            if ($student['oldClassId'] === $newClassId) {
                                $unchangedCount++;
                                continue;
                            }

                            $updateUid = $student['uid'];
                            if (!$stmtUpdate->execute()) {
                                $allUpdatesSucceeded = false;
                                $resultMessage = '所属グループ(クラス)の一括変更中にエラーが発生しました。';
                                break;
                            }
                            $updatedCount++;
                        }
                        $stmtUpdate->close();

                        if ($allUpdatesSucceeded && $conn->commit()) {
                            $transactionStarted = false;
                            $resultStatus = 'success';
                            $updateCompleted = true;
                            $selectedCount = count($selectedStudents);

                            if ($updatedCount === 0) {
                                $resultTitle = '所属クラスに変更はありません';
                                $resultMessage = "選択した{$selectedCount}人は、すでに指定したグループ(クラス)に所属しています。";
                            } else {
                                $resultTitle = '所属クラスを一括変更しました';
                                $resultMessage = "選択した{$selectedCount}人のうち{$updatedCount}人を変更しました。";
                                if ($unchangedCount > 0) {
                                    $resultMessage .= " 残り{$unchangedCount}人は変更先に所属済みです。";
                                }
                            }
                        } elseif ($allUpdatesSucceeded) {
                            $resultMessage = '所属グループ(クラス)変更を確定できませんでした。';
                        }
                    }
                }
            }
        }
    } catch (Throwable $error) {
        $resultMessage = '所属グループ(クラス)の一括変更中にエラーが発生しました。';
    }

    if ($transactionStarted) {
        $conn->rollback();
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
                            <dt>選択した学習者</dt>
                            <dd><?= count($studentUids) ?>人</dd>
                        </div>
                        <?php if ($updateCompleted): ?>
                        <div class="submit-result-summary-item">
                            <dt>変更した学習者</dt>
                            <dd><?= $updatedCount ?>人</dd>
                        </div>
                        <div class="submit-result-summary-item">
                            <dt>変更先に所属済み</dt>
                            <dd><?= $unchangedCount ?>人</dd>
                        </div>
                        <?php endif; ?>
                    </dl>

                    <?php if ($updateCompleted && $studentsVerified && $newClassAllowed): ?>
                        <section class="class-transfer-section" aria-labelledby="class-transfer-title">
                            <h3 id="class-transfer-title">学習者ごとの変更内容</h3>
                            <ul class="bulk-transfer-result-list">
                                <?php foreach ($selectedStudents as $student): ?>
                                    <?php $isUnchanged = $student['oldClassId'] === $newClassId; ?>
                                    <li>
                                        <span class="bulk-transfer-student">
                                            <strong><?= htmlspecialchars($student['name'] !== '' ? $student['name'] : '名称未設定', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small>UID: <?= htmlspecialchars($student['uid'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </span>
                                        <span class="bulk-transfer-route">
                                            <?= htmlspecialchars($student['oldClassName'], ENT_QUOTES, 'UTF-8') ?>
                                            <span aria-hidden="true">→</span>
                                            <strong><?= htmlspecialchars($newClassName, ENT_QUOTES, 'UTF-8') ?></strong>
                                        </span>
                                        <span class="bulk-transfer-state<?= $isUnchanged ? ' is-unchanged' : '' ?>">
                                            <?= $isUnchanged ? '変更なし' : '変更' ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
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

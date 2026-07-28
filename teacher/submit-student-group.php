<?php
include '../lang.php';
require '../dbc.php';

unset($_SESSION['conditions']);

$isPostRequest = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$teacherId = (string)($_SESSION['MemberID'] ?? '');
$groupNameValue = $_POST['group_name'] ?? '';
$groupName = is_scalar($groupNameValue) ? trim((string)$groupNameValue) : '';
$selectedStudents = [];
$postedStudents = $_POST['students'] ?? [];

if (is_array($postedStudents)) {
    foreach ($postedStudents as $postedStudent) {
        if (!is_scalar($postedStudent)) {
            continue;
        }
        $studentId = trim((string)$postedStudent);
        if ($studentId !== '') {
            $selectedStudents[$studentId] = $studentId;
        }
    }
}
$selectedStudents = array_values($selectedStudents);

$groupCreated = false;
$groupId = null;
$resultStatus = 'error';
$resultMessage = '';
$memberResults = [];
$successfulMemberCount = 0;
$failedMemberCount = 0;

if ($teacherId === '') {
    $resultMessage = 'ログイン情報を確認できません。もう一度ログインしてください。';
} elseif (!$isPostRequest) {
    $resultMessage = 'このページは、学習者グループ作成画面から操作してください。';
} elseif ($groupName === '') {
    $resultMessage = 'グループ名を入力してください。';
} else {
    $stmtGroup = $conn->prepare('INSERT INTO `groups` (group_name, TID) VALUES (?, ?)');
    if (!$stmtGroup) {
        $resultMessage = translate('submit-student-group.php_53行目_グループ作成に失敗しました');
    } else {
        $stmtGroup->bind_param('ss', $groupName, $teacherId);
        if ($stmtGroup->execute()) {
            $groupCreated = true;
            $groupId = $stmtGroup->insert_id;
            $resultMessage = translate('submit-student-group.php_51行目_グループが正常に作成されました');
        } else {
            $resultMessage = translate('submit-student-group.php_53行目_グループ作成に失敗しました')
                . '：' . $stmtGroup->error;
        }
        $stmtGroup->close();
    }

    if ($groupCreated && !empty($selectedStudents)) {
        $stmtMember = $conn->prepare('INSERT INTO group_members (group_id, uid) VALUES (?, ?)');
        if (!$stmtMember) {
            $failedMemberCount = count($selectedStudents);
            foreach ($selectedStudents as $studentId) {
                $memberResults[] = [
                    'student_id' => $studentId,
                    'success' => false,
                    'message' => translate('submit-student-group.php_63行目_学生追加に失敗しました'),
                ];
            }
        } else {
            foreach ($selectedStudents as $studentId) {
                $stmtMember->bind_param('is', $groupId, $studentId);
                $memberAdded = $stmtMember->execute();
                if ($memberAdded) {
                    $successfulMemberCount++;
                } else {
                    $failedMemberCount++;
                }
                $memberResults[] = [
                    'student_id' => $studentId,
                    'success' => $memberAdded,
                    'message' => $memberAdded
                        ? translate('submit-student-group.php_61行目_がグループに追加されました')
                        : translate('submit-student-group.php_63行目_学生追加に失敗しました') . $stmtMember->error,
                ];
            }
            $stmtMember->close();
        }
    }

    if ($groupCreated && $failedMemberCount === 0) {
        $resultStatus = 'success';
    } elseif ($groupCreated) {
        $resultStatus = 'warning';
        $resultMessage = 'グループは作成されましたが、一部の学習者を追加できませんでした。';
    }
}

$statusTitle = [
    'success' => 'グループを作成しました',
    'warning' => '一部の登録を完了できませんでした',
    'error' => 'グループを作成できませんでした',
][$resultStatus];
$statusIcon = [
    'success' => '✓',
    'warning' => '!',
    'error' => '×',
][$resultStatus];

$teacher_page_title = '学習者グループ作成結果';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('submit-student-group.php_5行目_教師用ダッシュボード') ?></title>
    <link rel="stylesheet" href="../style/teachertrue_styles.css">
    <style>
        .group-submit-page {
            width: min(960px, 100%);
            margin: 0 auto;
            box-sizing: border-box;
        }

        .group-result-card {
            padding: 0;
            overflow: hidden;
            border-top: 5px solid #dc2626;
        }

        .group-result-card.is-success {
            border-top-color: #0f766e;
        }

        .group-result-card.is-warning {
            border-top-color: #d97706;
        }

        .group-result-hero {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 28px 30px 22px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .group-result-icon {
            display: inline-flex;
            flex: 0 0 54px;
            width: 54px;
            height: 54px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 1.8rem;
            font-weight: 800;
        }

        .is-success .group-result-icon {
            background: #ccfbf1;
            color: #0f766e;
        }

        .is-warning .group-result-icon {
            background: #fef3c7;
            color: #b45309;
        }

        .group-result-heading h2 {
            margin: 0 0 6px;
            padding: 0;
            border: 0;
            color: #1f2937;
            font-size: clamp(1.35rem, 3vw, 1.75rem);
        }

        .group-result-heading p {
            margin: 0;
            color: #64748b;
            line-height: 1.65;
        }

        .group-result-body {
            padding: 26px 30px 30px;
        }

        .group-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin: 0 0 24px;
        }

        .group-summary-item {
            min-width: 0;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }

        .group-summary-item dt {
            margin-bottom: 7px;
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .group-summary-item dd {
            margin: 0;
            overflow-wrap: anywhere;
            color: #1f2937;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .member-result-section {
            margin-top: 8px;
            padding-top: 22px;
            border-top: 1px solid #e5e7eb;
        }

        .member-result-section h3 {
            margin: 0 0 14px;
            color: #1f2937;
            font-size: 1.05rem;
        }

        .member-result-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .member-result-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }

        .member-result-badge {
            flex: 0 0 auto;
            padding: 3px 8px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 0.75rem;
            font-weight: 800;
        }

        .member-result-item.is-error .member-result-badge {
            background: #fee2e2;
            color: #b91c1c;
        }

        .member-result-text {
            min-width: 0;
            color: #475569;
            line-height: 1.5;
        }

        .member-result-text strong {
            display: block;
            overflow-wrap: anywhere;
            color: #1f2937;
        }

        .empty-member-note {
            margin: 0;
            padding: 14px 16px;
            border-radius: 8px;
            background: #f8fafc;
            color: #64748b;
        }

        .group-result-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
        }

        .group-result-button {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 0 18px;
            border: 1px solid #007bff;
            border-radius: 6px;
            background: #007bff;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            transition: background-color 0.2s, border-color 0.2s;
        }

        .group-result-button:hover {
            border-color: #0056b3;
            background: #0056b3;
        }

        .group-result-button.is-secondary {
            border-color: #cbd5e1;
            background: #fff;
            color: #334155;
        }

        .group-result-button.is-secondary:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }

        @media (max-width: 700px) {
            .group-result-hero,
            .group-result-body {
                padding-left: 20px;
                padding-right: 20px;
            }

            .group-summary-grid {
                grid-template-columns: 1fr;
            }

            .group-result-actions {
                flex-direction: column;
            }

            .group-result-button {
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <?php
    include __DIR__ . '/teacher-menu.php';
    $conn->close();
    ?>
    <div class="main-content">
        <main class="page-content group-submit-page">
            <section class="card group-result-card is-<?= htmlspecialchars($resultStatus, ENT_QUOTES, 'UTF-8') ?>">
                <header class="group-result-hero">
                    <span class="group-result-icon" aria-hidden="true"><?= $statusIcon ?></span>
                    <div class="group-result-heading">
                        <h2><?= htmlspecialchars($statusTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <p><?= htmlspecialchars($resultMessage, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </header>

                <div class="group-result-body">
                    <dl class="group-summary-grid">
                        <div class="group-summary-item">
                            <dt><?= translate('submit-student-group.php_44行目_グループ名') ?></dt>
                            <dd><?= htmlspecialchars($groupName !== '' ? $groupName : '未指定', ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="group-summary-item">
                            <dt><?= translate('submit-student-group.php_46行目_教師ID') ?></dt>
                            <dd><?= htmlspecialchars($teacherId !== '' ? $teacherId : '確認できません', ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <div class="group-summary-item">
                            <dt>登録学習者数</dt>
                            <dd>
                                <?= $successfulMemberCount ?> / <?= count($selectedStudents) ?> 名
                            </dd>
                        </div>
                    </dl>

                    <?php if ($groupCreated): ?>
                        <section class="member-result-section" aria-labelledby="member-result-title">
                            <h3 id="member-result-title">
                                <?= translate('submit-student-group.php_45行目_学生リスト') ?>
                            </h3>
                            <?php if (empty($memberResults)): ?>
                                <p class="empty-member-note">このグループには学習者が選択されていません。</p>
                            <?php else: ?>
                                <ul class="member-result-list">
                                    <?php foreach ($memberResults as $memberResult): ?>
                                        <li class="member-result-item<?= $memberResult['success'] ? '' : ' is-error' ?>">
                                            <span class="member-result-badge">
                                                <?= $memberResult['success'] ? '追加済み' : '失敗' ?>
                                            </span>
                                            <span class="member-result-text">
                                                <strong>UID: <?= htmlspecialchars($memberResult['student_id'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?= htmlspecialchars($memberResult['message'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <nav class="group-result-actions" aria-label="次の操作">
                        <a class="group-result-button" href="create-student-group.php">学習者グループ作成へ戻る</a>
                        <a class="group-result-button is-secondary" href="teachertrue.php">ホームへ戻る</a>
                    </nav>
                </div>
            </section>
        </main>
    </div>
</body>
</html>

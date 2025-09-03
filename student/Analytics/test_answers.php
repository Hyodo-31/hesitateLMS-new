<?php
// ./student/Analytics/test_answers.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../dbc.php';

if (!isset($_SESSION['MemberID'])) {
    echo json_encode(['ok' => false, 'msg' => 'ログインが必要です。']);
    exit;
}
$uid = (int) $_SESSION['MemberID'];
$test_id = isset($_GET['test_id']) ? (int) $_GET['test_id'] : 0;
if ($test_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'test_id が不正です。']);
    exit;
}

try {
    // 1) テストに含まれる WID を取得（test_questions.OID -> quesorder.WID）
    $sqlW = "SELECT DISTINCT qo.WID
             FROM test_questions tq
             INNER JOIN quesorder qo ON qo.OID = tq.OID
             WHERE tq.test_id = ?";
    $stmtW = $conn->prepare($sqlW);
    $stmtW->bind_param('i', $test_id);
    $stmtW->execute();
    $resW = $stmtW->get_result();
    $wids = [];
    while ($row = $resW->fetch_assoc()) {
        $wids[] = (int) $row['WID'];
    }
    $stmtW->close();

    // 返却用
    $rows = [];

    // ======================= ▼▼▼ ここから修正 ▼▼▼ =======================
    // 2) 各WIDについて、機械学習結果テーブル(temporary_results)から
    //    「最新の作成日時」の結果を取得し、対応する正誤(linedata.TF)をJOINする。

    $sql = "
        SELECT
            tr.Understand,
            tr.attempt,
            ld.TF
        FROM
            temporary_results AS tr
        LEFT JOIN
            linedata AS ld ON tr.UID = ld.UID AND tr.WID = ld.WID AND tr.attempt = ld.attempt
        WHERE
            tr.UID = ? AND tr.WID = ?
        ORDER BY
            tr.created_at DESC -- 解答回数(attempt)ではなく作成日時(created_at)で並び替える
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);

    foreach ($wids as $wid) {
        $understand_code = null;
        $attempt = null;
        $tf_code = null;

        $stmt->bind_param('ii', $uid, $wid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // 機械学習結果テーブルから取得した値
            $understand_code = is_null($row['Understand']) ? null : (int) $row['Understand'];
            $attempt = is_null($row['attempt']) ? null : (int) $row['attempt'];
            
            // linedataテーブルから取得した正誤の値
            $tf_code = is_null($row['TF']) ? null : (int) $row['TF'];
        }
        $result->free();

        // 軌跡再現リンク
        $link = "../../teacher/mousemove/mousemove.php?UID=" . urlencode((string) $uid)
            . "&WID=" . urlencode((string) $wid)
            . "&LogID=" . urlencode((string) $attempt);

        $rows[] = [
            'WID' => $wid,
            'understand_code' => $understand_code,
            'tf_code' => $tf_code,
            'attempt' => $attempt,
            'link' => $attempt ? $link : null, // attemptがない場合はリンクをnullにする
        ];
    }

    $stmt->close();
    // ======================= ▲▲▲ 修正はここまで ▲▲▲ =======================

    echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => 'サーバーエラー: ' . $e->getMessage()]);
}

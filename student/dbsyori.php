<?php
/**
 * Error reporting level
 */
//error_reporting(E_ALL);   // デバッグ時
error_reporting(0);   // 運用時
include '../lang.php';
// session_start(); // lang.phpで開始済み
require "../dbc.php";
$MemberID = $_SESSION["MemberID"];

// WIDをパラメータから取得
$wid = isset($_GET['param1']) ? $_GET['param1'] : null;
$param2 = isset($_GET['param2']) ? $_GET['param2'] : null;

// WIDが必要な処理のためにバリデーション
if (in_array($param2, ['q', 'q1', 'd', 'j', 'f', 's1', 's2', 'p']) && !is_numeric($wid)) {
    die(translate('dbsyori.php_21行目_エラー'));
}

// 解答系文の参照
if ($param2 == "q") {
    $Question = "SELECT Sentence FROM question_info WHERE WID = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['Sentence'];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}

// 開始英文の参照
else if ($param2 == "q1") {
    $Question = "SELECT start FROM question_info WHERE WID = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['start'];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}

// divideの参照
else if ($param2 == "d") {
    $Divide = "SELECT divide FROM question_info WHERE WID = ?";
    $stmt = $conn->prepare($Divide);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['divide'];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}
// -------------------------------------------------------------------------------------
// 日本語の参照
else if ($param2 == "j") {
    $JP = "SELECT Japanese FROM question_info WHERE WID = ?";
    $stmt = $conn->prepare($JP);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['Japanese'];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}
// ----------------------------------------------------------------
// Fixの参照
else if ($param2 == "f") {
    $Fix = "SELECT Fix FROM question_info WHERE WID = ?";
    $stmt = $conn->prepare($Fix);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        // 必要であれば文字コード変換を行う
        echo mb_convert_encoding($row['Fix'], "UTF-8", "EUC-JP");
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}
// ----------------------------------------------------------------
// 別解１の参照
else if ($param2 == "s1") {
    $Question = "SELECT PartSentence FROM partans WHERE Point = 10 AND WID = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['PartSentence'];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}
// ---------------------------------------------
// 別解2の参照 (カラム名がSentence2で正しいか確認してください)
else if ($param2 == "s2") {
    $Question = "SELECT Sentence2 FROM question_info WHERE WID = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['Sentence2'];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}
// ---------------------------------------------------
// 部分点検索
else if ($param2 == "p") {
    $Question = "SELECT PartSentence FROM PartAns WHERE WID = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['PartSentence'];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}
// -----------------------------------------------------
// 問題を決めた個数
else if ($param2 == "count") {
    // このクエリはmysqli_queryで問題ないが、一貫性のためプリペアードステートメントを推奨
    $Question = "SELECT count(*) as count FROM test_questions WHERE test_id = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('i', $_SESSION["Qid"]);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['count'];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}
// 問題を決めての検索 (元からプリペアドステートメント)
else if ($param2 == "load") {
    $Question = "SELECT WID FROM test_questions WHERE OID = ? AND test_id = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('ii', $wid, $_SESSION["Qid"]);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['WID'];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}

// 20250514追加
// user_progress追加 (元からプリペアドステートメント)
else if ($param2 == "u") {
    $insert_SQL = "INSERT INTO user_progress (uid, test_id, current_oid) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_SQL);
    $stmt->bind_param('iii', $MemberID, $_SESSION["Qid"], $wid);
    $stmt->execute();
    $stmt->close();
}
?>
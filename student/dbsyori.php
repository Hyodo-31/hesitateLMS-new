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

// ======================= ▼▼▼ 修正点 ▼▼▼ =======================
// ques.phpから渡された言語情報を取得
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en'; // デフォルトは 'en' (英語)

// 言語情報に応じてテーブル名を決定
$question_table = ($lang === 'ja') ? 'question_info_ja' : 'question_info';
// ======================= ▲▲▲ 修正点 ▲▲▲ =======================

// WIDが必要な処理のためにバリデーション
if (in_array($param2, ['q', 'q1', 'd', 'j', 'f', 's1', 's2', 'p']) && !is_numeric($wid)) {
    die(translate('dbsyori.php_21行目_エラー'));
}

// 解答系文の参照
if ($param2 == "q") {
    // 日本語テストの場合は`English`カラムを、英語テストの場合は`Sentence`カラムを参照
    $column = ($lang === 'ja') ? 'English' : 'Sentence';
    $Question = "SELECT {$column} FROM {$question_table} WHERE WID = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row[$column];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}

// 開始英文の参照
else if ($param2 == "q1") {
    $Question = "SELECT start FROM {$question_table} WHERE WID = ?";
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
    $Divide = "SELECT divide FROM {$question_table} WHERE WID = ?";
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
    // 日本語テストの場合は`Sentence`カラムを、英語テストの場合は`Japanese`カラムを参照
    $column = ($lang === 'ja') ? 'Sentence' : 'Japanese';
    $JP = "SELECT {$column} FROM {$question_table} WHERE WID = ?";
    $stmt = $conn->prepare($JP);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row[$column];
    } else {
        echo translate('dbsyori.php_21行目_エラー');
    }
    $stmt->close();
}
// ----------------------------------------------------------------
// Fixの参照
else if ($param2 == "f") {
    $Fix = "SELECT Fix FROM {$question_table} WHERE WID = ?";
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
    // ★注意: 別解テーブル(partans)が言語ごとに分かれていない場合、このままでは機能しません。
    // partansテーブルにも言語を区別するカラムが必要になる可能性があります。
    // ここでは、一旦そのままにしておきます。
    $Question = "SELECT PartSentence FROM partans WHERE Point = 10 AND WID = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['PartSentence'];
    } else {
        // エラーではなく空を返す方が挙動として自然かもしれません
        echo "";
    }
    $stmt->close();
}
// ---------------------------------------------
// 別解2の参照
else if ($param2 == "s2") {
    // Sentence2カラムは question_info にしか無い可能性が高いです。
    // question_info_ja にも同様のカラムが存在するか確認が必要です。
    $Question = "SELECT Sentence2 FROM {$question_table} WHERE WID = ?";
    $stmt = $conn->prepare($Question);
    $stmt->bind_param('i', $wid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo $row['Sentence2'];
    } else {
        // エラーではなく空を返す方が挙動として自然かもしれません
        echo "";
    }
    $stmt->close();
}
// ---------------------------------------------------
// (以降の処理は test_questions や user_progress を参照しており、言語に依存しないため修正不要)
// ...
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
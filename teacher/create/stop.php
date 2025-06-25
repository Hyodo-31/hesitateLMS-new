<?php
// セッションを開始して、セッション変数にアクセスできるようにする
session_start();

// 問題作成プロセスで使用されるセッション変数をすべてクリア（unset）する
// これにより、次回新しい問題を作成する際に古いデータが残らないようにします
unset($_SESSION["Japanese"]);
unset($_SESSION["Sentence"]);
unset($_SESSION["dtcnt"]);
unset($_SESSION["divide"]);
unset($_SESSION["divide2"]);
unset($_SESSION["fix"]);
unset($_SESSION["fixlabel"]);
unset($_SESSION["num"]);
unset($_SESSION["pro"]);
unset($_SESSION["property"]);
unset($_SESSION["start"]);
unset($_SESSION["mode"]);
unset($_SESSION["view_Sentence"]);
unset($_SESSION["wordnum"]);
unset($_SESSION["rock"]);

// 教師のダッシュボード（ホーム画面）にリダイレクトする
header("Location: ../teachertrue.php");
exit; // リダイレクト後にスクリプトの実行を確実に終了させます
?>
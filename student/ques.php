<?php
//ログイン関連
error_reporting(E_ALL);
session_start();
include '../lang.php';
if(!isset($_SESSION["MemberName"])){
    require"notlogin";
    session_destroy();
    exit;
}

if($_SESSION["examflag"] == 1){
    require"overlap.php";
    exit;
}else{
    $_SESSION["examflag"] = 2;
    $_SESSION["page"] = "ques";
}
?>
<!DOCTYPE html PUBLIC "-//W3c//DTD HTML 4.01 Transitional//EN">
<html lang="<?= $lang ?>">
<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?= translate('ques.php_22行目_並べ替え問題プログラム') ?></title>
<link rel="stylesheet" href="../style/StyleSheet.css" type="text/css" /> 

<script type="text/javascript"
        src="jquery-1.11.3.min.js"></script>
<script type="text/javascript"
        src="yui/build/yahoo/yahoo-min.js"></script>
<script type="text/javascript"
        src="yui/build/event/event-min.js"></script>
<script type="text/javascript"
        src="yui/build/dom/dom-min.js"></script>
<script type="text/javascript"
        src="prototype.js"></script>
<script type="text/javascript"
        src="dateformat.js"></script>
<script type="text/javascript"
        src="wz_jsgraphics.js"></script>
<script type="text/javascript"
        src="yui/build/dragdrop/dragdrop-min.js"></script>
<script type="text/javascript"
        src="yui/build/animation/animation-min.js"></script>
</head>

<script type="text/javascript">
    //ストップウォッチ関数
    myButton = 0;            // [Start]/[Stop]のフラグ
    var myStart;
    var myStop;
    function myCheck(myFlg) {
        if (myButton == 0) {      // Startボタンを押した
            myStart = new Date();  // スタート時間を退避
            myButton = 1;
            myInterval = setInterval("myCheck(1)", 1);
        } else {                 // スタート実行中
            if (myFlg == 0) {       // Stopボタンを押した
                myButton = 0;
                clearInterval(myInterval);
            }
            myStop = new Date();  // 経過時間を退避
            myTime = myStop.getTime() - myStart.getTime(); // 通算ミリ秒計算
            myS = Math.floor(myTime / 1000);                 // '秒'取得
            myMS = myTime % 1000;                        // 'ミリ秒'取得
            document.getElementById("TextBox1").innerHTML = myS + "." + myMS + <?= json_encode(translate('ques.php_82行目_秒')) ?>;
        }
    }
    //ストップウォッチ関数2(全体用)
    myButton2 = 0;            // [Start]/[Stop]のフラグ
    var myStart2;
    var myStop2;
    function myCheck2(myFlg2) {
        if (myButton2 == 0) {      // Startボタンを押した
            myStart2 = new Date();  // スタート時間を退避
            myButton2 = 1;
            myInterval2 = setInterval("myCheck2(1)", 1);
        } else {                 // スタート実行中
            if (myFlg2 == 0) {      // Stopボタンを押した
                myButton2 = 0;
                clearInterval(myInterval2);
            }
            myStop2 = new Date();  // 経過時間を退避
            myTime2 = myStop2.getTime() - myStart2.getTime(); // 通算ミリ秒計算
            myS2 = Math.floor(myTime2 / 1000);                 // '秒'取得
            myMS2 = myTime2 % 1000;                        // 'ミリ秒'取得
        }
    }
    //＃構造体の宣言
    var Mouse = new Object();
    Mouse["Time"] = 0;
    Mouse["X"] = 0;
    Mouse["Y"] = 0;
    Mouse["DragDrop"] = 0;  //ドラッグ中か（0:MouseMove,1:MouseDown,2:MouseUp)
    Mouse["DropPos"] = 0;       //どこドロップされたか(0:元,1:レジスタ1,2:レジスタ2,3:レジスタ3)
    Mouse["hlabel"] = "";       //ドラッグしているラベル（マウスが当たっているラベル）
    Mouse["Label"] = "";        //どのラベルが対象か（複数ラベル)
    Mouse["WID"] = 0;
    //-------------------------
    var AnswerData = new Object();
    AnswerData["WID"] = 0;                       //問題番号
    AnswerData["Date"] = new Date; //解答日時
    AnswerData["TF"] = 0;                       //正誤
    AnswerData["Time"] = 0;                 //解答時間
    AnswerData["Understand"] = 0;
    AnswerData["EndSentence"] = "";
    AnswerData["hesitate"] = "";
    AnswerData["hesitate1"] = "";
    AnswerData["hesitate2"] = "";
    AnswerData["comments"] = "";
    AnswerData["check"] = 0;
    $countHearing = [];
    $s = 0;

    //--------------------------
    p = new Array();
    Mouse_Flag = new Boolean(false);    //マウスの軌跡を保存するかどうか
    IsDragging = new Boolean(false);    //ドラッグ中の場合true
    function Point(_x, _y) { this.x = _x; this.y = _y; }
    //使う例 print(DiffPoint.x);
    var DiffPoint = new Point(0, 0);     //ドラッグ開始地点とドラッグ開始時のボタンの位置とのずれ
    var DLabel = "";
    var x = 0;                                      //挿入線を描画する位置
    var y1 = 0;
    var y2 = 0;
    //frag = 0;
    var Mylabels = new Array();             //並び替えラベルの元
    var MylabelsD = new Array();            //divideyou
    var Mylabels_r1 = new Array();      //レジスタ用
    var Mylabels_r2 = new Array();
    var Mylabels_r3 = new Array();
    var Mylabels_ea = new Array();      //最終解答欄用
    var MyLabels_h = new Array();       //ヒアリング機能用
    var MyNums = new Array();               //番号リスト
    var DefaultX = 30;                      //ラベルの初期値
    var DefaultY = 100;
    var DefaultX_r1 = 30;                       //ラベルの初期値
    var DefaultY_r1 = 250;
    var DefaultX_r2 = 30;                       //ラベルの初期値
    var DefaultY_r2 = 330;
    var DefaultX_r3 = 30;                       //ラベルの初期値
    var DefaultY_r3 = 410;
    var DefaultX_ea = 30;                       //ラベルの初期値
    var DefaultY_ea = 170;
    var sPos = new Point(0, 0);
    var ePos = new Point(0, 0);
    var PorQ;                               //文末の.または?を格納するよう
    var Answer;                             //正解
    var Question;                       //問題文(先頭小文字、文末ぬき）
    var str1;                                   //Answerの補助
    var str2;
    var LabelNum;                           //ラベルの数
    var Answer;                             //正解
    var Answer1;                            //別解1
    var Answer2;                            //別解2
    var linedataFlg = false;                //linedataに書き込み中
    var Answertime = new Date;          //解答日時(datatime?)
    var $Mouse_Data = Mouse;                //マウスの軌跡情報を保持
    var Mouse_Num;                                  //マウスの軌跡情報の数
    var StartQues = "";                           //始めの問題の状態
    var MyAnswer = "";                    //自分の答え
    var WriteAnswer = "";             //自分の答え保存用
    var $QAData = AnswerData; //問題データ保存用
    var MyControls = new Array();       //グループ化ラベルをまとめた配列
    var AllCorrectAns = 0;                  //全体の正解数
    var ResAns = 0;
    var AllResAns = 0;                          //全体の解答数
    var OID = 0;                            //解答番号、linedataとlinedatamouseを関連付けるキー
    var WID = 0;
    var checkl = 0;                     //phpオリジナル、重さをなくすため
    var cx = 0;                             //キャンバスのギャップの修正用
    var cy = 0;
    var MV = new Boolean(false); //グループ化のためのドラッグ中か
    var loc = -1; //グループ化の線の位置　0:左上 1:左下 2:右上 3:右下
    var PreMouseTime = -1; //前回のマウス取得時間（※新しい問題が出るたびに初期化させている）
    var dd = new Array(); //ドラッグドロップ変数
    var $AccessDate; //ログイン日時
    Mld = new Boolean(false); //mylabeldownイベント中か
    var FixLabels = new Array(); //固定ラベル
    var FixNum = new Array(); //固定ラベルの番号
    var FixText = new Array(); //固定ラベルのタグを含むテキスト
    MytoFo = new Boolean(false); //IEのバグ対応。MyLabels_MouseMove→Form1_onMouseMoveのため
    var DragL; //ドラッグ中のラベルの引渡し。
    var QuesNum = 0;   //問題番号のインターフェース用.　何問目？とかにつかう。1-30
    var array_flag = -1; //どこでイベントが起こったか判定する。(マウスダウン用)　0=問題提示欄 1=レジスタ1 2=レジスタ2 3=レジスタ3 4=最終解答欄
    var array_flag2 = -1; //どこでイベントが起こったか判定する。(マウスアップ用)　0=問題提示欄 1=レジスタ1 2=レジスタ2 3=レジスタ3 4=最終解答欄
    var d_flag = -1; //どこでイベントが起こったか判定する。(マウスアップ)　0=問題提示欄 1=レジスタ1 2=レジスタ2 3=3 4=最終解答欄
    //再表示用だよ
    var Mylabels2 = new Array();
    var Mylabels_left = new Array();
    var region = 0;
    var URL = './' //サーバー用
    var attempt = 0;    //20250514追加

    
    var Qid = <?php echo $Qid = $_GET['Qid']; ?> //LineQuesFormのボタンのURL引数
    /*
    var nEnd;
    if (Qid === 0) {
        nEnd = 30;
    }
    else if (Qid === 1) {
        nEnd = 55;
    }
    else if (Qid === 2) {
        nEnd = 80;
    }
    else if (Qid === 3) {
        nEnd = 104;
    }
        */

    
</script>
<?php
    //Qidの値を取得して、それによってnEndを取得
    //nEndはOIDの最終番号
    require "../dbc.php";
    $_SESSION['Qid'] = $_GET['Qid'];
    $Qid = $_GET['Qid'];
    $sql = "select t_q.OID,t_q.WID 
            from test_questions t_q
            WHERE t_q.test_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $Qid);
    $stmt->execute();
    $result = $stmt->get_result();
    $all_rows = $result->fetch_all(MYSQLI_ASSOC);
    $last_rows = end($all_rows);        //最後の行の要素を取得
    $nEnd = $last_rows['OID'];
    //echo $Qid;
    //echo $nEnd;
    $stmt->close();
?>

<script type="text/javascript">
    nEnd = <?php echo $nEnd; ?>
    

    //ランダムに配列を並び替えるソース
    Array.prototype.random = function () {
        this.sort(function (a, b) {
            var i = Math.ceil(Math.random() * 100) % 2;
            if (i == 0) { return -1; }
            else { return 1; }
        });
    }
    //-------------------------------------------------------------
    //配列に指定した値があるかチェック
    if (!Array.prototype.contains) {
        Array.prototype.contains = function (value) {
            for (var i in this) {
                if (this.hasOwnProperty(i) && this[i] === value) {
                    return true;
                }
            }
            return false;
        }
    }
    //-------------------------------------------------------------
    //ロードイベント
    //body がloadされた時点で実行される。
    function ques_Load() {
        new Ajax.Request(URL + 'swrite.php',//こんにちはOOさん出力
        {
            method: 'get',
            onSuccess: getA,
            onFailure: getE
        });
        //▲マウスデータの取得
        //ドラッグ開始地点の保存
        function getA(req) {
            alert(req.responseText);
        }
        function getE(req) {
            alert(<?= json_encode(translate('ques.php_325行目_書き込みに失敗しました')) ?>);
        }
        AnswerT = new DateFormat("yyyy-MM-dd HH:mm:ss");
        $AccessDate = AnswerT.format(new Date());
        BPen = new jsGraphics("myCanvas");                     //ペン(グループ化用)
        BPen.setColor("black");
        //破線のスタイルを設定
        BPen.setStroke(-1);
        BPen2 = new jsGraphics("myCanvas");                    //ペン(挿入線用)
        BPen2.setColor("black");
        //スラッシュ入れる用
        BPen3 = new jsGraphics("myCanvas2");
        document.onmousemove = Form1_MouseMove;
        document.onselectstart = "return false";
        //-------------------------------------------------------------
        //DBから引用
        function getError(req,requestSource) {
            alert(<?= json_encode(translate('ques.php_335行目_リクエストエラー')) ?> + "(" + requestSource + "):" + req.status + "--" + req.statusText);
            console.log("エラー発生元：" + requestSource);
            console.log(req.responseText);
            window.close();
        }
        //=============linedatamouseがなかったら作成============
        new Ajax.Request(URL + 'linemouse.php',
        {
            method: 'get',
            onSuccess: getm,
            onFailure: function(req){getError(req,"linemouse.php")}
        });
        function getm(res) {
        }
        //======================================================

        //解答データのうち最大のOIDを計算。要は次に出題する問題を算出する。
        var $a = "a"; //モード制御用
        $params = 'param1=' + encodeURIComponent($a);
        new Ajax.Request(URL + 'load.php',
        {
            method: 'get',
            onSuccess: getOID,
            onFailure: function(req){getError(req,"load.php")},
            parameters: $params
        });
        function getOID(res) {
            OID = res.responseText; //load.phpから最大のOIDが入っているはずのresが帰ってくるのでそれを代入
            console.log("res"+res.responseText);
            console.log("OID = " + OID);
            if (OID == <?= json_encode(translate('ques.php_351行目_OID抽出エラー（マウス）')) ?> || OID == "" || OID == <?= json_encode(translate('ques.php_351行目_OIDエラー')) ?> || OID == <?= json_encode(translate('ques.php_351行目_これは最初の問題です')) ?>) {
                OID = 1;
            } else {
                OID = parseInt(OID) + 1;//取って来たのか履歴データなので次の問題を出すためにインクリメント
            }
            QuesNum = parseInt(OID);
        }
        myCheck2(0);
        //===================
    }
    //ロードイベント終了========================================

    //問題の出題関数
    function setques() {
        //OID=出題順
        Fixmsg.innerHTML = <?= json_encode(translate('ques.php_364行目_情報')) ?>;
        myCheck(0);
        //問題固定var------------
        var $Load = "load";
        var $w = "w";
        var $params = 'param1=' + encodeURIComponent(OID)
                      + '&param2=' + encodeURIComponent($Load);
        console.log("$wの時:" + $params);
        new Ajax.Request(URL + 'dbsyori.php', //本番用
        {
            method: 'get',
            onSuccess: getOIDtoWID,
            onFailure: Error,
            parameters: $params
        });

        function Error(res) {
            alert(<?= json_encode(translate('ques.php_379行目_問題取得失敗')) ?>);
            window.close;
        }
        function getOIDtoWID(res) {
            if (res.responseText == "エラー" && OID != nEnd + 1) {
                alert(<?= json_encode(translate('ques.php_384行目_固定問題番号取得エラー')) ?>);
            } else {
                console.log("デバッグ" + res.responseText);
                WID = res.responseText - 0;
                console.log("WID = " + WID);
                $q = "q";
                var $params_for_attempt = 'param1=' + encodeURIComponent(WID)
                new Ajax.Request(URL + 'getattempt.php', //本番用
                    {
                        method: 'get',
                        onSuccess: getAttempt,
                        onFailure: function(req){getError(req,"getattempt.php")},
                        parameters: $params_for_attempt
                    });
                function getAttempt(res) {
                    attempt = res.responseText;
                    console.log("attempt = " + attempt);
                }
                var $params = 'param1=' + encodeURIComponent(WID)
                          + '&param2=' + encodeURIComponent($q);
                console.log("$qの時:" + $params);
                new Ajax.Request(URL + 'dbsyori.php', //本番用
                    {
                    method: 'get',
                    onSuccess: getResponse,
                    onFailure: function(req){getError(req,"dbsyori.php")},
                    parameters: $params
                });
                
                //関数開始-----------------------------------
                function getResponse(req) {
                    //console.log("attempt = " + attempt);
                    PorQ = req.responseText.charAt(req.responseText.length - 1); //ピリオド、または？を抜き取る
                    str1 = req.responseText.substr(0, 1);
                    str2 = req.responseText.substr(1);
                    Answer = str1.toUpperCase() + str2; //完全な答え
                    $q = "q1";
                    $params = 'param1=' + encodeURIComponent(WID)
                                  + '&param2=' + encodeURIComponent($q);
                    new Ajax.Request(URL + 'dbsyori.php', //本番用
                        {
                        method: 'get',
                        onSuccess: getStart,
                        onFailure: function(req){getError(req,"dbsyori.php")},
                        parameters: $params
                    });
                    
                    function getStart(req1) {
                        Mylabels = req1.responseText.split("|");
                        $d = "d";
                        $params = 'param1=' + encodeURIComponent(WID)
                                  + '&param2=' + encodeURIComponent($d);
                        new Ajax.Request(URL + 'dbsyori.php', //本番用
                            {
                            method: 'get',
                            onSuccess: getDivide,
                            onFailure: function(req){getError(req,"dbsyori.php")},
                            parameters: $params
                        });
                        function getDivide(req2) {
                        MylabelsD = req2.responseText.split("|");
                        $f = "f";
                        $params = 'param1=' + encodeURIComponent(WID)
                                  + '&param2=' + encodeURIComponent($f);
                        new Ajax.Request(URL + 'dbsyori.php', //本番用
                            {
                            method: 'get',
                            onSuccess: getFix,
                            onFailure: function(req){getError(req,"dbsyori.php")},
                            parameters: $params
                        });
                        function getFix(Fix) //固定情報の表示
                        {
                            msg.innerHTML = Fix.responseText;
                            if (Fix.responseText != "-1") {
                                

                                FixNum = Fix.responseText.split("#"); //♯区切り
                                for (i = 0; i <= FixNum.length - 1; i++) {
                                    FixNum[i] -= 0; //数値化
                                    FixLabels[i] = MylabelsD[FixNum[i]];
                                    FixNum[i] += 1;
                                    Fixmsg.innerHTML += "</br><font size='5' color='green'>" + FixLabels[i] + "</font>" + <?= json_encode(translate('ques.php_442行目_は')) ?> + "<font size='5' color='red'>" + FixNum[i] + "</font>" + <?= json_encode(translate('ques.php_442行目_番目にきます')) ?>;
                                    FixNum[i] -= 1;
                                }
                            } else {
                                FixNum = 0
                            }
                            LabelNum = Mylabels.length;
                            //--------------------------------
                            //body要素を取得
                            var body = document.getElementsByTagName("body")[0];
                            var el;
                            //------------------------------
                            for (i = 0; i <= LabelNum - 1; i++) {
                                //p要素を作成
                                var p = document.createElement("div");
                                var n = document.createElement("div"); //そのラベルが何番目にくるのかを表示するためのdiv要素
                                //テキストノードを作成
                                p.setAttribute("id", i);
                                n.setAttribute("id", -i); //一応何かのために(削除用)
                                YAHOO.util.Dom.setStyle(p, "position", "absolute");
                                YAHOO.util.Dom.setStyle(n, "position", "absolute");
                                if (i < 1) {
                                    YAHOO.util.Dom.setStyle(p, "left", DefaultX);
                                    YAHOO.util.Dom.setStyle(p, "top", DefaultY);
                                    var LL = YAHOO.util.Dom.getRegion(p);
                                    YAHOO.util.Dom.setStyle(n, "top", DefaultY - 15);
                                } else {
                                    YAHOO.util.Dom.setStyle(p, "left", el.right + 17);
                                    YAHOO.util.Dom.setStyle(p, "top", DefaultY);
                                    var LL = YAHOO.util.Dom.getRegion(p);
                                    YAHOO.util.Dom.setStyle(n, "top", DefaultY - 15);
                                }
                                YAHOO.util.Dom.setStyle(p, "width", "auto");
                                YAHOO.util.Dom.setStyle(n, "width", "auto");
                                YAHOO.util.Dom.setStyle(p, "font-family", "Arial");
                                YAHOO.util.Dom.setStyle(n, "font-size", "20px");
                                if (i == LabelNum - 1) {
                                    StartQues += Mylabels[i];
                                } else {
                                    StartQues += Mylabels[i] + "|";
                                }
                                dd[i] = new YAHOO.util.DD(p);
                                var str = document.createTextNode(Mylabels[i]);
                                //テキストノードをp要素に追加
                                p.appendChild(str);
                                MyNums[i] = i + 1;
                                var str2 = document.createTextNode(MyNums[i]);

                                //p要素をbody要素に追加
                                Mylabels[i] = p;
                                body.appendChild(Mylabels[i]);
                                //p要素をbody要素に追加
                                MyNums[i] = n;
                                body.appendChild(MyNums[i]);

                                var LL = YAHOO.util.Dom.getRegion(p);
                                YAHOO.util.Dom.setStyle(n, "left", LL.left + (LL.right - LL.left) / 2 - 2);

                                el = YAHOO.util.Dom.getRegion(p);
                                //イベントハンドラの追加
                                dd[i].onMouseDown = function (e) { MyLabels_MouseDown(this.getDragEl()) }
                                dd[i].onMouseUp = function (e) { MyLabels_MouseUp(this.getDragEl()) }
                                dd[i].onDrag = function (e) { MyLabels_MouseMove(this.getDragEl()) }
                                YAHOO.util.Event.addListener(Mylabels[i], 'mouseover', MyLabels_MouseEnter);
                                YAHOO.util.Event.addListener(Mylabels[i], 'mouseout', MyLabels_MouseLeave);

                                region = YAHOO.util.Dom.getRegion(Mylabels[i]);
                                Mylabels_left[i] = region.left;
                                if (i != Mylabels.length - 1) {
                                    BPen3.setFont("arial", "15px", Font.ITALIC_BOLD);
                                    BPen3.drawString("/", region.right + 7, 100);
                                    BPen3.paint();
                                }
                            }
                            //Mylabels配列のコピー。Mylabelは今後動かさないので。
                            Mylabels2 = Mylabels.concat();
                            //-------------------------------------
                            //日本文の取得
                            var $j = "j";
                            $params = 'param1=' + encodeURIComponent(WID)
                                            + '&param2=' + encodeURIComponent($j);
                            new Ajax.Request(URL + 'dbsyori.php',
                                    {
                                        method: 'get',
                                        onSuccess: getJapanese,
                                        onFailure: function(req){getError(req,"dbsyori.php")},
                                        parameters: $params
                                    });
                            function getJapanese(res) {
                                document.getElementById("RichTextBox1").innerHTML = res.responseText;
                                //-------------------------------------
                                //別解の取得(得点は10点の物）
                                var $s1 = "s1";
                                $params = 'param1=' + encodeURIComponent(WID)
                                                + '&param2=' + encodeURIComponent($s1);
                                new Ajax.Request(URL + 'dbsyori.php',
                                {
                                    method: 'get',
                                    onSuccess: getSentence1,
                                    onFailure: function(req){getError(req,"dbsyori.php")},
                                    parameters: $params
                                });
                                function getSentence1(res) {
                                    if (res.responseText != "") {
                                        str1 = res.responseText.substr(0, 1);
                                        str2 = res.responseText.substr(1);
                                        Answer1 = str1.toUpperCase() + str2; //先頭を大文字に変更
                                        //英文を取得
                                        var $s2 = "s2";
                                        $params = 'param1=' + encodeURIComponent(WID)
                                                          + '&param2=' + encodeURIComponent($s2);
                                        new Ajax.Request(URL + 'dbsyori.php',
                                        {
                                            method: 'get',
                                            onSuccess: getSentence2,
                                            onFailure: function(req){getError(req,"dbsyori.php")},
                                            parameters: $params
                                        });
                                        function getSentence2(res) {
                                            if (res.responseText != "") {//NULL以外だったら
                                                str1 = res.responseText.substr(0, 1);
                                                str2 = res.responseText.substr(1);
                                                Answer2 = str1.toUpperCase() + str2;
                                            } //ifres.responseText != ""ここまで------------------------------------
                                        } // getSentence2ここまで--------------------------------------------------------
                                    }
                                } // getSentence1ここまで---------------------------------------------------
                            } // getJapaneseここまで--------------------------------------------------------
                            Mouse_Flag = true;
                        } //Fix関数ここまで--------------------------------------------------------
                    }
                    }
                } /*getStart関数ここまで*/
                
                //--関数getresponseここまで---------------------------------------
            }
        }
        /*
        function getError(req) {
            alert("失敗b");
            window.close;
        }
            */
        //マウス取得スタート
        PreMouseTime = -1;

        //時刻を取得
        AnswerT = new DateFormat("yyyy-MM-dd HH:mm:ss");
        Answertime = AnswerT.format(new Date());
    }
    //問題の出題関数ここまで-------------------------------------------------------
    //範囲指定をするときのドラッグ開始処理------------------------------
    function Form1_MouseDown() {
        if (event.y <= 130)
        { d_flag = 0; }
        else if (event.y <= 215 && event.y > 130)
        { d_flag = 4; }
        else if (event.y <= 295 && event.y > 215)
        { d_flag = 1; }
        else if (event.y <= 375 && event.y > 295)
        { d_flag = 2; }
        else if (event.y > 375)
        { d_flag = 3; }
        if (Mouse_Flag == false) {
            return;
        }
        //マウスカーソルを十字に
        document.body.style.cursor = "crosshair";

        //グループ化されたラベルの初期化
        for (i = 0; i <= MyControls.length - 1; i++) {
            YAHOO.util.Dom.setStyle(MyControls[i], "color", "black");
        }
        MyControls = new Array();
        //開始点の取得
        sPos.x = event.x + cx;
        sPos.y = event.y + cy;
        ePos.x = event.x + cx;
        ePos.y = event.y + cy;
        //document.getElementById("msg").innerHTML = "Form1_MouseDown";
        MV = true;
    }
    //------------------------------------------------------------------
    //マウスアップ関数ここから(範囲選択を確定（ラベルをグループ化))---------------------------------------------------
    function Form1_MouseUp() {
        MV = false;
        if (Mouse_Flag == false || IsDragging == true) {
            return;
        }
        BPen.clear();
        //マウスカーソルを戻す
        document.body.style.cursor = "default";
        var g_array = new Array();
        if (d_flag == 0) { g_array = Mylabels.slice(0); }
        else if (d_flag == 1) { g_array = Mylabels_r1.slice(0); }
        else if (d_flag == 2) { g_array = Mylabels_r2.slice(0); }
        else if (d_flag == 3) { g_array = Mylabels_r3.slice(0); }
        else if (d_flag == 4) { g_array = Mylabels_ea.slice(0); }
        //選択範囲の中にラベルがあればグループ化する
        //青色への色変えも
        //左上,右上,左下,右下の４方向からのドラッグに対応------------------------------------------
        for (i = 0; i <= g_array.length; i++) {
            //一時退避・・・なくて良い
            MLi = YAHOO.util.Dom.getRegion(g_array[i]);
            if (sPos.x <= ePos.x && sPos.y <= ePos.y) {  //左上
                if ((sPos.x < MLi.right && sPos.y < MLi.bottom) && (ePos.x > MLi.left && ePos.y > MLi.top)) {
                    MyControls.push(g_array[i]);
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "blue");
                }
            }
            else if (sPos.x <= ePos.x && sPos.y >= ePos.y) {//左下
                if ((sPos.x < MLi.right && sPos.y > MLi.top) && (ePos.x > MLi.left && ePos.y < MLi.bottom)) {
                    MyControls.push(g_array[i]);
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "blue");
                }
            }
            else if (sPos.x > ePos.x && sPos.y < ePos.y) {//右上
                if ((sPos.x > MLi.left && sPos.y < MLi.bottom) && (ePos.x < MLi.right && ePos.y > MLi.top)) {
                    MyControls.push(g_array[i]);
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "blue");
                }
            }
            else if (sPos.x > ePos.x && sPos.y > ePos.y) {//右下
                if ((sPos.x > MLi.left && sPos.y > MLi.top) && (ePos.x < MLi.right && ePos.y < MLi.bottom)) {
                    MyControls.push(g_array[i]);
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "blue");
                }
            }
        } //----------------------------------------------------------------------------------------
    }
    //-----------------------------------------------------------
    //ドラッグ中に範囲指定の線を描画など
    function Form1_MouseMove(sender) {
        //ドラッグ中
        if (MV == true) {
            draw();
            ePos.x = event.x + cx;
            ePos.y = event.y + cy;
        }
        //--------------------別のマウスムーブの取り込み--------------------------------------
        var P = new Point(0, 0);
        if (Mouse_Flag == true) {
            //マウスの位置座標を取得
            P.x = event.x;
            P.y = event.y;
            var a;
            if (PreMouseTime != -1) { //データを間引く
                //経過時間取得-----
                myStop = new Date();
                mTime = myStop.getTime() - myStart.getTime();
                a = mTime - PreMouseTime;
                if (a < 100) {
                    return;
                }
            }

            //マウスデータの取得
            myStop = new Date();
            mTime = myStop.getTime() - myStart.getTime();
            $Mouse_Data["WID"] = WID;
            $Mouse_Data["Time"] = mTime;
            if (IsDragging == true) {
                var hLabel = sender;
                var hl = YAHOO.util.Dom.getRegion(DragL);
                $Mouse_Data["X"] = hl.left;
                $Mouse_Data["Y"] = hl.top;
            } else {
                $Mouse_Data["X"] = P.x;
                $Mouse_Data["Y"] = P.y;
            }
            $Mouse_Data["DragDrop"] = 0;
            $Mouse_Data["DropPos"] = -1;
            $Mouse_Data["hlabel"] = "";
            $Mouse_Data["Label"] = "";
            Mouse_Num += 1;
            PreMouseTime = $Mouse_Data["Time"];

            //encodeURI = 変換してるだけだぴょん
            //paramっていうのに各変数を入れてる！(tmpfileで&で区切って送ってる)
            var $params = 'param1=' + encodeURIComponent($Mouse_Data["WID"])
                      + '&param2=' + encodeURIComponent($Mouse_Data["Time"])
                      + '&param3=' + encodeURIComponent($Mouse_Data["X"])
                      + '&param4=' + encodeURIComponent($Mouse_Data["Y"])
                      + '&param5=' + encodeURIComponent($Mouse_Data["DragDrop"])
                      + '&param6=' + encodeURIComponent($Mouse_Data["DropPos"])
                      + '&param7=' + encodeURIComponent($Mouse_Data["hlabel"])
                      + '&param8=' + encodeURIComponent($Mouse_Data["Label"]);
            new Ajax.Request(URL + 'tmpfile.php',
            {
                method: 'get',
                onSuccess: getA,
                onFailure: getE,
                parameters: $params
            });
            //▲マウスデータの取得
            //ドラッグ開始地点の保存
            function getA(req) {
                document.getElementById("msg").innerHTML = req.responseText;
                MytoFo = false;
            }
            function getE(req) {
                alert("失敗c");
            }
        }
        //--------------------別のマウスムーブここまで----------------------------------------------------------------
    }
    function draw() {
        BPen.clear();

        //レジスタ3をドラッグ中
        if (d_flag == 3) {
            //もし、範囲の線を超えてしまっていたら？
            if (ePos.y <= 375) { ePos.y = 375; }
            //もし、下の範囲を超えてしまっていたら？（バグ対策だぴょん） マウスアップの時になんかバグが起きてるかも！
            else if (ePos.y >= 480) { ePos.y = 480; }
        } else if (d_flag == 0) {//問題提示欄をドラッグ中
            //もし、範囲の線を超えてしまっていたら？
            if (ePos.y >= 130) { ePos.y = 130; }
        } else { //その他
            //最終解答欄だった場合
            if (d_flag == 4) {
                //上限を超えていた場合
                if (ePos.y <= 130) { ePos.y = 130; }
                //下限を超えていた場合
                else if (ePos.y >= 215) { ePos.y = 215; }
            }
            //レジスタ1だった場合
            if (d_flag == 1) {
                //上限を超えていた場合
                if (ePos.y <= 215) { ePos.y = 215; }
                //下限を超えていた場合
                else if (ePos.y >= 295) { ePos.y = 295; }
            }
            //レジスタ2だった場合
            if (d_flag == 2) {
                //上限を超えていた場合
                if (ePos.y <= 295) { ePos.y = 295; }
                //下限を超えていた場合
                else if (ePos.y >= 375) { ePos.y = 375; }
            }
        }
        //消える描画でドラッグ中の四角形を描く
        //左上、右上、左下、右下、の４方向からのドラッグに対応
        if (sPos.x <= ePos.x && sPos.y <= ePos.y) {  //左上
            BPen.drawRect(sPos.x, sPos.y, ePos.x - sPos.x, ePos.y - sPos.y)
            loc = 0;
        } else if (sPos.x <= ePos.x && sPos.y >= ePos.y) {//左下
            BPen.drawRect(sPos.x, ePos.y, ePos.x - sPos.x, sPos.y - ePos.y)
            loc = 1;
        } else if (sPos.x > ePos.x && sPos.y < ePos.y) {//右上
            BPen.drawRect(ePos.x, sPos.y, sPos.x - ePos.x, ePos.y - sPos.y)
            loc = 2;
        } else if (sPos.x > ePos.x && sPos.y > ePos.y) {//右下
            BPen.drawRect(ePos.x, ePos.y, sPos.x - ePos.x, sPos.y - ePos.y)
            loc = 3;
        }
        BPen.paint();
        //もし選択範囲にラベルがあれば赤色に色づけ
        //選択範囲が解除されたら黒色に戻る処理も実装
        //どの欄を対象にしているか、フラグにより判別
        var g_array = new Array();
        if (d_flag == 0) { g_array = Mylabels.slice(0); }
        else if (d_flag == 1) { g_array = Mylabels_r1.slice(0); }
        else if (d_flag == 2) { g_array = Mylabels_r2.slice(0); }
        else if (d_flag == 3) { g_array = Mylabels_r3.slice(0); }
        else if (d_flag == 4) { g_array = Mylabels_ea.slice(0); }

        //色付け。このへんはあまりいじってません。
        for (i = 0; i <= g_array.length - 1; i++) {
            //一時退避
            //退避ラベルならスキップ・・・必要なし
            //範囲選択をすべて抱合⇒一部抱合に変更
            MLi = YAHOO.util.Dom.getRegion(g_array[i]);
            if (sPos.x <= ePos.x && sPos.y <= ePos.y) { //左上---------------------------
                if ((sPos.x < MLi.right && sPos.y < MLi.bottom) && (ePos.x > MLi.left && ePos.y > MLi.top)) {
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "red");
                } else {
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "black");
                }
            } //左上ここまで--------------------------------------------------
            else if (sPos.x <= ePos.x && sPos.y >= ePos.y) {//左下
                if ((sPos.x < MLi.right && sPos.y > MLi.top) && (ePos.x > MLi.left && ePos.y < MLi.bottom)) {
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "red");
                } else {
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "black");
                }
            } else if (sPos.x > ePos.x && sPos.y < ePos.y) {//右上
                if ((sPos.x > MLi.left && sPos.y < MLi.bottom) && (ePos.x < MLi.right && ePos.y > MLi.top)) {
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "red");
                } else {
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "black");
                }
            } else if (sPos.x > ePos.x && sPos.y > ePos.y) {//右下
                if ((sPos.x > MLi.left && sPos.y > MLi.top) && (ePos.x < MLi.right && ePos.y < MLi.bottom)) {
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "red");
                } else {
                    YAHOO.util.Dom.setStyle(g_array[i], "color", "black");
                }
            }
        } //forここまで-----------------------------------------
    }

    //------------------------------------------------------------------------------- 
    //ソート関数ここから----------------------------------------------------------
    function MyLabelSort(sender, ex, ey) {
        var mylabelarray3 = new Array();
        var X_p = 0;
        var Y_p = 0;
        if (array_flag2 == 0) {
            mylabelarray3 = Mylabels.slice(0);
            X_p = DefaultX;
            Y_p = DefaultY;
        } else if (array_flag2 == 1) {
            mylabelarray3 = Mylabels_r1.slice(0);
            X_p = DefaultX_r1;
            Y_p = DefaultY_r1;
        } else if (array_flag2 == 2) {
            mylabelarray3 = Mylabels_r2.slice(0);
            X_p = DefaultX_r2;
            Y_p = DefaultY_r2;
        } else if (array_flag2 == 3) {
            mylabelarray3 = Mylabels_r3.slice(0);
            X_p = DefaultX_r3;
            Y_p = DefaultY_r3;
        } else if (array_flag2 == 4) {
            mylabelarray3 = Mylabels_ea.slice(0);
            X_p = DefaultX_ea;
            Y_p = DefaultY_ea;
        }
        var i;
        var j;
        var k;

        var hLabel; // = document.createElement("div");
        hLabel = sender;
        var aNum = new Array(); //問題文のラベル番号を記憶
        var aCount = 0;
        for (i = 0; i <= mylabelarray3.length - 1; i++) {
            aNum.push(i);
            aCount++;
        }
        var iLabel = new Array(); //スワップ用配列
        var item = MyControls.indexOf(hLabel);
        //グループ化されているかの判定
        if (MyControls.length > 0) {
            //問題提示欄にドロップされた場合のみ
            if (array_flag2 == 0) {
                for (j = 0; j < MyControls.length; j++) {
                    for (i = 0; i < Mylabels2.length; i++) {
                        if (Mylabels2[i].id == MyControls[j].id) { break; }
                    }
                    mylabelarray3.splice(i, 1, MyControls[j]);
                    Mylabels = mylabelarray3.slice(0);
                    YAHOO.util.Dom.setX(Mylabels[i], Mylabels_left[i]);
                    YAHOO.util.Dom.setY(Mylabels[i], 100);
                }
                return mylabelarray3;
            }
            var X1 = YAHOO.util.Dom.getRegion(MyControls[0]);
            //どこに挿入するか調べる。
            for (m = 0; m <= mylabelarray3.length; m++) {
                if (m == mylabelarray3.length) { break; }
                var X2 = YAHOO.util.Dom.getRegion(mylabelarray3[m]);
                if (X1.left <= X2.left) {
                    break;
                }
            }

            //配列に挿入
            for (k = 0; k < MyControls.length; k++) {
                mylabelarray3.splice(m + k, 0, MyControls[k])
            }
            //もとの問題文の位置のラベルを整形
            for (i = 0; i < mylabelarray3.length; i++) {
                if (i == 0) {
                    YAHOO.util.Dom.setX(mylabelarray3[0], X_p);
                    YAHOO.util.Dom.setY(mylabelarray3[0], Y_p);
                } else {
                    var X1 = YAHOO.util.Dom.getRegion(mylabelarray3[i - 1]);
                    YAHOO.util.Dom.setX(mylabelarray3[i], X1.right + 17);
                    YAHOO.util.Dom.setY(mylabelarray3[i], Y_p);
                }
            }
        } else {
            //問題提示欄にドロップされた場合のみ
            if (array_flag2 == 0) {
                for (i = 0; i < Mylabels2.length; i++) {
                    if (Mylabels2[i].id == hLabel.id) { break; }
                }
                mylabelarray3.splice(i, 1, hLabel);
                Mylabels = mylabelarray3.slice(0);
                YAHOO.util.Dom.setX(Mylabels[i], Mylabels_left[i]);
                YAHOO.util.Dom.setY(Mylabels[i], 100);
                return mylabelarray3;
            }
            var X1 = YAHOO.util.Dom.getRegion(hLabel);
            //どこに挿入するか調べる。グループ化も一緒にやってしまえたらやってしまおう。
            for (j = 0; j <= mylabelarray3.length; j++) {
                if (j == mylabelarray3.length) { break; }
                var X2 = YAHOO.util.Dom.getRegion(mylabelarray3[j]);
                if (X1.left <= X2.left) {
                    break;
                }
            }
            //配列に挿入
            mylabelarray3.splice(j, 0, hLabel)
            //もとの問題文の位置のラベルを整形
            for (i = 0; i < mylabelarray3.length; i++) {
                if (i == 0) {
                    YAHOO.util.Dom.setX(mylabelarray3[0], X_p);
                    YAHOO.util.Dom.setY(mylabelarray3[0], Y_p);
                } else {
                    var X1 = YAHOO.util.Dom.getRegion(mylabelarray3[i - 1]);
                    YAHOO.util.Dom.setX(mylabelarray3[i], X1.right + 17);
                    YAHOO.util.Dom.setY(mylabelarray3[i], Y_p);
                }
            }
        }
        if (array_flag2 == 1) { Mylabels_r1 = mylabelarray3.slice(0); }
        else if (array_flag2 == 2) { Mylabels_r2 = mylabelarray3.slice(0); }
        else if (array_flag2 == 3) { Mylabels_r3 = mylabelarray3.slice(0); }
        else if (array_flag2 == 4) { Mylabels_ea = mylabelarray3.slice(0); }
        return mylabelarray3;

    }
    //マウスが上に来たらラベルの見た目を変えたり、グループ化やレジスタラベルの対応---------------
    function MyLabels_MouseEnter(e) {
        if (MV == true || IsDragging == true) {
            return;
        }
        //レジスタ内のグループ化・・・なくてよし
        var index = MyControls.indexOf(this);
        //グループ化されたラベルの初期化
        if (index == -1) {
            for (i = 0; i <= MyControls.length - 1; i++) {
                //YAHOO.util.Dom.setStyle(MyControls[i], "text-decoration", "none");
                YAHOO.util.Dom.setStyle(MyControls[i], "background-color", "transparent");
            }
            MyControls = new Array();
        } else {
            for (i = 0; i <= MyControls.length - 1; i++) {
                //YAHOO.util.Dom.setStyle(MyControls[i], "text-decoration", "underline overline");
                YAHOO.util.Dom.setStyle(MyControls[i], "background-color", "yellow");
            }
        }
        //YAHOO.util.Dom.setStyle(this, "text-decoration", "underline overline");
        YAHOO.util.Dom.setStyle(this, "background-color", "yellow");

    }
    function MyLabels_MouseLeave() {
        if (MV == true || IsDragging == true) {
            return;
        }
        for (i = 0; i <= MyControls.length - 1; i++) {
            //YAHOO.util.Dom.setStyle(MyControls[i], "text-decoration", "none");
            YAHOO.util.Dom.setStyle(MyControls[i], "background-color", "transparent");
        }
        //YAHOO.util.Dom.setStyle(this, "text-decoration", "none");
        YAHOO.util.Dom.setStyle(this, "background-color", "transparent");
    }

    //★★ラベルクリック時。引っこ抜くときの作業とかしてるよ
    function MyLabels_MouseDown(sender) {
        myStop = new Date();
        var mylabelarray = new Array();

        //どこのラベル郡にsenderが入ってるのか判定。ついでにどの位置に入っていたのかも。
        //idでやってます。頭悪いです。ごめんなさい。
        //もっといい簡潔な方法があったら書き換えてください。
        //単語何番目にあるんです？？グループ化してないとき
        var index_sender = 0;
        //単語何番目にあるんです？？グループ化してるとき
        var index_sender_g = 0;
        //もうグループ化してあるところは分かっているはず(d_flagで判定してるはず)なので
        //グループ化の先頭は何番目に入ってるのか？調べる
        //↑のコメントは多分出海さんなので文句は出海さんへお願いします
        //問題提示欄
        for (i = 0; i < Mylabels.length; i++) {
            if (Mylabels[i] == undefined) { continue; }
            if (Mylabels[i].id == sender.id) { array_flag = 0; index_sender = i; }
        }
        //レジスタ1
        for (i = 0; i < Mylabels_r1.length; i++) {
            if (Mylabels_r1[i].id == sender.id) { array_flag = 1; index_sender = i; }
        }
        //レジスタ2
        for (i = 0; i < Mylabels_r2.length; i++) {
            if (Mylabels_r2[i].id == sender.id) { array_flag = 2; index_sender = i; }
        }
        //レジスタ3
        for (i = 0; i < Mylabels_r3.length; i++) {
            if (Mylabels_r3[i].id == sender.id) { array_flag = 3; index_sender = i; }
        }
        //最終解答欄
        for (i = 0; i < Mylabels_ea.length; i++) {
            if (Mylabels_ea[i].id == sender.id) { array_flag = 4; index_sender = i; }
        }
        //もしグループ化されているなら
        if (MyControls.length > 0) {
            var g_array = new Array();
            if (array_flag == 1) { g_array = Mylabels_r1.slice(0); }
            else if (array_flag == 2) { g_array = Mylabels_r2.slice(0); }
            else if (array_flag == 3) { g_array = Mylabels_r3.slice(0); }
            else if (array_flag == 4) { g_array = Mylabels_ea.slice(0); }

            //グループ化の先頭が何番目に入っているか？
            for (i = 0; i < g_array.length; i++) {
                if (g_array[i] == undefined) { continue; }
                if (g_array[i].id == MyControls[0].id) { index_sender_g = i; }
            }
        }
        if (array_flag == 0) { mylabelarray = Mylabels.slice(0); }
        else if (array_flag == 1) { mylabelarray = Mylabels_r1.slice(0); }
        else if (array_flag == 2) { mylabelarray = Mylabels_r2.slice(0); }
        else if (array_flag == 3) { mylabelarray = Mylabels_r3.slice(0); }
        else if (array_flag == 4) { mylabelarray = Mylabels_ea.slice(0); }
        //グループ化されたラベルの初期化とか、hLabelに退避とか
        Mld = true;
        var hLabel = sender;
        DragL = sender; //IEのバグ対応
        IsDragging = true;
        //一時退避・・・レジスタなくすからよい？
        var DPos = 0;
        DLabel = "";
        //グループ化ラベルを#で連結する グループラベル
        if (MyControls.length != 0) {
            for (i = 0; i <= MyControls.length - 1; i++) {
                if (i == MyControls.length - 1) {
                    DLabel = DLabel + MyControls[i].id;
                } else {
                    DLabel = DLabel + MyControls[i].id + "#";
                }
            }
        } else {
            DLabel = DLabel + hLabel.id;
        }

        //Mylabelsで引っこ抜きがあったとき(array_flag==0だったとき)は、問題を詰める作業は行わない。
        if (array_flag == 0) {
            delete mylabelarray[index_sender];
        } else {
            //グループ化の場合
            if (MyControls.length > 0) {
                mylabelarray.splice(index_sender_g, MyControls.length);
            } else {
                mylabelarray.splice(index_sender, 1);
            }
            //各フラグに合わせて、デフォルトのYの値を変える。Xはついで。
            //array_flagは、ラベルのドラッグがどこで開始したかを示している。(問題提示欄か、レジスタか..？)
            //それによってデフォルトのY座標を変化させる。(ここでは、元あったラベルの並べ替えを行うので。)
            //ここで示すX,Y座標は、ドラッグ中のラベルがあったラベル群のX,Yだよ。
            var X_p = 0;
            var Y_p = 0;
            var DestX = 0;
            var DestY = 0;
            if (array_flag == 1) {
                X_p = DefaultX_r1;
                Y_p = DefaultY_r1;
            } else if (array_flag == 2) {
                X_p = DefaultX_r2;
                Y_p = DefaultY_r2;
            } else if (array_flag == 3) {
                X_p = DefaultX_r3;
                Y_p = DefaultY_r3;
            } else if (array_flag == 4) {
                X_p = DefaultX_ea;
                Y_p = DefaultY_ea;
            }
            //相対位置の計算
            var hl = YAHOO.util.Dom.getRegion(hLabel);
            DestX = hl.left + event.x - DiffPoint.x;
            DestY = hl.top + event.y - DiffPoint.y;

            //元の位置にあるラベルの位置を決定
            for (i = 0; i <= mylabelarray.length; i++) {
                if (i == 0) {
                    YAHOO.util.Dom.setX(mylabelarray[i], X_p);
                    YAHOO.util.Dom.setY(mylabelarray[i], Y_p);
                } else {
                    var al = YAHOO.util.Dom.getRegion(mylabelarray[i - 1]);
                    YAHOO.util.Dom.setX(mylabelarray[i], al.right + 17); //解像度がちがうため？
                    YAHOO.util.Dom.setY(mylabelarray[i], Y_p);
                }
            }
        }

        //経過時間取得-----
        mTime = myStop.getTime() - myStart.getTime();
        //----------------
        var X = YAHOO.util.Dom.getRegion(hLabel);
        //▼マウスデータの取得
        $Mouse_Data["WID"] = WID;
        $Mouse_Data["Time"] = mTime;
        $Mouse_Data["X"] = X.left;
        $Mouse_Data["Y"] = X.top;
        $Mouse_Data["DragDrop"] = 2;
        $Mouse_Data["DropPos"] = DPos;
        $Mouse_Data["hlabel"] = hLabel.id;
        $Mouse_Data["Label"] = DLabel;
        Mouse_Num += 1;

        var $params = 'param1=' + encodeURIComponent($Mouse_Data["WID"])
                      + '&param2=' + encodeURIComponent($Mouse_Data["Time"])
                      + '&param3=' + encodeURIComponent($Mouse_Data["X"])
                      + '&param4=' + encodeURIComponent($Mouse_Data["Y"])
                      + '&param5=' + encodeURIComponent($Mouse_Data["DragDrop"])
                      + '&param6=' + encodeURIComponent($Mouse_Data["DropPos"])
                      + '&param7=' + encodeURIComponent($Mouse_Data["hlabel"])
                      + '&param8=' + encodeURIComponent($Mouse_Data["Label"]);
        new Ajax.Request(URL + 'tmpfile.php',
        {
            method: 'get',
            onSuccess: getA,
            onFailure: getE,
            parameters: $params
        });
        //▲マウスデータの取得
        //ドラッグ開始地点の保存
        function getA(req) {
            document.getElementById("msg").innerHTML = req.responseText;
            Mld = false;
        }
        function getE(req) {
            alert("失敗d");
        }

        DiffPoint = new Point(event.x, event.y);
        var obj = document.getElementById("TermText");
        if (obj.style.display == 'block') {
            var lblText = "";
            var TextNum;
            if (hLabel.id == LabelNum - 1) {
                lblText = hLabel.innerHTML;
                TextNum = document.Questions.TermText.value.indexOf(lblText.substring(0, lblText.length - 1));
                if (TextNum == -1) {
                    lblText = lblText.substring(0, lblText.length - 1) + ".";
                } else {
                    lblText = "";
                }
            } else {
                TextNum = document.Questions.TermText.value.indexOf(hLabel.innerHTML);
                if (TextNum == -1) {
                    lblText = lblText + hLabel.innerHTML + ".";
                }
            }
            document.Questions.TermText.value += lblText;
            TermTextChange();
        }

        if (array_flag == 0) { Mylabels = mylabelarray.slice(0); }
        else if (array_flag == 1) { Mylabels_r1 = mylabelarray.slice(0); }
        else if (array_flag == 2) { Mylabels_r2 = mylabelarray.slice(0); }
        else if (array_flag == 3) { Mylabels_r3 = mylabelarray.slice(0); }
        else if (array_flag == 4) { Mylabels_ea = mylabelarray.slice(0); }
    }

    //★★ラベルを離した時の作業。問題文の形を変えたりいろいろ
    function MyLabels_MouseUp(sender) {
        //枠の色リセット
        document.getElementById("question").style.borderColor = "black";
        document.getElementById("register1").style.borderColor = "black";
        document.getElementById("register2").style.borderColor = "black";
        document.getElementById("register3").style.borderColor = "black";
        document.getElementById("answer").style.borderColor = "black";
        var mylabelarray2 = new Array();
        //イベントが起こったy座標の判定。それによって単語をどこに落とすか決める。
        if (event.y <= 150) { array_flag2 = 0; }
        else if (event.y <= 240 && event.y > 150) { array_flag2 = 4; }
        else if (event.y <= 320 && event.y > 240) { array_flag2 = 1; }
        else if (event.y <= 400 && event.y > 320) { array_flag2 = 2; }
        else if (event.y > 400) { array_flag2 = 3; }

        if (array_flag2 == 0) { mylabelarray2 = Mylabels.slice(0); }
        else if (array_flag2 == 1) { mylabelarray2 = Mylabels_r1.slice(0); }
        else if (array_flag2 == 2) { mylabelarray2 = Mylabels_r2.slice(0); }
        else if (array_flag2 == 3) { mylabelarray2 = Mylabels_r3.slice(0); }
        else if (array_flag2 == 4) { mylabelarray2 = Mylabels_ea.slice(0); }
        if (IsDragging != true) {
            return;
        }
        var hLabel = sender;

        //グループ化関係の処理
        for (i = 0; i <= MyControls.length - 1; i++) {
            //YAHOO.util.Dom.setStyle(MyControls[i], "text-decoration", "none");
            YAHOO.util.Dom.setStyle(MyControls[i], "background-color", "transparent");
        }
        //YAHOO.util.Dom.setStyle(hLabel, "text-decoration", "none");
        YAHOO.util.Dom.setStyle(hLabel, "background-color", "transparent");
        draw3();

        var Dpos = 0;
        var P = new Point(0, 0);
        var hl = YAHOO.util.Dom.getRegion(hLabel);
        P.x = hl.left;
        P.y = hl.top;
        mylabelarray2 = MyLabelSort(sender, event.x, event.y);

        DPos = 0;
        IsDragging = false;

        //▼マウスデータの取得
        myStop = new Date();
        mTime = myStop.getTime() - myStart.getTime();
        $Mouse_Data["WID"] = WID;
        $Mouse_Data["Time"] = mTime;
        $Mouse_Data["X"] = P.x;
        $Mouse_Data["Y"] = P.y;
        $Mouse_Data["DragDrop"] = 1;
        $Mouse_Data["DropPos"] = DPos;
        $Mouse_Data["hlabel"] = "";
        $Mouse_Data["Label"] = "";
        Mouse_Num += 1;

        var $params = 'param1=' + encodeURIComponent($Mouse_Data["WID"])
                      + '&param2=' + encodeURIComponent($Mouse_Data["Time"])
                      + '&param3=' + encodeURIComponent($Mouse_Data["X"])
                      + '&param4=' + encodeURIComponent($Mouse_Data["Y"])
                      + '&param5=' + encodeURIComponent($Mouse_Data["DragDrop"])
                      + '&param6=' + encodeURIComponent($Mouse_Data["DropPos"])
                      + '&param7=' + encodeURIComponent($Mouse_Data["hlabel"])
                      + '&param8=' + encodeURIComponent($Mouse_Data["Label"]);
        new Ajax.Request(URL + 'tmpfile.php',
        {
            method: 'get',
            onSuccess: getA,
            onFailure: getE,
            parameters: $params
        });
        //▲マウスデータの取得
        //ドラッグ開始地点の保存
        function getA(req) {
            document.getElementById("msg").innerHTML = req.responseText;
        }
        function getE(req) {
            alert("失敗f");
        }

        if (array_flag2 == 0) { Mylabels = mylabelarray2.slice(0); }
        else if (array_flag2 == 1) { Mylabels_r1 = mylabelarray2.slice(0); }
        else if (array_flag2 == 2) { Mylabels_r2 = mylabelarray2.slice(0); }
        else if (array_flag2 == 3) { Mylabels_r3 = mylabelarray2.slice(0); }
        else if (array_flag2 == 4) { Mylabels_ea = mylabelarray2.slice(0); }

    }
    //★★マウスでラベルをドラッグ中。動かしてるときだからここで挿入線をアレしたりコレしたり
    function MyLabels_MouseMove(sender) {
        if (IsDragging != true) {
            return;
        }
        var hLabel = sender;

        //グループ化ラベルを動かすときの処理。何故動いているか不明。
        var GroupMem = 0;
        hl1 = YAHOO.util.Dom.getRegion(hLabel);
        for (i = 0; i <= MyControls.length - 1; i++) {
            var mcl = YAHOO.util.Dom.getRegion(MyControls[i]);
            if (hl1.left == mcl.left && hl1.top == mcl.top) {
                GroupMem = i //今どのラベルを動かしてるかを記憶（グループ化ラベル）
                break;
            }
        }
        //グループ化ラベルの位置を決定
        for (j = 0; j <= MyControls.length - 1; j++) {
            //ドラッグラベルの左側の位置を決定(hLabelの左側をはじめに決定、それ以降は減算により位置を決定していく)
            if (j < GroupMem) {
                var mcl1 = YAHOO.util.Dom.getRegion(MyControls[GroupMem - 1]);
                YAHOO.util.Dom.setX(MyControls[GroupMem - 1], hl1.left - (mcl1.right - mcl1.left) - 10); //解像度
                YAHOO.util.Dom.setY(MyControls[GroupMem - 1], hl1.top);
                for (k = GroupMem - 1; k >= 0; k--) {
                    var mcl2 = YAHOO.util.Dom.getRegion(MyControls[k + 1]);
                    var mcl3 = YAHOO.util.Dom.getRegion(MyControls[k]);
                    YAHOO.util.Dom.setX(MyControls[k], mcl2.left - (mcl3.right - mcl3.left) - 10); //解像度の影響本来は10
                    YAHOO.util.Dom.setY(MyControls[k], mcl2.top);
                }
                j = GroupMem;
            } else if (j == GroupMem) {
                YAHOO.util.Dom.setX(MyControls[j], hl1.left);
                YAHOO.util.Dom.setY(MyControls[j], hl1.top);
            } else if (j > GroupMem) {
                //ドラッグラベルの右側の位置を決定
                var mclj = YAHOO.util.Dom.getRegion(MyControls[j - 1]);
                YAHOO.util.Dom.setX(MyControls[j], mclj.right + 10); //
                YAHOO.util.Dom.setY(MyControls[j], mclj.top);
            }
        }

        var line_flag = -1;
        var line_array = new Array();
        var line_x = 0;
        var line_y = 0;
        var line_y2 = 0;
        var lstart_x = 0;
        var lstart_y = 0;
        //枠の色リセット
        document.getElementById("question").style.borderColor = "black";
        document.getElementById("register1").style.borderColor = "black";
        document.getElementById("register2").style.borderColor = "black";
        document.getElementById("register3").style.borderColor = "black";
        document.getElementById("answer").style.borderColor = "black";
        //挿入線関係。まずy座標でどこに挿入線を引くか判定
        if (event.y <= 150) { line_flag = 0; }
        else if (event.y <= 240 && event.y > 150) { line_flag = 4; }
        else if (event.y <= 320 && event.y > 240) { line_flag = 1; }
        else if (event.y <= 400 && event.y > 320) { line_flag = 2; }
        else if (event.y > 400) { line_flag = 3; }
        if (line_flag == 0) {
            line_array = Mylabels.slice(0);
            lstart_x = DefaultX;
            lstart_y = DefaultY;
            document.getElementById("question").style.borderColor = "red";
        } else if (line_flag == 1) {
            line_array = Mylabels_r1.slice(0);
            lstart_x = DefaultX_r1;
            lstart_y = DefaultY_r1;
            document.getElementById("register1").style.borderColor = "red";
        } else if (line_flag == 2) {
            line_array = Mylabels_r2.slice(0);
            lstart_x = DefaultX_r2;
            lstart_y = DefaultY_r2;
            document.getElementById("register2").style.borderColor = "red";
        } else if (line_flag == 3) {
            line_array = Mylabels_r3.slice(0);
            lstart_x = DefaultX_r3;
            lstart_y = DefaultY_r3;
            document.getElementById("register3").style.borderColor = "red";
        } else if (line_flag == 4) {
            line_array = Mylabels_ea.slice(0);
            lstart_x = DefaultX_ea;
            lstart_y = DefaultY_ea;
            document.getElementById("answer").style.borderColor = "red";
        }
        if (line_array.length == 0) {
            var line_x = lstart_x;
            var line_y = lstart_y;
            var line_y2 = lstart_y + 18;
        } else {
            for (i = 0; i < line_array.length; i++) {
                if (MyControls.length > 0) {
                    var send = YAHOO.util.Dom.getRegion(MyControls[0]);
                } else {
                    var send = YAHOO.util.Dom.getRegion(sender);
                }
                var ali = YAHOO.util.Dom.getRegion(line_array[i]);
                var ali1 = YAHOO.util.Dom.getRegion(line_array[i + 1]);
                if (i == 0 && send.left < ali.left) {
                    //もし左端のラベルの左側に挿入しようとするなら
                    //左端のラベルから挿入位置を計算して表示
                    line_x = ali.left - 8;
                    line_y = ali.top;
                    line_y2 = line_y + (ali.bottom - ali.top);
                } else if (i == line_array.length - 1 && send.left >= ali.left) {
                    //もし右端に挿入しようとするなら
                    //右端のラベルから挿入位置を計算して表示
                    line_x = ali.right + 8;
                    line_y = ali.top;
                    line_y2 = line_y + (ali.bottom - ali.top);
                } else if (send.left >= ali.left && send.left < ali1.left) {
                    //ラベルに挟まれた位置に挿入するなら
                    //右のラベルから挿入位置を計算して表示
                    line_x = ali1.left - 8;
                    line_y = ali1.top;
                    line_y2 = line_y + (ali1.bottom - ali1.top);
                }
            }
        }
        //問題提示欄には表示させない
        if (line_flag != 0) {
            draw3();
            draw2(line_x, line_y, line_y2);
        } else { draw3(); }

        MytoFo = true;
        Form1_MouseMove(sender);
    }
    //挿入線の描画
    function draw2(x, y1, y2) {
        BPen2.drawLine(x + cx, y1 + cy, x + cx, y2 + cy);
        BPen2.paint();
        checkl = x;
    }
    function draw3() {
        BPen2.clear();
    }

    //○○終了ボタン主に一時ファイルの書き込み処理
    function LineQuestioneForm_Closing() {
        //▲マウスデータの取得
        alert(<?= json_encode(translate('ques.php_919行目_お疲れ様です')) ?>);
        new Ajax.Request(URL + 'ewrite.php',
        {
            method: 'get',
            onSuccess: getA,
            onFailure: getE
        });
        //▲マウスデータの取得
        //ドラッグ開始地点の保存
        function getA(req) {
            mybutton = 0;
            console.log(req.responseText);
            alert(req.responseText);
            window.close();
        }
        function getE(req) {
            alert(<?= json_encode(translate('ques.php_929行目_書き込み失敗')) ?>);
        }
    }

    //○○決定ボタン
    function Button1_Click() {
        //終了条件チェック
        if (Mylabels_ea.length != Mylabels2.length) {
            alert(<?= json_encode(translate('ques.php_778行目_まだ並べ替えが終了していません')) ?>);
            return;
        }
        //固定ラベルチェック
        for (i = 0; i < FixNum.length; i++) {
            var fixcheck = 0;
            fixcheck = Mylabels_ea[FixNum[i]].innerHTML;
            if (FixLabels[i] != fixcheck) {
                var fixnum2 = FixNum[i] + 1
                var confirm_msg = <?= json_encode(translate('ques.php_783行目_位置固定単語である')) ?> + FixLabels[i] + <?= json_encode(translate('ques.php_783行目_が')) ?> + fixnum2 + <?= json_encode(translate('ques.php_783行目_番目に来ていませんがよろしいですか')) ?>;
                var fix_a = confirm(confirm_msg);
                if (fix_a == true) {
                    continue;
                }
                else{
                    return;
                }
            }
        }

        BPen3.clear();
        YAHOO.util.Dom.setStyle("Button1", "display", "none");
        document.getElementById("Button1").disabled = true;

        var P = new Point(0, 0);
        P.x = event.x;
        P.y = event.y;
        if (Mouse_Flag == false) {
            return;
        }
        Mouse_Flag = false;
        myStop = new Date();
        mTime = myStop.getTime() - myStart.getTime();

        myCheck(0); //ストップウォッチを止める

        //グループ化されたコントロールの初期化
        for (i = 0; i <= MyControls.length - 1; i++) {
            YAHOO.util.Dom.setStyle(Mylabels_ea[i], "color", "black");
        }
        //削除
        MyControls.splice(0, MyControls.length - 1);

        var $Mouse_Data = Mouse;
        $Mouse_Data["WID"] = WID;
        $Mouse_Data["Time"] = mTime;
        $Mouse_Data["X"] = P.x;
        $Mouse_Data["Y"] = P.y;
        $Mouse_Data["DragDrop"] = -1;
        $Mouse_Data["DropPos"] = -1;
        $Mouse_Data["hlabel"] = "";
        $Mouse_Data["Label"] = "";
        Mouse_Num += 1;

        var $params = 'param1=' + encodeURIComponent($Mouse_Data["WID"])
                      + '&param2=' + encodeURIComponent($Mouse_Data["Time"])
                      + '&param3=' + encodeURIComponent($Mouse_Data["X"])
                      + '&param4=' + encodeURIComponent($Mouse_Data["Y"])
                      + '&param5=' + encodeURIComponent($Mouse_Data["DragDrop"])
                      + '&param6=' + encodeURIComponent($Mouse_Data["DropPos"])
                      + '&param7=' + encodeURIComponent($Mouse_Data["hlabel"])
                      + '&param8=' + encodeURIComponent($Mouse_Data["Label"]);
        new Ajax.Request(URL + 'tmpfile.php',
        {
            method: 'get',
            onSuccess: getA,
            onFailure: getE,
            parameters: $params
        });
        //▲マウスデータの取得
        function getA(req) {
        }
        function getE(req) {
            alert("失敗g");
        }
        //先頭の文字列を大文字に変換
        str1 = Mylabels_ea[0].innerHTML.substr(0, 1);
        str2 = Mylabels_ea[0].innerHTML.substr(1);
        Mylabels_ea[0].innerHTML = str1.toUpperCase() + str2; //先頭の文字が大文字に

        //ピリオドまたはクエスチョンを最後につける
        Mylabels_ea[Mylabels2.length - 1].innerHTML += PorQ;

        //自分の解答を文字列に格納
        for (i = 0; i <= Mylabels2.length - 1; i++) {
            //区切りラベルは解答に入れない
            if (Mylabels_ea[i].innerHTML == "/") {
                continue;
            }
            MyAnswer += Mylabels_ea[i].innerHTML + " ";
        }
        MyAnswer = MyAnswer.replace(/^\s+|\s+$/g, ""); //前後の空白削除
        WriteAnswer = MyAnswer;
        $QAData["EndSentence"] = MyAnswer;
        ResAns += 1;
        AllResAns += 1;

        //単語単位迷い度取得の配列初期化・単語迷い度変数初期化
        $countHearing = [];
        for (i = 0; i <= Mylabels2.length - 1; i++){
        $countHearing[i] = 0;
        }
        $countH0 = 999999999;
        $countH1 = 999999999;
        $countH2 = 999999999;
        $countH3 = 999999999;
        $countH4 = 999999999;
        $countH5 = 999999999;
        $countH6 = 999999999;
        $countH7 = 999999999;
        $countH8 = 999999999;
        $countH9 = 999999999;
        $countH10 = 999999999;
        $countH11 = 999999999;
        $countH12 = 999999999;
        $countH13 = 999999999;
        $countH14 = 999999999;
        $countH15 = 999999999;
        $countH16 = 999999999;
        $countH17 = 999999999;
        $countH18 = 999999999;
        $countH19 = 999999999;
        $countH20 = 999999999;


        //例題の場合
        if (OID == -1) {
            print_answer();
            YAHOO.util.Dom.setStyle("TextBox1", "display", "none");
            YAHOO.util.Dom.setStyle("Label2", "display", "none");
            YAHOO.util.Dom.setStyle("Fixmsg", "display", "none");
            YAHOO.util.Dom.setStyle("ButtonE2", "display", "block");
            YAHOO.util.Dom.setStyle("register", "display", "none");
            YAHOO.util.Dom.setStyle("register1", "display", "none");
            YAHOO.util.Dom.setStyle("register2", "display", "none");
            YAHOO.util.Dom.setStyle("register3", "display", "none");
        }else{

            $QAData["comments"] = -1;
            $QAData["hesitate"] = -1;
            $QAData["hesitate1"] = -1;
            $QAData["hesitate2"] = -1;
            $QAData["check"] = 0;

            YAHOO.util.Dom.setStyle("hearing", "display", "block");
            YAHOO.util.Dom.setStyle("hearing2", "display", "block");
            YAHOO.util.Dom.setStyle("hearingT1", "display", "block");
            YAHOO.util.Dom.setStyle("hearingT2", "display", "block");
            YAHOO.util.Dom.setStyle("checkbox", "display", "block");
            YAHOO.util.Dom.setStyle("checkbox2", "display", "block");
            
            // var checkbox = document.getElementById('three');
            // checkbox.indeterminate = true;
          
            var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comment' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;//仮の直しcomments→comment

            // for (i = 0; i <= Mylabels2.length - 1; i++) {
            // YAHOO.util.Dom.setStyle("radiobotton", "display", "block");
            // }

            YAHOO.util.Dom.setStyle("comments", "display", "block");
            //YAHOO.util.Dom.setStyle("comments2", "display", "block");
            YAHOO.util.Dom.setStyle("QuesLevel", "display", "none");

            YAHOO.util.Dom.setStyle("choose2", "display", "none");
            //YAHOO.util.Dom.setStyle("TermText", "display", "none");
            //YAHOO.util.Dom.setStyle("TermLabel", "display", "none");
            //YAHOO.util.Dom.setStyle("OrderLabel", "display", "none");
            //YAHOO.util.Dom.setStyle("ButtonM", "display", "none");
            YAHOO.util.Dom.setStyle("TextBox1", "display", "none");
            YAHOO.util.Dom.setStyle("Label2", "display", "none");
            YAHOO.util.Dom.setStyle("Fixmsg", "display", "none");
            YAHOO.util.Dom.setStyle("ButtonM", "display", "none");
            //YAHOO.util.Dom.setStyle("ButtonM2", "display", "none");
            YAHOO.util.Dom.setStyle("Button5", "display", "block");

            YAHOO.util.Dom.setStyle("register", "display", "none");
            YAHOO.util.Dom.setStyle("register1", "display", "none");
            YAHOO.util.Dom.setStyle("register2", "display", "none");
            YAHOO.util.Dom.setStyle("register3", "display", "none");

           
            document.getElementById("Button2").disabled = true;
            document.getElementById("Buttonl").disabled = true;

            }


        //決定を押した後にクリックできないように要素を見えなくする→解答欄のdivタグに追加して表示
        var answerBox = document.getElementById('answer');
        for(var i=0; Mylabels_ea.length; i++){
            Mylabels_ea[i].setAttribute('style','display:none;');
            var span = document.createElement('span');
            span.setAttribute('style','font-size:1.6em;');
            span.appendChild(document.createTextNode(Mylabels_ea[i].firstChild.nodeValue+' '));
            answerBox.appendChild(span);
        }

    }

    //○○次の問題ボタン
    function Button2_Click() {
        var answerBox = document.getElementById('answer');
        jQuery(answerBox).empty();

        if (OID == nEnd) {
            alert(<?= json_encode(translate('ques.php_996行目_終了です')) ?>);
            return;
        } else if (OID % 5 == 0 && OID != 0) {
                var alert_msg = <?= json_encode(translate('ques.php_998行目_書き込みを行ってください')) ?> + QuesNum + <?= json_encode(translate('ques.php_998行目_問終了')) ?>;
                alert(alert_msg);
                return;
        }
        if (Mouse_Flag == true) {
            return;
        }

        for (i = 0; i <= LabelNum - 1; i++) {
            _delete_dom_obj(i);
        }
        for (i = 0; i >= -MyNums.length + 1; i--) {
            _delete_dom_obj(i);
        }
        MyNums.splice(0, MyNums.length - 1);
        StartQues = "";
        FixLabels = new Array(); //固定ラベル
        FixNum = new Array(); //固定ラベルの番号
        FixText = new Array(); //固定ラベルのタグを含むテキスト
        //ここで初期化類やっちゃおう
        Mylabels_r1.length = 0;
        Mylabels_r2.length = 0;
        Mylabels_r3.length = 0;
        Mylabels_ea.length = 0;
        Mylabels2.length = 0;
        Mylabels_left.length = 0;
        OID = (OID * 1) + 1;
        QuesNum = (OID * 1);
        MyAnswer = "";
        linedataFlg = false;
        document.getElementById("TermText").value = "";
        //document.getElementById("QuesLevel").options[0].selected = true;

        if (OID == 1) {
            YAHOO.util.Dom.setStyle("exercise", "display", "none");
            YAHOO.util.Dom.setStyle("ButtonE2", "display", "none");
            document.getElementById("Button1").disabled = false;
        }else{
            YAHOO.util.Dom.setStyle("Button2", "display", "none");
            YAHOO.util.Dom.setStyle("QuesLevel", "display", "none"); //悩み度
            YAHOO.util.Dom.setStyle("choose2", "display", "none");
            YAHOO.util.Dom.setStyle("QuesLabel", "display", "none");
            YAHOO.util.Dom.setStyle("TermText", "display", "none");
            YAHOO.util.Dom.setStyle("TermLabel", "display", "none");
            YAHOO.util.Dom.setStyle("OrderLabel", "display", "none");
            YAHOO.util.Dom.setStyle("Button4", "display", "none");
            YAHOO.util.Dom.setStyle("ButtonM", "display", "none");
            document.getElementById("Button4").disabled = true;
        }

        document.getElementById("number").innerHTML = "<b>" + QuesNum + "/" + nEnd + "<b>";
        YAHOO.util.Dom.setStyle("number", "display", "block");
        YAHOO.util.Dom.setStyle("Button1", "display", "block");
        YAHOO.util.Dom.setStyle("RichTextBox2", "display", "none"); //解答(下)
        YAHOO.util.Dom.setStyle("RichTextBox3", "display", "none"); //正誤
        YAHOO.util.Dom.setStyle("TextBox1", "display", "none"); //解答時間
        YAHOO.util.Dom.setStyle("Label2", "display", "block"); //説明
        YAHOO.util.Dom.setStyle("Fixmsg", "display", "block");
        YAHOO.util.Dom.setStyle("register", "display", "block");
        YAHOO.util.Dom.setStyle("register1", "display", "block");
        YAHOO.util.Dom.setStyle("register2", "display", "block");
        YAHOO.util.Dom.setStyle("register3", "display", "block");

        setques();
    }
    //解答ラベルの削除-----------------------------
    function _delete_dom_obj(id_name) {
        var dom_obj = document.getElementById(id_name);
        var dom_obj_parent = dom_obj.parentNode;
        dom_obj_parent.removeChild(dom_obj);
    }
    //スタート
    function Button3_Click() {
        if (OID > nEnd) {
            alert(<?= json_encode(translate('ques.php_1045行目_終了しています')) ?>);
            document.location = "result.php?Qid=" + Qid;
        }
        if (Mouse_Flag == true) {
            return;
        }

        /*
        20250108変更
        if (OID == 0) {
            YAHOO.util.Dom.setStyle("exercise", "display", "block");
        }else{
            YAHOO.util.Dom.setStyle("exercise", "display", "none");
            document.getElementById("number").innerHTML = "<b>" + QuesNum + "/" + nEnd + "<b>";
            YAHOO.util.Dom.setStyle("number", "display", "block");
            document.getElementById("Button4").disabled = true;
            YAHOO.util.Dom.setStyle("Button4", "display", "none");
        }
            */
        YAHOO.util.Dom.setStyle("exercise", "display", "none");
        document.getElementById("number").innerHTML = "<b>" + QuesNum + "/" + nEnd + "<b>";
        YAHOO.util.Dom.setStyle("number", "display", "block");
        document.getElementById("Button4").disabled = true;
        YAHOO.util.Dom.setStyle("Button4", "display", "none");

        document.getElementById("Button3").disabled = true;
        YAHOO.util.Dom.setStyle("Button3", "display", "none");
        YAHOO.util.Dom.setStyle("Button1", "display", "block");
        setques();
    
    }

    //迷い度決定
     function ButtonM_Click() {
        
         var cmbQues;
        
        //if ($QAData["hesitate"] == "" && $QAData["check"] == 0 ) {
        //cmbQues = document.getElementById("QuesLevel");
        //} else if ($QAData["hesitate"] == "" && $QAData["check"] == 1 ){
        //cmbQues = document.getElementById("QuesLevel3");
        //} else if ($QAData["hesitate"] == 0 || $QAData["hesitate"] == 1 || $QAData["hesitate"] == 2 || $QAData["hesitate"] == 3 || $QAData["hesitate"] == 4 || $QAData["hesitate"] == 5 || $QAData["hesitate"] == 6 || $QAData["hesitate"] == 7 || $QAData["hesitate"] == 8 || $QAData["hesitate"] == 9 || $QAData["hesitate"] == 10 || $QAData["hesitate"] == 11 || $QAData["hesitate"] == 12 ){
        //cmbQues = document.getElementById("QuesLevel2");
        //} else {
        //cmbQues = document.getElementById("QuesLevel3");
        //}

        if ($QAData["check"] === 1 || $QAData["hesitate2"] != "") {

        cmbQues = document.getElementById("QuesLevel3");
        } else {
        if ($QAData["hesitate"] === "" ){

        cmbQues = document.getElementById("QuesLevel");
        } else if ($QAData["hesitate1"] != "" ){
        cmbQues = document.getElementById("QuesLevel2");
        } else {

        cmbQues = document.getElementById("QuesLevel3");
        }
        }

        /*if ($QAData["hesitate"] == "" && $QAData["check"] == 0 ) {
        cmbQues = document.getElementById("QuesLevel");
        } else if ($QAData["check"] == 1 ){
        cmbQues = document.getElementById("QuesLevel3");
        } else if (($QAData["hesitate"] == 0 || $QAData["hesitate"] == 1 || $QAData["hesitate"] == 2 || $QAData["hesitate"] == 3 || $QAData["hesitate"] == 4 || $QAData["hesitate"] == 5 || $QAData["hesitate"] == 6 || $QAData["hesitate"] == 7 || $QAData["hesitate"] == 8 || $QAData["hesitate"] == 9 || $QAData["hesitate"] == 10 || $QAData["hesitate"] == 11 || $QAData["hesitate"] == 12 || $QAData["hesitate"] == 13 || $QAData["hesitate"] == 14 || $QAData["hesitate"] == 15 || $QAData["hesitate"] == 16 || $QAData["hesitate"] == 17 || $QAData["hesitate"] == 18 || $QAData["hesitate"] == 19 || $QAData["hesitate"] == 20 || $QAData["hesitate"] == 21) && $QAData["check"] == 0 ){
        cmbQues = document.getElementById("QuesLevel2");
        } else {
        cmbQues = document.getElementById("QuesLevel3");
        }*/


        $QAData["Understand"] = 5 - (cmbQues.selectedIndex * 1);

        if ($QAData["Understand"] == 5) {
            alert("迷い度が選択されていません");
            return;
        }

        var MyComments = document.getElementsByTagName("textarea");
        
        cmt = MyComments[0].value;
        if (cmt == "") cmt = "";
        
        $QAData["comments"] = cmt;



         document.getElementById("Button1").disabled = false;

         YAHOO.util.Dom.setStyle("QuesLevel", "display", "none");
         YAHOO.util.Dom.setStyle("QuesLevel2", "display", "none");
         YAHOO.util.Dom.setStyle("QuesLevel3", "display", "none");
         YAHOO.util.Dom.setStyle("choose2", "display", "none");
         YAHOO.util.Dom.setStyle("TermText", "display", "none");
         YAHOO.util.Dom.setStyle("TermLabel", "display", "none");
         YAHOO.util.Dom.setStyle("OrderLabel", "display", "none");
         YAHOO.util.Dom.setStyle("ButtonM", "display", "none");

         YAHOO.util.Dom.setStyle("hearing", "display", "none");//自由記述欄修正
         YAHOO.util.Dom.setStyle("comments", "display", "none");//自由記述欄修正
         YAHOO.util.Dom.setStyle("comments2", "display", "none");//自由記述欄修正

        last();
                   
        }

        //悩み度変更
    //  function QuesLevelChange() {
    //     var obj;
    //     obj = document.getElementById("QuesLevel");
    //     index = obj.selectedIndex;
    //     if (index != 0) {
    //             YAHOO.util.Dom.setStyle("ButtonM", "display", "block");
    //             document.getElementById("Button1").disabled = false;
           
    //     }
    // }

    //悩み度決定2
     // function ButtonM2_Click() {

     //    var obj;
     //    obj = document.getElementById("QuesLevel");
     //    index = obj.selectedIndex;
     //    if (index != 0) {
     //            YAHOO.util.Dom.setStyle("ButtonM", "display", "block");
     //            document.getElementById("Button1").disabled = false;
           
     //    }

     //     var cmbQues;
     //     cmbQues = document.getElementById("QuesLevel");

     //     $QAData["Understand"] = 5 - (cmbQues.selectedIndex * 1);

     //     YAHOO.util.Dom.setStyle("QuesLevel", "display", "none");
     //     YAHOO.util.Dom.setStyle("choose2", "display", "none");
     //     YAHOO.util.Dom.setStyle("TermText", "display", "none");
     //     YAHOO.util.Dom.setStyle("TermLabel", "display", "none");
     //     YAHOO.util.Dom.setStyle("OrderLabel", "display", "none");
     //     YAHOO.util.Dom.setStyle("ButtonM", "display", "none");

     //    last();
                   
     //    }


    //正誤表示
    function print_answer() {
        if (MyAnswer == Answer || MyAnswer == Answer1 || MyAnswer == Answer2) {
            document.getElementById("RichTextBox3").innerHTML = <?= json_encode(translate('ques.php_890行目_正誤O')) ?>;
            YAHOO.util.Dom.setStyle("RichTextBox3", "color", "red");
            //DBに登録するときは１とするように変更が必要
            TF = 1;
            document.getElementById("RichTextBox2").innerHTML = <?= json_encode(translate('ques.php_893行目_正解')) ?> + Answer;
            YAHOO.util.Dom.setStyle("RichTextBox2", "display", "block");
            AllCorrectAns += 1;
        } else {
            document.getElementById("RichTextBox3").innerHTML = <?= json_encode(translate('ques.php_896行目_正誤X')) ?>;
            YAHOO.util.Dom.setStyle("RichTextBox3", "color", "blue");
            //DBに登録するときは0とするように変更が必要
            TF = 0;
            document.getElementById("RichTextBox2").innerHTML = <?= json_encode(translate('ques.php_893行目_正解')) ?> + Answer;
            YAHOO.util.Dom.setStyle("RichTextBox2", "display", "block");
        }
        YAHOO.util.Dom.setStyle("RichTextBox3", "display", "block");
        YAHOO.util.Dom.setStyle("choose2", "display", "none");

        var myStoppers;
        var mTimers;
        myStoppers = new Date();
        mTimers = myStoppers.getTime() - myStart.getTime();

        $QAData["WID"] = WID;
        $QAData["Date"] = Answertime;
        $QAData["TF"] = TF;
        $QAData["Time"] = mTimers;
        $QAData["Qid"] = Qid;
        var $params = 'param1=' + encodeURIComponent($QAData["WID"])
                              + '&param2=' + encodeURIComponent($QAData["Date"])
                              + '&param3=' + encodeURIComponent($QAData["TF"])
                              + '&param4=' + encodeURIComponent($QAData["Time"])
                              + '&param5=' + encodeURIComponent($QAData["Understand"])
                              + '&param6=' + encodeURIComponent($QAData["EndSentence"])
                              + '&param7=' + encodeURIComponent($QAData["hesitate"])
                              + '&param8=' + encodeURIComponent($QAData["hesitate1"])
                              + '&param9=' + encodeURIComponent($QAData["hesitate2"])
                              + '&param10=' + encodeURIComponent($QAData["comments"])
                              + '&param11=' + encodeURIComponent($QAData["check"]);

        if (!(linedataFlg)) {
            linedataFlg = true;
            new Ajax.Request(URL + 'tmpfile2.php',
                    {
                        method: 'get',
                        onSuccess: getA,
                        onFailure: getE,
                        parameters: $params
                    });
            //▲マウスデータの取得
            //ドラッグ開始地点の保存
            function getA(req) {
                //ここでuser_progressを更新する
                $u = "u";
                $params = 'param1=' + encodeURIComponent(OID)
                        + '&param2=' + encodeURIComponent($u);
                new Ajax.Request(URL + 'dbsyori.php', //本番用
                    {
                    method: 'get',
                    onSuccess: getwriteuser_progress,
                    onFailure: function(req){getError(req,"dbsyori.php")},
                    parameters: $params
                });
            }
            function getwriteuser_progress(req) {
            
            }
            function getE(req) {
                alert("失敗h");
            }
        }
        Mouse_Num = 0;
    }

    //最終処理
    function last(){

        print_answer();
       
        YAHOO.util.Dom.setStyle("TextBox1", "display", "block");
        if (OID % 5 != 0) {
            YAHOO.util.Dom.setStyle("Button2", "display", "block");
            document.getElementById("Button2").disabled = false;
        }        
        YAHOO.util.Dom.setStyle("Button4", "display", "block");
        document.getElementById("Button4").disabled = false;
        //document.getElementById("Button2").disabled = false;
        document.check.checkbox.checked = false;

        if (OID == nEnd) {
            alert(<?= json_encode(translate('ques.php_1058行目_終了ですお疲れ様でした')) ?>);
            //▲マウスデータの取得
            alert(<?= json_encode(translate('ques.php_1062行目_採点を行います')) ?>);
            new Ajax.Request(URL + 'ewrite.php',
            {
                method: 'get',
                onSuccess: getA,
                onFailure: getE
            });
            //▲マウスデータの取得
            //ドラッグ開始地点の保存
            function getA(req) {
                mybutton = 0;
                alert(req.responseText);
                document.location = "result.php?Qid=" + Qid;
            }
            function getE(req) {
                alert(<?= json_encode(translate('ques.php_929行目_書き込み失敗')) ?>);
            }
            return;
        } else if (OID % 5 == 0) {
            var alert_msg = <?= json_encode(translate('ques.php_1074行目_書き込みを行ってください2')) ?> + QuesNum + <?= json_encode(translate('ques.php_998行目_問終了')) ?>;
            alert(alert_msg);
            return;
        }
    }


    //単語ごとの迷い度3段階
    /*$countHearing = [];
    for (i = 0; i <= Mylabels2.length - 1; i++){
        $countHearing[i] = 0;
    }*/
    $countH0 = 999999999;
    $countH0_3 = 0;
    function ButtonH0_Click(){
        //$countH = 0;
        $countH0--;
        $countHearing[0] = $countH0 % 3;
        //alert($countHearing[0]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH1 = 99999999;
    $countH1_3 = 0;
    function ButtonH1_Click(){
        //$countH = 0;
        $countH1--;
        $countHearing[1] = $countH1 % 3;
        //alert($countHearing[1]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH2 = 99999999;
    $countH2_3 = 0;
    function ButtonH2_Click(){
        //$countH = 0;
        $countH2--;
        $countHearing[2] = $countH2 % 3;
        //alert($countHearing[2]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH3 = 99999999;
    $countH3_3 = 0;
    function ButtonH3_Click(){
        //$countH = 0;
        $countH3--;
        $countHearing[3] = $countH3 % 3;
        //alert($countHearing[3]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH4 = 99999999;
    $countH4_3 = 0;
    function ButtonH4_Click(){
        //$countH = 0;
        $countH4--;
        $countHearing[4] = $countH4 % 3;
        //alert($countHearing[4]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH5 = 99999999;
    $countH5_3 = 0;
    function ButtonH5_Click(){
        //$countH = 0;
        $countH5--;
        $countHearing[5] = $countH5 % 3;
        //alert($countHearing[5]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH6 = 99999999;
    $countH6_3 = 0;
    function ButtonH6_Click(){
        //$countH = 0;
        $countH6--;
        $countHearing[6] = $countH6 % 3;
        //alert($countHearing[6]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH7 = 99999999;
    $countH7_3 = 0;
    function ButtonH7_Click(){
        //$countH = 0;
        $countH7--;
        $countHearing[7] = $countH7 % 3;
        //alert($countHearing[7]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH8 = 99999999;
    function ButtonH8_Click(){
        //$countH = 0;
        $countH8--;
        $countHearing[8] = $countH8 % 3;
        //alert($countHearing[8]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH9 = 99999999;
    function ButtonH9_Click(){
        //$countH = 0;
        $countH9--;
        $countHearing[9] = $countH9 % 3;
        //alert($countHearing[9]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH10 = 99999999;
    function ButtonH10_Click(){
        //$countH = 0;
        $countH10--;
        $countHearing[10]= $countH10 % 3;
        //alert($countHearing[10]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH11 = 99999999;
    function ButtonH11_Click(){
        //$countH = 0;
        $countH11--;
        $countHearing[11] = $countH11 % 3;
        //alert($countHearing[11]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH12 = 99999999;
    function ButtonH12_Click(){
        //$countH = 0;
        $countH12--;
        $countHearing[12] = $countH12 % 3;
        //alert($countHearing[12]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH13 = 99999999;
    function ButtonH13_Click(){
        //$countH = 0;
        $countH13--;
        $countHearing[13] = $countH13 % 3;
        //alert($countHearing[13]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH14 = 99999999;
    function ButtonH14_Click(){
        //$countH = 0;
        $countH14--;
        $countHearing[14] = $countH14 % 3;
        //alert($countHearing[14]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH15 = 99999999;
    function ButtonH15_Click(){
        //$countH = 0;
        $countH15--;
        $countHearing[15] = $countH15 % 3;
        //alert($countHearing[15]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH16 = 99999999;
    function ButtonH16_Click(){
        //$countH = 0;
        $countH16--;
        $countHearing[16] = $countH16 % 3;
        //alert($countHearing[16]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH17 = 99999999;
    function ButtonH17_Click(){
        //$countH = 0;
        $countH17--;
        $countHearing[17] = $countH17 % 3;
        //alert($countHearing[17]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH18 = 99999999;
    function ButtonH18_Click(){
        //$countH = 0;
        $countH18--;
        $countHearing[18] = $countH18 % 3;
        //alert($countHearing[18]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH19 = 99999999;
    function ButtonH19_Click(){
        //$countH = 0;
        $countH19--;
        $countHearing[19] = $countH19 % 3;
        //alert($countHearing[19]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }
    $countH20 = 99999999;
    function ButtonH20_Click(){
        //$countH = 0;
        $countH20--;
        $countHearing[20] = $countH20 % 3;
        //alert($countHearing[20]);
        var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
            for (i = 0; i <= Mylabels2.length - 1; i++) {
                HearingHtml += "<input name=\"HearingCheck\" id=\"select" + i + "\" value=\""+ i + "\"  onclick=\"ButtonH"+i+"_Click()\" s=\""+$countHearing[i]+"\" type=\"button\"><label for=\"select" + i + "\"s=\""+$countHearing[i]+"\">"
                            + Mylabels_ea[i].innerHTML + "</label>";
                
            }
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:350;top:50;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;
    }

    //ヒアリング
    function Button5_Click() {
        //alert("countHearing"+$countHearing);

        

        var numC = 0;
        var num = 0;
        var num2 = 0;
        chkvalue = "";
        chkvalue_1 = "";
        chkvalue_2 = "";
        var chkvalue2 = 0;
        var MyForm = document.getElementById("HearingForm");
        var MyTag = MyForm.getElementsByTagName("input");

        
        
        chk = document.check.checkbox.checked;
        //alert(document.getElementById("HearingForm")[1].s);
        for (i = 0; i < MyTag.length; i++) {
            if(($countHearing[i] == 1)||($countHearing[i] == 2)){//if (MyTag[i].checked) {
                if (numC == 0) chkvalue += MyTag[i].value;
                else chkvalue += "#" + MyTag[i].value;
                numC++;
            }
        }
        for (i = 0; i < MyTag.length; i++) {
            if($countHearing[i] == 1){//if (MyTag[i].checked) {
                if (num == 0) chkvalue_1 += MyTag[i].value;
                else chkvalue_1 += "#" + MyTag[i].value;
                num++;
            }
        }
        for (i = 0; i < MyTag.length; i++) {
            if($countHearing[i] == 2){//if (MyTag[i].checked) {
                if (num2 == 0) chkvalue_2 += MyTag[i].value;
                else chkvalue_2 += "#" + MyTag[i].value;
                num2++;
            }
        }
        
        //自由記述欄修正
        YAHOO.util.Dom.setStyle("hearing", "display", "block");
        
        
            
            // var checkbox = document.getElementById('three');
            // checkbox.indeterminate = true;
          
            var HearingHtml = "";
            HearingHtml = "<form name=\"Hearing\" id=\"HearingForm\"><div class=\"check\">";
        
            HearingHtml += "</div><textarea id='comments' cols='50' rows='2' style=\" position:absolute;left:30;top:120;display:none; \"></textarea></form>";
            document.getElementById("hearing").innerHTML = HearingHtml;//仮の直しcomments→comment

            // for (i = 0; i <= Mylabels2.length - 1; i++) {
            // YAHOO.util.Dom.setStyle("radiobotton", "display", "block");
            // }

            YAHOO.util.Dom.setStyle("comments", "display", "block");
            YAHOO.util.Dom.setStyle("comments2", "display", "block");
        
        //alert("両方:"+chkvalue);
        //alert("迷い度1:"+chkvalue_1);
        //alert("迷い度2:"+chkvalue_2);
        var MyComments = document.getElementsByTagName("textarea");
        
        cmt = MyComments[0].value;
        if (cmt == "") cmt = "";

        if( chk==true ){
            chkvalue2 = 1;
        }
        //document.write(chkvalue2);

        

        $QAData["comments"] = cmt;
        $QAData["hesitate"] = chkvalue;
        $QAData["hesitate1"] = chkvalue_1;
        $QAData["hesitate2"] = chkvalue_2;
        $QAData["check"] = chkvalue2;

        // if(cmt == "" && chkvalue == "" && chkvalue2 == 0){
        //    alert("チェックまたは記述をしてください。");
        //     return;
        // }

        /*if ($QAData["hesitate"] == "" && $QAData["check"] == 0 ) {
        YAHOO.util.Dom.setStyle("QuesLevel", "display", "block");
        } else if ($QAData["check"] == 1 ){
        YAHOO.util.Dom.setStyle("QuesLevel3", "display", "block");
        } else if (($QAData["hesitate"] == 0 || $QAData["hesitate"] == 1 || $QAData["hesitate"] == 2 || $QAData["hesitate"] == 3 || $QAData["hesitate"] == 4 || $QAData["hesitate"] == 5 || $QAData["hesitate"] == 6 || $QAData["hesitate"] == 7 || $QAData["hesitate"] == 8 || $QAData["hesitate"] == 9 || $QAData["hesitate"] == 10 || $QAData["hesitate"] == 11 || $QAData["hesitate"] == 12 || $QAData["hesitate"] == 13 || $QAData["hesitate"] == 14 || $QAData["hesitate"] == 15 || $QAData["hesitate"] == 16 || $QAData["hesitate"] == 17 || $QAData["hesitate"] == 18 || $QAData["hesitate"] == 19 || $QAData["hesitate"] == 20 || $QAData["hesitate"] == 21) && $QAData["check"] == 0 ){
        YAHOO.util.Dom.setStyle("QuesLevel2", "display", "block");
        } else {
        YAHOO.util.Dom.setStyle("QuesLevel3", "display", "block");
        }*/


        if ($QAData["check"] === 1 || $QAData["hesitate2"] != "") {

        YAHOO.util.Dom.setStyle("QuesLevel3", "display", "block");
        } else {
        if ($QAData["hesitate"] === "" ){

        YAHOO.util.Dom.setStyle("QuesLevel", "display", "block");
        } else if ($QAData["hesitate1"] != ""){
        YAHOO.util.Dom.setStyle("QuesLevel2", "display", "block")
        } else {

        YAHOO.util.Dom.setStyle("QuesLevel3", "display", "block");
        }
        }


        //YAHOO.util.Dom.setStyle("QuesLevel", "display", "block");
        //YAHOO.util.Dom.setStyle("comments", "display", "none");//自由記述欄修正
        //YAHOO.util.Dom.setStyle("comments2", "display", "none");
        YAHOO.util.Dom.setStyle("choose2", "display", "block");
        //YAHOO.util.Dom.setStyle("hearing", "display", "none");//自由記述欄修正
        YAHOO.util.Dom.setStyle("hearing2", "display", "none");
        YAHOO.util.Dom.setStyle("hearingT1", "display", "none");
        YAHOO.util.Dom.setStyle("hearingT2", "display", "none");
        YAHOO.util.Dom.setStyle("checkbox", "display", "none");
        YAHOO.util.Dom.setStyle("checkbox2", "display", "none");
        YAHOO.util.Dom.setStyle("Button5", "display", "none");

        YAHOO.util.Dom.setStyle("ButtonM", "display", "block");
        //document.getElementById("Button1").disabled = false;
        //YAHOO.util.Dom.setStyle("ButtonM2", "display", "block");
          
    }

        

</script>
<body id=mybody onLoad = "ques_Load()" onMouseDown = "Form1_MouseDown()" onMouseUp = "Form1_MouseUp()">
<input type = "button"
    id = "Button3"
    value="<?= translate('ques.php_1512行目_スタート') ?>"
    onclick="Button3_Click()"
    style="width:80px;height:36px;position:absolute;left:768px;top:27px;display: block"/>

<input type = "button"
    id = "Button1"
    value="<?= translate('ques.php_1517行目_決定') ?>"
    onclick="Button1_Click()"
    style="width:80px;height:36px;position:absolute;left:768px;top:32px;display:none"/>

<form name="Questions">
<input type = "button"
    id = "ButtonM"
    value="<?= translate('ques.php_1517行目_決定') ?>"
    onclick="ButtonM_Click()"
    style="width:80px;height:30px;position:absolute;left:600px;top:365px;display:none"/>
</form>

<form name="Hearing">
<input type = "button"
    id = "Button5"
    value="<?= translate('ques.php_1517行目_決定') ?>"
    onclick="Button5_Click()"
    style="width:80px;height:30px;position:absolute;left:750px;top:240px;display:none"/>
</form>

<input type = "button"
    id = "Button2"
    value="<?= translate('ques.php_1541行目_次の問題') ?>"
    onclick="Button2_Click()"
    style="width:auto;height:33px;position:absolute;left:670px;top:365px;display:none"/>

<input type = "button"
    id = "ButtonE2"
    value="<?= translate('ques.php_1546行目_問題へ') ?>"
    onclick="Button2_Click()"
    style="width:75px;height:33px;position:absolute;left:768px;top:274px;display:none"/>


<input type = "button"
    id = "Button4"
    value="<?= translate('ques.php_1551行目_終了') ?>"
    onclick="LineQuestioneForm_Closing()"
    style="width:75px;height:20px;position:absolute;left:780px;top:365px;background-color:pink;display:none"/>

<font color="red" style="position:absolute;left:12;top:7"><?= translate('ques.php_1554行目_日本文') ?></font>
<div id = "RichTextBox1" style="background-color:#ffa500;position:absolute;
     left:12;top:27;width:731;height:36;border-style:inset">
     <?= translate('ques.php_1556行目_ここに日本文が表示されます') ?></div>
<div id = "RichTextBox2" style="background-color:#a1ffa1;position:absolute;
     left:12;top:240;width:650;height:67;border-style:inset;display:none"><?= translate('ques.php_1559行目_ここに正解を表示') ?></div>

<div id = "RichTextBox3" style="background-color:#a1ffa1;position:absolute;
     left:670;top:272;width:90;height:auto;border-style:inset;display:none"><?= translate('ques.php_1562行目_正誤を表示') ?></div>

<div id = "TextBox1"  style="background-color:#a1ffa1;position:absolute;
     left:670;top:240;width:90;height:23;border-style:inset;display:none"><?= translate('ques.php_1565行目_解答時間') ?></div>

<div id = "Label2" style="position:absolute;
     left:12;top:530;width:300;height:80;font-size:12;background-color:#ffa500;">
         <?= translate('ques.php_1568行目_操作説明') ?></br>
        
         <b><?= translate('ques.php_1570行目_単語の移動') ?></b></br>
         <b><?= translate('ques.php_1571行目_グループ化') ?></b></br></div>

<font id = "register" color ="red" style="position:absolute;left:12;top:220"><?= translate('ques.php_1573行目_単語退避レジスタ') ?></font>
<div id = "register1" style="padding: 10px; border: 2px dotted #333333;position:absolute;
    left:12;top:240;width:500;height:15;font-size:12;"></div>

<div id = "register2" style="padding: 10px; border: 2px dotted #333333;position:absolute;
    left:12;top:320;width:500;height:15;font-size:12;"></div>
<div id = "register3" style="padding: 10px; border: 2px dotted #333333;position:absolute;
    left:12;top:400;width:500;height:15;font-size:12;"></div>

<font color="red" style="position:absolute;left:12;top:140"><?= translate('ques.php_1582行目_解答欄') ?></font>
<div id = "answer" style="z-index=10;padding: 10px; border: 4px solid #333333;position:absolute;
    left:12;top:160;width:800;height:20;font-size:12;"></div>

<div style="position:absolute;left:12;top:70"><font color="red"><?= translate('ques.php_1586行目_問題提示欄') ?></font></div>
<div id = "question" style="padding: 10px; border: 2px solid #333333;position:absolute;
    left:12;top:90;width:800;height:20;font-size:12;"></div>

<font id="hearing2" color="red" style="position:absolute;left:12;top:220;display:none"><b><?= translate('ques.php_1590行目_迷った単語をクリックしてください') ?></b></font>

<div id="hearingT2" style="position:absolute;
     left:400;top:220;width:auto;height:20;font-size:12;background-color:#ff0000;display:none"><?= translate('ques.php_1592行目_かなり迷った') ?></div>
<div id="hearingT1" style="position:absolute;
     left:500;top:220;width:auto;height:20;font-size:12;background-color:#ffee00;display:none"><?= translate('ques.php_1594行目_少し迷った') ?></div>

<div id = "hearing" style="padding: 10px; border: 1px solid #333333;position:absolute;
    left:12;top:240;width:700;height:60;font-size:36;display:none;background-color: #ffffff">
</div>

<font id = "comments2" cols='50' rows='2' size='2' style=" position:absolute;left:30;top:330;display:none;"><b><?= translate('ques.php_1600行目_自由にご記入ください') ?></b></font>


<form name="check" action="">
<input id="checkbox" 
type="checkbox" 
value="全体的にわからなかった" 
style="width:80px;height:30px;position:absolute;left:5px;top:350px;display:none"/>
</form>
<font id="checkbox2" style="position:absolute;left:70;top:360;display:none"><b><?= translate('ques.php_1606行目_全体的にわからなかった') ?></b></font>

<div id="myCanvas" style="position:absolute;top:0;left:0;height:500px;width:500px;z-index:-1"></div>

<div id="myCanvas2" style="position:absolute;top:0;left:0;height:500px;width:500px;z-index:-1"></div>

<div id="msg" style="position:absolute;
     left:50;top:300;width:500;height:30;font-size:12;background-color:#ffa500;display:none"></div>

<div id="Fixmsg" style="position:absolute;
     left:320;top:530;width:200;height:80;font-size:12;background-color:#ffa500;display:block"><?= translate('ques.php_364行目_情報') ?></div>

<font id="exercise" color="red" style="position:absolute;
     left:768;top:10;width:80;height:18;font-size:18;color:red;display:none"><b><?= translate('ques.php_1622行目_例題') ?></b></font>

<div id="number" style="position:absolute;
     left:768;top:6;width:80;height:18;font-size:18;color:red;display;:none"></div>


<form name="Questions">

<label for="QuesLevel" id="QuesLabel" style="position:absolute;left:600px;top:220px;display:none">
<?= translate('ques.php_1629行目_解答の迷い度') ?></label>
<select 
    id = "QuesLevel"
    size="5"
    style=" font-size: 15px; position:absolute;left:600px;top:240px;display:none">   
<option value="choose" disabled="disabled"><?= translate('ques.php_1633行目_迷い度を選択してください') ?></option> 
<option value="level1" selected="selected"><?= translate('ques.php_1634行目_ほとんど迷わなかった') ?></option> 
<option value="level2"><?= translate('ques.php_1635行目_少し迷った') ?></option>
<option value="level3"><?= translate('ques.php_1636行目_かなり迷った') ?></option>
<option value="level0"><?= translate('ques.php_1637行目_誤って決定ボタンを押した') ?></option>
</select>
<select 
    id = "QuesLevel2"
    size="5"
    style=" font-size: 15px; position:absolute;left:600px;top:240px;display:none">   
<option value="choose" disabled="disabled"><?= translate('ques.php_1633行目_迷い度を選択してください') ?></option> 
<option value="level1"><?= translate('ques.php_1634行目_ほとんど迷わなかった') ?></option> 
<option value="level2" selected="selected"><?= translate('ques.php_1635行目_少し迷った') ?></option>
<option value="level3"><?= translate('ques.php_1636行目_かなり迷った') ?></option>
<option value="level0"><?= translate('ques.php_1637行目_誤って決定ボタンを押した') ?></option>
</select>
<select 
    id = "QuesLevel3"
    size="5"
    style=" font-size: 15px; position:absolute;left:600px;top:240px;display:none">   
<option value="choose" disabled="disabled"><?= translate('ques.php_1633行目_迷い度を選択してください') ?></option> 
<option value="level1"><?= translate('ques.php_1634行目_ほとんど迷わなかった') ?></option> 
<option value="level2"><?= translate('ques.php_1635行目_少し迷った') ?></option>
<option value="level3" selected="selected"><?= translate('ques.php_1636行目_かなり迷った') ?></option>
<option value="level0"><?= translate('ques.php_1637行目_誤って決定ボタンを押した') ?></option>
</select>



<input type="hidden" id="TermText" value="">
 </form>
    <script type="text/javascript">

    function disableSelection(target) {
        if (typeof target.onselectstart != "undefined") //IE route
            target.onselectstart = function () { return false }
        else if (typeof target.style.MozUserSelect != "undefined") //Firefox route
            target.style.MozUserSelect = "none"
        else //All other route (ie: Opera)
            target.onmousedown = function () { return false }
        target.style.cursor = "default"
    }
    disableSelection(document.getElementById("question"));
    disableSelection(document.getElementById("answer"));
    disableSelection(document.getElementById("myCanvas"));
    disableSelection(document.getElementById("myCanvas2"));
    disableSelection(document.getElementById("mybody"));
</script>
</body>
</html>

<?php
// lang.phpでセッションが開始されるため、個別のsession_startは不要
require "../lang.php";
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?= translate('mousemove.php_3行目_マウス軌跡再現') ?></title>
    <script type="text/javascript">
        var nativeSetInterval = window.setInterval;
        _setInterval = {};

        window.setInterval = function (process, delay) {
            var entry;
            if (typeof process == 'string') {
                entry = new _setInterval.Entry(function () {
                    eval(process);
                }, delay);
            } else if (typeof process == 'function') {
                entry = new _setInterval.Entry(process, delay);
            } else {
                throw Error(<?= json_encode(translate('mousemove.php_18行目_第一引数が不正です')) ?>);
            }
            var id = _setInterval.queue.length;
            _setInterval.queue[id] = entry
            return id;
        };

        window.clearInterval = function (id) {
            if (_setInterval.queue[id]) {
                _setInterval.queue[id].loop = function () { };
            }
        };

        _setInterval.queue = [];

        _setInterval.Entry = function (process, delay) {
            this.process = process;
            this.delay = delay;
            this.time = 0;
        };

        _setInterval.Entry.prototype.loop = function (time) {
            this.time += time;
            while (this.time >= this.delay) {
                this.process();
                this.time -= this.delay
            }
        };

        _setInterval.lastTime = new Date().getTime();

        nativeSetInterval(function () {
            var time = new Date().getTime();
            var subTime = time - _setInterval.lastTime;
            _setInterval.lastTime = time;
            for (var i = 0; i < _setInterval.queue.length; i++) {
                if (_setInterval.queue[i]) {
                    _setInterval.queue[i].loop(subTime);
                }
            }
        }, 10);
    </script>

    <?php
    require("../dbc.php");
    ?>
    <style type="text/css">
        <!--
        th,
        td {
            font-size: 11pt;
        }
        -->
    </style>

    <style type="text/css">
        <!--
        div#myCanvasb {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas2 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas2_1 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas2_2 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas2_3 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas2_4 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas3 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas3_1 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas3_2 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas3_3 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas3_4 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas4 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas5 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        div#myCanvas6 {
            position: absolute;
            left: 50px;
            top: 150px;
        }

        #jquery-ui-slider-value {
            border: 0;
            color: red !important;
            font-weight: bold;
            background-color: transparent;
            margin: 5px;
            width: 100px;
        }

        #jquery-ui-slider {
            margin: 0 10px;
            /* width: 300px; */
        }

        #slider-container .ui-slider-range {
            background: #ef2929;
        }

        #slider-container .ui-slider-handle {
            border-color: #ef2929;
        }

        #slider-ticks {
            position: absolute;
            top: 5px;
            /* スライダーの位置に合わせて微調整 */
            left: 0;
            width: 100%;
            height: 10px;
            pointer-events: none;
            /* マウス操作をスライダーに透過させる */
        }

        .tick {
            position: absolute;
            width: 2px;
            height: 10px;
            background-color: rgba(0, 0, 0, 0.7);
            top: -3px;
            /* スライダーのバーの上にはみ出すように */
        }

        #slider-tooltip {
            display: none;
            position: absolute;
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 9999;
            pointer-events: none;
        }

        .wide-container {
            width: 95%;
            /* 幅を画面いっぱいに少し広げます */
            max-width: 1000px;
            margin: 10px 20px;
            /* 上下10px、左右20pxの余白を確保して左寄せにします */
        }

        .info-icon {
            display: inline-block;
            width: 18px;
            height: 18px;
            background-color: #4a90e2;
            color: white;
            border-radius: 50%;
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            line-height: 18px;
            cursor: pointer;
            margin-left: 5px;
            position: relative;
            /* ポップアップの位置の基準点 */
            vertical-align: middle;
        }

        .info-popup {
            visibility: hidden;
            position: absolute;
            /* ▼▼▼▼▼ 変更箇所(ここから) ▼▼▼▼▼ */
            top: 150%;
            /* アイコンからの垂直方向の距離 */
            left: 0;
            /* ポップアップの右端をアイコンの右端に合わせる */
            right: auto;
            /* left指定を解除 */
            transform: none;
            /* transform指定を解除 */
            /* ▲▲▲▲▲ 変更箇所(ここまで) ▲▲▲▲▲ */
            width: 450px;
            background-color: #333;
            color: #fff;
            text-align: left;
            padding: 12px;
            border-radius: 6px;
            z-index: 10;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 11pt;
            font-weight: normal;
            line-height: 1.5;
        }

        .info-popup::after {
            content: "";
            position: absolute;
            /* ▼▼▼▼▼ 変更箇所(ここから) ▼▼▼▼▼ */
            bottom: 100%;
            /* 矢印をポップアップの上に配置 */
            left: 15px;
            /* 矢印を右側に寄せる */
            right: auto;
            /* left指定を解除 */
            margin-right: 0;
            /* margin指定を解除 */
            border-width: 5px;
            border-style: solid;
            border-color: transparent transparent #333 transparent;
            /* 矢印が上を向くように設定 */
            /* ▲▲▲▲▲ 変更箇所(ここまで) ▲▲▲▲▲ */
        }

        .info-icon:hover .info-popup {
            visibility: visible;
            /* ホバー時に表示 */
            opacity: 1;
        }

        /* ▲▲▲▲▲ ここまで ▲▲▲▲▲ */
        -->
    </style>
    <?php
    // 合計値を求めるメソッド sum()
    function sum($array1)
    {
        // 対象配列の抽出
        $target = $array1;
        // ここから合計値の計算
        $result = 0.0; // 合計値
        for ($i = 0; $i < count($target); $i++) {
            $result += $target[$i];
        }
        return $result;    // 合計値を返して終了
    }

    // 平均値・期待値を求めるメソッド ave()
    function ave($array1)
    {
        // 対象配列の抽出
        $target = $array1;
        // 平均値の計算　配列の合計値を算出して、要素数で割る
        $sum = sum($target);
        if (count($target) > 0) {
            $result = $sum / count($target);
        } else {
            $result = 0;
        }
        return $result;
    }

    // 分散を求めるメソッド varp()
    function varp($array1)
    {
        // 対象配列の抽出
        $target = $array1;
        // 分散 E{X-(E(X))^2}　により求められる
        $ave = ave($target);
        $tmp = 0; // 作業用変数
        // X-(E(X))^2 の値を入れておく配列
        $tmparray = array();
        // 配列の1要素ずつ、 (X-E(X))^2 を計算
        for ($i = 0; $i < count($target); $i++) {
            $tmp = $target[$i] - $ave;        // X-E(X)
            $tmparray[$i] = $tmp * $tmp;     // (X-E(X))^2
        }
        // 最後に、その平均値をもとめて終わり
        $result = ave($tmparray);
        return $result;
    }

    // 標準偏差を求めるメソッド sd()
    function sd($array1)
    {
        // 対象配列の抽出
        $target = $array1;
        // 標準偏差は分散の平方根により求められる
        $varp = varp($target);    // 分散の算出
        $result = sqrt($varp);            // その平方根をとる
        return $result;
    }
    ?>

    <?php
    //print_r($_POST["datalist"]);    
    //uid,widを受け取る
    $data_list = "";

    // POSTで受け取ったデータの処理
    if (isset($_POST["datalist"])) {
        $data_list = $_POST["datalist"];
        echo htmlspecialchars($data_list, ENT_QUOTES, 'UTF-8') . "<br>";

        // 受け取ったものをコンマで区切る
        $ID = explode(",", $data_list);

        // 各データを変数に格納
        $uid = $ID[0];
        $wid = $ID[1];
        $attempt_num = $ID[2];
    } elseif (isset($_GET["UID"]) && isset($_GET["WID"]) && isset($_GET["LogID"])) {
        // GETで受け取ったデータの処理（デフォルトのケース）
        $uid = $_GET["UID"];
        $wid = $_GET["WID"];
        $attempt_num = $_GET["LogID"];
    }


    // データベースから値を取り出す
    $query = "select distinct(Time),X,Y,DD,DPos,hLabel,Label,UTurnX,UTurnY from linedatamouse where uid = $uid and WID = $wid and attempt = $attempt_num order by Time";
    $res = mysqli_query($conn, $query) or die("Error:query1");
    $query2 = "select EndSentence,Understand,TF from linedata where uid = $uid and WID = $wid and attempt = $attempt_num";
    $res2 = mysqli_query($conn, $query2) or die("Error:query2");
    $query3 = "select Japanese,Sentence,grammar,level,start,divide from question_info where WID = $wid";
    $res3 = mysqli_query($conn, $query3) or die("Error:query3");
    // ▼▼▼▼▼ trackdataへのクエリを削除し、test_featurevalueへのクエリに変更 ▼▼▼▼▼
    // attemptカラムで絞り込むことで、正しい試行回数のデータを取得
    //$query4 = "select distance, averageSpeed, maxStopTime, DDCount, xUTurnCount, yUTurnCount, xUTurnCountDD, yUTurnCountDD, groupingDDCount from test_featurevalue where uid = $uid and WID = $wid and attempt = $attempt_num";
    $query4 = "select maxStopTime, DDCount, xUTurnCount, yUTurnCount, xUTurnCountDD, yUTurnCountDD, groupingDDCount, answeringTime, maxDDTime, totalStopTime, thinkingTime from test_featurevalue where uid = $uid and WID = $wid and attempt = $attempt_num";
    $res4 = mysqli_query($conn, $query4) or die("Error:query4");
    $query5 = "select Time from linedata where uid = $uid and WID = $wid and attempt = $attempt_num";
    $res5 = mysqli_query($conn, $query5) or die("Error:query5");
    // temporary_resultsから迷い推定結果を取得するクエリ
    $query_est = "SELECT Understand FROM temporary_results WHERE UID = " . $uid . " AND WID = " . $wid . " AND attempt = " . $attempt_num;
    $res_est = mysqli_query($conn, $query_est) or die("Error:query_est");

    $row = mysqli_fetch_array($res2);
    if (isset($row['EndSentence'])) {
        $es = $row['EndSentence'];
    }
    if (isset($row['Understand'])) {
        $us = $row['Understand'];
    }
    // ▼▼▼▼▼ ここから追加 ▼▼▼▼▼
    if (isset($row['TF'])) {
        $tf_result = $row['TF']; // 正誤情報を新しい変数に格納
    }
    // ▲▲▲▲▲ ここまで追加 ▲▲▲▲▲
    $row2 = mysqli_fetch_array($res3);
    if (isset($row2['Japanese'])) {
        $js = $row2['Japanese'];
    }
    if (isset($row2['Sentence'])) {
        $se = $row2['Sentence'];
    }
    if (isset($row2['grammar'])) {
        $grammar = $row2['grammar'];
    }
    if (isset($row2['level'])) {
        $level = $row2['level'];
    }
    if (isset($row2['start'])) {
        $start = $row2['start'];
    }
    $row3 = mysqli_fetch_array($res4);

    if (isset($row3['groupingDDCount'])) {
        $groupcount = $row3['groupingDDCount'];
    }
    // Uターン回数は、ドラッグ中とそうでないものを合算して表示
    if (isset($row3['xUTurnCount']) && isset($row3['xUTurnCountDD'])) {
        $uturncount_X = $row3['xUTurnCount'] + $row3['xUTurnCountDD'];
    }
    if (isset($row3['yUTurnCount']) && isset($row3['yUTurnCountDD'])) {
        $uturncount_Y = $row3['yUTurnCount'] + $row3['yUTurnCountDD'];
    }
    if (isset($row3['maxStopTime'])) { // 'M'を小文字に修正
        $maxstoptime = $row3['maxStopTime'] / 1000;
    }
    if (isset($row3['DDCount'])) { // 'DragDropCount'を'DDCount'に修正
        $dragdropcount = $row3['DDCount'];
    }
    if (isset($row3['maxDDTime'])) {
        $maxDDTime = $row3['maxDDTime'] / 1000;
    }
    if (isset($row3['totalStopTime'])) {
        $totalStopTime = $row3['totalStopTime'] / 1000;
    }
    if (isset($row3['thinkingTime'])) {
        $thinkingTime = $row3['thinkingTime'] / 1000;
    }
    if (isset($row3['answeringTime'])) {
        $answeringTime = $row3['answeringTime'] / 1000;
    }
    $row4 = mysqli_fetch_array($res5);
    if (isset($row4['Time'])) {
        $a_time = $row4['Time'] / 1000;
    }

    $estimated_us = null; // デフォルト値を設定
    if ($row_est = mysqli_fetch_array($res_est)) {
        if (isset($row_est['Understand'])) {
            $estimated_us = $row_est['Understand'];
        }
    }
    $grammar_split = array();
    $grammar_print = array();
    if (isset($grammar)) {
        $grammar_split = explode("#", $grammar);
        array_pop($grammar_split);
        array_shift($grammar_split);

        /*for($i = 0; $i<count($grammar_split); $i++){
            $query6 = "select Item from grammar where GID = $grammar_split[$i]";
            $res6 = mysqli_query($conn,$query6);
            $row5 = mysqli_fetch_array($res6);
            $grammar_print[$i] = $row5['Item'];
        } */

        // ★★★★★★★★★★★★★★★★★★★ 修正箇所 ★★★★★★★★★★★★★★★★★★★
        // 多言語対応テーブルから、現在の言語設定に応じた文法名を取得する
    
        // SQL文をループの外で一度だけ準備（効率化のため）
        // テーブル名はご自身の環境に合わせてください (例: grammar_translations)
        $sql_translate = "SELECT Item FROM grammar_translations WHERE GID = ? AND language = ?";
        $stmt_translate = $conn->prepare($sql_translate);

        if ($stmt_translate) { // SQLの準備が成功したかチェック
            for ($i = 0; $i < count($grammar_split); $i++) {
                // ループの中でパラメータをセットして実行
                $stmt_translate->bind_param("is", $grammar_split[$i], $lang);
                $stmt_translate->execute();
                $result_translate = $stmt_translate->get_result();

                if ($row_translate = $result_translate->fetch_assoc()) {
                    // 翻訳が見つかった場合
                    $grammar_print[$i] = $row_translate['Item'];
                } else {
                    // 翻訳が見つからなかった場合の表示
                    $grammar_print[$i] = "[GID: " . htmlspecialchars($grammar_split[$i], ENT_QUOTES, 'UTF-8') . "]";
                }
            }
            $stmt_translate->close(); // ステートメントを閉じる
        }
        // ★★★★★★★★★★★★★★★★★★★ 修正はここまで ★★★★★★★★★★★★★★★★★★★★
    }

    $time = array();
    $x = array();
    $y = array();
    $DD = array();
    $DPos = array();
    $hLabel = array();
    $Label = array();
    //$addk = array();
    $UTurnX = array();
    $UTurnY = array();


    //echo $uid."<br>";
    //echo $wid."<br>";
    // 切り取って配列へ
    if ($res && $res->num_rows > 0) {
        while ($Column = $res->fetch_assoc()) {
            $time[] = $Column['Time'];
            $x[] = $Column['X'];
            $y[] = $Column['Y'];
            $DD[] = $Column['DD'];
            $DPos[] = $Column['DPos'];
            $hLabel[] = $Column['hLabel'];
            $Label[] = $Column['Label'];
            //$addk[] = $Column['addk'];
            $UTurnX[] = $Column['UTurnX'];
            $UTurnY[] = $Column['UTurnY'];
        }
    } else {
        echo translate('mousemove.php_400行目_結果セットが空です');
    }

    $timestring = "";
    $xstring = "";
    $ystring = "";
    $DDstring = "";
    $DPosstring = "";
    $hLabelstring = "";
    $Labelstring = "";
    $addkstring = "";
    $UTurnXstring = "";
    $UTurnYstring = "";
    $DDdragTime = array();
    $all_dd_events = array(); // 全てのD&Dイベントを格納する配列
    $dd_counts = array();     // 各単語のD&D回数をカウントする配列
    
    // 繋げて配列へ格納(Javascriptへ値を渡すため)
    for ($i = 0; $i < count($time); $i++) {
        if ($i > 0) {
            $timestring .= "###";
            $xstring .= "###";
            $ystring .= "###";
            $DDstring .= "###";
            $DPosstring .= "###";
            $hLabelstring .= "###";
            $Labelstring .= "###";
            $addkstring .= "###";
            $UTurnXstring .= "###";
            $UTurnYstring .= "###";
        }
        $timestring .= $time[$i];
        $xstring .= $x[$i];
        $ystring .= $y[$i];
        $DDstring .= $DD[$i];
        $DPosstring .= $DPos[$i];
        $hLabelstring .= $hLabel[$i];
        $Labelstring .= $Label[$i];
        //$addkstring .= $addk[$i];
        $UTurnXstring .= $UTurnX[$i];
        $UTurnYstring .= $UTurnY[$i];
        if ($DD[$i] == '2' && !isset($DDdragTime[$hLabel[$i]])) {
            $DDdragTime[$hLabel[$i]] = $time[$i];
        }

        if ($DD[$i] == '2') {
            $label_id = $hLabel[$i];
            // この単語のD&D回数をカウントアップ
            if (!isset($dd_counts[$label_id])) {
                $dd_counts[$label_id] = 0;
            }
            $dd_counts[$label_id]++;

            // イベント情報を配列に追加
            $all_dd_events[] = array(
                'time' => $time[$i],
                'hLabel' => $label_id, // クリックした単語のID
                'labelGroup' => $Label[$i], // グループ化された全単語のID文字列
                'count' => $dd_counts[$label_id]
            );
        }
    }
    $DDdragTime_json = json_encode($DDdragTime);
    $all_dd_events_json = json_encode($all_dd_events);

    // D&Dイベントログからユニークなグループを抽出
    $unique_groups = array();
    if (isset($all_dd_events) && is_array($all_dd_events)) {
        foreach ($all_dd_events as $event) {
            // '#'が含まれていればグループとみなす
            if (strpos($event['labelGroup'], '#') !== false) {
                $unique_groups[$event['labelGroup']] = true;
            }
        }
    }
    ?>
    <script type="text/javascript" src="wz_jsgraphics.js"></script>
    <script type="text/javascript">
        var t = 0;
        var x = 0;
        var y = 0;

        // linedatamouse内の各情報
        var t_point = new Array();
        var x_point = new Array();
        var y_point = new Array();
        var DD_point = new Array();
        var DPos_point = new Array();
        var hLabel_point = new Array();
        var Label_point = new Array();
        var addk_point = new Array();
        var UTurnX_point = new Array();
        var UTurnY_point = new Array();


        // 初期の英単語の並び情報
        var start_point = new Array();
        var tstring = "<?php echo $timestring; ?>";
        var xstring = "<?php echo $xstring; ?>";
        var ystring = "<?php echo $ystring; ?>";
        var DDstring = "<?php echo $DDstring; ?>";
        var DPosstring = "<?php echo $DPosstring; ?>";
        var hLabelstring = "<?php echo $hLabelstring; ?>";
        var Labelstring = "<?php echo $Labelstring; ?>";
        var UTurnXstring = "<?php echo $UTurnXstring; ?>";
        var UTurnYstring = "<?php echo $UTurnYstring; ?>";
        var startstring = "<?php echo isset($start) ? $start : ''; ?>";



        t_point = tstring.split("###");
        x_point = xstring.split("###");
        y_point = ystring.split("###");
        DD_point = DDstring.split("###");
        DPos_point = DPosstring.split("###");
        hLabel_point = hLabelstring.split("###");
        Label_point = Labelstring.split("###");
        UTurnX_point = UTurnXstring.split("###");
        UTurnY_point = UTurnYstring.split("###");
        var DDdragTime = <?php echo $DDdragTime_json; ?>;
        var all_dd_events = <?php echo $all_dd_events_json; ?>;
        UTurnFlag = 0;
        UTurnCount = 0;
        console.log(DDdragTime);
    </script>
    <link rel="stylesheet" href="themes/base/jquery.ui.all.css" />
    <script type="text/javascript" src="jquery-1.8.3.js"></script>
    <script type="text/javascript" src="ui/jquery.ui.core.js"></script>
    <script type="text/javascript" src="ui/jquery.ui.widget.js"></script>
    <script type="text/javascript" src="ui/jquery.ui.mouse.js"></script>
    <script type="text/javascript" src="ui/jquery.ui.slider.js"></script>
</head>

<body>
    <form name="myForm" action="#">
        <div>
            <input type="text" size="20" name="time" disabled>
            <select NAME="speed" SIZE=1>
                <OPTION value=5><?= translate('mousemove.php_492行目_等倍') ?></OPTION>
                <OPTION value=2.5><?= translate('mousemove.php_493行目_0.5倍') ?></OPTION>
                <OPTION value=10><?= translate('mousemove.php_494行目_2倍') ?></OPTION>
                <OPTION value=15><?= translate('mousemove.php_495行目_3倍') ?></OPTION>
                <OPTION value=25><?= translate('mousemove.php_496行目_5倍') ?></OPTION>
            </select>
            <input type="button" value="<?= translate('mousemove.php_497行目_軌跡再現') ?>" name="start" id="start_b"
                onclick="interval(); DrawAline();">
            <input type="button" value="<?= translate('mousemove.php_499行目_一時停止') ?>" name="stop"
                onclick="stop_interval()">
            <input type="button" value="<?= translate('mousemove.php_501行目_リセット') ?>" name="reset" onclick="reset_c()">
            <input type="button" value="リプレイ" name="replay" id="replay_b" onclick="replay_segment()"
                style="display: none;">
            <select name="labelDD" SIZE=1 onchange="updateUIForSelection()">
                <option value="100"><?= translate('すべての単語') ?></option>
                <?php
                $tangoarray = isset($start) ? explode("|", $start) : [];
                // 個別の単語をリストに追加
                foreach ($tangoarray as $i => $word) {
                    if (!empty($word)) {
                        echo '<option value="' . $i . '">' . htmlspecialchars($word, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                }
                // グループ化された単語をリストに追加
                foreach (array_keys($unique_groups) as $group_string) {
                    // ▼▼▼▼▼ このブロックで置き換え ▼▼▼▼▼
                    $group_words = [];
                    // 1. 文字列を '#' で分割する
                    $group_ids_raw = explode('#', $group_string);

                    // 2. 分割後の各IDをチェックし、有効なものだけを処理する
                    foreach ($group_ids_raw as $id) {
                        // IDの前後の空白を削除
                        $trimmed_id = trim($id);
                        // IDが空文字列でないことを確認
                        if ($trimmed_id !== '') {
                            $numeric_id = intval($trimmed_id);
                            if (isset($tangoarray[$numeric_id])) {
                                $group_words[] = $tangoarray[$numeric_id];
                            }
                        }
                    }
                    // ▲▲▲▲▲ ここまで ▲▲▲▲▲
                
                    $display_text = "グループ: [ " . implode(', ', $group_words) . " ]";
                    echo '<option value="' . htmlspecialchars($group_string, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($display_text, ENT_QUOTES, 'UTF-8') . '</option>';
                }
                ?>
            </select>
            <span id="playback-controls" style="visibility: hidden;">
                <label for="playbackDurationInput">D&D後 </label>
                <input type="number" id="playbackDurationInput" value="3" min="1" style="width: 4em;">
                <span> 秒間再生</span>
                <input type="checkbox" id="playToEndCheckbox" onchange="toggleDurationInput()">
                <label for="playToEndCheckbox">最後まで</label>
            </span>
            &nbsp;&nbsp;
            <span id="instance-controls" style="display: none;">
                <label for="dd_instance_select">移動回数: </label>
                <select id="dd_instance_select"></select>
            </span>
        </div>
    </form>

    <div class="wide-container">
        <div style="display: inline-block; vertical-align: middle;">
            <?= translate('mousemove.php_516行目_途中から開始') ?>：<input type="text" id="jquery-ui-slider-value" /> /
            <script type="text/javascript">
                document.write(t_point[t_point.length - 4]);
            </script>ms

            <span class="info-icon">ⓘ
                <span class="info-popup" style="width: 450px; text-align: left; padding: 12px; white-space: normal;">
                    <b style="font-size: 14px; margin-bottom: 8px; display: block;"><?= translate('軌跡再現の操作方法') ?></b>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li style="margin-bottom: 8px;">
                            <b><?= translate('再生/操作') ?>:</b> 「軌跡再現」ボタンで再生します。スライドバーを動かすと、任意の時点への早送りや巻き戻しが可能です。
                        </li>
                        <li style="margin-bottom: 8px;">
                            <b><?= translate('イベント確認') ?>:</b>
                            バー上の黒い縦線は、単語やグループのDrag&Dropが行われた箇所です。マウスカーソルを合わせると、操作された単語名がポップアップで表示されます。
                        </li>
                        <li style="margin-bottom: 8px;">
                            <b><?= translate('特定場面の再生') ?>:</b>
                            ドロップダウンから特定の単語やグループを選択すると、その単語が操作された場面へジャンプし、指定した秒数だけ（または最後まで）再生できます。
                        </li>
                        <li>
                            <b><?= translate('詳細情報') ?>:</b> 学習者の詳細結果は画面下部で確認できます。
                        </li>
                    </ul>
                </span>
            </span>
        </div>

        <div id="slider-container" style="position: relative; padding: 5px 0;">
            <div id="jquery-ui-slider"></div>
            <div id="slider-ticks"></div>
        </div>
        <div id="slider-tooltip"></div>
    </div>
    <br>

    <script type="text/javascript" src="excanvas.js"></script>
    <canvas id="canvas" width="0" height="0" style="visibility:hidden;position:absolute;"></canvas>

    <table border="1" cellspacing="1" width="1000" height="500">
        <tr>
            <td>
                <div id="myCanvasb"></div>
                <div id="myCanvas"></div>
                <div id="myCanvas2"></div>
                <div id="myCanvas2_1"></div>
                <div id="myCanvas2_2"></div>
                <div id="myCanvas2_3"></div>
                <div id="myCanvas2_4"></div>
                <div id="myCanvas3"></div>
                <div id="myCanvas3_1"></div>
                <div id="myCanvas3_2"></div>
                <div id="myCanvas3_3"></div>
                <div id="myCanvas3_4"></div>
                <div id="myCanvas4"></div>
                <div id="myCanvas5"></div>
                <div id="myCanvas6"></div>
                <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
                <br><br><br><br><br><br><br><br><br><br><br><br><br><br>
                <table border="0" width="995" height="200">
                    <tr>
                        <td>
                            <table border="1" cellspacing="1" width="600" height="200">
                                <tr>
                                    <td>
                                        <b><u><?= translate('mousemove.php_534行目_問題番号') ?></u>：<?php echo isset($wid) ? htmlspecialchars($wid, ENT_QUOTES, 'UTF-8') : ''; ?>
                                            <u><?= translate('mousemove.php_535行目_ユーザ番号') ?></u>：<?php echo isset($uid) ? htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') : ''; ?></b>
                                        <br><br>
                                        <b><u><?= translate('mousemove.php_537行目_最終解答文') ?></u>：<?php echo isset($es) ? htmlspecialchars($es, ENT_QUOTES, 'UTF-8') : ''; ?></b><br><br>
                                        <b><u><?= translate('mousemove.php_539行目_日本語文') ?></u>：<?php echo isset($js) ? htmlspecialchars($js, ENT_QUOTES, 'UTF-8') : ''; ?></b><br>
                                        <b><u><?= translate('mousemove.php_540行目_正解文') ?></u>：<?php echo isset($se) ? htmlspecialchars($se, ENT_QUOTES, 'UTF-8') : ''; ?></b><br>
                                        <br>
                                        <b><u><?= translate('学習者の申告迷い度') ?></u>：
                                            <?php
                                            if (isset($us)) {
                                                if ($us == 0) {
                                                    print (translate('mousemove.php_544行目_間違って終了ボタン'));
                                                } elseif ($us == 4) {
                                                    print (translate('mousemove.php_545行目_ほとんど迷わなかった'));
                                                } elseif ($us == 3) {
                                                    print (translate('mousemove.php_546行目_少し迷った'));
                                                } elseif ($us == 2) {
                                                    print (translate('mousemove.php_547行目_かなり迷った'));
                                                }
                                            }
                                            ?>
                                        </b><br>
                                        <b><u><?= translate('迷い推定結果') ?></u>：
                                            <?php
                                            if ($estimated_us === null) {
                                                echo translate('未推定');
                                            } elseif ($estimated_us == 2) {
                                                echo translate('迷い有り');
                                            } elseif ($estimated_us == 4) {
                                                echo translate('迷い無し');
                                            } else {
                                                echo translate('未推定');
                                            }
                                            ?>
                                        </b><br>
                                        <b><u><?= translate('mousemove.php_550行目_正誤') ?></u>：
                                            <?php
                                            if (isset($tf_result) && $tf_result == 1) { // $tf_resultが1なら正解
                                                print ("○");
                                            } else {
                                                print ("×");
                                            }
                                            ?>
                                        </b><br>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table border="1" cellspacing="1" width="380" height="200">
                                <tr>
                                    <td>
                                        <?= translate('mousemove.php_560行目_文法項目') ?>：<?php
                                          if (isset($grammar_print)) {
                                              for ($i = 0; $i < count($grammar_print); $i++) {
                                                  print (htmlspecialchars($grammar_print[$i], ENT_QUOTES, 'UTF-8'));
                                                  print (" ");
                                              }
                                          }
                                          ?>
                                        <br>
                                        <?= translate('mousemove.php_565行目_難易度') ?>：<?php
                                          if (isset($level)) {
                                              if ($level == 1) {
                                                  print (translate('mousemove.php_566行目_初級'));
                                              } else if ($level == 2) {
                                                  print (translate('mousemove.php_567行目_中級'));
                                              } else if ($level == 3) {
                                                  print (translate('mousemove.php_568行目_上級'));
                                              }
                                          }
                                          ?>
                                        <br><br>
                                        <?= translate('mousemove.php_576行目_解答時間') ?>：<?php
                                          if (isset($a_time)) {
                                              echo htmlspecialchars($a_time, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?><?= translate('mousemove.php_578行目_秒') ?><br>
                                        <?= translate('mousemove.php_579行目_DragDrop回数') ?>：<?php
                                          if (isset($dragdropcount)) {
                                              echo htmlspecialchars($dragdropcount, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?><?= translate('mousemove.php_581行目_回') ?><br>
                                        <?= translate('グループ化Drag&Drop回数') ?>：<?php
                                          if (isset($groupcount)) {
                                              echo htmlspecialchars($groupcount, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?><?= translate('mousemove.php_605行目_回') ?><br>
                                        <?= translate('mousemove.php_582行目_Uターン回数X') ?>：<?php
                                          if (isset($uturncount_X)) {
                                              echo htmlspecialchars($uturncount_X, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?><?= translate('mousemove.php_584行目_回') ?><br>
                                        <?= translate('mousemove.php_585行目_Uターン回数Y') ?>：<?php
                                          if (isset($uturncount_Y)) {
                                              echo htmlspecialchars($uturncount_Y, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?><?= translate('mousemove.php_587行目_回') ?><br>
                                        <?= translate('mousemove.php_598行目_最大静止時間') ?>：<?php
                                          if (isset($maxstoptime)) {
                                              echo htmlspecialchars($maxstoptime, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?>s<br>
                                        <?= translate('総合静止時間') ?>：<?php
                                          if (isset($totalStopTime)) {
                                              echo htmlspecialchars($totalStopTime, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?>s<br>
                                        <?= translate('開始から最初の単語を触るまでの時間') ?>：<?php
                                          if (isset($thinkingTime)) {
                                              echo htmlspecialchars($thinkingTime, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?>s<br>
                                        <?= translate('最初の単語を触ってから最後までの時間') ?>：<?php
                                          if (isset($answeringTime)) {
                                              echo htmlspecialchars($answeringTime, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?>s<br>
                                        <?= translate('最大Drag&Drop時間') ?>：<?php
                                          if (isset($maxDDTime)) {
                                              echo htmlspecialchars($maxDDTime, ENT_QUOTES, 'UTF-8');
                                          }
                                          ?>s<br>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <br>
                <table border="1" cellspacing="1" width="600" height="200">
                    <tr>
                        <td>
                            <?php
                            // ▼▼▼▼▼ ここからPHPロジックの修正 ▼▼▼▼▼
                            $diarray = isset($start) ? explode("|", $start) : [];
                            $word_count = array();

                            // 「余分なDD動作」のカウントロジックを修正
                            $sql_DD = "select * from linedatamouse where UID = " . $uid . " and WID = " . $wid . " and attempt = " . $attempt_num . " order by Time;";
                            $res_DD = mysqli_query($conn, $sql_DD) or die("接続エラー");
                            $Array_Flag = 0; // Drag開始時のエリアを保持するフラグ
                            $Label_div_for_wc = array();
                            $Label_for_wc = "";
                            while ($row_DD = mysqli_fetch_array($res_DD)) {
                                if ($row_DD["DD"] == 2) { //Drag時
                                    $Label_for_wc = $row_DD["Label"];
                                    $Label_div_for_wc = explode("#", $Label_for_wc);

                                    // Drag開始エリアを判定・保持
                                    if ($row_DD["Y"] > 130 && $row_DD["Y"] <= 215)
                                        $Array_Flag = 4;      // 解答欄
                                    else if ($row_DD["Y"] > 215 && $row_DD["Y"] <= 295)
                                        $Array_Flag = 1; // レジスタ1
                                    else if ($row_DD["Y"] > 295 && $row_DD["Y"] <= 375)
                                        $Array_Flag = 2; // レジスタ2
                                    else if ($row_DD["Y"] > 375)
                                        $Array_Flag = 3;                       // レジスタ3
                                    else
                                        $Array_Flag = 0;                                              // 問題提示欄
                            
                                } else if ($row_DD["DD"] == 1) { //Drop時
                                    $should_count = false;
                                    $drop_Y = $row_DD["Y"];

                                    // ケース1: 問題提示欄以外から問題提示欄に戻された場合
                                    if ($drop_Y <= 130 && $Array_Flag != 0) {
                                        $should_count = true;
                                    }
                                    // ケース2: 同じ解答欄や同じレジスタ内で移動した場合 (入れ替え)
                                    else if (($drop_Y > 130 && $drop_Y <= 215) && $Array_Flag == 4) {
                                        $should_count = true;
                                    } else if (($drop_Y > 215 && $drop_Y <= 295) && $Array_Flag == 1) {
                                        $should_count = true;
                                    } else if (($drop_Y > 295 && $drop_Y <= 375) && $Array_Flag == 2) {
                                        $should_count = true;
                                    } else if ($drop_Y > 375 && $Array_Flag == 3) {
                                        $should_count = true;
                                    }

                                    if ($should_count) {
                                        foreach ($Label_div_for_wc as $value) {
                                            if (trim($value) !== '') { // 空のIDはカウントしない
                                                if (isset($word_count[$value]))
                                                    $word_count[$value]++;
                                                else
                                                    $word_count[$value] = 1;
                                            }
                                        }
                                    }
                                }
                            }
                            arsort($word_count);

                            // 「入れ替え間時間」の計算ロジック
                            $sql_DD2 = "select * from linedatamouse where UID = " . $uid . " and WID = " . $wid . " and attempt = " . $attempt_num . " order by Time;";
                            $res_DD2 = mysqli_query($conn, $sql_DD2) or die("接続エラー");
                            $DC_Flag = 0;
                            $DC_array = array();
                            $Time_array = array();
                            $WID_array = array();
                            $Label_array = array();
                            $Before_Label_array = array(); // 直前の単語を保存
                            $before_Label = "";
                            $j = 0;
                            while ($row_DD2 = mysqli_fetch_array($res_DD2)) {
                                if ($row_DD2["DD"] == 2) { // Drag時
                                    if ($DC_Flag == 1) {
                                        $Time_array[$j] = $row_DD2["Time"];
                                        $DC_array[$j] = $row_DD2["Time"] - $before_Time;
                                        $WID_array[$j] = $row_DD2["WID"];
                                        $Label_array[$j] = $row_DD2["Label"];
                                        $Before_Label_array[$j] = $before_Label; // Drop時の単語を保存
                                        $j++;
                                    }
                                } else if ($row_DD2["DD"] == 1) { // Drop時
                                    $DC_Flag = 1;
                                    $before_Time = $row_DD2["Time"];
                                    $before_Label = $row_DD2["Label"]; // Dropされた単語のラベルを一時保存
                                } else if ($row_DD2["DD"] == -1) {
                                    $DC_Flag = 0;
                                }
                            }

                            $aveDC_sub = empty($DC_array) ? 0 : ave($DC_array);
                            $sdDC_sub = empty($DC_array) ? 0 : sd($DC_array);
                            $thereshold_2 = 2 * $sdDC_sub + $aveDC_sub;
                            $thereshold_1 = 1 * $sdDC_sub + $aveDC_sub;
                            $thereshold_075 = 0.75 * $sdDC_sub + $aveDC_sub;

                            $key2_array = array();
                            $key1_array = array();
                            $key075_array = array();

                            foreach ($DC_array as $j => $value) {
                                if (isset($WID_array[$j]) && $WID_array[$j] == $wid) {
                                    if ($value >= $thereshold_2) {
                                        $key2_array[] = $j;
                                    }
                                    if ($value >= $thereshold_1 and $value < $thereshold_2) {
                                        $key1_array[] = $j;
                                    }
                                    if ($value >= $thereshold_075 and $value < $thereshold_1) {
                                        $key075_array[] = $j;
                                    }
                                }
                            }

                            // 単語IDを単語名に一括変換
                            for ($j = 0; $j < count($Label_array); $j++) {
                                for ($i = count($diarray) - 1; $i >= 0; $i--) {
                                    $replacement = isset($diarray[$i]) ? $diarray[$i] : '';
                                    if (isset($Label_array[$j])) {
                                        $Label_array[$j] = str_replace($i, $replacement, $Label_array[$j]);
                                    }
                                    if (isset($Before_Label_array[$j])) {
                                        $Before_Label_array[$j] = str_replace($i, $replacement, $Before_Label_array[$j]);
                                    }
                                }
                            }

                            $DC_array2 = array();
                            foreach ($key2_array as $idx) {
                                if (isset($DC_array[$idx])) {
                                    $DC_array2[$idx] = $DC_array[$idx];
                                }
                            }
                            $DC_array1 = array();
                            foreach ($key1_array as $idx) {
                                if (isset($DC_array[$idx])) {
                                    $DC_array1[$idx] = $DC_array[$idx];
                                }
                            }
                            $DC_array075 = array();
                            foreach ($key075_array as $idx) {
                                if (isset($DC_array[$idx])) {
                                    $DC_array075[$idx] = $DC_array[$idx];
                                }
                            }
                            arsort($DC_array2);
                            arsort($DC_array1);
                            arsort($DC_array075);

                            // ▼▼▼▼▼ 「迷い候補リスト」の生成ロジックを修正 ▼▼▼▼▼
                            $raw_hesitation_words = [];
                            foreach ($word_count as $key => $value) {
                                $word_id = (int) $key;
                                if ($value >= 1 && isset($diarray[$word_id])) {
                                    $raw_hesitation_words[] = $diarray[$word_id];
                                }
                            }
                            $all_dc_arrays = $DC_array2 + $DC_array1 + $DC_array075;
                            foreach ($all_dc_arrays as $index => $time) {
                                if (isset($Before_Label_array[$index])) {
                                    $raw_hesitation_words[] = $Before_Label_array[$index];
                                }
                                if (isset($Label_array[$index])) {
                                    $raw_hesitation_words[] = $Label_array[$index];
                                }
                            }

                            $final_word_list = [];
                            foreach ($raw_hesitation_words as $word_entry) {
                                $words = explode(',', str_replace('#', ',', $word_entry));
                                foreach ($words as $word) {
                                    $cleaned_word = trim($word, " \t\n\r\0\x0B,");
                                    if ($cleaned_word !== '') {
                                        $final_word_list[] = $cleaned_word;
                                    }
                                }
                            }
                            $final_word_list = array_unique($final_word_list);
                            // ▲▲▲▲▲ 修正はここまで ▲▲▲▲▲
                            ?>

                            <b><u><?= translate('mousemove.php_1078行目_迷い候補リスト') ?></u></b>
                            <span class="info-icon">ⓘ
                                <span class="info-popup">
                                    以下の二つの項目に該当する単語を「迷った単語」の候補としてリストアップしています。
                                </span>
                            </span>
                            <br>
                            <?php
                            foreach ($final_word_list as $value) {
                                echo htmlspecialchars(str_replace('#', ', ', $value), ENT_QUOTES, 'UTF-8') . "<br>";
                            }
                            ?>
                            <br>
                            <b><u><?= translate('mousemove.php_1107行目_余分なDD動作') ?></u></b>
                            <span class="info-icon">ⓘ
                                <span class="info-popup">
                                    一度、解答欄や退避エリアに置かれた後、再び問題提示エリアに戻された単語や、解答欄や各レジスタ（退避エリア）の中で、順番を入れ替えた単語のリストです。移動回数が多いほど、その単語の配置に迷ったことを示唆します。
                                </span>
                            </span>
                            <br>
                            <?php
                            if (empty($word_count)) {
                                echo "該当なし<br>";
                            } else {
                                $multiple_hits = [];
                                $single_hits = [];
                                foreach ($word_count as $key => $value) {
                                    if ($value >= 2) {
                                        $multiple_hits[$key] = $value;
                                    } elseif ($value == 1) {
                                        $single_hits[$key] = $value;
                                    }
                                }

                                if (!empty($multiple_hits)) {
                                    echo "<u>" . translate('複数回検出') . "</u><br>";
                                    foreach ($multiple_hits as $key => $value) {
                                        // ▼▼▼▼▼ ここを修正 ▼▼▼▼▼
                                        $word_id = (int) $key; // キーを整数に変換
                                        $word_name = isset($diarray[$word_id]) && trim($diarray[$word_id]) !== ''
                                            ? htmlspecialchars($diarray[$word_id], ENT_QUOTES, 'UTF-8')
                                            : "ID:" . htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); // 念のためフォールバック
                                        // ▲▲▲▲▲ 修正はここまで ▲▲▲▲▲
                                        echo "検出単語: " . $word_name
                                            . " (" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                                            . translate('mousemove.php_1117行目_回') . ")<br>";
                                    }
                                }

                                if (!empty($single_hits)) {
                                    echo "<u>" . translate('1回検出') . "</u><br>";
                                    foreach ($single_hits as $key => $value) {
                                        // ▼▼▼▼▼ ここを修正 ▼▼▼▼▼
                                        $word_id = (int) $key; // キーを整数に変換
                                        $word_name = isset($diarray[$word_id]) && trim($diarray[$word_id]) !== ''
                                            ? htmlspecialchars($diarray[$word_id], ENT_QUOTES, 'UTF-8')
                                            : "ID:" . htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); // 念のためフォールバック
                                        // ▲▲▲▲▲ 修正はここまで ▲▲▲▲▲
                                        echo "検出単語: " . $word_name . "<br>";
                                    }
                                }
                            }
                            ?>
                            <br>
                            <b><u><?= translate('mousemove.php_1128行目_入れ替え間時間') ?></u></b>
                            <span class="info-icon">ⓘ
                                <span class="info-popup">
                                    ある単語を配置(Drop)してから、次に別の単語を掴む(Drag)までの時間を計測し、特に長かったものをリストアップしています。<br>(表記：→次に掴んだ単語 :
                                    掴むまでの時間) <br>時間が長いほど、次の操作に迷った可能性を示します。この問題解答時における解答データを元に、入れ替え間の平均時間などを計算しています。
                                </span>
                            </span>
                            <br>
                            <u>特に長い時間の単語 (平均 + 標準偏差×2以上に該当)</u><br>
                            <?php
                            foreach ($DC_array2 as $current_index => $value) {
                                $curr_label_text = isset($Label_array[$current_index]) ? str_replace('#', ', ', $Label_array[$current_index]) : 'N/A';
                                $time_s = number_format($value / 1000, 2);
                                echo " → " . htmlspecialchars($curr_label_text, ENT_QUOTES, 'UTF-8') . "： " . htmlspecialchars($time_s, ENT_QUOTES, 'UTF-8') . "秒<br>";
                            }
                            ?>
                            <u>やや長い時間の単語 (平均 + 標準偏差×1以上に該当)</u><br>
                            <?php
                            foreach ($DC_array1 as $current_index => $value) {
                                $curr_label_text = isset($Label_array[$current_index]) ? str_replace('#', ', ', $Label_array[$current_index]) : 'N/A';
                                $time_s = number_format($value / 1000, 2);
                                echo " → " . htmlspecialchars($curr_label_text, ENT_QUOTES, 'UTF-8') . "： " . htmlspecialchars($time_s, ENT_QUOTES, 'UTF-8') . "秒<br>";
                            }
                            ?>
                            <u>少し長い時間の単語 (平均 + 標準偏差×0.75以上に該当)</u><br>
                            <?php
                            foreach ($DC_array075 as $current_index => $value) {
                                $curr_label_text = isset($Label_array[$current_index]) ? str_replace('#', ', ', $Label_array[$current_index]) : 'N/A';
                                $time_s = number_format($value / 1000, 2);
                                echo " → " . htmlspecialchars($curr_label_text, ENT_QUOTES, 'UTF-8') . "： " . htmlspecialchars($time_s, ENT_QUOTES, 'UTF-8') . "秒<br>";
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <script type="text/javascript">
        // 初期の英単語の並び情報を|で区切って入れる
        start_point = startstring.split("|");
        start_point_x = new Array();
        start_point_w = new Array();
        df_x = 30;
        wd = 0;
        for (l = 0; l < start_point.length; l++) {
            //単語の位置を記録
            start_point_x[l] = df_x;
            //単語の長さ取得
            wd = strWidth(start_point[l]);
            start_point_w[l] = wd;
            df_x = df_x + wd + 18;
        }
        var m = 0;
        var timer1;
        //単語のそれぞれの位置を記録しておく。単語をドロップする際に使用
        var Label_x = new Array();
        //グループ化の場合分け
        var grouptest = new Array();
        //現在の単語の並び順
        var now_list = new Array();
        //各配列・各座標用
        //問題提示欄
        var list_q = new Array();
        var Label_q = new Array();
        //最終解答欄
        var list_a = new Array();
        var Label_a = new Array();
        //レジスタ1
        var list_r1 = new Array();
        var Label_r1 = new Array();
        //レジスタ1
        var list_r2 = new Array();
        var Label_r2 = new Array();
        //レジスタ1
        var list_r3 = new Array();
        var Label_r3 = new Array();
        //キャンバス受け渡し用
        var jg_canvas = 0;
        //キャンバス受け渡し用
        var jg_canvas2 = 0;
        //イベントがどこで起こっているか？　0:問題提示欄 1:レジスタ1 2:レジスタ2 3:レジスタ3 4:最終解答欄
        var md_flag = -1;
        //最初だけリスト全表示したいのでそのためのフラグ
        var start_st = 0;
        //ここをまず問題提示欄に変える？？
        //ロードイベントと同時にできないかな。最初(ロードした時)だけフラグ立てておいて
        //文字出力するときにif文で分ける。出力したらフラグを戻す。
        for (l = 0; l < start_point.length; l++) {
            //単語の数だけ取得
            list_q[l] = l;
        }
        //初期配置記録用
        var start_t = start_point.concat();
        //Labelで指定された単語は現在何番目にあるのか記録
        var t_word = 0;
        //変換用
        var parse_t = 0;
        //単語（群）一時退避用
        var word = new Array();
        //単語移動フラグ
        var wordmove = 0;
        //スライダー用
        var slider = 0;
        // リプレイ機能用の変数
        var lastPlaybackStartTime = 0;
        var lastPlaybackDuration = 0;
        // D&D後の再生停止時間（ミリ秒）。-1の場合は最後まで再生
        var playbackStopTime = -1;
        //ドラッグした単語の順番
        var DDdragTimetemp = 0;
        //単語の長さを測る。IEだと上手く動いてくれない。
        function strWidth(str) {
            var canvas = document.getElementById('canvas');
            if (canvas && canvas.getContext) {
                var context = canvas.getContext('2d');
                context.font = "16px 'arial'";
                var metrics = context.measureText(str);
                return metrics.width;
            }
            return str.length * 8; // Fallback for browsers that don't support canvas
        }
        //キャンバスに単語を配置する。
        function DrawString() {
            //フラグによって各配列の中身をnow_listにぶち込み
            if (md_flag == 0) {
                now_list = list_q.slice(0);
                Label_x = Label_q.slice(0);
                console.log(now_list);
                console.log(Label_x);
                var string_x = 30;
                var string_y = 100;
                jg_canvas = jg3
                jg_canvas2 = jg2
            } else if (md_flag == 1) {
                now_list = list_r1.slice(0);
                Label_x = Label_r1.slice(0);
                var string_x = 30;
                var string_y = 250;
                jg_canvas = jg3_1
                jg_canvas2 = jg2_1
            } else if (md_flag == 2) {
                now_list = list_r2.slice(0);
                Label_x = Label_r2.slice(0);
                var string_x = 30;
                var string_y = 330;
                jg_canvas = jg3_2
                jg_canvas2 = jg2_2
            } else if (md_flag == 3) {
                now_list = list_r3.slice(0);
                Label_x = Label_r3.slice(0);
                var string_x = 30;
                var string_y = 410;
                jg_canvas = jg3_3
                jg_canvas2 = jg2_3
            } else if (md_flag == 4) {
                now_list = list_a.slice(0);
                Label_x = Label_a.slice(0);
                var string_x = 30;
                var string_y = 170;
                jg_canvas = jg3_4
                jg_canvas2 = jg2_4
            }

            jg_canvas2.clear();
            jg_canvas.clear();
            if (md_flag != 0) {
                var l = 0;
                //単語の長さ。x座標に足していく。
                var w_width = 0;
                for (l = 0; l < now_list.length; l++) {
                    //単語のフォント設定
                    jg_canvas.setFont("arial", "16px", Font.Plain);
                    //単語の出力
                    jg_canvas.drawString(start_point[now_list[l]], string_x, string_y);
                    jg_canvas.paint();
                    //単語の位置を記録
                    Label_x[l] = string_x;
                    //単語の長さ取得
                    w_width = strWidth(start_point[now_list[l]]);
                    //背景を付ける
                    jg_canvas2.setColor("white");
                    jg_canvas2.fillRect(string_x, string_y, w_width, 20);
                    jg_canvas2.paint();
                    string_x = string_x + w_width + 18;
                }
            } else {
                for (l = 0; l < now_list.length; l++) {
                    //単語のフォント設定
                    jg_canvas.setFont("arial", "16px", Font.Plain);
                    //単語の出力
                    jg_canvas.drawString(start_point[now_list[l]], start_point_x[now_list[l]], string_y);
                    jg_canvas.paint();
                    jg_canvas2.setColor("white");
                    jg_canvas2.fillRect(start_point_x[now_list[l]], string_y, start_point_w[now_list[l]], 20);
                    jg_canvas2.paint();
                }
            }
            //フラグによってnow_listをぶち込み
            if (md_flag == 0) {
                list_q = now_list.slice(0);
                Label_q = Label_x.slice(0);
            } else if (md_flag == 1) {
                list_r1 = now_list.slice(0);
                Label_r1 = Label_x.slice(0);
            } else if (md_flag == 2) {
                list_r2 = now_list.slice(0);
                Label_r2 = Label_x.slice(0);
            } else if (md_flag == 3) {
                list_r3 = now_list.slice(0);
                Label_r3 = Label_x.slice(0);
            } else if (md_flag == 4) {
                list_a = now_list.slice(0);
                Label_a = Label_x.slice(0);
            }
        }

        //リセット
        function reset_c() {
            t = 0;
            m = 0;
            playbackStopTime = -1;
            document.myForm.time.value = "";
            stop_interval();

            document.getElementById('playback-controls').style.visibility = 'hidden';
            document.getElementById('instance-controls').style.display = 'none'; // 移動回数UIを隠す
            document.myForm.labelDD.value = '100'; // ドロップダウンを「------」に戻す
            document.getElementById('replay_b').style.display = 'none'; // リプレイボタンを隠す

            jg_b.clear();
            jg.clear();
            jg2.clear();
            jg2_1.clear();
            jg2_2.clear();
            jg2_3.clear();
            jg2_4.clear();
            jg3.clear();
            jg3_1.clear();
            jg3_2.clear();
            jg3_3.clear();
            jg3_4.clear();
            jg4.clear();
            jg5.clear();
            jg6.clear();
            start_point.length = 0;
            now_list.length = 0;
            start_point = startstring.split("|");
            word.length = 0;
            list_q.length = 0;
            list_a.length = 0;
            list_r1.length = 0;
            list_r2.length = 0;
            list_r3.length = 0;
            Label_q.length = 0;
            Label_a.length = 0;
            Label_r1.length = 0;
            Label_r2.length = 0;
            Label_r3.length = 0;
            for (l = 0; l < start_point.length; l++) {
                list_q[l] = l;
            }
            document.getElementById("start_b").style.visibility = "visible";
            document.getElementById("jquery-ui-slider").style.visibility = "visible";
        }

        /**
         * 単語/グループの選択に応じて、再生時間や移動回数のUIを更新する関数
         */
        function updateUIForSelection() {
            var selection = document.myForm.labelDD.value;
            var playbackControls = document.getElementById('playback-controls');
            var instanceControls = document.getElementById('instance-controls');
            var instanceSelect = document.getElementById('dd_instance_select');

            // UIを初期状態にリセット
            instanceSelect.innerHTML = '';
            instanceControls.style.display = 'none';
            playbackControls.style.visibility = 'hidden';

            // 「------」が選択された場合は処理を終了
            if (selection === '100') {
                return;
            }

            // 選択された単語/グループに一致するD&Dイベントを全て検索
            var matchingEvents = all_dd_events.filter(function (event) {
                if (selection.includes('#')) {
                    // グループが選択された場合
                    return event.labelGroup === selection;
                } else {
                    // 個別の単語が選択された場合
                    return event.hLabel === selection;
                }
            });

            // 一致するイベントが1つでもあれば、再生時間UIを表示
            if (matchingEvents.length > 0) {
                playbackControls.style.visibility = 'visible';
            }

            // 一致するイベントが複数ある場合は、移動回数選択UIを表示
            if (matchingEvents.length > 1) {
                instanceControls.style.display = 'inline';
                matchingEvents.forEach(function (event, index) {
                    var option = document.createElement('option');
                    option.value = event.time; // valueにイベント発生時間を設定
                    option.textContent = (index + 1) + '回目 (' + event.time + 'ms)';
                    instanceSelect.appendChild(option);
                });
            }
        }

        /**
         * 「最後まで」チェックボックスの状態に応じて、秒数入力欄を無効化/有効化する関数
         */
        function toggleDurationInput() {
            var checkbox = document.getElementById('playToEndCheckbox');
            var numberInput = document.getElementById('playbackDurationInput');
            numberInput.disabled = checkbox.checked;
        }

        /**
         * 直前の単語スキップ＆指定秒数再生を繰り返す関数
         */
        function replay_segment() {
            // 再生状態を、前回の再生開始時間まで巻き戻す
            renderStateAtTime(lastPlaybackStartTime);

            // 再生停止時間を再設定
            if (lastPlaybackDuration > 0) {
                playbackStopTime = lastPlaybackStartTime + lastPlaybackDuration;
            } else {
                playbackStopTime = -1; // 最後まで再生
            }

            // ボタンの状態を制御
            document.getElementById("start_b").style.visibility = "hidden";
            document.getElementById("replay_b").style.display = "none";

            // 再生開始
            timer1 = setInterval(timer, 5);
        }

        //インターバル開始
        function interval() {
            document.getElementById("start_b").style.visibility = "hidden";
            // ▼▼▼▼▼ ここから追加 ▼▼▼▼▼
            document.getElementById("replay_b").style.display = "none"; // リプレイボタンを隠す
            lastPlaybackDuration = 0; // リプレイ情報をリセット
            // ▲▲▲▲▲ ここまで追加 ▲▲▲▲▲
            md_flag = 0;
            DrawString();

            var labelDDvalue = document.myForm.labelDD.value;
            var labelDDTime = 0;

            if (labelDDvalue !== '100') {
                var instanceControls = document.getElementById('instance-controls');
                var instanceSelect = document.getElementById('dd_instance_select');

                // 移動回数UIが表示されているかチェック
                if (instanceControls.style.display !== 'none' && instanceSelect.value) {
                    // 表示されていれば、選択された回数の時間をジャンプ先とする
                    labelDDTime = parseInt(instanceSelect.value, 10);
                } else {
                    // 表示されていなければ、最初に見つかったD&Dイベントの時間をジャンプ先とする
                    var firstMatch = all_dd_events.find(function (event) {
                        return (labelDDvalue.includes('#')) ? (event.labelGroup === labelDDvalue) : (event.hLabel === labelDDvalue);
                    });
                    if (firstMatch) {
                        labelDDTime = firstMatch.time;
                    }
                }
                var playToEnd = document.getElementById('playToEndCheckbox').checked;

                if (playToEnd) {
                    // 「最後まで再生」が選択された場合
                    playbackStopTime = -1;
                    lastPlaybackDuration = -1; // リプレイ用に「最後まで」を記録
                } else {
                    // 秒数が指定された場合
                    var durationSec = parseInt(document.getElementById('playbackDurationInput').value, 10);
                    if (!isNaN(durationSec) && durationSec > 0 && !isNaN(labelDDTime)) {
                        playbackStopTime = labelDDTime + (durationSec * 1000);
                        lastPlaybackDuration = durationSec * 1000; // リプレイ用に秒数を記録
                    } else {
                        playbackStopTime = -1;
                    }
                }
                lastPlaybackStartTime = labelDDTime; // リプレイ用に開始時間を記録
            } else {
                playbackStopTime = -1;
            }

            console.log("labelDDvalue" + labelDDvalue);
            console.log("labelDDTime is " + labelDDTime);
            console.log("slider is " + slider);

            /*
            for (n = t; n < slider; n++) {
                t = t + 1;
                if (m + 1 < t_point.length && t == t_point[m + 1]) {
                    grouptest = Label_point[m + 1].split("#");
                    //グループ化されていない場合
                    if (DD_point[m + 1] == 2 && grouptest[1] == undefined) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130) {
                            md_flag = 0;
                        } else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130) {
                            md_flag = 4;
                        } else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215) {
                            md_flag = 1;
                        } else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295) {
                            md_flag = 2;
                        } else if (parseInt(y_point[m + 1]) > 375) {
                            md_flag = 3;
                        }
                        WordDrag();
                        wordmove = 1;
                    }

                    //グループ化された場合(複数選択されている場合)
                    if (DD_point[m + 1] == 2 && grouptest[1] != undefined) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130) {
                            md_flag = 0;
                        } else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130) {
                            md_flag = 4;
                        } else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215) {
                            md_flag = 1;
                        } else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295) {
                            md_flag = 2;
                        } else if (parseInt(y_point[m + 1]) > 375) {
                            md_flag = 3;
                        }
                        WordGroup();
                        wordmove = 1;
                    }

                    //ドラッグ＆ドロップが行われた場合に、単語の並び順等を変更
                    if (DD_point[m + 1] == 1) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130) {
                            md_flag = 0;
                        } else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130) {
                            md_flag = 4;
                        } else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215) {
                            md_flag = 1;
                        } else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295) {
                            md_flag = 2;
                        } else if (parseInt(y_point[m + 1]) > 375) {
                            md_flag = 3;
                        }
                        WordDrop();
                        wordmove = 0;
                        jg4.clear();
                        jg5.clear();

                    }
                    m = m + 1;
                    //バグ対策
                    if (m + 1 < t_point.length && parseInt(t_point[m]) == parseInt(t_point[m + 1])) {
                        m = m + 1;
                    }
                }
            }
            */

            if (labelDDTime != 0 && slider == 0) {
                for (n = t; n < labelDDTime; n++) {
                    t = t + 1;
                    if (m + 1 < t_point.length && t == t_point[m + 1]) {
                        grouptest = Label_point[m + 1].split("#");
                        //グループ化されていない場合
                        if (DD_point[m + 1] == 2 && grouptest[1] == undefined) {
                            //ここでイベントが起こった場所によって場合分け
                            if (parseInt(y_point[m + 1]) <= 130) {
                                md_flag = 0;
                            } else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130) {
                                md_flag = 4;
                            } else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215) {
                                md_flag = 1;
                            } else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295) {
                                md_flag = 2;
                            } else if (parseInt(y_point[m + 1]) > 375) {
                                md_flag = 3;
                            }
                            WordDrag();
                            wordmove = 1;
                        }

                        //グループ化された場合(複数選択されている場合)
                        if (DD_point[m + 1] == 2 && grouptest[1] != undefined) {
                            //ここでイベントが起こった場所によって場合分け
                            if (parseInt(y_point[m + 1]) <= 130) {
                                md_flag = 0;
                            } else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130) {
                                md_flag = 4;
                            } else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215) {
                                md_flag = 1;
                            } else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295) {
                                md_flag = 2;
                            } else if (parseInt(y_point[m + 1]) > 375) {
                                md_flag = 3;
                            }
                            WordGroup();
                            wordmove = 1;
                        }

                        //ドラッグ＆ドロップが行われた場合に、単語の並び順等を変更
                        if (DD_point[m + 1] == 1) {
                            //ここでイベントが起こった場所によって場合分け
                            if (parseInt(y_point[m + 1]) <= 130) {
                                md_flag = 0;
                            } else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130) {
                                md_flag = 4;
                            } else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215) {
                                md_flag = 1;
                            } else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295) {
                                md_flag = 2;
                            } else if (parseInt(y_point[m + 1]) > 375) {
                                md_flag = 3;
                            }
                            WordDrop();
                            wordmove = 0;
                            jg4.clear();
                            jg5.clear();

                        }
                        m = m + 1;
                        //バグ対策
                        if (m + 1 < t_point.length && parseInt(t_point[m]) == parseInt(t_point[m + 1])) {
                            m = m + 1;
                        }
                    }
                }
            }

            timer1 = setInterval(timer, 5);
        }

        //インターバル中止
        function stop_interval() {
            clearInterval(timer1);
            document.getElementById("start_b").style.visibility = "visible";
            // 直前が指定秒数再生だった場合のみリプレイボタンを表示
            if (lastPlaybackDuration > 0) {
                document.getElementById('replay_b').style.display = 'inline';
            }
            $("#jquery-ui-slider").slider("value", t); // ← 画面表示(time.value)ではなく、変数tを参照するように変更
        }

        //タイマーに合わせて描画
        function timer() {
            var indexof = 0;
            var dummy = new Array();
            var splice_num = 0;
            var j = 0;
            var k = document.myForm.speed.value;
            for (j = 0; j < k; j++) {
                t = t + 1;

                if (playbackStopTime != -1 && t >= playbackStopTime) {
                    alert("指定時間の再生が終了しました。");
                    stop_interval(); // 再生を停止
                    return; // 関数を抜ける
                }
                document.myForm.time.value = t;
                $("#jquery-ui-slider").slider("value", t); // 連動するけど重すぎる

                if (m + 1 < t_point.length && t == t_point[m + 1]) {
                    grouptest = Label_point[m + 1].split("#");
                    //グループ化されていない場合
                    if (DD_point[m + 1] == 2 && grouptest[1] == undefined) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130) {
                            md_flag = 0;
                        } else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130) {
                            md_flag = 4;
                        } else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215) {
                            md_flag = 1;
                        } else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295) {
                            md_flag = 2;
                        } else if (parseInt(y_point[m + 1]) > 375) {
                            md_flag = 3;
                        }
                        WordDrag();
                        wordmove = 1;
                    }

                    //グループ化された場合(複数選択されている場合)
                    if (DD_point[m + 1] == 2 && grouptest[1] != undefined) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130) {
                            md_flag = 0;
                        } else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130) {
                            md_flag = 4;
                        } else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215) {
                            md_flag = 1;
                        } else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295) {
                            md_flag = 2;
                        } else if (parseInt(y_point[m + 1]) > 375) {
                            md_flag = 3;
                        }
                        WordGroup();
                        wordmove = 1;
                    }

                    //ドラッグ＆ドロップが行われた場合に、単語の並び順等を変更
                    if (DD_point[m + 1] == 1) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130) {
                            md_flag = 0;
                        } else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130) {
                            md_flag = 4;
                        } else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215) {
                            md_flag = 1;
                        } else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295) {
                            md_flag = 2;
                        } else if (parseInt(y_point[m + 1]) > 375) {
                            md_flag = 3;
                        }
                        WordDrop();
                        wordmove = 0;
                        jg4.clear();
                        jg5.clear();
                    }
                    DrawLine();
                }
            }
            if (m + 1 >= t_point.length) {
                alert("再現終了");
                UTurnCount = 0;
                reset_c();
            }
        }
        //ドラッグ処理
        function WordDrag() {
            //三木さんすいません
            if (list_q.indexOf(parseInt(hLabel_point[m + 1])) != -1) {
                md_flag = 0;
            }
            if (list_r1.indexOf(parseInt(hLabel_point[m + 1])) != -1) {
                md_flag = 1;
            }
            if (list_r2.indexOf(parseInt(hLabel_point[m + 1])) != -1) {
                md_flag = 2;
            }
            if (list_r3.indexOf(parseInt(hLabel_point[m + 1])) != -1) {
                md_flag = 3;
            }
            if (list_a.indexOf(parseInt(hLabel_point[m + 1])) != -1) {
                md_flag = 4;
            }
            //フラグによって各配列の中身をnow_listにぶち込み
            if (md_flag == 0) {
                now_list = list_q.slice(0);
            } else if (md_flag == 1) {
                now_list = list_r1.slice(0);
            } else if (md_flag == 2) {
                now_list = list_r2.slice(0);
            } else if (md_flag == 3) {
                now_list = list_r3.slice(0);
            } else if (md_flag == 4) {
                now_list = list_a.slice(0);
            }
            //now_list(現在の並び順)の中で、hLabel_point[m+1]の値は何番目にあるのか調べて、t_wordに入れる
            t_word = now_list.indexOf(parseInt(hLabel_point[m + 1]));
            /*console.log(t_word);*/
            //ドラッグ中の単語を退避させておく
            parse_t = parseInt(t_word);
            console.log("parse_t is" + parse_t)
            //word[0]の中にどの単語であるかが含まれている．
            word[0] = now_list[parse_t];
            console.log("word[0] is " + word[0])

            //ドラッグ中の単語の消去
            now_list.splice(parse_t, 1);
            //フラグによってnow_listにぶち込み
            if (md_flag == 0) {
                list_q = now_list.slice(0);
            } else if (md_flag == 1) {
                list_r1 = now_list.slice(0);
            } else if (md_flag == 2) {
                list_r2 = now_list.slice(0);
            } else if (md_flag == 3) {
                list_r3 = now_list.slice(0);
            } else if (md_flag == 4) {
                list_a = now_list.slice(0);
            }
            DrawString();
        }

        //グループ化処理
        function WordGroup() {
            //フラグによって各配列の中身をnow_listにぶち込み
            if (md_flag == 0) {
                now_list = list_q.slice(0);
            } else if (md_flag == 1) {
                now_list = list_r1.slice(0);
            } else if (md_flag == 2) {
                now_list = list_r2.slice(0);
            } else if (md_flag == 3) {
                now_list = list_r3.slice(0);
            } else if (md_flag == 4) {
                now_list = list_a.slice(0);
            }
            word = Label_point[m + 1].split("#");
            //ドラッグ処理を単語数分繰り返すだけ
            for (i = 0; i < word.length; i++) {
                t_word = now_list.indexOf(parseInt(grouptest[i]));
                parse_t = parseInt(t_word);
                word[i] = now_list[parse_t];
                now_list.splice(parse_t, 1);
            }
            //フラグによってnow_listにぶち込み
            if (md_flag == 0) {
                list_q = now_list.slice(0);
            } else if (md_flag == 1) {
                list_r1 = now_list.slice(0);
            } else if (md_flag == 2) {
                list_r2 = now_list.slice(0);
            } else if (md_flag == 3) {
                list_r3 = now_list.slice(0);
            } else if (md_flag == 4) {
                list_a = now_list.slice(0);
            }
            DrawString();
        }

        //単語をどこにドロップするか見る
        function WordDrop() {
            //フラグによって各配列の中身をnow_listにぶち込み
            if (md_flag == 0) {
                now_list = list_q.slice(0);
                Label_x = Label_q.slice(0);
            } else if (md_flag == 1) {
                now_list = list_r1.slice(0);
                Label_x = Label_r1.slice(0);
            } else if (md_flag == 2) {
                now_list = list_r2.slice(0);
                Label_x = Label_r2.slice(0);
            } else if (md_flag == 3) {
                now_list = list_r3.slice(0);
                Label_x = Label_r3.slice(0);
            } else if (md_flag == 4) {
                now_list = list_a.slice(0);
                Label_x = Label_a.slice(0);
            }

            // 各変数のやくわりこーなー
            // x_point[m+1]:今まさにドロップが行われた場所
            // now_list[]:現時点での(単語持ってかれてるから最初よりは少なくﾅｯﾃｲﾙﾖ)単語の並び順
            // word[]:今退避させてある単語(群)(ドラッグ中の単語)
            // Label_x[]:現時点での単語の並び順における、各単語のx座標

            // 作業用配列
            var tmplist = new Array();
            tmplist = now_list; //　一時的に作業用へ退避　この配列を処理に使用する
            if (now_list[0] == undefined || md_flag == 0) {
                for (j = 0; j < word.length; j++) {
                    tmplist.splice(0 + j, 0, word[j]);
                }
            } else {
                // 1番左端だったときの検査
                if (parseInt(x_point[m + 1]) <= Label_x[0]) {
                    for (j = 0; j < word.length; j++) {
                        tmplist.splice(0 + j, 0, word[j]);
                    }
                }
                // 2番目～最後から2番目までの検査
                for (i = 0; i < (tmplist.length - 1); i++) {
                    if (parseInt(x_point[m + 1]) > Label_x[i] && parseInt(x_point[m + 1]) <= Label_x[i + 1]) {
                        for (j = 0; j < word.length; j++) {
                            tmplist.splice(i + 1 + j, 0, word[j]);
                        }
                    }
                }
                // 右端の検査
                if (parseInt(x_point[m + 1]) > Label_x[tmplist.length - 1]) {
                    for (j = 0; j < word.length; j++) {
                        tmplist.splice(tmplist.length + j, 0, word[j]);
                    }
                }
            }
            now_list = tmplist;
            //フラグによってnow_listにぶち込み
            if (md_flag == 0) {
                list_q = now_list.slice(0);
            } else if (md_flag == 1) {
                list_r1 = now_list.slice(0);
            } else if (md_flag == 2) {
                list_r2 = now_list.slice(0);
            } else if (md_flag == 3) {
                list_r3 = now_list.slice(0);
            } else if (md_flag == 4) {
                list_a = now_list.slice(0);
            }
            DrawString();
            word.length = 0;
        }


        //線を描画
        function DrawLine() {
            jg6.clear();

            if (t % 50000 <= 10000) {
                jg.setColor("pink");
            } else if (t % 50000 > 10000 && t % 50000 <= 20000) {
                jg.setColor("blue");
            } else if (t % 50000 > 20000 && t % 50000 <= 30000) {
                jg.setColor("orange");
            } else if (t % 50000 > 30000 && t % 50000 <= 40000) {
                jg.setColor("green");
            } else if (t % 50000 > 40000 && t % 50000 <= 50000) {
                jg.setColor("red");
            }
            //else if (t%50000 > 50000)               { jg.setColor("black"); }



            //int型に変換
            var x1 = parseInt(x_point[m]);
            var y1 = parseInt(y_point[m]);
            var x2 = parseInt(x_point[m + 1]);
            var y2 = parseInt(y_point[m + 1]);
            var xu = parseInt(UTurnX_point[m]);
            var yu = parseInt(UTurnY_point[m]);
            jg.drawLine(x1, y1, x2, y2);
            /*ｘ軸Uターン：☆、Y軸Uターン:★、どっちも：■、どっちもない：○*/
            if (yu == 1 && xu == 1) {
                jg.fillRect(x1, y1, 10, 10);
            } else if (xu == 1 && yu != 1) {
                jg.drawString("☆", x1 - 5, y1 - 5);
            } else if (xu != 1 && yu == 1) {
                jg.drawString("★", x1 - 5, y1 - 5);
            } else {
                jg.drawEllipse(x1, y1 - 2, 4, 4);
            }
            jg.paint();
            //マウスポインター
            jg6.drawImage("pointer001.png", x2, y2, 10, 18);
            jg6.paint();
            if (wordmove == 1) {
                WordMove();
            }
            m = m + 1;
            //バグ対策
            if (parseInt(t_point[m]) == parseInt(t_point[m + 1])) {
                m = m + 1;
            }
        }

        function DrawAline() {
            //決定ボタン
            jg_b.drawImage("kettei.png", 760, 30, 78, 35);
            //問題提示欄
            jg_b.drawRect(12, 80, 700, 40);
            //最終解答欄
            jg_b.drawRect(12, 150, 700, 40);
            //レジスタ3つ
            jg_b.drawRect(12, 240, 500, 30);
            jg_b.drawRect(12, 320, 500, 30);
            jg_b.drawRect(12, 400, 500, 30);
            jg_b.paint();
        }

        function WordMove() {
            jg4.clear();
            jg5.clear();
            var n = 0;
            //単語初期位置
            var w_string_x = parseInt(x_point[m + 1]);
            var w_string_y = parseInt(y_point[m + 1]) - 10;
            //単語の長さ。x座標に足していく。
            var w_width_2 = 0;
            for (n = 0; n < word.length; n++) {
                //単語のフォント設定
                jg5.setFont("arial", "16px", Font.Plain);
                //単語の出力
                jg5.drawString(start_point[word[n]], w_string_x, w_string_y);
                jg5.paint();
                //単語の長さ取得。足す。
                w_width_2 = strWidth(start_point[word[n]]);
                jg4.setColor("yellow");
                jg4.fillRect(w_string_x, w_string_y, w_width_2, 20);
                jg4.paint();
                w_string_x = w_string_x + w_width_2 + 17;
            }
        }

        // ▼▼▼▼▼ ここから追加 ▼▼▼▼▼
        /**
         * 指定された時間の状態を再計算して描画する関数
         * @param {number} targetTime - 再描画したい時間 (ミリ秒)
         */
        function renderStateAtTime(targetTime) {
            // 1. 動いている場合は停止し、画面と変数を完全にリセット
            stop_interval();
            reset_c();

            // 2. 指定時間までイベント処理を（描画なしで）一気に進める
            while (m + 1 < t_point.length && parseInt(t_point[m + 1]) <= targetTime) {
                // イベントが発生したY座標に基づいて、どのエリアでの操作かを判断するフラグを設定
                var current_y = parseInt(y_point[m + 1]);
                if (current_y <= 130) {
                    md_flag = 0; // 問題提示欄
                } else if (current_y > 130 && current_y <= 215) {
                    md_flag = 4; // 最終解答欄
                } else if (current_y > 215 && current_y <= 295) {
                    md_flag = 1; // レジスタ1
                } else if (current_y > 295 && current_y <= 375) {
                    md_flag = 2; // レジスタ2
                } else if (current_y > 375) {
                    md_flag = 3; // レジスタ3
                }
                // timer()関数内のイベント処理ロジックを流用
                grouptest = Label_point[m + 1].split("#");
                // グループ化されていない場合
                if (DD_point[m + 1] == 2 && grouptest[1] == undefined) {
                    WordDrag();
                    wordmove = 1;
                }
                // グループ化された場合
                if (DD_point[m + 1] == 2 && grouptest[1] != undefined) {
                    WordGroup();
                    wordmove = 1;
                }
                // ドロップされた場合
                if (DD_point[m + 1] == 1) {
                    WordDrop();
                    wordmove = 0;
                }
                m = m + 1;
                // 同じ時間のデータが連続する場合のスキップ処理
                while (m + 1 < t_point.length && parseInt(t_point[m]) == parseInt(t_point[m + 1])) {
                    m = m + 1;
                }
            }

            // 3. 計算後の最終的な単語の位置を描画
            DrawAline(); // 背景の枠線を描画
            md_flag = 0;
            DrawString();
            md_flag = 1;
            DrawString();
            md_flag = 2;
            DrawString();
            md_flag = 3;
            DrawString();
            md_flag = 4;
            DrawString();

            // 4. 最初から指定時間までのマウス軌跡を一気に描画
            jg.clear(); // 軌跡キャンバスをクリア
            for (var i = 0; i < m; i++) {
                // DrawLine()のロジックを流用
                if (parseInt(t_point[i]) % 50000 <= 10000) {
                    jg.setColor("pink");
                } else if (parseInt(t_point[i]) % 50000 > 10000 && parseInt(t_point[i]) % 50000 <= 20000) {
                    jg.setColor("blue");
                } else if (parseInt(t_point[i]) % 50000 > 20000 && parseInt(t_point[i]) % 50000 <= 30000) {
                    jg.setColor("orange");
                } else if (parseInt(t_point[i]) % 50000 > 30000 && parseInt(t_point[i]) % 50000 <= 40000) {
                    jg.setColor("green");
                } else if (parseInt(t_point[i]) % 50000 > 40000 && parseInt(t_point[i]) % 50000 <= 50000) {
                    jg.setColor("red");
                }

                jg.drawLine(parseInt(x_point[i]), parseInt(y_point[i]), parseInt(x_point[i + 1]), parseInt(y_point[i + 1]));
            }
            jg.paint();

            // 5. 最終的なマウスカーソルの位置を描画
            jg6.clear();
            if (m < t_point.length) {
                jg6.drawImage("pointer001.png", x_point[m], y_point[m], 10, 18);
                jg6.paint();
            }

            // 6. グローバルな時間とスライダーの値を更新して、再生再開に備える
            t = targetTime;
            document.myForm.time.value = t;
            jQuery('#jquery-ui-slider').slider('value', t);
            document.getElementById("start_b").style.visibility = "visible";
        }
        // ▲▲▲▲▲ ここまで追加 ▲▲▲▲▲

        jQuery(function ($) {
            var $slider = $('#jquery-ui-slider');
            var $sliderContainer = $('#slider-container');
            var $tooltip = $('#slider-tooltip');
            var maxTime = t_point[t_point.length - 4];

            // 各単語・グループの総D&D回数を事前に計算しておく
            var eventTotalCounts = {};
            all_dd_events.forEach(function (event) {
                var key = event.labelGroup.includes('#') ? event.labelGroup : event.hLabel;
                if (!eventTotalCounts[key]) {
                    eventTotalCounts[key] = 0;
                }
                eventTotalCounts[key]++;
            });

            // D&Dイベントデータの時間プロパティを数値に変換（重要）
            all_dd_events.forEach(function (event) {
                event.time = parseInt(event.time, 10);
            });

            // 1. スライダーの初期化
            $slider.slider({
                range: 'min',
                value: 0,
                min: 0,
                max: maxTime,
                step: 1, // より滑らかに動かすためにstepを1に
                slide: function (event, ui) {
                    $('#jquery-ui-slider-value').val(ui.value + 'ms');
                },
                stop: function (event, ui) {
                    $('#jquery-ui-slider-value').val(ui.value + 'ms');
                    renderStateAtTime(ui.value);
                }
            });
            $('#jquery-ui-slider-value').val($slider.slider('value') + 'ms');

            // 2. D&Dイベントの目盛りをスライダー上に描画する
            function drawTicks() {
                var $ticksContainer = $('#slider-ticks');
                $ticksContainer.empty(); // 描画前に一度クリア
                var sliderWidth = $slider.width();

                // スライダーの幅が0、または最大時間が未定義の場合は処理を中断
                if (sliderWidth === 0 || !maxTime) return;

                all_dd_events.forEach(function (event) {
                    // イベント時間の相対的な位置をパーセンテージで計算
                    var leftPosition = (event.time / maxTime) * 100;

                    // 位置が0%から100%の範囲内にあることを確認
                    if (leftPosition >= 0 && leftPosition <= 100) {
                        // 新しい目盛り(tick)要素を作成し、計算した位置に配置
                        $('<div>', {
                            class: 'tick'
                        }).css('left', leftPosition + '%').appendTo($ticksContainer);
                    }
                });
            }

            // ページ読み込み完了後に一度だけ実行
            $(window).on('load', function () {
                // DOMの描画が安定するのを少し待ってから目盛りを描画
                setTimeout(drawTicks, 100);
            });


            // 3. スライダーコンテナ上でのマウス移動でツールチップを表示
            $sliderContainer.on('mousemove', function (e) {
                var offsetX = e.pageX - $(this).offset().left;
                var sliderWidth = $(this).width();
                var hoverTime = (offsetX / sliderWidth) * maxTime;

                // マウス位置に最も近いD&Dイベントを探す
                var closestEvent = null;
                var minDiff = Infinity; // 差分の最小値を記録する変数

                all_dd_events.forEach(function (event) {
                    var diff = Math.abs(event.time - hoverTime);
                    if (diff < minDiff) {
                        minDiff = diff;
                        closestEvent = event;
                    }
                });

                // 一定の近さ（スライダー全長の1.5%以内）にあればツールチップを表示
                var threshold = maxTime * 0.015;

                if (closestEvent && minDiff < threshold) {
                    var tooltipText = "";
                    var key = closestEvent.labelGroup.includes('#') ? closestEvent.labelGroup : closestEvent.hLabel;
                    var totalCount = eventTotalCounts[key]; // 事前に計算した総回数を取得

                    // ベースとなるテキストを生成
                    if (key.includes('#')) {
                        // グループの場合
                        var groupLabels = closestEvent.labelGroup.split('#').filter(Boolean); // filter(Boolean)で空文字を除去
                        var wordNames = groupLabels.map(function (labelId) {
                            var id = parseInt(labelId, 10);
                            return (start_point[id] !== undefined) ? start_point[id] : ''; // undefinedの場合は空文字を返す
                        }).filter(Boolean); // 再度、空文字を除去
                        tooltipText = "グループ: [ " + wordNames.join(', ') + " ]";
                    } else {
                        // 単一の単語の場合
                        tooltipText = start_point[parseInt(closestEvent.hLabel, 10)];
                    }

                    // 総回数が1回より大きい場合、何回目の操作かを表示
                    if (totalCount > 1) {
                        tooltipText += " (" + closestEvent.count + "回目)";
                    }

                    $tooltip.html(tooltipText)
                        .css({
                            left: e.pageX + 15,
                            top: e.pageY - 30
                        })
                        .show();
                } else {
                    $tooltip.hide();
                }
            }).on('mouseleave', function () {
                $tooltip.hide(); // マウスが外れたらツールチップを隠す
            });
        });



        //ボタン用
        var jg_b = new jsGraphics("myCanvasb");
        //線を引く用
        var jg = new jsGraphics("myCanvas");
        //並び単語の背景用
        var jg2 = new jsGraphics("myCanvas2");
        //並び単語の背景用(レジスタ1)
        var jg2_1 = new jsGraphics("myCanvas2_1");
        //並び単語の背景用(レジスタ2)
        var jg2_2 = new jsGraphics("myCanvas2_2");
        //並び単語の背景用(レジスタ3)
        var jg2_3 = new jsGraphics("myCanvas2_3");
        //並び単語の背景用(最終解答欄)
        var jg2_4 = new jsGraphics("myCanvas2_4");
        //単語表示用(問題提示欄)
        var jg3 = new jsGraphics("myCanvas3");
        //単語表示用(レジスタ1)
        var jg3_1 = new jsGraphics("myCanvas3_1");
        //単語表示用(レジスタ2)
        var jg3_2 = new jsGraphics("myCanvas3_2");
        //単語表示用(レジスタ3)
        var jg3_3 = new jsGraphics("myCanvas3_3");
        //単語表示用(最終解答欄)
        var jg3_4 = new jsGraphics("myCanvas3_4");
        //移動単語背景用
        var jg4 = new jsGraphics("myCanvas4");
        //移動単語表示用
        var jg5 = new jsGraphics("myCanvas5");
        //マウスカーソル表示用
        var jg6 = new jsGraphics("myCanvas6");
    </script>
</body>

</html>
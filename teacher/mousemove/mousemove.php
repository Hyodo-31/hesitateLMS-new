<html>
<head>
<title>マウス軌跡再現</title>
    <script type="text/javascript">
        var nativeSetInterval = window.setInterval;
        _setInterval = {};

        window.setInterval = function(process, delay) {
	        var entry;
	        if(typeof process == 'string') {
		        entry = new _setInterval.Entry(function(){eval(process);}, delay);
	        }else if(typeof process == 'function') {
		        entry = new _setInterval.Entry(process, delay);
	        }else {
		        throw Error('第一引数が不正です。');
	        }
	        var id = _setInterval.queue.length;
	        _setInterval.queue[id] = entry
	        return id;
        };

        window.clearInterval = function(id) {
	        _setInterval.queue[id].loop = function(){};
        };

        _setInterval.queue = [];

        _setInterval.Entry = function(process, delay) {
	        this.process = process;
	        this.delay   = delay;
	        this.time    = 0;
        };

        _setInterval.Entry.prototype.loop = function(time) {
	        this.time += time;
	        while(this.time >= this.delay) {
		        this.process();
		        this.time -= this.delay
	        }
        };

        _setInterval.lastTime = new Date().getTime();

        nativeSetInterval(function() {
	        var time = new Date().getTime();
	        var subTime =  time - _setInterval.lastTime;
	        _setInterval.lastTime = time;
	        for(var i = 0; i < _setInterval.queue.length; i++) {
		        _setInterval.queue[i].loop(subTime);
	        }
        }, 10);
    </script>

    <?php
	    require("../../dbc.php");
    ?>
    <style type="text/css">
         <!--
         th,td{
         font-size:11pt;
         }
         -->
    </style>
 
    <style type="text/css"><!--
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
            width: 300px;
        }
        -->
    </style>
    <?php
    	// 合計値を求めるメソッド sum()
	    function sum($array1){
		    // 対象配列の抽出
		    $target = $array1;
		    // ここから合計値の計算
		    $result = 0.0; // 合計値
		    for ( $i=0; $i<count($target); $i++ ){
			    $result += $target[$i];
		    }
		    return $result;	// 合計値を返して終了
		}
	
	    // 平均値・期待値を求めるメソッド ave()
	    function ave($array1){
		    // 対象配列の抽出
		    $target = $array1;
		    // 平均値の計算　配列の合計値を算出して、要素数で割る
		    $sum = sum($target);
		    if ( count($target)>0 ){
			    $result = $sum / count($target);
		    }else{
			    $result = 0;
		    }
		    return $result;	
	    }
	
	    // 分散を求めるメソッド varp()
	    function varp($array1){
		    // 対象配列の抽出
		    $target = $array1;
		    // 分散 E{X-(E(X))^2}　により求められる
		    $ave = ave($target);
		    $tmp = 0; // 作業用変数
		    // X-(E(X))^2 の値を入れておく配列
		    $tmparray = array();
		    // 配列の1要素ずつ、 (X-E(X))^2 を計算
		    for ( $i=0; $i<count($target); $i++ ){
			    $tmp = $target[$i] - $ave;		// X-E(X)
			    $tmparray[$i] = $tmp * $tmp; 	// (X-E(X))^2
		    }
		    // 最後に、その平均値をもとめて終わり
		    $result = ave($tmparray);
		    return $result;	
	    }
	
	    // 標準偏差を求めるメソッド sd()
	    function sd($array1){
		    // 対象配列の抽出
		    $target = $array1;
		    // 標準偏差は分散の平方根により求められる
		    $varp = varp($target);	// 分散の算出
		    $result = sqrt($varp);			// その平方根をとる
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
        echo $data_list."<br>";

        // 受け取ったものをコンマで区切る
        $ID = explode(",", $data_list);

        // 各データを変数に格納
        $uid = $ID[0];
        $wid = $ID[1];

    } elseif (isset($_GET["UID"]) && isset($_GET["WID"])) {
        // GETで受け取ったデータの処理（デフォルトのケース）
        $uid = $_GET["UID"];
        $wid = $_GET["WID"];
    } else {
        // エラーメッセージ
        $uid = 30914025;
        $wid = 22;
    }
    // データベースから値を取り出す
    $query = "select distinct(Time),X,Y,DD,DPos,hLabel,Label,UTurnX,UTurnY from linedatamouse where uid = $uid and WID = $wid order by Time";
    $res = mysqli_query($conn,$query) or die("Error:query1");
    $query2 = "select EndSentence,Understand from linedata where uid = $uid and WID = $wid";
    $res2 = mysqli_query($conn,$query2) or die("Error:query2");
    $query3 = "select Japanese,Sentence,grammar,level,start,divide from question_info where WID = $wid";
    $res3 = mysqli_query($conn,$query3) or die("Error:query3");
    $query4 = "select Distance,AveSpeed,MaxStopTime,point,GroupCount,UTurnCount_X,UTurnCount_Y,UTurnCount_XinDD,UTurnCount_YinDD,DragDropCount from trackdata where uid = $uid and WID = $wid";
    $res4 = mysqli_query($conn,$query4) or die("Error:query4");
    $query5 = "select Time from linedata where uid = $uid and WID = $wid";
    $res5 = mysqli_query($conn,$query5) or die("Error:query5");

    $row = mysqli_fetch_array($res2);
    if(isset($row['EndSentence'])){
        $es = $row['EndSentence'];
    }
    if(isset($row['Understand'])){
        $us = $row['Understand'];
    }
    $row2 = mysqli_fetch_array($res3);
    if(isset($row2['Japanese'])){
        $js = $row2['Japanese'];
    }
    if(isset($row2['Sentence'])){
        $se = $row2['Sentence'];
    }
    if(isset($row2['grammar'])){
        $grammar = $row2['grammar'];
    }
    if(isset($row2['level'])){
        $level = $row2['level'];
    }
    if(isset($row2['start'])){
        $start = $row2['start'];
    }
    $row3 = mysqli_fetch_array($res4);
    if(isset($row3['point'])){
        $point = $row3['point'];
    }
    if(isset($row3['AveSpeed'])){
        $avespeed = $row3['AveSpeed'];
    }
    if(isset($row3['Distance'])){
        $distance = $row3['Distance'];
    }
    if(isset($row3['GroupCount'])){
        $groupcount = $row3['GroupCount'];
    }
    if(isset($row3['UTurnCount_X']) && isset($row3['UTurnCount_XinDD'])){
        $uturncount_X = $row3['UTurnCount_X'] + $row3['UTurnCount_XinDD'];
    }
    if(isset($row3['UTurnCount_Y']) && isset($row3['UTurnCount_YinDD'])){
        $uturncount_Y = $row3['UTurnCount_Y'] + $row3['UTurnCount_YinDD'];
    }
    if(isset($row3['MaxStopTime'])){
        $maxstoptime = $row3['MaxStopTime'];
    }
    if(isset($row3['DragDropCount'])){
        $dragdropcount = $row3['DragDropCount'];
    }
    $row4 = mysqli_fetch_array($res5);
    if(isset($row4['Time'])){
        $a_time = $row4['Time']/1000;
    }
    $grammar_split = array();
    $grammar_split = explode("#",$grammar);
    array_pop($grammar_split);
    array_shift($grammar_split);
    $grammar_print = array();

    for($i = 0; $i<count($grammar_split); $i++){
        $query6 = "select Item from grammar where GID = $grammar_split[$i]";
        $res6 = mysqli_query($conn,$query6);
        $row5 = mysqli_fetch_array($res6);
        $grammar_print[$i] = $row5['Item'];
    }

    $time = array();
    $x = array();
    $y = array();
    $DD = array();
    $DPos = array();
    $hLabel = array();
    $Label = array();
    //$addk = array();
    $UTurnX =array();
    $UTurnY =array();

    
    //echo $uid."<br>";
    //echo $wid."<br>";
    // 切り取って配列へ
    if($res -> num_rows > 0){
        while ( $Column = $res-> fetch_assoc() ){
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

    }else{
        echo "結果セットが空です．";
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

    // 繋げて配列へ格納(Javascriptへ値を渡すため)
    for ( $i=0; $i<count($time); $i++ ){
	    if ( $i > 0 ){
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
        if($DD[$i] == '2'){
            $DDdragTime[$hLabel[$i]] = $time[$i];
        }

    }
    $DDdragTime_json = json_encode($DDdragTime);
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
        var startstring = "<?php echo $start; ?>";

        

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
    <!--各フォーム-->
    <form name="myForm" action="#">
        <div>
            <input type="text" size="20" name="time" disabled>
            <select NAME = "speed" SIZE = 1>
                <OPTION value = 5>等倍</option>
                <OPTION value = 2.5>0.5倍</option>
                <OPTION value = 10>2倍</option>
	            <OPTION value = 15>3倍</option>
	            <OPTION value = 25>5倍</option>
	        </select>
            <input type="button" value="軌跡再現" name="start" id = "start_b" 
              onclick="interval(); DrawAline();">
            <input type="button" value="一時停止" name="stop" 
              onclick="stop_interval()">
            <input type="button" value="リセット" name="reset"
              onclick="reset_c()">
            <select name="labelDD" SIZE = 1>
                <?php 
                    $tangoarray = explode("|",$start);
                    $tangocount = count($tangoarray);
                    echo '<option value= 100>------</option>'; 
                    for ($i = 0; $i < $tangocount; $i++) {
                        if($i%2 == 0){
                            echo '<option value="' . $i . '">' . $tangoarray[$i] . '</option>';
                        }
                            
                    }
                ?>
            </select>
        </div>
    </form>

    <p>途中から開始：<input type="text" id="jquery-ui-slider-value" /> / <script type="text/javascript">document.write(t_point[t_point.length-4]);</script>ms</p> 

    <div id="jquery-ui-slider"></div><br>

    <!--文字の幅を計測するために使用-->
    <script type="text/javascript" src="excanvas.js"></script>
    <canvas id="canvas" width="0" height="0" style="visibility:hidden;position:absolute;"></canvas>

    <!--描画用キャンバス・各パラメータ表示用テーブルなど-->
    <table border = "1" cellspacing = "1" width = "1000" height = "500">
        <tr><td>
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
            <table border = "0" width = "995" height = "200">
                <tr><td>
                    <table border = "1" cellspacing = "1" width = "600" height = "200">
                        <tr><td>
                            <b><u>問題番号</u>：<?php echo $wid;?>
                               <u>ユーザ番号</u>：<?php echo $uid;?></b>
                            <br><br>
                            <b><u>最終解答文</u>：<?php echo $es; ?></b><br><br>
                            <b><u>日本語文</u>：<?php echo $js; ?></b><br>
                            <b><u>正解文</u>：<?php echo $se; ?></b><br>
                            <br>
                            <b><u>迷い度</u>：
                            <?php
                                if ($us == 0){print("間違って終了ボタンを押した");}
                                elseif ( $us == 4 ){print("ほとんど迷わなかった");}
                                elseif ( $us == 3 ){print("少し迷った");}
                                elseif ( $us == 2 ){print("かなり迷った");}
                                //elseif ( $us == 1 ){print("かなり迷った");}
                            ?>
                            </b><br>
                            <b><u>正誤</u>：
                            <?php
                                if(isset($point) && $point == 10){print("○");}
                                else{print("×");}
                            ?>
                            </b><br>
                        </td></tr>
                    </table>
                    </td><td>
                        <table border = "1" cellspacing = "1" width = "380" height = "200">
                            <tr><td>
                                文法項目：<?php
                                    for($i = 0; $i<count($grammar_print); $i++)
                                    {print($grammar_print[$i]);
                                    print(" ");}
                                ?>
                                難易度：<?php
                                    if ( $level == 1 ){print("初級");}
                                    else if ( $level == 2 ){print("中級");}
                                    else if ( $level == 3 ){print("上級");}
                                ?>
                                <br><br>
                                得点：<?php
                                    if(isset($point)){
                                        echo $point;
                                    }
                                ?>点<br>
                                解答時間：<?php
                                    if(isset($a_time)){
                                        echo $a_time;
                                    }
                                ?>秒<br>
                                Drag＆Drop回数：<?php
                                    if(isset($dragdropcount)){
                                        echo $dragdropcount;
                                    }
                                ?>回<br>
                                Uターン回数(X)：<?php
                                    if(isset($uturncount_X)){
                                        echo $uturncount_X;
                                    }
                                ?>回<br>
                                Uターン回数(Y)：<?php
                                    if(isset($uturncount_Y)){
                                        echo $uturncount_Y;
                                    }
                                ?>回<br>
                                マウス移動距離：<?php
                                    if(isset($distance)){
                                        echo $distance;
                                    }
                                ?>pixel<br>
                                平均マウス速度：<?php
                                    if(isset($avespeed)){
                                        echo $avespeed;
                                    }
                                ?>pixel/ms<br>
                                最大静止時間：<?php
                                    if(isset($maxstoptime)){
                                        echo $maxstoptime;
                                    }
                                ?>ms<br>
                                グループ化回数：<?php
                                    if(isset($groupcount)){
                                        echo $groupcount;
                                    }
                                ?>回<br>
                            </td></tr>
                        </table>
                    </td></tr>
            </table>
            <br>
            <table border = "1" cellspacing = "1" width = "600" height = "200">
            <tr><td>
            <?php
                $diarray = explode("|",$start);
                $diarray_num = count($diarray);
                $word_count = array();
                $DD_count = array();
                $AAword_count = array();
                $Label = "";
                $AALabel = "";
                $Array_Flag = 0;
                $AARR_Flag = 0;
                $sql_DD ="select * from linedatamouse where UID = ".$uid." and WID = ".$wid." order by Time;";
                $res_DD = mysqli_query($conn,$sql_DD) or die("接続エラー");
                while($row_DD = mysqli_fetch_array($res_DD)){
                    if($row_DD["DD"] ==2){//Drag時
                        $Label = $row_DD["Label"];
                        $Label_div = explode("#",$Label);
                        $DragTime = $row_DD["Time"];
                        foreach($Label_div as $value){
                            if(isset($DD_count[$value])){
                                $DD_count[$value] += 1;
                            }else{
                                $DD_count[$value] = 1;
                            }
                            
                        }
                        /*各欄の座標で場所を判断しているため、新しい実験のときはここを変更すること*/
                        if($row_DD["Y"] > 130 and $row_DD["Y"]<= 215){ $Array_Flag = 4; //解答欄 
                        }else if($row_DD["Y"] > 215 and $row_DD["Y"]<=295){ $Array_Flag = 1;//レジスタ1
                        }else if($row_DD["Y"] > 295 and $row_DD["Y"]<=375){ $Array_Flag = 2;//レジスタ2
                        }else if($row_DD["Y"] > 375){ $Array_Flag = 3;//レジスタ3
                        }else{ $Array_Flag = 0;//問題提示欄
                        }
                    }else if($row_DD["DD"] ==1){//Drop時
                        $DropTime = $row_DD["Time"];
                        if($row_DD["Y"] <= 130){//　→問題提示欄
                            if($Array_Flag!=0){//問題提示欄から以外→問題提示欄はD&Dカウント
                                foreach($Label_div as $value){
                                    if(isset($word_count[$value])){
                                        $word_count[$value]++;
                                    }else{
                                        $word_count[$value] = 1;
                                    }
                                }
                            }
                        }else if($row_DD["Y"] > 130 and $row_DD["Y"]<= 215){//　→解答欄
                            if($Array_Flag==4){//解答欄入れ替え
                                $AARR_Flag = 4;
                                foreach($Label_div as $value){
                                    if(isset($word_count[$value])){
                                        $word_count[$value]++;
                                    }else{
                                        $word_count[$value] = 1;
                                    }
                                    
                                }
                            }
                        }else if($row_DD["Y"] > 215 and $row_DD["Y"]<=295){ //→レジスタ1
                            if($Array_Flag==1){
                                $AARR_Flag = 1;
                                foreach($Label_div as $value){
                                    if(isset($word_count[$value])){
                                        $word_count[$value]++;
                                    }else{
                                        $word_count[$value] = 1;
                                    }
                                    
                                }
                            }
                        }else if($row_DD["Y"] > 295 and $row_DD["Y"]<=375){ //→レジスタ2
                            if($Array_Flag==2){
                                $AARR_Flag = 2;
                                foreach($Label_div as $value){
                                    if(isset($word_count[$value])){
                                        $word_count[$value]++;
                                    }else{
                                        $word_count[$value] = 1;
                                    }
                                }
                            }
                        }else if($row_DD["Y"] > 375){ //→レジスタ3
                            if($Array_Flag==3){
                                $AARR_Flag = 3;
                                foreach($Label_div as $value){
                                    if(isset($word_count[$value])){
                                        $word_count[$value]++;
                                    }else{
                                        $word_count[$value] = 1;
                                    }
                                }
                            }
                        }
                        if($AARR_Flag>=1 and $AARR_Flag<=4){//解答欄内or同レジスタ内移動の場合
                            $AALabel = $Label;
                            $AALabel_div = explode("#",$AALabel);
                            foreach($AALabel_div as $value){
                                if(isset($AAword_count[$value])){
                                    $AAword_count[$value]++;//単語の取得
                                }else{
                                    $AAword_count[$value] = 1;//単語の取得
                                }
                                
                            }
                        }
                        $AARR_Flag = 0;//解答欄内入れ替え判定フラグ初期化
                        $Label_div = array();
                    }
                }
                arsort($AAword_count);
                arsort($word_count);
                $sql_DD2="select * from linedatamouse where UID = ".$uid." order by WID,Time;";
                $res_DD2 = mysqli_query($conn,$sql_DD2) or die("接続エラー");
                $DC_Flag = 0;
                $DC_array = array();
                $Time_array = array();
                $WID_array = array();
                $Label_array = array();
                $hazureDC = array();
                $DevDC = array();
                $j = 0;
                $l_80 = 0;
                $l_75 = 0;
                $l_70 = 0;
                $l_65 = 0;
                $l_60 = 0;
                $l_2 = 0;
                $l_1 = 0;
                $l_075 = 0;
                $key80_array =array();
                $key75_array =array();
                $key70_array =array();
                $key65_array =array();
                $key60_array =array();
                $key2_array = array();
                $key1_array = array();
                $key075_array = array();
                $m = 0;
                while($row_DD2 = mysqli_fetch_array($res_DD2)){
                    if($row_DD2["DD"] ==2){
                        if($DC_Flag ==1){
                            $Time_array[$j] = $row_DD2["Time"];
                            $DC_array[$j] = $row_DD2["Time"] - $before_Time;
                            $WID_array[$j] = $row_DD2["WID"];
                            $Label_array[$j] = $row_DD2["Label"];
                            $j++;
                        }
                    }else if($row_DD2["DD"] ==1){
                        $DC_Flag = 1;
                        $before_Time = $row_DD2["Time"];
                    }else if($row_DD2["DD"] ==-1){
                        $DC_Flag = 0;
                    }
                }
       
                $aveDC_sub = ave($DC_array);//純粋なDC時間の平均値
                $sdDC_sub = sd($DC_array);//純粋なDC時間の標準偏差
                $thereshold_2 = 2*$sdDC_sub+$aveDC_sub;//閾値下
                $thereshold_3 = 3*$sdDC_sub+$aveDC_sub;//閾値上
                $thereshold_1 = 1*$sdDC_sub+$aveDC_sub;//閾値上     
                $thereshold_075 = 0.75*$sdDC_sub+$aveDC_sub;//閾値上
                $j = 0;
                $sdDC = 0;
                $aveDC = 0;
                $tempsdDC = 50;
                foreach($DC_array as $value){
                    $DevDC[$j] = ($value- $aveDC)*10 / $tempsdDC;
                    if($WID_array[$j] == $wid){
                        if($value>=$thereshold_2 ){ $key2_array[$l_2] = $j; $l_2++;}
                        if($value>=$thereshold_1 and $value<$thereshold_2){ $key1_array[$l_1] = $j; $l_1++;}               
                        if($value>=$thereshold_075 and $value<$thereshold_1){ $key075_array[$l_075] = $j; $l_075++;}
                        for($i = count($diarray)-1;$i>=0;$i--){
                            $Label_array[$j] = str_replace($i,$diarray[$i],$Label_array[$j]);
                        }
                    }
                    $j++;
                }
                $p2 = 0;
                $p1 = 0;
                $p075 = 0;
                
                $DC_array2 = array();
                $DC_array1 = array();
                $DC_array075 = array();
                $key_sub2 = array();
                $key_sub1  = array();
                $key_sub075 = array();
                foreach($key2_array as $value){
                    $DC_array2[$p2] = $DC_array[$value];
                    $key_sub2[$p2] = $value;
                    $p2++;
                }
                foreach($key1_array as $value){
                    $DC_array1[$p1] = $DC_array[$value];
                    $key_sub1[$p1] = $value;
                    $p1++;
                }
                foreach($key075_array as $value){
                    $DC_array075[$p075] = $DC_array[$value];
                    $key_sub075[$p075] = $value;
                    $p075++;
                }
                arsort($DC_array2);
                arsort($DC_array1);
                arsort($DC_array075);
            ?>
            <?php
                function arrayGetDuplicate($array){//重複した配列を抽出する関数
                    $duplicate = array();
                    $already = array();
                    foreach ($array as $key=>$value){
                        foreach ($array as $key2=>$value2) {
                            if($key == $key2){
                                continue;
                            }
                            if(in_array($key2, $already)){
                                continue;
                            }
                            if($array[$key] == $array[$key2]){
                                $already[] = $key;
                                $duplicate[] = $array[$key] ;
                            }
                        }
                    }
                    return $duplicate;
                }        

                //迷い抽出テスト用はじめ
                $word_array = array();//迷い単語格納用
                $Labelnum = 0;
                //迷い抽出テスト終わり
                $word_array = array();//迷い単語格納用
                $Labelnum = 0;
                foreach ($word_count as $key => $value){
                    if($value >=1){
                        if(isset($diarray[$key])){
                            $word_array[$Labelnum] = $diarray[$key];
                            $Labelnum++;
                        }
                     
                    }
                }
                /*
                foreach($DC_array3 as $key=>$value){
                    $word_array[$Labelnum] = $Label_array[$key_sub3[$key]];
                    $Labelnum++;
                }
                */
                 foreach($DC_array2 as $key=>$value){
                    $word_array[$Labelnum] = $Label_array[$key_sub2[$key]];
                    $Labelnum++;
                }
                 foreach($DC_array1 as $key=>$value){
                    $word_array[$Labelnum] = $Label_array[$key_sub1[$key]];
                    $Labelnum++;
                }
                foreach($DC_array075 as $key=>$value){
                    $word_array[$Labelnum] = $Label_array[$key_sub075[$key]];
                    $Labelnum++;
                }
            ?>
            <b><u>・迷い候補リスト</u></b></br>
            <?php
                //2023/11/29追加 phpからpythonを呼び出す
                $filename = "Python/helloworld.py";
                $command = "py " . $filename;
                exec($command, $dum,$rtn);
                echo "python:" . $dum[0];
                
                
                
                $word_array = array_unique($word_array);
                $hesitate_word="";
                $hesitate_cnt=0;
                foreach($word_array as $value){
                    echo $value."<br>";
                    if($hesitate_cnt==0) $hesitate_word=$value;
                    else $hesitate_word=$hesitate_word."#".$value;
                    $hesitate_cnt++;
                }
                // テーブルhesitate.への挿入.使いたいときはコメントを外してください
                /*
                $sql_insert_ready="select * from hesitate where uid=".$uid." and wid=".$wid;
                $res_insert_ready=mysqli_query($conn,$sql_insert_ready);
                $num_ready=mysqli_num_rows($res_insert_ready);
                if($num_ready == 0){
                    $sql_insert="insert into hesitate values(".$uid.",".$wid.",\"".$hesitate_word."\")";
                    $res_insert= mysqli_query($conn,$sql_insert) or die("接続エラー");
                }
                */
                
            ?>
            <br>
            <b><u>・余分なD&D動作が検出された単語</u></b><br>
            <?php   
                $p = 0;
                $q = 0;
                foreach ($word_count as $key => $value){
                    if($p ==0){
            ?>
                        <u>[複数回]</u><br>
            <?php
                    }
                    if ($value >=2){
                        echo " ".$diarray[$key]."(".$key."): ".$value." 回<br>";
                        $p++;
                    }else if ($value >=1){
                        if($q == 0){
            ?>
                            <u></u>[1回]</u><br>
            <?php
                        }
                        if(isset($diarray[$key])){
                            echo " ".$diarray[$key]."(".$key.")<br>";
                        }
                        $q++;
                    }
                }
            ?>
            <br>
            <b><u>・入れ替え間時間</u></b><br> 
                <u>長いもの</u><br>
                <?php
                    foreach($DC_array2 as $key=>$value){
                        echo $Label_array[$key_sub2[$key]].$value." [経過時間".$Time_array[$key_sub2[$key]]."ms～]<br>";
                    }
                ?>
                <u>1.0</u><br>
                <?php
                    foreach($DC_array1 as $key=>$value){
                        echo $Label_array[$key_sub1[$key]].$value." [経過時間".$Time_array[$key_sub1[$key]]."ms～]<br>";
                    }
                ?>
                <u>0.75</u><br>
                <?php
                    foreach($DC_array075 as $key=>$value){
                        echo $Label_array[$key_sub075[$key]].$value." [経過時間".$Time_array[$key_sub075[$key]]."ms～]<br>";
                    }
                ?>
            </td></tr>
            </table>
        </td></tr>
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
        //ドラッグした単語の順番
        var DDdragTimetemp = 0;
        //単語の長さを測る。IEだと上手く動いてくれない。
        function strWidth(str) {
            var canvas = document.getElementById('canvas');
            if (canvas.getContext) {
                var context = canvas.getContext('2d');
                context.font = "16px 'arial'";
                var metrics = context.measureText(str);
                return metrics.width;
            }
            return -1;
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
            }else if (md_flag == 1) {
                now_list = list_r1.slice(0);
                Label_x = Label_r1.slice(0);
                var string_x = 30;
                var string_y = 250;
                jg_canvas = jg3_1
                jg_canvas2 = jg2_1
            }else if (md_flag == 2) {
                now_list = list_r2.slice(0);
                Label_x = Label_r2.slice(0);
                var string_x = 30;
                var string_y = 330;
                jg_canvas = jg3_2
                jg_canvas2 = jg2_2
            }else if (md_flag == 3) {
                now_list = list_r3.slice(0);
                Label_x = Label_r3.slice(0);
                var string_x = 30;
                var string_y = 410;
                jg_canvas = jg3_3
                jg_canvas2 = jg2_3
            }else if (md_flag == 4) {
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
            }else {
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
            }else if (md_flag == 1) {
                list_r1 = now_list.slice(0);
                Label_r1 = Label_x.slice(0);
            }else if (md_flag == 2) {
                list_r2 = now_list.slice(0);
                Label_r2 = Label_x.slice(0);
            }else if (md_flag == 3) {
                list_r3 = now_list.slice(0);
                Label_r3 = Label_x.slice(0);
            }else if (md_flag == 4) {
                list_a = now_list.slice(0);
                Label_a = Label_x.slice(0);
            }
        }

        //リセット
        function reset_c() {
            t = 0;
            m = 0;
            document.myForm.time.value = "";
            stop_interval();
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

        //インターバル開始
        function interval() {
            document.getElementById("start_b").style.visibility = "hidden";
            // document.getElementById("jquery-ui-slider").style.visibility = "hidden";

            md_flag = 0;
            DrawString();
            //String内で場合分けするか、もしくはイベントフラグを0にしてぶちこむ。←この場合はstart_stはいらないヨ
            labelDDvalue = document.myForm.labelDD.value;
            labelDDTime = DDdragTime[labelDDvalue];

            console.log("labelDDvalue" + labelDDvalue);
            console.log("labelDDTime is " + labelDDTime);
            console.log("slider is " + slider);

            for (n = t; n < slider; n++) {
                t = t + 1;
                if (t == t_point[m + 1]) {
                    grouptest = Label_point[m + 1].split("#");
                    //グループ化されていない場合
                    if (DD_point[m + 1] == 2 && grouptest[1] == undefined) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130) { md_flag = 0; }
                        else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130){ md_flag = 4; }
                        else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215){ md_flag = 1; }
                        else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295){ md_flag = 2; }
                        else if (parseInt(y_point[m + 1]) > 375){ md_flag = 3; }
                        WordDrag();
                        wordmove = 1;
                    }

                    //グループ化された場合(複数選択されている場合)
                    if (DD_point[m + 1] == 2 && grouptest[1] != undefined) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130){ md_flag = 0; }
                        else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130){ md_flag = 4; }
                        else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215){ md_flag = 1; }
                        else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295){ md_flag = 2; }
                        else if (parseInt(y_point[m + 1]) > 375){ md_flag = 3; }
                        WordGroup();
                        wordmove = 1;
                    }

                    //ドラッグ＆ドロップが行われた場合に、単語の並び順等を変更
                    if (DD_point[m + 1] == 1) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130){ md_flag = 0; }
                        else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130){ md_flag = 4; }
                        else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215){ md_flag = 1; }
                        else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295){ md_flag = 2; }
                        else if (parseInt(y_point[m + 1]) > 375){ md_flag = 3; }
                        WordDrop();
                        wordmove = 0;
                        jg4.clear();
                        jg5.clear();

                    }
                    m = m + 1;
                    //バグ対策
                    if (parseInt(t_point[m]) == parseInt(t_point[m + 1])) { m = m + 1; }
                }
            }

            if(labelDDTime != 0 && slider == 0){
                for (n = t; n < labelDDTime; n++) {
                    t = t + 1;
                    if (t == t_point[m + 1]) {
                        grouptest = Label_point[m + 1].split("#");
                        //グループ化されていない場合
                        if (DD_point[m + 1] == 2 && grouptest[1] == undefined) {
                            //ここでイベントが起こった場所によって場合分け
                            if (parseInt(y_point[m + 1]) <= 130) { md_flag = 0; }
                            else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130){ md_flag = 4; }
                            else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215){ md_flag = 1; }
                            else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295){ md_flag = 2; }
                            else if (parseInt(y_point[m + 1]) > 375){ md_flag = 3; }
                            WordDrag();
                            wordmove = 1;
                        }

                        //グループ化された場合(複数選択されている場合)
                        if (DD_point[m + 1] == 2 && grouptest[1] != undefined) {
                            //ここでイベントが起こった場所によって場合分け
                            if (parseInt(y_point[m + 1]) <= 130){ md_flag = 0; }
                            else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130){ md_flag = 4; }
                            else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215){ md_flag = 1; }
                            else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295){ md_flag = 2; }
                            else if (parseInt(y_point[m + 1]) > 375){ md_flag = 3; }
                            WordGroup();
                            wordmove = 1;
                        }

                        //ドラッグ＆ドロップが行われた場合に、単語の並び順等を変更
                        if (DD_point[m + 1] == 1) {
                            //ここでイベントが起こった場所によって場合分け
                            if (parseInt(y_point[m + 1]) <= 130){ md_flag = 0; }
                            else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130){ md_flag = 4; }
                            else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215){ md_flag = 1; }
                            else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295){ md_flag = 2; }
                            else if (parseInt(y_point[m + 1]) > 375){ md_flag = 3; }
                            WordDrop();
                            wordmove = 0;
                            jg4.clear();
                            jg5.clear();

                        }
                        m = m + 1;
                        //バグ対策
                        if (parseInt(t_point[m]) == parseInt(t_point[m + 1])) { m = m + 1; }
                    }
                }
            }

            timer1 = setInterval(timer, 5);
        }

        //インターバル中止
        function stop_interval() {
            clearInterval(timer1);
            document.getElementById("start_b").style.visibility = "visible";
            $("#jquery-ui-slider").slider("value", document.myForm.time.value);
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
                document.myForm.time.value = t;
                // $("#jquery-ui-slider").slider("value", t); // 連動するけど重すぎる

                if (t == t_point[m + 1]) {
                    grouptest = Label_point[m + 1].split("#");
                    //グループ化されていない場合
                    if (DD_point[m + 1] == 2 && grouptest[1] == undefined) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130){ md_flag = 0; }
                        else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130){ md_flag = 4; }
                        else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215){ md_flag = 1; }
                        else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295){ md_flag = 2; }
                        else if (parseInt(y_point[m + 1]) > 375){ md_flag = 3; }
                        WordDrag();
                        wordmove = 1;
                    }

                    //グループ化された場合(複数選択されている場合)
                    if (DD_point[m + 1] == 2 && grouptest[1] != undefined) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130){ md_flag = 0; }
                        else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130){ md_flag = 4; }
                        else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215){ md_flag = 1; }
                        else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295){ md_flag = 2; }
                        else if (parseInt(y_point[m + 1]) > 375){ md_flag = 3; }
                        WordGroup();
                        wordmove = 1;
                    }

                    //ドラッグ＆ドロップが行われた場合に、単語の並び順等を変更
                    if (DD_point[m + 1] == 1) {
                        //ここでイベントが起こった場所によって場合分け
                        if (parseInt(y_point[m + 1]) <= 130){ md_flag = 0; }
                        else if (parseInt(y_point[m + 1]) <= 215 && parseInt(y_point[m + 1]) > 130){ md_flag = 4; }
                        else if (parseInt(y_point[m + 1]) <= 295 && parseInt(y_point[m + 1]) > 215){ md_flag = 1; }
                        else if (parseInt(y_point[m + 1]) <= 375 && parseInt(y_point[m + 1]) > 295){ md_flag = 2; }
                        else if (parseInt(y_point[m + 1]) > 375){ md_flag = 3; }
                        WordDrop();
                        wordmove = 0;
                        jg4.clear();
                        jg5.clear();
                    }
                    DrawLine();
                }
            }
            if (t_point[t_point.length] == t_point[m + 1]) {
                alert("再現終了");
                UTurnCount = 0;
                reset_c();
            }
        }
        //ドラッグ処理
        function WordDrag() {
            //三木さんすいません
            if (list_q.indexOf(parseInt(hLabel_point[m + 1])) != -1) { md_flag = 0; }
            if (list_r1.indexOf(parseInt(hLabel_point[m + 1])) != -1) { md_flag = 1; }
            if (list_r2.indexOf(parseInt(hLabel_point[m + 1])) != -1) { md_flag = 2; }
            if (list_r3.indexOf(parseInt(hLabel_point[m + 1])) != -1) { md_flag = 3; }
            if (list_a.indexOf(parseInt(hLabel_point[m + 1])) != -1) { md_flag = 4; }
            //フラグによって各配列の中身をnow_listにぶち込み
            if (md_flag == 0) { now_list = list_q.slice(0); }
            else if (md_flag == 1) { now_list = list_r1.slice(0); }
            else if (md_flag == 2) { now_list = list_r2.slice(0); }
            else if (md_flag == 3) { now_list = list_r3.slice(0); }
            else if (md_flag == 4) { now_list = list_a.slice(0); }
            //now_list(現在の並び順)の中で、hLabel_point[m+1]の値は何番目にあるのか調べて、t_wordに入れる
            t_word = now_list.indexOf(parseInt(hLabel_point[m + 1]));
            /*console.log(t_word);*/
            //ドラッグ中の単語を退避させておく
            parse_t = parseInt(t_word);
            console.log("parse_t is" + parse_t )
            //word[0]の中にどの単語であるかが含まれている．
            word[0] = now_list[parse_t];
            console.log("word[0] is " + word[0])

            //ドラッグ中の単語の消去
            now_list.splice(parse_t, 1);
            //フラグによってnow_listにぶち込み
            if (md_flag == 0) { list_q = now_list.slice(0); }
            else if (md_flag == 1) { list_r1 = now_list.slice(0); }
            else if (md_flag == 2) { list_r2 = now_list.slice(0); }
            else if (md_flag == 3) { list_r3 = now_list.slice(0); }
            else if (md_flag == 4) { list_a = now_list.slice(0); }
            DrawString();
        }

        //グループ化処理
        function WordGroup() {
            //フラグによって各配列の中身をnow_listにぶち込み
            if (md_flag == 0) { now_list = list_q.slice(0); }
            else if (md_flag == 1) { now_list = list_r1.slice(0); }
            else if (md_flag == 2) { now_list = list_r2.slice(0); }
            else if (md_flag == 3) { now_list = list_r3.slice(0); }
            else if (md_flag == 4) { now_list = list_a.slice(0); }
            word = Label_point[m + 1].split("#");
            //ドラッグ処理を単語数分繰り返すだけ
            for (i = 0; i < word.length; i++) {
                t_word = now_list.indexOf(parseInt(grouptest[i]));
                parse_t = parseInt(t_word);
                word[i] = now_list[parse_t];
                now_list.splice(parse_t, 1);
            }
            //フラグによってnow_listにぶち込み
            if (md_flag == 0) { list_q = now_list.slice(0); }
            else if (md_flag == 1) { list_r1 = now_list.slice(0); }
            else if (md_flag == 2) { list_r2 = now_list.slice(0); }
            else if (md_flag == 3) { list_r3 = now_list.slice(0); }
            else if (md_flag == 4) { list_a = now_list.slice(0); }
            DrawString();
        }

        //単語をどこにドロップするか見る
        function WordDrop() {
            //フラグによって各配列の中身をnow_listにぶち込み
            if (md_flag == 0) {
                now_list = list_q.slice(0);
                Label_x = Label_q.slice(0);
            }else if (md_flag == 1) {
                now_list = list_r1.slice(0);
                Label_x = Label_r1.slice(0);
            }else if (md_flag == 2) {
                now_list = list_r2.slice(0);
                Label_x = Label_r2.slice(0);
            }else if (md_flag == 3) {
                now_list = list_r3.slice(0);
                Label_x = Label_r3.slice(0);
            }else if (md_flag == 4) {
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
            tmplist = now_list;     //　一時的に作業用へ退避　この配列を処理に使用する
            if (now_list[0] == undefined || md_flag == 0) {
                for (j = 0; j < word.length; j++) {
                    tmplist.splice(0 + j, 0, word[j]);
                }
            }else {
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
            if (md_flag == 0) { list_q = now_list.slice(0); }
            else if (md_flag == 1) { list_r1 = now_list.slice(0); }
            else if (md_flag == 2) { list_r2 = now_list.slice(0); }
            else if (md_flag == 3) { list_r3 = now_list.slice(0); }
            else if (md_flag == 4) { list_a = now_list.slice(0); }
            DrawString();
            word.length = 0;
        }


        //線を描画
        function DrawLine() {
            jg6.clear();
            
            if      (t%50000 <= 10000)              { jg.setColor("pink"); }
            else if (t%50000 > 10000 && t%50000 <= 20000) { jg.setColor("blue"); }
            else if (t%50000 > 20000 && t%50000 <= 30000) { jg.setColor("orange"); }
            else if (t%50000 > 30000 && t%50000 <= 40000) { jg.setColor("green"); }
            else if (t%50000 > 40000 && t%50000 <= 50000) { jg.setColor("red"); }
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
            if (yu == 1 && xu==1){
                jg.fillRect(x1, y1, 10, 10);
            }else if(xu==1 && yu!=1){
                jg.drawString("☆",x1-5, y1-5);
            }else if(xu!=1 && yu==1){
                jg.drawString("★",x1-5, y1-5);
            }else{
                jg.drawEllipse(x1, y1 - 2, 4, 4);
            } 
            jg.paint();
            //マウスポインター
            jg6.drawImage("pointer001.png", x2, y2, 10, 18);
            jg6.paint();
            if (wordmove == 1) { WordMove(); }
            m = m + 1;
            //バグ対策
            if (parseInt(t_point[m]) == parseInt(t_point[m + 1])) { m = m + 1; }
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

        jQuery(function () {
            jQuery('#jquery-ui-slider').slider({
                range: 'min',
                value: 0,
                min: 0,
                max: t_point[t_point.length - 4],
                step: 100,
                slide: function (event, ui) {
                    jQuery('#jquery-ui-slider-value').val(ui.value + 'ms');
                    document.myForm.time.value = ui.value;
                    slider = ui.value;
                }
            });
            jQuery('#jquery-ui-slider-value').val(jQuery('#jquery-ui-slider').slider('value') + 'ms');
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
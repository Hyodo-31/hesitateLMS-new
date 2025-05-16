<!DOCTYPE html PUBLIC "-//W3c//DTD HTML 4.01 Transitional//EN">

<?php

//ログイン関連
error_reporting(E_ALL);
session_start();
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


<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>解答結果</title>
<link rel="stylesheet" href="../style/StyleSheet.css" type="text/css" />  
</head>
<body>
<span style="line-height:20px"> 
<div align="center">
<img src="image/logo.png">
</div>
<div align="right">
    <!--
<a class="btn_yellow" href="./LineQuesForm.php" onclick="window.close();">戻る</a><br>
-->
</div>
<div align="center">
<br><br>
英単語並べ替え問題解答おつかれさまでした。<br>
以下、解答結果です。<br>
<br>
<br>
<?php

require "../dbc.php";
//$uid="70110086";
$uid=$_SESSION["MemberID"];
//echo "uid:".$uid;
$Name=$_SESSION["MemberName"];
$Qid = $_GET["Qid"];
echo $Name."さんの解答結果<br><br>";

$sql = "SELECT 
            SUM(TF) as correct_count,
            count(*) as total_count,
            (SUM(TF)/count(*))*100 as accuracy_rate
        FROM linedata
        WHERE UID = ? and test_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $uid, $Qid);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

/*
$sql="select (Table1.Maru/(Table1.Maru+Table2.Batsu))*100 as Percent from
(select count(*) as Maru from linedata where uid='".$uid."' and TF=1 and WID!=8) as Table1,
(select count(*) as Batsu from linedata where uid='".$uid."' and TF=0 and WID!=8) as Table2";


$res = mysql_query($sql, $conn) or die("Member抽出エラー");
$row = mysql_fetch_array($res);
*/

if (isset($row['accuracy_rate'])) {
    echo "<font size=12pt color=red>正解率：" . round($row['accuracy_rate'], 2) . "%</font><br><br>";
} else {
    // 例: 0%として扱う、あるいは「データ無し」と表示
    echo "<font size=12pt color=red>正解率：0%</font><br><br>";
}



$sql_2 = "select tq.OID,l.WID,l.TF,l.EndSentence,q.Sentence
            from test_questions tq,linedata l,question_info q
            where tq.WID=l.WID and l.WID=q.WID and tq.test_id= ? and l.uid= ?
            order by tq.OID";
$stmt_2 = $conn->prepare($sql_2);
$stmt_2->bind_param('ii', $Qid, $uid);
$stmt_2->execute();
$res_2 = $stmt_2->get_result();

/*
$sql_2=" select quesorder.OID,linedata.WID,linedata.TF,linedata.EndSentence,question_info.Sentence,trackdata.point from linedata,quesorder,question_info,trackdata 
        where linedata.WID=question_info.WID and linedata.uid='".$uid."' 
        and quesorder.WID=linedata.WID and trackdata.uid='".$uid."' 
        and trackdata.WID=linedata.WID 
        order by quesorder.OID";
//echo "sql:".$sql_2;
$res_2 = mysql_query($sql_2, $conn) or die("Member抽出エラー");
// echo "res:".$res_2."<br>";
*/


echo "<table class='table_1'><tr><th>問題番号</th><th>解答</th><th>正答</th><th>〇/×</th></tr>";

//echo "before loop";
while($row_2 = mysqli_fetch_array($res_2)){
    echo "<tr><td>".(intval($row_2['OID']))."</td>";
    echo "<td>".$row_2['EndSentence']."</td>";
    echo "<td>".$row_2['Sentence']."</td><td>";
    $tf=$row_2['TF'];
    if($tf=='1') echo "〇</td>";
    else echo "×</td>";

    //echo "<td>".$row_2['point']."</td></tr>"; トラックデータテーブルがよくわからんためいったん消す
}
//echo "after loop";
echo "</table>";


mysqli_close($conn);

?>

<br>

</span>
</div>
</body>
</html>

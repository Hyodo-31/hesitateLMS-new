<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<?php
session_start();
if(!isset($_SESSION["MemberName"])){ //ログインしていない場合
	require"notlogin.html";
	session_destroy();
	exit;
}
$_SESSION["examflag"] = 0;
?>

<html>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<head>
	<title>登録</title>
    <link rel="stylesheet" href="../../style/StyleSheet.css" type="text/css" />  
</head>

<body>
<div align="center">
	<FONT size="6">登録画面</FONT>
	</br>
<?php
session_start();
require "../../dbc.php";
$Japanese = $_SESSION["Japanese"];
$Sentence = $_SESSION["Sentence"];
$dtcnt = $_SESSION["dtcnt"];
$divide2 =$_SESSION["divide2"];
$fix = $_SESSION["fix"];
$property = $_SESSION["property"];
$start =$_SESSION["start"];
$mode = $_SESSION["mode"];
$member = $_SESSION["MemberName"];
$wordnum =$_SESSION["wordnum"];


error_reporting(-1);

if($mode == 0){
$sql_ins = "insert into question_info VALUES($dtcnt,'".$Japanese."','".$Sentence."','".$fix."','-1'
,'-1','".$start."','".$divide2."',$wordnum,'".$member."')";
}else{
	$sql_ins = "update question_info SET Japanese = '".$Japanese."',Sentence = '".$Sentence."'
	,fix ='".$fix."',divide = '".$divide2."',wordnum =$wordnum,start = '".$start."',author = '".$member."'
	 where WID = $dtcnt;";
}
print "<br>";
echo $sql_ins."<BR>";
//SQLを実行
//echo $sql_ins."<br>";
if (!$res = mysqli_query($conn,$sql_ins)) {
	echo "SQL実行時エラー" ;
	exit ;
}
//データベースから切断
mysqli_close($conn) ;

//メッセージ出力
 $_SESSION["Japanese"]="";
 $_SESSION["Sentence"]="";
 $_SESSION["dtcnt"]="";
 $_SESSION["divide"]="";
 $_SESSION["divide2"]="";
 $_SESSION["fix"]="";
 $_SESSION["fixlabel"]="";
 $_SESSION["num"]="";
 $_SESSION["pro"]="";
 $_SESSION["property"]="";
 $_SESSION["start"]="";
?>
<font size = 4>
<b>登録完了しました。</br></br></b>
</font>

<a href="../teachertrue.php" class="button">　ホーム画面へ　</a><br><br>
</div>
</body>
</html>
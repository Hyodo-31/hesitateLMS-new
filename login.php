<?php
    session_start()
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="style/login_style.css" type="text/css" />  
<title>英単語並べ替え問題LMS</title>
</head>
<body>
<span style="line-height:20px">         <!--行間隔指定-->

<?php
    require "dbc.php";
?>
<?php
    //idとpassの検証
    //テキストボックスに入力されたデータを受け取る
    if(isset($_POST["idtxt"]) && isset($_POST["passtxt"]) && !empty($_POST["idtxt"]) && !empty($_POST["passtxt"])){
        $id = @$_POST["idtxt"];
        $pass = @$_POST["passtxt"];

        echo "入力id：{$id}<br>";
        echo "パス：{$pass}<br>";

        $_SESSION["URL"]="./";
        //$_SESSION["URL"]="http://localhost/";
        //データを取り出す
        $sqlteacher = "SELECT TID FROM teachers WHERE (Tname = '".$id."' && Pass = '".$pass."' )";
        $sqlstu = "SELECT UID FROM member WHERE (Name = '".$id."' && Pass = '".$pass."')";
        $resteach = mysqli_query($conn,$sqlteacher);
        $numteach = mysqli_num_rows($resteach);
        
        $resstu   = mysqli_query($conn,$sqlstu);
        $numstu = mysqli_num_rows($resstu);
        function logLogin($conn, $userId) {
            $sqlLog = "INSERT INTO auth_logs (user_id, action) 
                       VALUES ('".$userId."', 'login')";

            // ログを挿入
            if (!mysqli_query($conn, $sqlLog)) {
                echo "Error: " . $sqlLog . "<br>" . mysqli_error($conn);
            }
        }
        

        if ($numteach > 0){
            $rowteach = mysqli_fetch_array($resteach);
            $_SESSION["MemberID"] = $rowteach['TID'];
            $_SESSION["MemberName"] = $id;
            //現在時刻の取得
            $AccessDate = date('Y-m-d H:i:s');
            $_SESSION["AccessDate"] = $AccessDate;
            // ログイン成功ログを記録
            logLogin($conn, $_SESSION["MemberID"]);
            echo "上のif文に入りました";
            //echo "$resteach<br>";
            //header("location: ./teacher.php");
            header("location: ./teacher/teachertrue.php");
        }else if($numstu > 0){
            $rowstu = mysqli_fetch_array($resstu);
            $_SESSION["MemberID"] = $rowstu['UID'];
            $_SESSION["MemberName"] = $id;
            //現在時刻の取得
            $AccessDate = date('Y-m-d H:i:s');
            $_SESSION["AccessDate"] = $AccessDate;
            // ログイン成功ログを記録
            logLogin($conn, $_SESSION["MemberID"]);
            echo "下のif文に入りました<br>";
            echo "MemberID:{$_SESSION["MemberID"]}";
            header("location: ./student/student.php");
        }else{
            echo "<p> ID[ $id ]が存在しないか、IDとパスワードの組み合わせが不正です。<br>";
        }
    }
    mysqli_close($conn);
?>
<div class = "login-container">
    <img id = "log" src = "logo.png">
    <form method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
        <table>
            <tr>
                <td>ユーザーID</td>
                <td><input type="text" name="idtxt" class="input"></td>
            </tr>
            <tr>
                <td>パスワード</td>
                <td><input type="password" name="passtxt" class="input"></td>
            </tr>
            <tr>
                <td><input type="submit" name="b1" value="ログイン" class="button"></td>
            </tr>
        </table>
    </form>
</div>


</span>
</body>
</html>
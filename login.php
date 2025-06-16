<?php include 'lang.php'; 
    //session_start()  ここら辺の部分の元のやつはスマホの研究タブに写真有　1-5行目のところ
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">  

<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="style/login_style.css" type="text/css" />  
<title><?= translate('login.php_10行目_英単語並べ替え問題LMS') ?></title>
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
        $sqlstu = "SELECT UID FROM students WHERE (Name = '".$id."' AND Pass = '".$pass."')";
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
            //ここでClassID取得
            $get_classID_sql = "SELECT ClassID FROM students WHERE UID = ?";
            $stmt = $conn->prepare($get_classID_sql);
            $stmt->bind_param('i', $_SESSION["MemberID"]);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $_SESSION["ClassID"] = $row['ClassID'];
            //現在時刻の取得
            $AccessDate = date('Y-m-d H:i:s');
            $_SESSION["AccessDate"] = $AccessDate;
            //ここで所属する全てのグループIDを取得
            // 学習者が所属するグループIDを取得する
            $group_ids = array();
            $sql_groups = "SELECT group_id FROM group_members WHERE uid = ?";
            $stmt_groups = $conn->prepare($sql_groups);
            $stmt_groups->bind_param("i", $_SESSION["MemberID"]);
            $stmt_groups->execute();
            $result_groups = $stmt_groups->get_result();
            while ($row = $result_groups->fetch_assoc()) {
                $group_ids[] = $row['group_id'];
            }

            // グループに所属していない場合の対応（テスト取得時にマッチしない値を入れる）
            if(empty($group_ids)){
                // 存在しないグループID（例えば 0）を入れておく
                $group_ids[] = 0;
            }
            $_SESSION["GroupIDs"] = $group_ids;
            // ログイン成功ログを記録
            logLogin($conn, $_SESSION["MemberID"]);
            echo "下のif文に入りました<br>";
            echo "MemberID:{$_SESSION["MemberID"]}";
            header("location: ./student/student.php");
        }else{
            echo "<p> ID[ $id ]が存在しないか、IDとパスワードの組み合わせが不正です。<br>";
        }
    }
    // メモリ解放
    mysqli_close($conn);

    
?>

<div class="language-buttons"> 
     <!-- 言語選択ボタンの設定 -->
    <p><?= translate('言語を選択してください') ?>:</p>
    <a href="?lang=ja">日本語</a>
    <a href="?lang=en">English</a>
</div>

<div class = "login-container">
    <img id="log" src="<?= translate('logo_image_path') ?>">
    <form method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
        <table>
            <tr>
                <td><?= translate('login.php_126行目_ユーザーID') ?></td>
                <td><input type="text" name="idtxt" class="input"></td>
            </tr>
            <tr>
                <td><?= translate('login.php_130行目_パスワード') ?></td>
                <td><input type="password" name="passtxt" class="input"></td>
            </tr>
            <tr>
                <td><input type="submit" name="b1" value=<?= translate('login.php_134行目_ログイン') ?> class="button"></td>
            </tr>
        </table>
    </form>
</div>


</span>
</body>
</html>
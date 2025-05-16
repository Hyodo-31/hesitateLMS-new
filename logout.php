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
    function logLogout($conn, $userId) {
        $sqlLog = "INSERT INTO auth_logs (user_id, action) 
                   VALUES ('".$userId."', 'logout')";
    
        // ログを挿入
        if (!mysqli_query($conn, $sqlLog)) {
            echo "Error: " . $sqlLog . "<br>" . mysqli_error($conn);
        }
    }

    //ログアウト記録
    logLogout($conn, $_SESSION["MemberID"]);
    //セッションの破棄
    session_unset();
    session_destroy();
    
?>
<div class = "login-container">
    <img id = "log" src = "logo.png">
    <h2>正常にログアウトしました</h2>
    <a href="login.php">ログイン画面へ戻る</a>

</div>


</span>
</body>
</html>
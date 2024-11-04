<?php
    require "../dbc.php";

    // クエリパラメータからオフセットを取得
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // 次の5件のお知らせを取得
    $result = $conn->query("SELECT id, subject, content FROM notifications ORDER BY created_at DESC LIMIT 5 OFFSET $offset");
    while ($row = $result->fetch_assoc()) {
        echo "<div class='notification-item' data-content='{$row['content']}'>";
        echo "<p>{$row['subject']}</p>";
        echo "</div>";
    }
    $result->free();
    $conn->close();
?>
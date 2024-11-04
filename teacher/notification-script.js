jQuery(document).ready(function() {
    // ダブルクリックで別ページに移動して詳細表示
    jQuery(".notification-item").on("dblclick", function() {
        let notificationId = jQuery(this).data("id");
        // 詳細ページへ移動
        window.location.href = "notification-detail.php?id=" + notificationId;
    });
});

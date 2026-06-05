<?php
$teacher_page_title = $teacher_page_title ?? 'LMS 先生用ホーム画面';
$teacher_menu_base_path = $teacher_menu_base_path ?? '';
$teacher_menu_logout_path = $teacher_menu_logout_path ?? '../logout.php';
$teacher_menu_teacher_name = '先生';
$teacher_menu_teacher_id = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;

if ($teacher_menu_teacher_id && isset($conn) && $conn instanceof mysqli) {
    $stmt_teacher_menu = $conn->prepare("SELECT TName FROM teachers WHERE TID = ?");
    if ($stmt_teacher_menu) {
        $stmt_teacher_menu->bind_param("s", $teacher_menu_teacher_id);
        $stmt_teacher_menu->execute();
        $result_teacher_menu = $stmt_teacher_menu->get_result();
        if ($row_teacher_menu = $result_teacher_menu->fetch_assoc()) {
            $teacher_menu_teacher_name = $row_teacher_menu['TName'];
        }
        $stmt_teacher_menu->close();
    }
}

if (!function_exists('teacher_menu_path')) {
    function teacher_menu_path(string $path): string
    {
        if ($path === '#' || str_starts_with($path, '#') || str_starts_with($path, '/') || preg_match('/^https?:\/\//', $path)) {
            return htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        }

        $base_path = $GLOBALS['teacher_menu_base_path'] ?? '';
        return htmlspecialchars($base_path . $path, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('teacher_menu_is_current')) {
    function teacher_menu_is_current(array $pages): bool
    {
        $current_page = basename(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '');
        return in_array($current_page, $pages, true);
    }
}

if (!function_exists('teacher_menu_link_attrs')) {
    function teacher_menu_link_attrs(array $pages): string
    {
        return teacher_menu_is_current($pages) ? ' class="is-active" aria-current="page"' : '';
    }
}

if (!function_exists('teacher_menu_group_class')) {
    function teacher_menu_group_class(array $pages): string
    {
        $classes = ['sidebar-item', 'has-submenu'];
        if (teacher_menu_is_current($pages)) {
            $classes[] = 'open';
        }
        return htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8');
    }
}
?>
<div id="sidebar" class="sidebar" aria-label="教師メニュー">
    <div class="sidebar-header">
        <h3>メニュー</h3>
        <button id="sidebar-close" class="sidebar-close-button" type="button" aria-label="メニューを閉じる">&times;</button>
    </div>
    <ul>
        <li><a href="<?= teacher_menu_path('teachertrue.php') ?>"<?= teacher_menu_link_attrs(['teachertrue.php']) ?>>ホーム</a></li>
        <li class="<?= teacher_menu_group_class(['machineLearning_sample.php', 'machineLearning_word.php', 'feature_correlation.php', 'clustering.php']) ?>">
            <a href="#" class="submenu-toggle">迷い推定・機械学習関連</a>
            <ul class="submenu">
                <li><a href="<?= teacher_menu_path('machineLearning_sample.php') ?>"<?= teacher_menu_link_attrs(['machineLearning_sample.php']) ?>>迷い推定・機械学習（問題単位）</a></li>
                <li><a href="<?= teacher_menu_path('machineLearning_word.php') ?>"<?= teacher_menu_link_attrs(['machineLearning_word.php']) ?>>迷い推定・機械学習（単語単位）</a></li>
                <li><a href="<?= teacher_menu_path('feature_correlation.php') ?>"<?= teacher_menu_link_attrs(['feature_correlation.php']) ?>>特徴量検索機能</a></li>
                <li><a href="<?= teacher_menu_path('clustering.php') ?>"<?= teacher_menu_link_attrs(['clustering.php']) ?>>クラスタリング</a></li>
            </ul>
        </li>
        <li class="<?= teacher_menu_group_class(['create-notification.php', 'register-student.php', 'register-classteacher.php']) ?>">
            <a href="#" class="submenu-toggle">新規登録</a>
            <ul class="submenu">
                <li><a href="<?= teacher_menu_path('create-notification.php') ?>"<?= teacher_menu_link_attrs(['create-notification.php']) ?>>お知らせ作成</a></li>
                <li><a href="<?= teacher_menu_path('register-student.php') ?>"<?= teacher_menu_link_attrs(['register-student.php']) ?>>新規学習者登録</a></li>
                <li><a href="<?= teacher_menu_path('register-classteacher.php') ?>"<?= teacher_menu_link_attrs(['register-classteacher.php']) ?>>クラス登録</a></li>
            </ul>
        </li>
        <li class="<?= teacher_menu_group_class([]) ?>">
            <a href="#" class="submenu-toggle">新規問題作成</a>
            <ul class="submenu">
                <li><a href="<?= teacher_menu_path('create/new.php?mode=0') ?>">新規英語問題作成</a></li>
                <li><a href="<?= teacher_menu_path('create_ja/new.php?mode=0') ?>">新規日本語問題作成</a></li>
            </ul>
        </li>
        <li class="<?= teacher_menu_group_class(['create-test.php', 'create-test-ja.php']) ?>">
            <a href="#" class="submenu-toggle">新規テスト作成</a>
            <ul class="submenu">
                <li><a href="<?= teacher_menu_path('create-test.php') ?>"<?= teacher_menu_link_attrs(['create-test.php']) ?>>新規英語テスト作成</a></li>
                <li><a href="<?= teacher_menu_path('create-test-ja.php') ?>"<?= teacher_menu_link_attrs(['create-test-ja.php']) ?>>新規日本語テスト作成</a></li>
            </ul>
        </li>
        <li class="<?= teacher_menu_group_class(['create-student-group.php']) ?>">
            <a href="#" class="submenu-toggle">学習者関連</a>
            <ul class="submenu">
                <li><a href="<?= teacher_menu_path('#') ?>">学習者グラフ表示</a></li>
                <li><a href="<?= teacher_menu_path('create-student-group.php') ?>"<?= teacher_menu_link_attrs(['create-student-group.php']) ?>>学習者グルーピング作成</a></li>
            </ul>
        </li>
    </ul>
</div>
<div id="sidebar-backdrop" class="sidebar-backdrop"></div>

<header class="fixed-header">
    <div class="header-left">
        <button id="menu-toggle" class="menu-button" type="button" aria-controls="sidebar" aria-expanded="false">☰</button>
        <h1><?= htmlspecialchars($teacher_page_title, ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <div class="header-right">
        <span class="user-name"><?= htmlspecialchars($teacher_menu_teacher_name, ENT_QUOTES, 'UTF-8') ?> がログイン中</span>
        <a href="<?= htmlspecialchars($teacher_menu_logout_path, ENT_QUOTES, 'UTF-8') ?>" class="logout-link">ログアウト</a>
    </div>
</header>

<script>
    (function () {
        function initTeacherMenu() {
            const menuToggle = document.getElementById('menu-toggle');
            const sidebarClose = document.getElementById('sidebar-close');
            const backdrop = document.getElementById('sidebar-backdrop');
            const sidebar = document.getElementById('sidebar');

            if (!menuToggle || !sidebarClose || !backdrop || !sidebar) return;

            function openSidebar() {
                document.body.classList.add('sidebar-open');
                menuToggle.setAttribute('aria-expanded', 'true');
            }

            function closeSidebar() {
                document.body.classList.remove('sidebar-open');
                menuToggle.setAttribute('aria-expanded', 'false');
                sidebar.querySelectorAll('.has-submenu.open').forEach(submenu => {
                    submenu.classList.remove('open');
                });
            }

            menuToggle.addEventListener('click', openSidebar);
            sidebarClose.addEventListener('click', closeSidebar);
            backdrop.addEventListener('click', closeSidebar);

            sidebar.addEventListener('click', function (e) {
                const toggle = e.target.closest('.submenu-toggle');
                if (!toggle) return;

                e.preventDefault();
                toggle.parentElement.classList.toggle('open');
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTeacherMenu);
        } else {
            initTeacherMenu();
        }
    })();
</script>

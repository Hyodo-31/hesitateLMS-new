<?php
$teacher_page_title = $teacher_page_title ?? 'LMS 先生用ホーム画面';
$teacher_menu_base_path = $teacher_menu_base_path ?? '';
$teacher_menu_logout_path = $teacher_menu_logout_path ?? '../logout.php';
$teacher_menu_teacher_name = '先生';
$teacher_menu_teacher_id = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;
$teacher_menu_has_assigned_class = false;
$teacher_menu_show_word_ml = false;

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

    $stmt_teacher_menu_classes = $conn->prepare("SELECT 1 FROM classteacher WHERE TID = ? LIMIT 1");
    if ($stmt_teacher_menu_classes) {
        $stmt_teacher_menu_classes->bind_param("s", $teacher_menu_teacher_id);
        $stmt_teacher_menu_classes->execute();
        $stmt_teacher_menu_classes->store_result();
        $teacher_menu_has_assigned_class = $stmt_teacher_menu_classes->num_rows > 0;
        $stmt_teacher_menu_classes->close();
    }
}

if (!function_exists('teacher_menu_path')) {
    function teacher_menu_path(string $path): string
    {
        if ($path === '#' || str_starts_with($path, '#') || str_starts_with($path, '/') || preg_match('/^https?:\/\//', $path)) {
            return htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        }

        $base_path = $GLOBALS['teacher_menu_base_path'] ?? '';
        $has_assigned_class = $GLOBALS['teacher_menu_has_assigned_class'] ?? true;
        $allowed_before_class_registration = [
            'register-classteacher.php' => true,
        ];
        $path_page = basename(parse_url($path, PHP_URL_PATH) ?: $path);
        if (!$has_assigned_class && !isset($allowed_before_class_registration[$path_page])) {
            return htmlspecialchars($base_path . 'register-classteacher.php?required=1', ENT_QUOTES, 'UTF-8');
        }

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
                <?php if ($teacher_menu_show_word_ml): ?>
                <li><a href="<?= teacher_menu_path('machineLearning_word.php') ?>"<?= teacher_menu_link_attrs(['machineLearning_word.php']) ?>>迷い推定・機械学習（単語単位）</a></li>
                <?php endif; ?>
                <li><a href="<?= teacher_menu_path('feature_correlation.php') ?>" target="_blank" rel="noopener noreferrer"<?= teacher_menu_link_attrs(['feature_correlation.php']) ?>>特徴量相関表示</a></li>
                <li><a href="<?= teacher_menu_path('clustering.php') ?>" target="_blank" rel="noopener noreferrer"<?= teacher_menu_link_attrs(['clustering.php']) ?>>クラスタリング</a></li>
            </ul>
        </li>
        <li class="<?= teacher_menu_group_class(['create-notification.php', 'register-student.php', 'register-classteacher.php']) ?>">
            <a href="#" class="submenu-toggle">新規登録</a>
            <ul class="submenu">
                <li><a href="<?= teacher_menu_path('create-notification.php') ?>"<?= teacher_menu_link_attrs(['create-notification.php']) ?>>お知らせ作成</a></li>
                <li><a href="<?= teacher_menu_path('register-student.php') ?>"<?= teacher_menu_link_attrs(['register-student.php']) ?>>新規学習者登録</a></li>
                <li><a href="<?= teacher_menu_path('register-classteacher.php') ?>"<?= teacher_menu_link_attrs(['register-classteacher.php']) ?>>グループ(クラス)登録</a></li>
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
        <li class="<?= teacher_menu_group_class(['create-student-group.php', 'submit-student-group.php', 'submit-update-student-class.php']) ?>">
            <a href="#" class="submenu-toggle">学習者関連</a>
            <ul class="submenu">
                <li><a href="<?= teacher_menu_path('create-student-group.php') ?>"<?= teacher_menu_link_attrs(['create-student-group.php', 'submit-student-group.php', 'submit-update-student-class.php']) ?>>学習者グルーピング作成</a></li>
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
        const transitionTeacherId = <?= json_encode((string)($teacher_menu_teacher_id ?? ''), JSON_UNESCAPED_UNICODE) ?>;
        const transitionPage = <?= json_encode(basename(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: ''), JSON_UNESCAPED_UNICODE) ?>;
        const transitionMarkerKey = `hesitateLms:correlationToClustering:${transitionTeacherId}`;
        const transitionCountKey = `hesitateLms:correlationToClusteringCount:${transitionTeacherId}`;
        const transitionMarkerLifetimeMs = 10000;

        function readTransitionCount() {
            if (!transitionTeacherId) return 0;
            try {
                const count = Number(sessionStorage.getItem(transitionCountKey) || 0);
                return Number.isInteger(count) && count > 0 ? Math.min(count, 10000) : 0;
            } catch (error) {
                return 0;
            }
        }

        function writeTransitionCount(count) {
            if (!transitionTeacherId) return;
            try {
                sessionStorage.setItem(
                    transitionCountKey,
                    String(Math.max(0, Math.min(10000, Number(count) || 0)))
                );
            } catch (error) {
                // ブラウザー保存が利用できない場合も画面操作は継続する。
            }
        }

        window.TeacherTabTransition = {
            getCorrelationToClusteringCount() {
                return readTransitionCount();
            },
            acknowledgeCorrelationToClusteringCount(usedCount) {
                const used = Math.max(0, Number(usedCount) || 0);
                writeTransitionCount(Math.max(0, readTransitionCount() - used));
            },
        };

        function markCorrelationTabHidden() {
            if (!transitionTeacherId || transitionPage !== 'feature_correlation.php') return;
            try {
                localStorage.setItem(transitionMarkerKey, JSON.stringify({
                    teacher_id: transitionTeacherId,
                    source: 'feature_correlation.php',
                    marker_id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
                    hidden_at: Date.now(),
                }));
            } catch (error) {
                // ブラウザー保存が利用できない場合は移動回数0として扱う。
            }
        }

        function consumeCorrelationTransition() {
            if (!transitionTeacherId || transitionPage !== 'clustering.php' || document.hidden) return;
            try {
                const rawMarker = localStorage.getItem(transitionMarkerKey);
                if (!rawMarker) return;
                localStorage.removeItem(transitionMarkerKey);
                const marker = JSON.parse(rawMarker);
                const age = Date.now() - Number(marker?.hidden_at || 0);
                if (marker?.teacher_id !== transitionTeacherId
                    || marker?.source !== 'feature_correlation.php'
                    || age < 0
                    || age > transitionMarkerLifetimeMs) {
                    return;
                }
                writeTransitionCount(readTransitionCount() + 1);
            } catch (error) {
                try {
                    localStorage.removeItem(transitionMarkerKey);
                } catch (storageError) {
                    // 保存領域へアクセスできなくても画面操作は継続する。
                }
            }
        }

        function initTeacherTabTransition() {
            if (!transitionTeacherId) return;
            if (transitionPage === 'feature_correlation.php') {
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) markCorrelationTabHidden();
                });
                return;
            }
            if (transitionPage === 'clustering.php') {
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) consumeCorrelationTransition();
                });
                window.addEventListener('focus', consumeCorrelationTransition);
                consumeCorrelationTransition();
            }
        }

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
            document.addEventListener('DOMContentLoaded', () => {
                initTeacherMenu();
                initTeacherTabTransition();
            });
        } else {
            initTeacherMenu();
            initTeacherTabTransition();
        }
    })();
</script>

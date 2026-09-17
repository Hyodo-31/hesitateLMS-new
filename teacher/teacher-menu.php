<?php
$teacher_page_title = $teacher_page_title ?? 'LMS 先生用ホーム画面';
$teacher_menu_base_path = $teacher_menu_base_path ?? '';
$teacher_menu_logout_path = $teacher_menu_logout_path ?? '../logout.php';
$teacher_menu_teacher_name = '先生';
$teacher_menu_teacher_id = $_SESSION['TID'] ?? $_SESSION['MemberID'] ?? null;
$teacher_menu_has_assigned_class = false;
$teacher_menu_show_word_ml = false;
require_once __DIR__ . '/hesitation-estimation-state.php';

if (empty($_SESSION['teacher_hesitation_toggle_csrf']) || !is_string($_SESSION['teacher_hesitation_toggle_csrf'])) {
    $_SESSION['teacher_hesitation_toggle_csrf'] = bin2hex(random_bytes(32));
}
$teacher_menu_hesitation_suppressed = teacher_hesitation_is_suppressed();

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
            'toggle-hesitation-estimation.php' => true,
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
                <li>
                    <button
                        type="button"
                        id="hesitation-estimation-toggle"
                        class="hesitation-estimation-toggle<?= $teacher_menu_hesitation_suppressed ? ' is-suppressed' : '' ?>"
                        data-endpoint="<?= teacher_menu_path('toggle-hesitation-estimation.php') ?>"
                        data-csrf-token="<?= htmlspecialchars($_SESSION['teacher_hesitation_toggle_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                        aria-pressed="<?= $teacher_menu_hesitation_suppressed ? 'true' : 'false' ?>"
                    ><?= $teacher_menu_hesitation_suppressed ? '未推定状態を解除' : '未推定状態にする' ?></button>
                </li>
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
        const transitionStateVersionKey = `${transitionCountKey}:version`;
        const transitionStateVersion = '3';
        const correlationModes = ['understand', 'hesitation_degree', 'feature_pair'];
        const countedCorrelationTriggers = new Set([
            'uid_selection',
            'a_university_2019',
            'mode_change',
            'feature_x_change',
            'feature_y_change',
            'prediction_filter_change',
            'display_button',
            'ranking_click',
            'legacy_filter_change',
        ]);
        const hesitationStateChangedKey = `hesitateLms:hesitationStateChanged:${transitionTeacherId}`;
        const transitionMarkerLifetimeMs = 10000;
        let correlationActivitySnapshot = null;

        function normalizeTransitionCount(value) {
            const count = Number(value);
            return Number.isInteger(count) && count > 0 ? Math.min(count, 10000) : 0;
        }

        function transitionModeKey(mode) {
            return `${transitionCountKey}:${mode}`;
        }

        function ensureTransitionStateVersion() {
            if (!transitionTeacherId) return;
            try {
                if (sessionStorage.getItem(transitionStateVersionKey) === transitionStateVersion) return;
                sessionStorage.removeItem(transitionCountKey);
                correlationModes.forEach((mode) => sessionStorage.removeItem(transitionModeKey(mode)));
                sessionStorage.setItem(transitionStateVersionKey, transitionStateVersion);
            } catch (error) {
                // ブラウザー保存が利用できない場合も画面操作は継続する。
            }
        }

        function emptyTransitionSnapshot() {
            return {
                total: 0,
                understand: 0,
                hesitation_degree: 0,
                feature_pair: 0,
            };
        }

        correlationActivitySnapshot = emptyTransitionSnapshot();

        function normalizedSnapshot(rawSnapshot) {
            const snapshot = emptyTransitionSnapshot();
            let remaining = 10000;
            correlationModes.forEach((mode) => {
                snapshot[mode] = Math.min(
                    remaining,
                    normalizeTransitionCount(rawSnapshot?.[mode] || 0)
                );
                remaining -= snapshot[mode];
            });
            snapshot.total = 10000 - remaining;
            return snapshot;
        }

        function markerTransitionSnapshot(marker) {
            if (marker?.correlation_counts && typeof marker.correlation_counts === 'object') {
                return normalizedSnapshot(marker.correlation_counts);
            }
            const legacySnapshot = emptyTransitionSnapshot();
            if (correlationModes.includes(marker?.analysis_mode)) {
                legacySnapshot[marker.analysis_mode] = 1;
                legacySnapshot.total = 1;
            }
            return legacySnapshot;
        }

        function addTransitionSnapshots(currentSnapshot, addedSnapshot) {
            const combined = normalizedSnapshot(currentSnapshot);
            let remaining = Math.max(0, 10000 - combined.total);
            correlationModes.forEach((mode) => {
                if (remaining <= 0) return;
                const added = Math.min(
                    remaining,
                    normalizeTransitionCount(addedSnapshot?.[mode] || 0)
                );
                combined[mode] += added;
                remaining -= added;
            });
            combined.total = combined.understand
                + combined.hesitation_degree
                + combined.feature_pair;
            return combined;
        }

        function readTransitionSnapshot() {
            const snapshot = emptyTransitionSnapshot();
            if (!transitionTeacherId) return snapshot;
            try {
                let remaining = 10000;
                correlationModes.forEach((mode) => {
                    snapshot[mode] = Math.min(
                        remaining,
                        normalizeTransitionCount(sessionStorage.getItem(transitionModeKey(mode)) || 0)
                    );
                    remaining -= snapshot[mode];
                });
                snapshot.total = 10000 - remaining;
            } catch (error) {
                return emptyTransitionSnapshot();
            }
            return snapshot;
        }

        function writeTransitionSnapshot(snapshot) {
            if (!transitionTeacherId) return;
            try {
                const normalized = emptyTransitionSnapshot();
                let remaining = 10000;
                correlationModes.forEach((mode) => {
                    normalized[mode] = Math.min(
                        remaining,
                        normalizeTransitionCount(snapshot?.[mode] || 0)
                    );
                    remaining -= normalized[mode];
                    sessionStorage.setItem(transitionModeKey(mode), String(normalized[mode]));
                });
                normalized.total = 10000 - remaining;
                sessionStorage.setItem(transitionCountKey, String(normalized.total));
            } catch (error) {
                // ブラウザー保存が利用できない場合も画面操作は継続する。
            }
        }

        function readTransitionCount() {
            return readTransitionSnapshot().total;
        }

        function writeTransitionCount(count) {
            if (normalizeTransitionCount(count) === 0) {
                writeTransitionSnapshot(emptyTransitionSnapshot());
            }
        }

        window.TeacherTabTransition = {
            recordCorrelationActivity(mode, trigger) {
                if (transitionPage !== 'feature_correlation.php'
                    || !correlationModes.includes(mode)
                    || !countedCorrelationTriggers.has(trigger)
                    || correlationActivitySnapshot.total >= 10000) {
                    return;
                }
                correlationActivitySnapshot[mode] += 1;
                correlationActivitySnapshot.total += 1;
            },
            getCorrelationToClusteringCount() {
                return readTransitionCount();
            },
            getCorrelationToClusteringSnapshot() {
                return readTransitionSnapshot();
            },
            acknowledgeCorrelationToClusteringCount(usedCount) {
                const used = Math.max(0, Number(usedCount) || 0);
                if (used >= readTransitionCount()) {
                    writeTransitionCount(0);
                }
            },
            acknowledgeCorrelationToClusteringSnapshot(usedSnapshot) {
                const current = readTransitionSnapshot();
                correlationModes.forEach((mode) => {
                    current[mode] = Math.max(
                        0,
                        current[mode] - normalizeTransitionCount(usedSnapshot?.[mode] || 0)
                    );
                });
                writeTransitionSnapshot(current);
            },
        };

        function markCorrelationTabHidden() {
            if (!transitionTeacherId || transitionPage !== 'feature_correlation.php') return;
            const snapshot = normalizedSnapshot(correlationActivitySnapshot);
            correlationActivitySnapshot = emptyTransitionSnapshot();
            try {
                // A marker represents only the correlation operations performed
                // during the visibility period that has just ended.
                localStorage.removeItem(transitionMarkerKey);
                if (snapshot.total === 0) return;
                localStorage.setItem(transitionMarkerKey, JSON.stringify({
                    teacher_id: transitionTeacherId,
                    source: 'feature_correlation.php',
                    correlation_counts: snapshot,
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
                const markerSnapshot = markerTransitionSnapshot(marker);
                if (marker?.teacher_id !== transitionTeacherId
                    || marker?.source !== 'feature_correlation.php'
                    || markerSnapshot.total === 0
                    || age < 0
                    || age > transitionMarkerLifetimeMs) {
                    return;
                }
                writeTransitionSnapshot(addTransitionSnapshots(
                    readTransitionSnapshot(),
                    markerSnapshot
                ));
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
            ensureTransitionStateVersion();
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

            const hesitationToggle = document.getElementById('hesitation-estimation-toggle');
            hesitationToggle?.addEventListener('click', async function () {
                if (hesitationToggle.disabled) return;
                hesitationToggle.disabled = true;
                try {
                    const body = new URLSearchParams({
                        csrf_token: hesitationToggle.dataset.csrfToken || '',
                    });
                    const response = await fetch(hesitationToggle.dataset.endpoint || 'toggle-hesitation-estimation.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                        body: body.toString(),
                        credentials: 'same-origin',
                    });
                    const result = await response.json();
                    if (!response.ok || result.ok !== true) {
                        throw new Error(result.error || '未推定状態を切り替えられませんでした。');
                    }
                    try {
                        localStorage.setItem(hesitationStateChangedKey, String(Date.now()));
                    } catch (storageError) {
                        // 他タブへの通知ができなくても、このタブの切替は有効。
                    }
                    window.location.reload();
                } catch (error) {
                    console.warn('未推定状態の切替に失敗しました。', error);
                    window.alert(error instanceof Error ? error.message : '未推定状態を切り替えられませんでした。');
                    hesitationToggle.disabled = false;
                }
            });

            window.addEventListener('storage', function (event) {
                if (transitionTeacherId && event.key === hesitationStateChangedKey && event.newValue) {
                    window.location.reload();
                }
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

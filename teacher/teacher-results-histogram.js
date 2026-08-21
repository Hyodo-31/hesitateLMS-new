(function () {
    'use strict';

    const responseCache = new Map();
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[char]));
    const compareIds = (a, b) => String(a).localeCompare(String(b), 'ja', { numeric: true });
    const union = (left, right) => new Set([...left, ...right]);
    const intersection = (left, right) => new Set([...left].filter((value) => right.has(value)));
    const complement = (source, universe) => new Set([...universe].filter((value) => !source.has(value)));

    function evaluateExpression(tokens, resolveCondition, universe, emptyResult) {
        if (!tokens.length) return emptyResult();
        let index = 0;
        const primary = () => {
            const token = tokens[index];
            if (!token) throw new Error('条件が途中で終わっています。');
            if (token.kind === 'not') {
                index += 1;
                return complement(primary(), universe);
            }
            if (token.kind === 'open') {
                index += 1;
                const result = orExpression();
                if (tokens[index]?.kind !== 'close') throw new Error('閉じ括弧を置いてください。');
                index += 1;
                return result;
            }
            if (token.kind === 'condition') {
                index += 1;
                const resolved = resolveCondition(token.value);
                if (!(resolved instanceof Set)) throw new Error('対象を選択してください。');
                return resolved;
            }
            throw new Error('条件または括弧を置いてください。');
        };
        const andExpression = () => {
            let result = primary();
            while (tokens[index]?.kind === 'and') {
                index += 1;
                result = intersection(result, primary());
            }
            return result;
        };
        const orExpression = () => {
            let result = andExpression();
            while (tokens[index]?.kind === 'or') {
                index += 1;
                result = union(result, andExpression());
            }
            return result;
        };
        const result = orExpression();
        if (index !== tokens.length) throw new Error('式の並びを確認してください。');
        return result;
    }

    function formatNumber(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) return '-';
        const absolute = Math.abs(number);
        const digits = absolute >= 100 ? 0 : absolute >= 10 ? 1 : absolute < 1 ? 4 : 2;
        return new Intl.NumberFormat('ja-JP', { maximumFractionDigits: digits }).format(number);
    }

    function quantile(sorted, ratio) {
        if (!sorted.length) return 0;
        const position = (sorted.length - 1) * ratio;
        const lower = Math.floor(position);
        const upper = Math.ceil(position);
        if (lower === upper) return sorted[lower];
        return sorted[lower] + ((sorted[upper] - sorted[lower]) * (position - lower));
    }

    function niceStep(raw) {
        if (!Number.isFinite(raw) || raw <= 0) return 1;
        const exponent = Math.floor(Math.log10(raw));
        const magnitude = 10 ** exponent;
        const fraction = raw / magnitude;
        const niceFraction = fraction < 1.5 ? 1 : fraction < 2.25 ? 2 : fraction < 3.5 ? 2.5 : fraction < 7.5 ? 5 : 10;
        return niceFraction * magnitude;
    }

    function buildHistogram(rawPoints, percentage) {
        const points = rawPoints
            .map((point) => ({ id: String(point.id), value: Number(point.value) }))
            .filter((point) => Number.isFinite(point.value))
            .sort((a, b) => a.value - b.value);
        if (!points.length) return null;
        const values = points.map((point) => point.value);
        const min = values[0];
        const max = values[values.length - 1];
        if (min === max) {
            return { labels: [formatNumber(min)], bins: [{ start: min, end: max, members: points }], counts: [points.length], min, max, step: 0 };
        }
        const range = max - min;
        const iqr = quantile(values, 0.75) - quantile(values, 0.25);
        const fdWidth = iqr > 0 ? (2 * iqr) / Math.cbrt(values.length) : 0;
        const fdBins = fdWidth > 0 ? Math.ceil(range / fdWidth) : 0;
        const targetBins = Math.max(Math.min(5, values.length), Math.min(12, Math.max(fdBins, Math.ceil(Math.log2(values.length) + 1))));
        let step = niceStep(range / targetBins);
        let lower = Math.floor(min / step) * step;
        let upper = Math.ceil(max / step) * step;
        if (percentage) {
            lower = Math.max(0, lower);
            upper = Math.min(100, upper);
        } else if (min >= 0) {
            lower = Math.max(0, lower);
        }
        if (upper <= lower) upper = lower + step;
        let binCount = Math.max(1, Math.ceil((upper - lower) / step));
        while (binCount > 12) {
            step = niceStep(step * 1.5);
            lower = Math.floor(min / step) * step;
            upper = Math.ceil(max / step) * step;
            if (percentage) {
                lower = Math.max(0, lower);
                upper = Math.min(100, upper);
            }
            binCount = Math.max(1, Math.ceil((upper - lower) / step));
        }
        const bins = Array.from({ length: binCount }, (_, index) => ({
            start: lower + (step * index), end: lower + (step * (index + 1)), members: [],
        }));
        points.forEach((point) => {
            const rawIndex = point.value === upper ? binCount - 1 : Math.floor((point.value - lower) / step);
            bins[Math.max(0, Math.min(binCount - 1, rawIndex))].members.push(point);
        });
        return {
            labels: bins.map((bin) => `${formatNumber(bin.start)}〜${formatNumber(bin.end)}`),
            bins, counts: bins.map((bin) => bin.members.length), min, max, step,
        };
    }

    async function loadData(scope, testId) {
        const key = scope === 'test' ? `test:${testId || ''}` : scope;
        if (responseCache.has(key)) return responseCache.get(key);
        const request = (async () => {
            const body = new FormData();
            body.append('scope', scope);
            if (scope === 'test') body.append('test_id', testId || '');
            const response = await fetch('teacher-results-histogram-data.php', { method: 'POST', body });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) throw new Error(payload?.message || 'ヒストグラムデータの取得に失敗しました。');
            return payload;
        })();
        responseCache.set(key, request);
        try {
            return await request;
        } catch (error) {
            responseCache.delete(key);
            throw error;
        }
    }

    class ResultsHistogram {
        constructor(options) {
            this.root = typeof options.root === 'string' ? document.querySelector(options.root) : options.root;
            this.scope = options.scope;
            this.getTestId = options.getTestId || (() => '');
            this.onSubmit = options.onSubmit;
            this.onDetailStudentChange = options.onDetailStudentChange || (() => {});
            this.features = Object.entries(options.features || {}).map(([value, label]) => ({ value, label }));
            this.featureMeta = options.featureMeta || {};
            this.groups = options.groups || [];
            this.groupStudents = options.groupStudents || {};
            this.data = null;
            this.contextKey = '';
            this.loaded = false;
            this.loading = false;
            this.expanded = false;
            this.sourceTokens = [];
            this.barTokens = { uid: [], wid: [] };
            this.barConditions = { uid: new Map(), wid: new Map() };
            this.sourceUids = new Set();
            this.pendingWids = new Set();
            this.appliedWids = new Set();
            this.widLogicResult = null;
            this.uidResult = new Set();
            this.resultChecks = new Map();
            this.detailUid = '';
            this.widPending = false;
            this.charts = { uidFeature: null, widFeature: null };
            this.contentBound = false;
            this.renderShell();
            this.bindShell();
        }

        id(role) { return `${this.root.id}-${role}`; }
        q(role) { return this.root.querySelector(`[data-role="${role}"]`); }
        qa(role) { return Array.from(this.root.querySelectorAll(`[data-role="${role}"]`)); }
        meta(feature) { return this.featureMeta[feature] || { displayScale: 1, unit: '' }; }
        displayValue(feature, value) {
            if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return null;
            return Number(value) * Number(this.meta(feature).displayScale || 1);
        }

        renderShell() {
            const detailText = this.scope === 'student'
                ? '縦棒から学習者候補を絞り、1人を選んで問題分布と詳細結果を表示します。'
                : '縦棒からUID・WIDを抽出し、既存と同じ形式で学習者結果を表示します。';
            this.root.classList.add('trh-root');
            this.root.innerHTML = `
                <button type="button" class="trh-toggle" data-role="toggle" aria-expanded="false">
                    <span><strong>ヒストグラムでの検索</strong><small>${detailText}</small></span><span class="trh-toggle-icon" aria-hidden="true"></span>
                </button>
                <div class="trh-panel" data-role="panel" hidden>
                    <p class="trh-status" data-role="status">パネルを開くとデータを読み込みます。</p>
                    <div data-role="content" hidden></div>
                </div>`;
        }

        bindShell() {
            this.q('toggle').addEventListener('click', () => {
                this.expanded = !this.expanded;
                this.q('toggle').setAttribute('aria-expanded', String(this.expanded));
                this.q('panel').hidden = !this.expanded;
                if (this.expanded) this.ensureLoaded();
                else Object.values(this.charts).forEach((chart) => chart?.resize());
            });
        }

        currentContextKey() {
            return this.scope === 'test' ? `test:${this.getTestId() || ''}` : this.scope;
        }

        async resetContext() {
            const nextKey = this.currentContextKey();
            if (nextKey === this.contextKey && this.loaded) return;
            this.destroyCharts();
            this.loaded = false;
            this.data = null;
            this.contextKey = nextKey;
            this.resetState();
            this.q('content').hidden = true;
            this.q('status').hidden = false;
            this.q('status').textContent = this.scope === 'test' && !this.getTestId()
                ? '先にテストを選択してください。'
                : 'パネルを開くとデータを読み込みます。';
            if (this.expanded) await this.ensureLoaded();
        }

        resetState() {
            this.sourceTokens = [];
            this.barTokens = { uid: [], wid: [] };
            this.barConditions = { uid: new Map(), wid: new Map() };
            this.sourceUids = new Set();
            this.pendingWids = new Set();
            this.appliedWids = new Set();
            this.widLogicResult = null;
            this.uidResult = new Set();
            this.resultChecks.clear();
            this.detailUid = '';
            this.widPending = false;
        }

        async ensureLoaded() {
            if (this.loaded || this.loading) return;
            const testId = this.scope === 'test' ? this.getTestId() : '';
            if (this.scope === 'test' && !testId) {
                this.q('status').textContent = '先にテストを選択してください。';
                return;
            }
            this.loading = true;
            const requestedKey = this.currentContextKey();
            this.q('status').hidden = false;
            this.q('status').classList.remove('is-error');
            this.q('status').textContent = 'ヒストグラムデータを読み込んでいます...';
            try {
                const data = await loadData(this.scope, testId);
                if (requestedKey !== this.currentContextKey()) return;
                this.data = data;
                this.contextKey = requestedKey;
                this.resetState();
                this.sourceUids = new Set(data.students.map((student) => String(student.uid)));
                this.appliedWids = new Set(data.wids.map((wid) => String(wid.WID)));
                this.pendingWids = new Set(this.appliedWids);
                this.renderContent();
                this.loaded = true;
                this.q('status').hidden = true;
                this.q('content').hidden = false;
                requestAnimationFrame(() => this.renderCharts());
            } catch (error) {
                this.q('status').textContent = error.message || 'ヒストグラムデータの取得に失敗しました。';
                this.q('status').classList.add('is-error');
            } finally {
                this.loading = false;
                if (this.expanded && !this.loaded && requestedKey !== this.currentContextKey()) {
                    queueMicrotask(() => this.ensureLoaded());
                }
            }
        }

        renderContent() {
            const featureOptions = `<optgroup label="特徴量">${this.features.map((item) => `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)} (${escapeHtml(item.value)})</option>`).join('')}</optgroup><optgroup label="結果指標"><option value="__accuracy">正答率 (%)</option><option value="__hesitation">迷い率 (%)</option></optgroup>`;
            this.q('content').innerHTML = `
                <div class="trh-heading"><h4>ヒストグラムでの検索</h4><p>特徴量・正答率・迷い率の縦棒を選択し、論理式で検索対象を作成します。</p></div>
                <details class="trh-step trh-step-wid" data-role="wid-step" open>
                    <summary class="trh-step-summary"><span class="trh-step-number">1</span><span><strong>WIDに関する検索</strong><small>問題を選び、WID分布の縦棒から条件を作成します。</small></span><span class="trh-step-icon" aria-hidden="true"></span></summary>
                    <div class="trh-step-body">
                        <section class="trh-stage"><div class="trh-stage-heading"><span>①</span><div><h5>WIDの選択</h5><p>UID検索に使用する問題をチェックしてください。</p></div></div><div class="trh-actions"><button type="button" data-action="wid-all">すべて選択</button><button type="button" data-action="wid-none">すべて解除</button></div><div data-role="wid-list"></div></section>
                        <section class="trh-stage"><div class="trh-stage-heading"><span>②</span><div><h5>WIDのヒストグラム</h5><p>正答率・迷い率も「特徴量・指標」から選択できます。</p></div></div><article class="trh-chart-card"><div class="trh-chart-controls"><label>特徴量・指標<select data-role="wid-feature">${featureOptions}</select></label><label>対象UID<select data-role="wid-feature-uids"><option value="all">全UID</option><option value="checked">選択UID</option></select></label><label>対象WID<select data-role="wid-feature-wids"><option value="checked" selected>チェック中のWID</option><option value="all">全WID</option></select></label></div><div class="trh-canvas"><canvas data-role="wid-feature-chart"></canvas></div><p data-role="wid-feature-summary"></p></article></section>
                        <section class="trh-stage"><div class="trh-stage-heading"><span>③</span><div><h5>WID縦棒の論理式</h5><p>縦棒を選び、AND・OR・NOT・括弧で組み合わせた結果をWIDチェックへ反映します。</p></div></div><div data-role="wid-logic"></div><div data-role="wid-saved"></div></section>
                        <div class="trh-flow-action"><div><strong>WIDの選択を確定</strong><span data-role="wid-apply-summary">${this.appliedWids.size}件のWIDをUID検索へ反映しています。</span></div><button type="button" class="trh-primary" data-action="apply-wids">選択したWIDをUID検索へ反映</button></div>
                    </div>
                </details>
                <details class="trh-step trh-step-uid" data-role="uid-step">
                    <summary class="trh-step-summary"><span class="trh-step-number">2</span><span><strong>UIDに関する検索</strong><small>学習者を選び、UID分布の縦棒から候補を絞り込みます。</small></span><span class="trh-step-icon" aria-hidden="true"></span></summary>
                    <div class="trh-step-body">
                        <section class="trh-stage"><div class="trh-stage-heading"><span>④</span><div><h5>論理式とUIDの選択</h5><p>グループ条件の論理式、またはチェックボックスで対象UIDを選択します。</p></div></div><div data-role="source-logic"></div><div data-role="uid-list"></div></section>
                        <section class="trh-stage"><div class="trh-stage-heading"><span>⑤</span><div><h5>UIDのヒストグラム</h5><p>正答率・迷い率も「特徴量・指標」から選択できます。</p></div></div><article class="trh-chart-card"><div class="trh-chart-controls"><label>特徴量・指標<select data-role="uid-feature">${featureOptions}</select></label><label>対象UID<select data-role="uid-feature-uids"><option value="checked" selected>チェック中のUID</option><option value="all">全UID</option></select></label><label>対象WID<select data-role="uid-feature-wids"><option value="checked" selected>反映済みのWID</option><option value="all">全WID</option></select></label></div><div class="trh-canvas"><canvas data-role="uid-feature-chart"></canvas></div><p data-role="uid-feature-summary"></p></article></section>
                        <section class="trh-stage"><div class="trh-stage-heading"><span>⑥</span><div><h5>UID縦棒の論理式</h5><p>論理式の結果は下の「ヒストグラム選択結果」へ自動で反映されます。</p></div></div><div data-role="uid-logic"></div><div data-role="uid-saved"></div></section>
                    </div>
                </details>
                <section class="trh-result"><div class="trh-result-heading"><span class="trh-step-number">3</span><div><h5>ヒストグラム選択結果</h5><p>⑥のUID縦棒論理式による検索結果です。</p></div></div><p data-role="result-summary">UID縦棒を選択してください。</p><div data-role="result-list"></div><div class="trh-result-filters" data-role="result-filters"></div><div class="trh-actions"><button type="button" class="trh-primary" data-action="show-results">ヒストグラム条件で結果を表示</button></div></section>`;
            this.renderSourceLogic();
            this.renderUidList();
            this.renderWidList();
            this.renderBarLogic('uid');
            this.renderBarLogic('wid');
            this.renderSaved('uid');
            this.renderSaved('wid');
            this.renderResultList();
            this.renderResultFilters();
            this.bindContent();
        }

        bindContent() {
            if (this.contentBound) return;
            this.contentBound = true;
            this.root.addEventListener('click', this.boundClick = (event) => this.handleClick(event), { once: false });
            this.root.addEventListener('change', this.boundChange = (event) => this.handleChange(event), { once: false });
            this.root.addEventListener('toggle', (event) => {
                if (event.target.matches('.trh-step') && event.target.open) requestAnimationFrame(() => this.renderCharts());
            }, true);
        }

        handleClick(event) {
            const action = event.target.closest('[data-action]')?.dataset.action;
            if (!action || !this.loaded) return;
            if (action.startsWith('source-add-')) this.addSourceToken(action.replace('source-add-', ''));
            else if (action === 'source-apply') this.applySourceExpression();
            else if (action === 'source-reset') { this.sourceTokens = []; this.sourceUids = new Set(this.data.students.map((item) => String(item.uid))); this.renderSourceLogic(); this.renderUidList(); this.renderCharts(); }
            else if (action === 'source-trim') this.trimTokens('source');
            else if (action === 'source-clear') { this.sourceTokens = []; this.renderSourceLogic(); }
            else if (action.startsWith('bar-add-')) { const [, , entity, kind] = action.split('-'); this.addBarToken(entity, kind); }
            else if (action.startsWith('bar-reset-')) this.rebuildBarDefault(action.replace('bar-reset-', ''));
            else if (action.startsWith('bar-clear-saved-')) this.clearSavedBars(action.replace('bar-clear-saved-', ''));
            else if (action.startsWith('bar-clear-')) { const entity = action.replace('bar-clear-', ''); this.barTokens[entity] = []; this.renderBarLogic(entity); this.applyBarExpression(entity); }
            else if (action.startsWith('bar-remove-')) this.removeSavedBar(action.replace('bar-remove-', ''), event.target.closest('[data-condition-id]')?.dataset.conditionId || '');
            else if (action === 'wid-all' || action === 'wid-none') {
                this.qa('wid-checkbox').forEach((input) => { input.checked = action === 'wid-all'; });
                this.pendingWids = new Set(this.qa('wid-checkbox').filter((input) => input.checked).map((input) => String(input.value)));
                this.markWidPending();
                this.renderCharts();
            }
            else if (action === 'apply-wid-logic') this.applyWidLogicToChecks();
            else if (action === 'apply-wids') this.applyWids();
            else if (action === 'show-results') this.submit();
            else if (action === 'remove-token') this.removeToken(event.target);
        }

        handleChange(event) {
            const role = event.target.dataset.role;
            if (!role || !this.loaded) return;
            if (role === 'uid-all') {
                this.qa('uid-checkbox').forEach((input) => { input.checked = event.target.checked; });
                this.sourceUids = new Set(this.qa('uid-checkbox').filter((input) => input.checked).map((input) => input.value));
                this.syncUidMasters(); this.renderCharts();
            } else if (role === 'uid-class-all') {
                this.qa('uid-checkbox').filter((input) => input.dataset.classId === event.target.dataset.classId).forEach((input) => { input.checked = event.target.checked; });
                this.sourceUids = new Set(this.qa('uid-checkbox').filter((input) => input.checked).map((input) => input.value));
                this.syncUidMasters(); this.renderCharts();
            } else if (role === 'uid-checkbox') {
                this.sourceUids = new Set(this.qa('uid-checkbox').filter((input) => input.checked).map((input) => input.value));
                this.syncUidMasters(); this.renderCharts();
            } else if (role === 'wid-checkbox') {
                this.pendingWids = new Set(this.qa('wid-checkbox').filter((input) => input.checked).map((input) => String(input.value)));
                this.markWidPending();
                this.renderCharts();
            }
            else if (role === 'source-kind' || role === 'source-value') this.updateTokenFromControl('source', event.target);
            else if (role === 'uid-bar-kind' || role === 'uid-bar-value') this.updateTokenFromControl('uid', event.target);
            else if (role === 'wid-bar-kind' || role === 'wid-bar-value') this.updateTokenFromControl('wid', event.target);
            else if (role === 'result-checkbox') this.resultChecks.set(event.target.value, event.target.checked);
            else if (role === 'detail-student') this.selectDetailStudent(event.target.value);
            else if (['uid-feature', 'uid-feature-uids', 'uid-feature-wids', 'wid-feature', 'wid-feature-uids', 'wid-feature-wids'].includes(role)) this.renderCharts();
        }

        sourceOptions() {
            const classMap = new Map();
            this.data.students.forEach((student) => classMap.set(String(student.ClassID), student.ClassName));
            return [
                ...[...classMap.entries()].map(([id, label]) => ({ value: `class:${id}`, label: `グループ(クラス): ${label}` })),
                ...this.groups.map((group) => ({ value: `group:${group.group_id}`, label: `グループ: ${group.group_name}` })),
            ];
        }

        tokenKindOptions(selected, conditionLabel) {
            return [['condition', conditionLabel], ['and', 'AND'], ['or', 'OR'], ['not', 'NOT'], ['open', '('], ['close', ')']]
                .map(([value, label]) => `<option value="${value}"${selected === value ? ' selected' : ''}>${label}</option>`).join('');
        }

        tokenHtml(token, index, mode, conditionOptions) {
            const rolePrefix = mode === 'source' ? 'source' : `${mode}-bar`;
            const condition = token.kind === 'condition'
                ? `<select data-role="${rolePrefix}-value" data-token-index="${index}">${conditionOptions(token.value)}</select>`
                : `<span>${token.kind === 'open' ? '(' : token.kind === 'close' ? ')' : token.kind.toUpperCase()}</span>`;
            return `<span class="trh-token ${token.kind}" data-token-mode="${mode}" data-token-index="${index}"><select data-role="${rolePrefix}-kind" data-token-index="${index}">${this.tokenKindOptions(token.kind, mode === 'source' ? '対象' : '縦棒')}</select>${condition}<button type="button" data-action="remove-token" aria-label="部品を削除">×</button></span>`;
        }

        sourceConditionOptions(selected) {
            const options = this.sourceOptions();
            if (!options.length) return '<option value="">対象がありません</option>';
            return options.map((option) => `<option value="${escapeHtml(option.value)}"${option.value === selected ? ' selected' : ''}>${escapeHtml(option.label)}</option>`).join('');
        }

        insertOptions(tokens) {
            return ['<option value="">末尾に追加</option>', ...tokens.map((token, index) => `<option value="${index}">${index + 1}個目の前</option>`)].join('');
        }

        renderSourceLogic(message = 'すべての学習者を対象にしています。', isError = false) {
            const target = this.q('source-logic');
            target.innerHTML = `<div class="trh-logic"><strong>論理式で対象UIDを選択</strong><div class="trh-toolbar">${['condition', 'and', 'or', 'not', 'open', 'close'].map((kind) => `<button type="button" data-action="source-add-${kind}">${kind === 'condition' ? '対象を追加' : kind === 'open' ? '(' : kind === 'close' ? ')' : kind.toUpperCase()}</button>`).join('')}<label>追加位置<select data-role="source-insert">${this.insertOptions(this.sourceTokens)}</select></label></div><div class="trh-builder">${this.sourceTokens.map((token, index) => this.tokenHtml(token, index, 'source', (selected) => this.sourceConditionOptions(selected))).join('')}</div><div class="trh-actions"><button type="button" data-action="source-apply">絞り込みを適用</button><button type="button" data-action="source-reset">リセット</button><button type="button" data-action="source-trim">追加位置から後ろを削除</button><button type="button" data-action="source-clear">式を空にする</button><span class="${isError ? 'is-error' : ''}" data-role="source-summary">${escapeHtml(message)}</span></div></div>`;
        }

        addSourceToken(kind) {
            const value = kind === 'condition' ? (this.sourceOptions()[0]?.value || '') : '';
            this.insertToken(this.sourceTokens, { kind, value }, this.q('source-insert')?.value);
            this.renderSourceLogic();
        }

        applySourceExpression() {
            const universe = new Set(this.data.students.map((item) => String(item.uid)));
            try {
                const selected = evaluateExpression(this.sourceTokens, (value) => {
                    const [type, id] = String(value || '').split(':');
                    let values = [];
                    if (type === 'class') values = this.data.students.filter((student) => String(student.ClassID) === id).map((student) => String(student.uid));
                    else if (type === 'group') values = (this.groupStudents[id] || []).map(String);
                    else return null;
                    return new Set(values.filter((uid) => universe.has(uid)));
                }, universe, () => new Set(universe));
                this.sourceUids = selected;
                this.renderSourceLogic(`${selected.size}名の学習者を対象にしています。`);
                this.renderUidList();
                this.renderCharts();
            } catch (error) {
                this.renderSourceLogic(error.message || '論理式を確認してください。', true);
            }
        }

        renderUidList() {
            const classes = new Map();
            this.data.students.forEach((student) => {
                const id = String(student.ClassID);
                if (!classes.has(id)) classes.set(id, { name: student.ClassName, students: [] });
                classes.get(id).students.push(student);
            });
            this.q('uid-list').innerHTML = `<div class="trh-list-controls"><label><input type="checkbox" data-role="uid-all"> 全ての学習者を選択 / 解除</label></div><div class="trh-check-list trh-uid-check-list">${[...classes.entries()].map(([classId, entry]) => `<div class="trh-class-header"><strong>${escapeHtml(entry.name)}</strong><label><input type="checkbox" data-role="uid-class-all" data-class-id="${escapeHtml(classId)}"> このグループ(クラス)を全て選択 / 解除</label></div>${entry.students.map((student) => `<label class="trh-check-item"><input type="checkbox" data-role="uid-checkbox" data-class-id="${escapeHtml(classId)}" value="${escapeHtml(student.uid)}"${this.sourceUids.has(String(student.uid)) ? ' checked' : ''}> ${escapeHtml(student.Name)} (UID:${escapeHtml(student.uid)})</label>`).join('')}`).join('')}</div>`;
            this.syncUidMasters();
        }

        syncUidMasters() {
            const inputs = this.qa('uid-checkbox');
            const all = this.q('uid-all');
            if (all) {
                all.checked = inputs.length > 0 && inputs.every((input) => input.checked);
                all.indeterminate = inputs.some((input) => input.checked) && !all.checked;
            }
            this.qa('uid-class-all').forEach((master) => {
                const items = inputs.filter((input) => input.dataset.classId === master.dataset.classId);
                master.checked = items.length > 0 && items.every((input) => input.checked);
                master.indeterminate = items.some((input) => input.checked) && !master.checked;
            });
        }

        activeWidRows() {
            if (this.scope !== 'student' || !this.detailUid) return this.data.wids;
            const available = new Set([
                ...this.data.featurePairs.filter((pair) => String(pair.uid) === this.detailUid).map((pair) => String(pair.wid)),
                ...this.data.metricAttempts.filter((item) => String(item.uid) === this.detailUid).map((item) => String(item.wid)),
            ]);
            return this.data.wids.filter((row) => available.has(String(row.WID)));
        }

        renderWidList() {
            const rows = this.activeWidRows();
            this.q('wid-list').innerHTML = `<div class="trh-check-list">${rows.length ? rows.map((row) => `<label class="trh-check-item"><input type="checkbox" data-role="wid-checkbox" value="${escapeHtml(row.WID)}"${this.pendingWids.has(String(row.WID)) ? ' checked' : ''}> WID:${escapeHtml(row.WID)}${row.Sentence ? ` : ${escapeHtml(row.Sentence)}` : ''}</label>`).join('') : '<p>対象のWIDがありません。</p>'}</div>`;
        }

        renderResultFilters() {
            const correctnessOptions = this.scope === 'test'
                ? '<option value="all">すべて</option><option value="correct">正解</option><option value="incorrect">不正解</option><option value="unanswered">未解答</option>'
                : '<option value="all">すべて</option><option value="correct">正解</option><option value="incorrect">不正解</option>';
            const hesitationOptions = this.scope === 'test'
                ? '<option value="all">すべて</option><option value="hesitated">迷い有り</option><option value="not_hesitated">迷い無し</option><option value="not_estimated">未推定</option><option value="na">-(該当なし)</option>'
                : '<option value="all">すべて</option><option value="hesitated">迷い有り</option><option value="not_hesitated">迷い無し</option><option value="not_estimated">未推定</option>';
            this.q('result-filters').innerHTML = `<label>正誤で絞り込み<select data-role="result-correctness">${correctnessOptions}</select></label><label>迷い推定結果で絞り込み<select data-role="result-hesitation">${hesitationOptions}</select></label>`;
        }

        renderResultList() {
            const result = [...this.uidResult].sort(compareIds);
            const directory = new Map(this.data.students.map((student) => [String(student.uid), student]));
            this.q('result-summary').textContent = `選択中のUID縦棒: ${this.barConditions.uid.size}本 / 対象UID: ${result.length}人`;
            if (this.scope === 'student') {
                this.q('result-list').innerHTML = `<label class="trh-detail-select">詳細を表示する学習者<select data-role="detail-student"><option value="">-- 候補から選択してください --</option>${result.map((uid) => { const student = directory.get(uid); return `<option value="${escapeHtml(uid)}"${this.detailUid === uid ? ' selected' : ''}>${escapeHtml(student?.Name || '名前未登録')} (UID:${escapeHtml(uid)})</option>`; }).join('')}</select></label>`;
            } else {
                this.q('result-list').innerHTML = result.length ? `<div class="trh-check-list">${result.map((uid) => { const student = directory.get(uid); const checked = this.resultChecks.has(uid) ? this.resultChecks.get(uid) : true; this.resultChecks.set(uid, checked); return `<label class="trh-check-item"><input type="checkbox" data-role="result-checkbox" value="${escapeHtml(uid)}"${checked ? ' checked' : ''}> ${escapeHtml(student?.Name || '名前未登録')} (UID:${escapeHtml(uid)})</label>`; }).join('')}</div>` : '<p>UIDを含む縦棒をクリックすると、ここに候補が表示されます。</p>';
            }
        }

        selectDetailStudent(uid) {
            this.detailUid = String(uid || '');
            this.barConditions.wid.clear();
            this.barTokens.wid = [];
            this.appliedWids = new Set(this.activeWidRows().map((row) => String(row.WID)));
            this.pendingWids = new Set(this.appliedWids);
            this.widLogicResult = null;
            this.widPending = false;
            this.renderWidList();
            this.renderBarLogic('wid');
            this.renderSaved('wid');
            const widSummary = this.q('wid-apply-summary');
            widSummary.textContent = `${this.appliedWids.size}件のWIDをUID検索へ反映しています。`;
            widSummary.classList.remove('is-pending');
            this.onDetailStudentChange(this.detailUid);
            this.renderCharts();
        }

        renderBarLogic(entity, message = '') {
            const target = this.q(`${entity}-logic`);
            const conditions = this.barConditions[entity];
            const options = (selected) => conditions.size
                ? [...conditions.values()].map((condition) => `<option value="${escapeHtml(condition.id)}"${condition.id === selected ? ' selected' : ''}>${escapeHtml(condition.label)}</option>`).join('')
                : '<option value="">選択中の縦棒がありません</option>';
            const widApplyButton = entity === 'wid' ? `<button type="button" class="trh-primary" data-action="apply-wid-logic"${conditions.size ? '' : ' disabled'}>論理式の結果をWIDチェックへ反映</button>` : '';
            target.innerHTML = `<div class="trh-logic"><strong>選択した${entity.toUpperCase()}縦棒の論理式</strong><div class="trh-toolbar">${['condition', 'and', 'or', 'not', 'open', 'close'].map((kind) => `<button type="button" data-action="bar-add-${entity}-${kind}">${kind === 'condition' ? '縦棒を追加' : kind === 'open' ? '(' : kind === 'close' ? ')' : kind.toUpperCase()}</button>`).join('')}<label>追加位置<select data-role="${entity}-bar-insert">${this.insertOptions(this.barTokens[entity])}</select></label></div><div class="trh-builder">${this.barTokens[entity].map((token, index) => this.tokenHtml(token, index, entity, options)).join('')}</div><div class="trh-actions"><button type="button" data-action="bar-reset-${entity}">OR式に戻す</button><button type="button" data-action="bar-clear-${entity}">式を空にする</button>${widApplyButton}<span class="${message.startsWith('エラー:') ? 'is-error' : ''}" data-role="${entity}-bar-summary">${escapeHtml(message || `${entity.toUpperCase()}の縦棒は選択されていません。`)}</span></div></div>`;
        }

        renderSaved(entity) {
            const target = this.q(`${entity}-saved`);
            const conditions = [...this.barConditions[entity].values()];
            target.innerHTML = `<div class="trh-saved-heading"><strong>保存した${entity.toUpperCase()}縦棒</strong><button type="button" data-action="bar-clear-saved-${entity}">保存情報をすべて削除</button></div><div class="trh-saved-list">${conditions.length ? conditions.map((condition) => `<span class="trh-saved" data-condition-id="${escapeHtml(condition.id)}"><span>${escapeHtml(condition.label)}</span><button type="button" data-action="bar-remove-${entity}" aria-label="保存した縦棒を削除">×</button></span>`).join('') : `<span>保存した${entity.toUpperCase()}縦棒はありません。</span>`}</div>`;
        }

        addBarToken(entity, kind) {
            const value = kind === 'condition' ? ([...this.barConditions[entity].keys()][0] || '') : '';
            this.insertToken(this.barTokens[entity], { kind, value }, this.q(`${entity}-bar-insert`)?.value);
            this.renderBarLogic(entity);
            this.applyBarExpression(entity);
        }

        insertToken(tokens, token, rawPosition) {
            const position = rawPosition === '' || rawPosition === undefined ? tokens.length : Number(rawPosition);
            if (Number.isInteger(position) && position >= 0 && position < tokens.length) tokens.splice(position, 0, token);
            else tokens.push(token);
        }

        trimTokens(mode) {
            const select = this.q('source-insert');
            if (!select || select.value === '') { this.renderSourceLogic('削除を始める追加位置を選択してください。', true); return; }
            this.sourceTokens.splice(Number(select.value));
            this.renderSourceLogic('指定位置以降の部品を削除しました。');
        }

        removeToken(button) {
            const holder = button.closest('[data-token-mode]');
            if (!holder) return;
            const mode = holder.dataset.tokenMode;
            const index = Number(holder.dataset.tokenIndex);
            if (mode === 'source') { this.sourceTokens.splice(index, 1); this.renderSourceLogic(); }
            else { this.barTokens[mode].splice(index, 1); this.renderBarLogic(mode); this.applyBarExpression(mode); }
        }

        updateTokenFromControl(mode, control) {
            const index = Number(control.dataset.tokenIndex);
            const tokens = mode === 'source' ? this.sourceTokens : this.barTokens[mode];
            if (!tokens[index]) return;
            if (control.dataset.role.endsWith('-kind')) {
                tokens[index] = { kind: control.value, value: control.value === 'condition' ? (mode === 'source' ? this.sourceOptions()[0]?.value : [...this.barConditions[mode].keys()][0]) || '' : '' };
            } else tokens[index].value = control.value;
            if (mode === 'source') this.renderSourceLogic();
            else { this.renderBarLogic(mode); this.applyBarExpression(mode); }
        }

        rebuildBarDefault(entity) {
            this.barTokens[entity] = [];
            [...this.barConditions[entity].keys()].forEach((id, index) => {
                if (index) this.barTokens[entity].push({ kind: 'or', value: '' });
                this.barTokens[entity].push({ kind: 'condition', value: id });
            });
            this.renderBarLogic(entity);
            this.applyBarExpression(entity);
        }

        applyBarExpression(entity) {
            const conditions = this.barConditions[entity];
            const universe = new Set(entity === 'uid' ? this.data.students.map((item) => String(item.uid)) : this.activeWidRows().map((item) => String(item.WID)));
            try {
                const result = evaluateExpression(this.barTokens[entity], (id) => conditions.get(id)?.members || null, universe, () => {
                    let all = new Set(); conditions.forEach((condition) => { all = union(all, condition.members); }); return all;
                });
                this.renderBarLogic(entity, conditions.size ? `${conditions.size}本の縦棒から${result.size}件を選択しています。` : `${entity.toUpperCase()}の縦棒は選択されていません。`);
                if (entity === 'uid') {
                    this.uidResult = result;
                    if (this.detailUid && !result.has(this.detailUid)) this.selectDetailStudent('');
                    this.renderResultList();
                } else {
                    this.widLogicResult = result;
                }
            } catch (error) {
                if (entity === 'wid') this.widLogicResult = null;
                this.renderBarLogic(entity, `エラー: ${error.message || '論理式を確認してください。'}`);
            }
        }

        applyWidLogicToChecks() {
            if (!(this.widLogicResult instanceof Set)) {
                window.alert('WID縦棒の論理式を完成させてください。');
                return;
            }
            const available = new Set(this.activeWidRows().map((row) => String(row.WID)));
            this.pendingWids = new Set([...this.widLogicResult].filter((wid) => available.has(wid)));
            this.qa('wid-checkbox').forEach((input) => { input.checked = this.pendingWids.has(String(input.value)); });
            this.markWidPending(`論理式の結果（${this.pendingWids.size}件）をWIDチェックへ反映しました。続けてUID検索への反映ボタンを押してください。`);
            this.renderCharts();
        }

        removeSavedBar(entity, id) {
            if (!this.barConditions[entity].delete(id)) return;
            this.rebuildBarDefault(entity);
            this.renderSaved(entity);
            this.renderCharts();
        }

        clearSavedBars(entity) {
            this.barConditions[entity].clear();
            this.barTokens[entity] = [];
            this.renderBarLogic(entity);
            this.renderSaved(entity);
            this.applyBarExpression(entity);
            this.renderCharts();
        }

        markWidPending(message = 'WIDチェックに未反映の変更があります。「選択したWIDをUID検索へ反映」を押してください。') {
            this.widPending = true;
            const summary = this.q('wid-apply-summary');
            summary.textContent = message;
            summary.classList.add('is-pending');
        }

        applyWids() {
            this.pendingWids = new Set(this.qa('wid-checkbox').filter((input) => input.checked).map((input) => String(input.value)));
            const changed = this.pendingWids.size !== this.appliedWids.size
                || [...this.pendingWids].some((wid) => !this.appliedWids.has(wid));
            this.appliedWids = new Set(this.pendingWids);
            this.widPending = false;
            const summary = this.q('wid-apply-summary');
            summary.textContent = `${this.appliedWids.size}件のWIDをUID検索と結果条件へ反映しました。`;
            summary.classList.remove('is-pending');
            if (changed) {
                this.barConditions.uid.clear();
                this.barTokens.uid = [];
                this.uidResult.clear();
                this.renderBarLogic('uid');
                this.renderSaved('uid');
                this.renderResultList();
            }
            const uidStep = this.q('uid-step');
            if (uidStep) uidStep.open = true;
            this.renderCharts();
        }

        selectedSourceUids(mode) { return mode === 'checked' ? new Set(this.sourceUids) : new Set(this.data.students.map((item) => String(item.uid))); }
        selectedWids(mode, source = 'applied') {
            if (mode !== 'checked') return new Set(this.activeWidRows().map((item) => String(item.WID)));
            return new Set(source === 'pending' ? this.pendingWids : this.appliedWids);
        }

        aggregateFeature(entity, feature, uidMode, widMode, widSource = 'applied') {
            let uids = this.selectedSourceUids(uidMode);
            const wids = this.selectedWids(widMode, widSource);
            if (this.scope === 'student' && this.detailUid && entity === 'wid') uids = new Set([this.detailUid]);
            const grouped = new Map();
            this.data.featurePairs.forEach((pair) => {
                const uid = String(pair.uid), wid = String(pair.wid);
                if (!uids.has(uid) || !wids.has(wid)) return;
                const value = this.displayValue(feature, pair.features?.[feature]);
                const count = Number(pair.featureCounts?.[feature] || 0);
                if (value === null || !Number.isFinite(value) || count <= 0) return;
                const id = entity === 'uid' ? uid : wid;
                const group = grouped.get(id) || { sum: 0, count: 0 };
                group.sum += value * count; group.count += count; grouped.set(id, group);
            });
            return [...grouped.entries()].map(([id, group]) => ({ id, value: group.sum / group.count }));
        }

        aggregateMetric(entity, metric, uidMode, widMode, widSource = 'applied') {
            let uids = this.selectedSourceUids(uidMode);
            const wids = this.selectedWids(widMode, widSource);
            if (this.scope === 'student' && this.detailUid && entity === 'wid') uids = new Set([this.detailUid]);
            const grouped = new Map();
            this.data.metricAttempts.forEach((attempt) => {
                const uid = String(attempt.uid), wid = String(attempt.wid);
                if (!uids.has(uid) || !wids.has(wid)) return;
                const raw = Number(metric === 'accuracy' ? attempt.correctness : attempt.hesitation);
                const valid = metric === 'accuracy' ? raw === 0 || raw === 1 : raw === 2 || raw === 4;
                if (!valid) return;
                const id = entity === 'uid' ? uid : wid;
                const group = grouped.get(id) || { numerator: 0, denominator: 0 };
                group.denominator += 1;
                if ((metric === 'accuracy' && raw === 1) || (metric === 'hesitation' && raw === 2)) group.numerator += 1;
                grouped.set(id, group);
            });
            return [...grouped.entries()].map(([id, group]) => ({ id, value: (group.numerator * 100) / group.denominator }));
        }

        renderCharts() {
            if (!this.loaded && !this.data) return;
            this.renderEntityChart('wid', 'pending');
            this.renderEntityChart('uid', 'applied');
        }

        renderEntityChart(entity, widSource) {
            const feature = this.q(`${entity}-feature`)?.value;
            if (!feature) return;
            const uidMode = this.q(`${entity}-feature-uids`).value;
            const widMode = this.q(`${entity}-feature-wids`).value;
            const metric = feature === '__accuracy' ? 'accuracy' : feature === '__hesitation' ? 'hesitation' : '';
            const percentage = Boolean(metric);
            const label = metric ? (metric === 'accuracy' ? '正答率' : '迷い率') : (this.features.find((item) => item.value === feature)?.label || feature);
            const points = metric
                ? this.aggregateMetric(entity, metric, uidMode, widMode, widSource)
                : this.aggregateFeature(entity, feature, uidMode, widMode, widSource);
            const unit = metric ? '%' : (this.meta(feature).unit || '-');
            const detailSignature = entity === 'wid' ? `|${this.detailUid}` : '';
            this.renderChart(`${entity}Feature`, this.q(`${entity}-feature-chart`), this.q(`${entity}-feature-summary`), points, `${label}の${entity.toUpperCase()}分布`, `${entity.toUpperCase()}ごとの平均値 (${unit})`, entity, percentage, `${feature}|${uidMode}|${widMode}|${widSource}${detailSignature}`);
        }

        renderChart(key, canvas, summary, points, title, xTitle, entity, percentage, signature) {
            this.charts[key]?.destroy(); this.charts[key] = null;
            const histogram = buildHistogram(points, percentage);
            if (!histogram) { summary.textContent = points.length ? '表示できるデータがありません。' : '対象範囲に分布データがありません。'; return; }
            summary.textContent = histogram.step > 0 ? `対象${entity.toUpperCase()}数: ${points.length} / 実測範囲: ${formatNumber(histogram.min)}〜${formatNumber(histogram.max)} / 階級幅: ${formatNumber(histogram.step)}` : `対象${entity.toUpperCase()}数: ${points.length} / すべて同じ値: ${formatNumber(histogram.min)}`;
            if (typeof window.Chart === 'undefined') { summary.textContent += ' / グラフライブラリを読み込めませんでした。'; return; }
            const conditions = histogram.bins.map((bin, index) => ({
                id: JSON.stringify([key, signature, bin.start, bin.end]), entity,
                members: new Set(bin.members.map((member) => String(member.id))), label: `${title} / ${histogram.labels[index]}`,
            }));
            const saved = this.barConditions[entity];
            const color = entity === 'uid' ? 'rgba(20, 184, 166, .62)' : 'rgba(59, 130, 246, .58)';
            const border = entity === 'uid' ? 'rgba(15, 118, 110, 1)' : 'rgba(29, 78, 216, 1)';
            const selected = (condition) => saved.has(condition.id);
            const chart = new window.Chart(canvas, {
                type: 'bar',
                data: { labels: histogram.labels, datasets: [{ label: `${entity.toUpperCase()}数`, data: histogram.counts, backgroundColor: conditions.map((item) => selected(item) ? 'rgba(37, 99, 235, .88)' : color), borderColor: conditions.map((item) => selected(item) ? 'rgba(30, 64, 175, 1)' : border), borderWidth: conditions.map((item) => selected(item) ? 3 : 1), borderRadius: 4 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: true },
                    onClick: (_event, elements, activeChart) => {
                        const index = elements?.[0]?.index;
                        if (!Number.isInteger(index) || !conditions[index].members.size) return;
                        const condition = conditions[index];
                        if (saved.has(condition.id)) saved.delete(condition.id); else saved.set(condition.id, condition);
                        this.rebuildBarDefault(entity); this.renderSaved(entity);
                        const dataset = activeChart.data.datasets[0];
                        dataset.backgroundColor = conditions.map((item) => saved.has(item.id) ? 'rgba(37, 99, 235, .88)' : color);
                        dataset.borderColor = conditions.map((item) => saved.has(item.id) ? 'rgba(30, 64, 175, 1)' : border);
                        dataset.borderWidth = conditions.map((item) => saved.has(item.id) ? 3 : 1);
                        activeChart.update('none');
                    },
                    onHover: (event, elements) => { if (event.native?.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: { legend: { display: false }, title: { display: true, text: title }, tooltip: { callbacks: { afterBody: (items) => { const ids = histogram.bins[items?.[0]?.dataIndex]?.members.map((member) => String(member.id)).sort(compareIds) || []; return ids.length <= 12 ? ids.join(', ') : `${ids.slice(0, 12).join(', ')} ほか${ids.length - 12}件`; } } } },
                    scales: { x: { title: { display: true, text: xTitle }, ticks: { maxRotation: 45, minRotation: 0 } }, y: { beginAtZero: true, title: { display: true, text: `${entity.toUpperCase()}数` }, ticks: { precision: 0 } } },
                },
            });
            this.charts[key] = chart;
        }

        destroyCharts() { Object.keys(this.charts).forEach((key) => { this.charts[key]?.destroy(); this.charts[key] = null; }); }

        async submit() {
            if (this.widPending) { window.alert('WID選択の変更を「検索」で反映してください。'); return; }
            const uids = this.scope === 'student'
                ? (this.detailUid ? [this.detailUid] : [])
                : [...this.uidResult].filter((uid) => this.resultChecks.get(uid) !== false);
            if (!uids.length) { window.alert(this.scope === 'student' ? '候補から学習者を1名選択してください。' : 'UID縦棒から学習者を1名以上選択してください。'); return; }
            if (!this.appliedWids.size) { window.alert('WIDを1件以上選択してください。'); return; }
            const button = this.root.querySelector('[data-action="show-results"]');
            button.disabled = true;
            try {
                await this.onSubmit({
                    uids, studentId: this.scope === 'student' ? this.detailUid : '', wids: [...this.appliedWids],
                    correctness: this.q('result-correctness').value, hesitation: this.q('result-hesitation').value,
                });
            } finally { button.disabled = false; }
        }
    }

    window.TeacherResultsHistogram = {
        create(options) {
            if (!options.root || !(typeof options.root === 'string' ? document.querySelector(options.root) : options.root)) return null;
            return new ResultsHistogram(options);
        },
        clearCache() { responseCache.clear(); },
    };
}());

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

    function descriptiveStats(sortedValues) {
        if (!sortedValues.length) return { mean: 0, median: 0, q1: 0, q3: 0, iqr: 0 };
        const q1 = quantile(sortedValues, 0.25);
        const q3 = quantile(sortedValues, 0.75);
        return {
            mean: sortedValues.reduce((sum, value) => sum + value, 0) / sortedValues.length,
            median: quantile(sortedValues, 0.5),
            q1,
            q3,
            iqr: q3 - q1,
        };
    }

    function buildCountAxis(rawCounts) {
        const counts = rawCounts.map(Number).filter(Number.isFinite);
        const maximum = counts.length ? Math.max(...counts) : 0;
        if (maximum <= 0) return { max: 1, step: 1, overflowIndexes: [], actualMax: 0, fence: 0 };
        const positive = counts.filter((count) => count > 0).sort((left, right) => left - right);
        const stats = descriptiveStats(positive);
        const fence = stats.iqr > 0 ? stats.q3 + (1.5 * stats.iqr) : Math.max(stats.median * 3, stats.median + 3);
        const canClip = positive.length >= 4 && maximum > fence;
        const regularCounts = canClip ? positive.filter((count) => count <= fence) : positive;
        const visibleMaximum = Math.max(1, ...(regularCounts.length ? regularCounts : positive));
        const step = Math.max(1, Math.ceil(niceStep(visibleMaximum / 6)));
        let axisMax = Math.max(step, Math.ceil(visibleMaximum / step) * step);
        if (!canClip || axisMax >= maximum) axisMax = Math.max(step, Math.ceil(maximum / step) * step);
        return {
            max: axisMax,
            step,
            overflowIndexes: counts.map((count, index) => count > axisMax ? index : -1).filter((index) => index >= 0),
            actualMax: maximum,
            fence,
        };
    }

    function overflowMarkerPlugin(overflowIndexes) {
        return {
            id: 'teacherResultOverflowMarker',
            afterDatasetsDraw(chart) {
                if (!overflowIndexes.length) return;
                const meta = chart.getDatasetMeta(0);
                const { ctx, chartArea } = chart;
                ctx.save();
                ctx.strokeStyle = '#991b1b';
                ctx.fillStyle = '#991b1b';
                ctx.lineWidth = 2.5;
                ctx.textAlign = 'center';
                ctx.font = 'bold 13px sans-serif';
                overflowIndexes.forEach((index) => {
                    const element = meta.data[index];
                    if (!element) return;
                    const half = Math.max(7, Math.min(13, (element.width || 26) / 2 - 2));
                    const y = chartArea.top + 8;
                    ctx.beginPath();
                    ctx.moveTo(element.x - half, y + 7);
                    ctx.lineTo(element.x - half / 3, y + 1);
                    ctx.lineTo(element.x + half / 3, y + 7);
                    ctx.lineTo(element.x + half, y + 1);
                    ctx.stroke();
                    ctx.fillText('▲', element.x, y - 1);
                });
                ctx.restore();
            },
        };
    }

    function overflowIndexAtPointer(chart, event, overflowIndexes) {
        if (!chart?.chartArea || !event || !overflowIndexes.length) return null;
        const x = Number(event.x);
        const y = Number(event.y);
        if (!Number.isFinite(x) || !Number.isFinite(y) || y < chart.chartArea.top || y > chart.chartArea.bottom) return null;
        const elements = chart.getDatasetMeta(0)?.data || [];
        return overflowIndexes.find((index) => {
            const element = elements[index];
            const halfWidth = Math.max(8, Number(element?.width || 24) / 2);
            return element && x >= element.x - halfWidth && x <= element.x + halfWidth;
        }) ?? null;
    }

    function syncOverflowHover(chart, event, elements, overflowIndexes) {
        const regularIndex = elements?.[0]?.index;
        const regularOverflowIndex = Number.isInteger(regularIndex) && overflowIndexes.includes(regularIndex)
            ? regularIndex
            : null;
        const overflowIndex = Number.isInteger(regularOverflowIndex)
            ? regularOverflowIndex
            : Number.isInteger(regularIndex)
                ? null
                : overflowIndexAtPointer(chart, event, overflowIndexes);
        const target = event.native?.target;
        if (target) target.style.cursor = Number.isInteger(regularIndex) || Number.isInteger(overflowIndex) ? 'pointer' : 'default';
        if (Number.isInteger(overflowIndex)) {
            chart.$overflowHoverIndex = overflowIndex;
            const active = [{ datasetIndex: 0, index: overflowIndex }];
            const tooltipY = Math.min(
                chart.chartArea.bottom - 14,
                Math.max(chart.chartArea.top + 14, Number(event.y) || chart.chartArea.top + 14)
            );
            chart.setActiveElements(active);
            chart.tooltip?.setActiveElements(active, { x: event.x, y: tooltipY });
            chart.draw();
        } else if (Number.isInteger(chart.$overflowHoverIndex)) {
            chart.$overflowHoverIndex = null;
            if (!Number.isInteger(regularIndex)) {
                chart.setActiveElements([]);
                chart.tooltip?.setActiveElements([], { x: event.x, y: event.y });
                chart.draw();
            }
        }
    }

    const MAX_CUSTOM_BINS = 100;

    function buildHistogram(rawPoints, percentage, requestedStep = null) {
        const points = rawPoints
            .map((point) => ({ id: String(point.id), value: Number(point.value) }))
            .filter((point) => Number.isFinite(point.value))
            .sort((a, b) => a.value - b.value);
        if (!points.length) return null;
        const values = points.map((point) => point.value);
        const stats = descriptiveStats(values);
        const min = values[0];
        const max = values[values.length - 1];
        const hasRequestedStep = requestedStep !== null && requestedStep !== '';
        const numericRequestedStep = hasRequestedStep ? Number(requestedStep) : null;
        if (hasRequestedStep && (!Number.isFinite(numericRequestedStep) || numericRequestedStep <= 0)) {
            throw new RangeError('階級幅は0より大きい数値で指定してください。');
        }
        if (hasRequestedStep && percentage && numericRequestedStep > 100) {
            throw new RangeError('割合の階級幅は100以下で指定してください。');
        }
        if (min === max) {
            return {
                labels: [formatNumber(min)],
                bins: [{ start: min, end: max, members: points, isOverflow: false }],
                counts: [points.length], min, max, step: 0, outlierFence: max, outlierCount: 0, ...stats,
            };
        }
        const outlierFence = percentage ? Math.min(100, stats.q3 + (1.5 * stats.iqr)) : stats.q3 + (1.5 * stats.iqr);
        const normalPoints = points.filter((point) => point.value <= outlierFence);
        const outlierPoints = points.filter((point) => point.value > outlierFence);
        const normalValues = normalPoints.map((point) => point.value);
        const normalMin = normalValues[0];
        const normalMax = normalValues[normalValues.length - 1];
        const range = normalMax - normalMin;
        const normalIqr = quantile(normalValues, 0.75) - quantile(normalValues, 0.25);
        const fdWidth = normalIqr > 0 ? (2 * normalIqr) / Math.cbrt(normalValues.length) : 0;
        const fdBins = fdWidth > 0 ? Math.ceil(range / fdWidth) : 0;
        const targetBins = Math.max(Math.min(5, normalValues.length), Math.min(12, Math.max(fdBins, Math.ceil(Math.log2(normalValues.length) + 1))));
        let step = hasRequestedStep ? numericRequestedStep : niceStep(range / targetBins);
        let lower = Math.floor(normalMin / step) * step;
        let upper = Math.ceil(normalMax / step) * step;
        if (percentage) {
            lower = Math.max(0, lower);
            upper = Math.min(100, upper);
        } else if (normalMin >= 0) {
            lower = Math.max(0, lower);
        }
        if (upper <= lower) upper = lower + step;
        let binCount = Math.max(1, Math.ceil((upper - lower) / step));
        if (hasRequestedStep && binCount > MAX_CUSTOM_BINS) {
            const minimumStep = niceStep((normalMax - lower) / MAX_CUSTOM_BINS);
            throw new RangeError(`縦棒が${MAX_CUSTOM_BINS}本を超えます。階級幅を${formatNumber(minimumStep)}以上にしてください。`);
        }
        while (!hasRequestedStep && binCount > 12) {
            step = niceStep(step * 1.5);
            lower = Math.floor(normalMin / step) * step;
            upper = Math.ceil(normalMax / step) * step;
            if (percentage) {
                lower = Math.max(0, lower);
                upper = Math.min(100, upper);
            }
            binCount = Math.max(1, Math.ceil((upper - lower) / step));
        }
        const bins = Array.from({ length: binCount }, (_, index) => ({
            start: lower + (step * index), end: lower + (step * (index + 1)), members: [], isOverflow: false,
        }));
        normalPoints.forEach((point) => {
            const rawIndex = point.value === upper ? binCount - 1 : Math.floor((point.value - lower) / step);
            bins[Math.max(0, Math.min(binCount - 1, rawIndex))].members.push(point);
        });
        if (outlierPoints.length) {
            bins[bins.length - 1].end = Math.min(bins[bins.length - 1].end, outlierFence);
            bins.push({ start: outlierFence, end: max, members: outlierPoints, isOverflow: true });
        }
        return {
            labels: bins.map((bin) => bin.isOverflow
                ? `${formatNumber(outlierFence)}超`
                : bin.start === bin.end
                    ? formatNumber(bin.start)
                    : `${formatNumber(bin.start)}〜${formatNumber(bin.end)}`),
            bins, counts: bins.map((bin) => bin.members.length), min, max, step,
            outlierFence, outlierCount: outlierPoints.length, normalMax, ...stats,
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
            this.submitLabel = options.submitLabel || '選択条件で結果を表示';
            this.onDetailStudentChange = options.onDetailStudentChange || (() => {});
            this.features = Object.entries(options.features || {}).map(([value, label]) => ({ value, label }));
            this.featureMeta = options.featureMeta || {};
            this.groups = options.groups || [];
            this.groupStudents = options.groupStudents || {};
            this.data = null;
            this.contextKey = '';
            this.loaded = false;
            this.loading = false;
            this.expanded = options.initialExpanded !== false;
            this.selectionModes = { wid: 'checkbox', uid: 'checkbox' };
            this.sourceTokens = [];
            this.barConditions = { uid: new Map(), wid: new Map() };
            this.binSettings = { uid: new Map(), wid: new Map() };
            this.autoBinSteps = { uid: new Map(), wid: new Map() };
            this.sourceUids = new Set();
            this.pendingWids = new Set();
            this.appliedWids = new Set();
            this.uidResult = new Set();
            this.resultChecks = new Map();
            this.detailUid = '';
            this.widPending = false;
            this.charts = { uidFeature: null, widFeature: null };
            this.contentBound = false;
            this.renderShell();
            this.bindShell();
            if (this.expanded) queueMicrotask(() => this.ensureLoaded());
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
            const detailText = '問題(WID)を先に選び、その問題だけを対象に学習者(UID)を絞り込みます。';
            this.root.classList.add('trh-root');
            this.root.innerHTML = `
                <button type="button" class="trh-toggle" data-role="toggle" aria-expanded="${String(this.expanded)}">
                    <span><strong>問題(WID) → 学習者(UID) の絞り込み</strong><small>${detailText}</small></span><span class="trh-toggle-icon" aria-hidden="true"></span>
                </button>
                <div class="trh-panel" data-role="panel"${this.expanded ? '' : ' hidden'}>
                    <p class="trh-status" data-role="status">選択に必要なデータを読み込んでいます...</p>
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
            this.selectionModes = { wid: 'checkbox', uid: 'checkbox' };
            this.sourceTokens = [];
            this.barConditions = { uid: new Map(), wid: new Map() };
            this.binSettings = { uid: new Map(), wid: new Map() };
            this.autoBinSteps = { uid: new Map(), wid: new Map() };
            this.sourceUids = new Set();
            this.pendingWids = new Set();
            this.appliedWids = new Set();
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
                this.uidResult = new Set(this.sourceUids);
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
            if (!this.data.students.length || !this.data.wids.length) {
                const message = !this.data.students.length
                    ? '対象となる学習者(UID)がいません。'
                    : '対象となる問題(WID)がありません。';
                this.q('content').innerHTML = `<div class="trh-heading"><h4>絞り込み条件の選択</h4><p>${message}</p></div>`;
                return;
            }
            const featureOptions = `<optgroup label="特徴量">${this.features.map((item) => `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)} (${escapeHtml(item.value)})</option>`).join('')}</optgroup><optgroup label="結果指標"><option value="__accuracy">正答率 (%)</option><option value="__hesitation">迷い率 (%)</option></optgroup>`;
            this.q('content').innerHTML = `
                <div class="trh-heading"><h4>絞り込み条件の選択</h4><p>問題(WID)を確定してから、その問題だけを対象に学習者(UID)を絞り込みます。問題と学習者で、それぞれ選択方法を変更できます。</p></div>
                <details class="trh-step trh-step-wid" data-role="wid-step" open>
                    <summary class="trh-step-summary"><span class="trh-step-number">1</span><span><strong>問題(WID)を選択</strong><small>最初に、学習者(UID)の絞り込みへ使用する問題を決めます。</small></span><span class="trh-step-icon" aria-hidden="true"></span></summary>
                    <div class="trh-step-body">
                        <div class="result-search-mode-control"><label for="${this.id('wid-selection-mode')}">問題(WID)の選択方法</label><select id="${this.id('wid-selection-mode')}" data-role="wid-selection-mode"><option value="checkbox" selected>チェックボックスで選択</option><option value="histogram">ヒストグラムで選択</option></select></div>
                        <section class="trh-stage" data-role="wid-checkbox-panel"><div class="trh-stage-heading"><span>①</span><div><h5>問題(WID)のチェックボックス</h5><p>対象にする問題をチェックしてください。</p></div></div><div class="trh-actions"><button type="button" data-action="wid-all">すべて選択</button><button type="button" data-action="wid-none">すべて解除</button></div><div data-role="wid-list"></div></section>
                        <section class="trh-stage" data-role="wid-histogram-panel" hidden><div class="trh-stage-heading"><span>①</span><div><h5>問題(WID)のヒストグラム</h5><p>縦棒をクリックするたびに、その範囲の問題を追加・解除します。複数の縦棒はすべてOR（いずれかに該当）で合算されます。</p></div></div><article class="trh-chart-card"><div class="trh-chart-controls"><label>特徴量・指標<select data-role="wid-feature">${featureOptions}</select></label>${this.binControlsHtml('wid')}</div><div class="trh-canvas"><canvas data-role="wid-feature-chart"></canvas></div><p data-role="wid-feature-summary"></p></article><p class="trh-bar-summary" data-role="wid-bar-summary">問題(WID)の縦棒は選択されていません。</p><div data-role="wid-saved"></div><div data-role="wid-selected-info"></div></section>
                        <div class="trh-flow-action"><div><strong>問題(WID)の選択を確定</strong><span data-role="wid-apply-summary">${this.appliedWids.size}件の問題(WID)を学習者(UID)の絞り込みへ反映しています。</span></div><button type="button" class="trh-primary" data-action="apply-wids">選択した問題(WID)を学習者(UID)へ反映</button></div>
                    </div>
                </details>
                <details class="trh-step trh-step-uid" data-role="uid-step">
                    <summary class="trh-step-summary"><span class="trh-step-number">2</span><span><strong>学習者(UID)を選択</strong><small>手順1で確定した問題だけを対象に学習者を絞り込みます。</small></span><span class="trh-step-icon" aria-hidden="true"></span></summary>
                    <div class="trh-step-body">
                        <div class="result-search-mode-control"><label for="${this.id('uid-selection-mode')}">学習者(UID)の選択方法</label><select id="${this.id('uid-selection-mode')}" data-role="uid-selection-mode"><option value="checkbox" selected>チェックボックスで選択</option><option value="histogram">ヒストグラムで選択</option></select></div>
                        <section class="trh-stage" data-role="uid-checkbox-panel"><div class="trh-stage-heading"><span>②</span><div><h5>学習者(UID)のチェックボックス</h5><p>グループ条件またはチェックボックスで対象の学習者を選択します。</p></div></div><div data-role="source-logic"></div><div data-role="uid-list"></div></section>
                        <section class="trh-stage" data-role="uid-histogram-panel" hidden><div class="trh-stage-heading"><span>②</span><div><h5>学習者(UID)のヒストグラム</h5><p>手順1で確定した問題(WID)だけから分布を作ります。縦棒の追加・解除はすべてORで合算されます。</p></div></div><article class="trh-chart-card"><div class="trh-chart-controls"><label>特徴量・指標<select data-role="uid-feature">${featureOptions}</select></label>${this.binControlsHtml('uid')}</div><div class="trh-canvas"><canvas data-role="uid-feature-chart"></canvas></div><p data-role="uid-feature-summary"></p></article><p class="trh-bar-summary" data-role="uid-bar-summary">学習者(UID)の縦棒は選択されていません。</p><div data-role="uid-saved"></div></section>
                    </div>
                </details>
                <section class="trh-result"><div class="trh-result-heading"><span class="trh-step-number">3</span><div><h5>選択結果</h5><p>問題(WID)と学習者(UID)の両方の条件を使って結果を表示します。</p></div></div><p data-role="result-summary"></p><div data-role="result-list"></div><div class="trh-result-filters" data-role="result-filters"></div><div class="trh-actions"><button type="button" class="trh-primary" data-action="show-results">${escapeHtml(this.submitLabel)}</button></div></section>`;
            this.renderSourceLogic();
            this.renderUidList();
            this.renderWidList();
            this.renderSaved('uid');
            this.renderSaved('wid');
            this.renderSelectedWidInfo();
            this.renderResultList();
            this.renderResultFilters();
            this.bindContent();
            this.syncBinControls('wid');
            this.syncBinControls('uid');
            this.syncModePanels();
        }

        binControlsHtml(entity) {
            return `<div class="trh-bin-controls">
                <label>横軸の階級幅<select data-role="${entity}-bin-mode"><option value="auto">自動</option><option value="manual">幅を指定</option></select></label>
                <label data-role="${entity}-bin-width-control" hidden>指定幅 <span data-role="${entity}-bin-unit"></span><input type="number" min="0" step="any" inputmode="decimal" data-role="${entity}-bin-width"></label>
                <button type="button" data-action="apply-bin-width-${entity}" data-role="${entity}-bin-apply" hidden>幅を適用</button>
                <span class="trh-bin-error" data-role="${entity}-bin-error" aria-live="polite"></span>
            </div>`;
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
            const uidStep = event.target.closest('[data-role="uid-step"]');
            if (uidStep?.dataset.locked === 'true' && event.target.closest('summary')) {
                event.preventDefault();
                return;
            }
            const action = event.target.closest('[data-action]')?.dataset.action;
            if (!action || !this.loaded) return;
            if (action.startsWith('source-add-')) this.addSourceToken(action.replace('source-add-', ''));
            else if (action === 'source-apply') this.applySourceExpression();
            else if (action === 'source-reset') { this.sourceTokens = []; this.sourceUids = new Set(this.data.students.map((item) => String(item.uid))); this.uidResult = new Set(this.sourceUids); this.renderSourceLogic(); this.renderUidList(); this.renderResultList(); this.renderCharts(); }
            else if (action === 'source-trim') this.trimTokens('source');
            else if (action === 'source-clear') { this.sourceTokens = []; this.renderSourceLogic(); }
            else if (action.startsWith('bar-clear-saved-')) this.clearSavedBars(action.replace('bar-clear-saved-', ''));
            else if (action.startsWith('bar-remove-')) this.removeSavedBar(action.replace('bar-remove-', ''), event.target.closest('[data-condition-id]')?.dataset.conditionId || '');
            else if (action === 'wid-all' || action === 'wid-none') {
                this.qa('wid-checkbox').forEach((input) => { input.checked = action === 'wid-all'; });
                this.pendingWids = new Set(this.qa('wid-checkbox').filter((input) => input.checked).map((input) => String(input.value)));
                this.markWidPending();
                this.renderCharts();
            }
            else if (action === 'apply-wids') this.applyWids();
            else if (action.startsWith('apply-bin-width-')) this.applyBinWidth(action.replace('apply-bin-width-', ''));
            else if (action === 'show-results') this.submit();
            else if (action === 'remove-token') this.removeToken(event.target);
        }

        handleChange(event) {
            const role = event.target.dataset.role;
            if (!role || !this.loaded) return;
            if (role === 'wid-selection-mode' || role === 'uid-selection-mode') {
                const entity = role.startsWith('wid-') ? 'wid' : 'uid';
                this.selectionModes[entity] = event.target.value === 'histogram' ? 'histogram' : 'checkbox';
                this.syncModePanels(entity, true);
            } else if (role === 'uid-all') {
                this.qa('uid-checkbox').forEach((input) => { input.checked = event.target.checked; });
                this.sourceUids = new Set(this.qa('uid-checkbox').filter((input) => input.checked).map((input) => input.value));
                this.uidResult = new Set(this.sourceUids);
                this.syncUidMasters(); this.renderResultList(); this.renderCharts();
            } else if (role === 'uid-class-all') {
                this.qa('uid-checkbox').filter((input) => input.dataset.classId === event.target.dataset.classId).forEach((input) => { input.checked = event.target.checked; });
                this.sourceUids = new Set(this.qa('uid-checkbox').filter((input) => input.checked).map((input) => input.value));
                this.uidResult = new Set(this.sourceUids);
                this.syncUidMasters(); this.renderResultList(); this.renderCharts();
            } else if (role === 'uid-checkbox') {
                this.sourceUids = new Set(this.qa('uid-checkbox').filter((input) => input.checked).map((input) => input.value));
                this.uidResult = new Set(this.sourceUids);
                this.syncUidMasters(); this.renderResultList(); this.renderCharts();
            } else if (role === 'wid-checkbox') {
                this.pendingWids = new Set(this.qa('wid-checkbox').filter((input) => input.checked).map((input) => String(input.value)));
                this.markWidPending();
                this.renderCharts();
            }
            else if (role === 'source-kind' || role === 'source-value') this.updateTokenFromControl('source', event.target);
            else if (role === 'result-checkbox') this.resultChecks.set(event.target.value, event.target.checked);
            else if (role === 'detail-student') this.selectDetailStudent(event.target.value);
            else if (role === 'uid-bin-mode' || role === 'wid-bin-mode') this.changeBinMode(role.startsWith('uid-') ? 'uid' : 'wid', event.target.value);
            else if (role === 'uid-feature' || role === 'wid-feature') {
                const entity = role.startsWith('uid-') ? 'uid' : 'wid';
                this.syncBinControls(entity);
                this.renderCharts();
            }
        }

        syncModePanels(changedEntity = '', userChanged = false) {
            ['wid', 'uid'].forEach((entity) => {
                const mode = this.selectionModes[entity];
                const checkboxPanel = this.q(`${entity}-checkbox-panel`);
                const histogramPanel = this.q(`${entity}-histogram-panel`);
                if (checkboxPanel) checkboxPanel.hidden = mode !== 'checkbox';
                if (histogramPanel) histogramPanel.hidden = mode !== 'histogram';
            });

            if (changedEntity === 'wid' && userChanged) {
                if (this.selectionModes.wid === 'checkbox') {
                    this.pendingWids = new Set(this.qa('wid-checkbox').filter((input) => input.checked).map((input) => String(input.value)));
                } else {
                    this.pendingWids = this.selectedBarMembers('wid');
                    this.syncWidCheckboxes();
                }
                this.markWidPending();
            }
            if (changedEntity === 'uid' && userChanged) {
                this.uidResult = this.selectionModes.uid === 'checkbox'
                    ? new Set(this.sourceUids)
                    : this.selectedBarMembers('uid');
                this.renderResultList();
            }
            requestAnimationFrame(() => this.renderCharts());
        }

        selectedFeature(entity) {
            return this.q(`${entity}-feature`)?.value || '';
        }

        binSetting(entity, feature = this.selectedFeature(entity)) {
            return this.binSettings[entity].get(feature) || { mode: 'auto', width: '' };
        }

        setBinError(entity, message = '') {
            const target = this.q(`${entity}-bin-error`);
            if (target) target.textContent = message;
        }

        syncBinControls(entity) {
            const feature = this.selectedFeature(entity);
            const setting = this.binSetting(entity, feature);
            const mode = this.q(`${entity}-bin-mode`);
            const widthControl = this.q(`${entity}-bin-width-control`);
            const widthInput = this.q(`${entity}-bin-width`);
            const applyButton = this.q(`${entity}-bin-apply`);
            const unit = feature === '__accuracy' || feature === '__hesitation' ? '%' : (this.meta(feature).unit || '値');
            if (mode) mode.value = setting.mode;
            if (widthControl) widthControl.hidden = setting.mode !== 'manual';
            if (applyButton) applyButton.hidden = setting.mode !== 'manual';
            if (this.q(`${entity}-bin-unit`)) this.q(`${entity}-bin-unit`).textContent = `(${unit})`;
            if (widthInput) widthInput.value = setting.width === '' ? '' : String(setting.width);
        }

        clearFeatureConditions(entity, feature) {
            const conditions = this.barConditions[entity];
            let changed = false;
            conditions.forEach((condition, id) => {
                if (condition.feature === feature) {
                    conditions.delete(id);
                    changed = true;
                }
            });
            if (!changed) return;
            this.renderSaved(entity);
            if (this.selectionModes[entity] === 'histogram') this.syncBarSelection(entity);
        }

        changeBinMode(entity, mode) {
            const feature = this.selectedFeature(entity);
            if (!feature) return;
            this.setBinError(entity);
            if (mode === 'auto') {
                this.binSettings[entity].set(feature, { mode: 'auto', width: '' });
                this.clearFeatureConditions(entity, feature);
                this.syncBinControls(entity);
                this.renderEntityChart(entity, entity === 'wid' ? 'pending' : 'applied');
                return;
            }

            let width = this.binSetting(entity, feature).width;
            if (width === '') width = this.autoBinSteps[entity].get(feature) || '';
            this.binSettings[entity].set(feature, { mode: 'manual', width });
            this.syncBinControls(entity);
            const rendered = this.renderEntityChart(entity, entity === 'wid' ? 'pending' : 'applied');
            if (rendered && width === '') {
                const calculated = this.autoBinSteps[entity].get(feature) || '';
                this.binSettings[entity].set(feature, { mode: 'manual', width: calculated });
                this.syncBinControls(entity);
            }
        }

        applyBinWidth(entity) {
            const feature = this.selectedFeature(entity);
            const input = this.q(`${entity}-bin-width`);
            const width = Number(input?.value);
            if (!Number.isFinite(width) || width <= 0) {
                this.setBinError(entity, '階級幅は0より大きい数値で指定してください。');
                return;
            }
            const previous = this.binSetting(entity, feature);
            this.binSettings[entity].set(feature, { mode: 'manual', width });
            this.setBinError(entity);
            const rendered = this.renderEntityChart(entity, entity === 'wid' ? 'pending' : 'applied');
            if (!rendered) {
                this.binSettings[entity].set(feature, previous);
                return;
            }
            this.clearFeatureConditions(entity, feature);
            this.syncBinControls(entity);
        }

        selectedBarMembers(entity) {
            let selected = new Set();
            this.barConditions[entity].forEach((condition) => {
                selected = union(selected, condition.members);
            });
            return selected;
        }

        syncWidCheckboxes() {
            this.qa('wid-checkbox').forEach((input) => {
                input.checked = this.pendingWids.has(String(input.value));
            });
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
            target.innerHTML = `<div class="trh-logic"><strong>グループ条件で学習者(UID)を選択</strong><div class="trh-toolbar">${['condition', 'and', 'or', 'not', 'open', 'close'].map((kind) => `<button type="button" data-action="source-add-${kind}">${kind === 'condition' ? '対象を追加' : kind === 'open' ? '(' : kind === 'close' ? ')' : kind.toUpperCase()}</button>`).join('')}<label>追加位置<select data-role="source-insert">${this.insertOptions(this.sourceTokens)}</select></label></div><div class="trh-builder">${this.sourceTokens.map((token, index) => this.tokenHtml(token, index, 'source', (selected) => this.sourceConditionOptions(selected))).join('')}</div><div class="trh-actions"><button type="button" data-action="source-apply">絞り込みを適用</button><button type="button" data-action="source-reset">リセット</button><button type="button" data-action="source-trim">追加位置から後ろを削除</button><button type="button" data-action="source-clear">式を空にする</button><span class="${isError ? 'is-error' : ''}" data-role="source-summary">${escapeHtml(message)}</span></div></div>`;
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
                this.uidResult = new Set(selected);
                this.renderSourceLogic(`${selected.size}名の学習者を対象にしています。`);
                this.renderUidList();
                this.renderResultList();
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
            this.q('uid-list').innerHTML = `<div class="trh-list-controls"><label><input type="checkbox" data-role="uid-all"> 全ての学習者(UID)を選択 / 解除</label></div><div class="trh-check-list trh-uid-check-list">${[...classes.entries()].map(([classId, entry]) => `<div class="trh-class-header"><strong>${escapeHtml(entry.name)}</strong><label><input type="checkbox" data-role="uid-class-all" data-class-id="${escapeHtml(classId)}"> このグループ(クラス)を全て選択 / 解除</label></div>${entry.students.map((student) => `<label class="trh-check-item"><input type="checkbox" data-role="uid-checkbox" data-class-id="${escapeHtml(classId)}" value="${escapeHtml(student.uid)}"${this.sourceUids.has(String(student.uid)) ? ' checked' : ''}> ${escapeHtml(student.Name)}（学習者(UID): ${escapeHtml(student.uid)}）</label>`).join('')}`).join('')}</div>`;
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

        widDirectory() {
            return new Map((this.data?.wids || []).map((row) => [String(row.WID), row]));
        }

        widInfo(row) {
            const level = String(row?.levelLabel || '').trim() || '未設定';
            const grammar = Array.isArray(row?.grammarLabels) && row.grammarLabels.length
                ? row.grammarLabels.join('、')
                : '未設定';
            return { level, grammar };
        }

        widTooltipHtml(row) {
            const info = this.widInfo(row);
            return `<span class="trh-question-tooltip" role="tooltip"><strong>問題情報</strong><span>レベル: ${escapeHtml(info.level)}</span><span>文法: ${escapeHtml(info.grammar)}</span></span>`;
        }

        renderWidList() {
            const rows = this.activeWidRows();
            this.q('wid-list').innerHTML = `<div class="trh-check-list">${rows.length ? rows.map((row) => {
                const tooltipId = `${this.id('wid-info')}-${String(row.WID).replace(/[^a-zA-Z0-9_-]/g, '-')}`;
                const info = this.widInfo(row);
                return `<label class="trh-check-item trh-question-hover" title="レベル: ${escapeHtml(info.level)} / 文法: ${escapeHtml(info.grammar)}"><input type="checkbox" data-role="wid-checkbox" value="${escapeHtml(row.WID)}" aria-describedby="${escapeHtml(tooltipId)}"${this.pendingWids.has(String(row.WID)) ? ' checked' : ''}> <span>問題(WID): ${escapeHtml(row.WID)}${row.Sentence ? ` : ${escapeHtml(row.Sentence)}` : ''}</span><span class="trh-question-info" id="${escapeHtml(tooltipId)}">ⓘ${this.widTooltipHtml(row)}</span></label>`;
            }).join('') : '<p>対象の問題(WID)がありません。</p>'}</div>`;
        }

        renderSelectedWidInfo() {
            const target = this.q('wid-selected-info');
            if (!target) return;
            const directory = this.widDirectory();
            const rows = [...this.selectedBarMembers('wid')]
                .sort(compareIds)
                .map((wid) => directory.get(wid))
                .filter(Boolean);
            if (!rows.length) {
                target.innerHTML = '';
                return;
            }
            target.innerHTML = `<section class="trh-selected-question-info"><h6>選択した縦棒に含まれる問題情報（${rows.length}件）</h6><div class="trh-question-detail-list">${rows.map((row) => {
                const info = this.widInfo(row);
                return `<article><strong>問題(WID): ${escapeHtml(row.WID)}</strong><p>${escapeHtml(row.Sentence || '英文未登録')}</p><dl><div><dt>レベル</dt><dd>${escapeHtml(info.level)}</dd></div><div><dt>文法</dt><dd>${escapeHtml(info.grammar)}</dd></div></dl></article>`;
            }).join('')}</div></section>`;
        }

        widChartTooltipLines(bin, histogram, unit = '') {
            const lines = [];
            if (bin?.isOverflow) {
                lines.push(`外れ値の基準上限: ${formatNumber(histogram.outlierFence)}${unit}`);
                lines.push(`実測上限: ${formatNumber(histogram.max)}${unit}`);
                lines.push(`集約件数: ${bin.members.length}件`);
            }
            const directory = this.widDirectory();
            const ids = (bin?.members || []).map((member) => String(member.id)).sort(compareIds);
            ids.slice(0, 4).forEach((wid) => {
                const row = directory.get(wid);
                const info = this.widInfo(row);
                lines.push(`問題(WID): ${wid}${row?.Sentence ? ` / ${row.Sentence}` : ''}`);
                lines.push(`  レベル: ${info.level} / 文法: ${info.grammar}`);
            });
            if (ids.length > 4) lines.push(`ほか${ids.length - 4}件（選択後の一覧で確認できます）`);
            return lines;
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
            const histogramMode = this.selectionModes.uid === 'histogram';
            const selectedUids = histogramMode ? this.uidResult : this.sourceUids;
            const result = [...selectedUids].sort(compareIds);
            const directory = new Map(this.data.students.map((student) => [String(student.uid), student]));
            this.q('result-summary').textContent = histogramMode
                ? `選択中の学習者(UID)縦棒: ${this.barConditions.uid.size}本 / 対象学習者: ${result.length}人`
                : `チェックボックスで${result.length}人の学習者(UID)を選択しています。`;
            if (!histogramMode) {
                this.q('result-list').innerHTML = '<p>上の学習者(UID)チェックボックスの選択を使用します。</p>';
                return;
            }
            if (this.scope === 'student') {
                this.q('result-list').innerHTML = `<label class="trh-detail-select">詳細を表示する学習者<select data-role="detail-student"><option value="">-- 候補から選択してください --</option>${result.map((uid) => { const student = directory.get(uid); return `<option value="${escapeHtml(uid)}"${this.detailUid === uid ? ' selected' : ''}>${escapeHtml(student?.Name || '名前未登録')}（学習者(UID): ${escapeHtml(uid)}）</option>`; }).join('')}</select></label>`;
            } else {
                this.q('result-list').innerHTML = result.length ? `<div class="trh-check-list">${result.map((uid) => { const student = directory.get(uid); const checked = this.resultChecks.has(uid) ? this.resultChecks.get(uid) : true; this.resultChecks.set(uid, checked); return `<label class="trh-check-item"><input type="checkbox" data-role="result-checkbox" value="${escapeHtml(uid)}"${checked ? ' checked' : ''}> ${escapeHtml(student?.Name || '名前未登録')}（学習者(UID): ${escapeHtml(uid)}）</label>`; }).join('')}</div>` : '<p>学習者(UID)を含む縦棒をクリックすると、ここに候補が表示されます。</p>';
            }
        }

        selectDetailStudent(uid) {
            this.detailUid = String(uid || '');
            this.barConditions.wid.clear();
            this.appliedWids = new Set(this.activeWidRows().map((row) => String(row.WID)));
            this.pendingWids = new Set(this.appliedWids);
            this.widPending = false;
            this.renderWidList();
            this.renderSaved('wid');
            const widSummary = this.q('wid-apply-summary');
            widSummary.textContent = `${this.appliedWids.size}件の問題(WID)を学習者(UID)の絞り込みへ反映しています。`;
            widSummary.classList.remove('is-pending');
            this.onDetailStudentChange(this.detailUid);
            this.renderCharts();
        }

        renderSaved(entity) {
            const target = this.q(`${entity}-saved`);
            const conditions = [...this.barConditions[entity].values()];
            const label = entity === 'uid' ? '学習者(UID)' : '問題(WID)';
            target.innerHTML = `<div class="trh-saved-heading"><strong>選択中の${label}縦棒（すべてOR）</strong><button type="button" data-action="bar-clear-saved-${entity}">すべて解除</button></div><div class="trh-saved-list">${conditions.length ? conditions.map((condition) => `<span class="trh-saved" data-condition-id="${escapeHtml(condition.id)}"><span>${escapeHtml(condition.label)}</span><button type="button" data-action="bar-remove-${entity}" aria-label="選択した縦棒を解除">×</button></span>`).join('') : '<span>選択した縦棒はありません。</span>'}</div>`;
            if (entity === 'wid') this.renderSelectedWidInfo();
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
        }

        updateTokenFromControl(mode, control) {
            const index = Number(control.dataset.tokenIndex);
            const tokens = this.sourceTokens;
            if (!tokens[index]) return;
            if (control.dataset.role.endsWith('-kind')) {
                tokens[index] = { kind: control.value, value: control.value === 'condition' ? (this.sourceOptions()[0]?.value || '') : '' };
            } else tokens[index].value = control.value;
            this.renderSourceLogic();
        }

        syncBarSelection(entity) {
            const result = this.selectedBarMembers(entity);
            const label = entity === 'uid' ? '学習者(UID)' : '問題(WID)';
            const summary = this.q(`${entity}-bar-summary`);
            if (summary) {
                summary.textContent = this.barConditions[entity].size
                    ? `${this.barConditions[entity].size}本の縦棒をORで合算し、${result.size}件の${label}を選択しています。`
                    : `${label}の縦棒は選択されていません。`;
            }
            if (entity === 'uid') {
                this.uidResult = result;
                if (this.detailUid && !result.has(this.detailUid)) this.selectDetailStudent('');
                this.renderResultList();
            } else {
                this.pendingWids = result;
                this.syncWidCheckboxes();
                this.markWidPending(`${this.barConditions.wid.size}本の縦棒をORで合算し、${result.size}件の問題(WID)を選択しています。学習者(UID)へ反映してください。`);
            }
        }

        removeSavedBar(entity, id) {
            if (!this.barConditions[entity].delete(id)) return;
            this.renderSaved(entity);
            this.syncBarSelection(entity);
            this.renderCharts();
        }

        clearSavedBars(entity) {
            this.barConditions[entity].clear();
            this.renderSaved(entity);
            this.syncBarSelection(entity);
            this.renderCharts();
        }

        markWidPending(message = '問題(WID)の選択に未反映の変更があります。「選択した問題(WID)を学習者(UID)へ反映」を押してください。') {
            this.widPending = true;
            const summary = this.q('wid-apply-summary');
            summary.textContent = message;
            summary.classList.add('is-pending');
            const uidStep = this.q('uid-step');
            if (uidStep) {
                uidStep.open = false;
                uidStep.dataset.locked = 'true';
                const note = uidStep.querySelector('.trh-step-summary small');
                if (note) note.textContent = '先に手順1の問題(WID)を学習者(UID)へ反映してください。';
            }
        }

        applyWids() {
            this.pendingWids = this.selectionModes.wid === 'histogram'
                ? this.selectedBarMembers('wid')
                : new Set(this.qa('wid-checkbox').filter((input) => input.checked).map((input) => String(input.value)));
            if (!this.pendingWids.size) {
                window.alert('問題(WID)を1件以上選択してください。');
                this.markWidPending();
                return;
            }
            const changed = this.pendingWids.size !== this.appliedWids.size
                || [...this.pendingWids].some((wid) => !this.appliedWids.has(wid));
            this.appliedWids = new Set(this.pendingWids);
            this.widPending = false;
            const summary = this.q('wid-apply-summary');
            summary.textContent = `${this.appliedWids.size}件の問題(WID)を学習者(UID)の絞り込みと結果条件へ反映しました。`;
            summary.classList.remove('is-pending');
            if (changed) {
                this.barConditions.uid.clear();
                this.uidResult = this.selectionModes.uid === 'checkbox' ? new Set(this.sourceUids) : new Set();
                this.renderSaved('uid');
                if (this.selectionModes.uid === 'histogram') this.syncBarSelection('uid');
                this.renderResultList();
            }
            const uidStep = this.q('uid-step');
            if (uidStep) {
                delete uidStep.dataset.locked;
                const note = uidStep.querySelector('.trh-step-summary small');
                if (note) note.textContent = '手順1で確定した問題だけを対象に学習者を絞り込みます。';
                uidStep.open = true;
            }
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
            if (!feature) return false;
            const uidMode = 'all';
            const widMode = entity === 'uid' ? 'checked' : 'all';
            const metric = feature === '__accuracy' ? 'accuracy' : feature === '__hesitation' ? 'hesitation' : '';
            const percentage = Boolean(metric);
            const label = metric ? (metric === 'accuracy' ? '正答率' : '迷い率') : (this.features.find((item) => item.value === feature)?.label || feature);
            const points = metric
                ? this.aggregateMetric(entity, metric, uidMode, widMode, widSource)
                : this.aggregateFeature(entity, feature, uidMode, widMode, widSource);
            const unit = metric ? '%' : (this.meta(feature).unit || '-');
            const detailSignature = entity === 'wid' ? `|${this.detailUid}` : '';
            const entityLabel = entity === 'uid' ? '学習者(UID)' : '問題(WID)';
            const setting = this.binSetting(entity, feature);
            return this.renderChart(
                `${entity}Feature`, this.q(`${entity}-feature-chart`), this.q(`${entity}-feature-summary`),
                points, `${label}の${entityLabel}分布`, `${entityLabel}ごとの平均値 (${unit})`, entity,
                percentage, `${feature}|${uidMode}|${widMode}|${widSource}${detailSignature}`, feature,
                setting.mode === 'manual' ? setting.width : null, unit
            );
        }

        renderChart(key, canvas, summary, points, title, xTitle, entity, percentage, signature, feature, requestedStep, unit) {
            let histogram;
            try {
                histogram = buildHistogram(points, percentage, requestedStep);
            } catch (error) {
                this.setBinError(entity, error.message || '階級幅を確認してください。');
                return false;
            }
            if (!histogram) {
                this.charts[key]?.destroy(); this.charts[key] = null;
                summary.textContent = points.length ? '表示できるデータがありません。' : '対象範囲に分布データがありません。';
                return true;
            }
            this.setBinError(entity);
            if (this.binSetting(entity, feature).mode === 'auto' && histogram.step > 0) {
                this.autoBinSteps[entity].set(feature, histogram.step);
            }
            this.charts[key]?.destroy(); this.charts[key] = null;
            const entityLabel = entity === 'uid' ? '学習者(UID)' : '問題(WID)';
            const unitText = unit === '-' ? '' : unit;
            const countAxis = buildCountAxis(histogram.counts);
            const statsText = `平均: ${formatNumber(histogram.mean)}${unitText} / 中央値: ${formatNumber(histogram.median)}${unitText} / 四分位範囲: ${formatNumber(histogram.iqr)}${unitText}`;
            summary.textContent = histogram.step > 0
                ? `対象${entityLabel}数: ${points.length} / ${statsText} / 実測範囲: ${formatNumber(histogram.min)}〜${formatNumber(histogram.max)}${unitText} / 横軸階級幅: ${formatNumber(histogram.step)}${unitText}${histogram.outlierCount ? ` / 横軸外れ値: ${histogram.outlierCount}件を「${formatNumber(histogram.outlierFence)}${unitText}超」に集約` : ''} / 縦軸目盛り: ${formatNumber(countAxis.step)}件${countAxis.overflowIndexes.length ? ` / ▲は表示上限${formatNumber(countAxis.max)}件を超える縦棒（ホバーで実件数を表示）` : ''}`
                : `対象${entityLabel}数: ${points.length} / すべて同じ値: ${formatNumber(histogram.min)}${unitText} / 縦軸目盛り: ${formatNumber(countAxis.step)}件`;
            if (typeof window.Chart === 'undefined') { summary.textContent += ' / グラフライブラリを読み込めませんでした。'; return true; }
            const conditions = histogram.bins.map((bin, index) => ({
                id: JSON.stringify([key, signature, bin.start, bin.end, histogram.step]), entity, feature,
                members: new Set(bin.members.map((member) => String(member.id))), label: `${title} / ${histogram.labels[index]}${unitText}`,
            }));
            const saved = this.barConditions[entity];
            const color = entity === 'uid' ? 'rgba(20, 184, 166, .62)' : 'rgba(59, 130, 246, .58)';
            const border = entity === 'uid' ? 'rgba(15, 118, 110, 1)' : 'rgba(29, 78, 216, 1)';
            const selected = (condition) => saved.has(condition.id);
            const displayedCounts = histogram.counts.map((count, index) =>
                countAxis.overflowIndexes.includes(index) ? countAxis.max : count
            );
            const chart = new window.Chart(canvas, {
                type: 'bar',
                data: { labels: histogram.labels, datasets: [{ label: `${entityLabel}数`, data: displayedCounts, backgroundColor: conditions.map((item) => selected(item) ? 'rgba(37, 99, 235, .88)' : color), borderColor: conditions.map((item) => selected(item) ? 'rgba(30, 64, 175, 1)' : border), borderWidth: conditions.map((item) => selected(item) ? 3 : 1), borderRadius: 4 }] },
                plugins: [overflowMarkerPlugin(countAxis.overflowIndexes)],
                options: {
                    responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: true },
                    onClick: (_event, elements, activeChart) => {
                        const index = Number.isInteger(elements?.[0]?.index)
                            ? elements[0].index
                            : overflowIndexAtPointer(activeChart, _event, countAxis.overflowIndexes);
                        if (!Number.isInteger(index) || !conditions[index].members.size) return;
                        const condition = conditions[index];
                        if (saved.has(condition.id)) saved.delete(condition.id); else saved.set(condition.id, condition);
                        this.renderSaved(entity);
                        this.syncBarSelection(entity);
                        const dataset = activeChart.data.datasets[0];
                        dataset.backgroundColor = conditions.map((item) => saved.has(item.id) ? 'rgba(37, 99, 235, .88)' : color);
                        dataset.borderColor = conditions.map((item) => saved.has(item.id) ? 'rgba(30, 64, 175, 1)' : border);
                        dataset.borderWidth = conditions.map((item) => saved.has(item.id) ? 3 : 1);
                        activeChart.update('none');
                    },
                    onHover: (event, elements, activeChart) => syncOverflowHover(activeChart, event, elements, countAxis.overflowIndexes),
                    plugins: { legend: { display: false }, title: { display: true, text: title }, tooltip: { callbacks: {
                        label: (context) => `${entityLabel}数: ${histogram.counts[context.dataIndex]}件`,
                        afterBody: (items) => {
                        const index = items?.[0]?.dataIndex;
                        const bin = histogram.bins[index];
                        if (!bin) return [];
                        const lines = [];
                        if (countAxis.overflowIndexes.includes(index)) lines.push(`実件数: ${bin.members.length}件（縦軸の表示上限: ${formatNumber(countAxis.max)}件）`);
                        if (entity === 'wid') return [...lines, ...this.widChartTooltipLines(bin, histogram, unitText)];
                        if (bin.isOverflow) {
                            lines.push(`外れ値の基準上限: ${formatNumber(histogram.outlierFence)}${unitText}`);
                            lines.push(`実測上限: ${formatNumber(histogram.max)}${unitText}`);
                            lines.push(`集約件数: ${bin.members.length}件`);
                        }
                        const ids = bin.members.map((member) => String(member.id)).sort(compareIds);
                        lines.push(ids.length <= 12 ? ids.join(', ') : `${ids.slice(0, 12).join(', ')} ほか${ids.length - 12}件`);
                        return lines;
                    } } } },
                    scales: { x: { title: { display: true, text: xTitle }, ticks: { maxRotation: 45, minRotation: 0 } }, y: { beginAtZero: true, max: countAxis.max, title: { display: true, text: `${entityLabel}数` }, ticks: { precision: 0, stepSize: countAxis.step } } },
                },
            });
            this.charts[key] = chart;
            return true;
        }

        destroyCharts() { Object.keys(this.charts).forEach((key) => { this.charts[key]?.destroy(); this.charts[key] = null; }); }

        async submit() {
            if (this.widPending) { window.alert('問題(WID)の選択変更を学習者(UID)の絞り込みへ反映してください。'); return; }
            const uids = this.scope === 'student'
                ? (this.detailUid ? [this.detailUid] : [])
                : this.selectionModes.uid === 'histogram'
                    ? [...this.uidResult].filter((uid) => this.resultChecks.get(uid) !== false)
                    : [...this.sourceUids];
            if (!uids.length) { window.alert('学習者(UID)を1名以上選択してください。'); return; }
            if (!this.appliedWids.size) { window.alert('問題(WID)を1件以上選択してください。'); return; }
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
        _test: { buildHistogram, buildCountAxis, overflowIndexAtPointer, syncOverflowHover },
    };
}());

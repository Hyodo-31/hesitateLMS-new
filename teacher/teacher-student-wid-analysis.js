(function () {
    'use strict';

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[char]));
    const compareIds = (left, right) => String(left).localeCompare(String(right), 'ja', { numeric: true });
    const union = (left, right) => new Set([...left, ...right]);

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
            .sort((left, right) => left.value - right.value);
        if (!points.length) return null;

        const values = points.map((point) => point.value);
        const min = values[0];
        const max = values[values.length - 1];
        if (min === max) {
            return {
                labels: [formatNumber(min)],
                bins: [{ start: min, end: max, members: points }],
                counts: [points.length], min, max, step: 0,
            };
        }

        const range = max - min;
        const iqr = quantile(values, 0.75) - quantile(values, 0.25);
        const fdWidth = iqr > 0 ? (2 * iqr) / Math.cbrt(values.length) : 0;
        const fdBins = fdWidth > 0 ? Math.ceil(range / fdWidth) : 0;
        const targetBins = Math.max(
            Math.min(5, values.length),
            Math.min(12, Math.max(fdBins, Math.ceil(Math.log2(values.length) + 1)))
        );
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
            binCount = Math.max(1, Math.ceil((upper - lower) / step));
        }

        const bins = Array.from({ length: binCount }, (_unused, index) => ({
            start: lower + (index * step),
            end: lower + ((index + 1) * step),
            members: [],
        }));
        points.forEach((point) => {
            const rawIndex = point.value === upper ? bins.length - 1 : Math.floor((point.value - lower) / step);
            const binIndex = Math.max(0, Math.min(bins.length - 1, rawIndex));
            bins[binIndex].members.push(point);
        });

        return {
            labels: bins.map((bin) => `${formatNumber(bin.start)}～${formatNumber(bin.end)}`),
            bins,
            counts: bins.map((bin) => bin.members.length),
            min,
            max,
            step,
        };
    }

    class StudentWidHistogramAnalysis {
        constructor(options) {
            this.root = typeof options.root === 'string' ? document.querySelector(options.root) : options.root;
            this.features = Array.isArray(options.features)
                ? options.features
                : Object.entries(options.features || {}).map(([value, label]) => ({ value, label }));
            this.features = this.features.map((feature) => typeof feature === 'string'
                ? { value: feature, label: feature }
                : { value: String(feature.value), label: String(feature.label) });
            this.featureMeta = options.featureMeta || {};
            this.onResolve = typeof options.onResolve === 'function' ? options.onResolve : async () => ({ attempts: [] });
            this.studentId = '';
            this.data = null;
            this.chart = null;
            this.conditions = new Map();
            this.resultWids = new Set();
            this.loadSequence = 0;
            this.resultSequence = 0;

            this.root?.addEventListener('click', (event) => this.handleClick(event));
            this.root?.addEventListener('change', (event) => this.handleChange(event));
        }

        q(role) {
            return this.root?.querySelector(`[data-role="${role}"]`) || null;
        }

        clear() {
            this.loadSequence += 1;
            this.resultSequence += 1;
            this.chart?.destroy();
            this.chart = null;
            this.studentId = '';
            this.data = null;
            this.conditions.clear();
            this.resultWids.clear();
            if (this.root) {
                this.root.hidden = true;
                this.root.innerHTML = '';
            }
        }

        async load(studentId) {
            const targetId = String(studentId || '');
            this.clear();
            if (!targetId || !this.root) return;

            const sequence = this.loadSequence;
            this.studentId = targetId;
            this.root.hidden = false;
            this.root.innerHTML = '<p class="trh-status">問題(WID)の分布データを読み込んでいます...</p>';

            try {
                const body = new FormData();
                body.append('scope', 'student');
                body.append('student_id', targetId);
                const response = await fetch('teacher-results-histogram-data.php', { method: 'POST', body });
                const payload = await response.json();
                if (!response.ok || !payload?.ok) throw new Error(payload?.message || '問題(WID)の分布データの取得に失敗しました。');
                if (sequence !== this.loadSequence || targetId !== this.studentId) return;
                this.data = payload;
                this.renderShell();
                this.renderChart();
            } catch (error) {
                if (sequence !== this.loadSequence) return;
                this.root.innerHTML = `<p class="trh-status is-error">${escapeHtml(error.message || '問題(WID)の分布データの取得に失敗しました。')}</p>`;
            }
        }

        renderShell() {
            const featureOptions = [
                '<option value="__accuracy">正答率</option>',
                '<option value="__hesitation">迷い率</option>',
                '<optgroup label="操作特徴量">',
                ...this.features.map((feature) => `<option value="${escapeHtml(feature.value)}">${escapeHtml(feature.label)}</option>`),
                '</optgroup>',
            ].join('');

            this.root.innerHTML = `
                <section class="trh-stage swha-stage">
                    <div class="trh-stage-heading">
                        <span>①</span>
                        <div><h5>学習者が解いた問題(WID)の分布</h5><p>特徴量・正答率・迷い率を切り替えて、問題(WID)ごとの分布を確認できます。</p></div>
                    </div>
                    <article class="trh-chart-card swha-chart-card">
                        <div class="trh-chart-controls">
                            <label>特徴量・指標<select data-role="feature">${featureOptions}</select></label>
                        </div>
                        <div class="trh-canvas"><canvas data-role="chart" class="is-histogram-selectable"></canvas></div>
                        <p data-role="chart-summary"></p>
                    </article>
                </section>
                <section class="trh-stage swha-stage">
                    <div class="trh-stage-heading">
                        <span>②</span>
                        <div><h5>縦棒条件に該当する解答結果</h5><p>縦棒をクリックして問題(WID)を追加・解除します。複数の縦棒はすべてOR（いずれかに該当）で合算されます。</p></div>
                    </div>
                    <div data-role="saved"></div>
                    <p class="trh-bar-summary" data-role="selection-summary">縦棒は選択されていません。</p>
                    <div class="trh-result-filters">
                        <label>正誤<select data-role="correctness"><option value="all">すべて</option><option value="correct">正解</option><option value="incorrect">不正解</option></select></label>
                        <label>迷い推定<select data-role="hesitation"><option value="all">すべて</option><option value="hesitated">迷い有り</option><option value="not_hesitated">迷い無し</option><option value="not_estimated">未推定</option></select></label>
                    </div>
                    <div class="swha-results" data-role="results"><p>①のヒストグラムから縦棒をクリックしてください。</p></div>
                </section>`;
            this.renderSaved();
        }

        metricPoints(metric) {
            const grouped = new Map();
            (this.data?.metricAttempts || []).forEach((attempt) => {
                if (String(attempt.uid) !== this.studentId) return;
                const raw = Number(metric === 'accuracy' ? attempt.correctness : attempt.hesitation);
                const valid = metric === 'accuracy' ? raw === 0 || raw === 1 : raw === 2 || raw === 4;
                if (!valid) return;
                const wid = String(attempt.wid);
                const group = grouped.get(wid) || { numerator: 0, denominator: 0 };
                group.denominator += 1;
                if ((metric === 'accuracy' && raw === 1) || (metric === 'hesitation' && raw === 2)) group.numerator += 1;
                grouped.set(wid, group);
            });
            return [...grouped.entries()].map(([id, group]) => ({
                id,
                value: (group.numerator * 100) / group.denominator,
            }));
        }

        featurePoints(feature) {
            const grouped = new Map();
            const scale = Number(this.featureMeta?.[feature]?.displayScale || 1);
            (this.data?.featurePairs || []).forEach((pair) => {
                if (String(pair.uid) !== this.studentId) return;
                const raw = Number(pair.features?.[feature]);
                const count = Number(pair.featureCounts?.[feature] || 0);
                if (!Number.isFinite(raw) || count <= 0) return;
                const wid = String(pair.wid);
                const group = grouped.get(wid) || { sum: 0, count: 0 };
                group.sum += raw * scale * count;
                group.count += count;
                grouped.set(wid, group);
            });
            return [...grouped.entries()].map(([id, group]) => ({ id, value: group.sum / group.count }));
        }

        renderChart() {
            this.chart?.destroy();
            this.chart = null;
            const canvas = this.q('chart');
            const summary = this.q('chart-summary');
            const feature = this.q('feature')?.value || '__accuracy';
            if (!canvas || !summary) return;

            const metric = feature === '__accuracy' ? 'accuracy' : feature === '__hesitation' ? 'hesitation' : '';
            const percentage = Boolean(metric);
            const label = metric
                ? (metric === 'accuracy' ? '正答率' : '迷い率')
                : (this.features.find((item) => item.value === feature)?.label || feature);
            const unit = metric ? '%' : (this.featureMeta?.[feature]?.unit || '');
            const points = metric ? this.metricPoints(metric) : this.featurePoints(feature);
            const histogram = buildHistogram(points, percentage);
            if (!histogram) {
                summary.textContent = 'この指標について表示できる問題(WID)の分布データがありません。';
                return;
            }

            summary.textContent = histogram.step > 0
                ? `対象問題(WID)数: ${points.length} / 実測範囲: ${formatNumber(histogram.min)}～${formatNumber(histogram.max)}${unit} / 階級幅: ${formatNumber(histogram.step)}${unit}`
                : `対象問題(WID)数: ${points.length} / すべて同じ値: ${formatNumber(histogram.min)}${unit}`;
            if (typeof window.Chart === 'undefined') {
                summary.textContent += ' / グラフライブラリを読み込めませんでした。';
                return;
            }

            const conditions = histogram.bins.map((bin, index) => ({
                id: JSON.stringify([feature, bin.start, bin.end, index]),
                label: `${label}: ${histogram.labels[index]}${unit}`,
                members: new Set(bin.members.map((member) => String(member.id))),
            }));
            const baseColor = 'rgba(59, 130, 246, .58)';
            const selectedColor = 'rgba(37, 99, 235, .9)';
            const colors = () => conditions.map((condition) => this.conditions.has(condition.id) ? selectedColor : baseColor);

            this.chart = new window.Chart(canvas, {
                type: 'bar',
                data: {
                    labels: histogram.labels,
                    datasets: [{
                        label: '問題(WID)数',
                        data: histogram.counts,
                        backgroundColor: colors(),
                        borderColor: conditions.map((condition) => this.conditions.has(condition.id) ? 'rgba(30, 64, 175, 1)' : 'rgba(29, 78, 216, 1)'),
                        borderWidth: conditions.map((condition) => this.conditions.has(condition.id) ? 3 : 1),
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: true },
                    onClick: (_event, elements, chart) => {
                        const index = elements?.[0]?.index;
                        if (!Number.isInteger(index) || !conditions[index]?.members.size) return;
                        const condition = conditions[index];
                        if (this.conditions.has(condition.id)) this.conditions.delete(condition.id);
                        else this.conditions.set(condition.id, condition);
                        this.renderSaved();
                        this.applySelection();
                        chart.data.datasets[0].backgroundColor = colors();
                        chart.data.datasets[0].borderColor = conditions.map((item) => this.conditions.has(item.id) ? 'rgba(30, 64, 175, 1)' : 'rgba(29, 78, 216, 1)');
                        chart.data.datasets[0].borderWidth = conditions.map((item) => this.conditions.has(item.id) ? 3 : 1);
                        chart.update('none');
                    },
                    onHover: (event, elements) => {
                        if (event.native?.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                    },
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: `${label}の問題(WID)分布` },
                        tooltip: {
                            callbacks: {
                                afterBody: (items) => {
                                    const ids = histogram.bins[items?.[0]?.dataIndex]?.members
                                        .map((member) => String(member.id)).sort(compareIds) || [];
                                    return ids.length <= 12 ? `問題(WID): ${ids.join(', ')}` : `問題(WID): ${ids.slice(0, 12).join(', ')} ほか${ids.length - 12}件`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: { title: { display: true, text: `問題(WID)ごとの${metric ? label : '平均値'}${unit ? ` (${unit})` : ''}` } },
                        y: { beginAtZero: true, title: { display: true, text: '問題(WID)数' }, ticks: { precision: 0 } },
                    },
                },
            });
        }

        renderSaved() {
            const target = this.q('saved');
            if (!target) return;
            const conditions = [...this.conditions.values()];
            target.innerHTML = `<div class="trh-saved-heading"><strong>選択した縦棒（すべてOR）</strong><button type="button" data-action="clear-bars">すべて解除</button></div><div class="trh-saved-list">${conditions.length ? conditions.map((condition) => `<span class="trh-saved" data-condition-id="${escapeHtml(condition.id)}"><span>${escapeHtml(condition.label)}</span><button type="button" data-action="remove-bar" aria-label="選択した縦棒を解除">×</button></span>`).join('') : '<span>選択した縦棒はありません。</span>'}</div>`;
        }

        applySelection() {
            this.resultWids = new Set();
            this.conditions.forEach((condition) => {
                this.resultWids = union(this.resultWids, condition.members);
            });
            const summary = this.q('selection-summary');
            if (summary) {
                summary.textContent = this.conditions.size
                    ? `${this.conditions.size}本の縦棒をORで合算し、${this.resultWids.size}件の問題(WID)を表示対象にしています。`
                    : '縦棒は選択されていません。';
            }
            this.requestResults();
        }

        async requestResults() {
            const target = this.q('results');
            if (!target) return;
            const wids = [...this.resultWids].sort(compareIds);
            const sequence = ++this.resultSequence;
            if (!wids.length) {
                target.innerHTML = '<p>①のヒストグラムから縦棒をクリックしてください。</p>';
                return;
            }

            target.innerHTML = `<p class="loading">対象問題(WID)（${wids.length}件）の解答結果を読み込んでいます...</p>`;
            try {
                const data = await this.onResolve({
                    studentId: this.studentId,
                    wids,
                    correctness: this.q('correctness')?.value || 'all',
                    hesitation: this.q('hesitation')?.value || 'all',
                });
                if (sequence !== this.resultSequence) return;
                this.renderResults(data, wids);
            } catch (_error) {
                if (sequence !== this.resultSequence) return;
                target.innerHTML = '<p class="error">縦棒条件に該当する解答結果を読み込めませんでした。</p>';
            }
        }

        renderResults(data, wids) {
            const target = this.q('results');
            if (!target) return;
            const attempts = Array.isArray(data?.attempts) ? data.attempts : [];
            const widLabel = wids.length <= 20 ? wids.join(', ') : `${wids.slice(0, 20).join(', ')} ほか${wids.length - 20}件`;
            let html = `<div class="swha-result-summary"><strong>対象問題(WID): ${escapeHtml(widLabel)}</strong><span>該当解答: ${attempts.length}件</span></div>`;
            if (!attempts.length) {
                target.innerHTML = html + '<p>現在の縦棒と絞り込み条件に合う解答結果はありません。</p>';
                return;
            }

            html += `<div class="swha-table-wrap"><table><thead><tr><th>問題(WID)</th><th>テスト名</th><th>正誤</th><th>迷い推定</th><th>解答日時</th><th>軌跡再現</th></tr></thead><tbody>${attempts.map((attempt) => {
                const params = new URLSearchParams({
                    UID: this.studentId,
                    WID: attempt.WID ?? '',
                    test_id: attempt.test_id ?? '',
                    LogID: attempt.attempt ?? '',
                });
                const correctness = String(attempt.correctness ?? '-');
                const hesitation = String(attempt.hesitation ?? '-');
                return `<tr><td>${escapeHtml(attempt.WID)} (${escapeHtml(attempt.attempt)}回目)</td><td>${escapeHtml(attempt.test_name || '（不明なテスト）')}</td><td class="${correctness === '不正解' ? 'incorrect' : ''}">${escapeHtml(correctness)}</td><td class="${hesitation === '迷い有り' ? 'hesitation-yes' : ''}">${escapeHtml(hesitation)}</td><td>${escapeHtml(attempt.date || '-')}</td><td><a href="../mousemove/mousemove.php?${escapeHtml(params.toString())}" target="_blank" rel="noopener noreferrer" class="link-button">表示</a></td></tr>`;
            }).join('')}</tbody></table></div>`;
            target.innerHTML = html;
        }

        removeBar(id) {
            if (!this.conditions.delete(id)) return;
            this.renderSaved();
            this.applySelection();
            this.renderChart();
        }

        handleClick(event) {
            const button = event.target.closest('[data-action]');
            if (!button || !this.data) return;
            const action = button.dataset.action;
            if (action === 'remove-bar') {
                this.removeBar(button.closest('[data-condition-id]')?.dataset.conditionId || '');
            } else if (action === 'clear-bars') {
                this.conditions.clear();
                this.renderSaved();
                this.applySelection();
                this.renderChart();
            }
        }

        handleChange(event) {
            if (!this.data) return;
            const role = event.target.dataset.role;
            if (role === 'feature') this.renderChart();
            else if (role === 'correctness' || role === 'hesitation') this.requestResults();
        }
    }

    window.StudentWidHistogramAnalysis = {
        create(options) {
            const root = typeof options.root === 'string' ? document.querySelector(options.root) : options.root;
            return root ? new StudentWidHistogramAnalysis(options) : null;
        },
    };
}());

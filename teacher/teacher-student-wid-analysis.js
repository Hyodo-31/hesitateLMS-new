(function () {
    'use strict';

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[character]));
    const compareIds = (left, right) => String(left).localeCompare(String(right), 'ja', { numeric: true });
    const MAX_CUSTOM_BINS = 100;
    let analysisSequence = 0;

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

    function buildHistogram(rawPoints, percentage, requestedStep = null) {
        const points = rawPoints
            .map((point) => ({ id: String(point.id), value: Number(point.value) }))
            .filter((point) => Number.isFinite(point.value))
            .sort((left, right) => left.value - right.value);
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
        const normalStats = descriptiveStats(normalValues);
        const fdWidth = normalStats.iqr > 0 ? (2 * normalStats.iqr) / Math.cbrt(normalValues.length) : 0;
        const fdBins = fdWidth > 0 ? Math.ceil(range / fdWidth) : 0;
        const sturgesBins = Math.ceil(Math.log2(normalValues.length) + 1);
        const targetBins = Math.max(Math.min(5, normalValues.length), Math.min(12, Math.max(fdBins, sturgesBins)));
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

        const bins = Array.from({ length: binCount }, (_unused, index) => ({
            start: lower + (index * step),
            end: lower + ((index + 1) * step),
            members: [],
            isOverflow: false,
        }));
        normalPoints.forEach((point) => {
            const rawIndex = point.value === upper ? bins.length - 1 : Math.floor((point.value - lower) / step);
            bins[Math.max(0, Math.min(bins.length - 1, rawIndex))].members.push(point);
        });
        if (outlierPoints.length) {
            bins[bins.length - 1].end = Math.min(bins[bins.length - 1].end, outlierFence);
            bins.push({ start: outlierFence, end: max, members: outlierPoints, isOverflow: true });
        }

        return {
            labels: bins.map((bin) => bin.isOverflow
                ? `${formatNumber(outlierFence)}超`
                : bin.start === bin.end ? formatNumber(bin.start) : `${formatNumber(bin.start)}～${formatNumber(bin.end)}`),
            bins,
            counts: bins.map((bin) => bin.members.length),
            min,
            max,
            step,
            outlierFence,
            outlierCount: outlierPoints.length,
            normalMax,
            ...stats,
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
            id: 'studentWidOverflowMarker',
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
        const overflowIndex = Number.isInteger(regularIndex) ? null : overflowIndexAtPointer(chart, event, overflowIndexes);
        const target = event.native?.target;
        if (target) target.style.cursor = Number.isInteger(regularIndex) || Number.isInteger(overflowIndex) ? 'pointer' : 'default';
        if (Number.isInteger(overflowIndex)) {
            chart.$overflowHoverIndex = overflowIndex;
            const active = [{ datasetIndex: 0, index: overflowIndex }];
            chart.setActiveElements(active);
            chart.tooltip?.setActiveElements(active, { x: event.x, y: Math.max(chart.chartArea.top + 14, event.y) });
            chart.draw();
        } else if (!Number.isInteger(regularIndex) && Number.isInteger(chart.$overflowHoverIndex)) {
            chart.$overflowHoverIndex = null;
            chart.setActiveElements([]);
            chart.tooltip?.setActiveElements([], { x: event.x, y: event.y });
            chart.draw();
        }
    }

    class StudentWidHistogramAnalysis {
        constructor(options) {
            this.root = typeof options.root === 'string' ? document.querySelector(options.root) : options.root;
            this.instanceId = ++analysisSequence;
            this.features = (Array.isArray(options.features)
                ? options.features
                : Object.entries(options.features || {}).map(([value, label]) => ({ value, label })))
                .map((feature) => typeof feature === 'string' ? { value: feature, label: feature } : { value: String(feature.value), label: String(feature.label) });
            this.featureMeta = options.featureMeta || {};
            this.onResolve = typeof options.onResolve === 'function' ? options.onResolve : async () => ({ attempts: [] });
            this.onRender = typeof options.onRender === 'function' ? options.onRender : null;
            this.studentId = '';
            this.data = null;
            this.chart = null;
            this.selectionMode = 'checkbox';
            this.checkboxWids = new Set();
            this.conditions = new Map();
            this.resultWids = new Set();
            this.binSettings = new Map();
            this.autoBinSteps = new Map();
            this.loadSequence = 0;
            this.resultSequence = 0;
            this.hasRenderedResults = false;
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
            this.checkboxWids.clear();
            this.binSettings.clear();
            this.autoBinSteps.clear();
            this.hasRenderedResults = false;
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
            this.root.innerHTML = '<p class="trh-status">問題(WID)の選択データを読み込んでいます...</p>';
            try {
                const body = new FormData();
                body.append('scope', 'student');
                body.append('student_id', targetId);
                const response = await fetch('teacher-results-histogram-data.php', { method: 'POST', body });
                const payload = await response.json();
                if (!response.ok || !payload?.ok) throw new Error(payload?.message || '問題(WID)のデータ取得に失敗しました。');
                if (sequence !== this.loadSequence || targetId !== this.studentId) return;
                this.data = payload;
                this.checkboxWids = new Set((payload.wids || []).map((row) => String(row.WID)));
                this.renderShell();
                this.renderChart();
                this.renderSelectionSummary();
            } catch (error) {
                if (sequence !== this.loadSequence) return;
                this.root.innerHTML = `<p class="trh-status is-error">${escapeHtml(error.message || '問題(WID)のデータ取得に失敗しました。')}</p>`;
            }
        }

        widInfo(row) {
            return {
                level: String(row?.levelLabel || '').trim() || '未設定',
                grammar: Array.isArray(row?.grammarLabels) && row.grammarLabels.length ? row.grammarLabels.join('、') : '未設定',
            };
        }

        widTooltip(row) {
            const info = this.widInfo(row);
            return `<span class="trh-question-tooltip" role="tooltip"><strong>問題情報</strong><span>レベル: ${escapeHtml(info.level)}</span><span>文法: ${escapeHtml(info.grammar)}</span></span>`;
        }

        renderShell() {
            const questionRows = Array.isArray(this.data?.wids) ? this.data.wids : [];
            const featureOptions = this.features.map((feature) => `<option value="${escapeHtml(feature.value)}">${escapeHtml(feature.label)}</option>`).join('');
            this.root.innerHTML = `
                <section class="trh-stage swha-stage">
                    <div class="trh-stage-heading"><span>①</span><div><h5>問題(WID)を選択</h5><p>チェックボックスまたはヒストグラムのどちらかで選べます。</p></div></div>
                    <div class="swha-mode-control"><label>選択方法<select data-role="selection-mode"><option value="checkbox">チェックボックス</option><option value="histogram">ヒストグラム</option></select></label></div>
                    <div data-role="checkbox-panel">
                        <div class="checkbox-controls"><label><input type="checkbox" data-role="select-all" checked> 全て選択 / 解除</label></div>
                        <div class="checkbox-list swha-question-list">${questionRows.length ? questionRows.map((row, index) => {
                            const tooltipId = `swha-${this.instanceId}-${index}`;
                            return `<label class="checkbox-item trh-question-hover" title="レベル: ${escapeHtml(this.widInfo(row).level)} / 文法: ${escapeHtml(this.widInfo(row).grammar)}"><input type="checkbox" data-role="wid-checkbox" value="${escapeHtml(row.WID)}" checked aria-describedby="${tooltipId}"><span>問題(WID): ${escapeHtml(row.WID)}${row.Sentence ? ` : ${escapeHtml(row.Sentence)}` : ''}</span><span class="trh-question-info" tabindex="0" id="${tooltipId}">ⓘ${this.widTooltip(row)}</span></label>`;
                        }).join('') : '<p>この学習者の解答履歴はありません。</p>'}</div>
                    </div>
                    <div data-role="histogram-panel" hidden>
                        <article class="trh-chart-card swha-chart-card">
                            <div class="trh-chart-controls"><label>操作特徴量<select data-role="feature">${featureOptions}</select></label>
                                <div class="trh-bin-controls"><label>横軸の階級幅<select data-role="bin-mode"><option value="auto">自動</option><option value="manual">幅を指定</option></select></label>
                                    <label data-role="bin-width-control" hidden>指定幅 <span data-role="bin-unit"></span><input type="number" min="0" step="any" inputmode="decimal" data-role="bin-width"></label>
                                    <button type="button" data-action="apply-bin-width" data-role="bin-apply" hidden>幅を適用</button><span class="trh-bin-error" data-role="bin-error" aria-live="polite"></span></div></div>
                            <div class="trh-canvas"><canvas data-role="chart" class="is-histogram-selectable"></canvas></div><p data-role="chart-summary"></p>
                        </article>
                        <div data-role="saved"></div><div data-role="selected-info"></div>
                    </div>
                    <p class="trh-bar-summary" data-role="selection-summary"></p>
                    <div class="trh-result-filters"><label>正誤<select data-role="correctness"><option value="all">すべて</option><option value="correct">正解</option><option value="incorrect">不正解</option></select></label>
                        <label>迷い推定<select data-role="hesitation"><option value="all">すべて</option><option value="hesitated">迷い有り</option><option value="not_hesitated">迷い無し</option><option value="not_estimated">未推定</option></select></label></div>
                    <button type="button" class="action-button" data-action="show-results">選択した問題(WID)の詳細を表示</button>
                </section>
                <section class="trh-stage swha-stage"><div class="trh-stage-heading"><span>②</span><div><h5>学習者情報</h5><p>問題別の結果、総合評価、文法項目ごとの表とグラフを表示します。</p></div></div>
                    <div class="swha-results" data-role="results"><p>問題(WID)を選択して「詳細を表示」を押してください。</p></div></section>`;
            this.renderSaved();
            this.syncBinControls();
        }

        selectedFeature() {
            return this.q('feature')?.value || this.features[0]?.value || '';
        }

        binSetting(feature = this.selectedFeature()) {
            return this.binSettings.get(feature) || { mode: 'auto', width: '' };
        }

        setBinError(message = '') {
            const target = this.q('bin-error');
            if (target) target.textContent = message;
        }

        syncBinControls() {
            const feature = this.selectedFeature();
            const setting = this.binSetting(feature);
            const unit = this.featureMeta?.[feature]?.unit || '値';
            if (this.q('bin-mode')) this.q('bin-mode').value = setting.mode;
            if (this.q('bin-width-control')) this.q('bin-width-control').hidden = setting.mode !== 'manual';
            if (this.q('bin-apply')) this.q('bin-apply').hidden = setting.mode !== 'manual';
            if (this.q('bin-unit')) this.q('bin-unit').textContent = `(${unit})`;
            if (this.q('bin-width')) this.q('bin-width').value = setting.width === '' ? '' : String(setting.width);
        }

        clearFeatureConditions(feature) {
            let changed = false;
            this.conditions.forEach((condition, id) => {
                if (condition.feature === feature) {
                    this.conditions.delete(id);
                    changed = true;
                }
            });
            if (changed) this.applyHistogramSelection();
        }

        changeBinMode(mode) {
            const feature = this.selectedFeature();
            if (!feature) return;
            this.setBinError();
            if (mode === 'auto') {
                this.binSettings.set(feature, { mode: 'auto', width: '' });
                this.clearFeatureConditions(feature);
                this.syncBinControls();
                this.renderChart();
                return;
            }
            let width = this.binSetting(feature).width;
            if (width === '') width = this.autoBinSteps.get(feature) || '';
            this.binSettings.set(feature, { mode: 'manual', width });
            this.syncBinControls();
            this.renderChart();
        }

        applyBinWidth() {
            const feature = this.selectedFeature();
            const width = Number(this.q('bin-width')?.value);
            if (!Number.isFinite(width) || width <= 0) {
                this.setBinError('階級幅は0より大きい数値で指定してください。');
                return;
            }
            const previous = this.binSetting(feature);
            this.binSettings.set(feature, { mode: 'manual', width });
            this.setBinError();
            if (!this.renderChart()) {
                this.binSettings.set(feature, previous);
                return;
            }
            this.clearFeatureConditions(feature);
            this.syncBinControls();
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

        widDirectory() {
            return new Map((this.data?.wids || []).map((row) => [String(row.WID), row]));
        }

        widChartTooltipLines(bin, histogram, unit, countAxis, index) {
            const lines = [];
            if (countAxis.overflowIndexes.includes(index)) lines.push(`実件数: ${bin.members.length}件（縦軸の表示上限: ${formatNumber(countAxis.max)}件）`);
            if (bin?.isOverflow) {
                lines.push(`横軸の外れ値基準: ${formatNumber(histogram.outlierFence)}${unit}`);
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

        renderChart() {
            const canvas = this.q('chart');
            const summary = this.q('chart-summary');
            const feature = this.selectedFeature();
            if (!canvas || !summary || !feature) return false;
            const label = this.features.find((item) => item.value === feature)?.label || feature;
            const unit = this.featureMeta?.[feature]?.unit || '';
            const points = this.featurePoints(feature);
            const setting = this.binSetting(feature);
            let histogram;
            try {
                histogram = buildHistogram(points, false, setting.mode === 'manual' ? setting.width : null);
            } catch (error) {
                this.setBinError(error.message || '階級幅を確認してください。');
                return false;
            }
            if (!histogram) {
                this.chart?.destroy();
                this.chart = null;
                summary.textContent = 'この特徴量について表示できる問題(WID)の分布データがありません。';
                return true;
            }
            this.setBinError();
            if (setting.mode === 'auto' && histogram.step > 0) this.autoBinSteps.set(feature, histogram.step);
            this.chart?.destroy();
            this.chart = null;
            const countAxis = buildCountAxis(histogram.counts);
            const statsText = `平均: ${formatNumber(histogram.mean)}${unit} / 中央値: ${formatNumber(histogram.median)}${unit} / 四分位範囲: ${formatNumber(histogram.iqr)}${unit}`;
            summary.textContent = histogram.step > 0
                ? `対象問題(WID)数: ${points.length} / ${statsText} / 実測範囲: ${formatNumber(histogram.min)}～${formatNumber(histogram.max)}${unit} / 横軸階級幅: ${formatNumber(histogram.step)}${unit}${histogram.outlierCount ? ` / 横軸外れ値: ${histogram.outlierCount}件を「${formatNumber(histogram.outlierFence)}${unit}超」に集約` : ''} / 縦軸目盛り: ${formatNumber(countAxis.step)}件${countAxis.overflowIndexes.length ? ` / ▲は表示上限${formatNumber(countAxis.max)}件を超える縦棒（ホバーで実件数を表示）` : ''}`
                : `対象問題(WID)数: ${points.length} / すべて同じ値: ${formatNumber(histogram.min)}${unit} / 縦軸目盛り: ${formatNumber(countAxis.step)}件`;
            if (typeof window.Chart === 'undefined') return true;

            const conditions = histogram.bins.map((bin, index) => ({
                id: JSON.stringify([feature, bin.start, bin.end, histogram.step, index]),
                feature,
                label: `${label}: ${histogram.labels[index]}${unit}`,
                members: new Set(bin.members.map((member) => String(member.id))),
            }));
            const colors = () => conditions.map((condition) => this.conditions.has(condition.id) ? 'rgba(37, 99, 235, .9)' : 'rgba(59, 130, 246, .58)');
            this.chart = new window.Chart(canvas, {
                type: 'bar',
                data: { labels: histogram.labels, datasets: [{ label: '問題(WID)数', data: histogram.counts, backgroundColor: colors(), borderColor: conditions.map((condition) => this.conditions.has(condition.id) ? 'rgba(30, 64, 175, 1)' : 'rgba(29, 78, 216, 1)'), borderWidth: conditions.map((condition) => this.conditions.has(condition.id) ? 3 : 1), borderRadius: 4 }] },
                plugins: [overflowMarkerPlugin(countAxis.overflowIndexes)],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: true },
                    onClick: (_event, elements, chart) => {
                        const index = Number.isInteger(elements?.[0]?.index)
                            ? elements[0].index
                            : overflowIndexAtPointer(chart, _event, countAxis.overflowIndexes);
                        if (!Number.isInteger(index) || !conditions[index]?.members.size) return;
                        const condition = conditions[index];
                        if (this.conditions.has(condition.id)) this.conditions.delete(condition.id); else this.conditions.set(condition.id, condition);
                        this.applyHistogramSelection();
                        chart.data.datasets[0].backgroundColor = colors();
                        chart.data.datasets[0].borderColor = conditions.map((item) => this.conditions.has(item.id) ? 'rgba(30, 64, 175, 1)' : 'rgba(29, 78, 216, 1)');
                        chart.data.datasets[0].borderWidth = conditions.map((item) => this.conditions.has(item.id) ? 3 : 1);
                        chart.update('none');
                    },
                    onHover: (event, elements, chart) => syncOverflowHover(chart, event, elements, countAxis.overflowIndexes),
                    plugins: { legend: { display: false }, title: { display: true, text: `${label}の問題(WID)分布` }, tooltip: { callbacks: { afterBody: (items) => {
                        const index = items?.[0]?.dataIndex;
                        const bin = histogram.bins[index];
                        return bin ? this.widChartTooltipLines(bin, histogram, unit, countAxis, index) : [];
                    } } } },
                    scales: { x: { title: { display: true, text: `問題(WID)ごとの平均値${unit ? ` (${unit})` : ''}` } }, y: { beginAtZero: true, max: countAxis.max, title: { display: true, text: '問題(WID)数' }, ticks: { precision: 0, stepSize: countAxis.step } } },
                },
            });
            return true;
        }

        renderSaved() {
            const target = this.q('saved');
            if (!target) return;
            const conditions = [...this.conditions.values()];
            target.innerHTML = `<div class="trh-saved-heading"><strong>選択した縦棒（すべてOR）</strong><button type="button" data-action="clear-bars">すべて解除</button></div><div class="trh-saved-list">${conditions.length ? conditions.map((condition) => `<span class="trh-saved" data-condition-id="${escapeHtml(condition.id)}"><span>${escapeHtml(condition.label)}</span><button type="button" data-action="remove-bar" aria-label="選択した縦棒を解除">×</button></span>`).join('') : '<span>選択した縦棒はありません。</span>'}</div>`;
            this.renderSelectedWidInfo();
        }

        renderSelectedWidInfo() {
            const target = this.q('selected-info');
            if (!target) return;
            const directory = this.widDirectory();
            const rows = [...this.resultWids].sort(compareIds).map((wid) => directory.get(wid)).filter(Boolean);
            target.innerHTML = rows.length ? `<section class="trh-selected-question-info"><h6>選択した縦棒に含まれる問題情報（${rows.length}件）</h6><div class="trh-question-detail-list">${rows.map((row) => {
                const info = this.widInfo(row);
                return `<article><strong>問題(WID): ${escapeHtml(row.WID)}</strong><p>${escapeHtml(row.Sentence || '英文未登録')}</p><dl><div><dt>レベル</dt><dd>${escapeHtml(info.level)}</dd></div><div><dt>文法</dt><dd>${escapeHtml(info.grammar)}</dd></div></dl></article>`;
            }).join('')}</div></section>` : '';
        }

        applyHistogramSelection() {
            this.resultWids = new Set();
            this.conditions.forEach((condition) => condition.members.forEach((wid) => this.resultWids.add(wid)));
            this.renderSaved();
            this.renderSelectionSummary();
        }

        currentWids() {
            const source = this.selectionMode === 'histogram' ? this.resultWids : this.checkboxWids;
            return [...source].sort(compareIds);
        }

        renderSelectionSummary() {
            const target = this.q('selection-summary');
            if (!target) return;
            const count = this.currentWids().length;
            target.textContent = this.selectionMode === 'histogram'
                ? (this.conditions.size ? `${this.conditions.size}本の縦棒をORで合算し、${count}件の問題(WID)を選択しています。` : 'ヒストグラムの縦棒をクリックして問題(WID)を選択してください。')
                : `${count}件の問題(WID)をチェックボックスで選択しています。`;
        }

        async requestResults() {
            const target = this.q('results');
            const wids = this.currentWids();
            if (!target) return;
            if (!wids.length) {
                window.alert('問題(WID)を1件以上選択してください。');
                return;
            }
            const sequence = ++this.resultSequence;
            target.innerHTML = `<p class="loading">${wids.length}件の問題(WID)について学習者情報を読み込んでいます...</p>`;
            try {
                const data = await this.onResolve({ studentId: this.studentId, wids, correctness: this.q('correctness')?.value || 'all', hesitation: this.q('hesitation')?.value || 'all' });
                if (sequence !== this.resultSequence) return;
                this.hasRenderedResults = true;
                if (this.onRender) this.onRender({ target, data, studentId: this.studentId, wids });
                else target.innerHTML = `<p>${Array.isArray(data?.attempts) ? data.attempts.length : 0}件の解答結果があります。</p>`;
            } catch (_error) {
                if (sequence !== this.resultSequence) return;
                target.innerHTML = '<p class="error">学習者情報を読み込めませんでした。</p>';
            }
        }

        removeBar(id) {
            if (!this.conditions.delete(id)) return;
            this.applyHistogramSelection();
            this.renderChart();
        }

        handleClick(event) {
            const button = event.target.closest('[data-action]');
            if (!button || !this.data) return;
            const action = button.dataset.action;
            if (action === 'remove-bar') this.removeBar(button.closest('[data-condition-id]')?.dataset.conditionId || '');
            else if (action === 'clear-bars') {
                this.conditions.clear();
                this.applyHistogramSelection();
                this.renderChart();
            } else if (action === 'apply-bin-width') this.applyBinWidth();
            else if (action === 'show-results') this.requestResults();
        }

        handleChange(event) {
            if (!this.data) return;
            const role = event.target.dataset.role;
            if (role === 'selection-mode') {
                this.selectionMode = event.target.value === 'histogram' ? 'histogram' : 'checkbox';
                this.q('checkbox-panel').hidden = this.selectionMode !== 'checkbox';
                this.q('histogram-panel').hidden = this.selectionMode !== 'histogram';
                this.renderSelectionSummary();
                if (this.selectionMode === 'histogram') this.chart?.resize();
            } else if (role === 'select-all') {
                this.checkboxWids.clear();
                this.root.querySelectorAll('[data-role="wid-checkbox"]').forEach((checkbox) => {
                    checkbox.checked = event.target.checked;
                    if (checkbox.checked) this.checkboxWids.add(String(checkbox.value));
                });
                this.renderSelectionSummary();
            } else if (role === 'wid-checkbox') {
                if (event.target.checked) this.checkboxWids.add(String(event.target.value)); else this.checkboxWids.delete(String(event.target.value));
                const boxes = [...this.root.querySelectorAll('[data-role="wid-checkbox"]')];
                if (this.q('select-all')) this.q('select-all').checked = boxes.length > 0 && boxes.every((checkbox) => checkbox.checked);
                this.renderSelectionSummary();
            } else if (role === 'feature') {
                this.syncBinControls();
                this.renderChart();
            } else if (role === 'bin-mode') this.changeBinMode(event.target.value);
            else if ((role === 'correctness' || role === 'hesitation') && this.hasRenderedResults) this.requestResults();
        }
    }

    window.StudentWidHistogramAnalysis = {
        create(options) {
            const root = typeof options.root === 'string' ? document.querySelector(options.root) : options.root;
            return root ? new StudentWidHistogramAnalysis(options) : null;
        },
        _test: { buildHistogram, buildCountAxis, overflowIndexAtPointer },
    };
}());

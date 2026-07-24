document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.getElementById('search-form');
    const studentList = document.getElementById('student-list');
    const histogramStudentList = document.getElementById('histogram-student-list');
    const studentListDisplayMode = document.getElementById('student-list-display-mode');
    const histogramStudentListSummary = document.getElementById('histogram-student-list-summary');
    const filterAverageScope = document.getElementById('filter-feature-average-scope');
    const histogramAverageScope = document.getElementById('histogram-feature-average-scope');
    const featureOptions = Object.entries(window.studentGroupFeatureColumns || {})
        .map(([value, label]) => ({ value, label }));
    const featureDisplayMeta = window.studentGroupFeatureDisplayMeta || {};
    const histogramData = window.studentGroupHistogramData || {};
    const histogramFeaturePairs = Array.isArray(histogramData.featurePairs) ? histogramData.featurePairs : [];
    const histogramMetricAttempts = Array.isArray(histogramData.metricAttempts) ? histogramData.metricAttempts : [];
    const uidLogicFilterGroups = window.studentGroupLogicFilterGroups || [];
    const uidLogicFilterStudentsByGroup = window.studentGroupLogicFilterStudentsByGroup || {};
    const featureFilterPlaceholders = window.studentGroupFeatureFilterPlaceholders || { min: '最小値', max: '最大値' };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const compareIds = (a, b) => String(a).localeCompare(String(b), 'ja', { numeric: true });
    const setUnion = (left, right) => new Set([...left, ...right]);
    const setIntersection = (left, right) => new Set([...left].filter((value) => right.has(value)));
    const setComplement = (source, universe) => new Set([...universe].filter((value) => !source.has(value)));

    const getFeatureMeta = (feature) => featureDisplayMeta[feature] || { displayScale: 1, unit: '' };
    const toDisplayFeatureValue = (feature, value) => {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
            return null;
        }
        return Number(value) * Number(getFeatureMeta(feature).displayScale || 1);
    };
    const formatFeatureValue = (feature, value) => {
        const displayValue = toDisplayFeatureValue(feature, value);
        if (displayValue === null) {
            return '-';
        }
        return `${displayValue.toFixed(2)}${getFeatureMeta(feature).unit || ''}`;
    };
    const createFeatureTooltipHtml = (title, values, count) => {
        const rows = featureOptions.map((option) => `
            <span class="feature-tooltip-label">${escapeHtml(option.label)}</span>
            <span class="feature-tooltip-value">${escapeHtml(formatFeatureValue(option.value, values[option.value]))}</span>
        `).join('');
        return `
            <span class="student-feature-popup" role="tooltip" hidden>
                <span class="feature-tooltip-title">${escapeHtml(title)} (${count}件)</span>
                <span class="feature-tooltip-grid">${rows}</span>
            </span>
        `;
    };

    const setupAccordion = (toggleId, panelId, onOpen = null) => {
        const toggle = document.getElementById(toggleId);
        const panel = document.getElementById(panelId);
        if (!toggle || !panel) {
            return;
        }
        toggle.setAttribute('aria-expanded', 'false');
        panel.hidden = true;
        toggle.addEventListener('click', () => {
            const willExpand = toggle.getAttribute('aria-expanded') !== 'true';
            toggle.setAttribute('aria-expanded', String(willExpand));
            panel.hidden = !willExpand;
            if (willExpand && typeof onOpen === 'function') {
                onOpen();
            }
        });
    };

    let initializeHistograms = () => {};
    setupAccordion('filter-conditions-toggle', 'filter-conditions-panel');
    setupAccordion('histogram-conditions-toggle', 'histogram-conditions-panel', () => initializeHistograms());

    const evaluateSetExpression = (tokens, resolveCondition, universe) => {
        if (tokens.length === 0) {
            return null;
        }
        let index = 0;
        const primary = () => {
            const token = tokens[index];
            if (!token) {
                throw new Error('条件が途中で終わっています。');
            }
            if (token.type === 'operator' && token.operator === 'NOT') {
                index += 1;
                return setComplement(primary(), universe);
            }
            if (token.type === 'paren' && token.paren === '(') {
                index += 1;
                const result = orExpression();
                if (tokens[index]?.type !== 'paren' || tokens[index]?.paren !== ')') {
                    throw new Error('閉じ括弧を置いてください。');
                }
                index += 1;
                return result;
            }
            if (token.type === 'condition') {
                index += 1;
                const result = resolveCondition(token);
                if (!(result instanceof Set)) {
                    throw new Error('対象を選択してください。');
                }
                return result;
            }
            throw new Error('条件または括弧を置いてください。');
        };
        const andExpression = () => {
            let result = primary();
            while (tokens[index]?.type === 'operator' && tokens[index].operator === 'AND') {
                index += 1;
                result = setIntersection(result, primary());
            }
            return result;
        };
        const orExpression = () => {
            let result = andExpression();
            while (tokens[index]?.type === 'operator' && tokens[index].operator === 'OR') {
                index += 1;
                result = setUnion(result, andExpression());
            }
            return result;
        };
        const result = orExpression();
        if (index !== tokens.length) {
            throw new Error('式の並びを確認してください。');
        }
        return result;
    };

    let scheduleFilterSearch = () => {};
    let scheduleHistogramRendering = () => {};

    const setupUidSelectionArea = ({
        panelId,
        builderId,
        summaryId,
        containerId,
        insertPositionId,
        addButtonSelector,
        resetButtonId,
        trimButtonId,
        clearButtonId,
        itemSelector,
        checkboxSelector,
        selectAllSelector,
        selectAllClassSelector,
        onSelectionChanged,
    }) => {
        const panel = document.getElementById(panelId);
        const builder = document.getElementById(builderId);
        const summary = document.getElementById(summaryId);
        const container = document.getElementById(containerId);
        const insertPosition = document.getElementById(insertPositionId);
        if (!panel || !builder || !summary || !container || !insertPosition) {
            return null;
        }

        const getItems = () => Array.from(container.querySelectorAll(itemSelector));
        const getCheckbox = (item) => item.querySelector(checkboxSelector);
        const allIds = () => getItems().map((item) => String(getCheckbox(item)?.value ?? '')).filter(Boolean);
        const classMap = () => {
            const map = {};
            getItems().forEach((item) => {
                const classId = String(item.dataset.classId || '');
                const checkbox = getCheckbox(item);
                if (!classId || !checkbox) {
                    return;
                }
                if (!map[classId]) {
                    map[classId] = [];
                }
                map[classId].push(String(checkbox.value));
            });
            return map;
        };
        const targetOptions = () => {
            const classes = Array.from(container.querySelectorAll(selectAllClassSelector)).map((input) => ({
                value: `class:${String(input.dataset.classId || '')}`,
                label: `グループ(クラス): ${input.closest('.class-group-header')?.querySelector('h5')?.textContent?.trim() || input.dataset.classId}`,
            }));
            const groups = uidLogicFilterGroups.map((group) => ({
                value: `group:${String(group.group_id)}`,
                label: `グループ: ${group.group_name}`,
            }));
            return [...classes, ...groups];
        };
        const targetOptionsHtml = (selected = '') => {
            const options = targetOptions();
            if (options.length === 0) {
                return '<option value="">対象がありません</option>';
            }
            return options.map((option) => (
                `<option value="${escapeHtml(option.value)}"${option.value === selected ? ' selected' : ''}>${escapeHtml(option.label)}</option>`
            )).join('');
        };
        const kindOptionsHtml = (selected) => [
            ['condition', '対象'],
            ['and', 'AND'],
            ['or', 'OR'],
            ['not', 'NOT'],
            ['open', '('],
            ['close', ')'],
        ].map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${label}</option>`).join('');
        const tokenLabel = (token) => {
            if (token.dataset.kind === 'condition') {
                const select = token.querySelector('.logic-filter-target');
                return select?.options[select.selectedIndex]?.textContent || '対象';
            }
            if (token.dataset.kind === 'open') return '(';
            if (token.dataset.kind === 'close') return ')';
            return token.dataset.kind.toUpperCase();
        };
        const updateInsertOptions = () => {
            const current = insertPosition.value;
            const tokens = Array.from(builder.querySelectorAll('.logic-filter-token'));
            insertPosition.innerHTML = ['<option value="">末尾に追加</option>']
                .concat(tokens.map((token, index) => `<option value="${index}">${index + 1}個目の前 (${escapeHtml(tokenLabel(token))})</option>`))
                .join('');
            if (current !== '' && Number(current) < tokens.length) {
                insertPosition.value = current;
            }
        };
        const renderToken = (token, kind, target = '') => {
            token.className = 'logic-filter-token';
            token.dataset.kind = kind;
            const kindSelect = `<select class="logic-filter-kind">${kindOptionsHtml(kind)}</select>`;
            if (kind === 'condition') {
                token.innerHTML = `${kindSelect}<select class="logic-filter-target">${targetOptionsHtml(target)}</select><button type="button" class="logic-filter-remove" aria-label="部品を削除">x</button>`;
            } else if (kind === 'open' || kind === 'close') {
                token.classList.add('paren');
                token.innerHTML = `${kindSelect}<span>${kind === 'open' ? '(' : ')'}</span><button type="button" class="logic-filter-remove" aria-label="部品を削除">x</button>`;
            } else {
                token.classList.add('operator');
                token.dataset.operator = kind.toUpperCase();
                token.innerHTML = `${kindSelect}<span>${kind.toUpperCase()}</span><button type="button" class="logic-filter-remove" aria-label="部品を削除">x</button>`;
            }
        };
        const addToken = (kind) => {
            const token = document.createElement('span');
            renderToken(token, kind);
            const tokens = Array.from(builder.querySelectorAll('.logic-filter-token'));
            const position = insertPosition.value === '' ? tokens.length : Number(insertPosition.value);
            if (Number.isInteger(position) && position >= 0 && position < tokens.length) {
                builder.insertBefore(token, tokens[position]);
                insertPosition.value = String(position + 1);
            } else {
                builder.appendChild(token);
            }
            updateInsertOptions();
            evaluateAndApply();
        };
        const getTokens = () => Array.from(builder.querySelectorAll('.logic-filter-token')).map((token) => {
            const kind = token.dataset.kind;
            if (kind === 'condition') {
                const [targetType, targetId] = String(token.querySelector('.logic-filter-target')?.value || '').split(':');
                return { type: 'condition', targetType, targetId };
            }
            if (kind === 'open' || kind === 'close') {
                return { type: 'paren', paren: kind === 'open' ? '(' : ')' };
            }
            return { type: 'operator', operator: kind.toUpperCase() };
        });
        const syncMasterChecks = () => {
            container.querySelectorAll(selectAllClassSelector).forEach((input) => {
                const items = getItems().filter((item) => String(item.dataset.classId) === String(input.dataset.classId));
                input.checked = items.length > 0 && items.every((item) => getCheckbox(item)?.checked);
                input.indeterminate = items.some((item) => getCheckbox(item)?.checked) && !input.checked;
            });
            const selectAll = container.querySelector(selectAllSelector);
            if (selectAll) {
                const items = getItems();
                selectAll.checked = items.length > 0 && items.every((item) => getCheckbox(item)?.checked);
                selectAll.indeterminate = items.some((item) => getCheckbox(item)?.checked) && !selectAll.checked;
            }
        };
        const applyIds = (selected) => {
            getItems().forEach((item) => {
                const checkbox = getCheckbox(item);
                if (checkbox) {
                    checkbox.checked = selected.has(String(checkbox.value));
                }
            });
            syncMasterChecks();
            onSelectionChanged?.();
        };
        const evaluateAndApply = () => {
            try {
                const universe = new Set(allIds());
                const tokens = getTokens();
                const selected = tokens.length === 0
                    ? universe
                    : evaluateSetExpression(tokens, (token) => {
                        const source = token.targetType === 'group' ? uidLogicFilterStudentsByGroup : classMap();
                        const values = source[String(token.targetId)] || [];
                        return new Set(values.map(String).filter((uid) => universe.has(uid)));
                    }, universe);
                applyIds(selected);
                summary.textContent = tokens.length === 0
                    ? 'すべての学習者を対象にしています。'
                    : `${selected.size}名の学習者を選択しています。`;
                summary.classList.remove('is-error');
                return true;
            } catch (error) {
                summary.textContent = error.message || '論理式を確認してください。';
                summary.classList.add('is-error');
                return false;
            }
        };

        panel.querySelectorAll(addButtonSelector).forEach((button) => {
            button.addEventListener('click', () => addToken(button.dataset.addUidFilter || button.dataset.addHistogramUidFilter));
        });
        builder.addEventListener('click', (event) => {
            const removeButton = event.target.closest('.logic-filter-remove');
            if (!removeButton) return;
            removeButton.closest('.logic-filter-token')?.remove();
            updateInsertOptions();
            evaluateAndApply();
        });
        builder.addEventListener('change', (event) => {
            const token = event.target.closest('.logic-filter-token');
            if (!token) return;
            if (event.target.classList.contains('logic-filter-kind')) {
                renderToken(token, event.target.value);
            }
            updateInsertOptions();
            evaluateAndApply();
        });
        document.getElementById(resetButtonId)?.addEventListener('click', () => {
            builder.innerHTML = '';
            updateInsertOptions();
            evaluateAndApply();
        });
        document.getElementById(clearButtonId)?.addEventListener('click', () => {
            builder.innerHTML = '';
            updateInsertOptions();
            evaluateAndApply();
        });
        document.getElementById(trimButtonId)?.addEventListener('click', () => {
            if (insertPosition.value === '') {
                summary.textContent = '削除を開始する追加位置を選択してください。';
                summary.classList.add('is-error');
                return;
            }
            const start = Number(insertPosition.value);
            Array.from(builder.querySelectorAll('.logic-filter-token')).forEach((token, index) => {
                if (index >= start) token.remove();
            });
            updateInsertOptions();
            evaluateAndApply();
        });
        container.addEventListener('change', (event) => {
            const target = event.target;
            if (target.matches(selectAllSelector)) {
                getItems().forEach((item) => {
                    const checkbox = getCheckbox(item);
                    if (checkbox) checkbox.checked = target.checked;
                });
                container.querySelectorAll(selectAllClassSelector).forEach((input) => {
                    input.checked = target.checked;
                    input.indeterminate = false;
                });
            } else if (target.matches(selectAllClassSelector)) {
                getItems()
                    .filter((item) => String(item.dataset.classId) === String(target.dataset.classId))
                    .forEach((item) => {
                        const checkbox = getCheckbox(item);
                        if (checkbox) checkbox.checked = target.checked;
                    });
            }
            if (target.matches(`${checkboxSelector}, ${selectAllSelector}, ${selectAllClassSelector}`)) {
                syncMasterChecks();
                onSelectionChanged?.();
            }
        });
        updateInsertOptions();
        syncMasterChecks();
        return { evaluateAndApply, syncMasterChecks };
    };

    setupUidSelectionArea({
        panelId: 'uid-logic-filter-panel',
        builderId: 'uid-logic-filter-builder',
        summaryId: 'uid-logic-filter-summary',
        containerId: 'uid-checkbox-list',
        insertPositionId: 'uid-logic-filter-insert-position',
        addButtonSelector: '[data-add-uid-filter]',
        resetButtonId: 'reset-uid-logic-filter',
        trimButtonId: 'trim-uid-logic-filter',
        clearButtonId: 'clear-uid-logic-filter',
        itemSelector: '.uid-filter-item',
        checkboxSelector: '.uid-checkbox',
        selectAllSelector: '.select-all',
        selectAllClassSelector: '.select-all-class',
        onSelectionChanged: () => scheduleFilterSearch(0),
    });
    setupUidSelectionArea({
        panelId: 'histogram-uid-logic-filter-panel',
        builderId: 'histogram-uid-logic-filter-builder',
        summaryId: 'histogram-uid-logic-filter-summary',
        containerId: 'histogram-uid-checkbox-list',
        insertPositionId: 'histogram-uid-logic-filter-insert-position',
        addButtonSelector: '[data-add-histogram-uid-filter]',
        resetButtonId: 'reset-histogram-uid-logic-filter',
        trimButtonId: 'trim-histogram-uid-logic-filter',
        clearButtonId: 'clear-histogram-uid-logic-filter',
        itemSelector: '.histogram-uid-filter-item',
        checkboxSelector: '.histogram-uid-checkbox',
        selectAllSelector: '.histogram-select-all',
        selectAllClassSelector: '.histogram-select-all-class',
        onSelectionChanged: () => scheduleHistogramRendering(true),
    });

    const featureFilterBuilder = document.getElementById('feature-filter-builder');
    const featureFilterExpressionInput = document.getElementById('feature-filter-expression');
    const featureFilterSettings = document.getElementById('feature-filter-settings');
    const featureFilterSummary = document.getElementById('feature-filter-summary');
    const featureFilterInsertPosition = document.getElementById('feature-filter-insert-position');
    const featureSettingValues = {};

    const setupFeatureFilterBuilder = () => {
        if (!featureFilterBuilder || !featureFilterExpressionInput || !featureFilterSettings || !featureFilterInsertPosition) {
            return;
        }
        const tokens = () => Array.from(featureFilterBuilder.querySelectorAll('.feature-filter-token'));
        const kindOptions = (selected) => [
            ['condition', '特徴量'],
            ['and', 'AND'],
            ['or', 'OR'],
            ['not', 'NOT'],
            ['open', '('],
            ['close', ')'],
        ].map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${label}</option>`).join('');
        const featureTargetOptions = (selected = '') => featureOptions.map((option) => (
            `<option value="${escapeHtml(option.value)}"${option.value === selected ? ' selected' : ''}>${escapeHtml(option.label)} (${escapeHtml(option.value)})</option>`
        )).join('');
        const tokenLabel = (token) => {
            if (token.dataset.kind === 'condition') {
                const select = token.querySelector('.feature-filter-target');
                return select?.options[select.selectedIndex]?.textContent || '特徴量';
            }
            if (token.dataset.kind === 'open') return '(';
            if (token.dataset.kind === 'close') return ')';
            return token.dataset.kind.toUpperCase();
        };
        const updateInsert = () => {
            const current = featureFilterInsertPosition.value;
            featureFilterInsertPosition.innerHTML = ['<option value="">末尾に追加</option>']
                .concat(tokens().map((token, index) => `<option value="${index}">${index + 1}個目の前 (${escapeHtml(tokenLabel(token))})</option>`))
                .join('');
            if (current !== '' && Number(current) < tokens().length) {
                featureFilterInsertPosition.value = current;
            }
        };
        const readSettings = () => {
            featureFilterSettings.querySelectorAll('.feature-filter-setting-row').forEach((row) => {
                featureSettingValues[row.dataset.feature] = {
                    min: row.querySelector('.feature-filter-min')?.value || '',
                    max: row.querySelector('.feature-filter-max')?.value || '',
                };
            });
        };
        const renderSettings = () => {
            readSettings();
            const selectedFeatures = [];
            tokens().forEach((token) => {
                if (token.dataset.kind !== 'condition') return;
                const feature = token.querySelector('.feature-filter-target')?.value;
                if (feature && !selectedFeatures.includes(feature)) selectedFeatures.push(feature);
            });
            if (selectedFeatures.length === 0) {
                featureFilterSettings.innerHTML = '<p class="feature-filter-empty">論理式に特徴量を追加すると、ここに最小値・最大値の設定欄が表示されます。</p>';
                return;
            }
            featureFilterSettings.innerHTML = selectedFeatures.map((feature) => {
                const option = featureOptions.find((item) => item.value === feature);
                const values = featureSettingValues[feature] || { min: '', max: '' };
                return `
                    <div class="feature-filter-setting-row" data-feature="${escapeHtml(feature)}">
                        <div>
                            <div class="feature-filter-setting-name">${escapeHtml(option?.label || feature)}</div>
                            <div class="feature-filter-setting-key">${escapeHtml(feature)}</div>
                            <input type="hidden" name="feature_filters[${escapeHtml(feature)}][enabled]" value="1">
                        </div>
                        <label><span>${escapeHtml(featureFilterPlaceholders.min)}</span><input class="feature-filter-min" type="number" step="any" name="feature_filters[${escapeHtml(feature)}][min]" value="${escapeHtml(values.min)}"></label>
                        <label><span>${escapeHtml(featureFilterPlaceholders.max)}</span><input class="feature-filter-max" type="number" step="any" name="feature_filters[${escapeHtml(feature)}][max]" value="${escapeHtml(values.max)}"></label>
                    </div>
                `;
            }).join('');
        };
        const renderToken = (token, kind, selectedTarget = '') => {
            token.className = 'feature-filter-token';
            token.dataset.kind = kind;
            const kindSelect = `<select class="feature-filter-token-kind">${kindOptions(kind)}</select>`;
            if (kind === 'condition') {
                token.innerHTML = `${kindSelect}<select class="feature-filter-target">${featureTargetOptions(selectedTarget || featureOptions[0]?.value || '')}</select><button type="button" class="feature-filter-token-remove" aria-label="部品を削除">x</button>`;
            } else {
                token.innerHTML = `${kindSelect}<button type="button" class="feature-filter-token-remove" aria-label="部品を削除">x</button>`;
            }
        };
        const addToken = (kind) => {
            const token = document.createElement('div');
            renderToken(token, kind);
            const currentTokens = tokens();
            const position = featureFilterInsertPosition.value === '' ? currentTokens.length : Number(featureFilterInsertPosition.value);
            if (Number.isInteger(position) && position >= 0 && position < currentTokens.length) {
                featureFilterBuilder.insertBefore(token, currentTokens[position]);
                featureFilterInsertPosition.value = String(position + 1);
            } else {
                featureFilterBuilder.appendChild(token);
            }
            updateInsert();
            renderSettings();
            syncExpression();
        };
        const expressionTokens = () => tokens().map((token) => {
            const kind = token.dataset.kind;
            if (kind === 'condition') {
                return { type: 'condition', feature: token.querySelector('.feature-filter-target')?.value || '' };
            }
            if (kind === 'open' || kind === 'close') {
                return { type: 'paren', paren: kind === 'open' ? '(' : ')' };
            }
            return { type: 'operator', operator: kind.toUpperCase() };
        });
        const validateExpression = (list) => {
            if (list.length === 0) return;
            evaluateSetExpression(
                list,
                (token) => token.feature ? new Set([token.feature]) : null,
                new Set(featureOptions.map((option) => option.value))
            );
        };
        const syncExpression = () => {
            const list = expressionTokens();
            try {
                validateExpression(list);
                featureFilterExpressionInput.value = JSON.stringify(list);
                featureFilterSummary.textContent = list.length === 0
                    ? '特徴量条件は未設定です。'
                    : `${list.filter((token) => token.type === 'condition').length}個の特徴量条件を検索に使用します。`;
                featureFilterSummary.classList.remove('is-error');
                scheduleFilterSearch(300);
                return true;
            } catch (error) {
                featureFilterSummary.textContent = error.message || '特徴量の論理式が正しくありません。';
                featureFilterSummary.classList.add('is-error');
                return false;
            }
        };

        document.getElementById('add-feature-filter-condition')?.addEventListener('click', () => addToken('condition'));
        document.getElementById('add-feature-filter-and')?.addEventListener('click', () => addToken('and'));
        document.getElementById('add-feature-filter-or')?.addEventListener('click', () => addToken('or'));
        document.getElementById('add-feature-filter-not')?.addEventListener('click', () => addToken('not'));
        document.getElementById('add-feature-filter-open')?.addEventListener('click', () => addToken('open'));
        document.getElementById('add-feature-filter-close')?.addEventListener('click', () => addToken('close'));
        document.getElementById('reset-feature-filter-expression')?.addEventListener('click', () => {
            featureFilterBuilder.innerHTML = '';
            updateInsert();
            renderSettings();
            syncExpression();
        });
        document.getElementById('clear-feature-filter-expression')?.addEventListener('click', () => {
            featureFilterBuilder.innerHTML = '';
            updateInsert();
            renderSettings();
            syncExpression();
        });
        document.getElementById('trim-feature-filter-expression')?.addEventListener('click', () => {
            if (featureFilterInsertPosition.value === '') {
                featureFilterSummary.textContent = '削除を開始する追加位置を選択してください。';
                featureFilterSummary.classList.add('is-error');
                return;
            }
            const start = Number(featureFilterInsertPosition.value);
            tokens().forEach((token, index) => {
                if (index >= start) token.remove();
            });
            updateInsert();
            renderSettings();
            syncExpression();
        });
        featureFilterBuilder.addEventListener('change', (event) => {
            const token = event.target.closest('.feature-filter-token');
            if (!token) return;
            if (event.target.classList.contains('feature-filter-token-kind')) {
                renderToken(token, event.target.value);
            }
            updateInsert();
            renderSettings();
            syncExpression();
        });
        featureFilterBuilder.addEventListener('click', (event) => {
            const remove = event.target.closest('.feature-filter-token-remove');
            if (!remove) return;
            remove.closest('.feature-filter-token')?.remove();
            updateInsert();
            renderSettings();
            syncExpression();
        });
        featureFilterSettings.addEventListener('input', () => {
            readSettings();
            scheduleFilterSearch(300);
        });
        updateInsert();
        renderSettings();
        featureFilterExpressionInput.value = '[]';
    };
    setupFeatureFilterBuilder();

    const filterStudentCheckboxState = new Map();
    let filterRequestController = null;
    let filterSearchTimer = null;
    const saveFilterStudentChecks = () => {
        studentList?.querySelectorAll('input[name="students[]"]').forEach((input) => {
            filterStudentCheckboxState.set(String(input.value), input.checked);
        });
    };
    const restoreFilterStudentChecks = () => {
        studentList?.querySelectorAll('input[name="students[]"]').forEach((input) => {
            input.checked = filterStudentCheckboxState.get(String(input.value)) || false;
        });
    };
    const requestFilterStudents = async () => {
        if (!searchForm || !studentList) {
            return;
        }
        window.clearTimeout(filterSearchTimer);
        saveFilterStudentChecks();
        const formData = new FormData(searchForm);
        formData.set('uid_selection_present', '1');
        formData.set('wid_selection_present', '1');
        formData.set('average_scope', filterAverageScope?.value || 'selected');
        if (!formData.get('accuracy_min')) formData.set('accuracy_min', '0');
        if (!formData.get('accuracy_max')) formData.set('accuracy_max', '100');
        if (!formData.get('hesitation_rate_min')) formData.set('hesitation_rate_min', '0');
        if (!formData.get('hesitation_rate_max')) formData.set('hesitation_rate_max', '100');
        if (!formData.get('total_answers_min')) formData.set('total_answers_min', '0');
        if (!formData.get('total_answers_max')) formData.set('total_answers_max', '99999999');

        filterRequestController?.abort();
        const requestController = new AbortController();
        filterRequestController = requestController;
        studentList.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch('search-students.php', {
                method: 'POST',
                body: formData,
                signal: requestController.signal,
            });
            const html = await response.text();
            if (filterRequestController !== requestController) {
                return;
            }
            if (!response.ok) {
                throw new Error(html.replace(/<[^>]+>/g, '').trim() || '学習者の検索に失敗しました。');
            }
            studentList.innerHTML = html;
            restoreFilterStudentChecks();
            syncStudentListDisplayMode();
        } catch (error) {
            if (error.name !== 'AbortError' && filterRequestController === requestController) {
                studentList.innerHTML = `<li class="student-list-status student-list-error">${escapeHtml(error.message || '学習者の検索に失敗しました。')}</li>`;
            }
        } finally {
            if (filterRequestController === requestController) {
                studentList.removeAttribute('aria-busy');
            }
        }
    };
    scheduleFilterSearch = (delay = 0) => {
        window.clearTimeout(filterSearchTimer);
        filterSearchTimer = window.setTimeout(requestFilterStudents, Math.max(0, delay));
    };

    searchForm?.addEventListener('change', (event) => {
        if (event.target.matches('input[type="number"]')) {
            scheduleFilterSearch(300);
        } else {
            scheduleFilterSearch(0);
        }
    });
    searchForm?.addEventListener('input', (event) => {
        if (event.target.matches('input[type="number"]')) {
            scheduleFilterSearch(300);
        }
    });
    studentList?.addEventListener('change', (event) => {
        if (event.target.matches('input[name="students[]"]')) {
            filterStudentCheckboxState.set(String(event.target.value), event.target.checked);
        }
    });
    filterAverageScope?.addEventListener('change', () => scheduleFilterSearch(0));
    document.getElementById('select-all-wid-btn')?.addEventListener('click', () => {
        document.querySelectorAll('#wid-checkbox-list .wid-checkbox').forEach((checkbox) => {
            checkbox.checked = true;
        });
        scheduleFilterSearch(0);
    });
    document.getElementById('deselect-all-wid-btn')?.addEventListener('click', () => {
        document.querySelectorAll('#wid-checkbox-list .wid-checkbox').forEach((checkbox) => {
            checkbox.checked = false;
        });
        scheduleFilterSearch(0);
    });

    const histogramStudentDirectory = new Map(
        Array.from(document.querySelectorAll('.histogram-uid-filter-item')).map((item) => {
            const input = item.querySelector('.histogram-uid-checkbox');
            return [String(input?.value || ''), item.dataset.studentName || ''];
        }).filter(([uid]) => uid)
    );
    const allHistogramUids = new Set(histogramStudentDirectory.keys());
    const allHistogramWids = new Set(
        Array.from(document.querySelectorAll('.histogram-wid-checkbox')).map((input) => String(input.value))
    );
    const getCheckedIds = (selector) => new Set(
        Array.from(document.querySelectorAll(selector)).filter((input) => input.checked).map((input) => String(input.value))
    );
    const histogramStudentCheckboxState = new Map();
    const histogramBarConditions = {
        uid: new Map(),
        wid: new Map(),
    };
    const barLogicControllers = {};
    let applyingWidBarResult = false;
    let appliedHistogramWids = getCheckedIds('.histogram-wid-checkbox');
    const savedWidBarsList = document.getElementById('histogram-saved-wid-bars-list');
    const widFilterApplySummary = document.getElementById('histogram-wid-filter-apply-summary');

    const markWidFilterPending = () => {
        if (!widFilterApplySummary) return;
        widFilterApplySummary.textContent = 'WID選択に未反映の変更があります。「検索」を押すとヒストグラムと特徴量平均へ反映されます。';
        widFilterApplySummary.classList.add('is-pending');
    };
    const renderSavedWidBars = () => {
        if (!savedWidBarsList) return;
        const conditions = [...histogramBarConditions.wid.values()];
        if (conditions.length === 0) {
            savedWidBarsList.innerHTML = '<span class="histogram-saved-wid-bars-empty">保存したWID縦棒はありません。</span>';
            return;
        }
        savedWidBarsList.innerHTML = conditions.map((condition) => `
            <span class="histogram-saved-wid-bar">
                <span>${escapeHtml(condition.label)}</span>
                <button type="button" data-saved-wid-bar-id="${escapeHtml(condition.id)}" aria-label="${escapeHtml(condition.label)}を削除">×</button>
            </span>
        `).join('');
    };

    const getHistogramFeatureAverages = (uid) => {
        const selectedOnly = (histogramAverageScope?.value || 'selected') === 'selected';
        const selectedWids = selectedOnly ? appliedHistogramWids : null;
        const sums = {};
        const counts = {};
        histogramFeaturePairs.forEach((pair) => {
            if (String(pair.uid) !== String(uid)) return;
            if (selectedWids && !selectedWids.has(String(pair.wid))) return;
            featureOptions.forEach((option) => {
                const average = pair.features?.[option.value];
                const count = Number(pair.featureCounts?.[option.value] || 0);
                if (average === null || average === undefined || !Number.isFinite(Number(average)) || count <= 0) return;
                sums[option.value] = (sums[option.value] || 0) + (Number(average) * count);
                counts[option.value] = (counts[option.value] || 0) + count;
            });
        });
        const values = {};
        featureOptions.forEach((option) => {
            values[option.value] = counts[option.value] ? sums[option.value] / counts[option.value] : null;
        });
        return {
            values,
            count: Math.max(0, ...Object.values(counts)),
        };
    };
    const renderHistogramStudentList = (selectedUids = null) => {
        if (!histogramStudentList) return;
        histogramStudentList.querySelectorAll('input[name="students[]"]').forEach((input) => {
            histogramStudentCheckboxState.set(String(input.value), input.checked);
        });
        const result = selectedUids instanceof Set
            ? [...selectedUids].sort(compareIds)
            : [];
        const activeLookup = new Set(result);
        [...histogramStudentCheckboxState.keys()].forEach((uid) => {
            if (!activeLookup.has(uid)) histogramStudentCheckboxState.delete(uid);
        });
        const histogramModeActive = studentListDisplayMode?.value === 'histogram';
        if (result.length === 0) {
            histogramStudentList.innerHTML = '<li class="histogram-student-list-empty">UIDを含む縦棒をクリックすると、ここにグループ候補が表示されます。</li>';
        } else {
            const scopeTitle = histogramAverageScope?.value === 'all'
                ? '学習者ごとの全解答問題における特徴量平均'
                : '学習者ごとの選択した問題における特徴量平均';
            histogramStudentList.innerHTML = result.map((uid) => {
                const name = histogramStudentDirectory.get(uid) || '名前未登録';
                const checked = histogramStudentCheckboxState.has(uid) ? histogramStudentCheckboxState.get(uid) : true;
                histogramStudentCheckboxState.set(uid, checked);
                const average = getHistogramFeatureAverages(uid);
                return `
                    <li class="student-item histogram-student-item" data-uid="${escapeHtml(uid)}">
                        <label class="student-choice student-result-choice click-tooltip-choice">
                            <input type="checkbox" name="students[]" value="${escapeHtml(uid)}"${checked ? ' checked' : ''}${histogramModeActive ? '' : ' disabled'}>
                            <span class="student-result-identity">
                                <strong>UID: ${escapeHtml(uid)}</strong>
                                <span>名前: ${escapeHtml(name)}</span>
                            </span>
                            <button type="button" class="student-info-button" aria-label="学習者ごとの特徴量平均を表示">ⓘ</button>
                            ${createFeatureTooltipHtml(scopeTitle, average.values, average.count)}
                        </label>
                    </li>
                `;
            }).join('');
        }
        if (histogramStudentListSummary) {
            histogramStudentListSummary.textContent = `選択中のUID縦棒: ${histogramBarConditions.uid.size}本 / 対象UID: ${result.length}人`;
        }
    };
    histogramStudentList?.addEventListener('change', (event) => {
        if (event.target.matches('input[name="students[]"]')) {
            histogramStudentCheckboxState.set(String(event.target.value), event.target.checked);
        }
    });
    histogramAverageScope?.addEventListener('change', () => {
        const current = barLogicControllers.uid?.lastResult || new Set();
        renderHistogramStudentList(current);
    });

    const setupBarLogic = (entity, onResult) => {
        const builder = document.getElementById(`histogram-${entity}-bar-logic-builder`);
        const summary = document.getElementById(`histogram-${entity}-bar-logic-summary`);
        const insertPosition = document.getElementById(`histogram-${entity}-bar-logic-insert-position`);
        const panel = document.getElementById(`histogram-${entity}-bar-logic-panel`);
        if (!builder || !summary || !insertPosition || !panel) return null;
        const available = histogramBarConditions[entity];
        const universe = entity === 'uid' ? allHistogramUids : allHistogramWids;
        const controller = { lastResult: new Set() };
        const tokens = () => Array.from(builder.querySelectorAll('.logic-filter-token'));
        const conditionOptions = (selected = '') => {
            if (available.size === 0) return '<option value="">選択中の縦棒がありません</option>';
            return [...available.values()].map((condition) => (
                `<option value="${escapeHtml(condition.id)}"${condition.id === selected ? ' selected' : ''}>${escapeHtml(condition.label)}</option>`
            )).join('');
        };
        const kindOptions = (selected) => [
            ['condition', '縦棒'],
            ['and', 'AND'],
            ['or', 'OR'],
            ['not', 'NOT'],
            ['open', '('],
            ['close', ')'],
        ].map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${label}</option>`).join('');
        const renderToken = (token, kind, conditionId = '') => {
            token.className = 'logic-filter-token';
            token.dataset.kind = kind;
            const kindSelect = `<select class="logic-filter-kind">${kindOptions(kind)}</select>`;
            if (kind === 'condition') {
                token.innerHTML = `${kindSelect}<select class="logic-filter-target">${conditionOptions(conditionId)}</select><button type="button" class="logic-filter-remove" aria-label="部品を削除">x</button>`;
            } else if (kind === 'open' || kind === 'close') {
                token.innerHTML = `${kindSelect}<span>${kind === 'open' ? '(' : ')'}</span><button type="button" class="logic-filter-remove" aria-label="部品を削除">x</button>`;
            } else {
                token.innerHTML = `${kindSelect}<span>${kind.toUpperCase()}</span><button type="button" class="logic-filter-remove" aria-label="部品を削除">x</button>`;
            }
        };
        const tokenLabel = (token) => {
            if (token.dataset.kind === 'condition') {
                const select = token.querySelector('.logic-filter-target');
                return select?.options[select.selectedIndex]?.textContent || '縦棒';
            }
            if (token.dataset.kind === 'open') return '(';
            if (token.dataset.kind === 'close') return ')';
            return token.dataset.kind.toUpperCase();
        };
        const updateInsert = () => {
            const current = insertPosition.value;
            insertPosition.innerHTML = ['<option value="">末尾に追加</option>']
                .concat(tokens().map((token, index) => `<option value="${index}">${index + 1}個目の前 (${escapeHtml(tokenLabel(token))})</option>`))
                .join('');
            if (current !== '' && Number(current) < tokens().length) insertPosition.value = current;
        };
        const addToken = (kind, conditionId = '', evaluate = true) => {
            const token = document.createElement('span');
            renderToken(token, kind, conditionId);
            const currentTokens = tokens();
            const position = insertPosition.value === '' ? currentTokens.length : Number(insertPosition.value);
            if (Number.isInteger(position) && position >= 0 && position < currentTokens.length) {
                builder.insertBefore(token, currentTokens[position]);
            } else {
                builder.appendChild(token);
            }
            updateInsert();
            if (evaluate) evaluateAndApply();
        };
        const expressionTokens = () => tokens().map((token) => {
            const kind = token.dataset.kind;
            if (kind === 'condition') {
                return { type: 'condition', conditionId: token.querySelector('.logic-filter-target')?.value || '' };
            }
            if (kind === 'open' || kind === 'close') {
                return { type: 'paren', paren: kind === 'open' ? '(' : ')' };
            }
            return { type: 'operator', operator: kind.toUpperCase() };
        });
        const implicitOrResult = () => {
            let result = new Set();
            available.forEach((condition) => {
                result = setUnion(result, condition.members);
            });
            return result;
        };
        const evaluateAndApply = () => {
            try {
                const list = expressionTokens();
                const result = list.length === 0
                    ? implicitOrResult()
                    : evaluateSetExpression(list, (token) => available.get(token.conditionId)?.members || null, universe);
                controller.lastResult = result;
                summary.textContent = available.size === 0
                    ? `${entity.toUpperCase()}の縦棒は選択されていません。`
                    : `${available.size}本の縦棒から${result.size}件を選択しています。`;
                summary.classList.remove('is-error');
                onResult(result, available.size);
                return true;
            } catch (error) {
                summary.textContent = error.message || '縦棒の論理式を確認してください。';
                summary.classList.add('is-error');
                return false;
            }
        };
        const rebuildDefault = () => {
            builder.innerHTML = '';
            [...available.keys()].forEach((conditionId, index) => {
                if (index > 0) addToken('or', '', false);
                addToken('condition', conditionId, false);
            });
            updateInsert();
            evaluateAndApply();
        };
        controller.selectionAdded = (conditionId) => {
            if (tokens().length === 0) {
                addToken('condition', conditionId, false);
            } else if (evaluateAndApply()) {
                addToken('or', '', false);
                addToken('condition', conditionId, false);
            } else {
                rebuildDefault();
                return;
            }
            updateInsert();
            evaluateAndApply();
        };
        controller.selectionRemoved = () => rebuildDefault();
        controller.rebuildDefault = rebuildDefault;

        panel.querySelectorAll(`[data-add-histogram-bar-logic^="${entity}-"]`).forEach((button) => {
            button.addEventListener('click', () => {
                const kind = button.dataset.addHistogramBarLogic.replace(`${entity}-`, '');
                addToken(kind);
            });
        });
        builder.addEventListener('click', (event) => {
            const remove = event.target.closest('.logic-filter-remove');
            if (!remove) return;
            remove.closest('.logic-filter-token')?.remove();
            updateInsert();
            evaluateAndApply();
        });
        builder.addEventListener('change', (event) => {
            const token = event.target.closest('.logic-filter-token');
            if (!token) return;
            if (event.target.classList.contains('logic-filter-kind')) {
                renderToken(token, event.target.value);
            }
            updateInsert();
            evaluateAndApply();
        });
        document.getElementById(`reset-histogram-${entity}-bar-logic`)?.addEventListener('click', rebuildDefault);
        document.getElementById(`clear-histogram-${entity}-bar-logic`)?.addEventListener('click', () => {
            builder.innerHTML = '';
            updateInsert();
            evaluateAndApply();
        });
        updateInsert();
        return controller;
    };

    barLogicControllers.uid = setupBarLogic('uid', (result) => renderHistogramStudentList(result));
    barLogicControllers.wid = setupBarLogic('wid', (result, conditionCount) => {
        if (conditionCount === 0 && !applyingWidBarResult) {
            applyingWidBarResult = true;
            document.querySelectorAll('.histogram-wid-checkbox').forEach((checkbox) => {
                checkbox.checked = false;
            });
            applyingWidBarResult = false;
            markWidFilterPending();
            return;
        }
        applyingWidBarResult = true;
        document.querySelectorAll('.histogram-wid-checkbox').forEach((checkbox) => {
            checkbox.checked = result.has(String(checkbox.value));
        });
        applyingWidBarResult = false;
        markWidFilterPending();
    });

    const clearEntityBarConditions = (entity) => {
        if (histogramBarConditions[entity].size === 0) return;
        histogramBarConditions[entity].clear();
        barLogicControllers[entity]?.rebuildDefault();
        if (entity === 'wid') renderSavedWidBars();
    };
    const clearChartBarConditions = (chartKey, entities = ['uid']) => {
        entities.forEach((entity) => {
            let changed = false;
            histogramBarConditions[entity].forEach((condition, id) => {
                if (condition.chartKey === chartKey) {
                    histogramBarConditions[entity].delete(id);
                    changed = true;
                }
            });
            if (changed) {
                barLogicControllers[entity]?.rebuildDefault();
                if (entity === 'wid') renderSavedWidBars();
            }
        });
    };

    document.getElementById('histogram-select-all-wid-btn')?.addEventListener('click', () => {
        document.querySelectorAll('.histogram-wid-checkbox').forEach((checkbox) => {
            checkbox.checked = true;
        });
        markWidFilterPending();
    });
    document.getElementById('histogram-deselect-all-wid-btn')?.addEventListener('click', () => {
        document.querySelectorAll('.histogram-wid-checkbox').forEach((checkbox) => {
            checkbox.checked = false;
        });
        markWidFilterPending();
    });
    document.getElementById('histogram-wid-checkbox-list')?.addEventListener('change', (event) => {
        if (!event.target.matches('.histogram-wid-checkbox') || applyingWidBarResult) return;
        markWidFilterPending();
    });
    savedWidBarsList?.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('[data-saved-wid-bar-id]');
        if (!deleteButton) return;
        const conditionId = deleteButton.dataset.savedWidBarId;
        if (!histogramBarConditions.wid.delete(conditionId)) return;
        barLogicControllers.wid?.selectionRemoved(conditionId);
        renderSavedWidBars();
        scheduleHistogramRendering(false);
    });
    document.getElementById('clear-saved-histogram-wid-bars')?.addEventListener('click', () => {
        clearEntityBarConditions('wid');
        scheduleHistogramRendering(false);
    });
    document.getElementById('apply-histogram-wid-filter')?.addEventListener('click', () => {
        appliedHistogramWids = getCheckedIds('.histogram-wid-checkbox');
        if (widFilterApplySummary) {
            widFilterApplySummary.textContent = `${appliedHistogramWids.size}件のWIDをヒストグラムと特徴量平均へ反映しました。`;
            widFilterApplySummary.classList.remove('is-pending');
        }
        scheduleHistogramRendering(false);
        renderHistogramStudentList(barLogicControllers.uid?.lastResult || new Set());
    });
    renderSavedWidBars();

    const formatHistogramValue = (value) => {
        const number = Number(value);
        if (!Number.isFinite(number)) return '-';
        const absolute = Math.abs(number);
        const digits = absolute >= 100 ? 0 : absolute >= 10 ? 1 : absolute < 1 ? 4 : 2;
        return new Intl.NumberFormat('ja-JP', { maximumFractionDigits: digits }).format(number);
    };
    const quantile = (sorted, ratio) => {
        if (sorted.length === 0) return 0;
        const position = (sorted.length - 1) * ratio;
        const lower = Math.floor(position);
        const upper = Math.ceil(position);
        if (lower === upper) return sorted[lower];
        return sorted[lower] + ((sorted[upper] - sorted[lower]) * (position - lower));
    };
    const niceStep = (raw) => {
        if (!Number.isFinite(raw) || raw <= 0) return 1;
        const exponent = Math.floor(Math.log10(raw));
        const magnitude = 10 ** exponent;
        const fraction = raw / magnitude;
        const niceFraction = fraction < 1.5 ? 1 : fraction < 2.25 ? 2 : fraction < 3.5 ? 2.5 : fraction < 7.5 ? 5 : 10;
        return niceFraction * magnitude;
    };
    const buildHistogram = (rawPoints, percentage = false) => {
        const points = rawPoints
            .map((point) => ({ id: String(point.id), value: Number(point.value) }))
            .filter((point) => Number.isFinite(point.value))
            .sort((a, b) => a.value - b.value);
        if (points.length === 0) return null;
        const values = points.map((point) => point.value);
        const min = values[0];
        const max = values[values.length - 1];
        if (min === max) {
            return {
                labels: [formatHistogramValue(min)],
                bins: [{ start: min, end: max, members: points }],
                counts: [points.length],
                min,
                max,
                step: 0,
            };
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
            start: lower + (step * index),
            end: lower + (step * (index + 1)),
            members: [],
        }));
        points.forEach((point) => {
            const rawIndex = point.value === upper ? binCount - 1 : Math.floor((point.value - lower) / step);
            const index = Math.max(0, Math.min(binCount - 1, rawIndex));
            bins[index].members.push(point);
        });
        return {
            labels: bins.map((bin) => `${formatHistogramValue(bin.start)}〜${formatHistogramValue(bin.end)}`),
            bins,
            counts: bins.map((bin) => bin.members.length),
            min,
            max,
            step,
        };
    };

    const histogramCharts = { uidFeature: null, widFeature: null, metric: null };
    const destroyChart = (key) => {
        histogramCharts[key]?.destroy();
        histogramCharts[key] = null;
    };
    const aggregateFeaturePoints = (entity, feature, uidScope, widScope) => {
        const selectedUids = uidScope === 'checked' ? getCheckedIds('.histogram-uid-checkbox') : null;
        const selectedWids = widScope === 'checked' ? appliedHistogramWids : null;
        if (selectedUids && selectedUids.size === 0) return { points: [], reason: '選択したUIDがありません。' };
        if (selectedWids && selectedWids.size === 0) return { points: [], reason: '選択したWIDがありません。' };
        const grouped = new Map();
        histogramFeaturePairs.forEach((pair) => {
            const uid = String(pair.uid);
            const wid = String(pair.wid);
            if (selectedUids && !selectedUids.has(uid)) return;
            if (selectedWids && !selectedWids.has(wid)) return;
            const value = toDisplayFeatureValue(feature, pair.features?.[feature]);
            const count = Number(pair.featureCounts?.[feature] || 0);
            if (value === null || !Number.isFinite(value) || count <= 0) return;
            const id = entity === 'uid' ? uid : wid;
            const group = grouped.get(id) || { sum: 0, count: 0 };
            group.sum += value * count;
            group.count += count;
            grouped.set(id, group);
        });
        return {
            points: [...grouped.entries()].filter(([, group]) => group.count > 0).map(([id, group]) => ({ id, value: group.sum / group.count })),
            reason: '対象範囲に特徴量データがありません。',
        };
    };
    const aggregateMetricPoints = (entity, metric, uidScope, widScope) => {
        const selectedUids = uidScope === 'checked' ? getCheckedIds('.histogram-uid-checkbox') : null;
        const selectedWids = widScope === 'checked' ? appliedHistogramWids : null;
        if (selectedUids && selectedUids.size === 0) return { points: [], reason: '選択したUIDがありません。' };
        if (selectedWids && selectedWids.size === 0) return { points: [], reason: '選択したWIDがありません。' };
        const grouped = new Map();
        histogramMetricAttempts.forEach((attempt) => {
            const uid = String(attempt.uid);
            const wid = String(attempt.wid);
            if (selectedUids && !selectedUids.has(uid)) return;
            if (selectedWids && !selectedWids.has(wid)) return;
            const stored = metric === 'accuracy' ? attempt.correctness : attempt.hesitation;
            const raw = Number(stored);
            const valid = metric === 'accuracy' ? raw === 0 || raw === 1 : raw === 2 || raw === 4;
            if (!valid) return;
            const id = entity === 'uid' ? uid : wid;
            const group = grouped.get(id) || { numerator: 0, denominator: 0 };
            group.denominator += 1;
            if ((metric === 'accuracy' && raw === 1) || (metric === 'hesitation' && raw === 2)) group.numerator += 1;
            grouped.set(id, group);
        });
        return {
            points: [...grouped.entries()].map(([id, group]) => ({ id, value: (group.numerator * 100) / group.denominator })),
            reason: metric === 'accuracy' ? '対象範囲に正誤データがありません。' : '対象範囲に迷い推定データがありません。',
        };
    };
    const renderChart = ({
        key,
        canvas,
        summary,
        points,
        title,
        xTitle,
        entity,
        color,
        borderColor,
        signature,
        percentage = false,
        formatValue = formatHistogramValue,
    }) => {
        if (!canvas || !summary) return;
        const histogram = buildHistogram(points, percentage);
        if (!histogram) {
            destroyChart(key);
            summary.textContent = '表示できるデータがありません。';
            summary.classList.add('is-empty');
            return;
        }
        summary.textContent = histogram.step > 0
            ? `対象${entity.toUpperCase()}数: ${points.length} / 実測範囲: ${formatValue(histogram.min)}〜${formatValue(histogram.max)} / 階級幅: ${formatValue(histogram.step)}`
            : `対象${entity.toUpperCase()}数: ${points.length} / すべて同じ値: ${formatValue(histogram.min)}`;
        summary.classList.remove('is-empty');
        if (typeof Chart === 'undefined') {
            summary.textContent += ' / グラフライブラリを読み込めませんでした。';
            return;
        }
        destroyChart(key);
        const conditions = histogram.bins.map((bin, index) => {
            const members = new Set(bin.members.map((member) => String(member.id)));
            const id = JSON.stringify([key, signature, bin.start, bin.end]);
            return {
                id,
                chartKey: key,
                entity,
                members,
                label: `${title} / ${histogram.labels[index]}`,
            };
        });
        const selectedMap = histogramBarConditions[entity];
        const isSelected = (index) => selectedMap.has(conditions[index].id);
        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: histogram.labels,
                datasets: [{
                    label: `${entity.toUpperCase()}数`,
                    data: histogram.counts,
                    backgroundColor: histogram.counts.map((_count, index) => isSelected(index) ? 'rgba(37, 99, 235, 0.88)' : color),
                    borderColor: histogram.counts.map((_count, index) => isSelected(index) ? 'rgba(30, 64, 175, 1)' : borderColor),
                    borderWidth: histogram.counts.map((_count, index) => isSelected(index) ? 3 : 1),
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: true },
                onClick: (_event, elements, activeChart) => {
                    const index = elements?.[0]?.index;
                    if (!Number.isInteger(index) || conditions[index].members.size === 0) return;
                    const condition = conditions[index];
                    if (selectedMap.has(condition.id)) {
                        selectedMap.delete(condition.id);
                        barLogicControllers[entity]?.selectionRemoved(condition.id);
                    } else {
                        selectedMap.set(condition.id, condition);
                        barLogicControllers[entity]?.selectionAdded(condition.id);
                    }
                    if (entity === 'wid') renderSavedWidBars();
                    const dataset = activeChart.data.datasets[0];
                    dataset.backgroundColor = histogram.counts.map((_count, itemIndex) => selectedMap.has(conditions[itemIndex].id) ? 'rgba(37, 99, 235, 0.88)' : color);
                    dataset.borderColor = histogram.counts.map((_count, itemIndex) => selectedMap.has(conditions[itemIndex].id) ? 'rgba(30, 64, 175, 1)' : borderColor);
                    dataset.borderWidth = histogram.counts.map((_count, itemIndex) => selectedMap.has(conditions[itemIndex].id) ? 3 : 1);
                    activeChart.update('none');
                },
                onHover: (event, elements) => {
                    if (event.native?.target) event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                },
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: title, color: '#1f2937', font: { size: 14, weight: '700' } },
                    tooltip: {
                        callbacks: {
                            afterBody: (items) => {
                                const index = items?.[0]?.dataIndex;
                                const ids = histogram.bins[index]?.members?.map((member) => String(member.id)).sort(compareIds) || [];
                                return ids.length <= 12 ? ids.join(', ') : `${ids.slice(0, 12).join(', ')} ほか${ids.length - 12}件`;
                            },
                        },
                    },
                },
                scales: {
                    x: { title: { display: true, text: xTitle }, ticks: { maxRotation: 45, minRotation: 0 } },
                    y: { beginAtZero: true, title: { display: true, text: `${entity.toUpperCase()}数` }, ticks: { precision: 0 } },
                },
            },
        });
        histogramCharts[key] = chart;
    };

    let histogramsInitialized = false;
    let histogramFrame = null;
    const setupHistograms = () => {
        const uidFeatureSelect = document.getElementById('uid-feature-histogram-feature');
        const uidScopeSelect = document.getElementById('uid-feature-histogram-uid-scope');
        const uidWidScopeSelect = document.getElementById('uid-feature-histogram-wid-scope');
        const widFeatureSelect = document.getElementById('wid-feature-histogram-feature');
        const widUidScopeSelect = document.getElementById('wid-feature-histogram-uid-scope');
        const widScopeSelect = document.getElementById('wid-feature-histogram-wid-scope');
        const metricSelect = document.getElementById('metric-histogram-metric');
        const metricEntitySelect = document.getElementById('metric-histogram-entity');
        const metricUidScopeSelect = document.getElementById('metric-histogram-uid-scope');
        const metricWidScopeSelect = document.getElementById('metric-histogram-wid-scope');
        if (!uidFeatureSelect || !widFeatureSelect || !metricSelect || !metricEntitySelect) return;

        if (!histogramsInitialized) {
            const featureHtml = featureOptions.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)} (${escapeHtml(option.value)})</option>`).join('');
            uidFeatureSelect.innerHTML = featureHtml;
            widFeatureSelect.innerHTML = featureHtml;
        }
        const renderAll = () => {
            const uidFeature = uidFeatureSelect.value;
            const uidFeatureResult = aggregateFeaturePoints('uid', uidFeature, uidScopeSelect.value, uidWidScopeSelect.value);
            renderChart({
                key: 'uidFeature',
                canvas: document.getElementById('uid-feature-histogram-chart'),
                summary: document.getElementById('uid-feature-histogram-summary'),
                points: uidFeatureResult.points,
                title: `${featureOptions.find((option) => option.value === uidFeature)?.label || uidFeature}のUID分布`,
                xTitle: `UIDごとの平均値 (${getFeatureMeta(uidFeature).unit || '-'})`,
                entity: 'uid',
                color: 'rgba(20, 184, 166, 0.62)',
                borderColor: 'rgba(15, 118, 110, 1)',
                signature: `${uidFeature}|${uidScopeSelect.value}|${uidWidScopeSelect.value}`,
                formatValue: (value) => `${formatHistogramValue(value)}${getFeatureMeta(uidFeature).unit || ''}`,
            });
            if (uidFeatureResult.points.length === 0) document.getElementById('uid-feature-histogram-summary').textContent = uidFeatureResult.reason;

            const widFeature = widFeatureSelect.value;
            const widFeatureResult = aggregateFeaturePoints('wid', widFeature, widUidScopeSelect.value, widScopeSelect.value);
            renderChart({
                key: 'widFeature',
                canvas: document.getElementById('wid-feature-histogram-chart'),
                summary: document.getElementById('wid-feature-histogram-summary'),
                points: widFeatureResult.points,
                title: `${featureOptions.find((option) => option.value === widFeature)?.label || widFeature}のWID分布`,
                xTitle: `WIDごとの平均値 (${getFeatureMeta(widFeature).unit || '-'})`,
                entity: 'wid',
                color: 'rgba(59, 130, 246, 0.58)',
                borderColor: 'rgba(29, 78, 216, 1)',
                signature: `${widFeature}|${widUidScopeSelect.value}|${widScopeSelect.value}`,
                formatValue: (value) => `${formatHistogramValue(value)}${getFeatureMeta(widFeature).unit || ''}`,
            });
            if (widFeatureResult.points.length === 0) document.getElementById('wid-feature-histogram-summary').textContent = widFeatureResult.reason;

            const metric = metricSelect.value;
            const entity = metricEntitySelect.value;
            const metricResult = aggregateMetricPoints(entity, metric, metricUidScopeSelect.value, metricWidScopeSelect.value);
            const metricLabel = metric === 'accuracy' ? '正答率' : '迷い率';
            renderChart({
                key: 'metric',
                canvas: document.getElementById('metric-histogram-chart'),
                summary: document.getElementById('metric-histogram-summary'),
                points: metricResult.points,
                title: `${entity.toUpperCase()}ごとの${metricLabel}分布`,
                xTitle: `${metricLabel} (%)`,
                entity,
                color: metric === 'accuracy' ? 'rgba(34, 197, 94, 0.58)' : 'rgba(249, 115, 22, 0.58)',
                borderColor: metric === 'accuracy' ? 'rgba(21, 128, 61, 1)' : 'rgba(194, 65, 12, 1)',
                signature: `${metric}|${entity}|${metricUidScopeSelect.value}|${metricWidScopeSelect.value}`,
                percentage: true,
                formatValue: (value) => `${formatHistogramValue(value)}%`,
            });
            if (metricResult.points.length === 0) document.getElementById('metric-histogram-summary').textContent = metricResult.reason;
        };
        scheduleHistogramRendering = (clearSelections = false) => {
            if (!histogramsInitialized) return;
            if (clearSelections) {
                clearChartBarConditions('uidFeature');
                clearChartBarConditions('metric');
            }
            if (histogramFrame !== null) window.cancelAnimationFrame(histogramFrame);
            histogramFrame = window.requestAnimationFrame(() => {
                histogramFrame = null;
                renderAll();
            });
        };
        if (!histogramsInitialized) {
            [
                [uidFeatureSelect, 'uidFeature'],
                [uidScopeSelect, 'uidFeature'],
                [uidWidScopeSelect, 'uidFeature'],
                [widFeatureSelect, 'widFeature'],
                [widUidScopeSelect, 'widFeature'],
                [widScopeSelect, 'widFeature'],
                [metricSelect, 'metric'],
                [metricEntitySelect, 'metric'],
                [metricUidScopeSelect, 'metric'],
                [metricWidScopeSelect, 'metric'],
            ].forEach(([element, key]) => element?.addEventListener('change', () => {
                if (key !== 'widFeature') clearChartBarConditions(key);
                renderAll();
            }));
            histogramsInitialized = true;
        }
        renderAll();
    };
    initializeHistograms = () => {
        if (!histogramsInitialized) {
            setupHistograms();
        } else {
            Object.values(histogramCharts).forEach((chart) => chart?.resize());
        }
    };

    const syncStudentListDisplayMode = () => {
        const histogramActive = studentListDisplayMode?.value === 'histogram';
        if (studentList) {
            studentList.hidden = histogramActive;
            studentList.querySelectorAll('input[name="students[]"]').forEach((input) => {
                input.disabled = histogramActive;
            });
        }
        if (histogramStudentList) {
            histogramStudentList.hidden = !histogramActive;
            histogramStudentList.querySelectorAll('input[name="students[]"]').forEach((input) => {
                input.disabled = !histogramActive;
            });
        }
        document.querySelectorAll('[data-average-scope-mode]').forEach((control) => {
            control.hidden = control.dataset.averageScopeMode !== (histogramActive ? 'histogram' : 'filter');
        });
        if (histogramStudentListSummary) histogramStudentListSummary.hidden = !histogramActive;
    };
    studentListDisplayMode?.addEventListener('change', syncStudentListDisplayMode);
    syncStudentListDisplayMode();

    let floatingTooltip = null;
    let activeTooltipChoice = null;
    const closeFloatingTooltip = () => {
        floatingTooltip?.remove();
        floatingTooltip = null;
        activeTooltipChoice?.classList.remove('tooltip-floating-active');
        activeTooltipChoice = null;
    };
    const positionFloatingTooltip = () => {
        if (!floatingTooltip || !activeTooltipChoice) return;
        const sourceRect = activeTooltipChoice.getBoundingClientRect();
        const tooltipRect = floatingTooltip.getBoundingClientRect();
        const margin = 12;
        let left = Math.max(margin, Math.min((window.innerWidth - tooltipRect.width) / 2, window.innerWidth - tooltipRect.width - margin));
        let top = sourceRect.bottom + 8;
        if (top + tooltipRect.height > window.innerHeight - margin) {
            top = Math.max(margin, sourceRect.top - tooltipRect.height - 8);
        }
        floatingTooltip.style.left = `${left}px`;
        floatingTooltip.style.top = `${top}px`;
    };
    document.addEventListener('click', (event) => {
        const button = event.target.closest('.student-info-button');
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();
        const choice = button.closest('.student-choice');
        const tooltip = choice?.querySelector('.student-feature-popup');
        if (!choice || !tooltip) return;
        closeFloatingTooltip();
        activeTooltipChoice = choice;
        activeTooltipChoice.classList.add('tooltip-floating-active');
        floatingTooltip = document.createElement('div');
        floatingTooltip.className = 'student-floating-tooltip student-floating-tooltip-click';
        floatingTooltip.innerHTML = `<button type="button" class="student-tooltip-close" aria-label="閉じる">×</button>${tooltip.innerHTML}`;
        floatingTooltip.querySelector('.student-tooltip-close')?.addEventListener('click', closeFloatingTooltip);
        document.body.appendChild(floatingTooltip);
        positionFloatingTooltip();
    });
    window.addEventListener('scroll', positionFloatingTooltip, true);
    window.addEventListener('resize', positionFloatingTooltip);

    renderHistogramStudentList(new Set());
    scheduleFilterSearch(0);
});

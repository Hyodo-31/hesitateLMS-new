document.addEventListener('DOMContentLoaded', () => {
    const searchButton = document.getElementById('search-button');
    const studentList = document.getElementById('student-list');
    let floatingTooltip = null;
    let activeChoice = null;
    const featureFilterBuilder = document.getElementById('feature-filter-builder');
    const featureFilterExpressionInput = document.getElementById('feature-filter-expression');
    const featureFilterSettings = document.getElementById('feature-filter-settings');
    const featureFilterSummary = document.getElementById('feature-filter-summary');
    const featureFilterInsertPosition = document.getElementById('feature-filter-insert-position');
    const addFeatureFilterConditionButton = document.getElementById('add-feature-filter-condition');
    const addFeatureFilterAndButton = document.getElementById('add-feature-filter-and');
    const addFeatureFilterOrButton = document.getElementById('add-feature-filter-or');
    const addFeatureFilterNotButton = document.getElementById('add-feature-filter-not');
    const addFeatureFilterOpenButton = document.getElementById('add-feature-filter-open');
    const addFeatureFilterCloseButton = document.getElementById('add-feature-filter-close');
    const resetFeatureFilterExpressionButton = document.getElementById('reset-feature-filter-expression');
    const trimFeatureFilterExpressionButton = document.getElementById('trim-feature-filter-expression');
    const clearFeatureFilterExpressionButton = document.getElementById('clear-feature-filter-expression');
    const featureFilterOptions = Object.entries(window.studentGroupFeatureColumns || {}).map(([value, label]) => ({ value, label }));
    const featureFilterPlaceholders = window.studentGroupFeatureFilterPlaceholders || { min: '最小値', max: '最大値' };
    const featureFilterSettingValues = {};

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const getFeatureFilterTokens = () => {
        if (!featureFilterBuilder) {
            return [];
        }

        return Array.from(featureFilterBuilder.querySelectorAll('.feature-filter-token'));
    };

    const readFeatureFilterSettingValues = () => {
        if (!featureFilterSettings) {
            return;
        }

        featureFilterSettings.querySelectorAll('.feature-filter-setting-row').forEach((row) => {
            const feature = row.dataset.feature;
            if (!feature) {
                return;
            }

            featureFilterSettingValues[feature] = {
                min: row.querySelector('.feature-filter-min')?.value ?? '',
                max: row.querySelector('.feature-filter-max')?.value ?? '',
            };
        });
    };

    const getFeatureFilterKindSelectHtml = (selectedKind) => {
        const options = [
            { value: 'condition', label: '特徴量' },
            { value: 'and', label: 'AND' },
            { value: 'or', label: 'OR' },
            { value: 'not', label: 'NOT' },
            { value: 'open', label: '(' },
            { value: 'close', label: ')' },
        ];

        return options.map((option) => {
            const selected = option.value === selectedKind ? ' selected' : '';
            return `<option value="${option.value}"${selected}>${option.label}</option>`;
        }).join('');
    };

    const getFeatureFilterKindFromToken = (type, value = '') => {
        if (type === 'condition') {
            return 'condition';
        }
        if (type === 'operator') {
            return String(value).toLowerCase();
        }
        return value === '(' ? 'open' : 'close';
    };

    const getFeatureFilterTokenSpecFromKind = (kind) => {
        if (kind === 'condition') {
            return { type: 'condition', value: '' };
        }
        if (kind === 'and') {
            return { type: 'operator', value: 'AND' };
        }
        if (kind === 'or') {
            return { type: 'operator', value: 'OR' };
        }
        if (kind === 'not') {
            return { type: 'operator', value: 'NOT' };
        }
        return { type: 'paren', value: kind === 'open' ? '(' : ')' };
    };

    const createFeatureFilterTargetOptionsHtml = (selectedValue = '') => {
        if (featureFilterOptions.length === 0) {
            return '<option value="">特徴量がありません</option>';
        }

        return featureFilterOptions.map((option) => {
            const selected = option.value === String(selectedValue) ? ' selected' : '';
            return `<option value="${escapeHtml(option.value)}"${selected}>${escapeHtml(option.label)} (${escapeHtml(option.value)})</option>`;
        }).join('');
    };

    const getFeatureFilterTokenDisplayLabel = (token) => {
        const type = token.dataset.tokenType;
        if (type === 'condition') {
            const select = token.querySelector('.feature-filter-target');
            return select?.options[select.selectedIndex]?.textContent || '特徴量';
        }
        if (type === 'operator') {
            return token.dataset.operator || '演算子';
        }
        return token.dataset.paren || '括弧';
    };

    const updateFeatureFilterInsertOptions = () => {
        if (!featureFilterInsertPosition) {
            return;
        }

        const tokens = getFeatureFilterTokens();
        const current = featureFilterInsertPosition.value;
        const options = ['<option value="">末尾に追加</option>'];
        tokens.forEach((token, index) => {
            const label = getFeatureFilterTokenDisplayLabel(token);
            options.push(`<option value="${index}">${index + 1}個目の前 (${escapeHtml(label)})</option>`);
        });
        featureFilterInsertPosition.innerHTML = options.join('');
        if (current !== '' && Number(current) < tokens.length) {
            featureFilterInsertPosition.value = current;
        }
    };

    const insertFeatureFilterToken = (token) => {
        const tokens = getFeatureFilterTokens();
        const position = featureFilterInsertPosition?.value === '' ? tokens.length : Number(featureFilterInsertPosition?.value);
        if (!Number.isInteger(position) || position >= tokens.length) {
            featureFilterBuilder.appendChild(token);
            return null;
        }

        const safePosition = Math.max(position, 0);
        featureFilterBuilder.insertBefore(token, tokens[safePosition]);
        return safePosition;
    };

    const renderFeatureFilterSettings = () => {
        if (!featureFilterSettings) {
            return;
        }

        readFeatureFilterSettingValues();
        const selectedFeatures = [];
        getFeatureFilterTokens().forEach((token) => {
            if (token.dataset.tokenType !== 'condition') {
                return;
            }

            const feature = token.querySelector('.feature-filter-target')?.value;
            if (feature && !selectedFeatures.includes(feature)) {
                selectedFeatures.push(feature);
            }
        });

        if (selectedFeatures.length === 0) {
            featureFilterSettings.innerHTML = '<p class="feature-filter-empty">論理式に特徴量を追加すると、ここに最小値・最大値の設定欄が表示されます。</p>';
            return;
        }

        featureFilterSettings.innerHTML = selectedFeatures.map((feature) => {
            const option = featureFilterOptions.find((item) => item.value === feature);
            const label = option?.label || feature;
            const values = featureFilterSettingValues[feature] || { min: '', max: '' };
            return `
                <div class="feature-filter-setting-row" data-feature="${escapeHtml(feature)}">
                    <div>
                        <div class="feature-filter-setting-name">${escapeHtml(label)}</div>
                        <div class="feature-filter-setting-key">${escapeHtml(feature)}</div>
                        <input type="hidden" name="feature_filters[${escapeHtml(feature)}][enabled]" value="1">
                    </div>
                    <label>
                        <span>${escapeHtml(featureFilterPlaceholders.min)}</span>
                        <input class="feature-filter-min" type="number" step="any" name="feature_filters[${escapeHtml(feature)}][min]" value="${escapeHtml(values.min)}" placeholder="${escapeHtml(featureFilterPlaceholders.min)}">
                    </label>
                    <label>
                        <span>${escapeHtml(featureFilterPlaceholders.max)}</span>
                        <input class="feature-filter-max" type="number" step="any" name="feature_filters[${escapeHtml(feature)}][max]" value="${escapeHtml(values.max)}" placeholder="${escapeHtml(featureFilterPlaceholders.max)}">
                    </label>
                </div>
            `;
        }).join('');
    };

    const getFeatureFilterExpressionTokens = () => getFeatureFilterTokens().map((token) => {
        const type = token.dataset.tokenType;
        if (type === 'condition') {
            return {
                type,
                feature: token.querySelector('.feature-filter-target')?.value || '',
            };
        }
        if (type === 'operator') {
            return { type, operator: token.dataset.operator };
        }
        return { type: 'paren', paren: token.dataset.paren };
    });

    const validateFeatureFilterExpression = (tokens) => {
        if (tokens.length === 0) {
            return;
        }

        let expectsOperand = true;
        let depth = 0;
        tokens.forEach((token) => {
            if (token.type === 'condition') {
                if (!expectsOperand) {
                    throw new Error('条件の間には AND または OR を入れてください。');
                }
                if (!token.feature) {
                    throw new Error('特徴量が選択されていない条件があります。');
                }
                expectsOperand = false;
                return;
            }

            if (token.type === 'operator') {
                if (token.operator === 'NOT') {
                    if (!expectsOperand) {
                        throw new Error('NOT の前には AND または OR を入れてください。');
                    }
                    return;
                }
                if (expectsOperand) {
                    throw new Error('AND または OR の前に条件を置いてください。');
                }
                expectsOperand = true;
                return;
            }

            if (token.paren === '(') {
                if (!expectsOperand) {
                    throw new Error('括弧の前には AND または OR を入れてください。');
                }
                depth++;
                return;
            }

            if (depth === 0) {
                throw new Error('閉じ括弧が多すぎます。');
            }
            if (expectsOperand) {
                throw new Error('括弧の中に条件を入れてください。');
            }
            depth--;
            expectsOperand = false;
        });

        if (depth > 0) {
            throw new Error('閉じていない括弧があります。');
        }
        if (expectsOperand) {
            throw new Error('式の最後は条件または閉じ括弧にしてください。');
        }
    };

    const syncFeatureFilterExpressionInput = (showError = false) => {
        if (!featureFilterExpressionInput) {
            return true;
        }

        const tokens = getFeatureFilterExpressionTokens();
        try {
            validateFeatureFilterExpression(tokens);
        } catch (error) {
            if (featureFilterSummary) {
                featureFilterSummary.textContent = error.message || '特徴量の論理式が正しくありません。';
                featureFilterSummary.classList.add('is-error');
            }
            if (showError) {
                alert(error.message || '特徴量の論理式が正しくありません。');
            }
            return false;
        }

        readFeatureFilterSettingValues();
        featureFilterExpressionInput.value = JSON.stringify(tokens);
        if (featureFilterSummary) {
            featureFilterSummary.textContent = tokens.length === 0
                ? '特徴量条件は未設定です。'
                : `${tokens.filter((token) => token.type === 'condition').length}個の特徴量条件を検索に使用します。`;
            featureFilterSummary.classList.remove('is-error');
        }
        return true;
    };

    const createFeatureFilterToken = (type, value = '') => {
        if (!featureFilterBuilder) {
            return null;
        }

        const token = document.createElement('div');
        token.className = 'feature-filter-token';
        token.dataset.tokenType = type;
        const kind = getFeatureFilterKindFromToken(type, value);
        const kindSelect = `<select class="feature-filter-token-kind" aria-label="部品の種類">${getFeatureFilterKindSelectHtml(kind)}</select>`;

        if (type === 'condition') {
            const selectedFeature = value || featureFilterOptions[0]?.value || '';
            token.classList.add('feature-filter-token-condition');
            token.innerHTML = `
                ${kindSelect}
                <select class="feature-filter-target" aria-label="絞り込み特徴量">
                    ${createFeatureFilterTargetOptionsHtml(selectedFeature)}
                </select>
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">×</button>
            `;
        } else if (type === 'operator') {
            token.classList.add('feature-filter-token-operator');
            token.classList.toggle('feature-filter-token-not', value === 'NOT');
            token.dataset.operator = value;
            token.innerHTML = `
                ${kindSelect}
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">×</button>
            `;
        } else {
            token.classList.add('feature-filter-token-paren');
            token.dataset.paren = value;
            token.innerHTML = `
                ${kindSelect}
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">×</button>
            `;
        }

        const insertedIndex = insertFeatureFilterToken(token);
        updateFeatureFilterInsertOptions();
        if (insertedIndex !== null && featureFilterInsertPosition) {
            featureFilterInsertPosition.value = String(insertedIndex + 1);
        }
        renderFeatureFilterSettings();
        syncFeatureFilterExpressionInput(false);
        return token;
    };

    const replaceFeatureFilterToken = (token, nextKind) => {
        const { type, value } = getFeatureFilterTokenSpecFromKind(nextKind);
        token.className = 'feature-filter-token';
        token.dataset.tokenType = type;
        delete token.dataset.operator;
        delete token.dataset.paren;

        const kindSelect = `<select class="feature-filter-token-kind" aria-label="部品の種類">${getFeatureFilterKindSelectHtml(nextKind)}</select>`;
        if (type === 'condition') {
            token.classList.add('feature-filter-token-condition');
            token.innerHTML = `
                ${kindSelect}
                <select class="feature-filter-target" aria-label="絞り込み特徴量">
                    ${createFeatureFilterTargetOptionsHtml()}
                </select>
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">×</button>
            `;
        } else if (type === 'operator') {
            token.classList.add('feature-filter-token-operator');
            token.classList.toggle('feature-filter-token-not', value === 'NOT');
            token.dataset.operator = value;
            token.innerHTML = `
                ${kindSelect}
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">×</button>
            `;
        } else {
            token.classList.add('feature-filter-token-paren');
            token.dataset.paren = value;
            token.innerHTML = `
                ${kindSelect}
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">×</button>
            `;
        }

        renderFeatureFilterSettings();
        updateFeatureFilterInsertOptions();
        syncFeatureFilterExpressionInput(false);
    };

    const clearFeatureFilterExpression = (message = '特徴量条件は未設定です。') => {
        if (!featureFilterBuilder) {
            return;
        }

        featureFilterBuilder.innerHTML = '';
        updateFeatureFilterInsertOptions();
        renderFeatureFilterSettings();
        syncFeatureFilterExpressionInput(false);
        if (featureFilterSummary) {
            featureFilterSummary.textContent = message;
            featureFilterSummary.classList.remove('is-error');
        }
    };

    const trimFeatureFilterExpressionFromPosition = () => {
        const tokens = getFeatureFilterTokens();
        if (tokens.length === 0) {
            if (featureFilterSummary) {
                featureFilterSummary.textContent = '削除する部品がありません。';
                featureFilterSummary.classList.remove('is-error');
            }
            return;
        }

        if (!featureFilterInsertPosition || featureFilterInsertPosition.value === '') {
            if (featureFilterSummary) {
                featureFilterSummary.textContent = '削除を開始する位置を「追加位置」から選んでください。';
                featureFilterSummary.classList.add('is-error');
            }
            return;
        }

        const startIndex = Number(featureFilterInsertPosition.value);
        tokens.forEach((token, index) => {
            if (index >= startIndex) {
                token.remove();
            }
        });
        updateFeatureFilterInsertOptions();
        renderFeatureFilterSettings();
        syncFeatureFilterExpressionInput(false);
        if (featureFilterSummary) {
            featureFilterSummary.textContent = `${startIndex + 1}個目以降の部品を削除しました。`;
            featureFilterSummary.classList.remove('is-error');
        }
    };

    if (featureFilterBuilder) {
        updateFeatureFilterInsertOptions();
        renderFeatureFilterSettings();
        syncFeatureFilterExpressionInput(false);

        addFeatureFilterConditionButton?.addEventListener('click', () => createFeatureFilterToken('condition'));
        addFeatureFilterAndButton?.addEventListener('click', () => createFeatureFilterToken('operator', 'AND'));
        addFeatureFilterOrButton?.addEventListener('click', () => createFeatureFilterToken('operator', 'OR'));
        addFeatureFilterNotButton?.addEventListener('click', () => createFeatureFilterToken('operator', 'NOT'));
        addFeatureFilterOpenButton?.addEventListener('click', () => createFeatureFilterToken('paren', '('));
        addFeatureFilterCloseButton?.addEventListener('click', () => createFeatureFilterToken('paren', ')'));
        resetFeatureFilterExpressionButton?.addEventListener('click', () => clearFeatureFilterExpression());
        clearFeatureFilterExpressionButton?.addEventListener('click', () => clearFeatureFilterExpression('論理式を空にしました。検索時は特徴量条件を使用しません。'));
        trimFeatureFilterExpressionButton?.addEventListener('click', trimFeatureFilterExpressionFromPosition);

        featureFilterBuilder.addEventListener('change', (event) => {
            const token = event.target.closest('.feature-filter-token');
            if (!token) {
                return;
            }
            if (event.target.classList.contains('feature-filter-token-kind')) {
                replaceFeatureFilterToken(token, event.target.value);
                return;
            }
            renderFeatureFilterSettings();
            updateFeatureFilterInsertOptions();
            syncFeatureFilterExpressionInput(false);
        });

        featureFilterBuilder.addEventListener('click', (event) => {
            if (!event.target.classList.contains('feature-filter-token-remove')) {
                return;
            }
            event.target.closest('.feature-filter-token').remove();
            renderFeatureFilterSettings();
            updateFeatureFilterInsertOptions();
            syncFeatureFilterExpressionInput(false);
        });

        featureFilterSettings?.addEventListener('input', () => {
            readFeatureFilterSettingValues();
            syncFeatureFilterExpressionInput(false);
        });
    }

    const removeFloatingTooltip = () => {
        if (floatingTooltip) {
            floatingTooltip.remove();
            floatingTooltip = null;
        }
        if (activeChoice) {
            activeChoice.classList.remove('tooltip-floating-active');
            activeChoice = null;
        }
    };

    const positionFloatingTooltip = () => {
        if (!floatingTooltip || !activeChoice) {
            return;
        }

        const rect = activeChoice.getBoundingClientRect();
        const margin = 12;
        floatingTooltip.style.left = `${margin}px`;
        floatingTooltip.style.top = `${margin}px`;

        const tooltipRect = floatingTooltip.getBoundingClientRect();
        const centerOffset = Math.min(80, window.innerWidth * 0.08);
        let left = ((window.innerWidth - tooltipRect.width) / 2) + centerOffset;
        let top = rect.bottom + 8;

        if (top + tooltipRect.height > window.innerHeight - margin) {
            top = Math.max(margin, rect.top - tooltipRect.height - 8);
        }

        left = Math.max(margin, Math.min(left, window.innerWidth - tooltipRect.width - margin));
        floatingTooltip.style.left = `${left}px`;
        floatingTooltip.style.top = `${top}px`;
    };

    document.addEventListener('mouseover', (event) => {
        const choice = event.target.closest('.student-choice');
        if (!choice || choice === activeChoice) {
            return;
        }

        const tooltip = choice.querySelector('.student-feature-popup, .student-tooltip');
        if (!tooltip) {
            return;
        }

        removeFloatingTooltip();
        activeChoice = choice;
        activeChoice.classList.add('tooltip-floating-active');
        floatingTooltip = document.createElement('div');
        floatingTooltip.className = 'student-floating-tooltip';
        floatingTooltip.innerHTML = tooltip.innerHTML;
        floatingTooltip.addEventListener('mouseleave', removeFloatingTooltip);
        document.body.appendChild(floatingTooltip);
        positionFloatingTooltip();
    });

    document.addEventListener('mouseout', (event) => {
        if (!activeChoice || !event.target.closest('.student-choice')) {
            return;
        }

        if (floatingTooltip && floatingTooltip.contains(event.relatedTarget)) {
            return;
        }

        if (!activeChoice.contains(event.relatedTarget)) {
            removeFloatingTooltip();
        }
    });

    document.addEventListener('focusin', (event) => {
        const choice = event.target.closest('.student-choice');
        if (!choice) {
            return;
        }

        const tooltip = choice.querySelector('.student-feature-popup, .student-tooltip');
        if (!tooltip) {
            return;
        }

        removeFloatingTooltip();
        activeChoice = choice;
        activeChoice.classList.add('tooltip-floating-active');
        floatingTooltip = document.createElement('div');
        floatingTooltip.className = 'student-floating-tooltip';
        floatingTooltip.innerHTML = tooltip.innerHTML;
        floatingTooltip.addEventListener('mouseleave', removeFloatingTooltip);
        document.body.appendChild(floatingTooltip);
        positionFloatingTooltip();
    });

    document.addEventListener('focusout', (event) => {
        if (activeChoice && activeChoice.contains(event.target)) {
            removeFloatingTooltip();
        }
    });

    window.addEventListener('scroll', positionFloatingTooltip, true);
    window.addEventListener('resize', positionFloatingTooltip);

    searchButton.addEventListener('click', () => {
        if (!syncFeatureFilterExpressionInput(true)) {
            return;
        }

        // フォームデータを収集
        const formData = new FormData(document.getElementById('search-form'));
        // FormDataの内容を確認
        for (const [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }
        if(!formData.get(('accuracy_min'))){
            formData.set('accuracy_min', 0);
        }
        if(!formData.get(('accuracy_max'))){
            formData.set('accuracy_max', 100);
        }
        if(!formData.get(('hesitation_rate_min'))){
            formData.set('hesitation_rate_min', 0);
        }
        if(!formData.get(('hesitation_rate_max'))){
            formData.set('hesitation_rate_max', 100);
        }
        if(!formData.get(('total_answers_min'))){
            formData.set('total_answers_min', 0);
        }
        if(!formData.get(('total_answers_max'))){
            formData.set('total_answers_max', 99999999);
            
        }
        // FormDataの内容を確認
        for (const [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }

        // サーバーに検索条件を送信
        fetch('search-students.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.text())
        .then(data => {
            // リストを更新
            
            studentList.innerHTML = data;
        })
        .catch(error => console.error('エラー:', error));
    });
});

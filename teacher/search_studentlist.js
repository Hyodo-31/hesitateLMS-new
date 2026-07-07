document.addEventListener('DOMContentLoaded', () => {
    const searchButton = document.getElementById('search-button');
    const studentList = document.getElementById('student-list');
    const showStudentFeatureAveragesButton = document.getElementById('show-student-feature-averages');
    const studentFeatureAverageList = document.getElementById('student-feature-average-list');
    const featureGlobalAverageSelect = document.getElementById('feature-global-average-select');
    const featureGlobalAverageValue = document.getElementById('feature-global-average-value');
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
    const featureGlobalAverages = window.studentGroupFeatureGlobalAverages || {};
    const uidLogicFilterGroups = window.studentGroupLogicFilterGroups || [];
    const uidLogicFilterStudentsByGroup = window.studentGroupLogicFilterStudentsByGroup || {};
    const featureFilterPlaceholders = window.studentGroupFeatureFilterPlaceholders || { min: '最小値', max: '最大値' };
    const featureFilterSettingValues = {};

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const formatFeatureValue = (value) => {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
            return '-';
        }
        return Number(value).toFixed(2);
    };

    const createFeatureTooltipHtml = (title, values, count) => {
        const rows = featureFilterOptions.map((option) => `
            <span class="feature-tooltip-label">${escapeHtml(option.label)}</span>
            <span class="feature-tooltip-value">${escapeHtml(formatFeatureValue(values[option.value]))}</span>
        `).join('');

        return `
            <span class="student-feature-popup" role="tooltip" hidden>
                <span class="feature-tooltip-title">${escapeHtml(title)} (${count}件)</span>
                <span class="feature-tooltip-grid">${rows}</span>
            </span>
        `;
    };

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
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">x</button>
            `;
        } else if (type === 'operator') {
            token.classList.add('feature-filter-token-operator');
            token.classList.toggle('feature-filter-token-not', value === 'NOT');
            token.dataset.operator = value;
            token.innerHTML = `
                ${kindSelect}
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">x</button>
            `;
        } else {
            token.classList.add('feature-filter-token-paren');
            token.dataset.paren = value;
            token.innerHTML = `
                ${kindSelect}
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">x</button>
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
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">x</button>
            `;
        } else if (type === 'operator') {
            token.classList.add('feature-filter-token-operator');
            token.classList.toggle('feature-filter-token-not', value === 'NOT');
            token.dataset.operator = value;
            token.innerHTML = `
                ${kindSelect}
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">x</button>
            `;
        } else {
            token.classList.add('feature-filter-token-paren');
            token.dataset.paren = value;
            token.innerHTML = `
                ${kindSelect}
                <button type="button" class="feature-filter-token-remove" aria-label="部品を削除">x</button>
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

    const renderFeatureGlobalAverage = () => {
        if (!featureGlobalAverageSelect || !featureGlobalAverageValue) {
            return;
        }

        const feature = featureGlobalAverageSelect.value;
        if (!feature) {
            featureGlobalAverageValue.textContent = '特徴量を選択してください。';
            return;
        }

        const option = featureFilterOptions.find((item) => item.value === feature);
        featureGlobalAverageValue.textContent = `${option?.label || feature}: ${formatFeatureValue(featureGlobalAverages[feature])}`;
    };

    if (featureGlobalAverageSelect) {
        featureGlobalAverageSelect.innerHTML = [
            '<option value="">特徴量を選択</option>',
            ...featureFilterOptions.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)} (${escapeHtml(option.value)})</option>`),
        ].join('');
        featureGlobalAverageSelect.addEventListener('change', renderFeatureGlobalAverage);
        renderFeatureGlobalAverage();
    }

    const setupUidLogicFilter = () => {
        const panel = document.getElementById('uid-logic-filter-panel');
        const builder = document.getElementById('uid-logic-filter-builder');
        const summary = document.getElementById('uid-logic-filter-summary');
        const studentContainer = document.getElementById('uid-checkbox-list');
        const insertPosition = document.getElementById('uid-logic-filter-insert-position');
        if (!panel || !builder || !summary || !studentContainer || !insertPosition) return;

        const getStudentItems = () => Array.from(studentContainer.querySelectorAll('.checkbox-item'));
        const getStudentCheckbox = (item) => item.querySelector('input[type="checkbox"]');
        const allStudentIds = () => getStudentItems().map((item) => getStudentCheckbox(item).value);
        const classOptions = () => Array.from(studentContainer.querySelectorAll('.select-all-class')).map((input) => {
            const heading = input.closest('.class-group-header');
            return { id: String(input.dataset.classId), label: heading?.querySelector('h5')?.textContent?.trim() || `Class ${input.dataset.classId}` };
        });
        const classMap = () => {
            const map = {};
            getStudentItems().forEach((item) => {
                const classId = String(item.dataset.classId);
                if (!map[classId]) map[classId] = [];
                map[classId].push(getStudentCheckbox(item).value);
            });
            return map;
        };
        const targetOptions = () => [
            ...classOptions().map((item) => ({ value: `class:${item.id}`, label: `グループ(クラス): ${item.label}` })),
            ...uidLogicFilterGroups.map((item) => ({ value: `group:${item.group_id}`, label: `グループ: ${item.group_name}` })),
        ];
        const optionHtml = () => {
            const options = targetOptions();
            return options.length === 0 ? '<option value="">対象がありません</option>' : options.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join('');
        };
        const kindOptionsHtml = (selectedKind) => [
            ['condition', '蟇ｾ雎｡'], ['and', 'AND'], ['or', 'OR'], ['not', 'NOT'], ['open', '('], ['close', ')'],
        ].map(([value, label]) => `<option value="${value}"${value === selectedKind ? ' selected' : ''}>${label}</option>`).join('');
        const tokenLabel = (token) => {
            const kind = token.dataset.kind;
            if (kind === 'condition') {
                const select = token.querySelector('.logic-filter-target');
                return select?.options[select.selectedIndex]?.textContent || '蟇ｾ雎｡';
            }
            if (kind === 'open') return '(';
            if (kind === 'close') return ')';
            return token.dataset.operator || kind.toUpperCase();
        };
        const updateInsertOptions = () => {
            const current = insertPosition.value;
            const tokens = Array.from(builder.querySelectorAll('.logic-filter-token'));
            insertPosition.innerHTML = ['<option value="">末尾に追加</option>'].concat(tokens.map((token, index) => `<option value="${index}">${index + 1}個目の前 (${escapeHtml(tokenLabel(token))})</option>`)).join('');
            if (current !== '' && Number(current) < tokens.length) insertPosition.value = current;
        };
        const renderToken = (token, kind) => {
            token.className = 'logic-filter-token';
            token.dataset.kind = kind;
            delete token.dataset.operator;
            const kindSelect = `<select class="logic-filter-kind">${kindOptionsHtml(kind)}</select>`;
            if (kind === 'condition') {
                token.innerHTML = `${kindSelect}<select class="logic-filter-target">${optionHtml()}</select><button type="button" class="logic-filter-remove">x</button>`;
            } else if (kind === 'open' || kind === 'close') {
                token.classList.add('paren');
                token.innerHTML = `${kindSelect}<span>${kind === 'open' ? '(' : ')'}</span><button type="button" class="logic-filter-remove">x</button>`;
            } else {
                token.classList.add('operator');
                if (kind === 'not') token.classList.add('not');
                token.dataset.operator = kind.toUpperCase();
                token.innerHTML = `${kindSelect}<span>${kind.toUpperCase()}</span><button type="button" class="logic-filter-remove">x</button>`;
            }
        };
        const addToken = (kind) => {
            const token = document.createElement('span');
            renderToken(token, kind);
            const tokens = Array.from(builder.querySelectorAll('.logic-filter-token'));
            const position = insertPosition.value !== '' ? Number(insertPosition.value) : tokens.length;
            if (Number.isInteger(position) && position >= 0 && position < tokens.length) {
                builder.insertBefore(token, tokens[position]);
                insertPosition.value = String(position + 1);
            } else {
                builder.appendChild(token);
            }
            updateInsertOptions();
        };
        const getTokens = () => Array.from(builder.querySelectorAll('.logic-filter-token')).map((token) => {
            const kind = token.dataset.kind;
            if (kind === 'condition') {
                const [targetType, targetId] = token.querySelector('.logic-filter-target').value.split(':');
                return { type: 'condition', targetType, targetId };
            }
            if (kind === 'open' || kind === 'close') return { type: 'paren', paren: kind === 'open' ? '(' : ')' };
            return { type: 'operator', operator: token.dataset.operator };
        });
        const setForCondition = (type, id) => {
            const available = new Set(allStudentIds());
            const source = type === 'group' ? uidLogicFilterStudentsByGroup : classMap();
            return new Set((source[String(id)] || []).map(String).filter((uid) => available.has(uid)));
        };
        const complement = (source) => {
            const selected = new Set(source);
            return new Set(allStudentIds().filter((uid) => !selected.has(uid)));
        };
        const evaluate = () => {
            const list = getTokens();
            if (list.length === 0) return new Set(allStudentIds());
            let index = 0;
            const primary = () => {
                const token = list[index];
                if (!token) throw new Error('条件が途中で終わっています。');
                if (token.type === 'operator' && token.operator === 'NOT') {
                    index++;
                    return complement(primary());
                }
                if (token.type === 'paren' && token.paren === '(') {
                    index++;
                    const result = orExpr();
                    if (!list[index] || list[index].type !== 'paren' || list[index].paren !== ')') throw new Error('閉じ括弧を置いてください。');
                    index++;
                    return result;
                }
                if (token.type === 'condition') {
                    index++;
                    if (!token.targetType || !token.targetId) throw new Error('対象を選択してください。');
                    return setForCondition(token.targetType, token.targetId);
                }
                throw new Error('条件または括弧を置いてください。');
            };
            const andExpr = () => {
                let result = primary();
                while (list[index]?.type === 'operator' && list[index].operator === 'AND') {
                    index++;
                    const right = primary();
                    result = new Set([...result].filter((uid) => right.has(uid)));
                }
                return result;
            };
            const orExpr = () => {
                let result = andExpr();
                while (list[index]?.type === 'operator' && list[index].operator === 'OR') {
                    index++;
                    result = new Set([...result, ...andExpr()]);
                }
                return result;
            };
            const result = orExpr();
            if (index !== list.length) throw new Error('式の並びを確認してください。');
            return result;
        };
        const syncClassChecks = () => {
            studentContainer.querySelectorAll('.select-all-class').forEach((input) => {
                const items = getStudentItems().filter((item) => item.dataset.classId === input.dataset.classId);
                input.checked = items.length > 0 && items.every((item) => getStudentCheckbox(item).checked);
            });
            const selectAll = studentContainer.querySelector('.select-all');
            if (selectAll) {
                const items = getStudentItems();
                selectAll.checked = items.length > 0 && items.every((item) => getStudentCheckbox(item).checked);
            }
        };

        panel.querySelectorAll('[data-add-uid-filter]').forEach((button) => button.addEventListener('click', () => addToken(button.dataset.addUidFilter)));
        builder.addEventListener('click', (event) => {
            if (!event.target.classList.contains('logic-filter-remove')) return;
            event.target.closest('.logic-filter-token').remove();
            updateInsertOptions();
        });
        builder.addEventListener('change', (event) => {
            const token = event.target.closest('.logic-filter-token');
            if (!token) return;
            if (event.target.classList.contains('logic-filter-kind')) renderToken(token, event.target.value);
            updateInsertOptions();
        });
        document.getElementById('apply-uid-logic-filter')?.addEventListener('click', () => {
            try {
                const selected = evaluate();
                getStudentItems().forEach((item) => { getStudentCheckbox(item).checked = selected.has(getStudentCheckbox(item).value); });
                syncClassChecks();
                summary.textContent = `${selected.size}名の学習者を選択しています。`;
                summary.classList.remove('is-error');
            } catch (error) {
                summary.textContent = error.message || '論理式を確認してください。';
                summary.classList.add('is-error');
            }
        });
        document.getElementById('reset-uid-logic-filter')?.addEventListener('click', () => {
            builder.innerHTML = '';
            getStudentItems().forEach((item) => { getStudentCheckbox(item).checked = true; });
            syncClassChecks();
            summary.textContent = 'すべての学習者を対象にしています。';
            summary.classList.remove('is-error');
            addToken('condition');
        });
        document.getElementById('trim-uid-logic-filter')?.addEventListener('click', () => {
            if (insertPosition.value === '') {
                summary.textContent = '削除を開始する追加位置を選択してください。';
                summary.classList.add('is-error');
                return;
            }
            const start = Number(insertPosition.value);
            Array.from(builder.querySelectorAll('.logic-filter-token')).forEach((token, index) => { if (index >= start) token.remove(); });
            updateInsertOptions();
            summary.textContent = `${start + 1}個目以降の部品を削除しました。`;
            summary.classList.remove('is-error');
        });
        document.getElementById('clear-uid-logic-filter')?.addEventListener('click', () => {
            builder.innerHTML = '';
            updateInsertOptions();
            summary.textContent = '式を空にしました。空のまま適用すると、すべての学習者が対象になります。';
            summary.classList.remove('is-error');
        });
        studentContainer.addEventListener('change', (event) => {
            const target = event.target;
            if (target.classList.contains('select-all')) {
                getStudentItems().forEach((item) => { getStudentCheckbox(item).checked = target.checked; });
                studentContainer.querySelectorAll('.select-all-class').forEach((input) => { input.checked = target.checked; });
                return;
            }
            if (target.classList.contains('select-all-class')) {
                getStudentItems().filter((item) => item.dataset.classId === target.dataset.classId).forEach((item) => { getStudentCheckbox(item).checked = target.checked; });
                syncClassChecks();
                return;
            }
            if (target.classList.contains('uid-checkbox')) syncClassChecks();
        });
        addToken('condition');
        syncClassChecks();
    };

    setupUidLogicFilter();

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
        clearFeatureFilterExpressionButton?.addEventListener('click', () => clearFeatureFilterExpression('式を空にしました。検索時は特徴量条件を使用しません。'));
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

    const openFloatingTooltip = (choice, closeable = false) => {
        const tooltip = choice.querySelector('.student-feature-popup, .student-tooltip');
        if (!tooltip) {
            return;
        }

        removeFloatingTooltip();
        activeChoice = choice;
        activeChoice.classList.add('tooltip-floating-active');
        floatingTooltip = document.createElement('div');
        floatingTooltip.className = closeable ? 'student-floating-tooltip student-floating-tooltip-click' : 'student-floating-tooltip';
        floatingTooltip.innerHTML = closeable
            ? `<button type="button" class="student-tooltip-close" aria-label="閉じる">×</button>${tooltip.innerHTML}`
            : tooltip.innerHTML;
        floatingTooltip.addEventListener('mouseleave', () => {
            if (!closeable) {
                removeFloatingTooltip();
            }
        });
        floatingTooltip.querySelector('.student-tooltip-close')?.addEventListener('click', removeFloatingTooltip);
        document.body.appendChild(floatingTooltip);
        positionFloatingTooltip();
    };

    document.addEventListener('mouseover', (event) => {
        const choice = event.target.closest('.student-choice');
        if (!choice || choice === activeChoice || choice.classList.contains('uid-filter-choice') || choice.classList.contains('click-tooltip-choice')) {
            return;
        }

        openFloatingTooltip(choice);
    });

    document.addEventListener('mouseout', (event) => {
        if (!activeChoice || !event.target.closest('.student-choice')) {
            return;
        }

        if (activeChoice.classList.contains('uid-filter-choice') || activeChoice.classList.contains('click-tooltip-choice')) {
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
        if (!choice || choice.classList.contains('uid-filter-choice') || choice.classList.contains('click-tooltip-choice')) {
            return;
        }

        openFloatingTooltip(choice);
    });

    document.addEventListener('focusout', (event) => {
        if (activeChoice?.classList.contains('uid-filter-choice') || activeChoice?.classList.contains('click-tooltip-choice')) {
            return;
        }
        if (activeChoice && activeChoice.contains(event.target)) {
            removeFloatingTooltip();
        }
    });

    document.addEventListener('click', (event) => {
        const infoButton = event.target.closest('.student-info-button');
        if (!infoButton) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        const choice = infoButton.closest('.student-choice');
        if (choice) {
            openFloatingTooltip(choice, true);
        }
    });

    window.addEventListener('scroll', positionFloatingTooltip, true);
    window.addEventListener('resize', positionFloatingTooltip);

    const renderStudentFeatureAverages = () => {
        if (!studentFeatureAverageList || !studentList) {
            return;
        }

        const items = Array.from(studentList.querySelectorAll('.student-pair-item'));
        if (items.length === 0) {
            studentFeatureAverageList.innerHTML = '<p class="feature-filter-empty">表示中のUID/WIDペアがありません。</p>';
            return;
        }

        const grouped = new Map();
        items.forEach((item) => {
            const uid = item.dataset.uid || '';
            if (!uid) {
                return;
            }

            let features = {};
            try {
                features = JSON.parse(item.dataset.features || '{}');
            } catch (error) {
                features = {};
            }

            if (!grouped.has(uid)) {
                grouped.set(uid, { count: 0, sums: {}, counts: {} });
            }

            const group = grouped.get(uid);
            group.count += 1;
            featureFilterOptions.forEach((option) => {
                const value = features[option.value];
                if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
                    return;
                }
                group.sums[option.value] = (group.sums[option.value] || 0) + Number(value);
                group.counts[option.value] = (group.counts[option.value] || 0) + 1;
            });
        });

        studentFeatureAverageList.innerHTML = Array.from(grouped.entries()).map(([uid, group]) => {
            const averages = {};
            featureFilterOptions.forEach((option) => {
                averages[option.value] = group.counts[option.value] ? group.sums[option.value] / group.counts[option.value] : null;
            });

            return `
                <div class="student-average-item">
                    <span class="student-choice student-average-choice click-tooltip-choice" tabindex="0">
                        <span class="student-average-label">UID:${escapeHtml(uid)}</span>
                        <button type="button" class="student-info-button" aria-label="学習者ごとの選択した問題における各特徴量の平均表示">ⓘ</button>
                        ${createFeatureTooltipHtml('学習者ごとの選択した問題における各特徴量の平均表示', averages, group.count)}
                    </span>
                </div>
            `;
        }).join('');
    };

    showStudentFeatureAveragesButton?.addEventListener('click', renderStudentFeatureAverages);

    searchButton.addEventListener('click', () => {
        if (!syncFeatureFilterExpressionInput(true)) {
            return;
        }

        // 繝輔か繝ｼ繝繝・・繧ｿ繧貞庶髮・
        const formData = new FormData(document.getElementById('search-form'));
        // FormData縺ｮ蜀・ｮｹ繧堤｢ｺ隱・
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
        // FormData縺ｮ蜀・ｮｹ繧堤｢ｺ隱・
        for (const [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }

        // 繧ｵ繝ｼ繝舌・縺ｫ讀懃ｴ｢譚｡莉ｶ繧帝∽ｿ｡
        fetch('search-students.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.text())
        .then(data => {
            // 繝ｪ繧ｹ繝医ｒ譖ｴ譁ｰ
            
            studentList.innerHTML = data;
            if (studentFeatureAverageList) {
                studentFeatureAverageList.innerHTML = '';
            }
        })
        .catch(error => console.error('エラー:', error));
    });
});


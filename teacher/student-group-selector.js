(function () {
    'use strict';

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[character]));
    const compareIds = (left, right) => String(left).localeCompare(String(right), 'ja', { numeric: true });

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('student-group-unified-selector');
        const groupForm = document.querySelector('.student-group-create-form');
        const studentList = document.getElementById('student-list');
        const summary = document.getElementById('unified-group-selection-summary');
        if (!root || !groupForm || !studentList || !window.TeacherResultsHistogram) return;

        const studentDirectory = new Map((window.studentGroupStudents || []).map((student) => [String(student.uid), student]));
        const metricAttempts = Array.isArray(window.studentGroupHistogramData?.metricAttempts)
            ? window.studentGroupHistogramData.metricAttempts
            : [];

        const attemptMatches = (attempt, correctness, hesitation) => {
            const correctnessMatches = correctness === 'all'
                || (correctness === 'correct' && Number(attempt.correctness) === 1)
                || (correctness === 'incorrect' && Number(attempt.correctness) === 0);
            const hesitationValue = attempt.hesitation === null || attempt.hesitation === undefined ? null : Number(attempt.hesitation);
            const hesitationMatches = hesitation === 'all'
                || (hesitation === 'hesitated' && hesitationValue === 2)
                || (hesitation === 'not_hesitated' && hesitationValue === 4)
                || (hesitation === 'not_estimated' && ![2, 4].includes(hesitationValue));
            return correctnessMatches && hesitationMatches;
        };

        const applyResultFilters = (uids, wids, correctness, hesitation) => {
            if (correctness === 'all' && hesitation === 'all') return uids;
            const uidSet = new Set(uids.map(String));
            const widSet = new Set(wids.map(String));
            const matched = new Set();
            metricAttempts.forEach((attempt) => {
                const uid = String(attempt.uid);
                if (!uidSet.has(uid) || !widSet.has(String(attempt.wid))) return;
                if (attemptMatches(attempt, correctness, hesitation)) matched.add(uid);
            });
            return uids.filter((uid) => matched.has(String(uid)));
        };

        const renderCandidates = (uids, wids, correctness, hesitation) => {
            const filteredUids = applyResultFilters(uids.map(String), wids.map(String), correctness, hesitation).sort(compareIds);
            if (!filteredUids.length) {
                studentList.innerHTML = '<li class="student-list-status">選択条件に該当する学習者はいません。</li>';
                if (summary) summary.textContent = `対象問題(WID): ${wids.length}件 / グループ候補: 0人`;
                return;
            }
            studentList.innerHTML = filteredUids.map((uid) => {
                const student = studentDirectory.get(uid) || {};
                return `<li class="student-item" data-uid="${escapeHtml(uid)}"><label class="student-choice student-result-choice"><input type="checkbox" name="students[]" value="${escapeHtml(uid)}" checked><span class="student-result-identity"><strong>学習者(UID): ${escapeHtml(uid)}</strong><span>名前: ${escapeHtml(student.name || '未登録')}</span><span>グループ(クラス): ${escapeHtml(student.className || '未登録')}</span></span></label></li>`;
            }).join('');
            if (summary) summary.textContent = `対象問題(WID): ${wids.length}件 / グループ候補: ${filteredUids.length}人（全員選択中）`;
        };

        window.TeacherResultsHistogram.create({
            root,
            scope: 'class',
            features: window.studentGroupFeatureColumns || {},
            featureMeta: window.studentGroupFeatureDisplayMeta || {},
            groups: window.studentGroupLogicFilterGroups || [],
            groupStudents: window.studentGroupLogicFilterStudentsByGroup || {},
            submitLabel: '選択した学習者をグループ候補へ反映',
            onSubmit: async ({ uids, wids, correctness, hesitation }) => {
                renderCandidates(uids, wids, correctness, hesitation);
                studentList.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            },
        });

        studentList.innerHTML = '<li class="student-list-status">問題(WID)を確定し、学習者(UID)を選択してグループ候補へ反映してください。</li>';
        studentList.addEventListener('change', (event) => {
            if (!event.target.matches('input[name="students[]"]') || !summary) return;
            const total = studentList.querySelectorAll('input[name="students[]"]').length;
            const checked = studentList.querySelectorAll('input[name="students[]"]:checked').length;
            summary.textContent = `グループ候補: ${total}人 / 作成対象として選択中: ${checked}人`;
        });
        groupForm.addEventListener('submit', (event) => {
            if (studentList.querySelector('input[name="students[]"]:checked')) return;
            event.preventDefault();
            window.alert('グループに追加する学習者を1人以上選択してください。');
        });
    });
}());

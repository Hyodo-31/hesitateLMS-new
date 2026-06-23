document.addEventListener('DOMContentLoaded', () => {
    const searchButton = document.getElementById('search-button');
    const studentList = document.getElementById('student-list');
    let floatingTooltip = null;
    let activeChoice = null;

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
        const availableRight = window.innerWidth - rect.left - margin;
        const showRight = availableRight >= tooltipRect.width;
        let left = showRight ? rect.left : window.innerWidth - tooltipRect.width - margin;
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

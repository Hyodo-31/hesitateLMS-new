document.addEventListener('DOMContentLoaded', () => {
    const searchButton = document.getElementById('search-button');
    const studentList = document.getElementById('student-list');

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

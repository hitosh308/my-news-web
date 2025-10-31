document.addEventListener('DOMContentLoaded', () => {
    const editor = document.getElementById('config-editor');
    const status = document.getElementById('settings-status');
    const config = window.NEWS_CONFIG || {};
    editor.value = JSON.stringify(config, null, 2);

    document.getElementById('save-settings').addEventListener('click', async () => {
        let parsed;
        status.textContent = '';
        try {
            parsed = JSON.parse(editor.value);
        } catch (error) {
            status.textContent = 'JSONの構文が正しくありません。';
            status.style.color = '#e53935';
            return;
        }

        status.textContent = '保存中...';
        status.style.color = '';

        try {
            const response = await fetch('api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ config: parsed })
            });

            if (!response.ok) {
                throw new Error('保存に失敗しました');
            }

            const data = await response.json();
            if (data.status !== 'ok') {
                throw new Error('保存に失敗しました');
            }

            status.textContent = '設定を保存しました。';
            status.style.color = '#2f6fed';
        } catch (error) {
            console.error(error);
            status.textContent = '設定の保存に失敗しました。';
            status.style.color = '#e53935';
        }
    });
});

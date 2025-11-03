const STORAGE_KEY = 'my-news-web-settings';
const DB_NAME = 'my-news-web';
const DB_VERSION = 1;
const STORE_NAME = 'settings';
const SETTINGS_CHANNEL_NAME = 'my-news-web-settings';

const state = {
    categories: [],
    sources: [],
    views: [],
    defaultKeywords: []
};

let settingsBroadcastChannel = null;

document.addEventListener('DOMContentLoaded', () => {
    initializeSettingsPage().catch(error => {
        console.error('設定画面の初期化に失敗しました', error);
    });
});

async function initializeSettingsPage() {
    const config = window.NEWS_CONFIG || {};
    const stored = await loadStoredSettings();
    state.categories = Array.isArray(config.categories)
        ? config.categories
            .filter(category => typeof category === 'string' && category.trim().length > 0)
            .map(category => category.trim())
        : [];
    state.sources = Array.isArray(config.sources)
        ? config.sources
            .map(source => {
                if (typeof source === 'string') {
                    return { id: source, name: source };
                }
                if (!source || typeof source !== 'object') {
                    return null;
                }
                const id = typeof source.id === 'string' ? source.id : '';
                if (!id) {
                    return null;
                }
                return {
                    id,
                    name: typeof source.name === 'string' ? source.name : id
                };
            })
            .filter(Boolean)
        : [];
    const fallbackViews = Array.isArray(config.views)
        ? config.views
            .map(normalizeView)
            .filter(view => view.id && view.name)
        : [];
    const fallbackKeywords = normalizeKeywordsList(config.conditions ? config.conditions.keywords : []);

    state.views = stored ? stored.views : fallbackViews;
    state.defaultKeywords = stored ? stored.defaultKeywords : fallbackKeywords;

    renderViews();
    initializeDefaultKeywords();
    bindEvents();
}

async function loadStoredSettings() {
    if (!isIndexedDbAvailable()) {
        console.warn('IndexedDBが利用できないため、保存済みの設定を読み込めません。');
        return null;
    }

    try {
        const db = await openSettingsDatabase();
        return await new Promise(resolve => {
            const transaction = db.transaction(STORE_NAME, 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.get(STORAGE_KEY);

            request.onsuccess = () => {
                const raw = request.result;
                if (!raw || typeof raw !== 'object') {
                    resolve(null);
                    return;
                }

                const views = Array.isArray(raw.views)
                    ? raw.views
                        .map(normalizeView)
                        .filter(view => view.id && view.name)
                    : [];
                const defaultKeywords = normalizeKeywordsList(raw.conditions ? raw.conditions.keywords : []);

                resolve({ views, defaultKeywords });
            };

            request.onerror = event => {
                console.error('設定の読み込みに失敗しました', event.target.error);
                resolve(null);
            };

            transaction.oncomplete = () => {
                db.close();
            };

            transaction.onabort = () => {
                console.error('設定の読み込みトランザクションが中断されました', transaction.error);
                db.close();
                resolve(null);
            };
        });
    } catch (error) {
        console.error('設定の読み込みに失敗しました', error);
        return null;
    }
}

async function saveSettingsToStorage(views, defaultKeywords) {
    if (!isIndexedDbAvailable()) {
        throw new Error('IndexedDBが利用できません');
    }

    const payload = {
        views: views.map(view => ({
            id: view.id,
            name: view.name,
            categories: Array.isArray(view.categories) ? view.categories.slice() : [],
            sources: Array.isArray(view.sources) ? view.sources.slice() : [],
            keywords: Array.isArray(view.keywords) ? view.keywords.slice() : []
        })),
        conditions: {
            keywords: Array.isArray(defaultKeywords) ? defaultKeywords.slice() : []
        }
    };

    const db = await openSettingsDatabase();

    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.put(payload, STORAGE_KEY);

        request.onerror = event => {
            reject(event.target.error || new Error('設定の保存に失敗しました'));
        };

        transaction.oncomplete = () => {
            db.close();
            resolve();
        };

        transaction.onabort = () => {
            const error = transaction.error || new Error('設定の保存が中断されました');
            db.close();
            reject(error);
        };
    });
}

function prepareViewsForStorage(views) {
    const normalized = [];
    const usedIds = new Set();
    let maxAutoNumber = 0;

    const registerId = id => {
        if (typeof id !== 'string') {
            return;
        }
        const trimmed = id.trim();
        if (!trimmed) {
            return;
        }
        if (!usedIds.has(trimmed)) {
            usedIds.add(trimmed);
        }

        const match = /^view-(\d+)$/i.exec(trimmed);
        if (match) {
            const value = Number(match[1]);
            if (value > maxAutoNumber) {
                maxAutoNumber = value;
            }
        }
    };

    state.views.forEach(view => {
        if (!view || typeof view !== 'object') {
            return;
        }
        registerId(view.id);
    });

    views.forEach(view => {
        const normalizedView = normalizeView(view);
        if (!normalizedView.name) {
            return;
        }

        if (normalizedView.id) {
            registerId(normalizedView.id);
        }

        normalized.push(normalizedView);
    });

    normalized.forEach(view => {
        if (view.id) {
            return;
        }

        let candidate;
        do {
            maxAutoNumber += 1;
            candidate = `view-${maxAutoNumber}`;
        } while (usedIds.has(candidate));

        view.id = candidate;
        usedIds.add(candidate);
    });

    return normalized;
}

function normalizeKeywordsList(values) {
    if (!Array.isArray(values)) {
        return [];
    }

    return Array.from(new Set(
        values
            .filter(value => typeof value === 'string')
            .map(value => value.trim())
            .filter(value => value.length > 0)
    ));
}

function isIndexedDbAvailable() {
    return typeof window !== 'undefined' && 'indexedDB' in window;
}

function supportsBroadcastChannel() {
    return typeof window !== 'undefined' && 'BroadcastChannel' in window;
}

function getSettingsBroadcastChannel() {
    if (!supportsBroadcastChannel()) {
        return null;
    }

    if (!settingsBroadcastChannel) {
        settingsBroadcastChannel = new BroadcastChannel(SETTINGS_CHANNEL_NAME);
        window.addEventListener('beforeunload', closeSettingsBroadcastChannel);
    }

    return settingsBroadcastChannel;
}

function closeSettingsBroadcastChannel() {
    if (!settingsBroadcastChannel) {
        return;
    }

    try {
        settingsBroadcastChannel.close();
    } catch (error) {
        console.warn('設定同期チャネルのクローズに失敗しました', error);
    }

    settingsBroadcastChannel = null;
    window.removeEventListener('beforeunload', closeSettingsBroadcastChannel);
}

function notifySettingsUpdate(views, defaultKeywords) {
    const channel = getSettingsBroadcastChannel();
    if (!channel) {
        return;
    }

    try {
        channel.postMessage({
            type: 'settings-updated',
            payload: {
                views: Array.isArray(views)
                    ? views.map(view => ({
                        id: view.id,
                        name: view.name,
                        categories: Array.isArray(view.categories) ? view.categories.slice() : [],
                        sources: Array.isArray(view.sources) ? view.sources.slice() : [],
                        keywords: Array.isArray(view.keywords) ? view.keywords.slice() : []
                    }))
                    : [],
                defaultKeywords: Array.isArray(defaultKeywords) ? defaultKeywords.slice() : []
            }
        });
    } catch (error) {
        console.warn('設定更新の通知に失敗しました', error);
    }
}

function bindEvents() {
    const addButton = document.getElementById('add-view');
    if (addButton) {
        addButton.addEventListener('click', () => {
            state.views = snapshotViews();
            state.views.push({
                id: '',
                name: '',
                categories: [],
                sources: [],
                keywords: []
            });
            renderViews();
        });
    }

    const editor = document.getElementById('view-editor');
    if (editor) {
        editor.addEventListener('click', event => {
            const deleteButton = event.target.closest('[data-action="delete-view"]');
            if (!deleteButton) {
                return;
            }

            const card = deleteButton.closest('.view-card');
            if (!card) {
                return;
            }

            const index = Number(card.dataset.index);
            if (Number.isInteger(index)) {
                const updated = snapshotViews();
                updated.splice(index, 1);
                state.views = updated;
                renderViews();
            }
        });
    }

    const saveButton = document.getElementById('save-settings');
    if (saveButton) {
        saveButton.addEventListener('click', handleSave);
    }
}

function renderViews() {
    const container = document.getElementById('view-editor');
    if (!container) {
        return;
    }

    container.innerHTML = '';

    if (!state.views.length) {
        const emptyMessage = document.createElement('p');
        emptyMessage.className = 'view-empty';
        emptyMessage.textContent = 'ビューが登録されていません。「ビューを追加」から作成してください。';
        container.appendChild(emptyMessage);
        return;
    }

    state.views.forEach((view, index) => {
        const card = document.createElement('section');
        card.className = 'view-card';
        card.dataset.index = String(index);
        card.dataset.viewId = view.id || '';

        const autoIdPlaceholder = view.id ? '' : '保存時に自動採番されます';

        card.innerHTML = `
            <div class="view-card-header">
                <h3>${escapeHtml(view.name || `ビュー${index + 1}`)}</h3>
                <div class="view-card-actions">
                    <span class="view-id-label">ID: ${view.id ? escapeHtml(view.id) : '未割り当て'}</span>
                    <button type="button" class="ghost-button danger" data-action="delete-view">削除</button>
                </div>
            </div>
            <div class="form-grid">
                <label>ビューID
                    <input type="text" name="view-id" value="${escapeAttribute(view.id)}" placeholder="${escapeAttribute(autoIdPlaceholder)}" readonly>
                </label>
                <label>表示名
                    <input type="text" name="view-name" value="${escapeAttribute(view.name)}" placeholder="例: テクノロジー特集">
                </label>
            </div>
            <div class="view-field-group">
                <div class="field-header">
                    <h4>カテゴリ</h4>
                    <p>未選択の場合はすべてのカテゴリが対象になります。</p>
                </div>
                <div class="checkbox-grid">
                    ${renderCategoryCheckboxes(index, view.categories)}
                </div>
            </div>
            <div class="view-field-group">
                <div class="field-header">
                    <h4>ニュースサイト</h4>
                    <p>未選択の場合はすべてのサイトが対象になります。</p>
                </div>
                <div class="checkbox-grid">
                    ${renderSourceCheckboxes(index, view.sources)}
                </div>
            </div>
            <div class="view-field-group">
                <h4>キーワード</h4>
                <p>カンマ区切りで入力してください。</p>
                <textarea name="view-keywords" rows="2" placeholder="例: AI, スタートアップ">${escapeHtml((view.keywords || []).join(', '))}</textarea>
            </div>
        `;

        const nameInput = card.querySelector('input[name="view-name"]');
        const heading = card.querySelector('.view-card-header h3');
        if (nameInput && heading) {
            nameInput.addEventListener('input', () => {
                heading.textContent = nameInput.value.trim() || `ビュー${index + 1}`;
            });
        }

        container.appendChild(card);
    });
}

function renderCategoryCheckboxes(viewIndex, selected = []) {
    const selectedSet = new Set(Array.isArray(selected) ? selected : []);
    if (!state.categories.length) {
        return '<p class="empty-helper">カテゴリが設定されていません。</p>';
    }
    return state.categories.map((category, categoryIndex) => {
        const id = `view-${viewIndex}-category-${categoryIndex}`;
        if (!category) {
            return '';
        }
        const checked = selectedSet.has(category) ? ' checked' : '';
        return `
            <label for="${id}">
                <input type="checkbox" id="${id}" name="category-${viewIndex}" value="${escapeAttribute(category)}"${checked}>
                ${escapeHtml(category)}
            </label>
        `;
    }).join('');
}

function renderSourceCheckboxes(viewIndex, selected = []) {
    const selectedSet = new Set(Array.isArray(selected) ? selected : []);
    if (!state.sources.length) {
        return '<p class="empty-helper">ニュースサイトが設定されていません。</p>';
    }
    return state.sources.map((source, sourceIndex) => {
        const idValue = source.id;
        if (!idValue) {
            return '';
        }
        const labelId = `view-${viewIndex}-source-${sourceIndex}`;
        const checked = selectedSet.has(idValue) ? ' checked' : '';
        return `
            <label for="${labelId}">
                <input type="checkbox" id="${labelId}" name="source-${viewIndex}" value="${escapeAttribute(idValue)}"${checked}>
                ${escapeHtml(source.name)}
            </label>
        `;
    }).join('');
}

function initializeDefaultKeywords() {
    const input = document.getElementById('default-keywords');
    if (!input) {
        return;
    }

    input.value = state.defaultKeywords.join(', ');
}

async function handleSave() {
    const status = document.getElementById('settings-status');
    if (!status) {
        return;
    }

    status.textContent = '';
    status.style.color = '';

    const views = collectViewsFromDom();
    if (views === null) {
        // collectViewsFromDom already displayed error message
        return;
    }

    const defaultKeywordsInput = document.getElementById('default-keywords');
    const defaultKeywords = parseKeywords(defaultKeywordsInput ? defaultKeywordsInput.value : '');

    status.textContent = '保存中...';

    try {
        const normalizedViews = prepareViewsForStorage(views);
        const normalizedKeywords = normalizeKeywordsList(defaultKeywords);
        await saveSettingsToStorage(normalizedViews, normalizedKeywords);
        notifySettingsUpdate(normalizedViews, normalizedKeywords);

        state.views = normalizedViews;
        state.defaultKeywords = normalizedKeywords;

        renderViews();
        initializeDefaultKeywords();

        status.textContent = '設定を保存しました。';
        status.style.color = '#2f6fed';
    } catch (error) {
        console.error(error);
        status.textContent = '設定の保存に失敗しました。ブラウザの設定を確認してください。';
        status.style.color = '#e53935';
    }
}

function openSettingsDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = event => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME);
            }
        };

        request.onsuccess = event => {
            resolve(event.target.result);
        };

        request.onerror = event => {
            reject(event.target.error || new Error('IndexedDBを開けませんでした'));
        };
    });
}

function snapshotViews() {
    const cards = document.querySelectorAll('.view-card');
    if (!cards.length) {
        return state.views.slice();
    }

    return Array.from(cards).map((card, index) => {
        const idInput = card.querySelector('input[name="view-id"]');
        const nameInput = card.querySelector('input[name="view-name"]');
        const keywordsInput = card.querySelector('textarea[name="view-keywords"]');

        return normalizeView({
            id: card.dataset.viewId || (idInput ? idInput.value : ''),
            name: nameInput ? nameInput.value : '',
            categories: Array.from(new Set(collectCheckedValues(card, `input[name="category-${index}"]:checked`))),
            sources: Array.from(new Set(collectCheckedValues(card, `input[name="source-${index}"]:checked`))),
            keywords: keywordsInput ? parseKeywords(keywordsInput.value) : []
        });
    });
}

function collectViewsFromDom() {
    const status = document.getElementById('settings-status');
    const cards = document.querySelectorAll('.view-card');
    const views = [];
    const ids = new Set();

    for (let index = 0; index < cards.length; index++) {
        const card = cards[index];
        const nameInput = card.querySelector('input[name="view-name"]');
        const keywordsInput = card.querySelector('textarea[name="view-keywords"]');

        const viewId = (card.dataset.viewId || '').trim();
        const viewName = nameInput ? nameInput.value.trim() : '';
        const keywords = keywordsInput ? parseKeywords(keywordsInput.value) : [];
        const categories = Array.from(new Set(collectCheckedValues(card, `input[name="category-${index}"]:checked`)));
        const sources = Array.from(new Set(collectCheckedValues(card, `input[name="source-${index}"]:checked`)));

        if (!viewName) {
            if (status) {
                status.textContent = '表示名は必須です。';
                status.style.color = '#e53935';
            }
            return null;
        }

        if (viewId && ids.has(viewId)) {
            if (status) {
                status.textContent = 'ビューIDが重複しています。最新の情報を読み込んでから再度保存してください。';
                status.style.color = '#e53935';
            }
            return null;
        }

        if (viewId) {
            ids.add(viewId);
        }

        views.push({
            id: viewId,
            name: viewName,
            categories,
            sources,
            keywords
        });
    }

    if (status) {
        status.textContent = '';
        status.style.color = '';
    }

    return views;
}

function collectCheckedValues(root, selector) {
    return Array.from(root.querySelectorAll(selector))
        .map(input => input.value.trim())
        .filter(value => value.length > 0);
}

function parseKeywords(value) {
    const keywords = value
        .split(',')
        .map(keyword => keyword.trim())
        .filter(keyword => keyword.length > 0);
    return Array.from(new Set(keywords));
}

function escapeHtml(value = '') {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeAttribute(value = '') {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function normalizeView(view) {
    const categories = Array.isArray(view.categories)
        ? Array.from(new Set(
            view.categories
                .filter(item => typeof item === 'string' && item.trim().length > 0)
                .map(item => item.trim())
        ))
        : [];
    const sources = Array.isArray(view.sources)
        ? Array.from(new Set(
            view.sources
                .filter(item => typeof item === 'string' && item.trim().length > 0)
                .map(item => item.trim())
        ))
        : [];
    const keywords = Array.isArray(view.keywords)
        ? Array.from(new Set(
            view.keywords
                .filter(item => typeof item === 'string' && item.trim().length > 0)
                .map(item => item.trim())
        ))
        : [];

    return {
        id: typeof view.id === 'string' ? view.id.trim() : '',
        name: typeof view.name === 'string' ? view.name.trim() : '',
        categories,
        sources,
        keywords
    };
}

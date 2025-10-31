const state = {
    categories: [],
    sources: [],
    keywords: [],
    layout: 'grid',
    data: null
};

document.addEventListener('DOMContentLoaded', () => {
    bootstrapConfig();
    bindEvents();
    renderFilters();
    renderKeywords();
    loadNews();
});

function bootstrapConfig() {
    const config = window.NEWS_CONFIG || {};
    state.categories = config.categories || [];
    state.sources = (config.sources || []).map(source => ({
        id: source.id,
        name: source.name,
        selected: true
    }));
    state.keywords = (config.conditions && config.conditions.keywords) ? config.conditions.keywords.slice() : [];
    state.rawConfig = config;
}

function bindEvents() {
    document.getElementById('refresh-news').addEventListener('click', loadNews);
    document.getElementById('add-keyword').addEventListener('click', () => {
        const input = document.getElementById('keyword-input');
        addKeyword(input.value);
        input.value = '';
    });

    document.getElementById('keyword-input').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addKeyword(e.target.value);
            e.target.value = '';
        }
    });

    document.querySelectorAll('.layout-button').forEach(button => {
        button.addEventListener('click', () => {
            state.layout = button.dataset.layout;
            document.querySelectorAll('.layout-button').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            applyLayout();
        });
    });

    document.getElementById('open-settings').addEventListener('click', () => {
        openSettings();
    });

    document.getElementById('close-settings').addEventListener('click', () => {
        closeSettings();
    });

    document.getElementById('save-settings').addEventListener('click', () => {
        saveSettings();
    });
}

function renderFilters() {
    const categoryList = document.getElementById('category-list');
    categoryList.innerHTML = '';
    state.categories.forEach((category, index) => {
        const id = `cat-${index}`;
        const wrapper = document.createElement('label');
        wrapper.innerHTML = `<input type="checkbox" id="${id}" value="${category}" checked> ${category}`;
        categoryList.appendChild(wrapper);
    });

    categoryList.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            loadNews();
        });
    });

    const sourceList = document.getElementById('source-list');
    sourceList.innerHTML = '';
    state.sources.forEach((source, index) => {
        const id = `source-${index}`;
        const wrapper = document.createElement('label');
        wrapper.innerHTML = `<input type="checkbox" id="${id}" value="${source.id}" ${source.selected ? 'checked' : ''}> ${source.name}`;
        sourceList.appendChild(wrapper);
    });

    sourceList.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            const source = state.sources.find(s => s.id === checkbox.value);
            if (source) {
                source.selected = checkbox.checked;
            }
            loadNews();
        });
    });
}

function renderKeywords() {
    const container = document.getElementById('keyword-tags');
    container.innerHTML = '';
    state.keywords.forEach(keyword => {
        const tag = document.createElement('span');
        tag.className = 'keyword-tag';
        tag.innerHTML = `${keyword}<button aria-label="削除" data-value="${keyword}">×</button>`;
        container.appendChild(tag);
    });

    container.querySelectorAll('button').forEach(button => {
        button.addEventListener('click', () => {
            removeKeyword(button.dataset.value);
        });
    });
}

function addKeyword(keyword) {
    const trimmed = keyword.trim();
    if (!trimmed) return;
    if (!state.keywords.includes(trimmed)) {
        state.keywords.push(trimmed);
        renderKeywords();
        loadNews();
    }
}

function removeKeyword(keyword) {
    state.keywords = state.keywords.filter(k => k !== keyword);
    renderKeywords();
    loadNews();
}

async function loadNews() {
    const statusText = document.getElementById('status-text');
    statusText.textContent = '読み込み中...';

    const selectedCategories = Array.from(document.querySelectorAll('#category-list input:checked')).map(el => el.value);
    const selectedSources = state.sources.filter(s => s.selected).map(s => s.id);

    try {
        const response = await fetch('api/news.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                categories: selectedCategories,
                sources: selectedSources,
                keywords: state.keywords
            })
        });

        if (!response.ok) {
            throw new Error('ニュース取得に失敗しました');
        }

        const data = await response.json();
        state.data = data;
        renderNews();
        statusText.textContent = `最終更新: ${new Date(data.generatedAt).toLocaleString('ja-JP')}`;
    } catch (error) {
        console.error(error);
        statusText.textContent = 'ニュースを取得できませんでした。設定を確認してください。';
        document.getElementById('news-grid').innerHTML = '';
    }
}

function renderNews() {
    const container = document.getElementById('news-grid');
    container.className = `news-grid ${state.layout}`;
    container.innerHTML = '';

    if (!state.data || !state.data.sources || state.data.sources.length === 0) {
        container.innerHTML = '<p>条件に一致するニュースが見つかりませんでした。</p>';
        return;
    }

    state.data.sources.forEach(source => {
        const section = document.createElement('section');
        section.className = 'news-section';
        section.draggable = true;
        section.dataset.sourceId = source.sourceId;

        const header = document.createElement('div');
        header.className = 'section-header';
        const categoryLabels = (source.categories || []).join(', ');
        header.innerHTML = `<h3>${source.source}</h3><span>${categoryLabels}</span>`;
        section.appendChild(header);

        const articleWrapper = document.createElement('div');
        articleWrapper.className = 'article-wrapper';

        if (source.error) {
            const error = document.createElement('p');
            error.textContent = source.error;
            articleWrapper.appendChild(error);
        } else if (source.empty) {
            const emptyMessage = document.createElement('p');
            emptyMessage.textContent = '現在の条件ではこのサイトの記事は見つかりませんでした。';
            articleWrapper.appendChild(emptyMessage);
        } else {
            source.items.forEach(item => {
                const card = document.createElement('article');
               card.className = 'article-card';
               card.draggable = true;
               card.innerHTML = `
                   <div class="article-title">${escapeHtml(item.title)}</div>
                   <div class="article-meta">${item.published ? `<span>${item.published}</span>` : ''}</div>
                   <div class="article-description">${escapeHtml(item.description)}</div>
                   <a href="${item.link}" class="article-link" target="_blank" rel="noopener noreferrer">続きを読む</a>
               `;
                articleWrapper.appendChild(card);
                enableCardDrag(card);
           });
       }

        section.appendChild(articleWrapper);
        container.appendChild(section);
        enableSectionDrag(section, container);
    });
}

function applyLayout() {
    const container = document.getElementById('news-grid');
    container.className = `news-grid ${state.layout}`;
}

function escapeHtml(value) {
    const safe = value || '';
    return safe
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function enableSectionDrag(section, container) {
    section.addEventListener('dragstart', () => {
        section.classList.add('dragging');
    });
    section.addEventListener('dragend', () => {
        section.classList.remove('dragging');
    });

    if (!container.dataset.sectionDragListener) {
        container.addEventListener('dragover', event => {
            event.preventDefault();
            const dragging = container.querySelector('.news-section.dragging');
            if (!dragging) return;
            const afterElement = getDragAfterElement(container, event.clientY);
            if (afterElement == null) {
                container.appendChild(dragging);
            } else {
                container.insertBefore(dragging, afterElement);
            }
        });
        container.dataset.sectionDragListener = '1';
    }
}

function enableCardDrag(card) {
    card.addEventListener('dragstart', () => card.classList.add('dragging'));
    card.addEventListener('dragend', () => card.classList.remove('dragging'));

    card.addEventListener('dragover', event => {
        event.preventDefault();
    });

    const wrapper = card.parentElement;
    if (!wrapper) return;
    if (!wrapper.dataset.dragListener) {
        wrapper.addEventListener('dragover', event => {
            event.preventDefault();
            const dragging = wrapper.querySelector('.article-card.dragging');
            if (!dragging) return;
            const afterElement = getDragAfterElement(wrapper, event.clientY, '.article-card');
            if (afterElement == null) {
                wrapper.appendChild(dragging);
            } else {
                wrapper.insertBefore(dragging, afterElement);
            }
        });
        wrapper.dataset.dragListener = '1';
    }
}

function getDragAfterElement(container, y, selector = '.news-section') {
    const draggableElements = [...container.querySelectorAll(`${selector}:not(.dragging)` )];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset, element: child };
        }
        return closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element || null;
}

function openSettings() {
    const modal = document.getElementById('settings-modal');
    const editor = document.getElementById('config-editor');
    editor.value = JSON.stringify(state.rawConfig || {}, null, 2);
    modal.hidden = false;
}

function closeSettings() {
    const modal = document.getElementById('settings-modal');
    modal.hidden = true;
}

async function saveSettings() {
    const editor = document.getElementById('config-editor');
    let parsed;
    try {
        parsed = JSON.parse(editor.value);
    } catch (e) {
        alert('JSONの構文が正しくありません。');
        return;
    }

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

        state.rawConfig = parsed;
        state.categories = parsed.categories || [];
        state.sources = (parsed.sources || []).map(source => ({
            id: source.id,
            name: source.name,
            selected: true
        }));
        state.keywords = (parsed.conditions && parsed.conditions.keywords) ? parsed.conditions.keywords.slice() : [];

        renderFilters();
        renderKeywords();
        closeSettings();
        loadNews();
    } catch (error) {
        console.error(error);
        alert('設定の保存に失敗しました。');
    }
}

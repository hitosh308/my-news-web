const state = {
    categories: [],
    sources: [],
    keywords: [],
    selectedCategories: null,
    data: null,
    views: [],
    activeViewId: null
};

document.addEventListener('DOMContentLoaded', () => {
    bootstrapConfig();
    bindEvents();
    renderViewList();
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
    state.views = Array.isArray(config.views) ? config.views : [];

    if (state.views.length > 0) {
        applyView(state.views[0].id, { suppressRender: true });
    } else {
        state.activeViewId = null;
        state.selectedCategories = null;
        state.keywords = (config.conditions && Array.isArray(config.conditions.keywords))
            ? config.conditions.keywords.slice()
            : [];
        state.sources.forEach(source => {
            source.selected = true;
        });
    }
}

function bindEvents() {
    const refreshButton = document.getElementById('refresh-news');
    if (refreshButton) {
        refreshButton.addEventListener('click', loadNews);
    }

    const addKeywordButton = document.getElementById('add-keyword');
    if (addKeywordButton) {
        addKeywordButton.addEventListener('click', () => {
            const input = document.getElementById('keyword-input');
            if (!input) {
                return;
            }
            addKeyword(input.value);
            input.value = '';
        });
    }

    const keywordInput = document.getElementById('keyword-input');
    if (keywordInput) {
        keywordInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addKeyword(e.target.value);
                e.target.value = '';
            }
        });
    }

    initializeFilterPanel();
}

function renderViewList() {
    const container = document.getElementById('view-list');
    if (!container) {
        return;
    }

    container.innerHTML = '';

    if (!state.views.length) {
        const empty = document.createElement('p');
        empty.className = 'view-empty';
        empty.textContent = 'ビューが設定されていません。';
        container.appendChild(empty);
        return;
    }

    state.views.forEach(view => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `view-button${state.activeViewId === view.id ? ' active' : ''}`;
        button.textContent = view.name || view.id;
        button.addEventListener('click', () => {
            if (state.activeViewId === view.id) {
                return;
            }
            applyView(view.id);
        });
        container.appendChild(button);
    });
}

function applyView(viewId, options = {}) {
    const view = state.views.find(v => v.id === viewId);
    if (!view) {
        return;
    }

    state.activeViewId = viewId;
    state.keywords = Array.isArray(view.keywords) ? view.keywords.slice() : [];
    const categories = Array.isArray(view.categories) ? view.categories.filter(Boolean) : [];
    state.selectedCategories = categories.length === 0 ? null : categories;

    const sourceIds = Array.isArray(view.sources) ? view.sources.filter(Boolean) : [];
    const selectedSourceIds = sourceIds.length === 0 ? state.sources.map(source => source.id) : sourceIds;
    state.sources.forEach(source => {
        source.selected = selectedSourceIds.includes(source.id);
    });

    if (!options.suppressRender) {
        renderViewList();
        renderFilters();
        renderKeywords();
        loadNews();
    }
}

function renderFilters() {
    const categoryList = document.getElementById('category-list');
    if (categoryList) {
        categoryList.innerHTML = '';
        state.categories.forEach((category, index) => {
            const id = `cat-${index}`;
            const wrapper = document.createElement('label');
            const isChecked = state.selectedCategories === null || (state.selectedCategories || []).includes(category);
            const value = escapeAttribute(category);
            wrapper.innerHTML = `<input type="checkbox" id="${id}" value="${value}" ${isChecked ? 'checked' : ''}> ${escapeHtml(category)}`;
            categoryList.appendChild(wrapper);
        });

        categoryList.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const checkboxes = categoryList.querySelectorAll('input[type="checkbox"]');
                const selected = Array.from(categoryList.querySelectorAll('input[type="checkbox"]:checked')).map(el => el.value);
                state.selectedCategories = selected.length === checkboxes.length ? null : selected;
                loadNews();
            });
        });
    }

    const sourceList = document.getElementById('source-list');
    if (sourceList) {
        sourceList.innerHTML = '';
        state.sources.forEach((source, index) => {
            const id = `source-${index}`;
            const wrapper = document.createElement('label');
            const value = escapeAttribute(source.id);
            wrapper.innerHTML = `<input type="checkbox" id="${id}" value="${value}" ${source.selected ? 'checked' : ''}> ${escapeHtml(source.name)}`;
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
}

function renderKeywords() {
    const container = document.getElementById('keyword-tags');
    if (!container) {
        return;
    }

    container.innerHTML = '';
    state.keywords.forEach(keyword => {
        const tag = document.createElement('span');
        tag.className = 'keyword-tag';
        tag.innerHTML = `${escapeHtml(keyword)}<button aria-label="削除" data-value="${escapeAttribute(keyword)}">×</button>`;
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

    const categoryList = document.getElementById('category-list');
    if (categoryList) {
        const checkboxes = categoryList.querySelectorAll('input[type="checkbox"]');
        const checkedValues = Array.from(categoryList.querySelectorAll('input[type="checkbox"]:checked')).map(el => el.value);
        state.selectedCategories = checkedValues.length === checkboxes.length ? null : checkedValues;
    }

    const sourceList = document.getElementById('source-list');
    if (sourceList) {
        const selectedIds = Array.from(sourceList.querySelectorAll('input[type="checkbox"]'))
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);
        state.sources.forEach(source => {
            source.selected = selectedIds.includes(source.id);
        });
    }

    const categoriesPayload = state.selectedCategories === null ? null : state.selectedCategories.slice();
    const selectedSources = state.sources.filter(s => s.selected).map(s => s.id);
    const keywordsPayload = state.keywords.slice();

    try {
        const response = await fetch('api/news.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                categories: categoriesPayload,
                sources: selectedSources,
                keywords: keywordsPayload
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

function initializeFilterPanel() {
    const toggleButtons = document.querySelectorAll('[data-filter-toggle]');
    const closeFiltersButton = document.getElementById('close-filters');
    const filterBackdrop = document.getElementById('filter-backdrop');

    toggleButtons.forEach(button => {
        button.addEventListener('click', () => {
            if (document.body.classList.contains('filters-open')) {
                closeFilters();
            } else {
                openFilters();
            }
        });
    });

    if (closeFiltersButton) {
        closeFiltersButton.addEventListener('click', closeFilters);
    }

    if (filterBackdrop) {
        filterBackdrop.addEventListener('click', closeFilters);
    }

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && document.body.classList.contains('filters-open')) {
            closeFilters();
        }
    });

    const wideScreenQuery = window.matchMedia('(min-width: 961px)');
    const handleQueryChange = () => {
        closeFilters();
    };

    if (typeof wideScreenQuery.addEventListener === 'function') {
        wideScreenQuery.addEventListener('change', handleQueryChange);
    } else if (typeof wideScreenQuery.addListener === 'function') {
        wideScreenQuery.addListener(handleQueryChange);
    }

    closeFilters();
}

function openFilters() {
    if (!isMobileLayout()) {
        return;
    }

    document.body.classList.add('filters-open');
    const filterPanel = document.getElementById('filter-panel');
    const filterBackdrop = document.getElementById('filter-backdrop');

    if (filterPanel) {
        filterPanel.setAttribute('aria-hidden', 'false');
    }

    if (filterBackdrop) {
        filterBackdrop.hidden = false;
    }

    setToggleState(true);
}

function closeFilters() {
    document.body.classList.remove('filters-open');
    const filterPanel = document.getElementById('filter-panel');
    const filterBackdrop = document.getElementById('filter-backdrop');

    if (filterBackdrop) {
        filterBackdrop.hidden = true;
    }

    if (filterPanel) {
        if (isMobileLayout()) {
            filterPanel.setAttribute('aria-hidden', 'true');
        } else {
            filterPanel.removeAttribute('aria-hidden');
        }
    }

    setToggleState(false);
}

function isMobileLayout() {
    return window.matchMedia('(max-width: 960px)').matches;
}

function setToggleState(isOpen) {
    const toggleButtons = document.querySelectorAll('[data-filter-toggle]');
    toggleButtons.forEach(button => {
        button.setAttribute('aria-expanded', String(isOpen));
        const labelOpen = button.dataset.labelOpen;
        const labelClose = button.dataset.labelClose;
        if (labelOpen && labelClose) {
            button.setAttribute('aria-label', isOpen ? labelClose : labelOpen);
        }
    });
}

function renderNews() {
    const container = document.getElementById('news-grid');
    container.className = 'news-grid';
    container.innerHTML = '';

    if (!state.data || !state.data.sources || state.data.sources.length === 0) {
        container.innerHTML = '<p>条件に一致するニュースが見つかりませんでした。</p>';
        return;
    }

    let hasSections = false;

    state.data.sources.forEach(source => {
        const items = Array.isArray(source.items) ? source.items.filter(Boolean) : [];
        if (!items.length) {
            return;
        }

        const section = document.createElement('section');
        section.className = 'news-section';
        section.draggable = true;
        section.dataset.sourceId = source.sourceId;

        const header = document.createElement('div');
        header.className = 'section-header';
        const categories = (source.categories || [])
            .map(category => typeof category === 'string' ? category.trim() : category)
            .filter(Boolean);
        const categoryLabels = categories.join(', ');
        const categoryHtml = categoryLabels ? `<span class="section-categories">${escapeHtml(categoryLabels)}</span>` : '';
        const sourceName = typeof source.source === 'string' ? source.source.trim() : source.source;
        const sourceHtml = sourceName ? `<span class="section-source">${escapeHtml(sourceName)}</span>` : '';
        const headerContent = `${categoryHtml}${sourceHtml}`;
        header.innerHTML = headerContent;
        section.appendChild(header);

        const articleWrapper = document.createElement('div');
        articleWrapper.className = 'article-wrapper';

        items.forEach(item => {
            const card = document.createElement('article');
            card.className = 'article-card';
            card.draggable = true;
            const imageHtml = item.image ? `<figure class="article-image"><img src="${escapeAttribute(item.image)}" alt="${escapeAttribute(item.title || '記事のアイキャッチ画像')}"></figure>` : '';
            card.innerHTML = `
                ${imageHtml}
                <div class="article-title">${escapeHtml(item.title)}</div>
                <div class="article-meta">${item.published ? `<span>${escapeHtml(item.published)}</span>` : ''}</div>
                <div class="article-description">${escapeHtml(item.description)}</div>
                <a href="${escapeAttribute(item.link)}" class="article-link" target="_blank" rel="noopener noreferrer">続きを読む</a>
            `;
            articleWrapper.appendChild(card);
            enableCardDrag(card);
        });

        section.appendChild(articleWrapper);
        container.appendChild(section);
        enableSectionDrag(section, container);
        hasSections = true;
    });

    if (!hasSections) {
        container.innerHTML = '<p>条件に一致するニュースが見つかりませんでした。</p>';
        return;
    }

    container.classList.add('column');
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

function escapeAttribute(value) {
    const safe = value || '';
    return safe
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
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

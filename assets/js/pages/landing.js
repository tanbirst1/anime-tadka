(function () {
    'use strict';

    const searchInput = document.getElementById('live-search-input');
    const resultsBox = document.getElementById('search-results-box');
    const historyBox = document.getElementById('search-history-box');
    const spinner = document.getElementById('search-spinner');
    const submitBtn = document.getElementById('search-submit-btn');
    const HISTORY_KEY = 'animetadka_search_history';
    const MAX_HISTORY = 10;
    let debounceTimer = null;

    function getHistory() {
        try {
            const raw = localStorage.getItem(HISTORY_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveHistory(term) {
        if (!term || !term.trim()) return;
        const list = getHistory().filter(h => h.toLowerCase() !== term.toLowerCase());
        list.unshift(term);
        const trimmed = list.slice(0, MAX_HISTORY);
        try {
            localStorage.setItem(HISTORY_KEY, JSON.stringify(trimmed));
        } catch (e) {}
        renderHistory();
    }

    function renderHistory() {
        const list = getHistory();
        const container = document.getElementById('history-items-list');
        if (!container) return;
        if (list.length === 0) {
            historyBox.style.display = 'none';
            return;
        }
        historyBox.style.display = 'block';
        container.innerHTML = list.map(term =>
            '<div class="history-item" data-term="' + escapeHtml(term) + '">' +
            '<span class="history-term">' + escapeHtml(term) + '</span>' +
            '<button class="history-remove" data-term="' + escapeHtml(term) + '" aria-label="Remove">&times;</button>' +
            '</div>'
        ).join('');
    }

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function showResults(html) {
        resultsBox.innerHTML = html;
        resultsBox.style.display = 'block';
        historyBox.style.display = 'none';
        spinner.style.display = 'none';
    }

    function showNoResults() {
        resultsBox.innerHTML = '<div class="no-search-res"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg><p>No results found.</p></div>';
        resultsBox.style.display = 'block';
        historyBox.style.display = 'none';
        spinner.style.display = 'none';
    }

    function performSearch(term) {
        if (!term || !term.trim()) {
            resultsBox.style.display = 'none';
            renderHistory();
            return;
        }

        spinner.style.display = 'block';
        resultsBox.style.display = 'none';

        const formData = new FormData();
        formData.append('action', 'anime_search');
        formData.append('term', term.trim());

        fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        })
        .then(function (res) { return res.text(); })
        .then(function (html) {
            if (!html || html === '0') {
                showNoResults();
                return;
            }
            showResults(html);
            saveHistory(term);
        })
        .catch(function () {
            showNoResults();
        });
    }

    function goToSearchPage() {
        const term = searchInput.value.trim();
        if (term) {
            window.location.href = '/search?s=' + encodeURIComponent(term);
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = searchInput.value;
            clearTimeout(debounceTimer);
            if (!term || !term.trim()) {
                resultsBox.style.display = 'none';
                renderHistory();
                return;
            }
            debounceTimer = setTimeout(function () {
                performSearch(term);
            }, 300);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                goToSearchPage();
            }
        });

        searchInput.addEventListener('focus', function () {
            if (!searchInput.value.trim()) {
                renderHistory();
            }
        });
    }

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            goToSearchPage();
        });
    }

    document.addEventListener('click', function (e) {
        const target = e.target;

        if (target.closest('.top-search-tag')) {
            const term = target.closest('.top-search-tag').getAttribute('data-term');
            if (term) {
                searchInput.value = term;
                performSearch(term);
            }
            return;
        }

        if (target.closest('.history-item') || target.closest('.history-remove')) {
            const term = target.closest('[data-term]') ? target.closest('[data-term]').getAttribute('data-term') : null;
            if (term) {
                if (target.closest('.history-remove')) {
                    const list = getHistory().filter(h => h.toLowerCase() !== term.toLowerCase());
                    try { localStorage.setItem(HISTORY_KEY, JSON.stringify(list)); } catch (e) {}
                    renderHistory();
                } else {
                    searchInput.value = term;
                    performSearch(term);
                }
            }
            return;
        }

        if (target.closest('.search-res-item')) {
            const title = target.closest('.search-res-item').querySelector('.res-title');
            if (title && title.textContent) {
                saveSearchHistory(title.textContent.trim());
            }
        }

        if (!target.closest('.landing-search-area') && !target.closest('.top-searches-wrap')) {
            if (!searchInput.matches(':focus')) {
                resultsBox.style.display = 'none';
            }
        }
    });

    window.saveSearchHistory = function (term) {
        if (term) {
            saveHistory(term);
        }
    };

    renderHistory();
})();

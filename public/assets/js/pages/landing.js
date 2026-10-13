/**
 * Landing page JavaScript — search, social share, top searches
 */
(function() {
    'use strict';

    // Copy link
    window.copyLandingLink = function() {
        navigator.clipboard.writeText(window.location.origin).then(function() {
            var btn = document.querySelector('.glass-link[onclick="copyLandingLink()"]');
            if (btn) {
                var orig = btn.innerHTML;
                btn.innerHTML = '✓ COPIED!';
                setTimeout(function() { btn.innerHTML = orig; }, 2000);
            }
        }).catch(function() {
            // Fallback
            var input = document.createElement('input');
            input.value = window.location.origin;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        });
    };

    // Live search on landing page
    var searchInput = document.getElementById('live-search-input');
    var searchResults = document.getElementById('search-results-box');
    var searchHistory = document.getElementById('search-history-box');
    var historyList = document.getElementById('history-items-list');
    var searchSpinner = document.getElementById('search-spinner');

    if (searchInput) {
        var debounceTimer;

        searchInput.addEventListener('input', function() {
            var query = this.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 2) {
                if (searchResults) searchResults.style.display = 'none';
                if (searchHistory && getSearchHistory().length > 0) {
                    showSearchHistory();
                }
                return;
            }

            if (searchSpinner) searchSpinner.style.display = 'block';

            debounceTimer = setTimeout(function() {
                fetchSearchResults(query);
            }, 300);
        });

        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length === 0 && getSearchHistory().length > 0) {
                showSearchHistory();
            }
        });
    }

    function fetchSearchResults(query) {
        // In production this would hit an API endpoint
        // For now, redirect to search page
        if (searchSpinner) searchSpinner.style.display = 'none';
        window.location.href = '/search?s=' + encodeURIComponent(query);
    }

    function getSearchHistory() {
        try {
            return JSON.parse(localStorage.getItem('at_search_history') || '[]');
        } catch(e) { return []; }
    }

    function showSearchHistory() {
        if (!searchHistory || !historyList) return;
        var history = getSearchHistory();
        if (history.length === 0) { searchHistory.style.display = 'none'; return; }
        
        historyList.innerHTML = '';
        history.slice(0, 5).forEach(function(term) {
            var btn = document.createElement('button');
            btn.className = 'history-item';
            btn.textContent = term;
            btn.addEventListener('click', function() {
                if (searchInput) searchInput.value = term;
                window.location.href = '/search?s=' + encodeURIComponent(term);
            });
            historyList.appendChild(btn);
        });
        searchHistory.style.display = 'block';
    }

    // Top search tags
    document.querySelectorAll('.top-search-tag').forEach(function(tag) {
        tag.addEventListener('click', function() {
            var term = this.getAttribute('data-term');
            window.location.href = '/search?s=' + encodeURIComponent(term);
        });
    });
})();

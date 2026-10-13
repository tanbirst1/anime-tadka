/**
 * Main site JavaScript — sidebar, search, navigation, interactions
 */
(function() {
    'use strict';

    // === Sidebar ===
    var sidebar = document.getElementById('at-mobile-sidebar');
    var backdrop = document.getElementById('at-backdrop');
    var toggle = document.getElementById('sidebar-toggle');
    var closeBtn = document.getElementById('sidebar-close');

    // Global functions matching the reference site's inline handlers.
    window.openAkSidebar = function() {
        if (sidebar) sidebar.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-open');
        document.body.classList.add('at-menu-frozen');
    };

    window.closeAkSidebar = function() {
        if (sidebar) sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-open');
        document.body.classList.remove('at-menu-frozen');
    };

    function openSidebar() { window.openAkSidebar(); }
    function closeSidebar() { window.closeAkSidebar(); }

    if (toggle) toggle.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);

    // === Search overlay keyboard shortcuts (no visual impact) ===
    // theme.js (reference JS) owns the open/close + live search; these are
    // non-visual conveniences: Esc closes, Ctrl/Cmd+K opens from anywhere.
    var searchPopup = document.getElementById('search-popup');
    var searchInput = document.getElementById('live-search-input');
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            var popup = document.getElementById('search-popup');
            if (popup && !popup.classList.contains('active')) {
                var t = document.getElementById('toggle-search-overlay');
                if (t) t.click();
            }
            return;
        }
        if (e.key === 'Escape' && searchPopup && searchPopup.classList.contains('active')) {
            var bg = document.getElementById('close-search-bg');
            if (bg) bg.click();
        }
    });
    // Clear the input when the overlay is dismissed (visual state stays clean).
    if (searchPopup && searchInput) {
        searchPopup.addEventListener('transitionend', function(e) {
            if (e.propertyName === 'opacity' && !searchPopup.classList.contains('active')) {
                if (document.activeElement === searchInput || searchInput.value) {
                    searchInput.value = '';
                }
            }
        });
    }

    // === Shuffle Button ===
    var shuffleBtn = document.getElementById('shuffleBtn');
    if (shuffleBtn) {
        shuffleBtn.addEventListener('click', function() {
            var overlay = document.getElementById('diceOverlay');
            if (overlay) {
                overlay.style.display = 'flex';
                setTimeout(function() {
                    window.location.href = shuffleBtn.getAttribute('data-url') || '/home';
                }, 1800);
            } else {
                window.location.reload();
            }
        });
    }

    // === App Promo Dismiss ===
    var promoDismiss = document.getElementById('at-promo-dismiss');
    var appPromo = document.getElementById('at-app-promo');
    if (promoDismiss && appPromo) {
        promoDismiss.addEventListener('click', function() {
            appPromo.classList.remove('show-promo');
            appPromo.style.display = 'none';
        });
    }

    // === History Management (localStorage based) ===
    window.AnimeTadka = {
        addToHistory: function(anime) {
            try {
                var history = JSON.parse(localStorage.getItem('at_watch_history') || '[]');
                history = history.filter(function(h) { return h.slug !== anime.slug; });
                anime.timestamp = Date.now();
                history.unshift(anime);
                if (history.length > 50) history = history.slice(0, 50);
                localStorage.setItem('at_watch_history', JSON.stringify(history));
            } catch(e) {}
        },
        getHistory: function() {
            try {
                return JSON.parse(localStorage.getItem('at_watch_history') || '[]');
            } catch(e) { return []; }
        },
        clearHistory: function() {
            localStorage.removeItem('at_watch_history');
        },
        saveSearchTerm: function(term) {
            try {
                var history = JSON.parse(localStorage.getItem('at_search_history') || '[]');
                history = history.filter(function(t) { return t !== term; });
                history.unshift(term);
                if (history.length > 20) history = history.slice(0, 20);
                localStorage.setItem('at_search_history', JSON.stringify(history));
            } catch(e) {}
        }
    };
})();

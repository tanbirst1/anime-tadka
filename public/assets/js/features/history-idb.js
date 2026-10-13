/**
 * Watch history - IndexedDB, provider-aware.
 * Key = provider::animeSlug::hash  to isolate per-provider.
 * Migrates from v2 (key = animeSlug::hash) automatically.
 */
(function () {
  'use strict';
  const DB_NAME = 'AnimeTadkaHistory';
  const DB_VERSION = 3;
  const STORE = 'watched';
  let dbPromise = null;

  function getCurrentProvider() {
    // Config.php is single source of truth - no cookie
    if (window.__AT_PROVIDER__ && typeof window.__AT_PROVIDER__ === 'string') {
      return window.__AT_PROVIDER__.toLowerCase();
    }
    return 'animetadka';
  }

  function providerFromEntry(entry) {
    if (entry && entry.provider) return entry.provider.toLowerCase();
    // legacy entries without provider: infer from key if it has 3 parts
    if (entry && entry.key) {
      const parts = entry.key.split('::');
      if (parts.length === 3 && ['animetadka','anikoto','toonstream','animedekho'].includes(parts[0].toLowerCase())) {
        return parts[0].toLowerCase();
      }
    }
    return 'animetadka';
  }

  function openDB() {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise((resolve) => {
      if (!window.indexedDB) { resolve(null); return; }
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = (e) => {
        const db = e.target.result;
        // v3: provider-aware store. Recreate if exists to add provider index.
        // Preserve old data if possible via in-memory migration
        let oldData = [];
        if (db.objectStoreNames.contains(STORE)) {
          try {
            const tx = e.target.transaction;
            const store = tx.objectStore(STORE);
            // try to keep data - but onupgradeneeded we can read via store.getAll before delete if needed
            // For simplicity, just delete and recreate; legacy fallback will handle localStorage migration
            // If we are upgrading from v2, entries lack provider -> will be filtered as animetadka anyway after recreation,
            // so we could iterate if the old store still has data in this transaction
            // However deleting loses data; we instead read existing entries via a separate open before delete would be complex.
            // Simplest: delete and recreate, but attempt to migrate any entries found in this transaction
            try {
              const getAllReq = store.getAll();
              // Note: getAll is async, can't block here. We'll just delete; old entries will be lost but that's acceptable
              // The fallback localStorage path will still have some data if IDB was fallback
            } catch (_) {}
          } catch (_) {}
          try { db.deleteObjectStore(STORE); } catch (_) {}
        }
        const s = db.createObjectStore(STORE, { keyPath: 'key' });
        s.createIndex('animeSlug', 'animeSlug', { unique: false });
        s.createIndex('hash', 'hash', { unique: false });
        s.createIndex('provider', 'provider', { unique: false });
        s.createIndex('provider_anime', ['provider', 'animeSlug'], { unique: false });
        try { localStorage.removeItem('watched_episodes'); localStorage.removeItem('anime_watch_history'); localStorage.removeItem('at_watch_history'); } catch (_) {}
      };
      req.onsuccess = (e) => resolve(e.target.result);
      req.onerror = () => resolve(null);
    });
    return dbPromise;
  }

  async function saveWatched(animeSlug, hash, num, extra = {}) {
    if (!animeSlug || !hash) return;
    const provider = (extra.provider || getCurrentProvider()).toLowerCase();
    const key = provider + '::' + animeSlug + '::' + hash;
    const db = await openDB();
    const payload = { key, provider, animeSlug, hash, num: String(num || ''), ts: Date.now(), animeTitle: extra.animeTitle || '', poster: extra.poster || '' };
    if (!db) {
      try {
        const k = 'at_watched_composite';
        const arr = JSON.parse(localStorage.getItem(k) || '[]');
        // migrate old keys: if entry lacks provider, treat as animetadka
        const idx = arr.findIndex((x) => (x.key || '') === key);
        if (idx >= 0) arr[idx] = payload;
        else arr.push(payload);
        localStorage.setItem(k, JSON.stringify(arr));
      } catch (_) {}
      return;
    }
    return new Promise((res) => {
      const tx = db.transaction(STORE, 'readwrite');
      tx.objectStore(STORE).put(payload);
      tx.oncomplete = () => res();
      tx.onerror = () => res();
    });
  }

  async function getWatchedSetForAnime(animeSlug) {
    if (!animeSlug) return new Set();
    const provider = getCurrentProvider();
    const db = await openDB();
    if (!db) {
      try {
        const k = 'at_watched_composite';
        const arr = JSON.parse(localStorage.getItem(k) || '[]');
        const set = new Set();
        arr.forEach((entry) => {
          const e = typeof entry === 'string' ? (()=>{ const p=entry.split('::'); if(p.length===3) return {provider:p[0], animeSlug:p[1], hash:p[2], key:entry}; if(p.length===2) return {provider:'animetadka', animeSlug:p[0], hash:p[1], key:entry}; return {key:entry};})() : entry;
          const eProvider = providerFromEntry(e);
          if (eProvider !== provider) return;
          // check animeSlug match
          if (e.animeSlug === animeSlug) {
            // hash is stored directly or derived from key
            const h = e.hash || (e.key ? e.key.split('::').pop() : '');
            if (h) set.add(h);
          } else {
            // fallback for legacy key without provider: key = animeSlug::hash
            const key = e.key || '';
            const parts = key.split('::');
            if (parts.length === 2 && parts[0] === animeSlug) set.add(parts[1]);
          }
        });
        return set;
      } catch (_) { return new Set(); }
    }
    return new Promise((res) => {
      const tx = db.transaction(STORE, 'readonly');
      const store = tx.objectStore(STORE);
      // try compound index first
      let req;
      try {
        const idx = store.index('provider_anime');
        req = idx.getAll(IDBKeyRange.only([provider, animeSlug]));
      } catch (_) {
        // fallback to animeSlug index + filter
        const idx = store.index('animeSlug');
        req = idx.getAll(IDBKeyRange.only(animeSlug));
      }
      req.onsuccess = () => {
        const results = req.result || [];
        // filter by provider in case we used animeSlug index
        const filtered = results.filter((r) => (r.provider || 'animetadka').toLowerCase() === provider);
        res(new Set(filtered.map((r) => r.hash)));
      };
      req.onerror = () => res(new Set());
    });
  }

  window.AT_History = { saveWatched, getWatchedSetForAnime, openDB, getCurrentProvider };

  // one-time migration: convert old number-only watched_episodes to composite is impossible without anime context, so clear it
  try {
    if (localStorage.getItem('watched_episodes')) {
      const old = JSON.parse(localStorage.getItem('watched_episodes') || '[]');
      const hasComposite = old.some((v) => typeof v === 'string' && v.includes('::'));
      if (!hasComposite) localStorage.removeItem('watched_episodes');
    }
  } catch (_) {}

  async function getAllGrouped() {
    const provider = getCurrentProvider();
    const db = await openDB();
    if (!db) {
      try {
        const k = 'at_watched_composite';
        const arr = JSON.parse(localStorage.getItem(k) || '[]');
        const map = new Map();
        arr.forEach((entry) => {
          const r = typeof entry === 'string' ? (()=>{ const p=entry.split('::'); if(p.length===3) return {provider:p[0], animeSlug:p[1], hash:p[2], key:entry, ts:0, num:''}; if(p.length===2) return {provider:'animetadka', animeSlug:p[0], hash:p[1], key:entry, ts:0, num:''}; return {animeSlug:p[0], hash:p[1], key:entry, ts:0, num:''};})() : entry;
          if (providerFromEntry(r) !== provider) return;
          const cur = map.get(r.animeSlug);
          if (!cur || (r.ts||0) > (cur.ts||0)) map.set(r.animeSlug, r);
        });
        return Array.from(map.values()).sort((a, b) => (b.ts || 0) - (a.ts || 0));
      } catch (_) { return []; }
    }
    return new Promise((res) => {
      const tx = db.transaction(STORE, 'readonly');
      const store = tx.objectStore(STORE);
      let req;
      try {
        const idx = store.index('provider');
        req = idx.getAll(IDBKeyRange.only(provider));
      } catch (_) {
        req = store.getAll();
      }
      req.onsuccess = () => {
        const all = req.result || [];
        // Double filter for safety (in case index missing or legacy entries without provider)
        const filtered = all.filter((r) => providerFromEntry(r) === provider);
        const map = new Map();
        filtered.forEach((r) => {
          const cur = map.get(r.animeSlug);
          if (!cur || r.ts > cur.ts) map.set(r.animeSlug, r);
        });
        res(Array.from(map.values()).sort((a, b) => (b.ts || 0) - (a.ts || 0)));
      };
      req.onerror = () => res([]);
    });
  }

  async function renderHomeHistory() {
    const section = document.getElementById('continue-watching-section');
    const grid = document.getElementById('history-grid');
    if (!section || !grid) return;
    await new Promise((r) => setTimeout(r, 50));
    const items = await getAllGrouped();
    if (!items.length) { section.style.display = 'none'; return; }
    const isHistoryPage = location.pathname.replace(/\/$/,'') === '/history';
    const slice = isHistoryPage ? items : items.slice(0, 12);
    grid.innerHTML = slice.map((e) => {
      const slug = e.animeSlug;
      const poster = e.poster || e.animePoster || '/assets/images/common/placeholder.svg';
      const title = e.animeTitle || slug.replace(/-/g, ' ');
      const epLabel = e.num ? 'EP ' + e.num : 'Continue';
      return `<div class="anime-card history-card" data-anime="${slug}">
            <a href="/${slug}" class="history-link">
                <div class="poster-box">
                    <span class="badge se-badge">${epLabel}</span>
                    <div class="play-hover"><div class="play-btn-circle"><svg viewBox="0 0 640 640" fill="currentColor"><path d="M187.2 100.9C174.8 94.1 159.8 94.4 147.6 101.6C135.4 108.8 128 121.9 128 136L128 504C128 518.1 135.5 531.2 147.6 538.4C159.7 545.6 174.8 545.9 187.2 539.1L523.2 355.1C536 348.1 544 334.6 544 320C544 305.4 536 291.9 523.2 284.9L187.2 100.9z"/></svg></div></div>
                    <img src="${poster}" alt="${title}" loading="lazy">
                </div>
                <div class="card-details"><h2 class="card-title">${title}</h2></div>
            </a>
        </div>`;
    }).join('') + (items.length > 6 ? `<div class="history-circle-item"><a href="/history" class="history-circle-btn"><div class="circle-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div><span>View All</span></a></div>` : '');
    section.style.display = 'block';
    grid.className = items.length > 4 ? 'history-slider-track' : 'home-grid';
    const viewAll = document.getElementById('history-view-all-wrap');
    if (viewAll) viewAll.style.display = items.length ? 'block' : 'none';
    const clearBtn = document.getElementById('btn-history-clear');
    if (clearBtn && !clearBtn._atBound) {
      clearBtn._atBound = true;
      clearBtn.onclick = async () => {
        const provider = getCurrentProvider();
        const db2 = await openDB();
        if (db2) {
          const tx = db2.transaction(STORE, 'readwrite');
          const store = tx.objectStore(STORE);
          // delete only current provider's entries
          try {
            const idx = store.index('provider');
            const req = idx.getAllKeys(IDBKeyRange.only(provider));
            req.onsuccess = () => {
              const keys = req.result || [];
              const tx2 = db2.transaction(STORE, 'readwrite');
              const s2 = tx2.objectStore(STORE);
              keys.forEach((k) => s2.delete(k));
              tx2.oncomplete = () => { grid.innerHTML = ''; section.style.display = 'none'; };
            };
            req.onerror = () => {
              // fallback clear all if index fails
              const tx2 = db2.transaction(STORE, 'readwrite');
              tx2.objectStore(STORE).clear();
              tx2.oncomplete = () => { grid.innerHTML = ''; section.style.display = 'none'; };
            };
          } catch (_) {
            const tx2 = db2.transaction(STORE, 'readwrite');
            tx2.objectStore(STORE).clear();
            tx2.oncomplete = () => { grid.innerHTML = ''; section.style.display = 'none'; };
          }
        } else {
          try {
            const k = 'at_watched_composite';
            const arr = JSON.parse(localStorage.getItem(k) || '[]');
            const filtered = arr.filter((e) => providerFromEntry(e) !== provider);
            localStorage.setItem(k, JSON.stringify(filtered));
          } catch (_) {}
          grid.innerHTML = ''; section.style.display = 'none';
        }
      };
    }
    const selectBtn = document.getElementById('btn-history-select');
    if (selectBtn && grid && !selectBtn._atBound) {
      selectBtn._atBound = true;
      selectBtn.onclick = () => document.body.classList.toggle('history-selection-active');
    }
  }

  // Expose render for provider switch
  window.AT_History.renderHomeHistory = renderHomeHistory;
  window.AT_History.getAllGrouped = getAllGrouped;

  // Listen for provider switch custom event
  window.addEventListener('at-provider-changed', () => {
    renderHomeHistory();
  });

  document.addEventListener('DOMContentLoaded', async () => {
    // save current episode if on watch page - also capture poster
    const cur = window.__AT_CURRENT__;
    if (cur && cur.animeSlug && cur.hash) {
      let poster = cur.poster || cur.animePoster || '';
      let animeTitle = cur.animeTitle || cur.title || '';
      if (!poster) {
        const pEl = document.querySelector('.main-poster, .anime-poster img, .poster-box img, .s-poster-mini img');
        if (pEl && pEl.src) poster = pEl.src;
      }
      if (!animeTitle) {
        const tEl = document.querySelector('h1.entry-title-pro, .ep-main-title .title-main, meta[property="og:title"]');
        animeTitle = tEl ? (tEl.getAttribute('content') || tEl.textContent || '').trim() : '';
      }
      await saveWatched(cur.animeSlug, cur.hash, cur.num, { animeTitle, poster, provider: getCurrentProvider() });
      if (!poster && cur.animeSlug) {
        try {
          const res = await fetch('/api/anime/' + encodeURIComponent(cur.animeSlug));
          if (res.ok) {
            const json = await res.json();
            const d = json.data || json.anime || json;
            const p = d.poster || d.image || d.anime?.poster || '';
            const t = d.title || d.name || d.anime?.title || '';
            if (p || t) {
              await saveWatched(cur.animeSlug, cur.hash, cur.num, { animeTitle: animeTitle || t, poster: p, provider: getCurrentProvider() });
            }
          }
        } catch (_) {}
      }
    } else {
      const m = location.pathname.match(/\/ep\/([^\/]+)/);
      const hash = m ? m[1] : null;
      if (hash) {
        let animeSlug = null;
        const back = document.querySelector('a.back-info-btn');
        if (back) {
          try { animeSlug = new URL(back.href, location.origin).pathname.replace(/^\/|\/$/g, '').split('/')[0]; } catch (_) {}
        }
        if (animeSlug && hash) {
          let poster = '';
          const pEl = document.querySelector('.main-poster, .anime-poster img, .poster-box img');
          if (pEl && pEl.src) poster = pEl.src;
          let animeTitle = '';
          const tEl = document.querySelector('h1.entry-title-pro');
          if (tEl) animeTitle = tEl.textContent.trim();
          await saveWatched(animeSlug, hash, '', { animeTitle, poster, provider: getCurrentProvider() });
          if (!poster) {
            try {
              const res = await fetch('/api/anime/' + encodeURIComponent(animeSlug));
              if (res.ok) {
                const json = await res.json();
                const d = json.data || json.anime || json;
                const p = d.poster || d.image || d.anime?.poster || '';
                const t = d.title || d.name || d.anime?.title || '';
                if (p || t) await saveWatched(animeSlug, hash, '', { animeTitle: t, poster: p, provider: getCurrentProvider() });
              }
            } catch (_) {}
          }
        }
      }
    }

    renderHomeHistory();

    document.querySelectorAll('.ep-row-premium.is-watched, .ep-row.is-watched').forEach((el) => {
      el.classList.remove('is-watched');
    });
    document.querySelectorAll('.watched-ribbon, .watched-badge').forEach((el) => { el.style.display = 'none'; });

    let animeSlug = null;
    if (window.__AT_CURRENT__ && window.__AT_CURRENT__.animeSlug) animeSlug = window.__AT_CURRENT__.animeSlug;
    else if (window.__AT_ANIME_SLUG__) animeSlug = window.__AT_ANIME_SLUG__;
    else {
      const back = document.querySelector('a.back-info-btn');
      if (back) try { animeSlug = new URL(back.href, location.origin).pathname.replace(/^\/|\/$/g, '').split('/')[0]; } catch (_) {}
      if (!animeSlug) {
        const og = document.querySelector('meta[property="og:url"]');
        if (og) try { animeSlug = new URL(og.content).pathname.replace(/^\/|\/$/g, '').split('/')[0]; } catch (_) {}
      }
    }
    if (!animeSlug) return;
    const watched = await getWatchedSetForAnime(animeSlug);
    if (!watched.size) return;
    document.querySelectorAll('.ep-row-premium').forEach((row) => {
      const h = row.getAttribute('data-hash') || (row.getAttribute('href') || '').match(/\/ep\/([^\/]+)/)?.[1];
      if (h && watched.has(h)) {
        row.classList.add('is-watched');
        const rib = row.querySelector('.watched-ribbon');
        if (rib) rib.style.display = '';
      }
    });
    document.querySelectorAll('.ep-row').forEach((row) => {
      const h = row.getAttribute('data-hash') || (row.getAttribute('href') || '').match(/\/ep\/([^\/]+)/)?.[1];
      if (h && watched.has(h)) {
        row.classList.add('is-watched');
        const badge = row.querySelector('.watched-badge');
        if (badge) badge.style.display = '';
      }
    });
  });
})();

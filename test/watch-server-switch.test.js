'use strict';

/**
 * Regression tests for the watch-page server switching.
 *
 * The bug they cover ("src not load"): the server tabs kept a global
 * `switching` lock that was only released by the player iframe's `load`
 * event. Many player frames never fire `load` (CSP frame-ancestors, ORB
 * blocked ad frames, cross-origin CDN players), so after one switch the lock
 * stayed armed for its 10s timeout and swallowed the next tap. Clicking a
 * second server therefore did nothing and the player looked like it had
 * failed to load its src.
 *
 * These tests run the REAL inline script from app/Views/watch/episode.php in
 * jsdom, so they fail if the lock is reintroduced or the lazy resolver stops
 * replaying the click.
 */

const test = require('node:test');
const assert = require('node:assert/strict');

const { renderEpisodeView, buildPage, settle } = require('./helpers/render.js');

const PLAYER = 'https://abyssplayer.com/sRiYyQmRE';

// Real theme.js arms setInterval()s; close every window so the test process can
// exit instead of waiting on those timers.
const openDoms = [];
test.afterEach(() => {
  while (openDoms.length) {
    try { openDoms.pop().window.close(); } catch { /* ignore */ }
  }
});

/** Server list used by every case: one resolved active tab, two lazy tabs. */
function episodeFixture() {
  return {
    episode: {
      hash: 'jojos-bizarre-adventure-4x8',
      title: 'Six Bullets Appears (2)',
      number: 'S4 E8',
      animeSlug: 'jojos-bizarre-adventure',
      backLabel: 'Back to Series',
      player_source: PLAYER,
      servers: [
        { name: 'HydraX', active: true, url: 'https://animedekho.tv/player.php?url=AAA', source: PLAYER, resolved: true },
        { name: 'Vidmoly', active: false, url: 'https://animedekho.tv/player.php?url=BBB', source: 'https://animedekho.tv/player.php?url=BBB', resolved: false },
        { name: 'Voe', active: false, url: 'https://animedekho.tv/player.php?url=CCC', source: 'https://animedekho.tv/player.php?url=CCC', resolved: false },
      ],
    },
  };
}

/** The direct player each lazy wrapper resolves to. */
const RESOLVED = {
  'https://animedekho.tv/player.php?url=BBB': 'https://vidmoly.biz/embed-bp61adhetdjb.html',
  'https://animedekho.tv/player.php?url=CCC': 'https://voe.sx/e/e/t7nyud2ejgys',
};

/**
 * Render the view (plus the real jQuery + theme.js), install a fetch stub for
 * /player/server-src, and load the document so all inline scripts bind.
 */
async function boot(fixture) {
  const html = renderEpisodeView(fixture);
  const dom = buildPage(html);
  const win = dom.window;
  openDoms.push(dom);

  const requested = [];
  win.fetch = (url) => {
    requested.push(String(url));
    const m = /[?&]url=([^&]+)/.exec(String(url));
    const target = m ? decodeURIComponent(m[1]) : '';
    const src = RESOLVED[target] || null;
    return Promise.resolve({ json: () => Promise.resolve({ ok: src !== null, src }) });
  };

  await settle();
  return { dom, win, requested };
}

/** The buttons in DOM order. */
function buttons(win) {
  return Array.from(win.document.querySelectorAll('.srv-btn'));
}

/** Read the live iframe src. */
function frameSrc(win) {
  return win.document.getElementById('at-player-v2').src;
}

/** Simulate a user click that also fires the capture/bubble listeners. */
function click(win, btn) {
  btn.dispatchEvent(new win.MouseEvent('click', { bubbles: true, cancelable: true }));
}

test('a lazy server resolves its wrapper and then switches the player', async () => {
  const { win, requested } = await boot(episodeFixture());

  const voe = buttons(win).find((b) => b.textContent.trim() === 'Voe');
  click(win, voe);
  await settle();

  assert.ok(
    requested.some((u) => u.includes('player.php%3Furl%3DCCC') || u.includes('player.php?url=CCC')),
    'the unresolved tab must ask /player/server-src for its wrapper',
  );
  assert.equal(frameSrc(win), RESOLVED['https://animedekho.tv/player.php?url=CCC'], 'the player must load the resolved src');
  assert.equal(voe.getAttribute('data-resolved'), '1', 'the tab is marked resolved after one lookup');
});

test('switching again right after a switch still works when the iframe never fires load', async () => {
  const { win } = await boot(episodeFixture());

  const [hydrax, vidmoly, voe] = buttons(win);
  assert.equal(frameSrc(win), PLAYER, 'starts on the active server');

  // First switch: Vidmoly (lazy -> resolved -> replayed click).
  click(win, vidmoly);
  await settle();
  assert.equal(frameSrc(win), RESOLVED['https://animedekho.tv/player.php?url=BBB']);

  // The iframe is deliberately NOT sent a `load` event — this is exactly the
  // real-world case (blocked / aborted frames) that used to leave the lock
  // armed. A second switch must still go through.
  click(win, voe);
  await settle();
  assert.equal(
    frameSrc(win),
    RESOLVED['https://animedekho.tv/player.php?url=CCC'],
    'the second switch must not be swallowed by a stale switching lock',
  );

  // And back again, still without any load event.
  click(win, hydrax);
  await settle();
  assert.equal(frameSrc(win), PLAYER, 'switching back to an earlier server works too');
});

test('clicking the already-shown server does not reload the frame', async () => {
  const { win } = await boot(episodeFixture());

  const hydrax = buttons(win).find((b) => b.textContent.trim() === 'HydraX');
  const before = frameSrc(win);
  click(win, hydrax);
  await settle();
  assert.equal(frameSrc(win), before, 'the active tab keeps the same src (no redundant reload)');
});

test('the view no longer ships a switching lock', () => {
  const html = renderEpisodeView(episodeFixture());
  assert.ok(!/switching\s*=/.test(html), 'no global switching lock in the shipped script');
  assert.ok(!/armTimeout/.test(html), 'no lock timeout machinery in the shipped script');
});

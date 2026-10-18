'use strict';

/**
 * Shared harness for the watch-page player tests.
 *
 * The server-tab switching behaviour lives in an inline <script> inside
 * app/Views/watch/episode.php. There is no build step, so these tests load
 * the real view file, render it through PHP (with a stubbed provider) and run
 * the emitted inline script inside a jsdom document. That exercises the exact
 * bytes that ship to the browser rather than a copy that can drift.
 */

const { execFileSync } = require('node:child_process');
const path = require('node:path');
const { JSDOM, VirtualConsole } = require('jsdom');

const ROOT = path.resolve(__dirname, '..', '..');
const VIEW = path.join(ROOT, 'app', 'Views', 'watch', 'episode.php');

/**
 * Render app/Views/watch/episode.php to HTML by evaluating it with PHP.
 *
 * The view expects a handful of globals ($episode, $servers, Config, View).
 * Config::get is stubbed and the Config/View classes are defined only if the
 * real autoloader did not already provide them.
 *
 * @param {{episode: object, servers?: object[]}} ctx
 * @returns {string} rendered HTML
 */
function renderEpisodeView(ctx) {
  const payload = Buffer.from(JSON.stringify(ctx)).toString('base64');

  const bootstrap = [
    '$ctx = json_decode(base64_decode(getenv("AT_CTX")), true);',
    '$episode = $ctx["episode"];',
    '$servers = $ctx["servers"] ?? ($episode["servers"] ?? []);',
    'if (!class_exists("Config")) {',
    '  class Config {',
    '    public static function get($k, $d = null) { return $k === "provider" ? getenv("AT_PROVIDER") ?: "animedekho" : $d; }',
    '  }',
    '}',
    'if (!class_exists("View")) {',
    '  class View {',
    '    public static function escape($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }',
    '    public static function hurl($u) { return (string) $u; }',
    '  }',
    '}',
    'require getenv("AT_VIEW");',
  ].join('\n');

  const code = bootstrap;

  return execFileSync('php', ['-d', 'error_reporting=E_ALL & ~E_DEPRECATED', '-r', code], {
    env: { ...process.env, AT_CTX: payload, AT_VIEW: VIEW },
    cwd: ROOT,
    encoding: 'utf8',
    maxBuffer: 16 * 1024 * 1024,
  });
}

/**
 * Build a jsdom document from rendered view HTML.
 *
 * jsdom does not implement navigation, so assigning iframe.src would warn and
 * not update the property. We install a tiny src accessor on the specific
 * frame element so the switch code observes the assignment (mirroring a real
 * browser).
 */
function buildDom(html, opts = {}) {
  const url = opts.url || 'http://animeetadka.xo.je/ep/jojos-bizarre-adventure-4x8?type=episode';
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', () => {}); // "navigation not implemented" is expected

  const dom = new JSDOM(html, {
    url,
    runScripts: 'dangerously',
    pretendToBeVisual: true,
    virtualConsole,
    beforeParse: opts.beforeParse,
  });

  const frame = dom.window.document.getElementById('at-player-v2');
  if (frame) {
    let srcValue = frame.getAttribute('src') || '';
    Object.defineProperty(frame, 'src', {
      configurable: true,
      get() { return srcValue; },
      set(v) { srcValue = String(v); frame.setAttribute('src', srcValue); },
    });
  }
  return dom;
}

const fs = require('node:fs');
const JQUERY_SRC = fs.readFileSync(path.join(ROOT, 'public', 'assets', 'js', 'vendor', 'jquery.min.js'), 'utf8');
const THEME_SRC = fs.readFileSync(path.join(ROOT, 'public', 'assets', 'js', 'core', 'theme.js'), 'utf8');

/**
 * Integration boot: the real view markup PLUS the real jQuery and the real
 * theme.js (which owns the actual iframe-switch delegation). This is the exact
 * script set a visitor's browser runs, so a regression in either script is
 * caught. `theme.js` is appended after the view markup, matching the layout.
 */
function buildPage(html, opts = {}) {
  const withTheme = opts.withTheme !== false;
  const full = withTheme
    ? html + '\n<script>' + JQUERY_SRC + '\n</script>\n<script>' + THEME_SRC + '\n</script>'
    : html;

  const dom = buildDom(full, opts);

  // The inline view script binds on DOMContentLoaded; jsdom fires it during
  // construction for a full document, but dispatch defensively when needed.
  const win = dom.window;
  if (win.document.readyState !== 'complete') {
    win.document.dispatchEvent(new win.Event('DOMContentLoaded'));
  }
  return dom;
}

/** Let queued microtasks/macrotasks drain. */
async function settle(ms = 0) {
  await new Promise((r) => setTimeout(r, ms));
  await Promise.resolve();
  await Promise.resolve();
}

module.exports = { renderEpisodeView, buildDom, buildPage, settle, ROOT, VIEW };

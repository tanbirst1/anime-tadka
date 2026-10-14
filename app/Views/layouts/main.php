<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::escape($title ?? Config::get('site_name')) ?></title>
    <meta name="description" content="<?= View::escape($description ?? 'Watch and Download Hindi Dubbed Anime online for free.') ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= View::escape(Config::get('base_url')) ?>/">
    <meta property="og:title" content="<?= View::escape($title ?? Config::get('site_name') . ' - Watch & Download Hindi Dubbed Anime For Free') ?>">
    <meta property="og:description" content="<?= View::escape($description ?? 'Stream your favorite anime in Hindi, Tamil, Telugu & English. High-quality WEB-DL episodes with daily updates and no registration required.') ?>">
    <meta property="og:image" content="<?= View::escape(Config::get('base_url') . Config::get('main_image')) ?>">
    <meta property="og:image:width" content="1376">
    <meta property="og:image:height" content="768">
    <meta property="og:site_name" content="<?= View::escape(Config::get('site_name')) ?>">
    <meta property="og:locale" content="en_US">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= View::escape($title ?? Config::get('site_name') . ' - Watch & Download Hindi Dubbed Anime For Free') ?>">
    <meta name="twitter:description" content="<?= View::escape($description ?? 'Stream your favorite anime in Hindi, Tamil, Telugu & English. High-quality WEB-DL episodes with daily updates and no registration required.') ?>">
    <meta name="twitter:image" content="<?= View::escape(Config::get('base_url') . Config::get('main_image')) ?>">
    <link rel="icon" href="<?= View::escape(Config::get('icon')) ?>" type="image/png">

    <link rel="stylesheet" href="/assets/css/main-bundle.css">
    <?php if (str_contains((string)($bodyClass ?? ''), 'single-ep')): ?><link rel="stylesheet" href="/assets/css/pages/episode.css"><?php endif; ?>
    <script>window.__AT_PROVIDER__="<?= strtolower(api_provider()) ?>"; window.__AT_PROVIDER_LIST__=["animetadka","anikoto","toonstream","animedekho"];</script>
</head>
<body class="<?= View::escape($bodyClass ?? '') ?>" data-ad-timer-enabled="<?= !empty($adTimer['enabled']) ? '1' : '0' ?>" data-ad-timer-minutes="<?= (int)($adTimer['minutes'] ?? 0) ?>">
    <!-- SVG Sprite (hidden) -->
    <svg style="display:none" xmlns="http://www.w3.org/2000/svg">
        <symbol id="icon-view-all" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="icon-folder" viewBox="0 0 24 24"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="12 6 12 12 16 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
        <symbol id="icon-trash" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><line x1="10" y1="11" x2="10" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="14" y1="11" x2="14" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
        <symbol id="icon-check-mode" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><polyline points="22 4 12 14.01 9 11.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    </svg>

    <!-- Header -->
    <header class="main-header">
        <div class="header-inner">
            <div class="header-left">
                <div class="ak-hamburger-btn" id="sidebar-toggle" onclick="openAkSidebar()">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="6" x2="20" y2="6"></line>
                        <line x1="4" y1="12" x2="20" y2="12"></line>
                        <line x1="4" y1="18" x2="14" y2="18"></line>
                    </svg>
                </div>
                <div class="site-logo">
                    <a href="<?= View::hurl('/home') ?>">
<img src="<?= View::escape(Config::get('logo')) ?>" alt="<?= View::escape(Config::get('site_name')) ?>">
                    </a>
                </div>
            </div>
            <div class="header-actions">
                <button id="shuffleBtn" class="shuffle-btn" aria-label="Random Anime">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 18h1.4c1.3 0 2.5-.6 3.3-1.7l6.1-8.6c.7-1.1 2-1.7 3.3-1.7H22"></path>
                        <path d="m18 2 4 4-4 4"></path>
                        <path d="M2 6h1.9c1.5 0 2.9.9 3.6 2.2"></path>
                        <path d="M22 18h-5.9c-1.3 0-2.6-.7-3.3-1.8l-.5-.8"></path>
                        <path d="m18 14 4 4-4 4"></path>
                    </svg>
                </button>
                <div id="sa-status-container" class="sa-status-box unverified-active" data-hours="24" data-home="/skip-ads">
                    <div id="sa-status-trigger" class="sa-fixed-icon is-warning" data-last="0">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="sa-status-svg">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <g class="sa-icon-content"><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></g>
                        </svg>
                    </div>
                    <div id="sa-status-tooltip" class="sa-fixed-tooltip">
                        <div class="sa-fixed-tt-inner">
                            <div class="sa-fixed-tt-close" id="sa-tt-close-btn">×</div>
                            <div class="sa-tt-label">Skip Ads Status</div>
                            <div class="sa-tt-status-header">
                                <div class="sa-tt-icon-circle bg-red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg></div>
                                <h2 class="sa-tt-status-title unverified-text">Not Verified</h2>
                            </div>
                            <p class="sa-tt-explanation">Verification required to remove advertisements.</p>
                            <div class="sa-dynamic-area"><a href="/skip-ads" class="sa-tt-action-btn">VERIFY NOW</a></div>
                        </div>
                    </div>
                </div>
                <div class="search-btn" id="toggle-search-overlay">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
            </div>
        </div>
    </header>

    <!-- Search Overlay -->
    <div class="search-overlay" id="search-popup">
        <div class="search-overlay-bg" id="close-search-bg"></div>
        <div class="search-modal-box">
            <div class="search-input-wrap">
                <svg class="search-input-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.3-4.3"></path>
                </svg>
                <input type="text" id="live-search-input" placeholder="Type to find anime..." autocomplete="off">
                <div class="search-loader" id="search-spinner"></div>
            </div>
            <div id="search-history-box" class="search-history-container" style="display:none;">
                <div class="history-head">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>Recent Searches</span>
                </div>
                <div id="history-items-list"></div>
            </div>
            <div id="search-results-box" class="sexy-scroll"></div>
        </div>
    </div>

    <!-- Mobile Sidebar -->
    <div id="at-backdrop" class="ak-menu-backdrop" onclick="closeAkSidebar()"></div>
    <div id="at-mobile-sidebar" class="ak-sidebar-wrapper">
        <div class="ak-sidebar-header">
            <div class="ak-sidebar-logo">
                <a href="<?= View::hurl('/home') ?>" style="display: flex; align-items: center; text-decoration: none;">
                    <img src="<?= View::escape(Config::get('logo')) ?>" alt="<?= View::escape(Config::get('site_name')) ?>" style="max-width: 180px; max-height: 45px; width: auto; height: auto; object-fit: contain;">
                </a>
            </div>
            <div class="ak-sidebar-close" id="sidebar-close" onclick="closeAkSidebar()">&times;</div>
        </div>
        <nav class="ak-sidebar-nav">
            <a href="/app" class="ak-single-link">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                <span>App</span>
            </a>
            <?php
            // Load menu data for non-animetadka providers
            $menuData = null;
            if (!api_is_animetadka()) {
                require_once ROOT_PATH . '/app/Helpers/ProviderAdapters.php';
                $menuData = api_menu_data();
            }
            ?>
            <?php if (api_is_animetadka()): ?>
            <!-- Animetadka: original hardcoded menu -->
            <a href="/type/series/" class="ak-single-link">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                <span>Series</span>
            </a>
            <a href="/type/movies/" class="ak-single-link">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                <span>Movies</span>
            </a>
            <details class="ak-native-drop" name="sidebar-acc">
                <summary class="ak-drop-trigger"><div class="ak-drop-left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                    <span>Languages</span></div>
                    <svg class="ak-drop-caret" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary><div class="ak-drop-content">
                    <a href="/lang/bengali/" class="ak-sub-link">Bengali</a><a href="/lang/chinese/" class="ak-sub-link">Chinese</a><a href="/lang/english/" class="ak-sub-link">English</a><a href="/lang/french/" class="ak-sub-link">French</a><a href="/lang/hindi/" class="ak-sub-link">Hindi</a><a href="/lang/japanese/" class="ak-sub-link">Japanese</a><a href="/lang/kanada/" class="ak-sub-link">Kanada</a><a href="/lang/kannada/" class="ak-sub-link">Kannada</a><a href="/lang/korean/" class="ak-sub-link">Korean</a><a href="/lang/malayalam/" class="ak-sub-link">Malayalam</a><a href="/lang/marathi/" class="ak-sub-link">Marathi</a><a href="/lang/tamil/" class="ak-sub-link">Tamil</a><a href="/lang/telugu/" class="ak-sub-link">Telugu</a>
                </div>
            </details>
            <details class="ak-native-drop" name="sidebar-acc">
                <summary class="ak-drop-trigger"><div class="ak-drop-left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="7" y1="10" x2="17" y2="10"></line><line x1="7" y1="14" x2="14" y2="14"></line></svg>
                    <span>Subtitles</span></div>
                    <svg class="ak-drop-caret" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary><div class="ak-drop-content">
                    <a href="/sub/english/" class="ak-sub-link">English</a><a href="/sub/hindi/" class="ak-sub-link">Hindi</a>
                </div>
            </details>
            <details class="ak-native-drop" name="sidebar-acc">
                <summary class="ak-drop-trigger"><div class="ak-drop-left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"></path><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
                    <span>Studios</span></div>
                    <svg class="ak-drop-caret" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary><div class="ak-drop-content">
                    <a href="/studio/airtel-anime-booth/" class="ak-sub-link">Airtel Anime Booth</a><a href="/studio/amazon-prime-video/" class="ak-sub-link">Amazon Prime Video</a><a href="/studio/ani-one-india/" class="ak-sub-link">Ani-One India</a><a href="/studio/anime-times/" class="ak-sub-link">Anime Times</a><a href="/studio/cartoon-network/" class="ak-sub-link">Cartoon Network</a><a href="/studio/crunchyroll/" class="ak-sub-link">Crunchyroll</a><a href="/studio/disney/" class="ak-sub-link">Disney</a><a href="/studio/disney-channel/" class="ak-sub-link">DISNEY CHANNEL</a><a href="/studio/etv-bal-bharat/" class="ak-sub-link">ETV Bal Bharat</a><a href="/studio/fan-dub/" class="ak-sub-link">Fan Dub</a><a href="/studio/hungama-tv/" class="ak-sub-link">Hungama TV</a><a href="/studio/jio-cinema/" class="ak-sub-link">JIO CINEMA</a><a href="/studio/kidzone-plus/" class="ak-sub-link">Kidzone Plus</a><a href="/studio/marvel-hq/" class="ak-sub-link">Marvel HQ</a><a href="/studio/muse-india/" class="ak-sub-link">Muse India</a><a href="/studio/mx-player/" class="ak-sub-link">MX Player</a><a href="/studio/netflix/" class="ak-sub-link">Netflix</a><a href="/studio/nickelodeon/" class="ak-sub-link">Nickelodeon</a><a href="/studio/sony-yay/" class="ak-sub-link">Sony Yay</a><a href="/studio/sonyliv/" class="ak-sub-link">SonyLiv</a><a href="/studio/zee-cafe/" class="ak-sub-link">ZEE CAFE</a>
                </div>
            </details>
            <details class="ak-native-drop" name="sidebar-acc">
                <summary class="ak-drop-trigger"><div class="ak-drop-left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Genres</span></div>
                    <svg class="ak-drop-caret" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary><div class="ak-drop-content">
                    <a href="/genre/action/" class="ak-sub-link">Action</a><a href="/genre/adventure/" class="ak-sub-link">Adventure</a><a href="/genre/animation/" class="ak-sub-link">Animation</a><a href="/genre/award-winning/" class="ak-sub-link">Award Winning</a><a href="/genre/comedy/" class="ak-sub-link">Comedy</a><a href="/genre/crime/" class="ak-sub-link">Crime</a><a href="/genre/drama/" class="ak-sub-link">Drama</a><a href="/genre/ecchi/" class="ak-sub-link">Ecchi</a><a href="/genre/family/" class="ak-sub-link">Family</a><a href="/genre/fantasy/" class="ak-sub-link">Fantasy</a><a href="/genre/girls-love/" class="ak-sub-link">Girls Love</a><a href="/genre/gourmet/" class="ak-sub-link">Gourmet</a><a href="/genre/hentai/" class="ak-sub-link">Hentai</a><a href="/genre/horror/" class="ak-sub-link">Horror</a><a href="/genre/kids/" class="ak-sub-link">Kids</a><a href="/genre/mystery/" class="ak-sub-link">Mystery</a><a href="/genre/politics/" class="ak-sub-link">Politics</a><a href="/genre/romance/" class="ak-sub-link">Romance</a><a href="/genre/sci-fi/" class="ak-sub-link">Sci-Fi</a><a href="/genre/science-fiction/" class="ak-sub-link">Science Fiction</a><a href="/genre/slice-of-life/" class="ak-sub-link">Slice of Life</a><a href="/genre/sports/" class="ak-sub-link">Sports</a><a href="/genre/supernatural/" class="ak-sub-link">Supernatural</a><a href="/genre/suspense/" class="ak-sub-link">Suspense</a><a href="/genre/thriller/" class="ak-sub-link">Thriller</a><a href="/genre/war/" class="ak-sub-link">War</a>
                </div>
            </details>
            <?php else: ?>
            <!-- Dynamic menu for anikoto / toonstream / animedekho -->
            <?php if (!empty($menuData['links'])): ?>
            <?php foreach ($menuData['links'] as $l): ?>
            <a href="<?= View::escape(View::hurl($l['path'] ?? '/catalog/' . $l['slug'])) ?>" class="ak-single-link">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span><?= View::escape($l['name']) ?></span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($menuData['types'])): ?>
            <details class="ak-native-drop" name="sidebar-acc">
                <summary class="ak-drop-trigger"><div class="ak-drop-left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                    <span>Types</span></div>
                    <svg class="ak-drop-caret" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary><div class="ak-drop-content">
                    <?php foreach ($menuData['types'] as $t): ?>
                    <a href="<?= View::escape(View::hurl('/catalog/' . $t['slug'])) ?>" class="ak-sub-link"><?= View::escape($t['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php endif; ?>
            <?php if (!empty($menuData['genres'])): ?>
            <details class="ak-native-drop" name="sidebar-acc">
                <summary class="ak-drop-trigger"><div class="ak-drop-left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Genres</span></div>
                    <svg class="ak-drop-caret" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary><div class="ak-drop-content">
                    <?php foreach ($menuData['genres'] as $g): ?>
                    <a href="<?= View::escape(View::hurl('/catalog/genre/' . $g['slug'])) ?>" class="ak-sub-link"><?= View::escape($g['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php endif; ?>
            <?php if (!empty($menuData['years'])): ?>
            <details class="ak-native-drop" name="sidebar-acc">
                <summary class="ak-drop-trigger"><div class="ak-drop-left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Years</span></div>
                    <svg class="ak-drop-caret" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary><div class="ak-drop-content">
                    <?php foreach ($menuData['years'] as $y): ?>
                    <a href="/hentai/year/<?= View::escape($y['slug']) ?>" class="ak-sub-link"><?= View::escape($y['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php endif; ?>
            <?php if (!empty($menuData['statuses'])): ?>
            <details class="ak-native-drop" name="sidebar-acc">
                <summary class="ak-drop-trigger"><div class="ak-drop-left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Status</span></div>
                    <svg class="ak-drop-caret" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary><div class="ak-drop-content">
                    <?php foreach ($menuData['statuses'] as $s): ?>
                    <a href="<?= View::escape(View::hurl('/catalog/season/' . $s['slug'])) ?>" class="ak-sub-link"><?= View::escape($s['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
            <?php endif; ?>
            <?php endif; ?>
            <details class="ak-native-drop" name="sidebar-acc">
                <summary class="ak-drop-trigger"><div class="ak-drop-left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    <span>Important Pages</span></div>
                    <svg class="ak-drop-caret" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary><div class="ak-drop-content">
                    <a href="/contact-us/" class="ak-sub-link">Contact Us</a><a href="/credits/" class="ak-sub-link">Credits</a><a href="/dmca/" class="ak-sub-link">DMCA</a><a href="/privacy-policy/" class="ak-sub-link">Privacy Policy</a>
                </div>
            </details>
        </nav>
    </div>

    <!-- Main Content -->
    <main class="site-main">
        <?= $content ?>
    </main>

    <!-- Footer -->
    <footer class="glass-footer">
        <div class="footer-inner-pro">
            <div class="footer-az-section">
                <span class="footer-label-pro">Browse by Letter</span>
                <div class="az-grid-pro">
                    <a href="<?= View::hurl('/letter/0-9/') ?>" class="az-link-pro">0-9</a>
                    <?php foreach (range('a','z') as $letter): ?>
                    <a href="<?= View::hurl('/letter/' . $letter . '/') ?>" class="az-link-pro"><?= strtoupper($letter) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <nav class="footer-nav-pages">
                <a href="/contact-us/">Contact Us</a>
                <span class="nav-sep">|</span>
                <a href="/credits/">Credits</a>
                <span class="nav-sep">|</span>
                <a href="/dmca/">DMCA</a>
                <span class="nav-sep">|</span>
                <a href="/privacy-policy/">Privacy Policy</a>
            </nav>
            <div class="footer-disclaimer-pro">
                <p><strong><?= View::escape(Config::get('site_name')) ?></strong> does not store any files on own server. We only index links from internet which are hosted on third-party services. <strong>We Index Links Just Like Google.</strong></p>
            </div>
            <div class="footer-copy-pro">
                <p style="color: #ffffff;">&copy; 2026 <span style="color: #ffffff;"><?= View::escape(Config::get('site_name')) ?></span>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- App Promo Floating -->
    <div id="at-floating-stack"<?= (($bodyClass ?? '') === 'page-search') ? ' style="display:none;"' : '' ?>>
        <?php $adTimer = Config::get('ad_timer', ['enabled' => false, 'minutes' => 5]); ?>
        <?php if (str_contains((string)($bodyClass ?? ''), 'single-ep') && !empty($adTimer['enabled']) && ($adTimer['minutes'] ?? 0) > 0): ?>
        <div id="sa-friendly-trial-banner" class="at-app-promo-wrapper sa-trial-banner-pro show-promo" style="display: flex;">
            <div class="sa-trial-header">
                <div class="sa-trial-icon-wrap">
                    <svg style="width: 22px; height: 22px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <div class="sa-trial-text-container">
                    <div class="sa-trial-title">Ad-Free Watch Time</div>
                    <div class="sa-trial-desc">Verify in <span id="sa-big-timer" class="sa-trial-timer">--:--</span></div>
                </div>
            </div>
            <button id="sa-verify-now-btn" class="sa-verify-btn-pro" data-bound="true">
                <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Verify Now
            </button>
        </div>
        <div id="at-app-promo" class="at-app-promo-wrapper" style="display: none;">
            <a href="/app" class="at-promo-link">
                <div class="at-promo-icon-wrap">
                    <svg class="at-android-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.523 15.3414C17.523 15.3414 17.021 15.3414 16.519 15.3414C16.519 14.8394 16.519 14.3374 16.519 13.8354C17.021 13.8354 17.523 13.8354 17.523 13.8354C17.523 14.3374 17.523 14.8394 17.523 15.3414ZM7.481 15.3414C7.481 15.3414 6.979 15.3414 6.477 15.3414C6.477 14.8394 6.477 14.3374 6.477 13.8354C6.979 13.8354 7.481 13.8354 7.481 13.8354C7.481 14.3374 7.481 14.8394 7.481 15.3414ZM16.418 9.9214L17.924 7.2114C18.024 7.0104 17.924 6.7094 17.723 6.6094C17.522 6.5094 17.221 6.6094 17.121 6.8104L15.615 9.4204C14.51 8.9184 13.305 8.6174 12 8.6174C10.695 8.6174 9.49 8.9184 8.385 9.4204L6.879 6.8104C6.779 6.6094 6.478 6.5094 6.277 6.6094C6.076 6.7094 5.976 7.0104 6.076 7.2114L7.582 9.9214C4.671 11.6284 2.663 14.6404 2 18.2544L22 18.2544C21.337 14.6404 19.329 11.6284 16.418 9.9214Z"></path>
                    </svg>
                </div>
                <div class="at-promo-text" style="flex: 1; min-width: max-content; overflow: visible;">
                    <span class="at-promo-title" style="display: inline-block; white-space: nowrap; min-width: max-content; flex-shrink: 0; max-width: none; overflow: visible; word-break: keep-all;">Get <?= View::escape(Config::get('site_name')) ?> App</span>
                    <span class="at-promo-subtitle">No Ads • Android Only</span>
                </div>
            </a>
            <button id="at-promo-dismiss" class="at-promo-close" aria-label="Dismiss" data-bound="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <?php else: ?>
        <div id="at-app-promo" class="at-app-promo-wrapper show-promo" style="display: flex;">
            <a href="/app" class="at-promo-link">
                <div class="at-promo-icon-wrap">
                    <svg class="at-android-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.523 15.3414C17.523 15.3414 17.021 15.3414 16.519 15.3414C16.519 14.8394 16.519 14.3374 16.519 13.8354C17.021 13.8354 17.523 13.8354 17.523 13.8354C17.523 14.3374 17.523 14.8394 17.523 15.3414ZM7.481 15.3414C7.481 15.3414 6.979 15.3414 6.477 15.3414C6.477 14.8394 6.477 14.3374 6.477 13.8354C6.979 13.8354 7.481 13.8354 7.481 13.8354C7.481 14.3374 7.481 14.8394 7.481 15.3414ZM16.418 9.9214L17.924 7.2114C18.024 7.0104 17.924 6.7094 17.723 6.6094C17.522 6.5094 17.221 6.6094 17.121 6.8104L15.615 9.4204C14.51 8.9184 13.305 8.6174 12 8.6174C10.695 8.6174 9.49 8.9184 8.385 9.4204L6.879 6.8104C6.779 6.6094 6.478 6.5094 6.277 6.6094C6.076 6.7094 5.976 7.0104 6.076 7.2114L7.582 9.9214C4.671 11.6284 2.663 14.6404 2 18.2544L22 18.2544C21.337 14.6404 19.329 11.6284 16.418 9.9214Z"></path>
                    </svg>
                </div>
                <div class="at-promo-text" style="flex: 1; min-width: max-content; overflow: visible;">
                    <span class="at-promo-title" style="display: inline-block; white-space: nowrap; min-width: max-content; flex-shrink: 0; max-width: none; overflow: visible; word-break: keep-all;">Get <?= View::escape(Config::get('site_name')) ?> App</span>
                    <span class="at-promo-subtitle">No Ads • Android Only</span>
                </div>
            </a>
            <button id="at-promo-dismiss" class="at-promo-close" aria-label="Dismiss" data-bound="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Floating Schedule Button -->
    <?php if (($bodyClass ?? '') !== 'page-search'): ?>
    <div class="sch-float-anchor" id="sch-floating-btn">
        <div class="sch-red-dot" id="sch-float-dot" style="display:none;">0</div>
        <div class="sch-bell-core" onclick="window.location.href='/schedule'">
            <div class="sch-svg-dance">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Floating Telegram Button -->
    <div id="tg-float-wrap" class="tg-float-anchor">
        <span class="tg-status-dot"></span>
        <div id="tg-float-btn" class="tg-bell-core">
            <svg viewBox="0 0 640 640" fill="currentColor">
                <path d="M320 72C183 72 72 183 72 320C72 457 183 568 320 568C457 568 568 457 568 320C568 183 457 72 320 72zM435 240.7C431.3 279.9 415.1 375.1 406.9 419C403.4 437.6 396.6 443.8 390 444.4C375.6 445.7 364.7 434.9 350.7 425.7C328.9 411.4 316.5 402.5 295.4 388.5C270.9 372.4 286.8 363.5 300.7 349C304.4 345.2 367.8 287.5 369 282.3C369.2 281.6 369.3 279.2 367.8 277.9C366.3 276.6 364.2 277.1 362.7 277.4C360.5 277.9 325.6 300.9 258.1 346.5C248.2 353.3 239.2 356.6 231.2 356.4C222.3 356.2 205.3 351.4 192.6 347.3C177.1 342.3 164.7 339.6 165.8 331C166.4 326.5 172.5 322 184.2 317.3C256.5 285.8 304.7 265 328.8 255C397.7 226.4 412 221.4 421.3 221.2C423.4 221.2 427.9 221.7 430.9 224.1C432.9 225.8 434.1 228.2 434.4 230.8C434.9 234 435 237.3 434.8 240.6z"></path>
            </svg>
        </div>
    </div>

    <!-- Telegram Overlay -->
    <div id="tg-overlay" class="tg-global-overlay"></div>

    <!-- Telegram Popup Sheet -->
    <div id="tg-popup" class="tg-full-sheet">
        <div class="tg-sheet-content">
            <button id="tg-popup-close" class="tg-x-close" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <div class="tg-elite-tag">OFFICIAL</div>
            <div class="tg-main-msg">Join our <strong style="color:#08c">Telegram Channel</strong> for the latest anime updates, download links, and exclusive content!</div>
            <div class="tg-btn-grid">
                <a id="tg-popup-join" href="<?= View::escape(Config::get("socials")["telegram"] ?? "https://t.me/+2XKc4jsHo_RlNDdl") ?>" target="_blank" class="tg-elite-link chan">
                    <div class="tg-svg-dance">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.891 8.11l-1.92 9.06c-.145.648-.529.81-.145.648l-3.028-2.23-1.461 1.408c-.162.162-.298.298-.612.298l.217-3.09 5.625-5.08c.245-.218-.054-.338-.378-.124l-6.95 4.37-2.99-.93c-.65-.204-.66-.65.135-.96l11.68-4.5c.54-.2 1.012.12 1.012.12s-.176.66-.312.93z"></path></svg>
                    </div>
                    JOIN CHANNEL
                </a>
            </div>
        </div>
    </div>
    <script src="/assets/js/vendor/jquery.min.js" defer></script>
    <script src="/assets/js/vendor/swiper-bundle.min.js" defer></script>
    <script id="anime-master-js-js-extra">
        var animeData = {"ajax_url":"/wp-admin/admin-ajax.php","home_url":"/home"};
    </script>
    <script src="/assets/js/core/theme.js" defer></script>
    <script src="/assets/js/core/app.js" defer></script>
    <script src="/assets/js/features/ad-timer-history.js" defer></script>
    <script src="/assets/js/features/history-idb.js" defer></script>
    <script>
    // Provider is single source of truth from config.php (no cookie). History already provider-aware via window.__AT_PROVIDER__.
    (function(){ try{ localStorage.removeItem('at_provider'); }catch(e){} try{ document.cookie='at_provider=;path=/;max-age=0'; }catch(e){} })();
    </script>
    <script>
    window.addEventListener('load', function() {
        var tgFloatWrap = document.getElementById('tg-float-wrap');
        var tgPopup = document.getElementById('tg-popup');
        var tgOverlay = document.getElementById('tg-overlay');
        var tgPopupClose = document.getElementById('tg-popup-close');
        var tgPopupJoin = document.getElementById('tg-popup-join');
        if (!tgFloatWrap || !tgPopup) return;

        var tgBase = <?= json_encode(Config::get("socials")["telegram"] ?? "https://t.me/+2XKc4jsHo_RlNDdl") ?>;

        tgFloatWrap.onclick = function() {
            if (tgPopupJoin) tgPopupJoin.href = tgBase;
            tgPopup.classList.add('active');
            if (tgOverlay) tgOverlay.classList.add('active');
            tgFloatWrap.style.transform = 'scale(0) translateY(50px)';
            tgFloatWrap.style.opacity = '0';
        };
        var tgClose = function() {
            tgPopup.classList.remove('active');
            if (tgOverlay) tgOverlay.classList.remove('active');
            tgFloatWrap.style.transform = '';
            tgFloatWrap.style.opacity = '';
        };
        if (tgPopupClose) tgPopupClose.onclick = tgClose;
        if (tgOverlay) tgOverlay.onclick = tgClose;
    });
    </script>
</body>
</html>

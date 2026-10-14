<?php
/**
 * Home page — main site content (rendered inside layouts/main.php <main class="site-main">)
 * Structure mirrors the original animetadka.com /home page exactly.
 */
?>
<!-- Home page CSS (landscape Recent Episodes grid) -->
<link rel="stylesheet" href="/assets/css/pages/home.css">

<!-- SVG symbols used by home sections (not defined in the main layout sprite) -->
<svg style="display:none;" xmlns="http://www.w3.org/2000/svg">
    <symbol id="icon-check-mode" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></symbol>
    <symbol id="icon-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></symbol>
</svg>

<!-- ============ 1. SPOTLIGHT CAROUSEL (Swiper) ============ -->
<div class="sa-spotlight-wrapper" style="position: relative; margin-top: 10px; margin-bottom: 35px;">
    <div class="swiper swiper-container sa-spotlight-swiper" id="sa-spotlight-content">
        <div class="swiper-wrapper">
            <?php foreach ($spotlight as $i => $item): ?>
            <div class="swiper-slide">
                <div class="sa-spotlight-card">
                    <?php if ($i === 0): ?>
                    <img src="<?= $item['image'] ?>" class="sa-card-bg" fetchpriority="high" alt="Spotlight Banner">
                    <?php else: ?>
                    <img src="<?= $item['image'] ?>" class="sa-card-bg" loading="lazy" alt="Spotlight Banner">
                    <?php endif; ?>
                    <div class="sa-card-overlay"></div>
                    <div class="sa-card-content">
                        <span class="sa-rank-tag">#<?= $i + 1 ?> Spotlight</span>
                        <?php if (!empty($item['logo']) && $item['logo'] !== $item['image']): ?>
                            <img src="<?= View::escape($item['logo']) ?>" class="sa-logo-img" alt="Logo">
                        <?php elseif (!empty($item['title'])): ?>
                            <h2 class="sa-logo-text" style="font-size:28px;font-weight:900;color:#fff;line-height:1.2;text-shadow:0 2px 12px rgba(0,0,0,0.8);margin:10px 0;max-width:90%"><?= View::escape($item['title']) ?></h2>
                        <?php else: ?>
                            <img src="<?= View::escape($item['image']) ?>" class="sa-logo-img" alt="Logo">
                        <?php endif; ?>
                        <div class="sa-meta-row">
                            <span class="sa-badge"><?= View::escape($item['audio'] ?? '') ?></span>
                            <span class="sa-genres"><?= View::escape($item['genres'] ?? '') ?></span>
                        </div>
                        <p class="sa-description"><?= View::escape($item['description'] ?? '') ?></p>
                        <div class="sa-actions">
                            <a href="<?= View::hurl('/') ?><?= View::escape($item['slug']) ?>" class="sa-btn-main">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg> Watch Now
                            </a>
                            <a href="<?= View::hurl('/') ?><?= View::escape($item['slug']) ?>" class="sa-btn-alt">
                                Details <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="sa-spotlight-controls">
            <div class="swiper-pagination sa-dots-container swiper-pagination-clickable swiper-pagination-bullets swiper-pagination-horizontal"></div>
            <div class="sa-arrow-group">
                <div class="sa-nav-btn sa-prev" tabindex="0" role="button" aria-label="Previous slide">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"></path></svg>
                </div>
                <div class="sa-nav-btn sa-next" tabindex="0" role="button" aria-label="Next slide">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"></path></svg>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ 2. CONTINUE WATCHING (History slider) ============ -->
<section id="continue-watching-section" class="home-section-pro" style="display:none;">
    <div class="section-head-pro">
        <div class="head-left"><h2 class="head-title" id="history-section-title">Continue Watching</h2></div>
        <div class="history-actions">
            <button id="btn-history-select" class="history-tool-btn" title="Select Items">
                <svg><use href="#icon-check-mode"></use></svg>
            </button>
            <button id="btn-history-clear" class="history-tool-btn" title="Clear All History">
                <svg><use href="#icon-trash"></use></svg>
            </button>
        </div>
    </div>
    <div id="history-grid" class="home-grid"></div>
    <div class="history-buttons-footer" style="text-align:center; padding: 0 !important; margin: 0 !important; line-height: 0;">
        <div id="history-view-all-wrap" style="display:none;">
            <a href="/history" class="view-all-btn" style="padding: 12px 40px; font-size: 13px; display: inline-flex;">
                <span>VIEW ALL HISTORY →</span>
            </a>
        </div>
    </div>
</section>

<!-- ============ 3. LATEST UPDATES / LATEST ANIMES GRID ============ -->
<!-- Hidden inside provider sections (e.g. /hentai/): their own Recent/Hentai
     sections below replace it, and its latest-episode data is unreliable there. -->
<?php $latestEpisodesSection = null; ?>
<?php if (empty($GLOBALS['__link_base'])): ?>
<?php foreach ($extraSections as $sec): ?>
<?php if (stripos($sec['title'] ?? '', 'episode') !== false && $latestEpisodesSection === null): ?>
<?php $latestEpisodesSection = $sec; ?>
<?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($latestEpisodesSection)): ?>
<!-- ============ 2b. LATEST EPISODES (landscape episode grid) ============ -->
<section class="home-section-pro" id="latest-episodes-anchor">
    <div class="section-head-pro">
        <div class="head-left">
            <div class="title-with-icon" style="display: flex; align-items: center; gap: 10px;">
                <svg class="flame-pro" style="width: 24px; height: 24px; color:#9b59b6; filter: drop-shadow(0 0 6px rgba(155, 89, 182, 0.4));"><use href="#icon-clock"></use></svg>
                <h2 class="head-title"><?= View::escape($latestEpisodesSection['title']) ?></h2>
            </div>
        </div>
        <div class="section-footer-view-all" style="margin: 0;">
            <a href="<?= View::hurl('/all') ?>" class="view-all-btn" style="padding: 10px 22px; font-size: 12px; display: inline-flex;">
                <span>VIEW ALL →</span>
            </a>
        </div>
    </div>
    <div class="home-grid ep-landscape-grid">
        <?php foreach (array_slice($latestEpisodesSection['items'] ?? [], 0, 12) as $ep): ?>
        <div class="anime-card ep-landscape-card">
            <a href="<?= View::hurl('/') ?><?= View::escape($ep['slug']) ?>">
                <div class="ep-thumb">
                    <?php if (!empty($ep['episode'])): ?><span class="badge se-badge"><?= View::escape($ep['episode']) ?></span><?php endif; ?>
                    <?php if (!empty($ep['audio'])): ?><span class="badge meta-badge"><?= View::escape($ep['audio']) ?></span><?php endif; ?>
                    <div class="play-hover"><div class="play-btn-circle"><svg viewBox="0 0 640 640" fill="currentColor"><path d="M187.2 100.9C174.8 94.1 159.8 94.4 147.6 101.6C135.4 108.8 128 121.9 128 136L128 504C128 518.1 135.5 531.2 147.6 538.4C159.7 545.6 174.8 545.9 187.2 539.1L523.2 355.1C536 348.1 544 334.6 544 320C544 305.4 536 291.9 523.2 284.9L187.2 100.9z"></path></svg></div></div>
                    <img src="<?= View::escape($ep['image']) ?>" class="attachment-medium size-medium wp-post-image" alt="<?= View::escape($ep['title']) ?>" loading="lazy">
                </div>
                <div class="card-details"><h2 class="card-title"><?= View::escape($ep['title']) ?></h2></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<section class="home-section-pro" id="latest-updates-anchor">
    <div class="section-head-pro" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px; align-items: center;">
        <div class="head-left">
            <div class="title-with-icon" style="display: flex; align-items: center; gap: 10px;">
                <svg class="flame-pro" style="width: 24px; height: 24px; color:#9b59b6; filter: drop-shadow(0 0 6px rgba(155, 89, 182, 0.4));"><use href="#icon-clock"></use></svg>
                <h2 class="head-title">Latest Animes</h2>
            </div>
        </div>
        <div class="at-text-tabs">
            <button class="at-txt-tab active" data-target="all">All</button>
            <button class="at-txt-tab" data-target="series">Series</button>
            <?php if (Config::get('provider') !== 'watchhentai'): ?>
            <button class="at-txt-tab" data-target="movies">Movies</button>
            <?php endif; ?>
        </div>
    </div>
    <div id="latest-updates-grid" style="position: relative;">
        <div id="tab-loader" class="premium-tab-loader">
            <div class="heartbeat-loader">
                <svg viewBox="0 0 150 50">
                    <path d="M 0 25 L 20 25 L 25 15 L 30 35 L 35 25 L 50 25 L 55 5 L 65 45 L 75 15 L 80 25 L 100 25 L 105 18 L 110 30 L 115 25 L 150 25"></path>
                </svg>
            </div>
        </div>
        <div class="home-grid skeleton-grid" id="lat-skel-wrapper" style="display: none !important;">
            <?php for ($i = 0; $i < 18; $i++): ?>
            <div class="skeleton-card"><div class="skel-poster"></div><div class="skel-text"></div></div>
            <?php endfor; ?>
        </div>
        <div class="home-grid lat-target-grid" id="lat-grid-all" style="display: grid !important;">
            <?php foreach ($latestAll as $ep): ?>
            <div class="anime-card">
                <a href="<?= View::hurl('/') ?><?= View::escape($ep['slug']) ?>">
                    <div class="poster-box">
                        <?php if (!empty($ep['episode'] ?? '')): ?><span class="badge se-badge"><?= View::escape($ep['episode']) ?></span><?php endif; ?>
                        <?php if (!empty($ep['audio'] ?? '')): ?><span class="badge meta-badge"><?= View::escape($ep['audio']) ?></span><?php endif; ?><?php if (!empty($ep['year'] ?? '')): ?><?php if (!empty($ep['year'] ?? '')): ?><?php endif; ?><?php endif; ?>
                        <div class="play-hover"><div class="play-btn-circle"><svg viewBox="0 0 640 640" fill="currentColor"><path d="M187.2 100.9C174.8 94.1 159.8 94.4 147.6 101.6C135.4 108.8 128 121.9 128 136L128 504C128 518.1 135.5 531.2 147.6 538.4C159.7 545.6 174.8 545.9 187.2 539.1L523.2 355.1C536 348.1 544 334.6 544 320C544 305.4 536 291.9 523.2 284.9L187.2 100.9z"></path></svg></div></div>
                        <img src="<?= $ep['image'] ?>" class="attachment-medium size-medium wp-post-image" alt="<?= View::escape($ep['title']) ?>" loading="lazy">
                    </div>
                    <div class="card-details"><h2 class="card-title"><?= View::escape($ep['title']) ?></h2></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="home-grid lat-target-grid" id="lat-grid-series" style="display: none !important;">
            <?php foreach ($latestSeries as $ep): ?>
            <div class="anime-card">
                <a href="<?= View::hurl('/') ?><?= View::escape($ep['slug']) ?>">
                    <div class="poster-box">
                        <?php if (!empty($ep['episode'] ?? '')): ?><span class="badge se-badge"><?= View::escape($ep['episode']) ?></span><?php endif; ?>
                        <?php if (!empty($ep['audio'] ?? '')): ?><span class="badge meta-badge"><?= View::escape($ep['audio']) ?></span><?php endif; ?><?php if (!empty($ep['year'] ?? '')): ?><?php if (!empty($ep['year'] ?? '')): ?><?php endif; ?><?php endif; ?>
                        <div class="play-hover"><div class="play-btn-circle"><svg viewBox="0 0 640 640" fill="currentColor"><path d="M187.2 100.9C174.8 94.1 159.8 94.4 147.6 101.6C135.4 108.8 128 121.9 128 136L128 504C128 518.1 135.5 531.2 147.6 538.4C159.7 545.6 174.8 545.9 187.2 539.1L523.2 355.1C536 348.1 544 334.6 544 320C544 305.4 536 291.9 523.2 284.9L187.2 100.9z"></path></svg></div></div>
                        <img src="<?= $ep['image'] ?>" class="attachment-medium size-medium wp-post-image" alt="<?= View::escape($ep['title']) ?>" loading="lazy">
                    </div>
                    <div class="card-details"><h2 class="card-title"><?= View::escape($ep['title']) ?></h2></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (Config::get('provider') !== 'watchhentai'): ?>
        <div class="home-grid lat-target-grid" id="lat-grid-movies" style="display: none !important;">
            <?php foreach ($latestMovies as $ep): ?>
            <div class="anime-card">
                <a href="<?= View::hurl('/') ?><?= View::escape($ep['slug']) ?>">
                    <div class="poster-box">
                        <?php if (!empty($ep['episode'] ?? '')): ?><span class="badge se-badge"><?= View::escape($ep['episode']) ?></span><?php endif; ?>
                        <?php if (!empty($ep['audio'] ?? '')): ?><span class="badge meta-badge"><?= View::escape($ep['audio']) ?></span><?php endif; ?><?php if (!empty($ep['year'] ?? '')): ?><?php if (!empty($ep['year'] ?? '')): ?><?php endif; ?><?php endif; ?>
                        <div class="play-hover"><div class="play-btn-circle"><svg viewBox="0 0 640 640" fill="currentColor"><path d="M187.2 100.9C174.8 94.1 159.8 94.4 147.6 101.6C135.4 108.8 128 121.9 128 136L128 504C128 518.1 135.5 531.2 147.6 538.4C159.7 545.6 174.8 545.9 187.2 539.1L523.2 355.1C536 348.1 544 334.6 544 320C544 305.4 536 291.9 523.2 284.9L187.2 100.9z"></path></svg></div></div>
                        <img src="<?= $ep['image'] ?>" class="attachment-medium size-medium wp-post-image" alt="<?= View::escape($ep['title']) ?>" loading="lazy">
                    </div>
                    <div class="card-details"><h2 class="card-title"><?= View::escape($ep['title']) ?></h2></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="section-footer-view-all">
        <a href="<?= View::hurl('/all') ?>" class="view-all-bottom-btn">
            <span>View All Animes</span>
            <svg><use href="#icon-view-all"></use></svg>
        </a>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($extraSections)): ?>
<?php foreach ($extraSections as $sec): ?>
<?php if ($latestEpisodesSection !== null && $sec === $latestEpisodesSection) continue; // pinned above the Latest Animes grid ?>
<?php if (array_key_exists('standalone', $sec) && empty($sec['standalone'])) continue; // its items live in the Latest Animes grid ?>
<?php $isEpisodesLandscape = ($sec['layout'] ?? '') === 'episodes-landscape'; ?>
<section class="home-section-pro">
    <div class="section-head-pro">
        <div class="head-left">
            <div class="title-with-icon" style="display: flex; align-items: center; gap: 10px;">
                <svg class="flame-pro" style="width: 24px; height: 24px; color:#9b59b6;"><use href="#icon-clock"></use></svg>
                <h2 class="head-title"><?= View::escape($sec['title']) ?></h2>
            </div>
        </div>
    </div>
    <?php if ($isEpisodesLandscape): ?>
    <!-- Landscape: 16:9 episode cards (Recent Episodes) -->
    <div class="home-grid ep-landscape-grid">
        <?php foreach (array_slice($sec['items'] ?? [], 0, 12) as $ep): ?>
        <div class="anime-card ep-landscape-card">
            <a href="<?= View::hurl('/') ?><?= View::escape($ep['slug']) ?>">
                <div class="ep-thumb">
                    <?php if (!empty($ep['episode'])): ?>
                    <span class="badge se-badge"><?= View::escape($ep['episode']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ep['audio'])): ?>
                    <span class="badge meta-badge"><?= View::escape($ep['audio']) ?></span><?php if (!empty($ep['year'] ?? '')): ?><?php if (!empty($ep['year'] ?? '')): ?><?php endif; ?><?php endif; ?>
                    <?php endif; ?>
                    <div class="play-hover"><div class="play-btn-circle"><svg viewBox="0 0 640 640" fill="currentColor"><path d="M187.2 100.9C174.8 94.1 159.8 94.4 147.6 101.6C135.4 108.8 128 121.9 128 136L128 504C128 518.1 135.5 531.2 147.6 538.4C159.7 545.6 174.8 545.9 187.2 539.1L523.2 355.1C536 348.1 544 334.6 544 320C544 305.4 536 291.9 523.2 284.9L187.2 100.9z"></path></svg></div></div>
                    <img src="<?= View::escape($ep['image']) ?>" class="attachment-medium size-medium wp-post-image" alt="<?= View::escape($ep['title']) ?>" loading="lazy">
                </div>
                <div class="card-details"><h2 class="card-title"><?= View::escape($ep['title']) ?></h2></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Portrait: default poster grid (unchanged) -->
    <div class="home-grid">
        <?php foreach (array_slice($sec['items'] ?? [], 0, 12) as $ep): ?>
        <div class="anime-card">
            <a href="<?= View::hurl('/') ?><?= View::escape($ep['slug']) ?>">
                <div class="poster-box">
                    <?php if (!empty($ep['episode'] ?? '')): ?><span class="badge se-badge"><?= View::escape($ep['episode']) ?></span><?php endif; ?>
                    <?php if (!empty($ep['audio'] ?? '')): ?><span class="badge meta-badge"><?= View::escape($ep['audio']) ?></span><?php endif; ?><?php if (!empty($ep['year'] ?? '')): ?><?php if (!empty($ep['year'] ?? '')): ?><?php endif; ?><?php endif; ?>
                    <div class="play-hover"><div class="play-btn-circle"><svg viewBox="0 0 640 640" fill="currentColor"><path d="M187.2 100.9C174.8 94.1 159.8 94.4 147.6 101.6C135.4 108.8 128 121.9 128 136L128 504C128 518.1 135.5 531.2 147.6 538.4C159.7 545.6 174.8 545.9 187.2 539.1L523.2 355.1C536 348.1 544 334.6 544 320C544 305.4 536 291.9 523.2 284.9L187.2 100.9z"></path></svg></div></div>
                    <img src="<?= View::escape($ep['image']) ?>" class="attachment-medium size-medium wp-post-image" alt="<?= View::escape($ep['title']) ?>" loading="lazy">
                </div>
                <div class="card-details"><h2 class="card-title"><?= View::escape($ep['title']) ?></h2></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php endforeach; ?>
<?php endif; ?>

<!-- ============ 4. TOP 10 ANIME (with tabs) ============ -->
<?php if (!empty($topDay) || !empty($topWeek) || !empty($topMonth)): ?>
<section class="home-section-pro top-anime-section">
    <div id="sa-top10-unique-header" class="section-head-pro" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px; align-items: center;">
        <div class="head-left">
            <div class="title-with-icon" style="display: flex; align-items: center; gap: 10px;">
                <svg class="flame-pro" style="width: 24px; height: 24px; color: #9b59b6; filter: drop-shadow(0 0 6px rgba(155, 89, 182, 0.4));"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                <h2 class="head-title" style="white-space: nowrap; margin: 0;"><?= !empty($GLOBALS['__link_base']) ? 'Popular' : 'Top Animes' ?></h2>
            </div>
        </div>
        <?php if (empty($GLOBALS['__link_base'])): ?>
        <div class="at-top-tabs">
            <button class="at-t-tab active" data-range="day">Day</button>
            <button class="at-t-tab" data-range="week">Week</button>
            <button class="at-t-tab" data-range="month">Month</button>
        </div>
        <?php endif; ?>
    </div>
    <div id="top-10-wrapper">
        <div class="top-10-grid" id="top-grid-day">
            <?php foreach ($topDay as $anime): ?>
            <a href="<?= View::hurl('/') ?><?= View::escape($anime['slug']) ?>" class="top-10-item">
                <div class="top-number"><?= str_pad((int)$anime['rank'], 2, '0', STR_PAD_LEFT) ?></div>
                <img src="<?= $anime['image'] ?>" class="top-poster" loading="lazy" alt="poster">
                <div class="top-info">
                    <h3><?= View::escape($anime['title']) ?></h3>
                    <div class="top-meta">
                        <div class="top-tags"><span style="background: rgba(155, 89, 182, 0.15); color: #9b59b6;"><?= View::escape($anime['rating'] ?? '') ?></span></div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="top-10-grid" id="top-grid-week" style="display: none !important;">
            <?php foreach ($topWeek as $anime): ?>
            <a href="<?= View::hurl('/') ?><?= View::escape($anime['slug']) ?>" class="top-10-item">
                <div class="top-number"><?= str_pad((int)$anime['rank'], 2, '0', STR_PAD_LEFT) ?></div>
                <img src="<?= $anime['image'] ?>" class="top-poster" loading="lazy" alt="poster">
                <div class="top-info">
                    <h3><?= View::escape($anime['title']) ?></h3>
                    <div class="top-meta">
                        <div class="top-tags"><span style="background: rgba(155, 89, 182, 0.15); color: #9b59b6;"><?= View::escape($anime['rating'] ?? '') ?></span></div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="top-10-grid" id="top-grid-month" style="display: none !important;">
            <?php foreach ($topMonth as $anime): ?>
            <a href="<?= View::hurl('/') ?><?= View::escape($anime['slug']) ?>" class="top-10-item">
                <div class="top-number"><?= str_pad((int)$anime['rank'], 2, '0', STR_PAD_LEFT) ?></div>
                <img src="<?= $anime['image'] ?>" class="top-poster" loading="lazy" alt="poster">
                <div class="top-info">
                    <h3><?= View::escape($anime['title']) ?></h3>
                    <div class="top-meta">
                        <div class="top-tags"><span style="background: rgba(155, 89, 182, 0.15); color: #9b59b6;"><?= View::escape($anime['rating'] ?? '') ?></span></div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

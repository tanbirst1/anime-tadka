<?php
/**
 * Search page — mirrors the original animetadka.com search page exactly:
 * spotlight hero + continue watching + latest animes grid.
 */
$spotlight = $spotlight ?? [];
$latest = $latest ?? [];
?>
<div class="sa-spotlight-wrapper" style="position: relative; margin-top: 10px; margin-bottom: 35px;">
    <div class="swiper swiper-container sa-spotlight-swiper swiper-fade" id="sa-spotlight-content" data-start-at-zero>
        <div class="swiper-wrapper">
            <?php foreach ($spotlight as $i => $slide): ?>
            <div class="swiper-slide" role="group" aria-label="<?= $i + 1 ?> / <?= count($spotlight) ?>" data-swiper-slide-index="<?= $i ?>">
                <div class="sa-spotlight-card">
                    <img src="<?= $slide['bg'] ?>" class="sa-card-bg" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> alt="Spotlight Banner">
                    <div class="sa-card-overlay"></div>
                    <div class="sa-card-content">
                        <span class="sa-rank-tag"><?= View::escape($slide['rank']) ?></span>
                        <?php if (!empty($slide['logo']) && $slide['logo'] !== ($slide['bg'] ?? '')): ?>
                        <img src="<?= View::escape($slide['logo']) ?>" class="sa-logo-img" alt="Logo">
                        <?php elseif (!empty($slide['title'])): ?>
                            <h2 class="sa-logo-text" style="font-size:28px;font-weight:900;color:#fff;line-height:1.2;text-shadow:0 2px 12px rgba(0,0,0,0.8);margin:10px 0;max-width:90%"><?= View::escape($slide['title']) ?></h2>
                        <?php else: ?>
                            <img src="<?= View::escape($slide['bg'] ?? '') ?>" class="sa-logo-img" alt="Logo">
                        <?php endif; ?>
                        <div class="sa-meta-row">
                            <span class="sa-badge"><?= View::escape($slide['badge']) ?></span>
                            <span class="sa-genres"><?= View::escape($slide['genres']) ?></span>
                        </div>
                        <p class="sa-description"><?= View::escape($slide['desc']) ?></p>
                        <div class="sa-actions">
                            <a href="<?= View::escape(View::typeHref('/', $slide['type'] ?? 'series', trim((string) ($slide['watch'] ?? ''), '/'))) ?>" class="sa-btn-main">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg> Watch Now
                            </a>
                            <a href="<?= View::escape(View::typeHref('/', $slide['type'] ?? 'series', trim((string) ($slide['details'] ?? ''), '/'))) ?>" class="sa-btn-alt"> Details
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="sa-spotlight-controls">
            <div class="swiper-pagination sa-dots-container swiper-pagination-clickable swiper-pagination-bullets swiper-pagination-horizontal">
                <?php foreach ($spotlight as $i => $slide): ?>
                <span class="swiper-pagination-bullet<?= $i === 0 ? ' swiper-pagination-bullet-active' : '' ?>" tabindex="0" role="button" aria-label="Go to slide <?= $i + 1 ?>"></span>
                <?php endforeach; ?>
            </div>
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

<section id="continue-watching-section" class="home-section-pro" style="display: block;">
    <div class="section-head-pro">
        <div class="head-left">
            <h2 class="head-title" id="history-section-title">Continue Watching</h2>
        </div>
        <div class="history-actions">
            <button id="btn-history-select" class="history-tool-btn" title="Select Items">
                <svg><use href="#icon-check-mode"></use></svg>
            </button>
            <button id="btn-history-clear" class="history-tool-btn" title="Clear All History">
                <svg><use href="#icon-trash"></use></svg>
            </button>
        </div>
    </div>
    <div id="history-grid" class="history-slider-track">
        <div class="anime-card history-card" data-id="11898">
            <div class="history-select-overlay">
                <div class="custom-check-wrapper">
                    <input type="checkbox" class="history-checkbox" value="11898">
                    <span class="check-mark-styled"></span>
                </div>
            </div>
            <a href="/naruto-shippuden-hindi-dubbed-multi-audio-bluray-episodes-watch-online-download/" class="history-link">
                <div class="poster-box">
                    <span class="badge se-badge">S17-E368</span>
                    <div class="play-hover">
                        <div class="play-btn-circle"><svg viewBox="0 0 640 640" fill="currentColor"><path d="M187.2 100.9C174.8 94.1 159.8 94.4 147.6 101.6C135.4 108.8 128 121.9 128 136L128 504C128 518.1 135.5 531.2 147.6 538.4C159.7 545.6 174.8 545.9 187.2 539.1L523.2 355.1C536 348.1 544 334.6 544 320C544 305.4 536 291.9 523.2 284.9L187.2 100.9z"></path></svg></div>
                    </div>
                    <img src="/assets/images/common/naruto.jpg" alt="Naruto Shippūden">
                    <div class="history-date-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <span>18 Aug 2026</span>
                    </div>
                </div>
                <div class="card-details">
                    <h2 class="card-title">Naruto Shippūden</h2>
                </div>
            </a>
        </div>
    </div>
    <div class="history-buttons-footer" style="text-align:center; padding: 0 !important; margin: 0 !important; line-height: 0;">
        <div id="history-view-all-wrap" style="display:none;">
            <a href="/history" class="view-all-btn" style="padding: 12px 40px; font-size: 13px; display: inline-flex;">
                <span>VIEW ALL HISTORY →</span>
            </a>
        </div>
    </div>
</section>

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
        <div class="home-grid lat-target-grid" id="lat-grid-all" style="display: grid !important;">
            <?php foreach ($latest as $item): ?>
            <div class="anime-card">
                <a href="<?= View::escape(View::typeHref('/', $item['type'] ?? '', $item['slug'] ?? '')) ?>">
                    <div class="poster-box">
                        <span class="badge se-badge"><?= View::escape($item['episode'] ?? '') ?></span>
                        <span class="badge meta-badge"><?= View::escape($item['audio'] ?? '') ?></span>
                        <div class="play-hover"><div class="play-btn-circle"><svg viewBox="0 0 640 640" fill="currentColor"><path d="M187.2 100.9C174.8 94.1 159.8 94.4 147.6 101.6C135.4 108.8 128 121.9 128 136L128 504C128 518.1 135.5 531.2 147.6 538.4C159.7 545.6 174.8 545.9 187.2 539.1L523.2 355.1C536 348.1 544 334.6 544 320C544 305.4 536 291.9 523.2 284.9L187.2 100.9z"></path></svg></div></div>
                        <img src="<?= $item['image'] ?>" class="attachment-medium size-medium wp-post-image" alt="<?= View::escape($item['title'] ?? '') ?>" loading="lazy">
                    </div>
                    <div class="card-details"><h2 class="card-title"><?= View::escape($item['title'] ?? '') ?></h2></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
/**
 * Language listing page — mirrors the original animetadka.com language page exactly.
 */
?>
<div class="pro-section-header-v2">
    <div class="header-main-content">
        <div class="icon-aura-v2">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
        </div>
        <div class="title-text-group-v2">
            <span class="dynamic-pro-label"><?= View::escape($genreLabel ?? 'Language Collection') ?></span>
            <h2 class="main-pro-title-v2"><?= View::escape($genreTitle ?? '') ?></h2>
        </div>
        <div class="tab-triggers" style="margin-left:auto;">
            <?php require ROOT_PATH . '/app/Views/partials/type-toggle.php'; ?>
        </div>
    </div>
    <div class="pro-full-separator"></div>
</div>

<div class="home-grid">
    <?php foreach ($anime as $item): ?>
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
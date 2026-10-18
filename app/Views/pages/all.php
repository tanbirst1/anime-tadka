<?php
/**
 * All Anime listing page — mirrors the original animetadka.com all page exactly.
 */
?>
<svg style="display:none;" xmlns="http://www.w3.org/2000/svg">
    <symbol id="icon-grid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></symbol>
</svg>

<section class="home-section-pro">
    <div class="section-head-pro" style="padding-top: 30px;">
        <div class="head-left">
            <div class="title-with-icon" style="display: flex; align-items: center; gap: 10px;">
                <svg class="flame-pro" style="width: 24px; height: 24px; color:var(--accent); filter: drop-shadow(0 0 6px rgba(155, 89, 182, 0.4));"><use href="#icon-grid"></use></svg>
                <h2 class="head-title">All Animes</h2>
            </div>
        </div>
    </div>
    <div class="home-grid">
        <?php foreach ($anime as $item): ?>
        <div class="anime-card">
            <a href="<?= View::escape(View::typeHref('/', $item['type'] ?? '', $item['slug'] ?? '')) ?>">
                <div class="poster-box">
                    <span class="badge se-badge"><?= View::escape($item['episode'] ?? '') ?></span>
                    <span class="badge meta-badge"><?= View::escape($item['audio'] ?? '') ?></span>
                    <div class="play-hover"><div class="play-btn-circle"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg></div></div>
                    <img src="<?= $item['image'] ?>" class="attachment-medium size-medium wp-post-image" alt="<?= View::escape($item['title'] ?? '') ?>" loading="lazy">
                </div>
                <div class="card-details"><h2 class="card-title"><?= View::escape($item['title'] ?? '') ?></h2></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php require ROOT_PATH . '/app/Views/partials/pagination.php'; ?>
</section>
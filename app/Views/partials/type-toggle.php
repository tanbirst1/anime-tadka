<?php
/**
 * Series/Movies toggle — mirrors the original site's tab-triggers.
 *
 * Expects: $toggleBase (page-1 URL, e.g. '/catalog'),
 *          $activeType ('series' | 'movies' | ''),
 *          $showToggle (bool, default true).
 */
$toggleBase = rtrim((string) ($toggleBase ?? ''), '/');
$activeType = (string) ($activeType ?? '');
$showToggle = ($showToggle ?? true);
if (!$showToggle) return;
?>
<div class="tab-triggers">
    <a href="<?= View::escape($toggleBase . '/series') ?>" class="tab-btn <?= $activeType === 'series' ? 'active' : '' ?>">Series</a>
    <a href="<?= View::escape($toggleBase . '/movie') ?>" class="tab-btn <?= $activeType === 'movies' ? 'active' : '' ?>">Movies</a>
</div>

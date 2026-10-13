<?php
/**
 * Schedule page — weekly anime schedule with day tabs, cards, countdowns.
 * Expects $days: array of 7 day arrays, each with 'name', 'date', 'cards'.
 */
$dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$todayIndex = (int) date('w'); // 0=Sunday
?>

<!-- Schedule CSS -->
<link rel="stylesheet" href="/assets/css/pages/schedule.css">

<div class="sa-sch-wrapper">
    <!-- Day Navigation -->
    <div class="sa-sch-nav-wrapper">
        <button class="sa-nav-arrow left-arrow" id="nav-arrow-left">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <div class="sa-sch-nav-scroll" id="sa-nav-scroll">
            <div class="sa-sch-day-nav">
                <?php foreach ($days as $i => $day): ?>
                <button class="day-btn <?= $i === $todayIndex ? 'active' : '' ?>" data-day="<?= $i ?>">
                    <span class="d-name"><?= View::escape($day['name'] ?? $dayNames[$i]) ?></span>
                    <span class="d-date"><?= View::escape($day['date'] ?? '') ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <button class="sa-nav-arrow right-arrow" id="nav-arrow-right">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
    </div>

    <!-- Timezone Notice -->
    <div class="sa-timezone-notice">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
        All schedule times are in India Standard Time (IST)
    </div>

    <!-- Reminders Drawer -->
    <div class="sa-reminders-wrap" id="sa-reminders-master" style="display:none;">
        <button class="sa-reminders-trigger" id="reminders-toggle">
            <div class="trigger-left">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px; height:20px; color:#f59e0b;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                <span class="trigger-text">My Watchlist Reminders</span>
            </div>
            <div class="trigger-right">
                <span id="reminder-count-dot" class="red-dot-drawer" style="display:none;"></span>
                <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg>
            </div>
        </button>
        <div class="sa-reminders-content" id="reminders-list-container" style="display:none;"></div>
    </div>

    <!-- Day Panes -->
    <div class="sa-sch-main-content">
        <?php foreach ($days as $i => $day): ?>
        <div class="sa-day-pane <?= $i === $todayIndex ? 'active' : '' ?>" id="pane-<?= $i ?>">
            <div class="sa-sch-grid">
                <?php if (empty($day['cards'])): ?>
                <div class="no-data">No scheduled anime for this day.</div>
                <?php else: ?>
                <?php foreach ($day['cards'] as $card): ?>
                <?php
                    $status = $card['status'] ?? 'pending';
                    $url = $card['url'] ?? '';
                    $onclick = $url !== '' ? "window.location.href='" . View::escape($url) . "'" : '';
                    $pid = $card['pid'] ?? '';
                    $season = $card['season'] ?? 1;
                    $episode = $card['episode'] ?? 1;
                    $batch = $card['batch'] ?? 1;
                    $time24 = $card['time24'] ?? '';
                    $timeBadge = $card['timeBadge'] ?? '';
                    $languages = $card['languages'] ?? '';
                    $cardTitle = $card['title'] ?? '';
                    $seasonLabel = $card['seasonLabel'] ?? 'Season 1';
                    $episodeLabel = $card['episodeLabel'] ?? 'Episode 1';
                    $image = $card['image'] ?? '';
                ?>
                <div class="sa-sch-card <?= View::escape($status) ?>"
                     data-pid="<?= View::escape($pid) ?>"
                     data-s="<?= (int)$season ?>"
                     data-e="<?= (int)$episode ?>"
                     data-b="<?= (int)$batch ?>"
                     data-url="<?= View::escape($url) ?>"
                     <?= $onclick ? "onclick=\"" . View::escape($onclick) . "\"" : '' ?>>
                    <div class="card-bg" style="background-image: url('<?= View::escape($image) ?>');"></div>
                    <div class="card-overlay"></div>
                    <?php if ($status !== 'uploaded'): ?>
                    <button class="sa-bell-btn" onclick="toggleReminder(<?= View::escape($pid) ?>, <?= (int)$season ?>, <?= (int)$episode ?>, <?= (int)$batch ?>, '<?= View::escape($cardTitle) ?>', '<?= View::escape($image) ?>', '<?= View::escape($timeBadge) ?>', this, '<?= $i ?>', '<?= View::escape($time24) ?>', event)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    </button>
                    <?php endif; ?>
                    <div class="card-content">
                        <div class="card-top">
                            <span class="sa-time-badge">
                                <?php if ($status === 'uploaded'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:12px; height:12px; margin-right:4px;"><circle cx="12" cy="12" r="10"></circle></svg>LIVE
                                <?php else: ?>
                                <?= View::escape($timeBadge) ?>
                                <?php endif; ?>
                            </span>
                            <span class="sa-lang-badge"><?= View::escape($languages) ?></span>
                        </div>
                        <h3 class="card-title"><?= View::escape($cardTitle) ?></h3>
                        <p class="card-meta"><?= View::escape($seasonLabel) ?> — <?= View::escape($episodeLabel) ?></p>
                        <div class="card-footer">
                            <?php if ($status === 'uploaded'): ?>
                            <a href="<?= View::escape($url) ?>" class="watch-btn" onclick="event.stopPropagation();">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px; height:16px;"><path d="M8 5v14l11-7z"/></svg>
                                WATCH NOW
                            </a>
                            <?php elseif ($status === 'delayed'): ?>
                            <div class="delayed-label">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px; height:16px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                DELAYED
                            </div>
                            <?php elseif ($status === 'pending'): ?>
                            <div class="countdown-wrap" data-time="<?= View::escape($time24) ?>" data-day="<?= $i ?>">
                                <span class="cd-text">Airing in</span>
                                <span class="cd-timer"><span class="sa-dots-loader"><span></span></span></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="sa-card-progress <?= $status === 'uploaded' ? 'full' : '' ?>">
                        <div class="sa-progress-bar"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Warning Note -->
    <div class="wn-bx">
        <div class="wn-ic">!</div>
        <div class="wn-tx">Please note that the timings provided above are taken from internet. They <span class="wn-hl">do not necessarily indicate the exact time</span> of indexing for each title.</div>
    </div>
</div>

<!-- Schedule JS -->
<script src="/assets/js/pages/schedule.js"></script>
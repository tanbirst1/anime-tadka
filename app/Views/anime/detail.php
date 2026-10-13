<?php $anime = $anime ?? []; ?>
<div class="cinematic-wrapper">
    <div class="hero-bg" style="background-image: url('<?= $anime['poster'] ?>');"></div>
    <div class="hero-overlay"></div>
    <div class="anime-content-area">

        <div class="hero-flex">
            <div class="poster-container">
                <img src="<?= $anime['poster'] ?>" class="main-poster" alt="<?= View::escape($anime['title']) ?>">
            </div>
            <div class="hero-details">
                <h1 class="entry-title-pro"><?= View::escape($anime['title']) ?></h1>
                <?php if (!empty($anime['genres'])): ?>
                <div class="genre-pills">
                    <?php foreach ($anime['genres'] as $genre): ?>
                    <a href="/genre/<?= strtolower(str_replace(' ', '-', $genre)) ?>"><?= View::escape($genre) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass-info-card">
            <div class="info-quote-line">
                <?php if (!empty($anime['type'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">TYPE:</span>
                    <span class="value-pro"><?= View::escape($anime['type']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['status'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">STATUS:</span>
                    <span class="value-pro"><?= View::escape($anime['status']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['episodes'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">EPISODES:</span>
                    <span class="value-pro"><?= View::escape($anime['episodes']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['aired'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">AIRED:</span>
                    <span class="value-pro"><?= View::escape($anime['aired']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['duration'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">DURATION:</span>
                    <span class="value-pro"><?= View::escape($anime['duration']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['quality'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">QUALITY:</span>
                    <span class="value-pro"><?= View::escape($anime['quality']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['audio'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">AUDIO:</span>
                    <span class="value-pro neon-orange"><?= View::escape($anime['audio']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['subtitle'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">SUBTITLE:</span>
                    <span class="value-pro neon-purple" style="color: #bf81d6 !important;"><?= View::escape($anime['subtitle']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['studio'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">STUDIO:</span>
                    <span class="value-pro neon-green"><?= View::escape($anime['studio']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['encode'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">ENCODE:</span>
                    <span class="value-pro"><?= View::escape($anime['encode']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['rating'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">RATING:</span>
                    <span class="value-pro"><?= View::escape($anime['rating']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($anime['views'])): ?>
                <div class="meta-row-pro">
                    <span class="label-pro">VIEWS:</span>
                    <span class="value-pro"><?= View::escape($anime['views']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($anime['description'])): ?>
            <div class="synopsis-pro">
                <h3>STORYLINE</h3>
                <p><?= nl2br(View::escape($anime['description'])) ?></p>
            </div>
            <?php endif; ?>

            <div class="sa-share-box">
                <div class="sa-share-inner">
                    <div class="sa-share-label">
                        <svg class="at-share-draw" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                        <span class="txt-pc">Share This Anime</span>
                        <span class="txt-mob">Share</span>
                    </div>
                    <div class="sa-share-grid">
                        <?php $shareUrl = Config::get('base_url') . '/' . $anime['slug']; ?>
                        <?php $shareText = $anime['title'] . ' - Watch Online & Download on ' . Config::get('site_name'); ?>
                        <a href="https://t.me/share/url?url=<?= urlencode($shareUrl) ?>&amp;text=<?= urlencode($shareText) ?>" target="_blank" class="sa-share-icon tg" title="Telegram" onclick="saShareDelay(event, this.href)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.891 8.11l-1.92 9.06c-.145.648-.529.81-.145.648l-3.028-2.23-1.461 1.408c-.162.162-.298.298-.612.298l.217-3.09 5.625-5.08c.245-.218-.054-.338-.378-.124l-6.95 4.37-2.99-.93c-.65-.204-.66-.65.135-.96l11.68-4.5c.54-.2 1.012.12 1.012.12s-.176.66-.312.93z"></path></svg>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" class="sa-share-icon fb" title="Facebook" onclick="saShareDelay(event, this.href)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                        </a>
                        <a href="https://api.whatsapp.com/send?text=<?= urlencode($shareText . ' ' . $shareUrl) ?>" target="_blank" class="sa-share-icon wa" title="WhatsApp" onclick="saShareDelay(event, this.href)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"></path></svg>
                        </a>
                        <a href="fb-messenger://share/?link=<?= urlencode($shareUrl) ?>" class="sa-share-icon ms" title="Messenger" onclick="saShareDelay(event, this.href)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.31 0 0 4.97 0 11.11c0 3.5 1.74 6.62 4.47 8.58.23.17.37.44.37.73v2.98c0 .48.51.78.93.54l3.32-1.85c.18-.1.38-.15.58-.15h2.33c6.69 0 12-4.97 12-11.11S18.69 0 12 0zm1.18 14.65l-2.07-2.22-4.04 2.22 4.44-4.73 2.11 2.22 4-2.22-4.44 4.73z"></path></svg>
                        </a>
                        <a href="sms:?body=<?= urlencode($shareText . ' ' . $shareUrl) ?>" class="sa-share-icon sm" title="SMS" onclick="saShareDelay(event, this.href)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        </a>
                        <button class="sa-share-icon cp" title="Copy Link" onclick="atCopyLink('<?= $shareUrl ?>')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        </button>
                    </div>
                </div>
                <div class="sa-share-shimmer"></div>
            </div>

            <?php $malId = $anime['external_ids']['mal'] ?? null; ?>
            <?php $tmdbId = $anime['external_ids']['tmdb'] ?? null; ?>
            <?php if ($malId || $tmdbId): ?>
            <div class="sa-external-card">
                <h3 class="sa-ext-title">Explore Expert Ratings, Production Intel &amp; Future Arc Updates</h3>
                <div class="sa-ext-grid">
                    <?php if ($malId): ?>
                    <a href="https://myanimelist.net/anime/<?= View::escape($malId) ?>" target="_blank" class="sa-ext-btn mal" rel="noopener noreferrer">
                        <img src="https://cdn.myanimelist.net/images/favicon.ico" alt="MAL" class="sa-btn-logo">
                        <span>MyAnimeList</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($tmdbId): ?>
                    <a href="https://www.themoviedb.org/tv/<?= View::escape($tmdbId) ?>" target="_blank" class="sa-ext-btn ext-movie-db" rel="noopener noreferrer">
                        <img src="https://www.themoviedb.org/assets/v4/logos/v2/blue_square_2-d537fb228cf3ded904ef09b136fe3fec72548ebc1fea3fbbd1ad9e36364db38b.svg" alt="TMDb" class="sa-btn-logo">
                        <span>TheMovieDB</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($episodes) || !empty($anime['latest_episode'])): ?>
            <?php $latestEp = $anime['latest_episode']; ?>
            <?php if (empty($latestEp['slug']) && !empty($episodes)): ?>
            <?php $lastRow = $episodes[count($episodes) - 1]; ?>
            <?php $latestEp = ['slug' => $lastRow['slug'], 'label' => 'Watch Latest Episode: Ep ' . $lastRow['number']]; ?>
            <?php endif; ?>
            <?php if (!empty($latestEp['slug'])): ?>
            <div class="at-latest-ep-btn-wrap">
                <a href="<?= View::hurl('/ep/') ?><?= View::escape($latestEp['slug']) ?><?= str_contains(strtolower($latestEp['slug'] ?? ''), 'movie') ? '?type=movie' : '?type=episode' ?>" class="at-latest-ep-btn">
                    <div class="at-btn-shine-loop"></div>
                    <div class="at-btn-inner">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        <span><?= View::escape($latestEp['label']) ?></span>
                    </div>
                </a>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <div style="display: flex; justify-content: center; margin: 15px 0 20px; width: 100%; box-sizing: border-box; padding: 0 10px;">
                </div>

            <script>window.__AT_ANIME_SLUG__ = <?= json_encode($anime['slug'] ?? '') ?>;</script>
            <?php if (!empty($seasons)): ?>
            <div class="at-season-jump-wrap">
                <div class="at-season-jump-title">↓ Scroll Directly To Any Season By 1 Click/Tap ↓</div>
                <div class="at-season-nav-grid">
                    <?php foreach ($seasons as $si => $season): ?>
                    <button onclick="document.getElementById('season-target-<?= ($si + 1) ?>').scrollIntoView({behavior: 'smooth', block: 'start'});" class="at-jump-btn">
                        <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        <?= View::escape($season['title']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="season-accordion" id="anime-ep-container">
                <?php foreach ($seasons as $si => $season): ?>
                <?php if (empty($season['episodes'])) continue; ?>
                <div class="season-block" id="season-target-<?= ($si + 1) ?>">
                    <div class="season-header" style="cursor: pointer;">
                        <div class="header-left">
                            <div class="s-poster-mini"><img src="<?= $season['poster'] ?>" loading="lazy" alt="<?= View::escape($season['title']) ?>"></div>
                            <div class="s-info-text">
                                <h3><?= View::escape($season['title']) ?></h3>
                                <p><?= count($season['episodes']) ?>/<?= View::escape($season['episode_count']) ?></p>
                            </div>
                        </div>
                        <div class="header-right">
                            <svg class="arrow-svg" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" style="transition: stroke 0.3s ease, transform 0.3s ease; transform: rotate(180deg);"><path d="M6 9l6 6 6-6"></path></svg>
                        </div>
                    </div>
                    <div class="ep-list-drawer" style="display:block;">
                        <?php foreach ($season['episodes'] as $ep): ?>
                        <a href="<?= View::hurl('/ep/') ?><?= View::escape($ep['slug']) ?><?= str_contains(strtolower($ep['slug'] ?? ''), 'movie') ? '?type=movie' : '?type=episode' ?>" class="ep-row" data-hash="<?= View::escape($ep['slug']) ?>" data-anime="<?= View::escape($anime['slug'] ?? '') ?>" style="color: #fff !important;">
                            <img src="<?= !empty($ep['thumbnail']) ? $ep['thumbnail'] : $season['poster'] ?>" loading="lazy" alt="<?= View::escape($ep['title']) ?>">
                            <div class="ep-info">
                                <strong><?php if (!empty($ep['number'])): ?>Ep <?= View::escape($ep['number']) ?>: <?php endif; ?><?= View::escape($ep['title']) ?></strong>
                            </div>
                            <div class="status-action">
                                <span class="watched-badge" style="display:none">Watched</span>
                                <button class="watch-btn">Watch</button>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif (!empty($episodes)): ?>
            <?php /* Fallback: no structured seasons but episodes exist (flat list) */ ?>
            <div class="season-accordion" id="anime-ep-container">
                <div class="season-block" id="season-target-1">
                    <div class="season-header" style="cursor: pointer;">
                        <div class="header-left">
                            <div class="s-poster-mini"><img src="<?= $anime['poster'] ?>" loading="lazy" alt="Episodes"></div>
                            <div class="s-info-text">
                                <h3>Episodes</h3>
                                <p><?= count($episodes) ?>/<?= count($episodes) ?> Episodes</p>
                            </div>
                        </div>
                        <div class="header-right">
                            <svg class="arrow-svg" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" style="transition: stroke 0.3s ease, transform 0.3s ease; transform: rotate(180deg);"><path d="M6 9l6 6 6-6"></path></svg>
                        </div>
                    </div>
                    <div class="ep-list-drawer" style="display:block;">
                        <?php foreach ($episodes as $ep): ?>
                        <a href="<?= View::hurl('/ep/') ?><?= View::escape($ep['slug']) ?><?= str_contains(strtolower($ep['slug'] ?? ''), 'movie') ? '?type=movie' : '?type=episode' ?>" class="ep-row" data-hash="<?= View::escape($ep['slug']) ?>" data-anime="<?= View::escape($anime['slug'] ?? '') ?>" style="color: #fff !important;">
                            <img src="<?= !empty($ep['thumbnail']) ? $ep['thumbnail'] : $anime['poster'] ?>" loading="lazy" alt="<?= View::escape($ep['title']) ?>">
                            <div class="ep-info">
                                <strong><?php if (!empty($ep['number'])): ?>Ep <?= View::escape($ep['number']) ?>: <?php endif; ?><?= View::escape($ep['title']) ?></strong>
                            </div>
                            <div class="status-action">
                                <span class="watched-badge" style="display:none">Watched</span>
                                <button class="watch-btn">Watch</button>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($related)): ?>
<section class="reco-section">
    <div class="reco-header">
        <svg class="reco-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
        <h3>Recommended For You</h3>
    </div>
    <div class="reco-slider-container">
        <div class="reco-slider" id="recoSlider">
            <?php foreach ($related as $rel): ?>
            <div class="reco-item">
                <a href="<?= View::hurl('/') ?><?= View::escape($rel['slug']) ?>">
                    <div class="reco-poster">
                        <img src="<?= $rel['image'] ?>" loading="lazy" alt="<?= View::escape($rel['title']) ?>">
                        <div class="reco-overlay">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                        </div>
                    </div>
                    <span class="reco-title"><?= View::escape($rel['title']) ?></span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="reco-dots" id="recoDots">
        <?php foreach ($related as $i => $rel): ?>
        <div class="reco-dot <?= $i === 0 ? 'active' : '' ?>"></div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
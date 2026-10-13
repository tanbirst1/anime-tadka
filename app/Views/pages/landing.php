<?php
/**
 * Landing page body content.
 * Rendered inside layouts/landing.php (which provides <head>, body class="landing-body",
 * glass background, top bar, hero and footer).
 *
 * Data contract (from LandingController):
 *   $topSearches : array of ['term', 'url']
 *   $shareText   : string (urlencoded share text)
 *   $shareUrl    : string (urlencoded share URL)
 *   $languages   : array of ['name', 'slug', 'image']
 */
?>

<section class="landing-search-area">
    <div class="search-input-wrap-pro">
        <svg class="search-icon-pro" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input type="text" id="live-search-input" placeholder="Search your favorite anime..." autocomplete="off">
        <button type="button" id="search-submit-btn" class="search-submit-btn" aria-label="Search" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:8px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.8); transition: all 0.2s;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        </button>
        <style>
            #search-submit-btn:hover {
                background: rgba(255,255,255,0.15);
                color: #fff;
            }
            #search-submit-btn:active {
                transform: translateY(-50%) scale(0.92);
            }
        </style>
        <div class="search-loader" id="search-spinner" style="display:none;"></div>
    </div>
    <div class="sa-search-divider"></div>
    <div id="search-history-box" class="landing-history-box" style="display:none;">
        <div class="history-head">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <span>RECENT SEARCHES</span>
        </div>
        <div id="history-items-list"></div>
    </div>

    <div id="search-results-box" class="landing-results-box sexy-scroll" style="display:none;"></div>
</section>

<div class="top-searches-wrap">
    <span class="label">TOP SEARCHES:</span>
    <div class="tag-cloud">
        <?php foreach ($topSearches as $item): ?>
        <button type="button" class="top-search-tag" data-term="<?= View::escape($item['term']) ?>">
            <?= View::escape($item['term']) ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<div class="landing-footer-actions">
    <a href="/home" class="btn-enter-pro">
        <span>VIEW FULL SITE</span>
        <div class="btn-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><path d="M5 12h14M12 5l7 7-7 7"></path></svg>
        </div>
    </a>
</div>

<section class="landing-share-section">
    <div class="share-head">
        <div class="share-accent"></div>
        <div class="share-text">
            <h3>Share <?= View::escape(Config::get('site_name')) ?></h3>
            <p>Help your friends find the best anime experience</p>
        </div>
    </div>

    <div class="pro-share-options">
        <div class="social-share-grid">
            <a href="https://api.whatsapp.com/send?text=<?= $shareText ?>" target="_blank" class="share-icon-btn wa" title="WhatsApp">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.72.938 3.659 1.434 5.633 1.434h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"></path></svg>
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" class="share-icon-btn fb" title="Facebook">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
            </a>
            <a href="https://t.me/share/url?url=<?= $shareUrl ?>&text=<?= $shareText ?>" target="_blank" class="share-icon-btn tg" title="Telegram">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.891 8.11l-1.92 9.06c-.145.648-.529.81-.145.648l-3.028-2.23-1.461 1.408c-.162.162-.298.298-.612.298l.217-3.09 5.625-5.08c.245-.218-.054-.338-.378-.124l-6.95 4.37-2.99-.93c-.65-.204-.66-.65.135-.96l11.68-4.5c.54-.2 1.012.12 1.012.12s-.176.66-.312.93z"></path></svg>
            </a>
            <a href="fb-messenger://share/?link=<?= $shareUrl ?>" target="_blank" class="share-icon-btn ms" title="Messenger">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.331 0 0 4.908 0 11.058c0 3.321 1.488 6.275 3.821 8.243.201.17.319.423.319.689 0 .285-.05.86-.25 1.574-.085.3.175.58.46.495 1.385-.41 2.505-.885 3.33-1.32.203-.105.433-.13.653-.08 1.144.256 2.344.394 3.58.394 6.669 0 12-4.908 12-11.058C24 4.908 18.669 0 12 0zm1.266 14.89l-2.485-2.651-4.85 2.651 5.336-5.671 2.51 2.651 4.825-2.651-5.336 5.671z"></path></svg>
            </a>
            <a href="sms:?body=<?= $shareText ?>" class="share-icon-btn msg" title="SMS Message">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
            </a>
        </div>

        <div style="display:flex; justify-content:flex-start; margin-top:10px;">
            <button onclick="copyLandingLink()" class="glass-link" style="cursor:pointer; display:flex; align-items:center; gap:10px; border-radius:12px; font-size:12px; padding:12px 25px;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                COPY LINK
            </button>
        </div>
    </div>
</section>

<section class="landing-languages">
    <?php if ($isAnimetadka): ?>
    <h4 class="lang-title">Watch Anime in <span>Your Language</span></h4>
    <div class="lang-grid-pro">
        <?php foreach ($languages as $lang): ?>
        <a href="/lang/<?= View::escape($lang['slug']) ?>" class="lang-card-pro">
            <img src="<?= View::escape($lang['image'] ?? '/assets/images/lang-' . $lang['slug'] . '.svg') ?>" loading="lazy" alt="<?= View::escape($lang['name']) ?>">
            <div class="card-overlay">
                <span><?= View::escape($lang['name']) ?></span>
                <div class="arrow-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12h14M12 5l7 7-7 7"></path></svg></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <h4 class="lang-title">Browse by <span>Genre</span></h4>
    <div class="lang-grid-pro">
        <?php foreach ($genres as $genre): ?>
        <a href="<?= View::escape($genre['href']) ?>" class="lang-card-pro">
            <img src="<?= View::escape($genre['image']) ?>" loading="lazy" alt="<?= View::escape($genre['name']) ?>">
            <div class="card-overlay">
                <span><?= View::escape($genre['name']) ?></span>
                <div class="arrow-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12h14M12 5l7 7-7 7"></path></svg></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php
/**
 * Render the SEO / index content section.
 *
 * When the active provider returns structured intro + sections (toonstream,
 * animedekho), those are rendered dynamically.  When the content is empty
 * (animetadka default), the original hardcoded text is shown instead.
 */
$hasApiContent = !empty($indexContent['intro']) || !empty($indexContent['sections']);
$siteName = View::escape(Config::get('site_name'));
?>

<div style="font-family: 'Inter', 'Montserrat', sans-serif; text-align: left; line-height: 1.8; color: #94a3b8; margin-top: 60px;">

<?php if ($hasApiContent): ?>

    <?php foreach ($indexContent['intro'] ?? [] as $para): ?>
    <p style="font-size: 15px; margin-bottom: 30px;"><?= View::escape($para) ?></p>
    <?php endforeach; ?>

    <?php $sectionNum = 0; ?>
    <?php foreach ($indexContent['sections'] ?? [] as $section):
        $sectionNum++;
        $heading = $section['heading'] ?? '';
        $type = $section['type'] ?? 'section';
        $paragraphs = $section['paragraphs'] ?? [];
    ?>
        <?php if ($type === 'section'): ?>
        <h3 style="font-size: 19px; font-weight: 800; color: #fff; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
            <span style="color: var(--accent);"><?= sprintf('%02d', $sectionNum) ?>.</span> <?= View::escape($heading) ?>
        </h3>
        <?php else: ?>
        <h4 style="font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 12px; margin-top: 20px;">
            <?= View::escape($heading) ?>
        </h4>
        <?php endif; ?>

        <?php foreach ($paragraphs as $para): ?>
        <p style="font-size: 14px; margin-bottom: 20px;"><?= View::escape($para) ?></p>
        <?php endforeach; ?>
    <?php endforeach; ?>

<?php else: ?>

    <h2 style="font-size: 24px; font-weight: 950; color: #ffffff; letter-spacing: -0.5px; line-height: 1.3; margin-bottom: 25px; border-left: 5px solid var(--accent); padding-left: 20px;">
        <?= $siteName ?> — The Ultimate Platform to Watch Anime in <span style="color: var(--accent);">Hindi, Telugu, Tamil, Malayalam, Bengali &amp; Kannada</span> online for Free.
    </h2>

    <p style="font-size: 15px; margin-bottom: 30px;">
        Did you realize that every month, more than a billion people use Google to look up things linked to anime? Given how popular anime is all around the world, it makes sense that there are a lot more websites offering free anime streaming. Like free online movie watching sites, there are better and worse websites when it comes to anime streaming. Because of this, we created <span style="color: #fff; font-weight: 700;"><?= $siteName ?></span>, one of the best free anime streaming sites accessible to all anime fans across the globe.
    </p>

    <h3 style="font-size: 19px; font-weight: 800; color: #fff; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
        <span style="color: var(--accent);">01.</span> Do you Know about <?= $siteName ?>?
    </h3>
    <p style="font-size: 14px; margin-bottom: 30px;">
        You can download extremely high resolution (Ultra High Definition) Hindi as well, Telugu, &amp; Tamil anime from <?= $siteName ?> for free without registering or paying anything. With a focused approach to user experience, we hope to make it the most secure website for free anime.
    </p>

    <h3 style="font-size: 19px; font-weight: 800; color: #fff; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
        <span style="color: var(--accent);">02.</span> Languages Offered for Anime Watching
    </h3>
    <p style="font-size: 14px; margin-bottom: 20px;">
        <?= $siteName ?> offers high-quality dubbed content in numerous regional languages to ensure every fan feels at home:
    </p>

    <div style="margin-bottom: 35px;">
        <div style="margin-bottom: 15px; background: rgba(155, 89, 182, 0.05); padding: 15px; border-radius: 12px; border-left: 3px solid var(--accent);">
            <strong style="color: #fff; display: block; margin-bottom: 5px; font-size: 15px;">Hindi Dub Anime</strong>
            <span style="font-size: 13px;">Free streaming of numerous programs like Naruto, Pokemon, Vinland Saga, and Jujutsu Kaisen. Official creators from Crunchyroll handle the dubbing.</span>
        </div>
        <div style="margin-bottom: 15px; background: rgba(155, 89, 182, 0.05); padding: 15px; border-radius: 12px; border-left: 3px solid var(--accent);">
            <strong style="color: #fff; display: block; margin-bottom: 5px; font-size: 15px;">Telugu Dub Anime</strong>
            <span style="font-size: 13px;">Series including My Hero Academia, Naruto, and My Dress Up Darling are available for free without account requirements.</span>
        </div>
        <div style="margin-bottom: 15px; background: rgba(155, 89, 182, 0.05); padding: 15px; border-radius: 12px; border-left: 3px solid var(--accent);">
            <strong style="color: #fff; display: block; margin-bottom: 5px; font-size: 15px;">Tamil Dub Anime</strong>
            <span style="font-size: 13px;">Watch Darling in the FranXX, Vinland Saga, and more with high-quality Tamil audio provided by official creators.</span>
        </div>
        <div style="margin-bottom: 15px; background: rgba(155, 89, 182, 0.05); padding: 15px; border-radius: 12px; border-left: 3px solid var(--accent);">
            <strong style="color: #fff; display: block; margin-bottom: 5px; font-size: 15px;">Malayalam &amp; Bengali Dubs</strong>
            <span style="font-size: 13px;">We offer a wide variety of titles like Naruto and My Hero Academia in Malayalam and Bengali for an ad-free regional experience.</span>
        </div>
    </div>

    <h3 style="font-size: 19px; font-weight: 800; color: #fff; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
        <span style="color: var(--accent);">03.</span> Is <?= $siteName ?> secure?
    </h3>
    <p style="font-size: 14px; margin-bottom: 35px;">
        Indeed, we are. We prioritize security above all else. We monitor our streams regularly to ensure they remain clean and safe. If you ever encounter any suspicious activity, kindly report the details to our support, and we will act immediately.
    </p>

    <h3 style="font-size: 19px; font-weight: 800; color: #fff; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <span style="color: var(--accent);">04.</span> Why <?= $siteName ?> is the Greatest?
    </h3>
    <p style="font-size: 14px; margin-bottom: 20px;">
        We have studied all competitors to build the ultimate experience. Here is why we are the top choice for anime fans:
    </p>

    <ul style="list-style: none; padding: 0; margin-bottom: 40px;">
        <li style="margin-bottom: 15px; font-size: 14px; display: flex; align-items: flex-start; gap: 12px;">
            <span style="color: var(--accent); font-weight: 900;">•</span>
            <span><strong style="color: #fff;">Safety First:</strong> We ensure a harmful-ad and virus-free environment for all users.</span>
        </li>
        <li style="margin-bottom: 15px; font-size: 14px; display: flex; align-items: flex-start; gap: 12px;">
            <span style="color: var(--accent); font-weight: 900;">•</span>
            <span><strong style="color: #fff;">Massive Library:</strong> From action and romance to mystery and school genres, we have everything multilingually dubbed.</span>
        </li>
        <li style="margin-bottom: 15px; font-size: 14px; display: flex; align-items: flex-start; gap: 12px;">
            <span style="color: var(--accent); font-weight: 900;">•</span>
            <span><strong style="color: #fff;">Smart Resolution:</strong> Adjust between 360p, 720p, or 1080p based on your internet speed for seamless playback.</span>
        </li>
        <li style="margin-bottom: 15px; font-size: 14px; display: flex; align-items: flex-start; gap: 12px;">
            <span style="color: var(--accent); font-weight: 900;">•</span>
            <span><strong style="color: #fff;">Blazing Fast UI:</strong> Our interface is designed to be intuitive for all ages. Finding your favorite title takes seconds.</span>
        </li>
        <li style="margin-bottom: 15px; font-size: 14px; display: flex; align-items: flex-start; gap: 12px;">
            <span style="color: var(--accent); font-weight: 900;">•</span>
            <span><strong style="color: #fff;">Cross-Platform:</strong> Fully responsive on mobile, PC, and tablets, though we recommend desktop for the most cinematic feel.</span>
        </li>
    </ul>

    <p style="font-size: 15px; color: #fff; font-weight: 600; margin-bottom: 5px;">
        Give <?= $siteName ?> a shot today.
    </p>
    <p style="font-size: 13px; color: #555;">
        If you appreciate our hard work, please bookmark our site and share it with your friends. Thank you for choosing us!
    </p>

<?php endif; ?>

</div>
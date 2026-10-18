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

    <link rel="stylesheet" href="/assets/css/landing-bundle.css">
    <link rel="stylesheet" href="/assets/css/components/bottom-nav.css">
    <script>window.__AT_PROVIDER__="<?= strtolower(api_provider()) ?>"; window.__AT_PROVIDER_LIST__=["animetadka","anikoto","toonstream","animedekho"];</script>
</head>
<body class="landing-body at-has-bnav">
    <div class="glass-bg-wrapper">
        <div class="blob-1"></div>
        <div class="blob-2"></div>
    </div>

    <main class="landing-master-container">
        <nav class="landing-top-bar">
            <?php foreach ($topBarLinks as $link): ?>
            <a href="<?= View::escape($link['href']) ?>" class="glass-link"><?= View::escape($link['label']) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="landing-content-inner">
            <header class="landing-hero">
                <div class="landing-logo-wrap">
                    <img src="<?= View::escape(Config::get('logo')) ?>" alt="Logo" class="main-logo">
                </div>
                <h1 class="landing-title">
                    WELCOME TO <span class="accent-text"><?= View::escape(strtoupper(Config::get('site_name'))) ?></span>
                </h1>
                <p class="landing-desc">Explore the largest library of high-quality Anime, Movies.</p>
            </header>

            <?= $content ?>

            <footer style="margin-top: 60px; padding: 50px 20px 10px; border-top: 1px solid rgba(255,255,255,0.03); background: linear-gradient(to top, rgba(155, 89, 182, 0.02) 0%, transparent 100%); text-align:center; position: relative; width: 100%; box-sizing: border-box;">
                <div style="max-width: 600px; margin: 0 auto;">
                    <p style="color: #ffffff; font-size: 14px; font-weight: 900; letter-spacing: 3px; text-transform: uppercase; margin: 0; filter: drop-shadow(0 0 8px rgba(155, 89, 182, 0.4));">
                        &copy; 2026 <span class="accent-text"><?= View::escape(Config::get('site_name')) ?></span>
                    </p>
                    <p style="color: #444444; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-top: 12px; margin-bottom: 0;">
                        The Ultimate Anime Experience &bull; All Rights Reserved
                    </p>
                </div>
            </footer>
        </div>
    </main>

    <script src="/assets/js/pages/fpage.js?v=2" defer></script>
    <script src="/assets/js/pages/landing.js?v=2" defer></script>
    <?php require ROOT_PATH . '/app/Views/partials/bottom-nav.php'; ?>
</body>
</html>
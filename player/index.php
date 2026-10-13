<?php 
$embedUrl = "$websiteUrl/player/v3.php";
?>
<head>
    <title>Watch <?= $animeInfo['name'] ?> Episode <?= $episodeNum ?> - <?= $websiteTitle ?></title>
    
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="title" content="Watch <?= $animeInfo['name'] ?> Episode <?= $episodeNum ?> - <?= $websiteTitle ?>">
    <meta name="description" content="<?= substr($animeInfo['description'], 0, 150) ?> ... at <?= $websiteUrl ?>">
    <meta name="keywords" content="<?= $websiteTitle ?>, <?= $animeInfo['name'] ?> Episode <?= $episodeNum ?>, <?= $animeInfo['name'] ?>, watch anime online, free anime, anime stream, anime hd, english sub">
    <meta name="charset" content="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
    <meta name="robots" content="index, follow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="Content-Language" content="en">
    <meta property="og:title" content="Watch <?= $animeInfo['name'] ?> Episode <?= $episodeNum ?> - <?= $websiteTitle ?>">
    <meta property="og:description" content="<?= substr($animeInfo['description'], 0, 150) ?> ... at <?= $websiteUrl ?>">
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= $websiteTitle ?>">
    <meta property="og:url" content="<?= $websiteUrl ?>/watch/<?= $url ?>">
    <meta itemprop="image" content="<?= $animeInfo['poster'] ?>">
    <meta property="og:image" content="<?= $animeInfo['poster'] ?>">
    <meta property="twitter:title" content="Watch <?= $animeInfo['name'] ?> Episode <?= $episodeNum ?> - <?= $websiteTitle ?>">
    <meta property="twitter:description" content="<?= substr($animeInfo['description'], 0, 150) ?> ... at <?= $websiteUrl ?>">
    <meta property="twitter:url" content="<?= $websiteUrl ?>/watch/<?= $url ?>">
    <meta property="twitter:card" content="summary">
    <meta name="apple-mobile-web-app-status-bar" content="#202125">
    <script type="text/javascript" src="//s7.addthis.com/js/300/addthis_widget.js#pubid=ra-63430163bc99824a"></script>
    <meta name="theme-color" content="#202125">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta2/css/all.min.css" type="text/css">
    <link rel="apple-touch-icon" href="<?=$websiteUrl?>/favicon.png?v=<?=$version?>" />
    <link rel="shortcut icon" href="<?=$websiteUrl?>/favicon.png?v=<?=$version?>" type="image/x-icon"/>
    <link rel="apple-touch-icon" sizes="180x180" href="<?=$websiteUrl?>/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?=$websiteUrl?>/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?=$websiteUrl?>/favicon-16x16.png">
    <link rel="mask-icon" href="<?=$websiteUrl?>/safari-pinned-tab.svg" color="#5bbad5">
    <link rel="icon" sizes="192x192" href="<?=$websiteUrl?>/files/images/touch-icon-192x192.png?v=<?=$version?>">
    <link rel="stylesheet" href="<?= $websiteUrl ?>/files/css/style.css?v=<?= $version ?>">
    <link rel="stylesheet" href="<?= $websiteUrl ?>/files/css/min.css?v=<?= $version ?>">
</head>
      <div class="player-frame">
                                        <div class="loading-relative loading-box" id="embed-loading">
                                            <div class="loading">
                                                <div class="span1"></div>
                                                <div class="span2"></div>
                                                <div class="span3"></div>
                                            </div>
                                        </div>
                                        <!-- Direct embed from megaplay.buzz -->
                                        <iframe id="player-iframe" src="<?= $embedUrl ?>" frameborder="0" scrolling="no" allow="accelerometer;autoplay;encrypted-media;gyroscope;picture-in-picture" allowfullscreen="true" webkitallowfullscreen="true" mozallowfullscreen="true" width="100%" height="100%"></iframe>
                                    </div>
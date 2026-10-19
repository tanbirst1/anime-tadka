<?php
require_once __DIR__ . '/../http.php';
require_once __DIR__ . '/../dom.php';

const GENRE_MAP_ANIKOTO = [
  'action' => '1', 'adventure' => '2', 'cars' => '538', 'comedy' => '8',
  'dementia' => '453', 'demons' => '119', 'drama' => '62', 'ecchi' => '214',
  'fantasy' => '3', 'game' => '180', 'harem' => '215', 'historical' => '70',
  'horror' => '222', 'isekai' => '74', 'josei' => '404', 'kids' => '46',
  'magic' => '203', 'martial-arts' => '114', 'mecha' => '123', 'military' => '125',
  'music' => '242', 'mystery' => '57', 'parody' => '162', 'police' => '136',
  'psychological' => '73', 'romance' => '28', 'samurai' => '163', 'school' => '14',
  'sci-fi' => '12', 'seinen' => '50', 'shoujo' => '252', 'shoujo-ai' => '235',
  'shounen' => '15', 'shounen-ai' => '233', 'slice-of-life' => '35', 'space' => '124',
  'sports' => '29', 'super-power' => '16', 'supernatural' => '9', 'thriller' => '54',
  'unknown' => '32', 'vampire' => '58',
];

const TYPE_SLUGS_ANIKOTO = ['movie', 'tv', 'ova', 'ona', 'special', 'music'];

function anikotoResolveGenreId($value) {
  if (!$value) return $value;
  $str = strtolower(trim((string)$value));
  if (preg_match('/^\d+$/', $str)) return $str;
  return GENRE_MAP_ANIKOTO[$str] ?? $str;
}

function anikotoResolveGenres(&$params) {
  if (!isset($params['genre[]'])) return;
  if (is_array($params['genre[]'])) {
    $params['genre[]'] = array_map('anikotoResolveGenreId', $params['genre[]']);
  } else {
    $params['genre[]'] = anikotoResolveGenreId($params['genre[]']);
  }
}

function anikotoExtractId($href) {
  if (!$href) return null;
  $href = preg_replace('/^https?:\/\/[^\/]+\/watch\//', '', $href);
  $href = preg_replace('/^\/watch\//', '', $href);
  $href = preg_replace('/\/ep-\d+.*$/', '', $href);
  $href = preg_replace('/^\//', '', $href);
  return trim($href) ?: null;
}

function anikotoParseEpisodes($el) {
  $subText = $el->find('.ep-status.sub span');
  $sub = count($subText) > 0 ? domNum($subText[0]->text()) : null;
  $dubText = $el->find('.ep-status.dub span');
  $dub = count($dubText) > 0 ? domNum($dubText[0]->text()) : null;
  return ['sub' => $sub, 'dub' => $dub];
}

function anikotoParseAitem($doc, $el) {
  $posterLink = $el->find('.ani.poster a, a.poster');
  $href = count($posterLink) > 0 ? $posterLink[0]->attr('href') : null;
  if (!$href) {
    $a = $el->find('a');
    $href = count($a) > 0 ? $a[0]->attr('href') : null;
  }
  $id = anikotoExtractId($href);
  $tipId = null;
  $aniPoster = $el->find('.ani.poster');
  if (count($aniPoster) > 0) $tipId = $aniPoster[0]->attr('data-tip');
  if (!$tipId) {
    $dataTip = $el->find('[data-tip]');
    if (count($dataTip) > 0) $tipId = $dataTip[0]->attr('data-tip');
  }
  $nameEl = $el->find('.name.d-title');
  $jname = count($nameEl) > 0 ? $nameEl[0]->attr('data-jp') : null;
  $name = count($nameEl) > 0 ? ($nameEl[0]->text() ?: '') : '';
  $poster = $el->find('img');
  $posterSrc = count($poster) > 0 ? $poster[0]->attr('src') : null;
  $right = $el->find('.meta .right');
  $type = count($right) > 0 ? $right[0]->text() : null;
  return [
    'id' => $id, 'tipId' => $tipId,
    'name' => $name,
    'jname' => $jname,
    'poster' => $posterSrc,
    'type' => $type ?: null,
    'episodes' => anikotoParseEpisodes($el),
  ];
}

function anikotoToApiUrl($siteHref, $providerName) {
  if (!$siteHref || $siteHref === 'javascript:;') return null;
  $base = "/api/$providerName";
  if (preg_match('/^\/genre\/(.+)$/', $siteHref, $m)) return "$base/genre/{$m[1]}";
  if (preg_match('/^\/az-list\/?(.*)$/', $siteHref, $m)) return $m[1] ? "$base/azlist/{$m[1]}" : "$base/azlist";
  if (preg_match('/\/watch\/(.+)$/', $siteHref, $m)) return "$base/anime/" . anikotoExtractId($siteHref);
  $slug = ltrim($siteHref, '/');
  if (strpos($slug, 'type/') === 0) return "$base/type/" . substr($slug, 5);
  if (in_array($slug, TYPE_SLUGS_ANIKOTO, true)) return "$base/type/$slug";
  if ($slug === 'filter') return "$base/search";
  if ($slug === 'random') return "/api/$providerName/random";
  if ($slug === 'home') return "$base/home";
  if ($slug === 'latest-updated') return "$base/category/latest-updated";
  if ($slug === 'new-release') return "$base/category/new-release";
  if ($slug === 'most-viewed') return "$base/category/most-viewed";
  if (strpos($slug, 'status/') === 0) return "$base/category/$slug";
  return $siteHref;
}

function anikotoGetLastPage($doc) {
  $xpath = new DOMXPath($doc instanceof DOMNodeWrapper ? $doc->getNode()->ownerDocument : $doc);
  $lastHref = $xpath->query("//nav//ul[contains(@class,'pagination')]//li[last()]//a[contains(@class,'page-link')]");
  if ($lastHref->length > 0) {
    $href = $lastHref->item(0)->getAttribute('href');
    if ($href && preg_match('/page=(\d+)/', $href, $m)) return (int)$m[1];
  }
  $max = 1;
  $links = $xpath->query("//nav//ul[contains(@class,'pagination')]//li//a[contains(@class,'page-link')]");
  foreach ($links as $el) {
    $n = (int)trim($el->textContent);
    if ($n > $max) $max = $n;
  }
  return $max;
}

function anikotoGetCurrentPage($doc) {
  $xpath = new DOMXPath($doc instanceof DOMNodeWrapper ? $doc->getNode()->ownerDocument : $doc);
  $active = $xpath->query("//nav//ul[contains(@class,'pagination')]//li[contains(@class,'active')]//a[contains(@class,'page-link')]");
  if ($active->length > 0) return (int)trim($active->item(0)->textContent) ?: 1;
  return 1;
}

// ─── Home ──────────────────────────────────────────────────────────

function anikotoParseHome($html) {
  $doc = domLoad($html);
  $genres = domEach($doc, '#menu ul li ul.c4 li a', fn($el) => trim($el->attr('title') ?: $el->text() ?: ''));
  $genres = array_values(array_filter($genres));

  // Spotlight slider. Newer Anikoto builds changed the hero markup: slides no
  // longer carry the `.item` class and the title/poster/description/rating
  // moved into `.ak-*` nodes. Try the current markup first, then fall back to
  // the legacy one so the slider never renders blank after a site redesign.
  $spotlight = domEach($doc, '#hotest .swiper-wrapper .swiper-slide.item, #hotest .swiper-wrapper .swiper-slide, #hotest .swiper-slide', function ($el, $i) {
    $playBtn = $el->find('a.btn.btn-primary, a.btn.play, a.btn');
    if (count($playBtn) === 0) $playBtn = $el->find('a[href*="/watch/"]');
    $href = count($playBtn) > 0 ? $playBtn[0]->attr('href') : null;
    $id = anikotoExtractId($href);

    $title = $el->find('h2.ak-title.d-title, h2.ak-title, .title.d-title');
    $name = count($title) > 0 ? $title[0]->text() : null;
    $jname = count($title) > 0 ? $title[0]->attr('data-jp') : null;

    // New: <div class="cover"><img src="...">  Old: .image div[style="background-image:url(...)"]
    $poster = null;
    $coverImg = $el->find('.cover img');
    if (count($coverImg) > 0) $poster = $coverImg[0]->attr('src') ?: $coverImg[0]->attr('data-src');
    if (!$poster) {
      $image = $el->find('.image div, .image');
      if (count($image) > 0) {
        $style = $image[0]->attr('style');
        if ($style && preg_match("/url\(['\"]?([^)'\"]+)['\"]?\)/", $style, $m)) $poster = $m[1];
      }
    }
    if (!$poster) {
      $anyImg = $el->find('img');
      if (count($anyImg) > 0) $poster = $anyImg[0]->attr('src');
    }

    // New: .ak-desc  Old: .synopsis
    $synopsis = $el->find('.ak-desc, .synopsis');
    $description = count($synopsis) > 0 ? $synopsis[0]->text() : null;

    $ratingText = null;
    $qualityText = null;
    $dateText = null;
    $rating = $el->find('i.rating');
    if (count($rating) > 0) $ratingText = trim($rating[0]->text());
    $quality = $el->find('i.quality');
    if (count($quality) > 0) $qualityText = trim($quality[0]->text());
    $date = $el->find('i.date');
    if (count($date) > 0) $dateText = trim($date[0]->text());

    // New markup packs type/date/rating into <span class="it"><i class="fa-star"></i>7.49</span>.
    foreach ($el->find('.ak-detail .it, .ak-detail > span') as $it) {
      $txt = trim($it->text());
      if ($txt === '') continue;
      $icon = $it->find('i');
      $iconClass = count($icon) > 0 ? (string) $icon[0]->attr('class') : '';
      if ($ratingText === null && strpos($iconClass, 'star') !== false) $ratingText = $txt;
      elseif ($dateText === null && strpos($iconClass, 'calendar') !== false) $dateText = $txt;
      elseif ($qualityText === null && strpos($iconClass, 'play') !== false) $qualityText = $txt;
    }

    $otherInfo = array_values(array_filter([$qualityText, $dateText]));
    return [
      'id' => $id, 'name' => $name, 'jname' => $jname ?: null,
      'poster' => $poster, 'description' => $description,
      'rating' => $ratingText, 'rank' => $i + 1, 'otherInfo' => $otherInfo,
      'genres' => [],
      'episodes' => ['sub' => null, 'dub' => null],
    ];
  });

  $latest = domEach($doc, '#recent-update .ani.items .item', function ($el) {
    $posterA = $el->find('.ani.poster a');
    $href = count($posterA) > 0 ? $posterA[0]->attr('href') : null;
    $id = anikotoExtractId($href);
    $nameEl = $el->find('.name.d-title');
    $name = count($nameEl) > 0 ? ($nameEl[0]->attr('data-jp') ?: $nameEl[0]->text()) : null;
    $jname = count($nameEl) > 0 ? $nameEl[0]->attr('data-jp') : null;
    $img = $el->find('img');
    $poster = count($img) > 0 ? $img[0]->attr('src') : null;
    $right = $el->find('.meta .right');
    $type = count($right) > 0 ? $right[0]->text() : null;
    return [
      'id' => $id, 'name' => $name, 'jname' => $jname ?: null,
      'poster' => $poster, 'type' => $type ?: null,
      'episodes' => anikotoParseEpisodes($el),
    ];
  });

  $newRel = domEach($doc, '.top-tables section[data-name="new-release"] .scaff.items a.item', function ($el) {
    $href = $el->attr('href');
    $nameEl = $el->find('.name.d-title');
    $name = count($nameEl) > 0 ? ($nameEl[0]->attr('data-jp') ?: $nameEl[0]->text()) : null;
    $jname = count($nameEl) > 0 ? $nameEl[0]->attr('data-jp') : null;
    $img = $el->find('img');
    $poster = count($img) > 0 ? $img[0]->attr('src') : null;
    return [
      'id' => anikotoExtractId($href), 'name' => $name, 'jname' => $jname ?: null,
      'poster' => $poster, 'type' => null, 'episodes' => anikotoParseEpisodes($el),
    ];
  });

  $topUpcoming = domEach($doc, '.top-tables section[data-name="new-added"] .scaff.items a.item', function ($el) {
    $href = $el->attr('href');
    $nameEl = $el->find('.name.d-title');
    $name = count($nameEl) > 0 ? ($nameEl[0]->attr('data-jp') ?: $nameEl[0]->text()) : null;
    $jname = count($nameEl) > 0 ? $nameEl[0]->attr('data-jp') : null;
    $img = $el->find('img');
    $poster = count($img) > 0 ? $img[0]->attr('src') : null;
    return [
      'id' => anikotoExtractId($href), 'name' => $name, 'jname' => $jname ?: null,
      'poster' => $poster, 'type' => null, 'episodes' => anikotoParseEpisodes($el),
    ];
  });

  $parseTab = function ($doc, $dataName) {
    return domEach($doc, ".tab-content[data-name=\"$dataName\"] .scaff.side.items a.item", function ($el) {
      $href = $el->attr('href');
      $class = $el->attr('class');
      $rank = null;
      if ($class && preg_match('/rank(\d+)/', $class, $m)) $rank = (int)$m[1];
      $nameEl = $el->find('.name.d-title');
      $name = count($nameEl) > 0 ? ($nameEl[0]->attr('data-jp') ?: $nameEl[0]->text()) : null;
      $img = $el->find('img');
      $poster = count($img) > 0 ? $img[0]->attr('src') : null;
      return [
        'id' => anikotoExtractId($href), 'rank' => $rank,
        'name' => $name, 'poster' => $poster,
        'episodes' => anikotoParseEpisodes($el),
      ];
    });
  };

  return [
    'genres' => $genres,
    'spotlightAnimes' => $spotlight,
    'latestEpisodeAnimes' => $latest,
    'newReleases' => $newRel,
    'topUpcomingAnimes' => $topUpcoming,
    'top10Animes' => [
      'today' => $parseTab($doc, 'day'),
      'day' => [],
      'week' => $parseTab($doc, 'week'),
      'month' => $parseTab($doc, 'month'),
    ],
  ];
}

// ─── Index ─────────────────────────────────────────────────────────

function anikotoParseIndex($html) {
  $doc = domLoad($html);
  return [
    'meta' => [
      'title' => domText($doc, 'title'),
      'description' => domAttr($doc, 'meta[name="description"]', 'content'),
      'ogImage' => domAttr($doc, 'meta[property="og:image"]', 'content'),
      'canonical' => domAttr($doc, 'link[rel="canonical"]', 'href'),
    ],
    'mostSearched' => domEach($doc, '.search-term a.item', function ($el) {
      $txt = preg_replace('/,?\s*$/', '', $el->text());
      return ['label' => $txt, 'keyword' => $txt];
    }),
    'genres' => domEach($doc, '#menu ul li ul.c4 li a', fn($el) => trim($el->attr('title') ?: $el->text() ?: '')),
    'azList' => domEach($doc, 'footer .azlist ul li a', fn($el) => ['label' => $el->text(), 'href' => $el->attr('href')]),
    'footerMenu' => domEach($doc, 'footer .inline-links ul li a', function ($el) {
      $span = $el->find('span');
      $label = count($span) > 0 ? $span[0]->text() : $el->text();
      return ['label' => trim($label), 'href' => $el->attr('href')];
    }),
  ];
}

// ─── Nav Menu ──────────────────────────────────────────────────────

function anikotoParseNavMenu($html, $providerName = 'anikoto') {
  $doc = domLoad($html);
  $genres = domEach($doc, '#menu ul li ul.c4 li a', function ($el) use ($providerName) {
    return [
      'name' => trim($el->attr('title') ?: $el->text() ?: ''),
      'url' => anikotoToApiUrl($el->attr('href'), $providerName),
    ];
  });
  $types = domEach($doc, '#menu ul li ul.c1 li a', function ($el) use ($providerName) {
    return [
      'name' => trim($el->attr('title') ?: $el->text() ?: ''),
      'url' => anikotoToApiUrl($el->attr('href'), $providerName),
    ];
  });
  $links = [];
  $menuItems = domEach($doc, '#menu > ul > li > a', function ($el) use ($providerName, &$links) {
    $href = $el->attr('href');
    $clone = $el->getNode()->cloneNode(true);
    $tmp = new DOMDocument();
    $tmp->appendChild($tmp->importNode($clone, true));
    $x = new DOMXPath($tmp);
    foreach ($x->query('//i | //ul | //h3') as $node) $node->parentNode->removeChild($node);
    $name = trim($tmp->textContent);
    if ($href && $href !== 'javascript:;' && $name && $name !== 'Genre' && $name !== 'Types') {
      $links[] = ['name' => $name, 'url' => anikotoToApiUrl($href, $providerName)];
    }
    return null;
  });
  return [
    'brand' => [
      'link' => domAttr($doc, 'header .logo a', 'href') ?: '/home',
      'logo' => domAttr($doc, 'header .logo img', 'src'),
    ],
    'buttons' => ['menu' => true, 'search' => true, 'watch2gether' => null, 'random' => '/random'],
    'search' => [
      'action' => "/api/$providerName/search",
      'placeholder' => domAttr($doc, 'header input[name="keyword"]', 'placeholder') ?: 'Search anime...',
      'filter_link' => "/api/$providerName/search",
    ],
    'menu' => ['genres' => $genres, 'types' => $types, 'links' => $links],
    'browse' => [
      'url' => "/api/$providerName/search",
      'sortOptions' => [
        ['label' => 'Default', 'value' => 'default'],
        ['label' => 'Latest Updated', 'value' => 'latest-updated'],
        ['label' => 'Latest Added', 'value' => 'latest-added'],
        ['label' => 'Score', 'value' => 'score'],
        ['label' => 'Name A-Z', 'value' => 'name-az'],
        ['label' => 'Release Date', 'value' => 'release-date'],
        ['label' => 'Most Viewed', 'value' => 'most-viewed'],
        ['label' => 'Number of episodes', 'value' => 'number_of_episodes'],
      ],
      'filters' => [
        'type' => ['Movie', 'Music', 'ONA', 'OVA', 'Special', 'TV'],
        'status' => ['finished-airing', 'currently-airing', 'not-yet-aired'],
        'season' => ['fall', 'summer', 'spring', 'winter'],
        'rating' => ['PG', 'PG-13', 'G', 'R', 'R+', 'Rx'],
        'language' => ['sub', 'dub'],
      ],
    ],
    'language' => ['en', 'jp'],
  ];
}

// ─── Search / Browse / List ────────────────────────────────────────

function anikotoParseSearchFromHtml($html) {
  $doc = domLoad($html);
  $animes = domEach($doc, '#list-items .item, .items .item', function ($el) use ($doc) {
    return anikotoParseAitem($doc, $el);
  });
  $cur = anikotoGetCurrentPage($doc);
  $last = anikotoGetLastPage($doc);
  return ['animes' => $animes, 'currentPage' => $cur, 'totalPages' => $last, 'hasNextPage' => $cur < $last, 'totalCount' => null];
}

function anikotoParseListPage($html) {
  $doc = domLoad($html);
  $title = domText($doc, '.head .title');
  $animes = domEach($doc, '#list-items .item, .items .item', function ($el) use ($doc) {
    return anikotoParseAitem($doc, $el);
  });
  $cur = anikotoGetCurrentPage($doc);
  $last = anikotoGetLastPage($doc);
  return ['title' => $title, 'animes' => $animes, 'currentPage' => $cur, 'totalPages' => $last, 'hasNextPage' => $cur < $last];
}

// ─── Detail ────────────────────────────────────────────────────────

function anikotoParseAnime($html) {
  $doc = domLoad($html);
  $animeId = domAttr($doc, '#watch-main', 'data-id');
  $canonical = domAttr($doc, 'link[rel="canonical"]', 'href') ?: '';
  $dataUrl = domAttr($doc, '#watch-main', 'data-url') ?: '';
  $slugFromUrl = anikotoExtractId($dataUrl ?: $canonical);
  $name = domText($doc, 'h1.title.d-title');
  if (!$name) {
    $ogTitle = domAttr($doc, 'meta[property="og:title"]', 'content');
    if ($ogTitle) $name = trim(preg_replace('/^Watch\s+/', '', preg_replace('/\s+Episode\s+\d+.*$/i', '', $ogTitle)));
  }
  $jname = domAttr($doc, 'h1.title.d-title', 'data-jp');
  $poster = domAttr($doc, '.poster img[itemprop="image"]', 'src') ?: domAttr($doc, 'meta[property="og:image"]', 'content');
  $synonyms = domText($doc, '.binfo .info .names');
  $description = domText($doc, '.synopsis .shorting .content') ?: domAttr($doc, 'meta[property="og:description"]', 'content');
  $rating = domText($doc, '.meta i.rating');
  $xpath = new DOMXPath($doc);

  $detailVal = function ($baseSel, $label) use ($doc, $xpath) {
    $bases = $xpath->query(cssToXPath($baseSel));
    if (!$bases) return null;
    foreach ($bases as $base) {
      foreach ($base->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE && strpos(trim($child->textContent), $label) === 0) {
          $spans = $child->getElementsByTagName('span');
          if ($spans->length > 0) {
            $clone = $spans->item(0)->cloneNode(true);
            $tmp = new DOMDocument();
            $tmp->appendChild($tmp->importNode($clone, true));
            $x = new DOMXPath($tmp);
            foreach ($x->query('//a') as $a) $a->parentNode->removeChild($a);
            $result = trim($tmp->textContent);
            if ($result) return $result;
            $as = $spans->item(0)->getElementsByTagName('a');
            if ($as->length > 0) return trim($as->item(0)->textContent);
          }
          return trim(str_replace($label, '', trim($child->textContent)));
        }
      }
    }
    return null;
  };

  $detailLinks = function ($baseSel, $label) use ($xpath) {
    $results = [];
    $bases = $xpath->query(cssToXPath($baseSel));
    if (!$bases) return $results;
    foreach ($bases as $base) {
      foreach ($base->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE && strpos(trim($child->textContent), $label) === 0) {
          $as = $child->getElementsByTagName('a');
          foreach ($as as $a) $results[] = trim($a->textContent);
          return $results;
        }
      }
    }
    return $results;
  };

  $firstMeta = '.bmeta .meta:first-child';
  $lastMeta = '.bmeta .meta:last-child';
  $type = $detailVal($firstMeta, 'Type:');
  $premiered = $detailLinks($firstMeta, 'Premiered:')[0] ?? null;
  $aired = $detailVal($firstMeta, 'Aired:');
  $status = $detailLinks($firstMeta, 'Status:')[0] ?? $detailVal($firstMeta, 'Status:');
  $genres = $detailLinks($firstMeta, 'Genres:');
  $score = $detailVal($lastMeta, 'MAL:');
  $duration = $detailVal($lastMeta, 'Duration:');
  $episodesRaw = $detailVal($lastMeta, 'Episodes:');
  $episodesTotal = ($episodesRaw === '?') ? null : ((int)$episodesRaw ?: null);
  $studios = $detailLinks($lastMeta, 'Studios:');
  $producers = $detailLinks($lastMeta, 'Producers:');

  $malId = null;
  $scripts = $xpath->query('//script');
  foreach ($scripts as $script) {
    $content = $script->textContent;
    if ($content && preg_match('/const\s+mangaId\s*=\s*(\d+)/', $content, $m)) {
      $malId = $m[1];
      break;
    }
  }

  $recommended = [];
  $sidebarLast = $xpath->query("//section[contains(@class,'w-side-section')][last()]//a[contains(@class,'item')]");
  if ($sidebarLast && $sidebarLast->length > 0) {
    foreach ($sidebarLast as $el) {
      $href = $el->getAttribute('href');
      $dotEls = $xpath->query(".//span[contains(@class,'dot')]", $el);
      $typeText = $dotEls->length > 0 ? trim($dotEls->item(0)->textContent) : '';
      $epsText = $dotEls->length > 1 ? trim($dotEls->item(1)->textContent) : '';
      $yearText = $dotEls->length > 2 ? trim($dotEls->item(2)->textContent) : '';
      $nameEl = $xpath->query(".//*[contains(@class,'d-title')]", $el);
      $rName = $nameEl->length > 0 ? ($nameEl->item(0)->getAttribute('data-jp') ?: trim($nameEl->item(0)->textContent)) : null;
      $rJname = $nameEl->length > 0 ? $nameEl->item(0)->getAttribute('data-jp') ?: null : null;
      $img = $xpath->query(".//img", $el);
      $rPoster = $img->length > 0 ? $img->item(0)->getAttribute('src') : null;
      $recommended[] = [
        'id' => anikotoExtractId($href),
        'name' => $rName, 'jname' => $rJname, 'poster' => $rPoster,
        'type' => $typeText ?: null, 'duration' => null,
        'episodes' => ['sub' => (int)$epsText ?: null, 'dub' => null],
        'year' => (int)$yearText ?: null,
      ];
    }
  }
  // Fallback: try more generic selectors if primary sidebar selector found nothing (site HTML changed)
  if (empty($recommended)) {
    $fallbacks = [
      "//aside//a[contains(@class,'item')]",
      "//div[contains(@class,'recommend')]//a[contains(@class,'item')]",
      "//section//a[contains(@class,'item') and .//img]",
      "//a[contains(@class,'item') and contains(@href,'/watch/')]",
    ];
    foreach ($fallbacks as $sel) {
      $nodes = $xpath->query($sel);
      if ($nodes && $nodes->length > 0 && $nodes->length < 50) {
        foreach ($nodes as $el) {
          $href = $el->getAttribute('href');
          if (!$href || strpos($href, '/watch/') === false) continue;
          $dotEls = $xpath->query(".//span[contains(@class,'dot')]", $el);
          $typeText = $dotEls->length > 0 ? trim($dotEls->item(0)->textContent) : '';
          $epsText = $dotEls->length > 1 ? trim($dotEls->item(1)->textContent) : '';
          $yearText = $dotEls->length > 2 ? trim($dotEls->item(2)->textContent) : '';
          $nameEl = $xpath->query(".//*[contains(@class,'d-title')]", $el);
          if ($nameEl->length === 0) $nameEl = $xpath->query(".//*[contains(@class,'title')]", $el);
          $rName = $nameEl->length > 0 ? ($nameEl->item(0)->getAttribute('data-jp') ?: trim($nameEl->item(0)->textContent)) : null;
          if (!$rName) $rName = trim($el->textContent) ?: null;
          $rJname = $nameEl->length > 0 ? $nameEl->item(0)->getAttribute('data-jp') ?: null : null;
          $img = $xpath->query(".//img", $el);
          $rPoster = $img->length > 0 ? ($img->item(0)->getAttribute('data-src') ?: $img->item(0)->getAttribute('src')) : null;
          if ($rName && $rPoster) {
            $recommended[] = [
              'id' => anikotoExtractId($href),
              'name' => $rName, 'jname' => $rJname, 'poster' => $rPoster,
              'type' => $typeText ?: null, 'duration' => null,
              'episodes' => ['sub' => (int)$epsText ?: null, 'dub' => null],
              'year' => (int)$yearText ?: null,
            ];
          }
          if (count($recommended) >= 12) break 2;
        }
      }
      if (!empty($recommended)) break;
    }
  }

  // Related: try to extract from page if exists; keep separate from recommended
  $related = [];
  $relSelectors = [
    "//section[contains(@class,'related')]//a[contains(@class,'item') or contains(@href,'/watch/')]",
    "//div[contains(@class,'related')]//a[contains(@href,'/watch/')]",
    "//h3[contains(.,'Related')]/following::section[1]//a[contains(@href,'/watch/')]",
  ];
  foreach ($relSelectors as $sel) {
    $nodes = $xpath->query($sel);
    if ($nodes && $nodes->length > 0 && $nodes->length < 30) {
      foreach ($nodes as $el) {
        $href = $el->getAttribute('href');
        if (!$href || strpos($href, '/watch/') === false) continue;
        $img = $xpath->query(".//img", $el);
        $rPoster = $img->length > 0 ? ($img->item(0)->getAttribute('data-src') ?: $img->item(0)->getAttribute('src')) : null;
        $nameEl = $xpath->query(".//*[contains(@class,'title')]", $el);
        $rName = $nameEl->length > 0 ? trim($nameEl->item(0)->textContent) : trim($el->textContent);
        if ($rName && $rPoster) {
          $related[] = [
            'id' => anikotoExtractId($href),
            'name' => $rName, 'jname' => null, 'poster' => $rPoster,
            'type' => null, 'duration' => null,
            'episodes' => ['sub' => null, 'dub' => null],
            'year' => null,
          ];
        }
        if (count($related) >= 12) break 2;
      }
    }
    if (!empty($related)) break;
  }

  return [
    'anime' => [
      'id' => $slugFromUrl ?: $animeId, 'animeId' => $animeId,
      'name' => $name, 'jname' => $jname ?: null,
      'synonyms' => $synonyms ?: null, 'japanese' => $jname ?: null,
      'poster' => $poster, 'description' => $description,
      'type' => $type ?: null, 'rating' => $rating ?: null,
      'episodes' => ['sub' => null, 'dub' => null],
      'duration' => $duration ?: null, 'premiered' => $premiered,
      'aired' => $aired, 'broadcast' => null, 'status' => $status ?: null,
      'score' => $score ?: null, 'episodesTotal' => $episodesTotal,
      'country' => null, 'genres' => $genres, 'studios' => $studios,
      'producers' => $producers, 'malId' => $malId, 'alId' => null,
    ],
    'related' => $related,
    'recommended' => $recommended,
    'seasons' => [],
  ];
}

// ─── JSON API parsers ──────────────────────────────────────────────

function anikotoParseAnimeFromJson($json) {
  if (!($json['ok'] ?? false)) throw new Exception('Invalid JSON API response');
  $d = $json['data']['anime'];
  return [
    'anime' => [
      'id' => (string)$d['id'], 'animeId' => (string)$d['id'],
      'name' => $d['title'], 'jname' => $d['native'] ?? null,
      'synonyms' => $d['alternative'] ?? null, 'japanese' => $d['native'] ?? null,
      'poster' => $d['poster'],
      'description' => isset($d['description']) ? strip_tags($d['description']) : null,
      'type' => $d['terms_by_type']['type'][0] ?? null,
      'rating' => $d['rating'] ?? null,
      'episodes' => ['sub' => $d['is_sub'] ?? null, 'dub' => $d['is_dub'] ?? null],
      'duration' => $d['duration'] ?? null, 'premiered' => null,
      'aired' => $d['aired'] ?? null, 'broadcast' => null,
      'status' => $d['status'] ?? null, 'score' => null,
      'episodesTotal' => isset($d['episodes']) ? (int)$d['episodes'] : null,
      'country' => null,
      'genres' => $d['terms_by_type']['genre'] ?? [],
      'studios' => $d['terms_by_type']['studios'] ?? [],
      'producers' => $d['terms_by_type']['producers'] ?? [],
      'malId' => $d['mal_id'] ?? null, 'alId' => $d['ani_id'] ?? null,
    ],
    'related' => [], 'recommended' => [], 'seasons' => [],
  ];
}

function anikotoParseEpisodesFromJson($json) {
  if (!($json['ok'] ?? false)) throw new Exception('Invalid JSON API response');
  $anime = $json['data']['anime'];
  $episodesList = $json['data']['episodes'] ?? [];
  $eps = [];
  foreach ($episodesList as $ep) {
    $sources = [];
    if (!empty($ep['embed_url']['sub'])) $sources['sub'] = $ep['embed_url']['sub'];
    if (!empty($ep['embed_url']['dub'])) $sources['dub'] = $ep['embed_url']['dub'];
    $eps[] = [
      'number' => $ep['number'],
      'title' => $ep['title'] ?? "{$anime['title']} - Episode {$ep['number']}",
      'isFiller' => false,
      'hasSub' => !empty($ep['embed_url']['sub']),
      'hasDub' => !empty($ep['embed_url']['dub']),
      'sources' => $sources,
    ];
  }
  return [
    'totalEpisodes' => count($eps),
    'malId' => $anime['mal_id'] ?? null,
    'alId' => $anime['ani_id'] ?? null,
    'episodes' => $eps,
  ];
}

// ─── Smart merge ───────────────────────────────────────────────────

function anikotoMergeAnimeData($jsonResult, $htmlResult) {
  $j = $jsonResult['anime'] ?? [];
  $h = $htmlResult['anime'] ?? [];

  $val = function (...$args) {
    foreach ($args as $v) {
      if ($v !== null && $v !== '' && $v !== 'unknown') return $v;
    }
    return $args[0];
  };

  $arr = function ($jArr, $hArr) {
    $jf = array_values(array_filter($jArr ?? [], fn($v) => $v && $v !== 'unknown'));
    $hf = array_values(array_filter($hArr ?? [], fn($v) => $v && $v !== 'unknown'));
    if (count($jf) > 0) return $jf;
    if (count($hf) > 0) return $hf;
    return (count($jArr ?? []) > 0) ? $jArr : ($hArr ?? []);
  };

  $cleanDur = function ($d) {
    if (!$d) return null;
    return preg_replace('/\s*min\s*min$/i', ' min', trim($d));
  };

  return [
    'anime' => [
      'id' => $val($j['id'] ?? null, $h['id'] ?? null),
      'animeId' => $val($j['animeId'] ?? null, $h['animeId'] ?? null),
      'name' => $val($j['name'] ?? null, $h['name'] ?? null),
      'jname' => $val($j['jname'] ?? null, $h['jname'] ?? null),
      'synonyms' => $val($j['synonyms'] ?? null, $h['synonyms'] ?? null),
      'japanese' => $val($j['japanese'] ?? null, $h['japanese'] ?? null),
      'poster' => $val($j['poster'] ?? null, $h['poster'] ?? null),
      'description' => $val($h['description'] ?? null, $j['description'] ?? null),
      'type' => $val($h['type'] ?? null, $j['type'] ?? null),
      'rating' => $val($h['rating'] ?? null, $j['rating'] ?? null),
      'episodes' => [
        'sub' => $val($j['episodes']['sub'] ?? null, $h['episodes']['sub'] ?? null),
        'dub' => $val($j['episodes']['dub'] ?? null, $h['episodes']['dub'] ?? null),
      ],
      'duration' => $cleanDur($val($h['duration'] ?? null, $j['duration'] ?? null)),
      'premiered' => $val($h['premiered'] ?? null, $j['premiered'] ?? null),
      'aired' => $val($h['aired'] ?? null, $j['aired'] ?? null),
      'broadcast' => $val($j['broadcast'] ?? null, $h['broadcast'] ?? null),
      'status' => $val($h['status'] ?? null, $j['status'] ?? null),
      'score' => $val($h['score'] ?? null, $j['score'] ?? null),
      'episodesTotal' => $val($j['episodesTotal'] ?? null, $h['episodesTotal'] ?? null),
      'country' => $val($j['country'] ?? null, $h['country'] ?? null),
      'genres' => $arr($j['genres'] ?? [], $h['genres'] ?? []),
      'studios' => $arr($j['studios'] ?? [], $h['studios'] ?? []),
      'producers' => $arr($j['producers'] ?? [], $h['producers'] ?? []),
      'malId' => $val($j['malId'] ?? null, $h['malId'] ?? null),
      'alId' => $val($j['alId'] ?? null, $h['alId'] ?? null),
    ],
    'related' => $val($jsonResult['related'] ?? [], $htmlResult['related'] ?? []),
    'recommended' => $val($jsonResult['recommended'] ?? [], $htmlResult['recommended'] ?? []),
    'seasons' => $val($jsonResult['seasons'] ?? [], $htmlResult['seasons'] ?? []),
  ];
}

// ─── Public API ────────────────────────────────────────────────────

/**
 * GET a path from the anikoto primary host, transparently falling back to the
 * backup mirror (https://anikoto.cz) when the primary fails — DNS, connection,
 * timeout, or a 4xx/5xx response.
 *
 * Within one request the host that last succeeded is remembered, so a page that
 * resolves several resources does not re-pay the primary timeout each time.
 * Across requests a short-lived sticky flag keeps traffic on the mirror while
 * the primary is down, then lets it drift back once the primary recovers.
 *
 * @param string $path   Path beginning with '/', e.g. '/home'.
 * @param array  $params Optional query parameters.
 * @return string Response body.
 */
function anikotoGet($path, $params = []) {
  static $preferred = null;

  $primary = baseUrl('anikoto');
  $backup  = baseUrl('anikotoBackup');

  // Ordered, de-duplicated candidate hosts. The remembered winner (if any) is
  // tried first; the rest follow as fallbacks.
  $hosts = [];
  foreach ([$preferred, $primary, $backup] as $h) {
    if (is_string($h) && $h !== '' && !in_array($h, $hosts, true)) $hosts[] = $h;
  }

  // Cross-request hint: when the primary was seen failing recently, start with
  // the backup instead of waiting on the primary timeout again.
  if ($preferred === null && $primary !== null && $backup !== null) {
    $sticky = anikotoStickyHost($primary, $backup);
    if ($sticky !== null) $hosts = anikotoReorderHosts($hosts, $sticky);
  }

  $lastError = null;
  $count = count($hosts);
  foreach ($hosts as $i => $host) {
    try {
      $body = httpGet($host . $path, $params);
      $preferred = $host;
      if ($host === $backup && $primary !== null) {
        anikotoMarkPrimaryDown($primary, $backup);
      } elseif ($host === $primary) {
        anikotoMarkPrimaryUp($primary);
      }
      return $body;
    } catch (Exception $e) {
      $lastError = $e;
      if ($i < $count - 1) {
        error_log('anikoto: ' . $host . $path . ' failed (' . $e->getMessage() . '), trying next mirror');
      }
    }
  }

  throw $lastError ?: new Exception("anikoto request failed: $path");
}

/** Move $host to the front of $hosts (no-op when absent). */
function anikotoReorderHosts(array $hosts, $host) {
  $idx = array_search($host, $hosts, true);
  if ($idx === false || $idx === 0) return $hosts;
  unset($hosts[$idx]);
  return array_values(array_merge([$host], $hosts));
}

/** Path of the sticky-failure flag file (best effort; '' when unavailable). */
function anikotoStickyFile() {
  if (!defined('CACHE_DIR')) return '';
  $dir = CACHE_DIR;
  if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return '';
  return $dir . '/anikoto_host.sticky';
}

/**
 * The backup host when the primary failed within the last 10 minutes, else null.
 * Best effort: any read problem simply returns null (try primary first).
 */
function anikotoStickyHost($primary, $backup) {
  $file = anikotoStickyFile();
  if ($file === '' || !is_file($file)) return null;
  $raw = @file_get_contents($file);
  if ($raw === false) return null;
  $parts = explode('|', $raw);
  if (count($parts) !== 3) return null;
  [$downHost, $stickyBackup, $ts] = $parts;
  if ($downHost !== $primary || $stickyBackup !== $backup) return null;
  if (time() - (int) $ts > 600) return null; // stale: retry the primary
  return $backup;
}

/** Remember that the primary is down so the next request starts on the backup. */
function anikotoMarkPrimaryDown($primary, $backup) {
  $file = anikotoStickyFile();
  if ($file === '') return;
  @file_put_contents($file, $primary . '|' . $backup . '|' . time(), LOCK_EX);
}

/** Clear the sticky flag once the primary answers again. */
function anikotoMarkPrimaryUp($primary) {
  $file = anikotoStickyFile();
  if ($file === '' || !is_file($file)) return;
  $raw = @file_get_contents($file);
  if ($raw !== false && str_starts_with($raw, $primary . '|')) {
    @unlink($file);
  }
}

function anikotoGetHome() {
  return anikotoParseHome(anikotoGet('/home'));
}

function anikotoGetIndex() {
  return anikotoParseIndex(anikotoGet('/'));
}

function anikotoGetById($id) {
  $isNumeric = preg_match('/^\d+$/', $id);
  $jsonData = null;
  $htmlData = null;
  $numericId = $isNumeric ? $id : null;

  if ($numericId) {
    try { $jsonData = anikotoParseAnimeFromJson(json_decode(httpGet(baseUrl('anikotoApi') . "/series/$numericId"), true)); } catch (Exception $e) {}
  }

  try {
    $htmlData = anikotoParseAnime(anikotoGet("/watch/" . ($numericId ?: $id)));
    if (!$numericId && ($htmlData['anime']['animeId'] ?? null)) $numericId = $htmlData['anime']['animeId'];
  } catch (Exception $e) {}

  if (!$jsonData && $numericId && !$isNumeric) {
    try { $jsonData = anikotoParseAnimeFromJson(json_decode(httpGet(baseUrl('anikotoApi') . "/series/$numericId"), true)); } catch (Exception $e) {}
  }

  if ($jsonData || $htmlData) return anikotoMergeAnimeData($jsonData, $htmlData);
  throw new Exception("Failed to fetch anime \"$id\" from all sources");
}

function anikotoGetAzList($sort = 'all', $page = 1) {
  $path = $sort === 'all' ? '/az-list' : "/az-list/$sort";
  $params = $page > 1 ? ['page' => $page] : [];
  return anikotoParseSearchFromHtml(anikotoGet($path, $params));
}

function anikotoGetGenre($name, $page = 1, $sort = null) {
  $params = ['page' => $page];
  if ($sort) $params['sort'] = $sort;
  return anikotoParseListPage(anikotoGet("/genre/$name", $params));
}

function anikotoGetCategory($name, $page = 1, $sort = null) {
  $params = ['page' => $page];
  if ($sort) $params['sort'] = $sort;
  return anikotoParseListPage(anikotoGet("/$name", $params));
}

function anikotoGetType($name, $page = 1, $sort = null) {
  $params = ['page' => $page];
  if ($sort) $params['sort'] = $sort;
  return anikotoParseListPage(anikotoGet("/type/" . strtolower($name), $params));
}

function anikotoGetNavMenu($providerName = 'anikoto') {
  return anikotoParseNavMenu(anikotoGet('/home'), $providerName);
}

function anikotoGetEpisodes($id) {
  $numericId = preg_match('/^\d+$/', $id) ? $id : null;
  if (!$numericId) {
    try {
      $parsed = anikotoParseAnime(anikotoGet("/watch/$id"));
      $numericId = $parsed['anime']['animeId'] ?? null;
    } catch (Exception $e) {}
  }
  if ($numericId) {
    return anikotoParseEpisodesFromJson(json_decode(httpGet(baseUrl('anikotoApi') . "/series/$numericId"), true));
  }
  throw new Exception("Could not determine numeric ID for \"$id\"");
}

function anikotoGetEpisode($id, $epNum) {
  $data = anikotoGetEpisodes($id);
  $n = (int)$epNum;
  $ep = null;
  foreach ($data['episodes'] as $e) {
    if ($e['number'] === $n) { $ep = $e; break; }
  }
  if (!$ep) throw new Exception("Episode $epNum not found. Total: {$data['totalEpisodes']}.");
  return ['malId' => $data['malId'], 'alId' => $data['alId'], 'episode' => $ep];
}

function anikotoSearch($q, $page = 1, $filters = []) {
  $params = array_merge(['keyword' => $q, 'page' => $page], $filters);
  anikotoResolveGenres($params);
  $html = anikotoGet('/filter', $params);
  $result = anikotoParseSearchFromHtml($html);
  $result['searchQuery'] = $q;
  $result['searchFilters'] = $filters;
  return $result;
}

function anikotoBrowse($filters = [], $page = 1) {
  $keyword = $filters['keyword'] ?? '';
  unset($filters['keyword']);
  $params = array_merge(['keyword' => $keyword, 'page' => $page], $filters);
  anikotoResolveGenres($params);
  $html = anikotoGet('/filter', $params);
  $result = anikotoParseSearchFromHtml($html);
  $result['filters'] = array_merge(['keyword' => $keyword ?: null], $filters);
  return $result;
}

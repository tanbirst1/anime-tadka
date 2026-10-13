<?php

declare(strict_types=1);

/**
 * CLI scraper.
 *
 * Usage:
 *   php bin/scrape.php home
 *   php bin/scrape.php latest [all|series|movies] [page]
 *   php bin/scrape.php search "naruto"
 *   php bin/scrape.php anime "naruto-shippuden-..."
 *   php bin/scrape.php episodes "naruto-shippuden-..."
 *   php bin/scrape.php episode "35278977b149"
 *   php bin/scrape.php movie "34d9ed3a8bd4"
 *   php bin/scrape.php category type series [page]
 *   php bin/scrape.php category lang hindi [page]
 *   php bin/scrape.php category genre action [page]
 *   php bin/scrape.php languages|genres|studios
 */

use AnimeTadka\Bootstrap;

require dirname(__DIR__) . '/src/autoload.php';

$args = array_slice($argv, 1);
$command = $args[0] ?? 'help';

$config = Bootstrap::config();
$scraper = Bootstrap::scraper($config);

try {
    switch ($command) {
        case 'home':
            $data = $scraper->getHome();
            break;

        case 'latest':
            $type = $args[1] ?? 'all';
            $page = (int) ($args[2] ?? 1);
            $data = $scraper->getLatest($type, $page);
            break;

        case 'search':
            $query = $args[1] ?? '';
            if ($query === '') {
                throw new RuntimeException('Usage: php bin/scrape.php search "query"');
            }
            $data = $scraper->search($query);
            break;

        case 'anime':
            $slug = $args[1] ?? '';
            if ($slug === '') {
                throw new RuntimeException('Usage: php bin/scrape.php anime "slug"');
            }
            $data = $scraper->getAnime($slug);
            break;

        case 'episodes':
            $slug = $args[1] ?? '';
            if ($slug === '') {
                throw new RuntimeException('Usage: php bin/scrape.php episodes "slug"');
            }
            $data = $scraper->getAnimeEpisodes($slug);
            break;

        case 'episode':
            $hash = $args[1] ?? '';
            if ($hash === '') {
                throw new RuntimeException('Usage: php bin/scrape.php episode "hash"');
            }
            $data = $scraper->getEpisode($hash);
            break;

        case 'movie':
            $hash = $args[1] ?? '';
            if ($hash === '') {
                throw new RuntimeException('Usage: php bin/scrape.php movie "hash"');
            }
            $data = $scraper->getMovie($hash);
            break;

        case 'category':
            $type = $args[1] ?? '';
            $slug = $args[2] ?? '';
            $page = (int) ($args[3] ?? 1);
            if ($type === '' || $slug === '') {
                throw new RuntimeException('Usage: php bin/scrape.php category {type|lang|genre|studio|sub} "slug" [page]');
            }
            $data = $scraper->getCategory($type, $slug, $page);
            break;

        case 'languages':
            $data = ['languages' => $scraper->getLanguages()];
            break;

        case 'genres':
            $data = ['genres' => $scraper->getGenres()];
            break;

        case 'studios':
            $data = ['studios' => $scraper->getStudios()];
            break;

        case 'help':
        default:
            echo file_get_contents(__FILE__) === false ? '' : '';
            $usage = <<<TXT
AnimeTadka CLI scraper

Usage:
  php bin/scrape.php home
  php bin/scrape.php latest [all|series|movies] [page]
  php bin/scrape.php search "query"
  php bin/scrape.php anime "slug"
  php bin/scrape.php episodes "slug"
  php bin/scrape.php episode "hash"
  php bin/scrape.php movie "hash"
  php bin/scrape.php category {type|lang|genre|studio|sub} "slug" [page]
  php bin/scrape.php languages|genres|studios

TXT;
            echo $usage;
            exit(0);
    }

    echo json_encode(['success' => true, 'data' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
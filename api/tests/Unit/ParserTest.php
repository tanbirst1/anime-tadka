<?php

declare(strict_types=1);

namespace AnimeTadka\Tests;

use AnimeTadka\Scraper\HtmlParser;
use AnimeTadka\Scraper\Parsers\AnimeParser;
use AnimeTadka\Scraper\Parsers\CategoryParser;
use AnimeTadka\Scraper\Parsers\EpisodeParser;
use AnimeTadka\Scraper\Parsers\ExtraParser;
use AnimeTadka\Scraper\Parsers\HomeParser;
use AnimeTadka\Scraper\Parsers\ProxyParser;
use AnimeTadka\Scraper\Parsers\SearchParser;
use AnimeTadka\Scraper\Parsers\TopParser;

/**
 * Parser tests against saved HTML fixtures — no network access.
 */
final class ParserTest extends TestCase
{
    private string $fixtures;

    public function setUp(): void
    {
        $this->fixtures = dirname(__DIR__) . '/fixtures';
    }

    private function parser(string $file, string $base = 'https://animetadka.com/'): HtmlParser
    {
        $html = (string) file_get_contents($this->fixtures . '/' . $file);
        return new HtmlParser($html, $base);
    }

    // ---------------------------------------------------------------- Home

    public function testHomeSpotlightExtraction(): void
    {
        $data = (new HomeParser($this->parser('home.html')))->parse();

        $this->assertNotEmpty($data['spotlight']);
        $first = $data['spotlight'][0];
        $this->assertStringContains('#1', (string) $first['rank']);
        $this->assertNotEmpty($first['badge']);
        $this->assertNotEmpty($first['genres']);
        $this->assertNotEmpty($first['description']);
        $this->assertStringContains('animetadka.com', (string) $first['watch_url']);
        $this->assertStringContains('animetadka.com', (string) $first['details_url']);
    }

    public function testHomeSpotlightImages(): void
    {
        $data = (new HomeParser($this->parser('home.html')))->parse();

        $first = $data['spotlight'][0];
        $this->assertNotEmpty($first['banner']);
        $this->assertNotEmpty($first['poster']);
        $this->assertStringContains('animetadka.com', (string) $first['banner']);
        $this->assertStringContains('animetadka.com', (string) $first['poster']);
        // Image URLs are serveproxy.com proxied; inner URL must have no query/resize
        $this->assertStringContains('serveproxy.com', (string) $first['banner']);
        $this->assertImageHasNoInnerQuery((string) $first['banner']);
        $this->assertImageHasNoInnerQuery((string) $first['poster']);
        $this->assertStringNotContains('-150x150', (string) $first['banner']);
    }

    public function testTopDay(): void
    {
        $data = (new TopParser($this->parser('top-day.html')))->parse();

        $this->assertNotEmpty($data);
        $this->assertGreaterThanOrEqual(10, count($data));

        $first = $data[0];
        $this->assertEquals('01', $first['rank']);
        $this->assertNotEmpty($first['title']);
        $this->assertStringContains('animetadka.com', (string) $first['url']);
        $this->assertStringContains('animetadka.com', (string) $first['poster']);
        $this->assertImageHasNoInnerQuery((string) $first['poster']);
        $this->assertStringNotContains('-150x150', (string) $first['poster']);
        $this->assertNotEmpty($first['languages']);
    }

    public function testTopWeekAndMonth(): void
    {
        foreach (['week', 'month'] as $range) {
            $data = (new TopParser($this->parser('top-' . $range . '.html')))->parse();
            $this->assertGreaterThanOrEqual(10, count($data), "{$range} should have 10 items");
            $this->assertNotEmpty($data[0]['title']);
            $this->assertStringContains('animetadka.com', (string) $data[0]['url']);
        }
    }

    public function testTopRanksSequential(): void
    {
        $data = (new TopParser($this->parser('top-day.html')))->parse();
        $ranks = array_column($data, 'rank');
        $this->assertEquals('01', $ranks[0]);
        $this->assertEquals('10', $ranks[9]);
    }

    public function testHomeLatestGrids(): void
    {
        $data = (new HomeParser($this->parser('home.html')))->parse();

        foreach (['all', 'series', 'movies'] as $grid) {
            $this->assertNotEmpty($data['latest'][$grid], "grid {$grid} should have items");
        }

        $card = $data['latest']['all'][0];
        $this->assertNotEmpty($card['title']);
        $this->assertStringContains('animetadka.com', (string) $card['url']);
        $this->assertStringContains('animetadka.com', (string) $card['poster']);
        $this->assertNotEmpty($card['episode_badge']);
        $this->assertNotEmpty($card['languages_badge']);

        // Poster URLs are serveproxy.com proxied; inner must be clean.
        $this->assertImageHasNoInnerQuery((string) $card['poster']);
        $this->assertStringNotContains('-150x150', (string) $card['poster']);
        $this->assertStringContains('.webp', (string) $card['poster']);
    }

    public function testHomeGridImagesNormalized(): void
    {
        $data = (new HomeParser($this->parser('home.html')))->parse();

        foreach ($data['latest']['all'] as $item) {
            $this->assertImageHasNoInnerQuery((string) $item['poster']);
            $this->assertStringNotContains('-150x150', (string) $item['poster'], 'poster should have no resize suffix');
            $this->assertStringNotContains('-300x', (string) $item['poster']);
        }
    }

    public function testHomeLanguages(): void
    {
        $data = (new HomeParser($this->parser('home.html')))->parse();

        $this->assertNotEmpty($data['languages']);
        $names = array_column($data['languages'], 'name');
        $this->assertTrue(in_array('Hindi', $names, true));
        $this->assertTrue(in_array('Tamil', $names, true));
        $this->assertStringContains('animetadka.com/lang/', (string) $data['languages'][0]['url']);
    }

    // -------------------------------------------------------------- Search

    public function testSearchResults(): void
    {
        $data = (new SearchParser($this->parser('search.html')))->parse();

        $this->assertNotEmpty($data);
        $first = $data[0];
        $this->assertNotEmpty($first['title']);
        $this->assertStringContains('animetadka.com', (string) $first['url']);
        $this->assertNotEmpty($first['poster']);
        $this->assertNotEmpty($first['languages']);
        $this->assertNotEmpty($first['type']);
    }

    public function testSearchFindsNaruto(): void
    {
        $data = (new SearchParser($this->parser('search.html')))->parse();
        $titles = array_column($data, 'title');
        $this->assertTrue(
            count(array_filter($titles, static fn ($t) => stripos((string) $t, 'naruto') !== false)) >= 2,
            'Expected multiple Naruto results'
        );
    }

    public function testSearchPosterNormalized(): void
    {
        $data = (new SearchParser($this->parser('search.html')))->parse();

        foreach ($data as $item) {
            $this->assertStringContains('animetadka.com', (string) $item['poster']);
            $this->assertImageHasNoInnerQuery((string) $item['poster']);
            $this->assertStringNotContains('-150x150', (string) $item['poster'], 'search thumbnails should resolve to full size');
        }
    }

    // --------------------------------------------------------------- Anime

    public function testAnimeMetadata(): void
    {
        $data = (new AnimeParser($this->parser('anime.html')))->parse();

        $this->assertEquals('Naruto Shippūden', $data['title']);
        $this->assertStringContains('animetadka.com', (string) $data['poster']);
        $this->assertStringContains('animetadka.com', (string) $data['banner']);
        $this->assertImageHasNoInnerQuery((string) $data['poster']);
        $this->assertNotEmpty($data['description']);
        $this->assertStringContains('Konoha', (string) $data['description']);
    }

    public function testAnimeGenres(): void
    {
        $data = (new AnimeParser($this->parser('anime.html')))->parse();
        $names = array_column($data['genres'], 'name');
        $this->assertTrue(in_array('Action', $names, true));
        $this->assertTrue(in_array('Adventure', $names, true));
    }

    public function testAnimeMetaRows(): void
    {
        $data = (new AnimeParser($this->parser('anime.html')))->parse();
        $meta = $data['meta'];

        $this->assertNotEmpty($meta['audio_languages']);
        $this->assertNotEmpty($meta['subtitles']);
        $this->assertNotEmpty($meta['studio']);
        $this->assertNotEmpty($meta['quality']);
        $this->assertNotEmpty($meta['encode']);

        $langNames = array_column((array) $meta['audio_languages'], 'name');
        $this->assertTrue(in_array('Hindi', $langNames, true));
    }

    public function testAnimeLatestEpisode(): void
    {
        $data = (new AnimeParser($this->parser('anime.html')))->parse();
        $this->assertNotNull($data['latest_episode']);
        $this->assertStringContains('S17-E368', (string) $data['latest_episode']['label']);
        $this->assertStringContains('/ep/', (string) $data['latest_episode']['url']);
    }

    public function testAnimeExternalIds(): void
    {
        $data = (new AnimeParser($this->parser('anime.html')))->parse();
        $this->assertEquals('1735', $data['external_ids']['mal']);
        $this->assertEquals('31910', $data['external_ids']['tmdb']);
    }

    public function testAnimeSeasonsAndEpisodes(): void
    {
        $data = (new AnimeParser($this->parser('anime.html')))->parse();

        $this->assertNotEmpty($data['seasons']);
        $first = $data['seasons'][0];
        $this->assertEquals(1, $first['season']);
        $this->assertStringContains('S1:', (string) $first['title']);
        $this->assertStringContains('Episodes', (string) $first['episode_count']);
        $this->assertNotEmpty($first['episodes']);

        $ep = $first['episodes'][0];
        $this->assertStringContains('S1-E1', (string) $ep['label']);
        $this->assertStringContains('/ep/', (string) $ep['url']);
        $this->assertNotNull($ep['id']);
        $this->assertStringContains('image.tmdb.org', (string) $ep['thumbnail']);
    }

    // ------------------------------------------------------------- Episode

    public function testEpisodeMetadata(): void
    {
        $data = (new EpisodeParser($this->parser('episode.html')))->parse();

        $this->assertStringContains('S1-E1:', (string) $data['title']['accent']);
        $this->assertEquals('Homecoming', $data['title']['title']);
        $this->assertEquals('8.3', $data['rating']);
        $this->assertEquals('23m', $data['runtime']);
        $this->assertNotEmpty($data['description']);
        $this->assertStringContains('/watch.php', (string) $data['player_url']);
    }

    public function testEpisodeBackLink(): void
    {
        $data = (new EpisodeParser($this->parser('episode.html')))->parse();
        $this->assertNotNull($data['back_link']);
        $this->assertStringContains('naruto-shippuden', (string) $data['back_link']['url']);
    }

    public function testEpisodeServers(): void
    {
        $data = (new EpisodeParser($this->parser('episode.html')))->parse();

        $this->assertNotEmpty($data['servers']);
        $this->assertGreaterThanOrEqual(3, count($data['servers']));

        $urls = array_column($data['servers'], 'url');
        $this->assertTrue(
            count(array_filter($urls, static fn ($u) => str_contains((string) $u, '/watch.php?'))) >= 3,
            'Expected at least 3 watch.php server URLs'
        );
    }

    public function testEpisodeNextAndDownloads(): void
    {
        $data = (new EpisodeParser($this->parser('episode.html')))->parse();

        $this->assertStringContains('/ep/', (string) $data['next_episode_url']);
        $this->assertNotEmpty($data['download_links']);
        $this->assertStringContains('/redirect/download/', (string) $data['download_links'][0]['url']);
    }

    // --------------------------------------------------------------- Movie

    public function testAnimeMovieDetailExposesWatchUrl(): void
    {
        $data = (new AnimeParser($this->parser('movie-detail.html')))->parse();

        // A movie detail page has no season blocks; its player lives behind
        // the "WATCH MOVIE NOW" button, which must be surfaced as watch_url.
        $this->assertEquals('https://animetadka.com/movie/414690d3ea71/', $data['watch_url']);
        $this->assertEquals([], $data['seasons']);
    }

    public function testAnimeSeriesHasNoWatchUrl(): void
    {
        // Series detail pages list episodes, so watch_url stays null.
        $data = (new AnimeParser($this->parser('anime.html')))->parse();
        $this->assertNull($data['watch_url']);
    }

    public function testMoviePage(): void
    {
        $data = (new EpisodeParser($this->parser('movie.html')))->parse();

        $this->assertStringContains('Demon Slayer', (string) $data['title']['accent']);
        $this->assertStringContains('Full Movie', (string) $data['title']['title']);
        $this->assertNotEmpty($data['rating']);
        $this->assertNotEmpty($data['runtime']);
        $this->assertNotEmpty($data['servers']);
        // The movie's back link points to its anime detail page.
        $this->assertStringContains('demon-slayer', (string) $data['back_link']['url']);
    }

    // ------------------------------------------------------------ Category

    public function testCategorySeries(): void
    {
        $data = (new CategoryParser($this->parser('category-series.html')))->parse();

        $this->assertStringContains('Serie', (string) $data['title']);
        $this->assertNotEmpty($data['items']);
        $this->assertStringContains('animetadka.com', (string) $data['items'][0]['url']);
    }

    public function testCategoryPagination(): void
    {
        $data = (new CategoryParser($this->parser('category-series.html')))->parse();

        $this->assertEquals(1, $data['pagination']['current']);
        $this->assertGreaterThanOrEqual(19, (int) $data['pagination']['total']);
        $this->assertStringContains('/page/2/', (string) $data['pagination']['next']);
    }

    public function testCategoryHindi(): void
    {
        $data = (new CategoryParser($this->parser('category-hindi.html')))->parse();
        $this->assertNotEmpty($data['items']);
        $this->assertNotEmpty($data['items'][0]['title']);
    }

    // ------------------------------------------------------------- Extra

    public function testExtraMenu(): void
    {
        $data = (new ExtraParser($this->parser('home.html')))->parse();

        $this->assertNotEmpty($data['menu']);
        $labels = array_column($data['menu'], 'label');
        $this->assertTrue(in_array('App', $labels, true));
        $this->assertTrue(in_array('Series', $labels, true));
        $this->assertTrue(in_array('Movies', $labels, true));
        $this->assertTrue(in_array('Languages', $labels, true));
        $this->assertTrue(in_array('Genres', $labels, true));

        // First item is a direct link with children empty.
        $this->assertEquals('App', $data['menu'][0]['label']);
        $this->assertStringContains('animetadka.com', (string) $data['menu'][0]['url']);
        $this->assertEquals([], $data['menu'][0]['children']);

        // Accordion blocks carry sub-links.
        $languages = null;
        foreach ($data['menu'] as $entry) {
            if ($entry['label'] === 'Languages') {
                $languages = $entry;
            }
        }
        $this->assertNotNull($languages);
        $this->assertNotEmpty($languages['children']);
        $childLabels = array_column($languages['children'], 'label');
        $this->assertTrue(in_array('Hindi', $childLabels, true));
        $hindiUrl = null;
        foreach ($languages['children'] as $child) {
            if ($child['label'] === 'Hindi') {
                $hindiUrl = $child['url'];
            }
        }
        $this->assertStringContains('animetadka.com/lang/hindi/', (string) $hindiUrl);
    }

    public function testExtraLogosAndFavicon(): void
    {
        $data = (new ExtraParser($this->parser('home.html')))->parse();

        $this->assertStringContains('animetadka.com', (string) $data['header_logo']);
        $this->assertImageHasNoInnerQuery((string) $data['header_logo']);
        $this->assertStringContains('.webp', (string) $data['header_logo']);

        $this->assertStringContains('9525.png', (string) $data['site_logo']);

        $this->assertStringContains('9525.png', (string) $data['favicon']);
        $this->assertImageHasNoInnerQuery((string) $data['favicon']);

        $this->assertNotEmpty($data['icons']);
        $this->assertStringContains('9525.png', (string) $data['icons'][0]['url']);
        $this->assertImageHasNoInnerQuery((string) $data['icons'][0]['url']);
    }

    public function testImageUrlNormalization(): void
    {
        $parser = $this->parser('home.html', 'https://animetadka.com/');

        // i0.wp.com URLs: strip ?fit=, unwrap CDN, wrap with serveproxy.com
        $this->assertEquals(
            'https://serveproxy.com/?url=https%3A%2F%2Fanimetadka.com%2Fwp-content%2Fuploads%2F2026%2F07%2Fcaptain-tsubasa-6a4f4c484d551-1.webp',
            $parser->normalizeImageUrl('https://i0.wp.com/animetadka.com/wp-content/uploads/2026/07/captain-tsubasa-6a4f4c484d551-1.webp?fit=214%2C300&ssl=1')
        );
        // Direct URLs: strip -WxH suffix, wrap with serveproxy.com
        $this->assertEquals(
            'https://serveproxy.com/?url=https%3A%2F%2Fanimetadka.com%2Fwp-content%2Fuploads%2F2026%2F03%2FmvYznVlIPU3cfcbG7dXh1fBONcc.jpg',
            $parser->normalizeImageUrl('https://animetadka.com/wp-content/uploads/2026/03/mvYznVlIPU3cfcbG7dXh1fBONcc-150x150.jpg')
        );
        // Relative URLs: make absolute, wrap with serveproxy.com
        $this->assertEquals(
            'https://serveproxy.com/?url=https%3A%2F%2Fanimetadka.com%2Fwp-content%2Fuploads%2F2026%2F06%2F6077-scaled-1.webp',
            $parser->normalizeImageUrl('/wp-content/uploads/2026/06/6077-scaled-1.webp')
        );
        // Non-image URLs keep their query strings.
        $this->assertEquals(
            'https://animetadka.com/watch.php?toonx=abc',
            $parser->normalizeUrl('https://animetadka.com/watch.php?toonx=abc')
        );
    }

    // ------------------------------------------------------- URL handling

    public function testUrlNormalization(): void
    {
        $parser = $this->parser('home.html', 'https://animetadka.com/home/');

        $this->assertEquals('https://animetadka.com/ep/abc/', $parser->normalizeUrl('/ep/abc/'));
        $this->assertEquals('https://animetadka.com/ep/abc/', $parser->normalizeUrl('https://animetadka.com/ep/abc/'));
        $this->assertEquals('https://animetadka.com/x', $parser->normalizeUrl('//animetadka.com/x'));
        $this->assertEquals('https://animetadka.com/home/y', $parser->normalizeUrl('y'));
    }

    public function testMalformedHtmlDoesNotCrash(): void
    {
        $parser = new HtmlParser('<html><body><div class="anime-card"><a href="/x/">broken', 'https://animetadka.com/');
        $this->assertNotNull($parser->first('//div[contains(@class,"anime-card")]'));
    }

    public function testMissingSelectorsReturnNull(): void
    {
        $parser = $this->parser('home.html');
        $this->assertNull($parser->firstText(['//div[contains(@class,"does-not-exist")]']));
        $this->assertNull($parser->firstUrl(['//a[@id="nope"]/@href']));
    }

    public function testEmptyHtmlThrows(): void
    {
        $threw = false;
        try {
            new HtmlParser('', 'https://animetadka.com/');
        } catch (\AnimeTadka\Exceptions\ParseException) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected ParseException for empty HTML');
    }

    // -------------------------------------------------------------- Proxy

    private function fixtureContent(string $file): string
    {
        return (string) file_get_contents($this->fixtures . '/' . $file);
    }

    public function testProxyExtractIframeSource(): void
    {
        $html = $this->fixtureContent('watch.html');

        $this->assertEquals(
            'https://as-cdn21.top/video/49182f81e6a13cf5eaa496d51fea6406',
            ProxyParser::extractIframeSource($html)
        );
    }

    public function testProxyExtractIframeSourceChallengeNull(): void
    {
        $html = $this->fixtureContent('watch-challenge.html');

        $this->assertNull(ProxyParser::extractIframeSource($html));
    }

    public function testProxyIsCloudflareChallenge(): void
    {
        $html = $this->fixtureContent('watch-challenge.html');

        $this->assertTrue(ProxyParser::isCloudflareChallenge($html));
    }

    public function testProxyIsCloudflareChallengeFalse(): void
    {
        $html = $this->fixtureContent('watch.html');

        $this->assertFalse(ProxyParser::isCloudflareChallenge($html));
    }

    public function testProxyParseDownloadPage(): void
    {
        $html = $this->fixtureContent('download.html');
        $page = ProxyParser::parseDownloadPage($html);

        $this->assertStringContains('Jujutsu Kaisen', $page['title']);
        $this->assertStringContains('Jujutsu Kaisen', $page['filename']);
        $this->assertStringNotContains(' - BoxDrive', $page['filename']);
        $this->assertGreaterThanOrEqual(3, count($page['mirrors']));

        $first = $page['mirrors'][0];
        $this->assertEquals('Voe DL', $first['name']);
        $this->assertEquals('https://voe.sx/r8zr0spjhpq6', $first['url']);
    }

    private function assertImageHasNoInnerQuery(string $url): void
    {
        // serveproxy.com wrapper is https://serveproxy.com/?url=<encoded>
        $this->assertStringContains('serveproxy.com', $url);
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $qs);
        $inner = $qs['url'] ?? '';
        $decoded = rawurldecode($inner);
        $this->assertStringNotContains('?', $decoded, 'inner image URL should have no query string');
    }
}
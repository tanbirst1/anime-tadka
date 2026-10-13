<?php
/**
 * Landing page controller — the glassmorphism splash at /
 */
class LandingController {
    public function index() {
        $provider = api_provider();
        $seo = $this->loadSeo($provider);
        $isAnimetadka = ($provider === 'animetadka');

        $data = [
            'title' => Config::get('site_name') . ' - Your Ultimate Destination for Premium Regional Dubbed Anime',
            'description' => Config::get('site_name') . ': Watch and Download Hindi Dubbed Anime online for free.',
            'isAnimetadka' => $isAnimetadka,
            'languages' => $this->getLanguages(),
            'genres' => $this->getGenres($provider),
            'topBarLinks' => $isAnimetadka ? $this->getLanguageTopBar() : $this->getGenreTopBar($provider),
            'topSearches' => $seo['topSearches'],
            'indexContent' => ['intro' => $seo['intro'], 'sections' => $seo['sections']],
            'shareUrl' => urlencode(Config::get('base_url')),
            'shareText' => urlencode('Watch or Download Animes Free on ' . Config::get('site_name') . ' Watch in Hindi, Tamil, Telugu Languages 🔥! ' . Config::get('base_url')),
        ];

        View::render('pages/landing', $data, 'landing');
    }

    /**
     * Language cards for animetadka provider (4 items).
     */
    private function getLanguages(): array {
        return [
            ['name' => 'Hindi', 'slug' => 'hindi', 'image' => '/assets/images/landing/hindi.webp'],
            ['name' => 'Tamil', 'slug' => 'tamil', 'image' => '/assets/images/landing/tamil.webp'],
            ['name' => 'Telugu', 'slug' => 'telugu', 'image' => '/assets/images/landing/telugu.webp'],
            ['name' => 'English', 'slug' => 'english', 'image' => '/assets/images/landing/english.webp'],
        ];
    }

    /**
     * Top bar links for animetadka: APP + languages.
     */
    private function getLanguageTopBar(): array {
        return [
            ['label' => 'APP', 'href' => '/home'],
            ['label' => 'HINDI', 'href' => '/lang/hindi'],
            ['label' => 'TAMIL', 'href' => '/lang/tamil'],
            ['label' => 'TELUGU', 'href' => '/lang/telugu'],
        ];
    }

    /**
     * Top bar links for non-animetadka: APP + genres (3 genre links).
     */
    private function getGenreTopBar(string $provider): array {
        $genres = $this->getGenres($provider);
        $links = [['label' => 'APP', 'href' => '/home']];
        foreach (array_slice($genres, 0, 3) as $g) {
            $links[] = ['label' => strtoupper($g['name']), 'href' => $g['href']];
        }
        return $links;
    }

    /**
     * Genre cards for non-animetadka providers (4 items).
     * Uses the original language background images as fallback.
     */
    private function getGenres(string $provider): array {
        $genreList = [
            ['name' => 'Action', 'slug' => 'action', 'image' => '/assets/images/landing/hindi.webp'],
            ['name' => 'Drama', 'slug' => 'drama', 'image' => '/assets/images/landing/tamil.webp'],
            ['name' => 'Comedy', 'slug' => 'comedy', 'image' => '/assets/images/landing/telugu.webp'],
            ['name' => 'Fantasy', 'slug' => 'fantasy', 'image' => '/assets/images/landing/english.webp'],
        ];

        $result = [];
        foreach ($genreList as $g) {
            $result[] = [
                'name'  => $g['name'],
                'slug'  => $g['slug'],
                'href'  => '/genre/' . $g['slug'],
                'image' => $g['image'],
            ];
        }
        return $result;
    }

    /**
     * Load SEO data from data/seo/{provider}.json.
     * Replaces {site_name} placeholder with Config::get('site_name').
     */
    private function loadSeo(string $provider): array {
        $file = ROOT_PATH . "/data/seo/$provider.json";

        if (!is_file($file)) {
            return $this->defaultSeo();
        }

        $raw = json_decode(file_get_contents($file), true);
        if (!is_array($raw)) {
            return $this->defaultSeo();
        }

        // Replace {site_name} placeholder with actual site name
        $siteName = Config::get('site_name');
        $raw = $this->replacePlaceholders($raw, $siteName);

        // Build top searches from JSON
        $topSearches = [];
        foreach ($raw['topSearches'] ?? [] as $term) {
            if (is_string($term) && $term !== '') {
                $topSearches[] = [
                    'term' => $term,
                    'url' => '/search?s=' . urlencode($term),
                ];
            }
        }

        return [
            'topSearches' => !empty($topSearches) ? $topSearches : $this->defaultSeo()['topSearches'],
            'intro' => $raw['intro'] ?? [],
            'sections' => $raw['sections'] ?? [],
        ];
    }

    /**
     * Recursively replace {site_name} in all string values of an array.
     */
    private function replacePlaceholders($data, string $siteName) {
        if (is_string($data)) {
            return str_replace('{site_name}', $siteName, $data);
        }
        if (is_array($data)) {
            return array_map(fn($v) => $this->replacePlaceholders($v, $siteName), $data);
        }
        return $data;
    }

    /**
     * Default SEO data (animetadka fallback).
     */
    private function defaultSeo(): array {
        return [
            'topSearches' => [
                ['term' => 'Naruto Shippuden', 'url' => '/search?s=Naruto+Shippuden'],
                ['term' => 'Naruto', 'url' => '/search?s=Naruto'],
                ['term' => 'One Piece', 'url' => '/search?s=One+Piece'],
                ['term' => 'Tokyo Revengers', 'url' => '/search?s=Tokyo+Revengers'],
                ['term' => 'Solo Leveling', 'url' => '/search?s=Solo+Leveling'],
                ['term' => 'Detective Conan', 'url' => '/search?s=Detective+Conan'],
                ['term' => 'Jujutsu Kaisen', 'url' => '/search?s=Jujutsu+Kaisen'],
                ['term' => 'Attack on Titan', 'url' => '/search?s=Attack+on+Titan'],
            ],
            'intro' => [],
            'sections' => [],
        ];
    }
}

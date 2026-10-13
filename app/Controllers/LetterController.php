<?php
class LetterController {
    public function index($letter, $page = 1, $allowFixture = true) {
        $letterKey = strtolower(trim($letter));
        $page = max(1, (int) ($_GET['page'] ?? $page ?? 1));
        $filter = strtolower(trim($_GET['type'] ?? ''));
        if (!in_array($filter, ['series', 'movies'], true)) {
            $filter = 'series';
        }

        // Live data from the API first; fall back to fixtures when the
        // source page is unreachable or returns no cards.
        $api = api_letter_items($letterKey, $page, $filter);
        if (!empty($api['items'])) {
            $anime = $api['items'];
            $pagination = [
                'current' => $api['pagination']['current'] ?? $page,
                'total' => $api['pagination']['total'] ?? 1,
                'baseUrl' => View::hurl('/letter/' . $letterKey),
            ];
        } elseif ($allowFixture) {
            $anime = $this->fixtureItems($letterKey);
            $totalPages = max(1, (int) ceil(count($anime) / 24));
            $page = min($page, $totalPages);
            $anime = array_slice($anime, ($page - 1) * 24, 24);
            $pagination = [
                'current' => $page,
                'total' => $totalPages,
                'baseUrl' => View::hurl('/letter/' . $letterKey),
            ];
        } else {
            // Fixture fallback suppressed (hentai section): show an empty
            // result rather than unrelated main-site fixture content.
            $anime = [];
            $pagination = [
                'current' => $page,
                'total' => 1,
                'baseUrl' => View::hurl('/letter/' . $letterKey),
            ];
        }

        $pageTitle = api_provider() === 'watchhentai'
            ? 'Series starting with ' . strtoupper($letter)
            : 'Anime Letter ' . strtoupper($letter);
        $data = [
            'title' => $pageTitle . ' - ' . Config::get('site_name'),
            'letter' => $letter,
            'anime' => $anime,
            'pagination' => $pagination,
            'activeType' => $filter,
            'toggleBase' => '/letter/' . $letterKey,
        ];
        View::render('catalog/letter', $data, 'main');
    }

    /**
     * Fixture fallback listing for a letter (used when the API is unavailable).
     */
    private function fixtureItems(string $letterKey): array {
        // Real Letter A cards scraped from the original site (letter-a page).
        $letterACards = [
            ['title' => 'A Couple of Cuckoos', 'slug' => 'a-couple-of-cuckoos-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S2-E12', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-01.jpg'],
            ['title' => 'A Galaxy Next Door', 'slug' => 'a-galaxy-next-door-hindi-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Hindi Sub', 'image' => '/assets/images/catalog/letter/letter-02.jpg'],
            ['title' => "A Gatherer's Adventure in Isekai", 'slug' => 'a-gatherers-adventure-in-isekai-hindi-dubbed-multi-audio-web-dl-episodes-watch-online', 'episode' => 'S1-E12', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-03.jpg'],
            ['title' => 'A Misanthrope Teaches a Class for Demi-Humans', 'slug' => 'a-misanthrope-teaches-a-class-for-demi-humans-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S1-E13', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-04.jpg'],
            ['title' => "A Record of a Mortal's Journey to Immortality", 'slug' => 'a-record-of-a-mortals-journey-to-immortality-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S1-E21', 'audio' => 'Hin-Chin', 'image' => '/assets/images/catalog/letter/letter-05.jpg'],
            ['title' => 'A Salad Bowl of Eccentrics', 'slug' => 'a-salad-bowl-of-eccentrics-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Hin-Jpn', 'image' => '/assets/images/catalog/letter/letter-06.jpg'],
            ['title' => 'A Sign of Affection', 'slug' => 'a-sign-of-affection-hindi-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Hindi SUB', 'image' => '/assets/images/catalog/letter/letter-07.jpg'],
            ['title' => 'Absolute Duo', 'slug' => 'absolute-duo-hindi-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Hindi Sub', 'image' => '/assets/images/catalog/letter/letter-08.jpg'],
            ['title' => "Adam's Sweet Agony", 'slug' => 'adams-sweet-agony-hindi-dubbed-multi-audio-web-dl-uncensored-episodes-watch-online-download', 'episode' => 'S1-E8', 'audio' => 'UNCENSORED', 'image' => '/assets/images/catalog/letter/letter-09.jpg'],
            ['title' => 'After the Rain', 'slug' => 'after-the-rain-hindi-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Hindi Sub', 'image' => '/assets/images/catalog/letter/letter-10.jpg'],
            ['title' => 'Agents of the Four Seasons: Dance of Spring', 'slug' => 'agents-of-the-four-seasons-dance-of-spring-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S1-E14', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-11.jpg'],
            ['title' => 'Aharen-san wa Hakarenai', 'slug' => 'aharen-san-wa-hakarenai-hindi-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S2-E12', 'audio' => 'Hindi Sub', 'image' => '/assets/images/catalog/letter/letter-12.jpg'],
            ['title' => 'AHO-GIRL', 'slug' => 'aho-girl-hindi-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Hindi Sub', 'image' => '/assets/images/catalog/letter/letter-13.jpg'],
            ['title' => 'Akame ga Kill!', 'slug' => 'akame-ga-kill-hindi-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S1-E24', 'audio' => 'Eng-Jpn', 'image' => '/assets/images/catalog/letter/letter-14.jpg'],
            ['title' => 'Akashic Records of Bastard Magic Instructor', 'slug' => 'akashic-records-of-bastard-magic-instructor-english-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Eng-Jpn', 'image' => '/assets/images/catalog/letter/letter-15.jpg'],
            ['title' => 'Akiba Maid War', 'slug' => 'akiba-maid-war-hindi-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Hindi Sub', 'image' => '/assets/images/catalog/letter/letter-16.jpg'],
            ['title' => 'Alya Sometimes Hides Her Feelings in Russian', 'slug' => 'alya-sometimes-hides-her-feelings-in-russian-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-17.jpg'],
            ['title' => 'Ameku M.D.: Doctor Detective', 'slug' => 'ameku-m-d-doctor-detective-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Hin-Eng-Jpn', 'image' => '/assets/images/catalog/letter/letter-18.jpg'],
            ['title' => "An Adventurer's Daily Grind at Age 29", 'slug' => 'an-adventurers-daily-grind-at-age-29-hindi-dubbed-multi-audio-web-dl-episodes-watch-online', 'episode' => 'S1-E12', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-19.jpg'],
            ['title' => "An Archdemon's Dilemma: How to Love Your Elf Bride", 'slug' => 'an-archdemons-dilemma-how-to-love-your-elf-bride-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-20.jpg'],
            ['title' => 'An Observation Log of My Fiancée Who Calls Herself a Villainess', 'slug' => 'an-observation-log-of-my-fiancee-who-calls-herself-a-villainess-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S1-E12', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-21.jpg'],
            ['title' => "Anyway, I'm Falling in Love with You.", 'slug' => 'anyway-im-falling-in-love-with-you-hindi-dubbed-multi-audio-episodes-watch-online-download', 'episode' => 'S1-E24', 'audio' => 'Eng-Jpn', 'image' => '/assets/images/catalog/letter/letter-22.jpg'],
            ['title' => "Arifureta: From Commonplace to World's Strongest", 'slug' => 'arifureta-from-commonplace-to-worlds-strongest-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S3-E16', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-23.jpg'],
            ['title' => 'Attack on Titan (Anime Times Dub)', 'slug' => 'attack-on-titan-hindi-dubbed-multi-audio-web-dl-episodes-watch-online-download', 'episode' => 'S4-E30', 'audio' => 'Multi Audio', 'image' => '/assets/images/catalog/letter/letter-24.jpg'],
        ];

        if ($letterKey === 'a') {
            return $letterACards;
        }

        $all = array_map('anime_view_data', fixtures_anime());
        $filtered = array_values(array_filter($all, function ($item) use ($letterKey) {
            $first = strtolower(substr($item['title'], 0, 1));
            if ($letterKey === '0-9') {
                return preg_match('/[0-9]/', $first) === 1;
            }
            return $first === $letterKey;
        }));
        return $filtered ?: $all;
    }
}
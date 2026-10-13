<?php
/**
 * Fixture data helpers — load sample data from data/fixtures/*.json
 * and map fixture records to the view data contract.
 */

/**
 * Load a JSON fixture file. Returns [] on missing file or parse error.
 */
function fixtures_load($name) {
    $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__));
    $file = $root . '/data/fixtures/' . $name . '.json';
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

/**
 * All anime fixtures.
 */
function fixtures_anime() {
    return fixtures_load('anime');
}

/**
 * All episode fixtures.
 */
function fixtures_episodes() {
    return fixtures_load('episodes');
}

/**
 * Derive an audio label from a slug (e.g. "hindi-dubbed" => "Hindi Dubbed").
 */
function anime_audio_from_slug($slug) {
    $slug = strtolower((string)$slug);
    $languages = ['hindi', 'tamil', 'telugu', 'bengali', 'malayalam', 'english', 'japanese', 'korean', 'chinese', 'kannada', 'marathi', 'french'];
    foreach ($languages as $lang) {
        if (strpos($slug, $lang) !== false) {
            return ucfirst($lang) . ' Dubbed';
        }
    }
    return 'Hindi Dubbed';
}

/**
 * Map a single anime fixture record to the view contract used by
 * listing cards, search results, home sections and the detail page:
 * title, slug, poster, type, status, episodes, aired, duration, quality,
 * audio, description, genres, studio, rating, views (+ image/badge/episode).
 */
function anime_view_data($item) {
    return [
        'id' => isset($item['id']) ? (int)$item['id'] : null,
        'title' => isset($item['title']) ? $item['title'] : '',
        'slug' => isset($item['slug']) ? $item['slug'] : '',
        'poster' => isset($item['poster']) ? $item['poster'] : '/assets/images/placeholder.jpg',
        'image' => isset($item['poster']) ? $item['poster'] : '/assets/images/placeholder.jpg',
        'type' => isset($item['type']) ? ucfirst($item['type']) : '',
        'status' => isset($item['status']) ? ucfirst($item['status']) : '',
        'episodes' => isset($item['total_episodes']) ? (string)$item['total_episodes'] : '',
        'episode' => isset($item['latest_episode']) ? 'Ep ' . $item['latest_episode'] : '',
        'aired' => isset($item['year']) ? (string)$item['year'] : '',
        'duration' => isset($item['duration']) ? $item['duration'] : '',
        'quality' => isset($item['quality']) ? $item['quality'] : '',
        'audio' => anime_audio_from_slug(isset($item['slug']) ? $item['slug'] : ''),
        'description' => isset($item['description']) ? $item['description'] : '',
        'genres' => isset($item['genres']) ? (array)$item['genres'] : [],
        'studio' => isset($item['studio']) ? $item['studio'] : '',
        'rating' => isset($item['rating']) ? (string)$item['rating'] : '',
        'views' => '',
        'badge' => isset($item['badge']) ? $item['badge'] : null,
    ];
}

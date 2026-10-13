<?php

declare(strict_types=1);

namespace AnimeTadka\Scraper\Parsers;

use AnimeTadka\Scraper\HtmlParser;

/**
 * Parses the weekly schedule page at /schedule/.
 *
 * Returns 7 days (Sunday–Saturday), each with an array of anime cards
 * carrying status (uploaded / delayed / pending), timing, and metadata.
 */
final class ScheduleParser
{
    private HtmlParser $html;

    public function __construct(HtmlParser $html)
    {
        $this->html = $html;
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(): array
    {
        $dayMeta = $this->parseDayButtons();
        $panes   = $this->html->query('//div[contains(@class,"sa-day-pane")]');

        $days = [];
        $i    = 0;

        foreach ($panes as $pane) {
            if (!$pane instanceof \DOMElement) continue;
            $meta  = $dayMeta[$i] ?? ['name' => 'Day ' . $i, 'date' => ''];
            $cards = $this->parseCards($pane);

            $days[] = [
                'name'  => $meta['name'],
                'date'  => $meta['date'],
                'cards' => $cards,
            ];
            $i++;
        }

        return ['days' => $days];
    }

    /**
     * Extract day names and dates from the navigation buttons.
     *
     * @return list<array{name: string, date: string}>
     */
    private function parseDayButtons(): array
    {
        $buttons = $this->html->query('//button[contains(@class,"day-btn")]');
        $result  = [];

        foreach ($buttons as $btn) {
            if (!$btn instanceof \DOMElement) continue;

            $nameEl = $this->html->first('.//span[contains(@class,"d-name")]', $btn);
            $dateEl = $this->html->first('.//span[contains(@class,"d-date")]', $btn);

            $result[] = [
                'name' => HtmlParser::clean($nameEl?->textContent ?? ''),
                'date' => HtmlParser::clean($dateEl?->textContent ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Parse all schedule cards inside a day pane.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseCards(\DOMElement $pane): array
    {
        $cards = [];

        foreach ($this->html->query('.//div[contains(@class,"sa-sch-card")]', $pane) as $card) {
            if (!$card instanceof \DOMElement) continue;
            $cards[] = $this->parseCard($card);
        }

        return $cards;
    }

    /**
     * Parse a single schedule card element.
     *
     * @return array<string, mixed>
     */
    private function parseCard(\DOMElement $card): array
    {
        $class  = $card->getAttribute('class');
        $status = 'pending';
        if (str_contains($class, 'uploaded')) {
            $status = 'uploaded';
        } elseif (str_contains($class, 'delayed')) {
            $status = 'delayed';
        }

        $pid     = $card->getAttribute('data-pid');
        $season  = (int) $card->getAttribute('data-s');
        $episode = (int) $card->getAttribute('data-e');
        $batch   = (int) $card->getAttribute('data-b');
        $url     = $card->getAttribute('data-url');

        // Image from card-bg background-image style.
        $image = '';
        $bgDiv = $this->html->first('.//div[contains(@class,"card-bg")]', $card);
        if ($bgDiv instanceof \DOMElement) {
            $style = $bgDiv->getAttribute('style');
            if (preg_match('/url\(["\']?([^"\')\s]+)["\']?\)/', $style, $m)) {
                $image = $m[1];
            }
        }

        // Time badge text.
        $timeBadge = '';
        $timeBadgeEl = $this->html->first('.//span[contains(@class,"sa-time-badge")]', $card);
        if ($timeBadgeEl !== null) {
            $timeBadge = HtmlParser::clean($timeBadgeEl->textContent);
        }

        // Time24 from countdown-wrap data-time.
        $time24 = '';
        $countdownEl = $this->html->first('.//div[contains(@class,"countdown-wrap")]', $card);
        if ($countdownEl instanceof \DOMElement) {
            $time24 = $countdownEl->getAttribute('data-time');
        }
        // Fallback: extract from bell button onclick for delayed cards.
        if ($time24 === '' && $status === 'delayed') {
            $bellBtn = $this->html->first('.//button[contains(@class,"sa-bell-btn")]', $card);
            if ($bellBtn instanceof \DOMElement) {
                $onclick = $bellBtn->getAttribute('onclick');
                if (preg_match("/'(\d{1,2}:\d{2})'/", $onclick, $m)) {
                    $time24 = $m[1];
                }
            }
        }

        // Languages.
        $languages = '';
        $langEl = $this->html->first('.//span[contains(@class,"sa-lang-badge")]', $card);
        if ($langEl !== null) {
            $languages = HtmlParser::clean($langEl->textContent);
        }

        // Title.
        $title = '';
        $titleEl = $this->html->first('.//h3[contains(@class,"card-title")]', $card);
        if ($titleEl !== null) {
            $title = HtmlParser::clean($titleEl->textContent);
        }

        // Meta (Season X — Episode Y).
        $seasonLabel  = 'Season ' . $season;
        $episodeLabel = 'Episode ' . $episode;
        $metaEl = $this->html->first('.//p[contains(@class,"card-meta")]', $card);
        if ($metaEl !== null) {
            $metaText = HtmlParser::clean($metaEl->textContent);
            if (preg_match('/^(Season\s+\d+)\s*[—\-]\s*(Episode\s+[\d\-\s]+)$/i', $metaText, $m)) {
                $seasonLabel  = $m[1];
                $episodeLabel = $m[2];
            }
        }

        return [
            'pid'          => $pid,
            'season'       => $season,
            'episode'      => $episode,
            'batch'        => $batch,
            'url'          => $url,
            'image'        => $image,
            'status'       => $status,
            'timeBadge'    => $timeBadge,
            'time24'       => $time24,
            'languages'    => $languages,
            'title'        => $title,
            'seasonLabel'  => $seasonLabel,
            'episodeLabel' => $episodeLabel,
        ];
    }

    /**
     * Extract the inner HTML of a DOM node.
     */
    private function nodeHtml(\DOMNode $node): string
    {
        if ($node instanceof \DOMDocument) {
            return $node->saveHTML();
        }
        $tmp = new \DOMDocument('1.0', 'UTF-8');
        $tmp->appendChild($tmp->importNode($node, true));
        return $tmp->saveHTML();
    }
}

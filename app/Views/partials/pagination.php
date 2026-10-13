<?php
/**
 * Pagination partial — renders the original site's .at-pagination markup.
 *
 * Expects: $pagination = ['current' => int, 'total' => int, 'baseUrl' => string]
 * baseUrl is the page-1 URL (e.g. '/all' or '/letter/a');
 * page N links to baseUrl . '/page/' . N.
 */
$pagination = $pagination ?? [];
$current = max(1, (int) ($pagination['current'] ?? 1));
$total = max(1, (int) ($pagination['total'] ?? 1));
$baseUrl = rtrim((string) ($pagination['baseUrl'] ?? ''), '/');

if ($total <= 1) {
    return; // single page — no pagination needed
}

$pageUrl = fn ($n) => $n <= 1 ? $baseUrl : $baseUrl . '/page/' . $n;
?>
<div class="at-pagination">
    <ul>
        <?php if ($current > 1): ?>
        <li><a class="page-numbers" href="<?= View::escape($pageUrl($current - 1)) ?>">← Prev</a></li>
        <?php endif; ?>

        <?php
        $start = max(1, $current - 2);
        $end = min($total, $current + 2);

        if ($start > 1) {
            echo '<li><a class="page-numbers" href="' . View::escape($pageUrl(1)) . '">1</a></li>';
            if ($start > 2) {
                echo '<li><span class="page-numbers dots">&hellip;</span></li>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $current) {
                echo '<li><span class="page-numbers current">' . $i . '</span></li>';
            } else {
                echo '<li><a class="page-numbers" href="' . View::escape($pageUrl($i)) . '">' . $i . '</a></li>';
            }
        }

        if ($end < $total) {
            if ($end < $total - 1) {
                echo '<li><span class="page-numbers dots">&hellip;</span></li>';
            }
            echo '<li><a class="page-numbers" href="' . View::escape($pageUrl($total)) . '">' . $total . '</a></li>';
        }
        ?>

        <?php if ($current < $total): ?>
        <li><a class="page-numbers" href="<?= View::escape($pageUrl($current + 1)) ?>">Next →</a></li>
        <?php endif; ?>
    </ul>
</div>
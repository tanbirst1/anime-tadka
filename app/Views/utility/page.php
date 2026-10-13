<?php
/**
 * Static page view — contact-us (and placeholder for others).
 */
?>
<?php if (($slug ?? '') === 'contact-us'): ?>
<div class="page-hero">
    <div class="page-hero-overlay"></div>
    <div class="page-hero-content">
        <h1 class="page-main-title">Contact Us</h1>
        <div class="page-breadcrumb">
            <a href="/home">Home</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="9 18 15 12 9 6"></polyline></svg>
            <span>Contact Us</span>
        </div>
    </div>
</div>
<div class="page-main-container">
    <div class="page-glass-card">
        <article class="page-entry-content">
            <p>Thank you for visiting <strong><?= View::escape(Config::get('site_name')) ?></strong>.</p>
            <p>If you need to get in touch with us, please use the appropriate contact method below depending on your issue.</p>
            <hr>
            <h2>Telegram Support</h2>
            <p>For the following issues, please contact us through our <strong>Telegram group</strong> using the <strong>floating Telegram button</strong> (<b><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="1em" height="1em" fill="currentColor" style="vertical-align:middle;"><path fill="#ffffff" d="M320 72C183 72 72 183 72 320C72 457 183 568 320 568C457 568 568 457 568 320C568 183 457 72 320 72zM435 240.7C431.3 279.9 415.1 375.1 406.9 419C403.4 437.6 396.6 443.8 390 444.4C375.6 445.7 364.7 434.9 350.7 425.7C328.9 411.4 316.5 402.5 295.4 388.5C270.9 372.4 286.8 363.5 300.7 349C304.4 345.2 367.8 287.5 369 282.3C369.2 281.6 369.3 279.2 367.8 277.9C366.3 276.6 364.2 277.1 362.7 277.4C360.5 277.9 325.6 300.9 258.1 346.5C248.2 353.3 239.2 356.6 231.2 356.4C222.3 356.2 205.3 351.4 192.6 347.3C177.1 342.3 164.7 339.6 165.8 331C166.4 326.5 172.5 322 184.2 317.3C256.5 285.8 304.7 265 328.8 255C397.7 226.4 412 221.4 421.3 221.2C423.4 221.2 427.9 221.7 430.9 224.1C432.9 225.8 434.1 228.2 434.4 230.8C434.9 234 435 237.3 434.8 240.6z"></path></svg></b>) available on the website:</p>
            <ul>
                <li>Broken links</li>
                <li>Missing episodes</li>
                <li>Playback issues</li>
                <li>General questions</li>
                <li>Feedback or suggestions</li>
            </ul>
            <p>This is the <strong>fastest and preferred way</strong> to reach us for regular website-related support.</p>
            <hr>
            <h2>Email Contact</h2>
            <p>For <strong>DMCA / copyright concerns</strong>, legal matters, or other serious inquiries, you may contact us by email:</p>
            <p><strong>Email: <a href="mailto:animetadka@proton.me">animetadka@proton.me</a></strong></p>
            <hr>
            <h2>Important Notice</h2>
            <p><?= View::escape(Config::get('site_name')) ?> does <strong>not host, upload, or store any video files on its own servers</strong>. All media content, embedded players, and download links are provided by <strong>third-party sources</strong>.</p>
            <p>Because of this, some playback, file availability, or removal-related issues may depend on the respective third-party provider.</p>
            <hr>
            <h2>Response Time</h2>
            <ul>
                <li><strong>Telegram support</strong> is recommended for faster help with normal issues.</li>
                <li><strong>Email inquiries</strong> may take up to <strong>7 business days</strong> to receive a response, depending on the nature of the request.</li>
            </ul>
            <p>Please avoid sending duplicate messages, as this may delay the review process.</p>
            <p>Thank you for your support and understanding.</p>
            <p><strong><?= View::escape(Config::get('site_name')) ?> Team</strong></p>
        </article>
        <?php else: ?>
        <article class="page-entry-content">
            <h2><?= View::escape($title ?? '') ?></h2>
            <p>This page is under construction.</p>
        </article>
        <?php endif; ?>
    </div>
</div>
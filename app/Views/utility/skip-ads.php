<?php
/**
 * Skip Ads verification page — standalone (no site header/footer),
 * mirrors the original animetadka.com/skip-ads page exactly.
 */
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/pages/gateway-style.css">

<style>
    .sa-logo-img { max-width: 250px; height: auto; margin-bottom: 15px; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.3)); }
    #sa-msg.sa-dynamic-heading { border-left: 3px solid var(--sa-accent); padding: 5px 0 5px 20px; margin: 0 auto 30px !important; text-align: left; width: 100%; max-width: 500px; }
    #sa-msg .sa-dynamic-title { color: #ffffff !important; font-size: clamp(18px, 4vw, 22px) !important; font-weight: 800 !important; margin: 0 0 8px 0 !important; line-height: 1.4 !important; letter-spacing: -0.5px !important; }
    #sa-msg .sa-dynamic-sub { color: rgba(255, 255, 255, 0.6) !important; font-size: 13px !important; font-weight: 600 !important; margin: 0 !important; line-height: 1.5 !important; }
    @media (max-width: 480px) { #sa-msg.sa-dynamic-heading { padding-left: 15px; margin-bottom: 25px !important; } #sa-msg .sa-dynamic-title { font-size: 18px !important; } #sa-msg .sa-dynamic-sub { font-size: 12px !important; } }
</style>

<div class="particles-bg"></div>
<div class="sa-container">
    <div class="sa-glass-card">
        <div class="sa-header">
            <a href="/home"><img src="/assets/images/common/skip-ads-logo.png" alt="Logo" class="sa-logo-img"></a>
            <div class="sa-hint-bar"><span>--- Choose ONE Server to Verify ---</span></div>
        </div>
        <div class="sa-tabs">
            <button class="sa-tab active" data-lang="en">English</button>
            <button class="sa-tab" data-lang="hi">Hindi</button>
            <button class="sa-tab" data-lang="ta">Tamil</button>
            <button class="sa-tab" data-lang="te">Telugu</button>
            <button class="sa-tab" data-lang="bn">Bengali</button>
        </div>
        <div class="sa-content">
            <div id="sa-msg" class="sa-dynamic-heading">
                <h2 class="sa-dynamic-title">Please Skip Ad Once in Every 24 Hours To Watch or Download.</h2>
                <p class="sa-dynamic-sub">This help keep our site running. This is required to watch Episodes/Movies.</p>
            </div>
            <div class="sa-server-list">
                <div class="sa-server-group">
                    <button class="sa-btn-main shortener-trigger" data-id="0" id="sa-lock-btn">
                        <i class="fa-solid fa-shield-check"></i>
                        <span class="btn-text">Verify via Server 1</span>
                        <div class="sa-loader"></div>
                    </button>
                    <div class="sa-tut-section">
                        <div class="sa-tut-title"><svg class="sa-flow-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path class="arrow-path-main" d="M12 3v15"></path><path d="M19 12l-7 7-7-7"></path></svg><span>Tutorial Video</span><svg class="sa-flow-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path class="arrow-path-main" d="M12 3v15"></path><path d="M19 12l-7 7-7-7"></path></svg></div>
                        <div class="sa-video-container" style="border:2px solid rgba(255, 255, 255, 0.1);"><iframe src="https://tiny.animetadka.com/Mini-Ttrl" allowfullscreen=""></iframe></div>
                    </div>
                </div>
                <div class="sa-or-divider"><span>OR</span></div>
                <div class="sa-server-group">
                    <button class="sa-btn-main shortener-trigger" data-id="1" id="sa-lock-btn">
                        <i class="fa-solid fa-shield-check"></i>
                        <span class="btn-text">Verify via Server 2</span>
                        <div class="sa-loader"></div>
                    </button>
                    <div class="sa-tut-section">
                        <div class="sa-tut-title"><svg class="sa-flow-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path class="arrow-path-main" d="M12 3v15"></path><path d="M19 12l-7 7-7-7"></path></svg><span>Tutorial Video</span><svg class="sa-flow-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path class="arrow-path-main" d="M12 3v15"></path><path d="M19 12l-7 7-7-7"></path></svg></div>
                        <div class="sa-video-container" style="border:2px solid rgba(255, 255, 255, 0.1);"><iframe src="https://tiny.animetadka.com/ShrnEa-Ttrl" allowfullscreen=""></iframe></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="sa-support-section">
            <span class="sa-support-hint">Having a problem?</span>
            <a href="https://t.me/+-uUJzsOeG-1lNDU1" target="_blank" class="sa-tg-support-btn"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.891 8.11l-1.92 9.06c-.145.648-.529.81-.145.648l-3.028-2.23-1.461 1.408c-.162.162-.298.298-.612.298l.217-3.09 5.625-5.08c.245-.218-.054-.338-.378-.124l-6.95 4.37-2.99-.93c-.65-.204-.66-.65.135-.96l11.68-4.5c.54-.2 1.012.12 1.012.12s-.176.66-.312.93z"></path></svg><span>JOIN TELEGRAM GROUP</span></a>
        </div>
        <div class="sa-footer-note" style="margin-top: 25px;"><center><p style="color:rgba(255,255,255,0.5); font-size: 11px;">This Step is Required to Keep our Website Alive.</p></center></div>
    </div>
</div>

<div id="sa-error-modal" class="sa-modal-overlay" style="position: fixed !important; inset: 0 !important; background: rgba(0, 0, 0, 0.95) !important; backdrop-filter: blur(20px) !important; -webkit-backdrop-filter: blur(20px) !important; z-index: 999999 !important; display: none; align-items: center !important; justify-content: center !important; padding: 20px !important;">
    <div class="sa-modal-box" style="background: #0d0f12 !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; border-radius: 35px !important; padding: 45px 30px !important; width: 100% !important; max-width: 420px !important; text-align: center !important; box-shadow: 0 50px 120px rgba(0,0,0,1) !important; font-family: 'Inter', sans-serif !important;">
        <div class="sa-modal-icon" style="width: 75px !important; height: 75px !important; background: rgba(239, 68, 68, 0.1) !important; color: #ef4444 !important; font-size: 35px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; margin: 0 auto 25px !important; border: 1px solid rgba(239, 68, 68, 0.2) !important; filter: drop-shadow(0 0 15px rgba(239, 68, 68, 0.3)) !important;"><i class="fa-solid fa-circle-exclamation"></i></div>
        <h3 style="color: #ffffff !important; font-size: 26px !important; font-weight: 900 !important; margin: 0 0 12px 0 !important; letter-spacing: -0.5px !important;">Server Issue</h3>
        <p style="color: rgba(255, 255, 255, 0.5) !important; font-size: 15px !important; line-height: 1.7 !important; margin: 0 0 35px 0 !important; font-weight: 500 !important;">This server is currently busy or down. Please try an Alternative Server below.</p>
        <button class="sa-modal-close" onclick="closeSaModal()" style="width: 100% !important; background: #9b59b6 !important; border: none !important; padding: 18px !important; border-radius: 18px !important; color: #ffffff !important; font-weight: 900 !important; font-size: 14px !important; text-transform: uppercase !important; letter-spacing: 1.5px !important; cursor: pointer !important; transition: 0.3s !important; box-shadow: 0 10px 25px rgba(155, 89, 182, 0.3) !important;">Try Another Server</button>
    </div>
</div>

<script>
    var sa_ajax_url = "/wp-admin/admin-ajax.php";
    var sa_verify_hours = "24";
    function closeSaModal() { document.getElementById('sa-error-modal').style.display = 'none'; }
</script>
<script src="/assets/js/features/gateway-logic.js"></script>
<script src="https://player.vimeo.com/api/player.js"></script>
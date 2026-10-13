/**
 * SKIP ADS 2.0: MASTER GATEWAY LOGIC 
 * Version: 16.3 - Corrected Dynamic Headings
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // --- TITAN GUARD: Exit immediately if not on the verification page ---
        if (!document.getElementById('sa-lock-btn')) return;
        
        // 1. DATA FROM PHP
        const hours = typeof sa_verify_hours !== 'undefined' ? sa_verify_hours : '24';
        const ajaxUrl = typeof sa_ajax_url !== 'undefined' ? sa_ajax_url : '/wp-admin/admin-ajax.php';

        // 2. DYNAMIC LANGUAGE DATA
        const sa_lang_data = {
            en: {
                hint: "--- Choose ONE Server to Verify ---",
                hintSingle: "--- Complete Steps to Watch ---",
                msg: `Please Skip Ad Once in Every ${hours} Hours To Watch or Download.`,
                sub: "This help keep our site running. This is required to watch Episodes/Movies.",
                btn: "Verify via Server ",
                btnSingle: "Click Here to Verify",
                how: "How to Skip Server "
            },
            hi: {
                hint: "--- सत्यापित करने के लिए एक सर्वर चुनें ---",
                hintSingle: "--- देखने के लिए स्टेप्स पूरे करें ---",
                msg: `कृपया देखने या डाउनलोड करने के लिए हर ${hours} घंटे में एक बार विज्ञापन स्किप करें।`,
                sub: "यह हमारी साइट को चालू रखने में मदद करता है। एपिसोड/फिल्में देखने के लिए यह आवश्यक है।",
                btn: "सर्वर द्वारा सत्यापित करें ",
                btnSingle: "सत्यापित करने के लिए यहां क्लिक करें",
                how: "सर्वर " + " कैसे छोड़ें?"
            },
            ta: {
                hint: "--- சரிபார்க்க ஒரு சேவையகத்தைத் தேர்வுசெய்க ---",
                hintSingle: "--- பார்க்க படிகளை முடிக்கவும் ---",
                msg: `பார்க்க அல்லது பதிவிறக்க ஒவ்வொரு ${hours} மணிநேரத்திற்கும் ஒருமுறை விளம்பரத்தைத் தவிர்க்கவும்.`,
                sub: "இது எங்கள் தளத்தை தொடர்ந்து இயங்க வைக்க உதவுகிறது. எபிசோடுகள்/திரைப்படங்களை பார்க்க இது அவசியம்.",
                btn: "சர்வர் மூலம் சரிபார்க்கவும் ",
                btnSingle: "சரிபார்க்க இங்கே கிளிக் செய்யவும்",
                how: "சர்வர் " + " வீடியோ"
            },
            te: {
                hint: "--- ధృవీకరించడానికి ఒక సర్వర్‌ని ఎంచుకోండి ---",
                hintSingle: "--- చూడటానికి దశలను పూర్తి చేయండి ---",
                msg: `చూడటానికి లేదా డౌన్‌లోడ్ చేయడానికి దయచేసి ప్రతి ${hours} గంటలకు ఒకసారి ప్రకటనను దాటవేయండి.`,
                sub: "ఇది మా సైట్ రన్ అవ్వడానికి సహాయపడుతుంది. ఎపిసోడ్‌లు/సినిమాలు చూడటానికి ఇది అవసరం.",
                btn: "సర్వర్ ద్వారా ధృవీకరించండి ",
                btnSingle: "ధృవీకరించడానికి ఇక్కడ క్లిక్ చేయండి",
                how: "సర్వర్ " + " ట్యుటోరియల్"
            },
            bn: {
                hint: "--- যাচাই করার জন্য একটি সার্ভার চয়ন করুন ---",
                hintSingle: "--- দেখার জন্য ধাপগুলো সম্পন্ন করুন ---",
                msg: `দেখতে বা ডাউনলোড করতে অনুগ্রহ করে প্রতি ${hours} ঘন্টায় একবার বিজ্ঞাপন এড়িয়ে যান।`,
                sub: "এটি আমাদের সাইট সচল রাখতে সাহায্য করে। এপিসোড/সিনেমা দেখার জন্য এটি প্রয়োজন।",
                btn: "সার্ভারের মাধ্যমে যাচাই করুন ",
                btnSingle: "যাচাই করতে এখানে ক্লিক করুন",
                how: "সার্ভার " + " কীভাবে এড়িয়ে যাবেন?"
            }
        };

        const hintLabel = document.querySelector('.sa-hint-bar span');
        const msgBox = document.getElementById('sa-msg');
        const tabsContainer = document.querySelector('.sa-tabs');
        const serverGroups = document.querySelectorAll('.sa-server-group');
        const errorModal = document.getElementById('sa-error-modal');

        // Detect Server Count
        const isSingle = serverGroups.length === 1;

        /**
         * 3. UI UPDATE ENGINE
         */
        function updateGatewayUI(lang) {
            const data = sa_lang_data[lang];
            if(!data) return;
            
            if(hintLabel) {
                hintLabel.innerText = isSingle ? data.hintSingle : data.hint;
            }

            // Inject the Title, Subtitle, and dynamic CSS classes
            if(msgBox) {
                msgBox.className = 'sa-dynamic-heading';
                msgBox.innerHTML = `
                    <h2 class="sa-dynamic-title">${data.msg}</h2>
                    <p class="sa-dynamic-sub">${data.sub}</p>
                `;
            }

            serverGroups.forEach((group, index) => {
                const serverNum = index + 1;
                const btnText = group.querySelector('.btn-text');
                if (btnText) {
                    btnText.innerText = isSingle ? data.btnSingle : data.btn + serverNum;
                }

                const tutText = group.querySelector('.tut-text');
                if (tutText) {
                    let finalTut = data.how;
                    if (['hi', 'bn', 'te'].includes(lang)) {
                        finalTut = finalTut.replace(" ", " " + serverNum + " ");
                    } else {
                        finalTut = finalTut + serverNum + "?";
                    }
                    tutText.innerText = finalTut;
                }
            });
        }

        function updateActiveTabUI(lang) {
            document.querySelectorAll('.sa-tab').forEach(t => {
                t.classList.toggle('active', t.getAttribute('data-lang') === lang);
            });
        }

        function initTabs() {
            if (!tabsContainer) return;
            const savedLang = localStorage.getItem('sa_preferred_lang') || 'en';
            updateActiveTabUI(savedLang);
            updateGatewayUI(savedLang);

            tabsContainer.addEventListener('click', (e) => {
                const clickedTab = e.target.closest('.sa-tab');
                if (!clickedTab) return;
                const lang = clickedTab.getAttribute('data-lang');
                localStorage.setItem('sa_preferred_lang', lang);
                updateActiveTabUI(lang);
                updateGatewayUI(lang);
            });
        }

        initTabs();
        
        /**
         * 4. SECURE AJAX HANDLER
         */
        const serverTriggers = document.querySelectorAll('.shortener-trigger');
        serverTriggers.forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.classList.contains('loading')) return;
                const button = this;
                const serverId = button.getAttribute('data-id');
                button.classList.add('loading');

                const formData = new FormData();
                formData.append('action', 'sa_pro_get_shortlink');
                formData.append('server_id', serverId);

                fetch(ajaxUrl + '?t=' + Date.now(), {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.data.short_url;
                    } else {
                        showErrorModal();
                        button.classList.remove('loading');
                    }
                })
                .catch(() => {
                    showErrorModal();
                    button.classList.remove('loading');
                });
            });
        });

        function showErrorModal() {
            if(errorModal) {
                errorModal.style.display = 'flex';
                const box = errorModal.querySelector('.sa-modal-box');
                if(box) {
                    box.style.animation = 'none';
                    void box.offsetWidth; 
                    box.style.animation = 'saModalScale 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                }
            }
        }
    });
})();
/**
 * Guided tour for the Getting Started page: a spotlight walkthrough with
 * optional bilingual (English/Tagalog) voice-over via the browser's built-in
 * Web Speech API (window.speechSynthesis) -- no external service, no API key,
 * consistent with the rest of this system's "self-contained AI" approach.
 */

function tourStepsData() {
    var roleLine = {
        en: 'You are logged in as ' + window.TOUR_ROLE + '. ' + window.TOUR_ROLE_BLURB_EN,
        tl: 'Naka-login ka bilang ' + window.TOUR_ROLE + '. ' + window.TOUR_ROLE_BLURB_TL,
    };
    return [
        {
            selector: '#tour-intro',
            title: { en: 'Welcome to the Guided Tour', tl: 'Maligayang Pagdating sa Guided Tour' },
            body: {
                en: "This tour will walk you through everything you can do in this Financial Management System. I'll highlight each part of the screen and explain it out loud. Click Next when you're ready.",
                tl: 'Sasamahan ka sa buong Financial Management System na ito. Iha-highlight ang bawat parte ng screen at ipapaliwanag nang malakas. I-click ang Next kapag handa ka na.',
            },
        },
        {
            selector: '#tour-intro',
            title: { en: 'Your Role', tl: 'Ang Iyong Role' },
            body: roleLine,
        },
        {
            selector: '#tour-bigpicture',
            title: { en: 'The Big Picture', tl: 'Ang Malaking Larawan' },
            body: {
                en: 'This is the most important concept in the whole system. Every module on top automatically posts its own accounting entries into one place: the General Ledger. You never type numbers into the ledger yourself. The Dashboard and every Financial Report read live from that same ledger, so everything always matches.',
                tl: 'Ito ang pinakamahalagang konsepto sa buong sistema. Lahat ng module sa itaas ay awtomatikong nagpo-post ng accounting entries papunta sa isang lugar lamang: ang General Ledger. Hindi mo kailangang mag-type ng numero mismo sa ledger. Ang Dashboard at lahat ng Financial Reports ay direktang babasa mula sa parehong ledger, kaya laging tugma ang lahat.',
            },
        },
        {
            selector: '#tour-roles',
            title: { en: 'Four Roles', tl: 'Apat na Role' },
            body: {
                en: 'Admin can do everything. Accountant can only create draft documents. Approver can only approve what an Accountant submitted. Auditor can only view everything, read-only, plus the full Audit Log. This split means no single non-Admin person can both create and approve the same transaction.',
                tl: 'Ang Admin ay puwedeng gumawa ng lahat. Ang Accountant ay puwede lamang gumawa ng draft na dokumento. Ang Approver ay puwede lamang mag-approve ng isinumite ng Accountant. Ang Auditor ay puwede lamang tumingin sa lahat, view-only, kasama ang buong Audit Log. Dahil dito, walang iisang tao maliban sa Admin ang puwedeng gumawa at mag-approve ng parehong transaksyon.',
            },
        },
        {
            selector: '#tour-ap',
            title: { en: 'Accounts Payable — Paying a Vendor', tl: 'Accounts Payable — Pagbayad sa Vendor' },
            body: {
                en: 'An Accountant creates a Bill, which starts as a Draft with no accounting effect yet. An Approver clicks Approve and Post, which books the expense to the General Ledger. Finally, someone records a Payment, which reduces cash and marks the bill as Paid.',
                tl: 'Gumagawa ang Accountant ng Bill, na magsisimula bilang Draft na walang epekto pa sa accounting. Ini-click ng Approver ang Approve and Post, na nagpo-post ng expense sa General Ledger. Sa huli, may magre-record ng Payment, na babawasan ang cash at magmamarka sa bill bilang Paid.',
            },
        },
        {
            selector: '#tour-ar',
            title: { en: 'Accounts Receivable — Getting Paid', tl: 'Accounts Receivable — Pagtanggap ng Bayad' },
            body: {
                en: 'The mirror image of Accounts Payable. An Accountant creates an Invoice as a Draft, an Approver approves and posts it to record the revenue, and when the customer actually pays, a Receipt increases cash and marks the invoice Paid.',
                tl: 'Kabaligtaran ito ng Accounts Payable. Gumagawa ang Accountant ng Invoice bilang Draft, ina-approve at ipino-post ito ng Approver para ma-record ang revenue, at kapag nagbayad na ang customer, ang Receipt ang magdaragdag sa cash at magmamarka sa invoice bilang Paid.',
            },
        },
        {
            selector: '#tour-dv',
            title: { en: 'Disbursement Voucher', tl: 'Disbursement Voucher' },
            body: {
                en: 'Use this for payouts that need a formal approval trail first, like an employee cash advance. It goes straight to Pending Approval, an Approver approves or rejects it, and once approved, clicking Mark Paid releases the funds and posts the entry.',
                tl: 'Gamitin ito para sa mga bayad na kailangan munang aprubahan, tulad ng cash advance ng empleyado. Direkta itong napupunta sa Pending Approval, ina-approve o tinatanggihan ng Approver, at kapag na-approve na, ang Mark Paid ang maglalabas ng pondo at magpo-post ng entry.',
            },
        },
        {
            selector: '#tour-cr',
            title: { en: 'Collection Receipt', tl: 'Collection Receipt' },
            body: {
                en: 'The inflow version — money collected that needs sign-off before it counts as officially deposited. Submitted for approval, approved, then marked Deposited, which is when cash and the General Ledger actually update.',
                tl: 'Ang kabaligtaran nito — pera na nakolekta na kailangan munang aprubahan bago ito ituring na opisyal na na-deposito. Isinumite para sa approval, ina-approve, pagkatapos minamarkahan bilang Deposited, saka lang aktwal na nag-a-update ang cash at General Ledger.',
            },
        },
        {
            selector: '#tour-budget',
            title: { en: 'Budget Management', tl: 'Budget Management' },
            body: {
                en: 'Set up a Budget Period and a Budget with a monthly amount per account. An Approver approves it, and only Approved budgets count. The Variance Report then compares Budgeted versus Actual live from the ledger — there is nothing to run or close, it is always current.',
                tl: 'Gumawa ng Budget Period at Budget na may buwanang halaga bawat account. Ina-approve ito ng Approver, at ang mga Approved na budget lamang ang binibilang. Ang Variance Report ay ihahambing agad ang Budgeted kumpara sa Actual mula sa ledger — walang kailangang i-run o isara, laging updated ito.',
            },
        },
        {
            selector: '#tour-cash',
            title: { en: 'Cash Management', tl: 'Cash Management' },
            body: {
                en: 'Every bank and cash-on-hand account lives here, each linked to a General Ledger account with a running balance. Transactions and transfers post straight to the ledger with no separate approval step, since they represent money that already moved. Reconciliation lets you match your books against a real bank statement.',
                tl: 'Dito nakatira ang bawat bank at cash-on-hand na account, konektado sa isang General Ledger account na may running balance. Ang mga transaction at transfer ay direktang nagpo-post sa ledger nang walang hiwalay na approval step, dahil kumakatawan sila sa perang natransfer na. Ang Reconciliation ay para itugma ang iyong mga libro sa aktwal na bank statement.',
            },
        },
        {
            selector: '#tour-reports',
            title: { en: 'Financial Reports', tl: 'Financial Reports' },
            body: {
                en: 'These are read-only — nothing to draft or approve. The Trial Balance, Income Statement, Balance Sheet, Cash Flow Statement, and Custom Report all read live from posted General Ledger activity, so they are always up to date the moment you open them.',
                tl: 'Ang mga ito ay read-only lamang — walang ida-draft o aaprubahan. Ang Trial Balance, Income Statement, Balance Sheet, Cash Flow Statement, at Custom Report ay direktang babasa mula sa naipost na General Ledger activity, kaya laging updated ang mga ito sa oras na buksan mo.',
            },
        },
        {
            selector: '#tour-tax',
            title: { en: 'Tax Management', tl: 'Tax Management' },
            body: {
                en: 'Tax Types are set up once. After that, every taxed Bill or Invoice line automatically creates a Tax Transaction by itself — you never enter those by hand. When it is time to pay the government, filing a Remittance aggregates every pending tax transaction, marks them Remitted, and posts the payment to the ledger.',
                tl: 'Ang Tax Types ay ise-set up nang isang beses lamang. Pagkatapos, ang bawat may-buwis na linya ng Bill o Invoice ay awtomatikong gagawa ng Tax Transaction — hindi mo ito kailangang i-type nang mano-mano. Kapag oras na para magbayad sa gobyerno, ang paggawa ng Remittance ay titipunin ang lahat ng pending tax transactions, mama-markahan silang Remitted, at ipo-post ang bayad sa ledger.',
            },
        },
        {
            selector: '#tour-admin',
            title: { en: 'Admin & Compliance', tl: 'Admin & Compliance' },
            body: {
                en: 'This area is mostly Admin-only. Users is where logins and roles are managed. Settings is where the GL Control Accounts and Decision Support thresholds live. The Audit Log is a permanent, un-editable record of every action taken by every user — an Auditor can view all three, but never change them.',
                tl: 'Halos Admin lamang ang may access dito. Ang Users ay kung saan pinamamahalaan ang mga login at role. Ang Settings ay kung saan nakatakda ang GL Control Accounts at Decision Support thresholds. Ang Audit Log ay isang permanente at hindi nababagong record ng bawat aksyon ng bawat user — makikita ito ng Auditor, pero hindi nila ito puwedeng baguhin.',
            },
        },
        {
            selector: '#tour-results',
            title: { en: 'See the Result', tl: 'Tingnan ang Resulta' },
            body: {
                en: 'After practicing any flow, come back to these four places: the Dashboard for live KPIs and alerts, Journal Entries for the actual debit and credit postings, Trial Balance to confirm the books still balance, and the Audit Log to see who did what and when.',
                tl: 'Pagkatapos mong subukan ang alinmang flow, bumalik sa apat na lugar na ito: ang Dashboard para sa live na KPIs at alerts, Journal Entries para sa aktwal na debit at credit, Trial Balance para kumpirmahin na balanse pa rin ang mga libro, at ang Audit Log para makita kung sino ang gumawa ng ano at kailan.',
            },
        },
        {
            selector: '#tour-controls',
            title: { en: "That's the Whole System!", tl: 'Iyon na ang Buong Sistema!' },
            body: {
                en: 'You can replay this tour anytime by clicking Getting Started in the sidebar. Now go explore — try creating a document if your role allows it, and watch the numbers update live.',
                tl: 'Puwede mong ulitin ang tour na ito anumang oras sa pamamagitan ng pag-click sa Getting Started sa sidebar. Ngayon, subukan mo nang galugarin — gumawa ng dokumento kung pinapahintulutan ng iyong role, at panoorin ang mga numero na nag-a-update nang live.',
            },
        },
    ];
}

var tourIndex = 0;
var tourSteps = [];
var tourLang = 'en';
var tourVoiceEnabled = true;
var tourVoicesCache = null;

function tourPickVoice(lang) {
    if (!('speechSynthesis' in window)) return null;
    if (!tourVoicesCache || !tourVoicesCache.length) {
        tourVoicesCache = window.speechSynthesis.getVoices();
    }
    if (!tourVoicesCache || !tourVoicesCache.length) return null;
    if (lang === 'tl') {
        // Match by BCP-47 prefix (fil-PH, tl-PH) AND by name, since some engines
        // expose a Filipino voice under an unexpected lang code but a clear name.
        return tourVoicesCache.find(function (v) {
            var l = v.lang.toLowerCase();
            var n = v.name.toLowerCase();
            return l.indexOf('fil') === 0 || l.indexOf('tl') === 0 || n.indexOf('filipino') !== -1 || n.indexOf('tagalog') !== -1;
        }) || null;
    }
    return tourVoicesCache.find(function (v) { return v.lang.toLowerCase().indexOf('en') === 0; }) || null;
}

var tourMutedNoVoice = false;

/**
 * Speaks the given text in the current tour language. Critically: if
 * tourLang is 'tl' and no real Filipino/Tagalog voice is installed, this does
 * NOT fall back to speaking the Tagalog text with an English voice -- that
 * mispronounces every word. It mutes instead and flags tourMutedNoVoice so
 * the UI can explain why, rather than silently sounding wrong.
 */
function tourSpeak(text) {
    tourMutedNoVoice = false;
    if (!tourVoiceEnabled || !('speechSynthesis' in window)) { updateVoiceButtonLabel(); return; }
    window.speechSynthesis.cancel();
    var voice = tourPickVoice(tourLang);
    if (tourLang === 'tl' && !voice) {
        tourMutedNoVoice = true;
        updateVoiceButtonLabel();
        updateVoiceNote();
        return;
    }
    var utter = new SpeechSynthesisUtterance(text);
    // voice can still be null here for English if the browser hasn't finished
    // loading its voice list yet (getVoices() is async and can be empty for a
    // moment even after priming on DOMContentLoaded) -- guard the null instead
    // of reading .lang off it, otherwise this throws and silently kills the
    // first-ever speak() call.
    if (voice) {
        utter.voice = voice;
        utter.lang = voice.lang;
    }
    utter.rate = tourLang === 'tl' ? 0.92 : 1;
    window.speechSynthesis.speak(utter);
    updateVoiceButtonLabel();
}

function updateVoiceButtonLabel() {
    var btn = document.getElementById('tourVoiceBtn');
    if (!btn) return;
    if (!tourVoiceEnabled) { btn.textContent = '🔇 Voice off'; return; }
    if (tourMutedNoVoice) { btn.textContent = '🔇 No Tagalog voice'; return; }
    btn.textContent = ('speechSynthesis' in window && window.speechSynthesis.speaking) ? '⏸ Pause voice' : '🔊 Replay voice';
}

function toggleTourSpeech() {
    if (!('speechSynthesis' in window)) return;
    if (tourMutedNoVoice) return;
    if (window.speechSynthesis.speaking && !window.speechSynthesis.paused) {
        window.speechSynthesis.pause();
    } else if (window.speechSynthesis.paused) {
        window.speechSynthesis.resume();
    } else {
        var step = tourSteps[tourIndex];
        tourSpeak(step.body[tourLang]);
    }
    setTimeout(updateVoiceButtonLabel, 100);
}

function updateVoiceNote() {
    var note = document.getElementById('tourVoiceNote');
    if (!note) return;
    if (!('speechSynthesis' in window)) {
        note.textContent = 'Your browser does not support voice-over; the tour will still work as a visual guide.';
        return;
    }
    var langChoice = document.querySelector('input[name="tourLangChoice"]:checked');
    var selectedLang = langChoice ? langChoice.value : 'en';
    if (selectedLang !== 'tl') { note.textContent = ''; return; }
    var hasFil = !!tourPickVoice('tl');
    note.textContent = hasFil
        ? ''
        : 'No Tagalog/Filipino voice is installed in this browser, so Tagalog narration will stay muted (captions will still show in Tagalog) rather than mispronounce it with an English voice. To add one on Windows: Settings → Time & Language → Language & region → Add a language → Filipino → install its speech pack, then restart your browser.';
}

function tourCheckVoiceAvailability() {
    if (!('speechSynthesis' in window)) { updateVoiceNote(); return; }
    tourVoicesCache = window.speechSynthesis.getVoices();
    if (tourVoicesCache && tourVoicesCache.length) { updateVoiceNote(); }
    window.speechSynthesis.onvoiceschanged = function () {
        tourVoicesCache = window.speechSynthesis.getVoices();
        updateVoiceNote();
    };
}

/**
 * Lets the user drag the tour tooltip by its header, in case it's covering
 * information on the page they want to read. Position resets to the sensible
 * default (near the highlighted element) on the next/previous step -- the
 * drag is a "move it out of the way for a moment" action, not a saved layout.
 */
var tourDrag = { active: false, offsetX: 0, offsetY: 0 };

function tourDragStart(e) {
    if (e.target.closest('.tour-close')) return;
    var tooltip = document.getElementById('tourTooltip');
    var point = e.touches ? e.touches[0] : e;
    var rect = tooltip.getBoundingClientRect();
    tourDrag.active = true;
    tourDrag.offsetX = point.clientX - rect.left;
    tourDrag.offsetY = point.clientY - rect.top;
    tooltip.classList.add('dragging');
    e.preventDefault();
}

function tourDragMove(e) {
    if (!tourDrag.active) return;
    var tooltip = document.getElementById('tourTooltip');
    var point = e.touches ? e.touches[0] : e;
    var maxX = window.innerWidth - tooltip.offsetWidth - 8;
    var maxY = window.innerHeight - tooltip.offsetHeight - 8;
    var x = Math.max(8, Math.min(point.clientX - tourDrag.offsetX, maxX));
    var y = Math.max(8, Math.min(point.clientY - tourDrag.offsetY, maxY));
    tooltip.style.left = x + 'px';
    tooltip.style.top = y + 'px';
    if (e.touches) e.preventDefault();
}

function tourDragEnd() {
    if (!tourDrag.active) return;
    tourDrag.active = false;
    document.getElementById('tourTooltip').classList.remove('dragging');
}

function initTourDrag() {
    var header = document.getElementById('tourTooltipHeader');
    if (!header) return;
    header.addEventListener('mousedown', tourDragStart);
    header.addEventListener('touchstart', tourDragStart, { passive: false });
    document.addEventListener('mousemove', tourDragMove);
    document.addEventListener('touchmove', tourDragMove, { passive: false });
    document.addEventListener('mouseup', tourDragEnd);
    document.addEventListener('touchend', tourDragEnd);
}

function positionTourElements(step) {
    var target = document.querySelector(step.selector);
    var spotlight = document.getElementById('tourSpotlight');
    var tooltip = document.getElementById('tourTooltip');
    if (!target) return;

    // Instant (not smooth) scroll -- a "smooth" animated scroll plus a guessed fixed
    // delay before measuring is a race condition: on the last step, which jumps from
    // near the bottom of the page all the way back to the very first card, the
    // animation can easily still be running when the old delay fired, so the tooltip
    // got positioned from a stale, mid-scroll measurement and ended up off-screen with
    // no way to reach Finish. Scrolling instantly removes the race entirely.
    target.scrollIntoView({ behavior: 'auto', block: 'center' });

    // Wait two animation frames so layout/paint from the scroll has actually
    // settled before measuring -- correct regardless of scroll distance, unlike a
    // fixed timeout.
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            var rect = target.getBoundingClientRect();
            var pad = 8;
            spotlight.style.top = (rect.top - pad) + 'px';
            spotlight.style.left = (rect.left - pad) + 'px';
            spotlight.style.width = (rect.width + pad * 2) + 'px';
            spotlight.style.height = (rect.height + pad * 2) + 'px';

            var tooltipTop = rect.bottom + 16;
            var viewportBottom = window.innerHeight;
            if (tooltipTop + 220 > viewportBottom) {
                tooltipTop = Math.max(16, rect.top - 16 - tooltip.offsetHeight);
            }
            var left = rect.left;
            var maxLeft = window.innerWidth - tooltip.offsetWidth - 16;

            // Defensive clamp: whatever the math above produced, never let the
            // tooltip land fully or partially outside the visible viewport.
            tooltipTop = Math.max(8, Math.min(tooltipTop, window.innerHeight - 40));
            left = Math.max(8, Math.min(left, maxLeft));

            tooltip.style.top = tooltipTop + 'px';
            tooltip.style.left = left + 'px';
        });
    });
}

function renderTourStep() {
    var step = tourSteps[tourIndex];
    document.getElementById('tourStepCounter').textContent = 'Step ' + (tourIndex + 1) + ' of ' + tourSteps.length;
    document.getElementById('tourTitle').textContent = step.title[tourLang];
    document.getElementById('tourBody').textContent = step.body[tourLang];
    document.getElementById('tourPrevBtn').style.visibility = tourIndex === 0 ? 'hidden' : 'visible';
    document.getElementById('tourNextBtn').textContent = (tourIndex === tourSteps.length - 1) ? 'Finish ✓' : 'Next →';
    positionTourElements(step);
    tourSpeak(step.body[tourLang]);
}

function startTour() {
    var langChoice = document.querySelector('input[name="tourLangChoice"]:checked');
    tourLang = langChoice ? langChoice.value : 'en';
    tourVoiceEnabled = document.getElementById('tourVoiceToggle').checked;
    tourSteps = tourStepsData();
    tourIndex = 0;
    document.getElementById('tourSpotlight').style.display = 'block';
    document.getElementById('tourTooltip').style.display = 'block';
    var exitPin = document.getElementById('tourExitPin');
    if (exitPin) exitPin.style.display = 'flex';
    document.body.classList.add('tour-active');
    renderTourStep();
}

function tourNext() {
    if (tourIndex >= tourSteps.length - 1) { endTour(); return; }
    tourIndex++;
    renderTourStep();
}

function tourPrev() {
    if (tourIndex <= 0) return;
    tourIndex--;
    renderTourStep();
}

function endTour() {
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    document.getElementById('tourSpotlight').style.display = 'none';
    document.getElementById('tourTooltip').style.display = 'none';
    var exitPin = document.getElementById('tourExitPin');
    if (exitPin) exitPin.style.display = 'none';
    document.body.classList.remove('tour-active');
}

document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('tour-controls')) return;
    tourCheckVoiceAvailability();
    initTourDrag();
    document.querySelectorAll('input[name="tourLangChoice"]').forEach(function (radio) {
        radio.addEventListener('change', updateVoiceNote);
    });
    // Guaranteed way out regardless of any tooltip positioning issue: Escape always
    // ends the tour immediately, so a mispositioned/invisible popup can never trap
    // someone into needing to refresh the page.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('tour-active')) {
            endTour();
        }
    });
});

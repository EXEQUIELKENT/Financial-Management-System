/**
 * Generic per-page guided tour: spotlights each element listed in
 * window.PAGE_GUIDE_STEPS (set by header.php from that page's PHP $pageHelp
 * array) in order, with bilingual (English/Tagalog) captions and optional
 * voice-over via the browser's built-in Web Speech API. Shares the same
 * .tour-* CSS/markup pattern as the Getting Started page's tour.js, but is
 * generic (driven by a selector + en/tl content per step) instead of the
 * fixed 15-step onboarding script, and only loads content for whichever page
 * it is currently on.
 */

var pgIndex = 0;
var pgLang = localStorage.getItem('tourLang') || 'en';
var pgVoiceEnabled = true;
var pgVoicesCache = null;
var pgMutedNoVoice = false;
var pgDrag = { active: false, offsetX: 0, offsetY: 0 };

function pgPickVoice(lang) {
    if (!('speechSynthesis' in window)) return null;
    if (!pgVoicesCache || !pgVoicesCache.length) pgVoicesCache = window.speechSynthesis.getVoices();
    if (!pgVoicesCache || !pgVoicesCache.length) return null;
    if (lang === 'tl') {
        return pgVoicesCache.find(function (v) {
            var l = v.lang.toLowerCase(), n = v.name.toLowerCase();
            return l.indexOf('fil') === 0 || l.indexOf('tl') === 0 || n.indexOf('filipino') !== -1 || n.indexOf('tagalog') !== -1;
        }) || null;
    }
    return pgVoicesCache.find(function (v) { return v.lang.toLowerCase().indexOf('en') === 0; }) || null;
}

function pgSpeak(text) {
    pgMutedNoVoice = false;
    if (!pgVoiceEnabled || !('speechSynthesis' in window)) { pgUpdateVoiceButton(); return; }
    window.speechSynthesis.cancel();
    var voice = pgPickVoice(pgLang);
    if (pgLang === 'tl' && !voice) {
        pgMutedNoVoice = true;
        pgUpdateVoiceButton();
        pgUpdateVoiceNote();
        return;
    }
    var utter = new SpeechSynthesisUtterance(text);
    // voice can still be null here for English if the browser hasn't finished
    // loading its voice list yet (getVoices() is async and often empty on the
    // very first call after a page load) -- guard the null instead of reading
    // .lang off it, otherwise this throws and silently kills the first-ever
    // speak() on a freshly loaded page.
    if (voice) {
        utter.voice = voice;
        utter.lang = voice.lang;
    }
    utter.rate = pgLang === 'tl' ? 0.92 : 1;
    window.speechSynthesis.speak(utter);
    pgUpdateVoiceButton();
}

function pgCheckVoiceAvailability() {
    if (!('speechSynthesis' in window)) return;
    pgVoicesCache = window.speechSynthesis.getVoices();
    window.speechSynthesis.onvoiceschanged = function () {
        pgVoicesCache = window.speechSynthesis.getVoices();
        pgUpdateVoiceNote();
    };
}

function pgUpdateVoiceButton() {
    var btn = document.getElementById('pgVoiceBtn');
    if (!btn) return;
    if (!pgVoiceEnabled) { btn.textContent = '🔇 Voice off'; return; }
    if (pgMutedNoVoice) { btn.textContent = '🔇 No Tagalog voice'; return; }
    btn.textContent = ('speechSynthesis' in window && window.speechSynthesis.speaking) ? '⏸ Pause voice' : '🔊 Replay voice';
}

function pgToggleVoice() {
    if (!('speechSynthesis' in window)) return;
    if (pgMutedNoVoice) return;
    if (window.speechSynthesis.speaking && !window.speechSynthesis.paused) {
        window.speechSynthesis.pause();
    } else if (window.speechSynthesis.paused) {
        window.speechSynthesis.resume();
    } else {
        var step = window.PAGE_GUIDE_STEPS[pgIndex];
        pgSpeak(step[pgLang].body);
    }
    setTimeout(pgUpdateVoiceButton, 100);
}

function pgUpdateVoiceNote() {
    var note = document.getElementById('pgVoiceNote');
    if (!note) return;
    if (!('speechSynthesis' in window)) { note.textContent = 'Voice-over isn\'t supported in this browser; captions still work.'; return; }
    if (pgLang !== 'tl') { note.textContent = ''; return; }
    note.textContent = pgPickVoice('tl')
        ? ''
        : 'No Tagalog/Filipino voice is installed, so narration stays muted for Tagalog (captions still show) rather than mispronounce it with an English voice.';
}

function setPageGuideLang(lang) {
    pgLang = lang;
    localStorage.setItem('tourLang', lang);
    document.querySelectorAll('.pg-lang-btn').forEach(function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
    });
    pgRenderStep();
}

function pgFindTarget(step) {
    // step.nth (0-indexed) picks the Nth match for repeating elements (e.g. the
    // 2nd of several .table-wrap sections) -- more reliable than a CSS
    // :nth-of-type() hack, which counts ALL sibling elements of that tag name,
    // not just the ones matching the class, and silently breaks if other divs
    // are interspersed.
    if (typeof step.nth === 'number') {
        var matches = document.querySelectorAll(step.selector);
        return matches[step.nth] || null;
    }
    return document.querySelector(step.selector);
}

function pgPositionElements(step) {
    var target = pgFindTarget(step);
    var spotlight = document.getElementById('pgSpotlight');
    var tooltip = document.getElementById('pgTooltip');
    if (!target) { spotlight.style.display = 'none'; return; }
    spotlight.style.display = 'block';

    target.scrollIntoView({ behavior: 'auto', block: 'center' });

    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            var rect = target.getBoundingClientRect();
            var pad = 8;
            spotlight.style.top = (rect.top - pad) + 'px';
            spotlight.style.left = (rect.left - pad) + 'px';
            spotlight.style.width = (rect.width + pad * 2) + 'px';
            spotlight.style.height = (rect.height + pad * 2) + 'px';

            var tooltipTop = rect.bottom + 16;
            if (tooltipTop + 220 > window.innerHeight) {
                tooltipTop = Math.max(16, rect.top - 16 - tooltip.offsetHeight);
            }
            var left = rect.left;
            var maxLeft = window.innerWidth - tooltip.offsetWidth - 16;

            tooltipTop = Math.max(8, Math.min(tooltipTop, window.innerHeight - 40));
            left = Math.max(8, Math.min(left, maxLeft));

            tooltip.style.top = tooltipTop + 'px';
            tooltip.style.left = left + 'px';
        });
    });
}

function pgRenderStep() {
    var steps = window.PAGE_GUIDE_STEPS;
    var step = steps[pgIndex];
    document.getElementById('pgStepCounter').textContent = 'Step ' + (pgIndex + 1) + ' of ' + steps.length;
    document.getElementById('pgTitle').textContent = step[pgLang].title;
    document.getElementById('pgBody').textContent = step[pgLang].body;
    document.getElementById('pgPrevBtn').style.visibility = pgIndex === 0 ? 'hidden' : 'visible';
    document.getElementById('pgNextBtn').textContent = (pgIndex === steps.length - 1) ? 'Finish ✓' : 'Next →';
    document.querySelectorAll('.pg-lang-btn').forEach(function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-lang') === pgLang);
    });
    pgUpdateVoiceNote();
    pgPositionElements(step);
    pgSpeak(step[pgLang].body);
}

function startPageGuide() {
    if (!window.PAGE_GUIDE_STEPS || !window.PAGE_GUIDE_STEPS.length) return;
    pgIndex = 0;
    document.getElementById('pgTooltip').style.display = 'block';
    document.getElementById('pgExitPin').style.display = 'flex';
    document.body.classList.add('tour-active');
    pgRenderStep();
}

function pageGuideNext() {
    var steps = window.PAGE_GUIDE_STEPS;
    if (pgIndex >= steps.length - 1) { endPageGuide(); return; }
    pgIndex++;
    pgRenderStep();
}

function pageGuidePrev() {
    if (pgIndex <= 0) return;
    pgIndex--;
    pgRenderStep();
}

function endPageGuide() {
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    document.getElementById('pgSpotlight').style.display = 'none';
    document.getElementById('pgTooltip').style.display = 'none';
    document.getElementById('pgExitPin').style.display = 'none';
    document.body.classList.remove('tour-active');
}

function pgDragStart(e) {
    if (e.target.closest('.tour-close')) return;
    var tooltip = document.getElementById('pgTooltip');
    var point = e.touches ? e.touches[0] : e;
    var rect = tooltip.getBoundingClientRect();
    pgDrag.active = true;
    pgDrag.offsetX = point.clientX - rect.left;
    pgDrag.offsetY = point.clientY - rect.top;
    tooltip.classList.add('dragging');
    e.preventDefault();
}

function pgDragMove(e) {
    if (!pgDrag.active) return;
    var tooltip = document.getElementById('pgTooltip');
    var point = e.touches ? e.touches[0] : e;
    var maxX = window.innerWidth - tooltip.offsetWidth - 8;
    var maxY = window.innerHeight - tooltip.offsetHeight - 8;
    tooltip.style.left = Math.max(8, Math.min(point.clientX - pgDrag.offsetX, maxX)) + 'px';
    tooltip.style.top = Math.max(8, Math.min(point.clientY - pgDrag.offsetY, maxY)) + 'px';
    if (e.touches) e.preventDefault();
}

function pgDragEnd() {
    if (!pgDrag.active) return;
    pgDrag.active = false;
    document.getElementById('pgTooltip').classList.remove('dragging');
}

document.addEventListener('DOMContentLoaded', function () {
    // Prime the voice list as early as possible -- getVoices() is async in
    // most browsers (empty on the first call after a page load, populated
    // once 'voiceschanged' fires), so starting this the moment the page loads
    // gives it a head start before the user actually clicks the guide button.
    pgCheckVoiceAvailability();

    if (!window.PAGE_GUIDE_STEPS || !window.PAGE_GUIDE_STEPS.length) return;

    var header = document.getElementById('pgTooltipHeader');
    if (header) {
        header.addEventListener('mousedown', pgDragStart);
        header.addEventListener('touchstart', pgDragStart, { passive: false });
        document.addEventListener('mousemove', pgDragMove);
        document.addEventListener('touchmove', pgDragMove, { passive: false });
        document.addEventListener('mouseup', pgDragEnd);
        document.addEventListener('touchend', pgDragEnd);
    }

    document.querySelectorAll('.pg-lang-btn').forEach(function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-lang') === pgLang);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('tour-active')) {
            endPageGuide();
        }
    });
});

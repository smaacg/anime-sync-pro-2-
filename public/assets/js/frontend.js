/**
 * Frontend JavaScript
 * Anime Sync Pro — frontend.js
 * 純原生 JS，不依賴 jQuery
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
    initLazyLoad();
    initStickyTabs();
    initActiveTab();
    initToggleExpand();
    initMusicPlayer();
    initCountdown();
});

// ========================================
// 圖片 Lazy Load
// ========================================
function initLazyLoad() {
    if (!('IntersectionObserver' in window)) return;
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                var img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            }
        });
    }, { rootMargin: '100px' });
    document.querySelectorAll('img[data-src]').forEach(function (img) {
        observer.observe(img);
    });
}

// ========================================
// Sticky Tab
// ========================================
function initStickyTabs() {
    var nav = document.querySelector('.asd-tabs');
    if (!nav) return;
    var navTop = nav.getBoundingClientRect().top + window.scrollY;
    window.addEventListener('scroll', function () {
        if (window.scrollY > navTop) {
            nav.classList.add('sticky');
        } else {
            nav.classList.remove('sticky');
        }
    }, { passive: true });
}

// ========================================
// Tab 高亮
// ========================================
function initActiveTab() {
    var tabs = document.querySelectorAll('.asd-tab');
    if (tabs.length === 0) return;

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
        });
    });

    window.addEventListener('scroll', function () {
        var scrollTop = window.scrollY + 100;
        var current = '';
        tabs.forEach(function (tab) {
            var href = tab.getAttribute('href');
            if (!href || href.charAt(0) !== '#') return;
            var section = document.querySelector(href);
            if (section && section.offsetTop <= scrollTop) {
                current = href;
            }
        });
        if (current) {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            var activeTab = document.querySelector('.asd-tab[href="' + current + '"]');
            if (activeTab) activeTab.classList.add('is-active');
        }
    }, { passive: true });
}

// ========================================
// 集數 / Staff / Cast 展開
// ========================================
function initToggleExpand() {

    // ── 集數展開 ──
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.asd-ep-toggle');
        if (!btn) return;
        var section = btn.closest('section');
        if (!section) return;
        section.querySelectorAll('.asd-ep-hidden').forEach(function (el) {
            el.classList.remove('asd-ep-hidden');
        });
        var wrap = btn.closest('div');
        if (wrap) wrap.style.display = 'none';
    });

    // ── Staff 展開 ──
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.asd-staff-toggle');
        if (!btn) return;
        var section = btn.closest('section');
        if (!section) return;
        section.querySelectorAll('.asd-staff-hidden').forEach(function (el) {
            el.classList.remove('asd-staff-hidden');
        });
        var wrap = btn.closest('div');
        if (wrap) wrap.style.display = 'none';
    });

    // ── Cast 展開 ──
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.asd-cast-toggle');
        if (!btn) return;
        var section = btn.closest('section');
        if (!section) return;
        section.querySelectorAll('.asd-cast-hidden').forEach(function (el) {
            el.classList.remove('asd-cast-hidden');
        });
        var wrap = btn.closest('div');
        if (wrap) wrap.style.display = 'none';
    });
}

// ========================================
// 音樂播放器
// ========================================
function initMusicPlayer() {
    var currentAudio = null;
    var currentBtn   = null;
    var rafId        = null;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.asd-music-play-btn');
        if (!btn) return;

        var card  = btn.closest('.asd-music-card-v2');
        var wrap  = card ? card.querySelector('.asd-music-player-wrap') : null;
        var audio = wrap ? wrap.querySelector('.asd-music-audio') : null;
        var bar   = wrap ? wrap.querySelector('.asd-music-progress-bar') : null;
        var time  = wrap ? wrap.querySelector('.asd-music-time') : null;

        if (!audio) return;

        // 暫停其他播放中的音樂
        if (currentAudio && currentAudio !== audio) {
            currentAudio.pause();
            if (currentBtn) currentBtn.classList.remove('is-playing');
            if (rafId) cancelAnimationFrame(rafId);
        }

        if (audio.paused) {
            audio.play().then(function () {
                btn.classList.add('is-playing');
                currentAudio = audio;
                currentBtn   = btn;
                updateProgress(bar, time, audio);
            }).catch(function (err) {
                console.warn('播放失敗:', err);
            });
        } else {
            audio.pause();
            btn.classList.remove('is-playing');
            if (rafId) cancelAnimationFrame(rafId);
        }

        // 播放結束重置
        audio.onended = function () {
            btn.classList.remove('is-playing');
            if (bar) bar.style.width = '0%';
            if (time) time.textContent = '0:00';
            if (rafId) cancelAnimationFrame(rafId);
        };

        // 點擊進度條跳轉
        if (wrap) {
            var progressWrap = wrap.querySelector('.asd-music-progress-wrap');
            if (progressWrap) {
                progressWrap.onclick = function (ev) {
                    if (!audio.duration) return;
                    var rect  = progressWrap.getBoundingClientRect();
                    var ratio = (ev.clientX - rect.left) / rect.width;
                    audio.currentTime = ratio * audio.duration;
                };
            }
        }
    });

    function updateProgress(bar, time, audio) {
        rafId = requestAnimationFrame(function () {
            if (audio.duration) {
                var pct = (audio.currentTime / audio.duration) * 100;
                if (bar) bar.style.width = pct + '%';
                if (time) time.textContent = formatTime(audio.currentTime);
            }
            if (!audio.paused) updateProgress(bar, time, audio);
        });
    }

    function formatTime(sec) {
        var m = Math.floor(sec / 60);
        var s = Math.floor(sec % 60);
        return m + ':' + (s < 10 ? '0' : '') + s;
    }
}

// ========================================
// 播出倒數計時
// ========================================
function initCountdown() {
    var cd = document.querySelector('.asd-countdown[data-ts]');
    if (!cd) return;

    var ts = parseInt(cd.getAttribute('data-ts'), 10) * 1000;

    function pad(n) { return n < 10 ? '0' + n : String(n); }

    function tick() {
        var diff = ts - Date.now();
        if (diff <= 0) { cd.textContent = '即將播出'; return; }
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000)  / 60000);
        var s = Math.floor((diff % 60000)    / 1000);
        var str = '';
        if (d > 0) str += d + ' 天 ';
        str += pad(h) + ':' + pad(m) + ':' + pad(s);
        cd.textContent = str;
    }

    tick();
    setInterval(tick, 1000);
}

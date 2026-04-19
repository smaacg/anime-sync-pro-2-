/**
 * Frontend JavaScript
 * Anime Sync Pro — frontend.js
 * 純原生 JS，不依賴 jQuery
 */

'use strict';

function safeInit(name, fn) {
    try {
        fn();
    } catch (err) {
        console.error('[Anime Sync Pro] init failed:', name, err);
    }
}

function asdInit() {
    if (window.__asdFrontendInited) return;
    if (!document.body) return;

    safeInit('lazy-load', initLazyLoad);
    safeInit('tabs', initTabs);
    safeInit('toggle-expand', initToggleExpand);
    safeInit('music-player', initMusicPlayer);
    safeInit('countdown', initCountdown);

    window.__asdFrontendInited = true;
    window.__asdFrontendBootedAt = Date.now();

    if (window.animeSyncData && window.animeSyncData.debug) {
        console.info('[Anime Sync Pro] frontend booted');
    }
}

window.asdInit = asdInit;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', asdInit, { once: true });
} else {
    asdInit();
}

window.addEventListener('load', asdInit, { once: true });
window.addEventListener('pageshow', function () {
    if (!window.__asdFrontendInited) {
        asdInit();
    }
});

// ========================================
// 圖片 Lazy Load
// ========================================
function initLazyLoad() {
    if (!('IntersectionObserver' in window)) return;

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;

            var img = entry.target;
            if (img.dataset.src) {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            }
            observer.unobserve(img);
        });
    }, { rootMargin: '100px' });

    document.querySelectorAll('img[data-src]').forEach(function (img) {
        observer.observe(img);
    });
}

// ========================================
// Tabs：高亮 + smooth scroll
// ========================================
function initTabs() {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.asd-tab'));
    var sections = Array.prototype.slice.call(document.querySelectorAll('.asd-section[id]'));
    if (!tabs.length || !sections.length) return;

    function setActiveTabById(id) {
        tabs.forEach(function (tab) {
            var href = tab.getAttribute('href');
            tab.classList.toggle('is-active', href === '#' + id);
        });
    }

    function getScrollOffset() {
        var nav = document.querySelector('.asd-tabs');
        var navHeight = nav ? nav.offsetHeight : 0;
        return navHeight + 16;
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            var href = tab.getAttribute('href');
            if (!href || href.charAt(0) !== '#') return;

            var target = document.querySelector(href);
            if (!target) return;

            e.preventDefault();

            var offset = getScrollOffset();
            var top = target.getBoundingClientRect().top + window.pageYOffset - offset;

            window.scrollTo({
                top: top,
                behavior: 'smooth'
            });

            setActiveTabById(target.id);
        });
    });

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            var visibleEntries = entries
                .filter(function (entry) { return entry.isIntersecting; })
                .sort(function (a, b) { return a.boundingClientRect.top - b.boundingClientRect.top; });

            if (visibleEntries.length) {
                setActiveTabById(visibleEntries[0].target.id);
            }
        }, {
            rootMargin: '-25% 0px -55% 0px',
            threshold: 0
        });

        sections.forEach(function (section) {
            observer.observe(section);
        });
    } else {
        function onScroll() {
            var currentId = '';
            var trigger = getScrollOffset() + 20;

            sections.forEach(function (section) {
                var rect = section.getBoundingClientRect();
                if (rect.top <= trigger) {
                    currentId = section.id;
                }
            });

            if (currentId) setActiveTabById(currentId);
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        onScroll();
    }
}

// ========================================
// 集數 / Staff / Cast 展開收合
// ========================================
function initToggleExpand() {
    bindToggleButtons({
        buttonSelector: '.asd-ep-toggle',
        hiddenSelector: '.asd-ep-hidden',
        hiddenClass: 'asd-ep-hidden',
        allSelector: '.asd-ep-row',
        defaultVisible: 3,
        collapsedText: function (count) { return '顯示全部 ' + count + ' 集 ▼'; },
        expandedText: '收起 ▲'
    });

    bindToggleButtons({
        buttonSelector: '.asd-staff-toggle',
        hiddenSelector: '.asd-staff-hidden',
        hiddenClass: 'asd-staff-hidden',
        allSelector: '.asd-staff-card-v2',
        defaultVisible: 6,
        collapsedText: function (count) { return '顯示全部 ' + count + ' 人 ▼'; },
        expandedText: '收起 ▲'
    });

    bindToggleButtons({
        buttonSelector: '.asd-cast-toggle',
        hiddenSelector: '.asd-cast-hidden',
        hiddenClass: 'asd-cast-hidden',
        allSelector: '.asd-cast-card',
        defaultVisible: 6,
        collapsedText: function (count) { return '顯示全部 ' + count + ' 人 ▼'; },
        expandedText: '收起 ▲'
    });

    function initToggleExpand() {
    bindToggleButtons({
        buttonSelector: '.asd-ep-toggle',
        itemSelector: '.asd-ep-row, [class*="asd-ep-row"]',
        hiddenClass: 'asd-ep-hidden',
        countLabel: '集'
    });

    bindToggleButtons({
        buttonSelector: '.asd-staff-toggle',
        itemSelector: '.asd-staff-card-v2, .asd-staff-card, [class*="asd-staff-card"]',
        hiddenClass: 'asd-staff-hidden',
        countLabel: '人'
    });

    bindToggleButtons({
        buttonSelector: '.asd-cast-toggle',
        itemSelector: '.asd-cast-card, .asd-cast-card-v2, [class*="asd-cast-card"]',
        hiddenClass: 'asd-cast-hidden',
        countLabel: '人'
    });

    function bindToggleButtons(config) {
        var buttons = Array.prototype.slice.call(document.querySelectorAll(config.buttonSelector));
        if (!buttons.length) return;

        buttons.forEach(function (btn) {
            var section = btn.closest('section');
            if (!section) return;

            var allItems = Array.prototype.slice.call(section.querySelectorAll(config.itemSelector));
            if (!allItems.length) return;

            var initialVisibleCount = allItems.filter(function (item) {
                return !item.classList.contains(config.hiddenClass);
            }).length;

            if (!initialVisibleCount) {
                initialVisibleCount = Math.min(4, allItems.length);
            }

            if (allItems.length <= initialVisibleCount) {
                btn.style.display = 'none';
                return;
            }

            btn.dataset.originalText = '顯示全部 ' + allItems.length + ' ' + config.countLabel + ' ▼';
            btn.textContent = btn.dataset.originalText;

            btn.addEventListener('click', function () {
                var expanded = btn.classList.contains('is-expanded');

                if (expanded) {
                    allItems.forEach(function (item, index) {
                        if (index >= initialVisibleCount) {
                            item.classList.add(config.hiddenClass);
                        } else {
                            item.classList.remove(config.hiddenClass);
                        }
                    });

                    btn.classList.remove('is-expanded');
                    btn.textContent = btn.dataset.originalText;

                    var top = section.getBoundingClientRect().top + window.pageYOffset - getStickyOffset();
                    window.scrollTo({
                        top: top,
                        behavior: 'smooth'
                    });
                } else {
                    allItems.forEach(function (item) {
                        item.classList.remove(config.hiddenClass);
                    });

                    btn.classList.add('is-expanded');
                    btn.textContent = '收起 ▲';
                }
            });
        });
    }

    function getStickyOffset() {
        var nav = document.querySelector('.asd-tabs');
        return (nav ? nav.offsetHeight : 0) + 16;
    }
}

// ========================================
// 音樂播放器
// ========================================
function initMusicPlayer() {
    if (document.body.dataset.asdMusicInited === '1') return;
    document.body.dataset.asdMusicInited = '1';

    var currentAudio = null;
    var currentBtn = null;
    var currentBar = null;
    var currentTime = null;
    var rafId = null;
    var playToken = 0;

    function cancelProgress() {
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
    }

    function resetUI(btn, bar, time) {
        if (btn) btn.classList.remove('is-playing');
        if (bar) bar.style.width = '0%';
        if (time) time.textContent = '0:00';
    }

    function formatTime(sec) {
        sec = isFinite(sec) ? sec : 0;
        var m = Math.floor(sec / 60);
        var s = Math.floor(sec % 60);
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function updateProgress(audio, bar, time) {
        cancelProgress();

        function loop() {
            if (!audio.paused && !audio.ended) {
                if (audio.duration) {
                    var pct = (audio.currentTime / audio.duration) * 100;
                    if (bar) bar.style.width = pct + '%';
                    if (time) time.textContent = formatTime(audio.currentTime);
                }
                rafId = requestAnimationFrame(loop);
            }
        }

        rafId = requestAnimationFrame(loop);
    }

    function setSource(audio, src) {
        if (!src) return false;
        if (audio.getAttribute('src') !== src) {
            audio.setAttribute('src', src);
            audio.load();
        }
        return true;
    }

    function playWithFallback(audio, primarySrc, fallbackSrc) {
        var triedFallback = false;

        function tryPlay(src) {
            if (!setSource(audio, src)) {
                return Promise.reject(new Error('empty media src'));
            }
            return audio.play().catch(function (err) {
                if (!triedFallback && fallbackSrc && fallbackSrc !== src) {
                    triedFallback = true;
                    console.warn('[Anime Sync Pro] primary audio failed, fallback to video src:', err);
                    return tryPlay(fallbackSrc);
                }
                throw err;
            });
        }

        return tryPlay(primarySrc || fallbackSrc);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.asd-music-play-btn');
        if (!btn) return;

        var card = btn.closest('.asd-music-card-v2');
        var wrap = card ? card.querySelector('.asd-music-player-wrap') : null;
        var audio = wrap ? wrap.querySelector('.asd-music-audio') : null;
        var bar = wrap ? wrap.querySelector('.asd-music-progress-bar') : null;
        var time = wrap ? wrap.querySelector('.asd-music-time') : null;

        if (!audio || !wrap) return;

        var primarySrc = wrap.dataset.audioSrc || audio.getAttribute('src') || '';
        var fallbackSrc = wrap.dataset.videoSrc || '';

        if (currentAudio && currentAudio !== audio) {
            currentAudio.pause();
            resetUI(currentBtn, currentBar, currentTime);
            cancelProgress();
        }

        if (!audio.paused) {
            audio.pause();
            resetUI(btn, bar, time);

            if (currentAudio === audio) {
                currentAudio = null;
                currentBtn = null;
                currentBar = null;
                currentTime = null;
            }

            cancelProgress();
            return;
        }

        playToken++;
        var token = playToken;

        playWithFallback(audio, primarySrc, fallbackSrc).then(function () {
            if (token !== playToken) return;

            currentAudio = audio;
            currentBtn = btn;
            currentBar = bar;
            currentTime = time;

            btn.classList.add('is-playing');
            updateProgress(audio, bar, time);
        }).catch(function (err) {
            console.warn('播放失敗:', err);

            var message = '目前瀏覽器可能不支援此音訊格式';
            if (fallbackSrc) {
                message += '（已嘗試備援來源）';
            }
            alert(message);
        });

        audio.onended = function () {
            resetUI(btn, bar, time);

            if (currentAudio === audio) {
                currentAudio = null;
                currentBtn = null;
                currentBar = null;
                currentTime = null;
            }

            cancelProgress();
        };
    });

    document.querySelectorAll('.asd-music-progress-wrap').forEach(function (progressWrap) {
        progressWrap.addEventListener('click', function (ev) {
            var wrap = progressWrap.closest('.asd-music-player-wrap');
            var audio = wrap ? wrap.querySelector('.asd-music-audio') : null;
            if (!audio || !audio.duration) return;

            var rect = progressWrap.getBoundingClientRect();
            var ratio = (ev.clientX - rect.left) / rect.width;
            ratio = Math.max(0, Math.min(1, ratio));
            audio.currentTime = ratio * audio.duration;
        });
    });
}

// ========================================
// 播出倒數計時
// 顯示風格：1天 3時 12分 5秒
// ========================================
function initCountdown() {
    var countdowns = document.querySelectorAll('.asd-countdown[data-ts]');
    if (!countdowns.length) return;

    function updateCountdowns() {
        var now = Math.floor(Date.now() / 1000);

        countdowns.forEach(function (el) {
            var ts = parseInt(el.getAttribute('data-ts'), 10);
            if (isNaN(ts)) return;

            var diff = ts - now;

            if (diff <= 0) {
                el.textContent = '已播出';
                return;
            }

            var d = Math.floor(diff / 86400);
            var h = Math.floor((diff % 86400) / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;

            el.textContent =
                (d > 0 ? d + '天 ' : '') +
                h + '時 ' +
                m + '分 ' +
                s + '秒';
        });
    }

    updateCountdowns();
    setInterval(updateCountdowns, 1000);
}

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

    var currentMedia = null;
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

    function updateProgress(media, bar, time) {
        cancelProgress();

        function loop() {
            if (media && !media.paused && !media.ended) {
                if (media.duration) {
                    var pct = (media.currentTime / media.duration) * 100;
                    if (bar) bar.style.width = pct + '%';
                    if (time) time.textContent = formatTime(media.currentTime);
                }
                rafId = requestAnimationFrame(loop);
            }
        }

        rafId = requestAnimationFrame(loop);
    }

    function stopMedia(media) {
        if (!media) return;
        try {
            media.pause();
            media.currentTime = 0;
        } catch (e) {}
    }

    function setSource(media, src) {
        if (!media || !src) return false;

        if (media.getAttribute('src') !== src) {
            media.setAttribute('src', src);
            media.load();
        }
        return true;
    }

    function getExt(src) {
        if (!src) return '';
        var clean = src.split('?')[0].split('#')[0];
        var parts = clean.split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function canPlayAudioSrc(src) {
        var audio = document.createElement('audio');
        var ext = getExt(src);

        if (ext === 'ogg' || ext === 'oga') {
            return !!(audio.canPlayType('audio/ogg; codecs="vorbis"') || audio.canPlayType('audio/ogg'));
        }
        if (ext === 'mp3') {
            return !!audio.canPlayType('audio/mpeg');
        }
        if (ext === 'm4a' || ext === 'aac') {
            return !!(audio.canPlayType('audio/mp4') || audio.canPlayType('audio/aac'));
        }

        return true;
    }

    function canPlayVideoSrc(src) {
        var video = document.createElement('video');
        var ext = getExt(src);

        if (ext === 'webm') {
            return !!(video.canPlayType('video/webm; codecs="vp8, vorbis"') || video.canPlayType('video/webm'));
        }
        if (ext === 'mp4' || ext === 'm4v') {
            return !!video.canPlayType('video/mp4');
        }

        return true;
    }

    function buildCandidates(wrap) {
        var candidates = [];
        var audio = wrap.querySelector('.asd-music-audio');
        var video = wrap.querySelector('.asd-music-video');
        var audioSrc = (wrap.dataset.audioSrc || '').trim();
        var videoSrc = (wrap.dataset.videoSrc || '').trim();

        if (audio && audioSrc) {
            candidates.push({
                el: audio,
                src: audioSrc,
                kind: 'audio',
                supported: canPlayAudioSrc(audioSrc)
            });
        }

        if (video && videoSrc) {
            video.muted = false;
            video.playsInline = true;

            candidates.push({
                el: video,
                src: videoSrc,
                kind: 'video',
                supported: canPlayVideoSrc(videoSrc)
            });
        }

        candidates.sort(function (a, b) {
            if (a.supported === b.supported) return 0;
            return a.supported ? -1 : 1;
        });

        return candidates;
    }

    function playOne(candidate) {
        if (!candidate || !candidate.el || !candidate.src) {
            return Promise.reject(new Error('empty candidate'));
        }

        if (!setSource(candidate.el, candidate.src)) {
            return Promise.reject(new Error('set source failed'));
        }

        return candidate.el.play().then(function () {
            return candidate.el;
        });
    }

    function playCandidates(candidates, index) {
        if (!candidates.length || index >= candidates.length) {
            return Promise.reject(new Error('no playable source'));
        }

        var candidate = candidates[index];

        return playOne(candidate).catch(function (err) {
            console.warn('[Anime Sync Pro] source failed:', candidate.kind, candidate.src, err);
            return playCandidates(candidates, index + 1);
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.asd-music-play-btn');
        if (!btn) return;

        var wrap = btn.closest('.asd-music-player-wrap');
        if (!wrap) return;

        var bar = wrap.querySelector('.asd-music-progress-bar');
        var time = wrap.querySelector('.asd-music-time');
        var openLink = wrap.querySelector('.asd-music-open-link');

        var candidates = buildCandidates(wrap);
        if (!candidates.length) {
            if (openLink && openLink.href) {
                window.open(openLink.href, '_blank', 'noopener');
                return;
            }
            alert('此主題曲沒有可播放來源');
            return;
        }

        var isSameWrapPlaying = currentMedia && wrap.contains(currentMedia) && !currentMedia.paused;
        if (isSameWrapPlaying) {
            currentMedia.pause();
            resetUI(btn, bar, time);
            cancelProgress();
            return;
        }

        if (currentMedia && currentMedia !== candidates[0].el) {
            stopMedia(currentMedia);
            resetUI(currentBtn, currentBar, currentTime);
            cancelProgress();
        }

        playToken++;
        var token = playToken;

        playCandidates(candidates, 0).then(function (media) {
            if (token !== playToken) {
                stopMedia(media);
                return;
            }

            currentMedia = media;
            currentBtn = btn;
            currentBar = bar;
            currentTime = time;

            btn.classList.add('is-playing');
            updateProgress(media, bar, time);

            media.onended = function () {
                resetUI(btn, bar, time);
                if (currentMedia === media) {
                    currentMedia = null;
                    currentBtn = null;
                    currentBar = null;
                    currentTime = null;
                }
                cancelProgress();
            };
        }).catch(function () {
            resetUI(btn, bar, time);
            cancelProgress();

            if (openLink && openLink.href) {
                alert('目前瀏覽器不支援 AnimeThemes 的 OGG / WebM 播放，請改用「開啟原檔」。');
            } else {
                alert('目前瀏覽器不支援此音訊格式。');
            }
        });
    });

    document.querySelectorAll('.asd-music-progress-wrap').forEach(function (progressWrap) {
        progressWrap.addEventListener('click', function (ev) {
            var wrap = progressWrap.closest('.asd-music-player-wrap');
            if (!wrap) return;

            var media = currentMedia && wrap.contains(currentMedia) ? currentMedia : null;
            if (!media || !media.duration) return;

            var rect = progressWrap.getBoundingClientRect();
            var ratio = (ev.clientX - rect.left) / rect.width;
            ratio = Math.max(0, Math.min(1, ratio));
            media.currentTime = ratio * media.duration;
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

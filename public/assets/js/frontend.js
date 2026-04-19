/**
 * Frontend JavaScript
 * Anime Sync Pro — frontend.js
 * 包含：Lazy Load、Sticky Tab、集數/Staff/Cast 展開、
 *       音樂播放器、Tab 高亮、倒數計時
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
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
        if ('IntersectionObserver' in window) {
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
    }

    // ========================================
    // Sticky Tab
    // ========================================
    function initStickyTabs() {
        var $nav = $('.asd-tabs');
        if ($nav.length === 0) return;

        var navTop = $nav.offset().top;

        $(window).on('scroll.stickytabs', function () {
            if ($(window).scrollTop() > navTop) {
                $nav.addClass('sticky');
            } else {
                $nav.removeClass('sticky');
            }
        });
    }

    // ========================================
    // Tab 高亮（根據 scroll 位置）
    // ========================================
    function initActiveTab() {
        var $tabs = $('.asd-tab');
        if ($tabs.length === 0) return;

        // 點擊 Tab 時標記 active
        $tabs.on('click', function () {
            $tabs.removeClass('is-active');
            $(this).addClass('is-active');
        });

        // scroll 時自動更新 active tab
        $(window).on('scroll.activetab', function () {
            var scrollTop = $(window).scrollTop() + 100;
            var current = '';

            $tabs.each(function () {
                var href = $(this).attr('href');
                if (!href || href.charAt(0) !== '#') return;
                var $section = $(href);
                if ($section.length && $section.offset().top <= scrollTop) {
                    current = href;
                }
            });

            if (current) {
                $tabs.removeClass('is-active');
                $('.asd-tab[href="' + current + '"]').addClass('is-active');
            }
        });
    }

// ========================================
// 集數 / Staff / Cast 展開
// ========================================
function initToggleExpand() {

    // ── 集數展開 ──
    $(document).on('click', '.asd-ep-toggle', function () {
        var $btn = $(this);
        $('#asd-ep-list .asd-ep-hidden').removeClass('asd-ep-hidden');
        $btn.closest('div').fadeOut(200);
    });

    // ── Staff 展開 ──
    $(document).on('click', '.asd-staff-toggle', function () {
        var $btn = $(this);
        $('#asd-staff-grid .asd-staff-hidden').removeClass('asd-staff-hidden');
        $btn.closest('div').fadeOut(200);
    });

    // ── Cast 展開 ──
    $(document).on('click', '.asd-cast-toggle', function () {
        var $btn = $(this);
        $('#asd-cast-grid .asd-cast-hidden').removeClass('asd-cast-hidden');
        $btn.closest('div').fadeOut(200);
    });
}


    // ========================================
    // 音樂播放器
    // ========================================
    function initMusicPlayer() {
        // 目前播放中的播放器
        var currentAudio = null;
        var currentBtn   = null;
        var rafId        = null;

        $(document).on('click', '.asd-music-play-btn', function () {
            var $btn   = $(this);
            var $card  = $btn.closest('.asd-music-card-v2');
            var $wrap  = $card.find('.asd-music-player-wrap');
            var audio  = $wrap.find('.asd-music-audio')[0];
            var $bar   = $wrap.find('.asd-music-progress-bar');
            var $time  = $wrap.find('.asd-music-time');

            if (!audio) return;

            // 點擊其他播放器時先暫停當前
            if (currentAudio && currentAudio !== audio) {
                currentAudio.pause();
                $(currentBtn).removeClass('is-playing');
                if (rafId) cancelAnimationFrame(rafId);
            }

            if (audio.paused) {
                audio.play().then(function () {
                    $btn.addClass('is-playing');
                    currentAudio = audio;
                    currentBtn   = $btn[0];
                    updateProgress($bar, $time, audio);
                }).catch(function (e) {
                    console.warn('播放失敗:', e);
                });
            } else {
                audio.pause();
                $btn.removeClass('is-playing');
                if (rafId) cancelAnimationFrame(rafId);
            }

            // 播放結束
            audio.onended = function () {
                $btn.removeClass('is-playing');
                $bar.css('width', '0%');
                $time.text('0:00');
                if (rafId) cancelAnimationFrame(rafId);
            };

            // 點擊進度條跳轉
            $wrap.find('.asd-music-progress-wrap').off('click.seek').on('click.seek', function (e) {
                if (!audio.duration) return;
                var rect  = this.getBoundingClientRect();
                var ratio = (e.clientX - rect.left) / rect.width;
                audio.currentTime = ratio * audio.duration;
            });
        });

        function updateProgress($bar, $time, audio) {
            rafId = requestAnimationFrame(function () {
                if (audio.duration) {
                    var pct = (audio.currentTime / audio.duration) * 100;
                    $bar.css('width', pct + '%');
                    $time.text(formatTime(audio.currentTime));
                }
                if (!audio.paused) {
                    updateProgress($bar, $time, audio);
                }
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
        var $cd = $('.asd-countdown[data-ts]');
        if ($cd.length === 0) return;

        var ts = parseInt($cd.data('ts'), 10) * 1000;

        function tick() {
            var diff = ts - Date.now();
            if (diff <= 0) {
                $cd.text('即將播出');
                return;
            }
            var d = Math.floor(diff / 86400000);
            var h = Math.floor((diff % 86400000) / 3600000);
            var m = Math.floor((diff % 3600000)  / 60000);
            var s = Math.floor((diff % 60000)    / 1000);
            var str = '';
            if (d > 0) str += d + ' 天 ';
            str += pad(h) + ':' + pad(m) + ':' + pad(s);
            $cd.text(str);
        }

        function pad(n) {
            return n < 10 ? '0' + n : n;
        }

        tick();
        setInterval(tick, 1000);
    }

})(jQuery);

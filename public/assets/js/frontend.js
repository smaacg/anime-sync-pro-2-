/**
 * Frontend JavaScript
 * ACF – 選擇器從 .anime-quick-nav 修正為 .asd-tabs（對齊 single-anime.php）
 *
 * @package Anime_Sync_Pro
 */

(function($) {
    'use strict';

   $(document).ready(function() {
    initLazyLoad();
    initStickyTabs();
    initToggleExpand(); // ← 加這行
});

    // ========================================
    // 圖片 Lazy Load
    // ========================================
    function initLazyLoad() {
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
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

            document.querySelectorAll('img[data-src]').forEach(function(img) {
                observer.observe(img);
            });
        }
    }

    // ========================================
    // ACF 修正：Sticky Tab（選擇器改為 .asd-tabs）
    // ========================================
    function initStickyTabs() {
        var $nav = $('.asd-tabs');
        if ($nav.length === 0) return;

        var navTop = $nav.offset().top;

        $(window).on('scroll.stickytabs', function() {
            if ($(window).scrollTop() > navTop) {
                $nav.addClass('sticky');
            } else {
                $nav.removeClass('sticky');
            }
        });
    }
    // ========================================
// 集數 / Staff / Cast 展開
// ========================================
function initToggleExpand() {

    // 集數展開
    $(document).on('click', '.asd-ep-toggle', function() {
        $('#asd-ep-list .asd-ep-hidden').removeClass('asd-ep-hidden');
        $(this).hide();
    });

    // Staff 展開
    $(document).on('click', '.asd-staff-toggle', function() {
        var $grid = $(this).closest('section').find('.asd-staff-hidden');
        $grid.removeClass('asd-staff-hidden');
        $(this).hide();
    });

    // Cast 展開
    $(document).on('click', '.asd-cast-toggle', function() {
        var $grid = $(this).closest('section').find('.asd-cast-hidden');
        $grid.removeClass('asd-cast-hidden');
        $(this).hide();
    });
}

})(jQuery);

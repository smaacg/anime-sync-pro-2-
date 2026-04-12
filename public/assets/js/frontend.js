/**
 * Frontend JavaScript
 * 
 * @package Anime_Sync_Pro
 */

(function($) {
    'use strict';

    // ========================================
    // 初始化
    // ========================================
    $(document).ready(function() {
        initQuickNav();
        initLazyLoad();
        initStickyNav();
    });

    // ========================================
    // 快速導覽：滾動高亮
    // ========================================
    function initQuickNav() {
        const $navLinks = $('.anime-quick-nav a');
        
        if ($navLinks.length === 0) return;
        
        // 點擊平滑滾動
        $navLinks.on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            const $target = $(target);
            
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - 80
                }, 600, 'swing');
            }
        });
        
        // 滾動時高亮對應導覽
        $(window).on('scroll.quicknav', function() {
            const scrollTop = $(window).scrollTop();
            
            $navLinks.each(function() {
                const $link = $(this);
                const targetId = $link.attr('href');
                const $section = $(targetId);
                
                if ($section.length) {
                    const sectionTop = $section.offset().top - 100;
                    const sectionBottom = sectionTop + $section.outerHeight();
                    
                    if (scrollTop >= sectionTop && scrollTop < sectionBottom) {
                        $navLinks.css('border-bottom-color', 'transparent')
                                 .css('color', '#9e9e9e');
                        $link.css('border-bottom-color', '#2271b1')
                             .css('color', '#fff');
                    }
                }
            });
        });
    }

    // ========================================
    // 圖片 Lazy Load（備用）
    // ========================================
    function initLazyLoad() {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    }
                });
            }, {
                rootMargin: '100px'
            });
            
            document.querySelectorAll('img[data-src]').forEach(function(img) {
                observer.observe(img);
            });
        }
    }

    // ========================================
    // 固定導覽列
    // ========================================
    function initStickyNav() {
        const $nav = $('.anime-quick-nav');
        
        if ($nav.length === 0) return;
        
        const navTop = $nav.offset().top;
        
        $(window).on('scroll.stickynav', function() {
            if ($(window).scrollTop() > navTop) {
                $nav.addClass('sticky');
            } else {
                $nav.removeClass('sticky');
            }
        });
    }

})(jQuery);

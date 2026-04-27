/* ============================================================
   微笑動漫 — 共用導航模組 (FINAL)
   修正重點：
   1. 移除重複 DOMContentLoaded 初始化
   2. 保留手機漢堡選單功能
   3. 補強桌機 hover / focus dropdown
   4. 避免 resize 後狀態殘留
   5. 保留 overflow more 功能
============================================================ */

'use strict';

/* ── 1. 導航溢出（NavOverflow）──────────────────────────── */
const NavOverflow = (() => {
  const PRIMARY_NAV_ITEMS_SELECTOR = '#primary-nav > .nav-link[data-nav-id]';
  const OVERFLOW_SENTINEL_SEL = '#nav-more-dropdown .nav-dropdown-divider';
  const MORE_ITEM_SEL = '#nav-more-item';
  const MORE_BTN_SEL = '#nav-more-btn';
  const NAV_SEL = '#primary-nav';

  let _overflowItems = [];

  function _getNavLinks() {
    return Array.from(document.querySelectorAll(PRIMARY_NAV_ITEMS_SELECTOR));
  }

  function _getSentinel() {
    return document.querySelector(OVERFLOW_SENTINEL_SEL);
  }

  function _getMoreItem() {
    return document.querySelector(MORE_ITEM_SEL);
  }

  function _restoreAll() {
    _overflowItems.forEach(({ el, clone }) => {
      el.classList.remove('nav-hidden');
      if (clone) clone.remove();
    });
    _overflowItems = [];
  }

  function _update() {
    const nav = document.querySelector(NAV_SEL);
    const moreItem = _getMoreItem();
    if (!nav || !moreItem) return;

    const navLinks = _getNavLinks();
    _restoreAll();

    moreItem.style.display = '';

    const moreW = moreItem.getBoundingClientRect().width || 72;
    const navW = nav.getBoundingClientRect().width;

    let usedW = 0;
    navLinks.forEach(el => {
      usedW += el.getBoundingClientRect().width + 4;
    });

    if (usedW <= navW - moreW - 8) return;

    const sentinel = _getSentinel();
    let cumW = 0;
    let hiddenCount = 0;

    for (let i = navLinks.length - 1; i >= 0; i--) {
      const el = navLinks[i];
      cumW += el.getBoundingClientRect().width + 2;
      if (usedW - cumW <= navW - moreW) {
        hiddenCount = navLinks.length - i;
        break;
      }
    }

    hiddenCount = Math.max(1, hiddenCount);
    const toHide = navLinks.slice(navLinks.length - hiddenCount);

    toHide.forEach(el => {
      const clone = document.createElement('a');
      clone.className = 'nav-overflow-item';
      clone.href = el.href;
      clone.dataset.overflowFor = el.dataset.navId || '';

      const dropRef = document.querySelector(
        `#nav-more-dropdown [data-nav-id="${el.dataset.navId}"]`
      );

      if (dropRef) {
        clone.innerHTML = dropRef.innerHTML;
      } else {
        clone.textContent = el.textContent.trim();
      }

      if (sentinel) {
        sentinel.before(clone);
      } else {
        const moreDropdown = document.querySelector('#nav-more-dropdown');
        if (moreDropdown) moreDropdown.appendChild(clone);
      }

      el.classList.add('nav-hidden');
      _overflowItems.push({ el, clone });
    });
  }

  function init() {
    _update();

    let resizeTimer = null;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(_update, 80);
    });

    const moreBtn = document.querySelector(MORE_BTN_SEL);
    const moreDropdown = document.querySelector('#nav-more-dropdown');

    if (moreBtn && moreDropdown) {
      moreBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const open = moreDropdown.classList.toggle('nav-more-open');
        moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');

        moreDropdown.style.opacity = open ? '1' : '';
        moreDropdown.style.pointerEvents = open ? 'all' : '';
        moreDropdown.style.transform = open ? 'translateX(-50%) translateY(0)' : '';
      });

      document.addEventListener('click', (e) => {
        if (!moreBtn.contains(e.target) && !moreDropdown.contains(e.target)) {
          moreDropdown.classList.remove('nav-more-open');
          moreBtn.setAttribute('aria-expanded', 'false');
          moreDropdown.style.opacity = '';
          moreDropdown.style.pointerEvents = '';
          moreDropdown.style.transform = '';
        }
      });
    }
  }

  return { init };
})();

/* ── 2. 最大化 / 還原（FullscreenToggle）───────────────── */
const FullscreenToggle = (() => {
  function init() {
    const btn = document.getElementById('maximize-btn');
    const icon = document.getElementById('maximize-icon');
    if (!btn) return;

    function updateIcon() {
      const isFull = !!(
        document.fullscreenElement ||
        document.webkitFullscreenElement ||
        document.mozFullScreenElement
      );

      if (icon) {
        icon.className = isFull ? 'fa-solid fa-compress' : 'fa-solid fa-expand';
      }

      btn.classList.toggle('active', isFull);
      btn.title = isFull ? '還原視窗' : '最大化頁面';
      btn.setAttribute('aria-label', isFull ? '還原視窗' : '最大化頁面');
    }

    btn.addEventListener('click', () => {
      if (
        document.fullscreenElement ||
        document.webkitFullscreenElement ||
        document.mozFullScreenElement
      ) {
        (document.exitFullscreen ||
          document.webkitExitFullscreen ||
          document.mozCancelFullScreen ||
          function () {})();
      } else {
        const el = document.documentElement;
        (el.requestFullscreen ||
          el.webkitRequestFullscreen ||
          el.mozRequestFullScreen ||
          function () {}).call(el);
      }
    });

    document.addEventListener('fullscreenchange', updateIcon);
    document.addEventListener('webkitfullscreenchange', updateIcon);
    document.addEventListener('mozfullscreenchange', updateIcon);

    updateIcon();
  }

  return { init };
})();

/* ── 3. 手機 / 桌機選單（MobileMenu）──────────────────── */
const MobileMenu = (() => {
  function init() {
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const nav = document.getElementById('primary-nav');

    if (!toggleBtn || !nav) return;

    const dropdownItems = Array.from(nav.querySelectorAll('.nav-item.has-dropdown'));

    function getDirectLink(item) {
      return item.querySelector(':scope > .nav-link') || item.querySelector('.nav-link');
    }

    function getDirectDropdown(item) {
      return item.querySelector(':scope > .nav-dropdown') || item.querySelector('.nav-dropdown');
    }

    function closeAllSubmenus(except = null) {
      dropdownItems.forEach(item => {
        if (except && item === except) return;
        item.classList.remove('mobile-open', 'nav-open');

        const link = getDirectLink(item);
        if (link) link.setAttribute('aria-expanded', 'false');
      });
    }

    function setMainMenu(open) {
      nav.classList.toggle('mobile-open', open);
      toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');

      const icon = toggleBtn.querySelector('i');
      if (icon) {
        icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
      }

      if (!open) closeAllSubmenus();
    }

    /* 主手機選單 */
    toggleBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      setMainMenu(!nav.classList.contains('mobile-open'));
    });

    /* 一般下拉父項 */
    dropdownItems.forEach(item => {
      const link = getDirectLink(item);
      const dropdown = getDirectDropdown(item);
      if (!link || !dropdown) return;

      /* 桌機 hover / focus 補強 */
      item.addEventListener('mouseenter', () => {
        if (window.innerWidth > 900) {
          item.classList.add('nav-open');
        }
      });

      item.addEventListener('mouseleave', () => {
        if (window.innerWidth > 900) {
          item.classList.remove('nav-open');
        }
      });

      item.addEventListener('focusin', () => {
        if (window.innerWidth > 900) {
          item.classList.add('nav-open');
        }
      });

      item.addEventListener('focusout', (e) => {
        if (window.innerWidth > 900 && !item.contains(e.relatedTarget)) {
          item.classList.remove('nav-open');
        }
      });

      /* 手機點父項展開 */
      link.addEventListener('click', (e) => {
        if (window.innerWidth <= 900) {
          e.preventDefault();
          e.stopPropagation();

          const willOpen = !item.classList.contains('mobile-open');
          closeAllSubmenus(item);

          item.classList.toggle('mobile-open', willOpen);
          item.classList.toggle('nav-open', willOpen);
          link.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
      });
    });

    /* 點外部關閉手機主選單 */
    document.addEventListener('click', (e) => {
      if (
        window.innerWidth <= 900 &&
        nav.classList.contains('mobile-open') &&
        !nav.contains(e.target) &&
        !toggleBtn.contains(e.target)
      ) {
        setMainMenu(false);
      }
    });

    /* ESC 關閉 */
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeAllSubmenus();
        if (window.innerWidth <= 900) {
          setMainMenu(false);
        }
      }
    });

    /* resize 清狀態 */
    window.addEventListener('resize', () => {
      if (window.innerWidth > 900) {
        nav.classList.remove('mobile-open');
        toggleBtn.setAttribute('aria-expanded', 'false');

        const icon = toggleBtn.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-bars';

        closeAllSubmenus();
      } else {
        closeAllSubmenus();
      }
    });
  }

  return { init };
})();

/* ── 4. 搜尋覆蓋層（SearchOverlay）────────────────────── */
const SearchOverlay = (() => {
  function init() {
    const toggleBtns = document.querySelectorAll('#search-toggle, .search-btn');
    const closeBtn = document.getElementById('search-close');
    const overlay = document.getElementById('search-overlay');
    const input = document.getElementById('search-input');

    if (!overlay) return;

    function open() {
      overlay.classList.add('open');
      if (input) setTimeout(() => input.focus(), 100);
    }

    function close() {
      overlay.classList.remove('open');
    }

    toggleBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        overlay.classList.contains('open') ? close() : open();
      });
    });

    if (closeBtn) closeBtn.addEventListener('click', close);

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') close();
    });

    overlay.addEventListener('click', e => {
      if (e.target === overlay) close();
    });
  }

  return { init };
})();

/* ── 5. Sticky Header ─────────────────────────────────── */
const StickyHeader = (() => {
  function init() {
    const header = document.getElementById('site-header');
    if (!header) return;

    window.addEventListener(
      'scroll',
      () => {
        header.classList.toggle('scrolled', window.scrollY > 50);
      },
      { passive: true }
    );
  }

  return { init };
})();

/* ── 6. 自動高亮目前頁面 ───────────────────────────────── */
const NavAutoActive = (() => {
  const FILE_NAV_MAP = {
    'index.html': null,
    'season.html': 'season',
    'news.html': 'news',
    'anime-list.html': 'anime',
    'anime.html': 'anime',
    'music.html': 'music',
    'ai-tools.html': 'ai',
    'ranking.html': 'ranking',
    'forum.html': 'forum',
    'lofi.html': 'lofi',
    'about.html': 'about',
    'games.html': 'games',
    'esports.html': 'esports',
    'manga.html': 'manga',
    'lightnovel.html': 'novel',
    'merch.html': 'merch',
    'vtuber.html': 'vtuber',
    'cosplay.html': 'cosplay',
    'pilgrimage.html': 'pilgrimage',
    'sponsor.html': 'sponsor',
    'search.html': null,
    '404.html': null,
    'privacy.html': null,
    'terms.html': null,
  };

  function init() {
    const path = window.location.pathname;
    const fileName = path.split('/').pop() || 'index.html';
    const activeId = FILE_NAV_MAP[fileName];

    if (!activeId) return;

    document.querySelectorAll('.nav-link.active, .nav-dropdown a.active').forEach(el => {
      el.classList.remove('active');
    });

    const mainLink = document.querySelector(
      `#primary-nav > .nav-link[data-nav-id="${activeId}"]`
    );
    if (mainLink) {
      mainLink.classList.add('active');
      return;
    }

    const dropLink = document.querySelector(
      `#nav-more-dropdown [data-nav-id="${activeId}"]`
    );

    if (dropLink) {
      dropLink.classList.add('active');
      const moreBtn = document.getElementById('nav-more-btn');
      if (moreBtn) moreBtn.classList.add('active');
    }
  }

  return { init };
})();

/* ── 7. dropdown 裁切補強 ─────────────────────────────── */
function fixDropdownOverflow() {
  const bar = document.querySelector('.header-nav-bar');
  const container = document.querySelector('.header-nav-bar .container');
  const nav = document.querySelector('.header-nav-bar .primary-nav');

  if (bar) {
    bar.style.setProperty('overflow-x', 'auto', 'important');
    bar.style.setProperty('overflow-y', 'visible', 'important');
  }

  if (container) {
    container.style.setProperty('overflow', 'visible', 'important');
  }

  if (nav) {
    nav.style.setProperty('overflow', 'visible', 'important');
  }
}

/* ── 唯一初始化 ───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  StickyHeader.init();
  NavOverflow.init();
  FullscreenToggle.init();
  MobileMenu.init();
  SearchOverlay.init();
  NavAutoActive.init();

  fixDropdownOverflow();
  window.addEventListener('load', fixDropdownOverflow);
  setTimeout(fixDropdownOverflow, 300);
  setTimeout(fixDropdownOverflow, 1000);

  console.log('[Nav] 導航模組初始化完成');
});

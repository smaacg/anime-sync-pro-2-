<?php
/**
 * 微笑動漫 Child Theme — header.php
 * 保守穩定版：
 * - 保留既有結構 / 樣式系統
 * - 不干擾目前已正常的手機版
 * - 只補桌機版 submenu 顯示
 *
 * @package SmileACG
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- ══════════════════════════════════════════
     登入 Modal
══════════════════════════════════════════ -->
<div id="login-modal" class="lm-overlay" role="dialog" aria-modal="true" aria-label="登入">
  <div class="lm-box">

    <button class="lm-close" id="lm-close" aria-label="關閉">
      <i class="fa-solid fa-xmark"></i>
    </button>

    <div class="lm-logo">
      <span class="logo-icon-box" aria-hidden="true">^_^</span>
      <span class="logo-text">微笑動漫<span class="logo-plus">+</span></span>
    </div>

    <p class="lm-subtitle">登入以解鎖完整功能</p>

    <div class="lm-tabs">
      <button class="lm-tab active" data-tab="login">登入</button>
      <button class="lm-tab" data-tab="register">註冊</button>
    </div>

    <!-- 登入面板 -->
    <div class="lm-panel" id="lm-panel-login">
      <?php echo do_shortcode('[ultimatemember form_id="1519"]'); ?>
    </div>

    <!-- 註冊面板 -->
    <div class="lm-panel" id="lm-panel-register" hidden>
      <?php echo do_shortcode('[ultimatemember form_id="1518"]'); ?>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════════════
     Header
══════════════════════════════════════════ -->
<header class="site-header glass-mid" id="site-header">

  <div class="header-top container">

    <a href="<?php echo esc_url( home_url('/') ); ?>" class="site-logo" aria-label="微笑動漫首頁">
      <span class="logo-icon-box" aria-hidden="true">^_^</span>
      <div class="logo-text-wrap">
        <span class="logo-text">微笑動漫<span class="logo-plus">+</span></span>
        <span class="logo-tagline">動漫的便利商店</span>
      </div>
    </a>

    <div class="header-search-wrap">
      <div class="header-search-box" id="header-search-box">
        <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
        <input type="text"
               id="header-search-input"
               class="header-search-input"
               placeholder="搜尋…"
               autocomplete="off"
               aria-label="搜尋" />
        <button class="header-search-btn" id="header-search-submit" aria-label="送出搜尋">搜尋</button>
      </div>
      <div class="header-search-dropdown" id="header-search-dropdown" aria-live="polite"></div>
    </div>

    <div class="header-actions">
      <?php if ( is_user_logged_in() ) :
        $user = wp_get_current_user(); ?>
        <div class="header-avatar-wrap">
          <?php echo get_avatar( $user->ID, 32, '', '', ['class' => 'header-avatar'] ); ?>
          <span class="header-username"><?php echo esc_html( $user->display_name ); ?></span>
        </div>
      <?php else : ?>
        <button type="button"
                class="btn btn-ghost btn-sm header-login-btn"
                id="open-login-modal">
          <i class="fa-solid fa-right-to-bracket"></i> 登入
        </button>
        <button type="button"
                class="btn btn-primary btn-sm header-reg-btn"
                id="open-register-modal">註冊</button>
      <?php endif; ?>

      <button class="btn-icon btn-ghost mobile-menu-btn"
              id="mobile-menu-toggle"
              aria-label="開關選單"
              aria-expanded="false">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
      </button>
    </div>

  </div>

  <div class="header-nav-bar">
    <div class="container">
      <nav class="primary-nav" id="primary-nav" aria-label="主選單">
        <?php
        if ( has_nav_menu( 'primary-menu' ) ) {
            wp_nav_menu( [
                'theme_location' => 'primary-menu',
                'container'      => false,
                'fallback_cb'    => false,
                'items_wrap'     => '%3$s',
                'walker'         => new SmileACG_Nav_Walker(),
            ] );
        } else {
            $nav_links = [
                [ 'url' => home_url('/'),         'label' => '首頁',    'icon' => 'fa-solid fa-house',                'check' => is_front_page() ],
                [ 'url' => home_url('/season/'),  'label' => '新番',    'icon' => 'fa-solid fa-calendar-days',        'check' => is_page('season') ],
                [ 'url' => home_url('/news/'),    'label' => '新聞',    'icon' => 'fa-solid fa-newspaper',            'check' => is_page('news') ],
                [ 'url' => home_url('/anime/'),   'label' => '動漫',    'icon' => 'fa-solid fa-film',                 'check' => ( is_post_type_archive('anime') || is_singular('anime') ) ],
                [ 'url' => home_url('/ranking/'), 'label' => '排行',    'icon' => 'fa-solid fa-trophy',              'check' => is_page('ranking') ],
                [ 'url' => home_url('/music/'),   'label' => '音樂',    'icon' => 'fa-solid fa-music',               'check' => is_page('music') ],
                [ 'url' => home_url('/cosplay/'), 'label' => 'COSPLAY', 'icon' => 'fa-solid fa-wand-magic-sparkles', 'check' => is_page('cosplay') ],
                [ 'url' => home_url('/about/'),   'label' => '關於',    'icon' => 'fa-solid fa-circle-info',         'check' => is_page('about') ],
            ];
            foreach ( $nav_links as $link ) {
                $active = $link['check'] ? ' active' : '';
                echo '<div class="nav-item">';
                echo '<a href="' . esc_url( $link['url'] ) . '" class="nav-link' . $active . '">';
                echo '<i class="' . esc_attr( $link['icon'] ) . '" aria-hidden="true"></i> ';
                echo esc_html( $link['label'] );
                echo '</a>';
                echo '</div>';
            }
        }
        ?>
      </nav>
    </div>
  </div>

</header>

<script>
(function () {

  /* ── 搜尋 ── */
  const input     = document.getElementById('header-search-input');
  const dropdown  = document.getElementById('header-search-dropdown');
  const submitBtn = document.getElementById('header-search-submit');
  let timer;

  if (input) {
    input.addEventListener('input', function () {
      clearTimeout(timer);
      const q = this.value.trim();

      if (q.length < 2) {
        if (dropdown) {
          dropdown.innerHTML = '';
          dropdown.classList.remove('open');
        }
        return;
      }

      timer = setTimeout(() => fetchResults(q), 300);
    });

    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') doSearch();
    });
  }

  if (submitBtn) {
    submitBtn.addEventListener('click', doSearch);
  }

  function doSearch() {
    const q = input ? input.value.trim() : '';
    if (!q) return;
    window.location.href = '<?php echo esc_js( home_url('/?s=') ); ?>' + encodeURIComponent(q);
  }

  function fetchResults(q) {
    if (typeof smaacg_ajax === 'undefined' || !dropdown) return;

    fetch(smaacg_ajax.ajax_url, {
      method : 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body   : new URLSearchParams({
        action: 'smaacg_search',
        nonce: smaacg_ajax.nonce,
        query: q
      }),
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.data || !data.data.length) {
        dropdown.innerHTML = '<div class="search-no-result">找不到相關結果</div>';
        dropdown.classList.add('open');
        return;
      }

      dropdown.innerHTML = data.data.map(item => `
        <a href="${item.url}" class="search-result-item">
          ${item.thumb
            ? `<img src="${item.thumb}" alt="" class="search-result-thumb" loading="lazy">`
            : `<span class="search-result-thumb-ph"><i class="fa-solid fa-film"></i></span>`}
          <span class="search-result-info">
            <span class="search-result-title">${item.title}</span>
            <span class="search-result-type">${item.type === 'anime' ? '動漫' : '新聞'}</span>
          </span>
        </a>`).join('');
      dropdown.classList.add('open');
    })
    .catch(() => {});
  }

  document.addEventListener('click', e => {
    if (!dropdown) return;
    if (!e.target.closest('#header-search-box') && !e.target.closest('#header-search-dropdown')) {
      dropdown.classList.remove('open');
    }
  });

  /* ── 登入 Modal ── */
  const modal    = document.getElementById('login-modal');
  const openBtn  = document.getElementById('open-login-modal');
  const closeBtn = document.getElementById('lm-close');
  const tabs     = document.querySelectorAll('.lm-tab');
  const panels   = {
    login    : document.getElementById('lm-panel-login'),
    register : document.getElementById('lm-panel-register'),
  };

function openModal() {
    if (!modal) return;
    document.body.style.overflow = 'hidden';
    setTimeout(() => modal.classList.add('lm-open'), 10);
}
window.smacgOpenLoginModal = function() {
    switchTab('login');
    openModal();
};

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('lm-open');
    document.body.style.overflow = '';
  }

  function switchTab(target) {
    tabs.forEach(t => t.classList.remove('active'));
    document.querySelector(`.lm-tab[data-tab="${target}"]`)?.classList.add('active');
    Object.entries(panels).forEach(([key, el]) => {
      if (el) el.hidden = (key !== target);
    });
  }

  if (openBtn) {
    openBtn.addEventListener('click', () => {
      switchTab('login');
      openModal();
    });
  }

  const openRegBtn = document.getElementById('open-register-modal');
  if (openRegBtn) {
    openRegBtn.addEventListener('click', () => {
      switchTab('register');
      openModal();
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', closeModal);
  }

  if (modal) {
    modal.addEventListener('click', e => {
      if (e.target === modal) closeModal();
    });
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal && modal.classList.contains('lm-open')) {
      closeModal();
    }
  });

  tabs.forEach(tab => {
    tab.addEventListener('click', function () {
      switchTab(this.dataset.tab);
    });
  });

})();
</script>

<!-- ==========================================================
     桌機版 Dropdown 最小修補
     - 不碰手機版
     - 不重寫主樣式
     - 只確保桌機 submenu 顯示出來
=========================================================== -->
<style id="smaacg-desktop-dropdown-fix">
@media (min-width: 901px) {
  .site-header,
  .header-nav-bar,
  .header-nav-bar .container,
  .header-nav-bar .primary-nav,
  .header-nav-bar .nav-item {
    overflow: visible !important;
  }

  .header-nav-bar .nav-item.has-dropdown {
    position: relative !important;
  }

  /* 補 bridge，避免滑鼠從主選單移到子選單時中間斷掉 */
  .header-nav-bar .nav-item.has-dropdown::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: 100%;
    height: 12px;
  }

  .header-nav-bar .nav-item.has-dropdown:hover > .nav-dropdown,
  .header-nav-bar .nav-item.has-dropdown.nav-open > .nav-dropdown,
  .header-nav-bar .nav-item.has-dropdown:focus-within > .nav-dropdown {
    display: flex !important;
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
    z-index: 2147483647 !important;
  }
}
</style>

<script id="smaacg-desktop-dropdown-fix-script">
(function () {
  if (window.__SMAACG_DESKTOP_DROPDOWN_FIX__) return;
  window.__SMAACG_DESKTOP_DROPDOWN_FIX__ = true;

  function initDesktopDropdownFix() {
    const nav = document.getElementById('primary-nav');
    if (!nav) return;

    const items = Array.from(nav.querySelectorAll('.nav-item.has-dropdown'));

    items.forEach(item => {
      const dropdown = item.querySelector(':scope > .nav-dropdown');
      if (!dropdown) return;

      let closeTimer = null;

      const openDropdown = () => {
        if (window.innerWidth <= 900) return;
        clearTimeout(closeTimer);

        item.classList.add('nav-open');
        dropdown.style.display = 'flex';
        dropdown.style.opacity = '1';
        dropdown.style.visibility = 'visible';
        dropdown.style.pointerEvents = 'auto';
        dropdown.style.zIndex = '2147483647';
      };

      const closeDropdown = () => {
        if (window.innerWidth <= 900) return;
        clearTimeout(closeTimer);

        closeTimer = setTimeout(() => {
          item.classList.remove('nav-open');
          dropdown.style.display = '';
          dropdown.style.opacity = '';
          dropdown.style.visibility = '';
          dropdown.style.pointerEvents = '';
          dropdown.style.zIndex = '';
        }, 120);
      };

      item.addEventListener('mouseenter', openDropdown);
      item.addEventListener('mouseleave', closeDropdown);

      item.addEventListener('focusin', openDropdown);
      item.addEventListener('focusout', (e) => {
        if (!item.contains(e.relatedTarget)) {
          closeDropdown();
        }
      });
    });

    /* 視窗縮到手機時，清掉桌機狀態 */
    window.addEventListener('resize', () => {
      if (window.innerWidth <= 900) {
        items.forEach(item => {
          item.classList.remove('nav-open');
          const dropdown = item.querySelector(':scope > .nav-dropdown');
          if (dropdown) {
            dropdown.style.display = '';
            dropdown.style.opacity = '';
            dropdown.style.visibility = '';
            dropdown.style.pointerEvents = '';
            dropdown.style.zIndex = '';
          }
        });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDesktopDropdownFix);
  } else {
    initDesktopDropdownFix();
  }
})();
</script>

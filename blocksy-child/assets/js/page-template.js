/**
 * 微笑動漫 — 共用頁面工具函式
 * 供各子頁面使用（mobile nav、sticky header、search）
 */
function initCommonPage() {
  /* Sticky Header */
  window.addEventListener('scroll', () =>
    document.getElementById('site-header')?.classList.toggle('scrolled', window.scrollY > 50)
  );

  /* Mobile Nav */
  const toggle = document.getElementById('mobile-menu-toggle');
  const nav    = document.getElementById('primary-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      nav.classList.toggle('mobile-open');
      toggle.innerHTML = nav.classList.contains('mobile-open')
        ? '<i class="fa-solid fa-xmark"></i>'
        : '<i class="fa-solid fa-bars"></i>';
    });
  }
  document.querySelectorAll('.nav-item > .nav-link').forEach(link => {
    link.addEventListener('click', function (e) {
      if (window.innerWidth > 900) return;
      e.preventDefault();
      this.closest('.nav-item').classList.toggle('mobile-open');
    });
  });
  document.addEventListener('click', e => {
    if (window.innerWidth > 900) return;
    if (!nav?.contains(e.target) && !toggle?.contains(e.target)) {
      nav?.classList.remove('mobile-open');
      if (toggle) toggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
    }
  });

  /* Search Overlay */
  const sToggle  = document.getElementById('search-toggle');
  const sOverlay = document.getElementById('search-overlay');
  const sClose   = document.getElementById('search-close');
  const sInput   = document.getElementById('search-input');
  sToggle?.addEventListener('click', () => {
    sOverlay?.classList.toggle('open');
    if (sOverlay?.classList.contains('open')) setTimeout(() => sInput?.focus(), 100);
  });
  sClose?.addEventListener('click', () => sOverlay?.classList.remove('open'));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') sOverlay?.classList.remove('open');
  });
  sInput?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && sInput.value.trim()) {
      window.location.href = `search.html?q=${encodeURIComponent(sInput.value.trim())}`;
    }
  });
}

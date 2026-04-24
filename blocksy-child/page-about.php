<?php
/**
 * Template Name: 關於本站
 * Template Post Type: page
 *
 * @package SmileACG
 */
get_header(); ?>

<style>
.page-hero--about { background:linear-gradient(135deg,rgba(59,130,246,0.12) 0%,rgba(139,92,246,0.1) 100%); border-bottom:1px solid var(--glass-border); padding:64px 0 48px; text-align:center; }
.about-page-title { font-size:40px; font-weight:800; color:var(--text-primary); margin-bottom:12px; }
.about-page-subtitle { font-size:16px; color:var(--text-muted); max-width:560px; margin:0 auto; line-height:1.7; }
.about-logo-box { width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3B82F6,#2563EB);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#fff;margin:0 auto 20px;box-shadow:0 8px 32px rgba(59,130,246,0.4); }
.about-section { padding:56px 0; }
.about-section + .about-section { border-top:1px solid var(--glass-border); }
.about-grid { display:grid; grid-template-columns:1fr 1fr; gap:48px; align-items:center; }
.about-h2 { font-size:26px;font-weight:800;color:var(--text-primary);margin-bottom:16px; }
.about-p { font-size:15px;color:var(--text-secondary);line-height:1.8;margin-bottom:14px; }
.feature-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:48px; }
.feature-card { border-radius:20px;padding:28px 24px;background:var(--glass-bg);border:1px solid var(--glass-border);transition:var(--trans-smooth); }
.feature-card:hover { transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,0.3); }
.feature-icon { width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:16px; }
.feature-title { font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:8px; }
.feature-desc { font-size:13px;color:var(--text-muted);line-height:1.7; }
.stats-row { display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:48px; }
.stat-box { text-align:center;padding:28px 20px;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:20px; }
.stat-num { font-size:36px;font-weight:800;color:var(--accent-blue);margin-bottom:4px; }
.stat-label { font-size:13px;color:var(--text-muted); }
.api-list { display:flex;flex-direction:column;gap:12px; }
.api-item { display:flex;align-items:center;gap:16px;padding:16px 20px;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:14px; }
.api-dot { width:10px;height:10px;border-radius:50%;flex-shrink:0; }
.api-name { font-size:14px;font-weight:700;color:var(--text-primary);min-width:140px; }
.api-desc { font-size:13px;color:var(--text-muted); }
.legal-links { display:flex;gap:20px;flex-wrap:wrap; }
.legal-link { padding:10px 20px;border-radius:var(--radius-pill);background:var(--glass-bg);border:1px solid var(--glass-border);font-size:13px;font-weight:600;color:var(--text-secondary);transition:var(--trans-fast);text-decoration:none; }
.legal-link:hover { color:var(--accent-blue);border-color:rgba(59,130,246,0.4); }
@media(max-width:900px){ .about-grid{grid-template-columns:1fr;gap:24px;} .feature-grid{grid-template-columns:1fr 1fr;} .stats-row{grid-template-columns:repeat(2,1fr);} }
@media(max-width:600px){ .feature-grid{grid-template-columns:1fr;} .stats-row{grid-template-columns:repeat(2,1fr);} .about-page-title{font-size:28px;} }
</style>

<!-- HERO -->
<div class="page-hero--about">
  <div class="container">
    <div class="about-logo-box">微</div>
    <h1 class="about-page-title">WeixiaoACG</h1>
    <p class="about-page-subtitle">華語 ACG 愛好者的高質感聚集地。動畫百科、季番表、OP/ED 試聽、追番記錄，一站滿足你的所有需求。</p>
  </div>
</div>

<main class="container">

  <!-- 特色功能 -->
  <section class="about-section">
    <h2 class="about-h2" style="text-align:center;margin-bottom:8px;">我們提供什麼？</h2>
    <p style="text-align:center;color:var(--text-muted);font-size:14px;margin-bottom:0;">整合多個頂級動漫資料庫，為你帶來最完整的 ACG 體驗</p>
    <div class="feature-grid">
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(59,130,246,0.15);color:var(--accent-blue);">📅</div>
        <div class="feature-title">本季新番週曆</div>
        <div class="feature-desc">依星期分組瀏覽當季所有播出作品，掌握最新播出動態，不漏追任何一部。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(139,92,246,0.15);color:#a78bfa;">🎵</div>
        <div class="feature-title">OP/ED 試聽</div>
        <div class="feature-desc">整合 AnimeThemes.moe，完整收錄歷年動畫 OP/ED，隨時試聽你最喜愛的主題曲。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">📖</div>
        <div class="feature-title">繁體中文資料</div>
        <div class="feature-desc">整合 Bangumi 資料庫並自動轉換為繁體中文，劇情、製作資訊完整呈現。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(249,115,22,0.15);color:#f97316;">📊</div>
        <div class="feature-title">三平台評分</div>
        <div class="feature-desc">同時顯示 AniList、MyAnimeList、Bangumi 三大平台評分，讓你做出最佳選擇。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(239,68,68,0.15);color:#f87171;">🎬</div>
        <div class="feature-title">角色與聲優</div>
        <div class="feature-desc">完整列出主要角色及配音聲優資訊，搭配頭像一目了然。</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(251,191,36,0.15);color:#fbbf24;">⭐</div>
        <div class="feature-title">追番進度記錄</div>
        <div class="feature-desc">本地記錄追番進度與收藏清單，快速掌握你的追番狀態。</div>
      </div>
    </div>
  </section>

  <!-- 數據統計 -->
  <section class="about-section">
    <h2 class="about-h2" style="text-align:center;">資料規模</h2>
    <div class="stats-row">
      <div class="stat-box">
        <div class="stat-num"><?php echo number_format( wp_count_posts('anime')->publish ?? 0 ); ?></div>
        <div class="stat-label">動畫作品</div>
      </div>
      <div class="stat-box"><div class="stat-num">4</div><div class="stat-label">資料來源 API</div></div>
      <div class="stat-box"><div class="stat-num">100%</div><div class="stat-label">繁體中文介面</div></div>
      <div class="stat-box"><div class="stat-num">免費</div><div class="stat-label">完全免費使用</div></div>
    </div>
  </section>

  <!-- 資料來源 -->
  <section class="about-section">
    <div class="about-grid">
      <div>
        <h2 class="about-h2">資料來源</h2>
        <p class="about-p">本站整合多個業界頂級動漫資料庫，所有資料均透過官方公開 API 取得，並不儲存於本站伺服器。</p>
        <p class="about-p">資料僅供資訊展示用途，版權歸各原始資料庫及內容創作者所有。</p>
      </div>
      <div class="api-list">
        <div class="api-item"><div class="api-dot" style="background:#02a9ff;"></div><div class="api-name">AniList</div><div class="api-desc">主要資料、Banner、評分、倒數、PV</div></div>
        <div class="api-item"><div class="api-dot" style="background:#f39c12;"></div><div class="api-name">Bangumi</div><div class="api-desc">中文名稱、劇情簡介、集數列表、關聯作品</div></div>
        <div class="api-item"><div class="api-dot" style="background:#2e51a2;"></div><div class="api-name">Jikan (MAL)</div><div class="api-desc">角色聲優、MAL 評分、串流平台</div></div>
        <div class="api-item"><div class="api-dot" style="background:#22c55e;"></div><div class="api-name">AnimeThemes.moe</div><div class="api-desc">OP/ED 主題曲試聽影片</div></div>
      </div>
    </div>
  </section>

  <!-- 法律 -->
  <section class="about-section">
    <h2 class="about-h2">法律資訊</h2>
    <p class="about-p">本站為非商業性質的動漫資訊平台，所有動畫資料、圖片、影片均來自第三方公開 API，版權歸各自持有人所有。本站不提供任何版權影片的非法串流或下載服務。</p>
    <div class="legal-links">
      <a href="<?php echo esc_url( home_url('/privacy/') ); ?>" class="legal-link"><i class="fa-solid fa-shield-halved"></i> 隱私權政策</a>
      <a href="<?php echo esc_url( home_url('/terms/') ); ?>" class="legal-link"><i class="fa-solid fa-file-contract"></i> 服務條款</a>
    </div>
  </section>

</main>

<?php get_footer(); ?>

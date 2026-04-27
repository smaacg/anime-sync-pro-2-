/* ============================================================
   微笑動漫 — ranking.js
   真實 API 版本：AniList GraphQL / Jikan / Bangumi
   ============================================================ */
'use strict';

/* ============================================================
   平台設定
   ============================================================ */
const PLATFORMS = {
  anilist: {
    label: 'AniList', icon: '🌐', color: '#02a9ff',
    desc: '來自 AniList 的全球評分排行，以現代介面和精細評分系統著稱',
    tags: ['歐美用戶為主', '評分細緻', '社群活躍'],
    link: 'https://anilist.co'
  },
  mal: {
    label: 'MAL / Jikan', icon: '📊', color: '#2e51a2',
    desc: '來自 MyAnimeList，全球最大動漫資料庫，歷史資料最完整',
    tags: ['全球最大', '歷史最完整', '用戶最多'],
    link: 'https://myanimelist.net'
  },
  bangumi: {
    label: 'Bangumi', icon: '🎯', color: '#f09199',
    desc: '來自 Bangumi 番組計畫，華語圈最完整的動漫資料庫',
    tags: ['華語圈最完整', '聲優資料詳盡', '中文社群'],
    link: 'https://bgm.tv'
  }
};

/* ============================================================
   STATE
   ============================================================ */
let rankState = {
  platform: 'anilist',
  period: 'weekly'
};

/* 快取，避免重複 API 請求 */
const _cache = {};

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  rankInitPlatformTabs();
  rankInitPeriodBtns();
  rankInitParticles();
  rankInitSwipe();
  rankRenderAll();
  rankInitSidebar();
});

/* ============================================================
   平台 Tab
   ============================================================ */
function rankInitPlatformTabs() {
  document.querySelectorAll('.rank-platform-tab').forEach(tab => {
    /* 移除站內 SmileACG+ 的 tab（尚無真實資料） */
if (tab.dataset.platform === 'site') {
  tab.style.display = 'none';
  tab.disabled = true;
  return;
}
    tab.addEventListener('click', () => {
      rankState.platform = tab.dataset.platform;
      document.querySelectorAll('.rank-platform-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      rankRenderAll();
    });
  });

  /* 預設選中 anilist */
  const defaultTab = document.querySelector('.rank-platform-tab[data-platform="anilist"]');
  if (defaultTab) {
    document.querySelectorAll('.rank-platform-tab').forEach(t => t.classList.remove('active'));
    defaultTab.classList.add('active');
  }

  /* 隱藏站內子分類列 */
  const siteSubRow = document.getElementById('site-sub-tabs');
  if (siteSubRow) siteSubRow.style.display = 'none';
}

/* ============================================================
   週期 Tab
   ============================================================ */
function rankInitPeriodBtns() {
  document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      rankState.period = btn.dataset.period;
      document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      rankFetchAndRender();
    });
  });
}

/* ============================================================
   RENDER ALL
   ============================================================ */
function rankRenderAll() {
  rankRenderPlatformCard(rankState.platform);
  rankFetchAndRender();
}

/* ============================================================
   平台介紹卡
   ============================================================ */
function rankRenderPlatformCard(platform) {
  const p = PLATFORMS[platform];
  if (!p) return;
  const card = document.getElementById('platform-info-card');
  if (!card) return;
  card.innerHTML = `
    <div style="display:flex;align-items:flex-start;gap:14px;">
      <div style="width:36px;height:36px;border-radius:10px;background:${p.color}22;color:${p.color};
                  display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">
        ${p.icon}
      </div>
      <div style="min-width:0;">
        <div style="font-weight:700;color:${p.color};margin-bottom:4px;">${p.label}</div>
        <p style="font-size:12px;color:var(--text-muted,rgba(208,215,224,.55));margin:0 0 8px;line-height:1.6;">${p.desc}</p>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          ${p.tags.map(t => `<span style="font-size:10px;padding:2px 8px;border-radius:50px;
            background:${p.color}15;border:1px solid ${p.color}30;color:${p.color};">${t}</span>`).join('')}
          ${p.link ? `<a href="${p.link}" target="_blank" rel="noopener"
            style="font-size:10px;padding:2px 8px;border-radius:50px;color:var(--text-muted);
            border:1px solid var(--glass-border,rgba(255,255,255,.08));text-decoration:none;">
            前往 ${p.label} ↗</a>` : ''}
        </div>
      </div>
    </div>`;
  card.style.borderColor = `${p.color}44`;
}

/* ============================================================
   Skeleton Loading
   ============================================================ */
function rankShowSkeleton() {
  const listEl = document.getElementById('rank-list');
  if (!listEl) return;
  listEl.innerHTML = Array(10).fill(`
    <div class="rank-loading">
      <div class="skeleton" style="height:90px;border-radius:16px;margin-bottom:8px;"></div>
    </div>`).join('');
}

/* ============================================================
   API FETCH + RENDER
   ============================================================ */
async function rankFetchAndRender() {
  const { platform, period } = rankState;
  const cacheKey = `${platform}_${period}`;

  rankShowSkeleton();

  try {
    let items = _cache[cacheKey];
    if (!items) {
      if (platform === 'anilist')  items = await fetchAniList(period);
      else if (platform === 'mal') items = await fetchMAL(period);
      else if (platform === 'bangumi') items = await fetchBangumi(period);
      else items = [];
      _cache[cacheKey] = items;
    }
    rankRenderList(items);
  } catch (e) {
    console.error('[ranking]', e);
    const listEl = document.getElementById('rank-list');
    if (listEl) listEl.innerHTML = `
      <div style="text-align:center;padding:48px 0;color:var(--text-muted);">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:28px;display:block;margin-bottom:12px;"></i>
        資料載入失敗，請稍後再試
      </div>`;
  }
}

/* ============================================================
   AniList API
   https://anilist.co/graphiql
   ============================================================ */
async function fetchAniList(period) {
  /* AniList 排序：SCORE_DESC 為全時期高分，沒有真正的「本週」概念
     period = weekly/monthly → 改用 TRENDING_DESC
     period = yearly/all    → 用 SCORE_DESC              */
  const sortBy = (period === 'daily' || period === 'weekly' || period === 'monthly')
    ? 'TRENDING_DESC'
    : 'SCORE_DESC';

  const query = `
    query ($sort: [MediaSort], $perPage: Int) {
      Page(perPage: $perPage) {
        media(type: ANIME, sort: $sort, status_in: [RELEASING, FINISHED]) {
          id
          title { romaji native userPreferred }
          coverImage { large }
          averageScore
          popularity
          trending
          genres
          seasonYear
          season
          status
          rankings { rank type allTime season }
        }
      }
    }`;

  const res = await fetch('https://graphql.anilist.co', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ query, variables: { sort: [sortBy], perPage: 20 } })
  });

  if (!res.ok) throw new Error(`AniList HTTP ${res.status}`);
  const json = await res.json();
  const mediaList = json?.data?.Page?.media || [];

  const seasonMap = { WINTER:'冬季', SPRING:'春季', SUMMER:'夏季', FALL:'秋季' };

  return mediaList.map((m, i) => ({
    rank: i + 1,
    titleZh: m.title.userPreferred || m.title.romaji,
    titleJp: m.title.native || '',
    cover: m.coverImage?.large || '',
    score: m.averageScore ? (m.averageScore / 10).toFixed(1) : null,
    scoredBy: m.popularity || 0,
    genres: (m.genres || []).slice(0, 3),
    year: m.seasonYear
      ? (m.season ? `${m.seasonYear} ${seasonMap[m.season] || ''}` : String(m.seasonYear))
      : '',
    anilistId: m.id,
    url: `https://anilist.co/anime/${m.id}`
  }));
}

/* ============================================================
   MAL / Jikan v4 API
   https://jikan.moe
   ============================================================ */
async function fetchMAL(period) {
  /* Jikan v4 端點：
     weekly/daily  → /top/anime?filter=airing
     monthly       → /top/anime?filter=bypopularity
     yearly/other  → /top/anime                     */
  let endpoint = 'https://api.jikan.moe/v4/top/anime?limit=20';
  if (period === 'daily' || period === 'weekly') {
    endpoint += '&filter=airing';
  } else if (period === 'monthly') {
    endpoint += '&filter=bypopularity';
  }

  const res = await fetch(endpoint);
  if (!res.ok) throw new Error(`Jikan HTTP ${res.status}`);
  const json = await res.json();
  const list = json?.data || [];

  return list.map((m, i) => ({
    rank: i + 1,
    titleZh: m.title || '',
    titleJp: m.title_japanese || '',
    cover: m.images?.jpg?.large_image_url || m.images?.jpg?.image_url || '',
    score: m.score ? Number(m.score).toFixed(1) : null,
    scoredBy: m.scored_by || 0,
    genres: (m.genres || []).slice(0, 3).map(g => g.name),
    year: m.year ? String(m.year) : '',
    anilistId: null,
    url: m.url || `https://myanimelist.net/anime/${m.mal_id}`
  }));
}

/* ============================================================
   Bangumi API
   https://bangumi.github.io/api/
   ============================================================ */
async function fetchBangumi(period) {
  /* Bangumi /v0/subjects 可依 rank 排序，type=2 = 動畫 */
  const res = await fetch(
    'https://api.bgm.tv/v0/subjects?type=2&sort=rank&limit=20',
    { headers: { 'Accept': 'application/json', 'User-Agent': 'weixiaoacg/1.0' } }
  );
  if (!res.ok) throw new Error(`Bangumi HTTP ${res.status}`);
  const json = await res.json();
  const list = json?.data || [];

  return list.map((m, i) => ({
    rank: i + 1,
    titleZh: m.name_cn || m.name || '',
    titleJp: m.name || '',
    cover: m.images?.large || m.images?.common || '',
    score: m.rating?.score ? Number(m.rating.score).toFixed(1) : null,
    scoredBy: m.rating?.total || 0,
    genres: (m.tags || []).slice(0, 3).map(t => t.name),
    year: m.date ? m.date.slice(0, 4) : '',
    anilistId: null,
    url: `https://bgm.tv/subject/${m.id}`
  }));
}

/* ============================================================
   渲染列表
   ============================================================ */
function rankRenderList(items) {
  const listEl = document.getElementById('rank-list');
  if (!listEl) return;

  const { platform, period } = rankState;
  const p = PLATFORMS[platform];
  const color = p?.color || '#63a8ff';

  if (!items || !items.length) {
    listEl.innerHTML = `<div style="text-align:center;padding:48px 0;color:var(--text-muted);">
      <i class="fa-solid fa-box-open" style="font-size:28px;display:block;margin-bottom:12px;"></i>
      此條件暫無資料</div>`;
    return;
  }

  listEl.innerHTML = items.map(item => {
    const numClass = item.rank === 1 ? 'rank-card__num--top1'
                   : item.rank === 2 ? 'rank-card__num--top2'
                   : item.rank === 3 ? 'rank-card__num--top3' : '';

    const crown = item.rank === 1 ? '<span class="rank-card__crown">👑</span>' : '';

    const scoreHtml = item.score
      ? `<div class="rank-card__score" style="color:${color};">${item.score}</div>
         <div class="rank-card__score-label">/ 10</div>`
      : '';

    const votesHtml = item.scoredBy
      ? `<div class="rank-card__votes">${Number(item.scoredBy).toLocaleString()} 人</div>`
      : '';

    const genres = (item.genres || []).map(g =>
      `<span class="rank-card__tag">${g}</span>`).join('');

    const yearTag = item.year
      ? `<span class="rank-card__tag rank-card__tag--year">${item.year}</span>`
      : '';

    const href = item.url || '#';

    return `
    <a class="rank-card" href="${href}" target="_blank" rel="noopener noreferrer">
      <div class="rank-card__rank">
        ${crown}
        <div class="rank-card__num ${numClass}">${item.rank}</div>
      </div>
      <div class="rank-card__cover">
        ${item.cover
          ? `<img src="${item.cover}" alt="${item.titleZh}" loading="lazy"
               onerror="this.parentElement.innerHTML='<div class=rank-card__cover-fb>🎬</div>';">`
          : `<div class="rank-card__cover-fb">🎬</div>`}
      </div>
      <div class="rank-card__body">
        <div class="rank-card__title">${item.titleZh}</div>
        ${item.titleJp && item.titleJp !== item.titleZh
          ? `<div class="rank-card__native">${item.titleJp}</div>` : ''}
        <div class="rank-card__tags">${genres}${yearTag}</div>
      </div>
      <div class="rank-card__meta">
        ${scoreHtml}
        ${votesHtml}
        <div class="rank-card__action" style="color:${color};border-color:${color}44;">
          詳情 →
        </div>
      </div>
    </a>`;
  }).join('');

  /* 更新計數列 */
  const countEl = document.getElementById('rank-count-info');
  if (countEl) {
    const periodLabels = { daily:'今日', weekly:'本週', monthly:'本月', yearly:'年度' };
    countEl.textContent = `${periodLabels[period] || '本週'} ${p?.label || ''} 排行 · Top ${items.length}`;
  }
}

/* ============================================================
   SIDEBAR — 從目前載入的真實資料取前幾筆
   ============================================================ */
function rankInitSidebar() {
  const newEl = document.getElementById('sidebar-new-list');
  if (newEl) {
    newEl.innerHTML = `
      <div style="text-align:center;padding:20px 0;color:var(--text-muted,rgba(208,215,224,.55));font-size:12px;line-height:1.8;">
        <i class="fa-solid fa-clock" style="font-size:20px;display:block;margin-bottom:8px;opacity:.5;"></i>
        評分系統開發中<br>敬請期待
      </div>`;
  }

  const moverEl = document.getElementById('sidebar-movers-list');
  if (moverEl) {
    moverEl.innerHTML = `
      <div style="text-align:center;padding:20px 0;color:var(--text-muted,rgba(208,215,224,.55));font-size:12px;line-height:1.8;">
        <i class="fa-solid fa-chart-line" style="font-size:20px;display:block;margin-bottom:8px;opacity:.5;"></i>
        站內排名數據<br>即將上線
      </div>`;
  }
}


/* ============================================================
   PARTICLES
   ============================================================ */
function rankInitParticles() {
  const canvas = document.getElementById('rank-particles');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W = canvas.width = canvas.offsetWidth;
  let H = canvas.height = canvas.offsetHeight;

  const dots = Array.from({ length: 60 }, () => ({
    x: Math.random() * W, y: Math.random() * H,
    r: Math.random() * 1.5 + 0.4,
    vx: (Math.random() - 0.5) * 0.3,
    vy: (Math.random() - 0.5) * 0.3,
    a: Math.random()
  }));

  (function draw() {
    ctx.clearRect(0, 0, W, H);
    dots.forEach(d => {
      ctx.beginPath();
      ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(180,160,255,${d.a * 0.6})`;
      ctx.fill();
      d.x += d.vx; d.y += d.vy;
      if (d.x < 0) d.x = W; if (d.x > W) d.x = 0;
      if (d.y < 0) d.y = H; if (d.y > H) d.y = 0;
    });
    requestAnimationFrame(draw);
  })();

  window.addEventListener('resize', () => {
    W = canvas.width = canvas.offsetWidth;
    H = canvas.height = canvas.offsetHeight;
  });
}

/* ============================================================
   SWIPE（手機左右滑動切換平台）
   ============================================================ */
function rankInitSwipe() {
  const el = document.querySelector('.rank-main');
  if (!el) return;
  let startX = 0, startY = 0;

  el.addEventListener('touchstart', e => {
    startX = e.touches[0].clientX;
    startY = e.touches[0].clientY;
  }, { passive: true });

  el.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - startX;
    const dy = e.changedTouches[0].clientY - startY;
    if (Math.abs(dx) < 60 || Math.abs(dy) > Math.abs(dx) * 0.8) return;

    const tabs = Array.from(document.querySelectorAll('.rank-platform-tab:not([style*="none"])'));
    const cur = tabs.findIndex(t => t.classList.contains('active'));
    if (cur === -1) return;
    const next = dx < 0 ? Math.min(cur + 1, tabs.length - 1) : Math.max(cur - 1, 0);
    if (next !== cur) {
      tabs[next].click();
      tabs[next].scrollIntoView({ inline: 'center', behavior: 'smooth' });
    }
  }, { passive: true });
}

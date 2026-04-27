/* ============================================================
   微笑動漫 — Main Page Script
   資料來源：
     AniList  → 熱門作品、歷年神作、即將開播
     Bangumi  → 本季新番週曆（/calendar）
   ============================================================ */

'use strict';

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener('DOMContentLoaded', async () => {
  // 先初始化 OpenCC，確保簡繁轉換可用
  await OpenCCHelper.init();

  initSearch();
  initTabs();
  loadHeroPosters();
  loadSeasonSection();       // 本季新番導航（/calendar，按星期分組）
  loadAnimeGrid('trending'); // 熱門作品區
  initPollClick();
  initChartTabs();
  initWikiCats();
});


/* ============================================================
   SEARCH
   ============================================================ */
function initSearch() {
  const toggle  = document.getElementById('search-toggle');
  const overlay = document.getElementById('search-overlay');
  const closeBtn = document.getElementById('search-close');
  const input   = document.getElementById('search-input');

  if (!toggle) return;

  toggle.addEventListener('click', () => {
    overlay.classList.toggle('open');
    if (overlay.classList.contains('open')) setTimeout(() => input?.focus(), 100);
  });
  if (closeBtn) closeBtn.addEventListener('click', () => overlay.classList.remove('open'));

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') overlay?.classList.remove('open');
  });

  if (input) {
    input.addEventListener('keydown', async e => {
      if (e.key !== 'Enter' || !input.value.trim()) return;
      const kw = input.value.trim();
      overlay.classList.remove('open');
      // 搜尋結果：先 toast，未來接 search.html
      showToast(`搜尋「${kw}」中…`, 'info');
      try {
        const results = await AniListAPI.searchMedia(kw, 6);
        if (results.length > 0) {
          showToast(`找到 ${results.length} 部相關作品`, 'success');
          // 導向至第一個結果（AniList ID）
          // window.location.href = `anime.html?id=${results[0].id}`;
        } else {
          showToast('找不到相關作品', 'info');
        }
      } catch {
        showToast('搜尋暫時無法使用', 'info');
      }
    });
  }
}


/* ============================================================
   TABS（熱門作品 tab）
   ============================================================ */
function initTabs() {
  const btns = document.querySelectorAll('.tab-btn:not(.weekday-tab)');
  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      btns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      loadAnimeGrid(btn.dataset.tab);
    });
  });
}

function initChartTabs() {
  document.querySelectorAll('.chart-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
    });
  });
}

function initWikiCats() {
  document.querySelectorAll('.wiki-cat').forEach(cat => {
    cat.addEventListener('click', () => {
      document.querySelectorAll('.wiki-cat').forEach(c => c.classList.remove('active'));
      cat.classList.add('active');
    });
  });
}


/* ============================================================
   HERO POSTERS（使用 AniList 本季熱門作品）
   ============================================================ */
async function loadHeroPosters() {
  const container = document.getElementById('hero-posters');
  if (!container) return;

  try {
    const { season, year } = AniListAPI.getCurrentSeason();
    const animes = await AniListAPI.getSeasonalAnime(season, year, 6);
    const top3 = animes.slice(0, 3);

    // 先嘗試從 Bangumi 補中文名稱（一次全部查，再同步渲染）
    await Promise.allSettled(top3.map(async (a) => {
      const nativeTitle = a.titleNative;
      if (!nativeTitle || a.titleChinese) return;
      try {
        const results = await BangumiAPI.searchByTitle(nativeTitle, 3);
        if (!results.length) return;
        const matched = results.find(r => isTitleMatch(nativeTitle, r.name, r.nameCn));
        if (!matched) return;
        const cnRaw = matched.nameCn || matched.name || '';
        const cn    = OpenCCHelper.convert(cnRaw);
        if (!cn || cn === nativeTitle || !/[\u4e00-\u9fff]/.test(cn)) return;
        a.titleChinese = cn; // 補充中文名
      } catch { /* 單筆失敗繼續用日文 */ }
    }));

    container.innerHTML = top3.map((a, i) => {
      const sizes   = [200, 170, 150];
      const tops    = [0, 40, 80];
      const opacity = i === 2 ? 'opacity:0.7;' : '';
      const score   = AniListAPI.formatAniListScore(a.averageScore);
      // 繁中優先（getDisplayTitle）：titleChinese > title_zh > titleNative > titleRomaji
      const name  = (typeof getDisplayTitle === 'function') ? getDisplayTitle(a)
        : (a.titleChinese || a.titleNative || a.titleRomaji || '未知');
      // 日文副標題（只有中文名與日文不同時才顯示）
      const jaTitle = (typeof getJaTitle === 'function') ? getJaTitle(a) : (a.titleNative || '');
      const showJa  = jaTitle && jaTitle !== name;
      // alt 文字
      const altText = showJa ? `${name}（${jaTitle}）` : name;
      return `
        <div class="poster-item glass" style="width:${sizes[i]}px; margin-top:${tops[i]}px; ${opacity}"
             onclick="goToAnime(${a.id})">
          <img src="${a.coverLarge}" alt="${altText}" loading="lazy"
               onerror="this.style.background='var(--glass-bg-mid)'; this.style.display='block'; this.style.height='${sizes[i]*1.4}px';" />
          <div style="padding:8px 10px 10px; background:linear-gradient(transparent, rgba(0,0,0,0.75));">
            <div class="poster-title" lang="zh-TW">${name}</div>
            ${showJa ? `<div class="poster-title-ja" lang="ja" style="font-size:10px; color:rgba(255,255,255,0.65); margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${jaTitle}</div>` : ''}
            ${score !== '–' ? `<div class="poster-score" style="font-size:11px; color:#FFD580; margin-top:2px;">★ ${score}</div>` : ''}
          </div>
        </div>
      `;
    }).join('');
  } catch (err) {
    console.warn('Hero posters load failed:', err);
  }
}


/* ============================================================
   ANIME GRID（熱門 / 本季 / 即將開播）— AniList
   ============================================================ */
async function loadAnimeGrid(tab = 'trending') {
  const grid = document.getElementById('anime-grid');
  if (!grid) return;

  // Skeleton
  grid.innerHTML = Array(6).fill(
    `<div class="anime-card skeleton glass" style="height:290px; border-radius:20px;"></div>`
  ).join('');

  try {
    let animes = [];
    const { season, year } = AniListAPI.getCurrentSeason();

    if (tab === 'trending') {
      // 大家都在看：本季熱門（popularity 排序）
      animes = await AniListAPI.getSeasonalAnime(season, year, 12);
    } else if (tab === 'top') {
      // 歷年神作（score 排序）
      animes = await AniListAPI.getTopAnime(12);
    } else if (tab === 'upcoming') {
      // 即將開播（下一季）
      const now = new Date();
      const month = now.getMonth() + 1;  // 1-12
      const year  = now.getFullYear();
      // 季節對應：1-3 WINTER, 4-6 SPRING, 7-9 SUMMER, 10-12 FALL
      const SEASONS = ['WINTER','SPRING','SUMMER','FALL'];
      const curSeasonIdx = Math.floor((month - 1) / 3);
      const nextSeasonIdx = (curSeasonIdx + 1) % 4;
      const nextSeason = SEASONS[nextSeasonIdx];
      const nextYear = nextSeasonIdx === 0 ? year + 1 : year;
      animes = await AniListAPI.getSeasonalAnime(nextSeason, nextYear, 12);
    } else {
      animes = await AniListAPI.getSeasonalAnime(season, year, 12);
    }

    const display = animes.slice(0, 6);
    if (display.length === 0) throw new Error('Empty');

    grid.innerHTML = display.map((a, i) => renderAnimeCard(a, i + 1)).join('');

    // 點擊進入作品頁（AniList ID）
    grid.querySelectorAll('.anime-card').forEach(card => {
      card.addEventListener('click', () => {
        const id = card.dataset.id;
        if (id) goToAnime(id);
      });
    });

    // Hover 按鈕
    grid.querySelectorAll('.overlay-btn').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        const card   = btn.closest('.anime-card');
        const id     = card?.dataset.id;
        const action = btn.dataset.action;
        if (action === 'collect') showToast('已加入收藏清單', 'success');
        else if (action === 'detail' && id) goToAnime(id);
      });
    });

    // 非同步補充 Bangumi 中文名稱（逐一查詢，避免衝擊 API）
    enrichCardsWithChineseName(display);

  } catch (err) {
    console.warn('Anime grid load failed:', err);
    grid.innerHTML = `
      <div style="grid-column:1/-1; text-align:center; color:var(--text-muted); padding:40px 0;">
        <i class="fa-solid fa-triangle-exclamation"></i> 資料載入失敗，請稍後重試
      </div>`;
  }
}

function renderAnimeCard(a, rank) {
  // 相容 AniList 與 Bangumi 資料格式
  const isBangumi   = !!a.doing && !a.coverLarge; // Bangumi 有 doing 欄位，AniList 有 coverLarge
  const score       = isBangumi
    ? BangumiAPI.formatScore(a.score)
    : AniListAPI.formatAniListScore(a.averageScore);
  const popularity  = isBangumi
    ? BangumiAPI.formatCount(a.doing)
    : (a.popularity ? (a.popularity >= 10000 ? `${(a.popularity/10000).toFixed(1)}萬` : String(a.popularity)) : null);
  const airDate     = isBangumi ? (a.airDate || '').slice(0, 7) : (a.seasonYear ? `${a.seasonYear}` : '');
  const image       = isBangumi ? a.image : (a.coverLarge || '');

  // 標題邏輯：繁中優先，日文為副標，英文僅 SEO（不顯示在前端卡片上）
  // AniList: 使用 getDisplayTitle()（titleChinese > displayName > titleNative > titleRomaji）
  // Bangumi: displayName 已有中文
  const _getTitle = typeof getDisplayTitle === 'function' ? getDisplayTitle : (x) =>
    (x.titleChinese || x.titleNative || x.titleRomaji || '未知');
  const _getJa    = typeof getJaTitle === 'function' ? getJaTitle : (x) => (x.titleNative || '');
  const displayName = isBangumi
    ? (a.displayName || a.name || '未知')
    : _getTitle(a);
  // 副標：日文原名（若與主標不同才顯示）
  const jaTitle     = _getJa(a);
  const titleSub    = (!isBangumi && jaTitle && jaTitle !== displayName) ? jaTitle : '';

  const statusStr   = isBangumi
    ? (a.doing > 0 ? '追番中' : '已完結')
    : ({'RELEASING':'播出中','FINISHED':'已完結','NOT_YET_RELEASED':'即將播出'}[a.status] || '');
  const statusCls   = (a.status === 'RELEASING' || (isBangumi && a.doing > 0)) ? 'status-airing' : 'status-finished';

  // tags（AniList genres，最多 2 個）
  const tags = (a.genres || []).slice(0, 2).map(t =>
    `<span class="chip tag-genre">${t}</span>`
  ).join('');

  return `
    <div class="anime-card glass" data-id="${a.id}"
         data-native="${(a.titleNative || '').replace(/"/g,'&quot;')}"
         style="cursor:pointer;">
      <div class="anime-card-img-wrap">
        <img class="anime-card-img" src="${image}" alt="${displayName}" loading="lazy"
          onerror="this.style.background='var(--glass-bg-mid)'; this.style.display='block'; this.style.height='200px';" />
        <div class="anime-card-rank">#${rank}</div>
        ${score !== '–' ? `<div class="anime-card-score"><i class="fa-solid fa-star" style="font-size:9px;"></i> ${score}</div>` : ''}
        <div class="anime-card-overlay">
          <div class="overlay-actions">
            <button class="overlay-btn primary" data-action="detail">詳細資訊</button>
            <button class="overlay-btn" data-action="collect">收藏</button>
          </div>
        </div>
      </div>
      <div class="anime-card-body">
        <div class="anime-card-title">${displayName}</div>
        ${titleSub ? `<div class="anime-card-title-ja" lang="ja" style="font-size:11px; color:var(--text-muted); margin-bottom:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${titleSub}</div>` : ''}
        <div class="anime-card-genres">${tags}</div>
        <div class="anime-card-stat">
          ${popularity
            ? `<span class="stat-item ${statusCls}"><i class="fa-solid fa-circle" style="font-size:7px;"></i> ${popularity} 人氣</span>`
            : statusStr ? `<span class="stat-item ${statusCls}"><i class="fa-solid fa-circle" style="font-size:7px;"></i> ${statusStr}</span>` : ''}
          ${airDate ? `<span class="stat-item"><i class="fa-solid fa-calendar"></i> ${airDate}</span>` : ''}
        </div>
      </div>
    </div>
  `;
}

/* 非同步為 AniList 卡片補充 Bangumi 繁體中文名稱
   ⚠️ 搜尋結果必須通過標題相符比對，避免誤補錯誤中文名 */
async function enrichCardsWithChineseName(animes) {
  const grid = document.getElementById('anime-grid');
  if (!grid) return;

  for (const a of animes) {
    const nativeTitle = a.titleNative;
    if (!nativeTitle) continue;

    try {
      // 搜尋 3 筆，從中找最接近的
      const results = await BangumiAPI.searchByTitle(nativeTitle, 3);
      if (!results.length) continue;

      // 嚴格比對：只取標題相符的第一筆
      const matched = results.find(r => isTitleMatch(nativeTitle, r.name, r.nameCn));
      if (!matched) continue;

      const cnRaw = matched.nameCn || matched.name || '';
      const cn    = OpenCCHelper.convert(cnRaw);
      // 需是中文（含中文字元），且不等於日文原名
      if (!cn || cn === nativeTitle || !/[\u4e00-\u9fff]/.test(cn)) continue;

      // 找到對應卡片（data-id）並更新標題
      const card = grid.querySelector(`.anime-card[data-id="${a.id}"]`);
      if (!card) continue;
      const titleEl = card.querySelector('.anime-card-title');
      if (titleEl) titleEl.textContent = cn;

      // 把日文原名搬到副標
      let subEl = card.querySelector('.anime-card-title-ja, .anime-card-title-en');
      if (!subEl) {
        subEl = document.createElement('div');
        subEl.className = 'anime-card-title-ja';
        subEl.setAttribute('lang', 'ja');
        subEl.style.cssText = 'font-size:11px;color:var(--text-muted);margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
        titleEl?.insertAdjacentElement('afterend', subEl);
      }
      subEl.textContent = nativeTitle;
    } catch { /* 單筆失敗不影響其他 */ }
  }
}


/* ============================================================
   本季新番導航 — Season Section（Bangumi /calendar）
   ============================================================ */

// 全域暫存 calendar 資料（按 weekday id 分組）
let _calendarData = {}; // { 1: [...], 2: [...], ... , 0: [...all] }

async function loadSeasonSection() {
  const cardsEl = document.getElementById('season-cards');
  if (!cardsEl) return;

  // skeleton
  cardsEl.innerHTML = Array(8).fill(`
    <div class="season-card skeleton" style="min-width:148px; height:260px; border-radius:16px;"></div>
  `).join('');

  try {
    const calendar = await BangumiAPI.getCalendar();

    if (!calendar || calendar.length === 0) throw new Error('No calendar data');

    // 整理成 { weekdayId: items[] }
    const all = [];
    _calendarData = {};
    for (const group of calendar) {
      _calendarData[group.weekday] = group.items;
      all.push(...group.items);
    }
    // 全部 tab
    _calendarData[0] = all.sort((a, b) => b.doing - a.doing);

    // 預設選今天
    const todayId = getTodayWeekdayId();
    setActiveWeekdayTab(todayId);

    // 渲染
    const todayItems = _calendarData[todayId] || _calendarData[0] || [];
    renderSeasonCards(todayItems.length > 0 ? todayItems : _calendarData[0]);

    // 初始化 tab 事件
    initWeekdayTabs();

  } catch (err) {
    console.warn('Season section load failed:', err);
    cardsEl.innerHTML = `
      <div style="color:var(--text-muted); font-size:13px; padding:24px; white-space:nowrap;">
        <i class="fa-solid fa-triangle-exclamation"></i> 資料載入失敗，請稍後重試
      </div>`;
  }
}

function initWeekdayTabs() {
  const tabs = document.querySelectorAll('.weekday-tab');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      const dayId = parseInt(tab.dataset.day) || 0; // 0=全部, 1-7=週一到週日
      const items = _calendarData[dayId] || _calendarData[0] || [];
      renderSeasonCards(items);
    });
  });
}

function renderSeasonCards(data) {
  const cardsEl = document.getElementById('season-cards');
  if (!cardsEl) return;

  if (!data || data.length === 0) {
    cardsEl.innerHTML = `
      <div style="color:var(--text-muted); font-size:13px; padding:24px 12px; white-space:nowrap;">
        <i class="fa-regular fa-calendar-xmark"></i>&nbsp; 本日無播出作品
      </div>`;
    return;
  }

  cardsEl.innerHTML = data.slice(0, 40).map(a => {
    const score  = BangumiAPI.formatScore(a.score);
    const doing  = BangumiAPI.formatCount(a.doing);
    const dayZh  = BangumiAPI.WEEKDAY_ZH[a.airWeekday] || '';
    const airing = a.doing > 0;

    // 優先顯示繁體中文名稱（OpenCC 轉換 name_cn），若無則顯示日文原名
    const nameCnRaw = a.nameCn || a.displayName || '';
    const nameCn    = OpenCCHelper.convert(nameCnRaw) || '';
    const nameJp    = a.name || '';
    // title：有中文名就顯示中文，否則顯示日文
    const title   = nameCn || nameJp || '未知作品';
    // 副標：若中文名與日文名不同，則顯示日文作為副標
    const titleJp = (nameCn && nameJp && nameCn !== nameJp) ? nameJp : '';

    return `
      <a class="season-card" href="anime.html?bgm=${a.id}" title="${title}${titleJp ? '\n' + titleJp : ''}">
        ${dayZh ? `<span class="season-card-day-badge">${dayZh}</span>` : ''}
        ${airing ? `<span class="season-card-airing"></span>` : ''}
        ${a.image
          ? `<img class="season-card-img" src="${a.image}" alt="${title}" loading="lazy"
               onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
             <div class="season-card-img-ph" style="display:none;">🎬</div>`
          : `<div class="season-card-img-ph">🎬</div>`
        }
        <div class="season-card-body">
          <div class="season-card-title">${title}</div>
          ${titleJp ? `<div class="season-card-jp">${titleJp}</div>` : ''}
          <div class="season-card-meta">
            ${score !== '–' ? `<span class="season-card-score">★ ${score}</span>` : ''}
            ${doing !== '–' ? `<span class="season-card-type">${doing}追</span>` : ''}
          </div>
        </div>
      </a>`;
  }).join('');
}

/* 取得今天對應的 Bangumi weekday id（1=週一 … 7=週日）*/
function getTodayWeekdayId() {
  // JS getDay()：0=Sun, 1=Mon … 6=Sat
  // Bangumi：1=Mon … 7=Sun
  const jsDay = new Date().getDay();
  return jsDay === 0 ? 7 : jsDay;
}

/* 設定 active tab（data-day 對應 Bangumi weekday id，0=全部）*/
function setActiveWeekdayTab(dayId) {
  document.querySelectorAll('.weekday-tab').forEach(t => {
    t.classList.toggle('active', parseInt(t.dataset.day) === dayId);
  });
}


/* ============================================================
   NAVIGATION
   ============================================================ */
function goToAnime(id) {
  window.location.href = `anime.html?id=${id}`;
}


/* ============================================================
   POLLS
   ============================================================ */
function initPollClick() {
  document.querySelectorAll('.poll-option').forEach(opt => {
    opt.addEventListener('click', () => showToast('感謝你的投票！', 'success'));
  });
}


/* ============================================================
   TOAST
   ============================================================ */
function showToast(msg, type = 'info') {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const icons = { success: 'fa-circle-check', info: 'fa-circle-info', error: 'fa-circle-xmark' };
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<i class="fa-solid ${icons[type] || icons.info}"></i> ${msg}`;
  container.appendChild(toast);

  requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 400);
  }, 3000);
}

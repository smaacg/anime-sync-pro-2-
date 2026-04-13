# Anime Sync Pro – CONTEXT.md v10.0 (2026‑04‑13)

## 專案基本資訊

- 外掛名稱：Anime Sync Pro
- Text‑domain：anime-sync-pro
- GitHub：https://github.com/smaacg/anime-sync-pro-2-
- PHP 要求：≥ 8.0　WP 要求：≥ 6.0
- 主檔案：anime-sync-pro.php
- 主機：Hostinger Business Web Hosting
- SSH：`ssh u393305917@srv2088.hstgr.io -p 65002`
- 插件路徑：`/home/u393305917/domains/dev.weixiaoacg.com/public_html/wp-content/plugins/anime-sync-pro`
- 測試站：https://dev.weixiaoacg.com
- 主題：Hello Elementor（Header/Footer 尚未製作，之後用 Elementor 完成）
- 常數：ANIME_SYNC_PRO_VERSION、ANIME_SYNC_PRO_DIR、ANIME_SYNC_PRO_URL、ANIME_SYNC_PRO_BASENAME
- 自動載入規則：前綴 Anime_Sync_ → includes/class-{slug}.php

---

## 目錄審計（v10.0）

| 路徑 | 狀態 | 備注 |
|------|------|------|
| anime-sync-pro.php | ✅ | 主引導 |
| includes/class-api-handler.php | ⚠️ 待修 | 見 Bug AX–ABB |
| includes/class-id-mapper.php | ⚠️ 待修 | 見 Bug ABC–ABD |
| includes/class-import-manager.php | ⚠️ 待修 | 見 Bug ABE–ABG |
| includes/class-acf-fields.php | ⚠️ 待修 | 見功能 F1–F4 |
| includes/class-cn-converter.php | ✅ | OpenCC S2TWP，含 get_stats()、convert()、loaded key |
| includes/class-cron-manager.php | ✅ | |
| includes/class-installer.php | ✅ | |
| includes/class-rate-limiter.php | ✅ | |
| includes/class-error-logger.php | ✅ | |
| includes/class-performance.php | ✅ | |
| includes/class-image-handler.php | ✅ | |
| includes/class-security.php | ✅ | |
| includes/class-review-queue.php | ✅ | |
| includes/class-custom-post-type.php | ✅ | |
| includes/class-frontend.php | ✅ | |
| admin/class-admin.php | ✅ | |
| admin/pages/settings.php | ✅ | |
| public/templates/single-anime.php | ⚠️ 待修 | 全面對齊骨架 |
| public/assets/css/anime-single.css | ⚠️ 待修 | 全面重寫 |
| data/cn-tw-dict.json | ✅ | |
| data/anime_map.json | ✅ | 23,206 筆 |
| composer.json | ✅ | overtrue/php-opencc ^1.3 |
| vendor/ | ✅ | OpenCC 已安裝 |

---

## Custom Post Type

- Slug：anime　REST：啟用　Rewrite slug：anime

---

## Taxonomies

| Taxonomy | 結構 | 範例 |
|----------|------|------|
| genre | 平面，28 個 slug | action、romance、suspense … |
| anime_season_tax | 階層：parent = 年份；child = {year}-{season} | 2026 → 2026-spring |
| anime_format_tax | 平面 | format-tv、format-movie … |

- 季節順序：winter → spring → summer → fall
- 格式對應：TV → format-tv、TV_SHORT → format-tv-short、MOVIE → format-movie、OVA → format-ova、ONA → format-ona、SPECIAL → format-special、MUSIC → format-music

---

## Meta Key 主清單

| Meta Key | 說明 |
|----------|------|
| anime_anilist_id | AniList ID |
| anime_mal_id | MAL ID（可為 null） |
| anime_bangumi_id | Bangumi subject ID |
| anime_title_chinese | 繁體中文標題（經 CN_Converter） |
| anime_title_romaji | Romaji 標題 |
| anime_title_english | 英文標題 |
| anime_title_native | 日文原題 |
| anime_format | 格式（TV / MOVIE…） |
| anime_status | 狀態（FINISHED / RELEASING…） |
| anime_season | 季節，大寫儲存（WINTER / SPRING / SUMMER / FALL） |
| anime_season_year | 播出年份 |
| anime_episodes | 集數 |
| anime_duration | 每集分鐘數 |
| anime_source | 原作類型 |
| anime_studios | 製作公司（逗號分隔字串） |
| anime_score_anilist | AniList 評分（原始 0–100 整數） |
| anime_score_mal | MAL 評分（0–10 float，直接儲存） |
| anime_score_bangumi | Bangumi 評分（×10 整數，前台除以 10 顯示） |
| anime_popularity | AniList 人氣值 |
| anime_cover_image | 封面圖 URL |
| anime_banner_image | 橫幅圖 URL |
| anime_trailer_url | YouTube 預告 URL |
| anime_synopsis_chinese | 中文簡介（繁體，來自 Bangumi） |
| anime_synopsis_english | 英文簡介（來自 AniList，不在後台顯示） |
| anime_start_date | 開播日期（Y-m-d） |
| anime_end_date | 完結日期（Y-m-d） |
| anime_streaming | 串流平台（JSON） |
| anime_themes | OP/ED 主題曲（JSON） |
| anime_staff_json | 製作人員（JSON） |
| anime_cast_json | 聲優角色（JSON） |
| anime_relations_json | 關聯作品（JSON） |
| anime_external_links | 外部連結（JSON） |
| anime_next_airing | 下集播出資訊（JSON） |
| anime_last_sync | 最後同步時間（mysql datetime） |
| anime_animethemes_id | AnimeThemes slug（有值時才寫入） |
| anime_wikipedia_url | Wikipedia 繁中頁面 URL（新增） |
| anime_official_site | 官方網站 URL |
| anime_twitter_url | Twitter / X URL |
| anime_tiktok_url | TikTok URL（手動填寫） |
| anime_tw_streaming | 台灣串流平台（JSON 陣列，checkbox 多選） |
| anime_tw_distributor | 台灣代理商（select 選單） |
| anime_tw_distributor_custom | 台灣代理商自訂（選「其他」時填寫） |
| anime_tw_broadcast | 台灣播出頻道與時間 |
| anime_episodes_json | 集數列表（JSON，來自 Bangumi episodes API） |
| anime_locked_fields | 鎖定欄位陣列（不自動覆蓋） |
| _bangumi_id_pending | 人工補齊旗標（六層查詢失敗時設為 1） |

---

## 分數儲存／顯示規則

| 來源 | 儲存格式 | 前台顯示 | 後台 ACF |
|------|----------|----------|----------|
| AniList averageScore | 原始 0–100 整數 | ÷10，1 位小數 | raw / 100 |
| MAL score | 0–10 float 直接儲存 | 直接顯示，1 位小數 | raw / 10 |
| Bangumi score | ×10 整數（如 7.4 → 74） | ÷10，1 位小數 | raw / 100 |

---

## 首次匯入預設鎖定欄位

首次匯入完成後，`import_single()` 自動寫入以下鎖定欄位（`anime_locked_fields`）：
- `anime_cover_image`
- `anime_banner_image`
- `anime_trailer_url`
- `anime_synopsis_chinese`

後續同步時這 4 個欄位不會被自動覆蓋。手動後台可增減鎖定欄位。

---

## 台灣串流平台清單（anime_tw_streaming，checkbox 多選）

巴哈姆特動畫瘋、Netflix、Disney+、Crunchyroll、Amazon Prime Video、
bilibili 台灣、friDay影音、MyVideo、Hami Video、LiTV 線上影視、
CATCHPLAY+、AniPass、ANIPLUS、LINE TV、ofiii、YouTube（官方頻道）

---

## 台灣代理商清單（anime_tw_distributor，select 下拉）

木棉花（Muse）、曼迪傳播、羚邦、普威爾、台灣角川、
東立出版社、尖端出版、利工民（青文）、博英社、其他（手動填寫）

---

## AniList GraphQL Query（最終版）

```graphql
query ($id: Int) {
  Media(id: $id, type: ANIME) {
    id
    idMal
    title { romaji english native }
    status
    format
    episodes
    duration
    source
    season
    seasonYear
    startDate { year month day }
    endDate   { year month day }
    averageScore
    popularity
    coverImage { extraLarge large }
    bannerImage
    trailer { id site }
    description(asHtml: false)
    genres
    tags { name isMediaSpoiler }
    studios(isMain: true) { nodes { name } }
    externalLinks { url site type }
    nextAiringEpisode { airingAt episode }
    relations {
      edges {
        relationType
        node { id title { romaji native } format }
      }
    }
    staff(perPage: 10) {
      edges {
        role
        node { name { full native } }
      }
    }
    characters(perPage: 10) {
      edges {
        role
        node { name { full native } }
        voiceActors(language: JAPANESE) { name { full native } }
      }
    }
  }
}
Copy
注意：externalLinks 加入 type 欄位供串流平台判斷。

Bangumi Episodes API
端點：https://api.bgm.tv/v0/episodes?subject_id={bgm_id}&type=0
儲存 key：anime_episodes_json
格式：[{"ep": 1, "name": "...", "name_cn": "...", "airdate": "2025-01-04"}, ...]
前台：顯示前 3 集，「顯示更多」展開全部
Bangumi 無資料時：顯示純集數編號（第 1 集、第 2 集…）
AnimeThemes API
端點：https://api.animethemes.moe/anime
Bug AX 修正：不使用 add_query_arg()，改用字串直接組 URL 避免 [] 被 encode
正確 URL 格式：
Copyhttps://api.animethemes.moe/anime?filter[has]=resources&filter[site]=MyAnimeList&filter[external_id]={mal_id}&include=animethemes.song,resources&page[number]=1
fetch_animethemes() 同時回傳 themes 陣列與 slug 字串
slug 存入 anime_animethemes_id
串流平台判斷白名單（parse_streaming_links）
判斷邏輯：type === "STREAMING" 或 site 名稱在白名單內：

Copycrunchyroll, funimation, netflix, amazon, hidive, hulu,
disney plus, disney+, bilibili, youtube, wakanim,
ani-one, aniplus, iqiyi, medialink
Wikipedia URL 抓取（fetch_wikipedia_url）
使用 zh.wikipedia.org API 搜尋繁體中文標題
端點：https://zh.wikipedia.org/w/api.php?action=query&list=search&srsearch={title}&format=json
快取：WordPress transient，30 天
儲存 key：anime_wikipedia_url
匯入完成摘要（方案 B）
匯入完成後回傳結構化摘要，顯示於後台匯入工具頁面：

Copy✅ 匯入完成：{標題}
├ AniList：✅
├ Bangumi：✅ ID: 487630 / ❌ 未找到（請手動填入 BGM ID）
├ 主題曲：✅ OP×1 ED×2 / ❌ 未找到
├ 封面圖：✅ / ❌
├ 串流平台：✅ 2 個 / ❌ 未找到
├ Wikipedia：✅ / ❌
└ 集數列表：✅ 13 集 / ❌
重新同步 Bangumi 按鈕
位置：後台草稿編輯頁「同步控制」側邊欄

流程：

手動在 anime_bangumi_id 欄位填入正確 BGM ID
點擊「重新同步 Bangumi」按鈕
AJAX 呼叫，帶 post_id
重新呼叫 get_bangumi_data()、get_bgm_staff()、get_bgm_chars()、fetch_bgm_episodes()
更新：中文標題、簡介、評分、Staff、Cast、集數列表
鎖定欄位照常跳過（is_field_locked() 檢查）
顯示成功或錯誤訊息
前台骨架設計（single-anime.php）
Copy================================================================================
[ Header（get_header()，Elementor 完成後自動套入）]
================================================================================

【麵包屑導航】首頁 > 動畫列表 > 作品名稱

【Hero 區】
[ 封面圖 ]   作品名稱（中文）
             作品名稱（日文）
             作品名稱（Romaji）
             ⭐ AniList / MAL / Bangumi 評分
             👥 人氣
             ▶ 預告片播放按鈕

【快速導覽（錨點跳轉，SEO 友善）】
[ 基本資訊 ] [ 劇情介紹 ] [ 集數列表 ] [ CAST ] [ STAFF ]
[ 主題曲 ] [ 線上看 ] [ 台灣播出 ] [ 相關新聞 ] [ 關聯作品 ]

【兩欄佈局：左 70% 主內容 + 右 30% 側邊欄】

左側主內容：
  📋 基本資訊（類型、集數、狀態、季度、時長、開播、下一集、原作、製作公司）
  📝 劇情簡介
  📅 集數列表（前 3 集 + 顯示更多）
  🎭 角色 & 聲優
  🎬 製作人員 STAFF
  🎵 主題曲 OP / ED
  📺 串流平台（含聯盟行銷入口，暫跳過）
  🗺️ 台灣播出資訊
  🔗 外部連結

右側側邊欄：
  📰 相關新聞
  🎬 關聯作品
  🔥 熱門推薦

【底部】
  H2：你可能也會喜歡（同類型推薦卡片）
  H2：常見問題 FAQ（自動產生，FAQPage Schema）
  Schema：TVSeries / AggregateRating / FAQPage / BreadcrumbList

[ Footer（get_footer()，Elementor 完成後自動套入）]
================================================================================
CSS 設計原則（anime-single.css）
所有樣式限定在 .asd-wrap 內，避免污染 Hello Elementor 全域
相容性處理：
Copy.asd-wrap * { box-sizing: border-box; }
.asd-wrap a { color: inherit; text-decoration: none; }
body.single-anime .site-content,
body.single-anime .content-area,
body.single-anime #content {
    padding: 0 !important;
    margin: 0 !important;
    max-width: none !important;
}
主題色：深色系（#0d1117 背景）
兩欄：grid-template-columns: 1fr 300px
RWD：900px 以下單欄，600px 以下調整字體與間距
快速導覽：sticky，滾動時固定在頂部
待輸出檔案清單（v10.0）
#	檔案	修改內容	Bug ID
1	includes/class-api-handler.php	① AnimeThemes URL 修正；② clean_synopsis() 截斷 [简介原文]；③ fetch_wikipedia_url()；④ 串流白名單加 type 判斷；⑤ fetch_animethemes() 回傳 slug；⑥ 新增 fetch_bgm_episodes()	AX、AY、AZ、ABA
2	includes/class-import-manager.php	① 存 anime_wikipedia_url；② 首次匯入鎖定 4 個欄位；③ 匯入完成摘要（方案 B）；④ 存 anime_episodes_json	ABE、ABF、ABG
3	includes/class-acf-fields.php	① 移除預告片預覽 JS 及 hook；② 台灣串流 checkbox；③ 台灣代理商 select + 自訂；④ 重新同步 Bangumi 按鈕；⑤ anime_episodes_json 欄位	F1–F5
4	includes/class-id-mapper.php	① match_best_result() 年份精確度；② 保留季數二次搜尋	ABC、ABD
5	public/templates/single-anime.php	全面對齊骨架：兩欄佈局、錨點導覽、集數列表、台灣播出、FAQ、關聯作品移右側欄	—
6	public/assets/css/anime-single.css	全面重寫，對齊骨架，相容 Hello Elementor	—
已知 Bug 完整歷史
Bug ID	檔案	說明	版本
A–Z	各檔案	早期 bug，詳見 v6.0	v6.0
AA	class-id-mapper.php	gmdate() 取代 date()	v9.0
AB	class-id-mapper.php	HTTP status code 驗證	v9.0
AJ	class-api-handler.php	externalLinks 傳入 mapper	v9.0
AK	class-api-handler.php	\r\n 清除	v9.0
AL	class-api-handler.php	name_cn 繁化	v9.0
AO	class-id-mapper.php	標題正規化 Layer 3 修正	v9.0
AR	class-api-handler.php	null idMal 安全處理	v9.0
AS	class-security.php	sanitize_year() gmdate()	v9.0
AT	class-import-manager.php	補寫 anime_animethemes_id	v9.0
AV	admin/pages/settings.php	改讀 anime_map_meta.json	v9.0
AW	class-import-manager.php / class-custom-post-type.php	anime_season 大小寫	v9.0
AX	class-api-handler.php	AnimeThemes URL [] encode 問題	v10.0
AY	class-api-handler.php	clean_synopsis() 未截斷 [简介原文]	v10.0
AZ	class-api-handler.php	fetch_wikipedia_url() 尚未實作	v10.0
ABA	class-api-handler.php	串流平台少抓，未判斷 type	v10.0
ABB	class-id-mapper.php	match_best_result() 年份精確度不足	v10.0
ABC	class-id-mapper.php	季數二次搜尋缺失	v10.0
ABE	class-import-manager.php	未存 anime_wikipedia_url	v10.0
ABF	class-import-manager.php	首次匯入未自動鎖定 4 個欄位	v10.0
ABG	class-import-manager.php	未存 anime_episodes_json	v10.0
OpenCC 安裝資訊
套件：overtrue/php-opencc v1.3.1
策略：S2TWP（簡體 → 台灣正體 + 詞彙本地化）
安裝路徑：插件根目錄 vendor/
安裝指令：
Copycd /home/u393305917/domains/dev.weixiaoacg.com/public_html/wp-content/plugins/anime-sync-pro
php composer.phar install --no-dev --optimize-autoloader
驗證指令：
Copyphp -r "require 'vendor/autoload.php'; echo \Overtrue\PHPOpenCC\OpenCC::convert('软件网络服务器', 'S2TWP');"
預期輸出：軟體網路伺服器
下一步
v10.0 為目前最終規劃狀態。確認 CONTEXT.md 無誤後，依序輸出 6 個檔案。 新發現的 Bug 從 ABH 開始編號，並更新至 CONTEXT v11.0。

Copy
確認這份 CONTEXT.md 無誤後，即可開始依序輸出 6 個檔案。

# Anime Sync Pro — CONTEXT.md
> Version : **v11.0**
> Date    : 2026-04-13
> Status  : All known bugs fixed. Next bug ID starts at **ABO**.

---

## 1. 專案基本資訊

| 項目 | 內容 |
|------|------|
| 外掛名稱 | Anime Sync Pro |
| Text Domain | `anime-sync-pro` |
| 版本 | 1.0.2 |
| GitHub | https://github.com/smaacg/anime-sync-pro-2- |
| 測試站 | https://dev.weixiaoacg.com |
| 正式站 | https://weixiaoacg.com（尚未部署） |
| 主機 | Hostinger Business |
| PHP 最低需求 | ≥ 8.0 |
| WordPress 最低需求 | ≥ 6.0 |
| 主檔案 | `anime-sync-pro.php` |
| 外掛路徑 | `wp-content/plugins/anime-sync-pro/` |
| Autoload 規則 | 前綴 `Anime_Sync_` → `includes/class-{slug}.php` |

---

## 2. 必要前置外掛

部署前必須先安裝並啟用以下外掛，順序不可顛倒：

| 順序 | 外掛 | 用途 |
|------|------|------|
| 1 | **Advanced Custom Fields (ACF)** 或 ACF Pro | 所有動畫自訂欄位 |
| 2 | **Custom Post Type UI (CPT UI)** | 註冊 `anime` CPT 及相關 Taxonomy |
| 3 | Anime Sync Pro（本外掛） | 同步 / 匯入動畫資料 |

> ⚠️ 若先啟用 Anime Sync Pro 而未裝 ACF，`acf/init` hook 不會觸發，
> 所有欄位群組不會建立，後台會看不到任何自訂欄位。

---

## 3. 初次安裝完整流程

### Step 1 — 安裝前置外掛

Copy
WordPress 後台 → 外掛 → 安裝外掛

搜尋並安裝「Advanced Custom Fields」→ 啟用
搜尋並安裝「Custom Post Type UI」→ 啟用
Copy
### Step 2 — 上傳 Anime Sync Pro

```bash
# 方法 A：SSH（推薦）
cd /path/to/wp-content/plugins/
git clone https://github.com/smaacg/anime-sync-pro-2- anime-sync-pro

# 方法 B：手動上傳
# 下載 ZIP → WordPress 後台 → 外掛 → 上傳外掛
Step 3 — 安裝 Composer 依賴（OpenCC 簡繁轉換）
Copy# SSH 進入外掛根目錄
cd /path/to/wp-content/plugins/anime-sync-pro/

# 安裝依賴（僅需執行一次）
composer install --no-dev --optimize-autoloader
安裝後目錄內會出現 vendor/ 資料夾， 確認 vendor/overtrue/php-opencc 存在即為成功。

驗證指令：

Copyphp -r "require 'vendor/autoload.php'; echo \Overtrue\PHPOpenCC\OpenCC::convert('软件', 'S2TWP');"
# 預期輸出：軟體
⚠️ 若主機不支援 SSH Composer，可在本機執行後將整個 vendor/ 資料夾上傳至外掛根目錄。 未安裝 OpenCC 時系統會自動 fallback 至靜態字典（data/cn-tw-dict.json）， 功能仍可運作但轉換品質較低。

Step 4 — 啟用外掛
CopyWordPress 後台 → 外掛 → 啟用「Anime Sync Pro」
啟用後系統會自動：

註冊 anime Custom Post Type
註冊 genre、anime_season_tax、anime_format_tax、post_tag Taxonomy
建立 ACF 欄位群組（需 ACF 已啟用）
排程每日自動同步 Cron Job
Step 5 — 執行分類初始化腳本
Copy瀏覽器訪問（需登入 WordPress 管理員）：
https://dev.weixiaoacg.com/wp-content/plugins/anime-sync-pro/setup-taxonomy.php
腳本會建立：

WordPress 文章分類（category）：頂層 13 個 + 子分類共 70+ 個
動畫類型（genre）：27 個
播出季度（anime_season_tax）：2000–2030 年，每年 4 季，共 310 個
動畫格式（anime_format_tax）：7 個
⚠️ 執行完畢後請立即刪除 setup-taxonomy.php， 該檔案沒有 ABSPATH 保護，任何人訪問都可執行。

Step 6 — 下載 Bangumi ID 對照表
CopyWordPress 後台 → Anime Sync → 工具 → 下載/更新 ID 對照表
系統會從 manami-project/anime-offline-database 下載 anime_map.json（約 4 MB）， 並自動建立 mal_index.json 與 name_cache.json。

完成後狀態顯示：

entry_count ≈ 23,000+
mal_count ≈ 12,000+
Step 7 — 測試匯入
CopyWordPress 後台 → Anime Sync → 匯入動畫
輸入 AniList ID（測試建議：177709 坂本日常、176496 我獨自升級 S2）
→ 點擊「開始匯入」
→ 確認匯入摘要全部 ✅
→ 至草稿確認欄位資料
4. 目錄結構
Copyanime-sync-pro/
├── anime-sync-pro.php          ✅ 主檔案，常數定義、autoload、CPT 註冊
├── composer.json               ✅ 依賴：overtrue/php-opencc ^1.3
├── vendor/                     ✅ Composer 產生（需執行 composer install）
│   └── overtrue/php-opencc/
├── setup-taxonomy.php          ✅ 分類初始化腳本（執行後刪除）
├── includes/
│   ├── class-api-handler.php   ✅ v11.0 AX ABA ABL
│   ├── class-id-mapper.php     ✅ v11.0 ABB ABC
│   ├── class-import-manager.php✅ v11.0 ABL ABM ABN
│   ├── class-acf-fields.php    ✅ v11.0 F1–F5
│   ├── class-cn-converter.php  ✅ OpenCC S2TWP + fallback 字典
│   ├── class-image-handler.php ✅
│   ├── class-rate-limiter.php  ✅
│   ├── class-cron-manager.php  ✅
│   ├── class-installer.php     ✅
│   └── class-error-logger.php  ✅
├── admin/
│   ├── class-admin-ui.php      ✅
│   ├── import-tool.php         ✅
│   └── dashboard.php           ✅
├── public/
│   ├── class-frontend.php      ✅
│   ├── templates/
│   │   └── single-anime.php    ✅ v11.0 F-TPL
│   └── assets/
│       └── css/
│           └── anime-single.css✅ v11.0 全部重寫
└── data/
    └── cn-tw-dict.json         ✅ fallback 靜態字典
5. Custom Post Type
項目	值
Slug	anime
REST API	✅ 啟用
支援 Taxonomy	genre、anime_season_tax、anime_format_tax、post_tag
6. Taxonomy 清單
genre（動畫類型）
中文	Slug
動作	action
冒險	adventure
喜劇	comedy
劇情	drama
奇幻	fantasy
恐怖	horror
魔法少女	mahou-shoujo
機甲	mecha
音樂	music-genre
推理	mystery
懸疑	suspense
心理	psychological
科幻	sci-fi
日常	slice-of-life
運動	sports
超自然	supernatural
驚悚	thriller
異世界	isekai
後宮	harem
百合	yuri
耽美	bl
歷史	historical
武俠	wuxia
校園	school
兒童	kids
輕色情	ecchi
戀愛	romance
anime_season_tax（播出季度）
結構：父層 {year}（如 2025）→ 子層 {year}-{season}（如 2025-winter）

季節 slug 順序：winter → spring → summer → fall

anime_format_tax（動畫格式）
中文	Slug
TV	format-tv
TV短篇	format-tv-short
劇場版	format-movie
OVA	format-ova
ONA	format-ona
特別篇	format-special
音樂MV	format-music
7. Meta Key 完整清單
IDs
Key	說明
anime_anilist_id	AniList ID
anime_mal_id	MyAnimeList ID
anime_bangumi_id	Bangumi Subject ID
anime_animethemes_id	AnimeThemes slug（如 sakamoto_days）
bangumi_id	舊 key，相容保留
標題
Key	說明
anime_title_chinese	繁體中文標題（Bangumi → OpenCC S2TWP）
anime_title_zh	同上，舊 key 相容
anime_title_romaji	Romaji 標題（AniList）
anime_title_english	英文標題（AniList）
anime_title_native	日文原名
分類
Key	說明
anime_format	TV / MOVIE / OVA / ONA / SPECIAL / MUSIC / TV_SHORT
anime_type	同上，舊 key 相容
anime_status	FINISHED / RELEASING / NOT_YET_RELEASED / CANCELLED / HIATUS
anime_season	WINTER / SPRING / SUMMER / FALL（大寫儲存）
anime_season_year	播出年份（int）
anime_year	同上，舊 key 相容
anime_episodes	總集數（int）
anime_duration	每集時長分鐘（int）
anime_source	ORIGINAL / MANGA / LIGHT_NOVEL / NOVEL / VISUAL_NOVEL 等
anime_studios	製作公司（逗號分隔字串）
評分
Key	說明	儲存方式	前台顯示
anime_score_anilist	AniList 評分	0–100 int	÷10 → 0.0–10.0
anime_score_mal	MAL 評分	0–10 float	直接顯示
anime_score_bangumi	Bangumi 評分	原始值 ×10 int（如 6.9→69）	÷10 → 0.0–10.0
anime_popularity	AniList 人氣	int	直接顯示
圖片與媒體
Key	說明
anime_cover_image	封面圖 URL（AniList extraLarge）
anime_banner_image	橫幅圖 URL（AniList bannerImage）
anime_trailer_url	YouTube 預告片 URL（可多行）
簡介
Key	說明
anime_synopsis_chinese	繁體中文簡介（Bangumi → OpenCC）
anime_synopsis_zh	同上，舊 key 相容
anime_synopsis_english	英文簡介（AniList，僅寫入不顯示）
日期
Key	說明
anime_start_date	開始播出日期（YYYY-MM-DD）
anime_end_date	結束播出日期（YYYY-MM-DD）
anime_next_airing	下集播出資訊 JSON {"airingAt":unix,"episode":n}
JSON 欄位
Key	說明
anime_streaming	串流平台 JSON [{"site":"Netflix","url":"..."}]
anime_themes	主題曲 JSON [{"type":"OP1","title":"..."}]
anime_staff_json	製作人員 JSON
anime_cast_json	角色聲優 JSON
anime_relations_json	關聯作品 JSON
anime_episodes_json	Bangumi 集數列表 JSON [{"ep":1,"name":"...","name_cn":"...","airdate":"..."}]
anime_external_links	AniList 全部外部連結 raw JSON
外部連結
Key	說明
anime_official_site	官方網站 URL
anime_twitter_url	Twitter / X URL
anime_wikipedia_url	Wikipedia 繁中頁面 URL（自動抓取）
anime_tiktok_url	TikTok URL（手動填寫）
台灣在地資訊
Key	說明
anime_tw_streaming	台灣串流平台（checkbox 陣列，值為 slug）
anime_tw_streaming_other	其他台灣串流平台（手動文字）
anime_tw_distributor	台灣代理商（select slug）
anime_tw_distributor_custom	其他代理商名稱（當 distributor = other）
anime_tw_broadcast	台灣播出頻道與時間（文字）
同步控制
Key	說明
anime_last_sync	最後同步時間（MySQL datetime）
anime_locked_fields	鎖定欄位陣列（meta key 清單）
anime_import_status	最後匯入摘要（文字）
_bangumi_id_pending	Bangumi ID 查找失敗旗標（1）
8. 台灣串流平台 Slug 對照
Slug	顯示名稱
bahamut	巴哈姆特動畫瘋
netflix	Netflix
disney	Disney+
amazon	Amazon Prime Video
kktv	KKTV
friday	friDay 影音
catchplay	CatchPlay+
bilibili	Bilibili 台灣
crunchyroll	Crunchyroll
hulu	Hulu
hidive	HIDIVE
ani-one	Ani-One
muse	Muse 木棉花
viu	Viu
wetv	WeTV
youtube	YouTube（官方頻道）
9. 台灣代理商 Slug 對照
Slug	顯示名稱
muse	木棉花（Muse）
medialink	曼迪傳播（Medialink）
jbf	日本橋文化（JBF）
righttime	正確時間
gaga	GaGa OOLala
catchplay	CatchPlay
netflix	Netflix 台灣
disney	Disney+ 台灣
kktv	KKTV
crunchyroll	Crunchyroll
ani-one	Ani-One Asia
other	其他（填寫自訂欄位）
10. 首次匯入自動鎖定欄位
以下欄位在首次匯入時若有值，會自動加入 anime_locked_fields， 避免後續自動同步覆蓋手動編輯的內容：

anime_cover_image
anime_banner_image
anime_trailer_url
anime_synopsis_chinese
11. API 端點
API	URL
AniList GraphQL	https://graphql.anilist.co
Bangumi Subject	https://api.bgm.tv/v0/subjects/{id}
Bangumi Episodes	https://api.bgm.tv/v0/episodes?subject_id={id}&type=0&limit=200
Bangumi Search	https://api.bgm.tv/v0/search/subjects
AnimeThemes	https://api.animethemes.moe/anime?filter[has]=resources&filter[site]=MyAnimeList&filter[external_id]={mal_id}&include=animethemes.song,resources&page[number]=1
Jikan (MAL)	https://api.jikan.moe/v4/anime/{mal_id}
Wikipedia ZH	https://zh.wikipedia.org/w/api.php
Google Translate	https://translate.googleapis.com/translate_a/single
⚠️ AnimeThemes URL 必須用字串直接組（不可用 add_query_arg()）， 否則 filter[has] 中的方括號會被編碼為 %5B%5D 導致 API 失效（Bug AX）。

12. AniList GraphQL Query
Copyquery ($id: Int) {
  Media(id: $id, type: ANIME) {
    id idMal
    title { romaji english native }
    status format episodes duration source season seasonYear
    startDate { year month day }
    endDate   { year month day }
    averageScore popularity
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
      edges { role node { name { full native } } }
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
13. Bangumi ID 六層查詢邏輯
Layer	方法	命中率
0	WP post meta anime_bangumi_id（已有資料）	100%
1	mal_index.json（MAL ID 直接對照）	~53%
2	AniList externalLinks 中的 bgm.tv URL	高（新番）
3	Bangumi 搜尋 + 日文原名 + 年份 ±1	~20–25%
4	Bangumi 搜尋 + 中文標題 + 年份	~3–5%
5	設定 _bangumi_id_pending 旗標，回傳 null	fallback
ABB 修正（v11.0）：match_best_result() 改為三組候選池策略：

Primary：年份差 ≤1（優先）
Secondary：年份差 2+（次選，容差 ≤2）
No-year：無年份資訊但相似度 ≥80%
ABC 修正（v11.0）：search_bangumi_by_title() 採兩段式搜尋：

第一次：normalize_title()（去季號）搜尋
第二次：原始標題（保留 Season 2 等）重試
14. 評分儲存 / 顯示規則
來源	儲存	前台顯示	ACF 欄位設定
AniList	0–100 int	÷10	max=100, step=1
MAL	0–10 float	直接顯示	max=10, step=0.01
Bangumi	原始值 ×10 存 int（6.9→69）	÷10	max=100, step=1
15. OpenCC 簡繁轉換
項目	說明
套件	overtrue/php-opencc v1.3.1
策略	S2TWP（簡體→台灣正體含詞彙本地化）
Fallback	data/cn-tw-dict.json 靜態字典
安裝指令	composer install --no-dev --optimize-autoloader
驗證指令	php -r "require 'vendor/autoload.php'; echo \Overtrue\PHPOpenCC\OpenCC::convert('软件','S2TWP');"
預期輸出	軟體
16. 匯入摘要格式（方案 B）
import_single() 回傳的 summary 陣列：

Copy[
  'anilist'    => bool,  // AniList 資料是否成功取得
  'bangumi'    => bool,  // Bangumi ID 是否解析成功
  'mal_score'  => bool,  // MAL 評分是否 > 0
  'themes'     => bool,  // AnimeThemes slug 是否有值
  'cover'      => bool,  // 封面圖片是否處理成功
  'wikipedia'  => bool,  // Wikipedia URL 是否取得
  'episodes'   => bool,  // Bangumi 集數列表是否有資料
  'streaming'  => bool,  // 串流平台清單是否非空
  'taxonomies' => bool,  // Taxonomy 是否寫入成功
]
17. 重新同步 Bangumi（AJAX）
後台 Sync Control 側欄的「重新同步 Bangumi」按鈕：

Action : anime_sync_resync_bangumi
Nonce : anime_sync_resync_bangumi
權限 : edit_posts
流程 : 讀取 anime_bangumi_id → 呼叫 Bangumi API → 更新中文標題、簡介、評分、封面、Staff、Cast、集數列表
鎖定欄位尊重 : 鎖定的欄位不會被覆蓋（封面除外，強制更新）
需要 API Handler 公開方法 :
fetch_bgm_data_public()
get_bgm_staff_public()
get_bgm_chars_public()
fetch_bgm_episodes()（已公開）
clean_synopsis_public()
⚠️ class-api-handler.php 目前 get_bangumi_data()、get_bgm_staff()、 get_bgm_chars()、clean_synopsis() 均為 private。 若要讓 AJAX handler 使用，需新增對應的 public 包裝方法， 或在 v11.0 後的下一版（ABO）修正。

18. 前台版型說明（single-anime.php）
佈局結構
Copy.asd-wrap
├── .asd-banner          橫幅圖（可選）
├── .asd-breadcrumb      麵包屑導覽
├── .asd-hero            封面 + 標題 + 評分 + 快速連結
├── .asd-tabs            錨點快速導覽（sticky）
├── .asd-container       兩欄容器（70% main + 30% sidebar）
│   ├── .asd-main
│   │   ├── #asd-sec-info       基本資訊
│   │   ├── #asd-sec-synopsis   劇情簡介
│   │   ├── #asd-sec-episodes   集數列表（前3集+展開）
│   │   ├── #asd-sec-cast       角色聲優（前12位+展開）
│   │   ├── #asd-sec-staff      製作人員
│   │   ├── #asd-sec-music      主題曲 OP/ED
│   │   ├── #asd-sec-stream     串流平台（台灣 + 其他地區）
│   │   ├── #asd-sec-trailer    預告片 iframe
│   │   ├── #asd-sec-relations  相關作品
│   │   ├── #asd-sec-links      外部連結
│   │   ├── #asd-sec-faq        常見問題（自動生成）
│   │   └── .asd-footer         來源 + 同步時間
│   └── .asd-sidebar
│       ├── 相關新聞
│       ├── 系列作品（前作/續作/衍生）
│       └── 熱門推薦
└── .asd-bottom-recs     底部推薦卡片（最多6筆）
CSS 斷點
斷點	變化
≤ 900px	單欄，sidebar 改為 grid
≤ 640px	Hero 改直排，Tab 橫向捲動，EP 日期隱藏
≤ 400px	底部推薦 2 欄，相關作品 3 欄
Schema 輸出
TVSeries / Movie / MusicVideoObject（依 format 切換）
BreadcrumbList
FAQPage（有 FAQ 資料時）
19. Bug 歷史紀錄
Bug ID	版本	檔案	說明
AJ	v1.x	api-handler	傳遞完整 externalLinks 給 get_bangumi_id()
AK	v1.x	api-handler	clean_synopsis() normalize \r\n
AL	v1.x	api-handler	synopsis + title 經 CN_Converter 轉換
AR	v1.x	api-handler	idMal null 安全處理
AT	v2.x	import-manager	寫入 anime_animethemes_id
AW	v2.x	import-manager	anime_season 大寫儲存，taxonomy slug 用小寫
ABF	v3.x	import-manager	儲存 anime_score_mal
ABG	v3.x	import-manager	儲存 official_site / twitter_url
ABH	v3.x	import-manager	寫入 post_tag（AniList tags）
ABI	v3.x	api-handler	clean_synopsis() 截斷 [简介原文]
ABJ	v3.x	api-handler	fetch_wikipedia_url()
ABK	v3.x	api-handler	parse_relations() 新增 title_chinese 等 key
ABL	v11.0	api-handler	fetch_bgm_episodes() 集數列表
ABM	v11.0	import-manager	首次匯入自動鎖定四個欄位
ABN	v11.0	import-manager	匯入摘要 summary 陣列
AX	v11.0	api-handler	fetch_animethemes() URL 字串組法，不用 add_query_arg()
ABA	v11.0	api-handler	parse_streaming_links() 加 type=STREAMING 判斷 + 白名單擴充
ABB	v11.0	id-mapper	match_best_result() 年份精準度三組候選池
ABC	v11.0	id-mapper	search_bangumi_by_title() 兩段式搜尋保留季號
F1	v11.0	acf-fields	移除 trailer preview JS 和 message 欄位
F2	v11.0	acf-fields	台灣串流改多選 checkbox
F3	v11.0	acf-fields	台灣代理商改 select + 自訂欄位
F4	v11.0	acf-fields	新增重新同步 Bangumi 按鈕
F5	v11.0	acf-fields	新增 anime_episodes_json 欄位
F-TPL	v11.0	single-anime.php	依新骨架全部重構
下一個 Bug ID：ABO

20. 已知待處理事項
項目	說明
ABO	class-api-handler.php 需新增 fetch_bgm_data_public()、get_bgm_staff_public()、get_bgm_chars_public()、clean_synopsis_public() 四個 public 包裝方法，供 AJAX 重新同步 Bangumi 使用
—	single-anime.php 與 anime-single.css 部署後需在測試站實際驗證版型
—	正式站尚未部署，所有修改目前僅在 dev.weixiaoacg.com 測試
Copy
---

CONTEXT.md v11.0 完成。新增的部分主要有：

- **Section 2**：必要前置外掛安裝順序（ACF → CPT UI → Anime Sync Pro）
- **Section 3**：完整七步驟安裝流程，包含 Composer 指令、驗證方式、`setup-taxonomy.php` 執行說明及刪除警告
- **Section 17**：重新同步 Bangumi AJAX 說明，並特別標記 `class-api-handler.php` 需要補 public 包裝方法（列為 ABO 待處理）
- **Section 20**：已知待處理事項，確保下次繼續時不會遺漏

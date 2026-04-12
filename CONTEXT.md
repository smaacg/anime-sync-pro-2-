Anime Sync Pro – CONTEXT.md v8.0 (2026‑04‑12)
專案基本資訊

外掛名稱：Anime Sync Pro
Text‑domain：anime-sync-pro
GitHub：https://github.com/smaacg/anime-sync-pro-2-
PHP 要求：≥ 8.0　WP 要求：≥ 6.0
主檔案：anime-sync-pro.php
常數：ANIME_SYNC_PRO_VERSION、ANIME_SYNC_PRO_DIR、ANIME_SYNC_PRO_URL、ANIME_SYNC_PRO_BASENAME
自動載入規則：前綴 Anime_Sync_ → includes/class-{slug}.php（主檔案內 spl_autoload_register）
目錄審計（v8.0）

路徑	狀態	備注
anime-sync-pro.php	✅	主引導，含 CPT / Taxonomy 註冊、Cron 啟用
includes/class-api-handler.php	⚠️	待修：AJ、AK、AL、AR
includes/class-id-mapper.php	🔴	需完整重寫（見下方重寫計畫）
includes/class-import-manager.php	⚠️	待修：AT、AW（season 大小寫）
includes/class-cron-manager.php	✅	run_update_map() 呼叫新版 mapper 後無需改動
includes/class-cn-converter.php	✅	提供 static_convert($text) 與 convert_array($data)
includes/class-acf-fields.php	✅	含 anime_animethemes_id 欄位定義（待 AT 補寫 meta）
includes/class-installer.php	✅	建立 DB 表、預設選項、upload 目錄
includes/class-rate-limiter.php	✅	AniList 2000ms、Jikan 1200ms、Bangumi 1000ms、AnimeThemes 700ms
includes/class-error-logger.php	✅	四級別 log、critical 寄信
includes/class-performance.php	✅	批次更新、記憶體管理、transient 工具
includes/class-image-handler.php	✅	media_library / cdn / api_url 三模式
includes/class-security.php	⚠️	待修：AS（sanitize_year 改 gmdate()）
includes/class-review-queue.php	✅	
includes/class-custom-post-type.php	⚠️	待修：AW（strtoupper($season) 比對）
includes/class-frontend.php	✅	
admin/class-admin.php	✅	
admin/pages/settings.php	⚠️	待修：AV（改讀 anime_map_meta.json）
data/cn-tw-dict.json	✅	繁化字典
data/anime_map.json	✅	已由使用者上傳至 WP（Rhilip/BangumiExtLinker，23,206 筆）
Custom Post Type

Slug：anime　REST：啟用　Rewrite slug：anime
Taxonomies

Taxonomy	Slug	結構
genre	28 個 slug（action … suspense）	平面
anime_season_tax	parent = 年份；child = {year}-{season}	階層
anime_format_tax	TV / TV_SHORT / MOVIE / OVA / ONA / SPECIAL / MUSIC	平面
季節順序：winter → spring → summer → fall

Meta Key 主清單

anime_anilist_id、anime_mal_id、anime_bangumi_id、anime_title_chinese、anime_title_romaji、anime_title_english、anime_title_native、anime_format、anime_status、anime_season、anime_season_year、anime_episodes、anime_duration、anime_source、anime_studios、anime_score_anilist、anime_score_mal、anime_score_bangumi、anime_popularity、anime_cover_image、anime_banner_image、anime_trailer_url、anime_synopsis_chinese、anime_synopsis_english、anime_start_date、anime_end_date、anime_streaming、anime_themes、anime_staff_json、anime_cast_json、anime_relations_json、anime_external_links、anime_next_airing、anime_last_sync、anime_animethemes_id（ACF 定義，待 Bug AT 補寫）

分數儲存／顯示規則

來源	儲存格式	前台顯示	後台顯示
AniList averageScore	原始 0–100 整數	÷10，保留 1 位小數	raw / 100
MAL score	×10 整數	÷10，保留 1 位小數	—
Bangumi score	×10 整數	÷10，保留 1 位小數	—
ACF 欄位不做任何除法格式化（Bug M 已移除）。

AniList GraphQL Query（最終版）

Copyquery ($id: Int) {
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
    endDate { year month day }
    averageScore
    popularity
    coverImage { extraLarge large }
    bannerImage
    trailer { id site }
    description(asHtml: false)
    genres
    studios(isMain: true) { nodes { name } }
    externalLinks { url site }
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
class‑id‑mapper.php 完整重寫計畫

資料來源

原始檔：anime_map.json（Rhilip/BangumiExtLinker，已由使用者上傳至 WP uploads 目錄）
遠端更新 URL：https://raw.githubusercontent.com/Rhilip/BangumiExtLinker/main/data/anime_map.json（CDN fallback：jsDelivr）
原始檔格式：JSON 陣列，每筆：
Copy{
  "name": "ようこそ実力至上主義の教室へ",
  "name_cn": "欢迎来到实力至上主义的教室",
  "date": "2017-07-12",
  "bgm_id": "167137",
  "mal_id": "33263",
  "douban_id": "...",
  "tvdb_id": "..."
}
衍生快取檔案（均存於 uploads/anime-sync-pro/）

檔案	內容	大小估計
anime_map.json	原始完整資料（已上傳）	~4 MB
mal_index.json	{ "mal_id": "bgm_id", … }	~300–400 KB
name_cache.json	{ "bgm_id": "name_cn_繁體", … }	~500–700 KB
anime_map_meta.json	版本資訊、筆數、時間戳	< 1 KB
anime_map_meta.json 結構

Copy{
  "source_url": "https://raw.githubusercontent.com/Rhilip/BangumiExtLinker/main/data/anime_map.json",
  "version": "2026-04-09T01:30:03Z",
  "entry_count": 23206,
  "mal_count": 12324,
  "generated_at": "2026-04-12T10:00:00Z",
  "etag": "\"abc123\""
}
建立索引流程（rebuild_indexes()）

讀取 anime_map.json（Anime_Sync_Performance::increase_memory_limit('128M')）
json_decode（associative array）
遍歷陣列：
若 mal_id 非空 → 寫入 $mal_index[$mal_id] = $entry['bgm_id']
若 name_cn 非空 → 繁化後寫入 $name_cache[$entry['bgm_id']] = Anime_Sync_CN_Converter::static_convert($entry['name_cn'])
寫入 mal_index.json.tmp、name_cache.json.tmp、anime_map_meta.json.tmp
驗證：確認三個 .tmp 檔案均可 json_decode 且非空
原子重命名（rename()）取代正式檔案
清除靜態快取變數
更新前去重下載（download_if_changed()）

讀取 anime_map_meta.json 中的 etag
發送 HEAD 請求至遠端，取得最新 ETag
若 ETag 相同 → 跳過下載，直接回傳 false（未更新）
若不同 → 下載完整檔案，覆蓋 anime_map.json，更新 meta，回傳 true
六層查詢邏輯（get_bangumi_id(array $anime_data): ?int）

$anime_data 包含：mal_id、anilist_id、post_id、title_native、title_chinese、season_year、external_links（AniList externalLinks 陣列）

層	資料來源	說明
0	WP post meta anime_bangumi_id	post_id > 0 時先讀，非空即回傳
1	mal_index.json	mal_id → O(1) 查詢，命中率 ~53 %
2	AniList external_links	掃描含 bgm.tv/subject/ 的 URL，擷取 ID
3	Bangumi Search API + 正規化標題 + 年份 ±1	命中率 ~20–25 %
4	Bangumi Search API + 中文標題 + 年份	命中率 ~3–5 %
5	寫入 _bangumi_id_pending post meta	供人工補齊
標題正規化（normalize_title(string $title): string）

剝除以下模式後 trim()：

\d+(st|nd|rd|th)\s+Season（不分大小寫）
Season\s+\d+
第\s*\d+\s*期
\d+年生[編編]
\d+学期／\d+學期
\d+期制
\s*\d+$（末尾純數字）
多餘空白 → 單空格
範例

原始標題	正規化後
ようこそ実力至上主義の教室へ 4th Season 2年生編1学期	ようこそ実力至上主義の教室へ
お隣の天使様にいつの間にか駄目人間にされていた件 2期制	お隣の天使様にいつの間にか駄目人間にされていた件
転生したらスライムだった件 第4期	転生したらスライムだった件
match_best_result() 邏輯

對每個 Bangumi 搜尋結果取正規化標題
與查詢正規化標題做完整字串比對
過濾年份：abs($result_year - $query_year) <= 1
若多筆符合 → 優先選 date 最接近的
若無符合 → 回傳 null
公開 API

Copy// 主要方法
public function get_bangumi_id(array $anime_data): ?int;

// 向後相容包裝
public function resolve_ids(
    $anilist_id,
    $mal_id,
    $bangumi_id,
    array $anilist_data = [],
    int $post_id = 0
): array;

// 地圖維護
public function download_and_cache_map(): int|false; // 回傳新增筆數或 false
public function get_map_status(): array;             // 讀 anime_map_meta.json
public function rebuild_indexes(): bool;             // 從現有 anime_map.json 重建索引
get_map_status() 回傳結構

Copy[
  'exists'        => bool,
  'path'          => string,
  'size'          => int,       // bytes
  'entry_count'   => int,       // 來自 anime_map_meta.json
  'mal_count'     => int,
  'last_updated'  => string,    // ISO 8601（generated_at）
  'age_hours'     => float,
  'version'       => string,    // source ETag / 日期
]
class‑api‑handler.php 待修清單

Bug	位置	修正方式
AJ	get_full_anime_data()	將完整 $anime_data（含 external_links、title_native、season_year）傳入 get_bangumi_id()
AK	clean_synopsis()	str_replace(["\r\n","\r"], "\n", $text) 加在最前
AL	get_full_anime_data()	anime_title_chinese 寫入前先 Anime_Sync_CN_Converter::static_convert()
AR	get_full_anime_data()	idMal 為 null 時不傳 MAL ID，mapper 內部已安全處理
待修 Bug 彙整表（v8.0）

Bug ID	檔案	嚴重度	說明
AA	class-id-mapper.php get_map_status()	🟡 Low	date() → gmdate()
AB	class-id-mapper.php download_and_cache_map()	🟡 Low	驗證 HTTP status code
AJ	class-api-handler.php	🔴 Critical	externalLinks 未傳入 mapper，Layer 2 永遠失敗
AK	class-api-handler.php clean_synopsis()	🟠 Medium	\r\n 未清除
AL	class-api-handler.php	🟠 Medium	name_cn 未繁化即寫入
AO	class-id-mapper.php match_best_result()	🔴 Critical	標題後綴不一致導致 Layer 3 失敗，需正規化
AR	class-api-handler.php	🟠 Medium	idMal 為 null 時未安全處理
AS	class-security.php sanitize_year()	🟡 Low	date() → gmdate()
AT	class-import-manager.php save_post_meta()	🟠 Medium	未寫入 anime_animethemes_id
AV	admin/pages/settings.php	🟠 Medium	直接解析 4 MB JSON 取筆數，改讀 anime_map_meta.json
AW	class-import-manager.php / class-custom-post-type.php	🟠 Medium	anime_season 大小寫不一致，後台欄位顯示 '—'
測試案例記錄

#	AniList ID	MAL ID	Bangumi ID	日文標題（節錄）	播出
1	171448	57723	515594	転生したらスライムだった件 第4期	2026‑04
2	170019	56876	458684	お隣の天使様…件2	2026‑04
3	180745	59708	636401	ようこそ実力至上主義…4th Season	2026‑04
三個案例在修正 AJ + AO 後，預期命中 Layer 2 或 Layer 3。

下一步確認事項

以上 CONTEXT v8.0 內容是否完整正確？
確認後開始輸出 class-id-mapper.php 完整重寫版（輸出前再次確認）。
之後輸出 class-api-handler.php 修正版（含 AJ / AK / AL / AR）。
最後輸出其他受影響檔案的 patch（AV、AW、AS、AT）。

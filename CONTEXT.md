Anime Sync Pro – CONTEXT.md v9.0 (2026‑04‑12)
專案基本資訊

外掛名稱：Anime Sync Pro
Text‑domain：anime-sync-pro
GitHub：https://github.com/smaacg/anime-sync-pro-2-
PHP 要求：≥ 8.0　WP 要求：≥ 6.0
主檔案：anime-sync-pro.php
常數：ANIME_SYNC_PRO_VERSION、ANIME_SYNC_PRO_DIR、ANIME_SYNC_PRO_URL、ANIME_SYNC_PRO_BASENAME
自動載入規則：前綴 Anime_Sync_ → includes/class-{slug}.php
目錄審計（v9.0）

路徑	狀態	備注
anime-sync-pro.php	✅	主引導，CPT / Taxonomy / Cron 初始化
includes/class-api-handler.php	✅	AJ / AK / AL / AR 已修正
includes/class-id-mapper.php	✅	完整重寫，六層查詢，索引建立，原子更新
includes/class-import-manager.php	✅	AT / AW 已修正
includes/class-cron-manager.php	✅	
includes/class-cn-converter.php	✅	
includes/class-acf-fields.php	✅	
includes/class-installer.php	✅	
includes/class-rate-limiter.php	✅	
includes/class-error-logger.php	✅	
includes/class-performance.php	✅	
includes/class-image-handler.php	✅	
includes/class-security.php	✅	AS 已修正
includes/class-review-queue.php	✅	
includes/class-custom-post-type.php	✅	AW 已修正
includes/class-frontend.php	✅	
admin/class-admin.php	✅	
admin/pages/settings.php	✅	AV 已修正
data/cn-tw-dict.json	✅	繁化字典
data/anime_map.json	✅	已上傳至 WP（23,206 筆）
所有已知 Bug 均已修正，目前無待修項目。

Custom Post Type

Slug：anime　REST：啟用　Rewrite slug：anime
Taxonomies

Taxonomy	結構	範例
genre	平面，28 個 slug	action、romance、suspense …
anime_season_tax	階層：parent = 年份；child = {year}-{season}	2026 → 2026-spring
anime_format_tax	平面	format-tv、format-movie …
季節順序：winter → spring → summer → fall

格式對應：TV → format-tv、TV_SHORT → format-tv-short、MOVIE → format-movie、OVA → format-ova、ONA → format-ona、SPECIAL → format-special、MUSIC → format-music

Meta Key 主清單

Meta Key	說明
anime_anilist_id	AniList ID
anime_mal_id	MAL ID（可為 null）
anime_bangumi_id	Bangumi subject ID
anime_title_chinese	繁體中文標題（經 CN_Converter）
anime_title_romaji	Romaji 標題
anime_title_english	英文標題
anime_title_native	日文原題
anime_format	格式（TV / MOVIE…）
anime_status	狀態（FINISHED / RELEASING…）
anime_season	季節，大寫儲存（WINTER / SPRING / SUMMER / FALL）
anime_season_year	播出年份
anime_episodes	集數
anime_duration	每集分鐘數
anime_source	原作類型
anime_studios	製作公司（逗號分隔字串）
anime_score_anilist	AniList 評分（原始 0–100）
anime_score_mal	MAL 評分（×10 整數）
anime_score_bangumi	Bangumi 評分（×10 整數）
anime_popularity	AniList 人氣值
anime_cover_image	封面圖 URL
anime_banner_image	橫幅圖 URL
anime_trailer_url	YouTube 預告 URL
anime_synopsis_chinese	中文簡介
anime_synopsis_english	英文簡介
anime_start_date	開播日期（Y-m-d）
anime_end_date	完結日期（Y-m-d）
anime_streaming	串流平台（JSON）
anime_themes	OP/ED 主題曲（JSON）
anime_staff_json	製作人員（JSON）
anime_cast_json	聲優角色（JSON）
anime_relations_json	關聯作品（JSON）
anime_external_links	外部連結（JSON）
anime_next_airing	下集播出資訊（JSON）
anime_last_sync	最後同步時間（mysql datetime）
anime_animethemes_id	AnimeThemes ID（有值時才寫入）
_bangumi_id_pending	人工補齊旗標（六層查詢失敗時設為 1）
分數儲存／顯示規則

來源	儲存格式	前台顯示	後台 ACF 顯示
AniList averageScore	原始 0–100 整數	÷10，1 位小數	raw / 100
MAL score	×10 整數	÷10，1 位小數	—
Bangumi score	×10 整數	÷10，1 位小數	—
ACF 欄位不做任何除法格式化。

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
    endDate   { year month day }
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
class‑id‑mapper.php 架構摘要（v9.0）

快取檔案（均存於 uploads/anime-sync-pro/）

檔案	內容	大小估計
anime_map.json	原始完整資料（已上傳）	~4 MB
mal_index.json	{ "mal_id": "bgm_id" }	~300–400 KB
name_cache.json	{ "bgm_id": "繁體中文 name_cn" }	~500–700 KB
anime_map_meta.json	版本、筆數、時間戳、ETag	< 1 KB
anime_map_meta.json 結構

Copy{
  "source_url":   "https://raw.githubusercontent.com/Rhilip/BangumiExtLinker/main/data/anime_map.json",
  "version":      "2026-04-09T01:30:03Z",
  "entry_count":  23206,
  "mal_count":    12324,
  "generated_at": "2026-04-12T10:00:00Z",
  "etag":         "\"abc123\""
}
六層查詢邏輯（get_bangumi_id(array $anime_data): ?int）

層	資料來源	命中率
0	WP post meta anime_bangumi_id	100%（有值時）
1	mal_index.json MAL ID → bgm_id	~53%
2	AniList external_links → bgm.tv/subject/ URL	新番高
3	Bangumi Search + 正規化日文標題 + 年份 ±1	~20–25%
4	Bangumi Search + 中文標題 + 年份	~3–5%
5	寫入 _bangumi_id_pending，供人工補齊	fallback
標題正規化（normalize_title()）剝除模式

\d+(st|nd|rd|th) Season、Season \d+、第\d+期、\d+年生[編篇]、\d+[学學]期、\d+期制、末尾純數字、多餘空白

正規化範例

原始標題	正規化後
ようこそ実力至上主義の教室へ 4th Season 2年生編1学期	ようこそ実力至上主義の教室へ
お隣の天使様にいつの間にか駄目人間にされていた件 2期制	お隣の天使様にいつの間にか駄目人間にされていた件
転生したらスライムだった件 第4期	転生したらスライムだった件
公開 API

Copypublic function get_bangumi_id( array $anime_data ): ?int;
public function resolve_ids( $anilist_id, $mal_id, $bangumi_id, array $anilist_data = [], int $post_id = 0 ): array;
public function download_and_cache_map(): int|false;
public function rebuild_indexes( ?array $data = null ): int|false;
public function get_map_status(): array;
public function get_chinese_title( int $bgm_id ): ?string;
public function get_last_error(): ?string;
get_map_status() 回傳結構

Copy[
  'exists'       => bool,
  'path'         => string,
  'size'         => int,      // bytes
  'entry_count'  => int,      // 來自 anime_map_meta.json
  'mal_count'    => int,
  'last_updated' => string,   // ISO 8601 generated_at
  'age_hours'    => float,
  'version'      => string,   // ETag
]
class‑api‑handler.php 修正摘要（v9.0）

Bug	修正內容
AJ	external_links、title_native、season_year 完整傳入 get_bangumi_id()
AK	clean_synopsis() 第一步 str_replace(["\r\n","\r"], "\n", $text)
AL	anime_title_chinese 回傳前經 Anime_Sync_CN_Converter::static_convert()
AR	idMal 為 null 時保留 null，不強制轉型為 0
已修正 Bug 完整歷史（v9.0）

Bug ID	檔案	說明	版本
A–X, Z	各檔案	早期 bug，詳見 v6.0 記錄	v6.0
AA	class-id-mapper.php	gmdate() 取代 date()	v9.0
AB	class-id-mapper.php	HTTP status code 驗證	v9.0
AJ	class-api-handler.php	externalLinks 傳入 mapper	v9.0
AK	class-api-handler.php	\r\n 清除	v9.0
AL	class-api-handler.php	name_cn 繁化	v9.0
AO	class-id-mapper.php	標題正規化，Layer 3 修正	v9.0
AR	class-api-handler.php	null idMal 安全處理	v9.0
AS	class-security.php	sanitize_year() 改用 gmdate()	v9.0
AT	class-import-manager.php	補寫 anime_animethemes_id	v9.0
AV	admin/pages/settings.php	改讀 anime_map_meta.json	v9.0
AW	class-import-manager.php / class-custom-post-type.php	anime_season 大小寫統一	v9.0
目前無待修 Bug。下一個新發現的 Bug 從 AX 開始編號。

測試案例記錄

#	AniList ID	MAL ID	Bangumi ID	日文標題（節錄）	播出	預期命中層
1	171448	57723	515594	転生したらスライムだった件 第4期	2026‑04	Layer 1 或 2
2	170019	56876	458684	お隣の天使様…件2	2026‑04	Layer 1 或 2
3	180745	59708	636401	ようこそ実力至上主義…4th Season	2026‑04	Layer 1 或 2
下一步

所有已知問題均已修正，CONTEXT v9.0 為目前最終狀態。如需新功能或發現新 Bug，從 AX 開始編號，並更新至 CONTEXT v10.0

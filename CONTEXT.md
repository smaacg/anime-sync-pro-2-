以下是新版 CONTEXT.md v7.0：

Anime Sync Pro — 開發上下文文件 (CONTEXT.md)
版本：7.0 | 更新日期：2026-04-12 適用模型：Claude / GPT / Gemini

一、專案基本資訊
項目	內容
Plugin Name	Anime Sync Pro
Text Domain	anime-sync-pro
GitHub Repo	https://github.com/smaacg/anime-sync-pro-2-
PHP Requirement	≥ 8.0（anime-sync-pro.php 標頭宣告 Requires PHP: 8.0）
WordPress Requirement	≥ 6.0
主要插件檔	anime-sync-pro.php
插件常數	ANIME_SYNC_PRO_VERSION, ANIME_SYNC_PRO_DIR, ANIME_SYNC_PRO_URL, ANIME_SYNC_PRO_BASENAME
二、完整目錄結構與審查狀態（v7.0）
Copyanime-sync-pro/
├── anime-sync-pro.php              ✅ 已修正 (Bug A,B,C) + Cron 初始化已完成
├── CONTEXT.md                      ✅ 本文件 v7.0
├── uninstall.php                   ✅ 無需修正
├── setup-taxonomy.php              ✅ 無需修正
├── data/
│   └── cn-tw-dict.json             ✅ 繁化字典，已正常載入
├── admin/
│   ├── class-admin.php             ✅ 已修正 (Bug D)
│   └── pages/
│       ├── dashboard.php           ✅ 無需修正
│       ├── import-tool.php         ✅ 無需修正
│       ├── review-queue.php        ✅ 無需修正
│       ├── published-list.php      ✅ 無需修正
│       ├── review-preview.php      ✅ 無需修正
│       ├── logs.php                ✅ 無需修正
│       └── settings.php            ✅ 無需修正
├── public/
│   ├── class-frontend.php          ✅ 已修正 (Bug B,E,F)
│   └── templates/
│       ├── single-anime.php        ✅ 已修正 (Bug V,W,X)
│       └── archive-anime.php       ✅ 已修正 (Bug 4,5,6,8)
└── includes/
    ├── class-security.php          ✅ 已修正 (Bug H,I)
    ├── class-acf-fields.php        ✅ 已修正 (Bug J,K,L,M)
    ├── class-import-manager.php    ✅ 已修正 (Bug 1,J,K,L,P,Q,R,U) — 現行版本已確認
    ├── class-api-handler.php       ⚠️ 需修正 (Bug AJ, AK, AL, AR)
    ├── class-id-mapper.php         🔴 需完整重寫（見下方說明）
    ├── class-cn-converter.php      ✅ 無需修正（convert() 實例方法已存在）
    ├── class-rate-limiter.php      ✅ Bug Z 已整合，有 animethemes 速率設定
    ├── class-cron-manager.php      ✅ 已新建
    ├── class-installer.php         ✅ 無需修正
    ├── class-review-queue.php      ✅ 無需修正
    ├── class-image-handler.php     ✅ 已修正 (Bug 2)
    ├── class-error-logger.php      ✅ 無需修正
    ├── class-performance.php       ✅ 無需修正
    └── class-custom-post-type.php  ✅ 無需修正
三、Custom Post Type 與 Taxonomy 定義
Post Type: anime
Rewrite slug: anime | REST API 啟用
Taxonomy 1: genre（Rewrite: genre）
已定義 slug：action / adventure / comedy / drama / fantasy / sci-fi / horror / mystery / psychological / romance / sports / supernatural / music-genre / slice-of-life / shounen / shoujo / seinen / josei / kids / yuri / bl / isekai / harem / historical / school / wuxia / suspense / mecha / mahou-shoujo / ecchi / thriller

Taxonomy 2: anime_season_tax（Rewrite: season）
父層：年份 term（如 2025）
子層：{year}-{season}（如 2025-winter）
季節順序：winter → spring → summer → fall
Taxonomy 3: anime_format_tax（Rewrite: format）
TV→format-tv / TV_SHORT→format-tv-short / MOVIE→format-movie / OVA→format-ova / ONA→format-ona / SPECIAL→format-special / MUSIC→format-music

四、Meta Key 對照總表（最終統一版）
⚠️ 此為唯一真實來源

Meta Key	說明	類型
anime_anilist_id	AniList ID	int
anime_mal_id	MyAnimeList ID	int
anime_bangumi_id	Bangumi ID	int
bangumi_id	相容舊 key（同時寫入）	int
anime_title_chinese	繁體中文標題	string
anime_title_zh	相容舊 key（同時寫入）	string
anime_title_romaji	羅馬字標題	string
anime_title_english	英文標題	string
anime_title_native	日文標題	string
anime_format	格式（TV/MOVIE 等）	string
anime_type	相容舊 key（同時寫入）	string
anime_status	播出狀態	string
anime_season	季節（小寫 spring 等）	string
anime_season_year	年份	int
anime_year	相容舊 key（同時寫入）	int
anime_episodes	集數	int
anime_duration	單集時長（分鐘）	int
anime_source	原作來源	string
anime_studios	製作公司	string
anime_score_anilist	AniList 評分 0-100 原始值	int
anime_score_bangumi	Bangumi 評分（×10 儲存）	int
anime_popularity	人氣數值	int
anime_cover_image	封面圖 URL	string
anime_banner_image	橫幅圖 URL	string
anime_trailer_url	預告片 URL	string
anime_synopsis_chinese	繁體中文簡介（Bangumi 優先）	string
anime_synopsis_zh	相容舊 key（同時寫入）	string
anime_synopsis_english	英文簡介	string
anime_start_date	開播日期 Y-m-d	string
anime_end_date	完結日期 Y-m-d	string
anime_streaming	串流平台 JSON	JSON
anime_themes	OP/ED 主題曲 JSON	JSON
anime_staff_json	製作人員 JSON	JSON
anime_cast_json	聲優/角色 JSON	JSON
anime_relations_json	相關作品 JSON	JSON
anime_last_sync	最後同步時間	timestamp
五、Bug 完整總表（v7.0，含待修正項目）
Bug ID	描述	影響檔案	狀態
Bug A	主插件缺少 rewrite flush 邏輯	anime-sync-pro.php	✅
Bug B	Frontend 多餘 JSON-LD filter	class-frontend.php	✅
Bug C	CPT/Taxonomy 初始化時機錯誤	anime-sync-pro.php	✅
Bug 1	Genre mapping 不完整	class-import-manager.php	✅
Bug 2	封面圖重複下載	class-image-handler.php	✅
Bug 3	Synopsis 未清除 spoiler 標記	class-api-handler.php	✅
Bug 4	archive season_label 邏輯錯誤	archive-anime.php	✅
Bug 5	archive canonical URL 錯誤	archive-anime.php	✅
Bug 6	分數顯示條件錯誤	archive-anime.php, single-anime.php	✅
Bug 8	anime-meta-ep 缺少 color class	archive-anime.php	✅
Bug D	批量操作 AJAX 缺失	class-admin.php	✅
Bug E	REST API 錯誤 taxonomy slug	class-frontend.php	✅
Bug F	anime-single.css 未 enqueue	class-frontend.php	✅
Bug H	class-security.php 缺少 ABSPATH	class-security.php	✅
Bug I	Security 驗證上限錯誤	class-security.php	✅
Bug J	ACF anime_themes_json key 不符	class-acf-fields.php, class-import-manager.php	✅
Bug K	ACF anime_streaming_json key 不符	class-acf-fields.php, class-import-manager.php	✅
Bug L	ACF anime_studio vs anime_studios 不符	class-acf-fields.php, class-import-manager.php	✅
Bug M	ACF acf/format_value ÷10 導致顯示錯誤	class-acf-fields.php	✅
Bug P	API Handler 未抓取 studios	class-api-handler.php, class-import-manager.php	✅
Bug Q	API Handler 未處理 themes	class-api-handler.php, class-import-manager.php	✅
Bug R	API Handler 未抓取 streaming	class-api-handler.php, class-import-manager.php	✅
Bug S	Bangumi 評分路徑解析失敗	class-api-handler.php	✅
Bug T	中文簡介誤用 AniList 而非 Bangumi	class-api-handler.php	✅
Bug U	缺少 duration, startDate, endDate	class-api-handler.php, class-import-manager.php	✅
Bug V	single-anime.php 使用舊 key anime_streaming_json	single-anime.php	✅
Bug W	single-anime.php 讀 anime_studio 應為 anime_studios	single-anime.php	✅
Bug X	倒數計時器 JS 邏輯不完整	single-anime.php	✅
Bug Z	Rate Limiter 孤立，未被 API Handler 呼叫	class-api-handler.php, class-rate-limiter.php	✅
Bug AA	get_map_status() 使用 date() 應改 gmdate()	class-id-mapper.php	🔴 待修（id-mapper 重寫時一併處理）
Bug AB	download_and_cache_map() 未驗證 HTTP 狀態碼	class-id-mapper.php	🔴 待修（同上）
Bug AJ	resolve_ids() 未傳入 externalLinks，Layer 2 永遠失敗	class-api-handler.php	🔴 待修
Bug AK	clean_synopsis() 未處理 \r\n，Bangumi summary 殘留空行	class-api-handler.php	🟠 待修
Bug AL	name_cn 存入前未經繁化 convert_text()（已確認 cn_converter 在 api_handler 內可呼叫）	class-api-handler.php	🟠 待修
Bug AO	search_bangumi_by_title() + match_best_result()：標題正規化不足，跨平台命名差異（4th Season 2年生編1学期 vs 4th Season）導致 Layer 3 大量失敗	class-id-mapper.php	🔴 待修（id-mapper 重寫時一併處理）
Bug AR	idMal 為 null 時未防呆，Layer 1 會用 null 查 index	class-api-handler.php	🟠 待修
六、class-id-mapper.php 完整重寫計畫（v7.0 核心工程）
6.1 資料來源變更
項目	舊版（v6）	新版（v7）
地圖 URL	bangumi/Archive mal_bangumi_map.json（已失效）	Rhilip/BangumiExtLinker anime_map.json
備援 URL	無	jsDelivr CDN 鏡像
資料格式	字典（mal_id → bgm_id）	陣列（每筆含 name / name_cn / bgm_id / mal_id 等欄位）
本地快取結構	單一 anime_map.json	anime_map.json（原始）+ mal_index.json（索引）+ name_cache.json（繁中名稱）+ anime_map_meta.json（版本元資料）
6.2 Bangumi ID 查找順序（六層架構）
Layer	方法	覆蓋率	準確率
0	WP post meta anime_bangumi_id（已匯入過的文章）	100%（適用）	100%
1	mal_index.json MAL ID → bgm_id O(1) 查找	~53%	100%
2	AniList externalLinks 解析 bgm.tv/subject/{id} URL	高（新番）	100%
3	Bangumi Search API + 正規化基底標題 + 年份（±1年）	~20–25%	高
4	Bangumi Search API + 繁體中文標題 + 年份	~3–5%	中
5	設置 _bangumi_id_pending 旗標，等待人工填入	—	—
6.3 標題正規化規則（Bug AO 修正核心）
搜尋前對 title_native 執行以下正規化，得到 $base_title：

去除：\d+th Season、\d+nd Season、\d+rd Season、\d+st Season
去除：第\d+期、Season \d+、\d+期制
去除：\d+年生編、\d+学期
去除：尾部獨立數字（如 件2 → 件，但僅限後綴純數字）
清理多餘空白
對 Bangumi 搜尋結果的每個標題同樣正規化後再比對。

驗證（三個測試案例）：

AniList native（正規化前）	正規化後	Bangumi 標題（正規化後）	比對結果
転生したらスライムだった件 第4期	転生したらスライムだった件	転生したらスライムだった件	✅
お隣の天使様にいつの間にか駄目人間にされていた件 2期制	お隣の天使様にいつの間にか駄目人間にされていた件	お隣の天使様にいつの間にか駄目人間にされていた件	✅
ようこそ実力至上主義の教室へ 4th Season 2年生編1学期	ようこそ実力至上主義の教室へ	ようこそ実力至上主義の教室へ	✅
6.4 name_cache.json 規格
Copy{
  "515594": "關於我轉生變成史萊姆這檔事 第四季",
  "458684": "關於鄰家的天使大人不知不覺把我慣成了廢人這檔子事第二季",
  "510710": "歡迎來到實力至上主義的教室第四季"
}
key：bgm_id（字串）
value：已繁化的 name_cn（由 Anime_Sync_CN_Converter::static_convert() 處理）
建立時機：download_and_cache_map() 下載並建立 mal_index.json 後同步建立
繁化由 Anime_Sync_CN_Converter::static_convert() 靜態方法呼叫，無需注入 converter 實例
6.5 anime_map_meta.json 規格
Copy{
  "version": "2026-04-09T01:30:03Z",
  "entry_count": 23206,
  "mal_count": 12324,
  "generated_at": "2026-04-12T10:00:00Z"
}
6.6 更新流程（原子操作）
下載新 anime_map.json → 寫入 .tmp 暫存檔
解析 JSON，建立 mal_index.tmp.json 與 name_cache.tmp.json（同時繁化 name_cn）
驗證三個 .tmp 檔案均有效
原子 rename（.tmp → 正式檔）
更新 anime_map_meta.json
清除靜態快取 self::$map = null
6.7 公開介面
Copy// 主要入口（新版）
public function get_bangumi_id(array $anime_data): ?int

// 相容舊版 class-api-handler.php 的包裝器（保留）
public function resolve_ids($anilist_id, $mal_id, $bangumi_id, array $anilist_data = [], int $post_id = 0): array
$anime_data 陣列格式（由 class-api-handler.php 組裝後傳入）：

Copy[
    'mal_id'         => int|null,     // 來自 AniList idMal，可為 null
    'anilist_id'     => int,
    'post_id'        => int,          // 更新既有文章時使用（Layer 0）
    'title_native'   => string,       // 日文標題，用於正規化後搜尋
    'title_chinese'  => string,       // 繁中標題，用於 Layer 4
    'season_year'    => int,
    'external_links' => array,        // AniList externalLinks 原始陣列（Layer 2）
]
七、class-api-handler.php 待修正清單（v7.0）
Bug	修正方式
AJ	resolve_ids() 呼叫改為 get_bangumi_id()，傳入完整 $anime_data（含 external_links）
AK	clean_synopsis() 第一步加入 str_replace("\r\n", "\n", $text) 和 str_replace("\r", "\n", $text)
AL	anime_title_chinese 欄位的 name_cn 來源已在 convert_text() 內繁化（現行 code 已走 convert_text()，但需確認 name_cache.json 路徑的繁化也在 id_mapper 建立期間完成）
AR	$mal_id = $al['idMal'] ?? null 後傳給 get_bangumi_id() 前不另做處理，由 id_mapper 內部做 null 判斷
八、分數儲存與顯示規則
來源	DB 儲存值	前台顯示
AniList averageScore	0-100 原始值	÷10，1 位小數
Bangumi score	×10 整數	÷10，1 位小數
ACF 不做任何 acf/format_value 除法（Bug M 已移除）。

九、AniList GraphQL Query（v7.0 最終版，已含所有欄位）
Copyquery($id: Int) {
  Media(id: $id, type: ANIME) {
    id idMal
    title { romaji english native }
    status format episodes duration source
    season seasonYear
    startDate { year month day }
    endDate   { year month day }
    averageScore meanScore popularity
    coverImage { extraLarge large }
    bannerImage
    trailer { id site }
    description(asHtml: false)
    genres
    studios { nodes { name isAnimationStudio } }
    externalLinks { site url type icon color language }
    nextAiringEpisode { airingAt episode }
    relations {
      edges {
        relationType
        node { id title { romaji native } format status episodes seasonYear coverImage { large } }
      }
    }
    staff(perPage: 10) {
      edges { role node { name { full } } }
    }
    characters(perPage: 10) {
      edges {
        role
        voiceActors { name { full } languageV2 }
        node { name { full } image { medium } }
      }
    }
  }
}
Copy
十、待解確認事項（輸出前必須回答）
#	問題	狀態
Q-AP	name_cache.json 繁化：已確認用 Anime_Sync_CN_Converter::static_convert() 靜態方法，無需注入實例	✅ 已解決
Q-AO	Bug AO 修正方向：基底標題正規化 + 年份篩選	✅ 已確認
Q-OUTPUT	輸出順序：先輸出 class-id-mapper.php（完整重寫），再輸出 class-api-handler.php（Bug AJ/AK/AL/AR 修正）	✅ 等待下一步指令
十一、測試案例記錄
AniList ID	MAL ID	Bangumi ID	標題	Layer 預測結果
182205	59970	515594	転生したらスライムだった件 第4期	L1 MISS → L2（修 AJ 後 HIT）→ L3 正規化後 HIT
170019	56876	458684	お隣の天使様…件2	L1 MISS → L2 HIT（修 AJ 後）
180745	59708	510710	ようこそ実力至上主義…4th Season	L1 MISS → L2 HIT（修 AJ 後）→ L3 正規化後 HIT
現在的問題只剩一個： Bug AO 修正方向 和 輸出指令 都已確認，但 Bug AL 的 name_cn 繁化路徑 需要再釐清一點——

現行 class-api-handler.php 在 anime_title_chinese 已走 $this->convert_text($bgm_data['name_cn']) 沒問題。但 name_cache.json 裡的 name_cn 是在 class-id-mapper.php 的 download_and_cache_map() 內建立的，那個時候沒有 api_handler 的 converter 實例。已確認改用 Anime_Sync_CN_Converter::static_convert() 靜態方法，可以直接在 id_mapper 內呼叫，無需注入。這樣 Bug AL 就完全解決了。

好，全部檔案確認完畢。現在交叉比對實際程式碼，發現 CONTEXT.md v7.0 還需要補幾個重要發現，不輸出程式碼：

讀完全部檔案後，新發現的問題：

Bug AS（新）— class-security.php 的 sanitize_year() 使用 date() 而非 gmdate() 第 38 行：$now = (int) date('Y'); 應改為 gmdate('Y')，與 Bug AA 同類問題。

Bug AT（新）— class-acf-fields.php 已有 anime_animethemes_id 欄位，但 class-import-manager.php 完全沒有寫入這個 meta ACF 定義了 field_anime_animethemes_id（name: anime_animethemes_id），但 save_post_meta() 的 $fields 陣列裡沒有這個 key，AnimeThemes 整合準備好後需要一起補上。

Bug AU（新）— class-cron-manager.php 的 run_update_map() 會在重寫後失效 run_update_map() 直接 new Anime_Sync_ID_Mapper() 並呼叫 download_and_cache_map()，這個沒問題。但重寫後 download_and_cache_map() 會多建立 mal_index.json、name_cache.json、anime_map_meta.json，所以 cron 這裡不需要改，只要確保 download_and_cache_map() 返回值仍是 int|false（bytes written）就好。✅ 確認不影響。

Bug AV（新）— settings.php 的 Map 狀態顯示需配合新格式更新 settings.php 顯示 $map_status['entry_count'] 和 $map_status['file_size']，重寫後 get_map_status() 需要改為讀取 anime_map_meta.json 的 entry_count 與 mal_count，而不是直接 count(json_decode(...)))（因為 anime_map.json 是陣列格式，23206 筆，每次 count 都要解析 4MB JSON，效能很差）。→ 這正是 anime_map_meta.json 存在的原因，get_map_status() 改讀 meta 檔即可。

Bug AW（新）— class-import-manager.php 的 save_post_meta() 寫入 anime_season 為小寫，但 class-custom-post-type.php 的 render_admin_columns() 用大寫 key 做 $season_map 比對 save_post_meta() 寫入 anime_season => $data['anime_season']，而 class-api-handler.php 回傳的是 strtolower($al['season']) → 小寫（如 spring）。但 class-custom-post-type.php 的 season_map 用大寫 key ['WINTER'=>'冬', 'SPRING'=>'春'...] 比對，導致後台列表季度欄永遠顯示 —。需要在 render_admin_columns() 改為 strtoupper($season) 後再查 map，或 save_post_meta() 改存大寫。

CONTEXT.md v7.0 最終版需補上的內容確認：

Bug	位置	嚴重度	說明
AS	class-security.php sanitize_year()	🟡 Low	date() 應改 gmdate()
AT	class-import-manager.php save_post_meta()	🟠 Medium	anime_animethemes_id 欄位未寫入（AnimeThemes 整合時補）
AV	class-id-mapper.php get_map_status()	🟠 Medium	改為讀 anime_map_meta.json，避免每次解析 4MB JSON
AW	class-custom-post-type.php render_admin_columns()	🟠 Medium	anime_season 大小寫不一致，後台季度欄永遠顯示 —

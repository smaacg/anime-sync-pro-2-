# CONTEXT.md — Anime Sync Pro 插件開發紀錄

最後更新：2026-04-16

---

## 專案基本資訊

- **插件名稱**：Anime Sync Pro
- **GitHub**：https://github.com/smaacg/anime-sync-pro-2-
- **WordPress 版本**：依主機環境
- **ACF 版本**：6.8.0（免費版，不支援 conditional_logic）
- **自訂文章類型**：`anime`
- **網站**：https://dev.weixiaoacg.com/

---

## 檔案結構

anime-sync-pro/ ├── includes/ │ ├── class-api-handler.php ← 核心 API 處理 │ ├── class-acf-fields.php ← ACF 欄位定義 │ ├── class-import-manager.php ← 匯入管理 │ ├── class-cn-converter.php ← 簡繁轉換 │ ├── class-cron-manager.php ← 排程 │ ├── class-custom-post-type.php ← CPT 定義 │ ├── class-error-logger.php ← 錯誤記錄 │ ├── class-id-mapper.php ← ID 對應 │ ├── class-image-handler.php ← 圖片處理 │ ├── class-installer.php ← 安裝器 │ ├── class-performance.php ← 效能 │ ├── class-rate-limiter.php ← API 速率限制 │ ├── class-review-queue.php ← 審核佇列 │ └── class-security.php ← 安全 ├── admin/ │ ├── class-admin.php ← 後台 AJAX handlers │ └── pages/ │ ├── import-tool.php ← 匯入工具 UI │ ├── dashboard.php │ ├── logs.php │ ├── published-list.php │ ├── review-preview.php │ ├── review-queue.php ← 審核佇列 UI │ └── settings.php └── public/ ├── class-frontend.php ← 前台類別 ├── assets/ │ ├── css/anime-single.css ← 單一動畫頁樣式（v11.2） │ └── js/frontend.js └── templates/ ├── single-anime.php ← 單一動畫頁模板（v12.1） ├── archive-anime.php ← 動畫列表頁模板（CSS 命名參考） └── archive-series.php ← 系列列表頁模板（已完成）

Copy
---

## API 資料來源

| 來源 | 用途 | Endpoint |
|------|------|----------|
| AniList（GraphQL） | 主要動畫資料、Staff/Cast fallback | https://graphql.anilist.co |
| Bangumi TV | 中文標題、簡介、Staff、Cast、集數（主要） | https://api.bgm.tv/v0/ |
| AnimeThemes | OP/ED 音訊（OGG）與影片（WebM） | https://api.animethemes.moe/anime |
| Jikan（MAL） | MAL 評分 | https://api.jikan.moe/v4/anime/ |
| Wikipedia ZH/EN | Wikipedia 連結 | https://zh.wikipedia.org/w/api.php |

---

## 重要 Meta 欄位清單

### 基本資訊
| Meta Key | 說明 |
|----------|------|
| `anime_anilist_id` | AniList ID |
| `anime_mal_id` | MyAnimeList ID |
| `anime_bangumi_id` | Bangumi ID |
| `anime_animethemes_id` | AnimeThemes slug |
| `anime_title_chinese` | 繁體中文標題（Bangumi） |
| `anime_title_native` | 日文原名 |
| `anime_title_romaji` | Romaji |
| `anime_title_english` | 英文標題 |
| `anime_format` | TV/MOVIE/OVA 等 |
| `anime_status` | FINISHED/RELEASING 等 |
| `anime_season` | WINTER/SPRING/SUMMER/FALL |
| `anime_season_year` | 播出年份 |
| `anime_episodes` | 總集數 |
| `anime_duration` | 每集時長（分鐘） |
| `anime_start_date` | 開始日期（YYYY-MM-DD） |
| `anime_end_date` | 結束日期 |
| `anime_studios` | 製作公司（逗號分隔） |
| `anime_source` | 原作來源 |
| `anime_popularity` | AniList 人氣數值 |

### 評分（重要：儲存格式）
| Meta Key | 儲存格式 | 前台顯示 |
|----------|----------|----------|
| `anime_score_anilist` | 0–100（AniList 原始） | 除以 10 → 0–10 |
| `anime_score_mal` | 0–100（×10 儲存） | 除以 10 → 0–10 |
| `anime_score_bangumi` | 0–100（×10 儲存） | 除以 10 → 0–10 |

### JSON 欄位
| Meta Key | 結構 | 來源 |
|----------|------|------|
| `anime_staff_json` | `[{id, name, role, image, source:'bangumi'}]` | Bangumi（取代 AniList） |
| `anime_cast_json` | `[{id, name, role, image, voice_actors:[{id,name,image}], source:'bangumi'}]` | Bangumi（取代 AniList） |
| `anime_relations_json` | `[{id, type, relation_type, title}]` | AniList |
| `anime_episodes_json` | `[{id, ep, name, name_cn, airdate, comment}]` | Bangumi |
| `anime_themes` | `[{type, sequence, slug, song_title, audio_url, video_url, resolution}]` | AnimeThemes |
| `anime_streaming` | `[{site, url}]` | AniList externalLinks |
| `anime_external_links` | AniList 原始格式 | AniList |
| `anime_next_airing` | `{airingAt: Unix時間戳, episode: 集數}` | AniList |

### 台灣在地資訊
| Meta Key | 說明 |
|----------|------|
| `anime_tw_streaming` | 陣列，勾選的平台 key |
| `anime_tw_streaming_other` | 其他平台（逗號分隔文字） |
| `anime_tw_streaming_url_{key}` | 各平台個別連結（16 個） |
| `anime_tw_distributor` | 代理商 key |
| `anime_tw_distributor_custom` | 自訂代理商名稱 |
| `anime_tw_broadcast` | 播出時間文字 |

### FAQ
| Meta Key | 說明 |
|----------|------|
| `anime_faq_json` | 手動 JSON：`[{"q":"問題","a":"答案"}]`，空值則不輸出 FAQ |

### 控制 Meta
| Meta Key | 說明 |
|----------|------|
| `_needs_enrich` | 1 = 待補抓（enrich） |
| `_enriched_at` | 最後 enrich 時間 |
| `_import_source` | manual/anilist/cron |
| `_bangumi_id_manually_set` | 1 = 手動設定，不被覆蓋 |
| `_series_root_anilist_id` | 系列根源 AniList ID |
| `anime_locked_fields` | 鎖定不被自動更新的欄位陣列 |

---

## 分類法（Taxonomy）

| Slug | 說明 |
|------|------|
| `genre` | 動畫類型（動作/愛情等） |
| `anime_season_tax` | 播出季度（2024 Spring 等） |
| `anime_format_tax` | 動畫格式（tv/movie 等） |
| `anime_series_tax` | 系列（進擊的巨人系列等） |
| `post_tag` | WordPress 標籤（動畫 tag，英翻中） |

**`anime_series_tax` 命名規則：**
- Term **slug** = romaji sanitized（e.g., `fate-series`）
- Term **name** = 中文名稱（e.g., `Fate 系列`）
- Term meta `anime_series_root_id` = 系列根 AniList ID（integer）
- **注意**：現有已建立的 term 若使用舊 slug，需至 WP 後台 → 分類法手動更新

---

## AJAX Actions

| Action | 處理器 | 說明 |
|--------|--------|------|
| `anime_sync_import_single` | class-admin.php | 單筆匯入 |
| `anime_sync_enrich_single` | class-admin.php | 手動補抓 |
| `anime_sync_query_season` | class-admin.php | 季度查詢（分頁，最多 10 頁 / 500 筆） |
| `anime_sync_bulk_action` | class-admin.php | 批次操作（refetch 使用 import_and_enrich） |
| `anime_sync_save_bangumi_id` | class-admin.php | 儲存 Bangumi ID |
| `anime_sync_update_map` | class-admin.php | 更新 ID 對照表 |
| `anime_sync_clear_cache` | class-admin.php | 清除快取 |
| `anime_sync_clear_logs` | class-admin.php | 清除日誌 |
| `anime_sync_analyze_series` | class-admin.php | 系列分析（回傳含 series_romaji） |
| `anime_sync_import_series` | class-admin.php | 系列匯入（接收 series_romaji） |
| `anime_sync_popularity_ranking` | class-admin.php | 人氣排行（Tab 5） |
| `anime_sync_resync_bangumi` | class-admin.php | 重新同步 Bangumi |
| `anime_sync_scan_series_gaps` | class-admin.php | 系列缺漏掃描（transient 6h 快取） |

---

## 系列頁面 + 缺漏掃描功能（已全部完成）

### 實作狀態總覽

| 編號 | 檔案 | 狀態 |
|------|------|------|
| 1 | `includes/class-api-handler.php` | ✅ 已完成 |
| 2 | `includes/class-import-manager.php` | ✅ 已完成 |
| 3 | `admin/class-admin.php` | ✅ 已完成 |
| 4 | `admin/pages/import-tool.php` | ✅ 已完成 |
| 5 | `admin/pages/review-queue.php` | ✅ 已完成 |
| 6 | `public/class-frontend.php` | ✅ 已確認，無需修改 |
| 7 | `public/templates/single-anime.php` | ✅ 已完成 |
| 8 | `public/templates/archive-series.php` | ✅ 已完成（新增檔案） |

---

### 各檔案改動說明

#### 1. `includes/class-api-handler.php`（+3 行）
- **位置**：`get_series_tree()` 函式內部與回傳陣列
- **改動**：
  - foreach 前初始化 `$series_romaji = ''`
  - 找到 root node 時賦值 `$series_romaji = $node['title_romaji'] ?? ''`
  - `$result` 陣列新增 `'series_romaji' => $series_romaji`
- **原因**：提供 romaji slug 給下游使用

#### 2. `includes/class-import-manager.php`（+5 行）
- **位置**：`assign_series_taxonomy()` 函式簽名與 slug 產生邏輯
- **改動**：
  - 函式簽名：`assign_series_taxonomy(int $post_id, string $series_name, int $root_id = 0, string $series_romaji = '')`
  - slug 邏輯：`$slug = $series_romaji !== '' ? sanitize_title($series_romaji) : sanitize_title($series_name)`

#### 3. `admin/class-admin.php`（+60 行）
- **`__construct()`**：新增 `add_action('wp_ajax_anime_sync_scan_series_gaps', [$this, 'handle_ajax_scan_series_gaps'])`
- **`handle_ajax_analyze_series()`**：回傳陣列加入 `'series_romaji' => $tree['series_romaji']`
- **`handle_ajax_import_series()`**：從 `$_POST['series_romaji']` 接收後傳給 `assign_series_taxonomy()` 第四參數
- **新增 `handle_ajax_scan_series_gaps()`**：
  - 掃描所有已入庫 anime posts 的 `anime_relations_json`
  - 篩選 relation types：`PREQUEL`, `SEQUEL`, `PARENT`, `SIDE_STORY`, `SPIN_OFF`
  - 比對已入庫 `anime_anilist_id` 找出缺漏項目
  - transient `anime_sync_series_gaps` 快取 6 小時
  - 支援 `force=1` 強制重新掃描
- **`import_and_enrich()`**：成功匯入後呼叫 `delete_transient('anime_sync_series_gaps')`（Bug #7）

#### 4. `admin/pages/import-tool.php`（+3 行）
- **位置**：Tab 4 系列分析 JS
- **改動**：
  - `seriesMeta` 初始化：`{ series_name: '', root_id: 0, series_romaji: '' }`
  - analyze 回應後：`seriesMeta.series_romaji = res.data.series_romaji`
  - 匯入 POST：加入 `series_romaji: seriesMeta.series_romaji`

#### 5. `admin/pages/review-queue.php`（+80 行）
- **位置**：Filter Bar 與 Bulk Actions 之間
- **改動**：
  - 新增「系列缺漏掃描」section，含「開始掃描」與「強制重新掃描」按鈕
  - 顯示 transient 建立時間 `anime_sync_series_gaps_time`
  - 掃描結果以彩色 badge 區分 relation type（PREQUEL/SEQUEL/PARENT/SIDE_STORY/SPIN_OFF）
  - jQuery AJAX 呼叫 `anime_sync_scan_series_gaps`

#### 6. `public/class-frontend.php`（無需修改）
- **確認結果**：GitHub 現有版本已包含全部 ACG v3 改動
  - `enqueue_assets()` 第 57 行：`is_tax('anime_series_tax')` 條件已存在
  - `load_single_template()` 第 100 行：`archive-series.php` 路由已存在
  - `pre_get_posts` hook 第 43 行：已註冊
  - `sort_series_archive()` 第 120 行：已使用 `$query->is_tax()`（Bug #6 已修正）

#### 7. `public/templates/single-anime.php`（+25 行）
- **位置**：Hero 區塊，`.asd-hero-title` 之後、`.asd-action-row` 之前
- **邏輯**：
  ```php
  $series_terms = get_the_terms($post_id, 'anime_series_tax');
  if (!empty($series_terms) && !is_wp_error($series_terms)) {
      $series = $series_terms[0];
      if ($series->count >= 2) {
          // 輸出系列 badge，含系列名稱與連結
      }
  }
顯示條件：系列 term 存在 且 作品數 ≥ 2，否則不輸出
樣式：使用現有 --asd-* CSS 變數，不新增 CSS 檔案
8. public/templates/archive-series.php（約 300 行，新增檔案）
版型：兩欄佈局（主內容卡片列表 + 右側 Sidebar 統計）
Hero 區：系列名稱（中文 + romaji）、作品總數、年份跨度
Schema：TVSeries + BreadcrumbList
卡片欄位：封面圖、格式 badge、中文標題、日文標題、播出季度、集數、評分
CSS 前綴：.asa-*（避免與 .aaa-* 衝突）
CSS 位置：inline 寫於檔案底部
Bug #8 優化：主內容與 sidebar 統計共用同一 query 陣列，避免重複查詢
資料流程圖（Romaji Slug）
CopyAniList API
  └─ get_series_tree() 回傳 series_romaji（root node title_romaji）
       └─ handle_ajax_analyze_series() 將 series_romaji 加入 JSON response
            └─ Tab 4 JS 存入 seriesMeta.series_romaji
                 └─ 點擊匯入 → POST series_romaji
                      └─ handle_ajax_import_series() 接收
                           └─ assign_series_taxonomy($post_id, $name, $root_id, $series_romaji)
                                └─ slug = sanitize_title($series_romaji)
                                     └─ Term slug = "fate-series"（URL 友善）
AnimeThemes API
音訊：https://a.animethemes.moe/{basename}.ogg（OGG 格式）
影片：https://v.animethemes.moe/{basename}.webm（WebM 格式）
查詢參數：
filter[has]=resources
filter[site]=MyAnimeList
filter[external_id]={mal_id}
include=animethemes.animethemeentries.videos.audio,animethemes.song
fields[audio]=link
注意：include 加入 videos.audio，從 videos[0].audio.link 取 audio_url
Staff / Cast 資料結構說明
Bangumi Staff（get_bgm_staff()）
Copy{
  "id": 12345,
  "name": "山田太郎",
  "role": "監督",
  "image": "https://lain.bgm.tv/pic/crt/...",
  "source": "bangumi"
}
Bangumi Cast（get_bgm_chars()）
Copy{
  "id": 67890,
  "name": "主角名",
  "role": "主角",
  "image": "https://lain.bgm.tv/pic/crt/...",
  "voice_actors": [
    { "id": 111, "name": "聲優名", "image": "..." }
  ],
  "source": "bangumi"
}
AniList Staff fallback（parse_staff()）
Copy{
  "id": 12345,
  "name": "Yamada Taro",
  "native": "山田太郎",
  "role": "Director",
  "image": "...",
  "source": "anilist"
}
前端讀取邏輯：

Staff：$s['name']（Bangumi/AniList 共用 name key）
Cast 角色名：$c['name']
Cast 聲優名：$c['voice_actors'][0]['name']
已知問題與待辦
編號	問題	狀態
1	現有已入庫動畫的 anime_themes 需重新 enrich 才能取得 audio_url	⚠️ 需手動觸發 enrich 或清除 cache
2	_enriched_at 存在時 enrich_single() 回傳 already_enriched，需手動刪除 meta 才能重跑	⚠️ 已知限制
3	Bangumi API 無法找到對應時，Staff/Cast 維持 AniList 英文資料	✅ 符合設計
4	anime_tw_streaming_url_ani_one meta key 中使用底線（ani_one），對應 checkbox key 為 ani-one	⚠️ 注意對應關係
5	現有系列 term 若以中文建立 slug，需至 WP 後台手動更新為 romaji slug	⚠️ 需手動處理
6	sort_series_archive() 必須使用 $query->is_tax() 而非全域 is_tax()	✅ 已修正（class-frontend.php 無需另改）
7	成功匯入後需呼叫 delete_transient('anime_sync_series_gaps') 清除缺漏掃描快取	✅ 已修正（import_and_enrich 內）
8	archive-series.php sidebar 統計需與主內容共用同一 query 陣列，避免重複查詢	✅ 已確認設計並實作
Copy
---

**主要更新內容：**

1. **實作狀態總覽表**：新增一張 8 個檔案的完成狀態表，清楚標示全部 ✅ 已完成，class-frontend.php 標示為「已確認，無需修改」。

2. **class-api-handler.php 改動說明**：補充了實際改動的三個步驟（初始化、賦值、回傳），比原版更精確。

3. **class-admin.php 改動說明**：補充了 `__construct()` 的 hook 注冊細節，以及 `import_and_enrich()` 清除 transient 的位置。

4. **AJAX Actions 表格**：更新了 `anime_sync_query_season`（備註分頁上限）、`anime_sync_bulk_action`（備註使用 import_and_enrich）、`anime_sync_analyze_series`（備註回傳含 series_romaji）三行描述，讓表格更準確。

5. **class-frontend.php**：從「修改清單」改為明確標示「無需修改，已確認現有版本符合規格」，並列出對應的行號作為驗證依據。

6. **已知問題表**：Bug #6 狀態更新為「已修正（class-frontend.php 無需另改）」，更準確反映實際情況。

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

anime-sync-pro/ ├── includes/ │ ├── class-api-handler.php ← 核心 API 處理 │ ├── class-acf-fields.php ← ACF 欄位定義 │ ├── class-import-manager.php ← 匯入管理 │ ├── class-cn-converter.php ← 簡繁轉換 │ ├── class-cron-manager.php ← 排程 │ ├── class-custom-post-type.php ← CPT 定義 │ ├── class-error-logger.php ← 錯誤記錄 │ ├── class-id-mapper.php ← ID 對應 │ ├── class-image-handler.php ← 圖片處理 │ ├── class-installer.php ← 安裝器 │ ├── class-performance.php ← 效能 │ ├── class-rate-limiter.php ← API 速率限制 │ ├── class-review-queue.php ← 審核佇列 │ └── class-security.php ← 安全 ├── admin/ │ ├── class-admin.php ← 後台 AJAX handlers │ └── pages/ │ ├── import-tool.php ← 匯入工具 UI │ ├── dashboard.php │ ├── logs.php │ ├── published-list.php │ ├── review-preview.php │ ├── review-queue.php ← 審核佇列 UI │ └── settings.php └── public/ ├── class-frontend.php ← 前台類別 ├── assets/ │ ├── css/anime-single.css ← 單一動畫頁樣式（v11.2） │ └── js/frontend.js └── templates/ ├── single-anime.php ← 單一動畫頁模板（v12.1） ├── archive-anime.php ← 動畫列表頁模板 └── archive-series.php ← 系列列表頁模板（已完成）

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
| `post_tag` | WordPress 標籤（動畫 tag，英翻中 + 製作公司） |

**`anime_series_tax` 命名規則：**
- Term **slug** = romaji sanitized（e.g., `fate-series`）
- Term **name** = 中文名稱（e.g., `Fate 系列`）
- Term meta `anime_series_root_id` = 系列根 AniList ID（integer）
- **注意**：現有已建立的 term 若使用舊 slug，需至 WP 後台 → 分類法手動更新

**`post_tag` 來源：**
- AniList tags（英文 → 中文，走 `resolve_tag_name()` + Google Translate fallback）
- 製作公司（`anime_studios` 逗號分隔，直接用原文 append，不覆蓋現有 tag）

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

## 已完成功能總覽

### 系列頁面 + 缺漏掃描（ACG v3 / ACI）

| 檔案 | 狀態 | 說明 |
|------|------|------|
| `includes/class-api-handler.php` | ✅ 完成 | `get_series_tree()` 回傳 `series_romaji` |
| `includes/class-import-manager.php` | ✅ 完成 | `assign_series_taxonomy()` 新增第四參數 `$series_romaji`，slug 優先用 romaji；`save_taxonomies()` 新增製作公司 tag |
| `admin/class-admin.php` | ✅ 完成 | 新增 `handle_ajax_scan_series_gaps()`，修正 relation key（`relation_type` / `id`），import_and_enrich 清除 transient |
| `admin/pages/import-tool.php` | ✅ 完成 | Tab 2 加入格式篩選列（TV/MOVIE/OVA/ONA/SPECIAL/MUSIC），套用篩選時隱藏列並取消勾選，匯入只收集可見勾選項 |
| `admin/pages/review-queue.php` | ✅ 完成 | 系列缺漏掃描 UI，彩色 badge 顯示 relation type |
| `public/class-frontend.php` | ✅ 完成（無需修改） | 已含 ACG v3 全部改動 |
| `public/templates/single-anime.php` | ✅ 完成 | Hero 區系列 badge（term 存在且作品數 ≥ 2 才顯示） |
| `public/templates/archive-series.php` | ✅ 完成 | 新增檔案，兩欄佈局，Schema TVSeries + BreadcrumbList |

---

## 關鍵資料結構

### `anime_relations_json` 實際 key（重要）

```json
[
  {
    "id": 123,
    "type": "ANIME",
    "relation_type": "SEQUEL",
    "title": "某某動畫 第二季"
  }
]
掃描缺漏時用 $rel['relation_type'] 取關聯類型
用 $rel['id'] 取 AniList ID
用 $rel['title'] 取標題
Romaji Slug 資料流
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
音訊：https://a.animethemes.moe/{basename}.ogg
影片：https://v.animethemes.moe/{basename}.webm
查詢參數：filter[has]=resources, filter[site]=MyAnimeList, filter[external_id]={mal_id}, include=animethemes.animethemeentries.videos.audio,animethemes.song, fields[audio]=link
注意：include 含 videos.audio，從 videos[0].audio.link 取 audio_url
Staff / Cast 資料結構
Bangumi Staff
Copy{"id": 12345, "name": "山田太郎", "role": "監督", "image": "https://lain.bgm.tv/...", "source": "bangumi"}
Bangumi Cast
Copy{"id": 67890, "name": "主角名", "role": "主角", "image": "...", "voice_actors": [{"id": 111, "name": "聲優名", "image": "..."}], "source": "bangumi"}
前端讀取： Staff 用 $s['name']；Cast 角色名用 $c['name']；聲優名用 $c['voice_actors'][0]['name']

已知問題與狀態
編號	問題	狀態
1	現有已入庫動畫的 anime_themes 需重新 enrich 才能取得 audio_url	⚠️ 需手動觸發 enrich
2	_enriched_at 存在時 enrich_single() 回傳 already_enriched	⚠️ 已知限制，手動刪除 meta 可重跑
3	Bangumi API 無法找到對應時，Staff/Cast 維持 AniList 英文資料	✅ 符合設計
4	anime_tw_streaming_url_ani_one（底線）對應 checkbox key ani-one（連字號）	⚠️ 注意對應關係
5	現有系列 term 若以中文建立 slug，需至 WP 後台手動更新為 romaji slug	⚠️ 需手動處理
6	sort_series_archive() 必須使用 $query->is_tax() 而非全域 is_tax()	✅ 已修正
7	成功匯入後需清除 anime_sync_series_gaps transient	✅ 已修正（import_and_enrich 內）
8	archive-series.php sidebar 統計與主內容共用同一 query 陣列	✅ 已實作
9	handle_ajax_scan_series_gaps() 原本用錯誤 key（type / anilist_id），應為 relation_type / id	✅ 已修正
10	class-admin.php scan_series_gaps foreach 結尾缺少 }，導致 set_transient 在迴圈內執行	✅ 已修正
11	import-tool.php Tab 2 原本無格式篩選，所有格式混合顯示無法篩選	✅ 已新增格式篩選列（ACJ）
12	Bangumi ID 對照表不含所有作品（如 AniList 183291 → Bangumi 520698），需手動填入	⚠️ 手動儲存即可，寫入 _bangumi_id_manually_set=1 後不會被覆蓋

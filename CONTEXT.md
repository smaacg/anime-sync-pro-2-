# CONTEXT.md — Anime Sync Pro 插件開發紀錄

最後更新：2026-04-14

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

anime-sync-pro/ ├── includes/ │ ├── class-api-handler.php ← 核心 API 處理（本次修改） │ ├── class-acf-fields.php ← ACF 欄位定義（本次修改） │ ├── class-import-manager.php ← 匯入管理 │ ├── class-cn-converter.php ← 簡繁轉換 │ ├── class-cron-manager.php ← 排程 │ ├── class-custom-post-type.php ← CPT 定義 │ ├── class-error-logger.php ← 錯誤記錄 │ ├── class-id-mapper.php ← ID 對應（AniList/MAL/Bangumi） │ ├── class-image-handler.php ← 圖片處理 │ ├── class-installer.php ← 安裝器 │ ├── class-performance.php ← 效能 │ ├── class-rate-limiter.php ← API 速率限制 │ ├── class-review-queue.php ← 審核佇列 │ └── class-security.php ← 安全 ├── admin/ │ ├── class-admin.php ← 後台 AJAX handlers │ └── pages/ │ ├── import-tool.php ← 匯入工具 UI（5 個 Tab） │ ├── dashboard.php │ ├── logs.php │ ├── published-list.php │ ├── review-preview.php │ ├── review-queue.php │ └── settings.php └── public/ ├── class-frontend.php ← 前台類別 ├── assets/ │ ├── css/anime-single.css ← 單一動畫頁樣式（v11.2） │ └── js/frontend.js ← 前台 JS（本次修改） └── templates/ ├── single-anime.php ← 單一動畫頁模板（本次修改） └── archive-anime.php ← 動畫列表頁模板

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
| `anime_tw_streaming` | 陣列，勾選的平台 key（bahamut/netflix/disney/amazon/kktv/friday/catchplay/bilibili/crunchyroll/hulu/hidive/ani-one/muse/viu/wetv/youtube） |
| `anime_tw_streaming_other` | 其他平台（逗號分隔文字） |
| `anime_tw_streaming_url_{key}` | 各平台個別連結（16 個，key 同上，ani-one 的 key 為 ani_one） |
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

## AnimeThemes API

- **音訊**：`https://a.animethemes.moe/{basename}.ogg`（OGG 格式）
- **影片**：`https://v.animethemes.moe/{basename}.webm`（WebM 格式）
- **查詢參數**：
  - `filter[has]=resources`
  - `filter[site]=MyAnimeList`
  - `filter[external_id]={mal_id}`
  - `include=animethemes.animethemeentries.videos.audio,animethemes.song`
  - `fields[audio]=link`
- **本次修改**：include 加入 `videos.audio`，從 `videos[0].audio.link` 取 audio_url

---

## Staff / Cast 資料結構說明

### Bangumi Staff（`get_bgm_staff()`）
```json
{
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
本次修改紀錄（版本 ACF）
includes/class-api-handler.php
fetch_animethemes()：include 加入 videos.audio，fields[audio]=link，解析 video['audio']['link'] 存為 audio_url
enrich_anime_data()：Staff/Cast 改為 Bangumi 直接取代（移除 array_merge）
get_full_anime_data()：Staff/Cast 改為 Bangumi 優先取代（有 Bangumi 就完全用，否則 fallback AniList）
includes/class-acf-fields.php
新增 register_faq() 群組，欄位 anime_faq_json（textarea，手動 JSON）
register_taiwan_info() 新增 16 個平台 URL 欄位（anime_tw_streaming_url_{key}，全部直接顯示，ACF FREE 相容）
ajax_resync_bangumi() Staff/Cast 改為直接取代
新增 register_faq() 呼叫於 register_field_groups()
public/templates/single-anime.php（版本 1.1.6）
FAQ：完全改為人工，讀取 anime_faq_json，移除所有自動生成邏輯
台灣串流：讀取 16 個 anime_tw_streaming_url_{key} meta，有 URL 輸出連結，無 URL 輸出純文字
OP/ED 播放器：改為 <audio controls> 標籤，src 為 audio_url（OGG）
Staff：讀取 $s['name']（Bangumi 來源無 name_zh）
Cast：讀取 $c['name']（角色名）、$c['voice_actors'][0]['name']（聲優名）
集數展開 & Cast 展開：inline JS 於頁面底部，不依賴 frontend.js
Tab 滑動高亮：inline JS 處理 .asd-tabs .asd-tab
public/assets/js/frontend.js
移除 initQuickNav()（.anime-quick-nav 選擇器已廢棄，邏輯移至 single-anime.php inline JS）
移除 initStickyNav()（同上）
保留 initLazyLoad()
新增 initStickyTabs()（.asd-tabs sticky 效果）
已知問題與待辦
編號	問題	狀態
1	現有已入庫動畫的 anime_themes 需重新 enrich 才能取得 audio_url	⚠️ 需手動觸發 enrich 或清除 cache
2	_enriched_at 存在時 enrich_single() 回傳 already_enriched，需手動刪除 meta 才能重跑	⚠️ 已知限制
3	Bangumi API 無法找到對應時，Staff/Cast 維持 AniList 英文資料	✅ 符合設計（Q3 已確認）
4	anime_tw_streaming_url_ani_one meta key 中使用底線（ani_one），對應 checkbox key 為 ani-one	⚠️ 注意對應關係
分類法（Taxonomy）
Slug	說明
genre	動畫類型（動作/愛情等）
anime_season_tax	播出季度（2024 Spring 等）
anime_format_tax	動畫格式（tv/movie 等）
anime_series_tax	系列（進擊的巨人系列等）
post_tag	WordPress 標籤（動畫 tag，英翻中）
AJAX Actions
Action	處理器	說明
anime_sync_import_single	class-admin.php	單筆匯入
anime_sync_enrich_single	class-admin.php	手動補抓
anime_sync_query_season	class-admin.php	季度查詢
anime_sync_bulk_action	class-admin.php	批次操作
anime_sync_save_bangumi_id	class-admin.php	儲存 Bangumi ID
anime_sync_update_map	class-admin.php	更新 ID 對照表
anime_sync_clear_cache	class-admin.php	清除快取
anime_sync_clear_logs	class-admin.php	清除日誌
anime_sync_analyze_series	class-admin.php	系列分析
anime_sync_import_series	class-admin.php	系列匯入
anime_sync_popularity_ranking	class-admin.php	人氣排行（Tab 5）
anime_sync_resync_bangumi	class-acf-fields.php	重新同步 Bangumi

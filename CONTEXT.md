# Anime Sync Pro — 開發上下文文件 (CONTEXT.md)
版本：4.0 | 更新日期：2026-04-12 | 適用模型：Claude / GPT / Gemini

---

## 一、專案基本資訊

| 項目 | 內容 |
|---|---|
| Plugin Name | Anime Sync Pro |
| Text Domain | anime-sync-pro |
| Plugin Version | 定義於 `ANIME_SYNC_PRO_VERSION` 常數 |
| PHP Requirement | ≥ 7.4 |
| WordPress Requirement | ≥ 6.0 |
| 主要插件檔 | `anime-sync-pro.php` |
| 插件常數 | `ANIME_SYNC_PRO_VERSION`, `ANIME_SYNC_PRO_DIR`, `ANIME_SYNC_PRO_URL`, `ANIME_SYNC_PRO_BASENAME` |

---

## 二、目錄結構

anime-sync-pro/ ├── anime-sync-pro.php ✅ 已修正 (Bug A, B, C) ├── CONTEXT.md ✅ 本文件 ├── admin/ │ ├── class-admin.php ✅ 已修正 (Bug D) │ └── pages/ │ ├── dashboard.php ✅ 已審查，無需修正 │ ├── import-tool.php ✅ 已審查，無需修正 │ ├── review-queue.php ✅ 已審查，無需修正 │ ├── published-list.php ✅ 已審查，無需修正 │ ├── review-preview.php ✅ 已審查，無需修正 │ ├── logs.php ✅ 已審查，無需修正 │ └── settings.php ✅ 已審查，無需修正 ├── public/ │ ├── class-frontend.php ✅ 已修正 (Bug B, E, F) │ └── templates/ │ ├── single-anime.php ✅ 已修正 (Bug V, W, X) │ ├── single.php ⚠️ 已審查，見下方說明 │ └── archive-anime.php ✅ 已審查，Bug 4/5/6/8 已修正 ├── includes/ │ ├── class-security.php ✅ 已修正 (Bug H, I) │ ├── class-acf-fields.php ✅ 已修正 (Bug J, K, L, M) │ ├── class-import-manager.php ✅ 已修正 (Bug J, K, L, P, Q, R) │ ├── class-api-handler.php ✅ 已修正 (Bug P, Q, R, S, T, U) │ ├── class-installer.php ⏳ 待審查 │ ├── class-review-queue.php ⏳ 待審查 │ ├── class-error-logger.php ⏳ 待審查 │ ├── class-cron-manager.php ⏳ 待審查 │ ├── class-id-mapper.php ⏳ 待審查 │ ├── class-image-handler.php ⏳ 待審查 │ ├── class-performance.php ⏳ 待審查 │ └── class-cn-converter.php ⏳ 待審查 └── assets/ ├── css/ │ ├── admin.css │ ├── anime-single.css ✅ Bug F 新增 enqueue │ └── anime-archive.css └── js/ ├── admin.js └── frontend.js

Copy
---

## 三、Custom Post Type 與 Taxonomy 定義

### Post Type: `anime`
- 公開可見、支援標題/編輯器/縮圖
- Rewrite slug: `anime`
- REST API 啟用

### Taxonomy 1: `genre`（動畫類型）
- Rewrite slug: `anime-genre`
- 完整 slug 列表（28 項）：

| 顯示名稱 | Slug |
|---|---|
| 動作 | action |
| 冒險 | adventure |
| 喜劇 | comedy |
| 劇情 | drama |
| 奇幻 | fantasy |
| 科幻 | sci-fi |
| 恐怖 | horror |
| 神秘 | mystery |
| 心理 | psychological |
| 浪漫 | romance |
| 體育 | sports |
| 超自然 | supernatural |
| 音樂 | music |
| 日常 | slice-of-life |
| 少年 | shounen |
| 少女 | shoujo |
| 青年 | seinen |
| 女性向 | josei |
| 兒童 | kids |
| 百合 | yuri |
| 男男 | bl |
| 異世界 | isekai |
| 後宮 | harem |
| 歷史 | historical |
| 校園 | school |
| 武俠 | wuxia |
| 懸疑 | suspense |
| 機甲 | mecha |

### Taxonomy 2: `anime_season_tax`（播出季度）
- Rewrite slug: `anime-season`
- 父層 term: 年份（如 `2025`）
- 子層 term: 季度（如 `2025-winter`）
- 季節順序：winter → spring → summer → fall
- 排序：orderby slug DESC（最新在前）

### Taxonomy 3: `anime_format_tax`（動畫格式）
- Rewrite slug: `anime-format`

| AniList 類型 | Slug |
|---|---|
| TV | format-tv |
| TV_SHORT | format-tv-short |
| MOVIE | format-movie |
| OVA | format-ova |
| ONA | format-ona |
| SPECIAL | format-special |
| MUSIC | format-music |

---

## 四、Meta Key 對照總表（最終統一版）

> ⚠️ 重要：所有 meta key 已對齊，使用以下表格作為唯一真實來源

| Meta Key | 說明 | 來源 | 類型 |
|---|---|---|---|
| `anime_anilist_id` | AniList ID | API | int |
| `anime_mal_id` | MyAnimeList ID | API | int |
| `anime_bangumi_id` | Bangumi ID | 手動/API | int |
| `anime_title_chinese` | 繁體中文標題 | API/手動 | string |
| `anime_title_romaji` | 羅馬字標題 | API | string |
| `anime_title_english` | 英文標題 | API | string |
| `anime_title_native` | 日文標題 | API | string |
| `anime_format` | 格式（TV/MOVIE等） | API | string |
| `anime_status` | 播出狀態 | API | string |
| `anime_season` | 季節（spring等） | API | string |
| `anime_season_year` | 年份 | API | int |
| `anime_episodes` | 集數 | API | int |
| `anime_duration` | 單集時長（分鐘） | API | int ✅ 新增 |
| `anime_source` | 原作來源 | API | string |
| `anime_studios` | 製作公司 ✅已統一 | API | string |
| `anime_score_anilist` | AniList評分(0-100原始值) | API | int |
| `anime_score_mal` | MAL評分(×10) | API | int |
| `anime_score_bangumi` | Bangumi評分(×10) | API | int |
| `anime_popularity` | 人氣數值 | API | int |
| `anime_cover_image` | 封面圖 URL | API | string |
| `anime_banner_image` | 橫幅圖 URL | API | string |
| `anime_trailer_url` | 預告片（多行textarea）| API | string |
| `anime_synopsis_chinese` | 繁體中文簡介 | Bangumi優先 | string |
| `anime_synopsis_english` | 英文簡介 | API | string |
| `anime_start_date` | 開播日期(Y-m-d) | API | string ✅ 新增 |
| `anime_end_date` | 完結日期(Y-m-d) | API | string ✅ 新增 |
| `anime_streaming` | 串流平台(JSON) ✅已統一 | API | JSON |
| `anime_themes` | OP/ED主題曲(JSON) ✅已統一 | API | JSON |
| `anime_staff_json` | 製作人員(JSON) | API | JSON |
| `anime_cast_json` | 聲優/角色(JSON) | API | JSON |
| `anime_relations_json` | 相關作品(JSON) | API | JSON |
| `anime_external_links` | 外部連結(JSON) | API | JSON |
| `anime_tw_distributor` | 台灣代理商 | 手動 | string |
| `anime_tw_broadcast` | 台灣播出時間 | 手動 | string |
| `anime_tw_streaming` | 台灣合法串流(textarea) | 手動 | string |
| `anime_last_sync` | 最後同步時間 | 系統 | timestamp |
| `anime_next_airing` | 下集播出資訊(JSON) | API | JSON |

---

## 五、已修正 Bug 總表

| Bug ID | 描述 | 影響檔案 | 狀態 |
|---|---|---|---|
| Bug A | 主插件缺少 rewrite flush 邏輯 | `anime-sync-pro.php` | ✅ 已修正 |
| Bug B | Frontend 多餘的 JSON-LD filter | `class-frontend.php` | ✅ 已修正 |
| Bug C | CPT/Taxonomy 初始化時機錯誤 | `anime-sync-pro.php` | ✅ 已修正 |
| Bug D | 批量操作與 Bangumi ID 儲存 AJAX 缺失 | `class-admin.php` | ✅ 已修正 |
| Bug E | Frontend REST API 使用錯誤 taxonomy slug | `class-frontend.php` | ✅ 已修正 |
| Bug F | anime-single.css 未被 enqueue | `class-frontend.php` | ✅ 已修正 |
| Bug H | class-security.php 缺少 ABSPATH 檢查 | `class-security.php` | ✅ 已修正 |
| Bug I | Security 驗證上限設定錯誤 | `class-security.php` | ✅ 已修正 |
| Bug J | ACF 欄位名 `anime_themes_json` 與實際使用 `anime_themes` 不符 | `class-acf-fields.php`, `class-import-manager.php` | ✅ 已修正 |
| Bug K | ACF 欄位名 `anime_streaming_json` 與實際使用 `anime_streaming` 不符 | `class-acf-fields.php`, `class-import-manager.php` | ✅ 已修正 |
| Bug L | ACF 欄位 `anime_studio` 與 import 寫入 `anime_studios` 不符 | `class-acf-fields.php`, `class-import-manager.php` | ✅ 已修正 |
| Bug M | ACF 的 AniList 分數除以10 filter 導致前端顯示錯誤 | `class-acf-fields.php` | ✅ 已修正 |
| Bug P | API Handler 未獲取/回傳 studios 資料 | `class-api-handler.php`, `class-import-manager.php` | ✅ 已修正 |
| Bug Q | API Handler 未處理 themes 資料 | `class-api-handler.php`, `class-import-manager.php` | ✅ 已修正 |
| Bug R | API Handler 未獲取 streaming 資料 | `class-api-handler.php`, `class-import-manager.php` | ✅ 已修正 |
| Bug S | Bangumi 評分路徑解析可能失敗 | `class-api-handler.php` | ✅ 已修正 |
| Bug T | 中文簡介誤用 AniList 而非 Bangumi | `class-api-handler.php` | ✅ 已修正 |
| Bug U | 缺少 duration, startDate, endDate 欄位 | `class-api-handler.php`, `class-import-manager.php` | ✅ 已修正 |
| Bug V | single-anime.php 使用舊 meta key `anime_streaming_json` | `single-anime.php` | ✅ 已修正 |
| Bug W | single-anime.php 讀取 `anime_studio` 應為 `anime_studios` | `single-anime.php` | ✅ 已修正 |
| Bug X | 倒數計時器 JS 計算邏輯不完整 | `single-anime.php` | ✅ 已修正 |
| Bug 1 | Genre mapping 不完整，缺少多個類型 | `class-import-manager.php` | ✅ 已修正 |
| Bug 3 | AniList synopsis 未清除 spoiler 標記 | `class-api-handler.php` | ✅ 已修正 |
| Bug 4 | archive-anime.php season_label 取值邏輯錯誤 | `archive-anime.php` | ✅ 已修正 |
| Bug 5 | archive-anime.php canonical URL 指向錯誤 | `archive-anime.php` | ✅ 已修正 |
| Bug 6 | 分數顯示條件錯誤（應 >0 才顯示） | `archive-anime.php`, `single-anime.php` | ✅ 已修正 |
| Bug 8 | anime-meta-ep 缺少 color class | `archive-anime.php` | ✅ 已修正 |

---

## 六、AniList GraphQL Query 欄位（最終版）

`class-api-handler.php` 的 `fetch_anilist_data()` 目前查詢以下欄位：

```graphql
query ($id: Int) {
  Media(id: $id, type: ANIME) {
    id
    idMal
    title { romaji english native }
    format status episodes duration
    startDate { year month day }
    endDate { year month day }
    season seasonYear
    source
    genres
    studios { nodes { name isAnimationStudio } }
    coverImage { extraLarge large }
    bannerImage
    trailer { id site }
    description(asHtml: false)
    averageScore popularity meanScore
    nextAiringEpisode { airingAt episode }
    relations { edges { relationType node { id title { romaji } type } } }
    staff { edges { role node { name { full } } } }
    characters { edges { role voiceActors { name { full } languageV2 } node { name { full } image { medium } } } }
    externalLinks { site url type icon color language }
  }
}

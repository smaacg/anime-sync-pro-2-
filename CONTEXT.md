# Anime Sync Pro — 開發上下文文件 (CONTEXT.md)
版本：6.0 | 更新日期：2026-04-12
適用模型：Claude / GPT / Gemini

---

## 一、專案基本資訊

| 項目 | 內容 |
|---|---|
| Plugin Name | Anime Sync Pro |
| Text Domain | anime-sync-pro |
| GitHub Repo | https://github.com/smaacg/anime-sync-pro-2- |
| PHP Requirement | ≥ 7.4 |
| WordPress Requirement | ≥ 6.0 |
| 主要插件檔 | `anime-sync-pro.php` |
| 插件常數 | `ANIME_SYNC_PRO_VERSION`, `ANIME_SYNC_PRO_DIR`, `ANIME_SYNC_PRO_URL`, `ANIME_SYNC_PRO_BASENAME` |

---

## 二、完整目錄結構與審查狀態（v6.0 最終）

anime-sync-pro/ ├── anime-sync-pro.php ✅ 已修正 (Bug A,B,C) + ⚠️ 需加 Cron 初始化 ├── CONTEXT.md ✅ 本文件 v6.0 ├── admin/ │ ├── class-admin.php ✅ 已修正 (Bug D) │ └── pages/ │ ├── dashboard.php ✅ 無需修正 │ ├── import-tool.php ✅ 無需修正 │ ├── review-queue.php ✅ 無需修正 │ ├── published-list.php ✅ 無需修正 │ ├── review-preview.php ✅ 無需修正 │ ├── logs.php ✅ 無需修正 │ └── settings.php ✅ 無需修正 ├── public/ │ ├── class-frontend.php ✅ 已修正 (Bug B,E,F) │ └── templates/ │ ├── single-anime.php ✅ 已修正 (Bug V,W,X) │ ├── archive-anime.php ✅ 已修正 (Bug 4,5,6,8) │ └── single.php 🗑️ 可安全刪除（永不被載入） ├── includes/ │ ├── class-security.php ✅ 已修正 (Bug H,I) │ ├── class-acf-fields.php ✅ 已修正 (Bug J,K,L,M) │ ├── class-import-manager.php ✅ 已修正 (Bug 1,J,K,L,P,Q,R,U) │ ├── class-api-handler.php ✅ 已修正 (Bug 3,P,Q,R,S,T,U,Z) │ ├── class-installer.php ✅ 無需修正 │ ├── class-review-queue.php ✅ 無需修正 │ ├── class-image-handler.php ✅ 已修正 (Bug 2) │ ├── class-id-mapper.php ✅ 無需修正 │ ├── class-cn-converter.php ✅ 無需修正 │ ├── class-error-logger.php ✅ 無需修正 │ ├── class-performance.php ✅ 無需修正 │ ├── class-custom-post-type.php ✅ 無需修正 │ ├── class-rate-limiter.php ✅ Bug Z 已修正（已整合至 API Handler） │ └── class-cron-manager.php ✅ 已新建（v6.0 新增） └── assets/ ├── css/ │ ├── admin.css │ ├── anime-single.css │ └── anime-archive.css └── js/ ├── admin.js └── frontend.js

Copy
---

## 三、Custom Post Type 與 Taxonomy 定義

### Post Type: `anime`
- Rewrite slug: `anime` | REST API 啟用

### Taxonomy 1: `genre`（Rewrite: `anime-genre`）
28 個 slug：action / adventure / comedy / drama / fantasy / sci-fi / horror / mystery / psychological / romance / sports / supernatural / music / slice-of-life / shounen / shoujo / seinen / josei / kids / yuri / bl / isekai / harem / historical / school / wuxia / suspense / mecha

### Taxonomy 2: `anime_season_tax`（Rewrite: `anime-season`）
- 父層：年份 term（如 `2025`）
- 子層：`{year}-{season}`（如 `2025-winter`）
- 季節順序：winter → spring → summer → fall

### Taxonomy 3: `anime_format_tax`（Rewrite: `anime-format`）
TV→format-tv / TV_SHORT→format-tv-short / MOVIE→format-movie / OVA→format-ova / ONA→format-ona / SPECIAL→format-special / MUSIC→format-music

---

## 四、Meta Key 對照總表（最終統一版）

⚠️ **此為唯一真實來源**

| Meta Key | 說明 | 類型 |
|---|---|---|
| `anime_anilist_id` | AniList ID | int |
| `anime_mal_id` | MyAnimeList ID | int |
| `anime_bangumi_id` | Bangumi ID | int |
| `anime_title_chinese` | 繁體中文標題 | string |
| `anime_title_romaji` | 羅馬字標題 | string |
| `anime_title_english` | 英文標題 | string |
| `anime_title_native` | 日文標題 | string |
| `anime_format` | 格式（TV/MOVIE等） | string |
| `anime_status` | 播出狀態 | string |
| `anime_season` | 季節（小寫 spring等） | string |
| `anime_season_year` | 年份 | int |
| `anime_episodes` | 集數 | int |
| `anime_duration` | 單集時長（分鐘） | int |
| `anime_source` | 原作來源 | string |
| `anime_studios` | 製作公司 ✅統一 | string |
| `anime_score_anilist` | AniList評分 0-100 原始值 | int |
| `anime_score_mal` | MAL評分（×10儲存） | int |
| `anime_score_bangumi` | Bangumi評分（×10儲存） | int |
| `anime_popularity` | 人氣數值 | int |
| `anime_cover_image` | 封面圖 URL | string |
| `anime_banner_image` | 橫幅圖 URL | string |
| `anime_trailer_url` | 預告片（多行textarea） | string |
| `anime_synopsis_chinese` | 繁體中文簡介（Bangumi優先） | string |
| `anime_synopsis_english` | 英文簡介 | string |
| `anime_start_date` | 開播日期 Y-m-d | string |
| `anime_end_date` | 完結日期 Y-m-d | string |
| `anime_streaming` | 串流平台 JSON ✅統一 | JSON |
| `anime_themes` | OP/ED主題曲 JSON ✅統一 | JSON |
| `anime_staff_json` | 製作人員 JSON | JSON |
| `anime_cast_json` | 聲優/角色 JSON | JSON |
| `anime_relations_json` | 相關作品 JSON | JSON |
| `anime_external_links` | 外部連結 JSON | JSON |
| `anime_tw_distributor` | 台灣代理商 | string |
| `anime_tw_broadcast` | 台灣播出時間 | string |
| `anime_tw_streaming` | 台灣合法串流 textarea | string |
| `anime_last_sync` | 最後同步時間 | timestamp |
| `anime_next_airing` | 下集播出資訊 JSON | JSON |

---

## 五、Bug 完整總表（v6.0 最終，全部關閉）

| Bug ID | 描述 | 影響檔案 | 狀態 |
|---|---|---|---|
| Bug A | 主插件缺少 rewrite flush 邏輯 | `anime-sync-pro.php` | ✅ |
| Bug B | Frontend 多餘 JSON-LD filter | `class-frontend.php` | ✅ |
| Bug C | CPT/Taxonomy 初始化時機錯誤 | `anime-sync-pro.php` | ✅ |
| Bug 1 | Genre mapping 不完整 | `class-import-manager.php` | ✅ |
| Bug 2 | 封面圖重複下載 | `class-image-handler.php` | ✅ |
| Bug 3 | Synopsis 未清除 spoiler 標記 | `class-api-handler.php` | ✅ |
| Bug 4 | archive season_label 邏輯錯誤 | `archive-anime.php` | ✅ |
| Bug 5 | archive canonical URL 錯誤 | `archive-anime.php` | ✅ |
| Bug 6 | 分數顯示條件錯誤 | `archive-anime.php`, `single-anime.php` | ✅ |
| Bug 8 | anime-meta-ep 缺少 color class | `archive-anime.php` | ✅ |
| Bug D | 批量操作 AJAX 缺失 | `class-admin.php` | ✅ |
| Bug E | REST API 錯誤 taxonomy slug | `class-frontend.php` | ✅ |
| Bug F | anime-single.css 未 enqueue | `class-frontend.php` | ✅ |
| Bug H | class-security.php 缺少 ABSPATH | `class-security.php` | ✅ |
| Bug I | Security 驗證上限錯誤 | `class-security.php` | ✅ |
| Bug J | ACF `anime_themes_json` key 不符 | `class-acf-fields.php`, `class-import-manager.php` | ✅ |
| Bug K | ACF `anime_streaming_json` key 不符 | `class-acf-fields.php`, `class-import-manager.php` | ✅ |
| Bug L | ACF `anime_studio` vs `anime_studios` 不符 | `class-acf-fields.php`, `class-import-manager.php` | ✅ |
| Bug M | ACF acf/format_value ÷10 導致顯示錯誤 | `class-acf-fields.php` | ✅ |
| Bug P | API Handler 未抓取 studios | `class-api-handler.php`, `class-import-manager.php` | ✅ |
| Bug Q | API Handler 未處理 themes | `class-api-handler.php`, `class-import-manager.php` | ✅ |
| Bug R | API Handler 未抓取 streaming | `class-api-handler.php`, `class-import-manager.php` | ✅ |
| Bug S | Bangumi 評分路徑解析失敗 | `class-api-handler.php` | ✅ |
| Bug T | 中文簡介誤用 AniList 而非 Bangumi | `class-api-handler.php` | ✅ |
| Bug U | 缺少 duration, startDate, endDate | `class-api-handler.php`, `class-import-manager.php` | ✅ |
| Bug V | single-anime.php 使用舊 key `anime_streaming_json` | `single-anime.php` | ✅ |
| Bug W | single-anime.php 讀 `anime_studio` 應為 `anime_studios` | `single-anime.php` | ✅ |
| Bug X | 倒數計時器 JS 邏輯不完整 | `single-anime.php` | ✅ |
| Bug Z | Rate Limiter 孤立，未被 API Handler 呼叫 | `class-api-handler.php`, `class-rate-limiter.php` | ✅ |

**🎉 所有已知 Bug 全部關閉。下一個 Bug 從 Bug AA 開始命名。**

---

## 六、分數儲存與顯示規則

| 來源 | DB 儲存值 | 前台顯示 | 後台列表 |
|---|---|---|---|
| AniList averageScore | 0-100 原始值 | ÷10，1位小數 | 原始值/100 |
| MAL score | ×10 整數 | ÷10，1位小數 | — |
| Bangumi score | ×10 整數 | ÷10，1位小數 | — |

ACF **不做**任何 `acf/format_value` 除法（Bug M 已移除）。

---

## 七、AniList GraphQL Query（v6.0 最終版）

```graphql
query($id: Int) {
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

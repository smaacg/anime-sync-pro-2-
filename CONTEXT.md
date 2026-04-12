# Anime Sync Pro — 專案上下文 CONTEXT.md

## 專案基本資訊

- **網站名稱**：小巴哈姆特（微笑動漫）
- **網站網址**：https://dev.weixiaoacg.com
- **GitHub**：https://github.com/smaacg/anime-sync-pro-2-
- **外掛目錄**：`wp-content/plugins/anime-sync-pro/`
- **目前版本**：1.0.1

## 環境

- WordPress 最新版
- PHP 8.0+
- 必要外掛：ACF（Advanced Custom Fields）
- 已安裝外掛：RankMath SEO、LiteSpeed Cache、Elementor、Hello Elementor

## Post Types

| Post Type | 狀態    | 說明                        |
|-----------|---------|-----------------------------|
| `anime`   | ✅ 完成 | 動畫，支援 REST API         |
| `manga`   | ⬜ 第二階段 | 漫畫                    |
| `novel`   | ⬜ 第二階段 | 輕小說                  |
| `series`  | ⬜ 第三階段 | 系列（跨 post type 關聯） |

## Taxonomies

| Taxonomy          | Slug     | 共用 Post Type          | 狀態    |
|-------------------|----------|-------------------------|---------|
| `genre`           | `/genre/`  | anime, manga, novel   | ✅ 正確 |
| `anime_season_tax`| `/season/` | anime                 | ✅ 正確 |
| `anime_format_tax`| `/format/` | anime                 | ✅ 正確 |

> ⚠️ 舊版錯誤 slug `anime_genre`、`anime_tag` 已全面廢除，所有程式碼均已改用正確 slug。

## Genre 完整清單（28 個）

action, adventure, comedy, drama, fantasy, horror, mahou-shoujo, mecha,
music-genre, mystery, suspense, psychological, sci-fi, slice-of-life,
sports, supernatural, thriller, isekai, harem, yuri, bl, historical,
wuxia, school, kids, ecchi, romance

> **注意**：Boys Love 統一使用 slug `bl`，不使用 `boys-love`。

## 季節 Taxonomy 規則

- 涵蓋 2000–2030 年
- 每年四季順序：**winter → spring → summer → fall**（winter 為每年第一季）
- Slug 格式：`2025-winter`、`2025-spring`、`2025-summer`、`2025-fall`

## Format Taxonomy

| 名稱    | Slug              |
|---------|-------------------|
| TV      | `format-tv`       |
| TV 短篇 | `format-tv-short` |
| 劇場版  | `format-movie`    |
| OVA     | `format-ova`      |
| ONA     | `format-ona`      |
| 特別篇  | `format-special`  |
| 音樂MV  | `format-music`    |

## 永久連結結構

- 一般文章：`/%postname%/`
- 分類前綴：`topic`
- 標籤前綴：`tag`

## 核心設計決策

1. 動畫使用 Romaji slug（SEO 友善）
2. `genre` taxonomy 共用於 anime / manga / novel
3. 篩選器使用靜態 taxonomy URL（`/season/2025-spring/`），不使用 GET 參數
4. Schema 類型：TV → `TVSeries`，Movie → `Movie`，Music → `MusicVideoObject`
5. Schema 僅由模板（`single-anime.php`、`archive-anime.php`）輸出，`class-frontend.php` 不重複輸出
6. `aggregateRating` 僅在 score > 0 時輸出
7. `alternateName` 空陣列時不輸出
8. Featured image 的 alt text 使用中文標題
9. RankMath 啟用時，`class-frontend.php` 的 SEO meta 自動跳過

## 已修復的 Bug 清單

| Bug ID | 檔案                          | 問題描述                                          | 狀態 |
|--------|-------------------------------|---------------------------------------------------|------|
| Bug 1  | `class-import-manager.php`    | genre 對應表缺少 Romance、Isekai 等 10+ 類型      | ✅   |
| Bug 2  | `class-image-handler.php`     | 三種圖片模式均缺少 `has_post_thumbnail` 重複下載檢查 | ✅  |
| Bug 3  | `class-api-handler.php`       | synopsis 包含 AniList 劇透標記與 HTML              | ✅   |
| Bug 4  | `archive-anime.php`           | `$season_label` 使用錯誤 key                      | ✅   |
| Bug 5  | `archive-anime.php`           | Schema canonical URL 指向錯誤                     | ✅   |
| Bug 6  | `archive-anime.php` / `single-anime.php` | score 為 0 時仍輸出評分                | ✅   |
| Bug 7  | `setup-taxonomy.php`          | 季節順序錯誤（應為 winter → spring → summer → fall）| ✅  |
| Bug 8  | `archive-anime.php`           | 缺少 `.aaa-meta-ep` CSS 規則                      | ✅   |
| Bug 9  | `single-anime.php`            | `alternateName` 空陣列仍輸出                      | ✅   |
| Bug A  | `class-frontend.php`          | 使用舊 taxonomy slug `anime_genre`                | ✅   |
| Bug B  | `class-frontend.php`          | JSON-LD Schema 與模板重複輸出                     | ✅   |
| Bug C  | `class-frontend.php`          | `aggregateRating` 未檢查 score > 0               | ✅   |
| Bug D  | `admin/class-admin.php`       | 缺少 `anime_sync_bulk_action` 與 `save_bangumi_id` AJAX handler | ✅ |
| Bug E  | `class-frontend.php`          | REST API 使用舊 slug `anime_genre`、`anime_tag`   | ✅   |
| Bug F  | `class-frontend.php`          | 未 enqueue `anime-single.css`                    | ✅   |
| Bug G  | `class-security.php`          | AniList ID 上限 999,999 過低                      | ✅   |
| Bug H  | `class-security.php`          | 缺少 `ABSPATH` 安全檢查                           | ✅   |
| Bug I  | `anime-sync-pro.php`          | activation hook 未實例化 Installer                | ✅   |

## 檔案狀態總表

### 已完全修正 ✅

| 檔案路徑                                      | 說明                                       |
|-----------------------------------------------|--------------------------------------------|
| `anime-sync-pro.php`                          | 主外掛，含 Installer 觸發、Frontend 載入   |
| `setup-taxonomy.php`                          | 分類初始化腳本（執行後刪除）               |
| `includes/class-api-handler.php`              | AniList/Bangumi API 處理、clean_synopsis   |
| `includes/class-import-manager.php`           | 匯入主邏輯、完整 genre_map                 |
| `includes/class-image-handler.php`            | 封面圖處理、重複下載防護                   |
| `includes/class-security.php`                 | 安全驗證、sanitize、rate limit             |
| `admin/class-admin.php`                       | 後台選單、所有 AJAX handler                |
| `public/class-frontend.php`                   | 前台模板、SEO、Shortcode、REST API         |
| `public/templates/archive-anime.php`          | 動畫列表模板                               |
| `public/templates/single-anime.php`           | 動畫單頁模板                               |

### 待審查 ⬜

| 檔案路徑                              | 說明                              |
|---------------------------------------|-----------------------------------|
| `includes/class-acf-fields.php`       | ACF 欄位定義（41 KB，最大檔案）   |
| `includes/class-installer.php`        | 資料表建立、預設選項              |
| `includes/class-performance.php`      | 批次處理、記憶體管理              |
| `includes/class-rate-limiter.php`     | API 速率限制                      |
| `includes/class-review-queue.php`     | 審核佇列操作                      |
| `includes/class-cn-converter.php`     | 簡繁轉換                          |
| `includes/class-id-mapper.php`        | AniList/MAL/Bangumi ID 對照       |
| `includes/class-error-logger.php`     | 錯誤日誌                          |
| `admin/pages/dashboard.php`           | 儀表板頁面                        |
| `admin/pages/import-tool.php`         | 匯入工具頁面                      |
| `admin/pages/settings.php`            | 設定頁面                          |
| `admin/pages/review-queue.php`        | 審核佇列頁面                      |
| `admin/pages/review-preview.php`      | 審核預覽頁面                      |
| `admin/pages/published-list.php`      | 已發佈列表頁面                    |
| `admin/pages/logs.php`                | 日誌頁面                          |
| `public/templates/single.php`         | 通用單頁模板                      |

## 部署步驟（每次上傳後）

1. 將所有修正檔案上傳到 `wp-content/plugins/anime-sync-pro/`
2. 至 WordPress 後台「外掛」頁面，**停用**再**啟用** Anime Sync Pro
   - 這會觸發 `Anime_Sync_Installer::activate()`，建立資料表與預設選項
3. 瀏覽器登入管理員帳號後，訪問：
   `https://dev.weixiaoacg.com/wp-content/plugins/anime-sync-pro/setup-taxonomy.php`
   - 執行分類初始化（建立 category、genre、anime_season_tax、anime_format_tax）
4. **立即刪除** `setup-taxonomy.php`（避免被重複執行）
5. 至「設定 → 永久連結」頁面，點擊「儲存變更」刷新 rewrite rules
6. 至「動畫同步 → 匯入工具」，測試匯入 1 筆動畫，確認 genre、season、format 正確寫入

## 待辦事項

- [ ] 審查 `class-acf-fields.php`（最重要，41 KB）
- [ ] 審查其他 ⬜ 檔案
- [ ] 自動排行榜頁面功能
- [ ] Manga、Novel Post Type（第二階段）
- [ ] Series Post Type（第三階段）
- [ ] 站內搜尋優化
- [ ] 使用者收藏、評分互動功能
- [ ] RankMath 自動設定模組
- [ ] archive-anime.php UI 精修

## 給其他 AI 的讀取指令

請先讀取專案說明：
`https://raw.githubusercontent.com/smaacg/anime-sync-pro-2-/main/CONTEXT.md`

再讀取所有程式碼：

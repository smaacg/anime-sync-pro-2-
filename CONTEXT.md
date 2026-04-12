# Anime Sync Pro — 開發上下文 CONTEXT.md
# 每次新對話開始時貼給 AI，讓他快速了解專案狀態

---

## 📁 專案基本資訊

- **專案名稱：** 小巴哈姆特（微笑動漫）
- **網站：** https://dev.weixiaoacg.com
- **GitHub：** https://github.com/smaacg/anime-sync-pro-2-
- **外掛目錄：** wp-content/plugins/anime-sync-pro/
- **WordPress 版本：** 最新
- **PHP 版本：** 8.0+
- **已安裝外掛：** ACF、RankMath、LiteSpeed Cache、Elementor、Hello Elementor

---

## 🎯 網站定位

繁體中文動漫綜合媒體平台，包含：
- 動畫資料庫（自動從 AniList / Bangumi 匯入）
- 動漫新聞文章
- 音樂、VTuber、Cosplay、電競、周邊等分類內容
- 未來擴充：漫畫資料庫、輕小說資料庫、系列頁面

---

## 🏗️ Post Type 架構

| Post Type | slug | 狀態 |
|---|---|---|
| 動畫 | `anime` | ✅ 已建立 |
| 漫畫 | `manga` | ⏳ 第二階段 |
| 輕小說 | `novel` | ⏳ 第二階段 |
| 系列 | `series` | ⏳ 第三階段 |

---

## 🗂️ Taxonomy 架構

| Taxonomy slug | 中文名稱 | 掛載 | URL | 狀態 |
|---|---|---|---|---|
| `genre` | 類型 | anime + manga + novel | `/genre/action/` | ✅ |
| `anime_season_tax` | 播出季度 | anime | `/season/2025-spring/` | ✅ |
| `anime_format_tax` | 動畫格式 | anime | `/format/tv/` | ✅ |

> ⚠️ **重要**：taxonomy slug 是 `genre`、`anime_season_tax`、`anime_format_tax`。
> 程式碼中任何地方出現 `anime_genre` 或 `anime_tag` 都是錯誤的舊 slug。

---

## 🎌 Genre 清單（27個，taxonomy: genre）

| AniList 英文 | taxonomy slug | 中文 |
|---|---|---|
| Action | action | 動作 |
| Adventure | adventure | 冒險 |
| Comedy | comedy | 喜劇 |
| Drama | drama | 劇情 |
| Fantasy | fantasy | 奇幻 |
| Horror | horror | 恐怖 |
| Mahou Shoujo | mahou-shoujo | 魔法少女 |
| Mecha | mecha | 機甲 |
| Music | music-genre | 音樂 |
| Mystery | mystery | 推理 |
| Suspense | suspense | 懸疑 |
| Psychological | psychological | 心理 |
| Sci-Fi | sci-fi | 科幻 |
| Slice of Life | slice-of-life | 日常 |
| Sports | sports | 運動 |
| Supernatural | supernatural | 超自然 |
| Thriller | thriller | 驚悚 |
| Isekai | isekai | 異世界 |
| Harem | harem | 後宮 |
| Yuri | yuri | 百合 |
| Boys Love | bl | 耽美 |
| Historical | historical | 歷史 |
| Wuxia | wuxia | 武俠 |
| School | school | 校園 |
| Kids | kids | 兒童 |
| Ecchi | ecchi | 輕色情 |
| Romance | romance | 戀愛 |

---

## 📅 Season Taxonomy（anime_season_tax）

- 範圍：2000 ~ 2030 年
- 父層：年份（slug: `2025`）
- 子層：冬→春→夏→秋（slug: `2025-winter`, `2025-spring`, `2025-summer`, `2025-fall`）
- URL：`/season/2025/`、`/season/2025-spring/`

---

## 🎬 Format Taxonomy（anime_format_tax）

| AniList 格式 | taxonomy slug |
|---|---|
| TV | format-tv |
| TV_SHORT | format-tv-short |
| MOVIE | format-movie |
| OVA | format-ova |
| ONA | format-ona |
| SPECIAL | format-special |
| MUSIC | format-music |

---

## 📰 文章分類（WordPress 預設 category）

頂層：新番、動漫新聞、音樂、漫畫情報（slug: manga-news）、輕小說情報（slug: novel-news）、
遊戲、電競、VTuber、Cosplay、周邊、聖地巡禮、AI工具、排行

子分類共約 70 個，詳見 setup-taxonomy.php。

> 注意：「漫畫情報」slug 是 manga-news（不是 manga），避免跟 manga Post Type 衝突。

---

## ⚙️ 核心決策紀錄

| 項目 | 決定 | 理由 |
|---|---|---|
| Post slug | Romaji 英文 | 避免中文編碼 URL，SEO 友善 |
| Genre taxonomy | 共用（anime+manga+novel） | 跨媒體 Genre 頁面聚合，SEO 權重集中 |
| Schema 輸出 | 由 single-anime.php 模板輸出 | 避免與 class-frontend.php 重複 |
| 篩選功能 | 靜態 taxonomy URL | SEO 最佳解 |
| 預設排序 | 季度（最新優先） | 新番自動排前面 |
| 圖片處理 | 三種模式（api_url/media_library/cdn） | 彈性設定 |

---

## 📋 SEO 決策

| 項目 | 決定 |
|---|---|
| Schema TVSeries/Movie/MusicVideoObject | 根據 format 自動切換（在 single-anime.php 輸出） |
| Schema BreadcrumbList | ✅ 在 single-anime.php 輸出 |
| Schema CollectionPage | ✅ 在 archive-anime.php 輸出 |
| Schema AggregateRating | ✅ score > 0 才輸出 |
| alternateName | ✅ 非空才輸出 |
| 底部 SEO 區塊 | taxonomy 內部連結 |
| Tag 頁面 | RankMath 設為 noindex |

---

## 🐛 已修復 Bug 紀錄

| Bug | 修復檔案 | 說明 |
|---|---|---|
| genre_map 缺少 10 個 AniList genre | class-import-manager.php | 補齊 Romance/Isekai/Harem 等 |
| 封面圖重複下載 | class-image-handler.php | 加入 has_post_thumbnail 檢查 |
| Synopsis HTML 標籤未清理 | class-api-handler.php | 新增 clean_synopsis() |
| $season_label 用錯 key | archive-anime.php | $status → $season |
| Schema url 分頁非 canonical | archive-anime.php | 固定指向第一頁 |
| aggregateRating 0分輸出 | archive-anime.php / single-anime.php | score > 0 才輸出 |
| .aaa-meta-ep CSS 缺色 | archive-anime.php | 補上 purple 色定義 |
| alternateName 空陣列 | single-anime.php | 非空才輸出 |
| setup-taxonomy genre slug 錯誤 | setup-taxonomy.php | 懸疑/驚悚 slug 對調，補 romance |
| 季節順序語意不符 | setup-taxonomy.php | 改為 winter→spring→summer→fall |
| Installer 未執行 | anime-sync-pro.php | activation hook 補上 Installer |
| Frontend 未載入 | anime-sync-pro.php | plugins_loaded 補上 Frontend |
| Schema 重複輸出 | class-frontend.php | 移除 output_json_ld，由模板輸出 |
| 麵包屑用舊 taxonomy slug | class-frontend.php | anime_genre → genre |
| REST API 用舊 taxonomy slug | class-frontend.php | anime_genre/anime_tag → genre |
| anime-single.css 未載入 | class-frontend.php | enqueue_assets 補上 |
| AniList ID 上限過低 | class-security.php | 999999 → 9999999 |
| ABSPATH 安全檢查缺失 | class-rate-limiter.php | 補上 exit 防護 |
| 審核佇列批次操作無效 | class-admin.php | 新增 bulk_action / save_bangumi_id handler |

---

## 📦 檔案清單與狀態

| 檔案 | 狀態 |
|---|---|
| anime-sync-pro.php | ✅ 已修正 |
| setup-taxonomy.php | ✅ 已修正（需執行一次建立 term） |
| includes/class-api-handler.php | ✅ 已修正 |
| includes/class-import-manager.php | ✅ 已修正 |
| includes/class-image-handler.php | ✅ 已修正 |
| includes/class-cn-converter.php | ✅ 無問題 |
| includes/class-id-mapper.php | ✅ 無問題 |
| includes/class-error-logger.php | ✅ 無問題 |
| includes/class-installer.php | ✅ 無問題 |
| includes/class-performance.php | ✅ 無問題 |
| includes/class-rate-limiter.php | ✅ 已修正（補 ABSPATH） |
| includes/class-review-queue.php | ✅ 無問題 |
| includes/class-security.php | ✅ 已修正 |
| includes/class-acf-fields.php | ⬜ 未審查 |
| admin/class-admin.php | ✅ 已修正 |
| admin/pages/dashboard.php | ⬜ 未審查 |
| admin/pages/import-tool.php | ✅ 無問題 |
| admin/pages/settings.php | ⬜ 未審查 |
| admin/pages/review-queue.php | ✅ 無問題（依賴 class-admin.php 修正） |
| admin/pages/review-preview.php | ⬜ 未審查 |
| admin/pages/published-list.php | ⬜ 未審查 |
| admin/pages/logs.php | ⬜ 未審查 |
| public/class-frontend.php | ✅ 已修正 |
| public/templates/archive-anime.php | ✅ 已修正 |
| public/templates/single-anime.php | ✅ 已修正 |
| public/templates/single.php | ⬜ 未審查 |

---

## 🚀 部署順序

1. 上傳所有修正後的檔案到 WordPress
2. 後台停用外掛 → 重新啟用（觸發 Installer 建立資料表）
3. 執行 setup-taxonomy.php 建立所有 term
4. 執行完畢立即刪除 setup-taxonomy.php
5. 測試匯入一部動畫確認流程正常

---

## 💡 待辦事項

- [ ] 審查 class-acf-fields.php、dashboard.php、settings.php、review-preview.php、published-list.php、logs.php、single.php
- [ ] 排行榜自動生成頁面
- [ ] 漫畫 Post Type + manga_format_tax
- [ ] 輕小說 Post Type
- [ ] 系列 Post Type（第三階段）
- [ ] 搜尋功能優化
- [ ] 用戶互動功能（追番、收藏、評分）

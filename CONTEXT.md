# Anime Sync Pro — CONTEXT.md
> Version : **v12.0**
> Date    : 2026-04-14
> Status  : All known bugs fixed. Next bug ID starts at **ABV**.

---

## 1. 專案基本資訊

| 項目 | 內容 |
|------|------|
| 外掛名稱 | Anime Sync Pro |
| Text Domain | `anime-sync-pro` |
| 版本 | **1.0.5** |
| GitHub | https://github.com/smaacg/anime-sync-pro-2- |
| 測試站 | https://dev.weixiaoacg.com |
| 正式站 | https://weixiaoacg.com（尚未部署） |
| 主機 | Hostinger Business |
| PHP 最低需求 | ≥ 8.0 |
| WordPress 最低需求 | ≥ 6.0 |
| 主檔案 | `anime-sync-pro.php` |
| 外掛路徑 | `wp-content/plugins/anime-sync-pro/` |
| Autoload 規則 | 前綴 `Anime_Sync_` → `includes/class-{slug}.php` |

---

## 2. 必要前置外掛

（同 v11.4，略）

---

## 3. 初次安裝完整流程

（同 v11.4，略）

---

## 4. 目錄結構

Copy
anime-sync-pro/ ├── anime-sync-pro.php ✅ v1.0.5 ACD 版本號更新 ├── composer.json ├── vendor/ ├── setup-taxonomy.php ✅ 補入第五部分說明（anime_series_tax） ├── includes/ │ ├── class-api-handler.php ✅ ACD 新增 get_series_tree()、fetch_anilist_popularity() │ │ 及系列輔助方法（find_series_root/expand_series_tree/...） │ ├── class-id-mapper.php ✅ 同 v11.0 │ ├── class-import-manager.php ✅ ACD 新增 analyze_series()、assign_series_taxonomy()、 │ │ get_popularity_ranking()；import_single() 加第三參數 $source │ ├── class-acf-fields.php │ ├── class-cn-converter.php │ ├── class-image-handler.php │ ├── class-rate-limiter.php │ ├── class-cron-manager.php ✅ 原有；import_single() 三參呼叫已相容 │ ├── class-installer.php │ └── class-error-logger.php ├── admin/ │ ├── class-admin.php ✅ ACD 新增 3 個 AJAX handler；季度查詢修正分頁 │ ├── class-import-manager.php ← 此檔案位於 admin/ 而非 includes/ │ └── pages/ │ ├── import-tool.php ✅ ACD 全寬 + Tab 4 系列 + Tab 5 排行 + 節流 │ ├── dashboard.php │ ├── review-queue.php │ ├── published-list.php │ ├── logs.php │ └── settings.php ├── public/ │ ├── class-frontend.php │ ├── templates/single-anime.php │ └── assets/css/anime-single.css └── data/ └── cn-tw-dict.json

Copy
---

## 5. Custom Post Type

（同 v11.4，略）

---

## 6. Taxonomy 清單

### genre（動畫類型）— 同 v11.4

### anime_season_tax（播出季度）— 同 v11.4

### anime_format_tax（動畫格式）— 同 v11.4

### anime_series_tax（系列分類）**ACD 新增**

| 屬性 | 值 |
|------|---|
| 物件類型 | `anime` |
| 階層 | `false`（非階層，每個系列為一個 term） |
| Slug 前綴 | `series/` |
| term 建立方式 | 由 `assign_series_taxonomy()` 自動建立，不需手動預建 |
| meta | `_series_root_anilist_id`（記錄根源 AniList ID） |

---

## 7. Meta Key 完整清單

（同 v11.4，補充新增欄位）

| Key | 說明 |
|-----|------|
| `_import_source` | 匯入來源（`manual` / `anilist` / `cron`）**ACD 新增** |
| `_series_root_anilist_id` | 系列根源 AniList ID **ACD 新增** |

---

## 8–16. （同 v11.4，略）

---

## 17. ACD：系列分析功能說明

### 系列樹建構流程

1. 輸入任意一部作品的 AniList ID（例如攻殼機動隊 S2）
2. `find_series_root()` 遞迴向上追溯 `PREQUEL`，找到最早的根源作品（最多 15 層，防止循環）
3. `expand_series_tree()` 以 BFS 從根源展開，收集所有 `SEQUEL / SIDE_STORY / SPIN_OFF / ALTERNATIVE / PARENT` 節點
4. 每個節點附帶：是否已匯入、WP post_id、編輯連結
5. 系列名稱取根源作品的中文標題（→ Romaji → fallback）
6. 結果快取 7 天（transient `anime_sync_series_tree_{id}`）

### API 請求估算（以 10 部系列為例）

- find_series_root：最多 15 次輕量 relations query
- expand_series_tree：每個節點 1 次 node_data query
- 總計約 10–25 次 AniList API calls
- 前端節流確保每 10 部匯入後暫停 10 秒

### 前端節流規則

- 共用 `throttledImport()` 函數
- 每處理 10 部 → 暫停 10 秒倒數 → 繼續
- 適用於 Tab 2（季度批次）、Tab 3（ID 清單）、Tab 4（系列）、Tab 5（排行）

---

## 18. 前台版型說明（single-anime.php）

（同 v11.4，略）

---

## 19. Bug 歷史紀錄

（同 v11.4，新增以下條目）

| Bug ID | 版本 | 檔案 | 說明 |
|--------|------|------|------|
| ABT | v11.4 | anime-single.css + single-anime.php + class-api-handler.php | 多項改版（配色/封面/移除推薦/AnimeThemes embed） |
| ABU | v1.0.5 | class-admin.php | 季度批次匯入修正：加入分頁邏輯，不再固定 50 部 |
| ACD | v1.0.5 | class-api-handler.php / class-import-manager.php / class-admin.php / import-tool.php | 系列分析（Tab 4）、人氣排行（Tab 5）、前端節流、import_single 第三參數、全寬版面 |

下一個 Bug ID：**ABV**

---

## 20. 已知待處理事項

| 項目 | 說明 |
|------|------|
| ABO | class-api-handler.php 需新增 fetch_bgm_data_public() 等 4 個 public 包裝方法，供 AJAX 重新同步 Bangumi 使用 |
| — | AnimeThemes embed 需重新同步番劇才能取得新的 theme_slug/anime_slug |
| — | 正式站尚未部署，所有修改目前僅在 dev.weixiaoacg.com 測試 |
| — | Tab 4 系列分析：Fate 系列等龐大系列（50+ 部）遞迴追溯可能觸發更多 API 請求，建議觀察實際速度後調整 MAX_DEPTH（目前 15 層） |

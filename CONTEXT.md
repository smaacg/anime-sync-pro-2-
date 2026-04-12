# Anime Sync Pro — 開發上下文 CONTEXT.md
# 每次新對話開始時貼給 Claude，讓他快速了解專案狀態

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

| Taxonomy | slug | 掛載 Post Type | URL | 狀態 |
|---|---|---|---|---|
| 類型 | `genre` | anime + manga + novel | `/genre/action/` | ✅ 待建立 |
| 播出季度 | `anime_season_tax` | anime | `/season/2025-spring/` | ✅ 待建立 |
| 動畫格式 | `anime_format_tax` | anime | `/format/tv/` | ✅ 待建立 |
| 漫畫格式 | `manga_format_tax` | manga | `/format-manga/xxx/` | ⏳ 第二階段 |

---

## 📂 WordPress 永久連結設定

- **永久連結結構：** 文章名稱 `/%postname%/`
- **分類前綴：** `topic`（文章分類 URL：`/topic/anime-news/`）
- **標籤前綴：** `tag`

---

## 🎌 Genre 清單（28個，taxonomy: genre）

```php
$anime_genres = [
    ['動作',     'action',        ''],
    ['冒險',     'adventure',     ''],
    ['喜劇',     'comedy',        ''],
    ['劇情',     'drama',         ''],
    ['奇幻',     'fantasy',       ''],
    ['恐怖',     'horror',        ''],
    ['魔法少女', 'mahou-shoujo',  ''],
    ['機甲',     'mecha',         ''],
    ['音樂',     'music',         ''],
    ['推理',     'mystery',       ''],
    ['懸疑',     'thriller',      ''],
    ['心理',     'psychological', ''],
    ['科幻',     'sci-fi',        ''],
    ['日常',     'slice-of-life', ''],
    ['運動',     'sports',        ''],
    ['超自然',   'supernatural',  ''],
    ['驚悚',     'suspense',      ''],
    ['異世界',   'isekai',        ''],
    ['後宮',     'harem',         ''],
    ['百合',     'yuri',          ''],
    ['耽美',     'bl',            ''],
    ['歷史',     'historical',    ''],
    ['武俠',     'wuxia',         ''],
    ['校園',     'school',        ''],
    ['兒童',     'kids',          ''],
    ['輕色情',   'ecchi',         ''],
];
Copy
📅 Season Taxonomy（anime_season_tax）
範圍：2000 ~ 2030 年
結構：年份為父層，四個季度為子層
slug 格式：2025（父）、2025-spring（子）
URL：/season/2025/、/season/2025-spring/
Copy$seasons = [
    ['春季', 'spring'],
    ['夏季', 'summer'],
    ['秋季', 'fall'],
    ['冬季', 'winter'],
];
🎬 Format Taxonomy（anime_format_tax）
Copy$anime_formats = [
    ['TV',     'format-tv',      ''],
    ['TV短篇', 'format-tv-short',''],
    ['劇場版', 'format-movie',   ''],
    ['OVA',    'format-ova',     ''],
    ['ONA',    'format-ona',     ''],
    ['特別篇', 'format-special', ''],
    ['音樂MV', 'format-music',   ''],
];
📰 文章分類（WordPress 預設 category）
頂層分類：新番、動漫新聞、音樂、漫畫情報、輕小說情報、遊戲、電競、VTuber、Cosplay、周邊、聖地巡禮、AI工具、排行

注意：

「漫畫」改為「漫畫情報」（slug: manga-news），避免跟 manga Post Type 衝突
「輕小說」改為「輕小說情報」（slug: novel-news），避免跟 novel Post Type 衝突
「聖地巡禮」、「Cosplay」、「電競」、「音樂」只保留頂層，移除動漫新聞底下的重複子分類
LOFI 不放進分類
⚙️ 核心決策紀錄
項目	決定	理由
Post slug	Romaji 英文	避免中文編碼 URL，SEO 友善
Genre taxonomy	共用（anime+manga+novel）	跨媒體 Genre 頁面聚合，SEO 權重集中
「動漫」頂層 category	不保留	跟 /anime/ archive 重疊，分散權重
篩選功能	方式 C（靜態 taxonomy URL + 動態參數）	SEO 最佳解
動畫卡片	豐富版	語意豐富，SEO 效果好
預設排序	季度（最新優先）	新番自動排前面
相關新聞	Tag 自動關聯	不需手動設定
排行榜	列入待辦，根據 meta 自動生成	之後做
用戶互動（追番/收藏）	跳過，之後做	優先做資料庫核心功能
📋 SEO 決策紀錄
項目	決定
Schema：TVSeries/Movie/MusicVideoObject	根據 format 自動切換
Schema：麵包屑 BreadcrumbList	✅ 加入
Schema：archive CollectionPage	✅ 加入
Schema：AggregateRating	✅ 加入（AniList 評分）
底部關鍵字區塊	改成 taxonomy 內部連結，避免關鍵字堆砌
Tag 頁面	RankMath 設為 noindex
featured image	三種圖片模式都補上 set_post_thumbnail
封面圖 Alt Text	自動設為「{標題} 封面圖 | 動畫資料庫」
🖼️ single-anime.php 骨架
CopyHeader（Logo + 搜尋 + 登入）
導覽列（首頁/當季動畫/動畫列表/排行榜/最新消息/關於我們）
麵包屑（首頁 > 動畫列表 > 作品名稱）
頂部區塊：封面圖 + 標題（中/日/Romaji）+ 評分 + 人氣 + 排名 + 預告片
快速導覽 Tab（基本資訊/劇情簡介/角色聲優/製作人員/主題曲/串流平台/相關作品/相關新聞）
主內容（左70%）：基本資訊 / 劇情簡介 / 角色聲優 / 製作人員 / 主題曲 / 串流平台 / 外部連結
側邊欄（右30%）：相關新聞（tag自動關聯）/ 關聯作品 / 熱門推薦（同genre+評分最高）
底部 SEO 區：taxonomy 內部連結（genre/season/format）
Schema JSON-LD：TVSeries/Movie/MusicVideoObject + BreadcrumbList + AggregateRating
Footer
📦 待輸出檔案清單
順序	檔案	狀態
1	setup-taxonomy.php	⏳ 待輸出
2	anime-sync-pro.php	⏳ 待輸出
3	class-api-handler.php	⏳ 待輸出（加 relations 欄位）
4	class-import-manager.php	⏳ 待輸出
5	class-image-handler.php	⏳ 待輸出
6	single-anime.php	⏳ 待輸出
7	archive-anime.php	⏳ 待輸出
🐛 已知 Bug 紀錄
Bug	狀態
anime_genre → 改名為 genre	⏳ 待修
api_url/cdn 模式沒有設定 featured image	⏳ 待修
封面圖 Alt Text 沒有設定	⏳ 待修
匯入時沒有寫入 format / season taxonomy	⏳ 待修
匯入時 post slug 用中文（應改為 Romaji）	⏳ 待修
AniList relations 欄位沒有抓取	⏳ 待修
💡 待辦事項（之後做）
 排行榜自動生成頁面（根據 anime_score_anilist 排序）
 漫畫 Post Type + manga_format_tax
 輕小說 Post Type
 系列 Post Type（第三階段）
 搜尋功能優化（跨 Post Type + meta 欄位搜尋）
 用戶互動功能（追番、收藏、評分）
 RankMath 設定（正式站架設時）
 archive-anime.php 篩選器 UI
 麵包屑設定（RankMath）
 Tag 頁面設為 noindex（RankMath）
 anime Post Type Schema 設為 TVSeries（RankMath）
Copy
---

把這個存成 `CONTEXT.md` 放在外掛根目錄，推到 GitHub，之後每次新對話開始時直接貼連結或內容給我就好。

準備好開始輸出第一個檔案 `setup-taxonomy.php` 嗎？
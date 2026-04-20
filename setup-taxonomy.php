<?php
/**
 * Anime Sync Pro — 分類初始化腳本
 * 放在 wp-content/plugins/anime-sync-pro/setup-taxonomy.php
 * 用管理員登入後訪問：https://dev.weixiaoacg.com/wp-content/plugins/anime-sync-pro/setup-taxonomy.php
 * 執行完畢後請立即刪除此檔案
 *
 * 修正紀錄：
 * - Bug 7：$season_labels 季節順序改為 winter → spring → summer → fall（符合播出時序）
 * - 對齊 Bug 2：$genres 新增 'romance' 對應（戀愛），補齊與 class-import-manager.php 的 genre_map 一致
 * - 對齊 Bug 2：'懸疑' slug 由 'thriller' 改為 'suspense'，'驚悚' slug 改為 'thriller'（語意對齊）
 */

// 載入 WordPress
$wp_load = dirname( __FILE__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) die( '找不到 wp-load.php' );
require_once $wp_load;

if ( ! current_user_can( 'manage_options' ) ) die( '請先登入 WordPress 管理員帳號' );

// ============================================================
// 工具函數
// ============================================================
function insert_term_safe( string $name, string $taxonomy, array $args = [] ): int {
    $existing = get_term_by( 'slug', $args['slug'] ?? sanitize_title( $name ), $taxonomy );
    if ( $existing ) return $existing->term_id;
    $result = wp_insert_term( $name, $taxonomy, $args );
    if ( is_wp_error( $result ) ) {
        echo "<p style='color:orange'>⚠️ {$taxonomy} / {$name}：" . $result->get_error_message() . "</p>";
        return 0;
    }
    echo "<p style='color:green'>✅ {$taxonomy} / {$name} 建立成功</p>";
    return $result['term_id'];
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>分類初始化</title></head><body>';
echo '<h1>Anime Sync Pro — 分類初始化腳本</h1>';

// ============================================================
// 第一部分：WordPress 預設 category（文章分類）
// ============================================================
echo '<h2>第一部分：文章分類（category）</h2>';

$categories = [
    // 頂層分類
    ['新番',       'new-season',   ''],
    ['動漫新聞',   'anime-news',   ''],
    ['音樂',       'music',        ''],
    ['漫畫情報',   'manga-news',   ''],  // 注意：不是「漫畫」，避免跟 manga Post Type 衝突
    ['輕小說情報', 'novel-news',   ''],  // 注意：不是「輕小說」，避免跟 novel Post Type 衝突
    ['遊戲',       'games',        ''],
    ['電競',       'esports',      ''],
    ['VTuber',     'vtuber',       ''],
    ['Cosplay',    'cosplay',      ''],
    ['周邊',       'merch',        ''],
    ['聖地巡禮',   'pilgrimage',   ''],
    ['AI工具',     'ai-tools',     ''],
    ['排行',       'ranking',      ''],

    // ── 新番子分類 ──
    ['本季新番',   'current-season',  'new-season'],
    ['即將開播',   'coming-soon',     'new-season'],
    ['新番預報',   'season-preview',  'new-season'],
    ['新番排行',   'season-ranking',  'new-season'],
    ['新番回顧',   'season-review',   'new-season'],

    // ── 動漫新聞子分類 ──
    ['新番情報',   'news-season',     'anime-news'],
    ['動漫評論',   'news-review,      'anime-news'],
    ['聲優藝人',   'news-seiyuu',     'anime-news'],
    ['音樂情報',   'news-music',      'anime-news'],
    ['周邊商品',   'news-merch',      'anime-news'],
    ['活動展覽',   'news-event',      'anime-news'],
    ['業界消息',   'news-industry',   'anime-news'],
    ['VTuber消息', 'news-vtuber',     'anime-news'],
    ['遊戲情報',   'news-game',       'anime-news'],
    ['漫畫情報',   'news-manga',      'anime-news'],
    ['輕小說情報', 'news-lightnovel', 'anime-news'],
    ['電影情報',   'news-movie',      'anime-news'],

    // ── 音樂子分類 ──
    ['原聲帶',     'music-ost',           'music'],
    ['OP／ED',     'music-oped',          'music'],
    ['聲優單曲',   'music-seiyuu-single', 'music'],
    ['演唱會',     'music-concert',       'music'],
    ['樂團',       'music-band',          'music'],

    // ── 漫畫情報子分類 ──
    ['連載情報',       'manga-serialization', 'manga-news'],
    ['單行本',         'manga-volume',        'manga-news'],
    ['完結作品',       'manga-completed',     'manga-news'],
    ['台灣代理漫畫',   'manga-tw',            'manga-news'],

    // ── 輕小說情報子分類 ──
    ['新刊情報',       'ln-new',         'novel-news'],
    ['改編情報',       'ln-adaptation',  'novel-news'],
    ['台灣代理輕小說', 'ln-tw',          'novel-news'],

    // ── 遊戲子分類 ──
    ['手遊',     'games-mobile',     'games'],
    ['主機遊戲', 'games-console',    'games'],
    ['PC遊戲',   'games-pc',         'games'],
    ['改編遊戲', 'games-adaptation', 'games'],

    // ── 電競子分類 ──
    ['賽事消息', 'esports-tournament', 'esports'],
    ['戰隊動態', 'esports-team',       'esports'],
    ['比賽結果', 'esports-result',     'esports'],

    // ── VTuber 子分類 ──
    ['出道情報', 'vtuber-debut',     'vtuber'],
    ['直播精華', 'vtuber-highlight', 'vtuber'],
    ['企劃活動', 'vtuber-event',     'vtuber'],
    ['畢業消息', 'vtuber-graduate',  'vtuber'],
    ['歌回',     'vtuber-utawaku',   'vtuber'],

    // ── Cosplay 子分類 ──
    ['攝影作品', 'cosplay-photo',    'cosplay'],
    ['活動紀錄', 'cosplay-event',    'cosplay'],
    ['裝扮教學', 'cosplay-tutorial', 'cosplay'],

    // ── 周邊子分類 ──
    ['模型公仔', 'merch-figure',   'merch'],
    ['一番賞',   'merch-ichiban',  'merch'],
    ['限定商品', 'merch-limited',  'merch'],
    ['預購情報', 'merch-preorder', 'merch'],
    ['扭蛋',     'merch-gashapon', 'merch'],

    // ── 聖地巡禮子分類 ──
    ['取景地地圖', 'pilgrimage-map',    'pilgrimage'],
    ['旅遊心得',   'pilgrimage-travel', 'pilgrimage'],
    ['日本旅遊',   'pilgrimage-japan',  'pilgrimage'],

    // ── AI工具子分類 ──
    ['繪圖模型',   'ai-image',    'ai-tools'],
    ['AI教學',     'ai-tutorial', 'ai-tools'],
    ['新工具發布', 'ai-release',  'ai-tools'],
    ['AI新聞',     'ai-news',     'ai-tools'],

    // ── 排行子分類 ──
    ['人氣排行', 'ranking-popular',  'ranking'],
    ['評分排行', 'ranking-score',    'ranking'],
    ['銷量排行', 'ranking-sales',    'ranking'],
    ['追番排行', 'ranking-watching', 'ranking'],
];

// 先建頂層，記錄 slug → term_id
$cat_ids = [];
foreach ( $categories as [ $name, $slug, $parent_slug ] ) {
    if ( $parent_slug !== '' ) continue;
    $term_id = insert_term_safe( $name, 'category', [ 'slug' => $slug ] );
    if ( $term_id ) $cat_ids[ $slug ] = $term_id;
}

// 再建子層
foreach ( $categories as [ $name, $slug, $parent_slug ] ) {
    if ( $parent_slug === '' ) continue;
    $parent_id = $cat_ids[ $parent_slug ] ?? 0;
    if ( ! $parent_id ) {
        // 嘗試從資料庫找父層
        $parent_term = get_term_by( 'slug', $parent_slug, 'category' );
        $parent_id   = $parent_term ? $parent_term->term_id : 0;
    }
    $term_id = insert_term_safe( $name, 'category', [ 'slug' => $slug, 'parent' => $parent_id ] );
    if ( $term_id ) $cat_ids[ $slug ] = $term_id;
}

// ============================================================
// 第二部分：動漫專用 Taxonomy
// ============================================================
echo '<h2>第二部分：動漫類型（genre）</h2>';

// ── Bug 對齊修正說明 ──────────────────────────────────────────
// 1. 新增 ['戀愛', 'romance', '']         → 對應 AniList 'Romance'
// 2. '懸疑' slug: 'thriller' → 'suspense' → 對應 AniList 'Suspense'
// 3. '驚悚' slug: 'suspense' → 'thriller' → 對應 AniList 'Thriller'
//    （原版兩者 slug 互換，導致語意錯誤）
// ─────────────────────────────────────────────────────────────
$genres = [
    ['動作',     'action',        ''],
    ['冒險',     'adventure',     ''],
    ['喜劇',     'comedy',        ''],
    ['劇情',     'drama',         ''],
    ['奇幻',     'fantasy',       ''],
    ['恐怖',     'horror',        ''],
    ['魔法少女', 'mahou-shoujo',  ''],
    ['機甲',     'mecha',         ''],
    ['音樂',     'music-genre',   ''],  // slug 加 -genre 避免跟 category/music 衝突
    ['推理',     'mystery',       ''],
    ['懸疑',     'suspense',      ''],  // ← 修正：slug 由 thriller 改為 suspense
    ['心理',     'psychological', ''],
    ['科幻',     'sci-fi',        ''],
    ['日常',     'slice-of-life', ''],
    ['運動',     'sports',        ''],
    ['超自然',   'supernatural',  ''],
    ['驚悚',     'thriller',      ''],  // ← 修正：slug 由 suspense 改為 thriller
    ['異世界',   'isekai',        ''],
    ['後宮',     'harem',         ''],
    ['百合',     'yuri',          ''],
    ['耽美',     'bl',            ''],
    ['歷史',     'historical',    ''],
    ['武俠',     'wuxia',         ''],
    ['校園',     'school',        ''],
    ['兒童',     'kids',          ''],
    ['輕色情',   'ecchi',         ''],
    ['戀愛',     'romance',       ''],  // ← 新增：對應 AniList 'Romance'
];

foreach ( $genres as [ $name, $slug, ] ) {
    insert_term_safe( $name, 'genre', [ 'slug' => $slug ] );
}

// ============================================================
// 第三部分：播出季度（anime_season_tax）2000–2030
// ============================================================
echo '<h2>第三部分：播出季度（anime_season_tax）2000–2030</h2>';

// ── Bug 7 修正：季節順序改為 winter → spring → summer → fall ──
// 冬季（1–3 月）為每年第一個播出季，應排在最前面。
// 原版順序 spring → summer → fall → winter 在後台列表語意上不直觀。
$season_labels = [
    'winter' => '冬季',  // ← 移到第一位（Bug 7 修正）
    'spring' => '春季',
    'summer' => '夏季',
    'fall'   => '秋季',
];

for ( $year = 2000; $year <= 2030; $year++ ) {
    // 建年份父層
    $parent_id = insert_term_safe( (string) $year, 'anime_season_tax', [
        'slug' => (string) $year,
    ]);

    // 建四個季度子層
    foreach ( $season_labels as $slug_suffix => $label ) {
        insert_term_safe( "{$year} {$label}", 'anime_season_tax', [
            'slug'   => "{$year}-{$slug_suffix}",
            'parent' => $parent_id,
        ]);
    }
}

// ============================================================
// 第四部分：動漫格式（anime_format_tax）
// ============================================================
echo '<h2>第四部分：動漫格式（anime_format_tax）</h2>';

$formats = [
    ['TV',     'format-tv',       ''],
    ['TV短篇', 'format-tv-short', ''],
    ['劇場版', 'format-movie',    ''],
    ['OVA',    'format-ova',      ''],
    ['ONA',    'format-ona',      ''],
    ['特別篇', 'format-special',  ''],
    ['音樂MV', 'format-music',    ''],
];

foreach ( $formats as [ $name, $slug, ] ) {
    insert_term_safe( $name, 'anime_format_tax', [ 'slug' => $slug ] );
}
// ============================================================
// 第五部分：系列（anime_series_tax）
// ============================================================
echo '<h2>第五部分：系列（anime_series_tax）</h2>';
echo '<p style="color:#666;">anime_series_tax 為非階層 Taxonomy，不需要預建 term。<br>
系列 term 將由匯入外掛在匯入時自動建立（例如「進擊的巨人」）。</p>';
echo '<p style="color:green;">✅ anime_series_tax 已在 anime-sync-pro.php 中註冊，無需手動建立 term。</p>';

// ============================================================
// 完成
// ============================================================
update_option( 'anime_sync_taxonomy_setup_done', true );

echo '<hr>';
echo '<h2 style="color:green">✅ 所有分類建立完成！</h2>';
echo '<p style="color:red;font-weight:bold;">⚠️ 請立即刪除此檔案：wp-content/plugins/anime-sync-pro/setup-taxonomy.php</p>';
echo '</body></html>';

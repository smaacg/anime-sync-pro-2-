<?php
/**
 * Anime Sync Pro — 分類初始化腳本 v2
 * 路徑：wp-content/plugins/anime-sync-pro/setup-taxonomy.php
 * 使用管理員登入後訪問：https://dev.weixiaoacg.com/wp-content/plugins/anime-sync-pro/setup-taxonomy.php
 * 執行完畢後請立即刪除此檔案
 */

$wp_load = dirname( __FILE__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) die( '找不到 wp-load.php' );
require_once $wp_load;

if ( ! current_user_can( 'manage_options' ) ) die( '請先登入 WordPress 管理員帳號' );

// ============================================================
// 工具函數
// ============================================================
function insert_term_safe( string $name, string $taxonomy, array $args = [] ): int {
    $existing = get_term_by( 'slug', $args['slug'] ?? sanitize_title( $name ), $taxonomy );
    if ( $existing ) {
        echo "<p style='color:#888'>⏭️ 已存在：{$taxonomy} / {$name}</p>";
        return $existing->term_id;
    }
    $result = wp_insert_term( $name, $taxonomy, $args );
    if ( is_wp_error( $result ) ) {
        echo "<p style='color:orange'>⚠️ {$taxonomy} / {$name}：" . $result->get_error_message() . "</p>";
        return 0;
    }
    echo "<p style='color:green'>✅ {$taxonomy} / {$name} 建立成功</p>";
    return $result['term_id'];
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>分類初始化 v2</title></head><body>';
echo '<h1>Anime Sync Pro — 分類初始化腳本 v2</h1>';

// ============================================================
// 第一部分：WordPress 文章分類（category）
// ============================================================
echo '<h2>第一部分：文章分類（category）</h2>';

$categories = [

    // ── 頂層分類 ──────────────────────────────────────────────
    ['新聞',     'acg-news',    ''],
    ['評論',     'review',      ''],
    ['專題',     'feature',     ''],
    ['新番',     'acg-season',  ''],
    ['音樂',     'acg-music',   ''],
    ['遊戲',     'games',       ''],
    ['電競',     'esports',     ''],
    ['VTuber',   'vtuber',      ''],
    ['Cosplay',  'cosplay',     ''],
    ['周邊',     'merch',       ''],
    ['聖地巡禮', 'travel',      ''],
    ['AI工具',   'ai-tools',    ''],
    ['排行',     'ranking',     ''],

    // ── 新聞子分類 ────────────────────────────────────────────
    ['動漫',     'anime',       'acg-news'],
    ['漫畫',     'manga',       'acg-news'],
    ['遊戲',     'game',        'acg-news'],
    ['音樂',     'music',       'acg-news'],
    ['VTuber',   'vtuber',      'acg-news'],
    ['業界',     'industry',    'acg-news'],
    ['活動',     'event',       'acg-news'],
    ['電影',     'movie',       'acg-news'],
    ['聲優',     'seiyuu',      'acg-news'],

    // ── 評論子分類 ────────────────────────────────────────────
    ['動畫評論', 'anime',       'review'],
    ['漫畫評論', 'manga',       'review'],
    ['遊戲評論', 'game',        'review'],
    ['輕小說',   'novel',       'review'],
    ['電影評論', 'movie',       'review'],

    // ── 專題子分類 ────────────────────────────────────────────
    ['分析',     'analysis',    'feature'],
    ['盤點',     'list',        'feature'],
    ['文化',     'culture',     'feature'],
    ['產業',     'industry',    'feature'],

    // ── 新番子分類 ────────────────────────────────────────────
    ['本季新番', 'current',     'acg-season'],
    ['即將開播', 'coming',      'acg-season'],
    ['新番回顧', 'review',      'acg-season'],

    // ── 音樂子分類 ────────────────────────────────────────────
    ['原聲帶',   'ost',         'acg-music'],
    ['OP／ED',   'oped',        'acg-music'],
    ['聲優單曲', 'single',      'acg-music'],
    ['演唱會',   'concert',     'acg-music'],
    ['樂團',     'band',        'acg-music'],

    // ── 遊戲子分類 ────────────────────────────────────────────
    ['手遊',     'mobile',      'games'],
    ['主機遊戲', 'console',     'games'],
    ['PC遊戲',   'pc',          'games'],
    ['改編遊戲', 'adaptation',  'games'],

    // ── 電競子分類 ────────────────────────────────────────────
    ['賽事',     'tournament',  'esports'],
    ['戰隊',     'team',        'esports'],
    ['結果',     'result',      'esports'],

    // ── VTuber 子分類 ─────────────────────────────────────────
    ['出道',     'debut',       'vtuber'],
    ['直播',     'highlight',   'vtuber'],
    ['活動',     'event',       'vtuber'],
    ['畢業',     'graduate',    'vtuber'],
    ['歌回',     'utawaku',     'vtuber'],

    // ── Cosplay 子分類 ────────────────────────────────────────
    ['攝影',     'photo',       'cosplay'],
    ['活動',     'event',       'cosplay'],
    ['教學',     'tutorial',    'cosplay'],

    // ── 周邊子分類 ────────────────────────────────────────────
    ['模型',     'figure',      'merch'],
    ['限定',     'limited',     'merch'],
    ['預購',     'preorder',    'merch'],
    ['扭蛋',     'gashapon',    'merch'],

    // ── 聖地巡禮子分類 ────────────────────────────────────────
    ['取景地',   'location',    'travel'],
    ['旅遊心得', 'trip',        'travel'],
    ['日本旅遊', 'japan',       'travel'],

    // ── AI工具子分類 ──────────────────────────────────────────
    ['繪圖',     'image',       'ai-tools'],
    ['影片',     'video',       'ai-tools'],
    ['寫作',     'writing',     'ai-tools'],
    ['教學',     'tutorial',    'ai-tools'],
    ['新工具',   'release',     'ai-tools'],

    // ── 排行子分類 ────────────────────────────────────────────
    ['動漫排行', 'anime',       'ranking'],
    ['漫畫排行', 'manga',       'ranking'],
    ['音樂排行', 'music',       'ranking'],
    ['遊戲排行', 'game',        'ranking'],
    ['VTuber排行','vtuber',     'ranking'],
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
        $parent_term = get_term_by( 'slug', $parent_slug, 'category' );
        $parent_id   = $parent_term ? $parent_term->term_id : 0;
    }
    $term_id = insert_term_safe( $name, 'category', [ 'slug' => $slug, 'parent' => $parent_id ] );
    if ( $term_id ) $cat_ids[ $slug ] = $term_id;
}

// ============================================================
// 第二部分：動漫類型（genre）
// ============================================================
echo '<h2>第二部分：動漫類型（genre）</h2>';

$genres = [
    ['動作',     'action',        ''],
    ['冒險',     'adventure',     ''],
    ['喜劇',     'comedy',        ''],
    ['劇情',     'drama',         ''],
    ['奇幻',     'fantasy',       ''],
    ['恐怖',     'horror',        ''],
    ['魔法少女', 'mahou-shoujo',  ''],
    ['機甲',     'mecha',         ''],
    ['音樂',     'music-genre',   ''],
    ['推理',     'mystery',       ''],
    ['懸疑',     'suspense',      ''],
    ['心理',     'psychological', ''],
    ['科幻',     'sci-fi',        ''],
    ['日常',     'slice-of-life', ''],
    ['運動',     'sports',        ''],
    ['超自然',   'supernatural',  ''],
    ['驚悚',     'thriller',      ''],
    ['異世界',   'isekai',        ''],
    ['後宮',     'harem',         ''],
    ['百合',     'yuri',          ''],
    ['耽美',     'bl',            ''],
    ['歷史',     'historical',    ''],
    ['武俠',     'wuxia',         ''],
    ['校園',     'school',        ''],
    ['兒童',     'kids',          ''],
    ['輕色情',   'ecchi',         ''],
    ['戀愛',     'romance',       ''],
];

foreach ( $genres as [ $name, $slug ] ) {
    insert_term_safe( $name, 'genre', [ 'slug' => $slug ] );
}

// ============================================================
// 第三部分：播出季度（anime_season_tax）2000–2030
// ============================================================
echo '<h2>第三部分：播出季度（anime_season_tax）2000–2030</h2>';

$season_labels = [
    'winter' => '冬季',
    'spring' => '春季',
    'summer' => '夏季',
    'fall'   => '秋季',
];

for ( $year = 2000; $year <= 2030; $year++ ) {
    $parent_id = insert_term_safe( (string) $year, 'anime_season_tax', [
        'slug' => (string) $year,
    ]);
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

foreach ( $formats as [ $name, $slug ] ) {
    insert_term_safe( $name, 'anime_format_tax', [ 'slug' => $slug ] );
}

// ============================================================
// 第五部分：系列（anime_series_tax）
// ============================================================
echo '<h2>第五部分：系列（anime_series_tax）</h2>';
echo '<p style="color:#666;">anime_series_tax 為非階層 Taxonomy，term 由匯入外掛自動建立，無需手動預建。</p>';
echo '<p style="color:green;">✅ anime_series_tax 已在 anime-sync-pro.php 中註冊。</p>';

// ============================================================
// 完成
// ============================================================
update_option( 'anime_sync_taxonomy_setup_v2_done', true );

echo '<hr>';
echo '<h2 style="color:green">✅ 所有分類建立完成！</h2>';
echo '<p><strong>永久連結設定提醒：</strong></p>';
echo '<ul>';
echo '<li>後台 → 設定 → 永久連結 → 自訂結構填入：<code>/%category%/%postname%/</code></li>';
echo '<li>分類目錄基點填入：<code>（留空）</code></li>';
echo '<li>儲存兩次讓 rewrite rules 重新整理</li>';
echo '</ul>';
echo '<p style="color:red;font-weight:bold;">⚠️ 請立即刪除此檔案：wp-content/plugins/anime-sync-pro/setup-taxonomy.php</p>';
echo '</body></html>';

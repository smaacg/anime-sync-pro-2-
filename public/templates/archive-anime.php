<?php
/**
 * Archive Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/archive-anime.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

wp_enqueue_style(
    'anime-sync-archive',
    plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/anime-archive.css',
    [],
    defined( 'ANIME_SYNC_PRO_VERSION' ) ? ANIME_SYNC_PRO_VERSION : '1.0.0'
);

get_header();

/* ── 頁面資訊 ─────────────────────────────────────────────── */
$is_archive    = is_post_type_archive( 'anime' );
$is_genre      = is_tax( 'genre' );
$is_season     = is_tax( 'anime_season_tax' );
$is_format     = is_tax( 'anime_format_tax' );
$current_term  = ( $is_genre || $is_season || $is_format ) ? get_queried_object() : null;

$archive_title = '動畫列表';
$archive_desc  = '';
if ( $current_term ) {
    $archive_title = $current_term->name;
    $archive_desc  = term_description( $current_term->term_id );
}

$total_posts   = (int) $GLOBALS['wp_query']->found_posts;
$current_page  = max( 1, get_query_var( 'paged' ) );

/* ── 當前篩選狀態（用於 active 判斷）────────────────────── */
$active_genre  = $is_genre  ? $current_term->slug : ( $_GET['genre']  ?? '' );
$active_season = $is_season ? $current_term->slug : ( $_GET['season'] ?? '' );
$active_format = $is_format ? $current_term->slug : ( $_GET['format'] ?? '' );

/* ── 抓取 Taxonomy 選項 ───────────────────────────────────── */
// 季度：只取近五年（最新優先）
$current_year  = (int) gmdate( 'Y' );
$season_terms  = get_terms([
    'taxonomy'   => 'anime_season_tax',
    'orderby'    => 'slug',
    'order'      => 'DESC',
    'hide_empty' => true,
    'parent'     => 0,    // 只取年份父層
    'number'     => 6,    // 近六年
]);

// 季度子層（春夏秋冬）
$season_children = [];
if ( ! is_wp_error( $season_terms ) ) {
    foreach ( $season_terms as $year_term ) {
        $children = get_terms([
            'taxonomy'   => 'anime_season_tax',
            'parent'     => $year_term->term_id,
            'orderby'    => 'slug',
            'order'      => 'DESC',
            'hide_empty' => true,
        ]);
        if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
            $season_children[ $year_term->slug ] = $children;
        }
    }
}

// 格式
$format_terms = get_terms([
    'taxonomy'   => 'anime_format_tax',
    'orderby'    => 'count',
    'order'      => 'DESC',
    'hide_empty' => true,
]);

// 類型
$genre_terms = get_terms([
    'taxonomy'   => 'genre',
    'orderby'    => 'count',
    'order'      => 'DESC',
    'hide_empty' => true,
    'number'     => 20,
]);

/* ── Schema：CollectionPage ──────────────────────────────── */
$schema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'CollectionPage',
    'name'        => $archive_title . ' | 動畫資料庫',
    'description' => $archive_desc
        ? wp_strip_all_tags( $archive_desc )
        : '收錄所有動畫資訊，包含評分、季度、類型、聲優等完整資料。',
    'url'         => get_pagenum_link( $current_page ),
];

/* ── Schema：麵包屑 ───────────────────────────────────────── */
$breadcrumb_items = [
    [ '@type' => 'ListItem', 'position' => 1, 'name' => '首頁',     'item' => home_url('/') ],
    [ '@type' => 'ListItem', 'position' => 2, 'name' => '動畫列表', 'item' => home_url('/anime/') ],
];
if ( $current_term ) {
    $breadcrumb_items[] = [
        '@type'    => 'ListItem',
        'position' => 3,
        'name'     => $current_term->name,
        'item'     => get_term_link( $current_term ),
    ];
}
$breadcrumb_schema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => $breadcrumb_items,
];
?>

<?php /* ── Schema JSON-LD ─────────────────────────────────── */ ?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<script type="application/ld+json"><?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<div class="aaa-wrap">

<?php /* ── 麵包屑 ───────────────────────────────────────────── */ ?>
<nav class="aaa-breadcrumb" aria-label="麵包屑導航">
    <ol>
        <li><a href="<?php echo esc_url( home_url('/') ); ?>">首頁</a></li>
        <li><a href="<?php echo esc_url( home_url('/anime/') ); ?>">動畫列表</a></li>
        <?php if ( $current_term ) : ?>
        <li><?php echo esc_html( $current_term->name ); ?></li>
        <?php endif; ?>
    </ol>
</nav>

<?php /* ── 頁首 ─────────────────────────────────────────────── */ ?>
<div class="aaa-header">
    <h1 class="aaa-title"><?php echo esc_html( $archive_title ); ?></h1>
    <?php if ( $archive_desc ) : ?>
    <p class="aaa-desc"><?php echo wp_kses_post( $archive_desc ); ?></p>
    <?php endif; ?>
    <p class="aaa-count">共 <strong><?php echo esc_html( $total_posts ); ?></strong> 部動畫</p>
</div>

<?php /* ── 篩選列（方式 C：靜態 taxonomy URL + 手風琴展開）───── */ ?>
<div class="aaa-filter-wrap">

    <?php /* 季度篩選 */ ?>
    <div class="aaa-filter-group">
        <div class="aaa-filter-label">📅 播出季度</div>
        <div class="aaa-filter-row">
            <a href="<?php echo esc_url( get_post_type_archive_link('anime') ); ?>"
               class="aaa-filter-btn <?php echo ( $is_archive && ! $active_season ) ? 'active' : ''; ?>">
                全部
            </a>
            <?php foreach ( $season_children as $year_slug => $children ) : ?>
                <?php foreach ( $children as $child ) : ?>
                <a href="<?php echo esc_url( get_term_link( $child ) ); ?>"
                   class="aaa-filter-btn <?php echo ( $active_season === $child->slug ) ? 'active' : ''; ?>">
                    <?php echo esc_html( $child->name ); ?>
                </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php /* 格式篩選 */ ?>
    <?php if ( ! is_wp_error( $format_terms ) && $format_terms ) : ?>
    <div class="aaa-filter-group">
        <div class="aaa-filter-label">🎬 動畫格式</div>
        <div class="aaa-filter-row">
            <a href="<?php echo esc_url( get_post_type_archive_link('anime') ); ?>"
               class="aaa-filter-btn <?php echo ( $is_archive && ! $active_format ) ? 'active' : ''; ?>">
                全部
            </a>
            <?php foreach ( $format_terms as $fmt ) : ?>
            <a href="<?php echo esc_url( get_term_link( $fmt ) ); ?>"
               class="aaa-filter-btn <?php echo ( $active_format === $fmt->slug ) ? 'active' : ''; ?>">
                <?php echo esc_html( $fmt->name ); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php /* 類型篩選 */ ?>
    <?php if ( ! is_wp_error( $genre_terms ) && $genre_terms ) : ?>
    <div class="aaa-filter-group">
        <div class="aaa-filter-label">🏷️ 動畫類型</div>
        <div class="aaa-filter-row">
            <a href="<?php echo esc_url( get_post_type_archive_link('anime') ); ?>"
               class="aaa-filter-btn <?php echo ( $is_archive && ! $active_genre ) ? 'active' : ''; ?>">
                全部
            </a>
            <?php foreach ( $genre_terms as $gn ) : ?>
            <a href="<?php echo esc_url( get_term_link( $gn ) ); ?>"
               class="aaa-filter-btn <?php echo ( $active_genre === $gn->slug ) ? 'active' : ''; ?>">
                <?php echo esc_html( $gn->name ); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php /* ── 動畫卡片網格 ──────────────────────────────────────── */ ?>
<?php if ( have_posts() ) : ?>

<div class="aaa-grid" id="aaa-grid">
<?php
$season_labels = [
    'WINTER' => '冬季', 'SPRING' => '春季',
    'SUMMER' => '夏季', 'FALL'   => '秋季',
];
$format_labels = [
    'TV' => 'TV', 'TV_SHORT' => 'TV短篇', 'MOVIE' => '劇場版',
    'OVA' => 'OVA', 'ONA' => 'ONA', 'SPECIAL' => '特別篇', 'MUSIC' => 'MV',
];
$status_labels = [
    'FINISHED'         => '已完結',
    'RELEASING'        => '連載中',
    'NOT_YET_RELEASED' => '尚未播出',
    'CANCELLED'        => '已取消',
    'HIATUS'           => '暫停中',
];
$status_classes = [
    'FINISHED'         => 's-fin',
    'RELEASING'        => 's-rel',
    'NOT_YET_RELEASED' => 's-pre',
    'CANCELLED'        => 's-can',
    'HIATUS'           => 's-hia',
];

while ( have_posts() ) : the_post();
    $pid = get_the_ID();

    // Meta
    $cover       = get_post_meta( $pid, 'anime_cover_image',  true )
                ?: get_the_post_thumbnail_url( $pid, 'medium' );
    $title_zh    = get_post_meta( $pid, 'anime_title_chinese', true ) ?: get_the_title();
    $title_ro    = get_post_meta( $pid, 'anime_title_romaji',  true );
    $score_raw   = get_post_meta( $pid, 'anime_score_anilist', true );
    $score       = is_numeric( $score_raw ) ? number_format( $score_raw / 10, 1 ) : '';
    $season      = get_post_meta( $pid, 'anime_season',        true );
    $year        = (int) get_post_meta( $pid, 'anime_year',    true );
    $format      = get_post_meta( $pid, 'anime_format',        true );
    $status      = get_post_meta( $pid, 'anime_status',        true );
    $episodes    = (int) get_post_meta( $pid, 'anime_episodes', true );
    $popularity  = (int) get_post_meta( $pid, 'anime_popularity', true );

    $season_label = $season_labels[ $status ] ?? '';
    $format_label = $format_labels[ $format ] ?? $format;
    $status_label = $status_labels[ $status ] ?? '';
    $status_class = $status_classes[ $status ] ?? '';
    $season_str   = ( $year && isset( $season_labels[ $season ] ) )
        ? $year . ' ' . $season_labels[ $season ]
        : ( $year ?: '' );
?>
<article class="aaa-card">
    <a href="<?php the_permalink(); ?>" class="aaa-card-link">

        <?php /* 封面圖 */ ?>
        <div class="aaa-card-cover-wrap">
            <?php if ( $cover ) : ?>
            <img class="aaa-card-cover"
                 src="<?php echo esc_url( $cover ); ?>"
                 alt="<?php echo esc_attr( $title_zh ); ?> 封面圖"
                 loading="lazy">
            <?php else : ?>
            <div class="aaa-card-cover aaa-no-cover">無封面</div>
            <?php endif; ?>

            <?php /* 狀態 badge */ ?>
            <?php if ( $status_label ) : ?>
            <span class="aaa-status-badge <?php echo esc_attr( $status_class ); ?>">
                <?php echo esc_html( $status_label ); ?>
            </span>
            <?php endif; ?>

            <?php /* 評分 overlay */ ?>
            <?php if ( $score ) : ?>
            <span class="aaa-score-badge">⭐ <?php echo esc_html( $score ); ?></span>
            <?php endif; ?>
        </div>

        <?php /* 卡片資訊 */ ?>
        <div class="aaa-card-body">
            <h3 class="aaa-card-title"><?php echo esc_html( $title_zh ); ?></h3>
            <?php if ( $title_ro ) : ?>
            <p class="aaa-card-romaji"><?php echo esc_html( $title_ro ); ?></p>
            <?php endif; ?>

            <div class="aaa-card-meta">
                <?php if ( $format_label ) : ?>
                <span class="aaa-meta-tag aaa-meta-format"><?php echo esc_html( $format_label ); ?></span>
                <?php endif; ?>
                <?php if ( $season_str ) : ?>
                <span class="aaa-meta-tag aaa-meta-season"><?php echo esc_html( $season_str ); ?></span>
                <?php endif; ?>
                <?php if ( $episodes ) : ?>
                <span class="aaa-meta-tag aaa-meta-ep"><?php echo esc_html( $episodes ); ?> 集</span>
                <?php endif; ?>
            </div>

            <?php if ( $popularity ) : ?>
            <div class="aaa-card-pop">👥 <?php echo esc_html( number_format( $popularity ) ); ?></div>
            <?php endif; ?>
        </div>

    </a>
</article>
<?php endwhile; ?>
</div>

<?php /* ── 分頁 ─────────────────────────────────────────────── */ ?>
<nav class="aaa-pagination" aria-label="分頁導航">
    <?php
    echo paginate_links([
        'prev_text' => '← 上一頁',
        'next_text' => '下一頁 →',
        'mid_size'  => 2,
        'type'      => 'list',
    ]);
    ?>
</nav>

<?php /* ── SEO 底部：Taxonomy 內部連結 ─────────────────────── */ ?>
<div class="aaa-seo-footer">
    <?php if ( ! is_wp_error( $genre_terms ) && $genre_terms ) : ?>
    <div class="aaa-seo-row">
        <span class="aaa-seo-label">動畫類型：</span>
        <?php foreach ( $genre_terms as $g ) : ?>
        <a href="<?php echo esc_url( get_term_link( $g ) ); ?>" class="aaa-seo-tag">
            <?php echo esc_html( $g->name ); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ( ! is_wp_error( $format_terms ) && $format_terms ) : ?>
    <div class="aaa-seo-row">
        <span class="aaa-seo-label">動畫格式：</span>
        <?php foreach ( $format_terms as $f ) : ?>
        <a href="<?php echo esc_url( get_term_link( $f ) ); ?>" class="aaa-seo-tag">
            <?php echo esc_html( $f->name ); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php else : ?>

<?php /* ── 無結果 ────────────────────────────────────────────── */ ?>
<div class="aaa-empty">
    <p>目前沒有動畫資料</p>
    <?php if ( current_user_can( 'manage_options' ) ) : ?>
    <a href="<?php echo esc_url( admin_url('admin.php?page=anime-sync-import') ); ?>"
       class="aaa-import-btn">前往匯入動畫</a>
    <?php endif; ?>
</div>

<?php endif; ?>
</div>

<style>
/* ============================================================
   Archive Anime — 內嵌樣式（之後可移至 anime-archive.css）
   ============================================================ */

.aaa-wrap { max-width: 1280px; margin: 0 auto; padding: 0 20px 60px; }

/* 麵包屑 */
.aaa-breadcrumb ol { display:flex; gap:8px; list-style:none; margin:16px 0; padding:0; font-size:13px; color:#888; flex-wrap:wrap; }
.aaa-breadcrumb li + li::before { content:'›'; margin-right:8px; }
.aaa-breadcrumb a { color:#5b9bd5; text-decoration:none; }
.aaa-breadcrumb a:hover { text-decoration:underline; }

/* 頁首 */
.aaa-header { text-align:center; padding:40px 20px 24px; }
.aaa-title  { font-size:clamp(1.6rem,4vw,2.4rem); margin:0 0 8px; }
.aaa-desc   { color:#aaa; margin:0 0 8px; }
.aaa-count  { color:#888; font-size:14px; margin:0; }
.aaa-count strong { color:#5b9bd5; }

/* 篩選列 */
.aaa-filter-wrap  { background:rgba(255,255,255,0.04); border-radius:12px; padding:20px; margin-bottom:32px; display:flex; flex-direction:column; gap:16px; }
.aaa-filter-group { display:flex; flex-direction:column; gap:8px; }
.aaa-filter-label { font-size:13px; color:#aaa; font-weight:600; }
.aaa-filter-row   { display:flex; flex-wrap:wrap; gap:6px; }
.aaa-filter-btn   {
    display:inline-block; padding:6px 14px; border-radius:20px; font-size:13px;
    text-decoration:none; color:#ccc; background:rgba(255,255,255,0.06);
    border:1px solid rgba(255,255,255,0.1); transition:all .2s; white-space:nowrap;
}
.aaa-filter-btn:hover,
.aaa-filter-btn.active { background:#2271b1; color:#fff; border-color:#2271b1; }

/* 卡片網格 */
.aaa-grid {
    display:grid;
    grid-template-columns: repeat( auto-fill, minmax(160px, 1fr) );
    gap:20px;
    margin-bottom:40px;
}
@media (min-width:600px)  { .aaa-grid { grid-template-columns: repeat( auto-fill, minmax(180px,1fr) ); } }
@media (min-width:1024px) { .aaa-grid { grid-template-columns: repeat( auto-fill, minmax(200px,1fr) ); } }

/* 卡片 */
.aaa-card       { border-radius:10px; overflow:hidden; background:rgba(255,255,255,0.05); transition:transform .2s,box-shadow .2s; }
.aaa-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(0,0,0,.4); }
.aaa-card-link  { display:block; text-decoration:none; color:inherit; }

.aaa-card-cover-wrap { position:relative; aspect-ratio:2/3; overflow:hidden; background:#111; }
.aaa-card-cover      { width:100%; height:100%; object-fit:cover; display:block; transition:transform .3s; }
.aaa-card:hover .aaa-card-cover { transform:scale(1.04); }
.aaa-no-cover        { display:flex; align-items:center; justify-content:center; color:#555; font-size:13px; height:100%; }

/* Badge overlay */
.aaa-status-badge {
    position:absolute; top:8px; left:8px; padding:2px 8px; border-radius:4px;
    font-size:11px; font-weight:700; letter-spacing:.5px;
}
.s-rel { background:#27ae60; color:#fff; }
.s-fin { background:#555;    color:#fff; }
.s-pre { background:#2980b9; color:#fff; }
.s-can { background:#c0392b; color:#fff; }
.s-hia { background:#e67e22; color:#fff; }

.aaa-score-badge {
    position:absolute; bottom:8px; right:8px; background:rgba(0,0,0,.75);
    color:#f1c40f; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:700;
}

/* 卡片內容 */
.aaa-card-body   { padding:10px 12px 12px; }
.aaa-card-title  { font-size:13px; font-weight:700; margin:0 0 4px; line-height:1.4;
                   display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.aaa-card-romaji { font-size:11px; color:#888; margin:0 0 6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.aaa-card-meta   { display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px; }
.aaa-meta-tag    { font-size:11px; padding:2px 6px; border-radius:3px; background:rgba(255,255,255,0.08); color:#bbb; }
.aaa-meta-format { background:rgba(91,155,213,.2); color:#5b9bd5; }
.aaa-meta-season { background:rgba(39,174,96,.15); color:#27ae60; }
.aaa-card-pop    { font-size:11px; color:#777; }

/* 分頁 */
.aaa-pagination { display:flex; justify-content:center; margin:32px 0; }
.aaa-pagination .page-numbers {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:36px; height:36px; padding:0 10px; margin:0 3px;
    border-radius:6px; background:rgba(255,255,255,0.06);
    color:#ccc; text-decoration:none; font-size:14px;
    border:1px solid rgba(255,255,255,0.1); transition:all .2s;
}
.aaa-pagination .page-numbers:hover,
.aaa-pagination .page-numbers.current { background:#2271b1; color:#fff; border-color:#2271b1; }
.aaa-pagination .page-numbers.dots { background:none; border:none; cursor:default; }

/* SEO 底部連結 */
.aaa-seo-footer { border-top:1px solid rgba(255,255,255,0.08); padding-top:24px; margin-top:16px; display:flex; flex-direction:column; gap:10px; }
.aaa-seo-row    { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
.aaa-seo-label  { font-size:12px; color:#888; min-width:70px; }
.aaa-seo-tag    { font-size:12px; color:#5b9bd5; text-decoration:none; padding:2px 8px; border-radius:12px; border:1px solid rgba(91,155,213,0.3); transition:all .2s; }
.aaa-seo-tag:hover { background:rgba(91,155,213,0.15); }

/* 無結果 */
.aaa-empty      { text-align:center; padding:80px 20px; color:#888; }
.aaa-import-btn { display:inline-block; margin-top:16px; padding:10px 24px; background:#2271b1; color:#fff; border-radius:6px; text-decoration:none; }

/* ── 手機版 ─────────────────────────────────────────────── */
@media (max-width: 600px) {
    .aaa-filter-row    { gap:5px; }
    .aaa-filter-btn    { padding:5px 11px; font-size:12px; }
    .aaa-card-body     { padding:8px 10px 10px; }
    .aaa-card-title    { font-size:12px; }
}
</style>

<?php get_footer(); ?>

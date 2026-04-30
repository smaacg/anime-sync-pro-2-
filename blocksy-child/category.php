<?php
/**
 * 微笑動漫 — 分類頁模板 FINAL v3
 * 路徑：wp-content/themes/blocksy-child/category.php
 */

get_header();

$current_cat = get_queried_object();
$slug        = $current_cat->slug ?? '';
$cat_name    = $current_cat->name ?? '';
$cat_desc    = $current_cat->description ?? '';
$paged       = max( 1, get_query_var('paged') );
$per_page    = 12;

// ── 分類設定 ──────────────────────────────────────────────────
$cat_config = [
    'news'     => [ 'icon' => '📰', 'color' => '#e74c3c', 'desc' => '最新動漫資訊，第一手掌握業界動態' ],
    'review'   => [ 'icon' => '⭐', 'color' => '#f39c12', 'desc' => '動漫觀後心得，分享你的感受與評價' ],
    'feature'  => [ 'icon' => '🔍', 'color' => '#8e44ad', 'desc' => '深度專題分析，帶你了解動漫產業' ],
    'anime'    => [ 'icon' => '🎬', 'color' => '#3498db', 'desc' => '動漫情報專區，涵蓋最新動畫資訊' ],
    'manga'    => [ 'icon' => '📚', 'color' => '#27ae60', 'desc' => '漫畫情報專區，連載、單行本最新消息' ],
    'novel'    => [ 'icon' => '📖', 'color' => '#16a085', 'desc' => '輕小說情報，新刊與改編消息一手掌握' ],
    'music'    => [ 'icon' => '🎵', 'color' => '#e91e63', 'desc' => '動漫音樂專區，OP、ED、原聲帶情報' ],
    'games'    => [ 'icon' => '🎮', 'color' => '#2c3e50', 'desc' => '遊戲情報專區，手遊、主機、PC 最新資訊' ],
    'esports'  => [ 'icon' => '🏆', 'color' => '#e67e22', 'desc' => '電競賽事資訊，賽事、戰隊、比賽結果' ],
    'vtuber'   => [ 'icon' => '🎭', 'color' => '#9b59b6', 'desc' => 'VTuber 情報，出道、直播、活動最新消息' ],
    'cosplay'  => [ 'icon' => '👘', 'color' => '#e91e63', 'desc' => 'Cosplay 專區，攝影作品與活動紀錄' ],
    'merch'    => [ 'icon' => '🛍️', 'color' => '#e74c3c', 'desc' => '動漫周邊情報，模型、限定、預購資訊' ],
    'travel'   => [ 'icon' => '✈️', 'color' => '#1abc9c', 'desc' => '聖地巡禮指南，取景地與旅遊心得分享' ],
    'ai-tools' => [ 'icon' => '🤖', 'color' => '#34495e', 'desc' => 'AI 工具介紹，繪圖、影片、寫作工具評測' ],
    'rankings' => [ 'icon' => '📊', 'color' => '#c0392b', 'desc' => '動漫排行榜，人氣、評分、銷量即時排名' ],
];

$config      = $cat_config[ $slug ] ?? [ 'icon' => '📁', 'color' => '#555', 'desc' => '' ];
$icon        = $config['icon'];
$color       = $config['color'];
$description = $cat_desc ?: $config['desc'];

// ── 文章查詢 ──────────────────────────────────────────────────
$args = [
    'cat'            => $current_cat->term_id,
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'post_status'    => 'publish',
];
$query      = new WP_Query( $args );
$total      = $query->found_posts;
$post_count = $query->post_count;
?>

<style>
/* ── Reset & Base ── */
.cat-page { max-width: 1200px; margin: 0 auto; padding: 0 20px 60px; }

/* ── Hero ── */
.cat-hero {
    background: linear-gradient(135deg, <?php echo $color; ?>22, <?php echo $color; ?>11);
    border-left: 5px solid <?php echo $color; ?>;
    border-radius: 12px;
    padding: 40px;
    margin: 30px 0;
    display: flex;
    align-items: center;
    gap: 24px;
}
.cat-hero-icon { font-size: 56px; line-height: 1; }
.cat-hero-info h1 { margin: 0 0 8px; font-size: 2rem; color: #1a1a2e; }
.cat-hero-info p  { margin: 0 0 12px; color: #555; font-size: 1rem; line-height: 1.6; }
.cat-hero-meta    { display: flex; gap: 16px; flex-wrap: wrap; }
.cat-hero-meta span {
    background: <?php echo $color; ?>;
    color: #fff;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

/* ── Grid ── */
.cat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-top: 32px;
}

/* ── Card ── */
.cat-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    transition: transform .25s, box-shadow .25s;
    display: flex;
    flex-direction: column;
}
.cat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 28px rgba(0,0,0,.14);
}
.cat-card a { text-decoration: none; color: inherit; }

.cat-card-thumb {
    width: 100%;
    aspect-ratio: 16/9;
    object-fit: cover;
    display: block;
    background: #f0f0f0;
}
.cat-card-thumb-placeholder {
    width: 100%;
    aspect-ratio: 16/9;
    background: linear-gradient(135deg, <?php echo $color; ?>22, <?php echo $color; ?>44);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
}

.cat-card-body  { padding: 20px; flex: 1; display: flex; flex-direction: column; }
.cat-card-cats  { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
.cat-card-cat {
    font-size: 0.75rem;
    font-weight: 700;
    color: <?php echo $color; ?>;
    background: <?php echo $color; ?>18;
    padding: 3px 10px;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.cat-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    line-height: 1.5;
    color: #1a1a2e;
    margin: 0 0 10px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.cat-card-excerpt {
    font-size: 0.88rem;
    color: #666;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}
.cat-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #f0f0f0;
    font-size: 0.8rem;
    color: #999;
}
.cat-card-date { display: flex; align-items: center; gap: 4px; }
.cat-card-read {
    color: <?php echo $color; ?>;
    font-weight: 600;
    font-size: 0.82rem;
}

/* ── Featured Card ── */
.cat-card-featured {
    grid-column: 1 / -1;
    flex-direction: row;
}
.cat-card-featured .cat-card-thumb,
.cat-card-featured .cat-card-thumb-placeholder {
    width: 45%;
    aspect-ratio: unset;
    min-height: 240px;
    flex-shrink: 0;
}
.cat-card-featured .cat-card-body { padding: 28px; }
.cat-card-featured .cat-card-title { font-size: 1.4rem; -webkit-line-clamp: 3; }
.cat-card-featured .cat-card-excerpt { -webkit-line-clamp: 4; }

/* ── Empty ── */
.cat-empty {
    text-align: center;
    padding: 80px 20px;
    color: #999;
}
.cat-empty-icon { font-size: 4rem; margin-bottom: 16px; }

/* ── Pagination ── */
.cat-pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 48px;
    flex-wrap: wrap;
}
.cat-pagination a,
.cat-pagination span {
    padding: 8px 16px;
    border-radius: 8px;
    border: 2px solid #eee;
    color: #333;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all .2s;
}
.cat-pagination a:hover { border-color: <?php echo $color; ?>; color: <?php echo $color; ?>; }
.cat-pagination .current {
    background: <?php echo $color; ?>;
    border-color: <?php echo $color; ?>;
    color: #fff;
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .cat-hero { flex-direction: column; text-align: center; padding: 28px 20px; }
    .cat-hero-meta { justify-content: center; }
    .cat-card-featured { flex-direction: column; }
    .cat-card-featured .cat-card-thumb,
    .cat-card-featured .cat-card-thumb-placeholder { width: 100%; min-height: 200px; aspect-ratio: 16/9; }
    .cat-grid { grid-template-columns: 1fr; }
}
</style>

<div class="cat-page">

    <!-- Hero -->
    <div class="cat-hero">
        <div class="cat-hero-icon"><?php echo $icon; ?></div>
        <div class="cat-hero-info">
            <h1><?php echo esc_html( $cat_name ); ?></h1>
            <?php if ( $description ) : ?>
                <p><?php echo esc_html( $description ); ?></p>
            <?php endif; ?>
            <div class="cat-hero-meta">
                <span>共 <?php echo $total; ?> 篇文章</span>
                <?php if ( $paged > 1 ) : ?>
                    <span>第 <?php echo $paged; ?> 頁</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 文章列表 -->
    <?php if ( $query->have_posts() ) : ?>
        <div class="cat-grid">
        <?php
        $is_first = true;
        while ( $query->have_posts() ) :
            $query->the_post();
            $post_id    = get_the_ID();
            $permalink  = get_permalink();
            $title      = get_the_title();
            $excerpt    = get_the_excerpt();
            $date       = get_the_date('Y.m.d');
            $thumb      = get_the_post_thumbnail_url( $post_id, $is_first ? 'large' : 'medium' );
            $cats       = get_the_category();
        ?>
            <article class="cat-card <?php echo $is_first ? 'cat-card-featured' : ''; ?>">
                <a href="<?php echo esc_url( $permalink ); ?>">
                    <?php if ( $thumb ) : ?>
                        <img class="cat-card-thumb" src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="<?php echo $is_first ? 'eager' : 'lazy'; ?>">
                    <?php else : ?>
                        <div class="cat-card-thumb-placeholder"><?php echo $icon; ?></div>
                    <?php endif; ?>
                </a>
                <div class="cat-card-body">
                    <?php if ( $cats ) : ?>
                        <div class="cat-card-cats">
                            <?php foreach ( array_slice( $cats, 0, 2 ) as $cat ) : ?>
                                <span class="cat-card-cat"><?php echo esc_html( $cat->name ); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <h2 class="cat-card-title">
                        <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
                    </h2>
                    <?php if ( $excerpt ) : ?>
                        <p class="cat-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
                    <?php endif; ?>
                    <div class="cat-card-footer">
                        <span class="cat-card-date">📅 <?php echo $date; ?></span>
                        <span class="cat-card-read">閱讀更多 →</span>
                    </div>
                </div>
            </article>
        <?php
            $is_first = false;
        endwhile;
        wp_reset_postdata();
        ?>
        </div>

        <!-- 分頁 -->
        <div class="cat-pagination">
            <?php
            echo paginate_links([
                'total'     => $query->max_num_pages,
                'current'   => $paged,
                'prev_text' => '← 上一頁',
                'next_text' => '下一頁 →',
                'type'      => 'list',
                'before_page_number' => '',
            ]);
            ?>
        </div>

    <?php else : ?>
        <div class="cat-empty">
            <div class="cat-empty-icon"><?php echo $icon; ?></div>
            <p>這個分類還沒有文章，敬請期待！</p>
        </div>
    <?php endif; ?>

</div>

<?php get_footer(); ?>

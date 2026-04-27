<?php
/**
 * Template Name: 動漫新聞
 * Template Post Type: page
 *
 * @package SmileACG
 */

get_header();

$paged    = max( 1, absint( get_query_var( 'paged' ) ) );
$cat_slug = sanitize_text_field( $_GET['cat'] ?? '' );
$news_args = [
    'post_type'      => [ 'post', 'news-ticker' ],
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
];
if ( $cat_slug ) {
    $news_args['tax_query'] = [[
        'taxonomy' => 'category',
        'field'    => 'slug',
        'terms'    => $cat_slug,
    ]];
}
$news_query = new WP_Query( $news_args );
?>

<section class="page-hero page-hero--news glass-mid">
    <div class="container">
        <h1 class="page-hero__title">
            <i class="fa-solid fa-newspaper" style="color:var(--accent-blue);"></i>
            動漫新聞
        </h1>
        <p class="page-hero__sub">最新動漫資訊、聲優消息、作品公告</p>
        <!-- 分類篩選 -->
        <div class="news-cat-tabs tab-switch">
            <a href="<?php the_permalink(); ?>" class="tab-btn <?php echo $cat_slug ? '' : 'active'; ?>">全部</a>
            <?php
            $news_cats = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => true, 'number' => 10 ] );
            foreach ( (array) $news_cats as $cat ) :
                $active = ( $cat_slug === $cat->slug ) ? ' active' : '';
            ?>
            <a href="?cat=<?php echo esc_attr( $cat->slug ); ?>" class="tab-btn<?php echo $active; ?>">
                <?php echo esc_html( $cat->name ); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="news-body">
<div class="container news-layout">

    <!-- 主新聞列表 -->
    <div class="news-main">
        <?php if ( $news_query->have_posts() ) : ?>
        <div class="news-grid" id="news-grid" data-wp="news-list">
            <?php while ( $news_query->have_posts() ) : $news_query->the_post(); ?>
            <article class="news-card glass-card" data-wp="news-card" data-id="<?php the_ID(); ?>">
                <?php if ( has_post_thumbnail() ) : ?>
                <a href="<?php the_permalink(); ?>" class="news-card__cover-wrap">
                    <?php the_post_thumbnail( 'smaacg-banner', [ 'class' => 'news-card__cover' ] ); ?>
                </a>
                <?php endif; ?>
                <div class="news-card__body">
                    <div class="news-card__meta">
                        <?php
                        $cats = get_the_category();
                        if ( $cats ) {
                            echo '<span class="chip chip--blue">' . esc_html( $cats[0]->name ) . '</span>';
                        }
                        ?>
                        <time class="news-card__date" datetime="<?php echo get_the_date( 'c' ); ?>">
                            <?php echo get_the_date( 'Y.m.d' ); ?>
                        </time>
                    </div>
                    <h2 class="news-card__title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <p class="news-card__excerpt"><?php the_excerpt(); ?></p>
                    <a href="<?php the_permalink(); ?>" class="news-card__read-more">
                        閱讀全文 <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
            </article>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <!-- Pagination -->
        <div class="news-pagination">
            <?php echo paginate_links( [
                'total'   => $news_query->max_num_pages,
                'current' => $paged,
            ] ); ?>
        </div>
        <?php else : ?>
        <div class="news-empty">
            <p><i class="fa-solid fa-inbox" style="font-size:2rem;opacity:.4;"></i></p>
            <p>目前沒有新聞文章</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- 側欄 -->
    <aside class="news-sidebar">
        <div class="rank-sidebar-card glass-card">
            <div class="rank-sidebar-title"><i class="fa-solid fa-fire"></i> 熱門新聞</div>
            <?php
            $popular = new WP_Query([
                'post_type' => 'post', 'posts_per_page' => 5,
                'orderby' => 'comment_count', 'order' => 'DESC',
            ]);
            if ( $popular->have_posts() ) : ?>
            <ul class="sb-rank-list">
                <?php $n = 1; while ( $popular->have_posts() ) : $popular->the_post(); ?>
                <li class="sb-rank-item">
                    <span class="sb-rank-num"><?php echo $n++; ?></span>
                    <a href="<?php the_permalink(); ?>" class="sb-rank-title"><?php the_title(); ?></a>
                </li>
                <?php endwhile; wp_reset_postdata(); ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php if ( is_active_sidebar( 'sidebar-1' ) ) dynamic_sidebar( 'sidebar-1' ); ?>
    </aside>

</div><!-- /.news-layout -->
</div><!-- /.news-body -->

<?php get_footer(); ?>

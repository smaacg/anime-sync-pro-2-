<?php
/**
 * Template Name: 專欄頁面
 * 用途：顯示 review（評論）+ feature（專題）混合內容
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// ── 抓最新評論 6 篇 ──
$review_query = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 6,
    'category_name'  => 'review',
    'post_status'    => 'publish',
]);

// ── 抓最新專題 6 篇 ──
$feature_query = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 6,
    'category_name'  => 'feature',
    'post_status'    => 'publish',
]);
?>

<div class="columns-page">
    <h1 class="page-title">🔍 專欄</h1>
    <p class="page-desc">深度評論與精選專題，帶你看見動漫世界的不同角度</p>

    <!-- ── 評論區塊 ── -->
    <section class="columns-section review-section">
        <header class="section-header">
            <h2>📝 評論</h2>
            <a href="/review/" class="more-link">查看全部 →</a>
        </header>
        
        <?php if ($review_query->have_posts()) : ?>
            <div class="columns-grid">
                <?php while ($review_query->have_posts()) : $review_query->the_post(); ?>
                    <article class="column-card">
                        <a href="<?php the_permalink(); ?>" class="card-link">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="card-thumb">
                                    <?php the_post_thumbnail('medium'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-info">
                                <div class="card-channels">
                                    <?php the_terms(get_the_ID(), 'channel', '', ' · '); ?>
                                </div>
                                <h3 class="card-title"><?php the_title(); ?></h3>
                                <div class="card-meta">
                                    <span class="date"><?php echo get_the_date(); ?></span>
                                </div>
                                <div class="card-excerpt">
                                    <?php echo wp_trim_words(get_the_excerpt(), 30); ?>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <p class="empty-msg">尚未有評論文章</p>
        <?php endif; ?>
    </section>

    <!-- ── 專題區塊 ── -->
    <section class="columns-section feature-section">
        <header class="section-header">
            <h2>📚 專題</h2>
            <a href="/feature/" class="more-link">查看全部 →</a>
        </header>
        
        <?php if ($feature_query->have_posts()) : ?>
            <div class="columns-grid">
                <?php while ($feature_query->have_posts()) : $feature_query->the_post(); ?>
                    <article class="column-card">
                        <a href="<?php the_permalink(); ?>" class="card-link">
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="card-thumb">
                                    <?php the_post_thumbnail('medium'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="card-info">
                                <div class="card-channels">
                                    <?php the_terms(get_the_ID(), 'channel', '', ' · '); ?>
                                </div>
                                <h3 class="card-title"><?php the_title(); ?></h3>
                                <div class="card-meta">
                                    <span class="date"><?php echo get_the_date(); ?></span>
                                </div>
                                <div class="card-excerpt">
                                    <?php echo wp_trim_words(get_the_excerpt(), 30); ?>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <p class="empty-msg">尚未有專題文章</p>
        <?php endif; ?>
    </section>
</div>

<?php get_footer(); ?>

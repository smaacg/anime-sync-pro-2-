<?php
/**
 * Archive Anime Template
 * 
 * @package Anime_Sync_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// 取得季度分類資訊
$current_term = get_queried_object();
$archive_title = is_post_type_archive('anime') ? '所有動畫' : $current_term->name ?? '動畫列表';
$archive_desc = is_tax() ? term_description() : '';
$total_posts = $wp_query->found_posts;
?>

<div class="anime-archive-page">
    
    <!-- 頁首 -->
    <div class="anime-archive-header">
        <h1><?php echo esc_html($archive_title); ?></h1>
        <?php if ($archive_desc): ?>
            <p><?php echo wp_kses_post($archive_desc); ?></p>
        <?php endif; ?>
        <p style="color: #666; font-size: 14px;">
            共 <?php echo esc_html($total_posts); ?> 部動畫
        </p>
    </div>
    
    <!-- 篩選列（季度）-->
    <?php
    $seasons = get_terms(array(
        'taxonomy' => 'anime_season',
        'orderby' => 'name',
        'order' => 'DESC',
        'number' => 16
    ));
    
    if (!empty($seasons) && !is_wp_error($seasons)):
    ?>
    <div class="anime-filter-bar">
        <div class="filter-inner">
            <a href="<?php echo get_post_type_archive_link('anime'); ?>" 
               class="filter-btn <?php echo is_post_type_archive('anime') ? 'active' : ''; ?>">
                全部
            </a>
            <?php foreach ($seasons as $term): ?>
                <a href="<?php echo get_term_link($term); ?>" 
                   class="filter-btn <?php echo (is_tax('anime_season', $term->term_id)) ? 'active' : ''; ?>">
                    <?php echo esc_html($term->name); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 動畫網格 -->
    <?php if (have_posts()): ?>
    <div class="anime-grid">
        <?php while (have_posts()): the_post();
            $post_id = get_the_ID();
            $cover_url = get_post_meta($post_id, 'anime_cover_url', true);
            $title_tw = get_post_meta($post_id, 'anime_title_chinese_traditional', true);
            $score = get_post_meta($post_id, 'anime_score_anilist', true);
            $season = get_post_meta($post_id, 'anime_season', true);
            $year = get_post_meta($post_id, 'anime_year', true);
            $format = get_post_meta($post_id, 'anime_format', true);
            $display_title = $title_tw ?: get_the_title();
            
            $season_labels = array(
                'WINTER' => '冬',
                'SPRING' => '春',
                'SUMMER' => '夏',
                'FALL' => '秋'
            );
        ?>
        <div class="anime-card">
            <a href="<?php the_permalink(); ?>">
                <?php if ($cover_url): ?>
                    <img class="anime-card-cover" 
                         src="<?php echo esc_url($cover_url); ?>" 
                         alt="<?php echo esc_attr($display_title); ?>"
                         loading="lazy">
                <?php else: ?>
                    <div class="anime-card-cover-placeholder">無封面</div>
                <?php endif; ?>
                
                <div class="anime-card-body">
                    <h3 class="anime-card-title"><?php echo esc_html($display_title); ?></h3>
                    <div class="anime-card-meta">
                        <span>
                            <?php echo esc_html($year); ?>
                            <?php echo esc_html($season_labels[$season] ?? ''); ?>
                        </span>
                        <?php if ($score): ?>
                            <span class="anime-card-score">★ <?php echo esc_html($score); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endwhile; ?>
    </div>
    
    <!-- 分頁 -->
    <div class="anime-pagination">
        <?php
        echo paginate_links(array(
            'prev_text' => '← 上一頁',
            'next_text' => '下一頁 →',
            'mid_size' => 2
        ));
        ?>
    </div>
    
    <?php else: ?>
    <div class="anime-no-results">
        <p>目前沒有動畫資料</p>
        <?php if (current_user_can('manage_options')): ?>
            <a href="<?php echo admin_url('admin.php?page=anime-sync-import'); ?>" 
               class="streaming-btn">
                前往匯入動畫
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
</div>

<!-- Archive 額外 CSS -->
<style>
.anime-filter-bar {
    max-width: 1200px;
    margin: 0 auto 30px;
    padding: 0 20px;
    overflow-x: auto;
}

.filter-inner {
    display: flex;
    gap: 8px;
    padding-bottom: 5px;
    flex-wrap: nowrap;
}

.filter-btn {
    display: inline-block;
    padding: 8px 16px;
    background: var(--anime-card-bg, #16213e);
    color: var(--anime-text, #e0e0e0);
    border-radius: 20px;
    text-decoration: none;
    font-size: 13px;
    white-space: nowrap;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s;
}

.filter-btn:hover,
.filter-btn.active {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.anime-pagination {
    max-width: 1200px;
    margin: 40px auto 0;
    padding: 0 20px;
    display: flex;
    justify-content: center;
    gap: 8px;
}

.anime-pagination .page-numbers {
    display: inline-block;
    padding: 8px 14px;
    background: var(--anime-card-bg, #16213e);
    color: var(--anime-text, #e0e0e0);
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s;
}

.anime-pagination .page-numbers:hover,
.anime-pagination .page-numbers.current {
    background: #2271b1;
    color: #fff;
}

.anime-no-results {
    max-width: 1200px;
    margin: 0 auto;
    padding: 80px 20px;
    text-align: center;
    color: var(--anime-text-muted, #9e9e9e);
}
</style>

<?php get_footer(); ?>

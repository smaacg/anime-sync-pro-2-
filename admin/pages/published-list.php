<?php
/**
 * 檔案名稱: admin/pages/published-list.php
 * 功能：顯示已發佈的動畫清單 (修正圖片顯示問題)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 分頁邏輯
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$per_page = 20;

$args = array(
    'post_type'      => 'anime',
    'post_status'    => array('publish', 'pending', 'draft'),
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC'
);

$query = new WP_Query($args);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">已發布動畫</h1>
    <hr class="wp-header-end">
    
    <div class="anime-sync-published-list" style="margin-top: 20px;">
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('edit.php?post_type=anime'); ?>" class="button">完整管理介面</a>
                <a href="<?php echo admin_url('post-new.php?post_type=anime'); ?>" class="button button-primary">新增動畫</a>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 70px;">封面</th>
                    <th>標題</th>
                    <th style="width: 100px;">AniList ID</th>
                    <th style="width: 100px;">狀態</th>
                    <th style="width: 150px;">發布日期</th>
                    <th style="width: 220px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($query->have_posts()): ?>
                    <?php while ($query->have_posts()): $query->the_post(); ?>
                        <?php
                        $post_id = get_the_ID();
                        // 嘗試多種可能的 Meta Key 獲取 AniList ID
                        $anilist_id = get_post_meta($post_id, 'anime_id_anilist', true) ?: get_post_meta($post_id, '_anilist_id', true);
                        
                        // --- 圖片抓取邏輯修正 ---
                        // 1. 優先嘗試 WP 特色圖片
                        $cover_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
                        // 2. 如果沒有特色圖片，嘗試從 Meta 抓取原始網址
                        if (!$cover_url) {
                            $cover_url = get_post_meta($post_id, 'anime_cover_url', true) ?: get_post_meta($post_id, '_anime_poster', true);
                        }
                        ?>
                        <tr>
                            <td>
                                <?php if ($cover_url): ?>
                                    <img src="<?php echo esc_url($cover_url); ?>" 
                                         style="width: 50px; height: 70px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; display: block;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 70px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 10px; border: 1px solid #ddd;">無封面</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>
                                    <a href="<?php echo get_edit_post_link($post_id); ?>" style="font-size: 14px; text-decoration: none;">
                                        <?php the_title(); ?>
                                    </a>
                                </strong>
                                <div class="row-actions" style="visibility: visible; margin-top: 4px;">
                                    <span class="id">ID: <?php echo $post_id; ?></span>
                                </div>
                            </td>
                            <td><code><?php echo esc_html($anilist_id ?: '-'); ?></code></td>
                            <td>
                                <?php 
                                $status = get_post_status();
                                if ($status === 'publish') {
                                    echo '<span style="background: #e7f6ed; color: #207b45; padding: 3px 8px; border-radius: 3px; font-weight: 500;">已發布</span>';
                                } elseif ($status === 'pending') {
                                    echo '<span style="background: #fff4e5; color: #b25e09; padding: 3px 8px; border-radius: 3px; font-weight: 500;">待審核</span>';
                                } else {
                                    echo '<span style="background: #f0f0f1; color: #646970; padding: 3px 8px; border-radius: 3px; font-weight: 500;">草稿</span>';
                                }
                                ?>
                            </td>
                            <td><span class="description"><?php echo get_the_date('Y-m-d H:i'); ?></span></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($post_id); ?>" class="button button-small">編輯</a>
                                <a href="<?php echo get_permalink($post_id); ?>" class="button button-small" target="_blank">查看</a>
                                <button type="button" 
                                        class="button button-small resync-anime" 
                                        data-post-id="<?php echo esc_attr($post_id); ?>"
                                        data-anilist-id="<?php echo esc_attr($anilist_id); ?>"
                                        style="color: #2271b1;">
                                    重新同步
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 60px; background: #fff;">
                            <span class="dashicons dashicons-video-alt3" style="font-size: 48px; width: 48px; height: 48px; color: #ddd;"></span>
                            <p style="margin-top: 15px; font-size: 16px; color: #666;">尚未匯入任何動畫數據</p>
                            <a href="<?php echo admin_url('admin.php?page=anime-sync-import'); ?>" class="button button-primary button-large">立即去匯入</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($query->max_num_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $query->max_num_pages,
                    'current'   => $paged
                ));
                ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<?php wp_reset_postdata(); ?>

<style>
/* 稍微美化一下表格 */
.wp-list-table th { font-weight: 600 !important; background: #f8f9fa; }
.wp-list-table td { vertical-align: middle !important; }
.resync-anime:hover { background: #f0f6fb !important; border-color: #2271b1 !important; }
</style>
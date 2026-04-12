<?php
/**
 * Dashboard Page
 * * @package Anime_Sync_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

// 取得統計資料
$review_queue = new Anime_Sync_Review_Queue();
$logger = new Anime_Sync_Error_Logger();

$pending_count = $review_queue->get_count('pending');
$approved_count = $review_queue->get_count('approved');

$published_count = wp_count_posts('anime');
$published_total = $published_count->publish ?? 0;

$log_stats = $logger->get_statistics(7);
?>

<div class="wrap">
    <h1>Anime Sync Pro 儀表板</h1>
    
    <div class="anime-sync-dashboard">
        
        <div class="anime-sync-stats-grid">
            <div class="anime-sync-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <h3>待審核</h3>
                    <p class="stat-number"><?php echo esc_html($pending_count); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=anime-sync-queue'); ?>" class="stat-link">查看佇列 →</a>
                </div>
            </div>

            <div class="anime-sync-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stat-content">
                    <h3>已通過</h3>
                    <p class="stat-number"><?php echo esc_html($approved_count); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=anime-sync-queue&status=approved'); ?>" class="stat-link">查看已通過 →</a>
                </div>
            </div>
            
            <div class="anime-sync-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-megaphone"></span>
                </div>
                <div class="stat-content">
                    <h3>已發布</h3>
                    <p class="stat-number"><?php echo esc_html($published_total); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=anime-sync-published'); ?>" class="stat-link">查看動畫 →</a>
                </div>
            </div>
            
            <div class="anime-sync-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="stat-content">
                    <h3>錯誤 (7天)</h3>
                    <p class="stat-number"><?php echo esc_html($log_stats['error'] + $log_stats['critical']); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=anime-sync-logs'); ?>" class="stat-link">查看日誌 →</a>
                </div>
            </div>
        </div>
        
        <div class="anime-sync-quick-actions">
            <h2>快速操作</h2>
            <div class="quick-actions-grid">
                <a href="<?php echo admin_url('admin.php?page=anime-sync-import'); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-download"></span> 匯入動畫
                </a>
                <a href="<?php echo admin_url('admin.php?page=anime-sync-queue'); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-list-view"></span> 審核佇列
                </a>
                <a href="<?php echo admin_url('admin.php?page=anime-sync-settings'); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-admin-settings"></span> 設定
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=anime'); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-edit"></span> 管理動畫
                </a>
            </div>
        </div>

        <?php /* ── 繁簡轉換器狀態測試區塊 ───────────────────────────── */ ?>
        <div class="anime-sync-converter-test" style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
            <h2>繁簡轉換器狀態測試</h2>
            <?php
            if ( class_exists('Anime_Sync_CN_Converter') ) {
                // ✅ 改為實例呼叫，避免 non-static fatal error
                $cn_converter = new Anime_Sync_CN_Converter();
                $stats    = $cn_converter->get_stats();
                $test_cn  = "动画制作、脚本、监督、角色设计";
                $test_tw  = $cn_converter->convert( $test_cn );
                $is_working = ( $test_cn !== $test_tw );
                ?>
                <table class="wp-list-table widefat fixed">
                    <tr>
                        <th style="width: 200px;">字典檔案路徑</th>
                        <td><code><?php echo esc_html($stats['dict_path']); ?></code></td>
                    </tr>
                    <tr>
                        <th>檔案狀態</th>
                        <td>
                            <?php if ($stats['file_size'] > 0): ?>
                                <span style="color: green;">✓ 檔案存在 (<?php echo size_format($stats['file_size']); ?>)</span>
                            <?php else: ?>
                                <span style="color: red;">✗ 檔案不存在或為空</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>詞條總數</th>
                        <td><?php echo esc_html($stats['entry_count']); ?> 條</td>
                    </tr>
                    <tr>
                        <th>轉換測試</th>
                        <td>
                            原文：<?php echo esc_html($test_cn); ?><br>
                            結果：<strong><?php echo esc_html($test_tw); ?></strong><br>
                            狀態：<?php echo $is_working
                                ? '<span style="color: green;">✓ 運作正常</span>'
                                : '<span style="color: red;">✗ 轉換無效（請檢查字典內容）</span>'; ?>
                        </td>
                    </tr>
                </table>
            <?php } else {
                echo '<p style="color: red;">錯誤：找不到 Anime_Sync_CN_Converter 類別。</p>';
            } ?>
        </div>
        
        <div class="anime-sync-recent-logs">
            <h2>最近日誌 (7天)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 120px;">等級</th>
                        <th>訊息</th>
                        <th style="width: 180px;">時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $recent_logs = $logger->get_recent_logs(10);
                    if (!empty($recent_logs)) {
                        foreach ($recent_logs as $log) {
                            $level_class = 'log-level-' . esc_attr($log['level']);
                            ?>
                            <tr>
                                <td>
                                    <span class="log-level-badge <?php echo $level_class; ?>">
                                        <?php echo esc_html(strtoupper($log['level'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['message']); ?></td>
                                <td><?php echo esc_html($log['created_at']); ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="3" style="text-align: center; color: #999;">尚無日誌記錄</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="anime-sync-system-info">
            <h2>系統資訊</h2>
            <table class="wp-list-table widefat fixed">
                <tbody>
                    <tr>
                        <th style="width: 200px;">插件版本</th>
                        <td><?php echo defined('ANIME_SYNC_VERSION') ? esc_html(ANIME_SYNC_VERSION) : '1.0.0'; ?></td>
                    </tr>
                    <tr>
                        <th>WordPress 版本</th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th>PHP 版本</th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th>記憶體使用</th>
                        <td>
                            <?php 
                            if (class_exists('Anime_Sync_Performance')) {
                                $memory = Anime_Sync_Performance::get_memory_usage();
                                echo esc_html($memory['current'] . ' / ' . $memory['limit']);
                            }
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
    </div>
</div>

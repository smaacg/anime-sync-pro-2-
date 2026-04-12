<?php
/**
 * Error Logs Page
 * 
 * @package Anime_Sync_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

$logger = new Anime_Sync_Error_Logger();

// 取得篩選參數
$level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : null;
$limit = isset($_GET['limit']) ? absint($_GET['limit']) : 100;

// 取得日誌
$logs = $logger->get_recent_logs($limit, $level);
$stats = $logger->get_statistics(7);
?>

<div class="wrap">
    <h1>錯誤日誌</h1>
    
    <div class="anime-sync-logs">
        
        <!-- 統計 -->
        <div class="log-stats">
            <h2>統計 (最近 7 天)</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-label">總計</span>
                    <span class="stat-value"><?php echo esc_html($stats['total']); ?></span>
                </div>
                <div class="stat-item stat-info">
                    <span class="stat-label">資訊</span>
                    <span class="stat-value"><?php echo esc_html($stats['info']); ?></span>
                </div>
                <div class="stat-item stat-warning">
                    <span class="stat-label">警告</span>
                    <span class="stat-value"><?php echo esc_html($stats['warning']); ?></span>
                </div>
                <div class="stat-item stat-error">
                    <span class="stat-label">錯誤</span>
                    <span class="stat-value"><?php echo esc_html($stats['error']); ?></span>
                </div>
                <div class="stat-item stat-critical">
                    <span class="stat-label">嚴重</span>
                    <span class="stat-value"><?php echo esc_html($stats['critical']); ?></span>
                </div>
            </div>
        </div>
        
        <!-- 篩選器 -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <select id="log-level-filter">
                    <option value="">所有等級</option>
                    <option value="info" <?php selected($level, 'info'); ?>>資訊</option>
                    <option value="warning" <?php selected($level, 'warning'); ?>>警告</option>
                    <option value="error" <?php selected($level, 'error'); ?>>錯誤</option>
                    <option value="critical" <?php selected($level, 'critical'); ?>>嚴重</option>
                </select>
                
                <select id="log-limit">
                    <option value="100" <?php selected($limit, 100); ?>>最近 100 筆</option>
                    <option value="500" <?php selected($limit, 500); ?>>最近 500 筆</option>
                    <option value="1000" <?php selected($limit, 1000); ?>>最近 1000 筆</option>
                </select>
                
                <button type="button" id="filter-logs" class="button">篩選</button>
                <button type="button" id="clear-old-logs" class="button">清除 30 天前日誌</button>
            </div>
        </div>
        
        <!-- 日誌列表 -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 100px;">等級</th>
                    <th>訊息</th>
                    <th style="width: 180px;">時間</th>
                    <th style="width: 80px;">詳情</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <?php $level_class = 'log-level-' . esc_attr($log['level']); ?>
                        <tr>
                            <td>
                                <span class="log-level-badge <?php echo $level_class; ?>">
                                    <?php echo esc_html(strtoupper($log['level'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log['message']); ?></td>
                            <td><?php echo esc_html($log['created_at']); ?></td>
                            <td>
                                <?php if (!empty($log['context'])): ?>
                                    <button type="button" class="button button-small show-context" 
                                            data-context='<?php echo esc_attr(json_encode($log['context'], JSON_UNESCAPED_UNICODE)); ?>'>
                                        查看
                                    </button>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: #999;">
                            <span class="dashicons dashicons-yes-alt" style="font-size: 48px;"></span>
                            <p style="margin-top: 10px;">目前沒有日誌記錄</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
    </div>
</div>

<!-- Context Modal -->
<div id="context-modal" style="display: none;">
    <div class="context-modal-overlay"></div>
    <div class="context-modal-content">
        <div class="context-modal-header">
            <h3>日誌詳情</h3>
            <button type="button" class="close-modal">&times;</button>
        </div>
        <div class="context-modal-body">
            <pre id="context-data"></pre>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // 篩選
    $('#filter-logs').on('click', function() {
        const level = $('#log-level-filter').val();
        const limit = $('#log-limit').val();
        let url = '?page=anime-sync-logs';
        if (level) url += '&level=' + level;
        if (limit) url += '&limit=' + limit;
        window.location.href = url;
    });
    
    // 清除舊日誌
    $('#clear-old-logs').on('click', function() {
        if (!confirm('確定要清除 30 天前的日誌嗎？')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'anime_clear_old_logs',
                nonce: '<?php echo wp_create_nonce("anime_sync_ajax"); ?>'
            },
            success: function(response) {
                alert('已清除 ' + response.data.count + ' 筆舊日誌');
                location.reload();
            }
        });
    });
    
    // 顯示詳情
    $('.show-context').on('click', function() {
        const context = $(this).data('context');
        $('#context-data').text(JSON.stringify(context, null, 2));
        $('#context-modal').fadeIn();
    });
    
    $('.close-modal, .context-modal-overlay').on('click', function() {
        $('#context-modal').fadeOut();
    });
});
</script>

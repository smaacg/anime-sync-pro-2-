<?php
/**
 * Review Preview Page
 * 
 * @package Anime_Sync_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

$queue_id = isset($_GET['queue_id']) ? absint($_GET['queue_id']) : 0;

if (!$queue_id) {
    wp_die('無效的佇列 ID');
}

$review_queue = new Anime_Sync_Review_Queue();
$item = $review_queue->get_item($queue_id);

if (!$item) {
    wp_die('找不到佇列項目');
}

$data = is_array( $item['api_data'] ?? null ) ? $item['api_data'] : [];

$title       = is_array( $data['title'] ?? null ) ? $data['title'] : [];
$score       = is_array( $data['score'] ?? null ) ? $data['score'] : [];
$synopsis    = is_array( $data['synopsis'] ?? null ) ? $data['synopsis'] : [];
$music       = is_array( $data['music'] ?? null ) ? $data['music'] : [];
$studios     = is_array( $data['studios'] ?? null ) ? $data['studios'] : [];
$genres      = is_array( $data['genres'] ?? null ) ? $data['genres'] : [];
$cover_image = $data['cover_image'] ?? ( $data['anime_cover_image'] ?? '' );
$anilist_id  = (int) ( $data['id_anilist'] ?? ( $data['anilist_id'] ?? 0 ) );
$mal_id      = (int) ( $data['id_mal'] ?? ( $data['mal_id'] ?? 0 ) );
$bangumi_id  = (int) ( $data['id_bangumi'] ?? ( $data['bangumi_id'] ?? 0 ) );
?>

<div class="wrap">
    <h1>預覽動漫資料</h1>
    
    <div class="anime-sync-preview">
        
        <!-- 頂部操作列 -->
        <div class="preview-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-queue' ) ); ?>" 
               class="button">
                ← 返回佇列
            </a>
            
            <?php if ($item['status'] === 'pending'): ?>
            <button type="button" 
                    class="button button-primary approve-item" 
                    data-queue-id="<?php echo esc_attr($queue_id); ?>">
                ✓ 通過並建立草稿
            </button>
            <?php endif; ?>
            
            <button type="button" 
                    class="button button-link-delete delete-item" 
                    data-queue-id="<?php echo esc_attr($queue_id); ?>">
                刪除
            </button>
        </div>
        
        <!-- 預覽內容 -->
        <div class="preview-content">
            
            <!-- 封面與基本資訊 -->
            <div class="preview-header">
                <?php if (!empty($cover_image)): ?>
                <div class="preview-cover">
                    <img src="<?php echo esc_url($cover_image); ?>" 
                         alt="<?php echo esc_attr($title['romaji'] ?? ''); ?>">
                </div>
                <?php endif; ?>
                
                <div class="preview-title-block">
                    <h2><?php echo esc_html($title['chinese_traditional'] ?? $title['romaji'] ?? ''); ?></h2>
                    
                    <div class="title-variants">
                        <?php if (!empty($title['romaji'])): ?>
                            <p><strong>羅馬拼音：</strong><?php echo esc_html($title['romaji']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($title['english'])): ?>
                            <p><strong>英文：</strong><?php echo esc_html($title['english']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($title['native'])): ?>
                            <p><strong>原文：</strong><?php echo esc_html($title['native']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="preview-meta">
                        <span class="meta-badge"><?php echo esc_html($data['format'] ?? 'TV'); ?></span>
                        <span class="meta-badge"><?php echo esc_html($data['season'] ?? ''); ?> <?php echo esc_html($data['year'] ?? ''); ?></span>
                        <span class="meta-badge"><?php echo esc_html($data['episodes'] ?? '?'); ?> 集</span>
                        <span class="meta-badge"><?php echo esc_html($data['duration'] ?? '?'); ?> 分鐘</span>
                    </div>
                </div>
            </div>
            
            <!-- 評分 -->
            <div class="preview-section">
                <h3>評分</h3>
                <div class="score-grid">
                    <div class="score-item">
                        <span class="score-label">AniList</span>
                        <span class="score-value"><?php echo esc_html($score['anilist'] ?? 0); ?>/100</span>
                    </div>
                    <div class="score-item">
                        <span class="score-label">MyAnimeList</span>
                        <span class="score-value"><?php echo esc_html($score['mal'] ?? 0); ?>/10</span>
                    </div>
                    <div class="score-item">
                        <span class="score-label">Bangumi</span>
                        <span class="score-value"><?php echo esc_html($score['bangumi'] ?? 0); ?>/10</span>
                    </div>
                    <div class="score-item">
                        <span class="score-label">人氣</span>
                        <span class="score-value"><?php echo esc_html(number_format($data['popularity'] ?? 0)); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- 簡介 -->
            <div class="preview-section">
                <h3>簡介</h3>
                <?php if (!empty($synopsis['chinese_traditional'])): ?>
                    <div class="synopsis-block">
                        <h4>繁體中文</h4>
                        <p><?php echo wp_kses_post($synopsis['chinese_traditional']); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($synopsis['english'])): ?>
                    <div class="synopsis-block">
                        <h4>English</h4>
                        <p><?php echo wp_kses_post($synopsis['english']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 製作資訊 -->
            <div class="preview-section">
                <h3>製作資訊</h3>
                <table class="wp-list-table widefat fixed">
                    <tr>
                        <th style="width: 150px;">製作公司</th>
                        <td>
                            <?php 
                            if (!empty($studios)) {
                                $studio_names = array_column($studios, 'name');
                                echo esc_html(implode(', ', $studio_names));
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>類型</th>
                        <td>
                            <?php 
                            if (!empty($genres)) {
                                echo esc_html(implode(', ', $genres));
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>原作類型</th>
                        <td><?php echo esc_html($data['source'] ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <th>狀態</th>
                        <td><?php echo esc_html($data['status'] ?? '—'); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- 音樂 -->
            <?php if (!empty($music['openings']) || !empty($music['endings'])): ?>
            <div class="preview-section">
                <h3>音樂</h3>
                
                <?php if (!empty($music['openings'])): ?>
                <div class="music-block">
                    <h4>OP 片頭曲</h4>
                    <ul>
                        <?php foreach ($music['openings'] as $op): ?>
                            <li>
                                <?php echo esc_html($op['title'] ?? ''); ?>
                                <?php if (!empty($op['artist'])): ?>
                                    - <?php echo esc_html($op['artist']); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($music['endings'])): ?>
                <div class="music-block">
                    <h4>ED 片尾曲</h4>
                    <ul>
                        <?php foreach ($music['endings'] as $ed): ?>
                            <li>
                                <?php echo esc_html($ed['title'] ?? ''); ?>
                                <?php if (!empty($ed['artist'])): ?>
                                    - <?php echo esc_html($ed['artist']); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- 資料來源 ID -->
            <div class="preview-section">
                <h3>資料來源 ID</h3>
                <table class="wp-list-table widefat fixed">
                    <tr>
                        <th style="width: 150px;">AniList</th>
                        <td>
                            <a href="https://anilist.co/anime/<?php echo esc_attr($anilist_id); ?>" 
                               target="_blank">
                                <?php echo esc_html($anilist_id); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th>MyAnimeList</th>
                        <td>
                            <?php if (!empty($mal_id)): ?>
                                <a href="https://myanimelist.net/anime/<?php echo esc_attr($mal_id); ?>" 
                                   target="_blank">
                                    <?php echo esc_html($mal_id); ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Bangumi</th>
                        <td>
                            <?php if (!empty($bangumi_id)): ?>
                                <a href="https://bgm.tv/subject/<?php echo esc_attr($bangumi_id); ?>" 
                                   target="_blank">
                                    <?php echo esc_html($bangumi_id); ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- 原始 JSON 資料 -->
            <div class="preview-section">
                <h3>原始 JSON 資料</h3>
                <details>
                    <summary>點擊展開</summary>
                    <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;"><?php 
                        echo esc_html(wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )); 
                    ?></pre>
                </details>
            </div>
            
        </div>
        
    </div>
</div>

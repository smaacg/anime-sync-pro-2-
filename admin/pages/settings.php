<?php
/**
 * Admin Page: Settings
 *
 * File: admin/pages/settings.php
 * Plugin-wide configuration: API keys, cron schedule, cache control,
 * log management, map download, and debug tools.
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ───────────────────────────────────────────────
    Handle form save (Settings API fallback)
─────────────────────────────────────────────── */
$saved = false;
if (
    isset( $_POST['anime_sync_settings_nonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['anime_sync_settings_nonce'] ) ), 'anime_sync_save_settings' )
) {
    $fields = [
        'anime_sync_site_name'          => 'sanitize_text_field',
        'anime_sync_site_url'           => 'esc_url_raw',
        'anime_sync_daily_hour_taipei'  => 'intval',
        'anime_sync_weekly_day'         => 'sanitize_text_field',
        'anime_sync_weekly_hour_taipei' => 'intval',
        'anime_sync_rating_batch_size'  => 'intval',
        'anime_sync_log_retention_days' => 'intval',
        'anime_sync_debug_mode'         => 'intval',
        'anime_sync_cache_ttl_hours'    => 'intval',
    ];
    foreach ( $fields as $key => $sanitizer ) {
        $raw = isset( $_POST[ $key ] ) ? $_POST[ $key ] : '';
        update_option( $key, $sanitizer( $raw ) );
    }
    // Reschedule cron after time change
    do_action( 'anime_sync_reschedule_cron' );
    $saved = true;
}

/* ───────────────────────────────────────────────
    Read current values
─────────────────────────────────────────────── */
$site_name          = get_option( 'anime_sync_site_name',           get_bloginfo( 'name' ) );
$site_url           = get_option( 'anime_sync_site_url',            get_site_url() );
$daily_hour         = (int) get_option( 'anime_sync_daily_hour_taipei',  3 );
$weekly_day         = get_option( 'anime_sync_weekly_day',          'monday' );
$weekly_hour        = (int) get_option( 'anime_sync_weekly_hour_taipei', 4 );
$rating_batch_size  = (int) get_option( 'anime_sync_rating_batch_size',  25 );
$log_retention      = (int) get_option( 'anime_sync_log_retention_days', 30 );
$debug_mode         = (int) get_option( 'anime_sync_debug_mode',          0 );
$cache_ttl          = (int) get_option( 'anime_sync_cache_ttl_hours',    24 );

/* ───────────────────────────────────────────────
    Map status (調用修正後的 Mapper 靜態方法)
─────────────────────────────────────────────── */
$map_status = Anime_Sync_ID_Mapper::get_map_status();

/* ───────────────────────────────────────────────
    Log file info
─────────────────────────────────────────────── */
$upload_dir  = wp_upload_dir();
$log_dir     = trailingslashit( $upload_dir['basedir'] ) . 'anime-sync-pro/logs/';
$log_files   = glob( $log_dir . '*.log' ) ?: [];
$log_count   = count( $log_files );
$log_size    = 0;
foreach ( $log_files as $lf ) { 
    if ( file_exists( $lf ) ) {
        $log_size += filesize( $lf ); 
    }
}
$log_size_kb = round( $log_size / 1024, 1 );

/* ───────────────────────────────────────────────
    Cron next run times
─────────────────────────────────────────────── */
$next_daily  = wp_next_scheduled( 'anime_sync_daily_update' );
$next_weekly = wp_next_scheduled( 'anime_sync_weekly_update' );
$next_rating = wp_next_scheduled( 'anime_sync_rating_batch' );

$days_of_week = [
    'monday'    => __( '星期一', 'anime-sync-pro' ),
    'tuesday'   => __( '星期二', 'anime-sync-pro' ),
    'wednesday' => __( '星期三', 'anime-sync-pro' ),
    'thursday'  => __( '星期四', 'anime-sync-pro' ),
    'friday'    => __( '星期五', 'anime-sync-pro' ),
    'saturday'  => __( '星期六', 'anime-sync-pro' ),
    'sunday'    => __( '星期日', 'anime-sync-pro' ),
];
?>

<div class="wrap anime-sync-settings">

    <h1><?php esc_html_e( '設定', 'anime-sync-pro' ); ?></h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( '設定已儲存。', 'anime-sync-pro' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'anime_sync_save_settings', 'anime_sync_settings_nonce' ); ?>

        <div class="anime-sync-settings-card">
            <h2><?php esc_html_e( '網站識別 (User-Agent)', 'anime-sync-pro' ); ?></h2>
            <p class="description">
                <?php esc_html_e( '用於 API 請求的 User-Agent 標頭，部分 API（如 Bangumi）要求必填。', 'anime-sync-pro' ); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="anime_sync_site_name"><?php esc_html_e( '網站名稱', 'anime-sync-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="anime_sync_site_name" name="anime_sync_site_name" value="<?php echo esc_attr( $site_name ); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="anime_sync_site_url"><?php esc_html_e( '網站 URL', 'anime-sync-pro' ); ?></label>
                    </th>
                    <td>
                        <input type="url" id="anime_sync_site_url" name="anime_sync_site_url" value="<?php echo esc_attr( $site_url ); ?>" class="regular-text" />
                        <p class="description">
                            <?php
                            printf(
                                esc_html__( '產生的 UA：%s', 'anime-sync-pro' ),
                                '<code>' . esc_html( sanitize_text_field( $site_name ) . '/1.0 (' . esc_url_raw( $site_url ) . ')' ) . '</code>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="anime-sync-settings-card">
            <h2><?php esc_html_e( '排程設定（台北時間）', 'anime-sync-pro' ); ?></h2>
            <p class="description"><?php esc_html_e( '所有時間均為台北時間（UTC+8）。', 'anime-sync-pro' ); ?></p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="anime_sync_daily_hour_taipei"><?php esc_html_e( '每日更新時間', 'anime-sync-pro' ); ?></label></th>
                    <td>
                        <select id="anime_sync_daily_hour_taipei" name="anime_sync_daily_hour_taipei">
                            <?php for ( $h = 0; $h < 24; $h++ ) : ?>
                                <option value="<?php echo $h; ?>" <?php selected( $daily_hour, $h ); ?>><?php echo sprintf( '%02d:00', $h ); ?></option>
                            <?php endfor; ?>
                        </select>
                        <?php if ( $next_daily ) : ?>
                            <span class="description" style="margin-left:12px;">
                                <?php printf( esc_html__( '下次執行：%s', 'anime-sync-pro' ), esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $next_daily ), 'Y-m-d H:i' ) ) ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="anime_sync_weekly_day"><?php esc_html_e( '每週更新日', 'anime-sync-pro' ); ?></label></th>
                    <td>
                        <select id="anime_sync_weekly_day" name="anime_sync_weekly_day">
                            <?php foreach ( $days_of_week as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $weekly_day, $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="anime_sync_rating_batch_size"><?php esc_html_e( '評分批次大小', 'anime-sync-pro' ); ?></label></th>
                    <td>
                        <input type="number" id="anime_sync_rating_batch_size" name="anime_sync_rating_batch_size" value="<?php echo esc_attr( $rating_batch_size ); ?>" min="5" max="100" class="small-text" />
                        <p class="description"><?php esc_html_e( '建議 20–30。', 'anime-sync-pro' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="anime-sync-settings-card">
            <h2><?php esc_html_e( '記錄與偵錯', 'anime-sync-pro' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="anime_sync_log_retention_days"><?php esc_html_e( '記錄保留天數', 'anime-sync-pro' ); ?></label></th>
                    <td>
                        <input type="number" id="anime_sync_log_retention_days" name="anime_sync_log_retention_days" value="<?php echo esc_attr( $log_retention ); ?>" min="1" max="365" class="small-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( '偵錯模式', 'anime-sync-pro' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="anime_sync_debug_mode" value="1" <?php checked( $debug_mode, 1 ); ?> />
                            <?php esc_html_e( '啟用詳細偵錯記錄', 'anime-sync-pro' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e( '儲存設定', 'anime-sync-pro' ); ?></button>
        </p>
    </form>

    <div class="anime-sync-settings-card">
        <h2><?php esc_html_e( 'Bangumi ID 對照表 (anime_map.json)', 'anime-sync-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( '檔案狀態', 'anime-sync-pro' ); ?></th>
                <td>
                    <?php if ( $map_status['exists'] ) : ?>
                        <span style="color:#46b450;">&#10003;</span>
                        <?php printf(
                            esc_html__( '存在 · %s 筆映射 · 大小 %s KB · 上次更新 %s', 'anime-sync-pro' ),
                            number_format( $map_status['entry_count'] ),
                            number_format( $map_status['file_size'] / 1024, 1 ),
                            esc_html( $map_status['last_updated'] ?: __( '不明', 'anime-sync-pro' ) )
                        ); ?>
                    <?php else : ?>
                        <span style="color:#dc3232;">&#10007; <?php esc_html_e( '檔案不存在，請點擊下方下載', 'anime-sync-pro' ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '手動更新', 'anime-sync-pro' ); ?></th>
                <td>
                    <button type="button" id="btn-update-map" class="button button-secondary"><?php esc_html_e( '立即下載 / 更新對照表', 'anime-sync-pro' ); ?></button>
                    <span id="update-map-result" style="margin-left:12px;"></span>
                </td>
            </tr>
        </table>
    </div>

    <div class="anime-sync-settings-card">
        <h2><?php esc_html_e( '手動功能', 'anime-sync-pro' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( '快取管理', 'anime-sync-pro' ); ?></th>
                <td>
                    <button type="button" id="btn-clear-cache" class="button button-secondary"><?php esc_html_e( '清除外掛快取', 'anime-sync-pro' ); ?></button>
                    <span id="clear-cache-result" style="margin-left:10px;"></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( '記錄管理', 'anime-sync-pro' ); ?></th>
                <td>
                    <button type="button" id="btn-clear-logs" class="button button-secondary"><?php esc_html_e( '清除所有 Log 檔案', 'anime-sync-pro' ); ?></button>
                    <span id="clear-logs-result" style="margin-left:10px;"></span>
                </td>
            </tr>
        </table>
    </div>

    <div class="anime-sync-settings-card">
        <h2><?php esc_html_e( '系統資訊', 'anime-sync-pro' ); ?></h2>
        <table class="form-table">
            <tr><th scope="row"><?php esc_html_e( 'PHP 版本', 'anime-sync-pro' ); ?></th><td><?php echo PHP_VERSION; ?></td></tr>
            <tr><th scope="row"><?php esc_html_e( 'WordPress 版本', 'anime-sync-pro' ); ?></th><td><?php echo get_bloginfo( 'version' ); ?></td></tr>
            <tr><th scope="row"><?php esc_html_e( '外掛版本', 'anime-sync-pro' ); ?></th><td><?php echo defined('ANIME_SYNC_PRO_VERSION') ? ANIME_SYNC_PRO_VERSION : '1.0.0'; ?></td></tr>
        </table>
    </div>

</div>

<style>
.anime-sync-settings-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px 24px; margin-top: 20px; max-width: 900px; }
.anime-sync-settings-card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
</style>

<script>
( function( $ ) {
    'use strict';
    const ajaxParams = { nonce: '<?php echo wp_create_nonce("anime_sync_admin_nonce"); ?>' };

    $( '#btn-update-map' ).on( 'click', function () {
        const $btn = $( this );
        $btn.prop( 'disabled', true ).text( '下載中...' );
        $.post( ajaxurl, { action: 'anime_sync_update_map', nonce: ajaxParams.nonce }, function ( resp ) {
            $btn.prop( 'disabled', false ).text( '立即下載 / 更新對照表' );
            alert( resp.success ? '更新成功' : '更新失敗：' + resp.data );
            location.reload();
        } );
    } );

    $( '#btn-clear-cache' ).on( 'click', function () {
        $.post( ajaxurl, { action: 'anime_sync_clear_cache', nonce: ajaxParams.nonce }, function ( resp ) {
            $( '#clear-cache-result' ).text( resp.success ? '成功' : '失敗' ).fadeOut(2000);
        } );
    } );

    $( '#btn-clear-logs' ).on( 'click', function () {
        if(!confirm('確定要刪除所有記錄檔嗎？')) return;
        $.post( ajaxurl, { action: 'anime_sync_clear_logs', nonce: ajaxParams.nonce }, function ( resp ) {
            alert( resp.success ? '已清除' : '失敗' );
            location.reload();
        } );
    } );
} )( jQuery );
</script>
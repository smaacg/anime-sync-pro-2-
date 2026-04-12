<?php
/**
 * Installer Class
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Installer {

    /**
     * 插件啟用時執行
     */
    public function activate(): void {
        $this->create_tables();
        $this->set_default_options();
        $this->create_upload_dirs();
        $this->register_cpt_for_flush();
        flush_rewrite_rules();

        update_option( 'anime_sync_activated_at', current_time( 'mysql' ) );
        update_option( 'anime_sync_version', ANIME_SYNC_PRO_VERSION );
    }

    /**
     * 插件停用時執行
     */
    public function deactivate(): void {
        flush_rewrite_rules();
        delete_transient( 'anime_sync_pending_count' );
    }

    /**
     * 建立資料庫資料表
     */
    private function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // 審核佇列資料表
        $queue_table = $wpdb->prefix . 'anime_review_queue';
        $queue_sql   = "CREATE TABLE IF NOT EXISTS {$queue_table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            anilist_id  INT(11) UNSIGNED NOT NULL,
            title       VARCHAR(255) NOT NULL DEFAULT '',
            api_data    LONGBLOB,
            status      ENUM('pending','approved','rejected','published') NOT NULL DEFAULT 'pending',
            source      VARCHAR(20) NOT NULL DEFAULT 'manual',
            wp_post_id  BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            created_at  DATETIME NOT NULL,
            updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY anilist_id (anilist_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        // 錯誤日誌資料表
        $logs_table = $wpdb->prefix . 'anime_sync_logs';
        $logs_sql   = "CREATE TABLE IF NOT EXISTS {$logs_table} (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            level      ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info',
            message    TEXT NOT NULL,
            context    LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $queue_sql );
        dbDelta( $logs_sql );
    }

    /**
     * 設定預設選項
     */
    private function set_default_options(): void {
        $defaults = [
            'anime_sync_cn_method'           => 'dict',
            'anime_sync_image_method'        => 'api_url',
            'anime_sync_cdn_provider'        => 'cloudflare',
            'anime_sync_cdn_base_url'        => '',
            'anime_sync_api_delay'           => 1000,
            'anime_sync_batch_size'          => 15,
            'anime_sync_log_email_notify'    => 1,
            'anime_sync_log_retention_days'  => 30,
            'anime_sync_debug_mode'          => 0,
            'anime_sync_delete_on_uninstall' => 0,
        ];

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * 建立上傳目錄
     */
    private function create_upload_dirs(): void {
        $upload_dir = wp_upload_dir();

        $dirs = [
            $upload_dir['basedir'] . '/anime-sync-pro',
            $upload_dir['basedir'] . '/anime-covers',
            $upload_dir['basedir'] . '/anime-sync-cache',
        ];

        foreach ( $dirs as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
                file_put_contents( $dir . '/.htaccess', 'Options -Indexes' );
            }
        }
    }

    /**
     * ✅ 修正：移除重複的 register_post_type 呼叫
     * CPT 已在 anime-sync-pro.php 的 init hook 中註冊
     * 此處只排程刷新 rewrite rules
     */
    private function register_cpt_for_flush(): void {
        update_option( 'anime_sync_flush_rewrite', 1 );
    }
}

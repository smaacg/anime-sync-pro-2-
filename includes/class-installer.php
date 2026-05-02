<?php
/**
 * Installer Class
 *
 * @package Anime_Sync_Pro
 * @version 1.1.0 — 新增 anime_user_status + anime_user_status_stats 兩張表（巴哈級規模）
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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ────────────────────────────────────────────
        // 表 1：審核佇列
        // ────────────────────────────────────────────
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
        dbDelta( $queue_sql );

        // ────────────────────────────────────────────
        // 表 2：錯誤日誌
        // ────────────────────────────────────────────
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
        dbDelta( $logs_sql );

        // ────────────────────────────────────────────
        // 表 3：評分資料表
        // ────────────────────────────────────────────
        $ratings_table = $wpdb->prefix . 'anime_ratings';
        $ratings_sql   = "CREATE TABLE IF NOT EXISTS {$ratings_table} (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            anime_id         BIGINT(20) UNSIGNED NOT NULL,
            user_id          BIGINT(20) UNSIGNED NOT NULL,
            score_story      DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            score_music      DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            score_animation  DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            score_voice      DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            score_overall    DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            weight           DECIMAL(4,2) NOT NULL DEFAULT 1.00,
            created_at       DATETIME NOT NULL,
            updated_at       DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_anime (user_id, anime_id),
            KEY anime_id (anime_id),
            KEY score_overall (score_overall)
        ) {$charset_collate};";
        dbDelta( $ratings_sql );

        // ────────────────────────────────────────────
        // 表 4：使用者追蹤狀態（取代 user_meta 'anime_user_data' JSON）
        // 巴哈級規模設計：百萬會員、五千萬筆紀錄
        // ────────────────────────────────────────────
        $user_status_table = $wpdb->prefix . 'anime_user_status';
        $user_status_sql   = "CREATE TABLE IF NOT EXISTS {$user_status_table} (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       BIGINT(20) UNSIGNED NOT NULL,
            anime_id      BIGINT(20) UNSIGNED NOT NULL,
            status        TINYINT UNSIGNED DEFAULT NULL,
            progress      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            favorited     TINYINT(1) NOT NULL DEFAULT 0,
            fullcleared   TINYINT(1) NOT NULL DEFAULT 0,
            started_at    DATETIME DEFAULT NULL,
            completed_at  DATETIME DEFAULT NULL,
            note          VARCHAR(500) DEFAULT NULL,
            is_private    TINYINT(1) NOT NULL DEFAULT 0,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_anime (user_id, anime_id),
            KEY user_status (user_id, status, updated_at),
            KEY user_favorited (user_id, favorited, updated_at),
            KEY anime_status (anime_id, status),
            KEY anime_favorited (anime_id, favorited),
            KEY updated_at (updated_at)
        ) {$charset_collate};";
        dbDelta( $user_status_sql );

        // ────────────────────────────────────────────
        // 表 5：使用者追蹤狀態彙總（排行榜預計算）
        // 由 cron 每 15 分鐘重算
        // ────────────────────────────────────────────
        $us_stats_table = $wpdb->prefix . 'anime_user_status_stats';
        $us_stats_sql   = "CREATE TABLE IF NOT EXISTS {$us_stats_table} (
            anime_id        BIGINT(20) UNSIGNED NOT NULL,
            want_count      INT UNSIGNED NOT NULL DEFAULT 0,
            watching_count  INT UNSIGNED NOT NULL DEFAULT 0,
            completed_count INT UNSIGNED NOT NULL DEFAULT 0,
            dropped_count   INT UNSIGNED NOT NULL DEFAULT 0,
            favorited_count INT UNSIGNED NOT NULL DEFAULT 0,
            total_count     INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (anime_id),
            KEY watching_count (watching_count),
            KEY favorited_count (favorited_count),
            KEY total_count (total_count)
        ) {$charset_collate};";
        dbDelta( $us_stats_sql );
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
                file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
            }
        }
    }

    /**
     * CPT 已在 anime-sync-pro.php 的 init hook 中註冊
     */
    private function register_cpt_for_flush(): void {
        update_option( 'anime_sync_flush_rewrite', 1 );
    }
}

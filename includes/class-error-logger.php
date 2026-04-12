<?php
/**
 * Error Logger
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Error_Logger {

    private $wpdb;
    private $table_name;

    const LEVEL_INFO     = 'info';
    const LEVEL_WARNING  = 'warning';
    const LEVEL_ERROR    = 'error';
    const LEVEL_CRITICAL = 'critical';

    public function __construct() {
        global $wpdb;
        $this->wpdb       = $wpdb;
        $this->table_name = $wpdb->prefix . 'anime_sync_logs';
    }

    // =========================================================================
    // 實例方法
    // =========================================================================

    public function log( string $level, string $message, array $context = [] ): bool {
        $valid_levels = [
            self::LEVEL_INFO,
            self::LEVEL_WARNING,
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL,
        ];

        if ( ! in_array( $level, $valid_levels, true ) ) {
            $level = self::LEVEL_INFO;
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'level'      => $level,
                'message'    => sanitize_text_field( $message ),
                'context'    => wp_json_encode( $context, JSON_UNESCAPED_UNICODE ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        if ( $level === self::LEVEL_CRITICAL ) {
            $this->send_critical_email( $message, $context );
        }

        return $result !== false;
    }

    public function get_recent_logs( int $limit = 100, ?string $level = null ): array {
        if ( ! empty( $level ) ) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE level = %s ORDER BY created_at DESC LIMIT %d",
                $level,
                absint( $limit )
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d",
                absint( $limit )
            );
        }

        $logs = $this->wpdb->get_results( $query, ARRAY_A );

        foreach ( $logs as &$log ) {
            if ( ! empty( $log['context'] ) ) {
                $log['context'] = json_decode( $log['context'], true );
            }
        }

        return $logs;
    }

    public function delete_old_logs( int $days = 30 ): int {
        $date   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $date
            )
        );
        return (int) $result;
    }

    public function get_statistics( int $days = 7 ): array {
        $date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT level, COUNT(*) as count FROM {$this->table_name}
                 WHERE created_at >= %s GROUP BY level",
                $date
            ),
            ARRAY_A
        );

        $stats = [
            'info'     => 0,
            'warning'  => 0,
            'error'    => 0,
            'critical' => 0,
            'total'    => 0,
        ];

        foreach ( $results as $row ) {
            $stats[ $row['level'] ] = (int) $row['count'];
            $stats['total']        += (int) $row['count'];
        }

        return $stats;
    }

    private function send_critical_email( string $message, array $context ): void {
        if ( ! get_option( 'anime_sync_log_email_notify', true ) ) {
            return;
        }

        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );

        wp_mail(
            $admin_email,
            sprintf( '[%s] Anime Sync Pro - Critical Error', $site_name ),
            sprintf(
                "A critical error occurred:\n\nMessage: %s\n\nContext: %s\n\nTime: %s",
                $message,
                print_r( $context, true ),
                current_time( 'mysql' )
            )
        );
    }

    // =========================================================================
    // 靜態包裝方法（供其他類別用靜態方式呼叫）
    // =========================================================================

    public static function info( string $context, string $message ): void {
        ( new self() )->log( self::LEVEL_INFO, "[{$context}] {$message}" );
    }

    public static function warning( string $context, string $message ): void {
        ( new self() )->log( self::LEVEL_WARNING, "[{$context}] {$message}" );
    }

    public static function error( string $context, string $message ): void {
        ( new self() )->log( self::LEVEL_ERROR, "[{$context}] {$message}" );
    }

    public static function critical( string $context, string $message ): void {
        ( new self() )->log( self::LEVEL_CRITICAL, "[{$context}] {$message}" );
    }

    /**
     * 靜態包裝：清除舊日誌（供 admin AJAX 呼叫）
     */
    public static function clear_old_logs( int $days = 30 ): int {
        return ( new self() )->delete_old_logs( $days );
    }
}

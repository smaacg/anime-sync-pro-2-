<?php
/**
 * User Status Cron
 *
 * 每 15 分鐘重算 anime_user_status_stats 彙總表
 *
 * @package Anime_Sync_Pro
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_User_Status_Cron {

    const HOOK     = 'anime_sync_recalc_user_status_stats';
    const SCHEDULE = 'asp_every_15_minutes';

    public function __construct() {
        // 自訂 schedule
        add_filter( 'cron_schedules', [ $this, 'add_schedule' ] );

        // 註冊 cron handler
        add_action( self::HOOK, [ $this, 'recalc_stats' ] );

        // 啟用時排程（idempotent）
        add_action( 'init', [ $this, 'maybe_schedule' ] );

        // 提供手動觸發（admin / wp-cli）
        add_action( 'wp_ajax_asp_recalc_user_status_stats', [ $this, 'ajax_manual_recalc' ] );
    }

    /** 加入「每 15 分鐘」排程 */
    public function add_schedule( $schedules ) {
        $schedules[ self::SCHEDULE ] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => '每 15 分鐘（Anime Sync）',
        ];
        return $schedules;
    }

    /** 確保 cron 已被排程 */
    public function maybe_schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 60, self::SCHEDULE, self::HOOK );
        }
    }

    /** 取消排程（外掛停用時呼叫） */
    public static function unschedule(): void {
        $ts = wp_next_scheduled( self::HOOK );
        if ( $ts ) wp_unschedule_event( $ts, self::HOOK );
    }

    /**
     * 重算彙總表（核心）
     *
     * 策略：TRUNCATE + INSERT ... SELECT GROUP BY
     * 一句 SQL 完成全表彙總，無需逐筆迴圈。
     */
    public function recalc_stats(): int {
        global $wpdb;

        $main_table  = $wpdb->prefix . 'anime_user_status';
        $stats_table = $wpdb->prefix . 'anime_user_status_stats';

        $start_time = microtime( true );

        // 1. 清空彙總表
        $wpdb->query( "TRUNCATE TABLE {$stats_table}" );

        // 2. 從主表 GROUP BY anime_id 重算所有計數
        // status: 0=want, 1=watching, 2=completed, 3=dropped
        $sql = "
            INSERT INTO {$stats_table}
                (anime_id, want_count, watching_count, completed_count, dropped_count, favorited_count, total_count)
            SELECT
                anime_id,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS want_count,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS watching_count,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS dropped_count,
                SUM(favorited)                              AS favorited_count,
                COUNT(*)                                    AS total_count
            FROM {$main_table}
            GROUP BY anime_id
        ";

        $rows_affected = $wpdb->query( $sql );

        $duration = round( microtime( true ) - $start_time, 3 );

        // 清除排行榜快取
        $this->flush_ranking_cache();

        // 紀錄
        if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
            Anime_Sync_Error_Logger::info( 'User status stats recalculated', [
                'rows_affected' => (int) $rows_affected,
                'duration_sec'  => $duration,
            ] );
        }

        return (int) $rows_affected;
    }

    /** 手動觸發（管理員後台或 WP-CLI 用） */
    public function ajax_manual_recalc(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => '權限不足' ], 403 );
        }
        check_ajax_referer( 'asp_recalc_stats', 'nonce' );

        $count = $this->recalc_stats();
        wp_send_json_success( [
            'message' => "已重算 {$count} 部動畫的彙總",
            'count'   => $count,
        ] );
    }

    /** 清除所有排行榜快取（重算後讓使用者立刻看到新結果） */
    private function flush_ranking_cache(): void {
        $types  = [ 'favorited', 'watching', 'completed', 'want', 'dropped', 'total' ];
        $limits = [ 10, 20, 30, 50, 100 ];
        foreach ( $types as $t ) {
            foreach ( $limits as $l ) {
                wp_cache_delete( "us_rank_{$t}_{$l}", 'anime_user_status' );
            }
        }
    }
}

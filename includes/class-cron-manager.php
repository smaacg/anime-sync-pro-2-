<?php
/**
 * Cron Manager — 排程同步管理
 *
 * 負責：
 *  1. 註冊自訂 WP-Cron 排程間隔
 *  2. 每日自動更新評分 / 熱度 / 播出狀態
 *  3. 每週清除過期快取與舊日誌
 *  4. 季度批次匯入（新一季動畫自動入庫）
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Cron_Manager {

    // =========================================================================
    // 排程 Hook 名稱常數
    // =========================================================================
    const HOOK_DAILY_SCORE_UPDATE  = 'anime_sync_daily_score_update';
    const HOOK_WEEKLY_CLEANUP      = 'anime_sync_weekly_cleanup';
    const HOOK_SEASON_IMPORT       = 'anime_sync_season_auto_import';
    const HOOK_UPDATE_MAP          = 'anime_sync_update_anime_map';

    private Anime_Sync_Import_Manager $import_manager;
    private Anime_Sync_Error_Logger   $logger;

    public function __construct( Anime_Sync_Import_Manager $import_manager ) {
        $this->import_manager = $import_manager;
        $this->logger         = new Anime_Sync_Error_Logger();

        // 註冊自訂排程間隔
        add_filter( 'cron_schedules', [ $this, 'add_custom_schedules' ] );

        // 綁定排程 Hook 到對應處理方法
        add_action( self::HOOK_DAILY_SCORE_UPDATE, [ $this, 'run_daily_score_update' ] );
        add_action( self::HOOK_WEEKLY_CLEANUP,      [ $this, 'run_weekly_cleanup' ] );
        add_action( self::HOOK_SEASON_IMPORT,       [ $this, 'run_season_auto_import' ] );
        add_action( self::HOOK_UPDATE_MAP,          [ $this, 'run_update_map' ] );
    }

    // =========================================================================
    // 排程間隔定義
    // =========================================================================

    /**
     * 新增自訂 WP-Cron 排程間隔。
     */
    public function add_custom_schedules( array $schedules ): array {
        $schedules['anime_sync_twice_daily'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __( 'Anime Sync: 每12小時', 'anime-sync-pro' ),
        ];
        $schedules['anime_sync_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Anime Sync: 每週', 'anime-sync-pro' ),
        ];
        return $schedules;
    }

    // =========================================================================
    // 排程啟用 / 停用
    // =========================================================================

    /**
     * 插件啟用時：註冊所有排程事件。
     * 由 anime-sync-pro.php 的 register_activation_hook 呼叫。
     */
    public static function activate(): void {
        // 每日更新評分（時間由設定頁控制，預設 UTC 03:00）
        if ( ! wp_next_scheduled( self::HOOK_DAILY_SCORE_UPDATE ) ) {
            $daily_hour = (int) get_option( 'anime_sync_daily_hour', 3 );
            $today_utc  = strtotime( gmdate( "Y-m-d {$daily_hour}:00:00" ) );
            $start_time = $today_utc < time() ? $today_utc + DAY_IN_SECONDS : $today_utc;
            wp_schedule_event( $start_time, 'daily', self::HOOK_DAILY_SCORE_UPDATE );
        }

        // 每週清理（預設週日 UTC 04:00）
        if ( ! wp_next_scheduled( self::HOOK_WEEKLY_CLEANUP ) ) {
            wp_schedule_event( strtotime( 'next sunday 04:00:00' ), 'anime_sync_weekly', self::HOOK_WEEKLY_CLEANUP );
        }

        // Bangumi ID 地圖每週自動更新
        if ( ! wp_next_scheduled( self::HOOK_UPDATE_MAP ) ) {
            wp_schedule_event( strtotime( 'next monday 02:00:00' ), 'anime_sync_weekly', self::HOOK_UPDATE_MAP );
        }
    }

    /**
     * 插件停用時：移除所有排程事件。
     * 由 anime-sync-pro.php 的 register_deactivation_hook 呼叫。
     */
    public static function deactivate(): void {
        $hooks = [
            self::HOOK_DAILY_SCORE_UPDATE,
            self::HOOK_WEEKLY_CLEANUP,
            self::HOOK_SEASON_IMPORT,
            self::HOOK_UPDATE_MAP,
        ];
        foreach ( $hooks as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }
    }

    // =========================================================================
    // 任務一：每日評分 / 熱度 / 狀態更新
    // =========================================================================

    /**
     * 對「連載中」與「近期完結」動畫執行評分同步。
     * 使用 Anime_Sync_Performance::batch_process() 分批處理，避免逾時。
     */
    public function run_daily_score_update(): void {
        Anime_Sync_Performance::set_time_limit( 300 );
        Anime_Sync_Performance::increase_memory_limit( '256M' );

        $this->logger->log( 'info', '每日評分更新開始' );

        $batch_size = (int) get_option( 'anime_sync_batch_size', 15 );

        // 查詢連載中 + 近期 90 天內完結的動畫
        $query = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'anime_status',
                    'value'   => 'RELEASING',
                    'compare' => '=',
                ],
                [
                    'relation' => 'AND',
                    [
                        'key'     => 'anime_status',
                        'value'   => 'FINISHED',
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'anime_end_date',
                        'value'   => gmdate( 'Y-m-d', strtotime( '-90 days' ) ),
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ],
                ],
            ],
            'no_found_rows'  => true,
        ] );

        $post_ids = $query->posts;

        if ( empty( $post_ids ) ) {
            $this->logger->log( 'info', '每日評分更新：無需更新的動畫' );
            return;
        }

        $this->logger->log( 'info', sprintf( '每日評分更新：共 %d 部動畫待更新', count( $post_ids ) ) );

        $updated = 0;
        $failed  = 0;

        Anime_Sync_Performance::batch_process(
            $post_ids,
            function( int $post_id ) use ( &$updated, &$failed ): void {
                $anilist_id = (int) get_post_meta( $post_id, 'anime_anilist_id', true );
                if ( ! $anilist_id ) {
                    return;
                }

                // ✅ 使用 Rate Limiter 控制請求間隔
                ( new Anime_Sync_Rate_Limiter() )->wait_if_needed( 'anilist' );

                $result = $this->import_manager->import_single( $anilist_id, null, 'anilist' );

                if ( ! empty( $result['success'] ) ) {
                    $updated++;
                } else {
                    $failed++;
                    $this->logger->log( 'warning', '評分更新失敗', [
                        'post_id'    => $post_id,
                        'anilist_id' => $anilist_id,
                        'error'      => $result['message'] ?? '未知錯誤',
                    ] );
                }
            },
            $batch_size
        );

        $this->logger->log( 'info', sprintf(
            '每日評分更新完成：成功 %d / 失敗 %d',
            $updated,
            $failed
        ) );

        // 更新最後執行時間
        update_option( 'anime_sync_last_daily_run', current_time( 'mysql' ) );
    }

    // =========================================================================
    // 任務二：每週清理
    // =========================================================================

    /**
     * 清除過期快取、舊日誌、無用 transient。
     */
    public function run_weekly_cleanup(): void {
        $this->logger->log( 'info', '每週清理開始' );

        // 1. 清除舊日誌
        $retention_days = (int) get_option( 'anime_sync_log_retention_days', 30 );
        $deleted_logs   = $this->logger->delete_old_logs( $retention_days );
        $this->logger->log( 'info', "已清除 {$deleted_logs} 筆舊日誌" );

        // 2. 清除所有 anime_sync_cache_* transient
        Anime_Sync_Performance::clear_all_caches();

        // 3. 清除請求節流快取
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_anime_sync_last_request_%'
                OR option_name LIKE '_transient_timeout_anime_sync_last_request_%'"
        );

        $this->logger->log( 'info', '每週清理完成' );
        update_option( 'anime_sync_last_weekly_cleanup', current_time( 'mysql' ) );
    }

    // =========================================================================
    // 任務三：季度自動匯入
    // =========================================================================

    /**
     * 觸發新一季動畫的批次匯入（手動或排程呼叫）。
     *
     * @param string $season  季節（WINTER/SPRING/SUMMER/FALL）
     * @param int    $year    年份
     * @return array          匯入結果摘要
     */
    public function run_season_auto_import( string $season = '', int $year = 0 ): array {
        Anime_Sync_Performance::set_time_limit( 600 );
        Anime_Sync_Performance::increase_memory_limit( '512M' );

        // 若未指定，自動判斷當前季度
        if ( empty( $season ) || $year === 0 ) {
            [ $season, $year ] = $this->get_current_season();
        }

        $this->logger->log( 'info', "季度匯入開始：{$year} {$season}" );

        // 透過 AniList GraphQL 取得該季度清單
        $media_list = $this->fetch_season_list( $season, $year );

        if ( empty( $media_list ) ) {
            $this->logger->log( 'warning', "季度匯入：{$year} {$season} 無資料" );
            return [ 'success' => false, 'message' => '無資料', 'imported' => 0 ];
        }

        $imported = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ( $media_list as $media ) {
            $anilist_id = (int) ( $media['id'] ?? 0 );
            if ( ! $anilist_id ) {
                continue;
            }

            // ✅ Rate Limiter
            ( new Anime_Sync_Rate_Limiter() )->wait_if_needed( 'anilist' );

            $result = $this->import_manager->import_single( $anilist_id, null, 'anilist' );

            if ( ! empty( $result['success'] ) ) {
                $imported++;
            } elseif ( isset( $result['skipped'] ) && $result['skipped'] ) {
                $skipped++;
            } else {
                $failed++;
            }
        }

        $summary = [
            'success'  => true,
            'season'   => $season,
            'year'     => $year,
            'total'    => count( $media_list ),
            'imported' => $imported,
            'skipped'  => $skipped,
            'failed'   => $failed,
        ];

        $this->logger->log( 'info', '季度匯入完成', $summary );
        return $summary;
    }

    // =========================================================================
    // 任務四：Bangumi ID 地圖更新
    // =========================================================================

    /**
     * 觸發 Bangumi ID 地圖的重新下載與快取。
     */
    public function run_update_map(): void {
        $this->logger->log( 'info', 'Bangumi ID 地圖更新開始' );

        $mapper = new Anime_Sync_ID_Mapper();
        $result = $mapper->download_and_cache_map();

        if ( $result ) {
            $this->logger->log( 'info', 'Bangumi ID 地圖更新成功，寫入 ' . $result . ' bytes' );
        } else {
            $this->logger->log( 'error', 'Bangumi ID 地圖更新失敗' );
        }
    }

    // =========================================================================
    // 輔助方法
    // =========================================================================

    /**
     * 取得當前季節與年份。
     *
     * @return array [season_uppercase, year]
     */
    private function get_current_season(): array {
        $month = (int) gmdate( 'n' );
        $year  = (int) gmdate( 'Y' );

        $season = match ( true ) {
            $month >= 1  && $month <= 3  => 'WINTER',
            $month >= 4  && $month <= 6  => 'SPRING',
            $month >= 7  && $month <= 9  => 'SUMMER',
            $month >= 10 && $month <= 12 => 'FALL',
            default                      => 'WINTER',
        };

        return [ $season, $year ];
    }

    /**
     * 透過 AniList GraphQL 抓取季度媒體清單。
     *
     * @param string $season  WINTER/SPRING/SUMMER/FALL
     * @param int    $year    年份
     * @return array          AniList Media 物件陣列（含 id, title）
     */
    private function fetch_season_list( string $season, int $year ): array {
        $query = <<<'GQL'
        query ($season: MediaSeason, $year: Int, $page: Int) {
            Page(page: $page, perPage: 50) {
                pageInfo { hasNextPage }
                media(
                    season: $season
                    seasonYear: $year
                    type: ANIME
                    format_in: [TV, TV_SHORT, ONA, OVA, MOVIE, SPECIAL]
                    sort: [POPULARITY_DESC]
                ) {
                    id
                    title { romaji native }
                    format
                    episodes
                    status
                }
            }
        }
        GQL;

        $all_media = [];
        $page      = 1;

        do {
            // ✅ Rate Limiter
            ( new Anime_Sync_Rate_Limiter() )->wait_if_needed( 'anilist' );

            $response = wp_remote_post( 'https://graphql.anilist.co', [
                'body'    => wp_json_encode( [
                    'query'     => $query,
                    'variables' => [
                        'season' => $season,
                        'year'   => $year,
                        'page'   => $page,
                    ],
                ] ),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'timeout' => 20,
            ] );

            if ( is_wp_error( $response ) ) {
                break;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $page_data   = $body['data']['Page'] ?? [];
            $media_items = $page_data['media'] ?? [];
            $has_next    = $page_data['pageInfo']['hasNextPage'] ?? false;

            $all_media = array_merge( $all_media, $media_items );
            $page++;

        } while ( $has_next && $page <= 10 ); // 最多抓 10 頁 = 500 筆

        return $all_media;
    }

    // =========================================================================
    // 工具方法：取得排程狀態（供 settings.php 顯示）
    // =========================================================================

    /**
     * 取得所有排程的下次執行時間。
     *
     * @return array  [ hook_name => next_run_datetime|null ]
     */
    public static function get_schedule_status(): array {
        $hooks = [
            self::HOOK_DAILY_SCORE_UPDATE => '每日評分更新',
            self::HOOK_WEEKLY_CLEANUP      => '每週清理',
            self::HOOK_UPDATE_MAP          => 'Bangumi 地圖更新',
            self::HOOK_SEASON_IMPORT       => '季度自動匯入',
        ];

        $status = [];
        foreach ( $hooks as $hook => $label ) {
            $timestamp        = wp_next_scheduled( $hook );
            $status[ $label ] = $timestamp
                ? wp_date( 'Y-m-d H:i:s', $timestamp )
                : null;
        }
        return $status;
    }
}

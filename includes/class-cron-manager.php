<?php
/**
 * Cron Manager — 排程同步管理
 *
 * 修正紀錄：
 * - 新增 transient lock，防止 season import / daily update 重複執行
 * - run_daily_score_update() 改為分頁查詢，不再 posts_per_page => -1
 * - Rate Limiter 改為單例，不在 loop 內重複 new
 * - run_season_auto_import() 支援 wp_schedule_single_event 傳參
 * - fetch_season_list() 加入 429 / WP_Error 重試機制
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
    const HOOK_DAILY_SCORE_UPDATE = 'anime_sync_daily_score_update';
    const HOOK_WEEKLY_CLEANUP     = 'anime_sync_weekly_cleanup';
    const HOOK_SEASON_IMPORT      = 'anime_sync_season_auto_import';
    const HOOK_UPDATE_MAP         = 'anime_sync_update_anime_map';

    // Lock TTL（秒）：超過此時間視為鎖已過期（防止崩潰後死鎖）
    const LOCK_TTL_DAILY  = 1800;   // 30 分鐘
    const LOCK_TTL_SEASON = 3600;   // 60 分鐘

    private Anime_Sync_Import_Manager $import_manager;
    private Anime_Sync_Error_Logger   $logger;
    private Anime_Sync_Rate_Limiter   $rate_limiter; // ✅ 單例，不在 loop 內重複 new

    public function __construct( Anime_Sync_Import_Manager $import_manager ) {
        $this->import_manager = $import_manager;
        $this->logger         = new Anime_Sync_Error_Logger();
        $this->rate_limiter   = new Anime_Sync_Rate_Limiter(); // ✅ 建構時建立一次

        add_filter( 'cron_schedules', [ $this, 'add_custom_schedules' ] );

        add_action( self::HOOK_DAILY_SCORE_UPDATE, [ $this, 'run_daily_score_update' ] );
        add_action( self::HOOK_WEEKLY_CLEANUP,      [ $this, 'run_weekly_cleanup' ] );
        add_action( self::HOOK_UPDATE_MAP,          [ $this, 'run_update_map' ] );

        // ✅ 季度匯入支援帶參數觸發（wp_schedule_single_event）
        add_action( self::HOOK_SEASON_IMPORT, [ $this, 'run_season_auto_import' ], 10, 2 );
    }

    // =========================================================================
    // 排程間隔定義
    // =========================================================================

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

    public static function activate(): void {
        if ( ! wp_next_scheduled( self::HOOK_DAILY_SCORE_UPDATE ) ) {
            $daily_hour = (int) get_option( 'anime_sync_daily_hour', 3 );
            $today_utc  = strtotime( gmdate( "Y-m-d {$daily_hour}:00:00" ) );
            $start_time = $today_utc < time() ? $today_utc + DAY_IN_SECONDS : $today_utc;
            wp_schedule_event( $start_time, 'daily', self::HOOK_DAILY_SCORE_UPDATE );
        }

        if ( ! wp_next_scheduled( self::HOOK_WEEKLY_CLEANUP ) ) {
            wp_schedule_event( strtotime( 'next sunday 04:00:00' ), 'anime_sync_weekly', self::HOOK_WEEKLY_CLEANUP );
        }

        if ( ! wp_next_scheduled( self::HOOK_UPDATE_MAP ) ) {
            wp_schedule_event( strtotime( 'next monday 02:00:00' ), 'anime_sync_weekly', self::HOOK_UPDATE_MAP );
        }
    }

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

    public function run_daily_score_update(): void {

        // ✅ Lock：防止重複執行
        if ( get_transient( 'anime_sync_lock_daily' ) ) {
            $this->logger->log( 'warning', '每日評分更新：已有另一個程序在執行，本次跳過' );
            return;
        }
        set_transient( 'anime_sync_lock_daily', 1, self::LOCK_TTL_DAILY );

        try {
            $this->_run_daily_score_update_inner();
        } finally {
            // ✅ 無論成功或例外，都釋放鎖
            delete_transient( 'anime_sync_lock_daily' );
        }
    }

    private function _run_daily_score_update_inner(): void {
        Anime_Sync_Performance::set_time_limit( 300 );
        Anime_Sync_Performance::increase_memory_limit( '256M' );

        $this->logger->log( 'info', '每日評分更新開始' );

        $batch_size  = (int) get_option( 'anime_sync_batch_size', 15 );
        $paged       = 1;
        $updated     = 0;
        $failed      = 0;
        $cutoff_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );

        // ✅ 分頁查詢，不再 posts_per_page => -1，避免一次載入大量 ID 爆記憶體
        do {
            $query = new WP_Query( [
                'post_type'      => 'anime',
                'post_status'    => 'publish',
                'posts_per_page' => 200,         // 每頁 200 筆
                'paged'          => $paged,
                'fields'         => 'ids',
                'no_found_rows'  => false,        // 需要 max_num_pages
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
                            'value'   => $cutoff_date,
                            'compare' => '>=',
                            'type'    => 'DATE',
                        ],
                    ],
                ],
            ] );

            if ( empty( $query->posts ) ) {
                break;
            }

            Anime_Sync_Performance::batch_process(
                $query->posts,
                function( int $post_id ) use ( &$updated, &$failed ): void {
                    $anilist_id = (int) get_post_meta( $post_id, 'anime_anilist_id', true );
                    if ( ! $anilist_id ) return;

                    $this->rate_limiter->wait_if_needed( 'anilist' ); // ✅ 使用單例

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

            $max_pages = (int) $query->max_num_pages;
            $paged++;

        } while ( $paged <= $max_pages );

        $this->logger->log( 'info', sprintf(
            '每日評分更新完成：成功 %d / 失敗 %d',
            $updated,
            $failed
        ) );

        update_option( 'anime_sync_last_daily_run', current_time( 'mysql' ) );
    }

    // =========================================================================
    // 任務二：每週清理
    // =========================================================================

    public function run_weekly_cleanup(): void {
        $this->logger->log( 'info', '每週清理開始' );

        $retention_days = (int) get_option( 'anime_sync_log_retention_days', 30 );
        $deleted_logs   = $this->logger->delete_old_logs( $retention_days );
        $this->logger->log( 'info', "已清除 {$deleted_logs} 筆舊日誌" );

        Anime_Sync_Performance::clear_all_caches();

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_anime_sync_last_request_%'
                OR option_name LIKE '_transient_timeout_anime_sync_last_request_%'"
        );

        // ✅ 順便清理殘留 lock（理論上不應存在，但崩潰後可能留下）
        delete_transient( 'anime_sync_lock_daily' );
        delete_transient( 'anime_sync_lock_season' );

        $this->logger->log( 'info', '每週清理完成' );
        update_option( 'anime_sync_last_weekly_cleanup', current_time( 'mysql' ) );
    }

    // =========================================================================
    // 任務三：季度自動匯入
    // =========================================================================

    /**
     * @param string $season  WINTER/SPRING/SUMMER/FALL（空字串 = 自動判斷）
     * @param int    $year    年份（0 = 自動判斷）
     *
     * 排程觸發範例（排定下季）：
     *   wp_schedule_single_event( $timestamp, Anime_Sync_Cron_Manager::HOOK_SEASON_IMPORT, [ 'SUMMER', 2026 ] );
     */
    public function run_season_auto_import( string $season = '', int $year = 0 ): array {

        // ✅ Lock：防止重複執行
        if ( get_transient( 'anime_sync_lock_season' ) ) {
            $this->logger->log( 'warning', '季度匯入：已有另一個程序在執行，本次跳過' );
            return [ 'success' => false, 'message' => '已鎖定，跳過', 'imported' => 0 ];
        }
        set_transient( 'anime_sync_lock_season', 1, self::LOCK_TTL_SEASON );

        try {
            return $this->_run_season_import_inner( $season, $year );
        } finally {
            delete_transient( 'anime_sync_lock_season' );
        }
    }

    private function _run_season_import_inner( string $season, int $year ): array {
        Anime_Sync_Performance::set_time_limit( 600 );
        Anime_Sync_Performance::increase_memory_limit( '512M' );

        if ( empty( $season ) || $year === 0 ) {
            [ $season, $year ] = $this->get_current_season();
        }

        $this->logger->log( 'info', "季度匯入開始：{$year} {$season}" );

        $media_list = $this->fetch_season_list( $season, $year );

        if ( empty( $media_list ) ) {
            $this->logger->log( 'warning', "季度匯入：{$year} {$season} 無資料" );
            return [ 'success' => false, 'message' => '無資料', 'imported' => 0 ];
        }

        $imported = 0;
        $skipped  = 0;
        $failed   = 0;

        // ✅ 使用 batch_process 分批，每批結束清記憶體
        Anime_Sync_Performance::batch_process(
            $media_list,
            function( array $media ) use ( &$imported, &$skipped, &$failed ): void {
                $anilist_id = (int) ( $media['id'] ?? 0 );
                if ( ! $anilist_id ) return;

                $this->rate_limiter->wait_if_needed( 'anilist' ); // ✅ 使用單例

                $result = $this->import_manager->import_single( $anilist_id, null, 'anilist' );

                if ( ! empty( $result['success'] ) ) {
                    $imported++;
                } elseif ( ! empty( $result['skipped'] ) ) {
                    $skipped++;
                } else {
                    $failed++;
                    $this->logger->log( 'warning', '季度匯入單筆失敗', [
                        'anilist_id' => $anilist_id,
                        'error'      => $result['message'] ?? '未知錯誤',
                    ] );
                }
            },
            15 // batch_size：每15部清一次記憶體
        );

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
     * ✅ 新增 429 / WP_Error 重試（最多 3 次，指數退避）
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
            $this->rate_limiter->wait_if_needed( 'anilist' );

            $body = $this->anilist_request( $query, [
                'season' => $season,
                'year'   => $year,
                'page'   => $page,
            ] );

            if ( $body === null ) {
                // 重試三次都失敗，停止翻頁
                $this->logger->log( 'error', "fetch_season_list 第 {$page} 頁請求失敗，停止" );
                break;
            }

            $page_data   = $body['data']['Page'] ?? [];
            $media_items = $page_data['media'] ?? [];
            $has_next    = $page_data['pageInfo']['hasNextPage'] ?? false;

            $all_media = array_merge( $all_media, $media_items );
            $page++;

        } while ( $has_next && $page <= 10 );

        return $all_media;
    }

    /**
     * 帶重試的 AniList POST 請求。
     * ✅ 最多重試 3 次，指數退避（2s → 4s → 8s）
     *
     * @return array|null  解碼後的 JSON body，失敗返回 null
     */
    private function anilist_request( string $gql, array $variables, int $max_retries = 3 ): ?array {
        $attempt = 0;

        while ( $attempt < $max_retries ) {
            $response = wp_remote_post( 'https://graphql.anilist.co', [
                'body'    => wp_json_encode( [
                    'query'     => $gql,
                    'variables' => $variables,
                ] ),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'timeout' => 20,
            ] );

            if ( is_wp_error( $response ) ) {
                $attempt++;
                $this->logger->log( 'warning', 'AniList 請求 WP_Error，第 ' . $attempt . ' 次重試', [
                    'error' => $response->get_error_message(),
                ] );
                sleep( 2 ** $attempt ); // 2s, 4s, 8s
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            // 429 Rate Limit
            if ( $code === 429 ) {
                $this->rate_limiter->handle_rate_limit_error( $response, 'anilist' );
                $attempt++;
                continue;
            }

            // 其他非 200
            if ( $code !== 200 ) {
                $attempt++;
                $this->logger->log( 'warning', "AniList 回應 HTTP {$code}，第 {$attempt} 次重試" );
                sleep( 2 ** $attempt );
                continue;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! is_array( $body ) ) {
                $attempt++;
                sleep( 2 ** $attempt );
                continue;
            }

            return $body;
        }

        return null;
    }

    // =========================================================================
    // 工具方法：取得排程狀態
    // =========================================================================

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

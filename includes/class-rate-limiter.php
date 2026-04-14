<?php
/**
 * 檔案名稱: includes/class-rate-limiter.php
 *
 * ACE – 修正 PHP 8.1 Deprecated：
 *       wait_if_needed() 的 usleep() 參數強制轉型為 (int)
 *       microtime 存入 transient 時先 (int) round() 避免浮點數累積
 */
class Anime_Sync_Rate_Limiter {

    private array $limits = [
        'anilist'     => 2000,  // 2 秒（30 req/min 保守值）
        'jikan'       => 1200,  // 1.2 秒（50 req/min）
        'bangumi'     => 1000,  // 1 秒
        'animethemes' => 700,   // 700 毫秒（90 req/min）
    ];

    /**
     * 等待直到可以發送請求
     */
    public function wait_if_needed( string $api_name ): void {
        $api_name = strtolower( $api_name );

        if ( ! isset( $this->limits[ $api_name ] ) ) {
            return;
        }

        $interval  = $this->limits[ $api_name ];                 // int，單位 ms
        $cache_key = 'anime_sync_last_request_' . $api_name;
        $last_time = get_transient( $cache_key );                // int ms

        if ( $last_time !== false ) {
            // 修正：elapsed 與 wait 統一用 int（ms）
            $now     = (int) round( microtime( true ) * 1000 );
            $elapsed = $now - (int) $last_time;

            if ( $elapsed < $interval ) {
                $wait_ms = $interval - $elapsed;                 // int ms
                usleep( $wait_ms * 1000 );                       // 轉為微秒，int
            }
        }

        // 修正：存入 int，避免浮點數在取出時造成精度問題
        set_transient( $cache_key, (int) round( microtime( true ) * 1000 ), 60 );
    }

    /**
     * 處理 429 錯誤
     */
    public function handle_rate_limit_error( $response, string $api_name ): void {
        $retry_after = 60;

        // 相容傳入 WP_HTTP_Response 或 headers array
        if ( is_array( $response ) && isset( $response['headers'] ) ) {
            $headers = $response['headers'];
        } elseif ( is_array( $response ) && isset( $response['Retry-After'] ) ) {
            $headers = $response;
        } else {
            $headers = wp_remote_retrieve_headers( $response );
        }

        if ( ! empty( $headers['retry-after'] ) ) {
            $retry_after = (int) $headers['retry-after'];
        } elseif ( ! empty( $headers['Retry-After'] ) ) {
            $retry_after = (int) $headers['Retry-After'];
        } elseif ( ! empty( $headers['x-ratelimit-reset'] ) ) {
            $retry_after = max( 1, (int) $headers['x-ratelimit-reset'] - time() );
        }

        if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
            Anime_Sync_Error_Logger::log( 'warning', sprintf(
                '%s API 速率限制，等待 %d 秒',
                ucfirst( $api_name ),
                $retry_after
            ) );
        }

        sleep( $retry_after );
    }

    /**
     * 檢查剩餘配額
     */
    public function check_remaining( $response, string $api_name ): void {
        // 相容傳入 headers array 或 WP_HTTP_Response
        if ( is_array( $response ) && isset( $response['headers'] ) ) {
            $headers = $response['headers'];
        } else {
            $headers = wp_remote_retrieve_headers( $response );
        }

        $remaining_raw = $headers['x-ratelimit-remaining'] ?? $headers['X-RateLimit-Remaining'] ?? null;
        if ( $remaining_raw === null ) return;

        $remaining = (int) $remaining_raw;
        $limit_raw = $headers['x-ratelimit-limit'] ?? $headers['X-RateLimit-Limit'] ?? 90;
        $limit     = max( 1, (int) $limit_raw );

        $percentage = ( $remaining / $limit ) * 100;

        if ( $percentage < 10 ) {
            if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
                Anime_Sync_Error_Logger::log( 'warning', sprintf(
                    '%s API 配額剩餘不足 10%% (%d/%d)',
                    ucfirst( $api_name ),
                    $remaining,
                    $limit
                ) );
            }
            sleep( 5 );
        }
    }
}

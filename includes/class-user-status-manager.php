<?php
/**
 * User Status Manager
 *
 * 處理使用者追蹤狀態（取代 user_meta 'anime_user_data' JSON 方案）
 *
 * @package Anime_Sync_Pro
 * @version 1.0.0
 *
 * REST API:
 *   GET    /wp-json/smileacg/v1/user-status/{anime_id}        取得單一動畫狀態
 *   POST   /wp-json/smileacg/v1/user-status/{anime_id}        更新狀態（status/progress/favorite/fullclear）
 *   DELETE /wp-json/smileacg/v1/user-status/{anime_id}        移除追蹤
 *   GET    /wp-json/smileacg/v1/user-status/list              取得當前使用者完整清單
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_User_Status_Manager {

    /* ── 狀態常數（DB 用 TINYINT，PHP 用名稱） ── */
    const STATUS_WANT      = 0;
    const STATUS_WATCHING  = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_DROPPED   = 3;

    private const STATUS_MAP = [
        'want'      => self::STATUS_WANT,
        'watching'  => self::STATUS_WATCHING,
        'completed' => self::STATUS_COMPLETED,
        'dropped'   => self::STATUS_DROPPED,
    ];

    private const STATUS_REVERSE = [
        self::STATUS_WANT      => 'want',
        self::STATUS_WATCHING  => 'watching',
        self::STATUS_COMPLETED => 'completed',
        self::STATUS_DROPPED   => 'dropped',
    ];

    /* ── Rate limit ── */
    private const RATE_LIMIT_MAX    = 30;               // 30 次/分鐘
    private const RATE_LIMIT_PERIOD = MINUTE_IN_SECONDS;

    /* ── 快取 ── */
    private const CACHE_GROUP   = 'anime_user_status';
    private const CACHE_TTL_ONE = 60;                   // 單筆狀態 1 分鐘
    private const CACHE_TTL_LST = 5 * MINUTE_IN_SECONDS;// 清單 5 分鐘

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /* ═══════════════════════════════════════════
     *  REST 路由
     * ═══════════════════════════════════════════ */
    public function register_routes(): void {
        $ns = 'smileacg/v1';

        // /user-status/list — 取得當前使用者完整清單
        register_rest_route( $ns, '/user-status/list', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'api_get_my_list' ],
            'permission_callback' => [ $this, 'require_login' ],
        ] );

        // /user-status/{anime_id} — GET / POST / DELETE
        register_rest_route( $ns, '/user-status/(?P<anime_id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'api_get_one' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'api_update' ],
                'permission_callback' => [ $this, 'require_login' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'api_delete' ],
                'permission_callback' => [ $this, 'require_login' ],
            ],
        ] );
    }

    public function require_login() {
        return is_user_logged_in()
            ? true
            : new WP_Error( 'rest_forbidden', '請先登入', [ 'status' => 401 ] );
    }

    /* ═══════════════════════════════════════════
     *  API: GET /user-status/{anime_id}
     * ═══════════════════════════════════════════ */
    public function api_get_one( WP_REST_Request $req ) {
        $user_id  = get_current_user_id();
        $anime_id = (int) $req['anime_id'];

        if ( ! $user_id ) {
            return rest_ensure_response( [
                'logged_in' => false,
                'data'      => $this->empty_entry(),
            ] );
        }

        $entry = $this->get_entry( $user_id, $anime_id );

        return rest_ensure_response( [
            'logged_in' => true,
            'data'      => $entry,
        ] );
    }

    /* ═══════════════════════════════════════════
     *  API: GET /user-status/list
     *  回傳當前使用者所有追蹤
     * ═══════════════════════════════════════════ */
    public function api_get_my_list( WP_REST_Request $req ) {
        $user_id = get_current_user_id();
        $list    = $this->get_user_list( $user_id );

        return rest_ensure_response( [
            'success' => true,
            'count'   => count( $list ),
            'data'    => $list,
        ] );
    }

    /* ═══════════════════════════════════════════
     *  API: POST /user-status/{anime_id}
     *  body: { action: 'status'|'progress'|'favorite'|'fullclear'|'note', value: ... }
     * ═══════════════════════════════════════════ */
    public function api_update( WP_REST_Request $req ) {
        $user_id  = get_current_user_id();
        $anime_id = (int) $req['anime_id'];
        $action   = sanitize_key( $req->get_param( 'action' ) );
        $value    = $req->get_param( 'value' );

        // 驗證動畫
        if ( get_post_type( $anime_id ) !== 'anime' ) {
            return new WP_Error( 'invalid_anime', '動畫不存在', [ 'status' => 400 ] );
        }

        // Rate limit
        if ( ! $this->check_rate_limit( $user_id ) ) {
            return new WP_Error( 'rate_limited', '操作過於頻繁，請稍候 1 分鐘', [ 'status' => 429 ] );
        }

        $result = false;
        switch ( $action ) {
            case 'status':
                $result = $this->set_status( $user_id, $anime_id, (string) $value );
                break;
            case 'progress':
                $delta = (int) $value;
                $result = $this->adjust_progress( $user_id, $anime_id, $delta );
                break;
            case 'progress_set':
                $result = $this->set_progress( $user_id, $anime_id, (int) $value );
                break;
            case 'favorite':
                $result = $this->toggle_favorite( $user_id, $anime_id );
                break;
            case 'fullclear':
                $result = $this->toggle_fullclear( $user_id, $anime_id );
                break;
            case 'note':
                $result = $this->set_note( $user_id, $anime_id, (string) $value );
                break;
            case 'private':
                $result = $this->set_private( $user_id, $anime_id, (int) $value );
                break;
            default:
                return new WP_Error( 'invalid_action', '不支援的動作', [ 'status' => 400 ] );
        }

        if ( $result === false ) {
            if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
                Anime_Sync_Error_Logger::error( 'User status write failed', [
                    'user_id'  => $user_id,
                    'anime_id' => $anime_id,
                    'action'   => $action,
                    'db_error' => $GLOBALS['wpdb']->last_error,
                ] );
            }
            return new WP_Error( 'db_error', '儲存失敗', [ 'status' => 500 ] );
        }

        // 取最新資料回傳
        $entry = $this->get_entry( $user_id, $anime_id, false ); // 強制重讀

        return rest_ensure_response( [
            'success' => true,
            'data'    => $entry,
        ] );
    }

    /* ═══════════════════════════════════════════
     *  API: DELETE /user-status/{anime_id}
     * ═══════════════════════════════════════════ */
    public function api_delete( WP_REST_Request $req ) {
        $user_id  = get_current_user_id();
        $anime_id = (int) $req['anime_id'];

        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'anime_user_status',
            [ 'user_id' => $user_id, 'anime_id' => $anime_id ],
            [ '%d', '%d' ]
        );

        if ( $result === false ) {
            return new WP_Error( 'db_error', '刪除失敗', [ 'status' => 500 ] );
        }

        $this->flush_cache( $user_id, $anime_id );

        return rest_ensure_response( [
            'success' => true,
            'message' => '已移除',
        ] );
    }

    /* ═══════════════════════════════════════════
     *  CORE: 寫入
     * ═══════════════════════════════════════════ */

    /** 設定狀態（want/watching/completed/dropped） */
    private function set_status( int $user_id, int $anime_id, string $status ): bool {
        if ( ! isset( self::STATUS_MAP[ $status ] ) ) return false;

        $status_int = self::STATUS_MAP[ $status ];
        global $wpdb;
        $table = $wpdb->prefix . 'anime_user_status';

        // 用 ON DUPLICATE KEY UPDATE 一句搞定 insert/update
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
                (user_id, anime_id, status, started_at, completed_at)
             VALUES (%d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
                status       = VALUES(status),
                started_at   = COALESCE(started_at, VALUES(started_at)),
                completed_at = IF(VALUES(status) = %d, VALUES(completed_at), completed_at)",
            $user_id,
            $anime_id,
            $status_int,
            current_time( 'mysql' ),
            $status_int === self::STATUS_COMPLETED ? current_time( 'mysql' ) : null,
            self::STATUS_COMPLETED
        );

        $result = $wpdb->query( $sql );
        if ( $result === false ) return false;

        $this->flush_cache( $user_id, $anime_id );
        return true;
    }

    /** 進度增減（帶上限：max_episodes） */
    private function adjust_progress( int $user_id, int $anime_id, int $delta ): bool {
        $max = (int) get_post_meta( $anime_id, 'anime_episodes', true );
        if ( $max <= 0 ) $max = 9999; // 連載中無上限

        global $wpdb;
        $table = $wpdb->prefix . 'anime_user_status';

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (user_id, anime_id, progress, started_at)
             VALUES (%d, %d, GREATEST(0, %d), %s)
             ON DUPLICATE KEY UPDATE
                progress = GREATEST(0, LEAST(progress + %d, %d)),
                started_at = COALESCE(started_at, VALUES(started_at))",
            $user_id, $anime_id, max( 0, $delta ),
            current_time( 'mysql' ),
            $delta, $max
        );

        $result = $wpdb->query( $sql );
        if ( $result === false ) return false;

        $this->flush_cache( $user_id, $anime_id );
        return true;
    }

    /** 直接指定進度（管理用） */
    private function set_progress( int $user_id, int $anime_id, int $progress ): bool {
        $max = (int) get_post_meta( $anime_id, 'anime_episodes', true );
        if ( $max <= 0 ) $max = 9999;
        $progress = max( 0, min( $progress, $max ) );

        global $wpdb;
        $table = $wpdb->prefix . 'anime_user_status';

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (user_id, anime_id, progress)
             VALUES (%d, %d, %d)
             ON DUPLICATE KEY UPDATE progress = VALUES(progress)",
            $user_id, $anime_id, $progress
        );

        $result = $wpdb->query( $sql );
        if ( $result === false ) return false;

        $this->flush_cache( $user_id, $anime_id );
        return true;
    }

    /** Toggle 收藏 */
    private function toggle_favorite( int $user_id, int $anime_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'anime_user_status';

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (user_id, anime_id, favorited)
             VALUES (%d, %d, 1)
             ON DUPLICATE KEY UPDATE favorited = 1 - favorited",
            $user_id, $anime_id
        );

        $result = $wpdb->query( $sql );
        if ( $result === false ) return false;

        $this->flush_cache( $user_id, $anime_id );
        return true;
    }

    /** Toggle 全破 */
    private function toggle_fullclear( int $user_id, int $anime_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'anime_user_status';

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (user_id, anime_id, fullcleared)
             VALUES (%d, %d, 1)
             ON DUPLICATE KEY UPDATE fullcleared = 1 - fullcleared",
            $user_id, $anime_id
        );

        $result = $wpdb->query( $sql );
        if ( $result === false ) return false;

        $this->flush_cache( $user_id, $anime_id );
        return true;
    }

    /** 設定備註 */
    private function set_note( int $user_id, int $anime_id, string $note ): bool {
        $note = mb_substr( wp_strip_all_tags( $note ), 0, 500 );

        global $wpdb;
        $table = $wpdb->prefix . 'anime_user_status';

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (user_id, anime_id, note)
             VALUES (%d, %d, %s)
             ON DUPLICATE KEY UPDATE note = VALUES(note)",
            $user_id, $anime_id, $note
        );

        $result = $wpdb->query( $sql );
        if ( $result === false ) return false;

        $this->flush_cache( $user_id, $anime_id );
        return true;
    }

    /** 設定私密 */
    private function set_private( int $user_id, int $anime_id, int $is_private ): bool {
        $is_private = $is_private ? 1 : 0;

        global $wpdb;
        $table = $wpdb->prefix . 'anime_user_status';

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (user_id, anime_id, is_private)
             VALUES (%d, %d, %d)
             ON DUPLICATE KEY UPDATE is_private = VALUES(is_private)",
            $user_id, $anime_id, $is_private
        );

        $result = $wpdb->query( $sql );
        if ( $result === false ) return false;

        $this->flush_cache( $user_id, $anime_id );
        return true;
    }

    /* ═══════════════════════════════════════════
     *  CORE: 讀取
     * ═══════════════════════════════════════════ */

    /** 取單筆（帶快取） */
    public function get_entry( int $user_id, int $anime_id, bool $use_cache = true ): array {
        if ( ! $user_id || ! $anime_id ) {
            return $this->empty_entry();
        }

        $key = "us_{$user_id}_{$anime_id}";
        if ( $use_cache ) {
            $cached = wp_cache_get( $key, self::CACHE_GROUP );
            if ( false !== $cached ) return $cached;
        }

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT status, progress, favorited, fullcleared,
                    started_at, completed_at, note, is_private,
                    created_at, updated_at
             FROM {$wpdb->prefix}anime_user_status
             WHERE user_id = %d AND anime_id = %d",
            $user_id, $anime_id
        ), ARRAY_A );

        $entry = $row ? $this->normalize_row( $row ) : $this->empty_entry();
        wp_cache_set( $key, $entry, self::CACHE_GROUP, self::CACHE_TTL_ONE );

        return $entry;
    }

    /** 取使用者完整清單（帶快取） */
    public function get_user_list( int $user_id, bool $use_cache = true ): array {
        if ( ! $user_id ) return [];

        $key = "us_list_{$user_id}";
        if ( $use_cache ) {
            $cached = wp_cache_get( $key, self::CACHE_GROUP );
            if ( false !== $cached ) return $cached;
        }

        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT anime_id, status, progress, favorited, fullcleared,
                    started_at, completed_at, note, is_private,
                    created_at, updated_at
             FROM {$wpdb->prefix}anime_user_status
             WHERE user_id = %d
             ORDER BY updated_at DESC",
            $user_id
        ), ARRAY_A );

        $list = [];
        if ( $rows ) {
            foreach ( $rows as $r ) {
                $entry = $this->normalize_row( $r );
                $entry['anime_id'] = (int) $r['anime_id'];
                $list[] = $entry;
            }
        }

        wp_cache_set( $key, $list, self::CACHE_GROUP, self::CACHE_TTL_LST );
        return $list;
    }

    /* ═══════════════════════════════════════════
     *  CORE: 排行榜（讀彙總表）
     * ═══════════════════════════════════════════ */

    /**
     * 取排行榜
     * @param string $type favorited|watching|completed|want|total
     * @param int    $limit
     */
    public function get_ranking( string $type = 'favorited', int $limit = 20 ): array {
        $allow = [ 'favorited', 'watching', 'completed', 'want', 'dropped', 'total' ];
        if ( ! in_array( $type, $allow, true ) ) $type = 'favorited';

        $limit = max( 1, min( 100, $limit ) );

        $cache_key = "us_rank_{$type}_{$limit}";
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) return $cached;

        global $wpdb;
        $col = "{$type}_count";
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT anime_id, {$col} AS count
             FROM {$wpdb->prefix}anime_user_status_stats
             WHERE {$col} > 0
             ORDER BY {$col} DESC, anime_id ASC
             LIMIT %d",
            $limit
        ), ARRAY_A );

        $rows = $rows ?: [];
        wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, 10 * MINUTE_IN_SECONDS );

        return $rows;
    }

    /* ═══════════════════════════════════════════
     *  Helpers
     * ═══════════════════════════════════════════ */

    private function normalize_row( array $row ): array {
        $status_int = $row['status'];
        return [
            'status'       => ( $status_int !== null && isset( self::STATUS_REVERSE[ (int) $status_int ] ) )
                                ? self::STATUS_REVERSE[ (int) $status_int ] : null,
            'progress'     => (int) $row['progress'],
            'favorited'    => (bool) $row['favorited'],
            'fullcleared'  => (bool) $row['fullcleared'],
            'started_at'   => $row['started_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'note'         => $row['note'] ?? null,
            'is_private'   => (bool) ( $row['is_private'] ?? 0 ),
            'created_at'   => $row['created_at'] ?? null,
            'updated_at'   => $row['updated_at'] ?? null,
        ];
    }

    private function empty_entry(): array {
        return [
            'status'       => null,
            'progress'     => 0,
            'favorited'    => false,
            'fullcleared'  => false,
            'started_at'   => null,
            'completed_at' => null,
            'note'         => null,
            'is_private'   => false,
            'created_at'   => null,
            'updated_at'   => null,
        ];
    }

    private function check_rate_limit( int $user_id ): bool {
        $key   = "asp_us_rate_{$user_id}";
        $count = (int) get_transient( $key );
        if ( $count >= self::RATE_LIMIT_MAX ) return false;
        set_transient( $key, $count + 1, self::RATE_LIMIT_PERIOD );
        return true;
    }

    /** 失效快取（單筆 + 清單） */
    private function flush_cache( int $user_id, int $anime_id ): void {
        wp_cache_delete( "us_{$user_id}_{$anime_id}", self::CACHE_GROUP );
        wp_cache_delete( "us_list_{$user_id}",        self::CACHE_GROUP );
    }
}

<?php
/**
 * Rating Manager Class
 * 負責 WeixiaoACG+ 評分系統的核心邏輯、資料庫讀寫、REST API
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Rating_Manager {

    /**
     * 資料表名稱
     */
    private string $table;

    /**
     * 加權公式最低門檻票數（低於此數時分數向全站均值靠近）
     */
    private int $min_votes = 5;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'anime_ratings';
        $this->register_rest_routes();
    }

    // ================================================================
    // REST API 路由註冊
    // ================================================================

    private function register_rest_routes(): void {
        add_action( 'rest_api_init', function () {

            // GET  /wp-json/smileacg/v1/ratings/{anime_id}
            register_rest_route( 'smileacg/v1', '/ratings/(?P<anime_id>\d+)', [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'api_get_ratings' ],
                    'permission_callback' => '__return_true',
                    'args'                => [
                        'anime_id' => [
                            'required'          => true,
                            'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                        ],
                    ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'api_submit_rating' ],
                    'permission_callback' => [ $this, 'require_login' ],
                    'args'                => [
                        'anime_id' => [
                            'required'          => true,
                            'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                        ],
                        'score_story' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_score' ],
                            'sanitize_callback' => fn( $v ) => round( (float) $v, 1 ),
                        ],
                        'score_music' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_score' ],
                            'sanitize_callback' => fn( $v ) => round( (float) $v, 1 ),
                        ],
                        'score_animation' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_score' ],
                            'sanitize_callback' => fn( $v ) => round( (float) $v, 1 ),
                        ],
                        'score_voice' => [
                            'required'          => true,
                            'validate_callback' => [ $this, 'validate_score' ],
                            'sanitize_callback' => fn( $v ) => round( (float) $v, 1 ),
                        ],
                    ],
                ],
            ] );

            // GET  /wp-json/smileacg/v1/ranking/site
            register_rest_route( 'smileacg/v1', '/ranking/site', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'api_get_site_ranking' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'limit' => [
                        'default'           => 20,
                        'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v >= 1 && (int) $v <= 50,
                        'sanitize_callback' => fn( $v ) => (int) $v,
                    ],
                ],
            ] );

        } );
    }

    // ================================================================
    // Permission Callbacks
    // ================================================================

    public function require_login( WP_REST_Request $request ): bool|WP_Error {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                '請先登入才能評分',
                [ 'status' => 401 ]
            );
        }
        return true;
    }

    // ================================================================
    // 分數驗證
    // ================================================================

    public function validate_score( $value ): bool {
        $v = (float) $value;
        return $v >= 1.0 && $v <= 10.0;
    }

    // ================================================================
    // API：取得評分統計 + 目前使用者的評分
    // ================================================================

    public function api_get_ratings( WP_REST_Request $request ): WP_REST_Response {
        $anime_id = (int) $request->get_param( 'anime_id' );
        $stats    = $this->get_stats( $anime_id );
        $my_score = null;

        if ( is_user_logged_in() ) {
            $my_score = $this->get_user_rating( $anime_id, get_current_user_id() );
        }

        return rest_ensure_response( [
            'stats'    => $stats,
            'my_score' => $my_score,
        ] );
    }

    // ================================================================
    // API：提交 / 修改評分
    // ================================================================

    public function api_submit_rating( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $anime_id       = (int) $request->get_param( 'anime_id' );
        $user_id        = get_current_user_id();
        $score_story    = (float) $request->get_param( 'score_story' );
        $score_music    = (float) $request->get_param( 'score_music' );
        $score_anim     = (float) $request->get_param( 'score_animation' );
        $score_voice    = (float) $request->get_param( 'score_voice' );

        // 驗證 anime post 是否存在
        if ( get_post_type( $anime_id ) !== 'anime' ) {
            return new WP_Error( 'invalid_anime', '找不到此動畫', [ 'status' => 404 ] );
        }

        // 計算各分類平均作為總分
        $score_overall = round( ( $score_story + $score_music + $score_anim + $score_voice ) / 4, 2 );

        // 計算使用者權重（老用戶 ×1.5，新用戶 ×0.5）
        $weight = $this->get_user_weight( $user_id );

        global $wpdb;
        $now      = current_time( 'mysql' );
        $existing = $this->get_user_rating( $anime_id, $user_id );

        if ( $existing ) {
            // 修改既有評分
            $wpdb->update(
                $this->table,
                [
                    'score_story'     => $score_story,
                    'score_music'     => $score_music,
                    'score_animation' => $score_anim,
                    'score_voice'     => $score_voice,
                    'score_overall'   => $score_overall,
                    'weight'          => $weight,
                    'updated_at'      => $now,
                ],
                [ 'anime_id' => $anime_id, 'user_id' => $user_id ],
                [ '%f', '%f', '%f', '%f', '%f', '%f', '%s' ],
                [ '%d', '%d' ]
            );
        } else {
            // 新增評分
            $wpdb->insert(
                $this->table,
                [
                    'anime_id'        => $anime_id,
                    'user_id'         => $user_id,
                    'score_story'     => $score_story,
                    'score_music'     => $score_music,
                    'score_animation' => $score_anim,
                    'score_voice'     => $score_voice,
                    'score_overall'   => $score_overall,
                    'weight'          => $weight,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ],
                [ '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s' ]
            );
        }

        // 重新計算並更新 post meta（供排行榜使用）
        $this->update_post_meta_scores( $anime_id );

        return rest_ensure_response( [
            'success'  => true,
            'message'  => $existing ? '評分已更新' : '評分成功',
            'stats'    => $this->get_stats( $anime_id ),
            'my_score' => $this->get_user_rating( $anime_id, $user_id ),
        ] );
    }

    // ================================================================
    // API：站內排行榜 Top N
    // ================================================================

    public function api_get_site_ranking( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $limit = (int) $request->get_param( 'limit' );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                anime_id,
                COUNT(*) AS vote_count,
                AVG(score_overall) AS avg_overall,
                AVG(score_story) AS avg_story,
                AVG(score_music) AS avg_music,
                AVG(score_animation) AS avg_animation,
                AVG(score_voice) AS avg_voice
             FROM {$this->table}
             GROUP BY anime_id
             HAVING vote_count >= 1
             ORDER BY avg_overall DESC
             LIMIT %d",
            $limit
        ) );

        $global_avg = $this->get_global_average();
        $result     = [];

        foreach ( $rows as $row ) {
            $weighted = $this->calc_weighted_score(
                (float) $row->avg_overall,
                (int)   $row->vote_count,
                $global_avg
            );
            $post = get_post( (int) $row->anime_id );
            if ( ! $post || $post->post_status !== 'publish' ) continue;

            $result[] = [
                'anime_id'      => (int) $row->anime_id,
                'title'         => get_post_meta( $post->ID, 'anime_title_chinese', true ) ?: $post->post_title,
                'cover'         => get_post_meta( $post->ID, 'anime_cover_image', true ),
                'url'           => get_permalink( $post->ID ),
                'vote_count'    => (int) $row->vote_count,
                'score'         => round( $weighted, 2 ),
                'avg_story'     => round( (float) $row->avg_story, 2 ),
                'avg_music'     => round( (float) $row->avg_music, 2 ),
                'avg_animation' => round( (float) $row->avg_animation, 2 ),
                'avg_voice'     => round( (float) $row->avg_voice, 2 ),
            ];
        }

        return rest_ensure_response( $result );
    }

    // ================================================================
    // 核心：取得單部動畫的統計資料
    // ================================================================

    public function get_stats( int $anime_id ): array {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*)          AS vote_count,
                AVG(score_overall)   AS avg_overall,
                AVG(score_story)     AS avg_story,
                AVG(score_music)     AS avg_music,
                AVG(score_animation) AS avg_animation,
                AVG(score_voice)     AS avg_voice
             FROM {$this->table}
             WHERE anime_id = %d",
            $anime_id
        ) );

        if ( ! $row || (int) $row->vote_count === 0 ) {
            return [
                'vote_count'    => 0,
                'score'         => null,
                'avg_story'     => null,
                'avg_music'     => null,
                'avg_animation' => null,
                'avg_voice'     => null,
                'distribution'  => array_fill( 1, 10, 0 ),
            ];
        }

        $global_avg = $this->get_global_average();
        $weighted   = $this->calc_weighted_score(
            (float) $row->avg_overall,
            (int)   $row->vote_count,
            $global_avg
        );

        // 分布圖：整數區間 1~10
        $dist_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT FLOOR(score_overall) AS bucket, COUNT(*) AS cnt
             FROM {$this->table}
             WHERE anime_id = %d
             GROUP BY bucket
             ORDER BY bucket ASC",
            $anime_id
        ) );

        $distribution = array_fill( 1, 10, 0 );
        foreach ( $dist_rows as $d ) {
            $b = (int) $d->bucket;
            if ( $b >= 1 && $b <= 10 ) {
                $distribution[ $b ] = (int) $d->cnt;
            }
        }

        return [
            'vote_count'    => (int) $row->vote_count,
            'score'         => round( $weighted, 2 ),
            'avg_story'     => round( (float) $row->avg_story, 2 ),
            'avg_music'     => round( (float) $row->avg_music, 2 ),
            'avg_animation' => round( (float) $row->avg_animation, 2 ),
            'avg_voice'     => round( (float) $row->avg_voice, 2 ),
            'distribution'  => $distribution,
        ];
    }

    // ================================================================
    // 取得特定使用者對某部動畫的評分
    // ================================================================

    public function get_user_rating( int $anime_id, int $user_id ): ?array {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT score_story, score_music, score_animation, score_voice, score_overall, updated_at
             FROM {$this->table}
             WHERE anime_id = %d AND user_id = %d
             LIMIT 1",
            $anime_id,
            $user_id
        ) );

        if ( ! $row ) return null;

        return [
            'score_story'     => (float) $row->score_story,
            'score_music'     => (float) $row->score_music,
            'score_animation' => (float) $row->score_animation,
            'score_voice'     => (float) $row->score_voice,
            'score_overall'   => (float) $row->score_overall,
            'updated_at'      => $row->updated_at,
        ];
    }

    // ================================================================
    // 加權評分公式（類 MAL Bayesian）
    // ================================================================

    private function calc_weighted_score( float $avg, int $votes, float $global_avg ): float {
        $m = $this->min_votes;
        return ( $votes / ( $votes + $m ) ) * $avg
             + ( $m    / ( $votes + $m ) ) * $global_avg;
    }

    // ================================================================
    // 全站所有評分的平均值（用於加權公式 C 值）
    // ================================================================

    private function get_global_average(): float {
        global $wpdb;
        $avg = (float) $wpdb->get_var(
            "SELECT AVG(score_overall) FROM {$this->table}"
        );
        return $avg > 0 ? $avg : 7.0; // 預設基準 7.0
    }

    // ================================================================
    // 使用者權重計算
    // 帳號 >= 30天 且 評分 >= 10 部 → ×1.5
    // 其餘新用戶 → ×0.5
    // ================================================================

    private function get_user_weight( int $user_id ): float {
        global $wpdb;

        $user       = get_userdata( $user_id );
        $reg_date   = strtotime( $user->user_registered );
        $days_old   = ( time() - $reg_date ) / DAY_IN_SECONDS;

        $rated_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT anime_id) FROM {$this->table} WHERE user_id = %d",
            $user_id
        ) );

        if ( $days_old >= 30 && $rated_count >= 10 ) {
            return 1.5;
        }
        if ( $days_old < 7 ) {
            return 0.5;
        }
        return 1.0;
    }

    // ================================================================
    // 更新 post meta，供排行榜頁面直接讀取
    // ================================================================

    private function update_post_meta_scores( int $anime_id ): void {
        $stats = $this->get_stats( $anime_id );
        update_post_meta( $anime_id, 'anime_score_site',       $stats['score'] ?? '' );
        update_post_meta( $anime_id, 'anime_score_site_count', $stats['vote_count'] ?? 0 );
    }
}

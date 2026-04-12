<?php
/**
 * Security Handler
 *
 * @package Anime_Sync_Pro
 */

// ✅ Bug H 修正：補上 ABSPATH 安全檢查
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Security {

    public static function verify_ajax_nonce( $action = 'anime_sync_ajax' ) {
        if ( ! check_ajax_referer( $action, 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => '安全驗證失敗' ], 403 );
            return false;
        }
        return true;
    }

    public static function check_capability( $capability = 'manage_options' ) {
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( [ 'message' => '權限不足' ], 403 );
            return false;
        }
        return true;
    }

    // ✅ Bug G 修正：AniList ID 上限從 999999 → 9999999
    public static function sanitize_anilist_id( $id ) {
        $id = absint( $id );
        if ( $id < 1 || $id > 9999999 ) return false;
        return $id;
    }

    public static function sanitize_anilist_ids( $ids ) {
        if ( is_string( $ids ) ) $ids = explode( ',', $ids );
        if ( ! is_array( $ids ) ) return [];
        $sanitized = [];
        foreach ( $ids as $id ) {
            $clean = self::sanitize_anilist_id( $id );
            if ( $clean !== false ) $sanitized[] = $clean;
        }
        return array_unique( $sanitized );
    }

    public static function sanitize_season( $season ) {
        $season = strtoupper( trim( $season ) );
        return in_array( $season, [ 'WINTER', 'SPRING', 'SUMMER', 'FALL' ], true ) ? $season : false;
    }

    public static function sanitize_year( $year ) {
        $year         = absint( $year );
        $current_year = (int) date( 'Y' );
        if ( $year < ( $current_year - 30 ) || $year > ( $current_year + 5 ) ) return false;
        return $year;
    }

    public static function escape_output( $text, $context = 'html' ) {
        switch ( $context ) {
            case 'attr':     return esc_attr( $text );
            case 'url':      return esc_url( $text );
            case 'js':       return esc_js( $text );
            case 'textarea': return esc_textarea( $text );
            default:         return wp_kses_post( $text );
        }
    }

    public static function validate_json( $json ) {
        if ( empty( $json ) ) return false;
        $data = json_decode( $json, true );
        return json_last_error() === JSON_ERROR_NONE ? $data : false;
    }

    public static function rate_limit_check( $action, $limit = 10, $period = 60 ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return false;
        $key      = 'anime_sync_rate_limit_' . $action . '_' . $user_id;
        $requests = get_transient( $key );
        if ( $requests === false ) { set_transient( $key, 1, $period ); return true; }
        if ( $requests >= $limit ) return false;
        set_transient( $key, $requests + 1, $period );
        return true;
    }
}

<?php
/**
 * Security Handler
 * 
 * @package Anime_Sync_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class Anime_Sync_Security {
    
    /**
     * Verify AJAX nonce
     * 
     * @param string $action Action name
     * @return bool Valid
     */
    public static function verify_ajax_nonce($action = 'anime_sync_ajax') {
        if (!check_ajax_referer($action, 'nonce', false)) {
            wp_send_json_error(array(
                'message' => '安全驗證失敗'
            ), 403);
            return false;
        }
        return true;
    }
    
    /**
     * Check user capability
     * 
     * @param string $capability Required capability
     * @return bool Has capability
     */
    public static function check_capability($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => '權限不足'
            ), 403);
            return false;
        }
        return true;
    }
    
    /**
     * Sanitize AniList ID
     * 
     * @param mixed $id Input ID
     * @return int|false Sanitized ID or false
     */
    public static function sanitize_anilist_id($id) {
        $id = absint($id);
        
        // AniList ID 範圍驗證 (1 到 999999)
        if ($id < 1 || $id > 999999) {
            return false;
        }
        
        return $id;
    }
    
    /**
     * Sanitize AniList ID array
     * 
     * @param array|string $ids IDs (array or comma-separated)
     * @return array Sanitized IDs
     */
    public static function sanitize_anilist_ids($ids) {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        
        if (!is_array($ids)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($ids as $id) {
            $clean_id = self::sanitize_anilist_id($id);
            if ($clean_id !== false) {
                $sanitized[] = $clean_id;
            }
        }
        
        return array_unique($sanitized);
    }
    
    /**
     * Sanitize season
     * 
     * @param string $season Season name
     * @return string|false Sanitized season or false
     */
    public static function sanitize_season($season) {
        $season = strtoupper(trim($season));
        
        $valid_seasons = array('WINTER', 'SPRING', 'SUMMER', 'FALL');
        
        if (!in_array($season, $valid_seasons)) {
            return false;
        }
        
        return $season;
    }
    
    /**
     * Sanitize year
     * 
     * @param mixed $year Year
     * @return int|false Sanitized year or false
     */
    public static function sanitize_year($year) {
        $year = absint($year);
        
        $current_year = (int) date('Y');
        $min_year = $current_year - 30;
        $max_year = $current_year + 5;
        
        if ($year < $min_year || $year > $max_year) {
            return false;
        }
        
        return $year;
    }
    
    /**
     * Escape output for display
     * 
     * @param string $text Text to escape
     * @param string $context Context (html/attr/url)
     * @return string Escaped text
     */
    public static function escape_output($text, $context = 'html') {
        switch ($context) {
            case 'attr':
                return esc_attr($text);
            case 'url':
                return esc_url($text);
            case 'js':
                return esc_js($text);
            case 'textarea':
                return esc_textarea($text);
            case 'html':
            default:
                return wp_kses_post($text);
        }
    }
    
    /**
     * Validate JSON data
     * 
     * @param string $json JSON string
     * @return array|false Decoded data or false
     */
    public static function validate_json($json) {
        if (empty($json)) {
            return false;
        }
        
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Rate limit check (simple implementation)
     * 
     * @param string $action Action identifier
     * @param int $limit Max requests
     * @param int $period Time period in seconds
     * @return bool Allowed
     */
    public static function rate_limit_check($action, $limit = 10, $period = 60) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $transient_key = 'anime_sync_rate_limit_' . $action . '_' . $user_id;
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            set_transient($transient_key, 1, $period);
            return true;
        }
        
        if ($requests >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $requests + 1, $period);
        return true;
    }
}

<?php
/**
 * Plugin Name: Anime Sync Pro
 * Description: 從 AniList、Bangumi 自動同步動畫資料。
 * Version:     1.0.5
 * Author:      SmaACG
 * Requires PHP: 8.0
 * Text Domain: anime-sync-pro
 *
 * Bug fixes / features in this version:
 *   ACD – 新增 anime_series_tax taxonomy（系列分類）
 *         系列分析（get_series_tree、analyze_series、assign_series_taxonomy）
 *         季度批次匯入分頁修正
 *         Tab 4 系列分析互動介面
 *         Tab 5 人氣排行互動介面
 *         前端節流（每 10 部暫停 10 秒）
 *         import_single() 新增第三參數 $source
 *   ACF – fetch_animethemes() 加入 videos.audio，audio_url 存入 themes
 *         enrich_anime_data() Staff/Cast 改為 Bangumi 直接取代
 *         get_full_anime_data() Staff/Cast 改為 Bangumi 優先取代
 *         重新同步 Bangumi 按鈕改為原生 Meta Box
 *         新增 wp_ajax_anime_resync_bangumi
 *         USER_AGENT 統一常數
 *         新增台灣串流平台個別 URL 欄位
 *         新增 anime_faq_json 手動 FAQ 欄位
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================
// 1. 常數定義
// ============================================================
define( 'ANIME_SYNC_PRO_VERSION',  '1.0.5' );
define( 'ANIME_SYNC_PRO_DIR',      plugin_dir_path( __FILE__ ) );
define( 'ANIME_SYNC_PRO_URL',      plugin_dir_url( __FILE__ ) );
define( 'ANIME_SYNC_PRO_BASENAME', plugin_basename( __FILE__ ) );

// ============================================================
// 2. Autoloader
// ============================================================
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'Anime_Sync_' ) !== 0 ) {
        return;
    }
    $file_name = 'class-' . strtolower(
        str_replace( [ 'Anime_Sync_', '_' ], [ '', '-' ], $class )
    ) . '.php';
    $sources = [
        ANIME_SYNC_PRO_DIR . 'includes/',
        ANIME_SYNC_PRO_DIR . 'admin/',
        ANIME_SYNC_PRO_DIR . 'public/',
    ];
    foreach ( $sources as $source ) {
        $file = $source . $file_name;
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
} );

// ============================================================
// 3. 註冊 Post Type 與 Taxonomy
// ============================================================
add_action( 'init', function () {

    // ----------------------------------------------------------
    // Post Type: anime
    // ----------------------------------------------------------
    register_post_type( 'anime', [
        'labels' => [
            'name'          => '動畫',
            'singular_name' => '動畫',
            'add_new'       => '新增動畫',
            'add_new_item'  => '新增動畫',
            'edit_item'     => '編輯動畫',
            'view_item'     => '檢視動畫',
            'search_items'  => '搜尋動畫',
            'not_found'     => '找不到動畫',
            'all_items'     => '所有動畫',
            'menu_name'     => '動畫',
        ],
        'public'             => true,
        'has_archive'        => 'anime',
        'show_in_rest'       => true,
        'show_in_nav_menus'  => true,
        'show_ui'            => true,
        'menu_icon'          => 'dashicons-format-video',
        'menu_position'      => 5,
        'supports'           => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'comments' ],
        'taxonomies'         => [ 'post_tag' ],
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'rewrite'            => [ 'slug' => 'anime', 'with_front' => false ],
    ] );

    // ----------------------------------------------------------
    // Taxonomy: genre
    // ----------------------------------------------------------
    register_taxonomy( 'genre', [ 'anime', 'manga', 'novel' ], [
        'labels' => [
            'name'          => '類型',
            'singular_name' => '類型',
            'search_items'  => '搜尋類型',
            'all_items'     => '所有類型',
            'edit_item'     => '編輯類型',
            'add_new_item'  => '新增類型',
        ],
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_in_nav_menus' => true,
        'show_admin_column' => true,
        'rewrite'           => [ 'slug' => 'genre', 'with_front' => false ],
    ] );

    // ----------------------------------------------------------
    // Taxonomy: anime_season_tax
    // ----------------------------------------------------------
    register_taxonomy( 'anime_season_tax', [ 'anime' ], [
        'labels' => [
            'name'          => '播

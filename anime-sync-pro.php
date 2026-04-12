<?php
/**
 * Plugin Name:       Anime Sync Pro
 * Description:       從 AniList、Bangumi 自動同步動畫資料。
 * Version:           1.0.1
 * Author:            Your Name
 * Requires PHP:      8.0
 * Text Domain:       anime-sync-pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// 1. 常數定義
// ============================================================
define( 'ANIME_SYNC_PRO_VERSION',  '1.0.1' );
define( 'ANIME_SYNC_PRO_DIR',      plugin_dir_path( __FILE__ ) );
define( 'ANIME_SYNC_PRO_URL',      plugin_dir_url( __FILE__ ) );
define( 'ANIME_SYNC_PRO_BASENAME', plugin_basename( __FILE__ ) );

// ============================================================
// 2. Autoloader
// ============================================================
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'Anime_Sync_' ) !== 0 ) return;
    $file_name = 'class-' . strtolower( str_replace( [ 'Anime_Sync_', '_' ], [ '', '-' ], $class ) ) . '.php';
    $sources   = [
        ANIME_SYNC_PRO_DIR . 'includes/',
        ANIME_SYNC_PRO_DIR . 'admin/',
        ANIME_SYNC_PRO_DIR . 'public/',
    ];
    foreach ( $sources as $source ) {
        $file = $source . $file_name;
        if ( file_exists( $file ) ) { require_once $file; return; }
    }
} );

// ============================================================
// 3. 註冊 Post Type 與 Taxonomy
// ============================================================
add_action( 'init', function() {

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
        'public'            => true,
        'has_archive'       => 'anime',
        'show_in_rest'      => true,
        'menu_icon'         => 'dashicons-format-video',
        'menu_position'     => 5,
        'supports'          => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'comments' ],
        'capability_type'   => 'post',
        'map_meta_cap'      => true,
        'rewrite'           => [ 'slug' => 'anime', 'with_front' => false ],
    ] );

    // genre | URL: /genre/action/
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
        'show_admin_column' => true,
        'rewrite'           => [ 'slug' => 'genre', 'with_front' => false ],
    ] );

    // anime_season_tax | URL: /season/2025-spring/
    register_taxonomy( 'anime_season_tax', [ 'anime' ], [
        'labels' => [
            'name'          => '播出季度',
            'singular_name' => '季度',
            'search_items'  => '搜尋季度',
            'all_items'     => '所有季度',
            'edit_item'     => '編輯季度',
            'add_new_item'  => '新增季度',
        ],
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => [ 'slug' => 'season', 'with_front' => false ],
    ] );

    // anime_format_tax | URL: /format/tv/
    register_taxonomy( 'anime_format_tax', [ 'anime' ], [
        'labels' => [
            'name'          => '動畫格式',
            'singular_name' => '格式',
            'search_items'  => '搜尋格式',
            'all_items'     => '所有格式',
            'edit_item'     => '編輯格式',
            'add_new_item'  => '新增格式',
        ],
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => [ 'slug' => 'format', 'with_front' => false ],
    ] );

}, 10 );

// ============================================================
// 4. 啟動 Hook（Bug I 修正：補上 Installer 執行）
// ============================================================
register_activation_hook( __FILE__, function() {
    // ✅ Bug I 修正：實例化 Installer，建立資料表、預設選項、上傳目錄
    if ( class_exists( 'Anime_Sync_Installer' ) ) {
        ( new Anime_Sync_Installer() )->activate();
    } else {
        // Autoloader 尚未掛載時的 fallback
        $installer_file = plugin_dir_path( __FILE__ ) . 'includes/class-installer.php';
        if ( file_exists( $installer_file ) ) {
            require_once $installer_file;
            ( new Anime_Sync_Installer() )->activate();
        }
    }
    flush_rewrite_rules();
} );

// ============================================================
// 5. 載入外掛核心
// ============================================================
add_action( 'plugins_loaded', function() {

    // 需要 ACF 才能運作
    if ( ! class_exists( 'ACF' ) ) return;

    // ACF 欄位定義
    if ( class_exists( 'Anime_Sync_ACF_Fields' ) ) {
        new Anime_Sync_ACF_Fields();
    }

    // 前台載入 Frontend（模板覆蓋、REST API、Shortcode）
    // ✅ Bug F 修正：Frontend 不限制 is_admin，前台也需要載入
    if ( class_exists( 'Anime_Sync_Frontend' ) ) {
        new Anime_Sync_Frontend();
    }

    // 後台 / Cron 才載入完整匯入功能
    if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
        $id_mapper      = new Anime_Sync_ID_Mapper();
        $converter      = new Anime_Sync_CN_Converter();
        $api_handler    = new Anime_Sync_API_Handler( $id_mapper, $converter );
        $import_manager = new Anime_Sync_Import_Manager( $api_handler );

        if ( class_exists( 'Anime_Sync_Admin' ) ) {
            new Anime_Sync_Admin( $import_manager );
        }
    }

} );

// ============================================================
// 6. Rewrite Rules 刷新
// ============================================================
add_action( 'init', function() {
    if ( get_option( 'anime_sync_flush_rewrite' ) ) {
        flush_rewrite_rules();
        delete_option( 'anime_sync_flush_rewrite' );
    }
}, 99 );

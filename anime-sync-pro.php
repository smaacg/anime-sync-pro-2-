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

// 1. 常數定義
define( 'ANIME_SYNC_PRO_VERSION', '1.0.1' );
define( 'ANIME_SYNC_PRO_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ANIME_SYNC_PRO_URL',     plugin_dir_url( __FILE__ ) );
define( 'ANIME_SYNC_PRO_BASENAME', plugin_basename( __FILE__ ) );

// 2. Autoloader
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'Anime_Sync_' ) !== 0 ) return;
    $file_name = 'class-' . strtolower( str_replace( [ 'Anime_Sync_', '_' ], [ '', '-' ], $class ) ) . '.php';
    $sources = [ ANIME_SYNC_PRO_DIR . 'includes/', ANIME_SYNC_PRO_DIR . 'admin/', ANIME_SYNC_PRO_DIR . 'public/' ];
    foreach ( $sources as $source ) {
        $file = $source . $file_name;
        if ( file_exists( $file ) ) { require_once $file; return; }
    }
} );

// 3. 註冊 CPT (解決 20 字元限制)
add_action( 'init', function() {
    register_post_type( 'anime', [
        'labels' => [ 'name' => '動畫', 'singular_name' => '動畫', 'add_new' => '新增動畫', 'edit_item' => '編輯動畫' ],
        'public' => true,
        'has_archive' => 'anime',
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-format-video',
        'supports' => [ 'title', 'editor', 'thumbnail', 'custom-fields', 'comments' ],
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'rewrite' => [ 'slug' => 'anime', 'with_front' => false ],
    ] );

    register_taxonomy( 'anime_genre', 'anime', [
        'label' => '類型',
        'hierarchical' => true,
        'show_in_rest' => true,
    ] );
}, 10 );

// 4. 啟動與載入
register_activation_hook( __FILE__, function() { flush_rewrite_rules(); } );

add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'ACF' ) ) return;
    if ( class_exists( 'Anime_Sync_ACF_Fields' ) ) new Anime_Sync_ACF_Fields();

    if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
        $id_mapper      = new Anime_Sync_ID_Mapper();
        $converter      = new Anime_Sync_CN_Converter();
        $api_handler    = new Anime_Sync_API_Handler( $id_mapper, $converter );
        $import_manager = new Anime_Sync_Import_Manager( $api_handler );
        if ( class_exists( 'Anime_Sync_Admin' ) ) new Anime_Sync_Admin( $import_manager );
    }
} );

add_action( 'init', function() {
    if ( get_option( 'anime_sync_flush_rewrite' ) ) {
        flush_rewrite_rules();
        delete_option( 'anime_sync_flush_rewrite' );
    }
}, 99 );

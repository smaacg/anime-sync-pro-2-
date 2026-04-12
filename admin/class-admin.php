<?php
/**
 * 檔案名稱: admin/class-admin.php
 * 修正版：解決常數未定義與路徑包含問題
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Admin {
    private $import_manager;

    public function __construct( $import_manager ) {
        $this->import_manager = $import_manager;
        
        // 註冊選單與資產
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // AJAX 處理
        add_action( 'wp_ajax_anime_sync_import_single', [ $this, 'handle_ajax_import_single' ] );
        add_action( 'wp_ajax_anime_clear_old_logs', [ $this, 'handle_clear_logs' ] );
    }

    /**
     * 註冊後台選單
     */
    public function register_admin_menu(): void {
        $cap = 'manage_options'; 
        
        // 主選單
        add_menu_page( '動畫同步 Pro', '動畫同步', $cap, 'anime-sync-pro', [ $this, 'render_dashboard' ], 'dashicons-video-alt', 30 );
        
        // 子選單
        add_submenu_page( 'anime-sync-pro', '儀表板', '儀表板', $cap, 'anime-sync-pro', [ $this, 'render_dashboard' ] );
        add_submenu_page( 'anime-sync-pro', '匯入工具', '匯入工具', $cap, 'anime-sync-import', [ $this, 'render_import_tool' ] );
        add_submenu_page( 'anime-sync-pro', '審核佇列', '審核佇列', $cap, 'anime-sync-queue', [ $this, 'render_review_queue' ] );
        add_submenu_page( 'anime-sync-pro', '查看動畫', '查看動畫', $cap, 'anime-sync-published', [ $this, 'render_published_page' ] );
        add_submenu_page( 'anime-sync-pro', '錯誤日誌', '錯誤日誌', $cap, 'anime-sync-logs', [ $this, 'render_logs_page' ] );
        add_submenu_page( 'anime-sync-pro', '插件設定', '插件設定', $cap, 'anime-sync-settings', [ $this, 'render_settings' ] );
    }

    /**
     * 通用的頁面渲染輔助函式
     */
    private function safe_include_page( $file_name ) {
        // 確保 ANIME_SYNC_PRO_DIR 已定義，否則嘗試抓取當前插件路徑
        $base_dir = defined( 'ANIME_SYNC_PRO_DIR' ) ? ANIME_SYNC_PRO_DIR : plugin_dir_path( dirname( __FILE__ ) );
        $file_path = $base_dir . 'admin/pages/' . $file_name;

        if ( file_exists( $file_path ) ) {
            include $file_path;
        } else {
            echo '<div class="wrap"><div class="notice notice-error"><p>錯誤：找不到頁面檔案 <code>' . esc_html( $file_name ) . '</code></p></div></div>';
        }
    }

    // 渲染各個頁面
    public function render_dashboard() { $this->safe_include_page( 'dashboard.php' ); }
    public function render_import_tool() { $this->safe_include_page( 'import-tool.php' ); }
    public function render_review_queue() { $this->safe_include_page( 'review-queue.php' ); }
    public function render_published_page() { $this->safe_include_page( 'published-list.php' ); }
    public function render_logs_page() { $this->safe_include_page( 'logs.php' ); }
    public function render_settings() { $this->safe_include_page( 'settings.php' ); }

    /**
     * AJAX 處理：匯入單個動畫
     */
    public function handle_ajax_import_single() {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );

        $anilist_id = isset( $_POST['anilist_id'] ) ? intval( $_POST['anilist_id'] ) : 0;
        if ( ! $anilist_id ) wp_send_json_error( '無效的 ID' );

        $result = $this->import_manager->import_single( $anilist_id );
        wp_send_json_success( $result );
    }

    /**
     * AJAX 處理：清除舊日誌
     */
    public function handle_clear_logs() {
        // 修正 nonce 名稱以符合 enqueue 時的定義
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );

        // 確保 Logger 類別存在
        if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
            $logger = new Anime_Sync_Error_Logger();
            $count = $logger->clear_old_logs( 30 ); 
            wp_send_json_success( [ 'count' => $count ] );
        } else {
            wp_send_json_error( '找不到 Logger 類別' );
        }
    }

    /**
     * 載入後台靜態資源 (CSS/JS)
     */
    public function enqueue_admin_assets( $hook ) {
        // 只在插件相關頁面載入資源
        if ( strpos( $hook, 'anime-sync' ) === false ) return;
        
        // 容錯處理：確保常數有定義，避免 Fatal Error
        $plugin_url = defined( 'ANIME_SYNC_PRO_URL' ) ? ANIME_SYNC_PRO_URL : plugin_dir_url( dirname( __FILE__ ) );
        $version    = defined( 'ANIME_SYNC_PRO_VERSION' ) ? ANIME_SYNC_PRO_VERSION : '1.0.0';

        // 載入 CSS
        wp_enqueue_style( 'anime-sync-admin', $plugin_url . 'admin/assets/css/admin.css', [], $version );
        
        // 載入 JS
        wp_enqueue_script( 'anime-sync-admin', $plugin_url . 'admin/assets/js/admin.js', [ 'jquery' ], $version, true );
        
        // 傳遞參數給 JS
        wp_localize_script( 'anime-sync-admin', 'animeSyncAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'anime_sync_admin_nonce' ),
        ] );
    }
}

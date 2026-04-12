<?php
/**
 * 檔案名稱: admin/class-admin.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Admin {

    private $import_manager;

    public function __construct( $import_manager ) {
        $this->import_manager = $import_manager;

        add_action( 'admin_menu',            [ $this, 'register_admin_menu'     ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets'    ] );

        // ── AJAX handlers ──────────────────────────────────
        add_action( 'wp_ajax_anime_sync_import_single', [ $this, 'handle_ajax_import_single' ] );
        add_action( 'wp_ajax_anime_sync_query_season',  [ $this, 'handle_ajax_query_season'  ] ); // ✅
        add_action( 'wp_ajax_anime_sync_update_map',    [ $this, 'handle_ajax_update_map'    ] ); // ✅
        add_action( 'wp_ajax_anime_sync_clear_cache',   [ $this, 'handle_ajax_clear_cache'   ] ); // ✅
        add_action( 'wp_ajax_anime_sync_clear_logs',    [ $this, 'handle_ajax_clear_logs'    ] ); // ✅
        add_action( 'wp_ajax_anime_clear_old_logs',     [ $this, 'handle_ajax_clear_logs'    ] ); // 舊名相容
    }

    // ── 選單 ───────────────────────────────────────────────
    public function register_admin_menu(): void {
        $cap = 'manage_options';
        add_menu_page( '動畫同步 Pro', '動畫同步', $cap, 'anime-sync-pro', [ $this, 'render_dashboard' ], 'dashicons-video-alt', 30 );
        add_submenu_page( 'anime-sync-pro', '儀表板',   '儀表板',   $cap, 'anime-sync-pro',       [ $this, 'render_dashboard'      ] );
        add_submenu_page( 'anime-sync-pro', '匯入工具', '匯入工具', $cap, 'anime-sync-import',    [ $this, 'render_import_tool'    ] );
        add_submenu_page( 'anime-sync-pro', '審核佇列', '審核佇列', $cap, 'anime-sync-queue',     [ $this, 'render_review_queue'   ] );
        add_submenu_page( 'anime-sync-pro', '查看動畫', '查看動畫', $cap, 'anime-sync-published', [ $this, 'render_published_page' ] );
        add_submenu_page( 'anime-sync-pro', '錯誤日誌', '錯誤日誌', $cap, 'anime-sync-logs',      [ $this, 'render_logs_page'      ] );
        add_submenu_page( 'anime-sync-pro', '插件設定', '插件設定', $cap, 'anime-sync-settings',  [ $this, 'render_settings'       ] );
    }

    // ── 頁面渲染 ───────────────────────────────────────────
    private function safe_include_page( string $file_name ): void {
        $base_dir  = defined( 'ANIME_SYNC_PRO_DIR' ) ? ANIME_SYNC_PRO_DIR : plugin_dir_path( dirname( __FILE__ ) );
        $file_path = $base_dir . 'admin/pages/' . $file_name;
        if ( file_exists( $file_path ) ) {
            include $file_path;
        } else {
            echo '<div class="wrap"><div class="notice notice-error"><p>找不到頁面：<code>' . esc_html( $file_name ) . '</code></p></div></div>';
        }
    }

    public function render_dashboard()      { $this->safe_include_page( 'dashboard.php'      ); }
    public function render_import_tool()    { $this->safe_include_page( 'import-tool.php'    ); }
    public function render_review_queue()   { $this->safe_include_page( 'review-queue.php'   ); }
    public function render_published_page() { $this->safe_include_page( 'published-list.php' ); }
    public function render_logs_page()      { $this->safe_include_page( 'logs.php'           ); }
    public function render_settings()       { $this->safe_include_page( 'settings.php'       ); }

    // ── AJAX：單筆匯入 ─────────────────────────────────────
    public function handle_ajax_import_single(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );

        $anilist_id = isset( $_POST['anilist_id'] ) ? intval( $_POST['anilist_id'] ) : 0;
        if ( ! $anilist_id ) wp_send_json_error( '無效的 ID' );

        $result = $this->import_manager->import_single( $anilist_id );
        wp_send_json_success( $result );
    }

    // ── AJAX：查詢季度清單 ─────────────────────────────────
    public function handle_ajax_query_season(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );

        $season  = strtoupper( sanitize_text_field( $_POST['season'] ?? '' ) );
        $year    = intval( $_POST['year'] ?? date('Y') );
        $allowed = [ 'WINTER', 'SPRING', 'SUMMER', 'FALL' ];

        if ( ! in_array( $season, $allowed, true ) || ! $year ) {
            wp_send_json_error( '請選擇有效的年份與季節' );
        }

        $query = '
        query($season:MediaSeason,$year:Int){
            Page(perPage:50){
                media(season:$season,seasonYear:$year,type:ANIME,sort:POPULARITY_DESC){
                    id idMal title{romaji} format episodes popularity status
                }
            }
        }';

        $res = wp_remote_post( 'https://graphql.anilist.co', [
            'body'    => json_encode( [ 'query' => $query, 'variables' => [ 'season' => $season, 'year' => $year ] ] ),
            'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'timeout' => 20,
        ]);

        if ( is_wp_error( $res ) ) wp_send_json_error( 'API 連線失敗：' . $res->get_error_message() );

        $code = wp_remote_retrieve_response_code( $res );
        if ( $code !== 200 ) wp_send_json_error( 'AniList 回傳 HTTP ' . $code );

        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! empty( $body['errors'] ) ) wp_send_json_error( 'AniList 錯誤：' . ( $body['errors'][0]['message'] ?? '未知' ) );

        $list = [];
        foreach ( $body['data']['Page']['media'] ?? [] as $m ) {
            $list[] = [
                'anilist_id'   => $m['id'],
                'mal_id'       => $m['idMal']           ?? null,
                'title_romaji' => $m['title']['romaji'] ?? '',
                'format'       => $m['format']          ?? '',
                'episodes'     => $m['episodes']        ?? null,
                'popularity'   => $m['popularity']      ?? 0,
                'status'       => $m['status']          ?? '',
            ];
        }

        wp_send_json_success( [ 'list' => $list, 'total' => count( $list ) ] );
    }

    // ── AJAX：更新 ID 對照表 ───────────────────────────────
    public function handle_ajax_update_map(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );

        if ( class_exists( 'Anime_Sync_ID_Mapper' ) ) {
            $mapper = new Anime_Sync_ID_Mapper();
            $result = $mapper->download_and_cache_map();
            if ( $result ) {
                wp_send_json_success( '對照表更新成功' );
            } else {
                wp_send_json_error( '下載失敗，請檢查網路連線' );
            }
        } else {
            wp_send_json_error( '找不到 ID Mapper 類別' );
        }
    }

    // ── AJAX：清除快取 ─────────────────────────────────────
    public function handle_ajax_clear_cache(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );

        // 清除所有 anime_sync 相關的 transient
        global $wpdb;
        $count = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_anime_sync_%'
             OR option_name LIKE '_transient_timeout_anime_sync_%'"
        );

        wp_send_json_success( '已清除 ' . (int) $count . ' 筆快取' );
    }

    // ── AJAX：清除日誌 ─────────────────────────────────────
    public function handle_ajax_clear_logs(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );

        if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
            $logger = new Anime_Sync_Error_Logger();
            $count  = $logger->delete_old_logs( 0 ); // 0 = 清除全部
            wp_send_json_success( [ 'count' => $count, 'message' => '已清除 ' . $count . ' 筆日誌' ] );
        } else {
            wp_send_json_error( '找不到 Logger 類別' );
        }
    }

    // ── 載入後台資源 ───────────────────────────────────────
    public function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, 'anime-sync' ) === false ) return;

        $plugin_url = defined( 'ANIME_SYNC_PRO_URL' )     ? ANIME_SYNC_PRO_URL     : plugin_dir_url( dirname( __FILE__ ) );
        $version    = defined( 'ANIME_SYNC_PRO_VERSION' ) ? ANIME_SYNC_PRO_VERSION : '1.0.0';

        wp_enqueue_style(  'anime-sync-admin', $plugin_url . 'admin/assets/css/admin.css', [],           $version );
        wp_enqueue_script( 'anime-sync-admin', $plugin_url . 'admin/assets/js/admin.js',  [ 'jquery' ], $version, true );

        wp_localize_script( 'anime-sync-admin', 'animeSyncAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'anime_sync_admin_nonce' ),
        ]);
    }
}

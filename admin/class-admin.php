<?php
/**
 * 檔案名稱: admin/class-admin.php
 *
 * ACB – handle_ajax_import_single() 加入 set_time_limit(120)
 *       新增 handle_ajax_enrich_single()：手動觸發補抓單部
 *       handle_ajax_save_bangumi_id() 寫入 _bangumi_id_manually_set
 *       handle_ajax_update_map() 加入 set_time_limit(180) + 回傳詳細統計
 *
 * ACD – handle_ajax_query_season() 修正：加入分頁邏輯，
 *       不再固定只抓 50 部，最多抓 10 頁 = 500 筆。
 *       新增三個 AJAX Handler：
 *         handle_ajax_analyze_series()     → Tab 4 系列分析
 *         handle_ajax_import_series()      → Tab 4 系列匯入（含歸類）
 *         handle_ajax_popularity_ranking() → Tab 5 人氣排行
 *
 * ACE – handle_ajax_analyze_series() 修正：
 *       1. is_wp_error() 防呆
 *       2. 判斷改為 empty($result['nodes'])（原 'tree' key 不存在）
 *       3. 回傳時將 nodes 對應到前端需要的 'tree' key
 *       4. 補算 total / imported 統計數字
 *
 * ACF – enqueue_admin_assets() 擴充載入條件，涵蓋 anime 文章編輯頁
 *       animeSyncAdmin 補入 resync i18n 鍵值
 *       建構子新增 wp_ajax_anime_resync_bangumi 掛載
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Admin {

    private $import_manager;

    public function __construct( $import_manager ) {
        $this->import_manager = $import_manager;

        add_action( 'admin_menu',            [ $this, 'register_admin_menu'  ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        add_action( 'wp_ajax_anime_sync_import_single',      [ $this, 'handle_ajax_import_single'      ] );
        add_action( 'wp_ajax_anime_sync_query_season',       [ $this, 'handle_ajax_query_season'       ] );
        add_action( 'wp_ajax_anime_sync_update_map',         [ $this, 'handle_ajax_update_map'         ] );
        add_action( 'wp_ajax_anime_sync_clear_cache',        [ $this, 'handle_ajax_clear_cache'        ] );
        add_action( 'wp_ajax_anime_sync_clear_logs',         [ $this, 'handle_ajax_clear_logs'         ] );
        add_action( 'wp_ajax_anime_clear_old_logs',          [ $this, 'handle_ajax_clear_logs'         ] );
        add_action( 'wp_ajax_anime_sync_bulk_action',        [ $this, 'handle_ajax_bulk_action'        ] );
        add_action( 'wp_ajax_anime_sync_save_bangumi_id',    [ $this, 'handle_ajax_save_bangumi_id'    ] );
        add_action( 'wp_ajax_anime_sync_enrich_single',      [ $this, 'handle_ajax_enrich_single'      ] );
        // ACD 新增
        add_action( 'wp_ajax_anime_sync_analyze_series',     [ $this, 'handle_ajax_analyze_series'     ] );
        add_action( 'wp_ajax_anime_sync_import_series',      [ $this, 'handle_ajax_import_series'      ] );
        add_action( 'wp_ajax_anime_sync_popularity_ranking', [ $this, 'handle_ajax_popularity_ranking' ] );
        // ACF 新增：Bangumi 重新同步
        add_action( 'wp_ajax_anime_resync_bangumi',          [ $this, 'handle_ajax_resync_bangumi'     ] );
    }

    // =========================================================================
    // Admin Menu
    // =========================================================================

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

    private function safe_include_page( string $file_name ): void {
        $base_dir  = defined( 'ANIME_SYNC_PRO_DIR' ) ? ANIME_SYNC_PRO_DIR : plugin_dir_path( dirname( __FILE__ ) );
        $file_path = $base_dir . 'admin/pages/' . $file_name;
        if ( file_exists( $file_path ) ) include $file_path;
        else echo '<div class="wrap"><div class="notice notice-error"><p>找不到頁面：<code>' . esc_html( $file_name ) . '</code></p></div></div>';
    }

    public function render_dashboard()      { $this->safe_include_page( 'dashboard.php'      ); }
    public function render_import_tool()    { $this->safe_include_page( 'import-tool.php'    ); }
    public function render_review_queue()   { $this->safe_include_page( 'review-queue.php'   ); }
    public function render_published_page() { $this->safe_include_page( 'published-list.php' ); }
    public function render_logs_page()      { $this->safe_include_page( 'logs.php'           ); }
    public function render_settings()       { $this->safe_include_page( 'settings.php'       ); }

    // =========================================================================
    // AJAX：單筆匯入
    // =========================================================================

    public function handle_ajax_import_single(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => '權限不足' ] );
        @set_time_limit( 120 );
        $anilist_id = isset( $_POST['anilist_id'] ) ? intval( $_POST['anilist_id'] ) : 0;
        if ( ! $anilist_id ) wp_send_json_error( [ 'message' => '無效的 ID' ] );
        $result = $this->import_manager->import_single( $anilist_id );
        wp_send_json_success( $result );
    }

    // =========================================================================
    // AJAX：手動補抓單部（ACB）
    // =========================================================================

    public function handle_ajax_enrich_single(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => '權限不足' ] );
        @set_time_limit( 120 );
        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) wp_send_json_error( [ 'message' => '無效的 post_id' ] );
        $result = $this->import_manager->enrich_single( $post_id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [ 'message' => '補抓完成', 'enriched' => array_keys( $result ) ] );
    }

    // =========================================================================
    // AJAX：查詢季度清單（ACD：分頁，最多 10 頁 = 500 筆）
    // =========================================================================

    public function handle_ajax_query_season(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );

        $season  = strtoupper( sanitize_text_field( $_POST['season'] ?? '' ) );
        $year    = intval( $_POST['year'] ?? date( 'Y' ) );
        $allowed = [ 'WINTER', 'SPRING', 'SUMMER', 'FALL' ];
        if ( ! in_array( $season, $allowed, true ) || ! $year ) {
            wp_send_json_error( '請選擇有效的年份與季節' );
        }

        $query = '
        query($season:MediaSeason,$year:Int,$page:Int){
            Page(page:$page,perPage:50){
                pageInfo { hasNextPage }
                media(season:$season,seasonYear:$year,type:ANIME,sort:POPULARITY_DESC){
                    id idMal title{romaji} format episodes popularity status
                }
            }
        }';

        $all_list = [];
        $page     = 1;

        do {
            $res = wp_remote_post( 'https://graphql.anilist.co', [
                'body'    => json_encode( [
                    'query'     => $query,
                    'variables' => [ 'season' => $season, 'year' => $year, 'page' => $page ],
                ] ),
                'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                'timeout' => 20,
            ] );

            if ( is_wp_error( $res ) ) break;

            $code = (int) wp_remote_retrieve_response_code( $res );

            if ( $code === 429 ) {
                sleep( 65 );
                $res = wp_remote_post( 'https://graphql.anilist.co', [
                    'body'    => json_encode( [
                        'query'     => $query,
                        'variables' => [ 'season' => $season, 'year' => $year, 'page' => $page ],
                    ] ),
                    'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                    'timeout' => 20,
                ] );
                if ( is_wp_error( $res ) ) break;
                $code = (int) wp_remote_retrieve_response_code( $res );
            }

            if ( $code !== 200 ) break;

            $body      = json_decode( wp_remote_retrieve_body( $res ), true );
            $page_data = $body['data']['Page'] ?? [];
            $media     = $page_data['media']   ?? [];
            $has_next  = (bool) ( $page_data['pageInfo']['hasNextPage'] ?? false );

            foreach ( $media as $m ) {
                $existing_query = new WP_Query( [
                    'post_type'      => 'anime',
                    'post_status'    => 'any',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                    'meta_query'     => [ [
                        'key'     => 'anime_anilist_id',
                        'value'   => (int) $m['id'],
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ] ],
                ] );
                $all_list[] = [
                    'anilist_id'   => (int) $m['id'],
                    'mal_id'       => $m['idMal']           ?? null,
                    'title_romaji' => $m['title']['romaji'] ?? '',
                    'format'       => $m['format']          ?? '',
                    'episodes'     => $m['episodes']        ?? null,
                    'popularity'   => (int) ( $m['popularity'] ?? 0 ),
                    'status'       => $m['status']          ?? '',
                    'imported'     => ! empty( $existing_query->posts ),
                ];
            }

            $page++;

            if ( $has_next && $page <= 10 ) {
                usleep( 500000 );
            }

        } while ( $has_next && $page <= 10 );

        if ( empty( $all_list ) ) {
            wp_send_json_error( '查無資料，請確認季度或年份是否正確' );
        }

        wp_send_json_success( [ 'list' => $all_list, 'total' => count( $all_list ) ] );
    }

    // =========================================================================
    // AJAX：批次操作
    // =========================================================================

    public function handle_ajax_bulk_action(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );
        $action   = sanitize_text_field( $_POST['bulk'] ?? '' );
        $post_ids = array_map( 'intval', (array) ( $_POST['post_ids'] ?? [] ) );
        if ( empty( $action ) || empty( $post_ids ) ) wp_send_json_error( '參數錯誤' );
        $allowed_actions = [ 'publish', 'draft', 'delete', 'refetch' ];
        if ( ! in_array( $action, $allowed_actions, true ) ) wp_send_json_error( '不允許的操作' );
        if ( $action === 'refetch' ) @set_time_limit( 120 * count( $post_ids ) );
        $count = 0;
        foreach ( $post_ids as $post_id ) {
            if ( ! $post_id || get_post_type( $post_id ) !== 'anime' ) continue;
            switch ( $action ) {
                case 'publish':
                    $r = wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
                    if ( $r && ! is_wp_error( $r ) ) $count++;
                    break;
                case 'draft':
                    $r = wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
                    if ( $r && ! is_wp_error( $r ) ) $count++;
                    break;
                case 'delete':
                    if ( wp_delete_post( $post_id, true ) ) $count++;
                    break;
                case 'refetch':
                    $anilist_id = (int) get_post_meta( $post_id, 'anime_anilist_id', true );
                    if ( $anilist_id ) {
                        $r = $this->import_manager->import_single( $anilist_id );
                        if ( ! empty( $r['success'] ) ) $count++;
                    }
                    break;
            }
        }
        wp_send_json_success( [ 'count' => $count, 'message' => "已完成 {$count} 筆操作" ] );
    }

    // =========================================================================
    // AJAX：儲存 Bangumi ID
    // =========================================================================

    public function handle_ajax_save_bangumi_id(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );
        $post_id    = intval( $_POST['post_id']    ?? 0 );
        $bangumi_id = intval( $_POST['bangumi_id'] ?? 0 );
        if ( ! $post_id || ! $bangumi_id ) wp_send_json_error( '參數錯誤' );
        if ( get_post_type( $post_id ) !== 'anime' ) wp_send_json_error( '文章類型錯誤' );
        update_post_meta( $post_id, 'anime_bangumi_id', $bangumi_id );
        update_post_meta( $post_id, 'bangumi_id',       $bangumi_id );
        delete_post_meta( $post_id, '_bangumi_id_pending' );
        update_post_meta( $post_id, '_bangumi_id_manually_set', 1 );
        wp_send_json_success( [ 'bangumi_id' => $bangumi_id ] );
    }

    // =========================================================================
    // AJAX：更新 ID 對照表
    // =========================================================================

    public function handle_ajax_update_map(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );
        @set_time_limit( 180 );
        if ( class_exists( 'Anime_Sync_ID_Mapper' ) ) {
            $mapper = new Anime_Sync_ID_Mapper();
            $result = $mapper->download_and_cache_map();
            if ( $result ) {
                $status = $mapper->get_map_status();
                wp_send_json_success( [
                    'message'          => '對照表更新成功',
                    'al_count'         => $status['al_count']         ?? 0,
                    'mal_count'        => $status['mal_count']        ?? 0,
                    'ext_mal_count'    => $status['ext_mal_count']    ?? 0,
                    'ext_anidb_count'  => $status['ext_anidb_count']  ?? 0,
                    'ext_last_updated' => $status['ext_last_updated'] ?? '',
                ] );
            } else {
                wp_send_json_error( '下載失敗，請檢查網路連線' );
            }
        } else {
            wp_send_json_error( '找不到 ID Mapper 類別' );
        }
    }

    // =========================================================================
    // AJAX：清除快取
    // =========================================================================

    public function handle_ajax_clear_cache(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );
        global $wpdb;
        $count = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_anime_sync_%'
             OR option_name LIKE '_transient_timeout_anime_sync_%'"
        );
        wp_send_json_success( '已清除 ' . (int) $count . ' 筆快取' );
    }

    // =========================================================================
    // AJAX：清除日誌
    // =========================================================================

    public function handle_ajax_clear_logs(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( '權限不足' );
        if ( class_exists( 'Anime_Sync_Error_Logger' ) ) {
            $count = ( new Anime_Sync_Error_Logger() )->delete_old_logs( 0 );
            wp_send_json_success( [ 'count' => $count, 'message' => '已清除 ' . $count . ' 筆日誌' ] );
        } else {
            wp_send_json_error( '找不到 Logger 類別' );
        }
    }

    // =========================================================================
    // AJAX：系列分析（ACD，Tab 4）
    // ACE 修正：
    //   1. is_wp_error() 防呆
    //   2. 判斷改用 empty($result['nodes'])
    //   3. 回傳時將 nodes 映射為前端 JS 需要的 'tree' key
    //   4. 補算 total / imported 統計
    // =========================================================================

    public function handle_ajax_analyze_series(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => '權限不足' ] );

        @set_time_limit( 180 );

        $anilist_id = intval( $_POST['anilist_id'] ?? 0 );
        if ( ! $anilist_id ) wp_send_json_error( [ 'message' => '無效的 AniList ID' ] );

        $result = $this->import_manager->analyze_series( $anilist_id );

        // 防呆：WP_Error
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // 防呆：nodes 為空
        if ( empty( $result['nodes'] ) ) {
            wp_send_json_error( [ 'message' => '找不到系列資料，請確認 ID 是否正確' ] );
        }

        // 補算 total / imported 統計（前端顯示用）
        $nodes    = $result['nodes'];
        $total    = count( $nodes );
        $imported = count( array_filter( $nodes, fn( $n ) => ! empty( $n['imported'] ) ) );

        wp_send_json_success( [
            'root_id'     => $result['root_id'],
            'series_name' => $result['series_name'],
            'tree'        => $nodes,    // 前端 JS 讀 res.data.tree
            'total'       => $total,
            'imported'    => $imported,
        ] );
    }

    // =========================================================================
    // AJAX：系列匯入（ACD，Tab 4）
    // =========================================================================

    public function handle_ajax_import_series(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => '權限不足' ] );

        @set_time_limit( 120 );

        $anilist_id  = intval( $_POST['anilist_id']  ?? 0 );
        $series_name = sanitize_text_field( $_POST['series_name'] ?? '' );
        $root_id     = intval( $_POST['root_id']     ?? 0 );

        if ( ! $anilist_id ) wp_send_json_error( [ 'message' => '無效的 AniList ID' ] );

        $result = $this->import_manager->import_single( $anilist_id );

        if ( ! empty( $result['success'] ) && ! empty( $result['post_id'] ) && $series_name !== '' ) {
            $this->import_manager->assign_series_taxonomy( (int) $result['post_id'], $series_name, $root_id );
            $result['series_assigned'] = true;
        }

        wp_send_json_success( $result );
    }

    // =========================================================================
    // AJAX：人氣排行（ACD，Tab 5）
    // =========================================================================

    public function handle_ajax_popularity_ranking(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => '權限不足' ] );

        $page = max( 1, intval( $_POST['page'] ?? 1 ) );

        if ( ! method_exists( $this->import_manager, 'get_popularity_ranking' ) ) {
            wp_send_json_error( [ 'message' => '功能不可用，請確認外掛版本是否為 1.0.5+' ] );
        }

        $result = $this->import_manager->get_popularity_ranking( $page );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    // =========================================================================
    // AJAX：重新同步 Bangumi（ACF 新增）
    // =========================================================================

    public function handle_ajax_resync_bangumi(): void {
        check_ajax_referer( 'anime_sync_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => '權限不足' ] );

        $post_id    = intval( $_POST['post_id']    ?? 0 );
        $bangumi_id = intval( $_POST['bangumi_id'] ?? 0 );

        if ( ! $post_id )    wp_send_json_error( [ 'message' => '無效的 post_id' ] );
        if ( ! $bangumi_id ) wp_send_json_error( [ 'message' => '請先填入 Bangumi ID 並儲存文章。' ] );
        if ( get_post_type( $post_id ) !== 'anime' ) wp_send_json_error( [ 'message' => '文章類型錯誤' ] );

        if ( ! class_exists( 'Anime_Sync_API_Handler' ) ) {
            wp_send_json_error( [ 'message' => '找不到 API Handler 類別' ] );
        }

        $api = new Anime_Sync_API_Handler();
        $result = $api->ajax_resync_bangumi( $post_id, $bangumi_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => '✅ 同步完成', 'updated' => $result ] );
    }

    // =========================================================================
    // 載入後台資源（ACF：擴充條件涵蓋 anime 文章編輯頁）
    // =========================================================================

    public function enqueue_admin_assets( string $hook ): void {
        $is_plugin_page = strpos( $hook, 'anime-sync' ) !== false;
        $is_anime_edit  = in_array( $hook, [ 'post.php', 'post-new.php' ], true )
                          && (
                              get_post_type() === 'anime'
                              || ( sanitize_key( $_GET['post_type'] ?? '' ) === 'anime' )
                          );

        if ( ! $is_plugin_page && ! $is_anime_edit ) return;

        $url     = defined( 'ANIME_SYNC_PRO_URL' )     ? ANIME_SYNC_PRO_URL     : plugin_dir_url( dirname( __FILE__ ) );
        $version = defined( 'ANIME_SYNC_PRO_VERSION' ) ? ANIME_SYNC_PRO_VERSION : '1.0.0';

        wp_enqueue_style(  'anime-sync-admin', $url . 'admin/assets/css/admin.css', [],           $version );
        wp_enqueue_script( 'anime-sync-admin', $url . 'admin/assets/js/admin.js',  [ 'jquery' ], $version, true );
        wp_localize_script( 'anime-sync-admin', 'animeSyncAdmin', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'anime_sync_admin_nonce' ),
            'syncing'       => __( '同步中，請稍候…',                  'anime-sync-pro' ),
            'sync_success'  => __( '✅ 同步完成，頁面即將重新整理…',   'anime-sync-pro' ),
            'error_no_id'   => __( '請先填入 Bangumi ID 並儲存文章。', 'anime-sync-pro' ),
            'network_error' => __( '網路錯誤，請重試。',               'anime-sync-pro' ),
        ] );
    }
}

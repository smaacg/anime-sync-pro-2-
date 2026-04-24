<?php
/**
 * 微笑動漫 Child Theme — functions.php
 *
 * @package SmileACG
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
   常數定義
   ============================================================ */
define( 'SMAACG_VERSION',    '1.0.0' );
define( 'SMAACG_THEME_URL',  get_stylesheet_directory_uri() );
define( 'SMAACG_PLUGIN_URL', plugins_url( 'smaacg-core' ) );
define( 'SMAACG_THEME_DIR',  get_stylesheet_directory() );

/* ============================================================
   主題支援
   ============================================================ */
add_action( 'after_setup_theme', 'smaacg_theme_setup' );
function smaacg_theme_setup() {
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'html5', [
        'search-form', 'comment-form', 'comment-list',
        'gallery', 'caption', 'style', 'script',
    ] );
    add_theme_support( 'custom-logo', [
        'height'      => 40,
        'width'       => 180,
        'flex-height' => true,
        'flex-width'  => true,
    ] );
    add_theme_support( 'menus' );

    add_image_size( 'smaacg-cover',  300, 420, true );
    add_image_size( 'smaacg-banner', 1280, 400, true );
    add_image_size( 'smaacg-thumb',  160, 224, true );
    add_image_size( 'anime-thumb',   300, 420, true );
    add_image_size( 'news-thumb',    800, 450, true );
    add_image_size( 'season-thumb',  180, 260, true );

    load_child_theme_textdomain( 'smaacg', SMAACG_THEME_DIR . '/languages' );
}

/* ============================================================
   選單註冊
   ============================================================ */
add_action( 'after_setup_theme', 'smaacg_register_menus' );
function smaacg_register_menus() {
    register_nav_menus( [
        'primary-menu'  => __( '主導覽選單',   'smaacg' ),
        'footer-menu'   => __( '頁腳選單',     'smaacg' ),
        'more-menu'     => __( '更多下拉選單', 'smaacg' ),
        'primary'       => __( '主選單',       'smaacg' ),
        'secondary'     => __( '次要選單',     'smaacg' ),
        'footer-col-1'  => __( '頁腳欄位 1',   'smaacg' ),
        'footer-col-2'  => __( '頁腳欄位 2',   'smaacg' ),
        'footer-col-3'  => __( '頁腳欄位 3',   'smaacg' ),
        'footer-col-4'  => __( '頁腳欄位 4',   'smaacg' ),
    ] );
}

/* ============================================================
   自訂 Nav Walker
   ============================================================ */
class SmileACG_Nav_Walker extends Walker_Nav_Menu {

    public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
        $item    = $data_object;
        $classes = empty( $item->classes ) ? [] : (array) $item->classes;

        $is_current = in_array( 'current-menu-item',    $classes, true )
                   || in_array( 'current-page-ancestor', $classes, true )
                   || in_array( 'current-menu-ancestor', $classes, true );

        $has_children = in_array( 'menu-item-has-children', $classes, true );
        $icon         = trim( $item->description ?? '' );

        if ( $depth === 0 ) {
            $output .= '<div class="nav-item' . ( $has_children ? ' has-dropdown' : '' ) . '">';
            $output .= '<a href="' . esc_url( $item->url ) . '"'
                     . ' class="nav-link' . ( $is_current ? ' active' : '' ) . '"'
                     . ( $item->target ? ' target="' . esc_attr( $item->target ) . '"' : '' ) . '>';
            if ( $icon ) $output .= '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i> ';
            $output .= esc_html( $item->title );
            if ( $has_children ) $output .= ' <i class="fa-solid fa-chevron-down nav-arrow" aria-hidden="true"></i>';
            $output .= '</a>';
        } else {
            $output .= '<a href="' . esc_url( $item->url ) . '"'
                     . ' class="nav-dropdown-item' . ( $is_current ? ' active' : '' ) . '"'
                     . ( $item->target ? ' target="' . esc_attr( $item->target ) . '"' : '' ) . '>';
            if ( $icon ) $output .= '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i> ';
            $output .= esc_html( $item->title );
            $output .= '</a>';
        }
    }

    public function end_el( &$output, $data_object, $depth = 0, $args = null ) {
        if ( $depth === 0 ) $output .= '</div>';
    }

    public function start_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '<div class="nav-dropdown">';
    }

    public function end_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '</div>';
    }
}

/* ============================================================
   樣式載入
   ============================================================ */
add_action( 'wp_enqueue_scripts', 'smaacg_enqueue_styles' );
function smaacg_enqueue_styles() {

    wp_enqueue_style(
        'blocksy-parent',
        get_template_directory_uri() . '/style.css',
        [],
        wp_get_theme( 'blocksy' )->get( 'Version' )
    );

    wp_enqueue_style(
        'smaacg-fonts',
        'https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;500;700&family=Inter:wght@300;400;500;600;700;800&display=swap',
        [], null
    );

    $global_styles = [
        'smaacg-glass' => [ 'file' => 'glass.css', 'dep' => ['blocksy-parent'] ],
        'smaacg-style' => [ 'file' => 'style.css',  'dep' => ['smaacg-glass']  ],
    ];
    foreach ( $global_styles as $handle => $cfg ) {
        $path = SMAACG_THEME_DIR . '/assets/css/' . $cfg['file'];
        if ( file_exists( $path ) ) {
            wp_enqueue_style( $handle, SMAACG_THEME_URL . '/assets/css/' . $cfg['file'], $cfg['dep'], filemtime( $path ) );
        }
    }

    $conditional_styles = [];
    if ( is_page('news') || is_page_template('page-news.php') )             $conditional_styles['smaacg-news']       = 'news.css';
    if ( is_page('season') || is_page_template('page-season.php') )         $conditional_styles['smaacg-season']     = 'season.css';
    if ( is_page_template('page-ranking.php') )                              $conditional_styles['smaacg-ranking']    = 'ranking.css';
    if ( is_page_template('page-anime-list.php') )                           $conditional_styles['smaacg-anime-list'] = 'anime-list.css';
    if ( is_page_template('page-music.php') )                                $conditional_styles['smaacg-music']      = 'music.css';
    if ( is_page_template('page-cosplay.php') )                              $conditional_styles['smaacg-cosplay']    = 'cosplay.css';
    if ( is_search() )                                                        $conditional_styles['smaacg-search']     = 'search.css';
    if ( is_404() )                                                           $conditional_styles['smaacg-404']        = '404.css';
    if ( is_page( ['about','contact','disclaimer','sources','privacy','terms'] ) ) $conditional_styles['smaacg-static'] = 'static.css';

    foreach ( $conditional_styles as $handle => $file ) {
        $path = SMAACG_THEME_DIR . '/assets/css/' . $file;
        if ( file_exists( $path ) ) {
            wp_enqueue_style( $handle, SMAACG_THEME_URL . '/assets/css/' . $file, ['smaacg-style'], filemtime( $path ) );
        }
    }

    if ( is_singular('anime') ) {
        $anime_css = WP_PLUGIN_DIR . '/anime-sync-pro/public/assets/css/anime-single.css';
        if ( file_exists( $anime_css ) ) {
            wp_enqueue_style(
                'smaacg-anime',
                plugins_url( 'anime-sync-pro/public/assets/css/anime-single.css' ),
                ['smaacg-style'],
                filemtime( $anime_css )
            );
        }
    }

    $admin_css = SMAACG_THEME_DIR . '/assets/css/admin-sync.css';
    if ( file_exists( $admin_css ) ) {
        wp_enqueue_style( 'smaacg-admin-sync', SMAACG_THEME_URL . '/assets/css/admin-sync.css', ['smaacg-style'], filemtime( $admin_css ) );
    }
}

/* ── FA6 ── */
add_action( 'wp_enqueue_scripts', function () {
    wp_dequeue_style( 'font-awesome' );
    wp_deregister_style( 'font-awesome' );
    wp_enqueue_style( 'smaacg-fa6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css', [], '6.5.0' );
}, 999 );

/* ============================================================
   JS 載入
   ============================================================ */
add_action( 'wp_enqueue_scripts', 'smaacg_enqueue_scripts' );
function smaacg_enqueue_scripts() {

    $global_scripts = [
        'smaacg-api'           => [ 'file' => 'api.js',           'dep' => []              ],
        'smaacg-utils'         => [ 'file' => 'utils.js',         'dep' => ['smaacg-api']  ],
        'smaacg-page-template' => [ 'file' => 'page-template.js', 'dep' => ['smaacg-utils'] ],
        'smaacg-nav'           => [ 'file' => 'nav.js',           'dep' => ['smaacg-utils'] ],
    ];
    foreach ( $global_scripts as $handle => $cfg ) {
        $path = SMAACG_THEME_DIR . '/assets/js/' . $cfg['file'];
        if ( file_exists( $path ) ) {
            wp_enqueue_script( $handle, SMAACG_THEME_URL . '/assets/js/' . $cfg['file'], $cfg['dep'], filemtime( $path ), true );
        }
    }

    if ( is_front_page() ) {
        $path = SMAACG_THEME_DIR . '/assets/js/main.js';
        if ( file_exists( $path ) ) {
            wp_enqueue_script( 'smaacg-main', SMAACG_THEME_URL . '/assets/js/main.js', ['smaacg-api'], filemtime( $path ), true );
        }
    }

    if ( is_singular('anime') ) {
        $path = SMAACG_THEME_DIR . '/assets/js/anime.js';
        if ( file_exists( $path ) ) {
            wp_enqueue_script( 'smaacg-anime-js', SMAACG_THEME_URL . '/assets/js/anime.js', ['smaacg-api'], filemtime( $path ), true );
        }
    }

    if ( is_page_template('page-ranking.php') ) {
        $path = SMAACG_THEME_DIR . '/assets/js/ranking.js';
        if ( file_exists( $path ) ) {
            wp_enqueue_script( 'smaacg-ranking', SMAACG_THEME_URL . '/assets/js/ranking.js', ['smaacg-utils','smaacg-api'], filemtime( $path ), true );
        }
    }

    wp_localize_script( 'smaacg-nav', 'smaacg_ajax', [
        'ajax_url'     => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'smaacg_nonce' ),
        'user_id'      => get_current_user_id(),
        'is_logged_in' => is_user_logged_in(),
        'user_level'   => smaacg_get_user_level_int(),
        'site_url'     => home_url(),
        'login_url'    => wp_login_url( get_permalink() ),
        'register_url' => wp_registration_url(),
        'rest_url'     => rest_url( 'smaacg/v1/' ),
        'rest_nonce'   => wp_create_nonce( 'wp_rest' ),
    ] );
}

/* ============================================================
   用戶等級輔助函式（簡單整數版，供 enqueue 用）
   ============================================================ */
function smaacg_get_user_level_int() : int {
    if ( ! is_user_logged_in() ) return 0;
    $user_id = get_current_user_id();
    $level   = (int) get_user_meta( $user_id, 'smaacg_user_level', true );
    if ( ! $level && function_exists( 'um_user' ) ) {
        $role = um_user( 'role' );
        if ( $role === 'smaacg_vip' )     $level = 3;
        elseif ( $role === 'smaacg_pro' ) $level = 2;
        else                              $level = 1;
    }
    return $level ?: 1;
}

function smaacg_get_user_points( int $user_id = 0 ) : int {
    $uid = $user_id ?: get_current_user_id();
    return (int) get_user_meta( $uid, 'smaacg_points', true );
}

/* ============================================================
   管理後台樣式
   ============================================================ */
add_action( 'admin_enqueue_scripts', 'smaacg_admin_styles' );
function smaacg_admin_styles() {
    $admin_css = SMAACG_THEME_DIR . '/assets/css/admin-sync.css';
    if ( file_exists( $admin_css ) ) {
        wp_enqueue_style( 'smaacg-admin', SMAACG_THEME_URL . '/assets/css/admin-sync.css', [], filemtime( $admin_css ) );
    }
}

/* ============================================================
   AJAX — 收藏
   ============================================================ */
add_action( 'wp_ajax_smaacg_toggle_favorite', 'smaacg_ajax_toggle_favorite' );
function smaacg_ajax_toggle_favorite() {
    check_ajax_referer( 'smaacg_nonce', 'nonce' );
    $post_id = (int) ( $_POST['post_id'] ?? 0 );
    $user_id = get_current_user_id();
    if ( ! $post_id || ! $user_id ) wp_send_json_error( [ 'msg' => '無效請求' ] );
    $favs = get_user_meta( $user_id, 'smaacg_favorites', true ) ?: [];
    $key  = array_search( $post_id, $favs );
    if ( $key !== false ) { unset( $favs[ $key ] ); $action = 'removed'; }
    else                  { $favs[] = $post_id;      $action = 'added';   }
    update_user_meta( $user_id, 'smaacg_favorites', array_values( $favs ) );
    wp_send_json_success( [ 'action' => $action, 'count' => count( $favs ) ] );
}

/* ============================================================
   AJAX — 追番進度
   ============================================================ */
add_action( 'wp_ajax_smaacg_update_progress', 'smaacg_ajax_update_progress' );
function smaacg_ajax_update_progress() {
    check_ajax_referer( 'smaacg_nonce', 'nonce' );
    $post_id  = (int) ( $_POST['post_id']      ?? 0 );
    $progress = (int) ( $_POST['progress']      ?? 0 );
    $status   = sanitize_text_field( $_POST['watch_status'] ?? '' );
    $user_id  = get_current_user_id();
    if ( ! $post_id || ! $user_id ) wp_send_json_error( [ 'msg' => '無效請求' ] );
    $data = [ 'progress' => $progress, 'watch_status' => $status, 'updated_at' => time() ];
    update_user_meta( $user_id, "smaacg_progress_{$post_id}", $data );
    wp_send_json_success( $data );
}

/* ============================================================
   AJAX — 即時搜尋
   ============================================================ */
add_action( 'wp_ajax_smaacg_search',        'smaacg_ajax_search' );
add_action( 'wp_ajax_nopriv_smaacg_search', 'smaacg_ajax_search' );
function smaacg_ajax_search() {
    check_ajax_referer( 'smaacg_nonce', 'nonce' );
    $keyword = sanitize_text_field( $_POST['keyword'] ?? '' );
    $type    = sanitize_text_field( $_POST['type']    ?? 'all' );
    if ( strlen( $keyword ) < 2 ) wp_send_json_error( [ 'msg' => '關鍵字太短' ] );
    $post_types = match ( $type ) {
        'anime' => ['anime'], 'manga' => ['manga'],
        'character' => ['character'], 'va' => ['voice-actor'], 'music' => ['music'],
        default => ['anime','manga','novel','game','character','voice-actor','post'],
    };
    $query   = new WP_Query( [ 's' => $keyword, 'post_type' => $post_types, 'posts_per_page' => 12, 'post_status' => 'publish' ] );
    $results = [];
    while ( $query->have_posts() ) {
        $query->the_post();
        $pid       = get_the_ID();
        $results[] = [
            'id'       => $pid,
            'title'    => get_the_title(),
            'title_zh' => get_field( 'smaacg_title_zh', $pid ) ?: get_the_title(),
            'type'     => get_post_type(),
            'url'      => get_permalink(),
            'cover'    => get_the_post_thumbnail_url( $pid, 'smaacg-thumb' ) ?: get_field( 'smaacg_cover_url', $pid ),
            'score'    => get_field( 'smaacg_score_anilist', $pid ),
        ];
    }
    wp_reset_postdata();
    wp_send_json_success( [ 'results' => $results, 'total' => $query->found_posts ] );
}

/* ============================================================
   AJAX — 評分
   ============================================================ */
add_action( 'wp_ajax_smaacg_submit_rating', 'smaacg_ajax_submit_rating' );
function smaacg_ajax_submit_rating() {
    check_ajax_referer( 'smaacg_nonce', 'nonce' );
    $post_id = (int)   ( $_POST['post_id'] ?? 0 );
    $score   = (float) ( $_POST['score']   ?? 0 );
    $user_id = get_current_user_id();
    if ( ! $post_id || ! $user_id ) wp_send_json_error( [ 'msg' => '請先登入' ] );
    if ( smaacg_get_user_level_int() < 2 ) wp_send_json_error( [ 'msg' => '需要 Lv.2 以上才能評分', 'code' => 'level_required' ] );
    if ( $score < 1 || $score > 10 )       wp_send_json_error( [ 'msg' => '評分範圍 1–10' ] );
    if ( function_exists( 'yasr_save_visitor_vote' ) ) {
        $result = yasr_save_visitor_vote( $post_id, $score );
        wp_send_json_success( [ 'msg' => '評分成功', 'yasr' => $result ] );
    }
    update_user_meta( $user_id, "smaacg_rating_{$post_id}", $score );
    wp_send_json_success( [ 'msg' => '評分成功' ] );
}

/* ============================================================
   AJAX — Bangumi 重同步
   ============================================================ */
add_action( 'wp_ajax_smaacg_resync_bangumi', 'smaacg_ajax_resync_bangumi' );
function smaacg_ajax_resync_bangumi() {
    check_ajax_referer( 'smaacg_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'msg' => '權限不足' ] );
    if ( class_exists( 'Anime_Sync_API_Handler' ) ) {
        ( new Anime_Sync_API_Handler() )->ajax_resync_bangumi();
    } else {
        wp_send_json_error( [ 'msg' => 'API Handler 類別未載入' ] );
    }
}

/* ============================================================
   REST API
   ============================================================ */
add_action( 'rest_api_init', 'smaacg_register_rest_routes' );
function smaacg_register_rest_routes() {
    register_rest_route( 'smaacg/v1', '/ranking', [
        'methods'             => 'GET',
        'callback'            => 'smaacg_rest_ranking',
        'permission_callback' => '__return_true',
        'args'                => [
            'platform' => [ 'default' => 'anilist', 'sanitize_callback' => 'sanitize_text_field' ],
            'period'   => [ 'default' => 'weekly',  'sanitize_callback' => 'sanitize_text_field' ],
            'limit'    => [ 'default' => 20,          'sanitize_callback' => 'absint' ],
        ],
    ] );
    register_rest_route( 'smaacg/v1', '/user/favorites', [
        'methods'             => 'GET',
        'callback'            => 'smaacg_rest_user_favorites',
        'permission_callback' => 'is_user_logged_in',
    ] );
}

function smaacg_rest_ranking( WP_REST_Request $request ) : WP_REST_Response {
    $platform    = $request->get_param( 'platform' );
    $limit       = min( $request->get_param( 'limit' ), 50 );
    $score_field = match ( $platform ) {
        'mal'     => 'smaacg_score_mal',
        'bangumi' => 'smaacg_score_bangumi',
        default   => 'smaacg_score_anilist',
    };
    $query = new WP_Query( [
        'post_type'      => 'anime',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'meta_key'       => $score_field,
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
        'meta_query'     => [ [ 'key' => $score_field, 'compare' => 'EXISTS' ] ],
    ] );
    $items = []; $rank = 1;
    while ( $query->have_posts() ) {
        $query->the_post();
        $pid     = get_the_ID();
        $items[] = [
            'rank'       => $rank++,
            'id'         => $pid,
            'title_zh'   => get_field( 'smaacg_title_zh', $pid ) ?: get_the_title(),
            'title_jp'   => get_field( 'smaacg_title_ja', $pid ),
            'cover'      => get_the_post_thumbnail_url( $pid, 'smaacg-cover' ) ?: get_field( 'smaacg_cover_url', $pid ),
            'score'      => (float) get_field( $score_field, $pid ),
            'url'        => get_permalink(),
            'anilist_id' => (int) get_field( 'smaacg_anilist_id', $pid ),
        ];
    }
    wp_reset_postdata();
    return new WP_REST_Response( [ 'platform' => $platform, 'data' => $items ], 200 );
}

function smaacg_rest_user_favorites( WP_REST_Request $request ) : WP_REST_Response {
    $user_id = get_current_user_id();
    $fav_ids = get_user_meta( $user_id, 'smaacg_favorites', true ) ?: [];
    $items   = [];
    foreach ( (array) $fav_ids as $pid ) {
        if ( ! $pid ) continue;
        $items[] = [
            'id'       => $pid,
            'title_zh' => get_field( 'smaacg_title_zh', $pid ) ?: get_the_title( $pid ),
            'cover'    => get_the_post_thumbnail_url( $pid, 'smaacg-thumb' ),
            'url'      => get_permalink( $pid ),
        ];
    }
    return new WP_REST_Response( [ 'favorites' => $items ], 200 );
}

/* ============================================================
   Widgets
   ============================================================ */
add_action( 'widgets_init', 'smaacg_widgets_init' );
function smaacg_widgets_init() {
    register_sidebar( [ 'name' => __( '頁腳 Widget 區 1', 'smaacg' ), 'id' => 'footer-1',
        'before_widget' => '<div class="footer-widget">', 'after_widget' => '</div>',
        'before_title'  => '<h5 class="footer-widget-title">', 'after_title' => '</h5>' ] );
    register_sidebar( [ 'name' => __( '側欄 Widget 區', 'smaacg' ), 'id' => 'sidebar-1',
        'before_widget' => '<div class="sidebar-card glass-mid">', 'after_widget' => '</div>',
        'before_title'  => '<div class="rank-sidebar-title">', 'after_title' => '</div>' ] );
}

/* ============================================================
   Excerpt / 搜尋表單
   ============================================================ */
add_filter( 'excerpt_length', fn() => 60 );
add_filter( 'excerpt_more',   fn() => '…' );

add_filter( 'get_search_form', 'smaacg_custom_search_form' );
function smaacg_custom_search_form( string $form ) : string {
    ob_start(); ?>
    <form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
        <div class="search-box glass-light">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="search" id="search-input" name="s"
                   placeholder="<?php esc_attr_e( '搜尋動畫、角色、聲優、新聞…', 'smaacg' ); ?>"
                   value="<?php echo esc_attr( get_search_query() ); ?>"
                   autocomplete="off" />
            <button type="submit" class="btn-icon btn-ghost" aria-label="<?php esc_attr_e( '搜尋', 'smaacg' ); ?>">
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </div>
    </form>
    <?php return ob_get_clean();
}

/* ============================================================
   輔助函式
   ============================================================ */
if ( ! function_exists( 'smaacg_get_news_thumb' ) ) {
    function smaacg_get_news_thumb( int $post_id, string $size = 'news-thumb' ) : string {
        $url = get_the_post_thumbnail_url( $post_id, $size );
        if ( $url ) return $url;
        $post    = get_post( $post_id );
        $content = $post->post_content ?? '';
        if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $content, $m ) ) return $m[1];
        if ( function_exists( 'get_field' ) ) {
            $acf = get_field( 'smaacg_cover_url', $post_id );
            if ( $acf ) return $acf;
        }
        return '';
    }
}

/* ============================================================
   強制使用子主題 header.php
   ============================================================ */
add_filter( 'blocksy:header:is-enabled', '__return_false' );
add_action( 'get_header', function( $name ) {
    $template = locate_template( 'header.php' );
    if ( $template ) load_template( $template );
}, 1 );

add_filter( 'blocksy_hero_enabled', '__return_false' );

/* ============================================================
   防呆佔位函式
   ============================================================ */
if ( ! function_exists( 'smaacg_get_anilist' ) ) {
    function smaacg_get_anilist( $id ) { return null; }
}
if ( ! function_exists( 'smaacg_get_bangumi' ) ) {
    function smaacg_get_bangumi( $id, $type = 2 ) { return null; }
}

/* ============================================================
   REST — 依 AniList ID 查站內 URL
   ============================================================ */
add_action( 'rest_api_init', function () {
    register_rest_route( 'smileacg/v1', '/anime-url', [
        'methods'             => 'GET',
        'callback'            => 'smaacg_get_anime_url_by_anilist',
        'permission_callback' => '__return_true',
        'args' => [ 'ids' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ] ],
    ] );
} );

function smaacg_get_anime_url_by_anilist( WP_REST_Request $request ) {
    $ids_raw = $request->get_param('ids');
    $ids     = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );
    if ( empty( $ids ) ) return new WP_Error( 'no_ids', 'ids 參數必填', [ 'status' => 400 ] );
    $posts = get_posts( [
        'post_type' => 'anime', 'post_status' => 'publish',
        'posts_per_page' => count( $ids ), 'no_found_rows' => true,
        'meta_query' => [ [ 'key' => 'anime_anilist_id', 'value' => $ids, 'compare' => 'IN', 'type' => 'NUMERIC' ] ],
    ] );
    $map = [];
    foreach ( $posts as $post ) {
        $al_id = (int) get_post_meta( $post->ID, 'anime_anilist_id', true );
        if ( $al_id ) $map[ $al_id ] = [ 'url' => get_permalink( $post->ID ), 'slug' => $post->post_name ];
    }
    return rest_ensure_response( $map );
}

/* ============================================================
   防止選單 Warning
   ============================================================ */
add_action( 'admin_init', function () {
    $locations  = get_nav_menu_locations();
    $registered = array_keys( get_registered_nav_menus() );
    foreach ( $registered as $location ) {
        if ( empty( $locations[ $location ] ) ) $locations[ $location ] = 0;
    }
    set_theme_mod( 'nav_menu_locations', $locations );
} );

/* ============================================================
   AJAX 登入
   ============================================================ */
add_action( 'wp_ajax_nopriv_smaacg_ajax_login', 'smaacg_ajax_login' );
function smaacg_ajax_login() {
    check_ajax_referer( 'smaacg_nonce', 'nonce' );
    $username = sanitize_user( $_POST['log'] ?? '' );
    $password = $_POST['pwd'] ?? '';
    $remember = ! empty( $_POST['rememberme'] );
    if ( ! $username || ! $password ) wp_send_json_error( [ 'msg' => '請輸入帳號和密碼' ] );
    $user = wp_signon( [ 'user_login' => $username, 'user_password' => $password, 'remember' => $remember ], is_ssl() );
    if ( is_wp_error( $user ) ) wp_send_json_error( [ 'msg' => '帳號或密碼錯誤，請再試一次' ] );
    wp_send_json_success( [ 'msg' => '登入成功', 'redirect' => home_url('/') ] );
}

/* ============================================================
   Nav overflow fix
   ============================================================ */
add_action( 'wp_footer', function() {
    echo '<script>
    (function() {
        function fixNavOverflow() {
            var bar = document.querySelector(".header-nav-bar");
            var nav = document.querySelector(".header-nav-bar .primary-nav");
            if (bar) { bar.style.setProperty("overflow-x","auto","important"); bar.style.setProperty("overflow-y","visible","important"); }
            if (nav) { nav.style.setProperty("overflow","visible","important"); }
        }
        fixNavOverflow();
        window.addEventListener("load", fixNavOverflow);
        setTimeout(fixNavOverflow, 500);
    })();
    </script>';
}, 999 );

/* ============================================================
   微笑動漫 — 動漫追蹤 & 積分系統
   ============================================================ */
define( 'SMACG_POINT_FAVORITE',  5  );
define( 'SMACG_POINT_WANT',      1  );
define( 'SMACG_POINT_WATCHING',  3  );
define( 'SMACG_POINT_COMPLETED', 8  );
define( 'SMACG_POINT_FULLCLEAR', 10 );
define( 'SMACG_POINT_EPISODE',   1  );
define( 'SMACG_POINT_READ',      2  );
define( 'SMACG_POINT_COMMENT',   3  );
define( 'SMACG_POINT_LOGIN',     1  );

/* ── 等級設定 ── */
function smacg_get_levels(): array {
    return [
        [ 'min' => 0,    'label' => '🌱 新手',   'key' => 'newbie'  ],
        [ 'min' => 100,  'label' => '⭐ 動漫迷', 'key' => 'lover'   ],
        [ 'min' => 500,  'label' => '💫 老手',   'key' => 'veteran' ],
        [ 'min' => 2000, 'label' => '🔥 狂熱者', 'key' => 'fanatic' ],
        [ 'min' => 5000, 'label' => '👑 大師',   'key' => 'master'  ],
    ];
}

function smacg_get_user_level( int $user_id ): array {
    $points  = (int) get_user_meta( $user_id, 'anime_total_points', true );
    $levels  = smacg_get_levels();
    $current = $levels[0];
    $next    = null;
    foreach ( $levels as $i => $level ) {
        if ( $points >= $level['min'] ) { $current = $level; $next = $levels[ $i + 1 ] ?? null; }
    }
    $progress_pct = 100;
    if ( $next ) {
        $range        = $next['min'] - $current['min'];
        $earned       = $points - $current['min'];
        $progress_pct = $range > 0 ? min( 100, round( $earned / $range * 100 ) ) : 100;
    }
    return [ 'points' => $points, 'current' => $current, 'next' => $next, 'progress_pct' => $progress_pct ];
}

/* ── 積分核心 ── */
function smacg_add_points( int $user_id, int $points, string $reason = '' ): void {
    if ( $points <= 0 || ! $user_id ) return;
    $total = (int) get_user_meta( $user_id, 'anime_total_points', true );
    update_user_meta( $user_id, 'anime_total_points', $total + $points );
    $log   = json_decode( get_user_meta( $user_id, 'anime_points_log', true ) ?: '[]', true );
    $log[] = [ 'points' => $points, 'reason' => $reason, 'time' => time() ];
    if ( count( $log ) > 100 ) $log = array_slice( $log, -100 );
    update_user_meta( $user_id, 'anime_points_log', wp_json_encode( $log ) );
}

function smacg_get_anime_data( int $user_id ): array {
    $raw = get_user_meta( $user_id, 'anime_user_data', true );
    return $raw ? json_decode( $raw, true ) : [];
}

function smacg_save_anime_data( int $user_id, array $data ): void {
    update_user_meta( $user_id, 'anime_user_data', wp_json_encode( $data ) );
}

function smacg_check_cooldown( int $user_id, string $action, int $post_id ): bool {
    $key  = "smacg_cd_{$action}_{$post_id}";
    $last = (int) get_user_meta( $user_id, $key, true );
    if ( time() - $last < DAY_IN_SECONDS ) return false;
    update_user_meta( $user_id, $key, time() );
    return true;
}

/* ── REST API ── */
add_action( 'rest_api_init', function () {
    register_rest_route( 'smacg/v1', '/anime-data', [
        'methods' => 'GET', 'callback' => 'smacg_api_get_data', 'permission_callback' => 'is_user_logged_in',
    ] );
    register_rest_route( 'smacg/v1', '/anime-update', [
        'methods'             => 'POST',
        'callback'            => 'smacg_api_update',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'post_id' => [ 'required' => true, 'type' => 'integer', 'minimum' => 1 ],
            'action'  => [ 'required' => true, 'type' => 'string', 'enum' => ['status','progress','favorite','fullclear'] ],
            'value'   => [ 'required' => false ],
        ],
    ] );
    register_rest_route( 'smacg/v1', '/user-level', [
        'methods' => 'GET', 'callback' => 'smacg_api_user_level', 'permission_callback' => 'is_user_logged_in',
    ] );
} );

function smacg_api_get_data(): WP_REST_Response {
    return rest_ensure_response( smacg_get_anime_data( get_current_user_id() ) );
}

function smacg_api_update( WP_REST_Request $req ): WP_REST_Response|WP_Error {
    $uid     = get_current_user_id();
    $post_id = (int) $req->get_param( 'post_id' );
    $action  = sanitize_text_field( $req->get_param( 'action' ) );
    $value   = $req->get_param( 'value' );
    if ( ! get_post( $post_id ) ) return new WP_Error( 'not_found', '找不到文章', [ 'status' => 404 ] );
    $data   = smacg_get_anime_data( $uid );
    $entry  = $data[ $post_id ] ?? [ 'status' => null, 'progress' => 0, 'favorited' => false, 'fullcleared' => false ];
    $points = 0;

    switch ( $action ) {
        case 'status':
            $allowed = ['want','watching','completed','dropped','none'];
            if ( ! in_array( $value, $allowed, true ) ) return new WP_Error( 'invalid_status', '無效狀態', [ 'status' => 400 ] );
            $old_status      = $entry['status'] ?? null;
            $entry['status'] = $value === 'none' ? null : $value;
            if ( $value !== 'none' && $value !== $old_status && smacg_check_cooldown( $uid, "status_{$value}", $post_id ) ) {
                $points = match ( $value ) { 'want' => SMACG_POINT_WANT, 'watching' => SMACG_POINT_WATCHING, 'completed' => SMACG_POINT_COMPLETED, default => 0 };
            }
            /* 點已看完自動補滿進度 */
            if ( $value === 'completed' ) {
                $total_ep = (int) get_post_meta( $post_id, 'anime_episodes', true );
                if ( $total_ep > 0 ) $entry['progress'] = $total_ep;
                if ( ! $entry['fullcleared'] ) {
                    $entry['fullcleared'] = true;
                    if ( smacg_check_cooldown( $uid, 'fullclear', $post_id ) ) $points += SMACG_POINT_FULLCLEAR;
                }
            }
            break;

        case 'progress':
            $total_ep = (int) get_post_meta( $post_id, 'anime_episodes', true );
            $delta    = (int) $value;
            $new_prog = max( 0, $entry['progress'] + $delta );
            if ( $total_ep > 0 ) $new_prog = min( $total_ep, $new_prog );
            if ( $delta > 0 && $new_prog > $entry['progress'] ) {
                $ep_key = "ep_{$post_id}_{$new_prog}";
                if ( smacg_check_cooldown( $uid, $ep_key, $post_id ) ) $points = SMACG_POINT_EPISODE;
            }
            $entry['progress'] = $new_prog;
            if ( $total_ep > 0 && $new_prog >= $total_ep && ! $entry['fullcleared'] ) {
                $entry['fullcleared'] = true;
                if ( smacg_check_cooldown( $uid, 'fullclear', $post_id ) ) $points += SMACG_POINT_FULLCLEAR;
            }
            break;

        case 'favorite':
            $now_fav            = ! $entry['favorited'];
            $entry['favorited'] = $now_fav;
            if ( $now_fav && smacg_check_cooldown( $uid, 'favorite', $post_id ) ) $points = SMACG_POINT_FAVORITE;
            break;

        case 'fullclear':
            if ( ! $entry['fullcleared'] ) {
                $entry['fullcleared'] = true;
                if ( smacg_check_cooldown( $uid, 'fullclear', $post_id ) ) $points = SMACG_POINT_FULLCLEAR;
                $total_ep = (int) get_post_meta( $post_id, 'anime_episodes', true );
                if ( $total_ep > 0 ) $entry['progress'] = $total_ep;
                $entry['status'] = 'completed';
            }
            break;
    }

    $data[ $post_id ] = $entry;
    smacg_save_anime_data( $uid, $data );
    if ( $points > 0 ) smacg_add_points( $uid, $points, "{$action}:{$post_id}" );

    return rest_ensure_response( [
        'success'       => true,
        'entry'         => $entry,
        'points_earned' => $points,
        'total_points'  => (int) get_user_meta( $uid, 'anime_total_points', true ),
        'level'         => smacg_get_user_level( $uid ),
    ] );
}

function smacg_api_user_level(): WP_REST_Response {
    return rest_ensure_response( smacg_get_user_level( get_current_user_id() ) );
}

/* ── 閱讀積分 ── */
add_action( 'wp_ajax_smacg_read_article', function () {
    check_ajax_referer( 'smacg_nonce', 'nonce' );
    $uid     = get_current_user_id();
    $post_id = (int) ( $_POST['post_id'] ?? 0 );
    if ( $uid && $post_id && smacg_check_cooldown( $uid, 'read', $post_id ) ) {
        smacg_add_points( $uid, SMACG_POINT_READ, "read:{$post_id}" );
    }
    wp_send_json_success();
} );

/* ── 留言積分 ── */
add_action( 'comment_post', function ( $comment_id, $approved ) {
    if ( $approved !== 1 ) return;
    $comment = get_comment( $comment_id );
    $uid     = (int) $comment->user_id;
    if ( $uid && smacg_check_cooldown( $uid, 'comment', (int) $comment->comment_post_ID ) ) {
        smacg_add_points( $uid, SMACG_POINT_COMMENT, "comment:{$comment->comment_post_ID}" );
    }
}, 10, 2 );

/* ── 每日登入積分 ── */
add_action( 'um_user_login', function ( $user_id ) {
    $uid   = (int) $user_id;
    if ( ! $uid ) return;
    $last  = (string) get_user_meta( $uid, 'smacg_last_login_date', true );
    $today = date( 'Y-m-d' );
    if ( $last !== $today ) {
        update_user_meta( $uid, 'smacg_last_login_date', $today );
        smacg_add_points( $uid, SMACG_POINT_LOGIN, 'daily_login' );
    }
} );

/* ── 動漫單頁：載入追蹤 UI 腳本（唯一一個）── */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_singular( 'anime' ) ) return;

    wp_enqueue_style(
        'smacg-anime-status',
        get_stylesheet_directory_uri() . '/assets/css/anime-status.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'smacg-anime-status',
        get_stylesheet_directory_uri() . '/assets/js/anime-status.js',
        [ 'jquery' ],
        '1.0.0',
        true
    );

    wp_localize_script( 'smacg-anime-status', 'SmacgConfig', [
        'apiUrl'    => esc_url_raw( rest_url( 'smacg/v1/' ) ),
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'wp_rest' ),
        'ajaxNonce' => wp_create_nonce( 'smacg_nonce' ),
        'loggedIn'  => is_user_logged_in(),
        'loginUrl'  => function_exists( 'um_get_core_page' )
                       ? um_get_core_page( 'login' )
                       : wp_login_url( get_permalink() ),
        'postId'    => get_the_ID(),
        'permalink' => get_permalink(),
        'title'     => get_the_title(),
    ] );
}, 20 );

/* ── 登入 Modal（wp_footer 輸出，不依賴 header.php）── */
add_action( 'wp_footer', function () {
    if ( is_user_logged_in() ) return;
    ?>
    <div id="login-modal" class="lm-overlay" role="dialog" aria-modal="true" aria-label="登入">
      <div class="lm-box">
        <button class="lm-close" id="lm-close" aria-label="關閉"><i class="fa-solid fa-xmark"></i></button>
        <div class="lm-logo">
          <span class="logo-icon-box" aria-hidden="true">^_^</span>
          <span class="logo-text">微笑動漫<span class="logo-plus">+</span></span>
        </div>
        <p class="lm-subtitle">登入以解鎖完整功能</p>
        <div class="lm-tabs">
          <button class="lm-tab active" data-tab="login">登入</button>
          <button class="lm-tab" data-tab="register">註冊</button>
        </div>
        <div class="lm-panel" id="lm-panel-login">
          <?php echo do_shortcode('[ultimatemember form_id="1519"]'); ?>
        </div>
        <div class="lm-panel" id="lm-panel-register" hidden>
          <?php echo do_shortcode('[ultimatemember form_id="1518"]'); ?>
        </div>
      </div>
    </div>

    <script>
    (function () {
        const modal    = document.getElementById('login-modal');
        const closeBtn = document.getElementById('lm-close');
        const tabs     = document.querySelectorAll('#login-modal .lm-tab');
        const panels   = {
            login    : document.getElementById('lm-panel-login'),
            register : document.getElementById('lm-panel-register'),
        };

        function openModal() {
            if (!modal) return;
            document.body.style.overflow = 'hidden';
            setTimeout(() => modal.classList.add('lm-open'), 10);
        }

        function closeModal() {
            if (!modal) return;
            modal.classList.remove('lm-open');
            document.body.style.overflow = '';
        }

        function switchTab(target) {
            tabs.forEach(t => t.classList.remove('active'));
            document.querySelector(`#login-modal .lm-tab[data-tab="${target}"]`)?.classList.add('active');
            Object.entries(panels).forEach(([key, el]) => { if (el) el.hidden = (key !== target); });
        }

        window.smacgOpenLoginModal = function () {
            switchTab('login');
            openModal();
        };

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (modal) modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal?.classList.contains('lm-open')) closeModal(); });
        tabs.forEach(tab => tab.addEventListener('click', function () { switchTab(this.dataset.tab); }));

        document.addEventListener('click', function (e) {
            const btn    = e.target.closest('#open-login-modal, .header-login-btn');
            const regBtn = e.target.closest('#open-register-modal, .header-reg-btn');
            if (btn)    { e.preventDefault(); switchTab('login');    openModal(); }
            if (regBtn) { e.preventDefault(); switchTab('register'); openModal(); }
        });
    })();
    </script>
    <?php
}, 99 );

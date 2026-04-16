<?php
/**
 * Frontend Handler
 * @package Anime_Sync_Pro
 *
 * ACG – enqueue_assets() 加入 is_search + post_type=anime 條件
 *       load_single_template() 加入 is_search + post_type=anime 條件
 *       讓搜尋結果頁套用 archive-anime.php 模板並正確載入 CSS/JS
 *       新增 filter_anime_search()：搜尋時同時查詢
 *       anime_title_romaji、anime_title_english meta 欄位
 *       僅在 post_type=anime 搜尋時生效，不影響其他搜尋
 * ACG v2 – anime-single.css 擴展至 archive / taxonomy / search 頁
 *          確保 --asd-* CSS 變數在所有 anime 頁面皆可用
 * ACG v3 – 新增 anime_series_tax 系列分類法支援
 *          enqueue_assets() 加入 is_tax('anime_series_tax') 條件
 *          load_single_template() 加入系列頁路由 → archive-series.php
 *          新增 pre_get_posts hook → sort_series_archive()
 *          sort_series_archive() 使用 $query->is_tax() 避免全域污染（Bug #6 修正）
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets'      ] );
        add_filter( 'template_include',   [ $this, 'load_single_template' ] );
        add_action( 'wp_head',            [ $this, 'output_seo_meta'      ] );
        add_filter( 'the_title',          [ $this, 'filter_title'         ], 10, 2 );
        // ✅ Bug B 修正：移除 output_json_ld hook，Schema 由模板自行輸出，避免重複
        add_filter( 'body_class',         [ $this, 'add_body_classes'     ] );
        add_shortcode( 'anime_score',     [ $this, 'shortcode_score'      ] );
        add_shortcode( 'anime_streaming', [ $this, 'shortcode_streaming'  ] );
        add_shortcode( 'anime_themes',    [ $this, 'shortcode_themes'     ] );
        add_action( 'rest_api_init',      [ $this, 'register_rest_routes' ] );
        // ACG：搜尋時同時查詢 romaji / english meta 欄位
        add_filter( 'posts_search',       [ $this, 'filter_anime_search'  ], 10, 2 );
        // ACG v3：系列頁排序 hook
        add_action( 'pre_get_posts',      [ $this, 'sort_series_archive'  ] );
    }

    // =========================================================
    // 資源載入
    // =========================================================
    public function enqueue_assets(): void {

        // ACG：加入搜尋頁條件
        $is_anime_search = is_search() && get_query_var( 'post_type' ) === 'anime';

        if ( ! is_singular( 'anime' )
            && ! is_post_type_archive( 'anime' )
            && ! is_tax( 'genre' )
            && ! is_tax( 'anime_season_tax' )
            && ! is_tax( 'anime_format_tax' )
            && ! is_tax( 'anime_series_tax' )
            && ! $is_anime_search
        ) {
            return;
        }

        wp_enqueue_style(
            'anime-sync-public',
            ANIME_SYNC_PRO_URL . 'public/assets/css/public.css',
            [],
            ANIME_SYNC_PRO_VERSION
        );
        wp_enqueue_style(
            'anime-sync-style',
            ANIME_SYNC_PRO_URL . 'public/assets/css/style.css',
            [ 'anime-sync-public' ],
            ANIME_SYNC_PRO_VERSION
        );

        // ✅ Bug F 修正：anime-single.css 載入
        // ACG v2：擴展至 archive / taxonomy / search 頁，確保 --asd-* 變數全站可用
        // ACG v3：加入 anime_series_tax
        if ( is_singular( 'anime' )
            || is_post_type_archive( 'anime' )
            || is_tax( 'genre' )
            || is_tax( 'anime_season_tax' )
            || is_tax( 'anime_format_tax' )
            || is_tax( 'anime_series_tax' )
            || $is_anime_search
        ) {
            wp_enqueue_style(
                'anime-sync-single',
                ANIME_SYNC_PRO_URL . 'public/assets/css/anime-single.css',
                [ 'anime-sync-public' ],
                ANIME_SYNC_PRO_VERSION
            );
        }

        wp_enqueue_script(
            'anime-sync-frontend',
            ANIME_SYNC_PRO_URL . 'public/assets/js/frontend.js',
            [ 'jquery' ],
            ANIME_SYNC_PRO_VERSION,
            true
        );
        wp_localize_script( 'anime-sync-frontend', 'animeSyncData', [
            'restUrl' => esc_url_raw( rest_url( 'anime-sync/v1/' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    // =========================================================
    // 模板覆蓋
    // =========================================================
    public function load_single_template( string $template ): string {

        if ( is_singular( 'anime' ) ) {
            $theme = locate_template( 'single-anime.php' );
            if ( $theme ) return $theme;
            $plugin = ANIME_SYNC_PRO_DIR . 'public/templates/single-anime.php';
            if ( file_exists( $plugin ) ) return $plugin;
        }

        // ACG v3：系列頁路由 → archive-series.php
        if ( is_tax( 'anime_series_tax' ) ) {
            $theme = locate_template( 'archive-series.php' );
            if ( $theme ) return $theme;
            $plugin = ANIME_SYNC_PRO_DIR . 'public/templates/archive-series.php';
            if ( file_exists( $plugin ) ) return $plugin;
        }

        // ACG：加入搜尋頁條件，anime 搜尋結果頁套用 archive 模板
        $is_anime_search = is_search() && get_query_var( 'post_type' ) === 'anime';

        if (
            is_post_type_archive( 'anime' )
            || is_tax( 'genre' )
            || is_tax( 'anime_season_tax' )
            || is_tax( 'anime_format_tax' )
            || $is_anime_search
        ) {
            $theme = locate_template( 'archive-anime.php' );
            if ( $theme ) return $theme;
            $plugin = ANIME_SYNC_PRO_DIR . 'public/templates/archive-anime.php';
            if ( file_exists( $plugin ) ) return $plugin;
        }

        return $template;
    }

    // =========================================================
    // ACG v3：系列頁排序
    // Bug #6 修正：使用 $query->is_tax() 而非全域 is_tax()
    // 排序：anime_season_year ASC → anime_start_date ASC
    // posts_per_page = -1 載入全部（注意超大系列效能）
    // =========================================================
    public function sort_series_archive( \WP_Query $query ): void {

        // 只處理前台主查詢的系列分類法頁面
        if ( is_admin() ) return;
        if ( ! $query->is_main_query() ) return;
        if ( ! $query->is_tax( 'anime_series_tax' ) ) return;

        $query->set( 'posts_per_page', -1 );
        $query->set( 'meta_key', 'anime_season_year' );
        $query->set( 'orderby', 'meta_value_num' );
        $query->set( 'order', 'ASC' );
    }

    // =========================================================
    // ACG：搜尋 meta 欄位擴展
    // 讓 anime 搜尋同時查 anime_title_romaji 和 anime_title_english
    // 只在 post_type=anime 搜尋時觸發，完全不影響其他 post_type 搜尋
    // =========================================================
    public function filter_anime_search( string $search, \WP_Query $query ): string {

        // 只處理前台主查詢的 anime 搜尋
        if ( ! $query->is_search() )                  return $search;
        if ( $query->get( 'post_type' ) !== 'anime' ) return $search;
        if ( empty( $search ) )                       return $search;

        $term = $query->get( 's' );
        if ( empty( $term ) ) return $search;

        global $wpdb;
        $like = '%' . $wpdb->esc_like( $term ) . '%';

        // 子查詢：找出 romaji 或 english 符合的 post_id
        $meta_search = $wpdb->prepare(
            " OR ( {$wpdb->posts}.ID IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE (
                    ( meta_key = 'anime_title_romaji'  AND meta_value LIKE %s )
                    OR
                    ( meta_key = 'anime_title_english' AND meta_value LIKE %s )
                )
            ) )",
            $like,
            $like
        );

        // 插入到 WordPress 原本 WHERE 條件的最後一個 ) 之前
        $search = preg_replace( '/\)$/', $meta_search . ')', $search );

        return $search;
    }

    // =========================================================
    // SEO Meta（有 RankMath / Yoast 時跳過）
    // =========================================================
    public function output_seo_meta(): void {
        if ( ! is_singular( 'anime' ) ) return;
        if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || class_exists( 'All_in_One_SEO_Pack' ) ) return;

        global $post;
        $pid   = $post->ID;
        $title = get_post_meta( $pid, 'anime_title_chinese', true ) ?: get_the_title( $pid );
        $desc  = mb_substr( wp_strip_all_tags(
            get_post_meta( $pid, 'anime_synopsis_chinese', true )
            ?: get_post_meta( $pid, 'anime_synopsis', true )
            ?: ''
        ), 0, 160 );
        $cover = get_post_meta( $pid, 'anime_cover_image', true );
        $url   = get_permalink( $pid );

        echo '<meta property="og:type"        content="video.tv_show">'                              . "\n";
        echo '<meta property="og:title"       content="' . esc_attr( $title ) . '">'                 . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $desc )  . '">'                 . "\n";
        echo '<meta property="og:url"         content="' . esc_url( $url )    . '">'                 . "\n";
        echo '<meta property="og:site_name"   content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
        if ( $cover ) echo '<meta property="og:image" content="' . esc_url( $cover ) . '">'          . "\n";
        echo '<meta name="twitter:card"        content="summary_large_image">'                       . "\n";
        echo '<meta name="twitter:title"       content="' . esc_attr( $title ) . '">'                . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $desc )  . '">'                . "\n";
        if ( $cover ) echo '<meta name="twitter:image" content="' . esc_url( $cover ) . '">'         . "\n";
        echo '<link rel="canonical" href="'    . esc_url( $url ) . '">'                              . "\n";
    }

    // =========================================================
    // 標題過濾
    // =========================================================
    public function filter_title( string $title, int $post_id = 0 ): string {
        if ( ! $post_id || get_post_type( $post_id ) !== 'anime' ) return $title;
        return get_post_meta( $post_id, 'anime_title_chinese', true ) ?: $title;
    }

    // =========================================================
    // Body Classes
    // =========================================================
    public function add_body_classes( array $classes ): array {
        if ( is_singular( 'anime' ) ) {
            global $post;
            $format    = get_post_meta( $post->ID, 'anime_format', true );
            $status    = get_post_meta( $post->ID, 'anime_status', true );
            $classes[] = 'anime-single';
            if ( $format ) $classes[] = 'anime-format-' . sanitize_html_class( strtolower( $format ) );
            if ( $status ) $classes[] = 'anime-status-' . sanitize_html_class( strtolower( $status ) );
        }
        if ( is_post_type_archive( 'anime' ) ) $classes[] = 'anime-archive';
        return $classes;
    }

    // =========================================================
    // Shortcodes
    // =========================================================
    public function shortcode_score( array $atts ): string {
        $atts    = shortcode_atts( [ 'post_id' => get_the_ID() ], $atts );
        $pid     = (int) $atts['post_id'];
        if ( ! $pid ) return '';
        $anilist = get_post_meta( $pid, 'anime_score_anilist', true );
        $bangumi = get_post_meta( $pid, 'anime_score_bangumi', true );
        $mal     = get_post_meta( $pid, 'anime_score_mal',     true );
        ob_start(); ?>
        <div class="anime-scores">
            <?php if ( $anilist ) : ?>
                <span class="score score-anilist">
                    <span class="score-label">AniList</span>
                    <span class="score-value"><?php echo esc_html( number_format( (float) $anilist, 1 ) ); ?></span>
                </span>
            <?php endif; ?>
            <?php if ( $bangumi ) : ?>
                <span class="score score-bangumi">
                    <span class="score-label">Bangumi</span>
                    <span class="score-value"><?php echo esc_html( number_format( (float) $bangumi, 1 ) ); ?></span>
                </span>
            <?php endif; ?>
            <?php if ( $mal ) : ?>
                <span class="score score-mal">
                    <span class="score-label">MAL</span>
                    <span class="score-value"><?php echo esc_html( number_format( (float) $mal, 1 ) ); ?></span>
                </span>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    public function shortcode_streaming( array $atts ): string {
        $atts      = shortcode_atts( [ 'post_id' => get_the_ID() ], $atts );
        $pid       = (int) $atts['post_id'];
        $raw       = get_post_meta( $pid, 'anime_streaming', true );
        if ( ! $raw ) return '';
        $platforms = is_array( $raw ) ? $raw : json_decode( $raw, true );
        if ( empty( $platforms ) ) return '';
        ob_start(); ?>
        <div class="anime-streaming">
            <h4><?php esc_html_e( '串流平台', 'anime-sync-pro' ); ?></h4>
            <ul class="streaming-list">
                <?php foreach ( $platforms as $item ) :
                    $name = $item['platform'] ?? $item['site'] ?? '';
                    $url  = $item['url'] ?? '';
                    if ( ! $name ) continue;
                ?>
                <li>
                    <?php if ( $url ) : ?>
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html( $name ); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html( $name ); ?>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php return ob_get_clean();
    }

    public function shortcode_themes( array $atts ): string {
        $atts   = shortcode_atts( [ 'post_id' => get_the_ID() ], $atts );
        $pid    = (int) $atts['post_id'];
        $raw    = get_post_meta( $pid, 'anime_themes', true );
        if ( ! $raw ) return '';
        $themes = is_array( $raw ) ? $raw : json_decode( $raw, true );
        if ( empty( $themes ) ) return '';
        $ops = array_filter( $themes, fn( $t ) => strtoupper( $t['type'] ?? '' ) === 'OP' );
        $eds = array_filter( $themes, fn( $t ) => strtoupper( $t['type'] ?? '' ) === 'ED' );
        ob_start(); ?>
        <div class="anime-themes">
            <?php if ( $ops ) : ?>
                <div class="themes-op">
                    <h4><?php esc_html_e( '片頭曲 (OP)', 'anime-sync-pro' ); ?></h4>
                    <?php foreach ( $ops as $t ) $this->render_theme_item( $t ); ?>
                </div>
            <?php endif; ?>
            <?php if ( $eds ) : ?>
                <div class="themes-ed">
                    <h4><?php esc_html_e( '片尾曲 (ED)', 'anime-sync-pro' ); ?></h4>
                    <?php foreach ( $eds as $t ) $this->render_theme_item( $t ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    private function render_theme_item( array $theme ): void {
        $title      = $theme['song_title'] ?? $theme['title'] ?? __( '未知曲目', 'anime-sync-pro' );
        $artists    = $theme['artists'] ?? $theme['by'] ?? [];
        $artist_str = is_array( $artists )
            ? implode( '、', array_filter(
                array_map( fn( $a ) => is_array( $a ) ? ( $a['name'] ?? '' ) : (string) $a, $artists )
            ) )
            : (string) $artists;
        $video    = $theme['video_url'] ?? $theme['video'] ?? '';
        $sequence = $theme['sequence'] ?? '';
        $type     = strtoupper( $theme['type'] ?? '' );
        ?>
        <div class="theme-item">
            <div class="theme-info">
                <?php if ( $sequence ) : ?>
                    <span class="theme-seq"><?php echo esc_html( $type . $sequence ); ?></span>
                <?php endif; ?>
                <span class="theme-title"><?php echo esc_html( $title ); ?></span>
                <?php if ( $artist_str ) : ?>
                    <span class="theme-artist"><?php echo esc_html( $artist_str ); ?></span>
                <?php endif; ?>
            </div>
            <?php if ( $video ) : ?>
                <a href="<?php echo esc_url( $video ); ?>" target="_blank" rel="noopener" class="theme-video-link">
                    ▶ <?php esc_html_e( '觀看', 'anime-sync-pro' ); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================
    // REST API
    // =========================================================
    public function register_rest_routes(): void {
        register_rest_route( 'anime-sync/v1', '/anime/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_get_anime' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => fn( $v ) => is_numeric( $v ),
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );
        register_rest_route( 'anime-sync/v1', '/season', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'rest_get_season' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function rest_get_anime( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post = get_post( $request->get_param( 'id' ) );
        if ( ! $post || $post->post_type !== 'anime' || $post->post_status !== 'publish' ) {
            return new WP_Error( 'not_found', '找不到該動畫', [ 'status' => 404 ] );
        }
        return new WP_REST_Response( $this->build_rest_response( $post ), 200 );
    }

    public function rest_get_season( WP_REST_Request $request ): WP_REST_Response {
        $year   = $request->get_param( 'year' );
        $season = strtoupper( $request->get_param( 'season' ) ?? '' );
        $args   = [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'meta_query'     => [],
        ];
        if ( $year )   $args['meta_query'][] = [ 'key' => 'anime_season_year', 'value' => $year,   'type' => 'NUMERIC' ];
        if ( $season ) $args['meta_query'][] = [ 'key' => 'anime_season',      'value' => $season               ];
        if ( count( $args['meta_query'] ) > 1 ) $args['meta_query']['relation'] = 'AND';
        $q     = new WP_Query( $args );
        $items = array_map( [ $this, 'build_rest_response' ], $q->posts );
        return new WP_REST_Response( [ 'total' => $q->found_posts, 'items' => $items ], 200 );
    }

    private function build_rest_response( WP_Post $post ): array {
        $id   = $post->ID;
        $meta = [];
        foreach ( [
            'anime_anilist_id', 'anime_mal_id', 'anime_bangumi_id',
            'anime_title_chinese', 'anime_title_native', 'anime_title_romaji',
            'anime_format', 'anime_status', 'anime_episodes', 'anime_duration',
            'anime_start_date', 'anime_end_date', 'anime_season', 'anime_season_year',
            'anime_score_anilist', 'anime_score_bangumi', 'anime_score_mal',
            'anime_synopsis_chinese', 'anime_cover_image', 'anime_banner_image',
            'anime_trailer_url', 'anime_staff_json', 'anime_cast_json',
            'anime_relations_json', 'anime_last_sync',
        ] as $key ) {
            $v = get_post_meta( $id, $key, true );
            if ( $v !== '' && $v !== false ) {
                if ( is_string( $v ) && str_starts_with( trim( $v ), '[' ) ) {
                    $d = json_decode( $v, true );
                    $v = ( json_last_error() === JSON_ERROR_NONE ) ? $d : $v;
                }
                $meta[ $key ] = $v;
            }
        }

        // ✅ Bug E 修正：使用正確 taxonomy slug 'genre'
        $genres = get_the_terms( $id, 'genre' );

        return [
            'id'     => $id,
            'slug'   => $post->post_name,
            'url'    => get_permalink( $id ),
            'title'  => $meta['anime_title_chinese'] ?? get_the_title( $id ),
            'meta'   => $meta,
            'genres' => ( $genres && ! is_wp_error( $genres ) ) ? wp_list_pluck( $genres, 'name' ) : [],
        ];
    }
}

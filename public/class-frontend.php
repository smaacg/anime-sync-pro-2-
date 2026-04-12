<?php
/**
 * Frontend Handler
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts',      [ $this, 'enqueue_assets' ] );
        add_filter( 'template_include',        [ $this, 'load_single_template' ] );
        add_action( 'wp_head',                 [ $this, 'output_seo_meta' ] );
        add_filter( 'the_title',               [ $this, 'filter_title' ], 10, 2 );
        add_action( 'wp_head',                 [ $this, 'output_json_ld' ] );
        add_filter( 'body_class',              [ $this, 'add_body_classes' ] );
        add_shortcode( 'anime_score',          [ $this, 'shortcode_score' ] );
        add_shortcode( 'anime_streaming',      [ $this, 'shortcode_streaming' ] );
        add_shortcode( 'anime_themes',         [ $this, 'shortcode_themes' ] );
        add_action( 'rest_api_init',           [ $this, 'register_rest_routes' ] );
    }

    // =========================================================
    // 資源載入
    // =========================================================

    public function enqueue_assets(): void {
        if ( ! is_singular( 'anime' ) && ! is_post_type_archive( 'anime' ) ) {
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
            $theme_template = locate_template( 'single-anime.php' );
            if ( $theme_template ) {
                return $theme_template;
            }
            $plugin_template = ANIME_SYNC_PRO_DIR . 'public/templates/single-anime.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }

        if ( is_post_type_archive( 'anime' ) ) {
            $theme_template = locate_template( 'archive-anime.php' );
            if ( $theme_template ) {
                return $theme_template;
            }
            $plugin_template = ANIME_SYNC_PRO_DIR . 'public/templates/archive-anime.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }

        return $template;
    }

    // =========================================================
    // SEO Meta Tags
    // =========================================================

    public function output_seo_meta(): void {
        if ( ! is_singular( 'anime' ) ) {
            return;
        }

        // 若已啟用主流 SEO 外掛則跳過
        if (
            defined( 'WPSEO_VERSION' ) ||          // Yoast SEO
            defined( 'RANK_MATH_VERSION' ) ||       // Rank Math
            class_exists( 'All_in_One_SEO_Pack' )   // AIOSEO
        ) {
            return;
        }

        global $post;
        $post_id      = $post->ID;
        $title        = get_post_meta( $post_id, 'anime_title_chinese', true )
                        ?: get_the_title( $post_id );
        $description  = wp_strip_all_tags( get_post_meta( $post_id, 'anime_synopsis_chinese', true )
                        ?: get_post_meta( $post_id, 'anime_synopsis', true )
                        ?: '' );
        $description  = mb_substr( $description, 0, 160 );
        $cover        = get_post_meta( $post_id, 'anime_cover_image', true );
        $canonical    = get_permalink( $post_id );

        echo '<meta property="og:type"        content="video.tv_show">' . "\n";
        echo '<meta property="og:title"       content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
        echo '<meta property="og:url"         content="' . esc_url( $canonical ) . '">' . "\n";
        echo '<meta property="og:site_name"   content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";

        if ( $cover ) {
            echo '<meta property="og:image" content="' . esc_url( $cover ) . '">' . "\n";
        }

        echo '<meta name="twitter:card"        content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title"       content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";

        if ( $cover ) {
            echo '<meta name="twitter:image" content="' . esc_url( $cover ) . '">' . "\n";
        }

        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
    }

    // =========================================================
    // 標題過濾
    // =========================================================

    public function filter_title( string $title, int $post_id = 0 ): string {
        if ( ! $post_id || get_post_type( $post_id ) !== 'anime' ) {
            return $title;
        }

        $chinese_title = get_post_meta( $post_id, 'anime_title_chinese', true );
        return $chinese_title ?: $title;
    }

    // =========================================================
    // JSON-LD 結構化資料
    // =========================================================

    public function output_json_ld(): void {
        if ( ! is_singular( 'anime' ) ) {
            return;
        }

        global $post;
        $post_id = $post->ID;

        $title       = get_post_meta( $post_id, 'anime_title_chinese', true ) ?: get_the_title( $post_id );
        $native      = get_post_meta( $post_id, 'anime_title_native', true );
        $description = wp_strip_all_tags(
            get_post_meta( $post_id, 'anime_synopsis_chinese', true )
            ?: get_post_meta( $post_id, 'anime_synopsis', true )
            ?: ''
        );
        $cover       = get_post_meta( $post_id, 'anime_cover_image', true );
        $format      = get_post_meta( $post_id, 'anime_format', true );
        $start_date  = get_post_meta( $post_id, 'anime_start_date', true );
        $end_date    = get_post_meta( $post_id, 'anime_end_date', true );
        $score       = get_post_meta( $post_id, 'anime_score_anilist', true );
        $studios_raw = get_post_meta( $post_id, 'anime_studios', true );

        $schema_type = ( $format === 'MOVIE' ) ? 'Movie' : 'TVSeries';

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => $schema_type,
            'name'        => $title,
            'url'         => get_permalink( $post_id ),
            'description' => $description,
        ];

        if ( $native ) {
            $schema['alternateName'] = $native;
        }

        if ( $cover ) {
            $schema['image'] = $cover;
        }

        if ( $start_date ) {
            $schema['startDate'] = $start_date;
        }

        if ( $end_date ) {
            $schema['endDate'] = $end_date;
        }

        if ( $score ) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => round( (float) $score / 10, 1 ),
                'bestRating'  => '10',
                'worstRating' => '0',
                'ratingCount' => '1',
            ];
        }

        if ( $studios_raw ) {
            $studios = is_array( $studios_raw ) ? $studios_raw : json_decode( $studios_raw, true );
            if ( is_array( $studios ) ) {
                $schema['productionCompany'] = array_map( function( $s ) {
                    return [
                        '@type' => 'Organization',
                        'name'  => is_array( $s ) ? ( $s['name'] ?? '' ) : $s,
                    ];
                }, $studios );
            }
        }

        // BreadcrumbList
        $breadcrumb = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $this->build_breadcrumb_schema( $post_id ),
        ];

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        echo "\n" . '</script>' . "\n";

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        echo "\n" . '</script>' . "\n";
    }

    /**
     * 建立麵包屑結構
     */
    private function build_breadcrumb_schema( int $post_id ): array {
        $items = [];
        $pos   = 1;

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => get_bloginfo( 'name' ),
            'item'     => home_url( '/' ),
        ];

        $archive_url = get_post_type_archive_link( 'anime' );
        if ( $archive_url ) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => __( '動畫列表', 'anime-sync-pro' ),
                'item'     => $archive_url,
            ];
        }

        $genres = get_the_terms( $post_id, 'anime_genre' );
        if ( $genres && ! is_wp_error( $genres ) ) {
            $genre = reset( $genres );
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => $genre->name,
                'item'     => get_term_link( $genre ),
            ];
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos,
            'name'     => get_post_meta( $post_id, 'anime_title_chinese', true ) ?: get_the_title( $post_id ),
            'item'     => get_permalink( $post_id ),
        ];

        return $items;
    }

    // =========================================================
    // Body Classes
    // =========================================================

    public function add_body_classes( array $classes ): array {
        if ( is_singular( 'anime' ) ) {
            global $post;
            $post_id = $post->ID;
            $format  = get_post_meta( $post_id, 'anime_format', true );
            $status  = get_post_meta( $post_id, 'anime_status', true );

            $classes[] = 'anime-single';

            if ( $format ) {
                $classes[] = 'anime-format-' . sanitize_html_class( strtolower( $format ) );
            }
            if ( $status ) {
                $classes[] = 'anime-status-' . sanitize_html_class( strtolower( $status ) );
            }
        }

        if ( is_post_type_archive( 'anime' ) ) {
            $classes[] = 'anime-archive';
        }

        return $classes;
    }

    // =========================================================
    // Shortcodes
    // =========================================================

    /**
     * [anime_score post_id="123"]
     */
    public function shortcode_score( array $atts ): string {
        $atts    = shortcode_atts( [ 'post_id' => get_the_ID() ], $atts );
        $post_id = (int) $atts['post_id'];

        if ( ! $post_id ) {
            return '';
        }

        $anilist = get_post_meta( $post_id, 'anime_score_anilist', true );
        $bangumi = get_post_meta( $post_id, 'anime_score_bangumi', true );
        $mal     = get_post_meta( $post_id, 'anime_score_mal', true );

        ob_start();
        ?>
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
        <?php
        return ob_get_clean();
    }

    /**
     * [anime_streaming post_id="123"]
     * ✅ 修正：site → platform
     */
    public function shortcode_streaming( array $atts ): string {
        $atts        = shortcode_atts( [ 'post_id' => get_the_ID() ], $atts );
        $post_id     = (int) $atts['post_id'];
        $raw         = get_post_meta( $post_id, 'anime_streaming', true );

        if ( ! $raw ) {
            return '';
        }

        $platforms = is_array( $raw ) ? $raw : json_decode( $raw, true );

        if ( empty( $platforms ) || ! is_array( $platforms ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="anime-streaming">
            <h4 class="streaming-title"><?php esc_html_e( '串流平台', 'anime-sync-pro' ); ?></h4>
            <ul class="streaming-list">
                <?php foreach ( $platforms as $item ) :
                    // ✅ 修正：使用 platform 鍵名（與 merge_api_data() 一致）
                    $platform_name = $item['platform'] ?? $item['site'] ?? '';
                    $url           = $item['url'] ?? '';
                    if ( ! $platform_name ) continue;
                ?>
                    <li class="streaming-item">
                        <?php if ( $url ) : ?>
                            <a href="<?php echo esc_url( $url ); ?>"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="streaming-link">
                                <?php echo esc_html( $platform_name ); ?>
                            </a>
                        <?php else : ?>
                            <span class="streaming-name"><?php echo esc_html( $platform_name ); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [anime_themes post_id="123"]
     * ✅ 修正：title → song_title、by → artists（陣列）、video → video_url
     */
    public function shortcode_themes( array $atts ): string {
        $atts    = shortcode_atts( [ 'post_id' => get_the_ID() ], $atts );
        $post_id = (int) $atts['post_id'];
        $raw     = get_post_meta( $post_id, 'anime_themes', true );

        if ( ! $raw ) {
            return '';
        }

        $themes = is_array( $raw ) ? $raw : json_decode( $raw, true );

        if ( empty( $themes ) || ! is_array( $themes ) ) {
            return '';
        }

        $ops = array_filter( $themes, fn( $t ) => strtoupper( $t['type'] ?? '' ) === 'OP' );
        $eds = array_filter( $themes, fn( $t ) => strtoupper( $t['type'] ?? '' ) === 'ED' );

        ob_start();
        ?>
        <div class="anime-themes">
            <?php if ( ! empty( $ops ) ) : ?>
                <div class="themes-section themes-op">
                    <h4 class="themes-heading"><?php esc_html_e( '片頭曲 (OP)', 'anime-sync-pro' ); ?></h4>
                    <?php foreach ( $ops as $theme ) : ?>
                        <?php $this->render_theme_item( $theme ); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $eds ) ) : ?>
                <div class="themes-section themes-ed">
                    <h4 class="themes-heading"><?php esc_html_e( '片尾曲 (ED)', 'anime-sync-pro' ); ?></h4>
                    <?php foreach ( $eds as $theme ) : ?>
                        <?php $this->render_theme_item( $theme ); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * 渲染單首主題曲
     * ✅ 修正鍵名：song_title、artists、video_url
     */
    private function render_theme_item( array $theme ): void {
        // ✅ 修正：song_title（而非 title）
        $song_title = $theme['song_title'] ?? $theme['title'] ?? __( '未知曲目', 'anime-sync-pro' );

        // ✅ 修正：artists 為陣列（而非 by 字串）
        $artists_raw = $theme['artists'] ?? $theme['by'] ?? [];
        if ( is_array( $artists_raw ) ) {
            $artists_str = implode( '、', array_filter( array_map(
                fn( $a ) => is_array( $a ) ? ( $a['name'] ?? '' ) : (string) $a,
                $artists_raw
            ) ) );
        } else {
            $artists_str = (string) $artists_raw;
        }

        // ✅ 修正：video_url（而非 video）
        $video_url = $theme['video_url'] ?? $theme['video'] ?? '';
        $sequence  = $theme['sequence'] ?? '';
        $type_label = strtoupper( $theme['type'] ?? '' );
        ?>
        <div class="theme-item">
            <div class="theme-info">
                <?php if ( $sequence ) : ?>
                    <span class="theme-sequence"><?php echo esc_html( $type_label . $sequence ); ?></span>
                <?php endif; ?>
                <span class="theme-title"><?php echo esc_html( $song_title ); ?></span>
                <?php if ( $artists_str ) : ?>
                    <span class="theme-artist"><?php echo esc_html( $artists_str ); ?></span>
                <?php endif; ?>
            </div>
            <?php if ( $video_url ) : ?>
                <a href="<?php echo esc_url( $video_url ); ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="theme-video-link">
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
        register_rest_route(
            'anime-sync/v1',
            '/anime/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'rest_get_anime' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => [
                        'validate_callback' => fn( $v ) => is_numeric( $v ),
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            'anime-sync/v1',
            '/season',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'rest_get_season' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'year'   => [
                        'validate_callback' => fn( $v ) => is_numeric( $v ) && $v >= 1900 && $v <= 2100,
                        'sanitize_callback' => 'absint',
                    ],
                    'season' => [
                        'validate_callback' => fn( $v ) => in_array( strtoupper( $v ), [ 'WINTER', 'SPRING', 'SUMMER', 'FALL' ], true ),
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );
    }

    /**
     * REST：取得單部動畫
     */
    public function rest_get_anime( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id = $request->get_param( 'id' );
        $post    = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'anime' || $post->post_status !== 'publish' ) {
            return new WP_Error( 'not_found', __( '找不到該動畫', 'anime-sync-pro' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $this->build_rest_response( $post ), 200 );
    }

    /**
     * REST：取得季度列表
     */
    public function rest_get_season( WP_REST_Request $request ): WP_REST_Response {
        $year   = $request->get_param( 'year' );
        $season = strtoupper( $request->get_param( 'season' ) ?? '' );

        $args = [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'meta_query'     => [],
        ];

        if ( $year ) {
            $args['meta_query'][] = [
                'key'   => 'anime_season_year',
                'value' => $year,
                'type'  => 'NUMERIC',
            ];
        }

        if ( $season ) {
            $args['meta_query'][] = [
                'key'   => 'anime_season',
                'value' => $season,
            ];
        }

        if ( count( $args['meta_query'] ) > 1 ) {
            $args['meta_query']['relation'] = 'AND';
        }

        $query = new WP_Query( $args );
        $items = [];

        foreach ( $query->posts as $post ) {
            $items[] = $this->build_rest_response( $post );
        }

        return new WP_REST_Response( [
            'total' => $query->found_posts,
            'items' => $items,
        ], 200 );
    }

    /**
     * 組裝 REST 回應資料
     */
    private function build_rest_response( WP_Post $post ): array {
        $id  = $post->ID;
        $raw = [];

        $meta_keys = [
            'anime_anilist_id', 'anime_mal_id', 'anime_bangumi_id',
            'anime_title_chinese', 'anime_title_native', 'anime_title_romaji',
            'anime_format', 'anime_status', 'anime_episodes', 'anime_duration',
            'anime_start_date', 'anime_end_date', 'anime_season', 'anime_season_year',
            'anime_score_anilist', 'anime_score_bangumi', 'anime_score_mal',
            'anime_synopsis', 'anime_synopsis_chinese',
            'anime_cover_image', 'anime_cover_large',
            'anime_trailer_url', 'anime_trailer_thumbnail',
            'anime_studios', 'anime_producers',
            'anime_characters', 'anime_staff',
            'anime_themes', 'anime_streaming', 'anime_external_links',
            'anime_next_airing_episode', 'anime_next_airing_time',
            'anime_last_sync',
        ];

        foreach ( $meta_keys as $key ) {
            $value = get_post_meta( $id, $key, true );
            if ( $value !== '' && $value !== false ) {
                // 嘗試解析 JSON 陣列欄位
                if ( is_string( $value ) && str_starts_with( trim( $value ), '[' ) ) {
                    $decoded = json_decode( $value, true );
                    $value   = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : $value;
                }
                $raw[ $key ] = $value;
            }
        }

        $genres = get_the_terms( $id, 'anime_genre' );
        $tags   = get_the_terms( $id, 'anime_tag' );

        return [
            'id'          => $id,
            'slug'        => $post->post_slug ?? $post->post_name,
            'url'         => get_permalink( $id ),
            'title'       => $raw['anime_title_chinese'] ?? get_the_title( $id ),
            'meta'        => $raw,
            'genres'      => ( $genres && ! is_wp_error( $genres ) )
                             ? wp_list_pluck( $genres, 'name' )
                             : [],
            'tags'        => ( $tags && ! is_wp_error( $tags ) )
                             ? wp_list_pluck( $tags, 'name' )
                             : [],
        ];
    }
}

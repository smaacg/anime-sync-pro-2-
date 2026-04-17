<?php
/**
 * Single Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/single-anime.php
 *
 * @package Anime_Sync_Pro
 * @version 14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_enqueue_style(
    'anime-sync-single',
    plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/anime-single.css',
    array(),
    '14.0'
);

get_header();

while ( have_posts() ) :
    the_post();

    $post_id = get_the_ID();

    /* ── Helpers ── */
    $get_meta = function ( $key, $default = '' ) use ( $post_id ) {
        $value = get_post_meta( $post_id, $key, true );
        return ( $value === '' || $value === null ) ? $default : $value;
    };

    $decode_json = function ( $raw ) {
        if ( is_array( $raw ) ) return $raw;
        if ( ! is_string( $raw ) || $raw === '' ) return array();
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : array();
    };

    $format_date = function ( $raw ) {
        if ( empty( $raw ) ) return '';
        $raw = trim( (string) $raw );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) return $raw;
        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $raw, $m ) ) return $m[1].'-'.$m[2].'-'.$m[3];
        $ts = strtotime( $raw );
        return ( $ts !== false ) ? gmdate( 'Y-m-d', $ts ) : $raw;
    };

    $starts_with = function ( $haystack, $needle ) {
        return $needle !== '' && strpos( $haystack, $needle ) === 0;
    };

    $substr_safe = function ( $text, $start, $length = null ) {
        $text = (string) $text;
        if ( function_exists( 'mb_substr' ) ) {
            return ( $length === null ) ? mb_substr( $text, $start ) : mb_substr( $text, $start, $length );
        }
        return ( $length === null ) ? substr( $text, $start ) : substr( $text, $start, $length );
    };

    $fallback_text = function ( $text, $length = 2 ) use ( $substr_safe ) {
        $text = trim( wp_strip_all_tags( (string) $text ) );
        if ( $text === '' ) return 'AN';
        return $substr_safe( $text, 0, $length );
    };

    $normalize_news_item = function ( $item ) {
        if ( ! is_array( $item ) ) return null;
        $title = '';
        $url   = '';
        if ( ! empty( $item['title'] ) )         $title = $item['title'];
        elseif ( ! empty( $item['name'] ) )      $title = $item['name'];
        elseif ( ! empty( $item['headline'] ) )  $title = $item['headline'];
        if ( ! empty( $item['url'] ) )           $url = $item['url'];
        elseif ( ! empty( $item['link'] ) )      $url = $item['link'];
        $title = trim( (string) $title );
        $url   = trim( (string) $url );
        if ( $title === '' ) return null;
        return array( 'title' => $title, 'url' => $url );
    };

    /* ── Meta ── */
    $anilist_id = (int) $get_meta( 'anime_anilist_id', 0 );
    $mal_id     = (int) $get_meta( 'anime_mal_id', 0 );
    $bangumi_id = (int) $get_meta( 'anime_bangumi_id', 0 );

    $title_chinese = $get_meta( 'anime_title_chinese' );
    $title_native  = $get_meta( 'anime_title_native' );
    $title_romaji  = $get_meta( 'anime_title_romaji' );
    $title_english = $get_meta( 'anime_title_english' );
    $display_title = $title_chinese ? $title_chinese : get_the_title();

    $format      = $get_meta( 'anime_format' );
    $status      = $get_meta( 'anime_status' );
    $season      = $get_meta( 'anime_season' );
    $season_year = (int) $get_meta( 'anime_season_year', 0 );
    $episodes    = (int) $get_meta( 'anime_episodes', 0 );
    $ep_aired    = (int) $get_meta( 'anime_episodes_aired', 0 );
    $duration    = (int) $get_meta( 'anime_duration', 0 );
    $source      = $get_meta( 'anime_source' );
    $studio      = $get_meta( 'anime_studios' );
    $popularity  = (int) $get_meta( 'anime_popularity', 0 );

    $tw_streaming_raw   = $get_meta( 'anime_tw_streaming' );
    $tw_streaming_other = $get_meta( 'anime_tw_streaming_other' );
    $tw_distributor     = $get_meta( 'anime_tw_distributor' );
    $tw_dist_custom     = $get_meta( 'anime_tw_distributor_custom' );
    $tw_broadcast       = $get_meta( 'anime_tw_broadcast' );

    $tw_stream_url_map = array(
        'bahamut'     => $get_meta( 'anime_tw_streaming_url_bahamut' ),
        'netflix'     => $get_meta( 'anime_tw_streaming_url_netflix' ),
        'disney'      => $get_meta( 'anime_tw_streaming_url_disney' ),
        'amazon'      => $get_meta( 'anime_tw_streaming_url_amazon' ),
        'kktv'        => $get_meta( 'anime_tw_streaming_url_kktv' ),
        'friday'      => $get_meta( 'anime_tw_streaming_url_friday' ),
        'catchplay'   => $get_meta( 'anime_tw_streaming_url_catchplay' ),
        'bilibili'    => $get_meta( 'anime_tw_streaming_url_bilibili' ),
        'crunchyroll' => $get_meta( 'anime_tw_streaming_url_crunchyroll' ),
        'hulu'        => $get_meta( 'anime_tw_streaming_url_hulu' ),
        'hidive'      => $get_meta( 'anime_tw_streaming_url_hidive' ),
        'ani-one'     => $get_meta( 'anime_tw_streaming_url_ani_one' ),
        'muse'        => $get_meta( 'anime_tw_streaming_url_muse' ),
        'viu'         => $get_meta( 'anime_tw_streaming_url_viu' ),
        'wetv'        => $get_meta( 'anime_tw_streaming_url_wetv' ),
        'youtube'     => $get_meta( 'anime_tw_streaming_url_youtube' ),
    );

    $tw_dist_labels = array(
        'muse'        => 'Muse',
        'medialink'   => 'Medialink',
        'jbf'         => 'JBF',
        'righttime'   => '右時數位',
        'gaga'        => 'GaGa OOLala',
        'catchplay'   => 'CatchPlay',
        'netflix'     => 'Netflix 台灣',
        'disney'      => 'Disney+ 台灣',
        'kktv'        => 'KKTV',
        'crunchyroll' => 'Crunchyroll',
        'ani-one'     => 'Ani-One Asia',
        'other'       => '',
    );

    $tw_dist_display = '';
    if ( $tw_distributor === 'other' ) {
        $tw_dist_display = $tw_dist_custom ? $tw_dist_custom : '';
    } elseif ( $tw_distributor ) {
        $tw_dist_display = isset( $tw_dist_labels[ $tw_distributor ] ) ? $tw_dist_labels[ $tw_distributor ] : $tw_distributor;
    }

    $tw_stream_labels = array(
        'bahamut'     => '巴哈姆特動畫瘋',
        'netflix'     => 'Netflix',
        'disney'      => 'Disney+',
        'amazon'      => 'Amazon Prime Video',
        'kktv'        => 'KKTV',
        'friday'      => 'friDay 影音',
        'catchplay'   => 'CatchPlay+',
        'bilibili'    => 'Bilibili 台灣',
        'crunchyroll' => 'Crunchyroll',
        'hulu'        => 'Hulu',
        'hidive'      => 'HIDIVE',
        'ani-one'     => 'Ani-One',
        'muse'        => 'Muse 木棉花',
        'viu'         => 'Viu',
        'wetv'        => 'WeTV',
        'youtube'     => 'YouTube',
    );

    $tw_streaming_items = array();
    if ( ! empty( $tw_streaming_raw ) ) {
        $raw_arr = is_array( $tw_streaming_raw ) ? $tw_streaming_raw : array( $tw_streaming_raw );
        foreach ( $raw_arr as $key ) {
            $key = trim( (string) $key );
            if ( $key === '' ) continue;
            $tw_streaming_items[] = array(
                'key'   => $key,
                'label' => isset( $tw_stream_labels[ $key ] ) ? $tw_stream_labels[ $key ] : $key,
                'url'   => isset( $tw_stream_url_map[ $key ] ) ? $tw_stream_url_map[ $key ] : '',
            );
        }
    }
    if ( $tw_streaming_other ) {
        foreach ( array_map( 'trim', explode( ',', $tw_streaming_other ) ) as $extra ) {
            if ( $extra !== '' ) {
                $tw_streaming_items[] = array( 'key' => 'other', 'label' => $extra, 'url' => '' );
            }
        }
    }

    $start_date = $format_date( $get_meta( 'anime_start_date' ) );
    $end_date   = $format_date( $get_meta( 'anime_end_date' ) );

    $score_anilist_raw = $get_meta( 'anime_score_anilist' );
    $score_anilist_num = is_numeric( $score_anilist_raw ) ? (float) $score_anilist_raw : 0;
    $score_anilist     = $score_anilist_num > 0 ? number_format( $score_anilist_num / 10, 1 ) : '';

    $score_mal_raw = $get_meta( 'anime_score_mal' );
    $score_mal_num = is_numeric( $score_mal_raw ) ? (float) $score_mal_raw : 0;
    $score_mal     = $score_mal_num > 0 ? number_format( $score_mal_num / 10, 1 ) : '';

    $score_bangumi_raw = $get_meta( 'anime_score_bangumi' );
    $score_bangumi_num = is_numeric( $score_bangumi_raw ) ? (float) $score_bangumi_raw : 0;
    $score_bangumi     = $score_bangumi_num > 0 ? number_format( $score_bangumi_num / 10, 1 ) : '';

    $cover_image  = $get_meta( 'anime_cover_image' );
    $banner_image = $get_meta( 'anime_banner_image' );
    $trailer_url  = $get_meta( 'anime_trailer_url' );

    $youtube_id = '';
    if ( $trailer_url ) {
        foreach ( preg_split( '/[,\n]+/', (string) $trailer_url ) as $t_url ) {
            $t_url = trim( $t_url );
            if ( $t_url === '' ) continue;
            if ( preg_match( '/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{11})/', $t_url, $m ) ) {
                $youtube_id = $m[1]; break;
            }
            if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $t_url ) ) {
                $youtube_id = $t_url; break;
            }
        }
    }

    $official_site = $get_meta( 'anime_official_site' );
    $twitter_url   = $get_meta( 'anime_twitter_url' );
    $wikipedia_url = $get_meta( 'anime_wikipedia_url' );
    $tiktok_url    = $get_meta( 'anime_tiktok_url' );
    $affiliate_html = $get_meta( 'anime_affiliate_html' );

    $next_airing_raw = $get_meta( 'anime_next_airing' );
    $airing_data = array();
    if ( $next_airing_raw ) {
        $decoded_airing = is_array( $next_airing_raw ) ? $next_airing_raw : json_decode( $next_airing_raw, true );
        if ( is_array( $decoded_airing ) ) $airing_data = $decoded_airing;
    }

    $synopsis_raw = $get_meta( 'anime_synopsis_chinese' );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = $get_meta( 'anime_synopsis' );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = get_the_content();
    $synopsis = trim( (string) $synopsis_raw );

    $streaming_list = $decode_json( $get_meta( 'anime_streaming' ) );
    $themes_list    = $decode_json( $get_meta( 'anime_themes' ) );
    $cast_list      = $decode_json( $get_meta( 'anime_cast_json' ) );
    $staff_list     = $decode_json( $get_meta( 'anime_staff_json' ) );
    $relations_list = $decode_json( $get_meta( 'anime_relations_json' ) );
    $episodes_list  = $decode_json( $get_meta( 'anime_episodes_json' ) );

    $news_items = $decode_json( $get_meta( 'anime_related_news_json' ) );
    if ( empty( $news_items ) ) $news_items = $decode_json( $get_meta( 'anime_news_json' ) );

    $normalized_news = array();
    foreach ( $news_items as $news_item ) {
        $normalized = $normalize_news_item( $news_item );
        if ( $normalized ) $normalized_news[] = $normalized;
    }
    $news_items = $normalized_news;

    /* ── Themes ── */
    $seen = array(); $openings = array(); $endings = array();
    foreach ( $themes_list as $t ) {
        $type   = strtoupper( trim( isset( $t['type'] ) ? $t['type'] : '' ) );
        $stitle = trim( isset( $t['song_title'] ) ? $t['song_title'] : ( isset( $t['title'] ) ? $t['title'] : '' ) );
        $key    = $type . '||' . $stitle;
        if ( isset( $seen[ $key ] ) ) continue;
        $seen[ $key ] = true;
        if ( $starts_with( $type, 'OP' ) )      $openings[] = $t;
        elseif ( $starts_with( $type, 'ED' ) )  $endings[]  = $t;
    }

    /* ── Labels ── */
    $season_labels = array( 'WINTER' => '冬季', 'SPRING' => '春季', 'SUMMER' => '夏季', 'FALL' => '秋季' );
    $format_labels = array( 'TV' => 'TV', 'TV_SHORT' => 'TV', 'MOVIE' => '劇場版', 'OVA' => 'OVA', 'ONA' => 'ONA', 'SPECIAL' => 'SPECIAL', 'MUSIC' => 'MV' );
    $status_labels = array( 'FINISHED' => '已完結', 'RELEASING' => '連載中', 'NOT_YET_RELEASED' => '未開播', 'CANCELLED' => '已取消', 'HIATUS' => '暫停' );
    $status_classes = array( 'FINISHED' => 's-fin', 'RELEASING' => 's-rel', 'NOT_YET_RELEASED' => 's-pre', 'CANCELLED' => 's-can', 'HIATUS' => 's-hia' );
    $source_labels = array( 'ORIGINAL' => '原創', 'MANGA' => '漫畫改編', 'LIGHT_NOVEL' => '輕小說改編', 'NOVEL' => '小說改編', 'VISUAL_NOVEL' => '視覺小說改編', 'VIDEO_GAME' => '遊戲改編', 'WEB_MANGA' => '網路漫畫改編', 'BOOK' => '書籍改編', 'MUSIC' => '音樂', 'GAME' => '遊戲改編', 'LIVE_ACTION' => '真人影視改編', 'MULTIMEDIA_PROJECT' => '多媒體企劃', 'OTHER' => '其他' );

    $season_label = isset( $season_labels[ $season ] ) ? $season_labels[ $season ] : $season;
    $format_label = isset( $format_labels[ $format ] ) ? $format_labels[ $format ] : $format;
    $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
    $status_class = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : '';
    $source_label = isset( $source_labels[ $source ] ) ? $source_labels[ $source ] : $source;

    $ep_str = '';
    if ( $episodes ) {
        $ep_str = ( $ep_aired && $ep_aired < $episodes )
            ? $ep_aired . ' / ' . $episodes . ' 集'
            : $episodes . ' 集';
    }

    $season_str = '';
    if ( $season_year && $season_label ) $season_str = $season_year . ' ' . $season_label;
    elseif ( $season_year )              $season_str = (string) $season_year;

    $genre_terms        = get_the_terms( $post_id, 'genre' );
    $season_terms       = get_the_terms( $post_id, 'anime_season_tax' );
    $genre_terms        = is_array( $genre_terms ) ? $genre_terms : array();
    $season_terms       = is_array( $season_terms ) ? $season_terms : array();
    $season_child_terms = array();
    foreach ( $season_terms as $term ) {
        if ( ! empty( $term->parent ) ) $season_child_terms[] = $term;
    }

    /* ── Relations ── */
    $site_relations = array();
    if ( ! empty( $relations_list ) ) {
        foreach ( $relations_list as $rel ) {
            $rel_anilist_id = isset( $rel['anilist_id'] ) ? (int) $rel['anilist_id'] : ( isset( $rel['id'] ) ? (int) $rel['id'] : 0 );
            if ( ! $rel_anilist_id ) continue;
            $qr = get_posts( array( 'post_type' => 'anime', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true, 'meta_query' => array( array( 'key' => 'anime_anilist_id', 'value' => $rel_anilist_id, 'type' => 'NUMERIC' ) ) ) );
            if ( ! empty( $qr ) ) {
                $site_rel_post   = $qr[0];
                $relation_labels = array( 'PREQUEL' => '前傳', 'SEQUEL' => '續集', 'PARENT' => '主線', 'SIDE_STORY' => '外傳', 'CHARACTER' => '角色客串', 'SUMMARY' => '總集篇', 'ALTERNATIVE' => '替代版本', 'SPIN_OFF' => '衍生作', 'OTHER' => '其他', 'SOURCE' => '原作', 'COMPILATION' => '合輯', 'CONTAINS' => '收錄', 'ANIME' => '動畫' );
                $raw_label       = isset( $rel['relation_label'] ) ? $rel['relation_label'] : ( isset( $rel['type'] ) ? $rel['type'] : '' );
                $site_relations[] = array(
                    'title_zh'       => get_post_meta( $site_rel_post->ID, 'anime_title_chinese', true ) ?: ( isset( $rel['title_zh'] ) ? $rel['title_zh'] : ( isset( $rel['title'] ) ? $rel['title'] : '' ) ),
                    'title_native'   => isset( $rel['title_native'] ) ? $rel['title_native'] : ( isset( $rel['native'] ) ? $rel['native'] : '' ),
                    'relation_label' => isset( $relation_labels[ $raw_label ] ) ? $relation_labels[ $raw_label ] : $raw_label,
                    'format'         => isset( $rel['format'] ) ? $rel['format'] : '',
                    'cover_image'    => get_post_meta( $site_rel_post->ID, 'anime_cover_image', true ) ?: ( isset( $rel['cover_image'] ) ? $rel['cover_image'] : '' ),
                    'url'            => get_permalink( $site_rel_post->ID ),
                );
            }
        }
    }

    /* ── Schema ── */
    $schema_type  = ( $format === 'MOVIE' ) ? 'Movie' : ( ( $format === 'MUSIC' ) ? 'MusicVideoObject' : 'TVSeries' );
    $schema_genres = array();
    foreach ( $genre_terms as $t ) $schema_genres[] = $t->name;
    $alternate_names    = array_values( array_filter( array( $title_native, $title_romaji, $title_english ) ) );
    $schema_description = $substr_safe( wp_strip_all_tags( $synopsis ), 0, 200 );

    $schema = array(
        '@context' => 'https://schema.org', '@type' => $schema_type,
        'name' => $display_title, 'description' => $schema_description,
        'image' => $cover_image ? $cover_image : get_the_post_thumbnail_url( $post_id, 'large' ),
        'genre' => $schema_genres, 'datePublished' => $start_date, 'url' => get_permalink( $post_id ),
    );
    if ( ! empty( $alternate_names ) ) $schema['alternateName'] = $alternate_names;
    if ( $episodes )                   $schema['numberOfEpisodes'] = $episodes;
    if ( $score_anilist_num > 0 ) {
        $schema['aggregateRating'] = array( '@type' => 'AggregateRating', 'ratingValue' => number_format( $score_anilist_num / 10, 1 ), 'bestRating' => '10', 'worstRating' => '1', 'ratingCount' => max( 1, $popularity ) );
    }
    if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) {
        $schema['potentialAction'] = array( '@type' => 'WatchAction', 'target' => get_permalink( $post_id ) . '#asd-sec-stream' );
    }

    $breadcrumb_schema = array(
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => array(
            array( '@type' => 'ListItem', 'position' => 1, 'name' => '首頁', 'item' => home_url( '/' ) ),
            array( '@type' => 'ListItem', 'position' => 2, 'name' => '動畫', 'item' => home_url( '/anime/' ) ),
            array( '@type' => 'ListItem', 'position' => 3, 'name' => $display_title, 'item' => get_permalink( $post_id ) ),
        ),
    );

    $faq_items  = array();
    $faq_schema = null;
    $faq_json_raw = $get_meta( 'anime_faq_json' );
    if ( $faq_json_raw ) {
        $faq_decoded = json_decode( $faq_json_raw, true );
        if ( is_array( $faq_decoded ) ) $faq_items = $faq_decoded;
    }
    if ( ! empty( $faq_items ) ) {
        $faq_schema_main = array();
        foreach ( $faq_items as $f ) {
            if ( empty( $f['q'] ) || empty( $f['a'] ) ) continue;
            $faq_schema_main[] = array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $f['a'] ) ) );
        }
        if ( ! empty( $faq_schema_main ) ) {
            $faq_schema = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq_schema_main );
        }
    }

    /* ── Cast ── */
    $cast_main = array();
    foreach ( $cast_list as $c ) {
        if ( isset( $c['role'] ) && strtoupper( $c['role'] ) === 'MAIN' ) $cast_main[] = $c;
    }
    if ( empty( $cast_main ) ) $cast_main = array_slice( $cast_list, 0, 8 );

    $poster_fallback     = $fallback_text( $display_title, 2 );
    $has_sidebar_content = ( ! empty( $news_items ) || ! empty( $site_relations ) || $affiliate_html || $official_site || $twitter_url || $wikipedia_url || $tiktok_url );
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<script type="application/ld+json"><?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php if ( $faq_schema ) : ?>
<script type="application/ld+json"><?php echo wp_json_encode( $faq_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php endif; ?>

<div class="asd-wrap">

    <?php /* ── Banner ── */ ?>
    <?php if ( $banner_image ) : ?>
        <div class="asd-banner" style="background-image:url(<?php echo esc_url( $banner_image ); ?>)">
            <div class="asd-banner-fade"></div>
        </div>
    <?php else : ?>
        <div class="asd-banner asd-banner--fallback"></div>
    <?php endif; ?>

    <?php /* ── Breadcrumb ── */ ?>
    <nav class="asd-breadcrumb" aria-label="麵包屑">
        <ol itemscope itemtype="https://schema.org/BreadcrumbList">
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" itemprop="item"><span itemprop="name">首頁</span></a>
                <meta itemprop="position" content="1">
            </li>
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="<?php echo esc_url( home_url( '/anime/' ) ); ?>" itemprop="item"><span itemprop="name">動畫</span></a>
                <meta itemprop="position" content="2">
            </li>
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <span itemprop="name"><?php echo esc_html( $display_title ); ?></span>
                <meta itemprop="position" content="3">
            </li>
        </ol>
    </nav>

    <?php /* ── Hero ── */ ?>
    <div class="asd-hero-new">

        <?php /* 背景圖 */ ?>
        <?php if ( $banner_image ) : ?>
            <div class="asd-hero-bg-img" style="background-image:url(<?php echo esc_url( $banner_image ); ?>)"></div>
        <?php elseif ( $cover_image ) : ?>
            <div class="asd-hero-bg-img" style="background-image:url(<?php echo esc_url( $cover_image ); ?>)"></div>
        <?php endif; ?>

        <?php /* 封面 */ ?>
        <div class="asd-hero-poster">
            <?php if ( $cover_image ) : ?>
                <img src="<?php echo esc_url( $cover_image ); ?>"
                     alt="<?php echo esc_attr( $display_title ); ?> 封面"
                     class="asd-poster-img" loading="eager"
                     onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="asd-poster-fallback" style="display:none"><span><?php echo esc_html( $poster_fallback ); ?></span></div>
            <?php elseif ( has_post_thumbnail() ) : ?>
                <?php echo get_the_post_thumbnail( $post_id, 'large', array( 'class' => 'asd-poster-img', 'loading' => 'eager', 'alt' => $display_title . ' 封面' ) ); ?>
            <?php else : ?>
                <div class="asd-poster-fallback"><span><?php echo esc_html( $poster_fallback ); ?></span></div>
            <?php endif; ?>
        </div>

        <?php /* 主內容 */ ?>
        <div class="asd-hero-body">

            <div class="asd-hero-kicker">
                <span>動畫</span>
                <?php if ( $season_str ) : ?><span class="asd-hbc-sep">·</span><span><?php echo esc_html( $season_str ); ?></span><?php endif; ?>
                <?php if ( ! empty( $genre_terms ) ) : ?><span class="asd-hbc-sep">·</span><span><?php echo esc_html( $genre_terms[0]->name ); ?></span><?php endif; ?>
            </div>

            <h1 class="asd-hero-title"><?php echo esc_html( $display_title ); ?></h1>

            <?php if ( $title_native ) : ?>
                <p class="asd-hero-native"><?php echo esc_html( $title_native ); ?></p>
            <?php endif; ?>
            <?php if ( $title_romaji && $title_romaji !== $title_native ) : ?>
                <p class="asd-hero-native asd-hero-romaji"><?php echo esc_html( $title_romaji ); ?></p>
            <?php endif; ?>

            <?php
            $series_tax_terms = get_the_terms( $post_id, 'anime_series_tax' );
            if ( ! empty( $series_tax_terms ) && ! is_wp_error( $series_tax_terms ) ) :
                $series_tax     = $series_tax_terms[0];
                $series_tax_url = get_term_link( $series_tax );
                if ( $series_tax->count >= 2 && ! is_wp_error( $series_tax_url ) ) :
            ?>
                <a href="<?php echo esc_url( $series_tax_url ); ?>" class="asd-series-entry-badge">
                    🔗 <?php echo esc_html( $series_tax->name ); ?> 系列
                </a>
            <?php endif; endif; ?>

            <div class="asd-hero-badges">
                <?php if ( $status_label ) echo '<span class="asd-hbadge' . ( $status_class ? ' asd-hbadge--' . esc_attr( $status_class ) : '' ) . '">' . esc_html( $status_label ) . '</span>'; ?>
                <?php if ( $format_label ) echo '<span class="asd-hbadge">' . esc_html( $format_label ) . '</span>'; ?>
                <?php if ( $season_str )   echo '<span class="asd-hbadge">' . esc_html( $season_str ) . '</span>'; ?>
                <?php if ( $ep_str )       echo '<span class="asd-hbadge">' . esc_html( $ep_str ) . '</span>'; ?>
                <?php foreach ( array_slice( $genre_terms, 0, 3 ) as $gt ) echo '<span class="asd-hbadge asd-hbadge--genre">' . esc_html( $gt->name ) . '</span>'; ?>
            </div>

            <?php /* 評分 pill */ ?>
            <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
                <div class="asd-hero-scores">
                    <?php if ( $score_anilist ) : ?>
                        <a class="asd-score-pill asd-score-pill--al" <?php if ( $anilist_id ) echo 'href="https://anilist.co/anime/' . esc_attr( $anilist_id ) . '/" target="_blank" rel="noopener"'; ?>>
                            <strong><?php echo esc_html( $score_anilist ); ?></strong>
                            <small>AniList</small>
                        </a>
                    <?php endif; ?>
                    <?php if ( $score_mal ) : ?>
                        <a class="asd-score-pill asd-score-pill--mal" <?php if ( $mal_id ) echo 'href="https://myanimelist.net/anime/' . esc_attr( $mal_id ) . '/" target="_blank" rel="noopener"'; ?>>
                            <strong><?php echo esc_html( $score_mal ); ?></strong>
                            <small>MAL</small>
                        </a>
                    <?php endif; ?>
                    <?php if ( $score_bangumi ) : ?>
                        <a class="asd-score-pill asd-score-pill--bgm" <?php if ( $bangumi_id ) echo 'href="https://bgm.tv/subject/' . esc_attr( $bangumi_id ) . '/" target="_blank" rel="noopener"'; ?>>
                            <strong><?php echo esc_html( $score_bangumi ); ?></strong>
                            <small>Bangumi</small>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php /* 按鈕列 */ ?>
            <div class="asd-hero-actions">
                <?php if ( $youtube_id ) : ?>
                    <a href="#asd-sec-trailer" class="asd-action-btn asd-action-btn--primary">▶ 觀看預告</a>
                <?php endif; ?>
                <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?>
                    <a href="#asd-sec-stream" class="asd-action-btn asd-action-btn--ghost" title="<?php echo esc_attr( $display_title ); ?> 線上觀看">🎬 線上觀看</a>
                <?php endif; ?>
                <a href="https://forms.gle/ID" target="_blank" rel="noopener noreferrer" class="asd-action-btn asd-action-btn--ghost">📥 下載資源</a>
                <?php if ( $official_site ) : ?>
                    <a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener noreferrer" class="asd-action-btn asd-action-btn--ghost">🔗 官方網站</a>
                <?php endif; ?>
            </div>

        </div>

        <?php /* 右側邊欄 */ ?>
        <div class="asd-hero-sidebar">

            <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
                <div class="asd-hside-block">
                    <div class="asd-hside-title">評分</div>
                    <?php if ( $score_anilist ) : ?>
                        <div class="asd-hside-row">
                            <span class="asd-hside-dot" style="background:var(--asd-score-al)"></span>
                            <span class="asd-hside-key">AniList</span>
                            <span class="asd-hside-val"><?php echo esc_html( $score_anilist ); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ( $score_mal ) : ?>
                        <div class="asd-hside-row">
                            <span class="asd-hside-dot" style="background:var(--asd-score-mal)"></span>
                            <span class="asd-hside-key">MAL</span>
                            <span class="asd-hside-val"><?php echo esc_html( $score_mal ); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ( $score_bangumi ) : ?>
                        <div class="asd-hside-row">
                            <span class="asd-hside-dot" style="background:var(--asd-score-bgm)"></span>
                            <span class="asd-hside-key">Bangumi</span>
                            <span class="asd-hside-val"><?php echo esc_html( $score_bangumi ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="asd-hside-block">
                <?php
                $meta_rows = array(
                    '集數'     => $ep_str,
                    '每集時長' => $duration ? $duration . ' 分鐘' : '',
                    '原作'     => $source_label,
                    '季度'     => $season_str,
                    '製作公司' => $studio,
                );
                foreach ( $meta_rows as $mk => $mv ) :
                    if ( ! strlen( (string) $mv ) ) continue;
                ?>
                    <div class="asd-hside-info-row">
                        <span class="asd-hside-info-key"><?php echo esc_html( $mk ); ?></span>
                        <span class="asd-hside-info-val"><?php echo esc_html( $mv ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php /* 外部來源連結 */ ?>
            <?php if ( $anilist_id || $mal_id || $bangumi_id || $wikipedia_url ) : ?>
                <div class="asd-hside-block asd-hside-links">
                    <div class="asd-hside-title">外部連結</div>
                    <div class="asd-hside-ext-links">
                        <?php if ( $anilist_id ) : ?>
                            <a href="https://anilist.co/anime/<?php echo esc_attr( $anilist_id ); ?>/" target="_blank" rel="noopener" class="asd-hside-ext-btn asd-ext--al">AniList ↗</a>
                        <?php endif; ?>
                        <?php if ( $mal_id ) : ?>
                            <a href="https://myanimelist.net/anime/<?php echo esc_attr( $mal_id ); ?>/" target="_blank" rel="noopener" class="asd-hside-ext-btn asd-ext--mal">MAL ↗</a>
                        <?php endif; ?>
                        <?php if ( $bangumi_id ) : ?>
                            <a href="https://bgm.tv/subject/<?php echo esc_attr( $bangumi_id ); ?>/" target="_blank" rel="noopener" class="asd-hside-ext-btn asd-ext--bgm">Bangumi ↗</a>
                        <?php endif; ?>
                        <?php if ( $wikipedia_url ) : ?>
                            <a href="<?php echo esc_url( $wikipedia_url ); ?>" target="_blank" rel="noopener" class="asd-hside-ext-btn">Wikipedia ↗</a>
                        <?php endif; ?>
                        <?php if ( $twitter_url ) : ?>
                            <a href="<?php echo esc_url( $twitter_url ); ?>" target="_blank" rel="noopener" class="asd-hside-ext-btn">Twitter ↗</a>
                        <?php endif; ?>
                        <?php if ( $tiktok_url ) : ?>
                            <a href="<?php echo esc_url( $tiktok_url ); ?>" target="_blank" rel="noopener" class="asd-hside-ext-btn">TikTok ↗</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <?php /* ── Tabs ── */ ?>
    <nav class="asd-tabs" id="asd-tabs" aria-label="內容導覽">
        <a class="asd-tab" href="#asd-sec-info">📋 基本資訊</a>
        <?php if ( $synopsis ) : ?><a class="asd-tab" href="#asd-sec-synopsis">📝 劇情簡介</a><?php endif; ?>
        <?php if ( $youtube_id ) : ?><a class="asd-tab" href="#asd-sec-trailer">🎞 預告片</a><?php endif; ?>
        <?php if ( ! empty( $episodes_list ) ) : ?><a class="asd-tab" href="#asd-sec-episodes">📺 集數列表</a><?php endif; ?>
        <?php if ( ! empty( $staff_list ) ) : ?><a class="asd-tab" href="#asd-sec-staff">🎬 STAFF</a><?php endif; ?>
        <?php if ( ! empty( $cast_main ) ) : ?><a class="asd-tab" href="#asd-sec-cast">🎭 CAST</a><?php endif; ?>
        <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?><a class="asd-tab" href="#asd-sec-music">🎵 主題曲</a><?php endif; ?>
        <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?><a class="asd-tab" href="#asd-sec-stream">📡 串流平台</a><?php endif; ?>
        <?php if ( ! empty( $faq_items ) ) : ?><a class="asd-tab" href="#asd-sec-faq">❓ 常見問題</a><?php endif; ?>
        <?php if ( $official_site || $twitter_url || $wikipedia_url || $tiktok_url || $anilist_id || $mal_id || $bangumi_id ) : ?>
            <a class="asd-tab" href="#asd-sec-links">🔗 外部連結</a>
        <?php endif; ?>
        <a class="asd-tab" href="#comments">💬 留言</a>
    </nav>

    <div class="asd-container<?php echo $has_sidebar_content ? ' asd-container--has-sidebar' : ''; ?>">

        <main class="asd-main" id="asd-main">

            <?php /* ── 基本資訊 ── */ ?>
            <section class="asd-section" id="asd-sec-info">
                <h2 class="asd-section-title">📋 基本資訊</h2>
                <div class="asd-info-grid">
                    <?php
                    $info_rows = array(
                        '類型'     => $format_label,
                        '集數'     => $ep_str,
                        '狀態'     => $status_label,
                        '播出季度' => $season_str,
                        '每集時長' => $duration ? $duration . ' 分鐘' : '',
                        '開始日期' => $start_date,
                        '結束日期' => ( $end_date && $status === 'FINISHED' ) ? $end_date : '',
                        '原作來源' => $source_label,
                        '製作公司' => $studio,
                        '代理發行' => $tw_dist_display,
                        '播出頻道' => $tw_broadcast,
                    );
                    foreach ( $info_rows as $label => $val ) :
                        if ( $val === '' || $val === null ) continue;
                    ?>
                        <div class="asd-info-row">
                            <span class="asd-info-label"><?php echo esc_html( $label ); ?></span>
                            <span class="asd-info-val"><?php echo esc_html( $val ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ( $status === 'RELEASING' && ! empty( $airing_data['airingAt'] ) ) : ?>
                    <div class="asd-airing-bar">
                        <span>第 <?php echo esc_html( isset( $airing_data['episode'] ) ? $airing_data['episode'] : '' ); ?> 集播出倒數</span>
                        <strong class="asd-countdown" data-ts="<?php echo esc_attr( $airing_data['airingAt'] ); ?>"></strong>
                    </div>
                <?php endif; ?>
            </section>

            <?php /* ── 劇情簡介 ── */ ?>
            <?php if ( $synopsis ) : ?>
                <section class="asd-section" id="asd-sec-synopsis">
                    <h2 class="asd-section-title">📝 劇情簡介</h2>
                    <div class="asd-synopsis"><?php echo wp_kses_post( wpautop( $synopsis ) ); ?></div>
                </section>
            <?php endif; ?>

            <?php /* ── 預告片 ── */ ?>
            <?php if ( $youtube_id ) : ?>
                <section class="asd-section" id="asd-sec-trailer">
                    <h2 class="asd-section-title">🎞 預告片</h2>
                    <div class="asd-trailer-wrap">
                        <iframe src="https://www.youtube.com/embed/<?php echo esc_attr( $youtube_id ); ?>?rel=0&modestbranding=1"
                            title="<?php echo esc_attr( $display_title ); ?> 預告片"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen loading="lazy">
                        </iframe>
                    </div>
                </section>
            <?php endif; ?>

            <?php /* ── 集數列表 ── */ ?>
            <?php if ( ! empty( $episodes_list ) ) : ?>
                <section class="asd-section" id="asd-sec-episodes">
                    <h2 class="asd-section-title">📺 集數列表</h2>
                    <div class="asd-ep-list" id="asd-ep-list">
                        <?php foreach ( $episodes_list as $i => $ep ) :
                            $ep_num     = isset( $ep['ep'] ) ? (int) $ep['ep'] : 0;
                            $ep_name_cn = trim( isset( $ep['name_cn'] ) ? $ep['name_cn'] : '' );
                            $ep_name_ja = trim( isset( $ep['name'] ) ? $ep['name'] : '' );
                            $ep_airdate = isset( $ep['airdate'] ) ? $ep['airdate'] : '';
                            if ( $ep_name_cn !== '' && class_exists( 'Anime_Sync_CN_Converter' ) && method_exists( 'Anime_Sync_CN_Converter', 'static_convert' ) ) {
                                $ep_name_cn = Anime_Sync_CN_Converter::static_convert( $ep_name_cn );
                            }
                            $ep_name = $ep_name_cn ? $ep_name_cn : $ep_name_ja;
                        ?>
                            <div class="asd-ep-row<?php echo $i >= 3 ? ' asd-ep-hidden' : ''; ?>">
                                <span class="asd-ep-num">第 <?php echo esc_html( $ep_num ); ?> 集</span>
                                <span class="asd-ep-title">
                                    <?php if ( $ep_name ) echo esc_html( $ep_name ); ?>
                                    <?php if ( $ep_name_cn && $ep_name_ja && $ep_name_cn !== $ep_name_ja ) : ?>
                                        <small class="asd-ep-title-ja"><?php echo esc_html( $ep_name_ja ); ?></small>
                                    <?php endif; ?>
                                </span>
                                <?php if ( $ep_airdate ) : ?>
                                    <span class="asd-ep-date"><?php echo esc_html( $ep_airdate ); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( count( $episodes_list ) > 3 ) : ?>
                        <button class="asd-ep-toggle" id="asd-ep-toggle" type="button">
                            顯示全部 <?php echo count( $episodes_list ); ?> 集 ▼
                        </button>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php /* ── STAFF ── */ ?>
            <?php if ( ! empty( $staff_list ) ) : ?>
                <section class="asd-section" id="asd-sec-staff">
                    <h2 class="asd-section-title">🎬 STAFF</h2>
                    <div class="asd-staff-grid" id="asd-staff-grid">
                        <?php
                        $staff_show_limit = 8;
                        foreach ( $staff_list as $si => $s ) :
                            $s_name = isset( $s['name'] ) ? trim( $s['name'] ) : ( isset( $s['name_zh'] ) ? trim( $s['name_zh'] ) : '' );
                            if ( class_exists( 'Anime_Sync_CN_Converter' ) && method_exists( 'Anime_Sync_CN_Converter', 'static_convert' ) ) {
                                $s_name = Anime_Sync_CN_Converter::static_convert( $s_name );
                            }
                            $s_role = isset( $s['role'] ) ? trim( $s['role'] ) : ( isset( $s['role_zh'] ) ? trim( $s['role_zh'] ) : '' );
                            if ( class_exists( 'Anime_Sync_CN_Converter' ) && method_exists( 'Anime_Sync_CN_Converter', 'static_convert' ) ) {
                                $s_role = Anime_Sync_CN_Converter::static_convert( $s_role );
                            }
                            $s_img  = isset( $s['image'] ) ? $s['image'] : ( isset( $s['image_url'] ) ? $s['image_url'] : ( isset( $s['img'] ) ? $s['img'] : '' ) );
                            $s_fb   = $fallback_text( $s_name, 1 );
                            if ( $s_name === '' ) continue;
                        ?>
                            <div class="asd-staff-card<?php echo $si >= $staff_show_limit ? ' asd-staff-hidden' : ''; ?>">
                                <div class="asd-staff-avatar">
                                    <?php if ( $s_img ) : ?>
                                        <img src="<?php echo esc_url( $s_img ); ?>"
                                             alt="<?php echo esc_attr( $s_name ); ?>"
                                             loading="lazy"
                                             onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <span class="asd-staff-avatar-fb" style="display:none"><?php echo esc_html( $s_fb ); ?></span>
                                    <?php else : ?>
                                        <span class="asd-staff-avatar-fb"><?php echo esc_html( $s_fb ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="asd-staff-info">
                                    <div class="asd-staff-name"><?php echo esc_html( $s_name ); ?></div>
                                    <?php if ( $s_role ) : ?>
                                        <div class="asd-staff-role"><?php echo esc_html( $s_role ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( count( $staff_list ) > $staff_show_limit ) : ?>
                        <button class="asd-staff-toggle" id="asd-staff-toggle" type="button">
                            顯示全部 <?php echo count( $staff_list ); ?> 人 ▼
                        </button>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php /* ── CAST ── */ ?>
            <?php if ( ! empty( $cast_main ) ) : ?>
                <section class="asd-section" id="asd-sec-cast">
                    <h2 class="asd-section-title">🎭 CAST</h2>
                    <div class="asd-cast-grid" id="asd-cast-grid">
                        <?php foreach ( $cast_main as $ci => $c ) :
                            /* 角色名稱 */
                            $char_name = '';
                            if ( ! empty( $c['character_name_zh'] ) )      $char_name = $c['character_name_zh'];
                            elseif ( ! empty( $c['character_name'] ) )      $char_name = $c['character_name'];
                            elseif ( ! empty( $c['char_name_zh'] ) )        $char_name = $c['char_name_zh'];
                            elseif ( ! empty( $c['char_name'] ) )           $char_name = $c['char_name'];
                            elseif ( ! empty( $c['name'] ) )                $char_name = $c['name'];

                            /* 聲優名稱 */
                            $cv_name = '';
                            if ( ! empty( $c['voice_actor'] ) )             $cv_name = $c['voice_actor'];
                            elseif ( ! empty( $c['va_name'] ) )             $cv_name = $c['va_name'];
                            elseif ( ! empty( $c['cv'] ) )                  $cv_name = $c['cv'];
                            elseif ( ! empty( $c['voice_actor_name'] ) )    $cv_name = $c['voice_actor_name'];
                            elseif ( ! empty( $c['actor'] ) )               $cv_name = $c['actor'];
                            elseif ( ! empty( $c['actor_name'] ) )          $cv_name = $c['actor_name'];

                            /* 圖片 */
                            $c_img = '';
                            if ( ! empty( $c['character_image'] ) )         $c_img = $c['character_image'];
                            elseif ( ! empty( $c['char_image'] ) )          $c_img = $c['char_image'];
                            elseif ( ! empty( $c['image'] ) )               $c_img = $c['image'];
                            elseif ( ! empty( $c['image_url'] ) )           $c_img = $c['image_url'];
                            elseif ( ! empty( $c['cover'] ) )               $c_img = $c['cover'];

                            $c_fb = $fallback_text( $char_name, 2 );
                            if ( $char_name === '' && $cv_name === '' ) continue;
                        ?>
                            <div class="asd-cast-card">
                                <div class="asd-cast-avatar">
                                    <?php if ( $c_img ) : ?>
                                        <img src="<?php echo esc_url( $c_img ); ?>"
                                             alt="<?php echo esc_attr( $char_name ); ?>"
                                             loading="lazy"
                                             onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <span class="asd-cast-avatar-fb" style="display:none"><?php echo esc_html( $c_fb ); ?></span>
                                    <?php else : ?>
                                        <span class="asd-cast-avatar-fb"><?php echo esc_html( $c_fb ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="asd-cast-info">
                                    <?php if ( $char_name ) : ?>
                                        <div class="asd-cast-char"><?php echo esc_html( $char_name ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( $cv_name ) : ?>
                                        <div class="asd-cast-cv">CV: <?php echo esc_html( $cv_name ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php /* ── 主題曲 ── */ ?>
            <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?>
                <section class="asd-section" id="asd-sec-music">
                    <h2 class="asd-section-title">🎵 主題曲</h2>

                    <?php if ( ! empty( $openings ) ) : ?>
                        <div class="asd-music-group">
                            <h3 class="asd-music-group-title">片頭曲 OP</h3>
                            <?php foreach ( $openings as $ot ) :
                                $song_title  = isset( $ot['song_title'] ) ? $ot['song_title'] : ( isset( $ot['title'] ) ? $ot['title'] : '' );
                                $song_native = isset( $ot['song_native'] ) ? $ot['song_native'] : ( isset( $ot['native'] ) ? $ot['native'] : '' );
                                $song_artist = isset( $ot['artist'] ) ? $ot['artist'] : ( isset( $ot['singer'] ) ? $ot['singer'] : '' );
                                $song_audio  = isset( $ot['audio_url'] ) ? $ot['audio_url'] : ( isset( $ot['audio'] ) ? $ot['audio'] : '' );
                                $song_type   = isset( $ot['type'] ) ? strtoupper( $ot['type'] ) : 'OP';
                            ?>
                                <div class="asd-music-card">
                                    <div class="asd-music-header">
                                        <span class="asd-music-type-badge asd-music-type-op"><?php echo esc_html( $song_type ); ?></span>
                                        <div class="asd-music-titles">
                                            <div class="asd-music-song-title"><?php echo esc_html( $song_title ); ?></div>
                                            <?php if ( $song_native && $song_native !== $song_title ) : ?>
                                                <div class="asd-music-song-native"><?php echo esc_html( $song_native ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( $song_artist ) : ?>
                                                <div class="asd-music-artist"><?php echo esc_html( $song_artist ); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ( $song_audio ) : ?>
                                        <audio class="asd-music-player" controls preload="none">
                                            <source src="<?php echo esc_url( $song_audio ); ?>">
                                            您的瀏覽器不支援音訊播放。
                                        </audio>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $endings ) ) : ?>
                        <div class="asd-music-group">
                            <h3 class="asd-music-group-title">片尾曲 ED</h3>
                            <?php foreach ( $endings as $et ) :
                                $song_title  = isset( $et['song_title'] ) ? $et['song_title'] : ( isset( $et['title'] ) ? $et['title'] : '' );
                                $song_native = isset( $et['song_native'] ) ? $et['song_native'] : ( isset( $et['native'] ) ? $et['native'] : '' );
                                $song_artist = isset( $et['artist'] ) ? $et['artist'] : ( isset( $et['singer'] ) ? $et['singer'] : '' );
                                $song_audio  = isset( $et['audio_url'] ) ? $et['audio_url'] : ( isset( $et['audio'] ) ? $et['audio'] : '' );
                                $song_type   = isset( $et['type'] ) ? strtoupper( $et['type'] ) : 'ED';
                            ?>
                                <div class="asd-music-card">
                                    <div class="asd-music-header">
                                        <span class="asd-music-type-badge asd-music-type-ed"><?php echo esc_html( $song_type ); ?></span>
                                        <div class="asd-music-titles">
                                            <div class="asd-music-song-title"><?php echo esc_html( $song_title ); ?></div>
                                            <?php if ( $song_native && $song_native !== $song_title ) : ?>
                                                <div class="asd-music-song-native"><?php echo esc_html( $song_native ); ?></div>
                                            <?php endif; ?>
                                            <?php if ( $song_artist ) : ?>
                                                <div class="asd-music-artist"><?php echo esc_html( $song_artist ); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ( $song_audio ) : ?>
                                        <audio class="asd-music-player" controls preload="none">
                                            <source src="<?php echo esc_url( $song_audio ); ?>">
                                            您的瀏覽器不支援音訊播放。
                                        </audio>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </section>
            <?php endif; ?>

            <?php /* ── 串流平台 ── */ ?>
            <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?>
                <section class="asd-section" id="asd-sec-stream">
                    <h2 class="asd-section-title">📡 串流平台</h2>

                    <?php if ( ! empty( $tw_streaming_items ) ) : ?>
                        <div class="asd-stream-region">
                            <div class="asd-stream-region-title">🇹🇼 台灣</div>
                            <div class="asd-stream-grid">
                                <?php foreach ( $tw_streaming_items as $si ) : ?>
                                    <?php if ( $si['url'] ) : ?>
                                        <a href="<?php echo esc_url( $si['url'] ); ?>" target="_blank" rel="noopener" class="asd-stream-card">
                                            <span class="asd-stream-label"><?php echo esc_html( $si['label'] ); ?></span>
                                            <span class="asd-stream-go">▶ 前往觀看</span>
                                        </a>
                                    <?php else : ?>
                                        <div class="asd-stream-card asd-stream-card--no-link">
                                            <span class="asd-stream-label"><?php echo esc_html( $si['label'] ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    $intl_streams = array();
                    foreach ( $streaming_list as $sl ) {
                        $sl_region = isset( $sl['region'] ) ? strtoupper( $sl['region'] ) : '';
                        if ( $sl_region !== 'TW' && $sl_region !== '台灣' ) {
                            $intl_streams[] = $sl;
                        }
                    }
                    if ( ! empty( $intl_streams ) ) :
                    ?>
                        <div class="asd-stream-region">
                            <div class="asd-stream-region-title">🌐 國際</div>
                            <div class="asd-stream-grid">
                                <?php foreach ( $intl_streams as $sl ) :
                                    $sl_name = isset( $sl['site'] ) ? $sl['site'] : ( isset( $sl['name'] ) ? $sl['name'] : '' );
                                    $sl_url  = isset( $sl['url'] ) ? $sl['url'] : '';
                                    if ( $sl_name === '' ) continue;
                                ?>
                                    <?php if ( $sl_url ) : ?>
                                        <a href="<?php echo esc_url( $sl_url ); ?>" target="_blank" rel="noopener" class="asd-stream-card">
                                            <span class="asd-stream-label"><?php echo esc_html( $sl_name ); ?></span>
                                            <span class="asd-stream-go">▶ 前往觀看</span>
                                        </a>
                                    <?php else : ?>
                                        <div class="asd-stream-card asd-stream-card--no-link">
                                            <span class="asd-stream-label"><?php echo esc_html( $sl_name ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </section>
            <?php endif; ?>

            <?php /* ── 常見問題 ── */ ?>
            <?php if ( ! empty( $faq_items ) ) : ?>
                <section class="asd-section" id="asd-sec-faq">
                    <h2 class="asd-section-title">❓ 常見問題</h2>
                    <div class="asd-faq-list">
                        <?php foreach ( $faq_items as $fi => $faq ) :
                            if ( empty( $faq['q'] ) ) continue;
                        ?>
                            <div class="asd-faq-item">
                                <div class="asd-faq-question" onclick="this.parentElement.classList.toggle('is-open')">
                                    <span><?php echo esc_html( $faq['q'] ); ?></span>
                                    <span class="asd-faq-icon">＋</span>
                                </div>
                                <?php if ( ! empty( $faq['a'] ) ) : ?>
                                    <div class="asd-faq-answer"><?php echo wp_kses_post( wpautop( $faq['a'] ) ); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php /* ── 外部連結 ── */ ?>
            <?php if ( $official_site || $twitter_url || $wikipedia_url || $tiktok_url || $anilist_id || $mal_id || $bangumi_id ) : ?>
                <section class="asd-section" id="asd-sec-links">
                    <h2 class="asd-section-title">🔗 外部連結</h2>
                    <div class="asd-links-grid">
                        <?php if ( $official_site ) : ?>
                            <a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener" class="asd-link-card">
                                <span class="asd-link-card__site">🌐 官方網站</span>
                                <span class="asd-link-card__title">Official Site</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $twitter_url ) : ?>
                            <a href="<?php echo esc_url( $twitter_url ); ?>" target="_blank" rel="noopener" class="asd-link-card">
                                <span class="asd-link-card__site">🐦 Twitter / X</span>
                                <span class="asd-link-card__title">@官方帳號</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $wikipedia_url ) : ?>
                            <a href="<?php echo esc_url( $wikipedia_url ); ?>" target="_blank" rel="noopener" class="asd-link-card">
                                <span class="asd-link-card__site">📖 Wikipedia</span>
                                <span class="asd-link-card__title">Wikipedia 條目</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $tiktok_url ) : ?>
                            <a href="<?php echo esc_url( $tiktok_url ); ?>" target="_blank" rel="noopener" class="asd-link-card">
                                <span class="asd-link-card__site">🎵 TikTok</span>
                                <span class="asd-link-card__title">TikTok 帳號</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $anilist_id ) : ?>
                            <a href="https://anilist.co/anime/<?php echo esc_attr( $anilist_id ); ?>/" target="_blank" rel="noopener" class="asd-link-card asd-link-card--al">
                                <span class="asd-link-card__site">AL AniList</span>
                                <span class="asd-link-card__title">AniList 頁面</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $mal_id ) : ?>
                            <a href="https://myanimelist.net/anime/<?php echo esc_attr( $mal_id ); ?>/" target="_blank" rel="noopener" class="asd-link-card asd-link-card--mal">
                                <span class="asd-link-card__site">MAL MyAnimeList</span>
                                <span class="asd-link-card__title">MyAnimeList 頁面</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $bangumi_id ) : ?>
                            <a href="https://bgm.tv/subject/<?php echo esc_attr( $bangumi_id ); ?>/" target="_blank" rel="noopener" class="asd-link-card asd-link-card--bgm">
                                <span class="asd-link-card__site">BGM Bangumi</span>
                                <span class="asd-link-card__title">Bangumi 頁面</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php /* ── 留言 ── */ ?>
            <div class="asd-comments" id="comments">
                <div class="asd-comment-box">
                    <?php
                    if ( comments_open() || get_comments_number() ) {
                        comments_template();
                    }
                    ?>
                </div>
            </div>

        </main>

        <?php /* ── 右側 Sidebar ── */ ?>
        <?php if ( $has_sidebar_content ) : ?>
            <aside class="asd-sidebar">

                <?php if ( $affiliate_html ) : ?>
                    <div class="asd-side-section">
                        <div class="asd-side-section__head"><h3>贊助</h3></div>
                        <div class="asd-affiliate-box"><?php echo wp_kses_post( $affiliate_html ); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $site_relations ) ) : ?>
                    <div class="asd-side-section">
                        <div class="asd-side-section__head"><h3>相關作品</h3></div>
                        <div class="asd-side-cards">
                            <?php foreach ( $site_relations as $rel ) : ?>
                                <a href="<?php echo esc_url( $rel['url'] ); ?>" class="asd-mini-card">
                                    <div class="asd-mini-card__thumb">
                                        <?php if ( $rel['cover_image'] ) : ?>
                                            <img src="<?php echo esc_url( $rel['cover_image'] ); ?>" alt="<?php echo esc_attr( $rel['title_zh'] ); ?>" loading="lazy">
                                        <?php endif; ?>
                                    </div>
                                    <div class="asd-mini-card__body">
                                        <?php if ( $rel['relation_label'] ) : ?>
                                            <div class="asd-mini-card__meta"><?php echo esc_html( $rel['relation_label'] ); ?></div>
                                        <?php endif; ?>
                                        <div class="asd-mini-card__title"><?php echo esc_html( $rel['title_zh'] ? $rel['title_zh'] : $rel['title_native'] ); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $news_items ) ) : ?>
                    <div class="asd-side-section">
                        <div class="asd-side-section__head"><h3>相關新聞</h3></div>
                        <div class="asd-side-news">
                            <?php foreach ( array_slice( $news_items, 0, 5 ) as $ni ) : ?>
                                <?php if ( $ni['url'] ) : ?>
                                    <a href="<?php echo esc_url( $ni['url'] ); ?>" target="_blank" rel="noopener" class="asd-news-card">
                                        <div class="asd-news-card__title"><?php echo esc_html( $ni['title'] ); ?></div>
                                    </a>
                                <?php else : ?>
                                    <div class="asd-news-card">
                                        <div class="asd-news-card__title"><?php echo esc_html( $ni['title'] ); ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </aside>
        <?php endif; ?>

    </div>

</div>

<script>
(function(){
    /* ── Episode toggle ── */
    var epToggle = document.getElementById('asd-ep-toggle');
    if(epToggle){
        epToggle.addEventListener('click',function(){
            var hidden = document.querySelectorAll('#asd-ep-list .asd-ep-hidden');
            var expanded = this.classList.contains('is-expanded');
            hidden.forEach(function(el){ el.style.display = expanded ? '' : 'flex'; });
            this.classList.toggle('is-expanded');
            this.textContent = expanded
                ? '顯示全部 <?php echo count($episodes_list); ?> 集 ▼'
                : '收起集數列表 ▲';
        });
    }

    /* ── Staff toggle ── */
    var staffToggle = document.getElementById('asd-staff-toggle');
    if(staffToggle){
        staffToggle.addEventListener('click',function(){
            var hidden = document.querySelectorAll('#asd-staff-grid .asd-staff-hidden');
            var expanded = this.classList.contains('is-expanded');
            hidden.forEach(function(el){ el.style.display = expanded ? '' : 'flex'; });
            this.classList.toggle('is-expanded');
            this.textContent = expanded
                ? '顯示全部 <?php echo count($staff_list); ?> 人 ▼'
                : '收起 STAFF ▲';
        });
    }

    /* ── FAQ toggle ── */
    document.querySelectorAll('.asd-faq-question').forEach(function(q){
        q.addEventListener('click',function(){
            var item = this.parentElement;
            var isOpen = item.classList.contains('is-open');
            document.querySelectorAll('.asd-faq-item.is-open').forEach(function(oi){ oi.classList.remove('is-open'); });
            if(!isOpen) item.classList.add('is-open');
        });
    });

    /* ── Countdown ── */
    var countdowns = document.querySelectorAll('.asd-countdown[data-ts]');
    if(countdowns.length){
        function updateCountdowns(){
            var now = Math.floor(Date.now()/1000);
            countdowns.forEach(function(el){
                var diff = parseInt(el.dataset.ts,10) - now;
                if(diff<=0){ el.textContent='已播出'; return; }
                var d=Math.floor(diff/86400), h=Math.floor(diff%86400/3600), m=Math.floor(diff%3600/60), s=diff%60;
                el.textContent = (d?d+'天':'')+(h?h+'時':'')+(m?m+'分':'')+s+'秒';
            });
        }
        updateCountdowns();
        setInterval(updateCountdowns,1000);
    }

    /* ── Active tab highlight ── */
    var tabs = document.querySelectorAll('.asd-tab');
    var sections = [];
    tabs.forEach(function(t){
        var id = t.getAttribute('href');
        if(id && id.startsWith('#')){
            var sec = document.querySelector(id);
            if(sec) sections.push({tab:t,sec:sec});
        }
    });
    function setActiveTab(){
        var scrollY = window.scrollY + 120;
        var active = null;
        sections.forEach(function(s){ if(s.sec.offsetTop <= scrollY) active=s; });
        tabs.forEach(function(t){ t.classList.remove('is-active'); });
        if(active) active.tab.classList.add('is-active');
    }
    window.addEventListener('scroll',setActiveTab,{passive:true});
    setActiveTab();
})();
</script>

<?php endwhile; ?>
<?php get_footer(); ?>

<?php
/**
 * Single Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/single-anime.php
 *
 * @package Anime_Sync_Pro
 * @version 13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_enqueue_style(
    'anime-sync-single',
    plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/anime-single.css',
    array(),
    '14.5'
);

get_header();

while ( have_posts() ) :
    the_post();

    $post_id = get_the_ID();

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
        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $raw, $m ) ) return $m[1] . '-' . $m[2] . '-' . $m[3];
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
        if ( ! empty( $item['title'] ) ) $title = $item['title'];
        elseif ( ! empty( $item['name'] ) ) $title = $item['name'];
        elseif ( ! empty( $item['headline'] ) ) $title = $item['headline'];
        if ( ! empty( $item['url'] ) ) $url = $item['url'];
        elseif ( ! empty( $item['link'] ) ) $url = $item['link'];
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
        'righttime'   => '',
        'gaga'        => 'GaGa OOLala',
        'catchplay'   => 'CatchPlay',
        'netflix'     => 'Netflix',
        'disney'      => 'Disney+',
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
        'bahamut'     => '巴哈姆特',
        'netflix'     => 'Netflix',
        'disney'      => 'Disney+',
        'amazon'      => 'Amazon Prime Video',
        'kktv'        => 'KKTV',
        'friday'      => 'friDay影音',
        'catchplay'   => 'CatchPlay+',
        'bilibili'    => 'Bilibili',
        'crunchyroll' => 'Crunchyroll',
        'hulu'        => 'Hulu',
        'hidive'      => 'HIDIVE',
        'ani-one'     => 'Ani-One',
        'muse'        => 'Muse Asia',
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
                'label' => isset( $tw_stream_labels[ $key ] ) ? $tw_stream_labels[ $key ] : $key,
                'url'   => isset( $tw_stream_url_map[ $key ] ) ? $tw_stream_url_map[ $key ] : '',
            );
        }
    }
    if ( $tw_streaming_other ) {
        $extra_items = array_map( 'trim', explode( ',', $tw_streaming_other ) );
        foreach ( $extra_items as $extra ) {
            if ( $extra !== '' ) {
                $tw_streaming_items[] = array( 'label' => $extra, 'url' => '' );
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
        $trailer_candidates = preg_split( '/[,\n]+/', (string) $trailer_url );
        if ( is_array( $trailer_candidates ) ) {
            foreach ( $trailer_candidates as $t_url ) {
                $t_url = trim( $t_url );
                if ( $t_url === '' ) continue;
                if ( preg_match( '/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{11})/', $t_url, $m ) ) {
                    $youtube_id = $m[1];
                    break;
                }
                if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $t_url ) ) {
                    $youtube_id = $t_url;
                    break;
                }
            }
        }
    }

    $official_site  = $get_meta( 'anime_official_site' );
    $twitter_url    = $get_meta( 'anime_twitter_url' );
    $wikipedia_url  = $get_meta( 'anime_wikipedia_url' );
    $tiktok_url     = $get_meta( 'anime_tiktok_url' );
    $affiliate_html = $get_meta( 'anime_affiliate_html' );

    $next_airing_raw = $get_meta( 'anime_next_airing' );
    $airing_data     = array();
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
    if ( ! empty( $news_items ) ) {
        foreach ( $news_items as $news_item ) {
            $normalized = $normalize_news_item( $news_item );
            if ( $normalized ) $normalized_news[] = $normalized;
        }
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
        if ( $starts_with( $type, 'OP' ) ) $openings[] = $t;
        elseif ( $starts_with( $type, 'ED' ) ) $endings[] = $t;
    }

    /* ── Labels ── */
    $season_labels  = array( 'WINTER' => '冬季', 'SPRING' => '春季', 'SUMMER' => '夏季', 'FALL' => '秋季' );
    $format_labels  = array( 'TV' => 'TV', 'TV_SHORT' => 'TV', 'MOVIE' => '劇場版', 'OVA' => 'OVA', 'ONA' => 'ONA', 'SPECIAL' => '特別篇', 'MUSIC' => 'MV' );
    $status_labels  = array( 'FINISHED' => '已完結', 'RELEASING' => '連載中', 'NOT_YET_RELEASED' => '尚未播出', 'CANCELLED' => '已取消', 'HIATUS' => '暫停中' );
    $status_classes = array( 'FINISHED' => 's-fin', 'RELEASING' => 's-rel', 'NOT_YET_RELEASED' => 's-pre', 'CANCELLED' => 's-can', 'HIATUS' => 's-hia' );
    $source_labels  = array(
        'ORIGINAL' => '原創', 'MANGA' => '漫畫改編', 'LIGHT_NOVEL' => '輕小說改編',
        'NOVEL' => '小說改編', 'VISUAL_NOVEL' => '視覺小說改編', 'VIDEO_GAME' => '電玩改編',
        'WEB_MANGA' => '網路漫畫改編', 'BOOK' => '書籍改編', 'MUSIC' => '音樂改編',
        'GAME' => '遊戲改編', 'LIVE_ACTION' => '真人改編', 'MULTIMEDIA_PROJECT' => '跨媒體企劃',
        'OTHER' => '其他',
    );

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
    elseif ( $season_year ) $season_str = (string) $season_year;

    $genre_terms  = get_the_terms( $post_id, 'genre' );
    $season_terms = get_the_terms( $post_id, 'anime_season_tax' );
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
            $rel_anilist_id = 0;
            if ( isset( $rel['anilist_id'] ) ) $rel_anilist_id = (int) $rel['anilist_id'];
            elseif ( isset( $rel['id'] ) ) $rel_anilist_id = (int) $rel['id'];
            if ( ! $rel_anilist_id ) continue;
            $qr = get_posts( array(
                'post_type' => 'anime', 'post_status' => 'publish',
                'posts_per_page' => 1, 'no_found_rows' => true,
                'meta_query' => array( array( 'key' => 'anime_anilist_id', 'value' => $rel_anilist_id, 'type' => 'NUMERIC' ) ),
            ) );
            if ( ! empty( $qr ) ) {
                $site_rel_post = $qr[0];
                $relation_labels = array(
                    'PREQUEL' => '前作', 'SEQUEL' => '續作', 'PARENT' => '本篇',
                    'SIDE_STORY' => '外傳', 'CHARACTER' => '角色', 'SUMMARY' => '總集篇',
                    'ALTERNATIVE' => '替代版本', 'SPIN_OFF' => '衍生作', 'OTHER' => '相關',
                    'SOURCE' => '原作', 'COMPILATION' => '編輯版', 'CONTAINS' => '收錄',
                    'ANIME' => '動畫',
                );
                $raw_label = isset( $rel['relation_label'] ) ? $rel['relation_label'] : ( isset( $rel['type'] ) ? $rel['type'] : '' );
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
    $schema_type = 'TVSeries';
    if ( $format === 'MOVIE' ) $schema_type = 'Movie';
    elseif ( $format === 'MUSIC' ) $schema_type = 'MusicVideoObject';

    $schema_genres = array();
    foreach ( $genre_terms as $t ) $schema_genres[] = $t->name;

    $alternate_names    = array_values( array_filter( array( $title_native, $title_romaji, $title_english ) ) );
    $schema_description = $substr_safe( wp_strip_all_tags( $synopsis ), 0, 200 );

    $schema = array(
        '@context' => 'https://schema.org', '@type' => $schema_type,
        'name' => $display_title, 'description' => $schema_description,
        'image' => $cover_image ? $cover_image : get_the_post_thumbnail_url( $post_id, 'large' ),
        'genre' => $schema_genres, 'datePublished' => $start_date,
        'url' => get_permalink( $post_id ),
    );
    if ( ! empty( $alternate_names ) ) $schema['alternateName'] = $alternate_names;
    if ( $episodes ) $schema['numberOfEpisodes'] = $episodes;
    if ( $score_anilist_num > 0 ) {
        $schema['aggregateRating'] = array(
            '@type' => 'AggregateRating',
            'ratingValue' => number_format( $score_anilist_num / 10, 1 ),
            'bestRating' => '10', 'worstRating' => '1',
            'ratingCount' => max( 1, $popularity ),
        );
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

    $faq_items = array(); $faq_schema = null;
    $faq_json_raw = $get_meta( 'anime_faq_json' );
    if ( $faq_json_raw ) {
        $faq_decoded = json_decode( $faq_json_raw, true );
        if ( is_array( $faq_decoded ) ) $faq_items = $faq_decoded;
    }
    if ( ! empty( $faq_items ) ) {
        $faq_schema_main = array();
        foreach ( $faq_items as $f ) {
            if ( empty( $f['q'] ) || empty( $f['a'] ) ) continue;
            $faq_schema_main[] = array(
                '@type' => 'Question', 'name' => $f['q'],
                'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $f['a'] ) ),
            );
        }
        if ( ! empty( $faq_schema_main ) ) {
            $faq_schema = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq_schema_main );
        }
    }

    /* ── Cast ── */
$cast_main = array();
foreach ( $cast_list as $c ) {
    $role = isset( $c['role'] ) ? trim( $c['role'] ) : '';
    if ( $role === '主角' || strtoupper( $role ) === 'MAIN' ) $cast_main[] = $c;
}
if ( empty( $cast_main ) ) $cast_main = array_slice( $cast_list, 0, 8 );

    $poster_fallback     = $fallback_text( $display_title, 2 );
    $has_sidebar_content = ! empty( $news_items ) || ! empty( $site_relations ) || $affiliate_html || $official_site || $twitter_url || $wikipedia_url || $tiktok_url;
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

    <?php /* ── Hero ── */ ?>
    <div class="asd-hero-new">

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

        <div class="asd-hero-body">

            <div class="asd-hero-breadcrumb">
                <span>動畫</span>
                <?php if ( $season_str ) : ?>
                    <span class="asd-hbc-sep">›</span>
                    <span><?php echo esc_html( $season_str ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $genre_terms ) ) : ?>
                    <span class="asd-hbc-sep">›</span>
                    <span><?php echo esc_html( $genre_terms[0]->name ); ?></span>
                <?php endif; ?>
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
                    📺 <?php echo esc_html( $series_tax->name ); ?> 系列
                </a>
            <?php endif; endif; ?>

            <div class="asd-hero-badges">
                <?php
                if ( $status_label ) {
                    $cls = 'asd-hbadge' . ( $status_class ? ' asd-hbadge--' . $status_class : '' );
                    echo '<span class="' . esc_attr( $cls ) . '">' . esc_html( $status_label ) . '</span>';
                }
                if ( $format_label ) echo '<span class="asd-hbadge">' . esc_html( $format_label ) . '</span>';
                if ( $season_str )   echo '<span class="asd-hbadge">' . esc_html( $season_str ) . '</span>';
                if ( $ep_str )       echo '<span class="asd-hbadge">' . esc_html( $ep_str ) . '</span>';
                foreach ( array_slice( $genre_terms, 0, 3 ) as $gt ) {
                    echo '<span class="asd-hbadge asd-hbadge--genre">' . esc_html( $gt->name ) . '</span>';
                }
                ?>
            </div>

            <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
                <div class="asd-hero-scores-new">
                    <?php if ( $score_anilist ) : ?>
                        <div class="asd-score-pill asd-score-pill--al">
                            <span class="asd-sp-dot"></span>
                            <span class="asd-sp-val"><?php echo esc_html( $score_anilist ); ?></span>
                            <span class="asd-sp-label">AniList</span>
                        </div>
                    <?php endif; ?>
                    <?php if ( $score_mal ) : ?>
                        <div class="asd-score-pill asd-score-pill--mal">
                            <span class="asd-sp-dot"></span>
                            <span class="asd-sp-val"><?php echo esc_html( $score_mal ); ?></span>
                            <span class="asd-sp-label">MAL</span>
                        </div>
                    <?php endif; ?>
                    <?php if ( $score_bangumi ) : ?>
                        <div class="asd-score-pill asd-score-pill--bgm">
                            <span class="asd-sp-dot"></span>
                            <span class="asd-sp-val"><?php echo esc_html( $score_bangumi ); ?></span>
                            <span class="asd-sp-label">Bangumi</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="asd-hero-actions">
                <?php if ( $youtube_id ) : ?>
                    <a href="#asd-sec-trailer" class="asd-action-btn asd-action-btn--primary">▶ 觀看預告</a>
                <?php endif; ?>
                <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?>
                    <a href="#asd-sec-stream" class="asd-action-btn asd-action-btn--ghost"
                       title="<?php echo esc_attr( $display_title ); ?> 線上觀看">📺 線上觀看</a>
                <?php endif; ?>
                <a href="https://forms.gle/ID" target="_blank" rel="noopener noreferrer" class="asd-action-btn asd-action-btn--ghost">✏ 糾錯回報</a>
                <?php if ( $official_site ) : ?>
                    <a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener noreferrer" class="asd-action-btn asd-action-btn--ghost">🌐 官方網站</a>
                <?php endif; ?>
            </div>

        </div>

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
                    '集數' => $ep_str,
                    '時長' => $duration ? $duration . ' 分鐘' : '',
                    '原作' => $source_label,
                    '季度' => $season_str,
                    '製作' => $studio,
                );
                $has_any_meta = false;
                foreach ( $meta_rows as $mk => $mv ) :
                    if ( ! strlen( (string) $mv ) ) continue;
                    $has_any_meta = true;
                ?>
                    <div class="asd-hside-info-row">
                        <span class="asd-hside-info-key"><?php echo esc_html( $mk ); ?></span>
                        <span class="asd-hside-info-val"><?php echo esc_html( $mv ); ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ( ! $has_any_meta ) : ?>
                    <p style="font-size:12px;color:var(--asd-text-muted);text-align:center;padding:8px 0;margin:0;">暫無資料</p>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.asd-hero-new -->

    <?php /* ── Tabs ── */ ?>
    <nav class="asd-tabs" id="asd-tabs" aria-label="頁面導航">
        <a class="asd-tab" href="#asd-sec-info">📋 基本資訊</a>
        <?php if ( $synopsis ) : ?><a class="asd-tab" href="#asd-sec-synopsis">📝 劇情簡介</a><?php endif; ?>
        <?php if ( $youtube_id ) : ?><a class="asd-tab" href="#asd-sec-trailer">🎞 預告片</a><?php endif; ?>
        <?php if ( ! empty( $episodes_list ) ) : ?><a class="asd-tab" href="#asd-sec-episodes">📺 集數列表</a><?php endif; ?>
        <?php if ( ! empty( $staff_list ) ) : ?><a class="asd-tab" href="#asd-sec-staff">🎬 STAFF</a><?php endif; ?>
        <?php if ( ! empty( $cast_main ) ) : ?><a class="asd-tab" href="#asd-sec-cast">🎭 CAST</a><?php endif; ?>
        <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?><a class="asd-tab" href="#asd-sec-music">🎵 主題曲</a><?php endif; ?>
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
                <div class="asd-glass-divider"></div>
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
                        '台灣代理' => $tw_dist_display,
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
                                    <div class="asd-glass-divider"></div>
                    <div class="asd-synopsis"><?php echo wp_kses_post( wpautop( $synopsis ) ); ?></div>
                </section>
            <?php endif; ?>

            <?php /* ── 預告片 ── */ ?>
            <?php if ( $youtube_id ) : ?>
                <section class="asd-section" id="asd-sec-trailer">
                    <h2 class="asd-section-title">🎞 預告片</h2>
                                    <div class="asd-glass-divider"></div>
                    <div class="asd-trailer-wrap">
                        <iframe
                            src="https://www.youtube.com/embed/<?php echo esc_attr( $youtube_id ); ?>?rel=0&modestbranding=1"
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
                                    <div class="asd-glass-divider"></div>
                    <div class="asd-ep-list" id="asd-ep-list">
                        <?php foreach ( $episodes_list as $i => $ep ) :
                            $ep_num     = isset( $ep['ep'] ) ? (int) $ep['ep'] : 0;
                            $ep_name_cn = trim( isset( $ep['name_cn'] ) ? $ep['name_cn'] : '' );
                            $ep_name_ja = trim( isset( $ep['name'] ) ? $ep['name'] : '' );
                            $ep_airdate = isset( $ep['airdate'] ) ? $ep['airdate'] : '';
                            if ( $ep_name_cn !== '' && class_exists( 'Anime_Sync_CN_Converter' ) ) {
                                $ep_name_cn = Anime_Sync_CN_Converter::static_convert( $ep_name_cn );
                            }
                            $ep_name = $ep_name_cn ? $ep_name_cn : $ep_name_ja;
                        ?>
                            <div class="asd-ep-row<?php echo $i >= 3 ? ' asd-ep-hidden' : ''; ?>">
                                <span class="asd-ep-num">第 <?php echo esc_html( $ep_num ); ?> 集</span>
                                <span class="asd-ep-title">
                                    <?php echo esc_html( $ep_name ); ?>
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
    <div style="display:flex;justify-content:center;margin-top:12px;">
        <button class="asd-ep-toggle" id="asd-ep-toggle" type="button">
            顯示全部 <?php echo count( $episodes_list ); ?> 集 ▼
        </button>
    </div>
<?php endif; ?>
</section>
<?php endif; ?>

            <?php /* ── STAFF — v14.5：純文字卡片，隱藏頭像 ── */ ?>
            <?php if ( ! empty( $staff_list ) ) : ?>
                <section class="asd-section" id="asd-sec-staff">
                    <h2 class="asd-section-title">🎬 STAFF</h2>
                                    <div class="asd-glass-divider"></div>
                    <div class="asd-staff-grid-v2">
                        <?php foreach ( $staff_list as $si => $s ) :
                            $s_name = trim( isset( $s['name'] ) ? $s['name'] : ( isset( $s['name_native'] ) ? $s['name_native'] : '' ) );
                            $s_role = trim( isset( $s['role'] ) ? $s['role'] : '' );
                            if ( $s_name === '' && $s_role === '' ) continue;
                        ?>
                            <div class="asd-staff-card-v2<?php echo $si >= 16 ? ' asd-staff-hidden' : ''; ?>">
                                <div class="asd-staff-info">
                                    <div class="asd-staff-name"><?php echo esc_html( $s_name ); ?></div>
                                    <?php if ( $s_role ) : ?>
                                        <div class="asd-staff-role"><?php echo esc_html( $s_role ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
<?php if ( count( $staff_list ) > 16 ) : ?>
    <div style="display:flex;justify-content:center;margin-top:12px;">
        <button class="asd-staff-toggle" id="asd-staff-toggle" type="button">
            顯示全部 <?php echo count( $staff_list ); ?> 位人員 ▼
        </button>
    </div>
<?php endif; ?>
                </section>
            <?php endif; ?>


            <?php /* ── CAST ── */ ?>
            <?php if ( ! empty( $cast_main ) ) : ?>
                <section class="asd-section" id="asd-sec-cast">
                    <h2 class="asd-section-title">🎭 CAST</h2>
                                    <div class="asd-glass-divider"></div>
                    <div class="asd-cast-grid-v2">
                     <?php foreach ( $cast_main as $ci => $c ) :
    $c_char = trim( isset( $c['name'] ) ? $c['name'] : '' );
    $c_va = '';
    if ( ! empty( $c['voice_actors'] ) && is_array( $c['voice_actors'] ) ) {
        $c_va = trim( isset( $c['voice_actors'][0]['name'] ) ? $c['voice_actors'][0]['name'] : '' );
    }
    $c_img = isset( $c['image'] ) ? $c['image'] : '';
    $c_fb  = $fallback_text( $c_char, 1 );
?>

                            <div class="asd-cast-card-v2<?php echo $ci >= 8 ? ' asd-cast-hidden' : ''; ?>">
                                <div class="asd-cast-avatar">
                                    <?php if ( $c_img ) : ?>
                                        <img src="<?php echo esc_url( $c_img ); ?>"
                                             alt="<?php echo esc_attr( $c_char ); ?>"
                                             loading="lazy"
                                             onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <div class="asd-cast-avatar-fb" style="display:none"><?php echo esc_html( $c_fb ); ?></div>
                                    <?php else : ?>
                                        <div class="asd-cast-avatar-fb"><?php echo esc_html( $c_fb ); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="asd-cast-info">
                                    <div class="asd-cast-char"><?php echo esc_html( $c_char ); ?></div>
                                    <?php if ( $c_va ) : ?>
                                        <div class="asd-cast-va">CV: <?php echo esc_html( $c_va ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
<?php if ( count( $cast_main ) > 8 ) : ?>
    <div style="display:flex;justify-content:center;margin-top:12px;">
        <button class="asd-cast-toggle" id="asd-cast-toggle" type="button">
            顯示全部聲優 ▼
        </button>
    </div>
<?php endif; ?>
                </section>
            <?php endif; ?>


            <?php /* ── 主題曲 ── */ ?>
            <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?>
                <section class="asd-section" id="asd-sec-music">
                    <h2 class="asd-section-title">🎵 主題曲</h2>
                                    <div class="asd-glass-divider"></div>
                    <?php if ( ! empty( $openings ) ) : ?>
                        <div class="asd-music-group">
                            <h3 class="asd-music-group-title">片頭曲 OP</h3>
                            <?php foreach ( $openings as $op ) :
                                $op_title  = isset( $op['song_title'] ) ? $op['song_title'] : ( isset( $op['title'] ) ? $op['title'] : '' );
                                $op_artist = isset( $op['artist'] ) ? $op['artist'] : '';
                                $op_native = isset( $op['song_native'] ) ? $op['song_native'] : '';
                                $op_type   = isset( $op['type'] ) ? $op['type'] : 'OP';
                                $op_audio  = isset( $op['audio_url'] ) ? $op['audio_url'] : '';
                            ?>
                                <div class="asd-music-card-v2">
                                    <div class="asd-music-type-badge"><?php echo esc_html( $op_type ); ?></div>
                                    <div class="asd-music-body">
                                        <div class="asd-music-title"><?php echo esc_html( $op_title ); ?></div>
                                        <?php if ( $op_native ) : ?>
                                            <div class="asd-music-native"><?php echo esc_html( $op_native ); ?></div>
                                        <?php endif; ?>
                                        <?php if ( $op_artist ) : ?>
                                            <div class="asd-music-artist"><?php echo esc_html( $op_artist ); ?></div>
                                        <?php endif; ?>
                                        <?php if ( $op_audio ) : ?>
                                            <audio controls preload="none" class="asd-music-player">
                                                <source src="<?php echo esc_url( $op_audio ); ?>">
                                                您的瀏覽器不支援音訊播放。
                                            </audio>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $endings ) ) : ?>
                        <div class="asd-music-group">
                            <h3 class="asd-music-group-title">片尾曲 ED</h3>
                            <?php foreach ( $endings as $ed ) :
                                $ed_title  = isset( $ed['song_title'] ) ? $ed['song_title'] : ( isset( $ed['title'] ) ? $ed['title'] : '' );
                                $ed_artist = isset( $ed['artist'] ) ? $ed['artist'] : '';
                                $ed_native = isset( $ed['song_native'] ) ? $ed['song_native'] : '';
                                $ed_type   = isset( $ed['type'] ) ? $ed['type'] : 'ED';
                                $ed_audio  = isset( $ed['audio_url'] ) ? $ed['audio_url'] : '';
                            ?>
                                <div class="asd-music-card-v2">
                                    <div class="asd-music-type-badge"><?php echo esc_html( $ed_type ); ?></div>
                                    <div class="asd-music-body">
                                        <div class="asd-music-title"><?php echo esc_html( $ed_title ); ?></div>
                                        <?php if ( $ed_native ) : ?>
                                            <div class="asd-music-native"><?php echo esc_html( $ed_native ); ?></div>
                                        <?php endif; ?>
                                        <?php if ( $ed_artist ) : ?>
                                            <div class="asd-music-artist"><?php echo esc_html( $ed_artist ); ?></div>
                                        <?php endif; ?>
                                        <?php if ( $ed_audio ) : ?>
                                            <audio controls preload="none" class="asd-music-player">
                                                <source src="<?php echo esc_url( $ed_audio ); ?>">
                                                您的瀏覽器不支援音訊播放。
                                            </audio>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php /* ── 串流平台 — v14.5：台灣🔴 / 國際🔵 class ── */ ?>
            <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?>
                <section class="asd-section" id="asd-sec-stream">
                    <h2 class="asd-section-title">📡 串流平台</h2>
                                    <div class="asd-glass-divider"></div>
                    <?php if ( ! empty( $tw_streaming_items ) ) : ?>
                        <div class="asd-stream-region asd-stream-region--tw">台灣</div>
                        <div class="asd-stream-list">
                            <?php foreach ( $tw_streaming_items as $st ) : ?>
                                <?php if ( $st['url'] ) : ?>
                                    <a href="<?php echo esc_url( $st['url'] ); ?>" target="_blank" rel="noopener" class="asd-stream-btn">
                                        <?php echo esc_html( $st['label'] ); ?> ↗
                                    </a>
                                <?php else : ?>
                                    <span class="asd-stream-btn asd-stream-btn--no-link"><?php echo esc_html( $st['label'] ); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $streaming_list ) ) : ?>
                        <div class="asd-stream-region asd-stream-region--intl" style="margin-top:14px;">國際</div>
                        <div class="asd-stream-list">
                            <?php foreach ( $streaming_list as $st ) :
                                $st_site = isset( $st['site'] ) ? $st['site'] : '';
                                $st_url  = isset( $st['url'] ) ? $st['url'] : '';
                                if ( ! $st_site && ! $st_url ) continue;
                            ?>
                                <?php if ( $st_url ) : ?>
                                    <a href="<?php echo esc_url( $st_url ); ?>" target="_blank" rel="noopener" class="asd-stream-btn">
                                        <?php echo esc_html( $st_site ); ?> ↗
                                    </a>
                                <?php else : ?>
                                    <span class="asd-stream-btn asd-stream-btn--no-link"><?php echo esc_html( $st_site ); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php /* ── FAQ ── */ ?>
            <?php if ( ! empty( $faq_items ) ) : ?>
                <section class="asd-section" id="asd-sec-faq">
                    <h2 class="asd-section-title">❓ 常見問題</h2>
                                    <div class="asd-glass-divider"></div>
                    <div class="asd-faq-list">
                        <?php foreach ( $faq_items as $f ) :
                            if ( empty( $f['q'] ) || empty( $f['a'] ) ) continue;
                        ?>
                            <div class="asd-faq-item">
                                <div class="asd-faq-question">
                                    <?php echo esc_html( $f['q'] ); ?>
                                    <span class="asd-faq-icon">＋</span>
                                </div>
                                <div class="asd-faq-answer"><?php echo wp_kses_post( $f['a'] ); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php /* ── 外部連結 ── */ ?>
            <?php if ( $official_site || $twitter_url || $wikipedia_url || $tiktok_url || $anilist_id || $mal_id || $bangumi_id ) : ?>
                <section class="asd-section" id="asd-sec-links">
                    <h2 class="asd-section-title">🔗 外部連結</h2>
                                    <div class="asd-glass-divider"></div>
                    <div class="asd-ext-links-grid">
                        <?php if ( $anilist_id ) : ?>
                            <a href="https://anilist.co/anime/<?php echo esc_attr( $anilist_id ); ?>/" target="_blank" rel="noopener" class="asd-ext-link-card asd-ext--al">
                                <span class="asd-ext-site">AniList</span><span class="asd-ext-arrow">↗</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $mal_id ) : ?>
                            <a href="https://myanimelist.net/anime/<?php echo esc_attr( $mal_id ); ?>/" target="_blank" rel="noopener" class="asd-ext-link-card asd-ext--mal">
                                <span class="asd-ext-site">MyAnimeList</span><span class="asd-ext-arrow">↗</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $bangumi_id ) : ?>
                            <a href="https://bgm.tv/subject/<?php echo esc_attr( $bangumi_id ); ?>/" target="_blank" rel="noopener" class="asd-ext-link-card asd-ext--bgm">
                                <span class="asd-ext-site">Bangumi</span><span class="asd-ext-arrow">↗</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $official_site ) : ?>
                            <a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener" class="asd-ext-link-card">
                                <span class="asd-ext-site">🌐 官方網站</span><span class="asd-ext-arrow">↗</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $twitter_url ) : ?>
                            <a href="<?php echo esc_url( $twitter_url ); ?>" target="_blank" rel="noopener" class="asd-ext-link-card">
                                <span class="asd-ext-site">𝕏 Twitter</span><span class="asd-ext-arrow">↗</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $wikipedia_url ) : ?>
                            <a href="<?php echo esc_url( $wikipedia_url ); ?>" target="_blank" rel="noopener" class="asd-ext-link-card">
                                <span class="asd-ext-site">📖 Wikipedia</span><span class="asd-ext-arrow">↗</span>
                            </a>
                        <?php endif; ?>
                        <?php if ( $tiktok_url ) : ?>
                            <a href="<?php echo esc_url( $tiktok_url ); ?>" target="_blank" rel="noopener" class="asd-ext-link-card">
                                <span class="asd-ext-site">🎵 TikTok</span><span class="asd-ext-arrow">↗</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
<!-- 最後更新 -->
<div class="asd-last-updated">
    <span>最後更新：<?php echo get_the_modified_date('Y-m-d'); ?></span>
</div>
            <?php /* ── 留言 ── */ ?>
            <section class="asd-section asd-comments" id="comments">
                <h2 class="asd-section-title">💬 留言</h2>
                <div class="asd-comment-box">
                    <?php comments_template(); ?>
                </div>
            </section>

        </main><!-- /.asd-main -->

        <?php if ( $has_sidebar_content ) : ?>
            <aside class="asd-sidebar" id="asd-sidebar">

                <?php if ( ! empty( $site_relations ) ) : ?>
                    <div class="asd-side-section">
                        <div class="asd-side-section__head"><h3>🔗 相關作品</h3></div>
                        <div class="asd-side-cards">
                            <?php foreach ( $site_relations as $rel ) : ?>
                                <a href="<?php echo esc_url( $rel['url'] ); ?>" class="asd-mini-card">
                                    <div class="asd-mini-card__thumb">
                                        <?php if ( $rel['cover_image'] ) : ?>
                                            <img src="<?php echo esc_url( $rel['cover_image'] ); ?>"
                                                 alt="<?php echo esc_attr( $rel['title_zh'] ); ?>" loading="lazy">
                                        <?php endif; ?>
                                    </div>
                                    <div class="asd-mini-card__body">
                                        <div class="asd-mini-card__title"><?php echo esc_html( $rel['title_zh'] ); ?></div>
                                        <div class="asd-mini-card__meta">
                                            <?php if ( $rel['relation_label'] ) echo esc_html( $rel['relation_label'] ); ?>
                                            <?php if ( $rel['format'] ) echo ' · ' . esc_html( $rel['format'] ); ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $genre_terms ) || ! empty( $season_child_terms ) ) : ?>
                    <div class="asd-side-section">
                        <div class="asd-side-section__head"><h3>🏷️ 作品標籤</h3></div>
                        <div class="asd-tags-wrap" style="margin-top:16px;">
                            <?php if ( ! empty( $season_child_terms ) ) : ?>
                                <div class="asd-tags-row">
                                    <span class="asd-tags-row-label">季度</span>
                                    <div class="asd-tags-list">
                                        <?php foreach ( $season_child_terms as $sterm ) : ?>
                                            <a href="<?php echo esc_url( get_term_link( $sterm ) ); ?>" class="asd-tag-item asd-tag-item--season">
                                                <?php echo esc_html( $sterm->name ); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
<?php if ( ! empty( $genre_terms ) ) : ?>
    <div class="asd-tags-row">
        <span class="asd-tags-row-label">類型</span>
        <div class="asd-tags-list">
            <?php foreach ( $genre_terms as $gterm ) : ?>
                <a href="<?php echo esc_url( get_term_link( $gterm ) ); ?>" class="asd-tag-item asd-tag-item--genre">
                    <?php echo esc_html( $gterm->name ); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php if ( $studio ) : ?>
    <div class="asd-tags-row">
        <span class="asd-tags-row-label">製作</span>
        <div class="asd-tags-list">
            <span class="asd-tag-item"><?php echo esc_html( $studio ); ?></span>
        </div>
    </div>
<?php endif; ?>
        </div>
    </div>
<?php endif; ?>

                <?php if ( ! empty( $news_items ) ) : ?>
                    <div class="asd-side-section">
                        <div class="asd-side-section__head"><h3>📰 相關新聞</h3></div>
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

                <?php if ( $affiliate_html ) : ?>
                    <div class="asd-side-section">
                        <div class="asd-side-section__head"><h3>🛒 購買連結</h3></div>
                        <div class="asd-affiliate-box"><?php echo wp_kses_post( $affiliate_html ); ?></div>
                    </div>
                <?php endif; ?>

            </aside>
        <?php endif; ?>

    </div><!-- /.asd-container -->

</div><!-- /.asd-wrap -->

<script>
(function () {

    /* ── 集數展開 ── */
    var epToggle = document.getElementById('asd-ep-toggle');
    if (epToggle) {
        epToggle.addEventListener('click', function () {
            var hidden = document.querySelectorAll('.asd-ep-hidden');
            if (hidden.length) {
                hidden.forEach(function (el) { el.classList.remove('asd-ep-hidden'); });
                epToggle.textContent = '收起 ▲';
            } else {
                var rows = document.querySelectorAll('.asd-ep-row');
                rows.forEach(function (el, i) { if (i >= 3) el.classList.add('asd-ep-hidden'); });
                epToggle.textContent = '顯示全部 <?php echo count( $episodes_list ); ?> 集 ▼';
            }
        });
    }

    /* ── STAFF 展開 ── */
    var staffToggle = document.getElementById('asd-staff-toggle');
    if (staffToggle) {
        staffToggle.addEventListener('click', function () {
            var hidden = document.querySelectorAll('.asd-staff-hidden');
            if (hidden.length) {
                hidden.forEach(function (el) { el.classList.remove('asd-staff-hidden'); });
                staffToggle.textContent = '收起 ▲';
            } else {
                var cards = document.querySelectorAll('.asd-staff-card-v2');
                cards.forEach(function (el, i) { if (i >= 16) el.classList.add('asd-staff-hidden'); });
                staffToggle.textContent = '顯示全部 <?php echo count( $staff_list ); ?> 位人員 ▼';
            }
        });
    }

    /* ── CAST 展開 ── */
    var castToggle = document.getElementById('asd-cast-toggle');
    if (castToggle) {
        castToggle.addEventListener('click', function () {
            var hidden = document.querySelectorAll('.asd-cast-hidden');
            if (hidden.length) {
                hidden.forEach(function (el) { el.classList.remove('asd-cast-hidden'); });
                castToggle.textContent = '收起 ▲';
            } else {
                var cards = document.querySelectorAll('.asd-cast-card-v2');
                cards.forEach(function (el, i) { if (i >= 8) el.classList.add('asd-cast-hidden'); });
                castToggle.textContent = '顯示全部配音員 ▼';
            }
        });
    }

    /* ── FAQ 展開/收起 ── */
    document.querySelectorAll('.asd-faq-question').forEach(function (q) {
        q.addEventListener('click', function () {
            var item = q.parentElement;
            item.classList.toggle('is-open');
            q.querySelector('.asd-faq-icon').textContent = item.classList.contains('is-open') ? '－' : '＋';
        });
    });

    /* ── 倒數計時 ── */
    document.querySelectorAll('.asd-countdown').forEach(function (el) {
        var ts = parseInt(el.dataset.ts, 10);
        if (!ts) return;
        function update() {
            var diff = ts - Math.floor(Date.now() / 1000);
            if (diff <= 0) { el.textContent = '已播出'; return; }
            var d = Math.floor(diff / 86400);
            var h = Math.floor((diff % 86400) / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;
            el.textContent = (d ? d + '天 ' : '') + h + '時 ' + m + '分 ' + s + '秒';
        }
        update();
        setInterval(update, 1000);
    });

    /* ── Tab active on scroll ── */
    var tabs = document.querySelectorAll('.asd-tab[href^="#"]');
    var sections = [];
    tabs.forEach(function (tab) {
        var id = tab.getAttribute('href').replace('#', '');
        var sec = document.getElementById(id);
        if (sec) sections.push({ tab: tab, sec: sec });
    });

    function setActive() {
        var scrollY = window.scrollY + 140;
        var current = sections[0];
        sections.forEach(function (item) {
            if (item.sec.offsetTop <= scrollY) current = item;
        });
        tabs.forEach(function (t) { t.classList.remove('is-active'); });
        if (current) current.tab.classList.add('is-active');
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
        });
    });

    window.addEventListener('scroll', setActive, { passive: true });
    setActive();

})();
</script>

<?php
endwhile;
get_footer();

<?php
/**
 * Single Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/single-anime.php
 *
 * @package Anime_Sync_Pro
 * @version 15.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

wp_enqueue_style(
    'anime-sync-single',
    plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/anime-single.css',
    array(),
    '15.0'
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
        $title = ''; $url = '';
        if ( ! empty( $item['title'] ) )        $title = $item['title'];
        elseif ( ! empty( $item['name'] ) )     $title = $item['name'];
        elseif ( ! empty( $item['headline'] ) ) $title = $item['headline'];
        if ( ! empty( $item['url'] ) )          $url = $item['url'];
        elseif ( ! empty( $item['link'] ) )     $url = $item['link'];
        $title = trim( (string) $title );
        $url   = trim( (string) $url );
        if ( $title === '' ) return null;
        return array( 'title' => $title, 'url' => $url );
    };

    /* ── IDs ── */
    $anilist_id = (int) $get_meta( 'anime_anilist_id', 0 );
    $mal_id     = (int) $get_meta( 'anime_mal_id', 0 );
    $bangumi_id = (int) $get_meta( 'anime_bangumi_id', 0 );

    /* ── Titles ── */
    $title_chinese = $get_meta( 'anime_title_chinese' );
    $title_native  = $get_meta( 'anime_title_native' );
    $title_romaji  = $get_meta( 'anime_title_romaji' );
    $title_english = $get_meta( 'anime_title_english' );
    $display_title = $title_chinese ? $title_chinese : get_the_title();

    /* ── Basic info ── */
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

    /* ── Streaming ── */
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
        'muse' => 'Muse', 'medialink' => 'Medialink', 'jbf' => 'JBF',
        'righttime' => '右時數位', 'gaga' => 'GaGa OOLala', 'catchplay' => 'CatchPlay',
        'netflix' => 'Netflix', 'disney' => 'Disney+', 'kktv' => 'KKTV',
        'crunchyroll' => 'Crunchyroll', 'ani-one' => 'Ani-One Asia', 'other' => '',
    );
    $tw_dist_display = '';
    if ( $tw_distributor === 'other' ) {
        $tw_dist_display = $tw_dist_custom ? $tw_dist_custom : '';
    } elseif ( $tw_distributor ) {
        $tw_dist_display = isset( $tw_dist_labels[$tw_distributor] ) ? $tw_dist_labels[$tw_distributor] : $tw_distributor;
    }
    $tw_stream_labels = array(
        'bahamut' => '巴哈姆特動畫瘋', 'netflix' => 'Netflix', 'disney' => 'Disney+',
        'amazon' => 'Amazon Prime Video', 'kktv' => 'KKTV', 'friday' => 'friDay影音',
        'catchplay' => 'CatchPlay+', 'bilibili' => 'Bilibili', 'crunchyroll' => 'Crunchyroll',
        'hulu' => 'Hulu', 'hidive' => 'HIDIVE', 'ani-one' => 'Ani-One',
        'muse' => 'Muse Asia', 'viu' => 'Viu', 'wetv' => 'WeTV', 'youtube' => 'YouTube',
    );
    $tw_streaming_items = array();
    if ( ! empty( $tw_streaming_raw ) ) {
        $raw_arr = is_array( $tw_streaming_raw ) ? $tw_streaming_raw : array( $tw_streaming_raw );
        foreach ( $raw_arr as $key ) {
            $key = trim( (string) $key );
            if ( $key === '' ) continue;
            $tw_streaming_items[] = array(
                'key'   => $key,
                'label' => isset( $tw_stream_labels[$key] ) ? $tw_stream_labels[$key] : $key,
                'url'   => isset( $tw_stream_url_map[$key] ) ? $tw_stream_url_map[$key] : '',
            );
        }
    }
    if ( $tw_streaming_other ) {
        foreach ( array_map( 'trim', explode( ',', $tw_streaming_other ) ) as $extra ) {
            if ( $extra !== '' ) $tw_streaming_items[] = array( 'key' => 'other', 'label' => $extra, 'url' => '' );
        }
    }

    /* ── Dates ── */
    $start_date = $format_date( $get_meta( 'anime_start_date' ) );
    $end_date   = $format_date( $get_meta( 'anime_end_date' ) );

    /* ── Scores ── */
    $score_anilist_raw = $get_meta( 'anime_score_anilist' );
    $score_anilist_num = is_numeric( $score_anilist_raw ) ? (float) $score_anilist_raw : 0;
    $score_anilist     = $score_anilist_num > 0 ? number_format( $score_anilist_num / 10, 1 ) : '';
    $score_mal_raw     = $get_meta( 'anime_score_mal' );
    $score_mal_num     = is_numeric( $score_mal_raw ) ? (float) $score_mal_raw : 0;
    $score_mal         = $score_mal_num > 0 ? number_format( $score_mal_num / 10, 1 ) : '';
    $score_bangumi_raw = $get_meta( 'anime_score_bangumi' );
    $score_bangumi_num = is_numeric( $score_bangumi_raw ) ? (float) $score_bangumi_raw : 0;
    $score_bangumi     = $score_bangumi_num > 0 ? number_format( $score_bangumi_num / 10, 1 ) : '';

    /* ── Images & trailer ── */
    $cover_image  = $get_meta( 'anime_cover_image' );
    $banner_image = $get_meta( 'anime_banner_image' );
    $trailer_url  = $get_meta( 'anime_trailer_url' );
    $youtube_id   = '';
    if ( $trailer_url ) {
        foreach ( preg_split( '/[,\n]+/', (string) $trailer_url ) as $t_url ) {
            $t_url = trim( $t_url );
            if ( $t_url === '' ) continue;
            if ( preg_match( '/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{11})/', $t_url, $m ) ) { $youtube_id = $m[1]; break; }
            if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $t_url ) ) { $youtube_id = $t_url; break; }
        }
    }

    /* ── Links ── */
    $official_site  = $get_meta( 'anime_official_site' );
    $twitter_url    = $get_meta( 'anime_twitter_url' );
    $wikipedia_url  = $get_meta( 'anime_wikipedia_url' );
    $tiktok_url     = $get_meta( 'anime_tiktok_url' );
    $affiliate_html = $get_meta( 'anime_affiliate_html' );

    /* ── Airing ── */
    $next_airing_raw = $get_meta( 'anime_next_airing' );
    $airing_data     = array();
    if ( $next_airing_raw ) {
        $decoded_airing = is_array( $next_airing_raw ) ? $next_airing_raw : json_decode( $next_airing_raw, true );
        if ( is_array( $decoded_airing ) ) $airing_data = $decoded_airing;
    }

    /* ── Synopsis ── */
    $synopsis_raw = $get_meta( 'anime_synopsis_chinese' );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = $get_meta( 'anime_synopsis' );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = get_the_content();
    $synopsis = trim( (string) $synopsis_raw );

    /* ── JSON lists ── */
    $streaming_list = $decode_json( $get_meta( 'anime_streaming' ) );
    $themes_list    = $decode_json( $get_meta( 'anime_themes' ) );
    $cast_list      = $decode_json( $get_meta( 'anime_cast_json' ) );
    $staff_list     = $decode_json( $get_meta( 'anime_staff_json' ) );
    $relations_list = $decode_json( $get_meta( 'anime_relations_json' ) );
    $episodes_list  = $decode_json( $get_meta( 'anime_episodes_json' ) );

    /* ── News ── */
    $news_items = $decode_json( $get_meta( 'anime_related_news_json' ) );
    if ( empty( $news_items ) ) $news_items = $decode_json( $get_meta( 'anime_news_json' ) );
    $normalized_news = array();
    foreach ( $news_items as $ni ) { $nn = $normalize_news_item( $ni ); if ( $nn ) $normalized_news[] = $nn; }
    $news_items = $normalized_news;

    /* ── Themes ── */
    $seen = array(); $openings = array(); $endings = array();
    foreach ( $themes_list as $t ) {
        $type   = strtoupper( trim( isset( $t['type'] ) ? $t['type'] : '' ) );
        $stitle = trim( isset( $t['song_title'] ) ? $t['song_title'] : ( isset( $t['title'] ) ? $t['title'] : '' ) );
        $key    = $type.'||'.$stitle;
        if ( isset( $seen[$key] ) ) continue;
        $seen[$key] = true;
        if ( $starts_with( $type, 'OP' ) )     $openings[] = $t;
        elseif ( $starts_with( $type, 'ED' ) ) $endings[]  = $t;
    }

    /* ── Labels ── */
    $season_labels  = array( 'WINTER' => '冬季', 'SPRING' => '春季', 'SUMMER' => '夏季', 'FALL' => '秋季' );
    $format_labels  = array( 'TV' => 'TV', 'TV_SHORT' => 'TV SHORT', 'MOVIE' => '劇場版', 'OVA' => 'OVA', 'ONA' => 'ONA', 'SPECIAL' => 'SPECIAL', 'MUSIC' => 'MV' );
    $status_labels  = array( 'FINISHED' => '已完結', 'RELEASING' => '連載中', 'NOT_YET_RELEASED' => '未開播', 'CANCELLED' => '已取消', 'HIATUS' => '暫停' );
    $status_classes = array( 'FINISHED' => 's-fin', 'RELEASING' => 's-rel', 'NOT_YET_RELEASED' => 's-pre', 'CANCELLED' => 's-can', 'HIATUS' => 's-hia' );
    $source_labels  = array(
        'ORIGINAL' => '原創', 'MANGA' => '漫畫改編', 'LIGHT_NOVEL' => '輕小說改編',
        'NOVEL' => '小說改編', 'VISUAL_NOVEL' => '視覺小說改編', 'VIDEO_GAME' => '遊戲改編',
        'WEB_MANGA' => '網路漫畫改編', 'BOOK' => '書籍改編', 'MUSIC' => '音樂',
        'GAME' => '遊戲改編', 'LIVE_ACTION' => '真人改編', 'MULTIMEDIA_PROJECT' => '多媒體企劃', 'OTHER' => '其他',
    );

    $season_label = isset( $season_labels[$season] ) ? $season_labels[$season] : $season;
    $format_label = isset( $format_labels[$format] ) ? $format_labels[$format] : $format;
    $status_label = isset( $status_labels[$status] ) ? $status_labels[$status] : $status;
    $status_class = isset( $status_classes[$status] ) ? $status_classes[$status] : '';
    $source_label = isset( $source_labels[$source] ) ? $source_labels[$source] : $source;

    $ep_str = '';
    if ( $episodes ) {
        $ep_str = ( $ep_aired && $ep_aired < $episodes ) ? $ep_aired.' / '.$episodes.' 集' : $episodes.' 集';
    }
    $season_str = '';
    if ( $season_year && $season_label ) $season_str = $season_year.' '.$season_label;
    elseif ( $season_year )              $season_str = (string) $season_year;

    /* ── Terms ── */
    $genre_terms        = get_the_terms( $post_id, 'genre' );
    $season_terms       = get_the_terms( $post_id, 'anime_season_tax' );
    $genre_terms        = is_array( $genre_terms ) ? $genre_terms : array();
    $season_terms       = is_array( $season_terms ) ? $season_terms : array();
    $season_child_terms = array();
    foreach ( $season_terms as $term ) { if ( ! empty( $term->parent ) ) $season_child_terms[] = $term; }

    /* ── Relations ── */
    $site_relations = array();
    if ( ! empty( $relations_list ) ) {
        foreach ( $relations_list as $rel ) {
            $rel_id = isset( $rel['anilist_id'] ) ? (int) $rel['anilist_id'] : ( isset( $rel['id'] ) ? (int) $rel['id'] : 0 );
            if ( ! $rel_id ) continue;
            $qr = get_posts( array( 'post_type' => 'anime', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true, 'meta_query' => array( array( 'key' => 'anime_anilist_id', 'value' => $rel_id, 'type' => 'NUMERIC' ) ) ) );
            if ( ! empty( $qr ) ) {
                $rp = $qr[0];
                $rl = array( 'PREQUEL' => '前傳', 'SEQUEL' => '續集', 'PARENT' => '主線', 'SIDE_STORY' => '外傳', 'CHARACTER' => '角色客串', 'SUMMARY' => '總集篇', 'ALTERNATIVE' => '替代版本', 'SPIN_OFF' => '衍生作', 'OTHER' => '其他', 'SOURCE' => '原作', 'COMPILATION' => '合輯', 'CONTAINS' => '收錄', 'ANIME' => '動畫' );
                $rk = isset( $rel['relation_label'] ) ? $rel['relation_label'] : ( isset( $rel['type'] ) ? $rel['type'] : '' );
                $site_relations[] = array(
                    'title_zh'       => get_post_meta( $rp->ID, 'anime_title_chinese', true ) ?: ( isset( $rel['title_zh'] ) ? $rel['title_zh'] : ( isset( $rel['title'] ) ? $rel['title'] : '' ) ),
                    'title_native'   => isset( $rel['title_native'] ) ? $rel['title_native'] : ( isset( $rel['native'] ) ? $rel['native'] : '' ),
                    'relation_label' => isset( $rl[$rk] ) ? $rl[$rk] : $rk,
                    'format'         => isset( $rel['format'] ) ? $rel['format'] : '',
                    'cover_image'    => get_post_meta( $rp->ID, 'anime_cover_image', true ) ?: ( isset( $rel['cover_image'] ) ? $rel['cover_image'] : '' ),
                    'url'            => get_permalink( $rp->ID ),
                );
            }
        }
    }

    /* ── Schema ── */
    $schema_type        = ( $format === 'MOVIE' ) ? 'Movie' : ( ( $format === 'MUSIC' ) ? 'MusicVideoObject' : 'TVSeries' );
    $schema_genres      = array();
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
    if ( $score_anilist_num > 0 )      $schema['aggregateRating'] = array( '@type' => 'AggregateRating', 'ratingValue' => number_format( $score_anilist_num / 10, 1 ), 'bestRating' => '10', 'worstRating' => '1', 'ratingCount' => max( 1, $popularity ) );
    if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) $schema['potentialAction'] = array( '@type' => 'WatchAction', 'target' => get_permalink( $post_id ).'#asd-sec-stream' );
    $breadcrumb_schema = array(
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => array(
            array( '@type' => 'ListItem', 'position' => 1, 'name' => '首頁', 'item' => home_url( '/' ) ),
            array( '@type' => 'ListItem', 'position' => 2, 'name' => '動畫', 'item' => home_url( '/anime/' ) ),
            array( '@type' => 'ListItem', 'position' => 3, 'name' => $display_title, 'item' => get_permalink( $post_id ) ),
        ),
    );

    /* ── FAQ ── */
    $faq_items = array(); $faq_schema = null;
    $faq_json_raw = $get_meta( 'anime_faq_json' );
    if ( $faq_json_raw ) { $faq_decoded = json_decode( $faq_json_raw, true ); if ( is_array( $faq_decoded ) ) $faq_items = $faq_decoded; }
    if ( ! empty( $faq_items ) ) {
        $fsm = array();
        foreach ( $faq_items as $f ) {
            if ( empty( $f['q'] ) || empty( $f['a'] ) ) continue;
            $fsm[] = array( '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $f['a'] ) ) );
        }
        if ( ! empty( $fsm ) ) $faq_schema = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $fsm );
    }

    /* ── Cast（正確讀取角色名 + 聲優名）── */
    $cast_main = array();
    foreach ( $cast_list as $c ) {
        $role = isset( $c['role'] ) ? strtoupper( trim( $c['role'] ) ) : '';
        if ( $role === 'MAIN' ) $cast_main[] = $c;
    }
    if ( empty( $cast_main ) ) $cast_main = array_slice( $cast_list, 0, 10 );

    $poster_fallback     = $fallback_text( $display_title, 2 );
    $has_sidebar_content = true; /* 側邊欄永遠顯示 */

?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<script type="application/ld+json"><?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php if ( $faq_schema ) : ?>
<script type="application/ld+json"><?php echo wp_json_encode( $faq_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php endif; ?>

<div class="asd-wrap">

<?php /* ── Banner ── */ ?>
<?php if ( $banner_image ) : ?>
<div class="asd-banner" style="background-image:url(<?php echo esc_url( $banner_image ); ?>)"><div class="asd-banner-fade"></div></div>
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

<?php /* ── Hero（3 欄：封面 | 主資訊 | 右邊欄）── */ ?>
<div class="asd-hero-new">

    <?php if ( $banner_image || $cover_image ) : ?>
    <div class="asd-hero-bg-img" style="background-image:url(<?php echo esc_url( $banner_image ? $banner_image : $cover_image ); ?>)"></div>
    <?php endif; ?>

    <?php /* 封面圖 */ ?>
    <div class="asd-hero-poster">
        <?php if ( $cover_image ) : ?>
            <img src="<?php echo esc_url( $cover_image ); ?>"
                 alt="<?php echo esc_attr( $display_title ); ?> 封面"
                 class="asd-poster-img" loading="eager"
                 onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="asd-poster-fallback" style="display:none"><span><?php echo esc_html( $poster_fallback ); ?></span></div>
        <?php elseif ( has_post_thumbnail() ) : ?>
            <?php echo get_the_post_thumbnail( $post_id, 'large', array( 'class' => 'asd-poster-img', 'loading' => 'eager', 'alt' => $display_title.' 封面' ) ); ?>
        <?php else : ?>
            <div class="asd-poster-fallback"><span><?php echo esc_html( $poster_fallback ); ?></span></div>
        <?php endif; ?>
    </div>

    <?php /* 主資訊欄 */ ?>
    <div class="asd-hero-body">
        <div class="asd-hero-kicker">
            <span>動畫</span>
            <?php if ( $season_str ) : ?><span class="asd-hbc-sep">·</span><span><?php echo esc_html( $season_str ); ?></span><?php endif; ?>
            <?php if ( ! empty( $genre_terms ) ) : ?><span class="asd-hbc-sep">·</span><span><?php echo esc_html( $genre_terms[0]->name ); ?></span><?php endif; ?>
        </div>

        <h1 class="asd-hero-title"><?php echo esc_html( $display_title ); ?></h1>

        <?php if ( $title_native ) : ?><p class="asd-hero-native"><?php echo esc_html( $title_native ); ?></p><?php endif; ?>
        <?php if ( $title_romaji && $title_romaji !== $title_native ) : ?><p class="asd-hero-native asd-hero-romaji"><?php echo esc_html( $title_romaji ); ?></p><?php endif; ?>

        <?php
        $series_tax_terms = get_the_terms( $post_id, 'anime_series_tax' );
        if ( ! empty( $series_tax_terms ) && ! is_wp_error( $series_tax_terms ) ) :
            $st = $series_tax_terms[0]; $stu = get_term_link( $st );
            if ( $st->count >= 2 && ! is_wp_error( $stu ) ) :
        ?>
        <a href="<?php echo esc_url( $stu ); ?>" class="asd-series-entry-badge">🔗 <?php echo esc_html( $st->name ); ?> 系列</a>
        <?php endif; endif; ?>

        <div class="asd-hero-badges">
            <?php if ( $status_label ) echo '<span class="asd-hbadge'.( $status_class ? ' asd-hbadge--'.esc_attr( $status_class ) : '' ).'">'.esc_html( $status_label ).'</span>'; ?>
            <?php if ( $format_label ) echo '<span class="asd-hbadge">'.esc_html( $format_label ).'</span>'; ?>
            <?php if ( $season_str )   echo '<span class="asd-hbadge">'.esc_html( $season_str ).'</span>'; ?>
            <?php if ( $ep_str )       echo '<span class="asd-hbadge">'.esc_html( $ep_str ).'</span>'; ?>
            <?php foreach ( array_slice( $genre_terms, 0, 3 ) as $gt ) echo '<span class="asd-hbadge asd-hbadge--genre">'.esc_html( $gt->name ).'</span>'; ?>
        </div>

        <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
        <div class="asd-hero-scores">
            <?php if ( $score_anilist ) : ?>
            <a class="asd-score-pill asd-score-pill--al" <?php if ( $anilist_id ) echo 'href="https://anilist.co/anime/'.esc_attr( $anilist_id ).'/" target="_blank" rel="noopener"'; ?>>
                <strong><?php echo esc_html( $score_anilist ); ?></strong><small>AniList</small>
            </a>
            <?php endif; ?>
            <?php if ( $score_mal ) : ?>
            <a class="asd-score-pill asd-score-pill--mal" <?php if ( $mal_id ) echo 'href="https://myanimelist.net/anime/'.esc_attr( $mal_id ).'/" target="_blank" rel="noopener"'; ?>>
                <strong><?php echo esc_html( $score_mal ); ?></strong><small>MAL</small>
            </a>
            <?php endif; ?>
            <?php if ( $score_bangumi ) : ?>
            <a class="asd-score-pill asd-score-pill--bgm" <?php if ( $bangumi_id ) echo 'href="https://bgm.tv/subject/'.esc_attr( $bangumi_id ).'/" target="_blank" rel="noopener"'; ?>>
                <strong><?php echo esc_html( $score_bangumi ); ?></strong><small>Bangumi</small>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="asd-hero-actions">
            <?php if ( $youtube_id ) : ?><a href="#asd-sec-trailer" class="asd-action-btn asd-action-btn--primary">▶ 觀看預告</a><?php endif; ?>
            <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?><a href="#asd-sec-stream" class="asd-action-btn asd-action-btn--ghost">🎬 線上觀看</a><?php endif; ?>
            <?php if ( $official_site ) : ?><a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener" class="asd-action-btn asd-action-btn--ghost">🔗 官方網站</a><?php endif; ?>
            <?php if ( $twitter_url ) : ?><a href="<?php echo esc_url( $twitter_url ); ?>" target="_blank" rel="noopener" class="asd-action-btn asd-action-btn--ghost">🐦 Twitter</a><?php endif; ?>
        </div>
    </div>

    <?php /* 右邊欄 */ ?>
    <div class="asd-hero-sidebar">
        <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
        <div class="asd-hside-block">
            <div class="asd-hside-title">評分</div>
            <?php if ( $score_anilist ) : ?><div class="asd-hside-row"><span class="asd-hside-dot" style="background:var(--asd-score-al)"></span><span class="asd-hside-key">AniList</span><span class="asd-hside-val"><?php echo esc_html( $score_anilist ); ?></span></div><?php endif; ?>
            <?php if ( $score_mal )     : ?><div class="asd-hside-row"><span class="asd-hside-dot" style="background:var(--asd-score-mal)"></span><span class="asd-hside-key">MAL</span><span class="asd-hside-val"><?php echo esc_html( $score_mal ); ?></span></div><?php endif; ?>
            <?php if ( $score_bangumi ) : ?><div class="asd-hside-row"><span class="asd-hside-dot" style="background:var(--asd-score-bgm)"></span><span class="asd-hside-key">Bangumi</span><span class="asd-hside-val"><?php echo esc_html( $score_bangumi ); ?></span></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="asd-hside-block">
            <?php
            $meta_rows = array(
                '集數'     => $ep_str,
                '每集時長' => $duration ? $duration.' 分鐘' : '',
                '原作'     => $source_label,
                '季度'     => $season_str,
                '製作公司' => $studio,
                '代理'     => $tw_dist_display,
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

        <?php if ( $anilist_id || $mal_id || $bangumi_id || $official_site || $wikipedia_url || $twitter_url ) : ?>
        <div class="asd-hside-block">
            <div class="asd-hside-title">外部連結</div>
            <div class="asd-hside-ext-links">
                <?php if ( $anilist_id )    echo '<a href="https://anilist.co/anime/'.esc_attr( $anilist_id ).'/" target="_blank" rel="noopener" class="asd-hside-ext-btn asd-ext--al">AniList ↗</a>'; ?>
                <?php if ( $mal_id )        echo '<a href="https://myanimelist.net/anime/'.esc_attr( $mal_id ).'/" target="_blank" rel="noopener" class="asd-hside-ext-btn asd-ext--mal">MAL ↗</a>'; ?>
                <?php if ( $bangumi_id )    echo '<a href="https://bgm.tv/subject/'.esc_attr( $bangumi_id ).'/" target="_blank" rel="noopener" class="asd-hside-ext-btn asd-ext--bgm">Bangumi ↗</a>'; ?>
                <?php if ( $wikipedia_url ) echo '<a href="'.esc_url( $wikipedia_url ).'" target="_blank" rel="noopener" class="asd-hside-ext-btn">Wikipedia ↗</a>'; ?>
                <?php if ( $twitter_url )   echo '<a href="'.esc_url( $twitter_url ).'" target="_blank" rel="noopener" class="asd-hside-ext-btn">Twitter ↗</a>'; ?>
                <?php if ( $official_site ) echo '<a href="'.esc_url( $official_site ).'" target="_blank" rel="noopener" class="asd-hside-ext-btn">官方網站 ↗</a>'; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.asd-hero-new -->

<?php /* ── Tabs ── */ ?>
<nav class="asd-tabs" id="asd-tabs" aria-label="內容導覽">
    <a class="asd-tab" href="#asd-sec-info">📋 基本資訊</a>
    <?php if ( $synopsis ) : ?><a class="asd-tab" href="#asd-sec-synopsis">📝 劇情</a><?php endif; ?>
    <?php if ( $youtube_id ) : ?><a class="asd-tab" href="#asd-sec-trailer">🎞 預告片</a><?php endif; ?>
    <?php if ( ! empty( $episodes_list ) ) : ?><a class="asd-tab" href="#asd-sec-episodes">📺 集數</a><?php endif; ?>
    <?php if ( ! empty( $staff_list ) ) : ?><a class="asd-tab" href="#asd-sec-staff">🎬 STAFF</a><?php endif; ?>
    <?php if ( ! empty( $cast_main ) ) : ?><a class="asd-tab" href="#asd-sec-cast">🎭 CAST</a><?php endif; ?>
    <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?><a class="asd-tab" href="#asd-sec-music">🎵 主題曲</a><?php endif; ?>
    <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?><a class="asd-tab" href="#asd-sec-stream">📡 串流</a><?php endif; ?>
    <?php if ( ! empty( $faq_items ) ) : ?><a class="asd-tab" href="#asd-sec-faq">❓ FAQ</a><?php endif; ?>
    <?php if ( $anilist_id || $mal_id || $bangumi_id || $official_site ) : ?><a class="asd-tab" href="#asd-sec-links">🔗 連結</a><?php endif; ?>
    <a class="asd-tab" href="#comments">💬 留言</a>
</nav>

<?php /* ── 主體（左 70% 內容 + 右 30% 側邊欄）── */ ?>
<div class="asd-container asd-container--has-sidebar">

    <main class="asd-main" id="asd-main">

        <?php /* 基本資訊 */ ?>
        <section class="asd-section" id="asd-sec-info">
            <h2 class="asd-section-title">📋 基本資訊</h2>
            <div class="asd-info-grid">
            <?php
            $info_rows = array(
                '類型'     => $format_label,
                '集數'     => $ep_str,
                '狀態'     => $status_label,
                '播出季度' => $season_str,
                '每集時長' => $duration ? $duration.' 分鐘' : '',
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

            <?php if ( ! empty( $genre_terms ) || ! empty( $season_child_terms ) ) : ?>
            <div class="asd-tags-wrap" style="margin-top:16px">
                <?php if ( ! empty( $season_child_terms ) ) : ?>
                <div class="asd-tags-row">
                    <span class="asd-tags-row-label">季度</span>
                    <div class="asd-tags-list">
                        <?php foreach ( $season_child_terms as $t ) : $tu = get_term_link( $t ); ?>
                        <?php if ( ! is_wp_error( $tu ) ) echo '<a href="'.esc_url( $tu ).'" class="asd-tag-item asd-tag-item--season">'.esc_html( $t->name ).'</a>'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ( ! empty( $genre_terms ) ) : ?>
                <div class="asd-tags-row">
                    <span class="asd-tags-row-label">類型</span>
                    <div class="asd-tags-list">
                        <?php foreach ( $genre_terms as $gt ) : $gtu = get_term_link( $gt ); ?>
                        <?php if ( ! is_wp_error( $gtu ) ) echo '<a href="'.esc_url( $gtu ).'" class="asd-tag-item asd-tag-item--genre">'.esc_html( $gt->name ).'</a>'; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $airing_data ) ) : ?>
            <div class="asd-airing-bar">
                <span>📡 下一集播出</span>
                <?php
                $ep_num  = isset( $airing_data['episode'] ) ? $airing_data['episode'] : '';
                $air_ts  = isset( $airing_data['airingAt'] ) ? (int) $airing_data['airingAt'] : 0;
                $air_str = $air_ts ? gmdate( 'Y-m-d H:i', $air_ts ) : '';
                if ( $ep_num ) echo '<span>第 '.esc_html( $ep_num ).' 集</span>';
                if ( $air_str ) echo '<span>'.esc_html( $air_str ).'（UTC）</span>';
                ?>
            </div>
            <?php endif; ?>
        </section>

        <?php /* 劇情 */ ?>
        <?php if ( $synopsis ) : ?>
        <section class="asd-section" id="asd-sec-synopsis">
            <h2 class="asd-section-title">📝 劇情簡介</h2>
            <div class="asd-synopsis"><?php echo wpautop( esc_html( $synopsis ) ); ?></div>
        </section>
        <?php endif; ?>

        <?php /* 預告片 */ ?>
        <?php if ( $youtube_id ) : ?>
        <section class="asd-section" id="asd-sec-trailer">
            <h2 class="asd-section-title">🎞 預告片</h2>
            <div class="asd-trailer-wrap">
                <iframe src="https://www.youtube.com/embed/<?php echo esc_attr( $youtube_id ); ?>?rel=0&modestbranding=1"
                        title="<?php echo esc_attr( $display_title ); ?> 預告片"
                        frameborder="0"
                        allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture"
                        allowfullscreen loading="lazy"></iframe>
            </div>
        </section>
        <?php endif; ?>

        <?php /* 集數 */ ?>
        <?php if ( ! empty( $episodes_list ) ) : ?>
        <section class="asd-section" id="asd-sec-episodes">
            <h2 class="asd-section-title">📺 集數列表</h2>
            <div class="asd-ep-list" id="asd-ep-list">
            <?php
            $ep_show_limit = 10;
            foreach ( $episodes_list as $ei => $ep ) :
                $ep_num   = isset( $ep['episode'] ) ? $ep['episode'] : ( isset( $ep['number'] ) ? $ep['number'] : ( $ei + 1 ) );
                $ep_title = isset( $ep['title_zh'] ) ? $ep['title_zh'] : ( isset( $ep['title'] ) ? $ep['title'] : '' );
                $ep_ja    = isset( $ep['title_ja'] ) ? $ep['title_ja'] : ( isset( $ep['title_native'] ) ? $ep['title_native'] : '' );
                $ep_date  = isset( $ep['air_date'] ) ? $ep['air_date'] : ( isset( $ep['airingAt'] ) ? gmdate( 'Y-m-d', (int) $ep['airingAt'] ) : '' );
                $hidden   = $ei >= $ep_show_limit ? ' asd-ep-hidden' : '';
            ?>
            <div class="asd-ep-row<?php echo esc_attr( $hidden ); ?>">
                <span class="asd-ep-num">第 <?php echo esc_html( $ep_num ); ?> 集</span>
                <div class="asd-ep-title">
                    <?php if ( $ep_title ) echo esc_html( $ep_title ); ?>
                    <?php if ( $ep_ja )    echo '<span class="asd-ep-title-ja">'.esc_html( $ep_ja ).'</span>'; ?>
                </div>
                <?php if ( $ep_date ) echo '<span class="asd-ep-date">'.esc_html( $ep_date ).'</span>'; ?>
            </div>
            <?php endforeach; ?>
            </div>
            <?php if ( count( $episodes_list ) > $ep_show_limit ) : ?>
            <button class="asd-ep-toggle" onclick="(function(btn){var rows=document.querySelectorAll('#asd-ep-list .asd-ep-hidden,#asd-ep-list .asd-ep-show');var hidden=document.querySelectorAll('#asd-ep-list .asd-ep-hidden');if(hidden.length){document.querySelectorAll('#asd-ep-list .asd-ep-row').forEach(function(r,i){if(i>='<?php echo (int) $ep_show_limit; ?>'){r.classList.remove('asd-ep-hidden');r.classList.add('asd-ep-show');}});btn.textContent='收起';}else{document.querySelectorAll('#asd-ep-list .asd-ep-show').forEach(function(r,i){r.classList.remove('asd-ep-show');r.classList.add('asd-ep-hidden');});btn.textContent='顯示全部 <?php echo count( $episodes_list ); ?> 集';}})(this)">顯示全部 <?php echo count( $episodes_list ); ?> 集</button>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php /* STAFF */ ?>
        <?php if ( ! empty( $staff_list ) ) : ?>
        <section class="asd-section" id="asd-sec-staff">
            <h2 class="asd-section-title">🎬 STAFF</h2>
            <div class="asd-staff-grid" id="asd-staff-grid">
            <?php
            $staff_limit = 8;
            foreach ( $staff_list as $si => $s ) :
                $s_name  = isset( $s['name_zh'] ) && $s['name_zh']    ? $s['name_zh']
                         : ( isset( $s['name'] ) && $s['name']         ? $s['name'] : '' );
                $s_role  = isset( $s['role_zh'] ) && $s['role_zh']    ? $s['role_zh']
                         : ( isset( $s['role'] ) && $s['role']         ? $s['role'] : '' );
                $s_img   = isset( $s['image'] ) ? $s['image'] : ( isset( $s['avatar'] ) ? $s['avatar'] : '' );
                $s_fb    = $fallback_text( $s_name, 1 );
                $hidden  = $si >= $staff_limit ? ' asd-staff-hidden' : '';
            ?>
            <div class="asd-staff-card<?php echo esc_attr( $hidden ); ?>">
                <div class="asd-staff-avatar">
                    <?php if ( $s_img ) : ?>
                        <img src="<?php echo esc_url( $s_img ); ?>" alt="<?php echo esc_attr( $s_name ); ?>"
                             loading="lazy" onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <span class="asd-staff-avatar-fb" style="display:none"><?php echo esc_html( $s_fb ); ?></span>
                    <?php else : ?>
                        <span class="asd-staff-avatar-fb"><?php echo esc_html( $s_fb ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="asd-staff-info">
                    <div class="asd-staff-name"><?php echo esc_html( $s_name ); ?></div>
                    <?php if ( $s_role ) echo '<div class="asd-staff-role">'.esc_html( $s_role ).'</div>'; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php if ( count( $staff_list ) > $staff_limit ) : ?>
            <button class="asd-staff-toggle" onclick="(function(btn){var hd=document.querySelectorAll('#asd-staff-grid .asd-staff-hidden');if(hd.length){hd.forEach(function(el){el.classList.remove('asd-staff-hidden');el.classList.add('asd-staff-show');});btn.textContent='收起';}else{document.querySelectorAll('#asd-staff-grid .asd-staff-show').forEach(function(el){el.classList.remove('asd-staff-show');el.classList.add('asd-staff-hidden');});btn.textContent='顯示全部 <?php echo count( $staff_list ); ?> 人';}})(this)">顯示全部 <?php echo count( $staff_list ); ?> 人</button>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php /* CAST */ ?>
        <?php if ( ! empty( $cast_main ) ) : ?>
        <section class="asd-section" id="asd-sec-cast">
            <h2 class="asd-section-title">🎭 CAST</h2>
            <div class="asd-cast-grid" id="asd-cast-grid">
            <?php
            $cast_limit = 10;
            foreach ( $cast_main as $ci => $c ) :
                /* ── 角色名（character name）── */
                $char_name = '';
                foreach ( array( 'character_name_zh', 'character_name', 'char_name', 'name_zh', 'character' ) as $ck ) {
                    if ( ! empty( $c[$ck] ) ) { $char_name = $c[$ck]; break; }
                }
                /* ── 聲優名（voice actor）── */
                $va_name = '';
                foreach ( array( 'voice_actor', 'voice_actor_name', 'va', 'va_name', 'voice_actor_zh', 'actor', 'actor_name', 'seiyuu' ) as $vk ) {
                    if ( ! empty( $c[$vk] ) ) { $va_name = $c[$vk]; break; }
                }
                /* ── 如果角色名和聲優名都沒找到，用 name 欄位 ── */
                if ( ! $char_name && ! $va_name && ! empty( $c['name'] ) ) $char_name = $c['name'];
                /* ── 頭像圖片 ── */
                $c_img = '';
                foreach ( array( 'character_image', 'image', 'avatar', 'char_image' ) as $ik ) {
                    if ( ! empty( $c[$ik] ) ) { $c_img = $c[$ik]; break; }
                }
                $c_fb   = $fallback_text( $char_name ? $char_name : ( $va_name ? $va_name : 'AN' ), 2 );
                $hidden = $ci >= $cast_limit ? ' asd-cast-hidden' : '';
            ?>
            <div class="asd-cast-card<?php echo esc_attr( $hidden ); ?>">
                <div class="asd-cast-avatar">
                    <?php if ( $c_img ) : ?>
                        <img src="<?php echo esc_url( $c_img ); ?>" alt="<?php echo esc_attr( $char_name ); ?>"
                             loading="lazy" onerror="this.onerror=null;this.style.display='none';this.parentNode.querySelector('.asd-cast-avatar-fb').style.display='flex';">
                        <span class="asd-cast-avatar-fb" style="display:none"><?php echo esc_html( $c_fb ); ?></span>
                    <?php else : ?>
                        <span class="asd-cast-avatar-fb"><?php echo esc_html( $c_fb ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="asd-cast-info">
                    <?php if ( $char_name ) echo '<div class="asd-cast-char">'.esc_html( $char_name ).'</div>'; ?>
                    <?php if ( $va_name )   echo '<div class="asd-cast-cv">CV: '.esc_html( $va_name ).'</div>'; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php if ( count( $cast_main ) > $cast_limit ) : ?>
            <button class="asd-cast-toggle" onclick="(function(btn){var hd=document.querySelectorAll('#asd-cast-grid .asd-cast-hidden');if(hd.length){hd.forEach(function(el){el.classList.remove('asd-cast-hidden');el.classList.add('asd-cast-show');});btn.textContent='收起';}else{document.querySelectorAll('#asd-cast-grid .asd-cast-show').forEach(function(el){el.classList.remove('asd-cast-show');el.classList.add('asd-cast-hidden');});btn.textContent='顯示全部 <?php echo count( $cast_main ); ?> 人';}})(this)">顯示全部 <?php echo count( $cast_main ); ?> 人</button>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php /* 主題曲 */ ?>
        <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?>
        <section class="asd-section" id="asd-sec-music">
            <h2 class="asd-section-title">🎵 主題曲</h2>
            <?php
            $render_theme = function( $themes, $group_title ) {
                if ( empty( $themes ) ) return;
                echo '<div class="asd-music-group">';
                echo '<div class="asd-music-group-title">'.esc_html( $group_title ).'</div>';
                foreach ( $themes as $t ) {
                    $type   = strtoupper( trim( isset( $t['type'] ) ? $t['type'] : '' ) );
                    $stitle = isset( $t['song_title'] ) ? $t['song_title'] : ( isset( $t['title'] ) ? $t['title'] : '' );
                    $native = isset( $t['song_title_native'] ) ? $t['song_title_native'] : ( isset( $t['native_title'] ) ? $t['native_title'] : '' );
                    $artist = isset( $t['artist'] ) ? $t['artist'] : ( isset( $t['song_artist'] ) ? $t['song_artist'] : '' );
                    $audio  = isset( $t['audio_url'] ) ? $t['audio_url'] : ( isset( $t['preview_url'] ) ? $t['preview_url'] : '' );
                    $tb     = strpos( $type, 'OP' ) !== false ? 'asd-music-type-op' : 'asd-music-type-ed';
                    echo '<div class="asd-music-card">';
                    echo '<div class="asd-music-header">';
                    echo '<span class="asd-music-type-badge '.$tb.'">'.esc_html( $type ).'</span>';
                    echo '<div class="asd-music-titles">';
                    echo '<div class="asd-music-song-title">'.esc_html( $stitle ).'</div>';
                    if ( $native ) echo '<div class="asd-music-song-native">'.esc_html( $native ).'</div>';
                    if ( $artist ) echo '<div class="asd-music-artist">'.esc_html( $artist ).'</div>';
                    echo '</div></div>';
                    if ( $audio ) echo '<audio class="asd-music-player" src="'.esc_url( $audio ).'" controls preload="none"></audio>';
                    echo '</div>';
                }
                echo '</div>';
            };
            if ( ! empty( $openings ) ) $render_theme( $openings, '片頭曲 OP' );
            if ( ! empty( $endings ) )  $render_theme( $endings,  '片尾曲 ED' );
            ?>
        </section>
        <?php endif; ?>

        <?php /* 串流 */ ?>
        <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?>
        <section class="asd-section" id="asd-sec-stream">
            <h2 class="asd-section-title">📡 串流平台</h2>
            <?php if ( ! empty( $tw_streaming_items ) ) : ?>
            <div class="asd-stream-region">
                <div class="asd-stream-region-title">🇹🇼 台灣</div>
                <div class="asd-stream-grid">
                <?php foreach ( $tw_streaming_items as $si ) :
                    $cls = $si['url'] ? '' : ' asd-stream-card--no-link';
                    $tag = $si['url'] ? 'a' : 'div';
                    $href = $si['url'] ? ' href="'.esc_url( $si['url'] ).'" target="_blank" rel="noopener noreferrer"' : '';
                ?>
                <<?php echo $tag; ?> class="asd-stream-card<?php echo esc_attr( $cls ); ?>"<?php echo $href; ?>>
                    <span class="asd-stream-label"><?php echo esc_html( $si['label'] ); ?></span>
                    <?php if ( $si['url'] ) echo '<span class="asd-stream-go">前往觀看 ↗</span>'; ?>
                </<?php echo $tag; ?>>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ( ! empty( $streaming_list ) ) : ?>
            <div class="asd-stream-region">
                <div class="asd-stream-region-title">🌐 國際</div>
                <div class="asd-stream-grid">
                <?php foreach ( $streaming_list as $si ) :
                    $label = isset( $si['site'] ) ? $si['site'] : ( isset( $si['label'] ) ? $si['label'] : '' );
                    $url   = isset( $si['url'] ) ? $si['url'] : '';
                    $tag   = $url ? 'a' : 'div';
                    $href  = $url ? ' href="'.esc_url( $url ).'" target="_blank" rel="noopener noreferrer"' : '';
                    $cls   = $url ? '' : ' asd-stream-card--no-link';
                ?>
                <<?php echo $tag; ?> class="asd-stream-card<?php echo esc_attr( $cls ); ?>"<?php echo $href; ?>>
                    <span class="asd-stream-label"><?php echo esc_html( $label ); ?></span>
                    <?php if ( $url ) echo '<span class="asd-stream-go">前往觀看 ↗</span>'; ?>
                </<?php echo $tag; ?>>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php /* FAQ */ ?>
        <?php if ( ! empty( $faq_items ) ) : ?>
        <section class="asd-section" id="asd-sec-faq">
            <h2 class="asd-section-title">❓ 常見問題</h2>
            <div class="asd-faq-list">
            <?php foreach ( $faq_items as $fi => $f ) :
                if ( empty( $f['q'] ) ) continue;
            ?>
            <div class="asd-faq-item" id="asd-faq-<?php echo (int) $fi; ?>">
                <div class="asd-faq-question" onclick="(function(el){var p=el.parentNode;p.classList.toggle('is-open');})(this)">
                    <span><?php echo esc_html( $f['q'] ); ?></span>
                    <span class="asd-faq-icon">＋</span>
                </div>
                <?php if ( ! empty( $f['a'] ) ) : ?>
                <div class="asd-faq-answer"><?php echo wpautop( esc_html( $f['a'] ) ); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php /* 外部連結 */ ?>
        <?php if ( $anilist_id || $mal_id || $bangumi_id || $official_site || $twitter_url || $wikipedia_url || $tiktok_url ) : ?>
        <section class="asd-section" id="asd-sec-links">
            <h2 class="asd-section-title">🔗 外部連結</h2>
            <div class="asd-links-grid">
                <?php if ( $anilist_id ) : ?>
                <a href="https://anilist.co/anime/<?php echo esc_attr( $anilist_id ); ?>/" target="_blank" rel="noopener" class="asd-link-card asd-link-card--al">
                    <span class="asd-link-card__site">AniList</span>
                    <span class="asd-link-card__title"><?php echo esc_html( $display_title ); ?> ↗</span>
                </a>
                <?php endif; ?>
                <?php if ( $mal_id ) : ?>
                <a href="https://myanimelist.net/anime/<?php echo esc_attr( $mal_id ); ?>/" target="_blank" rel="noopener" class="asd-link-card asd-link-card--mal">
                    <span class="asd-link-card__site">MyAnimeList</span>
                    <span class="asd-link-card__title"><?php echo esc_html( $display_title ); ?> ↗</span>
                </a>
                <?php endif; ?>
                <?php if ( $bangumi_id ) : ?>
                <a href="https://bgm.tv/subject/<?php echo esc_attr( $bangumi_id ); ?>/" target="_blank" rel="noopener" class="asd-link-card asd-link-card--bgm">
                    <span class="asd-link-card__site">Bangumi</span>
                    <span class="asd-link-card__title"><?php echo esc_html( $display_title ); ?> ↗</span>
                </a>
                <?php endif; ?>
                <?php if ( $official_site ) : ?>
                <a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener" class="asd-link-card">
                    <span class="asd-link-card__site">官方網站</span>
                    <span class="asd-link-card__title">Official Site ↗</span>
                </a>
                <?php endif; ?>
                <?php if ( $twitter_url ) : ?>
                <a href="<?php echo esc_url( $twitter_url ); ?>" target="_blank" rel="noopener" class="asd-link-card">
                    <span class="asd-link-card__site">Twitter</span>
                    <span class="asd-link-card__title">@官方帳號 ↗</span>
                </a>
                <?php endif; ?>
                <?php if ( $wikipedia_url ) : ?>
                <a href="<?php echo esc_url( $wikipedia_url ); ?>" target="_blank" rel="noopener" class="asd-link-card">
                    <span class="asd-link-card__site">Wikipedia</span>
                    <span class="asd-link-card__title"><?php echo esc_html( $display_title ); ?> ↗</span>
                </a>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php /* 留言 */ ?>
        <div class="asd-comments" id="comments">
            <div class="asd-comment-box">
                <?php comments_template(); ?>
            </div>
        </div>

    </main><!-- /.asd-main -->

    <?php /* ── 右側邊欄（30%）── */ ?>
    <aside class="asd-sidebar">

        <?php if ( $affiliate_html ) : ?>
        <div class="asd-side-section">
            <div class="asd-side-section__head"><h3>📦 周邊商品</h3></div>
            <div class="asd-affiliate-box"><?php echo $affiliate_html; ?></div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $site_relations ) ) : ?>
        <div class="asd-side-section">
            <div class="asd-side-section__head"><h3>🔗 相關作品</h3></div>
            <div class="asd-side-cards">
            <?php foreach ( $site_relations as $rel ) : ?>
            <a href="<?php echo esc_url( $rel['url'] ); ?>" class="asd-mini-card">
                <div class="asd-mini-card__thumb">
                    <?php if ( $rel['cover_image'] ) : ?>
                    <img src="<?php echo esc_url( $rel['cover_image'] ); ?>" alt="<?php echo esc_attr( $rel['title_zh'] ); ?>" loading="lazy">
                    <?php endif; ?>
                </div>
                <div class="asd-mini-card__body">
                    <p class="asd-mini-card__title"><?php echo esc_html( $rel['title_zh'] ?: $rel['title_native'] ); ?></p>
                    <p class="asd-mini-card__meta"><?php echo esc_html( $rel['relation_label'] ); ?></p>
                </div>
            </a>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $news_items ) ) : ?>
        <div class="asd-side-section">
            <div class="asd-side-section__head"><h3>📰 相關新聞</h3></div>
            <div class="asd-side-news">
            <?php foreach ( array_slice( $news_items, 0, 6 ) as $ni ) : ?>
            <?php if ( $ni['url'] ) : ?>
            <a href="<?php echo esc_url( $ni['url'] ); ?>" target="_blank" rel="noopener" class="asd-news-card">
                <p class="asd-news-card__title"><?php echo esc_html( $ni['title'] ); ?></p>
            </a>
            <?php else : ?>
            <div class="asd-news-card"><p class="asd-news-card__title"><?php echo esc_html( $ni['title'] ); ?></p></div>
            <?php endif; ?>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php /* 如果沒有任何側邊欄資料，顯示外部連結 */ ?>
        <?php if ( empty( $site_relations ) && empty( $news_items ) && ! $affiliate_html ) : ?>
        <?php if ( $anilist_id || $mal_id || $bangumi_id ) : ?>
        <div class="asd-side-section">
            <div class="asd-side-section__head"><h3>🔗 資料來源</h3></div>
            <div class="asd-hside-ext-links">
                <?php if ( $anilist_id ) echo '<a href="https://anilist.co/anime/'.esc_attr( $anilist_id ).'/" target="_blank" rel="noopener" class="asd-hside-ext-btn asd-ext--al">AniList ↗</a>'; ?>
                <?php if ( $mal_id )     echo '<a href="https://myanimelist.net/anime/'.esc_attr( $mal_id ).'/" target="_blank" rel="noopener" class="asd-hside-ext-btn asd-ext--mal">MAL ↗</a>'; ?>
                <?php if ( $bangumi_id ) echo '<a href="https://bgm.tv/subject/'.esc_attr( $bangumi_id ).'/" target="_blank" rel="noopener" class="asd-hside-ext-btn asd-ext--bgm">Bangumi ↗</a>'; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </aside>

</div><!-- /.asd-container -->

</div><!-- /.asd-wrap -->

<?php
    // Inline JS for tab active state & sticky tabs
    ?>
<script>
(function(){
    // Tabs active highlight on scroll
    var tabs = document.querySelectorAll('.asd-tab');
    var sections = [];
    tabs.forEach(function(tab){
        var href = tab.getAttribute('href');
        if(href && href.startsWith('#')){
            var el = document.querySelector(href);
            if(el) sections.push({tab:tab, el:el});
        }
    });
    function updateActive(){
        var scrollY = window.scrollY + 100;
        var current = null;
        sections.forEach(function(s){
            if(s.el.offsetTop <= scrollY) current = s;
        });
        tabs.forEach(function(t){ t.classList.remove('is-active'); });
        if(current) current.tab.classList.add('is-active');
    }
    window.addEventListener('scroll', updateActive, {passive:true});
    updateActive();

    // Smooth scroll for tab links
    tabs.forEach(function(tab){
        tab.addEventListener('click', function(e){
            var href = tab.getAttribute('href');
            if(href && href.startsWith('#')){
                var target = document.querySelector(href);
                if(target){
                    e.preventDefault();
                    target.scrollIntoView({behavior:'smooth', block:'start'});
                }
            }
        });
    });
})();
</script>

<?php
endwhile;
get_footer();

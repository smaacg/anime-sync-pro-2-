<?php
/**
 * Single Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/single-anime.php
 *
 * ACF – FAQ 改為完全人工（anime_faq_json），移除自動生成邏輯
 *       台灣串流平台加入個別連結（anime_tw_streaming_url_{key}）
 *       OP/ED 播放器改為 <audio> 標籤（audio_url OGG）
 *       集數列表「顯示全部」與 Cast「顯示全部」加入 inline JS
 *       STAFF 欄位讀取 name（Bangumi 來源）
 * ACG – 集數標題 name_cn 加入繁體轉換
 *       集數列表按鈕加入收起功能
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

wp_enqueue_style(
    'anime-sync-single',
    plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/anime-single.css',
    [],
    '1.1.7'
);

get_header();

while ( have_posts() ) : the_post();
    $post_id = get_the_ID();

    /* ═══════════════════════════════════════════════════════════
       META 讀取
    ═══════════════════════════════════════════════════════════ */

    $anilist_id = (int) get_post_meta( $post_id, 'anime_anilist_id', true );
    $mal_id     = (int) get_post_meta( $post_id, 'anime_mal_id',     true );
    $bangumi_id = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );

    $title_chinese = get_post_meta( $post_id, 'anime_title_chinese', true );
    $title_native  = get_post_meta( $post_id, 'anime_title_native',  true );
    $title_romaji  = get_post_meta( $post_id, 'anime_title_romaji',  true );
    $title_english = get_post_meta( $post_id, 'anime_title_english', true );
    $display_title = $title_chinese ?: get_the_title();

    $format      = get_post_meta( $post_id, 'anime_format',      true );
    $status      = get_post_meta( $post_id, 'anime_status',      true );
    $season      = get_post_meta( $post_id, 'anime_season',      true );
    $season_year = (int) get_post_meta( $post_id, 'anime_season_year', true );
    $episodes    = (int) get_post_meta( $post_id, 'anime_episodes',    true );
    $ep_aired    = (int) get_post_meta( $post_id, 'anime_episodes_aired', true );
    $duration    = (int) get_post_meta( $post_id, 'anime_duration',    true );
    $source      = get_post_meta( $post_id, 'anime_source',      true );
    $studio      = get_post_meta( $post_id, 'anime_studios',     true );
    $popularity  = (int) get_post_meta( $post_id, 'anime_popularity',  true );

    $tw_streaming_raw   = get_post_meta( $post_id, 'anime_tw_streaming',          true );
    $tw_streaming_other = get_post_meta( $post_id, 'anime_tw_streaming_other',    true );
    $tw_distributor     = get_post_meta( $post_id, 'anime_tw_distributor',        true );
    $tw_dist_custom     = get_post_meta( $post_id, 'anime_tw_distributor_custom', true );
    $tw_broadcast       = get_post_meta( $post_id, 'anime_tw_broadcast',          true );

    // ACF：讀取 16 個平台個別 URL
    $tw_stream_url_map = [
        'bahamut'    => get_post_meta( $post_id, 'anime_tw_streaming_url_bahamut',    true ),
        'netflix'    => get_post_meta( $post_id, 'anime_tw_streaming_url_netflix',    true ),
        'disney'     => get_post_meta( $post_id, 'anime_tw_streaming_url_disney',     true ),
        'amazon'     => get_post_meta( $post_id, 'anime_tw_streaming_url_amazon',     true ),
        'kktv'       => get_post_meta( $post_id, 'anime_tw_streaming_url_kktv',       true ),
        'friday'     => get_post_meta( $post_id, 'anime_tw_streaming_url_friday',     true ),
        'catchplay'  => get_post_meta( $post_id, 'anime_tw_streaming_url_catchplay',  true ),
        'bilibili'   => get_post_meta( $post_id, 'anime_tw_streaming_url_bilibili',   true ),
        'crunchyroll'=> get_post_meta( $post_id, 'anime_tw_streaming_url_crunchyroll',true ),
        'hulu'       => get_post_meta( $post_id, 'anime_tw_streaming_url_hulu',       true ),
        'hidive'     => get_post_meta( $post_id, 'anime_tw_streaming_url_hidive',     true ),
        'ani-one'    => get_post_meta( $post_id, 'anime_tw_streaming_url_ani_one',    true ),
        'muse'       => get_post_meta( $post_id, 'anime_tw_streaming_url_muse',       true ),
        'viu'        => get_post_meta( $post_id, 'anime_tw_streaming_url_viu',        true ),
        'wetv'       => get_post_meta( $post_id, 'anime_tw_streaming_url_wetv',       true ),
        'youtube'    => get_post_meta( $post_id, 'anime_tw_streaming_url_youtube',    true ),
    ];

    $tw_dist_labels = [
        'muse'       => '木棉花（Muse）', 'medialink' => '曼迪傳播（Medialink）',
        'jbf'        => '日本橋文化（JBF）', 'righttime' => '正確時間',
        'gaga'       => 'GaGa OOLala', 'catchplay' => 'CatchPlay',
        'netflix'    => 'Netflix 台灣', 'disney'    => 'Disney+ 台灣',
        'kktv'       => 'KKTV', 'crunchyroll' => 'Crunchyroll',
        'ani-one'    => 'Ani-One Asia', 'other' => '',
    ];
    $tw_dist_display = '';
    if ( $tw_distributor === 'other' ) {
        $tw_dist_display = $tw_dist_custom ?: '';
    } elseif ( $tw_distributor ) {
        $tw_dist_display = $tw_dist_labels[ $tw_distributor ] ?? $tw_distributor;
    }

    $tw_stream_labels = [
        'bahamut'    => '巴哈姆特動畫瘋', 'netflix'    => 'Netflix',
        'disney'     => 'Disney+',        'amazon'     => 'Amazon Prime Video',
        'kktv'       => 'KKTV',           'friday'     => 'friDay 影音',
        'catchplay'  => 'CatchPlay+',     'bilibili'   => 'Bilibili 台灣',
        'crunchyroll'=> 'Crunchyroll',    'hulu'       => 'Hulu',
        'hidive'     => 'HIDIVE',         'ani-one'    => 'Ani-One',
        'muse'       => 'Muse 木棉花',    'viu'        => 'Viu',
        'wetv'       => 'WeTV',           'youtube'    => 'YouTube（官方頻道）',
    ];

    // 建立台灣串流平台清單（含個別 URL）
    $tw_streaming_items = [];
    if ( ! empty( $tw_streaming_raw ) ) {
        $raw_arr = is_array( $tw_streaming_raw ) ? $tw_streaming_raw : [ $tw_streaming_raw ];
        foreach ( $raw_arr as $key ) {
            $tw_streaming_items[] = [
                'label' => $tw_stream_labels[ $key ] ?? $key,
                'url'   => $tw_stream_url_map[ $key ] ?? '',
            ];
        }
    }
    if ( $tw_streaming_other ) {
        foreach ( array_map( 'trim', explode( ',', $tw_streaming_other ) ) as $extra ) {
            if ( $extra ) $tw_streaming_items[] = [ 'label' => $extra, 'url' => '' ];
        }
    }
    $tw_streaming_list = array_column( $tw_streaming_items, 'label' );

    $format_date = function ( $raw ) {
        if ( empty( $raw ) ) return '';
        $raw = trim( (string) $raw );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) return $raw;
        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $raw, $m ) ) return $m[1] . '-' . $m[2] . '-' . $m[3];
        $ts = strtotime( $raw );
        return $ts !== false ? gmdate( 'Y-m-d', $ts ) : $raw;
    };
    $start_date = $format_date( get_post_meta( $post_id, 'anime_start_date', true ) );
    $end_date   = $format_date( get_post_meta( $post_id, 'anime_end_date',   true ) );

    $score_anilist_raw = get_post_meta( $post_id, 'anime_score_anilist', true );
    $score_anilist_num = is_numeric( $score_anilist_raw ) ? (float) $score_anilist_raw : 0;
    $score_anilist     = $score_anilist_num > 0 ? number_format( $score_anilist_num / 10, 1 ) : '';

    $score_mal_raw = get_post_meta( $post_id, 'anime_score_mal', true );
    $score_mal_num = is_numeric( $score_mal_raw ) ? (float) $score_mal_raw : 0;
    $score_mal     = $score_mal_num > 0 ? number_format( $score_mal_num / 10, 1 ) : '';

    $score_bangumi_raw = get_post_meta( $post_id, 'anime_score_bangumi', true );
    $score_bangumi_num = is_numeric( $score_bangumi_raw ) ? (float) $score_bangumi_raw : 0;
    $score_bangumi     = $score_bangumi_num > 0 ? number_format( $score_bangumi_num / 10, 1 ) : '';

    $cover_image  = get_post_meta( $post_id, 'anime_cover_image',  true );
    $banner_image = get_post_meta( $post_id, 'anime_banner_image', true );
    $trailer_url  = get_post_meta( $post_id, 'anime_trailer_url',  true );

    $youtube_id = '';
    if ( $trailer_url ) {
        foreach ( array_filter( array_map( 'trim', preg_split( '/[,\n]+/', $trailer_url ) ) ) as $t_url ) {
            if ( preg_match( '/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{11})/', $t_url, $ym ) ) {
                $youtube_id = $ym[1]; break;
            } elseif ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $t_url ) ) {
                $youtube_id = $t_url; break;
            }
        }
    }

    $official_site = get_post_meta( $post_id, 'anime_official_site', true );
    $twitter_url   = get_post_meta( $post_id, 'anime_twitter_url',   true );
    $wikipedia_url = get_post_meta( $post_id, 'anime_wikipedia_url', true );
    $tiktok_url    = get_post_meta( $post_id, 'anime_tiktok_url',    true );

    $next_airing_raw = get_post_meta( $post_id, 'anime_next_airing', true );
    $airing_data     = [];
    if ( $next_airing_raw ) {
        $decoded = is_array( $next_airing_raw ) ? $next_airing_raw : json_decode( $next_airing_raw, true );
        if ( is_array( $decoded ) ) $airing_data = $decoded;
    }

    $last_sync = get_post_meta( $post_id, 'anime_last_sync', true );

    $synopsis_raw = get_post_meta( $post_id, 'anime_synopsis_chinese', true );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = get_post_meta( $post_id, 'anime_synopsis', true );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = get_the_content();
    $synopsis = trim( $synopsis_raw );

    $decode_json = function ( $raw ) {
        if ( is_array( $raw ) ) return $raw;
        if ( ! is_string( $raw ) || empty( $raw ) ) return [];
        $d = json_decode( $raw, true );
        return is_array( $d ) ? $d : [];
    };

    $streaming_list = $decode_json( get_post_meta( $post_id, 'anime_streaming',      true ) );
    $themes_list    = $decode_json( get_post_meta( $post_id, 'anime_themes',         true ) );
    $cast_list      = $decode_json( get_post_meta( $post_id, 'anime_cast_json',      true ) );
    $staff_list     = $decode_json( get_post_meta( $post_id, 'anime_staff_json',     true ) );
    $relations_list = $decode_json( get_post_meta( $post_id, 'anime_relations_json', true ) );
    $episodes_list  = $decode_json( get_post_meta( $post_id, 'anime_episodes_json',  true ) );

    $seen = []; $openings = []; $endings = [];
    foreach ( $themes_list as $t ) {
        $type   = strtoupper( trim( $t['type']       ?? '' ) );
        $stitle = trim( $t['song_title'] ?? $t['title'] ?? '' );
        $key    = $type . '||' . $stitle;
        if ( isset( $seen[$key] ) ) continue;
        $seen[$key] = true;
        if ( str_starts_with( $type, 'OP' ) )     $openings[] = $t;
        elseif ( str_starts_with( $type, 'ED' ) ) $endings[]  = $t;
    }

    $season_labels  = [ 'WINTER' => '冬季', 'SPRING' => '春季', 'SUMMER' => '夏季', 'FALL' => '秋季' ];
    $format_labels  = [ 'TV' => 'TV', 'TV_SHORT' => 'TV短篇', 'MOVIE' => '劇場版', 'OVA' => 'OVA', 'ONA' => 'ONA', 'SPECIAL' => '特別篇', 'MUSIC' => '音樂MV' ];
    $status_labels  = [ 'FINISHED' => '已完結', 'RELEASING' => '連載中', 'NOT_YET_RELEASED' => '尚未播出', 'CANCELLED' => '已取消', 'HIATUS' => '暫停中' ];
    $status_classes = [ 'FINISHED' => 's-fin', 'RELEASING' => 's-rel', 'NOT_YET_RELEASED' => 's-pre', 'CANCELLED' => 's-can', 'HIATUS' => 's-hia' ];
    $source_labels  = [ 'ORIGINAL' => '原創', 'MANGA' => '漫畫改編', 'LIGHT_NOVEL' => '輕小說', 'NOVEL' => '小說', 'VISUAL_NOVEL' => '視覺小說', 'VIDEO_GAME' => '遊戲', 'WEB_MANGA' => '網路漫畫', 'BOOK' => '書籍', 'MUSIC' => '音樂', 'GAME' => '遊戲', 'LIVE_ACTION' => '真人', 'MULTIMEDIA_PROJECT' => '多媒體企劃', 'OTHER' => '其他' ];

    $season_label = $season_labels[ $season ] ?? $season;
    $format_label = $format_labels[ $format ] ?? $format;
    $status_label = $status_labels[ $status ] ?? $status;
    $status_class = $status_classes[ $status ] ?? '';
    $source_label = $source_labels[ $source ] ?? $source;

    $ep_str = '';
    if ( $episodes ) {
        $ep_str = ( $ep_aired && $ep_aired < $episodes )
            ? $ep_aired . ' / ' . $episodes . ' 集'
            : $episodes . ' 集';
    }

    $genre_terms        = get_the_terms( $post_id, 'genre' )            ?: [];
    $season_terms       = get_the_terms( $post_id, 'anime_season_tax' ) ?: [];
    $season_child_terms = array_values( array_filter( $season_terms, fn( $t ) => $t->parent > 0 ) );

    // 站內相關作品
    $site_relations = [];
    if ( ! empty( $relations_list ) ) {
        foreach ( $relations_list as $rel ) {
            $rel_anilist_id = (int) ( $rel['anilist_id'] ?? $rel['id'] ?? 0 );
            if ( ! $rel_anilist_id ) continue;
            $qr = get_posts( [ 'post_type' => 'anime', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true, 'meta_query' => [ [ 'key' => 'anime_anilist_id', 'value' => $rel_anilist_id, 'type' => 'NUMERIC' ] ] ] );
            if ( ! empty( $qr ) ) {
                $site_rel_post    = $qr[0];
                $site_relations[] = [
                    'title_zh'       => $rel['title_zh']       ?? ( $rel['title'] ?? '' ),
                    'title_native'   => $rel['title_native']   ?? ( $rel['native'] ?? '' ),
                    'relation_label' => $rel['relation_label'] ?? ( $rel['type'] ?? '' ),
                    'format'         => $rel['format']         ?? '',
                    'cover_image'    => get_post_meta( $site_rel_post->ID, 'anime_cover_image', true ) ?: ( $rel['cover_image'] ?? '' ),
                    'url'            => get_permalink( $site_rel_post->ID ),
                ];
            }
        }
    }

    // Schema
    $schema_type   = 'TVSeries';
    if ( $format === 'MOVIE' ) $schema_type = 'Movie';
    if ( $format === 'MUSIC' ) $schema_type = 'MusicVideoObject';
    $schema_genres   = array_map( fn( $t ) => $t->name, $genre_terms );
    $alternate_names = array_values( array_filter( [ $title_native, $title_romaji, $title_english ] ) );
    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => $schema_type,
        'name'        => $display_title,
        'description' => mb_substr( wp_strip_all_tags( $synopsis ), 0, 200 ),
        'image'       => $cover_image ?: get_the_post_thumbnail_url( $post_id, 'large' ),
        'genre'       => $schema_genres,
        'datePublished' => $start_date,
        'url'         => get_permalink( $post_id ),
    ];
    if ( $alternate_names ) $schema['alternateName']    = $alternate_names;
    if ( $episodes )        $schema['numberOfEpisodes'] = $episodes;
    if ( $score_anilist_num > 0 ) {
        $schema['aggregateRating'] = [ '@type' => 'AggregateRating', 'ratingValue' => number_format( $score_anilist_num / 10, 1 ), 'bestRating' => '10', 'worstRating' => '1', 'ratingCount' => max( 1, $popularity ) ];
    }
    $breadcrumb_schema = [
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [ '@type' => 'ListItem', 'position' => 1, 'name' => '首頁',     'item' => home_url( '/' ) ],
            [ '@type' => 'ListItem', 'position' => 2, 'name' => '動畫列表', 'item' => home_url( '/anime/' ) ],
            [ '@type' => 'ListItem', 'position' => 3, 'name' => $display_title, 'item' => get_permalink( $post_id ) ],
        ],
    ];

    // ACF：FAQ 完全人工，從 anime_faq_json 讀取
    $faq_items = [];
    $faq_json_raw = get_post_meta( $post_id, 'anime_faq_json', true );
    if ( $faq_json_raw ) {
        $faq_decoded = json_decode( $faq_json_raw, true );
        if ( is_array( $faq_decoded ) ) $faq_items = $faq_decoded;
    }

    $faq_schema = null;
    if ( ! empty( $faq_items ) ) {
        $faq_schema = [
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => array_map( fn( $f ) => [
                '@type' => 'Question', 'name' => $f['q'],
                'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $f['a'] ],
            ], $faq_items ),
        ];
    }
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<script type="application/ld+json"><?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php if ( $faq_schema ) : ?>
<script type="application/ld+json"><?php echo wp_json_encode( $faq_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php endif; ?>

<div class="asd-wrap">

<?php if ( $banner_image ) : ?>
<div class="asd-banner" style="background-image:url(<?php echo esc_url( $banner_image ); ?>)">
    <div class="asd-banner-fade"></div>
</div>
<?php endif; ?>

<nav class="asd-breadcrumb" aria-label="麵包屑導航">
    <ol itemscope itemtype="https://schema.org/BreadcrumbList">
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" itemprop="item"><span itemprop="name">首頁</span></a>
            <meta itemprop="position" content="1">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a href="<?php echo esc_url( home_url( '/anime/' ) ); ?>" itemprop="item"><span itemprop="name">動畫列表</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <span itemprop="name"><?php echo esc_html( $display_title ); ?></span>
            <meta itemprop="position" content="3">
        </li>
    </ol>
</nav>

<div class="asd-hero">
    <div class="asd-hero-cover">
        <?php if ( $cover_image ) : ?>
        <img src="<?php echo esc_url( $cover_image ); ?>" alt="<?php echo esc_attr( $display_title ); ?> 封面圖" class="asd-cover-img" loading="eager" width="225" height="320">
        <?php elseif ( has_post_thumbnail() ) : ?>
        <?php the_post_thumbnail( 'large', [ 'class' => 'asd-cover-img', 'loading' => 'eager' ] ); ?>
        <?php endif; ?>
    </div>

    <div class="asd-hero-info">
        <h1 class="asd-title"><?php echo esc_html( $display_title ); ?></h1>
        <?php if ( $title_native )  : ?><p class="asd-title-sub asd-title-native"><?php echo esc_html( $title_native ); ?></p><?php endif; ?>
        <?php if ( $title_romaji )  : ?><p class="asd-title-sub asd-title-romaji"><?php echo esc_html( $title_romaji ); ?></p><?php endif; ?>
        <?php if ( $title_english && $title_english !== $title_romaji ) : ?><p class="asd-title-sub asd-title-english"><?php echo esc_html( $title_english ); ?></p><?php endif; ?>

        <div class="asd-badges">
            <?php if ( $status_label ) echo '<span class="asd-badge asd-badge-status ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>'; ?>
            <?php if ( $format_label ) echo '<span class="asd-badge asd-badge-format">' . esc_html( $format_label ) . '</span>'; ?>
            <?php if ( $season_label && $season_year ) echo '<span class="asd-badge asd-badge-season">' . esc_html( $season_year . ' ' . $season_label ) . '</span>'; ?>
        </div>

        <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
        <div class="asd-hero-scores">
            <?php if ( $score_anilist ) : ?><div class="asd-score-block asd-score-al"><span class="asd-score-platform">AniList</span><span class="asd-score-val"><?php echo esc_html( $score_anilist ); ?></span><span class="asd-score-max">/ 10</span></div><?php endif; ?>
            <?php if ( $score_mal )     : ?><div class="asd-score-block asd-score-mal"><span class="asd-score-platform">MAL</span><span class="asd-score-val"><?php echo esc_html( $score_mal ); ?></span><span class="asd-score-max">/ 10</span></div><?php endif; ?>
            <?php if ( $score_bangumi ) : ?><div class="asd-score-block asd-score-bgm"><span class="asd-score-platform">Bangumi</span><span class="asd-score-val"><?php echo esc_html( $score_bangumi ); ?></span><span class="asd-score-max">/ 10</span></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="asd-quick-links">
            <?php if ( $youtube_id ) : ?><a href="#asd-sec-trailer" class="asd-quick-link asd-quick-link--trailer">▶ 預告片</a><?php endif; ?>
        </div>
    </div>
</div>

<nav class="asd-tabs" id="asd-tabs" aria-label="頁面導覽">
    <a class="asd-tab" href="#asd-sec-info">📋 基本資訊</a>
    <?php if ( $synopsis )                   : ?><a class="asd-tab" href="#asd-sec-synopsis">📝 劇情簡介</a><?php endif; ?>
    <?php if ( ! empty( $episodes_list ) )   : ?><a class="asd-tab" href="#asd-sec-episodes">📺 集數列表</a><?php endif; ?>
    <?php if ( $staff_list )                 : ?><a class="asd-tab" href="#asd-sec-staff">🎬 製作人員</a><?php endif; ?>
    <?php if ( $cast_list )                  : ?><a class="asd-tab" href="#asd-sec-cast">🎭 角色聲優</a><?php endif; ?>
    <?php if ( $openings || $endings )       : ?><a class="asd-tab" href="#asd-sec-music">🎵 主題曲</a><?php endif; ?>
    <?php if ( $tw_streaming_items || $streaming_list ) : ?><a class="asd-tab" href="#asd-sec-stream">📡 串流平台</a><?php endif; ?>
    <?php if ( ! empty( $site_relations ) )  : ?><a class="asd-tab" href="#asd-sec-relations">🔗 相關作品</a><?php endif; ?>
    <?php if ( ! empty( $faq_items ) )       : ?><a class="asd-tab" href="#asd-sec-faq">❓ 常見問題</a><?php endif; ?>
</nav>

<div class="asd-container">
<main class="asd-main" id="asd-main">

<!-- ═══ 基本資訊 ═══ -->
<section class="asd-section" id="asd-sec-info">
    <h2 class="asd-section-title">📋 基本資訊</h2>
    <div class="asd-info-grid">
    <?php
    $info_rows = [
        '類型'     => $format_label,
        '集數'     => $ep_str,
        '狀態'     => $status_label,
        '播出季度' => ( $season_label && $season_year ) ? $season_year . ' ' . $season_label : '',
        '每集時長' => $duration ? $duration . ' 分鐘' : '',
        '開始日期' => $start_date,
        '結束日期' => ( $end_date && $status === 'FINISHED' ) ? $end_date : '',
        '原作來源' => $source_label,
        '製作公司' => $studio,
        '台灣代理' => $tw_dist_display,
        '台灣播出' => $tw_broadcast,
    ];
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
    <div class="asd-seo-links">
        <?php if ( ! empty( $genre_terms ) ) : ?>
        <div class="asd-seo-row">
            <span class="asd-seo-label">類型：</span>
            <?php foreach ( $genre_terms as $gt ) : ?><a href="<?php echo esc_url( get_term_link( $gt ) ); ?>" class="asd-seo-tag"><?php echo esc_html( $gt->name ); ?></a><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ( ! empty( $season_child_terms ) ) : ?>
        <div class="asd-seo-row">
            <span class="asd-seo-label">季度：</span>
            <?php foreach ( $season_child_terms as $st ) : ?><a href="<?php echo esc_url( get_term_link( $st ) ); ?>" class="asd-seo-tag"><?php echo esc_html( $st->name ); ?></a><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ( $status === 'RELEASING' && ! empty( $airing_data['airingAt'] ) ) : ?>
    <div class="asd-airing-bar">
        <span>📅 第 <?php echo esc_html( $airing_data['episode'] ?? '' ); ?> 集播出倒數：</span>
        <strong class="asd-countdown" data-ts="<?php echo esc_attr( $airing_data['airingAt'] ); ?>">計算中…</strong>
    </div>
    <?php endif; ?>
</section>

<!-- ═══ 劇情簡介 ═══ -->
<?php if ( $synopsis ) : ?>
<section class="asd-section" id="asd-sec-synopsis">
    <h2 class="asd-section-title">📝 劇情簡介</h2>
    <div class="asd-synopsis"><?php echo wp_kses_post( wpautop( $synopsis ) ); ?></div>
</section>
<?php endif; ?>

<!-- ═══ 集數列表 ═══ -->
<?php if ( ! empty( $episodes_list ) ) : ?>
<section class="asd-section" id="asd-sec-episodes">
    <h2 class="asd-section-title">📺 集數列表</h2>
    <div class="asd-ep-list" id="asd-ep-list">
    <?php foreach ( $episodes_list as $i => $ep ) :
        $ep_num     = (int)  ( $ep['ep']      ?? 0 );
        $ep_name_cn = trim(    $ep['name_cn']  ?? '' );
        $ep_name_ja = trim(    $ep['name']     ?? '' );
        $ep_airdate =          $ep['airdate']  ?? '';
        // ACG：集數中文標題轉繁體
        if ( $ep_name_cn !== '' ) {
            $ep_name_cn = Anime_Sync_CN_Converter::static_convert( $ep_name_cn );
        }
        $ep_name = $ep_name_cn ?: $ep_name_ja;
    ?>
    <div class="asd-ep-row<?php echo $i >= 3 ? ' asd-ep-hidden' : ''; ?>">
        <span class="asd-ep-num">第 <?php echo esc_html( $ep_num ); ?> 集</span>
        <span class="asd-ep-title">
            <?php if ( $ep_name ) echo esc_html( $ep_name ); ?>
            <?php if ( $ep_name_cn && $ep_name_ja && $ep_name_cn !== $ep_name_ja ) : ?>
            <small class="asd-ep-title-ja"><?php echo esc_html( $ep_name_ja ); ?></small>
            <?php endif; ?>
        </span>
        <?php if ( $ep_airdate ) : ?><span class="asd-ep-date"><?php echo esc_html( $ep_airdate ); ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php if ( count( $episodes_list ) > 3 ) : ?>
    <div class="asd-ep-more-wrap">
        <button class="asd-ep-more-btn" id="asd-ep-more-btn" data-total="<?php echo count( $episodes_list ); ?>">
            顯示全部 <?php echo count( $episodes_list ); ?> 集 ▾
        </button>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ═══ STAFF ═══ -->
<?php if ( ! empty( $staff_list ) ) : ?>
<section class="asd-section" id="asd-sec-staff">
    <h2 class="asd-section-title">🎬 STAFF</h2>
    <div class="asd-staff-grid">
    <?php foreach ( $staff_list as $i => $s ) :
        $s_name = $s['name']  ?? '';
        $s_role = $s['role']  ?? '';
    ?>
    <div class="asd-staff-card<?php echo $i >= 12 ? ' asd-staff-extra' : ''; ?>">
        <div class="asd-staff-names">
            <span class="asd-staff-name"><?php echo esc_html( $s_name ); ?></span>
            <span class="asd-staff-role"><?php echo esc_html( $s_role ); ?></span>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php if ( count( $staff_list ) > 12 ) : ?>
    <div class="asd-staff-more-wrap">
        <button class="asd-staff-more-btn" id="asd-staff-more-btn">
            顯示全部 <?php echo count( $staff_list ); ?> 位人員 ▾
        </button>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ═══ CAST ═══ -->
<?php if ( ! empty( $cast_list ) ) : ?>
<section class="asd-section" id="asd-sec-cast">
    <h2 class="asd-section-title">🎭 CAST</h2>
    <div class="asd-cast-grid" id="asd-cast-grid">
    <?php foreach ( $cast_list as $i => $c ) :
        $char_name = $c['name']  ?? '';
        $char_img  = $c['image'] ?? '';
        $va_list   = $c['voice_actors'] ?? [];
        $va_name   = ! empty( $va_list ) ? ( $va_list[0]['name'] ?? '' ) : '';
    ?>
    <div class="asd-cast-card<?php echo $i >= 12 ? ' asd-cast-extra' : ''; ?>">
        <div class="asd-cast-img">
            <?php if ( $char_img ) : ?>
            <img src="<?php echo esc_url( $char_img ); ?>"
                 alt="<?php echo esc_attr( $char_name ); ?>"
                 loading="lazy">
            <?php else : ?>
            <div class="asd-cast-noimg">
                <?php echo esc_html( mb_substr( $char_name, 0, 1 ) ); ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="asd-cast-names">
            <?php if ( $char_name ) : ?>
            <span class="asd-cast-char"><?php echo esc_html( $char_name ); ?></span>
            <?php endif; ?>
            <?php if ( $va_name ) : ?>
            <span class="asd-cast-va">CV: <?php echo esc_html( $va_name ); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php if ( count( $cast_list ) > 12 ) : ?>
    <div class="asd-cast-more-wrap">
        <button class="asd-cast-more-btn" id="asd-cast-more-btn">
            顯示全部 <?php echo count( $cast_list ); ?> 位角色 ▾
        </button>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ═══ 主題曲 ═══ -->
<?php if ( $openings || $endings ) : ?>
<section class="asd-section" id="asd-sec-music">
    <h2 class="asd-section-title">🎵 主題曲</h2>
    <?php foreach ( [ '片頭曲 OP' => $openings, '片尾曲 ED' => $endings ] as $group_label => $group ) :
        if ( empty( $group ) ) continue; ?>
    <div class="asd-theme-group">
        <p class="asd-theme-group-title"><?php echo esc_html( $group_label ); ?></p>
        <?php foreach ( $group as $t ) :
            $t_type    = strtoupper( trim( $t['type']       ?? '' ) );
            $t_title   = trim( $t['song_title'] ?? $t['title']  ?? '' );
            $t_artist  = trim( $t['artist']     ?? '' );
            $t_num     = preg_replace( '/[^0-9]/', '', $t_type );
            $t_label   = str_starts_with( $t_type, 'OP' ) ? 'OP' : 'ED';
            $t_label  .= $t_num ? $t_num : '';
            $t_audio   = $t['audio_url'] ?? $t['audio'] ?? '';
            $t_video   = $t['video_url'] ?? $t['video_link'] ?? '';
        ?>
        <div class="asd-theme-item">
            <span class="asd-theme-label"><?php echo esc_html( $t_label ); ?></span>
            <span class="asd-theme-title"><?php echo esc_html( $t_title ); ?></span>
            <?php if ( $t_artist ) : ?><span class="asd-theme-artist"><?php echo esc_html( $t_artist ); ?></span><?php endif; ?>
            <?php if ( $t_audio ) : ?>
            <audio controls preload="none" style="width:100%;margin-top:6px;display:block;">
                <source src="<?php echo esc_url( $t_audio ); ?>" type="audio/ogg">
                您的瀏覽器不支援音訊播放。
            </audio>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ═══ 串流平台 ═══ -->
<?php if ( $tw_streaming_items || $streaming_list ) : ?>
<section class="asd-section" id="asd-sec-stream">
    <h2 class="asd-section-title">📡 串流平台</h2>

    <?php if ( $tw_streaming_items ) : ?>
    <div class="asd-stream-group">
        <p class="asd-stream-group-title">🇹🇼 台灣</p>
        <div class="asd-stream-list">
        <?php foreach ( $tw_streaming_items as $item ) :
            $label = $item['label'];
            $url   = $item['url'];
        ?>
        <div class="asd-stream-item">
            <?php if ( $url ) : ?>
            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="asd-stream-link">
                <?php echo esc_html( $label ); ?> ↗
            </a>
            <?php else : ?>
            <span class="asd-stream-name"><?php echo esc_html( $label ); ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $streaming_list ) : ?>
    <div class="asd-stream-group">
        <p class="asd-stream-group-title">🌐 國際</p>
        <div class="asd-stream-list">
        <?php foreach ( $streaming_list as $s ) : ?>
        <div class="asd-stream-item">
            <?php if ( ! empty( $s['url'] ) ) : ?>
            <a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="asd-stream-link">
                <?php echo esc_html( $s['site'] ?? '' ); ?> ↗
            </a>
            <?php else : ?>
            <span class="asd-stream-name"><?php echo esc_html( $s['site'] ?? '' ); ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ═══ 相關作品 ═══ -->
<?php if ( ! empty( $site_relations ) ) : ?>
<section class="asd-section" id="asd-sec-relations">
    <h2 class="asd-section-title">🔗 相關作品</h2>
    <div class="asd-relations-grid">
    <?php foreach ( $site_relations as $rel ) : ?>
    <a href="<?php echo esc_url( $rel['url'] ); ?>" class="asd-relation-card">
        <?php if ( $rel['cover_image'] ) : ?>
        <div class="asd-relation-thumb"><img src="<?php echo esc_url( $rel['cover_image'] ); ?>" alt="<?php echo esc_attr( $rel['title_zh'] ); ?>" loading="lazy"></div>
        <?php endif; ?>
        <div class="asd-relation-info">
            <span class="asd-relation-type"><?php echo esc_html( $rel['relation_label'] ); ?></span>
            <span class="asd-relation-title"><?php echo esc_html( $rel['title_zh'] ?: $rel['title_native'] ); ?></span>
        </div>
    </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ═══ FAQ ═══ -->
<?php if ( ! empty( $faq_items ) ) : ?>
<section class="asd-section" id="asd-sec-faq">
    <h2 class="asd-section-title">❓ 常見問題</h2>
    <div class="asd-faq-list">
    <?php foreach ( $faq_items as $faq ) : ?>
    <div class="asd-faq-item">
        <p class="asd-faq-q"><?php echo esc_html( $faq['q'] ); ?></p>
        <p class="asd-faq-a"><?php echo wp_kses_post( $faq['a'] ); ?></p>
    </div>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

</main>
</div><!-- .asd-container -->
</div><!-- .asd-wrap -->

<script>
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {

        // 集數列表「顯示全部 / 收起」
        var epBtn = document.getElementById('asd-ep-more-btn');
        if (epBtn) {
            var epTotal = epBtn.dataset.total;
            epBtn.addEventListener('click', function () {
                var hidden = document.querySelectorAll('#asd-ep-list .asd-ep-hidden');
                if (hidden.length > 0) {
                    // 展開
                    hidden.forEach(function (el) { el.classList.remove('asd-ep-hidden'); });
                    epBtn.textContent = '收起 ▴';
                } else {
                    // 收起
                    var rows = document.querySelectorAll('#asd-ep-list .asd-ep-row');
                    rows.forEach(function (el, i) {
                        if (i >= 3) el.classList.add('asd-ep-hidden');
                    });
                    epBtn.textContent = '顯示全部 ' + epTotal + ' 集 ▾';
                }
            });
        }

        // Cast「顯示全部」
        var castBtn = document.getElementById('asd-cast-more-btn');
        if (castBtn) {
            castBtn.addEventListener('click', function () {
                document.querySelectorAll('.asd-cast-extra').forEach(function (el) {
                    el.style.display = '';
                });
                var wrap = document.querySelector('.asd-cast-more-wrap');
                if (wrap) wrap.style.display = 'none';
            });
        }

        // 播出倒數計時
        var countdown = document.querySelector('.asd-countdown[data-ts]');
        if (countdown) {
            var ts = parseInt(countdown.dataset.ts, 10) * 1000;
            function updateCountdown() {
                var diff = ts - Date.now();
                if (diff <= 0) { countdown.textContent = '即將播出'; return; }
                var d = Math.floor(diff / 86400000);
                var h = Math.floor((diff % 86400000) / 3600000);
                var m = Math.floor((diff % 3600000) / 60000);
                countdown.textContent = d + ' 天 ' + h + ' 時 ' + m + ' 分';
            }
            updateCountdown();
            setInterval(updateCountdown, 60000);
        }

        // Tab 滑動高亮
        var tabLinks = document.querySelectorAll('.asd-tabs .asd-tab');
        if (tabLinks.length) {
            window.addEventListener('scroll', function () {
                var scrollTop = window.scrollY;
                tabLinks.forEach(function (link) {
                    var targetId = link.getAttribute('href');
                    var section  = document.querySelector(targetId);
                    if (!section) return;
                    var top    = section.offsetTop - 120;
                    var bottom = top + section.offsetHeight;
                    if (scrollTop >= top && scrollTop < bottom) {
                        tabLinks.forEach(function (l) { l.classList.remove('active'); });
                        link.classList.add('active');
                    }
                });
            });
        }
    });
})();
</script>

<?php endwhile;
get_footer();

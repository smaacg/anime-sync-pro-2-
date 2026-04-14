<?php
/**
 * Single Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/single-anime.php
 *
 * Bug fixes / changes in this version:
 *   Bug 4  – Bangumi 評分除以 10 顯示
 *   Bug 6  – Relations key 對齊 parse_relations() 實際回傳結構
 *   F-TPL  – 依新骨架重構：兩欄佈局、錨點快速導覽、集數列表、台灣播出資訊、FAQ、底部推薦
 *   ABP    – 底部推薦 & 側欄推薦圖片加 thumb-wrap 容器防跑版
 *   SCORE  – 評分列改 Glass 風格，移除人氣區塊
 *   ABR    – 結構級圖片修正：relations/sidebar-rel/staff/cast 圖片加容器包裹
 *            搭配 anime-single.css v11.2 的 position:absolute img 模式
 *            移除 get_the_post_thumbnail() 的硬編尺寸，由 CSS aspect-ratio 控制
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

wp_enqueue_style(
    'anime-sync-single',
    plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/anime-single.css',
    [],
    defined( 'ANIME_SYNC_PRO_VERSION' ) ? ANIME_SYNC_PRO_VERSION : '1.0.0'
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

    $tw_dist_labels = [
        'muse'        => '木棉花（Muse）',
        'medialink'   => '曼迪傳播（Medialink）',
        'jbf'         => '日本橋文化（JBF）',
        'righttime'   => '正確時間',
        'gaga'        => 'GaGa OOLala',
        'catchplay'   => 'CatchPlay',
        'netflix'     => 'Netflix 台灣',
        'disney'      => 'Disney+ 台灣',
        'kktv'        => 'KKTV',
        'crunchyroll' => 'Crunchyroll',
        'ani-one'     => 'Ani-One Asia',
        'other'       => '',
    ];
    $tw_dist_display = '';
    if ( $tw_distributor === 'other' ) {
        $tw_dist_display = $tw_dist_custom ?: '';
    } elseif ( $tw_distributor ) {
        $tw_dist_display = $tw_dist_labels[ $tw_distributor ] ?? $tw_distributor;
    }

    $tw_stream_labels = [
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
        'youtube'     => 'YouTube（官方頻道）',
    ];
    $tw_streaming_list = [];
    if ( ! empty( $tw_streaming_raw ) ) {
        $raw_arr = is_array( $tw_streaming_raw ) ? $tw_streaming_raw : [ $tw_streaming_raw ];
        foreach ( $raw_arr as $key ) {
            $tw_streaming_list[] = $tw_stream_labels[ $key ] ?? $key;
        }
    }
    if ( $tw_streaming_other ) {
        foreach ( array_map( 'trim', explode( ',', $tw_streaming_other ) ) as $extra ) {
            if ( $extra ) $tw_streaming_list[] = $extra;
        }
    }

    $format_date = function ( $raw ) {
        if ( empty( $raw ) ) return '';
        $raw = trim( (string) $raw );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) return $raw;
        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $raw, $m ) )
            return $m[1] . '-' . $m[2] . '-' . $m[3];
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
    $score_mal     = $score_mal_num > 0 ? number_format( $score_mal_num, 1 ) : '';

    // Bug 4: Bangumi 儲存值為原始 ×10，除以 10 顯示
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
        $type   = strtoupper( trim( $t['type'] ?? '' ) );
        $stitle = trim( $t['song_title'] ?? $t['title'] ?? '' );
        $key    = $type . '||' . $stitle;
        if ( isset( $seen[$key] ) ) continue;
        $seen[$key] = true;
        if ( str_starts_with( $type, 'OP' ) )     $openings[] = $t;
        elseif ( str_starts_with( $type, 'ED' ) ) $endings[]  = $t;
    }

    $season_labels  = [ 'WINTER' => '冬季', 'SPRING' => '春季', 'SUMMER' => '夏季', 'FALL' => '秋季' ];
    $format_labels  = [
        'TV' => 'TV', 'TV_SHORT' => 'TV短篇', 'MOVIE' => '劇場版',
        'OVA' => 'OVA', 'ONA' => 'ONA', 'SPECIAL' => '特別篇', 'MUSIC' => '音樂MV',
    ];
    $status_labels  = [
        'FINISHED'         => '已完結',  'RELEASING'        => '連載中',
        'NOT_YET_RELEASED' => '尚未播出','CANCELLED'        => '已取消',
        'HIATUS'           => '暫停中',
    ];
    $status_classes = [
        'FINISHED' => 's-fin', 'RELEASING' => 's-rel', 'NOT_YET_RELEASED' => 's-pre',
        'CANCELLED' => 's-can', 'HIATUS' => 's-hia',
    ];
    $source_labels = [
        'ORIGINAL' => '原創', 'MANGA' => '漫畫改編', 'LIGHT_NOVEL' => '輕小說',
        'NOVEL' => '小說', 'VISUAL_NOVEL' => '視覺小說', 'VIDEO_GAME' => '遊戲',
        'WEB_MANGA' => '網路漫畫', 'BOOK' => '書籍', 'MUSIC' => '音樂',
        'GAME' => '遊戲', 'LIVE_ACTION' => '真人', 'MULTIMEDIA_PROJECT' => '多媒體企劃',
        'OTHER' => '其他',
    ];

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

    $genre_terms  = get_the_terms( $post_id, 'genre' )            ?: [];
    $season_terms = get_the_terms( $post_id, 'anime_season_tax' ) ?: [];
    $format_terms = get_the_terms( $post_id, 'anime_format_tax' ) ?: [];

    // 熱門推薦
    $recommend_posts = [];
    if ( ! empty( $genre_terms ) ) {
        $genre_ids = wp_list_pluck( $genre_terms, 'term_id' );
        $rq = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'post__not_in'   => [ $post_id ],
            'tax_query'      => [ [ 'taxonomy' => 'genre', 'field' => 'term_id', 'terms' => $genre_ids, 'operator' => 'IN' ] ],
            'meta_key'       => 'anime_score_anilist',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );
        $recommend_posts = $rq->posts;
        wp_reset_postdata();
    }

    // 相關新聞
    $news_posts = [];
    if ( $display_title ) {
        $nq = new WP_Query( [
            'post_type' => 'post', 'post_status' => 'publish',
            'posts_per_page' => 5, 'tag' => sanitize_title( $display_title ), 'no_found_rows' => true,
        ] );
        $news_posts = $nq->posts;
        wp_reset_postdata();
        if ( empty( $news_posts ) && $title_romaji ) {
            $nq2 = new WP_Query( [
                'post_type' => 'post', 'post_status' => 'publish',
                'posts_per_page' => 5, 's' => $title_romaji, 'no_found_rows' => true,
            ] );
            $news_posts = $nq2->posts;
            wp_reset_postdata();
        }
    }

    // Schema
    $schema_type  = 'TVSeries';
    if ( $format === 'MOVIE' ) $schema_type = 'Movie';
    if ( $format === 'MUSIC' ) $schema_type = 'MusicVideoObject';
    $schema_genres   = array_map( fn( $t ) => $t->name, $genre_terms );
    $alternate_names = array_values( array_filter( [ $title_native, $title_romaji, $title_english ] ) );
    $schema = [
        '@context' => 'https://schema.org', '@type' => $schema_type,
        'name'     => $display_title,
        'description' => mb_substr( wp_strip_all_tags( $synopsis ), 0, 200 ),
        'image'    => $cover_image ?: get_the_post_thumbnail_url( $post_id, 'large' ),
        'genre'    => $schema_genres, 'datePublished' => $start_date, 'url' => get_permalink( $post_id ),
    ];
    if ( $alternate_names ) $schema['alternateName']    = $alternate_names;
    if ( $episodes )        $schema['numberOfEpisodes'] = $episodes;
    if ( $score_anilist_num > 0 ) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format( $score_anilist_num / 10, 1 ),
            'bestRating' => '10', 'worstRating' => '1',
            'ratingCount' => max( 1, $popularity ),
        ];
    }
    $breadcrumb_schema = [
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [ '@type' => 'ListItem', 'position' => 1, 'name' => '首頁',     'item' => home_url( '/' ) ],
            [ '@type' => 'ListItem', 'position' => 2, 'name' => '動畫列表', 'item' => home_url( '/anime/' ) ],
            [ '@type' => 'ListItem', 'position' => 3, 'name' => $display_title, 'item' => get_permalink( $post_id ) ],
        ],
    ];

    // FAQ
    $faq_items = [];
    if ( $episodes && $start_date ) {
        $faq_items[] = [
            'q' => '《' . $display_title . '》總共有幾集？',
            'a' => '《' . $display_title . '》共 ' . $episodes . ' 集，於 ' . $start_date . ' 開始播出。'
                   . ( $end_date && $status === 'FINISHED' ? '已於 ' . $end_date . ' 完結。' : '' ),
        ];
    }
    if ( ! empty( $tw_streaming_list ) ) {
        $faq_items[] = [
            'q' => '《' . $display_title . '》在哪裡可以看？台灣有哪些串流平台？',
            'a' => '《' . $display_title . '》在台灣可於以下平台收看：' . implode( '、', $tw_streaming_list ) . '。',
        ];
    } elseif ( ! empty( $streaming_list ) ) {
        $platforms = array_filter( array_map( fn( $s ) => $s['site'] ?? '', $streaming_list ) );
        if ( $platforms ) {
            $faq_items[] = [
                'q' => '《' . $display_title . '》在哪裡可以看？',
                'a' => '《' . $display_title . '》可於以下平台收看：' . implode( '、', $platforms ) . '。',
            ];
        }
    }
    if ( $studio ) {
        $faq_items[] = [
            'q' => '《' . $display_title . '》是哪間公司製作的？',
            'a' => '《' . $display_title . '》由 ' . $studio . ' 負責製作動畫。',
        ];
    }
    $has_sequel = false;
    foreach ( $relations_list as $rel ) {
        if ( ( $rel['relation_label'] ?? $rel['type'] ?? '' ) === '續作' ) { $has_sequel = true; break; }
    }
    if ( $has_sequel ) {
        $faq_items[] = [
            'q' => '《' . $display_title . '》有續集嗎？',
            'a' => '根據目前資料，《' . $display_title . '》已有續集作品，詳情請參閱下方「相關作品」區塊。',
        ];
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
        <img src="<?php echo esc_url( $cover_image ); ?>" alt="<?php echo esc_attr( $display_title ); ?> 封面圖"
             class="asd-cover-img" loading="eager" width="225" height="320">
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

        <?php /* ── 評分列 SCORE：Glass 風格，移除人氣 ── */ ?>
        <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
        <div class="asd-hero-scores">
            <?php if ( $score_anilist ) : ?>
            <div class="asd-score-block asd-score-al">
                <span class="asd-score-platform">AniList</span>
                <span class="asd-score-val"><?php echo esc_html( $score_anilist ); ?></span>
                <span class="asd-score-max">/ 10</span>
            </div>
            <?php endif; ?>
            <?php if ( $score_mal ) : ?>
            <div class="asd-score-block asd-score-mal">
                <span class="asd-score-platform">MAL</span>
                <span class="asd-score-val"><?php echo esc_html( $score_mal ); ?></span>
                <span class="asd-score-max">/ 10</span>
            </div>
            <?php endif; ?>
            <?php if ( $score_bangumi ) : ?>
            <div class="asd-score-block asd-score-bgm">
                <span class="asd-score-platform">Bangumi</span>
                <span class="asd-score-val"><?php echo esc_html( $score_bangumi ); ?></span>
                <span class="asd-score-max">/ 10</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="asd-quick-links">
            <?php if ( $anilist_id )    echo '<a href="https://anilist.co/anime/'          . esc_attr( $anilist_id )  . '/" class="asd-quick-link" target="_blank" rel="noopener">AniList</a>'; ?>
            <?php if ( $mal_id )        echo '<a href="https://myanimelist.net/anime/'      . esc_attr( $mal_id )     . '/" class="asd-quick-link" target="_blank" rel="noopener">MAL</a>'; ?>
            <?php if ( $bangumi_id )    echo '<a href="https://bgm.tv/subject/'             . esc_attr( $bangumi_id ) . '" class="asd-quick-link" target="_blank" rel="noopener">Bangumi</a>'; ?>
            <?php if ( $official_site ) echo '<a href="' . esc_url( $official_site )        . '" class="asd-quick-link" target="_blank" rel="noopener">官網</a>'; ?>
            <?php if ( $twitter_url )   echo '<a href="' . esc_url( $twitter_url )          . '" class="asd-quick-link" target="_blank" rel="noopener">X</a>'; ?>
            <?php if ( $wikipedia_url ) echo '<a href="' . esc_url( $wikipedia_url )        . '" class="asd-quick-link" target="_blank" rel="noopener">Wiki</a>'; ?>
            <?php if ( $youtube_id )    echo '<a href="https://www.youtube.com/watch?v='    . esc_attr( $youtube_id ) . '" class="asd-quick-link asd-quick-link--trailer" target="_blank" rel="noopener">▶ 預告片</a>'; ?>
        </div>
    </div>
</div>

<nav class="asd-tabs" id="asd-tabs" aria-label="頁面導覽">
    <a class="asd-tab" href="#asd-sec-info">📋 基本資訊</a>
    <?php if ( $synopsis )                 : ?><a class="asd-tab" href="#asd-sec-synopsis">📝 劇情簡介</a><?php endif; ?>
    <?php if ( ! empty( $episodes_list ) ) : ?><a class="asd-tab" href="#asd-sec-episodes">📺 集數列表</a><?php endif; ?>
    <?php if ( $cast_list )                : ?><a class="asd-tab" href="#asd-sec-cast">🎭 角色聲優</a><?php endif; ?>
    <?php if ( $staff_list )               : ?><a class="asd-tab" href="#asd-sec-staff">🎬 製作人員</a><?php endif; ?>
    <?php if ( $openings || $endings )     : ?><a class="asd-tab" href="#asd-sec-music">🎵 主題曲</a><?php endif; ?>
    <?php if ( $streaming_list || $tw_streaming_list ) : ?><a class="asd-tab" href="#asd-sec-stream">📡 串流平台</a><?php endif; ?>
    <?php if ( $relations_list )           : ?><a class="asd-tab" href="#asd-sec-relations">🔗 相關作品</a><?php endif; ?>
    <?php if ( ! empty( $faq_items ) )     : ?><a class="asd-tab" href="#asd-sec-faq">❓ 常見問題</a><?php endif; ?>
</nav>

<div class="asd-container">

    <main class="asd-main" id="asd-main">

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
            <?php if ( $status === 'RELEASING' && ! empty( $airing_data['airingAt'] ) ) : ?>
            <div class="asd-airing-bar">
                <span>📅 第 <?php echo esc_html( $airing_data['episode'] ?? '' ); ?> 集播出倒數：</span>
                <strong class="asd-countdown" data-ts="<?php echo esc_attr( $airing_data['airingAt'] ); ?>">計算中…</strong>
            </div>
            <?php endif; ?>
        </section>

        <?php if ( $synopsis ) : ?>
        <section class="asd-section" id="asd-sec-synopsis">
            <h2 class="asd-section-title">📝 劇情簡介</h2>
            <div class="asd-synopsis"><?php echo wp_kses_post( wpautop( $synopsis ) ); ?></div>
        </section>
        <?php endif; ?>

        <?php if ( ! empty( $episodes_list ) ) : ?>
        <section class="asd-section" id="asd-sec-episodes">
            <h2 class="asd-section-title">📺 集數列表</h2>
            <div class="asd-ep-list" id="asd-ep-list">
            <?php foreach ( $episodes_list as $i => $ep ) :
                $ep_num     = (int)   ( $ep['ep']      ?? 0 );
                $ep_name_cn = trim(     $ep['name_cn']  ?? '' );
                $ep_name_ja = trim(     $ep['name']     ?? '' );
                $ep_airdate =           $ep['airdate']  ?? '';
                $ep_name    = $ep_name_cn ?: $ep_name_ja;
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

        <?php if ( $cast_list ) : ?>
        <section class="asd-section" id="asd-sec-cast">
            <h2 class="asd-section-title">🎭 角色與聲優</h2>
            <div class="asd-cast-grid" id="asd-cast-grid">
            <?php foreach ( $cast_list as $i => $c ) :
                $char_name = $c['char_name_zh'] ?? ( $c['char_name_ja'] ?? ( $c['character'] ?? '' ) );
                $char_img  = $c['char_image']   ?? '';
                $va_name   = $c['va_name']       ?? ( $c['voice_actor'] ?? ( $c['name'] ?? '' ) );
            ?>
            <div class="asd-cast-card<?php echo $i >= 12 ? ' asd-cast-extra' : ''; ?>">
                <div class="asd-cast-img">
                    <?php if ( $char_img ) : ?>
                    <img src="<?php echo esc_url( $char_img ); ?>" alt="<?php echo esc_attr( $char_name ); ?>" loading="lazy">
                    <?php else : ?><div class="asd-cast-noimg">?</div><?php endif; ?>
                </div>
                <div class="asd-cast-names">
                    <?php if ( $char_name ) echo '<span class="asd-cast-char">' . esc_html( $char_name ) . '</span>'; ?>
                    <?php if ( $va_name )   echo '<span class="asd-cast-va">CV: ' . esc_html( $va_name ) . '</span>'; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <?php if ( count( $cast_list ) > 12 ) : ?>
            <div class="asd-cast-more-wrap">
                <button class="asd-cast-more-btn" id="asd-cast-more-btn">顯示全部 <?php echo count( $cast_list ); ?> 位角色 ▾</button>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ( $staff_list ) : ?>
        <section class="asd-section" id="asd-sec-staff">
            <h2 class="asd-section-title">🎬 製作人員</h2>
            <div class="asd-staff-grid">
            <?php foreach ( $staff_list as $s ) :
                $s_name = $s['name_zh'] ?? ( $s['name'] ?? '' );
                $s_role = $s['role']    ?? '';
                $s_img  = $s['image']   ?? '';
            ?>
            <div class="asd-staff-card">
                <?php /* ABR: img 外包 .asd-staff-avatar，搭配 CSS 44×44 圓形 + absolute img */ ?>
                <?php if ( $s_img ) : ?>
                <div class="asd-staff-avatar">
                    <img src="<?php echo esc_url( $s_img ); ?>" alt="<?php echo esc_attr( $s_name ); ?>" loading="lazy">
                </div>
                <?php else : ?><div class="asd-staff-noimg">?</div><?php endif; ?>
                <div class="asd-staff-info">
                    <span class="asd-staff-name"><?php echo esc_html( $s_name ); ?></span>
                    <span class="asd-staff-role"><?php echo esc_html( $s_role ); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ( $openings || $endings ) : ?>
        <section class="asd-section" id="asd-sec-music">
            <h2 class="asd-section-title">🎵 主題曲</h2>
            <?php foreach ( [ '片頭曲 OP' => $openings, '片尾曲 ED' => $endings ] as $group_label => $group ) :
                if ( empty( $group ) ) continue; ?>
            <div class="asd-theme-group">
                <p class="asd-theme-group-title"><?php echo esc_html( $group_label ); ?></p>
                <?php foreach ( $group as $t ) :
                    $t_type   = strtoupper( trim( $t['type']       ?? '' ) );
                    $t_title  = trim( $t['song_title'] ?? $t['title']  ?? '' );
                    $t_artist = trim( $t['artist']     ?? '' );
                    $t_link   = trim( $t['link']       ?? '' );
                    $t_num    = preg_replace( '/^(OP|ED)/i', '', $t_type ) ?: '1';
                    $t_label  = ( str_starts_with( $t_type, 'OP' ) ? 'OP' : 'ED' ) . $t_num;
                ?>
                <div class="asd-theme-row">
                    <span class="asd-theme-label"><?php echo esc_html( $t_label ); ?></span>
                    <div class="asd-theme-meta">
                        <span class="asd-theme-title"><?php echo esc_html( $t_title ); ?></span>
                        <?php if ( $t_artist ) echo '<span class="asd-theme-artist">' . esc_html( $t_artist ) . '</span>'; ?>
                    </div>
                    <?php if ( $t_link ) : ?>
                    <div class="asd-theme-player">
                        <a href="<?php echo esc_url( $t_link ); ?>" class="asd-theme-play-link" target="_blank" rel="noopener">▶ 試聽</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if ( $streaming_list || ! empty( $tw_streaming_list ) ) : ?>
        <section class="asd-section" id="asd-sec-stream">
            <h2 class="asd-section-title">📡 串流平台</h2>
            <?php if ( ! empty( $tw_streaming_list ) ) : ?>
            <p class="asd-stream-subtitle">🇹🇼 台灣平台</p>
            <div class="asd-stream-grid">
                <?php foreach ( $tw_streaming_list as $plat ) : ?>
                <span class="asd-stream-tag"><?php echo esc_html( $plat ); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ( ! empty( $streaming_list ) ) : ?>
            <p class="asd-stream-subtitle">🌐 其他地區平台</p>
            <div class="asd-stream-grid">
                <?php foreach ( $streaming_list as $s ) :
                    $s_site = $s['site'] ?? '';
                    $s_url  = $s['url']  ?? '';
                    if ( ! $s_site ) continue;
                ?>
                <?php if ( $s_url ) : ?>
                <a href="<?php echo esc_url( $s_url ); ?>" class="asd-stream-btn" target="_blank" rel="noopener">
                    <span class="asd-stream-icon">▶</span><?php echo esc_html( $s_site ); ?>
                </a>
                <?php else : ?>
                <span class="asd-stream-tag"><?php echo esc_html( $s_site ); ?></span>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ( $youtube_id ) : ?>
        <section class="asd-section" id="asd-sec-trailer">
            <h2 class="asd-section-title">🎞️ 預告片</h2>
            <div class="asd-trailer-wrap">
                <iframe class="asd-trailer"
                        src="https://www.youtube.com/embed/<?php echo esc_attr( $youtube_id ); ?>?rel=0"
                        allowfullscreen loading="lazy"
                        title="<?php echo esc_attr( $display_title ); ?> 預告片"></iframe>
            </div>
        </section>
        <?php endif; ?>

        <?php if ( ! empty( $relations_list ) ) : ?>
        <section class="asd-section" id="asd-sec-relations">
            <h2 class="asd-section-title">🔗 相關作品</h2>
            <div class="asd-relations-grid">
            <?php foreach ( $relations_list as $rel ) :
                // Bug 6: 對齊 parse_relations() 實際 key
                $rel_title  = $rel['title_zh']      ?? ( $rel['title']        ?? '' );
                $rel_native = $rel['title_native']   ?? ( $rel['native']       ?? '' );
                $rel_type   = $rel['relation_label'] ?? ( $rel['type']         ?? '' );
                $rel_fmt    = $rel['format']         ?? '';
                $rel_img    = $rel['cover']          ?? ( $rel['cover_image']  ?? '' );
                $rel_url    = $rel['url']            ?? ( $rel['anilist_url']  ?? '' );
                if ( ! $rel_title && ! $rel_native ) continue;
            ?>
            <<?php echo $rel_url ? 'a href="' . esc_url( $rel_url ) . '" ' : 'div '; ?>class="asd-relation-card" target="_blank" rel="noopener">
                <?php /* ABR: img 外包 .asd-relation-thumb，搭配 CSS aspect-ratio + absolute img */ ?>
                <div class="asd-relation-thumb">
                    <?php if ( $rel_img ) : ?>
                    <img src="<?php echo esc_url( $rel_img ); ?>" alt="<?php echo esc_attr( $rel_title ); ?>" loading="lazy">
                    <?php else : ?><div class="asd-relation-noimg">🎬</div><?php endif; ?>
                </div>
                <div class="asd-relation-info">
                    <?php if ( $rel_type )   echo '<span class="asd-relation-type">'   . esc_html( $rel_type )   . '</span>'; ?>
                    <?php if ( $rel_title )  echo '<span class="asd-relation-title">'  . esc_html( $rel_title )  . '</span>'; ?>
                    <?php if ( $rel_native ) echo '<span class="asd-relation-native">' . esc_html( $rel_native ) . '</span>'; ?>
                    <?php if ( $rel_fmt )    echo '<span class="asd-relation-format">' . esc_html( $rel_fmt )    . '</span>'; ?>
                </div>
            </<?php echo $rel_url ? 'a' : 'div'; ?>>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php
        $ext_links = [];
        if ( $official_site ) $ext_links['官方網站']   = $official_site;
        if ( $twitter_url )   $ext_links['X (Twitter)'] = $twitter_url;
        if ( $wikipedia_url ) $ext_links['Wikipedia']  = $wikipedia_url;
        if ( $tiktok_url )    $ext_links['TikTok']     = $tiktok_url;
        if ( $anilist_id )    $ext_links['AniList']    = 'https://anilist.co/anime/' . $anilist_id . '/';
        if ( $mal_id )        $ext_links['MAL']        = 'https://myanimelist.net/anime/' . $mal_id . '/';
        if ( $bangumi_id )    $ext_links['Bangumi']    = 'https://bgm.tv/subject/' . $bangumi_id;
        ?>
        <?php if ( ! empty( $ext_links ) ) : ?>
        <section class="asd-section" id="asd-sec-links">
            <h2 class="asd-section-title">🔗 外部連結</h2>
            <div class="asd-ext-grid">
                <?php foreach ( $ext_links as $label => $href ) : ?>
                <a href="<?php echo esc_url( $href ); ?>" class="asd-ext-link" target="_blank" rel="noopener"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ( ! empty( $faq_items ) ) : ?>
        <section class="asd-section" id="asd-sec-faq">
            <h2 class="asd-section-title">❓ 常見問題</h2>
            <div class="asd-faq-list">
            <?php foreach ( $faq_items as $faq ) : ?>
            <div class="asd-faq-item">
                <h3 class="asd-faq-q"><?php echo esc_html( $faq['q'] ); ?></h3>
                <p class="asd-faq-a"><?php echo esc_html( $faq['a'] ); ?></p>
            </div>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="asd-seo-links">
            <?php if ( ! empty( $genre_terms ) ) : ?>
            <div class="asd-seo-row">
                <span class="asd-seo-label">類型：</span>
                <?php foreach ( $genre_terms as $gt ) : ?>
                <a href="<?php echo esc_url( get_term_link( $gt ) ); ?>" class="asd-seo-tag"><?php echo esc_html( $gt->name ); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ( ! empty( $season_terms ) ) : ?>
            <div class="asd-seo-row">
                <span class="asd-seo-label">季度：</span>
                <?php foreach ( $season_terms as $st ) : ?>
                <a href="<?php echo esc_url( get_term_link( $st ) ); ?>" class="asd-seo-tag"><?php echo esc_html( $st->name ); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="asd-footer">
            <p class="asd-footer-src">資料來源：AniList・Bangumi・MyAnimeList</p>
            <?php if ( $last_sync ) : ?>
            <p class="asd-footer-sync">最後同步：<?php echo esc_html( date( 'Y-m-d H:i', strtotime( $last_sync ) ) ); ?></p>
            <?php endif; ?>
        </div>

        <div class="asd-comments-wrap">
            <?php comments_template(); ?>
        </div>

    </main>

    <aside class="asd-sidebar">

        <?php if ( ! empty( $news_posts ) ) : ?>
        <div class="asd-sidebar-block">
            <h3 class="asd-sidebar-title">相關新聞</h3>
            <ul class="asd-news-list">
            <?php foreach ( $news_posts as $np ) : ?>
            <li>
                <a href="<?php echo esc_url( get_permalink( $np->ID ) ); ?>"><?php echo esc_html( get_the_title( $np->ID ) ); ?></a>
                <span class="asd-news-date"><?php echo esc_html( get_the_date( 'Y-m-d', $np->ID ) ); ?></span>
            </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $relations_list ) ) : ?>
        <div class="asd-sidebar-block">
            <h3 class="asd-sidebar-title">系列作品</h3>
            <?php foreach ( array_slice( $relations_list, 0, 5 ) as $rel ) :
                $rel_title  = $rel['title_zh']      ?? ( $rel['title']       ?? '' );
                $rel_type   = $rel['relation_label'] ?? ( $rel['type']        ?? '' );
                $rel_img    = $rel['cover']          ?? ( $rel['cover_image'] ?? '' );
                $rel_url    = $rel['url']            ?? ( $rel['anilist_url'] ?? '' );
                if ( ! $rel_title ) continue;
            ?>
            <a href="<?php echo $rel_url ? esc_url( $rel_url ) : '#'; ?>" class="asd-sidebar-rel-card" target="_blank" rel="noopener">
                <?php /* ABR: img 外包 .asd-sidebar-rel-thumb，搭配 CSS 44×62 + absolute img */ ?>
                <div class="asd-sidebar-rel-thumb">
                    <?php if ( $rel_img ) : ?>
                    <img src="<?php echo esc_url( $rel_img ); ?>" alt="<?php echo esc_attr( $rel_title ); ?>" loading="lazy">
                    <?php else : ?><div class="asd-sidebar-rel-noimg">🎬</div><?php endif; ?>
                </div>
                <div class="asd-sidebar-rel-info">
                    <?php if ( $rel_type ) echo '<span class="asd-sidebar-rel-label">' . esc_html( $rel_type ) . '</span>'; ?>
                    <span class="asd-sidebar-rel-title"><?php echo esc_html( $rel_title ); ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php /* ── 側欄推薦（ABP：asd-sidebar-rec-thumb-wrap 防跑版）── */ ?>
        <?php if ( ! empty( $recommend_posts ) ) : ?>
        <div class="asd-sidebar-block">
            <h3 class="asd-sidebar-title">你可能也喜歡</h3>
            <?php foreach ( array_slice( $recommend_posts, 0, 5 ) as $rec ) :
                $rec_id        = $rec->ID;
                $rec_title     = get_post_meta( $rec_id, 'anime_title_chinese', true ) ?: get_the_title( $rec_id );
                $rec_score_raw = get_post_meta( $rec_id, 'anime_score_anilist', true );
                $rec_score     = is_numeric( $rec_score_raw ) && $rec_score_raw > 0
                                 ? number_format( (float) $rec_score_raw / 10, 1 ) : '';
                $rec_cover     = get_post_meta( $rec_id, 'anime_cover_image', true );
            ?>
            <a href="<?php echo esc_url( get_permalink( $rec_id ) ); ?>" class="asd-sidebar-rec-card">
                <div class="asd-sidebar-rec-thumb-wrap">
                    <?php if ( $rec_cover ) : ?>
                    <img src="<?php echo esc_url( $rec_cover ); ?>" alt="<?php echo esc_attr( $rec_title ); ?>" loading="lazy">
                    <?php elseif ( has_post_thumbnail( $rec_id ) ) : ?>
                    <?php echo get_the_post_thumbnail( $rec_id, 'thumbnail', ['loading' => 'lazy'] ); ?>
                    <?php else : ?><div class="asd-sidebar-rec-noimg">🎬</div><?php endif; ?>
                </div>
                <div class="asd-sidebar-rec-info">
                    <span class="asd-sidebar-rec-title"><?php echo esc_html( $rec_title ); ?></span>
                    <?php if ( $rec_score ) echo '<span class="asd-sidebar-rec-score">⭐ ' . esc_html( $rec_score ) . '</span>'; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </aside>

</div>

<?php /* ══ 底部熱門推薦（ABP：asd-rec-thumb-wrap 防跑版）══ */ ?>
<?php if ( ! empty( $recommend_posts ) ) : ?>
<section class="asd-bottom-recs">
    <h2 class="asd-bottom-recs-title">🔥 熱門推薦</h2>
    <div class="asd-bottom-recs-grid">
    <?php foreach ( $recommend_posts as $rec ) :
        $rec_id        = $rec->ID;
        $rec_title     = get_post_meta( $rec_id, 'anime_title_chinese', true ) ?: get_the_title( $rec_id );
        $rec_score_raw = get_post_meta( $rec_id, 'anime_score_anilist', true );
        $rec_score     = is_numeric( $rec_score_raw ) && $rec_score_raw > 0
                         ? number_format( (float) $rec_score_raw / 10, 1 ) : '';
        $rec_cover     = get_post_meta( $rec_id, 'anime_cover_image', true );
    ?>
    <a href="<?php echo esc_url( get_permalink( $rec_id ) ); ?>" class="asd-rec-card">
        <div class="asd-rec-thumb-wrap">
            <?php if ( $rec_cover ) : ?>
            <img src="<?php echo esc_url( $rec_cover ); ?>" alt="<?php echo esc_attr( $rec_title ); ?>" loading="lazy">
            <?php elseif ( has_post_thumbnail( $rec_id ) ) : ?>
            <?php echo get_the_post_thumbnail( $rec_id, 'large', ['loading' => 'lazy'] ); ?>
            <?php else : ?><div class="asd-rec-noimg">🎬</div><?php endif; ?>
        </div>
        <div class="asd-rec-info">
            <span class="asd-rec-title"><?php echo esc_html( $rec_title ); ?></span>
            <?php if ( $rec_score ) echo '<span class="asd-rec-score">⭐ ' . esc_html( $rec_score ) . '</span>'; ?>
        </div>
    </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

</div>

<script>
(function(){
    // 集數展開
    var epBtn = document.getElementById('asd-ep-more-btn');
    if ( epBtn ) {
        epBtn.addEventListener('click', function(){
            document.querySelectorAll('.asd-ep-hidden').forEach(function(r){ r.style.display = ''; r.classList.remove('asd-ep-hidden'); });
            epBtn.style.display = 'none';
        });
    }
    // 角色展開
    var castBtn = document.getElementById('asd-cast-more-btn');
    if ( castBtn ) {
        castBtn.addEventListener('click', function(){
            document.querySelectorAll('.asd-cast-extra').forEach(function(c){ c.style.display = ''; c.classList.remove('asd-cast-extra'); });
            castBtn.style.display = 'none';
        });
    }
    // 播出倒數
    var cd = document.querySelector('.asd-countdown');
    if ( cd ) {
        var ts = parseInt( cd.dataset.ts, 10 ) * 1000;
        function tick(){
            var diff = ts - Date.now();
            if ( diff <= 0 ) { cd.textContent = '即將播出'; return; }
            var d = Math.floor( diff / 86400000 );
            var h = Math.floor( ( diff % 86400000 ) / 3600000 );
            var m = Math.floor( ( diff % 3600000 )  / 60000 );
            var s = Math.floor( ( diff % 60000 )    / 1000 );
            cd.textContent = d + ' 天 ' + h + ' 時 ' + m + ' 分 ' + s + ' 秒';
        }
        tick(); setInterval( tick, 1000 );
    }
    // Tab 高亮
    var tabs = document.querySelectorAll('.asd-tab');
    var secs = document.querySelectorAll('.asd-section[id]');
    if ( tabs.length && secs.length ) {
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(e){
                if ( e.isIntersecting ){
                    tabs.forEach(function(t){ t.classList.remove('active'); });
                    var act = document.querySelector('.asd-tab[href="#' + e.target.id + '"]');
                    if ( act ) act.classList.add('active');
                }
            });
        }, { rootMargin: '-20% 0px -70% 0px' });
        secs.forEach(function(s){ io.observe(s); });
    }
})();
</script>

<?php endwhile; ?>
<?php get_footer(); ?>

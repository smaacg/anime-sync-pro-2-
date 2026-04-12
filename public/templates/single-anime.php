<?php
/**
 * Single Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/single-anime.php
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

    /* ── IDs ───────────────────────────────────────────────── */
    $anilist_id = (int) get_post_meta( $post_id, 'anime_anilist_id', true );
    $mal_id     = (int) get_post_meta( $post_id, 'anime_mal_id',     true );
    $bangumi_id = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );

    /* ── 標題 ──────────────────────────────────────────────── */
    $title_chinese = get_post_meta( $post_id, 'anime_title_chinese', true );
    $title_native  = get_post_meta( $post_id, 'anime_title_native',  true );
    $title_romaji  = get_post_meta( $post_id, 'anime_title_romaji',  true );
    $title_english = get_post_meta( $post_id, 'anime_title_english', true );
    $display_title = $title_chinese ?: get_the_title();

    /* ── 基本資訊 ───────────────────────────────────────────── */
    $format      = get_post_meta( $post_id, 'anime_format',               true );
    $status      = get_post_meta( $post_id, 'anime_status',               true );
    $season      = get_post_meta( $post_id, 'anime_season',               true );
    $season_year = (int) get_post_meta( $post_id, 'anime_season_year',    true );
    $episodes    = (int) get_post_meta( $post_id, 'anime_episodes',       true );
    $ep_aired    = (int) get_post_meta( $post_id, 'anime_episodes_aired', true );
    $duration    = (int) get_post_meta( $post_id, 'anime_duration',       true );
    $source      = get_post_meta( $post_id, 'anime_source',               true );
    // ✅ 問題 W 修正：key 改為 anime_studios（有 s），與 import-manager 寫入對齊
    $studio      = get_post_meta( $post_id, 'anime_studios',              true );
    $tw_dist     = get_post_meta( $post_id, 'anime_tw_distributor',       true );
    $tw_bc       = get_post_meta( $post_id, 'anime_tw_broadcast',         true );
    $popularity  = (int) get_post_meta( $post_id, 'anime_popularity',     true );

    /* ── 日期 ───────────────────────────────────────────────── */
    $format_date = function( $raw ) {
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

    /* ── 評分 ───────────────────────────────────────────────── */
    $score_anilist_raw = get_post_meta( $post_id, 'anime_score_anilist', true );
    // ✅ Bug 6 修正：score 為 0 時不輸出 aggregateRating Schema
    // AniList 原始值 0–100，除以 10 顯示為 0–10 分制
    $score_anilist_num = is_numeric( $score_anilist_raw ) ? (float) $score_anilist_raw : 0;
    $score_anilist     = $score_anilist_num > 0
        ? number_format( $score_anilist_num / 10, 1 )
        : '';
    $score_mal         = get_post_meta( $post_id, 'anime_score_mal',     true );
    $score_bangumi     = get_post_meta( $post_id, 'anime_score_bangumi', true );

    /* ── 圖片 / 預告 ────────────────────────────────────────── */
    $cover_image  = get_post_meta( $post_id, 'anime_cover_image',  true );
    $banner_image = get_post_meta( $post_id, 'anime_banner_image', true );
    $trailer_url  = get_post_meta( $post_id, 'anime_trailer_url',  true );

    $youtube_id = '';
    if ( $trailer_url ) {
        // 支援多個網址（textarea 格式），取第一個有效的 YouTube URL
        $trailer_urls = array_filter( array_map( 'trim', preg_split( '/[,\n]+/', $trailer_url ) ) );
        foreach ( $trailer_urls as $t_url ) {
            if ( preg_match( '/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{11})/', $t_url, $ym ) ) {
                $youtube_id = $ym[1];
                break;
            } elseif ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $t_url ) ) {
                $youtube_id = $t_url;
                break;
            }
        }
    }

    /* ── 外部連結 ───────────────────────────────────────────── */
    $official_site = get_post_meta( $post_id, 'anime_official_site', true );
    $twitter_url   = get_post_meta( $post_id, 'anime_twitter_url',   true );
    $wikipedia_url = get_post_meta( $post_id, 'anime_wikipedia_url', true );
    $tiktok_url    = get_post_meta( $post_id, 'anime_tiktok_url',    true );

    /* ── 下集播出 ───────────────────────────────────────────── */
    $next_airing_raw = get_post_meta( $post_id, 'anime_next_airing', true );
    $airing_data     = [];
    if ( $next_airing_raw ) {
        $decoded = is_array( $next_airing_raw ) ? $next_airing_raw : json_decode( $next_airing_raw, true );
        if ( is_array( $decoded ) ) $airing_data = $decoded;
    }

    /* ── 最後同步 ───────────────────────────────────────────── */
    $last_sync = get_post_meta( $post_id, 'anime_last_sync', true );

    /* ── 劇情簡介 ───────────────────────────────────────────── */
    $synopsis_raw = get_post_meta( $post_id, 'anime_synopsis_chinese', true );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = get_post_meta( $post_id, 'anime_synopsis', true );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = get_the_content();
    $synopsis = trim( $synopsis_raw );

    /* ── JSON 解碼 ──────────────────────────────────────────── */
    $decode_json = function( $raw ) {
        if ( is_array( $raw ) ) return $raw;
        if ( ! is_string( $raw ) || empty( $raw ) ) return [];
        $d = json_decode( $raw, true );
        return is_array( $d ) ? $d : [];
    };
    // ✅ 問題 V 修正：key 改為 anime_streaming / anime_themes，與 import-manager 寫入對齊
    $streaming_list = $decode_json( get_post_meta( $post_id, 'anime_streaming',     true ) );
    $themes_list    = $decode_json( get_post_meta( $post_id, 'anime_themes',        true ) );
    $cast_list      = $decode_json( get_post_meta( $post_id, 'anime_cast_json',     true ) );
    $staff_list     = $decode_json( get_post_meta( $post_id, 'anime_staff_json',    true ) );
    $relations_list = $decode_json( get_post_meta( $post_id, 'anime_relations_json',true ) );

    /* ── OP/ED 分組去重 ─────────────────────────────────────── */
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

    /* ── Label Maps ─────────────────────────────────────────── */
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

    /* ── Taxonomy 資料 ───────────────────────────────────────── */
    $genre_terms  = get_the_terms( $post_id, 'genre' )            ?: [];
    $season_terms = get_the_terms( $post_id, 'anime_season_tax' ) ?: [];
    $format_terms = get_the_terms( $post_id, 'anime_format_tax' ) ?: [];

    /* ── 熱門推薦 ────────────────────────────────────────────── */
    $recommend_posts = [];
    if ( ! empty( $genre_terms ) ) {
        $genre_ids       = wp_list_pluck( $genre_terms, 'term_id' );
        $recommend_query = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'post__not_in'   => [ $post_id ],
            'tax_query'      => [ [
                'taxonomy' => 'genre',
                'field'    => 'term_id',
                'terms'    => $genre_ids,
                'operator' => 'IN',
            ] ],
            'meta_key'      => 'anime_score_anilist',
            'orderby'       => 'meta_value_num',
            'order'         => 'DESC',
            'no_found_rows' => true,
        ] );
        $recommend_posts = $recommend_query->posts;
        wp_reset_postdata();
    }

    /* ── 相關新聞 ────────────────────────────────────────────── */
    $news_posts = [];
    if ( $display_title ) {
        $tag_slug   = sanitize_title( $display_title );
        $news_query = new WP_Query( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'tag'            => $tag_slug,
            'no_found_rows'  => true,
        ] );
        $news_posts = $news_query->posts;
        wp_reset_postdata();

        if ( empty( $news_posts ) && $title_romaji ) {
            $news_query2 = new WP_Query( [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 5,
                's'              => $title_romaji,
                'no_found_rows'  => true,
            ] );
            $news_posts = $news_query2->posts;
            wp_reset_postdata();
        }
    }

    /* ── Schema：根據 format 自動切換類型 ───────────────────── */
    $schema_type = 'TVSeries';
    if ( $format === 'MOVIE' ) $schema_type = 'Movie';
    if ( $format === 'MUSIC' ) $schema_type = 'MusicVideoObject';

    $schema_genres = [];
    foreach ( $genre_terms as $gt ) $schema_genres[] = $gt->name;

    // ✅ Bug 9 修正：alternateName 全空時不輸出該欄位
    $alternate_names = array_values( array_filter( [ $title_native, $title_romaji, $title_english ] ) );

    $schema = [
        '@context'      => 'https://schema.org',
        '@type'         => $schema_type,
        'name'          => $display_title,
        'description'   => mb_substr( wp_strip_all_tags( $synopsis ), 0, 200 ),
        'image'         => $cover_image ?: get_the_post_thumbnail_url( $post_id, 'large' ),
        'genre'         => $schema_genres,
        'datePublished' => $start_date,
        'url'           => get_permalink( $post_id ),
    ];

    // ✅ Bug 9 修正：只在有值時才加入 alternateName
    if ( ! empty( $alternate_names ) ) {
        $schema['alternateName'] = $alternate_names;
    }

    if ( $episodes ) {
        $schema['numberOfEpisodes'] = $episodes;
    }

    // ✅ Bug 6 修正：score > 0 才輸出 aggregateRating
    if ( $score_anilist_num > 0 ) {
        $schema['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format( $score_anilist_num / 10, 1 ),
            'bestRating'  => '10',
            'worstRating' => '1',
            'ratingCount' => max( 1, $popularity ),
        ];
    }

    /* ── Schema：麵包屑 ─────────────────────────────────────── */
    $breadcrumb_schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            [ '@type' => 'ListItem', 'position' => 1, 'name' => '首頁',     'item' => home_url( '/' ) ],
            [ '@type' => 'ListItem', 'position' => 2, 'name' => '動畫列表', 'item' => home_url( '/anime/' ) ],
            [ '@type' => 'ListItem', 'position' => 3, 'name' => $display_title, 'item' => get_permalink( $post_id ) ],
        ],
    ];
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<script type="application/ld+json"><?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>

<div class="asd-wrap">

<?php if ( $banner_image ) : ?>
<div class="asd-banner" style="background-image:url(<?php echo esc_url( $banner_image ); ?>)">
    <div class="asd-banner-fade"></div>
</div>
<?php endif; ?>

<nav class="asd-breadcrumb" aria-label="麵包屑導航">
    <ol>
        <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">首頁</a></li>
        <li><a href="<?php echo esc_url( home_url( '/anime/' ) ); ?>">動畫列表</a></li>
        <li><?php echo esc_html( $display_title ); ?></li>
    </ol>
</nav>

<div class="asd-hero">
    <div class="asd-hero-cover">
        <?php if ( $cover_image ) : ?>
        <img src="<?php echo esc_url( $cover_image ); ?>"
             alt="<?php echo esc_attr( $display_title ); ?> 封面圖"
             class="asd-cover-img" loading="eager">
        <?php elseif ( has_post_thumbnail() ) : ?>
        <?php the_post_thumbnail( 'large', [ 'class' => 'asd-cover-img', 'loading' => 'eager' ] ); ?>
        <?php endif; ?>
    </div>

    <div class="asd-hero-info">
        <h1 class="asd-title"><?php echo esc_html( $display_title ); ?></h1>
        <?php if ( $title_native )  : ?><p class="asd-title-native"><?php echo esc_html( $title_native );  ?></p><?php endif; ?>
        <?php if ( $title_romaji )  : ?><p class="asd-title-romaji"><?php echo esc_html( $title_romaji );  ?></p><?php endif; ?>
        <?php if ( $title_english ) : ?><p class="asd-title-english"><?php echo esc_html( $title_english ); ?></p><?php endif; ?>

        <div class="asd-badges">
            <?php if ( $status_label ) echo '<span class="asd-badge asd-badge-status ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>'; ?>
            <?php if ( $format_label ) echo '<span class="asd-badge asd-badge-format">' . esc_html( $format_label ) . '</span>'; ?>
            <?php if ( $season_label && $season_year ) echo '<span class="asd-badge asd-badge-season">' . esc_html( $season_year . ' ' . $season_label ) . '</span>'; ?>
        </div>

        <div class="asd-hero-scores">
            <?php if ( $score_anilist ) : ?>
            <div class="asd-hero-score asd-score-al">
                <span class="asd-score-label">⭐ AniList</span>
                <span class="asd-score-val"><?php echo esc_html( $score_anilist ); ?></span>
                <span class="asd-score-max">/ 10</span>
            </div>
            <?php endif; ?>
            <?php if ( $score_mal ) : ?>
            <div class="asd-hero-score asd-score-mal">
                <span class="asd-score-label">⭐ MAL</span>
                <span class="asd-score-val"><?php echo esc_html( $score_mal ); ?></span>
                <span class="asd-score-max">/ 10</span>
            </div>
            <?php endif; ?>
            <?php if ( $score_bangumi ) : ?>
            <div class="asd-hero-score asd-score-bgm">
                <span class="asd-score-label">⭐ Bangumi</span>
                <span class="asd-score-val"><?php echo esc_html( $score_bangumi ); ?></span>
                <span class="asd-score-max">/ 10</span>
            </div>
            <?php endif; ?>
            <?php if ( $popularity ) : ?>
            <div class="asd-hero-score asd-score-pop">
                <span class="asd-score-label">👥 人氣</span>
                <span class="asd-score-val"><?php echo esc_html( number_format( $popularity ) ); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ( $youtube_id ) : ?>
        <a class="asd-trailer-btn"
           href="https://www.youtube.com/watch?v=<?php echo esc_attr( $youtube_id ); ?>"
           target="_blank" rel="noopener">▶ 觀看預告片</a>
        <?php endif; ?>
    </div>
</div>

<nav class="asd-tabs" id="asd-tabs">
    <a class="asd-tab active" href="#asd-sec-info">📋 基本資訊</a>
    <?php if ( $synopsis )          : ?><a class="asd-tab" href="#asd-sec-synopsis">📝 劇情簡介</a><?php endif; ?>
    <?php if ( $cast_list )         : ?><a class="asd-tab" href="#asd-sec-cast">🎭 角色聲優</a><?php endif; ?>
    <?php if ( $staff_list )        : ?><a class="asd-tab" href="#asd-sec-staff">🎬 製作人員</a><?php endif; ?>
    <?php if ($openings || $endings): ?><a class="asd-tab" href="#asd-sec-music">🎵 主題曲</a><?php endif; ?>
    <?php if ( $streaming_list )    : ?><a class="asd-tab" href="#asd-sec-stream">📺 串流平台</a><?php endif; ?>
    <?php if ( $relations_list )    : ?><a class="asd-tab" href="#asd-sec-relations">🎬 相關作品</a><?php endif; ?>
    <?php if ( $news_posts )        : ?><a class="asd-tab" href="#asd-sec-news">📰 相關新聞</a><?php endif; ?>
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
                '台灣代理' => $tw_dist,
                '台灣播出' => $tw_bc,
            ];
            foreach ( $info_rows as $label => $val ) :
                if ( empty( $val ) ) continue;
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

        <?php if ( $cast_list ) : ?>
        <section class="asd-section" id="asd-sec-cast">
            <h2 class="asd-section-title">🎭 角色與聲優</h2>
            <div class="asd-cast-grid" id="asd-cast-grid">
                <?php foreach ( $cast_list as $i => $c ) :
                    $char_name = $c['char_name_zh'] ?? ( $c['char_name_ja'] ?? '' );
                    $char_img  = $c['char_image']   ?? '';
                    $va_name   = $c['va_name']       ?? ( $c['name'] ?? '' );
                    $is_extra  = $i >= 12;
                ?>
                <div class="asd-cast-card<?php echo $is_extra ? ' asd-cast-extra' : ''; ?>">
                    <div class="asd-cast-char-img">
                        <?php if ( $char_img ) : ?>
                        <img src="<?php echo esc_url( $char_img ); ?>"
                             alt="<?php echo esc_attr( $char_name ); ?>" loading="lazy">
                        <?php else : ?><div class="asd-cast-noimg">?</div><?php endif; ?>
                    </div>
                    <div class="asd-cast-names">
                        <?php if ( $char_name ) echo '<span class="asd-cast-char">' . esc_html( $char_name ) . '</span>'; ?>
                        <?php if ( $va_name )   echo '<span class="asd-cast-va">CV: '  . esc_html( $va_name )   . '</span>'; ?>
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
                    <?php if ( $s_img ) : ?>
                    <img src="<?php echo esc_url( $s_img ); ?>"
                         alt="<?php echo esc_attr( $s_name ); ?>" loading="lazy">
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
            <?php foreach ( [ '片頭曲 OP' => $openings, '片尾曲 ED' => $endings ] as $grp_title => $grp ) :
                if ( ! $grp ) continue; ?>
            <div class="asd-theme-group">
                <h3 class="asd-theme-group-title"><?php echo esc_html( $grp_title ); ?></h3>
                <?php foreach ( $grp as $t ) :
                    $label      = $t['label']      ?? ( ( $t['type'] ?? 'OP' ) . ( $t['sequence'] ?? 1 ) );
                    $song_title = $t['song_title'] ?? $t['title']  ?? '';
                    $artists    = $t['artists']    ?? [];
                    if ( is_string( $artists ) ) $artists = array_filter( [ $artists ] );
                    $artist_str = implode( '・', $artists );
                    $video_url  = $t['video_url']  ?? $t['video']  ?? '';
                    $is_webm    = $video_url && str_ends_with(
                        strtolower( (string) parse_url( $video_url, PHP_URL_PATH ) ), '.webm'
                    );
                ?>
                <div class="asd-theme-row">
                    <span class="asd-theme-label"><?php echo esc_html( $label ); ?></span>
                    <div class="asd-theme-meta">
                        <?php if ( $song_title ) echo '<span class="asd-theme-title">'  . esc_html( $song_title ) . '</span>'; ?>
                        <?php if ( $artist_str ) echo '<span class="asd-theme-artist">' . esc_html( $artist_str ) . '</span>'; ?>
                    </div>
                    <?php if ( $video_url ) : ?>
                    <div class="asd-theme-player">
                        <?php if ( $is_webm ) : ?>
                        <audio class="asd-theme-audio" controls preload="none">
                            <source src="<?php echo esc_url( $video_url ); ?>" type="video/webm">
                        </audio>
                        <?php else : ?>
                        <a class="asd-theme-play-link"
                           href="<?php echo esc_url( $video_url ); ?>"
                           target="_blank" rel="noopener">▶ 試聽</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if ( $streaming_list ) : ?>
        <section class="asd-section" id="asd-sec-stream">
            <h2 class="asd-section-title">📺 串流平台</h2>
            <div class="asd-stream-grid">
            <?php foreach ( $streaming_list as $pl ) :
                $pname = $pl['platform'] ?? $pl['site'] ?? '';
                $purl  = $pl['url'] ?? '';
                if ( ! $purl ) continue;
            ?>
                <a href="<?php echo esc_url( $purl ); ?>"
                   target="_blank" rel="noopener" class="asd-stream-btn">
                    <span class="asd-stream-icon">▶</span><?php echo esc_html( $pname ); ?>
                </a>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ( $youtube_id ) : ?>
        <section class="asd-section" id="asd-sec-trailer">
            <h2 class="asd-section-title">🎞️ 預告片</h2>
            <div class="asd-trailer-wrap">
                <iframe class="asd-trailer"
                    src="https://www.youtube.com/embed/<?php echo esc_attr( $youtube_id ); ?>?rel=0"
                    allowfullscreen loading="lazy"
                    title="<?php echo esc_attr( $display_title ); ?> 預告片">
                </iframe>
            </div>
        </section>
        <?php endif; ?>

        <?php if ( $relations_list ) : ?>
        <section class="asd-section" id="asd-sec-relations">
            <h2 class="asd-section-title">🎬 相關作品</h2>
            <div class="asd-relations-grid">
            <?php foreach ( $relations_list as $rel ) :
                $rel_anilist_id = (int) ( $rel['anilist_id'] ?? 0 );
                $rel_title      = $rel['title_chinese'] ?: ( $rel['title_romaji'] ?? '' );
                $rel_cover      = $rel['cover_image']   ?? '';
                $rel_label      = $rel['relation_label'] ?? '';
                $rel_format     = $format_labels[ $rel['format'] ?? '' ] ?? ( $rel['format'] ?? '' );

                $rel_post = null;
                if ( $rel_anilist_id ) {
                    $rel_q = new WP_Query( [
                        'post_type'      => 'anime',
                        'post_status'    => 'publish',
                        'meta_query'     => [ [ 'key' => 'anime_anilist_id', 'value' => $rel_anilist_id ] ],
                        'fields'         => 'ids',
                        'posts_per_page' => 1,
                        'no_found_rows'  => true,
                    ] );
                    if ( $rel_q->have_posts() ) $rel_post = $rel_q->posts[0];
                    wp_reset_postdata();
                }

                $rel_url = $rel_post
                    ? get_permalink( $rel_post )
                    : ( $rel_anilist_id ? 'https://anilist.co/anime/' . $rel_anilist_id . '/' : '#' );
            ?>
            <a href="<?php echo esc_url( $rel_url ); ?>"
               class="asd-relation-card"
               <?php echo ! $rel_post ? 'target="_blank" rel="noopener"' : ''; ?>>
                <?php if ( $rel_cover ) : ?>
                <img src="<?php echo esc_url( $rel_cover ); ?>"
                     alt="<?php echo esc_attr( $rel_title ); ?>" loading="lazy">
                <?php endif; ?>
                <div class="asd-relation-info">
                    <span class="asd-relation-type"><?php echo esc_html( $rel_label ); ?></span>
                    <span class="asd-relation-title"><?php echo esc_html( $rel_title ); ?></span>
                    <?php if ( $rel_format ) echo '<span class="asd-relation-format">' . esc_html( $rel_format ) . '</span>'; ?>
                </div>
            </a>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="asd-section" id="asd-sec-links">
            <h2 class="asd-section-title">🔗 外部連結</h2>
            <div class="asd-ext-grid">
                <?php if ( $official_site )  echo '<a href="' . esc_url( $official_site )  . '" class="asd-ext-link" target="_blank" rel="noopener">🌐 官方網站</a>'; ?>
                <?php if ( $twitter_url )    echo '<a href="' . esc_url( $twitter_url )    . '" class="asd-ext-link" target="_blank" rel="noopener">𝕏 Twitter / X</a>'; ?>
                <?php if ( $wikipedia_url )  echo '<a href="' . esc_url( $wikipedia_url )  . '" class="asd-ext-link" target="_blank" rel="noopener">📖 Wikipedia</a>'; ?>
                <?php if ( $tiktok_url )     echo '<a href="' . esc_url( $tiktok_url )     . '" class="asd-ext-link" target="_blank" rel="noopener">🎵 TikTok</a>'; ?>
                <?php if ( $anilist_id )     echo '<a href="https://anilist.co/anime/'         . esc_attr( $anilist_id ) . '/" class="asd-ext-link" target="_blank" rel="noopener">◈ AniList</a>'; ?>
                <?php if ( $mal_id )         echo '<a href="https://myanimelist.net/anime/'    . esc_attr( $mal_id )     . '/" class="asd-ext-link" target="_blank" rel="noopener">◉ MyAnimeList</a>'; ?>
                <?php if ( $bangumi_id )     echo '<a href="https://bgm.tv/subject/'           . esc_attr( $bangumi_id ) . '" class="asd-ext-link" target="_blank" rel="noopener">◎ Bangumi</a>'; ?>
            </div>
        </section>

        <?php if ( shortcode_exists( 'yasr_visitor_votes' ) ) : ?>
        <section class="asd-section">
            <h2 class="asd-section-title">⭐ 讀者評分</h2>
            <?php echo do_shortcode( '[yasr_visitor_votes size="large" show_count="yes"]' ); ?>
        </section>
        <?php endif; ?>

        <div class="asd-seo-links">
            <?php if ( $genre_terms ) : ?>
            <div class="asd-seo-row">
                <span class="asd-seo-label">動畫類型：</span>
                <?php foreach ( $genre_terms as $gt ) : ?>
                <a href="<?php echo esc_url( get_term_link( $gt ) ); ?>" class="asd-seo-tag">
                    <?php echo esc_html( $gt->name ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php
            $child_seasons = array_filter( $season_terms, fn( $t ) => $t->parent > 0 );
            if ( $child_seasons ) :
            ?>
            <div class="asd-seo-row">
                <span class="asd-seo-label">播出季度：</span>
                <?php foreach ( $child_seasons as $st ) : ?>
                <a href="<?php echo esc_url( get_term_link( $st ) ); ?>" class="asd-seo-tag">
                    <?php echo esc_html( $st->name ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ( $format_terms ) : ?>
            <div class="asd-seo-row">
                <span class="asd-seo-label">動畫格式：</span>
                <?php foreach ( $format_terms as $ft ) : ?>
                <a href="<?php echo esc_url( get_term_link( $ft ) ); ?>" class="asd-seo-tag">
                    <?php echo esc_html( $ft->name ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <footer class="asd-footer">
            <p class="asd-footer-src">資料來源：
            <?php
            $srcs = [];
            if ( $anilist_id )           $srcs[] = '<a href="https://anilist.co/anime/'     . esc_attr( $anilist_id ) . '/" target="_blank" rel="noopener">AniList</a>';
            if ( $mal_id )               $srcs[] = '<a href="https://myanimelist.net/anime/' . esc_attr( $mal_id )    . '/" target="_blank" rel="noopener">MyAnimeList</a>';
            if ( $bangumi_id )           $srcs[] = '<a href="https://bgm.tv/subject/'        . esc_attr( $bangumi_id ). '" target="_blank" rel="noopener">Bangumi</a>';
            if ( $openings || $endings ) $srcs[] = '<a href="https://animethemes.moe/" target="_blank" rel="noopener">AnimeThemes</a>';
            echo implode( ' ／ ', $srcs );
            ?>
            </p>
            <?php if ( $last_sync ) : ?>
            <p class="asd-footer-sync">最後同步：<?php echo esc_html( gmdate( 'Y-m-d H:i', is_numeric( $last_sync ) ? (int) $last_sync : strtotime( $last_sync ) ) ); ?> UTC</p>
            <?php endif; ?>
        </footer>

        <?php if ( comments_open() || get_comments_number() ) : ?>
        <div class="asd-comments-wrap">
            <?php comments_template(); ?>
        </div>
        <?php endif; ?>

    </main>

    <aside class="asd-sidebar">

        <?php if ( $news_posts ) : ?>
        <div class="asd-sidebar-block" id="asd-sec-news">
            <h3 class="asd-sidebar-title">📰 相關新聞</h3>
            <ul class="asd-news-list">
            <?php foreach ( $news_posts as $news_post ) : ?>
                <li>
                    <a href="<?php echo esc_url( get_permalink( $news_post ) ); ?>">
                        <?php echo esc_html( get_the_title( $news_post ) ); ?>
                    </a>
                    <span class="asd-news-date">
                        <?php echo esc_html( get_the_date( 'Y-m-d', $news_post ) ); ?>
                    </span>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ( $recommend_posts ) : ?>
        <div class="asd-sidebar-block">
            <h3 class="asd-sidebar-title">🔥 熱門推薦</h3>
            <ul class="asd-recommend-list">
            <?php foreach ( $recommend_posts as $rec ) :
                $rec_cover = get_post_meta( $rec->ID, 'anime_cover_image', true )
                          ?: get_the_post_thumbnail_url( $rec->ID, 'thumbnail' );
                $rec_title = get_post_meta( $rec->ID, 'anime_title_chinese', true )
                          ?: get_the_title( $rec );
                $rec_score_raw = get_post_meta( $rec->ID, 'anime_score_anilist', true );
                $rec_score     = ( is_numeric( $rec_score_raw ) && (float) $rec_score_raw > 0 )
                    ? number_format( (float) $rec_score_raw / 10, 1 )
                    : '';
            ?>
                <li>
                    <a href="<?php echo esc_url( get_permalink( $rec ) ); ?>" class="asd-recommend-item">
                        <?php if ( $rec_cover ) : ?>
                        <img src="<?php echo esc_url( $rec_cover ); ?>"
                             alt="<?php echo esc_attr( $rec_title ); ?>" loading="lazy">
                        <?php endif; ?>
                        <div class="asd-recommend-info">
                            <span class="asd-recommend-title"><?php echo esc_html( $rec_title ); ?></span>
                            <?php if ( $rec_score ) : ?>
                            <span class="asd-recommend-score">⭐ <?php echo esc_html( $rec_score ); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </aside>

</div><!-- .asd-container -->
</div><!-- .asd-wrap -->

<script>
/* ── Show More Cast ─────────────────────────────────── */
(function(){
    const btn  = document.getElementById('asd-cast-more-btn');
    const grid = document.getElementById('asd-cast-grid');
    if ( ! btn || ! grid ) return;
    btn.addEventListener('click', function(){
        grid.querySelectorAll('.asd-cast-extra').forEach(el => el.style.display = 'flex');
        btn.parentElement.style.display = 'none';
    });
})();

/* ── Tab 切換 ───────────────────────────────────────── */
(function(){
    const tabs = document.querySelectorAll('.asd-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e){
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
})();

/* ── Countdown Timer ────────────────────────────────── */
(function(){
    const el = document.querySelector('.asd-countdown');
    if ( ! el ) return;
    const ts = parseInt( el.dataset.ts, 10 ) * 1000;
    function update(){
        const diff = ts - Date.now();
        if ( diff <= 0 ) { el.textContent = '即將播出'; return; }
        const d = Math.floor( diff / 86400000 );
        const h = Math.floor( ( diff % 86400000 ) / 3600000 );
        const m = Math.floor( ( diff % 3600000  ) / 60000   );
        const s = Math.floor( ( diff % 60000    ) / 1000    );
        el.textContent = ( d > 0 ? d + ' 天 ' : '' ) +
            String(h).padStart(2,'0') + ':' +
            String(m).padStart(2,'0') + ':' +
            String(s).padStart(2,'0');
    }
    update();
    setInterval( update, 1000 );
})();
</script>

<?php endwhile; ?>
<?php get_footer(); ?>

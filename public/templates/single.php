<?php
/**
 * Single Anime Template - Hello Elementor Compatible
 *
 * @package Anime_Sync_Pro
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

if ( ! have_posts() ) {
    get_footer();
    exit;
}

while ( have_posts() ) :
    the_post();
    $post_id = get_the_ID();

    // ── 基本 ID ──────────────────────────────────────────────
    $anilist_id = (int) get_post_meta( $post_id, 'anime_anilist_id', true );
    $mal_id     = (int) get_post_meta( $post_id, 'anime_mal_id',     true );
    $bangumi_id = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );

    // ── 標題 ─────────────────────────────────────────────────
    $title_chinese = get_post_meta( $post_id, 'anime_title_chinese', true );
    $title_english = get_post_meta( $post_id, 'anime_title_english', true );
    $title_native  = get_post_meta( $post_id, 'anime_title_native',  true );
    $title_romaji  = get_post_meta( $post_id, 'anime_title_romaji',  true );
    $display_title = $title_chinese ?: get_the_title();

    // ── 格式 / 狀態 / 來源 ────────────────────────────────────
    $format = get_post_meta( $post_id, 'anime_format', true );
    $status = get_post_meta( $post_id, 'anime_status', true );
    $source = get_post_meta( $post_id, 'anime_source', true );

    // ── 季度 / 集數 / 時長 ────────────────────────────────────
    $season         = get_post_meta( $post_id, 'anime_season',         true );
    $season_year    = get_post_meta( $post_id, 'anime_season_year',    true );
    $episodes       = get_post_meta( $post_id, 'anime_episodes',       true );
    $episodes_aired = get_post_meta( $post_id, 'anime_episodes_aired', true );
    $duration       = get_post_meta( $post_id, 'anime_duration',       true );

    // ── 日期格式化 ────────────────────────────────────────────
    $format_date = function( $raw ): string {
        if ( empty( $raw ) ) return '';
        $raw = trim( (string) $raw );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) return $raw;
        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $raw, $m ) ) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        $ts = strtotime( $raw );
        return $ts !== false ? gmdate( 'Y-m-d', $ts ) : $raw;
    };

    $start_date = $format_date( get_post_meta( $post_id, 'anime_start_date', true ) );
    $end_date   = $format_date( get_post_meta( $post_id, 'anime_end_date',   true ) );

    // ── 下次播出 ─────────────────────────────────────────────
    $next_airing = get_post_meta( $post_id, 'anime_next_airing', true );
    $airing_data = [];
    if ( ! empty( $next_airing ) ) {
        $decoded = json_decode( $next_airing, true );
        $airing_data = is_array( $decoded ) ? $decoded : [];
    }

    // ── 評分 ─────────────────────────────────────────────────
    $score_anilist = get_post_meta( $post_id, 'anime_score_anilist', true );
    $score_mal     = get_post_meta( $post_id, 'anime_score_mal',     true );
    $score_bangumi = get_post_meta( $post_id, 'anime_score_bangumi', true );

    // ── 簡介（截斷原文）──────────────────────────────────────
    $synopsis_raw = get_post_meta( $post_id, 'anime_synopsis_chinese', true );
    if ( empty( $synopsis_raw ) ) {
        $synopsis_raw = get_post_meta( $post_id, 'anime_synopsis', true );
    }
    $synopsis_chinese = $synopsis_raw;
    foreach ( [ '[簡介原文]', '[原文]', '[Source]', '~!', "\n\n---" ] as $delim ) {
        if ( ! empty( $synopsis_chinese ) && str_contains( $synopsis_chinese, $delim ) ) {
            $synopsis_chinese = trim( explode( $delim, $synopsis_chinese )[0] );
            break;
        }
    }
    if ( empty( $synopsis_chinese ) ) {
        $synopsis_chinese = get_the_content();
    }

    // ── 圖片 ─────────────────────────────────────────────────
    $cover_image  = get_post_meta( $post_id, 'anime_cover_image',  true );
    $banner_image = get_post_meta( $post_id, 'anime_banner_image', true );

    // ── 預告片 ───────────────────────────────────────────────
    $trailer_url = get_post_meta( $post_id, 'anime_trailer_url', true );
    $youtube_id  = '';
    if ( ! empty( $trailer_url ) ) {
        if ( preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $trailer_url, $m ) ) {
            $youtube_id = $m[1];
        } elseif ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $trailer_url ) ) {
            $youtube_id = $trailer_url;
        }
    }

    // ── 製作資訊 ─────────────────────────────────────────────
    $studio         = get_post_meta( $post_id, 'anime_studio',         true );
    $official_site  = get_post_meta( $post_id, 'anime_official_site',  true );
    $twitter        = get_post_meta( $post_id, 'anime_twitter_url',    true );
    $wikipedia      = get_post_meta( $post_id, 'anime_wikipedia_url',  true );
    $tiktok         = get_post_meta( $post_id, 'anime_tiktok_url',     true );
    $tw_distributor = get_post_meta( $post_id, 'anime_tw_distributor', true );
    $tw_broadcast   = get_post_meta( $post_id, 'anime_tw_broadcast',   true );
    $last_sync      = get_post_meta( $post_id, 'anime_last_sync',      true );
    if ( empty( $last_sync ) ) {
        $last_sync  = get_post_meta( $post_id, 'anime_last_updated',   true );
    }

    // ── JSON 解碼 ─────────────────────────────────────────────
    $decode_json = function( $raw ): array {
        if ( is_array( $raw ) ) return $raw;
        if ( ! is_string( $raw ) || empty( $raw ) ) return [];
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) return $decoded;
        $u = @unserialize( $raw );
        return is_array( $u ) ? $u : [];
    };

    $streaming_list = $decode_json( get_post_meta( $post_id, 'anime_streaming_json', true ) );
    $themes_list    = $decode_json( get_post_meta( $post_id, 'anime_themes_json',    true ) );
    $cast_list      = $decode_json( get_post_meta( $post_id, 'anime_cast_json',      true ) );
    $staff_list     = $decode_json( get_post_meta( $post_id, 'anime_staff_json',     true ) );

    // ── OP/ED 去重分類 ────────────────────────────────────────
    $seen  = [];
    $openings = [];
    $endings  = [];
    foreach ( $themes_list as $theme ) {
        $type  = strtoupper( trim( $theme['type'] ?? '' ) );
        $key   = $type . '||' . trim( $theme['song_title'] ?? $theme['title'] ?? '' );
        if ( isset( $seen[ $key ] ) ) continue;
        $seen[ $key ] = true;
        if ( str_starts_with( $type, 'OP' ) || $type === 'OPENING' ) {
            $openings[] = $theme;
        } elseif ( str_starts_with( $type, 'ED' ) || $type === 'ENDING' ) {
            $endings[] = $theme;
        }
    }

    // ── 標籤映射 ──────────────────────────────────────────────
    $season_labels  = [ 'WINTER'=>'冬季','SPRING'=>'春季','SUMMER'=>'夏季','FALL'=>'秋季' ];
    $format_labels  = [ 'TV'=>'TV 動畫','TV_SHORT'=>'TV 短篇','MOVIE'=>'劇場版','OVA'=>'OVA','ONA'=>'ONA','SPECIAL'=>'特別篇','MUSIC'=>'MV' ];
    $status_labels  = [ 'FINISHED'=>'已完結','RELEASING'=>'連載中','NOT_YET_RELEASED'=>'尚未播出','CANCELLED'=>'已取消','HIATUS'=>'暫停中' ];
    $status_classes = [ 'FINISHED'=>'status-finished','RELEASING'=>'status-releasing','NOT_YET_RELEASED'=>'status-upcoming','CANCELLED'=>'status-cancelled','HIATUS'=>'status-hiatus' ];
    $source_labels  = [
        'ORIGINAL'=>'原創','MANGA'=>'漫畫改編','LIGHT_NOVEL'=>'輕小說改編',
        'NOVEL'=>'小說改編','VISUAL_NOVEL'=>'視覺小說改編','VIDEO_GAME'=>'遊戲改編',
        'WEB_MANGA'=>'網路漫畫改編','BOOK'=>'書籍改編','MUSIC'=>'音樂改編',
        'GAME'=>'遊戲改編','LIVE_ACTION'=>'真人改編','MULTIMEDIA_PROJECT'=>'多媒體企劃','OTHER'=>'其他',
    ];

    $season_label = $season_labels[ $season ] ?? $season;
    $format_label = $format_labels[ $format ] ?? $format;
    $status_label = $status_labels[ $status ] ?? $status;
    $status_class = $status_classes[ $status ] ?? '';
    $source_label = $source_labels[ $source ] ?? $source;

    // ── CSS 載入 ──────────────────────────────────────────────
    wp_enqueue_style(
        'anime-sync-single',
        ANIME_SYNC_PRO_URL . 'public/assets/css/anime-single.css',
        [],
        ANIME_SYNC_PRO_VERSION
    );
?>

<div class="page-content">
<article id="post-<?php the_ID(); ?>" <?php post_class( 'anime-single-wrap' ); ?>>

    <?php // ── Banner ─────────────────────────────────────────── ?>
    <?php if ( ! empty( $banner_image ) ) : ?>
    <div class="asb-banner" style="background-image:url('<?php echo esc_url( $banner_image ); ?>');">
        <div class="asb-banner-overlay"></div>
    </div>
    <?php endif; ?>

    <?php // ── Hero ───────────────────────────────────────────── ?>
    <div class="asb-hero">
        <div class="asb-cover-col">
            <?php if ( ! empty( $cover_image ) ) : ?>
                <img class="asb-cover"
                     src="<?php echo esc_url( $cover_image ); ?>"
                     alt="<?php echo esc_attr( $display_title ); ?>"
                     loading="eager">
            <?php elseif ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'large', [ 'class' => 'asb-cover' ] ); ?>
            <?php endif; ?>

            <?php if ( ! empty( $streaming_list ) ) : ?>
            <div class="asb-streaming">
                <?php foreach ( $streaming_list as $pl ) :
                    $pname = $pl['platform'] ?? $pl['site'] ?? '';
                    $purl  = $pl['url'] ?? '';
                    if ( empty( $purl ) ) continue;
                ?>
                <a href="<?php echo esc_url( $purl ); ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="asb-streaming-btn">
                    <?php echo esc_html( $pname ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="asb-info-col">
            <h1 class="asb-title"><?php echo esc_html( $display_title ); ?></h1>

            <?php if ( ! empty( $title_native ) && $title_native !== $display_title ) : ?>
                <p class="asb-title-alt"><?php echo esc_html( $title_native ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $title_romaji ) && $title_romaji !== $display_title ) : ?>
                <p class="asb-title-alt asb-romaji"><?php echo esc_html( $title_romaji ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $title_english ) && $title_english !== $display_title ) : ?>
                <p class="asb-title-alt asb-english"><?php echo esc_html( $title_english ); ?></p>
            <?php endif; ?>

            <div class="asb-badges">
                <?php if ( ! empty( $status_label ) ) : ?>
                    <span class="asb-badge asb-badge-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $format_label ) ) : ?>
                    <span class="asb-badge asb-badge-format"><?php echo esc_html( $format_label ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $season_label ) && ! empty( $season_year ) ) : ?>
                    <span class="asb-badge asb-badge-season"><?php echo esc_html( $season_year . ' ' . $season_label ); ?></span>
                <?php endif; ?>
            </div>

            <div class="asb-scores">
                <?php if ( ! empty( $score_anilist ) ) : ?>
                <div class="asb-score asb-score-anilist">
                    <span class="asb-score-label">AniList</span>
                    <span class="asb-score-val"><?php echo esc_html( $score_anilist ); ?></span>
                </div>
                <?php endif; ?>
                <?php if ( ! empty( $score_mal ) ) : ?>
                <div class="asb-score asb-score-mal">
                    <span class="asb-score-label">MAL</span>
                    <span class="asb-score-val"><?php echo esc_html( $score_mal ); ?></span>
                </div>
                <?php endif; ?>
                <?php if ( ! empty( $score_bangumi ) ) : ?>
                <div class="asb-score asb-score-bangumi">
                    <span class="asb-score-label">Bangumi</span>
                    <span class="asb-score-val"><?php echo esc_html( $score_bangumi ); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <dl class="asb-meta">
                <?php if ( ! empty( $season_label ) && ! empty( $season_year ) ) : ?>
                    <dt>播出季度</dt><dd><?php echo esc_html( $season_year . ' ' . $season_label ); ?></dd>
                <?php endif; ?>
                <?php if ( ! empty( $episodes ) ) : ?>
                    <dt>集數</dt>
                    <dd><?php
                        if ( ! empty( $episodes_aired ) && (int)$episodes_aired < (int)$episodes ) {
                            echo esc_html( $episodes_aired . ' / ' . $episodes . ' 集' );
                        } else {
                            echo esc_html( $episodes . ' 集' );
                        }
                    ?></dd>
                <?php endif; ?>
                <?php if ( ! empty( $duration ) ) : ?>
                    <dt>每集時長</dt><dd><?php echo esc_html( $duration . ' 分鐘' ); ?></dd>
                <?php endif; ?>
                <?php if ( ! empty( $start_date ) ) : ?>
                    <dt>開始日期</dt><dd><?php echo esc_html( $start_date ); ?></dd>
                <?php endif; ?>
                <?php if ( ! empty( $end_date ) && $status === 'FINISHED' ) : ?>
                    <dt>結束日期</dt><dd><?php echo esc_html( $end_date ); ?></dd>
                <?php endif; ?>
                <?php if ( ! empty( $source_label ) ) : ?>
                    <dt>原作來源</dt><dd><?php echo esc_html( $source_label ); ?></dd>
                <?php endif; ?>
                <?php if ( ! empty( $studio ) ) : ?>
                    <dt>製作公司</dt><dd><?php echo esc_html( $studio ); ?></dd>
                <?php endif; ?>
                <?php if ( ! empty( $tw_distributor ) ) : ?>
                    <dt>台灣代理</dt><dd><?php echo esc_html( $tw_distributor ); ?></dd>
                <?php endif; ?>
                <?php if ( ! empty( $tw_broadcast ) ) : ?>
                    <dt>台灣播出</dt><dd><?php echo esc_html( $tw_broadcast ); ?></dd>
                <?php endif; ?>
            </dl>

            <?php if ( $status === 'RELEASING' && ! empty( $airing_data['airingAt'] ) ) : ?>
            <div class="asb-airing">
                📅 第 <?php echo esc_html( $airing_data['episode'] ?? '' ); ?> 集：
                <span class="asb-countdown" data-timestamp="<?php echo esc_attr( $airing_data['airingAt'] ); ?>">計算中…</span>
            </div>
            <?php endif; ?>

            <div class="asb-ext-links">
                <?php if ( ! empty( $official_site ) ) : ?>
                    <a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener noreferrer" class="asb-ext-btn">官方網站</a>
                <?php endif; ?>
                <?php if ( ! empty( $twitter ) ) : ?>
                    <a href="<?php echo esc_url( $twitter ); ?>" target="_blank" rel="noopener noreferrer" class="asb-ext-btn">Twitter / X</a>
                <?php endif; ?>
                <?php if ( ! empty( $wikipedia ) ) : ?>
                    <a href="<?php echo esc_url( $wikipedia ); ?>" target="_blank" rel="noopener noreferrer" class="asb-ext-btn">Wikipedia</a>
                <?php endif; ?>
                <?php if ( ! empty( $tiktok ) ) : ?>
                    <a href="<?php echo esc_url( $tiktok ); ?>" target="_blank" rel="noopener noreferrer" class="asb-ext-btn">TikTok</a>
                <?php endif; ?>
                <?php if ( $anilist_id ) : ?>
                    <a href="https://anilist.co/anime/<?php echo esc_attr( $anilist_id ); ?>/" target="_blank" rel="noopener noreferrer" class="asb-ext-btn asb-ext-anilist">AniList</a>
                <?php endif; ?>
                <?php if ( $mal_id ) : ?>
                    <a href="https://myanimelist.net/anime/<?php echo esc_attr( $mal_id ); ?>/" target="_blank" rel="noopener noreferrer" class="asb-ext-btn asb-ext-mal">MAL</a>
                <?php endif; ?>
                <?php if ( $bangumi_id ) : ?>
                    <a href="https://bgm.tv/subject/<?php echo esc_attr( $bangumi_id ); ?>" target="_blank" rel="noopener noreferrer" class="asb-ext-btn asb-ext-bangumi">Bangumi</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="asb-body">

        <?php // ── 故事介紹 ──────────────────────────────────────── ?>
        <?php if ( ! empty( $synopsis_chinese ) ) : ?>
        <section class="asb-section">
            <h2 class="asb-section-title">故事介紹</h2>
            <div class="asb-synopsis">
                <?php echo wp_kses_post( wpautop( $synopsis_chinese ) ); ?>
            </div>
        </section>
        <?php endif; ?>

        <?php // ── 預告片 ────────────────────────────────────────── ?>
        <?php if ( ! empty( $youtube_id ) ) : ?>
        <section class="asb-section">
            <h2 class="asb-section-title">預告片</h2>
            <div class="asb-trailer">
                <iframe
                    src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr( $youtube_id ); ?>?rel=0&modestbranding=1"
                    title="<?php echo esc_attr( $display_title ); ?> 預告片"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen loading="lazy">
                </iframe>
            </div>
        </section>
        <?php endif; ?>

        <?php // ── 主題曲 ────────────────────────────────────────── ?>
        <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?>
        <section class="asb-section">
            <h2 class="asb-section-title">主題曲</h2>
            <div class="asb-themes">
                <?php if ( ! empty( $openings ) ) : ?>
                <div class="asb-themes-group">
                    <h3 class="asb-themes-subtitle">片頭曲 OP</h3>
                    <ul class="asb-themes-list">
                        <?php foreach ( $openings as $theme ) :
                            $label      = $theme['label']      ?? $theme['type'] ?? 'OP';
                            $song_title = $theme['song_title'] ?? $theme['title'] ?? '未知曲目';
                            $video_url  = $theme['video_url']  ?? $theme['video'] ?? '';
                            $notes      = $theme['notes']      ?? '';
                            $artists    = $theme['artists']    ?? [];
                            $artist_str = is_array( $artists )
                                ? implode( '、', array_filter( array_map( 'strval', $artists ) ) )
                                : (string) $artists;
                        ?>
                        <li class="asb-theme-item">
                            <span class="asb-theme-label"><?php echo esc_html( $label ); ?></span>
                            <span class="asb-theme-title"><?php echo esc_html( $song_title ); ?></span>
                            <?php if ( ! empty( $artist_str ) ) : ?>
                                <span class="asb-theme-artist">— <?php echo esc_html( $artist_str ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $notes ) ) : ?>
                                <span class="asb-theme-notes"><?php echo esc_html( $notes ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $video_url ) ) : ?>
                                <a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener noreferrer" class="asb-theme-video">▶ 觀看</a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $endings ) ) : ?>
                <div class="asb-themes-group">
                    <h3 class="asb-themes-subtitle">片尾曲 ED</h3>
                    <ul class="asb-themes-list">
                        <?php foreach ( $endings as $theme ) :
                            $label      = $theme['label']      ?? $theme['type'] ?? 'ED';
                            $song_title = $theme['song_title'] ?? $theme['title'] ?? '未知曲目';
                            $video_url  = $theme['video_url']  ?? $theme['video'] ?? '';
                            $notes      = $theme['notes']      ?? '';
                            $artists    = $theme['artists']    ?? [];
                            $artist_str = is_array( $artists )
                                ? implode( '、', array_filter( array_map( 'strval', $artists ) ) )
                                : (string) $artists;
                        ?>
                        <li class="asb-theme-item">
                            <span class="asb-theme-label"><?php echo esc_html( $label ); ?></span>
                            <span class="asb-theme-title"><?php echo esc_html( $song_title ); ?></span>
                            <?php if ( ! empty( $artist_str ) ) : ?>
                                <span class="asb-theme-artist">— <?php echo esc_html( $artist_str ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $notes ) ) : ?>
                                <span class="asb-theme-notes"><?php echo esc_html( $notes ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $video_url ) ) : ?>
                                <a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener noreferrer" class="asb-theme-video">▶ 觀看</a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php // ── 登場人物 ──────────────────────────────────────── ?>
        <?php if ( ! empty( $cast_list ) ) : ?>
        <section class="asb-section">
            <h2 class="asb-section-title">登場人物 &amp; 聲優</h2>
            <div class="asb-cast-grid" id="asb-cast-grid">
                <?php
                $cast_show  = array_slice( $cast_list, 0, 12 );
                $cast_extra = array_slice( $cast_list, 12 );
                foreach ( $cast_show as $item ) :
                    $char_name  = $item['char_name_zh'] ?? $item['char_name_ja'] ?? '';
                    $char_image = $item['char_image']   ?? '';
                    $char_role  = $item['role']         ?? '';
                    $va_name    = $item['va_name']       ?? '';
                    $va_image   = $item['va_image']      ?? '';
                ?>
                <div class="asb-cast-card">
                    <div class="asb-cast-char">
                        <?php if ( ! empty( $char_image ) ) : ?>
                            <img src="<?php echo esc_url( $char_image ); ?>" alt="<?php echo esc_attr( $char_name ); ?>" loading="lazy" class="asb-cast-img">
                        <?php else : ?>
                            <div class="asb-cast-img asb-cast-placeholder"></div>
                        <?php endif; ?>
                        <p class="asb-cast-name"><?php echo esc_html( $char_name ); ?></p>
                        <?php if ( ! empty( $char_role ) ) : ?>
                            <p class="asb-cast-role"><?php echo esc_html( $char_role ); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $va_name ) ) : ?>
                    <div class="asb-cast-va">
                        <?php if ( ! empty( $va_image ) ) : ?>
                            <img src="<?php echo esc_url( $va_image ); ?>" alt="<?php echo esc_attr( $va_name ); ?>" loading="lazy" class="asb-cast-img">
                        <?php else : ?>
                            <div class="asb-cast-img asb-cast-placeholder"></div>
                        <?php endif; ?>
                        <p class="asb-cast-name"><?php echo esc_html( $va_name ); ?></p>
                        <p class="asb-cast-role">聲優</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ( ! empty( $cast_extra ) ) : ?>
            <div id="asb-cast-extra" style="display:none;">
                <div class="asb-cast-grid">
                <?php foreach ( $cast_extra as $item ) :
                    $char_name  = $item['char_name_zh'] ?? $item['char_name_ja'] ?? '';
                    $char_image = $item['char_image']   ?? '';
                    $char_role  = $item['role']         ?? '';
                    $va_name    = $item['va_name']       ?? '';
                    $va_image   = $item['va_image']      ?? '';
                ?>
                <div class="asb-cast-card">
                    <div class="asb-cast-char">
                        <?php if ( ! empty( $char_image ) ) : ?>
                            <img src="<?php echo esc_url( $char_image ); ?>" alt="<?php echo esc_attr( $char_name ); ?>" loading="lazy" class="asb-cast-img">
                        <?php else : ?>
                            <div class="asb-cast-img asb-cast-placeholder"></div>
                        <?php endif; ?>
                        <p class="asb-cast-name"><?php echo esc_html( $char_name ); ?></p>
                        <?php if ( ! empty( $char_role ) ) : ?>
                            <p class="asb-cast-role"><?php echo esc_html( $char_role ); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $va_name ) ) : ?>
                    <div class="asb-cast-va">
                        <?php if ( ! empty( $va_image ) ) : ?>
                            <img src="<?php echo esc_url( $va_image ); ?>" alt="<?php echo esc_attr( $va_name ); ?>" loading="lazy" class="asb-cast-img">
                        <?php else : ?>
                            <div class="asb-cast-img asb-cast-placeholder"></div>
                        <?php endif; ?>
                        <p class="asb-cast-name"><?php echo esc_html( $va_name ); ?></p>
                        <p class="asb-cast-role">聲優</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <div class="asb-show-more-wrap">
                <button id="asb-btn-show-cast" class="asb-show-more-btn">
                    顯示全部 <?php echo count( $cast_list ); ?> 位角色 ▼
                </button>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php // ── 製作人員 ──────────────────────────────────────── ?>
        <?php if ( ! empty( $staff_list ) ) : ?>
        <section class="asb-section">
            <h2 class="asb-section-title">製作人員</h2>
            <div class="asb-staff-grid">
                <?php foreach ( $staff_list as $s ) :
                    $sname  = ! empty( $s['name_zh'] ) ? $s['name_zh'] : ( $s['name_ja'] ?? '' );
                    $srole  = $s['role']  ?? '';
                    $simage = $s['image'] ?? '';
                ?>
                <div class="asb-staff-card">
                    <?php if ( ! empty( $simage ) ) : ?>
                        <img src="<?php echo esc_url( $simage ); ?>" alt="<?php echo esc_attr( $sname ); ?>" loading="lazy" class="asb-staff-img">
                    <?php else : ?>
                        <div class="asb-staff-img asb-staff-placeholder"></div>
                    <?php endif; ?>
                    <p class="asb-staff-name"><?php echo esc_html( $sname ); ?></p>
                    <?php if ( ! empty( $srole ) ) : ?>
                        <p class="asb-staff-role"><?php echo esc_html( $srole ); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php // ── 頁腳 ──────────────────────────────────────────── ?>
        <footer class="asb-footer">
            <p class="asb-data-src">
                資料來源：
                <?php if ( $anilist_id ) : ?><a href="https://anilist.co/anime/<?php echo esc_attr( $anilist_id ); ?>/" target="_blank" rel="noopener noreferrer">AniList</a><?php endif; ?>
                <?php if ( $mal_id ) : ?>／<a href="https://myanimelist.net/anime/<?php echo esc_attr( $mal_id ); ?>/" target="_blank" rel="noopener noreferrer">MyAnimeList</a><?php endif; ?>
                <?php if ( $bangumi_id ) : ?>／<a href="https://bgm.tv/subject/<?php echo esc_attr( $bangumi_id ); ?>" target="_blank" rel="noopener noreferrer">Bangumi</a><?php endif; ?>
                <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?>／<a href="https://animethemes.moe/" target="_blank" rel="noopener noreferrer">AnimeThemes</a><?php endif; ?>
            </p>
            <?php if ( ! empty( $last_sync ) ) : ?>
                <p class="asb-last-sync">最後同步：<?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( $last_sync ) ) ); ?> UTC</p>
            <?php endif; ?>
        </footer>

    </div><!-- .asb-body -->

</article>
</div><!-- .page-content -->

<script>
(function(){
    'use strict';
    // 顯示全部角色
    var btn = document.getElementById('asb-btn-show-cast');
    if(btn){
        btn.addEventListener('click', function(){
            var extra = document.getElementById('asb-cast-extra');
            if(extra){ extra.style.display = 'block'; }
            this.style.display = 'none';
        });
    }
    // 倒數計時
    document.querySelectorAll('.asb-countdown[data-timestamp]').forEach(function(el){
        var ts = parseInt(el.dataset.timestamp, 10) * 1000;
        function tick(){
            var diff = ts - Date.now();
            if(diff <= 0){ el.textContent = '已播出'; return; }
            var d = Math.floor(diff/86400000);
            var h = Math.floor((diff%86400000)/3600000);
            var m = Math.floor((diff%3600000)/60000);
            el.textContent = d+'天 '+h+'小時 '+m+'分鐘後播出';
            setTimeout(tick, 60000);
        }
        tick();
    });
})();
</script>

<?php
endwhile;
get_footer();
?>

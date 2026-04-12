<?php
/**
 * Single Anime Template – Dark Theme
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

    /* ── IDs ─────────────────────────────────────────────── */
    $anilist_id  = (int) get_post_meta( $post_id, 'anime_anilist_id',  true );
    $mal_id      = (int) get_post_meta( $post_id, 'anime_mal_id',      true );
    $bangumi_id  = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );

    /* ── 標題 ─────────────────────────────────────────────── */
    $title_chinese = get_post_meta( $post_id, 'anime_title_chinese', true );
    $title_native  = get_post_meta( $post_id, 'anime_title_native',  true );
    $title_romaji  = get_post_meta( $post_id, 'anime_title_romaji',  true );
    $title_english = get_post_meta( $post_id, 'anime_title_english', true );
    $display_title = $title_chinese ?: get_the_title();

    /* ── 基本資訊 ──────────────────────────────────────────── */
    $format      = get_post_meta( $post_id, 'anime_format',                true );
    $status      = get_post_meta( $post_id, 'anime_status',                true );
    $season      = get_post_meta( $post_id, 'anime_season',                true );
    $season_year = (int) get_post_meta( $post_id, 'anime_season_year',    true );
    $episodes    = (int) get_post_meta( $post_id, 'anime_episodes',        true );
    $ep_aired    = (int) get_post_meta( $post_id, 'anime_episodes_aired', true );
    $duration    = (int) get_post_meta( $post_id, 'anime_duration',        true );
    $source      = get_post_meta( $post_id, 'anime_source',                true );
    $studio      = get_post_meta( $post_id, 'anime_studio',                true );
    $tw_dist     = get_post_meta( $post_id, 'anime_tw_distributor',        true );
    $tw_bc       = get_post_meta( $post_id, 'anime_tw_broadcast',          true );

    /* ── 日期格式化 ────────────────────────────────────────── */
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

    /* ── 分數 (含 AniList 換算) ───────────────────────────── */
    $score_anilist_raw = get_post_meta( $post_id, 'anime_score_anilist', true );
    // 自動將 100 分制轉換為 10 分制
    $score_anilist = is_numeric($score_anilist_raw) ? number_format($score_anilist_raw / 10, 1) : $score_anilist_raw;
    
    $score_mal     = get_post_meta( $post_id, 'anime_score_mal',     true );
    $score_bangumi = get_post_meta( $post_id, 'anime_score_bangumi', true );

    /* ── 圖片 / 預告 ───────────────────────────────────────── */
    $cover_image  = get_post_meta( $post_id, 'anime_cover_image',  true );
    $banner_image = get_post_meta( $post_id, 'anime_banner_image', true );
    $trailer_url  = get_post_meta( $post_id, 'anime_trailer_url',  true );

    $youtube_id = '';
    if ( $trailer_url ) {
        if ( preg_match( '/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{11})/', $trailer_url, $ym ) ) {
            $youtube_id = $ym[1];
        } elseif ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $trailer_url ) ) {
            $youtube_id = $trailer_url;
        }
    }

    /* ── 外部連結 ──────────────────────────────────────────── */
    $official_site = get_post_meta( $post_id, 'anime_official_site', true );
    $twitter_url   = get_post_meta( $post_id, 'anime_twitter_url',   true );
    $wikipedia_url = get_post_meta( $post_id, 'anime_wikipedia_url', true );
    $tiktok_url    = get_post_meta( $post_id, 'anime_tiktok_url',    true );

    /* ── 下集播出 ──────────────────────────────────────────── */
    $next_airing_raw = get_post_meta( $post_id, 'anime_next_airing', true );
    $airing_data = [];
    if ( $next_airing_raw ) {
        $decoded = is_array( $next_airing_raw ) ? $next_airing_raw : json_decode( $next_airing_raw, true );
        if ( is_array( $decoded ) ) $airing_data = $decoded;
    }

    /* ── 最後同步 ──────────────────────────────────────────── */
    $last_sync = get_post_meta( $post_id, 'anime_last_sync', true );

    /* ── 劇情簡介 ──────────────────────────────────────────── */
    $synopsis_raw = get_post_meta( $post_id, 'anime_synopsis_chinese', true );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = get_post_meta( $post_id, 'anime_synopsis', true );
    if ( empty( $synopsis_raw ) ) $synopsis_raw = get_the_content();
    foreach ( [ '[簡介原文]', '[原文]', '~!', '[Source]' ] as $delim ) {
        if ( strpos( $synopsis_raw, $delim ) !== false ) {
            $synopsis_raw = explode( $delim, $synopsis_raw )[0];
            break;
        }
    }
    $synopsis = trim( $synopsis_raw );

    /* ── JSON 解碼 ─────────────────────────────────────────── */
    $decode_json = function ( $raw ) {
        if ( is_array( $raw ) ) return $raw;
        if ( ! is_string( $raw ) || empty( $raw ) ) return [];
        $d = json_decode( $raw, true );
        if ( is_array( $d ) ) return $d;
        $u = @unserialize( $raw );
        return is_array( $u ) ? $u : [];
    };
    $streaming_list = $decode_json( get_post_meta( $post_id, 'anime_streaming_json', true ) );
    $themes_list    = $decode_json( get_post_meta( $post_id, 'anime_themes_json',    true ) );
    $cast_list      = $decode_json( get_post_meta( $post_id, 'anime_cast_json',      true ) );
    $staff_list     = $decode_json( get_post_meta( $post_id, 'anime_staff_json',     true ) );

    /* ── OP/ED 分組去重 ────────────────────────────────────── */
    $seen = []; $openings = []; $endings = [];
    foreach ( $themes_list as $t ) {
        $type   = strtoupper( trim( $t['type'] ?? '' ) );
        $stitle = trim( $t['song_title'] ?? $t['title'] ?? '' );
        $key    = $type . '||' . $stitle;
        if ( isset( $seen[$key] ) ) continue;
        $seen[$key] = true;
        if ( str_starts_with( $type, 'OP' ) )      $openings[] = $t;
        elseif ( str_starts_with( $type, 'ED' ) )  $endings[]  = $t;
    }

    /* ── Label Maps ────────────────────────────────────────── */
    $season_labels  = [ 'WINTER'=>'冬季','SPRING'=>'春季','SUMMER'=>'夏季','FALL'=>'秋季' ];
    $format_labels  = [ 'TV'=>'TV','TV_SHORT'=>'TV短篇','MOVIE'=>'劇場版','OVA'=>'OVA','ONA'=>'ONA','SPECIAL'=>'特別篇','MUSIC'=>'MV' ];
    $status_labels  = [ 'FINISHED'=>'已完結','RELEASING'=>'連載中','NOT_YET_RELEASED'=>'尚未播出','CANCELLED'=>'已取消','HIATUS'=>'暫停中' ];
    $status_classes = [ 'FINISHED'=>'s-fin','RELEASING'=>'s-rel','NOT_YET_RELEASED'=>'s-pre','CANCELLED'=>'s-can','HIATUS'=>'s-hia' ];
    $source_labels  = [ 'ORIGINAL'=>'原創','MANGA'=>'漫畫改編','LIGHT_NOVEL'=>'輕小說','NOVEL'=>'小說','VISUAL_NOVEL'=>'視覺小說','VIDEO_GAME'=>'遊戲','WEB_MANGA'=>'網路漫畫','BOOK'=>'書籍','MUSIC'=>'音樂','GAME'=>'遊戲','LIVE_ACTION'=>'真人','MULTIMEDIA_PROJECT'=>'多媒體企劃','OTHER'=>'其他' ];

    $season_label = $season_labels[$season]  ?? $season;
    $format_label = $format_labels[$format]  ?? $format;
    $status_label = $status_labels[$status]  ?? $status;
    $status_class = $status_classes[$status] ?? '';
    $source_label = $source_labels[$source]  ?? $source;

    $ep_str = '';
    if ( $episodes ) {
        $ep_str = ( $ep_aired && $ep_aired < $episodes )
            ? $ep_aired . ' / ' . $episodes . ' 集'
            : $episodes . ' 集';
    }
?>
<div class="asd-wrap">

<?php /* ══ BANNER ══════════════════════════════════════════════ */ ?>
<div class="asd-banner" style="<?php echo $banner_image ? 'background-image:url(' . esc_url($banner_image) . ')' : ''; ?>">
    <div class="asd-banner-fade"></div>
</div>

<?php /* ══ MAIN CONTENT ════════════════════════════════════════ */ ?>
<div class="asd-container">

    <?php /* ── LEFT SIDEBAR ─────────────────────────────────── */ ?>
    <aside class="asd-sidebar">
        <div class="asd-cover-wrap">
            <?php if ( $cover_image ) : ?>
                <img class="asd-cover" src="<?php echo esc_url($cover_image); ?>" alt="<?php echo esc_attr($display_title); ?>" loading="eager">
            <?php elseif ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail('large', ['class'=>'asd-cover']); ?>
            <?php endif; ?>
        </div>

        <?php /* 分數 */ ?>
        <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
        <div class="asd-score-block">
            <?php if ( $score_anilist ) : ?>
            <div class="asd-score-item asd-score-al">
                <span class="asd-score-src">AniList</span>
                <span class="asd-score-num"><?php echo esc_html($score_anilist); ?></span>
            </div>
            <?php endif; ?>
            <?php if ( $score_mal ) : ?>
            <div class="asd-score-item asd-score-mal">
                <span class="asd-score-src">MAL</span>
                <span class="asd-score-num"><?php echo esc_html($score_mal); ?></span>
            </div>
            <?php endif; ?>
            <?php if ( $score_bangumi ) : ?>
            <div class="asd-score-item asd-score-bgm">
                <span class="asd-score-src">Bangumi</span>
                <span class="asd-score-num"><?php echo esc_html($score_bangumi); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php /* YASR 評分 */ ?>
        <?php if ( shortcode_exists( 'yasr_visitor_votes' ) ) : ?>
        <div class="asd-yasr-sidebar">
            <?php echo do_shortcode( '[yasr_visitor_votes size="medium" show_count="yes"]' ); ?>
        </div>
        <?php endif; ?>

        <?php /* 串流平台 */ ?>
        <?php if ( $streaming_list ) : ?>
        <div class="asd-stream-block">
            <div class="asd-block-label">合法觀看平台</div>
            <?php foreach ( $streaming_list as $pl ) :
                $pname = $pl['platform'] ?? $pl['site'] ?? '';
                $purl  = $pl['url'] ?? '';
                if ( ! $purl ) continue;
            ?>
            <a href="<?php echo esc_url($purl); ?>" target="_blank" rel="noopener" class="asd-stream-btn">
                <span class="asd-stream-icon">▶</span><?php echo esc_html($pname); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php /* 外部連結 */ ?>
        <div class="asd-ext-block">
            <div class="asd-block-label">外部連結</div>
            <?php if ($official_site)  echo '<a href="'.esc_url($official_site).'" class="asd-ext-link" target="_blank" rel="noopener"><span class="asd-ext-icon">🌐</span>官方網站</a>'; ?>
            <?php if ($twitter_url)    echo '<a href="'.esc_url($twitter_url).'" class="asd-ext-link" target="_blank" rel="noopener"><span class="asd-ext-icon">𝕏</span>Twitter / X</a>'; ?>
            <?php if ($wikipedia_url)  echo '<a href="'.esc_url($wikipedia_url).'" class="asd-ext-link" target="_blank" rel="noopener"><span class="asd-ext-icon">📖</span>Wikipedia</a>'; ?>
            <?php if ($tiktok_url)      echo '<a href="'.esc_url($tiktok_url).'" class="asd-ext-link" target="_blank" rel="noopener"><span class="asd-ext-icon">🎵</span>TikTok</a>'; ?>
            <?php if ($anilist_id)      echo '<a href="https://anilist.co/anime/'.esc_attr($anilist_id).'/" class="asd-ext-link asd-ext-al" target="_blank" rel="noopener"><span class="asd-ext-icon">◈</span>AniList</a>'; ?>
            <?php if ($mal_id)          echo '<a href="https://myanimelist.net/anime/'.esc_attr($mal_id).'/" class="asd-ext-link asd-ext-mal" target="_blank" rel="noopener"><span class="asd-ext-icon">◉</span>MyAnimeList</a>'; ?>
            <?php if ($bangumi_id)      echo '<a href="https://bgm.tv/subject/'.esc_attr($bangumi_id).'" class="asd-ext-link asd-ext-bgm" target="_blank" rel="noopener"><span class="asd-ext-icon">◎</span>Bangumi</a>'; ?>
        </div>
    </aside>

    <?php /* ── MAIN AREA ──────────────────────────────────────── */ ?>
    <main class="asd-main">

        <?php /* 標題區 */ ?>
        <div class="asd-title-block">
            <div class="asd-badges">
                <?php if ($status_label) echo '<span class="asd-badge asd-badge-status '.$status_class.'">'.esc_html($status_label).'</span>'; ?>
                <?php if ($format_label) echo '<span class="asd-badge asd-badge-format">'.esc_html($format_label).'</span>'; ?>
                <?php if ($season_label && $season_year) echo '<span class="asd-badge asd-badge-season">'.esc_html($season_year.' '.$season_label).'</span>'; ?>
            </div>
            <h1 class="asd-title"><?php echo esc_html($display_title); ?></h1>
            <?php if ($title_native)  echo '<p class="asd-title-sub asd-native">'.esc_html($title_native).'</p>'; ?>
            <?php if ($title_romaji)  echo '<p class="asd-title-sub asd-romaji">'.esc_html($title_romaji).'</p>'; ?>
            <?php if ($title_english) echo '<p class="asd-title-sub asd-english">'.esc_html($title_english).'</p>'; ?>
        </div>

        <?php /* 資訊格 */ ?>
        <div class="asd-info-grid">
            <?php
            $info_rows = [
                '播出季度' => ( $season_label && $season_year ) ? $season_year . ' ' . $season_label : '',
                '集數'     => $ep_str,
                '每集時長' => $duration ? $duration . ' 分鐘' : '',
                '開始日期' => $start_date,
                '結束日期' => ( $end_date && $status === 'FINISHED' ) ? $end_date : '',
                '原作來源' => $source_label,
                '製作公司' => $studio,
                '台灣代理' => $tw_dist,
                '台灣播出' => $tw_bc,
            ];
            foreach ( $info_rows as $label => $val ) :
                if ( empty($val) ) continue;
            ?>
            <div class="asd-info-row">
                <span class="asd-info-label"><?php echo esc_html($label); ?></span>
                <span class="asd-info-val"><?php echo esc_html($val); ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php /* 下集倒數 */ ?>
        <?php if ( $status === 'RELEASING' && ! empty($airing_data['airingAt']) ) : ?>
        <div class="asd-airing-bar">
            <span class="asd-airing-icon">📅</span>
            第 <?php echo esc_html($airing_data['episode'] ?? ''); ?> 集播出倒數：
            <strong class="asd-countdown" data-ts="<?php echo esc_attr($airing_data['airingAt']); ?>">計算中…</strong>
        </div>
        <?php endif; ?>

        <?php /* 故事介紹 */ ?>
        <?php if ( $synopsis ) : ?>
        <section class="asd-section">
            <h2 class="asd-section-title">故事介紹</h2>
            <div class="asd-synopsis"><?php echo wp_kses_post( wpautop($synopsis) ); ?></div>
        </section>
        <?php endif; ?>

        <?php /* 預告片 */ ?>
        <?php if ( $youtube_id ) : ?>
        <section class="asd-section">
            <h2 class="asd-section-title">預告片</h2>
            <div class="asd-trailer-wrap">
                <iframe class="asd-trailer"
                    src="https://www.youtube.com/embed/<?php echo esc_attr($youtube_id); ?>?rel=0"
                    allowfullscreen loading="lazy"
                    title="<?php echo esc_attr($display_title); ?> 預告片">
                </iframe>
            </div>
        </section>
        <?php endif; ?>

        <?php /* ══ OP/ED 主題曲 ══════════════════════════════════════ */ ?>
        <?php if ( $openings || $endings ) : ?>
        <section class="asd-section">
            <h2 class="asd-section-title">主題曲</h2>
            <div class="asd-themes-wrap">

                <?php foreach ( [ '片頭曲 OP' => $openings, '片尾曲 ED' => $endings ] as $grp_title => $grp ) :
                    if ( ! $grp ) continue; ?>
                <div class="asd-theme-group">
                    <h3 class="asd-theme-group-title"><?php echo esc_html($grp_title); ?></h3>

                    <?php foreach ( $grp as $t ) :
                        $label      = $t['label'] ?? ( ($t['type']??'OP') . ($t['sequence']??1) );
                        $song_title = $t['song_title'] ?? $t['title'] ?? '';
                        $artists    = $t['artists'] ?? [];
                        if ( is_string($artists) ) $artists = array_filter([$artists]);
                        $artist_str = implode( '・', $artists );
                        $video_url  = $t['video_url'] ?? $t['video'] ?? '';
                        $notes      = $t['notes'] ?? '';
                        $is_webm    = $video_url && str_ends_with(
                            strtolower( (string) parse_url( $video_url, PHP_URL_PATH ) ), '.webm'
                        );
                    ?>
                    <div class="asd-theme-row">
                        <span class="asd-theme-label"><?php echo esc_html($label); ?></span>
                        <div class="asd-theme-meta">
                            <?php if ($song_title) : ?>
                            <span class="asd-theme-title"><?php echo esc_html($song_title); ?></span>
                            <?php endif; ?>
                            <?php if ($artist_str) : ?>
                            <span class="asd-theme-artist"><?php echo esc_html($artist_str); ?></span>
                            <?php endif; ?>
                            <?php if ($notes) : ?>
                            <span class="asd-theme-notes"><?php echo esc_html($notes); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ( $video_url ) : ?>
                        <div class="asd-theme-player">
                            <?php if ( $is_webm ) : ?>
                            <audio class="asd-theme-audio" controls preload="none">
                                <source src="<?php echo esc_url($video_url); ?>" type="video/webm">
                                您的瀏覽器不支援音訊播放。
                            </audio>
                            <?php else : ?>
                            <a class="asd-theme-play-link"
                               href="<?php echo esc_url($video_url); ?>"
                               target="_blank" rel="noopener" title="試聽">
                                 ▶ 試聽
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                </div>
                <?php endforeach; ?>

            </div>
        </section>
        <?php endif; ?>

        <?php /* 角色與聲優 */ ?>
        <?php if ( $cast_list ) : ?>
        <section class="asd-section">
            <h2 class="asd-section-title">角色與聲優</h2>
            <div class="asd-cast-grid" id="asd-cast-grid">
                <?php foreach ( $cast_list as $i => $c ) :
                    $char_name = $c['char_name_zh'] ?: ($c['char_name_ja'] ?? '');
                    $char_img  = $c['char_image']   ?? '';
                    $va_name   = $c['va_name']       ?? '';
                    $va_img    = $c['va_image']      ?? '';
                    $is_extra  = $i >= 12;
                ?>
                <div class="asd-cast-card<?php echo $is_extra ? ' asd-cast-extra' : ''; ?>">
                    <div class="asd-cast-imgs">
                        <div class="asd-cast-char-img">
                            <?php if ($char_img) : ?>
                            <img src="<?php echo esc_url($char_img); ?>" alt="<?php echo esc_attr($char_name); ?>" loading="lazy">
                            <?php else : ?><div class="asd-cast-noimg">?</div><?php endif; ?>
                        </div>
                    </div>
                    <div class="asd-cast-names">
                        <span class="asd-cast-char-name"><?php echo esc_html($char_name); ?></span>
                        <?php if ($va_name) echo '<span class="asd-cast-va-name">'.esc_html($va_name).'</span>'; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ( count($cast_list) > 12 ) : ?>
            <div class="asd-cast-more-wrap">
                <button class="asd-cast-more-btn" id="asd-cast-more-btn">
                    顯示全部 <?php echo count($cast_list); ?> 位角色 ▾
                </button>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php /* 製作人員 */ ?>
        <?php if ( $staff_list ) : ?>
        <section class="asd-section">
            <h2 class="asd-section-title">製作人員</h2>
            <div class="asd-staff-grid">
                <?php foreach ( $staff_list as $s ) :
                    $s_name = $s['name_zh'] ?: ($s['name_ja'] ?? '');
                    $s_role = $s['role']    ?? '';
                    $s_img  = $s['image']   ?? '';
                ?>
                <div class="asd-staff-card">
                    <?php if ($s_img) : ?>
                    <img src="<?php echo esc_url($s_img); ?>" alt="<?php echo esc_attr($s_name); ?>" loading="lazy">
                    <?php else : ?><div class="asd-staff-noimg">?</div><?php endif; ?>
                    <div class="asd-staff-info">
                        <span class="asd-staff-name"><?php echo esc_html($s_name); ?></span>
                        <span class="asd-staff-role"><?php echo esc_html($s_role); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php /* Footer */ ?>
        <footer class="asd-footer">
            <p class="asd-footer-src">資料來源：
            <?php
            $srcs = [];
            if ($anilist_id)         $srcs[] = '<a href="https://anilist.co/anime/'.esc_attr($anilist_id).'/" target="_blank" rel="noopener">AniList</a>';
            if ($mal_id)             $srcs[] = '<a href="https://myanimelist.net/anime/'.esc_attr($mal_id).'/" target="_blank" rel="noopener">MyAnimeList</a>';
            if ($bangumi_id)         $srcs[] = '<a href="https://bgm.tv/subject/'.esc_attr($bangumi_id).'" target="_blank" rel="noopener">Bangumi</a>';
            if ($openings||$endings) $srcs[] = '<a href="https://animethemes.moe/" target="_blank" rel="noopener">AnimeThemes</a>';
            echo implode( ' ／ ', $srcs );
            ?>
            </p>
            <?php if ($last_sync) : ?>
            <p class="asd-footer-sync">最後同步：<?php echo esc_html( gmdate( 'Y-m-d H:i', is_numeric($last_sync) ? (int)$last_sync : strtotime($last_sync) ) ); ?> UTC</p>
            <?php endif; ?>
        </footer>

        <?php /* ══ YASR 評分區塊 ════════════════════════════════════ */ ?>
        <?php if ( shortcode_exists( 'yasr_visitor_votes' ) ) : ?>
        <div class="asd-yasr-wrap">
            <h2 class="asd-section-title">讀者評分</h2>
            <?php echo do_shortcode( '[yasr_visitor_votes size="large" show_count="yes"]' ); ?>
        </div>
        <?php endif; ?>

        <?php /* ══ wpDiscuz 留言 ════════════════════════════════════ */ ?>
        <?php if ( comments_open() || get_comments_number() ) : ?>
        <div class="asd-comments-wrap">
            <?php comments_template(); ?>
        </div>
        <?php endif; ?>

    </main>
</div></div><script>
(function(){
    'use strict';

    /* ── 顯示全部角色 ──────────────────────────────────────── */
    var btn = document.getElementById('asd-cast-more-btn');
    if (btn) {
        btn.addEventListener('click', function(){
            document.querySelectorAll('.asd-cast-extra').forEach(function(el){
                el.style.display = 'flex';
            });
            btn.closest('.asd-cast-more-wrap').style.display = 'none';
        });
    }

    /* ── 倒數計時 ──────────────────────────────────────────── */
    var els = document.querySelectorAll('.asd-countdown[data-ts]');
    if (els.length) {
        function tick(){
            var now = Math.floor(Date.now() / 1000);
            els.forEach(function(el){
                var diff = parseInt(el.dataset.ts, 10) - now;
                if (diff <= 0){ el.textContent = '已播出'; return; }
                var d = Math.floor(diff / 86400),
                    h = Math.floor(diff % 86400 / 3600),
                    m = Math.floor(diff % 3600 / 60),
                    s = diff % 60;
                el.textContent = (d ? d + '天 ' : '') +
                    String(h).padStart(2,'0') + ':' +
                    String(m).padStart(2,'0') + ':' +
                    String(s).padStart(2,'0');
            });
        }
        tick();
        setInterval(tick, 1000);
    }

    /* ── 同時只播一個 audio ────────────────────────────────── */
    var audios = document.querySelectorAll('.asd-theme-audio');
    if (audios.length > 1) {
        audios.forEach(function(audio){
            audio.addEventListener('play', function(){
                audios.forEach(function(other){
                    if (other !== audio && !other.paused) other.pause();
                });
            });
        });
    }

})();
</script>

<?php endwhile; ?>
<?php get_footer(); ?>
<?php
/**
 * Single Anime Template — Anime Sync Pro v16.0
 * Path: wp-content/plugins/anime-sync-pro/public/templates/single-anime.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Enqueue CSS ──────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'asd-single',
        plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'public/assets/css/anime-single.css',
        [],
        '16.0'
    );
} );

get_header();

// ── Meta helpers ─────────────────────────────────────────────
$pid   = get_the_ID();
$gmeta = function( $key ) use ( $pid ) {
    return get_post_meta( $pid, $key, true );
};
$json  = function( $val ) {
    if ( is_array( $val ) ) return $val;
    if ( ! $val ) return [];
    $d = json_decode( $val, true );
    return is_array( $d ) ? $d : [];
};
$fdate = function( $s ) {
    if ( ! $s ) return '—';
    $t = strtotime( $s );
    return $t ? date_i18n( 'Y-m-d', $t ) : $s;
};
$esc   = function( $s ) { return esc_html( $s ); };
$fall  = function( $v, $fb = '—' ) { return ( $v && $v !== '' ) ? $v : $fb; };

// ── Pull meta ────────────────────────────────────────────────
$title_zh   = $fall( $gmeta('title_zh')   ?: $gmeta('chinese_title')  ?: get_the_title(), get_the_title() );
$title_jp   = $fall( $gmeta('title_jp')   ?: $gmeta('native_title')   ?: $gmeta('title_native') );
$title_en   = $fall( $gmeta('title_en')   ?: $gmeta('english_title'), '' );
$title_ro   = $fall( $gmeta('title_romaji') ?: $gmeta('romaji'), '' );
$format     = $fall( $gmeta('format')     ?: $gmeta('anime_format'), 'TV' );
$status     = $fall( $gmeta('status')     ?: $gmeta('anime_status'), '' );
$season     = $fall( $gmeta('season')     ?: $gmeta('anime_season'), '' );
$season_yr  = $fall( $gmeta('season_year'), '' );
$eps        = $fall( $gmeta('episodes')   ?: $gmeta('episode_count'), '' );
$duration   = $fall( $gmeta('duration')   ?: $gmeta('episode_duration'), '' );
$source     = $fall( $gmeta('source')     ?: $gmeta('anime_source'), '' );
$studio_raw = $gmeta('studios')           ?: $gmeta('studio') ?: $gmeta('animation_studio') ?: '';
$studios_arr= is_array( $studio_raw ) ? $studio_raw : ( $studio_raw ? [ $studio_raw ] : [] );
$studio_str = implode( ', ', array_filter( array_map( function($s){ return is_array($s)? ($s['name']??'') : $s; }, $studios_arr ) ) ) ?: '—';
$cover_img  = $gmeta('cover_image')       ?: $gmeta('image_url') ?: $gmeta('poster_url') ?: get_the_post_thumbnail_url( $pid, 'large' ) ?: '';
$banner_img = $gmeta('banner_image')      ?: $gmeta('banner_url') ?: '';
$synopsis   = $fall( $gmeta('synopsis')   ?: $gmeta('description') ?: get_the_excerpt() );
$trailer_url= $gmeta('trailer_url')       ?: $gmeta('trailer_embed') ?: '';
$start_date = $gmeta('start_date')        ?: $gmeta('air_date') ?: '';
$end_date   = $gmeta('end_date')          ?: $gmeta('end_air_date') ?: '';
$score_al   = $gmeta('score_al')          ?: $gmeta('anilist_score') ?: '';
$score_mal  = $gmeta('score_mal')         ?: $gmeta('mal_score') ?: '';
$score_bgm  = $gmeta('score_bgm')         ?: $gmeta('bangumi_score') ?: '';
$url_al     = $gmeta('url_anilist')       ?: $gmeta('anilist_url') ?: '';
$url_mal    = $gmeta('url_mal')           ?: $gmeta('mal_url') ?: '';
$url_bgm    = $gmeta('url_bangumi')       ?: $gmeta('bangumi_url') ?: '';
$url_wiki   = $gmeta('url_wiki')          ?: $gmeta('wikipedia_url') ?: '';
$url_tw     = $gmeta('url_twitter')       ?: $gmeta('twitter_url') ?: '';

$cast_raw   = $gmeta('cast')              ?: $gmeta('characters') ?: '';
$staff_raw  = $gmeta('staff')             ?: $gmeta('crew') ?: '';
$eps_raw    = $gmeta('episode_list')      ?: $gmeta('episodes_data') ?: '';
$themes_raw = $gmeta('themes')            ?: $gmeta('theme_songs') ?: '';
$stream_raw = $gmeta('streaming')         ?: $gmeta('streaming_platforms') ?: '';
$faq_raw    = $gmeta('faq')               ?: $gmeta('faqs') ?: '';

$cast_arr   = $json( $cast_raw );
$staff_arr  = $json( $staff_raw );
$eps_arr    = $json( $eps_raw );
$themes_arr = $json( $themes_raw );
$stream_arr = $json( $stream_raw );
$faq_arr    = $json( $faq_raw );

// ── Localise labels ──────────────────────────────────────────
$lbl_format_map = [
    'TV'=>'TV','MOVIE'=>'電影','OVA'=>'OVA','ONA'=>'ONA','SPECIAL'=>'特別篇','MUSIC'=>'音樂'
];
$format_lbl = $lbl_format_map[ strtoupper($format) ] ?? $format;

$status_lbl_map = [
    'FINISHED'=>'已完結','FINISHED_AIRING'=>'已完結','RELEASING'=>'播出中',
    'NOT_YET_RELEASED'=>'尚未播出','CANCELLED'=>'已取消','HIATUS'=>'暫停'
];
$status_lbl = $status_lbl_map[ strtoupper(str_replace(' ','_',$status)) ] ?? $status;
$status_class = in_array(strtoupper($status),['RELEASING','AIRING']) ? 'asd-badge--airing' : 'asd-badge--finished';

$season_map = ['WINTER'=>'冬季','SPRING'=>'春季','SUMMER'=>'夏季','FALL'=>'秋季'];
$season_lbl = ( $season_map[ strtoupper($season) ] ?? $season );
$season_full = trim( $season_lbl . ( $season_yr ? ' '.$season_yr : '' ) );

$source_map = [
    'MANGA'=>'漫畫改編','LIGHT_NOVEL'=>'輕小說改編','NOVEL'=>'小說改編',
    'ORIGINAL'=>'原創','GAME'=>'遊戲改編','VISUAL_NOVEL'=>'視覺小說改編',
    'WEB_MANGA'=>'網路漫畫改編','OTHER'=>'其他'
];
$source_lbl = $source_map[ strtoupper($source) ] ?? $source;

// ── Schema JSON-LD ───────────────────────────────────────────
$schema = [
    '@context' => 'https://schema.org',
    '@type'    => 'TVSeries',
    'name'     => $title_zh,
    'description' => wp_strip_all_tags( $synopsis ),
    'url'      => get_permalink(),
];
if ( $cover_img ) $schema['image'] = $cover_img;
if ( $start_date ) $schema['startDate'] = $start_date;
if ( $end_date   ) $schema['endDate']   = $end_date;
if ( $eps )        $schema['numberOfEpisodes'] = intval($eps);
echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE ) . '</script>';
?>
<div class="asd-wrap">

<?php /* ── Banner ── */ ?>
<?php if ( $banner_img ): ?>
<div class="asd-banner" style="background-image:url('<?php echo esc_url($banner_img); ?>')">
  <div class="asd-banner-fade"></div>
</div>
<?php else: ?>
<div class="asd-banner asd-banner--fallback"></div>
<?php endif; ?>

<?php /* ── Breadcrumb ── */ ?>
<nav class="asd-breadcrumb" aria-label="breadcrumb">
  <ol>
    <li><a href="<?php echo home_url('/'); ?>">首頁</a></li>
    <li><a href="<?php echo home_url('/anime/'); ?>">動畫</a></li>
    <li aria-current="page"><?php echo $esc($title_zh); ?></li>
  </ol>
</nav>

<?php /* ── Hero ── */ ?>
<div class="asd-hero">

  <!-- Cover -->
  <div class="asd-cover">
    <?php if ( $cover_img ): ?>
      <img src="<?php echo esc_url($cover_img); ?>" alt="<?php echo $esc($title_zh); ?>" loading="eager">
    <?php else: ?>
      <div class="asd-cover-fallback"><?php echo mb_substr($title_zh,0,2); ?></div>
    <?php endif; ?>
  </div>

  <!-- Main info -->
  <div class="asd-info">
    <div class="asd-kicker">🎬 <?php echo $esc($season_full ?: '動畫'); ?></div>
    <h1 class="asd-title"><?php echo $esc($title_zh); ?></h1>
    <?php if ( $title_jp !== '—' ): ?>
      <p class="asd-native"><?php echo $esc($title_jp); ?></p>
    <?php endif; ?>
    <?php if ( $title_ro ): ?>
      <p class="asd-native asd-romaji"><?php echo $esc($title_ro); ?></p>
    <?php endif; ?>

    <!-- Badges -->
    <div class="asd-badges">
      <?php if ( $format_lbl ): ?><span class="asd-badge asd-badge--tv"><?php echo $esc($format_lbl); ?></span><?php endif; ?>
      <?php if ( $status_lbl ): ?><span class="asd-badge <?php echo $status_class; ?>"><?php echo $esc($status_lbl); ?></span><?php endif; ?>
      <?php if ( $season_full ): ?><span class="asd-badge asd-badge--summer"><?php echo $esc($season_full); ?></span><?php endif; ?>
      <?php if ( $eps ): ?><span class="asd-badge"><?php echo $esc($eps); ?> 集</span><?php endif; ?>
    </div>

    <!-- Scores -->
    <?php if ( $score_al || $score_mal || $score_bgm ): ?>
    <div class="asd-scores">
      <?php if ( $score_al ): ?>
      <div class="asd-score asd-score--al">
        <strong><?php echo $esc($score_al); ?></strong>
        <small>AniList</small>
      </div>
      <?php endif; ?>
      <?php if ( $score_mal ): ?>
      <div class="asd-score asd-score--mal">
        <strong><?php echo $esc($score_mal); ?></strong>
        <small>MyAnimeList</small>
      </div>
      <?php endif; ?>
      <?php if ( $score_bgm ): ?>
      <div class="asd-score asd-score--bgm">
        <strong><?php echo $esc($score_bgm); ?></strong>
        <small>Bangumi</small>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="asd-actions">
      <?php if ( $trailer_url ): ?>
        <a href="#tab-trailer" class="asd-btn asd-btn--primary">▶ 觀看預告</a>
      <?php endif; ?>
      <a href="#tab-episodes" class="asd-btn asd-btn--ghost">📺 集數列表</a>
      <a href="#tab-info" class="asd-btn asd-btn--ghost">ℹ 基本資訊</a>
    </div>
  </div><!-- .asd-info -->

  <!-- Right sidebar in hero -->
  <div class="asd-hside">
    <!-- Info block -->
    <div class="asd-hblock">
      <div class="asd-hblock-title">基本資訊</div>
      <?php
      $meta_rows = [
        '類型'   => $format_lbl,
        '集數'   => $eps ? $eps.' 集' : '—',
        '狀態'   => $status_lbl ?: '—',
        '播出季' => $season_full ?: '—',
        '時長'   => $duration ? $duration.' 分鐘' : '—',
        '原作'   => $source_lbl ?: '—',
        '製作'   => $studio_str,
        '開始'   => $fdate($start_date),
        '結束'   => $fdate($end_date),
      ];
      foreach ( $meta_rows as $k => $v ):
        if ( $v === '—' && $k !== '類型' ) continue;
      ?>
      <div class="asd-hinfo">
        <span class="asd-hinfo-k"><?php echo $esc($k); ?></span>
        <span class="asd-hinfo-v"><?php echo $esc($v); ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- External links -->
    <?php if ( $url_al || $url_mal || $url_bgm || $url_wiki || $url_tw ): ?>
    <div class="asd-hblock">
      <div class="asd-hblock-title">外部連結</div>
      <div class="asd-ext-links">
        <?php if ($url_al):  ?><a href="<?php echo esc_url($url_al);   ?>" target="_blank" rel="noopener" class="asd-ext asd-ext--al">AniList</a><?php endif; ?>
        <?php if ($url_mal): ?><a href="<?php echo esc_url($url_mal);  ?>" target="_blank" rel="noopener" class="asd-ext asd-ext--mal">MAL</a><?php endif; ?>
        <?php if ($url_bgm): ?><a href="<?php echo esc_url($url_bgm);  ?>" target="_blank" rel="noopener" class="asd-ext asd-ext--bgm">Bangumi</a><?php endif; ?>
        <?php if ($url_wiki):?><a href="<?php echo esc_url($url_wiki); ?>" target="_blank" rel="noopener" class="asd-ext asd-ext--wiki">Wiki</a><?php endif; ?>
        <?php if ($url_tw):  ?><a href="<?php echo esc_url($url_tw);   ?>" target="_blank" rel="noopener" class="asd-ext asd-ext--tw">Twitter</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- .asd-hside -->

</div><!-- .asd-hero -->

<?php /* ── Tab Bar ── */ ?>
<div class="asd-tabs-wrap">
  <nav class="asd-tabs" role="tablist">
    <a class="asd-tab is-active" href="#tab-info"     role="tab">ℹ 基本資訊</a>
    <a class="asd-tab" href="#tab-synopsis"           role="tab">📝 劇情</a>
    <?php if ( $trailer_url ): ?>
    <a class="asd-tab" href="#tab-trailer"            role="tab">🎞 預告片</a>
    <?php endif; ?>
    <?php if ( $eps_arr ): ?>
    <a class="asd-tab" href="#tab-episodes"           role="tab">📺 集數</a>
    <?php endif; ?>
    <?php if ( $staff_arr ): ?>
    <a class="asd-tab" href="#tab-staff"              role="tab">🎬 STAFF</a>
    <?php endif; ?>
    <?php if ( $cast_arr ): ?>
    <a class="asd-tab" href="#tab-cast"               role="tab">🎭 CAST</a>
    <?php endif; ?>
    <?php if ( $themes_arr ): ?>
    <a class="asd-tab" href="#tab-music"              role="tab">🎵 主題曲</a>
    <?php endif; ?>
    <?php if ( $stream_arr ): ?>
    <a class="asd-tab" href="#tab-stream"             role="tab">📡 串流</a>
    <?php endif; ?>
    <?php if ( $faq_arr ): ?>
    <a class="asd-tab" href="#tab-faq"                role="tab">❓ FAQ</a>
    <?php endif; ?>
    <a class="asd-tab" href="#tab-comments"           role="tab">💬 留言</a>
  </nav>
</div>

<?php /* ── Body (main + sidebar) ── */ ?>
<div class="asd-body">

  <main class="asd-main">

    <!-- Tab: Info -->
    <section id="tab-info" class="asd-section">
      <h2 class="asd-sec-title">📋 基本資訊</h2>
      <div class="asd-info-grid">
        <?php
        $cells = [
          '類型'     => $format_lbl,
          '集數'     => $eps ? $eps.' 集' : '—',
          '每集時長' => $duration ? $duration.' 分鐘' : '—',
          '狀態'     => $status_lbl ?: '—',
          '播出季節' => $season_full ?: '—',
          '原作來源' => $source_lbl ?: '—',
          '製作公司' => $studio_str,
          '開始日期' => $fdate($start_date),
          '結束日期' => $fdate($end_date),
        ];
        foreach ( $cells as $lbl => $val ):
        ?>
        <div class="asd-info-cell">
          <div class="asd-info-cell-label"><?php echo $esc($lbl); ?></div>
          <div class="asd-info-cell-value"><?php echo $esc($val); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Tab: Synopsis -->
    <section id="tab-synopsis" class="asd-section">
      <h2 class="asd-sec-title">📝 劇情簡介</h2>
      <div class="asd-synopsis"><?php echo wp_kses_post( nl2br( $synopsis ) ); ?></div>
    </section>

    <!-- Tab: Trailer -->
    <?php if ( $trailer_url ): ?>
    <section id="tab-trailer" class="asd-section">
      <h2 class="asd-sec-title">🎞 預告片</h2>
      <div class="asd-trailer-wrap">
        <iframe src="<?php echo esc_url($trailer_url); ?>"
          title="<?php echo $esc($title_zh); ?> 預告片"
          frameborder="0" allowfullscreen loading="lazy"
          allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture">
        </iframe>
      </div>
    </section>
    <?php endif; ?>

    <!-- Tab: Episodes -->
    <?php if ( $eps_arr ): ?>
    <section id="tab-episodes" class="asd-section">
      <h2 class="asd-sec-title">📺 集數列表</h2>
      <div class="asd-ep-list">
        <?php foreach ( $eps_arr as $i => $ep ):
          $ep_num   = $ep['number'] ?? $ep['episode'] ?? ($i+1);
          $ep_title = $ep['title'] ?? $ep['title_jp'] ?? $ep['title_zh'] ?? ( '第 '.$ep_num.' 集' );
          $ep_date  = $ep['air_date'] ?? $ep['aired'] ?? $ep['date'] ?? '';
        ?>
        <div class="asd-ep">
          <span class="asd-ep-num"><?php echo intval($ep_num); ?></span>
          <span class="asd-ep-title"><?php echo $esc($ep_title); ?></span>
          <?php if ($ep_date): ?>
          <span class="asd-ep-date"><?php echo $esc($fdate($ep_date)); ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Tab: Staff -->
    <?php if ( $staff_arr ): ?>
    <section id="tab-staff" class="asd-section">
      <h2 class="asd-sec-title">🎬 STAFF</h2>
      <div class="asd-staff-grid">
        <?php foreach ( $staff_arr as $s ):
          $s_name  = $s['name']  ?? $s['name_zh']  ?? $s['name_jp'] ?? '';
          $s_role  = $s['role']  ?? $s['role_zh']  ?? $s['position'] ?? '';
          $s_img   = $s['image'] ?? $s['image_url'] ?? $s['avatar']  ?? '';
          if ( ! $s_name ) continue;
          // Get first character for initial
          $initial = mb_strtoupper( mb_substr( $s_name, 0, 1 ) );
        ?>
        <div class="asd-staff-card">
          <div class="asd-staff-avatar">
            <?php if ( $s_img ): ?>
              <img src="<?php echo esc_url($s_img); ?>" alt="<?php echo $esc($s_name); ?>" loading="lazy">
            <?php else: ?>
              <span class="asd-staff-initials"><?php echo $esc($initial); ?></span>
            <?php endif; ?>
          </div>
          <div class="asd-staff-name"><?php echo $esc($s_name); ?></div>
          <?php if ( $s_role ): ?>
          <span class="asd-staff-role"><?php echo $esc($s_role); ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Tab: Cast -->
    <?php if ( $cast_arr ): ?>
    <section id="tab-cast" class="asd-section">
      <h2 class="asd-sec-title">🎭 CAST</h2>
      <div class="asd-cast-grid">
        <?php foreach ( $cast_arr as $c ):
          // Character name: try multiple keys
          $char = $c['character_name_zh'] ?? $c['character_name'] ?? $c['char_name'] ?? $c['name_zh'] ?? $c['name'] ?? '';
          // Voice actor: try multiple keys
          $va   = $c['voice_actor'] ?? $c['voice_actor_name'] ?? $c['va'] ?? $c['va_name']
               ?? $c['voice_actor_zh'] ?? $c['actor'] ?? $c['actor_name'] ?? $c['seiyuu'] ?? '';
          $c_img = $c['image'] ?? $c['image_url'] ?? $c['avatar'] ?? $c['character_image'] ?? '';
          if ( ! $char && ! $va ) continue;
          $disp = $char ?: $va;
          $initial = mb_strtoupper( mb_substr( $disp, 0, 1 ) );
        ?>
        <div class="asd-cast-card">
          <div class="asd-cast-avatar">
            <?php if ( $c_img ): ?>
              <img src="<?php echo esc_url($c_img); ?>" alt="<?php echo $esc($char); ?>" loading="lazy">
            <?php else: ?>
              <span style="font-size:1.4rem;font-weight:900;color:rgba(255,255,255,.7)"><?php echo $esc($initial); ?></span>
            <?php endif; ?>
          </div>
          <div class="asd-cast-char"><?php echo $esc($char ?: '—'); ?></div>
          <?php if ( $va ): ?>
          <div class="asd-cast-cv">CV: <?php echo $esc($va); ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Tab: Music -->
    <?php if ( $themes_arr ): ?>
    <section id="tab-music" class="asd-section">
      <h2 class="asd-sec-title">🎵 主題曲</h2>
      <div class="asd-music-list">
        <?php foreach ( $themes_arr as $t ):
          $t_type   = strtolower( $t['type'] ?? $t['theme_type'] ?? 'ed' );
          $t_title  = $t['title'] ?? $t['song_title'] ?? $t['name'] ?? '';
          $t_artist = $t['artist'] ?? $t['performer'] ?? $t['artist_name'] ?? '';
          $type_cls = ( $t_type === 'op' ) ? 'asd-music-type--op' : 'asd-music-type--ed';
          $type_lbl = ( $t_type === 'op' ) ? 'OP' : 'ED';
        ?>
        <div class="asd-music-card">
          <div class="asd-music-type <?php echo $type_cls; ?>"><?php echo $type_lbl; ?></div>
          <div class="asd-music-body">
            <div class="asd-music-title"><?php echo $esc($t_title ?: '—'); ?></div>
            <?php if ( $t_artist ): ?>
            <div class="asd-music-artist"><?php echo $esc($t_artist); ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Tab: Streaming -->
    <?php if ( $stream_arr ): ?>
    <section id="tab-stream" class="asd-section">
      <h2 class="asd-sec-title">📡 串流平台</h2>
      <div class="asd-stream-grid">
        <?php foreach ( $stream_arr as $st ):
          $st_name  = $st['name'] ?? $st['platform'] ?? '';
          $st_url   = $st['url']  ?? $st['link'] ?? '';
          $st_flag  = $st['flag'] ?? $st['country_flag'] ?? '🌐';
        ?>
        <?php if ( $st_url ): ?>
        <a href="<?php echo esc_url($st_url); ?>" target="_blank" rel="noopener" class="asd-stream-card">
          <span class="asd-stream-flag"><?php echo $esc($st_flag); ?></span>
          <span class="asd-stream-name"><?php echo $esc($st_name); ?></span>
        </a>
        <?php else: ?>
        <div class="asd-stream-card">
          <span class="asd-stream-flag"><?php echo $esc($st_flag); ?></span>
          <span class="asd-stream-name"><?php echo $esc($st_name); ?></span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Tab: FAQ -->
    <?php if ( $faq_arr ): ?>
    <section id="tab-faq" class="asd-section">
      <h2 class="asd-sec-title">❓ 常見問題</h2>
      <div class="asd-faq-list">
        <?php foreach ( $faq_arr as $fi => $fq ):
          $fq_q = $fq['question'] ?? $fq['q'] ?? '';
          $fq_a = $fq['answer']   ?? $fq['a'] ?? '';
          if ( ! $fq_q ) continue;
        ?>
        <div class="asd-faq-item" id="faq-<?php echo $fi; ?>">
          <div class="asd-faq-q" onclick="this.parentElement.classList.toggle('is-open')">
            <?php echo $esc($fq_q); ?>
            <span class="asd-faq-toggle">＋</span>
          </div>
          <div class="asd-faq-a"><?php echo wp_kses_post($fq_a); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Tab: Comments -->
    <section id="tab-comments" class="asd-section asd-comments">
      <h2 class="asd-sec-title">💬 留言</h2>
      <?php
      if ( comments_open() || get_comments_number() ) {
          comments_template();
      } else {
          echo '<p style="color:var(--asd-text-faint);font-size:.84rem">留言功能已關閉。</p>';
      }
      ?>
    </section>

  </main><!-- .asd-main -->

  <!-- Right sidebar column -->
  <aside class="asd-side">

    <!-- Related / mini info card -->
    <div class="asd-side-block">
      <div class="asd-side-block-title">相關資訊</div>
      <div style="display:flex;flex-direction:column;gap:6px">
        <?php if ( $studio_str !== '—' ): ?>
        <div style="font-size:.78rem;color:var(--asd-text-soft)">
          <span style="color:var(--asd-text-faint);font-size:.70rem;display:block;margin-bottom:2px">製作公司</span>
          <?php echo $esc($studio_str); ?>
        </div>
        <?php endif; ?>
        <?php if ( $source_lbl ): ?>
        <div style="font-size:.78rem;color:var(--asd-text-soft)">
          <span style="color:var(--asd-text-faint);font-size:.70rem;display:block;margin-bottom:2px">原作類型</span>
          <?php echo $esc($source_lbl); ?>
        </div>
        <?php endif; ?>
        <?php if ( $eps ): ?>
        <div style="font-size:.78rem;color:var(--asd-text-soft)">
          <span style="color:var(--asd-text-faint);font-size:.70rem;display:block;margin-bottom:2px">集數</span>
          <?php echo $esc($eps); ?> 集
        </div>
        <?php endif; ?>
        <?php if ( $duration ): ?>
        <div style="font-size:.78rem;color:var(--asd-text-soft)">
          <span style="color:var(--asd-text-faint);font-size:.70rem;display:block;margin-bottom:2px">每集時長</span>
          <?php echo $esc($duration); ?> 分鐘
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tags block (if any) -->
    <?php
    $tags = get_the_tags( $pid );
    if ( $tags && ! is_wp_error($tags) ):
    ?>
    <div class="asd-side-block">
      <div class="asd-side-block-title">標籤</div>
      <div class="asd-tags">
        <?php foreach ( $tags as $tag ): ?>
        <a href="<?php echo get_tag_link($tag); ?>" class="asd-tag"><?php echo $esc($tag->name); ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- External links block -->
    <?php if ( $url_al || $url_mal || $url_bgm || $url_wiki || $url_tw ): ?>
    <div class="asd-side-block">
      <div class="asd-side-block-title">外部連結</div>
      <div class="asd-links-grid">
        <?php if ($url_al):  ?><a href="<?php echo esc_url($url_al);   ?>" target="_blank" rel="noopener" class="asd-link-card asd-link-card--al"><span class="asd-link-icon">📊</span><span class="asd-link-label">AniList</span></a><?php endif; ?>
        <?php if ($url_mal): ?><a href="<?php echo esc_url($url_mal);  ?>" target="_blank" rel="noopener" class="asd-link-card asd-link-card--mal"><span class="asd-link-icon">📋</span><span class="asd-link-label">MyAnimeList</span></a><?php endif; ?>
        <?php if ($url_bgm): ?><a href="<?php echo esc_url($url_bgm);  ?>" target="_blank" rel="noopener" class="asd-link-card asd-link-card--bgm"><span class="asd-link-icon">🎌</span><span class="asd-link-label">Bangumi</span></a><?php endif; ?>
        <?php if ($url_wiki):?><a href="<?php echo esc_url($url_wiki); ?>" target="_blank" rel="noopener" class="asd-link-card"><span class="asd-link-icon">📖</span><span class="asd-link-label">Wikipedia</span></a><?php endif; ?>
        <?php if ($url_tw):  ?><a href="<?php echo esc_url($url_tw);   ?>" target="_blank" rel="noopener" class="asd-link-card"><span class="asd-link-icon">🐦</span><span class="asd-link-label">Twitter</span></a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </aside>

</div><!-- .asd-body -->

<script>
// Simple tab switching
document.addEventListener('DOMContentLoaded', function(){
  var tabs = document.querySelectorAll('.asd-tab');
  tabs.forEach(function(tab){
    tab.addEventListener('click', function(e){
      tabs.forEach(function(t){ t.classList.remove('is-active'); });
      this.classList.add('is-active');
    });
  });
});
</script>

</div><!-- .asd-wrap -->
<?php get_footer(); ?>

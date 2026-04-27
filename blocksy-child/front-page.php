<?php get_header(); ?>

<!-- ============================================================
     HERO
     ============================================================ -->
<!-- ============================================================
     HERO
     ============================================================ -->
<section class="hero-section" id="hero">
  <div class="hero-bg-layer" id="hero-bg"></div>
  <div class="hero-noise"></div>
  <div class="container hero-content-wrap">

    <div class="hero-text">
      <div class="hero-eyebrow">
        <span class="chip active"><i class="fa-solid fa-fire-flame-curved"></i> 2026 春季焦點</span>
      </div>
      <h1 class="hero-title">
        成功不是<br>
        <span class="line-gradient">一蹴而就而是</span><br>
        <span class="line-accent">每天持續的努力</span>
      </h1>
      <p class="hero-subtitle">
        —— 史蒂芬·柯維<br />
      </p>

      <!-- 毛玻璃時鐘 -->
      <div class="hero-stats">
        <div class="hero-clock glass">
          <div class="hero-clock-time" id="hero-clock-time">--:--:--</div>
          <div class="hero-clock-bottom">
            <span class="hero-clock-date" id="hero-clock-date">---- / -- / --</span>
            <span class="hero-clock-sep">・</span>
            <span class="hero-clock-weekday" id="hero-clock-weekday">---</span>
          </div>
        </div>
      </div>

      <div class="hero-actions">
        <a href="#season-section" class="btn btn-primary"><i class="fa-solid fa-calendar-check"></i> 看本季新番</a>
        <a href="#wiki-section" class="btn btn-secondary"><i class="fa-solid fa-book-open"></i> 瀏覽情報</a>
      </div>
    </div>

    <?php
    /* =====================================================
       ▼▼▼ 修改這裡：換成你自己的圖片網址、標題、連結 ▼▼▼
       ===================================================== */
    $hero_posters = [
        [
            'img'   => 'https://dev.weixiaoacg.com/wp-content/uploads/2026/04/AMQabn64.jpg',
            'title' => '動漫標題一',
            'url'   => 'https://dev.weixiaoacg.com/anime',
        ],
        [
            'img'   => 'https://dev.weixiaoacg.com/wp-content/uploads/2026/04/hHkWM6Qh.jpg',
            'title' => '動漫標題二',
            'url'   => 'https://dev.weixiaoacg.com/anime',
        ],
        [
            'img'   => 'https://dev.weixiaoacg.com/wp-content/uploads/2026/04/KfOWNtnB.jpg',
            'title' => '動漫標題三',
            'url'   => 'https://dev.weixiaoacg.com/anime',
        ],
    ];
    /* ▲▲▲ 修改到這裡為止 ▲▲▲ */
    ?>

    <div class="hero-posters" id="hero-posters">
      <?php foreach ( $hero_posters as $poster ) : ?>
      <a href="<?php echo esc_url( $poster['url'] ); ?>"
         class="poster-item glass"
         title="<?php echo esc_attr( $poster['title'] ); ?>">
        <img src="<?php echo esc_url( $poster['img'] ); ?>"
             alt="<?php echo esc_attr( $poster['title'] ); ?>"
             loading="lazy"
             onerror="this.style.display='none';this.closest('.poster-item').classList.add('skeleton');">
        <span class="poster-item__title"><?php echo esc_html( $poster['title'] ); ?></span>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<script>
(function () {
    const timeEl   = document.getElementById('hero-clock-time');
    const dateEl   = document.getElementById('hero-clock-date');
    const weekEl   = document.getElementById('hero-clock-weekday');
    const weekdays = ['星期日','星期一','星期二','星期三','星期四','星期五','星期六'];

    function tick() {
        const now = new Date();
        const hh  = String(now.getHours()).padStart(2, '0');
        const mm  = String(now.getMinutes()).padStart(2, '0');
        const ss  = String(now.getSeconds()).padStart(2, '0');
        const y   = now.getFullYear();
        const mo  = String(now.getMonth() + 1).padStart(2, '0');
        const d   = String(now.getDate()).padStart(2, '0');

        if (timeEl) timeEl.textContent = `${hh}：${mm}：${ss}`;
        if (dateEl) dateEl.textContent = `${y} / ${mo} / ${d}`;
        if (weekEl) weekEl.textContent = weekdays[now.getDay()];
    }

    tick();
    setInterval(tick, 1000);
})();
</script>


<!-- ============================================================
     最新新聞
     ============================================================ -->
<section class="section" id="news-section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">
        <i class="fa-solid fa-newspaper" style="margin-right:8px;"></i>最新新聞
      </h2>
      <a href="<?php echo esc_url( home_url('/news/') ); ?>" class="section-link">
        查看全部 <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <?php
    $news_all = new WP_Query([
        'post_type'      => 'post',
        'posts_per_page' => 6,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    /* ── 跑馬燈標題 ── */
    $ticker_items = [];
    if ( $news_all->have_posts() ) {
        foreach ( $news_all->posts as $p ) {
            $ticker_items[] = esc_html( $p->post_title );
        }
    } else {
        $ticker_items = [
            'SPY×FAMILY Season 3 製作確認',
            '進擊的巨人 OST 原聲帶全球發行',
            '台灣 ACG 展覽 2026 舉辦日期公布',
            '咒術迴戰最終章動畫化正式宣布',
            'LiSA 台灣演唱會門票即日起開放購票',
        ];
    }
    ?>

    <!-- 跑馬燈 -->
    <div class="news-ticker-wrap">
      <span class="news-ticker-label"><i class="fa-solid fa-bolt"></i> 快訊</span>
      <div class="news-ticker-overflow">
        <div class="news-ticker-track" id="tickerTrack">
          <?php foreach ( $ticker_items as $item ) : ?>
            <span><?php echo $item; ?>&nbsp;&nbsp;·&nbsp;&nbsp;</span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- 6 欄卡片網格 -->
    <?php if ( $news_all->have_posts() ) : ?>
    <div class="news-grid">
      <?php while ( $news_all->have_posts() ) : $news_all->the_post();
        $nid       = get_the_ID();
        $cats      = get_the_category( $nid );
        $cat_label = ! empty( $cats ) ? esc_html( $cats[0]->name ) : '新聞';
        $ntime     = human_time_diff( get_the_time('U'), current_time('timestamp') ) . '前';

        /* ── 封面圖：三層 fallback ── */
        $thumb = '';
        if ( function_exists('smaacg_get_news_thumb') ) {
            $thumb = smaacg_get_news_thumb( $nid, 'news-thumb' );
        }
        if ( ! $thumb ) {
            $thumb = get_the_post_thumbnail_url( $nid, 'medium' );
        }
        if ( ! $thumb ) {
            preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', get_the_content(), $img_match );
            $thumb = $img_match[1] ?? '';
        }
      ?>
      <a href="<?php the_permalink(); ?>" class="news-card glass">

        <!-- 封面圖區 -->
        <div class="news-card__thumb">
          <?php if ( $thumb ) : ?>
            <img src="<?php echo esc_url( $thumb ); ?>"
                 alt="<?php the_title_attribute(); ?>"
                 loading="lazy"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="news-card__placeholder" style="display:none">
              <i class="fa-solid fa-newspaper"></i>
            </div>
          <?php else : ?>
            <div class="news-card__placeholder">
              <i class="fa-solid fa-newspaper"></i>
            </div>
          <?php endif; ?>
          <span class="news-card__cat news-tag tag-rose"><?php echo $cat_label; ?></span>
        </div>

        <!-- 文字區 -->
        <div class="news-card__body">
          <h3 class="news-card__title"><?php the_title(); ?></h3>
          <div class="news-card__meta">
            <span><i class="fa-regular fa-clock"></i> <?php echo $ntime; ?></span>
          </div>
        </div>

      </a>
      <?php endwhile; wp_reset_postdata(); ?>
    </div><!-- .news-grid -->

    <?php else : ?>
    <div class="news-empty glass-mid">
      <span style="font-size:2rem;">📭</span>
      <p>目前尚無新聞，請稍後回來查看。</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<style>
/* ================================================================
   新聞 6 欄卡片網格
   ================================================================ */
.news-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 24px;
}
@media (max-width: 1024px) {
    .news-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 540px) {
    .news-grid { grid-template-columns: 1fr; gap: 14px; }
}

/* 單張卡片 */
.news-card {
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    overflow: hidden;
    text-decoration: none;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    transition: transform .2s ease, box-shadow .2s ease;
}
.news-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 32px rgba(0,0,0,.45);
}

/* 封面圖 */
.news-card__thumb {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: rgba(255,255,255,.06);
    flex-shrink: 0;
}
.news-card__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .35s ease;
}
.news-card:hover .news-card__thumb img {
    transform: scale(1.06);
}

/* 無圖佔位 */
.news-card__placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(99,168,255,.12), rgba(168,99,255,.10));
    color: rgba(255,255,255,.25);
    font-size: 36px;
}

/* 分類角標 */
.news-card__cat {
    position: absolute;
    top: 10px;
    left: 10px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    backdrop-filter: blur(6px);
}

/* 文字區 */
.news-card__body {
    padding: 14px 16px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}
.news-card__title {
    font-size: 14px;
    font-weight: 700;
    color: rgba(220,230,245,.90);
    margin: 0;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.news-card__meta {
    margin-top: auto;
    font-size: 12px;
    color: rgba(220,230,245,.4);
    display: flex;
    align-items: center;
    gap: 6px;
}
</style>

<!-- ============================================================
     本季新番
     ============================================================ -->
<?php
$_season_query = new WP_Query( [
    'post_type'      => 'anime',
    'post_status'    => 'publish',
    'posts_per_page' => 30,
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
    'meta_key'       => 'anime_title_chinese',
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'     => 'anime_season',
            'value'   => 'SPRING',
            'compare' => '=',
        ],
        [
            'key'     => 'anime_season_year',
            'value'   => '2026',
            'compare' => '=',
            'type'    => 'NUMERIC',
        ],
    ],
] );
?>

<section class="section season-section" id="season-section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">本季新番導航</h2>
      <div class="tab-switch weekday-tabs" id="weekday-tabs">
        <button class="tab-btn weekday-tab active" data-day="0">全部</button>
        <button class="tab-btn weekday-tab" data-day="1">週一</button>
        <button class="tab-btn weekday-tab" data-day="2">週二</button>
        <button class="tab-btn weekday-tab" data-day="3">週三</button>
        <button class="tab-btn weekday-tab" data-day="4">週四</button>
        <button class="tab-btn weekday-tab" data-day="5">週五</button>
        <button class="tab-btn weekday-tab" data-day="6">週六</button>
        <button class="tab-btn weekday-tab" data-day="7">週日</button>
      </div>
      <a href="<?php echo esc_url( home_url('/season/') ); ?>" class="section-link">
        看完整新番表 <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <?php if ( $_season_query->have_posts() ) : ?>
    <div class="season-cards scroll-row" id="season-cards">
      <?php while ( $_season_query->have_posts() ) : $_season_query->the_post();
        $pid    = get_the_ID();

        $cover  = get_field( 'anime_cover_image', $pid )
                  ?: get_the_post_thumbnail_url( $pid, 'season-thumb' );

        $title  = get_field( 'anime_title_chinese', $pid ) ?: get_the_title();

        $score_raw = (float) get_field( 'anime_score_anilist', $pid );
        $score     = $score_raw > 0 ? number_format( $score_raw / 10, 1 ) : '';

        $ep_total  = (int) get_field( 'anime_episodes', $pid );
        $ep_aired  = (int) get_field( 'anime_episodes_aired', $pid );
        $ep_label  = '';
        if ( $ep_total > 0 ) {
            $ep_label = $ep_aired > 0 && $ep_aired < $ep_total
                ? "{$ep_aired}/{$ep_total} 集"
                : "{$ep_total} 集";
        } elseif ( $ep_aired > 0 ) {
            $ep_label = "第 {$ep_aired} 集";
        }

        $status = get_field( 'anime_status', $pid );
        $status_label = match( $status ) {
            'RELEASING'        => '連載中',
            'NOT_YET_RELEASED' => '即將播出',
            'FINISHED'         => '已完結',
            'HIATUS'           => '休播中',
            default            => '',
        };
        $status_class = match( $status ) {
            'RELEASING'        => 'status--on-air',
            'NOT_YET_RELEASED' => 'status--upcoming',
            'FINISHED'         => 'status--finished',
            default            => '',
        };

        $weekday = 0;
        $next_airing = get_field( 'anime_next_airing', $pid );
        if ( $next_airing ) {
            $ts = strtotime( $next_airing );
            if ( $ts ) $weekday = (int) date( 'N', $ts );
        }
        if ( ! $weekday ) {
            $start_date = get_field( 'anime_start_date', $pid );
            if ( $start_date ) {
                $ts = strtotime( $start_date );
                if ( $ts ) $weekday = (int) date( 'N', $ts );
            }
        }
      ?>

      <a href="<?php the_permalink(); ?>"
         class="season-card glass"
         data-day="<?php echo esc_attr( $weekday ); ?>"
         title="<?php echo esc_attr( $title ); ?>">

        <div class="season-card__cover-wrap">
          <?php if ( $cover ) : ?>
            <img src="<?php echo esc_url( $cover ); ?>"
                 alt="<?php echo esc_attr( $title ); ?>"
                 class="season-card__cover" loading="lazy">
          <?php else : ?>
            <div class="season-card__cover season-card__cover--placeholder">
              <i class="fa-solid fa-film" aria-hidden="true"></i>
            </div>
          <?php endif; ?>

          <?php if ( $score ) : ?>
          <span class="season-card__score">
            <i class="fa-solid fa-star" aria-hidden="true"></i>
            <?php echo esc_html( $score ); ?>
          </span>
          <?php endif; ?>

          <?php if ( $status_label ) : ?>
          <span class="season-card__status <?php echo esc_attr( $status_class ); ?>">
            <?php echo esc_html( $status_label ); ?>
          </span>
          <?php endif; ?>
        </div>

        <div class="season-card__info">
          <p class="season-card__title"><?php echo esc_html( $title ); ?></p>
          <?php if ( $ep_label ) : ?>
          <span class="season-card__ep"><?php echo esc_html( $ep_label ); ?></span>
          <?php endif; ?>
        </div>

      </a>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>

    <?php else : ?>
    <div class="season-empty glass">
      <i class="fa-solid fa-calendar-xmark fa-2x" aria-hidden="true"></i>
      <p>本季新番資料準備中，敬請期待。</p>
    </div>
    <?php endif; ?>

  </div>
</section>

<script>
(function () {
    const tabs  = document.querySelectorAll('.weekday-tab');
    const cards = document.querySelectorAll('#season-cards .season-card');
    if ( !tabs.length || !cards.length ) return;

    tabs.forEach( tab => {
        tab.addEventListener('click', () => {
            tabs.forEach( t => t.classList.remove('active') );
            tab.classList.add('active');
            const day = tab.dataset.day;
            cards.forEach( card => {
                card.style.display =
                    ( day === '0' || card.dataset.day === day ) ? '' : 'none';
            });
        });
    });
})();
</script>

<!-- ============================================================
     熱門作品（唯一一份，函式只定義一次）
     ============================================================ -->
<?php
/* ── 季度計算：下一季 ── */
$current_month = (int) date('n');
$current_year  = (int) date('Y');

if ( $current_month >= 1 && $current_month <= 3 ) {
    $next_season = 'SPRING'; $next_year = $current_year;
} elseif ( $current_month >= 4 && $current_month <= 6 ) {
    $next_season = 'SUMMER'; $next_year = $current_year;
} elseif ( $current_month >= 7 && $current_month <= 9 ) {
    $next_season = 'FALL';   $next_year = $current_year;
} else {
    $next_season = 'WINTER'; $next_year = $current_year + 1;
}

/* ── 查詢函式（只定義一次，加 function_exists 防呆）── */
if ( ! function_exists( 'smacg_get_anime' ) ) {
    function smacg_get_anime( $orderby_meta, $order = 'DESC', $extra_meta = [] ) {
        $args = [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'meta_key'       => $orderby_meta,
            'orderby'        => 'meta_value_num',
            'order'          => $order,
            'no_found_rows'  => true,
        ];
        if ( ! empty( $extra_meta ) ) {
            $args['meta_query'] = array_merge( [ 'relation' => 'AND' ], $extra_meta );
        }
        return new WP_Query( $args );
    }
}

/* ── 卡片輸出函式（只定義一次，加 function_exists 防呆）── */
if ( ! function_exists( 'smacg_anime_card' ) ) {
    function smacg_anime_card( $post ) {
        $id    = $post->ID;
        $title = get_post_meta( $id, 'anime_title_chinese', true ) ?: $post->post_title;
        $cover = get_post_meta( $id, 'anime_cover_image', true );
        $score = get_post_meta( $id, 'anime_score_site', true );
        $url   = get_permalink( $id );

        $score_display = $score ? number_format( (float) $score, 1 ) : null;
        $fb = mb_substr( $title, 0, 2 );
        ?>
        <a href="<?php echo esc_url( $url ); ?>" class="smacg-anime-card">
            <div class="smacg-card-thumb">
                <?php if ( $cover ) : ?>
                    <img src="<?php echo esc_url( $cover ); ?>"
                         alt="<?php echo esc_attr( $title ); ?>"
                         loading="lazy"
                         onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="smacg-card-fb" style="display:none"><span><?php echo esc_html( $fb ); ?></span></div>
                <?php else : ?>
                    <div class="smacg-card-fb"><span><?php echo esc_html( $fb ); ?></span></div>
                <?php endif; ?>
                <?php if ( $score_display ) : ?>
                    <span class="smacg-card-score">
                        <i class="fa-solid fa-star"></i> <?php echo esc_html( $score_display ); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="smacg-card-body">
                <h3 class="smacg-card-title"><?php echo esc_html( $title ); ?></h3>
            </div>
        </a>
        <?php
    }
}
?>

<section class="section" id="hot-anime-section">
  <div class="container">

    <div class="section-header">
      <h2 class="section-title">熱門作品</h2>
      <div class="tab-switch">
        <button class="smacg-tab-btn active" data-tab="trending">大家都在看</button>
        <button class="smacg-tab-btn" data-tab="top">歷年神作</button>
        <button class="smacg-tab-btn" data-tab="upcoming">即將開播</button>
      </div>
      <a href="<?php echo esc_url( home_url('/anime/') ); ?>" class="section-link">
        更多作品 <i class="fa-solid fa-arrow-right"></i>
      </a>
    </div>

    <?php /* ── 大家都在看 ── */ ?>
    <div class="smacg-anime-grid" id="smacg-tab-trending">
        <?php
        $q = smacg_get_anime( 'anime_score_site_count' );
        if ( $q->have_posts() ) :
            while ( $q->have_posts() ) { $q->the_post(); smacg_anime_card( get_post() ); }
            wp_reset_postdata();
        else : ?>
            <p class="smacg-tab-empty">暫無資料</p>
        <?php endif; ?>
    </div>

    <?php /* ── 歷年神作 ── */ ?>
    <div class="smacg-anime-grid" id="smacg-tab-top" style="display:none">
        <?php
        $q = smacg_get_anime( 'anime_score_site' );
        if ( $q->have_posts() ) :
            while ( $q->have_posts() ) { $q->the_post(); smacg_anime_card( get_post() ); }
            wp_reset_postdata();
        else : ?>
            <p class="smacg-tab-empty">暫無資料</p>
        <?php endif; ?>
    </div>

    <?php /* ── 即將開播 ── */ ?>
    <div class="smacg-anime-grid" id="smacg-tab-upcoming" style="display:none">
        <?php
        $q = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'anime_season',      'value' => $next_season, 'compare' => '=' ],
                [ 'key' => 'anime_season_year', 'value' => $next_year,   'compare' => '=', 'type' => 'NUMERIC' ],
            ],
        ] );
        if ( $q->have_posts() ) :
            while ( $q->have_posts() ) { $q->the_post(); smacg_anime_card( get_post() ); }
            wp_reset_postdata();
        else : ?>
            <p class="smacg-tab-empty">下一季暫無資料</p>
        <?php endif; ?>
    </div>

  </div>
</section>

<style>
.smacg-anime-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    margin-top: 20px;
}
@media (max-width: 1024px) {
    .smacg-anime-grid { grid-template-columns: repeat(4, 1fr); }
}
@media (max-width: 768px) {
    .smacg-anime-grid { grid-template-columns: repeat(3, 1fr); gap: 12px; }
}
@media (max-width: 480px) {
    .smacg-anime-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
}

.smacg-anime-card {
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    overflow: hidden;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    text-decoration: none;
    transition: transform .2s ease, box-shadow .2s ease;
}
.smacg-anime-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 32px rgba(0,0,0,.4);
}

.smacg-card-thumb {
    position: relative;
    width: 100%;
    aspect-ratio: 3/4;
    overflow: hidden;
    background: rgba(255,255,255,.06);
}
.smacg-card-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .3s ease;
}
.smacg-anime-card:hover .smacg-card-thumb img {
    transform: scale(1.05);
}
.smacg-card-fb {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 800;
    color: rgba(255,255,255,.3);
    background: rgba(255,255,255,.04);
}
.smacg-card-score {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(0,0,0,.75);
    color: #ffd60a;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    gap: 3px;
}
.smacg-card-body {
    padding: 10px 12px 12px;
}
.smacg-card-title {
    font-size: 13px;
    font-weight: 600;
    color: rgba(220,230,245,.85);
    margin: 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.smacg-tab-btn {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.08);
    color: rgba(220,230,245,.55);
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s ease;
}
.smacg-tab-btn:hover {
    background: rgba(255,255,255,.10);
    color: rgba(220,230,245,.85);
}
.smacg-tab-btn.active {
    background: rgba(99,168,255,.20);
    border-color: rgba(99,168,255,.45);
    color: #63a8ff;
}
.smacg-tab-empty {
    color: rgba(220,230,245,.4);
    font-size: 14px;
    padding: 40px 0;
    text-align: center;
    grid-column: 1 / -1;
}
</style>

<script>
(function () {
    const btns   = document.querySelectorAll('#hot-anime-section .smacg-tab-btn');
    const panels = {
        trending : document.getElementById('smacg-tab-trending'),
        top      : document.getElementById('smacg-tab-top'),
        upcoming : document.getElementById('smacg-tab-upcoming'),
    };

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = this.dataset.tab;
            btns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            Object.keys(panels).forEach(function (key) {
                panels[key].style.display = key === target ? 'grid' : 'none';
            });
        });
    });
})();
</script>

<!-- ============================================================
     未來場景 Coming Soon
     ============================================================ -->
<section class="section coming-soon-section">
  <div class="container">
    <div class="section-header">
      <div>
        <h2 class="section-title">微笑動漫未來場景</h2>
        <p style="font-size:13px; color:var(--text-muted); margin-top:6px;">更多精彩，陸續展開</p>
      </div>
    </div>
    <div class="coming-cards-grid">

      <div class="coming-card glass">
        <div class="coming-card-icon">🎮</div>
        <div class="coming-card-title">遊戲情報</div>
        <div class="coming-card-desc">ACG 改編遊戲・手遊攻略・玩法解析</div>
        <span class="coming-card-btn"><i class="fa-solid fa-clock"></i> 敬請期待</span>
      </div>

      <div class="coming-card glass">
        <div class="coming-card-icon">🏆</div>
        <div class="coming-card-title">周邊收藏</div>
        <div class="coming-card-desc">手辦・模型・排名商品・發售日期</div>
        <span class="coming-card-btn"><i class="fa-solid fa-clock"></i> 敬請期待</span>
      </div>

      <div class="coming-card glass">
        <div class="coming-card-icon">🤖</div>
        <div class="coming-card-title">AI 工具實驗室</div>
        <div class="coming-card-desc">ACG 向 AI 生成工具與應用導覽</div>
        <span class="coming-card-btn"><i class="fa-solid fa-clock"></i> 敬請期待</span>
      </div>

      <div class="coming-card glass">
        <div class="coming-card-icon">🕹️</div>
        <div class="coming-card-title">電競資訊</div>
        <div class="coming-card-desc">日系電競賽事・選手資料・賽程播報</div>
        <span class="coming-card-btn"><i class="fa-solid fa-clock"></i> 敬請期待</span>
      </div>

    </div>
  </div>
</section>

<!-- ============================================================
     會員 CTA
     ============================================================ -->
<section class="section member-cta-section">
  <div class="container">
    <div class="member-cta-grid">
      <div class="member-cta-left">
        <span class="member-cta-badge"><i class="fa-solid fa-user-plus"></i> 免費加入會員</span>
        <h2 class="member-cta-title">打造你的玻璃收藏牆</h2>
        <p class="member-cta-desc">收藏作品・追番進度・私房清單・解鎖成就・展示頁面</p>
        <div class="member-cta-btns">
          <a href="<?php echo esc_url( wp_registration_url() ); ?>" class="btn btn-primary">
            <i class="fa-solid fa-user-plus"></i> 免費註冊
          </a>
          <a href="<?php echo esc_url( home_url('/about/') ); ?>" class="btn btn-secondary">
            <i class="fa-solid fa-eye"></i> 探索功能
          </a>
        </div>
      </div>
      <div class="member-level-panel glass-mid">
        <div class="member-level-title">會員成長路徑</div>
        <div class="member-level-list">
          <div class="member-level-item"><div class="member-level-icon">🥤</div><div class="member-level-info"><div class="member-level-name">Lv.1 初入番坑</div></div><span class="member-level-tag tag-cyan">新手歡迎者</span></div>
          <div class="member-level-item"><div class="member-level-icon">⭐</div><div class="member-level-info"><div class="member-level-name">Lv.2 收藏見習生</div></div><span class="member-level-tag tag-blue">見習收藏家</span></div>
          <div class="member-level-item"><div class="member-level-icon">🔥</div><div class="member-level-info"><div class="member-level-name">Lv.3 追番達家</div></div><span class="member-level-tag tag-orange">頻道創作者</span></div>
          <div class="member-level-item"><div class="member-level-icon">🏆</div><div class="member-level-info"><div class="member-level-name">Lv.5 動漫鑑賞家</div></div><span class="member-level-tag tag-blue">鑑賞家</span></div>
          <div class="member-level-item member-level-locked"><div class="member-level-icon">👑</div><div class="member-level-info"><div class="member-level-name">Lv.7 微笑榮譽會員</div></div><span class="member-level-tag tag-locked"><i class="fa-solid fa-lock"></i></span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php get_footer(); ?>

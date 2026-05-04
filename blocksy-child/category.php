<?php
/**
 * Category / Channel Archive Template
 * 服務 URL：
 *   /news/  /review/  /feature/  /announcement/
 *   /news/anime/  /news/voice-actor/  /review/game/  ...
 *
 * Path: wp-content/themes/blocksy-child/category.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// ── 取得目前 archive 的 term ──
$queried        = get_queried_object();
$current_term   = ( $queried instanceof WP_Term ) ? $queried : null;
$current_tax    = $current_term ? $current_term->taxonomy : '';
$current_termid = $current_term ? $current_term->term_id  : 0;
$current_slug   = $current_term ? $current_term->slug     : '';

// ── 頁面標題 / 副標題（依分類動態顯示） ──
$page_titles = [
    'announcement' => [ '本站公告', '官方訊息・系統公告・重要通知' ],
    'news'         => [ '最新動漫資訊', '聲優消息・新番公告・活動報導・業界動態，每日更新' ],
    'review'       => [ '動漫評論', '深度解析・心得分享・作品評價' ],
    'feature'      => [ '專題報導', '深度專題・年度回顧・主題企劃' ],
];
if ( 'category' === $current_tax && isset( $page_titles[ $current_slug ] ) ) {
    $hero_title    = $page_titles[ $current_slug ][0];
    $hero_subtitle = $page_titles[ $current_slug ][1];
    $hero_badge    = $current_term->name;
} elseif ( $current_term ) {
    $hero_title    = single_term_title( '', false );
    $hero_subtitle = $current_term->description ?: '相關文章列表';
    $hero_badge    = $current_term->name;
} else {
    $hero_title    = '所有文章';
    $hero_subtitle = '';
    $hero_badge    = '文章';
}

// ── 共用：依目前 archive 過濾 tax_query ──
$archive_tax_query = [];
if ( $current_term ) {
    $archive_tax_query[] = [
        'taxonomy' => $current_tax,
        'field'    => 'term_id',
        'terms'    => $current_termid,
    ];
}

// ── 輪播：本分類最新 5 篇 ──
$carousel_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'tax_query'           => $archive_tax_query,
] );

// ── 熱門：本分類留言數最多 5 篇 ──
$popular_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'orderby'             => 'comment_count',
    'order'               => 'DESC',
    'tax_query'           => $archive_tax_query,
] );

// ── 熱門標籤（全站）──
$popular_tags = get_tags( [
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 15,
    'hide_empty' => true,
] );

// ── Filter Tabs：
// 在 category 頁顯示 channel 列表；在 channel 頁顯示 category 列表
$filter_label  = '';
$filter_terms  = [];
$filter_all_url = '';
if ( 'category' === $current_tax ) {
    // 例如在 /news/ 顯示所有 channel
    $filter_label  = '頻道';
    $filter_terms  = get_terms( [ 'taxonomy' => 'channel', 'hide_empty' => true ] );
    $filter_all_url = get_term_link( $current_term );
} elseif ( 'channel' === $current_tax ) {
    // 例如在 /news/anime/ 顯示「全部新聞 / 評論 / 專題」
    $filter_label  = '類型';
    $filter_terms  = get_categories( [
        'slug'       => [ 'announcement', 'news', 'review', 'feature' ],
        'hide_empty' => false,
    ] );
    $filter_all_url = get_term_link( $current_term );
}
?>

<!-- 額外載入本頁專用 CSS / JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<link rel="stylesheet" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/css/news.css' ); ?>" />

<!-- ===== PAGE HERO ===== -->
<div class="page-hero">
  <div class="container">
    <div class="page-badge"><i class="fa-solid fa-newspaper"></i> <?php echo esc_html( $hero_badge ); ?></div>
    <h1 class="page-title"><?php echo esc_html( $hero_title ); ?></h1>
    <?php if ( $hero_subtitle ) : ?>
      <p class="page-subtitle"><?php echo esc_html( $hero_subtitle ); ?></p>
    <?php endif; ?>
  </div>
</div>

<!-- ===== MAIN ===== -->
<main class="container" style="padding: 32px 0 64px;">

  <!-- ── 海報輪播 ── -->
  <?php if ( $carousel_query->have_posts() ) : ?>
  <div class="news-carousel-wrap">
    <div class="swiper news-swiper">
      <div class="swiper-wrapper">
        <?php while ( $carousel_query->have_posts() ) : $carousel_query->the_post();
          $cats      = get_the_category();
          $cat_label = ! empty( $cats ) ? esc_html( $cats[0]->name ) : '最新';

          $carousel_img_url = '';
          if ( has_post_thumbnail() ) {
              $carousel_img_url = get_the_post_thumbnail_url( get_the_ID(), 'large' );
          } else {
              preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', get_the_content(), $m );
              if ( ! empty( $m[1] ) ) $carousel_img_url = $m[1];
          }
        ?>
        <div class="swiper-slide">
          <a href="<?php the_permalink(); ?>" class="swiper-slide-inner">
            <?php if ( $carousel_img_url ) : ?>
              <div class="swiper-slide-bg" style="background-image: url('<?php echo esc_url( $carousel_img_url ); ?>');"></div>
              <img class="carousel-main-img"
                   src="<?php echo esc_url( $carousel_img_url ); ?>"
                   alt="<?php echo esc_attr( get_the_title() ); ?>"
                   loading="lazy" />
            <?php else : ?>
              <div class="carousel-no-img">📰</div>
            <?php endif; ?>

            <div class="swiper-slide-caption">
              <div class="swiper-slide-tag"><?php echo $cat_label; ?></div>
              <div class="swiper-slide-title"><?php the_title(); ?></div>
              <div class="swiper-slide-meta">
                <i class="fa-regular fa-clock"></i> <?php echo get_the_date( 'Y-m-d' ); ?>
                &nbsp;·&nbsp;
                <i class="fa-regular fa-user"></i> <?php the_author(); ?>
              </div>
            </div>
          </a>
        </div>
        <?php endwhile; wp_reset_postdata(); ?>
      </div>
      <div class="swiper-pagination"></div>
      <div class="swiper-button-prev"></div>
      <div class="swiper-button-next"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Filter Tabs（頻道 / 類型切換） ── -->
  <?php if ( ! empty( $filter_terms ) ) : ?>
  <div class="news-filter">
    <a href="<?php echo esc_url( $filter_all_url ); ?>" class="news-filter-btn active">全部</a>
    <?php foreach ( $filter_terms as $t ) :
      // 在 category 頁，這裡列出的是 channel：連到 /{current_cat}/{channel}/ 才符合 editorial routing
      if ( 'category' === $current_tax ) {
          $tab_url = home_url( '/' . $current_slug . '/' . $t->slug . '/' );
      } else {
          // 在 channel 頁，列的是 category：連到 /{cat}/{current_channel}/
          $tab_url = home_url( '/' . $t->slug . '/' . $current_slug . '/' );
      }
    ?>
      <a href="<?php echo esc_url( $tab_url ); ?>" class="news-filter-btn">
        <?php echo esc_html( $t->name ); ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="news-layout">

    <!-- ── 主要新聞區（使用主迴圈，由 WordPress 自動篩文章） ── -->
    <div class="news-main-grid">

      <?php if ( have_posts() ) : ?>
        <div class="news-card-list">
          <?php while ( have_posts() ) : the_post();
            $cats      = get_the_category();
            $cat_label = ! empty( $cats ) ? esc_html( $cats[0]->name ) : '文章';
          ?>
          <a href="<?php the_permalink(); ?>" class="news-card glass">
            <div class="news-card-img">
              <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'medium', [ 'alt' => get_the_title(), 'loading' => 'lazy' ] ); ?>
              <?php else :
                preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', get_the_content(), $cm );
                if ( ! empty( $cm[1] ) ) : ?>
                  <img src="<?php echo esc_url( $cm[1] ); ?>"
                       alt="<?php echo esc_attr( get_the_title() ); ?>"
                       loading="lazy" />
                <?php else : ?>
                  <span class="news-card-placeholder">📰</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="news-card-body">
              <div class="news-card-tag"><?php echo $cat_label; ?></div>
              <div class="news-card-title"><?php the_title(); ?></div>
              <div class="news-card-meta">
                <i class="fa-regular fa-clock"></i> <?php echo get_the_date( 'Y-m-d' ); ?>
              </div>
            </div>
          </a>
          <?php endwhile; ?>
        </div>

        <!-- ── 分頁（自動跟主查詢） ── -->
        <div class="news-pagination">
          <?php
          the_posts_pagination( [
              'prev_text' => '<i class="fa-solid fa-chevron-left"></i>',
              'next_text' => '<i class="fa-solid fa-chevron-right"></i>',
              'end_size'  => 2,
              'mid_size'  => 1,
          ] );
          ?>
        </div>

      <?php else : ?>
        <div class="news-empty glass">
          <i class="fa-regular fa-newspaper"></i>
          <p>目前沒有文章，請稍後再來查看。</p>
        </div>
      <?php endif; ?>

    </div>

    <!-- ── 側欄 ── -->
    <aside class="news-sidebar">

      <!-- 熱門新聞 -->
      <div class="sidebar-widget glass">
        <div class="sidebar-widget-title">
          <i class="fa-solid fa-fire" style="color:#f97316;"></i> 熱門文章
        </div>
        <?php if ( $popular_query->have_posts() ) :
          $pop_i = 0;
          while ( $popular_query->have_posts() ) : $popular_query->the_post();
            $pop_i++;
            $is_top = $pop_i <= 3 ? 'top-3' : '';
        ?>
        <a href="<?php the_permalink(); ?>" class="sidebar-list-item">
          <div class="sidebar-item-num <?php echo $is_top; ?>"><?php echo $pop_i; ?></div>
          <div>
            <div class="sidebar-item-title"><?php the_title(); ?></div>
            <div class="sidebar-item-date">
              <?php echo human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ); ?>前
            </div>
          </div>
        </a>
        <?php endwhile; wp_reset_postdata(); ?>
        <?php endif; ?>
      </div>

      <!-- 熱門標籤 -->
      <?php if ( ! empty( $popular_tags ) ) : ?>
      <div class="sidebar-widget glass">
        <div class="sidebar-widget-title">
          <i class="fa-solid fa-tags" style="color:var(--accent-blue);"></i> 熱門標籤
        </div>
        <div class="tag-cloud">
          <?php foreach ( $popular_tags as $tag ) : ?>
          <a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="tag-pill">
            #<?php echo esc_html( $tag->name ); ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- 訂閱快報 -->
      <div class="sidebar-widget glass subscribe-box">
        <div class="sidebar-widget-title">
          <i class="fa-solid fa-bell" style="color:var(--accent-blue);"></i> 訂閱快報
        </div>
        <p class="subscribe-desc">每週精選重要動漫資訊，直送你的信箱</p>
        <input type="email" class="subscribe-input" placeholder="your@email.com" />
        <button class="btn btn-primary subscribe-btn">訂閱</button>
      </div>

    </aside>
  </div>
</main>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
(function () {
  'use strict';
  if ( document.querySelector('.news-swiper') ) {
    new Swiper('.news-swiper', {
      loop:       true,
      autoplay:   { delay: 5000, disableOnInteraction: false },
      speed:      700,
      effect:     'fade',
      fadeEffect: { crossFade: true },
      pagination: { el: '.swiper-pagination', clickable: true },
      navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
      a11y:       { enabled: true },
    });
  }
})();
</script>

<?php
get_footer();

<?php
/**
 * Template Name: 動漫新聞
 * Path: wp-content/themes/your-theme/page-news.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// ── 分頁參數 ──
$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

// ── 輪播：最新 5 篇 ──
$carousel_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'paged'               => 1,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
] );

// ── 主要新聞列表：每頁 12 篇 ──
$news_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 12,
    'paged'               => $paged,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'orderby'             => 'date',
    'order'               => 'DESC',
] );

// ── 熱門新聞 ──
$popular_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'orderby'             => 'comment_count',
    'order'               => 'DESC',
] );

// ── 熱門標籤 ──
$popular_tags = get_tags( [
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 15,
    'hide_empty' => true,
] );

// ── 所有分類 ──
$all_categories = get_categories( [
    'orderby'    => 'count',
    'order'      => 'DESC',
    'hide_empty' => true,
] );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php wp_head(); ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri(); ?>/css/news.css" />
</head>
<body <?php body_class(); ?>>

<!-- ===== PAGE HERO ===== -->
<div class="page-hero">
  <div class="container">
    <div class="page-badge"><i class="fa-solid fa-newspaper"></i> 動漫新聞</div>
    <h1 class="page-title">最新動漫資訊</h1>
    <p class="page-subtitle">聲優消息・新番公告・活動報導・業界動態，每日更新</p>
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

          // 抓圖片 URL
          $carousel_img_url = '';
          if ( has_post_thumbnail() ) {
              $carousel_img_url = get_the_post_thumbnail_url( get_the_ID(), 'large' );
          } else {
              $post_content = get_the_content();
              preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $post_content, $matches );
              if ( ! empty( $matches[1] ) ) {
                  $carousel_img_url = $matches[1];
              }
          }
        ?>
        <div class="swiper-slide">
          <a href="<?php the_permalink(); ?>" class="swiper-slide-inner">

            <?php if ( $carousel_img_url ) : ?>
              <!-- 模糊背景 -->
              <div class="swiper-slide-bg"
                   style="background-image: url('<?php echo esc_url( $carousel_img_url ); ?>');">
              </div>
              <!-- 主圖 -->
              <img class="carousel-main-img"
                   src="<?php echo esc_url( $carousel_img_url ); ?>"
                   alt="<?php echo esc_attr( get_the_title() ); ?>"
                   loading="lazy" />
            <?php else : ?>
              <div class="carousel-no-img">📰</div>
            <?php endif; ?>

            <!-- 字幕 -->
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

  <!-- ── Filter Tabs ── -->
  <div class="news-filter">
    <?php
    $current_cat = get_query_var( 'cat' );
    $news_page   = get_permalink( get_the_ID() );
    ?>
    <a href="<?php echo esc_url( $news_page ); ?>"
       class="news-filter-btn <?php echo ( ! $current_cat ) ? 'active' : ''; ?>">
      全部
    </a>
    <?php foreach ( $all_categories as $cat ) : ?>
    <a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"
       class="news-filter-btn <?php echo ( absint( $current_cat ) === absint( $cat->term_id ) ) ? 'active' : ''; ?>">
      <?php echo esc_html( $cat->name ); ?>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="news-layout">

    <!-- ── 主要新聞區 ── -->
    <div class="news-main-grid">

      <?php if ( $news_query->have_posts() ) : ?>
        <div class="news-card-list">
          <?php while ( $news_query->have_posts() ) : $news_query->the_post();
            $cats      = get_the_category();
            $cat_label = ! empty( $cats ) ? esc_html( $cats[0]->name ) : '新聞';
          ?>
          <a href="<?php the_permalink(); ?>" class="news-card glass">
            <div class="news-card-img">
              <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'medium', [ 'alt' => get_the_title(), 'loading' => 'lazy' ] ); ?>
              <?php else : ?>
                <?php
                // fallback 抓文章內第一張圖
                $post_content = get_the_content();
                preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $post_content, $card_matches );
                if ( ! empty( $card_matches[1] ) ) : ?>
                  <img src="<?php echo esc_url( $card_matches[1] ); ?>"
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
          <?php endwhile; wp_reset_postdata(); ?>
        </div>

        <!-- ── 分頁 ── -->
        <?php if ( $news_query->max_num_pages > 1 ) : ?>
        <div class="news-pagination">
          <?php
          echo paginate_links( [
              'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
              'format'    => '?paged=%#%',
              'current'   => max( 1, $paged ),
              'total'     => $news_query->max_num_pages,
              'prev_text' => '<i class="fa-solid fa-chevron-left"></i>',
              'next_text' => '<i class="fa-solid fa-chevron-right"></i>',
              'type'      => 'plain',
              'end_size'  => 2,
              'mid_size'  => 1,
          ] );
          ?>
        </div>
        <?php endif; ?>

      <?php else : ?>
        <div class="news-empty glass">
          <i class="fa-regular fa-newspaper"></i>
          <p>目前沒有新聞，請稍後再來查看。</p>
        </div>
      <?php endif; ?>

    </div>

    <!-- ── 側欄 ── -->
    <aside class="news-sidebar">

      <!-- 熱門新聞 -->
      <div class="sidebar-widget glass">
        <div class="sidebar-widget-title">
          <i class="fa-solid fa-fire" style="color:#f97316;"></i> 熱門新聞
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
          <i class="fa-solid fa-bell" style="color:var(--accent-blue);"></i> 訂閱新聞快報
        </div>
        <p class="subscribe-desc">每週精選重要動漫資訊，直送你的信箱</p>
        <input type="email" class="subscribe-input" placeholder="your@email.com" />
        <button class="btn btn-primary subscribe-btn">訂閱</button>
      </div>

    </aside>
  </div>
</main>

<?php get_footer(); ?>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
(function () {
  'use strict';

  // ── Swiper 輪播 ──
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
<?php wp_footer(); ?>
</body>
</html>

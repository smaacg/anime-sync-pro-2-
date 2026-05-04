<?php
/**
 * Single Post Template
 * 服務 URL：
 *   /announcement/post-slug/
 *   /news/anime/post-slug/   /news/voice-actor/post-slug/
 *   /review/game/post-slug/  /feature/industry/post-slug/  ...
 *
 * Path: wp-content/themes/blocksy-child/single.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// ── 取得目前文章的 category + channel ──
$post_id        = get_the_ID();
$primary_cat    = null;   // WP_Term (category)
$primary_chan   = null;   // WP_Term (channel)

$cats = get_the_category( $post_id );
if ( ! empty( $cats ) ) {
    // 優先抓 announcement/news/review/feature
    $editorial_slugs = [ 'announcement', 'news', 'review', 'feature' ];
    foreach ( $cats as $c ) {
        if ( in_array( $c->slug, $editorial_slugs, true ) ) { $primary_cat = $c; break; }
    }
    if ( ! $primary_cat ) $primary_cat = $cats[0];
}

$chans = get_the_terms( $post_id, 'channel' );
if ( ! empty( $chans ) && ! is_wp_error( $chans ) ) {
    $primary_chan = $chans[0];
}

// ── 閱讀時間估算 ──
$content_text  = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
$word_count    = mb_strlen( $content_text, 'UTF-8' );
$read_minutes  = max( 1, (int) ceil( $word_count / 400 ) );  // 中文約 400 字/分

// ── 同分類熱門文章（側欄）──
$sidebar_tax_query = [];
if ( $primary_cat ) {
    $sidebar_tax_query[] = [
        'taxonomy' => 'category',
        'field'    => 'term_id',
        'terms'    => $primary_cat->term_id,
    ];
}
$popular_query = new WP_Query( [
    'post_type'           => 'post',
    'posts_per_page'      => 5,
    'post__not_in'        => [ $post_id ],
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'orderby'             => 'comment_count',
    'order'               => 'DESC',
    'tax_query'           => $sidebar_tax_query,
] );

// ── 相關文章（同 category + 同 channel 優先）──
$related_args = [
    'post_type'           => 'post',
    'posts_per_page'      => 6,
    'post__not_in'        => [ $post_id ],
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'orderby'             => 'date',
    'order'               => 'DESC',
];
$related_tax = [];
if ( $primary_cat ) {
    $related_tax[] = [
        'taxonomy' => 'category',
        'field'    => 'term_id',
        'terms'    => $primary_cat->term_id,
    ];
}
if ( $primary_chan ) {
    $related_tax[] = [
        'taxonomy' => 'channel',
        'field'    => 'term_id',
        'terms'    => $primary_chan->term_id,
    ];
}
if ( count( $related_tax ) > 1 ) {
    $related_tax['relation'] = 'AND';
}
if ( ! empty( $related_tax ) ) {
    $related_args['tax_query'] = $related_tax;
}
$related_query = new WP_Query( $related_args );

// 若同 cat+chan 不足 6 篇，僅以同 category 補
if ( $related_query->found_posts < 6 && $primary_cat ) {
    $related_query = new WP_Query( [
        'post_type'           => 'post',
        'posts_per_page'      => 6,
        'post__not_in'        => [ $post_id ],
        'post_status'         => 'publish',
        'ignore_sticky_posts' => true,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'tax_query'           => [[
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => $primary_cat->term_id,
        ]],
    ] );
}

// ── 熱門標籤（全站）──
$popular_tags = get_tags( [
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 15,
    'hide_empty' => true,
] );
?>

<!-- 額外 CSS / JS -->
<link rel="stylesheet" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/css/news.css' ); ?>" />
<link rel="stylesheet" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/css/single.css' ); ?>" />

<main class="container single-wrap" style="padding: 24px 0 64px;">

  <?php while ( have_posts() ) : the_post(); ?>

  <!-- ── 麵包屑 ── -->
  <nav class="breadcrumb" aria-label="breadcrumb">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">首頁</a>
    <span class="sep">›</span>
    <?php if ( $primary_cat ) : ?>
      <a href="<?php echo esc_url( get_term_link( $primary_cat ) ); ?>"><?php echo esc_html( $primary_cat->name ); ?></a>
      <span class="sep">›</span>
    <?php endif; ?>
    <?php if ( $primary_chan ) : ?>
      <a href="<?php echo esc_url( get_term_link( $primary_chan ) ); ?>"><?php echo esc_html( $primary_chan->name ); ?></a>
      <span class="sep">›</span>
    <?php endif; ?>
    <span class="current"><?php echo esc_html( wp_trim_words( get_the_title(), 16, '…' ) ); ?></span>
  </nav>

  <article id="post-<?php the_ID(); ?>" <?php post_class( 'single-article glass' ); ?>>

    <!-- ── 標題區 ── -->
    <header class="single-header">
      <div class="single-tags">
        <?php if ( $primary_cat ) : ?>
          <a class="single-tag cat" href="<?php echo esc_url( get_term_link( $primary_cat ) ); ?>">
            <?php echo esc_html( $primary_cat->name ); ?>
          </a>
        <?php endif; ?>
        <?php if ( $primary_chan ) : ?>
          <a class="single-tag chan" href="<?php echo esc_url( get_term_link( $primary_chan ) ); ?>">
            <?php echo esc_html( $primary_chan->name ); ?>
          </a>
        <?php endif; ?>
      </div>

      <h1 class="single-title"><?php the_title(); ?></h1>

      <div class="single-meta">
        <span><i class="fa-regular fa-user"></i> <?php the_author(); ?></span>
        <span><i class="fa-regular fa-clock"></i> <?php echo get_the_date( 'Y-m-d' ); ?></span>
        <span><i class="fa-regular fa-eye"></i> 約 <?php echo (int) $read_minutes; ?> 分鐘閱讀</span>
        <?php if ( comments_open() || get_comments_number() ) : ?>
        <span><i class="fa-regular fa-comment"></i> <?php comments_number( '0 留言', '1 留言', '% 留言' ); ?></span>
        <?php endif; ?>
      </div>
    </header>

    <!-- ── 主圖 ── -->
    <?php if ( has_post_thumbnail() ) : ?>
    <div class="single-cover">
      <?php the_post_thumbnail( 'large', [ 'alt' => get_the_title(), 'loading' => 'eager' ] ); ?>
    </div>
    <?php endif; ?>

    <!-- ── 內容 ── -->
    <div class="single-content">
      <?php the_content(); ?>
      <?php
        wp_link_pages( [
            'before' => '<div class="single-pagelinks">頁次：',
            'after'  => '</div>',
        ] );
      ?>
    </div>

    <!-- ── 文章標籤 ── -->
    <?php $post_tags = get_the_tags(); if ( $post_tags ) : ?>
    <div class="single-post-tags">
      <i class="fa-solid fa-tags"></i>
      <?php foreach ( $post_tags as $t ) : ?>
        <a href="<?php echo esc_url( get_tag_link( $t->term_id ) ); ?>" class="tag-pill">
          #<?php echo esc_html( $t->name ); ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── 上下篇 ── -->
    <nav class="single-nav">
      <div class="single-nav-prev">
        <?php previous_post_link( '%link', '<i class="fa-solid fa-chevron-left"></i> %title', true ); ?>
      </div>
      <div class="single-nav-next">
        <?php next_post_link( '%link', '%title <i class="fa-solid fa-chevron-right"></i>', true ); ?>
      </div>
    </nav>

  </article>

  <!-- ── 相關文章 ── -->
  <?php if ( $related_query->have_posts() ) : ?>
  <section class="related-section">
    <h2 class="section-title">
      <i class="fa-solid fa-layer-group"></i> 相關文章
    </h2>
    <div class="news-card-list related-grid">
      <?php while ( $related_query->have_posts() ) : $related_query->the_post();
        $rcats     = get_the_category();
        $rcat_lbl  = ! empty( $rcats ) ? esc_html( $rcats[0]->name ) : '文章';
      ?>
      <a href="<?php the_permalink(); ?>" class="news-card glass">
        <div class="news-card-img">
          <?php if ( has_post_thumbnail() ) : ?>
            <?php the_post_thumbnail( 'medium', [ 'alt' => get_the_title(), 'loading' => 'lazy' ] ); ?>
          <?php else :
            preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', get_the_content(), $rm );
            if ( ! empty( $rm[1] ) ) : ?>
              <img src="<?php echo esc_url( $rm[1] ); ?>"
                   alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" />
            <?php else : ?>
              <span class="news-card-placeholder">📰</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <div class="news-card-body">
          <div class="news-card-tag"><?php echo $rcat_lbl; ?></div>
          <div class="news-card-title"><?php the_title(); ?></div>
          <div class="news-card-meta">
            <i class="fa-regular fa-clock"></i> <?php echo get_the_date( 'Y-m-d' ); ?>
          </div>
        </div>
      </a>
      <?php endwhile; wp_reset_postdata(); ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── 留言 ── -->
  <?php if ( comments_open() || get_comments_number() ) : ?>
  <section class="single-comments glass">
    <?php comments_template(); ?>
  </section>
  <?php endif; ?>

  <?php endwhile; ?>

</main>

<!-- ── 側欄（行動版會排在內容下方，桌機可改 layout） ── -->
<aside class="news-sidebar single-sidebar container">

  <!-- 同分類熱門 -->
  <?php if ( $popular_query->have_posts() ) : ?>
  <div class="sidebar-widget glass">
    <div class="sidebar-widget-title">
      <i class="fa-solid fa-fire" style="color:#f97316;"></i>
      <?php echo $primary_cat ? esc_html( $primary_cat->name ) : ''; ?>熱門
    </div>
    <?php $pop_i = 0;
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
  </div>
  <?php endif; ?>

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

<?php get_footer();

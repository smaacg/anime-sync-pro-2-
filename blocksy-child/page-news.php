<?php
/**
 * Template Name: 動漫新聞
 * Template Post Type: page
 *
 * @package SmileACG
 */
get_header(); ?>

<style>
.page-hero--news { background:linear-gradient(135deg,rgba(239,68,68,0.12) 0%,rgba(249,115,22,0.08) 100%); border-bottom:1px solid var(--glass-border); padding:48px 0 36px; }
.page-badge { display:inline-flex;align-items:center;gap:6px;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);color:#f87171;border-radius:var(--radius-pill);padding:4px 14px;font-size:12px;font-weight:600;margin-bottom:16px; }
.news-page-title { font-size:32px;font-weight:800;color:var(--text-primary);margin-bottom:8px; }
.news-page-subtitle { font-size:14px;color:var(--text-muted); }
.news-filter { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px; }
.news-filter-btn { padding:7px 16px;border-radius:var(--radius-pill);font-size:13px;font-weight:500;background:var(--glass-bg);border:1px solid var(--glass-border);color:var(--text-secondary);cursor:pointer;transition:var(--trans-fast); }
.news-filter-btn:hover { color:var(--text-primary);background:var(--glass-bg-mid); }
.news-filter-btn.active { background:rgba(239,68,68,0.2);border-color:rgba(239,68,68,0.4);color:#f87171; }
.news-page-layout { display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start;padding:32px 0 64px; }
.news-main-grid { display:grid;gap:20px; }
.news-featured { border-radius:20px;overflow:hidden;background:var(--glass-bg);border:1px solid var(--glass-border);cursor:pointer;transition:var(--trans-smooth);display:grid;grid-template-columns:1fr 1fr; }
.news-featured:hover { transform:translateY(-3px);box-shadow:0 12px 40px rgba(0,0,0,0.35); }
.news-featured-img { aspect-ratio:16/10;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:64px;background:linear-gradient(135deg,#1e3a5f,#2d1b69); }
.news-featured-img img { width:100%;height:100%;object-fit:cover; }
.news-featured-body { padding:28px 24px;display:flex;flex-direction:column;justify-content:center; }
.news-featured-tag { display:inline-block;background:rgba(239,68,68,0.2);color:#f87171;border-radius:var(--radius-pill);padding:3px 10px;font-size:11px;font-weight:700;margin-bottom:12px; }
.news-featured-title { font-size:18px;font-weight:700;color:var(--text-primary);line-height:1.5;margin-bottom:10px; }
.news-featured-meta { font-size:12px;color:var(--text-muted); }
.news-card-list { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
.news-card { border-radius:16px;overflow:hidden;background:var(--glass-bg);border:1px solid var(--glass-border);cursor:pointer;transition:var(--trans-smooth);text-decoration:none;display:block; }
.news-card:hover { transform:translateY(-3px);box-shadow:0 8px 28px rgba(0,0,0,0.3); }
.news-card-img { aspect-ratio:16/9;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:36px;background:linear-gradient(135deg,#1e2d3d,#2a1f3d); }
.news-card-img img { width:100%;height:100%;object-fit:cover; }
.news-card-body { padding:14px 16px; }
.news-card-tag { font-size:10px;font-weight:700;color:#f87171;text-transform:uppercase;margin-bottom:6px; }
.news-card-title { font-size:13px;font-weight:600;color:var(--text-primary);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:8px; }
.news-card-meta { font-size:11px;color:var(--text-muted); }
.news-sidebar { display:flex;flex-direction:column;gap:20px; }
.sidebar-widget { border-radius:16px;background:var(--glass-bg);border:1px solid var(--glass-border);padding:20px; }
.sidebar-widget-title { font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:14px;display:flex;align-items:center;gap:8px; }
.sidebar-list-item { display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--glass-border); }
.sidebar-list-item:last-child { border-bottom:none;padding-bottom:0; }
.sidebar-item-num { width:24px;height:24px;border-radius:50%;background:rgba(59,130,246,0.15);color:var(--accent-blue);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.sidebar-item-num.top-3 { background:rgba(251,191,36,0.15);color:#fbbf24; }
.sidebar-item-title { font-size:12px;font-weight:500;color:var(--text-secondary);line-height:1.5;flex:1; }
.sidebar-item-date { font-size:10px;color:var(--text-muted);margin-top:2px; }
.tag-cloud { display:flex;flex-wrap:wrap;gap:8px; }
.tag-pill { padding:5px 12px;border-radius:var(--radius-pill);background:var(--glass-bg-mid);border:1px solid var(--glass-border);font-size:12px;color:var(--text-secondary);cursor:pointer;transition:var(--trans-fast);text-decoration:none; }
.tag-pill:hover { color:var(--accent-blue);border-color:rgba(59,130,246,0.4); }
@media(max-width:1024px){ .news-page-layout{grid-template-columns:1fr;} }
@media(max-width:768px){ .news-featured{grid-template-columns:1fr;} .news-card-list{grid-template-columns:1fr;} .news-page-title{font-size:24px;} }
</style>

<!-- HERO -->
<div class="page-hero--news">
  <div class="container">
    <div class="page-badge"><i class="fa-solid fa-newspaper"></i> 動漫新聞</div>
    <h1 class="news-page-title">最新動漫資訊</h1>
    <p class="news-page-subtitle">聲優消息・新番公告・活動報導・業界動態，每日更新</p>
  </div>
</div>

<div class="container">
<div class="news-page-layout">

  <!-- 主要新聞區 -->
  <div class="news-main-grid">

    <!-- Filter Tabs -->
    <div class="news-filter">
      <button class="news-filter-btn active" data-cat="">全部</button>
      <?php
      $cats = get_categories(['hide_empty' => true]);
      foreach ($cats as $cat) :
      ?>
      <button class="news-filter-btn" data-cat="<?php echo esc_attr($cat->slug); ?>">
        <?php echo esc_html($cat->name); ?>
      </button>
      <?php endforeach; ?>
    </div>

    <?php
    // 頭條：最新一篇
    $featured = new WP_Query([
      'posts_per_page' => 1,
      'post_status'    => 'publish',
      'post_type'      => 'post',
    ]);
    if ($featured->have_posts()) : $featured->the_post();
      $fid    = get_the_ID();
      $thumb  = smaacg_get_news_thumb($fid);
      $cats_f = get_the_category($fid);
      $cat_n  = $cats_f ? $cats_f[0]->name : '頭條';
    ?>
    <a href="<?php the_permalink(); ?>" class="news-featured glass" style="text-decoration:none;color:inherit;">
      <div class="news-featured-img">
        <?php if ($thumb) : ?>
          <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
        <?php else : ?>📰<?php endif; ?>
      </div>
      <div class="news-featured-body">
        <span class="news-featured-tag"><?php echo esc_html($cat_n); ?></span>
        <h2 class="news-featured-title"><?php the_title(); ?></h2>
        <div class="news-featured-meta">
          <i class="fa-regular fa-clock"></i> <?php echo get_the_date('Y-m-d'); ?>
          &nbsp;·&nbsp;
          <i class="fa-regular fa-eye"></i> <?php echo number_format(intval(get_post_meta($fid, 'post_views_count', true))); ?> 次瀏覽
        </div>
      </div>
    </a>
    <?php wp_reset_postdata(); endif; ?>

    <!-- 新聞卡片 -->
    <?php
    $news = new WP_Query([
      'posts_per_page' => 6,
      'offset'         => 1,
      'post_status'    => 'publish',
      'post_type'      => 'post',
    ]);
    ?>
    <div class="news-card-list" id="news-card-list">
    <?php if ($news->have_posts()) : while ($news->have_posts()) : $news->the_post();
      $nid   = get_the_ID();
      $nthumb = smaacg_get_news_thumb($nid);
      $ncats = get_the_category($nid);
      $ncat  = $ncats ? $ncats[0]->name : 'NEWS';
    ?>
      <a href="<?php the_permalink(); ?>" class="news-card glass">
        <div class="news-card-img">
          <?php if ($nthumb) : ?>
            <img src="<?php echo esc_url($nthumb); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
          <?php else : ?>📰<?php endif; ?>
        </div>
        <div class="news-card-body">
          <div class="news-card-tag"><?php echo esc_html($ncat); ?></div>
          <div class="news-card-title"><?php the_title(); ?></div>
          <div class="news-card-meta"><i class="fa-regular fa-clock"></i> <?php echo get_the_date('Y-m-d'); ?></div>
        </div>
      </a>
    <?php endwhile; wp_reset_postdata(); endif; ?>
    </div>

    <!-- 載入更多 -->
    <div style="text-align:center;padding:12px 0;">
      <a href="<?php echo esc_url(home_url('/news/')); ?>" class="btn btn-ghost" style="padding:12px 32px;border-radius:var(--radius-pill);font-size:14px;">
        <i class="fa-solid fa-rotate"></i> 查看更多新聞
      </a>
    </div>

  </div><!-- /.news-main-grid -->

  <!-- 側欄 -->
  <aside class="news-sidebar">

    <!-- 熱門新聞 -->
    <div class="sidebar-widget glass">
      <div class="sidebar-widget-title"><i class="fa-solid fa-fire" style="color:#f97316;"></i> 熱門新聞</div>
      <?php
      $popular = new WP_Query([
        'posts_per_page' => 5,
        'post_status'    => 'publish',
        'post_type'      => 'post',
        'meta_key'       => 'post_views_count',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
      ]);
      $rank = 1;
      if ($popular->have_posts()) : while ($popular->have_posts()) : $popular->the_post(); ?>
      <div class="sidebar-list-item">
        <div class="sidebar-item-num <?php echo $rank <= 3 ? 'top-3' : ''; ?>"><?php echo $rank; ?></div>
        <div>
          <a href="<?php the_permalink(); ?>" style="text-decoration:none;">
            <div class="sidebar-item-title"><?php the_title(); ?></div>
          </a>
          <div class="sidebar-item-date"><?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . '前'; ?></div>
        </div>
      </div>
      <?php $rank++; endwhile; wp_reset_postdata(); endif; ?>
    </div>

    <!-- 熱門標籤 -->
    <div class="sidebar-widget glass">
      <div class="sidebar-widget-title"><i class="fa-solid fa-tags" style="color:var(--accent-blue);"></i> 熱門標籤</div>
      <div class="tag-cloud">
        <?php
        $tags = get_tags(['orderby' => 'count', 'order' => 'DESC', 'number' => 12]);
        foreach ($tags as $tag) :
        ?>
        <a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>" class="tag-pill">
          #<?php echo esc_html($tag->name); ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 訂閱 -->
    <div class="sidebar-widget glass" style="background:linear-gradient(135deg,rgba(59,130,246,0.1),rgba(139,92,246,0.1));">
      <div class="sidebar-widget-title"><i class="fa-solid fa-bell" style="color:var(--accent-blue);"></i> 訂閱新聞快報</div>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;line-height:1.6;">每週精選重要動漫資訊，直送你的信箱</p>
      <?php echo do_shortcode('[mc4wp_form]'); ?>
    </div>

  </aside>

</div><!-- /.news-page-layout -->
</div><!-- /.container -->

<script>
(function(){
  document.querySelectorAll('.news-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.news-filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });
})();
</script>

<?php get_footer(); ?>

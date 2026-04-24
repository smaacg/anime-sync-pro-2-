<?php
/**
 * Template Name: 本季新番
 * Template Post Type: page
 */
get_header();

/* ── 季度計算 ── */
$now_month = (int) date('n');
$now_year  = (int) date('Y');
$now_day   = (int) date('N'); // 1=週一…7=週日

if ( $now_month <= 3 )       { $cur_season = 'WINTER'; }
elseif ( $now_month <= 6 )   { $cur_season = 'SPRING'; }
elseif ( $now_month <= 9 )   { $cur_season = 'SUMMER'; }
else                          { $cur_season = 'FALL';   }

$season_zh = [ 'WINTER' => '冬', 'SPRING' => '春', 'SUMMER' => '夏', 'FALL' => '秋' ];
$season_label = $now_year . ' 年 ' . $season_zh[ $cur_season ] . '季新番';

/* ── 抓取本季所有動漫 ── */
$season_query = new WP_Query([
    'post_type'      => 'anime',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'no_found_rows'  => true,
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => 'anime_season',      'value' => $cur_season, 'compare' => '=' ],
        [ 'key' => 'anime_season_year', 'value' => $now_year,   'compare' => '=', 'type' => 'NUMERIC' ],
    ],
]);

/* ── 把文章整理成星期分組 ── */
$weekday_zh  = [ 0 => '全部', 1 => '週一', 2 => '週二', 3 => '週三', 4 => '週四', 5 => '週五', 6 => '週六', 7 => '週日' ];
$by_weekday  = [ 0 => [] ]; // 0 = 全部
for ( $i = 1; $i <= 7; $i++ ) $by_weekday[$i] = [];

$all_posts = [];

if ( $season_query->have_posts() ) :
    while ( $season_query->have_posts() ) : $season_query->the_post();
        $pid = get_the_ID();

        $title    = get_post_meta( $pid, 'anime_title_chinese', true ) ?: get_the_title();
        $title_jp = get_post_meta( $pid, 'anime_title_japanese', true ) ?: '';
        $cover    = get_post_meta( $pid, 'anime_cover_image', true )
                    ?: get_the_post_thumbnail_url( $pid, 'medium' );
        $score_raw = (float) get_post_meta( $pid, 'anime_score_anilist', true );
        $score    = $score_raw > 0 ? number_format( $score_raw / 10, 1 ) : '';
        $status   = get_post_meta( $pid, 'anime_status', true );
        $ep_total = (int) get_post_meta( $pid, 'anime_episodes', true );
        $ep_aired = (int) get_post_meta( $pid, 'anime_episodes_aired', true );
        $url      = get_permalink( $pid );

        /* 星期幾 */
        $weekday = 0;
        $next_airing = get_post_meta( $pid, 'anime_next_airing', true );
        if ( $next_airing ) {
            $ts = strtotime( $next_airing );
            if ( $ts ) $weekday = (int) date('N', $ts);
        }
        if ( ! $weekday ) {
            $start = get_post_meta( $pid, 'anime_start_date', true );
            if ( $start ) {
                $ts = strtotime( $start );
                if ( $ts ) $weekday = (int) date('N', $ts);
            }
        }

        $post_data = compact('pid','title','title_jp','cover','score','status','ep_total','ep_aired','url','weekday');
        $all_posts[] = $post_data;
        $by_weekday[0][] = $post_data;
        if ( $weekday >= 1 && $weekday <= 7 ) $by_weekday[$weekday][] = $post_data;

    endwhile;
    wp_reset_postdata();
endif;

$total = count( $all_posts );
?>

<style>
.season-page-hero {
  background: linear-gradient(135deg, rgba(59,130,246,0.15) 0%, rgba(139,92,246,0.1) 100%);
  border-bottom: 1px solid var(--glass-border);
  padding: 48px 0 36px;
}
.season-page-title { font-size: 32px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px; }
.season-page-subtitle { font-size: 14px; color: var(--text-muted); }
.season-badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.3);
  color: var(--accent-blue); border-radius: var(--radius-pill);
  padding: 4px 14px; font-size: 12px; font-weight: 600; margin-bottom: 16px;
}
.weekday-bar { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 32px; }
.weekday-btn {
  padding: 8px 18px; border-radius: var(--radius-pill); font-size: 13px; font-weight: 600;
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  color: var(--text-secondary); cursor: pointer; transition: var(--trans-fast); white-space: nowrap;
}
.weekday-btn:hover { color: var(--text-primary); background: var(--glass-bg-mid); }
.weekday-btn.active { background: var(--accent-blue); border-color: var(--accent-blue); color: #fff; }
.weekday-btn .day-count { font-size: 11px; opacity: 0.75; margin-left: 4px; }
.season-full-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 20px; padding: 4px 0 40px;
}
.sf-card {
  border-radius: 16px; overflow: hidden; cursor: pointer;
  transition: var(--trans-smooth);
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  position: relative; text-decoration: none; display: block;
}
.sf-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.4); border-color: rgba(59,130,246,0.4); }
.sf-card-img { width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block; }
.sf-card-img-ph { width: 100%; aspect-ratio: 2/3; display: flex; align-items: center; justify-content: center; font-size: 40px; background: var(--glass-bg-mid); }
.sf-card-body { padding: 10px 12px 12px; }
.sf-card-title { font-size: 13px; font-weight: 600; color: var(--text-primary); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.sf-card-jp { font-size: 11px; color: var(--text-muted); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sf-card-meta { display: flex; align-items: center; gap: 6px; margin-top: 6px; }
.sf-card-score { font-size: 11px; color: #FFD580; font-weight: 600; }
.sf-card-ep { font-size: 11px; color: var(--text-muted); }
.sf-card-day { position: absolute; top: 8px; left: 8px; background: rgba(0,0,0,0.7); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-pill); backdrop-filter: blur(4px); }
.sf-card-airing { position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 6px #22c55e; animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.5;transform:scale(1.4);} }
.season-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.season-count { font-size: 13px; color: var(--text-muted); }
.season-count span { color: var(--text-primary); font-weight: 700; }
.sort-select { background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-secondary); border-radius: var(--radius-pill); padding: 6px 14px; font-size: 13px; cursor: pointer; outline: none; }
.sort-select:focus { border-color: var(--accent-blue); }
.sf-group { display: contents; }
.sf-group[hidden] { display: none; }
@media (max-width: 768px) { .season-full-grid { grid-template-columns: repeat(auto-fill, minmax(130px,1fr)); gap: 12px; } .season-page-title { font-size: 24px; } }
@media (max-width: 480px) { .season-full-grid { grid-template-columns: repeat(3,1fr); gap: 10px; } }
</style>

<!-- PAGE HERO -->
<div class="season-page-hero">
  <div class="container">
    <div class="season-badge"><i class="fa-solid fa-calendar-week"></i> 本季新番</div>
    <h1 class="season-page-title"><?php echo esc_html( $season_label ); ?></h1>
    <p class="season-page-subtitle">依星期瀏覽當季所有播出作品，資料來源：站內資料庫</p>
  </div>
</div>

<!-- MAIN -->
<main class="container" style="padding-top:32px;">

  <!-- 星期 Tab -->
  <div class="weekday-bar" id="weekday-bar">
    <?php foreach ( $weekday_zh as $d => $label ) :
      $cnt = count( $by_weekday[$d] );
      if ( $d > 0 && $cnt === 0 ) continue; // 沒有作品的星期不顯示
    ?>
    <button class="weekday-btn<?php echo $d === $now_day ? ' active' : ( $d === 0 && $now_day === 0 ? ' active' : '' ); ?>"
            data-day="<?php echo $d; ?>">
      <?php echo esc_html( $label ); ?>
      <?php if ( $cnt > 0 ) : ?><span class="day-count"><?php echo $cnt; ?></span><?php endif; ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- 工具列 -->
  <div class="season-toolbar">
    <div class="season-count">共 <span id="season-count"><?php echo $total; ?></span> 部作品</div>
    <select class="sort-select" id="sort-select">
      <option value="default">預設排序</option>
      <option value="score">依評分排序</option>
      <option value="ep">依集數排序</option>
    </select>
  </div>

  <!-- 作品 Grid -->
  <div class="season-full-grid" id="season-grid">
    <?php if ( empty( $all_posts ) ) : ?>
      <div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:60px 0;">
        <i class="fa-solid fa-calendar-xmark" style="font-size:32px;display:block;margin-bottom:12px;"></i>
        本季暫無資料，請稍後回來查看。
      </div>
    <?php else : ?>

      <?php foreach ( $by_weekday as $day_id => $posts ) : ?>
      <div class="sf-group"
           data-group="<?php echo esc_attr( $day_id ); ?>"
           <?php echo ( $day_id !== $now_day && !( $day_id === 0 && $now_day === 0 ) ) ? 'hidden' : ''; ?>>
        <?php foreach ( $posts as $p ) :
          $ep_label = '';
          if ( $p['ep_total'] > 0 ) {
            $ep_label = $p['ep_aired'] > 0 && $p['ep_aired'] < $p['ep_total']
              ? $p['ep_aired'] . '/' . $p['ep_total'] . ' 集'
              : $p['ep_total'] . ' 集';
          } elseif ( $p['ep_aired'] > 0 ) {
            $ep_label = '第 ' . $p['ep_aired'] . ' 集';
          }
          $is_airing = ( $p['status'] === 'RELEASING' );
          $day_zh    = $p['weekday'] >= 1 ? $weekday_zh[ $p['weekday'] ] : '';
        ?>
        <a class="sf-card" href="<?php echo esc_url( $p['url'] ); ?>" title="<?php echo esc_attr( $p['title'] ); ?>">
          <?php if ( $day_id === 0 && $day_zh ) : ?>
            <span class="sf-card-day"><?php echo esc_html( $day_zh ); ?></span>
          <?php endif; ?>
          <?php if ( $is_airing ) : ?>
            <span class="sf-card-airing"></span>
          <?php endif; ?>
          <?php if ( $p['cover'] ) : ?>
            <img class="sf-card-img"
                 src="<?php echo esc_url( $p['cover'] ); ?>"
                 alt="<?php echo esc_attr( $p['title'] ); ?>"
                 loading="lazy"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="sf-card-img-ph" style="display:none;">🎬</div>
          <?php else : ?>
            <div class="sf-card-img-ph">🎬</div>
          <?php endif; ?>
          <div class="sf-card-body">
            <div class="sf-card-title"><?php echo esc_html( $p['title'] ); ?></div>
            <?php if ( $p['title_jp'] && $p['title_jp'] !== $p['title'] ) : ?>
              <div class="sf-card-jp"><?php echo esc_html( $p['title_jp'] ); ?></div>
            <?php endif; ?>
            <div class="sf-card-meta">
              <?php if ( $p['score'] ) : ?>
                <span class="sf-card-score">★ <?php echo esc_html( $p['score'] ); ?></span>
              <?php endif; ?>
              <?php if ( $ep_label ) : ?>
                <span class="sf-card-ep"><?php echo esc_html( $ep_label ); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div><!-- .sf-group -->
      <?php endforeach; ?>

    <?php endif; ?>
  </div><!-- #season-grid -->

</main>

<script>
(function () {
    /* ── 星期切換 ── */
    const bar    = document.getElementById('weekday-bar');
    const groups = document.querySelectorAll('.sf-group');
    const countEl = document.getElementById('season-count');

    bar.addEventListener('click', function (e) {
        const btn = e.target.closest('.weekday-btn');
        if (!btn) return;
        const day = parseInt(btn.dataset.day);

        document.querySelectorAll('.weekday-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        let visible = 0;
        groups.forEach(function (g) {
            const match = parseInt(g.dataset.group) === day;
            g.hidden = !match;
            if (match) visible += g.querySelectorAll('.sf-card').length;
        });
        if (countEl) countEl.textContent = visible;
    });

    /* ── 排序 ── */
    document.getElementById('sort-select').addEventListener('change', function () {
        const mode = this.value;
        groups.forEach(function (group) {
            const cards = Array.from(group.querySelectorAll('.sf-card'));
            cards.sort(function (a, b) {
                if (mode === 'score') {
                    const sa = parseFloat(a.querySelector('.sf-card-score')?.textContent) || 0;
                    const sb = parseFloat(b.querySelector('.sf-card-score')?.textContent) || 0;
                    return sb - sa;
                }
                if (mode === 'ep') {
                    const ea = parseInt(a.querySelector('.sf-card-ep')?.textContent) || 0;
                    const eb = parseInt(b.querySelector('.sf-card-ep')?.textContent) || 0;
                    return eb - ea;
                }
                return 0;
            });
            cards.forEach(c => group.appendChild(c));
        });
    });

    /* ── 預設顯示今天 ── */
    const jsDay  = new Date().getDay();
    const today  = jsDay === 0 ? 7 : jsDay;
    const todayBtn = document.querySelector(`.weekday-btn[data-day="${today}"]`);
    if (todayBtn && !todayBtn.classList.contains('active')) {
        todayBtn.click();
    } else if (!todayBtn) {
        const allBtn = document.querySelector('.weekday-btn[data-day="0"]');
        if (allBtn) allBtn.click();
    }
})();
</script>

<?php get_footer(); ?>

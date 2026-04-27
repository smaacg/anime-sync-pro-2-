<?php
/**
 * 微笑動漫 Child Theme — footer.php
 */

/* ── 社群連結 ── */
$social = [
    'discord'        => [ 'https://discord.com/invite/yw73RBZgss',               'fa-brands fa-discord',   'Discord'   ],
    'facebook_group' => [ 'https://www.facebook.com/groups/148714851855091/',     'fa-brands fa-facebook',  'FB 社團'   ],
    'facebook_page'  => [ 'https://www.facebook.com/smaacg/?locale=zh_CN',       'fa-brands fa-facebook',  'FB 粉專'   ],
    'plurk'          => [ 'https://www.plurk.com/SMAACG',                        'fa-solid fa-p',          'Plurk'     ],
    'vocus'          => [ 'https://vocus.cc/user/69c92cdcfd89780001abdd8e',       'fa-solid fa-pen-nib',    'Vocus'     ],
    'tiktok'         => [ '',                                                      'fa-brands fa-tiktok',    '抖音'      ],
    'youtube'        => [ '',                                                      'fa-brands fa-youtube',   'YouTube'   ],
    'twitter'        => [ '',                                                      'fa-brands fa-x-twitter', 'X'         ],
    'instagram'      => [ '',                                                      'fa-brands fa-instagram', 'Instagram' ],
];

$site_name = get_bloginfo('name') ?: '微笑動漫';
?>

<footer class="site-footer glass-mid" role="contentinfo">
  <div class="container">
    <div class="footer-top">

      <!-- ── Brand ── -->
      <div class="footer-brand">
        <a href="<?php echo esc_url( home_url('/') ); ?>"
           class="site-logo footer-logo"
           aria-label="<?php echo esc_attr( $site_name ); ?> 首頁">
          <span class="logo-icon-box" aria-hidden="true">微</span>
          <span class="logo-text">
            <?php echo esc_html( $site_name ); ?><span class="logo-plus" aria-hidden="true">+</span>
          </span>
        </a>

        <p class="footer-tagline">
          <?php echo esc_html( get_bloginfo('description') ?: '高質感 ACG 情報中心，動漫・音樂・COSPLAY・百科，讓你不錯過任何精彩。' ); ?>
        </p>

        <!-- 社群圖示 -->
        <nav class="social-links" aria-label="社群媒體連結">
          <?php foreach ( $social as $key => [ $url, $icon, $label ] ) : ?>
            <?php if ( $url !== '' ) : ?>
              <a href="<?php echo esc_url( $url ); ?>"
                 class="footer-social-btn"
                 aria-label="<?php echo esc_attr( $label ); ?>"
                 target="_blank" rel="noopener noreferrer">
                <i class="<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
              </a>
            <?php else : ?>
              <span class="footer-social-btn footer-social-placeholder"
                    title="<?php echo esc_attr( $label ); ?> 即將上線"
                    aria-label="<?php echo esc_attr( $label ); ?>（即將上線）">
                <i class="<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
              </span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      </div>

      <!-- ── 站內欄目 ── -->
      <nav class="footer-nav-group" aria-label="站內欄目">
        <h5>站內欄目</h5>
        <?php
        $col1 = [
            '首頁'      => home_url('/'),
            '新番導覽'  => home_url('/season/'),
            '最新新聞'  => home_url('/news/'),
            '動漫音樂'  => home_url('/music/'),
            'COSPLAYER' => home_url('/cosplay/'),
        ];
        foreach ( $col1 as $label => $url ) : ?>
          <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
      </nav>

      <!-- ── 資料庫 ── -->
      <nav class="footer-nav-group" aria-label="資料庫">
        <h5>資料庫</h5>
        <?php
        $col2 = [
            '動畫百科' => home_url('/anime/'),
            '角色資料' => home_url('/character/'),
            '聲優資料' => home_url('/voice-actor/'),
            '製作公司' => home_url('/studio/'),
            '改編遊戲' => home_url('/games/'),
        ];
        foreach ( $col2 as $label => $url ) : ?>
          <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
      </nav>

      <!-- ── 社群 ── -->
      <nav class="footer-nav-group" aria-label="社群">
        <h5>社群</h5>
        <?php
        $col3 = [
            '討論區'       => home_url('/forum/'),
            '季番投票'     => home_url('/vote/'),
            'COSPLAY 展示' => home_url('/cosplay/'),
            '投稿須知'     => home_url('/submit/'),
        ];
        foreach ( $col3 as $label => $url ) : ?>
          <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
      </nav>

      <!-- ── 關於 ── -->
      <nav class="footer-nav-group" aria-label="關於">
        <h5>關於</h5>
        <?php
        $col4 = [
            '關於微笑動漫' => home_url('/about/'),
            '聯絡／合作'   => home_url('/contact/'),
            '免責聲明'     => home_url('/disclaimer/'),
            '資料來源'     => home_url('/sources/'),
            '隱私政策'     => home_url('/privacy/'),
        ];
        foreach ( $col4 as $label => $url ) : ?>
          <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
        <?php endforeach; ?>
      </nav>

    </div><!-- .footer-top -->

    <div class="footer-divider" role="separator"></div>

    <!-- ── Footer Bottom ── -->
    <div class="footer-bottom">
      <p class="footer-copy">
        © <?php echo esc_html( date('Y') ); ?> 微笑動漫 WeixiaoACG．All rights reserved．
        資料來源包含
        <a href="https://bgm.tv" target="_blank" rel="noopener noreferrer">Bangumi</a>、
        <a href="https://anilist.co" target="_blank" rel="noopener noreferrer">AniList</a>、
        <a href="https://myanimelist.net" target="_blank" rel="noopener noreferrer">MyAnimeList</a>、
        <a href="https://jikan.moe" target="_blank" rel="noopener noreferrer">Jikan</a>、
        <a href="https://www.wikipedia.org" target="_blank" rel="noopener noreferrer">Wikipedia</a>，
        OP／ED 來源
        <a href="https://animethemes.moe" target="_blank" rel="noopener noreferrer">AnimeThemes.moe</a>。
      </p>

      <nav class="footer-bottom-links" aria-label="法律資訊">
        <a href="<?php echo esc_url( home_url('/terms/') ); ?>">使用條款</a>
        <a href="<?php echo esc_url( home_url('/privacy/') ); ?>">隱私政策</a>
        <a href="<?php echo esc_url( home_url('/disclaimer/') ); ?>">免責聲明</a>
        <a href="<?php echo esc_url( home_url('/about/') ); ?>">關於我們</a>
      </nav>
    </div>

  </div>
</footer>

<!-- Toast 通知容器 -->
<div class="toast-container" id="toast-container" aria-live="polite" aria-atomic="false"></div>

<?php wp_footer(); ?>
</body>
</html>

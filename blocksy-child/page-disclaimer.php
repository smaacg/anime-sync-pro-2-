<?php
/**
 * Template Name: 免責聲明
 */
get_header(); ?>

<main class="static-page">

  <!-- Hero -->
  <section class="static-hero">
    <div class="static-hero-inner">
      <div class="static-hero-icon">⚠️</div>
      <h1 class="static-hero-title">免責聲明</h1>
      <p class="static-hero-sub">最後更新日期：2026 年 4 月 25 日</p>
    </div>
  </section>

  <div class="static-content container">

    <!-- 前言 -->
    <section class="static-section glass-light">
      <p class="static-lead">
        本頁面說明微笑動漫（以下簡稱「本站」）對於網站內容、版權、第三方連結及相關服務的免責事項。使用本站即表示您已閱讀並同意以下聲明。
      </p>
    </section>

    <!-- 一、版權聲明 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">01</span>
        <h2 class="static-section-title">版權聲明</h2>
      </div>
      <div class="glass-light static-card">
        <p>本站為動漫資訊分享平台，網站內所呈現之動漫作品名稱、封面圖片、劇照、角色圖像、OP/ED 影片等一切內容，其著作權均屬各原作者、出版社、動畫製作公司及相關版權持有人所有。</p>
        <p>本站使用上述內容之目的僅為資訊介紹、評論及教育用途，並非用於任何商業盈利行為。本站對所有版權內容不主張任何所有權。</p>
        <div class="static-notice">
          <i class="fa-solid fa-circle-info"></i>
          若您為版權持有人，認為本站任何內容侵犯您的著作權，請立即透過 <a href="mailto:weixiaoacg.com@gmail.com" class="static-link" style="margin:0;display:inline;">weixiaoacg.com@gmail.com</a> 聯絡我們，我們將於 72 小時內處理。
        </div>
      </div>
    </section>

    <!-- 二、合理使用聲明 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">02</span>
        <h2 class="static-section-title">合理使用聲明</h2>
      </div>
      <div class="glass-light static-card">
        <p>本站引用之圖片及影音資料，係依據著作權法之合理使用原則（Fair Use）進行使用，具體理由如下：</p>
        <ul class="static-list" style="margin-top:14px;">
          <li>使用目的為<strong>非營利性資訊介紹及評論</strong>，不以商業獲利為目的。</li>
          <li>所引用之內容均為<strong>作品資訊之最小必要範圍</strong>，不以取代原作為目的。</li>
          <li>所有圖片資源主要來源於 <strong>AniList</strong>、<strong>Bangumi</strong> 等公開授權之第三方資料庫，透過官方 API 取得。</li>
          <li>本站引用行為<strong>不對原作品之市場價值造成實質損害</strong>。</li>
        </ul>
      </div>
    </section>

    <!-- 三、第三方資料來源 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">03</span>
        <h2 class="static-section-title">第三方資料來源</h2>
      </div>
      <div class="static-grid-2">
        <?php
        $sources = [
          [
            'icon'  => 'fa-solid fa-database',
            'name'  => 'AniList',
            'url'   => 'https://anilist.co',
            'desc'  => '本站動漫資料、封面圖片、評分及相關資訊之主要來源，透過 AniList 官方公開 GraphQL API 取得。',
          ],
          [
            'icon'  => 'fa-solid fa-tv',
            'name'  => 'Bangumi',
            'url'   => 'https://bgm.tv',
            'desc'  => '中文動漫資料、劇情簡介、集數列表及關聯作品資訊來源，透過 Bangumi 官方公開 REST API 取得。',
          ],
          [
            'icon'  => 'fa-solid fa-list',
            'name'  => 'MyAnimeList（Jikan）',
            'url'   => 'https://jikan.moe',
            'desc'  => '角色聲優、MAL 評分及串流平台資訊來源，透過 Jikan 非官方公開 API 取得。',
          ],
          [
            'icon'  => 'fa-solid fa-music',
            'name'  => 'AnimeThemes.moe',
            'url'   => 'https://animethemes.moe',
            'desc'  => '動畫 OP/ED 主題曲試聽影片來源，透過 AnimeThemes 官方公開 API 取得。',
          ],
        ];
        foreach ( $sources as $s ) : ?>
        <div class="glass-light static-card">
          <div class="static-card-head">
            <i class="<?php echo esc_attr( $s['icon'] ); ?> static-card-fa"></i>
            <h4 class="static-card-title"><?php echo esc_html( $s['name'] ); ?></h4>
          </div>
          <p><?php echo esc_html( $s['desc'] ); ?></p>
          <a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener" class="static-link">
            前往官網 <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="glass-light static-card" style="margin-top:16px;">
        <p>以上所有資料均透過各平台官方或公開 API 取得，本站不對上述第三方平台之資料準確性、完整性或即時性作出保證。所有資料之版權歸各原始資料庫及內容創作者所有。</p>
      </div>
    </section>

    <!-- 四、內容準確性 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">04</span>
        <h2 class="static-section-title">內容準確性</h2>
      </div>
      <div class="static-grid-2">
        <?php
        $accuracy = [
          [ 'icon' => '📡', 'title' => '資料同步延遲',   'desc' => '本站資料透過第三方 API 定期同步，可能與原始資料庫存在時間差，不保證即時準確。' ],
          [ 'icon' => '🌐', 'title' => '繁體中文轉換',   'desc' => '部分中文資料由簡體中文自動轉換為繁體中文，可能存在用詞差異，僅供參考。' ],
          [ 'icon' => '⭐', 'title' => '評分資料',       'desc' => '本站顯示之 AniList、MAL、Bangumi 評分資料均來自各平台，僅為參考用途。' ],
          [ 'icon' => '📅', 'title' => '播出時程',       'desc' => '動畫播出日期及集數資訊可能因製作方調整而有所變動，以各官方公告為準。' ],
        ];
        foreach ( $accuracy as $a ) : ?>
        <div class="glass-light static-item-card">
          <span class="static-item-icon"><?php echo $a['icon']; ?></span>
          <div>
            <strong><?php echo esc_html( $a['title'] ); ?></strong>
            <p><?php echo esc_html( $a['desc'] ); ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 五、不提供非法服務聲明 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">05</span>
        <h2 class="static-section-title">不提供非法服務聲明</h2>
      </div>
      <div class="static-grid-3">
        <?php
        $no_illegal = [
          [ 'icon' => '🚫', 'title' => '不提供非法串流',   'desc' => '本站不提供任何版權動畫影片的非法串流播放服務。' ],
          [ 'icon' => '⬇️', 'title' => '不提供非法下載',   'desc' => '本站不提供任何版權影片、漫畫的非法下載連結。' ],
          [ 'icon' => '💰', 'title' => '非商業用途',       'desc' => '本站為非營利個人網站，不以版權內容進行任何形式的商業獲利。' ],
        ];
        foreach ( $no_illegal as $n ) : ?>
        <div class="glass-light static-card static-card--center">
          <div class="static-item-icon"><?php echo $n['icon']; ?></div>
          <h4 class="static-card-title"><?php echo esc_html( $n['title'] ); ?></h4>
          <p><?php echo esc_html( $n['desc'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 六、第三方連結 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">06</span>
        <h2 class="static-section-title">第三方連結</h2>
      </div>
      <div class="glass-light static-card">
        <p>本站可能包含指向第三方網站的連結（包括但不限於 AniList、Bangumi、MyAnimeList、YouTube、各動畫官網等）。這些連結僅為方便用戶參考之用，本站對於第三方網站之內容、隱私政策或服務不負任何責任。</p>
        <p>瀏覽第三方網站時，請自行閱讀並遵守其相關條款與政策。</p>
      </div>
    </section>

    <!-- 七、責任限制 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">07</span>
        <h2 class="static-section-title">責任限制</h2>
      </div>
      <div class="glass-light static-card">
        <ul class="static-list">
          <li>本站對於因使用或無法使用本站服務所造成的任何直接、間接、附帶或後續損害，不負任何賠償責任。</li>
          <li>本站對於因第三方 API 服務中斷、資料錯誤或延遲所造成的任何損失，不負任何責任。</li>
          <li>本站對於用戶之間的任何糾紛，不負任何仲裁或賠償義務。</li>
          <li>本站保留隨時修改、暫停或終止任何服務的權利，且無須事先通知，對因此造成的損失不負任何責任。</li>
          <li>本站盡力維護系統安全，但不保證網站完全不受病毒或惡意程式的侵害，用戶應自行採取適當的防護措施。</li>
        </ul>
      </div>
    </section>

    <!-- 八、聲明變更 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">08</span>
        <h2 class="static-section-title">聲明變更</h2>
      </div>
      <div class="glass-light static-card">
        <p>本站保留隨時修改本免責聲明的權利。聲明修改後將更新頁面頂部的「最後更新日期」。您於聲明修改後繼續使用本站，即視為同意修改後的內容。</p>
      </div>
    </section>

    <!-- 版權侵權通報 CTA -->
    <section class="static-cta glass-mid">
      <div class="static-cta-icon">📩</div>
      <h3 class="static-cta-title">發現版權問題？</h3>
      <p class="static-cta-desc">
        若您認為本站任何內容侵犯了您的著作權，<br>
        請立即聯絡我們，我們將於 <strong>72 小時內</strong>處理您的申訴。
      </p>
      <a href="mailto:weixiaoacg.com@gmail.com" class="btn btn-primary">
        <i class="fa-solid fa-envelope"></i> weixiaoacg.com@gmail.com
      </a>
    </section>

  </div>
</main>

<?php get_footer(); ?>

<?php
/**
 * Template Name: 使用條款
 */
get_header(); ?>

<main class="static-page">

  <!-- Hero -->
  <section class="static-hero">
    <div class="static-hero-inner">
      <div class="static-hero-icon">📋</div>
      <h1 class="static-hero-title">使用條款</h1>
      <p class="static-hero-sub">最後更新日期：2026 年 4 月 25 日</p>
    </div>
  </section>

  <div class="static-content container">

    <!-- 前言 -->
    <section class="static-section glass-light">
      <p class="static-lead">
        歡迎使用微笑動漫。請在使用本站前仔細閱讀以下使用條款。當您存取或使用本站任何服務時，即表示您已閱讀、理解並同意接受本條款之所有內容。若您不同意本條款，請勿使用本站服務。
      </p>
    </section>

    <!-- 一、服務說明 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">01</span>
        <h2 class="static-section-title">服務說明</h2>
      </div>
      <div class="glass-light static-card">
        <p>微笑動漫是一個以動漫資訊為主題的非營利性個人網站，提供動畫、漫畫相關資訊、用戶評分、追番紀錄、積分系統等功能。本站資料主要來源於 AniList、Bangumi 等第三方公開資料庫，並不代表任何官方動漫製作公司或版權持有人。</p>
      </div>
    </section>

    <!-- 二、帳號註冊與使用規範 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">02</span>
        <h2 class="static-section-title">帳號註冊與使用規範</h2>
      </div>
      <div class="glass-light static-card">
        <ul class="static-list">
          <li>您必須年滿 13 歲方可註冊帳號。</li>
          <li>您須提供真實、準確的註冊資訊，並自行負責帳號安全。</li>
          <li>您不得將帳號轉讓、出售或借予他人使用。</li>
          <li>您可使用 Google 帳號進行第三方登入，相關資料處理請參閱本站隱私政策。</li>
          <li>本站保留在不事先通知的情況下，暫停或終止違規帳號的權利。</li>
        </ul>
      </div>
    </section>

    <!-- 三、禁止行為 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">03</span>
        <h2 class="static-section-title">禁止行為</h2>
      </div>
      <div class="static-grid-2">
        <?php
        $prohibited = [
          [ 'icon' => '🚫', 'text' => '散布違法、有害、誹謗、騷擾、猥褻或侵犯他人權利之內容' ],
          [ 'icon' => '🎭', 'text' => '冒充他人或虛假聲稱與任何個人或機構有關聯' ],
          [ 'icon' => '🦠', 'text' => '上傳或傳播任何病毒、惡意程式或任何破壞性程式碼' ],
          [ 'icon' => '🤖', 'text' => '以自動化工具、爬蟲程式大量存取或抓取本站資料' ],
          [ 'icon' => '🔓', 'text' => '試圖繞過本站的安全機制或未授權存取系統' ],
          [ 'icon' => '⚡', 'text' => '從事任何干擾本站正常運作的行為' ],
        ];
        foreach ( $prohibited as $item ) : ?>
        <div class="glass-light static-item-card">
          <span class="static-item-icon"><?php echo $item['icon']; ?></span>
          <p><?php echo esc_html( $item['text'] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 四、積分系統規則 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">04</span>
        <h2 class="static-section-title">積分系統規則</h2>
      </div>
      <div class="glass-light static-card">
        <ul class="static-list">
          <li>本站積分系統為虛擬獎勵機制，積分<strong>不具任何現金價值</strong>，不可兌換現金或轉移至其他平台。</li>
          <li>積分透過正常使用行為獲得，包括每日登入、收藏動漫、完成追番、留言等。</li>
          <li>本站保留調整積分規則、等級設定的權利，恕不另行通知。</li>
          <li>若帳號因違規被封鎖或刪除，所有積分將一併清除，恕不補償。</li>
          <li>嚴禁以任何作弊手段（如程式自動刷積分）獲取積分，一經發現將立即封號。</li>
        </ul>
      </div>
    </section>

    <!-- 五、內容版權聲明 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">05</span>
        <h2 class="static-section-title">內容版權聲明</h2>
      </div>
      <div class="glass-light static-card">
        <ul class="static-list">
          <li>本站動漫資訊、圖片及相關內容之版權歸各原作者、出版社及版權持有人所有。</li>
          <li>本站所使用之動漫封面圖、劇照等圖像資源主要來源於 AniList（anilist.co）及 Bangumi（bgm.tv）等公開資料庫，僅供資訊介紹用途。</li>
          <li>本站自行撰寫之文章、評論及介紹文字，版權歸微笑動漫所有，未經授權不得轉載。</li>
          <li>若您為版權持有人，認為本站內容侵犯您的著作權，請聯絡：<a href="mailto:weixiaoacg.com@gmail.com" class="static-link">weixiaoacg.com@gmail.com</a>，我們將於收到通知後盡速處理。</li>
        </ul>
      </div>
    </section>

    <!-- 六、廣告服務 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">06</span>
        <h2 class="static-section-title">廣告服務</h2>
      </div>
      <div class="glass-light static-card">
        <p>本站目前未投放廣告，未來可能引入第三方廣告服務（如 Google AdSense）。引入後將更新本條款及隱私政策，並於網站顯著位置公告。第三方廣告商可能依其本身的隱私政策使用 Cookie 追蹤技術，用戶可透過瀏覽器設定管理或拒絕 Cookie。</p>
      </div>
    </section>

    <!-- 七、免責聲明 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">07</span>
        <h2 class="static-section-title">免責聲明</h2>
      </div>
      <div class="glass-light static-card">
        <ul class="static-list">
          <li>本站資訊僅供參考，不保證所有內容之即時性、準確性及完整性。</li>
          <li>本站對於因使用或無法使用本站服務所造成的任何直接或間接損害，不負任何賠償責任。</li>
          <li>本站所提供之第三方連結（如 AniList、Bangumi、YouTube 等），其內容由第三方負責，本站不對其內容負責。</li>
          <li>本站保留隨時修改、暫停或終止服務的權利，恕不另行通知。</li>
        </ul>
      </div>
    </section>

    <!-- 八、條款變更 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">08</span>
        <h2 class="static-section-title">條款變更</h2>
      </div>
      <div class="glass-light static-card">
        <p>本站保留隨時修改本使用條款的權利。條款修改後將更新頁面頂部的「最後更新日期」，並於網站公告。您於條款修改後繼續使用本站，即視為同意修改後的條款。</p>
      </div>
    </section>

    <!-- 聯絡我們 -->
    <section class="static-cta glass-mid">
      <div class="static-cta-icon">✉️</div>
      <h3 class="static-cta-title">有任何疑問？</h3>
      <p class="static-cta-desc">如有關於本使用條款的任何問題，歡迎隨時聯絡我們。</p>
      <a href="mailto:weixiaoacg.com@gmail.com" class="btn btn-primary">
        <i class="fa-solid fa-envelope"></i> weixiaoacg.com@gmail.com
      </a>
    </section>

  </div>
</main>

<?php get_footer(); ?>

<?php
/**
 * Template Name: 隱私政策
 */
get_header(); ?>

<main class="static-page">

  <!-- Hero -->
  <section class="static-hero">
    <div class="static-hero-inner">
      <div class="static-hero-icon">🔒</div>
      <h1 class="static-hero-title">隱私政策</h1>
      <p class="static-hero-sub">最後更新日期：2026 年 4 月 25 日</p>
    </div>
  </section>

  <div class="static-content container">

    <!-- 前言 -->
    <section class="static-section glass-light">
      <p class="static-lead">
        微笑動漫非常重視您的隱私。本隱私政策說明我們如何收集、使用、儲存及保護您的個人資料。使用本站即表示您同意本政策之內容。
      </p>
    </section>

    <!-- 一、我們收集的資料 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">01</span>
        <h2 class="static-section-title">我們收集的資料</h2>
      </div>
      <div class="static-grid-2">
        <div class="glass-light static-card">
          <h4 class="static-card-title">👤 帳號資料</h4>
          <ul class="static-list">
            <li>使用者名稱</li>
            <li>電子郵件地址</li>
            <li>密碼（加密儲存，本站無法讀取明文）</li>
            <li>頭像及個人簡介（選填）</li>
          </ul>
        </div>
        <div class="glass-light static-card">
          <h4 class="static-card-title">🔑 Google 登入資料</h4>
          <ul class="static-list">
            <li>Google 帳號公開資料（名稱、電子郵件、頭像）</li>
            <li>本站不會存取您的 Google 密碼或其他私人資料</li>
          </ul>
        </div>
        <div class="glass-light static-card">
          <h4 class="static-card-title">📊 使用行為資料</h4>
          <ul class="static-list">
            <li>追番紀錄（收藏、觀看狀態、進度）</li>
            <li>評分紀錄</li>
            <li>積分及等級資料</li>
            <li>每日登入紀錄</li>
          </ul>
        </div>
        <div class="glass-light static-card">
          <h4 class="static-card-title">🌐 自動收集資料</h4>
          <ul class="static-list">
            <li>IP 位址</li>
            <li>瀏覽器類型及版本</li>
            <li>存取時間及頁面紀錄</li>
            <li>Cookie 資料</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- 二、資料使用目的 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">02</span>
        <h2 class="static-section-title">資料使用目的</h2>
      </div>
      <div class="glass-light static-card">
        <ul class="static-list">
          <li>提供帳號登入及身份驗證服務</li>
          <li>儲存您的追番進度、收藏及評分紀錄</li>
          <li>計算並顯示積分與等級</li>
          <li>改善網站功能與使用者體驗</li>
          <li>防止濫用及維護網站安全</li>
          <li>未來引入廣告服務時進行基本受眾分析（屆時將另行告知）</li>
        </ul>
        <div class="static-notice">
          <i class="fa-solid fa-shield-halved"></i>
          本站不會將您的個人資料出售、租借或以任何商業目的提供給第三方。
        </div>
      </div>
    </section>

    <!-- 三、第三方服務 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">03</span>
        <h2 class="static-section-title">第三方服務</h2>
      </div>
      <div class="static-grid-2">
        <?php
        $third_parties = [
          [
            'name'    => 'Google OAuth',
            'icon'    => 'fa-brands fa-google',
            'desc'    => '提供社群帳號登入功能。',
            'link'    => 'https://policies.google.com/privacy',
            'label'   => '查看隱私政策',
          ],
          [
            'name'    => 'AniList',
            'icon'    => 'fa-solid fa-database',
            'desc'    => '提供動漫資料及封面圖片來源。',
            'link'    => 'https://anilist.co/privacy',
            'label'   => '查看隱私政策',
          ],
          [
            'name'    => 'Bangumi',
            'icon'    => 'fa-solid fa-tv',
            'desc'    => '提供動漫資料及中文資訊來源。',
            'link'    => 'https://bgm.tv',
            'label'   => '前往官網',
          ],
          [
            'name'    => 'Google Fonts',
            'icon'    => 'fa-solid fa-font',
            'desc'    => '本站使用 Google Fonts 載入字體，可能收集基本連線資料。',
            'link'    => 'https://policies.google.com/privacy',
            'label'   => '查看隱私政策',
          ],
        ];
        foreach ( $third_parties as $tp ) : ?>
        <div class="glass-light static-card">
          <div class="static-card-head">
            <i class="<?php echo esc_attr( $tp['icon'] ); ?> static-card-fa"></i>
            <h4 class="static-card-title"><?php echo esc_html( $tp['name'] ); ?></h4>
          </div>
          <p><?php echo esc_html( $tp['desc'] ); ?></p>
          <a href="<?php echo esc_url( $tp['link'] ); ?>" target="_blank" rel="noopener" class="static-link">
            <?php echo esc_html( $tp['label'] ); ?> <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="glass-light static-card" style="margin-top:16px;">
        <h4 class="static-card-title">📢 未來廣告服務</h4>
        <p>本站未來可能引入 Google AdSense 或其他廣告服務。引入後，廣告商可能使用 Cookie 追蹤您的瀏覽行為以提供個人化廣告。屆時將更新本政策並公告。</p>
      </div>
    </section>

    <!-- 四、資料儲存與安全 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">04</span>
        <h2 class="static-section-title">資料儲存與安全</h2>
      </div>
      <div class="glass-light static-card">
        <ul class="static-list">
          <li>您的資料儲存於本站伺服器，並採取合理的技術措施保護資料安全。</li>
          <li>密碼採用加密方式儲存，本站工作人員無法讀取您的密碼明文。</li>
          <li>本站會定期備份資料，但不對因不可抗力（如伺服器故障、駭客攻擊）造成的資料遺失負責。</li>
          <li>您的帳號資料將保留至您主動刪除帳號為止。</li>
        </ul>
      </div>
    </section>

    <!-- 五、Cookie 使用 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">05</span>
        <h2 class="static-section-title">Cookie 使用</h2>
      </div>
      <div class="static-grid-3">
        <div class="glass-light static-card static-card--center">
          <div class="static-item-icon">🔧</div>
          <h4 class="static-card-title">必要性 Cookie</h4>
          <p>維持登入狀態及基本功能，無法關閉。</p>
        </div>
        <div class="glass-light static-card static-card--center">
          <div class="static-item-icon">⚙️</div>
          <h4 class="static-card-title">功能性 Cookie</h4>
          <p>記憶您的設定偏好，提升使用體驗。</p>
        </div>
        <div class="glass-light static-card static-card--center">
          <div class="static-item-icon">📢</div>
          <h4 class="static-card-title">第三方 Cookie</h4>
          <p>由 Google 等第三方服務設置，未來廣告引入後會增加。</p>
        </div>
      </div>
      <div class="glass-light static-card" style="margin-top:16px;">
        <p>您可透過瀏覽器設定管理或刪除 Cookie，但部分功能（如保持登入）可能因此受影響。</p>
      </div>
    </section>

    <!-- 六、您的權利 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">06</span>
        <h2 class="static-section-title">您的權利</h2>
      </div>
      <div class="static-grid-2">
        <?php
        $rights = [
          [ 'icon' => '👁️',  'title' => '查閱權',   'desc' => '您可隨時在帳號設定中查閱您的個人資料。' ],
          [ 'icon' => '✏️',  'title' => '更正權',   'desc' => '您可在帳號設定中修改不正確的個人資料。' ],
          [ 'icon' => '🗑️', 'title' => '刪除權',   'desc' => '您可在帳號設定中申請刪除帳號及所有相關資料。' ],
          [ 'icon' => '📦',  'title' => '資料匯出', 'desc' => '您可在帳號設定中申請匯出您的個人資料。' ],
          [ 'icon' => '↩️',  'title' => '撤回同意', 'desc' => '您可隨時撤回對資料使用的同意，但這可能影響部分服務的使用。' ],
        ];
        foreach ( $rights as $r ) : ?>
        <div class="glass-light static-item-card">
          <span class="static-item-icon"><?php echo $r['icon']; ?></span>
          <div>
            <strong><?php echo esc_html( $r['title'] ); ?></strong>
            <p><?php echo esc_html( $r['desc'] ); ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 七、未成年人保護 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">07</span>
        <h2 class="static-section-title">未成年人保護</h2>
      </div>
      <div class="glass-light static-card">
        <p>本站服務不針對 13 歲以下兒童。若我們發現不慎收集了 13 歲以下兒童的個人資料，將立即刪除相關資料。若您認為我們收集了兒童的個人資料，請立即聯絡我們。</p>
      </div>
    </section>

    <!-- 八、跨境資料傳輸 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">08</span>
        <h2 class="static-section-title">跨境資料傳輸</h2>
      </div>
      <div class="glass-light static-card">
        <p>本站服務面向台灣、香港、澳門及其他地區用戶。由於本站使用 Google OAuth、AniList 等國際第三方服務，您的部分資料可能被傳輸至台灣以外的伺服器處理。上述第三方服務均符合其所在地區的資料保護法規。</p>
      </div>
    </section>

    <!-- 九、隱私政策變更 -->
    <section class="static-section">
      <div class="static-section-header">
        <span class="static-section-num">09</span>
        <h2 class="static-section-title">隱私政策變更</h2>
      </div>
      <div class="glass-light static-card">
        <p>本站保留隨時修改本隱私政策的權利。政策修改後將更新頁面頂部的「最後更新日期」。重大變更時將於網站顯著位置公告。您於政策修改後繼續使用本站，即視為同意修改後的政策。</p>
      </div>
    </section>

    <!-- 聯絡我們 -->
    <section class="static-cta glass-mid">
      <div class="static-cta-icon">✉️</div>
      <h3 class="static-cta-title">隱私相關疑問？</h3>
      <p class="static-cta-desc">如有任何關於本隱私政策的疑問或申訴，歡迎隨時聯絡我們。</p>
      <a href="mailto:weixiaoacg.com@gmail.com" class="btn btn-primary">
        <i class="fa-solid fa-envelope"></i> weixiaoacg.com@gmail.com
      </a>
    </section>

  </div>
</main>

<?php get_footer(); ?>

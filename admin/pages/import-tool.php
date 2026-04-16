<?php
/**
 * 檔案名稱: admin/pages/import-tool.php
 *
 * ACD – 全寬版面、Tab 2 季度批次、Tab 4 系列分析、Tab 5 人氣排行
 * ACE – Tab 4 renderSeriesTable() node.id → node.anilist_id
 *       Tab 4 relation_type 空值顯示「根源」而非「—」
 *       Tab 5 全部補完：action 名稱、res.data.items、item.anilist_id、
 *              翻頁累加邏輯（第 1 頁替換，後續追加）
 * ACJ – Tab 2 季度結果加入格式篩選列（TV/MOVIE/OVA/ONA/SPECIAL）
 *       匯入時只收集可見勾選項（:checked:visible）
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cn_converter    = new Anime_Sync_CN_Converter();
$converter_stats = $cn_converter->get_stats();
?>

<div class="wrap anime-sync-import-tool">
    <h1>匯入動畫工具</h1>

    <div class="notice notice-info inline" style="margin: 15px 0 10px; border-left-color: #2271b1;">
        <p>
            <strong>🔍 繁簡轉換器狀態：</strong>
            詞條總數 <code><?php echo number_format($converter_stats['entry_count']); ?></code> 條 |
            <?php echo $converter_stats['loaded']
                ? '<span style="color:green;font-weight:bold;">✓ 運作正常</span>'
                : '<span style="color:red;font-weight:bold;">❌ 字典載入失敗</span>'; ?>
            <?php if ($converter_stats['loaded']): ?>
                | <small>測試：「脚本」→「<?php echo esc_html( $cn_converter->convert('脚本') ); ?>」</small>
            <?php endif; ?>
        </p>
    </div>

    <h2 class="nav-tab-wrapper" style="margin-bottom:0;">
        <a href="#single"  class="nav-tab nav-tab-active" data-tab="single">📥 單筆匯入</a>
        <a href="#season"  class="nav-tab" data-tab="season">📅 季度批次匯入</a>
        <a href="#batch"   class="nav-tab" data-tab="batch">📋 ID 清單匯入</a>
        <a href="#series"  class="nav-tab" data-tab="series">🔗 系列分析匯入</a>
        <a href="#ranking" class="nav-tab" data-tab="ranking">🏆 人氣排行匯入</a>
    </h2>

    <!-- TAB 1：單筆匯入 -->
    <div id="tab-single" class="anime-sync-tab-content" style="display:block;">
        <div class="asc-card" style="max-width:680px; margin-top:20px;">
            <h3>透過 AniList ID 匯入</h3>
            <table class="form-table">
                <tr>
                    <th><label for="single-anilist-id">AniList ID</label></th>
                    <td>
                        <input type="number" id="single-anilist-id" class="regular-text" placeholder="例如: 1535">
                        <p class="description">請輸入作品在 AniList 網址結尾的數字。</p>
                    </td>
                </tr>
                <tr>
                    <th>選項</th>
                    <td><label><input type="checkbox" id="single-force-update"> 強制更新（若已存在則覆蓋資料）</label></td>
                </tr>
            </table>
            <p><button type="button" id="btn-single-import" class="button button-primary button-large">開始匯入</button></p>
            <div id="single-import-result" style="margin-top:20px; padding:15px; display:none; border-radius:4px;"></div>
        </div>
    </div>

    <!-- TAB 2：季度批次匯入 -->
    <div id="tab-season" class="anime-sync-tab-content" style="display:none;">
        <div class="asc-card" style="margin-top:20px;">
            <h3>按季度篩選</h3>
            <div style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; margin-bottom:20px;">
                <div>
                    <label>年份</label><br>
                    <select id="season-year-select">
                        <?php for($y = date('Y')+1; $y >= 2000; $y--) echo "<option value='$y'" . (date('Y') == $y ? ' selected' : '') . ">$y</option>"; ?>
                    </select>
                </div>
                <div>
                    <label>季節</label><br>
                    <select id="season-select">
                        <option value="WINTER">冬季 (1–3月)</option>
                        <option value="SPRING">春季 (4–6月)</option>
                        <option value="SUMMER">夏季 (7–9月)</option>
                        <option value="FALL">秋季 (10–12月)</option>
                    </select>
                </div>
                <div>
                    <button type="button" id="btn-season-query" class="button">第一步：查詢季度清單</button>
                    <span id="season-query-spinner" style="display:none; margin-left:8px;">⏳ 查詢中，可能需要 10–30 秒…</span>
                </div>
            </div>
            <div id="season-preview" style="display:none;">
                <p id="season-preview-summary" class="description" style="font-size:13px;"></p>

                <!-- ACJ：格式篩選列 -->
                <div id="season-format-filter" style="margin-bottom:12px; padding:10px 12px; background:#f6f7f7; border:1px solid #ddd; border-radius:4px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                    <strong style="margin-right:4px;">篩選格式：</strong>
                    <label><input type="checkbox" class="format-filter-check" value="TV" checked> TV</label>
                    <label><input type="checkbox" class="format-filter-check" value="MOVIE" checked> MOVIE</label>
                    <label><input type="checkbox" class="format-filter-check" value="OVA" checked> OVA</label>
                    <label><input type="checkbox" class="format-filter-check" value="ONA" checked> ONA</label>
                    <label><input type="checkbox" class="format-filter-check" value="SPECIAL" checked> SPECIAL</label>
                    <label><input type="checkbox" class="format-filter-check" value="MUSIC"> MUSIC</label>
                    <button type="button" id="btn-apply-format-filter" class="button button-small" style="margin-left:8px;">套用篩選</button>
                    <span id="season-filter-count" style="color:#666; font-size:12px;"></span>
                </div>

                <div class="asc-table-wrap">
                    <table class="wp-list-table widefat fixed striped asc-season-table">
                        <thead><tr>
                            <th style="width:36px;"><input id="season-select-all" type="checkbox" checked></th>
                            <th style="width:70px;">ID</th>
                            <th>名稱 (Romaji)</th>
                            <th style="width:80px;">格式</th>
                            <th style="width:60px;">集數</th>
                            <th style="width:90px;">人氣</th>
                            <th style="width:90px;">狀態</th>
                            <th style="width:80px;">站內狀態</th>
                        </tr></thead>
                        <tbody id="season-anime-tbody"></tbody>
                    </table>
                </div>
                <div style="margin-top:15px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <button type="button" id="btn-season-import" class="button button-primary">第二步：開始批次匯入選中項</button>
                    <button type="button" id="btn-season-stop" class="button" style="display:none; color:red;">停止匯入</button>
                    <span id="season-throttle-notice" style="display:none; color:#d97706; font-weight:bold;"></span>
                </div>
                <div id="season-progress-wrap" style="margin-top:20px; display:none;">
                    <div style="background:#eee; height:20px; border-radius:10px; overflow:hidden;">
                        <div id="season-progress-bar" style="background:#2271b1; width:0%; height:100%; transition:width .3s;"></div>
                    </div>
                    <p id="season-progress-text" style="text-align:center; font-weight:bold;"></p>
                    <div id="season-import-log" class="asc-log-box"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3：ID 清單批次匯入 -->
    <div id="tab-batch" class="anime-sync-tab-content" style="display:none;">
        <div class="asc-card" style="max-width:680px; margin-top:20px;">
            <h3>大量 ID 清單匯入</h3>
            <p class="description">請輸入 AniList ID，用換行或逗號隔開。</p>
            <textarea id="batch-id-list" rows="8" class="large-text" placeholder="例如:&#10;1535&#10;21,20&#10;16498"></textarea>
            <p id="batch-id-count" style="color:#666;">0 個 ID</p>
            <p><label><input type="checkbox" id="batch-force-update"> 強制更新（跳過已存在的檢查）</label></p>
            <p>
                <button type="button" id="btn-batch-import" class="button button-primary">開始批次匯入</button>
                <button type="button" id="btn-batch-stop" class="button" style="display:none; color:red;">停止</button>
                <span id="batch-throttle-notice" style="display:none; color:#d97706; font-weight:bold; margin-left:10px;"></span>
            </p>
            <div id="batch-progress-wrap" style="margin-top:20px; display:none;">
                <div style="background:#eee; height:20px; border-radius:10px; overflow:hidden;">
                    <div id="batch-progress-bar" style="background:#2271b1; width:0%; height:100%;"></div>
                </div>
                <p id="batch-progress-text" style="text-align:center; font-weight:bold;"></p>
                <div id="batch-import-log" class="asc-log-box"></div>
            </div>
        </div>
    </div>

    <!-- TAB 4：系列分析匯入 -->
    <div id="tab-series" class="anime-sync-tab-content" style="display:none;">
        <div class="asc-card" style="margin-top:20px;">
            <h3>🔗 系列分析與匯入</h3>
            <p class="description">輸入任意一部作品的 AniList ID，系統將自動追溯前作、列出完整系列，並標記哪些已匯入。</p>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:20px;">
                <input type="number" id="series-anilist-id" class="regular-text" placeholder="輸入任意一部的 AniList ID，例如 20958">
                <button type="button" id="btn-analyze-series" class="button button-primary">🔍 分析系列</button>
                <span id="series-analyze-spinner" style="display:none;">⏳ 分析中，正在遞迴追溯前作…</span>
            </div>
            <div id="series-result" style="display:none;">
                <div id="series-info" style="background:#f0f7ff; border:1px solid #b8d4f5; border-radius:4px; padding:12px; margin-bottom:15px;"></div>
                <div class="asc-table-wrap">
                    <table class="wp-list-table widefat fixed striped asc-series-table">
                        <thead><tr>
                            <th style="width:36px;"><input id="series-select-all" type="checkbox" checked></th>
                            <th style="width:70px;">ID</th>
                            <th>作品名稱</th>
                            <th style="width:80px;">格式</th>
                            <th style="width:70px;">年份</th>
                            <th style="width:100px;">關聯類型</th>
                            <th style="width:90px;">站內狀態</th>
                        </tr></thead>
                        <tbody id="series-tbody"></tbody>
                    </table>
                </div>
                <div style="margin-top:15px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <button type="button" id="btn-series-import" class="button button-primary">📥 匯入選中作品並歸入系列</button>
                    <button type="button" id="btn-series-stop" class="button" style="display:none; color:red;">停止</button>
                    <span id="series-throttle-notice" style="display:none; color:#d97706; font-weight:bold;"></span>
                </div>
                <div id="series-progress-wrap" style="margin-top:20px; display:none;">
                    <div style="background:#eee; height:20px; border-radius:10px; overflow:hidden;">
                        <div id="series-progress-bar" style="background:#2271b1; width:0%; height:100%; transition:width .3s;"></div>
                    </div>
                    <p id="series-progress-text" style="text-align:center; font-weight:bold;"></p>
                    <div id="series-import-log" class="asc-log-box"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 5：人氣排行匯入 -->
    <div id="tab-ranking" class="anime-sync-tab-content" style="display:none;">
        <div class="asc-card" style="margin-top:20px;">
            <h3>🏆 AniList 人氣排行匯入</h3>
            <p class="description">依 AniList 人氣排行載入，每次 50 部，標記已匯入狀態，未匯入預設勾選。</p>
            <div style="display:flex; gap:10px; align-items:center; margin-bottom:15px; flex-wrap:wrap;">
                <button type="button" id="btn-ranking-load" class="button">📄 載入排行（第 <span id="ranking-page-num">1</span> 頁）</button>
                <button type="button" id="btn-ranking-more" class="button" style="display:none;">➕ 載入更多 50 部</button>
                <span id="ranking-load-spinner" style="display:none;">⏳ 載入中…</span>
            </div>
            <div id="ranking-preview" style="display:none;">
                <p id="ranking-preview-summary" class="description"></p>
                <div class="asc-table-wrap">
                    <table class="wp-list-table widefat fixed striped asc-ranking-table">
                        <thead><tr>
                            <th style="width:36px;"><input id="ranking-select-all" type="checkbox" checked></th>
                            <th style="width:40px;">#</th>
                            <th style="width:60px;">封面</th>
                            <th>作品名稱</th>
                            <th style="width:80px;">格式</th>
                            <th style="width:60px;">集數</th>
                            <th style="width:90px;">人氣</th>
                            <th style="width:90px;">站內狀態</th>
                        </tr></thead>
                        <tbody id="ranking-tbody"></tbody>
                    </table>
                </div>
                <div style="margin-top:15px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <button type="button" id="btn-ranking-import" class="button button-primary" style="display:none;">📥 匯入選中作品</button>
                    <button type="button" id="btn-ranking-stop" class="button" style="display:none; color:red;">停止</button>
                    <span id="ranking-throttle-notice" style="display:none; color:#d97706; font-weight:bold;"></span>
                </div>
                <div id="ranking-progress-wrap" style="margin-top:20px; display:none;">
                    <div style="background:#eee; height:20px; border-radius:10px; overflow:hidden;">
                        <div id="ranking-progress-bar" style="background:#2271b1; width:0%; height:100%; transition:width .3s;"></div>
                    </div>
                    <p id="ranking-progress-text" style="text-align:center; font-weight:bold;"></p>
                    <div id="ranking-import-log" class="asc-log-box"></div>
                </div>
            </div>
        </div>
    </div>

</div><!-- .anime-sync-import-tool -->

<style>
.anime-sync-import-tool { max-width: none !important; }
#wpcontent { padding-right: 20px; }
.asc-card {
    background: #fff; border: 1px solid #ccd0d4;
    padding: 20px 24px; box-shadow: 0 1px 1px rgba(0,0,0,.04);
    border-radius: 4px; margin-bottom: 20px;
}
.asc-table-wrap { overflow-x: auto; margin-bottom: 10px; border: 1px solid #ddd; }
.asc-table-wrap table { width: 100%; min-width: 600px; }
.asc-season-table  th:nth-child(3),
.asc-series-table  th:nth-child(3),
.asc-ranking-table th:nth-child(4) { width: auto; min-width: 200px; }
.asc-cover-thumb { width: 36px; height: 50px; object-fit: cover; border-radius: 2px; display: block; }
.asc-log-box {
    background: #111; color: #0f0; padding: 10px; height: 220px;
    overflow-y: auto; font-family: monospace; font-size: 12px;
    margin-top: 10px; border-radius: 5px;
}
.log-success  { color: #0f0; }
.log-warning  { color: #f90; font-weight: bold; }
.log-error    { color: #f33; }
.log-skip     { color: #aaa; }
.log-info     { color: #3df; border-bottom: 1px solid #333; padding-bottom: 2px; margin-bottom: 5px; }
.log-throttle { color: #ff0; font-weight: bold; }
.status-imported { color: #46b450; font-weight: bold; }
.status-new      { color: #2271b1; }
#single-import-result.success { background: #edfaef; border: 1px solid #46b450; color: #235926; }
#single-import-result.warning { background: #fff8e5; border: 1px solid #d97706; color: #7a4b00; }
#single-import-result.error   { background: #fcf0f1; border: 1px solid #dc3232; color: #a42821; }
/* ACJ：格式篩選列隱藏的列 */
#season-anime-tbody tr.format-hidden { display: none; }
</style>

<script>
jQuery( function( $ ) {

    var NONCE    = animeSyncAdmin.nonce;
    var AJAX_URL = animeSyncAdmin.ajaxUrl || ajaxurl;

    /* ── Tab 切換 ──────────────────────────────────────────────── */
    function switchTab( tabId ) {
        $( '.anime-sync-import-tool .nav-tab' ).removeClass( 'nav-tab-active' );
        $( '.nav-tab[data-tab="' + tabId + '"]' ).addClass( 'nav-tab-active' );
        $( '.anime-sync-tab-content' ).hide();
        $( '#tab-' + tabId ).show();
    }
    $( document ).on( 'click', '.anime-sync-import-tool .nav-tab', function(e) {
        e.preventDefault();
        switchTab( $( this ).data('tab') );
        window.location.hash = $( this ).data('tab');
    } );
    var _hash = window.location.hash.replace('#','');
    if ( _hash && $( '.nav-tab[data-tab="' + _hash + '"]' ).length ) switchTab( _hash );
    else switchTab( 'single' );

    /* ── 共用工具 ──────────────────────────────────────────────── */
    function esc( str ) {
        if ( ! str ) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function appendLog( sel, text, type ) {
        var $log = $( sel );
        $log.append( '<div class="log-' + (type||'info') + '">[' + new Date().toLocaleTimeString() + '] ' + text + '</div>' )
            .scrollTop( $log[0].scrollHeight );
    }

    function throttledImport( ids, importFn, logSel, progressSel, textSel, noticeSel, stopRef, onComplete ) {
        var total = ids.length, current = 0;

        function next() {
            if ( stopRef.value || current >= total ) {
                $( noticeSel ).hide();
                if ( onComplete ) onComplete( current );
                return;
            }
            if ( current > 0 && current % 10 === 0 ) {
                var countdown = 10;
                $( noticeSel ).show().text( '⏸ 防止 API 限制，暫停 ' + countdown + ' 秒…' );
                appendLog( logSel, '── 已處理 ' + current + ' 部，暫停 10 秒防止 API 限制 ──', 'throttle' );
                var timer = setInterval( function() {
                    countdown--;
                    if ( countdown <= 0 ) { clearInterval(timer); $( noticeSel ).hide(); doImport(); }
                    else $( noticeSel ).text( '⏸ 防止 API 限制，暫停 ' + countdown + ' 秒…' );
                }, 1000 );
                return;
            }
            doImport();
        }

        function doImport() {
            if ( stopRef.value || current >= total ) {
                $( noticeSel ).hide();
                if ( onComplete ) onComplete( current );
                return;
            }
            importFn( ids[current], function() {
                current++;
                $( progressSel ).css( 'width', Math.round(current/total*100) + '%' );
                $( textSel ).text( current + ' / ' + total );
                setTimeout( next, 400 );
            } );
        }

        next();
    }

    /* ── TAB 1：單筆匯入 ────────────────────────────────────────── */
    $( '#btn-single-import' ).on( 'click', function() {
        var id = $.trim( $( '#single-anilist-id' ).val() );
        if ( !id || isNaN(id) || parseInt(id) <= 0 ) { alert('請輸入有效的 AniList ID'); return; }
        var $btn = $( this ).prop('disabled',true).text('匯入中…');
        var $r   = $( '#single-import-result' ).hide().removeClass('success warning error');
        $.post( AJAX_URL, { action:'anime_sync_import_single', anilist_id:id,
            force_update: $( '#single-force-update' ).is(':checked') ? 1 : 0, nonce:NONCE } )
        .done( function(res) {
            if ( res.success ) {
                var miss = res.data.bangumi_missing === true;
                $r.addClass( miss ? 'warning' : 'success' ).html(
                    '<strong>' + (miss?'⚠️':'✅') + ' ' + res.data.message + '</strong>' +
                    (res.data.edit_url ? ' <br><a href="'+res.data.edit_url+'" class="button" target="_blank" style="margin-top:10px;">前往編輯</a>' : '') );
            } else {
                $r.addClass('error').html('<strong>❌ ' + (res.data.message||'匯入失敗') + '</strong>');
            }
        } ).fail( function() { $r.addClass('error').html('<strong>❌ 網路或 PHP 錯誤。</strong>'); } )
        .always( function() { $btn.prop('disabled',false).text('開始匯入'); $r.show(); } );
    } );

    /* ── TAB 2：季度批次匯入 ─────────────────────────────────────── */
    var seasonStop = { value: false };

    $( '#btn-season-query' ).on( 'click', function() {
        var $btn = $( this ).prop('disabled',true).text('查詢中…');
        $( '#season-query-spinner' ).show();
        $( '#season-preview' ).hide();
        $.post( AJAX_URL, { action:'anime_sync_query_season',
            season: $( '#season-select' ).val(), year: $( '#season-year-select' ).val(), nonce:NONCE } )
        .done( function(res) {
            if ( res.success && res.data.list ) {
                renderSeasonTable( res.data.list );
                updateSeasonSummary();
                $( '#season-preview' ).show();
                $( '#btn-season-import' ).prop('disabled',false);
            } else { alert( res.data || '查詢失敗' ); }
        } ).fail( function() { alert('查詢失敗。'); } )
        .always( function() { $btn.prop('disabled',false).text('第一步：查詢季度清單'); $( '#season-query-spinner' ).hide(); } );
    } );

    function renderSeasonTable( list ) {
        var html = '';
        $.each( list, function(i, item) {
            var imp = item.imported ? '<span class="status-imported">✅ 已匯入</span>' : '<span class="status-new">⬜ 未匯入</span>';
            var fmt = ( item.format || '' ).toUpperCase();
            html += '<tr data-format="' + esc(fmt) + '">' +
                '<td><input type="checkbox" class="season-item-check" value="'+item.anilist_id+'" '+(item.imported?'':'checked')+'></td>' +
                '<td>'+item.anilist_id+'</td><td>'+(item.title_romaji||'-')+'</td>' +
                '<td>'+(item.format||'-')+'</td><td>'+(item.episodes||'?')+'</td>' +
                '<td>'+(item.popularity||0)+'</td><td>'+(item.status||'-')+'</td>' +
                '<td>'+imp+'</td></tr>';
        } );
        $( '#season-anime-tbody' ).html( html );
    }

    // ACJ：更新摘要文字（顯示中 / 全部 / 勾選數）
    function updateSeasonSummary() {
        var total    = $( '#season-anime-tbody tr' ).length;
        var visible  = $( '#season-anime-tbody tr:visible' ).length;
        var checked  = $( '.season-item-check:checked:visible' ).length;
        $( '#season-preview-summary' ).text(
            '共 ' + total + ' 部，目前顯示 ' + visible + ' 部，已勾選 ' + checked + ' 部。'
        );
        $( '#season-filter-count' ).text( '顯示 ' + visible + ' / ' + total + ' 部' );
    }

    // ACJ：套用格式篩選
    $( '#btn-apply-format-filter' ).on( 'click', function() {
        var checked = [];
        $( '.format-filter-check:checked' ).each( function() {
            checked.push( $( this ).val().toUpperCase() );
        } );
        $( '#season-anime-tbody tr' ).each( function() {
            var fmt = ( $( this ).data('format') || '' ).toUpperCase();
            if ( checked.length === 0 || checked.indexOf( fmt ) !== -1 ) {
                $( this ).show();
            } else {
                $( this ).hide();
                // 隱藏的列取消勾選，避免被匯入
                $( this ).find( '.season-item-check' ).prop( 'checked', false );
            }
        } );
        updateSeasonSummary();
    } );

    $( '#season-select-all' ).on( 'change', function() {
        // 只操作可見列
        $( '#season-anime-tbody tr:visible .season-item-check' ).prop( 'checked', $( this ).is(':checked') );
        updateSeasonSummary();
    } );

    // 勾選變動時更新摘要
    $( document ).on( 'change', '.season-item-check', function() {
        updateSeasonSummary();
    } );

    $( '#btn-season-import' ).on( 'click', function() {
        // ACJ：只收集可見且勾選的項目
        var ids = [];
        $( '#season-anime-tbody tr:visible .season-item-check:checked' ).each( function() {
            ids.push( parseInt( $( this ).val() ) );
        } );
        if ( !ids.length ) { alert('請至少選擇一部動畫'); return; }
        seasonStop.value = false;
        $( '#btn-season-import' ).prop('disabled',true);
        $( '#btn-season-stop' ).show();
        $( '#season-progress-wrap' ).show();
        $( '#season-import-log' ).empty();
        throttledImport( ids, function(id,done) {
            $.post( AJAX_URL, { action:'anime_sync_import_single', anilist_id:id, nonce:NONCE } )
            .done( function(res) {
                var label = (res.data&&res.data.title) ? res.data.title : id;
                var msg   = (res.data&&res.data.message) ? res.data.message : 'Done';
                appendLog( '#season-import-log', label+': '+msg,
                    res.success ? (res.data.bangumi_missing?'warning':(res.data.skipped?'skip':'success')) : 'error' );
            } ).fail( function() { appendLog('#season-import-log', id+': 網路錯誤', 'error'); } )
            .always( done );
        }, '#season-import-log','#season-progress-bar','#season-progress-text','#season-throttle-notice', seasonStop,
        function() { $( '#btn-season-import' ).prop('disabled',false); $( '#btn-season-stop' ).hide(); appendLog('#season-import-log','── 匯入完成 ──','info'); } );
    } );
    $( '#btn-season-stop' ).on( 'click', function() { seasonStop.value = true; } );

    /* ── TAB 3：ID 清單批次匯入 ──────────────────────────────────── */
    var batchStop = { value: false };

    $( '#batch-id-list' ).on( 'input', function() {
        var ids = $( this ).val().split(/[\n,]+/).map(s=>s.trim()).filter(s=>/^\d+$/.test(s));
        $( '#batch-id-count' ).text( ids.length + ' 個 ID' );
    } );

    $( '#btn-batch-import' ).on( 'click', function() {
        var ids = $( '#batch-id-list' ).val().split(/[\n,]+/).map(s=>parseInt(s.trim())).filter(n=>n>0);
        if ( !ids.length ) { alert('請輸入至少一個有效 ID'); return; }
        batchStop.value = false;
        $( '#btn-batch-import' ).prop('disabled',true);
        $( '#btn-batch-stop' ).show();
        $( '#batch-progress-wrap' ).show();
        $( '#batch-import-log' ).empty();
        var s=0, w=0, sk=0, f=0;
        throttledImport( ids, function(id,done) {
            $.post( AJAX_URL, { action:'anime_sync_import_single', anilist_id:id,
                force_update: $( '#batch-force-update' ).is(':checked')?1:0, nonce:NONCE } )
            .done( function(res) {
                var label=(res.data&&res.data.title)?res.data.title:id;
                var msg=(res.data&&res.data.message)?res.data.message:'Done';
                if(res.success){ if(res.data.skipped){sk++;appendLog('#batch-import-log',label+': '+msg,'skip');}
                    else if(res.data.bangumi_missing){w++;appendLog('#batch-import-log',label+': '+msg,'warning');}
                    else{s++;appendLog('#batch-import-log',label+': '+msg,'success');}
                } else { f++; appendLog('#batch-import-log',id+': ❌ '+msg,'error'); }
            } ).fail( function() { f++; appendLog('#batch-import-log',id+': 網路錯誤','error'); } )
            .always( done );
        }, '#batch-import-log','#batch-progress-bar','#batch-progress-text','#batch-throttle-notice', batchStop,
        function() {
            $( '#btn-batch-import' ).prop('disabled',false); $( '#btn-batch-stop' ).hide();
            appendLog('#batch-import-log','── 完成：✅'+s+' ⚠️'+w+' ⏭'+sk+' ❌'+f+' ──','info');
        } );
    } );
    $( '#btn-batch-stop' ).on( 'click', function() { batchStop.value = true; } );

    /* ── TAB 4：系列分析匯入 ─────────────────────────────────────── */
    var seriesStop = { value: false };
    var seriesMeta = { series_name: '', root_id: 0, series_romaji: '' };

    $( '#btn-analyze-series' ).on( 'click', function() {
        var id = parseInt( $( '#series-anilist-id' ).val() );
        if ( !id || id <= 0 ) { alert('請輸入有效的 AniList ID'); return; }
        var $btn = $( this ).prop('disabled',true).text('分析中…');
        $( '#series-analyze-spinner' ).show();
        $( '#series-result' ).hide();
        $.post( AJAX_URL, { action:'anime_sync_analyze_series', anilist_id:id, nonce:NONCE } )
        .done( function(res) {
            if ( res.success && res.data.tree ) {
                var d = res.data;
                seriesMeta.series_name   = d.series_name;
                seriesMeta.root_id       = d.root_id;
                seriesMeta.series_romaji = d.series_romaji || '';
                $( '#series-info' ).html(
                    '🎯 <strong>系列名稱：' + esc(d.series_name) + '</strong>　' +
                    '根源 ID：' + d.root_id + '　共 ' + d.total + ' 部　' +
                    '已匯入 <span style="color:green;">' + d.imported + '</span> 部　' +
                    '待匯入 <span style="color:#d97706;">' + (d.total-d.imported) + '</span> 部'
                );
                renderSeriesTable( d.tree );
                $( '#series-result' ).show();
                $( '#btn-series-import' ).prop('disabled',false);
            } else {
                alert( (res.data&&res.data.message) ? res.data.message : '分析失敗' );
            }
        } ).fail( function() { alert('網路錯誤，請重試。'); } )
        .always( function() { $btn.prop('disabled',false).text('🔍 分析系列'); $( '#series-analyze-spinner' ).hide(); } );
    } );

    function renderSeriesTable( tree ) {
        var labelMap = { 'PREQUEL':'前作','SEQUEL':'續作','SIDE_STORY':'外傳','SPIN_OFF':'衍生','ALTERNATIVE':'平行','PARENT':'主作品' };
        var html = '';
        $.each( tree, function(i, node) {
            var aid     = node.anilist_id;
            var name    = node.title_chinese || node.title_romaji || node.title_native || ('ID ' + aid);
            var imp     = node.imported
                ? '<span class="status-imported">✅ 已匯入</span>'
                : '<span class="status-new">⬜ 未匯入</span>';
            var checked = node.imported ? '' : 'checked';
            var relType = node.relation_type
                ? ( labelMap[node.relation_type] || node.relation_type )
                : '根源';
            html += '<tr>' +
                '<td><input type="checkbox" class="series-item-check" value="' + aid + '" ' + checked + '></td>' +
                '<td>' + aid + '</td>' +
                '<td>' + esc(name) + ( node.imported && node.edit_url ? ' <a href="'+node.edit_url+'" target="_blank" style="font-size:11px;">[編輯]</a>' : '' ) + '</td>' +
                '<td>' + (node.format    || '—') + '</td>' +
                '<td>' + (node.season_year || '—') + '</td>' +
                '<td>' + relType + '</td>' +
                '<td>' + imp + '</td>' +
                '</tr>';
        } );
        $( '#series-tbody' ).html( html );
    }

    $( '#series-select-all' ).on( 'change', function() {
        $( '.series-item-check' ).prop( 'checked', $( this ).is(':checked') );
    } );

    $( '#btn-series-import' ).on( 'click', function() {
        var ids = [];
        $( '.series-item-check:checked' ).each( function() { ids.push( parseInt( $( this ).val() ) ); } );
        if ( !ids.length ) { alert('請至少選擇一部'); return; }
        seriesStop.value = false;
        $( '#btn-series-import' ).prop('disabled',true);
        $( '#btn-series-stop' ).show();
        $( '#series-progress-wrap' ).show();
        $( '#series-import-log' ).empty();
        throttledImport( ids, function(id,done) {
            $.post( AJAX_URL, { action:'anime_sync_import_series', anilist_id:id,
                series_name:seriesMeta.series_name, root_id:seriesMeta.root_id,
                series_romaji:seriesMeta.series_romaji, nonce:NONCE } )
            .done( function(res) {
                var label=(res.data&&res.data.title)?res.data.title:id;
                var msg=(res.data&&res.data.message)?res.data.message:'Done';
                if ( res.success ) {
                    var extra = res.data.series_assigned ? ' 🔗 已歸入系列' : '';
                    appendLog( '#series-import-log', label+': '+msg+extra,
                        res.data.bangumi_missing?'warning':(res.data.skipped?'skip':'success') );
                } else { appendLog('#series-import-log', id+': ❌ '+msg, 'error'); }
            } ).fail( function() { appendLog('#series-import-log', id+': 網路錯誤', 'error'); } )
            .always( done );
        }, '#series-import-log','#series-progress-bar','#series-progress-text','#series-throttle-notice', seriesStop,
        function() {
            $( '#btn-series-import' ).prop('disabled',false); $( '#btn-series-stop' ).hide();
            appendLog('#series-import-log','── 匯入完成 ──','info');
        } );
    } );
    $( '#btn-series-stop' ).on( 'click', function() { seriesStop.value = true; } );

    /* ── TAB 5：人氣排行匯入 ────────────────────────────────────── */
    var rankingPage = 1;
    var rankingStop = { value: false };

    function loadRankingPage() {
        $( '#ranking-load-spinner' ).show();
        $( '#btn-ranking-load' ).prop('disabled',true);
        $( '#btn-ranking-more' ).prop('disabled',true);

        $.post( AJAX_URL, {
            action : 'anime_sync_popularity_ranking',
            page   : rankingPage,
            nonce  : NONCE
        } ).done( function(res) {
            if ( res.success && res.data && res.data.items ) {
                renderRankingTable( res.data.items );
                $( '#ranking-preview' ).show();
                $( '#btn-ranking-import' ).show();
                $( '#btn-ranking-more' ).show();
                $( '#ranking-preview-summary' ).text(
                    '第 ' + rankingPage + ' 頁，本頁 ' + res.data.items.length +
                    ' 部，累計 ' + $( '.ranking-item-check' ).length + ' 部'
                );
                $( '#ranking-page-num' ).text( rankingPage );
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : '載入失敗';
                alert( '❌ ' + msg );
            }
        } ).fail( function(xhr) {
            alert( '❌ 網路錯誤（HTTP ' + xhr.status + '），請稍後重試。' );
        } ).always( function() {
            $( '#ranking-load-spinner' ).hide();
            $( '#btn-ranking-load' ).prop('disabled',false);
            $( '#btn-ranking-more' ).prop('disabled',false);
        } );
    }

    $( '#btn-ranking-load' ).on( 'click', function() {
        rankingPage = 1;
        $( '#ranking-tbody' ).empty();
        $( '#ranking-page-num' ).text(1);
        loadRankingPage();
    } );

    $( '#btn-ranking-more' ).on( 'click', function() {
        rankingPage++;
        loadRankingPage();
    } );

    function renderRankingTable( items ) {
        var startRank = ( rankingPage - 1 ) * 50 + 1;
        var html = '';
        $.each( items, function(i, item) {
            var aid    = item.anilist_id;
            var name   = item.title_chinese || item.title_romaji || item.title_native || ('ID ' + aid);
            var cover  = item.cover_image
                ? '<img src="' + esc(item.cover_image) + '" class="asc-cover-thumb" loading="lazy">'
                : '—';
            var imp    = item.imported
                ? '<span class="status-imported">✅ 已匯入</span>'
                : '<span class="status-new">⬜ 未匯入</span>';
            var checked = item.imported ? '' : 'checked';
            html += '<tr>' +
                '<td><input type="checkbox" class="ranking-item-check" value="' + aid + '" ' + checked + '></td>' +
                '<td>' + (startRank + i) + '</td>' +
                '<td>' + cover + '</td>' +
                '<td>' + esc(name) +
                    ( item.imported && item.edit_url
                        ? ' <a href="'+esc(item.edit_url)+'" target="_blank" style="font-size:11px;">[編輯]</a>'
                        : '' ) +
                '</td>' +
                '<td>' + (item.format   || '—') + '</td>' +
                '<td>' + (item.episodes || '?') + '</td>' +
                '<td>' + (item.popularity || 0) + '</td>' +
                '<td>' + imp + '</td>' +
                '</tr>';
        } );
        if ( rankingPage === 1 ) {
            $( '#ranking-tbody' ).html( html );
        } else {
            $( '#ranking-tbody' ).append( html );
        }
    }

    $( '#ranking-select-all' ).on( 'change', function() {
        $( '.ranking-item-check' ).prop( 'checked', $( this ).is(':checked') );
    } );

    $( '#btn-ranking-import' ).on( 'click', function() {
        var ids = [];
        $( '.ranking-item-check:checked' ).each( function() { ids.push( parseInt( $( this ).val() ) ); } );
        if ( !ids.length ) { alert('請至少選擇一部'); return; }
        rankingStop.value = false;
        $( '#btn-ranking-import' ).prop('disabled',true);
        $( '#btn-ranking-stop' ).show();
        $( '#ranking-progress-wrap' ).show();
        $( '#ranking-import-log' ).empty();
        throttledImport( ids, function(id,done) {
            $.post( AJAX_URL, { action:'anime_sync_import_single', anilist_id:id, nonce:NONCE } )
            .done( function(res) {
                var label=(res.data&&res.data.title)?res.data.title:id;
                var msg=(res.data&&res.data.message)?res.data.message:'Done';
                if ( res.success ) {
                    appendLog( '#ranking-import-log', label+': '+msg,
                        res.data.bangumi_missing?'warning':(res.data.skipped?'skip':'success') );
                } else { appendLog('#ranking-import-log', id+': ❌ '+msg, 'error'); }
            } ).fail( function() { appendLog('#ranking-import-log', id+': 網路錯誤', 'error'); } )
            .always( done );
        }, '#ranking-import-log','#ranking-progress-bar','#ranking-progress-text','#ranking-throttle-notice', rankingStop,
        function() {
            $( '#btn-ranking-import' ).prop('disabled',false); $( '#btn-ranking-stop' ).hide();
            appendLog('#ranking-import-log','── 匯入完成 ──','info');
        } );
    } );
    $( '#btn-ranking-stop' ).on( 'click', function() { rankingStop.value = true; } );

} );
</script>

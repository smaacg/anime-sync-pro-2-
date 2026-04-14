<?php
/**
 * 檔案名稱: admin/pages/import-tool.php
 * 功能：動畫匯入工具頁面
 *
 * ACA – appendLog 新增 warning 類型（橙色）
 *       季度批次 / ID 批次 .done() 改讀 res.data.message 原文
 *       當 res.data.bangumi_missing === true 時改用 warning 顏色
 *       批次 ID 匯入成功也改顯示 message 而非硬字串
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cn_converter    = new Anime_Sync_CN_Converter();
$converter_stats = $cn_converter->get_stats();
?>

<div class="wrap anime-sync-import-tool">
    <h1>匯入動畫工具</h1>

    <div class="notice notice-info inline" style="margin: 20px 0; border-left-color: #2271b1;">
        <p>
            <strong>🔍 繁簡轉換器狀態：</strong>
            詞條總數 <code style="background: #fff; padding: 2px 5px;"><?php echo number_format($converter_stats['entry_count']); ?></code> 條 | 
            狀態: <?php echo $converter_stats['loaded'] ? '<span style="color:green; font-weight:bold;">✓ 運作正常</span>' : '<span style="color:red; font-weight:bold;">❌ 字典載入失敗</span>'; ?>
            <?php if ($converter_stats['loaded']): ?>
                | <small>測試：「脚本」→ 「<?php echo esc_html( $cn_converter->convert('脚本') ); ?>」</small>
            <?php endif; ?>
        </p>
    </div>

    <h2 class="nav-tab-wrapper">
        <a href="#single" class="nav-tab nav-tab-active" data-tab="single">單筆匯入</a>
        <a href="#season" class="nav-tab" data-tab="season">季度批次匯入</a>
        <a href="#batch" class="nav-tab" data-tab="batch">ID 清單匯入</a>
    </h2>

    <!-- ── TAB 1：單筆匯入 ──────────────────────────────────────────── -->
    <div id="tab-single" class="anime-sync-tab-content" style="display:block;">
        <div class="card" style="max-width: 800px; margin-top: 20px;">
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
                    <td>
                        <label><input type="checkbox" id="single-force-update"> 強制更新 (若已存在則覆蓋資料)</label>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" id="btn-single-import" class="button button-primary button-large">開始匯入</button>
            </p>
            <div id="single-import-result" style="margin-top:20px; padding:15px; display:none; border-radius:4px;"></div>
        </div>
    </div>

    <!-- ── TAB 2：季度批次匯入 ──────────────────────────────────────── -->
    <div id="tab-season" class="anime-sync-tab-content" style="display:none;">
        <div class="card" style="margin-top: 20px;">
            <h3>按季度篩選</h3>
            <div class="flex-filters" style="display:flex; gap:15px; align-items: flex-end; margin-bottom:20px;">
                <div>
                    <label>年份</label><br>
                    <select id="season-year-select">
                        <?php for($y = date('Y')+1; $y >= 2000; $y--) echo "<option value='$y'>$y</option>"; ?>
                    </select>
                </div>
                <div>
                    <label>季節</label><br>
                    <select id="season-select">
                        <option value="WINTER">冬季 (1-3月)</option>
                        <option value="SPRING">春季 (4-6月)</option>
                        <option value="SUMMER">夏季 (7-9月)</option>
                        <option value="FALL">秋季 (10-12月)</option>
                    </select>
                </div>
                <div>
                    <button type="button" id="btn-season-query" class="button">第一步：查詢季度清單</button>
                </div>
            </div>

            <div id="season-preview" style="display:none;">
                <p id="season-preview-summary" class="description"></p>
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; margin-bottom: 15px;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td id="cb" class="manage-column column-cb check-column">
                                    <input id="season-select-all" type="checkbox" checked>
                                </td>
                                <th>ID</th>
                                <th>名稱 (Romaji)</th>
                                <th>格式</th>
                                <th>集數</th>
                                <th>人氣</th>
                                <th>狀態</th>
                            </tr>
                        </thead>
                        <tbody id="season-anime-tbody"></tbody>
                    </table>
                </div>
                <button type="button" id="btn-season-import" class="button button-primary">第二步：開始批次匯入選中項</button>
                <button type="button" id="btn-season-stop" class="button" style="display:none; color:red;">停止匯入</button>

                <div id="season-progress-wrap" style="margin-top:20px; display:none;">
                    <div style="background:#eee; height:20px; border-radius:10px; overflow:hidden;">
                        <div id="season-progress-bar" style="background:#2271b1; width:0%; height:100%; transition: width 0.3s;"></div>
                    </div>
                    <p id="season-progress-text" style="text-align:center; font-weight:bold;"></p>
                    <div id="season-import-log" style="background:#000; color:#0f0; padding:10px; height:200px; overflow-y:auto; font-family:monospace; font-size:12px; margin-top:10px; border-radius:5px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 3：ID 清單批次匯入 ───────────────────────────────────── -->
    <div id="tab-batch" class="anime-sync-tab-content" style="display:none;">
        <div class="card" style="margin-top: 20px;">
            <h3>大量 ID 清單匯入</h3>
            <p class="description">請輸入 AniList ID，用換行或逗號隔開。</p>
            <textarea id="batch-id-list" rows="8" class="large-text" placeholder="例如:&#10;1&#10;2, 3&#10;4"></textarea>
            <p id="batch-id-count">0 個 ID</p>
            <p>
                <label><input type="checkbox" id="batch-force-update"> 強制更新 (跳過已存在的檢查)</label>
            </p>
            <p>
                <button type="button" id="btn-batch-import" class="button button-primary">開始批次匯入</button>
                <button type="button" id="btn-batch-stop" class="button" style="display:none; color:red;">停止</button>
            </p>

            <div id="batch-progress-wrap" style="margin-top:20px; display:none;">
                <div style="background:#eee; height:20px; border-radius:10px; overflow:hidden;">
                    <div id="batch-progress-bar" style="background:#2271b1; width:0%; height:100%;"></div>
                </div>
                <p id="batch-progress-text" style="text-align:center; font-weight:bold;"></p>
                <div id="batch-import-log" style="background:#000; color:#0f0; padding:10px; height:250px; overflow-y:auto; font-family:monospace; font-size:12px; margin-top:10px; border-radius:5px;"></div>
            </div>
        </div>
    </div>
</div>

<style>
/* ── 日誌顏色 ─────────────────────────────────────── */
.log-success { color: #0f0; }
.log-warning { color: #f90; font-weight: bold; }   /* ACA：Bangumi 未找到 */
.log-error   { color: #f33; }
.log-skip    { color: #aaa; }
.log-info    { color: #3df; border-bottom: 1px solid #333; padding-bottom: 2px; margin-bottom: 5px; }

/* ── 卡片 ─────────────────────────────────────────── */
.anime-sync-tab-content .card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

/* ── 單筆結果區塊 ─────────────────────────────────── */
#single-import-result.success { background: #edfaef; border: 1px solid #46b450; color: #235926; }
#single-import-result.warning { background: #fff8e5; border: 1px solid #d97706; color: #7a4b00; }
#single-import-result.error   { background: #fcf0f1; border: 1px solid #dc3232; color: #a42821; }
</style>

<script>
jQuery( function( $ ) {

    /* ── Tab 切換 ────────────────────────────────────────────────── */
    function switchTab( tabId ) {
        $( '.anime-sync-import-tool .nav-tab' ).removeClass( 'nav-tab-active' );
        $( '.nav-tab[data-tab="' + tabId + '"]' ).addClass( 'nav-tab-active' );
        $( '.anime-sync-tab-content' ).hide();
        $( '#tab-' + tabId ).show();
    }

    $( document ).on( 'click', '.anime-sync-import-tool .nav-tab', function( e ) {
        e.preventDefault();
        switchTab( $( this ).data( 'tab' ) );
        window.location.hash = $( this ).data( 'tab' );
    } );

    var hash = window.location.hash.replace( '#', '' );
    if ( hash && $( '.nav-tab[data-tab="' + hash + '"]' ).length > 0 ) {
        switchTab( hash );
    } else {
        switchTab( 'single' );
    }

    /* ── ID 清單字數統計 ──────────────────────────────────────────── */
    $( '#batch-id-list' ).on( 'input', function() {
        var ids = $( this ).val()
            .split( /[\n,]+/ )
            .map( function( s ) { return s.trim(); } )
            .filter( function( s ) { return /^\d+$/.test( s ); } );
        $( '#batch-id-count' ).text( ids.length + ' 個 ID' );
    } );

    /* ── 共用 log 寫入 ───────────────────────────────────────────── */
    /**
     * ACA：新增 warning 類型（橙色），用於 Bangumi ID 未找到但匯入成功的情況。
     * @param {string} selector  日誌容器的 jQuery selector
     * @param {string} text      顯示文字
     * @param {string} type      success | warning | error | skip | info
     */
    function appendLog( selector, text, type ) {
        var line = '<div class="log-' + ( type || 'info' ) + '">[' +
            new Date().toLocaleTimeString() + '] ' + text + '</div>';
        var $log = $( selector );
        $log.append( line ).scrollTop( $log[0].scrollHeight );
    }

    /* ── TAB 1 — 單筆匯入 ───────────────────────────────────────── */
    $( '#btn-single-import' ).on( 'click', function() {
        var id = $.trim( $( '#single-anilist-id' ).val() );
        if ( ! id || isNaN( id ) || parseInt( id, 10 ) <= 0 ) {
            alert( '請輸入有效的 AniList ID' );
            return;
        }

        var $btn    = $( this ).prop( 'disabled', true ).text( '匯入中…' );
        var $result = $( '#single-import-result' ).hide().removeClass( 'success warning error' );

        $.post( ajaxurl, {
            action:       'anime_sync_import_single',
            anilist_id:   id,
            force_update: $( '#single-force-update' ).is( ':checked' ) ? 1 : 0,
            nonce:        animeSyncAdmin.nonce
        } )
        .done( function( res ) {
            if ( res.success ) {
                // ACA：若 bangumi_missing，改用橙色警告框
                var isMissing  = res.data.bangumi_missing === true;
                var cssClass   = isMissing ? 'warning' : 'success';
                var icon       = isMissing ? '⚠️' : '✅';
                $result.addClass( cssClass ).html(
                    '<strong>' + icon + ' ' + res.data.message + '</strong>' +
                    ( res.data.edit_url
                        ? ' <br><a href="' + res.data.edit_url + '" class="button" target="_blank" style="margin-top:10px;">前往編輯動畫內容</a>'
                        : '' )
                );
            } else {
                $result.addClass( 'error' ).html(
                    '<strong>❌ ' + ( res.data.message || '匯入失敗' ) + '</strong>'
                );
            }
        } )
        .fail( function() {
            $result.addClass( 'error' ).html(
                '<strong>❌ 網路或 PHP 發生錯誤，請檢查後台錯誤日誌。</strong>'
            );
        } )
        .always( function() {
            $btn.prop( 'disabled', false ).text( '開始匯入' );
            $result.show();
        } );
    } );

    /* ── TAB 2 — 季度批次匯入 ──────────────────────────────────── */
    var seasonStop = false;

    $( '#btn-season-query' ).on( 'click', function() {
        var season = $( '#season-select' ).val();
        var year   = $( '#season-year-select' ).val();
        var $btn   = $( this ).prop( 'disabled', true ).text( '查詢中…' );
        $( '#season-preview' ).hide();

        $.post( ajaxurl, {
            action: 'anime_sync_query_season',
            season: season,
            year:   year,
            nonce:  animeSyncAdmin.nonce
        } )
        .done( function( res ) {
            if ( res.success && res.data.list ) {
                renderSeasonTable( res.data.list );
                $( '#season-preview' ).show();
                $( '#btn-season-import' ).prop( 'disabled', false );
                $( '#season-preview-summary' ).text( '共找到 ' + res.data.list.length + ' 部。' );
            } else {
                alert( res.data.message || '查詢失敗' );
            }
        } )
        .always( function() {
            $btn.prop( 'disabled', false ).text( '第一步：查詢季度清單' );
        } );
    } );

    function renderSeasonTable( list ) {
        var html = '';
        $.each( list, function( i, item ) {
            html += '<tr>' +
                '<td><input type="checkbox" class="season-item-check" value="' + item.anilist_id + '" checked></td>' +
                '<td>' + item.anilist_id + '</td>' +
                '<td>' + ( item.title_romaji || '-' ) + '</td>' +
                '<td>' + ( item.format       || '-' ) + '</td>' +
                '<td>' + ( item.episodes     || '?' ) + '</td>' +
                '<td>' + ( item.popularity   || 0   ) + '</td>' +
                '<td>' + ( item.status       || '-' ) + '</td>' +
                '</tr>';
        } );
        $( '#season-anime-tbody' ).html( html );
    }

    $( '#season-select-all' ).on( 'change', function() {
        $( '.season-item-check' ).prop( 'checked', $( this ).is( ':checked' ) );
    } );

    $( '#btn-season-import' ).on( 'click', function() {
        var ids = [];
        $( '.season-item-check:checked' ).each( function() {
            ids.push( parseInt( $( this ).val(), 10 ) );
        } );
        if ( ids.length === 0 ) { alert( '請至少選擇一部動畫' ); return; }

        seasonStop = false;
        $( '#btn-season-import' ).prop( 'disabled', true );
        $( '#btn-season-stop' ).show();
        $( '#season-progress-wrap' ).show();
        $( '#season-import-log' ).empty();

        var total = ids.length, current = 0;

        function importNext() {
            if ( seasonStop || current >= total ) {
                $( '#btn-season-import' ).prop( 'disabled', false );
                $( '#btn-season-stop' ).hide();
                appendLog( '#season-import-log', '── 匯入完成 ──', 'info' );
                return;
            }

            var id = ids[ current ];

            $.post( ajaxurl, {
                action:     'anime_sync_import_single',
                anilist_id: id,
                nonce:      animeSyncAdmin.nonce
            } )
            .done( function( res ) {
                var label = res.data && res.data.title ? res.data.title : id;
                var msg   = res.data && res.data.message ? res.data.message : 'Done';

                if ( res.success ) {
                    if ( res.data.skipped ) {
                        // 已存在，跳過
                        appendLog( '#season-import-log', label + ': ' + msg, 'skip' );
                    } else if ( res.data.bangumi_missing === true ) {
                        // ACA：匯入成功但 Bangumi ID 未找到 → 橙色警告
                        appendLog( '#season-import-log', label + ': ' + msg, 'warning' );
                    } else {
                        // 完全成功
                        appendLog( '#season-import-log', label + ': ' + msg, 'success' );
                    }
                } else {
                    appendLog( '#season-import-log', label + ': ' + msg, 'error' );
                }
            } )
            .fail( function() {
                appendLog( '#season-import-log', id + ': 網路錯誤', 'error' );
            } )
            .always( function() {
                current++;
                var pct = Math.round( ( current / total ) * 100 );
                $( '#season-progress-bar' ).css( 'width', pct + '%' );
                $( '#season-progress-text' ).text( current + ' / ' + total );
                setTimeout( importNext, 300 );
            } );
        }

        importNext();
    } );

    $( '#btn-season-stop' ).on( 'click', function() { seasonStop = true; } );

    /* ── TAB 3 — ID 清單批次匯入 ────────────────────────────────── */
    var batchStop = false;

    $( '#btn-batch-import' ).on( 'click', function() {
        var ids = $( '#batch-id-list' ).val()
            .split( /[\n,]+/ )
            .map( function( s ) { return parseInt( s.trim(), 10 ); } )
            .filter( function( n ) { return n > 0; } );

        if ( ids.length === 0 ) { alert( '請輸入至少一個有效 ID' ); return; }

        batchStop = false;
        $( '#btn-batch-import' ).prop( 'disabled', true );
        $( '#btn-batch-stop' ).show();
        $( '#batch-progress-wrap' ).show();
        $( '#batch-import-log' ).empty();

        var total = ids.length, current = 0, success = 0, warn = 0, skip = 0, fail = 0;

        function batchNext() {
            if ( batchStop || current >= total ) {
                $( '#btn-batch-import' ).prop( 'disabled', false );
                $( '#btn-batch-stop' ).hide();
                // ACA：完成摘要加入 warn 計數
                appendLog( '#batch-import-log',
                    '── 完成：✅ 成功 ' + success +
                    ' / ⚠️ Bangumi缺失 ' + warn +
                    ' / ⏭ 跳過 ' + skip +
                    ' / ❌ 失敗 ' + fail + ' ──',
                    'info'
                );
                return;
            }

            var id = ids[ current ];

            $.post( ajaxurl, {
                action:       'anime_sync_import_single',
                anilist_id:   id,
                force_update: $( '#batch-force-update' ).is( ':checked' ) ? 1 : 0,
                nonce:        animeSyncAdmin.nonce
            } )
            .done( function( res ) {
                var label = res.data && res.data.title   ? res.data.title   : id;
                var msg   = res.data && res.data.message ? res.data.message : 'Done';

                if ( res.success ) {
                    if ( res.data.skipped ) {
                        skip++;
                        appendLog( '#batch-import-log', label + ': ' + msg, 'skip' );
                    } else if ( res.data.bangumi_missing === true ) {
                        // ACA：匯入成功但 Bangumi ID 缺失 → 橙色
                        warn++;
                        appendLog( '#batch-import-log', label + ': ' + msg, 'warning' );
                    } else {
                        success++;
                        appendLog( '#batch-import-log', label + ': ' + msg, 'success' );
                    }
                } else {
                    fail++;
                    appendLog( '#batch-import-log', id + ': ❌ ' + msg, 'error' );
                }
            } )
            .fail( function() {
                fail++;
                appendLog( '#batch-import-log', id + ': ❌ 網路錯誤', 'error' );
            } )
            .always( function() {
                current++;
                $( '#batch-progress-bar' ).css( 'width', Math.round( ( current / total ) * 100 ) + '%' );
                $( '#batch-progress-text' ).text( current + ' / ' + total );
                setTimeout( batchNext, 300 );
            } );
        }

        batchNext();
    } );

    $( '#btn-batch-stop' ).on( 'click', function() { batchStop = true; } );

} );
</script>

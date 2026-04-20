/**
 * Admin JavaScript
 *
 * File: admin/assets/js/admin.js
 * @package Anime_Sync_Pro
 */

/* global jQuery, ajaxurl, animeSyncAdmin */
( function ( $ ) {
    'use strict';

    $( function () {

        /* ═══════════════════════════════════════════════════════════
           UTILITIES
        ══════════════════════════════════════════════════════════ */

        function logLine( $log, text, type ) {
            type = type || 'info';
            const ts    = new Date().toLocaleTimeString( 'zh-TW', { hour12: false } );
            const $line = $( '<div>' )
                .addClass( 'log-' + type )
                .text( '[' + ts + '] ' + text );
            $log.append( $line );
            $log.scrollTop( $log[ 0 ].scrollHeight );
        }

        function updateProgress( $bar, $text, done, total ) {
            const pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
            $bar.css( 'width', pct + '%' );
            $text.text( done + ' / ' + total + '  (' + pct + '%)' );
        }

        function delay( ms ) {
            return new Promise( function ( resolve ) {
                setTimeout( resolve, ms );
            } );
        }

        function ajaxPost( data ) {
            data.nonce = animeSyncAdmin.nonce;
            return $.post( ajaxurl, data );
        }

        function parseIdList( raw ) {
            return raw
                .split( /[\n,\s]+/ )
                .map( function ( s ) { return parseInt( $.trim( s ), 10 ); } )
                .filter( function ( n ) { return ! isNaN( n ) && n > 0; } )
                .filter( function ( n, i, arr ) { return arr.indexOf( n ) === i; } );
        }

        function escHtml( str ) {
            return String( str )
                .replace( /&/g,  '&amp;'  )
                .replace( /</g,  '&lt;'   )
                .replace( />/g,  '&gt;'   )
                .replace( /"/g,  '&quot;' )
                .replace( /'/g,  '&#39;'  );
        }

        /* ═══════════════════════════════════════════════════════════
           TAB SWITCHING
        ══════════════════════════════════════════════════════════ */

        $( document ).on( 'click', '.anime-sync-import-tool .nav-tab', function ( e ) {
            e.preventDefault();
            const tab = $( this ).data( 'tab' );
            $( '.anime-sync-import-tool .nav-tab' ).removeClass( 'nav-tab-active' );
            $( this ).addClass( 'nav-tab-active' );
            $( '.anime-sync-tab-content' ).hide();
            $( '#tab-' + tab ).show();
        } );

        /* ═══════════════════════════════════════════════════════════
           SINGLE IMPORT
        ══════════════════════════════════════════════════════════ */

        $( '#btn-single-import' ).on( 'click', function () {
            const anilistId   = $.trim( $( '#single-anilist-id' ).val() );
            const forceUpdate = $( '#single-force-update' ).is( ':checked' ) ? 1 : 0;
            const $btn        = $( this );
            const $result     = $( '#single-import-result' );

            if ( ! anilistId || isNaN( anilistId ) || parseInt( anilistId ) < 1 ) {
                $result
                    .removeClass( 'success' )
                    .addClass( 'error' )
                    .html( animeSyncAdmin.i18n.invalid_id || '請輸入有效的 AniList ID。' )
                    .show();
                return;
            }

            $btn.prop( 'disabled', true )
                .text( animeSyncAdmin.i18n.importing || '匯入中…' );
            $result.hide().removeClass( 'success error' );

            ajaxPost( {
                action     : 'anime_sync_import_single',
                anilist_id : parseInt( anilistId ),
                force      : forceUpdate,
            } ).done( function ( resp ) {
                if ( resp.success ) {
                    const d = resp.data;
                    let html = '<strong>✓ ' + ( d.title || '' ) + '</strong><br>';
                    if ( d.post_id ) {
                        html += '<a href="' + d.edit_url + '" target="_blank">'
                             + ( animeSyncAdmin.i18n.edit_post || '編輯文章' )
                             + ' #' + d.post_id + '</a>';
                    }
                    if ( d.bangumi_pending ) {
                        html += '<br><span style="color:#f0a500;">⚠ '
                             + ( animeSyncAdmin.i18n.bangumi_pending
                                 || 'Bangumi ID 未能自動解析，請至審核佇列手動填寫。' )
                             + '</span>';
                    }
                    if ( d.errors && d.errors.length ) {
                        html += '<br><small style="color:#dc3232;">'
                             + d.errors.join( '；' ) + '</small>';
                    }
                    $result.addClass( 'success' ).html( html ).show();
                } else {
                    $result
                        .addClass( 'error' )
                        .html( '✗ ' + ( resp.data || animeSyncAdmin.i18n.import_failed || '匯入失敗。' ) )
                        .show();
                }
            } ).fail( function () {
                $result
                    .addClass( 'error' )
                    .html( animeSyncAdmin.i18n.network_error || '網路錯誤，請重試。' )
                    .show();
            } ).always( function () {
                $btn.prop( 'disabled', false )
                    .text( animeSyncAdmin.i18n.start_import || '開始匯入' );
            } );
        } );

        /* ═══════════════════════════════════════════════════════════
           SEASON BATCH IMPORT
        ══════════════════════════════════════════════════════════ */

        let seasonAnimeList  = [];
        let seasonImportStop = false;

        $( '#btn-season-query' ).on( 'click', function () {
            const season     = $( '#season-select' ).val();
            const year       = parseInt( $( '#season-year-select' ).val() );
            const formats    = $( 'input[name="season-format[]"]:checked' )
                                 .map( function () { return $( this ).val(); } ).get();
            const $btn       = $( this );
            const $btnImport = $( '#btn-season-import' );

            if ( ! formats.length ) {
                alert( animeSyncAdmin.i18n.select_format || '請至少選擇一種格式。' );
                return;
            }

            $btn.prop( 'disabled', true )
                .text( animeSyncAdmin.i18n.querying || '查詢中…' );
            $btnImport.prop( 'disabled', true );
            $( '#season-preview' ).hide();
            $( '#season-progress-wrap' ).hide();
            seasonAnimeList = [];

            ajaxPost( {
                action  : 'anime_sync_query_season_ids',
                season  : season,
                year    : year,
                formats : formats,
            } ).done( function ( resp ) {
                if ( resp.success && resp.data.anime_list ) {
                    seasonAnimeList = resp.data.anime_list;
                    renderSeasonTable( seasonAnimeList );
                    $btnImport.prop( 'disabled', false );
                } else {
                    alert( resp.data || animeSyncAdmin.i18n.query_failed || '查詢失敗。' );
                }
            } ).fail( function () {
                alert( animeSyncAdmin.i18n.network_error || '網路錯誤。' );
            } ).always( function () {
                $btn.prop( 'disabled', false )
                    .text( animeSyncAdmin.i18n.query_season || '第一步：查詢季度動漫清單' );
            } );
        } );

        function renderSeasonTable( list ) {
            const skipExisting  = $( '#season-skip-existing' ).is( ':checked' );
            const minPopularity = $( '#season-min-popularity' ).is( ':checked' );
            const $tbody        = $( '#season-anime-tbody' ).empty();
            let shown = 0;

            list.forEach( function ( anime ) {
                if ( skipExisting  && anime.exists )              { return; }
                if ( minPopularity && anime.popularity <= 500 )   { return; }
                shown++;

                const existsBadge = anime.exists
                    ? ' <span style="color:#0073aa;font-size:11px;">[已存在]</span>'
                    : '';

                $tbody.append(
                    $( '<tr>' )
                        .attr( 'data-anilist-id', anime.id )
                        .html(
                            '<td><input type="checkbox" class="season-item-check" '
                            + 'value="' + anime.id + '" '
                            + ( anime.exists ? '' : 'checked' )
                            + ' /></td>'
                            + '<td>' + anime.id + '</td>'
                            + '<td>' + escHtml( anime.title ) + existsBadge + '</td>'
                            + '<td>' + escHtml( anime.format || '—' ) + '</td>'
                            + '<td>' + ( anime.episodes || '?' ) + '</td>'
                            + '<td>' + ( anime.popularity || 0 ) + '</td>'
                            + '<td>' + escHtml( anime.status || '—' ) + '</td>'
                        )
                );
            } );

            $( '#season-preview-summary' ).text(
                ( animeSyncAdmin.i18n.found_count || '共找到 {n} 部，顯示 {s} 部' )
                    .replace( '{n}', list.length )
                    .replace( '{s}', shown )
            );
            $( '#season-preview' ).show();
        }

        $( document ).on( 'change', '#season-select-all', function () {
            $( '.season-item-check' ).prop( 'checked', this.checked );
        } );

        $( '#btn-season-import' ).on( 'click', async function () {
            const selectedIds = $( '.season-item-check:checked' )
                .map( function () { return parseInt( $( this ).val() ); } )
                .get();

            if ( ! selectedIds.length ) {
                alert( animeSyncAdmin.i18n.select_anime || '請勾選至少一部動漫。' );
                return;
            }

            seasonImportStop = false;
            const $btnImport = $( this );
            const $btnStop   = $( '#btn-season-stop' );
            const $btnQuery  = $( '#btn-season-query' );
            const $bar       = $( '#season-progress-bar' );
            const $text      = $( '#season-progress-text' );
            const $log       = $( '#season-import-log' );

            $btnImport.prop( 'disabled', true );
            $btnQuery.prop( 'disabled', true );
            $btnStop.show().prop( 'disabled', false )
                    .text( animeSyncAdmin.i18n.stop || '停止' );
            $( '#season-progress-wrap' ).show();
            $log.empty();
            updateProgress( $bar, $text, 0, selectedIds.length );

            let done = 0;

            for ( const anilistId of selectedIds ) {
                if ( seasonImportStop ) {
                    logLine( $log, animeSyncAdmin.i18n.import_stopped || '已停止匯入。', 'info' );
                    break;
                }

                logLine( $log, 'AniList #' + anilistId + ' …', 'info' );

                try {
                    const resp = await $.post( ajaxurl, {
                        action     : 'anime_sync_import_single',
                        nonce      : animeSyncAdmin.nonce,
                        anilist_id : anilistId,
                        force      : 0,
                    } );

                    done++;
                    updateProgress( $bar, $text, done, selectedIds.length );

                    if ( resp.success ) {
                        const d = resp.data;
                        logLine(
                            $log,
                            '✓ ' + ( d.title || 'AniList #' + anilistId )
                            + ( d.bangumi_pending ? ' ⚠ Bangumi 待處理' : '' ),
                            'success'
                        );
                    } else {
                        logLine(
                            $log,
                            '✗ AniList #' + anilistId + '：' + ( resp.data || '未知錯誤' ),
                            'error'
                        );
                    }
                } catch ( e ) {
                    done++;
                    updateProgress( $bar, $text, done, selectedIds.length );
                    logLine( $log, '✗ AniList #' + anilistId + '：網路錯誤', 'error' );
                }

                if ( ! seasonImportStop && done < selectedIds.length ) {
                    await delay( 3200 );
                }
            }

            logLine(
                $log,
                ( animeSyncAdmin.i18n.import_done || '匯入完成。成功 {d}/{t}' )
                    .replace( '{d}', done )
                    .replace( '{t}', selectedIds.length ),
                'info'
            );

            $btnImport.prop( 'disabled', false );
            $btnQuery.prop( 'disabled', false );
            $btnStop.hide();
        } );

        $( '#btn-season-stop' ).on( 'click', function () {
            seasonImportStop = true;
            $( this ).prop( 'disabled', true )
                     .text( animeSyncAdmin.i18n.stopping || '停止中…' );
        } );

        /* ═══════════════════════════════════════════════════════════
           BATCH IMPORT (ID list)
        ══════════════════════════════════════════════════════════ */

        let batchImportStop = false;

        $( '#batch-id-list' ).on( 'input', function () {
            const ids = parseIdList( $( this ).val() );
            $( '#batch-id-count' ).text(
                ids.length + ( animeSyncAdmin.i18n.id_count_suffix || ' 個 ID' )
            );
        } );

        $( '#btn-batch-import' ).on( 'click', async function () {
            const raw = $( '#batch-id-list' ).val();
            const ids = parseIdList( raw );

            if ( ! ids.length ) {
                alert( animeSyncAdmin.i18n.no_ids || '請輸入至少一個 ID。' );
                return;
            }

            const skipExisting = $( '#batch-skip-existing' ).is( ':checked' ) ? 1 : 0;
            const forceUpdate  = $( '#batch-force-update' ).is( ':checked' )  ? 1 : 0;

            batchImportStop = false;
            const $btnStart = $( this );
            const $btnStop  = $( '#btn-batch-stop' );
            const $bar      = $( '#batch-progress-bar' );
            const $text     = $( '#batch-progress-text' );
            const $log      = $( '#batch-import-log' );

            $btnStart.prop( 'disabled', true );
            $btnStop.show().prop( 'disabled', false )
                    .text( animeSyncAdmin.i18n.stop || '停止' );
            $( '#batch-progress-wrap' ).show();
            $log.empty();
            updateProgress( $bar, $text, 0, ids.length );

            let done = 0, succeeded = 0, failed = 0, skipped = 0;

            for ( const anilistId of ids ) {
                if ( batchImportStop ) {
                    logLine( $log, animeSyncAdmin.i18n.import_stopped || '已停止匯入。', 'info' );
                    break;
                }

                logLine( $log, 'AniList #' + anilistId + ' …', 'info' );

                try {
                    const resp = await $.post( ajaxurl, {
                        action     : 'anime_sync_import_single',
                        nonce      : animeSyncAdmin.nonce,
                        anilist_id : anilistId,
                        force      : forceUpdate,
                        skip       : skipExisting,
                    } );

                    done++;
                    updateProgress( $bar, $text, done, ids.length );

                    if ( resp.success ) {
                        if ( resp.data.skipped ) {
                            skipped++;
                            logLine( $log, '→ AniList #' + anilistId + ' 已存在，略過', 'skip' );
                        } else {
                            succeeded++;
                            logLine(
                                $log,
                                '✓ ' + ( resp.data.title || 'AniList #' + anilistId )
                                + ( resp.data.bangumi_pending ? ' ⚠ Bangumi 待處理' : '' ),
                                'success'
                            );
                        }
                    } else {
                        failed++;
                        logLine(
                            $log,
                            '✗ AniList #' + anilistId + '：' + ( resp.data || '未知錯誤' ),
                            'error'
                        );
                    }
                } catch ( e ) {
                    done++;
                    failed++;
                    updateProgress( $bar, $text, done, ids.length );
                    logLine( $log, '✗ AniList #' + anilistId + '：網路錯誤', 'error' );
                }

                if ( ! batchImportStop && done < ids.length ) {
                    await delay( 3200 );
                }
            }

            logLine(
                $log,
                '完成：成功 ' + succeeded
                + '，略過 ' + skipped
                + '，失敗 ' + failed
                + '，共 ' + ids.length,
                'info'
            );

            $btnStart.prop( 'disabled', false );
            $btnStop.hide().prop( 'disabled', false )
                    .text( animeSyncAdmin.i18n.stop || '停止' );
        } );

        $( '#btn-batch-stop' ).on( 'click', function () {
            batchImportStop = true;
            $( this ).prop( 'disabled', true )
                     .text( animeSyncAdmin.i18n.stopping || '停止中…' );
        } );

        /* ═══════════════════════════════════════════════════════════
           DASHBOARD STATS
        ══════════════════════════════════════════════════════════ */

        if ( $( '#anime-sync-dashboard-stats' ).length ) {
            loadDashboardStats();
        }

        function loadDashboardStats() {
            ajaxPost( { action: 'anime_sync_get_stats' } ).done( function ( resp ) {
                if ( ! resp.success ) { return; }
                const d = resp.data;
                setStatCell( '#stat-total',       d.total_anime );
                setStatCell( '#stat-published',   d.published );
                setStatCell( '#stat-draft',       d.draft );
                setStatCell( '#stat-airing',      d.airing );
                setStatCell( '#stat-pending-bgm', d.pending_bangumi );
                setStatCell( '#stat-map-entries', d.map_entries
                    ? Number( d.map_entries ).toLocaleString() : '—' );
                setStatCell( '#stat-last-daily',  d.last_daily  || '—' );
                setStatCell( '#stat-last-weekly', d.last_weekly || '—' );
                setStatCell( '#stat-memory',      d.memory_usage );
            } );
        }

        function setStatCell( selector, value ) {
            const $el = $( selector );
            if ( $el.length ) { $el.text( value !== undefined ? value : '—' ); }
        }

        /* ── Review Queue global helper ── */
        window.animeSyncBulkAction = function ( action, postIds, callback ) {
            ajaxPost( {
                action   : 'anime_sync_bulk_action',
                bulk     : action,
                post_ids : postIds,
            } ).done( callback );
        };

        /* ═══════════════════════════════════════════════════════════
           RESYNC BANGUMI（Meta Box 按鈕）← 移回閉包內，問題修正
        ══════════════════════════════════════════════════════════ */

        $( document ).on(
            'input change',
            '#acf-field_anime_bangumi_id, input[name="acf[field_anime_bangumi_id]"]',
            function () {
                var val = parseInt( $( this ).val(), 10 );
                $( '#anime-resync-bangumi-btn' ).prop( 'disabled', ! ( val > 0 ) );
            }
        );

        $( '#anime-resync-bangumi-btn' ).on( 'click', function () {
            var $btn = $( this );
            var $msg = $( '#anime-resync-bangumi-msg' );

            var postId = $( '#post_ID' ).val()
                      || new URLSearchParams( window.location.search ).get( 'post' )
                      || '0';

            var bangumiId = $( '#acf-field_anime_bangumi_id' ).val()
                         || $( 'input[name="acf[field_anime_bangumi_id]"]' ).val()
                         || '';

            if ( ! postId || postId === '0' ) {
                $msg.css( 'color', '#d63638' )
                    .text( '請先儲存草稿以取得文章 ID，再執行同步。' );
                return;
            }

            if ( ! bangumiId || parseInt( bangumiId, 10 ) <= 0 ) {
                $msg.css( 'color', '#d63638' )
                    .text( animeSyncAdmin.error_no_id || '請先填入 Bangumi ID。' );
                return;
            }

            $btn.prop( 'disabled', true );
            $msg.css( 'color', '#666' )
                .text( animeSyncAdmin.syncing || '同步中，請稍候…' );

            $.ajax( {
                url      : ajaxurl,
                type     : 'POST',
                data     : {
                    action     : 'anime_resync_bangumi',
                    nonce      : animeSyncAdmin.nonce,
                    post_id    : postId,
                    bangumi_id : bangumiId,
                },
                dataType : 'json',
                timeout  : 60000,
            } )
            .done( function ( res ) {
                if ( res && res.success ) {
                    $msg.css( 'color', '#00a32a' )
                        .text( animeSyncAdmin.sync_success || '✅ 同步完成，頁面即將重新整理…' );
                    setTimeout( function () { location.reload(); }, 1500 );
                } else {
                    var errMsg = ( res && res.data && res.data.message )
                        ? res.data.message
                        : ( res && res.data ? res.data : '未知錯誤' );
                    $msg.css( 'color', '#d63638' ).text( '❌ ' + errMsg );
                    $btn.prop( 'disabled', false );
                }
            } )
            .fail( function ( xhr, status ) {
                var detail = status === 'timeout'
                    ? '請求逾時，請重試。'
                    : ( animeSyncAdmin.network_error || '網路錯誤，請重試。' );
                $msg.css( 'color', '#d63638' ).text( '❌ ' + detail );
                $btn.prop( 'disabled', false );
            } );
        } );

    } ); // end document.ready

} )( jQuery );

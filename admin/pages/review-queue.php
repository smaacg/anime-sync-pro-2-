<?php
/**
 * Admin Page: Review Queue
 *
 * File: admin/pages/review-queue.php
 * Displays all anime draft posts awaiting editorial review before publishing.
 * Allows bulk publish, bulk delete, individual field editing, and Bangumi
 * pending-ID resolution.
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ───────────────────────────────────────────────
   Collect filter params
─────────────────────────────────────────────── */
$filter_status   = isset( $_GET['filter_status'] )  ? sanitize_text_field( $_GET['filter_status'] )  : 'draft';
$filter_season   = isset( $_GET['filter_season'] )  ? sanitize_text_field( $_GET['filter_season'] )  : '';
$filter_year     = isset( $_GET['filter_year'] )    ? (int) $_GET['filter_year']                     : 0;
$filter_pending  = isset( $_GET['filter_pending'] ) ? (bool) $_GET['filter_pending']                 : false;
$paged           = max( 1, isset( $_GET['paged'] )  ? (int) $_GET['paged']                           : 1 );
$per_page        = 20;

/* ───────────────────────────────────────────────
   Build WP_Query args
─────────────────────────────────────────────── */
$query_args = [
    'post_type'      => 'anime',
    'post_status'    => in_array( $filter_status, [ 'draft', 'publish', 'any' ], true )
                            ? $filter_status : 'draft',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [],
];

if ( $filter_season ) {
    $query_args['meta_query'][] = [
        'key'     => 'anime_season',
        'value'   => strtoupper( $filter_season ),
        'compare' => '=',
    ];
}
if ( $filter_year > 0 ) {
    $query_args['meta_query'][] = [
        'key'     => 'anime_season_year',
        'value'   => $filter_year,
        'compare' => '=',
        'type'    => 'NUMERIC',
    ];
}
if ( $filter_pending ) {
    $query_args['meta_query'][] = [
        'key'     => '_bangumi_id_pending',
        'value'   => '1',
        'compare' => '=',
    ];
}
if ( ! empty( $query_args['meta_query'] ) && count( $query_args['meta_query'] ) > 1 ) {
    $query_args['meta_query']['relation'] = 'AND';
}

$anime_query  = new WP_Query( $query_args );
$total_posts  = $anime_query->found_posts;
$total_pages  = $anime_query->max_num_pages;

/* ───────────────────────────────────────────────
   Pending Bangumi count (for notice badge)
─────────────────────────────────────────────── */
$pending_count = (int) ( new WP_Query( [
    'post_type'      => 'anime',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [ [ 'key' => '_bangumi_id_pending', 'value' => '1', 'compare' => '=' ] ],
] ) )->found_posts;

$current_year  = (int) date( 'Y' );
$years         = range( $current_year + 1, 2000 );
$seasons       = [ 'WINTER', 'SPRING', 'SUMMER', 'FALL' ];
?>

<div class="wrap anime-sync-review-queue">

    <h1 class="wp-heading-inline">
        <?php esc_html_e( '審核佇列', 'anime-sync-pro' ); ?>
    </h1>

    <?php if ( $pending_count > 0 ) : ?>
        <span class="anime-sync-badge" style="
            background:#dc3232;color:#fff;border-radius:10px;
            padding:2px 8px;font-size:12px;margin-left:8px;vertical-align:middle;">
            <?php printf( esc_html__( '%d 筆 Bangumi ID 待處理', 'anime-sync-pro' ), $pending_count ); ?>
        </span>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- ───────────────── Filter Bar ───────────────── -->
    <form method="get" class="anime-sync-filter-form" style="margin:16px 0;">
        <input type="hidden" name="page" value="anime-sync-queue" />

        <select name="filter_status">
            <option value="draft"   <?php selected( $filter_status, 'draft' );   ?>>
                <?php esc_html_e( '草稿', 'anime-sync-pro' ); ?>
            </option>
            <option value="publish" <?php selected( $filter_status, 'publish' ); ?>>
                <?php esc_html_e( '已發佈', 'anime-sync-pro' ); ?>
            </option>
            <option value="any"     <?php selected( $filter_status, 'any' );     ?>>
                <?php esc_html_e( '全部', 'anime-sync-pro' ); ?>
            </option>
        </select>

        <select name="filter_season">
            <option value=""><?php esc_html_e( '全季節', 'anime-sync-pro' ); ?></option>
            <?php foreach ( $seasons as $s ) : ?>
                <option value="<?php echo esc_attr( $s ); ?>"
                    <?php selected( $filter_season, $s ); ?>>
                    <?php echo esc_html( $s ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="filter_year">
            <option value="0"><?php esc_html_e( '全年份', 'anime-sync-pro' ); ?></option>
            <?php foreach ( $years as $y ) : ?>
                <option value="<?php echo esc_attr( $y ); ?>"
                    <?php selected( $filter_year, $y ); ?>>
                    <?php echo esc_html( $y ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label style="margin:0 8px;">
            <input type="checkbox" name="filter_pending" value="1"
                <?php checked( $filter_pending ); ?> />
            <?php esc_html_e( '僅顯示 Bangumi 待處理', 'anime-sync-pro' ); ?>
        </label>

        <button type="submit" class="button">
            <?php esc_html_e( '篩選', 'anime-sync-pro' ); ?>
        </button>

        <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-queue' ) ); ?>"
           class="button button-link">
            <?php esc_html_e( '重設', 'anime-sync-pro' ); ?>
        </a>

        <span style="margin-left:16px;color:#777;">
            <?php printf(
                esc_html__( '共 %d 筆', 'anime-sync-pro' ),
                $total_posts
            ); ?>
        </span>
    </form>

    <!-- ───────────────── Bulk Actions ───────────────── -->
    <div class="anime-sync-bulk-bar" style="margin-bottom:12px;">
        <select id="bulk-action-select">
            <option value=""><?php esc_html_e( '批次動作', 'anime-sync-pro' ); ?></option>
            <option value="publish"><?php esc_html_e( '批次發佈', 'anime-sync-pro' ); ?></option>
            <option value="draft"><?php esc_html_e( '批次轉為草稿', 'anime-sync-pro' ); ?></option>
            <option value="delete"><?php esc_html_e( '批次刪除', 'anime-sync-pro' ); ?></option>
            <option value="refetch"><?php esc_html_e( '批次重新抓取資料', 'anime-sync-pro' ); ?></option>
        </select>
        <button type="button" id="btn-bulk-apply" class="button">
            <?php esc_html_e( '套用', 'anime-sync-pro' ); ?>
        </button>
        <span id="bulk-action-result" style="margin-left:12px;"></span>
    </div>

    <!-- ───────────────── Main Table ───────────────── -->
    <?php if ( $anime_query->have_posts() ) : ?>

    <table class="wp-list-table widefat fixed striped anime-sync-queue-table">
        <thead>
            <tr>
                <td class="manage-column column-cb check-column">
                    <input type="checkbox" id="queue-select-all" />
                </td>
                <th style="width:70px;"><?php esc_html_e( '封面', 'anime-sync-pro' ); ?></th>
                <th><?php esc_html_e( '標題', 'anime-sync-pro' ); ?></th>
                <th style="width:90px;"><?php esc_html_e( '格式', 'anime-sync-pro' ); ?></th>
                <th style="width:110px;"><?php esc_html_e( '季度', 'anime-sync-pro' ); ?></th>
                <th style="width:80px;"><?php esc_html_e( '狀態', 'anime-sync-pro' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'AniList', 'anime-sync-pro' ); ?></th>
                <th style="width:100px;"><?php esc_html_e( 'Bangumi', 'anime-sync-pro' ); ?></th>
                <th style="width:120px;"><?php esc_html_e( '上次同步', 'anime-sync-pro' ); ?></th>
                <th style="width:180px;"><?php esc_html_e( '操作', 'anime-sync-pro' ); ?></th>
            </tr>
        </thead>
        <tbody>

        <?php while ( $anime_query->have_posts() ) :
            $anime_query->the_post();
            $post_id      = get_the_ID();
            $anilist_id   = get_post_meta( $post_id, 'anime_anilist_id',   true );
            $bangumi_id   = get_post_meta( $post_id, 'anime_bangumi_id',   true );
            $mal_id       = get_post_meta( $post_id, 'anime_mal_id',       true );
            $format       = get_post_meta( $post_id, 'anime_format',       true );
            $season       = get_post_meta( $post_id, 'anime_season',       true );
            $season_year  = get_post_meta( $post_id, 'anime_season_year',  true );
            $status       = get_post_meta( $post_id, 'anime_status',       true );
            $cover_image  = get_post_meta( $post_id, 'anime_cover_image',  true );
            $last_sync    = get_post_meta( $post_id, 'anime_last_sync',    true );
            $is_pending   = get_post_meta( $post_id, '_bangumi_id_pending', true );
            $post_status  = get_post_status( $post_id );
            $title_cn     = get_post_meta( $post_id, 'anime_title_chinese', true );
            $edit_url     = get_edit_post_link( $post_id );
            $view_url     = get_permalink( $post_id );

            $season_label = $season && $season_year
                ? esc_html( $season . ' ' . $season_year )
                : '—';

            $last_sync_label = $last_sync
                ? esc_html( date( 'Y-m-d H:i', strtotime( $last_sync ) ) )
                : esc_html__( '從未', 'anime-sync-pro' );
        ?>
            <tr data-post-id="<?php echo esc_attr( $post_id ); ?>"
                class="<?php echo $is_pending ? 'bangumi-pending' : ''; ?>">

                <!-- Checkbox -->
                <th scope="row" class="check-column">
                    <input type="checkbox" class="queue-item-checkbox"
                           value="<?php echo esc_attr( $post_id ); ?>" />
                </th>

                <!-- Cover -->
                <td>
                    <?php if ( $cover_image ) : ?>
                        <img src="<?php echo esc_url( $cover_image ); ?>"
                             alt="" style="width:48px;height:68px;object-fit:cover;border-radius:2px;" />
                    <?php else : ?>
                        <div style="width:48px;height:68px;background:#eee;border-radius:2px;
                             display:flex;align-items:center;justify-content:center;color:#999;font-size:10px;">
                            N/A
                        </div>
                    <?php endif; ?>
                </td>

                <!-- Title -->
                <td>
                    <strong>
                        <a href="<?php echo esc_url( $edit_url ); ?>">
                            <?php echo esc_html( $title_cn ?: get_the_title() ); ?>
                        </a>
                    </strong>
                    <br>
                    <span style="color:#777;font-size:12px;">
                        <?php echo esc_html( get_the_title() ); ?>
                    </span>
                    <?php if ( $is_pending ) : ?>
                        <br>
                        <span class="anime-sync-badge" style="
                            background:#f0a500;color:#fff;border-radius:3px;
                            padding:1px 5px;font-size:11px;">
                            <?php esc_html_e( 'Bangumi 待處理', 'anime-sync-pro' ); ?>
                        </span>
                    <?php endif; ?>
                </td>

                <!-- Format -->
                <td><?php echo esc_html( $format ?: '—' ); ?></td>

                <!-- Season -->
                <td><?php echo $season_label; ?></td>

                <!-- Anime Status -->
                <td>
                    <?php
                    $status_labels = [
                        'FINISHED'         => [ esc_html__( '完結', 'anime-sync-pro' ),   '#46b450' ],
                        'RELEASING'        => [ esc_html__( '播出中', 'anime-sync-pro' ), '#0073aa' ],
                        'NOT_YET_RELEASED' => [ esc_html__( '未播出', 'anime-sync-pro' ), '#888888' ],
                        'CANCELLED'        => [ esc_html__( '取消', 'anime-sync-pro' ),   '#dc3232' ],
                        'HIATUS'           => [ esc_html__( '休刊', 'anime-sync-pro' ),   '#f0a500' ],
                    ];
                    if ( $status && isset( $status_labels[ $status ] ) ) {
                        [$label, $color] = $status_labels[ $status ];
                        echo '<span style="color:' . esc_attr( $color ) . ';font-weight:600;">'
                            . esc_html( $label ) . '</span>';
                    } else {
                        echo '—';
                    }
                    ?>
                </td>

                <!-- AniList ID -->
                <td>
                    <?php if ( $anilist_id ) : ?>
                        <a href="https://anilist.co/anime/<?php echo esc_attr( $anilist_id ); ?>/"
                           target="_blank" rel="noopener">
                            <?php echo esc_html( $anilist_id ); ?>
                        </a>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <!-- Bangumi ID -->
                <td>
                    <?php if ( $bangumi_id && ! $is_pending ) : ?>
                        <a href="https://bgm.tv/subject/<?php echo esc_attr( $bangumi_id ); ?>"
                           target="_blank" rel="noopener">
                            <?php echo esc_html( $bangumi_id ); ?>
                        </a>
                    <?php elseif ( $is_pending ) : ?>
                        <!-- Inline Bangumi ID input -->
                        <div class="bangumi-id-edit" style="display:flex;gap:4px;align-items:center;">
                            <input type="number" class="bangumi-id-input small-text"
                                   placeholder="ID" min="1"
                                   value="<?php echo esc_attr( $bangumi_id ); ?>"
                                   data-post-id="<?php echo esc_attr( $post_id ); ?>"
                                   style="width:70px;" />
                            <button type="button" class="button button-small btn-save-bangumi-id"
                                    data-post-id="<?php echo esc_attr( $post_id ); ?>">
                                <?php esc_html_e( '儲存', 'anime-sync-pro' ); ?>
                            </button>
                        </div>
                    <?php else : ?>—<?php endif; ?>
                </td>

                <!-- Last Sync -->
                <td style="font-size:12px;"><?php echo $last_sync_label; ?></td>

                <!-- Actions -->
                <td>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;">

                        <?php if ( $post_status === 'draft' ) : ?>
                            <button type="button"
                                    class="button button-small btn-publish-one"
                                    data-post-id="<?php echo esc_attr( $post_id ); ?>">
                                <?php esc_html_e( '發佈', 'anime-sync-pro' ); ?>
                            </button>
                        <?php else : ?>
                            <button type="button"
                                    class="button button-small btn-unpublish-one"
                                    data-post-id="<?php echo esc_attr( $post_id ); ?>">
                                <?php esc_html_e( '轉草稿', 'anime-sync-pro' ); ?>
                            </button>
                        <?php endif; ?>

                        <button type="button"
                                class="button button-small btn-refetch-one"
                                data-post-id="<?php echo esc_attr( $post_id ); ?>"
                                data-anilist-id="<?php echo esc_attr( $anilist_id ); ?>">
                            <?php esc_html_e( '重抓', 'anime-sync-pro' ); ?>
                        </button>

                        <a href="<?php echo esc_url( $edit_url ); ?>"
                           class="button button-small">
                            <?php esc_html_e( '編輯', 'anime-sync-pro' ); ?>
                        </a>

                        <?php if ( $post_status === 'publish' ) : ?>
                            <a href="<?php echo esc_url( $view_url ); ?>"
                               class="button button-small" target="_blank" rel="noopener">
                                <?php esc_html_e( '檢視', 'anime-sync-pro' ); ?>
                            </a>
                        <?php endif; ?>

                        <button type="button"
                                class="button button-small button-link-delete btn-delete-one"
                                data-post-id="<?php echo esc_attr( $post_id ); ?>">
                            <?php esc_html_e( '刪除', 'anime-sync-pro' ); ?>
                        </button>

                    </div><!-- /actions -->
                </td>

            </tr>
        <?php endwhile; wp_reset_postdata(); ?>

        </tbody>
    </table>

    <!-- ───────────────── Pagination ───────────────── -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom" style="margin-top:12px;">
            <div class="tablenav-pages">
                <?php
                $base_url = add_query_arg( [
                    'page'           => 'anime-sync-queue',
                    'filter_status'  => $filter_status,
                    'filter_season'  => $filter_season,
                    'filter_year'    => $filter_year,
                    'filter_pending' => $filter_pending ? '1' : '',
                ], admin_url( 'admin.php' ) );

                echo paginate_links( [
                    'base'      => $base_url . '%_%',
                    'format'    => '&paged=%#%',
                    'current'   => $paged,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ] );
                ?>
            </div>
        </div>
    <?php endif; ?>

    <?php else : ?>
        <div class="anime-sync-empty-state" style="
            text-align:center;padding:60px 20px;color:#777;border:2px dashed #ddd;
            border-radius:4px;margin-top:20px;">
            <span class="dashicons dashicons-format-video"
                  style="font-size:48px;height:48px;width:48px;color:#ddd;"></span>
            <p style="font-size:16px;margin-top:12px;">
                <?php esc_html_e( '目前沒有符合條件的動畫。', 'anime-sync-pro' ); ?>
            </p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=anime-sync-import' ) ); ?>"
               class="button button-primary">
                <?php esc_html_e( '前往匯入工具', 'anime-sync-pro' ); ?>
            </a>
        </div>
    <?php endif; ?>

</div><!-- .wrap -->

<!-- ───────────────── Inline JS ───────────────── -->
<script>
( function( $ ) {
    'use strict';

    /* ── Select-all checkbox ── */
    $( '#queue-select-all' ).on( 'change', function () {
        $( '.queue-item-checkbox' ).prop( 'checked', this.checked );
    } );

    /* ── Helper: collect checked IDs ── */
    function getCheckedIds() {
        return $( '.queue-item-checkbox:checked' ).map( function () {
            return $( this ).val();
        } ).get();
    }

    /* ── Bulk apply ── */
    $( '#btn-bulk-apply' ).on( 'click', function () {
        const action  = $( '#bulk-action-select' ).val();
        const ids     = getCheckedIds();
        const $result = $( '#bulk-action-result' );

        if ( ! action ) {
            $result.text( '<?php esc_js( esc_html__( '請選擇動作', 'anime-sync-pro' ) ); ?>' );
            return;
        }
        if ( ids.length === 0 ) {
            $result.text( '<?php esc_js( esc_html__( '請勾選至少一筆', 'anime-sync-pro' ) ); ?>' );
            return;
        }
        if ( action === 'delete' &&
             ! confirm( '<?php esc_js( esc_html__( '確定要刪除選取的動畫？此操作無法復原。', 'anime-sync-pro' ) ); ?>' ) ) {
            return;
        }

        $result.text( '<?php esc_js( esc_html__( '處理中…', 'anime-sync-pro' ) ); ?>' );

        $.post( ajaxurl, {
            action  : 'anime_sync_bulk_action',
            nonce   : animeSyncAdmin.nonce,
            bulk    : action,
            post_ids: ids,
        }, function ( resp ) {
            if ( resp.success ) {
                $result.text( resp.data.message || '<?php esc_js( esc_html__( '完成', 'anime-sync-pro' ) ); ?>' );
                setTimeout( function () { location.reload(); }, 1200 );
            } else {
                $result.text( resp.data || '<?php esc_js( esc_html__( '發生錯誤', 'anime-sync-pro' ) ); ?>' );
            }
        } );
    } );

    /* ── Publish single ── */
    $( document ).on( 'click', '.btn-publish-one', function () {
        const postId = $( this ).data( 'post-id' );
        const $btn   = $( this );
        $btn.prop( 'disabled', true ).text( '…' );

        $.post( ajaxurl, {
            action  : 'anime_sync_bulk_action',
            nonce   : animeSyncAdmin.nonce,
            bulk    : 'publish',
            post_ids: [ postId ],
        }, function ( resp ) {
            if ( resp.success ) {
                $btn.closest( 'tr' ).fadeOut( 400, function () { $( this ).remove(); } );
            } else {
                $btn.prop( 'disabled', false ).text( '<?php esc_js( esc_html__( '發佈', 'anime-sync-pro' ) ); ?>' );
                alert( resp.data || '<?php esc_js( esc_html__( '失敗', 'anime-sync-pro' ) ); ?>' );
            }
        } );
    } );

    /* ── Unpublish single ── */
    $( document ).on( 'click', '.btn-unpublish-one', function () {
        const postId = $( this ).data( 'post-id' );
        const $btn   = $( this );
        $btn.prop( 'disabled', true ).text( '…' );

        $.post( ajaxurl, {
            action  : 'anime_sync_bulk_action',
            nonce   : animeSyncAdmin.nonce,
            bulk    : 'draft',
            post_ids: [ postId ],
        }, function ( resp ) {
            if ( resp.success ) { location.reload(); }
            else {
                $btn.prop( 'disabled', false )
                    .text( '<?php esc_js( esc_html__( '轉草稿', 'anime-sync-pro' ) ); ?>' );
                alert( resp.data || '<?php esc_js( esc_html__( '失敗', 'anime-sync-pro' ) ); ?>' );
            }
        } );
    } );

    /* ── Delete single ── */
    $( document ).on( 'click', '.btn-delete-one', function () {
        if ( ! confirm( '<?php esc_js( esc_html__( '確定刪除這筆動畫？', 'anime-sync-pro' ) ); ?>' ) ) return;
        const postId = $( this ).data( 'post-id' );
        const $btn   = $( this );
        $btn.prop( 'disabled', true );

        $.post( ajaxurl, {
            action  : 'anime_sync_bulk_action',
            nonce   : animeSyncAdmin.nonce,
            bulk    : 'delete',
            post_ids: [ postId ],
        }, function ( resp ) {
            if ( resp.success ) {
                $btn.closest( 'tr' ).fadeOut( 400, function () { $( this ).remove(); } );
            } else {
                $btn.prop( 'disabled', false );
                alert( resp.data || '<?php esc_js( esc_html__( '刪除失敗', 'anime-sync-pro' ) ); ?>' );
            }
        } );
    } );

    /* ── Refetch single ── */
    $( document ).on( 'click', '.btn-refetch-one', function () {
        const postId    = $( this ).data( 'post-id' );
        const anilistId = $( this ).data( 'anilist-id' );
        const $btn      = $( this );
        $btn.prop( 'disabled', true ).text( '…' );

        $.post( ajaxurl, {
            action     : 'anime_sync_import_single',
            nonce      : animeSyncAdmin.nonce,
            anilist_id : anilistId,
            post_id    : postId,
            force      : 1,
        }, function ( resp ) {
            $btn.prop( 'disabled', false )
                .text( '<?php esc_js( esc_html__( '重抓', 'anime-sync-pro' ) ); ?>' );
            if ( resp.success ) {
                $btn.closest( 'tr' ).find( 'td' ).last().prepend(
                    $( '<span>' ).css( { color:'#46b450', marginRight:'8px' } )
                                 .text( '✓' )
                );
            } else {
                alert( resp.data || '<?php esc_js( esc_html__( '重抓失敗', 'anime-sync-pro' ) ); ?>' );
            }
        } );
    } );

    /* ── Save Bangumi ID inline ── */
    $( document ).on( 'click', '.btn-save-bangumi-id', function () {
        const $btn    = $( this );
        const postId  = $btn.data( 'post-id' );
        const bgmId   = $btn.closest( '.bangumi-id-edit' )
                            .find( '.bangumi-id-input' ).val();

        if ( ! bgmId || bgmId < 1 ) {
            alert( '<?php esc_js( esc_html__( '請輸入有效的 Bangumi ID', 'anime-sync-pro' ) ); ?>' );
            return;
        }

        $btn.prop( 'disabled', true ).text( '…' );

        $.post( ajaxurl, {
            action     : 'anime_sync_save_bangumi_id',
            nonce      : animeSyncAdmin.nonce,
            post_id    : postId,
            bangumi_id : bgmId,
        }, function ( resp ) {
            if ( resp.success ) {
                $btn.closest( 'tr' ).find( '.bangumi-pending' ).remove();
                $btn.closest( 'td' ).html(
                    '<a href="https://bgm.tv/subject/' + bgmId + '" target="_blank" rel="noopener">'
                    + bgmId + '</a>'
                );
                $btn.closest( 'tr' ).removeClass( 'bangumi-pending' );
            } else {
                $btn.prop( 'disabled', false )
                    .text( '<?php esc_js( esc_html__( '儲存', 'anime-sync-pro' ) ); ?>' );
                alert( resp.data || '<?php esc_js( esc_html__( '儲存失敗', 'anime-sync-pro' ) ); ?>' );
            }
        } );
    } );

} )( jQuery );
</script>

<style>
.anime-sync-queue-table .bangumi-pending { background: #fffbea !important; }
.anime-sync-queue-table th,
.anime-sync-queue-table td { vertical-align: middle; }
.anime-sync-filter-form select,
.anime-sync-filter-form input[type="checkbox"] { vertical-align: middle; }
</style>

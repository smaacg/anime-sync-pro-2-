<?php
/**
 * Custom Post Type — 後台列表欄位與更新訊息
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Custom_Post_Type {

    /**
     * Constructor
     */
    public function __construct() {
        $post_type = 'anime';

        add_filter( "manage_{$post_type}_posts_columns",         [ $this, 'add_admin_columns'    ] );
        add_action( "manage_{$post_type}_posts_custom_column",   [ $this, 'render_admin_columns' ], 10, 2 );
        add_filter( "manage_edit-{$post_type}_sortable_columns", [ $this, 'sortable_columns'     ] );

        add_filter( 'post_updated_messages', [ $this, 'custom_messages'      ] );
        add_action( 'pre_get_posts',         [ $this, 'handle_column_sorting' ] );
        add_action( 'admin_head',            [ $this, 'add_admin_styles'      ] );
    }

    /**
     * 新增自訂欄位到後台列表
     */
    public function add_admin_columns( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( $key === 'title' ) {
                $new_columns['anime_cover']      = '封面';
                $new_columns['anime_anilist_id'] = 'AniList ID';
                $new_columns['anime_status_col'] = '狀態';
                $new_columns['anime_score']      = '評分';
                $new_columns['anime_season_col'] = '季度';
                $new_columns['anime_sync']       = '最後同步';
            }
        }
        return $new_columns;
    }

    /**
     * 渲染後台列表內容
     */
    public function render_admin_columns( string $column, int $post_id ): void {
        switch ( $column ) {

            case 'anime_cover':
                $cover_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
                if ( ! $cover_url ) {
                    $cover_url = get_post_meta( $post_id, 'anime_cover_image', true )
                              ?: get_post_meta( $post_id, 'anime_cover_url', true );
                }
                if ( $cover_url ) {
                    printf(
                        '<img src="%s" class="anime-admin-thumb" loading="lazy">',
                        esc_url( $cover_url )
                    );
                } else {
                    echo '<div class="anime-admin-thumb" style="display:flex;align-items:center;justify-content:center;font-size:9px;color:#ccc;">NO IMG</div>';
                }
                break;

            case 'anime_anilist_id':
                $id = get_post_meta( $post_id, 'anime_anilist_id', true )
                   ?: get_post_meta( $post_id, 'anime_id_anilist', true );
                if ( $id ) {
                    printf(
                        '<a href="https://anilist.co/anime/%s" target="_blank">#%s</a>',
                        esc_attr( $id ),
                        esc_html( $id )
                    );
                } else {
                    echo '<span class="na">—</span>';
                }
                break;

            case 'anime_status_col':
                $status = get_post_meta( $post_id, 'anime_status', true );
                $status_map = [
                    'FINISHED'         => [ '已完結', '#2ecc71' ],
                    'RELEASING'        => [ '連載中', '#3498db' ],
                    'NOT_YET_RELEASED' => [ '未播出', '#95a5a6' ],
                    'CANCELLED'        => [ '已取消', '#e74c3c' ],
                    'HIATUS'           => [ '休播中', '#f39c12' ],
                ];
                if ( $status && isset( $status_map[ $status ] ) ) {
                    printf(
                        '<span class="anime-status-badge" style="border-left: 3px solid %s;">%s</span>',
                        esc_attr( $status_map[ $status ][1] ),
                        esc_html( $status_map[ $status ][0] )
                    );
                } else {
                    echo '<span class="na">—</span>';
                }
                break;

            case 'anime_score':
                $score = get_post_meta( $post_id, 'anime_score_anilist', true );
                echo $score
                    ? '<strong>' . esc_html( $score ) . '</strong><small style="color:#aaa;">/100</small>'
                    : '<span class="na">—</span>';
                break;

            case 'anime_season_col':
                // Bug AW fix: normalize to uppercase before map lookup
                $season = strtoupper(
                    (string) get_post_meta( $post_id, 'anime_season', true )
                );
                $year = get_post_meta( $post_id, 'anime_season_year', true );

                $season_map = [
                    'WINTER' => '冬',
                    'SPRING' => '春',
                    'SUMMER' => '夏',
                    'FALL'   => '秋',
                ];

                echo ( $year && $season )
                    ? esc_html( $year . ' ' . ( $season_map[ $season ] ?? $season ) )
                    : '<span class="na">—</span>';
                break;

            case 'anime_sync':
                $sync = get_post_meta( $post_id, 'anime_last_sync', true );
                echo $sync
                    ? esc_html( wp_date( 'Y-m-d H:i', strtotime( $sync ) ) )
                    : '<span class="na">—</span>';
                break;
        }
    }

    public function sortable_columns( array $columns ): array {
        $columns['anime_anilist_id'] = 'anime_anilist_id';
        $columns['anime_score']      = 'anime_score_anilist';
        $columns['anime_season_col'] = 'anime_season_year';
        $columns['anime_sync']       = 'anime_last_sync';
        return $columns;
    }

    public function handle_column_sorting( $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        $orderby = $query->get( 'orderby' );

        if ( in_array( $orderby, [ 'anime_anilist_id', 'anime_score_anilist', 'anime_season_year' ], true ) ) {
            $query->set( 'meta_key', $orderby );
            $query->set( 'orderby', 'meta_value_num' );
        } elseif ( 'anime_last_sync' === $orderby ) {
            $query->set( 'meta_key', 'anime_last_sync' );
            $query->set( 'orderby', 'meta_value' );
        }
    }

    public function add_admin_styles(): void {
        $screen = get_current_screen();
        if ( isset( $screen->post_type ) && 'anime' === $screen->post_type ) {
            echo '<style>
                .column-anime_cover { width: 60px; }
                .anime-admin-thumb { width: 45px; height: 63px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; display: block; background: #f8f9fa; }
                .anime-status-badge { padding-left: 8px; font-weight: 600; font-size: 12px; }
                .na { color: #aaa; }
                .column-anime_anilist_id, .column-anime_score { width: 100px; }
            </style>';
        }
    }

    public function custom_messages( array $messages ): array {
        $post = get_post();
        if ( ! $post || $post->post_type !== 'anime' ) {
            return $messages;
        }

        $view_link = sprintf( ' <a href="%s">查看動畫</a>', esc_url( get_permalink( $post->ID ) ) );
        $messages['anime'] = [
            0  => '',
            1  => '動畫已更新。' . $view_link,
            4  => '動畫已更新。',
            6  => '動畫已發布。' . $view_link,
            7  => '動畫已儲存。',
            8  => '動畫已提交審核。',
            9  => '動畫已排程發布。',
            10 => '動畫草稿已更新。',
        ];
        return $messages;
    }
}

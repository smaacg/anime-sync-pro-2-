<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Import_Manager {

    private Anime_Sync_API_Handler $api_handler;
    private Anime_Sync_Image_Handler $image_handler;

    public function __construct( Anime_Sync_API_Handler $api_handler ) {
        $this->api_handler   = $api_handler;
        $this->image_handler = new Anime_Sync_Image_Handler();
    }

    public function import_single( int $input_id, ?int $bangumi_id = null, string $input_type = 'auto' ): array {

        $data = $this->api_handler->get_all_data_from_input( $input_id, $input_type, $bangumi_id );

        if ( isset( $data['success'] ) && ! $data['success'] ) {
            return [ 'success' => false, 'message' => $data['message'] ?? '資料取得失敗' ];
        }

        $anilist_id = $data['anime_anilist_id'] ?? 0;
        $mal_id     = $data['anime_mal_id']     ?? null;

        if ( ! $anilist_id ) {
            return [ 'success' => false, 'message' => 'AniList ID 無效' ];
        }

        // 檢查重複
        $existing = $this->find_existing( $anilist_id, $mal_id );
        if ( $existing ) {
            return [
                'success'  => true,
                'skipped'  => true,
                'message'  => '動畫已存在，略過',
                'post_id'  => $existing,
                'edit_url' => get_edit_post_link( $existing, 'raw' ),
            ];
        }

        // 建立草稿
        $title = ! empty( $data['anime_title_chinese'] )
            ? $data['anime_title_chinese']
            : ( $data['anime_title_romaji'] ?? 'Unknown' );

        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => $data['anime_synopsis_chinese'] ?? '',
            'post_type'    => 'anime',
            'post_status'  => 'draft',
        ] );

        if ( is_wp_error( $post_id ) ) {
            return [ 'success' => false, 'message' => '建立草稿失敗：' . $post_id->get_error_message() ];
        }

        // ✅ 完整寫入所有 Meta
        $this->save_post_meta( $post_id, $data );

        // ✅ 處理封面圖片
        if ( ! empty( $data['anime_cover_image'] ) ) {
            $this->image_handler->handle_cover(
                $data['anime_cover_image'],
                $data['anime_title_romaji'] ?? 'anime-' . $anilist_id,
                $post_id
            );
        }

        // ✅ 設定分類 taxonomy
        if ( ! empty( $data['_genres'] ) && is_array( $data['_genres'] ) ) {
            wp_set_object_terms( $post_id, $data['_genres'], 'anime_genre' );
        }

        return [
            'success'  => true,
            'message'  => '匯入成功：' . $title,
            'title'    => $title,
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        ];
    }

    /**
     * ✅ 完整寫入所有 anime meta 欄位
     */
    private function save_post_meta( int $post_id, array $data ): void {

        $fields = [
            // ID
            'anime_anilist_id'       => $data['anime_anilist_id']       ?? '',
            'anime_mal_id'           => $data['anime_mal_id']           ?? '',
            'bangumi_id'             => $data['bangumi_id']             ?? '',

            // 標題
            'anime_title_zh'         => $data['anime_title_chinese']    ?? '',
            'anime_title_romaji'     => $data['anime_title_romaji']     ?? '',
            'anime_title_english'    => $data['anime_title_english']    ?? '',
            'anime_title_native'     => $data['anime_title_native']     ?? '',

            // 基本資訊
            'anime_status'           => $data['anime_status']           ?? '',
            'anime_type'             => $data['anime_type']             ?? '',
            'anime_episodes'         => $data['anime_episodes']         ?? 0,
            'anime_season'           => $data['anime_season']           ?? '',
            'anime_season_year'      => $data['anime_year']             ?? 0,
            'anime_year'             => $data['anime_year']             ?? 0,

            // 評分
            'anime_score_anilist'    => $data['anime_score_anilist']    ?? 0,
            'anime_score_bangumi'    => $data['anime_score_bangumi']    ?? 0,

            // 圖片
            'anime_cover_image'      => $data['anime_cover_image']      ?? '',
            'anime_banner_image'     => $data['anime_banner_image']     ?? '',

            // 簡介
            'anime_synopsis_zh'      => $data['anime_synopsis_chinese'] ?? '',

            // Staff / Cast
            'anime_staff_json'       => $data['anime_staff_json']       ?? '[]',
            'anime_cast_json'        => $data['anime_cast_json']        ?? '[]',

            // 同步時間
            'anime_last_sync'        => current_time( 'mysql' ),
        ];

        foreach ( $fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
    }

    /**
     * 查找已存在的文章
     */
    private function find_existing( int $al_id, $mal_id ): int|false {

        // 先用 AniList ID 查
        $q = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'any',
            'meta_query'     => [ [ 'key' => 'anime_anilist_id', 'value' => $al_id ] ],
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );

        if ( $q->have_posts() ) return (int) $q->posts[0];

        // 再用舊的 key 查（相容性）
        $q2 = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'any',
            'meta_query'     => [ [ 'key' => 'anilist_id', 'value' => $al_id ] ],
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );

        return $q2->have_posts() ? (int) $q2->posts[0] : false;
    }
}

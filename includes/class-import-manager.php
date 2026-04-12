<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Import_Manager {

    private Anime_Sync_API_Handler $api_handler;
    private Anime_Sync_Image_Handler $image_handler;

    public function __construct( Anime_Sync_API_Handler $api_handler ) {
        $this->api_handler   = $api_handler;
        $this->image_handler = new Anime_Sync_Image_Handler();
    }

    // ============================================================
    // 主要匯入方法
    // ============================================================
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

        // ── 標題（繁中優先，fallback Romaji）──────────────────
        $title = ! empty( $data['anime_title_chinese'] )
            ? $data['anime_title_chinese']
            : ( $data['anime_title_romaji'] ?? 'Unknown' );

        // ── Post Slug（用 Romaji，SEO 友善）───────────────────
        $romaji = $data['anime_title_romaji'] ?? '';
        $slug   = $this->generate_slug( $romaji, $anilist_id );

        // ── 建立草稿 ──────────────────────────────────────────
        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $data['anime_synopsis_chinese'] ?? '',
            'post_type'    => 'anime',
            'post_status'  => 'draft',
        ] );

        if ( is_wp_error( $post_id ) ) {
            return [ 'success' => false, 'message' => '建立草稿失敗：' . $post_id->get_error_message() ];
        }

        // ── 寫入所有 Meta ─────────────────────────────────────
        $this->save_post_meta( $post_id, $data );

        // ── 處理封面圖片 ──────────────────────────────────────
        if ( ! empty( $data['anime_cover_image'] ) ) {
            $this->image_handler->handle_cover(
                $data['anime_cover_image'],
                $romaji ?: 'anime-' . $anilist_id,
                $post_id
            );
        }

        // ── 設定 Taxonomy ─────────────────────────────────────
        $this->save_taxonomies( $post_id, $data );

        return [
            'success'  => true,
            'message'  => '匯入成功：' . $title,
            'title'    => $title,
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        ];
    }

    // ============================================================
    // 生成 Romaji Slug
    // ============================================================
    private function generate_slug( string $romaji, int $anilist_id ): string {
        if ( empty( $romaji ) ) return 'anime-' . $anilist_id;

        // 轉小寫、移除特殊字元、空格換成 -
        $slug = strtolower( $romaji );
        $slug = preg_replace( '/[^a-z0-9\s\-]/', '', $slug );
        $slug = preg_replace( '/[\s\-]+/', '-', trim( $slug ) );
        $slug = trim( $slug, '-' );

        if ( empty( $slug ) ) return 'anime-' . $anilist_id;

        // 確保 slug 唯一性
        $original_slug = $slug;
        $counter       = 1;
        while ( $this->slug_exists( $slug ) ) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    // ============================================================
    // 檢查 Slug 是否已存在
    // ============================================================
    private function slug_exists( string $slug ): bool {
        $q = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'any',
            'name'           => $slug,
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );
        return $q->have_posts();
    }

    // ============================================================
    // 寫入所有 Meta 欄位
    // ============================================================
    private function save_post_meta( int $post_id, array $data ): void {

        $synopsis = $data['anime_synopsis_chinese'] ?? '';
        $title_zh = $data['anime_title_chinese']    ?? '';
        $bgm_id   = $data['bangumi_id']             ?? '';
        $type     = $data['anime_type']             ?? '';

        $fields = [
            // ── ID ────────────────────────────────────────────
            'anime_anilist_id'       => $data['anime_anilist_id']    ?? '',
            'anime_mal_id'           => $data['anime_mal_id']        ?? '',
            'anime_bangumi_id'       => $bgm_id,
            'bangumi_id'             => $bgm_id,         // 相容舊 key

            // ── 標題 ──────────────────────────────────────────
            'anime_title_chinese'    => $title_zh,
            'anime_title_zh'         => $title_zh,       // 相容舊 key
            'anime_title_romaji'     => $data['anime_title_romaji']  ?? '',
            'anime_title_english'    => $data['anime_title_english'] ?? '',
            'anime_title_native'     => $data['anime_title_native']  ?? '',

            // ── 基本資訊 ──────────────────────────────────────
            'anime_status'           => $data['anime_status']        ?? '',
            'anime_format'           => $type,
            'anime_type'             => $type,           // 相容舊 key
            'anime_episodes'         => $data['anime_episodes']      ?? 0,
            'anime_season'           => $data['anime_season']        ?? '',
            'anime_season_year'      => $data['anime_year']          ?? 0,
            'anime_year'             => $data['anime_year']          ?? 0,

            // ── 評分與人氣 ────────────────────────────────────
            'anime_score_anilist'    => $data['anime_score_anilist'] ?? 0,
            'anime_score_bangumi'    => $data['anime_score_bangumi'] ?? 0,
            'anime_popularity'       => $data['anime_popularity']    ?? 0,

            // ── 圖片 ──────────────────────────────────────────
            'anime_cover_image'      => $data['anime_cover_image']   ?? '',
            'anime_banner_image'     => $data['anime_banner_image']  ?? '',

            // ── 簡介 ──────────────────────────────────────────
            'anime_synopsis_chinese' => $synopsis,
            'anime_synopsis_zh'      => $synopsis,       // 相容舊 key

            // ── Staff / Cast ──────────────────────────────────
            'anime_staff_json'       => $data['anime_staff_json']    ?? '[]',
            'anime_cast_json'        => $data['anime_cast_json']     ?? '[]',

            // ── 關聯作品 ──────────────────────────────────────
            'anime_relations_json'   => $data['anime_relations_json'] ?? '[]',

            // ── 同步時間 ──────────────────────────────────────
            'anime_last_sync'        => current_time( 'mysql' ),
        ];

        foreach ( $fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
    }

    // ============================================================
    // 寫入 Taxonomy（genre / anime_season_tax / anime_format_tax）
    // ============================================================
    private function save_taxonomies( int $post_id, array $data ): void {

        // ── Genre（類型）─────────────────────────────────────
        $genres = $data['_genres'] ?? [];
        if ( ! empty( $genres ) && is_array( $genres ) ) {
            // AniList 回傳英文 genre，對應到 taxonomy slug
            $genre_map = [
                'Action'        => 'action',
                'Adventure'     => 'adventure',
                'Comedy'        => 'comedy',
                'Drama'         => 'drama',
                'Fantasy'       => 'fantasy',
                'Horror'        => 'horror',
                'Mahou Shoujo'  => 'mahou-shoujo',
                'Mecha'         => 'mecha',
                'Music'         => 'music-genre',
                'Mystery'       => 'mystery',
                'Psychological' => 'psychological',
                'Romance'       => 'romance',
                'Sci-Fi'        => 'sci-fi',
                'Slice of Life' => 'slice-of-life',
                'Sports'        => 'sports',
                'Supernatural'  => 'supernatural',
                'Thriller'      => 'thriller',
                'Ecchi'         => 'ecchi',
            ];

            $term_ids = [];
            foreach ( $genres as $genre_en ) {
                $slug = $genre_map[ $genre_en ] ?? sanitize_title( $genre_en );
                $term = get_term_by( 'slug', $slug, 'genre' );
                if ( $term ) {
                    $term_ids[] = $term->term_id;
                }
            }

            if ( ! empty( $term_ids ) ) {
                wp_set_object_terms( $post_id, $term_ids, 'genre' );
            }
        }

        // ── 播出季度（anime_season_tax）──────────────────────
        $season    = strtolower( $data['anime_season'] ?? '' );
        $year      = (int) ( $data['anime_year'] ?? 0 );

        $season_slug_map = [
            'spring' => 'spring',
            'summer' => 'summer',
            'fall'   => 'fall',
            'winter' => 'winter',
        ];

        if ( $year && isset( $season_slug_map[ $season ] ) ) {
            $parent_term = get_term_by( 'slug', (string) $year, 'anime_season_tax' );
            $child_term  = get_term_by( 'slug', "{$year}-{$season}", 'anime_season_tax' );

            $season_term_ids = [];
            if ( $parent_term ) $season_term_ids[] = $parent_term->term_id;
            if ( $child_term )  $season_term_ids[] = $child_term->term_id;

            if ( ! empty( $season_term_ids ) ) {
                wp_set_object_terms( $post_id, $season_term_ids, 'anime_season_tax' );
            }
        }

        // ── 動畫格式（anime_format_tax）──────────────────────
        $format = strtoupper( $data['anime_type'] ?? '' );

        $format_slug_map = [
            'TV'      => 'format-tv',
            'TV_SHORT'=> 'format-tv-short',
            'MOVIE'   => 'format-movie',
            'OVA'     => 'format-ova',
            'ONA'     => 'format-ona',
            'SPECIAL' => 'format-special',
            'MUSIC'   => 'format-music',
        ];

        if ( isset( $format_slug_map[ $format ] ) ) {
            $format_term = get_term_by( 'slug', $format_slug_map[ $format ], 'anime_format_tax' );
            if ( $format_term ) {
                wp_set_object_terms( $post_id, [ $format_term->term_id ], 'anime_format_tax' );
            }
        }
    }

    // ============================================================
    // 查找已存在的文章
    // ============================================================
    private function find_existing( int $al_id, $mal_id ): int|false {

        $q = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'any',
            'meta_query'     => [ [ 'key' => 'anime_anilist_id', 'value' => $al_id ] ],
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );

        if ( $q->have_posts() ) return (int) $q->posts[0];

        // 相容舊 key
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

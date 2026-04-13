<?php
/**
 * Class Anime_Sync_Import_Manager
 *
 * Bug fixes in this version:
 *   AW  – anime_season 統一大寫儲存；taxonomy slug 查詢用小寫
 *   AT  – 寫入 anime_animethemes_id（從 animethemes_slug）
 *   ABF – 儲存 anime_score_mal
 *   ABG – 儲存 anime_official_site / anime_twitter_url
 *   ABH – 寫入 post_tag（AniList tags）
 *   ABI – 儲存 anime_wikipedia_url
 *   ABJ – tag 查重邏輯（繁中名稱優先，英文原文相容）
 *   ABK – Google 免費翻譯 fallback
 *   ABL – 儲存 anime_episodes_json（Bangumi 集數列表）
 *   ABM – 首次匯入自動鎖定四個欄位
 *         ( anime_cover_image / anime_banner_image /
 *           anime_trailer_url / anime_synopsis_chinese )
 *   ABN – 匯入完成後回傳結構化摘要（方案 B）
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Import_Manager {

    private Anime_Sync_API_Handler   $api_handler;
    private Anime_Sync_Image_Handler $image_handler;

    public function __construct( Anime_Sync_API_Handler $api_handler ) {
        $this->api_handler   = $api_handler;
        $this->image_handler = new Anime_Sync_Image_Handler();
    }

    // =========================================================================
    // 主要匯入方法
    // =========================================================================

    public function import_single( int $anilist_id, ?int $bangumi_id = null ): array {

        // ── 初始化摘要（ABN）─────────────────────────────────────────────────
        $summary = [
            'anilist'    => false,
            'bangumi'    => false,
            'mal_score'  => false,
            'themes'     => false,
            'cover'      => false,
            'wikipedia'  => false,
            'episodes'   => false,
            'streaming'  => false,
            'taxonomies' => false,
        ];

        // ── 1. 取得完整資料 ───────────────────────────────────────────────────
        $data = $this->api_handler->get_full_anime_data( $anilist_id, 0, $bangumi_id );

        if ( is_wp_error( $data ) ) {
            return [
                'success' => false,
                'message' => $data->get_error_message(),
                'summary' => $summary,
            ];
        }

        if ( empty( $data ) || ! is_array( $data ) ) {
            return [
                'success' => false,
                'message' => '資料取得失敗',
                'summary' => $summary,
            ];
        }

        // ── 2. 驗證 AniList ID ────────────────────────────────────────────────
        $confirmed_anilist_id = (int) ( $data['anilist_id'] ?? 0 );
        $mal_id               = $data['mal_id'] ?? null;

        if ( ! $confirmed_anilist_id ) {
            return [
                'success' => false,
                'message' => 'AniList ID 無效',
                'summary' => $summary,
            ];
        }

        $summary['anilist'] = true;

        // ── 3. 填寫各項摘要狀態 ──────────────────────────────────────────────
        $summary['bangumi']   = ! empty( $data['bangumi_id'] );
        $summary['mal_score'] = isset( $data['anime_score_mal'] ) && (float) $data['anime_score_mal'] > 0;
        $summary['wikipedia'] = ! empty( $data['anime_wikipedia_url'] );
        $summary['streaming'] = ! empty( $data['anime_streaming'] )
                                && $data['anime_streaming'] !== '[]';

        // AnimeThemes：slug 有值代表有抓到
        $summary['themes']   = ! empty( $data['animethemes_slug'] );

        // Bangumi 集數
        $summary['episodes'] = ! empty( $data['anime_episodes_json'] )
                               && $data['anime_episodes_json'] !== '[]';

        // ── 4. 檢查是否已存在 ─────────────────────────────────────────────────
        $existing = $this->find_existing( $confirmed_anilist_id, $mal_id );
        if ( $existing ) {
            return [
                'success'  => true,
                'skipped'  => true,
                'message'  => '動畫已存在，略過',
                'post_id'  => $existing,
                'edit_url' => get_edit_post_link( $existing, 'raw' ),
                'summary'  => $summary,
            ];
        }

        // ── 5. 決定標題與 Slug ────────────────────────────────────────────────
        $title  = ! empty( $data['anime_title_chinese'] )
                  ? $data['anime_title_chinese']
                  : ( $data['anime_title_romaji'] ?? 'Unknown' );
        $romaji = $data['anime_title_romaji'] ?? '';
        $slug   = $this->generate_slug( $romaji, $confirmed_anilist_id );

        // ── 6. 建立草稿 ───────────────────────────────────────────────────────
        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $data['anime_synopsis_chinese'] ?? '',
            'post_type'    => 'anime',
            'post_status'  => 'draft',
        ] );

        if ( is_wp_error( $post_id ) ) {
            return [
                'success' => false,
                'message' => '建立草稿失敗：' . $post_id->get_error_message(),
                'summary' => $summary,
            ];
        }

        // ── 7. 寫入所有 Meta ──────────────────────────────────────────────────
        $this->save_post_meta( $post_id, $data );

        // ── 8. ABM：首次匯入自動鎖定四個欄位 ────────────────────────────────
        $this->apply_first_import_locks( $post_id, $data );

        // ── 9. 處理封面圖片 ───────────────────────────────────────────────────
        if ( ! empty( $data['anime_cover_image'] ) ) {
            $cover_ok = $this->image_handler->handle_cover(
                $data['anime_cover_image'],
                $romaji ?: 'anime-' . $confirmed_anilist_id,
                $post_id
            );
            $summary['cover'] = (bool) $cover_ok;
        }

        // ── 10. 設定 Taxonomy（含 post_tag）──────────────────────────────────
        $tax_ok = $this->save_taxonomies( $post_id, $data );
        $summary['taxonomies'] = $tax_ok;

        // ── 11. 回傳結果（含摘要）────────────────────────────────────────────
        return [
            'success'  => true,
            'message'  => '匯入成功：' . $title,
            'title'    => $title,
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
            'summary'  => $summary,
        ];
    }

    // =========================================================================
    // ABM：首次匯入自動鎖定欄位
    // =========================================================================

    /**
     * 首次匯入時，若以下欄位有值則自動加入鎖定清單，
     * 防止之後自動同步覆蓋編輯好的內容。
     *
     * 預設鎖定：封面圖片、橫幅圖片、預告片網址、中文簡介。
     */
    private function apply_first_import_locks( int $post_id, array $data ): void {
        $auto_lock_fields = [
            'anime_cover_image'      => $data['anime_cover_image']      ?? '',
            'anime_banner_image'     => $data['anime_banner_image']      ?? '',
            'anime_trailer_url'      => $data['anime_trailer_url']       ?? '',
            'anime_synopsis_chinese' => $data['anime_synopsis_chinese']  ?? '',
        ];

        $to_lock = [];
        foreach ( $auto_lock_fields as $key => $value ) {
            if ( $value !== '' && $value !== null ) {
                $to_lock[] = $key;
            }
        }

        if ( ! empty( $to_lock ) ) {
            update_post_meta( $post_id, 'anime_locked_fields', $to_lock );
        }
    }

    // =========================================================================
    // 生成 Romaji Slug
    // =========================================================================

    private function generate_slug( string $romaji, int $anilist_id ): string {
        if ( empty( $romaji ) ) return 'anime-' . $anilist_id;

        $slug = strtolower( $romaji );
        $slug = preg_replace( '/[^a-z0-9\s\-]/', '', $slug );
        $slug = preg_replace( '/[\s\-]+/', '-', trim( $slug ) );
        $slug = trim( $slug, '-' );

        if ( empty( $slug ) ) return 'anime-' . $anilist_id;

        $original = $slug;
        $counter  = 1;
        while ( $this->slug_exists( $slug ) ) {
            $slug = $original . '-' . $counter++;
        }
        return $slug;
    }

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

    // =========================================================================
    // 寫入所有 Meta 欄位
    // =========================================================================

    private function save_post_meta( int $post_id, array $data ): void {

        $synopsis = $data['anime_synopsis_chinese'] ?? '';
        $title_zh = $data['anime_title_chinese']    ?? '';
        $bgm_id   = $data['bangumi_id']             ?? '';
        $format   = $data['anime_format']           ?? '';
        $year     = $data['anime_season_year']      ?? 0;

        $fields = [
            // ── IDs ───────────────────────────────────────────────────────────
            'anime_anilist_id'       => $data['anilist_id']              ?? '',
            'anime_mal_id'           => $data['mal_id']                  ?? '',
            'anime_bangumi_id'       => $bgm_id,
            'bangumi_id'             => $bgm_id,   // 相容舊 key

            // ── 標題 ──────────────────────────────────────────────────────────
            'anime_title_chinese'    => $title_zh,
            'anime_title_zh'         => $title_zh, // 相容舊 key
            'anime_title_romaji'     => $data['anime_title_romaji']      ?? '',
            'anime_title_english'    => $data['anime_title_english']     ?? '',
            'anime_title_native'     => $data['anime_title_native']      ?? '',

            // ── 基本資訊 ──────────────────────────────────────────────────────
            'anime_status'           => $data['anime_status']            ?? '',
            'anime_format'           => $format,
            'anime_type'             => $format,   // 相容舊 key
            'anime_episodes'         => $data['anime_episodes']          ?? 0,
            'anime_duration'         => $data['anime_duration']          ?? 0,
            'anime_source'           => $data['anime_source']            ?? '',

            // AW：統一大寫儲存
            'anime_season'           => strtoupper( $data['anime_season'] ?? '' ),
            'anime_season_year'      => $year,
            'anime_year'             => $year,     // 相容舊 key

            // ── 評分與人氣 ────────────────────────────────────────────────────
            'anime_score_anilist'    => $data['anime_score_anilist']     ?? 0,
            'anime_score_bangumi'    => $data['anime_score_bangumi']     ?? 0,
            // ABF：儲存 MAL 評分（0–10 float）
            'anime_score_mal'        => $data['anime_score_mal']         ?? 0,
            'anime_popularity'       => $data['anime_popularity']        ?? 0,

            // ── 圖片 ──────────────────────────────────────────────────────────
            'anime_cover_image'      => $data['anime_cover_image']       ?? '',
            'anime_banner_image'     => $data['anime_banner_image']      ?? '',
            'anime_trailer_url'      => $data['anime_trailer_url']       ?? '',

            // ── 簡介 ──────────────────────────────────────────────────────────
            'anime_synopsis_chinese' => $synopsis,
            'anime_synopsis_zh'      => $synopsis, // 相容舊 key
            'anime_synopsis_english' => $data['anime_synopsis_english']  ?? '',

            // ── Staff / Cast / Relations ──────────────────────────────────────
            'anime_staff_json'       => $data['anime_staff_json']        ?? '[]',
            'anime_cast_json'        => $data['anime_cast_json']         ?? '[]',
            'anime_relations_json'   => $data['anime_relations_json']    ?? '[]',

            // ── 製作公司 ──────────────────────────────────────────────────────
            'anime_studios'          => $data['anime_studios']           ?? '',

            // ── 日期 ──────────────────────────────────────────────────────────
            'anime_start_date'       => $data['anime_start_date']        ?? '',
            'anime_end_date'         => $data['anime_end_date']          ?? '',

            // ── 主題曲（JSON）────────────────────────────────────────────────
            'anime_themes'           => isset( $data['anime_themes'] )
                                        ? ( is_array( $data['anime_themes'] )
                                            ? wp_json_encode( $data['anime_themes'], JSON_UNESCAPED_UNICODE )
                                            : $data['anime_themes'] )
                                        : '[]',

            // ── 串流平台（JSON）──────────────────────────────────────────────
            'anime_streaming'        => isset( $data['anime_streaming'] )
                                        ? ( is_array( $data['anime_streaming'] )
                                            ? wp_json_encode( $data['anime_streaming'], JSON_UNESCAPED_UNICODE )
                                            : $data['anime_streaming'] )
                                        : '[]',

            // ABL：Bangumi 集數列表（JSON）
            'anime_episodes_json'    => $data['anime_episodes_json']     ?? '[]',

            // ── 外部連結 ──────────────────────────────────────────────────────
            'anime_external_links'   => $data['anime_external_links']    ?? '',
            // ABG：個別外部連結欄位
            'anime_official_site'    => $data['anime_official_site']     ?? '',
            'anime_twitter_url'      => $data['anime_twitter_url']       ?? '',
            // ABI：Wikipedia URL
            'anime_wikipedia_url'    => $data['anime_wikipedia_url']     ?? '',

            // ── 下集資訊 ──────────────────────────────────────────────────────
            'anime_next_airing'      => $data['anime_next_airing']       ?? '',

            // ── 同步時間 ──────────────────────────────────────────────────────
            'anime_last_sync'        => current_time( 'mysql' ),
        ];

        foreach ( $fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        // AT：寫入 anime_animethemes_id（從 animethemes_slug）
        if ( ! empty( $data['animethemes_slug'] ) ) {
            update_post_meta(
                $post_id,
                'anime_animethemes_id',
                sanitize_text_field( (string) $data['animethemes_slug'] )
            );
        }
    }

    // =========================================================================
    // 寫入 Taxonomy
    // =========================================================================

    private function save_taxonomies( int $post_id, array $data ): bool {

        $ok = true;

        // ── Genre ─────────────────────────────────────────────────────────────
        $genres = $data['anime_genres'] ?? [];
        if ( ! empty( $genres ) && is_array( $genres ) ) {

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
                'Sci-Fi'        => 'sci-fi',
                'Slice of Life' => 'slice-of-life',
                'Sports'        => 'sports',
                'Supernatural'  => 'supernatural',
                'Thriller'      => 'thriller',
                'Ecchi'         => 'ecchi',
                'Romance'       => 'romance',
                'Isekai'        => 'isekai',
                'Harem'         => 'harem',
                'Boys Love'     => 'bl',
                'Yuri'          => 'yuri',
                'Historical'    => 'historical',
                'School'        => 'school',
                'Kids'          => 'kids',
                'Wuxia'         => 'wuxia',
                'Suspense'      => 'suspense',
            ];

            $term_ids = [];
            foreach ( $genres as $genre_en ) {
                $slug = $genre_map[ $genre_en ] ?? null;
                if ( $slug === null ) continue;
                $term = get_term_by( 'slug', $slug, 'genre' );
                if ( $term ) $term_ids[] = $term->term_id;
            }
            if ( ! empty( $term_ids ) ) {
                $result = wp_set_object_terms( $post_id, $term_ids, 'genre' );
                if ( is_wp_error( $result ) ) $ok = false;
            }
        }

        // ── 播出季度（anime_season_tax）──────────────────────────────────────
        // AW：meta 存大寫，slug 查詢用小寫
        $season = strtolower( $data['anime_season'] ?? '' );
        $year   = (int) ( $data['anime_season_year'] ?? 0 );

        if ( $year && in_array( $season, [ 'winter', 'spring', 'summer', 'fall' ], true ) ) {
            $parent_term     = get_term_by( 'slug', (string) $year, 'anime_season_tax' );
            $child_term      = get_term_by( 'slug', "{$year}-{$season}", 'anime_season_tax' );
            $season_term_ids = [];
            if ( $parent_term ) $season_term_ids[] = $parent_term->term_id;
            if ( $child_term )  $season_term_ids[] = $child_term->term_id;
            if ( ! empty( $season_term_ids ) ) {
                $result = wp_set_object_terms( $post_id, $season_term_ids, 'anime_season_tax' );
                if ( is_wp_error( $result ) ) $ok = false;
            }
        }

        // ── 動畫格式（anime_format_tax）──────────────────────────────────────
        $format = strtoupper( $data['anime_format'] ?? '' );

        $format_slug_map = [
            'TV'       => 'format-tv',
            'TV_SHORT' => 'format-tv-short',
            'MOVIE'    => 'format-movie',
            'OVA'      => 'format-ova',
            'ONA'      => 'format-ona',
            'SPECIAL'  => 'format-special',
            'MUSIC'    => 'format-music',
        ];

        if ( isset( $format_slug_map[ $format ] ) ) {
            $format_term = get_term_by( 'slug', $format_slug_map[ $format ], 'anime_format_tax' );
            if ( $format_term ) {
                $result = wp_set_object_terms( $post_id, [ $format_term->term_id ], 'anime_format_tax' );
                if ( is_wp_error( $result ) ) $ok = false;
            }
        }

        // ── post_tag（ABH / ABJ / ABK）───────────────────────────────────────
        $anime_tags = $data['anime_tags'] ?? [];
        if ( ! empty( $anime_tags ) && is_array( $anime_tags ) ) {
            $tag_ids = [];
            foreach ( $anime_tags as $tag_en ) {
                if ( empty( $tag_en ) ) continue;
                $tag_name = $this->resolve_tag_name( $tag_en );
                $term_id  = $this->find_or_create_tag( $tag_en, $tag_name );
                if ( $term_id ) {
                    $tag_ids[] = $term_id;
                }
            }
            // 去重後寫入
            $tag_ids = array_unique( $tag_ids );
            if ( ! empty( $tag_ids ) ) {
                $result = wp_set_object_terms( $post_id, $tag_ids, 'post_tag', false );
                if ( is_wp_error( $result ) ) $ok = false;
            }
        }

        return $ok;
    }

    // =========================================================================
    // Tag 處理：查本地對照表 → Google 翻譯 → 英文原文
    // =========================================================================

    private function resolve_tag_name( string $tag_en ): string {

        // 步驟 1：查本地對照表
        $tag_map = $this->get_tag_map();
        if ( isset( $tag_map[ $tag_en ] ) ) {
            return $tag_map[ $tag_en ];
        }

        // 步驟 2：查 transient 快取
        $cache_key = 'anime_sync_tag_zh_' . md5( $tag_en );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return (string) $cached;
        }

        // 步驟 3：呼叫 Google 免費翻譯 API
        $translated = $this->google_translate( $tag_en, 'en', 'zh-TW' );
        if ( $translated !== '' && $translated !== $tag_en ) {
            set_transient( $cache_key, $translated, 30 * DAY_IN_SECONDS );
            return $translated;
        }

        // 步驟 4：fallback 英文原文
        set_transient( $cache_key, $tag_en, 7 * DAY_IN_SECONDS );
        return $tag_en;
    }

    private function google_translate( string $text, string $sl, string $tl ): string {
        $url = 'https://translate.googleapis.com/translate_a/single'
             . '?client=gtx'
             . '&sl=' . rawurlencode( $sl )
             . '&tl=' . rawurlencode( $tl )
             . '&dt=t'
             . '&q='  . rawurlencode( $text );

        $response = wp_remote_get( $url, [
            'timeout'    => 8,
            'user-agent' => 'Mozilla/5.0 (compatible; AnimeSync-Pro/1.0)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) return '';
        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return '';

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $result = $body[0][0][0] ?? '';

        if ( ! is_string( $result ) || $result === '' ) return '';
        return trim( $result );
    }

    private function find_or_create_tag( string $tag_en, string $tag_name ): ?int {

        // 先查繁中名稱
        $term = get_term_by( 'name', $tag_name, 'post_tag' );
        if ( $term ) return $term->term_id;

        // 繁中找不到時，查英文原文（相容後台尚未改名的舊 tag）
        if ( $tag_name !== $tag_en ) {
            $term = get_term_by( 'name', $tag_en, 'post_tag' );
            if ( $term ) return $term->term_id;
        }

        // 都找不到 → 建立新 tag（用繁中名稱）
        $inserted = wp_insert_term( $tag_name, 'post_tag' );
        if ( is_wp_error( $inserted ) ) return null;

        return (int) $inserted['term_id'];
    }

    private function get_tag_map(): array {
        return [
            // ── 敘事手法 ──────────────────────────────────────────────────────
            'Amnesia'                      => '失憶',
            'Time Skip'                    => '時間跳躍',
            'Flashback'                    => '回憶閃回',
            'Non-linear'                   => '非線性敘事',
            'Unreliable Narrator'          => '不可靠敘述者',
            'Multiple Perspectives'        => '多視角敘事',
            'Frame Story'                  => '框架故事',
            'Tragedy'                      => '悲劇',
            'Plot Twist'                   => '劇情反轉',
            'Mystery'                      => '推理懸疑',
            'Cliffhanger'                  => '懸念結局',

            // ── 世界觀設定 ────────────────────────────────────────────────────
            'Isekai'                       => '異世界',
            'Post-Apocalyptic'             => '末日後',
            'Cyberpunk'                    => '賽博龐克',
            'Steampunk'                    => '蒸汽龐克',
            'Dystopia'                     => '反烏托邦',
            'Utopia'                       => '烏托邦',
            'Space'                        => '宇宙太空',
            'Virtual World'                => '虛擬世界',
            'Alternate Universe'           => '平行宇宙',
            'Urban Fantasy'                => '都市奇幻',
            'High Fantasy'                 => '高奇幻',
            'Dark Fantasy'                 => '黑暗奇幻',
            'Historical'                   => '歷史',
            'Feudal Japan'                 => '戰國時代',
            'Edo Period'                   => '江戶時代',
            'Meiji Period'                 => '明治時代',
            'Ancient China'                => '古代中國',
            'Medieval'                     => '中世紀',

            // ── 角色類型 ──────────────────────────────────────────────────────
            'Tsundere'                     => '傲嬌',
            'Kuudere'                      => '冷淡',
            'Dandere'                      => '膽小',
            'Yandere'                      => '病嬌',
            'Deredere'                     => '開朗',
            'Harem'                        => '後宮',
            'Reverse Harem'                => '逆後宮',
            'Loli'                         => '蘿莉',
            'Shota'                        => '正太',
            'Kemonomimi'                   => '獸耳',
            'Catgirl'                      => '貓娘',
            'Vampire'                      => '吸血鬼',
            'Werewolf'                     => '狼人',
            'Demon'                        => '惡魔',
            'Angel'                        => '天使',
            'Robot'                        => '機器人',
            'Android'                      => '人造人',
            'Idol'                         => '偶像',
            'Ninja'                        => '忍者',
            'Samurai'                      => '武士',
            'Witch'                        => '魔女',
            'Elf'                          => '精靈',
            'Maid'                         => '女僕',
            'Butler'                       => '執事',
            'Otaku'                        => '宅男',
            'Villain Protagonist'          => '反派主角',
            'Anti-Hero'                    => '反英雄',
            'Female Protagonist'           => '女主角',
            'Male Protagonist'             => '男主角',

            // ── 劇情元素 ──────────────────────────────────────────────────────
            'Revenge'                      => '復仇',
            'Love Triangle'                => '三角戀',
            'Coming of Age'                => '成長物語',
            'Redemption'                   => '救贖',
            'Betrayal'                     => '背叛',
            'Sacrifice'                    => '犧牲',
            'Survival'                     => '生存',
            'War'                          => '戰爭',
            'Politics'                     => '政治',
            'Revolution'                   => '革命',
            'Conspiracy'                   => '陰謀',
            'Slice of Life'                => '日常生活',
            'Found Family'                 => '羈絆家人',
            'Bromance'                     => '兄弟情誼',
            'Forbidden Love'               => '禁忌之戀',
            'Second Chance'                => '重來機會',
            'Reincarnation'                => '轉生',
            'Time Travel'                  => '時間旅行',
            'Body Swap'                    => '靈魂互換',
            'Gender Bender'                => '性別轉換',
            'Memory Manipulation'          => '記憶操控',
            'Death Game'                   => '死亡遊戲',
            'Battle Royale'                => '大逃殺',
            'Tournament'                   => '武鬥大會',
            'Heist'                        => '竊盜行動',
            'Detective'                    => '偵探',
            'Crime'                        => '犯罪',

            // ── 超自然與能力 ──────────────────────────────────────────────────
            'Super Power'                  => '超能力',
            'Magic'                        => '魔法',
            'Alchemy'                      => '鍊金術',
            'Psychic'                      => '超感應',
            'Telepathy'                    => '心靈感應',
            'Telekinesis'                  => '念力',
            'Shapeshifting'                => '變形',
            'Necromancy'                   => '死靈魔法',
            'Exorcism'                     => '驅魔',
            'Summoning'                    => '召喚',
            'Familiar'                     => '使魔',
            'Curse'                        => '詛咒',
            'Contract'                     => '契約',
            'Overpowered Protagonist'      => '主角無敵',
            'Level System'                 => '等級系統',
            'RPG'                          => 'RPG 系統',
            'Game Elements'                => '遊戲要素',
            'Card Battle'                  => '卡牌對戰',

            // ── 戰鬥系 ────────────────────────────────────────────────────────
            'Martial Arts'                 => '武術',
            'Swordplay'                    => '劍術',
            'Gunfight'                     => '槍戰',
            'Mecha'                        => '機甲',
            'Giant Robot'                  => '巨大機器人',
            'Military'                     => '軍事',
            'Assassin'                     => '刺客',
            'Mercenary'                    => '傭兵',
            'Monster'                      => '怪物',
            'Kaiju'                        => '怪獸',
            'Zombie'                       => '殭屍',

            // ── 職業與場景 ────────────────────────────────────────────────────
            'School'                       => '校園',
            'University'                   => '大學',
            'Office'                       => '職場',
            'Medical'                      => '醫療',
            'Police'                       => '警察',
            'Law'                          => '法律',
            'Sports'                       => '體育競技',
            'Music'                        => '音樂',
            'Cooking'                      => '料理',
            'Gaming'                       => '電競遊戲',
            'Art'                          => '藝術',
            'Photography'                  => '攝影',
            'Fashion'                      => '時尚',
            'Racing'                       => '賽車',
            'Fishing'                      => '釣魚',
            'Gardening'                    => '園藝',
            'Farming'                      => '農業',

            // ── 關係與社交 ────────────────────────────────────────────────────
            'Childhood Friends'            => '青梅竹馬',
            'Teacher-Student Romance'      => '師生戀',
            'Age Gap'                      => '年齡差',
            'Siblings'                     => '兄弟姊妹',
            'Family'                       => '家庭',
            'Single Parent'                => '單親',
            'Orphan'                       => '孤兒',
            'Friendship'                   => '友情',
            'Rivals'                       => '競爭對手',
            'Master-Servant'               => '主僕關係',
            'Contract Marriage'            => '契約婚姻',
            'Workplace Romance'            => '職場戀愛',

            // ── 心理與哲學 ────────────────────────────────────────────────────
            'Psychological'                => '心理',
            'Philosophy'                   => '哲學',
            'Existentialism'               => '存在主義',
            'Morality'                     => '道德',
            'Identity Crisis'              => '認同危機',
            'PTSD'                         => '創傷後壓力症',
            'Depression'                   => '憂鬱',
            'Loneliness'                   => '孤獨',
            'Social Anxiety'               => '社交恐懼',

            // ── 風格與表現手法 ────────────────────────────────────────────────
            'Comedy'                       => '喜劇',
            'Parody'                       => '惡搞',
            'Satire'                       => '諷刺',
            'Fourth Wall Breaking'         => '打破第四道牆',
            'Meta'                         => '後設',
            'Horror'                       => '恐怖',
            'Gore'                         => '血腥暴力',
            'Ecchi'                        => '情色',
            'Fanservice'                   => '福利向',
            'Cute Girls Doing Cute Things' => '萌系日常',
            'Iyashikei'                    => '治癒系',
            'Josei'                        => '少女漫改',
            'Seinen'                       => '青年漫改',
            'Shounen'                      => '少年漫改',
            'Shoujo'                       => '少女漫改',
            'Anthology'                    => '短篇集',
            'Omnibus'                      => '單元劇',

            // ── 動物與非人類 ──────────────────────────────────────────────────
            'Animal'                       => '動物',
            'Anthropomorphism'             => '擬人化',
            'Dragons'                      => '龍',
            'Dinosaurs'                    => '恐龍',
            'Insects'                      => '昆蟲',

            // ── 其他常見 tag ──────────────────────────────────────────────────
            'Adaptation'                   => '改編作品',
            'Original'                     => '原創',
            'Short Episodes'               => '短篇',
            'CGI'                          => '3D CG',
            'Music Anime'                  => '音樂動畫',
            'Otome Game'                   => '乙女遊戲',
            'Gacha'                        => '抽卡',
            'Crowdfunded'                  => '群眾募資',
            'Based on a Manga'             => '漫畫改編',
            'Based on a Novel'             => '小說改編',
            'Based on a Game'              => '遊戲改編',
            'Crossover'                    => '跨作品',
            'Sequel'                       => '續集',
            'Prequel'                      => '前傳',
            'Spin-off'                     => '衍生作',
        ];
    }

    // =========================================================================
    // 查找已存在的文章
    // =========================================================================

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

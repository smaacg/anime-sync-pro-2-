<?php
/**
 * Class Anime_Sync_Import_Manager
 *
 * Bug fixes in this version:
 *   AW  – uppercase season storage
 *   AT  – write animethemes_slug as anime_animethemes_id
 *   ABF – store MAL score
 *   ABG – parse_external_links for official_site / twitter_url
 *   ABH – write AniList tags
 *   ABI – truncate [简介原文] in clean_synopsis
 *   ABJ – store wikipedia_url
 *   ABK – store relations with title_chinese / relation_label / cover_image / anilist_id
 *   ABL – cache Bangumi episodes JSON
 *   ABM – apply_first_import_locks() stores non-empty locked fields
 *   ABN – return structured summary from import_single()
 *   ABO – force second write of anime_bangumi_id if valid integer
 *   ACA – import_single() message 加入 ⚠️ Bangumi 缺失警告 + bangumi_missing flag
 *         save_post_meta() 寫入正整數 bgm_id 後清除 _bangumi_id_pending
 *         save_post_meta() 若已有 anime_bangumi_id 且 _bangumi_id_manually_set=1 則跳過覆蓋
 *         find_existing() 加入 'fields' => 'ids' 提升 WP_Query 效能
 *         google_translate() 日誌輸出遮蔽 API key
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
    // PUBLIC – 單部匯入
    // =========================================================================

    public function import_single( int $anilist_id, ?int $bangumi_id = null ): array {

        // 1. 取得完整資料
        $data = $this->api_handler->get_full_anime_data( $anilist_id, 0, $bangumi_id );

        if ( is_wp_error( $data ) ) {
            return [
                'success' => false,
                'message' => '取得資料失敗：' . $data->get_error_message(),
            ];
        }

        // 2. 驗證 AniList ID
        if ( empty( $data['anilist_id'] ) ) {
            return [
                'success' => false,
                'message' => '無效的 AniList 資料',
            ];
        }

        // 3. 彙整摘要
        $summary = [
            'anilist'   => ! empty( $data['anilist_id'] ),
            'bangumi'   => ! empty( $data['bangumi_id'] ) && $data['bangumi_id'] > 0,
            'mal_score' => ! empty( $data['anime_score_mal'] ),
            'themes'    => ! empty( $data['anime_themes'] ) && $data['anime_themes'] !== '[]',
            'cover'     => ! empty( $data['anime_cover_image'] ),
            'wikipedia' => ! empty( $data['anime_wikipedia_url'] ),
            'episodes'  => ! empty( $data['anime_episodes_json'] ) && $data['anime_episodes_json'] !== '[]',
            'streaming' => ! empty( $data['anime_streaming'] ) && $data['anime_streaming'] !== '[]',
        ];

        // 4. 檢查既有文章
        $existing_post_id = $this->find_existing( $anilist_id );

        if ( $existing_post_id ) {
            return [
                'success'  => false,
                'message'  => '已存在：' . get_the_title( $existing_post_id ),
                'post_id'  => $existing_post_id,
                'edit_url' => get_edit_post_link( $existing_post_id, 'raw' ),
            ];
        }

        // 5. 標題與 Slug
        $title = $data['anime_title_chinese'] ?: $data['anime_title_romaji'] ?: $data['anime_title_english'] ?: "Anime #{$anilist_id}";
        $slug  = $this->generate_slug( $data['anime_title_romaji'] ?: $title );

        // 6. 建立草稿
        $post_id = wp_insert_post( [
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_status'  => 'draft',
            'post_type'    => 'anime',
            'post_content' => '',
        ] );

        if ( is_wp_error( $post_id ) ) {
            return [
                'success' => false,
                'message' => '建立文章失敗：' . $post_id->get_error_message(),
            ];
        }

        // 7. 儲存 Meta
        $this->save_post_meta( $post_id, $data );

        // 8. 首次匯入鎖定
        $this->apply_first_import_locks( $post_id, $data );

        // 9. 封面圖片
        if ( ! empty( $data['anime_cover_image'] ) ) {
            $this->image_handler->set_featured_image_from_url(
                $post_id,
                $data['anime_cover_image'],
                $title
            );
        }

        // 10. 分類法
        $this->save_taxonomies( $post_id, $data );

        // 11. 回傳結果（ACA：加入 bangumi 警告）
        $warn = '';
        if ( ! $summary['bangumi'] ) {
            $warn .= ' ⚠️ Bangumi ID 未找到';
        }
        if ( ! $summary['themes'] ) {
            $warn .= ' | 無主題曲';
        }

        return [
            'success'         => true,
            'message'         => '匯入成功：' . $title . $warn,
            'title'           => $title,
            'post_id'         => $post_id,
            'edit_url'        => get_edit_post_link( $post_id, 'raw' ),
            'summary'         => $summary,
            'bangumi_missing' => ! $summary['bangumi'], // ACA：前端可據此標色
        ];
    }

    // =========================================================================
    // PRIVATE – 首次匯入鎖定
    // =========================================================================

    private function apply_first_import_locks( int $post_id, array $data ): void {
        $locked = [];

        if ( ! empty( $data['anime_cover_image'] ) ) {
            $locked['cover'] = $data['anime_cover_image'];
        }
        if ( ! empty( $data['anime_banner_image'] ) ) {
            $locked['banner'] = $data['anime_banner_image'];
        }
        if ( ! empty( $data['anime_trailer_url'] ) ) {
            $locked['trailer'] = $data['anime_trailer_url'];
        }
        if ( ! empty( $data['anime_synopsis_chinese'] ) ) {
            $locked['synopsis_chinese'] = $data['anime_synopsis_chinese'];
        }

        if ( ! empty( $locked ) ) {
            update_post_meta( $post_id, 'anime_locked_fields', $locked );
        }
    }

    // =========================================================================
    // PRIVATE – Slug 產生
    // =========================================================================

    private function generate_slug( string $romaji ): string {
        $slug = sanitize_title( $romaji );
        $slug = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $slug ) );
        $slug = trim( $slug, '-' );

        if ( $slug === '' ) {
            $slug = 'anime-' . time();
        }

        $original_slug = $slug;
        $counter       = 1;

        while ( get_page_by_path( $slug, OBJECT, 'anime' ) ) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    // =========================================================================
    // PRIVATE – 儲存 Meta
    // =========================================================================

    private function save_post_meta( int $post_id, array $data ): void {

        // ── ACA：Bangumi ID 手動設定保護 ──────────────────────────────────────
        // 若已有 anime_bangumi_id 且標記為手動設定，則跳過覆蓋
        $existing_bgm_id    = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );
        $is_manually_set    = (bool) get_post_meta( $post_id, '_bangumi_id_manually_set', true );
        $new_bgm_id         = (int) ( $data['bangumi_id'] ?? 0 );
        $skip_bgm_overwrite = ( $existing_bgm_id > 0 && $is_manually_set );

        $meta_fields = [
            // IDs
            'anime_anilist_id'       => $data['anilist_id']          ?? 0,
            'anime_mal_id'           => $data['mal_id']              ?? 0,
            'anime_animethemes_id'   => $data['animethemes_slug']    ?? '',

            // Titles
            'anime_title_chinese'    => $data['anime_title_chinese'] ?? '',
            'anime_title_romaji'     => $data['anime_title_romaji']  ?? '',
            'anime_title_english'    => $data['anime_title_english'] ?? '',
            'anime_title_native'     => $data['anime_title_native']  ?? '',

            // Classification
            'anime_format'           => $data['anime_format']        ?? '',
            'anime_status'           => $data['anime_status']        ?? '',
            'anime_season'           => strtoupper( $data['anime_season'] ?? '' ), // AW
            'anime_season_year'      => $data['anime_season_year']   ?? 0,
            'anime_source'           => $data['anime_source']        ?? '',
            'anime_episodes'         => $data['anime_episodes']      ?? 0,
            'anime_duration'         => $data['anime_duration']      ?? 0,

            // Studios
            'anime_studios'          => $data['anime_studios']       ?? '',

            // Scores
            'anime_score_anilist'    => $data['anime_score_anilist'] ?? 0,
            'anime_score_bangumi'    => $data['anime_score_bangumi'] ?? 0,
            'anime_score_mal'        => $data['anime_score_mal']     ?? 0,  // ABF
            'anime_popularity'       => $data['anime_popularity']    ?? 0,

            // Images
            'anime_cover_image'      => $data['anime_cover_image']   ?? '',
            'anime_banner_image'     => $data['anime_banner_image']  ?? '',
            'anime_trailer_url'      => $data['anime_trailer_url']   ?? '',

            // Synopsis
            'anime_synopsis_chinese' => $data['anime_synopsis_chinese'] ?? '',
            'anime_synopsis_english' => $data['anime_synopsis_english'] ?? '',

            // Dates
            'anime_start_date'       => $data['anime_start_date']    ?? '',
            'anime_end_date'         => $data['anime_end_date']      ?? '',

            // JSON
            'anime_streaming'        => $data['anime_streaming']     ?? '[]',
            'anime_themes'           => $data['anime_themes']        ?? '[]',
            'anime_staff_json'       => $data['anime_staff_json']    ?? '[]',
            'anime_cast_json'        => $data['anime_cast_json']     ?? '[]',
            'anime_relations_json'   => $data['anime_relations_json'] ?? '[]',
            'anime_episodes_json'    => $data['anime_episodes_json'] ?? '[]', // ABL

            // External
            'anime_official_site'    => $data['anime_official_site'] ?? '',  // ABG
            'anime_twitter_url'      => $data['anime_twitter_url']   ?? '',  // ABG
            'anime_wikipedia_url'    => $data['anime_wikipedia_url'] ?? '',  // ABJ
            'anime_external_links'   => $data['anime_external_links'] ?? '[]',
            'anime_next_airing'      => $data['anime_next_airing']   ?? '',

            // Sync
            'anime_sync_time'        => current_time( 'mysql' ),
        ];

        foreach ( $meta_fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        // ── Bangumi ID 寫入（ABO + ACA）──────────────────────────────────────
        if ( ! $skip_bgm_overwrite ) {
            // ABO：正規化，確保是正整數
            $bgm_id_normalized = absint( $new_bgm_id );
            if ( $bgm_id_normalized > 0 ) {
                update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id_normalized );
                // ABO：強制第二次寫入，確保 meta 更新成功
                update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id_normalized );
                // ACA：成功寫入後清除 pending flag
                delete_post_meta( $post_id, '_bangumi_id_pending' );
            } else {
                // 找不到 bgm_id，設定 pending flag
                update_post_meta( $post_id, '_bangumi_id_pending', 1 );
            }
        }
    }

    // =========================================================================
    // PRIVATE – 分類法
    // =========================================================================

    private function save_taxonomies( int $post_id, array $data ): void {

        // 類型（genre）
        $genre_map = [
            'Action'          => 'action',
            'Adventure'       => 'adventure',
            'Comedy'          => 'comedy',
            'Drama'           => 'drama',
            'Ecchi'           => 'ecchi',
            'Fantasy'         => 'fantasy',
            'Horror'          => 'horror',
            'Mahou Shoujo'    => 'mahou-shoujo',
            'Mecha'           => 'mecha',
            'Music'           => 'music',
            'Mystery'         => 'mystery',
            'Psychological'   => 'psychological',
            'Romance'         => 'romance',
            'Sci-Fi'          => 'sci-fi',
            'Slice of Life'   => 'slice-of-life',
            'Sports'          => 'sports',
            'Supernatural'    => 'supernatural',
            'Thriller'        => 'thriller',
        ];

        $genre_ids = [];
        foreach ( (array) ( $data['anime_genres'] ?? [] ) as $genre_name ) {
            $slug = $genre_map[ $genre_name ] ?? sanitize_title( $genre_name );
            $term = get_term_by( 'slug', $slug, 'anime_genre' );
            if ( $term ) {
                $genre_ids[] = $term->term_id;
            }
        }
        if ( ! empty( $genre_ids ) ) {
            wp_set_object_terms( $post_id, $genre_ids, 'anime_genre' );
        }

        // 季度（season）
        $season_year = (int) ( $data['anime_season_year'] ?? 0 );
        $season      = strtolower( $data['anime_season'] ?? '' );
        if ( $season_year > 0 && $season !== '' ) {
            $season_term_name = $season_year . ' ' . ucfirst( $season );
            $season_term      = get_term_by( 'name', $season_term_name, 'anime_season' );
            if ( ! $season_term ) {
                $inserted    = wp_insert_term( $season_term_name, 'anime_season' );
                $season_term = is_wp_error( $inserted ) ? null : get_term( $inserted['term_id'], 'anime_season' );
            }
            if ( $season_term ) {
                wp_set_object_terms( $post_id, [ $season_term->term_id ], 'anime_season' );
            }
        }

        // 格式（format）
        $format_map = [
            'TV'       => 'tv',
            'TV_SHORT' => 'tv-short',
            'MOVIE'    => 'movie',
            'SPECIAL'  => 'special',
            'OVA'      => 'ova',
            'ONA'      => 'ona',
            'MUSIC'    => 'music-video',
        ];
        $format      = $data['anime_format'] ?? '';
        $format_slug = $format_map[ $format ] ?? sanitize_title( $format );
        if ( $format_slug !== '' ) {
            $format_term = get_term_by( 'slug', $format_slug, 'anime_format' );
            if ( $format_term ) {
                wp_set_object_terms( $post_id, [ $format_term->term_id ], 'anime_format' );
            }
        }

        // 標籤（tags）- ABH
        $tags = (array) ( $data['anime_tags'] ?? [] );
        foreach ( $tags as $tag_name ) {
            if ( empty( $tag_name ) ) continue;
            $chinese_name = $this->resolve_tag_name( $tag_name );
            $this->find_or_create_tag( $post_id, $chinese_name, $tag_name );
        }
    }

    // =========================================================================
    // PRIVATE – 標籤處理
    // =========================================================================

    private function resolve_tag_name( string $english_name ): string {
        $map = $this->get_tag_map();
        if ( isset( $map[ $english_name ] ) ) {
            return $map[ $english_name ];
        }

        $cache_key = 'anime_sync_tag_' . md5( $english_name );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return (string) $cached;
        }

        $translated = $this->google_translate( $english_name );
        $result     = $translated ?: $english_name;

        set_transient( $cache_key, $result, 30 * DAY_IN_SECONDS );
        return $result;
    }

    private function google_translate( string $text ): string {
        $api_key = defined( 'ANIME_SYNC_GOOGLE_TRANSLATE_KEY' )
                   ? ANIME_SYNC_GOOGLE_TRANSLATE_KEY
                   : '';

        if ( $api_key === '' ) return '';

        $url = add_query_arg( [
            'q'      => $text,
            'target' => 'zh-TW',
            'source' => 'en',
            'key'    => $api_key,
        ], 'https://translation.googleapis.com/language/translate/v2' );

        $response = wp_remote_post( $url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) ) {
            // ACA：日誌遮蔽 API key，避免洩漏
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                $safe_url = add_query_arg( [
                    'q'      => $text,
                    'target' => 'zh-TW',
                    'source' => 'en',
                    'key'    => '***REDACTED***',
                ], 'https://translation.googleapis.com/language/translate/v2' );
                error_log( '[AnimeSync] Google Translate error: ' . $response->get_error_message() . ' URL: ' . $safe_url );
            }
            return '';
        }

        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return '';

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $result = $body['data']['translations'][0]['translatedText'] ?? '';

        return html_entity_decode( $result, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }

    private function find_or_create_tag( int $post_id, string $chinese_name, string $english_fallback ): void {
        // 先用中文名查找
        $term = get_term_by( 'name', $chinese_name, 'post_tag' );

        // 若無，用英文 fallback 查找
        if ( ! $term && $chinese_name !== $english_fallback ) {
            $term = get_term_by( 'name', $english_fallback, 'post_tag' );
        }

        // 都沒有就新增
        if ( ! $term ) {
            $inserted = wp_insert_term( $chinese_name, 'post_tag' );
            if ( ! is_wp_error( $inserted ) ) {
                $term = get_term( $inserted['term_id'], 'post_tag' );
            }
        }

        if ( $term && ! is_wp_error( $term ) ) {
            wp_set_object_terms( $post_id, [ $term->term_id ], 'post_tag', true );
        }
    }

    // =========================================================================
    // PRIVATE – 標籤對照表
    // =========================================================================

    private function get_tag_map(): array {
        return [
            // 敘事技法
            'Unreliable Narrator'         => '不可靠的敘事者',
            'Non-Linear Storytelling'     => '非線性敘事',
            'Frame Story'                 => '框架故事',
            'Anthology'                   => '選集',
            'Meta'                        => '元敘事',

            // 世界觀
            'Isekai'                      => '異世界',
            'Dystopia'                    => '反烏托邦',
            'Post-Apocalyptic'            => '後末日',
            'Cyberpunk'                   => '賽博龐克',
            'Steampunk'                   => '蒸汽龐克',
            'Space'                       => '宇宙',
            'Virtual World'               => '虛擬世界',
            'Urban Fantasy'               => '都市奇幻',
            'High Fantasy'                => '高魔幻想',
            'Low Fantasy'                 => '低魔幻想',
            'Historical'                  => '歷史',
            'Alternate History'           => '架空歷史',
            'Mythology'                   => '神話',

            // 角色類型
            'Anti-Hero'                   => '反英雄',
            'Villain Protagonist'         => '反派主角',
            'Overpowered Main Character'  => '主角無敵',
            'Female Protagonist'          => '女主角',
            'Male Protagonist'            => '男主角',
            'Ensemble Cast'               => '群像劇',
            'Loli'                        => '蘿莉',
            'Shota'                       => '正太',
            'Kuudere'                     => '酷系',
            'Tsundere'                    => '傲嬌',
            'Yandere'                     => '病嬌',
            'Dandere'                     => '害羞系',

            // 劇情要素
            'Revenge'                     => '復仇',
            'Coming of Age'               => '成長',
            'Redemption'                  => '救贖',
            'Time Travel'                 => '時間旅行',
            'Memory Manipulation'         => '記憶操控',
            'Parallel Universe'           => '平行宇宙',
            'Reincarnation'               => '轉生',
            'Survival'                    => '生存',
            'Thriller'                    => '驚悚',
            'Mystery'                     => '懸疑',
            'Conspiracy'                  => '陰謀',
            'Betrayal'                    => '背叛',
            'War'                         => '戰爭',
            'Politics'                    => '政治',
            'Crime'                       => '犯罪',
            'Love Triangle'               => '三角戀',
            'Arranged Marriage'           => '包辦婚姻',
            'Forbidden Love'              => '禁斷之愛',

            // 超自然/能力
            'Magic'                       => '魔法',
            'Super Power'                 => '超能力',
            'Alchemy'                     => '鍊金術',
            'Exorcism'                    => '除靈',
            'Demons'                      => '惡魔',
            'Vampires'                    => '吸血鬼',
            'Shapeshifting'               => '變形',
            'Necromancy'                  => '死靈術',
            'Elemental Powers'            => '元素能力',

            // 戰鬥
            'Swordplay'                   => '劍術',
            'Martial Arts'                => '武術',
            'Gunfights'                   => '槍戰',
            'Mecha'                       => '機甲',
            'Shounen Battles'             => '少年戰鬥',
            'Tournament Arc'              => '武鬥大會',
            'Strategy Game'               => '策略遊戲',
            'Card Game'                   => '卡牌遊戲',

            // 職業/生活
            'School Life'                 => '校園生活',
            'Slice of Life'               => '日常生活',
            'Work Life'                   => '職場',
            'Medicine'                    => '醫療',
            'Cooking'                     => '料理',
            'Music'                       => '音樂',
            'Sports'                      => '體育',
            'Idol'                        => '偶像',
            'Military'                    => '軍事',
            'Police'                      => '警察',
            'Mafia'                       => '黑道',

            // 人際關係
            'Friendship'                  => '友情',
            'Family'                      => '家庭',
            'Harem'                       => '多女主',
            'Reverse Harem'               => '逆後宮',
            'BL'                          => '男男',
            'GL'                          => '百合',
            'Romance'                     => '戀愛',
            'Age Gap'                     => '年齡差',

            // 心理/主題
            'Psychological'               => '心理',
            'Philosophy'                  => '哲學',
            'Depression'                  => '憂鬱',
            'Trauma'                      => '創傷',
            'Identity'                    => '認同',
            'Social Commentary'           => '社會評論',
            'Religion'                    => '宗教',

            // 風格
            'Comedy'                      => '喜劇',
            'Parody'                      => '惡搞',
            'Satire'                      => '諷刺',
            'Dark'                        => '黑暗',
            'Gore'                        => '血腥',
            'Ecchi'                       => '色情',
            'Fanservice'                  => '賣肉',
            'Cute Girls Doing Cute Things'=> '萌系日常',
            'Chibi'                       => 'Q版',

            // 動物/其他
            'Anthropomorphism'            => '擬人',
            'Animal Protagonists'         => '動物主角',
            'Kemonomimi'                  => '獸耳',
            'Dragons'                     => '龍',
            'Robots'                      => '機器人',

            // 常用標籤
            'Based on Manga'              => '漫改',
            'Based on Light Novel'        => '輕小說改編',
            'Based on Game'               => '遊改',
            'Based on Novel'              => '小說改編',
            'Sequel'                      => '續集',
            'Prequel'                     => '前傳',
            'Remake'                      => '重製',
            'Original'                    => '原創',
            'Short Episodes'              => '短篇',
            'Long Running'                => '長篇連載',
            'CGI'                         => 'CGI動畫',
        ];
    }

    // =========================================================================
    // PRIVATE – 查找既有文章
    // =========================================================================

    private function find_existing( int $anilist_id ): ?int {
        // ACA：加入 'fields' => 'ids'，減少記憶體用量
        $query = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'any',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => 'anime_anilist_id',
                    'value' => $anilist_id,
                    'type'  => 'NUMERIC',
                ],
                [
                    'key'   => 'anilist_id', // 舊版相容
                    'value' => $anilist_id,
                    'type'  => 'NUMERIC',
                ],
            ],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'fields'         => 'ids', // ACA：只回傳 ID，效能最佳化
        ] );

        return ! empty( $query->posts ) ? (int) $query->posts[0] : null;
    }
}

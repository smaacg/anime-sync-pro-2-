<?php
/**
 * 檔案名稱: admin/class-import-manager.php
 *
 * 變更記錄：
 * AW  – 季度儲存改為大寫
 * AT  – 標題斷詞修正
 * ABF – 儲存 MAL 評分
 * ABG – 儲存 Bangumi 評分
 * ABH – 儲存 Wikipedia URL
 * ABI – 儲存串流連結
 * ABJ – 儲存劇照/橫幅
 * ABK – 儲存 AnimeThemes
 * ABM – 儲存關係作品 JSON
 * ABO – 防止覆蓋手動設定的 Bangumi ID
 * ACA – Bangumi 缺失警告 + bangumi_missing flag
 * ACB – import_single() 改呼叫 get_core_anime_data()，目標 < 15s
 *        新增 enrich_single()，供 WP-Cron 補抓第二段資料
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Import_Manager {

    private Anime_Sync_API_Handler  $api_handler;
    private Anime_Sync_CN_Converter $cn_converter;

    public function __construct(
        Anime_Sync_API_Handler  $api_handler,
        Anime_Sync_CN_Converter $cn_converter
    ) {
        $this->api_handler  = $api_handler;
        $this->cn_converter = $cn_converter;
    }

    // =========================================================================
    // PUBLIC – 單筆匯入（ACB：改用 get_core_anime_data，< 15 秒）
    // =========================================================================

    public function import_single( int $anilist_id, ?int $bangumi_id = null ): array {

        // 1. 取得核心資料（ACB：不再呼叫 get_full_anime_data）
        $anime_data = $this->api_handler->get_core_anime_data( $anilist_id, 0, $bangumi_id );

        if ( is_wp_error( $anime_data ) ) {
            return [
                'success' => false,
                'message' => '資料取得失敗：' . $anime_data->get_error_message(),
            ];
        }

        // 2. 驗證 AniList ID
        if ( empty( $anime_data['anilist_id'] ) ) {
            return [
                'success' => false,
                'message' => '無效的 AniList 資料（缺少 anilist_id）',
            ];
        }

        // 3. 整理摘要旗標
        $has_bangumi    = ! empty( $anime_data['bangumi_id'] ) && (int) $anime_data['bangumi_id'] > 0;
        $has_chinese    = ! empty( $anime_data['anime_title_chinese'] );
        $has_synopsis   = ! empty( $anime_data['anime_synopsis_chinese'] );
        $has_cover      = ! empty( $anime_data['anime_cover_image'] );
        $has_streaming  = ! empty( $anime_data['anime_streaming'] ) && $anime_data['anime_streaming'] !== '[]';

        $summary = implode( ' | ', array_filter( [
            $has_chinese   ? '✅ 中文標題'   : '⚠️ 無中文標題',
            $has_bangumi   ? '✅ Bangumi'    : '⚠️ 缺 Bangumi',
            $has_synopsis  ? '✅ 簡介'       : null,
            $has_cover     ? '✅ 封面'       : '⚠️ 無封面',
            $has_streaming ? '✅ 串流'       : null,
            '⏳ 待補抓：聲優/主題曲/Wikipedia',
        ] ) );

        // 4. 檢查是否已存在
        $existing_id = $this->find_existing( $anilist_id );
        $is_update   = (bool) $existing_id;

        // 5. 生成標題與 slug
        $post_title = ! empty( $anime_data['anime_title_chinese'] )
            ? $anime_data['anime_title_chinese']
            : ( $anime_data['anime_title_romaji'] ?? "Anime {$anilist_id}" );

        $post_slug = $this->generate_slug( $anime_data );

        // 6. 建立或更新文章
        $post_data = [
            'post_type'   => 'anime',
            'post_title'  => $post_title,
            'post_name'   => $post_slug,
            'post_status' => 'draft',
            'post_author' => get_current_user_id() ?: 1,
        ];

        if ( $is_update ) {
            $post_data['ID'] = $existing_id;
            $post_id = wp_update_post( $post_data, true );
        } else {
            $post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            return [
                'success' => false,
                'message' => '文章建立失敗：' . $post_id->get_error_message(),
            ];
        }

        // 7. 儲存 Meta
        $this->save_post_meta( $post_id, $anime_data );

        // 8. 首次匯入鎖定
        if ( ! $is_update ) {
            $this->apply_first_import_locks( $post_id, $anime_data );
        }

        // 9. 設定特色圖片
        if ( ! empty( $anime_data['anime_cover_image'] ) ) {
            $this->set_featured_image( $post_id, $anime_data['anime_cover_image'], $post_title );
        }

        // 10. 套用分類法
        $this->save_taxonomies( $post_id, $anime_data );

        // 11. 排程 Cron 補抓（ACB）
        if ( ! wp_next_scheduled( 'anime_sync_enrich_post', [ $post_id ] ) ) {
            wp_schedule_single_event( time() + 60, 'anime_sync_enrich_post', [ $post_id ] );
        }

        // 12. 組裝回傳
        $display_title = $anime_data['anime_title_chinese'] ?: $anime_data['anime_title_romaji'] ?: "ID {$anilist_id}";
        $action_label  = $is_update ? '已更新' : '已匯入';
        $base_message  = "{$action_label} – {$display_title} (ID {$anilist_id})";

        $bangumi_missing = ! $has_bangumi;
        if ( $bangumi_missing ) {
            $base_message .= ' ⚠️ Bangumi ID 未找到，將於背景補抓';
        }

        return [
            'success'         => true,
            'message'         => $base_message,
            'post_id'         => $post_id,
            'edit_url'        => get_edit_post_link( $post_id, 'raw' ),
            'summary'         => $summary,
            'bangumi_missing' => $bangumi_missing,
            'needs_enrich'    => true,
        ];
    }

    // =========================================================================
    // PUBLIC – 補抓第二段資料（ACB 新增，供 WP-Cron 或手動觸發）
    // =========================================================================

    public function enrich_single( int $post_id ): array|\WP_Error {
        // 避免重複補抓
        if ( get_post_meta( $post_id, '_enriched_at', true ) ) {
            return new \WP_Error( 'already_enriched', "Post {$post_id} already enriched." );
        }
        return $this->api_handler->enrich_anime_data( $post_id );
    }

    // =========================================================================
    // PRIVATE – 首次匯入鎖定欄位
    // =========================================================================

    private function apply_first_import_locks( int $post_id, array $data ): void {
        $lock_fields = [
            'anime_cover_image'      => $data['anime_cover_image']      ?? '',
            'anime_banner_image'     => $data['anime_banner_image']     ?? '',
            'anime_trailer_url'      => $data['anime_trailer_url']      ?? '',
            'anime_synopsis_chinese' => $data['anime_synopsis_chinese'] ?? '',
        ];
        foreach ( $lock_fields as $key => $val ) {
            if ( $val !== '' ) {
                update_post_meta( $post_id, "_lock_{$key}", 1 );
            }
        }
    }

    // =========================================================================
    // PRIVATE – 產生 Slug
    // =========================================================================

    private function generate_slug( array $data ): string {
        $candidates = array_filter( [
            $data['anime_title_romaji']  ?? '',
            $data['anime_title_english'] ?? '',
            'anime-' . ( $data['anilist_id'] ?? 0 ),
        ] );

        $raw  = reset( $candidates );
        $slug = sanitize_title( $raw );
        if ( $slug === '' ) $slug = 'anime-' . ( $data['anilist_id'] ?? 0 );

        // 確保唯一性
        $original = $slug;
        $suffix   = 1;
        while ( get_page_by_path( $slug, OBJECT, 'anime' ) ) {
            $slug = $original . '-' . $suffix++;
        }
        return $slug;
    }

    // =========================================================================
    // PRIVATE – 儲存 Post Meta
    // =========================================================================

    private function save_post_meta( int $post_id, array $data ): void {

        $meta_map = [
            // IDs
            'anime_anilist_id'       => $data['anilist_id']          ?? 0,
            'anime_mal_id'           => $data['mal_id']              ?? 0,
            'animethemes_slug'       => $data['animethemes_slug']    ?? '',
            // 標題
            'anime_title_chinese'    => $data['anime_title_chinese'] ?? '',
            'anime_title_romaji'     => $data['anime_title_romaji']  ?? '',
            'anime_title_english'    => $data['anime_title_english'] ?? '',
            'anime_title_native'     => $data['anime_title_native']  ?? '',
            // 分類
            'anime_format'           => $data['anime_format']        ?? '',
            'anime_status'           => $data['anime_status']        ?? '',
            'anime_season'           => strtoupper( $data['anime_season'] ?? '' ),   // AW
            'anime_season_year'      => $data['anime_season_year']   ?? 0,
            'anime_source'           => $data['anime_source']        ?? '',
            'anime_episodes'         => $data['anime_episodes']      ?? 0,
            'anime_duration'         => $data['anime_duration']      ?? 0,
            'anime_studios'          => $data['anime_studios']       ?? '',
            // 評分
            'anime_score_anilist'    => $data['anime_score_anilist'] ?? 0,
            'anime_score_bangumi'    => $data['anime_score_bangumi'] ?? 0,
            'anime_score_mal'        => $data['anime_score_mal']     ?? 0,           // ABF
            'anime_popularity'       => $data['anime_popularity']    ?? 0,
            // 圖片
            'anime_cover_image'      => $data['anime_cover_image']   ?? '',          // ABJ
            'anime_banner_image'     => $data['anime_banner_image']  ?? '',          // ABJ
            'anime_trailer_url'      => $data['anime_trailer_url']   ?? '',
            // 簡介
            'anime_synopsis_chinese' => $data['anime_synopsis_chinese'] ?? '',
            'anime_synopsis_english' => $data['anime_synopsis_english'] ?? '',
            // 日期
            'anime_start_date'       => $data['anime_start_date']    ?? '',
            'anime_end_date'         => $data['anime_end_date']      ?? '',
            // JSON blobs
            'anime_streaming'        => $data['anime_streaming']     ?? '[]',        // ABI
            'anime_themes'           => $data['anime_themes']        ?? '[]',        // ABK
            'anime_staff_json'       => $data['anime_staff_json']    ?? '[]',
            'anime_cast_json'        => $data['anime_cast_json']     ?? '[]',
            'anime_relations_json'   => $data['anime_relations_json'] ?? '[]',       // ABM
            'anime_episodes_json'    => $data['anime_episodes_json'] ?? '[]',
            // 外部連結
            'anime_official_site'    => $data['anime_official_site'] ?? '',
            'anime_twitter_url'      => $data['anime_twitter_url']   ?? '',
            'anime_wikipedia_url'    => $data['anime_wikipedia_url'] ?? '',          // ABH
            'anime_external_links'   => $data['anime_external_links'] ?? '[]',
            // 其他
            'anime_next_airing'      => $data['anime_next_airing']   ?? '',
            'anime_sync_time'        => current_time( 'mysql' ),
        ];

        foreach ( $meta_map as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        // ── Bangumi ID 寫入（ABO + ACA）──────────────────────────────────────
        $bgm_id_raw     = $data['bangumi_id'] ?? null;
        $bgm_id         = $bgm_id_raw !== null ? abs( intval( $bgm_id_raw ) ) : 0;
        $manually_set   = (bool) get_post_meta( $post_id, '_bangumi_id_manually_set', true );

        if ( $bgm_id > 0 && ! $manually_set ) {
            // 寫入兩個 meta key 確保相容性
            update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
            update_post_meta( $post_id, 'bangumi_id',       $bgm_id );
            delete_post_meta( $post_id, '_bangumi_id_pending' );
        } elseif ( $bgm_id <= 0 && ! $manually_set ) {
            // 尚無 Bangumi ID，標記待補抓
            delete_post_meta( $post_id, 'anime_bangumi_id' );
            delete_post_meta( $post_id, 'bangumi_id' );
            update_post_meta( $post_id, '_bangumi_id_pending', 1 );
        }
        // manually_set = true 時完全不動 Bangumi ID

        // ── 標記需要補抓（ACB）────────────────────────────────────────────────
        if ( ! empty( $data['_needs_enrich'] ) ) {
            update_post_meta( $post_id, '_needs_enrich', 1 );
        }
    }

    // =========================================================================
    // PRIVATE – 設定特色圖片
    // =========================================================================

    private function set_featured_image( int $post_id, string $image_url, string $title ): void {
        // 若已有特色圖片且有鎖定旗標，跳過
        if ( has_post_thumbnail( $post_id ) && get_post_meta( $post_id, '_lock_anime_cover_image', true ) ) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $filename   = sanitize_file_name( 'anime-cover-' . $post_id . '-' . md5( $image_url ) . '.jpg' );
        $file_path  = $upload_dir['path'] . '/' . $filename;

        if ( ! file_exists( $file_path ) ) {
            $response = wp_remote_get( $image_url, [ 'timeout' => 15 ] );
            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return;
            $image_data = wp_remote_retrieve_body( $response );
            if ( empty( $image_data ) ) return;
            file_put_contents( $file_path, $image_data );
        }

        $file_type = wp_check_filetype( $filename );
        $attachment = [
            'post_mime_type' => $file_type['type'],
            'post_title'     => sanitize_text_field( $title ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment( $attachment, $file_path, $post_id );
        if ( is_wp_error( $attach_id ) ) return;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        set_post_thumbnail( $post_id, $attach_id );
    }

    // =========================================================================
    // PRIVATE – 儲存分類法
    // =========================================================================

    private function save_taxonomies( int $post_id, array $data ): void {

        // Genre
        if ( ! empty( $data['anime_genres'] ) && is_array( $data['anime_genres'] ) ) {
            $genre_ids = [];
            foreach ( $data['anime_genres'] as $genre_name ) {
                $genre_name = trim( (string) $genre_name );
                if ( $genre_name === '' ) continue;
                $term = term_exists( $genre_name, 'genre' );
                if ( ! $term ) $term = wp_insert_term( $genre_name, 'genre' );
                if ( ! is_wp_error( $term ) ) $genre_ids[] = (int) ( $term['term_id'] ?? $term );
            }
            if ( ! empty( $genre_ids ) ) wp_set_post_terms( $post_id, $genre_ids, 'genre' );
        }

        // Season（anime_season_tax）
        $season_year = (int) ( $data['anime_season_year'] ?? 0 );
        $season      = strtoupper( $data['anime_season'] ?? '' );
        if ( $season_year && $season ) {
            $season_label = "{$season_year} " . ucfirst( strtolower( $season ) );
            $term = term_exists( $season_label, 'anime_season_tax' );
            if ( ! $term ) $term = wp_insert_term( $season_label, 'anime_season_tax' );
            if ( ! is_wp_error( $term ) ) wp_set_post_terms( $post_id, [ (int) ( $term['term_id'] ?? $term ) ], 'anime_season_tax' );
        }

        // Format（anime_format_tax）
        $format = $data['anime_format'] ?? '';
        if ( $format !== '' ) {
            $format_slug = strtolower( str_replace( '_', '-', $format ) );
            $term = term_exists( $format_slug, 'anime_format_tax' );
            if ( ! $term ) $term = wp_insert_term( ucfirst( $format_slug ), 'anime_format_tax', [ 'slug' => $format_slug ] );
            if ( ! is_wp_error( $term ) ) wp_set_post_terms( $post_id, [ (int) ( $term['term_id'] ?? $term ) ], 'anime_format_tax' );
        }

        // Tags（post_tag）
        if ( ! empty( $data['anime_tags'] ) && is_array( $data['anime_tags'] ) ) {
            $tag_ids = [];
            foreach ( $data['anime_tags'] as $tag_name ) {
                $tag_name = trim( (string) $tag_name );
                if ( $tag_name === '' ) continue;
                $zh_name = $this->resolve_tag_name( $tag_name );
                $tag_id  = $this->find_or_create_tag( $zh_name );
                if ( $tag_id ) $tag_ids[] = $tag_id;
            }
            if ( ! empty( $tag_ids ) ) wp_set_post_terms( $post_id, $tag_ids, 'post_tag' );
        }
    }

    // =========================================================================
    // PRIVATE – Tag 名稱解析（中文對照 + Google 翻譯 fallback）
    // =========================================================================

    private function resolve_tag_name( string $en_name ): string {
        $map = $this->get_tag_map();
        if ( isset( $map[ $en_name ] ) ) return $map[ $en_name ];

        $cache_key = 'anime_sync_tag_' . md5( $en_name );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (string) $cached;

        $zh = $this->google_translate( $en_name );
        $zh = $zh ?: $en_name;
        set_transient( $cache_key, $zh, 30 * DAY_IN_SECONDS );
        return $zh;
    }

    private function google_translate( string $text ): string {
        $api_key = defined( 'GOOGLE_TRANSLATE_API_KEY' ) ? GOOGLE_TRANSLATE_API_KEY : '';
        if ( ! $api_key ) return '';

        $url = 'https://translation.googleapis.com/language/translate/v2'
             . '?q='      . rawurlencode( $text )
             . '&target=zh-TW&source=en&format=text'
             . '&key='    . rawurlencode( $api_key );

        // ABO：日誌中遮蔽 API Key
        $log_url = preg_replace( '/key=[^&]+/', 'key=***REDACTED***', $url );
        Anime_Sync_Error_Logger::log( 'debug', "Google Translate: {$log_url}" );

        $response = wp_remote_get( $url, [
            'timeout'    => 8,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return '';

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body['data']['translations'][0]['translatedText'] ?? '';
    }

    private function find_or_create_tag( string $name ): ?int {
        $name = trim( $name );
        if ( $name === '' ) return null;
        $term = term_exists( $name, 'post_tag' );
        if ( ! $term ) $term = wp_insert_term( $name, 'post_tag' );
        if ( is_wp_error( $term ) ) return null;
        return (int) ( $term['term_id'] ?? $term );
    }

    // =========================================================================
    // PRIVATE – 靜態 Tag 對照表
    // =========================================================================

    private function get_tag_map(): array {
        return [
            // ── 敘事手法 ──
            'Amnesia'                    => '失憶',
            'Revenge'                    => '復仇',
            'Reincarnation'              => '轉生',
            'Time Travel'                => '時間旅行',
            'Time Loop'                  => '時間循環',
            'Isekai'                     => '異世界',
            'Parallel World'             => '平行世界',
            'Virtual Reality'            => '虛擬實境',
            'Augmented Reality'          => '擴增實境',
            'Post-Apocalyptic'           => '末日後',
            'Dystopia'                   => '反烏托邦',
            'Utopia'                     => '烏托邦',
            'Alternate History'          => '架空歷史',
            'Historical'                 => '歷史',
            'Fictional World'            => '架空世界',

            // ── 世界觀 ──
            'Space'                      => '宇宙',
            'Space Opera'                => '太空歌劇',
            'Cyberpunk'                  => '賽博龐克',
            'Steampunk'                  => '蒸汽龐克',
            'Dieselpunk'                 => '柴油龐克',
            'Fantasy World'              => '奇幻世界',
            'High Fantasy'               => '高奇幻',
            'Low Fantasy'                => '低奇幻',
            'Urban Fantasy'              => '都市奇幻',
            'Mythology'                  => '神話',
            'Feudal Japan'               => '日本戰國',

            // ── 角色類型 ──
            'Anti-Hero'                  => '反英雄',
            'Villain Protagonist'        => '反派主角',
            'Overpowered Protagonist'    => '無敵主角',
            'Female Protagonist'         => '女主角',
            'Male Protagonist'           => '男主角',
            'Non-Human Protagonist'      => '非人類主角',
            'Ensemble Cast'              => '群像劇',
            'Kuudere'                    => '酷蛋',
            'Tsundere'                   => '傲嬌',
            'Yandere'                    => '病嬌',
            'Dandere'                    => '呆萌',

            // ── 劇情元素 ──
            'Coming of Age'              => '成長故事',
            'Redemption'                 => '救贖',
            'Found Family'               => '羈絆家族',
            'Tragedy'                    => '悲劇',
            'Comedy'                     => '喜劇',
            'Parody'                     => '搞笑惡搞',
            'Romance'                    => '戀愛',
            'Harem'                      => '後宮',
            'Reverse Harem'              => '逆後宮',
            'Love Triangle'              => '三角戀',
            'Forbidden Love'             => '禁忌之戀',
            'Arranged Marriage'          => '包辦婚姻',
            'Slice of Life'              => '日常',
            'School Life'                => '校園生活',
            'Work Life'                  => '職場生活',

            // ── 超自然/能力 ──
            'Magic'                      => '魔法',
            'Superpowers'                => '超能力',
            'Supernatural'               => '超自然',
            'Demons'                     => '惡魔',
            'Angels'                     => '天使',
            'Vampires'                   => '吸血鬼',
            'Werewolves'                 => '狼人',
            'Ghosts'                     => '鬼魂',
            'Undead'                     => '不死族',
            'Gods'                       => '神明',
            'Spirits'                    => '精靈/靈魂',
            'Witches'                    => '女巫',
            'Curses'                     => '詛咒',
            'Alchemy'                    => '煉金術',
            'Necromancy'                 => '死靈術',

            // ── 戰鬥 ──
            'Action'                     => '動作',
            'Martial Arts'               => '武術',
            'Swordplay'                  => '劍術',
            'Archery'                    => '弓術',
            'Gunfights'                  => '槍戰',
            'Mechs'                      => '機甲',
            'Military'                   => '軍事',
            'War'                        => '戰爭',
            'Battle Royale'              => '大逃殺',
            'Survival'                   => '求生',
            'Tournament'                 => '競技賽',
            'Strategy Game'              => '策略遊戲',

            // ── 職業 ──
            'Idol'                       => '偶像',
            'Musician'                   => '音樂人',
            'Detective'                  => '偵探',
            'Police'                     => '警察',
            'Samurai'                    => '武士',
            'Ninja'                      => '忍者',
            'Pirate'                     => '海盜',
            'Doctor'                     => '醫生',
            'Teacher'                    => '教師',
            'Chef'                       => '廚師',
            'Athlete'                    => '運動員',
            'Adventurer'                 => '冒險者',
            'Guild'                      => '公會',

            // ── 關係 ──
            'Siblings'                   => '兄弟姊妹',
            'Twins'                      => '雙胞胎',
            'Master-Servant'             => '主僕關係',
            'Senpai-Kohai'               => '前輩後輩',
            'Childhood Friends'          => '青梅竹馬',
            'Rivals'                     => '對手',
            'Bromance'                   => '兄弟情誼',

            // ── 心理/社會 ──
            'Psychological'              => '心理',
            'Trauma'                     => '心理創傷',
            'Mental Illness'             => '精神疾病',
            'Social Commentary'          => '社會批評',
            'Politics'                   => '政治',
            'Philosophy'                 => '哲學',
            'Religion'                   => '宗教',

            // ── 風格 ──
            'Gore'                       => '血腥暴力',
            'Horror'                     => '恐怖',
            'Ecchi'                      => '輕微色情',
            'Fanservice'                 => '福利',
            'Chibi'                      => '超可愛',
            'Moe'                        => '萌',
            'Cute Girls Doing Cute Things'=> '日常萌系',

            // ── 動物/其他 ──
            'Anthropomorphism'           => '擬人化',
            'Dragons'                    => '龍',
            'Cats'                       => '貓',
            'Dogs'                       => '狗',
            'Kemonomimi'                 => '獸耳',
            'Monster Girls'              => '娘化怪物',

            // ── 常見改編標籤 ──
            'Based on a Manga'           => '漫改',
            'Based on a Light Novel'     => '輕小說改編',
            'Based on a Novel'           => '小說改編',
            'Based on a Game'            => '遊改',
            'Based on a Visual Novel'    => '視覺小說改編',
            'Original'                   => '原創',
            'Sequel'                     => '續集',
            'Prequel'                    => '前傳',
            'Spin-Off'                   => '外傳',
        ];
    }

    // =========================================================================
    // PRIVATE – 查找已存在的文章
    // =========================================================================

    private function find_existing( int $anilist_id ): int {
        $query = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',          // 只取 ID，節省記憶體
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => 'anime_anilist_id',
                    'value'   => $anilist_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
            ],
        ] );

        return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
    }
}

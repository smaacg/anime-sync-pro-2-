<?php
/**
 * Class Anime_Sync_ID_Mapper
 *
 * Bug fixes in this version:
 *   ABB – match_best_result() 年份精準度提升
 *   ABC – normalize_title() 保留季號進行第二次搜尋
 *   ABR – normalize_title() 加入 null 防護
 *   ABS – 相似度門檻從 60% 降至 45%，加 fallback 機制
 *   ABT – query_bangumi_search() limit 從 10 提升至 25
 *   ABU – validate_bangumi_id() 年份差從 1 放寬至 2
 *   ABV – get_bangumi_id() Layer 2 新增 bangumi.tv URL 格式支援
 *   ABW – download_and_cache_map() 新增 AniList ID → Bangumi ID 直接索引
 *         （Layer 0.5），因 anime-offline-database 無 bgm.tv，改用 AniList ID 對照
 *   ABX – get_bangumi_id() 成功時自動寫入 post meta
 *   ABY – match_best_result() 加入 debug log（可透過常數關閉）
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_ID_Mapper {

    // -------------------------------------------------------------------------
    // 常數
    // -------------------------------------------------------------------------

    const MAP_DIR          = 'anime-sync-pro';
    const MAP_FILE         = 'anime_map.json';
    const MAL_INDEX_FILE   = 'mal_index.json';
    const NAME_CACHE_FILE  = 'name_cache.json';
    const META_FILE        = 'anime_map_meta.json';
    const AL_INDEX_FILE    = 'al_index.json';   // ABW：AniList ID → Bangumi ID
    const MAP_SOURCE_URL   = 'https://raw.githubusercontent.com/manami-project/anime-offline-database/master/anime-offline-database-minified.json';
    const BGM_SEARCH_URL   = 'https://api.bgm.tv/v0/search/subjects';
    const BGM_SUBJECT_URL  = 'https://api.bgm.tv/v0/subjects/';

    // ABY：設為 true 時在 debug.log 輸出比對過程，正式環境改 false
    const DEBUG_LOG        = true;

    // -------------------------------------------------------------------------
    // 屬性
    // -------------------------------------------------------------------------

    private ?array  $anime_map   = null;
    private ?array  $mal_index   = null;
    private ?array  $al_index    = null;   // ABW
    private ?array  $name_cache  = null;
    private ?string $last_error  = null;
    private string  $upload_dir;

    // -------------------------------------------------------------------------
    // 建構子
    // -------------------------------------------------------------------------

    public function __construct() {
        $uploads          = wp_upload_dir();
        $this->upload_dir = trailingslashit( $uploads['basedir'] ) . self::MAP_DIR;
    }

    // =========================================================================
    // PUBLIC – 主要對照查詢
    // =========================================================================

    /**
     * 七層查詢邏輯，回傳 Bangumi subject ID 或 null。
     *
     * Layer 0   – WP post meta（100% 準確，post_id > 0 時才查）
     * Layer 0.5 – al_index（AniList ID 直接對照，ABW 新增）
     * Layer 1   – mal_index（MAL ID 直接對照）
     * Layer 2   – AniList externalLinks 中的 bgm.tv / bangumi.tv URL
     * Layer 3   – Bangumi 搜尋 + 日文原名 + 年份
     * Layer 4   – Bangumi 搜尋 + 中文標題 + 年份
     * Layer 5   – 設定 _bangumi_id_pending flag，回傳 null
     */
    public function get_bangumi_id( array $anime_data ): ?int {

        $anilist_id    = (int)    ( $anime_data['anilist_id']     ?? 0   );
        $mal_id        = isset( $anime_data['mal_id'] ) && $anime_data['mal_id'] !== null
                         ? (int) $anime_data['mal_id'] : null;
        $post_id       = (int)    ( $anime_data['post_id']        ?? 0   );
        $title_native  = (string) ( $anime_data['title_native']   ?? '' );
        $title_romaji  = (string) ( $anime_data['title_romaji']   ?? '' );
        $title_chinese = (string) ( $anime_data['title_chinese']  ?? '' );
        $season_year   = (int)    ( $anime_data['season_year']    ?? 0   );
        $ext_links     =           $anime_data['external_links']  ?? [];

        // ── Layer 0：WP post meta ─────────────────────────────────────────────
        if ( $post_id > 0 ) {
            $meta_bgm = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );
            if ( $meta_bgm > 0 ) return $meta_bgm;
        }

        // ── Layer 0.5：al_index（AniList ID → Bangumi ID，ABW）───────────────
        if ( $anilist_id > 0 ) {
            $this->load_al_index();
            $bgm_id = $this->al_index[ $anilist_id ] ?? null;
            if ( $bgm_id ) {
                $bgm_id = (int) $bgm_id;
                // ABX：成功寫入 post meta
                if ( $post_id > 0 ) {
                    update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
                }
                return $bgm_id;
            }
        }

        // ── Layer 1：mal_index ────────────────────────────────────────────────
        if ( $mal_id && $mal_id > 0 ) {
            $this->load_mal_index();
            $bgm_id = $this->mal_index[ $mal_id ] ?? null;
            if ( $bgm_id ) {
                // ABU：validate 年份差放寬至 2
                $validated = $this->validate_bangumi_id( (int) $bgm_id, $title_native, $season_year );
                if ( $validated ) {
                    // ABX：成功寫入 post meta
                    if ( $post_id > 0 ) {
                        update_post_meta( $post_id, 'anime_bangumi_id', $validated );
                    }
                    return $validated;
                }
            }
        }

        // ── Layer 2：AniList externalLinks → bgm.tv / bangumi.tv ─────────────
        // ABV：同時支援 bgm.tv 和 bangumi.tv 兩種 URL 格式
        foreach ( $ext_links as $link ) {
            $url = $link['url'] ?? '';
            if ( preg_match( '#(?:bgm|bangumi)\.tv/subject/(\d+)#', $url, $m ) ) {
                $bgm_id = (int) $m[1];
                if ( $bgm_id > 0 ) {
                    // ABX：成功寫入 post meta
                    if ( $post_id > 0 ) {
                        update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
                    }
                    return $bgm_id;
                }
            }
        }

        // ── Layer 3：Bangumi 搜尋 + 日文原名 ─────────────────────────────────
        if ( $title_native !== '' ) {
            $bgm_id = $this->search_bangumi_by_title( $title_native, $season_year );
            if ( $bgm_id ) {
                if ( $post_id > 0 ) update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 4a：Bangumi 搜尋 + 中文標題 ────────────────────────────────
        if ( $title_chinese !== '' ) {
            $bgm_id = $this->search_bangumi_by_title( $title_chinese, $season_year );
            if ( $bgm_id ) {
                if ( $post_id > 0 ) update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 4b：Bangumi 搜尋 + 羅馬拼音標題（ABS 新增）────────────────
        if ( $title_romaji !== '' && $title_romaji !== $title_native ) {
            $bgm_id = $this->search_bangumi_by_title( $title_romaji, $season_year );
            if ( $bgm_id ) {
                if ( $post_id > 0 ) update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 5：設定 pending flag ────────────────────────────────────────
        if ( $post_id > 0 ) {
            update_post_meta( $post_id, '_bangumi_id_pending', 1 );
        }
        $this->last_error = "Bangumi ID not found for AniList ID {$anilist_id}";
        return null;
    }

    // =========================================================================
    // PUBLIC – 工具方法
    // =========================================================================

    public function get_chinese_title( int $bgm_id ): string {
        $this->load_name_cache();
        return $this->name_cache[ $bgm_id ] ?? '';
    }

    public function get_last_error(): ?string {
        return $this->last_error;
    }

    public function get_map_status(): array {
        $path = $this->get_file_path( self::MAP_FILE );
        $meta = $this->load_json_file( $this->get_file_path( self::META_FILE ) );

        if ( ! file_exists( $path ) ) {
            return [
                'exists'      => false,
                'path'        => $path,
                'size'        => 0,
                'entry_count' => 0,
                'mal_count'   => 0,
                'al_count'    => 0,
                'last_updated'=> '',
                'age_hours'   => null,
                'version'     => '',
            ];
        }

        $size      = filesize( $path );
        $modified  = filemtime( $path );
        $age_hours = $modified ? round( ( time() - $modified ) / 3600, 1 ) : null;

        return [
            'exists'       => true,
            'path'         => $path,
            'size'         => $size,
            'entry_count'  => $meta['entry_count']  ?? 0,
            'mal_count'    => $meta['mal_count']     ?? 0,
            'al_count'     => $meta['al_count']      ?? 0,   // ABW
            'last_updated' => $meta['generated_at']  ?? gmdate( 'Y-m-d H:i:s', $modified ),
            'age_hours'    => $age_hours,
            'version'      => $meta['version']       ?? '',
        ];
    }

    // =========================================================================
    // PUBLIC – 下載與索引
    // =========================================================================

    public function download_and_cache_map(): bool {
        if ( ! wp_mkdir_p( $this->upload_dir ) ) {
            $this->last_error = 'Cannot create upload directory: ' . $this->upload_dir;
            return false;
        }

        $response = wp_remote_get( self::MAP_SOURCE_URL, [
            'timeout'    => 120,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            $this->last_error = $response->get_error_message();
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            $this->last_error = "HTTP {$code} fetching map.";
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
            $this->last_error = 'Invalid map JSON structure.';
            return false;
        }

        $map        = [];
        $mal_index  = [];
        $al_index   = [];   // ABW：AniList ID → Bangumi ID
        $name_cache = [];

        foreach ( $data['data'] as $entry ) {
            $sources = $entry['sources'] ?? [];
            $al_id   = null;
            $mal_id  = null;
            $bgm_id  = null;

            foreach ( $sources as $src ) {
                if ( preg_match( '#anilist\.co/anime/(\d+)#', $src, $m ) )
                    $al_id = (int) $m[1];
                if ( preg_match( '#myanimelist\.net/anime/(\d+)#', $src, $m ) )
                    $mal_id = (int) $m[1];
                // ABV：同時支援 bgm.tv 和 bangumi.tv
                if ( preg_match( '#(?:bgm|bangumi)\.tv/subject/(\d+)#', $src, $m ) )
                    $bgm_id = (int) $m[1];
            }

            if ( $al_id  && $bgm_id ) $al_index[ $al_id ]   = $bgm_id;  // ABW
            if ( $al_id  && $bgm_id ) $map[ $al_id ]         = $bgm_id;
            if ( $mal_id && $bgm_id ) $mal_index[ $mal_id ]  = $bgm_id;

            // 快取中文標題
            if ( $bgm_id ) {
                $titles = $entry['titles'] ?? [];
                foreach ( $titles as $t ) {
                    $lang = $t['lang'] ?? '';
                    if ( $lang === 'zh-Hans' || $lang === 'zh-Hant' ) {
                        $name_cache[ $bgm_id ] = $t['title'] ?? '';
                        break;
                    }
                }
                // synonyms 也嘗試找中文（anime-offline-database 無 titles 欄位時）
                if ( empty( $name_cache[ $bgm_id ] ) ) {
                    foreach ( $entry['synonyms'] ?? [] as $syn ) {
                        if ( preg_match( '/\p{Han}/u', $syn ) ) {
                            $name_cache[ $bgm_id ] = $syn;
                            break;
                        }
                    }
                }
            }
        }

        $entry_count = count( $map );
        $mal_count   = count( $mal_index );
        $al_count    = count( $al_index );

        $this->write_json( self::MAP_FILE,       $map );
        $this->write_json( self::MAL_INDEX_FILE, $mal_index );
        $this->write_json( self::AL_INDEX_FILE,  $al_index );   // ABW
        $this->write_json( self::NAME_CACHE_FILE, $name_cache );
        $this->write_json( self::META_FILE, [
            'source_url'  => self::MAP_SOURCE_URL,
            'version'     => $data['license']['url'] ?? '1.0',
            'entry_count' => $entry_count,
            'mal_count'   => $mal_count,
            'al_count'    => $al_count,
            'generated_at'=> gmdate( 'Y-m-d H:i:s' ),
            'etag'        => wp_remote_retrieve_header( $response, 'etag' ),
        ] );

        $this->anime_map  = $map;
        $this->mal_index  = $mal_index;
        $this->al_index   = $al_index;
        $this->name_cache = $name_cache;

        return true;
    }

    public function rebuild_indexes(): bool {
        $this->load_anime_map();
        if ( empty( $this->anime_map ) ) {
            $this->last_error = 'anime_map is empty, cannot rebuild indexes.';
            return false;
        }

        $this->mal_index  = null;
        $this->al_index   = null;
        $this->name_cache = null;

        $this->load_mal_index();
        $this->load_al_index();
        $this->load_name_cache();

        return true;
    }

    // =========================================================================
    // PRIVATE – Bangumi 搜尋
    // =========================================================================

    /**
     * ABC 修正流程：
     *   第一次：normalize_title()（去除季號）搜尋
     *   第二次：若無結果，用原始標題（保留季號）重試
     */
    private function search_bangumi_by_title( string $title, int $year ): ?int {

        $normalized = $this->normalize_title( $title );

        if ( $normalized !== '' ) {
            $results = $this->query_bangumi_search( $normalized );
            $best    = $this->match_best_result( $results, $normalized, $year );
            if ( $best ) return $best;
        }

        // ABC：第二次搜尋用原始標題（保留季號）
        if ( $title !== $normalized && $title !== '' ) {
            $results2 = $this->query_bangumi_search( $title );
            $best2    = $this->match_best_result( $results2, $title, $year );
            if ( $best2 ) return $best2;
        }

        return null;
    }

    /**
     * ABT：limit 提升至 25，確保熱門作品同名時能找到正確條目。
     */
    private function query_bangumi_search( string $keyword ): array {
        $url = add_query_arg( [
            'limit'  => 25,  // ABT：從 10 提升至 25
            'offset' => 0,
        ], self::BGM_SEARCH_URL );

        $body = wp_json_encode( [
            'keyword' => $keyword,
            'filter'  => [ 'type' => [ 2 ] ],
        ] );

        $response = wp_remote_post( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => $body,
        ] );

        if ( is_wp_error( $response ) ) return [];
        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['data'] ?? [];
    }

    /**
     * ABS + ABB + ABR：
     *   - 相似度門檻從 60% 降至 45%
     *   - 加入 fallback：若有任何結果，至少回傳相似度最高的
     *   - 年份差 secondary 從 2 放寬，同時提供更寬鬆的 fallback
     *   - ABY：加入 debug log
     */
    private function match_best_result( array $results, string $title, int $year ): ?int {

        if ( empty( $results ) ) return null;

        $normalized_input = $this->normalize_title( $title );

        $candidates_primary   = [];
        $candidates_secondary = [];
        $candidates_no_year   = [];
        $all_candidates       = [];   // ABS fallback 用

        foreach ( $results as $subject ) {
            $bgm_id = (int) ( $subject['id'] ?? 0 );
            if ( ! $bgm_id ) continue;

            // ABR：null 防護
            $bgm_title_cn = (string) ( $subject['name_cn'] ?? '' );
            $bgm_title_ja = (string) ( $subject['name']    ?? '' );

            $sim_cn = 0.0;
            $sim_ja = 0.0;
            similar_text( $normalized_input, $this->normalize_title( $bgm_title_cn ), $sim_cn );
            similar_text( $normalized_input, $this->normalize_title( $bgm_title_ja ), $sim_ja );

            // ABS：雙向比對，取較高值
            $sim_cn_rev = 0.0;
            $sim_ja_rev = 0.0;
            if ( $bgm_title_cn !== '' ) {
                similar_text( $this->normalize_title( $bgm_title_cn ), $normalized_input, $sim_cn_rev );
            }
            if ( $bgm_title_ja !== '' ) {
                similar_text( $this->normalize_title( $bgm_title_ja ), $normalized_input, $sim_ja_rev );
            }
            $sim = max( $sim_cn, $sim_ja, $sim_cn_rev, $sim_ja_rev );

            // ABY：debug log
            if ( self::DEBUG_LOG ) {
                error_log( sprintf(
                    '[BGM MATCH] INPUT: %s | BGM: %s / %s | SIM: %.1f%%',
                    $normalized_input, $bgm_title_ja, $bgm_title_cn, $sim
                ) );
            }

            // ABS：門檻從 60% 降至 45%
            if ( $sim < 45.0 ) continue;

            $bgm_year  = $this->get_bangumi_year( $subject );
            $year_diff = ( $year > 0 && $bgm_year > 0 ) ? abs( $bgm_year - $year ) : 999;

            $candidate = [
                'id'        => $bgm_id,
                'sim'       => $sim,
                'year_diff' => $year_diff,
            ];

            $all_candidates[] = $candidate;

            if ( ! $bgm_year ) {
                $candidates_no_year[] = $candidate;
            } elseif ( $year_diff <= 1 ) {
                $candidates_primary[]   = $candidate;
            } else {
                $candidates_secondary[] = $candidate;
            }
        }

        // ── 選取策略 ────────────────────────────────────────────────────────

        // 第一優先：年份差 0–1，取相似度最高
        if ( ! empty( $candidates_primary ) ) {
            usort( $candidates_primary, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
            return $candidates_primary[0]['id'];
        }

        // 第二優先：年份差 2–3，ABU 放寬
        if ( ! empty( $candidates_secondary ) ) {
            usort( $candidates_secondary, function ( $a, $b ) {
                if ( $a['year_diff'] !== $b['year_diff'] ) {
                    return $a['year_diff'] <=> $b['year_diff'];
                }
                return $b['sim'] <=> $a['sim'];
            } );
            // ABU：年份差從 2 放寬至 3
            if ( $candidates_secondary[0]['year_diff'] <= 3 ) {
                return $candidates_secondary[0]['id'];
            }
        }

        // 第三優先：無年份資訊，相似度 >= 80%
        if ( ! empty( $candidates_no_year ) ) {
            usort( $candidates_no_year, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
            if ( $candidates_no_year[0]['sim'] >= 80.0 ) {
                return $candidates_no_year[0]['id'];
            }
        }

        // ABS fallback：若有任何通過 45% 門檻的候選，至少回傳相似度最高者
        // （避免完全找不到的情況）
        if ( ! empty( $all_candidates ) ) {
            usort( $all_candidates, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
            // 只在相似度 >= 65% 時才 fallback，避免錯誤配對
            if ( $all_candidates[0]['sim'] >= 65.0 ) {
                if ( self::DEBUG_LOG ) {
                    error_log( '[BGM MATCH] Using fallback candidate ID=' .
                        $all_candidates[0]['id'] . ' sim=' . $all_candidates[0]['sim'] );
                }
                return $all_candidates[0]['id'];
            }
        }

        return null;
    }

    // =========================================================================
    // PRIVATE – 標題正規化
    // =========================================================================

    /**
     * ABR + ABS：null 防護 + 全形羅馬數字轉換。
     *
     * 注意：這個函式會移除季號，ABC 的第二次搜尋直接用原始標題繞過此函式。
     */
    private function normalize_title( string $title ): string {

        if ( $title === '' ) return '';

        $title = mb_strtolower( $title );

        // ABS：全形羅馬數字 → 阿拉伯數字（處理 無職転生Ⅱ 這類情況）
        $roman_map = [
            'ⅻ' => '12', 'ⅺ' => '11', 'ⅹ' => '10',
            'ⅸ' => '9',  'ⅷ' => '8',  'ⅶ' => '7',
            'ⅵ' => '6',  'ⅴ' => '5',  'ⅳ' => '4',
            'ⅲ' => '3',  'ⅱ' => '2',  'ⅰ' => '1',
        ];
        $title = str_replace(
            array_keys( $roman_map ),
            array_values( $roman_map ),
            $title
        );

        // 括號內容移除
        $title = preg_replace( '/[\(\（][^\)\）]*[\)\）]/', '', $title );
        $title = preg_replace( '/[\[【][^\]】]*[\]】]/',   '', $title );

        // 季號移除（英文 Season/Part/Cour）
        $title = preg_replace(
            '/\b(?:season|part|cour|chapter|arc)\s*\d+\b/i',
            '',
            $title
        );
        $title = preg_replace(
            '/\b\d+(?:st|nd|rd|th)\s+season\b/i',
            '',
            $title
        );

        // 日文第N期 → 移除
        $title = preg_replace( '/第\s*\d+\s*[期季クール]/u', '', $title );

        $title = preg_replace( '/\s+\d+$/', '', $title );
        $title = preg_replace( '/\b(19|20)\d{2}\b/', '', $title );
        $title = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $title );
        $title = preg_replace( '/\s+/', ' ', $title );

        return trim( $title );
    }

    // =========================================================================
    // PRIVATE – Bangumi 年份解析
    // =========================================================================

    private function get_bangumi_year( array $subject ): int {
        $date = $subject['date'] ?? $subject['air_date'] ?? '';
        if ( $date === '' ) return 0;
        if ( preg_match( '/^(\d{4})/', $date, $m ) ) {
            return (int) $m[1];
        }
        return 0;
    }

    // =========================================================================
    // PRIVATE – Bangumi ID 驗證
    // =========================================================================

    /**
     * ABU：年份差從 1 放寬至 2。
     */
    private function validate_bangumi_id( int $bgm_id, string $title, int $year ): ?int {
        $cache_key = 'anime_sync_bgm_validate_' . $bgm_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return (int) $cached > 0 ? (int) $cached : null;
        }

        $response = wp_remote_get( self::BGM_SUBJECT_URL . $bgm_id, [
            'timeout'    => 10,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ||
             (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        $bgm_year = $this->get_bangumi_year( $data );

        // ABU：年份差從 1 放寬至 2
        if ( $year > 0 && $bgm_year > 0 && abs( $bgm_year - $year ) > 2 ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        // ABR：null 防護
        $bgm_title = (string) ( $data['name_cn'] ?? $data['name'] ?? '' );
        $sim       = 0.0;
        similar_text(
            $this->normalize_title( (string) $title ),
            $this->normalize_title( $bgm_title ),
            $sim
        );

        // ABS：相似度門檻從 40% 降至 30%
        if ( $sim < 30.0 ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        set_transient( $cache_key, $bgm_id, 7 * DAY_IN_SECONDS );
        return $bgm_id;
    }

    // =========================================================================
    // PRIVATE – 檔案讀寫
    // =========================================================================

    private function load_anime_map(): void {
        if ( $this->anime_map !== null ) return;
        $this->anime_map = $this->load_json_file( $this->get_file_path( self::MAP_FILE ) ) ?? [];
    }

    private function load_mal_index(): void {
        if ( $this->mal_index !== null ) return;
        $this->mal_index = $this->load_json_file( $this->get_file_path( self::MAL_INDEX_FILE ) ) ?? [];
    }

    // ABW：新增 al_index 讀取
    private function load_al_index(): void {
        if ( $this->al_index !== null ) return;
        $this->al_index = $this->load_json_file( $this->get_file_path( self::AL_INDEX_FILE ) ) ?? [];
    }

    private function load_name_cache(): void {
        if ( $this->name_cache !== null ) return;
        $this->name_cache = $this->load_json_file( $this->get_file_path( self::NAME_CACHE_FILE ) ) ?? [];
    }

    private function load_json_file( string $path ): ?array {
        if ( ! file_exists( $path ) ) return null;
        $content = file_get_contents( $path );
        if ( $content === false ) return null;
        $decoded = json_decode( $content, true );
        return is_array( $decoded ) ? $decoded : null;
    }

    private function write_json( string $filename, array $data ): bool {
        $path    = $this->get_file_path( $filename );
        $content = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
        if ( $content === false ) return false;
        if ( ! wp_mkdir_p( $this->upload_dir ) ) return false;
        $result = file_put_contents( $path, $content, LOCK_EX );
        return $result !== false;
    }

    private function get_file_path( string $filename ): string {
        return trailingslashit( $this->upload_dir ) . $filename;
    }
}

<?php
/**
 * Class Anime_Sync_ID_Mapper
 *
 * Bug fixes in this version:
 *   ABB – match_best_result() 年份精準度提升：
 *         優先選最接近目標年份的結果，而非遇到年份差 >1 就放棄。
 *   ABC – normalize_title() 保留季號進行第二次搜尋：
 *         第一次搜尋用去季號標題，若無結果則用原始標題（保留 Season 2 等）重試。
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
    const MAP_SOURCE_URL   = 'https://raw.githubusercontent.com/manami-project/anime-offline-database/master/anime-offline-database-minified.json';
    const BGM_SEARCH_URL   = 'https://api.bgm.tv/v0/search/subjects';
    const BGM_SUBJECT_URL  = 'https://api.bgm.tv/v0/subjects/';

    // -------------------------------------------------------------------------
    // 屬性
    // -------------------------------------------------------------------------

    private ?array  $anime_map   = null;
    private ?array  $mal_index   = null;
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
     * 六層查詢邏輯，回傳 Bangumi subject ID 或 null。
     *
     * Layer 0 – WP post meta（100% 準確，post_id > 0 時才查）
     * Layer 1 – mal_index（MAL ID 直接對照，~53%）
     * Layer 2 – AniList externalLinks 中的 bgm.tv URL（高準確率）
     * Layer 3 – Bangumi 搜尋 + 日文原名 + 年份 ±1（~20–25%）
     * Layer 4 – Bangumi 搜尋 + 中文標題 + 年份（~3–5%）
     * Layer 5 – 設定 _bangumi_id_pending flag，回傳 null
     */
    public function get_bangumi_id( array $anime_data ): ?int {

        $anilist_id    = (int)    ( $anime_data['anilist_id']     ?? 0   );
        $mal_id        = isset( $anime_data['mal_id'] ) && $anime_data['mal_id'] !== null
                         ? (int) $anime_data['mal_id'] : null;
        $post_id       = (int)    ( $anime_data['post_id']        ?? 0   );
        $title_native  =           $anime_data['title_native']    ?? '';
        $title_chinese =           $anime_data['title_chinese']   ?? '';
        $season_year   = (int)    ( $anime_data['season_year']    ?? 0   );
        $ext_links     =           $anime_data['external_links']  ?? [];

        // ── Layer 0：WP post meta ─────────────────────────────────────────────
        if ( $post_id > 0 ) {
            $meta_bgm = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );
            if ( $meta_bgm > 0 ) return $meta_bgm;
        }

        // ── Layer 1：mal_index ────────────────────────────────────────────────
        if ( $mal_id && $mal_id > 0 ) {
            $this->load_mal_index();
            $bgm_id = $this->mal_index[ $mal_id ] ?? null;
            if ( $bgm_id ) {
                $validated = $this->validate_bangumi_id( (int) $bgm_id, $title_native, $season_year );
                if ( $validated ) return $validated;
            }
        }

        // ── Layer 2：AniList externalLinks → bgm.tv ───────────────────────────
        foreach ( $ext_links as $link ) {
            $url = $link['url'] ?? '';
            if ( preg_match( '#bgm\.tv/subject/(\d+)#', $url, $m ) ) {
                $bgm_id = (int) $m[1];
                if ( $bgm_id > 0 ) return $bgm_id;
            }
        }

        // ── Layer 3：Bangumi 搜尋 + 日文原名 ─────────────────────────────────
        if ( $title_native !== '' ) {
            $bgm_id = $this->search_bangumi_by_title( $title_native, $season_year );
            if ( $bgm_id ) return $bgm_id;
        }

        // ── Layer 4：Bangumi 搜尋 + 中文標題 ─────────────────────────────────
        if ( $title_chinese !== '' ) {
            $bgm_id = $this->search_bangumi_by_title( $title_chinese, $season_year );
            if ( $bgm_id ) return $bgm_id;
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

        // 轉換為 anilist_id => bangumi_id 對照表
        $map        = [];
        $mal_index  = [];
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
                if ( preg_match( '#bgm\.tv/subject/(\d+)#', $src, $m ) )
                    $bgm_id = (int) $m[1];
            }

            if ( $al_id && $bgm_id )  $map[ $al_id ]       = $bgm_id;
            if ( $mal_id && $bgm_id ) $mal_index[ $mal_id ] = $bgm_id;

            // 快取中文標題
            if ( $bgm_id ) {
                $titles = $entry['titles'] ?? [];
                foreach ( $titles as $t ) {
                    if ( ( $t['lang'] ?? '' ) === 'zh-Hans' ||
                         ( $t['lang'] ?? '' ) === 'zh-Hant' ) {
                        $name_cache[ $bgm_id ] = $t['title'] ?? '';
                        break;
                    }
                }
            }
        }

        $entry_count = count( $map );
        $mal_count   = count( $mal_index );

        // 寫入檔案
        $this->write_json( self::MAP_FILE,       $map );
        $this->write_json( self::MAL_INDEX_FILE, $mal_index );
        $this->write_json( self::NAME_CACHE_FILE, $name_cache );
        $this->write_json( self::META_FILE, [
            'source_url'  => self::MAP_SOURCE_URL,
            'version'     => $data['license']['url'] ?? '1.0',
            'entry_count' => $entry_count,
            'mal_count'   => $mal_count,
            'generated_at'=> gmdate( 'Y-m-d H:i:s' ),
            'etag'        => wp_remote_retrieve_header( $response, 'etag' ),
        ] );

        // 重設記憶體快取
        $this->anime_map  = $map;
        $this->mal_index  = $mal_index;
        $this->name_cache = $name_cache;

        return true;
    }

    public function rebuild_indexes(): bool {
        $this->load_anime_map();
        if ( empty( $this->anime_map ) ) {
            $this->last_error = 'anime_map is empty, cannot rebuild indexes.';
            return false;
        }

        $mal_index  = [];
        $name_cache = [];

        // anime_map 結構：[ al_id => bgm_id ]
        // mal_index 需要從原始 MAP_FILE 重建，此處直接重載
        $raw = $this->load_json_file( $this->get_file_path( self::MAP_FILE ) );
        if ( ! is_array( $raw ) ) return false;

        foreach ( $raw as $al_id => $bgm_id ) {
            // mal_index 在 download_and_cache_map() 時已獨立建立，這裡只重載
        }

        // 重載 mal_index
        $this->mal_index = null;
        $this->load_mal_index();

        // 重載 name_cache
        $this->name_cache = null;
        $this->load_name_cache();

        return true;
    }

    // =========================================================================
    // PRIVATE – Bangumi 搜尋
    // =========================================================================

    /**
     * 以標題在 Bangumi 搜尋，回傳最佳 subject ID 或 null。
     *
     * ABC 修正流程：
     *   第一次：normalize_title()（去除季號）搜尋
     *   第二次：若無結果，用原始標題（保留季號）重試
     */
    private function search_bangumi_by_title( string $title, int $year ): ?int {

        // ── 第一次搜尋：normalize（去季號）─────────────────────────────────
        $normalized = $this->normalize_title( $title );
        if ( $normalized !== '' ) {
            $results = $this->query_bangumi_search( $normalized );
            $best    = $this->match_best_result( $results, $normalized, $year );
            if ( $best ) return $best;
        }

        // ── ABC：第二次搜尋：原始標題（保留 Season 2 等季號）───────────────
        if ( $title !== $normalized && $title !== '' ) {
            $results2 = $this->query_bangumi_search( $title );
            $best2    = $this->match_best_result( $results2, $title, $year );
            if ( $best2 ) return $best2;
        }

        return null;
    }

    /**
     * 呼叫 Bangumi v0 搜尋 API，回傳 subjects 陣列。
     */
    private function query_bangumi_search( string $keyword ): array {
        $url = add_query_arg( [
            'limit'  => 10,
            'offset' => 0,
        ], self::BGM_SEARCH_URL );

        $body = wp_json_encode( [
            'keyword' => $keyword,
            'filter'  => [ 'type' => [ 2 ] ], // type 2 = anime
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
     * ABB 修正：從搜尋結果挑選最佳符合項目。
     *
     * 修正前：年份差 >1 直接放棄整個結果。
     * 修正後：
     *   1. 先計算每筆結果與目標年份的差距。
     *   2. 篩選標題相似度 >= 0.6 的候選。
     *   3. 在候選中優先選年份差最小者（≤1 為優先，>1 為次選）。
     *   4. 若完全沒有年份資訊，仍允許依標題相似度選取。
     */
    private function match_best_result( array $results, string $title, int $year ): ?int {

        if ( empty( $results ) ) return null;

        $normalized_input = $this->normalize_title( $title );

        $candidates_primary   = []; // 年份差 0–1
        $candidates_secondary = []; // 年份差 2+
        $candidates_no_year   = []; // 無年份資訊

        foreach ( $results as $subject ) {
            $bgm_id    = (int) ( $subject['id'] ?? 0 );
            if ( ! $bgm_id ) continue;

            // ── 標題相似度 ──────────────────────────────────────────────────
            $bgm_title_cn  = $subject['name_cn'] ?? '';
            $bgm_title_ja  = $subject['name']    ?? '';

            $sim_cn = 0.0;
            $sim_ja = 0.0;
            similar_text( $normalized_input, $this->normalize_title( $bgm_title_cn ), $sim_cn );
            similar_text( $normalized_input, $this->normalize_title( $bgm_title_ja ), $sim_ja );
            $sim = max( $sim_cn, $sim_ja );

            if ( $sim < 60.0 ) continue; // 相似度門檻 60%

            // ── 年份差計算 ──────────────────────────────────────────────────
            $bgm_year = $this->get_bangumi_year( $subject );

            // 無年份資訊
            if ( ! $bgm_year ) {
                $candidates_no_year[] = [
                    'id'  => $bgm_id,
                    'sim' => $sim,
                ];
                continue;
            }

            $year_diff = $year > 0 ? abs( $bgm_year - $year ) : 999;

            $candidate = [
                'id'        => $bgm_id,
                'sim'       => $sim,
                'year_diff' => $year_diff,
            ];

            // ABB：依年份差分組，而非直接放棄
            if ( $year_diff <= 1 ) {
                $candidates_primary[]   = $candidate;
            } else {
                $candidates_secondary[] = $candidate;
            }
        }

        // ── 選取策略 ────────────────────────────────────────────────────────
        // 優先從 primary（年份差 ≤1）中取相似度最高者
        if ( ! empty( $candidates_primary ) ) {
            usort( $candidates_primary, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
            return $candidates_primary[0]['id'];
        }

        // 其次從 secondary（年份差 2+）中取年份差最小、相似度最高者
        // 這處理像「我獨自升級 Season 2」這類需要寬鬆年份匹配的情況
        if ( ! empty( $candidates_secondary ) ) {
            usort( $candidates_secondary, function ( $a, $b ) {
                // 先比年份差（小的優先）
                if ( $a['year_diff'] !== $b['year_diff'] ) {
                    return $a['year_diff'] <=> $b['year_diff'];
                }
                // 年份差相同再比相似度（大的優先）
                return $b['sim'] <=> $a['sim'];
            } );
            // 年份差 ≤2 才允許（避免選到差太遠的）
            if ( $candidates_secondary[0]['year_diff'] <= 2 ) {
                return $candidates_secondary[0]['id'];
            }
        }

        // 最後考慮無年份資訊但相似度高的結果
        if ( ! empty( $candidates_no_year ) ) {
            usort( $candidates_no_year, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
            if ( $candidates_no_year[0]['sim'] >= 80.0 ) {
                return $candidates_no_year[0]['id'];
            }
        }

        return null;
    }

    // =========================================================================
    // PRIVATE – 標題正規化
    // =========================================================================

    /**
     * 移除常見的季號、年份、括號等干擾字元，方便字串比對。
     *
     * 注意：此方法只用於「比對用的正規化副本」。
     * ABC 修正中，原始標題（含季號）會另外用於第二次搜尋，
     * 因此這裡不需要修改，維持原本移除季號的行為。
     */
    private function normalize_title( string $title ): string {
        if ( $title === '' ) return '';

        // 統一半形
        $title = mb_strtolower( $title );

        // 移除括號內容
        $title = preg_replace( '/[\(\（][^\)\）]*[\)\）]/', '', $title );
        $title = preg_replace( '/[\[【][^\]】]*[\]】]/',   '', $title );

        // 移除常見季號標記（英文）
        $title = preg_replace(
            '/\b(?:season|part|cour|chapter|arc)\s*\d+\b/i',
            '',
            $title
        );
        // 移除 "2nd season"、"3rd season" 等
        $title = preg_replace(
            '/\b\d+(?:st|nd|rd|th)\s+season\b/i',
            '',
            $title
        );
        // 移除尾端獨立數字（如 "bleach 2"）
        $title = preg_replace( '/\s+\d+$/', '', $title );

        // 移除年份（4 位數）
        $title = preg_replace( '/\b(19|20)\d{2}\b/', '', $title );

        // 移除特殊符號（保留中日文字元）
        $title = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $title );

        // 合併多餘空白
        $title = preg_replace( '/\s+/', ' ', $title );

        return trim( $title );
    }

    // =========================================================================
    // PRIVATE – Bangumi 年份解析
    // =========================================================================

    private function get_bangumi_year( array $subject ): int {
        // Bangumi v0 API 回傳 date 欄位，格式 YYYY-MM-DD 或 YYYY
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
     * 對照 mal_index 查到的 bgm_id 進行輕量驗證：
     * 呼叫 Bangumi API 確認標題與年份大致符合。
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

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        $bgm_year = $this->get_bangumi_year( $data );

        // 年份驗證：允許差距 ≤1（ABB 一致）
        if ( $year > 0 && $bgm_year > 0 && abs( $bgm_year - $year ) > 1 ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        // 標題驗證（輕量）
        $bgm_title    = $data['name_cn'] ?? $data['name'] ?? '';
        $sim          = 0.0;
        similar_text(
            $this->normalize_title( $title ),
            $this->normalize_title( $bgm_title ),
            $sim
        );

        if ( $sim < 40.0 ) {
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

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
 *   ABX – get_bangumi_id() 成功時自動寫入 post meta
 *   ABY – match_best_result() 加入 debug log（DEBUG_LOG 常數控制）
 *   ABZ – 新增 Layer -1 靜態表、Layer 1.5/1.6/1.7/1.8（BangumiExtLinker、
 *         Jikan external、AniDB 橋接），match_best_result() 加入季度 + 集數驗證，
 *         修正 $map 冗餘賦值，DEBUG_LOG 預設改 false，$ext_links 型別防護強化
 *   ACA – 成功寫入 bangumi_id 後自動清除 _bangumi_id_pending meta
 *         補全 season_to_quarter() / get_bangumi_quarter() 完整實作
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

    // anime-offline-database 相關
    const MAP_FILE         = 'anime_map.json';
    const MAL_INDEX_FILE   = 'mal_index.json';
    const NAME_CACHE_FILE  = 'name_cache.json';
    const META_FILE        = 'anime_map_meta.json';
    const AL_INDEX_FILE    = 'al_index.json';
    const MAP_SOURCE_URL   = 'https://raw.githubusercontent.com/manami-project/anime-offline-database/master/anime-offline-database-minified.json';

    // BangumiExtLinker 相關（ABZ）
    const BGM_EXT_SOURCE_URL       = 'https://raw.githubusercontent.com/Rhilip/BangumiExtLinker/master/data/anime_map.json';
    const BGM_EXT_MAL_INDEX_FILE   = 'bgm_ext_mal_index.json';   // mal_id  → { bgm_id, date, name }
    const BGM_EXT_NAME_INDEX_FILE  = 'bgm_ext_name_index.json';  // name_lc → { bgm_id, date }
    const BGM_EXT_ANIDB_INDEX_FILE = 'bgm_ext_anidb_index.json'; // anidb_id → bgm_id
    const BGM_EXT_META_FILE        = 'bgm_ext_meta.json';

    // Bangumi API
    const BGM_SEARCH_URL   = 'https://api.bgm.tv/v0/search/subjects';
    const BGM_SUBJECT_URL  = 'https://api.bgm.tv/v0/subjects/';

    // Jikan（ABZ Layer 1.7）
    const JIKAN_EXTERNAL_URL = 'https://api.jikan.moe/v4/anime/';

    // ABY：false = 正式環境，true = 開發偵錯
    const DEBUG_LOG        = false;

    // -------------------------------------------------------------------------
    // 靜態對照表（Layer -1）
    // ABZ：已知無法自動匹配的特殊案例，anilist_id => bgm_id
    // -------------------------------------------------------------------------

    private const STATIC_BGM_MAP = [
        // 範例：153518 => 328609,
        // 在此新增已知特殊案例
    ];

    // -------------------------------------------------------------------------
    // 屬性
    // -------------------------------------------------------------------------

    private ?array  $anime_map           = null;
    private ?array  $mal_index           = null;
    private ?array  $al_index            = null;
    private ?array  $name_cache          = null;
    private ?array  $bgm_ext_mal_index   = null;  // ABZ
    private ?array  $bgm_ext_name_index  = null;  // ABZ
    private ?array  $bgm_ext_anidb_index = null;  // ABZ
    private ?string $last_error          = null;
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
     * 多層查詢邏輯，回傳 Bangumi subject ID 或 null。
     *
     * Layer 0   – WP post meta（100% 準確）
     * Layer -1  – 靜態 STATIC_BGM_MAP（100% 準確，已知特殊案例）
     * Layer 0.5 – al_index（AniList ID，anime-offline-database）
     * Layer 1   – mal_index（MAL ID，anime-offline-database）
     * Layer 1.5 – BangumiExtLinker mal_id 精確查表 + 年份驗證
     * Layer 1.6 – BangumiExtLinker 日文名精確查表 + 年份驗證
     * Layer 1.7 – Jikan API external links（MAL → bgm.tv URL）
     * Layer 1.8 – AniDB 橋接（AniList externalLinks → anidb_id → bgm_id）
     * Layer 2   – AniList externalLinks 直接含 bgm.tv / bangumi.tv URL
     * Layer 3   – Bangumi API 搜尋 + 日文原名 + 季度 + 集數驗證
     * Layer 4   – Bangumi API 搜尋 + 中文 / Romaji + 季度 + 集數驗證
     * Layer 5   – 設定 _bangumi_id_pending flag，回傳 null
     */
    public function get_bangumi_id( array $anime_data ): ?int {

        $anilist_id    = (int)    ( $anime_data['anilist_id']    ?? 0  );
        $mal_id        = isset( $anime_data['mal_id'] ) && $anime_data['mal_id'] !== null
                         ? (int) $anime_data['mal_id'] : null;
        $post_id       = (int)    ( $anime_data['post_id']       ?? 0  );
        $title_native  = (string) ( $anime_data['title_native']  ?? '' );
        $title_romaji  = (string) ( $anime_data['title_romaji']  ?? '' );
        $title_chinese = (string) ( $anime_data['title_chinese'] ?? '' );
        $season_year   = (int)    ( $anime_data['season_year']   ?? 0  );
        $season        = strtoupper( (string) ( $anime_data['season'] ?? '' ) ); // ABZ
        $episodes      = (int)    ( $anime_data['episodes']      ?? 0  );        // ABZ
        // ABZ：型別防護，確保是陣列
        $ext_links     = (array)  ( $anime_data['external_links'] ?? [] );

        // ── Layer 0：WP post meta ─────────────────────────────────────────────
        if ( $post_id > 0 ) {
            $meta_bgm = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );
            if ( $meta_bgm > 0 ) return $meta_bgm;
        }

        // ── Layer -1：靜態對照表（ABZ）────────────────────────────────────────
        if ( $anilist_id > 0 && isset( self::STATIC_BGM_MAP[ $anilist_id ] ) ) {
            $bgm_id = (int) self::STATIC_BGM_MAP[ $anilist_id ];
            if ( $bgm_id > 0 ) {
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 0.5：al_index（AniList ID → Bangumi ID）────────────────────
        if ( $anilist_id > 0 ) {
            $this->load_al_index();
            $bgm_id = $this->al_index[ $anilist_id ] ?? null;
            if ( $bgm_id ) {
                $bgm_id = (int) $bgm_id;
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 1：mal_index（MAL ID → Bangumi ID）─────────────────────────
        if ( $mal_id && $mal_id > 0 ) {
            $this->load_mal_index();
            $bgm_id = $this->mal_index[ $mal_id ] ?? null;
            if ( $bgm_id ) {
                $validated = $this->validate_bangumi_id( (int) $bgm_id, $title_native, $season_year );
                if ( $validated ) {
                    $this->write_bgm_id( $post_id, $validated );
                    return $validated;
                }
            }
        }

        // ── Layer 1.5：BangumiExtLinker mal_id 查表（ABZ）────────────────────
        if ( $mal_id && $mal_id > 0 ) {
            $this->load_bgm_ext_mal_index();
            $entry = $this->bgm_ext_mal_index[ $mal_id ] ?? null;
            if ( $entry ) {
                $bgm_id   = (int) ( $entry['bgm_id'] ?? 0 );
                $ext_year = (int) substr( $entry['date'] ?? '', 0, 4 );
                // 年份驗證 ±1（BangumiExtLinker 資料精準，不需 ±2）
                if ( $bgm_id > 0 && ( $season_year === 0 || $ext_year === 0 || abs( $ext_year - $season_year ) <= 1 ) ) {
                    $this->write_bgm_id( $post_id, $bgm_id );
                    return $bgm_id;
                }
            }
        }

        // ── Layer 1.6：BangumiExtLinker 日文名查表（ABZ）─────────────────────
        if ( $title_native !== '' ) {
            $this->load_bgm_ext_name_index();
            $key   = $this->normalize_name_light( $title_native );
            $entry = $this->bgm_ext_name_index[ $key ] ?? null;
            if ( $entry ) {
                $bgm_id   = (int) ( $entry['bgm_id'] ?? 0 );
                $ext_year = (int) substr( $entry['date'] ?? '', 0, 4 );
                if ( $bgm_id > 0 && ( $season_year === 0 || $ext_year === 0 || abs( $ext_year - $season_year ) <= 1 ) ) {
                    $this->write_bgm_id( $post_id, $bgm_id );
                    return $bgm_id;
                }
            }
        }

        // ── Layer 1.7：Jikan external links（ABZ）────────────────────────────
        if ( $mal_id && $mal_id > 0 ) {
            $bgm_id = $this->fetch_jikan_bgm_url( $mal_id );
            if ( $bgm_id > 0 ) {
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 1.8：AniDB 橋接（ABZ）──────────────────────────────────────
        // 從 AniList externalLinks 中解析 AniDB URL → anidb_id → bgm_id
        foreach ( $ext_links as $link ) {
            if ( ! is_array( $link ) ) continue;
            $url = $link['url'] ?? '';
            if ( preg_match( '#anidb\.net/(?:anime|a)/(\d+)#', $url, $m ) ) {
                $anidb_id = (int) $m[1];
                if ( $anidb_id > 0 ) {
                    $this->load_bgm_ext_anidb_index();
                    $bgm_id = $this->bgm_ext_anidb_index[ $anidb_id ] ?? null;
                    if ( $bgm_id ) {
                        $bgm_id = (int) $bgm_id;
                        $this->write_bgm_id( $post_id, $bgm_id );
                        return $bgm_id;
                    }
                }
                break; // AniDB 連結最多一個，找到就不再繼續
            }
        }

        // ── Layer 2：AniList externalLinks → bgm.tv / bangumi.tv ─────────────
        foreach ( $ext_links as $link ) {
            if ( ! is_array( $link ) ) continue;
            $url = $link['url'] ?? '';
            if ( preg_match( '#(?:bgm|bangumi)\.tv/subject/(\d+)#', $url, $m ) ) {
                $bgm_id = (int) $m[1];
                if ( $bgm_id > 0 ) {
                    $this->write_bgm_id( $post_id, $bgm_id );
                    return $bgm_id;
                }
            }
        }

        // ── Layer 3：Bangumi 搜尋 + 日文原名 + 季度 + 集數（ABZ）────────────
        if ( $title_native !== '' ) {
            $bgm_id = $this->search_bangumi_by_title( $title_native, $season_year, $season, $episodes );
            if ( $bgm_id ) {
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 4a：Bangumi 搜尋 + 中文標題 + 季度 + 集數（ABZ）───────────
        if ( $title_chinese !== '' ) {
            $bgm_id = $this->search_bangumi_by_title( $title_chinese, $season_year, $season, $episodes );
            if ( $bgm_id ) {
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 4b：Bangumi 搜尋 + Romaji 標題 + 季度 + 集數（ABZ）────────
        if ( $title_romaji !== '' && $title_romaji !== $title_native ) {
            $bgm_id = $this->search_bangumi_by_title( $title_romaji, $season_year, $season, $episodes );
            if ( $bgm_id ) {
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 5：pending flag ─────────────────────────────────────────────
        if ( $post_id > 0 ) {
            update_post_meta( $post_id, '_bangumi_id_pending', 1 );
        }
        $this->last_error = "Bangumi ID not found for AniList ID {$anilist_id}";
        return null;
    }

    // =========================================================================
    // PRIVATE – 統一寫入 bgm_id 並清除 pending（ACA）
    // =========================================================================

    /**
     * 成功找到 bgm_id 後統一呼叫此方法：
     * 1. 寫入 anime_bangumi_id post meta
     * 2. 清除 _bangumi_id_pending flag
     */
    private function write_bgm_id( int $post_id, int $bgm_id ): void {
        if ( $post_id <= 0 || $bgm_id <= 0 ) return;
        update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
        delete_post_meta( $post_id, '_bangumi_id_pending' );
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
        $path     = $this->get_file_path( self::MAP_FILE );
        $meta     = $this->load_json_file( $this->get_file_path( self::META_FILE ) );
        $ext_meta = $this->load_json_file( $this->get_file_path( self::BGM_EXT_META_FILE ) );

        if ( ! file_exists( $path ) ) {
            return [
                'exists'           => false,
                'path'             => $path,
                'size'             => 0,
                'entry_count'      => 0,
                'mal_count'        => 0,
                'al_count'         => 0,
                'ext_total'        => 0,
                'ext_mal_count'    => 0,
                'ext_anidb_count'  => 0,
                'last_updated'     => '',
                'ext_last_updated' => '',
                'age_hours'        => null,
                'version'          => '',
            ];
        }

        $size      = filesize( $path );
        $modified  = filemtime( $path );
        $age_hours = $modified ? round( ( time() - $modified ) / 3600, 1 ) : null;

        return [
            'exists'           => true,
            'path'             => $path,
            'size'             => $size,
            'entry_count'      => $meta['entry_count']         ?? 0,
            'mal_count'        => $meta['mal_count']           ?? 0,
            'al_count'         => $meta['al_count']            ?? 0,
            'ext_total'        => $ext_meta['total']           ?? 0,
            'ext_mal_count'    => $ext_meta['mal_count']       ?? 0,
            'ext_anidb_count'  => $ext_meta['anidb_count']     ?? 0,
            'last_updated'     => $meta['generated_at']        ?? gmdate( 'Y-m-d H:i:s', $modified ),
            'ext_last_updated' => $ext_meta['generated_at']    ?? '',
            'age_hours'        => $age_hours,
            'version'          => $meta['version']             ?? '',
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

        // ── 第一段：anime-offline-database ───────────────────────────────────
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
            $this->last_error = "HTTP {$code} fetching anime-offline-database.";
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
            $this->last_error = 'Invalid anime-offline-database JSON structure.';
            return false;
        }

        $al_index   = [];
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
                if ( preg_match( '#(?:bgm|bangumi)\.tv/subject/(\d+)#', $src, $m ) )
                    $bgm_id = (int) $m[1];
            }

            // ABZ：修正冗餘，統一只寫 $al_index（$map 與 $al_index 用途相同）
            if ( $al_id  && $bgm_id ) $al_index[ $al_id ]  = $bgm_id;
            if ( $mal_id && $bgm_id ) $mal_index[ $mal_id ] = $bgm_id;

            if ( $bgm_id ) {
                $titles = $entry['titles'] ?? [];
                foreach ( $titles as $t ) {
                    $lang = $t['lang'] ?? '';
                    if ( $lang === 'zh-Hans' || $lang === 'zh-Hant' ) {
                        $name_cache[ $bgm_id ] = $t['title'] ?? '';
                        break;
                    }
                }
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

        $this->write_json( self::MAP_FILE,        $al_index );  // 保留相容舊邏輯
        $this->write_json( self::MAL_INDEX_FILE,  $mal_index );
        $this->write_json( self::AL_INDEX_FILE,   $al_index );
        $this->write_json( self::NAME_CACHE_FILE, $name_cache );
        $this->write_json( self::META_FILE, [
            'source_url'   => self::MAP_SOURCE_URL,
            'version'      => $data['license']['url'] ?? '1.0',
            'entry_count'  => count( $al_index ),
            'mal_count'    => count( $mal_index ),
            'al_count'     => count( $al_index ),
            'generated_at' => gmdate( 'Y-m-d H:i:s' ),
            'etag'         => wp_remote_retrieve_header( $response, 'etag' ),
        ] );

        $this->al_index   = $al_index;
        $this->mal_index  = $mal_index;
        $this->name_cache = $name_cache;

        // ── 第二段：BangumiExtLinker（ABZ）───────────────────────────────────
        $ext_response = wp_remote_get( self::BGM_EXT_SOURCE_URL, [
            'timeout'    => 120,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $ext_response ) ) {
            $this->last_error = 'BangumiExtLinker download failed: ' . $ext_response->get_error_message();
            return true; // 第一段成功，不影響主流程
        }

        $ext_code = (int) wp_remote_retrieve_response_code( $ext_response );
        if ( $ext_code !== 200 ) {
            $this->last_error = "BangumiExtLinker HTTP {$ext_code}.";
            return true;
        }

        $ext_data = json_decode( wp_remote_retrieve_body( $ext_response ), true );
        if ( ! is_array( $ext_data ) ) {
            $this->last_error = 'Invalid BangumiExtLinker JSON structure.';
            return true;
        }

        $bgm_ext_mal_index   = [];
        $bgm_ext_name_index  = [];
        $bgm_ext_anidb_index = [];

        foreach ( $ext_data as $entry ) {
            $bgm_id   = (int) ( $entry['bgm_id']   ?? 0 );
            $mal_id   = isset( $entry['mal_id']   ) && $entry['mal_id']   !== null ? (int) $entry['mal_id']   : null;
            $anidb_id = isset( $entry['anidb_id'] ) && $entry['anidb_id'] !== null ? (int) $entry['anidb_id'] : null;
            $name     = (string) ( $entry['name'] ?? '' );
            $date     = (string) ( $entry['date'] ?? '' );

            if ( ! $bgm_id ) continue;

            if ( $mal_id ) {
                $bgm_ext_mal_index[ $mal_id ] = [
                    'bgm_id' => $bgm_id,
                    'date'   => $date,
                    'name'   => $name,
                ];
            }

            if ( $anidb_id ) {
                $bgm_ext_anidb_index[ $anidb_id ] = $bgm_id;
            }

            if ( $name !== '' ) {
                $key = $this->normalize_name_light( $name );
                if ( $key !== '' ) {
                    // 若有多筆相同名字，保留較新的（date 較大者）
                    $existing_date = $bgm_ext_name_index[ $key ]['date'] ?? '';
                    if ( ! isset( $bgm_ext_name_index[ $key ] ) || $date > $existing_date ) {
                        $bgm_ext_name_index[ $key ] = [
                            'bgm_id' => $bgm_id,
                            'date'   => $date,
                        ];
                    }
                }
            }
        }

        $this->write_json( self::BGM_EXT_MAL_INDEX_FILE,   $bgm_ext_mal_index );
        $this->write_json( self::BGM_EXT_NAME_INDEX_FILE,  $bgm_ext_name_index );
        $this->write_json( self::BGM_EXT_ANIDB_INDEX_FILE, $bgm_ext_anidb_index );
        $this->write_json( self::BGM_EXT_META_FILE, [
            'source_url'   => self::BGM_EXT_SOURCE_URL,
            'total'        => count( $ext_data ),
            'mal_count'    => count( $bgm_ext_mal_index ),
            'anidb_count'  => count( $bgm_ext_anidb_index ),
            'name_count'   => count( $bgm_ext_name_index ),
            'generated_at' => gmdate( 'Y-m-d H:i:s' ),
        ] );

        $this->bgm_ext_mal_index   = $bgm_ext_mal_index;
        $this->bgm_ext_name_index  = $bgm_ext_name_index;
        $this->bgm_ext_anidb_index = $bgm_ext_anidb_index;

        return true;
    }

    public function rebuild_indexes(): bool {
        $this->load_anime_map();
        if ( empty( $this->anime_map ) ) {
            $this->last_error = 'anime_map is empty, cannot rebuild indexes.';
            return false;
        }

        // 重置所有快取，強制從磁碟重新載入
        $this->mal_index           = null;
        $this->al_index            = null;
        $this->name_cache          = null;
        $this->bgm_ext_mal_index   = null;
        $this->bgm_ext_name_index  = null;
        $this->bgm_ext_anidb_index = null;

        $this->load_mal_index();
        $this->load_al_index();
        $this->load_name_cache();
        $this->load_bgm_ext_mal_index();
        $this->load_bgm_ext_name_index();
        $this->load_bgm_ext_anidb_index();

        return true;
    }

    // =========================================================================
    // PRIVATE – Bangumi 搜尋
    // =========================================================================

    /**
     * ABZ：新增 $season 和 $episodes 參數，傳遞給 match_best_result()。
     */
    private function search_bangumi_by_title( string $title, int $year, string $season = '', int $episodes = 0 ): ?int {

        $normalized = $this->normalize_title( $title );

        if ( $normalized !== '' ) {
            $results = $this->query_bangumi_search( $normalized );
            $best    = $this->match_best_result( $results, $normalized, $year, $season, $episodes );
            if ( $best ) return $best;
        }

        // ABC：第二次搜尋用原始標題（包含季號），處理 normalize 後關鍵詞被去除的情況
        if ( $title !== $normalized && $title !== '' ) {
            $results2 = $this->query_bangumi_search( $title );
            $best2    = $this->match_best_result( $results2, $title, $year, $season, $episodes );
            if ( $best2 ) return $best2;
        }

        return null;
    }

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
     * ABZ：加入季度篩選與集數驗證。
     *
     * 選取優先順序（由高到低）：
     *   P1 – 年份差 ≤1 + 同季（或無季度資訊）+ 集數吻合（或無集數資訊）
     *   P2 – 年份差 ≤1 + 季度差 1（相鄰季）+ 集數吻合
     *   P3 – 年份差 ≤1（不管季度），集數吻合
     *   P4 – 年份差 ≤1，集數不吻合但差距 ≤ 6
     *   P5 – 年份差 2–3
     *   P6 – 無年份資訊，相似度 ≥ 80%
     *   fallback – 全部候選中相似度 ≥ 65%
     */
    private function match_best_result( array $results, string $title, int $year, string $season = '', int $episodes = 0 ): ?int {

        if ( empty( $results ) ) return null;

        $normalized_input = $this->normalize_title( $title );
        $input_quarter    = $this->season_to_quarter( $season ); // ABZ

        $p1 = $p2 = $p3 = $p4 = [];
        $candidates_secondary = [];
        $candidates_no_year   = [];
        $all_candidates       = [];

        foreach ( $results as $subject ) {
            $bgm_id = (int) ( $subject['id'] ?? 0 );
            if ( ! $bgm_id ) continue;

            $bgm_title_cn = (string) ( $subject['name_cn'] ?? '' );
            $bgm_title_ja = (string) ( $subject['name']    ?? '' );

            $sim_cn = $sim_ja = $sim_cn_rev = $sim_ja_rev = 0.0;
            similar_text( $normalized_input, $this->normalize_title( $bgm_title_cn ), $sim_cn );
            similar_text( $normalized_input, $this->normalize_title( $bgm_title_ja ), $sim_ja );
            if ( $bgm_title_cn !== '' )
                similar_text( $this->normalize_title( $bgm_title_cn ), $normalized_input, $sim_cn_rev );
            if ( $bgm_title_ja !== '' )
                similar_text( $this->normalize_title( $bgm_title_ja ), $normalized_input, $sim_ja_rev );

            $sim = max( $sim_cn, $sim_ja, $sim_cn_rev, $sim_ja_rev );

            if ( self::DEBUG_LOG ) {
                error_log( sprintf(
                    '[BGM MATCH] INPUT: %s | BGM: %s / %s | SIM: %.1f%%',
                    $normalized_input, $bgm_title_ja, $bgm_title_cn, $sim
                ) );
            }

            if ( $sim < 45.0 ) continue; // ABS：門檻 45%

            $bgm_year  = $this->get_bangumi_year( $subject );
            $year_diff = ( $year > 0 && $bgm_year > 0 ) ? abs( $bgm_year - $year ) : 999;

            // ABZ：季度比對
            $bgm_quarter  = $this->get_bangumi_quarter( $subject );
            $quarter_diff = ( $input_quarter > 0 && $bgm_quarter > 0 )
                            ? abs( $input_quarter - $bgm_quarter )
                            : 999;  // 999 = 無法比對

            // ABZ：集數比對（差距 ≤3 或無資訊視為吻合）
            $bgm_eps  = (int) ( $subject['eps'] ?? 0 );
            $eps_diff = ( $episodes > 0 && $bgm_eps > 0 ) ? abs( $bgm_eps - $episodes ) : 999;
            $eps_ok   = ( $eps_diff === 999 || $eps_diff <= 3 );

            $candidate = [
                'id'           => $bgm_id,
                'sim'          => $sim,
                'year_diff'    => $year_diff,
                'quarter_diff' => $quarter_diff,
                'eps_diff'     => $eps_diff,
            ];

            $all_candidates[] = $candidate;

            if ( $year_diff === 999 ) {
                $candidates_no_year[] = $candidate;
            } elseif ( $year_diff <= 1 ) {
                if ( ( $quarter_diff === 999 || $quarter_diff === 0 ) && $eps_ok ) {
                    $p1[] = $candidate; // P1：同年 + 同季 + 集數吻合
                } elseif ( $quarter_diff === 1 && $eps_ok ) {
                    $p2[] = $candidate; // P2：同年 + 相鄰季 + 集數吻合
                } elseif ( $eps_ok ) {
                    $p3[] = $candidate; // P3：同年 + 集數吻合（季不限）
                } elseif ( $eps_diff <= 6 ) {
                    $p4[] = $candidate; // P4：同年 + 集數差距 ≤6
                } else {
                    $candidates_secondary[] = $candidate; // 集數差太大，降級
                }
            } else {
                $candidates_secondary[] = $candidate;
            }
        }

        // ── 選取策略（P1 → P2 → P3 → P4 → P5 → P6 → fallback）─────────────

        foreach ( [ $p1, $p2, $p3, $p4 ] as $pool ) {
            if ( ! empty( $pool ) ) {
                usort( $pool, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
                if ( self::DEBUG_LOG ) {
                    error_log( '[BGM MATCH] Selected ID=' . $pool[0]['id'] . ' sim=' . $pool[0]['sim'] );
                }
                return $pool[0]['id'];
            }
        }

        // P5：年份差 2–3
        if ( ! empty( $candidates_secondary ) ) {
            usort( $candidates_secondary, function ( $a, $b ) {
                if ( $a['year_diff'] !== $b['year_diff'] )
                    return $a['year_diff'] <=> $b['year_diff'];
                return $b['sim'] <=> $a['sim'];
            } );
            if ( $candidates_secondary[0]['year_diff'] <= 3 ) {
                return $candidates_secondary[0]['id'];
            }
        }

        // P6：無年份資訊，相似度 ≥ 80%
        if ( ! empty( $candidates_no_year ) ) {
            usort( $candidates_no_year, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
            if ( $candidates_no_year[0]['sim'] >= 80.0 )
                return $candidates_no_year[0]['id'];
        }

        // fallback：相似度 ≥ 65%（ABS fallback 保留）
        if ( ! empty( $all_candidates ) ) {
            usort( $all_candidates, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
            if ( $all_candidates[0]['sim'] >= 65.0 ) {
                if ( self::DEBUG_LOG ) {
                    error_log( '[BGM MATCH] fallback ID=' . $all_candidates[0]['id'] .
                               ' sim=' . $all_candidates[0]['sim'] );
                }
                return $all_candidates[0]['id'];
            }
        }

        return null;
    }

    // =========================================================================
    // PRIVATE – Layer 1.7：Jikan external links
    // =========================================================================

    /**
     * ABZ：打 Jikan /anime/{mal_id}/external，解析 bgm.tv / bangumi.tv URL。
     * 結果快取 7 天（同一部作品不重複打）。
     */
    private function fetch_jikan_bgm_url( int $mal_id ): int {
        $cache_key = 'anime_sync_jikan_ext_' . $mal_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (int) $cached;

        $response = wp_remote_get( self::JIKAN_EXTERNAL_URL . $mal_id . '/external', [
            'timeout'    => 10,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return 0;
        }

        $data  = json_decode( wp_remote_retrieve_body( $response ), true );
        $links = $data['data'] ?? [];

        foreach ( $links as $link ) {
            $url = $link['url'] ?? '';
            if ( preg_match( '#(?:bgm|bangumi)\.tv/subject/(\d+)#', $url, $m ) ) {
                $bgm_id = (int) $m[1];
                if ( $bgm_id > 0 ) {
                    set_transient( $cache_key, $bgm_id, 7 * DAY_IN_SECONDS );
                    return $bgm_id;
                }
            }
        }

        set_transient( $cache_key, 0, 7 * DAY_IN_SECONDS );
        return 0;
    }

    // =========================================================================
    // PRIVATE – 標題正規化
    // =========================================================================

    private function normalize_title( string $title ): string {
        if ( $title === '' ) return ''; // ABR：null 防護

        $title = mb_strtolower( $title );

        // 全形羅馬數字轉阿拉伯數字
        $roman_map = [
            'ⅻ' => '12', 'ⅺ' => '11', 'ⅹ' => '10',
            'ⅸ' => '9',  'ⅷ' => '8',  'ⅶ' => '7',
            'ⅵ' => '6',  'ⅴ' => '5',  'ⅳ' => '4',
            'ⅲ' => '3',  'ⅱ' => '2',  'ⅰ' => '1',
        ];
        $title = str_replace( array_keys( $roman_map ), array_values( $roman_map ), $title );

        // 去除括號內容、Season/Part 標記、日文期/季/クール、年份、非文字字元
        $title = preg_replace( '/[\(\（][^\)\）]*[\)\）]/', '', $title );
        $title = preg_replace( '/[\[【][^\]】]*[\]】]/',   '', $title );
        $title = preg_replace( '/\b(?:season|part|cour|chapter|arc)\s*\d+\b/i', '', $title );
        $title = preg_replace( '/\b\d+(?:st|nd|rd|th)\s+season\b/i', '', $title );
        $title = preg_replace( '/第\s*\d+\s*[期季クール]/u', '', $title );
        $title = preg_replace( '/\s+\d+$/', '', $title );
        $title = preg_replace( '/\b(19|20)\d{2}\b/', '', $title );
        $title = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $title );
        $title = preg_replace( '/\s+/', ' ', $title );

        return trim( $title );
    }

    /**
     * ABZ：輕量正規化，僅用於 Layer 1.6 日文名查表。
     * 只做 lowercase + 統一半形空格 + trim，保留所有有意義字元。
     */
    private function normalize_name_light( string $name ): string {
        if ( $name === '' ) return '';
        $name = str_replace( '　', ' ', $name ); // 全形空格 → 半形
        $name = mb_strtolower( $name );
        return trim( $name );
    }

    // =========================================================================
    // PRIVATE – 季度工具（ABZ + ACA 補全）
    // =========================================================================

    /**
     * AniList season 字串 → 季度數字
     * 1 = 冬（WINTER, 1–3月）
     * 2 = 春（SPRING, 4–6月）
     * 3 = 夏（SUMMER, 7–9月）
     * 4 = 秋（FALL, 10–12月）
     * 0 = 未知
     */
    private function season_to_quarter( string $season ): int {
        return match( strtoupper( $season ) ) {
            'WINTER' => 1,
            'SPRING' => 2,
            'SUMMER' => 3,
            'FALL'   => 4,
            default  => 0,
        };
    }

    /**
     * ACA：從 Bangumi subject 的 date 欄位推算季度（1–4）。
     * Bangumi date 格式：YYYY-MM-DD 或 YYYY-MM。
     */
    private function get_bangumi_quarter( array $subject ): int {
        $date = $subject['date'] ?? '';
        if ( $date === '' ) return 0;

        // 取月份（date 格式 YYYY-MM-DD 或 YYYY-MM）
        $parts = explode( '-', $date );
        $month = isset( $parts[1] ) ? (int) $parts[1] : 0;
        if ( $month <= 0 ) return 0;

        if ( $month >= 1  && $month <= 3  ) return 1; // 冬
        if ( $month >= 4  && $month <= 6  ) return 2; // 春
        if ( $month >= 7  && $month <= 9  ) return 3; // 夏
        if ( $month >= 10 && $month <= 12 ) return 4; // 秋

        return 0;
    }

    // =========================================================================
    // PRIVATE – Bangumi 年份提取
    // =========================================================================

    private function get_bangumi_year( array $subject ): int {
        // 優先使用 date 欄位（精確）
        $date = $subject['date'] ?? '';
        if ( $date !== '' ) {
            $year = (int) substr( $date, 0, 4 );
            if ( $year > 1900 ) return $year;
        }

        // 退而使用 air_date（舊格式相容）
        $air_date = $subject['air_date'] ?? '';
        if ( $air_date !== '' ) {
            $year = (int) substr( $air_date, 0, 4 );
            if ( $year > 1900 ) return $year;
        }

        return 0;
    }

    // =========================================================================
    // PRIVATE – Bangumi ID 驗證
    // =========================================================================

    /**
     * ABU：年份差放寬至 2，相似度門檻降至 30%。
     * 用於 Layer 1 mal_index 命中後的二次確認。
     */
    private function validate_bangumi_id( int $bgm_id, string $title, int $year ): ?int {
        $cache_key = 'anime_sync_bgm_validate_' . $bgm_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached > 0 ? (int) $cached : null;

        $response = wp_remote_get( self::BGM_SUBJECT_URL . $bgm_id, [
            'timeout'    => 10,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        $subject = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $subject ) ) {
            set_transient( $cache_key, 0, HOUR_IN_SECONDS );
            return null;
        }

        // 年份驗證 ±2（ABU）
        $bgm_year = $this->get_bangumi_year( $subject );
        if ( $year > 0 && $bgm_year > 0 && abs( $bgm_year - $year ) > 2 ) {
            set_transient( $cache_key, 0, DAY_IN_SECONDS );
            return null;
        }

        // 標題相似度 ≥ 30%（ABU）
        $bgm_title = $subject['name'] ?? '';
        $sim       = 0.0;
        similar_text(
            $this->normalize_title( $title ),
            $this->normalize_title( $bgm_title ),
            $sim
        );

        if ( $sim < 30.0 ) {
            set_transient( $cache_key, 0, DAY_IN_SECONDS );
            return null;
        }

        set_transient( $cache_key, $bgm_id, 7 * DAY_IN_SECONDS );
        return $bgm_id;
    }

    // =========================================================================
    // PRIVATE – 惰性載入器
    // =========================================================================

    private function load_anime_map(): void {
        if ( $this->anime_map === null ) {
            $this->anime_map = $this->load_json_file( $this->get_file_path( self::MAP_FILE ) ) ?? [];
        }
    }

    private function load_mal_index(): void {
        if ( $this->mal_index === null ) {
            $this->mal_index = $this->load_json_file( $this->get_file_path( self::MAL_INDEX_FILE ) ) ?? [];
        }
    }

    private function load_al_index(): void {
        if ( $this->al_index === null ) {
            $this->al_index = $this->load_json_file( $this->get_file_path( self::AL_INDEX_FILE ) ) ?? [];
        }
    }

    private function load_name_cache(): void {
        if ( $this->name_cache === null ) {
            $this->name_cache = $this->load_json_file( $this->get_file_path( self::NAME_CACHE_FILE ) ) ?? [];
        }
    }

    private function load_bgm_ext_mal_index(): void {
        if ( $this->bgm_ext_mal_index === null ) {
            $this->bgm_ext_mal_index = $this->load_json_file( $this->get_file_path( self::BGM_EXT_MAL_INDEX_FILE ) ) ?? [];
        }
    }

    private function load_bgm_ext_name_index(): void {
        if ( $this->bgm_ext_name_index === null ) {
            $this->bgm_ext_name_index = $this->load_json_file( $this->get_file_path( self::BGM_EXT_NAME_INDEX_FILE ) ) ?? [];
        }
    }

    private function load_bgm_ext_anidb_index(): void {
        if ( $this->bgm_ext_anidb_index === null ) {
            $this->bgm_ext_anidb_index = $this->load_json_file( $this->get_file_path( self::BGM_EXT_ANIDB_INDEX_FILE ) ) ?? [];
        }
    }

    // =========================================================================
    // PRIVATE – 檔案 I/O
    // =========================================================================

    private function load_json_file( string $path ): ?array {
        if ( ! file_exists( $path ) ) return null;
        $json = file_get_contents( $path );
        if ( $json === false ) return null;
        $data = json_decode( $json, true );
        return is_array( $data ) ? $data : null;
    }

    private function write_json( string $filename, array $data ): bool {
        $path    = $this->get_file_path( $filename );
        $encoded = wp_json_encode( $data );
        if ( $encoded === false ) return false;
        return file_put_contents( $path, $encoded ) !== false;
    }

    private function get_file_path( string $filename ): string {
        return trailingslashit( $this->upload_dir ) . $filename;
    }
}

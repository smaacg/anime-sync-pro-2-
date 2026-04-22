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
 *   ACB – MAP_SOURCE_URL 更新為 GitHub Releases 路徑
 *         download_and_cache_map() 第一段改用 cURL 串流寫檔
 *   ACC – anime-offline-database 已不含 Bangumi 對應（0筆）
 *         entry_count / mal_count 改從 BangumiExtLinker 計算
 *         META_FILE 寫入移至第二段完成後，後台顯示正確筆數
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
    const AL_INDEX_FILE    = 'al_index.json';

    // ✅ ACB：更新為 GitHub Releases 路徑
    const MAP_SOURCE_URL   = 'https://github.com/manami-project/anime-offline-database/releases/latest/download/anime-offline-database-minified.json';

    const BGM_EXT_SOURCE_URL       = 'https://raw.githubusercontent.com/Rhilip/BangumiExtLinker/master/data/anime_map.json';
    const BGM_EXT_MAL_INDEX_FILE   = 'bgm_ext_mal_index.json';
    const BGM_EXT_NAME_INDEX_FILE  = 'bgm_ext_name_index.json';
    const BGM_EXT_ANIDB_INDEX_FILE = 'bgm_ext_anidb_index.json';
    const BGM_EXT_META_FILE        = 'bgm_ext_meta.json';

    const BGM_SEARCH_URL   = 'https://api.bgm.tv/v0/search/subjects';
    const BGM_SUBJECT_URL  = 'https://api.bgm.tv/v0/subjects/';

    const JIKAN_EXTERNAL_URL = 'https://api.jikan.moe/v4/anime/';

    const DEBUG_LOG        = false;

    private const STATIC_BGM_MAP = [
        // 範例：153518 => 328609,
    ];

    // -------------------------------------------------------------------------
    // 屬性
    // -------------------------------------------------------------------------

    private ?array  $anime_map           = null;
    private ?array  $mal_index           = null;
    private ?array  $al_index            = null;
    private ?array  $name_cache          = null;
    private ?array  $bgm_ext_mal_index   = null;
    private ?array  $bgm_ext_name_index  = null;
    private ?array  $bgm_ext_anidb_index = null;
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

    public function get_bangumi_id( array $anime_data ): ?int {

        $anilist_id    = (int)    ( $anime_data['anilist_id']    ?? 0  );
        $mal_id        = isset( $anime_data['mal_id'] ) && $anime_data['mal_id'] !== null
                         ? (int) $anime_data['mal_id'] : null;
        $post_id       = (int)    ( $anime_data['post_id']       ?? 0  );
        $title_native  = (string) ( $anime_data['title_native']  ?? '' );
        $title_romaji  = (string) ( $anime_data['title_romaji']  ?? '' );
        $title_chinese = (string) ( $anime_data['title_chinese'] ?? '' );
        $season_year   = (int)    ( $anime_data['season_year']   ?? 0  );
        $season        = strtoupper( (string) ( $anime_data['season'] ?? '' ) );
        $episodes      = (int)    ( $anime_data['episodes']      ?? 0  );
        $ext_links     = (array)  ( $anime_data['external_links'] ?? [] );

        // ── Layer 0：WP post meta ─────────────────────────────────────────────
        if ( $post_id > 0 ) {
            $meta_bgm = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );
            if ( $meta_bgm <= 0 ) {
                $meta_bgm = (int) get_post_meta( $post_id, 'bangumi_id', true );
            }
            if ( $meta_bgm > 0 ) return $meta_bgm;
        }

        // ── Layer -1：靜態對照表 ──────────────────────────────────────────────
        if ( $anilist_id > 0 && isset( self::STATIC_BGM_MAP[ $anilist_id ] ) ) {
            $bgm_id = (int) self::STATIC_BGM_MAP[ $anilist_id ];
            if ( $bgm_id > 0 ) {
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 0.5：al_index ───────────────────────────────────────────────
        if ( $anilist_id > 0 ) {
            $this->load_al_index();
            $bgm_id = $this->al_index[ $anilist_id ] ?? null;
            if ( $bgm_id ) {
                $bgm_id = (int) $bgm_id;
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 1：mal_index ────────────────────────────────────────────────
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

        // ── Layer 1.5：BangumiExtLinker mal_id ───────────────────────────────
        if ( $mal_id && $mal_id > 0 ) {
            $this->load_bgm_ext_mal_index();
            $entry = $this->bgm_ext_mal_index[ $mal_id ] ?? null;
            if ( $entry ) {
                $bgm_id   = (int) ( $entry['bgm_id'] ?? 0 );
                $ext_year = (int) substr( $entry['date'] ?? '', 0, 4 );
                if ( $bgm_id > 0 && ( $season_year === 0 || $ext_year === 0 || abs( $ext_year - $season_year ) <= 1 ) ) {
                    $this->write_bgm_id( $post_id, $bgm_id );
                    return $bgm_id;
                }
            }
        }

        // ── Layer 1.6：BangumiExtLinker 日文名 ───────────────────────────────
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

        // ── Layer 1.7：Jikan external links ──────────────────────────────────
        if ( $mal_id && $mal_id > 0 ) {
            $bgm_id = $this->fetch_jikan_bgm_url( $mal_id );
            if ( $bgm_id > 0 ) {
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 1.8：AniDB 橋接 ─────────────────────────────────────────────
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
                break;
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

        // ── Layer 3：Bangumi 搜尋 + 日文原名 ─────────────────────────────────
        if ( $title_native !== '' ) {
            $bgm_id = $this->search_bangumi_by_title( $title_native, $season_year, $season, $episodes );
            if ( $bgm_id ) {
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 4a：Bangumi 搜尋 + 中文標題 ────────────────────────────────
        if ( $title_chinese !== '' ) {
            $bgm_id = $this->search_bangumi_by_title( $title_chinese, $season_year, $season, $episodes );
            if ( $bgm_id ) {
                $this->write_bgm_id( $post_id, $bgm_id );
                return $bgm_id;
            }
        }

        // ── Layer 4b：Bangumi 搜尋 + Romaji ──────────────────────────────────
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
    // PRIVATE – 統一寫入 bgm_id
    // =========================================================================

    private function write_bgm_id( int $post_id, int $bgm_id ): void {
        if ( $post_id <= 0 || $bgm_id <= 0 ) return;
        update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
        update_post_meta( $post_id, 'bangumi_id', $bgm_id );
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

        // ── 第一段：anime-offline-database（cURL 串流）───────────────────────
        $tmp_file = $this->get_file_path( 'anime_map_tmp.json' );

        $fp = fopen( $tmp_file, 'wb' );
        if ( ! $fp ) {
            $this->last_error = '無法建立暫存檔：' . $tmp_file;
            return false;
        }

        $ch = curl_init( self::MAP_SOURCE_URL );
        curl_setopt_array( $ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_USERAGENT      => 'AnimeSync-Pro/1.0 (WordPress)',
            CURLOPT_SSL_VERIFYPEER => true,
        ] );
        $ok        = curl_exec( $ch );
        $http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_err  = curl_error( $ch );
        curl_close( $ch );
        fclose( $fp );

        if ( ! $ok || $http_code !== 200 ) {
            @unlink( $tmp_file );
            $this->last_error = "下載失敗：HTTP {$http_code}，{$curl_err}";
            return false;
        }

        Anime_Sync_Performance::increase_memory_limit( '512M' );
        $body = file_get_contents( $tmp_file );
        @unlink( $tmp_file );

        if ( ! $body ) {
            $this->last_error = '暫存檔讀取失敗';
            return false;
        }

        $data = json_decode( $body, true );
        unset( $body );

        if ( $data === null ) {
            $this->last_error = 'JSON decode 失敗：' . json_last_error_msg();
            return false;
        }

        if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
            $this->last_error = 'JSON 結構異常，頂層 keys：' . implode( ', ', array_keys( $data ) );
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

        unset( $data );

        $this->write_json( self::MAP_FILE,        $al_index );
        $this->write_json( self::MAL_INDEX_FILE,  $mal_index );
        $this->write_json( self::AL_INDEX_FILE,   $al_index );
        $this->write_json( self::NAME_CACHE_FILE, $name_cache );

        $this->al_index   = $al_index;
        $this->mal_index  = $mal_index;
        $this->name_cache = $name_cache;

        // ── 第二段：BangumiExtLinker ──────────────────────────────────────────
        $ext_response = wp_remote_get( self::BGM_EXT_SOURCE_URL, [
            'timeout'    => 120,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $ext_response ) ) {
            $this->last_error = 'BangumiExtLinker download failed: ' . $ext_response->get_error_message();
            // 第一段成功，寫入暫時 meta 後回傳 true
            $this->write_json( self::META_FILE, [
                'source_url'   => self::MAP_SOURCE_URL,
                'version'      => '1.0',
                'entry_count'  => 0,
                'mal_count'    => 0,
                'al_count'     => count( $al_index ),
                'generated_at' => gmdate( 'Y-m-d H:i:s' ),
            ] );
            return true;
        }

        $ext_code = (int) wp_remote_retrieve_response_code( $ext_response );
        if ( $ext_code !== 200 ) {
            $this->last_error = "BangumiExtLinker HTTP {$ext_code}.";
            $this->write_json( self::META_FILE, [
                'source_url'   => self::MAP_SOURCE_URL,
                'version'      => '1.0',
                'entry_count'  => 0,
                'mal_count'    => 0,
                'al_count'     => count( $al_index ),
                'generated_at' => gmdate( 'Y-m-d H:i:s' ),
            ] );
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

        // ✅ ACC：META_FILE 移至第二段完成後寫入，entry_count 反映 BangumiExtLinker 筆數
        $this->write_json( self::META_FILE, [
            'source_url'   => self::MAP_SOURCE_URL,
            'version'      => '1.0',
            'entry_count'  => count( $bgm_ext_mal_index ),
            'mal_count'    => count( $bgm_ext_mal_index ),
            'al_count'     => count( $al_index ),
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

    private function search_bangumi_by_title( string $title, int $year, string $season = '', int $episodes = 0 ): ?int {
        foreach ( $this->build_search_variants( $title ) as $variant ) {
            $results = $this->query_bangumi_search( $variant );
            $best    = $this->match_best_result( $results, $variant, $year, $season, $episodes );
            if ( $best ) return $best;
        }
        return null;
    }

    private function build_search_variants( string $title ): array {
        $title = trim( $title );
        if ( $title === '' ) return [];

        $variants = [ $title ];

        $normalized = $this->normalize_title( $title );
        if ( $normalized !== '' ) $variants[] = $normalized;

        $without_brackets = preg_replace( '/[\(\（\[【].*?[\)\）\]】]/u', ' ', $title );
        $without_brackets = trim( preg_replace( '/\s+/u', ' ', (string) $without_brackets ) );
        if ( $without_brackets !== '' ) {
            $variants[] = $without_brackets;
            $variants[] = $this->normalize_title( $without_brackets );
        }

        $base_title = preg_replace(
            [
                '/\b\d+(?:st|nd|rd|th)\s+season\b/ui',
                '/\bseason\s*\d+\b/ui',
                '/\bpart\s*\d+\b/ui',
                '/\bcour\s*\d+\b/ui',
                '/第\s*\d+\s*(?:期|季|部|クール)\b/u',
                '/\b(?:special\s*edition|special\s*edit(?:ion)?|digest|compilation)\b/ui',
                '/(?:特別編集版|特別編輯版|総集編|總集篇|总集篇|先行上映|先行上映版|劇場先行版)/u',
            ],
            ' ',
            $title
        );
        $base_title = trim( preg_replace( '/\s+/u', ' ', (string) $base_title ) );
        if ( $base_title !== '' && $base_title !== $title ) {
            $variants[] = $base_title;
            $variants[] = $this->normalize_title( $base_title );
        }

        $cleaned = [];
        foreach ( $variants as $v ) {
            $v = trim( preg_replace( '/\s+/u', ' ', (string) $v ) );
            if ( $v !== '' ) $cleaned[ $v ] = true;
        }

        return array_keys( $cleaned );
    }

    private function query_bangumi_search( string $keyword ): array {
        $url = add_query_arg( [ 'limit' => 25, 'offset' => 0 ], self::BGM_SEARCH_URL );

        $response = wp_remote_post( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'body'       => wp_json_encode( [ 'keyword' => $keyword, 'filter' => [ 'type' => [ 2 ] ] ] ),
        ] );

        if ( is_wp_error( $response ) ) return [];
        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['data'] ?? [];
    }

    private function match_best_result( array $results, string $title, int $year, string $season = '', int $episodes = 0 ): ?int {
        if ( empty( $results ) ) return null;

        $normalized_input = $this->normalize_title( $title );
        $input_quarter    = $this->season_to_quarter( $season );

        $p1 = $p2 = $p3 = $p4 = $candidates_secondary = $candidates_no_year = $all_candidates = [];

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
                error_log( sprintf( '[BGM MATCH] INPUT: %s | BGM: %s / %s | SIM: %.1f%%',
                    $normalized_input, $bgm_title_ja, $bgm_title_cn, $sim ) );
            }

            if ( $sim < 45.0 ) continue;

            $bgm_year     = $this->get_bangumi_year( $subject );
            $year_diff    = ( $year > 0 && $bgm_year > 0 ) ? abs( $bgm_year - $year ) : 999;
            $bgm_quarter  = $this->get_bangumi_quarter( $subject );
            $quarter_diff = ( $input_quarter > 0 && $bgm_quarter > 0 ) ? abs( $input_quarter - $bgm_quarter ) : 999;
            $bgm_eps      = (int) ( $subject['eps'] ?? 0 );
            $eps_diff     = ( $episodes > 0 && $bgm_eps > 0 ) ? abs( $bgm_eps - $episodes ) : 999;
            $eps_ok       = ( $eps_diff === 999 || $eps_diff <= 3 );

            $candidate = [ 'id' => $bgm_id, 'sim' => $sim, 'year_diff' => $year_diff,
                           'quarter_diff' => $quarter_diff, 'eps_diff' => $eps_diff ];

            $all_candidates[] = $candidate;

            if ( $year_diff === 999 ) {
                $candidates_no_year[] = $candidate;
            } elseif ( $year_diff <= 1 ) {
                if ( ( $quarter_diff === 999 || $quarter_diff === 0 ) && $eps_ok ) $p1[] = $candidate;
                elseif ( $quarter_diff === 1 && $eps_ok )                          $p2[] = $candidate;
                elseif ( $eps_ok )                                                 $p3[] = $candidate;
                elseif ( $eps_diff <= 6 )                                          $p4[] = $candidate;
                else                                                               $candidates_secondary[] = $candidate;
            } else {
                $candidates_secondary[] = $candidate;
            }
        }

        foreach ( [ $p1, $p2, $p3, $p4 ] as $pool ) {
            if ( ! empty( $pool ) ) {
                usort( $pool, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
                if ( self::DEBUG_LOG ) error_log( '[BGM MATCH] Selected ID=' . $pool[0]['id'] . ' sim=' . $pool[0]['sim'] );
                return $pool[0]['id'];
            }
        }

        if ( ! empty( $candidates_secondary ) ) {
            usort( $candidates_secondary, fn( $a, $b ) =>
                $a['year_diff'] !== $b['year_diff'] ? $a['year_diff'] <=> $b['year_diff'] : $b['sim'] <=> $a['sim'] );
            if ( $candidates_secondary[0]['year_diff'] <= 3 ) return $candidates_secondary[0]['id'];
        }

        if ( ! empty( $candidates_no_year ) ) {
            usort( $candidates_no_year, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
            if ( $candidates_no_year[0]['sim'] >= 80.0 ) return $candidates_no_year[0]['id'];
        }

        if ( ! empty( $all_candidates ) ) {
            usort( $all_candidates, fn( $a, $b ) => $b['sim'] <=> $a['sim'] );
            if ( $all_candidates[0]['sim'] >= 65.0 ) {
                if ( self::DEBUG_LOG ) error_log( '[BGM MATCH] fallback ID=' . $all_candidates[0]['id'] . ' sim=' . $all_candidates[0]['sim'] );
                return $all_candidates[0]['id'];
            }
        }

        return null;
    }

    // =========================================================================
    // PRIVATE – Layer 1.7：Jikan
    // =========================================================================

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
        if ( $title === '' ) return '';

        $title = mb_strtolower( $title );

        $roman_map = [
            'ⅻ' => '12', 'ⅺ' => '11', 'ⅹ' => '10',
            'ⅸ' => '9',  'ⅷ' => '8',  'ⅶ' => '7',
            'ⅵ' => '6',  'ⅴ' => '5',  'ⅳ' => '4',
            'ⅲ' => '3',  'ⅱ' => '2',  'ⅰ' => '1',
        ];
        $title = str_replace( array_keys( $roman_map ), array_values( $roman_map ), $title );

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

    private function normalize_name_light( string $name ): string {
        if ( $name === '' ) return '';
        $name = str_replace( '　', ' ', $name );
        $name = mb_strtolower( $name );
        return trim( $name );
    }

    // =========================================================================
    // PRIVATE – 季度工具
    // =========================================================================

    private function season_to_quarter( string $season ): int {
        return match( strtoupper( $season ) ) {
            'WINTER' => 1,
            'SPRING' => 2,
            'SUMMER' => 3,
            'FALL'   => 4,
            default  => 0,
        };
    }

    private function get_bangumi_quarter( array $subject ): int {
        $date  = $subject['date'] ?? '';
        if ( $date === '' ) return 0;
        $parts = explode( '-', $date );
        $month = isset( $parts[1] ) ? (int) $parts[1] : 0;
        if ( $month <= 0 ) return 0;
        if ( $month <= 3  ) return 1;
        if ( $month <= 6  ) return 2;
        if ( $month <= 9  ) return 3;
        if ( $month <= 12 ) return 4;
        return 0;
    }

    // =========================================================================
    // PRIVATE – Bangumi 年份
    // =========================================================================

    private function get_bangumi_year( array $subject ): int {
        foreach ( [ 'date', 'air_date' ] as $key ) {
            $val = $subject[ $key ] ?? '';
            if ( $val !== '' ) {
                $year = (int) substr( $val, 0, 4 );
                if ( $year > 1900 ) return $year;
            }
        }
        return 0;
    }

    // =========================================================================
    // PRIVATE – Bangumi ID 驗證
    // =========================================================================

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

        $bgm_year = $this->get_bangumi_year( $subject );
        if ( $year > 0 && $bgm_year > 0 && abs( $bgm_year - $year ) > 2 ) {
            set_transient( $cache_key, 0, DAY_IN_SECONDS );
            return null;
        }

        $sim = 0.0;
        similar_text( $this->normalize_title( $title ), $this->normalize_title( $subject['name'] ?? '' ), $sim );
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
        if ( $this->anime_map === null )
            $this->anime_map = $this->load_json_file( $this->get_file_path( self::MAP_FILE ) ) ?? [];
    }

    private function load_mal_index(): void {
        if ( $this->mal_index === null )
            $this->mal_index = $this->load_json_file( $this->get_file_path( self::MAL_INDEX_FILE ) ) ?? [];
    }

    private function load_al_index(): void {
        if ( $this->al_index === null )
            $this->al_index = $this->load_json_file( $this->get_file_path( self::AL_INDEX_FILE ) ) ?? [];
    }

    private function load_name_cache(): void {
        if ( $this->name_cache === null )
            $this->name_cache = $this->load_json_file( $this->get_file_path( self::NAME_CACHE_FILE ) ) ?? [];
    }

    private function load_bgm_ext_mal_index(): void {
        if ( $this->bgm_ext_mal_index === null )
            $this->bgm_ext_mal_index = $this->load_json_file( $this->get_file_path( self::BGM_EXT_MAL_INDEX_FILE ) ) ?? [];
    }

    private function load_bgm_ext_name_index(): void {
        if ( $this->bgm_ext_name_index === null )
            $this->bgm_ext_name_index = $this->load_json_file( $this->get_file_path( self::BGM_EXT_NAME_INDEX_FILE ) ) ?? [];
    }

    private function load_bgm_ext_anidb_index(): void {
        if ( $this->bgm_ext_anidb_index === null )
            $this->bgm_ext_anidb_index = $this->load_json_file( $this->get_file_path( self::BGM_EXT_ANIDB_INDEX_FILE ) ) ?? [];
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

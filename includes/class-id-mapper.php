<?php
/**
 * Class Anime_Sync_ID_Mapper
 *
 * Resolves Bangumi subject IDs from AniList / MAL IDs and titles.
 * Data source: Rhilip/BangumiExtLinker anime_map.json
 *
 * Lookup layers:
 *   0. WP post meta anime_bangumi_id         (100 % when present)
 *   1. mal_index.json  MAL ID → bgm_id       (~53 %)
 *   2. AniList externalLinks → bgm.tv URL    (high for new titles)
 *   3. Bangumi Search API + normalized title + year ±1  (~20–25 %)
 *   4. Bangumi Search API + Chinese title + year        (~3–5 %)
 *   5. Write _bangumi_id_pending flag                   (manual fallback)
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_ID_Mapper {

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    const REMOTE_URL     = 'https://raw.githubusercontent.com/Rhilip/BangumiExtLinker/main/data/anime_map.json';
    const REMOTE_CDN     = 'https://cdn.jsdelivr.net/gh/Rhilip/BangumiExtLinker@main/data/anime_map.json';
    const MAP_FILE       = 'anime_map.json';
    const MAL_INDEX_FILE = 'mal_index.json';
    const NAME_CACHE_FILE= 'name_cache.json';
    const META_FILE      = 'anime_map_meta.json';
    const BGM_SEARCH_URL = 'https://api.bgm.tv/search/subject/';
    const BGM_SUBJECT_URL= 'https://api.bgm.tv/v0/subjects/';

    // -------------------------------------------------------------------------
    // Instance state
    // -------------------------------------------------------------------------

    /** @var string Absolute path to the upload cache directory */
    private string $cache_dir;

    /** @var string|null Last error message */
    private ?string $last_error = null;

    // Static in-memory caches (shared across instances within one request)
    private static ?array $mal_index_cache  = null;
    private static ?array $name_cache_cache = null;
    private static ?array $meta_cache       = null;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function __construct() {
        $upload             = wp_upload_dir();
        $this->cache_dir    = trailingslashit( $upload['basedir'] ) . 'anime-sync-pro/';
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Primary lookup: resolve Bangumi ID from available anime data.
     *
     * @param array $anime_data {
     *   int|null    $mal_id
     *   int|null    $anilist_id
     *   int         $post_id       (0 if not yet saved)
     *   string      $title_native  Japanese title from AniList
     *   string      $title_chinese Simplified Chinese title
     *   int|null    $season_year
     *   array       $external_links AniList externalLinks array
     * }
     * @return int|null Bangumi subject ID, or null on failure.
     */
    public function get_bangumi_id( array $anime_data ): ?int {

        $mal_id         = isset( $anime_data['mal_id'] )         ? (int) $anime_data['mal_id']        : 0;
        $anilist_id     = isset( $anime_data['anilist_id'] )      ? (int) $anime_data['anilist_id']     : 0;
        $post_id        = isset( $anime_data['post_id'] )         ? (int) $anime_data['post_id']        : 0;
        $title_native   = isset( $anime_data['title_native'] )    ? (string) $anime_data['title_native']  : '';
        $title_chinese  = isset( $anime_data['title_chinese'] )   ? (string) $anime_data['title_chinese'] : '';
        $season_year    = isset( $anime_data['season_year'] )     ? (int) $anime_data['season_year']   : 0;
        $external_links = isset( $anime_data['external_links'] )  ? (array) $anime_data['external_links'] : [];

        // --- Layer 0: existing WP post meta -----------------------------------
        if ( $post_id > 0 ) {
            $saved = get_post_meta( $post_id, 'anime_bangumi_id', true );
            if ( $saved && (int) $saved > 0 ) {
                return (int) $saved;
            }
        }

        // --- Layer 1: mal_index.json ------------------------------------------
        if ( $mal_id > 0 ) {
            $bgm_id = $this->lookup_by_mal( $mal_id );
            if ( $bgm_id ) {
                return $bgm_id;
            }
        }

        // --- Layer 2: AniList externalLinks → bgm.tv --------------------------
        if ( ! empty( $external_links ) ) {
            $bgm_id = $this->lookup_from_external_links( $external_links );
            if ( $bgm_id ) {
                return $bgm_id;
            }
        }

        // --- Layer 3: Bangumi Search + normalized Japanese title + year -------
        if ( $title_native !== '' && $season_year > 0 ) {
            $base = $this->normalize_title( $title_native );
            if ( $base !== '' ) {
                $bgm_id = $this->search_bangumi( $base, $season_year, 'native' );
                if ( $bgm_id ) {
                    return $bgm_id;
                }
            }
        }

        // --- Layer 4: Bangumi Search + Chinese title + year -------------------
        if ( $title_chinese !== '' && $season_year > 0 ) {
            $base = $this->normalize_title( $title_chinese );
            if ( $base !== '' ) {
                $bgm_id = $this->search_bangumi( $base, $season_year, 'chinese' );
                if ( $bgm_id ) {
                    return $bgm_id;
                }
            }
        }

        // --- Layer 5: mark as pending for manual entry ------------------------
        if ( $post_id > 0 ) {
            update_post_meta( $post_id, '_bangumi_id_pending', 1 );
            Anime_Sync_Error_Logger::warning(
                'Bangumi ID not found; marked as pending.',
                [
                    'anilist_id'    => $anilist_id,
                    'mal_id'        => $mal_id,
                    'title_native'  => $title_native,
                    'season_year'   => $season_year,
                ]
            );
        }

        return null;
    }

    /**
     * Backward-compatible wrapper for older callers.
     *
     * @param int|null   $anilist_id
     * @param int|null   $mal_id
     * @param int|null   $bangumi_id   Already-resolved ID (skips lookup if set)
     * @param array      $anilist_data Full AniList API response array
     * @param int        $post_id
     * @return array { anilist_id, mal_id, bangumi_id }
     */
    public function resolve_ids(
        $anilist_id,
        $mal_id,
        $bangumi_id,
        array $anilist_data = [],
        int $post_id = 0
    ): array {
        // If Bangumi ID already known, return early
        if ( $bangumi_id && (int) $bangumi_id > 0 ) {
            return [
                'anilist_id' => (int) $anilist_id,
                'mal_id'     => $mal_id ? (int) $mal_id : null,
                'bangumi_id' => (int) $bangumi_id,
            ];
        }

        // Build $anime_data from anilist_data
        $external_links = [];
        if ( ! empty( $anilist_data['externalLinks'] ) && is_array( $anilist_data['externalLinks'] ) ) {
            $external_links = $anilist_data['externalLinks'];
        }

        $title_native  = $anilist_data['title']['native']  ?? '';
        $title_chinese = $anilist_data['title']['chinese'] ?? '';
        $season_year   = $anilist_data['seasonYear']       ?? 0;

        $resolved = $this->get_bangumi_id( [
            'anilist_id'     => $anilist_id,
            'mal_id'         => $mal_id,
            'post_id'        => $post_id,
            'title_native'   => $title_native,
            'title_chinese'  => $title_chinese,
            'season_year'    => $season_year,
            'external_links' => $external_links,
        ] );

        return [
            'anilist_id' => $anilist_id ? (int) $anilist_id : null,
            'mal_id'     => $mal_id     ? (int) $mal_id     : null,
            'bangumi_id' => $resolved,
        ];
    }

    // -------------------------------------------------------------------------

    /**
     * Download remote anime_map.json only if changed (ETag check),
     * then rebuild indexes.
     *
     * @return int|false Number of entries indexed, or false on failure.
     */
    public function download_and_cache_map(): int|false {
        $meta     = $this->load_meta();
        $old_etag = $meta['etag'] ?? '';

        // HEAD request to check ETag
        $head = wp_remote_head( self::REMOTE_URL, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
        ] );

        $remote_etag = '';
        if ( ! is_wp_error( $head ) ) {
            $status = wp_remote_retrieve_response_code( $head );
            if ( (int) $status !== 200 && (int) $status !== 304 ) {
                Anime_Sync_Error_Logger::warning(
                    'anime_map.json HEAD returned unexpected status.',
                    [ 'status' => $status ]
                );
            }
            $remote_etag = wp_remote_retrieve_header( $head, 'etag' );
        }

        // Skip download if ETag unchanged
        if ( $remote_etag && $remote_etag === $old_etag ) {
            Anime_Sync_Error_Logger::info( 'anime_map.json unchanged (ETag match); rebuilding indexes from cache.' );
            return $this->rebuild_indexes();
        }

        // Download full file
        $map_path = $this->cache_dir . self::MAP_FILE;
        $response = wp_remote_get( self::REMOTE_URL, [
            'timeout'    => 120,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
        ] );

        if ( is_wp_error( $response ) ) {
            // Fallback to jsDelivr CDN
            Anime_Sync_Error_Logger::warning(
                'Primary download failed, trying CDN.',
                [ 'error' => $response->get_error_message() ]
            );
            $response = wp_remote_get( self::REMOTE_CDN, [
                'timeout'    => 120,
                'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            ] );
        }

        if ( is_wp_error( $response ) ) {
            $this->last_error = $response->get_error_message();
            Anime_Sync_Error_Logger::error(
                'anime_map.json download failed.',
                [ 'error' => $this->last_error ]
            );
            return false;
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            $this->last_error = "HTTP {$http_code}";
            Anime_Sync_Error_Logger::error(
                'anime_map.json download returned non-200.',
                [ 'http_code' => $http_code ]
            );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            $this->last_error = 'Empty response body.';
            Anime_Sync_Error_Logger::error( 'anime_map.json download returned empty body.' );
            return false;
        }

        // Write to .tmp first
        $tmp_map = $map_path . '.tmp';
        if ( false === file_put_contents( $tmp_map, $body ) ) {
            $this->last_error = 'Cannot write anime_map.json.tmp';
            Anime_Sync_Error_Logger::error( $this->last_error );
            return false;
        }

        // Validate JSON
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) || empty( $decoded ) ) {
            @unlink( $tmp_map );
            $this->last_error = 'Downloaded anime_map.json is not a valid JSON array.';
            Anime_Sync_Error_Logger::error( $this->last_error );
            return false;
        }

        // Atomic rename
        if ( ! rename( $tmp_map, $map_path ) ) {
            @unlink( $tmp_map );
            $this->last_error = 'Cannot rename anime_map.json.tmp to anime_map.json';
            Anime_Sync_Error_Logger::error( $this->last_error );
            return false;
        }

        // Update ETag in meta before rebuilding
        $meta['etag'] = $remote_etag;
        $this->save_meta_partial( $meta );

        // Rebuild indexes from the newly saved file
        $count = $this->rebuild_indexes( $decoded );

        Anime_Sync_Error_Logger::info(
            'anime_map.json downloaded and indexed.',
            [ 'entry_count' => $count ]
        );

        return $count;
    }

    // -------------------------------------------------------------------------

    /**
     * Rebuild mal_index.json and name_cache.json from the local anime_map.json.
     * Accepts an already-decoded array to avoid re-reading disk if available.
     *
     * @param array|null $data Pre-decoded array (optional)
     * @return int|false Number of entries, or false on failure.
     */
    public function rebuild_indexes( ?array $data = null ): int|false {
        Anime_Sync_Performance::increase_memory_limit( '128M' );

        if ( $data === null ) {
            $map_path = $this->cache_dir . self::MAP_FILE;
            if ( ! file_exists( $map_path ) ) {
                $this->last_error = 'anime_map.json not found; cannot rebuild indexes.';
                Anime_Sync_Error_Logger::error( $this->last_error );
                return false;
            }
            $raw  = file_get_contents( $map_path );
            $data = json_decode( $raw, true );
            if ( ! is_array( $data ) ) {
                $this->last_error = 'Failed to decode anime_map.json during rebuild.';
                Anime_Sync_Error_Logger::error( $this->last_error );
                return false;
            }
        }

        $mal_index  = [];
        $name_cache = [];
        $entry_count = 0;
        $mal_count   = 0;

        foreach ( $data as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }
            $bgm_id = isset( $entry['bgm_id'] ) ? trim( (string) $entry['bgm_id'] ) : '';
            if ( $bgm_id === '' ) {
                continue;
            }

            $entry_count++;

            // mal_index
            $mal_id = isset( $entry['mal_id'] ) ? trim( (string) $entry['mal_id'] ) : '';
            if ( $mal_id !== '' && $mal_id !== '0' ) {
                $mal_index[ $mal_id ] = $bgm_id;
                $mal_count++;
            }

            // name_cache (繁化)
            $name_cn = isset( $entry['name_cn'] ) ? trim( (string) $entry['name_cn'] ) : '';
            if ( $name_cn !== '' ) {
                $name_cache[ $bgm_id ] = Anime_Sync_CN_Converter::static_convert( $name_cn );
            }
        }

        // Write .tmp files
        $tmp_mal  = $this->cache_dir . self::MAL_INDEX_FILE  . '.tmp';
        $tmp_name = $this->cache_dir . self::NAME_CACHE_FILE . '.tmp';
        $tmp_meta = $this->cache_dir . self::META_FILE       . '.tmp';

        $meta_data = [
            'source_url'  => self::REMOTE_URL,
            'version'     => $this->load_meta()['etag'] ?? '',
            'entry_count' => $entry_count,
            'mal_count'   => $mal_count,
            'generated_at'=> gmdate( 'Y-m-d\TH:i:s\Z' ),
            'etag'        => $this->load_meta()['etag'] ?? '',
        ];

        $ok_mal  = file_put_contents( $tmp_mal,  wp_json_encode( $mal_index,  JSON_UNESCAPED_UNICODE ) );
        $ok_name = file_put_contents( $tmp_name, wp_json_encode( $name_cache, JSON_UNESCAPED_UNICODE ) );
        $ok_meta = file_put_contents( $tmp_meta, wp_json_encode( $meta_data,  JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

        if ( $ok_mal === false || $ok_name === false || $ok_meta === false ) {
            @unlink( $tmp_mal );
            @unlink( $tmp_name );
            @unlink( $tmp_meta );
            $this->last_error = 'Failed to write tmp index files.';
            Anime_Sync_Error_Logger::error( $this->last_error );
            return false;
        }

        // Validate .tmp files before renaming
        if (
            ! $this->validate_json_file( $tmp_mal )  ||
            ! $this->validate_json_file( $tmp_name ) ||
            ! $this->validate_json_file( $tmp_meta )
        ) {
            @unlink( $tmp_mal );
            @unlink( $tmp_name );
            @unlink( $tmp_meta );
            $this->last_error = 'Validation of tmp index files failed.';
            Anime_Sync_Error_Logger::error( $this->last_error );
            return false;
        }

        // Atomic rename
        rename( $tmp_mal,  $this->cache_dir . self::MAL_INDEX_FILE );
        rename( $tmp_name, $this->cache_dir . self::NAME_CACHE_FILE );
        rename( $tmp_meta, $this->cache_dir . self::META_FILE );

        // Clear static caches
        self::$mal_index_cache  = null;
        self::$name_cache_cache = null;
        self::$meta_cache       = null;

        return $entry_count;
    }

    // -------------------------------------------------------------------------

    /**
     * Return current map/index status (reads anime_map_meta.json).
     * Used by settings.php and admin UI.
     */
    public function get_map_status(): array {
        $meta      = $this->load_meta();
        $map_path  = $this->cache_dir . self::MAP_FILE;
        $exists    = file_exists( $map_path );
        $age_hours = 0.0;

        if ( $exists && ! empty( $meta['generated_at'] ) ) {
            $ts = strtotime( $meta['generated_at'] );
            if ( $ts ) {
                $age_hours = round( ( time() - $ts ) / 3600, 2 );
            }
        }

        return [
            'exists'       => $exists,
            'path'         => $map_path,
            'size'         => $exists ? (int) filesize( $map_path ) : 0,
            'entry_count'  => (int) ( $meta['entry_count']  ?? 0 ),
            'mal_count'    => (int) ( $meta['mal_count']    ?? 0 ),
            'last_updated' => $meta['generated_at'] ?? '',
            'age_hours'    => $age_hours,
            'version'      => $meta['etag']         ?? '',
        ];
    }

    // =========================================================================
    // PRIVATE – Lookup Helpers
    // =========================================================================

    /**
     * Layer 1: Look up Bangumi ID via mal_index.json.
     */
    private function lookup_by_mal( int $mal_id ): ?int {
        $index = $this->load_mal_index();
        $key   = (string) $mal_id;
        if ( isset( $index[ $key ] ) && $index[ $key ] !== '' ) {
            return (int) $index[ $key ];
        }
        return null;
    }

    // -------------------------------------------------------------------------

    /**
     * Layer 2: Extract Bangumi ID from AniList externalLinks array.
     *
     * Accepts both flat arrays of URLs and associative arrays:
     *   [ ['url' => 'https://bgm.tv/subject/515594', 'site' => 'Bangumi'], ... ]
     */
    private function lookup_from_external_links( array $external_links ): ?int {
        foreach ( $external_links as $link ) {
            $url = '';
            if ( is_array( $link ) ) {
                $url = $link['url'] ?? '';
            } elseif ( is_string( $link ) ) {
                $url = $link;
            }

            if ( $url === '' ) {
                continue;
            }

            // Match https://bgm.tv/subject/123456 or https://bangumi.tv/subject/123456
            if ( preg_match( '#(?:bgm|bangumi)\.tv/subject/(\d+)#i', $url, $m ) ) {
                $bgm_id = (int) $m[1];
                if ( $bgm_id > 0 ) {
                    return $bgm_id;
                }
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------

    /**
     * Layers 3 & 4: Search Bangumi API and match by normalized title + year.
     *
     * @param string $title      Already-normalized base title.
     * @param int    $year       Season year for filtering.
     * @param string $title_type 'native' or 'chinese' (for logging only).
     * @return int|null
     */
    private function search_bangumi( string $title, int $year, string $title_type ): ?int {
        $url = add_query_arg(
            [
                'type'  => 2,      // 2 = anime
                'responseGroup' => 'small',
            ],
            self::BGM_SEARCH_URL . rawurlencode( $title )
        );

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            Anime_Sync_Error_Logger::warning(
                "Bangumi search failed ({$title_type}).",
                [ 'title' => $title, 'error' => $response->get_error_message() ]
            );
            return null;
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $list = $body['list'] ?? [];

        if ( empty( $list ) ) {
            return null;
        }

        return $this->match_best_result( $list, $title, $year );
    }

    // -------------------------------------------------------------------------

    /**
     * Find the best-matching Bangumi subject from a search result list.
     *
     * Matching criteria:
     *  1. Normalize candidate title (Japanese name or Chinese name).
     *  2. Exact string match with query $base_title.
     *  3. Year tolerance ±1 (extracted from date field "YYYY-MM-DD").
     *  4. When multiple candidates match, prefer the closest year.
     *
     * @param array  $list        Bangumi search result list.
     * @param string $base_title  Already-normalized query title.
     * @param int    $year        Expected season year.
     * @return int|null
     */
    private function match_best_result( array $list, string $base_title, int $year ): ?int {
        $best_id   = null;
        $best_diff = PHP_INT_MAX;

        foreach ( $list as $item ) {
            if ( empty( $item['id'] ) ) {
                continue;
            }

            // Extract year from date (format: "YYYY-MM-DD" or "YYYY")
            $item_year = 0;
            if ( ! empty( $item['date'] ) ) {
                if ( preg_match( '/^(\d{4})/', $item['date'], $ym ) ) {
                    $item_year = (int) $ym[1];
                }
            } elseif ( ! empty( $item['air_date'] ) ) {
                if ( preg_match( '/^(\d{4})/', $item['air_date'], $ym ) ) {
                    $item_year = (int) $ym[1];
                }
            }

            // Year filter: allow ±1 tolerance
            if ( $item_year > 0 && abs( $item_year - $year ) > 1 ) {
                continue;
            }

            // Check both name_cn and name fields
            $candidates = [];
            if ( ! empty( $item['name'] ) ) {
                $candidates[] = $item['name'];
            }
            if ( ! empty( $item['name_cn'] ) ) {
                $candidates[] = $item['name_cn'];
            }

            $matched = false;
            foreach ( $candidates as $candidate ) {
                $normalized_candidate = $this->normalize_title( $candidate );
                if ( $normalized_candidate === $base_title ) {
                    $matched = true;
                    break;
                }
            }

            if ( ! $matched ) {
                continue;
            }

            // Prefer closest year
            $diff = $item_year > 0 ? abs( $item_year - $year ) : 1;
            if ( $diff < $best_diff ) {
                $best_diff = $diff;
                $best_id   = (int) $item['id'];
            }
        }

        return $best_id;
    }

    // =========================================================================
    // PRIVATE – Title Normalization
    // =========================================================================

    /**
     * Strip season/episode markers and return a clean base title for comparison.
     *
     * Patterns removed (case-insensitive):
     *  - "1st/2nd/3rd/4th... Season"
     *  - "Season 2"
     *  - "第X期 / 第 X 期"
     *  - "Xth Season 2年生編1学期" style suffixes
     *  - "X年生編" / "X年生篇"
     *  - "X学期" / "X學期"
     *  - "X期制"
     *  - Trailing standalone numbers " 2" / " 2nd" etc.
     *  - Extra whitespace → single space
     *
     * @param string $title Raw title.
     * @return string Normalized title.
     */
    public function normalize_title( string $title ): string {
        $title = trim( $title );

        // Remove ordinal season patterns: "4th Season", "2nd Season", etc.
        $title = preg_replace( '/\s*\d+(?:st|nd|rd|th)\s+season\b/ui', '', $title );

        // Remove "Season X"
        $title = preg_replace( '/\s*season\s*\d+\b/ui', '', $title );

        // Remove "第X期" / "第 X 期"
        $title = preg_replace( '/\s*第\s*\d+\s*期/u', '', $title );

        // Remove "Xth Season" (guard for remaining ordinal+season combos)
        $title = preg_replace( '/\s*\d+th\s+season/ui', '', $title );

        // Remove "X年生編" / "X年生篇" (e.g., 2年生編)
        $title = preg_replace( '/\s*\d+年生[編篇]/u', '', $title );

        // Remove "X学期" / "X學期"
        $title = preg_replace( '/\s*\d+[学學]期/u', '', $title );

        // Remove "X期制"
        $title = preg_replace( '/\s*\d+期制/u', '', $title );

        // Remove trailing standalone digit(s) (e.g., " 2" at end)
        $title = preg_replace( '/\s+\d+$/u', '', $title );

        // Collapse multiple spaces
        $title = preg_replace( '/\s+/u', ' ', $title );

        return trim( $title );
    }

    // =========================================================================
    // PRIVATE – Cache Loaders
    // =========================================================================

    /**
     * Load and statically cache mal_index.json.
     */
    private function load_mal_index(): array {
        if ( self::$mal_index_cache !== null ) {
            return self::$mal_index_cache;
        }
        $path = $this->cache_dir . self::MAL_INDEX_FILE;
        if ( ! file_exists( $path ) ) {
            return [];
        }
        $decoded = json_decode( file_get_contents( $path ), true );
        self::$mal_index_cache = is_array( $decoded ) ? $decoded : [];
        return self::$mal_index_cache;
    }

    // -------------------------------------------------------------------------

    /**
     * Load and statically cache name_cache.json.
     */
    private function load_name_cache(): array {
        if ( self::$name_cache_cache !== null ) {
            return self::$name_cache_cache;
        }
        $path = $this->cache_dir . self::NAME_CACHE_FILE;
        if ( ! file_exists( $path ) ) {
            return [];
        }
        $decoded = json_decode( file_get_contents( $path ), true );
        self::$name_cache_cache = is_array( $decoded ) ? $decoded : [];
        return self::$name_cache_cache;
    }

    // -------------------------------------------------------------------------

    /**
     * Load and statically cache anime_map_meta.json.
     */
    private function load_meta(): array {
        if ( self::$meta_cache !== null ) {
            return self::$meta_cache;
        }
        $path = $this->cache_dir . self::META_FILE;
        if ( ! file_exists( $path ) ) {
            return [];
        }
        $decoded = json_decode( file_get_contents( $path ), true );
        self::$meta_cache = is_array( $decoded ) ? $decoded : [];
        return self::$meta_cache;
    }

    // -------------------------------------------------------------------------

    /**
     * Partially update and persist meta (used to save ETag before full rebuild).
     */
    private function save_meta_partial( array $meta ): void {
        $path = $this->cache_dir . self::META_FILE;
        file_put_contents( $path, wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
        self::$meta_cache = $meta;
    }

    // =========================================================================
    // PRIVATE – Utilities
    // =========================================================================

    /**
     * Validate that a file exists and contains a non-empty JSON structure.
     */
    private function validate_json_file( string $path ): bool {
        if ( ! file_exists( $path ) ) {
            return false;
        }
        $decoded = json_decode( file_get_contents( $path ), true );
        return is_array( $decoded ) && ! empty( $decoded );
    }

    // -------------------------------------------------------------------------

    /**
     * Get Chinese title from name_cache.json by Bangumi ID.
     * Used externally by API handler or admin UI to display Chinese name.
     *
     * @param int $bgm_id
     * @return string|null
     */
    public function get_chinese_title( int $bgm_id ): ?string {
        $cache = $this->load_name_cache();
        $key   = (string) $bgm_id;
        return isset( $cache[ $key ] ) ? $cache[ $key ] : null;
    }

    // -------------------------------------------------------------------------

    /**
     * Return last error message (for admin UI display).
     */
    public function get_last_error(): ?string {
        return $this->last_error;
    }
}

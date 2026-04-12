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
 * Bugs fixed in this version:
 *   AX  – constructor ensures cache directory exists; normalize_title \s+\d+$ → \s+\d{1}$
 *   AY  – search_bangumi() rate-limited via Anime_Sync_Rate_Limiter
 *   AZ  – search_bangumi() results cached in transient (DAY_IN_SECONDS)
 *   ABA – normalize_title() full-width → half-width unification + mb_convert_kana
 *   ABB – search_bangumi() has 2-attempt retry on wp_remote_get failure
 *   ABC – season_year = 0 falls back to title-only search (no year filter)
 *   ABD – normalize_title() strips The Final / Final Season / Part N / Cour N etc.
 *   ABE – search_bangumi() retries with stripped keyword when special chars present
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

    const REMOTE_URL      = 'https://raw.githubusercontent.com/Rhilip/BangumiExtLinker/main/data/anime_map.json';
    const REMOTE_CDN      = 'https://cdn.jsdelivr.net/gh/Rhilip/BangumiExtLinker@main/data/anime_map.json';
    const MAP_FILE        = 'anime_map.json';
    const MAL_INDEX_FILE  = 'mal_index.json';
    const NAME_CACHE_FILE = 'name_cache.json';
    const META_FILE       = 'anime_map_meta.json';
    const BGM_SEARCH_URL  = 'https://api.bgm.tv/search/subject/';
    const BGM_SUBJECT_URL = 'https://api.bgm.tv/v0/subjects/';

    // Transient TTL for Bangumi search results
    const SEARCH_CACHE_TTL = DAY_IN_SECONDS;

    // -------------------------------------------------------------------------
    // Instance state
    // -------------------------------------------------------------------------

    /** @var string Absolute path to the upload cache directory */
    private string $cache_dir;

    /** @var Anime_Sync_Rate_Limiter */
    private Anime_Sync_Rate_Limiter $rate_limiter;

    /** @var string|null Last error message */
    private ?string $last_error = null;

    // Static in-memory caches (shared across instances within one request)
    private static ?array $mal_index_cache  = null;
    private static ?array $name_cache_cache = null;
    private static ?array $meta_cache       = null;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function __construct( ?Anime_Sync_Rate_Limiter $rate_limiter = null ) {
        $upload          = wp_upload_dir();
        $this->cache_dir = trailingslashit( $upload['basedir'] ) . 'anime-sync-pro/';

        // Bug AX fix: ensure cache directory exists
        if ( ! file_exists( $this->cache_dir ) ) {
            wp_mkdir_p( $this->cache_dir );
        }

        $this->rate_limiter = $rate_limiter ?? new Anime_Sync_Rate_Limiter();
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Primary lookup: resolve Bangumi ID from available anime data.
     *
     * @param array $anime_data {
     *   int|null  mal_id
     *   int|null  anilist_id
     *   int       post_id        (0 if not yet saved)
     *   string    title_native   Japanese title from AniList
     *   string    title_chinese  Simplified Chinese title
     *   int|null  season_year
     *   array     external_links AniList externalLinks array
     * }
     * @return int|null Bangumi subject ID, or null on failure.
     */
    public function get_bangumi_id( array $anime_data ): ?int {

        $mal_id         = isset( $anime_data['mal_id'] )        ? (int) $anime_data['mal_id']         : 0;
        $anilist_id     = isset( $anime_data['anilist_id'] )     ? (int) $anime_data['anilist_id']      : 0;
        $post_id        = isset( $anime_data['post_id'] )        ? (int) $anime_data['post_id']         : 0;
        $title_native   = isset( $anime_data['title_native'] )   ? (string) $anime_data['title_native']   : '';
        $title_chinese  = isset( $anime_data['title_chinese'] )  ? (string) $anime_data['title_chinese']  : '';
        $season_year    = isset( $anime_data['season_year'] )    ? (int) $anime_data['season_year']    : 0;
        $external_links = isset( $anime_data['external_links'] ) ? (array) $anime_data['external_links'] : [];

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

        // --- Layer 3: Bangumi Search + normalized Japanese title --------------
        if ( $title_native !== '' ) {
            $base = $this->normalize_title( $title_native );
            if ( $base !== '' ) {
                // Bug ABC fix: pass season_year even if 0; search handles it
                $bgm_id = $this->search_bangumi( $base, $season_year, 'native' );
                if ( $bgm_id ) {
                    return $bgm_id;
                }
            }
        }

        // --- Layer 4: Bangumi Search + Chinese title --------------------------
        if ( $title_chinese !== '' ) {
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
                    'anilist_id'   => $anilist_id,
                    'mal_id'       => $mal_id,
                    'title_native' => $title_native,
                    'season_year'  => $season_year,
                ]
            );
        }

        return null;
    }

    // -------------------------------------------------------------------------

    /**
     * Backward-compatible wrapper for older callers.
     *
     * @param int|null $anilist_id
     * @param int|null $mal_id
     * @param int|null $bangumi_id  Already-resolved ID (skips lookup if set)
     * @param array    $anilist_data Full AniList API response array
     * @param int      $post_id
     * @return array { anilist_id, mal_id, bangumi_id }
     */
    public function resolve_ids(
        $anilist_id,
        $mal_id,
        $bangumi_id,
        array $anilist_data = [],
        int $post_id = 0
    ): array {
        if ( $bangumi_id && (int) $bangumi_id > 0 ) {
            return [
                'anilist_id' => (int) $anilist_id,
                'mal_id'     => $mal_id ? (int) $mal_id : null,
                'bangumi_id' => (int) $bangumi_id,
            ];
        }

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
    public function download_and_cache_map() {
        $meta     = $this->load_meta();
        $old_etag = $meta['etag'] ?? '';

        // HEAD request to check ETag
        $head = wp_remote_head( self::REMOTE_URL, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
        ] );

        $remote_etag = '';
        if ( ! is_wp_error( $head ) ) {
            $status = (int) wp_remote_retrieve_response_code( $head );
            // Bug AB fix: validate HTTP status
            if ( $status !== 200 && $status !== 304 ) {
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
     *
     * @param array|null $data Pre-decoded array (optional)
     * @return int|false Number of entries, or false on failure.
     */
    public function rebuild_indexes( ?array $data = null ) {
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

        $mal_index   = [];
        $name_cache  = [];
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

            $mal_id = isset( $entry['mal_id'] ) ? trim( (string) $entry['mal_id'] ) : '';
            if ( $mal_id !== '' && $mal_id !== '0' ) {
                $mal_index[ $mal_id ] = $bgm_id;
                $mal_count++;
            }

            $name_cn = isset( $entry['name_cn'] ) ? trim( (string) $entry['name_cn'] ) : '';
            if ( $name_cn !== '' ) {
                $name_cache[ $bgm_id ] = Anime_Sync_CN_Converter::static_convert( $name_cn );
            }
        }

        $tmp_mal  = $this->cache_dir . self::MAL_INDEX_FILE  . '.tmp';
        $tmp_name = $this->cache_dir . self::NAME_CACHE_FILE . '.tmp';
        $tmp_meta = $this->cache_dir . self::META_FILE       . '.tmp';

        $existing_meta = $this->load_meta();
        $meta_data = [
            'source_url'   => self::REMOTE_URL,
            'version'      => $existing_meta['etag']  ?? '',
            'entry_count'  => $entry_count,
            'mal_count'    => $mal_count,
            'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ), // Bug AA fix
            'etag'         => $existing_meta['etag']  ?? '',
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

        rename( $tmp_mal,  $this->cache_dir . self::MAL_INDEX_FILE );
        rename( $tmp_name, $this->cache_dir . self::NAME_CACHE_FILE );
        rename( $tmp_meta, $this->cache_dir . self::META_FILE );

        self::$mal_index_cache  = null;
        self::$name_cache_cache = null;
        self::$meta_cache       = null;

        return $entry_count;
    }

    // -------------------------------------------------------------------------

    /**
     * Return current map/index status (reads anime_map_meta.json).
     * Bug AV fix: reads lightweight meta file instead of parsing full JSON.
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
     * Bug AJ fix: also checks site name for early filtering.
     */
    private function lookup_from_external_links( array $external_links ): ?int {
        foreach ( $external_links as $link ) {
            $url  = '';
            $site = '';

            if ( is_array( $link ) ) {
                $url  = $link['url']  ?? '';
                $site = $link['site'] ?? '';
            } elseif ( is_string( $link ) ) {
                $url = $link;
            }

            if ( $url === '' ) {
                continue;
            }

            // Quick site-name filter to reduce regex calls
            if ( $site !== '' && stripos( $site, 'bangumi' ) === false ) {
                // Still try URL regex in case site name differs
                if ( stripos( $url, 'bgm.tv' ) === false && stripos( $url, 'bangumi.tv' ) === false ) {
                    continue;
                }
            }

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
     * Bug AY  – rate-limited before each request.
     * Bug AZ  – results cached in transient.
     * Bug ABB – retries once on wp_remote_get failure.
     * Bug ABE – retries with stripped keyword if special chars present.
     * Bug ABC – when year = 0, skips year filter in match_best_result().
     *
     * @param string $title      Already-normalized base title.
     * @param int    $year       Season year (0 = unknown).
     * @param string $title_type 'native' or 'chinese' (for logging only).
     * @return int|null
     */
    private function search_bangumi( string $title, int $year, string $title_type ): ?int {

        // Bug AZ: check transient cache first
        $cache_key = 'bgm_search_' . md5( $title . '_' . $year );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached > 0 ? (int) $cached : null;
        }

        $result = $this->do_bangumi_search( $title, $year, $title_type );

        // Bug ABE: retry with keyword-only (strip special chars) if no result
        if ( $result === null && preg_match( '/[^\w\s\x{3000}-\x{9FFF}\x{F900}-\x{FAFF}]/u', $title ) ) {
            $stripped = preg_replace( '/[^\w\s\x{3000}-\x{9FFF}\x{F900}-\x{FAFF}]/u', ' ', $title );
            $stripped = trim( preg_replace( '/\s+/u', ' ', $stripped ) );
            if ( $stripped !== '' && $stripped !== $title ) {
                $result = $this->do_bangumi_search( $stripped, $year, $title_type . '_stripped' );
            }
        }

        // Cache result (store 0 for null to avoid re-querying)
        set_transient( $cache_key, $result ?? 0, self::SEARCH_CACHE_TTL );

        return $result;
    }

    // -------------------------------------------------------------------------

    /**
     * Internal: perform one Bangumi search request with rate-limit + retry.
     */
    private function do_bangumi_search( string $title, int $year, string $title_type ): ?int {

        // Bug AY: rate limit before calling Bangumi Search API
        $this->rate_limiter->wait_if_needed( 'bangumi' );

        $url = add_query_arg(
            [
                'type'          => 2,
                'responseGroup' => 'small',
            ],
            self::BGM_SEARCH_URL . rawurlencode( $title )
        );

        $args = [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ];

        // Bug ABB: retry once on failure
        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) {
            sleep( 1 );
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $response = wp_remote_get( $url, $args );
        }

        if ( is_wp_error( $response ) ) {
            Anime_Sync_Error_Logger::warning(
                "Bangumi search failed ({$title_type}) after retry.",
                [ 'title' => $title, 'error' => $response->get_error_message() ]
            );
            return null;
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        if ( $http_code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response );
            return null;
        }
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
     * Bug ABC fix: when $year = 0, skip year filter and pick highest-rated match.
     *
     * @param array  $list       Bangumi search result list.
     * @param string $base_title Already-normalized query title.
     * @param int    $year       Expected season year (0 = unknown).
     * @return int|null
     */
    private function match_best_result( array $list, string $base_title, int $year ): ?int {
        $best_id    = null;
        $best_diff  = PHP_INT_MAX;
        $best_count = -1; // fallback: use rating count when year unknown

        foreach ( $list as $item ) {
            if ( empty( $item['id'] ) ) {
                continue;
            }

            // Extract year from date field
            $item_year = 0;
            $date_raw  = $item['date'] ?? $item['air_date'] ?? '';
            if ( $date_raw !== '' && preg_match( '/(\d{4})/', $date_raw, $ym ) ) {
                $item_year = (int) $ym[1];
            }

            // Year filter: skip if year known and difference > 1
            // Bug ABC fix: skip filter entirely when $year = 0
            if ( $year > 0 && $item_year > 0 && abs( $item_year - $year ) > 1 ) {
                continue;
            }

            // Title matching: normalize both sides and compare
            $candidates = [];
            if ( ! empty( $item['name'] ) ) {
                $candidates[] = $item['name'];
            }
            if ( ! empty( $item['name_cn'] ) ) {
                $candidates[] = $item['name_cn'];
            }

            $matched = false;
            foreach ( $candidates as $candidate ) {
                if ( $this->normalize_title( $candidate ) === $base_title ) {
                    $matched = true;
                    break;
                }
            }

            if ( ! $matched ) {
                continue;
            }

            if ( $year > 0 ) {
                // Known year: prefer closest match
                $diff = $item_year > 0 ? abs( $item_year - $year ) : 1;
                if ( $diff < $best_diff ) {
                    $best_diff = $diff;
                    $best_id   = (int) $item['id'];
                }
            } else {
                // Bug ABC fix: unknown year → prefer entry with most ratings/collection count
                $count = (int) ( $item['collection']['collect'] ?? $item['rank'] ?? 0 );
                if ( $best_id === null || $count > $best_count ) {
                    $best_count = $count;
                    $best_id    = (int) $item['id'];
                }
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
     * Bugs fixed:
     *   AX  – trailing digit pattern changed to \s+\d{1}$ (single digit only)
     *   ABA – full-width space → half-width; mb_convert_kana for kana width unification
     *   ABD – added: The Final, Final Season, Part N, Part.N, Cour N, : subtitle patterns
     *
     * @param string $title Raw title.
     * @return string Normalized title.
     */
    public function normalize_title( string $title ): string {
        $title = trim( $title );

        // Bug ABA fix: normalize full-width space to half-width
        $title = str_replace( '　', ' ', $title );

        // Bug ABA fix: normalize full-width alphanumerics/punctuation to half-width
        // mb_convert_kana: 'a' = ASCII, 's' = space, 'K' = katakana half→full (skip), 'H' = skip
        if ( function_exists( 'mb_convert_kana' ) ) {
            $title = mb_convert_kana( $title, 'as', 'UTF-8' );
        }

        // ── Season number patterns ─────────────────────────────────────────

        // "4th Season", "2nd Season", "1st Season", "3rd Season" (ordinal)
        $title = preg_replace( '/\s*\d+(?:st|nd|rd|th)\s+season\b/ui', '', $title );

        // "Season 3", "Season3"
        $title = preg_replace( '/\s*season\s*\d+\b/ui', '', $title );

        // "第4期", "第 4 期"
        $title = preg_replace( '/\s*第\s*\d+\s*期/u', '', $title );

        // "第4季"
        $title = preg_replace( '/\s*第\s*\d+\s*季/u', '', $title );

        // Bug ABD fix: "Part 2", "Part.2", "Part II" (Arabic only; Roman numerals skipped)
        $title = preg_replace( '/\s*[Pp]art\s*\.?\s*\d+\b/u', '', $title );

        // Bug ABD fix: "Cour 2", "Cour2"
        $title = preg_replace( '/\s*[Cc]our\s*\d+\b/u', '', $title );

        // Bug ABD fix: "The Final", "Final Season" (standalone suffix)
        $title = preg_replace( '/\s+[Tt]he\s+[Ff]inal\b/u', '', $title );
        $title = preg_replace( '/\s+[Ff]inal\s+[Ss]eason\b/u', '', $title );

        // "X年生編 / X年生篇"
        $title = preg_replace( '/\s*\d+年生[編篇]/u', '', $title );

        // "X学期 / X學期"
        $title = preg_replace( '/\s*\d+[学學]期/u', '', $title );

        // "X期制"
        $title = preg_replace( '/\s*\d+期制/u', '', $title );

        // Bug AX fix: trailing SINGLE digit only (avoids nuking "86", "91 Days" etc.)
        $title = preg_replace( '/\s+\d{1}$/u', '', $title );

        // Collapse multiple spaces to one
        $title = preg_replace( '/\s+/u', ' ', $title );

        return trim( $title );
    }

    // =========================================================================
    // PRIVATE – Cache Loaders
    // =========================================================================

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

    private function save_meta_partial( array $meta ): void {
        $path = $this->cache_dir . self::META_FILE;
        file_put_contents(
            $path,
            wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
        );
        self::$meta_cache = $meta;
    }

    // =========================================================================
    // PRIVATE – Utilities
    // =========================================================================

    private function validate_json_file( string $path ): bool {
        if ( ! file_exists( $path ) ) {
            return false;
        }
        $decoded = json_decode( file_get_contents( $path ), true );
        return is_array( $decoded ) && ! empty( $decoded );
    }

    // =========================================================================
    // PUBLIC – Helpers
    // =========================================================================

    /**
     * Get Traditional Chinese title from name_cache.json by Bangumi ID.
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

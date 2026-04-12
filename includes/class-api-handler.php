<?php
/**
 * Class Anime_Sync_API_Handler
 *
 * Fetches full anime data from AniList and Bangumi APIs,
 * resolves IDs via the ID Mapper, and returns a unified array
 * ready for the Import Manager.
 *
 * Bug fixes applied in this version:
 *   AJ – pass full $anime_data (incl. externalLinks) to get_bangumi_id()
 *   AK – normalize \r\n in clean_synopsis()
 *   AL – convert anime_title_chinese via CN_Converter before returning
 *   AR – handle null idMal safely (do not cast null to 0)
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_API_Handler {

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    const ANILIST_ENDPOINT  = 'https://graphql.anilist.co';
    const BGM_SUBJECT_URL   = 'https://api.bgm.tv/v0/subjects/';
    const BGM_PERSON_URL    = 'https://api.bgm.tv/v0/subjects/';
    const ANIMETHEMES_URL   = 'https://api.animethemes.moe/anime';

    // -------------------------------------------------------------------------
    // Dependencies
    // -------------------------------------------------------------------------

    private Anime_Sync_Rate_Limiter  $rate_limiter;
    private ?Anime_Sync_ID_Mapper    $id_mapper;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function __construct(
        ?Anime_Sync_Rate_Limiter $rate_limiter = null,
        ?Anime_Sync_ID_Mapper    $id_mapper    = null
    ) {
        $this->rate_limiter = $rate_limiter ?? new Anime_Sync_Rate_Limiter();
        $this->id_mapper    = $id_mapper    ?? new Anime_Sync_ID_Mapper();
    }

    // =========================================================================
    // PUBLIC – Main Entry Point
    // =========================================================================

    /**
     * Fetch full anime data for a given AniList ID.
     *
     * @param int      $anilist_id  AniList media ID.
     * @param int      $post_id     WP post ID (0 if not yet created).
     * @param int|null $bangumi_id  Pre-known Bangumi ID (skip lookup if set).
     * @return array|WP_Error       Unified data array or WP_Error on failure.
     */
    public function get_full_anime_data( int $anilist_id, int $post_id = 0, ?int $bangumi_id = null ) {

        // --- 1. Fetch AniList data -------------------------------------------
        $this->rate_limiter->wait_if_needed( 'anilist' );
        $anilist_raw = $this->fetch_anilist_data( $anilist_id );

        if ( is_wp_error( $anilist_raw ) ) {
            return $anilist_raw;
        }

        $media = $anilist_raw['data']['Media'] ?? null;
        if ( empty( $media ) ) {
            return new WP_Error( 'anilist_empty', "AniList returned no data for ID {$anilist_id}." );
        }

        // --- 2. Extract core fields from AniList ----------------------------
        // Bug AR: idMal may be null; keep as null, do NOT cast to 0
        $mal_id         = isset( $media['idMal'] ) && $media['idMal'] !== null
                          ? (int) $media['idMal']
                          : null;

        $title_romaji   = $media['title']['romaji']  ?? '';
        $title_english  = $media['title']['english'] ?? '';
        $title_native   = $media['title']['native']  ?? '';
        $season_year    = $media['seasonYear']        ?? 0;
        $external_links = $media['externalLinks']     ?? [];   // Bug AJ: capture

        // --- 3. Resolve Bangumi ID -------------------------------------------
        if ( ! $bangumi_id || $bangumi_id <= 0 ) {

            // Bug AJ: build full $anime_data including externalLinks + title_native
            $anime_data_for_mapper = [
                'anilist_id'     => $anilist_id,
                'mal_id'         => $mal_id,          // null-safe (Bug AR)
                'post_id'        => $post_id,
                'title_native'   => $title_native,
                'title_chinese'  => '',               // filled after Bangumi fetch
                'season_year'    => $season_year,
                'external_links' => $external_links,  // Bug AJ
            ];

            $bangumi_id = $this->id_mapper->get_bangumi_id( $anime_data_for_mapper );
        }

        // --- 4. Fetch Bangumi data -------------------------------------------
        $bgm_data  = null;
        $bgm_staff = [];
        $bgm_chars = [];

        if ( $bangumi_id && $bangumi_id > 0 ) {
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_data = $this->get_bangumi_data( $bangumi_id );

            if ( ! is_wp_error( $bgm_data ) && is_array( $bgm_data ) ) {
                $this->rate_limiter->wait_if_needed( 'bangumi' );
                $bgm_staff = $this->get_bgm_staff( $bangumi_id );

                $this->rate_limiter->wait_if_needed( 'bangumi' );
                $bgm_chars = $this->get_bgm_chars( $bangumi_id );
            } else {
                $bgm_data = null;
            }
        }

        // --- 5. Build Chinese title -----------------------------------------
        // Priority: Bangumi name_cn → name_cache → empty
        $title_chinese_raw = '';
        if ( $bgm_data ) {
            $title_chinese_raw = $bgm_data['name_cn'] ?? $bgm_data['name'] ?? '';
        }
        if ( $title_chinese_raw === '' && $bangumi_id ) {
            $cached = $this->id_mapper->get_chinese_title( $bangumi_id );
            if ( $cached ) {
                $title_chinese_raw = $cached;
            }
        }

        // Bug AL: convert to Traditional Chinese via CN_Converter
        $title_chinese = $title_chinese_raw !== ''
            ? Anime_Sync_CN_Converter::static_convert( $title_chinese_raw )
            : '';

        // --- 6. Synopsis -----------------------------------------------------
        $synopsis_chinese = '';
        $synopsis_english = '';

        if ( $bgm_data && ! empty( $bgm_data['summary'] ) ) {
            $synopsis_chinese = $this->clean_synopsis( $bgm_data['summary'] );
        }
        if ( ! empty( $media['description'] ) ) {
            $synopsis_english = $this->clean_synopsis( $media['description'] );
        }

        // --- 7. Scores -------------------------------------------------------
        // AniList: store raw 0-100
        $score_anilist = isset( $media['averageScore'] ) ? (int) $media['averageScore'] : 0;

        // Bangumi: store ×10 integer (original is 0-10 float)
        $score_bangumi = 0;
        if ( $bgm_data ) {
            $raw_rating = $bgm_data['rating']['score']
                ?? $bgm_data['score']
                ?? null;
            if ( $raw_rating !== null ) {
                $score_bangumi = (int) round( (float) $raw_rating * 10 );
            }
        }

        // --- 8. Studios ------------------------------------------------------
        $studios = [];
        if ( ! empty( $media['studios']['nodes'] ) ) {
            foreach ( $media['studios']['nodes'] as $studio ) {
                if ( ! empty( $studio['name'] ) ) {
                    $studios[] = $studio['name'];
                }
            }
        }

        // --- 9. Dates --------------------------------------------------------
        $start_date = $this->parse_fuzzy_date( $media['startDate'] ?? [] );
        $end_date   = $this->parse_fuzzy_date( $media['endDate']   ?? [] );

        // --- 10. Streaming / ExternalLinks -----------------------------------
        $streaming = $this->parse_streaming_links( $external_links );

        // --- 11. Themes (AnimeThemes) ----------------------------------------
        $themes = $this->fetch_animethemes( $mal_id );

        // --- 12. Staff / Cast / Relations ------------------------------------
        $staff     = $this->parse_staff( $media['staff']['edges']      ?? [] );
        $cast      = $this->parse_cast(  $media['characters']['edges'] ?? [] );
        $relations = $this->parse_relations( $media['relations']['edges'] ?? [] );

        // Merge Bangumi staff if available
        if ( ! empty( $bgm_staff ) && ! is_wp_error( $bgm_staff ) ) {
            $staff = array_merge( $staff, $bgm_staff );
        }
        if ( ! empty( $bgm_chars ) && ! is_wp_error( $bgm_chars ) ) {
            $cast = array_merge( $cast, $bgm_chars );
        }

        // --- 13. Trailer -----------------------------------------------------
        $trailer_url = '';
        if ( ! empty( $media['trailer'] ) ) {
            $t_id   = $media['trailer']['id']   ?? '';
            $t_site = $media['trailer']['site']  ?? '';
            if ( $t_id !== '' && strtolower( $t_site ) === 'youtube' ) {
                $trailer_url = "https://www.youtube.com/watch?v={$t_id}";
            }
        }

        // --- 14. Next Airing -------------------------------------------------
        $next_airing = null;
        if ( ! empty( $media['nextAiringEpisode'] ) ) {
            $next_airing = [
                'airingAt' => $media['nextAiringEpisode']['airingAt'] ?? 0,
                'episode'  => $media['nextAiringEpisode']['episode']  ?? 0,
            ];
        }

        // --- 15. Assemble return array --------------------------------------
        return [
            // IDs
            'anilist_id'         => $anilist_id,
            'mal_id'             => $mal_id,                    // null if unknown (Bug AR)
            'bangumi_id'         => $bangumi_id,

            // Titles
            'anime_title_chinese'=> $title_chinese,             // Bug AL: converted
            'anime_title_romaji' => $title_romaji,
            'anime_title_english'=> $title_english,
            'anime_title_native' => $title_native,

            // Classification
            'anime_format'       => $media['format']  ?? '',
            'anime_status'       => $media['status']  ?? '',
            'anime_season'       => $media['season']  ?? '',
            'anime_season_year'  => $season_year,
            'anime_source'       => $media['source']  ?? '',
            'anime_episodes'     => (int) ( $media['episodes'] ?? 0 ),
            'anime_duration'     => (int) ( $media['duration'] ?? 0 ),

            // Studios
            'anime_studios'      => implode( ', ', $studios ),

            // Scores
            'anime_score_anilist'=> $score_anilist,
            'anime_score_bangumi'=> $score_bangumi,
            'anime_popularity'   => (int) ( $media['popularity'] ?? 0 ),

            // Images
            'anime_cover_image'  => $media['coverImage']['extraLarge']
                                    ?? $media['coverImage']['large']
                                    ?? '',
            'anime_banner_image' => $media['bannerImage'] ?? '',
            'anime_trailer_url'  => $trailer_url,

            // Synopsis
            'anime_synopsis_chinese' => $synopsis_chinese,
            'anime_synopsis_english' => $synopsis_english,

            // Dates
            'anime_start_date'   => $start_date,
            'anime_end_date'     => $end_date,

            // JSON-encoded fields
            'anime_streaming'    => wp_json_encode( $streaming,  JSON_UNESCAPED_UNICODE ),
            'anime_themes'       => wp_json_encode( $themes,     JSON_UNESCAPED_UNICODE ),
            'anime_staff_json'   => wp_json_encode( $staff,      JSON_UNESCAPED_UNICODE ),
            'anime_cast_json'    => wp_json_encode( $cast,       JSON_UNESCAPED_UNICODE ),
            'anime_relations_json' => wp_json_encode( $relations, JSON_UNESCAPED_UNICODE ),

            // Misc
            'anime_external_links' => wp_json_encode( $external_links, JSON_UNESCAPED_UNICODE ),
            'anime_next_airing'    => $next_airing ? wp_json_encode( $next_airing ) : '',
            'anime_genres'         => $media['genres'] ?? [],

            // Raw Bangumi data (for additional processing downstream)
            '_bgm_raw'             => $bgm_data,
        ];
    }

    // =========================================================================
    // PRIVATE – AniList
    // =========================================================================

    /**
     * Execute a GraphQL request against AniList.
     *
     * @param int $anilist_id
     * @return array|WP_Error Decoded response array or error.
     */
    private function fetch_anilist_data( int $anilist_id ) {
        $query = '
        query ($id: Int) {
          Media(id: $id, type: ANIME) {
            id
            idMal
            title { romaji english native }
            status
            format
            episodes
            duration
            source
            season
            seasonYear
            startDate { year month day }
            endDate   { year month day }
            averageScore
            popularity
            coverImage { extraLarge large }
            bannerImage
            trailer { id site }
            description(asHtml: false)
            genres
            studios(isMain: true) { nodes { name } }
            externalLinks { url site }
            nextAiringEpisode { airingAt episode }
            relations {
              edges {
                relationType
                node { id title { romaji native } format }
              }
            }
            staff(perPage: 10) {
              edges {
                role
                node { name { full native } }
              }
            }
            characters(perPage: 10) {
              edges {
                role
                node { name { full native } }
                voiceActors(language: JAPANESE) { name { full native } }
              }
            }
          }
        }';

        $payload = wp_json_encode( [
            'query'     => $query,
            'variables' => [ 'id' => $anilist_id ],
        ] );

        $response = wp_remote_post( self::ANILIST_ENDPOINT, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => $payload,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response );
            // Retry once
            $this->rate_limiter->wait_if_needed( 'anilist' );
            $response = wp_remote_post( self::ANILIST_ENDPOINT, [
                'timeout' => 20,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'body'    => $payload,
            ] );
            if ( is_wp_error( $response ) ) {
                return $response;
            }
            $code = (int) wp_remote_retrieve_response_code( $response );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'anilist_http_error', "AniList returned HTTP {$code}." );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $decoded['data']['Media'] ) ) {
            return new WP_Error( 'anilist_no_media', "AniList: no Media in response for ID {$anilist_id}." );
        }

        return $decoded;
    }

    // =========================================================================
    // PRIVATE – Bangumi
    // =========================================================================

    private function get_bangumi_data( int $bgm_id ) {
        $url      = self::BGM_SUBJECT_URL . $bgm_id;
        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response );
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $response = wp_remote_get( $url, [
                'timeout'    => 15,
                'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
                'headers'    => [ 'Accept' => 'application/json' ],
            ] );
            if ( is_wp_error( $response ) ) {
                return $response;
            }
            $code = (int) wp_remote_retrieve_response_code( $response );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'bgm_http_error', "Bangumi returned HTTP {$code} for subject {$bgm_id}." );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $decoded ) ? $decoded : new WP_Error( 'bgm_parse_error', 'Failed to parse Bangumi response.' );
    }

    // -------------------------------------------------------------------------

    private function get_bgm_staff( int $bgm_id ): array {
        $url      = self::BGM_SUBJECT_URL . $bgm_id . '/persons';
        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $result = [];

        if ( ! is_array( $data ) ) {
            return [];
        }

        foreach ( $data as $person ) {
            $name = $person['name'] ?? '';
            foreach ( $person['jobs'] ?? [] as $job ) {
                $result[] = [
                    'name' => $name,
                    'role' => $job,
                    'type' => 'staff',
                ];
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------

    private function get_bgm_chars( int $bgm_id ): array {
        $url      = self::BGM_SUBJECT_URL . $bgm_id . '/characters';
        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $result = [];

        if ( ! is_array( $data ) ) {
            return [];
        }

        foreach ( $data as $char ) {
            $actors = [];
            foreach ( $char['actors'] ?? [] as $actor ) {
                $actors[] = $actor['name'] ?? '';
            }
            $result[] = [
                'name'   => $char['name']     ?? '',
                'role'   => $char['relation'] ?? '',
                'actors' => $actors,
                'type'   => 'character',
            ];
        }

        return $result;
    }

    // =========================================================================
    // PRIVATE – AnimeThemes
    // =========================================================================

    private function fetch_animethemes( ?int $mal_id ): array {
        // Bug AQ: AnimeThemes uses MAL ID; skip if null (Bug AR safety)
        if ( ! $mal_id || $mal_id <= 0 ) {
            return [];
        }

        $this->rate_limiter->wait_if_needed( 'animethemes' );

        $url = add_query_arg( [
            'filter[has]'         => 'resources',
            'filter[site]'        => 'MyAnimeList',
            'filter[external_id]' => $mal_id,
            'include'             => 'animethemes.song,resources',
            'page[number]'        => 1,
        ], self::ANIMETHEMES_URL );

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $data  = json_decode( wp_remote_retrieve_body( $response ), true );
        $anime = $data['anime'][0] ?? null;

        if ( ! $anime || empty( $anime['animethemes'] ) ) {
            return [];
        }

        $themes = [];
        foreach ( $anime['animethemes'] as $theme ) {
            $themes[] = [
                'type'  => $theme['type']       ?? '',
                'title' => $theme['song']['title'] ?? '',
            ];
        }

        return $themes;
    }

    // =========================================================================
    // PRIVATE – Parsers
    // =========================================================================

    /**
     * Convert AniList fuzzy date to Y-m-d string.
     */
    private function parse_fuzzy_date( array $date ): string {
        $y = $date['year']  ?? 0;
        $m = $date['month'] ?? 0;
        $d = $date['day']   ?? 0;

        if ( ! $y ) {
            return '';
        }
        return sprintf( '%04d-%02d-%02d', $y, $m ?: 1, $d ?: 1 );
    }

    // -------------------------------------------------------------------------

    /**
     * Bug AK: Clean synopsis text.
     * - Normalize \r\n and \r to \n first (Bug AK).
     * - Remove spoiler tags.
     * - Remove "(Source: ...)" notes.
     * - Strip HTML tags.
     * - Collapse excessive blank lines.
     */
    private function clean_synopsis( string $text ): string {
        // Bug AK: normalize line endings first
        $text = str_replace( [ "\r\n", "\r" ], "\n", $text );

        // Remove spoiler blocks
        $text = preg_replace( '/<spoiler>.*?<\/spoiler>/si', '', $text );
        $text = preg_replace( '/\(Source:.*?\)/si', '', $text );

        // Strip HTML
        $text = wp_strip_all_tags( $text );

        // Collapse 3+ consecutive newlines to 2
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }

    // -------------------------------------------------------------------------

    /**
     * Extract streaming platform links from AniList externalLinks.
     */
    private function parse_streaming_links( array $external_links ): array {
        // Known streaming site identifiers
        $streaming_sites = [
            'crunchyroll', 'funimation', 'netflix', 'amazon',
            'hidive', 'hulu', 'disney plus', 'bilibili',
            'youtube', 'wakanim', 'ani-one', 'iqiyi',
        ];

        $streaming = [];
        foreach ( $external_links as $link ) {
            if ( ! is_array( $link ) ) {
                continue;
            }
            $site = strtolower( $link['site'] ?? '' );
            $url  = $link['url']  ?? '';
            if ( $url === '' || $site === '' ) {
                continue;
            }
            foreach ( $streaming_sites as $keyword ) {
                if ( strpos( $site, $keyword ) !== false ) {
                    $streaming[] = [
                        'site' => $link['site'],
                        'url'  => $url,
                    ];
                    break;
                }
            }
        }

        return $streaming;
    }

    // -------------------------------------------------------------------------

    private function parse_staff( array $edges ): array {
        $result = [];
        foreach ( $edges as $edge ) {
            $result[] = [
                'name'        => $edge['node']['name']['full']   ?? '',
                'name_native' => $edge['node']['name']['native'] ?? '',
                'role'        => $edge['role'] ?? '',
            ];
        }
        return $result;
    }

    // -------------------------------------------------------------------------

    private function parse_cast( array $edges ): array {
        $result = [];
        foreach ( $edges as $edge ) {
            $va = $edge['voiceActors'][0] ?? [];
            $result[] = [
                'character'       => $edge['node']['name']['full']   ?? '',
                'character_native'=> $edge['node']['name']['native'] ?? '',
                'role'            => $edge['role'] ?? '',
                'voice_actor'     => $va['name']['full']   ?? '',
                'va_native'       => $va['name']['native'] ?? '',
            ];
        }
        return $result;
    }

    // -------------------------------------------------------------------------

    private function parse_relations( array $edges ): array {
        $type_map = [
            'PREQUEL'        => '前作',
            'SEQUEL'         => '續作',
            'PARENT'         => '原作',
            'SIDE_STORY'     => '外傳',
            'CHARACTER'      => '角色出演',
            'SUMMARY'        => '總集篇',
            'ALTERNATIVE'    => '替代版本',
            'SPIN_OFF'       => '衍生作',
            'OTHER'          => '其他',
            'SOURCE'         => '原著',
            'COMPILATION'    => '彙整版',
            'CONTAINS'       => '包含',
            'ADAPTATION'     => '改編',
        ];

        $order = [ '前作', '續作', '原作', '外傳', '角色出演', '總集篇', '替代版本', '衍生作', '改編', '其他' ];

        $result = [];
        foreach ( $edges as $edge ) {
            $type_raw = $edge['relationType'] ?? 'OTHER';
            $label    = $type_map[ $type_raw ] ?? '其他';
            $result[] = [
                'id'     => $edge['node']['id']                     ?? 0,
                'title'  => $edge['node']['title']['romaji']        ?? '',
                'native' => $edge['node']['title']['native']        ?? '',
                'format' => $edge['node']['format']                 ?? '',
                'type'   => $label,
            ];
        }

        // Sort by defined order
        usort( $result, function ( $a, $b ) use ( $order ) {
            $ai = array_search( $a['type'], $order );
            $bi = array_search( $b['type'], $order );
            $ai = $ai === false ? 99 : $ai;
            $bi = $bi === false ? 99 : $bi;
            return $ai <=> $bi;
        } );

        return $result;
    }
}

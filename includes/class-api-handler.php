<?php
/**
 * Class Anime_Sync_API_Handler
 *
 * Bug fixes in this version:
 *   AJ  – pass full $anime_data (incl. externalLinks) to get_bangumi_id()
 *   AK  – normalize \r\n in clean_synopsis()
 *   AL  – convert synopsis + title via CN_Converter
 *   AR  – handle null idMal safely
 *   ABF – add fetch_mal_score() via Jikan API
 *   ABG – add parse_external_links() for official_site / twitter_url
 *   ABH – add tags { name isMediaSpoiler } to GraphQL query
 *   ABI – truncate [简介原文] in clean_synopsis()
 *   ABJ – add fetch_wikipedia_url() via Wikipedia API
 *   ABK – parse_relations() returns title_chinese / relation_label / cover_image / anilist_id
 *   AX  – fetch_animethemes() 改用字串組 URL，避免 add_query_arg() 編碼方括號
 *         同時回傳 slug 供 import_manager 寫入 anime_animethemes_id
 *   ABA – parse_streaming_links() 加入 type === STREAMING 判斷 + 白名單擴充
 *   ABL – fetch_bgm_episodes() 新增，抓取 Bangumi 集數列表
 *   ABQ – fetch_wikipedia_url() 新增 English Wikipedia REST API fallback；
 *         中文維基找不到時改以 title_english / title_romaji 查英文維基，
 *         並將結果快取，避免重複 API 呼叫
 *   ABR – parse_relations() GraphQL query 補上 coverImage { large }，
 *         並將 cover_image 由空字串改為讀取 $edge['node']['coverImage']['large']，
 *         解決相關作品 / 系列作品圖片全部顯示佔位符的問題
 *   ABZ – get_full_anime_data() 傳入 season / episodes / title_romaji 給 id_mapper，
 *         供 Layer 1.5–1.8、季度篩選、集數驗證使用
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
    const BGM_EPISODES_URL  = 'https://api.bgm.tv/v0/episodes';
    const ANIMETHEMES_URL   = 'https://api.animethemes.moe/anime';
    const JIKAN_ANIME_URL   = 'https://api.jikan.moe/v4/anime/';
    const WIKI_ZH_API       = 'https://zh.wikipedia.org/w/api.php';
    const WIKI_EN_REST      = 'https://en.wikipedia.org/api/rest_v1/page/summary/'; // ABQ

    // -------------------------------------------------------------------------
    // Dependencies
    // -------------------------------------------------------------------------

    private Anime_Sync_Rate_Limiter $rate_limiter;
    private ?Anime_Sync_ID_Mapper   $id_mapper;

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

    public function get_full_anime_data( int $anilist_id, int $post_id = 0, ?int $bangumi_id = null ): array|WP_Error {

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

        // --- 2. Extract core fields -----------------------------------------
        $mal_id         = isset( $media['idMal'] ) && $media['idMal'] !== null
                          ? (int) $media['idMal']
                          : null;

        $title_romaji   = $media['title']['romaji']  ?? '';
        $title_english  = $media['title']['english'] ?? '';
        $title_native   = $media['title']['native']  ?? '';
        $season_year    = $media['seasonYear']        ?? 0;
        $season         = $media['season']            ?? '';   // ABZ：SPRING/SUMMER/FALL/WINTER
        $episodes       = (int) ( $media['episodes'] ?? 0 );  // ABZ：集數
        $external_links = $media['externalLinks']     ?? [];

        // --- 3. Resolve Bangumi ID ------------------------------------------
        if ( ! $bangumi_id || $bangumi_id <= 0 ) {
            $anime_data_for_mapper = [
                'anilist_id'     => $anilist_id,
                'mal_id'         => $mal_id,
                'post_id'        => $post_id,
                'title_native'   => $title_native,
                'title_romaji'   => $title_romaji,   // ABZ
                'title_chinese'  => '',
                'season_year'    => $season_year,
                'season'         => $season,          // ABZ：季度字串
                'episodes'       => $episodes,        // ABZ：集數
                'external_links' => $external_links,
            ];
            $bangumi_id = $this->id_mapper->get_bangumi_id( $anime_data_for_mapper );
        }

        // --- 4. Fetch Bangumi data ------------------------------------------
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

        $title_chinese = $title_chinese_raw !== ''
            ? Anime_Sync_CN_Converter::static_convert( $title_chinese_raw )
            : '';

        // --- 6. Synopsis -----------------------------------------------------
        $synopsis_chinese = '';
        $synopsis_english = '';

        if ( $bgm_data && ! empty( $bgm_data['summary'] ) ) {
            $synopsis_chinese = $this->clean_synopsis( $bgm_data['summary'] );
            if ( $synopsis_chinese !== '' ) {
                $synopsis_chinese = Anime_Sync_CN_Converter::static_convert( $synopsis_chinese );
            }
        }
        if ( ! empty( $media['description'] ) ) {
            $synopsis_english = $this->clean_synopsis( $media['description'] );
        }

        // --- 7. Scores ------------------------------------------------------
        $score_anilist = isset( $media['averageScore'] ) ? (int) $media['averageScore'] : 0;

        $score_bangumi = 0;
        if ( $bgm_data ) {
            $raw_rating = $bgm_data['rating']['score'] ?? $bgm_data['score'] ?? null;
            if ( $raw_rating !== null ) {
                $score_bangumi = (int) round( (float) $raw_rating * 10 );
            }
        }

        $score_mal = $this->fetch_mal_score( $mal_id );

        // --- 8. Studios -----------------------------------------------------
        $studios = [];
        if ( ! empty( $media['studios']['nodes'] ) ) {
            foreach ( $media['studios']['nodes'] as $studio ) {
                if ( ! empty( $studio['name'] ) ) {
                    $studios[] = $studio['name'];
                }
            }
        }

        // --- 9. Dates -------------------------------------------------------
        $start_date = $this->parse_fuzzy_date( $media['startDate'] ?? [] );
        $end_date   = $this->parse_fuzzy_date( $media['endDate']   ?? [] );

        // --- 10. Streaming + External Links ---------------------------------
        $streaming    = $this->parse_streaming_links( $external_links );
        $parsed_links = $this->parse_external_links( $external_links );

        // --- 11. Wikipedia URL (ABJ + ABQ fallback) -------------------------
        $wikipedia_url = $this->fetch_wikipedia_url(
            $title_chinese,
            $title_native,
            $title_romaji,
            $title_english
        );

        // --- 12. Themes (AnimeThemes) – AX 修正 -----------------------------
        $animethemes_result  = $this->fetch_animethemes( $mal_id );
        $themes              = $animethemes_result['themes'] ?? [];
        $animethemes_slug    = $animethemes_result['slug']   ?? '';

        // --- 13. Bangumi 集數列表 (ABL) --------------------------------------
        $episodes_json = '[]';
        if ( $bangumi_id && $bangumi_id > 0 ) {
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_episodes  = $this->fetch_bgm_episodes( $bangumi_id );
            $episodes_json = wp_json_encode( $bgm_episodes, JSON_UNESCAPED_UNICODE );
        }

        // --- 14. Staff / Cast / Relations -----------------------------------
        $staff     = $this->parse_staff( $media['staff']['edges']         ?? [] );
        $cast      = $this->parse_cast(  $media['characters']['edges']    ?? [] );
        $relations = $this->parse_relations( $media['relations']['edges'] ?? [] );

        if ( ! empty( $bgm_staff ) && ! is_wp_error( $bgm_staff ) ) {
            $staff = array_merge( $staff, $bgm_staff );
        }
        if ( ! empty( $bgm_chars ) && ! is_wp_error( $bgm_chars ) ) {
            $cast = array_merge( $cast, $bgm_chars );
        }

        // --- 15. Trailer ----------------------------------------------------
        $trailer_url = '';
        if ( ! empty( $media['trailer'] ) ) {
            $t_id   = $media['trailer']['id']  ?? '';
            $t_site = $media['trailer']['site'] ?? '';
            if ( $t_id !== '' && strtolower( $t_site ) === 'youtube' ) {
                $trailer_url = "https://www.youtube.com/watch?v={$t_id}";
            }
        }

        // --- 16. Next Airing ------------------------------------------------
        $next_airing = null;
        if ( ! empty( $media['nextAiringEpisode'] ) ) {
            $next_airing = [
                'airingAt' => $media['nextAiringEpisode']['airingAt'] ?? 0,
                'episode'  => $media['nextAiringEpisode']['episode']  ?? 0,
            ];
        }

        // --- 17. Tags (ABH) -------------------------------------------------
        $anime_tags = [];
        foreach ( $media['tags'] ?? [] as $tag ) {
            if ( ! empty( $tag['isMediaSpoiler'] ) ) {
                continue;
            }
            if ( ! empty( $tag['name'] ) ) {
                $anime_tags[] = $tag['name'];
            }
        }

        // --- 18. Assemble return array --------------------------------------
        return [
            // IDs
            'anilist_id'             => $anilist_id,
            'mal_id'                 => $mal_id,
            'bangumi_id'             => $bangumi_id,
            'animethemes_slug'       => $animethemes_slug,

            // Titles
            'anime_title_chinese'    => $title_chinese,
            'anime_title_romaji'     => $title_romaji,
            'anime_title_english'    => $title_english,
            'anime_title_native'     => $title_native,

            // Classification
            'anime_format'           => $media['format'] ?? '',
            'anime_status'           => $media['status'] ?? '',
            'anime_season'           => $media['season'] ?? '',
            'anime_season_year'      => $season_year,
            'anime_source'           => $media['source'] ?? '',
            'anime_episodes'         => $episodes,
            'anime_duration'         => (int) ( $media['duration'] ?? 0 ),

            // Studios
            'anime_studios'          => implode( ', ', $studios ),

            // Scores
            'anime_score_anilist'    => $score_anilist,
            'anime_score_bangumi'    => $score_bangumi,
            'anime_score_mal'        => $score_mal,
            'anime_popularity'       => (int) ( $media['popularity'] ?? 0 ),

            // Images
            'anime_cover_image'      => $media['coverImage']['extraLarge']
                                        ?? $media['coverImage']['large']
                                        ?? '',
            'anime_banner_image'     => $media['bannerImage'] ?? '',
            'anime_trailer_url'      => $trailer_url,

            // Synopsis
            'anime_synopsis_chinese' => $synopsis_chinese,
            'anime_synopsis_english' => $synopsis_english,

            // Dates
            'anime_start_date'       => $start_date,
            'anime_end_date'         => $end_date,

            // JSON-encoded fields
            'anime_streaming'        => wp_json_encode( $streaming,  JSON_UNESCAPED_UNICODE ),
            'anime_themes'           => wp_json_encode( $themes,     JSON_UNESCAPED_UNICODE ),
            'anime_staff_json'       => wp_json_encode( $staff,      JSON_UNESCAPED_UNICODE ),
            'anime_cast_json'        => wp_json_encode( $cast,       JSON_UNESCAPED_UNICODE ),
            'anime_relations_json'   => wp_json_encode( $relations,  JSON_UNESCAPED_UNICODE ),
            'anime_episodes_json'    => $episodes_json,

            // External links
            'anime_official_site'    => $parsed_links['official_site'] ?? '',
            'anime_twitter_url'      => $parsed_links['twitter_url']   ?? '',
            'anime_wikipedia_url'    => $wikipedia_url,

            // Misc
            'anime_external_links'   => wp_json_encode( $external_links, JSON_UNESCAPED_UNICODE ),
            'anime_next_airing'      => $next_airing ? wp_json_encode( $next_airing ) : '',
            'anime_genres'           => $media['genres'] ?? [],
            'anime_tags'             => $anime_tags,

            // Raw Bangumi
            '_bgm_raw'               => $bgm_data,
        ];
    }

    // =========================================================================
    // PRIVATE – AniList
    // =========================================================================

    private function fetch_anilist_data( int $anilist_id ): array|WP_Error {
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
            tags { name isMediaSpoiler }
            studios(isMain: true) { nodes { name } }
            externalLinks { url site type }
            nextAiringEpisode { airingAt episode }
            relations {
              edges {
                relationType
                node { id title { romaji native } format coverImage { large } }
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

        $args = [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => $payload,
        ];

        $response = wp_remote_post( self::ANILIST_ENDPOINT, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response );
            $this->rate_limiter->wait_if_needed( 'anilist' );
            $response = wp_remote_post( self::ANILIST_ENDPOINT, $args );
            if ( is_wp_error( $response ) ) return $response;
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
    // PRIVATE – MAL Score via Jikan
    // =========================================================================

    private function fetch_mal_score( ?int $mal_id ): float {
        if ( ! $mal_id || $mal_id <= 0 ) return 0.0;

        $cache_key = 'anime_sync_mal_score_' . $mal_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (float) $cached;

        $this->rate_limiter->wait_if_needed( 'jikan' );

        $response = wp_remote_get( self::JIKAN_ANIME_URL . $mal_id, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) return 0.0;

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code === 429 ) {
            sleep( 2 );
            $response = wp_remote_get( self::JIKAN_ANIME_URL . $mal_id, [
                'timeout'    => 15,
                'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
                'headers'    => [ 'Accept' => 'application/json' ],
            ] );
            if ( is_wp_error( $response ) ) return 0.0;
            $code = (int) wp_remote_retrieve_response_code( $response );
        }

        if ( $code !== 200 ) return 0.0;

        $data  = json_decode( wp_remote_retrieve_body( $response ), true );
        $score = $data['data']['score'] ?? null;
        if ( $score === null ) return 0.0;

        $score_float = (float) $score;
        set_transient( $cache_key, $score_float, 12 * HOUR_IN_SECONDS );

        return $score_float;
    }

    // =========================================================================
    // PRIVATE – Wikipedia URL (ABJ + ABQ fallback)
    // =========================================================================

    private function fetch_wikipedia_url(
        string $title_chinese,
        string $title_native,
        string $title_romaji,
        string $title_english = ''
    ): string {

        $zh_candidates = array_filter( [ $title_chinese, $title_native, $title_romaji ] );

        foreach ( $zh_candidates as $title ) {
            $cache_key = 'anime_sync_wiki_zh_' . md5( $title );
            $cached    = get_transient( $cache_key );

            if ( $cached !== false ) {
                if ( $cached !== '' ) return (string) $cached;
                continue;
            }

            $url = add_query_arg( [
                'action'        => 'query',
                'titles'        => $title,
                'format'        => 'json',
                'prop'          => 'info',
                'inprop'        => 'url',
                'redirects'     => '1',
                'formatversion' => '2',
            ], self::WIKI_ZH_API );

            $response = wp_remote_get( $url, [
                'timeout'    => 10,
                'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
                'headers'    => [ 'Accept' => 'application/json' ],
            ] );

            if ( is_wp_error( $response ) ) continue;
            if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) continue;

            $data  = json_decode( wp_remote_retrieve_body( $response ), true );
            $pages = $data['query']['pages'] ?? [];

            foreach ( $pages as $page ) {
                if ( ! empty( $page['missing'] ) ) {
                    set_transient( $cache_key, '', 7 * DAY_IN_SECONDS );
                    continue 2;
                }

                $wiki_url = $page['fullurl'] ?? '';
                if ( $wiki_url === '' ) continue;

                set_transient( $cache_key, $wiki_url, 30 * DAY_IN_SECONDS );
                return $wiki_url;
            }

            set_transient( $cache_key, '', 7 * DAY_IN_SECONDS );
        }

        $en_candidates = array_filter( [ $title_english, $title_romaji ] );

        foreach ( $en_candidates as $title ) {
            $cache_key = 'anime_sync_wiki_en_' . md5( $title );
            $cached    = get_transient( $cache_key );

            if ( $cached !== false ) {
                if ( $cached !== '' ) return (string) $cached;
                continue;
            }

            $title_slug = str_replace( ' ', '_', trim( $title ) );
            $rest_url   = self::WIKI_EN_REST . rawurlencode( $title_slug );

            $response = wp_remote_get( $rest_url, [
                'timeout'    => 10,
                'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
                'headers'    => [ 'Accept' => 'application/json' ],
            ] );

            if ( is_wp_error( $response ) ) continue;

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( $code === 404 ) {
                set_transient( $cache_key, '', 7 * DAY_IN_SECONDS );
                continue;
            }

            if ( $code !== 200 ) continue;

            $body     = json_decode( wp_remote_retrieve_body( $response ), true );
            $wiki_url = $body['content_urls']['desktop']['page'] ?? '';

            if ( ( $body['type'] ?? '' ) === 'disambiguation' ) {
                set_transient( $cache_key, '', 7 * DAY_IN_SECONDS );
                continue;
            }

            if ( $wiki_url === '' ) {
                set_transient( $cache_key, '', 7 * DAY_IN_SECONDS );
                continue;
            }

            set_transient( $cache_key, $wiki_url, 30 * DAY_IN_SECONDS );
            return $wiki_url;
        }

        return '';
    }

    // =========================================================================
    // PRIVATE – Bangumi Subject
    // =========================================================================

    private function get_bangumi_data( int $bgm_id ): array|WP_Error {
        $url      = self::BGM_SUBJECT_URL . $bgm_id;
        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response );
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $response = wp_remote_get( $url, [
                'timeout'    => 15,
                'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
                'headers'    => [ 'Accept' => 'application/json' ],
            ] );
            if ( is_wp_error( $response ) ) return $response;
            $code = (int) wp_remote_retrieve_response_code( $response );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'bgm_http_error', "Bangumi returned HTTP {$code} for subject {$bgm_id}." );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $decoded ) ? $decoded : new WP_Error( 'bgm_parse_error', 'Failed to parse Bangumi response.' );
    }

    private function get_bgm_staff( int $bgm_id ): array {
        $url      = self::BGM_SUBJECT_URL . $bgm_id . '/persons';
        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $result = [];
        if ( ! is_array( $data ) ) return [];

        foreach ( $data as $person ) {
            $name = $person['name'] ?? '';
            foreach ( $person['jobs'] ?? [] as $job ) {
                $result[] = [ 'name' => $name, 'role' => $job, 'type' => 'staff' ];
            }
        }
        return $result;
    }

    private function get_bgm_chars( int $bgm_id ): array {
        $url      = self::BGM_SUBJECT_URL . $bgm_id . '/characters';
        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        $result = [];
        if ( ! is_array( $data ) ) return [];

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
    // PRIVATE – Bangumi 集數列表 (ABL)
    // =========================================================================

    public function fetch_bgm_episodes( int $bgm_id ): array {
        $cache_key = 'anime_sync_bgm_eps_' . $bgm_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (array) $cached;

        $url = add_query_arg( [
            'subject_id' => $bgm_id,
            'type'       => 0,
            'limit'      => 200,
            'offset'     => 0,
        ], self::BGM_EPISODES_URL );

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $list = $data['data'] ?? [];
        if ( ! is_array( $list ) ) return [];

        $result = [];
        foreach ( $list as $ep ) {
            $result[] = [
                'ep'      => (int) ( $ep['ep']      ?? 0 ),
                'name'    => $ep['name']             ?? '',
                'name_cn' => $ep['name_cn']          ?? '',
                'airdate' => $ep['airdate']           ?? '',
                'comment' => (int) ( $ep['comment']  ?? 0 ),
            ];
        }

        set_transient( $cache_key, $result, 24 * HOUR_IN_SECONDS );
        return $result;
    }

    // =========================================================================
    // PRIVATE – AnimeThemes (AX)
    // =========================================================================

    private function fetch_animethemes( ?int $mal_id ): array {
        $empty = [ 'themes' => [], 'slug' => '' ];
        if ( ! $mal_id || $mal_id <= 0 ) return $empty;

        $this->rate_limiter->wait_if_needed( 'animethemes' );

        $url = self::ANIMETHEMES_URL
            . '?filter[has]=resources'
            . '&filter[site]=MyAnimeList'
            . '&filter[external_id]=' . (int) $mal_id
            . '&include=animethemes.song,resources'
            . '&page[number]=1';

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return $empty;
        }

        $data  = json_decode( wp_remote_retrieve_body( $response ), true );
        $anime = $data['anime'][0] ?? null;
        if ( ! $anime || empty( $anime['animethemes'] ) ) return $empty;

        $slug   = $anime['slug'] ?? '';
        $themes = [];
        foreach ( $anime['animethemes'] as $theme ) {
            $theme_type     = $theme['type']     ?? '';
            $theme_sequence = $theme['sequence'] ?? '';
            $theme_slug     = $theme_type . ( $theme_sequence ? $theme_sequence : '' );
            $themes[] = [
                'type'       => $theme_type,
                'title'      => $theme['song']['title'] ?? '',
                'theme_slug' => $theme_slug,
                'anime_slug' => $slug,
            ];
        }

        return [ 'themes' => $themes, 'slug' => $slug ];
    }

    // =========================================================================
    // PRIVATE – Parsers
    // =========================================================================

    private function parse_fuzzy_date( array $date ): string {
        $y = $date['year']  ?? 0;
        $m = $date['month'] ?? 0;
        $d = $date['day']   ?? 0;
        if ( ! $y ) return '';
        return sprintf( '%04d-%02d-%02d', $y, $m ?: 1, $d ?: 1 );
    }

    private function clean_synopsis( string $text ): string {
        $text = str_replace( [ "\r\n", "\r" ], "\n", $text );
        $text = preg_replace( '/[\[【]简介原文[\]】].*/su', '', $text );
        $text = preg_replace( '/<spoiler>.*?<\/spoiler>/si', '', $text );
        $text = preg_replace( '/\(Source:.*?\)/si', '', $text );
        $text = wp_strip_all_tags( $text );
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );
        return trim( $text );
    }

    private function parse_streaming_links( array $external_links ): array {
        $name_whitelist = [
            'crunchyroll', 'funimation', 'netflix', 'amazon', 'prime video',
            'hidive', 'hulu', 'disney', 'bilibili', 'youtube',
            'wakanim', 'ani-one', 'iqiyi', 'wetv', 'viu',
            'bahamut', '巴哈姆特', 'linewebtoon', 'myanimelist',
            'medialink', 'muse', 'kktv', 'friday', 'catchplay',
        ];

        $streaming = [];
        $seen_urls = [];

        foreach ( $external_links as $link ) {
            if ( ! is_array( $link ) ) continue;

            $site = $link['site'] ?? '';
            $url  = $link['url']  ?? '';
            $type = strtoupper( $link['type'] ?? '' );

            if ( $url === '' || $site === '' ) continue;
            if ( isset( $seen_urls[ $url ] ) ) continue;

            $site_lower        = strtolower( $site );
            $is_streaming_type = ( $type === 'STREAMING' );
            $is_whitelisted    = false;

            foreach ( $name_whitelist as $keyword ) {
                if ( strpos( $site_lower, $keyword ) !== false ) {
                    $is_whitelisted = true;
                    break;
                }
            }

            if ( $is_streaming_type || $is_whitelisted ) {
                $streaming[]       = [ 'site' => $site, 'url' => $url ];
                $seen_urls[ $url ] = true;
            }
        }

        return $streaming;
    }

    private function parse_external_links( array $external_links ): array {
        $result = [ 'official_site' => '', 'twitter_url' => '' ];

        foreach ( $external_links as $link ) {
            if ( ! is_array( $link ) ) continue;
            $site = strtolower( $link['site'] ?? '' );
            $url  = $link['url'] ?? '';
            if ( $url === '' ) continue;

            if ( $result['official_site'] === '' &&
                 ( strpos( $site, 'official' ) !== false ||
                   $site === 'website' || $site === 'homepage' ) ) {
                $result['official_site'] = $url;
            }

            if ( $result['twitter_url'] === '' &&
                 ( strpos( $site, 'twitter' ) !== false ||
                   strpos( $site, ' x ' ) !== false ||
                   $site === 'x' ) ) {
                $result['twitter_url'] = $url;
            }
        }

        return $result;
    }

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

    private function parse_cast( array $edges ): array {
        $result = [];
        foreach ( $edges as $edge ) {
            $va = $edge['voiceActors'][0] ?? [];
            $result[] = [
                'character'        => $edge['node']['name']['full']   ?? '',
                'character_native' => $edge['node']['name']['native'] ?? '',
                'role'             => $edge['role'] ?? '',
                'voice_actor'      => $va['name']['full']   ?? '',
                'va_native'        => $va['name']['native'] ?? '',
            ];
        }
        return $result;
    }

    private function parse_relations( array $edges ): array {
        $type_map = [
            'PREQUEL'     => '前作',    'SEQUEL'      => '續作',
            'PARENT'      => '原作',    'SIDE_STORY'  => '外傳',
            'CHARACTER'   => '角色出演', 'SUMMARY'    => '總集篇',
            'ALTERNATIVE' => '替代版本', 'SPIN_OFF'   => '衍生作',
            'OTHER'       => '其他',    'SOURCE'      => '原著',
            'COMPILATION' => '彙整版',  'CONTAINS'    => '包含',
            'ADAPTATION'  => '改編',
        ];

        $result = [];
        foreach ( $edges as $edge ) {
            $type_raw     = $edge['relationType'] ?? 'OTHER';
            $label        = $type_map[ $type_raw ] ?? '其他';
            $title_romaji = $edge['node']['title']['romaji'] ?? '';
            $title_native = $edge['node']['title']['native'] ?? '';

            $result[] = [
                'id'             => $edge['node']['id'] ?? 0,
                'title'          => $title_romaji,
                'native'         => $title_native,
                'format'         => $edge['node']['format'] ?? '',
                'type'           => $label,
                'anilist_id'     => $edge['node']['id'] ?? 0,
                'title_chinese'  => '',
                'title_romaji'   => $title_romaji,
                'relation_label' => $label,
                'cover_image'    => $edge['node']['coverImage']['large'] ?? '',
            ];
        }
        return $result;
    }
}

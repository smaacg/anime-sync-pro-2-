<?php
/**
 * 檔案名稱: includes/class-api-handler.php
 *
 * ACB – get_core_anime_data()：只打 AniList + Bangumi subject，目標 < 15 秒
 *       fetch_wikipedia_url() timeout 5s；fetch_animethemes() timeout 8s
 * ACD – get_series_tree()、fetch_anilist_popularity()、SERIES_RELATION_TYPES
 * ACE – expand_series_tree() 補入 relation_type 記錄
 *       fetch_anilist_popularity() query 補入 episodes 欄位
 *       find_existing_post() 確保正確實作
 * ACF – fetch_animethemes() include 加入 videos.audio，audio_url 存入 themes
 *       enrich_anime_data() Staff/Cast 改為 Bangumi 直接取代（不合併）
 *       get_full_anime_data() Staff/Cast 改為 Bangumi 優先取代
 * ACG – 新增 USER_AGENT 常數，統一所有 Bangumi / Jikan / AnimeThemes 請求
 *       新增 ajax_resync_bangumi()：強制覆蓋 Bangumi 資料至指定文章
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_API_Handler {

    const ANILIST_ENDPOINT  = 'https://graphql.anilist.co';
    const BGM_SUBJECT_URL   = 'https://api.bgm.tv/v0/subjects/';
    const BGM_EPISODES_URL  = 'https://api.bgm.tv/v0/episodes';
    const ANIMETHEMES_URL   = 'https://api.animethemes.moe/anime';
    const JIKAN_ANIME_URL   = 'https://api.jikan.moe/v4/anime/';
    const WIKI_ZH_API       = 'https://zh.wikipedia.org/w/api.php';
    const WIKI_EN_REST      = 'https://en.wikipedia.org/api/rest_v1/page/summary/';

    // ACG 新增：統一 User-Agent 常數
    const USER_AGENT = 'SmaACG-Project/1.0 (https://weixiaoacg.com)';

    const SERIES_RELATION_TYPES = [
        'PREQUEL',
        'SEQUEL',
        'SIDE_STORY',
        'SPIN_OFF',
        'ALTERNATIVE',
        'PARENT',
    ];

    private Anime_Sync_Rate_Limiter $rate_limiter;
    private ?Anime_Sync_ID_Mapper   $id_mapper;

    public function __construct(
        ?Anime_Sync_Rate_Limiter $rate_limiter = null,
        ?Anime_Sync_ID_Mapper    $id_mapper    = null
    ) {
        $this->rate_limiter = $rate_limiter ?? new Anime_Sync_Rate_Limiter();
        $this->id_mapper    = $id_mapper    ?? new Anime_Sync_ID_Mapper();
    }

    // =========================================================================
    // PUBLIC – 核心匯入（ACB）目標 < 15 秒
    // =========================================================================

    public function get_core_anime_data( int $anilist_id, int $post_id = 0, ?int $bangumi_id = null ): array|WP_Error {

        $anilist_raw = $this->fetch_anilist_data( $anilist_id );
        if ( is_wp_error( $anilist_raw ) ) return $anilist_raw;

        $media = $anilist_raw['data']['Media'] ?? null;
        if ( empty( $media ) ) {
            return new WP_Error( 'anilist_empty', "AniList returned no data for ID {$anilist_id}." );
        }

        $mal_id         = isset( $media['idMal'] ) && $media['idMal'] !== null ? (int) $media['idMal'] : null;
        $title_romaji   = $media['title']['romaji']  ?? '';
        $title_english  = $media['title']['english'] ?? '';
        $title_native   = $media['title']['native']  ?? '';
        $season_year    = $media['seasonYear']        ?? 0;
        $season         = $media['season']            ?? '';
        $episodes       = (int) ( $media['episodes'] ?? 0 );
        $external_links = $media['externalLinks']     ?? [];

        if ( ! $bangumi_id || $bangumi_id <= 0 ) {
            $bangumi_id = $this->id_mapper->get_bangumi_id( [
                'anilist_id'     => $anilist_id,
                'mal_id'         => $mal_id,
                'post_id'        => $post_id,
                'title_native'   => $title_native,
                'title_romaji'   => $title_romaji,
                'title_chinese'  => '',
                'season_year'    => $season_year,
                'season'         => $season,
                'episodes'       => $episodes,
                'external_links' => $external_links,
            ] );
        }

        $bgm_data = null;
        if ( $bangumi_id && $bangumi_id > 0 ) {
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $result = $this->get_bangumi_data( $bangumi_id );
            if ( ! is_wp_error( $result ) && is_array( $result ) ) {
                $bgm_data = $result;
            }
        }

        $title_chinese_raw = '';
        if ( $bgm_data ) {
            $title_chinese_raw = $bgm_data['name_cn'] ?? $bgm_data['name'] ?? '';
        }
        if ( $title_chinese_raw === '' && $bangumi_id ) {
            $cached = $this->id_mapper->get_chinese_title( $bangumi_id );
            if ( $cached ) $title_chinese_raw = $cached;
        }
        $title_chinese = $title_chinese_raw !== ''
            ? Anime_Sync_CN_Converter::static_convert( $title_chinese_raw )
            : '';

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

        $score_anilist = isset( $media['averageScore'] ) ? (int) $media['averageScore'] : 0;
        $score_bangumi = 0;
        if ( $bgm_data ) {
            $raw = $bgm_data['rating']['score'] ?? $bgm_data['score'] ?? null;
            if ( $raw !== null ) $score_bangumi = (int) round( (float) $raw * 10 );
        }

        $studios = [];
        foreach ( $media['studios']['nodes'] ?? [] as $studio ) {
            if ( ! empty( $studio['name'] ) ) $studios[] = $studio['name'];
        }

        $start_date   = $this->parse_fuzzy_date( $media['startDate'] ?? [] );
        $end_date     = $this->parse_fuzzy_date( $media['endDate']   ?? [] );
        $streaming    = $this->parse_streaming_links( $external_links );
        $parsed_links = $this->parse_external_links( $external_links );
        $staff        = $this->parse_staff( $media['staff']['edges']         ?? [] );
        $cast         = $this->parse_cast(  $media['characters']['edges']    ?? [] );
        $relations    = $this->parse_relations( $media['relations']['edges'] ?? [] );

        $trailer_url = '';
        if ( ! empty( $media['trailer'] ) ) {
            $t_id   = $media['trailer']['id']   ?? '';
            $t_site = $media['trailer']['site']  ?? '';
            if ( $t_id !== '' && strtolower( $t_site ) === 'youtube' ) {
                $trailer_url = "https://www.youtube.com/watch?v={$t_id}";
            }
        }

        $next_airing = null;
        if ( ! empty( $media['nextAiringEpisode'] ) ) {
            $next_airing = [
                'airingAt' => $media['nextAiringEpisode']['airingAt'] ?? 0,
                'episode'  => $media['nextAiringEpisode']['episode']  ?? 0,
            ];
        }

        $anime_tags = [];
        foreach ( $media['tags'] ?? [] as $tag ) {
            if ( ! empty( $tag['isMediaSpoiler'] ) ) continue;
            if ( ! empty( $tag['name'] ) ) $anime_tags[] = $tag['name'];
        }

        return [
            'anilist_id'             => $anilist_id,
            'mal_id'                 => $mal_id,
            'bangumi_id'             => $bangumi_id,
            'animethemes_slug'       => '',
            'anime_title_chinese'    => $title_chinese,
            'anime_title_romaji'     => $title_romaji,
            'anime_title_english'    => $title_english,
            'anime_title_native'     => $title_native,
            'anime_format'           => $media['format'] ?? '',
            'anime_status'           => $media['status'] ?? '',
            'anime_season'           => $media['season'] ?? '',
            'anime_season_year'      => $season_year,
            'anime_source'           => $media['source'] ?? '',
            'anime_episodes'         => $episodes,
            'anime_duration'         => (int) ( $media['duration'] ?? 0 ),
            'anime_studios'          => implode( ', ', $studios ),
            'anime_score_anilist'    => $score_anilist,
            'anime_score_bangumi'    => $score_bangumi,
            'anime_score_mal'        => 0,
            'anime_popularity'       => (int) ( $media['popularity'] ?? 0 ),
            'anime_cover_image'      => $media['coverImage']['extraLarge'] ?? $media['coverImage']['large'] ?? '',
            'anime_banner_image'     => $media['bannerImage'] ?? '',
            'anime_trailer_url'      => $trailer_url,
            'anime_synopsis_chinese' => $synopsis_chinese,
            'anime_synopsis_english' => $synopsis_english,
            'anime_start_date'       => $start_date,
            'anime_end_date'         => $end_date,
            'anime_streaming'        => wp_json_encode( $streaming,      JSON_UNESCAPED_UNICODE ),
            'anime_themes'           => '[]',
            'anime_staff_json'       => wp_json_encode( $staff,          JSON_UNESCAPED_UNICODE ),
            'anime_cast_json'        => wp_json_encode( $cast,           JSON_UNESCAPED_UNICODE ),
            'anime_relations_json'   => wp_json_encode( $relations,      JSON_UNESCAPED_UNICODE ),
            'anime_episodes_json'    => '[]',
            'anime_official_site'    => $parsed_links['official_site']   ?? '',
            'anime_twitter_url'      => $parsed_links['twitter_url']     ?? '',
            'anime_wikipedia_url'    => '',
            'anime_external_links'   => wp_json_encode( $external_links, JSON_UNESCAPED_UNICODE ),
            'anime_next_airing'      => $next_airing ? wp_json_encode( $next_airing ) : '',
            'anime_genres'           => $media['genres'] ?? [],
            'anime_tags'             => $anime_tags,
            '_bgm_raw'               => $bgm_data,
            '_needs_enrich'          => true,
        ];
    }

    // =========================================================================
    // PUBLIC – 補抓第二段資料（ACB）
    // =========================================================================

public function enrich_anime_data( int $post_id ): array|WP_Error {

    $anilist_id    = (int)    get_post_meta( $post_id, 'anime_anilist_id',    true );
    $bangumi_id    = (int)    get_post_meta( $post_id, 'anime_bangumi_id',    true );
    $mal_id = (int) get_post_meta( $post_id, 'anime_mal_id', true );
if ( ! $mal_id ) {
    $mal_id = (int) get_post_meta( $post_id, 'mal_id', true );
}
        $title_chinese = (string) get_post_meta( $post_id, 'anime_title_chinese', true );
        $title_native  = (string) get_post_meta( $post_id, 'anime_title_native',  true );
        $title_romaji  = (string) get_post_meta( $post_id, 'anime_title_romaji',  true );
        $title_english = (string) get_post_meta( $post_id, 'anime_title_english', true );

        if ( ! $anilist_id ) {
            return new WP_Error( 'missing_anilist_id', "Post {$post_id} has no anime_anilist_id." );
        }

        $enriched = [];

        if ( $bangumi_id > 0 ) {
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_staff = $this->get_bgm_staff( $bangumi_id );
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_chars = $this->get_bgm_chars( $bangumi_id );

            // ACF 修正：Bangumi 直接取代 AniList，不合併
            if ( ! empty( $bgm_staff ) ) {
                $enriched['anime_staff_json'] = wp_json_encode( $bgm_staff, JSON_UNESCAPED_UNICODE );
            }
            if ( ! empty( $bgm_chars ) ) {
                $enriched['anime_cast_json'] = wp_json_encode( $bgm_chars, JSON_UNESCAPED_UNICODE );
            }

            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_episodes = $this->fetch_bgm_episodes( $bangumi_id );
            if ( ! empty( $bgm_episodes ) ) {
                $enriched['anime_episodes_json'] = wp_json_encode( $bgm_episodes, JSON_UNESCAPED_UNICODE );
            }
        }

        if ( $mal_id > 0 ) {
            $score_mal = $this->fetch_mal_score( $mal_id );
            if ( $score_mal > 0 ) $enriched['anime_score_mal'] = $score_mal;
        }

        $wiki_url = $this->fetch_wikipedia_url( $title_chinese, $title_native, $title_romaji, $title_english );
        if ( $wiki_url !== '' ) $enriched['anime_wikipedia_url'] = $wiki_url;

        if ( $mal_id > 0 ) {
            $themes_result = $this->fetch_animethemes( $mal_id );
            if ( ! empty( $themes_result['themes'] ) ) {
                $enriched['anime_themes']         = wp_json_encode( $themes_result['themes'], JSON_UNESCAPED_UNICODE );
                $enriched['anime_animethemes_id'] = $themes_result['slug'] ?? '';
            }
        }

        foreach ( $enriched as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
        delete_post_meta( $post_id, '_needs_enrich' );
        update_post_meta( $post_id, '_enriched_at', current_time( 'mysql' ) );

        return $enriched;
    }

    // =========================================================================
    // PUBLIC – 完整匯入（供 Cron 全量同步）
    // =========================================================================

    public function get_full_anime_data( int $anilist_id, int $post_id = 0, ?int $bangumi_id = null ): array|WP_Error {

        $anilist_raw = $this->fetch_anilist_data( $anilist_id );
        if ( is_wp_error( $anilist_raw ) ) return $anilist_raw;

        $media = $anilist_raw['data']['Media'] ?? null;
        if ( empty( $media ) ) {
            return new WP_Error( 'anilist_empty', "AniList returned no data for ID {$anilist_id}." );
        }

        $mal_id         = isset( $media['idMal'] ) && $media['idMal'] !== null ? (int) $media['idMal'] : null;
        $title_romaji   = $media['title']['romaji']  ?? '';
        $title_english  = $media['title']['english'] ?? '';
        $title_native   = $media['title']['native']  ?? '';
        $season_year    = $media['seasonYear']        ?? 0;
        $season         = $media['season']            ?? '';
        $episodes       = (int) ( $media['episodes'] ?? 0 );
        $external_links = $media['externalLinks']     ?? [];

        if ( ! $bangumi_id || $bangumi_id <= 0 ) {
            $bangumi_id = $this->id_mapper->get_bangumi_id( [
                'anilist_id'     => $anilist_id,
                'mal_id'         => $mal_id,
                'post_id'        => $post_id,
                'title_native'   => $title_native,
                'title_romaji'   => $title_romaji,
                'title_chinese'  => '',
                'season_year'    => $season_year,
                'season'         => $season,
                'episodes'       => $episodes,
                'external_links' => $external_links,
            ] );
        }

        $bgm_data  = null;
        $bgm_staff = [];
        $bgm_chars = [];

        if ( $bangumi_id && $bangumi_id > 0 ) {
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_result = $this->get_bangumi_data( $bangumi_id );
            if ( ! is_wp_error( $bgm_result ) && is_array( $bgm_result ) ) {
                $bgm_data = $bgm_result;
                $this->rate_limiter->wait_if_needed( 'bangumi' );
                $bgm_staff = $this->get_bgm_staff( $bangumi_id );
                $this->rate_limiter->wait_if_needed( 'bangumi' );
                $bgm_chars = $this->get_bgm_chars( $bangumi_id );
            }
        }

        $title_chinese_raw = '';
        if ( $bgm_data ) $title_chinese_raw = $bgm_data['name_cn'] ?? $bgm_data['name'] ?? '';
        if ( $title_chinese_raw === '' && $bangumi_id ) {
            $cached = $this->id_mapper->get_chinese_title( $bangumi_id );
            if ( $cached ) $title_chinese_raw = $cached;
        }
        $title_chinese = $title_chinese_raw !== ''
            ? Anime_Sync_CN_Converter::static_convert( $title_chinese_raw )
            : '';

        $synopsis_chinese = '';
        $synopsis_english = '';
        if ( $bgm_data && ! empty( $bgm_data['summary'] ) ) {
            $synopsis_chinese = $this->clean_synopsis( $bgm_data['summary'] );
            if ( $synopsis_chinese !== '' ) $synopsis_chinese = Anime_Sync_CN_Converter::static_convert( $synopsis_chinese );
        }
        if ( ! empty( $media['description'] ) ) $synopsis_english = $this->clean_synopsis( $media['description'] );

        $score_anilist = isset( $media['averageScore'] ) ? (int) $media['averageScore'] : 0;
        $score_bangumi = 0;
        if ( $bgm_data ) {
            $raw = $bgm_data['rating']['score'] ?? $bgm_data['score'] ?? null;
            if ( $raw !== null ) $score_bangumi = (int) round( (float) $raw * 10 );
        }
        $score_mal = ( $mal_id && $mal_id > 0 ) ? $this->fetch_mal_score( $mal_id ) : 0;

        $studios = [];
        foreach ( $media['studios']['nodes'] ?? [] as $s ) {
            if ( ! empty( $s['name'] ) ) $studios[] = $s['name'];
        }

        $start_date         = $this->parse_fuzzy_date( $media['startDate'] ?? [] );
        $end_date           = $this->parse_fuzzy_date( $media['endDate']   ?? [] );
        $streaming          = $this->parse_streaming_links( $external_links );
        $parsed_links       = $this->parse_external_links( $external_links );
        $wikipedia_url      = $this->fetch_wikipedia_url( $title_chinese, $title_native, $title_romaji, $title_english );
        $animethemes_result = $this->fetch_animethemes( $mal_id );
        $themes             = $animethemes_result['themes'] ?? [];
        $animethemes_slug   = $animethemes_result['slug']   ?? '';

        $episodes_json = '[]';
        if ( $bangumi_id && $bangumi_id > 0 ) {
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_episodes  = $this->fetch_bgm_episodes( $bangumi_id );
            $episodes_json = wp_json_encode( $bgm_episodes, JSON_UNESCAPED_UNICODE );
        }

        $staff     = $this->parse_staff( $media['staff']['edges']         ?? [] );
        $cast      = $this->parse_cast(  $media['characters']['edges']    ?? [] );
        $relations = $this->parse_relations( $media['relations']['edges'] ?? [] );

        // ACF 修正：有 Bangumi 就完全取代 AniList，沒有才 fallback
        if ( ! empty( $bgm_staff ) && ! is_wp_error( $bgm_staff ) ) $staff = $bgm_staff;
        if ( ! empty( $bgm_chars ) && ! is_wp_error( $bgm_chars ) ) $cast  = $bgm_chars;

        $trailer_url = '';
        if ( ! empty( $media['trailer'] ) ) {
            $t_id   = $media['trailer']['id']   ?? '';
            $t_site = $media['trailer']['site']  ?? '';
            if ( $t_id !== '' && strtolower( $t_site ) === 'youtube' ) {
                $trailer_url = "https://www.youtube.com/watch?v={$t_id}";
            }
        }

        $next_airing = null;
        if ( ! empty( $media['nextAiringEpisode'] ) ) {
            $next_airing = [
                'airingAt' => $media['nextAiringEpisode']['airingAt'] ?? 0,
                'episode'  => $media['nextAiringEpisode']['episode']  ?? 0,
            ];
        }

        $anime_tags = [];
        foreach ( $media['tags'] ?? [] as $tag ) {
            if ( ! empty( $tag['isMediaSpoiler'] ) ) continue;
            if ( ! empty( $tag['name'] ) ) $anime_tags[] = $tag['name'];
        }

        return [
            'anilist_id'             => $anilist_id,
            'mal_id'                 => $mal_id,
            'bangumi_id'             => $bangumi_id,
            'animethemes_slug'       => $animethemes_slug,
            'anime_title_chinese'    => $title_chinese,
            'anime_title_romaji'     => $title_romaji,
            'anime_title_english'    => $title_english,
            'anime_title_native'     => $title_native,
            'anime_format'           => $media['format'] ?? '',
            'anime_status'           => $media['status'] ?? '',
            'anime_season'           => $media['season'] ?? '',
            'anime_season_year'      => $season_year,
            'anime_source'           => $media['source'] ?? '',
            'anime_episodes'         => $episodes,
            'anime_duration'         => (int) ( $media['duration'] ?? 0 ),
            'anime_studios'          => implode( ', ', $studios ),
            'anime_score_anilist'    => $score_anilist,
            'anime_score_bangumi'    => $score_bangumi,
            'anime_score_mal'        => $score_mal,
            'anime_popularity'       => (int) ( $media['popularity'] ?? 0 ),
            'anime_cover_image'      => $media['coverImage']['extraLarge'] ?? $media['coverImage']['large'] ?? '',
            'anime_banner_image'     => $media['bannerImage'] ?? '',
            'anime_trailer_url'      => $trailer_url,
            'anime_synopsis_chinese' => $synopsis_chinese,
            'anime_synopsis_english' => $synopsis_english,
            'anime_start_date'       => $start_date,
            'anime_end_date'         => $end_date,
            'anime_streaming'        => wp_json_encode( $streaming,      JSON_UNESCAPED_UNICODE ),
            'anime_themes'           => wp_json_encode( $themes,         JSON_UNESCAPED_UNICODE ),
            'anime_staff_json'       => wp_json_encode( $staff,          JSON_UNESCAPED_UNICODE ),
            'anime_cast_json'        => wp_json_encode( $cast,           JSON_UNESCAPED_UNICODE ),
            'anime_relations_json'   => wp_json_encode( $relations,      JSON_UNESCAPED_UNICODE ),
            'anime_episodes_json'    => $episodes_json,
            'anime_official_site'    => $parsed_links['official_site']   ?? '',
            'anime_twitter_url'      => $parsed_links['twitter_url']     ?? '',
            'anime_wikipedia_url'    => $wikipedia_url,
            'anime_external_links'   => wp_json_encode( $external_links, JSON_UNESCAPED_UNICODE ),
            'anime_next_airing'      => $next_airing ? wp_json_encode( $next_airing ) : '',
            'anime_genres'           => $media['genres'] ?? [],
            'anime_tags'             => $anime_tags,
            '_bgm_raw'               => $bgm_data,
        ];
    }

    // =========================================================================
    // PUBLIC – 系列樹（ACD）
    // =========================================================================

public function get_series_tree( int $anilist_id ): array|WP_Error {

    $cache_key = 'anime_sync_series_tree_' . $anilist_id;
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    $root_id = $this->find_series_root( $anilist_id );
    if ( is_wp_error( $root_id ) ) return $root_id;

    $nodes = $this->expand_series_tree( $root_id );
    if ( is_wp_error( $nodes ) ) return $nodes;

    $series_name   = '';
    $series_romaji = '';  // ACI 新增

    foreach ( $nodes as $node ) {
        if ( (int) $node['anilist_id'] === $root_id ) {
            $series_name = $node['title_chinese'] ?: $node['title_romaji'] ?: '';
            $series_name = preg_replace(
                '/[\s：:]*(\d+(?:st|nd|rd|th)?[\s]*[Ss]eason|第[一二三四五六七八九十\d]+[季期]|[Ss]\d+).*$/u',
                '',
                $series_name
            );
            $series_name   = trim( $series_name );
            $series_romaji = $node['title_romaji'] ?? '';  // ACI 新增
            break;
        }
    }

    $result = [
        'root_id'       => $root_id,
        'series_name'   => $series_name,
        'series_romaji' => $series_romaji,  // ACI 新增
        'nodes'         => $nodes,
    ];

    set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
    return $result;
}

    // =========================================================================
    // PUBLIC – AniList 人氣排行（ACD + ACE）
    // =========================================================================

    public function fetch_anilist_popularity( int $page = 1 ): array|WP_Error {

        $cache_key = 'anime_sync_popularity_p' . $page;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $query = '
        query ($page: Int) {
          Page(page: $page, perPage: 50) {
            pageInfo { total currentPage hasNextPage }
            media(type: ANIME, sort: POPULARITY_DESC) {
              id
              title { romaji native }
              coverImage { large }
              format
              status
              seasonYear
              episodes
              popularity
            }
          }
        }';

        $this->rate_limiter->wait_if_needed( 'anilist' );
        $payload  = wp_json_encode( [ 'query' => $query, 'variables' => [ 'page' => $page ] ] );
        $response = wp_remote_post( self::ANILIST_ENDPOINT, [
            'timeout' => 20,
            'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'body'    => $payload,
        ] );

        if ( is_wp_error( $response ) ) return $response;
        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response, 'anilist' );
            sleep( 65 );
            $response = wp_remote_post( self::ANILIST_ENDPOINT, [
                'timeout' => 20,
                'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                'body'    => $payload,
            ] );
            if ( is_wp_error( $response ) ) return $response;
            $code = (int) wp_remote_retrieve_response_code( $response );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'anilist_http_error', "AniList popularity query returned HTTP {$code}." );
        }

        $decoded  = json_decode( wp_remote_retrieve_body( $response ), true );
        $page_obj = $decoded['data']['Page'] ?? null;
        if ( ! $page_obj ) {
            return new WP_Error( 'anilist_no_page', 'AniList popularity: no Page in response.' );
        }

        $items = [];
        foreach ( $page_obj['media'] ?? [] as $media ) {
            $al_id   = (int) ( $media['id'] ?? 0 );
            $post_id = $this->find_existing_post( $al_id );
            $items[] = [
                'anilist_id'   => $al_id,
                'title_romaji' => $media['title']['romaji']  ?? '',
                'title_native' => $media['title']['native']  ?? '',
                'cover_image'  => $media['coverImage']['large'] ?? '',
                'format'       => $media['format']     ?? '',
                'status'       => $media['status']     ?? '',
                'season_year'  => $media['seasonYear'] ?? 0,
                'episodes'     => (int) ( $media['episodes'] ?? 0 ),
                'popularity'   => (int) ( $media['popularity'] ?? 0 ),
                'imported'     => $post_id > 0,
                'post_id'      => $post_id,
                'edit_url'     => $post_id > 0 ? get_edit_post_link( $post_id, 'raw' ) : '',
            ];
        }

        $result = [
            'page_info' => $page_obj['pageInfo'] ?? [],
            'items'     => $items,
        ];

        set_transient( $cache_key, $result, 30 * MINUTE_IN_SECONDS );
        return $result;
    }

    // =========================================================================
    // PUBLIC – 重新同步 Bangumi（ACG 新增）
    // 強制覆蓋：中文標題、簡介、封面、評分、工作人員、角色、集數
    // =========================================================================

    public function ajax_resync_bangumi( int $post_id, int $bangumi_id ): array|WP_Error {

        // 1. 取得 Bangumi 基本資料
        $this->rate_limiter->wait_if_needed( 'bangumi' );
        $bgm_data = $this->get_bangumi_data( $bangumi_id );
        if ( is_wp_error( $bgm_data ) ) return $bgm_data;

        $updated = [];

        // 2. 中文標題
        $title_raw = $bgm_data['name_cn'] ?? $bgm_data['name'] ?? '';
        if ( $title_raw !== '' ) {
            $title_chinese = Anime_Sync_CN_Converter::static_convert( $title_raw );
            update_post_meta( $post_id, 'anime_title_chinese', $title_chinese );
            $updated[] = 'anime_title_chinese';
        }

        // 3. 中文簡介
        if ( ! empty( $bgm_data['summary'] ) ) {
            $synopsis = $this->clean_synopsis( $bgm_data['summary'] );
            if ( $synopsis !== '' ) {
                $synopsis = Anime_Sync_CN_Converter::static_convert( $synopsis );
            }
            update_post_meta( $post_id, 'anime_synopsis_chinese', $synopsis );
            $updated[] = 'anime_synopsis_chinese';
        }

        // 4. 封面圖（Bangumi 有較高品質則覆蓋）
        $bgm_cover = $bgm_data['images']['large'] ?? $bgm_data['images']['medium'] ?? '';
        if ( $bgm_cover !== '' ) {
            update_post_meta( $post_id, 'anime_cover_image', $bgm_cover );
            $updated[] = 'anime_cover_image';
        }

        // 5. Bangumi 評分
        $raw_score = $bgm_data['rating']['score'] ?? $bgm_data['score'] ?? null;
        if ( $raw_score !== null ) {
            $score_bangumi = (int) round( (float) $raw_score * 10 );
            update_post_meta( $post_id, 'anime_score_bangumi', $score_bangumi );
            $updated[] = 'anime_score_bangumi';
        }

        // 6. 工作人員
        $this->rate_limiter->wait_if_needed( 'bangumi' );
        $bgm_staff = $this->get_bgm_staff( $bangumi_id );
        if ( ! empty( $bgm_staff ) ) {
            update_post_meta( $post_id, 'anime_staff_json', wp_json_encode( $bgm_staff, JSON_UNESCAPED_UNICODE ) );
            $updated[] = 'anime_staff_json';
        }

        // 7. 角色
        $this->rate_limiter->wait_if_needed( 'bangumi' );
        $bgm_chars = $this->get_bgm_chars( $bangumi_id );
        if ( ! empty( $bgm_chars ) ) {
            update_post_meta( $post_id, 'anime_cast_json', wp_json_encode( $bgm_chars, JSON_UNESCAPED_UNICODE ) );
            $updated[] = 'anime_cast_json';
        }

        // 8. 集數
        $this->rate_limiter->wait_if_needed( 'bangumi' );
        $bgm_episodes = $this->fetch_bgm_episodes( $bangumi_id );
        if ( ! empty( $bgm_episodes ) ) {
            update_post_meta( $post_id, 'anime_episodes_json', wp_json_encode( $bgm_episodes, JSON_UNESCAPED_UNICODE ) );
            $updated[] = 'anime_episodes_json';
        }

        // 9. 更新同步時間
        update_post_meta( $post_id, 'anime_last_sync_time', current_time( 'mysql' ) );
        $updated[] = 'anime_last_sync_time';

        return $updated;
    }

    // =========================================================================
    // PRIVATE – 找系列根源
    // =========================================================================

    private function find_series_root( int $anilist_id, array $visited = [] ): int|WP_Error {
        if ( in_array( $anilist_id, $visited, true ) ) return $anilist_id;
        $visited[] = $anilist_id;

        $relations = $this->fetch_anilist_relations( $anilist_id );
        if ( is_wp_error( $relations ) ) return $anilist_id;

        foreach ( $relations as $rel ) {
            if ( $rel['type'] === 'PREQUEL' && ! empty( $rel['node_id'] ) ) {
                return $this->find_series_root( (int) $rel['node_id'], $visited );
            }
        }
        return $anilist_id;
    }

    // =========================================================================
    // PRIVATE – BFS 展開系列樹（ACE）
    // =========================================================================

    private function expand_series_tree( int $root_id ): array|WP_Error {

        $queue        = [ $root_id ];
        $visited      = [];
        $nodes        = [];
        $relation_map = [ $root_id => '' ];

        while ( ! empty( $queue ) ) {
            $current_id = array_shift( $queue );
            if ( in_array( $current_id, $visited, true ) ) continue;
            $visited[] = $current_id;

            $node_data = $this->fetch_anilist_node_data( $current_id );
            if ( is_wp_error( $node_data ) ) continue;

            $post_id = $this->find_existing_post( $current_id );

            $node_data['relation_type'] = $relation_map[ $current_id ] ?? '';
            $node_data['imported']      = $post_id > 0;
            $node_data['post_id']       = $post_id;
            $node_data['edit_url']      = $post_id > 0 ? get_edit_post_link( $post_id, 'raw' ) : '';
            $nodes[]                    = $node_data;

            $relations = $this->fetch_anilist_relations( $current_id );
            if ( is_wp_error( $relations ) ) continue;

            foreach ( $relations as $rel ) {
                $nid = (int) ( $rel['node_id'] ?? 0 );
                if (
                    $nid > 0 &&
                    in_array( $rel['type'], self::SERIES_RELATION_TYPES, true ) &&
                    ! in_array( $nid, $visited, true )
                ) {
                    if ( ! isset( $relation_map[ $nid ] ) ) {
                        $relation_map[ $nid ] = $rel['type'];
                    }
                    $queue[] = $nid;
                }
            }

            $this->rate_limiter->wait_if_needed( 'anilist' );
        }

        return $nodes;
    }

    // =========================================================================
    // PRIVATE – 取單一作品的關係列表
    // =========================================================================

    private function fetch_anilist_relations( int $anilist_id ): array|WP_Error {

        $cache_key = 'anime_sync_al_relations_' . $anilist_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (array) $cached;

        $query = '
        query ($id: Int) {
          Media(id: $id, type: ANIME) {
            relations {
              edges {
                relationType
                node { id }
              }
            }
          }
        }';

        $this->rate_limiter->wait_if_needed( 'anilist' );
        $payload  = wp_json_encode( [ 'query' => $query, 'variables' => [ 'id' => $anilist_id ] ] );
        $response = wp_remote_post( self::ANILIST_ENDPOINT, [
            'timeout' => 15,
            'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'body'    => $payload,
        ] );

        if ( is_wp_error( $response ) ) return $response;
        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response, 'anilist' );
            sleep( 65 );
            $response = wp_remote_post( self::ANILIST_ENDPOINT, [
                'timeout' => 15,
                'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                'body'    => $payload,
            ] );
            if ( is_wp_error( $response ) ) return $response;
            $code = (int) wp_remote_retrieve_response_code( $response );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'anilist_http_error', "fetch_anilist_relations: HTTP {$code} for ID {$anilist_id}." );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        $edges   = $decoded['data']['Media']['relations']['edges'] ?? [];

        $result = [];
        foreach ( $edges as $edge ) {
            $result[] = [
                'type'    => $edge['relationType']  ?? '',
                'node_id' => $edge['node']['id']    ?? 0,
            ];
        }

        set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
        return $result;
    }

    // =========================================================================
    // PRIVATE – 取單一作品節點的展示資料
    // =========================================================================

    private function fetch_anilist_node_data( int $anilist_id ): array|WP_Error {

        $cache_key = 'anime_sync_al_node_' . $anilist_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (array) $cached;

        $query = '
        query ($id: Int) {
          Media(id: $id, type: ANIME) {
            id
            title { romaji english native }
            coverImage { large }
            format
            status
            seasonYear
            episodes
          }
        }';

        $this->rate_limiter->wait_if_needed( 'anilist' );
        $payload  = wp_json_encode( [ 'query' => $query, 'variables' => [ 'id' => $anilist_id ] ] );
        $response = wp_remote_post( self::ANILIST_ENDPOINT, [
            'timeout' => 15,
            'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'body'    => $payload,
        ] );

        if ( is_wp_error( $response ) ) return $response;
        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response, 'anilist' );
            sleep( 65 );
            $response = wp_remote_post( self::ANILIST_ENDPOINT, [
                'timeout' => 15,
                'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                'body'    => $payload,
            ] );
            if ( is_wp_error( $response ) ) return $response;
            $code = (int) wp_remote_retrieve_response_code( $response );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'anilist_http_error', "fetch_anilist_node_data: HTTP {$code} for ID {$anilist_id}." );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        $media   = $decoded['data']['Media'] ?? null;
        if ( ! $media ) {
            return new WP_Error( 'anilist_no_media', "fetch_anilist_node_data: no Media for ID {$anilist_id}." );
        }

        $title_chinese    = '';
        $bgm_id_candidate = $this->id_mapper->get_bangumi_id( [
            'anilist_id'     => $anilist_id,
            'mal_id'         => null,
            'post_id'        => 0,
            'title_native'   => $media['title']['native']  ?? '',
            'title_romaji'   => $media['title']['romaji']  ?? '',
            'title_chinese'  => '',
            'season_year'    => $media['seasonYear'] ?? 0,
            'season'         => '',
            'episodes'       => (int) ( $media['episodes'] ?? 0 ),
            'external_links' => [],
        ] );
        if ( $bgm_id_candidate && $bgm_id_candidate > 0 ) {
            $cached_cn = $this->id_mapper->get_chinese_title( $bgm_id_candidate );
            if ( $cached_cn ) {
                $title_chinese = Anime_Sync_CN_Converter::static_convert( $cached_cn );
            }
        }

        $result = [
            'anilist_id'    => $anilist_id,
            'title_romaji'  => $media['title']['romaji']  ?? '',
            'title_english' => $media['title']['english'] ?? '',
            'title_native'  => $media['title']['native']  ?? '',
            'title_chinese' => $title_chinese,
            'cover_image'   => $media['coverImage']['large'] ?? '',
            'format'        => $media['format']    ?? '',
            'status'        => $media['status']    ?? '',
            'season_year'   => $media['seasonYear'] ?? 0,
            'episodes'      => (int) ( $media['episodes'] ?? 0 ),
        ];

        set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
        return $result;
    }

    // =========================================================================
    // PRIVATE – 查找已存在的文章
    // =========================================================================

    private function find_existing_post( int $anilist_id ): int {
        if ( $anilist_id <= 0 ) return 0;

        $cache_key = 'anime_sync_existing_post_' . $anilist_id;
        $cached    = wp_cache_get( $cache_key, 'anime_sync' );
        if ( $cached !== false ) return (int) $cached;

        $q = new WP_Query( [
            'post_type'      => 'anime',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ [
                'key'     => 'anime_anilist_id',
                'value'   => $anilist_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ] ],
        ] );

        $post_id = ! empty( $q->posts ) ? (int) $q->posts[0] : 0;
        wp_cache_set( $cache_key, $post_id, 'anime_sync', 300 );
        return $post_id;
    }

    // =========================================================================
    // PRIVATE – AniList 單部完整查詢
    // =========================================================================

    private function fetch_anilist_data( int $anilist_id ): array|WP_Error {

        $cache_key = 'anime_sync_anilist_' . $anilist_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $query = '
        query ($id: Int) {
          Media(id: $id, type: ANIME) {
            id idMal
            title { romaji english native }
            status format episodes duration source season seasonYear
            startDate { year month day }
            endDate   { year month day }
            averageScore popularity
            bannerImage
            coverImage { extraLarge large }
            description(asHtml: false)
            genres
            tags { name isMediaSpoiler }
            trailer { id site }
            nextAiringEpisode { airingAt episode }
            externalLinks { url site type language }
            studios(isMain: true) { nodes { name } }
            staff(sort: RELEVANCE, perPage: 25) {
              edges {
                role
                node { id name { full native } image { large } }
              }
            }
            characters(sort: ROLE, perPage: 25) {
              edges {
                role
                node { id name { full native } image { large } }
                voiceActors(language: JAPANESE) {
                  id name { full native } image { large }
                }
              }
            }
            relations {
              edges {
                relationType
                node { id type title { romaji } }
              }
            }
          }
        }';

        $this->rate_limiter->wait_if_needed( 'anilist' );
        $payload  = wp_json_encode( [ 'query' => $query, 'variables' => [ 'id' => $anilist_id ] ] );
        $response = wp_remote_post( self::ANILIST_ENDPOINT, [
            'timeout' => 20,
            'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'body'    => $payload,
        ] );

        if ( is_wp_error( $response ) ) return $response;
        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response, 'anilist' );
            sleep( 65 );
            $response = wp_remote_post( self::ANILIST_ENDPOINT, [
                'timeout' => 20,
                'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
                'body'    => $payload,
            ] );
            if ( is_wp_error( $response ) ) return $response;
            $code = (int) wp_remote_retrieve_response_code( $response );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'anilist_http_error', "AniList returned HTTP {$code} for ID {$anilist_id}." );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $decoded['data']['Media'] ) ) {
            return new WP_Error( 'anilist_empty', "AniList returned no Media for ID {$anilist_id}." );
        }

        set_transient( $cache_key, $decoded, 6 * HOUR_IN_SECONDS );
        return $decoded;
    }

// =========================================================================
// PRIVATE – MAL 評分（ACG：統一 User-Agent）
// =========================================================================

// =========================================================================
// PRIVATE – MAL 評分（ACH：改用 cURL 繞過 Hostinger wp_remote_get 504）
// =========================================================================

private function fetch_mal_score( ?int $mal_id ): int {
    if ( ! $mal_id || $mal_id <= 0 ) return 0;

    $cache_key = 'anime_sync_mal_score_' . $mal_id;
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return (int) $cached;

    $this->rate_limiter->wait_if_needed( 'jikan' );

    $url = self::JIKAN_ANIME_URL . $mal_id;
    $ch  = curl_init();
    curl_setopt_array( $ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => self::USER_AGENT,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ] );
    $body = curl_exec( $ch );
    $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $err  = curl_error( $ch );
    curl_close( $ch );

    if ( $err !== '' ) {
        error_log( '[MAL] cURL error: ' . $err );
        return 0;
    }
    if ( $code !== 200 ) {
        error_log( '[MAL] cURL HTTP code: ' . $code . ' for MAL ID: ' . $mal_id );
        return 0;
    }

    $data  = json_decode( $body, true );
    $score = isset( $data['data']['score'] ) ? (int) round( (float) $data['data']['score'] * 10 ) : 0;

    error_log( '[MAL] ID: ' . $mal_id . ' score: ' . $score );

    set_transient( $cache_key, $score, 12 * HOUR_IN_SECONDS );
    return $score;
}


    // =========================================================================
    // PRIVATE – Wikipedia URL
    // =========================================================================

    private function fetch_wikipedia_url( string $title_chinese, string $title_native, string $title_romaji, string $title_english ): string {
        $candidates = [ $title_chinese, $title_native, $title_romaji, $title_english ];

        if ( $title_chinese !== '' ) {
            $url = $this->search_wiki_zh( $title_chinese, $candidates );
            if ( $url !== '' ) return $url;
        }
        if ( $title_native !== '' ) {
            $url = $this->search_wiki_zh( $title_native, $candidates );
            if ( $url !== '' ) return $url;
        }
        $try = $title_english ?: $title_romaji;
        if ( $try !== '' ) {
            $url = $this->search_wiki_en( $try, $candidates );
            if ( $url !== '' ) return $url;
        }
        return '';
    }

    private function search_wiki_zh( string $title, array $candidates = [] ): string {
        $response = wp_remote_get( add_query_arg( [
            'action'   => 'query',
            'list'     => 'search',
            'srsearch' => $title,
            'srlimit'  => 3,
            'format'   => 'json',
        ], self::WIKI_ZH_API ), [ 'timeout' => 5 ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return '';
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $hits = $data['query']['search'] ?? [];
        if ( empty( $hits ) ) return '';

        foreach ( $hits as $hit ) {
            $page_title = $hit['title'] ?? '';
            if ( $page_title === '' ) continue;
            if ( $this->wiki_title_matches( $page_title, $candidates ) ) {
                return 'https://zh.wikipedia.org/wiki/' . rawurlencode( str_replace( ' ', '_', $page_title ) );
            }
        }
        return '';
    }

    private function search_wiki_en( string $title, array $candidates = [] ): string {
        $slug     = str_replace( ' ', '_', $title );
        $response = wp_remote_get( self::WIKI_EN_REST . rawurlencode( $slug ), [ 'timeout' => 5 ] );
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return '';
        $data       = json_decode( wp_remote_retrieve_body( $response ), true );
        $url        = $data['content_urls']['desktop']['page'] ?? '';
        $page_title = $data['title'] ?? '';

        if ( $url !== '' && $page_title !== '' && ! $this->wiki_title_matches( $page_title, $candidates ) ) {
            return '';
        }
        return $url;
    }

    private function wiki_title_matches( string $page_title, array $candidates ): bool {
        $page_lower = mb_strtolower( $page_title );
        foreach ( $candidates as $candidate ) {
            $candidate = trim( (string) $candidate );
            if ( $candidate === '' ) continue;
            $cand_lower = mb_strtolower( $candidate );

            // 包含判斷
            if ( mb_strpos( $page_lower, $cand_lower ) !== false || mb_strpos( $cand_lower, $page_lower ) !== false ) {
                return true;
            }

            // 相似度判斷
            similar_text( $page_lower, $cand_lower, $percent );
            if ( $percent >= 35 ) {
                return true;
            }
        }
        return false;
    }


    // =========================================================================
    // PRIVATE – Bangumi 資料（ACG：統一 User-Agent）
    // =========================================================================

    private function get_bangumi_data( int $bangumi_id ): array|WP_Error {

        $cache_key = 'anime_sync_bgm_subject_' . $bangumi_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $response = wp_remote_get( self::BGM_SUBJECT_URL . $bangumi_id, [
            'timeout' => 10,
            'headers' => [ 'User-Agent' => self::USER_AGENT ],
        ] );

        if ( is_wp_error( $response ) ) return $response;
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'bgm_http_error', "Bangumi returned HTTP {$code} for ID {$bangumi_id}." );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data ) ) {
            return new WP_Error( 'bgm_empty', "Bangumi returned empty data for ID {$bangumi_id}." );
        }

        set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
        return $data;
    }

private function get_bgm_staff( int $bangumi_id ): array {
    $cache_key = 'anime_sync_bgm_staff_' . $bangumi_id;
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return (array) $cached;

    $response = wp_remote_get( self::BGM_SUBJECT_URL . $bangumi_id . '/persons', [
        'timeout' => 10,
        'headers' => [ 'User-Agent' => self::USER_AGENT ],
    ] );

    if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

    $persons = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $persons ) ) return [];

    // ACH：只保留核心主創職位（依據 Bangumi 實際 relation 值）
    $allowed_roles = [
        '导演',       // 監督
        '原作',       // 原作
        '系列构成',   // 系列構成
        '脚本',       // 腳本
        '人物原案',   // 人物原案
        '角色设计',   // 角色設計
        '人物设定',   // 人物設定
        '音乐',       // 音樂
        '音響監督',
        '音响监督',
        '主题歌演出', // OP/ED 演出
        '主题歌作词', // OP/ED 作詞
        '主题歌作曲', // OP/ED 作曲
        '动画制作',   // 動漫製作公司
    ];

    $staff = [];
    foreach ( $persons as $p ) {
        $role = $p['relation'] ?? '';
        if ( in_array( $role, $allowed_roles, true ) ) {
            $staff[] = [
                'id'     => $p['id']             ?? 0,
                'name'   => Anime_Sync_CN_Converter::static_convert( $p['name'] ?? '' ),
                'role'   => $role,
                'image'  => $p['images']['large'] ?? $p['images']['medium'] ?? '',
                'source' => 'bangumi',
            ];
        }
    }

    // 原作排最前面，其餘依原順序
    usort( $staff, function( $a, $b ) {
        if ( $a['role'] === '原作' ) return -1;
        if ( $b['role'] === '原作' ) return 1;
        return 0;
    } );

    set_transient( $cache_key, $staff, 12 * HOUR_IN_SECONDS );
    return $staff;
}

    private function get_bgm_chars( int $bangumi_id ): array {
        $cache_key = 'anime_sync_bgm_chars_' . $bangumi_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (array) $cached;

        $response = wp_remote_get( self::BGM_SUBJECT_URL . $bangumi_id . '/characters', [
            'timeout' => 10,
            'headers' => [ 'User-Agent' => self::USER_AGENT ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

        $chars_raw = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $chars_raw ) ) return [];

        $chars = [];
        foreach ( $chars_raw as $c ) {
            $va = [];
            foreach ( $c['actors'] ?? [] as $a ) {
                $va[] = [
                    'id'    => $a['id']             ?? 0,
                    'name' => Anime_Sync_CN_Converter::static_convert( $a['name'] ?? '' ),
                    'image' => $a['images']['large'] ?? '',
                ];
            }
            $chars[] = [
                'id'           => $c['id']             ?? 0,
                'name' => Anime_Sync_CN_Converter::static_convert( $c['name'] ?? '' ),
                'role'         => $c['relation']        ?? '',
                'image'        => $c['images']['large'] ?? $c['images']['medium'] ?? '',
                'voice_actors' => $va,
                'source'       => 'bangumi',
            ];
        }

        set_transient( $cache_key, $chars, 12 * HOUR_IN_SECONDS );
        return $chars;
    }

    private function fetch_bgm_episodes( int $bangumi_id ): array {
        $cache_key = 'anime_sync_bgm_eps_' . $bangumi_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (array) $cached;

        $response = wp_remote_get( add_query_arg( [
            'subject_id' => $bangumi_id,
            'type'       => 0,
            'limit'      => 100,
            'offset'     => 0,
        ], self::BGM_EPISODES_URL ), [
            'timeout' => 10,
            'headers' => [ 'User-Agent' => self::USER_AGENT ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $eps  = $body['data'] ?? [];
        if ( ! is_array( $eps ) ) return [];

        $result = [];
        foreach ( $eps as $ep ) {
            $result[] = [
                'id'      => $ep['id']      ?? 0,
                'ep'      => $ep['ep']      ?? 0,
                'name'    => $ep['name']    ?? '',
                'name_cn' => $ep['name_cn'] ?? '',
                'airdate' => $ep['airdate'] ?? '',
                'comment' => (int) ( $ep['comment'] ?? 0 ),
            ];
        }

        set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );
        return $result;
    }

    // =========================================================================
    // PRIVATE – AnimeThemes（ACF：加入 videos.audio，取出 audio_url）
    //           ACG：統一 User-Agent
    // =========================================================================

    private function fetch_animethemes( ?int $mal_id ): array {
        if ( ! $mal_id || $mal_id <= 0 ) return [];

        $cache_key = 'anime_sync_themes_' . $mal_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (array) $cached;

        $this->rate_limiter->wait_if_needed( 'animethemes' );
        $response = wp_remote_get( add_query_arg( [
            'filter[has]'         => 'resources',
            'filter[site]'        => 'MyAnimeList',
            'filter[external_id]' => $mal_id,
            'include'             => 'animethemes.animethemeentries.videos.audio,animethemes.song.artists',
            'fields[anime]'       => 'slug',
            'fields[animetheme]'  => 'type,sequence,slug',
            'fields[song]'        => 'title',
            'fields[artist]'      => 'name',
            'fields[video]'       => 'link,resolution',
            'fields[audio]'       => 'link',
        ], self::ANIMETHEMES_URL ), [
            'timeout' => 8,
            'headers' => [ 'User-Agent' => self::USER_AGENT ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

        $body      = json_decode( wp_remote_retrieve_body( $response ), true );
        $anime_arr = $body['anime'] ?? [];
        if ( empty( $anime_arr ) ) return [];

        $anime_obj = $anime_arr[0];
        $slug      = $anime_obj['slug'] ?? '';
        $themes    = [];

        foreach ( $anime_obj['animethemes'] ?? [] as $theme ) {
            $entry     = $theme['animethemeentries'][0] ?? [];
            $videos    = $entry['videos']               ?? [];
            $video     = ! empty( $videos ) ? $videos[0] : [];
            $audio_url = $video['audio']['link']        ?? '';
            $video_url = $video['link']                 ?? '';

         $artists = [];
foreach ( $theme['song']['artists'] ?? [] as $a ) {
    $artists[] = $a['name'] ?? '';
}

$themes[] = [
    'type'       => $theme['type']          ?? '',
    'sequence'   => $theme['sequence']      ?? 1,
    'slug'       => $theme['slug']          ?? '',
    'song_title' => $theme['song']['title'] ?? '',
    'artist'     => implode( ', ', $artists ),
    'audio_url'  => $audio_url,
    'video_url'  => $video_url,
    'resolution' => $video['resolution']    ?? '',
];
        }

        $result = [ 'slug' => $slug, 'themes' => $themes ];
        set_transient( $cache_key, $result, 24 * HOUR_IN_SECONDS );
        return $result;
    }

    // =========================================================================
    // PRIVATE – 解析器
    // =========================================================================

    private function parse_fuzzy_date( array $date ): string {
        $y = $date['year']  ?? 0;
        $m = $date['month'] ?? 0;
        $d = $date['day']   ?? 0;
        if ( ! $y ) return '';
        return sprintf( '%04d-%02d-%02d', $y, $m ?: 1, $d ?: 1 );
    }

    private function parse_streaming_links( array $links ): array {
        $streaming_sites = [ 'Crunchyroll', 'Funimation', 'Netflix', 'Amazon Prime Video', 'Disney Plus', 'Bilibili', 'HIDIVE', 'VRV' ];
        $result = [];
        foreach ( $links as $link ) {
            if ( in_array( $link['site'] ?? '', $streaming_sites, true ) ) {
                $result[] = [
                    'site' => $link['site'] ?? '',
                    'url'  => $link['url']  ?? '',
                ];
            }
        }
        return $result;
    }

    private function parse_external_links( array $links ): array {
        $result = [ 'official_site' => '', 'twitter_url' => '' ];
        foreach ( $links as $link ) {
            $site = strtolower( $link['site'] ?? '' );
            $url  = $link['url'] ?? '';
            if ( $site === 'twitter' || $site === 'x' ) $result['twitter_url'] = $url;
            if ( in_array( $link['type'] ?? '', [ 'OFFICIAL', 'INFO' ], true ) && $result['official_site'] === '' ) {
                $result['official_site'] = $url;
            }
        }
        return $result;
    }

    private function parse_staff( array $edges ): array {
        $staff = [];
        foreach ( $edges as $edge ) {
            $node = $edge['node'] ?? [];
            if ( empty( $node['id'] ) ) continue;
            $staff[] = [
                'id'     => (int) $node['id'],
                'name'   => $node['name']['full']   ?? '',
                'native' => $node['name']['native'] ?? '',
                'role'   => $edge['role']           ?? '',
                'image'  => $node['image']['large'] ?? '',
                'source' => 'anilist',
            ];
        }
        return $staff;
    }

    private function parse_cast( array $edges ): array {
        $cast = [];
        foreach ( $edges as $edge ) {
            $node = $edge['node'] ?? [];
            if ( empty( $node['id'] ) ) continue;
            $vas = [];
            foreach ( $edge['voiceActors'] ?? [] as $va ) {
                $vas[] = [
                    'id'     => (int) ( $va['id'] ?? 0 ),
                    'name'   => $va['name']['full']   ?? '',
                    'native' => $va['name']['native'] ?? '',
                    'image'  => $va['image']['large'] ?? '',
                ];
            }
            $cast[] = [
                'id'           => (int) $node['id'],
                'name'         => $node['name']['full']   ?? '',
                'native'       => $node['name']['native'] ?? '',
                'role'         => $edge['role']           ?? '',
                'image'        => $node['image']['large'] ?? '',
                'voice_actors' => $vas,
                'source'       => 'anilist',
            ];
        }
        return $cast;
    }

    private function parse_relations( array $edges ): array {
        $relations = [];
        foreach ( $edges as $edge ) {
            $node = $edge['node'] ?? [];
            if ( empty( $node['id'] ) ) continue;
            $relations[] = [
                'id'            => (int) $node['id'],
                'type'          => $node['type']            ?? '',
                'relation_type' => $edge['relationType']    ?? '',
                'title'         => $node['title']['romaji'] ?? '',
            ];
        }
        return $relations;
    }

    private function clean_synopsis( string $text ): string {
        $text = preg_replace( '/<br\s*\/?>/i', "\n", $text );
        $text = wp_strip_all_tags( $text );
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = preg_replace( '/\(Source:.*?\)/si', '', $text );
        $text = preg_replace( '/\[Written by.*?\]/si', '', $text );
        return trim( $text );
    }

    // =========================================================================
    // PUBLIC – 公開包裝（供 AJAX 直接呼叫）
    // =========================================================================

    public function fetch_bgm_data_public( int $bangumi_id ): array|WP_Error {
        return $this->get_bangumi_data( $bangumi_id );
    }

    public function get_bgm_staff_public( int $bangumi_id ): array {
        return $this->get_bgm_staff( $bangumi_id );
    }

    public function get_bgm_chars_public( int $bangumi_id ): array {
        return $this->get_bgm_chars( $bangumi_id );
    }

    public function clean_synopsis_public( string $text ): string {
        return $this->clean_synopsis( $text );
    }
}

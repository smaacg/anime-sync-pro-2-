<?php
/**
 * 檔案名稱: includes/class-api-handler.php
 *
 * ACB – 新增 get_core_anime_data()：只打 AniList + Bangumi subject，
 *       不打 Bangumi staff/chars/episodes、Jikan、Wikipedia、AnimeThemes，
 *       目標單部執行時間 < 15 秒，徹底解決匯入 AJAX 超時問題。
 *       get_full_anime_data() 保留供 Cron 補抓使用。
 *       fetch_wikipedia_url() 所有 timeout 從 10s 降至 5s。
 *       fetch_animethemes() timeout 從 15s 降至 8s。
 *
 * ACD – 新增 get_series_tree()、fetch_anilist_popularity()。
 *       定義 SERIES_RELATION_TYPES 常數。
 *       新增輔助方法：find_series_root()、expand_series_tree()、
 *       fetch_anilist_relations()、fetch_anilist_node_data()。
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

    /** ACD – 系列關係類型（用於 get_series_tree 展開） */
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
    // PUBLIC – 核心匯入（ACB 新增）
    // 只打 AniList + Bangumi subject，目標 < 15 秒
    // =========================================================================

    public function get_core_anime_data( int $anilist_id, int $post_id = 0, ?int $bangumi_id = null ): array|WP_Error {

        // 1. AniList
        $anilist_raw = $this->fetch_anilist_data( $anilist_id );
        if ( is_wp_error( $anilist_raw ) ) return $anilist_raw;

        $media = $anilist_raw['data']['Media'] ?? null;
        if ( empty( $media ) ) {
            return new WP_Error( 'anilist_empty', "AniList returned no data for ID {$anilist_id}." );
        }

        // 2. 基本欄位
        $mal_id         = isset( $media['idMal'] ) && $media['idMal'] !== null ? (int) $media['idMal'] : null;
        $title_romaji   = $media['title']['romaji']  ?? '';
        $title_english  = $media['title']['english'] ?? '';
        $title_native   = $media['title']['native']  ?? '';
        $season_year    = $media['seasonYear']        ?? 0;
        $season         = $media['season']            ?? '';
        $episodes       = (int) ( $media['episodes'] ?? 0 );
        $external_links = $media['externalLinks']     ?? [];

        // 3. Bangumi ID 解析
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

        // 4. Bangumi subject（只打一次，不打 staff/chars/episodes）
        $bgm_data = null;
        if ( $bangumi_id && $bangumi_id > 0 ) {
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $result = $this->get_bangumi_data( $bangumi_id );
            if ( ! is_wp_error( $result ) && is_array( $result ) ) {
                $bgm_data = $result;
            }
        }

        // 5. 中文標題
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

        // 6. 簡介
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

        // 7. 評分（跳過 Jikan）
        $score_anilist = isset( $media['averageScore'] ) ? (int) $media['averageScore'] : 0;
        $score_bangumi = 0;
        if ( $bgm_data ) {
            $raw = $bgm_data['rating']['score'] ?? $bgm_data['score'] ?? null;
            if ( $raw !== null ) $score_bangumi = (int) round( (float) $raw * 10 );
        }

        // 8. 製作公司
        $studios = [];
        foreach ( $media['studios']['nodes'] ?? [] as $studio ) {
            if ( ! empty( $studio['name'] ) ) $studios[] = $studio['name'];
        }

        // 9. 日期
        $start_date = $this->parse_fuzzy_date( $media['startDate'] ?? [] );
        $end_date   = $this->parse_fuzzy_date( $media['endDate']   ?? [] );

        // 10. 串流 + 外部連結
        $streaming    = $this->parse_streaming_links( $external_links );
        $parsed_links = $this->parse_external_links( $external_links );

        // 11. Staff / Cast / Relations（只用 AniList）
        $staff     = $this->parse_staff( $media['staff']['edges']         ?? [] );
        $cast      = $this->parse_cast(  $media['characters']['edges']    ?? [] );
        $relations = $this->parse_relations( $media['relations']['edges'] ?? [] );

        // 12. Trailer
        $trailer_url = '';
        if ( ! empty( $media['trailer'] ) ) {
            $t_id   = $media['trailer']['id']  ?? '';
            $t_site = $media['trailer']['site'] ?? '';
            if ( $t_id !== '' && strtolower( $t_site ) === 'youtube' ) {
                $trailer_url = "https://www.youtube.com/watch?v={$t_id}";
            }
        }

        // 13. Next Airing
        $next_airing = null;
        if ( ! empty( $media['nextAiringEpisode'] ) ) {
            $next_airing = [
                'airingAt' => $media['nextAiringEpisode']['airingAt'] ?? 0,
                'episode'  => $media['nextAiringEpisode']['episode']  ?? 0,
            ];
        }

        // 14. Tags
        $anime_tags = [];
        foreach ( $media['tags'] ?? [] as $tag ) {
            if ( ! empty( $tag['isMediaSpoiler'] ) ) continue;
            if ( ! empty( $tag['name'] ) ) $anime_tags[] = $tag['name'];
        }

        // 15. 組裝（Wikipedia/Themes/Episodes/MAL 留空，Cron 補）
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
    // PUBLIC – 補抓第二段資料（ACB 新增）
    // =========================================================================

    public function enrich_anime_data( int $post_id ): array|WP_Error {

        $anilist_id    = (int) get_post_meta( $post_id, 'anime_anilist_id', true );
        $bangumi_id    = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );
        $mal_id        = (int) get_post_meta( $post_id, 'anime_mal_id',     true );
        $title_chinese = (string) get_post_meta( $post_id, 'anime_title_chinese', true );
        $title_native  = (string) get_post_meta( $post_id, 'anime_title_native',  true );
        $title_romaji  = (string) get_post_meta( $post_id, 'anime_title_romaji',  true );
        $title_english = (string) get_post_meta( $post_id, 'anime_title_english', true );

        if ( ! $anilist_id ) {
            return new WP_Error( 'missing_anilist_id', "Post {$post_id} has no anime_anilist_id." );
        }

        $enriched = [];

        // Bangumi staff + chars + episodes
        if ( $bangumi_id > 0 ) {
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_staff = $this->get_bgm_staff( $bangumi_id );
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_chars = $this->get_bgm_chars( $bangumi_id );

            $existing_staff = json_decode( get_post_meta( $post_id, 'anime_staff_json', true ) ?: '[]', true );
            $existing_cast  = json_decode( get_post_meta( $post_id, 'anime_cast_json',  true ) ?: '[]', true );

            if ( ! empty( $bgm_staff ) ) {
                $enriched['anime_staff_json'] = wp_json_encode( array_merge( $existing_staff, $bgm_staff ), JSON_UNESCAPED_UNICODE );
            }
            if ( ! empty( $bgm_chars ) ) {
                $enriched['anime_cast_json'] = wp_json_encode( array_merge( $existing_cast, $bgm_chars ), JSON_UNESCAPED_UNICODE );
            }

            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $bgm_episodes = $this->fetch_bgm_episodes( $bangumi_id );
            if ( ! empty( $bgm_episodes ) ) {
                $enriched['anime_episodes_json'] = wp_json_encode( $bgm_episodes, JSON_UNESCAPED_UNICODE );
            }
        }

        // MAL score
        if ( $mal_id > 0 ) {
            $score_mal = $this->fetch_mal_score( $mal_id );
            if ( $score_mal > 0 ) $enriched['anime_score_mal'] = $score_mal;
        }

        // Wikipedia
        $wiki_url = $this->fetch_wikipedia_url( $title_chinese, $title_native, $title_romaji, $title_english );
        if ( $wiki_url !== '' ) $enriched['anime_wikipedia_url'] = $wiki_url;

        // AnimeThemes
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
    // PUBLIC – 完整匯入（保留，供 Cron 全量同步使用）
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
        $score_mal = $this->fetch_mal_score( $mal_id );

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

        if ( ! empty( $bgm_staff ) && ! is_wp_error( $bgm_staff ) ) $staff = array_merge( $staff, $bgm_staff );
        if ( ! empty( $bgm_chars ) && ! is_wp_error( $bgm_chars ) ) $cast  = array_merge( $cast,  $bgm_chars );

        $trailer_url = '';
        if ( ! empty( $media['trailer'] ) ) {
            $t_id   = $media['trailer']['id']  ?? '';
            $t_site = $media['trailer']['site'] ?? '';
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
    // PUBLIC – 系列樹（ACD 新增）
    // 1. 由任意作品 ID 向上追溯 PREQUEL，找到系列根源
    // 2. 從根源 BFS 展開完整樹（SEQUEL/SIDE_STORY/SPIN_OFF/ALTERNATIVE/PARENT）
    // 3. 每個節點標記 imported / post_id / edit_url
    // 返回：[ 'root' => [...], 'nodes' => [...], 'series_name' => '...' ]
    // =========================================================================

    public function get_series_tree( int $anilist_id ): array|WP_Error {

        $cache_key = 'anime_sync_series_tree_' . $anilist_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        // 1. 找根源
        $root_id = $this->find_series_root( $anilist_id );
        if ( is_wp_error( $root_id ) ) return $root_id;

        // 2. BFS 展開
        $nodes = $this->expand_series_tree( $root_id );
        if ( is_wp_error( $nodes ) ) return $nodes;

        // 3. 系列名稱：優先取根源的中文或 Romaji 標題，去掉季數後綴
        $series_name = '';
        foreach ( $nodes as $node ) {
            if ( (int) $node['anilist_id'] === $root_id ) {
                $series_name = $node['title_chinese'] ?: $node['title_romaji'] ?: '';
                // 移除常見季數標記，例如「第二季」「Season 2」「2nd Season」
                $series_name = preg_replace( '/[\s：:]*(\d+(?:st|nd|rd|th)?[\s]*[Ss]eason|第[一二三四五六七八九十\d]+[季期]|[Ss]\d+).*$/u', '', $series_name );
                $series_name = trim( $series_name );
                break;
            }
        }

        $result = [
            'root_id'     => $root_id,
            'series_name' => $series_name,
            'nodes'       => $nodes,
        ];

        set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
        return $result;
    }

    // =========================================================================
    // PUBLIC – AniList 人氣排行（ACD 新增）
    // 每頁 50 筆，標記 imported / post_id
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
            $al_id    = (int) ( $media['id'] ?? 0 );
            $post_id  = $this->find_existing_post( $al_id );
            $items[]  = [
                'anilist_id'    => $al_id,
                'title_romaji'  => $media['title']['romaji']  ?? '',
                'title_native'  => $media['title']['native']  ?? '',
                'cover_image'   => $media['coverImage']['large'] ?? '',
                'format'        => $media['format']     ?? '',
                'status'        => $media['status']     ?? '',
                'season_year'   => $media['seasonYear'] ?? 0,
                'popularity'    => (int) ( $media['popularity'] ?? 0 ),
                'imported'      => $post_id > 0,
                'post_id'       => $post_id,
                'edit_url'      => $post_id > 0 ? get_edit_post_link( $post_id, 'raw' ) : '',
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
    // PRIVATE – 找系列根源（遞迴向上追溯 PREQUEL）
    // =========================================================================

    private function find_series_root( int $anilist_id, array $visited = [] ): int|WP_Error {

        if ( in_array( $anilist_id, $visited, true ) ) return $anilist_id; // 防迴圈
        $visited[] = $anilist_id;

        $relations = $this->fetch_anilist_relations( $anilist_id );
        if ( is_wp_error( $relations ) ) return $anilist_id; // 找不到就以此為根

        foreach ( $relations as $rel ) {
            if ( $rel['type'] === 'PREQUEL' && ! empty( $rel['node_id'] ) ) {
                return $this->find_series_root( (int) $rel['node_id'], $visited );
            }
        }
        return $anilist_id; // 沒有 PREQUEL，就是根
    }

    // =========================================================================
    // PRIVATE – BFS 展開系列樹
    // =========================================================================

    private function expand_series_tree( int $root_id ): array|WP_Error {

        $queue   = [ $root_id ];
        $visited = [];
        $nodes   = [];

        while ( ! empty( $queue ) ) {
            $current_id = array_shift( $queue );
            if ( in_array( $current_id, $visited, true ) ) continue;
            $visited[] = $current_id;

            // 取節點基本資料
            $node_data = $this->fetch_anilist_node_data( $current_id );
            if ( is_wp_error( $node_data ) ) continue;

            // 標記是否已匯入
            $post_id   = $this->find_existing_post( $current_id );
            $node_data['imported'] = $post_id > 0;
            $node_data['post_id']  = $post_id;
            $node_data['edit_url'] = $post_id > 0 ? get_edit_post_link( $post_id, 'raw' ) : '';
            $nodes[]               = $node_data;

            // 取關係，將符合類型的加入佇列
            $relations = $this->fetch_anilist_relations( $current_id );
            if ( is_wp_error( $relations ) ) continue;

            foreach ( $relations as $rel ) {
                if (
                    in_array( $rel['type'], self::SERIES_RELATION_TYPES, true ) &&
                    ! empty( $rel['node_id'] ) &&
                    ! in_array( (int) $rel['node_id'], $visited, true )
                ) {
                    $queue[] = (int) $rel['node_id'];
                }
            }

            // 限速
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
                'type'    => $edge['relationType'] ?? '',
                'node_id' => $edge['node']['id']   ?? 0,
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

        // 嘗試從 id_mapper 拿中文標題（不打 Bangumi，快速）
        $title_chinese = '';
        $bgm_id_candidate = $this->id_mapper->get_bangumi_id( [
            'anilist_id'    => $anilist_id,
            'mal_id'        => null,
            'post_id'       => 0,
            'title_native'  => $media['title']['native']  ?? '',
            'title_romaji'  => $media['title']['romaji']  ?? '',
            'title_chinese' => '',
            'season_year'   => $media['seasonYear'] ?? 0,
            'season'        => '',
            'episodes'      => (int) ( $media['episodes'] ?? 0 ),
            'external_links'=> [],
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
    // PRIVATE – AniList 單部完整查詢
    // =========================================================================

    private function fetch_anilist_data( int $anilist_id ): array|WP_Error {
        $query = '
        query ($id: Int) {
          Media(id: $id, type: ANIME) {
            id idMal
            title { romaji english native }
            status format episodes duration source season seasonYear
            startDate { year month day }
            endDate   { year month day }
            averageScore popularity
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
              edges { role node { name { full native } } }
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

        $payload = wp_json_encode( [ 'query' => $query, 'variables' => [ 'id' => $anilist_id ] ] );
        $args    = [
            'timeout' => 20,
            'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'body'    => $payload,
        ];

        $response = wp_remote_post( self::ANILIST_ENDPOINT, $args );
        if ( is_wp_error( $response ) ) return $response;

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response, 'anilist' );
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
    // PRIVATE – MAL Score
    // =========================================================================

    private function fetch_mal_score( ?int $mal_id ): float {
        if ( ! $mal_id || $mal_id <= 0 ) return 0.0;

        $cache_key = 'anime_sync_mal_score_' . $mal_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (float) $cached;

        $this->rate_limiter->wait_if_needed( 'jikan' );
        $response = wp_remote_get( self::JIKAN_ANIME_URL . $mal_id, [
            'timeout'    => 10,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) return 0.0;
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code === 429 ) {
            sleep( 2 );
            $response = wp_remote_get( self::JIKAN_ANIME_URL . $mal_id, [
                'timeout'    => 10,
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
    // PRIVATE – Wikipedia（ACB：timeout 從 10s 降至 5s）
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
                'action' => 'query', 'titles' => $title, 'format' => 'json',
                'prop' => 'info', 'inprop' => 'url', 'redirects' => '1', 'formatversion' => '2',
            ], self::WIKI_ZH_API );

            $response = wp_remote_get( $url, [
                'timeout'    => 5,
                'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
                'headers'    => [ 'Accept' => 'application/json' ],
            ] );

            if ( is_wp_error( $response ) ) { set_transient( $cache_key, '', 7 * DAY_IN_SECONDS ); continue; }
            if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) { set_transient( $cache_key, '', 7 * DAY_IN_SECONDS ); continue; }

            $data  = json_decode( wp_remote_retrieve_body( $response ), true );
            $pages = $data['query']['pages'] ?? [];

            foreach ( $pages as $page ) {
                if ( ! empty( $page['missing'] ) ) { set_transient( $cache_key, '', 7 * DAY_IN_SECONDS ); continue 2; }
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
            if ( $cached !== false ) { if ( $cached !== '' ) return (string) $cached; continue; }

            $rest_url = self::WIKI_EN_REST . rawurlencode( str_replace( ' ', '_', trim( $title ) ) );
            $response = wp_remote_get( $rest_url, [
                'timeout'    => 5,
                'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
                'headers'    => [ 'Accept' => 'application/json' ],
            ] );

            if ( is_wp_error( $response ) ) { set_transient( $cache_key, '', 7 * DAY_IN_SECONDS ); continue; }
            $code = (int) wp_remote_retrieve_response_code( $response );
            if ( $code === 404 ) { set_transient( $cache_key, '', 7 * DAY_IN_SECONDS ); continue; }
            if ( $code !== 200 ) continue;

            $body     = json_decode( wp_remote_retrieve_body( $response ), true );
            $wiki_url = $body['content_urls']['desktop']['page'] ?? '';
            if ( ( $body['type'] ?? '' ) === 'disambiguation' || $wiki_url === '' ) {
                set_transient( $cache_key, '', 7 * DAY_IN_SECONDS ); continue;
            }
            set_transient( $cache_key, $wiki_url, 30 * DAY_IN_SECONDS );
            return $wiki_url;
        }
        return '';
    }

    // =========================================================================
    // PRIVATE – Bangumi helpers
    // =========================================================================

    private function get_bangumi_data( int $bgm_id ): array|WP_Error {
        $url      = self::BGM_SUBJECT_URL . $bgm_id;
        $response = wp_remote_get( $url, [
            'timeout'    => 12,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );
        if ( is_wp_error( $response ) ) return $response;
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code === 429 ) {
            $this->rate_limiter->handle_rate_limit_error( $response, 'bangumi' );
            $this->rate_limiter->wait_if_needed( 'bangumi' );
            $response = wp_remote_get( $url, [
                'timeout'    => 12,
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
        $response = wp_remote_get( self::BGM_SUBJECT_URL . $bgm_id . '/persons', [
            'timeout'    => 12,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) return [];
        $result = [];
        foreach ( $data as $person ) {
            $name = $person['name'] ?? '';
            foreach ( $person['jobs'] ?? [] as $job ) {
                $result[] = [ 'name' => $name, 'role' => $job, 'type' => 'staff' ];
            }
        }
        return $result;
    }

    private function get_bgm_chars( int $bgm_id ): array {
        $response = wp_remote_get( self::BGM_SUBJECT_URL . $bgm_id . '/characters', [
            'timeout'    => 12,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) return [];
        $result = [];
        foreach ( $data as $char ) {
            $actors = [];
            foreach ( $char['actors'] ?? [] as $actor ) $actors[] = $actor['name'] ?? '';
            $result[] = [
                'name'   => $char['name']     ?? '',
                'role'   => $char['relation'] ?? '',
                'actors' => $actors,
                'type'   => 'character',
            ];
        }
        return $result;
    }

    public function fetch_bgm_episodes( int $bgm_id ): array {
        $cache_key = 'anime_sync_bgm_eps_' . $bgm_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (array) $cached;

        $url = add_query_arg( [
            'subject_id' => $bgm_id, 'type' => 0, 'limit' => 200, 'offset' => 0,
        ], self::BGM_EPISODES_URL );

        $response = wp_remote_get( $url, [
            'timeout'    => 12,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return [];

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
    // PRIVATE – AnimeThemes（ACB：timeout 從 15s 降至 8s）
    // =========================================================================

    private function fetch_animethemes( ?int $mal_id ): array {
        $empty = [ 'themes' => [], 'slug' => '' ];
        if ( ! $mal_id || $mal_id <= 0 ) return $empty;

        $this->rate_limiter->wait_if_needed( 'animethemes' );
        $url = self::ANIMETHEMES_URL
            . '?filter[has]=resources&filter[site]=MyAnimeList'
            . '&filter[external_id]=' . (int) $mal_id
            . '&include=animethemes.song,resources&page[number]=1';

        $response = wp_remote_get( $url, [
            'timeout'    => 8,
            'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
            'headers'    => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return $empty;

        $data  = json_decode( wp_remote_retrieve_body( $response ), true );
        $anime = $data['anime'][0] ?? null;
        if ( ! $anime || empty( $anime['animethemes'] ) ) return $empty;

        $slug   = $anime['slug'] ?? '';
        $themes = [];
        foreach ( $anime['animethemes'] as $theme ) {
            $type     = $theme['type']     ?? '';
            $sequence = $theme['sequence'] ?? '';
            $themes[] = [
                'type'       => $type,
                'title'      => $theme['song']['title'] ?? '',
                'theme_slug' => $type . ( $sequence ?: '' ),
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
        if ( ! $m ) return (string) $y;
        if ( ! $d ) return sprintf( '%04d-%02d', $y, $m );
        return sprintf( '%04d-%02d-%02d', $y, $m, $d );
    }

    private function parse_streaming_links( array $external_links ): array {
        $streaming_sites = [
            'crunchyroll', 'funimation', 'netflix', 'hulu', 'amazon',
            'hidive', 'disney plus', 'bilibili', 'youtube', 'wakanim',
            'ani-one', 'viu', 'kktv', 'friday', 'catchplay', 'wetv',
            'bahamut', 'muse', 'animelab',
        ];
        $result = [];
        foreach ( $external_links as $link ) {
            $site = strtolower( $link['site'] ?? '' );
            $type = strtolower( $link['type'] ?? '' );
            $url  = $link['url'] ?? '';
            if ( $url === '' ) continue;
            if ( $type === 'streaming' || in_array( $site, $streaming_sites, true ) ) {
                $result[] = [ 'site' => $link['site'] ?? $site, 'url' => $url ];
            }
        }
        return $result;
    }

    private function parse_external_links( array $external_links ): array {
        $result = [ 'official_site' => '', 'twitter_url' => '' ];
        foreach ( $external_links as $link ) {
            $site = strtolower( $link['site'] ?? '' );
            $url  = $link['url'] ?? '';
            if ( $url === '' ) continue;
            if ( $site === 'official site' || $site === 'official' ) {
                $result['official_site'] = $url;
            } elseif ( $site === 'twitter' || $site === 'x' ) {
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
                'type'        => 'staff',
            ];
        }
        return $result;
    }

    private function parse_cast( array $edges ): array {
        $result = [];
        foreach ( $edges as $edge ) {
            $va_list = [];
            foreach ( $edge['voiceActors'] ?? [] as $va ) {
                $va_list[] = $va['name']['full'] ?? '';
            }
            $result[] = [
                'name'        => $edge['node']['name']['full']   ?? '',
                'name_native' => $edge['node']['name']['native'] ?? '',
                'role'        => $edge['role'] ?? '',
                'voice_actors'=> $va_list,
                'type'        => 'character',
            ];
        }
        return $result;
    }

    private function parse_relations( array $edges ): array {
        $result = [];
        foreach ( $edges as $edge ) {
            $node    = $edge['node'] ?? [];
            $result[] = [
                'id'           => $node['id']                    ?? 0,
                'type'         => $edge['relationType']           ?? '',
                'title'        => $node['title']['romaji']        ?? '',
                'title_native' => $node['title']['native']        ?? '',
                'format'       => $node['format']                 ?? '',
                'cover_image'  => $node['coverImage']['large']    ?? '',
            ];
        }
        return $result;
    }

    private function clean_synopsis( string $text ): string {
        // 移除 HTML 標籤
        $text = wp_strip_all_tags( $text );
        // 移除 AniList 來源標記，例如 (Source: MU)
        $text = preg_replace( '/\(Source:[^\)]*\)/i', '', $text );
        // 移除 Bangumi note 標記
        $text = preg_replace( '/\[(?:url|b|i|s|img)[^\]]*\][^\[]*\[\/(?:url|b|i|s|img)\]/i', '', $text );
        return trim( $text );
    }

    // =========================================================================
    // PRIVATE – 查找已存在的文章
    // =========================================================================

    private function find_existing_post( int $anilist_id ): int {
        if ( $anilist_id <= 0 ) return 0;

        $cache_key = 'anime_sync_existing_post_' . $anilist_id;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return (int) $cached;

        $posts = get_posts( [
            'post_type'   => 'anime',
            'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
            'meta_key'    => 'anime_anilist_id',
            'meta_value'  => $anilist_id,
            'numberposts' => 1,
            'fields'      => 'ids',
        ] );

        $post_id = ! empty( $posts ) ? (int) $posts[0] : 0;
        set_transient( $cache_key, $post_id, HOUR_IN_SECONDS );
        return $post_id;
    }

    // =========================================================================
    // PUBLIC WRAPPERS – 供 AJAX Bangumi Resync 使用（ABO）
    // =========================================================================

    public function fetch_bgm_data_public( int $bgm_id ): array|WP_Error {
        return $this->get_bangumi_data( $bgm_id );
    }

    public function get_bgm_staff_public( int $bgm_id ): array {
        return $this->get_bgm_staff( $bgm_id );
    }

    public function get_bgm_chars_public( int $bgm_id ): array {
        return $this->get_bgm_chars( $bgm_id );
    }

    public function clean_synopsis_public( string $text ): string {
        return $this->clean_synopsis( $text );
    }
}

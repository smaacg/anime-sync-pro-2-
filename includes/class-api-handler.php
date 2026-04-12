<?php
/**
 * API Handler — AniList & Bangumi 資料抓取
 *
 * ✅ Bug P  修正：新增 studios 查詢與回傳
 * ✅ Bug Q  修正：新增 themes 欄位（空陣列預留）
 * ✅ Bug R  修正：新增 externalLinks（streaming）查詢與解析
 * ✅ Bug S  修正：Bangumi 評分路徑容錯
 * ✅ Bug T  修正：中文簡介優先使用 Bangumi summary
 * ✅ Bug U  修正：新增 duration, startDate, endDate 欄位
 * ✅ Bug Z  修正：整合 Anime_Sync_Rate_Limiter，防止 API 超速
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_API_Handler {

    const ANILIST_ENDPOINT = 'https://graphql.anilist.co';
    const BANGUMI_ENDPOINT = 'https://api.bgm.tv';

    private $id_mapper;
    private $converter;
    private Anime_Sync_Rate_Limiter $rate_limiter; // ✅ Bug Z

    public function __construct( $id_mapper = null, $converter = null ) {
        $this->id_mapper    = $id_mapper;
        $this->converter    = $converter;
        $this->rate_limiter = new Anime_Sync_Rate_Limiter(); // ✅ Bug Z
    }

    // =========================================================================
    // 公開入口
    // =========================================================================

    public function get_all_data_from_input( int $input_id, string $input_type = 'auto', ?int $bangumi_id = null ): array {
        return $this->get_full_anime_data( $input_id, $bangumi_id );
    }

    public function get_full_anime_data( int $anilist_id, $bangumi_id = null ): array {

        // ✅ Bug Z：AniList 請求前等待速率限制
        $this->rate_limiter->wait_if_needed( 'anilist' );

        $al_res = $this->fetch_anilist_data( $anilist_id );

        if ( ! $al_res['success'] ) {
            return $al_res;
        }

        $al = $al_res['data'];
        if ( empty( $al ) ) {
            return [ 'success' => false, 'message' => 'AniList 找不到 ID: ' . $anilist_id ];
        }

        $mal_id = $al['idMal'] ?? null;
        $bgm_id = $bangumi_id;

        if ( $this->id_mapper && method_exists( $this->id_mapper, 'resolve_ids' ) ) {
            $ids    = $this->id_mapper->resolve_ids( $anilist_id, $mal_id, $bangumi_id );
            $bgm_id = $ids['bangumi_id'] ?? $bangumi_id;
        }

        // ✅ Bug Z：Bangumi 請求前等待速率限制
        $this->rate_limiter->wait_if_needed( 'bangumi' );
        $bgm_data = $this->get_bangumi_data( $bgm_id ) ?? [];

        // ── Relations ────────────────────────────────────────────
        $relations = $this->parse_relations( $al['relations']['edges'] ?? [] );

        // ── Studios (Bug P) ──────────────────────────────────────
        $studios_nodes = $al['studios']['nodes'] ?? [];
        $studios_names = [];
        foreach ( $studios_nodes as $node ) {
            // 優先取動畫製作公司（isAnimationStudio = true）
            if ( ! empty( $node['isAnimationStudio'] ) && ! empty( $node['name'] ) ) {
                $studios_names[] = $node['name'];
            }
        }
        // 若無 isAnimationStudio，退而求其次取全部
        if ( empty( $studios_names ) ) {
            foreach ( $studios_nodes as $node ) {
                if ( ! empty( $node['name'] ) ) {
                    $studios_names[] = $node['name'];
                }
            }
        }
        $anime_studios = implode( ', ', $studios_names );

        // ── Streaming Links (Bug R) ──────────────────────────────
        $external_links = $al['externalLinks'] ?? [];
        $streaming      = [];
        foreach ( $external_links as $link ) {
            // type = STREAMING 為串流平台
            if ( isset( $link['type'] ) && strtoupper( $link['type'] ) === 'STREAMING' ) {
                $streaming[] = [
                    'site'  => $link['site']     ?? '',
                    'url'   => $link['url']       ?? '',
                    'icon'  => $link['icon']      ?? '',
                    'color' => $link['color']     ?? '',
                    'lang'  => $link['language']  ?? '',
                ];
            }
        }

        // ── Dates (Bug U) ────────────────────────────────────────
        $start_date = $this->parse_fuzzy_date( $al['startDate'] ?? [] );
        $end_date   = $this->parse_fuzzy_date( $al['endDate']   ?? [] );

        // ── Duration (Bug U) ─────────────────────────────────────
        $duration = (int) ( $al['duration'] ?? 0 );

        // ── Synopsis (Bug T) ─────────────────────────────────────
        // 優先 Bangumi summary（已是中文），fallback 至 AniList description（轉繁）
        if ( ! empty( $bgm_data['summary'] ) ) {
            $synopsis_chinese = $this->clean_synopsis(
                $this->convert_text( $bgm_data['summary'] )
            );
        } else {
            $synopsis_chinese = $this->clean_synopsis(
                $this->convert_text( $al['description'] ?? '' )
            );
        }

        // ── Bangumi Score (Bug S) ────────────────────────────────
        // Bangumi 評分結構：rating.score（浮點），×10 後取整數儲存
        $bgm_score_raw = $bgm_data['rating']['score'] ?? 0;
        $anime_score_bangumi = $bgm_score_raw > 0
            ? (int) round( (float) $bgm_score_raw * 10 )
            : 0;

        return [
            'success'               => true,

            // IDs
            'anime_anilist_id'      => $anilist_id,
            'anime_mal_id'          => $mal_id,
            'bangumi_id'            => $bgm_id,

            // 標題
            'anime_title_chinese'   => $this->convert_text(
                ( ! empty( $bgm_data['name_cn'] ) ? $bgm_data['name_cn'] : null )
                ?? ( ! empty( $bgm_data['name'] )    ? $bgm_data['name']    : null )
                ?? $al['title']['english']
                ?? $al['title']['romaji']
                ?? ''
            ),
            'anime_title_romaji'    => $al['title']['romaji']  ?? '',
            'anime_title_english'   => $al['title']['english'] ?? '',
            'anime_title_native'    => $al['title']['native']  ?? '',

            // 基本資訊
            'anime_status'          => $al['status']     ?? '',
            'anime_type'            => $al['format']     ?? 'TV',
            'anime_episodes'        => (int) ( $al['episodes']    ?? 0 ),
            'anime_duration'        => $duration,                         // ✅ Bug U
            'anime_season'          => strtolower( $al['season'] ?? '' ),
            'anime_year'            => (int) ( $al['seasonYear']  ?? 0 ),
            'anime_source'          => $al['source']     ?? '',
            'anime_studios'         => $anime_studios,                    // ✅ Bug P

            // 日期
            'anime_start_date'      => $start_date,                       // ✅ Bug U
            'anime_end_date'        => $end_date,                         // ✅ Bug U

            // 評分
            'anime_score_anilist'   => (int) ( $al['averageScore'] ?? 0 ),
            'anime_score_bangumi'   => $anime_score_bangumi,              // ✅ Bug S
            'anime_popularity'      => (int) ( $al['popularity']   ?? 0 ),

            // 圖片
            'anime_cover_image'     => $al['coverImage']['extraLarge'] ?? '',
            'anime_banner_image'    => $al['bannerImage'] ?? '',

            // 媒體
            'anime_trailer_url'     => isset( $al['trailer']['id'] )
                ? 'https://www.youtube.com/watch?v=' . $al['trailer']['id']
                : '',

            // 簡介
            'anime_synopsis_chinese' => $synopsis_chinese,                // ✅ Bug T
            'anime_synopsis_english' => $this->clean_synopsis( $al['description'] ?? '' ),

            // JSON 欄位
            'anime_staff_json'      => wp_json_encode( $this->get_bgm_staff( $bgm_id ),  JSON_UNESCAPED_UNICODE ),
            'anime_cast_json'       => wp_json_encode( $this->get_bgm_chars( $bgm_id ),  JSON_UNESCAPED_UNICODE ),
            'anime_relations_json'  => wp_json_encode( $relations,                       JSON_UNESCAPED_UNICODE ),
            'anime_streaming'       => wp_json_encode( $streaming,                       JSON_UNESCAPED_UNICODE ), // ✅ Bug R
            'anime_themes'          => wp_json_encode( [],                               JSON_UNESCAPED_UNICODE ), // ✅ Bug Q（預留）

            // Taxonomy 用
            '_genres'               => $al['genres'] ?? [],

            'errors'                => [],
        ];
    }

    // =========================================================================
    // Bug U：日期解析輔助
    // =========================================================================

    /**
     * 將 AniList FuzzyDate { year, month, day } 轉為 Y-m-d 字串。
     *
     * @param array $date AniList FuzzyDate 陣列
     * @return string     Y-m-d 格式，資料不完整時返回空字串
     */
    private function parse_fuzzy_date( array $date ): string {
        $year  = (int) ( $date['year']  ?? 0 );
        $month = (int) ( $date['month'] ?? 0 );
        $day   = (int) ( $date['day']   ?? 0 );

        if ( $year === 0 ) {
            return '';
        }

        $month = $month ?: 1;
        $day   = $day   ?: 1;

        return sprintf( '%04d-%02d-%02d', $year, $month, $day );
    }

    // =========================================================================
    // Bug 3：清理 Synopsis
    // =========================================================================

    private function clean_synopsis( string $text ): string {
        if ( empty( $text ) ) {
            return '';
        }

        // 1. 移除 AniList spoiler 標記
        $text = preg_replace( '/~!.*?!~/s', '', $text );

        // 2. 移除來源備註
        foreach ( [ '[Written by MAL Rewrite]', '[Source:', '(Source:', 'Source:', '[Written by' ] as $marker ) {
            $pos = strpos( $text, $marker );
            if ( $pos !== false ) {
                $text = substr( $text, 0, $pos );
            }
        }

        // 3. <br> 換行
        $text = preg_replace( '/<br\s*\/?>/i', "\n", $text );

        // 4. 移除所有 HTML 標籤
        $text = wp_strip_all_tags( $text );

        // 5. 整理空白
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );
        $text = trim( $text );

        return $text;
    }

    // =========================================================================
    // 關聯作品解析
    // =========================================================================

    private function parse_relations( array $edges ): array {
        $allowed_types = [
            'PREQUEL'     => '前傳',
            'SEQUEL'      => '續集',
            'SIDE_STORY'  => '外傳',
            'ALTERNATIVE' => '替代版本',
            'SPIN_OFF'    => '衍生作品',
            'ADAPTATION'  => '改編原作',
            'SOURCE'      => '原作',
            'SUMMARY'     => '總集篇',
            'PARENT'      => '主線作品',
            'CHARACTER'   => '角色客串',
            'OTHER'       => '其他相關',
        ];

        $relations = [];
        foreach ( $edges as $edge ) {
            $type = $edge['relationType'] ?? '';
            if ( ! isset( $allowed_types[ $type ] ) ) {
                continue;
            }
            $node        = $edge['node'] ?? [];
            $relations[] = [
                'relation_type'  => $type,
                'relation_label' => $allowed_types[ $type ],
                'anilist_id'     => $node['id'] ?? 0,
                'title_chinese'  => $this->convert_text( $node['title']['native'] ?? $node['title']['romaji'] ?? '' ),
                'title_romaji'   => $node['title']['romaji'] ?? '',
                'title_native'   => $node['title']['native'] ?? '',
                'format'         => $node['format']     ?? '',
                'status'         => $node['status']     ?? '',
                'cover_image'    => $node['coverImage']['large'] ?? '',
                'episodes'       => $node['episodes']   ?? 0,
                'season_year'    => $node['seasonYear'] ?? 0,
            ];
        }

        $priority = [ 'PREQUEL' => 1, 'SEQUEL' => 2, 'SIDE_STORY' => 3, 'SPIN_OFF' => 4, 'ADAPTATION' => 5, 'SOURCE' => 6 ];
        usort( $relations, function( $a, $b ) use ( $priority ) {
            return ( $priority[ $a['relation_type'] ] ?? 99 ) <=> ( $priority[ $b['relation_type'] ] ?? 99 );
        } );

        return $relations;
    }

    // =========================================================================
    // 文字轉換
    // =========================================================================

    private function convert_text( $text ): string {
        if ( empty( $text ) ) {
            return '';
        }
        return ( $this->converter && method_exists( $this->converter, 'convert' ) )
            ? $this->converter->convert( $text )
            : $text;
    }

    // =========================================================================
    // AniList GraphQL — 完整欄位版（含 Bug P/R/U 新增欄位）
    // =========================================================================

    private function fetch_anilist_data( int $id ): array {
        $query = <<<'GQL'
        query($id: Int) {
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
                meanScore
                popularity
                coverImage { extraLarge large }
                bannerImage
                trailer { id site }
                description(asHtml: false)
                genres
                studios {
                    nodes { name isAnimationStudio }
                }
                externalLinks {
                    site url type icon color language
                }
                nextAiringEpisode { airingAt episode }
                relations {
                    edges {
                        relationType
                        node {
                            id
                            title { romaji native }
                            format
                            status
                            episodes
                            seasonYear
                            coverImage { large }
                        }
                    }
                }
                staff(perPage: 10) {
                    edges {
                        role
                        node { name { full } }
                    }
                }
                characters(perPage: 10) {
                    edges {
                        role
                        voiceActors { name { full } languageV2 }
                        node { name { full } image { medium } }
                    }
                }
            }
        }
        GQL;

        $res = wp_remote_post( self::ANILIST_ENDPOINT, [
            'body'    => wp_json_encode( [ 'query' => $query, 'variables' => [ 'id' => $id ] ] ),
            'headers' => [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
            'timeout' => 20,
        ] );

        if ( is_wp_error( $res ) ) {
            return [ 'success' => false, 'message' => 'AniList 連線失敗：' . $res->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $res );
        if ( $code === 429 ) {
            // ✅ Bug Z：處理 429 速率限制
            $this->rate_limiter->handle_rate_limit_error(
                wp_remote_retrieve_headers( $res )->getAll(),
                'anilist'
            );
            return [ 'success' => false, 'message' => 'AniList 速率限制（429），請稍後重試' ];
        }

        if ( $code !== 200 ) {
            return [ 'success' => false, 'message' => 'AniList 回傳 HTTP ' . $code ];
        }

        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( ! empty( $body['errors'] ) ) {
            $msg = $body['errors'][0]['message'] ?? 'GraphQL 錯誤';
            return [ 'success' => false, 'message' => 'AniList GraphQL 錯誤：' . $msg ];
        }

        return [ 'success' => true, 'data' => $body['data']['Media'] ?? null ];
    }

    // =========================================================================
    // Bangumi API
    // =========================================================================

    private function get_bangumi_data( $id ): ?array {
        if ( ! $id ) {
            return null;
        }

        // ✅ Bug Z：Bangumi 請求速率限制
        $this->rate_limiter->wait_if_needed( 'bangumi' );

        $res = wp_remote_get( self::BANGUMI_ENDPOINT . "/v0/subjects/{$id}", [
            'headers' => [ 'User-Agent' => 'AnimeSyncPro/1.0 (https://github.com/smaacg/anime-sync-pro-2-)' ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
            return null;
        }

        return json_decode( wp_remote_retrieve_body( $res ), true );
    }

    private function get_bgm_staff( $id ): array {
        if ( ! $id ) {
            return [];
        }

        $this->rate_limiter->wait_if_needed( 'bangumi' );

        $res  = wp_remote_get( self::BANGUMI_ENDPOINT . "/v0/subjects/{$id}/persons", [
            'headers' => [ 'User-Agent' => 'AnimeSyncPro/1.0' ],
            'timeout' => 15,
        ] );
        $data = is_wp_error( $res ) ? [] : ( json_decode( wp_remote_retrieve_body( $res ), true ) ?? [] );

        $out = [];
        foreach ( array_slice( $data, 0, 10 ) as $p ) {
            $out[] = [
                'name' => $this->convert_text( $p['name'] ?? '' ),
                'role' => $p['relation'] ?? '',
            ];
        }
        return $out;
    }

    private function get_bgm_chars( $id ): array {
        if ( ! $id ) {
            return [];
        }

        $this->rate_limiter->wait_if_needed( 'bangumi' );

        $res  = wp_remote_get( self::BANGUMI_ENDPOINT . "/v0/subjects/{$id}/characters", [
            'headers' => [ 'User-Agent' => 'AnimeSyncPro/1.0' ],
            'timeout' => 15,
        ] );
        $data = is_wp_error( $res ) ? [] : ( json_decode( wp_remote_retrieve_body( $res ), true ) ?? [] );

        $out = [];
        foreach ( array_slice( $data, 0, 10 ) as $c ) {
            $out[] = [
                'name' => $this->convert_text( $c['name'] ?? '' ),
                'role' => $c['relation'] ?? '',
            ];
        }
        return $out;
    }
}

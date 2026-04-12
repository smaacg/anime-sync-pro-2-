<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_API_Handler {

    const ANILIST_ENDPOINT = 'https://graphql.anilist.co';
    const BANGUMI_ENDPOINT = 'https://api.bgm.tv';

    private $id_mapper;
    private $converter;

    public function __construct( $id_mapper = null, $converter = null ) {
        $this->id_mapper = $id_mapper;
        $this->converter = $converter;
    }

    public function get_all_data_from_input( int $input_id, string $input_type = 'auto', ?int $bangumi_id = null ): array {
        return $this->get_full_anime_data( $input_id, $bangumi_id );
    }

    public function get_full_anime_data( int $anilist_id, $bangumi_id = null ): array {
        $al_res = $this->fetch_anilist_data( $anilist_id );
        if ( ! $al_res['success'] ) return $al_res;

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

        $bgm_data = $this->get_bangumi_data( $bgm_id ) ?? [];

        // ── Relations（關聯作品）────────────────────────────
        $relations = $this->parse_relations( $al['relations']['edges'] ?? [] );

        return [
            'success'                => true,
            'anime_anilist_id'       => $anilist_id,
            'anime_mal_id'           => $mal_id,
            'bangumi_id'             => $bgm_id,
            'anime_title_chinese'    => $this->convert_text(
                ( ! empty( $bgm_data['name_cn'] ) ? $bgm_data['name_cn'] : null )
                ?? ( ! empty( $bgm_data['name'] )  ? $bgm_data['name']   : null )
                ?? $al['title']['english']
                ?? $al['title']['romaji']
                ?? ''
            ),
            'anime_title_romaji'     => $al['title']['romaji']          ?? '',
            'anime_title_english'    => $al['title']['english']         ?? '',
            'anime_title_native'     => $al['title']['native']          ?? '',
            'anime_status'           => $al['status']                   ?? '',
            'anime_type'             => $al['format']                   ?? 'TV',
            'anime_episodes'         => $al['episodes']                 ?? 0,
            'anime_season'           => $al['season']                   ?? '',
            'anime_year'             => $al['seasonYear']               ?? 0,
            'anime_score_anilist'    => $al['averageScore']             ?? 0,
            'anime_popularity'       => $al['popularity']               ?? 0,
            'anime_score_bangumi'    => $bgm_data['rating']['score']    ?? 0,
            'anime_cover_image'      => $al['coverImage']['extraLarge'] ?? '',
            'anime_banner_image'     => $al['bannerImage']              ?? '',
            // ✅ Bug 3 修正：清理 synopsis HTML 標籤與 AniList spoiler 標記
            'anime_synopsis_chinese' => $this->clean_synopsis(
                $this->convert_text( $al['description'] ?? '' )
            ),
            'anime_staff_json'       => json_encode( $this->get_bgm_staff( $bgm_id ), JSON_UNESCAPED_UNICODE ),
            'anime_cast_json'        => json_encode( $this->get_bgm_chars( $bgm_id ), JSON_UNESCAPED_UNICODE ),
            'anime_relations_json'   => json_encode( $relations, JSON_UNESCAPED_UNICODE ),
            '_genres'                => $al['genres'] ?? [],
            'errors'                 => [],
        ];
    }

    // ============================================================
    // Bug 3 修正：清理 AniList synopsis
    // 處理：~!spoiler!~ 標記、HTML 標籤、來源備註、多餘空白
    // ============================================================
    private function clean_synopsis( string $text ): string {
        if ( empty( $text ) ) return '';

        // 1. 移除 AniList spoiler 標記區塊：~!...!~
        $text = preg_replace( '/~!.*?!~/s', '', $text );

        // 2. 移除來源備註（AniList 常見格式）
        foreach ( [ '[Written by MAL Rewrite]', '[Source:', '(Source:', 'Source:', '[Written by' ] as $marker ) {
            $pos = strpos( $text, $marker );
            if ( $pos !== false ) {
                $text = substr( $text, 0, $pos );
            }
        }

        // 3. 將 <br> / <br /> 換成換行，保留段落結構
        $text = preg_replace( '/<br\s*\/?>/i', "\n", $text );

        // 4. 移除所有其他 HTML 標籤（保留純文字）
        $text = wp_strip_all_tags( $text );

        // 5. 清理多餘空白與換行
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );
        $text = trim( $text );

        return $text;
    }

    // ============================================================
    // 解析關聯作品
    // ============================================================
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
            if ( ! isset( $allowed_types[ $type ] ) ) continue;

            $node        = $edge['node'] ?? [];
            $relations[] = [
                'relation_type'  => $type,
                'relation_label' => $allowed_types[ $type ],
                'anilist_id'     => $node['id']                        ?? 0,
                'title_chinese'  => $this->convert_text(
                    $node['title']['native'] ?? $node['title']['romaji'] ?? ''
                ),
                'title_romaji'   => $node['title']['romaji']           ?? '',
                'title_native'   => $node['title']['native']           ?? '',
                'format'         => $node['format']                    ?? '',
                'status'         => $node['status']                    ?? '',
                'cover_image'    => $node['coverImage']['large']       ?? '',
                'episodes'       => $node['episodes']                  ?? 0,
                'season_year'    => $node['seasonYear']                ?? 0,
            ];
        }

        // 排序優先顯示：前傳、續集、外傳
        $priority = [
            'PREQUEL'    => 1, 'SEQUEL'     => 2, 'SIDE_STORY'  => 3,
            'SPIN_OFF'   => 4, 'ADAPTATION' => 5, 'SOURCE'      => 6,
        ];
        usort( $relations, function( $a, $b ) use ( $priority ) {
            $pa = $priority[ $a['relation_type'] ] ?? 99;
            $pb = $priority[ $b['relation_type'] ] ?? 99;
            return $pa <=> $pb;
        });

        return $relations;
    }

    // ============================================================
    // 文字轉換（簡 → 繁）
    // ============================================================
    private function convert_text( $text ): string {
        if ( empty( $text ) ) return '';
        return ( $this->converter && method_exists( $this->converter, 'convert' ) )
            ? $this->converter->convert( $text )
            : $text;
    }

    // ============================================================
    // 抓取 AniList GraphQL 資料
    // ============================================================
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
                description(asHtml: false)
                season
                seasonYear
                averageScore
                popularity
                coverImage { extraLarge large }
                bannerImage
                genres
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
            }
        }
        GQL;

        $res = wp_remote_post(
            self::ANILIST_ENDPOINT,
            [
                'body'    => json_encode( [ 'query' => $query, 'variables' => [ 'id' => $id ] ] ),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'timeout' => 20,
            ]
        );

        if ( is_wp_error( $res ) ) {
            return [ 'success' => false, 'message' => 'AniList 連線失敗：' . $res->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $res );
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

    // ============================================================
    // 抓取 Bangumi 主題資料
    // ============================================================
    private function get_bangumi_data( $id ): ?array {
        if ( ! $id ) return null;
        $res = wp_remote_get(
            self::BANGUMI_ENDPOINT . "/v0/subjects/{$id}",
            [
                'headers' => [ 'User-Agent' => 'AnimeSyncPro/1.0 (https://dev.weixiaoacg.com)' ],
                'timeout' => 15,
            ]
        );
        if ( is_wp_error( $res ) ) return null;
        if ( wp_remote_retrieve_response_code( $res ) !== 200 ) return null;
        return json_decode( wp_remote_retrieve_body( $res ), true );
    }

    // ============================================================
    // 抓取 Bangumi Staff
    // ============================================================
    private function get_bgm_staff( $id ): array {
        if ( ! $id ) return [];
        $res  = wp_remote_get(
            self::BANGUMI_ENDPOINT . "/v0/subjects/{$id}/persons",
            [
                'headers' => [ 'User-Agent' => 'AnimeSyncPro/1.0 (https://dev.weixiaoacg.com)' ],
                'timeout' => 15,
            ]
        );
        $data = is_wp_error( $res ) ? [] : ( json_decode( wp_remote_retrieve_body( $res ), true ) ?? [] );
        $out  = [];
        foreach ( array_slice( $data, 0, 10 ) as $p ) {
            $out[] = [
                'name' => $this->convert_text( $p['name'] ?? '' ),
                'role' => $p['relation'] ?? '',
            ];
        }
        return $out;
    }

    // ============================================================
    // 抓取 Bangumi 角色
    // ============================================================
    private function get_bgm_chars( $id ): array {
        if ( ! $id ) return [];
        $res  = wp_remote_get(
            self::BANGUMI_ENDPOINT . "/v0/subjects/{$id}/characters",
            [
                'headers' => [ 'User-Agent' => 'AnimeSyncPro/1.0 (https://dev.weixiaoacg.com)' ],
                'timeout' => 15,
            ]
        );
        $data = is_wp_error( $res ) ? [] : ( json_decode( wp_remote_retrieve_body( $res ), true ) ?? [] );
        $out  = [];
        foreach ( array_slice( $data, 0, 10 ) as $c ) {
            $out[] = [
                'name' => $this->convert_text( $c['name'] ?? '' ),
                'role' => $c['relation'] ?? '',
            ];
        }
        return $out;
    }
}

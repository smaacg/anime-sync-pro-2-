<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_API_Handler {

    const ANILIST_ENDPOINT  = 'https://graphql.anilist.co';   // ✅ 修正：原本是 anilist.co（錯誤）
    const BANGUMI_ENDPOINT  = 'https://api.bgm.tv';           // ✅ 修正：原本是 bgm.tv（錯誤）

    private $id_mapper;
    private $converter;

    public function __construct( $id_mapper = null, $converter = null ) {
        $this->id_mapper = $id_mapper;
        $this->converter = $converter;
    }

    public function get_all_data_from_input( int $input_id, string $input_type = 'auto', ?int $bangumi_id = null ) {
        return $this->get_full_anime_data( $input_id, $bangumi_id );
    }

    public function get_full_anime_data( int $anilist_id, $bangumi_id = null ): array {
        $al_res = $this->fetch_anilist_data( $anilist_id );
        if ( ! $al_res['success'] ) return $al_res;

        $al = $al_res['data'];

        // ✅ 修正：$al 可能是 null（AniList 找不到該 ID）
        if ( empty( $al ) ) {
            return [ 'success' => false, 'message' => 'AniList 找不到 ID: ' . $anilist_id ];
        }

        $mal_id = $al['idMal'] ?? null;

        $bgm_id = $bangumi_id;
        if ( $this->id_mapper && method_exists( $this->id_mapper, 'resolve_ids' ) ) {
            $ids    = $this->id_mapper->resolve_ids( $anilist_id, $mal_id, $bangumi_id );
            $bgm_id = $ids['bangumi_id'] ?? $bangumi_id;
        }

        // ✅ 修正：bgm_data 可能是 null，統一處理為空陣列
        $bgm_data = $this->get_bangumi_data( $bgm_id ) ?? [];

        return [
            'success'                => true,
            'anime_anilist_id'       => $anilist_id,
            'anime_mal_id'           => $mal_id,
            'bangumi_id'             => $bgm_id,
            // ✅ 修正：bgm_data 為空時不會再報 Warning
            'anime_title_chinese'    => $this->convert_text(
                ( ! empty( $bgm_data['name_cn'] ) ? $bgm_data['name_cn'] : null )
                ?? ( ! empty( $bgm_data['name'] )    ? $bgm_data['name']    : null )
                ?? $al['title']['english']
                ?? $al['title']['romaji']
                ?? ''
            ),
            'anime_title_romaji'     => $al['title']['romaji']  ?? '',
            'anime_title_english'    => $al['title']['english'] ?? '',
            'anime_title_native'     => $al['title']['native']  ?? '',
            'anime_status'           => $al['status']           ?? '',
            'anime_type'             => $al['format']           ?? 'TV',
            'anime_episodes'         => $al['episodes']         ?? 0,
            'anime_season'           => $al['season']           ?? '',
            'anime_year'             => $al['seasonYear']       ?? 0,
            'anime_score_anilist'    => $al['averageScore']     ?? 0,
            'anime_score_bangumi'    => $bgm_data['rating']['score'] ?? 0,
            'anime_cover_image'      => $al['coverImage']['extraLarge'] ?? '',
            'anime_banner_image'     => $al['bannerImage']      ?? '',
            'anime_synopsis_chinese' => $this->convert_text( $al['description'] ?? '' ),
            'anime_staff_json'       => json_encode( $this->get_bgm_staff( $bgm_id ), JSON_UNESCAPED_UNICODE ),
            'anime_cast_json'        => json_encode( $this->get_bgm_chars( $bgm_id ), JSON_UNESCAPED_UNICODE ),
            '_genres'                => $al['genres'] ?? [],
            'errors'                 => []
        ];
    }

    private function convert_text( $text ) {
        if ( empty( $text ) ) return '';
        return ( $this->converter && method_exists( $this->converter, 'convert' ) )
            ? $this->converter->convert( $text )
            : $text;
    }

    private function fetch_anilist_data( int $id ) {
        $query = 'query($id:Int){Media(id:$id,type:ANIME){id idMal title{romaji english native}status episodes description season seasonYear coverImage{extraLarge}bannerImage genres averageScore format}}';

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

        // ✅ 檢查 GraphQL errors
        if ( ! empty( $body['errors'] ) ) {
            $msg = $body['errors'][0]['message'] ?? 'GraphQL 錯誤';
            return [ 'success' => false, 'message' => 'AniList GraphQL 錯誤：' . $msg ];
        }

        return [ 'success' => true, 'data' => $body['data']['Media'] ?? null ];
    }

    private function get_bangumi_data( $id ) {
        if ( ! $id ) return null;
        $res = wp_remote_get(
            self::BANGUMI_ENDPOINT . "/v0/subjects/{$id}",
            [
                'headers' => [ 'User-Agent' => 'AnimeSyncPro/1.0' ],
                'timeout' => 15,
            ]
        );
        if ( is_wp_error( $res ) ) return null;
        $code = wp_remote_retrieve_response_code( $res );
        if ( $code !== 200 ) return null;
        return json_decode( wp_remote_retrieve_body( $res ), true );
    }

    private function get_bgm_staff( $id ) {
        if ( ! $id ) return [];
        $res = wp_remote_get(
            self::BANGUMI_ENDPOINT . "/v0/subjects/{$id}/persons",
            [
                'headers' => [ 'User-Agent' => 'AnimeSyncPro/1.0' ],
                'timeout' => 15,
            ]
        );
        $data = is_wp_error( $res ) ? [] : json_decode( wp_remote_retrieve_body( $res ), true );
        $out  = [];
        if ( is_array( $data ) ) {
            foreach ( array_slice( $data, 0, 10 ) as $p ) {
                $out[] = [
                    'name' => $this->convert_text( $p['name'] ?? '' ),
                    'role' => $p['relation'] ?? '',
                ];
            }
        }
        return $out;
    }

    private function get_bgm_chars( $id ) {
        if ( ! $id ) return [];
        $res = wp_remote_get(
            self::BANGUMI_ENDPOINT . "/v0/subjects/{$id}/characters",
            [
                'headers' => [ 'User-Agent' => 'AnimeSyncPro/1.0' ],
                'timeout' => 15,
            ]
        );
        $data = is_wp_error( $res ) ? [] : json_decode( wp_remote_retrieve_body( $res ), true );
        $out  = [];
        if ( is_array( $data ) ) {
            foreach ( array_slice( $data, 0, 10 ) as $c ) {
                $out[] = [
                    'name' => $this->convert_text( $c['name'] ?? '' ),
                    'role' => $c['relation'] ?? '',
                ];
            }
        }
        return $out;
    }
}

<?php
/**
 * 檔案名稱: includes/class-id-mapper.php
 * @package AnimeSyncPro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_ID_Mapper {

    const MAP_REMOTE_URL       = 'https://raw.githubusercontent.com/bangumi/Archive/master/aux/mal_bangumi_map.json';
    const MAP_FILENAME         = 'anime_map.json';
    const AOD_INDEX_FILENAME   = 'anime-offline-index.json';

    private static $map = null;
    private static $map_file_path = '';
    private static $offline_index_path = '';

    public function __construct() {
        self::init_paths();
        add_action( 'anime_sync_update_anime_map', [ $this, 'download_and_cache_map' ] );
    }

    private static function init_paths() {
        if ( empty( self::$map_file_path ) ) {
            $upload_dir = wp_upload_dir();
            $base_path  = trailingslashit( $upload_dir['basedir'] ) . 'anime-sync-pro/';
            self::$map_file_path      = $base_path . self::MAP_FILENAME;
            self::$offline_index_path = $base_path . self::AOD_INDEX_FILENAME;
        }
    }

    /**
     * 獲取地圖檔案狀態 (對應 settings.php 需求)
     */
    public static function get_map_status(): array {
        self::init_paths();
        $exists = file_exists( self::$map_file_path );
        $mtime  = $exists ? filemtime( self::$map_file_path ) : 0;

        return [
            'exists'       => $exists,
            'path'         => self::$map_file_path,
            'file_size'    => $exists ? filesize( self::$map_file_path ) : 0,
            'last_updated' => $exists ? date( 'Y-m-d H:i:s', $mtime ) : null,
            'age_hours'    => $exists ? round( ( time() - $mtime ) / HOUR_IN_SECONDS ) : 9999,
            'entry_count'  => self::get_entry_count(),
        ];
    }

    /**
     * 計算 JSON 內的映射數量
     */
    private static function get_entry_count(): int {
        if ( ! file_exists( self::$map_file_path ) ) return 0;
        $content = file_get_contents( self::$map_file_path );
        $decoded = json_decode( $content, true );
        return is_array( $decoded ) ? count( $decoded ) : 0;
    }

    /**
     * 手動/排程下載對照表
     */
    public function download_and_cache_map() {
        self::init_paths();
        $dir = dirname( self::$map_file_path );
        if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );

        $response = wp_remote_get( self::MAP_REMOTE_URL, [ 'timeout' => 30 ] );
        if ( is_wp_error( $response ) ) return false;

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) return false;

        return file_put_contents( self::$map_file_path, $body );
    }

    /**
     * 原有的 ID 解析邏輯 (保留並修正)
     */
    public function resolve_ids( $anilist_id = null, $mal_id = null, $bangumi_id = null, array $anilist_data = [], int $post_id = 0 ): array {
        $result = [
            'mal_id'            => !empty($mal_id) ? (int)$mal_id : null,
            'anilist_id'        => !empty($anilist_id) ? (int)$anilist_id : null,
            'bangumi_id'        => !empty($bangumi_id) ? (int)$bangumi_id : null,
            'matched_by'        => [],
            'validation_errors' => [],
        ];

        if ( empty( $result['bangumi_id'] ) ) {
            $result['bangumi_id'] = $this->find_bangumi_id( $result['mal_id'], $anilist_data, $post_id, $result['anilist_id'] );
        }

        if ( ! empty( $result['bangumi_id'] ) ) {
            $result['matched_by']['bangumi_id'] = 'resolved';
        }

        return $result;
    }

    public function find_bangumi_id( ?int $mal_id, array $anilist_data, int $post_id = 0, ?int $anilist_id = null ): ?int {
        if ( ! empty( $mal_id ) ) {
            $bgm_id = $this->lookup_from_map( (int) $mal_id );
            if ( $bgm_id ) return (int) $bgm_id;
        }
        return null;
    }

    private function lookup_from_map( int $mal_id ): ?int {
        $map = $this->load_map();
        $key = (string) $mal_id;
        return isset( $map[ $key ] ) ? (int) ( is_array( $map[ $key ] ) ? ( $map[ $key ]['bgm_id'] ?? 0 ) : $map[ $key ] ) : null;
    }

    private function load_map(): array {
        if ( null !== self::$map ) return self::$map;
        self::init_paths();
        if ( file_exists( self::$map_file_path ) ) {
            $decoded = json_decode( file_get_contents( self::$map_file_path ), true );
            if ( is_array( $decoded ) ) {
                self::$map = $decoded;
                return self::$map;
            }
        }
        return [];
    }
}
<?php
/**
 * 檔案名稱: includes/class-cn-converter.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_CN_Converter {

    private static $dict = null;
    private static $dict_path = '';

    /**
     * 支援非靜態呼叫：讓 $this->converter->convert() 能運作
     * 解決 API_Handler 在呼叫時產生的致命錯誤
     */
    public function convert( $text ) {
        return self::static_convert( $text );
    }

    /**
     * 載入字典檔並處理編碼問題
     */
    private static function load_dict() {
        if ( self::$dict !== null ) {
            return true;
        }

        if ( empty( self::$dict_path ) ) {
            self::$dict_path = defined( 'ANIME_SYNC_PRO_DIR' )
                ? ANIME_SYNC_PRO_DIR . 'data/cn-tw-dict.json'
                : plugin_dir_path( dirname( __FILE__ ) ) . 'data/cn-tw-dict.json';
        }

        if ( ! file_exists( self::$dict_path ) ) {
            error_log( "Anime Sync Pro: 找不到字典檔 - " . self::$dict_path );
            self::$dict = array();
            return false;
        }

        $json = file_get_contents( self::$dict_path );
        if ( false === $json ) {
            self::$dict = array();
            return false;
        }

        // 處理 UTF-8 BOM 頭，確保 JSON 解析不會出錯
        if ( substr( $json, 0, 3 ) === pack( "CCC", 0xef, 0xbb, 0xbf ) ) {
            $json = substr( $json, 3 );
        }
        
        $decoded = json_decode( $json, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
            error_log( "Anime Sync Pro: 字典 JSON 解析失敗 - " . json_last_error_msg() );
            self::$dict = array();
            return false;
        }

        // 移除 JSON 內的註解欄位
        unset( $decoded['_comment'] );
        self::$dict = $decoded;
        return true;
    }

    /**
     * 靜態轉換核心方法
     */
    public static function static_convert( $text ) {
        if ( empty( $text ) || ! is_string( $text ) ) {
            return $text;
        }

        if ( ! self::load_dict() || empty( self::$dict ) ) {
            return $text;
        }

        return strtr( $text, self::$dict );
    }

    /**
     * 批次轉換陣列內容
     */
    public static function convert_array( $data ) {
        if ( ! is_array( $data ) ) return $data;
        foreach ( $data as $key => $value ) {
            if ( is_string( $value ) ) {
                $data[ $key ] = self::static_convert( $value );
            } elseif ( is_array( $value ) ) {
                $data[ $key ] = self::convert_array( $value );
            }
        }
        return $data;
    }

    /**
     * 獲取字典載入狀態（供後台診斷使用）
     */
    public static function get_stats() {
        self::load_dict();
        return array(
            'loaded'      => ( ! empty( self::$dict ) ),
            'entry_count' => is_array( self::$dict ) ? count( self::$dict ) : 0,
            'dict_path'   => self::$dict_path,
            'file_size'   => file_exists( self::$dict_path ) ? filesize( self::$dict_path ) : -1,
        );
    }
}

<?php
/**
 * Class Anime_Sync_CN_Converter
 *
 * 簡繁轉換類別。
 * 優先使用 overtrue/php-opencc（S2TWP 策略）。
 * fallback 至靜態字典。
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_CN_Converter {

    private static ?bool  $opencc_available = null;
    private static ?array $dict_cache       = null;

// =========================================================================
// 公開靜態介面
// =========================================================================

public static function static_convert( string $text ): string {
    if ( $text === '' ) return '';
    // 先跑靜態字典強制替換每個簡體字
    $text = self::convert_with_dict( $text );
    // 再跑 OpenCC 處理詞組層級
    if ( self::is_opencc_available() ) {
        return self::convert_with_opencc_only( $text );
    }
    return $text;
}

private static function convert_with_opencc_only( string $text ): string {
    try {
        return \Overtrue\PHPOpenCC\OpenCC::convert( $text, 'S2TWP' );
    } catch ( \Throwable $e ) {
        return $text;
    }
}

    // =========================================================================
    // 相容實例方法（dashboard.php / import-tool.php）
    // =========================================================================

    public function convert( string $text ): string {
        return self::static_convert( $text );
    }

    public function get_stats(): array {
        $opencc_ok = self::is_opencc_available();

        if ( $opencc_ok ) {
            return [
                'dict_path'   => self::get_vendor_path(),
                'file_size'   => 1,
                'entry_count' => 'OpenCC S2TWP（詞庫完整）',
                'mode'        => 'opencc',
                'loaded'      => true,
            ];
        }

        $dict_file = self::get_dict_path();
        $file_size = file_exists( $dict_file ) ? filesize( $dict_file ) : 0;
        $dict      = self::load_dict();

        return [
            'dict_path'   => $dict_file,
            'file_size'   => (int) $file_size,
            'entry_count' => count( $dict ),
            'mode'        => 'dict',
            'loaded'      => $file_size > 0,
        ];
    }

    // =========================================================================
    // PRIVATE – 路徑輔助
    // =========================================================================

    /**
     * 用 __FILE__ 往上兩層取插件根目錄，比 plugin_dir_path() 更可靠。
     */
    private static function get_plugin_root(): string {
        // __FILE__ = .../anime-sync-pro/includes/class-cn-converter.php
        // dirname x2 = .../anime-sync-pro/
        return dirname( __FILE__, 2 ) . DIRECTORY_SEPARATOR;
    }

    private static function get_autoload_path(): string {
        return self::get_plugin_root() . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    }

    private static function get_vendor_path(): string {
        return self::get_plugin_root() . 'vendor' . DIRECTORY_SEPARATOR . 'overtrue' . DIRECTORY_SEPARATOR . 'php-opencc';
    }

    private static function get_dict_path(): string {
        return self::get_plugin_root() . 'data' . DIRECTORY_SEPARATOR . 'cn-tw-dict.json';
    }

    // =========================================================================
    // PRIVATE – OpenCC
    // =========================================================================

    private static function is_opencc_available(): bool {
        if ( self::$opencc_available !== null ) {
            return self::$opencc_available;
        }

        $autoload = self::get_autoload_path();

        if ( ! file_exists( $autoload ) ) {
            self::$opencc_available = false;
            return false;
        }

        if ( ! class_exists( 'Overtrue\\PHPOpenCC\\OpenCC', false ) ) {
            require_once $autoload;
        }

        self::$opencc_available = class_exists( 'Overtrue\\PHPOpenCC\\OpenCC', false );
        return self::$opencc_available;
    }

private static function convert_with_opencc( string $text ): string {
    try {
        $result = \Overtrue\PHPOpenCC\OpenCC::convert( $text, 'S2T' );
        $result = \Overtrue\PHPOpenCC\OpenCC::convert( $result, 'T2TW' );
        return self::convert_with_dict( $result );
    } catch ( \Throwable $e ) {
        return self::convert_with_dict( $text );
    }
}

    // =========================================================================
    // PRIVATE – Fallback 靜態字典
    // =========================================================================

    private static function load_dict(): array {
        if ( self::$dict_cache !== null ) {
            return self::$dict_cache;
        }

        $dict_file = self::get_dict_path();

        if ( ! file_exists( $dict_file ) ) {
            self::$dict_cache = [];
            return [];
        }

        $json = file_get_contents( $dict_file );
        if ( $json === false ) {
            self::$dict_cache = [];
            return [];
        }

        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            self::$dict_cache = [];
            return [];
        }

        uksort( $decoded, function( $a, $b ) {
            return mb_strlen( $b ) - mb_strlen( $a );
        } );

        self::$dict_cache = $decoded;
        return self::$dict_cache;
    }

    private static function convert_with_dict( string $text ): string {
        $dict = self::load_dict();
        if ( empty( $dict ) ) {
            return $text;
        }

        $keys   = [];
        $values = [];
        foreach ( $dict as $k => $v ) {
            if ( is_string( $k ) && is_string( $v ) ) {
                $keys[]   = $k;
                $values[] = $v;
            }
        }

        if ( empty( $keys ) ) {
            return $text;
        }

        return str_replace( $keys, $values, $text );
    }
}

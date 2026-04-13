<?php
/**
 * Class Anime_Sync_CN_Converter
 *
 * 簡繁轉換類別。
 *
 * 優先使用 overtrue/php-opencc（Composer 套件，S2TWP 策略）。
 * 若 vendor/autoload.php 不存在（尚未執行 composer install），
 * 自動 fallback 至原本的靜態字典邏輯，不會造成白屏或 Fatal Error。
 *
 * 安裝方式（SSH 至插件根目錄執行一次）：
 *   composer install --no-dev --optimize-autoloader
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_CN_Converter {

    // =========================================================================
    // 狀態旗標
    // =========================================================================

    /** @var bool|null OpenCC 是否可用（null = 尚未偵測） */
    private static ?bool $opencc_available = null;

    /** @var array|null fallback 靜態字典快取 */
    private static ?array $dict_cache = null;

    // =========================================================================
    // 公開靜態介面
    // =========================================================================

    /**
     * 將簡體中文字串轉換為繁體中文（台灣正體 + 詞彙本地化）。
     *
     * @param  string $text 輸入字串（簡體中文）
     * @return string       轉換後字串（繁體中文）
     */
    public static function static_convert( string $text ): string {
        if ( $text === '' ) return '';

        if ( self::is_opencc_available() ) {
            return self::convert_with_opencc( $text );
        }

        return self::convert_with_dict( $text );
    }

    // =========================================================================
    // 相容 dashboard.php / import-tool.php 的實例方法
    // =========================================================================

    /**
     * 非靜態包裝，讓 dashboard.php / import-tool.php 可用
     * $cn_converter->convert() 呼叫。
     *
     * @param  string $text 輸入字串（簡體中文）
     * @return string       轉換後字串（繁體中文）
     */
    public function convert( string $text ): string {
        return self::static_convert( $text );
    }

    /**
     * 回傳轉換器狀態資訊，供 dashboard.php / import-tool.php 顯示。
     * 包含 loaded key，相容 import-tool.php 第 23、24 行的判斷。
     *
     * @return array{dict_path: string, file_size: int, entry_count: int|string, mode: string, loaded: bool}
     */
    public function get_stats(): array {
        $dict_file = plugin_dir_path( __FILE__ ) . '../data/cn-tw-dict.json';
        $opencc_ok = self::is_opencc_available();

        if ( $opencc_ok ) {
            return [
                'dict_path'   => plugin_dir_path( __FILE__ ) . '../vendor/overtrue/php-opencc',
                'file_size'   => 1,
                'entry_count' => 'OpenCC S2TWP（詞庫完整）',
                'mode'        => 'opencc',
                'loaded'      => true,
            ];
        }

        // fallback 字典模式
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
    // PRIVATE – OpenCC 路徑
    // =========================================================================

    /**
     * 偵測 Composer autoload 與 OpenCC 類別是否存在。
     */
    private static function is_opencc_available(): bool {
        if ( self::$opencc_available !== null ) {
            return self::$opencc_available;
        }

        $autoload = plugin_dir_path( __FILE__ )
                    . '../vendor/autoload.php';

        if ( ! file_exists( $autoload ) ) {
            self::$opencc_available = false;
            return false;
        }

        // 只載入一次
        if ( ! class_exists( 'Overtrue\\PHPOpenCC\\OpenCC', false ) ) {
            require_once $autoload;
        }

        self::$opencc_available = class_exists( 'Overtrue\\PHPOpenCC\\OpenCC', false );
        return self::$opencc_available;
    }

    /**
     * 使用 overtrue/php-opencc 進行轉換。
     * 策略：S2TWP（簡體→台灣正體，含詞彙本地化）
     * 例：软件→軟體、网络→網路、服务器→伺服器
     */
    private static function convert_with_opencc( string $text ): string {
        try {
            return \Overtrue\PHPOpenCC\OpenCC::convert( $text, 'S2TWP' );
        } catch ( \Throwable $e ) {
            // OpenCC 轉換失敗時 fallback 字典
            return self::convert_with_dict( $text );
        }
    }

    // =========================================================================
    // PRIVATE – Fallback 靜態字典路徑
    // =========================================================================

    /**
     * 載入 data/cn-tw-dict.json 字典並快取。
     */
    private static function load_dict(): array {
        if ( self::$dict_cache !== null ) {
            return self::$dict_cache;
        }

        $dict_file = plugin_dir_path( __FILE__ ) . '../data/cn-tw-dict.json';

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

        // 依 key 長度降序排序，確保長詞優先匹配
        uksort( $decoded, function( $a, $b ) {
            return mb_strlen( $b ) - mb_strlen( $a );
        } );

        self::$dict_cache = $decoded;
        return self::$dict_cache;
    }

    /**
     * 使用靜態字典進行簡繁轉換（fallback）。
     */
    private static function convert_with_dict( string $text ): string {
        $dict = self::load_dict();
        if ( empty( $dict ) ) {
            return $text;
        }

        return str_replace(
            array_keys( $dict ),
            array_values( $dict ),
            $text
        );
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Image_Handler {

    private $logger;
    private int $cover_width  = 460;
    private int $cover_height = 651;

    public function __construct() {
        $this->logger = new Anime_Sync_Error_Logger();
    }

    // ============================================================
    // 主要入口：處理封面圖
    // ============================================================
    public function handle_cover( string $image_url, string $anime_title, int $post_id = 0 ): array {
        if ( empty( $image_url ) ) {
            return [ 'method' => 'none', 'value' => '' ];
        }

        $method = get_option( 'anime_sync_image_method', 'api_url' );

        switch ( $method ) {
            case 'media_library':
                return $this->handle_media_library( $image_url, $anime_title, $post_id );
            case 'cdn':
                return $this->handle_cdn( $image_url, $anime_title, $post_id );
            case 'api_url':
            default:
                return $this->handle_api_url( $image_url, $anime_title, $post_id );
        }
    }

    // ============================================================
    // 模式一：直接使用 API URL
    // Bug 2 修正：先檢查 featured image 是否已存在，避免重複下載
    // ============================================================
    private function handle_api_url( string $url, string $anime_title, int $post_id ): array {
        if ( ! $this->validate_url( $url ) ) {
            $this->logger->log( 'warning', 'Invalid image URL', [ 'url' => $url ] );
            return [ 'method' => 'api_url', 'value' => '' ];
        }

        $clean_url = esc_url_raw( $url );

        if ( $post_id ) {
            update_post_meta( $post_id, 'anime_cover_image', $clean_url );

            // ✅ Bug 2 修正：已有 featured image 就不重複下載
            if ( ! has_post_thumbnail( $post_id ) ) {
                $attachment_id = $this->download_and_upload( $url, $anime_title, $post_id, true );
                if ( ! $attachment_id ) {
                    $this->logger->log( 'warning', 'api_url 模式：無法下載封面至媒體庫，改用外部 URL', [
                        'url'   => $url,
                        'title' => $anime_title,
                    ]);
                }
            }
        }

        return [ 'method' => 'api_url', 'value' => $clean_url ];
    }

    // ============================================================
    // 模式二：上傳至媒體庫
    // Bug 2 修正：先檢查 featured image 是否已存在
    // ============================================================
    private function handle_media_library( string $url, string $anime_title, int $post_id ): array {
        if ( ! $this->validate_url( $url ) ) {
            $this->logger->log( 'warning', 'Invalid image URL for upload', [ 'url' => $url ] );
            return [ 'method' => 'media_library', 'value' => 0 ];
        }

        // ✅ Bug 2 修正：已有 featured image 就不重複下載
        if ( $post_id && has_post_thumbnail( $post_id ) ) {
            return [ 'method' => 'media_library', 'value' => get_post_thumbnail_id( $post_id ) ];
        }

        $attachment_id = $this->download_and_upload( $url, $anime_title, $post_id );

        if ( $attachment_id ) {
            $this->resize_image( $attachment_id, $this->cover_width, $this->cover_height );
            return [ 'method' => 'media_library', 'value' => $attachment_id ];
        }

        // 失敗時 fallback 到 api_url
        return $this->handle_api_url( $url, $anime_title, $post_id );
    }

    // ============================================================
    // 模式三：CDN 代理
    // Bug 2 修正：先檢查 featured image 是否已存在
    // ============================================================
    private function handle_cdn( string $url, string $anime_title, int $post_id ): array {
        $cdn_url = $this->build_cdn_url( $url, $this->cover_width, $this->cover_height );

        if ( $post_id ) {
            update_post_meta( $post_id, 'anime_cover_image', esc_url_raw( $cdn_url ) );

            // ✅ Bug 2 修正：已有 featured image 就不重複下載
            if ( ! has_post_thumbnail( $post_id ) ) {
                $attachment_id = $this->download_and_upload( $url, $anime_title, $post_id, true );
                if ( ! $attachment_id ) {
                    $this->logger->log( 'warning', 'cdn 模式：無法下載封面至媒體庫，改用 CDN URL', [
                        'url'   => $cdn_url,
                        'title' => $anime_title,
                    ]);
                }
            }
        }

        return [ 'method' => 'cdn', 'value' => esc_url_raw( $cdn_url ) ];
    }

    // ============================================================
    // 驗證圖片 URL
    // ============================================================
    public function validate_url( string $url ): bool {
        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $response = wp_remote_head( $url, [
            'timeout'   => 8,
            'sslverify' => false,
        ]);

        if ( is_wp_error( $response ) ) return false;

        $code         = wp_remote_retrieve_response_code( $response );
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );

        return $code === 200 && strpos( $content_type, 'image/' ) === 0;
    }

    // ============================================================
    // 下載圖片並上傳至媒體庫
    // ============================================================
    public function download_and_upload( string $url, string $title, int $post_id = 0, bool $silent = false ): int|false {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $temp_file = download_url( $url, 15 );

        if ( is_wp_error( $temp_file ) ) {
            if ( ! $silent ) {
                $this->logger->log( 'error', '封面圖下載失敗', [
                    'url'   => $url,
                    'error' => $temp_file->get_error_message(),
                ]);
            }
            return false;
        }

        $alt_text  = trim( $title ) . ' 封面圖 | 動畫資料庫';
        $file_name = sanitize_file_name( $title . '-cover.jpg' );

        $file_array = [
            'name'     => $file_name,
            'tmp_name' => $temp_file,
        ];

        $attachment_id = media_handle_sideload( $file_array, $post_id, $alt_text );

        @unlink( $temp_file );

        if ( is_wp_error( $attachment_id ) ) {
            if ( ! $silent ) {
                $this->logger->log( 'error', '封面圖上傳失敗', [
                    'title' => $title,
                    'error' => $attachment_id->get_error_message(),
                ]);
            }
            return false;
        }

        // 設定 Alt Text
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );

        // 設定為 Featured Image
        if ( $post_id ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }

        return $attachment_id;
    }

    // ============================================================
    // 裁切圖片至目標尺寸
    // ============================================================
    public function resize_image( int $attachment_id, int $width = 460, int $height = 651 ): bool {
        $file_path = get_attached_file( $attachment_id );

        if ( ! $file_path || ! file_exists( $file_path ) ) return false;

        $image = wp_get_image_editor( $file_path );
        if ( is_wp_error( $image ) ) return false;

        $image->resize( $width, $height, true );

        $upload_dir    = wp_upload_dir();
        $new_file_path = $upload_dir['path'] . '/anime-covers/' . basename( $file_path );

        wp_mkdir_p( dirname( $new_file_path ) );

        $saved = $image->save( $new_file_path );

        return ! is_wp_error( $saved );
    }

    // ============================================================
    // 建立 CDN URL
    // ============================================================
    public function build_cdn_url( string $original_url, int $width, int $height ): string {
        $cdn_provider = get_option( 'anime_sync_cdn_provider', 'cloudflare' );
        $cdn_base     = get_option( 'anime_sync_cdn_base_url', '' );

        if ( empty( $cdn_base ) ) return $original_url;

        switch ( $cdn_provider ) {
            case 'cloudflare':
                return sprintf(
                    '%s/cdn-cgi/image/width=%d,height=%d,fit=cover/%s',
                    rtrim( $cdn_base, '/' ),
                    $width,
                    $height,
                    urlencode( $original_url )
                );
            case 'imgproxy':
                return sprintf(
                    '%s/resize:fill:%d:%d/%s',
                    rtrim( $cdn_base, '/' ),
                    $width,
                    $height,
                    base64_encode( $original_url )
                );
            default:
                return $original_url;
        }
    }
}

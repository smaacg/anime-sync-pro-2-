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
    // ============================================================
    private function handle_api_url( string $url, string $anime_title, int $post_id ): array {
        if ( ! $this->validate_url( $url ) ) {
            $this->logger->log( 'warning', 'Invalid image URL', [ 'url' => $url ] );
            return [ 'method' => 'api_url', 'value' => '' ];
        }

        $clean_url = esc_url_raw( $url );

        if ( $post_id ) {
            update_post_meta( $post_id, 'anime_cover_image', $clean_url );

            if ( ! has_post_thumbnail( $post_id ) ) {
                $attachment_id = $this->download_and_upload( $url, $anime_title, $post_id, true );
                if ( ! $attachment_id ) {
                    $this->logger->log( 'warning', 'api_url 模式：無法下載封面至媒體庫，改用外部 URL', [
                        'url'   => $url,
                        'title' => $anime_title,
                    ] );
                }
            }
        }

        return [ 'method' => 'api_url', 'value' => $clean_url ];
    }

    // ============================================================
    // 模式二：上傳至媒體庫
    // ============================================================
    private function handle_media_library( string $url, string $anime_title, int $post_id ): array {
        if ( ! $this->validate_url( $url ) ) {
            $this->logger->log( 'warning', 'Invalid image URL for upload', [ 'url' => $url ] );
            return [ 'method' => 'media_library', 'value' => 0 ];
        }

        if ( $post_id && has_post_thumbnail( $post_id ) ) {
            return [ 'method' => 'media_library', 'value' => get_post_thumbnail_id( $post_id ) ];
        }

        $attachment_id = $this->download_and_upload( $url, $anime_title, $post_id );

        if ( $attachment_id ) {
            // ✅ 修正：resize_image() 現在會覆蓋原始檔並更新 attachment metadata
            $this->resize_image( $attachment_id, $this->cover_width, $this->cover_height );
            return [ 'method' => 'media_library', 'value' => $attachment_id ];
        }

        return $this->handle_api_url( $url, $anime_title, $post_id );
    }

    // ============================================================
    // 模式三：CDN 代理
    // ============================================================
    private function handle_cdn( string $url, string $anime_title, int $post_id ): array {
        $cdn_url = $this->build_cdn_url( $url, $this->cover_width, $this->cover_height );

        if ( $post_id ) {
            update_post_meta( $post_id, 'anime_cover_image', esc_url_raw( $cdn_url ) );

            if ( ! has_post_thumbnail( $post_id ) ) {
                $attachment_id = $this->download_and_upload( $url, $anime_title, $post_id, true );
                if ( ! $attachment_id ) {
                    $this->logger->log( 'warning', 'cdn 模式：無法下載封面至媒體庫，改用 CDN URL', [
                        'url'   => $cdn_url,
                        'title' => $anime_title,
                    ] );
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
        ] );

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
                ] );
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
                ] );
            }
            return false;
        }

        update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );

        if ( $post_id ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }

        return $attachment_id;
    }

    // ============================================================
    // ✅ 修正版 resize_image()
    //
    // 舊版問題：
    //   1. 另存到 /anime-covers/ 子目錄，產生孤兒檔
    //   2. 只回傳 bool，沒更新 attachment metadata
    //   3. WordPress 前台永遠用舊圖
    //
    // 修正做法：
    //   1. 直接覆蓋原始檔案（不產生孤兒）
    //   2. 呼叫 wp_generate_attachment_metadata() 重新產生所有尺寸
    //   3. 呼叫 wp_update_attachment_metadata() 寫回資料庫
    //   4. 清除 WordPress object cache，確保前台立即生效
    //   5. 同時清理舊版遺留的 /anime-covers/ 孤兒檔
    // ============================================================
    public function resize_image( int $attachment_id, int $width = 460, int $height = 651 ): bool {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file_path = get_attached_file( $attachment_id );

        if ( ! $file_path || ! file_exists( $file_path ) ) {
            $this->logger->log( 'warning', 'resize_image：找不到附件檔案', [
                'attachment_id' => $attachment_id,
                'file_path'     => $file_path,
            ] );
            return false;
        }

        // ── 1. 取得圖片編輯器 ──────────────────────────────────────────
        $image = wp_get_image_editor( $file_path );
        if ( is_wp_error( $image ) ) {
            $this->logger->log( 'warning', 'resize_image：無法建立 Image Editor', [
                'attachment_id' => $attachment_id,
                'error'         => $image->get_error_message(),
            ] );
            return false;
        }

        // ── 2. 取得原始尺寸，若已符合目標就跳過（避免無意義裁切）──────
        $current_size = $image->get_size();
        if (
            isset( $current_size['width'], $current_size['height'] ) &&
            (int) $current_size['width']  === $width &&
            (int) $current_size['height'] === $height
        ) {
            return true; // 尺寸已正確，無需處理
        }

        // ── 3. 裁切（true = 強制裁切，不留白）──────────────────────────
        $resized = $image->resize( $width, $height, true );
        if ( is_wp_error( $resized ) ) {
            $this->logger->log( 'warning', 'resize_image：resize 失敗', [
                'attachment_id' => $attachment_id,
                'error'         => $resized->get_error_message(),
            ] );
            return false;
        }

        // ── 4. 覆蓋儲存回原始路徑（不產生孤兒檔）──────────────────────
        $saved = $image->save( $file_path );
        if ( is_wp_error( $saved ) ) {
            $this->logger->log( 'warning', 'resize_image：儲存失敗', [
                'attachment_id' => $attachment_id,
                'file_path'     => $file_path,
                'error'         => $saved->get_error_message(),
            ] );
            return false;
        }

        // ── 5. 重新產生所有 WordPress intermediate sizes（thumbnail、medium 等）
        //       並更新 attachment metadata，讓前台立即反映新尺寸 ──────────
        $new_metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );

        if ( ! empty( $new_metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $new_metadata );
        }

        // ── 6. 清除 object cache，確保前台不讀到舊快取 ──────────────────
        clean_attachment_cache( $attachment_id );

        // ── 7. ✅ 清理舊版遺留的孤兒檔（/anime-covers/ 子目錄）──────────
        $this->cleanup_orphan_covers( $file_path );

        $this->logger->log( 'info', 'resize_image：裁切完成', [
            'attachment_id' => $attachment_id,
            'size'          => "{$width}x{$height}",
            'file_path'     => $file_path,
        ] );

        return true;
    }

    // ============================================================
    // ✅ 新增：清理舊版孤兒檔
    //    舊版 resize_image() 把裁切圖存在 uploads/{year}/{month}/anime-covers/
    //    這個方法會找到對應孤兒並刪除
    // ============================================================
    private function cleanup_orphan_covers( string $original_file_path ): void {
        $orphan_dir  = dirname( $original_file_path ) . '/anime-covers/';
        $orphan_file = $orphan_dir . basename( $original_file_path );

        if ( file_exists( $orphan_file ) ) {
            @unlink( $orphan_file );
            $this->logger->log( 'info', 'resize_image：已清除孤兒檔', [
                'orphan_file' => $orphan_file,
            ] );
        }

        // 如果目錄已空，也一併移除
        if ( is_dir( $orphan_dir ) ) {
            $remaining = array_diff( scandir( $orphan_dir ), [ '.', '..' ] );
            if ( empty( $remaining ) ) {
                @rmdir( $orphan_dir );
            }
        }
    }

    // ============================================================
    // ✅ 新增：一次性批次清理所有歷史孤兒檔
    //    可在後台手動呼叫，或掛 WP-Cron 定期執行
    //    回傳：刪除的檔案數量
    // ============================================================
    public function cleanup_all_orphan_covers(): int {
        $upload_base = wp_upload_dir()['basedir'];
        $deleted     = 0;

        // 掃描 uploads/ 下所有 anime-covers 子目錄
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $upload_base, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $path => $file_info ) {
            if ( $file_info->isDir() && $file_info->getFilename() === 'anime-covers' ) {
                $dir_path   = $file_info->getRealPath();
                $dir_files  = array_diff( scandir( $dir_path ), [ '.', '..' ] );

                foreach ( $dir_files as $filename ) {
                    $full_path = $dir_path . DIRECTORY_SEPARATOR . $filename;
                    if ( is_file( $full_path ) && @unlink( $full_path ) ) {
                        $deleted++;
                    }
                }

                // 目錄清空後移除
                $remaining = array_diff( scandir( $dir_path ), [ '.', '..' ] );
                if ( empty( $remaining ) ) {
                    @rmdir( $dir_path );
                }
            }
        }

        $this->logger->log( 'info', 'cleanup_all_orphan_covers：批次清理完成', [
            'deleted_files' => $deleted,
        ] );

        return $deleted;
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

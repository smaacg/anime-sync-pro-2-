<?php
/**
 * Image Handler
 * 
 * @package Anime_Sync_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class Anime_Sync_Image_Handler {
    
    /**
     * Error logger
     */
    private $logger;
    
    /**
     * Target cover dimensions
     */
    private $cover_width = 460;
    private $cover_height = 651;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new Anime_Sync_Error_Logger();
    }
    
    /**
     * Handle cover image
     * 
     * @param string $image_url Image URL
     * @param string $anime_title Anime title for filename
     * @param int $post_id Optional post ID
     * @return array Result with method and value
     */
    public function handle_cover($image_url, $anime_title, $post_id = null) {
        if (empty($image_url)) {
            return array(
                'method' => 'none',
                'value' => ''
            );
        }
        
        $method = get_option('anime_sync_image_method', 'api_url');
        
        switch ($method) {
            case 'media_library':
                return $this->handle_media_library($image_url, $anime_title, $post_id);
                
            case 'cdn':
                return $this->handle_cdn($image_url);
                
            case 'api_url':
            default:
                return $this->handle_api_url($image_url);
        }
    }
    
    /**
     * Handle API URL method (direct link)
     * 
     * @param string $url Image URL
     * @return array Result
     */
    private function handle_api_url($url) {
        // 驗證 URL 有效性
        if (!$this->validate_url($url)) {
            $this->logger->log('warning', 'Invalid image URL', array('url' => $url));
            return array(
                'method' => 'api_url',
                'value' => ''
            );
        }
        
        return array(
            'method' => 'api_url',
            'value' => esc_url($url)
        );
    }
    
    /**
     * Handle media library upload
     * 
     * @param string $url Image URL
     * @param string $title Title for filename
     * @param int $post_id Post ID
     * @return array Result
     */
    private function handle_media_library($url, $title, $post_id = null) {
        if (!$this->validate_url($url)) {
            $this->logger->log('warning', 'Invalid image URL for upload', array('url' => $url));
            return array('method' => 'media_library', 'value' => 0);
        }
        
        $attachment_id = $this->download_and_upload($url, $title, $post_id);
        
        if ($attachment_id) {
            // 產生自訂尺寸
            $this->resize_image($attachment_id, $this->cover_width, $this->cover_height);
            
            return array(
                'method' => 'media_library',
                'value' => $attachment_id
            );
        }
        
        // 失敗時回退到 API URL
        return array(
            'method' => 'api_url',
            'value' => esc_url($url)
        );
    }
    
    /**
     * Handle CDN proxy
     * 
     * @param string $url Original image URL
     * @return array Result
     */
    private function handle_cdn($url) {
        $cdn_url = $this->build_cdn_url($url, $this->cover_width, $this->cover_height);
        
        return array(
            'method' => 'cdn',
            'value' => esc_url($cdn_url)
        );
    }
    
    /**
     * Validate image URL
     * 
     * @param string $url URL to validate
     * @return bool Valid
     */
    public function validate_url($url) {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // 發送 HEAD 請求檢查
        $response = wp_remote_head($url, array(
            'timeout' => 5,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        return $code === 200 && strpos($content_type, 'image/') === 0;
    }
    
    /**
     * Download and upload to media library
     * 
     * @param string $url Image URL
     * @param string $title Title for filename
     * @param int $post_id Post ID
     * @return int|false Attachment ID or false
     */
    public function download_and_upload($url, $title, $post_id = null) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // 下載圖片
        $temp_file = download_url($url, 5);
        
        if (is_wp_error($temp_file)) {
            $this->logger->log('error', 'Failed to download image', array(
                'url' => $url,
                'error' => $temp_file->get_error_message()
            ));
            return false;
        }
        
        // 準備檔案陣列
        $file_array = array(
            'name' => sanitize_file_name($title . '-cover.jpg'),
            'tmp_name' => $temp_file
        );
        
        // 上傳到媒體庫
        $attachment_id = media_handle_sideload($file_array, $post_id, $title);
        
        // 清理暫存檔
        @unlink($temp_file);
        
        if (is_wp_error($attachment_id)) {
            $this->logger->log('error', 'Failed to upload image', array(
                'title' => $title,
                'error' => $attachment_id->get_error_message()
            ));
            return false;
        }
        
        // 設為文章特色圖片
        if ($post_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }
        
        return $attachment_id;
    }
    
    /**
     * Resize image to custom size
     * 
     * @param int $attachment_id Attachment ID
     * @param int $width Target width
     * @param int $height Target height
     * @return bool Success
     */
    public function resize_image($attachment_id, $width = 460, $height = 651) {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return false;
        }
        
        $image = wp_get_image_editor($file_path);
        
        if (is_wp_error($image)) {
            return false;
        }
        
        // 裁切並調整尺寸
        $image->resize($width, $height, true);
        
        // 儲存
        $upload_dir = wp_upload_dir();
        $new_file_path = $upload_dir['path'] . '/anime-covers/' . basename($file_path);
        
        // 確保目錄存在
        wp_mkdir_p(dirname($new_file_path));
        
        $saved = $image->save($new_file_path);
        
        if (is_wp_error($saved)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Build CDN URL
     * 
     * @param string $original_url Original image URL
     * @param int $width Width
     * @param int $height Height
     * @return string CDN URL
     */
    public function build_cdn_url($original_url, $width, $height) {
        $cdn_provider = get_option('anime_sync_cdn_provider', 'cloudflare');
        
        switch ($cdn_provider) {
            case 'cloudflare':
                // CloudFlare Images 格式範例
                $cdn_base = get_option('anime_sync_cdn_base_url', '');
                if (empty($cdn_base)) {
                    return $original_url;
                }
                return sprintf(
                    '%s/cdn-cgi/image/width=%d,height=%d,fit=cover/%s',
                    $cdn_base,
                    $width,
                    $height,
                    urlencode($original_url)
                );
                
            case 'imgproxy':
                // imgproxy 格式範例
                $cdn_base = get_option('anime_sync_cdn_base_url', '');
                if (empty($cdn_base)) {
                    return $original_url;
                }
                return sprintf(
                    '%s/resize:fill:%d:%d/%s',
                    $cdn_base,
                    $width,
                    $height,
                    base64_encode($original_url)
                );
                
            default:
                return $original_url;
        }
    }
}

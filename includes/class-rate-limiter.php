<?php
/**
 * API 速率限制處理
 */
class Anime_Sync_Rate_Limiter {
    
    private $limits = [
        'anilist' => 2000,      // 2 秒（30 req/min 保守值）
        'jikan' => 1200,        // 1.2 秒（50 req/min）
        'bangumi' => 1000,      // 1 秒
        'animethemes' => 700,   // 700 毫秒（90 req/min）
    ];
    
    /**
     * 等待直到可以發送請求
     */
    public function wait_if_needed($api_name) {
        $api_name = strtolower($api_name);
        
        if (!isset($this->limits[$api_name])) {
            return;
        }
        
        $interval = $this->limits[$api_name];
        $cache_key = 'anime_sync_last_request_' . $api_name;
        $last_time = get_transient($cache_key);
        
        if ($last_time !== false) {
            $elapsed = (microtime(true) * 1000) - $last_time;
            
            if ($elapsed < $interval) {
                $wait = ($interval - $elapsed) * 1000;
                usleep($wait);
            }
        }
        
        set_transient($cache_key, microtime(true) * 1000, 60);
    }
    
    /**
     * 處理 429 錯誤
     */
    public function handle_rate_limit_error($response_headers, $api_name) {
        $retry_after = 60;
        
        if (isset($response_headers['Retry-After'])) {
            $retry_after = intval($response_headers['Retry-After']);
        } elseif (isset($response_headers['X-RateLimit-Reset'])) {
            $reset_time = intval($response_headers['X-RateLimit-Reset']);
            $retry_after = max(1, $reset_time - time());
        }
        
        $logger = new Anime_Sync_Error_Logger();
        $logger->log('warning', sprintf(
            '%s API 速率限制，等待 %d 秒',
            ucfirst($api_name),
            $retry_after
        ));
        
        sleep($retry_after);
    }
    
    /**
     * 檢查剩餘配額
     */
    public function check_remaining($response_headers, $api_name) {
        if (!isset($response_headers['X-RateLimit-Remaining'])) {
            return;
        }
        
        $remaining = intval($response_headers['X-RateLimit-Remaining']);
        $limit = isset($response_headers['X-RateLimit-Limit']) 
            ? intval($response_headers['X-RateLimit-Limit']) 
            : 90;
        
        $percentage = ($remaining / $limit) * 100;
        
        if ($percentage < 10) {
            $logger = new Anime_Sync_Error_Logger();
            $logger->log('warning', sprintf(
                '%s API 配額剩餘不足 10%% (%d/%d)',
                ucfirst($api_name),
                $remaining,
                $limit
            ));
            
            sleep(5);
        }
    }
}

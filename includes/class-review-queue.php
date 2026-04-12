<?php
/**
 * Review Queue Manager
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_Review_Queue {

    private $wpdb;
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb       = $wpdb;
        $this->table_name = $wpdb->prefix . 'anime_review_queue';
    }

    // =========================================================================
    // 新增
    // =========================================================================

    /**
     * 新增項目到審核佇列。
     *
     * @param int    $anilist_id AniList ID。
     * @param array  $api_data   完整 API 資料陣列（merge_api_data 輸出格式）。
     * @param string $source     來源（manual / auto / season）。
     * @return int|false         佇列 ID，失敗返回 false。
     */
    public function add( int $anilist_id, array $api_data, string $source = 'manual' ): int|false {
        // 重複檢查
        if ( $this->get_item_by_anilist_id( $anilist_id ) ) {
            return false;
        }

        // ✅ 修正：從正確的 key 取得標題（merge_api_data 輸出格式）
        $title = '';
        if ( ! empty( $api_data['anime_title_chinese'] ) ) {
            $title = $api_data['anime_title_chinese'];
        } elseif ( ! empty( $api_data['anime_title_native'] ) ) {
            $title = $api_data['anime_title_native'];
        } elseif ( ! empty( $api_data['anime_title_romaji'] ) ) {
            $title = $api_data['anime_title_romaji'];
        }

        // 壓縮 JSON 資料
        $compressed_data = gzcompress(
            wp_json_encode( $api_data, JSON_UNESCAPED_UNICODE ),
            9
        );

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'anilist_id' => absint( $anilist_id ),
                'title'      => sanitize_text_field( $title ), // ✅ 獨立儲存標題欄位
                'api_data'   => $compressed_data,
                'status'     => 'pending',
                'source'     => sanitize_text_field( $source ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            return false;
        }

        return (int) $this->wpdb->insert_id;
    }

    // =========================================================================
    // 查詢
    // =========================================================================

    /**
     * 取得佇列項目列表（分頁）。
     *
     * @param int    $page     頁碼（從 1 開始）。
     * @param int    $per_page 每頁筆數。
     * @param string $status   狀態篩選（空字串表示不篩選）。
     * @return array           佇列項目陣列。
     */
    public function get_items( int $page = 1, int $per_page = 20, string $status = 'pending' ): array {
        $offset = ( $page - 1 ) * $per_page;

        // ✅ 修正：不使用 JSON_EXTRACT（api_data 是 BLOB），改讀獨立的 title 欄位
        if ( ! empty( $status ) ) {
            $query = $this->wpdb->prepare(
                "SELECT id, anilist_id, title, status, source, created_at, wp_post_id
                 FROM {$this->table_name}
                 WHERE status = %s
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $status,
                $per_page,
                $offset
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT id, anilist_id, title, status, source, created_at, wp_post_id
                 FROM {$this->table_name}
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            );
        }

        return $this->wpdb->get_results( $query, ARRAY_A ) ?: [];
    }

    /**
     * 取得單一佇列項目（含解壓縮 API 資料）。
     *
     * @param int $queue_id 佇列 ID。
     * @return array|null   項目資料，不存在返回 null。
     */
    public function get_item( int $queue_id ): ?array {
        $item = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                absint( $queue_id )
            ),
            ARRAY_A
        );

        if ( ! $item ) {
            return null;
        }

        // 解壓縮 JSON
        if ( ! empty( $item['api_data'] ) ) {
            $decompressed    = gzuncompress( $item['api_data'] );
            $item['api_data'] = $decompressed
                ? json_decode( $decompressed, true )
                : null;
        }

        return $item;
    }

    /**
     * 以 AniList ID 查找佇列項目。
     *
     * @param int $anilist_id AniList ID。
     * @return array|null     項目資料，不存在返回 null。
     */
    public function get_item_by_anilist_id( int $anilist_id ): ?array {
        $item_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE anilist_id = %d",
                absint( $anilist_id )
            )
        );

        if ( ! $item_id ) {
            return null;
        }

        return $this->get_item( (int) $item_id );
    }

    /**
     * 取得佇列總數。
     *
     * @param string|null $status 狀態篩選（null 表示不篩選）。
     * @return int                總數。
     */
    public function get_count( ?string $status = null ): int {
        if ( ! empty( $status ) ) {
            $count = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                    $status
                )
            );
        } else {
            $count = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name}"
            );
        }

        return (int) $count;
    }

    // =========================================================================
    // 更新
    // =========================================================================

    /**
     * 更新項目狀態。
     *
     * @param int      $queue_id   佇列 ID。
     * @param string   $new_status 新狀態。
     * @param int|null $wp_post_id WordPress 文章 ID（選填）。
     * @return bool                成功返回 true。
     */
    public function update_status( int $queue_id, string $new_status, ?int $wp_post_id = null ): bool {
        // ✅ 修正：動態產生 data 和 format 陣列，避免數量不一致
        $data    = [ 'status' => sanitize_text_field( $new_status ) ];
        $formats = [ '%s' ];

        if ( null !== $wp_post_id ) {
            $data['wp_post_id'] = absint( $wp_post_id );
            $formats[]          = '%d';
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            [ 'id' => absint( $queue_id ) ],
            $formats,
            [ '%d' ]
        );

        return $result !== false;
    }

    /**
     * 更新佇列項目的 API 資料（重新取得後覆寫）。
     *
     * @param int   $queue_id 佇列 ID。
     * @param array $api_data 新的 API 資料。
     * @return bool           成功返回 true。
     */
    public function update_api_data( int $queue_id, array $api_data ): bool {
        $compressed = gzcompress(
            wp_json_encode( $api_data, JSON_UNESCAPED_UNICODE ),
            9
        );

        // 同步更新標題
        $title = $api_data['anime_title_chinese']
            ?? $api_data['anime_title_native']
            ?? $api_data['anime_title_romaji']
            ?? '';

        $result = $this->wpdb->update(
            $this->table_name,
            [
                'api_data' => $compressed,
                'title'    => sanitize_text_field( $title ),
            ],
            [ 'id' => absint( $queue_id ) ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return $result !== false;
    }

    // =========================================================================
    // 刪除
    // =========================================================================

    /**
     * 刪除單一佇列項目。
     *
     * @param int $queue_id 佇列 ID。
     * @return bool         成功返回 true。
     */
    public function delete( int $queue_id ): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            [ 'id' => absint( $queue_id ) ],
            [ '%d' ]
        );

        return $result !== false;
    }

    // =========================================================================
    // 批次操作
    // =========================================================================

    /**
     * 批次刪除佇列項目。
     *
     * @param array $queue_ids 佇列 ID 陣列。
     * @return int             成功刪除的數量。
     */
    public function batch_delete( array $queue_ids ): int {
        if ( empty( $queue_ids ) ) {
            return 0;
        }

        $count = 0;
        foreach ( $queue_ids as $id ) {
            if ( $this->delete( (int) $id ) ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 批次核准佇列項目（狀態改為 approved）。
     *
     * @param array $queue_ids 佇列 ID 陣列。
     * @return int             成功核准的數量。
     */
    public function batch_approve( array $queue_ids ): int {
        if ( empty( $queue_ids ) ) {
            return 0;
        }

        $count = 0;
        foreach ( $queue_ids as $id ) {
            if ( $this->update_status( (int) $id, 'approved' ) ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 批次拒絕佇列項目（狀態改為 rejected）。
     *
     * @param array $queue_ids 佇列 ID 陣列。
     * @return int             成功拒絕的數量。
     */
    public function batch_reject( array $queue_ids ): int {
        if ( empty( $queue_ids ) ) {
            return 0;
        }

        $count = 0;
        foreach ( $queue_ids as $id ) {
            if ( $this->update_status( (int) $id, 'rejected' ) ) {
                $count++;
            }
        }

        return $count;
    }
}

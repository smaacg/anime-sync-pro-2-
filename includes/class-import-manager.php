<?php
/**
 * 檔案名稱: includes/class-import-manager.php
 *
 * ACD – 新增 analyze_series()：呼叫 api_handler->get_series_tree()，
 * 供 Tab 4 AJAX 分析系列使用。
 * 新增 assign_series_taxonomy()：建立或查找 anime_series_tax term，
 * 並將指定文章歸入該系列。
 * 新增 get_popularity_ranking()：委派給 api_handler->fetch_anilist_popularity()，
 * 供 Tab 5 AJAX 人氣排行使用。
 * import_single() 新增第三參數 $source（預設 'manual'），
 * 相容 class-cron-manager.php 呼叫 import_single($id, null, 'anilist')。
 * generate_slug() 新增 $exclude_id 參數，更新時排除自身避免無限加 suffix。
 * ACK – 新增 map_streaming_to_tw_fields()：解析 externalLinks 自動寫入台灣串流平台欄位。
 * ACL – import_single() enrich 排程改為依 post_id 尾數錯開時間，
 *        避免批量匯入時同時觸發大量 API 請求撞 rate limit。
 *
 * [修改] 新增：
 * - import_single() 更新時保留現有文章標題，不讓 API 覆寫人工編輯的標題
 * - fetch_themes_only()：公開包裝方法，供 class-cron-manager.php 呼叫 AnimeThemes API
 * - fetch_episodes_only()：公開包裝方法，供 class-cron-manager.php 呼叫 Bangumi 集數 API
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_Import_Manager {

	private Anime_Sync_API_Handler $api_handler;
	private Anime_Sync_CN_Converter $cn_converter;

	public function __construct(
		Anime_Sync_API_Handler $api_handler,
		Anime_Sync_CN_Converter $cn_converter
	) {
		$this->api_handler  = $api_handler;
		$this->cn_converter = $cn_converter;
	}

	// =========================================================================
	// PUBLIC – 單筆匯入（ACD：新增第三參數 $source）
	// =========================================================================

	public function import_single( int $anilist_id, ?int $bangumi_id = null, string $source = 'manual' ): array {

		$existing_id = $this->find_existing( $anilist_id );
		$is_update   = (bool) $existing_id;

		// 先重用既有文章已保存的 Bangumi ID，讓 API Handler / ID Mapper 的 Layer 0 真正生效。
		if ( ( ! $bangumi_id || $bangumi_id <= 0 ) && $existing_id > 0 ) {
			$stored_bangumi_id = (int) get_post_meta( $existing_id, 'anime_bangumi_id', true );
			if ( $stored_bangumi_id <= 0 ) {
				$stored_bangumi_id = (int) get_post_meta( $existing_id, 'bangumi_id', true );
			}
			if ( $stored_bangumi_id > 0 ) {
				$bangumi_id = $stored_bangumi_id;
			}
		}

		$anime_data = $this->api_handler->get_core_anime_data( $anilist_id, $existing_id, $bangumi_id );

		if ( is_wp_error( $anime_data ) ) {
			return [
				'success' => false,
				'message' => '資料取得失敗：' . $anime_data->get_error_message(),
			];
		}

		if ( empty( $anime_data['anilist_id'] ) ) {
			return [
				'success' => false,
				'message' => '無效的 AniList 資料（缺少 anilist_id）',
			];
		}

		$has_bangumi   = ! empty( $anime_data['bangumi_id'] ) && (int) $anime_data['bangumi_id'] > 0;
		$has_chinese   = ! empty( $anime_data['anime_title_chinese'] );
		$has_synopsis  = ! empty( $anime_data['anime_synopsis_chinese'] );
		$has_cover     = ! empty( $anime_data['anime_cover_image'] );
		$has_streaming = ! empty( $anime_data['anime_streaming'] ) && $anime_data['anime_streaming'] !== '[]';

		$summary = implode( ' | ', array_filter( [
			$has_chinese   ? '✅ 中文標題' : '⚠️ 無中文標題',
			$has_bangumi   ? '✅ Bangumi'  : '⚠️ 缺 Bangumi',
			$has_synopsis  ? '✅ 簡介'     : null,
			$has_cover     ? '✅ 封面'     : '⚠️ 無封面',
			$has_streaming ? '✅ 串流'     : null,
			'⏳ 待補抓：聲優/主題曲/Wikipedia',
		] ) );

		// [修改] 標題邏輯：
		// - 首次匯入（!$is_update）：使用 API 回傳的中文標題或 Romaji，行為與原本相同。
		// - 更新（$is_update）：優先保留現有文章的 post_title（人工編輯過的標題），
		//   避免每日排程覆寫人工修改。只有在現有標題為空時才 fallback 至 API 標題。
		if ( $is_update ) {
			$existing_post = get_post( $existing_id );
			$post_title    = ( $existing_post && trim( $existing_post->post_title ) !== '' )
				? $existing_post->post_title
				: ( ! empty( $anime_data['anime_title_chinese'] )
					? (string) $anime_data['anime_title_chinese']
					: ( $anime_data['anime_title_romaji'] ?? "Anime {$anilist_id}" ) );
		} else {
			$post_title = ! empty( $anime_data['anime_title_chinese'] )
				? (string) $anime_data['anime_title_chinese']
				: ( $anime_data['anime_title_romaji'] ?? "Anime {$anilist_id}" );
		}

		$post_slug   = $this->generate_slug( $anime_data, $existing_id );
		$post_fields = $this->extract_post_fields( $anime_data, $existing_id );

		$post_data = [
			'post_type'   => 'anime',
			'post_title'  => $post_title,
			'post_name'   => $post_slug,
			'post_status' => 'draft',
			'post_author' => get_current_user_id() ?: 1,
		];

		if ( ! empty( $post_fields ) ) {
			$post_data = array_merge( $post_data, $post_fields );
		}

		if ( $is_update ) {
			$post_data['ID'] = $existing_id;
			$post_id = wp_update_post( $post_data, true );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return [
				'success' => false,
				'message' => '文章建立失敗：' . $post_id->get_error_message(),
			];
		}

		$this->save_post_meta( $post_id, $anime_data );
		update_post_meta( $post_id, 'anime_last_updated', current_time( 'mysql' ) );

		if ( ! $is_update ) {
			$this->apply_first_import_locks( $post_id, $anime_data );
		}

		if ( ! empty( $anime_data['anime_cover_image'] ) ) {
			$this->set_featured_image( $post_id, $anime_data['anime_cover_image'], $post_title );
		}

		$this->save_taxonomies( $post_id, $anime_data );

		update_post_meta( $post_id, '_import_source', sanitize_text_field( $source ) );
		update_post_meta( $post_id, 'anime_last_sync', current_time( 'mysql' ) );
		delete_post_meta( $post_id, '_enriched_at' );

		// ✅ ACL：依 post_id 尾數錯開 enrich 排程，避免批量匯入時同時觸發大量 API 請求。
		// 每部動畫間隔 90 秒，post_id 尾數決定在哪個 slot 執行：
		//   post_id % 40 → 0~39，乘以 90 秒 → 最多分散在 60 分鐘內完成。
		if ( ! wp_next_scheduled( 'anime_sync_enrich_post', [ $post_id ] ) ) {
			$slot  = ( $post_id % 40 );           // 0 ~ 39
			$delay = 60 + ( $slot * 90 );          // 60s ~ 3570s（約 1 分鐘 ~ 60 分鐘）
			wp_schedule_single_event( time() + $delay, 'anime_sync_enrich_post', [ $post_id ] );
		}

		$display_title   = $anime_data['anime_title_chinese'] ?: $anime_data['anime_title_romaji'] ?: "ID {$anilist_id}";
		$action_label    = $is_update ? '已更新' : '已匯入';
		$base_message    = "{$action_label} – {$display_title} (ID {$anilist_id})";
		$bangumi_missing = ! $has_bangumi;

		if ( $bangumi_missing ) {
			$base_message .= ' ⚠️ Bangumi ID 未找到，將於背景補抓';
		}

		return [
			'success'         => true,
			'message'         => $base_message,
			'post_id'         => $post_id,
			'mal_id'          => $anime_data['mal_id'] ?? 0,
			'title'           => $display_title,
			'edit_url'        => get_edit_post_link( $post_id, 'raw' ),
			'summary'         => $summary,
			'bangumi_missing' => $bangumi_missing,
			'needs_enrich'    => true,
		];
	}

	// =========================================================================
	// PUBLIC – 補抓第二段資料（ACB，供 WP-Cron 或手動觸發）
	// =========================================================================

	public function enrich_single( int $post_id ): array|\WP_Error {
		if ( get_post_meta( $post_id, '_enriched_at', true ) ) {
			return new \WP_Error( 'already_enriched', "Post {$post_id} already enriched." );
		}

		$result = $this->api_handler->enrich_anime_data( $post_id );

		if ( ! is_wp_error( $result ) ) {
			update_post_meta( $post_id, '_enriched_at', current_time( 'mysql' ) );
			delete_post_meta( $post_id, '_needs_enrich' );
		}

		return $result;
	}

	// =========================================================================
	// PUBLIC – ACD 新增：系列分析（供 Tab 4 AJAX）
	// =========================================================================

	public function analyze_series( int $anilist_id ): array|\WP_Error {
		return $this->api_handler->get_series_tree( $anilist_id );
	}

	// =========================================================================
	// PUBLIC – ACD 新增：人氣排行（供 Tab 5 AJAX）
	// =========================================================================

	public function get_popularity_ranking( int $page = 1 ): array|\WP_Error {
		return $this->api_handler->fetch_anilist_popularity( $page );
	}

	// =========================================================================
	// PUBLIC – ACD 新增：系列 Taxonomy 歸類
	// =========================================================================

	public function assign_series_taxonomy( int $post_id, string $series_name, int $root_id = 0, string $series_romaji = '' ): bool {
		if ( ! $post_id || $series_name === '' ) return false;

		$series_name = trim( $series_name );
		$term        = term_exists( $series_name, 'anime_series_tax' );

		if ( ! $term ) {
			$slug   = $series_romaji !== '' ? sanitize_title( $series_romaji ) : sanitize_title( $series_name );
			$result = wp_insert_term( $series_name, 'anime_series_tax', [ 'slug' => $slug ] );
			if ( is_wp_error( $result ) ) return false;
			$term_id = (int) $result['term_id'];
		} else {
			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
		}

		$result = wp_set_post_terms( $post_id, [ $term_id ], 'anime_series_tax', false );
		if ( is_wp_error( $result ) ) return false;

		if ( $root_id > 0 ) {
			update_post_meta( $post_id, '_series_root_anilist_id', $root_id );
		}

		return true;
	}

	// =========================================================================
	// [修改] PUBLIC – 主題曲 API 公開包裝方法
	//
	// 供 class-cron-manager.php 的 run_themes_episodes_update() 呼叫。
	// 直接委派給 api_handler->fetch_animethemes()，
	// 該方法在 class-api-handler.php 中需確保為 public 存取修飾詞。
	//
	// 回傳格式範例：
	// [
	//   'themes' => [
	//     [ 'type' => 'OP', 'sequence' => '1', 'title' => '...', 'audio_url' => '...' ],
	//     [ 'type' => 'ED', 'sequence' => '1', 'title' => '...', 'audio_url' => '...' ],
	//   ]
	// ]
	// =========================================================================

	public function fetch_themes_only( int $mal_id ): array {
		if ( $mal_id <= 0 ) {
			return [];
		}
		return $this->api_handler->fetch_animethemes( $mal_id );
	}

	// =========================================================================
	// [修改] PUBLIC – 集數列表 API 公開包裝方法
	//
	// 供 class-cron-manager.php 的 run_themes_episodes_update() 呼叫。
	// 直接委派給 api_handler->fetch_bgm_episodes()，
	// 該方法在 class-api-handler.php 中需確保為 public 存取修飾詞。
	//
	// 回傳格式範例：
	// [
	//   [ 'id' => 1001, 'ep' => 1, 'name' => '第一話', 'name_cn' => '...' ],
	//   [ 'id' => 1002, 'ep' => 2, 'name' => '第二話', 'name_cn' => '...' ],
	// ]
	// =========================================================================

	public function fetch_episodes_only( int $bangumi_id ): array {
		if ( $bangumi_id <= 0 ) {
			return [];
		}
		return $this->api_handler->fetch_bgm_episodes( $bangumi_id );
	}

	// =========================================================================
	// PRIVATE – 首次匯入鎖定欄位
	// =========================================================================

	private function apply_first_import_locks( int $post_id, array $data ): void {
		$lock_fields = [
			'anime_cover_image'      => $data['anime_cover_image'] ?? '',
			'anime_banner_image'     => $data['anime_banner_image'] ?? '',
			'anime_trailer_url'      => $data['anime_trailer_url'] ?? '',
			'anime_synopsis_chinese' => $data['anime_synopsis_chinese'] ?? '',
		];

		foreach ( $lock_fields as $key => $val ) {
			if ( $val !== '' ) {
				update_post_meta( $post_id, "_lock_{$key}", 1 );
			}
		}
	}

	// =========================================================================
	// PRIVATE – 產生 Slug
	// =========================================================================

	private function generate_slug( array $data, int $exclude_id = 0 ): string {
		$candidates = array_filter( [
			$data['anime_title_romaji'] ?? '',
			$data['anime_title_english'] ?? '',
			'anime-' . ( $data['anilist_id'] ?? 0 ),
		] );

		$raw  = reset( $candidates );
		$slug = sanitize_title( $raw );
		if ( $slug === '' ) $slug = 'anime-' . ( $data['anilist_id'] ?? 0 );

		$original = $slug;
		$suffix   = 1;

		while ( true ) {
			$found = get_page_by_path( $slug, OBJECT, 'anime' );
			if ( ! $found || ( $exclude_id > 0 && (int) $found->ID === $exclude_id ) ) {
				break;
			}
			$slug = $original . '-' . $suffix++;
		}

		return $slug;
	}

	// =========================================================================
	// PRIVATE – 儲存 Post Meta
	// =========================================================================

	private function save_post_meta( int $post_id, array $data ): void {
		$animethemes_id   = isset( $data['anime_animethemes_id'] ) ? trim( (string) $data['anime_animethemes_id'] ) : '';
		$animethemes_slug = isset( $data['anime_animethemes_slug'] )
			? trim( (string) $data['anime_animethemes_slug'] )
			: trim( (string) ( $data['animethemes_slug'] ?? '' ) );

		if ( $animethemes_id !== '' && ! ctype_digit( $animethemes_id ) && $animethemes_slug === '' ) {
			$animethemes_slug = $animethemes_id;
			$animethemes_id   = '';
		}

		$meta_map = [
			'anime_anilist_id'       => $data['anilist_id'] ?? 0,
			'anime_mal_id'           => $data['mal_id'] ?? 0,
			'anime_animethemes_id'   => $animethemes_id,
			'anime_animethemes_slug' => $animethemes_slug,
			'animethemes_slug'       => $animethemes_slug,
			'anime_title_chinese'    => $data['anime_title_chinese'] ?? '',
			'anime_title_romaji'     => $data['anime_title_romaji'] ?? '',
			'anime_title_english'    => $data['anime_title_english'] ?? '',
			'anime_title_native'     => $data['anime_title_native'] ?? '',
			'anime_format'           => $data['anime_format'] ?? '',
			'anime_status'           => $data['anime_status'] ?? '',
			'anime_season'           => strtoupper( $data['anime_season'] ?? '' ),
			'anime_season_year'      => $data['anime_season_year'] ?? 0,
			'anime_source'           => $data['anime_source'] ?? '',
			'anime_episodes'         => $data['anime_episodes'] ?? 0,
			'anime_duration'         => $data['anime_duration'] ?? 0,
			'anime_studios'          => $data['anime_studios'] ?? '',
			'anime_score_anilist'    => $data['anime_score_anilist'] ?? 0,
			'anime_score_bangumi'    => $data['anime_score_bangumi'] ?? 0,
			'anime_score_mal'        => $data['anime_score_mal'] ?? 0,
			'anime_popularity'       => $data['anime_popularity'] ?? 0,
			'anime_cover_image'      => $data['anime_cover_image'] ?? '',
			'anime_banner_image'     => $data['anime_banner_image'] ?? '',
			'anime_trailer_url'      => $data['anime_trailer_url'] ?? '',
			'anime_synopsis_chinese' => $data['anime_synopsis_chinese'] ?? '',
			'anime_synopsis_english' => $data['anime_synopsis_english'] ?? '',
			'anime_start_date'       => $data['anime_start_date'] ?? '',
			'anime_end_date'         => $data['anime_end_date'] ?? '',
			'anime_streaming'        => $data['anime_streaming'] ?? '[]',
			'anime_themes'           => $data['anime_themes'] ?? '[]',
			'anime_staff_json'       => $data['anime_staff_json'] ?? '[]',
			'anime_cast_json'        => $data['anime_cast_json'] ?? '[]',
			'anime_relations_json'   => $data['anime_relations_json'] ?? '[]',
			'anime_episodes_json'    => $data['anime_episodes_json'] ?? '[]',
			'anime_official_site'    => $data['anime_official_site'] ?? '',
			'anime_twitter_url'      => $data['anime_twitter_url'] ?? '',
			'anime_wikipedia_url'    => $data['anime_wikipedia_url'] ?? '',
			'anime_external_links'   => $data['anime_external_links'] ?? '[]',
			'anime_next_airing'      => $data['anime_next_airing'] ?? '',
			'anime_sync_time'        => current_time( 'mysql' ),
		];

		foreach ( $meta_map as $key => $value ) {
			update_post_meta( $post_id, $key, $this->prepare_meta_value( $key, $value ) );
		}

		// 停用：不要自動把 AniList externalLinks 映射到台灣欄位。
		// 台灣平台改為完全由人工在 ACF 欄位維護。
		// $this->map_streaming_to_tw_fields( $post_id, $data['anime_external_links'] ?? '[]' );

		$bgm_id_raw        = $data['bangumi_id'] ?? null;
		$bgm_id            = $bgm_id_raw !== null ? abs( intval( $bgm_id_raw ) ) : 0;
		$manually_set      = (bool) get_post_meta( $post_id, '_bangumi_id_manually_set', true );
		$existing_bgm_id   = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );
		$existing_bangumi  = $existing_bgm_id > 0 ? $existing_bgm_id : (int) get_post_meta( $post_id, 'bangumi_id', true );

		if ( $bgm_id > 0 && ! $manually_set ) {
			update_post_meta( $post_id, 'anime_bangumi_id', $bgm_id );
			update_post_meta( $post_id, 'bangumi_id', $bgm_id );
			delete_post_meta( $post_id, '_bangumi_id_pending' );
		} elseif ( ! $manually_set ) {
			if ( $existing_bangumi > 0 ) {
				update_post_meta( $post_id, 'anime_bangumi_id', $existing_bangumi );
				update_post_meta( $post_id, 'bangumi_id', $existing_bangumi );
				delete_post_meta( $post_id, '_bangumi_id_pending' );
			} else {
				delete_post_meta( $post_id, 'anime_bangumi_id' );
				delete_post_meta( $post_id, 'bangumi_id' );
				update_post_meta( $post_id, '_bangumi_id_pending', 1 );
			}
		}

		if ( ! empty( $data['_needs_enrich'] ) ) {
			update_post_meta( $post_id, '_needs_enrich', 1 );
		}
	}

	private function prepare_meta_value( string $key, $value ) {
		if ( $this->is_json_meta_key( $key ) ) {
			return is_string( $value )
				? $this->cn_converter->convert_json_string( $value )
				: wp_json_encode( $this->cn_converter->convert_mixed( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		if ( $this->is_convertible_text_meta_key( $key ) ) {
			return is_string( $value ) ? $this->cn_converter->convert( $value ) : $value;
		}

		return $value;
	}

	private function is_convertible_text_meta_key( string $key ): bool {
		return in_array( $key, [
			'anime_synopsis_chinese',
			'anime_studios',
		], true );
	}

	private function is_json_meta_key( string $key ): bool {
		return in_array( $key, [
			'anime_staff_json',
			'anime_cast_json',
			'anime_episodes_json',
		], true );
	}

	private function extract_post_fields( array $data, int $existing_id = 0 ): array {
		$content_candidates = [
			'post_content',
			'content',
			'article_content',
			'generated_content',
			'draft_content',
			'body',
		];

		$excerpt_candidates = [
			'post_excerpt',
			'excerpt',
			'article_excerpt',
			'generated_excerpt',
			'summary',
		];

		$post_fields   = [];
		$existing_post = null;

		$content = $this->pick_first_string( $data, $content_candidates );
		if ( $content !== '' ) {
			$post_fields['post_content'] = $this->cn_converter->convert( $content );
		} elseif ( $existing_id > 0 ) {
			$existing_post = get_post( $existing_id );
			if ( $existing_post && ! empty( $existing_post->post_content ) ) {
				$post_fields['post_content'] = $existing_post->post_content;
			}
		}

		$excerpt = $this->pick_first_string( $data, $excerpt_candidates );
		if ( $excerpt !== '' ) {
			$post_fields['post_excerpt'] = $this->cn_converter->convert( $excerpt );
		} elseif ( $existing_id > 0 ) {
			$existing_post = $existing_post ?: get_post( $existing_id );
			if ( $existing_post && ! empty( $existing_post->post_excerpt ) ) {
				$post_fields['post_excerpt'] = $existing_post->post_excerpt;
			}
		}

		return $post_fields;
	}

	private function pick_first_string( array $data, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
				$value = trim( $data[ $key ] );
				if ( $value !== '' ) {
					return $value;
				}
			}
		}
		return '';
	}

	// =========================================================================
	// PRIVATE – 設定特色圖片
	// =========================================================================

	private function set_featured_image( int $post_id, string $image_url, string $title ): void {
		if ( has_post_thumbnail( $post_id ) && get_post_meta( $post_id, '_lock_anime_cover_image', true ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$filename   = sanitize_file_name( 'anime-cover-' . $post_id . '-' . md5( $image_url ) . '.jpg' );
		$file_path  = $upload_dir['path'] . '/' . $filename;

		if ( ! file_exists( $file_path ) ) {
			$response = wp_remote_get( $image_url, [ 'timeout' => 15 ] );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) return;
			$image_data = wp_remote_retrieve_body( $response );
			if ( empty( $image_data ) ) return;
			file_put_contents( $file_path, $image_data );
		}

		$file_type  = wp_check_filetype( $filename );
		$attachment = [
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_text_field( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attach_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( is_wp_error( $attach_id ) ) return;

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		set_post_thumbnail( $post_id, $attach_id );
	}

	// =========================================================================
	// PRIVATE – 儲存分類法
	// =========================================================================

	private function save_taxonomies( int $post_id, array $data ): void {

		if ( ! empty( $data['anime_genres'] ) && is_array( $data['anime_genres'] ) ) {
			$genre_ids = [];
			foreach ( $data['anime_genres'] as $genre_name ) {
				$genre_name = trim( (string) $genre_name );
				if ( $genre_name === '' ) continue;
				$term = term_exists( $genre_name, 'genre' );
				if ( ! $term ) $term = wp_insert_term( $genre_name, 'genre' );
				if ( ! is_wp_error( $term ) ) {
					$genre_ids[] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				}
			}
			if ( ! empty( $genre_ids ) ) wp_set_post_terms( $post_id, $genre_ids, 'genre' );
		}

		$season_year = (int) ( $data['anime_season_year'] ?? 0 );
		$season      = strtoupper( $data['anime_season'] ?? '' );
		if ( $season_year && $season ) {
			$season_map = [
				'SPRING' => '春季',
				'SUMMER' => '夏季',
				'FALL'   => '秋季',
				'WINTER' => '冬季',
			];
			$season_zh    = $season_map[ $season ] ?? ucfirst( strtolower( $season ) );
			$season_label = "{$season_year} {$season_zh}";

			$term = term_exists( $season_label, 'anime_season_tax' );
			if ( ! $term ) $term = wp_insert_term( $season_label, 'anime_season_tax' );
			if ( ! is_wp_error( $term ) ) {
				$tid = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				wp_set_post_terms( $post_id, [ $tid ], 'anime_season_tax' );
			}
		}

		$format = $data['anime_format'] ?? '';
		if ( $format !== '' ) {
			$format_slug = strtolower( str_replace( '_', '-', $format ) );
			$term = term_exists( $format_slug, 'anime_format_tax' );
			if ( ! $term ) $term = wp_insert_term( ucfirst( $format_slug ), 'anime_format_tax', [ 'slug' => $format_slug ] );
			if ( ! is_wp_error( $term ) ) {
				$tid = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				wp_set_post_terms( $post_id, [ $tid ], 'anime_format_tax' );
			}
		}

		if ( ! empty( $data['anime_tags'] ) && is_array( $data['anime_tags'] ) ) {
			$tag_ids = [];
			foreach ( $data['anime_tags'] as $tag_name ) {
				$tag_name = trim( (string) $tag_name );
				if ( $tag_name === '' ) continue;
				$zh_name = $this->resolve_tag_name( $tag_name );
				$tag_id  = $this->find_or_create_tag( $zh_name );
				if ( $tag_id ) $tag_ids[] = $tag_id;
			}
			if ( ! empty( $tag_ids ) ) wp_set_post_terms( $post_id, $tag_ids, 'post_tag' );
		}

		$studios_raw = $data['anime_studios'] ?? '';
		if ( ! empty( $studios_raw ) ) {
			$studio_names    = array_filter( array_map( 'trim', explode( ',', $studios_raw ) ) );
			$studio_term_ids = [];
			foreach ( $studio_names as $studio_name ) {
				if ( $studio_name === '' ) continue;
				$term = term_exists( $studio_name, 'anime_studio_tax' );
				if ( ! $term ) {
					$term = wp_insert_term( $studio_name, 'anime_studio_tax', [
						'slug' => sanitize_title( $studio_name ),
					] );
				}
				if ( ! is_wp_error( $term ) ) {
					$studio_term_ids[] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				}
			}
			if ( ! empty( $studio_term_ids ) ) {
				wp_set_object_terms( $post_id, $studio_term_ids, 'anime_studio_tax', false );
			}
		}
	}

	// =========================================================================
	// PRIVATE – 解析 externalLinks 自動寫入台灣串流平台欄位
	// =========================================================================

	private function map_streaming_to_tw_fields( int $post_id, string $external_links_json ): void {
		$links = json_decode( $external_links_json, true );
		if ( ! is_array( $links ) || empty( $links ) ) return;

		$platform_map = [
			'Crunchyroll'        => 'anime_tw_streaming_url_crunchyroll',
			'Netflix'            => 'anime_tw_streaming_url_netflix',
			'Disney Plus'        => 'anime_tw_streaming_url_disney',
			'Disney+'            => 'anime_tw_streaming_url_disney',
			'Amazon Prime Video' => 'anime_tw_streaming_url_amazon',
			'Hulu'               => 'anime_tw_streaming_url_hulu',
			'HIDIVE'             => 'anime_tw_streaming_url_hidive',
			'Bilibili'           => 'anime_tw_streaming_url_bilibili',
			'YouTube'            => 'anime_tw_streaming_url_youtube',
			'WeTV'               => 'anime_tw_streaming_url_wetv',
			'Viu'                => 'anime_tw_streaming_url_viu',
			'Ani-One Asia'       => 'anime_tw_streaming_url_ani_one',
			'Muse Asia'          => 'anime_tw_streaming_url_muse',
		];

		$platform_to_checkbox = [
			'Netflix'      => 'netflix',
			'Disney Plus'  => 'disney',
			'Disney+'      => 'disney',
			'Crunchyroll'  => 'crunchyroll',
			'Ani-One Asia' => 'ani-one',
			'Muse Asia'    => 'muse',
		];

		$checked_platforms = get_post_meta( $post_id, 'anime_tw_streaming', true );
		if ( ! is_array( $checked_platforms ) ) {
			$checked_platforms = [];
		}

		$has_existing = ! empty( $checked_platforms );

		foreach ( $links as $link ) {
			$site = $link['site'] ?? '';
			$url  = $link['url']  ?? '';
			$type = strtoupper( $link['type'] ?? '' );

			if ( $site === '' || $url === '' ) continue;
			if ( $type !== '' && $type !== 'STREAMING' ) continue;

			if ( $site === 'YouTube' ) {
				if ( stripos( $url, 'AniOneAsia' ) !== false || stripos( $url, 'ani-one' ) !== false ) {
					$site = 'Ani-One Asia';
				} elseif ( stripos( $url, 'MuseAsia' ) !== false || stripos( $url, 'muse' ) !== false ) {
					$site = 'Muse Asia';
				}
			}

			if ( isset( $platform_map[ $site ] ) ) {
				$meta_key = $platform_map[ $site ];
				$existing = get_post_meta( $post_id, $meta_key, true );
				if ( empty( $existing ) ) {
					update_post_meta( $post_id, $meta_key, esc_url_raw( $url ) );
				}
			}

			if ( ! $has_existing && isset( $platform_to_checkbox[ $site ] ) ) {
				$val = $platform_to_checkbox[ $site ];
				if ( ! in_array( $val, $checked_platforms, true ) ) {
					$checked_platforms[] = $val;
				}
			}
		}

		if ( ! $has_existing && ! empty( $checked_platforms ) ) {
			update_post_meta( $post_id, 'anime_tw_streaming', array_values( $checked_platforms ) );
		}
	}

	// =========================================================================
	// PRIVATE – Tag helpers
	// =========================================================================

	private function resolve_tag_name( string $en_name ): string {
		$map = $this->get_tag_map();
		if ( isset( $map[ $en_name ] ) ) return $map[ $en_name ];
		$cache_key = 'anime_sync_tag_' . md5( $en_name );
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) return (string) $cached;
		$zh = $this->google_translate( $en_name );
		$zh = $zh ?: $en_name;
		set_transient( $cache_key, $zh, 30 * DAY_IN_SECONDS );
		return $zh;
	}

	private function google_translate( string $text ): string {
		$api_key = defined( 'GOOGLE_TRANSLATE_API_KEY' ) ? GOOGLE_TRANSLATE_API_KEY : '';
		if ( ! $api_key ) return '';
		$url = 'https://translation.googleapis.com/language/translate/v2'
			. '?q=' . rawurlencode( $text )
			. '&target=zh-TW&source=en&format=text'
			. '&key=' . rawurlencode( $api_key );
		$log_url = preg_replace( '/key=[^&]+/', 'key=***REDACTED***', $url );
		Anime_Sync_Error_Logger::log( 'debug', "Google Translate: {$log_url}" );
		$response = wp_remote_get( $url, [
			'timeout'    => 8,
			'user-agent' => 'AnimeSync-Pro/1.0 (WordPress)',
			'headers'    => [ 'Accept' => 'application/json' ],
		] );
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) return '';
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['data']['translations'][0]['translatedText'] ?? '';
	}

	private function find_or_create_tag( string $name ): ?int {
		$name = trim( $name );
		if ( $name === '' ) return null;
		$term = term_exists( $name, 'post_tag' );
		if ( ! $term ) $term = wp_insert_term( $name, 'post_tag' );
		if ( is_wp_error( $term ) ) return null;
		return is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	}

	private function get_tag_map(): array {
		return [
			'Amnesia'                      => '失憶',
			'Revenge'                      => '復仇',
			'Reincarnation'                => '轉生',
			'Time Travel'                  => '時間旅行',
			'Time Loop'                    => '時間循環',
			'Isekai'                       => '異世界',
			'Parallel World'               => '平行世界',
			'Virtual Reality'              => '虛擬實境',
			'Augmented Reality'            => '擴增實境',
			'Post-Apocalyptic'             => '末日後',
			'Dystopia'                     => '反烏托邦',
			'Utopia'                       => '烏托邦',
			'Alternate History'            => '架空歷史',
			'Historical'                   => '歷史',
			'Fictional World'              => '架空世界',
			'Space'                        => '宇宙',
			'Space Opera'                  => '太空歌劇',
			'Cyberpunk'                    => '賽博龐克',
			'Steampunk'                    => '蒸汽龐克',
			'Dieselpunk'                   => '柴油龐克',
			'Fantasy World'                => '奇幻世界',
			'High Fantasy'                 => '高奇幻',
			'Low Fantasy'                  => '低奇幻',
			'Urban Fantasy'                => '都市奇幻',
			'Mythology'                    => '神話',
			'Feudal Japan'                 => '日本戰國',
			'Anti-Hero'                    => '反英雄',
			'Villain Protagonist'          => '反派主角',
			'Overpowered Protagonist'      => '無敵主角',
			'Female Protagonist'           => '女主角',
			'Male Protagonist'             => '男主角',
			'Non-Human Protagonist'        => '非人類主角',
			'Ensemble Cast'                => '群像劇',
			'Kuudere'                      => '酷蛋',
			'Tsundere'                     => '傲嬌',
			'Yandere'                      => '病嬌',
			'Dandere'                      => '呆萌',
			'Coming of Age'                => '成長故事',
			'Redemption'                   => '救贖',
			'Found Family'                 => '羈絆家族',
			'Tragedy'                      => '悲劇',
			'Comedy'                       => '喜劇',
			'Parody'                       => '搞笑惡搞',
			'Romance'                      => '戀愛',
			'Harem'                        => '後宮',
			'Reverse Harem'                => '逆後宮',
			'Love Triangle'                => '三角戀',
			'Forbidden Love'               => '禁忌之戀',
			'Arranged Marriage'            => '包辦婚姻',
			'Slice of Life'                => '日常',
			'School Life'                  => '校園生活',
			'Work Life'                    => '職場生活',
			'Magic'                        => '魔法',
			'Superpowers'                  => '超能力',
			'Supernatural'                 => '超自然',
			'Demons'                       => '惡魔',
			'Angels'                       => '天使',
			'Vampires'                     => '吸血鬼',
			'Werewolves'                   => '狼人',
			'Ghosts'                       => '鬼魂',
			'Undead'                       => '不死族',
			'Gods'                         => '神明',
			'Spirits'                      => '精靈/靈魂',
			'Witches'                      => '女巫',
			'Curses'                       => '詛咒',
			'Alchemy'                      => '煉金術',
			'Necromancy'                   => '死靈術',
			'Action'                       => '動作',
			'Martial Arts'                 => '武術',
			'Swordplay'                    => '劍術',
			'Archery'                      => '弓術',
			'Gunfights'                    => '槍戰',
			'Mechs'                        => '機甲',
			'Military'                     => '軍事',
			'War'                          => '戰爭',
			'Battle Royale'                => '大逃殺',
			'Survival'                     => '求生',
			'Tournament'                   => '競技賽',
			'Strategy Game'                => '策略遊戲',
			'Idol'                         => '偶像',
			'Musician'                     => '音樂人',
			'Detective'                    => '偵探',
			'Police'                       => '警察',
			'Samurai'                      => '武士',
			'Ninja'                        => '忍者',
			'Pirate'                       => '海盜',
			'Doctor'                       => '醫生',
			'Teacher'                      => '教師',
			'Chef'                         => '廚師',
			'Athlete'                      => '運動員',
			'Adventurer'                   => '冒險者',
			'Guild'                        => '公會',
			'Siblings'                     => '兄弟姊妹',
			'Twins'                        => '雙胞胎',
			'Master-Servant'               => '主僕關係',
			'Senpai-Kohai'                 => '前輩後輩',
			'Childhood Friends'            => '青梅竹馬',
			'Rivals'                       => '對手',
			'Bromance'                     => '兄弟情誼',
			'Psychological'                => '心理',
			'Trauma'                       => '心理創傷',
			'Mental Illness'               => '精神疾病',
			'Social Commentary'            => '社會批評',
			'Politics'                     => '政治',
			'Philosophy'                   => '哲學',
			'Religion'                     => '宗教',
			'Gore'                         => '血腥暴力',
			'Horror'                       => '恐怖',
			'Ecchi'                        => '輕微色情',
			'Fanservice'                   => '福利',
			'Chibi'                        => '超可愛',
			'Moe'                          => '萌',
			'Cute Girls Doing Cute Things' => '日常萌系',
			'Anthropomorphism'             => '擬人化',
			'Dragons'                      => '龍',
			'Cats'                         => '貓咪',
			'Dogs'                         => '狗狗',
			'Iyashikei'                    => '療癒系',
			'CGDCT'                        => '日常萌系',
			'Music'                        => '音樂',
			'Sports'                       => '運動',
			'Racing'                       => '賽車',
			'Cooking'                      => '料理',
			'Gaming'                       => '遊戲',
			'Card Games'                   => '卡牌遊戲',
			'Mahjong'                      => '麻將',
			'Shounen'                      => '少年',
			'Shoujo'                       => '少女',
			'Seinen'                       => '青年',
			'Josei'                        => '女性向',
			'Mecha'                        => '機器人',
			'Sci-Fi'                       => '科幻',
			'Science Fiction'              => '科幻',
			'Adventure'                    => '冒險',
			'Mystery'                      => '推理',
			'Thriller'                     => '驚悚',
			'Suspense'                     => '懸疑',
			'Drama'                        => '劇情',
			'Family'                       => '家庭',
			'Kids'                         => '兒童',
		];
	}

	// =========================================================================
	// PRIVATE – 查找現有文章
	// =========================================================================

	private function find_existing( int $anilist_id ): int {
		if ( $anilist_id <= 0 ) return 0;

		$query = new WP_Query( [
			'post_type'      => 'anime',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'     => 'anime_anilist_id',
					'value'   => $anilist_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				],
			],
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}
}

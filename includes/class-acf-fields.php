<?php
/**
 * ACF Fields Registration
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/includes/class-acf-fields.php
 *
 * Bug 1  – anime_score_bangumi max 改為 100、step 改為 1
 * Bug 7  – 移除英文簡介欄位
 * Bug 8  – 所有欄位標籤鎖定為中文
 * F1     – 移除 trailer preview message 欄位及其 render script
 * F2     – 台灣串流平台改為 16 選項 multi-checkbox
 * F3     – 台灣代理商改為 select + 自訂文字欄位
 * F4     – 新增「重新同步 Bangumi」按鈕（message 欄位 + AJAX）
 * F5     – 新增唯讀 anime_episodes_json textarea
 * ACF    – 新增 16 個平台個別 URL 欄位（Option A，全部顯示，無 conditional_logic）
 * ACF    – 新增 anime_faq_json 手動 FAQ textarea
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anime_Sync_ACF_Fields {

    public function __construct() {
        add_action( 'acf/init', [ $this, 'register_all_field_groups' ] );
    }

    public function register_all_field_groups() {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        $this->register_basic_info();
        $this->register_ratings();
        $this->register_synopsis();
        $this->register_media();
        $this->register_production();
        $this->register_themes_streaming();
        $this->register_external_links();
        $this->register_taiwan_info();
        $this->register_faq();
        $this->register_sync_control();
        $this->register_resync_script();
    }

    // ═══════════════════════════════════════════════════════════
    // 基本資訊
    // ═══════════════════════════════════════════════════════════

    private function register_basic_info() {
        acf_add_local_field_group( [
            'key'      => 'group_anime_basic_info',
            'title'    => '基本資訊',
            'fields'   => [
                [
                    'key'           => 'field_anime_anilist_id',
                    'label'         => 'AniList ID',
                    'name'          => 'anime_anilist_id',
                    'type'          => 'number',
                    'min'           => 0,
                    'step'          => 1,
                    'readonly'      => 0,
                ],
                [
                    'key'           => 'field_anime_mal_id',
                    'label'         => 'MAL ID',
                    'name'          => 'anime_mal_id',
                    'type'          => 'number',
                    'min'           => 0,
                    'step'          => 1,
                ],
                [
                    'key'           => 'field_anime_bangumi_id',
                    'label'         => 'Bangumi ID',
                    'name'          => 'anime_bangumi_id',
                    'type'          => 'number',
                    'min'           => 0,
                    'step'          => 1,
                ],
                [
                    'key'           => 'field_anime_title_chinese',
                    'label'         => '中文標題',
                    'name'          => 'anime_title_chinese',
                    'type'          => 'text',
                ],
                [
                    'key'           => 'field_anime_title_native',
                    'label'         => '日文標題',
                    'name'          => 'anime_title_native',
                    'type'          => 'text',
                ],
                [
                    'key'           => 'field_anime_title_romaji',
                    'label'         => '羅馬拼音標題',
                    'name'          => 'anime_title_romaji',
                    'type'          => 'text',
                ],
                [
                    'key'           => 'field_anime_title_english',
                    'label'         => '英文標題',
                    'name'          => 'anime_title_english',
                    'type'          => 'text',
                ],
                [
                    'key'           => 'field_anime_format',
                    'label'         => '類型',
                    'name'          => 'anime_format',
                    'type'          => 'select',
                    'choices'       => [
                        'TV'                 => 'TV',
                        'TV_SHORT'           => 'TV 短篇',
                        'MOVIE'              => '劇場版',
                        'OVA'                => 'OVA',
                        'ONA'                => 'ONA',
                        'SPECIAL'            => '特別篇',
                        'MUSIC'              => '音樂 MV',
                    ],
                    'allow_null'    => 1,
                ],
                [
                    'key'           => 'field_anime_status',
                    'label'         => '狀態',
                    'name'          => 'anime_status',
                    'type'          => 'select',
                    'choices'       => [
                        'FINISHED'           => '已完結',
                        'RELEASING'          => '連載中',
                        'NOT_YET_RELEASED'   => '尚未播出',
                        'CANCELLED'          => '已取消',
                        'HIATUS'             => '暫停中',
                    ],
                    'allow_null'    => 1,
                ],
                [
                    'key'           => 'field_anime_season',
                    'label'         => '季節',
                    'name'          => 'anime_season',
                    'type'          => 'select',
                    'choices'       => [
                        'WINTER' => '冬季',
                        'SPRING' => '春季',
                        'SUMMER' => '夏季',
                        'FALL'   => '秋季',
                    ],
                    'allow_null'    => 1,
                ],
                [
                    'key'           => 'field_anime_season_year',
                    'label'         => '播出年份',
                    'name'          => 'anime_season_year',
                    'type'          => 'number',
                    'min'           => 1900,
                    'max'           => 2100,
                    'step'          => 1,
                ],
                [
                    'key'           => 'field_anime_episodes',
                    'label'         => '集數',
                    'name'          => 'anime_episodes',
                    'type'          => 'number',
                    'min'           => 0,
                    'step'          => 1,
                ],
                [
                    'key'           => 'field_anime_episodes_aired',
                    'label'         => '已播集數',
                    'name'          => 'anime_episodes_aired',
                    'type'          => 'number',
                    'min'           => 0,
                    'step'          => 1,
                ],
                [
                    'key'           => 'field_anime_duration',
                    'label'         => '每集時長（分鐘）',
                    'name'          => 'anime_duration',
                    'type'          => 'number',
                    'min'           => 0,
                    'step'          => 1,
                ],
                [
                    'key'           => 'field_anime_source',
                    'label'         => '原作來源',
                    'name'          => 'anime_source',
                    'type'          => 'select',
                    'choices'       => [
                        'ORIGINAL'            => '原創',
                        'MANGA'               => '漫畫改編',
                        'LIGHT_NOVEL'         => '輕小說',
                        'NOVEL'               => '小說',
                        'VISUAL_NOVEL'        => '視覺小說',
                        'VIDEO_GAME'          => '遊戲',
                        'WEB_MANGA'           => '網路漫畫',
                        'BOOK'                => '書籍',
                        'MUSIC'               => '音樂',
                        'GAME'                => '遊戲（其他）',
                        'LIVE_ACTION'         => '真人',
                        'MULTIMEDIA_PROJECT'  => '多媒體企劃',
                        'OTHER'               => '其他',
                    ],
                    'allow_null'    => 1,
                ],
                [
                    'key'           => 'field_anime_studios',
                    'label'         => '製作公司',
                    'name'          => 'anime_studios',
                    'type'          => 'text',
                ],
                [
                    'key'           => 'field_anime_start_date',
                    'label'         => '開始日期',
                    'name'          => 'anime_start_date',
                    'type'          => 'text',
                    'placeholder'   => 'YYYY-MM-DD',
                ],
                [
                    'key'           => 'field_anime_end_date',
                    'label'         => '結束日期',
                    'name'          => 'anime_end_date',
                    'type'          => 'text',
                    'placeholder'   => 'YYYY-MM-DD',
                ],
                [
                    'key'           => 'field_anime_popularity',
                    'label'         => '人氣（AniList）',
                    'name'          => 'anime_popularity',
                    'type'          => 'number',
                    'min'           => 0,
                    'step'          => 1,
                ],
            ],
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order'  => 10,
            'style'       => 'default',
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // 評分
    // ═══════════════════════════════════════════════════════════

    private function register_ratings() {
        acf_add_local_field_group( [
            'key'    => 'group_anime_ratings',
            'title'  => '評分',
            'fields' => [
                [
                    'key'   => 'field_anime_score_anilist',
                    'label' => 'AniList 評分（原始值 0-1000）',
                    'name'  => 'anime_score_anilist',
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 1000,
                    'step'  => 1,
                ],
                [
                    'key'   => 'field_anime_score_mal',
                    'label' => 'MAL 評分（原始值 0-100）',
                    'name'  => 'anime_score_mal',
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 100,
                    'step'  => 1,
                ],
                // Bug 1：max 改為 100，step 改為 1
                [
                    'key'   => 'field_anime_score_bangumi',
                    'label' => 'Bangumi 評分（原始值 0-100）',
                    'name'  => 'anime_score_bangumi',
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 100,
                    'step'  => 1,
                ],
            ],
            'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order' => 20,
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // 劇情簡介（Bug 7：移除英文簡介）
    // ═══════════════════════════════════════════════════════════

    private function register_synopsis() {
        acf_add_local_field_group( [
            'key'    => 'group_anime_synopsis',
            'title'  => '劇情簡介',
            'fields' => [
                [
                    'key'   => 'field_anime_synopsis_chinese',
                    'label' => '中文簡介',
                    'name'  => 'anime_synopsis_chinese',
                    'type'  => 'textarea',
                    'rows'  => 6,
                ],
                // Bug 7：英文簡介欄位已移除
            ],
            'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order' => 30,
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // 媒體（F1：移除 trailer preview message）
    // ═══════════════════════════════════════════════════════════

    private function register_media() {
        acf_add_local_field_group( [
            'key'    => 'group_anime_media',
            'title'  => '媒體',
            'fields' => [
                [
                    'key'   => 'field_anime_cover_image',
                    'label' => '封面圖 URL',
                    'name'  => 'anime_cover_image',
                    'type'  => 'url',
                ],
                [
                    'key'   => 'field_anime_banner_image',
                    'label' => '橫幅圖 URL',
                    'name'  => 'anime_banner_image',
                    'type'  => 'url',
                ],
                [
                    'key'         => 'field_anime_trailer_url',
                    'label'       => '預告片 URL（YouTube）',
                    'name'        => 'anime_trailer_url',
                    'type'        => 'text',
                    'placeholder' => 'https://www.youtube.com/watch?v=XXXXXXXXXXX',
                ],
                // F1：trailer preview message 欄位及 render script 已移除
            ],
            'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order' => 40,
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // 製作資訊（含 Staff / Cast / Relations / Episodes JSON）
    // ═══════════════════════════════════════════════════════════

    private function register_production() {
        acf_add_local_field_group( [
            'key'    => 'group_anime_production',
            'title'  => '製作資訊',
            'fields' => [
                [
                    'key'          => 'field_anime_staff_json',
                    'label'        => 'Staff JSON',
                    'name'         => 'anime_staff_json',
                    'type'         => 'textarea',
                    'rows'         => 5,
                    'instructions' => '自動同步，請勿手動編輯。格式：[{"name":"...","role":"...","image":"..."}]',
                    'readonly'     => 1,
                ],
                [
                    'key'          => 'field_anime_cast_json',
                    'label'        => 'Cast JSON',
                    'name'         => 'anime_cast_json',
                    'type'         => 'textarea',
                    'rows'         => 5,
                    'instructions' => '自動同步，請勿手動編輯。格式：[{"name":"...","image":"...","voice_actors":[{"name":"..."}]}]',
                    'readonly'     => 1,
                ],
                [
                    'key'          => 'field_anime_relations_json',
                    'label'        => '相關作品 JSON',
                    'name'         => 'anime_relations_json',
                    'type'         => 'textarea',
                    'rows'         => 5,
                    'instructions' => '自動同步，請勿手動編輯。',
                    'readonly'     => 1,
                ],
                // F5：新增唯讀 episodes JSON
                [
                    'key'          => 'field_anime_episodes_json',
                    'label'        => '集數列表 JSON',
                    'name'         => 'anime_episodes_json',
                    'type'         => 'textarea',
                    'rows'         => 8,
                    'instructions' => '自動從 Bangumi 同步，請勿手動編輯。格式：[{"ep":1,"name":"...","name_cn":"...","airdate":"YYYY-MM-DD"}]',
                    'readonly'     => 1,
                ],
            ],
            'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order' => 50,
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // 主題曲 & 串流平台（國際）
    // ═══════════════════════════════════════════════════════════

    private function register_themes_streaming() {
        acf_add_local_field_group( [
            'key'    => 'group_anime_themes_streaming',
            'title'  => '主題曲 & 國際串流',
            'fields' => [
                [
                    'key'          => 'field_anime_themes',
                    'label'        => '主題曲 JSON（AnimeThemes）',
                    'name'         => 'anime_themes',
                    'type'         => 'textarea',
                    'rows'         => 6,
                    'instructions' => '自動同步。格式：[{"type":"OP1","song_title":"...","artist":"...","audio_url":"https://a.animethemes.moe/..."}]',
                    'readonly'     => 1,
                ],
                [
                    'key'          => 'field_anime_streaming',
                    'label'        => '國際串流平台 JSON',
                    'name'         => 'anime_streaming',
                    'type'         => 'textarea',
                    'rows'         => 4,
                    'instructions' => '自動同步。格式：[{"site":"Crunchyroll","url":"https://..."}]',
                    'readonly'     => 1,
                ],
            ],
            'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order' => 60,
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // 外部連結
    // ═══════════════════════════════════════════════════════════

    private function register_external_links() {
        acf_add_local_field_group( [
            'key'    => 'group_anime_external_links',
            'title'  => '外部連結',
            'fields' => [
                [
                    'key'   => 'field_anime_official_site',
                    'label' => '官方網站',
                    'name'  => 'anime_official_site',
                    'type'  => 'url',
                ],
                [
                    'key'   => 'field_anime_twitter_url',
                    'label' => 'Twitter / X',
                    'name'  => 'anime_twitter_url',
                    'type'  => 'url',
                ],
                [
                    'key'   => 'field_anime_wikipedia_url',
                    'label' => 'Wikipedia（中文）',
                    'name'  => 'anime_wikipedia_url',
                    'type'  => 'url',
                ],
                [
                    'key'   => 'field_anime_tiktok_url',
                    'label' => 'TikTok',
                    'name'  => 'anime_tiktok_url',
                    'type'  => 'url',
                ],
            ],
            'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order' => 70,
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // 台灣資訊（F2：16 個 checkbox + ACF：16 個 URL 欄位）
    // ═══════════════════════════════════════════════════════════

    private function register_taiwan_info() {

        // 16 個平台定義
        $platforms = $this->get_tw_platforms();

        // 組合 URL 欄位（Option A：全部顯示，寬度 50%）
        $url_fields = [];
        foreach ( $platforms as $key => $label ) {
            $url_fields[] = [
                'key'         => 'field_anime_tw_streaming_url_' . str_replace( '-', '_', $key ),
                'label'       => $label . ' URL',
                'name'        => 'anime_tw_streaming_url_' . str_replace( '-', '_', $key ),
                'type'        => 'url',
                'placeholder' => 'https://',
                'wrapper'     => [ 'width' => '50' ],
                'instructions'=> '勾選上方「' . $label . '」後，可在此填入該動畫的直達連結（留空則顯示純文字）。',
            ];
        }

        acf_add_local_field_group( [
            'key'    => 'group_anime_taiwan_info',
            'title'  => '台灣資訊',
            'fields' => array_merge(
                [
                    // F2：台灣串流平台（16 選項 multi-checkbox）
                    [
                        'key'          => 'field_anime_tw_streaming',
                        'label'        => '台灣串流平台',
                        'name'         => 'anime_tw_streaming',
                        'type'         => 'checkbox',
                        'choices'      => $platforms,
                        'layout'       => 'vertical',
                        'return_format'=> 'value',
                        'instructions' => '勾選有上架的平台；下方可填入該動畫的直達 URL。',
                    ],
                ],
                // ACF Option A：16 個 URL 欄位（全部顯示，無 conditional_logic）
                $url_fields,
                [
                    // 其他串流平台（自訂文字）
                    [
                        'key'         => 'field_anime_tw_streaming_other',
                        'label'       => '其他串流平台（自訂）',
                        'name'        => 'anime_tw_streaming_other',
                        'type'        => 'text',
                        'placeholder' => '多個請用逗號分隔',
                    ],
                    // F3：台灣代理商（select）
                    [
                        'key'     => 'field_anime_tw_distributor',
                        'label'   => '台灣代理商',
                        'name'    => 'anime_tw_distributor',
                        'type'    => 'select',
                        'choices' => [
                            'muse'        => '木棉花（Muse）',
                            'medialink'   => '曼迪傳播（Medialink）',
                            'jbf'         => '日本橋文化（JBF）',
                            'righttime'   => '正確時間',
                            'gaga'        => 'GaGa OOLala',
                            'catchplay'   => 'CatchPlay',
                            'netflix'     => 'Netflix 台灣',
                            'disney'      => 'Disney+ 台灣',
                            'kktv'        => 'KKTV',
                            'crunchyroll' => 'Crunchyroll',
                            'ani-one'     => 'Ani-One Asia',
                            'other'       => '其他（自訂）',
                        ],
                        'allow_null'   => 1,
                        'default_value'=> '',
                    ],
                    // F3：代理商自訂文字（選「其他」時填寫）
                    [
                        'key'         => 'field_anime_tw_distributor_custom',
                        'label'       => '台灣代理商（自訂名稱）',
                        'name'        => 'anime_tw_distributor_custom',
                        'type'        => 'text',
                        'placeholder' => '選擇「其他」時填寫',
                        'instructions'=> '僅在上方選「其他（自訂）」時生效。',
                    ],
                    // 台灣播出資訊
                    [
                        'key'         => 'field_anime_tw_broadcast',
                        'label'       => '台灣播出資訊',
                        'name'        => 'anime_tw_broadcast',
                        'type'        => 'text',
                        'placeholder' => '例：每週四 22:00',
                    ],
                ]
            ),
            'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order' => 80,
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // FAQ（ACF：完全人工，anime_faq_json textarea）
    // ═══════════════════════════════════════════════════════════

    private function register_faq() {
        acf_add_local_field_group( [
            'key'    => 'group_anime_faq',
            'title'  => '常見問題（FAQ）',
            'fields' => [
                [
                    'key'          => 'field_anime_faq_json',
                    'label'        => 'FAQ JSON',
                    'name'         => 'anime_faq_json',
                    'type'         => 'textarea',
                    'rows'         => 8,
                    'instructions' => '完全人工輸入，留空則不顯示 FAQ 區塊。格式：
[
  {"q": "問題一", "a": "答案一"},
  {"q": "問題二", "a": "答案二"}
]',
                    'placeholder'  => '[{"q":"問題","a":"答案"}]',
                ],
            ],
            'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order' => 85,
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // 同步控制（F4：重新同步 Bangumi 按鈕）
    // ═══════════════════════════════════════════════════════════

    private function register_sync_control() {
        acf_add_local_field_group( [
            'key'    => 'group_anime_sync_control',
            'title'  => '同步控制',
            'fields' => [
                [
                    'key'          => 'field_anime_last_sync',
                    'label'        => '最後同步時間',
                    'name'         => 'anime_last_sync',
                    'type'         => 'text',
                    'readonly'     => 1,
                    'instructions' => '系統自動更新，請勿手動修改。',
                ],
                [
                    'key'          => 'field_anime_import_status',
                    'label'        => '匯入狀態',
                    'name'         => 'anime_import_status',
                    'type'         => 'text',
                    'readonly'     => 1,
                ],
                // 鎖定欄位（多選）
                [
                    'key'          => 'field_anime_locked_fields',
                    'label'        => '鎖定欄位（不自動覆蓋）',
                    'name'         => 'anime_locked_fields',
                    'type'         => 'checkbox',
                    'choices'      => $this->get_auto_update_fields_labeled(),
                    'layout'       => 'vertical',
                    'return_format'=> 'value',
                    'instructions' => '勾選後，重新同步時該欄位將不會被覆蓋。',
                ],
                // F4：重新同步 Bangumi 按鈕
                [
                    'key'          => 'field_anime_resync_bangumi_btn',
                    'label'        => '重新同步 Bangumi',
                    'name'         => 'anime_resync_bangumi_btn',
                    'type'         => 'message',
                    'message'      => '<button type="button" id="anime-resync-bangumi-btn" class="button button-secondary">🔄 重新同步 Bangumi 資料</button>
<span id="anime-resync-bangumi-msg" style="margin-left:10px;"></span>',
                    'instructions' => '點擊後將從 Bangumi 重新抓取：中文標題、簡介、封面、評分、Staff、Cast、集數。',
                ],
            ],
            'location'   => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'menu_order' => 90,
        ] );
    }

    // ═══════════════════════════════════════════════════════════
    // F4：重新同步 Bangumi AJAX Script
    // ═══════════════════════════════════════════════════════════

    private function register_resync_script() {
        add_action( 'acf/input/admin_footer', function () {
            $screen = get_current_screen();
            if ( ! $screen || $screen->post_type !== 'anime' ) return;
            ?>
            <script>
            (function($) {
                $('#anime-resync-bangumi-btn').on('click', function() {
                    var $btn  = $(this);
                    var $msg  = $('#anime-resync-bangumi-msg');
                    var postId    = $('#post_ID').val();
                    var bangumiId = $('input[name="anime_bangumi_id"]').val()
                                 || $('#acf-field_anime_bangumi_id').val();

                    if (!bangumiId) {
                        $msg.css('color','red').text('請先填入 Bangumi ID');
                        return;
                    }

                    $btn.prop('disabled', true);
                    $msg.css('color','#666').text('同步中…');

                    $.post(ajaxurl, {
                        action    : 'anime_resync_bangumi',
                        nonce     : '<?php echo esc_js( wp_create_nonce( "anime_sync_nonce" ) ); ?>',
                        post_id   : postId,
                        bangumi_id: bangumiId
                    }, function(res) {
                        if (res.success) {
                            $msg.css('color','green').text('✅ ' + res.data);
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            $msg.css('color','red').text('❌ ' + res.data);
                        }
                    }).fail(function(){
                        $msg.css('color','red').text('❌ 網路錯誤，請重試');
                    }).always(function(){
                        $btn.prop('disabled', false);
                    });
                });
            })(jQuery);
            </script>
            <?php
        } );
    }

    // ═══════════════════════════════════════════════════════════
    // Helper：16 個台灣串流平台
    // ═══════════════════════════════════════════════════════════

    private function get_tw_platforms() {
        return [
            'bahamut'     => '巴哈姆特動畫瘋',
            'netflix'     => 'Netflix',
            'disney'      => 'Disney+',
            'amazon'      => 'Amazon Prime Video',
            'kktv'        => 'KKTV',
            'friday'      => 'friDay 影音',
            'catchplay'   => 'CatchPlay+',
            'bilibili'    => 'Bilibili 台灣',
            'crunchyroll' => 'Crunchyroll',
            'hulu'        => 'Hulu',
            'hidive'      => 'HIDIVE',
            'ani-one'     => 'Ani-One',
            'muse'        => 'Muse 木棉花',
            'viu'         => 'Viu',
            'wetv'        => 'WeTV',
            'youtube'     => 'YouTube（官方頻道）',
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Helper：可自動更新的欄位（供鎖定欄位 checkbox 使用）
    // ═══════════════════════════════════════════════════════════

    private function get_auto_update_fields_labeled() {
        return [
            'anime_title_chinese'     => '中文標題',
            'anime_title_native'      => '日文標題',
            'anime_title_romaji'      => '羅馬拼音標題',
            'anime_title_english'     => '英文標題',
            'anime_synopsis_chinese'  => '中文簡介',
            'anime_cover_image'       => '封面圖',
            'anime_banner_image'      => '橫幅圖',
            'anime_score_anilist'     => 'AniList 評分',
            'anime_score_mal'         => 'MAL 評分',
            'anime_score_bangumi'     => 'Bangumi 評分',
            'anime_staff_json'        => 'Staff JSON',
            'anime_cast_json'         => 'Cast JSON',
            'anime_episodes_json'     => '集數列表 JSON',
            'anime_themes'            => '主題曲 JSON',
            'anime_streaming'         => '國際串流 JSON',
            'anime_wikipedia_url'     => 'Wikipedia URL',
            'anime_start_date'        => '開始日期',
            'anime_end_date'          => '結束日期',
            'anime_studios'           => '製作公司',
            'anime_format'            => '類型',
            'anime_status'            => '狀態',
            'anime_episodes'          => '集數',
            'anime_duration'          => '每集時長',
            'anime_source'            => '原作來源',
            'anime_popularity'        => '人氣',
        ];
    }
}

new Anime_Sync_ACF_Fields();

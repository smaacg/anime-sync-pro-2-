<?php
/**
 * Class Anime_Sync_ACF_Fields
 *
 * Bug fixes in this version:
 *   Bug 1  – anime_score_bangumi max 改為 100，step 改為 1
 *   Bug 5  – YouTube 預告片 JS 移到 admin_footer，避免 ACF message 欄位跳脫標籤
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_ACF_Fields {

    public function __construct() {
        add_action( 'acf/init', [ $this, 'register_field_groups' ] );
        // Bug 5: JS 移到 admin_footer
        add_action( 'admin_footer', [ $this, 'render_trailer_preview_script' ] );
    }

    // =========================================================================
    // 註冊所有 ACF 欄位群組
    // =========================================================================

    public function register_field_groups(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        $this->register_basic_info();
        $this->register_ratings();
        $this->register_synopsis();
        $this->register_media();
        $this->register_production();
        $this->register_themes_streaming();
        $this->register_external_links();
        $this->register_taiwan_info();
        $this->register_sync_control();
    }

    // =========================================================================
    // 1. 基本資訊
    // =========================================================================

    private function register_basic_info(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_basic',
            'title'    => '基本資訊',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'normal',
            'style'    => 'default',
            'fields'   => [
                [
                    'key'          => 'field_anime_anilist_id',
                    'label'        => 'AniList ID',
                    'name'         => 'anime_anilist_id',
                    'type'         => 'number',
                    'instructions' => '來自 AniList 的動畫 ID',
                    'required'     => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_mal_id',
                    'label'        => 'MyAnimeList ID',
                    'name'         => 'anime_mal_id',
                    'type'         => 'number',
                    'instructions' => '來自 MyAnimeList 的動畫 ID',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_bangumi_id',
                    'label'        => 'Bangumi ID',
                    'name'         => 'anime_bangumi_id',
                    'type'         => 'number',
                    'instructions' => '來自 Bangumi 的動畫 ID',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_animethemes_id',
                    'label'        => 'AnimeThemes ID',
                    'name'         => 'anime_animethemes_id',
                    'type'         => 'text',
                    'instructions' => '來自 AnimeThemes 的動畫 slug',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_title_chinese',
                    'label'        => '中文標題',
                    'name'         => 'anime_title_chinese',
                    'type'         => 'text',
                    'instructions' => '繁體中文標題（來自 Bangumi，經簡繁轉換）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_title_native',
                    'label'        => '日文原名',
                    'name'         => 'anime_title_native',
                    'type'         => 'text',
                    'instructions' => '日文原始標題',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_title_romaji',
                    'label'        => 'Romaji 標題',
                    'name'         => 'anime_title_romaji',
                    'type'         => 'text',
                    'instructions' => '羅馬拼音標題（來自 AniList）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_title_english',
                    'label'        => '英文標題',
                    'name'         => 'anime_title_english',
                    'type'         => 'text',
                    'instructions' => '英文標題（來自 AniList）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_format',
                    'label'        => '動畫類型',
                    'name'         => 'anime_format',
                    'type'         => 'select',
                    'instructions' => '動畫格式',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'choices'      => [
                        'TV'       => 'TV',
                        'TV_SHORT' => 'TV 短篇',
                        'MOVIE'    => '劇場版',
                        'OVA'      => 'OVA',
                        'ONA'      => 'ONA',
                        'SPECIAL'  => '特別篇',
                        'MUSIC'    => '音樂 MV',
                    ],
                    'allow_null'   => 1,
                ],
                [
                    'key'          => 'field_anime_status',
                    'label'        => '播出狀態',
                    'name'         => 'anime_status',
                    'type'         => 'select',
                    'instructions' => '目前播出狀態',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'choices'      => [
                        'FINISHED'          => '已完結',
                        'RELEASING'         => '連載中',
                        'NOT_YET_RELEASED'  => '尚未播出',
                        'CANCELLED'         => '已取消',
                        'HIATUS'            => '暫停中',
                    ],
                    'allow_null'   => 1,
                ],
                [
                    'key'          => 'field_anime_source',
                    'label'        => '原作來源',
                    'name'         => 'anime_source',
                    'type'         => 'select',
                    'instructions' => '動畫原作來源',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'choices'      => [
                        'ORIGINAL'            => '原創',
                        'MANGA'               => '漫畫',
                        'LIGHT_NOVEL'         => '輕小說',
                        'NOVEL'               => '小說',
                        'VISUAL_NOVEL'        => '視覺小說',
                        'VIDEO_GAME'          => '遊戲',
                        'WEB_MANGA'           => '網路漫畫',
                        'BOOK'                => '書籍',
                        'MUSIC'               => '音樂',
                        'GAME'                => '遊戲',
                        'LIVE_ACTION'         => '真人',
                        'MULTIMEDIA_PROJECT'  => '多媒體企劃',
                        'OTHER'               => '其他',
                    ],
                    'allow_null'   => 1,
                ],
                [
                    'key'          => 'field_anime_season',
                    'label'        => '播出季節',
                    'name'         => 'anime_season',
                    'type'         => 'select',
                    'instructions' => '播出季節（儲存為大寫英文）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'choices'      => [
                        'WINTER' => '冬季 (1月)',
                        'SPRING' => '春季 (4月)',
                        'SUMMER' => '夏季 (7月)',
                        'FALL'   => '秋季 (10月)',
                    ],
                    'allow_null'   => 1,
                ],
                [
                    'key'          => 'field_anime_season_year',
                    'label'        => '播出年份',
                    'name'         => 'anime_season_year',
                    'type'         => 'number',
                    'instructions' => '播出年份（如 2024）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'min'          => 1900,
                    'max'          => 2100,
                ],
                [
                    'key'          => 'field_anime_episodes',
                    'label'        => '集數',
                    'name'         => 'anime_episodes',
                    'type'         => 'number',
                    'instructions' => '總集數（未知填 0）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'min'          => 0,
                ],
                [
                    'key'          => 'field_anime_duration',
                    'label'        => '每集時長（分鐘）',
                    'name'         => 'anime_duration',
                    'type'         => 'number',
                    'instructions' => '每集播出時長（分鐘）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'min'          => 0,
                ],
                [
                    'key'          => 'field_anime_start_date',
                    'label'        => '開始播出日期',
                    'name'         => 'anime_start_date',
                    'type'         => 'text',
                    'instructions' => '格式：YYYY-MM-DD',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_end_date',
                    'label'        => '結束播出日期',
                    'name'         => 'anime_end_date',
                    'type'         => 'text',
                    'instructions' => '格式：YYYY-MM-DD',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_next_airing',
                    'label'        => '下集播出資訊',
                    'name'         => 'anime_next_airing',
                    'type'         => 'textarea',
                    'instructions' => 'JSON 格式：{"airingAt": Unix時間戳, "episode": 集數}',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                    'readonly'     => 1,
                ],
            ],
        ] );
    }

    // =========================================================================
    // 2. 評分資訊
    // =========================================================================

    private function register_ratings(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_ratings',
            'title'    => '評分資訊',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'normal',
            'style'    => 'default',
            'fields'   => [
                [
                    'key'          => 'field_anime_score_anilist',
                    'label'        => 'AniList 評分',
                    'name'         => 'anime_score_anilist',
                    'type'         => 'number',
                    'instructions' => '範圍 0–100（AniList 原始值，前台除以 10 顯示）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'min'          => 0,
                    'max'          => 100,
                    'step'         => 1,
                ],
                [
                    'key'          => 'field_anime_score_mal',
                    'label'        => 'MyAnimeList 評分',
                    'name'         => 'anime_score_mal',
                    'type'         => 'number',
                    'instructions' => '範圍 0–10（MAL 原始值，直接顯示）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'min'          => 0,
                    'max'          => 10,
                    'step'         => 0.01,
                ],
                [
                    // Bug 1 修正：Bangumi 評分儲存為原始值 ×10（如 6.9 → 69）
                    // 因此 max 改為 100，step 改為 1，前台顯示時除以 10
                    'key'          => 'field_anime_score_bangumi',
                    'label'        => 'Bangumi 評分',
                    'name'         => 'anime_score_bangumi',
                    'type'         => 'number',
                    'instructions' => '範圍 0–100（Bangumi 原始值 ×10 儲存，前台除以 10 顯示）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'min'          => 0,
                    'max'          => 100,
                    'step'         => 1,
                ],
                [
                    'key'          => 'field_anime_popularity',
                    'label'        => '人氣指數',
                    'name'         => 'anime_popularity',
                    'type'         => 'number',
                    'instructions' => 'AniList 人氣數值',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                    'min'          => 0,
                ],
            ],
        ] );
    }

    // =========================================================================
    // 3. 劇情簡介
    // =========================================================================

    private function register_synopsis(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_synopsis',
            'title'    => '劇情簡介',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'normal',
            'style'    => 'default',
            'fields'   => [
                [
                    'key'          => 'field_anime_synopsis_chinese',
                    'label'        => '中文簡介',
                    'name'         => 'anime_synopsis_chinese',
                    'type'         => 'textarea',
                    'instructions' => '繁體中文劇情簡介（來自 Bangumi，經簡繁轉換）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 6,
                ],
                [
                    'key'          => 'field_anime_synopsis_english',
                    'label'        => '英文簡介',
                    'name'         => 'anime_synopsis_english',
                    'type'         => 'textarea',
                    'instructions' => '英文劇情簡介（來自 AniList）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 6,
                ],
            ],
        ] );
    }

    // =========================================================================
    // 4. 媒體素材
    // =========================================================================

    private function register_media(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_media',
            'title'    => '媒體素材',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'normal',
            'style'    => 'default',
            'fields'   => [
                [
                    'key'          => 'field_anime_cover_image',
                    'label'        => '封面圖片 URL',
                    'name'         => 'anime_cover_image',
                    'type'         => 'url',
                    'instructions' => '封面圖片網址（來自 AniList extraLarge）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_banner_image',
                    'label'        => '橫幅圖片 URL',
                    'name'         => 'anime_banner_image',
                    'type'         => 'url',
                    'instructions' => '橫幅圖片網址（來自 AniList bannerImage）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_trailer_url',
                    'label'        => '預告片網址',
                    'name'         => 'anime_trailer_url',
                    'type'         => 'textarea',
                    'instructions' => '每行或逗號分隔一個 YouTube 網址',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 3,
                ],
                [
                    // Bug 5 修正：只輸出容器 div，JS 由 admin_footer 輸出
                    'key'          => 'field_anime_trailer_preview',
                    'label'        => '預告片預覽',
                    'name'         => 'anime_trailer_preview',
                    'type'         => 'message',
                    'instructions' => '',
                    'message'      => '<div id="anime-trailer-preview-wrap"></div>',
                    'wrapper'      => [ 'width' => '100' ],
                ],
            ],
        ] );
    }

    // =========================================================================
    // 5. 製作資訊
    // =========================================================================

    private function register_production(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_production',
            'title'    => '製作資訊',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'normal',
            'style'    => 'default',
            'fields'   => [
                [
                    // Issue L 修正：欄位名稱改為 anime_studios
                    'key'          => 'field_anime_studios',
                    'label'        => '製作公司',
                    'name'         => 'anime_studios',
                    'type'         => 'text',
                    'instructions' => '主要製作公司（逗號分隔）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                ],
                [
                    'key'          => 'field_anime_staff_json',
                    'label'        => '製作人員 JSON',
                    'name'         => 'anime_staff_json',
                    'type'         => 'textarea',
                    'instructions' => 'JSON 格式，自動同步，請勿手動修改',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 4,
                    'readonly'     => 1,
                ],
                [
                    'key'          => 'field_anime_cast_json',
                    'label'        => '角色聲優 JSON',
                    'name'         => 'anime_cast_json',
                    'type'         => 'textarea',
                    'instructions' => 'JSON 格式，自動同步，請勿手動修改',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 4,
                    'readonly'     => 1,
                ],
                [
                    'key'          => 'field_anime_relations_json',
                    'label'        => '關聯作品 JSON',
                    'name'         => 'anime_relations_json',
                    'type'         => 'textarea',
                    'instructions' => 'JSON 格式，自動同步，請勿手動修改',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 4,
                    'readonly'     => 1,
                ],
            ],
        ] );
    }

    // =========================================================================
    // 6. 主題曲與串流平台
    // =========================================================================

    private function register_themes_streaming(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_themes_streaming',
            'title'    => '主題曲與串流平台',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'normal',
            'style'    => 'default',
            'fields'   => [
                [
                    // Issue J 修正：欄位名稱改為 anime_themes
                    'key'          => 'field_anime_themes',
                    'label'        => '主題曲 JSON',
                    'name'         => 'anime_themes',
                    'type'         => 'textarea',
                    'instructions' => 'JSON 格式（OP/ED），自動同步，請勿手動修改',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 4,
                    'readonly'     => 1,
                ],
                [
                    // Issue K 修正：欄位名稱改為 anime_streaming
                    'key'          => 'field_anime_streaming',
                    'label'        => '串流平台 JSON',
                    'name'         => 'anime_streaming',
                    'type'         => 'textarea',
                    'instructions' => 'JSON 格式（平台清單），自動同步，請勿手動修改',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 4,
                    'readonly'     => 1,
                ],
            ],
        ] );
    }

    // =========================================================================
    // 7. 外部連結
    // =========================================================================

    private function register_external_links(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_external_links',
            'title'    => '外部連結',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'normal',
            'style'    => 'default',
            'fields'   => [
                [
                    'key'          => 'field_anime_official_site',
                    'label'        => '官方網站',
                    'name'         => 'anime_official_site',
                    'type'         => 'url',
                    'instructions' => '官方網站網址',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_twitter_url',
                    'label'        => 'Twitter / X',
                    'name'         => 'anime_twitter_url',
                    'type'         => 'url',
                    'instructions' => 'Twitter 或 X 官方帳號網址',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_wikipedia_url',
                    'label'        => 'Wikipedia',
                    'name'         => 'anime_wikipedia_url',
                    'type'         => 'url',
                    'instructions' => 'Wikipedia 繁體中文頁面網址（自動抓取）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_tiktok_url',
                    'label'        => 'TikTok',
                    'name'         => 'anime_tiktok_url',
                    'type'         => 'url',
                    'instructions' => 'TikTok 官方帳號網址（手動填寫）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
            ],
        ] );
    }

    // =========================================================================
    // 8. 台灣在地資訊
    // =========================================================================

    private function register_taiwan_info(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_taiwan',
            'title'    => '台灣在地資訊',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'normal',
            'style'    => 'default',
            'fields'   => [
                [
                    'key'          => 'field_anime_tw_distributor',
                    'label'        => '台灣代理商',
                    'name'         => 'anime_tw_distributor',
                    'type'         => 'text',
                    'instructions' => '台灣代理或發行商名稱',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_tw_broadcast',
                    'label'        => '台灣播出時間',
                    'name'         => 'anime_tw_broadcast',
                    'type'         => 'text',
                    'instructions' => '台灣播出頻道與時間',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_tw_streaming',
                    'label'        => '台灣串流平台',
                    'name'         => 'anime_tw_streaming',
                    'type'         => 'textarea',
                    'instructions' => '台灣可收看的串流平台（每行一個）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 3,
                ],
            ],
        ] );
    }

    // =========================================================================
    // 9. 同步控制
    // =========================================================================

    private function register_sync_control(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_sync',
            'title'    => '同步控制',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'side',
            'style'    => 'default',
            'fields'   => [
                [
                    'key'          => 'field_anime_last_sync',
                    'label'        => '最後同步時間',
                    'name'         => 'anime_last_sync',
                    'type'         => 'text',
                    'instructions' => '最後一次從 API 同步的時間',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'readonly'     => 1,
                ],
                [
                    'key'          => 'field_anime_locked_fields',
                    'label'        => '鎖定欄位（不自動更新）',
                    'name'         => 'anime_locked_fields',
                    'type'         => 'checkbox',
                    'instructions' => '勾選後，該欄位不會被自動同步覆蓋',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'choices'      => array_combine(
                        self::get_auto_update_fields(),
                        self::get_auto_update_fields()
                    ),
                ],
            ],
        ] );
    }

    // =========================================================================
    // Bug 5：YouTube 預告片預覽 JS（移到 admin_footer）
    // =========================================================================

    public function render_trailer_preview_script(): void {
        // 只在 anime CPT 編輯頁面輸出
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'anime' ) return;
        ?>
        <script>
        (function () {
            'use strict';

            function extractYouTubeId(url) {
                var m = url.match(/(?:v=|youtu\.be\/|embed\/|v\/)([A-Za-z0-9_-]{11})/);
                return m ? m[1] : null;
            }

            function buildIframe(videoId) {
                return '<div style="display:inline-block;margin:4px;">' +
                    '<iframe width="280" height="158" ' +
                    'src="https://www.youtube.com/embed/' + videoId + '?rel=0" ' +
                    'frameborder="0" allowfullscreen loading="lazy"></iframe>' +
                    '</div>';
            }

            function renderPreviews() {
                var textarea = document.querySelector("textarea[name='anime_trailer_url']");
                var wrap     = document.getElementById('anime-trailer-preview-wrap');
                if (!textarea || !wrap) return;

                var raw  = textarea.value || '';
                var urls = raw.split(/[,\n]+/).map(function (s) {
                    return s.trim();
                }).filter(Boolean);

                if (!urls.length) {
                    wrap.innerHTML = '<p style="color:#999;">尚無預告片網址。</p>';
                    return;
                }

                var html = '';
                urls.forEach(function (url) {
                    var vid = extractYouTubeId(url);
                    if (vid) html += buildIframe(vid);
                });

                wrap.innerHTML = html || '<p style="color:#999;">無法解析 YouTube 網址。</p>';
            }

            function init() {
                renderPreviews();
                var textarea = document.querySelector("textarea[name='anime_trailer_url']");
                if (textarea) {
                    textarea.addEventListener('input', renderPreviews);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                setTimeout(init, 300);
            }
        })();
        </script>
        <?php
    }

    // =========================================================================
    // Static Helpers
    // =========================================================================

    public static function get_auto_update_fields(): array {
        return [
            'anime_score_anilist',
            'anime_score_mal',
            'anime_score_bangumi',
            'anime_popularity',
            'anime_status',
            'anime_episodes',
            'anime_next_airing',
            'anime_streaming',
            'anime_themes',
            'anime_staff_json',
            'anime_cast_json',
            'anime_relations_json',
            'anime_cover_image',
            'anime_banner_image',
            'anime_trailer_url',
            'anime_synopsis_chinese',
            'anime_synopsis_english',
            'anime_official_site',
            'anime_twitter_url',
            'anime_wikipedia_url',
            'anime_studios',
            'anime_start_date',
            'anime_end_date',
        ];
    }

    public static function get_one_time_fields(): array {
        return [
            'anime_anilist_id',
            'anime_mal_id',
            'anime_bangumi_id',
            'anime_animethemes_id',
            'anime_title_chinese',
            'anime_title_native',
            'anime_title_romaji',
            'anime_title_english',
            'anime_format',
            'anime_source',
            'anime_season',
            'anime_season_year',
        ];
    }

    public static function is_field_locked( int $post_id, string $meta_key ): bool {
        $locked = get_post_meta( $post_id, 'anime_locked_fields', true );
        if ( empty( $locked ) || ! is_array( $locked ) ) return false;
        return in_array( $meta_key, $locked, true );
    }
}

<?php
/**
 * Class Anime_Sync_ACF_Fields
 *
 * Bug fixes in this version:
 *   Bug 1  – anime_score_bangumi max 改為 100，step 改為 1
 *   Bug 7  – 移除英文簡介欄位
 *   Bug 8  – 鎖定欄位顯示中文標籤
 *   F1     – 移除 trailer preview message 欄位及 render_trailer_preview_script()
 *   F2     – 台灣串流平台改為多選 checkbox（16 個選項）
 *   F3     – 台灣代理商改為 select + 自訂文字欄位
 *   F4     – 新增「重新同步 Bangumi」按鈕（message 欄位 + AJAX）
 *   F5     – 新增 anime_episodes_json 欄位（唯讀 textarea）
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_ACF_Fields {

    public function __construct() {
        add_action( 'acf/init',      [ $this, 'register_field_groups' ] );
        add_action( 'admin_footer',  [ $this, 'render_resync_bangumi_script' ] );
        add_action( 'wp_ajax_anime_sync_resync_bangumi', [ $this, 'ajax_resync_bangumi' ] );
    }

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
                    'instructions' => '來自 Bangumi 的動畫 ID（可手動填入後點「重新同步 Bangumi」）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_animethemes_id',
                    'label'        => 'AnimeThemes Slug',
                    'name'         => 'anime_animethemes_id',
                    'type'         => 'text',
                    'instructions' => '來自 AnimeThemes 的動畫 slug（自動同步）',
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
                        'FINISHED'         => '已完結',
                        'RELEASING'        => '連載中',
                        'NOT_YET_RELEASED' => '尚未播出',
                        'CANCELLED'        => '已取消',
                        'HIATUS'           => '暫停中',
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
                        'ORIGINAL'           => '原創',
                        'MANGA'              => '漫畫',
                        'LIGHT_NOVEL'        => '輕小說',
                        'NOVEL'              => '小說',
                        'VISUAL_NOVEL'       => '視覺小說',
                        'VIDEO_GAME'         => '遊戲',
                        'WEB_MANGA'          => '網路漫畫',
                        'BOOK'               => '書籍',
                        'MUSIC'              => '音樂',
                        'GAME'               => '遊戲',
                        'LIVE_ACTION'        => '真人',
                        'MULTIMEDIA_PROJECT' => '多媒體企劃',
                        'OTHER'              => '其他',
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
                    'instructions' => 'JSON 格式：{"airingAt": Unix時間戳, "episode": 集數}（自動同步，已完結為空）',
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
                    // Bug 1 修正：max 改為 100，step 改為 1，前台除以 10 顯示
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
    // 3. 劇情簡介（Bug 7：移除英文簡介欄位）
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
                // Bug 7：英文簡介欄位已移除，不在後台顯示
                // anime_synopsis_english 仍由 API 寫入資料庫供其他用途
            ],
        ] );
    }

    // =========================================================================
    // 4. 媒體素材（F1：移除預告片 preview message 欄位）
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
                // F1：移除 field_anime_trailer_preview message 欄位
                // render_trailer_preview_script() 也已一併移除
            ],
        ] );
    }

    // =========================================================================
    // 5. 製作資訊（F5：新增 anime_episodes_json）
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
                [
                    // F5：新增 Bangumi 集數列表欄位
                    'key'          => 'field_anime_episodes_json',
                    'label'        => '集數列表 JSON',
                    'name'         => 'anime_episodes_json',
                    'type'         => 'textarea',
                    'instructions' => 'JSON 格式（來自 Bangumi），自動同步，請勿手動修改',
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
                    'key'          => 'field_anime_streaming',
                    'label'        => '串流平台 JSON',
                    'name'         => 'anime_streaming',
                    'type'         => 'textarea',
                    'instructions' => 'JSON 格式（自動從 AniList 同步），請勿手動修改',
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
    // 8. 台灣在地資訊（F2：串流 checkbox；F3：代理商 select + 自訂）
    // =========================================================================

    private function register_taiwan_info(): void {
        acf_add_local_field_group( [
            'key'      => 'group_anime_taiwan',
            'title'    => '台灣在地資訊',
            'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ] ],
            'position' => 'normal',
            'style'    => 'default',
            'fields'   => [

                // F2：台灣串流平台 → 多選 checkbox
                [
                    'key'          => 'field_anime_tw_streaming',
                    'label'        => '台灣串流平台',
                    'name'         => 'anime_tw_streaming',
                    'type'         => 'checkbox',
                    'instructions' => '可複選；特殊平台請手動於「其他串流平台」欄填寫',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'choices'      => [
                        'bahamut'    => '巴哈姆特動畫瘋',
                        'netflix'    => 'Netflix',
                        'disney'     => 'Disney+',
                        'amazon'     => 'Amazon Prime Video',
                        'kktv'       => 'KKTV',
                        'friday'     => 'friDay 影音',
                        'catchplay'  => 'CatchPlay+',
                        'bilibili'   => 'Bilibili 台灣',
                        'crunchyroll'=> 'Crunchyroll',
                        'hulu'       => 'Hulu',
                        'hidive'     => 'HIDIVE',
                        'ani-one'    => 'Ani-One',
                        'muse'       => 'Muse 木棉花',
                        'viu'        => 'Viu',
                        'wetv'       => 'WeTV',
                        'youtube'    => 'YouTube（官方頻道）',
                    ],
                    'layout'       => 'horizontal',
                    'toggle'       => 0,
                    'return_format'=> 'value',
                ],

                // F2：其他串流平台（手動補充）
                [
                    'key'          => 'field_anime_tw_streaming_other',
                    'label'        => '其他串流平台',
                    'name'         => 'anime_tw_streaming_other',
                    'type'         => 'text',
                    'instructions' => '清單以外的台灣串流平台，手動填寫（如：MyVideo、LineTV）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                ],

                // F3：台灣代理商 → select
                [
                    'key'          => 'field_anime_tw_distributor',
                    'label'        => '台灣代理商',
                    'name'         => 'anime_tw_distributor',
                    'type'         => 'select',
                    'instructions' => '選擇台灣代理或發行商；不在清單中請選「其他」並填寫下方欄位',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                    'choices'      => [
                        ''           => '— 請選擇 —',
                        'muse'       => '木棉花（Muse）',
                        'medialink'  => '曼迪傳播（Medialink）',
                        'jbf'        => '日本橋文化（JBF）',
                        'righttime'  => '正確時間',
                        'gaga'       => 'GaGa OOLala',
                        'catchplay'  => 'CatchPlay',
                        'netflix'    => 'Netflix 台灣',
                        'disney'     => 'Disney+ 台灣',
                        'kktv'       => 'KKTV',
                        'crunchyroll'=> 'Crunchyroll',
                        'ani-one'    => 'Ani-One Asia',
                        'other'      => '其他（請填寫下方）',
                    ],
                    'allow_null'   => 1,
                    'default_value'=> '',
                ],

                // F3：自訂代理商（僅選「其他」時填寫）
                [
                    'key'               => 'field_anime_tw_distributor_custom',
                    'label'             => '其他代理商名稱',
                    'name'              => 'anime_tw_distributor_custom',
                    'type'              => 'text',
                    'instructions'      => '選擇「其他」時填寫代理商名稱',
                    'required'          => 0,
                    'wrapper'           => [ 'width' => '50' ],
                    'conditional_logic' => [
                        [
                            [
                                'field'    => 'field_anime_tw_distributor',
                                'operator' => '==',
                                'value'    => 'other',
                            ],
                        ],
                    ],
                ],

                // 台灣播出時間（保留）
                [
                    'key'          => 'field_anime_tw_broadcast',
                    'label'        => '台灣播出時間',
                    'name'         => 'anime_tw_broadcast',
                    'type'         => 'text',
                    'instructions' => '台灣播出頻道與時間（如：每週六 23:00 BAHAMUT）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                ],
            ],
        ] );
    }

    // =========================================================================
    // 9. 同步控制（F4：重新同步 Bangumi 按鈕；Bug 8：鎖定欄位中文標籤）
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

                // F4：重新同步 Bangumi 按鈕
                [
                    'key'     => 'field_anime_resync_bangumi',
                    'label'   => '重新同步 Bangumi',
                    'name'    => 'anime_resync_bangumi',
                    'type'    => 'message',
                    'message' => '<button type="button" id="anime-resync-bangumi-btn"
                                    class="button button-secondary"
                                    style="width:100%;margin-bottom:6px;">
                                    🔄 重新同步 Bangumi
                                  </button>
                                  <div id="anime-resync-bangumi-result"
                                       style="font-size:12px;margin-top:4px;"></div>',
                    'wrapper' => [ 'width' => '100' ],
                ],

                // Bug 8：鎖定欄位顯示中文標籤
                [
                    'key'          => 'field_anime_locked_fields',
                    'label'        => '鎖定欄位（不自動更新）',
                    'name'         => 'anime_locked_fields',
                    'type'         => 'checkbox',
                    'instructions' => '勾選後，該欄位不會被自動同步覆蓋',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'choices'      => self::get_auto_update_fields_labeled(),
                    'layout'       => 'vertical',
                    'toggle'       => 0,
                    'return_format'=> 'value',
                ],

                // 匯入狀態訊息
                [
                    'key'          => 'field_anime_import_status',
                    'label'        => '匯入狀態',
                    'name'         => 'anime_import_status',
                    'type'         => 'textarea',
                    'instructions' => '最後一次匯入的結果摘要（自動寫入）',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                    'rows'         => 5,
                    'readonly'     => 1,
                ],
            ],
        ] );
    }

    // =========================================================================
    // F4：重新同步 Bangumi — 前端 JS
    // =========================================================================

    public function render_resync_bangumi_script(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'anime' ) return;
        ?>
        <script>
        (function () {
            'use strict';

            document.addEventListener( 'DOMContentLoaded', function () {
                var btn    = document.getElementById( 'anime-resync-bangumi-btn' );
                var result = document.getElementById( 'anime-resync-bangumi-result' );
                if ( ! btn ) return;

                btn.addEventListener( 'click', function () {
                    var postId = <?php echo (int) ( $_GET['post'] ?? 0 ); ?>;
                    if ( ! postId ) {
                        result.style.color = '#cc0000';
                        result.textContent = '❌ 請先儲存文章再重新同步。';
                        return;
                    }

                    btn.disabled       = true;
                    result.style.color = '#555';
                    result.textContent = '⏳ 同步中，請稍候…';

                    var data = new FormData();
                    data.append( 'action',  'anime_sync_resync_bangumi' );
                    data.append( 'post_id', postId );
                    data.append( 'nonce',   '<?php echo esc_js( wp_create_nonce( 'anime_sync_resync_bangumi' ) ); ?>' );

                    fetch( ajaxurl, { method: 'POST', body: data } )
                        .then( function ( r ) { return r.json(); } )
                        .then( function ( res ) {
                            btn.disabled = false;
                            if ( res.success ) {
                                result.style.color = '#007a29';
                                result.textContent = '✅ ' + ( res.data.message || '同步完成，請重新整理頁面。' );
                            } else {
                                result.style.color = '#cc0000';
                                result.textContent = '❌ ' + ( res.data.message || '同步失敗。' );
                            }
                        } )
                        .catch( function () {
                            btn.disabled       = false;
                            result.style.color = '#cc0000';
                            result.textContent = '❌ 網路錯誤，請重試。';
                        } );
                } );
            } );
        })();
        </script>
        <?php
    }

    // =========================================================================
    // F4：重新同步 Bangumi — AJAX Handler
    // =========================================================================

    public function ajax_resync_bangumi(): void {
        // 驗證 nonce
        if ( ! check_ajax_referer( 'anime_sync_resync_bangumi', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Nonce 驗證失敗' ] );
        }

        // 驗證權限
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => '權限不足' ] );
        }

        $post_id    = (int) ( $_POST['post_id'] ?? 0 );
        $bangumi_id = (int) get_post_meta( $post_id, 'anime_bangumi_id', true );

        if ( ! $post_id || get_post_type( $post_id ) !== 'anime' ) {
            wp_send_json_error( [ 'message' => '無效的文章 ID' ] );
        }

        if ( ! $bangumi_id ) {
            wp_send_json_error( [ 'message' => '此文章尚未設定 Bangumi ID，請先在基本資訊欄位填入後儲存，再重試。' ] );
        }

        // 取得 API Handler
        global $anime_sync_pro;
        $api_handler = $anime_sync_pro->get_api_handler()
                       ?? new Anime_Sync_API_Handler();

        // 重新抓取 Bangumi 資料
        $bgm_data  = $api_handler->fetch_bgm_data_public( $bangumi_id );
        $bgm_staff = $api_handler->get_bgm_staff_public( $bangumi_id );
        $bgm_chars = $api_handler->get_bgm_chars_public( $bangumi_id );
        $bgm_eps   = $api_handler->fetch_bgm_episodes( $bangumi_id );

        if ( is_wp_error( $bgm_data ) || ! is_array( $bgm_data ) ) {
            wp_send_json_error( [
                'message' => 'Bangumi API 回傳錯誤：' . ( is_wp_error( $bgm_data ) ? $bgm_data->get_error_message() : '無資料' ),
            ] );
        }

        $updated = [];

        // ── 中文標題 ──────────────────────────────────────────────────────────
        $title_raw = $bgm_data['name_cn'] ?? $bgm_data['name'] ?? '';
        if ( $title_raw !== '' ) {
            $title_zh = Anime_Sync_CN_Converter::static_convert( $title_raw );
            if ( ! Anime_Sync_ACF_Fields::is_field_locked( $post_id, 'anime_title_chinese' ) ) {
                update_post_meta( $post_id, 'anime_title_chinese', $title_zh );
                update_post_meta( $post_id, 'anime_title_zh',      $title_zh );
                $updated[] = '中文標題';
            }
        }

        // ── 中文簡介 ──────────────────────────────────────────────────────────
        if ( ! empty( $bgm_data['summary'] ) ) {
            $synopsis = Anime_Sync_CN_Converter::static_convert(
                $api_handler->clean_synopsis_public( $bgm_data['summary'] )
            );
            if ( ! Anime_Sync_ACF_Fields::is_field_locked( $post_id, 'anime_synopsis_chinese' ) ) {
                update_post_meta( $post_id, 'anime_synopsis_chinese', $synopsis );
                update_post_meta( $post_id, 'anime_synopsis_zh',      $synopsis );
                $updated[] = '中文簡介';
            }
        }

        // ── Bangumi 評分 ──────────────────────────────────────────────────────
        $raw_rating = $bgm_data['rating']['score'] ?? $bgm_data['score'] ?? null;
        if ( $raw_rating !== null ) {
            $score_bgm = (int) round( (float) $raw_rating * 10 );
            if ( ! Anime_Sync_ACF_Fields::is_field_locked( $post_id, 'anime_score_bangumi' ) ) {
                update_post_meta( $post_id, 'anime_score_bangumi', $score_bgm );
                $updated[] = 'Bangumi 評分';
            }
        }

        // ── 封面圖片（Bangumi 來源） ──────────────────────────────────────────
        $bgm_cover = $bgm_data['images']['large'] ?? $bgm_data['images']['medium'] ?? '';
        if ( $bgm_cover !== '' && ! Anime_Sync_ACF_Fields::is_field_locked( $post_id, 'anime_cover_image' ) ) {
            update_post_meta( $post_id, 'anime_cover_image', $bgm_cover );
            $updated[] = '封面圖片';
        }

        // ── Staff ─────────────────────────────────────────────────────────────
        if ( ! empty( $bgm_staff ) && ! is_wp_error( $bgm_staff ) ) {
            if ( ! Anime_Sync_ACF_Fields::is_field_locked( $post_id, 'anime_staff_json' ) ) {
                update_post_meta(
                    $post_id,
                    'anime_staff_json',
                    wp_json_encode( $bgm_staff, JSON_UNESCAPED_UNICODE )
                );
                $updated[] = '製作人員';
            }
        }

        // ── Cast ──────────────────────────────────────────────────────────────
        if ( ! empty( $bgm_chars ) && ! is_wp_error( $bgm_chars ) ) {
            if ( ! Anime_Sync_ACF_Fields::is_field_locked( $post_id, 'anime_cast_json' ) ) {
                update_post_meta(
                    $post_id,
                    'anime_cast_json',
                    wp_json_encode( $bgm_chars, JSON_UNESCAPED_UNICODE )
                );
                $updated[] = '角色聲優';
            }
        }

        // ── 集數列表 ──────────────────────────────────────────────────────────
        if ( ! empty( $bgm_eps ) ) {
            update_post_meta(
                $post_id,
                'anime_episodes_json',
                wp_json_encode( $bgm_eps, JSON_UNESCAPED_UNICODE )
            );
            $updated[] = '集數列表';
        }

        // ── 更新同步時間 ──────────────────────────────────────────────────────
        update_post_meta( $post_id, 'anime_last_sync', current_time( 'mysql' ) );

        $msg = empty( $updated )
               ? '同步完成（無欄位更新，可能均已鎖定）'
               : '已更新：' . implode( '、', $updated );

        wp_send_json_success( [ 'message' => $msg ] );
    }

    // =========================================================================
    // Static Helpers
    // =========================================================================

    /**
     * Bug 8：回傳 [ 'meta_key' => '中文標籤' ] 格式，供鎖定欄位 checkbox 使用。
     */
    public static function get_auto_update_fields_labeled(): array {
        return [
            'anime_score_anilist'    => 'AniList 評分',
            'anime_score_mal'        => 'MAL 評分',
            'anime_score_bangumi'    => 'Bangumi 評分',
            'anime_popularity'       => '人氣指數',
            'anime_status'           => '播出狀態',
            'anime_episodes'         => '集數',
            'anime_next_airing'      => '下集播出資訊',
            'anime_streaming'        => '串流平台',
            'anime_themes'           => '主題曲',
            'anime_staff_json'       => '製作人員',
            'anime_cast_json'        => '角色聲優',
            'anime_relations_json'   => '關聯作品',
            'anime_episodes_json'    => '集數列表',
            'anime_cover_image'      => '封面圖片',
            'anime_banner_image'     => '橫幅圖片',
            'anime_trailer_url'      => '預告片網址',
            'anime_synopsis_chinese' => '中文簡介',
            'anime_official_site'    => '官方網站',
            'anime_twitter_url'      => 'Twitter / X',
            'anime_wikipedia_url'    => 'Wikipedia',
            'anime_studios'          => '製作公司',
            'anime_start_date'       => '開始播出日期',
            'anime_end_date'         => '結束播出日期',
        ];
    }

    public static function get_auto_update_fields(): array {
        return array_keys( self::get_auto_update_fields_labeled() );
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

<?php
/**
 * 檔案名稱: includes/class-acf-fields.php
 *
 * Bug fixes in this version:
 *   問題 1 – anime_score_bangumi max 從 10 改為 100，step 改為 1
 *   問題 5 – YouTube 預告片預覽改為純 JS 動態讀取，不依賴 get_the_ID()
 *
 * @package Anime_Sync_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Anime_Sync_ACF_Fields {

    public function __construct() {
        add_action( 'acf/init', [ $this, 'register_all_field_groups' ] );
    }

    public function register_all_field_groups(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        $this->register_basic_info();
        $this->register_ratings();
        $this->register_synopsis();
        $this->register_media();
        $this->register_production();
        $this->register_themes_and_streaming();
        $this->register_external_links();
        $this->register_taiwan_info();
        $this->register_sync_control();
    }

    // =========================================================================
    // 群組 1：基本資訊
    // =========================================================================
    private function register_basic_info(): void {
        acf_add_local_field_group( [
            'key'                   => 'group_anime_basic_info',
            'title'                 => '📋 基本資訊',
            'fields'                => [
                [
                    'key'          => 'field_anime_anilist_id',
                    'label'        => 'AniList ID',
                    'name'         => 'anime_anilist_id',
                    'type'         => 'number',
                    'instructions' => '請填入 AniList 作品 ID（數字），例如：21。',
                    'required'     => 1,
                    'min'          => 1,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_mal_id',
                    'label'        => 'MyAnimeList ID',
                    'name'         => 'anime_mal_id',
                    'type'         => 'number',
                    'instructions' => '由 AniList API 自動填入（idMal 欄位）。',
                    'required'     => 0,
                    'min'          => 1,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_bangumi_id',
                    'label'        => 'Bangumi ID',
                    'name'         => 'anime_bangumi_id',
                    'type'         => 'number',
                    'instructions' => '由三層查找自動填入。若自動查找失敗，請手動填入。',
                    'required'     => 0,
                    'min'          => 1,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_animethemes_id',
                    'label'        => 'AnimeThemes ID',
                    'name'         => 'anime_animethemes_id',
                    'type'         => 'text',
                    'instructions' => '由 AnimeThemes API 自動填入（格式如 shingeki-no-kyojin）。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_title_chinese',
                    'label'        => '中文標題（台灣繁體）',
                    'name'         => 'anime_title_chinese',
                    'type'         => 'text',
                    'instructions' => '優先使用 Bangumi name_cn，fallback 至 AniList english → romaji。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_title_native',
                    'label'        => '日文原名',
                    'name'         => 'anime_title_native',
                    'type'         => 'text',
                    'instructions' => '由 AniList title.native 自動填入。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_title_romaji',
                    'label'        => 'Romaji 標題',
                    'name'         => 'anime_title_romaji',
                    'type'         => 'text',
                    'instructions' => '由 AniList title.romaji 自動填入，同時作為文章 slug 來源。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_title_english',
                    'label'        => '英文標題',
                    'name'         => 'anime_title_english',
                    'type'         => 'text',
                    'instructions' => '由 AniList title.english 自動填入。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_format',
                    'label'         => '作品類型',
                    'name'          => 'anime_format',
                    'type'          => 'select',
                    'instructions'  => '由 AniList format 欄位自動填入。',
                    'required'      => 0,
                    'choices'       => [
                        'TV'       => '電視動畫 (TV)',
                        'TV_SHORT' => '短篇電視動畫 (TV_SHORT)',
                        'MOVIE'    => '劇場版 (MOVIE)',
                        'SPECIAL'  => '特別篇 (SPECIAL)',
                        'OVA'      => 'OVA',
                        'ONA'      => '網路動畫 (ONA)',
                        'MUSIC'    => '音樂 (MUSIC)',
                    ],
                    'default_value' => 'TV',
                    'wrapper'       => [ 'width' => '33' ],
                ],
                [
                    'key'           => 'field_anime_status',
                    'label'         => '播出狀態',
                    'name'          => 'anime_status',
                    'type'          => 'select',
                    'instructions'  => '由每日 cron 自動更新。',
                    'required'      => 0,
                    'choices'       => [
                        'FINISHED'         => '已完結',
                        'RELEASING'        => '連載中',
                        'NOT_YET_RELEASED' => '尚未播出',
                        'CANCELLED'        => '已取消',
                        'HIATUS'           => '休播中',
                    ],
                    'default_value' => 'FINISHED',
                    'wrapper'       => [ 'width' => '33' ],
                ],
                [
                    'key'           => 'field_anime_source',
                    'label'         => '原作來源',
                    'name'          => 'anime_source',
                    'type'          => 'select',
                    'instructions'  => '由 AniList source 欄位自動填入。',
                    'required'      => 0,
                    'choices'       => [
                        'ORIGINAL'           => '原創',
                        'MANGA'              => '漫畫',
                        'LIGHT_NOVEL'        => '輕小說',
                        'VISUAL_NOVEL'       => '視覺小說',
                        'VIDEO_GAME'         => '遊戲',
                        'NOVEL'              => '小說',
                        'DOUJINSHI'          => '同人誌',
                        'ANIME'              => '動畫',
                        'WEB_NOVEL'          => '網路小說',
                        'LIVE_ACTION'        => '真人影視',
                        'GAME'               => '遊戲',
                        'COMIC'              => '漫畫',
                        'MULTIMEDIA_PROJECT' => '多媒體企劃',
                        'PICTURE_BOOK'       => '繪本',
                        'OTHER'              => '其他',
                    ],
                    'default_value' => 'ORIGINAL',
                    'wrapper'       => [ 'width' => '34' ],
                ],
                [
                    'key'          => 'field_anime_season',
                    'label'        => '播出季度',
                    'name'         => 'anime_season',
                    'type'         => 'select',
                    'instructions' => '由 AniList season 欄位自動填入。',
                    'required'     => 0,
                    'choices'      => [
                        'WINTER' => '冬季（1月）',
                        'SPRING' => '春季（4月）',
                        'SUMMER' => '夏季（7月）',
                        'FALL'   => '秋季（10月）',
                    ],
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_season_year',
                    'label'        => '播出年份',
                    'name'         => 'anime_season_year',
                    'type'         => 'number',
                    'instructions' => '由 AniList seasonYear 欄位自動填入。',
                    'required'     => 0,
                    'min'          => 1900,
                    'max'          => 2100,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_episodes',
                    'label'        => '總集數',
                    'name'         => 'anime_episodes',
                    'type'         => 'number',
                    'instructions' => '由 AniList episodes 欄位自動填入。',
                    'required'     => 0,
                    'min'          => 0,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_episodes_aired',
                    'label'        => '已播集數',
                    'name'         => 'anime_episodes_aired',
                    'type'         => 'number',
                    'instructions' => '播出中時由每日 cron 自動更新。',
                    'required'     => 0,
                    'min'          => 0,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_duration',
                    'label'        => '每集時長（分鐘）',
                    'name'         => 'anime_duration',
                    'type'         => 'number',
                    'instructions' => '由 AniList duration 欄位自動填入。',
                    'required'     => 0,
                    'min'          => 0,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'            => 'field_anime_start_date',
                    'label'          => '開播日期',
                    'name'           => 'anime_start_date',
                    'type'           => 'date_picker',
                    'instructions'   => '由 AniList startDate 欄位自動填入。',
                    'required'       => 0,
                    'display_format' => 'Y-m-d',
                    'return_format'  => 'Y-m-d',
                    'first_day'      => 1,
                    'wrapper'        => [ 'width' => '33' ],
                ],
                [
                    'key'            => 'field_anime_end_date',
                    'label'          => '完結日期',
                    'name'           => 'anime_end_date',
                    'type'           => 'date_picker',
                    'instructions'   => '完結後由 cron 自動填入。播出中時留空。',
                    'required'       => 0,
                    'display_format' => 'Y-m-d',
                    'return_format'  => 'Y-m-d',
                    'first_day'      => 1,
                    'wrapper'        => [ 'width' => '33' ],
                ],
                [
                    'key'          => 'field_anime_next_airing',
                    'label'        => '下一集播出時間',
                    'name'         => 'anime_next_airing',
                    'type'         => 'text',
                    'instructions' => '格式：YYYY-MM-DD HH:MM（台灣時間）。由每日 cron 自動更新；完結後清空。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '34' ],
                ],
            ],
            'location'              => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order'            => 10,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
        ] );
    }

    // =========================================================================
    // 群組 2：評分資訊
    // =========================================================================
    private function register_ratings(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_ratings',
            'title'  => '⭐ 評分資訊',
            'fields' => [
                [
                    'key'          => 'field_anime_score_anilist',
                    'label'        => 'AniList 評分（原始值）',
                    'name'         => 'anime_score_anilist',
                    'type'         => 'number',
                    'instructions' => '儲存範圍 0–100（AniList 原始值）。前端自動換算為 0–10 分制顯示。由每週 cron 自動更新。',
                    'required'     => 0,
                    'min'          => 0,
                    'max'          => 100,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_score_mal',
                    'label'        => 'MyAnimeList 評分',
                    'name'         => 'anime_score_mal',
                    'type'         => 'number',
                    'instructions' => '範圍 0–10（Jikan API 原始值）。由匯入時自動抓取，每週 cron 更新。',
                    'required'     => 0,
                    'min'          => 0,
                    'max'          => 10,
                    'step'         => 0.01,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    // 問題 1 修正：max 從 10 改為 100，step 改為 1
                    // Bangumi 評分存入時已 ×10（例：7.9 → 79），ACF 驗證需對應調整
                    'key'          => 'field_anime_score_bangumi',
                    'label'        => 'Bangumi 評分',
                    'name'         => 'anime_score_bangumi',
                    'type'         => 'number',
                    'instructions' => '儲存範圍 0–100（Bangumi 原始值 ×10）。前端自動換算為 0–10 分制顯示。由每週 cron 自動更新。',
                    'required'     => 0,
                    'min'          => 0,
                    'max'          => 100,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_popularity',
                    'label'        => 'AniList 人氣數',
                    'name'         => 'anime_popularity',
                    'type'         => 'number',
                    'instructions' => '由 AniList popularity 欄位自動填入（收藏人數）。每週更新。',
                    'required'     => 0,
                    'min'          => 0,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
                [
                    'key'          => 'field_anime_ranking',
                    'label'        => 'AniList 排名',
                    'name'         => 'anime_ranking',
                    'type'         => 'number',
                    'instructions' => '由 AniList rankings 欄位自動填入（全時期評分排名）。每週更新。',
                    'required'     => 0,
                    'min'          => 0,
                    'step'         => 1,
                    'wrapper'      => [ 'width' => '25' ],
                ],
            ],
            'location'   => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order' => 20,
            'position'   => 'normal',
            'style'      => 'default',
            'active'     => true,
        ] );
    }

    // =========================================================================
    // 群組 3：簡介
    // =========================================================================
    private function register_synopsis(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_synopsis',
            'title'  => '📝 簡介',
            'fields' => [
                [
                    'key'          => 'field_anime_synopsis_chinese',
                    'label'        => '中文簡介（台灣繁體）',
                    'name'         => 'anime_synopsis_chinese',
                    'type'         => 'textarea',
                    'instructions' => '優先使用 Bangumi summary（自動簡繁轉換）。修改後請在「同步控制」勾選「鎖定中文簡介」。',
                    'required'     => 0,
                    'rows'         => 6,
                    'new_lines'    => 'br',
                    'wrapper'      => [ 'width' => '100' ],
                ],
            ],
            'location'   => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order' => 30,
            'position'   => 'normal',
            'style'      => 'default',
            'active'     => true,
        ] );
    }

    // =========================================================================
    // 群組 4：媒體素材
    // =========================================================================
    private function register_media(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_media',
            'title'  => '🖼️ 媒體素材',
            'fields' => [
                [
                    'key'          => 'field_anime_cover_image',
                    'label'        => '封面圖片網址',
                    'name'         => 'anime_cover_image',
                    'type'         => 'url',
                    'instructions' => '由 AniList coverImage.extraLarge 自動填入。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                ],
                [
                    'key'          => 'field_anime_banner_image',
                    'label'        => '橫幅圖片網址',
                    'name'         => 'anime_banner_image',
                    'type'         => 'url',
                    'instructions' => '由 AniList bannerImage 自動填入。可留空。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                ],
                [
                    'key'          => 'field_anime_trailer_url',
                    'label'        => 'YouTube 預告片網址',
                    'name'         => 'anime_trailer_url',
                    'type'         => 'textarea',
                    'instructions' => '每行或逗號分隔一個 YouTube 網址。由 AniList 自動填入第一個，可人工追加。',
                    'required'     => 0,
                    'rows'         => 3,
                    'new_lines'    => '',
                    'wrapper'      => [ 'width' => '100' ],
                ],
                [
                    // 問題 5 修正：改為純 JS 動態讀取，不依賴 PHP get_the_ID()
                    // acf/init 執行時 get_the_ID() 永遠回傳 0，舊寫法導致已存在文章也無法即時預覽
                    'key'          => 'field_anime_trailer_preview',
                    'label'        => '預告片預覽',
                    'name'         => 'anime_trailer_preview',
                    'type'         => 'message',
                    'instructions' => '自動從上方第一個 YouTube 網址產生預覽，輸入後即時更新。',
                    'message'      => $this->get_trailer_preview_html(),
                    'wrapper'      => [ 'width' => '100' ],
                ],
            ],
            'location'   => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order' => 40,
            'position'   => 'normal',
            'style'      => 'default',
            'active'     => true,
        ] );
    }

    /**
     * 問題 5 修正：純 JS 實作，完全不依賴 PHP get_the_ID()。
     * JS 直接讀取同頁 textarea[name="anime_trailer_url"] 的值，
     * 新文章和已存在文章均可即時預覽。
     */
    private function get_trailer_preview_html(): string {
        if ( ! is_admin() ) {
            return '';
        }

        return '
<div id="anime-trailer-preview-wrap" style="margin-top:8px;"></div>
<script>
(function () {
    "use strict";

    function extractYouTubeId( url ) {
        var m = url.match( /(?:v=|youtu\.be\/)([A-Za-z0-9_-]{11})/ );
        return m ? m[1] : null;
    }

    function buildIframe( videoId ) {
        return "<div style=\"margin-bottom:12px;\">"
             + "<iframe width=\"560\" height=\"315\""
             + " src=\"https://www.youtube.com/embed/" + videoId + "?rel=0\""
             + " allowfullscreen loading=\"lazy\""
             + " style=\"border:0;max-width:100%;\"></iframe>"
             + "</div>";
    }

    function renderPreviews() {
        var textarea = document.querySelector( "textarea[name=\'anime_trailer_url\']" );
        var wrap     = document.getElementById( "anime-trailer-preview-wrap" );
        if ( ! textarea || ! wrap ) return;

        var raw  = textarea.value || "";
        var urls = raw.split( /[,\n]+/ )
                      .map( function(s){ return s.trim(); } )
                      .filter( Boolean );

        if ( ! urls.length ) {
            wrap.innerHTML = "<p style=\"color:#999;\">尚無預告片網址。</p>";
            return;
        }

        var html = "";
        urls.forEach( function( url ) {
            var vid = extractYouTubeId( url );
            if ( vid ) {
                html += buildIframe( vid );
            }
        } );

        wrap.innerHTML = html || "<p style=\"color:#999;\">無法解析 YouTube 網址。</p>";
    }

    // 初始化：等 ACF 欄位完全渲染後執行
    function init() {
        renderPreviews();
        var textarea = document.querySelector( "textarea[name=\'anime_trailer_url\']" );
        if ( textarea ) {
            textarea.addEventListener( "input", renderPreviews );
        }
    }

    if ( document.readyState === "loading" ) {
        document.addEventListener( "DOMContentLoaded", init );
    } else {
        // DOM 已就緒（ACF message 欄位可能在 DOMContentLoaded 後才插入）
        // 用 setTimeout 確保 ACF 完成渲染
        setTimeout( init, 300 );
    }
})();
</script>';
    }

    // =========================================================================
    // 群組 5：製作資訊
    // =========================================================================
    private function register_production(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_production',
            'title'  => '🎬 製作資訊',
            'fields' => [
                [
                    'key'          => 'field_anime_studios',
                    'label'        => '製作公司',
                    'name'         => 'anime_studios',
                    'type'         => 'text',
                    'instructions' => '由 AniList studios（isMain: true）自動填入主要製作公司名稱。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '100' ],
                ],
                [
                    'key'          => 'field_anime_staff_json',
                    'label'        => 'STAFF 資料（JSON）',
                    'name'         => 'anime_staff_json',
                    'type'         => 'textarea',
                    'instructions' => '由 Bangumi STAFF API 自動填入。請勿手動編輯。',
                    'required'     => 0,
                    'rows'         => 4,
                    'new_lines'    => '',
                    'wrapper'      => [ 'width' => '100' ],
                    'readonly'     => 1,
                ],
                [
                    'key'          => 'field_anime_cast_json',
                    'label'        => 'CAST 角色資料（JSON）',
                    'name'         => 'anime_cast_json',
                    'type'         => 'textarea',
                    'instructions' => '由 Bangumi CAST API 自動填入。請勿手動編輯。',
                    'required'     => 0,
                    'rows'         => 4,
                    'new_lines'    => '',
                    'wrapper'      => [ 'width' => '100' ],
                    'readonly'     => 1,
                ],
            ],
            'location'   => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order' => 50,
            'position'   => 'normal',
            'style'      => 'default',
            'active'     => true,
        ] );
    }

    // =========================================================================
    // 群組 6：主題曲與串流平台
    // =========================================================================
    private function register_themes_and_streaming(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_themes_streaming',
            'title'  => '🎵 主題曲與串流平台',
            'fields' => [
                [
                    'key'          => 'field_anime_themes',
                    'label'        => 'OP/ED 主題曲資料（JSON）',
                    'name'         => 'anime_themes',
                    'type'         => 'textarea',
                    'instructions' => '由 AnimeThemes API 透過 MAL ID 自動抓取。請勿手動編輯。',
                    'required'     => 0,
                    'rows'         => 4,
                    'new_lines'    => '',
                    'wrapper'      => [ 'width' => '100' ],
                    'readonly'     => 1,
                ],
                [
                    'key'          => 'field_anime_streaming',
                    'label'        => '串流平台資料（JSON）',
                    'name'         => 'anime_streaming',
                    'type'         => 'textarea',
                    'instructions' => '由 AniList externalLinks（type: STREAMING）自動填入。請勿手動編輯。',
                    'required'     => 0,
                    'rows'         => 4,
                    'new_lines'    => '',
                    'wrapper'      => [ 'width' => '100' ],
                    'readonly'     => 1,
                ],
            ],
            'location'   => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order' => 60,
            'position'   => 'normal',
            'style'      => 'default',
            'active'     => true,
        ] );
    }

    // =========================================================================
    // 群組 7：外部連結
    // =========================================================================
    private function register_external_links(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_external_links',
            'title'  => '🔗 外部連結',
            'fields' => [
                [
                    'key'          => 'field_anime_official_site',
                    'label'        => '官方網站',
                    'name'         => 'anime_official_site',
                    'type'         => 'url',
                    'instructions' => '由 AniList externalLinks 自動填入。可人工覆寫。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_twitter_url',
                    'label'        => 'Twitter / X 官方帳號',
                    'name'         => 'anime_twitter_url',
                    'type'         => 'url',
                    'instructions' => '由 AniList externalLinks 自動填入。可人工覆寫。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_wikipedia_url',
                    'label'        => 'Wikipedia 頁面',
                    'name'         => 'anime_wikipedia_url',
                    'type'         => 'url',
                    'instructions' => '由系統自動查詢繁體中文維基百科填入。可人工覆寫。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_tiktok_url',
                    'label'        => 'TikTok 官方帳號',
                    'name'         => 'anime_tiktok_url',
                    'type'         => 'url',
                    'instructions' => '請人工填入 TikTok 官方帳號連結（選填）。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
            ],
            'location'   => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order' => 70,
            'position'   => 'normal',
            'style'      => 'default',
            'active'     => true,
        ] );
    }

    // =========================================================================
    // 群組 8：台灣在地資訊
    // =========================================================================
    private function register_taiwan_info(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_taiwan_info',
            'title'  => '🇹🇼 台灣在地資訊',
            'fields' => [
                [
                    'key'          => 'field_anime_tw_distributor',
                    'label'        => '台灣代理商／發行商',
                    'name'         => 'anime_tw_distributor',
                    'type'         => 'text',
                    'instructions' => '請人工填入台灣代理商名稱（例：木棉花、普威爾）。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_tw_broadcast',
                    'label'        => '台灣播出時間',
                    'name'         => 'anime_tw_broadcast',
                    'type'         => 'text',
                    'instructions' => '請人工填入台灣播出時間（例：每週六 23:00 Netflix）。',
                    'required'     => 0,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_tw_streaming',
                    'label'        => '台灣合法串流平台',
                    'name'         => 'anime_tw_streaming',
                    'type'         => 'textarea',
                    'instructions' => '請人工填入，每行一個，格式：平台名稱|網址。例：動畫瘋|https://ani.gamer.com.tw/xxx。',
                    'required'     => 0,
                    'rows'         => 4,
                    'new_lines'    => '',
                    'wrapper'      => [ 'width' => '100' ],
                ],
            ],
            'location'   => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order' => 80,
            'position'   => 'normal',
            'style'      => 'default',
            'active'     => true,
        ] );
    }

    // =========================================================================
    // 群組 9：同步控制
    // =========================================================================
    private function register_sync_control(): void {
        acf_add_local_field_group( [
            'key'    => 'group_anime_sync_control',
            'title'  => '⚙️ 同步控制',
            'fields' => [
                [
                    'key'          => 'field_anime_last_sync',
                    'label'        => '上次 API 同步時間',
                    'name'         => 'anime_last_sync',
                    'type'         => 'text',
                    'instructions' => '由系統自動記錄。請勿手動修改。',
                    'required'     => 0,
                    'readonly'     => 1,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'          => 'field_anime_last_updated',
                    'label'        => '資料最後更新時間',
                    'name'         => 'anime_last_updated',
                    'type'         => 'text',
                    'instructions' => '每次任何欄位更新時由系統自動記錄。',
                    'required'     => 0,
                    'readonly'     => 1,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_anime_locked_fields',
                    'label'         => '鎖定欄位（防止自動覆寫）',
                    'name'          => 'anime_locked_fields',
                    'type'          => 'checkbox',
                    'instructions'  => '勾選後，自動更新 cron 將跳過該欄位，保留您的人工修改。',
                    'required'      => 0,
                    'choices'       => [
                        'anime_title_chinese'    => '中文標題',
                        'anime_synopsis_chinese' => '中文簡介',
                        'anime_cover_image'      => '封面圖片',
                        'anime_banner_image'     => '橫幅圖片',
                        'anime_trailer_url'      => 'YouTube 預告片',
                        'anime_official_site'    => '官方網站',
                        'anime_twitter_url'      => 'Twitter 連結',
                        'anime_wikipedia_url'    => 'Wikipedia 連結',
                        'anime_tiktok_url'       => 'TikTok 連結',
                        'anime_tw_distributor'   => '台灣代理商',
                        'anime_tw_broadcast'     => '台灣播出時間',
                        'anime_tw_streaming'     => '台灣串流平台',
                        'anime_cast_json'        => 'CAST 角色資料',
                        'anime_staff_json'       => 'STAFF 製作資料',
                        'anime_themes'           => 'OP/ED 主題曲',
                        'anime_streaming'        => '串流平台資料',
                    ],
                    'layout'        => 'horizontal',
                    'toggle'        => 0,
                    'return_format' => 'value',
                    'wrapper'       => [ 'width' => '100' ],
                ],
            ],
            'location'   => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'anime' ] ],
            ],
            'menu_order' => 90,
            'position'   => 'side',
            'style'      => 'default',
            'active'     => true,
        ] );
    }

    // =========================================================================
    // 靜態輔助方法
    // =========================================================================

    public static function get_auto_update_fields(): array {
        return [
            'anime_episodes_aired' => '已播集數',
            'anime_status'         => '播出狀態',
            'anime_next_airing'    => '下一集播出時間',
            'anime_end_date'       => '完結日期',
            'anime_episodes'       => '總集數（完結確認）',
            'anime_score_anilist'  => 'AniList 評分',
            'anime_score_mal'      => 'MAL 評分',
            'anime_score_bangumi'  => 'Bangumi 評分',
            'anime_popularity'     => 'AniList 人氣數',
            'anime_ranking'        => 'AniList 排名',
            'anime_themes'         => 'OP/ED 主題曲',
        ];
    }

    public static function get_one_time_fields(): array {
        return [
            'anime_anilist_id'     => 'AniList ID',
            'anime_mal_id'         => 'MAL ID',
            'anime_bangumi_id'     => 'Bangumi ID',
            'anime_animethemes_id' => 'AnimeThemes ID',
            'anime_title_native'   => '日文原名',
            'anime_title_romaji'   => 'Romaji 標題',
            'anime_title_english'  => '英文標題',
            'anime_format'         => '作品類型',
            'anime_source'         => '原作來源',
            'anime_season'         => '播出季度',
            'anime_season_year'    => '播出年份',
            'anime_duration'       => '每集時長',
            'anime_start_date'     => '開播日期',
            'anime_cover_image'    => '封面圖片',
            'anime_banner_image'   => '橫幅圖片',
            'anime_studios'        => '製作公司',
            'anime_staff_json'     => 'STAFF 資料',
            'anime_cast_json'      => 'CAST 資料',
            'anime_streaming'      => '串流平台資料',
            'anime_official_site'  => '官方網站',
            'anime_twitter_url'    => 'Twitter 連結',
        ];
    }

    public static function is_field_locked( int $post_id, string $meta_key ): bool {
        if ( function_exists( 'get_field' ) ) {
            $locked = get_field( 'anime_locked_fields', $post_id );
        } else {
            $locked = get_post_meta( $post_id, 'anime_locked_fields', true );
        }
        if ( empty( $locked ) || ! is_array( $locked ) ) return false;
        return in_array( $meta_key, $locked, true );
    }
}

<?php
/**
 * Single Anime Template
 * Plugin: Anime Sync Pro
 * Path: wp-content/plugins/anime-sync-pro/public/templates/single-anime.php
 *
 * @package Anime_Sync_Pro
 * @version 12.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_enqueue_style(
    'anime-sync-single',
    plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/anime-single.css',
    array(),
    '12.1'
);

get_header();

while ( have_posts() ) :
    the_post();

    $post_id = get_the_ID();

    /* Helpers */
    $get_meta = function ( $key, $default = '' ) use ( $post_id ) {
        $value = get_post_meta( $post_id, $key, true );
        return ( $value === '' || $value === null ) ? $default : $value;
    };

    $decode_json = function ( $raw ) {
        if ( is_array( $raw ) ) {
            return $raw;
        }
        if ( ! is_string( $raw ) || $raw === '' ) {
            return array();
        }
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : array();
    };

    $format_date = function ( $raw ) {
        if ( empty( $raw ) ) {
            return '';
        }

        $raw = trim( (string) $raw );

        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            return $raw;
        }

        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $raw, $m ) ) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        $ts = strtotime( $raw );
        return ( $ts !== false ) ? gmdate( 'Y-m-d', $ts ) : $raw;
    };

    $starts_with = function ( $haystack, $needle ) {
        return $needle !== '' && strpos( $haystack, $needle ) === 0;
    };

    $substr_safe = function ( $text, $start, $length = null ) {
        $text = (string) $text;

        if ( function_exists( 'mb_substr' ) ) {
            return ( $length === null ) ? mb_substr( $text, $start ) : mb_substr( $text, $start, $length );
        }

        return ( $length === null ) ? substr( $text, $start ) : substr( $text, $start, $length );
    };

    $fallback_text = function ( $text, $length = 2 ) use ( $substr_safe ) {
        $text = trim( wp_strip_all_tags( (string) $text ) );
        if ( $text === '' ) {
            return 'AN';
        }
        return $substr_safe( $text, 0, $length );
    };

    $normalize_news_item = function ( $item ) {
        if ( ! is_array( $item ) ) {
            return null;
        }

        $title = '';
        $url   = '';

        if ( ! empty( $item['title'] ) ) {
            $title = $item['title'];
        } elseif ( ! empty( $item['name'] ) ) {
            $title = $item['name'];
        } elseif ( ! empty( $item['headline'] ) ) {
            $title = $item['headline'];
        }

        if ( ! empty( $item['url'] ) ) {
            $url = $item['url'];
        } elseif ( ! empty( $item['link'] ) ) {
            $url = $item['link'];
        }

        $title = trim( (string) $title );
        $url   = trim( (string) $url );

        if ( $title === '' ) {
            return null;
        }

        return array(
            'title' => $title,
            'url'   => $url,
        );
    };

    /* Meta */
    $anilist_id = (int) $get_meta( 'anime_anilist_id', 0 );
    $mal_id     = (int) $get_meta( 'anime_mal_id', 0 );
    $bangumi_id = (int) $get_meta( 'anime_bangumi_id', 0 );

    $title_chinese = $get_meta( 'anime_title_chinese' );
    $title_native  = $get_meta( 'anime_title_native' );
    $title_romaji  = $get_meta( 'anime_title_romaji' );
    $title_english = $get_meta( 'anime_title_english' );
    $display_title = $title_chinese ? $title_chinese : get_the_title();

    $format      = $get_meta( 'anime_format' );
    $status      = $get_meta( 'anime_status' );
    $season      = $get_meta( 'anime_season' );
    $season_year = (int) $get_meta( 'anime_season_year', 0 );
    $episodes    = (int) $get_meta( 'anime_episodes', 0 );
    $ep_aired    = (int) $get_meta( 'anime_episodes_aired', 0 );
    $duration    = (int) $get_meta( 'anime_duration', 0 );
    $source      = $get_meta( 'anime_source' );
    $studio      = $get_meta( 'anime_studios' );
    $popularity  = (int) $get_meta( 'anime_popularity', 0 );

    $tw_streaming_raw   = $get_meta( 'anime_tw_streaming' );
    $tw_streaming_other = $get_meta( 'anime_tw_streaming_other' );
    $tw_distributor     = $get_meta( 'anime_tw_distributor' );
    $tw_dist_custom     = $get_meta( 'anime_tw_distributor_custom' );
    $tw_broadcast       = $get_meta( 'anime_tw_broadcast' );

    $tw_stream_url_map = array(
        'bahamut'     => $get_meta( 'anime_tw_streaming_url_bahamut' ),
        'netflix'     => $get_meta( 'anime_tw_streaming_url_netflix' ),
        'disney'      => $get_meta( 'anime_tw_streaming_url_disney' ),
        'amazon'      => $get_meta( 'anime_tw_streaming_url_amazon' ),
        'kktv'        => $get_meta( 'anime_tw_streaming_url_kktv' ),
        'friday'      => $get_meta( 'anime_tw_streaming_url_friday' ),
        'catchplay'   => $get_meta( 'anime_tw_streaming_url_catchplay' ),
        'bilibili'    => $get_meta( 'anime_tw_streaming_url_bilibili' ),
        'crunchyroll' => $get_meta( 'anime_tw_streaming_url_crunchyroll' ),
        'hulu'        => $get_meta( 'anime_tw_streaming_url_hulu' ),
        'hidive'      => $get_meta( 'anime_tw_streaming_url_hidive' ),
        'ani-one'     => $get_meta( 'anime_tw_streaming_url_ani_one' ),
        'muse'        => $get_meta( 'anime_tw_streaming_url_muse' ),
        'viu'         => $get_meta( 'anime_tw_streaming_url_viu' ),
        'wetv'        => $get_meta( 'anime_tw_streaming_url_wetv' ),
        'youtube'     => $get_meta( 'anime_tw_streaming_url_youtube' ),
    );

    $tw_dist_labels = array(
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
        'other'       => '',
    );

    $tw_dist_display = '';
    if ( $tw_distributor === 'other' ) {
        $tw_dist_display = $tw_dist_custom ? $tw_dist_custom : '';
    } elseif ( $tw_distributor ) {
        $tw_dist_display = isset( $tw_dist_labels[ $tw_distributor ] ) ? $tw_dist_labels[ $tw_distributor ] : $tw_distributor;
    }

    $tw_stream_labels = array(
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
    );

    $tw_streaming_items = array();

    if ( ! empty( $tw_streaming_raw ) ) {
        $raw_arr = is_array( $tw_streaming_raw ) ? $tw_streaming_raw : array( $tw_streaming_raw );
        foreach ( $raw_arr as $key ) {
            $key = trim( (string) $key );
            if ( $key === '' ) {
                continue;
            }

            $tw_streaming_items[] = array(
                'label' => isset( $tw_stream_labels[ $key ] ) ? $tw_stream_labels[ $key ] : $key,
                'url'   => isset( $tw_stream_url_map[ $key ] ) ? $tw_stream_url_map[ $key ] : '',
            );
        }
    }

    if ( $tw_streaming_other ) {
        $extra_items = array_map( 'trim', explode( ',', $tw_streaming_other ) );
        foreach ( $extra_items as $extra ) {
            if ( $extra !== '' ) {
                $tw_streaming_items[] = array(
                    'label' => $extra,
                    'url'   => '',
                );
            }
        }
    }

    $start_date = $format_date( $get_meta( 'anime_start_date' ) );
    $end_date   = $format_date( $get_meta( 'anime_end_date' ) );

    $score_anilist_raw = $get_meta( 'anime_score_anilist' );
    $score_anilist_num = is_numeric( $score_anilist_raw ) ? (float) $score_anilist_raw : 0;
    $score_anilist     = $score_anilist_num > 0 ? number_format( $score_anilist_num / 10, 1 ) : '';

    $score_mal_raw = $get_meta( 'anime_score_mal' );
    $score_mal_num = is_numeric( $score_mal_raw ) ? (float) $score_mal_raw : 0;
    $score_mal     = $score_mal_num > 0 ? number_format( $score_mal_num / 10, 1 ) : '';

    $score_bangumi_raw = $get_meta( 'anime_score_bangumi' );
    $score_bangumi_num = is_numeric( $score_bangumi_raw ) ? (float) $score_bangumi_raw : 0;
    $score_bangumi     = $score_bangumi_num > 0 ? number_format( $score_bangumi_num / 10, 1 ) : '';

    $cover_image  = $get_meta( 'anime_cover_image' );
    $banner_image = $get_meta( 'anime_banner_image' );
    $trailer_url  = $get_meta( 'anime_trailer_url' );

    $youtube_id = '';
    if ( $trailer_url ) {
        $trailer_candidates = preg_split( '/[,\n]+/', (string) $trailer_url );
        if ( is_array( $trailer_candidates ) ) {
            foreach ( $trailer_candidates as $t_url ) {
                $t_url = trim( $t_url );
                if ( $t_url === '' ) {
                    continue;
                }

                if ( preg_match( '/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{11})/', $t_url, $m ) ) {
                    $youtube_id = $m[1];
                    break;
                }

                if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $t_url ) ) {
                    $youtube_id = $t_url;
                    break;
                }
            }
        }
    }

    $official_site = $get_meta( 'anime_official_site' );
    $twitter_url   = $get_meta( 'anime_twitter_url' );
    $wikipedia_url = $get_meta( 'anime_wikipedia_url' );
    $tiktok_url    = $get_meta( 'anime_tiktok_url' );

    $affiliate_html = $get_meta( 'anime_affiliate_html' );

    $next_airing_raw = $get_meta( 'anime_next_airing' );
    $airing_data     = array();
    if ( $next_airing_raw ) {
        $decoded_airing = is_array( $next_airing_raw ) ? $next_airing_raw : json_decode( $next_airing_raw, true );
        if ( is_array( $decoded_airing ) ) {
            $airing_data = $decoded_airing;
        }
    }

    $synopsis_raw = $get_meta( 'anime_synopsis_chinese' );
    if ( empty( $synopsis_raw ) ) {
        $synopsis_raw = $get_meta( 'anime_synopsis' );
    }
    if ( empty( $synopsis_raw ) ) {
        $synopsis_raw = get_the_content();
    }
    $synopsis = trim( (string) $synopsis_raw );

    $streaming_list = $decode_json( $get_meta( 'anime_streaming' ) );
    $themes_list    = $decode_json( $get_meta( 'anime_themes' ) );
    $cast_list      = $decode_json( $get_meta( 'anime_cast_json' ) );
    $staff_list     = $decode_json( $get_meta( 'anime_staff_json' ) );
    $relations_list = $decode_json( $get_meta( 'anime_relations_json' ) );
    $episodes_list  = $decode_json( $get_meta( 'anime_episodes_json' ) );

    $news_items = $decode_json( $get_meta( 'anime_related_news_json' ) );
    if ( empty( $news_items ) ) {
        $news_items = $decode_json( $get_meta( 'anime_news_json' ) );
    }

    $normalized_news = array();
    if ( ! empty( $news_items ) ) {
        foreach ( $news_items as $news_item ) {
            $normalized = $normalize_news_item( $news_item );
            if ( $normalized ) {
                $normalized_news[] = $normalized;
            }
        }
    }
    $news_items = $normalized_news;

    /* Themes */
    $seen     = array();
    $openings = array();
    $endings  = array();

    foreach ( $themes_list as $t ) {
        $type   = strtoupper( trim( isset( $t['type'] ) ? $t['type'] : '' ) );
        $stitle = trim( isset( $t['song_title'] ) ? $t['song_title'] : ( isset( $t['title'] ) ? $t['title'] : '' ) );
        $key    = $type . '||' . $stitle;

        if ( isset( $seen[ $key ] ) ) {
            continue;
        }

        $seen[ $key ] = true;

        if ( $starts_with( $type, 'OP' ) ) {
            $openings[] = $t;
        } elseif ( $starts_with( $type, 'ED' ) ) {
            $endings[] = $t;
        }
    }

    /* Labels */
    $season_labels = array(
        'WINTER' => '冬季',
        'SPRING' => '春季',
        'SUMMER' => '夏季',
        'FALL'   => '秋季',
    );

    $format_labels = array(
        'TV'       => 'TV',
        'TV_SHORT' => 'TV短篇',
        'MOVIE'    => '劇場版',
        'OVA'      => 'OVA',
        'ONA'      => 'ONA',
        'SPECIAL'  => '特別篇',
        'MUSIC'    => '音樂MV',
    );

    $status_labels = array(
        'FINISHED'         => '已完結',
        'RELEASING'        => '連載中',
        'NOT_YET_RELEASED' => '尚未播出',
        'CANCELLED'        => '已取消',
        'HIATUS'           => '暫停中',
    );

    $status_classes = array(
        'FINISHED'         => 's-fin',
        'RELEASING'        => 's-rel',
        'NOT_YET_RELEASED' => 's-pre',
        'CANCELLED'        => 's-can',
        'HIATUS'           => 's-hia',
    );

    $source_labels = array(
        'ORIGINAL'           => '原創',
        'MANGA'              => '漫畫改編',
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
    );

    $season_label = isset( $season_labels[ $season ] ) ? $season_labels[ $season ] : $season;
    $format_label = isset( $format_labels[ $format ] ) ? $format_labels[ $format ] : $format;
    $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
    $status_class = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : '';
    $source_label = isset( $source_labels[ $source ] ) ? $source_labels[ $source ] : $source;

    $ep_str = '';
    if ( $episodes ) {
        $ep_str = ( $ep_aired && $ep_aired < $episodes )
            ? $ep_aired . ' / ' . $episodes . ' 集'
            : $episodes . ' 集';
    }

    $season_str = '';
    if ( $season_year && $season_label ) {
        $season_str = $season_year . ' ' . $season_label;
    } elseif ( $season_year ) {
        $season_str = (string) $season_year;
    }

    $genre_terms  = get_the_terms( $post_id, 'genre' );
    $season_terms = get_the_terms( $post_id, 'anime_season_tax' );

    $genre_terms        = is_array( $genre_terms ) ? $genre_terms : array();
    $season_terms       = is_array( $season_terms ) ? $season_terms : array();
    $season_child_terms = array();

    foreach ( $season_terms as $term ) {
        if ( ! empty( $term->parent ) ) {
            $season_child_terms[] = $term;
        }
    }
    /* 站內相關作品 */
    $site_relations = array();

    if ( ! empty( $relations_list ) ) {
        foreach ( $relations_list as $rel ) {
            $rel_anilist_id = 0;

            if ( isset( $rel['anilist_id'] ) ) {
                $rel_anilist_id = (int) $rel['anilist_id'];
            } elseif ( isset( $rel['id'] ) ) {
                $rel_anilist_id = (int) $rel['id'];
            }

            if ( ! $rel_anilist_id ) {
                continue;
            }

            $qr = get_posts(
                array(
                    'post_type'      => 'anime',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'no_found_rows'  => true,
                    'meta_query'     => array(
                        array(
                            'key'   => 'anime_anilist_id',
                            'value' => $rel_anilist_id,
                            'type'  => 'NUMERIC',
                        ),
                    ),
                )
            );

if ( ! empty( $qr ) ) {
    $site_rel_post = $qr[0];

    $relation_labels = array(
        'PREQUEL'     => '前傳',
        'SEQUEL'      => '續集',
        'PARENT'      => '正篇',
        'SIDE_STORY'  => '番外篇',
        'CHARACTER'   => '角色客串',
        'SUMMARY'     => '總集篇',
        'ALTERNATIVE' => '替代版本',
        'SPIN_OFF'    => '衍生作品',
        'OTHER'       => '其他',
        'SOURCE'      => '原作',
        'COMPILATION' => '合輯',
        'CONTAINS'    => '收錄',
        'ANIME'       => '動漫',
    );

    $raw_label = isset( $rel['relation_label'] ) ? $rel['relation_label'] : ( isset( $rel['type'] ) ? $rel['type'] : '' );

    $site_relations[] = array(
        'title_zh'       => get_post_meta( $site_rel_post->ID, 'anime_title_chinese', true )
                            ?: ( isset( $rel['title_zh'] ) ? $rel['title_zh'] : ( isset( $rel['title'] ) ? $rel['title'] : '' ) ),
        'title_native'   => isset( $rel['title_native'] ) ? $rel['title_native'] : ( isset( $rel['native'] ) ? $rel['native'] : '' ),
        'relation_label' => isset( $relation_labels[ $raw_label ] ) ? $relation_labels[ $raw_label ] : $raw_label,
        'format'         => isset( $rel['format'] ) ? $rel['format'] : '',
        'cover_image'    => get_post_meta( $site_rel_post->ID, 'anime_cover_image', true )
                            ?: ( isset( $rel['cover_image'] ) ? $rel['cover_image'] : '' ),
        'url'            => get_permalink( $site_rel_post->ID ),
    );
}



    /* Schema */
    $schema_type = 'TVSeries';
    if ( $format === 'MOVIE' ) {
        $schema_type = 'Movie';
    } elseif ( $format === 'MUSIC' ) {
        $schema_type = 'MusicVideoObject';
    }

    $schema_genres = array();
    foreach ( $genre_terms as $t ) {
        $schema_genres[] = $t->name;
    }

    $alternate_names = array_values(
        array_filter(
            array(
                $title_native,
                $title_romaji,
                $title_english,
            )
        )
    );

    $schema_description = wp_strip_all_tags( $synopsis );
    $schema_description = $substr_safe( $schema_description, 0, 200 );

    $schema = array(
        '@context'      => 'https://schema.org',
        '@type'         => $schema_type,
        'name'          => $display_title,
        'description'   => $schema_description,
        'image'         => $cover_image ? $cover_image : get_the_post_thumbnail_url( $post_id, 'large' ),
        'genre'         => $schema_genres,
        'datePublished' => $start_date,
        'url'           => get_permalink( $post_id ),
    );

    if ( ! empty( $alternate_names ) ) {
        $schema['alternateName'] = $alternate_names;
    }

    if ( $episodes ) {
        $schema['numberOfEpisodes'] = $episodes;
    }

    if ( $score_anilist_num > 0 ) {
        $schema['aggregateRating'] = array(
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format( $score_anilist_num / 10, 1 ),
            'bestRating'  => '10',
            'worstRating' => '1',
            'ratingCount' => max( 1, $popularity ),
        );
    }

    $breadcrumb_schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => array(
            array(
                '@type'    => 'ListItem',
                'position' => 1,
                'name'     => '首頁',
                'item'     => home_url( '/' ),
            ),
            array(
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => '動畫列表',
                'item'     => home_url( '/anime/' ),
            ),
            array(
                '@type'    => 'ListItem',
                'position' => 3,
                'name'     => $display_title,
                'item'     => get_permalink( $post_id ),
            ),
        ),
    );

    $faq_items    = array();
    $faq_schema   = null;
    $faq_json_raw = $get_meta( 'anime_faq_json' );

    if ( $faq_json_raw ) {
        $faq_decoded = json_decode( $faq_json_raw, true );
        if ( is_array( $faq_decoded ) ) {
            $faq_items = $faq_decoded;
        }
    }

    if ( ! empty( $faq_items ) ) {
        $faq_schema_main = array();

        foreach ( $faq_items as $f ) {
            if ( empty( $f['q'] ) || empty( $f['a'] ) ) {
                continue;
            }

            $faq_schema_main[] = array(
                '@type' => 'Question',
                'name'  => $f['q'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $f['a'] ),
                ),
            );
        }

        if ( ! empty( $faq_schema_main ) ) {
            $faq_schema = array(
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $faq_schema_main,
            );
        }
    }

    /* Cast：只取 MAIN，若無則取前 8 */
    $cast_main = array();

    foreach ( $cast_list as $c ) {
        $role = isset( $c['role'] ) ? strtoupper( $c['role'] ) : '';
        if ( $role === 'MAIN' ) {
            $cast_main[] = $c;
        }
    }

    if ( empty( $cast_main ) ) {
        $cast_main = array_slice( $cast_list, 0, 8 );
    }

    $cast_show_limit = 8;

    $poster_fallback = $fallback_text( $display_title, 2 );

    $has_sidebar_content = false;
    if ( ! empty( $news_items ) || ! empty( $site_relations ) || $affiliate_html || $official_site || $twitter_url || $wikipedia_url || $tiktok_url ) {
        $has_sidebar_content = true;
    }
?>
<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<script type="application/ld+json"><?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php if ( $faq_schema ) : ?>
<script type="application/ld+json"><?php echo wp_json_encode( $faq_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php endif; ?>

<div class="asd-wrap">

    <?php if ( $banner_image ) : ?>
        <div class="asd-banner" style="background-image:url(<?php echo esc_url( $banner_image ); ?>)">
            <div class="asd-banner-fade"></div>
        </div>
    <?php else : ?>
        <div class="asd-banner asd-banner--fallback"></div>
    <?php endif; ?>

    <nav class="asd-breadcrumb" aria-label="麵包屑導航">
        <ol itemscope itemtype="https://schema.org/BreadcrumbList">
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" itemprop="item">
                    <span itemprop="name">首頁</span>
                </a>
                <meta itemprop="position" content="1">
            </li>
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <a href="<?php echo esc_url( home_url( '/anime/' ) ); ?>" itemprop="item">
                    <span itemprop="name">動畫列表</span>
                </a>
                <meta itemprop="position" content="2">
            </li>
            <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                <span itemprop="name"><?php echo esc_html( $display_title ); ?></span>
                <meta itemprop="position" content="3">
            </li>
        </ol>
    </nav>

    <div class="asd-hero-new">

        <div class="asd-hero-poster">
            <?php if ( $cover_image ) : ?>
                <img
                    src="<?php echo esc_url( $cover_image ); ?>"
                    alt="<?php echo esc_attr( $display_title ); ?> 封面圖"
                    class="asd-poster-img"
                    loading="eager"
                    onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';"
                >
                <div class="asd-poster-fallback" style="display:none">
                    <span><?php echo esc_html( $poster_fallback ); ?></span>
                </div>
            <?php elseif ( has_post_thumbnail() ) : ?>
                <?php
                echo get_the_post_thumbnail(
                    $post_id,
                    'large',
                    array(
                        'class'   => 'asd-poster-img',
                        'loading' => 'eager',
                        'alt'     => $display_title . ' 封面圖',
                    )
                );
                ?>
            <?php else : ?>
                <div class="asd-poster-fallback">
                    <span><?php echo esc_html( $poster_fallback ); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="asd-hero-body">
            <div class="asd-hero-breadcrumb">
                <span>動畫</span>
                <?php if ( $season_str ) : ?>
                    <span class="asd-hbc-sep">›</span>
                    <span><?php echo esc_html( $season_str ); ?></span>
                <?php endif; ?>
                <?php if ( ! empty( $genre_terms ) ) : ?>
                    <span class="asd-hbc-sep">›</span>
                    <span><?php echo esc_html( $genre_terms[0]->name ); ?></span>
                <?php endif; ?>
            </div>

            <h1 class="asd-hero-title"><?php echo esc_html( $display_title ); ?></h1>

            <?php if ( $title_native ) : ?>
                <p class="asd-hero-native"><?php echo esc_html( $title_native ); ?></p>
            <?php endif; ?>

            <?php if ( $title_romaji ) : ?>
                <p class="asd-hero-native asd-hero-romaji"><?php echo esc_html( $title_romaji ); ?></p>
            <?php endif; ?>

            <div class="asd-hero-badges">
                <?php
                if ( $status_label ) {
                    $status_badge_class = 'asd-hbadge';
                    if ( $status_class ) {
                        $status_badge_class .= ' asd-hbadge--' . $status_class;
                    }
                    echo '<span class="' . esc_attr( $status_badge_class ) . '">' . esc_html( $status_label ) . '</span>';
                }

                if ( $format_label ) {
                    echo '<span class="asd-hbadge">' . esc_html( $format_label ) . '</span>';
                }

                if ( $season_str ) {
                    echo '<span class="asd-hbadge">' . esc_html( $season_str ) . '</span>';
                }

                if ( $ep_str ) {
                    echo '<span class="asd-hbadge">' . esc_html( $ep_str ) . '</span>';
                }

                foreach ( array_slice( $genre_terms, 0, 3 ) as $gt ) {
                    echo '<span class="asd-hbadge asd-hbadge--genre">' . esc_html( $gt->name ) . '</span>';
                }
                ?>
            </div>

            <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
                <div class="asd-hero-scores-new">
                    <?php if ( $score_anilist ) : ?>
                        <div class="asd-score-pill asd-score-pill--al">
                            <span class="asd-sp-dot"></span>
                            <span class="asd-sp-val"><?php echo esc_html( $score_anilist ); ?></span>
                            <span class="asd-sp-label">AniList</span>
                        </div>
                    <?php endif; ?>

                    <?php if ( $score_mal ) : ?>
                        <div class="asd-score-pill asd-score-pill--mal">
                            <span class="asd-sp-dot"></span>
                            <span class="asd-sp-val"><?php echo esc_html( $score_mal ); ?></span>
                            <span class="asd-sp-label">MAL</span>
                        </div>
                    <?php endif; ?>

                    <?php if ( $score_bangumi ) : ?>
                        <div class="asd-score-pill asd-score-pill--bgm">
                            <span class="asd-sp-dot"></span>
                            <span class="asd-sp-val"><?php echo esc_html( $score_bangumi ); ?></span>
                            <span class="asd-sp-label">Bangumi</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="asd-hero-actions">
                <?php if ( $youtube_id ) : ?>
                    <a href="#asd-sec-trailer" class="asd-action-btn asd-action-btn--primary">▶ 觀看預告</a>
                <?php endif; ?>

                <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?>
                    <a href="#asd-sec-stream" class="asd-action-btn asd-action-btn--ghost">📡 合法平台</a>
                <?php endif; ?>

                <?php if ( ! empty( $cast_main ) ) : ?>
                    <a href="#asd-sec-cast" class="asd-action-btn asd-action-btn--ghost">🎭 CAST</a>
                <?php endif; ?>

                <?php if ( $official_site ) : ?>
                    <a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener noreferrer" class="asd-action-btn asd-action-btn--ghost">🔗 官網</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="asd-hero-sidebar">
            <?php if ( $score_anilist || $score_mal || $score_bangumi ) : ?>
                <div class="asd-hside-block">
                    <div class="asd-hside-title">評分概況</div>

                    <?php if ( $score_anilist ) : ?>
                        <div class="asd-hside-row">
                            <span class="asd-hside-dot" style="background:var(--asd-score-al)"></span>
                            <span class="asd-hside-key">AniList</span>
                            <span class="asd-hside-val"><?php echo esc_html( $score_anilist ); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ( $score_mal ) : ?>
                        <div class="asd-hside-row">
                            <span class="asd-hside-dot" style="background:var(--asd-score-mal)"></span>
                            <span class="asd-hside-key">MAL</span>
                            <span class="asd-hside-val"><?php echo esc_html( $score_mal ); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ( $score_bangumi ) : ?>
                        <div class="asd-hside-row">
                            <span class="asd-hside-dot" style="background:var(--asd-score-bgm)"></span>
                            <span class="asd-hside-key">Bangumi</span>
                            <span class="asd-hside-val"><?php echo esc_html( $score_bangumi ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="asd-hside-block">
                <?php
                $meta_rows = array(
                    '集數' => $ep_str,
                    '時長' => $duration ? $duration . ' 分鐘' : '',
                    '原作' => $source_label,
                    '季節' => $season_str,
                    '製作' => $studio,
                );

                $has_any_meta = false;

                foreach ( $meta_rows as $mk => $mv ) :
                    if ( ! strlen( (string) $mv ) ) {
                        continue;
                    }
                    $has_any_meta = true;
                    ?>
                    <div class="asd-hside-info-row">
                        <span class="asd-hside-info-key"><?php echo esc_html( $mk ); ?></span>
                        <span class="asd-hside-info-val"><?php echo esc_html( $mv ); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if ( ! $has_any_meta ) : ?>
                    <p style="font-size:12px;color:var(--asd-text-muted);text-align:center;padding:8px 0;margin:0;">暫無資料</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <nav class="asd-tabs" id="asd-tabs" aria-label="頁面導覽">
        <a class="asd-tab" href="#asd-sec-info">📋 基本資訊</a>
        <?php if ( $synopsis ) : ?><a class="asd-tab" href="#asd-sec-synopsis">📝 劇情簡介</a><?php endif; ?>
        <?php if ( $youtube_id ) : ?><a class="asd-tab" href="#asd-sec-trailer">🎞 預告片</a><?php endif; ?>
        <?php if ( ! empty( $episodes_list ) ) : ?><a class="asd-tab" href="#asd-sec-episodes">📺 集數列表</a><?php endif; ?>
        <?php if ( ! empty( $staff_list ) ) : ?><a class="asd-tab" href="#asd-sec-staff">🎬 製作人員</a><?php endif; ?>
        <?php if ( ! empty( $cast_main ) ) : ?><a class="asd-tab" href="#asd-sec-cast">🎭 角色聲優</a><?php endif; ?>
        <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?><a class="asd-tab" href="#asd-sec-music">🎵 主題曲</a><?php endif; ?>
        <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?><a class="asd-tab" href="#asd-sec-stream">📡 串流平台</a><?php endif; ?>
        <?php if ( ! empty( $faq_items ) ) : ?><a class="asd-tab" href="#asd-sec-faq">❓ 常見問題</a><?php endif; ?>
    </nav>

    <div class="asd-container<?php echo $has_sidebar_content ? ' asd-container--has-sidebar' : ''; ?>">

        <main class="asd-main" id="asd-main">

            <section class="asd-section" id="asd-sec-info">
                <h2 class="asd-section-title">📋 基本資訊</h2>

                <div class="asd-info-grid">
                    <?php
                    $info_rows = array(
                        '類型'     => $format_label,
                        '集數'     => $ep_str,
                        '狀態'     => $status_label,
                        '播出季度' => $season_str,
                        '每集時長' => $duration ? $duration . ' 分鐘' : '',
                        '開始日期' => $start_date,
                        '結束日期' => ( $end_date && $status === 'FINISHED' ) ? $end_date : '',
                        '原作來源' => $source_label,
                        '製作公司' => $studio,
                        '台灣代理' => $tw_dist_display,
                        '台灣播出' => $tw_broadcast,
                    );

                    foreach ( $info_rows as $label => $val ) :
                        if ( $val === '' || $val === null ) {
                            continue;
                        }
                        ?>
                        <div class="asd-info-row">
                            <span class="asd-info-label"><?php echo esc_html( $label ); ?></span>
                            <span class="asd-info-val"><?php echo esc_html( $val ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ( $status === 'RELEASING' && ! empty( $airing_data['airingAt'] ) ) : ?>
                    <div class="asd-airing-bar">
                        <span>📅 第 <?php echo esc_html( isset( $airing_data['episode'] ) ? $airing_data['episode'] : '' ); ?> 集播出倒數：</span>
                        <strong class="asd-countdown" data-ts="<?php echo esc_attr( $airing_data['airingAt'] ); ?>">計算中…</strong>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ( $synopsis ) : ?>
                <section class="asd-section" id="asd-sec-synopsis">
                    <h2 class="asd-section-title">📝 劇情簡介</h2>
                    <div class="asd-synopsis"><?php echo wp_kses_post( wpautop( $synopsis ) ); ?></div>
                </section>
            <?php endif; ?>

            <?php if ( $youtube_id ) : ?>
                <section class="asd-section" id="asd-sec-trailer">
                    <h2 class="asd-section-title">🎞 預告片</h2>
                    <div class="asd-trailer-wrap">
                        <iframe
                            src="https://www.youtube.com/embed/<?php echo esc_attr( $youtube_id ); ?>?rel=0&modestbranding=1"
                            title="<?php echo esc_attr( $display_title ); ?> 預告片"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                            loading="lazy">
                        </iframe>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $episodes_list ) ) : ?>
                <section class="asd-section" id="asd-sec-episodes">
                    <h2 class="asd-section-title">📺 集數列表</h2>

                    <div class="asd-ep-list" id="asd-ep-list">
                        <?php foreach ( $episodes_list as $i => $ep ) : ?>
                            <?php
                            $ep_num     = isset( $ep['ep'] ) ? (int) $ep['ep'] : 0;
                            $ep_name_cn = trim( isset( $ep['name_cn'] ) ? $ep['name_cn'] : '' );
                            $ep_name_ja = trim( isset( $ep['name'] ) ? $ep['name'] : '' );
                            $ep_airdate = isset( $ep['airdate'] ) ? $ep['airdate'] : '';

                            if ( $ep_name_cn !== '' && class_exists( 'Anime_Sync_CN_Converter' ) && method_exists( 'Anime_Sync_CN_Converter', 'static_convert' ) ) {
                                $ep_name_cn = Anime_Sync_CN_Converter::static_convert( $ep_name_cn );
                            }

                            $ep_name = $ep_name_cn ? $ep_name_cn : $ep_name_ja;
                            ?>
                            <div class="asd-ep-row<?php echo $i >= 3 ? ' asd-ep-hidden' : ''; ?>">
                                <span class="asd-ep-num">第 <?php echo esc_html( $ep_num ); ?> 集</span>

                                <span class="asd-ep-title">
                                    <?php if ( $ep_name ) : ?>
                                        <?php echo esc_html( $ep_name ); ?>
                                    <?php endif; ?>

                                    <?php if ( $ep_name_cn && $ep_name_ja && $ep_name_cn !== $ep_name_ja ) : ?>
                                        <small class="asd-ep-title-ja"><?php echo esc_html( $ep_name_ja ); ?></small>
                                    <?php endif; ?>
                                </span>

                                <?php if ( $ep_airdate ) : ?>
                                    <span class="asd-ep-date"><?php echo esc_html( $ep_airdate ); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ( count( $episodes_list ) > 3 ) : ?>
                        <div class="asd-ep-more-wrap">
                            <button class="asd-ep-more-btn" id="asd-ep-more-btn" data-total="<?php echo esc_attr( count( $episodes_list ) ); ?>">
                                顯示全部 <?php echo esc_html( count( $episodes_list ) ); ?> 集 ▾
                            </button>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $staff_list ) ) : ?>
                <section class="asd-section" id="asd-sec-staff">
                    <h2 class="asd-section-title">🎬 STAFF</h2>

                    <div class="asd-staff-grid">
                        <?php foreach ( $staff_list as $i => $s ) : ?>
                            <?php
                            $s_name = isset( $s['name'] ) ? $s['name'] : '';
                            $s_role = isset( $s['role'] ) ? $s['role'] : '';
                            ?>
                            <div class="asd-staff-card<?php echo $i >= 12 ? ' asd-staff-extra' : ''; ?>">
                                <div class="asd-staff-names">
                                    <span class="asd-staff-name"><?php echo esc_html( $s_name ); ?></span>
                                    <?php if ( $s_role ) : ?>
                                        <span class="asd-staff-role"><?php echo esc_html( $s_role ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ( count( $staff_list ) > 12 ) : ?>
                        <div class="asd-staff-more-wrap">
                            <button class="asd-staff-more-btn" id="asd-staff-more-btn" data-total="<?php echo esc_attr( count( $staff_list ) ); ?>">
                                顯示全部 <?php echo esc_html( count( $staff_list ) ); ?> 位人員 ▾
                            </button>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $cast_main ) ) : ?>
                <section class="asd-section" id="asd-sec-cast">
                    <h2 class="asd-section-title">🎭 CAST</h2>

                    <div class="asd-cast-grid" id="asd-cast-grid">
                        <?php foreach ( $cast_main as $i => $c ) : ?>
                            <?php
                            $char_name = isset( $c['name'] ) ? $c['name'] : '';
                            $char_img  = isset( $c['image'] ) ? $c['image'] : '';
                            $va_list   = isset( $c['voice_actors'] ) && is_array( $c['voice_actors'] ) ? $c['voice_actors'] : array();
                            $va_name   = ! empty( $va_list ) && ! empty( $va_list[0]['name'] ) ? $va_list[0]['name'] : '';
                            $char_init = $fallback_text( $char_name ? $char_name : '？', 1 );
                            ?>
                            <div class="asd-cast-card<?php echo $i >= $cast_show_limit ? ' asd-cast-extra' : ''; ?>">
                                <div class="asd-cast-img">
                                    <?php if ( $char_img ) : ?>
                                        <img
                                            src="<?php echo esc_url( $char_img ); ?>"
                                            alt="<?php echo esc_attr( $char_name ); ?>"
                                            loading="lazy"
                                            onerror="this.onerror=null;this.parentElement.innerHTML='<div class=&quot;asd-cast-noimg&quot;><?php echo esc_js( $char_init ); ?></div>';"
                                        >
                                    <?php else : ?>
                                        <div class="asd-cast-noimg"><?php echo esc_html( $char_init ); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="asd-cast-names">
                                    <?php if ( $char_name ) : ?>
                                        <span class="asd-cast-char"><?php echo esc_html( $char_name ); ?></span>
                                    <?php endif; ?>

                                    <?php if ( $va_name ) : ?>
                                        <span class="asd-cast-va">CV: <?php echo esc_html( $va_name ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ( count( $cast_main ) > $cast_show_limit ) : ?>
                        <div class="asd-cast-more-wrap">
                            <button class="asd-cast-more-btn" id="asd-cast-more-btn" data-total="<?php echo esc_attr( count( $cast_main ) ); ?>">
                                顯示更多角色 ▾
                            </button>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <?php if ( ! empty( $openings ) || ! empty( $endings ) ) : ?>
                <section class="asd-section" id="asd-sec-music">
                    <h2 class="asd-section-title">🎵 主題曲</h2>

                    <?php
                    $music_groups = array(
                        '片頭曲 OP' => $openings,
                        '片尾曲 ED' => $endings,
                    );

                    foreach ( $music_groups as $group_label => $group ) :
                        if ( empty( $group ) ) {
                            continue;
                        }
                        ?>
                        <div class="asd-theme-group">
                            <p class="asd-theme-group-title"><?php echo esc_html( $group_label ); ?></p>

                            <?php foreach ( $group as $t ) : ?>
                                <?php
                                $t_type   = strtoupper( trim( isset( $t['type'] ) ? $t['type'] : '' ) );
                                $t_title  = trim( isset( $t['song_title'] ) ? $t['song_title'] : ( isset( $t['title'] ) ? $t['title'] : '' ) );
                                $t_artist = trim( isset( $t['artist'] ) ? $t['artist'] : '' );
                                $t_num    = preg_replace( '/[^0-9]/', '', $t_type );
                                $t_label  = $starts_with( $t_type, 'OP' ) ? 'OP' : 'ED';
                                $t_label .= $t_num ? $t_num : '';
                                $t_audio  = isset( $t['audio_url'] ) ? $t['audio_url'] : ( isset( $t['audio'] ) ? $t['audio'] : '' );
                                ?>
                                <div class="asd-theme-card">
                                    <div class="asd-theme-card-header">
                                        <span class="asd-theme-badge"><?php echo esc_html( $t_label ); ?></span>
                                        <div class="asd-theme-card-meta">
                                            <span class="asd-theme-title"><?php echo esc_html( $t_title ); ?></span>
                                            <?php if ( $t_artist ) : ?>
                                                <span class="asd-theme-artist">— <?php echo esc_html( $t_artist ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ( $t_audio ) : ?>
                                        <audio controls preload="none" class="asd-theme-audio">
                                            <source src="<?php echo esc_url( $t_audio ); ?>">
                                            您的瀏覽器不支援音訊播放。
                                        </audio>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $tw_streaming_items ) || ! empty( $streaming_list ) ) : ?>
                <section class="asd-section" id="asd-sec-stream">
                    <h2 class="asd-section-title">📡 串流平台</h2>

                    <?php if ( ! empty( $tw_streaming_items ) ) : ?>
                        <div class="asd-stream-group">
                            <p class="asd-stream-group-title">🇹🇼 台灣</p>
                            <div class="asd-stream-list">
                                <?php foreach ( $tw_streaming_items as $item ) : ?>
                                    <div class="asd-stream-item">
                                        <?php if ( ! empty( $item['url'] ) ) : ?>
                                            <a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="asd-stream-link">
                                                <?php echo esc_html( $item['label'] ); ?> ↗
                                            </a>
                                        <?php else : ?>
                                            <span class="asd-stream-name"><?php echo esc_html( $item['label'] ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $streaming_list ) ) : ?>
                        <div class="asd-stream-group">
                            <p class="asd-stream-group-title">🌐 國際</p>
                            <div class="asd-stream-list">
                                <?php foreach ( $streaming_list as $s ) : ?>
                                    <?php
                                    $site_name = isset( $s['site'] ) ? $s['site'] : '';
                                    $site_url  = isset( $s['url'] ) ? $s['url'] : '';
                                    if ( ! $site_name ) {
                                        continue;
                                    }
                                    ?>
                                    <div class="asd-stream-item">
                                        <?php if ( $site_url ) : ?>
                                            <a href="<?php echo esc_url( $site_url ); ?>" target="_blank" rel="noopener noreferrer" class="asd-stream-link">
                                                <?php echo esc_html( $site_name ); ?> ↗
                                            </a>
                                        <?php else : ?>
                                            <span class="asd-stream-name"><?php echo esc_html( $site_name ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $faq_items ) ) : ?>
                <section class="asd-section" id="asd-sec-faq">
                    <h2 class="asd-section-title">❓ 常見問題</h2>

                    <div class="asd-faq-list">
                        <?php foreach ( $faq_items as $faq ) : ?>
                            <?php
                            $faq_q = isset( $faq['q'] ) ? $faq['q'] : '';
                            $faq_a = isset( $faq['a'] ) ? $faq['a'] : '';

                            if ( $faq_q === '' && $faq_a === '' ) {
                                continue;
                            }
                            ?>
                            <div class="asd-faq-item">
                                <?php if ( $faq_q ) : ?>
                                    <p class="asd-faq-q"><?php echo esc_html( $faq_q ); ?></p>
                                <?php endif; ?>

                                <?php if ( $faq_a ) : ?>
                                    <p class="asd-faq-a"><?php echo wp_kses_post( $faq_a ); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        </main>

        <?php if ( $has_sidebar_content ) : ?>
            <aside class="asd-sidebar">

                <?php if ( ! empty( $news_items ) ) : ?>
                    <section class="asd-section">
                        <h2 class="asd-section-title">📰 相關新聞</h2>

                        <div class="asd-news-list">
                            <?php foreach ( $news_items as $news ) : ?>
                                <div class="asd-news-item">
                                    <?php if ( ! empty( $news['url'] ) ) : ?>
                                        <p class="asd-news-title">
                                            <a href="<?php echo esc_url( $news['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html( $news['title'] ); ?>
                                            </a>
                                        </p>
                                    <?php else : ?>
                                        <p class="asd-news-title"><?php echo esc_html( $news['title'] ); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ( ! empty( $site_relations ) ) : ?>
                    <section class="asd-section" id="asd-sec-relations">
                        <h2 class="asd-section-title">🎬 關聯作品</h2>

                        <div class="asd-relations-grid">
                            <?php foreach ( $site_relations as $rel ) : ?>
                                <?php
                                $rel_title = ! empty( $rel['title_zh'] ) ? $rel['title_zh'] : $rel['title_native'];
                                ?>
                                <a href="<?php echo esc_url( $rel['url'] ); ?>" class="asd-relation-card">
                                    <?php if ( ! empty( $rel['cover_image'] ) ) : ?>
                                        <div class="asd-relation-thumb">
                                            <img
                                                src="<?php echo esc_url( $rel['cover_image'] ); ?>"
                                                alt="<?php echo esc_attr( $rel_title ); ?>"
                                                loading="lazy"
                                                onerror="this.style.display='none';"
                                            >
                                        </div>
                                    <?php endif; ?>

                                    <div class="asd-relation-info">
                                        <span class="asd-relation-type"><?php echo esc_html( $rel['relation_label'] ); ?></span>
                                        <span class="asd-relation-title"><?php echo esc_html( $rel_title ); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ( $affiliate_html ) : ?>
                    <section class="asd-section">
                        <h2 class="asd-section-title">💡 推薦入口</h2>
                        <div class="asd-synopsis"><?php echo wp_kses_post( $affiliate_html ); ?></div>
                    </section>
                <?php endif; ?>
                <?php if ( ! empty( $genre_terms ) || ! empty( $season_child_terms ) ) : ?>
                    <section class="asd-section">
                        <h2 class="asd-section-title">🏷 作品標籤</h2>


                            <?php if ( ! empty( $genre_terms ) ) : ?>
                                <div class="asd-seo-row">
                                    <span class="asd-seo-label">類型：</span>
                                    <?php foreach ( $genre_terms as $gt ) : ?>
                                        <a href="<?php echo esc_url( get_term_link( $gt ) ); ?>" class="asd-seo-tag"><?php echo esc_html( $gt->name ); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ( ! empty( $season_child_terms ) ) : ?>
                                <div class="asd-seo-row">
                                    <span class="asd-seo-label">季度：</span>
                                    <?php foreach ( $season_child_terms as $st ) : ?>
                                        <a href="<?php echo esc_url( get_term_link( $st ) ); ?>" class="asd-seo-tag"><?php echo esc_html( $st->name ); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php if ( $official_site || $twitter_url || $wikipedia_url || $tiktok_url ) : ?>
                    <section class="asd-section">
                        <h2 class="asd-section-title">🔗 外部連結</h2>

                        <div class="asd-stream-list">
                            <?php if ( $official_site ) : ?>
                                <div class="asd-stream-item">
                                    <a href="<?php echo esc_url( $official_site ); ?>" target="_blank" rel="noopener noreferrer" class="asd-stream-link">官方網站 ↗</a>
                                </div>
                            <?php endif; ?>

                            <?php if ( $twitter_url ) : ?>
                                <div class="asd-stream-item">
                                    <a href="<?php echo esc_url( $twitter_url ); ?>" target="_blank" rel="noopener noreferrer" class="asd-stream-link">Twitter ↗</a>
                                </div>
                            <?php endif; ?>

                            <?php if ( $wikipedia_url ) : ?>
                                <div class="asd-stream-item">
                                    <a href="<?php echo esc_url( $wikipedia_url ); ?>" target="_blank" rel="noopener noreferrer" class="asd-stream-link">Wikipedia ↗</a>
                                </div>
                            <?php endif; ?>

                            <?php if ( $tiktok_url ) : ?>
                                <div class="asd-stream-item">
                                    <a href="<?php echo esc_url( $tiktok_url ); ?>" target="_blank" rel="noopener noreferrer" class="asd-stream-link">TikTok ↗</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

            </aside>
        <?php endif; ?>

    </div>
</div>

<script>
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        var epBtn = document.getElementById('asd-ep-more-btn');
        var epList = document.getElementById('asd-ep-list');

        if (epBtn && epList) {
            var epTotal = parseInt(epBtn.getAttribute('data-total') || '0', 10);
            var epExpanded = false;

            epBtn.addEventListener('click', function () {
                var rows = epList.querySelectorAll('.asd-ep-row');

                rows.forEach(function (row, index) {
                    if (index >= 3) {
                        if (epExpanded) {
                            row.classList.add('asd-ep-hidden');
                        } else {
                            row.classList.remove('asd-ep-hidden');
                        }
                    }
                });

                epExpanded = !epExpanded;
                epBtn.textContent = epExpanded ? '收起 ▴' : ('顯示全部 ' + epTotal + ' 集 ▾');
            });
        }

        var staffBtn = document.getElementById('asd-staff-more-btn');
        if (staffBtn) {
            var staffExpanded = false;

            staffBtn.addEventListener('click', function () {
                var extras = document.querySelectorAll('.asd-staff-extra');
                var total = parseInt(staffBtn.getAttribute('data-total') || '0', 10);

                extras.forEach(function (el) {
                    el.style.display = staffExpanded ? 'none' : 'flex';
                });

                staffExpanded = !staffExpanded;
                staffBtn.textContent = staffExpanded ? '收起 ▴' : ('顯示全部 ' + total + ' 位人員 ▾');
            });
        }

        var castBtn = document.getElementById('asd-cast-more-btn');
        if (castBtn) {
            castBtn.addEventListener('click', function () {
                var extras = document.querySelectorAll('.asd-cast-extra');

                extras.forEach(function (el) {
                    el.classList.remove('asd-cast-extra');
                    el.style.display = 'flex';
                });

                var wrap = document.querySelector('.asd-cast-more-wrap');
                if (wrap) {
                    wrap.style.display = 'none';
                }
            });
        }

        var countdown = document.querySelector('.asd-countdown[data-ts]');
        if (countdown) {
            var ts = parseInt(countdown.getAttribute('data-ts'), 10) * 1000;

            function updateCountdown() {
                var diff = ts - Date.now();

                if (isNaN(ts)) {
                    countdown.textContent = '時間資料錯誤';
                    return;
                }

                if (diff <= 0) {
                    countdown.textContent = '即將播出';
                    return;
                }

                var d = Math.floor(diff / 86400000);
                var h = Math.floor((diff % 86400000) / 3600000);
                var m = Math.floor((diff % 3600000) / 60000);

                countdown.textContent = d + ' 天 ' + h + ' 時 ' + m + ' 分';
            }

            updateCountdown();
            setInterval(updateCountdown, 60000);
        }

        var tabLinks = Array.prototype.slice.call(document.querySelectorAll('.asd-tabs .asd-tab'));

        if (tabLinks.length) {
            function activateCurrentTab() {
                var scrollTop = window.scrollY || window.pageYOffset;
                var activeLink = null;

                tabLinks.forEach(function (link) {
                    var targetId = link.getAttribute('href');
                    if (!targetId || targetId.charAt(0) !== '#') {
                        return;
                    }

                    var section = document.querySelector(targetId);
                    if (!section) {
                        return;
                    }

                    var top = section.offsetTop - 140;
                    var bottom = top + section.offsetHeight;

                    if (scrollTop >= top && scrollTop < bottom) {
                        activeLink = link;
                    }
                });

                if (!activeLink && tabLinks.length) {
                    activeLink = tabLinks[0];
                }

                tabLinks.forEach(function (link) {
                    link.classList.remove('active');
                });

                if (activeLink) {
                    activeLink.classList.add('active');
                }
            }

            activateCurrentTab();
            window.addEventListener('scroll', activateCurrentTab, { passive: true });

            tabLinks.forEach(function (link) {
                link.addEventListener('click', function () {
                    tabLinks.forEach(function (l) {
                        l.classList.remove('active');
                    });
                    link.classList.add('active');
                });
            });
        }
    });
})();
</script>

<?php
endwhile;
get_footer();

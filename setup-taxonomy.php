<?php
/**
 * 微笑動漫 — 分類建立腳本 FINAL v3
 * 路徑：wp-content/plugins/anime-sync-pro/setup-taxonomy.php
 * 用管理員帳號登入後訪問執行，完成後立即刪除
 */

$wp_load = dirname( __FILE__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) die( '找不到 wp-load.php' );
require_once $wp_load;

if ( ! current_user_can( 'manage_options' ) ) die( '請先登入 WordPress 管理員帳號' );

function insert_term_safe( string $name, string $taxonomy, array $args = [] ): int {
    $slug     = $args['slug'] ?? sanitize_title( $name );
    $existing = get_term_by( 'slug', $slug, $taxonomy );
    if ( $existing ) {
        echo "<p style='color:#888'>⏭️ 已存在：{$name} ({$slug})</p>";
        return (int) $existing->term_id;
    }
    $result = wp_insert_term( $name, $taxonomy, $args );
    if ( is_wp_error( $result ) ) {
        echo "<p style='color:orange'>⚠️ {$name} ({$slug})：" . $result->get_error_message() . "</p>";
        return 0;
    }
    echo "<p style='color:green'>✅ 建立：{$name} ({$slug})</p>";
    return (int) $result['term_id'];
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8">
<style>body{font-family:monospace;padding:30px;background:#f5f5f5;}</style>
</head><body>';
echo '<h1>微笑動漫 分類建立 FINAL v3</h1>';

// ══════════════════════════════════════════════════════
// 第一部分：文章分類（全部頂層，零子分類，零衝突）
// ══════════════════════════════════════════════════════
echo '<h2>文章分類（category）</h2>';
echo '<p style="color:#555">發文時同時勾選內容類型＋主題分類，Rank Math 設定主要分類決定 URL。</p>';

$categories = [
    // 內容類型（標記用）
    [ '新聞',     'news'     ],
    [ '評論',     'review'   ],
    [ '專題',     'feature'  ],

    // 主題分類（決定 URL）
    [ '動漫',     'anime'    ],
    [ '漫畫',     'manga'    ],
    [ '輕小說',   'novel'    ],
    [ '音樂',     'music'    ],
    [ '遊戲',     'games'    ],
    [ '電競',     'esports'  ],
    [ 'VTuber',   'vtuber'   ],
    [ 'Cosplay',  'cosplay'  ],
    [ '周邊',     'merch'    ],
    [ '聖地巡禮', 'travel'   ],
    [ 'AI工具',   'ai-tools' ],
    [ '排行',     'rankings' ],
];

foreach ( $categories as [ $name, $slug ] ) {
    insert_term_safe( $name, 'category', [ 'slug' => $slug ] );
}

// ══════════════════════════════════════════════════════
// 第二部分：動漫類型（genre）
// ══════════════════════════════════════════════════════
echo '<h2>動漫類型（genre）</h2>';

$genres = [
    [ '動作',     'action'        ],
    [ '冒險',     'adventure'     ],
    [ '喜劇',     'comedy'        ],
    [ '劇情',     'drama'         ],
    [ '奇幻',     'fantasy'       ],
    [ '恐怖',     'horror'        ],
    [ '魔法少女', 'mahou-shoujo'  ],
    [ '機甲',     'mecha'         ],
    [ '音樂',     'music-genre'   ],
    [ '推理',     'mystery'       ],
    [ '懸疑',     'suspense'      ],
    [ '心理',     'psychological' ],
    [ '科幻',     'sci-fi'        ],
    [ '日常',     'slice-of-life' ],
    [ '運動',     'sports'        ],
    [ '超自然',   'supernatural'  ],
    [ '驚悚',     'thriller'      ],
    [ '異世界',   'isekai'        ],
    [ '後宮',     'harem'         ],
    [ '百合',     'yuri'          ],
    [ '耽美',     'bl'            ],
    [ '歷史',     'historical'    ],
    [ '武俠',     'wuxia'         ],
    [ '校園',     'school'        ],
    [ '兒童',     'kids'          ],
    [ '輕色情',   'ecchi'         ],
    [ '戀愛',     'romance'       ],
];

foreach ( $genres as [ $name, $slug ] ) {
    insert_term_safe( $name, 'genre', [ 'slug' => $slug ] );
}

// ══════════════════════════════════════════════════════
// 第三部分：播出季度（anime_season_tax）2000–2030
// ══════════════════════════════════════════════════════
echo '<h2>播出季度（anime_season_tax）</h2>';

$seasons = [
    'winter' => '冬季',
    'spring' => '春季',
    'summer' => '夏季',
    'fall'   => '秋季',
];

for ( $year = 2000; $year <= 2030; $year++ ) {
    $parent_id = insert_term_safe( (string) $year, 'anime_season_tax', [
        'slug' => (string) $year,
    ]);
    foreach ( $seasons as $suffix => $label ) {
        insert_term_safe( "{$year} {$label}", 'anime_season_tax', [
            'slug'   => "{$year}-{$suffix}",
            'parent' => $parent_id,
        ]);
    }
}

// ══════════════════════════════════════════════════════
// 第四部分：動漫格式（anime_format_tax）
// ══════════════════════════════════════════════════════
echo '<h2>動漫格式（anime_format_tax）</h2>';

$formats = [
    [ 'TV',     'format-tv'       ],
    [ 'TV短篇', 'format-tv-short' ],
    [ '劇場版', 'format-movie'    ],
    [ 'OVA',    'format-ova'      ],
    [ 'ONA',    'format-ona'      ],
    [ '特別篇', 'format-special'  ],
    [ '音樂MV', 'format-music'    ],
];

foreach ( $formats as [ $name, $slug ] ) {
    insert_term_safe( $name, 'anime_format_tax', [ 'slug' => $slug ] );
}

// ══════════════════════════════════════════════════════
// 完成
// ══════════════════════════════════════════════════════
update_option( 'smileacg_taxonomy_final_v3', true );

echo '<hr>';
echo '<h2 style="color:green">✅ 完成！</h2>';
echo '<ul>';
echo '<li>後台 → 文章 → 分類目錄，刪除所有舊分類</li>';
echo '<li>設定 → 永久連結 → 自訂結構：<code>/%category%/%postname%/</code></li>';
echo '<li>分類目錄基點留空，儲存兩次</li>';
echo '<li style="color:red;font-weight:bold">⚠️ 立即刪除此檔案！</li>';
echo '</ul>';
echo '</body></html>';

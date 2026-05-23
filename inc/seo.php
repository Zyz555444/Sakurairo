<?php
/**
 * Theme SEO: meta tags, Open Graph, Twitter Cards, JSON-LD, breadcrumbs.
 *
 * @package Sakurairo
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cached breadcrumb trail for JSON-LD and markup.
 *
 * @return array<int, array{name: string, url: string}>
 */
function iro_get_breadcrumb_items()
{
    static $items = null;

    if ($items !== null) {
        return $items;
    }

    if (is_front_page() && !is_paged()) {
        $items = [];
        return $items;
    }

    $items = [
        [
            'name' => __('Home', 'sakurairo'),
            'url'  => home_url('/'),
        ],
    ];

    if (is_category()) {
        $cat = get_queried_object();
        if ($cat && !is_wp_error($cat)) {
            $ancestors = array_reverse(get_ancestors($cat->term_id, 'category'));
            foreach ($ancestors as $ancestor_id) {
                $ancestor = get_category($ancestor_id);
                if ($ancestor) {
                    $items[] = [
                        'name' => $ancestor->name,
                        'url'  => get_category_link($ancestor_id),
                    ];
                }
            }
            $items[] = [
                'name' => single_cat_title('', false),
                'url'  => get_category_link($cat->term_id),
            ];
        }
    } elseif (is_tag()) {
        $tag = get_queried_object();
        if ($tag && !is_wp_error($tag)) {
            $items[] = [
                'name' => single_tag_title('', false),
                'url'  => get_tag_link($tag->term_id),
            ];
        }
    } elseif (is_author()) {
        $items[] = [
            'name' => get_the_author(),
            'url'  => get_author_posts_url(get_queried_object_id()),
        ];
    } elseif (is_search()) {
        $items[] = [
            'name' => sprintf(
                /* translators: %s: search query */
                __('Search: %s', 'sakurairo'),
                get_search_query()
            ),
            'url' => get_search_link(),
        ];
    } elseif (is_singular()) {
        if (is_single()) {
            $categories = get_the_category();
            if (!empty($categories)) {
                $primary = $categories[0];
                $ancestors = array_reverse(get_ancestors($primary->term_id, 'category'));
                foreach ($ancestors as $ancestor_id) {
                    $ancestor = get_category($ancestor_id);
                    if ($ancestor) {
                        $items[] = [
                            'name' => $ancestor->name,
                            'url'  => get_category_link($ancestor_id),
                        ];
                    }
                }
                $items[] = [
                    'name' => $primary->name,
                    'url'  => get_category_link($primary->term_id),
                ];
            }
        }
        $items[] = [
            'name' => get_the_title(),
            'url'  => get_permalink(),
        ];
    } elseif (is_archive()) {
        $items[] = [
            'name' => wp_strip_all_tags(get_the_archive_title()),
            'url'  => '',
        ];
    }

    return $items;
}

/**
 * Output breadcrumb navigation.
 */
function iro_the_breadcrumbs()
{
    if (!iro_opt('iro_seo_breadcrumb', true)) {
        return;
    }

    if (is_front_page() && !is_paged()) {
        return;
    }

    $items = iro_get_breadcrumb_items();
    if (count($items) < 2) {
        return;
    }

    echo '<nav class="iro-breadcrumbs" aria-label="' . esc_attr__('Breadcrumb', 'sakurairo') . '">';
    echo '<ol class="iro-breadcrumbs-list" itemscope itemtype="https://schema.org/BreadcrumbList">';

    $position = 1;
    $last_index = count($items) - 1;

    foreach ($items as $index => $item) {
        $is_last = ($index === $last_index);
        echo '<li class="iro-breadcrumbs-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        echo '<meta itemprop="position" content="' . esc_attr((string) $position) . '" />';

        if (!$is_last && !empty($item['url'])) {
            echo '<a itemprop="item" href="' . esc_url($item['url']) . '">';
            echo '<span itemprop="name">' . esc_html($item['name']) . '</span>';
            echo '</a>';
        } else {
            echo '<span itemprop="name" aria-current="page">' . esc_html($item['name']) . '</span>';
        }

        echo '</li>';
        if (!$is_last) {
            echo '<li class="iro-breadcrumbs-sep" aria-hidden="true">/</li>';
        }
        $position++;
    }

    echo '</ol></nav>';
}

/**
 * Plain-text description for meta / OG.
 */
function iro_seo_get_description_text()
{
    global $post;
    $description = '';

    if (is_singular() && !empty($post)) {
        if (has_excerpt($post)) {
            $description = trim(wp_strip_all_tags(get_the_excerpt($post)));
        } elseif (!empty($post->post_content)) {
            $description = trim(mb_strimwidth(
                preg_replace('/\s+/', ' ', strip_tags($post->post_content)),
                0,
                240,
                '…'
            ));
        }
    }

    if (empty($description) && is_category()) {
        $description = trim(category_description());
    }

    if (empty($description) && is_tag()) {
        $description = trim(tag_description());
    }

    if (empty($description) && is_author()) {
        $description = trim(get_the_author_meta('description', get_queried_object_id()));
    }

    if (empty($description)) {
        $description = iro_opt('iro_meta_description');
    }

    if (empty($description)) {
        $description = get_bloginfo('description');
    }

    return $description;
}

function iro_get_keywords()
{
    global $post;
    $keywords = '';

    if (is_singular()) {
        $tags = get_the_tags();
        if ($tags) {
            $keywords = implode(',', array_column($tags, 'name'));
        }
    } elseif (is_category()) {
        $cats = get_the_category();
        if ($cats) {
            $keywords = implode(',', array_column($cats, 'name'));
        }
    }

    if (empty($keywords)) {
        $keywords = iro_opt('iro_meta_keywords');
    }

    if (empty($keywords)) {
        $keywords = get_bloginfo('name');
    }

    if (!empty($keywords)) {
        return '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
    }
    return '';
}

function iro_get_description()
{
    $description = iro_seo_get_description_text();
    if (!empty($description)) {
        return '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }
    return '';
}

/**
 * First image URL from post content.
 */
function iro_seo_get_content_image_url($post = null)
{
    if (!$post) {
        global $post;
    }
    if (empty($post->post_content)) {
        return '';
    }
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $matches)) {
        return esc_url_raw($matches[1]);
    }
    return '';
}

/**
 * OG image URL for current context.
 */
function iro_seo_get_image_url()
{
    $default_og = iro_opt('iro_seo_default_og_image');
    if (!empty($default_og) && !is_singular()) {
        return esc_url_raw($default_og);
    }

    if (is_singular()) {
        $thumb_id = get_post_thumbnail_id();
        if ($thumb_id) {
            $url = wp_get_attachment_image_url($thumb_id, 'large');
            if ($url) {
                return esc_url_raw($url);
            }
        }
        $content_img = iro_seo_get_content_image_url();
        if ($content_img) {
            return $content_img;
        }
    }

    if (!empty($default_og)) {
        return esc_url_raw($default_og);
    }

    if (function_exists('DEFAULT_FEATURE_IMAGE')) {
        $fallback = DEFAULT_FEATURE_IMAGE();
        if (!empty($fallback)) {
            return esc_url_raw($fallback);
        }
    }

    $favicon = iro_opt('favicon_link');
    if (!empty($favicon)) {
        return esc_url_raw($favicon);
    }

    return '';
}

function iro_seo_get_canonical_url()
{
    if (is_singular()) {
        return get_permalink();
    }
    if (is_category()) {
        return get_category_link(get_queried_object_id());
    }
    if (is_tag()) {
        return get_tag_link(get_queried_object_id());
    }
    if (is_author()) {
        return get_author_posts_url(get_queried_object_id());
    }
    if (is_search()) {
        return get_search_link();
    }
    if (is_front_page()) {
        return home_url('/');
    }
    if (is_home()) {
        $posts_page = get_option('page_for_posts');
        return $posts_page ? get_permalink($posts_page) : home_url('/');
    }
    if (is_archive()) {
        return get_pagenum_link();
    }
    return '';
}

function iro_seo_get_og_type()
{
    if (is_singular('post') || is_singular()) {
        return is_singular('post') ? 'article' : 'website';
    }
    if (is_author()) {
        return 'profile';
    }
    return 'website';
}

function iro_seo_get_locale()
{
    $locale = get_locale();
    return str_replace('_', '-', $locale);
}

function iro_get_open_graph_tags($include_og = true, $include_twitter = true)
{
    $title = wp_get_document_title();
    $description = iro_seo_get_description_text();
    $url = iro_seo_get_canonical_url();
    if (empty($url)) {
        $url = home_url(add_query_arg([]));
    }
    $type = iro_seo_get_og_type();
    $image = iro_seo_get_image_url();
    $site_name = get_bloginfo('name');
    $locale = iro_seo_get_locale();

    $out = '';

    if ($include_og) {
        $out .= '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        if ($description) {
            $out .= '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        }
        $out .= '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        $out .= '<meta property="og:type" content="' . esc_attr($type) . '">' . "\n";
        $out .= '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
        $out .= '<meta property="og:locale" content="' . esc_attr($locale) . '">' . "\n";
        if ($image) {
            $out .= '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        }
    }

    if ($include_twitter) {
        $out .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $out .= '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        if ($description) {
            $out .= '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        }
        if ($image) {
            $out .= '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        }
    }

    return $out;
}

function iro_get_canonical_tag()
{
    $url = iro_seo_get_canonical_url();
    if (empty($url)) {
        return '';
    }
    return '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
}

/**
 * Build JSON-LD graph for current page.
 *
 * @return array<int, array<string, mixed>>
 */
function iro_seo_get_json_ld_graph()
{
    $graph = [];

    $website = [
        '@type' => 'WebSite',
        '@id'   => home_url('/#website'),
        'url'   => home_url('/'),
        'name'  => get_bloginfo('name'),
        'description' => get_bloginfo('description'),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => home_url('/?s={search_term_string}'),
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
    $graph[] = $website;

    if (is_singular('post')) {
        global $post;
        $image = iro_seo_get_image_url();
        $article = [
            '@type' => 'BlogPosting',
            '@id' => get_permalink() . '#article',
            'headline' => get_the_title(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author(),
                'url' => get_author_posts_url(get_the_author_meta('ID')),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => get_permalink(),
            ],
        ];
        if ($image) {
            $article['image'] = [$image];
        }
        $desc = iro_seo_get_description_text();
        if ($desc) {
            $article['description'] = $desc;
        }
        $graph[] = $article;
    }

    $items = iro_get_breadcrumb_items();
    if (count($items) >= 2) {
        $list_elements = [];
        $position = 1;
        foreach ($items as $item) {
            $element = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $item['name'],
            ];
            if (!empty($item['url'])) {
                $element['item'] = $item['url'];
            }
            $list_elements[] = $element;
            $position++;
        }
        $graph[] = [
            '@type' => 'BreadcrumbList',
            '@id' => (iro_seo_get_canonical_url() ?: home_url('/')) . '#breadcrumb',
            'itemListElement' => $list_elements,
        ];
    }

    return $graph;
}

function iro_get_json_ld_script()
{
    $graph = iro_seo_get_json_ld_graph();
    if (empty($graph)) {
        return '';
    }
    $data = [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];
    return '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}

/**
 * 404 document title.
 */
function iro_seo_404_document_title($title)
{
    if (is_404()) {
        return '404 - ' . get_bloginfo('name');
    }
    return $title;
}
add_filter('pre_get_document_title', 'iro_seo_404_document_title');

/**
 * Register wp_head SEO output.
 */
function iro_seo_init()
{
    if (iro_opt('iro_seo', 'auto') === 'off') {
        return;
    }

    $mode = iro_opt('iro_seo', 'auto');

    if ($mode === 'auto') {
        add_action('wp_head', function () {
            ob_start();
        }, 0);

        add_action('wp_head', function () {
            $head_content = ob_get_clean();

            $has_description = preg_match('/<meta\s+name=["\']description["\']/i', $head_content);
            $has_keywords = preg_match('/<meta\s+name=["\']keywords["\']/i', $head_content);
            $has_og = preg_match('/<meta\s+property=["\']og:/i', $head_content);
            $has_twitter = preg_match('/<meta\s+name=["\']twitter:/i', $head_content);
            $has_canonical = preg_match('/<link\s+rel=["\']canonical["\']/i', $head_content);
            $has_json_ld = preg_match('/<script[^>]+type=["\']application\/ld\+json["\']/i', $head_content);

            echo $head_content;

            if (!$has_description) {
                echo iro_get_description();
            }
            if (!$has_keywords) {
                echo iro_get_keywords();
            }
            if (!$has_og || !$has_twitter) {
                echo iro_get_open_graph_tags(!$has_og, !$has_twitter);
            }
            if (!$has_canonical) {
                echo iro_get_canonical_tag();
            }
            if (!$has_json_ld) {
                echo iro_get_json_ld_script();
            }
        }, 99);
    } else {
        add_action('wp_head', function () {
            echo iro_get_description();
            echo iro_get_keywords();
            echo iro_get_open_graph_tags();
            echo iro_get_canonical_tag();
            echo iro_get_json_ld_script();
        }, 99);
    }
}
add_action('after_setup_theme', 'iro_seo_init');

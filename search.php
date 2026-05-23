<?php

/**
 * The template for displaying search results pages.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package Sakurairo
 */

get_header();
iro_the_breadcrumbs();
?>
<section id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
    
    <?php
    $paged = max(1, get_query_var('paged'));
    $search_query = get_search_query();
    $sticky_posts = array_map('intval', (array) get_option('sticky_posts'));
    $show_pages_filter = true;
    $posts_per_page = max(1, (int) get_option('posts_per_page', 10));

    $default_checked = array('post');
    if (iro_opt('search_for_shuoshuo')) {
        $default_checked[] = 'shuoshuo';
    }
    if (iro_opt('search_for_pages')) {
        if (iro_opt('only_admin_can_search_pages')) {
            if (current_user_can('manage_options')) {
                $default_checked[] = 'page';
            } else {
                $show_pages_filter = false;
            }
        } else {
            $default_checked[] = 'page';
        }
    } else {
        $show_pages_filter = false;
    }

    $content_types = isset($_GET['content_type'])
        ? explode(',', sanitize_text_field(wp_unslash($_GET['content_type'])))
        : $default_checked;
    $exclude_ids = array_filter(array_map('intval', explode(',', (string) iro_opt('custom_exclude_search_results'))));

    if (!iro_opt('patternimg') || !get_random_bg_url()) : ?>
        <header class="page-header">
            <h1 class="page-title"><?php printf(esc_html__('Search result: %s', 'sakurairo'), '<span>' . esc_html($search_query) . '</span>'); ?></h1>
        </header>
    <?php endif; ?>

    <?php
    $base_args = array(
        'post_type' => $content_types,
        'post_status' => 'publish',
        's' => $search_query,
        'orderby' => 'relevance',
        'order' => 'DESC',
    );

    if (iro_opt('only_admin_can_search_pages') && !current_user_can('manage_options')) {
        $base_args['post_type'] = array_values(array_diff($content_types, array('page')));
    }

    if (!empty($exclude_ids)) {
        $base_args['post__not_in'] = $exclude_ids;
    }

    $count_query = new WP_Query(array_merge($base_args, array(
        'posts_per_page' => 1,
        'fields' => 'ids',
    )));
    $total_results = (int) $count_query->found_posts;
    wp_reset_postdata();

    if (iro_opt('search_filter')) : ?>
        <div class="filter-container" id="filter-container">
            <div class="filter-count">
                <?php echo esc_html($total_results); ?> <?php echo esc_html__('results found', 'sakurairo'); ?>
            </div>

            <form id="search-filter-form" action="" method="GET">
                <?php if ($search_query) : ?>
                    <input type="hidden" name="s" value="<?php echo esc_attr($search_query); ?>">
                <?php endif; ?>

                <label>
                    <input type="checkbox" name="content_type[]" value="post" onchange="applyFilter()" <?php echo in_array('post', $content_types, true) ? 'checked' : ''; ?>> <?php echo esc_html__('Post', 'sakurairo'); ?>
                </label>

                <?php if (iro_opt('search_for_shuoshuo')) : ?>
                    <label>
                        <input type="checkbox" name="content_type[]" value="shuoshuo" onchange="applyFilter()" <?php echo in_array('shuoshuo', $content_types, true) ? 'checked' : ''; ?>> <?php echo esc_html__('shuoshuo', 'sakurairo'); ?>
                    </label>
                <?php endif; ?>

                <?php if ($show_pages_filter) : ?>
                    <label>
                        <input type="checkbox" name="content_type[]" value="page" onchange="applyFilter()" <?php echo in_array('page', $content_types, true) ? 'checked' : ''; ?>> <?php echo esc_html__('Page', 'sakurairo'); ?>
                    </label>
                <?php endif; ?>
            </form>

            <div id="filter-toggle" title="<?php echo esc_attr__('If no option is selected, all results are retrieved by default', 'sakurairo'); ?>" onclick="applyFilter()">
            <a href="./" id="the_filter" style="color: white;"><i class="fas fa-filter"></i></a> <?php echo esc_html__('Click to filter', 'sakurairo'); ?>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function applyFilter() {
        var filterForm = document.getElementById('search-filter-form');
        var checkboxes = filterForm.querySelectorAll('input[name="content_type[]"]');
        var selected = [];
        checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) selected.push(checkbox.value);
        });

        var searchParams = new URLSearchParams(window.location.search);
        searchParams.set('content_type', selected.join(','));
        var newUrl = window.location.pathname + '?' + searchParams.toString();

        var the_filter = document.getElementById('the_filter');
        the_filter.href = newUrl;
        the_filter.click();
    }
    </script>

    <?php
    $display_posts = array();
    $use_sticky = iro_opt('sticky_pinned_content') && !empty($sticky_posts);

    if ($use_sticky && $paged === 1) {
        $sticky_args = array_merge($base_args, array(
            'post__in' => $sticky_posts,
            'posts_per_page' => count($sticky_posts),
            'orderby' => 'post__in',
        ));
        if (!empty($exclude_ids)) {
            $sticky_args['post__not_in'] = $exclude_ids;
        }
        $sticky_query = new WP_Query($sticky_args);
        $sticky_results = $sticky_query->posts;
        $sticky_count = count($sticky_results);
        $non_sticky_needed = max(0, $posts_per_page - $sticky_count);

        if ($non_sticky_needed > 0) {
            $non_sticky_args = array_merge($base_args, array(
                'post__not_in' => array_merge($exclude_ids, $sticky_posts),
                'posts_per_page' => $non_sticky_needed,
                'paged' => 1,
            ));
            $non_sticky_query = new WP_Query($non_sticky_args);
            $display_posts = array_merge($sticky_results, $non_sticky_query->posts);
            wp_reset_postdata();
        } else {
            $display_posts = array_slice($sticky_results, 0, $posts_per_page);
        }
        wp_reset_postdata();
    } elseif ($use_sticky && $paged > 1) {
        $sticky_args = array_merge($base_args, array(
            'post__in' => $sticky_posts,
            'posts_per_page' => count($sticky_posts),
            'fields' => 'ids',
        ));
        $sticky_query = new WP_Query($sticky_args);
        $sticky_count = min((int) $sticky_query->found_posts, $posts_per_page);
        wp_reset_postdata();

        $non_sticky_page1 = max(0, $posts_per_page - $sticky_count);
        $offset = $non_sticky_page1 + ($paged - 2) * $posts_per_page;
        if ($offset < 0) {
            $offset = 0;
        }

        $page_args = array_merge($base_args, array(
            'post__not_in' => array_merge($exclude_ids, $sticky_posts),
            'posts_per_page' => $posts_per_page,
            'offset' => $offset,
        ));
        $page_query = new WP_Query($page_args);
        $display_posts = $page_query->posts;
        wp_reset_postdata();
    } else {
        $page_args = array_merge($base_args, array(
            'posts_per_page' => $posts_per_page,
            'paged' => $paged,
        ));
        $page_query = new WP_Query($page_args);
        $display_posts = $page_query->posts;
        wp_reset_postdata();
    }

    $total_pages = (int) ceil($total_results / $posts_per_page);

    if (!empty($display_posts)) :
        sakura_prime_post_caches($display_posts);
        foreach ($display_posts as $post) :
            setup_postdata($post);
            get_template_part('tpl/content', 'thumbcard');
        endforeach;

        the_posts_pagination(array(
            'total' => $total_pages,
            'current' => $paged,
            'format' => '?paged=%#%',
        ));
    else :
        ?>
        <div class="search-box" style="margin-top: 15px;">
            <form class="s-search" method="get" action="<?php echo esc_url(home_url('/')); ?>" role="search">
                <label class="screen-reader-text" for="search-empty-input"><?php esc_html_e('Search', 'sakurairo'); ?></label>
                <input id="search-empty-input" class="text-input" type="search" name="s" placeholder="<?php esc_attr_e('Search...', 'sakurairo'); ?>" required>
                <button class="search-submit" type="submit" aria-label="<?php esc_attr_e('Submit Search', 'sakurairo'); ?>">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </button>
            </form>
        </div>
        <?php get_template_part('tpl/content', 'none'); ?>
    <?php
    endif;
    wp_reset_postdata();
    ?>

		<style>
			.nav-previous,
			.nav-next {
				padding: 20px 0;
				text-align: center;
				margin: 40px 0 80px;
				display: inline-block;
				font-family: 'Fira Code', 'Noto Sans SC';
			}

			.nav-previous a,
			.nav-next a {
				padding: 13px 35px;
				border: 1px solid #D6D6D6;
				border-radius: 50px;
				color: #ADADAD;
				text-decoration: none;
			}

			.nav-previous span,
			.nav-next span {
				color: #989898;
				font-size: 15px;
			}

			.nav-previous a:hover,
			.nav-next a:hover {
				border: 1px solid #A0DAD0;
				color: #A0DAD0;
			}
		</style>
	</main>
</section>

<?php get_footer(); ?>

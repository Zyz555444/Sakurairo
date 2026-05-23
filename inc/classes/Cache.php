<?php

namespace Sakura\API;

class Cache
{
    const SEARCH_INDEX_OPTION = 'iro_search_index';

    private static function index_regex()
    {
        return <<<EOS
/<\/?[a-zA-Z]+("[^"]*"|'[^']*'|[^'">])*>|begin[\S\s]*\/begin|hermit[\S\s]*\/hermit|img[\S\s]*\/img|{{.*?}}|:.*?:/m
EOS;
    }

    private static function index_vowels()
    {
        return array("[", "{", "]", "}", "<", ">", "\r\n", "\r", "\n", "-", "'", '"', '`', " ", ":", ";", '\\', "  ", "toc");
    }

    private static function sanitize_index_text($text)
    {
        return str_replace(self::index_vowels(), ' ', preg_replace(self::index_regex(), ' ', $text));
    }

    public static function get_search_index()
    {
        $index = get_option(self::SEARCH_INDEX_OPTION, null);
        if (!is_array($index) || !isset($index['posts']) || !is_array($index['posts'])) {
            return null;
        }
        return $index;
    }

    public static function index_to_output($index)
    {
        $output = array_values($index['posts']);
        if (!empty($index['comments']) && is_array($index['comments'])) {
            $output = array_merge($output, array_values($index['comments']));
        }
        return $output;
    }

    public static function save_search_index($index)
    {
        update_option(self::SEARCH_INDEX_OPTION, $index, false);
        delete_transient('cache_search');
    }

    public static function build_post_index_entry($post)
    {
        global $more;
        $more = 1;
        setup_postdata($post);
        $entry = array(
            'type' => $post->post_type,
            'link' => get_permalink($post),
            'title' => get_the_title($post),
            'comments' => get_comments_number($post->ID),
            'text' => ($post->post_type !== 'page')
                ? self::sanitize_index_text(apply_filters('the_content', get_the_content(null, false, $post)))
                : '',
        );
        wp_reset_postdata();
        return $entry;
    }

    public static function build_comment_index_entry($comment)
    {
        $is_private = get_comment_meta($comment->comment_ID, '_private', true);
        $text = $is_private
            ? ($comment->comment_author . ': ' . __('The comment is private', 'sakurairo'))
            : ($comment->comment_author . '：' . $comment->comment_content);
        return array(
            'type' => 'comment',
            'link' => get_comment_link($comment),
            'title' => get_the_title($comment->comment_post_ID),
            'comments' => '',
            'text' => self::sanitize_index_text($text),
        );
    }

    public static function update_post_index($post_id)
    {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            self::remove_post_index($post_id);
            return;
        }
        if (!in_array($post->post_type, array('post', 'shuoshuo', 'page'), true)) {
            return;
        }
        $index = self::get_search_index();
        if ($index === null) {
            $index = array('posts' => array(), 'comments' => array());
        }
        $index['posts'][$post_id] = self::build_post_index_entry($post);
        self::save_search_index($index);
    }

    public static function remove_post_index($post_id)
    {
        $index = self::get_search_index();
        if ($index === null || !isset($index['posts'][$post_id])) {
            return;
        }
        unset($index['posts'][$post_id]);
        self::save_search_index($index);
    }

    public static function update_comment_index($comment_id)
    {
        if (!iro_opt('live_search_comment')) {
            return;
        }
        $comment = get_comment($comment_id);
        if (!$comment || $comment->comment_approved !== '1') {
            self::remove_comment_index($comment_id);
            return;
        }
        $index = self::get_search_index();
        if ($index === null) {
            $index = array('posts' => array(), 'comments' => array());
        }
        if (!isset($index['comments']) || !is_array($index['comments'])) {
            $index['comments'] = array();
        }
        $index['comments'][$comment_id] = self::build_comment_index_entry($comment);
        self::save_search_index($index);
    }

    public static function remove_comment_index($comment_id)
    {
        $index = self::get_search_index();
        if ($index === null || empty($index['comments'][$comment_id])) {
            return;
        }
        unset($index['comments'][$comment_id]);
        self::save_search_index($index);
    }

    public static function rebuild_search_index()
    {
        global $more;
        $more = 1;
        $index = array('posts' => array(), 'comments' => array());
        $publish = array('post', 'shuoshuo', 'page');
        $posts = get_posts(array(
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'post_type' => $publish,
        ));

        foreach ($posts as $post) {
            $index['posts'][$post->ID] = self::build_post_index_entry($post);
        }

        if (iro_opt('live_search_comment')) {
            $comments = get_comments(array(
                'status' => 'approve',
                'number' => 0,
            ));
            foreach ($comments as $comment) {
                $index['comments'][$comment->comment_ID] = self::build_comment_index_entry($comment);
            }
        }

        self::save_search_index($index);
        return self::index_to_output($index);
    }

    public static function search_json()
    {
        $index = self::get_search_index();
        if ($index !== null) {
            return self::index_to_output($index);
        }
        return self::rebuild_search_index();
    }

    public static function schedule_rebuild_search_index()
    {
        if (!wp_next_scheduled('iro_rebuild_search_index')) {
            wp_schedule_single_event(time() + 5, 'iro_rebuild_search_index');
        }
    }
}

<?php
/**
 * Plugin Name: CitizenReporter-REST
 * Description: A very simple plugin for adding REST API endpoints
 * Author: Phillip Ahereza
 * Author URI: http://codeforafrica.org
 * Version: 1.0.0
 */


add_action('rest_api_init', function () {
    register_rest_route('crrest/v1', '/assignments', array(
        'methods' => 'GET',
        'callback' => 'get_current_assignments',
    ));

    register_rest_route('crrest/v1', 'bot/assignments', array(
        'methods' => 'GET',
        'callback' => 'get_current_assignments_bot',
    ));

    register_rest_route('crrest/v1', 'user/posts/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'get_recent_user_posts',
    ));

    register_rest_route('crrest/v1', 'user', array(
        'methods' => 'GET',
        'callback' => 'get_user_info',
    ));
});


function get_current_assignments()
{
    $query = array('post_type' => "assignment");

    $posts_list = wp_get_recent_posts($query);

    if (!$posts_list)
        return array();

    $struct = array();

    foreach ($posts_list as $entry) {
        if ($entry["post_status"] == "publish") {
            $author = get_userdata($entry['post_author']);
            $title = $entry["post_title"];
            $content = $entry["post_content"];
            $custom_fields = get_post_custom($entry['ID']);

            $entry_index = count($struct) - 1;
            //get assignment thumbnail
            $thumb = wp_get_attachment_image_src(get_post_thumbnail_id($entry['ID']), 'full');
            $url = $thumb['0'];

            $address = get_post_meta($entry->ID, 'assignment_address', true);

            $args = array(
                'post_type' => 'post',
                'meta_key' => 'assignment_id',
                'meta_value' => $entry->ID
            );
            $responses = get_posts($args);
            $responses = sizeof($responses);
            $end_date = get_post_meta($entry->ID, 'assignment_date', true);

            $struct[] = array(
                "assignment_id" => $entry->ID,
                "title" => $title,
                "content" => $content,
                "thumbnail" => $url,
                "author_name" => $author->display_name,
                "author_id" => $author->ID,
                "address" => $address,
                "responses" => $responses,
                "deadline" => $end_date,
                "post_status" => $entry["post_status"]
            );
        }

    }

    $recent_posts = array();
    for ($j = 0; $j < count($struct); $j++) {
        array_push($recent_posts, $struct[$j]);
    }

    return array("assignments" => $recent_posts);
}

function get_recent_user_posts($data)
{
    $posts_list = wp_get_recent_posts(array(
        'author' => $data['id'],
        'post_status' => 'any',
        'post_type' => 'post'
    ));

    if (!$posts_list)
        return array();

    foreach ($posts_list as $entry) {
        if (!current_user_can('edit_post', $entry['ID']))
            continue;


        $categories = array();
        $catids = wp_get_post_categories($entry['ID']);
        foreach ($catids as $catid)
            $categories[] = get_cat_name($catid);

        $tagnames = array();
        $tags = wp_get_post_tags($entry['ID']);
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $tagnames[] = $tag->name;
            }
            $tagnames = implode(', ', $tagnames);
        } else {
            $tagnames = '';
        }

        $post = get_extended($entry['post_content']);
        $link = post_permalink($entry['ID']);

        // Get the post author info.
        $author = get_userdata($entry['post_author']);

        $allow_comments = ('open' == $entry['comment_status']) ? 1 : 0;
        $allow_pings = ('open' == $entry['ping_status']) ? 1 : 0;

        // Consider future posts as published
        if ($entry['post_status'] === 'future')
            $entry['post_status'] = 'publish';

        // Get post format
        $post_format = get_post_format($entry['ID']);
        if (empty($post_format))
            $post_format = 'standard';

        $user_ID = $data['id'];

        if ($user_ID == $entry['post_author']) {

            $recent_posts[] = array(
                'userid' => $entry['post_author'],
                'postid' => (string)$entry['ID'],
                'description' => $post['main'],
                'title' => $entry['post_title'],
                'link' => $link,
                'permaLink' => $link,
                // commented out because no other tool seems to use this
                // 'content' => $entry['post_content'],
                'categories' => $categories,
                'mt_excerpt' => $entry['post_excerpt'],
                'mt_text_more' => $post['extended'],
                'wp_more_text' => $post['more_text'],
                'mt_allow_comments' => $allow_comments,
                'mt_allow_pings' => $allow_pings,
                'mt_keywords' => $tagnames,
                'custom_fields' => get_post_meta($entry['ID']),
                'wp_slug' => $entry['post_name'],
                'wp_password' => $entry['post_password'],
                'wp_author_id' => (string)$author->ID,
                'wp_author_display_name' => $author->display_name,
                'post_status' => $entry['post_status'],
                'wp_post_format' => $post_format,
                'sticky' => ($entry['post_type'] === 'post' && is_sticky($entry['ID'])),
                'created' => $entry['post_date']
            );
        }
    }

    return $recent_posts;


}

function get_user_info($data)
{
    $username = $data['username'];

    $user = get_user_by("email", $username);
    $user_id = $user->ID;
    $p = array();
    $p['avatar'] = get_avatar_url($user_id);
    $p['user_id'] = $user_id;
    $p['username'] = get_userdata($user_id)->user_login;
    $p['password'] = get_user_meta($user_id, 'password', TRUE);
    $p['email'] = get_userdata($user_id)->user_email;;
    $p['first_name'] = get_user_meta($user_id, 'first_name', TRUE);
    $p['last_name'] = get_user_meta($user_id, 'last_name', TRUE);
    $p['phone_number'] = get_user_meta($user_id, 'phone_number', TRUE);
    $p['location'] = get_user_meta($user_id, 'location', TRUE);
    $p['address'] = get_user_meta($user_id, 'address', TRUE);

    return array("user" => $p);


}

function get_current_assignments_bot()
{
    $query = array('post_type' => "assignment");

    $posts_list = wp_get_recent_posts($query);

    if (!$posts_list)
        return array();

    $struct = array();

    foreach ($posts_list as $entry) {
        if ($entry["post_status"] == "publish") {
            $assignment_id = $entry->ID;
            $title = $entry["post_title"];
            $content = $entry["post_content"];

            //get assignment thumbnail
            $thumb = wp_get_attachment_image_src(get_post_thumbnail_id($entry['ID']), 'full');
            $url = $thumb['0'];


            $args = array(
                'post_type' => 'post',
                'meta_key' => 'assignment_id',
                'meta_value' => $entry->ID
            );
            $responses = get_posts($args);
            $responses = sizeof($responses);
            $end_date = get_post_meta($entry->ID, 'assignment_date', true);

            $struct[] = array(
                "assignment_id" => $entry->ID,
                "title" => $title,
                "subtitle" => $content,
                "image_url" => $url,
                "buttons" => [array(
                    "set_attributes" => array("assignment_id" => $assignment_id),
                    "type" => "show_block",
                    "block_names" => ["init_report",],
                    "title" => "Start Reporting",
                ),
                array("url" => "citizenreporter.codeforafrica.net",
                "type" => "json_plugin_url",
                "title" => "Get More Detail")
                ]

            );
        }
    }

    $data = array(
        "messages"=> [array(
            "attachment"=>array(
                "type"=>"template",
                "payload"=>array(
                    "template_type"=> "generic",
                    "elements"=>$struct
                )
            )
        )]
    );
    return($data);

}
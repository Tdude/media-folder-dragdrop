<?php
/**
 * Media Folder Drag & Drop Debug
 * All debug output and admin notices for the plugin are contained here.
 * Remove or rename this file to disable debugging.
 */

if (!defined('ABSPATH')) exit;

add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['debug_manual_query'])) return;
    $taxonomy = 'media_folder';
    $folder_id = intval($_GET['debug_manual_query']);
    $attachments = get_posts([
        'post_type' => 'attachment',
        'tax_query' => [[
            'taxonomy' => $taxonomy,
            'field' => 'term_id',
            'terms' => [$folder_id],
        ]],
        'posts_per_page' => -1,
    ]);
    echo '<div class="notice notice-info"><pre>';
    if (empty($attachments)) {
        echo 'No attachments found for folder ' . $folder_id . "\n";
    } else {
        foreach ($attachments as $att) {
            echo "ID: {$att->ID}, Title: {$att->post_title}, Status: {$att->post_status}\n";
        }
    }
    echo '</pre></div>';
});

add_action('admin_init', function() {
    if (isset($_GET['debug_update_counts'])) {
        wp_update_term_count_now([26, 27, 28], 'media_folder');
        echo '<pre>Updated term counts.</pre>';
        exit;
    }
});

// Debug: Programmatically reassign all attachments to their folders (fixes taxonomy relationships)
add_action('admin_init', function() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['debug_reassign_folders'])) return;
    $taxonomy = 'media_folder';
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    foreach ($terms as $term) {
        // Get all attachments currently assigned to this folder (by term_id)
        $attachments = get_posts([
            'post_type' => 'attachment',
            'tax_query' => [[
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => [$term->term_id],
            ]],
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        foreach ($attachments as $id) {
            // This will re-save the relationship in the DB
            wp_set_object_terms($id, [$term->term_id], $taxonomy, false);
        }
    }
    echo '<pre>Reassigned all attachments to their folders.</pre>';
    exit;
});

// Debug: List all taxonomies for attachments
add_action('admin_init', function() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['debug_attachment_taxonomies'])) return;
    $taxes = get_object_taxonomies('attachment');
    echo '<pre>Attachment taxonomies: ' . print_r($taxes, true) . '</pre>';
    exit;
});

// Debug: For a given attachment ID, print its media_folder terms
add_action('admin_init', function() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['debug_media_folder_id'])) return;
    $id = intval($_GET['debug_media_folder_id']);
    $terms = get_the_terms($id, 'media_folder');
    echo '<pre>Attachment ID: ' . $id . "\n";
    if (is_wp_error($terms)) {
        echo 'Error: ' . $terms->get_error_message();
    } elseif (!$terms) {
        echo 'No media_folder terms.';
    } else {
        foreach ($terms as $term) {
            echo 'Term: ' . $term->term_id . ' - ' . $term->name . "\n";
        }
    }
    exit;
});

// Debug: log the SQL query for the main media library query when filtering by folder
add_filter('query', function($sql) {
    if (isset($_GET['media_folder'])) {
        error_log('MEDIA FOLDER SQL: ' . $sql);
    }
    return $sql;
});

add_action('pre_get_posts', function($query) {
    global $pagenow;
    if (is_admin() && $pagenow === 'upload.php' && $query->is_main_query()) {
        error_log('TAX_QUERY: ' . print_r($query->get('tax_query'), true));
        add_filter('posts_results', function($posts, $query) {
            error_log('FILTERED POSTS: ' . print_r(wp_list_pluck($posts, 'ID'), true));
            return $posts;
        }, 10, 2);
    }
});
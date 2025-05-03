<?php
/**
 * Plugin Name: Media Folder Drag & Drop
 * Description: Categorize media files with drag-and-drop folders in the WordPress Media Library.
 * Version: 1.0.0
 * Author: Tibor Berki
 * Author URI: https://github.com/Tdude
 * Plugin URI: https://github.com/Tdude/media-folder-drag-drop
 */

if (!defined('ABSPATH')) exit;

// Register the media_folder taxonomy for attachments at the absolute earliest time
add_action('init', function() {
    register_taxonomy(
        'media_folder',
        'attachment',
        [
            'label' => __('Media Folders', 'media-folder-dragdrop'),
            'hierarchical' => true,
            'show_admin_column' => false,
            'show_ui' => true, // We'll provide our own UI but WordPress needs this
            'public' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => false,
        ]
    );
}, 0);

// Ensure the media_folder query var is registered for filtering
add_filter('query_vars', function($vars) {
    $vars[] = 'media_folder';
    return $vars;
});

class Media_Folder_DragDrop {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_move_media_to_folder', [$this, 'move_media_to_folder']);
        add_action('wp_ajax_get_media_folders', [$this, 'get_media_folders']);
        add_action('wp_ajax_create_media_folder', [$this, 'create_media_folder']);
        add_action('wp_ajax_get_media_folders_for_media', [$this, 'get_media_folders_for_media']);
    }

    // Enqueue JS/CSS for the admin Media Library and custom Folder Filter view
    public function enqueue_admin_scripts($hook) {
        // Always load on upload.php (default Media Library)
        if ($hook === 'upload.php') {
            wp_enqueue_style('media-folder-dragdrop', plugin_dir_url(__FILE__) . 'media-folder-dragdrop.css');
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_script('media-folder-dragdrop', plugin_dir_url(__FILE__) . 'media-folder-dragdrop.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'], '1.0.0', true);
            wp_localize_script('media-folder-dragdrop', 'MediaFolderDragDrop', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('media_folder_dragdrop'),
            ]);
        }
        // Also load on custom Folder Filter view (submenu page)
        if ($hook === 'media_page_media-folder-filter') {
            wp_enqueue_style('media-folder-dragdrop', plugin_dir_url(__FILE__) . 'media-folder-dragdrop.css');
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_script('media-folder-dragdrop', plugin_dir_url(__FILE__) . 'media-folder-dragdrop.js', ['jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'], '1.0.0', true);
            wp_localize_script('media-folder-dragdrop', 'MediaFolderDragDrop', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('media_folder_dragdrop'),
            ]);
        }
    }

    // AJAX: Move media to folder
    public function move_media_to_folder() {
        check_ajax_referer('media_folder_dragdrop', 'nonce');
        $media_ids = isset($_POST['media_ids']) ? (array) $_POST['media_ids'] : [];
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        foreach ($media_ids as $media_id) {
            wp_set_object_terms($media_id, $folder_id ? [$folder_id] : [], 'media_folder', false);
        }
        wp_send_json_success();
    }

    // AJAX: Get folder tree (with children)
    public function get_media_folders() {
        $folders = $this->get_folder_tree();
        wp_send_json_success($folders);
    }

    // AJAX: Create folder
    public function create_media_folder() {
        check_ajax_referer('media_folder_dragdrop', 'nonce');
        $name = sanitize_text_field($_POST['name']);
        $parent = isset($_POST['parent']) ? intval($_POST['parent']) : 0;
        $term = wp_insert_term($name, 'media_folder', ['parent' => $parent]);
        if (is_wp_error($term)) {
            wp_send_json_error($term->get_error_message());
        } else {
            wp_send_json_success($term);
        }
    }

    // AJAX: Get folder assignments for media IDs
    public function get_media_folders_for_media() {
        $ids = isset($_POST['ids']) ? array_map('intval', (array)$_POST['ids']) : [];
        $out = [];
        foreach ($ids as $id) {
            $terms = get_the_terms($id, 'media_folder');
            $out[$id] = [];
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $out[$id][] = [
                        'term_id' => $term->term_id,
                        'name' => $term->name
                    ];
                }
            }
        }
        wp_send_json_success($out);
    }

    // Helper: Build folder tree recursively
    private function get_folder_tree($parent = 0) {
        $terms = get_terms([
            'taxonomy' => 'media_folder',
            'hide_empty' => false,
            'parent' => $parent
        ]);
        $tree = [];
        foreach ($terms as $term) {
            $children = $this->get_folder_tree($term->term_id);
            $tree[] = [
                'term_id' => $term->term_id,
                'name' => $term->name,
                'children' => $children
            ];
        }
        return $tree;
    }
}

new Media_Folder_DragDrop();

// Custom minimal media folder filtered view
add_action('admin_menu', function() {
    add_submenu_page(
        'upload.php',
        __('Media Folder Filter', 'media-folder-dragdrop'),
        __('Folder Filter', 'media-folder-dragdrop'),
        'upload_files',
        'media-folder-filter',
        function() {
            $taxonomy = 'media_folder';
            $folders = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false
            ]);
            $selected = isset($_GET['media_folder']) ? intval($_GET['media_folder']) : 0;
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $per_page = 20;
            $offset = ($paged - 1) * $per_page;

            echo '<div class="wrap"><h1>' . esc_html__('Media Folder Filter', 'media-folder-dragdrop') . '</h1>';
            echo '<div class="wp-filter" style="margin-bottom:1.5em;">';
            echo '<div class="filter-items">';
            echo '<form method="get" id="media-folder-filter-form" style="display:inline-block;margin:0;">';
            echo '<input type="hidden" name="page" value="media-folder-filter">';
            echo '<label for="media-folder-select" class="screen-reader-text">' . esc_html__('Filter by folder', 'media-folder-dragdrop') . '</label>';
            echo '<select name="media_folder" id="media-folder-select" class="attachment-filters" style="margin-right:10px;max-width:220px;">';
            echo '<option value="">' . esc_html__('All Folders', 'media-folder-dragdrop') . '</option>';
            foreach ($folders as $folder) {
                printf('<option value="%d"%s>%s</option>', $folder->term_id, selected($selected, $folder->term_id, false), esc_html($folder->name));
            }
            echo '</select>';
            echo '<input type="submit" class="button" value="' . esc_attr__('Filter', 'media-folder-dragdrop') . '">';
            echo '</form>';
            echo '</div>';
            echo '</div>';

            // Inline JS for auto-submit on select change
            echo '<script>document.getElementById("media-folder-select").addEventListener("change", function(){document.getElementById("media-folder-filter-form").submit();});</script>';

            // Query attachments
            $args = [
                'post_type' => 'attachment',
                'posts_per_page' => $per_page,
                'offset' => $offset,
            ];
            if ($selected) {
                $args['tax_query'] = [[
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => [$selected],
                ]];
            }
            $attachments = get_posts($args);
            $total = 0;
            if ($selected) {
                $total = (int)wp_count_posts('attachment')->inherit;
                $count_query = new WP_Query([
                    'post_type' => 'attachment',
                    'tax_query' => [[
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => [$selected],
                    ]],
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'no_found_rows' => false
                ]);
                $total = $count_query->found_posts;
            } else {
                $count_query = new WP_Query([
                    'post_type' => 'attachment',
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'no_found_rows' => false
                ]);
                $total = $count_query->found_posts;
            }

            // Bulk actions form
            if ($attachments) {
                echo '<form method="post" id="bulk-action-form">';
                echo '<input type="hidden" name="media_folder" value="' . esc_attr($selected) . '">';
                echo '<table class="widefat fixed striped"><thead><tr>';
                echo '<th style="width:2em"><input type="checkbox" id="bulk-select-all"></th>';
                echo '<th>' . esc_html__('Thumbnail', 'media-folder-dragdrop') . '</th>';
                echo '<th>' . esc_html__('Title', 'media-folder-dragdrop') . '</th>';
                echo '<th>' . esc_html__('ID', 'media-folder-dragdrop') . '</th>';
                echo '<th>' . esc_html__('Status', 'media-folder-dragdrop') . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($attachments as $att) {
                    $thumb = wp_get_attachment_image($att->ID, 'thumbnail', false, ['style' => 'vertical-align:middle;max-width:80px;max-height:80px;']);
                    printf('<tr><td><input type="checkbox" name="attachments[]" value="%d"></td><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
                        $att->ID, $thumb, esc_html($att->post_title), $att->ID, esc_html($att->post_status));
                }
                echo '</tbody></table>';
                // Bulk move/delete actions (UI only, not functional yet)
                echo '<div style="margin:1em 0">';
                echo '<select name="bulk_action"><option value="">' . esc_html__('Bulk Actions', 'media-folder-dragdrop') . '</option>';
                echo '<option value="move">' . esc_html__('Move to Folder...', 'media-folder-dragdrop') . '</option>';
                echo '<option value="delete">' . esc_html__('Delete', 'media-folder-dragdrop') . '</option>';
                echo '</select> <button type="submit" class="button">' . esc_html__('Apply', 'media-folder-dragdrop') . '</button>';
                echo '</div>';
                echo '</form>';
                // Bulk select JS
                echo '<script>document.getElementById("bulk-select-all").addEventListener("change",function(e){var cbs=document.querySelectorAll("#bulk-action-form input[type=checkbox][name^=attachments]");for(var i=0;i<cbs.length;i++){cbs[i].checked=e.target.checked;}});</script>';
            } else {
                echo '<p>' . esc_html__('No attachments found.', 'media-folder-dragdrop') . '</p>';
            }

            // Pagination
            $total_pages = ceil($total / $per_page);
            if ($total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                $base_url = remove_query_arg('paged');
                for ($i = 1; $i <= $total_pages; $i++) {
                    $url = add_query_arg(['paged' => $i], $base_url);
                    if ($i == $paged) {
                        echo '<span class="current">' . $i . '</span> ';
                    } else {
                        echo '<a class="page-numbers" href="' . esc_url($url) . '">' . $i . '</a> ';
                    }
                }
                echo '</div></div>';
            }
            echo '</div>';
        }
    );
});

// Add "Add New Folder" submenu under Media
add_action('admin_menu', function() {
    add_submenu_page(
        'upload.php',
        __('Add New Folder', 'media-folder-dragdrop'),
        __('Add New Folder', 'media-folder-dragdrop'),
        'upload_files',
        'media-folder-add',
        function() {
            $taxonomy = 'media_folder';
            echo '<div class="wrap"><h1>' . esc_html__('Add New Folder', 'media-folder-dragdrop') . '</h1>';
            if (!empty($_POST['new_folder_name']) && current_user_can('manage_categories')) {
                $name = sanitize_text_field($_POST['new_folder_name']);
                $parent = isset($_POST['parent_folder']) ? intval($_POST['parent_folder']) : 0;
                $result = wp_insert_term($name, $taxonomy, ['parent' => $parent]);
                if (is_wp_error($result)) {
                    echo '<div class="error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="updated"><p>' . esc_html__('Folder created!', 'media-folder-dragdrop') . '</p></div>';
                }
            }
            echo '<form method="post">';
            echo '<label for="new_folder_name"><strong>' . esc_html__('Folder Name', 'media-folder-dragdrop') . '</strong></label><br>';
            echo '<input type="text" name="new_folder_name" id="new_folder_name" style="width:300px;max-width:100%" required> ';
            // Parent dropdown
            $folders = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
            echo '<br><label for="parent_folder">' . esc_html__('Parent Folder (optional)', 'media-folder-dragdrop') . '</label><br>';
            echo '<select name="parent_folder" id="parent_folder"><option value="">' . esc_html__('None', 'media-folder-dragdrop') . '</option>';
            foreach ($folders as $folder) {
                printf('<option value="%d">%s</option>', $folder->term_id, esc_html($folder->name));
            }
            echo '</select><br><br>';
            echo '<button class="button button-primary" type="submit">' . esc_html__('Add Folder', 'media-folder-dragdrop') . '</button>';
            echo '</form>';
            echo '<p style="margin-top:2em"><a href="' . esc_url(admin_url('edit-tags.php?taxonomy=media_folder&post_type=attachment')) . '">' . esc_html__('Manage All Folders', 'media-folder-dragdrop') . '</a></p>';
            echo '</div>';
        }
    );
}, 100);

// Debug logic is now in media-folder-dragdrop-debug.php (if present)
if (is_admin() && file_exists(__DIR__ . '/media-folder-dragdrop-debug.php')) {
    include_once __DIR__ . '/media-folder-dragdrop-debug.php';
}

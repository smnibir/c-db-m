<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * AJAX handlers for lazy loading dashboard content
 */

// Load tasks tab content
add_action('wp_ajax_load_tasks_tab', 'ajax_load_tasks_tab');
function ajax_load_tasks_tab() {
    check_ajax_referer('clickup_lazy_load_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $api_key = get_option('clickup_api_key');
    $folder_id = get_field('clickup_folder', 'user_' . $user_id);
    
    if (!$api_key || !$folder_id) {
        wp_send_json_error(['message' => 'Missing ClickUp API Key or Folder ID']);
    }
    
    // Use caching for task data
    $cache_key = ClickUp_Cache_Manager::get_user_cache_key('tasks', $user_id);
    
    $tasks = ClickUp_Cache_Manager::get_or_fetch(
        $cache_key,
        function() use ($api_key, $folder_id) {
            return get_clickup_tasks_from_folder($api_key, $folder_id);
        },
        ClickUp_Cache_Manager::CACHE_DURATION_SHORT // 5 minutes for tasks
    );
    
    if (is_wp_error($tasks)) {
        wp_send_json_error(['message' => 'Error fetching tasks']);
    }
    
    // Start output buffering
    ob_start();
    
    // Set variables needed by the template
    $tasks = $tasks;
    
    // Include the task list template
    include plugin_dir_path(__DIR__) . 'templates/task-list.php';
    
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}

// Load meeting notes tab content
add_action('wp_ajax_load_meeting_notes_tab', 'ajax_load_meeting_notes_tab');
function ajax_load_meeting_notes_tab() {
    check_ajax_referer('clickup_lazy_load_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $doc_id = get_field('client_portal', 'user_' . $user_id);
    $workspace_id = get_option('clickup_workspace_id');
    $api_key = get_option('clickup_api_key');
    
    if (!$doc_id || !$workspace_id || !$api_key) {
        wp_send_json_error(['message' => 'Missing ClickUp configuration']);
    }
    
    // Use caching for meeting notes
    $cache_key = ClickUp_Cache_Manager::get_user_cache_key('meeting_notes', $user_id);
    
    $pages = ClickUp_Cache_Manager::get_or_fetch(
        $cache_key,
        function() use ($workspace_id, $doc_id, $api_key) {
            $response = wp_remote_get("https://api.clickup.com/api/v3/workspaces/{$workspace_id}/docs/{$doc_id}/pages", [
                'headers' => ['Authorization' => $api_key],
                'timeout' => 20,
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return json_decode(wp_remote_retrieve_body($response), true);
        },
        ClickUp_Cache_Manager::CACHE_DURATION_MEDIUM // 30 minutes
    );
    
    if (is_wp_error($pages)) {
        wp_send_json_error(['message' => 'Error fetching meeting notes']);
    }
    
    // Find Meeting Notes page
    $meeting_notes_page = null;
    $pages_array = isset($pages['pages']) ? $pages['pages'] : $pages;
    
    foreach ($pages_array as $page) {
        if (isset($page['name']) && $page['name'] === 'Meeting Notes') {
            $meeting_notes_page = $page;
            break;
        }
    }
    
    ob_start();
    $content = $meeting_notes_page ? $meeting_notes_page['content'] : '';
    
    // Load Parsedown if available
    if (!class_exists('Parsedown')) {
        require_once plugin_dir_path(__DIR__) . 'includes/parsedown.php';
    }
    $Parsedown = class_exists('Parsedown') ? new Parsedown() : null;
    
    include plugin_dir_path(__DIR__) . 'templates/meeting-notes.php';
    
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}

// Load performance summary tab content
add_action('wp_ajax_load_performance_tab', 'ajax_load_performance_tab');
function ajax_load_performance_tab() {
    check_ajax_referer('clickup_lazy_load_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $doc_id = get_field('client_portal', 'user_' . $user_id);
    $workspace_id = get_option('clickup_workspace_id');
    $api_key = get_option('clickup_api_key');
    
    if (!$doc_id || !$workspace_id || !$api_key) {
        wp_send_json_error(['message' => 'Missing ClickUp configuration']);
    }
    
    // Use caching
    $cache_key = ClickUp_Cache_Manager::get_user_cache_key('performance', $user_id);
    
    $pages = ClickUp_Cache_Manager::get_or_fetch(
        $cache_key,
        function() use ($workspace_id, $doc_id, $api_key) {
            $response = wp_remote_get("https://api.clickup.com/api/v3/workspaces/{$workspace_id}/docs/{$doc_id}/pages", [
                'headers' => ['Authorization' => $api_key],
                'timeout' => 20,
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return json_decode(wp_remote_retrieve_body($response), true);
        },
        ClickUp_Cache_Manager::CACHE_DURATION_MEDIUM // 30 minutes
    );
    
    if (is_wp_error($pages)) {
        wp_send_json_error(['message' => 'Error fetching performance data']);
    }
    
    // Find Performance Summary page
    $performance_page = null;
    $pages_array = isset($pages['pages']) ? $pages['pages'] : $pages;
    
    foreach ($pages_array as $page) {
        if (isset($page['name']) && $page['name'] === 'Performance Summary') {
            $performance_page = $page;
            break;
        }
    }
    
    ob_start();
    $content = $performance_page ? $performance_page['content'] : '';
    
    // Load Parsedown if available
    if (!class_exists('Parsedown')) {
        require_once plugin_dir_path(__DIR__) . 'includes/parsedown.php';
    }
    $Parsedown = class_exists('Parsedown') ? new Parsedown() : null;
    
    include plugin_dir_path(__DIR__) . 'templates/performance-summary.php';
    
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}

// Load campaign strategy tab content
add_action('wp_ajax_load_campaign_tab', 'ajax_load_campaign_tab');
function ajax_load_campaign_tab() {
    check_ajax_referer('clickup_lazy_load_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $doc_id = get_field('client_portal', 'user_' . $user_id);
    $workspace_id = get_option('clickup_workspace_id');
    $api_key = get_option('clickup_api_key');
    
    if (!$doc_id || !$workspace_id || !$api_key) {
        wp_send_json_error(['message' => 'Missing ClickUp configuration']);
    }
    
    // Use caching
    $cache_key = ClickUp_Cache_Manager::get_user_cache_key('campaign', $user_id);
    
    $pages = ClickUp_Cache_Manager::get_or_fetch(
        $cache_key,
        function() use ($workspace_id, $doc_id, $api_key) {
            $response = wp_remote_get("https://api.clickup.com/api/v3/workspaces/{$workspace_id}/docs/{$doc_id}/pages", [
                'headers' => ['Authorization' => $api_key],
                'timeout' => 20,
            ]);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            return json_decode(wp_remote_retrieve_body($response), true);
        },
        ClickUp_Cache_Manager::CACHE_DURATION_LONG // 1 hour
    );
    
    if (is_wp_error($pages)) {
        wp_send_json_error(['message' => 'Error fetching campaign data']);
    }
    
    // Find Campaign Strategy page
    $campaign_page = null;
    $pages_array = isset($pages['pages']) ? $pages['pages'] : $pages;
    
    foreach ($pages_array as $page) {
        if (isset($page['name']) && $page['name'] === 'Campaign Strategy') {
            $campaign_page = $page;
            break;
        }
    }
    
    ob_start();
    $content = $campaign_page ? $campaign_page['content'] : '';
    
    // Load Parsedown if available
    if (!class_exists('Parsedown')) {
        require_once plugin_dir_path(__DIR__) . 'includes/parsedown.php';
    }
    $Parsedown = class_exists('Parsedown') ? new Parsedown() : null;
    
    include plugin_dir_path(__DIR__) . 'templates/campaign-strategy.php';
    
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}

// Helper function to get tasks (used by ajax handler)
function get_clickup_tasks_from_folder($api_key, $folder_id) {
    $all_tasks = [];
    $list_ids = [];

    // Step 1: Get Lists in Folder
    $res_lists = wp_remote_get("https://api.clickup.com/api/v2/folder/{$folder_id}/list", [
        'headers' => ['Authorization' => $api_key],
        'timeout' => 20,
    ]);

    if (is_wp_error($res_lists)) {
        return $res_lists;
    }

    $lists = json_decode(wp_remote_retrieve_body($res_lists), true)['lists'] ?? [];

    foreach ($lists as $list) {
        $list_ids[] = $list['id'];
    }

    // Step 2: Get tasks from each list (with pagination)
    foreach ($list_ids as $list_id) {
        $page = 0;
        do {
            $response = wp_remote_get("https://api.clickup.com/api/v2/list/{$list_id}/task?page={$page}&subtasks=true&include_closed=true", [
                'headers' => ['Authorization' => $api_key],
                'timeout' => 20,
            ]);

            if (is_wp_error($response)) {
                continue; // Skip this list on error
            }

            $tasks = json_decode(wp_remote_retrieve_body($response), true)['tasks'] ?? [];
            $all_tasks = array_merge($all_tasks, $tasks);

            $has_more = count($tasks) === 100;
            $page++;
        } while ($has_more);
    }

    return $all_tasks;
}

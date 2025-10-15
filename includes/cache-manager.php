<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Cache Manager for ClickUp API responses
 * Implements transient-based caching to reduce API calls
 */
class ClickUp_Cache_Manager {
    
    // Cache duration constants (in seconds)
    const CACHE_DURATION_SHORT = 300;      // 5 minutes - for frequently changing data
    const CACHE_DURATION_MEDIUM = 1800;    // 30 minutes - for moderately changing data
    const CACHE_DURATION_LONG = 3600;      // 1 hour - for rarely changing data
    const CACHE_DURATION_VERY_LONG = 86400; // 24 hours - for static data
    
    /**
     * Get cached data or fetch from API
     * 
     * @param string $cache_key Unique cache key
     * @param callable $callback Function to call if cache miss
     * @param int $duration Cache duration in seconds
     * @return mixed Cached or fresh data
     */
    public static function get_or_fetch($cache_key, $callback, $duration = self::CACHE_DURATION_MEDIUM) {
        // Try to get from cache first
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Cache miss - fetch fresh data
        $fresh_data = $callback();
        
        // Only cache if data is valid
        if (!is_wp_error($fresh_data) && $fresh_data !== null) {
            set_transient($cache_key, $fresh_data, $duration);
        }
        
        return $fresh_data;
    }
    
    /**
     * Generate cache key for user-specific data
     * 
     * @param string $base Base key name
     * @param int|null $user_id User ID (defaults to current user)
     * @return string Cache key
     */
    public static function get_user_cache_key($base, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        return "clickup_cache_{$base}_user_{$user_id}";
    }
    
    /**
     * Generate cache key for global data
     * 
     * @param string $base Base key name
     * @param array $params Additional parameters to include in key
     * @return string Cache key
     */
    public static function get_cache_key($base, $params = []) {
        $key = "clickup_cache_{$base}";
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        return $key;
    }
    
    /**
     * Clear specific cache entry
     * 
     * @param string $cache_key Cache key to clear
     * @return bool Success
     */
    public static function clear($cache_key) {
        return delete_transient($cache_key);
    }
    
    /**
     * Clear all ClickUp caches for a specific user
     * 
     * @param int|null $user_id User ID (defaults to current user)
     */
    public static function clear_user_cache($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        
        // Delete all transients matching the user pattern
        $pattern = '_transient_clickup_cache_%_user_' . $user_id;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );
        
        // Also clear timeout transients
        $timeout_pattern = '_transient_timeout_clickup_cache_%_user_' . $user_id;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $timeout_pattern
            )
        );
    }
    
    /**
     * Clear all ClickUp caches
     */
    public static function clear_all_cache() {
        global $wpdb;
        
        // Delete all clickup transients
        $pattern = '_transient_clickup_cache_%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );
        
        // Also clear timeout transients
        $timeout_pattern = '_transient_timeout_clickup_cache_%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $timeout_pattern
            )
        );
    }
    
    /**
     * Make cached API request
     * 
     * @param string $url API URL
     * @param array $args Request arguments
     * @param string $cache_key Cache key
     * @param int $duration Cache duration
     * @return array|WP_Error Response
     */
    public static function cached_api_request($url, $args = [], $cache_key = null, $duration = self::CACHE_DURATION_MEDIUM) {
        // Generate cache key if not provided
        if ($cache_key === null) {
            $cache_key = self::get_cache_key('api_request', ['url' => $url, 'args' => $args]);
        }
        
        return self::get_or_fetch($cache_key, function() use ($url, $args) {
            $response = wp_remote_get($url, $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            return json_decode($body, true);
        }, $duration);
    }
}

// Add admin action to clear cache
add_action('wp_ajax_clear_clickup_cache', function() {
    check_ajax_referer('clickup_cache_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
    
    if ($type === 'user' && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        ClickUp_Cache_Manager::clear_user_cache($user_id);
        wp_send_json_success(['message' => 'User cache cleared successfully']);
    } else {
        ClickUp_Cache_Manager::clear_all_cache();
        wp_send_json_success(['message' => 'All cache cleared successfully']);
    }
});

// Add user action to clear their own cache
add_action('wp_ajax_clear_my_clickup_cache', function() {
    check_ajax_referer('clickup_user_cache_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    ClickUp_Cache_Manager::clear_user_cache($user_id);
    
    wp_send_json_success(['message' => 'Your cache has been cleared successfully']);
});

<?php
/**
 * Plugin Name: Client Dashboard
 * Description: Custom Plugin. 
 * Version: 1.0.1
 * Author: S M Nibir
 */

if (!defined('ABSPATH')) exit;

// Lightweight caching helpers (transients with sane defaults)
if (!function_exists('wg_cache_get')) {
    /**
     * Retrieve a cached value by transient key.
     */
    function wg_cache_get(string $key)
    {
        return get_transient($key);
    }
}

if (!function_exists('wg_cache_set')) {
    /**
     * Store a value in the transient cache with a configurable TTL.
     */
    function wg_cache_set(string $key, $value, int $ttlSeconds)
    {
        // Ensure non-negative TTL
        $ttl = max(0, (int) $ttlSeconds);
        set_transient($key, $value, $ttl);
        return $value;
    }
}

if (!function_exists('wg_cache_delete')) {
    /**
     * Delete a cached transient by key.
     */
    function wg_cache_delete(string $key)
    {
        delete_transient($key);
    }
}

if (!function_exists('wg_cache_ttl')) {
    /**
     * Allow filtering TTLs per cache type with sensible defaults.
     * Example filters: 'wg_cache_ttl_clickup_pages', 'wg_cache_ttl_clickup_tasks', 'wg_cache_ttl_whatconverts_leads'
     */
    function wg_cache_ttl(string $type, int $defaultSeconds)
    {
        return (int) apply_filters('wg_cache_ttl_' . $type, $defaultSeconds);
    }
}

if (!function_exists('wg_user_cache_key')) {
    /**
     * Build a per-user cache key with stable hashing of parts.
     */
    function wg_user_cache_key(string $prefix, array $parts = [])
    {
        $userId = get_current_user_id();
        $saltParts = array_map('strval', $parts);
        array_unshift($saltParts, (string) $userId);
        $hash = md5(implode('|', $saltParts));
        return $prefix . '_' . $hash;
    }
}

// Load all modules
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/acf-space-field.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/client-dashboard-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/enqueue-admin-scripts.php';
require_once plugin_dir_path(__FILE__) . 'includes/parsedown.php';
require_once plugin_dir_path(__FILE__) . 'includes/billing-ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/api-endpoints.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-gf-clickup.php';

// OR if it's in functions.php:




wp_localize_script(
    'clickup-admin-js',
    'clickup_ajax',
    ['ajax_url' => admin_url('admin-ajax.php')]
);
// add_action('wp_enqueue_scripts', function() {
//     if (is_page('client-portal')) { // Adjust to match your portal page
//         wp_enqueue_script('wc-add-payment-method');
//     }
// });
add_action('wp_enqueue_scripts', function () {
    if (is_page('client-portal')) { // Adjust to match your page slug or ID
        wp_enqueue_script('wc-add-payment-method');

        // Enqueue your plugin stylesheet
        wp_enqueue_style(
            'client-dashboard-style',
            plugin_dir_url(__FILE__) . 'assets/style.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/style.css')
        );
    }
});
// Ensure Stripe library is loaded
add_action('plugins_loaded', function() {
    if (class_exists('WC_Gateway_Stripe')) {
        if (!class_exists('WC_Stripe_API')) {
            $stripe_includes = WP_PLUGIN_DIR . '/woocommerce-gateway-stripe/includes/';
            if (file_exists($stripe_includes . 'class-wc-stripe-api.php')) {
                require_once $stripe_includes . 'class-wc-stripe-api.php';
            }
        }
    }
});

// Prevent direct access to AJAX handlers
add_action('init', function() {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        // Allow AJAX requests
        return;
    }
    
    // Prevent direct file access
    if (strpos($_SERVER['REQUEST_URI'], 'billing-ajax-handlers.php') !== false) {
        wp_die('Direct access not allowed');
    }
});
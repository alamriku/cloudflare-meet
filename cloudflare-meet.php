<?php
/**
 * Plugin Name: Cloudflare Meet
 * Description: Professional video meetings using Cloudflare RealtimeKit
 * Version: 2.0.0
 * Author: Your Name
 * Text Domain: cloudflare-meet
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

namespace CloudflareMeet;

// Prevent direct access
use CloudflareMeet\Database\DatabaseManager;

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CLOUDFLARE_MEET_VERSION', '2.0.0');
define('CLOUDFLARE_MEET_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLOUDFLARE_MEET_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLOUDFLARE_MEET_PLUGIN_FILE', __FILE__);
define('CLOUDFLARE_MEET_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Cloudflare Meet: Please run "composer install" to install dependencies.', 'cloudflare-meet');
        echo '</p></div>';
    });
    return;
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    Plugin::getInstance();
});

 //Plugin activation hook
register_activation_hook(__FILE__, function() {
    // Load autoloader if not already loaded
    if (!class_exists('CloudflareMeet\\Database\\DatabaseManager')) {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
    }

    // Create database tables
    $database_manager = new DatabaseManager();
    $database_manager->createTables();

    // Flush rewrite rules
    flush_rewrite_rules();
});

// Plugin deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
});

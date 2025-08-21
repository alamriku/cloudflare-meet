<?php
declare(strict_types=1);

namespace CloudflareMeet;

use CloudflareMeet\Admin\AdminManager;
use CloudflareMeet\Core\Settings;
use CloudflareMeet\Frontend\ShortcodeManager;
use CloudflareMeet\API\RealtimeKitClient;
use CloudflareMeet\Database\DatabaseManager;
use CloudflareMeet\Core\AssetManager;
use CloudflareMeet\Core\AjaxHandler;


/**
 * Main Plugin Class
 */
final class Plugin {

    private static ?self $instance = null;

    // Properly declare all properties
    private AdminManager $admin_manager;
    private ShortcodeManager $shortcode_manager;
    private RealtimeKitClient $api_client;
    private DatabaseManager $database_manager;
    private AssetManager $asset_manager;
    private AjaxHandler $ajax_handler;
    private Settings $settings;

    private function __construct() {
        $this->initDependencies();
        $this->registerHooks();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initDependencies(): void {
        $this->settings = new Settings();
        $this->database_manager = new DatabaseManager();
        $this->api_client = new RealtimeKitClient($this->settings);
        $this->asset_manager = new AssetManager();
        $this->ajax_handler = new AjaxHandler($this->api_client, $this->database_manager);
        $this->admin_manager = new AdminManager($this->api_client, $this->database_manager, $this->settings);
        $this->shortcode_manager = new ShortcodeManager($this->api_client, $this->database_manager);
    }

    private function registerHooks(): void {
        register_activation_hook(CLOUDFLARE_MEET_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(CLOUDFLARE_MEET_PLUGIN_FILE, [$this, 'deactivate']);

        add_action('wp_enqueue_scripts', [$this->asset_manager, 'enqueuePublicAssets']);
        //add_action('admin_enqueue_scripts', [$this->asset_manager, 'enqueueAdminAssets']);

        add_action('init', [$this, 'loadTextDomain']);
        // Check if database tables exist and create them if needed
        add_action('admin_init', [$this, 'checkDatabaseTables']);
        // AJAX handlers
        $this->ajax_handler->registerHooks();
    }

    public function activate(): void {
        $this->database_manager->createTables();
        $this->database_manager->insertDefaultData();
        flush_rewrite_rules();

        // Clear any existing caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    public function deactivate(): void {
        wp_clear_scheduled_hook('cloudflare_meet_cleanup');
        flush_rewrite_rules();
    }

    public function loadTextDomain(): void {
        load_plugin_textdomain(
            'cloudflare-meet',
            false,
            dirname(CLOUDFLARE_MEET_BASENAME) . '/languages/'
        );
    }

    public function checkDatabaseTables(): void {
        // Check if tables exist and create them if they don't
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_meetings';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $this->database_manager->createTables();
            $this->database_manager->insertDefaultData();
        }
    }

    // Getter methods for accessing dependencies (if needed)
    public function getSettings(): Settings {
        return $this->settings;
    }

    public function getAPIClient(): RealtimeKitClient {
        return $this->api_client;
    }

    public function getDatabaseManager(): DatabaseManager {
        return $this->database_manager;
    }

    public function getAssetManager(): AssetManager {
        return $this->asset_manager;
    }

    public function getAdminManager(): AdminManager {
        return $this->admin_manager;
    }

    public function getShortcodeManager(): ShortcodeManager {
        return $this->shortcode_manager;
    }

    public function getAjaxHandler(): AjaxHandler {
        return $this->ajax_handler;
    }

    // Prevent cloning and unserialization
    private function __clone() {}

    public function __wakeup(): void {
        throw new \Exception("Cannot unserialize singleton");
    }
}

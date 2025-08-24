<?php
declare(strict_types=1);

namespace CloudflareMeet;

use CloudflareMeet\Admin\AdminManager;
use CloudflareMeet\Core\MeetingPageHandler;
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
        new MeetingPageHandler($this->database_manager);
    }

    private function registerHooks(): void {
        add_action('wp_enqueue_scripts', [$this->asset_manager, 'enqueuePublicAssets']);
        //add_action('admin_enqueue_scripts', [$this->asset_manager, 'enqueueAdminAssets']);

        add_action('init', [$this, 'loadTextDomain']);
	    add_action('init', [$this, 'handleShortcodeAssets'], 20); // After shortcodes are registered
        // AJAX handlers
        $this->ajax_handler->registerHooks();

	    // Shortcode-specific hooks
	    add_action('wp_head', [$this, 'addShortcodeDetection']);
	    add_filter('the_content', [$this, 'detectShortcodeInContent'], 5); // Early priority

	    // Template hooks for shortcode compatibility
	    add_action('wp_footer', [$this, 'addShortcodeFooterScripts']);

	    // Handle dynamic shortcode content (AJAX, widgets, etc.)
	    add_action('wp_ajax_load_shortcode_assets', [$this, 'loadShortcodeAssets']);
	    add_action('wp_ajax_nopriv_load_shortcode_assets', [$this, 'loadShortcodeAssets']);
    }

	/**
	 * Detect if shortcodes are present and conditionally load assets
	 */
	public function handleShortcodeAssets(): void {
		// Check if we're on a page that might have shortcodes
		if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
			return;
		}

		// Check if shortcodes are present in the current content
		global $post;

		if ($post && $this->hasCloudfareMeetShortcode($post->post_content)) {
			$this->asset_manager->enqueueShortcodeAssets();
		}
	}

	/**
	 * Check if content contains our shortcodes
	 */
	private function hasCloudfareMeetShortcode(string $content): bool {
		return has_shortcode($content, 'cloudflare_meet') || has_shortcode($content, 'cf_meeting');
	}

	/**
	 * Detect shortcodes in content filter
	 */
	public function detectShortcodeInContent($content): string {
		if ($this->hasCloudfareMeetShortcode($content)) {
			$this->asset_manager->enqueueShortcodeAssets();
		}
		return $content;
	}

	/**
	 * Add JavaScript to detect shortcodes in head
	 */
	public function addShortcodeDetection(): void {
		if (is_admin()) {
			return;
		}

		?>
		<script type="text/javascript">
            // Early detection for dynamically loaded shortcodes
            window.cloudflare_meet_shortcode_detection = true;
		</script>
		<?php
	}

	/**
	 * Add footer scripts for shortcode functionality
	 */
	public function addShortcodeFooterScripts(): void {
		if (is_admin()) {
			return;
		}

		// Only add if shortcodes are detected on the page
		global $post;
		if (!$post || !$this->hasCloudfareMeetShortcode($post->post_content)) {
			return;
		}

		?>
		<script type="text/javascript">
            // Ensure shortcode initialization after all content is loaded
            jQuery(document).ready(function($) {
                // Re-initialize shortcodes if any were added dynamically
                if (window.CloudflareMeetShortcodes && $('.cloudflare-meet-shortcode-container').length > 0) {
                    console.log('Reinitializing Cloudflare Meet Shortcodes from footer');
                    window.CloudflareMeetShortcodes.init();
                }
            });
		</script>
		<?php
	}

	/**
	 * AJAX handler to load shortcode assets dynamically
	 */
	public function loadShortcodeAssets(): void {
		check_ajax_referer('cloudflare_meet_nonce', 'nonce');

		$this->asset_manager->enqueueShortcodeAssets();

		wp_send_json_success([
			                     'message' => __('Shortcode assets loaded', 'cloudflare-meet')
		                     ]);
	}


	public function activate(): void {
        error_log('active method called');
        $this->database_manager->createTables();
        $this->database_manager->insert_default_data();
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

	/**
	 * Get shortcode usage statistics (for admin dashboard)
	 */
	public function getShortcodeStats(): array {
		global $wpdb;

		$shortcode_count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND (post_content LIKE '%[cloudflare_meet%' OR post_content LIKE '%[cf_meeting%')
        ");

		return [
			'total_shortcodes' => (int) $shortcode_count,
			'active_meetings' => $this->database_manager->getActiveMeetingCount(),
			'plugin_version' => CLOUDFLARE_MEET_VERSION
		];
	}

    // Prevent cloning and unserialization
    private function __clone() {}

    public function __wakeup(): void {
        throw new \Exception("Cannot unserialize singleton");
    }
}

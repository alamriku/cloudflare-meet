<?php
declare(strict_types=1);

namespace CloudflareMeet\Frontend;

use CloudflareMeet\API\RealtimeKitClient;
use CloudflareMeet\Database\DatabaseManager;

/**
 * Shortcode Manager
 */
class ShortcodeManager {
    
    private RealtimeKitClient $api_client;
    private DatabaseManager $database_manager;

    public function __construct(RealtimeKitClient $api_client, DatabaseManager $database_manager) {
        $this->api_client = $api_client;
        $this->database_manager = $database_manager;
        
        $this->registerShortcodes();
    }

    private function registerShortcodes(): void {
        add_shortcode('cloudflare_meet', [$this, 'renderMeetingRoom']);
        add_shortcode('cloudflare_join', [$this, 'renderJoinForm']);
        add_shortcode('cloudflare_create', [$this, 'renderCreateForm']);
    }

    /**
     * Main meeting room shortcode
     * [cloudflare_meet room="my-room" title="My Meeting" max_participants="10"]
     */
    public function renderMeetingRoom($atts): string {
        $atts = shortcode_atts([
            'room' => 'default-room',
            'title' => 'Meeting Room',
            'max_participants' => 10,
            'auto_join' => false,
            'record_on_start' => false,
            'theme' => 'default',
            'width' => '100%',
            'height' => '600px',
        ], $atts, 'cloudflare_meet');

        ob_start();
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/meeting-room.php';
        return ob_get_clean();
    }

    /**
     * Join meeting form shortcode
     * [cloudflare_join]
     */
    public function renderJoinForm($atts): string {
        $atts = shortcode_atts([
            'theme' => 'default',
            'show_name_field' => true,
        ], $atts, 'cloudflare_join');

        ob_start();
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/join-form.php';
        return ob_get_clean();
    }

    /**
     * Create meeting form shortcode
     * [cloudflare_create]
     */
    public function renderCreateForm($atts): string {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to create meetings.', 'cloudflare-meet') . '</p>';
        }

        $atts = shortcode_atts([
            'theme' => 'default',
            'show_advanced_options' => false,
        ], $atts, 'cloudflare_create');

        ob_start();
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/create-form.php';
        return ob_get_clean();
    }
}
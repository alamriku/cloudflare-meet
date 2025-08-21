<?php
declare(strict_types=1);

namespace CloudflareMeet\Core;

/**
 * Asset Manager for loading scripts and styles
 */
class AssetManager {
    
    public function enqueuePublicAssets(): void {
        // Load RealtimeKit UI Kit with proper module initialization
        wp_enqueue_script(
            'realtimekit-ui-init',
            CLOUDFLARE_MEET_PLUGIN_URL . 'assets/js/realtimekit-init.js',
            [],
            CLOUDFLARE_MEET_VERSION,
            false // Load in head
        );

        // Load RealtimeKit Web Core (optional, uncomment if needed)
         wp_enqueue_script(
             'realtimekit-web-core',
             'https://cdn.jsdelivr.net/npm/@cloudflare/realtimekit@latest/dist/browser.js',
             [],
             null,
             true
         );
        // Add module type to the script
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'realtimekit-ui-init') {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);

        // Load our custom JavaScript
        wp_enqueue_script(
            'cloudflare-meet-js',
            CLOUDFLARE_MEET_PLUGIN_URL . 'assets/js/cloudflare-meet.js',
            ['jquery', 'realtimekit-ui-init'],
            CLOUDFLARE_MEET_VERSION,
            true
        );

        // Load CSS
        wp_enqueue_style(
            'cloudflare-meet-css',
            CLOUDFLARE_MEET_PLUGIN_URL . 'assets/css/cloudflare-meet.css',
            [],
            CLOUDFLARE_MEET_VERSION
        );

        $this->localizeScript();
    }

    public function enqueueAdminAssets(string $hook): void {
        // Only load on our admin pages
        if (strpos($hook, 'cloudflare-meet') === false) {
            return;
        }

        wp_enqueue_script(
            'cloudflare-meet-admin-js',
            CLOUDFLARE_MEET_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-color-picker'],
            CLOUDFLARE_MEET_VERSION,
            true
        );

        wp_enqueue_style(
            'cloudflare-meet-admin-css',
            CLOUDFLARE_MEET_PLUGIN_URL . 'assets/css/admin.css',
            ['wp-color-picker'],
            CLOUDFLARE_MEET_VERSION
        );

        $this->localizeScript();
    }

    private function localizeScript(): void {
        wp_localize_script('cloudflare-meet-js', 'cloudflare_meet', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cloudflare_meet_nonce'),
            'user_id' => get_current_user_id(),
            'plugin_url' => CLOUDFLARE_MEET_PLUGIN_URL,
            'strings' => [
                'creating_meeting' => __('Creating meeting...', 'cloudflare-meet'),
                'joining_meeting' => __('Joining meeting...', 'cloudflare-meet'),
                'meeting_created' => __('Meeting created successfully!', 'cloudflare-meet'),
                'joined_meeting' => __('Joined meeting successfully!', 'cloudflare-meet'),
                'meeting_ended' => __('Meeting ended', 'cloudflare-meet'),
                'error_occurred' => __('An error occurred', 'cloudflare-meet'),
                'meeting_id_required' => __('Meeting ID is required', 'cloudflare-meet'),
                'confirm_end_meeting' => __('Are you sure you want to end this meeting?', 'cloudflare-meet'),
            ]
        ]);
    }
}

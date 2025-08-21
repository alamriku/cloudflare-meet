<?php
declare(strict_types=1);

namespace CloudflareMeet\Admin;

use CloudflareMeet\API\RealtimeKitClient;
use CloudflareMeet\Database\DatabaseManager;
use CloudflareMeet\Core\Settings;

/**
 * Admin Manager
 */
class AdminManager {
    
    private RealtimeKitClient $api_client;
    private DatabaseManager $database_manager;
    private Settings $settings;

    public function __construct(RealtimeKitClient $api_client, DatabaseManager $database_manager, Settings $settings) {
        $this->api_client = $api_client;
        $this->database_manager = $database_manager;
        $this->settings = $settings;
        
        $this->registerHooks();
    }

    private function registerHooks(): void {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'initSettings']);
        add_action('wp_ajax_cloudflare_test_api', [$this, 'testApiConnection']);
        add_action('admin_notices', [$this, 'showAdminNotices']);
    }

    public function addAdminMenu(): void {
        add_menu_page(
            __('Cloudflare Meet', 'cloudflare-meet'),
            __('Cloudflare Meet', 'cloudflare-meet'),
            'manage_options',
            'cloudflare-meet',
            [$this, 'dashboardPage'],
            'dashicons-video-alt2',
            30
        );
        
        add_submenu_page(
            'cloudflare-meet',
            __('Dashboard', 'cloudflare-meet'),
            __('Dashboard', 'cloudflare-meet'),
            'manage_options',
            'cloudflare-meet',
            [$this, 'dashboardPage']
        );

        add_submenu_page(
            'cloudflare-meet',
            __('Meetings', 'cloudflare-meet'),
            __('Meetings', 'cloudflare-meet'),
            'manage_options',
            'cloudflare-meet-meetings',
            [$this, 'meetingsPage']
        );

        add_submenu_page(
            'cloudflare-meet',
            __('Settings', 'cloudflare-meet'),
            __('Settings', 'cloudflare-meet'),
            'manage_options',
            'cloudflare-meet-settings',
            [$this, 'settingsPage']
        );

        add_submenu_page(
            'cloudflare-meet',
            __('Analytics', 'cloudflare-meet'),
            __('Analytics', 'cloudflare-meet'),
            'manage_options',
            'cloudflare-meet-analytics',
            [$this, 'analyticsPage']
        );
    }

    public function initSettings(): void {
        register_setting(
            'cloudflare_meet_settings',
            'cloudflare_meet_settings',
            [$this, 'sanitizeSettings']
        );
    }

    public function dashboardPage(): void {
        $recent_meetings = $this->database_manager->getActiveMeetings();
        $total_meetings = $this->getTotalMeetingsCount();
        $meetings_today = $this->getMeetingsTodayCount();
        
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    public function meetingsPage(): void {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'view':
                $this->viewMeetingPage();
                break;
            case 'edit':
                $this->editMeetingPage();
                break;
            default:
                $this->listMeetingsPage();
        }
    }

    public function settingsPage(): void {
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    public function analyticsPage(): void {
        $analytics_data = $this->getAnalyticsData();
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/admin/analytics.php';
    }

    private function listMeetingsPage(): void {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $meetings = $this->database_manager->getMeetings($per_page, $offset);
        $total_meetings = $this->getTotalMeetingsCount();
        $total_pages = ceil($total_meetings / $per_page);
        
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/admin/meetings-list.php';
    }

    private function viewMeetingPage(): void {
        $meeting_id = sanitize_text_field($_GET['meeting_id'] ?? '');
        
        if (empty($meeting_id)) {
            wp_die(__('Invalid meeting ID', 'cloudflare-meet'));
        }
        
        $meeting = $this->database_manager->getMeeting($meeting_id);
        
        if (!$meeting) {
            wp_die(__('Meeting not found', 'cloudflare-meet'));
        }
        
        try {
            $meeting_data = $this->api_client->getMeeting($meeting_id);
            $recordings = $this->api_client->getMeetingRecordings($meeting_id);
            $analytics = $this->api_client->getMeetingAnalytics($meeting_id);
        } catch (\Exception $e) {
            $meeting_data = null;
            $recordings = [];
            $analytics = null;
        }
        
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/admin/meeting-view.php';
    }

    public function testApiConnection(): void {
        check_ajax_referer('cloudflare_meet_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'cloudflare-meet'));
            return;
        }

        // Get current settings
        $org_id = $this->settings->get('organization_id');
        $api_key = $this->settings->get('api_key');

        // Debug: Check if credentials are present
        if (empty($org_id) || empty($api_key)) {
            wp_send_json_error(__('Organization ID or API Key is missing. Please configure your credentials first.', 'cloudflare-meet'));
            return;
        }

        try {
            $connected = $this->api_client->testConnection();

            if ($connected) {
                // Try to get presets to verify full API access
                try {
                    $presets = $this->api_client->getPresets();
                    wp_send_json_success([
                                             'message' => __('API connection successful!', 'cloudflare-meet'),
                                             'presets_count' => count($presets)
                                         ]);
                } catch (\Exception $e) {
                    wp_send_json_success([
                                             'message' => __('API connection successful, but could not load presets: ', 'cloudflare-meet') . $e->getMessage(),
                                             'presets_count' => 0
                                         ]);
                }
        } else {
                wp_send_json_error(__('API connection failed. Please check your credentials.', 'cloudflare-meet'));
            }
        } catch (\Exception $e) {
            wp_send_json_error(__('API connection test failed: ', 'cloudflare-meet') . $e->getMessage());
        }
}

    public function showAdminNotices(): void {
        if (!$this->isConfigured()) {
            echo '<div class="notice notice-warning"><p>';
            printf(
                __('Cloudflare Meet is not configured yet. Please <a href="%s">configure your RealtimeKit credentials</a>.', 'cloudflare-meet'),
                admin_url('admin.php?page=cloudflare-meet-settings')
            );
            echo '</p></div>';
        }
    }

    public function sanitizeSettings($input): array {
        $sanitized = [];
        
        $sanitized['organization_id'] = sanitize_text_field($input['organization_id'] ?? '');
        $sanitized['rest_api_auth_header'] = sanitize_text_field($input['rest_api_auth_header'] ?? '');
        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        $sanitized['default_preset'] = sanitize_text_field($input['default_preset'] ?? 'group_call_participant');
        $sanitized['max_participants'] = max(1, intval($input['max_participants'] ?? 10));
        $sanitized['auto_record'] = !empty($input['auto_record']);
        
        return $sanitized;
    }

    private function isConfigured(): bool {
        return !empty($this->settings->get('organization_id')) && !empty($this->settings->get('api_key'));
    }

    private function getTotalMeetingsCount(): int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_meetings';
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    private function getMeetingsTodayCount(): int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_meetings';
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = CURDATE()"
        );
    }

    private function getAnalyticsData(): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_meetings';
        
        // Get meetings by status
        $status_data = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
            ARRAY_A
        );
        
        // Get meetings by day for the last 30 days
        $daily_data = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM $table_name 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date",
            ARRAY_A
        );
        
        // Get average meeting duration
        $avg_duration = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, ended_at)) 
             FROM $table_name 
             WHERE ended_at IS NOT NULL"
        );
        
        return [
            'status_distribution' => $status_data,
            'daily_meetings' => $daily_data,
            'average_duration' => round($avg_duration ?? 0, 1),
            'total_meetings' => $this->getTotalMeetingsCount(),
            'meetings_today' => $this->getMeetingsTodayCount(),
        ];
    }
}

<?php
declare(strict_types=1);

namespace CloudflareMeet\Admin;

use CloudflareMeet\API\Exceptions\RealtimeKitException;
use CloudflareMeet\API\RealtimeKitClient;
use CloudflareMeet\Database\DatabaseManager;
use CloudflareMeet\Core\Settings;
use CloudflareMeet\Database\Models\Meeting;

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
        // AJAX hooks - WordPress AJAX system
        add_action('wp_ajax_cloudflare_create_meeting', [$this, 'handle_create_meeting_ajax']);
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
        $recent_meetings = $this->database_manager->get_active_meetings();
        $total_meetings = $this->getTotalMeetingsCount();
        $meetings_today = $this->getMeetingsTodayCount();
        
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    public function meetingsPage(): void {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'create':
                $this->create_meeting_page();    // Shows create form
                break;
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

    public function create_meeting_page(): void
    {
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/admin/create-meeting.php';
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
        
        $meetings = $this->database_manager->get_meetings($per_page, $offset);
        $total_meetings = $this->getTotalMeetingsCount();
        $total_pages = ceil($total_meetings / $per_page);
        
        include CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/admin/meetings-list.php';
    }

    private function viewMeetingPage(): void {
        $meeting_id = sanitize_text_field($_GET['meeting_id'] ?? '');
        
        if (empty($meeting_id)) {
            wp_die(__('Invalid meeting ID', 'cloudflare-meet'));
        }
        
        $meeting = $this->database_manager->get_meeting($meeting_id);
        
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

    /**
     * Handle AJAX request to create meeting
     *
     * WordPress AJAX Concepts:
     * - Always check nonce for security
     * - Always check user permissions
     * - Use wp_send_json_success() and wp_send_json_error()
     * - WordPress automatically dies after JSON response
     */
    public function handle_create_meeting_ajax(): void {
        try {
            // 1. Security Check - Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cloudflare_create_meeting')) {
                wp_send_json_error(__('Security check failed. Please refresh the page.', 'cloudflare-meet'));
                return;
            }

            // 2. Permission Check - Only admins can create meetings
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('You do not have permission to create meetings.', 'cloudflare-meet'));
                return;
            }

            // 3. Validate Required Fields
            $meeting_title = sanitize_text_field($_POST['meeting_title'] ?? '');
            if (empty($meeting_title)) {
                wp_send_json_error(__('Meeting title is required.', 'cloudflare-meet'));
                return;
            }

            // 4. Sanitize and Prepare Data
            $meeting_data = $this->prepare_meeting_data($_POST);

            // 5. Create Meeting via RealtimeKit API
            $session_data = $this->create_realtimekit_session($meeting_data);

            // 6. Save Meeting to Database
            $meeting_id = $this->save_meeting_to_database($meeting_data, $session_data);

            // 7. Prepare Response Data
            $response_data = $this->prepare_response_data($meeting_data, $session_data, $meeting_id);

            // 8. Send Success Response
            wp_send_json_success($response_data);

        } catch (\Exception $e) {
            // Handle any errors
            error_log('Cloudflare Meet - Create Meeting Error: ' . $e->getMessage());
            wp_send_json_error(__('Failed to create meeting. Please try again.', 'cloudflare-meet'));
        }
    }

    /**
     * Prepare and sanitize meeting data from POST request
     */
    private function prepare_meeting_data(array $post_data): array {
        return [
            'title' => sanitize_text_field($post_data['meeting_title'] ?? ''),
            'description' => sanitize_textarea_field($post_data['meeting_description'] ?? ''),
            'max_participants' => max(2, min(100, intval($post_data['max_participants'] ?? 10))),
            'auto_record' => !empty($post_data['auto_record']),
            'meeting_type' => sanitize_text_field($post_data['meeting_type'] ?? 'public'),
            'host_user_id' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ];
    }

    /**
     * Create session using RealtimeKit API
     * @throws RealtimeKitException
     */
    private function create_realtimekit_session(array $meeting_data): array {
        // Check if API is configured
        if (!$this->settings->is_configured()) {
            throw new \Exception(__('RealtimeKit API is not configured. Please check settings.', 'cloudflare-meet'));
        }

        // Create RealtimeKit session
        $session_params = [
            'title' => $meeting_data['title'],
            'record_on_start' => $meeting_data['auto_record'],
            //'max_participants' => $meeting_data['max_participants'],
            'preferred_region' => 'us-east-1', // Can be made configurable
        ];

        $session_data = $this->api_client->create_meeting($session_params);

        if (empty($session_data['id'])) {
            throw new \Exception(__('Failed to create RealtimeKit session.', 'cloudflare-meet'));
        }

        return $session_data;
    }

    /**
     * Save meeting to WordPress database
     */
    private function save_meeting_to_database(array $meeting_data, array $session_data): int {
        // Create Meeting model
        $meeting = new Meeting(
            $meeting_data['host_user_id'],
            $session_data['id'], // RealtimeKit session ID
            $meeting_data['title'],
            'scheduled', // Initial status
            0, // Initial participant count
            $meeting_data['max_participants'],
            null, // ID will be auto-generated
            $meeting_data['created_at'],
            null // Not ended yet
        );

        // Save to database
        $saved = $this->database_manager->create_meeting($meeting);

        if (!$saved) {
            throw new \Exception(__('Failed to save meeting to database.', 'cloudflare-meet'));
        }

        return $meeting->get_id() ?? 0;
    }

    /**
     * Prepare response data for frontend
     */
    private function prepare_response_data(array $meeting_data, array $session_data, int $meeting_id): array {
        $site_url = get_site_url();
        $session_id = $session_data['id'];

        return [
            'meeting_id' => $meeting_id,
            'session_id' => $session_id,
            'title' => $meeting_data['title'],
            'description' => $meeting_data['description'],
            'max_participants' => $meeting_data['max_participants'],
            'auto_record' => $meeting_data['auto_record'],
            'meeting_type' => $meeting_data['meeting_type'],
            'status' => 'scheduled',
            'created_at' => $meeting_data['created_at'],

            // URLs for frontend
            'join_url' => $site_url . "/meeting/{$session_id}",
            'start_url' => $site_url . "/meeting/{$session_id}?host=1",
            'admin_url' => admin_url("admin.php?page=cloudflare-meet-meetings&action=view&meeting_id={$session_id}"),

            // RealtimeKit data
            'realtimekit_data' => [
                'session_id' => $session_id,
                'room_name' => $session_data['room_name'] ?? $meeting_data['title'],
            ]
        ];
    }

}

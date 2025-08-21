<?php
declare(strict_types=1);

namespace CloudflareMeet\Core;

use CloudflareMeet\Database\DatabaseManager;
use JetBrains\PhpStorm\NoReturn;

/**
 * Meeting Page Handler for Query Parameter URLs
 */
class MeetingPageHandler {

    private DatabaseManager $database_manager;

    public function __construct(DatabaseManager $database_manager) {
        $this->database_manager = $database_manager;
        $this->register_hooks();
    }

    private function register_hooks(): void {
        add_action('template_redirect', [$this, 'handle_meeting_page']);
    }

    /**
     * Handle meeting page requests
     * Works with:
     * - yoursite.com/meeting?meeting_id=abc123
     * - yoursite.com/any-page?meeting_id=abc123
     * -
     */
    public function handle_meeting_page(): void {
        // Check if this is a meeting page request
        if (!$this->is_meeting_page_request()) {
            return;
        }

        $meeting_id = sanitize_text_field($_GET['meeting_id'] ?? '');

        if (empty($meeting_id)) {
            $this->show_error_page(__('Meeting ID is required.', 'cloudflare-meet'));
            return;
        }

        // Fetch meeting from database
        $meeting = $this->database_manager->get_meeting($meeting_id);

        if (!$meeting) {
            $this->show_error_page(__('Meeting not found.', 'cloudflare-meet'), 'meeting_not_found');
            return;
        }

        // Check meeting status
        if ($meeting->getStatus() === 'ended') {
            $this->show_error_page(__('This meeting has ended.', 'cloudflare-meet'), 'meeting_ended', $meeting);
            return;
        }

        // Load meeting page template
        $this->load_meeting_template($meeting_id, $meeting);
    }

    /**
     * Check if current request is for a meeting page
     * Option C: Both work - any URL with meeting_id parameter
     */
    private function is_meeting_page_request(): bool {
        // Check if meeting_id parameter exists
        if (!isset($_GET['meeting_id'])) {
            return false;
        }

        // Additional checks for meeting-specific URLs (optional)
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $is_meeting_url = str_contains($current_url, '/meeting');
        $is_meeting_page = is_page('meeting');

        // Return true if:
        // 1. Has meeting_id parameter AND
        // 2. Either is meeting-specific URL OR any URL (for flexibility)
        return ($is_meeting_url || $is_meeting_page);
    }

    /**
     * Load meeting page template
     */
    #[NoReturn] private function load_meeting_template(string $meeting_id, $meeting): void {
        // Set global variables for template
        global $cloudflare_meeting, $cloudflare_meeting_id;
        $cloudflare_meeting = $meeting;
        $cloudflare_meeting_id = $meeting_id;

        // Load template
        $template_path = CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/meeting-page.php';

        if (file_exists($template_path)) {
            $this->render_page($template_path);
        } else {
            $this->show_error_page(__('Meeting template not found.', 'cloudflare-meet'));
        }
    }

    /**
     * Show error page for meeting-related errors
     */
    #[NoReturn] private function show_error_page(string $message, string $error_type = 'general', $meeting = null): void {
        global $cloudflare_meeting_error, $cloudflare_meeting_error_type, $cloudflare_meeting;

        $cloudflare_meeting_error = $message;
        $cloudflare_meeting_error_type = $error_type;
        $cloudflare_meeting = $meeting;

        // Try to load error template first
        $error_template = CLOUDFLARE_MEET_PLUGIN_DIR . 'templates/meeting-error.php';

        if (file_exists($error_template)) {
            $this->render_page($error_template);
        } else {
            // Fallback to simple error display
            $this->render_simple_error($message);
        }
    }

    /**
     * Render page with proper WordPress headers
     */
    #[NoReturn] private function render_page(string $template_path): void {
        // Set proper HTTP status
        status_header(200);

        // Load WordPress head/footer
        get_header();
        include $template_path;
        get_footer();
        exit;
    }

    /**
     * Simple error rendering without template
     */
    #[NoReturn] private function render_simple_error(string $message): void {
        status_header(404);

        get_header();
        echo '<div class="cloudflare-meet-error">';
        echo '<h1>' . __('Meeting Error', 'cloudflare-meet') . '</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<a href="' . home_url() . '">' . __('Go to Homepage', 'cloudflare-meet') . '</a>';
        echo '</div>';
        get_footer();
        exit;
    }

    /**
     * Generate meeting link
     */
    public static function generate_meeting_link(string $meeting_id): string {
        $base_url = home_url('/meeting');
        return add_query_arg('meeting_id', $meeting_id, $base_url);
    }

    /**
     * Alternative: Generate meeting link for any page
     */
    public static function generate_meeting_link_any_page(string $meeting_id, string $page_url = ''): string {
        $base_url = $page_url ?: home_url();
        return add_query_arg('meeting_id', $meeting_id, $base_url);
    }
}

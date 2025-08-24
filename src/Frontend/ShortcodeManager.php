<?php
declare(strict_types=1);

namespace CloudflareMeet\Frontend;

use CloudflareMeet\API\RealtimeKitClient;
use CloudflareMeet\Database\DatabaseManager;

/**
 * Handles all shortcodes for the Cloudflare Meet plugin
 */
class ShortcodeManager {

	private RealtimeKitClient $api_client;
	private DatabaseManager $database_manager;

	public function __construct(RealtimeKitClient $api_client, DatabaseManager $database_manager) {
		$this->api_client = $api_client;
		$this->database_manager = $database_manager;

		$this->registerShortcodes();
	}

	/**
	 * Register all shortcodes
	 */
	private function registerShortcodes(): void {
		add_shortcode('cloudflare_meet', [$this, 'renderMeetingShortcode']);
		add_shortcode('cf_meeting', [$this, 'renderMeetingShortcode']); // Short alias
	}

	/**
	 * Main meeting shortcode handler
	 *
	 * Usage examples:
	 * [cloudflare_meet id="meeting-id-123"]
	 * [cloudflare_meet id="meeting-id-123" title="Custom Title"]
	 * [cf_meeting id="meeting-id-123" show_info="false"]
	 *
	 * @param array $atts Shortcode attributes
	 * @param string|null $content Shortcode content
	 * @return string HTML output
	 */
	public function renderMeetingShortcode($atts = [], $content = null): string {
		// Parse shortcode attributes with defaults
		$atts = shortcode_atts([
			                       'id' => '',           // Meeting ID (required)
			                       'title' => '',        // Custom title (optional)
			                       'show_info' => 'true', // Show meeting info section
			                       'show_details' => 'true', // Show meeting details section
			                       'width' => '100%',    // Container width
			                       'height' => 'auto',   // Container height
			                       'class' => '',        // Additional CSS classes
		                       ], $atts, 'cloudflare_meet');

		// Validate required attributes
		if (empty($atts['id'])) {
			return $this->renderError(__('Meeting ID is required.', 'cloudflare-meet'));
		}

		// Get meeting from database
		$meeting = $this->database_manager->get_meeting($atts['id']);

		if (!$meeting) {
			return $this->renderError(__('Meeting not found.', 'cloudflare-meet'));
		}

		if ($meeting->getStatus() !== 'active') {
			return $this->renderError(__('This meeting has ended.', 'cloudflare-meet'));
		}

		// Generate unique container ID for this shortcode instance
		$container_id = 'cf-meet-' . uniqid();

		// Enqueue necessary assets
		$this->enqueueShortcodeAssets();

		// Build the meeting interface HTML
		return $this->buildMeetingHTML($meeting, $atts, $container_id);
	}

	/**
	 * Render error message
	 */
	private function renderError(string $message): string {
		return sprintf(
			'<div class="cf-shortcode-error" style="padding: 15px; background: #fee; border: 1px solid #fcc; color: #c33; border-radius: 4px;">
                <strong>%s:</strong> %s
            </div>',
			__('Cloudflare Meet Error', 'cloudflare-meet'),
			esc_html($message)
		);
	}

	/**
	 * Enqueue assets needed for shortcode
	 */
	private function enqueueShortcodeAssets(): void {
		// Only enqueue if not already done
		if (!wp_script_is('cloudflare-meet-js', 'enqueued')) {
			wp_enqueue_script('cloudflare-meet-js');
			wp_enqueue_style('cloudflare-meet-css');

			// Also ensure RealtimeKit assets are loaded
			if (!wp_script_is('realtimekit-ui', 'enqueued')) {
				wp_enqueue_script('realtimekit-ui');
				wp_enqueue_script('realtimekit-web-core');
			}
		}
	}

	/**
	 * Build the main meeting HTML structure
	 */
	private function buildMeetingHTML($meeting, array $atts, string $container_id): string {
		// Start output buffering
		ob_start();

		// Get host information
		$host = get_user_by('ID', $meeting->getUserId());
		$host_name = $host ? $host->display_name : __('Unknown Host', 'cloudflare-meet');

		// Determine title
		$title = !empty($atts['title']) ? $atts['title'] : $meeting->getRoomName();

		// Container classes
		$container_classes = ['cloudflare-meet-shortcode-container'];
		if (!empty($atts['class'])) {
			$container_classes[] = sanitize_html_class($atts['class']);
		}

		// Container styles
		$container_styles = [];
		if ($atts['width'] !== '100%') {
			$container_styles[] = 'width: ' . esc_attr($atts['width']);
		}
		if ($atts['height'] !== 'auto') {
			$container_styles[] = 'height: ' . esc_attr($atts['height']);
		}

		?>
		<div id="<?php echo esc_attr($container_id); ?>"
		     class="<?php echo esc_attr(implode(' ', $container_classes)); ?>"
			<?php if (!empty($container_styles)): ?>
				style="<?php echo esc_attr(implode('; ', $container_styles)); ?>"
			<?php endif; ?>>

			<?php if (filter_var($atts['show_info'], FILTER_VALIDATE_BOOLEAN)): ?>
				<!-- Meeting Info Section -->
				<div class="cf-shortcode-meeting-header">
					<h3 class="cf-shortcode-meeting-title"><?php echo esc_html($title); ?></h3>

					<div class="cf-shortcode-meeting-meta">
                    <span class="cf-meta-host">
                        <?php _e('Host:', 'cloudflare-meet'); ?> <?php echo esc_html($host_name); ?>
                    </span>
						<span class="cf-meta-participants">
                        <?php _e('Participants:', 'cloudflare-meet'); ?>
							<?php echo esc_html($meeting->getParticipantCount()); ?>/<?php echo esc_html($meeting->getMaxParticipants()); ?>
                    </span>
						<span class="cf-meta-status cf-status-<?php echo esc_attr($meeting->getStatus()); ?>">
                        <?php echo esc_html(ucfirst($meeting->getStatus())); ?>
                    </span>
					</div>
				</div>
			<?php endif; ?>

			<!-- Status Messages -->
			<div class="cf-shortcode-status" style="display: none;"></div>

			<!-- Join Form Section -->
			<div class="cf-shortcode-join-section">
				<form class="cf-shortcode-join-form">
					<input type="hidden" class="cf-meeting-id" value="<?php echo esc_attr($meeting->getSessionId()); ?>">

					<div class="cf-form-row">
						<label for="<?php echo $container_id; ?>-name">
							<?php _e('Your Name', 'cloudflare-meet'); ?> <span class="required">*</span>
						</label>
						<input type="text"
						       id="<?php echo $container_id; ?>-name"
						       name="participant_name"
						       class="cf-participant-name"
						       placeholder="<?php esc_attr_e('Enter your name', 'cloudflare-meet'); ?>"
						       value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->display_name) : ''; ?>"
						       required>
					</div>

					<div class="cf-form-row">
						<label for="<?php echo $container_id; ?>-email">
							<?php _e('Email', 'cloudflare-meet'); ?> <span class="optional">(<?php _e('optional', 'cloudflare-meet'); ?>)</span>
						</label>
						<input type="email"
						       id="<?php echo $container_id; ?>-email"
						       name="participant_email"
						       class="cf-participant-email"
						       placeholder="<?php esc_attr_e('Enter your email', 'cloudflare-meet'); ?>"
						       value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>">
					</div>

					<div class="cf-form-row">
						<button type="submit" class="cf-shortcode-join-btn">
							<span class="cf-btn-text"><?php _e('Join Meeting', 'cloudflare-meet'); ?></span>
							<span class="cf-btn-loading" style="display: none;">
                                <span class="cf-spinner"></span>
                                <?php _e('Joining...', 'cloudflare-meet'); ?>
                            </span>
						</button>
					</div>
				</form>
			</div>

			<?php if (filter_var($atts['show_details'], FILTER_VALIDATE_BOOLEAN)): ?>
				<!-- Meeting Details Section -->
				<div class="cf-shortcode-details">
					<h4><?php _e('Meeting Information', 'cloudflare-meet'); ?></h4>

					<div class="cf-detail-row">
						<strong><?php _e('Meeting ID:', 'cloudflare-meet'); ?></strong>
						<code><?php echo esc_html($meeting->get_id()); ?></code>
						<button type="button" class="cf-copy-btn" data-copy="<?php echo esc_attr($meeting->get_id()); ?>">
							<?php _e('Copy', 'cloudflare-meet'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Video Interface (Hidden by default) -->
			<div class="cf-shortcode-video-interface" style="display: none;">
				<rtk-meeting
					class="cf-shortcode-rtk-meeting"
					mode="fill"
                    style="height: 100vh; width: 100%">
				</rtk-meeting>
			</div>
		</div>

		<?php
		return ob_get_clean();
	}
}

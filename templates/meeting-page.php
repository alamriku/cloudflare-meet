<?php
/**
 * Meeting Page Template
 * Displays meeting info and join form
 *
 * Available globals:
 * @var \CloudflareMeet\Database\Models\Meeting $cloudflare_meeting
 * @var string $cloudflare_meeting_id
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get global variables
global $cloudflare_meeting, $cloudflare_meeting_id;

// Get meeting host info
$host = get_user_by('ID', $cloudflare_meeting->getUserId());
$host_name = $host ? $host->display_name : __('Unknown Host', 'cloudflare-meet');

// Enqueue meeting page assets
wp_enqueue_script('cloudflare-meet-js');
wp_enqueue_style('cloudflare-meet-css');
?>

<div class="cloudflare-meet-page-container">

    <!-- Meeting Header Section -->
    <div class="cf-meeting-header-section">
        <div class="cf-meeting-info-card">
            <h1 class="cf-meeting-title"><?php echo esc_html($cloudflare_meeting->getRoomName()); ?></h1>

            <div class="cf-meeting-meta">
                <div class="cf-meta-item">
                    <span class="cf-meta-label"><?php _e('Host:', 'cloudflare-meet'); ?></span>
                    <span class="cf-meta-value"><?php echo esc_html($host_name); ?></span>
                </div>

                <div class="cf-meta-item">
                    <span class="cf-meta-label"><?php _e('Status:', 'cloudflare-meet'); ?></span>
                    <span class="cf-meta-value cf-status-<?php echo esc_attr($cloudflare_meeting->getStatus()); ?>">
                        <?php echo esc_html(ucfirst($cloudflare_meeting->getStatus())); ?>
                    </span>
                </div>

                <div class="cf-meta-item">
                    <span class="cf-meta-label"><?php _e('Participants:', 'cloudflare-meet'); ?></span>
                    <span class="cf-meta-value">
                        <?php echo esc_html($cloudflare_meeting->getParticipantCount()); ?> /
                        <?php echo esc_html($cloudflare_meeting->getMaxParticipants()); ?>
                    </span>
                </div>

                <div class="cf-meta-item">
                    <span class="cf-meta-label"><?php _e('Created:', 'cloudflare-meet'); ?></span>
                    <span class="cf-meta-value">
                        <?php echo esc_html(human_time_diff(strtotime($cloudflare_meeting->getCreatedAt()))); ?>
                        <?php _e('ago', 'cloudflare-meet'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Messages -->
    <div id="cf-meeting-status" class="cf-status-message" style="display: none;"></div>

    <!-- Join Meeting Section -->
    <div class="cf-join-meeting-section">
        <div class="cf-join-form-card">
            <h2><?php _e('Join Meeting', 'cloudflare-meet'); ?></h2>

            <form id="cf-join-meeting-form" class="cf-join-form">
                <!-- Hidden meeting ID -->
                <input type="hidden" id="cf-meeting-id" value="<?php echo esc_attr($cloudflare_meeting_id); ?>">

                <!-- Participant Name -->
                <div class="cf-form-group">
                    <label for="cf-participant-name"><?php _e('Your Name', 'cloudflare-meet'); ?> <span class="required">*</span></label>
                    <input type="text"
                           id="cf-participant-name"
                           name="participant_name"
                           class="cf-form-control"
                           placeholder="<?php _e('Enter your name', 'cloudflare-meet'); ?>"
                           value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->display_name) : ''; ?>"
                           required>
                </div>

                <!-- Participant Email (Optional) -->
                <div class="cf-form-group">
                    <label for="cf-participant-email"><?php _e('Email', 'cloudflare-meet'); ?> <span class="optional">(<?php _e('optional', 'cloudflare-meet'); ?>)</span></label>
                    <input type="email"
                           id="cf-participant-email"
                           name="participant_email"
                           class="cf-form-control"
                           placeholder="<?php _e('Enter your email', 'cloudflare-meet'); ?>"
                           value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>">
                </div>

                <!-- Join Button -->
                <div class="cf-form-group">
                    <button type="submit" id="cf-join-btn" class="cf-btn cf-btn-primary cf-btn-large">
                        <span class="cf-btn-text"><?php _e('Join Meeting', 'cloudflare-meet'); ?></span>
                        <span class="cf-btn-loading" style="display: none;">
                            <span class="cf-spinner"></span>
                            <?php _e('Joining...', 'cloudflare-meet'); ?>
                        </span>
                    </button>
                </div>

                <!-- Terms/Privacy Notice (if needed) -->
                <div class="cf-form-notice">
                    <small>
                        <?php _e('By joining this meeting, you agree to our terms of service and privacy policy.', 'cloudflare-meet'); ?>
                    </small>
                </div>
            </form>
        </div>
    </div>

    <!-- Meeting Details Section -->
    <div class="cf-meeting-details-section">
        <div class="cf-details-card">
            <h3><?php _e('Meeting Information', 'cloudflare-meet'); ?></h3>

            <div class="cf-detail-item">
                <strong><?php _e('Meeting ID:', 'cloudflare-meet'); ?></strong>
                <code class="cf-meeting-id-display"><?php echo esc_html($cloudflare_meeting_id); ?></code>
                <button type="button" class="cf-copy-btn" data-copy="<?php echo esc_attr($cloudflare_meeting_id); ?>">
                    <?php _e('Copy', 'cloudflare-meet'); ?>
                </button>
            </div>

            <div class="cf-detail-item">
                <strong><?php _e('Meeting Link:', 'cloudflare-meet'); ?></strong>
                <input type="text"
                       class="cf-meeting-link-input"
                       value="<?php echo esc_attr(CloudflareMeet\Core\MeetingPageHandler::generate_meeting_link($cloudflare_meeting_id)); ?>"
                       readonly>
                <button type="button" class="cf-copy-btn" data-copy="<?php echo esc_attr(CloudflareMeet\Core\MeetingPageHandler::generate_meeting_link($cloudflare_meeting_id)); ?>">
                    <?php _e('Copy Link', 'cloudflare-meet'); ?>
                </button>
            </div>

            <?php if ($cloudflare_meeting->getMaxParticipants() > 0): ?>
                <div class="cf-detail-item">
                    <strong><?php _e('Capacity:', 'cloudflare-meet'); ?></strong>
                    <?php printf(__('%d of %d participants', 'cloudflare-meet'),
                                 $cloudflare_meeting->getParticipantCount(),
                                 $cloudflare_meeting->getMaxParticipants()); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- RealtimeKit Video Interface (Hidden by default) -->
<div id="cf-video-interface" class="cf-video-interface" style="display: none;">
    <!-- RealtimeKit UI Kit Web Component - exactly as per docs -->
    <rtk-meeting
            id="cf-rtk-meeting"
            mode="fill"
            style="height: 100vh; width: 100%">
    </rtk-meeting>
</div>

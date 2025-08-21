<?php
/**
 * Meeting Error Page Template
 *
 * Available globals:
 * @var string $cloudflare_meeting_error
 * @var string $cloudflare_meeting_error_type
 * @var \CloudflareMeet\Database\Models\Meeting|null $cloudflare_meeting
 */

if (!defined('ABSPATH')) {
    exit;
}

global $cloudflare_meeting_error, $cloudflare_meeting_error_type, $cloudflare_meeting;
?>

<div class="cloudflare-meet-error-container">
    <div class="cf-error-card">

        <?php if ($cloudflare_meeting_error_type === 'meeting_not_found'): ?>
            <div class="cf-error-icon">❌</div>
            <h1><?php _e('Meeting Not Found', 'cloudflare-meet'); ?></h1>
            <p><?php echo esc_html($cloudflare_meeting_error); ?></p>
            <p><?php _e('The meeting you\'re looking for doesn\'t exist or may have been deleted.', 'cloudflare-meet'); ?></p>

        <?php elseif ($cloudflare_meeting_error_type === 'meeting_ended'): ?>
            <div class="cf-error-icon">🔚</div>
            <h1><?php _e('Meeting Has Ended', 'cloudflare-meet'); ?></h1>
            <p><?php echo esc_html($cloudflare_meeting_error); ?></p>

            <?php if ($cloudflare_meeting): ?>
                <div class="cf-meeting-summary">
                    <h3><?php _e('Meeting Summary', 'cloudflare-meet'); ?></h3>
                    <p><strong><?php _e('Meeting:', 'cloudflare-meet'); ?></strong> <?php echo esc_html($cloudflare_meeting->getRoomName()); ?></p>
                    <p><strong><?php _e('Duration:', 'cloudflare-meet'); ?></strong> <?php echo esc_html($cloudflare_meeting->getFormattedDuration()); ?></p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="cf-error-icon">⚠️</div>
            <h1><?php _e('Meeting Error', 'cloudflare-meet'); ?></h1>
            <p><?php echo esc_html($cloudflare_meeting_error); ?></p>

        <?php endif; ?>

        <div class="cf-error-actions">
            <a href="<?php echo home_url(); ?>" class="cf-btn cf-btn-primary">
                <?php _e('Go to Homepage', 'cloudflare-meet'); ?>
            </a>

            <button onclick="history.back()" class="cf-btn cf-btn-secondary">
                <?php _e('Go Back', 'cloudflare-meet'); ?>
            </button>
        </div>
    </div>
</div>


<?php
/**
 * View Meeting Template (Admin)
 *
 * @var \CloudflareMeet\Database\Models\Meeting $meeting
 * @var array|null $meeting_data RealtimeKit meeting data
 * @var array $recordings Meeting recordings
 * @var array|null $analytics Meeting analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

$host = get_user_by('ID', $meeting->getUserId());
$host_name = $host ? $host->display_name : __('Unknown Host', 'cloudflare-meet');
$participants = $this->database_manager->get_meeting_participants($meeting->getSessionId());
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Back to meetings list -->
    <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-meetings'); ?>" class="button">
        &larr; <?php _e('Back to Meetings', 'cloudflare-meet'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Meeting Overview Cards -->
    <div class="cf-meeting-overview" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">

        <!-- Meeting Info Card -->
        <div class="cf-info-card" style="background: #fff; padding: 20px; border-left: 4px solid #0073aa; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 15px 0; color: #0073aa;"><?php _e('Meeting Information', 'cloudflare-meet'); ?></h3>

            <div class="cf-info-row">
                <strong><?php _e('Meeting ID:', 'cloudflare-meet'); ?></strong><br>
                <code style="background: #f0f0f1; padding: 4px 8px; border-radius: 3px;"><?php echo esc_html($meeting->getSessionId()); ?></code>
                <button type="button" class="button-link cf-copy-btn" data-copy="<?php echo esc_attr($meeting->getSessionId()); ?>" style="margin-left: 10px;">
                    <?php _e('Copy', 'cloudflare-meet'); ?>
                </button>
            </div>

            <div class="cf-info-row" style="margin-top: 15px;">
                <strong><?php _e('Room Name:', 'cloudflare-meet'); ?></strong><br>
                <?php echo esc_html($meeting->getRoomName()); ?>
            </div>

            <div class="cf-info-row" style="margin-top: 15px;">
                <strong><?php _e('Host:', 'cloudflare-meet'); ?></strong><br>
                <?php echo esc_html($host_name); ?>
                <?php if ($host): ?>
                    <small>(<?php echo esc_html($host->user_email); ?>)</small>
                <?php endif; ?>
            </div>

            <div class="cf-info-row" style="margin-top: 15px;">
                <strong><?php _e('Status:', 'cloudflare-meet'); ?></strong><br>
                <span class="cf-status cf-status-<?php echo esc_attr($meeting->getStatus()); ?>" style="padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; text-transform: uppercase;">
                    <?php echo esc_html(ucfirst($meeting->getStatus())); ?>
                </span>
            </div>
        </div>

        <!-- Meeting Stats Card -->
        <div class="cf-stats-card" style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 15px 0; color: #00a32a;"><?php _e('Meeting Statistics', 'cloudflare-meet'); ?></h3>

            <div class="cf-stat-item" style="margin-bottom: 15px;">
                <div style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo esc_html($meeting->getParticipantCount()); ?></div>
                <div style="color: #666; font-size: 14px;"><?php _e('Current Participants', 'cloudflare-meet'); ?></div>
            </div>

            <div class="cf-stat-item" style="margin-bottom: 15px;">
                <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo esc_html($meeting->getMaxParticipants()); ?></div>
                <div style="color: #666; font-size: 14px;"><?php _e('Max Participants', 'cloudflare-meet'); ?></div>
            </div>

            <div class="cf-stat-item" style="margin-bottom: 15px;">
                <div style="font-size: 24px; font-weight: bold; color: #d63638;"><?php echo esc_html($meeting->getFormattedDuration()); ?></div>
                <div style="color: #666; font-size: 14px;"><?php _e('Duration', 'cloudflare-meet'); ?></div>
            </div>

            <div class="cf-stat-item">
                <div style="font-size: 18px; font-weight: bold; color: #666;">
                    <?php echo esc_html(human_time_diff(strtotime($meeting->getCreatedAt()))); ?> <?php _e('ago', 'cloudflare-meet'); ?>
                </div>
                <div style="color: #666; font-size: 14px;"><?php _e('Created', 'cloudflare-meet'); ?></div>
            </div>
        </div>

        <!-- Meeting Links Card -->
        <div class="cf-links-card" style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 15px 0; color: #d63638;"><?php _e('Meeting Links', 'cloudflare-meet'); ?></h3>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;"><?php _e('Join Link:', 'cloudflare-meet'); ?></label>
                <input type="text" class="regular-text" value="<?php echo esc_attr(\CloudflareMeet\Core\MeetingPageHandler::generate_meeting_link($meeting->getSessionId())); ?>" readonly>
                <button type="button" class="button cf-copy-btn" data-copy="<?php echo esc_attr(\CloudflareMeet\Core\MeetingPageHandler::generate_meeting_link($meeting->getSessionId())); ?>">
                    <?php _e('Copy Link', 'cloudflare-meet'); ?>
                </button>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;"><?php _e('Shortcode:', 'cloudflare-meet'); ?></label>
                <input type="text" class="regular-text" value='[cloudflare_meeting_room meeting_id="<?php echo esc_attr($meeting->getSessionId()); ?>"]' readonly>
                <button type="button" class="button cf-copy-btn" data-copy='[cloudflare_meeting_room meeting_id="<?php echo esc_attr($meeting->getSessionId()); ?>"]'>
                    <?php _e('Copy Shortcode', 'cloudflare-meet'); ?>
                </button>
            </div>

            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 5px;"><?php _e('Join Button Shortcode:', 'cloudflare-meet'); ?></label>
                <input type="text" class="regular-text" value='[cloudflare_join_button meeting_id="<?php echo esc_attr($meeting->getSessionId()); ?>" text="Join Meeting"]' readonly>
                <button type="button" class="button cf-copy-btn" data-copy='[cloudflare_join_button meeting_id="<?php echo esc_attr($meeting->getSessionId()); ?>" text="Join Meeting"]'>
                    <?php _e('Copy Shortcode', 'cloudflare-meet'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Meeting Actions -->
    <div class="cf-meeting-actions" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h3><?php _e('Actions', 'cloudflare-meet'); ?></h3>
        <p>
            <?php if ($meeting->isActive()): ?>
                <a href="<?php echo esc_url(\CloudflareMeet\Core\MeetingPageHandler::generate_meeting_link($meeting->getSessionId())); ?>" class="button button-primary" target="_blank">
                    <?php _e('Join Meeting', 'cloudflare-meet'); ?>
                </a>
                <button type="button" class="button button-secondary cf-end-meeting" data-meeting-id="<?php echo esc_attr($meeting->getSessionId()); ?>">
                    <?php _e('End Meeting', 'cloudflare-meet'); ?>
                </button>
            <?php else: ?>
                <span style="color: #666;"><?php _e('Meeting has ended', 'cloudflare-meet'); ?></span>
            <?php endif; ?>

            <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-meetings&action=edit&meeting_id=' . urlencode($meeting->getSessionId())); ?>" class="button">
                <?php _e('Edit Meeting', 'cloudflare-meet'); ?>
            </a>

            <button type="button" class="button button-link-delete cf-delete-meeting" data-meeting-id="<?php echo esc_attr($meeting->getSessionId()); ?>">
                <?php _e('Delete Meeting', 'cloudflare-meet'); ?>
            </button>
        </p>
    </div>

    <!-- Participants Section -->
    <div class="cf-participants-section" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h3><?php _e('Participants', 'cloudflare-meet'); ?> (<?php echo count($participants); ?>)</h3>

        <?php if (empty($participants)): ?>
            <p><?php _e('No participants have joined this meeting yet.', 'cloudflare-meet'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th><?php _e('Name', 'cloudflare-meet'); ?></th>
                    <th><?php _e('Email', 'cloudflare-meet'); ?></th>
                    <th><?php _e('Role', 'cloudflare-meet'); ?></th>
                    <th><?php _e('Status', 'cloudflare-meet'); ?></th>
                    <th><?php _e('Joined At', 'cloudflare-meet'); ?></th>
                    <th><?php _e('Duration', 'cloudflare-meet'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($participants as $participant): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($participant->get_participant_name()); ?></strong>
                            <?php if ($participant->is_logged_in_user()): ?>
                                <small>(<?php _e('Registered User', 'cloudflare-meet'); ?>)</small>
                            <?php else: ?>
                                <small>(<?php _e('Guest', 'cloudflare-meet'); ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $participant->get_email() ? esc_html($participant->get_email()) : '—'; ?></td>
                        <td>
                                <span class="cf-role cf-role-<?php echo esc_attr($participant->get_role()); ?>">
                                    <?php echo esc_html(ucfirst($participant->get_role())); ?>
                                </span>
                        </td>
                        <td>
                                <span class="cf-participant-status cf-status-<?php echo esc_attr($participant->get_status()); ?>">
                                    <?php echo esc_html(ucfirst($participant->get_status())); ?>
                                </span>
                        </td>
                        <td>
                            <?php if ($participant->get_joined_at()): ?>
                                <?php echo esc_html(human_time_diff(strtotime($participant->get_joined_at()))); ?> <?php _e('ago', 'cloudflare-meet'); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($participant->get_joined_at() && $participant->get_left_at()) {
                                $duration = strtotime($participant->get_left_at()) - strtotime($participant->get_joined_at());
                                echo esc_html(gmdate('H:i:s', $duration));
                            } elseif ($participant->get_joined_at()) {
                                echo '<span style="color: #00a32a;">' . __('Active', 'cloudflare-meet') . '</span>';
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- RealtimeKit Data (if available) -->
    <?php if ($meeting_data): ?>
        <div class="cf-realtimekit-data" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3><?php _e('RealtimeKit Data', 'cloudflare-meet'); ?></h3>
            <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto;">
                <pre><?php echo esc_html(json_encode($meeting_data, JSON_PRETTY_PRINT)); ?></pre>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recordings Section (if available) -->
    <?php if (!empty($recordings)): ?>
        <div class="cf-recordings-section" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3><?php _e('Recordings', 'cloudflare-meet'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th><?php _e('Recording ID', 'cloudflare-meet'); ?></th>
                    <th><?php _e('Duration', 'cloudflare-meet'); ?></th>
                    <th><?php _e('Created', 'cloudflare-meet'); ?></th>
                    <th><?php _e('Actions', 'cloudflare-meet'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recordings as $recording): ?>
                    <tr>
                        <td><code><?php echo esc_html($recording['id'] ?? 'N/A'); ?></code></td>
                        <td><?php echo esc_html($recording['duration'] ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($recording['created_at'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if (isset($recording['download_url'])): ?>
                                <a href="<?php echo esc_url($recording['download_url']); ?>" class="button button-small" target="_blank">
                                    <?php _e('Download', 'cloudflare-meet'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    jQuery(document).ready(function($) {
        // Copy functionality
        $('.cf-copy-btn').on('click', function() {
            const textToCopy = $(this).data('copy');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    showCopyFeedback($(this));
                });
            } else {
                // Fallback for older browsers
                const tempTextarea = $('<textarea>');
                $('body').append(tempTextarea);
                tempTextarea.val(textToCopy).select();
                document.execCommand('copy');
                tempTextarea.remove();
                showCopyFeedback($(this));
            }
        });

        function showCopyFeedback(button) {
            const originalText = button.text();
            button.text('<?php _e('Copied!', 'cloudflare-meet'); ?>');
            setTimeout(() => {
                button.text(originalText);
            }, 2000);
        }

        // End meeting
        $('.cf-end-meeting').on('click', function() {
            if (!confirm('<?php _e('Are you sure you want to end this meeting?', 'cloudflare-meet'); ?>')) {
                return;
            }

            const meetingId = $(this).data('meeting-id');
            const button = $(this);

            button.prop('disabled', true).text('<?php _e('Ending...', 'cloudflare-meet'); ?>');

            $.post(ajaxurl, {
                action: 'cloudflare_end_meeting',
                meeting_id: meetingId,
                nonce: '<?php echo wp_create_nonce('cloudflare_meet_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                    button.prop('disabled', false).text('<?php _e('End Meeting', 'cloudflare-meet'); ?>');
                }
            });
        });

        // Delete meeting
        $('.cf-delete-meeting').on('click', function() {
            if (!confirm('<?php _e('Are you sure you want to delete this meeting? This action cannot be undone.', 'cloudflare-meet'); ?>')) {
                return;
            }

            const meetingId = $(this).data('meeting-id');

            $.post(ajaxurl, {
                action: 'cloudflare_delete_meeting',
                meeting_id: meetingId,
                nonce: '<?php echo wp_create_nonce('cloudflare_meet_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    window.location.href = '<?php echo admin_url('admin.php?page=cloudflare-meet-meetings'); ?>';
                } else {
                    alert(response.data);
                }
            });
        });
    });
</script>

<style>
    .cf-status {
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .cf-status-active { background: #00a32a; color: white; }
    .cf-status-ended { background: #ddd; color: #666; }
    .cf-status-scheduled { background: #72aee6; color: white; }

    .cf-role-host { color: #d63638; font-weight: bold; }
    .cf-role-participant { color: #0073aa; }

    .cf-participant-status.cf-status-joined { color: #00a32a; }
    .cf-participant-status.cf-status-waiting { color: #dba617; }
    .cf-participant-status.cf-status-left { color: #666; }
</style>

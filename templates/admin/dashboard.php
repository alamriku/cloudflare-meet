<?php
/**
 * Admin Dashboard Template
 * 
 * @var array $recent_meetings
 * @var int $total_meetings
 * @var int $meetings_today
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Stats Cards -->
    <div class="cf-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="cf-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #0073aa; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #0073aa;"><?php _e('Total Meetings', 'cloudflare-meet'); ?></h3>
            <div style="font-size: 2em; font-weight: bold;"><?php echo number_format($total_meetings); ?></div>
        </div>
        
        <div class="cf-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #00a32a;"><?php _e('Meetings Today', 'cloudflare-meet'); ?></h3>
            <div style="font-size: 2em; font-weight: bold;"><?php echo number_format($meetings_today); ?></div>
        </div>
        
        <div class="cf-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #d63638;"><?php _e('Active Meetings', 'cloudflare-meet'); ?></h3>
            <div style="font-size: 2em; font-weight: bold;"><?php echo count($recent_meetings); ?></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="cf-quick-actions" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Quick Actions', 'cloudflare-meet'); ?></h2>
        <p>
            <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-meetings&action=create'); ?>" class="button button-primary">
                <?php _e('Create Meeting', 'cloudflare-meet'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-settings'); ?>" class="button">
                <?php _e('Settings', 'cloudflare-meet'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-analytics'); ?>" class="button">
                <?php _e('View Analytics', 'cloudflare-meet'); ?>
            </a>
        </p>
    </div>
    
    <!-- Recent Meetings -->
    <div class="cf-recent-meetings" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Recent Active Meetings', 'cloudflare-meet'); ?></h2>
        
        <?php if (empty($recent_meetings)): ?>
            <p><?php _e('No active meetings found.', 'cloudflare-meet'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Meeting ID', 'cloudflare-meet'); ?></th>
                        <th><?php _e('Room Name', 'cloudflare-meet'); ?></th>
                        <th><?php _e('Host', 'cloudflare-meet'); ?></th>
                        <th><?php _e('Participants', 'cloudflare-meet'); ?></th>
                        <th><?php _e('Created', 'cloudflare-meet'); ?></th>
                        <th><?php _e('Actions', 'cloudflare-meet'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_meetings as $meeting): ?>
                        <?php $host = get_user_by('ID', $meeting->getUserId()); ?>
                        <tr>
                            <td><code><?php echo esc_html($meeting->getSessionId()); ?></code></td>
                            <td><?php echo esc_html($meeting->getRoomName()); ?></td>
                            <td><?php echo $host ? esc_html($host->display_name) : __('Unknown', 'cloudflare-meet'); ?></td>
                            <td><?php echo esc_html($meeting->getParticipantCount()); ?></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($meeting->getCreatedAt()))); ?> <?php _e('ago', 'cloudflare-meet'); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-meetings&action=view&meeting_id=' . urlencode($meeting->getSessionId())); ?>" class="button button-small">
                                    <?php _e('View', 'cloudflare-meet'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- System Status -->
    <div class="cf-system-status" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('System Status', 'cloudflare-meet'); ?></h2>
        <div id="cf-api-status">
            <p><?php _e('Checking API connection...', 'cloudflare-meet'); ?></p>
        </div>
        <button type="button" id="cf-test-api" class="button">
            <?php _e('Test API Connection', 'cloudflare-meet'); ?>
        </button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test API on page load
    testApiConnection();
    
    $('#cf-test-api').click(function() {
        testApiConnection();
    });
    
    function testApiConnection() {
        $('#cf-api-status').html('<p><?php _e('Testing API connection...', 'cloudflare-meet'); ?></p>');
        
        $.post(ajaxurl, {
            action: 'cloudflare_test_api',
            nonce: '<?php echo wp_create_nonce('cloudflare_meet_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#cf-api-status').html(
                    '<p style="color: green;">✅ ' + response.data.message + '</p>' +
                    (response.data.presets_count ? '<p><small><?php _e('Found', 'cloudflare-meet'); ?> ' + response.data.presets_count + ' <?php _e('presets', 'cloudflare-meet'); ?></small></p>' : '')
                );
            } else {
                $('#cf-api-status').html('<p style="color: red;">❌ ' + response.data + '</p>');
            }
        }).fail(function() {
            $('#cf-api-status').html('<p style="color: red;">❌ <?php _e('Connection test failed', 'cloudflare-meet'); ?></p>');
        });
    }
});
</script>
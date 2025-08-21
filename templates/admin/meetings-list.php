<?php
/**
 * Meetings List Template
 *
 * @var array $meetings
 * @var int $total_meetings
 * @var int $total_pages
 * @var int $page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-meetings&action=create'); ?>" class="button button-primary">
                <?php _e('Create New Meeting', 'cloudflare-meet'); ?>
            </a>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(__('%d meetings', 'cloudflare-meet'), $total_meetings); ?></span>
                <?php
                echo paginate_links(array(
                                        'base' => add_query_arg('paged', '%#%'),
                                        'format' => '',
                                        'prev_text' => __('&laquo;'),
                                        'next_text' => __('&raquo;'),
                                        'total' => $total_pages,
                                        'current' => $page
                                    ));
                ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($meetings)): ?>
        <div class="notice notice-info">
            <p><?php _e('No meetings found.', 'cloudflare-meet'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th scope="col" class="manage-column column-title"><?php _e('Meeting ID', 'cloudflare-meet'); ?></th>
                <th scope="col" class="manage-column"><?php _e('Room Name', 'cloudflare-meet'); ?></th>
                <th scope="col" class="manage-column"><?php _e('Host', 'cloudflare-meet'); ?></th>
                <th scope="col" class="manage-column"><?php _e('Status', 'cloudflare-meet'); ?></th>
                <th scope="col" class="manage-column"><?php _e('Participants', 'cloudflare-meet'); ?></th>
                <th scope="col" class="manage-column"><?php _e('Created', 'cloudflare-meet'); ?></th>
                <th scope="col" class="manage-column"><?php _e('Duration', 'cloudflare-meet'); ?></th>
                <th scope="col" class="manage-column"><?php _e('Actions', 'cloudflare-meet'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($meetings as $meeting): ?>
                <?php $host = get_user_by('ID', $meeting->getUserId()); ?>
                <tr>
                    <td><code><?php echo esc_html($meeting->getSessionId()); ?></code></td>
                    <td><strong><?php echo esc_html($meeting->getRoomName()); ?></strong></td>
                    <td><?php echo $host ? esc_html($host->display_name) : __('Unknown', 'cloudflare-meet'); ?></td>
                    <td>
                            <span class="cf-status cf-status-<?php echo esc_attr($meeting->getStatus()); ?>">
                                <?php echo esc_html(ucfirst($meeting->getStatus())); ?>
                            </span>
                    </td>
                    <td><?php echo esc_html($meeting->getParticipantCount()); ?> / <?php echo esc_html($meeting->getMaxParticipants()); ?></td>
                    <td><?php echo esc_html(human_time_diff(strtotime($meeting->getCreatedAt()))); ?> <?php _e('ago', 'cloudflare-meet'); ?></td>
                    <td><?php echo esc_html($meeting->getFormattedDuration()); ?></td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-meetings&action=view&meeting_id=' . urlencode($meeting->getSessionId())); ?>" class="button button-small">
                            <?php _e('View', 'cloudflare-meet'); ?>
                        </a>
                        <?php if ($meeting->isActive()): ?>
                            <a href="#" class="button button-small cf-end-meeting" data-meeting-id="<?php echo esc_attr($meeting->getSessionId()); ?>">
                                <?php _e('End', 'cloudflare-meet'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
    .cf-status {
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .cf-status-active {
        background: #00a32a;
        color: white;
    }
    .cf-status-ended {
        background: #ddd;
        color: #666;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        $('.cf-end-meeting').click(function(e) {
            e.preventDefault();

            if (!confirm('<?php _e("Are you sure you want to end this meeting?", "cloudflare-meet"); ?>')) {
                return;
            }

            const meetingId = $(this).data('meeting-id');
            const button = $(this);

            button.prop('disabled', true).text('<?php _e("Ending...", "cloudflare-meet"); ?>');

            $.post(ajaxurl, {
                action: 'cloudflare_end_meeting',
                meeting_id: meetingId,
                nonce: '<?php echo wp_create_nonce('cloudflare_meet_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    console.log(response)
                    //location.reload();
                } else {
                    alert(response.data);
                    button.prop('disabled', false).text('<?php _e("End", "cloudflare-meet"); ?>');
                }
            });
        });
    });
</script>

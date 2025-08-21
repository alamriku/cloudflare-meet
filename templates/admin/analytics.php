<?php
/**
 * Analytics Page Template
 *
 * @var array $analytics_data
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Analytics Overview Cards -->
    <div class="cf-analytics-overview" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div class="cf-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #0073aa; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #0073aa;"><?php _e('Total Meetings', 'cloudflare-meet'); ?></h3>
            <div style="font-size: 2em; font-weight: bold;"><?php echo number_format($analytics_data['total_meetings'] ?? 0); ?></div>
            <small style="color: #666;"><?php _e('All time', 'cloudflare-meet'); ?></small>
        </div>

        <div class="cf-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #00a32a;"><?php _e('Meetings Today', 'cloudflare-meet'); ?></h3>
            <div style="font-size: 2em; font-weight: bold;"><?php echo number_format($analytics_data['meetings_today'] ?? 0); ?></div>
            <small style="color: #666;"><?php echo date('M j, Y'); ?></small>
        </div>

        <div class="cf-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin: 0 0 10px 0; color: #d63638;"><?php _e('Avg Duration', 'cloudflare-meet'); ?></h3>
            <div style="font-size: 2em; font-weight: bold;"><?php echo number_format($analytics_data['average_duration'] ?? 0, 1); ?></div>
            <small style="color: #666;"><?php _e('minutes', 'cloudflare-meet'); ?></small>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">

        <!-- Daily Meetings Chart -->
        <div class="cf-chart-container" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2><?php _e('Meetings Over Time (Last 30 Days)', 'cloudflare-meet'); ?></h2>
            <canvas id="cf-daily-meetings-chart" width="400" height="200"></canvas>
        </div>

        <!-- Status Distribution Chart -->
        <div class="cf-chart-container" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2><?php _e('Meeting Status Distribution', 'cloudflare-meet'); ?></h2>
            <canvas id="cf-status-chart" width="400" height="200"></canvas>
        </div>

    </div>

    <!-- Detailed Statistics -->
    <div class="cf-detailed-stats" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Detailed Statistics', 'cloudflare-meet'); ?></h2>

        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th><?php _e('Metric', 'cloudflare-meet'); ?></th>
                <th><?php _e('Value', 'cloudflare-meet'); ?></th>
                <th><?php _e('Description', 'cloudflare-meet'); ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><strong><?php _e('Total Meetings', 'cloudflare-meet'); ?></strong></td>
                <td><?php echo number_format($analytics_data['total_meetings'] ?? 0); ?></td>
                <td><?php _e('Total number of meetings created', 'cloudflare-meet'); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Meetings Today', 'cloudflare-meet'); ?></strong></td>
                <td><?php echo number_format($analytics_data['meetings_today'] ?? 0); ?></td>
                <td><?php _e('Meetings created today', 'cloudflare-meet'); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Average Duration', 'cloudflare-meet'); ?></strong></td>
                <td><?php echo number_format($analytics_data['average_duration'] ?? 0, 1); ?> <?php _e('min', 'cloudflare-meet'); ?></td>
                <td><?php _e('Average meeting duration in minutes', 'cloudflare-meet'); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Active Meetings', 'cloudflare-meet'); ?></strong></td>
                <td>
                    <?php
                    $active_count = 0;
                    if (isset($analytics_data['status_distribution'])) {
                        foreach ($analytics_data['status_distribution'] as $status) {
                            if ($status['status'] === 'active') {
                                $active_count = $status['count'];
                                break;
                            }
                        }
                    }
                    echo number_format($active_count);
                    ?>
                </td>
                <td><?php _e('Currently active meetings', 'cloudflare-meet'); ?></td>
            </tr>
            <tr>
                <td><strong><?php _e('Completed Meetings', 'cloudflare-meet'); ?></strong></td>
                <td>
                    <?php
                    $ended_count = 0;
                    if (isset($analytics_data['status_distribution'])) {
                        foreach ($analytics_data['status_distribution'] as $status) {
                            if ($status['status'] === 'ended') {
                                $ended_count = $status['count'];
                                break;
                            }
                        }
                    }
                    echo number_format($ended_count);
                    ?>
                </td>
                <td><?php _e('Meetings that have ended', 'cloudflare-meet'); ?></td>
            </tr>
            </tbody>
        </table>
    </div>

    <!-- Export Options -->
    <div class="cf-export-options" style="background: #fff; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2><?php _e('Export Data', 'cloudflare-meet'); ?></h2>
        <p><?php _e('Export your meeting analytics data for further analysis.', 'cloudflare-meet'); ?></p>

        <div style="margin: 15px 0;">
            <label for="cf-export-period"><?php _e('Select Period:', 'cloudflare-meet'); ?></label>
            <select id="cf-export-period">
                <option value="7"><?php _e('Last 7 days', 'cloudflare-meet'); ?></option>
                <option value="30"><?php _e('Last 30 days', 'cloudflare-meet'); ?></option>
                <option value="90"><?php _e('Last 3 months', 'cloudflare-meet'); ?></option>
                <option value="365"><?php _e('Last year', 'cloudflare-meet'); ?></option>
            </select>
        </div>

        <button type="button" id="cf-export-csv" class="button button-secondary">
            <?php _e('Export as CSV', 'cloudflare-meet'); ?>
        </button>

        <button type="button" id="cf-export-pdf" class="button button-secondary" disabled>
            <?php _e('Export as PDF', 'cloudflare-meet'); ?> <small>(<?php _e('Coming Soon', 'cloudflare-meet'); ?>)</small>
        </button>
    </div>
</div>

<!-- Include Chart.js for charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    jQuery(document).ready(function($) {

        // Daily Meetings Chart
        const dailyMeetingsData = <?php echo json_encode($analytics_data['daily_meetings'] ?? []); ?>;

        const dailyMeetingsChart = new Chart(document.getElementById('cf-daily-meetings-chart'), {
            type: 'line',
            data: {
                labels: dailyMeetingsData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: '<?php _e('Meetings', 'cloudflare-meet'); ?>',
                    data: dailyMeetingsData.map(item => item.count),
                    borderColor: '#0073aa',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Status Distribution Chart
        const statusData = <?php echo json_encode($analytics_data['status_distribution'] ?? []); ?>;

        const statusChart = new Chart(document.getElementById('cf-status-chart'), {
            type: 'doughnut',
            data: {
                labels: statusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: [
                        '#00a32a', // active - green
                        '#d63638', // ended - red
                        '#dba617', // other statuses - yellow
                        '#72aee6'  // additional colors
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Export CSV functionality
        $('#cf-export-csv').click(function() {
            const period = $('#cf-export-period').val();
            const button = $(this);

            button.prop('disabled', true).text('<?php _e('Exporting...', 'cloudflare-meet'); ?>');

            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            });

            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'cloudflare_export_analytics'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'period',
                value: period
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'format',
                value: 'csv'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: '<?php echo wp_create_nonce('cloudflare_meet_nonce'); ?>'
            }));

            $('body').append(form);
            form.submit();
            form.remove();

            setTimeout(function() {
                button.prop('disabled', false).text('<?php _e('Export as CSV', 'cloudflare-meet'); ?>');
            }, 2000);
        });

        // Refresh data every 30 seconds for real-time updates
        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
    });
</script>

<style>
    .cf-chart-container canvas {
        max-height: 300px;
    }

    .cf-stat-card:hover {
        transform: translateY(-2px);
        transition: transform 0.2s ease;
    }

    .cf-export-options select {
        margin: 0 10px;
        padding: 5px;
    }

    .cf-export-options button {
        margin-right: 10px;
    }

    @media (max-width: 782px) {
        .cf-analytics-overview,
        div[style*="grid-template-columns: 1fr 1fr"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

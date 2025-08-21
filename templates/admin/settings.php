<?php
/**
 * Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php
        // These are the correct WordPress settings API functions
        settings_fields('cloudflare_meet_settings');
        ?>

        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row">
                    <label for="organization_id"><?php _e('Organization ID', 'cloudflare-meet'); ?></label>
                </th>
                <td>
                    <?php $org_id = get_option('cloudflare_meet_settings')['organization_id'] ?? ''; ?>
                    <input type="text"
                           id="organization_id"
                           name="cloudflare_meet_settings[organization_id]"
                           value="<?php echo esc_attr($org_id); ?>"
                           class="regular-text" />
                    <p class="description"><?php _e('Your RealtimeKit Organization ID from Cloudflare dashboard', 'cloudflare-meet'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="api_key"><?php _e('API Key', 'cloudflare-meet'); ?></label>
                </th>
                <td>
                    <?php $api_key = get_option('cloudflare_meet_settings')['api_key'] ?? ''; ?>
                    <input type="password"
                           id="api_key"
                           name="cloudflare_meet_settings[api_key]"
                           value="<?php echo esc_attr($api_key); ?>"
                           class="regular-text" />
                    <p class="description"><?php _e('Your RealtimeKit API Key from Cloudflare dashboard', 'cloudflare-meet'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="api_key"><?php _e('Auth Token', 'cloudflare-meet'); ?></label>
                </th>
                <td>
                    <?php $rest_api_auth_header = get_option('cloudflare_meet_settings')['rest_api_auth_header'] ?? ''; ?>
                    <input type="password"
                           id="api_key"
                           name="cloudflare_meet_settings[rest_api_auth_header]"
                           value="<?php echo esc_attr($rest_api_auth_header); ?>"
                           class="regular-text" />
                    <p class="description"><?php _e('Your RealtimeKit Auth Token from Cloudflare dashboard', 'cloudflare-meet'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="default_preset"><?php _e('Default Preset', 'cloudflare-meet'); ?></label>
                </th>
                <td>
                    <?php
                    $settings = get_option('cloudflare_meet_settings', []);
                    $current_preset = $settings['default_preset'] ?? 'group_call_participant';
                    ?>
                    <input type="text"
                           id="default_preset"
                           name="cloudflare_meet_settings[default_preset]"
                           value="<?php echo esc_attr($current_preset); ?>"
                           class="regular-text" />
                    <p class="description"><?php _e('Default preset for meeting participants', 'cloudflare-meet'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="max_participants"><?php _e('Default Max Participants', 'cloudflare-meet'); ?></label>
                </th>
                <td>
                    <?php $max_participants = $settings['max_participants'] ?? 10; ?>
                    <input type="number"
                           id="max_participants"
                           name="cloudflare_meet_settings[max_participants]"
                           value="<?php echo esc_attr($max_participants); ?>"
                           min="1"
                           max="100"
                           class="small-text" />
                    <p class="description"><?php _e('Default maximum number of participants per meeting', 'cloudflare-meet'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="auto_record"><?php _e('Auto Record Meetings', 'cloudflare-meet'); ?></label>
                </th>
                <td>
                    <?php $auto_record = $settings['auto_record'] ?? false; ?>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php _e('Auto Record Meetings', 'cloudflare-meet'); ?></span></legend>
                        <label for="auto_record">
                            <input type="checkbox"
                                   id="auto_record"
                                   name="cloudflare_meet_settings[auto_record]"
                                   value="1"
                                <?php checked($auto_record, true); ?> />
                            <?php _e('Automatically start recording when meetings begin', 'cloudflare-meet'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            </tbody>
        </table>

        <?php submit_button(); ?>
    </form>

    <!-- API Connection Test -->
    <div class="cf-api-test" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
        <h2><?php _e('API Connection Test', 'cloudflare-meet'); ?></h2>
        <p><?php _e('Test your RealtimeKit API connection to ensure everything is configured correctly.', 'cloudflare-meet'); ?></p>

        <div id="cf-api-test-results" style="margin: 15px 0;">
            <p><?php _e('Click the button below to test your API connection.', 'cloudflare-meet'); ?></p>
        </div>

        <button type="button" id="cf-test-api-connection" class="button button-secondary">
            <?php _e('Test API Connection', 'cloudflare-meet'); ?>
        </button>
    </div>

    <!-- Setup Guide -->
    <div class="cf-setup-guide" style="margin-top: 30px; padding: 20px; background: #f0f6fc; border: 1px solid #c3d9ef;">
        <h2><?php _e('Setup Guide', 'cloudflare-meet'); ?></h2>
        <ol>
            <li><?php _e('Sign up for a Cloudflare account and enable RealtimeKit', 'cloudflare-meet'); ?></li>
            <li><?php _e('Get your Organization ID and API Key from the RealtimeKit dashboard', 'cloudflare-meet'); ?></li>
            <li><?php _e('Enter your credentials above and save the settings', 'cloudflare-meet'); ?></li>
            <li><?php _e('Test the API connection to ensure everything works', 'cloudflare-meet'); ?></li>
            <li><?php _e('Create your first meeting using the shortcode [cloudflare_meet]', 'cloudflare-meet'); ?></li>
        </ol>

        <p>
            <a href="https://developers.cloudflare.com/realtime/" target="_blank" class="button button-primary">
                <?php _e('View RealtimeKit Documentation', 'cloudflare-meet'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=cloudflare-meet'); ?>" class="button">
                <?php _e('Go to Dashboard', 'cloudflare-meet'); ?>
            </a>
        </p>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        $('#cf-test-api-connection').click(function() {
            const button = $(this);
            const results = $('#cf-api-test-results');

            button.prop('disabled', true).text('<?php _e('Testing...', 'cloudflare-meet'); ?>');
            results.html('<p><?php _e('Testing API connection...', 'cloudflare-meet'); ?></p>');

            $.post(ajaxurl, {
                action: 'cloudflare_test_api',
                nonce: '<?php echo wp_create_nonce('cloudflare_meet_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    results.html(
                        '<div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 4px;">' +
                        '<strong>✅ ' + response.data.message + '</strong>' +
                        (response.data.presets_count ? '<br><small><?php _e('Found', 'cloudflare-meet'); ?> ' + response.data.presets_count + ' <?php _e('presets', 'cloudflare-meet'); ?></small>' : '') +
                        '</div>'
                    );
                } else {
                    results.html(
                        '<div style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;">' +
                        '<strong>❌ ' + response.data + '</strong>' +
                        '</div>'
                    );
                }
            }).fail(function() {
                results.html(
                    '<div style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;">' +
                    '<strong>❌ <?php _e('Connection test failed', 'cloudflare-meet'); ?></strong>' +
                    '</div>'
                );
            }).always(function() {
                button.prop('disabled', false).text('<?php _e('Test API Connection', 'cloudflare-meet'); ?>');
            });
        });
    });
</script>

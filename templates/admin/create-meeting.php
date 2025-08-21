<?php
/**
 * Create Meeting Template
 *
 * WordPress Template Basics:
 * - Always check ABSPATH for security
 * - Use WordPress functions for forms and security
 * - Include nonces for AJAX security
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// WordPress admin page wrapper
?>

<div class="wrap">
    <!-- WordPress standard admin page header -->
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Back to meetings list link -->
    <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-meetings'); ?>" class="button">
        &larr; <?php _e('Back to Meetings', 'cloudflare-meet'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Meeting Creation Form -->
    <div class="cf-create-meeting-container">
        <div class="cf-form-section">
            <h2><?php _e('Create New Meeting', 'cloudflare-meet'); ?></h2>

            <!-- Status Messages (hidden by default) -->
            <div id="cf-create-status" class="notice" style="display: none;">
                <p></p>
            </div>

            <!-- WordPress-style form -->
            <form id="cf-create-meeting-form" class="cf-meeting-form">
                <!-- WordPress nonce for security -->
                <?php wp_nonce_field('cloudflare_create_meeting', 'cf_create_nonce'); ?>

                <table class="form-table">
                    <tbody>
                    <!-- Meeting Title -->
                    <tr>
                        <th scope="row">
                            <label for="meeting_title"><?php _e('Meeting Title', 'cloudflare-meet'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="meeting_title"
                                   name="meeting_title"
                                   class="regular-text"
                                   placeholder="<?php _e('Enter meeting title', 'cloudflare-meet'); ?>"
                                   required>
                            <p class="description">
                                <?php _e('Enter a descriptive title for your meeting', 'cloudflare-meet'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Meeting Description -->
                    <tr>
                        <th scope="row">
                            <label for="meeting_description"><?php _e('Description', 'cloudflare-meet'); ?></label>
                        </th>
                        <td>
                                <textarea id="meeting_description"
                                          name="meeting_description"
                                          class="large-text"
                                          rows="3"
                                          placeholder="<?php _e('Optional meeting description', 'cloudflare-meet'); ?>"></textarea>
                            <p class="description">
                                <?php _e('Optional description for the meeting', 'cloudflare-meet'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Max Participants -->
<!--                    <tr>-->
<!--                        <th scope="row">-->
<!--                            <label for="max_participants">--><?php //_e('Max Participants', 'cloudflare-meet'); ?><!--</label>-->
<!--                        </th>-->
<!--                        <td>-->
<!--                            <input type="number"-->
<!--                                   id="max_participants"-->
<!--                                   name="max_participants"-->
<!--                                   class="small-text"-->
<!--                                   value="10"-->
<!--                                   min="2"-->
<!--                                   max="100">-->
<!--                            <p class="description">-->
<!--                                --><?php //_e('Maximum number of participants (2-100)', 'cloudflare-meet'); ?>
<!--                            </p>-->
<!--                        </td>-->
<!--                    </tr>-->

                    <!-- Auto Record -->
                    <tr>
                        <th scope="row">
                            <?php _e('Recording Options', 'cloudflare-meet'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="auto_record">
                                    <input type="checkbox"
                                           id="auto_record"
                                           name="auto_record"
                                           value="1">
                                    <?php _e('Auto-start recording when meeting begins', 'cloudflare-meet'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <!-- Meeting Type -->
                    <tr>
                        <th scope="row">
                            <label for="meeting_type"><?php _e('Meeting Type', 'cloudflare-meet'); ?></label>
                        </th>
                        <td>
                            <select id="meeting_type" name="meeting_type" class="regular-text">
                                <option value="public"><?php _e('Public (Anyone with link can join)', 'cloudflare-meet'); ?></option>
                                <option value="private"><?php _e('Private (Invitation only)', 'cloudflare-meet'); ?></option>
                                <option value="waiting_room"><?php _e('Waiting Room (Host approval required)', 'cloudflare-meet'); ?></option>
                            </select>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <!-- Submit Button Section -->
                <p class="submit">
                    <button type="submit" class="button button-primary button-large" id="cf-create-btn">
                        <span class="cf-btn-text"><?php _e('Create Meeting', 'cloudflare-meet'); ?></span>
                        <span class="cf-btn-loading" style="display: none;">
                            <span class="spinner is-active"></span>
                            <?php _e('Creating...', 'cloudflare-meet'); ?>
                        </span>
                    </button>

                    <button type="button" class="button button-secondary" onclick="history.back()">
                        <?php _e('Cancel', 'cloudflare-meet'); ?>
                    </button>
                </p>
            </form>
        </div>

        <!-- Meeting Preview Section (shows after creation) -->
        <div id="cf-meeting-preview" class="cf-form-section" style="display: none;">
            <h2><?php _e('Meeting Created Successfully!', 'cloudflare-meet'); ?></h2>

            <div class="cf-meeting-info">
                <div class="cf-info-card">
                    <h3><?php _e('Meeting Details', 'cloudflare-meet'); ?></h3>
                    <p><strong><?php _e('Meeting ID:', 'cloudflare-meet'); ?></strong> <span id="cf-meeting-id"></span></p>
                    <p><strong><?php _e('Join URL:', 'cloudflare-meet'); ?></strong> <br>
                        <input type="text" id="cf-join-url" class="large-text" readonly>
                        <button type="button" class="button" onclick="copyToClipboard('cf-join-url')">
                            <?php _e('Copy', 'cloudflare-meet'); ?>
                        </button>
                    </p>
                </div>

                <div class="cf-actions">
                    <a href="#" id="cf-start-meeting" class="button button-primary button-large">
                        <?php _e('Start Meeting Now', 'cloudflare-meet'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=cloudflare-meet-meetings'); ?>" class="button">
                        <?php _e('Back to Meetings', 'cloudflare-meet'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WordPress Admin JavaScript -->
<script>
    jQuery(document).ready(function($) {
        // Handle form submission
        $('#cf-create-meeting-form').on('submit', function(e) {
            e.preventDefault();
            createMeeting();
        });

        function createMeeting() {
            const $form = $('#cf-create-meeting-form');
            const $btn = $('#cf-create-btn');
            const $status = $('#cf-create-status');

            // Show loading state
            $btn.prop('disabled', true);
            $('.cf-btn-text').hide();
            $('.cf-btn-loading').show();

            // Prepare form data
            const formData = {
                action: 'cloudflare_create_meeting',
                nonce: $('#cf_create_nonce').val(),
                meeting_title: $('#meeting_title').val(),
                meeting_description: $('#meeting_description').val(),
                max_participants: $('#max_participants').val(),
                auto_record: $('#auto_record').is(':checked'),
                meeting_type: $('#meeting_type').val()
            };

            // Make AJAX request
            $.post(ajaxurl, formData)
                .done(function(response) {
                    if (response.success) {
                        // Show success and meeting details
                        showMeetingCreated(response.data);
                        $status.removeClass('notice-error').addClass('notice-success').show();
                        $status.find('p').text('<?php _e('Meeting created successfully!', 'cloudflare-meet'); ?>');
                    } else {
                        // Show error
                        $status.removeClass('notice-success').addClass('notice-error').show();
                        $status.find('p').text(response.data || '<?php _e('Failed to create meeting', 'cloudflare-meet'); ?>');
                    }
                })
                .fail(function() {
                    // Show connection error
                    $status.removeClass('notice-success').addClass('notice-error').show();
                    $status.find('p').text('<?php _e('Connection error. Please try again.', 'cloudflare-meet'); ?>');
                })
                .always(function() {
                    // Reset button state
                    $btn.prop('disabled', false);
                    $('.cf-btn-text').show();
                    $('.cf-btn-loading').hide();
                });
        }

        function showMeetingCreated(meetingData) {
            // Hide form and show preview
            $('#cf-create-meeting-form').hide();
            $('#cf-meeting-preview').show();

            // Populate meeting details
            $('#cf-meeting-id').text(meetingData.session_id);
            $('#cf-join-url').val(meetingData.join_url);
            $('#cf-start-meeting').attr('href', meetingData.start_url);
        }

        // Copy to clipboard function
        window.copyToClipboard = function(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            document.execCommand('copy');

            // Show feedback
            const $button = $(element).next('button');
            const originalText = $button.text();
            $button.text('<?php _e('Copied!', 'cloudflare-meet'); ?>');
            setTimeout(() => {
                $button.text(originalText);
            }, 2000);
        };
    });
</script>

<!-- WordPress Admin Styles -->
<style>
    .cf-create-meeting-container {
        max-width: 800px;
    }

    .cf-form-section {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .cf-meeting-info {
        margin-top: 20px;
    }

    .cf-info-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .cf-actions {
        text-align: center;
    }

    .cf-actions .button {
        margin: 0 10px;
    }

    .cf-btn-loading .spinner {
        float: none;
        margin: 0 5px 0 0;
    }

    /* WordPress responsive form styles */
    @media screen and (max-width: 782px) {
        .cf-create-meeting-container {
            margin: 0;
        }

        .cf-form-section {
            margin: 10px 0;
            padding: 15px;
        }
    }
</style>

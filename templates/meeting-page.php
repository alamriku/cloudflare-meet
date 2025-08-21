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
    <!-- RealtimeKit UI Kit Custom Element -->
    <rtk-meeting id="cf-rtk-meeting"></rtk-meeting>

    <!-- Custom Controls Overlay -->
    <div class="cf-video-controls-overlay">
        <div class="cf-custom-controls">
            <button id="cf-leave-meeting" class="cf-btn cf-btn-danger cf-leave-btn">
                <span class="cf-btn-icon">📞</span>
                <?php _e('Leave Meeting', 'cloudflare-meet'); ?>
            </button>

            <button id="cf-toggle-fullscreen" class="cf-btn cf-btn-secondary" title="<?php _e('Toggle Fullscreen', 'cloudflare-meet'); ?>">
                <span class="cf-btn-icon">⛶</span>
            </button>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        // RealtimeKit variables
        let realtimeKitClient = null;
        let meetingElement = null;
        let isInMeeting = false;

        // Wait for RealtimeKit UI to be loaded
        function waitForRealtimeKit() {
            return new Promise((resolve) => {
                if (window.RealtimeKitUILoaded) {
                    resolve();
                    return;
                }

                // Check every 100ms until RealtimeKit is loaded
                const checkInterval = setInterval(() => {
                    if (window.RealtimeKitUILoaded) {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 100);

                // Timeout after 10 seconds
                setTimeout(() => {
                    clearInterval(checkInterval);
                    resolve(); // Resolve anyway to prevent hanging
                }, 10000);
            });
        }

        // Handle join meeting form
        $('#cf-join-meeting-form').on('submit', function(e) {
            e.preventDefault();
            joinMeeting();
        });

        // Copy functionality
        $('.cf-copy-btn').on('click', function() {
            const textToCopy = $(this).data('copy');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    showCopyFeedback($(this));
                }).catch(() => {
                    // Fallback for older browsers
                    fallbackCopyText(textToCopy, $(this));
                });
            } else {
                // Fallback for older browsers
                fallbackCopyText(textToCopy, $(this));
            }
        });

        function showCopyFeedback(button) {
            const originalText = button.text();
            button.text('<?php _e('Copied!', 'cloudflare-meet'); ?>');
            setTimeout(() => {
                button.text(originalText);
            }, 2000);
        }

        function fallbackCopyText(text, button) {
            // Create temporary textarea for older browsers
            const tempTextarea = $('<textarea>');
            $('body').append(tempTextarea);
            tempTextarea.val(text).select();
            document.execCommand('copy');
            tempTextarea.remove();
            showCopyFeedback(button);
        }

        function joinMeeting() {
            const $form = $('#cf-join-meeting-form');
            const $btn = $('#cf-join-btn');
            const $status = $('#cf-meeting-status');

            // Validate form
            const participantName = $('#cf-participant-name').val().trim();
            if (!participantName) {
                showStatus('<?php _e('Please enter your name.', 'cloudflare-meet'); ?>', 'error');
                $('#cf-participant-name').focus();
                return;
            }

            // Show loading state
            $btn.prop('disabled', true);
            $('.cf-btn-text').hide();
            $('.cf-btn-loading').show();

            // Prepare form data
            const formData = {
                action: 'cloudflare_join_meeting',
                nonce: cloudflare_meet.nonce,
                meeting_id: $('#cf-meeting-id').val(),
                participant_name: participantName,
                participant_email: $('#cf-participant-email').val().trim()
            };

            // Make AJAX request
            $.post(cloudflare_meet.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        console.log(response)
                        showStatus(response.data.message || '<?php _e('Successfully joined meeting!', 'cloudflare-meet'); ?>', 'success');

                        // Load RealtimeKit UI with the auth token
                        setTimeout(() => {
                            initializeRealtimeKit(response.data);
                        }, 1000);
                    } else {
                        showStatus(response.data || '<?php _e('Failed to join meeting', 'cloudflare-meet'); ?>', 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    showStatus('<?php _e('Connection error. Please try again.', 'cloudflare-meet'); ?>', 'error');
                })
                .always(function() {
                    // Reset button state
                    $btn.prop('disabled', false);
                    $('.cf-btn-text').show();
                    $('.cf-btn-loading').hide();
                });
        }

        async function initializeRealtimeKit(meetingData) {
            try {
                console.log('Initializing RealtimeKit with data:', meetingData);

                // Wait for RealtimeKit UI to be ready
                await waitForRealtimeKit();

                if (!window.RealtimeKitUILoaded) {
                    throw new Error('RealtimeKit UI failed to load');
                }

                // Hide join form sections
                $('.cf-meeting-header-section').fadeOut(300);
                $('.cf-join-meeting-section').fadeOut(300);
                $('.cf-meeting-details-section').fadeOut(300);

                // Show video interface
                setTimeout(() => {
                    $('#cf-video-interface').fadeIn(300);
                }, 400);

                // Wait for RealtimeKitClient to be available globally
                if (typeof RealtimeKitClient === 'undefined') {
                    throw new Error('RealtimeKitClient not available. Make sure the Web Core is loaded.');
                }

                // Extract auth token from CloudFlare response structure
                const authToken = meetingData.token;

                if (!authToken) {
                    throw new Error('No auth token received from server');
                }

                // Initialize RealtimeKit Client
                console.log('Initializing RealtimeKit Client with token:', authToken);

                realtimeKitClient = await RealtimeKitClient.init({
                    authToken: authToken,
                    defaults: {
                        audio: false, // User will enable manually in UI
                        video: false, // User will enable manually in UI
                    },
                    // Optional: Additional configuration
                    logLevel: 'info',
                });

                console.log('RealtimeKit Client initialized:', realtimeKitClient);

                // Get meeting element
                meetingElement = document.getElementById('cf-rtk-meeting');

                if (!meetingElement) {
                    throw new Error('Meeting element (#cf-rtk-meeting) not found in DOM');
                }

                console.log('Meeting element found:', meetingElement);

                // Configure meeting element
                meetingElement.showSetupScreen = false; // Skip setup screen
                meetingElement.meeting = realtimeKitClient;

                // Set up event listeners for the RealtimeKit component
                setupRealtimeKitEventListeners();

                isInMeeting = true;

                setTimeout(() => {
                    showStatus('<?php _e('Connected to meeting!', 'cloudflare-meet'); ?>', 'success');
                }, 1000);

            } catch (error) {
                console.error('Failed to initialize RealtimeKit:', error);
                showStatus('<?php _e('Failed to start video call: ', 'cloudflare-meet'); ?>' + error.message, 'error');

                // Show join form again
                setTimeout(() => {
                    showJoinForm();
                }, 2000);
            }
        }

        function setupRealtimeKitEventListeners() {
            if (!meetingElement) return;

            console.log('Setting up RealtimeKit event listeners');

            // Listen for meeting events (check RealtimeKit docs for actual event names)
            meetingElement.addEventListener('rtk-participant-joined', (event) => {
                console.log('Participant joined:', event.detail);
                showStatus('<?php _e('A participant joined the meeting', 'cloudflare-meet'); ?>', 'info');
            });

            meetingElement.addEventListener('rtk-participant-left', (event) => {
                console.log('Participant left:', event.detail);
                showStatus('<?php _e('A participant left the meeting', 'cloudflare-meet'); ?>', 'info');
            });

            meetingElement.addEventListener('rtk-meeting-ended', (event) => {
                console.log('Meeting ended:', event.detail);
                handleMeetingEnded();
            });

            meetingElement.addEventListener('rtk-error', (event) => {
                console.error('RealtimeKit error:', event.detail);
                showStatus('<?php _e('Meeting error: ', 'cloudflare-meet'); ?>' + (event.detail?.message || 'Unknown error'), 'error');
            });

            // Additional events that might be available
            meetingElement.addEventListener('rtk-connected', (event) => {
                console.log('RealtimeKit connected:', event.detail);
            });

            meetingElement.addEventListener('rtk-disconnected', (event) => {
                console.log('RealtimeKit disconnected:', event.detail);
                if (isInMeeting) {
                    handleMeetingEnded();
                }
            });
        }

        function handleMeetingEnded() {
            console.log('Handling meeting ended');
            isInMeeting = false;

            showStatus('<?php _e('The meeting has ended.', 'cloudflare-meet'); ?>', 'info');

            setTimeout(() => {
                showJoinForm();
            }, 2000);
        }

        function showJoinForm() {
            console.log('Showing join form');

            // Hide video interface
            $('#cf-video-interface').fadeOut(300);

            // Show join form sections
            setTimeout(() => {
                $('.cf-meeting-header-section').fadeIn(300);
                $('.cf-join-meeting-section').fadeIn(300);
                $('.cf-meeting-details-section').fadeIn(300);
            }, 400);

            // Clean up RealtimeKit
            cleanupRealtimeKit();
        }

        function cleanupRealtimeKit() {
            try {
                if (meetingElement) {
                    meetingElement.meeting = null;
                }

                if (realtimeKitClient) {
                    // Check if disconnect method exists
                    if (typeof realtimeKitClient.disconnect === 'function') {
                        realtimeKitClient.disconnect();
                    }
                    realtimeKitClient = null;
                }
            } catch (error) {
                console.error('Error during RealtimeKit cleanup:', error);
            }
        }

        function showStatus(message, type) {
            const $status = $('#cf-meeting-status');
            $status.removeClass('cf-success cf-error cf-info').addClass('cf-' + type);
            $status.html('<p>' + message + '</p>').show();

            // Auto-hide success/info messages
            if (type === 'success' || type === 'info') {
                setTimeout(() => {
                    $status.fadeOut();
                }, 5000);
            }
        }

        // Leave meeting handler
        $('#cf-leave-meeting').on('click', function() {
            if (confirm('<?php _e('Are you sure you want to leave the meeting?', 'cloudflare-meet'); ?>')) {
                leaveMeeting();
            }
        });

        function leaveMeeting() {
            console.log('User leaving meeting');

            isInMeeting = false;

            showStatus('<?php _e('You have left the meeting.', 'cloudflare-meet'); ?>', 'info');

            setTimeout(() => {
                showJoinForm();
            }, 1000);
        }

        // Toggle fullscreen
        $('#cf-toggle-fullscreen').on('click', function() {
            if (!meetingElement) return;

            try {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                    $(this).find('.cf-btn-icon').text('⛶');
                } else {
                    meetingElement.requestFullscreen();
                    $(this).find('.cf-btn-icon').text('⇱');
                }
            } catch (error) {
                console.error('Fullscreen error:', error);
            }
        });

        // Handle fullscreen change
        $(document).on('fullscreenchange', function() {
            const $fullscreenBtn = $('#cf-toggle-fullscreen');
            if (document.fullscreenElement) {
                $fullscreenBtn.find('.cf-btn-icon').text('⇱');
            } else {
                $fullscreenBtn.find('.cf-btn-icon').text('⛶');
            }
        });

        // Handle page unload/reload
        $(window).on('beforeunload', function() {
            if (isInMeeting) {
                cleanupRealtimeKit();
            }
        });

        // Handle page visibility change (user switches tabs)
        $(document).on('visibilitychange', function() {
            if (document.hidden && isInMeeting) {
                console.log('Page hidden while in meeting');
                // Optional: Show notification that user is still in meeting
            } else if (!document.hidden && isInMeeting) {
                console.log('Page visible while in meeting');
            }
        });

        // Debug: Log when RealtimeKit UI is loaded
        const checkRealtimeKitStatus = setInterval(() => {
            if (window.RealtimeKitUILoaded) {
                console.log('RealtimeKit UI is loaded and ready');
                clearInterval(checkRealtimeKitStatus);
            }
        }, 1000);

        // Clear debug interval after 10 seconds
        setTimeout(() => {
            clearInterval(checkRealtimeKitStatus);
        }, 10000);
    });
</script>

/**
 * Cloudflare Meet Shortcode JavaScript - Separate from main meeting page
 * Only handles shortcode instances, doesn't interfere with existing meeting page functionality
 */

(function($) {
    'use strict';

    // Only initialize if we're NOT on a meeting page (to avoid conflicts)
    if ($('#cf-join-meeting-form').length > 0) {
        console.log('Meeting page detected, skipping shortcode initialization');
        return;
    }

    class CloudflareMeetShortcode {
        constructor(container) {
            this.container = $(container);
            this.containerId = this.container.attr('id');
            this.meeting = null;
            this.meetingElement = null;
            this.authToken = null;
            this.isInMeeting = false;
            this.meetingId = this.container.find('.cf-meeting-id').val();

            console.log('CloudflareMeetShortcode initialized for:', this.containerId);
            this.init();
        }

        init() {
            if (!this.meetingId) {
                this.showStatus('Invalid meeting configuration', 'error');
                return;
            }

            this.bindEvents();
            this.waitForRealtimeKit();
            this.checkExistingSession();
        }

        bindEvents() {
            // Bind form submission for this specific container only
            this.container.find('.cf-shortcode-join-form').on('submit', (e) => {
                e.preventDefault();
                this.handleJoinMeeting();
            });

            // Copy button functionality
            this.container.find('.cf-copy-btn').on('click', (e) => {
                const textToCopy = $(e.target).data('copy');
                this.copyToClipboard(textToCopy);
            });

            // Handle browser/tab close - only if in meeting
            // $(window).on('beforeunload.shortcode-' + this.containerId, () => {
            //     if (this.isInMeeting && this.meeting) {
            //         this.handleLeaveBeforeUnload();
            //     }
            // });
        }

        async waitForRealtimeKit() {
            const maxAttempts = 50;
            let attempts = 0;

            const checkRealtimeKit = () => {
                attempts++;

                const coreLoaded = window.RealtimeKitClient;
                const uiLoaded = customElements.get('rtk-meeting');

                if (coreLoaded && uiLoaded) {
                    console.log('RealtimeKit loaded for shortcode:', this.containerId);
                    return;
                }

                if (attempts < maxAttempts) {
                    setTimeout(checkRealtimeKit, 200);
                } else {
                    console.error('RealtimeKit failed to load for shortcode:', this.containerId);
                    this.showStatus('Failed to load meeting components', 'error');
                }
            };

            checkRealtimeKit();
        }

        /**
         * Check for existing session - with shortcode-specific key
         */
        checkExistingSession() {
            const existingSession = this.getSessionData();

            if (existingSession) {
                console.log('Found existing session for shortcode:', this.containerId, existingSession);
                this.showRejoinOption(existingSession);
            }
        }

        /**
         * Show rejoin option UI - shortcode specific
         */
        showRejoinOption(sessionData) {
            const $joinSection = this.container.find('.cf-shortcode-join-section');
            const $rejoinHtml = `
                <div class="cf-shortcode-rejoin-section" style="margin-bottom: 20px; padding: 15px; background: #e7f3ff; border: 1px solid #b8daff; border-radius: 4px;">
                    <h4>Previous Session Found</h4>
                    <p>You were previously in this meeting as <strong>${sessionData.participant_name}</strong></p>
                    <p>
                        <button type="button" class="cf-rejoin-btn cf-btn cf-btn-primary" style="margin-right: 10px;">
                            Rejoin Meeting
                        </button>
                        <button type="button" class="cf-clear-session-btn cf-btn cf-btn-secondary">
                            Start Fresh
                        </button>
                    </p>
                </div>
            `;

            $joinSection.prepend($rejoinHtml);

            // Bind rejoin events for this container
            this.container.find('.cf-rejoin-btn').on('click', () => this.rejoinExistingSession(sessionData));
            this.container.find('.cf-clear-session-btn').on('click', () => {
                this.clearSession();
                this.container.find('.cf-shortcode-rejoin-section').remove();
                this.showStatus('Session cleared. You can join as a new participant.', 'info');
            });
        }

        /**
         * Rejoin with existing session
         */
        async rejoinExistingSession(sessionData) {
            console.log('Rejoining existing session for shortcode:', this.containerId);

            const $btn = this.container.find('.cf-rejoin-btn');
            this.setButtonLoading($btn, true, 'Rejoining...');

            try {
                await this.initializeRealtimeKit(sessionData.token);
                this.showVideoInterface();
                this.showStatus('Reconnected to meeting', 'success');
                this.container.find('.cf-shortcode-rejoin-section').remove();

            } catch (error) {
                console.error('Rejoin failed for shortcode:', this.containerId, error);
                this.showStatus('Failed to rejoin. Please start a new session.', 'error');
                this.clearSession();
                this.container.find('.cf-shortcode-rejoin-section').remove();
            } finally {
                this.setButtonLoading($btn, false, 'Rejoin Meeting');
            }
        }

        async handleJoinMeeting() {
            const $btn = this.container.find('.cf-shortcode-join-btn');

            // Validate form
            const participantName = this.container.find('.cf-participant-name').val().trim();
            if (!participantName) {
                this.showStatus('Please enter your name.', 'error');
                return;
            }

            // Check if RealtimeKit is loaded
            if (!window.RealtimeKitClient) {
                this.showStatus('Meeting components are still loading. Please wait...', 'error');
                return;
            }

            // Show loading state
            this.setButtonLoading($btn, true);

            try {
                // Use the same AJAX endpoint as the main meeting page
                const authData = await this.getAuthToken({
                    meeting_id: this.meetingId,
                    participant_name: participantName,
                    participant_email: this.container.find('.cf-participant-email').val().trim() || ''
                });

                console.log('Auth data received for shortcode:', this.containerId, authData);

                if (authData.token) {
                    await this.initializeRealtimeKit(authData.token);
                    this.storeSessionData(authData, participantName);
                    this.showVideoInterface();
                    this.showStatus('Successfully joined meeting!', 'success');
                } else {
                    throw new Error('No auth token received');
                }

            } catch (error) {
                console.error('Join meeting error for shortcode:', this.containerId, error);
                this.showStatus(error.message || 'Failed to join meeting', 'error');
            } finally {
                this.setButtonLoading($btn, false);
            }
        }

        // Use the same AJAX call as the main meeting page
        async getAuthToken(formData) {
            if (typeof cloudflare_meet === 'undefined') {
                throw new Error('AJAX configuration missing');
            }

            const response = await $.ajax({
                url: cloudflare_meet.ajax_url,
                method: 'POST',
                data: {
                    action: 'cloudflare_join_meeting', // Same action as main meeting page
                    nonce: cloudflare_meet.nonce,
                    ...formData
                }
            });

            if (!response.success) {
                throw new Error(response.data || 'Failed to get auth token');
            }

            return response.data;
        }

        // Same RealtimeKit initialization as main meeting page
        async initializeRealtimeKit(authToken) {
            try {
                console.log('Initializing RealtimeKit for shortcode:', this.containerId);

                this.meeting = await window.RealtimeKitClient.init({
                    authToken: authToken,
                    defaults: {
                        audio: false,
                        video: false,
                    }
                });

                console.log('RealtimeKit initialized for shortcode:', this.containerId);

                await this.meeting.join();
                console.log('Successfully joined meeting for shortcode:', this.containerId);

                this.isInMeeting = true;
                this.setupMeetingEventListeners();

            } catch (error) {
                console.error('RealtimeKit initialization error for shortcode:', this.containerId, error);
                throw new Error('Failed to initialize meeting: ' + error.message);
            }
        }

        // Same event listeners as main meeting page
        setupMeetingEventListeners() {
            if (!this.meeting || !this.meeting.self) {
                console.error('Meeting object not available for event listeners in shortcode:', this.containerId);
                return;
            }

            console.log('Setting up event listeners for shortcode:', this.containerId);

            this.meeting.self.on('roomLeft', ({ state }) => {
                console.log('Left meeting for shortcode:', this.containerId, 'state:', state);

                let reason = 'user_left';
                switch (state) {
                    case 'ended':
                        reason = 'meeting_ended';
                        break;
                    case 'kicked':
                        reason = 'kicked';
                        break;
                    case 'disconnected':
                        reason = 'disconnected';
                        break;
                    case 'failed':
                        reason = 'failed';
                        break;
                }

                this.handleMeetingLeft(reason);
            });

            this.meeting.self.on('roomJoined', () => {
                console.log('Successfully joined room for shortcode:', this.containerId);
            });

            if (this.meeting.room) {
                this.meeting.room.on('roomEnded', () => {
                    console.log('Meeting ended by host for shortcode:', this.containerId);
                    this.handleMeetingLeft('meeting_ended');
                });
            }
        }

        handleMeetingLeft(reason = 'unknown') {
            console.log('Meeting left for shortcode:', this.containerId, 'Reason:', reason);

            this.clearSession();
            this.isInMeeting = false;

            let message = 'You have left the meeting.';
            switch (reason) {
                case 'meeting_ended':
                    message = 'The meeting has ended.';
                    break;
                case 'disconnected':
                    message = 'Disconnected from meeting.';
                    break;
                case 'user_left':
                    message = 'You have successfully left the meeting.';
                    break;
            }

            this.returnToJoinInterface(message);
        }

        returnToJoinInterface(message) {
            const $videoInterface = this.container.find('.cf-shortcode-video-interface');
            const $joinSection = this.container.find('.cf-shortcode-join-section');
            const $header = this.container.find('.cf-shortcode-meeting-header');
            const $details = this.container.find('.cf-shortcode-details');

            // Hide video interface
            $videoInterface.fadeOut(300);

            // Show join sections again
            setTimeout(() => {
                $header.fadeIn(300);
                $joinSection.fadeIn(300);
                $details.fadeIn(300);

                this.showStatus(message, 'info');

                // Clear form for fresh start
                this.container.find('.cf-participant-name').val('');
                this.container.find('.cf-participant-email').val('');
            }, 400);
        }

        showVideoInterface() {
            console.log('Showing video interface for shortcode:', this.containerId);

            const $videoInterface = this.container.find('.cf-shortcode-video-interface');
            const $joinSection = this.container.find('.cf-shortcode-join-section');
            const $header = this.container.find('.cf-shortcode-meeting-header');
            const $details = this.container.find('.cf-shortcode-details');

            // Hide join sections
            $header.fadeOut(300);
            $joinSection.fadeOut(300);
            $details.fadeOut(300);

            // Show video interface
            setTimeout(() => {
                $videoInterface.fadeIn(300);
                this.initializeRealtimeKitUI();
            }, 400);
        }

        // Same RealtimeKit UI initialization as main meeting page
        initializeRealtimeKitUI() {
            this.meetingElement = this.container.find('.cf-shortcode-rtk-meeting')[0];

            if (!this.meetingElement) {
                console.error('Meeting element not found for shortcode:', this.containerId);
                return;
            }

            if (!this.meeting) {
                console.error('Meeting object not available for shortcode:', this.containerId);
                return;
            }

            console.log('Initializing RealtimeKit UI for shortcode:', this.containerId);

            try {
                setTimeout(() => {
                    this.meetingElement.meeting = this.meeting;
                    this.meetingElement.showSetupScreen = false;
                    this.meetingElement.leaveOnUnmount = false;

                    console.log('RealtimeKit UI initialized for shortcode:', this.containerId);
                }, 1000);

            } catch (error) {
                console.error('RealtimeKit UI initialization error for shortcode:', this.containerId, error);
                this.showStatus('Failed to initialize video interface', 'error');
            }
        }

        // Session management with shortcode-specific keys
        storeSessionData(authData, participantName) {
            const sessionData = {
                token: authData.token,
                participant_id: authData.participant_id || authData.id,
                participant_name: participantName,
                meeting_id: this.meetingId,
                joined_at: new Date().toISOString(),
                shortcode_container: this.containerId // Add container info
            };

            sessionStorage.setItem(this.getSessionKey(), JSON.stringify(sessionData));
            console.log('Session stored for shortcode:', this.containerId, sessionData);
        }

        getSessionData() {
            try {
                const stored = sessionStorage.getItem(this.getSessionKey());
                return stored ? JSON.parse(stored) : null;
            } catch (error) {
                console.error('Error parsing session data for shortcode:', this.containerId, error);
                return null;
            }
        }

        clearSession() {
            sessionStorage.removeItem(this.getSessionKey());
            console.log('Session cleared for shortcode:', this.containerId);
        }

        // Unique session key per shortcode instance
        getSessionKey() {
            return `cloudflare_meet_shortcode_${this.meetingId}_${this.containerId}`;
        }

        handleLeaveBeforeUnload() {
            if (this.isInMeeting && this.meeting) {
                try {
                    this.meeting.leave();
                } catch (error) {
                    console.warn('Error leaving meeting on unload for shortcode:', this.containerId, error);
                }
                this.clearSession();
            }
        }

        // UI helper methods
        setButtonLoading($button, loading, text = null) {
            if (loading) {
                $button.prop('disabled', true);
                $button.find('.cf-btn-text').hide();
                $button.find('.cf-btn-loading').show();
                if (text) {
                    $button.find('.cf-btn-loading').text(text);
                }
            } else {
                $button.prop('disabled', false);
                $button.find('.cf-btn-text').show();
                $button.find('.cf-btn-loading').hide();
            }
        }

        showStatus(message, type = 'info') {
            const $status = this.container.find('.cf-shortcode-status');
            $status.removeClass('cf-success cf-error cf-info cf-warning')
                .addClass('cf-' + type)
                .html('<p>' + message + '</p>')
                .show();

            if (type === 'success' || type === 'info') {
                setTimeout(() => {
                    $status.fadeOut();
                }, 5000);
            }
        }

        copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showStatus('Copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                this.showStatus('Copied to clipboard!', 'success');
            }
        }

        // Clean up method
        destroy() {
            // Remove event listeners
            $(window).off('beforeunload.shortcode-' + this.containerId);

            // Leave meeting if in one
            if (this.isInMeeting && this.meeting) {
                try {
                    this.meeting.leave();
                } catch (error) {
                    console.warn('Error leaving meeting during destroy:', error);
                }
            }

            // Clear session
            this.clearSession();

            console.log('Shortcode instance destroyed:', this.containerId);
        }
    }

    // Global shortcode manager - separate from main meeting page
    window.CloudflareMeetShortcodes = {
        instances: new Map(),

        init: function() {
            // Only initialize shortcode containers, not main meeting page
            $('.cloudflare-meet-shortcode-container').each(function() {
                const containerId = $(this).attr('id');
                if (containerId && !CloudflareMeetShortcodes.instances.has(containerId)) {
                    const instance = new CloudflareMeetShortcode(this);
                    CloudflareMeetShortcodes.instances.set(containerId, instance);
                    console.log('Initialized shortcode instance:', containerId);
                }
            });
        },

        getInstance: function(containerId) {
            return CloudflareMeetShortcodes.instances.get(containerId);
        },

        destroyInstance: function(containerId) {
            const instance = CloudflareMeetShortcodes.instances.get(containerId);
            if (instance) {
                instance.destroy();
                CloudflareMeetShortcodes.instances.delete(containerId);
            }
        },

        destroyAll: function() {
            CloudflareMeetShortcodes.instances.forEach((instance, containerId) => {
                instance.destroy();
            });
            CloudflareMeetShortcodes.instances.clear();
        }
    };

    // Initialize when DOM is ready - only if shortcodes exist
    $(document).ready(function() {
        if ($('.cloudflare-meet-shortcode-container').length > 0) {
            console.log('Initializing Cloudflare Meet Shortcodes');
            window.CloudflareMeetShortcodes.init();
        }
    });

    // Also initialize on AJAX content load (for dynamic content)
    $(document).ajaxComplete(function() {
        if ($('.cloudflare-meet-shortcode-container').length > 0) {
            window.CloudflareMeetShortcodes.init();
        }
    });

})(jQuery);

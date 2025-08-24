/**
 * Cloudflare Meet Frontend JavaScript - Complete Session Management with Leave Handling
 */

(function($) {
    'use strict';

    class CloudflareMeetClient {
        constructor() {
            this.meeting = null; // RealtimeKit Core meeting object
            this.meetingElement = null; // UI element
            this.authToken = null;
            this.isInMeeting = false;
            this.meetingId = null;

            console.log('CloudflareMeetClient initialized');
            this.init();
        }

        init() {
            this.meetingId = $('#cf-meeting-id').val();
            this.bindEvents();
            this.waitForRealtimeKit();

            // Check for existing session on page load
            this.checkExistingSession();
        }

        bindEvents() {
            $(document).on('submit', '#cf-join-meeting-form', (e) => {
                console.log('Form submitted');
                e.preventDefault();
                this.handleJoinMeeting();
            });

            // Handle browser/tab close
            // $(window).on('beforeunload', () => {
            //     this.handleLeaveBeforeUnload();
            // });
        }

        async waitForRealtimeKit() {
            const maxAttempts = 50;
            let attempts = 0;

            const checkRealtimeKit = () => {
                attempts++;

                // Check if both Core SDK and UI Kit are loaded
                const coreLoaded = window.RealtimeKitClient;
                const uiLoaded = customElements.get('rtk-meeting');

                if (coreLoaded && uiLoaded) {
                    console.log('Both RealtimeKit Core and UI Kit loaded');
                    return;
                }

                if (attempts < maxAttempts) {
                    setTimeout(checkRealtimeKit, 200);
                } else {
                    console.error('RealtimeKit failed to load after', maxAttempts * 200, 'ms');
                    console.error('Core loaded:', !!coreLoaded, 'UI loaded:', !!uiLoaded);
                }
            };

            checkRealtimeKit();
        }

        /**
         * Check for existing session on page load
         */
        checkExistingSession() {
            const existingSession = this.getSessionData();

            if (existingSession) {
                console.log('Found existing session:', existingSession);
                this.showRejoinOption(existingSession);
            }
        }

        /**
         * Show rejoin option UI
         */
        showRejoinOption(sessionData) {
            const $joinSection = $('.cf-join-meeting-section');
            const $rejoinHtml = `
                <div class="cf-rejoin-section" style="margin-bottom: 20px; padding: 15px; background: #e7f3ff; border: 1px solid #b8daff; border-radius: 4px;">
                    <h3>Previous Session Found</h3>
                    <p>You were previously in this meeting as <strong>${sessionData.participant_name}</strong></p>
                    <p>
                        <button type="button" id="cf-rejoin-btn" class="cf-btn cf-btn-primary">
                            Rejoin Meeting
                        </button>
                        <button type="button" id="cf-clear-session-btn" class="cf-btn cf-btn-secondary">
                            Start Fresh
                        </button>
                    </p>
                </div>
            `;

            $joinSection.prepend($rejoinHtml);

            // Bind rejoin events
            $('#cf-rejoin-btn').on('click', () => this.rejoinExistingSession(sessionData));
            $('#cf-clear-session-btn').on('click', () => {
                this.clearSession();
                $('.cf-rejoin-section').remove();
                this.showStatus('Session cleared. You can join as a new participant.', 'info');
            });
        }

        /**
         * Rejoin with existing session
         */
        async rejoinExistingSession(sessionData) {
            console.log('Rejoining existing session');

            const $btn = $('#cf-rejoin-btn');
            this.setButtonLoading($btn, true, 'Rejoining...');

            try {
                await this.initializeRealtimeKit(sessionData.token);
                this.showVideoInterface();
                this.showStatus('Reconnected to meeting', 'success');
                $('.cf-rejoin-section').remove();

            } catch (error) {
                console.error('Rejoin failed:', error);
                this.showStatus('Failed to rejoin. Please start a new session.', 'error');
                this.clearSession();
                $('.cf-rejoin-section').remove();
            } finally {
                this.setButtonLoading($btn, false, 'Rejoin Meeting');
            }
        }

        async handleJoinMeeting() {
            const $btn = $('#cf-join-btn');

            // Validate form
            const participantName = $('#cf-participant-name').val().trim();
            if (!participantName) {
                this.showStatus('Please enter your name.', 'error');
                return;
            }

            // Check if RealtimeKit is loaded
            if (!window.RealtimeKitClient) {
                this.showStatus('RealtimeKit is still loading. Please wait...', 'error');
                return;
            }

            // Show loading state
            this.setButtonLoading($btn, true);

            try {
                // Get auth token from server
                const authData = await this.getAuthToken({
                    meeting_id: this.meetingId,
                    participant_name: participantName,
                    participant_email: $('#cf-participant-email').val().trim() || ''
                });

                console.log('Auth data received:', authData);

                if (authData.token) {
                    // Initialize RealtimeKit Core with auth token
                    await this.initializeRealtimeKit(authData.token);

                    // Store session data
                    this.storeSessionData(authData, participantName);

                    // Show UI with meeting object
                    this.showVideoInterface();
                    this.showStatus('Successfully joined meeting!', 'success');
                } else {
                    throw new Error('No auth token received');
                }

            } catch (error) {
                console.error('Join meeting error:', error);
                this.showStatus(error.message || 'Failed to join meeting', 'error');
            } finally {
                this.setButtonLoading($btn, false);
            }
        }

        /**
         * Store session data in sessionStorage
         */
        storeSessionData(authData, participantName) {
            const sessionData = {
                token: authData.token,
                participant_id: authData.participant_id || authData.id,
                participant_name: participantName,
                meeting_id: this.meetingId,
                joined_at: new Date().toISOString()
            };

            sessionStorage.setItem(this.getSessionKey(), JSON.stringify(sessionData));
            console.log('Session stored:', sessionData);
        }

        /**
         * Get session data from sessionStorage
         */
        getSessionData() {
            try {
                const stored = sessionStorage.getItem(this.getSessionKey());
                return stored ? JSON.parse(stored) : null;
            } catch (error) {
                console.error('Error parsing session data:', error);
                return null;
            }
        }

        /**
         * Clear session data
         */
        clearSession() {
            sessionStorage.removeItem(this.getSessionKey());
            console.log('Session cleared');
        }

        /**
         * Get session storage key
         */
        getSessionKey() {
            return `cloudflare_meet_session_${this.meetingId}`;
        }

        async getAuthToken(formData) {
            if (typeof cloudflare_meet === 'undefined') {
                throw new Error('AJAX configuration missing');
            }

            const response = await $.ajax({
                url: cloudflare_meet.ajax_url,
                method: 'POST',
                data: {
                    action: 'cloudflare_join_meeting',
                    nonce: cloudflare_meet.nonce,
                    ...formData
                }
            });

            if (!response.success) {
                throw new Error(response.data || 'Failed to get auth token');
            }

            return response.data;
        }

        async initializeRealtimeKit(authToken) {
            try {
                console.log('Initializing RealtimeKit Core with token:', authToken);

                // Initialize RealtimeKit Core
                this.meeting = await window.RealtimeKitClient.init({
                    authToken: authToken,
                    defaults: {
                        audio: false,
                        video: false,
                    }
                });

                console.log('RealtimeKit Core initialized, meeting object:', this.meeting);

                // CRITICAL: Join the meeting first before showing UI
                console.log('Joining meeting...');
                await this.meeting.join();

                console.log('Successfully joined meeting');
                // CRITICAL: Set up event listeners BEFORE joining
                this.isInMeeting = true;
                this.setupMeetingEventListeners();
            } catch (error) {
                console.error('RealtimeKit Core initialization error:', error);
                throw new Error('Failed to initialize RealtimeKit: ' + error.message);
            }
        }

        /**
         * Set up RealtimeKit Core event listeners
         * This is the key to handling leave events properly
         */
        /**
         * Set up RealtimeKit Core event listeners
         * This is called AFTER meeting.join() completes
         */
        setupMeetingEventListeners() {
            if (!this.meeting || !this.meeting.self) {
                console.error('Meeting object or meeting.self not available for event listeners');
                return;
            }

            console.log('Setting up RealtimeKit event listeners');

            // Listen for when the local participant leaves the meeting
            this.meeting.self.on('roomLeft', ({ state }) => {
                console.log('Local participant left the meeting, state:', state);

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
                    case 'left':
                    default:
                        reason = 'user_left';
                        break;
                }

                this.handleMeetingLeft(reason);
            });

            // Listen for successful room join
            this.meeting.self.on('roomJoined', () => {
                console.log('Successfully joined the room');
            });

            // Listen for meeting end events (if room object is available)
            if (this.meeting.room) {
                this.meeting.room.on('roomEnded', () => {
                    console.log('Meeting was ended by host');
                    this.handleMeetingLeft('meeting_ended');
                });
            }

            // Listen for network quality issues
            this.meeting.self.on('mediaScoreUpdate', ({ kind, score }) => {
                if (score < 3) {
                    console.warn(`Poor ${kind} quality detected, score:`, score);
                }
            });

            // Listen for permission updates
            if (this.meeting.self.permissions) {
                this.meeting.self.permissions.on('*', () => {
                    console.log('Permissions updated');
                });
            }
        }

        /**
         * Handle when user leaves meeting (from any source)
         * This is called when:
         * 1. User clicks leave button in UI
         * 2. Host ends meeting
         * 3. Network disconnection
         * 4. Browser tab close
         */
        handleMeetingLeft(reason = 'unknown') {
            console.log('Meeting left. Reason:', reason);

            // Clear session storage immediately
            this.clearSession();

            // Update UI state
            this.isInMeeting = false;

            // Show appropriate message based on reason
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

            // Hide video interface and show join form
            this.returnToJoinInterface(message);

            // Optional: Notify server about leave (for analytics/logging)
           // this.notifyServerOfLeave(reason);
        }

        /**
         * Return to join interface after leaving meeting
         */
        returnToJoinInterface(message) {
            // Hide video interface
            $('#cf-video-interface').fadeOut(300);

            // Show join form sections again
            setTimeout(() => {
                $('.cf-meeting-header-section').fadeIn(300);
                $('.cf-join-meeting-section').fadeIn(300);
                $('.cf-meeting-details-section').fadeIn(300);

                // Show status message
                this.showStatus(message, 'info');

                // Clear form for fresh start (optional)
                $('#cf-participant-name').val('');
                $('#cf-participant-email').val('');
            }, 400);
        }

        /**
         * Handle browser/tab close
         */
        handleLeaveBeforeUnload() {
            if (this.isInMeeting && this.meeting) {
                // Leave the meeting gracefully
                try {
                    this.meeting.leave();
                } catch (error) {
                    console.warn('Error leaving meeting on unload:', error);
                }

                // Clear session
                this.clearSession();
            }
        }

        /**
         * Notify server that participant left (optional)
         */
        // async notifyServerOfLeave(reason) {
        //     if (typeof cloudflare_meet === 'undefined') {
        //         return;
        //     }
        //
        //     try {
        //         await $.ajax({
        //             url: cloudflare_meet.ajax_url,
        //             method: 'POST',
        //             data: {
        //                 action: 'cloudflare_leave_meeting',
        //                 nonce: cloudflare_meet.nonce,
        //                 meeting_id: this.meetingId,
        //                 reason: reason
        //             }
        //         });
        //     } catch (error) {
        //         console.warn('Failed to notify server of leave:', error);
        //     }
        // }

        showVideoInterface() {
            console.log('Showing video interface');

            // Hide join form sections
            $('.cf-meeting-header-section').fadeOut(300);
            $('.cf-join-meeting-section').fadeOut(300);
            $('.cf-meeting-details-section').fadeOut(300);

            // Show video interface
            setTimeout(() => {
                $('#cf-video-interface').fadeIn(300);
                this.initializeRealtimeKitUI();
            }, 400);
        }

        initializeRealtimeKitUI() {
            this.meetingElement = document.getElementById('cf-rtk-meeting');

            if (!this.meetingElement) {
                console.error('Meeting element not found');
                return;
            }

            if (!this.meeting) {
                console.error('Meeting object not available');
                return;
            }

            console.log('Assigning meeting object to UI element');

            try {
                // Wait a bit for the element to be fully rendered
                setTimeout(() => {
                    this.meetingElement.meeting = this.meeting;
                    this.meetingElement.showSetupScreen = false;

                    // Set leaveOnUnmount to false so we handle leave manually
                    this.meetingElement.leaveOnUnmount = false;

                    console.log('RealtimeKit UI initialized successfully');

                    // The leave button in the UI will now trigger the 'roomLeft' event
                    // which we're already listening for in setupMeetingEventListeners()

                }, 1000);

            } catch (error) {
                console.error('RealtimeKit UI initialization error:', error);
                this.showStatus('Failed to initialize video interface', 'error');
            }
        }

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
            const $status = $('#cf-meeting-status');
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
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('#cf-join-meeting-form').length > 0) {
            console.log('Meeting form found, initializing client');
            window.CloudflareMeetClient = new CloudflareMeetClient();
        }
    });

})(jQuery);

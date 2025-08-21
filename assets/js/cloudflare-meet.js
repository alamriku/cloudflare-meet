/**
 * Cloudflare Meet Frontend JavaScript
 * Integrates with RealtimeKit SDK
 */

(function($) {
    'use strict';

    // Import RealtimeKit from CDN (loaded in PHP)
    const { RealtimeKitClient } = window.RealtimeKit || {};

    class CloudflareMeet {
        constructor() {
            this.meeting = null;
            this.authToken = null;
            this.meetingId = null;
            this.isHost = false;
            this.localMediaEnabled = {
                video: false,
                audio: false
            };
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.checkExistingMeeting();
        }

        bindEvents() {
            $(document).on('click', '.cf-create-meeting', this.createMeeting.bind(this));
            $(document).on('click', '.cf-join-meeting', this.joinMeeting.bind(this));
            $(document).on('click', '.cf-leave-meeting', this.leaveMeeting.bind(this));
            $(document).on('click', '.cf-end-meeting', this.endMeeting.bind(this));
            $(document).on('click', '.cf-toggle-video', this.toggleVideo.bind(this));
            $(document).on('click', '.cf-toggle-audio', this.toggleAudio.bind(this));
            $(document).on('click', '.cf-share-screen', this.toggleScreenShare.bind(this));
            $(document).on('click', '.cf-toggle-recording', this.toggleRecording.bind(this));
        }

        async createMeeting(event) {
            event.preventDefault();
            
            const $button = $(event.currentTarget);
            const $container = $button.closest('.cloudflare-meet-container');
            
            try {
                this.showStatus($container, cloudflare_meet.strings.creating_meeting, 'info');
                $button.prop('disabled', true);

                const response = await $.ajax({
                    url: cloudflare_meet.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'cloudflare_create_meeting',
                        nonce: cloudflare_meet.nonce,
                        room_name: $container.data('room') || 'Meeting Room',
                        title: $container.data('title') || 'WordPress Meeting',
                        record_on_start: $container.data('record') || false
                    }
                });

                if (response.success) {
                    this.authToken = response.data.auth_token;
                    this.meetingId = response.data.meeting_id;
                    this.isHost = true;

                    await this.initializeRealtimeKit($container);
                    await this.joinRoom();
                    
                    this.showMeetingInterface($container);
                    this.showStatus($container, cloudflare_meet.strings.meeting_created, 'success');
                } else {
                    this.showStatus($container, response.data, 'error');
                }
            } catch (error) {
                console.error('Create meeting error:', error);
                this.showStatus($container, cloudflare_meet.strings.error_occurred, 'error');
            } finally {
                $button.prop('disabled', false);
            }
        }

        async joinMeeting(event) {
            event.preventDefault();
            
            const $button = $(event.currentTarget);
            const $container = $button.closest('.cloudflare-meet-container');
            const meetingId = $container.find('.cf-meeting-id-input').val();
            const participantName = $container.find('.cf-participant-name-input').val() || 'Guest';
            
            if (!meetingId) {
                this.showStatus($container, cloudflare_meet.strings.meeting_id_required, 'error');
                return;
            }

            try {
                this.showStatus($container, cloudflare_meet.strings.joining_meeting, 'info');
                $button.prop('disabled', true);

                const response = await $.ajax({
                    url: cloudflare_meet.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'cloudflare_join_meeting',
                        nonce: cloudflare_meet.nonce,
                        meeting_id: meetingId,
                        participant_name: participantName
                    }
                });

                if (response.success) {
                    this.authToken = response.data.auth_token;
                    this.meetingId = response.data.meeting_id;
                    this.isHost = false;

                    await this.initializeRealtimeKit($container);
                    await this.joinRoom();
                    
                    this.showMeetingInterface($container);
                    this.showStatus($container, cloudflare_meet.strings.joined_meeting, 'success');
                } else {
                    this.showStatus($container, response.data, 'error');
                }
            } catch (error) {
                console.error('Join meeting error:', error);
                this.showStatus($container, cloudflare_meet.strings.error_occurred, 'error');
            } finally {
                $button.prop('disabled', false);
            }
        }

        async initializeRealtimeKit($container) {
            if (!RealtimeKitClient) {
                throw new Error('RealtimeKit SDK not loaded');
            }

            this.meeting = await RealtimeKitClient.init({
                authToken: this.authToken,
                defaults: {
                    audio: false,
                    video: false,
                }
            });

            this.setupEventListeners($container);
        }

        setupEventListeners($container) {
            // Self events
            this.meeting.self.on('roomJoined', () => {
                console.log('Room joined successfully');
                this.updateMeetingInfo($container);
            });

            this.meeting.self.on('roomLeft', () => {
                console.log('Left the room');
                this.hideMeetingInterface($container);
                this.showStatus($container, cloudflare_meet.strings.meeting_ended, 'info');
            });

            this.meeting.self.on('mediaUpdate', (update) => {
                this.handleSelfMediaUpdate($container, update);
            });

            // Participant events
            this.meeting.participants.joined.on('participantJoined', (participant) => {
                this.handleParticipantJoined($container, participant);
            });

            this.meeting.participants.joined.on('participantLeft', (participant) => {
                this.handleParticipantLeft($container, participant);
            });

            this.meeting.participants.joined.on('videoUpdate', (participant) => {
                this.handleParticipantVideoUpdate($container, participant);
            });

            this.meeting.participants.joined.on('audioUpdate', (participant) => {
                this.handleParticipantAudioUpdate($container, participant);
            });

            // Recording events
            this.meeting.recording.on('recordingStarted', () => {
                this.updateRecordingStatus($container, true);
            });

            this.meeting.recording.on('recordingStopped', () => {
                this.updateRecordingStatus($container, false);
            });

            // AI events (if enabled)
            if (this.meeting.ai) {
                this.meeting.ai.on('transcriptUpdate', (transcript) => {
                    this.handleTranscriptUpdate($container, transcript);
                });
            }
        }

        async joinRoom() {
            await this.meeting.join();
        }

        async leaveMeeting(event) {
            event.preventDefault();
            
            if (this.meeting) {
                await this.meeting.leave();
                this.meeting = null;
                this.authToken = null;
                this.meetingId = null;
            }
        }

        async endMeeting(event) {
            event.preventDefault();
            
            if (!this.isHost || !this.meetingId) {
                return;
            }

            try {
                const response = await $.ajax({
                    url: cloudflare_meet.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'cloudflare_end_meeting',
                        nonce: cloudflare_meet.nonce,
                        meeting_id: this.meetingId
                    }
                });

               if (response.success) {
                   if (this.meeting) {
                       await this.meeting.leave();
                   }
                   this.meeting = null;
                   this.authToken = null;
                   this.meetingId = null;
               } else {
                   console.error('End meeting error:', response.data);
               }
           } catch (error) {
               console.error('End meeting error:', error);
           }
       }

       async toggleVideo(event) {
           event.preventDefault();
           
           if (!this.meeting) return;

           try {
               if (this.localMediaEnabled.video) {
                   await this.meeting.self.disableVideo();
               } else {
                   await this.meeting.self.enableVideo();
               }
           } catch (error) {
               console.error('Toggle video error:', error);
           }
       }

       async toggleAudio(event) {
           event.preventDefault();
           
           if (!this.meeting) return;

           try {
               if (this.localMediaEnabled.audio) {
                   await this.meeting.self.disableAudio();
               } else {
                   await this.meeting.self.enableAudio();
               }
           } catch (error) {
               console.error('Toggle audio error:', error);
           }
       }

       async toggleScreenShare(event) {
           event.preventDefault();
           
           if (!this.meeting) return;

           try {
               if (this.meeting.self.screenShareEnabled) {
                   await this.meeting.self.disableScreenShare();
               } else {
                   await this.meeting.self.enableScreenShare();
               }
           } catch (error) {
               console.error('Toggle screen share error:', error);
           }
       }

       async toggleRecording(event) {
           event.preventDefault();
           
           if (!this.meeting || !this.isHost) return;

           try {
               if (this.meeting.recording.recordingState === 'RECORDING') {
                   await this.meeting.recording.stop();
               } else {
                   await this.meeting.recording.start();
               }
           } catch (error) {
               console.error('Toggle recording error:', error);
           }
       }

       handleSelfMediaUpdate($container, update) {
           this.localMediaEnabled.video = update.videoEnabled;
           this.localMediaEnabled.audio = update.audioEnabled;
           
           // Update button states
           $container.find('.cf-toggle-video')
               .toggleClass('active', update.videoEnabled)
               .text(update.videoEnabled ? 'Turn Off Video' : 'Turn On Video');
               
           $container.find('.cf-toggle-audio')
               .toggleClass('active', update.audioEnabled)
               .text(update.audioEnabled ? 'Mute' : 'Unmute');

           // Update local video
           const $localVideo = $container.find('.cf-local-video');
           if (update.videoTrack) {
               $localVideo[0].srcObject = new MediaStream([update.videoTrack]);
               $localVideo.show();
           } else {
               $localVideo.hide();
           }
       }

       handleParticipantJoined($container, participant) {
           console.log('Participant joined:', participant);
           
           const $participantContainer = $(`
               <div class="cf-participant" data-participant-id="${participant.id}">
                   <div class="cf-participant-video-container">
                       <video class="cf-participant-video" autoplay playsinline></video>
                       <div class="cf-participant-info">
                           <span class="cf-participant-name">${participant.name}</span>
                           <div class="cf-participant-status">
                               <span class="cf-audio-status">🎤</span>
                               <span class="cf-video-status">📹</span>
                           </div>
                       </div>
                   </div>
               </div>
           `);
           
           $container.find('.cf-participants-grid').append($participantContainer);
           this.updateParticipantCount($container);
       }

       handleParticipantLeft($container, participant) {
           console.log('Participant left:', participant);
           
           $container.find(`[data-participant-id="${participant.id}"]`).remove();
           this.updateParticipantCount($container);
       }

       handleParticipantVideoUpdate($container, participant) {
           const $participant = $container.find(`[data-participant-id="${participant.id}"]`);
           const $video = $participant.find('.cf-participant-video');
           const $videoStatus = $participant.find('.cf-video-status');
           
           if (participant.videoTrack) {
               $video[0].srcObject = new MediaStream([participant.videoTrack]);
               $video.show();
               $videoStatus.text('📹').addClass('active');
           } else {
               $video.hide();
               $videoStatus.text('📹').removeClass('active');
           }
       }

       handleParticipantAudioUpdate($container, participant) {
           const $participant = $container.find(`[data-participant-id="${participant.id}"]`);
           const $audioStatus = $participant.find('.cf-audio-status');
           
           if (participant.audioEnabled) {
               $audioStatus.text('🎤').addClass('active');
           } else {
               $audioStatus.text('🔇').removeClass('active');
           }
       }

       handleTranscriptUpdate($container, transcript) {
           const $transcriptContainer = $container.find('.cf-transcript');
           if ($transcriptContainer.length) {
               $transcriptContainer.append(`
                   <div class="cf-transcript-entry">
                       <strong>${transcript.participantName}:</strong> ${transcript.text}
                   </div>
               `);
               $transcriptContainer.scrollTop($transcriptContainer[0].scrollHeight);
           }
       }

       updateRecordingStatus($container, isRecording) {
           const $recordingButton = $container.find('.cf-toggle-recording');
           const $recordingIndicator = $container.find('.cf-recording-indicator');
           
           if (isRecording) {
               $recordingButton.text('Stop Recording').addClass('recording');
               $recordingIndicator.show().text('🔴 Recording');
           } else {
               $recordingButton.text('Start Recording').removeClass('recording');
               $recordingIndicator.hide();
           }
       }

       updateParticipantCount($container) {
           const count = $container.find('.cf-participant').length + 1; // +1 for self
           $container.find('.cf-participant-count').text(count);
       }

       updateMeetingInfo($container) {
           $container.find('.cf-meeting-id-display').text(this.meetingId);
           this.updateParticipantCount($container);
       }

       showMeetingInterface($container) {
           $container.find('.cf-pre-meeting').hide();
           $container.find('.cf-meeting-interface').show();
           
           // Show appropriate controls based on role
           if (this.isHost) {
               $container.find('.cf-host-controls').show();
           } else {
               $container.find('.cf-host-controls').hide();
           }
       }

       hideMeetingInterface($container) {
           $container.find('.cf-meeting-interface').hide();
           $container.find('.cf-pre-meeting').show();
           $container.find('.cf-participants-grid').empty();
       }

       showStatus($container, message, type = 'info') {
           const $status = $container.find('.cf-status');
           $status.removeClass('info success error warning')
                  .addClass(type)
                  .text(message)
                  .show();
           
           if (type === 'success') {
               setTimeout(() => $status.fadeOut(), 3000);
           }
       }

       checkExistingMeeting() {
           // Check if there's a meeting in progress (from localStorage or URL params)
           const urlParams = new URLSearchParams(window.location.search);
           const meetingId = urlParams.get('meeting_id');
           
           if (meetingId) {
               $('.cf-meeting-id-input').val(meetingId);
           }
       }
   }

   // Initialize when DOM is ready
   $(document).ready(function() {
       window.CloudflareMeet = new CloudflareMeet();
   });

})(jQuery);
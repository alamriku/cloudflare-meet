<?php
declare(strict_types=1);

namespace CloudflareMeet\Core;

use CloudflareMeet\API\RealtimeKitClient;
use CloudflareMeet\Database\DatabaseManager;
use CloudflareMeet\Database\Models\Meeting;
use CloudflareMeet\API\Exceptions\RealtimeKitException;
use CloudflareMeet\Database\Models\Participant;

/**
 * AJAX Handler for frontend requests
 */
class AjaxHandler {
    
    private RealtimeKitClient $api_client;
    private DatabaseManager $database_manager;

    public function __construct(RealtimeKitClient $api_client, DatabaseManager $database_manager) {
        $this->api_client = $api_client;
        $this->database_manager = $database_manager;
    }

    public function registerHooks(): void {
        // Public AJAX actions (for logged-in and non-logged-in users)
        add_action('wp_ajax_cloudflare_create_meeting', [$this, 'createMeeting']);
        add_action('wp_ajax_cloudflare_join_meeting', [$this, 'join_meeting']);
        add_action('wp_ajax_cloudflare_end_meeting', [$this, 'endMeeting']);
        add_action('wp_ajax_cloudflare_test_api', [$this, 'testApiConnection']);
        
        // Private AJAX actions (only for logged-in users)
        add_action('wp_ajax_nopriv_cloudflare_join_meeting', [$this, 'join_meeting']);
    }

    public function createMeeting(): void {
        try {
            check_ajax_referer('cloudflare_meet_nonce', 'nonce');
            error_log('create meeting called');
            if (!is_user_logged_in()) {
                wp_send_json_error(__('You must be logged in to create meetings.', 'cloudflare-meet'));
                return;
            }
            
            $user_id = get_current_user_id();
            $room_name = sanitize_text_field($_POST['room_name'] ?? 'Meeting Room');
            $title = sanitize_text_field($_POST['title'] ?? $room_name);
            $record_on_start = filter_var($_POST['record_on_start'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            // Create meeting via RealtimeKit API
            $meeting_data = $this->api_client->create_meeting([
                'title' => $title,
                'record_on_start' => $record_on_start,
            ]);
            error_log(json_encode($meeting_data));
            // Create host participant
            $host_user = wp_get_current_user();
            $participant_data = $this->api_client->addParticipant($meeting_data['id'], [
                'name' => $host_user->display_name,
                'preset_name' => 'group_call_host',
                'custom_participant_id' => (string) $user_id,
            ]);
            
            // Save meeting to database
            $meeting = new Meeting(
                $user_id,
                $meeting_data['id'],
                $room_name,
                'active',
                1, // host counts as 1 participant
                10, // default max participants
                null,
                current_time('mysql'),
                null
            );
            
            $this->database_manager->createMeeting($meeting);
            
            wp_send_json_success([
                'meeting_id' => $meeting_data['id'],
                'auth_token' => $participant_data['token'],
                'room_name' => $room_name,
                'meeting_data' => $meeting_data,
            ]);
            
        } catch (RealtimeKitException $e) {
            wp_send_json_error($e->getMessage());
        } catch (\Exception $e) {
            wp_send_json_error(__('An error occurred while creating the meeting.', 'cloudflare-meet'));
        }
    }


    public function join_meeting(): void {
        try {
            check_ajax_referer('cloudflare_meet_nonce', 'nonce');

            $meeting_id = sanitize_text_field($_POST['meeting_id'] ?? '');
            $participant_name = sanitize_text_field($_POST['participant_name'] ?? 'Guest');
            $participant_email = sanitize_email($_POST['participant_email'] ?? '');

            if (empty($meeting_id)) {
                wp_send_json_error(__('Meeting ID is required.', 'cloudflare-meet'));
                return;
            }

            if (empty($participant_name)) {
                wp_send_json_error(__('Participant name is required.', 'cloudflare-meet'));
                return;
            }

            // Check if meeting exists and is active
            $meeting = $this->database_manager->get_meeting($meeting_id);
            if (!$meeting || $meeting->getStatus() !== 'active') {
                wp_send_json_error(__('Meeting not found or has ended.', 'cloudflare-meet'));
                return;
            }

            // Check participant limit
            $current_participant_count = $this->database_manager->get_participant_count($meeting_id);
//            if ($current_participant_count >= $meeting->getMaxParticipants()) {
//                wp_send_json_error(__('Meeting is full.', 'cloudflare-meet'));
//                return;
//            }

            $user_id = get_current_user_id();
            // Create participant in RealtimeKit
            $participant_data = $this->api_client->create_regular_participant(
                $meeting_id,
                $participant_name,
                $user_id ?: null,
	            $user_id ? 'group_call_host' : null,
            );

            // Create participant in local database
            $participant = new Participant(
                $meeting_id,
                $participant_name,
                'participant',
                $user_id ?: null,
                $participant_email ?: null,
                $participant_data['custom_participant_id'] ?? null,
                'approved', // Using RealtimeKit permission control
                $participant_data['token'] ?? null
            );

            $this->database_manager->create_participant($participant);

            // Update meeting participant count
            $this->database_manager->update_meeting($meeting_id, [
                'participant_count' => $current_participant_count + 1
            ]);

            // Return response with CloudFlare structure
            wp_send_json_success([
                                     // CloudFlare participant data structure
                                     'id' => $participant_data['id'] ?? '',
                                     'name' => $participant_data['name'] ?? $participant_name,
                                     'picture' => $participant_data['picture'] ?? null,
                                     'custom_participant_id' => $participant_data['custom_participant_id'] ?? '',
                                     'preset_name' => $participant_data['preset_name'] ?? '',
                                     'created_at' => $participant_data['created_at'] ?? '',
                                     'updated_at' => $participant_data['updated_at'] ?? '',
                                     'token' => $participant_data['token'] ?? '', // This is the auth token for RealtimeKit

                                     // Additional data for frontend
                                     'meeting_id' => $meeting_id,
                                     'participant_id' => $participant_data['id'] ?? $participant->get_custom_participant_id(),
                                     'auth_token' => $participant_data['token'] ?? '', // Alias for backward compatibility
                                     'message' => __('Successfully joined meeting!', 'cloudflare-meet')
                                 ]);

        } catch (RealtimeKitException $e) {
            wp_send_json_error($e->getMessage());
        } catch (\Exception $e) {
            wp_send_json_error(__('An error occurred while joining the meeting.', 'cloudflare-meet'));
        }
    }

    public function endMeeting(): void {
        try {
            check_ajax_referer('cloudflare_meet_nonce', 'nonce');
            
            if (!is_user_logged_in()) {
                wp_send_json_error(__('You must be logged in to end meetings.', 'cloudflare-meet'));
                return;
            }
            
            $meeting_id = sanitize_text_field($_POST['meeting_id'] ?? '');
            $user_id = get_current_user_id();
            
            if (empty($meeting_id)) {
                wp_send_json_error(__('Meeting ID is required.', 'cloudflare-meet'));
                return;
            }
            
            // Check if user owns the meeting
            $meeting = $this->database_manager->get_meeting($meeting_id);
            if (!$meeting || $meeting->getUserId() !== $user_id) {
                wp_send_json_error(__('You do not have permission to end this meeting.', 'cloudflare-meet'));
                return;
            }
            
            // Delete meeting from RealtimeKit (this ends it)
            $this->api_client->deleteMeeting($meeting_id);
            
            // Update meeting status in database
            $this->database_manager->update_meeting($meeting_id, [
                'status' => 'ended',
                'ended_at' => current_time('mysql')
            ]);
            
            wp_send_json_success(__('Meeting ended successfully.', 'cloudflare-meet'));
            
        } catch (RealtimeKitException $e) {
            wp_send_json_error($e->getMessage());
        } catch (\Exception $e) {
            wp_send_json_error(__('An error occurred while ending the meeting.', 'cloudflare-meet'));
        }
    }

    public function testApiConnection(): void {
        try {
            check_ajax_referer('cloudflare_meet_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Insufficient permissions.', 'cloudflare-meet'));
                return;
            }
            
            $connected = $this->api_client->testConnection();
            
            if ($connected) {
                wp_send_json_success(__('API connection successful!', 'cloudflare-meet'));
            } else {
                wp_send_json_error(__('API connection failed. Please check your credentials.', 'cloudflare-meet'));
            }
            
        } catch (\Exception $e) {
            wp_send_json_error(__('API connection test failed.', 'cloudflare-meet'));
        }
    }
}

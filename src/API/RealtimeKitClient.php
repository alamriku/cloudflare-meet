<?php
declare(strict_types=1);

namespace CloudflareMeet\API;

use CloudflareMeet\Core\Settings;
use CloudflareMeet\API\Exceptions\RealtimeKitException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Cloudflare RealtimeKit API Client
 */
class RealtimeKitClient {

    // Use the correct RealtimeKit API base URL
    private const API_BASE = 'https://api.realtime.cloudflare.com/v2/';

    private Client $http_client;
    private Settings $settings;
    public $account_id;
    public function __construct(Settings $settings) {
        $this->settings = $settings;
        $this->account_id = $settings->getAccountId();
        $this->http_client = new Client([
                                            'base_uri' => self::API_BASE,
                                            'timeout' => 30,
                                        ]);
    }

    /**
     * @throws RealtimeKitException
     */
    private function get_auth_headers(): array {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(
                    $this->settings->getOrganizationId() . ':' . $this->settings->getApiKey()
                ),
        ];
    }

    /**
     * Test API connection using getAllOrgs endpoint
     */
    /**
     * Test API connection
     */
    public function testConnection(): bool {
        try {
            $response = $this->http_client->get("/accounts/{$this->account_id}/calls/apps");
            $data = json_decode($response->getBody()->getContents(), true);
            error_log(json_encode($data));
            return $data['success'] ?? false;
        } catch (RequestException $e) {
            return false;
        }
    }

    /**
     * Create a new meeting
     * @throws RealtimeKitException
     */
    public function create_meeting(array $params = []): array {
        $headers = $this->get_auth_headers();

        try {
            $response = $this->http_client->post('meetings', [
                'headers' => $headers,
                'json' => $params
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            error_log('create_meeting log' . json_encode($data));
            // Log for debugging
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - Create Meeting - Status: $statusCode, Body: " . substr($body, 0, 500) . "\\n",
                              FILE_APPEND
            );

            if ($statusCode !== 200 && $statusCode !== 201) {
                throw new RealtimeKitException('Failed to create meeting - HTTP ' . $statusCode . ': ' . ($data['message'] ?? 'Unknown error'));
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new RealtimeKitException('Failed to create meeting: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? $data;

        } catch (RequestException $e) {
            $error_msg = 'Create meeting HTTP error: ' . $e->getMessage();
            if ($e->hasResponse()) {
                $error_msg .= ' | Response: ' . $e->getResponse()->getBody()->getContents();
            }
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - $error_msg\\n",
                              FILE_APPEND
            );
            throw new RealtimeKitException($error_msg);
        }
    }

    /**
     * Get all presets
     */
    public function getPresets(): array {
        try {
            $headers = $this->get_auth_headers();

            $response = $this->http_client->get('presets', [
                'headers' => $headers
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            // Log for debugging
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - Get Presets - Status: $statusCode, Body: " . substr($body, 0, 500) . "\\n",
                              FILE_APPEND
            );

            if ($statusCode !== 200) {
                throw new RealtimeKitException('Failed to get presets - HTTP ' . $statusCode);
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new RealtimeKitException('Failed to get presets: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? [];

        } catch (RealtimeKitException $e) {
            throw $e;
        } catch (RequestException $e) {
            $error_msg = 'Get presets HTTP error: ' . $e->getMessage();
            if ($e->hasResponse()) {
                $error_msg .= ' | Response: ' . $e->getResponse()->getBody()->getContents();
            }
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - $error_msg\\n",
                              FILE_APPEND
            );
            throw new RealtimeKitException($error_msg);
        }
    }

    /**
     * Get meeting details
     */
    public function getMeeting(string $meetingId): array {
        try {
            $headers = $this->get_auth_headers();

            $response = $this->http_client->get("meetings/{$meetingId}", [
                'headers' => $headers
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($statusCode !== 200) {
                throw new RealtimeKitException('Failed to get meeting - HTTP ' . $statusCode);
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new RealtimeKitException('Failed to get meeting: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? $data;

        } catch (RequestException $e) {
            throw new RealtimeKitException('Get meeting HTTP error: ' . $e->getMessage());
        }
    }

    /**
     * Add participant to meeting
     */
    public function addParticipant(string $meetingId, array $participantData): array {
        $headers = $this->get_auth_headers();

        $defaultData = [
            'name' => 'Participant',
            'preset_name' => $this->settings->get_default_preset(),
            'custom_participant_id' => null,
        ];

        $participantData = array_merge($defaultData, $participantData);

        try {
            $response = $this->http_client->post("meetings/{$meetingId}/participants", [
                'headers' => $headers,
                'json' => $participantData
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($statusCode !== 200 && $statusCode !== 201) {
                throw new RealtimeKitException('Failed to add participant - HTTP ' . $statusCode);
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new RealtimeKitException('Failed to add participant: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? $data;

        } catch (RequestException $e) {
            throw new RealtimeKitException('Add participant HTTP error: ' . $e->getMessage());
        }
    }

    /**
     * Delete meeting
     */
    public function deleteMeeting(string $meetingId): bool {
        try {
            $headers = $this->get_auth_headers();

            $response = $this->http_client->delete("meetings/{$meetingId}", [
                'headers' => $headers
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200 || $statusCode === 204) {
                return true;
            }

            return false;

        } catch (RequestException $e) {
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - Delete meeting error: " . $e->getMessage() . "\\n",
                              FILE_APPEND
            );
            return false;
        }
    }

    /**
     * Get meeting recordings
     */
    public function getMeetingRecordings(string $meetingId): array {
        try {
            $headers = $this->get_auth_headers();

            $response = $this->http_client->get("meetings/{$meetingId}/recordings", [
                'headers' => $headers
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($statusCode !== 200) {
                throw new RealtimeKitException('Failed to get recordings - HTTP ' . $statusCode);
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new RealtimeKitException('Failed to get recordings: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? [];

        } catch (RequestException $e) {
            throw new RealtimeKitException('Get recordings HTTP error: ' . $e->getMessage());
        }
    }

    /**
     * Get meeting analytics
     */
    public function getMeetingAnalytics(string $meetingId): array {
        try {
            $headers = $this->get_auth_headers();

            $response = $this->http_client->get("meetings/{$meetingId}/analytics", [
                'headers' => $headers
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($statusCode !== 200) {
                throw new RealtimeKitException('Failed to get analytics - HTTP ' . $statusCode);
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new RealtimeKitException('Failed to get analytics: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? [];

        } catch (RequestException $e) {
            throw new RealtimeKitException('Get analytics HTTP error: ' . $e->getMessage());
        }
    }

    /**
     * Create participant and get auth token
     * Enhanced version of existing addParticipant method
     * @throws RealtimeKitException
     */
    public function create_participant(string $meeting_id, array $participant_data): array {
        $headers = $this->get_auth_headers();

        // Ensure required fields with defaults
        $default_data = [
            'preset_name' => $this->settings->get_default_preset(),
            'custom_participant_id' => wp_generate_uuid4(),
        ];

        $participant_data = array_merge($default_data, $participant_data);

        // Validate required fields
        if (empty($participant_data['name'])) {
            throw new RealtimeKitException('name is required for participant creation');
        }

        try {
            $response = $this->http_client->post("meetings/{$meeting_id}/participants", [
                'headers' => $headers,
                'json' => $participant_data
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            // Log for debugging
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - Create Participant - Status: $statusCode, Body: " . substr($body, 0, 500) . "\n",
                              FILE_APPEND
            );

            if ($statusCode !== 200 && $statusCode !== 201) {
                throw new RealtimeKitException('Failed to create participant - HTTP ' . $statusCode . ': ' . ($data['message'] ?? 'Unknown error'));
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new RealtimeKitException('Failed to create participant: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? $data;

        } catch (RequestException $e) {
            $error_msg = 'Create participant HTTP error: ' . $e->getMessage();
            if ($e->hasResponse()) {
                $error_msg .= ' | Response: ' . $e->getResponse()->getBody()->getContents();
            }
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - $error_msg\n",
                              FILE_APPEND
            );
            throw new RealtimeKitException($error_msg);
        }
    }

    /**
     * Get specific participant details
     */
    public function get_participant(string $meeting_id, string $participant_id): array {
        try {
            $headers = $this->get_auth_headers();

            $response = $this->http_client->get("meetings/{$meeting_id}/participants/{$participant_id}", [
                'headers' => $headers
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($statusCode !== 200) {
                throw new RealtimeKitException('Failed to get participant - HTTP ' . $statusCode);
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new RealtimeKitException('Failed to get participant: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? $data;

        } catch (RequestException $e) {
            throw new RealtimeKitException('Get participant HTTP error: ' . $e->getMessage());
        }
    }

    /**
     * Get all participants in a meeting
     */
    public function get_meeting_participants_from_api(string $meeting_id): array {
        try {
            $headers = $this->get_auth_headers();

            $response = $this->http_client->get("meetings/{$meeting_id}/participants", [
                'headers' => $headers
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($statusCode !== 200) {
                throw new RealtimeKitException('Failed to get meeting participants - HTTP ' . $statusCode);
            }

            if (!isset($data['success']) || !$data['success']) {
                throw new RealtimeKitException('Failed to get meeting participants: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? [];

        } catch (RequestException $e) {
            throw new RealtimeKitException('Get meeting participants HTTP error: ' . $e->getMessage());
        }
    }

    /**
     * Remove participant from meeting
     */
    public function remove_participant(string $meeting_id, string $participant_id): bool {
        try {
            $headers = $this->get_auth_headers();

            $response = $this->http_client->delete("meetings/{$meeting_id}/participants/{$participant_id}", [
                'headers' => $headers
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200 || $statusCode === 204) {
                return true;
            }

            return false;

        } catch (RequestException $e) {
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - Remove participant error: " . $e->getMessage() . "\n",
                              FILE_APPEND
            );
            return false;
        }
    }

    /**
     * Update participant permissions/settings
     */
    public function update_participant(string $meeting_id, string $participant_id, array $updates): array {
        try {
            $headers = $this->get_auth_headers();

            $response = $this->http_client->patch("meetings/{$meeting_id}/participants/{$participant_id}", [
                'headers' => $headers,
                'json' => $updates
            ]);

            $statusCode = $response->getStatusCode();
       $body = $response->getBody()->getContents();
       $data = json_decode($body, true);

       if ($statusCode !== 200) {
           throw new RealtimeKitException('Failed to update participant - HTTP ' . $statusCode);
       }

       if (!isset($data['success']) || !$data['success']) {
           throw new RealtimeKitException('Failed to update participant: ' . ($data['message'] ?? 'Unknown error'));
       }

       return $data['data'] ?? $data;

   } catch (RequestException $e) {
            throw new RealtimeKitException('Update participant HTTP error: ' . $e->getMessage());
        }
    }

    /**
     * Create meeting host participant (used when creating meetings)
     * @throws RealtimeKitException
     */
    public function create_host_participant(string $meeting_id, int $user_id, string $host_name): array {
        return $this->create_participant($meeting_id, [
            'name' => $host_name,
            'preset_name' => 'group_call_host',
            'custom_participant_id' => "host_wp_user_{$user_id}_" . wp_generate_uuid4(),
        ]);
    }

	/**
	 * @param string $meeting_id
	 * @param string $participant_name
	 * @param int|null $user_id
	 * @param $preset_name
	 *
	 * @return array
	 * @throws RealtimeKitException
	 */
    public function create_regular_participant(string $meeting_id, string $participant_name, ?int $user_id = null, $preset_name): array {
        $custom_id = $user_id
            ? "participant_wp_user_{$user_id}_" . wp_generate_uuid4()
            : "guest_" . wp_generate_uuid4();

        return $this->create_participant($meeting_id, [
            'name' => $participant_name,
            'preset_name' => $preset_name ?? 'group_call_participant',
            'custom_participant_id' => $custom_id,
        ]);
    }

    /**
     * Batch create multiple participants (if needed for future features)
     */
    public function create_multiple_participants(string $meeting_id, array $participants_data): array {
        $results = [];
        $errors = [];

        foreach ($participants_data as $participant_data) {
            try {
                $result = $this->create_participant($meeting_id, $participant_data);
                $results[] = $result;
            } catch (RealtimeKitException $e) {
                $errors[] = [
                    'participant_data' => $participant_data,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'successful' => $results,
            'failed' => $errors,
            'total_attempted' => count($participants_data),
            'successful_count' => count($results),
            'failed_count' => count($errors)
        ];
    }
}

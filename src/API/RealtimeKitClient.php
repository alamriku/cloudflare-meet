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

    public function __construct(Settings $settings) {
        $this->settings = $settings;

        // Initialize without headers first to avoid errors when credentials are empty
        $this->http_client = new Client([
                                            'base_uri' => self::API_BASE,
                                            'timeout' => 30,
                                        ]);
    }

    /**
     * @throws RealtimeKitException
     */
    private function getAuthHeaders(): array {
        $auth_token = $this->settings->getRestApiAuthHeader();
        if (empty($auth_token)) {
            $auth_token = $this->settings->getApiKey();
        }

        if (empty($auth_token)) {
            throw new RealtimeKitException('REST API Auth Header or API Key is required');
        }

        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . $auth_token,
        ];
    }

    /**
     * Test API connection using getAllOrgs endpoint
     */
    public function testConnection(): bool {
        try {
            $headers = $this->getAuthHeaders();

            // Use the correct endpoint from the API docs
            $response = $this->http_client->get('orgs', [
                'headers' => $headers
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            // Log response details for debugging
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - Test Connection - Status: $statusCode, Body: " . substr($body, 0, 500) . "\\n",
                              FILE_APPEND
            );

            if ($statusCode === 200) {
                $data = json_decode($body, true);
                return isset($data['success']) ? $data['success'] : true;
            }

            return false;

        } catch (RealtimeKitException $e) {
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - Credentials Error: " . $e->getMessage() . "\\n",
                              FILE_APPEND
            );
            return false;

        } catch (RequestException $e) {
            $error_msg = 'HTTP Error: ' . $e->getMessage();
            $response_body = '';

            if ($e->hasResponse()) {
                $response_body = $e->getResponse()->getBody()->getContents();
                $error_msg .= ' | Response: ' . substr($response_body, 0, 500);
            }

            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - $error_msg\\n",
                              FILE_APPEND
            );
            return false;

        } catch (\Exception $e) {
            file_put_contents(ABSPATH . 'wp-content/realtimekit-debug.log',
                              date('Y-m-d H:i:s') . " - General Error: " . $e->getMessage() . "\\n",
                              FILE_APPEND
            );
            return false;
        }
    }

    /**
     * Create a new meeting
     * @throws RealtimeKitException
     */
    public function createMeeting(array $params = []): array {
        $headers = $this->getAuthHeaders();

        $defaultParams = [
            'title' => 'WordPress Meeting',
            'preferred_region' => 'us-east-1',
            'record_on_start' => false,
            'live_stream_on_start' => false,
        ];

        $params = array_merge($defaultParams, $params);

        try {
            $response = $this->http_client->post('meetings', [
                'headers' => $headers,
                'json' => $params
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

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
            $headers = $this->getAuthHeaders();

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
            $headers = $this->getAuthHeaders();

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
        $headers = $this->getAuthHeaders();

        $defaultData = [
            'name' => 'Participant',
            'preset_name' => $this->settings->getDefaultPreset(),
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
            $headers = $this->getAuthHeaders();

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
            $headers = $this->getAuthHeaders();

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
            $headers = $this->getAuthHeaders();

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
}

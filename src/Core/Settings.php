<?php
declare(strict_types=1);

namespace CloudflareMeet\Core;

/**
 * Settings Manager
 */
class Settings {

    private const string OPTION_KEY = 'cloudflare_meet_settings';

    private array $settings;

    public function __construct() {
        $this->settings = get_option(self::OPTION_KEY, []);
    }

    public function getOrganizationId(): string {
        return $this->settings['organization_id'] ?? '';
    }

    public function getApiKey(): string {
        return $this->settings['api_key'] ?? '';
    }

    // Add this new method for the REST API Auth Header
    public function getRestApiAuthHeader(): string {
        return $this->settings['rest_api_auth_header'] ?? '';
    }

    public function getAccountId(): string {
        return $this->settings['account_id'] ?? '';
    }

    public function getApiToken(): string {
        return $this->settings['api_token'] ?? '';
    }

    public function getAppId(): string {
        return $this->settings['app_id'] ?? '';
    }

    public function get_default_preset(): string {
        return $this->settings['default_preset'] ?? 'group_call_participant';
    }

    public function getMaxParticipants(): int {
        return (int) ($this->settings['max_participants'] ?? 10);
    }

    public function getAutoRecord(): bool {
        return (bool) ($this->settings['auto_record'] ?? false);
    }

    public function save(array $settings): bool {
        $this->settings = array_merge($this->settings, $settings);
        return update_option(self::OPTION_KEY, $this->settings);
    }

    public function get(string $key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    public function all(): array {
        return $this->settings;
    }

    public function is_configured(): bool {
        return !empty($this->getOrganizationId()) && !empty($this->getApiKey());
    }

    public function delete(): bool {
        $this->settings = [];
        return delete_option(self::OPTION_KEY);
    }

    public function reset(): bool {
        $this->settings = $this->getDefaults();
        return update_option(self::OPTION_KEY, $this->settings);
    }

    private function getDefaults(): array {
        return [
            'organization_id' => '',
            'api_key' => '',
            'account_id' => '',
            'api_token' => '',
            'app_id' => '',
            'default_preset' => 'group_call_participant',
            'max_participants' => 10,
            'auto_record' => false,
        ];
    }
}

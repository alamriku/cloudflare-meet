<?php
declare(strict_types=1);

namespace CloudflareMeet\Database\Models;

/**
 * Participant Model
 */
class Participant {

    private ?int $id;
    private string $meeting_id;
    private ?int $user_id;
    private string $custom_participant_id;
    private string $participant_name;
    private ?string $email;
    private string $role;
    private string $status;
    private ?string $realtimekit_token;
    private ?string $joined_at;
    private ?string $left_at;
    private string $created_at;

    public function __construct(
        string $meeting_id,
        string $participant_name,
        string $role = 'participant',
        ?int $user_id = null,
        ?string $email = null,
        ?string $custom_participant_id = null,
        string $status = 'waiting',
        ?string $realtimekit_token = null,
        ?int $id = null,
        ?string $joined_at = null,
        ?string $left_at = null,
        ?string $created_at = null
    ) {
        $this->id = $id;
        $this->meeting_id = $meeting_id;
        $this->user_id = $user_id;
        $this->custom_participant_id = $custom_participant_id ?? wp_generate_uuid4();
        $this->participant_name = $participant_name;
        $this->email = $email;
        $this->role = $role;
        $this->status = $status;
        $this->realtimekit_token = $realtimekit_token;
        $this->joined_at = $joined_at;
        $this->left_at = $left_at;
        $this->created_at = $created_at ?? current_time('mysql');
    }

    public static function from_array(array $data): self {
        return new self(
            $data['meeting_id'],
            $data['participant_name'],
            $data['role'] ?? 'participant',
            isset($data['user_id']) ? (int) $data['user_id'] : null,
            $data['email'] ?? null,
            $data['custom_participant_id'] ?? null,
            $data['status'] ?? 'waiting',
            $data['realtimekit_token'] ?? null,
            isset($data['id']) ? (int) $data['id'] : null,
            $data['joined_at'] ?? null,
            $data['left_at'] ?? null,
            $data['created_at'] ?? null
        );
    }

    public function to_array(): array {
        $data = [
            'meeting_id' => $this->meeting_id,
            'user_id' => $this->user_id,
            'custom_participant_id' => $this->custom_participant_id,
            'participant_name' => $this->participant_name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'realtimekit_token' => $this->realtimekit_token,
            'joined_at' => $this->joined_at,
            'left_at' => $this->left_at,
            'created_at' => $this->created_at,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        return $data;
    }

    public function get_formats_for_insert(): array {
        return [
            '%s', // meeting_id
            '%d', // user_id
            '%s', // custom_participant_id
            '%s', // participant_name
            '%s', // email
            '%s', // role
            '%s', // status
            '%s', // realtimekit_token
            '%s', // joined_at
            '%s', // left_at
            '%s', // created_at
        ];
    }

    // Getters
    public function get_id(): ?int {
        return $this->id;
    }

    public function get_meeting_id(): string {
        return $this->meeting_id;
    }

    public function get_user_id(): ?int {
        return $this->user_id;
    }

    public function get_custom_participant_id(): string {
        return $this->custom_participant_id;
    }

    public function get_participant_name(): string {
        return $this->participant_name;
    }

    public function get_email(): ?string {
        return $this->email;
    }

    public function get_role(): string {
        return $this->role;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function get_realtimekit_token(): ?string {
        return $this->realtimekit_token;
    }

    public function get_joined_at(): ?string {
        return $this->joined_at;
    }

    public function get_left_at(): ?string {
        return $this->left_at;
    }

    public function get_created_at(): string {
        return $this->created_at;
    }

    // Setters
    public function set_id(int $id): void {
        $this->id = $id;
    }

    public function set_status(string $status): void {
        $this->status = $status;
    }

    public function set_realtimekit_token(?string $token): void {
        $this->realtimekit_token = $token;
    }

    public function set_joined_at(?string $joined_at): void {
        $this->joined_at = $joined_at;
    }

    public function set_left_at(?string $left_at): void {
        $this->left_at = $left_at;
    }

    // Helper methods
    public function is_host(): bool {
        return $this->role === 'host';
    }

    public function is_participant(): bool {
        return $this->role === 'participant';
    }

    public function is_waiting(): bool {
        return $this->status === 'waiting';
    }

    public function is_approved(): bool {
        return $this->status === 'approved';
    }

    public function has_joined(): bool {
        return $this->status === 'joined';
    }

    public function has_left(): bool {
        return $this->status === 'left';
    }

    public function is_logged_in_user(): bool {
        return $this->user_id !== null;
    }

    public function is_guest(): bool {
        return $this->user_id === null;
    }
}

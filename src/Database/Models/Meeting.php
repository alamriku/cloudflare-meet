<?php
declare(strict_types=1);

namespace CloudflareMeet\Database\Models;
use DateTime;

/**
 * Meeting Model
 */
class Meeting {

    private ?int $id;
    private int $userId;
    private string $sessionId;
    private string $roomName;
    private string $status;
    private int $participantCount;
    private int $maxParticipants;
    private string $createdAt;
    private ?string $endedAt;

    public function __construct(
        int $userId,
        string $sessionId,
        string $roomName,
        string $status = 'active',
        int $participantCount = 0,
        int $maxParticipants = 10,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $endedAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->sessionId = $sessionId;
        $this->roomName = $roomName;
        $this->status = $status;
        $this->participantCount = $participantCount;
        $this->maxParticipants = $maxParticipants;
        $this->createdAt = $createdAt ?? current_time('mysql');
        $this->endedAt = $endedAt;
    }

    public static function fromArray(array $data): self {
        return new self(
            (int) $data['user_id'],
            $data['session_id'],
            $data['room_name'],
            $data['status'] ?? 'active',
            (int) ($data['participant_count'] ?? 0),
            (int) ($data['max_participants'] ?? 10),
            isset($data['id']) ? (int) $data['id'] : null,
            $data['created_at'] ?? null,
            $data['ended_at'] ?? null
        );
    }

    public function toArray(): array {
        $data = [
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'room_name' => $this->roomName,
            'status' => $this->status,
            'participant_count' => $this->participantCount,
            'max_participants' => $this->maxParticipants,
            'created_at' => $this->createdAt,
            'ended_at' => $this->endedAt,
        ];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        return $data;
    }

    public function getFormats(): array {
        $formats = [
            '%d', // user_id
            '%s', // session_id
            '%s', // room_name
            '%s', // status
            '%d', // participant_count
            '%d', // max_participants
            '%s', // created_at
            '%s', // ended_at
        ];

        if ($this->id !== null) {
            array_unshift($formats, '%d'); // id
        }

        return $formats;
    }

    public function getFormatsForInsert(): array {
        return [
            '%d', // user_id
            '%s', // session_id
            '%s', // room_name
            '%s', // status
            '%d', // participant_count
            '%d', // max_participants
            '%s', // created_at
            '%s', // ended_at
        ];
    }

    // Getters
    public function get_id(): ?int {
        return $this->id;
    }

    public function getUserId(): int {
        return $this->userId;
    }

    public function getSessionId(): string {
        return $this->sessionId;
    }

    public function getRoomName(): string {
        return $this->roomName;
    }

    public function getStatus(): string {
        return $this->status;
    }

    public function getParticipantCount(): int {
        return $this->participantCount;
    }

    public function getMaxParticipants(): int {
        return $this->maxParticipants;
    }

    public function getCreatedAt(): string {
        return $this->createdAt;
    }

    public function getEndedAt(): ?string {
        return $this->endedAt;
    }

    // Setters
    public function setId(int $id): void {
        $this->id = $id;
    }

    public function setStatus(string $status): void {
        $this->status = $status;
    }

    public function setParticipantCount(int $count): void {
        $this->participantCount = $count;
    }

    public function setEndedAt(?string $endedAt): void {
        $this->endedAt = $endedAt;
    }

    public function setMaxParticipants(int $maxParticipants): void {
        $this->maxParticipants = $maxParticipants;
    }

    // Helper methods
    public function isActive(): bool {
        return $this->status === 'active';
    }

    public function isEnded(): bool {
        return $this->status === 'ended';
    }

    public function getDuration(): ?int {
        if ($this->endedAt === null) {
            return null;
        }

        $start = new DateTime($this->createdAt);
        $end = new DateTime($this->endedAt);

        return $end->getTimestamp() - $start->getTimestamp();
    }

    public function getFormattedDuration(): string {
        $duration = $this->getDuration();

        if ($duration === null) {
            return __('Ongoing', 'cloudflare-meet');
        }

        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}

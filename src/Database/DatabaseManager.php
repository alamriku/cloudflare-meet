<?php
declare(strict_types=1);

namespace CloudflareMeet\Database;

use CloudflareMeet\Database\Models\Meeting;

/**
 * Database Manager Class
 */
class DatabaseManager {

    private const DB_VERSION = '1.0';
    private const OPTION_DB_VERSION = 'cloudflare_meet_db_version';

    public function createTables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $this->createMeetingsTable($charset_collate);

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
    }

    public function insertDefaultData(): void {
        // Insert any default data needed
        // This could include default presets, settings, etc.

        // For now, just ensure the database version is set
        if (!get_option(self::OPTION_DB_VERSION)) {
            update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
        }
    }

    private function createMeetingsTable(string $charset_collate): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            session_id varchar(255) NOT NULL,
            room_name varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'active',
            participant_count int DEFAULT 0,
            max_participants int DEFAULT 10,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            ended_at datetime NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY status (status),
            KEY created_at (created_at),
            UNIQUE KEY unique_session (session_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function getMeeting(string $sessionId): ?Meeting {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE session_id = %s",
                $sessionId
            ),
            ARRAY_A
        );

        return $result ? Meeting::fromArray($result) : null;
    }

    public function createMeeting(Meeting $meeting): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';

        $data = $meeting->toArray();
        // Remove id from insert data if it's null
        if ($data['id'] === null) {
            unset($data['id']);
        }

        $result = $wpdb->insert(
            $table_name,
            $data,
            $meeting->getFormatsForInsert()
        );

        return $result !== false;
    }

    public function updateMeeting(string $sessionId, array $data): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';

        // Prepare formats for the data being updated
        $formats = [];
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'user_id':
                case 'participant_count':
                case 'max_participants':
                    $formats[] = '%d';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }

        $result = $wpdb->update(
            $table_name,
            $data,
            ['session_id' => $sessionId],
            $formats,
            ['%s']
        );

        return $result !== false;
    }

    public function getUserMeetings(int $userId, int $limit = 10): array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $userId,
                $limit
            ),
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        return array_map([Meeting::class, 'fromArray'], $results);
    }

    public function getActiveMeetings(): array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active' ORDER BY created_at DESC",
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        return array_map([Meeting::class, 'fromArray'], $results);
    }

    public function getMeetings(int $limit = 20, int $offset = 0): array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        return array_map([Meeting::class, 'fromArray'], $results);
    }

    public function getTotalMeetingsCount(): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    public function getMeetingsTodayCount(): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = CURDATE()"
        );
    }

    public function deleteMeeting(string $sessionId): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';

        $result = $wpdb->delete(
            $table_name,
            ['session_id' => $sessionId],
            ['%s']
        );

        return $result !== false;
    }

    public function cleanupOldMeetings(int $days = 30): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cloudflare_meetings';

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        return $result ?: 0;
    }
}

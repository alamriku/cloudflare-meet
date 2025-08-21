<?php
declare(strict_types=1);

namespace CloudflareMeet\Database;

use CloudflareMeet\Database\Models\Meeting;
use CloudflareMeet\Database\Models\Participant;

/**
 * Database Manager Class
 */
class DatabaseManager {

    private \wpdb $wpdb;
    private const string DB_VERSION = '1.0';
    private const string OPTION_DB_VERSION = 'cloudflare_meet_db_version';

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    public function createTables(): void {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $this->wpdb->get_charset_collate();

        $meeting_table = $this->wpdb->prefix . 'cloudflare_meetings';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$meeting_table'") !== $meeting_table) {
            $this->createMeetingsTable($charset_collate);

        }
        $participants_table = $this->wpdb->prefix . 'cloudflare_participants';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '$participants_table'") !== $participants_table) {
            $this->create_participants_table($charset_collate);

        }

        update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
    }

    public function insert_default_data(): void {
        // Insert any default data needed
        // This could include default presets, settings, etc.

        // For now, just ensure the database version is set
        if (!get_option(self::OPTION_DB_VERSION)) {
            update_option(self::OPTION_DB_VERSION, self::DB_VERSION);
        }
    }

    private function createMeetingsTable(string $charset_collate): void {
        $table_name = $this->wpdb->prefix . 'cloudflare_meetings';

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

        dbDelta($sql);
    }

    private function create_participants_table(string $charset_collate): void {
        $table_name = $this->wpdb->prefix . 'cloudflare_participants';

        $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        meeting_id varchar(255) NOT NULL,
        user_id bigint(20) NULL,
        custom_participant_id varchar(255) NOT NULL,
        participant_name varchar(255) NOT NULL,
        email varchar(255) NULL,
        role enum('host', 'participant') DEFAULT 'participant',
        status enum('waiting', 'approved', 'joined', 'left') DEFAULT 'waiting',
        realtimekit_token text NULL,
        joined_at datetime NULL,
        left_at datetime NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        
        PRIMARY KEY (id)
    ) $charset_collate;";

        dbDelta($sql);
    }

    public function get_meeting(string $sessionId): ?Meeting {
        $table_name = $this->wpdb->prefix . 'cloudflare_meetings';
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE session_id = %s",
                $sessionId
            ),
            ARRAY_A
        );

        return $result ? Meeting::fromArray($result) : null;
    }

    public function create_meeting(Meeting $meeting): bool {
        $table_name = $this->wpdb->prefix . 'cloudflare_meetings';

        $data = $meeting->toArray();
        // Remove id from insert data if it's null
        if ($data['id'] === null) {
            unset($data['id']);
        }

        $result = $this->wpdb->insert(
            $table_name,
            $data,
            $meeting->getFormatsForInsert()
        );

        return $result !== false;
    }

    public function update_meeting(string $sessionId, array $data): bool {
        $table_name = $this->wpdb->prefix . 'cloudflare_meetings';

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

        $result = $this->wpdb->update(
            $table_name,
            $data,
            ['session_id' => $sessionId],
            $formats,
            ['%s']
        );

        return $result !== false;
    }

    public function get_user_meetings(int $userId, int $limit = 10): array {


        $table_name = $this->wpdb->prefix . 'cloudflare_meetings';
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
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

    public function get_active_meetings(): array {
        $table_name = $this->wpdb->prefix . 'cloudflare_meetings';
        $results = $this->wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active' ORDER BY created_at DESC",
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        return array_map([Meeting::class, 'fromArray'], $results);
    }

    public function get_meetings(int $limit = 20, int $offset = 0): array {
        $table_name = $this->wpdb->prefix . 'cloudflare_meetings';
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
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

    //Participants
    // Participant CRUD methods
    public function create_participant(Participant $participant): bool {

        $table_name = $this->wpdb->prefix . 'cloudflare_participants';

        $data = $participant->to_array();
        // Remove id from insert data if it's null
        if ($data['id'] === null) {
            unset($data['id']);
        }

        $result = $this->wpdb->insert(
            $table_name,
            $data,
            $participant->get_formats_for_insert()
        );

        return $result !== false;
    }

    public function get_participant(string $custom_participant_id): ?Participant {

        $table_name = $this->wpdb->prefix . 'cloudflare_participants';
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE custom_participant_id = %s",
                $custom_participant_id
            ),
            ARRAY_A
        );

        return $result ? Participant::from_array($result) : null;
    }

    public function get_meeting_participants(string $meeting_id): array {

        $table_name = $this->wpdb->prefix . 'cloudflare_participants';
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE meeting_id = %s ORDER BY created_at ASC",
                $meeting_id
            ),
            ARRAY_A
        );

        if (!$results) {
            return [];
        }

        return array_map([Participant::class, 'from_array'], $results);
    }

    public function update_participant(string $custom_participant_id, array $data): bool {

        $table_name = $this->wpdb->prefix . 'cloudflare_participants';

        // Prepare formats for the data being updated
        $formats = [];
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'user_id':
                case 'id':
                    $formats[] = '%d';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }

        $result = $this->wpdb->update(
            $table_name,
            $data,
            ['custom_participant_id' => $custom_participant_id],
            $formats,
            ['%s']
        );

        return $result !== false;
    }

    public function delete_participant(string $custom_participant_id): bool {

        $table_name = $this->wpdb->prefix . 'cloudflare_participants';

        $result = $this->wpdb->delete(
            $table_name,
            ['custom_participant_id' => $custom_participant_id],
            ['%s']
        );

        return $result !== false;
    }

    public function get_participant_count(string $meeting_id): int {

        $table_name = $this->wpdb->prefix . 'cloudflare_participants';

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE meeting_id = %s AND status IN ('approved', 'joined')",
                $meeting_id
            )
        );
    }

// TODO: Auto-cleanup methods for later implementation
    public function cleanup_old_participants(int $hours = 24): int {

        $table_name = $this->wpdb->prefix . 'cloudflare_participants';

        // TODO: Implement cleanup logic based on meeting end time or created_at
        // For now, just return 0
        return 0;
    }
}

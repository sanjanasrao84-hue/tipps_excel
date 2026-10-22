<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_gcal;

use core\encryption;
use stdClass;

/**
 * A user's connection to their Google Calendar. Access and refresh tokens are stored encrypted
 * (core encryption key in the data directory) and never leave the server.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connection {

    /** @var string Connected and synchronising. */
    public const STATUS_ACTIVE = 'active';

    /** @var string Google refused the token (revoked, expired or password changed): the user must connect again. */
    public const STATUS_RECONNECT = 'reconnect';

    /** @var string Table. */
    public const TABLE = 'local_gcal_connection';

    /** @var stdClass Database record. */
    public stdClass $record;

    /**
     * Constructor.
     *
     * @param stdClass $record
     */
    private function __construct(stdClass $record) {
        $this->record = $record;
    }

    /**
     * Connection of a user, if any.
     *
     * @param int $userid
     * @return self|null
     */
    public static function get(int $userid): ?self {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['userid' => $userid]);
        return $record ? new self($record) : null;
    }

    /**
     * Creates or replaces the connection of a user after they authorised access.
     *
     * @param int $userid
     * @param int $issuerid
     * @param array $tokens Google token response (access_token, expires_in, refresh_token, scope)
     * @param string $email
     * @return self
     */
    public static function save_authorisation(int $userid, int $issuerid, array $tokens, string $email): self {
        global $DB;

        $now = time();
        $connection = self::get($userid);
        if (!$connection) {
            $connection = new self((object) [
                'userid' => $userid, 'showpersonal' => 1, 'timecreated' => $now, 'lastsync' => 0,
            ]);
        }
        $record = $connection->record;
        $record->issuerid = $issuerid;
        $record->googleemail = $email;
        $record->scopes = $tokens['scope'] ?? '';
        $record->status = self::STATUS_ACTIVE;
        $record->lasterror = null;
        $record->timemodified = $now;
        $connection->set_tokens($tokens);

        if (empty($record->id)) {
            $record->id = $DB->insert_record(self::TABLE, $record);
        } else {
            $DB->update_record(self::TABLE, $record);
        }
        return $connection;
    }

    /**
     * Stores tokens from a Google token response (the refresh token is only sent when consent is given).
     *
     * @param array $tokens
     * @return void
     */
    public function set_tokens(array $tokens): void {
        if (!empty($tokens['access_token'])) {
            $this->record->accesstoken = encryption::encrypt($tokens['access_token']);
            $this->record->tokenexpires = time() + (int) ($tokens['expires_in'] ?? 3600);
        }
        if (!empty($tokens['refresh_token'])) {
            $this->record->refreshtoken = encryption::encrypt($tokens['refresh_token']);
        }
    }

    /**
     * Current access token, if still valid for at least a minute.
     *
     * @return string|null
     */
    public function get_valid_access_token(): ?string {
        if (empty($this->record->accesstoken) || $this->record->tokenexpires < time() + 60) {
            return null;
        }
        return encryption::decrypt($this->record->accesstoken);
    }

    /**
     * Refresh token.
     *
     * @return string|null
     */
    public function get_refresh_token(): ?string {
        return empty($this->record->refreshtoken) ? null : encryption::decrypt($this->record->refreshtoken);
    }

    /**
     * Whether the user granted a scope.
     *
     * @param string $scope
     * @return bool
     */
    public function has_scope(string $scope): bool {
        return in_array($scope, preg_split('/\s+/', (string) $this->record->scopes));
    }

    /**
     * Whether the connection can be used.
     *
     * @return bool
     */
    public function is_active(): bool {
        return $this->record->status === self::STATUS_ACTIVE;
    }

    /**
     * Whether the user's own Google events are shown in the LMS calendar.
     *
     * @return bool
     */
    public function shows_personal_events(): bool {
        return $this->is_active() && !empty($this->record->showpersonal) && get_config('local_gcal', 'showpersonal')
            && $this->has_scope(google\oauth::SCOPE_PERSONAL);
    }

    /**
     * Marks the connection as needing the user to connect again (and keeps everything else).
     *
     * @param string $error
     * @return void
     */
    public function mark_reconnect(string $error): void {
        $this->record->status = self::STATUS_RECONNECT;
        $this->record->accesstoken = null;
        $this->record->tokenexpires = 0;
        $this->record->lasterror = $error;
        $this->save();
    }

    /**
     * Saves the record.
     *
     * @return void
     */
    public function save(): void {
        global $DB;
        $this->record->timemodified = time();
        $DB->update_record(self::TABLE, $this->record);
    }

    /**
     * Deletes the connection and the links to the user's Google events. The Moodle account is not affected.
     *
     * @return void
     */
    public function delete(): void {
        global $DB;
        $DB->delete_records('local_gcal_eventmap', ['userid' => $this->record->userid]);
        $DB->delete_records(self::TABLE, ['id' => $this->record->id]);
    }
}

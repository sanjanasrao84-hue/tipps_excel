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

namespace local_gcal\local;

/**
 * Synchronisation log (failures, conflicts and changes applied from Google). Event contents are not logged.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logger {

    /** @var int Days logs are kept. */
    public const KEEP_DAYS = 30;

    /**
     * Adds a log entry.
     *
     * @param int $userid
     * @param string $level info, warning or error
     * @param string $action
     * @param string $message
     * @param int $eventid
     * @return void
     */
    public static function log(int $userid, string $level, string $action, string $message = '', int $eventid = 0): void {
        global $DB;
        $DB->insert_record('local_gcal_log', (object) [
            'userid' => $userid, 'level' => $level, 'action' => $action, 'eventid' => $eventid,
            'message' => shorten_text($message, 500), 'timecreated' => time(),
        ]);
    }

    /**
     * Removes old log entries.
     *
     * @return void
     */
    public static function cleanup(): void {
        global $DB;
        $DB->delete_records_select('local_gcal_log', 'timecreated < ?', [time() - self::KEEP_DAYS * DAYSECS]);
    }
}

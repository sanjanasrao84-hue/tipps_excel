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

namespace local_gcal\task;

use local_gcal\connection;
use local_gcal\local\logger;
use local_gcal\local\sync_manager;

/**
 * Regular synchronisation of every connected user, least recently synchronised first, within a time budget
 * (users not reached are synchronised at the next run).
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_all extends \core\task\scheduled_task {

    /** @var int Seconds a run may spend synchronising. */
    private const TIME_BUDGET = 240;

    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksyncall', 'local_gcal');
    }

    /**
     * Runs the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        logger::cleanup();
        if (!\local_gcal\google\oauth::is_configured()) {
            mtrace('Google Calendar is not configured.');
            return;
        }
        $deadline = time() + self::TIME_BUDGET;
        $userids = $DB->get_fieldset_select(connection::TABLE, 'userid', 'status = ?', [connection::STATUS_ACTIVE],
            'lastsync ASC');
        $done = 0;
        foreach ($userids as $userid) {
            if (time() >= $deadline) {
                mtrace('Time budget reached; remaining users are synchronised at the next run.');
                break;
            }
            $connection = connection::get((int) $userid);
            if (!$connection || !$connection->is_active()) {
                continue;
            }
            $manager = new sync_manager($connection);
            $manager->sync();
            $done++;
            mtrace("  User {$userid}: " . json_encode($manager->result));
        }
        mtrace("Synchronised {$done} of " . count($userids) . ' connected users.');
    }
}

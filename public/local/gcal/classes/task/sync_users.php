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
use local_gcal\local\sync_manager;

/**
 * Synchronises some users after a change (queued by the event observers).
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_users extends \core\task\adhoc_task {

    /**
     * Runs the task.
     *
     * @return void
     */
    public function execute() {
        foreach ($this->get_custom_data()->userids ?? [] as $userid) {
            $connection = connection::get((int) $userid);
            if ($connection && $connection->is_active()) {
                $manager = new sync_manager($connection);
                $manager->sync();
                mtrace("  User {$userid}: " . json_encode($manager->result));
            }
        }
    }
}

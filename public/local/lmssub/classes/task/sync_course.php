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

namespace local_lmssub\task;

use local_lmssub\access_engine;

/**
 * Background update of a course's subscribers after the course was created, moved, hidden, shown or excluded.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_course extends \core\task\adhoc_task {

    /**
     * Runs the task.
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        if ($DB->record_exists('course', ['id' => $data->courseid])) {
            access_engine::sync_course((int) $data->courseid);
        }
    }
}

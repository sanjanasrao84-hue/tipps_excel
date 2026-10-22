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
use local_lmssub\plan_manager;

/**
 * Background re-check of everyone, after a category was moved or deleted.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_all extends \core\task\adhoc_task {

    /**
     * Runs the task.
     */
    public function execute() {
        global $DB;
        // Courses newly covered need the subscription enrolment method.
        foreach ($DB->get_fieldset_select('local_lmssub_plan', 'id', '1 = 1') as $planid) {
            plan_manager::ensure_enrol_instances(plan_manager::courses_for_plan((int) $planid));
        }
        access_engine::sync_all();
    }
}

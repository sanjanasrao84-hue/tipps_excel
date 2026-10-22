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

namespace local_gcal\external;

use calendar_event;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Cancels (deletes) an academic event. Activity dates are changed in the activity, not here.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_event extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'eventid' => new external_value(PARAM_INT, 'Moodle event id'),
        ]);
    }

    /**
     * Deletes the event.
     *
     * @param int $eventid
     * @return array
     */
    public static function execute(int $eventid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['eventid' => $eventid]);
        self::validate_context(context_system::instance());

        $event = calendar_event::load($params['eventid']);
        if (!empty($event->modulename) || !calendar_delete_event_allowed($event)) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('cancelevent', 'local_gcal'));
        }
        $event->delete();
        $DB->delete_records('local_gcal_eventmeta', ['eventid' => $params['eventid']]);
        return ['deleted' => true];
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'Deleted'),
        ]);
    }
}

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

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Events of the dashboard Timeline "Sort by courses" view: for each course, its activity deadlines together with its
 * calendar page events, in date order.
 *
 * Same parameters and result as core_calendar_get_action_events_by_courses.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_timeline_course_events extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'Course id')),
            'timesortfrom' => new external_value(PARAM_INT, 'Start of the range (timestamp)', VALUE_DEFAULT, null,
                NULL_ALLOWED),
            'timesortto' => new external_value(PARAM_INT, 'End of the range (timestamp)', VALUE_DEFAULT, null, NULL_ALLOWED),
            'limitnum' => new external_value(PARAM_INT, 'Maximum number of events per course', VALUE_DEFAULT, 10),
            'searchvalue' => new external_value(PARAM_RAW, 'Text in the event or course name', VALUE_DEFAULT, null,
                NULL_ALLOWED),
        ]);
    }

    /**
     * Timeline events by course.
     *
     * @param int[] $courseids
     * @param int|null $timesortfrom
     * @param int|null $timesortto
     * @param int $limitnum
     * @param string|null $searchvalue
     * @return array
     */
    public static function execute(array $courseids, ?int $timesortfrom = null, ?int $timesortto = null, int $limitnum = 10,
            ?string $searchvalue = null): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseids', 'timesortfrom', 'timesortto', 'limitnum', 'searchvalue'));
        self::validate_context(context_system::instance());

        $grouped = [];
        global $DB;
        foreach (array_unique($params['courseids']) as $courseid) {
            // Only courses the user can access, like core.
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course || $course->id == SITEID || !can_access_course($course, null, '', true)) {
                continue;
            }
            $grouped[] = [
                'courseid' => $courseid,
                'events' => get_timeline_events::collect($courseid, (int) $params['timesortfrom'], $params['timesortto'], 0,
                    $params['limitnum'], $params['searchvalue']),
            ];
        }
        return ['groupedbycourse' => $grouped];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'groupedbycourse' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'events' => get_timeline_events::events_structure(),
            ])),
        ]);
    }
}

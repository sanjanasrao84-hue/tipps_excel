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

namespace local_gcal\output;

use core_course_category;
use core_date;
use local_gcal\form\event_form;
use local_gcal\google\oauth;
use local_gcal\local\event_source;
use local_gcal\local\status;
use moodle_page;
use moodle_url;

/**
 * The calendar (month, week, day and agenda views with filters), shared by the calendar page and the
 * Dashboard calendar block.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar {

    /**
     * Loads the calendar's JavaScript and strings into a page and returns its HTML. One calendar per page.
     *
     * @param moodle_page $page
     * @param int $courseid Course selected in the filter (0 for all)
     * @param string $initialdate Date shown first (YYYY-MM-DD, empty for today)
     * @param bool $inhead Load the FullCalendar library in the page head (only possible before the header is printed)
     * @return string
     */
    public static function render(moodle_page $page, int $courseid = 0, string $initialdate = '', bool $inhead = true): string {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/calendar/lib.php');

        // Filters: the user's courses and their organisations (top-level categories).
        $courses = [];
        $organisations = [];
        foreach (calendar_get_default_courses(null, 'id, fullname, category, sortorder', false, $USER->id) as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            $courses[] = ['id' => (int) $course->id, 'name' => format_string($course->fullname),
                'selected' => $course->id == $courseid];
            $category = core_course_category::get($course->category, IGNORE_MISSING, true);
            if ($category) {
                $parents = $category->get_parents();
                $top = core_course_category::get($parents ? reset($parents) : $category->id, IGNORE_MISSING, true);
                if ($top) {
                    $organisations[$top->id] = ['id' => (int) $top->id, 'name' => $top->get_formatted_name()];
                }
            }
        }
        usort($courses, fn($a, $b) => strcoll($a['name'], $b['name']));

        $categories = [];
        foreach (event_source::CATEGORIES as $key => [, $colour]) {
            $categories[] = ['key' => $key, 'name' => get_string('category_' . $key, 'local_gcal'), 'color' => $colour];
        }

        $cancreate = (bool) event_form::targets();
        $initialdate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $initialdate) ? $initialdate : '';

        $page->requires->js(new moodle_url('/local/gcal/thirdparty/fullcalendar/index.global.min.js'), $inhead);
        $page->requires->strings_for_js([
            'viewmonth', 'viewweek', 'viewday', 'viewagenda', 'today', 'allday', 'cancelevent', 'cancelconfirm', 'editevent',
            'createevent', 'eventcancelled', 'eventsaved', 'opencourse', 'openactivity', 'openingoogle', 'editdates', 'syncing',
            'syncfailed', 'notsynced', 'syncedtogoogle', 'personalnote', 'deadline', 'noevents', 'location', 'course', 'close',
        ], 'local_gcal');
        $page->requires->js_call_amd('local_gcal/calendar', 'init', [[
            'timezone' => core_date::get_user_timezone($USER),
            'locale' => current_language(),
            'initialdate' => $initialdate,
            'cancreate' => $cancreate,
            'firstday' => (int) \core_calendar\type_factory::get_calendar_instance()->get_starting_weekday(),
        ]]);

        return $page->get_renderer('core')->render_from_template('local_gcal/calendar', [
            'status' => status::export($USER->id),
            'courses' => $courses,
            'organisations' => array_values($organisations),
            'hasorganisations' => count($organisations) > 1,
            'categories' => $categories,
            'cancreate' => $cancreate,
            'isadmin' => is_siteadmin(),
            'settingsurl' => (new moodle_url('/admin/settings.php', ['section' => 'local_gcal']))->out(false),
            'issetup' => oauth::is_configured(),
        ]);
    }
}

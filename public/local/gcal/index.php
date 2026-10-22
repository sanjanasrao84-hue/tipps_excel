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

/**
 * Calendar: month, week, day and agenda views of the user's academic events and Google Calendar.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/calendar/lib.php');

use local_gcal\form\event_form;
use local_gcal\google\oauth;
use local_gcal\local\event_source;
use local_gcal\local\status;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot use the calendar');
}

$courseid = optional_param('course', 0, PARAM_INT);
$date = optional_param('date', '', PARAM_RAW);
$context = context_system::instance();
$url = new moodle_url('/local/gcal/index.php');

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('calendar', 'local_gcal'));
$PAGE->set_heading(get_string('calendar', 'local_gcal'));
$PAGE->add_body_class('local-gcal-page');

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

$status = status::export($USER->id);
$cancreate = (bool) event_form::targets();
$initialdate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';

$PAGE->requires->js(new moodle_url('/local/gcal/thirdparty/fullcalendar/index.global.min.js'), true);
$PAGE->requires->strings_for_js([
    'viewmonth', 'viewweek', 'viewday', 'viewagenda', 'today', 'allday', 'cancelevent', 'cancelconfirm', 'editevent', 'createevent', 'eventcancelled', 'eventsaved',
    'opencourse', 'openactivity', 'openingoogle', 'editdates', 'syncing', 'syncfailed', 'notsynced', 'syncedtogoogle',
    'personalnote', 'deadline', 'noevents', 'location', 'course', 'close',
], 'local_gcal');
$PAGE->requires->js_call_amd('local_gcal/calendar', 'init', [[
    'timezone' => core_date::get_user_timezone($USER),
    'locale' => current_language(),
    'initialdate' => $initialdate,
    'cancreate' => $cancreate,
    'firstday' => (int) \core_calendar\type_factory::get_calendar_instance()->get_starting_weekday(),
]]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_gcal/calendar', [
    'status' => $status,
    'courses' => $courses,
    'organisations' => array_values($organisations),
    'hasorganisations' => count($organisations) > 1,
    'categories' => $categories,
    'cancreate' => $cancreate,
    'isadmin' => is_siteadmin(),
    'settingsurl' => (new moodle_url('/admin/settings.php', ['section' => 'local_gcal']))->out(false),
    'issetup' => oauth::is_configured(),
]);
echo $OUTPUT->footer();

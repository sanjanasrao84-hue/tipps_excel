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

use local_gcal\output\calendar;

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

$html = calendar::render($PAGE, $courseid, $date);

echo $OUTPUT->header();
echo $html;
echo $OUTPUT->footer();

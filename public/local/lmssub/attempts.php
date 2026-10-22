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
 * Payment attempts: students who started paying for a plan, per course, with the option to make them subscribers.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\attempt_manager;
use local_lmssub\plan_manager;

$courseid = optional_param('courseid', 0, PARAM_INT);
$show = optional_param('show', 'open', PARAM_ALPHA);
$show = $show === 'all' ? 'all' : 'open';

$params = array_filter(['courseid' => $courseid, 'show' => $show === 'all' ? 'all' : null]);
$url = new moodle_url('/local/lmssub/attempts.php', $params);

if ($courseid) {
    // Opened from the course: shown inside the course, for the admins who manage subscribers.
    $course = get_course($courseid);
    require_login($course);
    require_capability('local/lmssub:managesubscribers', context_system::instance());
    $PAGE->set_url($url);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_title(get_string('paymentattempts', 'local_lmssub') . ': ' . format_string($course->fullname));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->navbar->add(get_string('paymentattempts', 'local_lmssub'), $url);
} else {
    admin_externalpage_setup('local_lmssub_attempts', '', null, $url);
    $course = null;
}

$attempts = attempt_manager::get_attempts($courseid, $show === 'open');
$rows = attempt_manager::export_rows(attempt_manager::group($attempts), $url, $courseid);

// Course filter: the courses opened by at least one plan.
$courseoptions = [];
foreach ($DB->get_records('local_lmssub_plan', null, 'sortorder', 'id') as $plan) {
    foreach (plan_manager::courses_for_plan((int) $plan->id) as $id => $unused) {
        $courseoptions[$id] = true;
    }
}
$courseselect = [['id' => 0, 'name' => get_string('allcourses', 'local_lmssub'), 'selected' => !$courseid]];
if ($courseoptions) {
    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($courseoptions));
    foreach ($DB->get_records_select('course', "id $insql", $inparams, 'fullname', 'id, fullname') as $c) {
        $courseselect[] = ['id' => $c->id, 'name' => format_string($c->fullname), 'selected' => $c->id == $courseid];
    }
}

$context = [
    'formurl' => (new moodle_url('/local/lmssub/attempts.php'))->out(false),
    'courseid' => $courseid,
    'coursename' => $course ? format_string($course->fullname) : '',
    'courses' => $courseselect,
    'showopen' => $show === 'open',
    'showall' => $show === 'all',
    'hasrows' => (bool) $rows,
    'rows' => $rows,
    'subscribersurl' => (new moodle_url('/local/lmssub/subscribers.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('paymentattempts', 'local_lmssub'));
echo $OUTPUT->render_from_template('local_lmssub/attempts_page', $context);
echo $OUTPUT->footer();

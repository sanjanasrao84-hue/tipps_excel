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
 * Preview courses covered: exactly which courses a plan opens.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\plan_manager;

$id = required_param('id', PARAM_INT);

admin_externalpage_setup('local_lmssub_plans', '', null, new moodle_url('/local/lmssub/preview.php', ['id' => $id]));
$plan = plan_manager::get_plan($id);
$types = plan_manager::get_types();

$PAGE->navbar->add(get_string('previewcourses', 'local_lmssub'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('previewcoursesfor', 'local_lmssub', format_string($plan->name)));

$details = [
    get_string('plantype', 'local_lmssub') => $types[$plan->type] ?? s($plan->type),
    get_string('status') => $plan->active ? get_string('active') : get_string('inactive'),
    get_string('devicelimit', 'local_lmssub') => (int) $plan->devicelimit,
    get_string('gracedays', 'local_lmssub') => (int) $plan->gracedays,
    get_string('autorenewallowed', 'local_lmssub') => $plan->autorenew ? get_string('yes') : get_string('no'),
];
$prices = [];
foreach (plan_manager::get_prices($id) as $price) {
    $prices[] = plan_manager::duration_label((int) $price->durationdays) . ': ' .
        plan_manager::format_amount((int) $price->amount, $price->currency);
}
$details[get_string('prices', 'local_lmssub')] = implode(', ', $prices);
$dl = '';
foreach ($details as $label => $value) {
    $dl .= html_writer::tag('dt', $label, ['class' => 'col-sm-3']) . html_writer::tag('dd', $value, ['class' => 'col-sm-9']);
}
echo html_writer::tag('dl', $dl, ['class' => 'row']);

$courseids = plan_manager::courses_for_plan($id);
if (!$courseids) {
    echo $OUTPUT->notification(get_string('nocoursescovered', 'local_lmssub'), 'warning');
} else {
    [$insql, $params] = $DB->get_in_or_equal($courseids);
    $courses = $DB->get_records_sql("SELECT c.id, c.fullname, c.shortname, c.visible, cc.name AS categoryname
                                       FROM {course} c
                                       JOIN {course_categories} cc ON cc.id = c.category
                                      WHERE c.id $insql
                                   ORDER BY cc.sortorder, c.sortorder", $params);
    $table = new html_table();
    $table->head = [get_string('course'), get_string('shortname'), get_string('category'), get_string('visible')];
    foreach ($courses as $course) {
        $table->data[] = [
            html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]), format_string($course->fullname)),
            format_string($course->shortname),
            format_string($course->categoryname),
            $course->visible ? get_string('yes') : get_string('no'),
        ];
    }
    echo html_writer::tag('p', get_string('coursescoveredcount', 'local_lmssub', count($courses)));
    echo html_writer::table($table);
}

if ($plan->type === plan_manager::TYPE_ALLACCESS && ($excluded = plan_manager::get_excluded_courses())) {
    echo $OUTPUT->notification(get_string('excludedcoursesnote', 'local_lmssub', count($excluded)), 'info');
}

echo $OUTPUT->single_button(new moodle_url('/local/lmssub/editplan.php', ['id' => $id]), get_string('edit'), 'get');
echo $OUTPUT->single_button(new moodle_url('/local/lmssub/plans.php'), get_string('back'), 'get');
echo $OUTPUT->footer();

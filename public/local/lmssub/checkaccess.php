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
 * Access checker: pick a user and a course to see why they do or do not have access.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\access_engine;

admin_externalpage_setup('local_lmssub_checkaccess');

$form = new \local_lmssub\form\override_form(null, ['checker' => true]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('checkaccess', 'local_lmssub'));
$form->display();

if ($data = $form->get_data()) {
    // Users picked one by one plus every member of the chosen cohorts.
    $userids = \local_lmssub\form\override_form::resolve_userids($data);
    $courseids = array_map('intval', (array) $data->courseids);
    $userfields = \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects;
    [$insql, $params] = $DB->get_in_or_equal($userids);
    $users = $DB->get_records_select('user', "id $insql AND deleted = 0", $params, 'lastname, firstname', "id, email, $userfields");
    [$insql, $params] = $DB->get_in_or_equal($courseids);
    $courses = $DB->get_records_select('course', "id $insql", $params, 'sortorder', 'id, fullname');

    $table = new html_table();
    $table->head = [get_string('user'), get_string('email'), get_string('course'),
        get_string('access', 'local_lmssub'), get_string('why', 'local_lmssub')];
    $table->attributes['class'] = 'generaltable table-sm';
    $yes = $no = 0;
    foreach ($users as $user) {
        foreach ($courses as $course) {
            $result = access_engine::explain((int) $user->id, (int) $course->id);
            if ($result['verdict']) {
                $yes++;
                $badge = html_writer::span(get_string('hasaccess', 'local_lmssub',
                    get_string('level_' . $result['level'], 'local_lmssub')), 'badge bg-success');
            } else {
                $no++;
                $badge = html_writer::span(get_string('noaccess', 'local_lmssub'), 'badge bg-warning text-dark');
            }
            $table->data[] = [
                html_writer::link(new moodle_url('/user/profile.php', ['id' => $user->id]), fullname($user)),
                s($user->email),
                html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]), format_string($course->fullname)),
                $badge,
                html_writer::alist($result['reasons'], ['class' => 'small mb-0']),
            ];
        }
    }
    echo $OUTPUT->heading(get_string('checkresults', 'local_lmssub'), 3);
    echo $OUTPUT->notification(get_string('checksummary', 'local_lmssub',
        (object) ['pairs' => $yes + $no, 'yes' => $yes, 'no' => $no]), 'info', false);
    echo html_writer::table($table);
}
echo $OUTPUT->footer();

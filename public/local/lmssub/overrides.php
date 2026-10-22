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
 * Admin "always allow" / "always block" for users in courses. A block always wins over a subscription.
 *
 * Several users and courses can be chosen at once; every user and course pair gets the override.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\access_engine;

admin_externalpage_setup('local_lmssub_overrides');

/**
 * Re-syncs each affected user, only for the courses whose override changed.
 *
 * @param array $affected userid => courseid[]
 */
function local_lmssub_overrides_resync(array $affected): void {
    foreach ($affected as $userid => $courseids) {
        access_engine::sync_user((int) $userid, array_values(array_unique($courseids)));
    }
}

// Delete one override, or every selected one.
$delete = optional_param('delete', 0, PARAM_INT);
$deleteids = optional_param_array('selected', [], PARAM_INT);
if (($delete || ($deleteids && optional_param('deleteselected', '', PARAM_RAW) !== '')) && confirm_sesskey()) {
    $ids = $delete ? [$delete] : $deleteids;
    [$insql, $params] = $DB->get_in_or_equal($ids);
    $overrides = $DB->get_records_select('local_lmssub_override', "id $insql", $params);
    $affected = [];
    foreach ($overrides as $override) {
        $affected[$override->userid][] = (int) $override->courseid;
    }
    $DB->delete_records_select('local_lmssub_override', "id $insql", $params);
    local_lmssub_overrides_resync($affected);
    redirect($PAGE->url, get_string('overridesremoved', 'local_lmssub', count($overrides)), null,
        \core\output\notification::NOTIFY_SUCCESS);
} else if ($deleteids === [] && optional_param('deleteselected', '', PARAM_RAW) !== '') {
    redirect($PAGE->url, get_string('nothingselected', 'local_lmssub'), null, \core\output\notification::NOTIFY_WARNING);
}

$form = new \local_lmssub\form\override_form();
if ($data = $form->get_data()) {
    // Users picked one by one plus every member of the chosen cohorts.
    $userids = \local_lmssub\form\override_form::resolve_userids($data);
    [$insql, $params] = $DB->get_in_or_equal(array_map('intval', (array) $data->courseids));
    $courseids = $DB->get_fieldset_select('course', 'id', "id $insql AND id <> " . SITEID, $params);

    $values = ['action' => $data->action, 'reason' => $data->reason, 'actorid' => $USER->id, 'timecreated' => time()];
    $added = $updated = 0;
    $affected = [];
    $transaction = $DB->start_delegated_transaction();
    foreach ($userids as $userid) {
        $existing = $DB->get_records_menu('local_lmssub_override', ['userid' => $userid], '', 'courseid, id');
        foreach ($courseids as $courseid) {
            if (isset($existing[$courseid])) {
                $DB->update_record('local_lmssub_override', (object) (['id' => $existing[$courseid]] + $values));
                $updated++;
            } else {
                $DB->insert_record('local_lmssub_override', (object) (['userid' => $userid, 'courseid' => $courseid]
                    + $values));
                $added++;
            }
            $affected[$userid][] = (int) $courseid;
        }
    }
    $transaction->allow_commit();
    local_lmssub_overrides_resync($affected);

    redirect($PAGE->url, get_string('overridessaved', 'local_lmssub', (object) ['added' => $added, 'updated' => $updated,
        'users' => count($userids), 'courses' => count($courseids)]), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overrides', 'local_lmssub'));
echo html_writer::tag('p', get_string('overrides_desc', 'local_lmssub'));
$form->display();

$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$overrides = $DB->get_records_sql("SELECT o.*, c.fullname AS coursename, u.email, $userfields
                                     FROM {local_lmssub_override} o
                                     JOIN {user} u ON u.id = o.userid
                                     JOIN {course} c ON c.id = o.courseid
                                 ORDER BY o.timecreated DESC, u.lastname, u.firstname");
if ($overrides) {
    echo $OUTPUT->heading(get_string('currentoverrides', 'local_lmssub', count($overrides)), 3);

    $selectall = html_writer::checkbox('selectall', 1, false, '', ['id' => 'lmssub-selectall',
        'title' => get_string('selectall'), 'class' => 'form-check-input']);
    $table = new html_table();
    $table->head = [$selectall, get_string('user'), get_string('email'), get_string('course'),
        get_string('overrideaction', 'local_lmssub'), get_string('reason', 'local_lmssub'), get_string('date'),
        get_string('actions')];
    $table->attributes['class'] = 'generaltable table-sm';
    foreach ($overrides as $override) {
        $badge = $override->action === 'block' ? 'badge bg-danger' : 'badge bg-success';
        $table->data[] = [
            html_writer::checkbox('selected[]', $override->id, false, '', ['class' => 'form-check-input lmssub-select']),
            fullname($override),
            s($override->email),
            format_string($override->coursename),
            html_writer::span(get_string('override_' . $override->action, 'local_lmssub'), $badge),
            s($override->reason),
            userdate($override->timecreated, get_string('strftimedatetimeshort')),
            html_writer::link(new moodle_url($PAGE->url, ['delete' => $override->id, 'sesskey' => sesskey()]),
                get_string('delete')),
        ];
    }
    $button = html_writer::tag('button', get_string('deleteselected', 'local_lmssub'), ['type' => 'submit',
        'name' => 'deleteselected', 'value' => 1, 'class' => 'btn btn-danger',
        'data-confirm' => get_string('deleteselected_confirm', 'local_lmssub')]);
    echo html_writer::tag('form', html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',
        'value' => sesskey()]) . html_writer::table($table) . $button,
        ['method' => 'post', 'action' => $PAGE->url->out(false), 'id' => 'lmssub-overrides']);

    // Select-all checkbox and a confirmation before deleting.
    $PAGE->requires->js_amd_inline("
        const form = document.getElementById('lmssub-overrides');
        document.getElementById('lmssub-selectall').addEventListener('change', e => {
            form.querySelectorAll('.lmssub-select').forEach(box => { box.checked = e.target.checked; });
        });
        form.addEventListener('submit', e => {
            const button = form.querySelector('[name=deleteselected]');
            if (!window.confirm(button.dataset.confirm)) {
                e.preventDefault();
            }
        });
    ");
}
echo $OUTPUT->footer();

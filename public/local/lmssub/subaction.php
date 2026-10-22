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
 * View a subscription and its history; extend, cancel or revoke it.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\form\action_form;
use local_lmssub\form\grant_form;
use local_lmssub\plan_manager;
use local_lmssub\subscription_manager as subs;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'view', PARAM_ALPHA);
if (!in_array($action, ['view', 'extend', 'cancel', 'revoke'], true)) {
    $action = 'view';
}

$url = new moodle_url('/local/lmssub/subaction.php', ['id' => $id, 'action' => $action]);
admin_externalpage_setup('local_lmssub_subscribers', '', null, $url);

$sub = subs::get($id);
$plan = plan_manager::get_plan((int) $sub->planid);
$user = core_user::get_user($sub->userid, '*', MUST_EXIST);
$viewurl = new moodle_url('/local/lmssub/subaction.php', ['id' => $id, 'action' => 'view']);

$targets = ['extend' => subs::STATUS_ACTIVE, 'cancel' => subs::STATUS_CANCELLED, 'revoke' => subs::STATUS_REVOKED];
$form = null;
if ($action !== 'view') {
    if (!subs::can_transition($sub->status, $targets[$action])) {
        redirect($viewurl, get_string('invalidtransition', 'local_lmssub', (object) [
            'from' => subs::get_status_name($sub->status), 'to' => subs::get_status_name($targets[$action])]),
            null, \core\output\notification::NOTIFY_ERROR);
    }
    $form = new action_form($url, ['action' => $action]);
    $form->set_data(['id' => $id, 'action' => $action]);
    if ($form->is_cancelled()) {
        redirect($viewurl);
    } else if ($data = $form->get_data()) {
        switch ($action) {
            case 'extend':
                subs::extend($sub, grant_form::get_days($data), $data->reason);
                break;
            case 'cancel':
                subs::cancel($sub, $data->reason);
                break;
            case 'revoke':
                subs::revoke($sub, $data->reason);
                break;
        }
        redirect($viewurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$PAGE->navbar->add(fullname($user));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('subscriptionof', 'local_lmssub', (object) [
    'user' => fullname($user), 'plan' => format_string($plan->name)]));

$fmt = get_string('strftimedatetime');
$details = [
    get_string('user') => html_writer::link(new moodle_url('/user/profile.php', ['id' => $user->id]), fullname($user)) .
        ' (' . s($user->email) . ')',
    get_string('plan', 'local_lmssub') => format_string($plan->name),
    get_string('level', 'local_lmssub') => get_string('level_' . $sub->level, 'local_lmssub'),
    get_string('status') => subs::get_status_name($sub->status),
    get_string('startdate', 'local_lmssub') => $sub->timestart ? userdate($sub->timestart, $fmt) : '-',
    get_string('enddate', 'local_lmssub') => $sub->timeend ? userdate($sub->timeend, $fmt) : '-',
    get_string('accessuntil', 'local_lmssub') => ($end = \local_lmssub\access_engine::effective_end($sub, $plan))
        ? userdate($end, $fmt) : '-',
    get_string('source', 'local_lmssub') => get_string('source_' . $sub->source, 'local_lmssub'),
];
$dl = '';
foreach ($details as $label => $value) {
    $dl .= html_writer::tag('dt', $label, ['class' => 'col-sm-3']) . html_writer::tag('dd', $value, ['class' => 'col-sm-9']);
}
echo html_writer::tag('dl', $dl, ['class' => 'row']);

if ($form) {
    echo $OUTPUT->heading(get_string('action_' . $action, 'local_lmssub'), 3);
    $form->display();
} else {
    $buttons = '';
    foreach ($targets as $name => $to) {
        if (subs::can_transition($sub->status, $to)) {
            $buttons .= $OUTPUT->single_button(new moodle_url('/local/lmssub/subaction.php', ['id' => $id, 'action' => $name]),
                get_string('action_' . $name, 'local_lmssub'), 'get');
        }
    }
    echo html_writer::div($buttons, 'd-flex gap-2 mb-3');
}

echo $OUTPUT->heading(get_string('history', 'local_lmssub'), 3);
$table = new html_table();
$table->head = [get_string('date'), get_string('action', 'local_lmssub'), get_string('status'),
    get_string('enddate', 'local_lmssub'), get_string('by', 'local_lmssub'), get_string('reason', 'local_lmssub')];
foreach (subs::get_events($id) as $event) {
    $actor = $event->actorid ? core_user::get_user($event->actorid) : null;
    $table->data[] = [
        userdate($event->timecreated, get_string('strftimedatetimeshort')),
        get_string('event_' . $event->action, 'local_lmssub'),
        ($event->fromstatus ? subs::get_status_name($event->fromstatus) . ' → ' : '') .
            ($event->tostatus ? subs::get_status_name($event->tostatus) : ''),
        $event->timeend ? userdate($event->timeend, get_string('strftimedatetimeshort')) : '-',
        $actor ? fullname($actor) : get_string('system', 'local_lmssub'),
        s($event->reason),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->single_button(new moodle_url('/local/lmssub/subscribers.php'), get_string('back'), 'get');
echo $OUTPUT->footer();

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
 * Make the student of a payment attempt a subscriber of the plan they tried to buy.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\attempt_manager;
use local_lmssub\form\attempt_grant_form;
use local_lmssub\form\grant_form;
use local_lmssub\plan_manager;

$source = required_param('source', PARAM_ALPHA);
$id = required_param('id', PARAM_INT);
$returnurl = optional_param('returnurl', '/local/lmssub/attempts.php', PARAM_LOCALURL);

$url = new moodle_url('/local/lmssub/markattempt.php', ['source' => $source, 'id' => $id]);
admin_externalpage_setup('local_lmssub_attempts', '', null, $url);
require_capability('local/lmssub:managesubscribers', context_system::instance());

$attempt = attempt_manager::get_attempt($source, $id);
$user = core_user::get_user($attempt->userid, '*', MUST_EXIST);
$plan = plan_manager::get_plan($attempt->planid);
$until = attempt_manager::subscribed_until($attempt->userid, $attempt->planid);
$back = new moodle_url($returnurl);

$form = new attempt_grant_form($url, ['subscribed' => (bool) $until]);
$presets = plan_manager::get_duration_presets();
$form->set_data([
    'source' => $source,
    'id' => $id,
    'returnurl' => $returnurl,
    // The duration the student tried to buy.
    'durationpreset' => isset($presets[$attempt->durationdays]) ? $attempt->durationdays : 0,
    'customdays' => isset($presets[$attempt->durationdays]) ? '' : $attempt->durationdays,
    'reason' => get_string('markassubscriber_reason', 'local_lmssub', (object) [
        'reference' => $attempt->reference ?: $attempt->id,
        'gateway' => get_string('gateway_' . $source, 'local_lmssub'),
    ]),
]);

if ($form->is_cancelled()) {
    redirect($back);
} else if ($data = $form->get_data()) {
    attempt_manager::mark_as_subscriber($attempt, grant_form::get_days($data), $data->reason);
    redirect($back, get_string('markedassubscriber', 'local_lmssub', (object) [
        'user' => fullname($user), 'plan' => format_string($plan->name),
    ]), null, \core\output\notification::NOTIFY_SUCCESS);
}

$title = get_string($until ? 'extendsubscription' : 'markassubscriber', 'local_lmssub');
$PAGE->navbar->add(get_string('paymentattempts', 'local_lmssub'), $back);
$PAGE->navbar->add($title);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$summary = new html_table();
$summary->attributes['class'] = 'generaltable w-auto';
$summary->data = [
    [get_string('student', 'local_lmssub'), $OUTPUT->user_picture($user, ['size' => 35]) . ' ' . fullname($user) .
        html_writer::div(s($user->email), 'small text-body-secondary')],
    [get_string('plan', 'local_lmssub'), format_string($plan->name) . ' &middot; ' .
        plan_manager::duration_label($attempt->durationdays)],
    [get_string('attemptamount', 'local_lmssub'), plan_manager::format_amount($attempt->amount, $attempt->currency)],
    [get_string('status'), attempt_manager::status_name($attempt->status)],
    [get_string('gateway', 'local_lmssub'), get_string('gateway_' . $source, 'local_lmssub') .
        ($attempt->reference ? ' &middot; ' . html_writer::span(s($attempt->reference), 'font-monospace') : '')],
    [get_string('attemptdate', 'local_lmssub'), userdate($attempt->timecreated)],
];
echo html_writer::table($summary);

if (in_array($attempt->status, attempt_manager::PAID_STATUSES, true)) {
    echo $OUTPUT->notification(get_string('markassubscriber_paid', 'local_lmssub'), 'info');
}
if ($reviewurl = attempt_manager::review_url($attempt)) {
    echo $OUTPUT->notification(get_string('markassubscriber_review', 'local_lmssub', $reviewurl->out(false)), 'warning');
}
if ($until) {
    echo $OUTPUT->notification(get_string('markassubscriber_extend', 'local_lmssub',
        userdate($until, get_string('strftimedate', 'langconfig'))), 'info');
}
echo html_writer::tag('p', get_string('markassubscriber_desc', 'local_lmssub'));

$form->display();
echo $OUTPUT->footer();

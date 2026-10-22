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
 * Renew a paid (enrolment on payment) course enrolment, before or after it expires.
 *
 * The enrolment page hides the payment button once a user has an enrolment, even an expired one, so this page
 * offers the same payment again for existing students.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');

use paygw_razorpay\expiry_manager;

$id = required_param('id', PARAM_INT); // Fee enrolment instance id.

$instance = $DB->get_record('enrol', ['id' => $id, 'enrol' => 'fee'], '*', MUST_EXIST);
$course = get_course($instance->courseid);
$context = context_course::instance($course->id);

// No course in require_login(): an expired student can no longer access the course itself.
require_login();
if (isguestuser()) {
    redirect(get_login_url());
}

$PAGE->set_url(expiry_manager::get_renew_url($id));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$coursename = format_string($course->fullname, true, ['context' => $context]);
$PAGE->set_title(get_string('renewtitle', 'paygw_razorpay', $coursename));
$PAGE->set_heading($coursename);

$ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $USER->id]);
if (!$ue) {
    // Not enrolled through this method (or already unenrolled): the normal enrolment page has the payment button.
    redirect(new moodle_url('/enrol/index.php', ['id' => $course->id]));
}

$cost = (float) $instance->cost > 0 ? (float) $instance->cost : (float) get_config('enrol_fee', 'cost');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('renewtitle', 'paygw_razorpay', $coursename));

if ($instance->status != ENROL_INSTANCE_ENABLED || abs($cost) < 0.01) {
    echo $OUTPUT->notification(get_string('renewalunavailable', 'paygw_razorpay'), 'error');
    echo $OUTPUT->footer();
    die();
}

$now = time();
$dateformat = get_string('strftimedatetime', 'langconfig');
if (!$ue->timeend) {
    $status = get_string('renewstatusunlimited', 'paygw_razorpay');
} else if ($ue->timeend > $now && $ue->status == ENROL_USER_ACTIVE) {
    $status = get_string('renewstatusactive', 'paygw_razorpay', userdate($ue->timeend, $dateformat));
} else {
    $status = get_string('renewstatusexpired', 'paygw_razorpay', userdate($ue->timeend, $dateformat));
}

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->data = [
    [get_string('course'), $coursename],
    [get_string('renewcurrentstatus', 'paygw_razorpay'), $status],
    [get_string('renewperiod', 'paygw_razorpay'),
        $instance->enrolperiod ? format_time($instance->enrolperiod) : get_string('unlimited')],
    [get_string('renewnewexpiry', 'paygw_razorpay'), $instance->enrolperiod ?
        userdate(max($now, (int) $ue->timeend) + $instance->enrolperiod, $dateformat) : get_string('unlimited')],
    [get_string('cost'), \core_payment\helper::get_cost_as_string($cost, $instance->currency)],
];
echo html_writer::table($table);

$PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');
echo $OUTPUT->render(new single_button(
    $PAGE->url,
    get_string('renewnow', 'paygw_razorpay'),
    'post',
    single_button::BUTTON_PRIMARY,
    [
        'data-action' => 'core_payment/triggerPayment',
        'data-component' => 'enrol_fee',
        'data-paymentarea' => 'fee',
        'data-itemid' => $instance->id,
        'data-cost' => $cost,
        'data-successurl' => \enrol_fee\payment\service_provider::get_success_url('fee', $instance->id)->out(false),
        'data-description' => get_string('purchasedescription', 'enrol_fee', $coursename),
    ]
));

echo $OUTPUT->footer();

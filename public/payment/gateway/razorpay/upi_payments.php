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
 * UPI payments to the site's own UPI ID: admins check each UTR against the bank statement and approve it.
 *
 * @package    paygw_razorpay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use paygw_razorpay\razorpay_helper;
use paygw_razorpay\upi_manager;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$status = optional_param('status', upi_manager::STATUS_PENDING, PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$context = context_system::instance();
$url = new moodle_url('/payment/gateway/razorpay/upi_payments.php', ['status' => $status]);
$PAGE->set_url($url);
require_login();
require_capability('moodle/payment:manageaccounts', $context);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('upipayments', 'paygw_razorpay'));
$PAGE->set_heading(get_string('upipayments', 'paygw_razorpay'));

if ($action && $id && confirm_sesskey()) {
    if ($action === 'approve') {
        $done = upi_manager::approve($id, (int) $USER->id);
        $message = get_string($done ? 'upiapproveddone' : 'upialreadyreviewed', 'paygw_razorpay');
    } else if ($action === 'reject') {
        $done = upi_manager::reject($id, (int) $USER->id, optional_param('note', '', PARAM_TEXT));
        $message = get_string($done ? 'upirejecteddone' : 'upialreadyreviewed', 'paygw_razorpay');
    }
    if (isset($message)) {
        redirect($url, $message, null, $done ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('upipayments_desc', 'paygw_razorpay'), ['class' => 'text-muted']);

// One tab per status, with the number of payments waiting.
$tabs = [];
foreach ([upi_manager::STATUS_PENDING, upi_manager::STATUS_APPROVED, upi_manager::STATUS_REJECTED] as $tabstatus) {
    $label = get_string('upistatus_' . $tabstatus, 'paygw_razorpay');
    if ($tabstatus === upi_manager::STATUS_PENDING) {
        $label .= ' (' . $DB->count_records('paygw_razorpay_upi', ['status' => $tabstatus]) . ')';
    }
    $tabs[] = new tabobject($tabstatus, new moodle_url($url, ['status' => $tabstatus]), $label);
}
echo $OUTPUT->tabtree($tabs, $status);

$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$records = $DB->get_records_sql("SELECT r.*, u.email, $userfields
                                   FROM {paygw_razorpay_upi} r
                                   JOIN {user} u ON u.id = r.userid
                                  WHERE r.status = :status
                               ORDER BY COALESCE(r.timesubmitted, r.timecreated) DESC",
    ['status' => $status], 0, 200);

if (!$records) {
    echo $OUTPUT->notification(get_string('upinone', 'paygw_razorpay'), 'info', false);
    echo $OUTPUT->footer();
    die;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = [
    get_string('date'),
    get_string('user'),
    get_string('upiitem', 'paygw_razorpay'),
    get_string('upiamount', 'paygw_razorpay'),
    get_string('upiutr', 'paygw_razorpay'),
    get_string('upireference', 'paygw_razorpay'),
    get_string('upipaidto', 'paygw_razorpay'),
    $status === upi_manager::STATUS_PENDING ? get_string('actions') : get_string('upireviewed', 'paygw_razorpay'),
];

foreach ($records as $record) {
    [$item] = razorpay_helper::describe_item($record->component, $record->paymentarea, (int) $record->itemid);
    $user = html_writer::link(new moodle_url('/user/profile.php', ['id' => $record->userid]), fullname($record)) .
        html_writer::div(s($record->email), 'small text-muted');

    if ($status === upi_manager::STATUS_PENDING) {
        $approve = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline'])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $record->id])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'approve'])
            . html_writer::tag('button', get_string('upiapprove', 'paygw_razorpay'), ['type' => 'submit',
                'class' => 'btn btn-success btn-sm mb-1',
                'onclick' => 'return confirm(' . json_encode(get_string('upiapproveconfirm', 'paygw_razorpay', $record->utr)) . ')'])
            . html_writer::end_tag('form');
        $reject = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-flex gap-1'])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $record->id])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reject'])
            . html_writer::empty_tag('input', ['type' => 'text', 'name' => 'note', 'class' => 'form-control form-control-sm',
                'placeholder' => get_string('upirejectreason', 'paygw_razorpay'), 'maxlength' => 255])
            . html_writer::tag('button', get_string('upireject', 'paygw_razorpay'),
                ['type' => 'submit', 'class' => 'btn btn-outline-danger btn-sm'])
            . html_writer::end_tag('form');
        $last = $approve . $reject;
    } else {
        $reviewer = $record->reviewedby ? core_user::get_user($record->reviewedby) : null;
        $last = ($reviewer ? fullname($reviewer) : '') . html_writer::div(
            $record->timereviewed ? userdate($record->timereviewed, get_string('strftimedatetimeshort', 'langconfig')) : '',
            'small text-muted');
        if ($record->note) {
            $last .= html_writer::div(s($record->note), 'small');
        }
    }

    $table->data[] = [
        userdate($record->timesubmitted ?: $record->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        $user,
        s($item),
        upi_manager::format_amount($record),
        html_writer::tag('code', s($record->utr)),
        html_writer::tag('code', s($record->reference)),
        s($record->vpa),
        $last,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();

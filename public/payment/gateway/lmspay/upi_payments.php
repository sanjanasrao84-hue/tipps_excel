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
 * UPI payments to the account's own UPI ID: an admin checks each UTR in the bank account, then approves or rejects it.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use paygw_lmspay\order_manager;

admin_externalpage_setup('paygw_lmspay_upipayments');
$context = context_system::instance();
require_capability('moodle/payment:manageaccounts', $context);
$url = new moodle_url('/payment/gateway/lmspay/upi_payments.php');

$orderid = optional_param('orderid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
if ($orderid && in_array($action, ['approve', 'reject'], true)) {
    require_sesskey();
    if (order_manager::review_utr($orderid, $action === 'approve', (int) $USER->id)) {
        redirect($url, get_string($action === 'approve' ? 'upiapproveddone' : 'upirejecteddone', 'paygw_lmspay'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($url, get_string('upialreadyreviewed', 'paygw_lmspay'), null, \core\output\notification::NOTIFY_WARNING);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('upipayments', 'paygw_lmspay'));
echo html_writer::div(get_string('upipayments_desc', 'paygw_lmspay'), 'mb-3');

/**
 * Orders of a status as table rows.
 *
 * @param string $status
 * @param int $limit
 * @return html_table
 */
function paygw_lmspay_upi_table(string $status, int $limit): html_table {
    global $DB, $OUTPUT;
    $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $orders = $DB->get_records_sql("SELECT o.*, $userfields, u.email
                                      FROM {paygw_lmspay_order} o
                                      JOIN {user} u ON u.id = o.userid
                                     WHERE o.status = :status AND o.utr IS NOT NULL
                                  ORDER BY o.timemodified " . ($status === 'pending' ? 'ASC' : 'DESC'),
        ['status' => $status], 0, $limit);

    $table = new html_table();
    $table->head = [get_string('upisubmittedon', 'paygw_lmspay'), get_string('user'), get_string('upiitem', 'paygw_lmspay'),
        get_string('amount', 'paygw_lmspay'), get_string('utr', 'paygw_lmspay'), get_string('upiorder', 'paygw_lmspay')];
    if ($status === 'pending') {
        $table->head[] = get_string('actions');
    }
    $table->attributes['class'] = 'table table-striped generaltable';
    foreach ($orders as $order) {
        $row = [
            userdate($order->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
            html_writer::link(new moodle_url('/user/profile.php', ['id' => $order->userid]), fullname($order)) .
                html_writer::div(s($order->email), 'small text-muted'),
            s(order_manager::describe($order->component, $order->paymentarea, (int) $order->itemid)),
            html_writer::tag('strong', order_manager::format_amount($order)),
            html_writer::tag('code', s($order->utr), ['class' => 'fs-6']),
            s($order->reference),
        ];
        if ($status === 'pending') {
            $actionurl = new moodle_url('/payment/gateway/lmspay/upi_payments.php', ['orderid' => $order->id]);
            $approve = new single_button(new moodle_url($actionurl, ['action' => 'approve']),
                get_string('upiapprove', 'paygw_lmspay'), 'post', single_button::BUTTON_SUCCESS);
            $approve->add_confirm_action(get_string('upiapproveconfirm', 'paygw_lmspay',
                ['utr' => $order->utr, 'amount' => order_manager::format_amount($order)]));
            $reject = new single_button(new moodle_url($actionurl, ['action' => 'reject']),
                get_string('upireject', 'paygw_lmspay'), 'post', single_button::BUTTON_DANGER);
            $reject->add_confirm_action(get_string('upirejectconfirm', 'paygw_lmspay', $order->utr));
            $row[] = html_writer::div($OUTPUT->render($approve) . $OUTPUT->render($reject), 'd-flex gap-2');
        }
        $table->data[] = $row;
    }
    return $table;
}

$pending = paygw_lmspay_upi_table('pending', 0);
echo $OUTPUT->heading(get_string('upipending', 'paygw_lmspay', count($pending->data ?? [])), 3);
echo $pending->data ? html_writer::table($pending) :
    $OUTPUT->notification(get_string('upinopending', 'paygw_lmspay'), \core\output\notification::NOTIFY_INFO, false);

foreach (['paid' => 'upirecentapproved', 'rejected' => 'upirecentrejected'] as $status => $heading) {
    $table = paygw_lmspay_upi_table($status, 20);
    if ($table->data) {
        echo $OUTPUT->heading(get_string($heading, 'paygw_lmspay'), 3, 'mt-4');
        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();

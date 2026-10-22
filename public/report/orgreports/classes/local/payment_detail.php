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

namespace report_orgreports\local;

use report_orgreports\local\tables\table_request;
use report_orgreports\org_context;

/**
 * Everything recorded about one transaction, for the detail view.
 *
 * The transaction is looked up through the organisation's own payment query, so a transaction of another
 * organisation is "not found". Signatures, secrets and card numbers are never read; UPI IDs are masked and card
 * details are reduced to the network.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_detail {

    /**
     * Mask a UPI ID: keep two characters and the handle (ab•••@bank).
     *
     * @param string|null $vpa
     * @return string
     */
    public static function mask_vpa(?string $vpa): string {
        $vpa = (string)$vpa;
        if (!str_contains($vpa, '@')) {
            return $vpa === '' ? '' : '•••';
        }
        [$name, $handle] = explode('@', $vpa, 2);
        return \core_text::substr($name, 0, 2) . '•••@' . $handle;
    }

    /**
     * Payment method detail without card digits or full UPI IDs.
     *
     * @param string|null $detail
     * @return string
     */
    public static function safe_method_detail(?string $detail): string {
        $detail = trim((string)$detail);
        if ($detail === '') {
            return '';
        }
        if (preg_match('/^UTR\s/i', $detail)) {
            return $detail;
        }
        if (str_contains($detail, '@')) {
            return self::mask_vpa($detail);
        }
        // Card details are "NETWORK •••• 1234": keep the network only.
        return trim(preg_replace('/[•*]+\s*\d+/u', '', $detail));
    }

    /**
     * Detail of a transaction of the organisation.
     *
     * @param org_context $org
     * @param string $txid e.g. PAY-5
     * @return array|null null when it does not exist or is not the organisation's
     */
    public static function get(org_context $org, string $txid): ?array {
        global $DB;
        if (!preg_match('/^(PAY|RZO|LPO|UPI)-(\d+)$/', $txid)) {
            return null;
        }
        $query = new payment_query($org, new table_request());
        [$from, $where, $params] = $query->sql(0, PHP_INT_MAX);
        $row = $DB->get_record_sql("SELECT " . payment_query::select_sql() . ", u.username, u.email, " .
            implode(', ', array_map(fn($f) => "u.$f", \core_user\fields::get_name_fields())) . "
            FROM $from WHERE $where AND t.txid = :txid", $params + ['txid' => $txid]);
        if (!$row) {
            return null;
        }
        $identity = has_capability('moodle/site:viewuseridentity', $org->context);
        $statuses = payment_query::get_statuses() + ['partrefunded' => get_string('pstatus_partrefunded',
            'report_orgreports')];
        $status = payment_query::display_status($row);
        $money = fn($v) => \core_payment\helper::get_cost_as_string((float)$v, (string)$row->currency);
        $date = fn($t) => $t ? userdate($t, get_string('strftimedatetime', 'langconfig')) : '';
        $gateway = get_string_manager()->string_exists('pluginname', 'paygw_' . $row->gateway) ?
            get_string('pluginname', 'paygw_' . $row->gateway) : $row->gateway;

        $sections = [];
        $sections['transaction'] = [
            [get_string('col_txid', 'report_orgreports'), $row->txid],
            [get_string('status'), $statuses[$status] ?? $status],
            [get_string('col_amount', 'report_orgreports'), $money($row->amount)],
            [get_string('col_datetime', 'report_orgreports'), $date($row->timecreated)],
            [get_string('col_gateway', 'report_orgreports'), $gateway],
            [get_string('col_method', 'report_orgreports'), payment_query::method_name((string)$row->method)],
            [get_string('col_source', 'report_orgreports'), get_string('source_' . $row->src, 'report_orgreports')],
        ];
        $customer = [[get_string('col_customer', 'report_orgreports'), fullname($row)]];
        if ($identity) {
            $customer[] = [get_string('email'), $row->email];
            $customer[] = [get_string('username'), $row->username];
        }
        $sections['customer'] = $customer;
        $item = $row->component === 'enrol_fee' ? format_string($row->coursename) :
            ($row->component === 'local_lmssub' ? format_string($row->planname) : $row->component);
        $sections['purchase'] = [
            [get_string('col_type', 'report_orgreports'), get_string($row->component === 'enrol_fee' ? 'itemtype_fee' :
                ($row->component === 'local_lmssub' ? 'itemtype_plan' : 'itemtype_other'), 'report_orgreports')],
            [get_string('col_courseorplan', 'report_orgreports'), $item],
            [get_string('organisation', 'report_orgreports'), $query->org_name($row)],
        ];

        // References and timeline from the gateway record (no signatures or secrets are selected).
        $refs = [];
        $timeline = [[$row->timecreated, get_string('tl_created', 'report_orgreports')]];
        $dbman = $DB->get_manager();
        if ($row->src === 'razorpay_order') {
            $o = $DB->get_record('paygw_razorpay_order', ['id' => $row->id], 'id, rp_orderid, status, paymentid, timecreated,
                timemodified');
            $refs[] = [get_string('ref_rporder', 'report_orgreports'), $o->rp_orderid];
            if ($o->paymentid && $dbman->table_exists('paygw_razorpay')) {
                $refs[] = [get_string('ref_rppayment', 'report_orgreports'),
                    $DB->get_field('paygw_razorpay', 'rp_paymentid', ['paymentid' => $o->paymentid], IGNORE_MULTIPLE)];
            }
            if ($o->timemodified != $o->timecreated) {
                $timeline[] = [$o->timemodified, get_string('tl_updated', 'report_orgreports', $o->status)];
            }
        } else if ($row->src === 'lmspay_order') {
            $o = $DB->get_record('paygw_lmspay_order', ['id' => $row->id], 'id, reference, provider, rp_orderid,
                rp_paymentid, utr, methoddetail, reviewedby, status, timecreated, timemodified');
            $refs[] = [get_string('ref_reference', 'report_orgreports'), $o->reference];
            foreach (['rp_orderid' => 'ref_rporder', 'rp_paymentid' => 'ref_rppayment', 'utr' => 'ref_utr'] as $f => $label) {
                if ($o->$f) {
                    $refs[] = [get_string($label, 'report_orgreports'), $o->$f];
                }
            }
            if ($detail = self::safe_method_detail($o->methoddetail)) {
                $sections['transaction'][] = [get_string('col_methoddetail', 'report_orgreports'), $detail];
            }
            if ($o->reviewedby) {
                $timeline[] = [$o->timemodified, get_string('tl_reviewed', 'report_orgreports', ['status' => $o->status,
                    'user' => fullname(\core_user::get_user($o->reviewedby))])];
            } else if ($o->timemodified != $o->timecreated) {
                $timeline[] = [$o->timemodified, get_string('tl_updated', 'report_orgreports', $o->status)];
            }
        } else if ($row->src === 'razorpay_upi') {
            $o = $DB->get_record('paygw_razorpay_upi', ['id' => $row->id], 'id, reference, vpa, utr, status, note, reviewedby,
                timesubmitted, timereviewed, timecreated');
            $refs[] = [get_string('ref_reference', 'report_orgreports'), $o->reference];
            if ($o->utr) {
                $refs[] = [get_string('ref_utr', 'report_orgreports'), $o->utr];
            }
            $sections['transaction'][] = [get_string('ref_payeevpa', 'report_orgreports'), self::mask_vpa($o->vpa)];
            if ($o->timesubmitted) {
                $timeline[] = [$o->timesubmitted, get_string('tl_submitted', 'report_orgreports')];
            }
            if ($o->timereviewed) {
                $timeline[] = [$o->timereviewed, get_string('tl_reviewed', 'report_orgreports', ['status' => $o->status,
                    'user' => $o->reviewedby ? fullname(\core_user::get_user($o->reviewedby)) : '-'])];
            }
        } else if ($row->src === 'payment' && $dbman->table_exists('paygw_razorpay')) {
            $ref = $DB->get_field('paygw_razorpay', 'rp_paymentid', ['paymentid' => $row->id], IGNORE_MULTIPLE);
            if ($ref) {
                $refs[] = [get_string('ref_rppayment', 'report_orgreports'), $ref];
            }
        }
        if ($row->paymentid) {
            $refs[] = [get_string('ref_moodlepayment', 'report_orgreports'), (string)$row->paymentid];
            $paid = $DB->get_field('payments', 'timecreated', ['id' => $row->paymentid]);
            if ($paid) {
                $timeline[] = [$paid, get_string('tl_paid', 'report_orgreports')];
            }
        }
        $sections['references'] = $refs;

        // Subscription and refund (local_lmssub).
        $refund = [];
        if ($row->paymentid && payment_query::has_refunds()) {
            $orders = $DB->get_records('local_lmssub_order', ['paymentid' => $row->paymentid], 'id',
                'id, subid, status, amount, currency, timecreated, timemodified');
            foreach ($orders as $order) {
                if ($order->status === 'refunded') {
                    $refund[] = [get_string('col_refund', 'report_orgreports'), $money($row->refunded)];
                    $refund[] = [get_string('refundedon', 'report_orgreports'), $date($order->timemodified)];
                    $timeline[] = [$order->timemodified, get_string('tl_refunded', 'report_orgreports')];
                }
                if ($order->subid && ($sub = $DB->get_record('local_lmssub_sub', ['id' => $order->subid], 'id, status,
                        timestart, timeend, graceend'))) {
                    $sections['subscription'] = [
                        [get_string('status'), (class_exists(\local_lmssub\subscription_manager::class) ?
                            (\local_lmssub\subscription_manager::get_statuses()[$sub->status] ?? $sub->status) : $sub->status)],
                        [get_string('col_timestart', 'report_orgreports'), $date($sub->timestart)],
                        [get_string('col_timeend', 'report_orgreports'), $date($sub->timeend)],
                    ];
                    foreach ($DB->get_records('local_lmssub_event', ['subid' => $sub->id], 'timecreated, id',
                            'id, action, fromstatus, tostatus, timecreated') as $event) {
                        $timeline[] = [$event->timecreated, get_string('tl_subscription', 'report_orgreports', [
                            'action' => $event->action, 'from' => $event->fromstatus ?: '-', 'to' => $event->tostatus ?: '-'])];
                    }
                }
            }
        }
        if (!$refund && payment_query::has_refunds() && $row->status === payment_source::COMPLETED) {
            $refund[] = [get_string('col_refund', 'report_orgreports'), get_string('norefund', 'report_orgreports')];
        }
        if ($refund) {
            $sections['refund'] = $refund;
        }

        // Other attempts of the same user for the same item (repeated attempts).
        $attempts = $DB->count_records_sql("SELECT COUNT(1) FROM $from WHERE $where AND t.userid = :auser
            AND t.component = :acomp AND t.itemid = :aitem", $params + ['auser' => $row->userid, 'acomp' => $row->component,
            'aitem' => $row->itemid]);
        if ($attempts > 1) {
            $sections['purchase'][] = [get_string('attemptsforitem', 'report_orgreports'), (string)$attempts];
        }

        usort($timeline, fn($a, $b) => $a[0] <=> $b[0]);
        $out = [];
        foreach ($sections as $key => $rows) {
            $out[] = ['key' => $key, 'title' => get_string('detail_' . $key, 'report_orgreports'),
                'rows' => array_map(fn($r) => ['label' => $r[0], 'value' => (string)$r[1]], array_values(array_filter($rows,
                    fn($r) => $r[1] !== null && $r[1] !== '')))];
        }
        return [
            'txid' => $row->txid,
            'status' => $status,
            'statuslabel' => $statuses[$status] ?? $status,
            'amount' => $money($row->amount),
            'sections' => $out,
            'timeline' => array_map(fn($t) => ['time' => $date($t[0]), 'text' => $t[1]], $timeline),
        ];
    }
}

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

use report_orgreports\org_context;

/**
 * Payments and payment attempts of an organisation.
 *
 * - Completed payments are the rows of the core {payments} table (only written by a gateway once money was received);
 *   revenue is only ever summed from there.
 * - Attempts come from the gateway order tables that exist (paygw_razorpay_order, paygw_lmspay_order,
 *   paygw_razorpay_upi) plus completed core payments that have no gateway order (e.g. PayPal), with one status:
 *   completed, pending (waiting for confirmation or review), initiated (checkout opened, never paid) or failed.
 *   Amounts of the order tables are minor units (paise) and are converted to major units.
 * - Refunds: only local_lmssub orders have a refunded status; no installed gateway records refunds.
 *
 * Payments belong to an organisation through what they paid for: the fee of one of its courses (enrol_fee) or a
 * subscription plan scoped to it (local_lmssub), see {@see org_context::sales_sql()}.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_source {

    /** @var string[] transaction id prefix of each source. */
    const PREFIXES = ['payment' => 'PAY-', 'razorpay_order' => 'RZO-', 'lmspay_order' => 'LPO-', 'razorpay_upi' => 'UPI-'];

    /** Statuses. */
    const COMPLETED = 'completed';
    /** Waiting for confirmation or review. */
    const PENDING = 'pending';
    /** Checkout opened, never paid. */
    const INITIATED = 'initiated';
    /** Rejected or failed. */
    const FAILED = 'failed';

    /**
     * Joins from a table alias with component, paymentarea and itemid to what was bought.
     *
     * @param string $alias
     * @return string
     */
    public static function item_joins(string $alias): string {
        global $DB;
        $sql = " LEFT JOIN {enrol} e ON $alias.component = 'enrol_fee' AND $alias.paymentarea = 'fee' AND e.id = $alias.itemid
                 LEFT JOIN {course} c ON c.id = e.courseid";
        if ($DB->get_manager()->table_exists('local_lmssub_price')) {
            $sql .= " LEFT JOIN {local_lmssub_price} pr ON $alias.component = 'local_lmssub' AND $alias.paymentarea = 'plan'
                                AND pr.id = $alias.itemid
                      LEFT JOIN {local_lmssub_plan} pl ON pl.id = pr.planid";
        }
        return $sql;
    }

    /**
     * Whether plan names can be joined.
     *
     * @return bool
     */
    public static function has_plans(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('local_lmssub_plan');
    }

    /**
     * Completed payments of the organisation in [start, end).
     *
     * Aliases: p payments, u user, c course (fees), pl plan (subscriptions).
     *
     * @param org_context $org
     * @param int $start
     * @param int $end
     * @return array [from, where, params]
     */
    public static function payments(org_context $org, int $start, int $end): array {
        [$salessql, $params] = $org->sales_sql();
        $from = "{payments} p JOIN {user} u ON u.id = p.userid" . self::item_joins('p');
        $params += ['pstart' => $start, 'pend' => $end];
        return [$from, "$salessql AND p.timecreated >= :pstart AND p.timecreated < :pend", $params];
    }

    /**
     * Amount of a payment as a number.
     *
     * @return string
     */
    public static function amount_sql(): string {
        global $DB;
        return $DB->sql_cast_char2real('p.amount');
    }

    /**
     * All payment attempts as one derived table "t" with: src, txid, id, userid, component, paymentarea, itemid,
     * amount (major units), currency, status, gateway, method, reference, paymentid, timecreated.
     *
     * @return string
     */
    public static function attempts_union(): string {
        global $DB;
        $dbman = $DB->get_manager();
        $status = fn(string $col) => "CASE WHEN $col IN ('paid', 'approved') THEN '" . self::COMPLETED . "'
            WHEN $col = 'pending' THEN '" . self::PENDING . "'
            WHEN $col IN ('rejected', 'failed') THEN '" . self::FAILED . "'
            ELSE '" . self::INITIATED . "' END";
        $parts = [];
        $linked = [];
        // A stable, searchable transaction id: source prefix and record id.
        $txid = fn(string $prefix, string $col) => $DB->sql_concat("'$prefix'", $DB->sql_cast_to_char($col)) . ' AS txid';
        // Razorpay payment id of a paid order (paygw_razorpay), shown and searched before the order id.
        $rzpayment = $dbman->table_exists('paygw_razorpay') ?
            '(SELECT MAX(rz.rp_paymentid) FROM {paygw_razorpay} rz WHERE rz.paymentid = ro.paymentid)' : 'NULL';
        $rzpaymentpp = $dbman->table_exists('paygw_razorpay') ?
            "COALESCE((SELECT MAX(rz.rp_paymentid) FROM {paygw_razorpay} rz WHERE rz.paymentid = pp.id), '')" : "''";
        if ($dbman->table_exists('paygw_razorpay_order')) {
            $parts[] = "SELECT 'razorpay_order' AS src, " . $txid(self::PREFIXES['razorpay_order'], 'ro.id') . ", ro.id,
                               ro.userid, ro.component, ro.paymentarea, ro.itemid,
                               ro.amount / 100.0 AS amount, ro.currency, " . $status('ro.status') . " AS status,
                               'razorpay' AS gateway, COALESCE(ro.method, '') AS method,
                               COALESCE($rzpayment, ro.rp_orderid) AS reference, ro.rp_orderid AS orderref,
                               ro.paymentid, ro.timecreated
                          FROM {paygw_razorpay_order} ro";
            $linked[] = "SELECT paymentid FROM {paygw_razorpay_order} WHERE paymentid IS NOT NULL";
        }
        if ($dbman->table_exists('paygw_lmspay_order')) {
            $parts[] = "SELECT 'lmspay_order' AS src, " . $txid(self::PREFIXES['lmspay_order'], 'lo.id') . ", lo.id, lo.userid, lo.component, lo.paymentarea, lo.itemid,
                               lo.amount / 100.0 AS amount, lo.currency, " . $status('lo.status') . " AS status,
                               'lmspay' AS gateway, COALESCE(lo.method, '') AS method,
                               COALESCE(lo.rp_paymentid, lo.utr, lo.reference) AS reference,
                               COALESCE(lo.rp_orderid, lo.reference) AS orderref, lo.paymentid, lo.timecreated
                          FROM {paygw_lmspay_order} lo";
            $linked[] = "SELECT paymentid FROM {paygw_lmspay_order} WHERE paymentid IS NOT NULL";
        }
        if ($dbman->table_exists('paygw_razorpay_upi')) {
            $parts[] = "SELECT 'razorpay_upi' AS src, " . $txid(self::PREFIXES['razorpay_upi'], 'up.id') . ", up.id, up.userid, up.component, up.paymentarea, up.itemid,
                               up.amount / 100.0 AS amount, up.currency, " . $status('up.status') . " AS status,
                               'razorpay' AS gateway, 'upi' AS method, COALESCE(up.utr, up.reference) AS reference,
                               up.reference AS orderref, up.paymentid, up.timecreated
                          FROM {paygw_razorpay_upi} up";
            $linked[] = "SELECT paymentid FROM {paygw_razorpay_upi} WHERE paymentid IS NOT NULL";
        }
        $notlinked = $linked ? 'WHERE pp.id NOT IN (' . implode(' UNION ', $linked) . ')' : '';
        $parts[] = "SELECT 'payment' AS src, " . $txid(self::PREFIXES['payment'], 'pp.id') . ", pp.id, pp.userid, pp.component, pp.paymentarea, pp.itemid,
                           " . $DB->sql_cast_char2real('pp.amount') . " AS amount, pp.currency,
                           '" . self::COMPLETED . "' AS status, pp.gateway, '' AS method, $rzpaymentpp AS reference,
                           '' AS orderref, pp.id AS paymentid,
                           pp.timecreated
                      FROM {payments} pp $notlinked";
        return '(' . implode(' UNION ALL ', $parts) . ') t';
    }

    /**
     * Payment attempts of the organisation in [start, end).
     *
     * Aliases: t attempt, u user, c course, pl plan.
     *
     * @param org_context $org
     * @param int $start
     * @param int $end
     * @return array [from, where, params]
     */
    public static function attempts(org_context $org, int $start, int $end): array {
        [$salessql, $params] = $org->sales_sql();
        $from = self::attempts_union() . " JOIN {user} u ON u.id = t.userid" . self::item_joins('t');
        $params += ['tstart' => $start, 'tend' => $end];
        return [$from, "$salessql AND t.timecreated >= :tstart AND t.timecreated < :tend", $params];
    }

    /**
     * Refunded subscription orders of the organisation in [start, end) (by last change), or null when not supported.
     *
     * @param org_context $org
     * @param int $start
     * @param int $end
     * @return array|null [count, totals by currency]
     */
    public static function refunds(org_context $org, int $start, int $end): ?array {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_lmssub_order')) {
            return null;
        }
        [$insql, $params] = $DB->get_in_or_equal($org->get_plan_ids(), SQL_PARAMS_NAMED, 'rp');
        $rows = $DB->get_records_sql("SELECT currency, COUNT(1) AS cnt, SUM(amount) AS total FROM {local_lmssub_order}
            WHERE status = 'refunded' AND planid $insql AND timemodified >= :rs AND timemodified < :re
            GROUP BY currency", $params + ['rs' => $start, 're' => $end]);
        $totals = [];
        $count = 0;
        foreach ($rows as $row) {
            $totals[$row->currency] = $row->total / 100;
            $count += $row->cnt;
        }
        return [$count, $totals];
    }

    /**
     * Status names.
     *
     * @return string[]
     */
    public static function get_statuses(): array {
        $result = [];
        foreach ([self::COMPLETED, self::PENDING, self::INITIATED, self::FAILED] as $status) {
            $result[$status] = get_string('paystatus_' . $status, 'report_orgreports');
        }
        return $result;
    }
}

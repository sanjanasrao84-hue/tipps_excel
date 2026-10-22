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

namespace report_orgreports\local\tables;

use report_orgreports\local\payment_query;

/**
 * Transaction list of the Payment Reports page.
 *
 * Customer e-mail is only shown to users who may see user identity (moodle/site:viewuseridentity).
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payments extends table {

    /** @var payment_query */
    private payment_query $query;

    /**
     * Constructor.
     *
     * @param \report_orgreports\local\metrics $metrics
     * @param table_request $request
     */
    public function __construct(\report_orgreports\local\metrics $metrics, table_request $request) {
        // Statuses are checked by the payment query, which supports the extra "refunded" filter.
        $status = $request->status;
        $request->status = '';
        parent::__construct($metrics, $request);
        $request->status = $status;
        $this->query = new payment_query($metrics->org, $request);
    }

    public static function is_allowed(\report_orgreports\org_context $org): bool {
        return $org->cansales;
    }

    public function has_course_filter(): bool {
        return false;
    }

    /**
     * Whether the user may see customer e-mail addresses.
     *
     * @return bool
     */
    public function can_view_identity(): bool {
        return has_capability('moodle/site:viewuseridentity', $this->org->context);
    }

    public function get_columns(): array {
        global $DB;
        $columns = [
            'date' => ['label' => get_string('col_datetime', 'report_orgreports'), 'format' => 'datetime',
                'sort' => 't.timecreated'],
            'txid' => ['label' => get_string('col_txid', 'report_orgreports'), 'format' => 'text', 'sort' => 't.txid'],
            'reference' => ['label' => get_string('col_gatewayref', 'report_orgreports'), 'format' => 'text',
                'sort' => 't.reference'],
        ];
        if ($this->org->is_all()) {
            $columns['organisation'] = ['label' => get_string('organisation', 'report_orgreports'), 'format' => 'text',
                'sort' => false];
        }
        $columns['customer'] = ['label' => get_string('col_customer', 'report_orgreports'), 'format' => 'text',
            'sort' => $DB->sql_fullname('u.firstname', 'u.lastname')];
        if ($this->can_view_identity()) {
            $columns['email'] = ['label' => get_string('email'), 'format' => 'text', 'sort' => 'u.email'];
        }
        $columns += [
            'item' => ['label' => get_string('col_courseorplan', 'report_orgreports'), 'format' => 'name', 'sort' => false],
            'method' => ['label' => get_string('col_method', 'report_orgreports'), 'format' => 'text', 'sort' => 't.method'],
            'amount' => ['label' => get_string('col_amount', 'report_orgreports'), 'format' => 'money', 'sort' => 't.amount'],
            'currency' => ['label' => get_string('currency'), 'format' => 'text', 'sort' => 't.currency'],
            'status' => ['label' => get_string('status'), 'format' => 'status', 'sort' => 't.status'],
            'refund' => ['label' => get_string('col_refund', 'report_orgreports'), 'format' => 'money',
                'sort' => payment_query::refund_sql()],
            'action' => ['label' => get_string('col_action', 'report_orgreports'), 'format' => 'action', 'sort' => false,
                'export' => false],
        ];
        return $columns;
    }

    protected function get_default_sort(): array {
        return ['date', 'DESC'];
    }

    public function get_statuses(): array {
        return payment_query::get_statuses();
    }

    public function count_rows(): int {
        global $DB;
        $range = $this->metrics->range;
        [$from, $where, $params] = $this->query->sql($range->start, $range->end);
        return $DB->count_records_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
    }

    public function get_rows(int $offset, int $limit): iterable {
        global $DB;
        $range = $this->metrics->range;
        [$from, $where, $params] = $this->query->sql($range->start, $range->end);
        [$sort, $dir] = $this->get_sort();
        $rs = $DB->get_recordset_sql("SELECT " . payment_query::select_sql() . ", u.email, " . self::name_selects() . "
            FROM $from WHERE $where
            ORDER BY " . $this->get_columns()[$sort]['sort'] . " $dir, t.timecreated DESC, t.txid", $params, $offset, $limit);
        $statuses = payment_query::get_statuses() + ['partrefunded' => get_string('pstatus_partrefunded',
            'report_orgreports')];
        foreach ($rs as $r) {
            $status = payment_query::display_status($r);
            yield [
                'date' => $r->timecreated,
                'txid' => $r->txid,
                'reference' => $r->reference,
                'organisation' => $this->query->org_name($r),
                'customer' => self::fullname($r),
                'email' => $r->email,
                'item' => $r->component === 'enrol_fee' ? $r->coursename : ($r->component === 'local_lmssub' ?
                    $r->planname : $r->component),
                'method' => payment_query::method_name((string)$r->method),
                'amount' => $r->amount,
                'currency' => $r->currency,
                'status' => $statuses[$status] ?? $status,
                'statuskey' => $status,
                'refund' => (float)$r->refunded > 0 ? $r->refunded : null,
                'action' => $r->txid,
            ];
        }
        $rs->close();
    }
}

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
 * The filtered transactions of the Payment Reports page.
 *
 * Calculation rules (see also {@see payment_source}):
 * - A transaction is one payment attempt: a gateway order (Razorpay order, lmspay order, Razorpay UPI request) or a
 *   core payment that has no gateway order. A paid gateway order and its core payment are one transaction, never two.
 * - Successful means a row in the core payments table, which gateways only write once the money was received; an
 *   order or subscription record on its own is never treated as a payment.
 * - Refunds are the local_lmssub orders marked refunded, joined to their payment once (grouped by payment) and capped
 *   at the payment amount, so a refund is never counted twice or above what was paid.
 * - Amounts of gateway tables are minor units (paise) and are converted to major units.
 *
 * Every filter value is checked against the organisation's own data before it is used.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_query {

    /** Status filter for completed payments that were (fully or partly) refunded. */
    const REFUNDED = 'refunded';

    /** @var org_context */
    public readonly org_context $org;

    /** @var table_request filters (status, method, courseid, planid, search) */
    public readonly table_request $filters;

    /** @var array|null cache of org names per category and plan. */
    private ?array $orgnames = null;

    /**
     * Constructor: drops filter values that are not offered for this organisation.
     *
     * @param org_context $org
     * @param table_request $filters
     */
    public function __construct(org_context $org, table_request $filters) {
        $this->org = $org;
        $this->filters = $filters;
        if ($filters->status !== '' && !isset(self::get_statuses()[$filters->status])) {
            $filters->status = '';
        }
        if ($filters->method !== '' && !isset($this->get_methods()[$filters->method])) {
            $filters->method = '';
        }
        if ($filters->courseid && !isset($this->get_courses()[$filters->courseid])) {
            $filters->courseid = 0;
        }
        if ($filters->planid && !isset($this->get_plans()[$filters->planid])) {
            $filters->planid = 0;
        }
    }

    /**
     * Whether refunds are recorded at all (local_lmssub orders).
     *
     * @return bool
     */
    public static function has_refunds(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('local_lmssub_order');
    }

    /**
     * Status filter options supported by the data model.
     *
     * @return string[]
     */
    public static function get_statuses(): array {
        $statuses = [
            payment_source::COMPLETED => get_string('pstatus_completed', 'report_orgreports'),
            payment_source::PENDING => get_string('pstatus_pending', 'report_orgreports'),
            payment_source::FAILED => get_string('pstatus_failed', 'report_orgreports'),
            payment_source::INITIATED => get_string('pstatus_initiated', 'report_orgreports'),
        ];
        if (self::has_refunds()) {
            $statuses[self::REFUNDED] = get_string('pstatus_refunded', 'report_orgreports');
        }
        return $statuses;
    }

    /**
     * Readable payment method.
     *
     * @param string $method
     * @return string
     */
    public static function method_name(string $method): string {
        $key = 'method_' . ($method === '' ? 'none' : $method);
        return get_string_manager()->string_exists($key, 'report_orgreports') ? get_string($key, 'report_orgreports') :
            $method;
    }

    /**
     * Payment methods that occur in the organisation's transactions (any time).
     *
     * @return string[] value => name ('none' for not recorded)
     */
    public function get_methods(): array {
        global $DB;
        static $cache = [];
        $key = $this->org->id;
        if (!isset($cache[$key])) {
            [$from, $where, $params] = payment_source::attempts($this->org, 0, PHP_INT_MAX);
            $methods = $DB->get_fieldset_sql("SELECT DISTINCT t.method FROM $from WHERE $where", $params);
            $result = [];
            foreach ($methods as $method) {
                $value = $method === '' || $method === null ? 'none' : $method;
                $result[$value] = self::method_name($value === 'none' ? '' : $value);
            }
            asort($result);
            $cache[$key] = $result;
        }
        return $cache[$key];
    }

    /**
     * Courses of the organisation that can be paid for (a fee enrolment method).
     *
     * @return string[] id => name
     */
    public function get_courses(): array {
        global $DB;
        [$csql, $params] = $this->org->course_sql('e.courseid');
        $ids = $DB->get_fieldset_sql("SELECT DISTINCT e.courseid FROM {enrol} e WHERE e.enrol = 'fee' AND $csql", $params);
        $result = [];
        foreach ($this->org->get_courses() as $id => $course) {
            if (in_array($id, $ids)) {
                $result[$id] = format_string($course->fullname, true, ['context' => \context_course::instance($id)]);
            }
        }
        return $result;
    }

    /**
     * Subscription plans of the organisation.
     *
     * @return string[] id => name
     */
    public function get_plans(): array {
        global $DB;
        if (!payment_source::has_plans()) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($this->org->get_plan_ids(), SQL_PARAMS_NAMED, 'qp');
        return array_map(fn($n) => format_string($n), $DB->get_records_select_menu('local_lmssub_plan', "id $insql",
            $params, 'sortorder, name', 'id, name'));
    }

    /**
     * SQL of the refund amount of a transaction (0 when none), capped at the amount paid.
     *
     * @return string
     */
    public static function refund_sql(): string {
        if (!self::has_refunds()) {
            return '0';
        }
        return 'CASE WHEN rf.paymentid IS NULL THEN 0 WHEN rf.refundminor / 100.0 > t.amount THEN t.amount
                     ELSE rf.refundminor / 100.0 END';
    }

    /**
     * The filtered transactions of a period.
     *
     * Aliases: t transaction (see payment_source::attempts_union), u user, c course, pl plan, pr price, rf refunds.
     *
     * @param int $start
     * @param int $end
     * @return array [from, where, params]
     */
    public function sql(int $start, int $end): array {
        global $DB;
        [$from, $where, $params] = payment_source::attempts($this->org, $start, $end);
        if (self::has_refunds()) {
            // One row per refunded payment, so a payment joins to its refunds once.
            $from .= " LEFT JOIN (SELECT paymentid, SUM(amount) AS refundminor, MAX(timemodified) AS refundtime,
                                         COUNT(1) AS refundcount
                                    FROM {local_lmssub_order}
                                   WHERE status = 'refunded' AND paymentid IS NOT NULL
                                GROUP BY paymentid) rf ON t.status = '" . payment_source::COMPLETED . "'
                                AND rf.paymentid = t.paymentid";
        }
        $where = [$where];
        $f = $this->filters;
        if ($f->status === self::REFUNDED) {
            $where[] = 'rf.paymentid IS NOT NULL';
        } else if ($f->status !== '') {
            $where[] = 't.status = :qstatus';
            $params['qstatus'] = $f->status;
        }
        if ($f->method === 'none') {
            $where[] = "(t.method = '' OR t.method IS NULL)";
        } else if ($f->method !== '') {
            $where[] = 't.method = :qmethod';
            $params['qmethod'] = $f->method;
        }
        if ($f->courseid) {
            $cond = 'e.courseid = :qcourse';
            $params['qcourse'] = $f->courseid;
            if (payment_source::has_plans()) {
                [$psql, $pparams] = $DB->get_in_or_equal($this->org->get_course_plan_ids($f->courseid), SQL_PARAMS_NAMED,
                    'qcp');
                $cond = "($cond OR pr.planid $psql)";
                $params += $pparams;
            }
            $where[] = $cond;
        }
        if ($f->planid) {
            $where[] = 'pr.planid = :qplan';
            $params['qplan'] = $f->planid;
        }
        if ($f->search !== '') {
            $columns = ['t.txid', 't.reference', 't.orderref', $DB->sql_fullname('u.firstname', 'u.lastname'), 'u.email', 'c.fullname'];
            if (payment_source::has_plans()) {
                $columns[] = 'pl.name';
            }
            $likes = [];
            foreach ($columns as $i => $column) {
                $likes[] = $DB->sql_like($column, ":qsearch$i", false, false);
                $params["qsearch$i"] = '%' . $DB->sql_like_escape($f->search) . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }
        return [$from, implode(' AND ', $where), $params];
    }

    /**
     * Fields every consumer needs.
     *
     * @return string
     */
    public static function select_sql(): string {
        $plan = payment_source::has_plans() ? 'pl.id AS planid, pl.name AS planname' : "0 AS planid, '' AS planname";
        return "t.src, t.txid, t.id, t.userid, t.component, t.paymentarea, t.itemid, t.amount, t.currency, t.status,
                t.gateway, t.method, t.reference, t.paymentid, t.timecreated, " . self::refund_sql() . " AS refunded,
                c.id AS courseid, c.fullname AS coursename, c.category AS coursecategory, $plan";
    }

    /**
     * Status shown for a row: completed payments with a refund show as refunded (or partially refunded).
     *
     * @param \stdClass $row with status, amount, refunded
     * @return string
     */
    public static function display_status(\stdClass $row): string {
        if ($row->status === payment_source::COMPLETED && (float)$row->refunded > 0) {
            return (float)$row->refunded + 0.005 >= (float)$row->amount ? 'refunded' : 'partrefunded';
        }
        return $row->status;
    }

    /**
     * Organisation(s) a transaction belongs to: its course's organisation or its plan's organisations.
     *
     * @param \stdClass $row with coursecategory, planid
     * @return string
     */
    public function org_name(\stdClass $row): string {
        global $DB;
        if ($this->orgnames === null) {
            $categories = $DB->get_records('course_categories', null, '', 'id, name, path, parent');
            $bycategory = [];
            foreach ($categories as $category) {
                $top = (int)explode('/', trim($category->path, '/'))[0];
                $bycategory[$category->id] = format_string($categories[$top]->name ?? '');
            }
            $byplan = [];
            if (payment_source::has_plans()) {
                $rs = $DB->get_recordset_sql("SELECT s.id, s.planid, s.scopetype, s.targetid, c.category
                    FROM {local_lmssub_scope} s LEFT JOIN {course} c ON s.scopetype = 'course' AND c.id = s.targetid
                    WHERE s.scopetype IN ('course', 'category')");
                foreach ($rs as $scope) {
                    $cat = $scope->scopetype === 'course' ? $scope->category : $scope->targetid;
                    if ($cat && isset($bycategory[$cat])) {
                        $byplan[$scope->planid][$bycategory[$cat]] = true;
                    }
                }
                $rs->close();
            }
            $this->orgnames = ['category' => $bycategory, 'plan' => $byplan];
        }
        if (!empty($row->coursecategory)) {
            return $this->orgnames['category'][$row->coursecategory] ?? '';
        }
        if (!empty($row->planid)) {
            $names = array_keys($this->orgnames['plan'][$row->planid] ?? []);
            return $names ? implode(', ', $names) : get_string('allorganisations', 'report_orgreports');
        }
        return '';
    }
}

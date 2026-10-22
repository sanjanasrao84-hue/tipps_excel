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

use report_orgreports\local\payment_source;

/**
 * Transaction history: every payment attempt with its status.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transactions extends table {

    public static function is_allowed(\report_orgreports\org_context $org): bool {
        return $org->cansales;
    }

    public function get_columns(): array {
        global $DB;
        return [
            'date' => ['label' => get_string('date'), 'format' => 'datetime', 'sort' => 't.timecreated'],
            'user' => ['label' => get_string('user'), 'format' => 'text',
                'sort' => $DB->sql_fullname('u.firstname', 'u.lastname')],
            'email' => ['label' => get_string('email'), 'format' => 'text', 'sort' => 'u.email'],
            'item' => ['label' => get_string('col_item', 'report_orgreports'), 'format' => 'name', 'sort' => false],
            'itemtype' => ['label' => get_string('col_type', 'report_orgreports'), 'format' => 'text', 'sort' => 't.component'],
            'gateway' => ['label' => get_string('col_gateway', 'report_orgreports'), 'format' => 'text', 'sort' => 't.gateway'],
            'method' => ['label' => get_string('col_method', 'report_orgreports'), 'format' => 'text', 'sort' => 't.method'],
            'reference' => ['label' => get_string('col_reference', 'report_orgreports'), 'format' => 'text', 'sort' => false],
            'amount' => ['label' => get_string('col_amount', 'report_orgreports'), 'format' => 'money', 'sort' => 't.amount'],
            'currency' => ['label' => get_string('currency'), 'format' => 'text', 'sort' => 't.currency'],
            'status' => ['label' => get_string('status'), 'format' => 'status', 'sort' => 't.status'],
        ];
    }

    protected function get_default_sort(): array {
        return ['date', 'DESC'];
    }

    public function get_statuses(): array {
        return payment_source::get_statuses();
    }

    /**
     * Query parts.
     *
     * @return array [from, where, params]
     */
    private function get_sql(): array {
        global $DB;
        $range = $this->metrics->range;
        [$from, $where, $params] = payment_source::attempts($this->org, $range->start, $range->end);
        $where = [$where];
        if ($this->request->status !== '') {
            $where[] = 't.status = :tstatus';
            $params['tstatus'] = $this->request->status;
        }
        if ($this->request->courseid) {
            $cond = 'e.courseid = :tcourse';
            $params['tcourse'] = $this->request->courseid;
            if (payment_source::has_plans()) {
                [$psql, $pparams] = $DB->get_in_or_equal($this->org->get_course_plan_ids($this->request->courseid),
                    SQL_PARAMS_NAMED, 'tp');
                $cond = "($cond OR pr.planid $psql)";
                $params += $pparams;
            }
            $where[] = $cond;
        }
        $search = [$DB->sql_fullname('u.firstname', 'u.lastname'), 'u.email', 'c.fullname', 't.gateway', 't.reference'];
        if (payment_source::has_plans()) {
            $search[] = 'pl.name';
        }
        $this->add_search($search, $where, $params);
        return [$from, implode(' AND ', $where), $params];
    }

    public function count_rows(): int {
        global $DB;
        [$from, $where, $params] = $this->get_sql();
        return $DB->count_records_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
    }

    public function get_rows(int $offset, int $limit): iterable {
        global $DB;
        [$from, $where, $params] = $this->get_sql();
        [$sort, $dir] = $this->get_sort();
        $plan = payment_source::has_plans() ? 'pl.name' : "''";
        $rs = $DB->get_recordset_sql("SELECT t.src, t.id, t.timecreated, t.component, t.gateway, t.method, t.reference,
                t.amount, t.currency, t.status, u.email, " . self::name_selects() . ", c.fullname AS coursename,
                $plan AS planname
            FROM $from WHERE $where
            ORDER BY " . $this->get_columns()[$sort]['sort'] . " $dir, t.timecreated DESC, t.src, t.id", $params, $offset,
            $limit);
        $statuses = payment_source::get_statuses();
        foreach ($rs as $r) {
            $isfee = $r->component === 'enrol_fee';
            yield [
                'date' => $r->timecreated,
                'user' => self::fullname($r),
                'email' => $r->email,
                'item' => $isfee ? $r->coursename : ($r->component === 'local_lmssub' ? $r->planname : $r->component),
                'itemtype' => get_string($isfee ? 'itemtype_fee' : ($r->component === 'local_lmssub' ? 'itemtype_plan' :
                    'itemtype_other'), 'report_orgreports'),
                'gateway' => get_string_manager()->string_exists('pluginname', 'paygw_' . $r->gateway) ?
                    get_string('pluginname', 'paygw_' . $r->gateway) : $r->gateway,
                'method' => $r->method,
                'reference' => $r->reference,
                'amount' => $r->amount,
                'currency' => $r->currency,
                'status' => $statuses[$r->status] ?? $r->status,
                'statuskey' => $r->status,
            ];
        }
        $rs->close();
    }
}

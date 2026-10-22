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
 * Sales per course (course fees) and per subscription plan, from completed payments.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemsales extends table {

    /** @var array[]|null cached items. */
    private ?array $items = null;

    public static function is_allowed(\report_orgreports\org_context $org): bool {
        return $org->cansales;
    }

    public function get_columns(): array {
        return [
            'name' => ['label' => get_string('col_item', 'report_orgreports'), 'format' => 'name', 'sort' => true],
            'type' => ['label' => get_string('col_type', 'report_orgreports'), 'format' => 'text', 'sort' => true],
            'sales' => ['label' => get_string('col_sales', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'buyers' => ['label' => get_string('col_buyers', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'revenue' => ['label' => get_string('card_revenue', 'report_orgreports'), 'format' => 'totals', 'sort' => false],
            'lastsale' => ['label' => get_string('col_lastsale', 'report_orgreports'), 'format' => 'datetime', 'sort' => true],
        ];
    }

    protected function get_default_sort(): array {
        return ['sales', 'DESC'];
    }

    /**
     * Items sold in the period, filtered.
     *
     * @return array[]
     */
    public function get_items(): array {
        global $DB;
        if ($this->items !== null) {
            return $this->items;
        }
        $range = $this->metrics->range;
        [$from, $where, $params] = payment_source::payments($this->org, $range->start, $range->end);
        if ($this->request->courseid) {
            $cond = 'e.courseid = :icourse';
            $params['icourse'] = $this->request->courseid;
            if (payment_source::has_plans()) {
                [$psql, $pparams] = $DB->get_in_or_equal($this->org->get_course_plan_ids($this->request->courseid),
                    SQL_PARAMS_NAMED, 'ip');
                $cond = "($cond OR pr.planid $psql)";
                $params += $pparams;
            }
            $where .= " AND $cond";
        }
        $plan = payment_source::has_plans() ? 'pr.planid, pl.name AS planname' : '0 AS planid, \'\' AS planname';
        $rs = $DB->get_recordset_sql("SELECT p.id, p.userid, p.component, p.amount, p.currency, p.timecreated,
                c.id AS courseid, c.fullname AS coursename, $plan
            FROM $from WHERE $where", $params);
        $items = [];
        foreach ($rs as $p) {
            if ($p->component === 'enrol_fee' && $p->courseid) {
                [$key, $name, $type] = ['c' . $p->courseid, $p->coursename, get_string('itemtype_fee', 'report_orgreports')];
            } else if ($p->component === 'local_lmssub' && $p->planid) {
                [$key, $name, $type] = ['p' . $p->planid, $p->planname, get_string('itemtype_plan', 'report_orgreports')];
            } else {
                [$key, $name, $type] = ['o' . $p->component, $p->component, get_string('itemtype_other', 'report_orgreports')];
            }
            $items[$key] ??= ['name' => $name, 'type' => $type, 'sales' => 0, 'buyers' => [], 'revenue' => [],
                'lastsale' => 0];
            $items[$key]['sales']++;
            $items[$key]['buyers'][$p->userid] = true;
            $items[$key]['revenue'][$p->currency] = ($items[$key]['revenue'][$p->currency] ?? 0) + (float)$p->amount;
            $items[$key]['lastsale'] = max($items[$key]['lastsale'], (int)$p->timecreated);
        }
        $rs->close();
        $result = [];
        foreach ($items as $item) {
            if ($this->matches($item['name'], $item['type'])) {
                $item['buyers'] = count($item['buyers']);
                $result[] = $item;
            }
        }
        return $this->items = $result;
    }

    public function count_rows(): int {
        return count($this->get_items());
    }

    public function get_rows(int $offset, int $limit): iterable {
        return $this->sort_and_slice($this->get_items(), $offset, $limit);
    }
}

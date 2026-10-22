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

/**
 * Sales per day (or hour / month, following the period): revenue, payments and attempts by status.
 *
 * Uses the same series as the Sales cards and charts, so every row adds up to the figures above it.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class salesbydate extends table {

    public static function is_allowed(\report_orgreports\org_context $org): bool {
        return $org->cansales;
    }

    public function has_course_filter(): bool {
        return false;
    }

    public function get_columns(): array {
        return [
            'period' => ['label' => get_string('col_period', 'report_orgreports'), 'format' => 'bucket', 'sort' => true,
                'sortkey' => 'start'],
            'revenue' => ['label' => get_string('card_revenue', 'report_orgreports'), 'format' => 'totals', 'sort' => false],
            'completed' => ['label' => get_string('card_completed', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'payers' => ['label' => get_string('col_payers', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'pending' => ['label' => get_string('paystatus_pending', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'initiated' => ['label' => get_string('paystatus_initiated', 'report_orgreports'), 'format' => 'int',
                'sort' => true],
            'failed' => ['label' => get_string('paystatus_failed', 'report_orgreports'), 'format' => 'int', 'sort' => true],
        ];
    }

    protected function get_default_sort(): array {
        return ['period', 'DESC'];
    }

    /**
     * One row per bucket that had any payment activity.
     *
     * @return array[]
     */
    private function build(): array {
        $m = $this->metrics;
        $revenue = $m->revenue();
        $payments = $m->payments();
        $payers = $m->paid_users();
        [$attempts] = $m->attempts();
        $rows = [];
        foreach ($m->range->get_buckets() as $i => $bucket) {
            $totals = [];
            foreach ($revenue['series'] as $currency => $series) {
                if ($series[$i]) {
                    $totals[$currency] = $series[$i];
                }
            }
            $row = [
                'period' => $bucket['label'],
                'perioddate' => $bucket['date'],
                'start' => $bucket['start'],
                'revenue' => $totals,
                'completed' => $payments['series'][$i],
                'payers' => $payers['series'][$i],
                'pending' => $attempts['pending'][$i],
                'initiated' => $attempts['initiated'][$i],
                'failed' => $attempts['failed'][$i],
            ];
            if ($totals || $row['completed'] || $row['pending'] || $row['initiated'] || $row['failed']) {
                $rows[] = $row;
            }
        }
        return array_values(array_filter($rows, fn($r) => $this->matches($r['period'], $r['perioddate'])));
    }

    public function count_rows(): int {
        return count($this->build());
    }

    public function get_rows(int $offset, int $limit): iterable {
        return $this->sort_and_slice($this->build(), $offset, $limit);
    }
}

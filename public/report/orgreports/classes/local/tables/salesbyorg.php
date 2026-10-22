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

use report_orgreports\local\metrics;
use report_orgreports\org_context;

/**
 * Sales per organisation, shown in the "All organisations" view only.
 *
 * Each row is computed with that organisation's own context, so it holds exactly what its managers see.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class salesbyorg extends table {

    public static function is_allowed(org_context $org): bool {
        return $org->is_all() && $org->cansales;
    }

    public function has_course_filter(): bool {
        return false;
    }

    public function get_columns(): array {
        return [
            'organisation' => ['label' => get_string('organisation', 'report_orgreports'), 'format' => 'name',
                'sort' => true],
            'courses' => ['label' => get_string('card_courses', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'revenue' => ['label' => get_string('card_revenue', 'report_orgreports'), 'format' => 'totals', 'sort' => false],
            'completed' => ['label' => get_string('card_completed', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'payers' => ['label' => get_string('col_payers', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'failed' => ['label' => get_string('paystatus_failed', 'report_orgreports'), 'format' => 'int', 'sort' => true],
        ];
    }

    protected function get_default_sort(): array {
        return ['completed', 'DESC'];
    }

    /**
     * One row per organisation the user may open.
     *
     * @return array[]
     */
    private function build(): array {
        $rows = [];
        foreach (org_context::get_available_orgs() as $id => $name) {
            if ($id === org_context::ALL) {
                continue;
            }
            $org = org_context::for_user($id);
            if (!$org->cansales || !$this->matches($name)) {
                continue;
            }
            $m = new metrics($org, $this->metrics->range);
            [, $attempts] = $m->attempts();
            $rows[] = [
                'organisation' => $name,
                'courses' => count($org->get_courses()),
                'revenue' => $m->revenue()['total'],
                'completed' => $m->payments()['total'],
                'payers' => $m->paid_users()['total'],
                'failed' => $attempts['failed'],
            ];
        }
        return $rows;
    }

    public function count_rows(): int {
        return count($this->build());
    }

    public function get_rows(int $offset, int $limit): iterable {
        return $this->sort_and_slice($this->build(), $offset, $limit);
    }
}

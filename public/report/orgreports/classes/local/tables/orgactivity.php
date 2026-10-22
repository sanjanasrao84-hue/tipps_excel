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
 * Learning statistics per organisation, shown in the "All organisations" view only.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orgactivity extends table {

    public static function is_allowed(org_context $org): bool {
        return $org->is_all();
    }

    public function has_course_filter(): bool {
        return false;
    }

    public function get_columns(): array {
        return [
            'organisation' => ['label' => get_string('organisation', 'report_orgreports'), 'format' => 'name',
                'sort' => true],
            'courses' => ['label' => get_string('card_courses', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'learners' => ['label' => get_string('card_totallearners', 'report_orgreports'), 'format' => 'int',
                'sort' => true],
            'activelearners' => ['label' => get_string('card_activelearners', 'report_orgreports'), 'format' => 'int',
                'sort' => true],
            'enrolments' => ['label' => get_string('card_enrolments', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'completions' => ['label' => get_string('card_completions', 'report_orgreports'), 'format' => 'int',
                'sort' => true],
            'teacherevents' => ['label' => get_string('card_teacheractions', 'report_orgreports'), 'format' => 'int',
                'sort' => true],
            'learningtime' => ['label' => get_string('card_learningtime', 'report_orgreports'), 'format' => 'duration',
                'sort' => true],
        ];
    }

    protected function get_default_sort(): array {
        return ['learners', 'DESC'];
    }

    /**
     * One row per organisation the user may open.
     *
     * @return array[]
     */
    private function build(): array {
        $rows = [];
        foreach (org_context::get_available_orgs() as $id => $name) {
            if ($id === org_context::ALL || !$this->matches($name)) {
                continue;
            }
            $org = org_context::for_user($id);
            $m = new metrics($org, $this->metrics->range);
            $rows[] = [
                'organisation' => $name,
                'courses' => count($org->get_courses()),
                'learners' => count($m->current_learner_ids()),
                'activelearners' => $m->active_learners()['total'],
                'enrolments' => $m->enrolments()['total'],
                'completions' => $m->completions()['total'],
                'teacherevents' => $m->role_events(true)['total'],
                'learningtime' => $m->learning_time()['total'],
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

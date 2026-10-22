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

/**
 * Activity per course: learners, participation, enrolments, completions, learner and teacher actions.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class courseactivity extends table {

    public function get_columns(): array {
        return [
            'course' => ['label' => get_string('course'), 'format' => 'name', 'sort' => true],
            'learners' => ['label' => get_string('col_learners', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'activelearners' => ['label' => get_string('card_activelearners', 'report_orgreports'), 'format' => 'int',
                'sort' => true],
            'participation' => ['label' => get_string('col_participation', 'report_orgreports'), 'format' => 'percent',
                'sort' => true],
            'enrolments' => ['label' => get_string('card_enrolments', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'completions' => ['label' => get_string('card_completions', 'report_orgreports'), 'format' => 'int',
                'sort' => true],
            'learnerevents' => ['label' => get_string('card_learnerevents', 'report_orgreports'), 'format' => 'int',
                'sort' => true],
            'teacherevents' => ['label' => get_string('card_teacheractions', 'report_orgreports'), 'format' => 'int',
                'sort' => true],
            'learningtime' => ['label' => get_string('card_learningtime', 'report_orgreports'), 'format' => 'duration',
                'sort' => true],
            'lastactivity' => ['label' => get_string('col_lastactivity', 'report_orgreports'), 'format' => 'datetime',
                'sort' => true],
        ];
    }

    protected function get_default_sort(): array {
        return ['learners', 'DESC'];
    }

    /**
     * Run a "courseid, value" query over the organisation's courses.
     *
     * @param string $sql with {COURSES}
     * @param array $params
     * @return array
     */
    private function by_course(string $sql, array $params = []): array {
        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($this->org->get_course_ids($this->request->courseid), SQL_PARAMS_NAMED,
            'cac');
        return $DB->get_records_sql_menu(str_replace('{COURSES}', $insql, $sql), $params + $inparams);
    }

    /**
     * Figures for every course of the organisation (current filter).
     *
     * @return array[] courseid => row
     */
    public function get_stats(): array {
        global $DB;
        $range = $this->metrics->range;
        $period = ['ps' => $range->start, 'pe' => $range->end];
        $now = round(time(), -2);

        $roleids = array_keys(get_archetype_roles('student')) ?: [0];
        [$rolesql, $rparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'car');
        $learnercond = "EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {context} ctx ON ctx.id = ra.contextid
                         AND ctx.contextlevel = " . CONTEXT_COURSE . "
                         WHERE ctx.instanceid = e.courseid AND ra.userid = ue.userid AND ra.roleid $rolesql)";
        $learners = $this->by_course("SELECT e.courseid, COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid JOIN {user} u ON u.id = ue.userid
            WHERE e.courseid {COURSES} AND u.deleted = 0 AND ue.status = 0 AND e.status = 0
              AND ue.timestart <= $now AND (ue.timeend = 0 OR ue.timeend > $now) AND $learnercond
            GROUP BY e.courseid", $rparams);

        [$learnersql, $lparams] = metrics::learner_log_sql('l');
        [$realsql, $realparams] = $this->metrics->real_user_sql('l');
        $active = $this->by_course("SELECT l.courseid, COUNT(DISTINCT l.userid) FROM {logstore_standard_log} l
            WHERE l.courseid {COURSES} AND $realsql AND $learnersql AND l.timecreated >= :ps AND l.timecreated < :pe
            GROUP BY l.courseid", $lparams + $realparams + $period);
        $learnerevents = $this->by_course("SELECT l.courseid, COUNT(1) FROM {logstore_standard_log} l
            WHERE l.courseid {COURSES} AND $realsql AND $learnersql AND l.timecreated >= :ps AND l.timecreated < :pe
            GROUP BY l.courseid", $lparams + $realparams + $period);
        [$teachersql, $tparams] = metrics::teacher_log_sql('l');
        $teacherevents = $this->by_course("SELECT l.courseid, COUNT(1) FROM {logstore_standard_log} l
            WHERE l.courseid {COURSES} AND $realsql AND $teachersql AND l.timecreated >= :ps AND l.timecreated < :pe
            GROUP BY l.courseid", $tparams + $realparams + $period);
        $last = $this->by_course("SELECT l.courseid, MAX(l.timecreated) FROM {logstore_standard_log} l
            WHERE l.courseid {COURSES} AND $realsql AND l.timecreated < :pe GROUP BY l.courseid",
            $realparams + ['pe' => $range->end]);
        $enrolments = $this->by_course("SELECT e.courseid, COUNT(1) FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid JOIN {user} u ON u.id = ue.userid
            WHERE e.courseid {COURSES} AND u.deleted = 0 AND ue.timecreated >= :ps AND ue.timecreated < :pe
            GROUP BY e.courseid", $period);
        $completions = $this->by_course("SELECT cc.course, COUNT(1) FROM {course_completions} cc
            JOIN {user} u ON u.id = cc.userid
            WHERE cc.course {COURSES} AND u.deleted = 0 AND cc.timecompleted >= :ps AND cc.timecompleted < :pe
            GROUP BY cc.course", $period);
        $learning = $this->metrics->learning_time()['courses'];

        $rows = [];
        foreach ($this->org->get_courses() as $id => $course) {
            if ($this->request->courseid && $id != $this->request->courseid) {
                continue;
            }
            $count = (int)($learners[$id] ?? 0);
            $activecount = (int)($active[$id] ?? 0);
            $rows[$id] = [
                'id' => $id,
                'course' => $course->fullname,
                'learners' => $count,
                'activelearners' => $activecount,
                'participation' => $count ? min(100, $activecount / $count * 100) : null,
                'enrolments' => (int)($enrolments[$id] ?? 0),
                'completions' => (int)($completions[$id] ?? 0),
                'learnerevents' => (int)($learnerevents[$id] ?? 0),
                'teacherevents' => (int)($teacherevents[$id] ?? 0),
                'learningtime' => (int)($learning[$id] ?? 0),
                'lastactivity' => (int)($last[$id] ?? 0) ?: null,
            ];
        }
        return $rows;
    }

    /**
     * Rows after the search.
     *
     * @return array[]
     */
    private function filtered(): array {
        return array_values(array_filter($this->get_stats(), fn($r) => $this->matches($r['course'])));
    }

    public function count_rows(): int {
        return count($this->filtered());
    }

    public function get_rows(int $offset, int $limit): iterable {
        return $this->sort_and_slice($this->filtered(), $offset, $limit);
    }
}

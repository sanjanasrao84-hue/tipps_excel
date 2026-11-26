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

namespace theme_eadtraining\output;

use context_system;
use core_completion\progress;
use renderable;
use renderer_base;
use templatable;

/**
 * Read-only learning overview shown at the top of the Dashboard.
 *
 * Every figure comes from existing Moodle data; nothing is written.
 *
 * @package   theme_eadtraining
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class glass_dashboard implements renderable, templatable {

    /** @var int Days shown in the activity chart. */
    const ACTIVITY_DAYS = 14;

    /** @var int Weeks shown in the admin enrolment chart. */
    const ENROL_WEEKS = 8;

    /** @var \stdClass The user the overview is for. */
    protected $user;

    /**
     * Constructor.
     *
     * @param \stdClass $user
     */
    public function __construct(\stdClass $user) {
        $this->user = $user;
    }

    /**
     * Export for the theme_eadtraining/glass_dashboard template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = [
            'firstname' => format_string($this->user->firstname),
            'learner' => $this->learner_overview(),
        ];
        if (has_capability('moodle/site:config', context_system::instance(), $this->user)) {
            $data['admin'] = $this->admin_overview();
        }
        return $data;
    }

    /**
     * The user's own progress, activity and course status.
     *
     * @return array
     */
    protected function learner_overview(): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $courses = [];
        $completed = $inprogress = $notstarted = 0;
        foreach (enrol_get_my_courses(['enablecompletion'], 'fullname ASC') as $course) {
            $percent = progress::get_course_progress_percentage($course, $this->user->id);
            if ($percent === null) {
                // Completion tracking is off for this course: there is no progress to show.
                continue;
            }
            $percent = (int) round($percent);
            if ($percent >= 100) {
                $completed++;
            } else if ($percent > 0) {
                $inprogress++;
            } else {
                $notstarted++;
            }
            $courses[] = [
                'name' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'percent' => $percent,
            ];
        }

        $tracked = count($courses);
        $overall = $tracked ? (int) round(array_sum(array_column($courses, 'percent')) / $tracked) : 0;
        $activity = $this->activity_by_day();

        return [
            'overall' => $overall,
            'remaining' => 100 - $overall,
            'hascourses' => $tracked > 0,
            'coursecount' => $tracked,
            'courses' => $courses,
            'status' => $this->status_segments($completed, $inprogress, $notstarted),
            'activity' => $activity,
            'activitytotal' => array_sum(array_column($activity['days'], 'count')),
            'recent' => $this->recent_completions(),
            'completedactivities' => $this->completed_activity_count(),
        ];
    }

    /**
     * Activity-level actions (views, attempts, submissions) per day for the last ACTIVITY_DAYS days.
     *
     * @return array
     */
    protected function activity_by_day(): array {
        global $DB;

        $days = [];
        $today = usergetmidnight(time());
        for ($i = self::ACTIVITY_DAYS - 1; $i >= 0; $i--) {
            $start = $today - $i * DAYSECS;
            $days[userdate($start, '%Y-%m-%d')] = [
                'label' => userdate($start, '%d %b'),
                'short' => userdate($start, '%a'),
                'count' => 0,
            ];
        }

        $since = $today - (self::ACTIVITY_DAYS - 1) * DAYSECS;
        $times = $DB->get_fieldset_sql(
            "SELECT timecreated FROM {logstore_standard_log}
              WHERE userid = :userid AND contextlevel = :level AND timecreated >= :since",
            ['userid' => $this->user->id, 'level' => CONTEXT_MODULE, 'since' => $since]
        );
        foreach ($times as $time) {
            $key = userdate($time, '%Y-%m-%d');
            if (isset($days[$key])) {
                $days[$key]['count']++;
            }
        }

        return $this->columns(array_values($days));
    }

    /**
     * Adds each column's height (as a share of the tallest) for the column chart.
     *
     * @param array $columns Items with a 'count'.
     * @return array
     */
    protected function columns(array $columns): array {
        $max = max(array_merge([0], array_column($columns, 'count')));
        foreach ($columns as $i => $column) {
            $columns[$i]['height'] = $max ? max(2, (int) round($column['count'] / $max * 100)) : 0;
            $columns[$i]['iszero'] = $column['count'] === 0;
        }
        return ['days' => $columns, 'max' => $max, 'hasdata' => $max > 0];
    }

    /**
     * Completed / in progress / not started, as segments of one bar.
     *
     * @param int $completed
     * @param int $inprogress
     * @param int $notstarted
     * @return array
     */
    protected function status_segments(int $completed, int $inprogress, int $notstarted): array {
        $total = $completed + $inprogress + $notstarted;
        $segments = [];
        foreach ([
            ['key' => 'completed', 'count' => $completed, 'label' => get_string('completed', 'completion')],
            ['key' => 'inprogress', 'count' => $inprogress, 'label' => get_string('inprogress', 'completion')],
            ['key' => 'notstarted', 'count' => $notstarted, 'label' => get_string('notyetstarted', 'completion')],
        ] as $segment) {
            $segment['share'] = $total ? round($segment['count'] / $total * 100, 1) : 0;
            $segment['hascount'] = $segment['count'] > 0;
            $segments[] = $segment;
        }
        return ['total' => $total, 'segments' => $segments];
    }

    /**
     * The user's most recently completed activities that they can still see.
     *
     * @return array
     */
    protected function recent_completions(): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT cmc.id, cmc.coursemoduleid, cmc.timemodified, cm.course
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cmc.userid = :userid AND cmc.completionstate > 0
           ORDER BY cmc.timemodified DESC",
            ['userid' => $this->user->id], 0, 20
        );

        $items = [];
        foreach ($records as $record) {
            try {
                $cm = get_fast_modinfo($record->course, $this->user->id)->get_cm($record->coursemoduleid);
            } catch (\moodle_exception $e) {
                continue;
            }
            if (!$cm->uservisible) {
                continue;
            }
            $items[] = [
                'name' => $cm->get_formatted_name(),
                'url' => $cm->url ? $cm->url->out(false) : null,
                'course' => format_string($cm->get_course()->fullname, true,
                    ['context' => \context_course::instance($cm->course)]),
                'icon' => $cm->get_icon_url()->out(false),
                'modname' => $cm->get_module_type_name(),
                'when' => userdate($record->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
            ];
            if (count($items) === 5) {
                break;
            }
        }
        return $items;
    }

    /**
     * How many activities the user has completed in total.
     *
     * @return int
     */
    protected function completed_activity_count(): int {
        global $DB;
        return $DB->count_records_select('course_modules_completion',
            'userid = :userid AND completionstate > 0', ['userid' => $this->user->id]);
    }

    /**
     * Site-wide figures for administrators.
     *
     * @return array
     */
    protected function admin_overview(): array {
        global $DB, $CFG;

        $since = time() - 30 * DAYSECS;
        $data = [
            'activelearners' => $DB->count_records_select('user',
                'deleted = 0 AND suspended = 0 AND lastaccess >= :since AND id <> :guest',
                ['since' => $since, 'guest' => $CFG->siteguest]),
            'enrolments' => $DB->count_records('user_enrolments', ['status' => ENROL_USER_ACTIVE]),
            'completions' => $DB->count_records_select('course_modules_completion',
                'completionstate > 0 AND timemodified >= :since', ['since' => $since]),
            'hassubscriptions' => false,
        ];

        if ($DB->get_manager()->table_exists('local_lmssub_sub')) {
            $data['hassubscriptions'] = true;
            $data['subscriptions'] = $DB->count_records('local_lmssub_sub', ['status' => 'active']);
        }

        // New enrolments per week.
        $weekstart = usergetmidnight(time()) - (self::ENROL_WEEKS * 7 - 1) * DAYSECS;
        $weeks = [];
        for ($i = 0; $i < self::ENROL_WEEKS; $i++) {
            $start = $weekstart + $i * 7 * DAYSECS;
            $weeks[] = ['label' => userdate($start, '%d %b'), 'short' => userdate($start, '%d %b'), 'count' => 0];
        }
        $times = $DB->get_fieldset_select('user_enrolments', 'timecreated', 'timecreated >= :since',
            ['since' => $weekstart]);
        foreach ($times as $time) {
            $index = (int) floor(($time - $weekstart) / (7 * DAYSECS));
            if (isset($weeks[$index])) {
                $weeks[$index]['count']++;
            }
        }
        $data['enrolchart'] = $this->columns($weeks);

        // Learners with the most completed activities in the last 30 days.
        $top = $DB->get_records_sql(
            "SELECT cmc.userid, COUNT(cmc.id) AS done
               FROM {course_modules_completion} cmc
               JOIN {user} u ON u.id = cmc.userid AND u.deleted = 0
              WHERE cmc.completionstate > 0 AND cmc.timemodified >= :since
           GROUP BY cmc.userid
           ORDER BY done DESC",
            ['since' => $since], 0, 5
        );
        $users = $top ? $DB->get_records_list('user', 'id', array_keys($top)) : [];
        $best = $top ? max(array_column($top, 'done')) : 0;
        $data['toplearners'] = [];
        foreach ($top as $userid => $row) {
            if (empty($users[$userid])) {
                continue;
            }
            $data['toplearners'][] = [
                'name' => fullname($users[$userid]),
                'url' => (new \moodle_url('/user/profile.php', ['id' => $userid]))->out(false),
                'done' => (int) $row->done,
                'percent' => $best ? (int) round($row->done / $best * 100) : 0,
            ];
        }
        $data['hastoplearners'] = !empty($data['toplearners']);
        return $data;
    }
}

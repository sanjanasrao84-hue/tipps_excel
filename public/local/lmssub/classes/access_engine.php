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

namespace local_lmssub;

/**
 * The access engine.
 *
 * Access = an active or grace-period subscription whose plan covers the course,
 * and no admin block, and the course is not excluded.
 *
 * The result is written into the enrol_lmssub enrolment of each course: its end date is the latest
 * end date of all subscriptions covering that course, so Moodle itself locks the course at that exact
 * second, without depending on scheduled tasks running on time.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_engine {

    /** @var int Above this many covered courses, a user's sync runs in the background. */
    const SYNC_NOW_LIMIT = 50;

    /**
     * Last moment a subscription gives access, including its grace period.
     *
     * @param \stdClass $sub
     * @param \stdClass $plan
     * @return int 0 when it gives no access
     */
    public static function effective_end(\stdClass $sub, \stdClass $plan): int {
        switch ($sub->status) {
            case subscription_manager::STATUS_ACTIVE:
                // The grace period follows the end date, so access does not stop the second a renewal is late.
                return (int) $sub->timeend + (int) $plan->gracedays * DAYSECS;
            case subscription_manager::STATUS_GRACE:
                return (int) $sub->graceend;
            case subscription_manager::STATUS_CANCELLED:
                // Cancelled: access until the end date, no grace.
                return (int) $sub->timeend;
        }
        return 0;
    }

    /**
     * Works out which courses a user's subscriptions open, until when, and at which level.
     *
     * @param int $userid
     * @return array courseid => (object) ['timeend' => int, 'level' => 'full'|'video', 'subids' => int[]]
     */
    public static function compute_user_access(int $userid): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(subscription_manager::LIVE_STATUSES, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $subs = $DB->get_records_select('local_lmssub_sub', "userid = :userid AND status $insql", $params);

        $now = time();
        $access = [];
        foreach ($subs as $sub) {
            $plan = plan_manager::get_plan((int) $sub->planid);
            $end = self::effective_end($sub, $plan);
            if ($end <= $now) {
                continue;
            }
            foreach (plan_manager::courses_for_plan((int) $sub->planid) as $courseid) {
                if (!isset($access[$courseid])) {
                    $access[$courseid] = (object) ['timeend' => 0, 'level' => 'video', 'subids' => []];
                }
                $entry = $access[$courseid];
                $entry->timeend = max($entry->timeend, $end);
                // A full plan wins over video only.
                if ($sub->level === 'full') {
                    $entry->level = 'full';
                }
                $entry->subids[] = (int) $sub->id;
            }
        }
        return $access;
    }

    /**
     * Recalculates a user's subscription access and writes it into their enrolments.
     *
     * Runs after every payment, renewal, cancellation, refund, admin action and course change.
     *
     * @param int $userid
     * @param int[]|null $onlycourses limit to these courses, or null for all
     */
    public static function sync_user(int $userid, ?array $onlycourses = null): void {
        global $DB;

        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            return;
        }
        $plugin = enrol_get_plugin('lmssub');
        $access = self::compute_user_access($userid);
        $overrides = $DB->get_records_menu('local_lmssub_override', ['userid' => $userid], '', 'courseid, action');

        // One subscription enrolment instance per course.
        $existing = $DB->get_records_sql(
            "SELECT e.courseid, ue.id, ue.status, ue.timestart, ue.timeend, e.id AS enrolid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'lmssub'
              WHERE ue.userid = :userid", ['userid' => $userid]);

        $allowed = array_keys(array_filter($overrides, fn($action) => $action === 'allow'));
        $courseids = array_unique(array_merge(array_keys($access), array_keys($existing), $allowed));
        if ($onlycourses !== null) {
            $courseids = array_intersect($courseids, array_map('intval', $onlycourses));
        }

        $roleid = $plugin->get_student_roleid();
        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;
            if ($courseid == SITEID || !$DB->record_exists('course', ['id' => $courseid])) {
                continue;
            }
            $override = $overrides[$courseid] ?? null;
            $ue = $existing[$courseid] ?? null;

            // An admin block always wins over the subscription.
            if ($override === 'block' || ($override !== 'allow' && !isset($access[$courseid]))) {
                if ($ue && (int) $ue->status !== ENROL_USER_SUSPENDED) {
                    // Suspended, not removed: grades, progress and certificates are kept.
                    $instance = $DB->get_record('enrol', ['id' => $ue->enrolid], '*', MUST_EXIST);
                    $plugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
                }
                continue;
            }

            $timeend = $override === 'allow' ? 0 : (int) $access[$courseid]->timeend;
            $instance = $ue ? $DB->get_record('enrol', ['id' => $ue->enrolid], '*', MUST_EXIST)
                : $plugin->get_or_create_instance($courseid);
            $timestart = $ue ? (int) $ue->timestart : time();
            // Only gives the Student role; enrol_user() changes nothing when the values are unchanged.
            $plugin->enrol_user($instance, $userid, $roleid, $timestart, $timeend, ENROL_USER_ACTIVE);
        }
    }

    /**
     * Syncs a user now, or in the background when their subscriptions cover many courses.
     *
     * @param int $userid
     */
    public static function sync_user_soon(int $userid): void {
        $count = count(self::compute_user_access($userid));
        if ($count <= self::SYNC_NOW_LIMIT) {
            self::sync_user($userid);
            return;
        }
        $task = new task\sync_user();
        $task->set_custom_data(['userid' => $userid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Re-checks everyone who could be affected by a change to a course.
     *
     * @param int $courseid
     */
    public static function sync_course(int $courseid): void {
        global $DB;

        plan_manager::reset_caches();
        $userids = $DB->get_fieldset_sql(
            "SELECT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'lmssub'
              WHERE e.courseid = :courseid", ['courseid' => $courseid]);
        $userids = array_merge($userids,
            $DB->get_fieldset_select('local_lmssub_override', 'userid', 'courseid = :courseid', ['courseid' => $courseid]));

        $planids = plan_manager::plans_covering_course($courseid);
        if ($planids) {
            plan_manager::ensure_enrol_instances([$courseid]);
            $userids = array_merge($userids, self::live_subscribers($planids));
        }
        foreach (array_unique($userids) as $userid) {
            self::sync_user((int) $userid, [$courseid]);
        }
    }

    /**
     * Queues a background re-check of every subscriber of a plan, after the plan changed.
     *
     * @param int $planid
     */
    public static function queue_plan_sync(int $planid): void {
        $task = new task\sync_plan();
        $task->set_custom_data(['planid' => $planid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Re-checks every subscriber of a plan.
     *
     * @param int $planid
     */
    public static function sync_plan(int $planid): void {
        global $DB;

        plan_manager::reset_caches();
        // Subscribers of the plan, plus anyone still enrolled in courses the plan no longer covers.
        $userids = $DB->get_fieldset_select('local_lmssub_sub', 'DISTINCT userid', 'planid = :planid', ['planid' => $planid]);
        foreach ($userids as $userid) {
            self::sync_user((int) $userid);
        }
    }

    /**
     * Re-checks every user who has, or had, subscription access.
     *
     * @return int number of users checked
     */
    public static function sync_all(): int {
        global $DB;

        plan_manager::reset_caches();
        $userids = $DB->get_fieldset_sql(
            "SELECT userid FROM {local_lmssub_sub}
              UNION
             SELECT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'lmssub'
              UNION
             SELECT userid FROM {local_lmssub_override}");
        foreach ($userids as $userid) {
            self::sync_user((int) $userid);
        }
        return count($userids);
    }

    /**
     * User ids with a live subscription on any of the plans.
     *
     * @param int[] $planids
     * @return int[]
     */
    protected static function live_subscribers(array $planids): array {
        global $DB;
        [$planin, $params] = $DB->get_in_or_equal($planids, SQL_PARAMS_NAMED, 'p');
        [$statusin, $sparams] = $DB->get_in_or_equal(subscription_manager::LIVE_STATUSES, SQL_PARAMS_NAMED, 's');
        return $DB->get_fieldset_select('local_lmssub_sub', 'DISTINCT userid',
            "planid $planin AND status $statusin", $params + $sparams);
    }

    /**
     * Explains why a user does or does not have subscription access to a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return array ['verdict' => bool, 'level' => ?string, 'reasons' => string[]]
     */
    public static function explain(int $userid, int $courseid): array {
        global $DB;

        $reasons = [];
        $access = self::compute_user_access($userid);
        $override = $DB->get_record('local_lmssub_override', ['userid' => $userid, 'courseid' => $courseid]);

        if (plan_manager::is_course_excluded($courseid)) {
            $reasons[] = get_string('explain_excluded', 'local_lmssub');
        }
        $subs = $DB->get_records('local_lmssub_sub', ['userid' => $userid], 'timecreated DESC');
        if (!$subs) {
            $reasons[] = get_string('explain_nosubs', 'local_lmssub');
        }
        foreach ($subs as $sub) {
            $plan = plan_manager::get_plan((int) $sub->planid);
            $a = (object) [
                'plan' => format_string($plan->name),
                'status' => subscription_manager::get_status_name($sub->status),
                'end' => $sub->timeend ? userdate($sub->timeend) : '-',
            ];
            if (!isset(plan_manager::courses_for_plan((int) $sub->planid)[$courseid])) {
                $reasons[] = get_string('explain_notcovered', 'local_lmssub', $a);
            } else if (self::effective_end($sub, $plan) <= time()) {
                $reasons[] = get_string('explain_covered_inactive', 'local_lmssub', $a);
            } else {
                $a->until = userdate(self::effective_end($sub, $plan));
                $reasons[] = get_string('explain_covered_active', 'local_lmssub', $a);
            }
        }

        $verdict = isset($access[$courseid]);
        $level = $verdict ? $access[$courseid]->level : null;
        if ($override) {
            $a = (object) ['reason' => s($override->reason)];
            if ($override->action === 'block') {
                $reasons[] = get_string('explain_blocked', 'local_lmssub', $a);
                $verdict = false;
                $level = null;
            } else {
                $reasons[] = get_string('explain_allowed', 'local_lmssub', $a);
                $verdict = true;
                $level = 'full';
            }
        }

        $ue = $DB->get_record_sql(
            "SELECT ue.status, ue.timeend
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'lmssub'
              WHERE ue.userid = :userid AND e.courseid = :courseid", ['userid' => $userid, 'courseid' => $courseid]);
        if ($ue) {
            $reasons[] = get_string('explain_enrolment', 'local_lmssub', (object) [
                'status' => (int) $ue->status === ENROL_USER_ACTIVE ? get_string('active') : get_string('suspended'),
                'end' => $ue->timeend ? userdate($ue->timeend) : get_string('never'),
            ]);
        }
        // Other enrolment methods (one-off purchases, manual) keep working unchanged.
        $now = time();
        $others = $DB->get_fieldset_sql(
            "SELECT DISTINCT e.enrol
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol <> 'lmssub'
              WHERE ue.userid = :userid AND e.courseid = :courseid AND ue.status = :active AND e.status = :enabled
                AND ue.timestart <= :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)",
            ['userid' => $userid, 'courseid' => $courseid, 'active' => ENROL_USER_ACTIVE,
                'enabled' => ENROL_INSTANCE_ENABLED, 'now1' => $now, 'now2' => $now]);
        if ($others) {
            $reasons[] = get_string('explain_otherenrolment', 'local_lmssub', implode(', ', $others));
        }

        return ['verdict' => $verdict, 'level' => $level, 'reasons' => $reasons];
    }
}

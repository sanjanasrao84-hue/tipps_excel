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
 * Event observers. New and changed courses are picked up automatically by full-LMS plans.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * A course was created, updated (moved, shown, hidden, excluded) or restored.
     *
     * @param \core\event\base $event
     */
    public static function course_changed(\core\event\base $event): void {
        plan_manager::reset_caches();
        $task = new task\sync_course();
        $task->set_custom_data(['courseid' => $event->courseid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * A course was deleted: drop it from plans and overrides. Core removes its enrolments.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal([plan_manager::SCOPE_COURSE, plan_manager::SCOPE_EXCLUDECOURSE],
            SQL_PARAMS_NAMED);
        $params['courseid'] = $event->courseid;
        $DB->delete_records_select('local_lmssub_scope', "scopetype $insql AND targetid = :courseid", $params);
        $DB->delete_records('local_lmssub_override', ['courseid' => $event->courseid]);
        plan_manager::reset_caches();
    }

    /**
     * A category was moved or deleted: the courses under full-LMS plans may have changed.
     *
     * @param \core\event\base $event
     */
    public static function category_changed(\core\event\base $event): void {
        plan_manager::reset_caches();
        \core\task\manager::queue_adhoc_task(new task\sync_all(), true);
    }

    /**
     * A user was deleted: end their subscriptions, keeping the history.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(
            array_merge(subscription_manager::LIVE_STATUSES, [subscription_manager::STATUS_PENDING]), SQL_PARAMS_NAMED);
        $params['userid'] = $event->objectid;
        foreach ($DB->get_records_select('local_lmssub_sub', "userid = :userid AND status $insql", $params) as $sub) {
            $from = $sub->status;
            $DB->update_record('local_lmssub_sub', (object) ['id' => $sub->id,
                'status' => subscription_manager::STATUS_REVOKED, 'timemodified' => time()]);
            $sub->status = subscription_manager::STATUS_REVOKED;
            subscription_manager::log($sub, 'revoke', $from, $sub->status, get_string('userdeleted', 'local_lmssub'));
        }
        $DB->delete_records('local_lmssub_override', ['userid' => $event->objectid]);
    }
}

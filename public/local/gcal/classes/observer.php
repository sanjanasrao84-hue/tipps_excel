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

namespace local_gcal;

use local_gcal\task\sync_users;

/**
 * Event observers: queue a sync of the connected users concerned by a change.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /** @var int Users per sync task. */
    private const BATCH = 50;

    /**
     * An academic event was created or updated.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function calendar_event_changed(\core\event\base $event): void {
        global $DB;
        $record = $DB->get_record('event', ['id' => $event->objectid],
            'id, eventtype, courseid, categoryid, userid, modulename');
        if ($record) {
            self::queue(self::connected_users_for($record));
        }
    }

    /**
     * An academic event was deleted: the users who had a copy are synchronised.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function calendar_event_deleted(\core\event\base $event): void {
        global $DB;
        self::queue($DB->get_fieldset_select('local_gcal_eventmap', 'userid', 'eventid = ?', [$event->objectid]));
    }

    /**
     * A user was enrolled, unenrolled or their enrolment changed: their events change.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function enrolment_changed(\core\event\base $event): void {
        global $DB;
        if ($event->relateduserid && $DB->record_exists(connection::TABLE, ['userid' => $event->relateduserid,
                'status' => connection::STATUS_ACTIVE])) {
            self::queue([$event->relateduserid]);
        }
    }

    /**
     * A user was deleted: their connection is removed (their Google data stays in their Google account).
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function user_deleted(\core\event\base $event): void {
        if ($connection = connection::get($event->objectid)) {
            $connection->delete();
        }
        global $DB;
        $DB->delete_records('local_gcal_log', ['userid' => $event->objectid]);
    }

    /**
     * Connected users who can see an event.
     *
     * @param \stdClass $event
     * @return int[]
     */
    private static function connected_users_for(\stdClass $event): array {
        global $DB;
        $active = connection::STATUS_ACTIVE;
        switch ($event->eventtype) {
            case 'user':
                return $DB->get_fieldset_select(connection::TABLE, 'userid', 'userid = ? AND status = ?',
                    [$event->userid, $active]);
            case 'site':
                return $DB->get_fieldset_select(connection::TABLE, 'userid', 'status = ?', [$active]);
            case 'category':
                $context = \context_coursecat::instance($event->categoryid, IGNORE_MISSING);
                if (!$context) {
                    return [];
                }
                $like = $DB->sql_like('ctx.path', ':path');
                return $DB->get_fieldset_sql("SELECT DISTINCT gc.userid
                    FROM {local_gcal_connection} gc
                    JOIN {user_enrolments} ue ON ue.userid = gc.userid
                    JOIN {enrol} e ON e.id = ue.enrolid
                    JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :level
                   WHERE gc.status = :active AND ({$like} OR ctx.path = :self)",
                    ['level' => CONTEXT_COURSE, 'active' => $active, 'path' => $context->path . '/%',
                        'self' => $context->path]);
            default:
                if (empty($event->courseid) || $event->courseid == SITEID) {
                    return [];
                }
                return $DB->get_fieldset_sql("SELECT DISTINCT gc.userid
                    FROM {local_gcal_connection} gc
                    JOIN {user_enrolments} ue ON ue.userid = gc.userid
                    JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
                   WHERE gc.status = :active", ['courseid' => $event->courseid, 'active' => $active]);
        }
    }

    /**
     * Queues sync tasks for users (duplicates of a waiting task are not queued again).
     *
     * @param int[] $userids
     * @return void
     */
    private static function queue(array $userids): void {
        $userids = array_values(array_unique(array_map('intval', $userids)));
        sort($userids);
        foreach (array_chunk($userids, self::BATCH) as $chunk) {
            $task = new sync_users();
            $task->set_custom_data(['userids' => $chunk]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
    }
}

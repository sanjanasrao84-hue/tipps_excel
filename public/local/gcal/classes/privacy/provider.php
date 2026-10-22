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

namespace local_gcal\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Stored and exported data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_gcal_connection', [
            'userid' => 'privacy:metadata:connection:userid',
            'googleemail' => 'privacy:metadata:connection:googleemail',
            'showpersonal' => 'privacy:metadata:connection:showpersonal',
            'lastsync' => 'privacy:metadata:connection:lastsync',
        ], 'privacy:metadata:connection');
        $collection->add_database_table('local_gcal_eventmap', [
            'eventid' => 'privacy:metadata:eventmap:eventid',
            'googleeventid' => 'privacy:metadata:eventmap:googleeventid',
        ], 'privacy:metadata:eventmap');
        $collection->add_database_table('local_gcal_log', [
            'action' => 'privacy:metadata:log:action',
            'timecreated' => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');
        $collection->add_external_location_link('google', [
            'name' => 'privacy:metadata:google:name',
            'times' => 'privacy:metadata:google:times',
            'description' => 'privacy:metadata:google:description',
        ], 'privacy:metadata:google');
        return $collection;
    }

    /**
     * Contexts with data of a user (system context).
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_gcal_connection', ['userid' => $userid])
                || $DB->record_exists('local_gcal_log', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_gcal_connection}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_gcal_log} WHERE userid > 0', []);
    }

    /**
     * Exports a user's data (tokens are never exported).
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            $connection = $DB->get_record('local_gcal_connection', ['userid' => $userid]);
            if ($connection) {
                writer::with_context($context)->export_data([get_string('pluginname', 'local_gcal')], (object) [
                    'googleemail' => $connection->googleemail,
                    'showpersonal' => transform::yesno($connection->showpersonal),
                    'lastsync' => $connection->lastsync ? transform::datetime($connection->lastsync) : '-',
                    'synchronisedevents' => $DB->count_records('local_gcal_eventmap', ['userid' => $userid]),
                ]);
            }
        }
    }

    /**
     * Deletes all users' data in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records('local_gcal_eventmap');
            $DB->delete_records('local_gcal_connection');
            $DB->delete_records('local_gcal_log');
        }
    }

    /**
     * Deletes a user's data.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                self::delete_user((int) $contextlist->get_user()->id);
            }
        }
    }

    /**
     * Deletes the data of users in a context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if ($userlist->get_context()->contextlevel == CONTEXT_SYSTEM) {
            foreach ($userlist->get_userids() as $userid) {
                self::delete_user((int) $userid);
            }
        }
    }

    /**
     * Deletes one user's data.
     *
     * @param int $userid
     * @return void
     */
    private static function delete_user(int $userid): void {
        global $DB;
        $DB->delete_records('local_gcal_eventmap', ['userid' => $userid]);
        $DB->delete_records('local_gcal_connection', ['userid' => $userid]);
        $DB->delete_records('local_gcal_log', ['userid' => $userid]);
    }
}

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

namespace local_gcal\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_gcal\connection;
use local_gcal\google\api_exception;
use local_gcal\local\event_source;
use local_gcal\local\sync_manager;

/**
 * Events of the calendar page: the user's academic events and, when connected, their own Google events.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_events extends external_api {

    /** @var int Longest range that can be requested, in days. */
    private const MAX_DAYS = 100;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'start' => new external_value(PARAM_INT, 'Start of the range (timestamp)'),
            'end' => new external_value(PARAM_INT, 'End of the range (timestamp)'),
            'courseid' => new external_value(PARAM_INT, 'Only this course', VALUE_DEFAULT, 0),
            'organisationid' => new external_value(PARAM_INT, 'Only this organisation (top-level category)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns the events.
     *
     * @param int $start
     * @param int $end
     * @param int $courseid
     * @param int $organisationid
     * @return array
     */
    public static function execute(int $start, int $end, int $courseid = 0, int $organisationid = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), compact('start', 'end', 'courseid', 'organisationid'));
        self::validate_context(context_system::instance());
        $start = $params['start'];
        $end = min($params['end'], $start + self::MAX_DAYS * DAYSECS);

        $events = [];
        $warnings = [];
        $source = new event_source($USER);
        $academic = $source->get_events($start, $end, $params['courseid'], $params['organisationid']);
        // Links to the copies in the user's own Google Calendar.
        $mylinks = [];
        if ($academic) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($academic), SQL_PARAMS_NAMED);
            $mylinks = $DB->get_records_select_menu('local_gcal_eventmap', "userid = :userid AND eventid {$insql}",
                ['userid' => $USER->id] + $inparams, '', 'eventid, htmllink');
        }

        foreach ($academic as $event) {
            $events[] = [
                'id' => 'm-' . $event->id,
                'eventid' => $event->id,
                'source' => 'lms',
                'title' => $event->name,
                'start' => $event->timestart,
                'end' => $event->timestart + $event->timeduration,
                'allday' => false,
                'category' => $event->category,
                'categoryname' => get_string('category_' . $event->category, 'local_gcal'),
                'color' => event_source::CATEGORIES[$event->category][1],
                'courseid' => $event->courseid,
                'coursename' => $event->coursefullname,
                'url' => (string) $event->url,
                'googleurl' => (string) ($mylinks[$event->id] ?? ''),
                'synced' => array_key_exists($event->id, $mylinks),
                'description' => shorten_text(trim(html_to_text(format_text($event->description, $event->descriptionformat),
                    0, false)), 600),
                'location' => $event->location,
                'canedit' => $event->canedit,
                'candelete' => $event->candelete,
                'editdatesurl' => (string) $event->editdatesurl,
                'deadline' => $event->timeduration == 0,
            ];
        }

        // The user's own Google events: shown to them only, never stored.
        $connection = connection::get($USER->id);
        if ($connection && $connection->shows_personal_events() && !$params['courseid'] && !$params['organisationid']) {
            try {
                foreach (sync_manager::personal_events($connection, $start, $end) as $item) {
                    $events[] = [
                        'id' => $item['id'], 'eventid' => 0, 'source' => 'google', 'title' => $item['title'],
                        'start' => $item['start'], 'end' => $item['end'], 'allday' => $item['allday'],
                        'category' => 'personal', 'categoryname' => get_string('category_personal', 'local_gcal'),
                        'color' => event_source::CATEGORIES['personal'][1], 'courseid' => 0, 'coursename' => '',
                        'url' => '', 'googleurl' => $item['googleurl'], 'synced' => true,
                        'description' => $item['description'], 'location' => $item['location'],
                        'canedit' => false, 'candelete' => false, 'editdatesurl' => '', 'deadline' => false,
                    ];
                }
            } catch (api_exception $e) {
                if ($e->needs_reconnect()) {
                    $connection->mark_reconnect($e->getMessage());
                }
                $warnings[] = get_string('personalunavailable', 'local_gcal');
            }
        }

        return ['events' => $events, 'warnings' => $warnings];
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'events' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_RAW, 'Unique id in the calendar'),
                'eventid' => new external_value(PARAM_INT, 'Moodle event id (0 for Google events)'),
                'source' => new external_value(PARAM_ALPHA, 'lms or google'),
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'start' => new external_value(PARAM_INT, 'Start'),
                'end' => new external_value(PARAM_INT, 'End'),
                'allday' => new external_value(PARAM_BOOL, 'All-day event'),
                'category' => new external_value(PARAM_ALPHANUMEXT, 'Category key'),
                'categoryname' => new external_value(PARAM_TEXT, 'Category name'),
                'color' => new external_value(PARAM_RAW, 'Colour'),
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'coursename' => new external_value(PARAM_TEXT, 'Course name'),
                'url' => new external_value(PARAM_URL, 'Course or activity page'),
                'googleurl' => new external_value(PARAM_URL, 'Event in Google Calendar'),
                'synced' => new external_value(PARAM_BOOL, 'In the user\'s Google Calendar'),
                'description' => new external_value(PARAM_TEXT, 'Description (plain text)'),
                'location' => new external_value(PARAM_TEXT, 'Location or online link'),
                'canedit' => new external_value(PARAM_BOOL, 'The user may edit it'),
                'candelete' => new external_value(PARAM_BOOL, 'The user may cancel it'),
                'editdatesurl' => new external_value(PARAM_URL, 'Activity settings, to change activity dates'),
                'deadline' => new external_value(PARAM_BOOL, 'A deadline (no duration)'),
            ])),
            'warnings' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Warning')),
        ]);
    }
}

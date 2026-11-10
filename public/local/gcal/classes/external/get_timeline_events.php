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
use local_gcal\local\event_source;
use moodle_url;

/**
 * Events of the dashboard Timeline block: its activity deadlines (core action events) together with the events of the
 * calendar page (lessons, live classes, course, organisation and site events...), in date order.
 *
 * Same parameters and paging as core_calendar_get_action_events_by_timesort, which the Timeline calls otherwise
 * (see theme_eadtraining js/esm/build/overrides/block_timeline/repository.js).
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_timeline_events extends external_api {

    /** @var int Range read when the Timeline has no end ("All"), in days. */
    private const OPEN_RANGE_DAYS = 366;

    /** @var int Deadlines read from core per call (its maximum), more than a Timeline page. */
    private const ACTION_LIMIT = 50;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'timesortfrom' => new external_value(PARAM_INT, 'Start of the range (timestamp)', VALUE_DEFAULT, 0),
            'timesortto' => new external_value(PARAM_INT, 'End of the range (timestamp)', VALUE_DEFAULT, null, NULL_ALLOWED),
            'aftereventid' => new external_value(PARAM_INT, 'Return the events after this one', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Maximum number of events', VALUE_DEFAULT, 20),
            'searchvalue' => new external_value(PARAM_RAW, 'Text in the event or course name', VALUE_DEFAULT, null,
                NULL_ALLOWED),
            'courseid' => new external_value(PARAM_INT, 'Only this course (0 for all)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Timeline events.
     *
     * @param int $timesortfrom
     * @param int|null $timesortto
     * @param int $aftereventid
     * @param int $limitnum
     * @param string|null $searchvalue
     * @param int $courseid
     * @return array
     */
    public static function execute(int $timesortfrom = 0, ?int $timesortto = null, int $aftereventid = 0, int $limitnum = 20,
            ?string $searchvalue = null, int $courseid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(),
            compact('timesortfrom', 'timesortto', 'aftereventid', 'limitnum', 'searchvalue', 'courseid'));
        self::validate_context(context_system::instance());

        $events = self::collect($params['courseid'], $params['timesortfrom'], $params['timesortto'], $params['aftereventid'],
            $params['limitnum'], $params['searchvalue']);

        return [
            'events' => $events,
            'firstid' => $events ? $events[0]['id'] : 0,
            'lastid' => $events ? $events[count($events) - 1]['id'] : 0,
        ];
    }

    /**
     * Deadlines and calendar events in date order, as Timeline items.
     *
     * @param int $courseid Only this course (0 for all, with site, organisation and personal events)
     * @param int $timesortfrom
     * @param int|null $timesortto
     * @param int $aftereventid Paging: events after this one
     * @param int $limitnum
     * @param string|null $searchvalue
     * @return array
     */
    public static function collect(int $courseid, int $timesortfrom, ?int $timesortto, int $aftereventid, int $limitnum,
            ?string $searchvalue): array {
        global $CFG, $DB, $USER;
        require_once("{$CFG->dirroot}/calendar/externallib.php");

        $limitnum = max(1, min(50, $limitnum));
        $search = \core_text::strtolower(trim((string) $searchvalue));

        // Paging: events after (time, id) of the last event shown, which may be a deadline or a calendar event.
        $from = $timesortfrom;
        $cursor = null;
        if ($aftereventid && ($after = $DB->get_record('event', ['id' => $aftereventid], 'id, timestart, timesort'))) {
            $cursor = [(int) ($after->timesort ?: $after->timestart), (int) $after->id];
            $from = max($from, $cursor[0]);
        }
        $to = $timesortto;

        $events = [];

        // The Timeline's own activity deadlines, as core returns them.
        if ($courseid) {
            $actions = external_api::clean_returnvalue(\core_calendar_external::get_calendar_action_events_by_course_returns(),
                \core_calendar_external::get_calendar_action_events_by_course($courseid, $from, $to, 0, self::ACTION_LIMIT,
                    $searchvalue));
        } else {
            $actions = external_api::clean_returnvalue(\core_calendar_external::get_calendar_action_events_by_timesort_returns(),
                \core_calendar_external::get_calendar_action_events_by_timesort($from, $to, 0, self::ACTION_LIMIT, false, null,
                    $searchvalue));
        }
        foreach ($actions['events'] as $event) {
            $events[$event['id']] = self::from_action_event($event);
        }
        // When core may have more deadlines, calendar events after the last one wait for the next page.
        $boundary = count($actions['events']) >= self::ACTION_LIMIT ? end($actions['events'])['timesort'] : null;

        // The calendar page events. Deadlines are left to core: they come with their action button.
        $calendar = (new event_source($USER))->get_events($from, $to ?? $from + self::OPEN_RANGE_DAYS * DAYSECS, $courseid);
        $today = usergetmidnight(time());
        $types = [];
        if ($calendar) {
            $types = $DB->get_records_list('event', 'id', array_keys($calendar), '', 'id, type, timesort');
        }
        foreach ($calendar as $id => $event) {
            if (isset($events[$id]) || !isset($types[$id]) || $types[$id]->type == CALENDAR_EVENT_TYPE_ACTION) {
                continue;
            }
            // Events of past days are not shown (only deadlines can be overdue); today's events stay all day.
            if ($event->timestart + $event->timeduration < $today) {
                continue;
            }
            if ($search !== '' && \core_text::strpos(\core_text::strtolower("{$event->name} {$event->coursefullname}"),
                    $search) === false) {
                continue;
            }
            $timesort = (int) ($types[$id]->timesort ?: $event->timestart);
            if (($to !== null && $timesort > $to) || ($boundary !== null && $timesort > $boundary)) {
                continue;
            }
            $events[$id] = self::from_calendar_event($event, $timesort);
        }

        $events = array_filter($events, function ($event) use ($cursor) {
            return !$cursor || $event['timesort'] > $cursor[0] || ($event['timesort'] == $cursor[0] && $event['id'] > $cursor[1]);
        });
        usort($events, fn($a, $b) => [$a['timesort'], $a['id']] <=> [$b['timesort'], $b['id']]);
        return array_slice($events, 0, $limitnum);
    }

    /**
     * Timeline item of a core action event (exported by core_calendar).
     *
     * @param array $event
     * @return array
     */
    private static function from_action_event(array $event): array {
        $item = array_intersect_key($event, array_flip(['id', 'name', 'activityname', 'activitystr', 'modulename',
            'eventtype', 'timesort', 'timeusermidnight', 'overdue', 'url', 'purpose']));
        $item['course'] = ['fullnamedisplay' => (string) ($event['course']['fullnamedisplay'] ?? '')];
        if (!empty($event['icon']['iconurl'])) {
            $item['icon'] = ['iconurl' => $event['icon']['iconurl'], 'alttext' => (string) ($event['icon']['alttext'] ?? '')];
        }
        if (!empty($event['action'])) {
            $item['action'] = array_intersect_key($event['action'], array_flip(['name', 'url', 'actionable',
                'showitemcount', 'itemcount']));
        }
        $item['calendarevent'] = false;
        return $item;
    }

    /**
     * Timeline item of a calendar page event. It opens the activity, or the calendar on the event day.
     *
     * @param \stdClass $event normalised event (event_source)
     * @param int $timesort
     * @return array
     */
    private static function from_calendar_event(\stdClass $event, int $timesort): array {
        global $OUTPUT;

        $calendarurl = new moodle_url('/local/gcal/index.php', ['date' => userdate($timesort, '%Y-%m-%d', 99, false)]);
        $url = $event->url ?: $calendarurl->out(false);
        $item = [
            'id' => $event->id,
            'name' => $event->name,
            'activityname' => $event->name,
            'activitystr' => get_string('category_' . $event->category, 'local_gcal'),
            'modulename' => $event->modulename,
            'eventtype' => $event->eventtype,
            'timesort' => $timesort,
            'timeusermidnight' => usergetmidnight($timesort),
            // Not a task: never "overdue" (the Overdue filter only lists deadlines).
            'overdue' => false,
            'url' => $url,
            'purpose' => $event->modulename ? (string) plugin_supports('mod', $event->modulename, FEATURE_MOD_PURPOSE, 'none')
                : '',
            'course' => ['fullnamedisplay' => $event->coursefullname],
            'calendarevent' => true,
        ];
        if ($event->modulename) {
            $item['icon'] = ['iconurl' => $OUTPUT->image_url('monologo', $event->modulename)->out(false),
                'alttext' => get_string('modulename', $event->modulename)];
        } else {
            // Same icon box as the activities (theme override of the Timeline ActivityIcon), so the items line up.
            $item['icon'] = ['iconurl' => $OUTPUT->image_url('i/calendar')->out(false), 'alttext' => $item['activitystr']];
        }
        return $item;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'events' => self::events_structure(),
            'firstid' => new external_value(PARAM_INT, 'First event id'),
            'lastid' => new external_value(PARAM_INT, 'Last event id'),
        ]);
    }

    /**
     * Structure of a list of Timeline items.
     *
     * @return external_multiple_structure
     */
    public static function events_structure(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Event id'),
                'name' => new external_value(PARAM_TEXT, 'Event name'),
                'activityname' => new external_value(PARAM_TEXT, 'Name shown as the link', VALUE_OPTIONAL),
                'activitystr' => new external_value(PARAM_TEXT, 'Kind of event', VALUE_OPTIONAL),
                'modulename' => new external_value(PARAM_PLUGIN, 'Activity module', VALUE_OPTIONAL),
                'eventtype' => new external_value(PARAM_TEXT, 'Event type'),
                'timesort' => new external_value(PARAM_INT, 'Sort time'),
                'timeusermidnight' => new external_value(PARAM_INT, 'User midnight of the event day'),
                'overdue' => new external_value(PARAM_BOOL, 'Overdue deadline'),
                'url' => new external_value(PARAM_URL, 'Link'),
                'purpose' => new external_value(PARAM_ALPHANUMEXT, 'Activity purpose', VALUE_OPTIONAL),
                'calendarevent' => new external_value(PARAM_BOOL, 'Event of the calendar page (not a deadline)'),
                'course' => new external_single_structure([
                    'fullnamedisplay' => new external_value(PARAM_RAW, 'Course name'),
                ]),
                'icon' => new external_single_structure([
                    'iconurl' => new external_value(PARAM_URL, 'Icon'),
                    'alttext' => new external_value(PARAM_TEXT, 'Icon text'),
                ], 'Icon', VALUE_OPTIONAL),
                'action' => new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Action name'),
                    'url' => new external_value(PARAM_URL, 'Action link'),
                    'actionable' => new external_value(PARAM_BOOL, 'Whether the action can be done now'),
                    'showitemcount' => new external_value(PARAM_BOOL, 'Show the item count', VALUE_OPTIONAL),
                    'itemcount' => new external_value(PARAM_INT, 'Item count', VALUE_OPTIONAL),
                ], 'Action', VALUE_OPTIONAL),
            ]));
    }
}

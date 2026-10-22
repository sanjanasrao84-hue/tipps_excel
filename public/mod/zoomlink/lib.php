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

/**
 * Library of the Zoom meeting link activity.
 *
 * @package    mod_zoomlink
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Calendar event type of a class. */
const ZOOMLINK_EVENT_TYPE = 'meeting_start';

/**
 * Features of the module.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function zoomlink_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_OTHER,
        FEATURE_GROUPS => false,
        FEATURE_GROUPINGS => false,
        FEATURE_MOD_INTRO => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_BACKUP_MOODLE2 => false,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_COMMUNICATION,
        default => null,
    };
}

/**
 * Adds a class.
 *
 * @param stdClass $data form data
 * @param mod_zoomlink_mod_form|null $mform
 * @return int new instance id
 */
function zoomlink_add_instance($data, $mform = null) {
    global $DB;
    $data->timemodified = time();
    $data->passcode = trim($data->passcode ?? '');
    $data->starttime = (int) ($data->starttime ?? 0);
    $data->id = $DB->insert_record('zoomlink', $data);
    zoomlink_update_calendar($data, (int) $data->coursemodule);
    return $data->id;
}

/**
 * Updates a class.
 *
 * @param stdClass $data form data
 * @param mod_zoomlink_mod_form|null $mform
 * @return bool
 */
function zoomlink_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $data->passcode = trim($data->passcode ?? '');
    $data->starttime = (int) ($data->starttime ?? 0);
    $DB->update_record('zoomlink', $data);
    zoomlink_update_calendar($data, (int) $data->coursemodule);
    return true;
}

/**
 * Deletes a class and its calendar event.
 *
 * @param int $id instance id
 * @return bool
 */
function zoomlink_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('zoomlink', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('event', ['modulename' => 'zoomlink', 'instance' => $id]);
    $DB->delete_records('zoomlink', ['id' => $id]);
    return true;
}

/**
 * Puts the class in the course calendar, or takes it out when it has no start time.
 *
 * @param stdClass $zoomlink
 * @param int $cmid
 */
function zoomlink_update_calendar(stdClass $zoomlink, int $cmid): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/calendar/lib.php');

    $eventid = $DB->get_field('event', 'id',
        ['modulename' => 'zoomlink', 'instance' => $zoomlink->id, 'eventtype' => ZOOMLINK_EVENT_TYPE]);
    if (empty($zoomlink->starttime)) {
        if ($eventid) {
            calendar_event::load($eventid)->delete();
        }
        return;
    }
    $event = (object) [
        'type' => CALENDAR_EVENT_TYPE_ACTION,
        'name' => get_string('calendarevent', 'zoomlink', $zoomlink->name),
        'description' => format_module_intro('zoomlink', $zoomlink, $cmid, false),
        'format' => FORMAT_HTML,
        'courseid' => $zoomlink->course,
        'groupid' => 0,
        'userid' => 0,
        'modulename' => 'zoomlink',
        'instance' => $zoomlink->id,
        'eventtype' => ZOOMLINK_EVENT_TYPE,
        'timestart' => $zoomlink->starttime,
        'timesort' => $zoomlink->starttime,
        'timeduration' => $zoomlink->duration * MINSECS,
        'visible' => instance_is_visible('zoomlink', $zoomlink),
    ];
    if ($eventid) {
        calendar_event::load($eventid)->update($event, false);
    } else {
        calendar_event::create($event, false);
    }
}

/**
 * Whether a link is a Zoom link, so the Join button can never send learners somewhere else.
 *
 * @param string $url
 * @return bool
 */
function zoomlink_is_valid_joinurl(string $url): bool {
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    return (bool) preg_match('~^https://~i', $url) && (bool) preg_match('/(^|\.)(zoom\.us|zoom\.com|zoomgov\.com)$/', $host);
}

/**
 * Where a class stands in time.
 *
 * @param stdClass $zoomlink
 * @param int|null $now
 * @return array [string status: open, upcoming, soon, live or ended; bool learners can join now; int when joining opens]
 */
function zoomlink_get_status(stdClass $zoomlink, ?int $now = null): array {
    $now = $now ?? time();
    if (empty($zoomlink->starttime)) {
        return ['open', true, 0];
    }
    $opens = $zoomlink->starttime - $zoomlink->opensbefore * MINSECS;
    $ends = $zoomlink->starttime + $zoomlink->duration * MINSECS;
    if ($now < $opens) {
        return ['upcoming', false, $opens];
    }
    if ($now < $zoomlink->starttime) {
        return ['soon', true, $opens];
    }
    if ($now < $ends) {
        return ['live', true, $opens];
    }
    // The meeting may run late, so the link stays usable after the planned end.
    return ['ended', true, $opens];
}

/**
 * Shows the time of the class on the course page.
 *
 * @param cm_info $cm
 */
function zoomlink_cm_info_view(cm_info $cm) {
    global $DB;
    $zoomlink = $DB->get_record('zoomlink', ['id' => $cm->instance], 'id, starttime, duration, opensbefore');
    if (!$zoomlink || empty($zoomlink->starttime)) {
        return;
    }
    [$status] = zoomlink_get_status($zoomlink);
    $badges = [
        'upcoming' => ['upcoming', 'bg-info'],
        'soon' => ['upcoming', 'bg-info'],
        'live' => ['live', 'bg-success'],
        'ended' => ['ended', 'bg-secondary'],
    ];
    [$label, $class] = $badges[$status];
    $when = get_string('whenwhere', 'zoomlink', [
        'start' => userdate($zoomlink->starttime, get_string('strftimedaydatetime', 'langconfig')),
        'duration' => $zoomlink->duration,
    ]);
    $html = html_writer::div(
        html_writer::tag('i', '', ['class' => 'fa-regular fa-calendar me-1', 'aria-hidden' => 'true']) . s($when) . ' ' .
        html_writer::span($status === 'ended' ? get_string('ended', 'zoomlink') : get_string($label, 'zoomlink'),
            'badge ' . $class . ' ms-1'),
        'small text-muted');
    $cm->set_after_link($html);
}

/**
 * The action of the class in the timeline block: join it.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_zoomlink_core_calendar_provide_event_action(calendar_event $event,
                                                         \core_calendar\action_factory $factory, int $userid = 0) {
    global $DB;
    $cm = get_fast_modinfo($event->courseid, $userid)->instances['zoomlink'][$event->instance] ?? null;
    $zoomlink = $DB->get_record('zoomlink', ['id' => $event->instance]);
    if (!$cm || !$cm->uservisible || !$zoomlink) {
        return null;
    }
    [$status, $canjoin] = zoomlink_get_status($zoomlink);
    if ($status === 'ended') {
        return null;
    }
    return $factory->create_instance(get_string('join', 'zoomlink'),
        new moodle_url('/mod/zoomlink/view.php', ['id' => $cm->id]), 1, $canjoin);
}

/**
 * Marks the class as viewed, for completion and logs.
 *
 * @param stdClass $zoomlink
 * @param stdClass $course
 * @param cm_info|stdClass $cm
 * @param context_module $context
 */
function zoomlink_view($zoomlink, $course, $cm, $context) {
    $event = \mod_zoomlink\event\course_module_viewed::create([
        'context' => $context,
        'objectid' => $zoomlink->id,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('zoomlink', $zoomlink);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

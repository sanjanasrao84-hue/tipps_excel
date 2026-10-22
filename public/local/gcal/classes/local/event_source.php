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

namespace local_gcal\local;

use context_course;
use context_coursecat;
use context_system;
use core_course_category;
use moodle_url;
use stdClass;

/**
 * Academic events of a user, read with the Moodle calendar API so that enrolments, groups, activity
 * visibility and organisation (category) boundaries are applied by Moodle itself.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_source {

    /** @var array Event categories: key => [Google colorId, CSS colour]. */
    public const CATEGORIES = [
        'lesson' => ['7', '#0f7c80'],
        'live' => ['9', '#3b5bdb'],
        'session' => ['2', '#2f9e44'],
        'assignment' => ['6', '#e8590c'],
        'assessment' => ['11', '#c92a2a'],
        'activity' => ['8', '#5c677d'],
        'course' => ['10', '#1f7fb6'],
        'organisation' => ['3', '#6f5fb8'],
        'institution' => ['5', '#b08900'],
        'reminder' => ['1', '#7b8aa0'],
        'personal' => ['', '#9aa5b1'],
    ];

    /** @var string[] Categories that can be chosen when creating an event. */
    public const CREATABLE = ['lesson', 'live', 'session', 'assessment', 'course'];

    /** @var string[] Modules whose dates are assessments. */
    private const ASSESSMENT_MODULES = ['quiz', 'workshop', 'lesson', 'h5pactivity', 'scorm'];

    /** @var int Duration given in Google to deadlines (Moodle events without duration), in seconds. */
    public const DEADLINE_LENGTH = 900;

    /** @var stdClass User whose events are read. */
    private stdClass $user;

    /** @var array Course records by id. */
    private array $courses = [];

    /**
     * Constructor.
     *
     * @param stdClass $user
     */
    public function __construct(stdClass $user) {
        $this->user = $user;
    }

    /**
     * Academic events of the user in a time range.
     *
     * @param int $from
     * @param int $to
     * @param int $courseid Only this course (0 for all)
     * @param int $organisationid Only courses of this organisation (top-level category id, 0 for all)
     * @return stdClass[] normalised events by id
     */
    public function get_events(int $from, int $to, int $courseid = 0, int $organisationid = 0): array {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $courses = calendar_get_default_courses(null, '*', false, $this->user->id);
        foreach ($courses as $course) {
            $this->courses[$course->id] = $course;
        }
        [$courseids, $groupids, $userid] = calendar_set_filters($courses, true, $this->user);
        $categoryids = $this->visible_categories($courses);

        $events = calendar_get_legacy_events($from, $to, [$this->user->id], $groupids ?: false, $courseids ?: false,
            true, true, $categoryids ?: false);

        $result = [];
        foreach ($events as $event) {
            $normalised = $this->normalise($event);
            if (!$normalised) {
                continue;
            }
            if ($courseid && $normalised->courseid != $courseid) {
                continue;
            }
            if ($organisationid && $normalised->organisationid != $organisationid) {
                continue;
            }
            $result[$normalised->id] = $normalised;
        }
        return $result;
    }

    /**
     * Categories whose events the user can see: categories of their courses and their parents, and categories they
     * can manage (computed without the session cache, so it is correct when several users are synchronised).
     *
     * @param stdClass[] $courses
     * @return int[]
     */
    private function visible_categories(array $courses): array {
        $categories = [];
        $coursecategories = array_flip(array_map(fn($course) => $course->category, $courses));
        foreach (core_course_category::get_all(['returnhidden' => true]) as $category) {
            if (isset($coursecategories[$category->id])
                    || has_capability('moodle/category:manage', $category->get_context(), $this->user, false)) {
                $categories[$category->id] = true;
                foreach ($category->get_parents() as $parentid) {
                    $categories[$parentid] = true;
                }
            }
        }
        return array_keys($categories);
    }

    /**
     * Event in the form used by the sync and the calendar page.
     *
     * @param stdClass $event Legacy event
     * @return stdClass|null null when the event should not be shown
     */
    public function normalise(stdClass $event): ?stdClass {
        global $DB;

        $course = null;
        if (!empty($event->courseid) && $event->courseid != SITEID) {
            $course = $this->courses[$event->courseid] ?? $DB->get_record('course', ['id' => $event->courseid],
                'id, shortname, fullname, category');
            if (!$course) {
                return null;
            }
            $this->courses[$course->id] = $course;
        }

        $url = null;
        $cmid = 0;
        $editdatesurl = null;
        if (!empty($event->modulename) && $course) {
            $modinfo = get_fast_modinfo($course->id, $this->user->id);
            $cm = $modinfo->instances[$event->modulename][$event->instance] ?? null;
            if (!$cm || !$cm->uservisible) {
                return null;
            }
            $cmid = $cm->id;
            $url = $cm->url ? $cm->url->out(false) : (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
            if (has_capability('moodle/course:manageactivities', $cm->context, $this->user)) {
                $editdatesurl = (new moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(false);
            }
        } else if ($course) {
            $url = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        }

        $categoryid = !empty($event->categoryid) ? (int) $event->categoryid : ($course ? (int) $course->category : 0);
        $organisationid = 0;
        if ($categoryid && ($category = core_course_category::get($categoryid, IGNORE_MISSING, true))) {
            $parents = $category->get_parents();
            $organisationid = $parents ? (int) reset($parents) : (int) $category->id;
        }

        $normalised = (object) [
            'id' => (int) $event->id,
            'name' => format_string($event->name, true, ['context' => $course ? context_course::instance($course->id)
                : context_system::instance()]),
            'description' => (string) $event->description,
            'descriptionformat' => (int) ($event->format ?? FORMAT_HTML),
            'eventtype' => (string) $event->eventtype,
            'modulename' => (string) ($event->modulename ?? ''),
            'instance' => (int) ($event->instance ?? 0),
            'cmid' => $cmid,
            'courseid' => $course ? (int) $course->id : 0,
            'courseshortname' => $course ? format_string($course->shortname) : '',
            'coursefullname' => $course ? format_string($course->fullname) : '',
            'categoryid' => !empty($event->categoryid) ? (int) $event->categoryid : 0,
            'organisationid' => $organisationid,
            'groupid' => (int) ($event->groupid ?? 0),
            'userid' => (int) ($event->userid ?? 0),
            'timestart' => (int) $event->timestart,
            'timeduration' => (int) $event->timeduration,
            'location' => (string) ($event->location ?? ''),
            'repeatid' => (int) ($event->repeatid ?? 0),
            'timemodified' => (int) ($event->timemodified ?? 0),
            'url' => $url,
            'editdatesurl' => $editdatesurl,
        ];
        $normalised->category = $this->category($normalised);
        [$normalised->canedit, $normalised->candelete] = $this->permissions($normalised);
        return $normalised;
    }

    /**
     * Category of an event.
     *
     * @param stdClass $event normalised event
     * @return string
     */
    private function category(stdClass $event): string {
        global $DB;
        static $meta = [];

        if (!array_key_exists($event->id, $meta)) {
            $meta[$event->id] = $DB->get_field('local_gcal_eventmeta', 'category', ['eventid' => $event->id]) ?: null;
        }
        if ($meta[$event->id] && isset(self::CATEGORIES[$meta[$event->id]])) {
            return $meta[$event->id];
        }
        if ($event->modulename === 'zoomlink') {
            return 'live';
        }
        if ($event->modulename === 'assign') {
            return 'assignment';
        }
        if (in_array($event->modulename, self::ASSESSMENT_MODULES)) {
            return 'assessment';
        }
        if ($event->modulename !== '') {
            return 'activity';
        }
        switch ($event->eventtype) {
            case 'category':
                return 'organisation';
            case 'site':
                return 'institution';
            case 'user':
                return 'reminder';
            default:
                return 'course';
        }
    }

    /**
     * Whether the user may edit and delete an event. Activity dates are changed in the activity settings.
     *
     * @param stdClass $event normalised event
     * @return bool[] [canedit, candelete]
     */
    private function permissions(stdClass $event): array {
        if ($event->modulename !== '') {
            return [false, false];
        }
        switch ($event->eventtype) {
            case 'user':
                $allowed = $event->userid == $this->user->id;
                break;
            case 'course':
            case 'group':
                $allowed = $event->courseid
                    && has_capability('moodle/calendar:manageentries', context_course::instance($event->courseid), $this->user);
                break;
            case 'category':
                $allowed = $event->categoryid && has_capability('moodle/calendar:manageentries',
                    context_coursecat::instance($event->categoryid), $this->user);
                break;
            case 'site':
                $allowed = has_capability('moodle/calendar:manageentries', context_system::instance(), $this->user);
                break;
            default:
                $allowed = false;
        }
        return [$allowed, $allowed];
    }

    /**
     * Whether changes made in Google Calendar may be applied to this event in Moodle (two-way sync).
     * Activity dates (deadlines, quizzes, live classes) are one-way: they are changed in the activity.
     *
     * @param stdClass $event normalised event
     * @return bool
     */
    public static function is_two_way(stdClass $event): bool {
        return $event->modulename === '' && $event->canedit;
    }

    /**
     * Google Calendar event for an academic event.
     *
     * @param stdClass $event normalised event
     * @param string $timezone User's time zone
     * @return array
     */
    public static function google_event(stdClass $event, string $timezone): array {
        global $CFG;

        $site = format_string(get_site()->shortname);
        $summary = $event->courseshortname !== '' ? "{$event->courseshortname} · {$event->name}" : $event->name;
        $duration = $event->timeduration > 0 ? $event->timeduration : self::DEADLINE_LENGTH;
        $lines = [];
        $text = trim(html_to_text(format_text($event->description, $event->descriptionformat, ['noclean' => false]), 0,
            false));
        if ($text !== '') {
            $lines[] = $text;
        }
        if ($event->coursefullname !== '') {
            $lines[] = get_string('googlecourse', 'local_gcal', $event->coursefullname);
        }
        if ($event->timeduration == 0 && $event->modulename !== '') {
            $lines[] = get_string('googledeadline', 'local_gcal', userdate($event->timestart));
        }
        if ($event->url) {
            $lines[] = get_string('googleopen', 'local_gcal', (object) ['site' => $site, 'url' => $event->url]);
        }
        $lines[] = get_string('googlemanaged', 'local_gcal', $site);

        $payload = [
            'summary' => $summary,
            'description' => implode("\n\n", $lines),
            'location' => $event->location,
            'start' => ['dateTime' => gmdate('Y-m-d\TH:i:s\Z', $event->timestart), 'timeZone' => $timezone],
            'end' => ['dateTime' => gmdate('Y-m-d\TH:i:s\Z', $event->timestart + $duration), 'timeZone' => $timezone],
            'status' => 'confirmed',
            'transparency' => $event->timeduration > 0 ? 'opaque' : 'transparent',
            'extendedProperties' => ['private' => [
                'lmsSite' => self::site_key(),
                'lmsEventId' => (string) $event->id,
                'lmsCategory' => $event->category,
            ]],
        ];
        $colour = self::CATEGORIES[$event->category][0] ?? '';
        if ($colour !== '') {
            $payload['colorId'] = $colour;
        }
        if ($event->url) {
            $payload['source'] = ['title' => $site, 'url' => $event->url];
        }
        return $payload;
    }

    /**
     * Identifier of this LMS in Google event properties (several sites may sync to the same Google account).
     *
     * @return string
     */
    public static function site_key(): string {
        global $CFG;
        return substr(sha1($CFG->wwwroot . '|' . ($CFG->siteidentifier ?? '')), 0, 16);
    }
}

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

use local_gcal\connection;
use local_gcal\google\api_exception;
use local_gcal\local\event_source;
use local_gcal\local\status;
use local_gcal\local\sync_manager;

/**
 * Upcoming academic events (and the user's own Google events), with the Google Calendar connection status.
 *
 * @package    block_gcal_upcoming
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_gcal_upcoming extends block_base {

    /** @var int Days shown. */
    private const DAYS = 14;

    /** @var int Events shown. */
    private const LIMIT = 6;

    /**
     * Initialises the block.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_gcal_upcoming');
    }

    /**
     * Show the short title on pages; pluginname must stay unique (it clashes with block_calendar_upcoming otherwise).
     *
     * @return void
     */
    public function specialization() {
        $this->title = get_string('blocktitle', 'block_gcal_upcoming');
    }

    /**
     * Where the block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true, 'site-index' => true, 'course-view' => true];
    }

    /**
     * Content.
     *
     * @return stdClass
     */
    public function get_content() {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/calendar/lib.php');

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $from = time();
        $to = $from + self::DAYS * DAYSECS;
        $courseid = ($this->page->course->id ?? SITEID) != SITEID ? (int) $this->page->course->id : 0;

        $events = [];
        foreach ((new event_source($USER))->get_events($from, $to, $courseid) as $event) {
            $events[] = [
                'title' => $event->name, 'start' => $event->timestart, 'deadline' => $event->timeduration == 0,
                'category' => get_string('category_' . $event->category, 'local_gcal'),
                'color' => event_source::CATEGORIES[$event->category][1],
                'course' => $event->courseshortname, 'url' => $event->url ?: '',
            ];
        }
        $connection = connection::get($USER->id);
        if (!$courseid && $connection && $connection->shows_personal_events()) {
            try {
                foreach (sync_manager::personal_events($connection, $from, $to) as $item) {
                    $events[] = [
                        'title' => $item['title'], 'start' => $item['start'], 'deadline' => false,
                        'category' => get_string('category_personal', 'local_gcal'),
                        'color' => event_source::CATEGORIES['personal'][1], 'course' => '', 'url' => $item['googleurl'],
                    ];
                }
            } catch (api_exception $e) {
                // The block still shows the academic events.
                debugging($e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        usort($events, fn($a, $b) => $a['start'] <=> $b['start']);
        $events = array_slice($events, 0, self::LIMIT);
        foreach ($events as &$event) {
            $event['day'] = userdate($event['start'], '%d');
            $event['month'] = userdate($event['start'], '%b');
            $event['time'] = userdate($event['start'], get_string('strftimetime', 'langconfig'));
        }

        $calendarurl = new moodle_url('/local/gcal/index.php', $courseid ? ['course' => $courseid] : []);
        $this->content->text = $this->page->get_renderer('core')->render_from_template('block_gcal_upcoming/content', [
            'events' => $events,
            'hasevents' => (bool) $events,
            'days' => self::DAYS,
            'status' => status::export($USER->id),
            'calendarurl' => $calendarurl->out(false),
        ]);
        return $this->content;
    }
}

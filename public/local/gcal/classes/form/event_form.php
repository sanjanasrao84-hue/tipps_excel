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

namespace local_gcal\form;

use calendar_event;
use context_course;
use context_coursecat;
use context_system;
use core_course_category;
use local_gcal\connection;
use local_gcal\local\event_source;
use local_gcal\local\sync_manager;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Creates or edits an academic event (lesson, live class, session...) in a course, an organisation or the
 * whole institution, according to the user's moodle/calendar:manageentries permissions.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_form extends \core_form\dynamic_form {

    /** @var int Most weekly repetitions. */
    private const MAX_REPEATS = 52;

    /**
     * Places where the current user may create events: "course:ID", "category:ID" and "site:0".
     *
     * @return string[] value => label
     */
    public static function targets(): array {
        $targets = [];
        if (has_capability('moodle/calendar:manageentries', context_system::instance())) {
            $targets['site:0'] = get_string('targetsite', 'local_gcal');
        }
        foreach (core_course_category::get_all(['returnhidden' => true]) as $category) {
            if (!$category->parent && has_capability('moodle/calendar:manageentries', $category->get_context())) {
                $targets['category:' . $category->id] = get_string('targetorganisation', 'local_gcal',
                    $category->get_formatted_name());
            }
        }
        $courses = get_user_capability_course('moodle/calendar:manageentries', null, true, 'fullname, shortname',
            'sortorder') ?: [];
        foreach ($courses as $course) {
            if ($course->id != SITEID) {
                $targets['course:' . $course->id] = get_string('targetcourse', 'local_gcal', format_string($course->fullname));
            }
        }
        return $targets;
    }

    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $id = (int) ($this->_ajaxformdata['id'] ?? 0);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if ($id) {
            $mform->addElement('static', 'targetname', get_string('target', 'local_gcal'));
        } else {
            $mform->addElement('select', 'target', get_string('target', 'local_gcal'), self::targets());
            $mform->addRule('target', null, 'required', null, 'client');
        }

        $categories = [];
        foreach (event_source::CREATABLE as $category) {
            $categories[$category] = get_string('category_' . $category, 'local_gcal');
        }
        $mform->addElement('select', 'category', get_string('eventcategory', 'local_gcal'), $categories);

        $mform->addElement('text', 'name', get_string('eventname', 'local_gcal'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('date_time_selector', 'timestart', get_string('eventstart', 'local_gcal'));

        $mform->addElement('text', 'duration', get_string('eventduration', 'local_gcal'), ['size' => 6]);
        $mform->setType('duration', PARAM_INT);
        $mform->setDefault('duration', 60);
        $mform->addHelpButton('duration', 'eventduration', 'local_gcal');

        $mform->addElement('select', 'mode', get_string('eventmode', 'local_gcal'), [
            'online' => get_string('modeonline', 'local_gcal'), 'offline' => get_string('modeoffline', 'local_gcal'),
        ]);
        $mform->addElement('text', 'location', get_string('eventlocation', 'local_gcal'), ['size' => 50]);
        $mform->setType('location', PARAM_TEXT);
        $mform->addHelpButton('location', 'eventlocation', 'local_gcal');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 4, 'cols' => 50]);
        $mform->setType('description', PARAM_TEXT);

        if (!$id) {
            $mform->addElement('text', 'repeats', get_string('repeatweekly', 'local_gcal'), ['size' => 4]);
            $mform->setType('repeats', PARAM_INT);
            $mform->setDefault('repeats', 1);
            $mform->addHelpButton('repeats', 'repeatweekly', 'local_gcal');
        }
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['id']) && !array_key_exists($data['target'] ?? '', self::targets())) {
            $errors['target'] = get_string('nopermissiontarget', 'local_gcal');
        }
        if (($data['duration'] ?? 0) < 0 || ($data['duration'] ?? 0) > 24 * 60) {
            $errors['duration'] = get_string('invalidduration', 'local_gcal');
        }
        if (empty($data['id']) && (($data['repeats'] ?? 1) < 1 || ($data['repeats'] ?? 1) > self::MAX_REPEATS)) {
            $errors['repeats'] = get_string('invalidrepeats', 'local_gcal', self::MAX_REPEATS);
        }
        return $errors;
    }

    /**
     * Context of the form.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        return context_system::instance();
    }

    /**
     * Checks the user may use the form.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        $id = (int) ($this->_ajaxformdata['id'] ?? $this->optional_param('id', 0, PARAM_INT));
        if ($id) {
            $event = calendar_event::load($id);
            if (!empty($event->modulename) || !calendar_edit_event_allowed($event, true)) {
                throw new \moodle_exception('nopermissions', 'error', '', get_string('editevent', 'local_gcal'));
            }
        } else if (!self::targets()) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('createevent', 'local_gcal'));
        }
    }

    /**
     * Saves the event (and its weekly repetitions).
     *
     * @return array
     */
    public function process_dynamic_submission() {
        global $DB, $USER;
        $data = $this->get_data();

        $fields = [
            'name' => $data->name,
            'description' => $data->description,
            'format' => FORMAT_PLAIN,
            'timestart' => $data->timestart,
            'timeduration' => max(0, (int) $data->duration) * MINSECS,
            'location' => trim($data->location),
        ];

        if (!empty($data->id)) {
            $event = calendar_event::load($data->id);
            $event->update((object) $fields, true);
            $eventids = [(int) $data->id];
        } else {
            [$type, $targetid] = explode(':', $data->target);
            $fields += [
                'eventtype' => $type,
                'courseid' => $type === 'course' ? (int) $targetid : SITEID,
                'categoryid' => $type === 'category' ? (int) $targetid : 0,
                'userid' => $USER->id,
                'visible' => 1,
            ];
            if ($data->repeats > 1) {
                $fields['repeat'] = 1;
                $fields['repeats'] = (int) $data->repeats;
            }
            $event = calendar_event::create((object) $fields, true);
            $eventids = $event->repeatid
                ? $DB->get_fieldset_select('event', 'id', 'repeatid = ?', [$event->repeatid])
                : [(int) $event->id];
        }

        foreach ($eventids as $eventid) {
            $meta = $DB->get_record('local_gcal_eventmeta', ['eventid' => $eventid]);
            $record = (object) ['eventid' => $eventid, 'category' => $data->category, 'mode' => $data->mode];
            if ($meta) {
                $record->id = $meta->id;
                $DB->update_record('local_gcal_eventmeta', $record);
            } else {
                $DB->insert_record('local_gcal_eventmeta', $record);
            }
        }

        // The author's own Google Calendar is updated straight away; other users through the sync tasks.
        $connection = connection::get($USER->id);
        if ($connection && $connection->is_active()) {
            (new sync_manager($connection))->sync();
        }
        return ['eventid' => (int) $event->id, 'count' => count($eventids)];
    }

    /**
     * Loads the form data.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $id = (int) ($this->_ajaxformdata['id'] ?? 0);
        if (!$id) {
            // Wall-clock start chosen in the calendar ("YYYY-MM-DDTHH:MM"), in the user's time zone.
            $timestart = 0;
            $start = (string) ($this->_ajaxformdata['start'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $start)) {
                $timestart = (new \DateTimeImmutable($start, \core_date::get_user_timezone_object()))->getTimestamp();
            }
            $this->set_data([
                'timestart' => $timestart ?: (int) (ceil(time() / HOURSECS) * HOURSECS),
                'duration' => 60, 'repeats' => 1, 'category' => 'lesson', 'mode' => 'online',
            ]);
            return;
        }
        $event = calendar_event::load($id);
        $meta = $DB->get_record('local_gcal_eventmeta', ['eventid' => $id]);
        if ($event->eventtype === 'category') {
            $target = get_string('targetorganisation', 'local_gcal',
                core_course_category::get($event->categoryid, IGNORE_MISSING, true)?->get_formatted_name() ?? '');
        } else if ($event->eventtype === 'site') {
            $target = get_string('targetsite', 'local_gcal');
        } else if ($event->eventtype === 'user') {
            $target = get_string('targetuser', 'local_gcal');
        } else {
            $target = get_string('targetcourse', 'local_gcal', format_string(get_course($event->courseid)->fullname));
        }
        $this->set_data([
            'id' => $id,
            'targetname' => $target,
            'category' => $meta->category ?? 'course',
            'mode' => $meta->mode ?? 'online',
            'name' => $event->name,
            'timestart' => $event->timestart,
            'duration' => (int) round($event->timeduration / MINSECS),
            'location' => $event->location,
            'description' => trim(html_to_text(format_text($event->description, $event->format), 0, false)),
        ]);
    }

    /**
     * Page of the form when not in a modal.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/gcal/index.php');
    }
}

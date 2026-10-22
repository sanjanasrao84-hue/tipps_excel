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
 * Settings form of the Zoom meeting link activity.
 *
 * @package    mod_zoomlink
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once(__DIR__ . '/lib.php');

/**
 * Settings form of the Zoom meeting link activity.
 */
class mod_zoomlink_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => 48]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'meeting', get_string('meeting', 'zoomlink'));
        $mform->setExpanded('meeting');
        $mform->addElement('text', 'joinurl', get_string('joinurl', 'zoomlink'),
            ['size' => 60, 'placeholder' => 'https://us05web.zoom.us/j/81234567890?pwd=...']);
        $mform->setType('joinurl', PARAM_RAW_TRIMMED);
        $mform->addRule('joinurl', null, 'required', null, 'client');
        $mform->addHelpButton('joinurl', 'joinurl', 'zoomlink');

        $mform->addElement('text', 'passcode', get_string('passcode', 'zoomlink'), ['size' => 20]);
        $mform->setType('passcode', PARAM_TEXT);
        $mform->addHelpButton('passcode', 'passcode', 'zoomlink');

        $mform->addElement('header', 'schedule', get_string('schedule', 'zoomlink'));
        $mform->setExpanded('schedule');
        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'zoomlink'), ['optional' => true]);
        $mform->setDefault('starttime', strtotime('tomorrow 19:00'));
        $mform->addHelpButton('starttime', 'starttime', 'zoomlink');

        $mform->addElement('text', 'duration', get_string('duration', 'zoomlink'), ['size' => 6]);
        $mform->setType('duration', PARAM_INT);
        $mform->setDefault('duration', 60);
        $mform->addHelpButton('duration', 'duration', 'zoomlink');
        $mform->disabledIf('duration', 'starttime[enabled]');

        $mform->addElement('select', 'opensbefore', get_string('opensbefore', 'zoomlink'),
            [0 => 0, 5 => 5, 10 => 10, 15 => 15, 30 => 30, 60 => 60]);
        $mform->setDefault('opensbefore', 15);
        $mform->addHelpButton('opensbefore', 'opensbefore', 'zoomlink');
        $mform->disabledIf('opensbefore', 'starttime[enabled]');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Checks the link and duration.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!zoomlink_is_valid_joinurl(trim($data['joinurl'] ?? ''))) {
            $errors['joinurl'] = get_string('joinurlinvalid', 'zoomlink');
        }
        if (!empty($data['starttime']) && ((int) $data['duration'] < 5 || (int) $data['duration'] > 1440)) {
            $errors['duration'] = get_string('durationinvalid', 'zoomlink');
        }
        return $errors;
    }
}

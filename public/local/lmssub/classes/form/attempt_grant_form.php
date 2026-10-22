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

namespace local_lmssub\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Confirms making the student of a payment attempt a subscriber: duration and reason.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_grant_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        foreach (['source' => PARAM_ALPHA, 'id' => PARAM_INT, 'returnurl' => PARAM_LOCALURL] as $name => $type) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, $type);
        }

        grant_form::add_duration_fields($mform);

        $mform->addElement('textarea', 'reason', get_string('reason', 'local_lmssub'), ['rows' => 2, 'cols' => 60]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string($this->_customdata['subscribed'] ? 'extendsubscription' : 'markassubscriber',
            'local_lmssub'));
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
        if (grant_form::get_days($data) <= 0) {
            $errors['customdays'] = get_string('error_duration', 'local_lmssub');
        }
        if (trim($data['reason'] ?? '') === '') {
            $errors['reason'] = get_string('required');
        }
        return $errors;
    }
}

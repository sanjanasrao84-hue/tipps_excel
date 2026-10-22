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

use local_lmssub\plan_manager;

/**
 * Grant a subscription by hand (without payment).
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grant_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('autocomplete', 'userid', get_string('user'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
        ]);
        $mform->addRule('userid', null, 'required', null, 'client');

        $plans = [];
        foreach ($DB->get_records('local_lmssub_plan', null, 'active DESC, sortorder') as $plan) {
            $plans[$plan->id] = format_string($plan->name) . ($plan->active ? '' : ' (' . get_string('inactive') . ')');
        }
        $mform->addElement('select', 'planid', get_string('plan', 'local_lmssub'), $plans);
        $mform->addRule('planid', null, 'required', null, 'client');

        self::add_duration_fields($mform);

        $mform->addElement('textarea', 'reason', get_string('reason', 'local_lmssub'), ['rows' => 2, 'cols' => 60]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('grant', 'local_lmssub'));
    }

    /**
     * Adds a duration select (presets + custom) and a custom days field.
     *
     * @param \MoodleQuickForm $mform
     */
    public static function add_duration_fields(\MoodleQuickForm $mform): void {
        $durations = plan_manager::get_duration_presets() + [0 => get_string('duration_custom', 'local_lmssub')];
        $mform->addElement('select', 'durationpreset', get_string('duration', 'local_lmssub'), $durations);
        $mform->setDefault('durationpreset', 30);
        $mform->addElement('text', 'customdays', get_string('customdays', 'local_lmssub'), ['size' => 5]);
        $mform->setType('customdays', PARAM_INT);
        $mform->hideIf('customdays', 'durationpreset', 'neq', 0);
    }

    /**
     * Number of days chosen in the duration fields.
     *
     * @param array|\stdClass $data
     * @return int
     */
    public static function get_days($data): int {
        $data = (array) $data;
        return ((int) ($data['durationpreset'] ?? 0)) ?: (int) ($data['customdays'] ?? 0);
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
        if (self::get_days($data) <= 0) {
            $errors['customdays'] = get_string('error_duration', 'local_lmssub');
        }
        if (trim($data['reason'] ?? '') === '') {
            $errors['reason'] = get_string('required');
        }
        return $errors;
    }
}

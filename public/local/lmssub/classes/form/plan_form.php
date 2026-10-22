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
 * Add / edit plan form.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $editing = !empty($this->_customdata['editing']);
        $pricecount = max(1, (int) ($this->_customdata['pricecount'] ?? 4));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // General.
        $mform->addElement('header', 'general', get_string('general'));
        $mform->addElement('text', 'name', get_string('planname', 'local_lmssub'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $types = $editing ? plan_manager::get_types() : plan_manager::get_enabled_types();
        $mform->addElement('select', 'type', get_string('plantype', 'local_lmssub'), $types);
        $mform->addHelpButton('type', 'plantype', 'local_lmssub');
        if ($editing) {
            $mform->freeze('type');
        }

        $mform->addElement('advcheckbox', 'active', get_string('planactive', 'local_lmssub'));
        $mform->addHelpButton('active', 'planactive', 'local_lmssub');
        $mform->setDefault('active', 1);

        // What the plan covers.
        $mform->addElement('header', 'scopehdr', get_string('scope', 'local_lmssub'));
        $mform->setExpanded('scopehdr');

        $mform->addElement('course', 'courses', get_string('scope_courses', 'local_lmssub'),
            ['multiple' => true, 'includefrontpage' => false]);
        $mform->addHelpButton('courses', 'scope_courses', 'local_lmssub');
        $mform->hideIf('courses', 'type', 'eq', plan_manager::TYPE_ALLACCESS);

        $categories = \core_course_category::make_categories_list();
        $mform->addElement('autocomplete', 'categories', get_string('scope_categories', 'local_lmssub'), $categories,
            ['multiple' => true, 'noselectionstring' => get_string('scope_allcategories', 'local_lmssub')]);
        $mform->addHelpButton('categories', 'scope_categories', 'local_lmssub');
        $mform->hideIf('categories', 'type', 'neq', plan_manager::TYPE_ALLACCESS);

        $mform->addElement('advcheckbox', 'includesub', get_string('scope_includesub', 'local_lmssub'));
        $mform->setDefault('includesub', 1);
        $mform->hideIf('includesub', 'type', 'neq', plan_manager::TYPE_ALLACCESS);

        $mform->addElement('autocomplete', 'excludecategories', get_string('scope_excludecategories', 'local_lmssub'),
            $categories, ['multiple' => true]);
        $mform->hideIf('excludecategories', 'type', 'neq', plan_manager::TYPE_ALLACCESS);

        $mform->addElement('course', 'excludecourses', get_string('scope_excludecourses', 'local_lmssub'),
            ['multiple' => true, 'includefrontpage' => false]);
        $mform->addHelpButton('excludecourses', 'scope_excludecourses', 'local_lmssub');
        $mform->hideIf('excludecourses', 'type', 'neq', plan_manager::TYPE_ALLACCESS);

        // Prices, one per duration.
        $mform->addElement('header', 'priceshdr', get_string('prices', 'local_lmssub'));
        $mform->setExpanded('priceshdr');
        $mform->addElement('static', 'priceshelp', '', get_string('prices_help', 'local_lmssub'));

        $durations = plan_manager::get_duration_presets() + [0 => get_string('duration_custom', 'local_lmssub')];
        $group = [
            $mform->createElement('select', 'durationpreset', get_string('duration', 'local_lmssub'),
                ['' => get_string('choosedots')] + $durations),
            $mform->createElement('text', 'customdays', get_string('customdays', 'local_lmssub'),
                ['size' => 5, 'placeholder' => get_string('customdays', 'local_lmssub')]),
            $mform->createElement('text', 'amount', get_string('amount', 'local_lmssub'),
                ['size' => 8, 'placeholder' => get_string('amount', 'local_lmssub')]),
        ];
        $repeated = [$mform->createElement('group', 'pricegroup', get_string('priceoption', 'local_lmssub'), $group, ' ', false)];
        $this->repeat_elements($repeated, $pricecount, [
            'customdays' => ['type' => PARAM_INT],
            'amount' => ['type' => PARAM_LOCALISEDFLOAT],
        ], 'pricerepeats', 'priceadd', 1, get_string('addprice', 'local_lmssub'), true);

        // Policies.
        $mform->addElement('header', 'policyhdr', get_string('policies', 'local_lmssub'));
        $mform->addElement('text', 'devicelimit', get_string('devicelimit', 'local_lmssub'), ['size' => 3]);
        $mform->setType('devicelimit', PARAM_INT);
        $mform->setDefault('devicelimit', 1);
        $mform->addHelpButton('devicelimit', 'devicelimit', 'local_lmssub');

        $mform->addElement('text', 'gracedays', get_string('gracedays', 'local_lmssub'), ['size' => 3]);
        $mform->setType('gracedays', PARAM_INT);
        $mform->setDefault('gracedays', 3);
        $mform->addHelpButton('gracedays', 'gracedays', 'local_lmssub');

        $mform->addElement('advcheckbox', 'autorenew', get_string('autorenewallowed', 'local_lmssub'));
        $mform->addHelpButton('autorenew', 'autorenewallowed', 'local_lmssub');

        $this->add_action_buttons();
    }

    /**
     * Fills the form from a plan, its prices and its scope.
     *
     * @param \stdClass $plan
     */
    public function set_plan(\stdClass $plan): void {
        $data = clone $plan;
        $data->courses = $data->categories = $data->excludecourses = $data->excludecategories = [];
        $data->includesub = 1;
        foreach (plan_manager::get_scopes((int) $plan->id) as $scope) {
            switch ($scope->scopetype) {
                case plan_manager::SCOPE_COURSE:
                    $data->courses[] = $scope->targetid;
                    break;
                case plan_manager::SCOPE_CATEGORY:
                    $data->categories[] = $scope->targetid;
                    $data->includesub = $scope->includesub;
                    break;
                case plan_manager::SCOPE_EXCLUDECOURSE:
                    $data->excludecourses[] = $scope->targetid;
                    break;
                case plan_manager::SCOPE_EXCLUDECATEGORY:
                    $data->excludecategories[] = $scope->targetid;
                    break;
            }
        }
        $presets = plan_manager::get_duration_presets();
        $i = 0;
        foreach (plan_manager::get_prices((int) $plan->id) as $price) {
            $days = (int) $price->durationdays;
            $data->durationpreset[$i] = isset($presets[$days]) ? $days : 0;
            $data->customdays[$i] = isset($presets[$days]) ? '' : $days;
            $data->amount[$i] = format_float($price->amount / 100, 2);
            $i++;
        }
        $this->set_data($data);
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

        $type = $data['type'] ?? '';
        $courses = array_filter((array) ($data['courses'] ?? []));
        if (in_array($type, [plan_manager::TYPE_COURSE, plan_manager::TYPE_VIDEO], true) && count($courses) !== 1) {
            $errors['courses'] = get_string('error_onecourse', 'local_lmssub');
        } else if ($type === plan_manager::TYPE_BUNDLE && count($courses) < 2) {
            $errors['courses'] = get_string('error_bundlecourses', 'local_lmssub');
        }

        $valid = 0;
        foreach (array_keys((array) ($data['durationpreset'] ?? [])) as $i) {
            $preset = $data['durationpreset'][$i];
            $amount = $data['amount'][$i] ?? '';
            if ($preset === '' && ($amount === '' || $amount === null)) {
                continue;
            }
            $days = ((int) $preset) ?: (int) ($data['customdays'][$i] ?? 0);
            if ($preset === '' || $days <= 0) {
                $errors["pricegroup[$i]"] = get_string('error_duration', 'local_lmssub');
            } else if ((float) unformat_float((string) $amount) <= 0) {
                $errors["pricegroup[$i]"] = get_string('error_amount', 'local_lmssub');
            } else {
                $valid++;
            }
        }
        if (!$valid && !preg_grep('/^pricegroup/', array_keys($errors))) {
            $errors['pricegroup[0]'] = get_string('error_noprices', 'local_lmssub');
        }

        if ((int) ($data['devicelimit'] ?? 0) < 1) {
            $errors['devicelimit'] = get_string('error_devicelimit', 'local_lmssub');
        }
        if ((int) ($data['gracedays'] ?? 0) < 0) {
            $errors['gracedays'] = get_string('error_gracedays', 'local_lmssub');
        }
        return $errors;
    }

    /**
     * Returns the data, with the price rows collected into $data->prices.
     *
     * @return \stdClass|null
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
        $data->prices = [];
        foreach (array_keys((array) ($data->durationpreset ?? [])) as $i) {
            $preset = $data->durationpreset[$i];
            if ($preset === '') {
                continue;
            }
            $days = ((int) $preset) ?: (int) ($data->customdays[$i] ?? 0);
            $data->prices[] = ['durationdays' => $days, 'amount' => (float) $data->amount[$i]];
        }
        return $data;
    }
}

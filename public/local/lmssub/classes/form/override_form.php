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
 * Add "always allow" or "always block" overrides, or pick users and courses for the access checker.
 *
 * Several users and several courses can be chosen; every user and course pair is processed.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override_form extends \moodleform {

    /** @var int Most user and course pairs processed in one go. */
    const MAX_PAIRS = 1000;

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $checker = !empty($this->_customdata['checker']);

        // One search box for users and cohorts. A user's value is its id; a cohort's is "c" + its id,
        // and choosing it adds all its members.
        $mform->addElement('autocomplete', 'userids', get_string('userscohorts', 'local_lmssub'), [], [
            'ajax' => 'local_lmssub/form_user_cohort_selector',
            'multiple' => true,
            'placeholder' => get_string('searchuserscohorts', 'local_lmssub'),
            'valuehtmlcallback' => [self::class, 'value_label'],
        ]);
        $mform->setType('userids', PARAM_ALPHANUM);
        $mform->addRule('userids', null, 'required', null, 'client');
        $mform->addHelpButton('userids', 'bulkusers', 'local_lmssub');

        $mform->addElement('course', 'courseids', get_string('courses'), ['multiple' => true, 'includefrontpage' => false]);
        $mform->addRule('courseids', null, 'required', null, 'client');
        $mform->addHelpButton('courseids', 'bulkcourses', 'local_lmssub');

        if ($checker) {
            $this->add_action_buttons(false, get_string('checkaccess', 'local_lmssub'));
            return;
        }

        $mform->addElement('select', 'action', get_string('overrideaction', 'local_lmssub'), [
            'block' => get_string('override_block', 'local_lmssub'),
            'allow' => get_string('override_allow', 'local_lmssub'),
        ]);
        $mform->addHelpButton('action', 'overrideaction', 'local_lmssub');

        $mform->addElement('textarea', 'reason', get_string('reason', 'local_lmssub'), ['rows' => 2, 'cols' => 60]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('addoverrides', 'local_lmssub'));
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
        if (empty($this->_customdata['checker']) && trim($data['reason'] ?? '') === '') {
            $errors['reason'] = get_string('required');
        }
        $userids = self::resolve_userids((object) $data);
        if (!$userids) {
            [, $cohortids] = self::split_values((array) ($data['userids'] ?? []));
            $errors['userids'] = get_string($cohortids ? 'cohortsempty' : 'nousersorcohorts', 'local_lmssub');
        }
        // Every pair is processed in one request, so keep a batch to a size that finishes quickly.
        $pairs = count($userids) * count((array) ($data['courseids'] ?? []));
        if ($pairs > self::MAX_PAIRS) {
            $errors['courseids'] = get_string('toomanypairs', 'local_lmssub',
                (object) ['pairs' => $pairs, 'max' => self::MAX_PAIRS]);
        }
        return $errors;
    }

    /**
     * Splits the values of the users box into user ids and cohort ids ("c" + id).
     *
     * @param array $values
     * @return array [int[] userids, int[] cohortids]
     */
    protected static function split_values(array $values): array {
        $userids = $cohortids = [];
        foreach ($values as $value) {
            $value = (string) $value;
            if (preg_match('/^c(\d+)$/', $value, $m)) {
                $cohortids[] = (int) $m[1];
            } else if (ctype_digit($value)) {
                $userids[] = (int) $value;
            }
        }
        return [$userids, $cohortids];
    }

    /**
     * Label of a chosen user or cohort, shown when the form is displayed again.
     *
     * @param string $value user id, or "c" + cohort id
     * @return string
     */
    public static function value_label($value): string {
        global $DB;
        [$userids, $cohortids] = self::split_values([$value]);
        if ($cohortids) {
            $cohort = $DB->get_record('cohort', ['id' => $cohortids[0]], 'id, name');
            if (!$cohort) {
                return '';
            }
            $members = $DB->count_records('cohort_members', ['cohortid' => $cohort->id]);
            return \html_writer::span(get_string('cohort', 'cohort'), 'badge bg-info text-dark me-1') .
                get_string('cohortoption', 'local_lmssub', (object) ['name' => format_string($cohort->name),
                    'members' => $members]);
        }
        $user = $userids ? \core_user::get_user($userids[0]) : false;
        return ($user && !$user->deleted) ? fullname($user) . ' (' . s($user->email) . ')' : '';
    }

    /**
     * The users to process: the users picked one by one plus every member of the chosen cohorts, without duplicates.
     *
     * Deleted accounts are left out.
     *
     * @param \stdClass $data submitted form data
     * @return int[]
     */
    public static function resolve_userids(\stdClass $data): array {
        global $DB;
        [$userids, $cohortids] = self::split_values((array) ($data->userids ?? []));
        if ($cohortids) {
            [$insql, $params] = $DB->get_in_or_equal($cohortids);
            $userids = array_merge($userids, $DB->get_fieldset_select('cohort_members', 'userid', "cohortid $insql", $params));
        }
        $userids = array_unique(array_filter($userids));
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        return array_map('intval', $DB->get_fieldset_select('user', 'id',
            "id $insql AND deleted = 0", $params));
    }
}

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
 * Subscription enrolment plugin.
 *
 * This is the only way subscription access enters a course. It never touches the existing
 * paid, manual or other enrolments: Moodle opens a course if any active enrolment exists,
 * so a course subscription and a full-LMS subscription can exist side by side.
 *
 * Enrolments are created, extended and suspended only by local_lmssub\access_engine.
 *
 * @package    enrol_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Subscription enrolment plugin class.
 *
 * @package    enrol_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_lmssub_plugin extends enrol_plugin {

    /**
     * Instances are added automatically when a plan covers the course.
     *
     * @param int $courseid
     * @return bool
     */
    public function can_add_instance($courseid) {
        return false;
    }

    /**
     * Deleting the instance would silently remove subscribers' access.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        return false;
    }

    /**
     * Teachers may still disable the method in a course.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        return has_capability('enrol/lmssub:config', context_course::instance($instance->courseid));
    }

    /**
     * Users cannot be enrolled by hand; access comes only from subscriptions.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_enrol(stdClass $instance) {
        return false;
    }

    /**
     * Users cannot be unenrolled by hand; access is ended by the subscription system.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return false;
    }

    /**
     * Enrolments cannot be edited by hand.
     *
     * @param stdClass $instance
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return false;
    }

    /**
     * Role assignments are protected so they are not changed outside the subscription system.
     *
     * @return bool
     */
    public function roles_protected() {
        return true;
    }

    /**
     * Returns the instance name.
     *
     * @param stdClass $instance
     * @return string
     */
    public function get_instance_name($instance) {
        return get_string('pluginname', 'enrol_lmssub');
    }

    /**
     * The "Subscribe to this course" panel on the page shown to learners without access.
     *
     * Lists the plans that open this course and links to the plans page, where the learner
     * picks a plan and a duration before paying.
     *
     * @param stdClass $instance
     * @return string
     */
    public function enrol_page_hook(stdClass $instance) {
        global $OUTPUT;

        $plans = \local_lmssub\plan_manager::get_purchasable_plans((int) $instance->courseid);
        if (!$plans) {
            return '';
        }

        $items = [];
        foreach ($plans as $plan) {
            $from = null;
            foreach ($plan->prices as $price) {
                if ($from === null || $price->amount < $from->amount) {
                    $from = $price;
                }
            }
            $a = (object) [
                'plan' => format_string($plan->name),
                'price' => \local_lmssub\plan_manager::format_amount((int) $from->amount, $from->currency),
                'duration' => \local_lmssub\plan_manager::duration_label((int) $from->durationdays),
            ];
            $label = get_string('planfrom', 'enrol_lmssub', $a);
            if ($plan->type === \local_lmssub\plan_manager::TYPE_ALLACCESS) {
                $label .= ' ' . html_writer::span(get_string('includedinfull', 'enrol_lmssub'), 'badge bg-success');
            }
            $items[] = $label;
        }
        $body = html_writer::tag('p', get_string('subscribe_desc', 'enrol_lmssub')) . html_writer::alist($items);

        if (isguestuser() || !isloggedin()) {
            $button = new single_button(new moodle_url(get_login_url()), get_string('loginsite'), 'get',
                single_button::BUTTON_PRIMARY);
        } else {
            $button = new single_button(new moodle_url('/local/lmssub/index.php', ['courseid' => $instance->courseid]),
                get_string('viewplans', 'enrol_lmssub'), 'get', single_button::BUTTON_PRIMARY);
        }
        $page = new \core_enrol\output\enrol_page($instance, get_string('subscribe', 'enrol_lmssub'), $body, [$button]);
        return $OUTPUT->render($page);
    }

    /**
     * Returns the existing instance in a course, creating it when missing.
     *
     * @param int $courseid
     * @return stdClass
     */
    public function get_or_create_instance(int $courseid): stdClass {
        global $DB;

        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'lmssub'], '*', IGNORE_MULTIPLE);
        if ($instance) {
            return $instance;
        }
        $course = get_course($courseid);
        $id = $this->add_instance($course, ['roleid' => $this->get_student_roleid()]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The role given to subscribers: the default student role only, never more.
     *
     * @return int
     */
    public function get_student_roleid(): int {
        global $CFG;
        require_once($CFG->libdir . '/accesslib.php');
        $roles = get_archetype_roles('student');
        return (int) reset($roles)->id;
    }

    /**
     * Restore an instance: reuse the existing one in the course.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        $instance = $this->get_or_create_instance((int) $course->id);
        $step->set_mapping('enrol', $oldid, $instance->id);
    }

    /**
     * Enrolments are not restored: the subscription system recreates them from active subscriptions.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $userid
     * @param int $oldinstancestatus
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid,
            $oldinstancestatus) {
    }
}

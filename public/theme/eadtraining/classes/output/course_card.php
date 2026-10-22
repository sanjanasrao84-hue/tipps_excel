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

namespace theme_eadtraining\output;

use context_course;
use core_course\external\course_summary_exporter;
use core_course_category;
use core_course_list_element;
use moodle_url;
use stdClass;

/**
 * Data of the course card (core_course/coursecard) for pages rendered in PHP: the home page,
 * the course catalogue and course search. The same template is used with core course data by
 * My courses, starred and recently accessed courses.
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_card {

    /** @var int Maximum length of the summary shown on a card. */
    private const SUMMARY_LENGTH = 150;

    /** @var string[] Enrolment methods that learners cannot use by themselves. */
    private const NON_SELF_SERVICE_ENROL = ['manual', 'guest', 'cohort', 'meta', 'category'];

    /**
     * Template context of a course card.
     *
     * @param core_course_list_element|stdClass $course
     * @return array
     */
    public static function export($course): array {
        global $CFG, $OUTPUT, $USER;

        require_once("{$CFG->libdir}/enrollib.php");

        if (!$course instanceof core_course_list_element) {
            $course = new core_course_list_element($course);
        }
        $context = context_course::instance($course->id);
        $viewurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        $fullname = format_string($course->fullname, true, ['context' => $context]);

        $image = course_summary_exporter::get_course_image($course) ?: $OUTPUT->get_default_image_for_courseid($course->id);
        $category = core_course_category::get($course->category, IGNORE_MISSING);

        $summary = '';
        if ($course->has_summary()) {
            $summary = format_text($course->summary, $course->summaryformat, ['context' => $context, 'overflowdiv' => false]);
            $summary = shorten_text(trim(html_to_text($summary, 0, false)), self::SUMMARY_LENGTH);
        }

        $enrolled = is_enrolled($context, null, '', true);
        $progress = null;
        if ($enrolled) {
            $progress = \core_completion\progress::get_course_progress_percentage(get_course($course->id), $USER->id);
        }

        $action = self::action($course, $context, $viewurl, $enrolled);
        $action['arialabel'] = get_string('coursecard_action', 'theme_eadtraining',
            (object) ['action' => $action['label'], 'course' => strip_tags($fullname)]);

        return [
            'id' => $course->id,
            'fullname' => $fullname,
            'viewurl' => $viewurl->out(false),
            'courseimage' => $image instanceof moodle_url ? $image->out(false) : (string) $image,
            'coursecategory' => $category ? $category->get_formatted_name() : '',
            'summary' => s($summary),
            'visible' => (bool) $course->visible,
            'enrolled' => $enrolled,
            'hasprogress' => $progress !== null,
            'progress' => $progress === null ? 0 : (int) floor($progress),
            'action' => $action,
        ];
    }

    /**
     * Card button, from the visitor's relationship to the course.
     *
     * @param core_course_list_element $course
     * @param context_course $context
     * @param moodle_url $viewurl
     * @param bool $enrolled
     * @return array label, url, primary
     */
    private static function action(core_course_list_element $course, context_course $context, moodle_url $viewurl,
            bool $enrolled): array {
        if ($enrolled) {
            return ['label' => get_string('coursecard_continue', 'theme_eadtraining'), 'url' => $viewurl->out(false),
                'primary' => true];
        }

        // Teachers and managers with access to every course.
        if (has_capability('moodle/course:view', $context)) {
            return ['label' => get_string('coursecard_open', 'theme_eadtraining'), 'url' => $viewurl->out(false),
                'primary' => false];
        }

        // Any enabled method that learners can use themselves (subscriptions, self enrolment, payments...).
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if (!in_array($instance->enrol, self::NON_SELF_SERVICE_ENROL)) {
                $url = new moodle_url('/enrol/index.php', ['id' => $course->id]);
                return ['label' => get_string('coursecard_enrol', 'theme_eadtraining'), 'url' => $url->out(false),
                    'primary' => true];
            }
        }

        return ['label' => get_string('coursecard_view', 'theme_eadtraining'), 'url' => $viewurl->out(false),
            'primary' => false];
    }
}

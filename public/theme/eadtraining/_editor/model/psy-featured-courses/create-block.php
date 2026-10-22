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
 * "Featured psychiatry courses" homepage section: every course the visitor can see, as the theme's course
 * cards, with an action that matches the visitor's access to each course (continue, open, enrol or view).
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_eadtraining\output\course_card;

/**
 * Function psy_featured_courses_createblocks
 *
 * Every course the visitor can see, in the course management order. The section is rendered without the
 * homepage cache ("nocache" in info.json), so enrolment and progress are always current.
 *
 * @param stdClass $page
 * @return string
 */
function psy_featured_courses_createblocks($page) {
    global $OUTPUT;

    $courses = core_course_category::top()->get_courses([
        'recursive' => true,
        'summary' => true,
        'sort' => ['sortorder' => 1],
    ]);

    if (!$courses) {
        $strings = theme_eadtraining_get_strings_langs();
        return '<p class="psy-courses__empty">' . s($strings['psy_courses_empty']) . '</p>';
    }

    $cards = '';
    foreach ($courses as $course) {
        $cards .= $OUTPUT->render_from_template('core_course/coursecard', course_card::export($course));
    }

    return '<div class="psy-courses__grid" role="list">' . $cards . '</div>';
}

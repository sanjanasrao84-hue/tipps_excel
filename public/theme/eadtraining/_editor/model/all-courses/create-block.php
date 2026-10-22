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
 * "All courses" homepage section: every course the visitor can see, as course cards.
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function all_courses_createblocks
 *
 * The same list as the site home "Available courses" (and the former "All courses" dashboard block):
 * visibility, course images and enrolment icons come from the course renderer.
 * Courses are shown in the order they were added (first course first).
 *
 * @param $page
 * @return string
 */
function all_courses_createblocks($page) {
    global $PAGE;

    $renderer = $PAGE->get_renderer('core', 'course');
    $html = $renderer->frontpage_available_courses(['id' => 1]);

    if (trim(strip_tags($html, '<a><img>')) === '') {
        return '<p class="all-courses-section__empty">' . get_string('nocoursesyet') . '</p>';
    }

    return $html;
}

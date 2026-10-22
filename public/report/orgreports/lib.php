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
 * Organisation reports dashboard: navigation.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Link in the course Reports menu.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function report_orgreports_extend_navigation_course($navigation, $course, $context) {
    global $DB;
    if ($course->id == SITEID || !has_capability('report/orgreports:viewcourse', $context)) {
        return;
    }
    $path = $DB->get_field('course_categories', 'path', ['id' => $course->category]);
    if ($path) {
        $orgid = (int)explode('/', trim($path, '/'))[0];
        $navigation->add(get_string('pluginname', 'report_orgreports'),
            new moodle_url('/report/orgreports/index.php', ['org' => $orgid]), navigation_node::TYPE_SETTING, null,
            'report_orgreports', new pix_icon('i/report', ''));
    }
}

/**
 * Link in a top-level category's settings.
 *
 * @param navigation_node $navigation
 * @param context_coursecat $context
 */
function report_orgreports_extend_navigation_category_settings($navigation, $context) {
    $category = core_course_category::get($context->instanceid, IGNORE_MISSING, true);
    if ($category && !$category->parent && has_capability('report/orgreports:view', $context)) {
        $navigation->add(get_string('pluginname', 'report_orgreports'),
            new moodle_url('/report/orgreports/index.php', ['org' => $category->id]), navigation_node::TYPE_SETTING, null,
            'report_orgreports', new pix_icon('i/report', ''));
        if (has_capability('report/orgreports:viewsales', $context)) {
            $navigation->add(get_string('paymentreports', 'report_orgreports'),
                new moodle_url('/report/orgreports/payments.php', ['org' => $category->id]), navigation_node::TYPE_SETTING,
                null, 'report_orgreports_payments', new pix_icon('i/payment', ''));
        }
    }
}

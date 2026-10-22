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
 * Library callbacks.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds "My subscriptions" to the user's own profile page.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 */
function local_lmssub_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (!$iscurrentuser || isguestuser($user)) {
        return;
    }
    $node = new \core_user\output\myprofile\node('miscellaneous', 'local_lmssub_my',
        get_string('mysubscriptions', 'local_lmssub'), null, new moodle_url('/local/lmssub/my.php'));
    $tree->add_node($node);
}

/**
 * Adds "Payment attempts" to the course menu, for the admins who manage subscribers.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_lmssub_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if ($course->id == SITEID || !has_capability('local/lmssub:managesubscribers', context_system::instance())) {
        return;
    }
    $navigation->add(get_string('paymentattempts', 'local_lmssub'),
        new moodle_url('/local/lmssub/attempts.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING, null, 'local_lmssub_attempts', new pix_icon('i/payment', ''));
}

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

namespace local_gcal;

use core\hook\after_config;
use core\hook\navigation\primary_extend;
use moodle_url;

/**
 * Hook callbacks.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Adds the calendar to the primary navigation.
     *
     * @param primary_extend $hook
     * @return void
     */
    public static function primary_extend(primary_extend $hook): void {
        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        $hook->get_primaryview()->add(get_string('calendar', 'local_gcal'), new moodle_url('/local/gcal/index.php'),
            \navigation_node::TYPE_ROOTNODE, null, 'local_gcal_calendar');
    }

    /**
     * Makes this calendar the primary calendar: the core calendar page opens it instead (exports and
     * subscriptions of the core calendar keep working).
     *
     * @param after_config $hook
     * @return void
     */
    public static function after_config(after_config $hook): void {
        global $SCRIPT;
        if (during_initial_install() || CLI_SCRIPT || AJAX_SCRIPT || $SCRIPT !== '/calendar/view.php'
                || !get_config('local_gcal', 'primarycalendar') || !isloggedin() || isguestuser()) {
            return;
        }
        $params = [];
        if ($courseid = optional_param('course', 0, PARAM_INT)) {
            $params['course'] = $courseid;
        }
        if ($time = optional_param('time', 0, PARAM_INT)) {
            $params['date'] = date('Y-m-d', $time);
        }
        redirect(new moodle_url('/local/gcal/index.php', $params));
    }
}

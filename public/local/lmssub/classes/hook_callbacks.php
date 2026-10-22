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

namespace local_lmssub;

use core\hook\navigation\primary_extend;

/**
 * Hook callbacks.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Adds "Subscription plans" to the top menu for logged in learners.
     *
     * @param primary_extend $hook
     */
    public static function primary_extend(primary_extend $hook): void {
        if (during_initial_install() || !isloggedin() || isguestuser() || !plan_manager::get_enabled_types()) {
            return;
        }
        $hook->get_primaryview()->add(get_string('subscriptionplans', 'local_lmssub'),
            new \moodle_url('/local/lmssub/index.php'), \navigation_node::TYPE_ROOTNODE, null, 'local_lmssub_plans');
    }
}

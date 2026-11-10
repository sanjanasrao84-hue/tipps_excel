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
 * Upgrade.
 *
 * @package    block_gcal_calendar
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade steps.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_gcal_calendar_upgrade($oldversion) {
    if ($oldversion < 2026111000) {
        // Sites that had an earlier version: add the calendar to the Dashboards that do not have it yet.
        require_once(__DIR__ . '/install.php');
        xmldb_block_gcal_calendar_install();
        upgrade_block_savepoint(true, 2026111000, 'gcal_calendar');
    }
    if ($oldversion < 2026111001) {
        // The calendar goes just before the Timeline.
        require_once(__DIR__ . '/install.php');
        block_gcal_calendar_place_before_timeline();
        upgrade_block_savepoint(true, 2026111001, 'gcal_calendar');
    }
    return true;
}

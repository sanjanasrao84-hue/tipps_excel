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
 * Web service functions used by the calendar page.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_gcal_get_events' => [
        'classname' => '\local_gcal\external\get_events',
        'description' => 'Academic events of the current user and, when connected, their own Google Calendar events.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_gcal_get_status' => [
        'classname' => '\local_gcal\external\get_status',
        'description' => 'Google Calendar connection status of the current user.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_gcal_sync_now' => [
        'classname' => '\local_gcal\external\sync_now',
        'description' => 'Synchronises the current user with Google Calendar now.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_gcal_delete_event' => [
        'classname' => '\local_gcal\external\delete_event',
        'description' => 'Cancels (deletes) an academic event the current user may delete.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_gcal_set_preferences' => [
        'classname' => '\local_gcal\external\set_preferences',
        'description' => 'Changes the Google Calendar preferences of the current user.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];

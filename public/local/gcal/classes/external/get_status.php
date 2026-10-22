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

namespace local_gcal\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_gcal\local\status;

/**
 * Google Calendar connection status of the current user.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_status extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns the status.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;
        self::validate_context(context_system::instance());
        return self::export(status::export($USER->id));
    }

    /**
     * Status fields returned by the web services.
     *
     * @param array $status
     * @return array
     */
    public static function export(array $status): array {
        return array_intersect_key($status, array_flip(array_keys(self::fields())));
    }

    /**
     * Status fields.
     *
     * @return external_value[]
     */
    private static function fields(): array {
        return [
            'configured' => new external_value(PARAM_BOOL, 'Google Calendar is configured on the site'),
            'canconnect' => new external_value(PARAM_BOOL, 'The user may connect a Google account'),
            'connecturl' => new external_value(PARAM_URL, 'Connect (or reconnect) page'),
            'disconnecturl' => new external_value(PARAM_URL, 'Disconnect page'),
            'googlecalendarurl' => new external_value(PARAM_URL, 'Google Calendar'),
            'connected' => new external_value(PARAM_BOOL, 'The user connected a Google account'),
            'active' => new external_value(PARAM_BOOL, 'The connection works'),
            'needsreconnect' => new external_value(PARAM_BOOL, 'Access was revoked or expired'),
            'email' => new external_value(PARAM_TEXT, 'Google account'),
            'lastsync' => new external_value(PARAM_INT, 'Last synchronisation'),
            'lastsynctext' => new external_value(PARAM_TEXT, 'Last synchronisation, formatted'),
            'lastsyncstatus' => new external_value(PARAM_ALPHA, 'ok, partial or failed'),
            'lasterror' => new external_value(PARAM_TEXT, 'Last error'),
            'showpersonal' => new external_value(PARAM_BOOL, 'Own Google events shown'),
            'personalavailable' => new external_value(PARAM_BOOL, 'Own Google events can be shown'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Status colour'),
        ];
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(self::fields());
    }
}

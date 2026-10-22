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
use local_gcal\connection;
use local_gcal\local\status;

/**
 * Changes the Google Calendar preferences of the current user.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_preferences extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'showpersonal' => new external_value(PARAM_BOOL, 'Show own Google events in the LMS calendar'),
        ]);
    }

    /**
     * Saves the preferences.
     *
     * @param bool $showpersonal
     * @return array
     */
    public static function execute(bool $showpersonal): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['showpersonal' => $showpersonal]);
        self::validate_context(context_system::instance());

        $connection = connection::get($USER->id);
        if (!$connection) {
            throw new \moodle_exception('notconnected', 'local_gcal');
        }
        $connection->record->showpersonal = $params['showpersonal'] ? 1 : 0;
        $connection->save();
        \cache::make('local_gcal', 'personalevents')->purge();
        return get_status::export(status::export($USER->id));
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return get_status::execute_returns();
    }
}

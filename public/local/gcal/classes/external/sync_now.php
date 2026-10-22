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
use local_gcal\local\sync_manager;

/**
 * Synchronises the current user with Google Calendar now.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_now extends external_api {

    /** @var int Seconds between two manual syncs (protects the Google API quota). */
    private const MIN_INTERVAL = 20;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Synchronises.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/gcal:connect', $context);

        $connection = connection::get($USER->id);
        if (!$connection || !$connection->is_active()) {
            throw new \moodle_exception('notconnected', 'local_gcal');
        }
        $message = '';
        if (time() - (int) $connection->record->lastsync < self::MIN_INTERVAL) {
            $message = get_string('syncrecent', 'local_gcal');
        } else {
            $manager = new sync_manager($connection);
            $manager->sync();
            $message = get_string('syncresult', 'local_gcal', (object) $manager->result);
        }
        return ['status' => get_status::export(status::export($USER->id)), 'message' => $message];
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => get_status::execute_returns(),
            'message' => new external_value(PARAM_TEXT, 'Result'),
        ]);
    }
}

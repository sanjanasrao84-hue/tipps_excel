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

namespace local_gcal\local;

use local_gcal\connection;
use local_gcal\google\oauth;
use moodle_url;

/**
 * Connection status of the current user, for the calendar page, the dashboard block and web services.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status {

    /**
     * Status data.
     *
     * @param int $userid
     * @return array
     */
    public static function export(int $userid): array {
        $connection = connection::get($userid);
        $configured = oauth::is_configured();
        $canconnect = $configured && has_capability('local/gcal:connect', \context_system::instance());
        $data = [
            'configured' => $configured,
            'canconnect' => $canconnect,
            'connected' => (bool) $connection,
            'active' => $connection && $connection->is_active(),
            'needsreconnect' => $connection && !$connection->is_active(),
            'email' => $connection ? (string) $connection->record->googleemail : '',
            'lastsync' => $connection ? (int) $connection->record->lastsync : 0,
            'lastsynctext' => $connection && $connection->record->lastsync
                ? userdate($connection->record->lastsync, get_string('strftimedatetimeshort', 'langconfig'))
                : get_string('never'),
            'lastsyncstatus' => $connection ? (string) $connection->record->lastsyncstatus : '',
            'lasterror' => $connection ? (string) $connection->record->lasterror : '',
            'showpersonal' => $connection && !empty($connection->record->showpersonal),
            'personalavailable' => $connection && $connection->has_scope(oauth::SCOPE_PERSONAL)
                && (bool) get_config('local_gcal', 'showpersonal'),
            'connecturl' => (new moodle_url('/local/gcal/connect.php', ['sesskey' => sesskey()]))->out(false),
            'disconnecturl' => (new moodle_url('/local/gcal/disconnect.php'))->out(false),
            'googlecalendarurl' => 'https://calendar.google.com/calendar/r',
        ];
        $data['statusclass'] = $data['active'] ? ($data['lastsyncstatus'] === 'ok' || $data['lastsyncstatus'] === ''
            ? 'success' : 'warning') : ($data['needsreconnect'] ? 'danger' : 'secondary');
        return $data;
    }
}

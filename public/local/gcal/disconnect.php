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
 * Disconnects the user's Google account. The Moodle account, courses and events are not affected.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core\output\notification;
use local_gcal\connection;
use local_gcal\google\api_exception;
use local_gcal\google\client;
use local_gcal\google\oauth;
use local_gcal\local\logger;

require_login(null, false);
$url = new moodle_url('/local/gcal/disconnect.php');
$returnurl = new moodle_url('/local/gcal/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title(get_string('disconnect', 'local_gcal'));
$PAGE->set_heading(get_string('disconnect', 'local_gcal'));

$connection = connection::get($USER->id);
if (!$connection) {
    redirect($returnurl);
}

if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
    $removecalendar = optional_param('removecalendar', 0, PARAM_BOOL);
    $refreshtoken = $connection->get_refresh_token();
    if ($connection->is_active()) {
        try {
            if ($removecalendar && $connection->record->calendarid) {
                (new client($connection))->call('DELETE', '/calendars/' . rawurlencode($connection->record->calendarid));
            }
        } catch (api_exception $e) {
            logger::log($USER->id, 'warning', 'disconnect', $e->getMessage());
        }
    }
    if ($refreshtoken) {
        try {
            oauth::revoke($refreshtoken);
        } catch (Throwable $e) {
            // The local connection is removed anyway; the user can also remove access in their Google account.
            logger::log($USER->id, 'warning', 'revoke', $e->getMessage());
        }
    }
    $connection->delete();
    \cache::make('local_gcal', 'personalevents')->purge();
    logger::log($USER->id, 'info', 'disconnected', $removecalendar ? 'calendar removed' : 'calendar kept');
    redirect($returnurl, get_string('disconnected', 'local_gcal'), null, notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_gcal/disconnect', [
    'email' => s($connection->record->googleemail),
    'actionurl' => $url->out(false),
    'cancelurl' => $returnurl->out(false),
    'sesskey' => sesskey(),
    'cancremove' => $connection->is_active(),
]);
echo $OUTPUT->footer();

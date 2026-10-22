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
 * Connects the user's Google Calendar: sends them to the Google consent screen, then stores the
 * authorisation returned by Google through Moodle's OAuth 2 callback (/admin/oauth2callback.php).
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core\output\notification;
use local_gcal\connection;
use local_gcal\google\api_exception;
use local_gcal\google\oauth;
use local_gcal\local\logger;
use local_gcal\local\sync_manager;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot connect a calendar');
}
$context = context_system::instance();
require_capability('local/gcal:connect', $context);
require_sesskey();

$returnurl = new moodle_url('/local/gcal/index.php');
if (!oauth::is_configured()) {
    redirect($returnurl, get_string('notconfigured', 'local_gcal'), null, notification::NOTIFY_ERROR);
}

$code = optional_param('oauth2code', '', PARAM_RAW);
$error = optional_param('error', '', PARAM_ALPHAEXT);

if ($error !== '') {
    // The user declined, or Google refused the request.
    logger::log($USER->id, 'warning', 'connect', $error);
    redirect($returnurl, get_string('connectcancelled', 'local_gcal'), null, notification::NOTIFY_WARNING);
}

if ($code === '') {
    $thisurl = new moodle_url('/local/gcal/connect.php', ['sesskey' => sesskey()]);
    redirect(oauth::authorization_url($thisurl, (string) $USER->email));
}

try {
    $tokens = oauth::exchange_code($code);
    $granted = preg_split('/\s+/', (string) ($tokens['scope'] ?? ''));
    if (!in_array(oauth::SCOPE_CALENDAR, $granted)) {
        // Google lets users untick permissions: the calendar permission is required.
        if (!empty($tokens['access_token'])) {
            oauth::revoke($tokens['access_token']);
        }
        redirect($returnurl, get_string('scopemissing', 'local_gcal'), null, notification::NOTIFY_ERROR);
    }
    $email = oauth::get_email($tokens['access_token']);
    $connection = connection::save_authorisation((int) $USER->id, (int) oauth::get_issuer()->get('id'), $tokens, $email);
    logger::log($USER->id, 'info', 'connected', $email);
} catch (api_exception $e) {
    logger::log($USER->id, 'error', 'connect', $e->getMessage());
    redirect($returnurl, get_string('connectfailed', 'local_gcal', $e->getMessage()), null, notification::NOTIFY_ERROR);
}

$manager = new sync_manager($connection);
$manager->sync();
redirect($returnurl, get_string('connected', 'local_gcal', (object) ['email' => s($email),
    'count' => $manager->result['created'] + $manager->result['updated']]), null, notification::NOTIFY_SUCCESS);

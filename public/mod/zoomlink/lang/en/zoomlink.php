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
 * Strings of the Zoom meeting link activity.
 *
 * @package    mod_zoomlink
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['calendarevent'] = '{$a} (live class)';
$string['duration'] = 'Duration (minutes)';
$string['duration_help'] = 'How long the class lasts. After this time the class is shown as ended, but the link still works.';
$string['durationinvalid'] = 'Enter a duration between 5 and 1440 minutes.';
$string['ended'] = 'This class has ended.';
$string['join'] = 'Join Zoom meeting';
$string['joinopensat'] = 'The Join button opens {$a}.';
$string['joinurl'] = 'Zoom meeting link';
$string['joinurl_help'] = 'Paste the invite link of the meeting from Zoom (zoom.us > Meetings > the meeting > Copy Invite Link), for example https://us05web.zoom.us/j/81234567890?pwd=AbCd123. Links that contain ?pwd= let learners join without typing the passcode.';
$string['joinurlinvalid'] = 'Enter a Zoom meeting link starting with https://, such as https://us05web.zoom.us/j/81234567890?pwd=...';
$string['live'] = 'Live now';
$string['meeting'] = 'Meeting';
$string['modulename'] = 'Zoom meeting link';
$string['modulename_help'] = 'A live class on Zoom. Paste the link of a meeting you created in Zoom and set its date and time: learners see when the class is, get it in their calendar, and join with one click. No Zoom API connection is needed.';
$string['modulenameplural'] = 'Zoom meeting links';
$string['nostart'] = 'No fixed time: the link is always open.';
$string['opensbefore'] = 'Learners can join (minutes before the start)';
$string['opensbefore_help'] = 'Before this, the Join button is not shown, so learners do not join an empty meeting days early.';
$string['passcode'] = 'Passcode';
$string['passcode_help'] = 'Optional. Only needed when the link does not contain the passcode (no ?pwd= in it); learners see it next to the Join button.';
$string['pluginadministration'] = 'Zoom meeting link administration';
$string['pluginname'] = 'Zoom meeting link';
$string['privacy:metadata'] = 'The Zoom meeting link activity stores no personal data. Learners join Zoom directly, under Zoom\'s own privacy policy.';
$string['schedule'] = 'Schedule';
$string['starttime'] = 'Start time';
$string['starttime_help'] = 'When the class starts. Untick Enable for a link that is always open, such as a weekly recurring meeting with no single date.';
$string['status'] = 'Status';
$string['upcoming'] = 'Upcoming';
$string['whenwhere'] = '{$a->start} · {$a->duration} min';
$string['zoomlink:addinstance'] = 'Add a Zoom meeting link';
$string['zoomlink:view'] = 'View Zoom meeting link';

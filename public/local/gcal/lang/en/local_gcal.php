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
 * Strings.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allcourses'] = 'All courses';
$string['allday'] = 'All day';
$string['allorganisations'] = 'All organisations';
$string['busy'] = 'Busy';
$string['cachedef_personalevents'] = 'Own Google Calendar events of the current user (session only)';
$string['calendar'] = 'Calendar';
$string['calendardescription'] = 'Academic events from {$a}: classes, sessions, deadlines and assessments. Managed automatically by the LMS.';
$string['calendarname'] = 'Calendar name';
$string['calendarname_desc'] = 'Name of the calendar created in each user\'s Google account for their academic events. Leave empty to use "Site short name – Academic".';
$string['cancelconfirm'] = 'Cancel "{$a}"? It is removed from the course calendar and from the Google Calendar of every participant.';
$string['cancelevent'] = 'Cancel event';
$string['category_activity'] = 'Activity dates';
$string['category_assessment'] = 'Quizzes and assessments';
$string['category_assignment'] = 'Assignment deadlines';
$string['category_course'] = 'Course events';
$string['category_institution'] = 'Institution events';
$string['category_lesson'] = 'Lessons and classes';
$string['category_live'] = 'Live classes';
$string['category_organisation'] = 'Organisation events';
$string['category_personal'] = 'My Google Calendar';
$string['category_reminder'] = 'My reminders';
$string['category_session'] = 'Sessions';
$string['close'] = 'Close';
$string['connect'] = 'Connect Google Calendar';
$string['connect_desc'] = 'Connect your Google account to get your classes, deadlines and assessments in Google Calendar.';
$string['connectcancelled'] = 'Google Calendar was not connected.';
$string['connected'] = 'Google Calendar connected ({$a->email}). {$a->count} academic events were added to your Google Calendar.';
$string['connectedto'] = 'Connected to Google Calendar';
$string['connectfailed'] = 'Google Calendar could not be connected: {$a}';
$string['connectunavailable'] = 'Google Calendar connection is not available for your account.';
$string['course'] = 'Course';
$string['createevent'] = 'Create event';
$string['deadline'] = 'deadline';
$string['defaultcalendarname'] = '{$a} – Academic';
$string['disconnect'] = 'Disconnect';
$string['disconnect_desc'] = 'Your academic events stop being synchronised. Your LMS account, courses and events are not affected, and you can connect again at any time.';
$string['disconnectconfirm'] = 'Disconnect Google account {$a}?';
$string['disconnected'] = 'Your Google account was disconnected.';
$string['editdates'] = 'Change activity dates';
$string['editevent'] = 'Edit event';
$string['eventcancelled'] = 'The event was cancelled.';
$string['eventcategories'] = 'Event types';
$string['eventcategory'] = 'Type';
$string['eventduration'] = 'Duration (minutes)';
$string['eventduration_help'] = 'Use 0 for a deadline or reminder without duration.';
$string['eventlocation'] = 'Location or online link';
$string['eventlocation_help'] = 'A room for an in-person session, or the meeting link for an online one.';
$string['eventmode'] = 'Delivery';
$string['eventname'] = 'Title';
$string['eventsaved'] = 'The event was saved and is being synchronised with Google Calendar.';
$string['eventstart'] = 'Starts';
$string['filters'] = 'Filters';
$string['futuredays'] = 'Days ahead';
$string['futuredays_desc'] = 'Academic events starting up to this many days ahead are synchronised.';
$string['googlecourse'] = 'Course: {$a}';
$string['googledeadline'] = 'Deadline: {$a}';
$string['googleerror'] = 'Google Calendar error ({$a->status} {$a->reason}): {$a->message}';
$string['googlemanaged'] = 'Synchronised from {$a}.';
$string['googleopen'] = 'Open in {$a->site}: {$a->url}';
$string['invalidduration'] = 'Enter a duration between 0 and 1440 minutes.';
$string['invalidrepeats'] = 'Enter a number between 1 and {$a}.';
$string['issuer'] = 'OAuth 2 service';
$string['issuer_desc'] = 'The Google OAuth 2 service users connect their calendar with.';
$string['lastsync'] = 'last synchronised {$a}';
$string['local/gcal:connect'] = 'Connect a Google account and synchronise academic events';
$string['local/gcal:viewsynclog'] = 'View the Google Calendar synchronisation log of all users';
$string['location'] = 'Location';
$string['modeoffline'] = 'In person';
$string['modeonline'] = 'Online';
$string['noevents'] = 'No events to display';
$string['nopermissiontarget'] = 'You cannot create events there.';
$string['notconfigured'] = 'Google Calendar is not configured on this site yet.';
$string['notconfiguredadmin'] = 'Google Calendar is not configured yet. Choose the Google OAuth 2 service in <a href="{$a}">Google Calendar settings</a>.';
$string['notconnected'] = 'Google Calendar is not connected';
$string['notgoogle'] = 'not Google';
$string['notsynced'] = 'Not in your Google Calendar yet (connect Google Calendar or sync now).';
$string['openactivity'] = 'Open activity';
$string['opencourse'] = 'Open course';
$string['openingoogle'] = 'Open in Google Calendar';
$string['openingooglecalendar'] = 'Open Google Calendar';
$string['organisation'] = 'Organisation';
$string['pastdays'] = 'Days back';
$string['pastdays_desc'] = 'Academic events that started up to this many days ago are still synchronised.';
$string['personalnote'] = 'From your own Google Calendar. Only you can see it here.';
$string['personalprivacy'] = 'Only you can see your own Google events. They are not stored in the LMS.';
$string['personalunavailable'] = 'Your own Google Calendar events could not be loaded.';
$string['pluginname'] = 'Google Calendar';
$string['primarycalendar'] = 'Primary calendar';
$string['primarycalendar_desc'] = 'Open this calendar instead of the standard Moodle calendar page. Calendar exports and subscriptions keep working.';
$string['privacy:metadata:connection'] = 'The Google account connected by the user, with encrypted access tokens.';
$string['privacy:metadata:connection:googleemail'] = 'Email address of the connected Google account.';
$string['privacy:metadata:connection:lastsync'] = 'Time of the last synchronisation.';
$string['privacy:metadata:connection:showpersonal'] = 'Whether the user\'s own Google events are shown in the LMS calendar.';
$string['privacy:metadata:connection:userid'] = 'The user.';
$string['privacy:metadata:eventmap'] = 'Links between Moodle events and the copies in the user\'s Google Calendar.';
$string['privacy:metadata:eventmap:eventid'] = 'The Moodle event.';
$string['privacy:metadata:eventmap:googleeventid'] = 'The event in Google Calendar.';
$string['privacy:metadata:google'] = 'Academic events the user can see are copied to their Google Calendar.';
$string['privacy:metadata:google:description'] = 'Event description and course name.';
$string['privacy:metadata:google:name'] = 'Event title.';
$string['privacy:metadata:google:times'] = 'Event start and end.';
$string['privacy:metadata:log'] = 'Synchronisation log.';
$string['privacy:metadata:log:action'] = 'What was done.';
$string['privacy:metadata:log:timecreated'] = 'When.';
$string['reconnect'] = 'Reconnect';
$string['reconnectneeded'] = 'Google Calendar needs to be reconnected';
$string['reconnectneeded_desc'] = 'Access to {$a} was revoked or expired. Synchronisation is paused until you reconnect.';
$string['removecalendar'] = 'Also delete the academic calendar from my Google account';
$string['repeatweekly'] = 'Occurrences (weekly)';
$string['repeatweekly_help'] = 'Number of weekly occurrences of the event, for example 12 for a term of weekly classes.';
$string['scopemissing'] = 'Google Calendar was not connected: permission to manage the academic calendar is needed. Connect again and keep that permission ticked.';
$string['setupinstructions'] = '<p>Users connect their own Google account; their academic events are copied to a calendar created by the LMS in their Google Calendar.</p><ol><li>In Google Cloud Console, enable the <strong>Google Calendar API</strong> and create an <strong>OAuth client ID</strong> (type "Web application") with the authorised redirect URI <code>{$a->redirecturi}</code>.</li><li>Create a Google service in <a href="{$a->oauthurl}">OAuth 2 services</a> with the client ID and secret.</li><li>Choose that service below.</li></ol>';
$string['showmypersonal'] = 'Show my own Google events';
$string['showpersonal'] = 'Show users\' own Google events';
$string['showpersonal_desc'] = 'Users can see their own Google Calendar events next to their academic events in the LMS calendar (read-only permission requested; events are read live and never stored or shown to others).';
$string['syncedtogoogle'] = 'In your Google Calendar.';
$string['syncfailed'] = 'Synchronisation failed.';
$string['syncing'] = 'Synchronising…';
$string['syncnow'] = 'Sync now';
$string['syncrecent'] = 'Your calendar was synchronised a few seconds ago.';
$string['syncresult'] = 'Synchronised: {$a->created} added, {$a->updated} updated, {$a->deleted} removed, {$a->fromgoogle} changes from Google, {$a->errors} errors.';
$string['target'] = 'Calendar';
$string['targetcourse'] = 'Course: {$a}';
$string['targetorganisation'] = 'Organisation: {$a}';
$string['targetsite'] = 'Whole institution';
$string['targetuser'] = 'Personal reminder';
$string['tasksyncall'] = 'Synchronise Google Calendars';
$string['today'] = 'Today';
$string['viewagenda'] = 'Agenda';
$string['viewday'] = 'Day';
$string['viewmonth'] = 'Month';
$string['viewweek'] = 'Week';
$string['when'] = 'When';

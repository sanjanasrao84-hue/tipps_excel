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

use calendar_event;
use core_date;
use local_gcal\connection;
use local_gcal\google\api_exception;
use local_gcal\google\client;
use stdClass;

/**
 * Synchronisation between Moodle academic events and a user's Google Calendar.
 *
 * Rules:
 * - Moodle to Google (all academic events the user can see, within the sync window): each event is copied to the
 *   user's academic calendar in Google. A link table and a private event property prevent duplicates; a content
 *   hash avoids unnecessary updates. Events deleted in Moodle, or no longer visible to the user (for example after
 *   unenrolment), are deleted from Google.
 * - Google to Moodle (two-way, only for events the user may edit in Moodle and that are not activity dates):
 *   changes and deletions made in Google are applied to the Moodle event, with the user's Moodle permissions.
 *   If the Moodle event also changed since the last sync, Moodle wins.
 * - Activity dates (deadlines, quizzes, live classes) and events the user may not edit are one-way: a change made
 *   in Google is reverted at the next sync.
 * - Events the user adds to the academic calendar in Google, and the user's other calendars, are never imported.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_manager {

    /** @var string Link table. */
    private const MAP = 'local_gcal_eventmap';

    /** @var connection */
    private connection $connection;

    /** @var stdClass */
    private stdClass $user;

    /** @var client */
    private client $client;

    /** @var event_source */
    private event_source $source;

    /** @var array Counters of the last sync. */
    public array $result = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'fromgoogle' => 0, 'reverted' => 0,
        'errors' => 0];

    /**
     * Constructor.
     *
     * @param connection $connection
     * @param client|null $client
     */
    public function __construct(connection $connection, ?client $client = null) {
        $this->connection = $connection;
        $this->user = \core_user::get_user($connection->record->userid, '*', MUST_EXIST);
        $this->client = $client ?? new client($connection);
        $this->source = new event_source($this->user);
    }

    /**
     * Synchronises the user. Must run as that user (web request) or from CLI/cron, where it switches to the user.
     *
     * @return bool true when everything was synchronised
     */
    public function sync(): bool {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/calendar/lib.php');

        if (!$this->connection->is_active()) {
            return false;
        }
        $previoususer = null;
        if ($USER->id != $this->user->id) {
            if (!CLI_SCRIPT) {
                throw new \coding_exception('A user can only be synchronised in their own session or from cron.');
            }
            $previoususer = $USER;
            \core\cron::setup_user($this->user);
        }
        \core_calendar\local\event\container::set_requesting_user($this->user->id);

        $status = 'ok';
        $error = null;
        try {
            $this->ensure_calendar();
            $this->pull();
            $this->push();
            if ($this->result['errors']) {
                $status = 'partial';
            }
        } catch (api_exception $e) {
            $status = 'failed';
            $error = $e->getMessage();
            logger::log($this->user->id, 'error', 'sync', $error);
            if ($e->needs_reconnect()) {
                $this->connection->mark_reconnect($error);
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
            logger::log($this->user->id, 'error', 'sync', $error);
        } finally {
            if ($previoususer) {
                \core\cron::setup_user($previoususer->id ? $previoususer : null);
            }
        }

        $this->connection->record->lastsync = time();
        $this->connection->record->lastsyncstatus = $status;
        if ($this->connection->is_active()) {
            $this->connection->record->lasterror = $error;
        }
        $this->connection->save();
        return $status === 'ok';
    }

    /**
     * Makes sure the academic calendar exists in the user's account (the user may have deleted it).
     *
     * @return void
     */
    private function ensure_calendar(): void {
        global $DB;
        $calendarid = (string) $this->connection->record->calendarid;
        if ($calendarid !== '' && $this->client->calendar_exists($calendarid)) {
            return;
        }
        $name = get_config('local_gcal', 'calendarname') ?: get_string('defaultcalendarname', 'local_gcal',
            format_string(get_site()->shortname));
        $this->connection->record->calendarid = $this->client->create_calendar($name, core_date::get_user_timezone($this->user));
        $this->connection->record->synctoken = null;
        $this->connection->save();
        // A new, empty calendar: every event must be created again.
        $DB->delete_records(self::MAP, ['userid' => $this->user->id]);
        logger::log($this->user->id, 'info', 'calendarcreated');
    }

    /**
     * Window of synchronised events.
     *
     * @return int[] [from, to]
     */
    public static function window(): array {
        $past = (int) (get_config('local_gcal', 'pastdays') ?: 7);
        $future = (int) (get_config('local_gcal', 'futuredays') ?: 180);
        return [time() - $past * DAYSECS, time() + $future * DAYSECS];
    }

    /**
     * Copies Moodle academic events to Google.
     *
     * @return void
     */
    private function push(): void {
        global $DB;
        [$from, $to] = self::window();
        $calendarid = $this->connection->record->calendarid;
        $timezone = core_date::get_user_timezone($this->user);
        $events = $this->source->get_events($from, $to);
        $maps = $DB->get_records(self::MAP, ['userid' => $this->user->id], '', '*');
        $mapsbyevent = [];
        foreach ($maps as $map) {
            $mapsbyevent[$map->eventid] = $map;
        }

        foreach ($events as $event) {
            $payload = event_source::google_event($event, $timezone);
            $hash = sha1(json_encode($payload));
            $map = $mapsbyevent[$event->id] ?? null;
            if ($map && $map->contenthash === $hash) {
                continue;
            }
            try {
                $google = null;
                if ($map) {
                    try {
                        $google = $this->client->patch_event($calendarid, $map->googleeventid, $payload);
                        $this->result['updated']++;
                    } catch (api_exception $e) {
                        if (!$e->is_not_found()) {
                            throw $e;
                        }
                    }
                }
                if (!$google) {
                    // No link (first sync or lost): reuse a copy already in Google instead of creating a duplicate.
                    $existing = $this->client->find_events($calendarid,
                        'lmsEventId=' . $event->id);
                    $existing = array_values(array_filter($existing,
                        fn($item) => ($item['extendedProperties']['private']['lmsSite'] ?? '') === event_source::site_key()));
                    if ($existing) {
                        $google = $this->client->patch_event($calendarid, $existing[0]['id'], $payload);
                        $this->result['updated']++;
                    } else {
                        $google = $this->client->insert_event($calendarid, $payload);
                        $this->result['created']++;
                    }
                }
                $this->save_map($map, $event, $google, $hash);
            } catch (api_exception $e) {
                if ($e->needs_reconnect()) {
                    throw $e;
                }
                $this->result['errors']++;
                logger::log($this->user->id, 'error', 'push', $e->getMessage(), $event->id);
            }
            unset($mapsbyevent[$event->id]);
        }

        // Links left: events deleted in Moodle, no longer visible to the user, or out of the window.
        foreach ($mapsbyevent as $map) {
            try {
                if ($map->timestart >= $from && $map->timestart <= $to) {
                    $this->client->delete_event($calendarid, $map->googleeventid);
                    $this->result['deleted']++;
                }
                // Past events are kept in Google as a history; only the link is removed.
                $DB->delete_records(self::MAP, ['id' => $map->id]);
            } catch (api_exception $e) {
                if ($e->needs_reconnect()) {
                    throw $e;
                }
                $this->result['errors']++;
                logger::log($this->user->id, 'error', 'delete', $e->getMessage(), $map->eventid);
            }
        }
    }

    /**
     * Stores the link between a Moodle event and its Google copy.
     *
     * @param stdClass|null $map
     * @param stdClass $event
     * @param array $google
     * @param string $hash
     * @return void
     */
    private function save_map(?stdClass $map, stdClass $event, array $google, string $hash): void {
        global $DB;
        $record = $map ?? (object) ['userid' => $this->user->id, 'eventid' => $event->id];
        $record->googleeventid = $google['id'];
        $record->htmllink = $google['htmlLink'] ?? null;
        $record->contenthash = $hash;
        $record->googleupdated = $google['updated'] ?? null;
        $record->timestart = $event->timestart;
        $record->timesynced = time();
        if (empty($record->id)) {
            $DB->insert_record(self::MAP, $record);
        } else {
            $DB->update_record(self::MAP, $record);
        }
    }

    /**
     * Applies the changes made in Google to the academic calendar (incremental, with Google sync tokens).
     *
     * @return void
     */
    private function pull(): void {
        global $DB;
        $calendarid = $this->connection->record->calendarid;
        [$items, $synctoken] = $this->client->list_changes($calendarid, $this->connection->record->synctoken ?: null);
        if ($items === null) {
            // The sync token expired: list everything again.
            [$items, $synctoken] = $this->client->list_changes($calendarid, null);
        }

        foreach ($items as $item) {
            $properties = $item['extendedProperties']['private'] ?? [];
            if (($properties['lmsSite'] ?? '') !== event_source::site_key() || empty($properties['lmsEventId'])) {
                continue;
            }
            $map = $DB->get_record(self::MAP, ['userid' => $this->user->id, 'eventid' => (int) $properties['lmsEventId']]);
            if (!$map || $map->googleeventid !== $item['id'] || ($item['updated'] ?? null) === $map->googleupdated) {
                // Unknown copy, or no change since our own last write.
                continue;
            }
            try {
                $this->apply_google_change($map, $item);
            } catch (api_exception $e) {
                if ($e->needs_reconnect()) {
                    throw $e;
                }
                $this->result['errors']++;
                logger::log($this->user->id, 'error', 'pull', $e->getMessage(), $map->eventid);
            } catch (\moodle_exception $e) {
                // For example no permission any more: the Google copy is restored from Moodle.
                $this->revert($map, 'Not applied: ' . $e->getMessage());
            }
        }

        $this->connection->record->synctoken = $synctoken;
        $this->connection->save();
    }

    /**
     * Applies one event changed in Google.
     *
     * @param stdClass $map
     * @param array $item Google event
     * @return void
     */
    private function apply_google_change(stdClass $map, array $item): void {
        global $DB;

        $record = $DB->get_record('event', ['id' => $map->eventid]);
        $event = $record ? $this->source->normalise($record) : null;
        if (!$event) {
            // Gone in Moodle or not visible any more: the next push removes the Google copy.
            $map->contenthash = '';
            $DB->update_record(self::MAP, $map);
            return;
        }
        $calendarevent = calendar_event::load($event->id);
        $cancelled = ($item['status'] ?? '') === 'cancelled';

        if (!event_source::is_two_way($event)
                || ($cancelled ? !calendar_delete_event_allowed($calendarevent) : !calendar_edit_event_allowed($calendarevent))) {
            $this->revert($map, $cancelled ? 'Deleted in Google, restored (one-way event)'
                : 'Changed in Google, restored (one-way event)');
            return;
        }
        if ($event->timemodified > $map->timesynced) {
            // Changed on both sides since the last sync: Moodle wins.
            $this->revert($map, 'Changed in Moodle and in Google: the Moodle version was kept');
            return;
        }

        if ($cancelled) {
            $calendarevent->delete();
            $DB->delete_records(self::MAP, ['id' => $map->id]);
            $this->result['fromgoogle']++;
            logger::log($this->user->id, 'info', 'deletedfromgoogle', '', $event->id);
            return;
        }

        $data = $this->moodle_changes($event, $item);
        if ($data) {
            $calendarevent->update((object) $data, true);
            $this->result['fromgoogle']++;
            logger::log($this->user->id, 'info', 'updatedfromgoogle', implode(', ', array_keys($data)), $event->id);
        }
        // When Moodle changed, the next push rewrites the Google copy in the LMS format (course prefix, links...).
        $map->contenthash = $data ? '' : $map->contenthash;
        $map->googleupdated = $item['updated'] ?? null;
        $map->timestart = $data['timestart'] ?? $event->timestart;
        $map->timesynced = time();
        $DB->update_record(self::MAP, $map);
    }

    /**
     * Fields of a Moodle event changed in Google.
     *
     * @param stdClass $event normalised Moodle event
     * @param array $item Google event
     * @return array changed fields
     */
    private function moodle_changes(stdClass $event, array $item): array {
        $data = [];

        $name = trim((string) ($item['summary'] ?? ''));
        $prefix = $event->courseshortname !== '' ? $event->courseshortname . ' · ' : '';
        if ($prefix !== '' && strpos($name, $prefix) === 0) {
            $name = trim(substr($name, strlen($prefix)));
        }
        if ($name !== '' && $name !== $event->name) {
            $data['name'] = $name;
        }

        [$start, $end] = self::google_times($item, core_date::get_user_timezone($this->user));
        if ($start !== null) {
            $duration = max(0, $end - $start);
            if ($event->timeduration == 0 && $duration == event_source::DEADLINE_LENGTH) {
                $duration = 0;
            }
            if ($start != $event->timestart) {
                $data['timestart'] = $start;
            }
            if ($duration != $event->timeduration) {
                $data['timeduration'] = $duration;
            }
        }

        $location = trim((string) ($item['location'] ?? ''));
        if ($location !== trim($event->location)) {
            $data['location'] = $location;
        }

        $description = self::user_description((string) ($item['description'] ?? ''));
        $current = trim(html_to_text(format_text($event->description, $event->descriptionformat), 0, false));
        if ($description !== $current) {
            $data['description'] = $description;
            $data['format'] = FORMAT_PLAIN;
        }
        return $data;
    }

    /**
     * The description written by the user, without the lines the LMS adds (course, deadline, link).
     *
     * @param string $description
     * @return string
     */
    private static function user_description(string $description): string {
        $site = format_string(get_site()->shortname);
        $added = [
            get_string('googlecourse', 'local_gcal', ''),
            get_string('googledeadline', 'local_gcal', ''),
            strtok(get_string('googleopen', 'local_gcal', (object) ['site' => $site, 'url' => '']), ':'),
            get_string('googlemanaged', 'local_gcal', $site),
        ];
        $paragraphs = [];
        foreach (preg_split("/\n\s*\n/", str_replace("\r\n", "\n", $description)) as $paragraph) {
            $paragraph = trim($paragraph);
            foreach ($added as $start) {
                if ($start !== '' && strpos($paragraph, trim($start)) === 0) {
                    continue 2;
                }
            }
            if ($paragraph !== '') {
                $paragraphs[] = $paragraph;
            }
        }
        return implode("\n\n", $paragraphs);
    }

    /**
     * Start and end of a Google event as timestamps (all-day events start at midnight in the user's time zone).
     *
     * @param array $item
     * @param string $timezone
     * @return array [start, end] (null when unknown)
     */
    public static function google_times(array $item, string $timezone): array {
        $times = [];
        foreach (['start', 'end'] as $key) {
            if (!empty($item[$key]['dateTime'])) {
                $times[] = (new \DateTimeImmutable($item[$key]['dateTime']))->getTimestamp();
            } else if (!empty($item[$key]['date'])) {
                $tz = new \DateTimeZone($item[$key]['timeZone'] ?? $timezone);
                $times[] = (new \DateTimeImmutable($item[$key]['date'] . ' 00:00:00', $tz))->getTimestamp();
            } else {
                $times[] = null;
            }
        }
        return $times;
    }

    /**
     * Restores the Google copy from Moodle at the next push.
     *
     * @param stdClass $map
     * @param string $reason
     * @return void
     */
    private function revert(stdClass $map, string $reason): void {
        global $DB;
        $map->contenthash = '';
        $DB->update_record(self::MAP, $map);
        $this->result['reverted']++;
        logger::log($this->user->id, 'warning', 'reverted', $reason, $map->eventid);
    }

    /**
     * The user's own Google events in a range (read live, kept a few minutes in their session cache only).
     *
     * @param connection $connection
     * @param int $from
     * @param int $to
     * @return array[]
     */
    public static function personal_events(connection $connection, int $from, int $to): array {
        $cache = \cache::make('local_gcal', 'personalevents');
        $key = "{$connection->record->userid}_{$from}_{$to}";
        if (($cached = $cache->get($key)) !== false) {
            return $cached;
        }
        $user = \core_user::get_user($connection->record->userid);
        $timezone = core_date::get_user_timezone($user);
        $events = [];
        foreach ((new client($connection))->list_personal_events($from, $to) as $item) {
            if (($item['status'] ?? '') === 'cancelled') {
                continue;
            }
            [$start, $end] = self::google_times($item, $timezone);
            if ($start === null) {
                continue;
            }
            $events[] = [
                'id' => 'g-' . $item['id'],
                'title' => (string) ($item['summary'] ?? get_string('busy', 'local_gcal')),
                'start' => $start,
                'end' => $end ?? $start,
                'allday' => empty($item['start']['dateTime']),
                'location' => (string) ($item['location'] ?? ''),
                'description' => shorten_text(trim((string) ($item['description'] ?? '')), 300),
                'googleurl' => (string) ($item['htmlLink'] ?? ''),
            ];
        }
        $cache->set($key, $events);
        return $events;
    }
}

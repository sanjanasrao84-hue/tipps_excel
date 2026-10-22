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

namespace local_gcal\google;

use local_gcal\connection;

/**
 * Google Calendar API v3 client for one connected user: refreshes the access token when needed,
 * retries rate-limited and failed requests with exponential back-off, and reports revoked access.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {

    /** @var string API base URL. */
    public const BASE = 'https://www.googleapis.com/calendar/v3';

    /** @var int Retries of a rate-limited or failed request. */
    private const MAX_RETRIES = 3;

    /** @var int Longest wait between retries, in seconds. */
    private const MAX_WAIT = 16;

    /** @var string[] 403 reasons that mean "slow down" rather than "forbidden". */
    private const RATE_LIMIT_REASONS = ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded'];

    /** @var connection */
    private connection $connection;

    /** @var callable Waits a number of seconds (replaced in tests). */
    private $sleep;

    /** @var int Requests sent (for logs and tests). */
    public int $requests = 0;

    /**
     * Constructor.
     *
     * @param connection $connection
     * @param callable|null $sleep
     */
    public function __construct(connection $connection, ?callable $sleep = null) {
        $this->connection = $connection;
        $this->sleep = $sleep ?? function(int $seconds): void {
            sleep($seconds);
        };
    }

    /**
     * Sends an API request.
     *
     * @param string $method
     * @param string $path Path under the API base, or a full URL
     * @param array $query Query parameters
     * @param array|null $body JSON body
     * @return array|null Decoded response (null for an empty response)
     */
    public function call(string $method, string $path, array $query = [], ?array $body = null): ?array {
        $url = (strpos($path, 'https://') === 0 ? $path : self::BASE . $path);
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&');
        }
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $refreshed = false;
        for ($attempt = 0;; $attempt++) {
            $headers = ['Authorization: Bearer ' . $this->access_token(), 'Accept: application/json'];
            if ($payload !== null) {
                $headers[] = 'Content-Type: application/json';
            }
            $this->requests++;
            [$status, $responseheaders, $responsebody] = oauth::transport()->request($method, $url, $headers, $payload);

            if ($status >= 200 && $status < 300) {
                return $responsebody === '' ? null : json_decode($responsebody, true);
            }
            $error = api_exception::from_response($status, $responsebody);

            // Expired access token: refresh once and try again.
            if ($status === 401 && !$refreshed) {
                $refreshed = true;
                $this->connection->record->tokenexpires = 0;
                continue;
            }

            $retryable = $status === 0 || $status === 429 || $status >= 500
                || ($status === 403 && in_array($error->reason, self::RATE_LIMIT_REASONS));
            if (!$retryable || $attempt >= self::MAX_RETRIES) {
                throw $error;
            }
            $wait = isset($responseheaders['retry-after']) ? (int) $responseheaders['retry-after'] : 2 ** $attempt;
            ($this->sleep)(min(max($wait, 1), self::MAX_WAIT));
        }
    }

    /**
     * A valid access token, refreshed with the refresh token when needed.
     *
     * @return string
     */
    private function access_token(): string {
        $token = $this->connection->get_valid_access_token();
        if ($token !== null) {
            return $token;
        }
        $refreshtoken = $this->connection->get_refresh_token();
        if (!$refreshtoken) {
            throw new api_exception(401, 'invalid_grant', 'No refresh token');
        }
        $tokens = oauth::refresh($refreshtoken);
        $this->connection->set_tokens($tokens);
        $this->connection->save();
        return $tokens['access_token'];
    }

    // Calendars.

    /**
     * Creates the academic calendar in the user's account.
     *
     * @param string $name
     * @param string $timezone
     * @return string calendar id
     */
    public function create_calendar(string $name, string $timezone): string {
        $calendar = $this->call('POST', '/calendars', [], [
            'summary' => $name,
            'description' => get_string('calendardescription', 'local_gcal', format_string(get_site()->fullname)),
            'timeZone' => $timezone,
        ]);
        return $calendar['id'];
    }

    /**
     * Whether a calendar still exists (the user may have deleted it in Google Calendar).
     *
     * @param string $calendarid
     * @return bool
     */
    public function calendar_exists(string $calendarid): bool {
        try {
            $this->call('GET', '/calendars/' . rawurlencode($calendarid));
            return true;
        } catch (api_exception $e) {
            if ($e->is_not_found()) {
                return false;
            }
            throw $e;
        }
    }

    // Events.

    /**
     * Inserts an event.
     *
     * @param string $calendarid
     * @param array $event
     * @return array created event
     */
    public function insert_event(string $calendarid, array $event): array {
        return $this->call('POST', '/calendars/' . rawurlencode($calendarid) . '/events', [], $event);
    }

    /**
     * Updates an event (only the given fields).
     *
     * @param string $calendarid
     * @param string $eventid
     * @param array $event
     * @return array updated event
     */
    public function patch_event(string $calendarid, string $eventid, array $event): array {
        return $this->call('PATCH', '/calendars/' . rawurlencode($calendarid) . '/events/' . rawurlencode($eventid), [],
            $event);
    }

    /**
     * Deletes an event; an event already gone is not an error.
     *
     * @param string $calendarid
     * @param string $eventid
     * @return void
     */
    public function delete_event(string $calendarid, string $eventid): void {
        try {
            $this->call('DELETE', '/calendars/' . rawurlencode($calendarid) . '/events/' . rawurlencode($eventid));
        } catch (api_exception $e) {
            if (!$e->is_not_found()) {
                throw $e;
            }
        }
    }

    /**
     * Events of a calendar having a private extended property (used to find an LMS event without a stored link).
     *
     * @param string $calendarid
     * @param string $property "name=value"
     * @return array[]
     */
    public function find_events(string $calendarid, string $property): array {
        $result = $this->call('GET', '/calendars/' . rawurlencode($calendarid) . '/events', [
            'privateExtendedProperty' => $property, 'showDeleted' => 'false', 'maxResults' => 10,
        ]);
        return $result['items'] ?? [];
    }

    /**
     * Changes made to a calendar since the last sync (incremental sync with a sync token).
     *
     * @param string $calendarid
     * @param string|null $synctoken Null for a full listing
     * @return array [array[] $items, string|null $nextsynctoken]; items null when the token expired (full sync needed)
     */
    public function list_changes(string $calendarid, ?string $synctoken): array {
        $items = [];
        $pagetoken = null;
        do {
            $query = ['showDeleted' => 'true', 'maxResults' => 250];
            if ($synctoken) {
                $query['syncToken'] = $synctoken;
            }
            if ($pagetoken) {
                $query['pageToken'] = $pagetoken;
            }
            try {
                $result = $this->call('GET', '/calendars/' . rawurlencode($calendarid) . '/events', $query);
            } catch (api_exception $e) {
                if ($e->status === 410 && $synctoken) {
                    return [null, null];
                }
                throw $e;
            }
            $items = array_merge($items, $result['items'] ?? []);
            $pagetoken = $result['nextPageToken'] ?? null;
        } while ($pagetoken);

        return [$items, $result['nextSyncToken'] ?? null];
    }

    /**
     * The user's own events (primary calendar) in a time range, recurring events expanded.
     *
     * @param int $from
     * @param int $to
     * @return array[]
     */
    public function list_personal_events(int $from, int $to): array {
        $items = [];
        $pagetoken = null;
        $pages = 0;
        do {
            $query = [
                'timeMin' => gmdate('c', $from), 'timeMax' => gmdate('c', $to),
                'singleEvents' => 'true', 'orderBy' => 'startTime', 'maxResults' => 250,
            ];
            if ($pagetoken) {
                $query['pageToken'] = $pagetoken;
            }
            $result = $this->call('GET', '/calendars/primary/events', $query);
            $items = array_merge($items, $result['items'] ?? []);
            $pagetoken = $result['nextPageToken'] ?? null;
        } while ($pagetoken && ++$pages < 8);

        return $items;
    }
}

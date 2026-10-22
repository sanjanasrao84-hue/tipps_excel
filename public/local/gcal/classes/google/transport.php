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

/**
 * HTTP transport used for every request to Google (OAuth and Calendar API).
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface transport {

    /**
     * Sends a request.
     *
     * @param string $method GET, POST, PATCH or DELETE
     * @param string $url Full URL, including the query string
     * @param array $headers Header lines ("Name: value")
     * @param string|null $body Request body
     * @return array [int $status, array $headers (lower-case name => value), string $body]
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array;
}

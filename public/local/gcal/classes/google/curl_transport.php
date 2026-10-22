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
 * Transport using Moodle's curl class (site proxy and HTTP security settings apply).
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class curl_transport implements transport {

    /** @var int Request timeout in seconds. */
    private const TIMEOUT = 30;

    /**
     * Sends a request.
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string|null $body
     * @return array [status, headers, body]
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['ignoresecurity' => false]);
        $curl->setHeader($headers);
        $options = ['CURLOPT_TIMEOUT' => self::TIMEOUT, 'CURLOPT_CONNECTTIMEOUT' => 10];

        switch ($method) {
            case 'GET':
                $response = $curl->get($url, [], $options);
                break;
            case 'POST':
                $response = $curl->post($url, $body ?? '', $options);
                break;
            case 'PATCH':
                $response = $curl->patch($url, $body ?? '', $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, [], $options);
                break;
            default:
                throw new \coding_exception("Unsupported HTTP method {$method}");
        }

        if ($curl->get_errno()) {
            // Network failure: reported as a retryable server error.
            return [0, [], (string) $curl->error];
        }
        $info = $curl->get_info();
        $responseheaders = [];
        foreach ($curl->get_raw_response() as $line) {
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $responseheaders[strtolower(trim($name))] = trim($value);
            }
        }
        return [(int) ($info['http_code'] ?? 0), $responseheaders, (string) $response];
    }
}

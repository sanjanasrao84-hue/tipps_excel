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
 * Error returned by Google.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_exception extends \moodle_exception {

    /** @var int HTTP status (0 for a network error). */
    public readonly int $status;

    /** @var string Google error reason, e.g. notFound, rateLimitExceeded, invalid_grant. */
    public readonly string $reason;

    /**
     * Constructor.
     *
     * @param int $status
     * @param string $reason
     * @param string $message
     */
    public function __construct(int $status, string $reason, string $message) {
        $this->status = $status;
        $this->reason = $reason;
        parent::__construct('googleerror', 'local_gcal', '', (object) [
            'status' => $status, 'reason' => $reason, 'message' => shorten_text($message, 300),
        ]);
    }

    /**
     * Whether the user must connect their Google account again (token revoked or expired).
     *
     * @return bool
     */
    public function needs_reconnect(): bool {
        return $this->reason === 'invalid_grant' || $this->reason === 'unauthorized_client'
            || ($this->status === 401 && $this->reason !== 'retry');
    }

    /**
     * Whether the resource no longer exists (deleted event or calendar).
     *
     * @return bool
     */
    public function is_not_found(): bool {
        return $this->status === 404 || $this->status === 410;
    }

    /**
     * Builds the exception from a Google error response.
     *
     * @param int $status
     * @param string $body
     * @return self
     */
    public static function from_response(int $status, string $body): self {
        $data = json_decode($body, true);
        $reason = '';
        $message = $body;
        if (is_array($data)) {
            if (isset($data['error']) && is_string($data['error'])) {
                // OAuth errors: {"error": "invalid_grant", "error_description": "..."}.
                $reason = $data['error'];
                $message = $data['error_description'] ?? $reason;
            } else if (isset($data['error']['message'])) {
                // API errors: {"error": {"code": 404, "message": "...", "errors": [{"reason": "notFound"}]}}.
                $reason = $data['error']['errors'][0]['reason'] ?? ($data['error']['status'] ?? '');
                $message = $data['error']['message'];
            }
        }
        return new self($status, (string) $reason, (string) $message);
    }
}

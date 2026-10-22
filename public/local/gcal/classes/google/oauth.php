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

use core\oauth2\issuer;
use moodle_url;

/**
 * Google OAuth 2.0 for users connecting their calendar. The client id and secret come from a Moodle
 * OAuth 2 service (Site administration > Server > OAuth 2 services), never from code or the browser.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class oauth {

    /** @var string Create calendars and manage the events of the calendars created by the LMS only. */
    public const SCOPE_CALENDAR = 'https://www.googleapis.com/auth/calendar.app.created';

    /** @var string Read the user's own events, to show them in the LMS calendar (optional). */
    public const SCOPE_PERSONAL = 'https://www.googleapis.com/auth/calendar.events.readonly';

    /** @var string Google token revocation endpoint. */
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /** @var transport|null Transport override (tests). */
    private static ?transport $transport = null;

    /**
     * Sets the transport used for every Google request (tests use a fake Google).
     *
     * @param transport|null $transport
     * @return void
     */
    public static function set_transport(?transport $transport): void {
        self::$transport = $transport;
    }

    /**
     * Transport for Google requests.
     *
     * @return transport
     */
    public static function transport(): transport {
        return self::$transport ?? new curl_transport();
    }

    /**
     * The configured OAuth 2 service, if usable.
     *
     * @return issuer|null
     */
    public static function get_issuer(): ?issuer {
        $issuerid = (int) get_config('local_gcal', 'issuerid');
        if (!$issuerid) {
            return null;
        }
        $issuer = issuer::get_record(['id' => $issuerid]);
        if (!$issuer || !$issuer->get('enabled') || !$issuer->is_configured()) {
            return null;
        }
        return $issuer;
    }

    /**
     * Whether users can connect.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return self::get_issuer() !== null;
    }

    /**
     * Scopes requested from the user: the minimum needed.
     *
     * @return string[]
     */
    public static function requested_scopes(): array {
        $scopes = ['openid', 'email', self::SCOPE_CALENDAR];
        if (get_config('local_gcal', 'showpersonal')) {
            $scopes[] = self::SCOPE_PERSONAL;
        }
        return $scopes;
    }

    /**
     * Redirect URI registered in Google Cloud Console: Moodle's standard OAuth 2 callback.
     *
     * @return string
     */
    public static function redirect_uri(): string {
        return (new moodle_url('/admin/oauth2callback.php'))->out(false);
    }

    /**
     * URL of the Google consent screen.
     *
     * @param moodle_url $returnurl Local page receiving the code (must include the sesskey)
     * @param string $loginhint Email to preselect
     * @return moodle_url
     */
    public static function authorization_url(moodle_url $returnurl, string $loginhint = ''): moodle_url {
        $issuer = self::get_issuer();
        $params = [
            'client_id' => $issuer->get('clientid'),
            'response_type' => 'code',
            'redirect_uri' => self::redirect_uri(),
            'scope' => implode(' ', self::requested_scopes()),
            // A refresh token lets synchronisation continue in the background.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $returnurl->out_as_local_url(false),
        ];
        if ($loginhint !== '') {
            $params['login_hint'] = $loginhint;
        }
        return new moodle_url($issuer->get_endpoint_url('authorization'), $params);
    }

    /**
     * Exchanges an authorisation code for tokens.
     *
     * @param string $code
     * @return array token response
     */
    public static function exchange_code(string $code): array {
        return self::token_request([
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => self::redirect_uri(),
        ]);
    }

    /**
     * Gets a new access token.
     *
     * @param string $refreshtoken
     * @return array token response
     */
    public static function refresh(string $refreshtoken): array {
        return self::token_request(['refresh_token' => $refreshtoken, 'grant_type' => 'refresh_token']);
    }

    /**
     * Email address of the Google account.
     *
     * @param string $accesstoken
     * @return string
     */
    public static function get_email(string $accesstoken): string {
        $url = self::get_issuer()->get_endpoint_url('userinfo');
        [$status, , $body] = self::transport()->request('GET', $url, ['Authorization: Bearer ' . $accesstoken]);
        if ($status !== 200) {
            throw api_exception::from_response($status, $body);
        }
        return clean_param(json_decode($body, true)['email'] ?? '', PARAM_EMAIL);
    }

    /**
     * Revokes the user's grant at Google (best effort: the connection is removed locally anyway).
     *
     * @param string $token Refresh or access token
     * @return bool
     */
    public static function revoke(string $token): bool {
        [$status] = self::transport()->request('POST', self::REVOKE_URL,
            ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['token' => $token], '', '&'));
        return $status === 200;
    }

    /**
     * Calls the token endpoint with the client credentials.
     *
     * @param array $params
     * @return array
     */
    private static function token_request(array $params): array {
        $issuer = self::get_issuer();
        if (!$issuer) {
            throw new \moodle_exception('notconfigured', 'local_gcal');
        }
        $params += ['client_id' => $issuer->get('clientid'), 'client_secret' => $issuer->get('clientsecret')];
        [$status, , $body] = self::transport()->request('POST', $issuer->get_endpoint_url('token'),
            ['Content-Type: application/x-www-form-urlencoded'], http_build_query($params, '', '&'));
        $data = json_decode($body, true);
        if ($status !== 200 || empty($data['access_token'])) {
            throw api_exception::from_response($status, $body);
        }
        return $data;
    }
}

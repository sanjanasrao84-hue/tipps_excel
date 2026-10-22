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

namespace theme_eadtraining\output;

use core_date;
use moodle_url;
use renderer_base;

/**
 * Compact profile popup opened from the avatar in the navbar: the current user's details and logout.
 *
 * Built from the core user menu data, so it keeps Moodle's own logout link (with sesskey) and the
 * "return to my normal role" / "logged in as" information. The other user menu entries stay
 * available on their own pages.
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_profile_popup implements \renderable, \templatable {

    /** @var array Core user menu data (core\navigation\output\primary user menu). */
    protected $usermenu;

    /**
     * Constructor.
     *
     * @param array $usermenu Core user menu data.
     */
    public function __construct(array $usermenu) {
        $this->usermenu = $usermenu;
    }

    /**
     * Template data.
     *
     * @param renderer_base $output
     * @return array|null Null when there is no logged-in user.
     */
    public function export_for_template(renderer_base $output): ?array {
        global $USER;

        if (!isloggedin() || isguestuser() || !empty($this->usermenu['unauthenticateduser'])) {
            return null;
        }

        $details = [];
        foreach (['institution', 'department'] as $field) {
            if (trim((string) ($USER->$field ?? '')) !== '') {
                $details[] = ['label' => get_string($field), 'value' => s($USER->$field)];
            }
        }
        $location = [];
        if (trim((string) ($USER->city ?? '')) !== '') {
            $location[] = s($USER->city);
        }
        if (!empty($USER->country)) {
            $countries = get_string_manager()->get_list_of_countries();
            $location[] = $countries[$USER->country] ?? s($USER->country);
        }
        if ($location) {
            $details[] = ['label' => get_string('location'), 'value' => implode(', ', $location)];
        }
        $details[] = ['label' => get_string('timezone'), 'value' => core_date::get_localised_timezone(core_date::get_user_timezone($USER))];
        if (!empty($USER->lastlogin)) {
            $details[] = ['label' => get_string('profile_popup_lastlogin', 'theme_eadtraining'),
                'value' => userdate($USER->lastlogin, get_string('strftimedatetime', 'langconfig'))];
        }

        // Logout and "return to my normal role" come from the core menu, with their sesskey.
        $logouturl = new moodle_url('/login/logout.php', ['sesskey' => sesskey()]);
        $returnrole = null;
        foreach ($this->usermenu['items'] ?? [] as $item) {
            $item = (array) $item;
            if (empty($item['url'])) {
                continue;
            }
            $url = $item['url'] instanceof moodle_url ? $item['url'] : new moodle_url($item['url']);
            if (($item['titleidentifier'] ?? '') === 'logout,moodle') {
                $logouturl = $url;
            } else if (($item['titleidentifier'] ?? '') === 'switchrolereturn,moodle') {
                $returnrole = ['url' => $url->out(false), 'title' => $item['title']];
            }
        }

        // Role switched / logged in as: the core metadata lines.
        $status = [];
        foreach ($this->usermenu['metadata'] ?? [] as $metadata) {
            $metadata = (array) $metadata;
            if (!empty($metadata['content'])) {
                $status[] = ['content' => $metadata['content']];
            }
        }

        return [
            'picture' => $output->user_picture($USER, ['size' => 100, 'link' => false, 'alttext' => false,
                'class' => 'eadtraining-profile-popup-picture']),
            'fullname' => fullname($USER),
            'username' => s($USER->username),
            'email' => s($USER->email),
            'details' => $details,
            'hasstatus' => (bool) $status,
            'status' => $status,
            'returnrole' => $returnrole,
            'profileurl' => (new moodle_url('/user/profile.php'))->out(false),
            'logouturl' => $logouturl->out(false),
        ];
    }
}

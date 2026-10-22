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
 * Settings.
 *
 * @package    local_gcal
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_gcal', get_string('pluginname', 'local_gcal'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $issuers = [0 => get_string('none')];
        foreach (\core\oauth2\api::get_all_issuers() as $issuer) {
            $issuers[$issuer->get('id')] = s($issuer->get('name'))
                . ($issuer->get('servicetype') === 'google' ? '' : ' (' . get_string('notgoogle', 'local_gcal') . ')');
        }
        $settings->add(new admin_setting_heading('local_gcal/setup', '',
            get_string('setupinstructions', 'local_gcal', (object) [
                'oauthurl' => (new moodle_url('/admin/tool/oauth2/issuers.php'))->out(false),
                'redirecturi' => \local_gcal\google\oauth::redirect_uri(),
            ])));
        $settings->add(new admin_setting_configselect('local_gcal/issuerid', get_string('issuer', 'local_gcal'),
            get_string('issuer_desc', 'local_gcal'), 0, $issuers));
        $settings->add(new admin_setting_configtext('local_gcal/calendarname', get_string('calendarname', 'local_gcal'),
            get_string('calendarname_desc', 'local_gcal'), '', PARAM_TEXT));
        $settings->add(new admin_setting_configcheckbox('local_gcal/showpersonal', get_string('showpersonal', 'local_gcal'),
            get_string('showpersonal_desc', 'local_gcal'), 1));
        $settings->add(new admin_setting_configcheckbox('local_gcal/primarycalendar',
            get_string('primarycalendar', 'local_gcal'), get_string('primarycalendar_desc', 'local_gcal'), 1));
        $settings->add(new admin_setting_configtext('local_gcal/pastdays', get_string('pastdays', 'local_gcal'),
            get_string('pastdays_desc', 'local_gcal'), 7, PARAM_INT));
        $settings->add(new admin_setting_configtext('local_gcal/futuredays', get_string('futuredays', 'local_gcal'),
            get_string('futuredays_desc', 'local_gcal'), 180, PARAM_INT));
    }
}

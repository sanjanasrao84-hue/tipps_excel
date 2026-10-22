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
 * Site administration > Subscriptions.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('root', new admin_category('local_lmssub', new lang_string('subscriptions', 'local_lmssub')));

$settings = new admin_settingpage('local_lmssub_settings', new lang_string('settings', 'local_lmssub'));
if ($ADMIN->fulltree) {
    // Each client site switches on Type 1, Type 2 or both; the same code serves every client.
    $settings->add(new admin_setting_configcheckbox('local_lmssub/enablecoursesubs',
        new lang_string('enablecoursesubs', 'local_lmssub'),
        new lang_string('enablecoursesubs_desc', 'local_lmssub'), 1));
    $settings->add(new admin_setting_configcheckbox('local_lmssub/enablefullsubs',
        new lang_string('enablefullsubs', 'local_lmssub'),
        new lang_string('enablefullsubs_desc', 'local_lmssub'), 1));

    $accounts = [0 => new lang_string('paymentaccount_auto', 'local_lmssub')];
    foreach ($DB->get_records('payment_accounts', ['archived' => 0], 'name', 'id, name') as $account) {
        $accounts[$account->id] = format_string($account->name);
    }
    $settings->add(new admin_setting_configselect('local_lmssub/paymentaccount',
        new lang_string('paymentaccount', 'local_lmssub'),
        new lang_string('paymentaccount_desc', 'local_lmssub'), 0, $accounts));
}
$ADMIN->add('local_lmssub', $settings);

$ADMIN->add('local_lmssub', new admin_externalpage('local_lmssub_plans',
    new lang_string('plans', 'local_lmssub'),
    new moodle_url('/local/lmssub/plans.php'), 'local/lmssub:manageplans'));
$ADMIN->add('local_lmssub', new admin_externalpage('local_lmssub_subscribers',
    new lang_string('subscribers', 'local_lmssub'),
    new moodle_url('/local/lmssub/subscribers.php'), 'local/lmssub:managesubscribers'));
$ADMIN->add('local_lmssub', new admin_externalpage('local_lmssub_attempts',
    new lang_string('paymentattempts', 'local_lmssub'),
    new moodle_url('/local/lmssub/attempts.php'), 'local/lmssub:managesubscribers'));
$ADMIN->add('local_lmssub', new admin_externalpage('local_lmssub_checkaccess',
    new lang_string('checkaccess', 'local_lmssub'),
    new moodle_url('/local/lmssub/checkaccess.php'), 'local/lmssub:managesubscribers'));
$ADMIN->add('local_lmssub', new admin_externalpage('local_lmssub_overrides',
    new lang_string('overrides', 'local_lmssub'),
    new moodle_url('/local/lmssub/overrides.php'), 'local/lmssub:managesubscribers'));

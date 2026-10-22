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
 * Grant a subscription without payment. If the user already has one on the plan, it is extended.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\form\grant_form;
use local_lmssub\subscription_manager;

admin_externalpage_setup('local_lmssub_subscribers', '', null, new moodle_url('/local/lmssub/grant.php'));
$returnurl = new moodle_url('/local/lmssub/subscribers.php');

if (!$DB->record_exists('local_lmssub_plan', [])) {
    redirect(new moodle_url('/local/lmssub/plans.php'), get_string('noplans', 'local_lmssub'), null,
        \core\output\notification::NOTIFY_WARNING);
}

$form = new grant_form();
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $user = core_user::get_user($data->userid, '*', MUST_EXIST);
    $sub = subscription_manager::grant((int) $user->id, (int) $data->planid, grant_form::get_days($data), $data->reason);
    redirect(new moodle_url('/local/lmssub/subaction.php', ['id' => $sub->id, 'action' => 'view']),
        get_string('granted', 'local_lmssub', fullname($user)), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->navbar->add(get_string('grantsubscription', 'local_lmssub'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('grantsubscription', 'local_lmssub'));
echo html_writer::tag('p', get_string('grantsubscription_desc', 'local_lmssub'));
$form->display();
echo $OUTPUT->footer();

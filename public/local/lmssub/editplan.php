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
 * Add or edit a plan.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\plan_manager;

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_lmssub_plans', '', null, new moodle_url('/local/lmssub/editplan.php', ['id' => $id]));
$returnurl = new moodle_url('/local/lmssub/plans.php');

$plan = $id ? plan_manager::get_plan($id) : null;
$pricecount = $plan ? max(4, count(plan_manager::get_prices($id))) : 4;
$form = new \local_lmssub\form\plan_form(null, ['editing' => (bool) $plan, 'pricecount' => $pricecount]);

if ($plan) {
    $form->set_plan($plan);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    $planid = plan_manager::save_plan($data);
    redirect(new moodle_url('/local/lmssub/preview.php', ['id' => $planid]), get_string('plansaved', 'local_lmssub'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$title = $plan ? get_string('editplan', 'local_lmssub') : get_string('addplan', 'local_lmssub');
$PAGE->navbar->add($title);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();

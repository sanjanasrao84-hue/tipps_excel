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
 * Turns the courses a learner picked in the bundle card into a bundle plan, then opens its checkout.
 *
 * The price is worked out here from the courses' own prices, never taken from the browser.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_lmssub\plan_manager;

$courseids = optional_param_array('courses', [], PARAM_INT);
$days = required_param('days', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login(null, false);
if (isguestuser()) {
    redirect(get_login_url());
}
require_sesskey();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/lmssub/bundle.php'));
$plansurl = new moodle_url('/local/lmssub/index.php', $courseid ? ['courseid' => $courseid] : []);

if (!isset(plan_manager::get_enabled_types()[plan_manager::TYPE_BUNDLE])) {
    redirect($plansurl, get_string('plannotavailable', 'local_lmssub'), null, \core\output\notification::NOTIFY_ERROR);
}

try {
    $planid = plan_manager::get_learner_bundle($courseids);
} catch (moodle_exception $e) {
    redirect($plansurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}

$price = plan_manager::prices_by_duration(plan_manager::get_prices($planid))[$days] ?? null;
if (!$price) {
    redirect($plansurl, get_string('error_bundlenoduration', 'local_lmssub'), null, \core\output\notification::NOTIFY_ERROR);
}
redirect(new moodle_url('/local/lmssub/checkout.php', ['priceid' => $price->id] + ($courseid ? ['courseid' => $courseid] : [])));

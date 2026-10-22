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
 * Site administration > Subscriptions > Plans.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\plan_manager;
use local_lmssub\subscription_manager;

admin_externalpage_setup('local_lmssub_plans');

$toggle = optional_param('toggle', 0, PARAM_INT);
if ($toggle && confirm_sesskey()) {
    $plan = plan_manager::get_plan($toggle);
    $DB->update_record('local_lmssub_plan', (object) ['id' => $plan->id, 'active' => $plan->active ? 0 : 1,
        'timemodified' => time(), 'usermodified' => $USER->id]);
    redirect($PAGE->url);
}

// Loading core/utility registers the confirmation dialog for links with data-modal="confirmation".
$PAGE->requires->js_amd_inline("require(['core/utility']);");
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('plans', 'local_lmssub'));

$enabled = plan_manager::get_enabled_types();
if (!$enabled) {
    echo $OUTPUT->notification(get_string('notypesenabled', 'local_lmssub'), 'warning');
} else {
    echo html_writer::div($OUTPUT->single_button(new moodle_url('/local/lmssub/editplan.php'),
        get_string('addplan', 'local_lmssub'), 'get', ['type' => single_button::BUTTON_PRIMARY]), 'd-flex justify-content-end mb-3');
}

$plans = $DB->get_records('local_lmssub_plan', null, 'sortorder, id');
if (!$plans) {
    echo $OUTPUT->notification(get_string('noplans', 'local_lmssub'), 'info');
    echo $OUTPUT->footer();
    die;
}

[$statusin, $params] = $DB->get_in_or_equal(subscription_manager::LIVE_STATUSES, SQL_PARAMS_NAMED);
$subscribers = $DB->get_records_sql_menu("SELECT planid, COUNT(1)
                                            FROM {local_lmssub_sub}
                                           WHERE status $statusin
                                        GROUP BY planid", $params);
$types = plan_manager::get_types();

$table = new html_table();
$table->head = [
    get_string('planname', 'local_lmssub'),
    get_string('plantype', 'local_lmssub'),
    get_string('prices', 'local_lmssub'),
    get_string('coursescovered', 'local_lmssub'),
    get_string('activesubscribers', 'local_lmssub'),
    get_string('status'),
    get_string('actions'),
];
foreach ($plans as $plan) {
    $prices = [];
    foreach (plan_manager::get_prices((int) $plan->id) as $price) {
        $prices[] = plan_manager::duration_label((int) $price->durationdays) . ': ' .
            plan_manager::format_amount((int) $price->amount, $price->currency);
    }
    $actions = [
        html_writer::link(new moodle_url('/local/lmssub/editplan.php', ['id' => $plan->id]), get_string('edit')),
        html_writer::link(new moodle_url('/local/lmssub/preview.php', ['id' => $plan->id]),
            get_string('previewcourses', 'local_lmssub')),
        html_writer::link(new moodle_url('/local/lmssub/subscribers.php', ['planid' => $plan->id]),
            get_string('subscribers', 'local_lmssub')),
        html_writer::link(new moodle_url($PAGE->url, ['toggle' => $plan->id, 'sesskey' => sesskey()]),
            $plan->active ? get_string('deactivate', 'local_lmssub') : get_string('activate', 'local_lmssub'),
            // Deactivating stops sales, so ask first (core/utility shows the dialog).
            $plan->active ? [
                'data-modal' => 'confirmation',
                'data-modal-type' => 'delete',
                'data-modal-title' => get_string('deactivateplan', 'local_lmssub'),
                'data-modal-content' => get_string('deactivateplan_confirm', 'local_lmssub', format_string($plan->name)),
                'data-modal-yes-button' => get_string('deactivate', 'local_lmssub'),
            ] : []),
    ];
    $table->data[] = [
        format_string($plan->name),
        $types[$plan->type] ?? s($plan->type),
        implode(html_writer::empty_tag('br'), $prices),
        count(plan_manager::courses_for_plan((int) $plan->id)),
        (int) ($subscribers[$plan->id] ?? 0),
        $plan->active ? get_string('active') : get_string('inactive'),
        implode(' | ', $actions),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();

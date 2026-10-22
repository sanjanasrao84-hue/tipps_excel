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
 * Checkout: summary of the selected plan and duration, then the Razorpay payment.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_lmssub\access_engine;
use local_lmssub\payment\service_provider;
use local_lmssub\plan_manager;
use local_lmssub\subscription_manager as subs;

$priceid = required_param('priceid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login(null, false);
if (isguestuser()) {
    redirect(get_login_url());
}

// When the learner came from a course's plans, links back go to that course's plans only.
if ($courseid && !$DB->record_exists('course', ['id' => $courseid])) {
    $courseid = 0;
}
$url = new moodle_url('/local/lmssub/checkout.php', ['priceid' => $priceid] + ($courseid ? ['courseid' => $courseid] : []));
$plansurl = new moodle_url('/local/lmssub/index.php', $courseid ? ['courseid' => $courseid] : []);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('checkout', 'local_lmssub'));
$PAGE->set_heading(get_string('checkout', 'local_lmssub'));
$PAGE->navbar->add(get_string('subscriptionplans', 'local_lmssub'), $plansurl);
$PAGE->navbar->add(get_string('checkout', 'local_lmssub'));

try {
    [$price, $plan] = service_provider::get_purchasable($priceid);
} catch (moodle_exception $e) {
    redirect($plansurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
}

$days = (int) $price->durationdays;
$now = time();
$current = subs::find_current((int) $USER->id, (int) $plan->id);
$renewing = $current && in_array($current->status, [subs::STATUS_ACTIVE, subs::STATUS_CANCELLED], true)
    && $current->timeend > $now;
// Same rule as subscription_manager::extend(): a renewal is added on top of the current end date.
$newend = ($renewing ? (int) $current->timeend : $now) + $days * DAYSECS;
$cost = plan_manager::format_amount((int) $price->amount, $price->currency);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('ordersummary', 'local_lmssub'));

$fmt = get_string('strftimedate');
$rows = [
    get_string('plan', 'local_lmssub') => format_string($plan->name),
    get_string('plantype', 'local_lmssub') => plan_manager::get_types()[$plan->type],
    get_string('duration', 'local_lmssub') => plan_manager::duration_label($days) .
        ' ' . html_writer::link($plansurl, get_string('changeduration', 'local_lmssub'), ['class' => 'small ms-2']),
    get_string('accessuntil', 'local_lmssub') => userdate($newend, $fmt),
];
$dl = '';
foreach ($rows as $label => $value) {
    $dl .= html_writer::tag('dt', $label, ['class' => 'col-sm-4']) . html_writer::tag('dd', $value, ['class' => 'col-sm-8']);
}
$dl .= html_writer::tag('dt', get_string('total', 'local_lmssub'), ['class' => 'col-sm-4 fs-5']) .
    html_writer::tag('dd', $cost, ['class' => 'col-sm-8 fs-5 fw-bold']);
$summary = html_writer::tag('dl', $dl, ['class' => 'row mb-0']);

if ($renewing) {
    $summary .= $OUTPUT->notification(get_string('renewnote', 'local_lmssub', userdate($current->timeend, $fmt)), 'info', false);
}

// Courses the plan opens.
$courseids = array_values(plan_manager::courses_for_plan((int) $plan->id));
[$insql, $params] = $DB->get_in_or_equal(array_slice($courseids, 0, 20) ?: [0]);
$names = array_map(fn($c) => format_string($c->fullname),
    $DB->get_records_select('course', "id $insql", $params, 'sortorder', 'id, fullname'));
if (count($courseids) > 20) {
    $names[] = get_string('andmore', 'local_lmssub', count($courseids) - 20);
}
$summary .= html_writer::tag('h4', get_string('coursesincluded', 'local_lmssub', count($courseids)), ['class' => 'h6 mt-3']);
$summary .= html_writer::alist($names);
if ($plan->type === plan_manager::TYPE_ALLACCESS) {
    $summary .= html_writer::tag('p', get_string('newcoursesincluded', 'local_lmssub'), ['class' => 'small text-muted']);
}

// The payment button opens Moodle's payment modal, which shows the Razorpay checkout.
$PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');
$attributes = \core_payment\helper::gateways_modal_link_params('local_lmssub', service_provider::AREA, (int) $price->id,
    get_string('paymentdescription', 'local_lmssub', (object) ['plan' => format_string($plan->name),
        'duration' => plan_manager::duration_label($days)]));
unset($attributes['id'], $attributes['role']);
$attributes['type'] = 'button';
$attributes['class'] = 'btn btn-primary btn-lg';
$attributes['data-lmssub-attempt'] = (int) $price->id;
$pay = html_writer::tag('button', get_string('paynow', 'local_lmssub', $cost), $attributes);

// Record the attempt for the admins as soon as the learner proceeds; the payment window opens regardless of the result.
$recordurl = json_encode((new moodle_url('/local/lmssub/recordattempt.php'))->out(false));
$PAGE->requires->js_init_code("
    document.querySelectorAll('[data-lmssub-attempt]').forEach(function(button) {
        button.addEventListener('click', function() {
            var data = new FormData();
            data.append('priceid', button.dataset.lmssubAttempt);
            data.append('sesskey', M.cfg.sesskey);
            fetch($recordurl, {method: 'POST', body: data, credentials: 'same-origin', keepalive: true}).catch(function() {});
        });
    });
");
$back = html_writer::link($plansurl, get_string('backtoplans', 'local_lmssub'), ['class' => 'btn btn-secondary btn-lg']);
$summary .= html_writer::div($pay . $back, 'd-flex flex-wrap gap-2 mt-3');
$summary .= html_writer::tag('p', get_string('paymentnote', 'local_lmssub'), ['class' => 'small text-muted mt-2 mb-0']);

echo html_writer::div(html_writer::div($summary, 'card-body'), 'card', ['style' => 'max-width: 72rem']);
echo $OUTPUT->footer();

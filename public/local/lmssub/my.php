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
 * My subscriptions: end dates and the courses each subscription opens.
 *
 * Renew, cancel auto-renew, invoices and the registered device are added with payments and device security.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_lmssub\access_engine;
use local_lmssub\plan_manager;
use local_lmssub\subscription_manager as subs;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests cannot have subscriptions');
}

$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url(new moodle_url('/local/lmssub/my.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mysubscriptions', 'local_lmssub'));
$PAGE->set_heading(get_string('mysubscriptions', 'local_lmssub'));

$paid = optional_param('paid', 0, PARAM_INT);

echo $OUTPUT->header();

if ($paid) {
    echo $OUTPUT->notification(get_string('paymentsuccess', 'local_lmssub'), 'success');
}
if (plan_manager::get_purchasable_plans()) {
    echo $OUTPUT->single_button(new moodle_url('/local/lmssub/index.php'), get_string('browseplans', 'local_lmssub'),
        'get', ['type' => single_button::BUTTON_PRIMARY]);
}

$subs = $DB->get_records_select('local_lmssub_sub', 'userid = :userid AND status <> :pending',
    ['userid' => $USER->id, 'pending' => subs::STATUS_PENDING], 'timeend DESC');
if (!$subs) {
    echo $OUTPUT->notification(get_string('nomysubscriptions', 'local_lmssub'), 'info');
    echo $OUTPUT->footer();
    die;
}
$purchasable = plan_manager::get_purchasable_plans();

$fmt = get_string('strftimedate');
foreach ($subs as $sub) {
    $plan = plan_manager::get_plan((int) $sub->planid);
    $end = access_engine::effective_end($sub, $plan);
    $live = $end > time();

    $lines = [
        get_string('status') . ': ' . subs::get_status_name($sub->status),
        get_string('level', 'local_lmssub') . ': ' . get_string('level_' . $sub->level, 'local_lmssub'),
    ];
    if ($sub->timeend) {
        $lines[] = ($live ? get_string('accessuntil', 'local_lmssub') : get_string('endedon', 'local_lmssub')) . ': ' .
            userdate($live ? $end : $sub->timeend, $fmt);
    }

    $courses = '';
    if ($live) {
        $courseids = plan_manager::courses_for_plan((int) $sub->planid);
        if ($courseids) {
            [$insql, $params] = $DB->get_in_or_equal(array_slice($courseids, 0, 50));
            $links = [];
            foreach ($DB->get_records_select('course', "id $insql", $params, 'fullname', 'id, fullname') as $course) {
                $links[] = html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]),
                    format_string($course->fullname));
            }
            if (count($courseids) > 50) {
                $links[] = get_string('andmore', 'local_lmssub', count($courseids) - 50);
            }
            $courses = html_writer::alist($links);
        }
    } else {
        $lines[] = get_string('progresswaiting', 'local_lmssub');
    }

    $body = html_writer::tag('h4', format_string($plan->name), ['class' => 'card-title']) .
        html_writer::alist($lines, ['class' => 'list-unstyled']) . $courses;
    // Renew: back to the plans page to choose a duration, then pay. Revoked subscriptions start over as a new purchase.
    if (isset($purchasable[$plan->id]) && $sub->status !== subs::STATUS_REVOKED) {
        $body .= html_writer::link(new moodle_url('/local/lmssub/index.php'), get_string('renew', 'local_lmssub'),
            ['class' => 'btn btn-outline-primary']);
    }
    echo html_writer::div(html_writer::div($body, 'card-body'), 'card mb-3');
}
echo $OUTPUT->footer();

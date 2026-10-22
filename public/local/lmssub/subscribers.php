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
 * Site administration > Subscriptions > Subscribers.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_lmssub\plan_manager;
use local_lmssub\subscription_manager;

$status = optional_param('status', '', PARAM_ALPHA);
$planid = optional_param('planid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_RAW_TRIMMED);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$baseurl = new moodle_url('/local/lmssub/subscribers.php', array_filter(['status' => $status, 'planid' => $planid,
    'search' => $search]));
admin_externalpage_setup('local_lmssub_subscribers', '', null, $baseurl);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('subscribers', 'local_lmssub'));
echo $OUTPUT->single_button(new moodle_url('/local/lmssub/grant.php'), get_string('grantsubscription', 'local_lmssub'),
    'get', ['type' => single_button::BUTTON_PRIMARY]);

// Learners who clicked "Proceed to payment" (or started paying) and did not complete it.
$attemptgroups = \local_lmssub\attempt_manager::group(\local_lmssub\attempt_manager::get_attempts(0, true));
echo $OUTPUT->heading(get_string('attemptedpayments', 'local_lmssub'), 3, 'mt-4');
if ($attemptgroups) {
    echo html_writer::tag('p', get_string('attemptedpayments_desc', 'local_lmssub'), ['class' => 'text-muted']);
    echo $OUTPUT->render_from_template('local_lmssub/attempts_table', [
        'rows' => \local_lmssub\attempt_manager::export_rows(array_slice($attemptgroups, 0, 10), $baseurl),
    ]);
    echo html_writer::link(new moodle_url('/local/lmssub/attempts.php'),
        get_string('attemptedpayments_all', 'local_lmssub', count($attemptgroups)) .
        ' ' . $OUTPUT->pix_icon('t/right', ''), ['class' => 'd-inline-block mb-3']);
} else {
    echo $OUTPUT->notification(get_string('noattempts_open', 'local_lmssub'), 'info', false);
}
echo $OUTPUT->heading(get_string('subscribers', 'local_lmssub'), 3, 'mt-4');

// Filters.
$plans = $DB->get_records_menu('local_lmssub_plan', null, 'sortorder', 'id, name');
$plans = array_map('format_string', $plans);
$statuses = subscription_manager::get_statuses();
$filter = html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(),
    'class' => 'd-flex flex-wrap gap-2 align-items-end my-3']);
$filter .= html_writer::select(['' => get_string('allstatuses', 'local_lmssub')] + $statuses, 'status', $status, false,
    ['class' => 'form-select w-auto']);
$filter .= html_writer::select([0 => get_string('allplans', 'local_lmssub')] + $plans, 'planid', $planid, false,
    ['class' => 'form-select w-auto']);
$filter .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search', 'value' => $search,
    'placeholder' => get_string('searchuser', 'local_lmssub'), 'class' => 'form-control w-auto']);
$filter .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'), 'class' => 'btn btn-secondary']);
$filter .= html_writer::end_tag('form');
echo $filter;

$where = ['u.deleted = 0'];
$params = [];
if ($status !== '' && isset($statuses[$status])) {
    $where[] = 's.status = :status';
    $params['status'] = $status;
}
if ($planid) {
    $where[] = 's.planid = :planid';
    $params['planid'] = $planid;
}
if ($search !== '') {
    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $where[] = '(' . $DB->sql_like($fullname, ':s1', false) . ' OR ' . $DB->sql_like('u.email', ':s2', false) . ')';
    $params['s1'] = $params['s2'] = '%' . $DB->sql_like_escape($search) . '%';
}
$wheresql = implode(' AND ', $where);
$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

$total = $DB->count_records_sql("SELECT COUNT(1)
                                   FROM {local_lmssub_sub} s
                                   JOIN {user} u ON u.id = s.userid
                                  WHERE $wheresql", $params);
$subs = $DB->get_records_sql("SELECT s.*, u.email, $userfields
                                FROM {local_lmssub_sub} s
                                JOIN {user} u ON u.id = s.userid
                               WHERE $wheresql
                            ORDER BY s.timemodified DESC, s.id DESC", $params, $page * $perpage, $perpage);

if (!$subs) {
    echo $OUTPUT->notification(get_string('nosubscriptions', 'local_lmssub'), 'info');
    echo $OUTPUT->footer();
    die;
}

$table = new html_table();
$table->head = [
    get_string('user'), get_string('plan', 'local_lmssub'), get_string('level', 'local_lmssub'), get_string('status'),
    get_string('startdate', 'local_lmssub'), get_string('enddate', 'local_lmssub'), get_string('source', 'local_lmssub'),
    get_string('actions'),
];
foreach ($subs as $sub) {
    $url = fn($action) => new moodle_url('/local/lmssub/subaction.php', ['id' => $sub->id, 'action' => $action]);
    $actions = [html_writer::link($url('view'), get_string('history', 'local_lmssub'))];
    foreach (['extend' => \local_lmssub\subscription_manager::STATUS_ACTIVE,
              'cancel' => \local_lmssub\subscription_manager::STATUS_CANCELLED,
              'revoke' => \local_lmssub\subscription_manager::STATUS_REVOKED] as $action => $to) {
        if (subscription_manager::can_transition($sub->status, $to)) {
            $actions[] = html_writer::link($url($action), get_string('action_' . $action, 'local_lmssub'));
        }
    }
    $end = $sub->timeend ? userdate($sub->timeend, get_string('strftimedatetimeshort')) : '-';
    if ($sub->status === subscription_manager::STATUS_GRACE && $sub->graceend) {
        $end .= html_writer::tag('div', get_string('graceuntil', 'local_lmssub',
            userdate($sub->graceend, get_string('strftimedatetimeshort'))), ['class' => 'small text-muted']);
    }
    $table->data[] = [
        html_writer::link(new moodle_url('/user/profile.php', ['id' => $sub->userid]), fullname($sub)) .
            html_writer::tag('div', s($sub->email), ['class' => 'small text-muted']),
        $plans[$sub->planid] ?? '-',
        get_string('level_' . $sub->level, 'local_lmssub'),
        subscription_manager::get_status_name($sub->status),
        $sub->timestart ? userdate($sub->timestart, get_string('strftimedatetimeshort')) : '-',
        $end,
        get_string('source_' . $sub->source, 'local_lmssub'),
        implode(' | ', $actions),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
echo $OUTPUT->footer();

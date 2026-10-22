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
 * Payment Reports: transactions of an organisation with summary, charts, list, detail and exports.
 *
 * The page draws the shell; report_orgreports/payments loads the data through the report_orgreports_get_payment_report,
 * report_orgreports_get_table and report_orgreports_get_payment_detail web services, which check permissions again.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use report_orgreports\daterange;
use report_orgreports\dashboard;
use report_orgreports\org_context;

$orgparam = optional_param('org', -1, PARAM_INT);
$rangeparam = optional_param('range', 'last30', PARAM_ALPHANUM);
$from = optional_param('from', '', PARAM_RAW_TRIMMED);
$to = optional_param('to', '', PARAM_RAW_TRIMMED);

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}

// Only organisations where the user may see sales.
$orgs = [];
foreach (org_context::get_available_orgs() as $id => $name) {
    if (org_context::for_user($id)->cansales) {
        $orgs[$id] = $name;
    }
}
if (!$orgs) {
    throw new moodle_exception('nopaymentaccess', 'report_orgreports');
}
$org = org_context::for_user(array_key_exists($orgparam, $orgs) ? $orgparam : array_key_first($orgs));
$range = daterange::from_values($rangeparam, $from, $to);

$url = new moodle_url('/report/orgreports/payments.php', ['org' => $org->id] + $range->to_params());
$PAGE->set_context($org->context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('paymentreports', 'report_orgreports') . ' – ' . $org->name);
$PAGE->set_heading(get_string('paymentreports', 'report_orgreports'));
$PAGE->add_body_class('report-orgreports-page');

$orgoptions = [];
foreach ($orgs as $id => $name) {
    $orgoptions[] = ['id' => $id, 'name' => $name, 'selected' => $id === $org->id];
}
$ranges = [];
foreach (daterange::PRESETS as $preset) {
    $ranges[] = ['value' => $preset, 'name' => get_string('range_' . $preset, 'report_orgreports'),
        'selected' => $preset === $range->preset];
}

$strings = [];
foreach (['loading', 'loadfailed', 'loadfailedhint', 'tablefailed', 'chartfailed', 'nopermission', 'nopermissionhint',
        'retry', 'nochartdata', 'customneeded', 'norows', 'norowsfiltered', 'norowsperiod', 'search', 'allcourses',
        'allstatuses', 'downloadcsv', 'rowsperpage', 'perpageoption', 'showing', 'pagination', 'previous', 'next', 'insights',
        'aboutdata', 'availability', 'keymetrics', 'metric', 'change', 'total', 'vsprevious', 'fromzero', 'currenttotal',
        'nochange', 'srup', 'srdown', 'srflat', 'hoursunit', 'minutesunit', 'mbunit', 'err_required', 'err_invalid',
        'err_order', 'err_future', 'err_toolong', 'notxmatch', 'notxmatchhint', 'resetfilters', 'detailtitle', 'close',
        'detailfailed', 'timeline', 'pexportcsv', 'pexportcsv_desc', 'pexportpdf', 'pexportpdf_desc', 'allmethods',
        'allplans', 'filtersactive'] as $key) {
    $strings[$key] = get_string('js_' . $key, 'report_orgreports');
}

$config = [
    'org' => $org->id,
    'range' => $range->preset,
    'from' => $range->from,
    'to' => $range->to,
    'today' => userdate(time(), '%Y-%m-%d', 99, false),
    'filters' => [
        'status' => optional_param('status', '', PARAM_ALPHA),
        'method' => optional_param('method', '', PARAM_ALPHANUMEXT),
        'courseid' => optional_param('courseid', 0, PARAM_INT),
        'planid' => optional_param('planid', 0, PARAM_INT),
        'search' => optional_param('search', '', PARAM_TEXT),
    ],
    'access' => dashboard::get_access($org),
    'exporturl' => (new moodle_url('/report/orgreports/export.php'))->out(false),
    'pageurl' => (new moodle_url('/report/orgreports/payments.php'))->out(false),
    'locale' => str_replace('_', '-', current_language()),
    'strings' => $strings,
];

$PAGE->requires->js_call_amd('report_orgreports/payments', 'init', ['#report-orgreports-payments']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('report_orgreports/payments', [
    'config' => json_encode($config),
    'orgs' => $orgoptions,
    'multipleorgs' => count($orgoptions) > 1,
    'orgname' => $org->name,
    'period' => $range->get_label(),
    'ranges' => $ranges,
    'from' => $range->from,
    'to' => $range->to,
    'today' => $config['today'],
    'canexport' => $org->canexport,
    'dashboardurl' => (new moodle_url('/report/orgreports/index.php', ['org' => $org->id, 'section' => 'sales'] +
        $range->to_params()))->out(false),
    'search' => $config['filters']['search'],
]);
echo $OUTPUT->footer();

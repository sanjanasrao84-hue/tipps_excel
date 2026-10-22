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
 * Organisation reports dashboard.
 *
 * The page only draws the shell; sections and tables are loaded by the report_orgreports/dashboard module through
 * the report_orgreports_get_section and report_orgreports_get_table web services.
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
$section = optional_param('section', 'overview', PARAM_ALPHA);
$rangeparam = optional_param('range', 'last30', PARAM_ALPHANUM);
$from = optional_param('from', '', PARAM_RAW_TRIMMED);
$to = optional_param('to', '', PARAM_RAW_TRIMMED);
$courseparam = optional_param('course', 0, PARAM_INT);

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}

$orgs = org_context::get_available_orgs();
if (!$orgs) {
    throw new moodle_exception('noaccess', 'report_orgreports');
}
$org = org_context::for_user(array_key_exists($orgparam, $orgs) ? $orgparam : null, 0, $courseparam);
$range = daterange::from_values($rangeparam, $from, $to);
$access = dashboard::get_access($org);
if (!in_array($section, array_column($access['sections'], 'key'), true)) {
    $section = 'overview';
}

$courseparams = $org->courseid ? ['course' => $org->courseid] : [];
$url = new moodle_url('/report/orgreports/index.php', ['org' => $org->id] + $courseparams + ['section' => $section] +
    $range->to_params());
$PAGE->set_context($org->context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'report_orgreports') . ' – ' . $org->name);
$PAGE->set_heading(get_string('pluginname', 'report_orgreports'));
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

// Strings used by the JavaScript components.
$strings = [];
foreach (['loading', 'loadfailed', 'loadfailedhint', 'tablefailed', 'chartfailed', 'nopermission', 'nopermissionhint',
        'retry', 'nochartdata', 'customneeded', 'periodempty', 'norows', 'norowsfiltered', 'norowsperiod', 'search',
        'allcourses', 'allstatuses', 'downloadcsv', 'rowsperpage', 'perpageoption', 'showing', 'pagination', 'previous',
        'next', 'insights', 'aboutdata', 'availability', 'keymetrics', 'periodsummary', 'metric', 'change', 'total',
        'vsprevious', 'fromzero', 'currenttotal', 'nochange', 'srup', 'srdown', 'srflat', 'hoursunit', 'minutesunit',
        'mbunit', 'exportstats', 'exportcsv', 'exportcsv_desc', 'exportpdf', 'exportpdf_desc', 'exporttables',
        'exporttable_desc', 'err_required', 'err_invalid', 'err_order', 'err_future', 'err_toolong'] as $key) {
    $strings[$key] = get_string('js_' . $key, 'report_orgreports');
}

$config = [
    'org' => $org->id,
    'course' => $org->courseid,
    'section' => $section,
    'range' => $range->preset,
    'from' => $range->from,
    'to' => $range->to,
    // Today in the user's timezone, the latest date a custom range may end on.
    'today' => userdate(time(), '%Y-%m-%d', 99, false),
    'access' => $access,
    'exporturl' => (new moodle_url('/report/orgreports/export.php'))->out(false),
    'pageurl' => (new moodle_url('/report/orgreports/index.php'))->out(false),
    // A BCP 47 tag for Intl (the langconfig locale is a system locale such as en_AU.UTF-8, which Intl rejects).
    'locale' => str_replace('_', '-', current_language()),
    'strings' => $strings,
];

$PAGE->requires->js_call_amd('report_orgreports/dashboard', 'init', ['#report-orgreports']);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('report_orgreports/dashboard', [
    'config' => json_encode($config),
    'orgs' => $orgoptions,
    'multipleorgs' => count($orgoptions) > 1,
    'courses' => array_map(fn($c) => $c + ['selected' => $c['id'] === $org->courseid], $access['orgcourses']),
    'hascourses' => !empty($access['orgcourses']),
    'orgname' => $org->name,
    'period' => $range->get_label(),
    'previousperiod' => $range->get_label(true),
    'ranges' => $ranges,
    'from' => $range->from,
    'to' => $range->to,
    'today' => $config['today'],
    'section' => $section,
    'sections' => array_map(fn($s) => $s + ['active' => $s['key'] === $section,
        'icon' => ['overview' => 'fa-gauge-high', 'sales' => 'fa-cart-shopping', 'usage' => 'fa-server',
            'activity' => 'fa-school'][$s['key']]], $access['sections']),
    'canexport' => $org->canexport,
    'cansales' => $org->cansales,
    'paymentsurl' => (new moodle_url('/report/orgreports/payments.php', ['org' => $org->id] + $range->to_params()))->out(false),
    'courselevel' => !$org->orglevel,
    'courselevelnotice' => get_string('courselevelnotice', 'report_orgreports', count($org->get_courses())),
]);
echo $OUTPUT->footer();

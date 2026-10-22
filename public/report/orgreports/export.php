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
 * Organisation reports: downloads.
 *
 * kind=table: CSV of a table with its filters. kind=stats: statistical report of a section (summary metrics,
 * organisation, period and the aggregated chart data) as CSV or PDF. Both use the same organisation, period and
 * permission checks as the dashboard.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use report_orgreports\dashboard;
use report_orgreports\local\tables\table_request;

$orgid = required_param('org', PARAM_INT);
$kind = required_param('kind', PARAM_ALPHA);
$range = optional_param('range', 'last30', PARAM_ALPHANUM);
$from = optional_param('from', '', PARAM_RAW_TRIMMED);
$to = optional_param('to', '', PARAM_RAW_TRIMMED);
$course = optional_param('course', 0, PARAM_INT);

require_login(null, false);
require_sesskey();
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}

$metrics = dashboard::metrics($orgid, $range, $from, $to, $kind === 'payments' ? 0 : $course);
$org = $metrics->org;
if (!$org->canexport) {
    throw new required_capability_exception($org->context, 'report/orgreports:export', 'nopermissions', '');
}
$PAGE->set_context($org->context);
$PAGE->set_url(new moodle_url('/report/orgreports/export.php'));

$daterange = $metrics->range;
$basename = clean_filename(core_text::strtolower(str_replace(' ', '-', $org->name .
    ($org->courseid ? ' ' . $org->get_courses()[$org->courseid]->shortname : ''))) . '_' . $daterange->from . '_' .
    $daterange->to);

if ($kind === 'table') {
    $tablekey = required_param('table', PARAM_ALPHA);
    $request = new table_request(optional_param('search', '', PARAM_TEXT), optional_param('courseid', 0, PARAM_INT),
        optional_param('status', '', PARAM_ALPHA), optional_param('sort', '', PARAM_ALPHANUMEXT),
        optional_param('dir', 'ASC', PARAM_ALPHA));
    $table = dashboard::table($metrics, $tablekey, $request);
    [$headers, $rows] = $table->get_export();
    \core\dataformat::download_data($basename . '_' . $tablekey, 'csv', $headers, $rows);
    die();
}

if ($kind === 'payments') {
    // Payment Reports: the filtered transaction list with the report details and summary, or a summary PDF.
    if (!$org->cansales) {
        throw new required_capability_exception($org->context, 'report/orgreports:viewsales', 'nopermissions', '');
    }
    $filters = new table_request(optional_param('search', '', PARAM_TEXT), optional_param('courseid', 0, PARAM_INT),
        optional_param('status', '', PARAM_ALPHA), optional_param('sort', '', PARAM_ALPHANUMEXT),
        optional_param('dir', 'DESC', PARAM_ALPHA), 0, 25, optional_param('method', '', PARAM_ALPHANUMEXT),
        optional_param('planid', 0, PARAM_INT));
    $query = new \report_orgreports\local\payment_query($org, $filters);
    $section = new \report_orgreports\local\sections\payments($metrics, $query);
    $data = $section->get_data();
    $table = dashboard::table($metrics, 'payments', $filters);
    [$headers, $rows] = $table->get_export();
    $plain = fn($text) => html_entity_decode(strip_tags((string)$text), ENT_QUOTES, 'UTF-8');
    $options = dashboard::payment_filter_options($query);
    $applied = [];
    foreach (['status' => 'statuses', 'method' => 'methods', 'courseid' => 'courses', 'planid' => 'plans'] as $f => $list) {
        if ($options['applied'][$f]) {
            $labels = array_column($options[$list], 'label', 'value');
            $applied[] = get_string('filter_' . $f, 'report_orgreports') . ': ' . ($labels[(string)$options['applied'][$f]] ??
                $options['applied'][$f]);
        }
    }
    if ($filters->search !== '') {
        $applied[] = get_string('search') . ': ' . $filters->search;
    }
    $meta = [
        [get_string('report', 'report_orgreports'), get_string('paymentreports', 'report_orgreports')],
        [get_string('organisation', 'report_orgreports'), $org->name],
        [get_string('reportingperiod', 'report_orgreports'), $data['period'] . ' (' . $daterange->from . ' – ' .
            $daterange->to . ')'],
        [get_string('comparisonperiod', 'report_orgreports'), $data['previousperiod']],
        [get_string('filtersapplied', 'report_orgreports'), $applied ? implode('; ', $applied) :
            get_string('nofilters', 'report_orgreports')],
        [get_string('timezone'), core_date::get_user_timezone()],
        [get_string('generated', 'report_orgreports'), userdate(time(), '%Y-%m-%d %H:%M', 99, false)],
    ];
    $filename = 'payment_report_' . userdate(time(), '%Y-%m-%d', 99, false);
    if (optional_param('format', 'csv', PARAM_ALPHA) !== 'pdf') {
        $width = max(count($headers), 5);
        $pad = fn(array $r) => array_pad(array_map(function($v) {
            $v = (string)$v;
            return preg_match('/^[=+\-@\t\r]/', $v) && !is_numeric($v) ? "'" . $v : $v;
        }, $r), $width, '');
        $all = (function() use ($meta, $data, $headers, $rows, $plain, $pad) {
            foreach (array_slice($meta, 1) as $line) {
                yield $pad($line);
            }
            yield $pad([]);
            yield $pad([get_string('summarymetrics', 'report_orgreports')]);
            yield $pad([get_string('metric', 'report_orgreports'), get_string('value', 'report_orgreports'),
                get_string('previousperiod', 'report_orgreports'), get_string('changepercent', 'report_orgreports')]);
            foreach ($data['cards'] as $card) {
                yield $pad([$card['label'], $plain($card['value']), $plain($card['previous'] ?? ''),
                    $card['change'] === null ? '' : sprintf('%.1f', $card['change'])]);
            }
            yield $pad([]);
            yield $pad([get_string('table_payments', 'report_orgreports')]);
            yield $pad($headers);
            foreach ($rows as $row) {
                yield $pad($row);
            }
        })();
        \core\dataformat::download_data($filename, 'csv', $pad($meta[0]), $all);
        die();
    }
    require_once($CFG->libdir . '/pdflib.php');
    $esc = fn($text) => s(str_replace(['–', '—'], '-', $plain($text)));
    $html = '<h1 style="color:#0369a1;">' . $esc(get_string('paymentreports', 'report_orgreports')) . '</h1>' .
        '<table cellpadding="3">';
    foreach ($meta as [$label, $value]) {
        $html .= '<tr><td width="28%"><b>' . $esc($label) . '</b></td><td width="72%">' . $esc($value) . '</td></tr>';
    }
    $html .= '</table><h2 style="color:#0369a1;">' . $esc(get_string('summarymetrics', 'report_orgreports')) . '</h2>' .
        '<table border="0.5" cellpadding="4"><tr style="background-color:#e0f2fe;"><th width="34%"><b>' .
        $esc(get_string('metric', 'report_orgreports')) . '</b></th><th width="26%"><b>' .
        $esc(get_string('value', 'report_orgreports')) . '</b></th><th width="26%"><b>' .
        $esc(get_string('previousperiod', 'report_orgreports')) . '</b></th><th width="14%"><b>' .
        $esc(get_string('changepercent', 'report_orgreports')) . '</b></th></tr>';
    foreach ($data['cards'] as $card) {
        $html .= '<tr><td>' . $esc($card['label']) . '</td><td>' . $esc($card['value']) . '</td><td>' .
            $esc($card['previous'] ?? '-') . '</td><td>' . ($card['change'] === null ? '-' : sprintf('%+.1f%%', $card['change'])) .
            '</td></tr>';
    }
    $html .= '</table><h2 style="color:#0369a1;">' . $esc(get_string('table_payments', 'report_orgreports')) . '</h2>';
    // The PDF lists the columns that fit a page; the CSV holds every column.
    $keep = [0, 1, 2];
    foreach ($headers as $i => $header) {
        if (in_array($header, [get_string('col_customer', 'report_orgreports'), get_string('col_amount', 'report_orgreports'),
                get_string('status'), get_string('col_refund', 'report_orgreports')])) {
            $keep[] = $i;
        }
    }
    $html .= '<table border="0.5" cellpadding="3" style="font-size:7pt;"><tr style="background-color:#e0f2fe;">';
    foreach ($keep as $i) {
        $html .= '<th><b>' . $esc($headers[$i]) . '</b></th>';
    }
    $html .= '</tr>';
    $count = 0;
    $limit = 500;
    foreach ($rows as $row) {
        if (++$count > $limit) {
            break;
        }
        $html .= '<tr>';
        foreach ($keep as $i) {
            $html .= '<td>' . $esc($row[$i]) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    if ($count > $limit) {
        $html .= '<p><i>' . $esc(get_string('pdflimit', 'report_orgreports', $limit)) . '</i></p>';
    }
    $html .= '<h3 style="color:#0369a1;">' . $esc(get_string('pnotice_rules', 'report_orgreports')) . '</h3><p>' .
        $esc(get_string('pnotice_rules_desc', 'report_orgreports')) . '</p>';
    $pdf = new pdf();
    $pdf->SetTitle(get_string('paymentreports', 'report_orgreports'));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetFont('freesans', '', 9);
    $pdf->AddPage();
    $pdf->writeHTML($html);
    $pdf->Output($filename . '.pdf', 'D');
    die();
}

if ($kind !== 'stats') {
    throw new invalid_parameter_exception('Unknown export');
}
$sectionkey = required_param('section', PARAM_ALPHA);
$format = optional_param('format', 'csv', PARAM_ALPHA) === 'pdf' ? 'pdf' : 'csv';
$data = dashboard::section($metrics, $sectionkey)->get_data();
$title = get_string('pluginname', 'report_orgreports') . ' – ' . get_string('section_' . $sectionkey, 'report_orgreports');
$plain = fn($text) => html_entity_decode(strip_tags((string)$text), ENT_QUOTES, 'UTF-8');
$meta = [
    [get_string('report', 'report_orgreports'), $title],
    [get_string('organisation', 'report_orgreports'), $data['organisation']],
    [get_string('filter_courseid', 'report_orgreports'), $org->courseid ?
        format_string($org->get_courses()[$org->courseid]->fullname) : get_string('js_allcourses', 'report_orgreports')],
    [get_string('period', 'report_orgreports'), $data['period']],
    [get_string('previousperiod', 'report_orgreports'), $data['previousperiod']],
    [get_string('timezone'), core_date::get_user_timezone()],
    [get_string('generated', 'report_orgreports'), userdate(time(), '%Y-%m-%d %H:%M')],
];

if ($format === 'csv') {
    // One CSV holding blocks: report details, summary metrics, every chart's aggregated data, insights and notices.
    $rows = [];
    foreach ($meta as $line) {
        $rows[] = $line;
    }
    $rows[] = [];
    $rows[] = [get_string('summarymetrics', 'report_orgreports')];
    $rows[] = [get_string('metric', 'report_orgreports'), get_string('value', 'report_orgreports'),
        get_string('previousperiod', 'report_orgreports'), get_string('changepercent', 'report_orgreports'),
        get_string('notes', 'report_orgreports')];
    foreach ($data['cards'] as $card) {
        $rows[] = [$card['label'], $plain($card['value']), $plain($card['previous'] ?? ''),
            $card['change'] === null ? '' : sprintf('%.1f', $card['change']), $plain($card['hint'])];
    }
    foreach ($data['charts'] as $chart) {
        $rows[] = [];
        $rows[] = [$chart['title'] . ($chart['unit'] !== 'count' ? ' (' . $chart['unit'] . ')' : '')];
        $rows[] = array_merge([get_string($chart['dates'] ? 'date' : 'label', $chart['dates'] ? 'moodle' : 'report_orgreports')],
            array_column($chart['datasets'], 'label'));
        foreach ($chart['labels'] as $i => $label) {
            $line = [$chart['dates'][$i] ?? $plain($label)];
            foreach ($chart['datasets'] as $dataset) {
                $line[] = $dataset['data'][$i] ?? '';
            }
            $rows[] = $line;
        }
    }
    if ($data['insights']) {
        $rows[] = [];
        $rows[] = [get_string('insights', 'report_orgreports')];
        foreach ($data['insights'] as $insight) {
            $rows[] = [$plain($insight)];
        }
    }
    if ($data['availability']) {
        $rows[] = [];
        $rows[] = [get_string('js_availability', 'report_orgreports')];
        $rows[] = [get_string('metric', 'report_orgreports'), get_string('status'), get_string('js_source',
            'report_orgreports')];
        foreach ($data['availability'] as $row) {
            $rows[] = [$plain($row['metric']), $row['statuslabel'], $plain($row['source'])];
        }
    }
    if ($data['notices']) {
        $rows[] = [];
        $rows[] = [get_string('notices', 'report_orgreports')];
        foreach ($data['notices'] as $notice) {
            $rows[] = [$plain($notice['title']), $plain($notice['text'])];
        }
    }
    $width = max(array_map('count', $rows));
    $rows = array_map(fn($r) => array_pad(array_map(function($v) {
        $v = (string)$v;
        return preg_match('/^[=+\-@\t\r]/', $v) && !is_numeric($v) ? "'" . $v : $v;
    }, $r), $width, ''), $rows);
    $headers = array_shift($rows);
    \core\dataformat::download_data($basename . '_' . $sectionkey . '_statistics', 'csv', $headers, $rows);
    die();
}

// PDF.
require_once($CFG->libdir . '/pdflib.php');
// Plain hyphens: every PDF reader shows them, whatever the font subset.
$esc = fn($text) => s(str_replace(['–', '—'], '-', $plain($text)));
$html = '<h1 style="color:#0369a1;">' . $esc($title) . '</h1><table cellpadding="3">';
foreach ($meta as [$label, $value]) {
    $html .= '<tr><td width="30%"><b>' . $esc($label) . '</b></td><td width="70%">' . $esc($value) . '</td></tr>';
}
$html .= '</table><h2 style="color:#0369a1;">' . $esc(get_string('summarymetrics', 'report_orgreports')) . '</h2>';
$html .= '<table border="0.5" cellpadding="4"><tr style="background-color:#e0f2fe;"><th width="26%"><b>' .
    $esc(get_string('metric', 'report_orgreports')) . '</b></th><th width="22%"><b>' .
    $esc(get_string('value', 'report_orgreports')) . '</b></th><th width="22%"><b>' .
    $esc(get_string('previousperiod', 'report_orgreports')) . '</b></th><th width="12%"><b>' .
    $esc(get_string('changepercent', 'report_orgreports')) . '</b></th><th width="18%"><b>' .
    $esc(get_string('notes', 'report_orgreports')) . '</b></th></tr>';
foreach ($data['cards'] as $card) {
    $html .= '<tr><td>' . $esc($card['label']) . '</td><td>' . $esc($card['value']) . '</td><td>' .
        $esc($card['previous'] ?? '—') . '</td><td>' . ($card['change'] === null ? '—' : sprintf('%+.1f%%', $card['change'])) .
        '</td><td style="font-size:7pt;">' . $esc($card['hint']) . '</td></tr>';
}
$html .= '</table>';
foreach ($data['charts'] as $chart) {
    $html .= '<h3 style="color:#0369a1;">' . $esc($chart['title']) . '</h3>';
    if ($chart['summary'] !== '') {
        $html .= '<p>' . $esc($chart['summary']) . '</p>';
    }
    if ($chart['empty']) {
        $html .= '<p><i>' . $esc(get_string('nodataperiod', 'report_orgreports')) . '</i></p>';
        continue;
    }
    $html .= '<table border="0.5" cellpadding="3"><tr style="background-color:#e0f2fe;"><th><b>' .
        $esc(get_string('label', 'report_orgreports')) . '</b></th>';
    foreach ($chart['datasets'] as $dataset) {
        $html .= '<th><b>' . $esc($dataset['label']) . '</b></th>';
    }
    $html .= '</tr>';
    foreach ($chart['labels'] as $i => $label) {
        $html .= '<tr><td>' . $esc($label) . '</td>';
        foreach ($chart['datasets'] as $dataset) {
            $html .= '<td>' . $esc(format_float($dataset['data'][$i] ?? 0, 2, true, true)) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
}
if ($data['availability']) {
    $html .= '<h3 style="color:#0369a1;">' . $esc(get_string('js_availability', 'report_orgreports')) . '</h3>' .
        '<table border="0.5" cellpadding="3"><tr style="background-color:#e0f2fe;"><th width="28%"><b>' .
        $esc(get_string('metric', 'report_orgreports')) . '</b></th><th width="16%"><b>' . $esc(get_string('status')) .
        '</b></th><th width="56%"><b>' . $esc(get_string('js_source', 'report_orgreports')) . '</b></th></tr>';
    foreach ($data['availability'] as $row) {
        $html .= '<tr><td>' . $esc($row['metric']) . '</td><td>' . $esc($row['statuslabel']) . '</td><td>' .
            $esc($row['source']) . '</td></tr>';
    }
    $html .= '</table>';
}
foreach (['insights' => $data['insights'], 'notices' => array_map(fn($n) => $n['title'] . ': ' . $n['text'],
        $data['notices'])] as $key => $items) {
    if ($items) {
        $html .= '<h3 style="color:#0369a1;">' . $esc(get_string($key, 'report_orgreports')) . '</h3><ul>';
        foreach ($items as $item) {
            $html .= '<li>' . $esc($item) . '</li>';
        }
        $html .= '</ul>';
    }
}
$pdf = new pdf();
$pdf->SetTitle($plain($title));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetFont('freesans', '', 9);
$pdf->AddPage();
$pdf->writeHTML($html);
$pdf->Output($basename . '_' . $sectionkey . '_statistics.pdf', 'D');

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

namespace report_orgreports\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use report_orgreports\dashboard;
use report_orgreports\local\payment_query;
use report_orgreports\local\sections\payments;
use report_orgreports\local\tables\table_request;

/**
 * Web service: summary cards, charts and filter options of the Payment Reports page.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_payment_report extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'org' => new external_value(PARAM_INT, 'Organisation id, 0 for all organisations'),
            'range' => new external_value(PARAM_ALPHANUM, 'Date range preset'),
            'from' => new external_value(PARAM_RAW_TRIMMED, 'Custom range start Y-m-d', VALUE_DEFAULT, ''),
            'to' => new external_value(PARAM_RAW_TRIMMED, 'Custom range end Y-m-d', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status filter', VALUE_DEFAULT, ''),
            'method' => new external_value(PARAM_ALPHANUMEXT, 'Payment method filter', VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT, 'Course filter', VALUE_DEFAULT, 0),
            'planid' => new external_value(PARAM_INT, 'Plan filter', VALUE_DEFAULT, 0),
            'search' => new external_value(PARAM_TEXT, 'Transaction id, reference or customer', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Build the report.
     *
     * @param int $org
     * @param string $range
     * @param string $from
     * @param string $to
     * @param string $status
     * @param string $method
     * @param int $courseid
     * @param int $planid
     * @param string $search
     * @return array
     */
    public static function execute(int $org, string $range, string $from = '', string $to = '', string $status = '',
            string $method = '', int $courseid = 0, int $planid = 0, string $search = ''): array {
        $p = self::validate_parameters(self::execute_parameters(), compact('org', 'range', 'from', 'to', 'status', 'method',
            'courseid', 'planid', 'search'));
        $metrics = dashboard::metrics($p['org'], $p['range'], $p['from'], $p['to']);
        self::validate_context($metrics->org->context);
        if (!payments::is_allowed($metrics->org)) {
            throw new \required_capability_exception($metrics->org->context, 'report/orgreports:viewsales',
                'nopermissions', '');
        }
        $filters = new table_request($p['search'], $p['courseid'], $p['status'], '', 'ASC', 0, 25, $p['method'],
            $p['planid']);
        $data = dashboard::guard(function() use ($metrics, $filters) {
            $query = new payment_query($metrics->org, $filters);
            $data = (new payments($metrics, $query))->get_data();
            $data['filters'] = dashboard::payment_filter_options($query);
            return $data;
        }, 'payment report');
        $data['access'] = dashboard::get_access($metrics->org);
        return ['json' => json_encode($data)];
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'json' => new external_value(PARAM_RAW, 'Report data as JSON'),
        ]);
    }
}

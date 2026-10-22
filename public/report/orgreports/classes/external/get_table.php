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
use report_orgreports\local\tables\table_request;

/**
 * Web service: one page of a dashboard table.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_table extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'org' => new external_value(PARAM_INT, 'Organisation id, 0 for all organisations'),
            'table' => new external_value(PARAM_ALPHA, 'Table key'),
            'range' => new external_value(PARAM_ALPHANUM, 'Date range preset'),
            'from' => new external_value(PARAM_RAW_TRIMMED, 'Custom range start Y-m-d', VALUE_DEFAULT, ''),
            'to' => new external_value(PARAM_RAW_TRIMMED, 'Custom range end Y-m-d', VALUE_DEFAULT, ''),
            'search' => new external_value(PARAM_TEXT, 'Search', VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT, 'Course filter', VALUE_DEFAULT, 0),
            'status' => new external_value(PARAM_ALPHA, 'Status filter', VALUE_DEFAULT, ''),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort column', VALUE_DEFAULT, ''),
            'dir' => new external_value(PARAM_ALPHA, 'ASC or DESC', VALUE_DEFAULT, 'ASC'),
            'page' => new external_value(PARAM_INT, 'Page', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page', VALUE_DEFAULT, 25),
            'method' => new external_value(PARAM_ALPHANUMEXT, 'Payment method filter', VALUE_DEFAULT, ''),
            'planid' => new external_value(PARAM_INT, 'Subscription plan filter', VALUE_DEFAULT, 0),
            'course' => new external_value(PARAM_INT, 'Course of the organisation, 0 for all', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Build the page.
     *
     * @param int $org
     * @param string $table
     * @param string $range
     * @param string $from
     * @param string $to
     * @param string $search
     * @param int $courseid
     * @param string $status
     * @param string $sort
     * @param string $dir
     * @param int $page
     * @param int $perpage
     * @param string $method
     * @param int $planid
     * @param int $course
     * @return array
     */
    public static function execute(int $org, string $table, string $range, string $from = '', string $to = '',
            string $search = '', int $courseid = 0, string $status = '', string $sort = '', string $dir = 'ASC',
            int $page = 0, int $perpage = 25, string $method = '', int $planid = 0, int $course = 0): array {
        $p = self::validate_parameters(self::execute_parameters(), compact('org', 'table', 'range', 'from', 'to', 'search',
            'courseid', 'status', 'sort', 'dir', 'page', 'perpage', 'method', 'planid', 'course'));
        $metrics = dashboard::metrics($p['org'], $p['range'], $p['from'], $p['to'], $p['course']);
        self::validate_context($metrics->org->context);
        $request = new table_request($p['search'], $p['courseid'], $p['status'], $p['sort'], $p['dir'], $p['page'],
            $p['perpage'], $p['method'], $p['planid']);
        $table = dashboard::table($metrics, $p['table'], $request);
        return ['json' => json_encode(dashboard::guard(fn() => $table->get_page(), 'table ' . $p['table']))];
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'json' => new external_value(PARAM_RAW, 'Table page as JSON'),
        ]);
    }
}

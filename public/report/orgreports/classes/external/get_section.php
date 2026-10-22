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

/**
 * Web service: cards, charts, insights and notices of one dashboard section.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_section extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'org' => new external_value(PARAM_INT, 'Organisation id, 0 for all organisations'),
            'section' => new external_value(PARAM_ALPHA, 'Section key'),
            'range' => new external_value(PARAM_ALPHANUM, 'Date range preset'),
            'from' => new external_value(PARAM_RAW_TRIMMED, 'Custom range start Y-m-d', VALUE_DEFAULT, ''),
            'to' => new external_value(PARAM_RAW_TRIMMED, 'Custom range end Y-m-d', VALUE_DEFAULT, ''),
            'course' => new external_value(PARAM_INT, 'Course of the organisation, 0 for all', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Build the section.
     *
     * @param int $org
     * @param string $section
     * @param string $range
     * @param string $from
     * @param string $to
     * @param int $course
     * @return array
     */
    public static function execute(int $org, string $section, string $range, string $from = '', string $to = '',
            int $course = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('org', 'section', 'range', 'from', 'to',
            'course'));
        $metrics = dashboard::metrics($params['org'], $params['range'], $params['from'], $params['to'], $params['course']);
        self::validate_context($metrics->org->context);
        $section = dashboard::section($metrics, $params['section']);
        $data = dashboard::guard(fn() => $section->get_data(), 'section ' . $params['section']);
        // What the dashboard controls must offer for this organisation, so switching it needs no page reload.
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
            'json' => new external_value(PARAM_RAW, 'Section data as JSON'),
        ]);
    }
}

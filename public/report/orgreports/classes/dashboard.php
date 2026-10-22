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

namespace report_orgreports;

use report_orgreports\local\metrics;
use report_orgreports\local\sections\section;
use report_orgreports\local\tables\table;
use report_orgreports\local\tables\table_request;

/**
 * Entry point shared by the page, the web services and the downloads: every request resolves the organisation from
 * the logged-in user and checks the section or table against that user's capabilities here.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard {

    /**
     * Organisation, period and metrics for a request.
     *
     * @param int|null $orgid
     * @param string $range
     * @param string $from
     * @param string $to
     * @param int $courseid narrow the organisation to this course, 0 for all its courses
     * @return metrics
     */
    public static function metrics(?int $orgid, string $range, string $from, string $to, int $courseid = 0): metrics {
        $org = org_context::for_user($orgid, 0, $courseid);
        return new metrics($org, daterange::from_values($range, $from, $to));
    }

    /**
     * Run report code for a web service: permission and parameter errors pass through, anything else (database
     * errors, PHP errors) is written to the server error log with its details and replaced by a generic message,
     * so no SQL or internals reach the browser.
     *
     * @param callable $fn
     * @param string $what what was being loaded, for the log
     * @return mixed
     */
    public static function guard(callable $fn, string $what) {
        global $USER;
        try {
            return $fn();
        } catch (\required_capability_exception | \require_login_exception | \invalid_parameter_exception $e) {
            throw $e;
        } catch (\moodle_exception $e) {
            if ($e->errorcode === 'noaccess' && $e->module === 'report_orgreports') {
                throw $e;
            }
            self::log_failure($e, $what, (int)$USER->id);
        } catch (\Throwable $e) {
            self::log_failure($e, $what, (int)$USER->id);
        }
        throw new \moodle_exception('loadfailed', 'report_orgreports');
    }

    /**
     * Write a failure to the server error log.
     *
     * @param \Throwable $e
     * @param string $what
     * @param int $userid
     */
    private static function log_failure(\Throwable $e, string $what, int $userid): void {
        $debuginfo = $e instanceof \moodle_exception ? (string)$e->debuginfo : '';
        error_log('report_orgreports: ' . $what . ' failed for user ' . $userid . ': ' . get_class($e) . ': ' .
            $e->getMessage() . ($debuginfo !== '' ? ' [' . $debuginfo . ']' : '') . ' at ' . $e->getFile() . ':' .
            $e->getLine());
        debugging('report_orgreports: ' . $what . ' failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    /**
     * Sections the user may open.
     *
     * @param org_context $org
     * @return string[] key => name
     */
    public static function get_sections(org_context $org): array {
        $result = [];
        foreach (section::ALL as $key) {
            $class = section::class_for($key);
            if ($class::is_allowed($org)) {
                $result[$key] = get_string('section_' . $key, 'report_orgreports');
            }
        }
        return $result;
    }

    /**
     * What the user may do in an organisation: sections, course filter options and downloads.
     *
     * @param org_context $org
     * @return array
     */
    public static function get_access(org_context $org): array {
        $sections = [];
        foreach (self::get_sections($org) as $key => $name) {
            $sections[] = ['key' => $key, 'name' => $name];
        }
        $list = fn(array $courses) => array_values(array_map(fn($course) => ['id' => (int)$course->id,
            'name' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)])],
            $courses));
        // Courses: the table filters (only the selected course when narrowed); orgcourses: the dashboard course filter.
        return ['sections' => $sections, 'courses' => $list($org->get_courses()), 'orgcourses' => $list($org->get_org_courses()),
            'course' => $org->courseid, 'canexport' => $org->canexport, 'cansales' => $org->cansales,
            'orglevel' => $org->orglevel, 'org' => $org->id, 'orgname' => $org->name];
    }

    /**
     * Filter options of the Payment Reports page: only values that occur in, or are supported by, the
     * organisation's own data, with the values currently applied.
     *
     * @param local\payment_query $query
     * @return array
     */
    public static function payment_filter_options(local\payment_query $query): array {
        $list = fn(array $options) => array_map(fn($v, $l) => ['value' => (string)$v, 'label' => $l], array_keys($options),
            $options);
        $f = $query->filters;
        return [
            'statuses' => $list(local\payment_query::get_statuses()),
            'methods' => $list($query->get_methods()),
            'courses' => $list($query->get_courses()),
            'plans' => $list($query->get_plans()),
            'applied' => ['status' => $f->status, 'method' => $f->method, 'courseid' => $f->courseid,
                'planid' => $f->planid, 'search' => $f->search],
        ];
    }

    /**
     * A section the user may open.
     *
     * @param metrics $metrics
     * @param string $key
     * @return section
     */
    public static function section(metrics $metrics, string $key): section {
        if (!isset(self::get_sections($metrics->org)[$key])) {
            throw new \required_capability_exception($metrics->org->context, 'report/orgreports:viewsales', 'nopermissions',
                '');
        }
        $class = section::class_for($key);
        return new $class($metrics);
    }

    /**
     * A table the user may see.
     *
     * @param metrics $metrics
     * @param string $key
     * @param table_request $request
     * @return table
     */
    public static function table(metrics $metrics, string $key, table_request $request): table {
        if (!in_array($key, table::ALL, true)) {
            throw new \invalid_parameter_exception('Unknown table');
        }
        $class = table::class_for($key);
        if (!$class::is_allowed($metrics->org)) {
            throw new \required_capability_exception($metrics->org->context, 'report/orgreports:viewsales', 'nopermissions',
                '');
        }
        return new $class($metrics, $request);
    }
}

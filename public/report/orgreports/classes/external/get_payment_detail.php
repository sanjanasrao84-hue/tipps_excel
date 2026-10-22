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
use report_orgreports\local\payment_detail;
use report_orgreports\org_context;

/**
 * Web service: everything recorded about one transaction of an organisation.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_payment_detail extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'org' => new external_value(PARAM_INT, 'Organisation id, 0 for all organisations'),
            'txid' => new external_value(PARAM_ALPHANUMEXT, 'Transaction id, e.g. PAY-5'),
        ]);
    }

    /**
     * Find the transaction.
     *
     * @param int $org
     * @param string $txid
     * @return array
     */
    public static function execute(int $org, string $txid): array {
        $p = self::validate_parameters(self::execute_parameters(), compact('org', 'txid'));
        $context = org_context::for_user($p['org']);
        self::validate_context($context->context);
        if (!$context->cansales) {
            throw new \required_capability_exception($context->context, 'report/orgreports:viewsales', 'nopermissions', '');
        }
        $detail = dashboard::guard(fn() => payment_detail::get($context, $p['txid']), 'payment detail');
        if ($detail === null) {
            // Unknown, or a transaction of another organisation: the same answer for both.
            throw new \moodle_exception('transactionnotfound', 'report_orgreports');
        }
        return ['json' => json_encode($detail)];
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'json' => new external_value(PARAM_RAW, 'Transaction detail as JSON'),
        ]);
    }
}

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
 * Saves the UTR of a UPI payment, for an admin to approve.
 *
 * @package    paygw_razorpay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_razorpay\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use paygw_razorpay\upi_manager;

/**
 * Saves the UTR of a UPI payment, for an admin to approve.
 *
 * @package    paygw_razorpay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_upi_payment extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_ALPHANUM, 'Reference returned by get_upi_details'),
            'utr' => new external_value(PARAM_TEXT, 'UTR / UPI transaction number from the UPI app'),
        ]);
    }

    /**
     * Saves the UTR.
     *
     * @param string $reference
     * @param string $utr
     * @return array
     */
    public static function execute(string $reference, string $utr): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), ['reference' => $reference, 'utr' => $utr]);
        self::validate_context(\context_system::instance());

        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('Payments require a logged in user');
        }
        [$success, $message] = upi_manager::submit($reference, $utr, (int) $USER->id);
        return ['success' => $success, 'message' => $message];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the UTR was saved'),
            'message' => new external_value(PARAM_TEXT, 'What to tell the learner'),
        ]);
    }
}

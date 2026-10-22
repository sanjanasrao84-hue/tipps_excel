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
 * Returns what the UPI / QR code option of the checkout shows.
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
 * Returns what the UPI / QR code option of the checkout shows.
 *
 * @package    paygw_razorpay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_upi_details extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'An identifier for payment area in the component'),
        ]);
    }

    /**
     * Returns our UPI ID and a QR code with the amount, or the payment already waiting for approval.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ]);
        self::validate_context(\context_system::instance());

        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('Payments require a logged in user');
        }
        return upi_manager::get_details($component, $paymentarea, $itemid, (int) $USER->id);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Whether the account takes UPI payments to its own UPI ID'),
            'pending' => new external_value(PARAM_BOOL, 'A payment was already sent and waits for approval'),
            'vpa' => new external_value(PARAM_TEXT, 'UPI ID to pay'),
            'payee' => new external_value(PARAM_TEXT, 'Name of the UPI ID holder'),
            'amount' => new external_value(PARAM_INT, 'Amount in paise'),
            'currency' => new external_value(PARAM_ALPHA, 'Currency'),
            'reference' => new external_value(PARAM_ALPHANUM, 'Reference to put in the payment note'),
            'link' => new external_value(PARAM_RAW, 'upi:// link that opens a UPI app'),
            'qrcode' => new external_value(PARAM_RAW, 'QR code image (data URI)'),
            'utr' => new external_value(PARAM_ALPHANUM, 'UTR of the payment waiting for approval'),
            'timesubmitted' => new external_value(PARAM_INT, 'When the waiting payment was sent'),
        ]);
    }
}

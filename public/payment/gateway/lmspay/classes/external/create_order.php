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

namespace paygw_lmspay\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use paygw_lmspay\order_manager;

/**
 * Starts a checkout and returns what it shows.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_order extends external_api {

    /**
     * Parameters.
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
     * Creates the order.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(),
            ['component' => $component, 'paymentarea' => $paymentarea, 'itemid' => $itemid]);
        self::validate_context(\context_system::instance());
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('Payments require a logged in user');
        }
        return order_manager::create($component, $paymentarea, $itemid, (int) $USER->id);
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reference' => new external_value(PARAM_TEXT, 'Order reference'),
            'amount' => new external_value(PARAM_INT, 'Amount in paise'),
            'currency' => new external_value(PARAM_ALPHA, 'Currency'),
            'brand' => new external_value(PARAM_TEXT, 'Name of the institute being paid'),
            'description' => new external_value(PARAM_TEXT, 'What is being paid for'),
            'methods' => new external_multiple_structure(new external_value(PARAM_ALPHA, 'Payment method')),
            'qrcode' => new external_value(PARAM_RAW, 'Model checkout: UPI QR code image (data URI)'),
            'upidirect' => new external_value(PARAM_BOOL, 'UPI is paid straight to the account UPI ID, then approved by an admin'),
            'upivpa' => new external_value(PARAM_TEXT, 'The account UPI ID, for direct UPI'),
            'upilink' => new external_value(PARAM_RAW, 'upi://pay link for direct UPI'),
            'upiflows' => new external_multiple_structure(new external_value(PARAM_ALPHA, 'Razorpay UPI flow: qr, collect or intent')),
            'provider' => new external_value(PARAM_ALPHA, 'model or razorpay'),
            'key' => new external_value(PARAM_ALPHANUMEXT, 'Razorpay key id'),
            'rporderid' => new external_value(PARAM_ALPHANUMEXT, 'Razorpay order id'),
            'prefill' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Learner name'),
                'email' => new external_value(PARAM_RAW_TRIMMED, 'Learner email'),
                'contact' => new external_value(PARAM_RAW_TRIMMED, 'Learner phone'),
            ]),
            'banks' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Bank code'),
                'name' => new external_value(PARAM_TEXT, 'Bank name'),
            ])),
            'wallets' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Wallet code'),
                'name' => new external_value(PARAM_TEXT, 'Wallet name'),
            ])),
        ]);
    }
}

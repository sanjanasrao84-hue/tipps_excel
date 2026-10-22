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
use core_external\external_single_structure;
use core_external\external_value;
use paygw_lmspay\order_manager;

/**
 * Razorpay payments of the Online payment checkout: completing a payment, and the UPI QR code.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class razorpay_payment extends external_api {

    /**
     * Checks the learner may pay.
     */
    protected static function check_user(): void {
        self::validate_context(\context_system::instance());
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('Payments require a logged in user');
        }
    }

    /**
     * Parameters of complete.
     *
     * @return external_function_parameters
     */
    public static function complete_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_TEXT, 'Order reference'),
            'paymentid' => new external_value(PARAM_ALPHANUMEXT, 'razorpay_payment_id'),
            'signature' => new external_value(PARAM_ALPHANUM, 'razorpay_signature'),
        ]);
    }

    /**
     * Completes an order with Razorpay's proof of payment.
     *
     * @param string $reference
     * @param string $paymentid
     * @param string $signature
     * @return array
     */
    public static function complete(string $reference, string $paymentid, string $signature): array {
        global $USER;
        $params = self::validate_parameters(self::complete_parameters(),
            ['reference' => $reference, 'paymentid' => $paymentid, 'signature' => $signature]);
        self::check_user();
        [$success, $message] = order_manager::complete_razorpay($params['reference'], (int) $USER->id,
            $params['paymentid'], $params['signature']);
        return ['success' => $success, 'message' => $message];
    }

    /**
     * Return value of complete.
     *
     * @return external_single_structure
     */
    public static function complete_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the payment was recorded'),
            'message' => new external_value(PARAM_TEXT, 'What to tell the learner'),
        ]);
    }

    /**
     * Parameters of create_qr and check_qr.
     *
     * @return external_function_parameters
     */
    public static function create_qr_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_TEXT, 'Order reference'),
        ]);
    }

    /**
     * Creates a Razorpay UPI QR code for the order.
     *
     * @param string $reference
     * @return array
     */
    public static function create_qr(string $reference): array {
        global $USER;
        $params = self::validate_parameters(self::create_qr_parameters(), ['reference' => $reference]);
        self::check_user();
        try {
            [$success, $message, $image, $closeby] = order_manager::create_qr($params['reference'], (int) $USER->id);
        } catch (\moodle_exception $e) {
            [$success, $message, $image, $closeby] = [false, $e->getMessage(), '', 0];
        }
        return ['success' => $success, 'message' => $message, 'image' => $image, 'closeby' => $closeby];
    }

    /**
     * Return value of create_qr.
     *
     * @return external_single_structure
     */
    public static function create_qr_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the QR code was created'),
            'message' => new external_value(PARAM_TEXT, 'Error to show'),
            'image' => new external_value(PARAM_URL, 'QR code image hosted by Razorpay'),
            'closeby' => new external_value(PARAM_INT, 'When the QR code stops working'),
        ]);
    }

    /**
     * Parameters of check_qr.
     *
     * @return external_function_parameters
     */
    public static function check_qr_parameters(): external_function_parameters {
        return self::create_qr_parameters();
    }

    /**
     * Whether the QR code has been paid; completes the order when it has.
     *
     * @param string $reference
     * @return array
     */
    public static function check_qr(string $reference): array {
        global $USER;
        $params = self::validate_parameters(self::check_qr_parameters(), ['reference' => $reference]);
        self::check_user();
        try {
            [$paid, $message] = order_manager::check_qr_payment($params['reference'], (int) $USER->id);
        } catch (\moodle_exception $e) {
            // Razorpay unreachable for a moment: the checkout asks again.
            [$paid, $message] = [false, ''];
        }
        return ['paid' => $paid, 'message' => $message];
    }

    /**
     * Return value of check_qr.
     *
     * @return external_single_structure
     */
    public static function check_qr_returns(): external_single_structure {
        return new external_single_structure([
            'paid' => new external_value(PARAM_BOOL, 'Whether the order is paid'),
            'message' => new external_value(PARAM_TEXT, 'What to tell the learner'),
        ]);
    }

    /**
     * Parameters of submit_utr.
     *
     * @return external_function_parameters
     */
    public static function submit_utr_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_TEXT, 'Order reference'),
            'utr' => new external_value(PARAM_TEXT, 'UPI transaction reference (UTR), 12 digits'),
        ]);
    }

    /**
     * Records the UTR of a UPI payment to the account's UPI ID, for an admin to approve.
     *
     * @param string $reference
     * @param string $utr
     * @return array
     */
    public static function submit_utr(string $reference, string $utr): array {
        global $USER;
        $params = self::validate_parameters(self::submit_utr_parameters(), ['reference' => $reference, 'utr' => $utr]);
        self::check_user();
        [$success, $message] = order_manager::submit_utr($params['reference'], (int) $USER->id, $params['utr']);
        return ['success' => $success, 'message' => $message];
    }

    /**
     * Return value of submit_utr.
     *
     * @return external_single_structure
     */
    public static function submit_utr_returns(): external_single_structure {
        return self::complete_returns();
    }
}

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
 * This class contains a list of webservice functions related to the razorpay payment gateway.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_razorpay\external;

use core_payment\helper;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_payment\helper as payment_helper;
use paygw_razorpay\event\payment_created;
use paygw_razorpay\expiry_manager;
use paygw_razorpay\razorpay_helper;

/**
 * webservice for transaction complete operation
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transaction_complete extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'The component name'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'The item id in the context of the component area'),
            'orderid' => new external_value(PARAM_TEXT, 'The order id coming back from razorpay'),
            'paymentid' => new external_value(PARAM_TEXT, 'The payment id coming back from razorpay'),
            'signature' => new external_value(PARAM_RAW, 'The signature coming back from razorpay'),
            'method' => new external_value(PARAM_ALPHANUMEXT, 'Payment method picked in the simulated checkout',
                VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param string $component Name of the component that the itemid belongs to
     * @param string $paymentarea
     * @param int $itemid An internal identifier that is used by the component
     * @param string $orderid razorpay order ID
     * @param string $paymentid razorpay payment ID
     * @param string $signature razorpay signature
     * @param string $method payment method picked in the simulated checkout (demo accounts only)
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid,
                                   string $orderid, string $paymentid, string $signature, string $method = ''): array {
        global $USER, $DB;

        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'orderid' => $orderid,
            'paymentid' => $paymentid,
            'signature' => $signature,
            'method' => $method,
        ]);

        // The order must have been created on this server, for this user and this item.
        $order = $DB->get_record('paygw_razorpay_order', ['rp_orderid' => $orderid]);
        if (!$order || !isloggedin() || isguestuser() || (int) $order->userid !== (int) $USER->id
                || $order->component !== $component || $order->paymentarea !== $paymentarea
                || (int) $order->itemid !== $itemid) {
            return ['success' => false, 'message' => get_string('ordermismatch', 'paygw_razorpay')];
        }
        // Never accept the same order or payment twice.
        if ($order->status !== 'created' || $DB->record_exists('paygw_razorpay', ['rp_paymentid' => $paymentid])) {
            return ['success' => false, 'message' => get_string('paymentalreadyprocessed', 'paygw_razorpay')];
        }

        $config = (object)helper::get_gateway_configuration($component, $paymentarea, $itemid, 'razorpay');
        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $razorpayhelper = new razorpay_helper($config->clientid, $config->secret, $config->environment ?? 'live');

        if ($razorpayhelper->is_demo()) {
            // Simulated payment: the token replaces Razorpay's signature, and the details mirror the saved order.
            $demo = $razorpayhelper->demo_payment($order, $paymentid, $signature, $method);
            $verifysignature = $demo !== null;
            [$orderdetails, $capture] = $demo ?? [null, null];
        } else {
            try {
                // Verify the signature, then ask Razorpay what was really paid.
                $verifysignature = $razorpayhelper->verify_signature([
                    'razorpay_signature' => $signature,
                    'razorpay_payment_id' => $paymentid,
                    'razorpay_order_id' => $orderid,
                ]);
                $orderdetails = $verifysignature ? $razorpayhelper->get_order_details($orderid) : null;
                $capture = $orderdetails ? $razorpayhelper->fetch_payment_details($paymentid) : null;
            } catch (\Exception $e) {
                debugging('Razorpay API error: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $verifysignature = false;
                $orderdetails = $capture = null;
            }
        }

        if (!$verifysignature || !$orderdetails || !$capture) {
            // Could not capture authorization!.
            return ['success' => false, 'message' => get_string('cannotfetchorderdatails', 'paygw_razorpay')];
        }

        // The amount paid must be exactly the amount saved when the order was created.
        $paid = $orderdetails['status'] == razorpay_helper::ORDER_STATUS_PAID
            && (int) $orderdetails['amount'] === (int) $order->amount
            && $orderdetails['currency'] === $order->currency
            && $capture['status'] == razorpay_helper::PAYMENT_STATUS_CAPTURED
            && $capture['order_id'] === $orderid
            && (int) $capture['amount'] === (int) $order->amount;
        if (!$paid) {
            return ['success' => false, 'message' => get_string('paymentnotcleared', 'paygw_razorpay')];
        }

        // Everything is correct. Let's give them what they paid for.
        $amount = $order->amount / 100;
        $currency = $order->currency;
        $transaction = null;
        try {
            $transaction = $DB->start_delegated_transaction();

            $paymentid1 = payment_helper::save_payment($payable->get_account_id(), $component, $paymentarea,
                $itemid, (int)$USER->id, $amount, $currency, 'razorpay');

            // Store razorpay extra information. The unique index on rp_paymentid stops a parallel replay.
            $record = new \stdClass();
            $record->paymentid = $paymentid1;
            $record->rp_orderid = $orderid;
            $record->rp_paymentid = $paymentid;
            $record->rp_signature = $signature;
            $DB->insert_record('paygw_razorpay', $record);

            $DB->update_record('paygw_razorpay_order', (object) [
                'id' => $order->id,
                'status' => 'paid',
                'paymentid' => $paymentid1,
                // How the learner paid: upi, card, netbanking, wallet, emi, cardless_emi or paylater.
                'method' => razorpay_helper::clean_method($capture['method'] ?? ''),
                'timemodified' => time(),
            ]);

            // A renewal: remember the enrolment as it was before the order is delivered.
            $isfeeenrolment = $component === 'enrol_fee' && $paymentarea === 'fee';
            $previous = $isfeeenrolment ?
                $DB->get_record('user_enrolments', ['enrolid' => $itemid, 'userid' => $USER->id]) : false;

            payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid1, (int)$USER->id);

            if ($isfeeenrolment) {
                expiry_manager::apply_renewal($itemid, (int)$USER->id, $previous);
            }

            // Trigger the event when payment successful.
            [, $context] = razorpay_helper::describe_item($component, $paymentarea, $itemid);
            $event = payment_created::create([
                'objectid' => $paymentid1,
                'userid' => $USER->id,
                'context' => $context,
                'other' => [
                    'currency' => $currency,
                    'amount' => $amount,
                ],
            ]);
            $event->trigger();

            $transaction->allow_commit();
            $success = true;
            $message = '';
        } catch (\Exception $e) {
            if ($transaction && !$transaction->is_disposed()) {
                try {
                    $transaction->rollback($e);
                } catch (\Exception $rethrown) {
                    // Rollback always rethrows; the error is reported below.
                    unset($rethrown);
                }
            }
            debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $success = false;
            $message = get_string('internalerror', 'paygw_razorpay');
        }

        return [
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_function_parameters
     */
    public static function execute_returns() {
        return new external_function_parameters([
            'success' => new external_value(PARAM_BOOL, 'Whether everything was successful or not.'),
            'message' => new external_value(PARAM_RAW, 'Message (usually the error message).'),
        ]);
    }
}

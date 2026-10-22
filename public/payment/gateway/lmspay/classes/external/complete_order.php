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
 * Completes a checkout with the payment method the learner used.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class complete_order extends external_api {

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_TEXT, 'Order reference from create_order'),
            'method' => new external_value(PARAM_ALPHA, 'upi, qr, card, netbanking, wallet or emi'),
            'detail' => new external_single_structure([
                'value' => new external_value(PARAM_TEXT, 'UPI app or ID, last 4 card digits, bank or wallet code',
                    VALUE_DEFAULT, ''),
                'network' => new external_value(PARAM_ALPHA, 'Card network', VALUE_DEFAULT, ''),
                'months' => new external_value(PARAM_INT, 'EMI tenure', VALUE_DEFAULT, 0),
            ]),
        ]);
    }

    /**
     * Completes the order.
     *
     * @param string $reference
     * @param string $method
     * @param array $detail
     * @return array
     */
    public static function execute(string $reference, string $method, array $detail): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            ['reference' => $reference, 'method' => $method, 'detail' => $detail]);
        self::validate_context(\context_system::instance());
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('Payments require a logged in user');
        }
        [$success, $message] = order_manager::complete($params['reference'], (int) $USER->id, $params['method'],
            $params['detail']);
        return ['success' => $success, 'message' => $message];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the payment was recorded'),
            'message' => new external_value(PARAM_TEXT, 'What to tell the learner'),
        ]);
    }
}

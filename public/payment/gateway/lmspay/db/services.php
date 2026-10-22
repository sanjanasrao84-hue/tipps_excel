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
 * Web services of the Online payment gateway.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_lmspay_create_order' => [
        'classname' => 'paygw_lmspay\external\create_order',
        'description' => 'Starts a checkout and returns the amount and payment methods.',
        'type' => 'write',
        'ajax' => true,
    ],
    'paygw_lmspay_complete_order' => [
        'classname' => 'paygw_lmspay\external\complete_order',
        'description' => 'Completes a checkout with the payment method the learner used.',
        'type' => 'write',
        'ajax' => true,
    ],
    'paygw_lmspay_complete_razorpay' => [
        'classname' => 'paygw_lmspay\external\razorpay_payment',
        'methodname' => 'complete',
        'description' => 'Completes a checkout paid through Razorpay, after checking the payment with Razorpay.',
        'type' => 'write',
        'ajax' => true,
    ],
    'paygw_lmspay_create_qr' => [
        'classname' => 'paygw_lmspay\external\razorpay_payment',
        'methodname' => 'create_qr',
        'description' => 'Creates a Razorpay UPI QR code for exactly the order amount.',
        'type' => 'write',
        'ajax' => true,
    ],
    'paygw_lmspay_submit_utr' => [
        'classname' => 'paygw_lmspay\external\razorpay_payment',
        'methodname' => 'submit_utr',
        'description' => 'Sends the UTR of a UPI payment to the account UPI ID, for an admin to approve.',
        'type' => 'write',
        'ajax' => true,
    ],
    'paygw_lmspay_check_qr' => [
        'classname' => 'paygw_lmspay\external\razorpay_payment',
        'methodname' => 'check_qr',
        'description' => 'Checks whether the order QR code was paid, and completes the order if so.',
        'type' => 'write',
        'ajax' => true,
    ],
];

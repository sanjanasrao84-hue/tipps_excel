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
 * Settings of the Online payment gateway.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_lmspay');
}

// UPI payments to the account's own UPI ID wait here for an admin to approve them.
$ADMIN->add('paymentgateways', new admin_externalpage('paygw_lmspay_upipayments',
    new lang_string('upipayments', 'paygw_lmspay'),
    new moodle_url('/payment/gateway/lmspay/upi_payments.php'), 'moodle/payment:manageaccounts'));

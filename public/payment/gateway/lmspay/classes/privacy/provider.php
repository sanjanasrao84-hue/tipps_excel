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

namespace paygw_lmspay\privacy;

use core_payment\privacy\paygw_provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for paygw_lmspay.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\data_provider,
    paygw_provider {

    /**
     * Returns metadata about this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('paygw_lmspay_order', [
            'userid' => 'privacy:metadata:order:userid',
            'amount' => 'privacy:metadata:order:amount',
            'method' => 'privacy:metadata:order:method',
            'methoddetail' => 'privacy:metadata:order:methoddetail',
            'utr' => 'privacy:metadata:order:utr',
            'timecreated' => 'privacy:metadata:order:timecreated',
        ], 'privacy:metadata:order');
        return $collection;
    }

    /**
     * Export all user data for the specified payment record, and the given context.
     *
     * @param \context $context
     * @param array $subcontext
     * @param \stdClass $payment
     */
    public static function export_payment_data(\context $context, array $subcontext, \stdClass $payment) {
        global $DB;
        $order = $DB->get_record('paygw_lmspay_order', ['paymentid' => $payment->id]);
        if ($order) {
            $subcontext[] = get_string('gatewayname', 'paygw_lmspay');
            writer::with_context($context)->export_data($subcontext, (object) [
                'reference' => $order->reference,
                'method' => $order->method,
                'methoddetail' => $order->methoddetail,
                'utr' => $order->utr,
            ]);
        }
    }

    /**
     * Delete all user data related to the given payments.
     *
     * @param string $paymentsql
     * @param array $paymentparams
     */
    public static function delete_data_for_payment_sql(string $paymentsql, array $paymentparams) {
        global $DB;
        $DB->delete_records_select('paygw_lmspay_order', "paymentid IN ({$paymentsql})", $paymentparams);
    }
}

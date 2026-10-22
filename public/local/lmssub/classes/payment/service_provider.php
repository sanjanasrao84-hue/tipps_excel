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

namespace local_lmssub\payment;

use local_lmssub\plan_manager;
use local_lmssub\subscription_manager;

/**
 * Payment callbacks: lets plans be paid through the existing Razorpay checkout.
 *
 * Payment area "plan"; the item id is the duration option (local_lmssub_price.id) being bought.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_provider implements \core_payment\local\callback\service_provider {

    /** @var string The only payment area. */
    const AREA = 'plan';

    /**
     * Amount, currency and payment account of a duration option.
     *
     * Only active options of active plans, of a type this site has switched on, can be bought.
     *
     * @param string $paymentarea
     * @param int $priceid
     * @return \core_payment\local\entities\payable
     */
    public static function get_payable(string $paymentarea, int $priceid): \core_payment\local\entities\payable {
        [$price, $plan] = self::get_purchasable($priceid);
        return new \core_payment\local\entities\payable($price->amount / 100, $price->currency,
            plan_manager::get_payment_account_id());
    }

    /**
     * Where the learner lands after paying.
     *
     * @param string $paymentarea
     * @param int $priceid
     * @return \moodle_url
     */
    public static function get_success_url(string $paymentarea, int $priceid): \moodle_url {
        return new \moodle_url('/local/lmssub/my.php', ['paid' => $priceid]);
    }

    /**
     * Creates or extends the subscription that was paid for.
     *
     * The payment has already been verified by the gateway. A plan switched off after the learner paid
     * is still delivered: they paid for it.
     *
     * @param string $paymentarea
     * @param int $priceid
     * @param int $paymentid
     * @param int $userid
     * @return bool
     */
    public static function deliver_order(string $paymentarea, int $priceid, int $paymentid, int $userid): bool {
        global $DB;

        $price = $DB->get_record('local_lmssub_price', ['id' => $priceid], '*', MUST_EXIST);
        $payment = $DB->get_record('payments', ['id' => $paymentid], '*', MUST_EXIST);

        $sub = subscription_manager::grant($userid, (int) $price->planid, (int) $price->durationdays,
            get_string('paidreason', 'local_lmssub', $paymentid), (int) $price->id, 'payment', $userid);

        $now = time();
        $DB->insert_record('local_lmssub_order', (object) [
            'userid' => $userid,
            'planid' => $price->planid,
            'priceid' => $price->id,
            'subid' => $sub->id,
            'amount' => (int) round($payment->amount * 100),
            'currency' => $payment->currency,
            'status' => 'paid',
            'paymentid' => $paymentid,
            'rp_orderid' => $DB->get_field('paygw_razorpay', 'rp_orderid', ['paymentid' => $paymentid]) ?: null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return true;
    }

    /**
     * Name and context shown by the payment gateway for an item.
     *
     * @param string $paymentarea
     * @param int $priceid
     * @return array [string name, \context context]
     */
    public static function get_item_description(string $paymentarea, int $priceid): array {
        global $DB;
        $price = $DB->get_record('local_lmssub_price', ['id' => $priceid], '*', MUST_EXIST);
        $plan = plan_manager::get_plan((int) $price->planid);
        return [format_string($plan->name) . ' - ' . plan_manager::duration_label((int) $price->durationdays),
            \context_system::instance()];
    }

    /**
     * Returns a duration option and its plan, if they can be bought now.
     *
     * @param int $priceid
     * @return array [price, plan]
     * @throws \moodle_exception when not purchasable
     */
    public static function get_purchasable(int $priceid): array {
        global $DB;

        $price = $DB->get_record('local_lmssub_price', ['id' => $priceid, 'active' => 1]);
        $plan = $price ? $DB->get_record('local_lmssub_plan', ['id' => $price->planid, 'active' => 1]) : null;
        if (!$price || !$plan || !isset(plan_manager::get_enabled_types()[$plan->type])) {
            throw new \moodle_exception('plannotavailable', 'local_lmssub');
        }
        if (!plan_manager::get_payment_account_id()) {
            throw new \moodle_exception('nopaymentaccount', 'local_lmssub');
        }
        return [$price, $plan];
    }
}

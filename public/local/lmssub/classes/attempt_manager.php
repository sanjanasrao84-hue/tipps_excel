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

namespace local_lmssub;

use local_lmssub\subscription_manager as subs;

/**
 * Payment attempts on subscription plans, as recorded by the payment gateways.
 *
 * Every checkout started for a plan is saved by the gateway (paygw_lmspay_order, paygw_razorpay_order and
 * paygw_razorpay_upi), paid or not. This class reads them, so admins can see who tried to pay for the plans
 * covering a course and turn a student into a subscriber. The gateway records are never changed.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_manager {

    /** @var array Gateway tables: source => [table, reference field, method field]. */
    const SOURCES = [
        'lmspay' => ['paygw_lmspay_order', 'reference', 'method'],
        'razorpay' => ['paygw_razorpay_order', 'rp_orderid', 'method'],
        'razorpayupi' => ['paygw_razorpay_upi', 'reference', null],
    ];

    /** @var string[] Statuses of a completed payment. */
    const PAID_STATUSES = ['paid', 'approved'];

    /** @var string[] Statuses of a UPI payment waiting for an admin's review in the gateway. */
    const REVIEW_STATUSES = ['pending'];

    /** @var string Source of the attempts recorded by the checkout page ("Proceed to payment" clicked). */
    const SOURCE_CHECKOUT = 'checkout';

    /** @var int Clicks on "Proceed to payment" for the same price within this time count as one attempt. */
    const CHECKOUT_MERGE_SECONDS = 600;

    /**
     * Records that the learner clicked "Proceed to payment" for a price: an unpaid order.
     *
     * @param int $userid
     * @param int $priceid
     * @return \stdClass the order
     */
    public static function record_checkout_attempt(int $userid, int $priceid): \stdClass {
        global $DB;

        // Same checks as the checkout page itself.
        [$price, $plan] = payment\service_provider::get_purchasable($priceid);
        $now = time();

        // Several clicks in a row (closing and reopening the payment window) are one attempt.
        $recent = $DB->get_records_select('local_lmssub_order',
            'userid = :userid AND priceid = :priceid AND status = :status AND paymentid IS NULL AND timemodified > :since',
            ['userid' => $userid, 'priceid' => $price->id, 'status' => 'created', 'since' => $now - self::CHECKOUT_MERGE_SECONDS],
            'timemodified DESC', '*', 0, 1);
        if ($order = reset($recent)) {
            $DB->set_field('local_lmssub_order', 'timemodified', $now, ['id' => $order->id]);
            $order->timemodified = $now;
            return $order;
        }

        $order = (object) [
            'userid' => $userid,
            'planid' => (int) $plan->id,
            'priceid' => (int) $price->id,
            'amount' => (int) $price->amount,
            'currency' => $price->currency,
            'status' => 'created',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $order->id = $DB->insert_record('local_lmssub_order', $order);
        return $order;
    }

    /**
     * Attempts recorded by the checkout page. An attempt counts as paid once the same learner paid for the same
     * price afterwards (the paid order is written by the payment delivery).
     *
     * @param array|null $planids
     * @param bool $openonly
     * @return \stdClass[]
     */
    protected static function get_checkout_attempts(?array $planids, bool $openonly): array {
        global $DB;

        $paid = "EXISTS (SELECT 1 FROM {local_lmssub_order} p
                          WHERE p.userid = o.userid AND p.priceid = o.priceid AND p.status = 'paid'
                                AND p.timecreated >= o.timecreated)";
        $where = "o.status = 'created' AND o.paymentid IS NULL";
        $params = [];
        if ($planids !== null) {
            [$insql, $params] = $DB->get_in_or_equal($planids, SQL_PARAMS_NAMED);
            $where .= " AND o.planid $insql";
        }
        if ($openonly) {
            $where .= " AND NOT $paid";
        }
        $sql = "SELECT o.id, o.userid, o.priceid, o.planid, pr.durationdays, o.amount, o.currency,
                       CASE WHEN $paid THEN 'paid' ELSE 'created' END AS status,
                       '' AS reference, '' AS method, o.timecreated, o.timemodified
                  FROM {local_lmssub_order} o
                  JOIN {local_lmssub_price} pr ON pr.id = o.priceid
                 WHERE $where";
        $attempts = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $record->source = self::SOURCE_CHECKOUT;
            $attempts[] = $record;
        }
        return $attempts;
    }

    /**
     * Payment attempts on plans, newest first.
     *
     * @param int $courseid only the plans that open this course (0 = all plans)
     * @param bool $openonly only attempts that were not paid
     * @return \stdClass[] source, id, userid, priceid, planid, durationdays, amount (paise), currency, status, reference,
     *     method, timecreated, timemodified
     */
    public static function get_attempts(int $courseid = 0, bool $openonly = true): array {
        global $DB;

        $planids = $courseid ? plan_manager::plans_covering_course($courseid) : null;
        if ($planids === []) {
            return [];
        }

        $dbman = $DB->get_manager();
        $attempts = self::get_checkout_attempts($planids, $openonly);
        foreach (self::SOURCES as $source => [$table, $reffield, $methodfield]) {
            if (!$dbman->table_exists($table)) {
                continue;
            }
            $method = $methodfield ? "o.{$methodfield}" : "'upi'";
            $timecreated = $table === 'paygw_razorpay_upi' ? 'COALESCE(o.timesubmitted, o.timecreated)' : 'o.timecreated';
            $where = "o.component = :component AND o.paymentarea = :area";
            $params = ['component' => 'local_lmssub', 'area' => payment\service_provider::AREA];
            if ($planids !== null) {
                [$insql, $inparams] = $DB->get_in_or_equal($planids, SQL_PARAMS_NAMED);
                $where .= " AND pr.planid $insql";
                $params += $inparams;
            }
            if ($openonly) {
                [$notpaid, $paidparams] = $DB->get_in_or_equal(self::PAID_STATUSES, SQL_PARAMS_NAMED, 'paid', false);
                $where .= " AND o.status $notpaid";
                $params += $paidparams;
            }
            $sql = "SELECT o.id, o.userid, o.itemid AS priceid, pr.planid, pr.durationdays, o.amount, o.currency, o.status,
                           o.{$reffield} AS reference, {$method} AS method, {$timecreated} AS timecreated, o.timemodified
                      FROM {{$table}} o
                      JOIN {local_lmssub_price} pr ON pr.id = o.itemid
                     WHERE $where";
            foreach ($DB->get_records_sql($sql, $params) as $record) {
                $record->source = $source;
                $attempts[] = $record;
            }
        }

        usort($attempts, fn($a, $b) => $b->timecreated <=> $a->timecreated);
        return $attempts;
    }

    /**
     * One payment attempt.
     *
     * @param string $source
     * @param int $id
     * @return \stdClass
     */
    public static function get_attempt(string $source, int $id): \stdClass {
        global $DB;

        if ($source === self::SOURCE_CHECKOUT) {
            $order = $DB->get_record('local_lmssub_order', ['id' => $id, 'status' => 'created'], '*', MUST_EXIST);
            foreach (self::get_checkout_attempts([(int) $order->planid], false) as $attempt) {
                if ((int) $attempt->id === (int) $order->id) {
                    foreach (['id', 'userid', 'priceid', 'planid', 'durationdays', 'amount', 'timecreated', 'timemodified'] as $field) {
                        $attempt->$field = (int) $attempt->$field;
                    }
                    return $attempt;
                }
            }
            throw new \moodle_exception('invalidrecord', 'error');
        }
        if (!isset(self::SOURCES[$source])) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        [$table, $reffield, $methodfield] = self::SOURCES[$source];
        $record = $DB->get_record($table, ['id' => $id, 'component' => 'local_lmssub',
            'paymentarea' => payment\service_provider::AREA], '*', MUST_EXIST);
        $price = $DB->get_record('local_lmssub_price', ['id' => $record->itemid], '*', MUST_EXIST);

        return (object) [
            'source' => $source,
            'id' => (int) $record->id,
            'userid' => (int) $record->userid,
            'priceid' => (int) $price->id,
            'planid' => (int) $price->planid,
            'durationdays' => (int) $price->durationdays,
            'amount' => (int) $record->amount,
            'currency' => $record->currency,
            'status' => $record->status,
            'reference' => $record->$reffield,
            'method' => $methodfield ? $record->$methodfield : 'upi',
            'timecreated' => (int) ($record->timesubmitted ?? 0) ?: (int) $record->timecreated,
            'timemodified' => (int) $record->timemodified,
        ];
    }

    /**
     * Groups attempts by student and plan duration: one row per thing a student tried to buy.
     *
     * @param \stdClass[] $attempts newest first
     * @return \stdClass[] userid, planid, priceid, durationdays, count, latest (attempt), first (time)
     */
    public static function group(array $attempts): array {
        $groups = [];
        foreach ($attempts as $attempt) {
            $key = "{$attempt->userid}-{$attempt->priceid}";
            if (!isset($groups[$key])) {
                $groups[$key] = (object) [
                    'userid' => (int) $attempt->userid,
                    'planid' => (int) $attempt->planid,
                    'priceid' => (int) $attempt->priceid,
                    'durationdays' => (int) $attempt->durationdays,
                    'count' => 0,
                    'latest' => $attempt,
                    'first' => (int) $attempt->timecreated,
                ];
            }
            $groups[$key]->count++;
            $groups[$key]->first = min($groups[$key]->first, (int) $attempt->timecreated);
        }

        return array_values($groups);
    }

    /**
     * Template rows (local_lmssub/attempts_table) for grouped attempts.
     *
     * @param \stdClass[] $groups from group()
     * @param \moodle_url $returnurl page to come back to after marking a subscriber
     * @param int $courseid course the page is about, for the user pictures (0 = site)
     * @return array[]
     */
    public static function export_rows(array $groups, \moodle_url $returnurl, int $courseid = 0): array {
        global $OUTPUT;

        $plans = [];
        $rows = [];
        foreach ($groups as $group) {
            $user = \core_user::get_user($group->userid);
            if (!$user || $user->deleted) {
                continue;
            }
            $plans[$group->planid] = $plans[$group->planid] ?? plan_manager::get_plan($group->planid);
            $latest = $group->latest;
            $until = self::subscribed_until($group->userid, $group->planid);
            $markurl = new \moodle_url('/local/lmssub/markattempt.php', ['source' => $latest->source, 'id' => $latest->id,
                'returnurl' => $returnurl->out_as_local_url(false)]);
            $reviewurl = self::review_url($latest);
            $timeformat = get_string('strftimedatetimeshort', 'langconfig');

            $rows[] = [
                'picture' => $OUTPUT->user_picture($user, ['size' => 35, 'courseid' => $courseid ?: SITEID]),
                'fullname' => fullname($user),
                'email' => $user->email,
                'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $user->id]))->out(false),
                'plan' => format_string($plans[$group->planid]->name),
                'duration' => plan_manager::duration_label($group->durationdays),
                'amount' => plan_manager::format_amount((int) $latest->amount, $latest->currency),
                'count' => $group->count,
                'multiple' => $group->count > 1,
                'first' => userdate($group->first, $timeformat),
                'last' => userdate($latest->timecreated, $timeformat),
                'status' => self::status_name($latest->status),
                'statusclass' => in_array($latest->status, self::PAID_STATUSES, true) ? 'success'
                    : (self::awaiting_review($latest) ? 'warning' : 'secondary'),
                'gateway' => get_string('gateway_' . $latest->source, 'local_lmssub'),
                'reference' => $latest->reference,
                'subscribed' => (bool) $until,
                'until' => $until ? userdate($until, get_string('strftimedate', 'langconfig')) : '',
                'markurl' => $markurl->out(false),
                'reviewurl' => $reviewurl ? $reviewurl->out(false) : '',
            ];
        }

        return $rows;
    }

    /**
     * Readable status of an attempt.
     *
     * @param string $status
     * @return string
     */
    public static function status_name(string $status): string {
        $key = 'attemptstatus_' . $status;
        return get_string_manager()->string_exists($key, 'local_lmssub')
            ? get_string($key, 'local_lmssub') : ucfirst($status);
    }

    /**
     * Whether a UPI payment of the attempt is waiting for review in its gateway.
     *
     * @param \stdClass $attempt
     * @return bool
     */
    public static function awaiting_review(\stdClass $attempt): bool {
        return in_array($attempt->status, self::REVIEW_STATUSES, true);
    }

    /**
     * Page where the gateway reviews UPI payments, for an attempt waiting for review.
     *
     * @param \stdClass $attempt
     * @return \moodle_url|null
     */
    public static function review_url(\stdClass $attempt): ?\moodle_url {
        if (!self::awaiting_review($attempt)) {
            return null;
        }
        $path = $attempt->source === 'lmspay' ? '/payment/gateway/lmspay/upi_payments.php' : '/payment/gateway/razorpay/upi_payments.php';
        return new \moodle_url($path);
    }

    /**
     * The student's current access through a plan, or 0.
     *
     * @param int $userid
     * @param int $planid
     * @return int access end time, 0 when not subscribed
     */
    public static function subscribed_until(int $userid, int $planid): int {
        $sub = subs::find_current($userid, $planid);
        if (!$sub) {
            return 0;
        }
        $until = access_engine::effective_end($sub, plan_manager::get_plan($planid));
        return $until > time() ? $until : 0;
    }

    /**
     * Makes the student of an attempt a subscriber of the attempted plan, or extends the current subscription.
     *
     * @param \stdClass $attempt
     * @param int $days
     * @param string $reason
     * @return \stdClass the subscription
     */
    public static function mark_as_subscriber(\stdClass $attempt, int $days, string $reason): \stdClass {
        global $USER;

        return subs::grant($attempt->userid, $attempt->planid, $days, $reason, $attempt->priceid, 'admin', (int) $USER->id);
    }
}

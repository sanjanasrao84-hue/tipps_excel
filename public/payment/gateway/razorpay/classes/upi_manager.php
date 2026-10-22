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
 * UPI payments made straight to the site's own UPI ID.
 *
 * @package    paygw_razorpay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_razorpay;

use core_payment\helper as payment_helper;
use paygw_razorpay\event\payment_created;

/**
 * UPI payments made straight to the site's own UPI ID.
 *
 * The learner scans a QR code (or opens their UPI app), pays, and enters the UTR number. Nothing reaches
 * Razorpay, so the payment cannot be checked automatically: an admin compares the UTR with the bank
 * statement and approves it, and only then is the order delivered.
 *
 * @package    paygw_razorpay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upi_manager {

    /** @var string Created when the learner opens the UPI option, no UTR yet. */
    public const STATUS_NEW = 'new';
    /** @var string UTR entered, waiting for an admin. */
    public const STATUS_PENDING = 'pending';
    /** @var string Approved and delivered. */
    public const STATUS_APPROVED = 'approved';
    /** @var string Rejected by an admin. */
    public const STATUS_REJECTED = 'rejected';

    /**
     * The UPI ID an account takes payments on, or '' when direct UPI is off.
     *
     * @param array $config gateway configuration
     * @return string
     */
    public static function get_vpa(array $config): string {
        $vpa = trim($config['upivpa'] ?? '');
        return gateway::is_valid_vpa($vpa) ? $vpa : '';
    }

    /**
     * Whether a UTR / UPI transaction number looks valid.
     *
     * UPI UTRs (RRNs) are 12 digits; some apps show a longer alphanumeric transaction id instead.
     *
     * @param string $utr
     * @return bool
     */
    public static function is_valid_utr(string $utr): bool {
        return (bool) preg_match('/^[A-Za-z0-9]{10,35}$/', $utr);
    }

    /**
     * The upi:// link that UPI apps open, with the amount and our reference filled in.
     *
     * @param string $vpa
     * @param string $payee
     * @param int $amount paise
     * @param string $currency
     * @param string $reference
     * @return string
     */
    public static function upi_link(string $vpa, string $payee, int $amount, string $currency, string $reference): string {
        // No "tr" parameter: on a personal UPI ID some apps decline payments that carry a merchant reference.
        return 'upi://pay?' . http_build_query([
            'pa' => $vpa,
            'pn' => $payee,
            'am' => sprintf('%.2f', $amount / 100),
            'cu' => $currency,
            'tn' => $reference,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * A QR code for a upi:// link, as an SVG data URI.
     *
     * @param string $link
     * @return string
     */
    public static function qrcode(string $link): string {
        $qrcode = new \core_qrcode($link);
        return 'data:image/svg+xml;base64,' . base64_encode($qrcode->getBarcodeSVGcode(5, 5, 'black'));
    }

    /**
     * What the UPI option of the checkout shows for an item: our UPI ID and a QR code with the amount,
     * or the payment the learner already sent, while an admin checks it.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return array
     */
    public static function get_details(string $component, string $paymentarea, int $itemid, int $userid): array {
        global $DB;

        $config = payment_helper::get_gateway_configuration($component, $paymentarea, $itemid, 'razorpay');
        $vpa = self::get_vpa($config);
        $none = ['enabled' => false, 'pending' => false, 'vpa' => '', 'payee' => '', 'amount' => 0, 'currency' => '',
            'reference' => '', 'link' => '', 'qrcode' => '', 'utr' => '', 'timesubmitted' => 0];
        if ($vpa === '') {
            return $none;
        }
        $payee = trim($config['upiname'] ?? '');

        // A payment already sent for this item is shown instead of asking the learner to pay again.
        $pending = $DB->get_records('paygw_razorpay_upi', ['userid' => $userid, 'component' => $component,
            'paymentarea' => $paymentarea, 'itemid' => $itemid, 'status' => self::STATUS_PENDING], 'timesubmitted DESC', '*', 0, 1);
        if ($pending = reset($pending)) {
            return ['enabled' => true, 'pending' => true, 'vpa' => $pending->vpa, 'payee' => $payee,
                'amount' => (int) $pending->amount, 'currency' => $pending->currency, 'reference' => $pending->reference,
                'link' => '', 'qrcode' => '', 'utr' => $pending->utr, 'timesubmitted' => (int) $pending->timesubmitted];
        }

        // The amount is always calculated on the server. No gateway surcharge: the money goes
        // straight to the bank, so there is no Razorpay fee to cover.
        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $cost = payment_helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), 0);
        $amount = razorpay_helper::to_paise((float) $cost);
        $currency = $payable->get_currency();

        // Reuse today's unpaid request, so reopening the checkout keeps the same reference.
        $existing = $DB->get_records_select('paygw_razorpay_upi',
            'userid = :userid AND component = :component AND paymentarea = :paymentarea AND itemid = :itemid
             AND status = :status AND amount = :amount AND vpa = :vpa AND timecreated > :since',
            ['userid' => $userid, 'component' => $component, 'paymentarea' => $paymentarea, 'itemid' => $itemid,
             'status' => self::STATUS_NEW, 'amount' => $amount, 'vpa' => $vpa, 'since' => time() - DAYSECS],
            'timecreated DESC', '*', 0, 1);
        $request = reset($existing);
        if (!$request) {
            $now = time();
            $request = (object) [
                'userid' => $userid,
                'component' => $component,
                'paymentarea' => $paymentarea,
                'itemid' => $itemid,
                'amount' => $amount,
                'currency' => $currency,
                'reference' => self::new_reference(),
                'vpa' => $vpa,
                'status' => self::STATUS_NEW,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $request->id = $DB->insert_record('paygw_razorpay_upi', $request);
        }

        $link = self::upi_link($vpa, $payee, $amount, $currency, $request->reference);
        return ['enabled' => true, 'pending' => false, 'vpa' => $vpa, 'payee' => $payee, 'amount' => $amount,
            'currency' => $currency, 'reference' => $request->reference, 'link' => $link, 'qrcode' => self::qrcode($link),
            'utr' => '', 'timesubmitted' => 0];
    }

    /**
     * A new, unused payment reference such as LMS7K2Q9XP4.
     *
     * @return string
     */
    protected static function new_reference(): string {
        global $DB;
        do {
            // No 0/O or 1/I, so it can be read back from a bank statement.
            $reference = 'LMS';
            for ($i = 0; $i < 8; $i++) {
                $reference .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while ($DB->record_exists('paygw_razorpay_upi', ['reference' => $reference]));
        return $reference;
    }

    /**
     * Saves the UTR the learner entered and tells the admins.
     *
     * @param string $reference
     * @param string $utr
     * @param int $userid
     * @return array [bool success, string message]
     */
    public static function submit(string $reference, string $utr, int $userid): array {
        global $DB;

        $utr = strtoupper(preg_replace('/\s+/', '', $utr));
        if (!self::is_valid_utr($utr)) {
            return [false, get_string('upiutrinvalid', 'paygw_razorpay')];
        }
        $request = $DB->get_record('paygw_razorpay_upi', ['reference' => $reference, 'userid' => $userid]);
        if (!$request || $request->status !== self::STATUS_NEW) {
            return [false, get_string('upirequestnotfound', 'paygw_razorpay')];
        }
        // The same bank transaction cannot pay twice.
        if ($DB->record_exists_select('paygw_razorpay_upi', 'utr = :utr AND status <> :rejected',
                ['utr' => $utr, 'rejected' => self::STATUS_REJECTED])) {
            return [false, get_string('upiutrused', 'paygw_razorpay')];
        }

        $now = time();
        $DB->update_record('paygw_razorpay_upi', (object) ['id' => $request->id, 'utr' => $utr,
            'status' => self::STATUS_PENDING, 'timesubmitted' => $now, 'timemodified' => $now]);
        $request->utr = $utr;
        self::notify_admins($request);
        return [true, get_string('upisubmitted', 'paygw_razorpay')];
    }

    /**
     * Approves a UPI payment: records it as a payment and gives the learner what they paid for.
     *
     * @param int $id paygw_razorpay_upi id
     * @param int $reviewerid
     * @return bool false when the payment is no longer waiting for approval
     */
    public static function approve(int $id, int $reviewerid): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $request = $DB->get_record('paygw_razorpay_upi', ['id' => $id]);
        if (!$request || $request->status !== self::STATUS_PENDING) {
            $transaction->allow_commit();
            return false;
        }

        $payable = payment_helper::get_payable($request->component, $request->paymentarea, (int) $request->itemid);
        $amount = $request->amount / 100;
        $userid = (int) $request->userid;
        $paymentid = payment_helper::save_payment($payable->get_account_id(), $request->component, $request->paymentarea,
            (int) $request->itemid, $userid, $amount, $request->currency, 'razorpay');

        $DB->update_record('paygw_razorpay_upi', (object) ['id' => $request->id, 'status' => self::STATUS_APPROVED,
            'paymentid' => $paymentid, 'reviewedby' => $reviewerid, 'timereviewed' => time(), 'timemodified' => time()]);

        // A renewal: remember the enrolment as it was before the order is delivered.
        $isfeeenrolment = $request->component === 'enrol_fee' && $request->paymentarea === 'fee';
        $previous = $isfeeenrolment ?
            $DB->get_record('user_enrolments', ['enrolid' => $request->itemid, 'userid' => $userid]) : false;

        payment_helper::deliver_order($request->component, $request->paymentarea, (int) $request->itemid, $paymentid, $userid);

        if ($isfeeenrolment) {
            expiry_manager::apply_renewal((int) $request->itemid, $userid, $previous);
        }

        [, $context] = razorpay_helper::describe_item($request->component, $request->paymentarea, (int) $request->itemid);
        payment_created::create([
            'objectid' => $paymentid,
            'userid' => $userid,
            'context' => $context,
            'other' => ['currency' => $request->currency, 'amount' => $amount],
        ])->trigger();

        $transaction->allow_commit();

        self::notify_learner($request, true, '');
        return true;
    }

    /**
     * Rejects a UPI payment, for example when the UTR is not on the bank statement.
     *
     * @param int $id
     * @param int $reviewerid
     * @param string $note reason shown to the learner
     * @return bool false when the payment is no longer waiting for approval
     */
    public static function reject(int $id, int $reviewerid, string $note): bool {
        global $DB;

        $request = $DB->get_record('paygw_razorpay_upi', ['id' => $id]);
        if (!$request || $request->status !== self::STATUS_PENDING) {
            return false;
        }
        $note = \core_text::substr(trim($note), 0, 255);
        $DB->update_record('paygw_razorpay_upi', (object) ['id' => $request->id, 'status' => self::STATUS_REJECTED,
            'note' => $note, 'reviewedby' => $reviewerid, 'timereviewed' => time(), 'timemodified' => time()]);
        self::notify_learner($request, false, $note);
        return true;
    }

    /**
     * Amount with its currency, for messages and the admin page.
     *
     * @param \stdClass $request
     * @return string
     */
    public static function format_amount(\stdClass $request): string {
        return \core_payment\helper::get_cost_as_string($request->amount / 100, $request->currency);
    }

    /**
     * Tells the payment admins a UPI payment is waiting for them.
     *
     * @param \stdClass $request
     */
    protected static function notify_admins(\stdClass $request): void {
        [$item] = razorpay_helper::describe_item($request->component, $request->paymentarea, (int) $request->itemid);
        $learner = \core_user::get_user($request->userid);
        $a = (object) [
            'learner' => fullname($learner),
            'item' => $item,
            'amount' => self::format_amount($request),
            'utr' => $request->utr,
            'reference' => $request->reference,
            'url' => (new \moodle_url('/payment/gateway/razorpay/upi_payments.php'))->out(false),
        ];
        $admins = get_users_by_capability(\context_system::instance(), 'moodle/payment:manageaccounts', 'u.*');
        foreach ($admins as $admin) {
            $message = new \core\message\message();
            $message->component = 'paygw_razorpay';
            $message->name = 'upisubmitted';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $admin;
            $message->subject = get_string('upiadminsubject', 'paygw_razorpay', $a);
            $message->fullmessage = get_string('upiadminbody', 'paygw_razorpay', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = $a->url;
            $message->contexturlname = get_string('upipayments', 'paygw_razorpay');
            message_send($message);
        }
    }

    /**
     * Tells the learner their UPI payment was approved or rejected.
     *
     * @param \stdClass $request
     * @param bool $approved
     * @param string $note
     */
    protected static function notify_learner(\stdClass $request, bool $approved, string $note): void {
        [$item] = razorpay_helper::describe_item($request->component, $request->paymentarea, (int) $request->itemid);
        $a = (object) [
            'item' => $item,
            'amount' => self::format_amount($request),
            'utr' => $request->utr,
            'note' => $note !== '' ? $note : '-',
        ];
        $key = $approved ? 'upiapproved' : 'upirejected';
        $message = new \core\message\message();
        $message->component = 'paygw_razorpay';
        $message->name = 'upiresult';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = \core_user::get_user($request->userid);
        $message->subject = get_string($key . 'subject', 'paygw_razorpay', $a);
        $message->fullmessage = get_string($key . 'body', 'paygw_razorpay', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        message_send($message);
    }
}

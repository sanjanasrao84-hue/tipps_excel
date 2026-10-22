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

namespace paygw_lmspay;

use core_payment\helper as payment_helper;

/**
 * Orders of the Online payment checkout.
 *
 * The checkout is a model: no payment provider is connected, so completing an order records the payment
 * without collecting money. Amounts, ownership and methods are still checked on the server, so a real
 * provider can later be added in complete() without changing the checkout.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class order_manager {

    /** @var int An order can be paid for this long after it was created. */
    public const ORDER_LIFETIME = 2 * HOURSECS;

    /** @var string[] Banks offered for netbanking, by IFSC prefix. */
    public const BANKS = [
        'SBIN' => 'State Bank of India', 'HDFC' => 'HDFC Bank', 'ICIC' => 'ICICI Bank', 'UTIB' => 'Axis Bank',
        'KKBK' => 'Kotak Mahindra Bank', 'PUNB' => 'Punjab National Bank', 'BARB' => 'Bank of Baroda',
        'CNRB' => 'Canara Bank', 'UBIN' => 'Union Bank of India', 'BKID' => 'Bank of India', 'IDIB' => 'Indian Bank',
        'MAHB' => 'Bank of Maharashtra', 'IOBA' => 'Indian Overseas Bank', 'UCBA' => 'UCO Bank',
        'CBIN' => 'Central Bank of India', 'YESB' => 'Yes Bank', 'INDB' => 'IndusInd Bank', 'IDFB' => 'IDFC FIRST Bank',
        'FDRL' => 'Federal Bank', 'KARB' => 'Karnataka Bank', 'SIBL' => 'South Indian Bank', 'RATN' => 'RBL Bank',
        'AUBL' => 'AU Small Finance Bank',
    ];

    /** @var string[] UPI apps. */
    public const UPI_APPS = ['gpay' => 'Google Pay', 'phonepe' => 'PhonePe', 'paytm' => 'Paytm', 'bhim' => 'BHIM',
        'amazonpay' => 'Amazon Pay', 'cred' => 'CRED'];

    /** @var string[] Wallets. */
    public const WALLETS = ['paytm' => 'Paytm', 'phonepe' => 'PhonePe', 'amazonpay' => 'Amazon Pay',
        'mobikwik' => 'Mobikwik', 'freecharge' => 'Freecharge', 'airtel' => 'Airtel Payments Bank', 'jiomoney' => 'JioMoney'];

    /** @var string[] Card networks. */
    public const NETWORKS = ['Visa', 'Mastercard', 'RuPay', 'Amex', 'Diners'];

    /** @var int[] EMI tenures in months, with yearly interest; 0 is no cost EMI. */
    public const EMI_PLANS = [3 => 0, 6 => 13, 9 => 14, 12 => 15];

    /**
     * Starts a checkout: works out the amount on the server and saves an order for the learner.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @return array what the checkout shows
     */
    public static function create(string $component, string $paymentarea, int $itemid, int $userid): array {
        global $DB, $SITE;

        $config = payment_helper::get_gateway_configuration($component, $paymentarea, $itemid, 'lmspay');
        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $surcharge = payment_helper::get_gateway_surcharge('lmspay');
        $cost = payment_helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
        $amount = (int) round($cost * 100);
        $currency = $payable->get_currency();
        $methods = gateway::get_enabled_methods($config);
        $brand = trim($config['brandname'] ?? '') ?: format_string($SITE->fullname);
        $description = self::describe($component, $paymentarea, $itemid);
        $api = self::get_api($config);

        $now = time();
        $order = (object) [
            'userid' => $userid,
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => self::new_reference(),
            'status' => 'created',
            'provider' => $api ? 'razorpay' : 'model',
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $result = [
            'reference' => $order->reference,
            'amount' => $amount,
            'currency' => $currency,
            'brand' => $brand,
            'description' => $description,
            'provider' => $order->provider,
            'key' => '',
            'rporderid' => '',
            'prefill' => ['name' => fullname($user), 'email' => $user->email, 'contact' => (string) $user->phone1],
            'qrcode' => '',
            'upiflows' => [],
            'upidirect' => false,
            'upivpa' => '',
            'upilink' => '',
        ];

        if ($api) {
            // Only offer what the Razorpay account has activated.
            $available = self::razorpay_available_methods($api);
            $methods = array_values(array_intersect($methods, $available));
            $rporder = $api->create_order($amount, $currency, $order->reference,
                ['userid' => $userid, 'component' => $component, 'paymentarea' => $paymentarea, 'itemid' => $itemid]);
            $order->rp_orderid = $rporder['id'];
            $order->id = $DB->insert_record('paygw_lmspay_order', $order);

            $rpmethods = $api->get_methods();
            $banks = is_array($rpmethods['netbanking'] ?? null) ? $rpmethods['netbanking'] : [];
            $wallets = array_keys(array_filter(is_array($rpmethods['wallet'] ?? null) ? $rpmethods['wallet'] : []));
            $wallets = array_combine($wallets, array_map(fn($code) => self::WALLETS[$code] ?? ucfirst($code), $wallets));
            $result['upiflows'] = self::razorpay_upi_flows($rpmethods);
            // Without UPI on the Razorpay account, learners can still pay by UPI straight to the account's UPI ID.
            $vpa = self::direct_upi_vpa($config);
            if (!in_array('upi', $methods) && $vpa && array_intersect(['upi', 'qr'], gateway::get_enabled_methods($config))) {
                array_unshift($methods, 'upi');
                $link = self::upi_link($vpa, $brand, $amount, $currency, $order->reference);
                $result = ['upidirect' => true, 'upivpa' => $vpa, 'upilink' => $link, 'qrcode' => self::qrcode($link)] + $result;
            }
            return ['key' => $api->get_keyid(), 'rporderid' => $order->rp_orderid] + $result + [
                'methods' => $methods,
                'banks' => self::code_names($banks),
                'wallets' => self::code_names($wallets ?: []),
            ];
        }

        $order->id = $DB->insert_record('paygw_lmspay_order', $order);
        $result['methods'] = $methods;
        $result['banks'] = self::code_names(self::BANKS);
        $result['wallets'] = self::code_names(self::WALLETS);
        if (in_array('qr', $methods)) {
            $result['qrcode'] = self::qrcode(self::upi_link(self::direct_upi_vpa($config) ?: 'payments@lms', $brand, $amount,
                $currency, $order->reference));
        }
        return $result;
    }

    /**
     * The account's own UPI ID, when learners may pay to it directly.
     *
     * @param array $config gateway configuration
     * @return string empty when there is none
     */
    public static function direct_upi_vpa(array $config): string {
        $vpa = trim($config['upivpa'] ?? '');
        return gateway::is_valid_vpa($vpa) ? $vpa : '';
    }

    /**
     * A upi://pay link for exactly the order amount, which every UPI app understands.
     *
     * @param string $vpa who is paid
     * @param string $name payee name shown in the app
     * @param int $amount paise
     * @param string $currency
     * @param string $reference order reference, shown as the payment note
     * @return string
     */
    public static function upi_link(string $vpa, string $name, int $amount, string $currency, string $reference): string {
        return 'upi://pay?' . http_build_query([
            'pa' => $vpa,
            'pn' => $name,
            'am' => sprintf('%.2f', $amount / 100),
            'cu' => $currency,
            'tn' => $reference,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * A QR code image of a link, as a data URI.
     *
     * @param string $link
     * @return string
     */
    protected static function qrcode(string $link): string {
        return 'data:image/svg+xml;base64,' . base64_encode((new \core_qrcode($link))->getBarcodeSVGcode(5, 5, 'black'));
    }

    /**
     * Records the UTR of a UPI payment the learner made to the account's UPI ID; an admin then approves it.
     *
     * @param string $reference
     * @param int $userid
     * @param string $utr the 12 digit UPI transaction reference
     * @return array [bool success, string message]
     */
    public static function submit_utr(string $reference, int $userid, string $utr): array {
        global $DB;
        [$order, $error] = self::get_payable_order($reference, $userid);
        if (!$order) {
            return [false, $error];
        }
        $config = payment_helper::get_gateway_configuration($order->component, $order->paymentarea, (int) $order->itemid,
            'lmspay');
        if ($order->provider !== 'razorpay' || !self::direct_upi_vpa($config)) {
            return [false, get_string('methodnotavailable', 'paygw_lmspay')];
        }
        $utr = preg_replace('/\s+/', '', $utr);
        if (!preg_match('/^\d{12}$/', $utr)) {
            return [false, get_string('utrinvalid', 'paygw_lmspay')];
        }
        // One bank transfer pays one order: a UTR already sent can only be sent again after it was rejected.
        [$insql, $params] = $DB->get_in_or_equal(['pending', 'paid'], SQL_PARAMS_NAMED);
        if ($DB->record_exists_select('paygw_lmspay_order', "utr = :utr AND status $insql", ['utr' => $utr] + $params)) {
            return [false, get_string('utrused', 'paygw_lmspay')];
        }
        $order->status = 'pending';
        $order->method = 'upi';
        $order->methoddetail = 'UTR ' . $utr;
        $order->utr = $utr;
        $order->timemodified = time();
        $DB->update_record('paygw_lmspay_order', $order);

        self::notify_admins($order);
        return [true, get_string('utrsubmitted', 'paygw_lmspay', ['utr' => $utr, 'reference' => $order->reference])];
    }

    /**
     * Approves or rejects a UPI payment waiting for an admin, and tells the learner.
     *
     * @param int $orderid
     * @param bool $approve
     * @param int $adminid
     * @return bool false when the order was not waiting any more
     */
    public static function review_utr(int $orderid, bool $approve, int $adminid): bool {
        global $DB;
        $order = $DB->get_record('paygw_lmspay_order', ['id' => $orderid, 'status' => 'pending']);
        if (!$order) {
            return false;
        }
        if ($approve) {
            if (!self::finish($order, 'upi', $order->methoddetail, null)) {
                return false;
            }
        } else {
            $DB->update_record('paygw_lmspay_order', (object) ['id' => $order->id, 'status' => 'rejected',
                'timemodified' => time()]);
        }
        $DB->set_field('paygw_lmspay_order', 'reviewedby', $adminid, ['id' => $order->id]);

        $a = ['utr' => $order->utr, 'reference' => $order->reference, 'amount' => self::format_amount($order),
            'item' => self::describe($order->component, $order->paymentarea, (int) $order->itemid)];
        $message = new \core\message\message();
        $message->component = 'paygw_lmspay';
        $message->name = 'upireviewed';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = (int) $order->userid;
        $message->subject = get_string($approve ? 'upiapproved_subject' : 'upirejected_subject', 'paygw_lmspay', $a);
        $message->fullmessage = get_string($approve ? 'upiapproved_body' : 'upirejected_body', 'paygw_lmspay', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        message_send($message);
        return true;
    }

    /**
     * Tells the payment admins that a UPI payment is waiting for them.
     *
     * @param \stdClass $order
     */
    protected static function notify_admins(\stdClass $order): void {
        $url = new \moodle_url('/payment/gateway/lmspay/upi_payments.php');
        $a = ['utr' => $order->utr, 'reference' => $order->reference, 'amount' => self::format_amount($order),
            'item' => self::describe($order->component, $order->paymentarea, (int) $order->itemid),
            'user' => fullname(\core_user::get_user($order->userid)), 'url' => $url->out(false)];
        // Site admins hold every capability without a role, so they are not in get_users_by_capability().
        $admins = get_admins() + get_users_by_capability(\context_system::instance(), 'moodle/payment:manageaccounts');
        foreach ($admins as $admin) {
            $message = new \core\message\message();
            $message->component = 'paygw_lmspay';
            $message->name = 'upisubmitted';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $admin;
            $message->subject = get_string('upisubmitted_subject', 'paygw_lmspay', $a);
            $message->fullmessage = get_string('upisubmitted_body', 'paygw_lmspay', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('upipayments', 'paygw_lmspay');
            message_send($message);
        }
    }

    /**
     * An order's amount for people, such as Rs 599.00.
     *
     * @param \stdClass $order
     * @return string
     */
    public static function format_amount(\stdClass $order): string {
        return payment_helper::get_cost_as_string($order->amount / 100, $order->currency);
    }

    /**
     * [code => name] as a list for the web service.
     *
     * @param array $list
     * @return array[]
     */
    protected static function code_names(array $list): array {
        $result = [];
        foreach ($list as $code => $name) {
            $result[] = ['code' => (string) $code, 'name' => (string) $name];
        }
        return $result;
    }

    /** @var razorpay_api|null Replaces the real API in tests. */
    public static $testapi = null;

    /**
     * The Razorpay API of an account, or null when the account uses the model checkout.
     *
     * @param array $config gateway configuration
     * @return razorpay_api|null
     */
    public static function get_api(array $config): ?razorpay_api {
        if (($config['provider'] ?? 'model') !== 'razorpay' || empty($config['keyid']) || empty($config['keysecret'])) {
            return null;
        }
        return self::$testapi ?? new razorpay_api($config['keyid'], $config['keysecret']);
    }

    /**
     * Which of our methods the Razorpay account has activated.
     *
     * Razorpay's own checkout window takes the payment, so it also covers the QR code (inside UPI) and EMI.
     *
     * @param razorpay_api $api
     * @param bool $fresh ask Razorpay now instead of using the copy kept for an hour
     * @return string[]
     */
    public static function razorpay_available_methods(razorpay_api $api, bool $fresh = false): array {
        $methods = $api->get_methods($fresh);
        $available = [];
        if (self::razorpay_upi_flows($methods)) {
            $available[] = 'upi';
        }
        if (!empty($methods['card'])) {
            $available[] = 'card';
        }
        if (!empty($methods['netbanking'])) {
            $available[] = 'netbanking';
        }
        if (is_array($methods['wallet'] ?? null) && array_filter($methods['wallet'])) {
            $available[] = 'wallet';
        }
        if (!empty($methods['emi'])) {
            $available[] = 'emi';
        }
        return $available;
    }

    /**
     * The ways of paying by UPI that the Razorpay account has activated.
     *
     * Nothing unless UPI itself is activated: an account can report upi_intent while UPI is off, and then Razorpay's
     * window cannot take a UPI payment at all. upi_type says which flows are on when Razorpay reports it.
     *
     * @param array $methods as returned by GET /methods
     * @return string[] qr, collect and/or intent
     */
    public static function razorpay_upi_flows(array $methods): array {
        if (empty($methods['upi'])) {
            return [];
        }
        $types = is_array($methods['upi_type'] ?? null) ? $methods['upi_type'] : ['collect' => 1, 'intent' => 1];
        $flows = ['qr'];
        if (!empty($types['collect'])) {
            $flows[] = 'collect';
        }
        if (!empty($types['intent']) || !empty($methods['upi_intent'])) {
            $flows[] = 'intent';
        }
        return $flows;
    }

    /**
     * Gets an order of the learner that can still be paid.
     *
     * @param string $reference
     * @param int $userid
     * @return array [order or null, error message]
     */
    protected static function get_payable_order(string $reference, int $userid): array {
        global $DB;
        $order = $DB->get_record('paygw_lmspay_order', ['reference' => $reference]);
        if (!$order || (int) $order->userid !== $userid) {
            return [null, get_string('ordernotfound', 'paygw_lmspay')];
        }
        if ($order->status === 'pending') {
            return [null, get_string('utrpending', 'paygw_lmspay')];
        }
        if ($order->status !== 'created') {
            return [null, get_string('alreadypaid', 'paygw_lmspay')];
        }
        return [$order, ''];
    }

    /**
     * The Razorpay API for an order's payment account.
     *
     * @param \stdClass $order
     * @return razorpay_api|null
     */
    protected static function order_api(\stdClass $order): ?razorpay_api {
        $config = payment_helper::get_gateway_configuration($order->component, $order->paymentarea, (int) $order->itemid,
            'lmspay');
        return self::get_api($config);
    }

    /**
     * Creates a single use Razorpay UPI QR code for exactly the order amount.
     *
     * @param string $reference
     * @param int $userid
     * @return array [success, message, image url, closes at]
     */
    public static function create_qr(string $reference, int $userid): array {
        global $DB;
        [$order, $error] = self::get_payable_order($reference, $userid);
        if (!$order) {
            return [false, $error, '', 0];
        }
        $api = $order->provider === 'razorpay' ? self::order_api($order) : null;
        if (!$api) {
            return [false, get_string('methodnotavailable', 'paygw_lmspay'), '', 0];
        }
        // A new QR code replaces the previous one of this order, which must no longer take money.
        if ($order->rp_qrid) {
            if (self::check_qr($order, $api)) {
                return [false, get_string('alreadypaid', 'paygw_lmspay'), '', 0];
            }
            $api->close_qr($order->rp_qrid);
        }
        $closeby = time() + 15 * MINSECS;
        $config = payment_helper::get_gateway_configuration($order->component, $order->paymentarea, (int) $order->itemid,
            'lmspay');
        $qr = $api->create_qr((int) $order->amount, trim($config['brandname'] ?? '') ?: 'Payment',
            self::describe($order->component, $order->paymentarea, (int) $order->itemid), $closeby,
            ['reference' => $order->reference, 'rp_orderid' => $order->rp_orderid]);
        $DB->set_field('paygw_lmspay_order', 'rp_qrid', $qr['id'], ['id' => $order->id]);
        return [true, '', $qr['image_url'], $closeby];
    }

    /**
     * Checks whether the learner has paid the order's QR code, and completes the order if so.
     *
     * @param string $reference
     * @param int $userid
     * @return array [paid, message]
     */
    public static function check_qr_payment(string $reference, int $userid): array {
        global $DB;
        $order = $DB->get_record('paygw_lmspay_order', ['reference' => $reference, 'userid' => $userid]);
        if ($order && $order->status === 'paid') {
            return [true, get_string('paymentdone', 'paygw_lmspay', $order->reference)];
        }
        if (!$order || !$order->rp_qrid || !($api = self::order_api($order))) {
            return [false, ''];
        }
        return self::check_qr($order, $api) ?
            [true, get_string('paymentdone', 'paygw_lmspay', $order->reference)] : [false, ''];
    }

    /**
     * Completes the order when its QR code has a captured payment of the full amount.
     *
     * @param \stdClass $order
     * @param razorpay_api $api
     * @return bool whether the order is now paid
     */
    protected static function check_qr(\stdClass $order, razorpay_api $api): bool {
        foreach ($api->qr_payments($order->rp_qrid) as $payment) {
            if (($payment['status'] ?? '') === 'captured' && (int) $payment['amount'] === (int) $order->amount
                    && ($payment['currency'] ?? '') === $order->currency) {
                return self::finish($order, 'qr', self::razorpay_detail($payment), $payment['id']);
            }
        }
        return false;
    }

    /**
     * A short description of how a Razorpay payment was made.
     *
     * @param array $payment
     * @return string
     */
    protected static function razorpay_detail(array $payment): string {
        switch ($payment['method'] ?? '') {
            case 'upi':
                return (string) ($payment['vpa'] ?? 'UPI');
            case 'card':
                $card = $payment['card'] ?? [];
                return trim(($card['network'] ?? 'Card') . ' •••• ' . ($card['last4'] ?? ''));
            case 'netbanking':
                return self::BANKS[$payment['bank'] ?? ''] ?? (string) ($payment['bank'] ?? '');
            case 'wallet':
                return self::WALLETS[$payment['wallet'] ?? ''] ?? (string) ($payment['wallet'] ?? '');
        }
        return (string) ($payment['method'] ?? '');
    }

    /**
     * What is being paid for, in a few words.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return string
     */
    public static function describe(string $component, string $paymentarea, int $itemid): string {
        global $DB, $SITE;
        $provider = "$component\\payment\\service_provider";
        if (class_exists($provider) && method_exists($provider, 'get_item_description')) {
            [$name] = $provider::get_item_description($paymentarea, $itemid);
            return $name;
        }
        if ($component === 'enrol_fee' && ($courseid = $DB->get_field('enrol', 'courseid', ['id' => $itemid]))) {
            return format_string(get_course($courseid)->fullname);
        }
        return format_string($SITE->fullname);
    }

    /**
     * A new, unused order reference such as LP-7K2Q9XP4WM.
     *
     * @return string
     */
    protected static function new_reference(): string {
        global $DB;
        do {
            $reference = 'LP-';
            for ($i = 0; $i < 10; $i++) {
                $reference .= 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[random_int(0, 31)];
            }
        } while ($DB->record_exists('paygw_lmspay_order', ['reference' => $reference]));
        return $reference;
    }

    /**
     * Checks what the checkout sent about how the learner paid, and returns a short description to keep.
     *
     * Full card numbers never reach the server: only the network and the last four digits.
     *
     * @param string $method
     * @param array $detail
     * @return string|null null when the details are not valid
     */
    public static function clean_detail(string $method, array $detail): ?string {
        $value = trim((string) ($detail['value'] ?? ''));
        switch ($method) {
            case 'upi':
                if (isset(self::UPI_APPS[$value])) {
                    return self::UPI_APPS[$value];
                }
                return gateway::is_valid_vpa($value) ? $value : null;
            case 'qr':
                return 'UPI QR';
            case 'card':
                $network = (string) ($detail['network'] ?? '');
                return in_array($network, self::NETWORKS, true) && preg_match('/^\d{4}$/', $value) ?
                    "$network •••• $value" : null;
            case 'netbanking':
                return self::BANKS[$value] ?? null;
            case 'wallet':
                return self::WALLETS[$value] ?? null;
            case 'emi':
                $months = (int) ($detail['months'] ?? 0);
                return isset(self::BANKS[$value]) && isset(self::EMI_PLANS[$months]) ?
                    self::BANKS[$value] . ", $months months" : null;
        }
        return null;
    }


    /**
     * Completes an order of the model checkout, with the method the learner picked.
     *
     * @param string $reference
     * @param int $userid
     * @param string $method
     * @param array $detail
     * @return array [bool success, string message]
     */
    public static function complete(string $reference, int $userid, string $method, array $detail): array {
        [$order, $error] = self::get_payable_order($reference, $userid);
        if (!$order) {
            return [false, $error];
        }
        // A Razorpay order is only ever completed with Razorpay's proof of payment.
        if ($order->provider !== 'model') {
            return [false, get_string('methodnotavailable', 'paygw_lmspay')];
        }
        if ($order->timecreated < time() - self::ORDER_LIFETIME) {
            return [false, get_string('orderexpired', 'paygw_lmspay')];
        }
        $config = payment_helper::get_gateway_configuration($order->component, $order->paymentarea, (int) $order->itemid,
            'lmspay');
        if (self::get_api($config) || !in_array($method, gateway::get_enabled_methods($config), true)) {
            return [false, get_string('methodnotavailable', 'paygw_lmspay')];
        }
        $methoddetail = self::clean_detail($method, $detail);
        if ($methoddetail === null) {
            return [false, get_string('detailsinvalid', 'paygw_lmspay')];
        }
        self::finish($order, $method, $methoddetail, null);
        return [true, get_string('paymentdone', 'paygw_lmspay', $order->reference)];
    }

    /**
     * Completes an order paid through Razorpay: checks the signature, then asks Razorpay what was really paid.
     *
     * @param string $reference
     * @param int $userid
     * @param string $paymentid razorpay_payment_id
     * @param string $signature razorpay_signature
     * @return array [bool success, string message]
     */
    public static function complete_razorpay(string $reference, int $userid, string $paymentid, string $signature): array {
        [$order, $error] = self::get_payable_order($reference, $userid);
        if (!$order) {
            return [false, $error];
        }
        $api = $order->provider === 'razorpay' ? self::order_api($order) : null;
        if (!$api || !$order->rp_orderid || !$api->verify_signature($order->rp_orderid, $paymentid, $signature)) {
            return [false, get_string('paymentnotverified', 'paygw_lmspay')];
        }
        try {
            $payment = $api->fetch_payment($paymentid);
            if (($payment['status'] ?? '') === 'authorized') {
                // Accounts without automatic capture: capture now, or Razorpay refunds it after a few days.
                $payment = $api->capture_payment($paymentid, (int) $order->amount, $order->currency) + $payment;
            }
        } catch (\moodle_exception $e) {
            return [false, get_string('paymentnotverified', 'paygw_lmspay')];
        }
        if (($payment['status'] ?? '') !== 'captured' || ($payment['order_id'] ?? '') !== $order->rp_orderid
                || (int) ($payment['amount'] ?? 0) !== (int) $order->amount || ($payment['currency'] ?? '') !== $order->currency) {
            return [false, get_string('paymentnotverified', 'paygw_lmspay')];
        }
        $method = $payment['method'] ?? '';
        $method = in_array($method, gateway::METHODS, true) ? $method : (string) $method;
        if (!self::finish($order, $method, self::razorpay_detail($payment), $paymentid)) {
            return [false, get_string('alreadypaid', 'paygw_lmspay')];
        }
        return [true, get_string('paymentdone', 'paygw_lmspay', $order->reference)];
    }

    /**
     * Records the payment and gives the learner what they paid for.
     *
     * @param \stdClass $order
     * @param string $method
     * @param string $methoddetail
     * @param string|null $rppaymentid Razorpay payment id
     * @return bool false when the order was paid in the meantime
     */
    protected static function finish(\stdClass $order, string $method, string $methoddetail, ?string $rppaymentid): bool {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        // Read again inside the transaction: a QR check and a checkout callback may arrive together.
        if (!in_array($DB->get_field('paygw_lmspay_order', 'status', ['id' => $order->id]), ['created', 'pending'], true)
                || ($rppaymentid && $DB->record_exists('paygw_lmspay_order', ['rp_paymentid' => $rppaymentid]))) {
            $transaction->allow_commit();
            return false;
        }
        $userid = (int) $order->userid;
        $payable = payment_helper::get_payable($order->component, $order->paymentarea, (int) $order->itemid);
        $paymentid = payment_helper::save_payment($payable->get_account_id(), $order->component, $order->paymentarea,
            (int) $order->itemid, $userid, $order->amount / 100, $order->currency, 'lmspay');
        $DB->update_record('paygw_lmspay_order', (object) [
            'id' => $order->id,
            'status' => 'paid',
            'method' => \core_text::substr($method, 0, 20),
            'methoddetail' => \core_text::substr($methoddetail, 0, 100),
            'paymentid' => $paymentid,
            'rp_paymentid' => $rppaymentid,
            'timemodified' => time(),
        ]);
        payment_helper::deliver_order($order->component, $order->paymentarea, (int) $order->itemid, $paymentid, $userid);
        $transaction->allow_commit();
        return true;
    }
}

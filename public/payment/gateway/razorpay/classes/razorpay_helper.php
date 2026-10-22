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
 * Contains helper class to work with razorpay REST API.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_razorpay;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/../.extlib/razorpay-php/Razorpay.php');

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

/**
 * helper class.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class razorpay_helper {

    /**
     * @var string The payment was paid successfully for the order.
     */
    public const ORDER_STATUS_PAID = 'paid';

    /**
     * @var string The payment was captured successfully for the order.
     */
    public const PAYMENT_STATUS_CAPTURED = 'captured';

    /**
     * @var string razorpay API Client ID
     */
    private $clientid;

    /**
     * @var string razorpay API secret
     */
    private $secret;

    /**
     * @var object APi object
     */
    private $api;

    /**
     * @var bool Demo mode: Razorpay is never contacted, orders and payments are simulated.
     */
    private $demo;

    /**
     * @var string Prefix of simulated order ids, so they can never be confused with real ones.
     */
    public const DEMO_ORDER_PREFIX = 'order_DEMO';

    /**
     * helper constructor.
     *
     * @param string $clientid The client id.
     * @param string $secret razorpay secret.
     * @param string $environment live, sandbox or demo
     */
    public function __construct(string $clientid, string $secret, string $environment = 'live') {
        $this->clientid = $clientid;
        $this->secret = $secret;
        $this->demo = $environment === 'demo';
        $this->api = new Api($this->clientid, $this->secret);
    }

    /**
     * Whether this account simulates payments instead of using Razorpay.
     *
     * @return bool
     */
    public function is_demo(): bool {
        return $this->demo;
    }

    /**
     * The token the simulated checkout must send back, standing in for Razorpay's signature.
     *
     * Only the server knows the secret, so a browser cannot make up a token for an order it was not given.
     *
     * @param string $orderid
     * @return string
     */
    public function demo_token(string $orderid): string {
        return hash_hmac('sha256', 'demo|' . $orderid, $this->secret);
    }

    /**
     * Converts an amount in rupees to whole paise, avoiding floating point errors.
     *
     * @param float $amount
     * @return int
     */
    public static function to_paise(float $amount): int {
        return (int) round($amount * 100);
    }

    /**
     * Returns a display name and context for the item being paid for.
     *
     * The gateway is used by enrol_fee today and by other components (subscription plans) later,
     * so the item must not be assumed to be an enrolment instance.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return array [string name, \context context]
     */
    public static function describe_item(string $component, string $paymentarea, int $itemid): array {
        global $DB, $SITE;

        // Components can describe their own items (for example subscription plans).
        $provider = "$component\\payment\\service_provider";
        if (class_exists($provider) && method_exists($provider, 'get_item_description')) {
            return $provider::get_item_description($paymentarea, $itemid);
        }
        if (strpos($component, 'enrol_') === 0) {
            $courseid = $DB->get_field('enrol', 'courseid', ['id' => $itemid]);
            if ($courseid) {
                $course = get_course($courseid);
                return [format_string($course->shortname), \context_course::instance($course->id)];
            }
        }
        return [format_string($SITE->shortname), \context_system::instance()];
    }

    /**
     * Create an order on Razorpay and save it on the server, so the payment can be checked against it later.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @param int $userid
     * @param float $amount amount in rupees, as returned by the payable
     * @param string $currency
     * @return array checkout options for the Razorpay JS SDK
     */
    public function create_order(string $component, string $paymentarea, int $itemid, int $userid,
                                 float $amount, string $currency): array {
        global $DB;

        [$name] = self::describe_item($component, $paymentarea, $itemid);
        $paise = self::to_paise($amount);
        // Razorpay receipts are limited to 40 characters.
        $receipt = substr("u{$userid}-i{$itemid}-" . time(), 0, 40);

        if ($this->demo) {
            $razorpayorderid = self::DEMO_ORDER_PREFIX . strtoupper(random_string(14));
        } else {
            $razorpayorder = $this->api->order->create([
                'receipt' => $receipt,
                'amount' => $paise,
                'currency' => $currency,
                'notes' => [
                    'userid' => $userid,
                    'component' => $component,
                    'paymentarea' => $paymentarea,
                    'itemid' => $itemid,
                ],
            ]);
            $razorpayorderid = $razorpayorder['id'];
        }

        $now = time();
        $DB->insert_record('paygw_razorpay_order', (object) [
            'userid' => $userid,
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'amount' => $paise,
            'currency' => $currency,
            'rp_orderid' => $razorpayorderid,
            'status' => 'created',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        return [
            "key" => $this->clientid,
            "amount" => $paise,
            "name" => $name,
            "description" => get_string('description', 'paygw_razorpay', $name),
            "image" => "",
            "prefill" => [
                "name" => \fullname($user),
                "email" => $user->email,
                "contact" => (string) $user->phone1,
            ],
            "notes" => [
                "course_id" => $receipt,
            ],
            "order_id" => $razorpayorderid,
            "demo" => $this->demo,
            "demotoken" => $this->demo ? $this->demo_token($razorpayorderid) : '',
            "currency" => $currency,
        ];
    }

    /**
     * A UPI QR code for the simulated checkout, as an SVG data URI.
     *
     * It points at a demo VPA, so scanning it with a real UPI app cannot send money anywhere.
     *
     * @param array $options checkout options returned by create_order
     * @return string
     */
    public static function demo_qrcode(array $options): string {
        $upi = 'upi://pay?' . http_build_query([
            'pa' => 'demo.lms@razorpay',
            'pn' => 'DEMO ' . $options['name'],
            'am' => sprintf('%.2f', $options['amount'] / 100),
            'cu' => $options['currency'],
            'tr' => $options['order_id'],
            'tn' => 'Demo payment - do not pay',
        ], '', '&', PHP_QUERY_RFC3986);
        $qrcode = new \core_qrcode($upi);
        return 'data:image/svg+xml;base64,' . base64_encode($qrcode->getBarcodeSVGcode(5, 5, 'black'));
    }

    /**
     * The payment method Razorpay reports, normalised to the methods the gateway knows.
     *
     * @param string|null $method
     * @return string
     */
    public static function clean_method(?string $method): string {
        // A QR code payment is an ordinary UPI payment as far as Razorpay is concerned.
        $method = $method === 'upiqr' ? 'upi' : (string) $method;
        return in_array($method, gateway::METHODS, true) ? $method : '';
    }

    /**
     * Checks a simulated payment and returns order and payment details shaped like Razorpay's,
     * so the normal checks in transaction_complete apply unchanged.
     *
     * @param \stdClass $order the saved order
     * @param string $paymentid
     * @param string $token
     * @param string $method the payment method picked in the simulated checkout
     * @return array|null [orderdetails, paymentdetails], or null when the token is wrong
     */
    public function demo_payment(\stdClass $order, string $paymentid, string $token, string $method = ''): ?array {
        if (!$this->demo || strpos($order->rp_orderid, self::DEMO_ORDER_PREFIX) !== 0
                || !preg_match('/^pay_DEMO[A-Za-z0-9]{8,32}$/', $paymentid)
                || !hash_equals($this->demo_token($order->rp_orderid), $token)) {
            return null;
        }
        $orderdetails = ['id' => $order->rp_orderid, 'status' => self::ORDER_STATUS_PAID,
            'amount' => (int) $order->amount, 'currency' => $order->currency];
        $payment = ['id' => $paymentid, 'status' => self::PAYMENT_STATUS_CAPTURED, 'order_id' => $order->rp_orderid,
            'amount' => (int) $order->amount, 'currency' => $order->currency, 'method' => self::clean_method($method)];
        return [$orderdetails, $payment];
    }

    /**
     * Verify the signature of an order on Razorpay.
     * @param array $attributes
     * @return bool status
     */
    public function verify_signature($attributes): bool {
        try {
            $this->api->utility->verifyPaymentSignature($attributes);
            $success = true;
        } catch (SignatureVerificationError $e) {
            $success = false;
        }
        return $success;
    }

    /**
     * Get the order details from Razorpay
     * @param string $orderid
     * @return array orderdetails
     */
    public function get_order_details($orderid) {
        $orderdata = $this->api->order->fetch($orderid);
        return $orderdata;
    }

    /**
     * Get the Payment details from Razorpay
     * @param string $paymentid
     * @return array paymentdetails
     */
    public function fetch_payment_details($paymentid) {
        $paymentdata = $this->api->payment->fetch($paymentid);
        return $paymentdata;
    }
}

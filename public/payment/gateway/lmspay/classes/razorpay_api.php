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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Small client for the Razorpay REST API (orders, payments, UPI QR codes, methods).
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class razorpay_api {

    /** @var string API base URL. */
    protected const BASE = 'https://api.razorpay.com/v1/';

    /** @var string Key id, rzp_test_... or rzp_live_... */
    protected $keyid;

    /** @var string Key secret. */
    protected $secret;

    /**
     * Constructor.
     *
     * @param string $keyid
     * @param string $secret
     */
    public function __construct(string $keyid, string $secret) {
        $this->keyid = $keyid;
        $this->secret = $secret;
    }

    /**
     * The key id, which the browser needs for razorpay.js.
     *
     * @return string
     */
    public function get_keyid(): string {
        return $this->keyid;
    }

    /**
     * Calls the API.
     *
     * @param string $method GET or POST
     * @param string $path
     * @param array $data request body for POST
     * @param bool $public a key-only endpoint, which rejects calls signed with the secret
     * @return array decoded response
     * @throws \moodle_exception when Razorpay answers with an error
     */
    protected function request(string $method, string $path, array $data = [], bool $public = false): array {
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_USERPWD' => $this->keyid . ':' . ($public ? '' : $this->secret),
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
        ]);
        $curl->setHeader(['Content-Type: application/json']);
        $url = self::BASE . $path;
        $body = $method === 'POST' ? $curl->post($url, json_encode((object) $data)) : $curl->get($url);
        $status = (int) ($curl->get_info()['http_code'] ?? 0);
        $response = json_decode((string) $body, true);
        if ($curl->get_errno() || $status < 200 || $status >= 300 || !is_array($response)) {
            $reason = $response['error']['description'] ?? ($curl->error ?: "HTTP $status");
            debugging("Razorpay $method $path failed: $reason", DEBUG_DEVELOPER);
            throw new \moodle_exception('razorpayerror', 'paygw_lmspay', '', $reason);
        }
        return $response;
    }

    /**
     * Creates an order.
     *
     * @param int $amount paise
     * @param string $currency
     * @param string $receipt our order reference
     * @param array $notes
     * @return array the order, with its id
     */
    public function create_order(int $amount, string $currency, string $receipt, array $notes): array {
        return $this->request('POST', 'orders', [
            'amount' => $amount,
            'currency' => $currency,
            'receipt' => $receipt,
            'notes' => $notes,
        ]);
    }

    /**
     * Fetches a payment.
     *
     * @param string $paymentid
     * @return array
     */
    public function fetch_payment(string $paymentid): array {
        // With the card expanded, for its network and last four digits.
        return $this->request('GET', 'payments/' . rawurlencode($paymentid) . '?expand%5B%5D=card');
    }

    /**
     * Captures an authorised payment.
     *
     * @param string $paymentid
     * @param int $amount paise
     * @param string $currency
     * @return array
     */
    public function capture_payment(string $paymentid, int $amount, string $currency): array {
        return $this->request('POST', 'payments/' . rawurlencode($paymentid) . '/capture',
            ['amount' => $amount, 'currency' => $currency]);
    }

    /**
     * Creates a single use UPI QR code for an exact amount.
     *
     * @param int $amount paise
     * @param string $name shown in the UPI app
     * @param string $description
     * @param int $closeby when the QR code stops working
     * @param array $notes
     * @return array the QR code, with id and image_url
     */
    public function create_qr(int $amount, string $name, string $description, int $closeby, array $notes): array {
        return $this->request('POST', 'payments/qr_codes', [
            'type' => 'upi_qr',
            'name' => \core_text::substr($name, 0, 40),
            'usage' => 'single_use',
            'fixed_amount' => true,
            'payment_amount' => $amount,
            'description' => \core_text::substr($description, 0, 100),
            'close_by' => $closeby,
            'notes' => $notes,
        ]);
    }

    /**
     * Payments made on a QR code.
     *
     * @param string $qrid
     * @return array[]
     */
    public function qr_payments(string $qrid): array {
        return $this->request('GET', 'payments/qr_codes/' . rawurlencode($qrid) . '/payments')['items'] ?? [];
    }

    /**
     * Closes a QR code so it cannot be paid again.
     *
     * @param string $qrid
     */
    public function close_qr(string $qrid): void {
        try {
            $this->request('POST', 'payments/qr_codes/' . rawurlencode($qrid) . '/close');
        } catch (\moodle_exception $e) {
            // Already closed or paid: nothing to do.
            unset($e);
        }
    }

    /**
     * The payment methods activated on the Razorpay account, cached for an hour.
     *
     * @param bool $fresh ask Razorpay now, for example when an admin is checking the account
     * @return array as returned by GET /methods: card, netbanking (code => name), wallet (code => bool), upi, emi...
     */
    public function get_methods(bool $fresh = false): array {
        $cache = \cache::make('paygw_lmspay', 'razorpaymethods');
        $methods = $fresh ? false : $cache->get($this->keyid);
        if ($methods === false) {
            $methods = $this->request('GET', 'methods', [], true);
            $cache->set($this->keyid, $methods);
        }
        return $methods;
    }

    /**
     * Checks the signature razorpay.js returns with a successful payment.
     *
     * @param string $orderid
     * @param string $paymentid
     * @param string $signature
     * @return bool
     */
    public function verify_signature(string $orderid, string $paymentid, string $signature): bool {
        return hash_equals(hash_hmac('sha256', $orderid . '|' . $paymentid, $this->secret), $signature);
    }
}

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

/**
 * Online payment gateway: one checkout with UPI, QR code, cards, netbanking, wallets and EMI.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {

    /** @var string[] Payment methods, in the order the checkout shows them. */
    public const METHODS = ['upi', 'qr', 'card', 'netbanking', 'wallet', 'emi'];

    /**
     * Supported currencies.
     *
     * @return string[]
     */
    public static function get_supported_currencies(): array {
        return ['INR'];
    }

    /**
     * The payment methods an account offers. A method never saved counts as on.
     *
     * @param array $config gateway configuration
     * @return string[]
     */
    public static function get_enabled_methods(array $config): array {
        return array_values(array_filter(self::METHODS, fn($method) => !empty($config['method_' . $method] ?? 1)));
    }

    /**
     * Whether a UPI ID looks valid, for example name@okhdfcbank.
     *
     * @param string $vpa
     * @return bool
     */
    public static function is_valid_vpa(string $vpa): bool {
        return (bool) preg_match('/^[A-Za-z0-9._-]{2,256}@[A-Za-z][A-Za-z0-9]{1,63}$/', $vpa);
    }

    /**
     * Configuration form for the gateway instance.
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('select', 'provider', get_string('provider', 'paygw_lmspay'), [
            'razorpay' => get_string('provider_razorpay', 'paygw_lmspay'),
            'model' => get_string('provider_model', 'paygw_lmspay'),
        ]);
        $mform->setDefault('provider', 'model');
        $mform->addHelpButton('provider', 'provider', 'paygw_lmspay');

        $mform->addElement('static', 'modelnotice', '', \html_writer::div(get_string('modelnotice_admin', 'paygw_lmspay'),
            'alert alert-warning mb-0'));
        $mform->hideIf('modelnotice', 'provider', 'neq', 'model');

        $mform->addElement('text', 'keyid', get_string('keyid', 'paygw_lmspay'), ['placeholder' => 'rzp_live_...']);
        $mform->setType('keyid', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('keyid', 'keyid', 'paygw_lmspay');
        $mform->hideIf('keyid', 'provider', 'neq', 'razorpay');
        $mform->addElement('passwordunmask', 'keysecret', get_string('keysecret', 'paygw_lmspay'));
        $mform->setType('keysecret', PARAM_RAW_TRIMMED);
        $mform->hideIf('keysecret', 'provider', 'neq', 'razorpay');

        // What the Razorpay account has activated, so the admin knows why a method is missing at checkout.
        $config = $form->get_gateway_persistent()->get_configuration();
        if (($config['provider'] ?? '') === 'razorpay' && !empty($config['keyid']) && !empty($config['keysecret'])) {
            $mform->addElement('static', 'razorpaystatus', get_string('razorpaystatus', 'paygw_lmspay'),
                self::describe_razorpay_methods($config));
            $mform->hideIf('razorpaystatus', 'provider', 'neq', 'razorpay');
        }

        $mform->addElement('text', 'brandname', get_string('brandname', 'paygw_lmspay'));
        $mform->setType('brandname', PARAM_TEXT);
        $mform->addHelpButton('brandname', 'brandname', 'paygw_lmspay');

        $mform->addElement('static', 'methodsheading', get_string('methods', 'paygw_lmspay'),
            get_string('methods_desc', 'paygw_lmspay'));
        foreach (self::METHODS as $method) {
            $mform->addElement('advcheckbox', 'method_' . $method, '', get_string('method_' . $method, 'paygw_lmspay'));
            $mform->setDefault('method_' . $method, 1);
        }

        $mform->addElement('text', 'upivpa', get_string('upivpa', 'paygw_lmspay'), ['placeholder' => 'institute@okhdfcbank']);
        $mform->setType('upivpa', PARAM_TEXT);
        $mform->addHelpButton('upivpa', 'upivpa', 'paygw_lmspay');
        $mform->addElement('static', 'upipaymentslink', '', \html_writer::link(
            new \moodle_url('/payment/gateway/lmspay/upi_payments.php'), get_string('upipayments', 'paygw_lmspay')));
        $mform->hideIf('upipaymentslink', 'provider', 'neq', 'razorpay');
    }

    /**
     * Which of our payment methods the Razorpay account has activated, as a short HTML list.
     *
     * @param array $config gateway configuration
     * @return string
     */
    public static function describe_razorpay_methods(array $config): string {
        try {
            $available = order_manager::razorpay_available_methods(order_manager::get_api($config), true);
        } catch (\moodle_exception $e) {
            return \html_writer::div($e->getMessage(), 'text-danger');
        }
        $items = [];
        // The QR code is not a method of its own with Razorpay: it is part of UPI.
        foreach (array_diff(self::METHODS, ['qr']) as $method) {
            $on = in_array($method, $available, true);
            $label = get_string('label_' . $method, 'paygw_lmspay');
            if ($method === 'upi' && !$on) {
                // Without UPI on Razorpay, learners can still pay by UPI to the account's own UPI ID.
                $vpa = order_manager::direct_upi_vpa($config);
                $on = (bool) $vpa;
                $label .= ' ' . \html_writer::span($vpa ? get_string('upistatus_direct', 'paygw_lmspay', s($vpa)) :
                    get_string('upistatus_off', 'paygw_lmspay'), 'small text-muted');
            }
            $items[] = \html_writer::tag('li', ($on ? '✅ ' : '❌ ') . $label);
        }
        return \html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled mb-1']) .
            \html_writer::div(get_string('razorpaystatus_desc', 'paygw_lmspay'), 'small text-muted');
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
                                                 \stdClass $data, array $files, array &$errors): void {
        if ($data->enabled && trim($data->brandname ?? '') === '') {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
        if (($data->provider ?? 'model') === 'razorpay') {
            if (!preg_match('/^rzp_(test|live)_[A-Za-z0-9]{8,32}$/', trim($data->keyid ?? ''))) {
                $errors['keyid'] = get_string('keyidinvalid', 'paygw_lmspay');
            }
            if (trim($data->keysecret ?? '') === '') {
                $errors['keysecret'] = get_string('required');
            }
        }
        if (!self::get_enabled_methods((array) $data)) {
            $errors['method_upi'] = get_string('nomethods', 'paygw_lmspay');
        }
        $vpa = trim($data->upivpa ?? '');
        if ($vpa !== '' && !self::is_valid_vpa($vpa)) {
            $errors['upivpa'] = get_string('upivpainvalid', 'paygw_lmspay');
        }
    }
}

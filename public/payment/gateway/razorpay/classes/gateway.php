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
 * Contains class for razorpay payment gateway.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_razorpay;

/**
 * Contains class for razorpay payment gateway.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Payment methods an account can offer, in the order the checkout shows them.
     * upiqr is the UPI QR code; Razorpay reports it as an ordinary upi payment.
     */
    public const METHODS = ['upi', 'upiqr', 'card', 'netbanking', 'wallet', 'emi', 'cardless_emi', 'paylater'];

    /**
     * The payment methods an account offers. Accounts saved before methods existed offer all of them.
     *
     * @param array $config gateway configuration
     * @return string[]
     */
    public static function get_enabled_methods(array $config): array {
        return array_values(array_filter(self::METHODS, fn($method) => !empty($config['method_' . $method] ?? 1)));
    }

    /**
     * Get supported currencies
     */
    public static function get_supported_currencies(): array {
        // 3-character ISO-4217: https://en.wikipedia.org/wiki/ISO_4217#Active_codes.
        return ['INR'];
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'brandname', get_string('brandname', 'paygw_razorpay'));
        $mform->setType('brandname', PARAM_TEXT);
        $mform->addHelpButton('brandname', 'brandname', 'paygw_razorpay');

        $mform->addElement('text', 'clientid', get_string('clientid', 'paygw_razorpay'));
        $mform->setType('clientid', PARAM_TEXT);
        $mform->addHelpButton('clientid', 'clientid', 'paygw_razorpay');

        $mform->addElement('passwordunmask', 'secret', get_string('secret', 'paygw_razorpay'));
        $mform->setType('secret', PARAM_TEXT);
        $mform->addHelpButton('secret', 'secret', 'paygw_razorpay');

        $options = [
            'live' => get_string('live', 'paygw_razorpay'),
            'sandbox'  => get_string('sandbox', 'paygw_razorpay'),
            // Demo never contacts Razorpay: a simulated checkout, to try the full flow without real keys.
            'demo' => get_string('demo', 'paygw_razorpay'),
        ];

        $mform->addElement('select', 'environment', get_string('environment', 'paygw_razorpay'), $options);
        $mform->addHelpButton('environment', 'environment', 'paygw_razorpay');

        $mform->addElement('static', 'methodsheading', get_string('paymentmethods', 'paygw_razorpay'),
            get_string('paymentmethods_desc', 'paygw_razorpay'));
        foreach (self::METHODS as $method) {
            $mform->addElement('advcheckbox', 'method_' . $method, '', get_string('method_' . $method, 'paygw_razorpay'));
            $mform->setDefault('method_' . $method, 1);
        }

        // Learners pay this UPI ID directly (QR code or UPI app); an admin approves each payment.
        $mform->addElement('static', 'upiheading', get_string('directupi', 'paygw_razorpay'),
            get_string('directupi_desc', 'paygw_razorpay'));
        $mform->addElement('text', 'upivpa', get_string('upivpa', 'paygw_razorpay'), ['placeholder' => 'yourname@okhdfcbank']);
        $mform->setType('upivpa', PARAM_TEXT);
        $mform->addHelpButton('upivpa', 'upivpa', 'paygw_razorpay');
        $mform->addElement('text', 'upiname', get_string('upiname', 'paygw_razorpay'));
        $mform->setType('upiname', PARAM_TEXT);
        $mform->addHelpButton('upiname', 'upiname', 'paygw_razorpay');
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
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
                                                 \stdClass $data, array $files, array &$errors): void {
        if ($data->enabled &&
                (empty($data->brandname) || empty($data->clientid) || empty($data->secret))) {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
        if (!self::get_enabled_methods((array) $data)) {
            $errors['method_upi'] = get_string('nomethodsenabled', 'paygw_razorpay');
        }
        $vpa = trim($data->upivpa ?? '');
        if ($vpa !== '' && !self::is_valid_vpa($vpa)) {
            $errors['upivpa'] = get_string('upivpainvalid', 'paygw_razorpay');
        }
        if ($vpa !== '' && trim($data->upiname ?? '') === '') {
            $errors['upiname'] = get_string('required');
        }
    }
}

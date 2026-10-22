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
 * This module is responsible for razorpay content in the gateways modal.
 *
 * @module     paygw_razorpay/gateway_modal
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';
import Templates from 'core/templates';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import {getString, getStrings} from 'core/str';


/**
 * Creates and shows a modal that contains a placeholder.
 *
 * @returns {Promise<Modal>}
 */
const showModalWithPlaceholder = async() => await Modal.create({
    body: await Templates.render('paygw_razorpay/razorpay_button_placeholder', {}),
    show: true,
    removeOnClose: true,
});

/**
 * Process the payment.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @returns {Promise<string>}
 */
export const process = (component, paymentArea, itemId) => {
    let modal = null;

    const complete = response => {
        modal.setBody(getString('authorising', 'paygw_razorpay'));
        return Repository.markTransactionComplete(component, paymentArea, itemId, response.razorpay_order_id,
            response.razorpay_payment_id, response.razorpay_signature, response.method || '');
    };

    return showModalWithPlaceholder()
        .then(createdModal => {
            modal = createdModal;
            modal.getRoot().on(ModalEvents.hidden, () => {
                // Destroy when hidden.
                modal.destroy();
            });

            return Repository.getUpiDetails(component, paymentArea, itemId);
        })
        // With a UPI ID on the account, learners first choose UPI / QR code or card / netbanking.
        .then(upi => (upi.enabled ? showUpiCheckout(modal, upi) : Promise.resolve()))
        .then(() => {
            modal.setBody(Templates.render('paygw_razorpay/razorpay_button_placeholder', {}));
            return Repository.getConfigForJs(component, paymentArea, itemId);
        })
        .then(razorpayConfig => {
            if (razorpayConfig.demo) {
                // Demo account: a simulated checkout instead of Razorpay.
                return showDemoCheckout(modal, razorpayConfig).then(complete);
            }
            return switchSdk().then(() => openRazorpay(modal, razorpayConfig)).then(complete);
        })
        .then(res => {
            if (modal) {
                modal.hide();
            }
            if (res.success) {
                // eslint-disable-next-line promise/no-return-wrap
                return Promise.resolve(res.message);
            }

            // eslint-disable-next-line promise/no-return-wrap
            return Promise.reject(res.message);
        })
        .catch(error => {
            if (modal) {
                modal.hide();
            }
            // Core shows this in an alert, so it must be a string (AJAX errors are objects).
            const message = (error && typeof error === 'object') ? (error.message || error.errorcode) : error;
            // eslint-disable-next-line promise/no-return-wrap
            return Promise.reject(message);
        });
};

/**
 * Formats an amount in paise.
 *
 * @param {number} paise
 * @param {string} currency
 * @returns {string}
 */
const formatPaise = (paise, currency) => {
    try {
        return new Intl.NumberFormat('en-IN', {style: 'currency', currency}).format(paise / 100);
    } catch (e) {
        return (paise / 100).toFixed(2) + ' ' + currency;
    }
};

/**
 * Lets the learner choose between UPI / QR code, paid straight to the account's UPI ID, and card / netbanking
 * through Razorpay.
 *
 * Resolves when the learner picks card / netbanking. A UPI payment is approved later by an admin, so once its UTR
 * is sent the promise never settles: the learner just closes the window.
 *
 * @param {Modal} modal
 * @param {Object} upi details from getUpiDetails
 * @returns {Promise}
 */
const showUpiCheckout = (modal, upi) => {
    const amount = formatPaise(upi.amount, upi.currency);
    const date = new Date(upi.timesubmitted * 1000).toLocaleString('en-IN', {dateStyle: 'medium', timeStyle: 'short'});

    return getStrings([
        {key: 'upipayee', component: 'paygw_razorpay', param: upi.payee || upi.vpa},
        {key: 'upinotehint', component: 'paygw_razorpay', param: {amount, reference: upi.reference}},
        {key: 'upipending', component: 'paygw_razorpay', param: {amount, date, utr: upi.utr}},
        {key: 'upiutrinvalid', component: 'paygw_razorpay'},
        {key: 'upicopied', component: 'paygw_razorpay'},
        {key: 'paymentcancelled', component: 'paygw_razorpay'},
    ]).then(([payeetext, notehint, pendingtext, utrinvalid, copied, cancelled]) =>
        Templates.renderForPromise('paygw_razorpay/upi_checkout', {
            amount,
            vpa: upi.vpa,
            payeetext,
            link: upi.link,
            qrcode: upi.qrcode,
            notehint,
            pending: upi.pending,
            pendingtext,
        }).then(({html}) => new Promise((resolve, reject) => {
            let done = false;
            modal.setBody(html);

            const wire = root => {
                const region = name => root.querySelector(`[data-region="${name}"]`);
                const show = name => ['chooser', 'upi', 'done'].forEach(other => {
                    if (region(other)) {
                        region(other).hidden = other !== name;
                    }
                });
                const showError = message => {
                    region('error').textContent = message;
                    region('error').hidden = !message;
                };

                root.addEventListener('click', e => {
                    const button = e.target.closest('[data-action]');
                    if (!button) {
                        return;
                    }
                    const action = button.dataset.action;
                    if (action === 'razorpay') {
                        done = true;
                        resolve();
                    } else if (action === 'upi') {
                        show('upi');
                    } else if (action === 'back') {
                        showError('');
                        show('chooser');
                    } else if (action === 'copy') {
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(button.dataset.copy).then(() => {
                                button.textContent = copied;
                                return null;
                            }).catch(() => null);
                        }
                    } else if (action === 'close') {
                        done = true;
                        modal.hide();
                    } else if (action === 'submit') {
                        const input = root.querySelector('#rzp-upi-utr');
                        const utr = input.value.replace(/\s+/g, '');
                        if (!/^[A-Za-z0-9]{10,35}$/.test(utr)) {
                            showError(utrinvalid);
                            input.focus();
                            return;
                        }
                        button.disabled = true;
                        showError('');
                        Repository.submitUpiPayment(upi.reference, utr).then(result => {
                            button.disabled = false;
                            if (!result.success) {
                                showError(result.message);
                                return null;
                            }
                            done = true;
                            region('donetext').textContent = result.message;
                            show('done');
                            return null;
                        }).catch(error => {
                            button.disabled = false;
                            showError(error.message || error.errorcode || String(error));
                        });
                    }
                });
            };

            // The body is set asynchronously; wait until it is in the page.
            const waitForBody = () => {
                const root = modal.getBody()[0].querySelector('.paygw-razorpay-upi');
                if (root) {
                    wire(root);
                } else {
                    window.setTimeout(waitForBody, 20);
                }
            };
            waitForBody();

            // Closing the window before paying counts as cancelling.
            modal.getRoot().on(ModalEvents.hidden, () => {
                if (!done) {
                    reject(cancelled);
                }
            });
        }))
    );
};

/**
 * Payment methods other than UPI, in the order the checkout shows them.
 *
 * @type {string[]}
 */
const OTHER_METHODS = ['card', 'netbanking', 'wallet', 'emi', 'cardless_emi', 'paylater'];

/**
 * Builds Razorpay's checkout display config, so it shows exactly the methods the account offers.
 *
 * @param {string[]} methods enabled methods
 * @returns {Promise<Object>}
 */
const getRazorpayDisplayConfig = methods => getStrings(
    ['block_upi'].concat(OTHER_METHODS.map(method => 'label_' + method))
        .map(key => ({key, component: 'paygw_razorpay'}))
).then(([upiName, ...names]) => {
    const blocks = {};
    // UPI ID and app (intent) payments, plus the QR code: Razorpay shows the QR on desktop.
    const upiFlows = [];
    if (methods.includes('upi')) {
        upiFlows.push('collect', 'intent');
    }
    if (methods.includes('upiqr')) {
        upiFlows.push('qr');
    }
    if (upiFlows.length) {
        blocks.upi = {name: upiName, instruments: [{method: 'upi', flows: upiFlows}]};
    }
    OTHER_METHODS.forEach((method, index) => {
        if (methods.includes(method)) {
            blocks[method] = {name: names[index], instruments: [{method}]};
        }
    });
    return {
        display: {
            blocks,
            sequence: Object.keys(blocks).map(key => 'block.' + key),
            preferences: {
                // eslint-disable-next-line camelcase
                show_default_blocks: false,
            },
        },
    };
});

/**
 * Opens the Razorpay checkout and resolves with Razorpay's response once the learner has paid.
 *
 * @param {Modal} modal
 * @param {Object} razorpayConfig
 * @returns {Promise<Object>}
 */
const openRazorpay = (modal, razorpayConfig) => {
    modal.setBody('');
    const options = Object.assign({}, razorpayConfig);
    const methods = options.methods || [];
    delete options.demo;
    delete options.demotoken;
    delete options.methods;
    delete options.qrcode;

    return getRazorpayDisplayConfig(methods).then(config => new Promise((resolve, reject) => {
        options.config = config;
        options.handler = resolve;
        // Close the empty modal if the user dismisses the Razorpay checkout.
        options.modal = {
            ondismiss: () => getString('paymentcancelled', 'paygw_razorpay').then(reject),
        };
        // eslint-disable-next-line no-undef
        const rzp1 = new Razorpay(options);
        rzp1.on('payment.failed', response => reject(response.error.description));
        rzp1.open();
    }));
};

/**
 * Icons of the payment methods in the simulated checkout.
 *
 * @type {Object}
 */
const METHOD_ICONS = {
    upi: 'fa-solid fa-mobile-screen-button',
    upiqr: 'fa-solid fa-qrcode',
    card: 'fa-regular fa-credit-card',
    netbanking: 'fa-solid fa-building-columns',
    wallet: 'fa-solid fa-wallet',
    emi: 'fa-solid fa-calendar-days',
    // eslint-disable-next-line camelcase
    cardless_emi: 'fa-solid fa-hand-holding-dollar',
    paylater: 'fa-solid fa-clock',
};

/**
 * Sample providers shown by the simulated checkout. On a real account Razorpay shows its own, live list.
 *
 * @type {Object}
 */
const DEMO_PROVIDERS = {
    upiapps: ['Google Pay', 'PhonePe', 'Paytm', 'BHIM', 'Amazon Pay', 'CRED'],
    popularbanks: [['SBIN', 'State Bank of India'], ['HDFC', 'HDFC Bank'], ['ICIC', 'ICICI Bank'], ['UTIB', 'Axis Bank'],
        ['KKBK', 'Kotak Mahindra Bank'], ['PUNB', 'Punjab National Bank']],
    otherbanks: [['AUBL', 'AU Small Finance Bank'], ['BARB', 'Bank of Baroda'], ['BKID', 'Bank of India'],
        ['MAHB', 'Bank of Maharashtra'], ['CNRB', 'Canara Bank'], ['CBIN', 'Central Bank of India'], ['FDRL', 'Federal Bank'],
        ['IDFB', 'IDFC FIRST Bank'], ['IDIB', 'Indian Bank'], ['IOBA', 'Indian Overseas Bank'], ['INDB', 'IndusInd Bank'],
        ['KARB', 'Karnataka Bank'], ['RATN', 'RBL Bank'], ['SIBL', 'South Indian Bank'], ['UCBA', 'UCO Bank'],
        ['UBIN', 'Union Bank of India'], ['YESB', 'Yes Bank']],
    wallets: ['Paytm', 'PhonePe', 'Amazon Pay', 'Mobikwik', 'Freecharge', 'Airtel Payments Bank', 'JioMoney'],
    emibanks: [['HDFC', 'HDFC Bank'], ['ICIC', 'ICICI Bank'], ['SBIN', 'SBI Card'], ['UTIB', 'Axis Bank'],
        ['KKBK', 'Kotak Mahindra Bank'], ['RATN', 'RBL Bank'], ['BARB', 'Bank of Baroda'], ['AMEX', 'American Express']],
    // Annual interest rates; 0 is a no cost EMI.
    emiplans: [[3, 0], [6, 13], [9, 14], [12, 15]],
    cardlessemi: ['Bajaj Finserv', 'Home Credit', 'ZestMoney', 'TVS Credit', 'HDFC Bank', 'ICICI Bank', 'Kotak Mahindra Bank'],
    paylater: ['Simpl', 'LazyPay', 'ICICI Bank PayLater', 'HDFC Bank FlexiPay', 'Amazon Pay Later', 'Kotak PayLater'],
};

/**
 * Up to two initials, standing in for a provider's logo.
 *
 * @param {string} name
 * @returns {string}
 */
const initials = name => name.split(/\s+/).filter(word => /^[A-Z]/.test(word)).slice(0, 2)
    .map(word => word[0].toUpperCase()).join('');

/**
 * A provider entry for the template.
 *
 * @param {string|Array} provider a name, or [code, name]
 * @returns {Object}
 */
const providerContext = provider => {
    const [code, name] = Array.isArray(provider) ? provider : [provider.toLowerCase().replace(/[^a-z0-9]+/g, '_'), provider];
    return {code, key: code, name, initials: initials(name)};
};

/**
 * Card network from the first digits of a card number.
 *
 * @param {string} digits
 * @returns {string}
 */
const cardBrand = digits => {
    if (/^4/.test(digits)) {
        return 'Visa';
    }
    if (/^(5[1-5]|2[2-7])/.test(digits)) {
        return 'Mastercard';
    }
    if (/^3[47]/.test(digits)) {
        return 'Amex';
    }
    if (/^(60|65|81|82|508|353|356)/.test(digits)) {
        return 'RuPay';
    }
    if (/^3(0[0-5]|[68])/.test(digits)) {
        return 'Diners';
    }
    return '';
};

/**
 * Luhn checksum of a card number.
 *
 * @param {string} digits
 * @returns {boolean}
 */
const luhn = digits => {
    let sum = 0;
    digits.split('').reverse().forEach((digit, index) => {
        let value = parseInt(digit, 10);
        if (index % 2) {
            value *= 2;
            if (value > 9) {
                value -= 9;
            }
        }
        sum += value;
    });
    return digits.length >= 12 && sum % 10 === 0;
};

/**
 * Whether a MM/YY expiry is this month or later.
 *
 * @param {string} expiry
 * @returns {boolean}
 */
const validExpiry = expiry => {
    const match = /^(0[1-9]|1[0-2])\/(\d{2})$/.exec(expiry);
    if (!match) {
        return false;
    }
    const now = new Date();
    const year = 2000 + parseInt(match[2], 10);
    const month = parseInt(match[1], 10);
    return year > now.getFullYear() || (year === now.getFullYear() && month >= now.getMonth() + 1);
};

/**
 * Shows the simulated checkout of a demo account and resolves with a response shaped like Razorpay's.
 *
 * Every payment method the account offers gets its own tab, so the whole flow can be tried without real keys.
 *
 * @param {Modal} modal
 * @param {Object} config
 * @returns {Promise<Object>}
 */
const showDemoCheckout = (modal, config) => {
    const amountValue = config.amount / 100;
    const format = value => {
        try {
            return new Intl.NumberFormat('en-IN', {style: 'currency', currency: config.currency}).format(value);
        } catch (e) {
            // Unknown currency: keep the plain amount.
            return value.toFixed(2) + ' ' + config.currency;
        }
    };
    const amount = format(amountValue);
    const methods = config.methods && config.methods.length ? config.methods : ['upi'];

    const plans = DEMO_PROVIDERS.emiplans.map(([months, rate]) => {
        const monthlyrate = rate / 1200;
        const monthly = rate ? amountValue * monthlyrate * Math.pow(1 + monthlyrate, months) /
            (Math.pow(1 + monthlyrate, months) - 1) : amountValue / months;
        return {months, rate, nocost: !rate, monthly: format(monthly), total: format(monthly * months)};
    });

    const stringKeys = ['demo_order', 'demo_paynow', 'demo_failed', 'paymentcancelled', 'demo_upiidinvalid',
        'demo_cardinvalid', 'demo_selectoption', 'demo_upiwaiting', 'demo_qrexpires']
        .concat(methods.map(method => 'label_' + method));
    // Filled in later, so keep the placeholder.
    const params = {'demo_order': config.order_id, 'demo_paynow': amount, 'demo_upiwaiting': '{$a}', 'demo_qrexpires': '{$a}'};
    const planStrings = [];
    plans.forEach(plan => {
        planStrings.push({key: 'demo_emimonthly', component: 'paygw_razorpay', param: {monthly: plan.monthly, months: plan.months}},
            {key: 'demo_emiinterest', component: 'paygw_razorpay', param: plan.rate},
            {key: 'demo_emitotal', component: 'paygw_razorpay', param: plan.total});
    });

    return getStrings(stringKeys.map(key => ({key, component: 'paygw_razorpay', param: params[key]})).concat(planStrings))
    .then(strings => {
        const str = {};
        stringKeys.forEach((key, index) => {
            str[key] = strings[index];
        });
        plans.forEach((plan, index) => {
            const offset = stringKeys.length + index * 3;
            plan.label = strings[offset];
            plan.ratelabel = strings[offset + 1];
            plan.totallabel = strings[offset + 2];
        });

        const context = {
            name: config.name,
            description: config.description,
            ordertext: str.demo_order,
            amount,
            paylabel: str.demo_paynow,
            qrcode: config.qrcode,
            methods: methods.map((key, index) => ({key, label: str['label_' + key], icon: METHOD_ICONS[key], first: index === 0})),
            upiapps: DEMO_PROVIDERS.upiapps.map(providerContext),
            popularbanks: DEMO_PROVIDERS.popularbanks.map(providerContext),
            otherbanks: DEMO_PROVIDERS.otherbanks.map(providerContext),
            wallets: DEMO_PROVIDERS.wallets.map(providerContext),
            emibanks: DEMO_PROVIDERS.emibanks.map(providerContext),
            emiplans: plans,
            cardlessemi: DEMO_PROVIDERS.cardlessemi.map(providerContext),
            paylater: DEMO_PROVIDERS.paylater.map(providerContext),
        };
        methods.forEach(method => {
            context['has' + method] = true;
        });
        return Templates.renderForPromise('paygw_razorpay/demo_checkout', context).then(({html}) => ({html, str}));
    })
    .then(({html, str}) => new Promise((resolve, reject) => {
        modal.setLarge();
        modal.setBody(html);
        // The body is set asynchronously; wait until it is in the page.
        const waitForBody = () => {
            const root = modal.getBody()[0].querySelector('.paygw-razorpay-demo');
            if (root) {
                wireDemoCheckout(modal, root, config, methods, str, resolve, reject);
            } else {
                window.setTimeout(waitForBody, 20);
            }
        };
        waitForBody();
    }));
};

/**
 * Adds the behaviour to the simulated checkout.
 *
 * @param {Modal} modal
 * @param {HTMLElement} root
 * @param {Object} config
 * @param {string[]} methods
 * @param {Object} str
 * @param {Function} resolve
 * @param {Function} reject
 */
const wireDemoCheckout = (modal, root, config, methods, str, resolve, reject) => {
    let done = false;
    let qrTimer = null;
    const panels = root.querySelector('.rzp-demo-panels');
    const errorBox = root.querySelector('[data-region="error"]');
    const processing = root.querySelector('.rzp-demo-processing');

    const showError = message => {
        errorBox.textContent = message;
        errorBox.hidden = !message;
    };

    const stopQrTimer = () => {
        if (qrTimer) {
            window.clearInterval(qrTimer);
            qrTimer = null;
        }
    };

    const startQrTimer = () => {
        const panel = root.querySelector('[data-panel="upiqr"]');
        if (!panel) {
            return;
        }
        stopQrTimer();
        const timer = panel.querySelector('[data-region="qrtimer"]');
        const expired = panel.querySelector('.rzp-demo-qr-expired');
        const payButton = panel.querySelector('[data-action="pay"]');
        let left = 300;
        expired.hidden = true;
        payButton.disabled = false;
        const tick = () => {
            const text = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
            timer.textContent = str.demo_qrexpires.replace('{$a}', text);
            if (left-- <= 0) {
                stopQrTimer();
                timer.textContent = '';
                expired.hidden = false;
                payButton.disabled = true;
            }
        };
        tick();
        qrTimer = window.setInterval(tick, 1000);
    };

    const showPanel = method => {
        root.querySelectorAll('.rzp-demo-methods [data-method]').forEach(tab => {
            const active = tab.dataset.method === method;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        root.querySelectorAll('.rzp-demo-panel').forEach(panel => {
            panel.hidden = panel.dataset.panel !== method;
        });
        showError('');
        if (method === 'upiqr') {
            startQrTimer();
        } else {
            stopQrTimer();
        }
    };

    const succeed = method => {
        done = true;
        stopQrTimer();
        root.querySelectorAll('.rzp-demo-panel').forEach(panel => {
            panel.hidden = true;
        });
        showError('');
        processing.hidden = false;
        root.querySelectorAll('button').forEach(button => {
            button.disabled = true;
        });
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        const random = Array.from(window.crypto.getRandomValues(new Uint8Array(14)), b => chars[b % chars.length]).join('');
        window.setTimeout(() => resolve({
            // eslint-disable-next-line camelcase
            razorpay_order_id: config.order_id,
            // eslint-disable-next-line camelcase
            razorpay_payment_id: 'pay_DEMO' + random,
            // eslint-disable-next-line camelcase
            razorpay_signature: config.demotoken,
            method,
        }), 1200);
    };

    const checks = {
        vpa: () => {
            const input = root.querySelector('#rzp-demo-vpa');
            const vpa = input.value.trim();
            if (!/^[A-Za-z0-9._-]{2,256}@[A-Za-z][A-Za-z0-9]{1,63}$/.test(vpa)) {
                showError(str.demo_upiidinvalid);
                input.focus();
                return false;
            }
            // A collect request: the learner approves it in their UPI app.
            const waiting = root.querySelector('.rzp-demo-waiting');
            waiting.querySelector('[data-region="waitingtext"]').textContent = str.demo_upiwaiting.replace('{$a}', vpa);
            waiting.hidden = false;
            showError('');
            return false;
        },
        card: () => {
            const digits = root.querySelector('#rzp-demo-cardnumber').value.replace(/\D/g, '');
            const expiry = root.querySelector('#rzp-demo-cardexpiry').value.trim();
            const cvv = root.querySelector('#rzp-demo-cardcvv').value.trim();
            const cvvLength = cardBrand(digits) === 'Amex' ? 4 : 3;
            if (!luhn(digits) || !validExpiry(expiry) || !new RegExp('^\\d{' + cvvLength + '}$').test(cvv)) {
                showError(str.demo_cardinvalid);
                return false;
            }
            return true;
        },
        choice: panel => {
            const picked = panel.querySelector('input[type="radio"]:checked');
            const other = panel.querySelector('[data-region="otherbank"]');
            if (!picked && !(other && other.value)) {
                showError(str.demo_selectoption);
                return false;
            }
            return true;
        },
    };

    root.addEventListener('click', e => {
        const tab = e.target.closest('.rzp-demo-methods [data-method]');
        if (tab) {
            showPanel(tab.dataset.method);
            return;
        }
        const button = e.target.closest('[data-action]');
        if (!button || done) {
            return;
        }
        const action = button.dataset.action;
        if (action === 'pay') {
            const check = checks[button.dataset.check];
            if (!check || check(button.closest('.rzp-demo-panel'))) {
                succeed(button.dataset.method);
            }
        } else if (action === 'approve') {
            succeed('upi');
        } else if (action === 'qrrefresh') {
            startQrTimer();
        } else if (action === 'fail') {
            done = true;
            stopQrTimer();
            reject(str.demo_failed);
        }
    });

    // Picking a provider highlights it; a popular bank and the "other banks" list exclude each other.
    root.addEventListener('change', e => {
        const target = e.target;
        const panel = target.closest('.rzp-demo-panel');
        if (!panel) {
            return;
        }
        if (target.type === 'radio') {
            panel.querySelectorAll('.rzp-demo-choice').forEach(choice => {
                choice.classList.toggle('selected', choice.contains(target));
            });
            const other = panel.querySelector('[data-region="otherbank"]');
            if (other) {
                other.value = '';
            }
        } else if (target.dataset.region === 'otherbank' && target.value) {
            panel.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.checked = false;
            });
            panel.querySelectorAll('.rzp-demo-choice').forEach(choice => choice.classList.remove('selected'));
        }
        showError('');
    });

    // Card number in groups of four with the network shown; expiry as MM/YY.
    const cardNumber = root.querySelector('#rzp-demo-cardnumber');
    if (cardNumber) {
        cardNumber.addEventListener('input', () => {
            const digits = cardNumber.value.replace(/\D/g, '').slice(0, 19);
            cardNumber.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
            root.querySelector('[data-region="cardbrand"]').textContent = cardBrand(digits);
        });
        const expiry = root.querySelector('#rzp-demo-cardexpiry');
        expiry.addEventListener('input', e => {
            let digits = expiry.value.replace(/\D/g, '').slice(0, 4);
            if (digits.length > 2 || (digits.length === 2 && e.inputType !== 'deleteContentBackward')) {
                digits = digits.slice(0, 2) + '/' + digits.slice(2);
            }
            expiry.value = digits;
        });
    }

    // Closing the modal counts as cancelling the payment.
    modal.getRoot().on(ModalEvents.hidden, () => {
        stopQrTimer();
        if (!done) {
            reject(str.paymentcancelled);
        }
    });

    panels.hidden = false;
    showPanel(methods[0]);
};

/**
 * Unloads the previously loaded razorpay JavaScript SDK, and loads a new one.
 *
 * @returns {Promise}
 */
const switchSdk = () => {
    const sdkUrl = 'https://checkout.razorpay.com/v1/checkout.js';

    // Check to see if this file has already been loaded. If so just go straight to the func.
    if (switchSdk.currentlyloaded === sdkUrl) {
        return Promise.resolve();
    }
    if (switchSdk.currentlyloaded) {
        const suspectedScript = document.querySelector(`script[src="${switchSdk.currentlyloaded}"]`);
        if (suspectedScript) {
            suspectedScript.parentNode.removeChild(suspectedScript);
        }
    }

    const script = document.createElement('script');

    return new Promise(resolve => {
        if (script.readyState) {
            script.onreadystatechange = function() {
                if (this.readyState === 'complete' || this.readyState === 'loaded') {
                    this.onreadystatechange = null;
                    resolve();
                }
            };
        } else {
            script.onload = function() {
                resolve();
            };
        }

        script.setAttribute('src', sdkUrl);
        document.head.appendChild(script);

        switchSdk.currentlyloaded = sdkUrl;
    });
};

/**
 * Holds the full url of loaded razorpay JavaScript SDK.
 *
 * @static
 * @type {string}
 */
switchSdk.currentlyloaded = '';

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
 * Strings for the Online payment gateway.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['alreadypaid'] = 'This order has already been paid.';
$string['amounttopay'] = 'Amount to pay';
$string['approve'] = 'Approve in UPI app';
$string['brandname'] = 'Name shown at checkout';
$string['brandname_help'] = 'Your institute name, shown at the top of the checkout and in the UPI QR code.';
$string['cancelled'] = 'Payment was cancelled.';
$string['cardcvv'] = 'CVV';
$string['cardexpiry'] = 'Expiry (MM/YY)';
$string['cardinvalid'] = 'Check the card number, expiry date and CVV.';
$string['cardname'] = 'Name on card';
$string['cardnumber'] = 'Card number';
$string['cardsecure'] = 'Your full card number never leaves this page.';
$string['choosebank'] = 'Choose your bank';
$string['choosemethod'] = 'Payment method';
$string['chooseoption'] = 'Choose an option to continue.';
$string['chooseplan'] = 'Choose an EMI plan';
$string['choosewallet'] = 'Choose a wallet';
$string['detailsinvalid'] = 'The payment details are not valid. Please try again.';
$string['emibank'] = 'Card issuing bank';
$string['emiinterest'] = '{$a}% p.a.';
$string['emimonthly'] = '{$a->monthly}/month × {$a->months} months';
$string['eminocost'] = 'No cost EMI';
$string['emitotal'] = 'Total {$a}';
$string['gatewaydescription'] = 'UPI, QR code, debit/credit card, netbanking, wallets and EMI.';
$string['gatewayname'] = 'Online payment (UPI / Card / Netbanking)';
$string['label_card'] = 'Card';
$string['label_emi'] = 'EMI';
$string['label_netbanking'] = 'Netbanking';
$string['label_qr'] = 'QR code';
$string['label_upi'] = 'UPI';
$string['label_wallet'] = 'Wallets';
$string['method_card'] = 'Debit and credit cards (Visa, Mastercard, RuPay, Amex, Diners)';
$string['method_emi'] = 'EMI on cards';
$string['method_netbanking'] = 'Netbanking';
$string['method_qr'] = 'UPI QR code';
$string['method_upi'] = 'UPI (UPI ID and UPI apps)';
$string['method_wallet'] = 'Wallets';
$string['methodnotavailable'] = 'This payment method is not available.';
$string['methods'] = 'Payment methods';
$string['methods_desc'] = 'The ways learners can pay.';
$string['modelnotice'] = 'Model checkout: no money is charged. Use it to try the payment flow.';
$string['modelnotice_admin'] = 'This is a model checkout: it shows every payment mode, but no payment provider is connected, so no money is collected and every completed checkout gives access for free. Do not offer it to real learners until a payment provider is connected.';
$string['nomethods'] = 'Enable at least one payment method.';
$string['ordernotfound'] = 'This order was not found. Please close this window and try again.';
$string['orderexpired'] = 'This checkout has expired. Please close this window and start again.';
$string['orderref'] = 'Order {$a}';
$string['otherbanks'] = 'Other banks';
$string['paybutton'] = 'Pay {$a}';
$string['paymentdone'] = 'Payment successful (order {$a}).';
$string['pluginname'] = 'Online payment';
$string['pluginname_desc'] = 'One checkout with UPI, QR code, cards, netbanking, wallets and EMI.';
$string['privacy:metadata:order'] = 'Orders started in the Online payment checkout';
$string['privacy:metadata:order:amount'] = 'The amount';
$string['privacy:metadata:order:method'] = 'How it was paid, for example UPI or card';
$string['privacy:metadata:order:methoddetail'] = 'The UPI app, bank, wallet, or card network and last four digits';
$string['privacy:metadata:order:timecreated'] = 'When the checkout started';
$string['privacy:metadata:order:userid'] = 'The user paying';
$string['processing'] = 'Processing payment...';
$string['qrexpired'] = 'This QR code has expired.';
$string['qrexpires'] = 'Expires in {$a}';
$string['qrpaid'] = 'I have paid';
$string['qrrefresh'] = 'Show again';
$string['qrscan'] = 'Scan with any UPI app: Google Pay, PhonePe, Paytm, BHIM, Amazon Pay or your bank app.';
$string['upiapps'] = 'Pay with a UPI app';
$string['upiid'] = 'Enter your UPI ID';
$string['upiidinvalid'] = 'Enter a valid UPI ID, for example name@okicici.';
$string['upiidplaceholder'] = 'example@okhdfcbank';
$string['upivpa'] = 'Your UPI ID';
$string['upivpa_help'] = 'Optional, for example institute@okhdfcbank. With Razorpay, when UPI is not activated on the Razorpay account, learners can pay by UPI straight to this UPI ID: they scan a QR code for the exact amount, then enter the 12-digit UTR, and an admin approves the payment under Site administration > Plugins > Payment gateways > UPI payments to verify. Leave empty to offer UPI only through Razorpay. With the model checkout it is the UPI ID in the QR code.';
$string['upivpainvalid'] = 'Enter a valid UPI ID, for example institute@okhdfcbank.';
$string['upiwaiting'] = 'A payment request was sent to {$a}. Open your UPI app and approve it.';
$string['verifypay'] = 'Verify and pay';
$string['cachedef_razorpaymethods'] = 'Payment methods activated on each Razorpay account';
$string['keyid'] = 'Razorpay Key ID';
$string['keyid_help'] = 'From the Razorpay Dashboard: Account & Settings > API Keys. Use a key starting with rzp_test_ to try payments with test cards and test UPI IDs, and rzp_live_ to take real money.';
$string['keyidinvalid'] = 'Enter a Razorpay Key ID, starting with rzp_test_ or rzp_live_.';
$string['keysecret'] = 'Razorpay Key Secret';
$string['nomethodsavailable'] = 'No payment method is available right now. Please contact us.';
$string['paymentnotverified'] = 'We could not confirm this payment with Razorpay. If money was taken, it will be refunded automatically by Razorpay; please contact us with the order number.';
$string['provider'] = 'Take payments with';
$string['provider_help'] = 'Razorpay: real payments, through your Razorpay account. Each payment is confirmed with Razorpay before access is given. Only the methods activated on your Razorpay account are offered.

Model: the same checkout with no payment provider. No money is collected and access is given for free; for trying the flow only.';
$string['provider_model'] = 'Model (no money is collected)';
$string['provider_razorpay'] = 'Razorpay';
$string['razorpayerror'] = 'Razorpay error: {$a}';
$string['razorpaystatus'] = 'Activated on your Razorpay account';
$string['razorpaystatus_desc'] = 'Only ✅ methods are offered at checkout, each in Razorpay\'s own secure window (the UPI QR code is part of UPI). UPI is offered only once Razorpay has activated UPI itself on the account. Ask Razorpay to activate the others (Dashboard > Account & Settings > Payment methods).';
$string['rzpnote_card'] = 'Credit, debit and prepaid cards: Visa, Mastercard, RuPay and more. You enter your card details and OTP in Razorpay\'s secure window.';
$string['rzpnote_emi'] = 'Pay in monthly instalments on your card. The banks, plans and interest are shown in Razorpay\'s secure window.';
$string['rzpnote_netbanking'] = 'Choose your bank in Razorpay\'s secure window, then log in to your bank to pay.';
$string['rzpnote_upi'] = 'Google Pay, PhonePe, Paytm, BHIM or any UPI app. Razorpay\'s secure window shows a QR code to scan, or lets you enter your UPI ID or open the app on this phone.';
$string['rzpnote_wallet'] = 'Choose your wallet in Razorpay\'s secure window.';
$string['securedby'] = 'Payments are processed securely by Razorpay.';
$string['amount'] = 'Amount';
$string['messageprovider:upireviewed'] = 'Your UPI payment was approved or rejected';
$string['messageprovider:upisubmitted'] = 'A UPI payment is waiting for approval';
$string['privacy:metadata:order:utr'] = 'The UPI transaction reference (UTR) the user sent for a UPI payment.';
$string['upialreadyreviewed'] = 'This payment was already approved or rejected.';
$string['upiapprove'] = 'Approve';
$string['upiapproveconfirm'] = 'Approve this payment? Only approve once you have found UTR {$a->utr} for {$a->amount} in your bank account. The learner gets access straight away.';
$string['upiapproved_body'] = 'Your UPI payment of {$a->amount} for {$a->item} (UTR {$a->utr}, order {$a->reference}) was verified. You now have access.';
$string['upiapproved_subject'] = 'Payment verified: {$a->item}';
$string['upiapproveddone'] = 'Payment approved. The learner now has access.';
$string['upidirect_amount'] = 'Pay exactly {$a}';
$string['upidirect_intro'] = 'Scan this QR code with any UPI app: Google Pay, PhonePe, Paytm, BHIM or your bank app.';
$string['upidirect_openapp'] = 'Open UPI app';
$string['upidirect_payto'] = 'Or pay to UPI ID';
$string['upidirect_step2'] = 'After paying, enter the 12-digit UTR (UPI reference number) from the payment details in your UPI app.';
$string['upiitem'] = 'For';
$string['upinopending'] = 'No UPI payments are waiting for approval.';
$string['upiorder'] = 'Order';
$string['upipayments'] = 'UPI payments to verify';
$string['upipayments_desc'] = 'Learners who paid by UPI straight to your UPI ID are listed here. Find each UTR and amount in your bank account or UPI app, then approve the payment to give access, or reject it if no such payment arrived.';
$string['upipending'] = 'Waiting for approval ({$a})';
$string['upirecentapproved'] = 'Recently approved';
$string['upirecentrejected'] = 'Recently rejected';
$string['upireject'] = 'Reject';
$string['upirejectconfirm'] = 'Reject the payment with UTR {$a}? The learner will be told it could not be verified.';
$string['upirejected_body'] = 'We could not find your UPI payment of {$a->amount} for {$a->item} (UTR {$a->utr}, order {$a->reference}). If money was taken from your account, please reply with a screenshot of the payment and we will sort it out.';
$string['upirejected_subject'] = 'Payment not verified: {$a->item}';
$string['upirejecteddone'] = 'Payment rejected. The learner has been told.';
$string['upisubmitted_body'] = '{$a->user} paid {$a->amount} by UPI for {$a->item} (order {$a->reference}) and sent UTR {$a->utr}. Check it in your bank account, then approve or reject it: {$a->url}';
$string['upisubmitted_subject'] = 'UPI payment to verify: {$a->amount} from {$a->user}';
$string['upisubmittedon'] = 'Sent';
$string['utr'] = 'UTR';
$string['utrinvalid'] = 'Enter the 12-digit UTR number from your UPI app.';
$string['utrplaceholder'] = '12-digit UTR, e.g. 412345678901';
$string['utrsubmit'] = 'Submit payment';
$string['utrsubmitted'] = 'Thank you! We received your payment details (UTR {$a->utr}, order {$a->reference}). Your access starts as soon as we verify the payment, usually within a few hours. You will get a notification.';
$string['utrused'] = 'This UTR has already been sent. Check the number in your UPI app.';
$string['upistatus_direct'] = '(paid to your UPI ID {$a}, approved by you: Razorpay has not activated UPI)';
$string['upistatus_off'] = '(not activated by Razorpay: enter your UPI ID below to accept UPI payments now)';
$string['utrpending'] = 'Your payment for this order is already waiting for verification.';

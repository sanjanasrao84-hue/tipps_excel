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
 * Strings for component 'paygw_razorpay', language 'en'
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['amountmismatch'] = 'The amount you attempted to pay does not match the required fee. Your account has not been debited.';
$string['authorising'] = 'Authorising the payment. Please wait...';
$string['paymentcancelled'] = 'Payment was cancelled.';
$string['brandname'] = 'Brand name';
$string['brandname_help'] = 'An optional label that overrides the business name for the razorpay account on the razorpay site.';
$string['cannotfetchorderdatails'] = 'Could not fetch payment details from razorpay. Your account has not been debited.';
$string['clientid'] = 'Client ID';
$string['clientid_help'] = 'The client ID that razorpay generated for your application.';
$string['description'] = 'Enrolment Fees for the Course: {$a}';
$string['environment'] = 'Environment';
$string['environment_help'] = 'You can set this to Sandbox if you are using sandbox accounts (for testing purpose only). Demo does not contact Razorpay at all: learners see a simulated checkout and every "payment" is free. Never use Demo on a live site.';
$string['demo'] = 'Demo (simulated payments, no Razorpay)';
$string['demo_fail'] = 'Simulate failed payment';
$string['demo_failed'] = 'Payment failed (demo).';
$string['demo_heading'] = 'Demo checkout';
$string['demo_notice'] = 'Demo mode: no money is charged and Razorpay is not contacted. Switch the payment account to Live or Sandbox before real use.';
$string['demo_pay'] = 'Simulate successful payment of {$a}';
$string['demo_order'] = 'Order {$a}';
$string['eventordercreated'] = 'Razorpay Order Created';
$string['eventpaymentdone'] = 'Online Payment Successful';
$string['gatewaydescription'] = 'Choose this option if you want to pay the fee in online mode';
$string['gatewayname'] = 'Razorpay';
$string['internalerror'] = 'An internal error has occurred. Please contact us.';
$string['live'] = 'Live';
$string['ordermismatch'] = 'This payment does not match the order created for you. Your access has not been changed; please contact us if you were charged.';
$string['paymentalreadyprocessed'] = 'This payment has already been processed.';
$string['paymentnotcleared'] = 'payment not cleared by razorpay.';
$string['pluginname'] = 'Razorpay';
$string['pluginname_desc'] = 'The razorpay plugin allows you to receive payments via razorpay.';
$string['privacy:metadata:paygw_razorpay_com'] = 'Shares the required user data with Razorpay for processing payments';
$string['privacy:metadata:paygw_razorpay_com:email'] = 'Email of the user requesting a transaction';
$string['privacy:metadata:paygw_razorpay_com:name'] = 'Full name of the user requesting a transaction';
$string['privacy:metadata:paygw_razorpay_com:phone1'] = 'Phone number of the user requesting a transaction';
$string['privacy:metadata:paygw_razorpay_order'] = 'Orders created on Razorpay, saved so each payment can be checked against them';
$string['privacy:metadata:paygw_razorpay_order:amount'] = 'The order amount in paise';
$string['privacy:metadata:paygw_razorpay_order:rp_orderid'] = 'The Razorpay order id';
$string['privacy:metadata:paygw_razorpay_order:timecreated'] = 'When the order was created';
$string['privacy:metadata:paygw_razorpay_order:userid'] = 'The user the order was created for';
$string['repeatedorder'] = 'This order has already been processed earlier.';
$string['sandbox'] = 'Sandbox';
$string['secret'] = 'Secret';
$string['secret_help'] = 'The secret that razorpay generated for your application.';
$string['daysleft'] = '{$a} days';
$string['expiredbanner'] = 'Your access to <strong>{$a->course}</strong> expired on {$a->date}. <a href="{$a->renewurl}">Renew now</a> to continue learning.';
$string['expiredmessagebody'] = 'Hello,

Your access to the course "{$a->course}" expired on {$a->date}.

To continue, renew your subscription here:
{$a->renewurl}';
$string['expiredmessagesubject'] = 'Your access to {$a->course} has expired';
$string['expirybanner'] = 'Show expiry banner';
$string['expirybanner_desc'] = 'Show a message at the top of every page to students whose paid access is about to expire or recently expired, with a link to renew.';
$string['expirybannertext'] = 'Your access to <strong>{$a->course}</strong> expires in {$a->timeleft} (on {$a->date}). <a href="{$a->renewurl}">Renew now</a>';
$string['expirynotify'] = 'Send expiry messages';
$string['expirynotify_desc'] = 'Send students a message (email and notification) before their paid access expires, and when it has expired.';
$string['expirysettings'] = 'Subscription expiry';
$string['expirysettings_desc'] = 'Applies to "Enrolment on payment" courses that have an enrolment duration and use a payment account with Razorpay enabled. Messages are sent by the scheduled task, so cron must be running.';
$string['expirywarndays'] = 'Warn days before expiry';
$string['expirywarndays_desc'] = 'Comma-separated number of days before expiry when a warning is sent, for example 7,3,1. The banner is shown from the largest value onwards.';
$string['expirywarningbody'] = 'Hello,

Your access to the course "{$a->course}" expires in {$a->timeleft}, on {$a->date}.

Renew now to keep your access without interruption. Any remaining days are added to the new period:
{$a->renewurl}';
$string['expirywarningsubject'] = 'Your access to {$a->course} expires in {$a->timeleft}';
$string['messageprovider:expired'] = 'Paid course access has expired';
$string['messageprovider:expirywarning'] = 'Paid course access is about to expire';
$string['onedayleft'] = '1 day';
$string['renewalunavailable'] = 'Renewal is not available for this course at the moment. Please contact the site administrator.';
$string['renewcurrentstatus'] = 'Current access';
$string['renewnewexpiry'] = 'Access after renewal until';
$string['renewnow'] = 'Renew now';
$string['renewperiod'] = 'Renewal period';
$string['renewstatusactive'] = 'Active until {$a}';
$string['renewstatusexpired'] = 'Expired on {$a}';
$string['renewstatusunlimited'] = 'Active, no expiry';
$string['renewtitle'] = 'Renew access: {$a}';
$string['sendexpirynotificationstask'] = 'Send paid enrolment expiry warnings';
$string['paymentmethods'] = 'Payment methods';
$string['paymentmethods_desc'] = 'The ways learners can pay. Razorpay only offers a method that is also activated on your Razorpay account (EMI, Cardless EMI and Pay Later usually need activating in the Razorpay dashboard).';
$string['nomethodsenabled'] = 'Enable at least one payment method.';
$string['method_upi'] = 'UPI: UPI ID and UPI apps (Google Pay, PhonePe, Paytm, BHIM...)';
$string['method_upiqr'] = 'UPI QR code (scan and pay)';
$string['method_card'] = 'Debit and credit cards (Visa, Mastercard, RuPay, Amex...)';
$string['method_netbanking'] = 'Netbanking';
$string['method_wallet'] = 'Wallets (Paytm, PhonePe, Amazon Pay, Mobikwik...)';
$string['method_emi'] = 'EMI on credit and debit cards';
$string['method_cardless_emi'] = 'Cardless EMI';
$string['method_paylater'] = 'Pay Later';
$string['label_upi'] = 'UPI';
$string['label_upiqr'] = 'Scan QR code';
$string['label_card'] = 'Card';
$string['label_netbanking'] = 'Netbanking';
$string['label_wallet'] = 'Wallets';
$string['label_emi'] = 'EMI';
$string['label_cardless_emi'] = 'Cardless EMI';
$string['label_paylater'] = 'Pay Later';
$string['block_upi'] = 'Pay using UPI';
$string['demo_approve'] = 'Simulate approval in the UPI app';
$string['demo_cardcvv'] = 'CVV';
$string['demo_cardexpiry'] = 'Expiry (MM/YY)';
$string['demo_cardinvalid'] = 'Check the card details. For the demo, use 4111 1111 1111 1111 with any future expiry and any CVV.';
$string['demo_cardname'] = 'Name on card';
$string['demo_cardnumber'] = 'Card number';
$string['demo_choosemethod'] = 'Payment method';
$string['demo_emibank'] = 'Card issuing bank';
$string['demo_emiinterest'] = '{$a}% p.a.';
$string['demo_emimonthly'] = '{$a->monthly}/month for {$a->months} months';
$string['demo_eminocost'] = 'No cost EMI';
$string['demo_emitenure'] = 'Choose a plan';
$string['demo_emitotal'] = 'Total {$a}';
$string['demo_otherbanks'] = 'Other banks';
$string['demo_paynow'] = 'Pay {$a}';
$string['demo_processing'] = 'Processing payment...';
$string['demo_qrexpired'] = 'This QR code has expired.';
$string['demo_qrexpires'] = 'QR code expires in {$a}';
$string['demo_qrnote'] = 'Demo QR code: it points at a dummy UPI ID, so no real app can pay it.';
$string['demo_qrpaid'] = 'Simulate: I have scanned and paid';
$string['demo_qrrefresh'] = 'Get a new QR code';
$string['demo_scanqr'] = 'Scan with any UPI app: Google Pay, PhonePe, Paytm, BHIM, Amazon Pay, CRED or your bank\'s app.';
$string['demo_selectbank'] = 'Select your bank';
$string['demo_selectoption'] = 'Select an option to continue.';
$string['demo_selectprovider'] = 'Select a provider';
$string['demo_selectwallet'] = 'Select a wallet';
$string['demo_upiapps'] = 'Pay with a UPI app';
$string['demo_upiid'] = 'Enter your UPI ID';
$string['demo_upiidinvalid'] = 'Enter a valid UPI ID, for example name@okicici.';
$string['demo_upiidplaceholder'] = 'example@okhdfcbank';
$string['demo_upiwaiting'] = 'A payment request was sent to {$a}. Open your UPI app and approve it.';
$string['demo_verifypay'] = 'Verify and pay';
$string['privacy:metadata:paygw_razorpay_order:method'] = 'How the order was paid, for example UPI or card';
$string['directupi'] = 'UPI / QR code to your own UPI ID';
$string['directupi_desc'] = 'Lets learners pay straight to your UPI ID by scanning a QR code or opening any UPI app, even when UPI is not activated on Razorpay. The learner enters the UTR number of the payment, and an admin checks it against the bank statement and approves it on the "UPI payments" page before access is given. Leave the UPI ID empty to turn this off.';
$string['upivpa'] = 'Your UPI ID';
$string['upivpa_help'] = 'The UPI ID (VPA) learners pay, for example institute@okhdfcbank or 9876543210@ybl. It is shown with a QR code at checkout. A UPI ID linked to a current or merchant account is best for collecting fees.';
$string['upivpainvalid'] = 'Enter a valid UPI ID, for example institute@okhdfcbank.';
$string['upiname'] = 'Name on the UPI ID';
$string['upiname_help'] = 'The account holder name, exactly as UPI apps show it, so learners can check they are paying the right person.';
$string['upichoose'] = 'How would you like to pay?';
$string['upichoose_upi'] = 'UPI / Scan QR code';
$string['upichoose_upi_desc'] = 'Google Pay, PhonePe, Paytm, BHIM or any UPI app';
$string['upichoose_razorpay'] = 'Card / Netbanking';
$string['upichoose_razorpay_desc'] = 'Debit card, credit card or netbanking via Razorpay';
$string['upiscan'] = 'Scan this QR code with any UPI app';
$string['upiorpayto'] = 'or pay to this UPI ID';
$string['upiopenapp'] = 'Open UPI app';
$string['upicopy'] = 'Copy';
$string['upicopied'] = 'Copied';
$string['upiamounttopay'] = 'Amount to pay';
$string['upipayee'] = 'Paying {$a}';
$string['upinotehint'] = 'Pay exactly {$a->amount}. If your app lets you add a note, write {$a->reference}.';
$string['upistep_pay'] = '1. Pay with any UPI app';
$string['upistep_utr'] = '2. Enter the UTR / transaction number';
$string['upiutr'] = 'UTR / UPI transaction number';
$string['upiutr_hint'] = 'After paying, open the payment in your UPI app: the 12-digit UTR (also called UPI Ref No. or Transaction ID) is shown there.';
$string['upiutrplaceholder'] = 'e.g. 412345678901';
$string['upisubmit'] = 'Submit payment';
$string['upiutrinvalid'] = 'Enter the UTR exactly as your UPI app shows it: usually 12 digits.';
$string['upiutrused'] = 'This UTR has already been submitted. If you think this is a mistake, please contact us.';
$string['upirequestnotfound'] = 'This payment request has expired or was already submitted. Please close this window and try again.';
$string['upisubmitted'] = 'Thank you! Your payment has been submitted. We will check it and give you access shortly; you will get a notification and an email once it is approved.';
$string['upipendingtitle'] = 'Payment waiting for approval';
$string['upipending'] = 'You paid {$a->amount} on {$a->date} (UTR {$a->utr}). We are checking it and will give you access as soon as it is approved.';
$string['upidone'] = 'Close';
$string['upiback'] = 'Back';
$string['upipayments'] = 'UPI payments';
$string['upipayments_desc'] = 'Payments learners made straight to your UPI ID. Find each UTR in your bank statement or UPI app, check the amount, then approve it to give the learner access, or reject it with a reason.';
$string['upistatus_pending'] = 'Waiting for approval';
$string['upistatus_approved'] = 'Approved';
$string['upistatus_rejected'] = 'Rejected';
$string['upinone'] = 'There are no payments here.';
$string['upiitem'] = 'For';
$string['upiamount'] = 'Amount';
$string['upireference'] = 'Reference';
$string['upipaidto'] = 'Paid to';
$string['upireviewed'] = 'Reviewed';
$string['upiapprove'] = 'Approve';
$string['upiapproveconfirm'] = 'Approve this payment and give access? Only do this if UTR {$a} is in your bank statement with the right amount.';
$string['upireject'] = 'Reject';
$string['upirejectreason'] = 'Reason (shown to the learner)';
$string['upiapproveddone'] = 'Payment approved: the learner now has access.';
$string['upirejecteddone'] = 'Payment rejected: the learner has been told.';
$string['upialreadyreviewed'] = 'This payment was already approved or rejected.';
$string['upiadminsubject'] = 'UPI payment to approve: {$a->amount} from {$a->learner}';
$string['upiadminbody'] = '{$a->learner} says they paid {$a->amount} by UPI for "{$a->item}".

UTR: {$a->utr}
Reference: {$a->reference}

Check the UTR in your bank statement, then approve or reject it here:
{$a->url}';
$string['upiapprovedsubject'] = 'Payment approved: {$a->item}';
$string['upiapprovedbody'] = 'Hello,

Your UPI payment of {$a->amount} (UTR {$a->utr}) has been approved. You now have access to "{$a->item}".';
$string['upirejectedsubject'] = 'Payment not approved: {$a->item}';
$string['upirejectedbody'] = 'Hello,

We could not approve your UPI payment of {$a->amount} (UTR {$a->utr}) for "{$a->item}".

Reason: {$a->note}

If money was taken from your account, please reply with a screenshot of the payment.';
$string['messageprovider:upisubmitted'] = 'UPI payment waiting for approval';
$string['messageprovider:upiresult'] = 'UPI payment approved or rejected';
$string['privacy:metadata:paygw_razorpay_upi'] = 'UPI payments made straight to the site\'s UPI ID';
$string['privacy:metadata:paygw_razorpay_upi:userid'] = 'The learner who paid';
$string['privacy:metadata:paygw_razorpay_upi:amount'] = 'The amount paid';
$string['privacy:metadata:paygw_razorpay_upi:utr'] = 'The UTR / transaction number the learner entered';
$string['privacy:metadata:paygw_razorpay_upi:timesubmitted'] = 'When the learner submitted the payment';

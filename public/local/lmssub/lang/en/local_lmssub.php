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
 * Strings.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['access'] = 'Access';
$string['accessuntil'] = 'Access until';
$string['action'] = 'Action';
$string['action_cancel'] = 'Cancel';
$string['action_cancel_desc'] = 'The learner keeps access until the end date, then the subscription expires. Auto-renew is switched off.';
$string['action_extend'] = 'Extend';
$string['action_revoke'] = 'Revoke';
$string['action_revoke_desc'] = 'Access to every course this subscription opens is locked immediately. Use this for refunds and misconduct. This cannot be undone; a new subscription would have to be granted.';
$string['activate'] = 'Activate';
$string['activesubscribers'] = 'Active subscribers';
$string['addoverride'] = 'Save override';
$string['addoverrides'] = 'Save overrides';
$string['addplan'] = 'Add plan';
$string['addprice'] = 'Add {no} more duration option(s)';
$string['allplans'] = 'All plans';
$string['allstatuses'] = 'All statuses';
$string['amount'] = 'Price (₹)';
$string['andmore'] = '... and {$a} more';
$string['autorenewallowed'] = 'Allow auto-renew';
$string['autorenewallowed_help'] = 'Whether learners may choose automatic renewal through Razorpay Subscriptions. Auto-renew is added with the payment stage; until then this only records the policy.';
$string['backtoplans'] = 'Back to plans';
$string['browseplans'] = 'Browse subscription plans';
$string['bulkcourses'] = 'Courses';
$string['bulkcourses_help'] = 'Choose one or more courses. Every chosen user is processed for every chosen course.';
$string['bulkusers'] = 'Users';
$string['bulkusers_help'] = 'Search by name, email or cohort and add as many as you need. Choosing a cohort adds all of its members. A user in several chosen cohorts, or also chosen by name, is processed once. Every user is processed for every chosen course.';
$string['by'] = 'By';
$string['changeduration'] = 'Change';
$string['checkaccess'] = 'Access checker';
$string['checkout'] = 'Checkout';
$string['checkresults'] = 'Results';
$string['checksummary'] = '{$a->pairs} checked: {$a->yes} with access, {$a->no} without.';
$string['chooseduration'] = 'Choose a duration';
$string['cohortoption'] = '{$a->name} ({$a->members} members)';
$string['cohortsempty'] = 'The chosen cohorts have no members. Add users or choose other cohorts.';
$string['coursescovered'] = 'Courses covered';
$string['coursescoveredcount'] = 'This plan opens {$a} course(s).';
$string['coursesincluded'] = 'Courses included ({$a})';
$string['currentoverrides'] = 'Current overrides ({$a})';
$string['customdays'] = 'Custom number of days';
$string['customfieldcategory'] = 'Subscriptions';
$string['deactivate'] = 'Deactivate';
$string['deleteselected'] = 'Delete selected';
$string['deleteselected_confirm'] = 'Delete the selected overrides? Access is recalculated for those users straight away.';
$string['devicelimit'] = 'Device limit';
$string['devicelimit_help'] = 'How many devices a subscriber may use. Enforced by the device security stage.';
$string['duration'] = 'Duration';
$string['duration_custom'] = 'Custom';
$string['duration_days'] = '{$a} days';
$string['duration_halfyearly'] = 'Half-yearly (6 months)';
$string['duration_monthly'] = 'Monthly (1 month)';
$string['duration_quarterly'] = 'Quarterly (3 months)';
$string['duration_yearly'] = 'Yearly (12 months)';
$string['editplan'] = 'Edit plan';
$string['enablecoursesubs'] = 'Enable course subscriptions (Type 1)';
$string['enablecoursesubs_desc'] = 'Single course and bundle plans.';
$string['enablefullsubs'] = 'Enable full-LMS subscriptions (Type 2)';
$string['enablefullsubs_desc'] = 'One plan that opens every eligible course, including courses added later.';
$string['enddate'] = 'End date';
$string['endedon'] = 'Ended on';
$string['error_amount'] = 'Enter a price greater than zero.';
$string['error_bundlecourses'] = 'A bundle needs at least two courses.';
$string['error_devicelimit'] = 'The device limit must be at least 1.';
$string['error_duration'] = 'Choose a duration, or enter a number of days greater than zero.';
$string['error_gracedays'] = 'The grace period cannot be negative.';
$string['error_noprices'] = 'Add at least one duration with a price.';
$string['error_onecourse'] = 'Choose exactly one course for this plan type.';
$string['event_cancel'] = 'Cancelled';
$string['event_expire'] = 'Expired';
$string['event_extend'] = 'Extended / renewed';
$string['event_grace'] = 'Grace period started';
$string['event_grant'] = 'Granted';
$string['event_revoke'] = 'Revoked';
$string['excludedcoursesnote'] = '{$a} course(s) on the site are marked "Excluded from full subscription" and are never opened by full-LMS plans.';
$string['excludedfield'] = 'Excluded from full subscription';
$string['excludedfield_desc'] = 'Tick to keep this course out of every full-LMS subscription plan (for premium, corporate or free courses). Course subscription plans can still include it.';
$string['explain_allowed'] = 'An admin "always allow" override gives access (reason: {$a->reason}).';
$string['explain_blocked'] = 'An admin "always block" override removes access, and it wins over every subscription (reason: {$a->reason}).';
$string['explain_covered_active'] = 'Plan "{$a->plan}" covers this course and the subscription is {$a->status}: access until {$a->until}.';
$string['explain_covered_inactive'] = 'Plan "{$a->plan}" covers this course, but the subscription is {$a->status} (ended {$a->end}).';
$string['explain_enrolment'] = 'Subscription enrolment in this course: {$a->status}, ends {$a->end}.';
$string['explain_excluded'] = 'This course is marked "Excluded from full subscription", so full-LMS plans do not open it.';
$string['explain_nosubs'] = 'The user has no subscriptions.';
$string['explain_notcovered'] = 'Plan "{$a->plan}" ({$a->status}) does not cover this course.';
$string['explain_otherenrolment'] = 'The user also has another active enrolment in this course ({$a}), which keeps working independently of subscriptions.';
$string['gracedays'] = 'Grace period (days)';
$string['gracedays_help'] = 'Days of continued access after the end date (or a failed renewal payment) before the course locks.';
$string['graceuntil'] = 'Grace until {$a}';
$string['grant'] = 'Grant';
$string['granted'] = 'Subscription granted to {$a}.';
$string['grantsubscription'] = 'Grant subscription';
$string['grantsubscription_desc'] = 'Gives a user a subscription without payment. If they already have a subscription on this plan, the days are added on top of its current end date.';
$string['hasaccess'] = 'Has access ({$a})';
$string['history'] = 'History';
$string['invalidtransition'] = 'A subscription cannot go from "{$a->from}" to "{$a->to}".';
$string['level'] = 'Access level';
$string['level_full'] = 'Full';
$string['level_video'] = 'Videos only';
$string['lmssub:managedevices'] = 'Manage subscriber devices';
$string['lmssub:manageplans'] = 'Manage subscription plans';
$string['lmssub:managesubscribers'] = 'Manage subscribers';
$string['lmssub:refund'] = 'Issue subscription refunds';
$string['lmssub:viewreports'] = 'View subscription reports';
$string['mysubscriptions'] = 'My subscriptions';
$string['newcoursesincluded'] = 'New courses added to these categories later are included automatically.';
$string['noaccess'] = 'No access';
$string['nocoursescovered'] = 'This plan does not open any course yet.';
$string['nomysubscriptions'] = 'You have no subscriptions.';
$string['nopaymentaccount'] = 'Payments are not set up yet. Please contact the site administrator.';
$string['noplans'] = 'There are no plans yet.';
$string['noplansavailable'] = 'No subscription plans are available right now.';
$string['nosubscriptions'] = 'No subscriptions found.';
$string['nothingselected'] = 'Nothing was selected.';
$string['notypesenabled'] = 'Both subscription types are switched off in Subscriptions > Settings, so no new plans can be added.';
$string['nousersorcohorts'] = 'Choose at least one user or cohort.';
$string['opens'] = 'Opens {$a} course(s):';
$string['opens_all'] = 'Opens all {$a} course(s), including new ones added later:';
$string['opens_video'] = 'Opens the videos of:';
$string['ordersummary'] = 'Order summary';
$string['override_allow'] = 'Always allow';
$string['override_block'] = 'Always block';
$string['overrideaction'] = 'Override';
$string['overrideaction_help'] = 'Always block removes subscription access to the course even when a subscription covers it (for example for misconduct). Always allow opens the course without a subscription. Other enrolment methods are not affected.';
$string['overrideremoved'] = 'Override removed.';
$string['overrides'] = 'Access overrides';
$string['overrides_desc'] = 'Admin "always allow" or "always block" for a user in one course. A block always wins over a subscription.';
$string['overridesaved'] = 'Override saved.';
$string['overridesremoved'] = '{$a} override(s) removed.';
$string['overridessaved'] = 'Overrides saved for {$a->users} user(s) in {$a->courses} course(s): {$a->added} added, {$a->updated} updated.';
$string['paidreason'] = 'Paid (payment #{$a})';
$string['paymentaccount'] = 'Payment account';
$string['paymentaccount_auto'] = 'Automatic (the only enabled account)';
$string['paymentaccount_desc'] = 'The payment account (Site administration > General > Payments > Payment accounts) that plan payments go to. It must have the Razorpay gateway enabled.';
$string['paymentdescription'] = '{$a->plan} - {$a->duration}';
$string['paymentnote'] = 'You will be asked to pay securely with Razorpay (UPI, card or netbanking). Access starts as soon as the payment is confirmed.';
$string['paymentsuccess'] = 'Payment received. Your subscription is active and your courses are open.';
$string['paynow'] = 'Proceed to payment ({$a})';
$string['plan'] = 'Plan';
$string['planactive'] = 'Available for purchase';
$string['planactive_help'] = 'Inactive plans cannot be bought. Existing subscribers keep their access until their end date.';
$string['planname'] = 'Plan name';
$string['plannotavailable'] = 'This plan is not available for purchase.';
$string['plans'] = 'Plans';
$string['plans_intro'] = 'Choose a plan and a duration. You will pay on the next step.';
$string['plansaved'] = 'Plan saved. These are the courses it opens.';
$string['plansforcourse'] = 'Plans that include {$a}';
$string['plantype'] = 'Plan type';
$string['plantype_help'] = '* Single course: one whole course.
* Bundle: the selected courses.
* Full access: every eligible course in the chosen categories, including courses added later.

The type cannot be changed after the plan is created.';
$string['pluginname'] = 'LMS subscriptions';
$string['policies'] = 'Policies';
$string['previewcourses'] = 'Preview courses covered';
$string['previewcoursesfor'] = 'Courses covered by {$a}';
$string['priceoption'] = 'Duration option';
$string['prices'] = 'Prices';
$string['prices_help'] = 'One price per duration, for example 1 month ₹499, 3 months ₹1299, 6 months ₹2399, 12 months ₹3999. Offering 3 or 4 options works best. Leave unused rows empty.';
$string['privacy:metadata:device'] = 'Devices registered by subscribers.';
$string['privacy:metadata:device:ip'] = 'The IP address the device was registered from.';
$string['privacy:metadata:device:useragent'] = 'The browser of the device.';
$string['privacy:metadata:device:userid'] = 'The user the device belongs to.';
$string['privacy:metadata:event'] = 'The history of every change to a subscription.';
$string['privacy:metadata:event:actorid'] = 'Who made the change.';
$string['privacy:metadata:event:reason'] = 'Why the change was made.';
$string['privacy:metadata:event:userid'] = 'The subscriber.';
$string['privacy:metadata:order'] = 'Payment attempts for subscription plans.';
$string['privacy:metadata:order:amount'] = 'The amount paid.';
$string['privacy:metadata:order:timecreated'] = 'When the order was created.';
$string['privacy:metadata:order:userid'] = 'The user who placed the order.';
$string['privacy:metadata:override'] = 'Admin access overrides for a user in a course.';
$string['privacy:metadata:override:reason'] = 'Why the override was added.';
$string['privacy:metadata:override:userid'] = 'The user the override applies to.';
$string['privacy:metadata:sub'] = 'Subscriptions held by users.';
$string['privacy:metadata:sub:planid'] = 'The plan subscribed to.';
$string['privacy:metadata:sub:status'] = 'The status of the subscription.';
$string['privacy:metadata:sub:timeend'] = 'When the subscription ends.';
$string['privacy:metadata:sub:timestart'] = 'When the subscription started.';
$string['privacy:metadata:sub:userid'] = 'The subscriber.';
$string['progresswaiting'] = 'Your grades and progress are kept. Renew to continue exactly where you left off.';
$string['reason'] = 'Reason';
$string['renew'] = 'Renew';
$string['renewnote'] = 'You already have this plan until {$a}. The new period is added on top, so no days are lost.';
$string['scope'] = 'What the plan covers';
$string['scope_allcategories'] = 'All categories';
$string['scope_categories'] = 'Categories included';
$string['scope_categories_help'] = 'Every visible course in these categories is opened, including courses added later. Leave empty to include every category on the site.';
$string['scope_courses'] = 'Courses';
$string['scope_courses_help'] = 'Single course plans take exactly one course; bundles take two or more.';
$string['scope_excludecategories'] = 'Categories excluded';
$string['scope_excludecourses'] = 'Courses excluded';
$string['scope_excludecourses_help'] = 'Courses this plan never opens. To keep a course out of every full-LMS plan, tick "Excluded from full subscription" in the course settings instead.';
$string['scope_includesub'] = 'Include subcategories';
$string['searchuser'] = 'Name or email';
$string['searchuserscohorts'] = 'Search users or cohorts';
$string['selectplan'] = 'Select plan';
$string['selectrenew'] = 'Select to renew';
$string['settings'] = 'Settings';
$string['showallplans'] = 'Show all plans';
$string['source'] = 'Source';
$string['source_admin'] = 'Granted by admin';
$string['source_payment'] = 'Payment';
$string['startdate'] = 'Start date';
$string['status_active'] = 'Active';
$string['status_cancelled'] = 'Cancelled';
$string['status_expired'] = 'Expired';
$string['status_grace'] = 'Grace period';
$string['status_pending'] = 'Pending payment';
$string['status_revoked'] = 'Revoked';
$string['subscribers'] = 'Subscribers';
$string['subscriptionof'] = '{$a->user}: {$a->plan}';
$string['subscriptionplans'] = 'Subscription plans';
$string['subscriptions'] = 'Subscriptions';
$string['system'] = 'System';
$string['task_nightlyresync'] = 'Re-check subscription access (safety net)';
$string['task_processexpiry'] = 'Process subscription expiry and grace periods';
$string['toomanypairs'] = 'That is {$a->pairs} user and course pairs; at most {$a->max} can be processed at once. Choose fewer users or courses.';
$string['total'] = 'Total';
$string['type_allaccess'] = 'Full access (all courses)';
$string['type_bundle'] = 'Bundle of courses';
$string['type_course'] = 'Single course';
$string['type_video'] = 'Video only (one course)';
$string['userdeleted'] = 'User deleted';
$string['userscohorts'] = 'Users or cohorts';
$string['verdict_no'] = '{$a->user} has no subscription access to {$a->course}.';
$string['verdict_yes'] = '{$a->user} has subscription access to {$a->course} ({$a->level}).';
$string['why'] = 'Why';
$string['yourplanuntil'] = 'Your plan until {$a}';

// Learner plans page.
$string['activeuntil'] = 'active until {$a}';
$string['bestvalue'] = 'Best value';
$string['everythingincluded'] = 'Everything included';
$string['faq'] = 'Frequently asked questions';
$string['faq1_q'] = 'What happens when my subscription ends?';
$string['faq1_a'] = 'The courses lock, but your grades, progress and certificates are kept. Renew at any time and you continue exactly where you left off.';
$string['faq2_q'] = 'Can I renew before my plan ends?';
$string['faq2_a'] = 'Yes. The new period is added on top of your current end date, so you never lose any days.';
$string['faq3_q'] = 'How can I pay?';
$string['faq3_a'] = 'Pay securely through Razorpay by UPI, debit or credit card, or netbanking.';
$string['faq4_q'] = 'I already bought a single course. Can I still get full access?';
$string['faq4_a'] = 'Yes. Both subscriptions stay active, and full access opens every included course.';
$string['filter_allaccess'] = 'Full access';
$string['filter_bundle'] = 'Bundles';
$string['filter_course'] = 'Single courses';
$string['filter_video'] = 'Video only';
$string['filterplans'] = 'Filter plans by type';
$string['hero_eyebrow'] = 'Subscriptions';
$string['hero_title'] = 'Learn at your own pace with a plan that fits you';
$string['howitworks'] = 'How it works';
$string['managesubscriptions'] = 'Manage subscriptions';
$string['opens_all_count'] = 'Opens all {$a} courses, plus new ones';
$string['opens_count'] = 'Includes {$a} course(s)';
$string['permonth'] = 'Works out to {$a} per month';
$string['renewplan'] = 'Renew plan';
$string['save'] = 'Save {$a}%';
$string['showmorecourses'] = '+ {$a} more course(s)';
$string['step1_title'] = 'Choose a plan';
$string['step1_text'] = 'Pick a single course, a bundle or full access, and how long you want it.';
$string['step2_title'] = 'Pay securely';
$string['step2_text'] = 'Complete the payment through Razorpay in a few seconds.';
$string['step3_title'] = 'Start learning';
$string['step3_text'] = 'Your courses open straight away and appear in My courses.';
$string['subscribenow'] = 'Subscribe now';
$string['trust_instant'] = 'Instant access after payment';
$string['trust_progress'] = 'Your progress is kept, even after a plan ends';
$string['trust_secure'] = 'Secure payment by UPI, card or netbanking';
$string['youractiveplans'] = 'Your active plans';
$string['duration_months'] = '{$a} months';
$string['duration_onemonth'] = '1 month';
$string['deactivateplan'] = 'Deactivate plan';
$string['deactivateplan_confirm'] = 'Deactivate the plan "{$a}"? Learners will no longer be able to buy or renew it. Existing subscribers keep their access until their end date. You can activate it again at any time.';
$string['expiredon'] = 'Expired on {$a}';
$string['feature_durations'] = '{$a} durations to choose from';
$string['feature_grace'] = '{$a}-day grace period after your plan ends';
$string['managesubscription'] = 'Manage subscription';
$string['openingcheckout'] = 'Opening checkout...';
$string['renewplan_aria'] = 'Renew {$a}';
$string['subscribenow_aria'] = 'Subscribe to {$a}';
$string['allcourses'] = 'All courses';
$string['attemptamount'] = 'Amount';
$string['attemptdate'] = 'Attempted on';
$string['attempts'] = 'Attempts';
$string['attempts_count'] = '{$a} attempts';
$string['attempts_one'] = '1 attempt';
$string['attempts_range'] = 'From {$a->first} to {$a->last}';
$string['attempts_show_all'] = 'All attempts, including paid';
$string['attempts_show_open'] = 'Not completed';
$string['attemptstatus_approved'] = 'Paid (UPI approved)';
$string['attemptstatus_created'] = 'Started, not paid';
$string['attemptstatus_new'] = 'Started, not paid';
$string['attemptstatus_paid'] = 'Paid';
$string['attemptstatus_pending'] = 'UPI payment to review';
$string['attemptstatus_rejected'] = 'UPI payment rejected';
$string['extendsubscription'] = 'Extend subscription';
$string['gateway'] = 'Payment gateway';
$string['gateway_lmspay'] = 'LMS Pay';
$string['gateway_razorpay'] = 'Razorpay';
$string['gateway_razorpayupi'] = 'Razorpay (UPI)';
$string['markassubscriber'] = 'Mark as subscriber';
$string['markassubscriber_desc'] = 'The student gets the plan for the duration below, as if granted by an admin. No payment is recorded; the reason is kept in the subscription history.';
$string['markassubscriber_extend'] = 'The student already has this plan until {$a}. The duration below is added to it.';
$string['markassubscriber_paid'] = 'This attempt was paid, so the subscription was already given when the payment completed.';
$string['markassubscriber_reason'] = 'Payment attempt {$a->reference} ({$a->gateway}) confirmed by an admin';
$string['markassubscriber_review'] = 'This UPI payment is waiting for review. Approving it on the <a href="{$a}">UPI payments page</a> records the payment and gives the subscription; use the button below only if you are not approving it there.';
$string['markedassubscriber'] = '{$a->user} is now a subscriber of {$a->plan}.';
$string['noattempts'] = 'No payment attempts for these plans yet.';
$string['noattempts_open'] = 'No unfinished payment attempts. Choose "All attempts" to see paid ones.';
$string['notsubscribed'] = 'Not subscribed';
$string['paymentattempts'] = 'Payment attempts';
$string['paymentattempts_course_desc'] = 'Students who clicked "Proceed to payment" for a plan that opens {$a}, and did not complete the payment. You can make them subscribers of the plan they chose.';
$string['paymentattempts_desc'] = 'Students who clicked "Proceed to payment" for a subscription plan and did not complete the payment, from the checkout page and every payment gateway. You can make them subscribers of the plan they chose.';
$string['reviewupi'] = 'Review UPI payment';
$string['student'] = 'Student';
$string['subscribeduntil'] = 'Subscribed until {$a}';
$string['subscription'] = 'Subscription';
$string['attemptedpayments'] = 'Attempted payments';
$string['attemptedpayments_all'] = 'View all attempted payments ({$a})';
$string['attemptedpayments_desc'] = 'Learners who clicked "Proceed to payment" and did not complete the payment. Mark them as subscribers if they paid another way.';
$string['gateway_checkout'] = 'Checkout ("Proceed to payment" clicked)';
$string['messageprovider:subscriptionended'] = 'Your subscription is cancelled or revoked';
$string['notify_cancelled_subject'] = 'Your subscription "{$a->plan}" has been cancelled';
$string['notify_cancelled_body'] = 'Your subscription "{$a->plan}" on {$a->site} has been cancelled and will not renew.

You keep access to your courses until {$a->date}. You can renew at any time from My subscriptions.';
$string['notify_cancelled_ended_subject'] = 'Your subscription "{$a->plan}" has been cancelled';
$string['notify_cancelled_ended_body'] = 'Your subscription "{$a->plan}" on {$a->site} has been cancelled and your access to its courses has ended.

You can subscribe again at any time from My subscriptions.';
$string['notify_revoked_subject'] = 'Your subscription "{$a->plan}" has ended';
$string['notify_revoked_body'] = 'Your subscription "{$a->plan}" on {$a->site} has been ended by the site administrator, and your access to its courses has been removed.

If you think this is a mistake, please contact the site administrator.';

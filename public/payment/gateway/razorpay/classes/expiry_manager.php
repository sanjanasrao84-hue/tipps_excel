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
 * Expiry warnings, expiry notices and renewal handling for paid (enrolment on payment) enrolments.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_razorpay;

use core_user;
use moodle_url;

/**
 * Expiry warnings, expiry notices and renewal handling for paid (enrolment on payment) enrolments.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class expiry_manager {

    /** @var int Expiry notices are only sent for enrolments that ended in this many recent days. */
    const EXPIRED_NOTICE_DAYS = 7;

    /** @var int The "expired" banner is shown for this many days after the enrolment ended. */
    const EXPIRED_BANNER_DAYS = 30;

    /**
     * Returns the configured "days before expiry" thresholds, largest first.
     *
     * @return int[]
     */
    public static function get_warning_days(): array {
        $days = array_map('intval', explode(',', (string) get_config('paygw_razorpay', 'expirywarndays')));
        $days = array_unique(array_filter($days, fn($d) => $d > 0));
        rsort($days);
        return $days;
    }

    /**
     * SQL selecting time-limited enrolments of fee instances whose payment account has razorpay enabled.
     *
     * @return array [sql, params]
     */
    protected static function get_enrolments_sql(): array {
        $sql = "SELECT ue.id, ue.userid, ue.enrolid, ue.status, ue.timestart, ue.timeend,
                       e.courseid, c.fullname AS coursename
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'fee' AND e.status = :instanceenabled
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {payment_gateways} pg ON pg.accountid = e.customint1 AND pg.gateway = 'razorpay' AND pg.enabled = 1
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE ue.timeend > 0";
        return [$sql, ['instanceenabled' => ENROL_INSTANCE_ENABLED]];
    }

    /**
     * Sends the "expires in N days" warnings and the "has expired" notices that are due.
     *
     * @param \progress_trace|null $trace
     */
    public static function send_notifications(?\progress_trace $trace = null): void {
        global $DB;

        $trace = $trace ?? new \null_progress_trace();
        if (!get_config('paygw_razorpay', 'expirynotify')) {
            $trace->output('Expiry notifications are disabled.');
            return;
        }

        $now = time();
        [$basesql, $params] = self::get_enrolments_sql();
        $warningdays = self::get_warning_days();

        if ($warningdays) {
            $sql = $basesql . " AND ue.status = :active AND ue.timeend > :now AND ue.timeend <= :until";
            $rs = $DB->get_recordset_sql($sql, $params + [
                'active' => ENROL_USER_ACTIVE,
                'now' => $now,
                'until' => $now + reset($warningdays) * DAYSECS,
            ]);
            foreach ($rs as $ue) {
                $daysleft = (int) ceil(($ue->timeend - $now) / DAYSECS);
                // Use the smallest threshold reached, so "7,3,1" sends at most three warnings per period.
                $stage = min(array_filter($warningdays, fn($d) => $d >= $daysleft));
                if (self::notify($ue, (string) $stage, $daysleft)) {
                    $trace->output("Sent {$stage}-day expiry warning for user enrolment {$ue->id}.");
                }
            }
            $rs->close();
        }

        // Depending on the "Enrolment expiry action" setting, an expired enrolment is either kept or suspended.
        $sql = $basesql . " AND ue.timeend <= :now AND ue.timeend > :since";
        $rs = $DB->get_recordset_sql($sql, $params + [
            'now' => $now,
            'since' => $now - self::EXPIRED_NOTICE_DAYS * DAYSECS,
        ]);
        foreach ($rs as $ue) {
            if (self::notify($ue, 'expired')) {
                $trace->output("Sent expiry notice for user enrolment {$ue->id}.");
            }
        }
        $rs->close();
    }

    /**
     * Sends the expiry notice when the enrolment plugin deletes an expired enrolment ("Unenrol user from course").
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        global $DB;

        $ue = (object) $event->other['userenrolment'];
        if ($event->other['enrol'] !== 'fee' || empty($ue->timeend) || $ue->timeend > time()
                || $ue->timeend < time() - self::EXPIRED_NOTICE_DAYS * DAYSECS
                || !get_config('paygw_razorpay', 'expirynotify')) {
            return;
        }
        $instance = $DB->get_record('enrol', ['id' => $ue->enrolid]);
        if (!$instance || !$DB->record_exists('payment_gateways',
                ['accountid' => $instance->customint1, 'gateway' => 'razorpay', 'enabled' => 1])) {
            return;
        }
        $ue->courseid = $instance->courseid;
        $ue->coursename = $DB->get_field('course', 'fullname', ['id' => $instance->courseid]);
        self::notify($ue, 'expired');
    }

    /**
     * Sends one warning or expiry message, unless it was already sent for this enrolment period.
     *
     * @param \stdClass $ue User enrolment with courseid and coursename
     * @param string $stage Threshold in days, or 'expired'
     * @param int $daysleft
     * @return bool Whether a message was sent
     */
    protected static function notify(\stdClass $ue, string $stage, int $daysleft = 0): bool {
        global $DB;

        $key = ['userenrolmentid' => $ue->id, 'timeend' => $ue->timeend, 'stage' => $stage];
        if ($DB->record_exists('paygw_razorpay_expiry', $key)) {
            return false;
        }
        $user = core_user::get_user($ue->userid);
        if (!$user || $user->deleted || $user->suspended) {
            return false;
        }

        $a = self::get_string_params($ue, $daysleft);
        $expired = $stage === 'expired';
        $prefix = $expired ? 'expiredmessage' : 'expirywarning';

        $message = new \core\message\message();
        $message->component = 'paygw_razorpay';
        $message->name = $expired ? 'expired' : 'expirywarning';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->courseid = $ue->courseid;
        $message->notification = 1;
        $message->subject = get_string($prefix . 'subject', 'paygw_razorpay', $a);
        $message->fullmessage = get_string($prefix . 'body', 'paygw_razorpay', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html($message->fullmessage, false, false, true);
        $message->smallmessage = $message->subject;
        $message->contexturl = $a->renewurl;
        $message->contexturlname = get_string('renewnow', 'paygw_razorpay');

        if (!message_send($message)) {
            return false;
        }
        $DB->insert_record('paygw_razorpay_expiry', (object) ($key + ['timesent' => time()]));
        return true;
    }

    /**
     * Placeholders used in the warning, notice and banner strings.
     *
     * @param \stdClass $ue User enrolment with courseid and coursename
     * @param int $daysleft
     * @return \stdClass
     */
    protected static function get_string_params(\stdClass $ue, int $daysleft): \stdClass {
        return (object) [
            'course' => format_string($ue->coursename, true, ['context' => \context_course::instance($ue->courseid)]),
            'timeleft' => $daysleft == 1 ? get_string('onedayleft', 'paygw_razorpay') :
                get_string('daysleft', 'paygw_razorpay', $daysleft),
            'date' => userdate($ue->timeend, get_string('strftimedatetime', 'langconfig')),
            'renewurl' => self::get_renew_url($ue->enrolid)->out(false),
        ];
    }

    /**
     * URL of the renewal page for a fee enrolment instance.
     *
     * @param int $enrolid
     * @return moodle_url
     */
    public static function get_renew_url(int $enrolid): moodle_url {
        return new moodle_url('/payment/gateway/razorpay/renew.php', ['id' => $enrolid]);
    }

    /**
     * Adds a page notification for each of the user's paid enrolments that is about to expire or recently expired.
     *
     * @param int $userid
     */
    public static function add_banners(int $userid): void {
        global $DB;

        $now = time();
        $warningdays = self::get_warning_days();
        $until = $now + ($warningdays ? reset($warningdays) * DAYSECS : 0);

        [$sql, $params] = self::get_enrolments_sql();
        $sql .= " AND ue.userid = :userid AND ue.timeend <= :until AND ue.timeend > :since";
        $enrolments = $DB->get_records_sql($sql, $params + [
            'userid' => $userid,
            'until' => $until,
            'since' => $now - self::EXPIRED_BANNER_DAYS * DAYSECS,
        ]);

        foreach ($enrolments as $ue) {
            $expired = $ue->timeend <= $now;
            $a = self::get_string_params($ue, (int) ceil(($ue->timeend - $now) / DAYSECS));
            $a->renewurl = s($a->renewurl);
            \core\notification::add(
                get_string($expired ? 'expiredbanner' : 'expirybannertext', 'paygw_razorpay', $a),
                $expired ? \core\notification::ERROR : \core\notification::WARNING
            );
        }
    }

    /**
     * Called after a successful payment for a fee enrolment, once the enrolment plugin has delivered the order.
     *
     * The enrolment plugin restarts the period from today and leaves an expired (suspended) user suspended, so a
     * renewing user would pay and stay locked out. Re-activate the user and add the paid period on top of
     * whatever time was still left.
     *
     * @param int $instanceid Fee enrolment instance id
     * @param int $userid
     * @param \stdClass|false $previous The user enrolment as it was before the payment
     */
    public static function apply_renewal(int $instanceid, int $userid, $previous): void {
        global $DB;

        if (!$previous) {
            // First purchase: the enrolment plugin already did everything.
            return;
        }
        $instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'fee'], '*', MUST_EXIST);

        $timeend = 0;
        if ($instance->enrolperiod) {
            $timeend = max(time(), (int) $previous->timeend) + $instance->enrolperiod;
        }
        enrol_get_plugin('fee')->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE, $previous->timestart, $timeend);
    }
}

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

namespace local_lmssub;

/**
 * Subscriptions and their statuses.
 *
 *   pending --pay--> active --period ends--> expired
 *   active --payment fails--> grace --payment succeeds--> active
 *   grace --still unpaid--> expired
 *   active --learner cancels--> cancelled (access until end date) --> expired
 *   active --refund or admin--> revoked (locked immediately)
 *
 * Every change goes through transition(), is written to local_lmssub_event and re-syncs access.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription_manager {

    /** @var string Created, waiting for payment. */
    const STATUS_PENDING = 'pending';
    /** @var string Paid and running. */
    const STATUS_ACTIVE = 'active';
    /** @var string Period ended or payment failed; access continues until graceend. */
    const STATUS_GRACE = 'grace';
    /** @var string Learner cancelled; access continues until the end date. */
    const STATUS_CANCELLED = 'cancelled';
    /** @var string Ended. */
    const STATUS_EXPIRED = 'expired';
    /** @var string Refunded or revoked by an admin; locked immediately. */
    const STATUS_REVOKED = 'revoked';

    /** @var string[] Statuses that can still give access. */
    const LIVE_STATUSES = [self::STATUS_ACTIVE, self::STATUS_GRACE, self::STATUS_CANCELLED];

    /** @var array Allowed status changes. */
    const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_ACTIVE, self::STATUS_EXPIRED, self::STATUS_REVOKED],
        self::STATUS_ACTIVE => [self::STATUS_ACTIVE, self::STATUS_GRACE, self::STATUS_EXPIRED, self::STATUS_CANCELLED,
            self::STATUS_REVOKED],
        self::STATUS_GRACE => [self::STATUS_ACTIVE, self::STATUS_EXPIRED, self::STATUS_REVOKED],
        self::STATUS_CANCELLED => [self::STATUS_ACTIVE, self::STATUS_EXPIRED, self::STATUS_REVOKED],
        self::STATUS_EXPIRED => [self::STATUS_ACTIVE],
        self::STATUS_REVOKED => [],
    ];

    /**
     * All statuses with their names.
     *
     * @return array
     */
    public static function get_statuses(): array {
        $result = [];
        foreach (array_keys(self::TRANSITIONS) as $status) {
            $result[$status] = self::get_status_name($status);
        }
        return $result;
    }

    /**
     * Name of a status.
     *
     * @param string $status
     * @return string
     */
    public static function get_status_name(string $status): string {
        return get_string('status_' . $status, 'local_lmssub');
    }

    /**
     * Returns a subscription.
     *
     * @param int $subid
     * @return \stdClass
     */
    public static function get(int $subid): \stdClass {
        global $DB;
        return $DB->get_record('local_lmssub_sub', ['id' => $subid], '*', MUST_EXIST);
    }

    /**
     * The user's current subscription to a plan (live or expired, never revoked), if any.
     *
     * @param int $userid
     * @param int $planid
     * @return \stdClass|null
     */
    public static function find_current(int $userid, int $planid): ?\stdClass {
        global $DB;
        $subs = $DB->get_records_select('local_lmssub_sub',
            'userid = :userid AND planid = :planid AND status <> :revoked',
            ['userid' => $userid, 'planid' => $planid, 'revoked' => self::STATUS_REVOKED], 'timeend DESC', '*', 0, 1);
        return $subs ? reset($subs) : null;
    }

    /**
     * Gives a user a subscription, or extends the one they already have on the same plan.
     *
     * @param int $userid
     * @param int $planid
     * @param int $days
     * @param string $reason
     * @param int|null $priceid duration option bought, if any
     * @param string $source payment or admin
     * @param int|null $actorid null for the current user
     * @return \stdClass the subscription
     */
    public static function grant(int $userid, int $planid, int $days, string $reason, ?int $priceid = null,
            string $source = 'admin', ?int $actorid = null): \stdClass {
        global $DB;

        if ($days <= 0) {
            throw new \invalid_parameter_exception('Duration must be at least one day');
        }
        $plan = plan_manager::get_plan($planid);
        if ($current = self::find_current($userid, $planid)) {
            return self::extend($current, $days, $reason, $priceid, $actorid);
        }

        $now = time();
        $sub = (object) [
            'userid' => $userid,
            'planid' => $planid,
            'priceid' => $priceid,
            'level' => plan_manager::plan_level($plan),
            'status' => self::STATUS_ACTIVE,
            'timestart' => $now,
            'timeend' => $now + $days * DAYSECS,
            'graceend' => 0,
            'autorenew' => 0,
            'source' => $source,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $sub->id = $DB->insert_record('local_lmssub_sub', $sub);
        self::log($sub, 'grant', null, self::STATUS_ACTIVE, $reason, $actorid);
        access_engine::sync_user_soon($userid);
        return $sub;
    }

    /**
     * Adds days on top of the current end date, so no days are lost; reopens an ended subscription.
     *
     * @param \stdClass $sub
     * @param int $days
     * @param string $reason
     * @param int|null $priceid
     * @param int|null $actorid
     * @return \stdClass
     */
    public static function extend(\stdClass $sub, int $days, string $reason, ?int $priceid = null,
            ?int $actorid = null): \stdClass {
        if ($days <= 0) {
            throw new \invalid_parameter_exception('Duration must be at least one day');
        }
        $now = time();
        $base = in_array($sub->status, [self::STATUS_ACTIVE, self::STATUS_CANCELLED], true) ? max($now, (int) $sub->timeend) : $now;
        $changes = [
            'timeend' => $base + $days * DAYSECS,
            'graceend' => 0,
        ];
        if ($sub->status === self::STATUS_EXPIRED || $sub->status === self::STATUS_PENDING) {
            $changes['timestart'] = $now;
        }
        if ($priceid) {
            $changes['priceid'] = $priceid;
        }
        return self::transition($sub, self::STATUS_ACTIVE, 'extend', $reason, $changes, $actorid);
    }

    /**
     * The learner (or an admin) cancels: access continues until the end date, then expires.
     *
     * @param \stdClass $sub
     * @param string $reason
     * @param int|null $actorid
     * @return \stdClass
     */
    public static function cancel(\stdClass $sub, string $reason, ?int $actorid = null): \stdClass {
        $sub = self::transition($sub, self::STATUS_CANCELLED, 'cancel', $reason, ['autorenew' => 0], $actorid);
        self::notify_ended($sub, $actorid);
        return $sub;
    }

    /**
     * Refund or admin action: access is locked immediately.
     *
     * @param \stdClass $sub
     * @param string $reason
     * @param int|null $actorid
     * @return \stdClass
     */
    public static function revoke(\stdClass $sub, string $reason, ?int $actorid = null): \stdClass {
        $now = time();
        $sub = self::transition($sub, self::STATUS_REVOKED, 'revoke', $reason,
            ['timeend' => min((int) $sub->timeend, $now), 'graceend' => 0, 'autorenew' => 0], $actorid);
        self::notify_ended($sub, $actorid);
        return $sub;
    }

    /**
     * Notifies the learner (popup and email) that their subscription was cancelled or revoked.
     * Not sent when learners cancel their own subscription. A failed message never blocks the change.
     *
     * @param \stdClass $sub the updated subscription
     * @param int|null $actorid
     */
    protected static function notify_ended(\stdClass $sub, ?int $actorid = null): void {
        global $USER;

        if ($actorid === null) {
            $actorid = (isloggedin() && !CLI_SCRIPT) ? (int) $USER->id : 0;
        }
        $user = \core_user::get_user((int) $sub->userid);
        if ($actorid == $sub->userid || !$user || $user->deleted || $user->suspended) {
            return;
        }

        $plan = plan_manager::get_plan((int) $sub->planid);
        $a = (object) [
            'plan' => format_string($plan->name),
            'site' => format_string(get_site()->fullname),
        ];
        if ($sub->status === self::STATUS_CANCELLED) {
            $end = access_engine::effective_end($sub, $plan);
            $a->date = userdate($end, get_string('strftimedate', 'langconfig'), \core_date::get_user_timezone($user));
            $key = $end > time() ? 'notify_cancelled' : 'notify_cancelled_ended';
        } else {
            $key = 'notify_revoked';
        }
        $url = new \moodle_url('/local/lmssub/my.php');

        $message = new \core\message\message();
        $message->component = 'local_lmssub';
        $message->name = 'subscriptionended';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string($key . '_subject', 'local_lmssub', $a);
        $message->fullmessage = get_string($key . '_body', 'local_lmssub', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html($message->fullmessage, false, false, true);
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = get_string('mysubscriptions', 'local_lmssub');

        try {
            message_send($message);
        } catch (\Throwable $e) {
            debugging('local_lmssub: could not send the subscription notification: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The period ended without renewal: start the grace period, if the plan has one.
     *
     * @param \stdClass $sub
     * @param int $gracedays
     * @return \stdClass
     */
    public static function start_grace(\stdClass $sub, int $gracedays): \stdClass {
        return self::transition($sub, self::STATUS_GRACE, 'grace', '',
            ['graceend' => (int) $sub->timeend + $gracedays * DAYSECS], 0);
    }

    /**
     * Ends a subscription.
     *
     * @param \stdClass $sub
     * @param string $reason
     * @param int|null $actorid
     * @return \stdClass
     */
    public static function expire(\stdClass $sub, string $reason = '', ?int $actorid = 0): \stdClass {
        return self::transition($sub, self::STATUS_EXPIRED, 'expire', $reason, [], $actorid);
    }

    /**
     * Whether a status change is allowed.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function can_transition(string $from, string $to): bool {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Changes a subscription's status, logs it and re-syncs the user's access.
     *
     * @param \stdClass $sub
     * @param string $to
     * @param string $action
     * @param string $reason
     * @param array $changes other fields to update
     * @param int|null $actorid
     * @return \stdClass the updated subscription
     */
    protected static function transition(\stdClass $sub, string $to, string $action, string $reason, array $changes = [],
            ?int $actorid = null): \stdClass {
        global $DB;

        $from = $sub->status;
        if (!self::can_transition($from, $to)) {
            throw new \moodle_exception('invalidtransition', 'local_lmssub', '', (object) [
                'from' => self::get_status_name($from), 'to' => self::get_status_name($to)]);
        }
        $update = (object) array_merge($changes, ['id' => $sub->id, 'status' => $to, 'timemodified' => time()]);
        $DB->update_record('local_lmssub_sub', $update);
        $sub = self::get((int) $sub->id);
        self::log($sub, $action, $from, $to, $reason, $actorid);
        access_engine::sync_user_soon((int) $sub->userid);
        return $sub;
    }

    /**
     * Writes a permanent history entry.
     *
     * @param \stdClass $sub
     * @param string $action
     * @param string|null $from
     * @param string|null $to
     * @param string $reason
     * @param int|null $actorid null for the current user, 0 for the system
     */
    public static function log(\stdClass $sub, string $action, ?string $from, ?string $to, string $reason = '',
            ?int $actorid = null): void {
        global $DB, $USER;

        if ($actorid === null) {
            $actorid = (isloggedin() && !CLI_SCRIPT) ? (int) $USER->id : 0;
        }
        $DB->insert_record('local_lmssub_event', (object) [
            'subid' => $sub->id,
            'userid' => $sub->userid,
            'actorid' => $actorid,
            'action' => $action,
            'fromstatus' => $from,
            'tostatus' => $to,
            'timeend' => (int) $sub->timeend,
            'reason' => $reason,
            'timecreated' => time(),
        ]);
    }

    /**
     * History of a subscription, newest first.
     *
     * @param int $subid
     * @return array
     */
    public static function get_events(int $subid): array {
        global $DB;
        return $DB->get_records('local_lmssub_event', ['subid' => $subid], 'timecreated DESC, id DESC');
    }
}

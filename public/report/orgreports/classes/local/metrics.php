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

namespace report_orgreports\local;

use report_orgreports\daterange;
use report_orgreports\org_context;

/**
 * Measures shared by the dashboard sections, each for the period (series and total) and the previous period (total).
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metrics {

    /** @var int a gap between two logged actions longer than this ends a learning session (seconds). */
    const IDLE = 1800;

    /** @var string login event. */
    const LOGIN_EVENT = '\\core\\event\\user_loggedin';

    /** @var org_context */
    public readonly org_context $org;

    /** @var daterange */
    public readonly daterange $range;

    /** @var array cache of computed measures. */
    private array $cache = [];

    /**
     * Constructor.
     *
     * @param org_context $org
     * @param daterange $range
     */
    public function __construct(org_context $org, daterange $range) {
        $this->org = $org;
        $this->range = $range;
    }

    /**
     * Zero for every bucket.
     *
     * @return array
     */
    public function zeros(): array {
        return array_fill(0, count($this->range->get_buckets()), 0);
    }

    /**
     * Count rows per bucket in the period, and in total for the period and the previous period.
     *
     * @param string $timecol
     * @param string $from
     * @param string $where
     * @param array $params
     * @return array ['series' => int[], 'total' => int, 'prev' => int]
     */
    public function count(string $timecol, string $from, string $where, array $params): array {
        global $DB;
        $p = $params + ['ms' => $this->range->start, 'me' => $this->range->end];
        $hour = $this->range->hour_sql($timecol, $p);
        $rows = $DB->get_records_sql_menu("SELECT $hour AS h, COUNT(1) AS c FROM $from
            WHERE $where AND $timecol >= :ms AND $timecol < :me GROUP BY $hour", $p);
        $series = $this->zeros();
        foreach ($rows as $h => $c) {
            $b = $this->range->bucket_of_hour((int)$h);
            if ($b !== null) {
                $series[$b] += (int)$c;
            }
        }
        $prev = $DB->count_records_sql("SELECT COUNT(1) FROM $from WHERE $where AND $timecol >= :ms AND $timecol < :me",
            $params + ['ms' => $this->range->prevstart, 'me' => $this->range->prevend]);
        return ['series' => $series, 'total' => array_sum($series), 'prev' => $prev];
    }

    /**
     * Distinct ids per bucket, and distinct in total for the period and the previous period.
     *
     * @param string $timecol
     * @param string $idcol
     * @param string $from
     * @param string $where
     * @param array $params
     * @return array ['series' => int[], 'total' => int, 'prev' => int, 'ids' => int[]]
     */
    public function distinct(string $timecol, string $idcol, string $from, string $where, array $params): array {
        global $DB;
        $p = $params + ['ms' => $this->range->start, 'me' => $this->range->end];
        $hour = $this->range->hour_sql($timecol, $p);
        $rs = $DB->get_recordset_sql("SELECT DISTINCT $hour AS h, $idcol AS id FROM $from
            WHERE $where AND $timecol >= :ms AND $timecol < :me", $p);
        $perbucket = [];
        $all = [];
        foreach ($rs as $row) {
            $b = $this->range->bucket_of_hour((int)$row->h);
            if ($b !== null) {
                $perbucket[$b][$row->id] = true;
                $all[$row->id] = true;
            }
        }
        $rs->close();
        $series = $this->zeros();
        foreach ($perbucket as $b => $ids) {
            $series[$b] = count($ids);
        }
        $prev = $DB->count_records_sql("SELECT COUNT(DISTINCT $idcol) FROM $from
            WHERE $where AND $timecol >= :ms AND $timecol < :me",
            $params + ['ms' => $this->range->prevstart, 'me' => $this->range->prevend]);
        return ['series' => $series, 'total' => count($all), 'prev' => $prev, 'ids' => array_keys($all)];
    }

    /**
     * Revenue from completed payments, per currency.
     *
     * @return array ['series' => [currency => float[]], 'total' => [currency => float], 'prev' => [currency => float]]
     */
    public function revenue(): array {
        global $DB;
        if (isset($this->cache['revenue'])) {
            return $this->cache['revenue'];
        }
        $amount = payment_source::amount_sql();
        [$from, $where, $params] = payment_source::payments($this->org, $this->range->start, $this->range->end);
        $hour = $this->range->hour_sql('p.timecreated', $params);
        $rs = $DB->get_recordset_sql("SELECT $hour AS h, p.currency, SUM($amount) AS amount FROM $from WHERE $where
            GROUP BY $hour, p.currency", $params);
        $series = [];
        $total = [];
        foreach ($rs as $row) {
            $b = $this->range->bucket_of_hour((int)$row->h);
            if ($b === null) {
                continue;
            }
            $series[$row->currency] ??= $this->zeros();
            $series[$row->currency][$b] += (float)$row->amount;
            $total[$row->currency] = ($total[$row->currency] ?? 0) + (float)$row->amount;
        }
        $rs->close();
        [$from, $where, $params] = payment_source::payments($this->org, $this->range->prevstart, $this->range->prevend);
        $prev = $DB->get_records_sql_menu("SELECT p.currency, SUM($amount) FROM $from WHERE $where GROUP BY p.currency",
            $params);
        $prev = array_map('floatval', $prev);
        ksort($series);
        return $this->cache['revenue'] = ['series' => $series, 'total' => $total, 'prev' => $prev];
    }

    /**
     * Completed payments.
     *
     * @return array see {@see self::count()}
     */
    public function payments(): array {
        if (!isset($this->cache['payments'])) {
            [$from, $where, $params] = payment_source::payments($this->org, 0, PHP_INT_MAX);
            $this->cache['payments'] = $this->count('p.timecreated', $from, $where, $params);
        }
        return $this->cache['payments'];
    }

    /**
     * Distinct paying users.
     *
     * @return array see {@see self::distinct()}
     */
    public function paid_users(): array {
        if (!isset($this->cache['paidusers'])) {
            [$from, $where, $params] = payment_source::payments($this->org, 0, PHP_INT_MAX);
            $this->cache['paidusers'] = $this->distinct('p.timecreated', 'p.userid', $from, $where, $params);
        }
        return $this->cache['paidusers'];
    }

    /**
     * New enrolments in the organisation's courses (user_enrolments created).
     *
     * @param int $courseid
     * @return array see {@see self::count()}
     */
    public function enrolments(int $courseid = 0): array {
        $key = "enrolments$courseid";
        if (!isset($this->cache[$key])) {
            [$csql, $params] = $this->org->course_sql('e.courseid', $courseid);
            $this->cache[$key] = $this->count('ue.timecreated', '{user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {user} u ON u.id = ue.userid', "$csql AND u.deleted = 0", $params);
        }
        return $this->cache[$key];
    }

    /**
     * New user accounts that belong to the organisation (by current membership).
     *
     * @return array see {@see self::count()}
     */
    public function signups(): array {
        if (!isset($this->cache['signups'])) {
            [$msql, $params] = $this->org->member_sql('u.id');
            $this->cache['signups'] = $this->count('u.timecreated', '{user} u', "u.deleted = 0 AND $msql", $params);
        }
        return $this->cache['signups'];
    }

    /**
     * Log condition for actions of real users (no guests, no anonymous events).
     *
     * @param string $alias
     * @return array [sql, params]
     */
    public function real_user_sql(string $alias): array {
        global $CFG;
        return ["$alias.userid > 0 AND $alias.userid <> " . (int)$CFG->siteguest . " AND $alias.anonymous = 0", []];
    }

    /**
     * Distinct users who logged in or acted in the organisation's courses.
     *
     * @return array see {@see self::distinct()}
     */
    public function active_users(): array {
        if (!isset($this->cache['active'])) {
            [$logsql, $params] = $this->org->log_sql('l');
            [$msql, $mparams] = $this->org->member_sql('l.userid');
            [$realsql, $rparams] = $this->real_user_sql('l');
            $where = "$realsql AND ($logsql OR (l.eventname = :loginevent AND $msql))";
            $this->cache['active'] = $this->distinct('l.timecreated', 'l.userid', '{logstore_standard_log} l', $where,
                $params + $mparams + $rparams + ['loginevent' => self::LOGIN_EVENT]);
        }
        return $this->cache['active'];
    }

    /**
     * Logins of the organisation's users.
     *
     * @return array ['count' => count(), 'users' => distinct()]
     */
    public function logins(): array {
        if (!isset($this->cache['logins'])) {
            [$msql, $params] = $this->org->member_sql('l.userid');
            [$realsql, $rparams] = $this->real_user_sql('l');
            $where = "l.eventname = :loginevent AND $realsql AND $msql";
            $params += $rparams + ['loginevent' => self::LOGIN_EVENT];
            $this->cache['logins'] = [
                'count' => $this->count('l.timecreated', '{logstore_standard_log} l', $where, $params),
                'users' => $this->distinct('l.timecreated', 'l.userid', '{logstore_standard_log} l', $where, $params),
            ];
        }
        return $this->cache['logins'];
    }

    /**
     * Condition that the log's user holds one of the roles (by archetype) in the log's course.
     *
     * @param string $alias
     * @param string[] $archetypes
     * @return array [sql, params]
     */
    public static function role_log_sql(string $alias, array $archetypes): array {
        global $DB;
        $roleids = [];
        foreach ($archetypes as $archetype) {
            $roleids = array_merge($roleids, array_keys(get_archetype_roles($archetype)));
        }
        [$insql, $params] = $DB->get_in_or_equal($roleids ?: [0], SQL_PARAMS_NAMED, 'lrole');
        static $n = 0;
        $p = 'rl' . (++$n);
        return ["EXISTS (SELECT 1 FROM {role_assignments} {$p}ra
                  JOIN {context} {$p}ctx ON {$p}ctx.id = {$p}ra.contextid AND {$p}ctx.contextlevel = " . CONTEXT_COURSE . "
                 WHERE {$p}ctx.instanceid = $alias.courseid AND {$p}ra.userid = $alias.userid AND {$p}ra.roleid $insql)",
            $params];
    }

    /**
     * Learner condition: the log's user holds a student role in the log's course.
     *
     * @param string $alias
     * @return array [sql, params]
     */
    public static function learner_log_sql(string $alias): array {
        return self::role_log_sql($alias, ['student']);
    }

    /**
     * Teacher condition: the log's user holds a teacher role in the log's course.
     *
     * @param string $alias
     * @return array [sql, params]
     */
    public static function teacher_log_sql(string $alias): array {
        return self::role_log_sql($alias, ['editingteacher', 'teacher']);
    }

    /**
     * Condition for actions of real users (no guests, no anonymous events) in the organisation's courses.
     *
     * @param string $alias
     * @param int $courseid
     * @return array [sql, params]
     */
    public function course_log_sql(string $alias, int $courseid = 0): array {
        [$logsql, $params] = $this->org->log_sql($alias, $courseid);
        [$realsql, $rparams] = $this->real_user_sql($alias);
        return ["$logsql AND $realsql", $params + $rparams];
    }

    /**
     * Estimated learning time of learners in the organisation's courses, from the standard log.
     *
     * Consecutive actions of a learner less than {@see self::IDLE} apart count as time spent; the time is given to the
     * bucket and course of the earlier action. It is an estimate: Moodle does not record time on page.
     *
     * @return array ['series' => int[] seconds, 'total' => int, 'prev' => int, 'courses' => [courseid => int]]
     */
    public function learning_time(): array {
        if (!isset($this->cache['learning'])) {
            $current = $this->sessions($this->range->start, $this->range->end, true);
            $prev = $this->sessions($this->range->prevstart, $this->range->prevend, false);
            $this->cache['learning'] = ['series' => $current['series'], 'total' => $current['total'],
                'prev' => $prev['total'], 'courses' => $current['courses']];
        }
        return $this->cache['learning'];
    }

    /**
     * Learning time between two times.
     *
     * @param int $start
     * @param int $end
     * @param bool $withseries
     * @return array
     */
    private function sessions(int $start, int $end, bool $withseries): array {
        global $DB;
        [$logsql, $params] = $this->org->log_sql('l');
        [$realsql, $rparams] = $this->real_user_sql('l');
        [$learnersql, $lparams] = self::learner_log_sql('l');
        $rs = $DB->get_recordset_sql("SELECT l.id, l.userid, l.courseid, l.timecreated FROM {logstore_standard_log} l
            WHERE $logsql AND $realsql AND $learnersql AND l.timecreated >= :ls AND l.timecreated < :le
            ORDER BY l.userid, l.timecreated, l.id", $params + $rparams + $lparams + ['ls' => $start, 'le' => $end]);
        $series = $withseries ? $this->zeros() : [];
        $courses = [];
        $total = 0;
        $last = null;
        foreach ($rs as $row) {
            if ($last && $last->userid == $row->userid) {
                $gap = $row->timecreated - $last->timecreated;
                if ($gap > 0 && $gap <= self::IDLE) {
                    $total += $gap;
                    $courses[$last->courseid] = ($courses[$last->courseid] ?? 0) + $gap;
                    if ($withseries && ($b = $this->range->bucket_of((int)$last->timecreated)) !== null) {
                        $series[$b] += $gap;
                    }
                }
            }
            $last = $row;
        }
        $rs->close();
        return ['series' => $series, 'total' => $total, 'courses' => $courses];
    }

    /**
     * Payment attempts per status: per bucket, in total and in the previous period.
     *
     * @return array [series by status, totals by status, previous totals by status]
     */
    public function attempts(): array {
        global $DB;
        if (isset($this->cache['attempts'])) {
            return $this->cache['attempts'];
        }
        $statuses = array_keys(payment_source::get_statuses());
        $series = array_fill_keys($statuses, $this->zeros());
        $totals = array_fill_keys($statuses, 0);
        [$from, $where, $params] = payment_source::attempts($this->org, $this->range->start, $this->range->end);
        $hour = $this->range->hour_sql('t.timecreated', $params);
        $rs = $DB->get_recordset_sql("SELECT $hour AS h, t.status, COUNT(1) AS c FROM $from WHERE $where
            GROUP BY $hour, t.status", $params);
        foreach ($rs as $row) {
            if (($b = $this->range->bucket_of_hour((int)$row->h)) !== null) {
                $series[$row->status][$b] += (int)$row->c;
                $totals[$row->status] += (int)$row->c;
            }
        }
        $rs->close();
        [$from, $where, $params] = payment_source::attempts($this->org, $this->range->prevstart, $this->range->prevend);
        $prev = array_fill_keys($statuses, 0);
        foreach ($DB->get_records_sql_menu("SELECT t.status, COUNT(1) FROM $from WHERE $where GROUP BY t.status",
                $params) as $status => $count) {
            $prev[$status] = (int)$count;
        }
        return $this->cache['attempts'] = [$series, $totals, $prev];
    }

    /**
     * Learners (student role) with an active enrolment in the organisation's courses today.
     *
     * @return int[] user ids
     */
    public function current_learner_ids(): array {
        global $DB;
        if (!isset($this->cache['currentlearners'])) {
            $roleids = array_keys(get_archetype_roles('student')) ?: [0];
            [$rolesql, $rparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'clr');
            [$csql, $cparams] = $this->org->course_sql('e.courseid');
            $now = round(time(), -2);
            $this->cache['currentlearners'] = array_map('intval', $DB->get_fieldset_sql("SELECT DISTINCT ue.userid
                FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid JOIN {user} u ON u.id = ue.userid
                WHERE $csql AND u.deleted = 0 AND ue.status = " . ENROL_USER_ACTIVE . " AND e.status = " .
                ENROL_INSTANCE_ENABLED . " AND ue.timestart <= $now AND (ue.timeend = 0 OR ue.timeend > $now)
                  AND EXISTS (SELECT 1 FROM {role_assignments} ra JOIN {context} ctx ON ctx.id = ra.contextid
                               AND ctx.contextlevel = " . CONTEXT_COURSE . "
                             WHERE ctx.instanceid = e.courseid AND ra.userid = ue.userid AND ra.roleid $rolesql)",
                $cparams + $rparams));
        }
        return $this->cache['currentlearners'];
    }

    /**
     * Learners with at least one logged action in the organisation's courses.
     *
     * @return array see {@see self::distinct()}
     */
    public function active_learners(): array {
        if (!isset($this->cache['activelearners'])) {
            [$logsql, $params] = $this->course_log_sql('l');
            [$learnersql, $lparams] = self::learner_log_sql('l');
            $this->cache['activelearners'] = $this->distinct('l.timecreated', 'l.userid', '{logstore_standard_log} l',
                "$logsql AND $learnersql", $params + $lparams);
        }
        return $this->cache['activelearners'];
    }

    /**
     * Logged actions of learners or teachers in the organisation's courses.
     *
     * @param bool $teachers teacher actions instead of learner actions
     * @return array see {@see self::count()}
     */
    public function role_events(bool $teachers): array {
        $key = $teachers ? 'teacherevents' : 'learnerevents';
        if (!isset($this->cache[$key])) {
            [$logsql, $params] = $this->course_log_sql('l');
            [$rolesql, $rparams] = $teachers ? self::teacher_log_sql('l') : self::learner_log_sql('l');
            $this->cache[$key] = $this->count('l.timecreated', '{logstore_standard_log} l', "$logsql AND $rolesql",
                $params + $rparams);
        }
        return $this->cache[$key];
    }

    /**
     * Course completions in the organisation's courses.
     *
     * @return array see {@see self::count()}
     */
    public function completions(): array {
        if (!isset($this->cache['completions'])) {
            [$ccsql, $params] = $this->org->course_sql('cc.course');
            $this->cache['completions'] = $this->count('cc.timecompleted',
                '{course_completions} cc JOIN {user} u ON u.id = cc.userid', "$ccsql AND u.deleted = 0", $params);
        }
        return $this->cache['completions'];
    }

    /**
     * Course page views in the organisation's courses, and the distinct users who viewed them.
     *
     * @return array ['count' => count(), 'users' => distinct()]
     */
    public function course_access(): array {
        global $DB;
        if (!isset($this->cache['courseaccess'])) {
            [$logsql, $params] = $this->course_log_sql('l');
            $where = "$logsql AND l.eventname = :courseviewed";
            $params['courseviewed'] = '\\core\\event\\course_viewed';
            $this->cache['courseaccess'] = [
                'count' => $this->count('l.timecreated', '{logstore_standard_log} l', $where, $params),
                'users' => $this->distinct('l.timecreated', 'l.userid', '{logstore_standard_log} l', $where, $params),
            ];
        }
        return $this->cache['courseaccess'];
    }

    /**
     * Change in percent, or null when it cannot be calculated (no previous value).
     *
     * @param float $current
     * @param float $previous
     * @return float|null
     */
    public static function change(float $current, float $previous): ?float {
        if ($previous <= 0) {
            return null;
        }
        return round(($current - $previous) / $previous * 100, 1);
    }
}

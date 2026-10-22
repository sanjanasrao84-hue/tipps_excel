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
 * Plans, their prices and what they cover.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_manager {

    /** @var string One whole course. */
    const TYPE_COURSE = 'course';
    /** @var string A bundle of selected courses. */
    const TYPE_BUNDLE = 'bundle';
    /** @var string Only the video activities of one course. */
    const TYPE_VIDEO = 'video';
    /** @var string Full LMS: every eligible course. */
    const TYPE_ALLACCESS = 'allaccess';

    /** @var string Scope row: a course is included. */
    const SCOPE_COURSE = 'course';
    /** @var string Scope row: a category is included. */
    const SCOPE_CATEGORY = 'category';
    /** @var string Scope row: a course is excluded. */
    const SCOPE_EXCLUDECOURSE = 'excludecourse';
    /** @var string Scope row: a category (and its subcategories) is excluded. */
    const SCOPE_EXCLUDECATEGORY = 'excludecategory';

    /** @var string Shortname of the course custom field "Excluded from full subscription". */
    const EXCLUSION_FIELD = 'lmssub_excluded';

    /** @var array Request cache of plan id => course ids. */
    protected static $coursecache = [];

    /**
     * All plan types with their names.
     *
     * @return array
     */
    public static function get_types(): array {
        return [
            self::TYPE_COURSE => get_string('type_course', 'local_lmssub'),
            self::TYPE_BUNDLE => get_string('type_bundle', 'local_lmssub'),
            self::TYPE_VIDEO => get_string('type_video', 'local_lmssub'),
            self::TYPE_ALLACCESS => get_string('type_allaccess', 'local_lmssub'),
        ];
    }

    /**
     * Plan types this site has switched on (Type 1, Type 2 or both).
     *
     * @return array
     */
    public static function get_enabled_types(): array {
        $types = self::get_types();
        // Video-only plans are not offered.
        unset($types[self::TYPE_VIDEO]);
        if (!get_config('local_lmssub', 'enablecoursesubs')) {
            unset($types[self::TYPE_COURSE], $types[self::TYPE_BUNDLE], $types[self::TYPE_VIDEO]);
        }
        if (!get_config('local_lmssub', 'enablefullsubs')) {
            unset($types[self::TYPE_ALLACCESS]);
        }
        return $types;
    }

    /**
     * Preset durations offered on the plan form.
     *
     * @return array days => label
     */
    public static function get_duration_presets(): array {
        return [
            30 => get_string('duration_monthly', 'local_lmssub'),
            90 => get_string('duration_quarterly', 'local_lmssub'),
            180 => get_string('duration_halfyearly', 'local_lmssub'),
            365 => get_string('duration_yearly', 'local_lmssub'),
        ];
    }

    /**
     * Human readable duration.
     *
     * @param int $days
     * @return string
     */
    public static function duration_label(int $days): string {
        $presets = self::get_duration_presets();
        return $presets[$days] ?? get_string('duration_days', 'local_lmssub', $days);
    }

    /**
     * Formats an amount stored in paise.
     *
     * @param int $paise
     * @param string $currency
     * @return string
     */
    public static function format_amount(int $paise, string $currency = 'INR'): string {
        return \core_payment\helper::get_cost_as_string($paise / 100, $currency);
    }

    /**
     * Level of access a plan gives.
     *
     * @param \stdClass $plan
     * @return string full or video
     */
    public static function plan_level(\stdClass $plan): string {
        return $plan->type === self::TYPE_VIDEO ? 'video' : 'full';
    }

    /**
     * Returns a plan.
     *
     * @param int $planid
     * @return \stdClass
     */
    public static function get_plan(int $planid): \stdClass {
        global $DB;
        return $DB->get_record('local_lmssub_plan', ['id' => $planid], '*', MUST_EXIST);
    }

    /**
     * Returns the duration options of a plan.
     *
     * @param int $planid
     * @param bool $activeonly
     * @return array
     */
    public static function get_prices(int $planid, bool $activeonly = true): array {
        global $DB;
        $params = ['planid' => $planid];
        if ($activeonly) {
            $params['active'] = 1;
        }
        return $DB->get_records('local_lmssub_price', $params, 'sortorder, durationdays');
    }

    /**
     * Returns the scope rows of a plan.
     *
     * @param int $planid
     * @return array
     */
    public static function get_scopes(int $planid): array {
        global $DB;
        return $DB->get_records('local_lmssub_scope', ['planid' => $planid], 'id');
    }

    /**
     * Creates or updates a plan from the plan form data.
     *
     * @param \stdClass $data
     * @return int plan id
     */
    public static function save_plan(\stdClass $data): int {
        global $DB, $USER;

        $now = time();
        $plan = (object) [
            'name' => trim($data->name),
            'description' => $data->description ?? '',
            'type' => $data->type,
            'active' => empty($data->active) ? 0 : 1,
            'devicelimit' => max(1, (int) $data->devicelimit),
            'gracedays' => max(0, (int) $data->gracedays),
            'autorenew' => empty($data->autorenew) ? 0 : 1,
            'usermodified' => $USER->id,
            'timemodified' => $now,
        ];

        $transaction = $DB->start_delegated_transaction();
        if (!empty($data->id)) {
            $plan->id = (int) $data->id;
            // The type decides what the subscribers already hold, so it cannot change.
            $plan->type = $DB->get_field('local_lmssub_plan', 'type', ['id' => $plan->id], MUST_EXIST);
            $DB->update_record('local_lmssub_plan', $plan);
        } else {
            $plan->timecreated = $now;
            $plan->sortorder = (int) $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) + 1 FROM {local_lmssub_plan}');
            $plan->id = $DB->insert_record('local_lmssub_plan', $plan);
        }

        self::save_prices($plan->id, $data->prices ?? []);
        self::save_scopes($plan, $data);
        $transaction->allow_commit();

        self::reset_caches();
        self::ensure_enrol_instances(self::courses_for_plan($plan->id));
        access_engine::queue_plan_sync($plan->id);

        return $plan->id;
    }

    /**
     * Saves duration options. Removed options are kept inactive, as subscriptions may point to them.
     *
     * @param int $planid
     * @param array $prices list of ['durationdays' => int, 'amount' => float rupees]
     */
    protected static function save_prices(int $planid, array $prices): void {
        global $DB;

        $existing = [];
        foreach ($DB->get_records('local_lmssub_price', ['planid' => $planid]) as $row) {
            $existing[$row->durationdays . ':' . $row->amount] = $row;
        }
        $keep = [];
        $sortorder = 0;
        foreach ($prices as $price) {
            $days = (int) $price['durationdays'];
            $paise = (int) round(((float) $price['amount']) * 100);
            $key = $days . ':' . $paise;
            if ($days <= 0 || isset($keep[$key])) {
                continue;
            }
            if (isset($existing[$key])) {
                $row = $existing[$key];
                $row->active = 1;
                $row->sortorder = $sortorder++;
                $DB->update_record('local_lmssub_price', $row);
            } else {
                $row = (object) ['planid' => $planid, 'durationdays' => $days, 'amount' => $paise,
                    'currency' => 'INR', 'active' => 1, 'sortorder' => $sortorder++];
                $row->id = $DB->insert_record('local_lmssub_price', $row);
            }
            $keep[$key] = $row->id;
        }
        foreach ($existing as $key => $row) {
            if (!isset($keep[$key]) && $row->active) {
                $DB->set_field('local_lmssub_price', 'active', 0, ['id' => $row->id]);
            }
        }
    }

    /**
     * Replaces the scope rows of a plan.
     *
     * @param \stdClass $plan
     * @param \stdClass $data
     */
    protected static function save_scopes(\stdClass $plan, \stdClass $data): void {
        global $DB;

        $DB->delete_records('local_lmssub_scope', ['planid' => $plan->id]);
        $rows = [];
        $add = function(string $type, $ids, int $includesub = 1) use (&$rows, $plan) {
            foreach (array_unique(array_filter(array_map('intval', (array) $ids))) as $id) {
                $rows[] = (object) ['planid' => $plan->id, 'scopetype' => $type, 'targetid' => $id,
                    'includesub' => $includesub];
            }
        };

        if ($plan->type === self::TYPE_ALLACCESS) {
            $add(self::SCOPE_CATEGORY, $data->categories ?? [], empty($data->includesub) ? 0 : 1);
            $add(self::SCOPE_EXCLUDECOURSE, $data->excludecourses ?? []);
            $add(self::SCOPE_EXCLUDECATEGORY, $data->excludecategories ?? []);
        } else {
            $add(self::SCOPE_COURSE, $data->courses ?? []);
        }
        if ($rows) {
            $DB->insert_records('local_lmssub_scope', $rows);
        }
    }

    /**
     * Course ids a plan covers.
     *
     * Course plans: exactly the chosen courses. Full-LMS plans: every visible course in the chosen
     * categories (or the whole site when none are chosen), minus excluded courses and categories,
     * minus courses marked "Excluded from full subscription".
     *
     * @param int $planid
     * @return int[] course ids, keyed by id
     */
    public static function courses_for_plan(int $planid): array {
        global $DB;

        if (isset(self::$coursecache[$planid])) {
            return self::$coursecache[$planid];
        }
        $plan = self::get_plan($planid);
        $scopes = self::get_scopes($planid);
        $bytype = [];
        foreach ($scopes as $scope) {
            $bytype[$scope->scopetype][] = $scope;
        }

        if ($plan->type !== self::TYPE_ALLACCESS) {
            $ids = array_map(fn($s) => (int) $s->targetid, $bytype[self::SCOPE_COURSE] ?? []);
            $courses = [];
            if ($ids) {
                [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $params['siteid'] = SITEID;
                $courses = $DB->get_fieldset_select('course', 'id', "id $insql AND id <> :siteid", $params);
            }
            return self::$coursecache[$planid] = self::keyed($courses);
        }

        $where = ['c.id <> :siteid', 'c.visible = 1'];
        $params = ['siteid' => SITEID];

        $include = [];
        foreach ($bytype[self::SCOPE_CATEGORY] ?? [] as $i => $scope) {
            $include[] = self::category_condition($scope->targetid, (bool) $scope->includesub, "inc{$i}", $params);
        }
        if ($include) {
            $where[] = '(' . implode(' OR ', $include) . ')';
        }
        foreach ($bytype[self::SCOPE_EXCLUDECATEGORY] ?? [] as $i => $scope) {
            $where[] = 'NOT ' . self::category_condition($scope->targetid, true, "exc{$i}", $params);
        }
        $excluded = array_map(fn($s) => (int) $s->targetid, $bytype[self::SCOPE_EXCLUDECOURSE] ?? []);
        $excluded = array_merge($excluded, self::get_excluded_courses());
        if ($excluded) {
            [$notin, $nparams] = $DB->get_in_or_equal($excluded, SQL_PARAMS_NAMED, 'ex', false);
            $where[] = "c.id $notin";
            $params += $nparams;
        }

        $sql = "SELECT c.id
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE " . implode(' AND ', $where);
        return self::$coursecache[$planid] = self::keyed($DB->get_fieldset_sql($sql, $params));
    }

    /**
     * SQL condition matching courses in a category, optionally with its subcategories.
     *
     * @param int $categoryid
     * @param bool $includesub
     * @param string $prefix
     * @param array $params
     * @return string
     */
    protected static function category_condition(int $categoryid, bool $includesub, string $prefix, array &$params): string {
        global $DB;
        $params["{$prefix}id"] = $categoryid;
        if (!$includesub) {
            return "(cc.id = :{$prefix}id)";
        }
        $path = $DB->get_field('course_categories', 'path', ['id' => $categoryid]);
        if ($path === false) {
            return "(cc.id = :{$prefix}id)";
        }
        $params["{$prefix}path"] = $path . '/%';
        return "(cc.id = :{$prefix}id OR " . $DB->sql_like('cc.path', ":{$prefix}path") . ")";
    }

    /**
     * Ids of plans that cover a course (inactive plans still give access to existing subscribers).
     *
     * @param int $courseid
     * @return int[]
     */
    public static function plans_covering_course(int $courseid): array {
        global $DB;
        $planids = [];
        foreach ($DB->get_fieldset_select('local_lmssub_plan', 'id', '1 = 1') as $planid) {
            if (isset(self::courses_for_plan((int) $planid)[$courseid])) {
                $planids[] = (int) $planid;
            }
        }
        return $planids;
    }

    /**
     * Course ids marked "Excluded from full subscription".
     *
     * @return int[]
     */
    public static function get_excluded_courses(): array {
        global $DB;
        $sql = "SELECT d.instanceid
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                  JOIN {customfield_category} cat ON cat.id = f.categoryid
                 WHERE f.shortname = :shortname AND cat.component = 'core_course' AND cat.area = 'course'
                   AND d.intvalue = 1";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['shortname' => self::EXCLUSION_FIELD]));
    }

    /**
     * Whether a course is marked "Excluded from full subscription".
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_course_excluded(int $courseid): bool {
        return in_array($courseid, self::get_excluded_courses(), true);
    }

    /**
     * Creates the locked course custom field "Excluded from full subscription", if missing.
     */
    public static function ensure_exclusion_field(): void {
        global $DB;

        $handler = \core_course\customfield\course_handler::create();
        $exists = $DB->record_exists_sql("SELECT 1
                  FROM {customfield_field} f
                  JOIN {customfield_category} cat ON cat.id = f.categoryid
                 WHERE f.shortname = :shortname AND cat.component = 'core_course' AND cat.area = 'course'",
            ['shortname' => self::EXCLUSION_FIELD]);
        if ($exists) {
            return;
        }
        $categoryid = $handler->create_category(get_string('customfieldcategory', 'local_lmssub'));
        $category = \core_customfield\category_controller::create($categoryid);
        $field = \core_customfield\field_controller::create(0, (object) ['type' => 'checkbox'], $category);
        $handler->save_field_configuration($field, (object) [
            'name' => get_string('excludedfield', 'local_lmssub'),
            'shortname' => self::EXCLUSION_FIELD,
            'description' => get_string('excludedfield_desc', 'local_lmssub'),
            'descriptionformat' => FORMAT_HTML,
            'configdata' => [
                'required' => 0,
                'uniquevalues' => 0,
                // Locked: only managers may change it, not every teacher.
                'locked' => 1,
                'visibility' => \core_course\customfield\course_handler::NOTVISIBLE,
                'checkbydefault' => 0,
            ],
        ]);
    }

    /**
     * Adds the subscription enrolment method to courses that do not have it yet.
     *
     * @param int[] $courseids
     */
    public static function ensure_enrol_instances(array $courseids): void {
        $plugin = enrol_get_plugin('lmssub');
        foreach ($courseids as $courseid) {
            $plugin->get_or_create_instance((int) $courseid);
        }
    }

    /**
     * The payment account plans are paid into: the configured one, or the only enabled account.
     *
     * @return int 0 when none is available
     */
    public static function get_payment_account_id(): int {
        global $DB;
        $accountid = (int) get_config('local_lmssub', 'paymentaccount');
        if ($accountid && $DB->record_exists('payment_accounts', ['id' => $accountid, 'enabled' => 1, 'archived' => 0])) {
            return $accountid;
        }
        $accounts = $DB->get_fieldset_select('payment_accounts', 'id', 'enabled = 1 AND archived = 0');
        return count($accounts) === 1 ? (int) reset($accounts) : 0;
    }

    /**
     * Plans a learner can buy now, with their active duration options, optionally only those covering a course.
     *
     * Full-LMS plans come first, as the plan that opens everything.
     *
     * @param int $courseid 0 for all
     * @return array of plan records, each with ->prices and ->courseids
     */
    public static function get_purchasable_plans(int $courseid = 0): array {
        global $DB;

        $types = self::get_enabled_types();
        if (!$types) {
            return [];
        }
        [$typein, $params] = $DB->get_in_or_equal(array_keys($types), SQL_PARAMS_NAMED);
        $plans = $DB->get_records_select('local_lmssub_plan', "active = 1 AND type $typein", $params, 'sortorder, id');

        $result = [];
        foreach ($plans as $plan) {
            $plan->prices = self::get_prices((int) $plan->id);
            $plan->courseids = self::courses_for_plan((int) $plan->id);
            if (!$plan->prices || !$plan->courseids || ($courseid && !isset($plan->courseids[$courseid]))) {
                continue;
            }
            $result[$plan->id] = $plan;
        }
        uasort($result, fn($a, $b) => ($b->type === self::TYPE_ALLACCESS) <=> ($a->type === self::TYPE_ALLACCESS));
        return $result;
    }

    /**
     * Clears the request cache, after plans or courses change.
     */
    public static function reset_caches(): void {
        self::$coursecache = [];
    }

    /**
     * Turns a list of ids into an id => id array.
     *
     * @param array $ids
     * @return int[]
     */
    protected static function keyed(array $ids): array {
        $result = [];
        foreach ($ids as $id) {
            $result[(int) $id] = (int) $id;
        }
        return $result;
    }
}

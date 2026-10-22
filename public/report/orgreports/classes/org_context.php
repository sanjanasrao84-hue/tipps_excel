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

namespace report_orgreports;

use context;
use context_course;
use context_coursecat;
use context_system;
use moodle_exception;

/**
 * The organisation a report runs in and what the current user may see in it.
 *
 * An organisation is a top-level course category with all its subcategories and courses. Nothing is stored about it:
 * it is resolved from the category tree and the user's capabilities on every request, and every query of the dashboard
 * is limited with the SQL helpers of this class. Users with report/orgreports:viewall in the system context also get
 * the "All organisations" view (id {@see self::ALL}). An organisation can also be narrowed to one of its courses
 * ({@see self::$courseid}): every query then only sees that course, its participants and its subscription plans.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class org_context {

    /** @var int id of the "All organisations" view. */
    const ALL = 0;

    /** @var int organisation id, or self::ALL. */
    public readonly int $id;

    /** @var string display name. */
    public readonly string $name;

    /** @var context category context, or system context for all organisations. */
    public readonly context $context;

    /** @var bool the whole organisation (false: only the courses where the user holds viewcourse). */
    public readonly bool $orglevel;

    /** @var bool revenue and the Sales section may be shown. */
    public readonly bool $cansales;

    /** @var bool downloads are allowed. */
    public readonly bool $canexport;

    /** @var int course the report is narrowed to, 0 for the whole organisation. */
    public readonly int $courseid;

    /** @var \stdClass[] courses the user may report on, by id (only the selected one when narrowed to a course). */
    private array $courses;

    /** @var \stdClass[] every course the user may report on in the organisation, by id. */
    private array $orgcourses;

    /** @var int[]|null cache of the subscription plans attributed to the organisation. */
    private ?array $orgplanids = null;

    /** @var int[]|null cache of the subscription plans attributed to the report (organisation or course). */
    private ?array $planids = null;

    /**
     * Use {@see self::for_user()}.
     *
     * @param int $id
     * @param string $name
     * @param context $context
     * @param bool $orglevel
     * @param array $courses
     * @param int $userid
     * @param int $courseid one of the courses to narrow the report to, 0 for all
     */
    private function __construct(int $id, string $name, context $context, bool $orglevel, array $courses, int $userid,
            int $courseid = 0) {
        $this->id = $id;
        $this->name = $name;
        $this->context = $context;
        $this->orglevel = $orglevel;
        $this->courseid = $courseid;
        $this->orgcourses = $courses;
        $this->courses = $courseid ? [$courseid => $courses[$courseid]] : $courses;
        $courses = $this->courses;
        $this->cansales = $orglevel && has_capability('report/orgreports:viewsales', $context, $userid);
        if ($orglevel) {
            $this->canexport = has_capability('report/orgreports:export', $context, $userid);
        } else {
            // A download must hold what the page shows, so every visible course needs the export capability.
            $canexport = (bool)$courses;
            foreach ($courses as $course) {
                $canexport = $canexport && has_capability('report/orgreports:export', context_course::instance($course->id),
                    $userid);
            }
            $this->canexport = $canexport;
        }
    }

    /**
     * Organisations the user may open with their access level.
     *
     * @param int $userid
     * @return array id => ['name', 'orglevel', 'courses']
     */
    private static function resolve(int $userid): array {
        global $DB;
        $result = [];
        $fields = 'c.id, c.fullname, c.shortname, c.category, c.visible';

        if (has_capability('report/orgreports:viewall', context_system::instance(), $userid)) {
            $courses = $DB->get_records_sql("SELECT $fields FROM {course} c WHERE c.id <> :site ORDER BY c.sortorder",
                ['site' => SITEID]);
            $result[self::ALL] = ['name' => get_string('allorganisations', 'report_orgreports'), 'orglevel' => true,
                'courses' => $courses];
        }

        $categories = $DB->get_records('course_categories', null, 'sortorder', 'id, name, parent, path');
        $orgof = [];
        $toplevel = [];
        foreach ($categories as $category) {
            $orgof[$category->id] = (int)explode('/', trim($category->path, '/'))[0];
            if (!$category->parent) {
                $toplevel[$category->id] = $category;
            }
        }

        $found = [];
        foreach ($toplevel as $orgid => $category) {
            if (has_capability('report/orgreports:view', context_coursecat::instance($orgid), $userid)) {
                $catids = array_keys(array_filter($orgof, fn($o) => $o === $orgid));
                [$insql, $params] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED);
                $found[$orgid] = ['orglevel' => true, 'courses' => $DB->get_records_sql(
                    "SELECT $fields FROM {course} c WHERE c.category $insql ORDER BY c.sortorder", $params)];
            }
        }
        // Teachers: only the courses where they hold the course-level capability.
        $mine = get_user_capability_course('report/orgreports:viewcourse', $userid, true,
            'fullname, shortname, category, visible', 'sortorder');
        foreach ($mine ?: [] as $course) {
            $orgid = $orgof[$course->category] ?? 0;
            if (!$orgid || !empty($found[$orgid]['orglevel'])) {
                continue;
            }
            $found[$orgid] ??= ['orglevel' => false, 'courses' => []];
            $found[$orgid]['courses'][$course->id] = $course;
        }
        foreach ($toplevel as $orgid => $category) {
            if (isset($found[$orgid])) {
                $result[$orgid] = $found[$orgid] + ['name' => format_string($category->name, true,
                    ['context' => context_coursecat::instance($orgid)])];
            }
        }
        return $result;
    }

    /**
     * Organisations the user may open.
     *
     * @param int $userid 0 for the current user
     * @return string[] id => name, "All organisations" first when allowed
     */
    public static function get_available_orgs(int $userid = 0): array {
        global $USER;
        return array_map(fn($o) => $o['name'], self::resolve($userid ?: (int)$USER->id));
    }

    /**
     * Context of an organisation for a user, checked against their capabilities.
     *
     * @param int|null $orgid null for the first one the user may open
     * @param int $userid 0 for the current user
     * @param int $courseid narrow to this course; ignored for "All organisations" and courses the user may not see
     * @return self
     * @throws moodle_exception without access
     */
    public static function for_user(?int $orgid, int $userid = 0, int $courseid = 0): self {
        global $USER;
        $userid = $userid ?: (int)$USER->id;
        $orgs = self::resolve($userid);
        if ($orgid === null && $orgs) {
            $orgid = array_key_first($orgs);
        }
        if ($orgid === null || !isset($orgs[$orgid])) {
            throw new moodle_exception('noaccess', 'report_orgreports');
        }
        $org = $orgs[$orgid];
        $context = $orgid === self::ALL ? context_system::instance() : context_coursecat::instance($orgid);
        if ($orgid === self::ALL || !isset($org['courses'][$courseid])) {
            $courseid = 0;
        }
        return new self($orgid, $org['name'], $context, $org['orglevel'], $org['courses'], $userid, $courseid);
    }

    /**
     * Whether this is the cross-organisation view.
     *
     * @return bool
     */
    public function is_all(): bool {
        return $this->id === self::ALL;
    }

    /**
     * Courses the user may report on.
     *
     * @return \stdClass[] by id
     */
    public function get_courses(): array {
        return $this->courses;
    }

    /**
     * Every course of the organisation the user may report on, also when the report is narrowed to one of them: the
     * options of the course filter. Empty for "All organisations", which is not narrowed to a course.
     *
     * @return \stdClass[] by id
     */
    public function get_org_courses(): array {
        return $this->is_all() ? [] : $this->orgcourses;
    }

    /**
     * Ids of those courses, or [0] when there are none so IN () stays valid and matches nothing.
     *
     * @param int $courseid only this course when it is one of them
     * @return int[]
     */
    public function get_course_ids(int $courseid = 0): array {
        if ($courseid) {
            return isset($this->courses[$courseid]) ? [$courseid] : [0];
        }
        return array_keys($this->courses) ?: [0];
    }

    /**
     * "column IN (courses)".
     *
     * @param string $column
     * @param int $courseid
     * @return array [sql, params]
     */
    public function course_sql(string $column, int $courseid = 0): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($this->get_course_ids($courseid), SQL_PARAMS_NAMED, 'orgc');
        return ["$column $insql", $params];
    }

    /**
     * Ids of the organisation's categories.
     *
     * @return int[]
     */
    public function get_category_ids(): array {
        global $DB;
        if ($this->is_all()) {
            return array_map('intval', $DB->get_fieldset_select('course_categories', 'id', '1 = 1'));
        }
        $path = $DB->get_field('course_categories', 'path', ['id' => $this->id]);
        return array_map('intval', $DB->get_fieldset_select('course_categories', 'id',
            'id = :id OR ' . $DB->sql_like('path', ':path'), ['id' => $this->id, 'path' => $path . '/%']));
    }

    /**
     * Subscription plans (local_lmssub) attributed to the report: those of the organisation, or of the course it is
     * narrowed to.
     *
     * @return int[] or [0]
     */
    public function get_plan_ids(): array {
        if ($this->planids === null) {
            $this->planids = $this->courseid ? $this->get_course_plan_ids($this->courseid) : $this->get_org_plan_ids();
        }
        return $this->planids;
    }

    /**
     * Subscription plans (local_lmssub) attributed to the organisation: those whose included courses or categories are
     * in it. Plans without include scopes cover the whole site and only show in "All organisations".
     *
     * @return int[] or [0]
     */
    private function get_org_plan_ids(): array {
        global $DB;
        if ($this->orgplanids === null) {
            if (!$DB->get_manager()->table_exists('local_lmssub_scope')) {
                $this->orgplanids = [0];
            } else if ($this->is_all()) {
                $this->orgplanids = array_map('intval', $DB->get_fieldset_select('local_lmssub_plan', 'id', '1 = 1')) ?: [0];
            } else {
                [$csql, $cparams] = $DB->get_in_or_equal(array_keys($this->orgcourses) ?: [0], SQL_PARAMS_NAMED, 'pc');
                [$catsql, $catparams] = $DB->get_in_or_equal($this->get_category_ids(), SQL_PARAMS_NAMED, 'pcat');
                $this->orgplanids = array_map('intval', $DB->get_fieldset_sql("SELECT DISTINCT planid
                    FROM {local_lmssub_scope}
                    WHERE (scopetype = 'course' AND targetid $csql) OR (scopetype = 'category' AND targetid $catsql)",
                    $cparams + $catparams)) ?: [0];
            }
        }
        return $this->orgplanids;
    }

    /**
     * Plans of the organisation that include one course (directly, through its category, or a parent category whose
     * scope includes subcategories).
     *
     * @param int $courseid one of the organisation's courses
     * @return int[] or [0]
     */
    public function get_course_plan_ids(int $courseid): array {
        global $DB;
        if (!isset($this->orgcourses[$courseid]) || !$DB->get_manager()->table_exists('local_lmssub_scope')) {
            return [0];
        }
        $category = (int)$this->orgcourses[$courseid]->category;
        $path = (string)$DB->get_field('course_categories', 'path', ['id' => $category]);
        $ancestors = array_map('intval', array_filter(explode('/', $path))) ?: [0];
        [$catsql, $params] = $DB->get_in_or_equal($ancestors, SQL_PARAMS_NAMED, 'cpa');
        [$plansql, $pparams] = $DB->get_in_or_equal($this->get_org_plan_ids(), SQL_PARAMS_NAMED, 'cpp');
        $ids = $DB->get_fieldset_sql("SELECT DISTINCT planid FROM {local_lmssub_scope}
            WHERE planid $plansql AND ((scopetype = 'course' AND targetid = :cid)
               OR (scopetype = 'category' AND targetid $catsql AND (targetid = :owncat OR includesub = 1)))",
            $params + $pparams + ['cid' => $courseid, 'owncat' => $category]);
        return array_map('intval', $ids) ?: [0];
    }

    /**
     * Condition that a user belongs to the organisation: enrolled in one of its visible courses, or holding a role in
     * one of its categories. For "All organisations" every real user. Narrowed to a course: enrolled in that course.
     *
     * @param string $column user id column
     * @return array [sql, params]
     */
    public function member_sql(string $column): array {
        global $DB, $CFG;
        if ($this->is_all()) {
            return ["$column <> " . (int)$CFG->siteguest, []];
        }
        [$csql, $params] = $DB->get_in_or_equal($this->get_course_ids(), SQL_PARAMS_NAMED, 'mc');
        $sql = "$column IN (SELECT mue.userid FROM {user_enrolments} mue JOIN {enrol} me ON me.id = mue.enrolid
                             WHERE me.courseid $csql)";
        if ($this->orglevel && !$this->courseid) {
            [$catsql, $catparams] = $DB->get_in_or_equal($this->get_category_ids(), SQL_PARAMS_NAMED, 'mcat');
            $sql = "($sql OR $column IN (SELECT mra.userid FROM {role_assignments} mra
                     JOIN {context} mctx ON mctx.id = mra.contextid AND mctx.contextlevel = " . CONTEXT_COURSECAT . "
                    WHERE mctx.instanceid $catsql))";
            $params += $catparams;
        }
        return [$sql, $params];
    }

    /**
     * Condition limiting payments (or payment attempts) to what the organisation sold.
     *
     * Needs the aliases e (enrol, joined on the fee item) and pr (local_lmssub_price, joined on the plan item) from
     * {@see local\payment_source}.
     *
     * @return array [sql, params]
     */
    public function sales_sql(): array {
        global $DB;
        if ($this->is_all()) {
            return ['1 = 1', []];
        }
        [$csql, $params] = $DB->get_in_or_equal($this->get_course_ids(), SQL_PARAMS_NAMED, 'sc');
        $sql = "(e.courseid $csql";
        if ($DB->get_manager()->table_exists('local_lmssub_price')) {
            [$psql, $pparams] = $DB->get_in_or_equal($this->get_plan_ids(), SQL_PARAMS_NAMED, 'sp');
            $sql .= " OR pr.planid $psql";
            $params += $pparams;
        }
        return [$sql . ')', $params];
    }

    /**
     * Condition limiting log entries to the organisation's courses.
     *
     * @param string $alias logstore_standard_log alias
     * @param int $courseid
     * @return array [sql, params]
     */
    public function log_sql(string $alias, int $courseid = 0): array {
        global $DB;
        if ($this->is_all() && !$courseid) {
            return ["$alias.courseid <> :logsite AND $alias.courseid > 0", ['logsite' => SITEID]];
        }
        [$csql, $params] = $DB->get_in_or_equal($this->get_course_ids($courseid), SQL_PARAMS_NAMED, 'lc');
        return ["$alias.courseid $csql", $params];
    }
}

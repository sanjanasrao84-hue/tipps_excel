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

namespace theme_eadtraining\local;

use core\navigation\views\primary;
use moodle_page;

/**
 * Which primary navigation tab the custom pages of this site belong to.
 *
 * Core finds the active tab from the navigation tree and the admin tree by exact URL; pages it cannot place (custom
 * reports with URL parameters, category or user context pages, plugin pages outside the admin tree...) fall back to
 * "Home". This maps those pages by script path, from the primary_extend hook (before core picks the active node), so no
 * page has to set it itself. A page that calls $PAGE->set_primary_active_tab() keeps its choice, and pages inside a
 * course or an activity keep the core behaviour.
 *
 * @package    theme_eadtraining
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class primary_tab {

    /** @var string Site administration. */
    public const ADMIN = 'siteadminnode';

    /** @var string Dashboard. */
    public const DASHBOARD = 'myhome';

    /** @var string My courses. */
    public const MYCOURSES = 'mycourses';

    /** @var string Home. */
    public const HOME = 'home';

    /** @var string Subscription plans (added by local_lmssub\hook_callbacks::primary_extend). */
    public const PLANS = 'local_lmssub_plans';

    /**
     * Script paths (from the web root) and the tab they belong to. A path ending in "/" covers the whole folder.
     * The first match wins, so list specific files before their folder.
     */
    public const MAP = [
        // Site administration: organisation and payment reports, subscription admin, theme admin tools.
        '/report/orgreports/' => self::ADMIN,
        '/payment/gateway/razorpay/upi_payments.php' => self::ADMIN,
        '/local/lmssub/attempts.php' => self::ADMIN,
        '/theme/eadtraining/banners/' => self::ADMIN,
        '/theme/eadtraining/quickstart/' => self::ADMIN,
        '/theme/eadtraining/report.php' => self::ADMIN,
        // Subscription plans: the catalogue, choosing a bundle, checkout, my subscriptions and renewals.
        '/local/lmssub/index.php' => self::PLANS,
        '/local/lmssub/bundle.php' => self::PLANS,
        '/local/lmssub/checkout.php' => self::PLANS,
        '/local/lmssub/my.php' => self::PLANS,
        '/payment/gateway/razorpay/renew.php' => self::PLANS,
        // Dashboard: the calendar lives on the Dashboard (its own tab was removed).
        '/local/gcal/' => self::DASHBOARD,
        // My courses: the learner's certificates.
        '/mod/customcert/my_certificates.php' => self::MYCOURSES,
        // Home: the home page editor.
        '/theme/eadtraining/_editor/' => self::HOME,
    ];

    /**
     * The tab of a script path, if it is one of this site's custom pages.
     *
     * @param string $script e.g. "/report/orgreports/index.php"
     * @return string|null
     */
    public static function for_script(string $script): ?string {
        $script = '/' . ltrim(str_replace('\\', '/', $script), '/');
        foreach (self::MAP as $path => $tab) {
            if (str_ends_with($path, '/') ? str_starts_with($script, $path) : $script === $path) {
                return $tab;
            }
        }
        return null;
    }

    /**
     * Reports of the site home (Home > Reports) and the URL parameter that holds the site course id there.
     *
     * Site administration > Reports links to the same scripts without the site course id (Logs "?id=0", Live logs and
     * Statistics without a course, Event monitoring rules "?courseid=0"), so an explicit site course id means the page
     * was opened from Home. Some of these scripts then set up the admin page anyway and pin Site administration.
     */
    public const SITE_COURSE_REPORTS = [
        '/report/view.php' => 'courseid',
        '/report/log/index.php' => 'id',
        '/report/loglive/index.php' => 'id',
        '/report/stats/index.php' => 'course',
        '/report/outline/index.php' => 'id',
        '/report/participation/index.php' => 'id',
        '/report/competency/index.php' => 'id',
        '/report/aiusage/index.php' => 'id',
        '/admin/tool/monitor/managerules.php' => 'courseid',
    ];

    /**
     * Whether the current request is a report of the site home opened from Home > Reports (see SITE_COURSE_REPORTS).
     *
     * @return bool
     */
    public static function is_site_course_report(): bool {
        global $SCRIPT;

        $param = self::SITE_COURSE_REPORTS[str_replace('\\', '/', (string) $SCRIPT)] ?? null;
        return $param !== null && optional_param($param, 0, PARAM_INT) == SITEID;
    }

    /**
     * Whether the current request is Logs opened as a report of the site home (/report/log/index.php?id=1, from
     * Home > Reports, where core adds an empty "iscoursepage"). The Site administration link is "?id=0".
     *
     * @return bool
     */
    public static function is_site_course_logs(): bool {
        global $SCRIPT;

        return str_replace('\\', '/', (string) $SCRIPT) === '/report/log/index.php' && self::is_site_course_report();
    }

    /**
     * Makes Logs opened from Home > Reports behave like the other site reports.
     *
     * For the site course, core report/log/index.php calls admin_externalpage_setup(): the page gets the system context,
     * the admin secondary menu and no "Reports" node, so report_helper::print_report_selector() prints a plain heading
     * instead of the dropdown. This switches that request to the site course context and drops the navigation core
     * already built for the admin tree, before the header is printed.
     *
     * @param moodle_page $page
     * @return void
     */
    public static function use_site_course_context(moodle_page $page): void {
        if (!self::is_site_course_logs() || $page->context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $page->set_context(\context_course::instance(SITEID));
        // The navigation objects are cached on the page (protected properties); drop those built for the system context.
        (function () {
            $this->_settingsnav = null;
            $this->_secondarynav = null;
            $this->_primarynav = null;
            $this->_primarynavcombined = null;
        })->call($page);
    }

    /**
     * Sets the active primary tab of the page being built, when the page did not choose one and belongs to a tab here.
     *
     * @param moodle_page $page
     * @param primary $primary the primary navigation being built (its tabs so far)
     * @return void
     */
    public static function apply(moodle_page $page, primary $primary): void {
        global $SCRIPT;

        // Reports opened from Home > Reports: core pins Site administration for some (Logs, Statistics, Event monitoring
        // rules), but they are reports of the site home.
        if (self::is_site_course_report() && in_array(self::HOME, $primary->get_children_key_list(), true)) {
            $page->set_primary_active_tab(self::HOME);
            return;
        }
        if ($page->get_primary_activate_tab() !== null || empty($SCRIPT)) {
            return;
        }
        // Inside a course or an activity core highlights the right tab (attempts.php also opens per course).
        if (in_array($page->context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE]) && $page->course->id != SITEID) {
            return;
        }
        $tab = self::for_script($SCRIPT);
        if ($tab === null && ($page->pagelayout === 'admin' || str_starts_with((string) $page->pagetype, 'admin-'))) {
            // Any other page of the Site administration tree (admin_externalpage_setup() pages, plugin settings...).
            $tab = self::ADMIN;
        }
        // Only a tab the user has: organisation managers who are not site administrators have no Site administration.
        if ($tab !== null && in_array($tab, $primary->get_children_key_list(), true)) {
            $page->set_primary_active_tab($tab);
        }
    }
}

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
     * Sets the active primary tab of the page being built, when the page did not choose one and belongs to a tab here.
     *
     * @param moodle_page $page
     * @param primary $primary the primary navigation being built (its tabs so far)
     * @return void
     */
    public static function apply(moodle_page $page, primary $primary): void {
        global $SCRIPT;

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

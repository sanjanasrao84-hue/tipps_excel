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
 * Hook callbacks for the razorpay payment gateway.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_razorpay;

use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Hook callbacks for the razorpay payment gateway.
 *
 * @package    paygw_razorpay
 * @copyright  2025 Santosh N.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Shows the "access expires in N days" / "access has expired" banners to the logged in user.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $USER;

        if (during_initial_install() || !isloggedin() || isguestuser() || !get_config('paygw_razorpay', 'expirybanner')) {
            return;
        }
        if (in_array($PAGE->pagelayout, ['embedded', 'popup', 'frametop', 'print', 'redirect', 'maintenance', 'secure'])) {
            return;
        }
        try {
            expiry_manager::add_banners((int) $USER->id);
        } catch (\Throwable $e) {
            // Never break page output because of the banner.
            debugging('paygw_razorpay expiry banner: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

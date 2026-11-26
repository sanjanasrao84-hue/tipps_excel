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

namespace theme_modernui\output;

/**
 * Core renderer for theme_modernui.
 *
 * Kept deliberately small: it only adds the dashboard hero after the page header. Everything else is Boost's.
 *
 * @package    theme_modernui
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * The page header, followed on the Dashboard by the personal welcome hero.
     *
     * @return string HTML
     */
    public function full_header() {
        global $USER;

        $hero = null;
        if ($this->page->pagelayout === 'mydashboard' && dashboard_hero::applies()) {
            // The hero greets the user on every visit, so core's once-a-session "Welcome back" line would repeat it.
            $USER->core_welcome_message = true;
            $hero = new dashboard_hero($USER);
        }

        $html = parent::full_header();
        if ($hero) {
            $html .= $this->render_from_template('theme_modernui/dashboard_hero', $hero->export_for_template($this));
        }
        return $html;
    }
}

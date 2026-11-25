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
 * Admin settings for theme_modernui.
 *
 * Scaffold only: the tabs (Brand, Typography, Shape, Layout, Login page, Presets, Dark mode, Advanced) are
 * built in Step 4. Setting names must not clash with Boost's (brandcolor, scss, scsspre, backgroundimage,
 * loginbackgroundimage), because Boost's SCSS callbacks read $theme->settings of its child themes too.
 *
 * @package    theme_modernui
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingmodernui', get_string('configtitle', 'theme_modernui'));

    $page = new admin_settingpage('theme_modernui_general', get_string('generalsettings', 'theme_modernui'));
    $page->add(new admin_setting_heading(
        'theme_modernui/scaffoldnote',
        '',
        get_string('scaffoldnote', 'theme_modernui'),
    ));
    $settings->add($page);
}

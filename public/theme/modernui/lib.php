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
 * Library functions for theme_modernui.
 *
 * SCSS pipeline (theme_config runs the parent's callbacks first):
 *   1. theme_boost_get_pre_scss()      Boost's own variables. Reads $theme->settings, so this theme must not
 *                                      define the setting names brandcolor / scsspre.
 *   2. theme_modernui_get_pre_scss()   (Step 3/4) SCSS variables that feed the token definitions.
 *   3. theme_modernui_get_main_scss_content()  Boost default preset + scss/modernui.scss.
 *   4. theme_boost_get_extra_scss()    Boost's background images and its "scss" setting, so this theme must
 *                                      not define the setting names scss / backgroundimage.
 *   5. theme_modernui_get_extra_scss() (Step 3/4) The :root CSS variables from the settings, then the custom SCSS.
 *
 * @package    theme_modernui
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content: Boost's default preset (Bootstrap + Moodle) followed by this theme's SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_modernui_get_main_scss_content($theme): string {
    global $CFG;

    $scss = file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    $scss .= "\n" . file_get_contents(__DIR__ . '/scss/modernui.scss');
    return $scss;
}

/**
 * Returns SCSS to prepend. Filled in by the design-system and settings steps.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_modernui_get_pre_scss($theme): string {
    return '';
}

/**
 * Returns SCSS to append. Filled in by the design-system and settings steps.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_modernui_get_extra_scss($theme): string {
    return '';
}

/**
 * Serves the files stored by the theme settings (logos, login background...).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_modernui_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    $fileareas = ['logo', 'logocompact', 'favicon', 'loginbgimage'];
    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, $fileareas, true)) {
        $theme = theme_config::load('modernui');
        // By default, theme files must be cache-able by both browsers and proxies.
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}

/**
 * The user preferences this theme owns. Filled in by the Appearance step.
 *
 * @return array[]
 */
function theme_modernui_user_preferences(): array {
    return [];
}

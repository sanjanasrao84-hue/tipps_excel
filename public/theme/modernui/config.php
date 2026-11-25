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
 * Theme config for theme_modernui, a child of Boost.
 *
 * Layouts are inherited from Boost. A layout file in this theme's layout/ directory takes precedence over Boost's
 * file of the same name, so only the layouts this theme restyles live here.
 *
 * Note: theme_config does not inherit these properties from the parent, so the ones Boost relies on are repeated.
 * Boost's pre/extra SCSS callbacks still run before this theme's own (theme_config walks the parents).
 *
 * @package    theme_modernui
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'modernui';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];
// The editor_scss is left unset on purpose: theme_config then uses Boost's.
$THEME->usefallback = true;
$THEME->scss = function ($theme) {
    return theme_modernui_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_modernui_get_pre_scss';
$THEME->extrascsscallback = 'theme_modernui_get_extra_scss';
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = [
    'notitle' => true,
];

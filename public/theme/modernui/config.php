<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'modernui';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->usefallback = true;
$THEME->scss = function ($theme) {
    return theme_modernui_get_main_scss_content($theme);
};
$THEME->prescsscallback = 'theme_modernui_get_pre_scss';
$THEME->extrascsscallback = 'theme_modernui_get_extra_scss';
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;
// theme_config does not inherit these from Boost. Without yuicssmodules = [] the legacy YUI reset/fonts/grids/base
// stylesheets load on every page; without activityheaderconfig activity pages show their title twice.
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->activityheaderconfig = [
    'notitle' => true,
];

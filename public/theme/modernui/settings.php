<?php
defined('MOODLE_INTERNAL') || die();

use theme_modernui\presets;

// Only needed on admin settings pages.
if ($ADMIN->fulltree) {
    $c = 'theme_modernui';
    $d = presets::get('indigo');
    $cb = 'theme_reset_all_caches';

    $settings = new theme_boost_admin_settingspage_tabs('themesettingmodernui', get_string('configtitle', $c));

    // Presets.
    $page = new admin_settingpage('theme_modernui_presets', get_string('tabpresets', $c));
    $s = new \theme_modernui\admin\setting_presets("$c/designpreset", get_string('designpreset', $c), get_string('designpreset_desc', $c));
    $s->set_updatedcallback($cb);
    $page->add($s);
    $settings->add($page);

    // Brand.
    $page = new admin_settingpage('theme_modernui_brand', get_string('tabbrand', $c));
    foreach (['primarycolor' => $d['primary'], 'accentcolor' => $d['accent'], 'backgroundcolor' => $d['background']] as $key => $default) {
        $s = new admin_setting_configcolourpicker("$c/$key", get_string($key, $c), get_string($key . '_desc', $c), $default);
        $s->set_updatedcallback($cb);
        $page->add($s);
    }
    $page->add(new admin_setting_heading("$c/logonote", '', get_string('logonote', $c)));
    $settings->add($page);

    // Typography.
    $page = new admin_settingpage('theme_modernui_type', get_string('tabtypography', $c));
    $s = new admin_setting_configselect("$c/fontfamily", get_string('fontfamily', $c), get_string('fontfamily_desc', $c), $d['font'], [
        'inter' => 'Inter', 'roboto' => 'Roboto', 'poppins' => 'Poppins', 'system' => get_string('fontsystem', $c),
    ]);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $s = new admin_setting_configtext("$c/customfont", get_string('customfont', $c), get_string('customfont_desc', $c), '', PARAM_TEXT);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $sizes = [];
    foreach ([14, 15, 16, 17, 18] as $px) {
        $sizes[$px] = $px . ' px';
    }
    $s = new admin_setting_configselect("$c/basefontsize", get_string('basefontsize', $c), '', 16, $sizes);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $settings->add($page);

    // Shape.
    $page = new admin_settingpage('theme_modernui_shape', get_string('tabshape', $c));
    $s = new admin_setting_configselect("$c/radius", get_string('radius', $c), '', $d['radius'], [
        'sharp' => get_string('radius_sharp', $c), 'medium' => get_string('radius_medium', $c), 'rounded' => get_string('radius_rounded', $c),
    ]);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $s = new admin_setting_configselect("$c/shadow", get_string('shadow', $c), '', 'soft', [
        'none' => get_string('shadow_none', $c), 'soft' => get_string('shadow_soft', $c), 'strong' => get_string('shadow_strong', $c),
    ]);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $settings->add($page);

    // Layout.
    $page = new admin_settingpage('theme_modernui_layout', get_string('tablayout', $c));
    $s = new admin_setting_configselect("$c/contentwidth", get_string('contentwidth', $c), get_string('contentwidth_desc', $c), 'boxed', [
        'boxed' => get_string('width_boxed', $c), 'full' => get_string('width_full', $c),
    ]);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $settings->add($page);

    // Login.
    $page = new admin_settingpage('theme_modernui_login', get_string('tablogin', $c));
    $s = new admin_setting_configselect("$c/loginlayout", get_string('loginlayout', $c), '', 'split', [
        'split' => get_string('login_split', $c), 'centered' => get_string('login_centered', $c),
    ]);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $s = new admin_setting_configstoredfile("$c/loginbg", get_string('loginbg', $c), get_string('loginbg_desc', $c), 'loginbg');
    $s->set_updatedcallback($cb);
    $page->add($s);
    // Rendered by layout/login.php on each request, so no cache reset is needed.
    $page->add(new admin_setting_configtext("$c/loginwelcometitle", get_string('loginwelcometitle', $c),
        get_string('loginwelcometitle_desc', $c), '', PARAM_TEXT));
    $page->add(new admin_setting_confightmleditor("$c/loginwelcometext", get_string('loginwelcometext', $c),
        get_string('loginwelcometext_desc', $c), ''));
    $settings->add($page);

    // Dark mode.
    $page = new admin_settingpage('theme_modernui_dark', get_string('tabdark', $c));
    $s = new admin_setting_configcheckbox("$c/darkmodeenabled", get_string('darkmodeenabled', $c), get_string('darkmodeenabled_desc', $c), 1);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $s = new admin_setting_configselect("$c/defaultmode", get_string('defaultmode', $c), get_string('defaultmode_desc', $c), 'light', [
        'light' => get_string('mode_light', $c), 'dark' => get_string('mode_dark', $c), 'auto' => get_string('mode_auto', $c),
    ]);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $settings->add($page);

    // Advanced.
    $page = new admin_settingpage('theme_modernui_advanced', get_string('tabadvanced', $c));
    $s = new admin_setting_scsscode("$c/scsspost", get_string('scsspost', $c), get_string('scsspost_desc', $c), '', PARAM_RAW);
    $s->set_updatedcallback($cb);
    $page->add($s);
    $settings->add($page);
}

<?php
defined('MOODLE_INTERNAL') || die();

use theme_modernui\helper;

/**
 * Main SCSS: Boost's SCSS plus this theme's post.scss (variable-driven, no fixed colours).
 */
function theme_modernui_get_main_scss_content($theme) {
    global $CFG;
    require_once($CFG->dirroot . '/theme/boost/lib.php');
    $scss = theme_boost_get_main_scss_content($theme);
    $scss .= file_get_contents(__DIR__ . '/scss/post.scss');
    return $scss;
}

/**
 * Pre SCSS: lets Bootstrap's compile-time variables follow the admin brand colour.
 * (Per-user colour changes are applied at runtime through CSS variables.)
 */
function theme_modernui_get_pre_scss($theme) {
    $a = helper::admin($theme);
    $scss = '$primary: ' . $a['primarycolor'] . ";\n";
    $scss .= '$font-family-sans-serif: ' . helper::font_stack($a) . ";\n";
    return $scss;
}

/**
 * Extra SCSS: CSS variables from admin settings, login background, then the admin's custom SCSS.
 */
function theme_modernui_get_extra_scss($theme) {
    $a = helper::admin($theme);
    $css = ':root{' . helper::css_variables($a) . '}';
    $url = $theme->setting_file_url('loginbg', 'loginbg');
    if (!empty($url)) {
        $css .= ':root{--mu-login-bg:url("' . $url . '");}';
    }
    if (!empty($theme->settings->scsspost)) {
        $css .= "\n" . $theme->settings->scsspost;
    }
    return $css;
}

/**
 * Serve the files uploaded in theme settings (login background).
 */
function theme_modernui_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === 'loginbg') {
        $theme = theme_config::load('modernui');
        return $theme->setting_file_serve('loginbg', $args, $forcedownload, $options);
    }
    send_file_not_found();
}

/**
 * User preferences this theme stores (so the Appearance panel can save them via the API).
 */
function theme_modernui_user_preferences(): array {
    // NULL_ALLOWED: the Appearance panel saves null to delete a preference ("Reset to site defaults"), so the
    // admin setting applies again. With NULL_NOT_ALLOWED core would store '' / 0 instead of deleting it.
    // permissioncallback: every logged-in user may set their own look, like Boost's preferences. Without it core
    // falls back to moodle/user:editownprofile, which some roles do not have.
    $base = [
        'null' => NULL_ALLOWED,
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];
    $text = function (array $choices) use ($base) {
        return $base + ['type' => PARAM_ALPHA, 'default' => null, 'choices' => $choices];
    };
    $flag = $base + ['type' => PARAM_INT, 'default' => null, 'choices' => [0, 1]];
    return [
        'theme_modernui_mode' => $text(['light', 'dark', 'auto']),
        'theme_modernui_accent' => $base + ['type' => PARAM_ALPHANUM, 'default' => null],
        'theme_modernui_fontsize' => $text(['small', 'medium', 'large']),
        'theme_modernui_density' => $text(['comfortable', 'compact']),
        'theme_modernui_motion' => $flag,
        'theme_modernui_contrast' => $flag,
    ];
}

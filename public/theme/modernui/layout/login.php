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
 * Login layout for theme_modernui.
 *
 * Renders Boost's theme_boost/login shell. The split-screen markup comes from this theme's overrides of
 * core/login_layout and core/login_panel, which read the extra context below.
 *
 * Welcome text priority: the theme's Login page settings, then the site's auth instructions, then core's defaults.
 *
 * @package    theme_modernui
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$syscontext = context_system::instance();
$settings = $PAGE->theme->settings;

$centered = ($settings->loginlayout ?? 'split') === 'centered';

$welcometitle = trim((string) ($settings->loginwelcometitle ?? ''));
$welcometitle = $welcometitle !== ''
    ? format_string($welcometitle, true, ['context' => $syscontext])
    : get_string('loginwelcometitle_default', 'theme_modernui',
        format_string($SITE->fullname, true, ['context' => $syscontext]));

$welcometext = trim((string) ($settings->loginwelcometext ?? ''));
if ($welcometext !== '') {
    $welcometext = format_text($welcometext, FORMAT_HTML, ['context' => $syscontext]);
} else if (!empty($CFG->auth_instructions)) {
    $welcometext = format_text($CFG->auth_instructions, FORMAT_MOODLE, ['context' => $syscontext]);
} else {
    $welcometext = html_writer::tag('p', get_string('loginwelcomedescription'));
}

// The full logo already sits above the form (core/loginform), so the panel uses the compact logo only.
$compactlogo = $OUTPUT->get_compact_logo_url(300, 300);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), 'escape' => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes([$centered ? 'mu-login-centered' : 'mu-login-split']),
    'mu_centered' => $centered,
    'mu_welcometitle' => $welcometitle,
    'mu_welcometext' => $welcometext,
    'mu_compactlogo' => $compactlogo ? $compactlogo->out(false) : null,
];

echo $OUTPUT->render_from_template('theme_boost/login', $templatecontext);

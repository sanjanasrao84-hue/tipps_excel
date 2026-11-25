<?php
namespace theme_modernui;

use core\hook\output\before_standard_head_html_generation;
use core\hook\output\before_standard_top_of_body_html_generation;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {
    /** Apply fonts, user colour and attributes before first paint (no flash of wrong theme). */
    public static function before_head(before_standard_head_html_generation $hook): void {
        global $PAGE;
        if ($PAGE->theme->name !== 'modernui') {
            return;
        }
        $hook->add_html(helper::head_html($PAGE->theme));
    }

    /** Add the Appearance button and panel for logged-in users. */
    public static function before_top_of_body(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;
        if ($PAGE->theme->name !== 'modernui' || !isloggedin() || isguestuser()) {
            return;
        }
        $e = helper::effective($PAGE->theme);
        $swatches = ['4F46E5', '0EA5E9', '047857', 'C2410C', 'BE185D', '7C3AED'];
        $context = [
            'darkenabled' => $e['darkenabled'],
            'swatches' => array_map(fn($h) => ['hex' => '#' . $h], $swatches),
        ];
        $hook->add_html($PAGE->get_renderer('core')->render_from_template('theme_modernui/appearance', $context));
        $PAGE->requires->js_call_amd('theme_modernui/appearance', 'init', [[
            'mode' => $e['mode'],
            'accent' => $e['accent'],
            'fontsize' => $e['fontsize'],
            'density' => $e['density'],
            'motion' => $e['motion'],
            'contrast' => $e['contrast'],
            'darkenabled' => $e['darkenabled'],
            'defaultmode' => $e['defaults']['mode'],
        ]]);
    }
}

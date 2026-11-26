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
        // The six accent swatches (data, like the presets). The key names the colour for screen readers.
        $swatches = [
            'indigo' => '#4F46E5', 'sky' => '#0EA5E9', 'emerald' => '#047857',
            'orange' => '#C2410C', 'pink' => '#BE185D', 'violet' => '#7C3AED',
        ];
        $option = fn(string $value, string $current, string $label) => [
            'value' => $value,
            'label' => get_string($label, 'theme_modernui'),
            'checked' => $value === $current,
        ];
        $modes = [$option('light', $e['mode'], 'mode_light')];
        if ($e['darkenabled']) {
            $modes[] = $option('dark', $e['mode'], 'mode_dark');
            $modes[] = $option('auto', $e['mode'], 'mode_auto');
        }
        $context = [
            'darkenabled' => $e['darkenabled'],
            'modes' => $modes,
            'fontsizes' => [
                $option('small', $e['fontsize'], 'size_small'),
                $option('medium', $e['fontsize'], 'size_medium'),
                $option('large', $e['fontsize'], 'size_large'),
            ],
            'densities' => [
                $option('comfortable', $e['density'], 'density_comfortable'),
                $option('compact', $e['density'], 'density_compact'),
            ],
            'motion' => $e['motion'],
            'contrast' => $e['contrast'],
            'customcolor' => strtolower($e['accent'] ?: $e['admin']['primarycolor']),
            'swatches' => array_map(fn($key, $hex) => [
                'hex' => $hex,
                'name' => get_string('swatch_' . $key, 'theme_modernui'),
                'pressed' => strcasecmp($hex, $e['accent']) === 0,
            ], array_keys($swatches), $swatches),
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

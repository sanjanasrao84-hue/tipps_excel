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

namespace theme_eadtraining\local;

/**
 * Colour themes for the Soft Modern Glass design: the one place their colours are defined.
 *
 * Every primary passes WCAG 4.5:1 with white text; every chart pair passes the colour-blind checks.
 * Ink (text) and status colours do not change between themes.
 *
 * @package   theme_eadtraining
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class palettes {

    /** @var string Config name of the selected colour theme. */
    const CONFIG = 'colortheme';

    /** @var string Theme used when nothing has been chosen yet. */
    const DEFAULT = 'clinical';

    /** @var string Theme marked as recommended: calm teal reads as trust and focus, which suits learning. */
    const RECOMMENDED = 'clinical';

    /**
     * All colour themes, in the order they are offered.
     *
     * Each theme is one 4-colour palette set ('swatches', shown as circles on the picker), mapped to:
     * primary (buttons, active items; the darkest colour so white text stays readable), deep / bright
     * (gradient ends), aqua (light accent), accent2 (second hue), highlight (tag pill text),
     * chart1 / chart2 (data), warm (donut remainder), glow1-3 (backdrop light), bgtop / bgbottom
     * (page canvas). Only colours differ between themes; the design is the same.
     *
     * @return array[]
     */
    public static function all(): array {
        return [
            'clinical' => [
                'swatches' => ['#0f7c80', '#2dd4bf', '#4f8cf7', '#a78bfa'],
                'primary' => '#0f7c80', 'deep' => '#0b5f66', 'bright' => '#14b8a6', 'aqua' => '#2dd4bf',
                'accent2' => '#4f8cf7', 'highlight' => '#5f4fb0',
                'chart1' => '#0d9488', 'chart2' => '#4f8cf7', 'warm' => '#e8892b',
                'glow1' => '#2dd4bf', 'glow2' => '#4f8cf7', 'glow3' => '#a78bfa',
                'bgtop' => '#eaf4f4', 'bgbottom' => '#ebeef6',
            ],
            'seaside' => [
                'swatches' => ['#f9ddb1', '#f88e86', '#034f70', '#0c82a0'],
                'primary' => '#034f70', 'deep' => '#023a54', 'bright' => '#0c82a0', 'aqua' => '#5bb8cf',
                'accent2' => '#f88e86', 'highlight' => '#a8433b',
                'chart1' => '#0c82a0', 'chart2' => '#f88e86', 'warm' => '#f88e86',
                'glow1' => '#5bb8cf', 'glow2' => '#f88e86', 'glow3' => '#f9ddb1',
                'bgtop' => '#eef5f7', 'bgbottom' => '#fbf1ea',
            ],
            'berry' => [
                'swatches' => ['#6a2c85', '#f78eb6', '#fdb45c', '#fde9b5'],
                'primary' => '#6a2c85', 'deep' => '#4e1f63', 'bright' => '#9b59b6', 'aqua' => '#c99ddb',
                'accent2' => '#f78eb6', 'highlight' => '#9c2f63',
                'chart1' => '#9b59b6', 'chart2' => '#e0902a', 'warm' => '#f78eb6',
                'glow1' => '#c99ddb', 'glow2' => '#f78eb6', 'glow3' => '#fde9b5',
                'bgtop' => '#f4eef8', 'bgbottom' => '#fdf3ea',
            ],
            'earth' => [
                'swatches' => ['#514541', '#0a9cb4', '#fdbf2d', '#ff6a13'],
                'primary' => '#0a7486', 'deep' => '#514541', 'bright' => '#0a9cb4', 'aqua' => '#5fd0e0',
                'accent2' => '#ff6a13', 'highlight' => '#514541',
                'chart1' => '#0a9cb4', 'chart2' => '#ff6a13', 'warm' => '#fdbf2d',
                'glow1' => '#5fd0e0', 'glow2' => '#fdbf2d', 'glow3' => '#ffb38a',
                'bgtop' => '#f1f4f3', 'bgbottom' => '#fbf4e6',
            ],
            'orchid' => [
                'swatches' => ['#6a2c91', '#9b2d7f', '#f67c8b', '#fca085'],
                'primary' => '#6a2c91', 'deep' => '#4c1d6b', 'bright' => '#9b2d7f', 'aqua' => '#c48ad8',
                'accent2' => '#f67c8b', 'highlight' => '#9b2d7f',
                'chart1' => '#9b2d7f', 'chart2' => '#ec6f5e', 'warm' => '#fca085',
                'glow1' => '#c48ad8', 'glow2' => '#f67c8b', 'glow3' => '#fca085',
                'bgtop' => '#f4eef9', 'bgbottom' => '#fdf0ee',
            ],
            'harvest' => [
                'swatches' => ['#ff5f3a', '#ffb352', '#72b52b', '#1e3289'],
                'primary' => '#1e3289', 'deep' => '#14205e', 'bright' => '#3550c0', 'aqua' => '#7d93e8',
                'accent2' => '#72b52b', 'highlight' => '#b8401f',
                'chart1' => '#ff5f3a', 'chart2' => '#3550c0', 'warm' => '#ffb352',
                'glow1' => '#ffb352', 'glow2' => '#a7d67c', 'glow3' => '#7d93e8',
                'bgtop' => '#f1f3fa', 'bgbottom' => '#fdf3e7',
            ],
            'coralcoast' => [
                'swatches' => ['#ff6567', '#034f6e', '#fdbe2d', '#efe3bd'],
                'primary' => '#034f6e', 'deep' => '#023a52', 'bright' => '#0b7aa0', 'aqua' => '#4fb3d1',
                'accent2' => '#ff6567', 'highlight' => '#b83b3d',
                'chart1' => '#0b7aa0', 'chart2' => '#ff6567', 'warm' => '#fdbe2d',
                'glow1' => '#ff6567', 'glow2' => '#fdbe2d', 'glow3' => '#efe3bd',
                'bgtop' => '#f0f5f6', 'bgbottom' => '#faf5e8',
            ],
            'candy' => [
                'swatches' => ['#ff8eb3', '#fce5c9', '#2e3592', '#9cd7fa'],
                'primary' => '#2e3592', 'deep' => '#1f2468', 'bright' => '#4a52c4', 'aqua' => '#9cd7fa',
                'accent2' => '#ff8eb3', 'highlight' => '#b4366a',
                'chart1' => '#4a52c4', 'chart2' => '#e5578e', 'warm' => '#9cd7fa',
                'glow1' => '#9cd7fa', 'glow2' => '#ff8eb3', 'glow3' => '#fce5c9',
                'bgtop' => '#eef3fc', 'bgbottom' => '#fdf0f3',
            ],
            'regatta' => [
                'swatches' => ['#1e3289', '#36b9be', '#fdb520', '#e95a43'],
                'primary' => '#1e3289', 'deep' => '#14205e', 'bright' => '#2f4bc0', 'aqua' => '#36b9be',
                'accent2' => '#e95a43', 'highlight' => '#b3372a',
                'chart1' => '#2f4bc0', 'chart2' => '#36b9be', 'warm' => '#fdb520',
                'glow1' => '#36b9be', 'glow2' => '#fdb520', 'glow3' => '#f29a8c',
                'bgtop' => '#eef1f9', 'bgbottom' => '#fcf3e6',
            ],
        ];
    }

    /**
     * The selected theme's key (falls back to the default if unknown).
     *
     * @return string
     */
    public static function active_key(): string {
        $key = (string) get_config('theme_eadtraining', self::CONFIG);
        return isset(self::all()[$key]) ? $key : self::DEFAULT;
    }

    /**
     * SCSS prepended before the theme's SCSS: every palette as a map, the active key, and $primary.
     *
     * The "Primary interface color" setting (theme_boost | brandcolor) keeps working: it is the
     * selected theme's main colour. Choosing a theme on colortheme.php fills that setting in.
     *
     * @return string
     */
    public static function scss(): string {
        $palettes = self::all();
        $active = self::active_key();
        $brand = (string) get_config('theme_boost', 'brandcolor');
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $brand)) {
            $palettes[$active]['primary'] = $brand;
        }

        $maps = [];
        foreach ($palettes as $key => $palette) {
            $pairs = [];
            foreach ($palette as $name => $value) {
                if (is_string($value)) {
                    $pairs[] = "{$name}: {$value}";
                }
            }
            $maps[] = "    \"{$key}\": (" . implode(', ', $pairs) . ")";
        }
        $primary = $palettes[$active]['primary'];

        return "\$g-palettes: (\n" . implode(",\n", $maps) . "\n);\n" .
            "\$g-palette-key: \"{$active}\";\n" .
            "\$primary: {$primary};\n";
    }
}

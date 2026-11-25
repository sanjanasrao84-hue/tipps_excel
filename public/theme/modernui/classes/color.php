<?php
namespace theme_modernui;

defined('MOODLE_INTERNAL') || die();

/**
 * Small colour helpers: validation, shades and automatic readable text colour.
 */
class color {
    public static function normalize(?string $hex, string $fallback): string {
        $hex = trim((string)$hex);
        if (preg_match('/^#?([0-9a-f]{3})$/i', $hex, $m)) {
            $h = $m[1];
            return '#' . strtoupper($h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2]);
        }
        if (preg_match('/^#?([0-9a-f]{6})$/i', $hex, $m)) {
            return '#' . strtoupper($m[1]);
        }
        return $fallback;
    }

    public static function rgb(string $hex): array {
        $n = hexdec(ltrim($hex, '#'));
        return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
    }

    /** Mix towards white (positive) or black (negative). */
    public static function shade(string $hex, float $pct): string {
        [$r, $g, $b] = self::rgb($hex);
        $t = $pct < 0 ? 0 : 255;
        $p = abs($pct);
        return sprintf('#%02X%02X%02X', round($r + ($t - $r) * $p), round($g + ($t - $g) * $p), round($b + ($t - $b) * $p));
    }

    public static function luminance(string $hex): float {
        $c = array_map(function ($v) {
            $v /= 255;
            return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }, self::rgb($hex));
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /** White or near-black, whichever has better contrast on this background. */
    public static function on(string $hex): string {
        $l = self::luminance($hex);
        return (1.05 / ($l + 0.05)) >= (($l + 0.05) / 0.0587) ? '#FFFFFF' : '#0F172A';
    }

    /** All the CSS custom properties that depend on the brand colour. */
    public static function accent_vars(string $hex): string {
        $lift = self::shade($hex, 0.55);
        return '--mu-primary:' . $hex . ';'
            . '--mu-primary-rgb:' . implode(',', self::rgb($hex)) . ';'
            . '--mu-primary-hover:' . self::shade($hex, -0.12) . ';'
            . '--mu-on-primary:' . self::on($hex) . ';'
            . '--mu-lift:' . $lift . ';'
            . '--mu-lift-rgb:' . implode(',', self::rgb($lift)) . ';'
            . '--mu-lift-hover:' . self::shade($lift, -0.12) . ';'
            . '--mu-on-lift:' . self::on($lift) . ';';
    }
}

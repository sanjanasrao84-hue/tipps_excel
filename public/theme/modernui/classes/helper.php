<?php
namespace theme_modernui;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves admin settings and per-user preferences into CSS variables and attributes.
 * Priority: user preference, then admin setting, then preset default.
 */
class helper {
    const FONTS = [
        'inter'   => ['Inter', 'Inter:wght@400;500;600;700'],
        'roboto'  => ['Roboto', 'Roboto:wght@400;500;700'],
        'poppins' => ['Poppins', 'Poppins:wght@400;500;600;700'],
        'system'  => [null, null],
    ];
    const SYSTEM_STACK = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    const RADIUS = ['sharp' => '2px', 'medium' => '8px', 'rounded' => '16px'];
    const SHADOW = [
        'none'   => 'none',
        'soft'   => '0 1px 3px rgba(15,23,42,.08), 0 1px 2px rgba(15,23,42,.06)',
        'strong' => '0 10px 25px rgba(15,23,42,.18)',
    ];

    /** Admin settings with defaults filled in. */
    public static function admin(?\theme_config $theme = null): array {
        $p = presets::get('indigo');
        $defaults = [
            'primarycolor'    => $p['primary'],
            'accentcolor'     => $p['accent'],
            'backgroundcolor' => $p['background'],
            'fontfamily'      => $p['font'],
            'customfont'      => '',
            'basefontsize'    => 16,
            'radius'          => $p['radius'],
            'shadow'          => 'soft',
            'contentwidth'    => 'boxed',
            'loginlayout'     => 'split',
            'darkmodeenabled' => 1,
            'defaultmode'     => 'light',
        ];
        $out = [];
        foreach ($defaults as $key => $default) {
            if ($theme && isset($theme->settings->$key)) {
                $val = $theme->settings->$key;
            } else {
                $val = get_config('theme_modernui', $key);
            }
            $out[$key] = ($val === false || $val === null || $val === '') ? $default : $val;
        }
        $out['primarycolor'] = color::normalize($out['primarycolor'], $p['primary']);
        $out['accentcolor'] = color::normalize($out['accentcolor'], $p['accent']);
        $out['backgroundcolor'] = color::normalize($out['backgroundcolor'], $p['background']);
        return $out;
    }

    public static function font_name(array $a): ?string {
        $custom = trim(preg_replace('/[^A-Za-z0-9 ]/', '', (string)$a['customfont']));
        if ($custom !== '') {
            return $custom;
        }
        return self::FONTS[$a['fontfamily']][0] ?? null;
    }

    public static function font_stack(array $a): string {
        $name = self::font_name($a);
        return $name ? '"' . $name . '", ' . self::SYSTEM_STACK : self::SYSTEM_STACK;
    }

    public static function font_url(array $a): ?string {
        $custom = trim(preg_replace('/[^A-Za-z0-9 ]/', '', (string)$a['customfont']));
        if ($custom !== '') {
            return 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $custom) . '&display=swap';
        }
        $spec = self::FONTS[$a['fontfamily']][1] ?? null;
        return $spec ? 'https://fonts.googleapis.com/css2?family=' . $spec . '&display=swap' : null;
    }

    /** CSS variables generated from the admin settings. */
    public static function css_variables(array $a): string {
        $bg = $a['backgroundcolor'];
        $radius = self::RADIUS[$a['radius']] ?? self::RADIUS['medium'];
        $shadow = self::SHADOW[$a['shadow']] ?? self::SHADOW['soft'];
        return color::accent_vars($a['primarycolor'])
            . '--mu-accent:' . $a['accentcolor'] . ';'
            . '--mu-bg:' . $bg . ';'
            . '--mu-bg-rgb:' . implode(',', color::rgb($bg)) . ';'
            . '--mu-font:' . self::font_stack($a) . ';'
            . '--mu-base-size:' . max(12, min(22, (int)$a['basefontsize'])) . 'px;'
            . '--mu-radius:' . $radius . ';'
            . '--mu-shadow:' . $shadow . ';';
    }

    /** Read one of this theme's user preferences, restricted to allowed values. */
    public static function user_pref(string $name, array $allowed = []): string {
        $val = (string)get_user_preferences('theme_modernui_' . $name, '');
        if ($allowed && !in_array($val, $allowed, true)) {
            return '';
        }
        return $val;
    }

    /** Everything the head script needs: effective user settings. */
    public static function effective(\theme_config $theme): array {
        $a = self::admin($theme);
        $darkok = !empty($a['darkmodeenabled']);
        $mode = self::user_pref('mode', ['light', 'dark', 'auto']);
        $default = in_array($a['defaultmode'], ['light', 'dark', 'auto'], true) ? $a['defaultmode'] : 'light';
        if (!$darkok) {
            $mode = 'light';
            $default = 'light';
        } else if ($mode === '') {
            $mode = $default;
        }
        $accent = self::user_pref('accent');
        $accent = preg_match('/^[0-9a-f]{6}$/i', $accent) ? '#' . strtoupper($accent) : '';
        return [
            'admin' => $a,
            'darkenabled' => $darkok,
            'defaults' => ['mode' => $default],
            'mode' => $mode,
            'accent' => $accent,
            'fontsize' => self::user_pref('fontsize', ['small', 'medium', 'large']) ?: 'medium',
            'density' => self::user_pref('density', ['comfortable', 'compact']) ?: 'comfortable',
            'motion' => self::user_pref('motion', ['1']) === '1',
            'contrast' => self::user_pref('contrast', ['1']) === '1',
        ];
    }

    /** HTML added to <head>: fonts, user colour override and the no-flash attribute script. */
    public static function head_html(\theme_config $theme): string {
        $e = self::effective($theme);
        $a = $e['admin'];
        $html = '';
        if ($url = self::font_url($a)) {
            $html .= '<link rel="preconnect" href="https://fonts.googleapis.com">'
                . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
                . '<link rel="stylesheet" href="' . s($url) . '">';
        }
        if ($e['accent'] !== '') {
            $html .= '<style id="mu-user-accent">:root:root{' . color::accent_vars($e['accent']) . '}</style>';
        }
        $attrs = [
            'data-mu-mode' => $e['mode'],
            'data-mu-fontsize' => $e['fontsize'],
            'data-mu-density' => $e['density'],
            'data-mu-motion' => $e['motion'] ? 'reduce' : 'normal',
            'data-mu-contrast' => $e['contrast'] ? 'high' : 'normal',
            'data-mu-width' => $a['contentwidth'] === 'full' ? 'full' : 'boxed',
        ];
        $json = json_encode($attrs);
        $html .= '<script>(function(){var d=document.documentElement,a=' . $json . ';'
            . 'for(var k in a){d.setAttribute(k,a[k]);}'
            . 'var m=a["data-mu-mode"],'
            . 'dark=m==="dark"||(m==="auto"&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches);'
            . 'd.setAttribute("data-bs-theme",dark?"dark":"light");})();</script>';
        return $html;
    }
}

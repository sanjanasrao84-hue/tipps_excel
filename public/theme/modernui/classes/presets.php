<?php
namespace theme_modernui;

defined('MOODLE_INTERNAL') || die();

/**
 * The ready-made design presets. Admin can pick one and still change any value.
 */
class presets {
    public static function all(): array {
        return [
            'indigo'  => ['primary' => '#4F46E5', 'accent' => '#06B6D4', 'background' => '#F8FAFC', 'font' => 'inter',   'radius' => 'medium'],
            'emerald' => ['primary' => '#047857', 'accent' => '#F59E0B', 'background' => '#F6FBF9', 'font' => 'roboto',  'radius' => 'medium'],
            'sunset'  => ['primary' => '#C2410C', 'accent' => '#DB2777', 'background' => '#FFFBF7', 'font' => 'poppins', 'radius' => 'rounded'],
            'mono'    => ['primary' => '#111827', 'accent' => '#6B7280', 'background' => '#FFFFFF', 'font' => 'system',  'radius' => 'sharp'],
        ];
    }

    public static function get(string $key): array {
        $all = self::all();
        return $all[$key] ?? $all['indigo'];
    }
}

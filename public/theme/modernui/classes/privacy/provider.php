<?php
namespace theme_modernui\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {

    const PREFS = ['mode', 'accent', 'fontsize', 'density', 'motion', 'contrast'];

    public static function get_metadata(collection $collection): collection {
        foreach (self::PREFS as $p) {
            $collection->add_user_preference('theme_modernui_' . $p, 'privacy:metadata:preference:' . $p);
        }
        return $collection;
    }

    public static function export_user_preferences(int $userid) {
        foreach (self::PREFS as $p) {
            $value = get_user_preferences('theme_modernui_' . $p, null, $userid);
            if ($value !== null) {
                writer::export_user_preference('theme_modernui', 'theme_modernui_' . $p, $value,
                    get_string('privacy:metadata:preference:' . $p, 'theme_modernui'));
            }
        }
    }
}

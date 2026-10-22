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
 * restore_theme_eadtraining_plugin.class.php
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->dirroot}/backup/moodle2/restore_theme_plugin.class.php");

/**
 * Class restore_theme_eadtraining_plugin
 */
class restore_theme_eadtraining_plugin extends restore_theme_plugin {

    /**
     * Defines the course-level structure data.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element(
                "eadtraining_courseopts",
                $this->get_pathfor("/eadtraining_courseopts")
            ),
            new restore_path_element(
                "eadtraining_moduleopt",
                $this->get_pathfor("/eadtraining_moduleopts/eadtraining_moduleopt")
            ),
            // Keep restoring backups generated before the element names were namespaced.
            new restore_path_element(
                "eadtraining_legacy_courseopts",
                $this->get_pathfor("/courseopts")
            ),
            new restore_path_element(
                "eadtraining_legacy_moduleopt",
                $this->get_pathfor("/moduleopts/moduleopt")
            ),
        ];
    }

    /**
     * Process
     *
     * @param array $data
     * @return void
     */
    public function process_eadtraining_courseopts($data) {
        $data = (object) $data;
        $courseid = $this->task->get_courseid();

        if ($data->course_summary_banner !== null && $data->course_summary_banner !== "") {
            set_config("course_summary_banner_{$courseid}", $data->course_summary_banner, "theme_eadtraining");
        }

        if ($data->course_sections_icons !== null && $data->course_sections_icons !== "") {
            set_config("course_sections_icons_{$courseid}", $data->course_sections_icons, "theme_eadtraining");
        }

        if ($data->override_course_primarycolor !== null && $data->override_course_primarycolor !== "") {
            set_config("override_course_primarycolor_{$courseid}", $data->override_course_primarycolor, "theme_eadtraining");
        }

        if ($data->override_course_secondarycolor !== null && $data->override_course_secondarycolor !== "") {
            set_config("override_course_secondarycolor_{$courseid}", $data->override_course_secondarycolor, "theme_eadtraining");
        }

        // Keep filename reference to build URL.
        if (!empty($data->banner_course_file)) {
            set_config("banner_course_file_{$courseid}", $data->banner_course_file, "theme_eadtraining");
        }
    }

    /**
     * Restore course settings from backups created with the previous XML names.
     *
     * @param array $data
     * @return void
     */
    public function process_eadtraining_legacy_courseopts($data) {
        $this->process_eadtraining_courseopts($data);
    }

    /**
     * Execute
     *
     * @return void
     */
    protected function after_execute_course() {
        $this->add_related_files("theme_eadtraining", "banner_course_file", null);

        $this->add_related_files(
            "theme_eadtraining",
            "theme_eadtraining_customimage",
            "theme_eadtraining_custommodule"
        );

        $this->add_related_files(
            "theme_eadtraining",
            "theme_eadtraining_customicon",
            "theme_eadtraining_custommodule"
        );

        cache::make("theme_eadtraining", "css_cache")->purge();
    }

    /**
     * Restore module visual customizations.
     *
     * @param array $data
     * @return void
     */
    public function process_eadtraining_moduleopt($data) {
        $data = (object) $data;

        if (empty($data->cmid)) {
            return;
        }

        $newcmid = $this->get_mappingid("course_module", $data->cmid);
        if (!$newcmid) {
            return;
        }

        if (!empty($data->customimage)) {
            set_config(
                "theme_eadtraining_customimage_{$newcmid}",
                $data->customimage,
                "theme_eadtraining"
            );
        }

        if (!empty($data->customicon)) {
            set_config(
                "theme_eadtraining_customicon_{$newcmid}",
                $data->customicon,
                "theme_eadtraining"
            );
        }

        if (!empty($data->customcolor)) {
            set_config(
                "theme_eadtraining_customcolor_{$newcmid}",
                $data->customcolor,
                "theme_eadtraining"
            );
        }

        // Register old cmid -> new cmid and old module context for file restore.
        $this->set_mapping(
            "theme_eadtraining_custommodule",
            $data->cmid,
            $newcmid,
            true,
            $data->contextid
        );
    }

    /**
     * Restore module settings from backups created with the previous XML names.
     *
     * @param array $data
     * @return void
     */
    public function process_eadtraining_legacy_moduleopt($data) {
        $this->process_eadtraining_moduleopt($data);
    }
}

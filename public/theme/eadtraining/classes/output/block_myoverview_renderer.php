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

namespace theme_eadtraining\output;

use block_myoverview\output\main;

/**
 * My courses (course overview block) renderer.
 *
 * The default sorting ("title" preference) lists courses in the course order set in course management, so
 * My courses shows them in the same order as the home page and the course catalogue. The sort menu
 * (block_myoverview/nav-sort-selector in this theme) labels it "Sort by course order".
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_myoverview_renderer extends \block_myoverview\output\renderer {

    /**
     * Return the main content for the block overview.
     *
     * @param main $main The main renderable
     * @return string HTML string
     */
    public function render_main(main $main) {
        global $USER;

        if (!count(enrol_get_all_users_courses($USER->id, true))) {
            return parent::render_main($main);
        }

        $data = $main->export_for_template($this);
        if ($data['sort'] === 'fullname') {
            $data['sort'] = 'sortorder';
        }
        return $this->render_from_template('block_myoverview/main', $data);
    }
}

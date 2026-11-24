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

/**
 * Font Awesome icon system of the eadtraining and eadflix themes.
 *
 * Mandatory fields are marked with a red asterisk (*) instead of core's exclamation circle. Plugins cannot change a core
 * icon through get_fontawesome_icon_map() (their maps are only added), so the core map is changed here.
 *
 * @package   theme_eadtraining
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icon_system_fontawesome extends \core\output\icon_system_fontawesome {
    /**
     * The core icon map, with the "required" icon changed.
     *
     * @return array
     */
    public function get_core_icon_map() {
        $map = parent::get_core_icon_map();
        $map['core:req'] = 'fa-asterisk text-danger';
        return $map;
    }
}

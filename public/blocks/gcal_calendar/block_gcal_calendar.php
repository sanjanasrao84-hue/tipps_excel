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


use local_gcal\output\calendar;

/**
 * The full calendar (month, week, day and agenda views with filters) on the Dashboard.
 *
 * @package    block_gcal_calendar
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_gcal_calendar extends block_base {

    /**
     * Initialises the block.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_gcal_calendar');
    }

    /**
     * Show the short title on pages.
     *
     * @return void
     */
    public function specialization() {
        $this->title = get_string('blocktitle', 'block_gcal_calendar');
    }

    /**
     * Where the block can be added: the Dashboard (the calendar's JavaScript runs once per page).
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true];
    }

    /**
     * One calendar per page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Content.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }
        // The page header may already be printed, so FullCalendar loads in the footer (the module waits for it).
        $this->content->text = calendar::render($this->page, 0, '', false);
        return $this->content;
    }
}

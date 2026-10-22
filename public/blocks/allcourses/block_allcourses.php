<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * All courses block: lists every visible course on the site, whether or not the user is enrolled.
 *
 * @package    block_allcourses
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_allcourses extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_allcourses');
    }

    public function applicable_formats() {
        return ['my' => true, 'site-index' => true];
    }

    public function has_config() {
        return false;
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // Same course list as the site home page: image, summary and enrolment (payment) icons.
        $renderer = $this->page->get_renderer('core', 'course');
        $this->content->text = $renderer->frontpage_available_courses();

        return $this->content;
    }
}

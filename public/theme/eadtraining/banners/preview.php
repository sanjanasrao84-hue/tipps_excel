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
 * Preview a homepage banner block or a custom banner.
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_eadtraining\section_manager;

require_once("../../../config.php");
require_once("{$CFG->libdir}/adminlib.php");

global $CFG, $OUTPUT, $PAGE;

admin_externalpage_setup("theme_eadtraining_banners");

$pageid = optional_param("pageid", 0, PARAM_INT);
$bannerid = optional_param("bannerid", 0, PARAM_INT);

$PAGE->set_url(new moodle_url("/theme/eadtraining/banners/preview.php", ["pageid" => $pageid, "bannerid" => $bannerid]));

if ($pageid) {
    $page = section_manager::get_home_section($pageid);
    $name = format_string($page->title);
    $html = section_manager::render_page_preview($page);
} else {
    $banner = section_manager::get_banner($bannerid);
    $name = format_string($banner->name);
    $html = section_manager::custom_banner_css() . section_manager::render_banner($banner, section_manager::get_image_url($banner->id));
}

$title = get_string("banners_preview_title", "theme_eadtraining", $name);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title);

echo $OUTPUT->header();
echo "<div class=\"theme-eadtraining-banner-preview mb-4\">{$html}</div>";
echo html_writer::link(new moodle_url("/theme/eadtraining/banners/index.php"), get_string("back"), ["class" => "btn btn-secondary"]);
echo $OUTPUT->footer();

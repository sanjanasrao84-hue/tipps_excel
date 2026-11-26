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
 * Colour theme picker: preview any colour theme on the site, then apply it to the whole LMS.
 *
 * @package   theme_eadtraining
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_eadtraining\local\palettes;

require_once(__DIR__ . "/../../config.php");
require_once("{$CFG->libdir}/adminlib.php");

admin_externalpage_setup("theme_eadtraining_colortheme");

$apply = optional_param("colortheme", "", PARAM_ALPHANUMEXT);
$all = palettes::all();

if ($apply !== "" && confirm_sesskey()) {
    if (!isset($all[$apply])) {
        throw new moodle_exception("invalidparameter", "debug");
    }
    set_config(palettes::CONFIG, $apply, "theme_eadtraining");
    // Keep the theme's "Primary interface color" setting in step with the chosen colours.
    set_config("brandcolor", $all[$apply]["primary"], "theme_boost");
    // The stylesheet is compiled with the theme's colours, so rebuild it for everyone.
    theme_reset_all_caches();
    redirect(
        new moodle_url("/theme/eadtraining/colortheme.php"),
        get_string("colortheme_applied", "theme_eadtraining", get_string("colortheme_{$apply}", "theme_eadtraining")),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$active = palettes::active_key();
$themes = [];
foreach ($all as $key => $palette) {
    $themes[] = [
        "key" => $key,
        "name" => get_string("colortheme_{$key}", "theme_eadtraining"),
        "active" => $key === $active,
        "recommended" => $key === palettes::RECOMMENDED,
        "swatches" => array_map(fn($color) => ["color" => $color], $palette["swatches"]),
    ];
}

$PAGE->set_url(new moodle_url("/theme/eadtraining/colortheme.php"));
$PAGE->set_title(get_string("colortheme", "theme_eadtraining"));
$PAGE->set_heading(get_string("colortheme", "theme_eadtraining"));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template("theme_eadtraining/colortheme", [
    "themes" => $themes,
    "action" => (new moodle_url("/theme/eadtraining/colortheme.php"))->out(false),
    "sesskey" => sesskey(),
]);
echo $OUTPUT->footer();

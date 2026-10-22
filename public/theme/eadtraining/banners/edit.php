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
 * Create or edit a custom banner.
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_eadtraining\section_manager;
use theme_eadtraining\form\custom_banner_form;

require_once("../../../config.php");
require_once("{$CFG->libdir}/adminlib.php");
require_once("{$CFG->libdir}/filelib.php");

global $CFG, $OUTPUT, $PAGE;

admin_externalpage_setup("theme_eadtraining_banners");

$id = optional_param("id", 0, PARAM_INT);
$returnurl = optional_param("returnurl", "", PARAM_LOCALURL);

$pageurl = new moodle_url("/theme/eadtraining/banners/edit.php", ["id" => $id]);
$PAGE->set_url($pageurl);
$indexurl = new moodle_url("/theme/eadtraining/banners/index.php");
$backurl = $returnurl ? new moodle_url($returnurl) : $indexurl;

$context = context_system::instance();
$banner = $id ? section_manager::get_banner($id) : section_manager::get_banner_defaults();

$title = get_string($id ? "banners_edit" : "banners_create", "theme_eadtraining");
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title);

$data = clone($banner);
$data->returnurl = $returnurl;
$data->description_editor = ["text" => $banner->description, "format" => $banner->descriptionformat];
$data->showonhome = $id ? (int) (bool) section_manager::get_banner_pages($id) : 1;

$draftitemid = file_get_submitted_draft_itemid("backgroundimage");
file_prepare_draft_area($draftitemid, $context->id, "theme_eadtraining", section_manager::FILEAREA, $id ?: null,
    section_manager::file_options());
$data->backgroundimage = $draftitemid;

$form = new custom_banner_form($pageurl);
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($backurl);
}

$preview = "";
if ($form->no_submit_button_pressed()) {
    $submitted = $form->get_submitted_data();
    if ($submitted) {
        $submitted->description = $submitted->description_editor["text"] ?? "";
        $submitted->descriptionformat = $submitted->description_editor["format"] ?? FORMAT_HTML;
        $preview = section_manager::render_banner($submitted, section_manager::get_draft_image_url((int) $submitted->backgroundimage));
    }
} else if ($formdata = $form->get_data()) {
    $formdata->id = $id;
    $formdata->description = $formdata->description_editor["text"];
    $formdata->descriptionformat = $formdata->description_editor["format"];
    $id = section_manager::save_banner($formdata);

    file_save_draft_area_files($formdata->backgroundimage, $context->id, "theme_eadtraining", section_manager::FILEAREA, $id,
        section_manager::file_options());

    $onhome = (bool) section_manager::get_banner_pages($id);
    if ($formdata->showonhome && !$onhome) {
        section_manager::add_banner_to_home($id);
    } else if (!$formdata->showonhome && $onhome) {
        foreach (section_manager::get_banner_pages($id) as $page) {
            section_manager::remove_from_home($page->id);
        }
    }
    section_manager::purge_caches();

    redirect($backurl, get_string("banners_saved", "theme_eadtraining"), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($id && !$preview) {
    // Show the saved banner while editing it.
    $preview = section_manager::render_banner($banner, section_manager::get_image_url($id));
}

$PAGE->requires->js_call_amd("theme_eadtraining/settings", "minicolors", ["id_textcolor"]);
$PAGE->requires->js_call_amd("theme_eadtraining/settings", "minicolors", ["id_overlaycolor"]);

echo $OUTPUT->header();
if ($preview) {
    echo $OUTPUT->heading(get_string("preview"), 3);
    if ($form->no_submit_button_pressed()) {
        echo $OUTPUT->notification(get_string("banners_preview_unsaved", "theme_eadtraining"), "info");
    }
    echo section_manager::custom_banner_css();
    echo "<div class=\"theme-eadtraining-banner-preview mb-4\">{$preview}</div>";
}
$form->display();
echo $OUTPUT->footer();

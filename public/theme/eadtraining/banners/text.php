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
 * Customise a homepage section: texts, links, images, options and items, as supported by its model.
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_eadtraining\form\section_form;
use theme_eadtraining\section_manager;

require_once("../../../config.php");
require_once("{$CFG->libdir}/adminlib.php");

global $CFG, $OUTPUT, $PAGE;

admin_externalpage_setup("theme_eadtraining_banners");

$pageid = required_param("pageid", PARAM_INT);
$returnurl = optional_param("returnurl", "", PARAM_LOCALURL);

$page = section_manager::get_home_section($pageid);

// Custom banners have their own form.
$bannerid = section_manager::get_page_bannerid($page);
if ($bannerid) {
    redirect(new moodle_url("/theme/eadtraining/banners/edit.php", ["id" => $bannerid, "returnurl" => $returnurl]));
}

$pageurl = new moodle_url("/theme/eadtraining/banners/text.php", ["pageid" => $page->id]);
$PAGE->set_url($pageurl);
$backurl = $returnurl ? new moodle_url($returnurl) : new moodle_url("/theme/eadtraining/banners/index.php");
$editorurl = new moodle_url("/theme/eadtraining/_editor/editor.php", ["dataid" => $page->id]);

$title = get_string("sections_edit_title", "theme_eadtraining", format_string($page->title));
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->navbar->add($title);

$fields = section_manager::get_section_fields($page);

// New empty items requested with "Add item"; a section without items starts with one.
$addrows = optional_param("addrows", 0, PARAM_INT);
if (optional_param("additem", "", PARAM_RAW) !== "") {
    $addrows++;
}
if ($fields["rowdefs"] && !$fields["rows"] && !$addrows) {
    $addrows = 1;
}
$rowcount = count($fields["rows"]) + $addrows;

$data = ["pageid" => $page->id, "returnurl" => $returnurl];
foreach (["texts", "links", "block"] as $group) {
    foreach ($fields[$group] as $field) {
        $data[$field["name"]] = $field["value"];
    }
}
foreach ($fields["rows"] as $index => $row) {
    foreach ($row as $key => $value) {
        $data["row_{$index}_{$key}"] = $value;
    }
    $data["row_{$index}_order"] = $index + 1;
}
foreach ($fields["images"] as $field) {
    $draftitemid = file_get_submitted_draft_itemid($field["name"]);
    file_prepare_draft_area($draftitemid, null, null, null, null, section_manager::image_options());
    $data[$field["name"]] = $draftitemid;
}

$form = new section_form($pageurl, ["fields" => $fields, "rowcount" => $rowcount, "addrows" => $addrows]);
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($backurl);
}

$previewpage = $page;
$notice = "";
if ($form->no_submit_button_pressed()) {
    $submitted = $form->get_submitted_data();
    if ($submitted && optional_param("previewbutton", "", PARAM_RAW) !== "") {
        $images = [];
        foreach ($fields["images"] as $field) {
            if (!empty($submitted->{$field["name"] . "_reset"})) {
                $images[$field["index"]] = "";
            } else if ($url = section_manager::get_draft_image_url((int) $submitted->{$field["name"]})) {
                $images[$field["index"]] = $url->out(false);
            }
        }
        [$previewpage, $missing] = section_manager::apply_section_values($page, (array) $submitted, !empty($submitted->rebuild),
            $images);
        $notice = $OUTPUT->notification(get_string("banners_preview_unsaved", "theme_eadtraining"), "info");
        if ($missing) {
            $notice .= $OUTPUT->notification(
                get_string("banners_textmissing", "theme_eadtraining", implode(", ", $missing)), "warning");
        }
    }
} else if ($formdata = $form->get_data()) {
    $images = [];
    $resets = [];
    foreach ($fields["images"] as $field) {
        if (!empty($formdata->{$field["name"] . "_reset"})) {
            $images[$field["index"]] = "";
            $resets[] = $field["index"];
        } else if ($url = section_manager::save_section_image($page->id, $field["index"], (int) $formdata->{$field["name"]})) {
            $images[$field["index"]] = $url;
        }
    }

    [$newpage, $missing] = section_manager::apply_section_values($page, (array) $formdata, !empty($formdata->rebuild), $images);
    section_manager::save_page($newpage);
    foreach ($resets as $index) {
        section_manager::delete_section_image($page->id, $index);
    }

    if ($missing) {
        redirect($pageurl, get_string("banners_textmissing", "theme_eadtraining", implode(", ", $missing)), null,
            \core\output\notification::NOTIFY_WARNING);
    }
    redirect($backurl, get_string("sections_saved", "theme_eadtraining"), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string("preview"), 3);
echo $notice;
if (section_manager::is_hidden($page)) {
    echo $OUTPUT->notification(get_string("sections_hidden_notice", "theme_eadtraining"), "warning");
}
echo "<div class=\"theme-eadtraining-banner-preview mb-4\">" . section_manager::render_page_preview($previewpage) . "</div>";

if (!section_manager::has_settings($fields)) {
    echo $OUTPUT->notification(get_string("banners_notexts", "theme_eadtraining"), "info");
    echo html_writer::div(
        html_writer::link($editorurl, get_string("banners_visualeditor", "theme_eadtraining"), ["class" => "btn btn-primary me-2"]) .
        html_writer::link($backurl, get_string("back"), ["class" => "btn btn-secondary"])
    );
} else {
    echo html_writer::tag("p", get_string("sections_edit_desc", "theme_eadtraining", $editorurl->out(false)));
    $form->display();
}

echo $OUTPUT->footer();

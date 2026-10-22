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
 * Homepage sections manager: sections on the homepage, custom banners and the section library.
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_eadtraining\section_manager;

require_once("../../../config.php");
require_once("{$CFG->libdir}/adminlib.php");

global $CFG, $DB, $OUTPUT, $PAGE;

admin_externalpage_setup("theme_eadtraining_banners");

$action = optional_param("action", "", PARAM_ALPHA);
$pageid = optional_param("pageid", 0, PARAM_INT);
$bannerid = optional_param("id", 0, PARAM_INT);
$confirm = optional_param("confirm", 0, PARAM_BOOL);
$returnurl = optional_param("returnurl", "", PARAM_LOCALURL);

$baseurl = new moodle_url("/theme/eadtraining/banners/index.php");
$PAGE->set_url($baseurl);
$backurl = $returnurl ? new moodle_url($returnurl) : $baseurl;
$success = \core\output\notification::NOTIFY_SUCCESS;

if ($action) {
    require_sesskey();

    switch ($action) {
        case "addtemplate":
            section_manager::add_template_to_home(required_param("template", PARAM_ALPHANUMEXT));
            redirect($backurl, get_string("sections_added", "theme_eadtraining"), null, $success);
            break;

        case "addbanner":
            section_manager::add_banner_to_home($bannerid);
            redirect($backurl, get_string("sections_added", "theme_eadtraining"), null, $success);
            break;

        case "up":
        case "down":
        case "top":
            section_manager::move($pageid, $action);
            redirect($backurl);
            break;

        case "hide":
        case "show":
            section_manager::set_hidden($pageid, $action == "hide");
            $message = $action == "hide" ? "sections_hidden" : "sections_shown";
            redirect($backurl, get_string($message, "theme_eadtraining"), null, $success);
            break;

        case "remove":
            $page = section_manager::get_home_section($pageid);
            if ($confirm) {
                section_manager::remove_from_home($page->id);
                redirect($backurl, get_string("sections_removed", "theme_eadtraining"), null, $success);
            }
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string("sections_remove_confirm", "theme_eadtraining", format_string($page->title)),
                new moodle_url($baseurl, ["action" => "remove", "pageid" => $page->id, "confirm" => 1, "sesskey" => sesskey(),
                    "returnurl" => $returnurl]),
                $backurl
            );
            echo $OUTPUT->footer();
            die;

        case "delete":
            $banner = section_manager::get_banner($bannerid);
            if ($confirm) {
                section_manager::delete_banner($banner->id);
                redirect($baseurl, get_string("banners_deleted", "theme_eadtraining"), null, $success);
            }
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string("banners_delete_confirm", "theme_eadtraining", format_string($banner->name)),
                new moodle_url($baseurl, ["action" => "delete", "id" => $banner->id, "confirm" => 1, "sesskey" => sesskey()]),
                $baseurl
            );
            echo $OUTPUT->footer();
            die;

        default:
            throw new moodle_exception("invalidaction");
    }
}

$languages = get_string_manager()->get_list_of_translations();
$actionurl = function (array $params) use ($baseurl) {
    return (new moodle_url($baseurl, $params + ["sesskey" => sesskey()]))->out(false);
};

// Sections currently on the homepage.
$homepages = section_manager::get_home_pages();
$sections = [];
$custominhome = [];
foreach ($homepages as $page) {
    $info = section_manager::get_template_info($page->template);
    if (!$info) {
        continue;
    }
    $customid = section_manager::get_page_bannerid($page);
    $custominhome[$customid] = true;
    $hidden = section_manager::is_hidden($page);
    $params = ["pageid" => $page->id];

    $sections[] = [
        "title" => format_string($page->title),
        "category" => section_manager::category_name($info->category ?? ""),
        "iscustom" => (bool) $customid,
        "hidden" => $hidden,
        "language" => $page->lang == "all" ? get_string("all") : ($languages[$page->lang] ?? $page->lang),
        "position" => $page->position,
        "total" => count($homepages),
        "isfirst" => $page->position == 1,
        "islast" => $page->position == count($homepages),
        "editurl" => $customid
            ? (new moodle_url("/theme/eadtraining/banners/edit.php", ["id" => $customid]))->out(false)
            : (new moodle_url("/theme/eadtraining/banners/text.php", $params))->out(false),
        "visualeditorurl" => $customid ? false
            : (new moodle_url("/theme/eadtraining/_editor/editor.php", ["dataid" => $page->id]))->out(false),
        "previewurl" => (new moodle_url("/theme/eadtraining/banners/preview.php", $params))->out(false),
        "upurl" => $actionurl(["action" => "up", "pageid" => $page->id]),
        "downurl" => $actionurl(["action" => "down", "pageid" => $page->id]),
        "topurl" => $actionurl(["action" => "top", "pageid" => $page->id]),
        "visibilityurl" => $actionurl(["action" => $hidden ? "show" : "hide", "pageid" => $page->id]),
        "removeurl" => $actionurl(["action" => "remove", "pageid" => $page->id]),
    ];
}

// Custom banners.
$custombanners = [];
foreach (section_manager::get_banners() as $banner) {
    $imageurl = section_manager::get_image_url($banner->id);
    $custombanners[] = [
        "name" => format_string($banner->name),
        "title" => format_string($banner->title),
        "imageurl" => $imageurl ? $imageurl->out(false) : false,
        "onhome" => !empty($custominhome[$banner->id]),
        "timemodified" => userdate($banner->timemodified, get_string("strftimedatetimeshort", "langconfig")),
        "editurl" => (new moodle_url("/theme/eadtraining/banners/edit.php", ["id" => $banner->id]))->out(false),
        "previewurl" => (new moodle_url("/theme/eadtraining/banners/preview.php", ["bannerid" => $banner->id]))->out(false),
        "addurl" => $actionurl(["action" => "addbanner", "id" => $banner->id]),
        "deleteurl" => $actionurl(["action" => "delete", "id" => $banner->id]),
    ];
}

// Section library.
$library = [];
foreach (section_manager::get_section_templates() as $group) {
    $templates = [];
    foreach ($group["templates"] as $template) {
        $templates[] = [
            "title" => $template->title,
            "image" => $template->image,
            "addurl" => $actionurl(["action" => "addtemplate", "template" => $template->template]),
        ];
    }
    $library[] = [
        "name" => $group["name"],
        "count" => count($templates),
        "templates" => $templates,
        "expanded" => $group["category"] == "banner",
    ];
}

$templatecontext = [
    "homemodeoff" => !get_config("theme_eadtraining", "homemode"),
    "quickstarturl" => (new moodle_url("/theme/eadtraining/quickstart/index.php"))->out(false),
    "homeurl" => (new moodle_url("/", ["redirect" => 0]))->out(false),
    "createurl" => (new moodle_url("/theme/eadtraining/banners/edit.php"))->out(false),
    "sections" => $sections,
    "hassections" => (bool) $sections,
    "custombanners" => $custombanners,
    "hascustombanners" => (bool) $custombanners,
    "library" => $library,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template("theme_eadtraining/banner_manager", $templatecontext);
echo $OUTPUT->footer();

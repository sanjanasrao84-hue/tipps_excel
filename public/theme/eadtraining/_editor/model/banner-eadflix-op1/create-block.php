<?php

use core_course\external\course_summary_exporter;

/**
 * Function banner_eadflix_op1_createblocks
 *
 * @param $page
 * @return string
 * @throws \dml_exception
 */
function banner_eadflix_op1_createblocks($page) {
    global $DB, $OUTPUT;

    $page->info = json_decode($page->info);

    $blocks = "";
    if (isset($page->info->savedata)) {
        foreach ($page->info->savedata as $data) {
            $course = $DB->get_record("course", ["id" => $data->courseid]);
            if (!$course) {
                continue;
            }

            $course = new core_course_list_element($course);

            $courseimage = course_summary_exporter::get_course_image($course);
            if (!$courseimage) {
                $courseimage = $OUTPUT->get_default_image_for_courseid($course->id);
            }

            $courseinfo = theme_eadtraining_get_editor_course_link($course);
            $title = !empty($data->title) ? s($data->title) : $course->fullname;
            $subtitle = !empty($data->subtitle) ? "<div class=\"course-subtitle\">" . s($data->subtitle) . "</div>" : "";
            $buttontext = !empty($data->buttontext) ? s($data->buttontext) : $courseinfo->access;
            $blocks .= "
                <div class=\"course-banner-item\">
                    <div class=\"course-bg-banner\">
                        <div style=\"background-image:url('{$courseimage}');\" class=\"course-bg-images\">
                        </div>
                        <div class=\"video-bg-player\"
                             data-trailer=\"https://www.youtube.com/watch?v={$data->youtubeid}\">
                        </div>
                        <div class=\"course-bg-overlay\"></div>
                    </div>
                    <div class=\"course-banner-content\">
                        <h3 class=\"course-title\">
                            <a href=\"{$courseinfo->link}\">{$title}</a>
                        </h3>
                        {$subtitle}
                        <div class=\"course-text-description\">
                            {$data->description}
                        </div>
                        <a class=\"btn btn-access\" href=\"{$courseinfo->link}\">{$buttontext}</a>
                    </div>
                </div>\n";
        }
    }

    return "<div class=\"owl-carousel owl-course-banner\">{$blocks}</div>";
}

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
 * A live class: when it is, and the button to join it on Zoom.
 *
 * @package    mod_zoomlink
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($id, 'zoomlink');
$zoomlink = $DB->get_record('zoomlink', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/zoomlink:view', $context);

zoomlink_view($zoomlink, $course, $cm, $context);

$PAGE->set_url('/mod/zoomlink/view.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . $zoomlink->name);
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->set_description('');

[$status, $canjoin, $opens] = zoomlink_get_status($zoomlink);
$dateformat = get_string('strftimedaydatetime', 'langconfig');

echo $OUTPUT->header();

echo html_writer::start_div('card mb-3');
echo html_writer::start_div('card-body');

if ($zoomlink->starttime) {
    $badges = ['upcoming' => 'bg-info', 'soon' => 'bg-info', 'live' => 'bg-success', 'ended' => 'bg-secondary'];
    $label = $status === 'live' ? get_string('live', 'zoomlink') :
        ($status === 'ended' ? get_string('ended', 'zoomlink') : get_string('upcoming', 'zoomlink'));
    echo html_writer::tag('p',
        html_writer::tag('i', '', ['class' => 'fa-regular fa-calendar me-2', 'aria-hidden' => 'true']) .
        html_writer::tag('strong', s(get_string('whenwhere', 'zoomlink', [
            'start' => userdate($zoomlink->starttime, $dateformat), 'duration' => $zoomlink->duration]))) .
        html_writer::span($label, 'badge ' . $badges[$status] . ' ms-2'),
        ['class' => 'fs-5']);
} else {
    echo html_writer::tag('p', get_string('nostart', 'zoomlink'), ['class' => 'text-muted']);
}

echo format_module_intro('zoomlink', $zoomlink, $cm->id);

if ($canjoin) {
    echo html_writer::link($zoomlink->joinurl,
        html_writer::tag('i', '', ['class' => 'fa-solid fa-video me-2', 'aria-hidden' => 'true']) . get_string('join', 'zoomlink'),
        ['class' => 'btn btn-' . ($status === 'ended' ? 'secondary' : 'primary') . ' btn-lg mt-2',
            'target' => '_blank', 'rel' => 'noopener noreferrer']);
    if ($zoomlink->passcode !== '' && $zoomlink->passcode !== null) {
        echo html_writer::div(get_string('passcode', 'zoomlink') . ': ' .
            html_writer::tag('code', s($zoomlink->passcode), ['class' => 'user-select-all fs-6']), 'mt-2');
    }
} else {
    echo $OUTPUT->notification(get_string('joinopensat', 'zoomlink', userdate($opens, $dateformat)),
        \core\output\notification::NOTIFY_INFO, false);
}

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();

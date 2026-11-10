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
 * Install: puts the calendar on the default Dashboard and on the Dashboards users have already customised, just
 * before the Timeline.
 *
 * @package    block_gcal_calendar
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the block to every Dashboard that does not have it.
 *
 * @return bool
 */
function xmldb_block_gcal_calendar_install() {
    global $DB;

    $pages = $DB->get_records_select('my_pages', 'name = ?', ['__default'], '', 'id, userid');
    $now = time();
    foreach ($pages as $page) {
        if ($DB->record_exists('block_instances', ['blockname' => 'gcal_calendar', 'pagetypepattern' => 'my-index',
                'subpagepattern' => (string) $page->id])) {
            continue;
        }
        if ($page->userid) {
            if (!$DB->record_exists('user', ['id' => $page->userid, 'deleted' => 0])) {
                continue;
            }
            $parentcontext = context_user::instance($page->userid);
        } else {
            $parentcontext = context_system::instance();
        }
        $id = $DB->insert_record('block_instances', (object) [
            'blockname' => 'gcal_calendar',
            'parentcontextid' => $parentcontext->id,
            'showinsubcontexts' => 0,
            'requiredbytheme' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => (string) $page->id,
            'defaultregion' => 'content',
            'defaultweight' => -1,
            'configdata' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        context_block::instance($id);
    }
    block_gcal_calendar_place_before_timeline();
    return true;
}

/**
 * Moves the calendar of every Dashboard just before its Timeline (Dashboards without a Timeline keep it at the top).
 *
 * @return void
 */
function block_gcal_calendar_place_before_timeline(): void {
    global $DB;

    $calendars = $DB->get_records('block_instances', ['blockname' => 'gcal_calendar', 'pagetypepattern' => 'my-index']);
    foreach ($calendars as $calendar) {
        $timeline = $DB->get_record('block_instances', ['blockname' => 'timeline', 'pagetypepattern' => 'my-index',
            'subpagepattern' => $calendar->subpagepattern, 'parentcontextid' => $calendar->parentcontextid], '*', IGNORE_MULTIPLE);
        if (!$timeline) {
            continue;
        }
        // Make room: the Timeline and the blocks after it move down one place, the calendar takes its place.
        $DB->execute('UPDATE {block_instances} SET defaultweight = defaultweight + 1
                        WHERE pagetypepattern = ? AND subpagepattern = ? AND parentcontextid = ? AND defaultregion = ?
                              AND defaultweight >= ? AND id <> ?',
            ['my-index', $timeline->subpagepattern, $timeline->parentcontextid, $timeline->defaultregion,
                $timeline->defaultweight, $calendar->id]);
        $DB->update_record('block_instances', (object) ['id' => $calendar->id, 'defaultregion' => $timeline->defaultregion,
            'defaultweight' => $timeline->defaultweight, 'timemodified' => time()]);
        // A position the user dragged the calendar to (block_positions) stays theirs.
    }
}

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

namespace theme_modernui\output;

use core\output\renderer_base;
use core_completion\progress;
use core_course\external\course_summary_exporter;

/**
 * The personal welcome hero at the top of the Dashboard.
 *
 * Greeting by time of day (in the user's timezone), a milestone line and stat chips from course completion,
 * a "Continue where you left off" card (last accessed activity, else last accessed course), and a one-time
 * celebration when a course reaches 100%. Read-only apart from remembering which completions were celebrated.
 *
 * @package    theme_modernui
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard_hero implements \core\output\renderable, \core\output\templatable {
    /** @var string User preference: comma-separated ids of the completed courses already celebrated. */
    public const PREF_CELEBRATED = 'theme_modernui_celebrated';

    /** @var int Most courses looked at for the stats, so a user enrolled in hundreds does not slow the page. */
    private const MAX_COURSES = 50;

    /** @var \stdClass The user. */
    private \stdClass $user;

    /**
     * Constructor.
     *
     * @param \stdClass $user The current user.
     */
    public function __construct(\stdClass $user) {
        $this->user = $user;
    }

    /**
     * Whether the hero is shown: real logged-in users only (not guests, not "log in as").
     *
     * @return bool
     */
    public static function applies(): bool {
        return isloggedin() && !isguestuser() && !\core\session\manager::is_loggedinas();
    }

    /**
     * Export for theme_modernui/dashboard_hero.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $courses = enrol_get_my_courses(['enablecompletion'], 'visible DESC, sortorder ASC', self::MAX_COURSES);

        // Progress per course: null when the course does not track completion for this user.
        $progress = [];
        foreach ($courses as $course) {
            $percentage = progress::get_course_progress_percentage($course, $this->user->id);
            $progress[$course->id] = $percentage === null ? null : (int) floor($percentage);
        }
        $tracked = array_filter($progress, fn($p) => $p !== null);
        $completed = array_keys(array_filter($tracked, fn($p) => $p >= 100));
        $inprogress = array_filter($tracked, fn($p) => $p < 100);

        $chips = $this->chips($courses, $inprogress, $completed);
        return [
            'greeting' => $this->greeting(),
            'date' => userdate(time(), get_string('strftimedaydate', 'langconfig')),
            'message' => $this->message($courses, $inprogress, $completed),
            'chips' => $chips,
            'haschips' => !empty($chips),
            'celebrate' => $this->celebrate($courses, $completed),
            'continue' => $this->continue_card($output, $courses, $progress),
            'browseurl' => (new \core\url('/course/index.php'))->out(false),
            'hascourses' => !empty($courses),
        ];
    }

    /**
     * "Good morning, Sam", by the hour in the user's timezone.
     *
     * @return string
     */
    private function greeting(): string {
        $hour = (int) (new \DateTime('now', \core_date::get_user_timezone_object()))->format('G');
        $key = $hour < 12 ? 'hero_morning' : ($hour < 18 ? 'hero_afternoon' : 'hero_evening');
        return get_string($key, 'theme_modernui', format_string($this->user->firstname));
    }

    /**
     * The milestone line under the greeting.
     *
     * @param array $courses Enrolled courses.
     * @param array $inprogress Progress of the tracked courses not yet complete, by course id.
     * @param array $completed Ids of the completed courses.
     * @return string
     */
    private function message(array $courses, array $inprogress, array $completed): string {
        if (!$courses) {
            return get_string('hero_msg_nocourses', 'theme_modernui');
        }
        if ($inprogress) {
            $average = (int) round(array_sum($inprogress) / count($inprogress));
            return get_string('hero_msg_progress', 'theme_modernui', $average);
        }
        if ($completed) {
            return get_string('hero_msg_alldone', 'theme_modernui');
        }
        return get_string('hero_msg_ready', 'theme_modernui');
    }

    /**
     * Stat chips: enrolled courses, in progress, completed.
     *
     * @param array $courses
     * @param array $inprogress
     * @param array $completed
     * @return array[]
     */
    private function chips(array $courses, array $inprogress, array $completed): array {
        if (!$courses) {
            return [];
        }
        $chip = fn(int $n, string $one, string $many, string $icon) => [
            'text' => get_string($n === 1 ? $one : $many, 'theme_modernui', $n),
            'icon' => $icon,
        ];
        $chips = [$chip(count($courses), 'chip_course', 'chip_courses', 'fa-book-open')];
        if ($inprogress) {
            $chips[] = $chip(count($inprogress), 'chip_inprogress', 'chip_inprogress', 'fa-person-running');
        }
        if ($completed) {
            $chips[] = $chip(count($completed), 'chip_completed', 'chip_completed', 'fa-trophy');
        }
        return $chips;
    }

    /**
     * One completed course that has not been celebrated yet; it is then remembered so it shows only once.
     *
     * @param array $courses
     * @param int[] $completed
     * @return array|null
     */
    private function celebrate(array $courses, array $completed): ?array {
        $seen = array_filter(explode(',', (string) get_user_preferences(self::PREF_CELEBRATED, '', $this->user)));
        $new = array_values(array_diff($completed, $seen));
        if (!$new) {
            return null;
        }
        $course = $courses[$new[0]];
        $seen[] = $course->id;
        set_user_preference(self::PREF_CELEBRATED, implode(',', array_unique($seen)), $this->user);
        return [
            'text' => get_string('hero_celebrate', 'theme_modernui',
                format_string($course->fullname, true, ['context' => \context_course::instance($course->id)])),
            'url' => (new \core\url('/course/view.php', ['id' => $course->id]))->out(false),
        ];
    }

    /**
     * "Continue where you left off": the last accessed activity, or else the last accessed course.
     *
     * @param renderer_base $output
     * @param array $courses Enrolled courses (fallback when nothing was opened yet).
     * @param array $progress Progress by course id (null when not tracked).
     * @return array|null
     */
    private function continue_card(renderer_base $output, array $courses, array $progress): ?array {
        $course = null;
        $activity = null;
        $url = null;

        // The newest few rows of block_recentlyaccesseditems' log, read directly: its helper loads every row and the
        // course data of every course in them just to return one item. The first one still visible wins.
        global $DB;
        $dbman = $DB->get_manager();
        $recentitems = $dbman->table_exists('block_recentlyaccesseditems')
            ? $DB->get_records('block_recentlyaccesseditems', ['userid' => $this->user->id], 'timeaccess DESC, id DESC',
                'id, courseid, cmid', 0, 5)
            : [];
        foreach ($recentitems as $item) {
            try {
                $modinfo = get_fast_modinfo($item->courseid, $this->user->id);
                $cm = $modinfo->get_cm($item->cmid);
            } catch (\moodle_exception $e) {
                continue; // Course or activity deleted since.
            }
            if (!$cm->uservisible || !$cm->url || !can_access_course($modinfo->get_course(), $this->user, '', true)) {
                continue;
            }
            $course = $modinfo->get_course();
            $activity = [
                'name' => $cm->get_formatted_name(),
                'modname' => get_string('modulename', $cm->modname),
                'iconurl' => $cm->get_icon_url()->out(false),
            ];
            $url = $cm->url;
            break;
        }
        $fresh = false;
        if (!$course) {
            global $CFG;
            require_once($CFG->dirroot . '/course/lib.php');
            $recent = course_get_recent_courses($this->user->id, 1);
            $course = reset($recent) ?: null;
        }
        if (!$course && $courses) {
            // Enrolled but never opened a course: offer the first one as a place to start.
            $course = reset($courses);
            $fresh = true;
        }
        if (!$course) {
            return null;
        }
        $url = $url ?? new \core\url('/course/view.php', ['id' => $course->id]);

        $percentage = $progress[$course->id] ?? progress::get_course_progress_percentage($course, $this->user->id);
        $percentage = $percentage === null ? null : (int) floor($percentage);
        $image = course_summary_exporter::get_course_image($course) ?: $output->get_generated_image_for_id($course->id);

        return [
            'url' => $url->out(false),
            'coursename' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
            'activity' => $activity,
            'image' => $image,
            'fresh' => $fresh,
            'hasprogress' => $percentage !== null,
            'progress' => $percentage ?? 0,
        ];
    }
}

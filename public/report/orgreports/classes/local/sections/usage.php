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

namespace report_orgreports\local\sections;

use report_orgreports\local\metrics;
use report_orgreports\local\tables\storage;
use report_orgreports\local\vdocipher;

/**
 * Bandwidth and usage: measured storage and streaming figures where a source exists, logins and video views.
 *
 * Every card and chart says whether it is measured (read from Moodle or VdoCipher) or estimated; metrics without a
 * reliable source are listed as unavailable with what they would need.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage extends section {

    /** @var string[] chart sizes. */
    const SIZES = ['logins' => 'wide', 'storagesplit' => 'narrow', 'bandwidth' => 'full'];

    /** @var string[] chart order. */
    const ORDER = ['logins', 'storagesplit', 'courseaccess', 'activeusers', 'learningbycourse', 'videoviews', 'bandwidth'];

    public function get_tables(): array {
        return ['storage' => get_string('table_storage', 'report_orgreports')];
    }

    protected function build(): void {
        global $DB;
        $m = $this->metrics;
        $org = $m->org;
        $labels = $m->range->get_labels();
        $measured = get_string('measured', 'report_orgreports');

        // Moodle file storage (current, measured).
        $files = storage::file_storage($org);
        $filebytes = array_sum(array_column($files, 'bytes'));
        $this->add_card('filestorage', 'fa-solid fa-hard-drive', display_size($filebytes), null, null, null,
            $measured . ' · ' . get_string('hint_filestorage', 'report_orgreports'));

        // VdoCipher videos of the organisation.
        $configured = vdocipher::is_configured();
        $videos = $configured ? vdocipher::get_org_videos($org) : [];
        $videobytes = 0;
        $videolength = 0;
        $unreadable = 0;
        foreach (array_keys($videos) as $videoid) {
            $info = vdocipher::get_video($videoid);
            if ($info === null) {
                $unreadable++;
                continue;
            }
            $videobytes += $info['bytes'];
            $videolength += $info['length'];
        }
        if ($configured) {
            $this->add_card('videostorage', 'fa-solid fa-film', $unreadable && $unreadable == count($videos) && $videos ?
                get_string('unavailable', 'report_orgreports') : display_size($videobytes), null, null, null,
                $measured . ' · ' . get_string('hint_videostorage', 'report_orgreports'));
            $this->add_card('videos', 'fa-solid fa-circle-play', self::number(count($videos)), null, null, null,
                get_string('hint_videos', 'report_orgreports', self::duration($videolength)));
        }

        // Views of the activities that hold videos (measured from the log, not bytes).
        $cmids = [];
        foreach ($videos as $video) {
            $cmids += $video['cmids'] ?? [];
        }
        $viewseries = null;
        if ($cmids) {
            [$cmsql, $params] = $DB->get_in_or_equal(array_keys($cmids), SQL_PARAMS_NAMED, 'vcm');
            [$logsql, $lparams] = $m->course_log_sql('l');
            $views = $m->count('l.timecreated', '{logstore_standard_log} l', "$logsql AND l.crud = 'r'
                AND l.contextlevel = " . CONTEXT_MODULE . " AND l.contextinstanceid $cmsql
                AND " . $DB->sql_like('l.eventname', ':vviewed'), $params + $lparams + ['vviewed' => '%course_module_viewed']);
            $this->add_card('videoviews', 'fa-solid fa-eye', self::number($views['total']), $views['total'],
                $views['prev'], self::number($views['prev']), $measured . ' · ' .
                get_string('hint_videoviews', 'report_orgreports'));
            $viewseries = $views['series'];
        }

        // Bandwidth: VdoCipher measures it per account only.
        $usage = null;
        if ($configured && $org->is_all()) {
            $usage = vdocipher::get_account_usage();
        }
        if ($usage !== null) {
            $series = $m->zeros();
            $total = 0;
            $prev = 0;
            $prevfrom = userdate($m->range->prevstart, '%Y-%m-%d');
            $prevto = userdate($m->range->prevend - 1, '%Y-%m-%d');
            $covered = false;
            foreach ($usage as $date => $row) {
                $time = make_timestamp(...array_map('intval', explode('-', $date)));
                if (($b = $m->range->bucket_of($time)) !== null) {
                    $series[$b] += $row['bandwidth'];
                    $total += $row['bandwidth'];
                }
                if ($date >= $prevfrom && $date <= $prevto) {
                    $prev += $row['bandwidth'];
                    $covered = true;
                }
            }
            $this->add_card('bandwidth', 'fa-solid fa-gauge-high', self::number($total), $total, $covered ? $prev : null,
                $covered ? self::number($prev) : null, $measured . ' · ' . get_string('hint_bandwidth', 'report_orgreports'));
            $this->add_chart('bandwidth', 'bar', $labels,
                [['label' => get_string('chart_bandwidth_series', 'report_orgreports'), 'data' => $series]], 'number',
                get_string('chart_bandwidth_summary', 'report_orgreports'));
        } else {
            $this->add_card('bandwidth', 'fa-solid fa-gauge-high', get_string('unavailable', 'report_orgreports'), null,
                null, null, get_string($org->is_all() ? 'hint_bandwidth_none' : 'hint_bandwidth_org', 'report_orgreports'));
        }

        // Logins.
        $logins = $m->logins();
        $this->add_card('logins', 'fa-solid fa-right-to-bracket', self::number($logins['count']['total']),
            $logins['count']['total'], $logins['count']['prev'], self::number($logins['count']['prev']),
            $measured . ' · ' . get_string('hint_logins', 'report_orgreports'));
        $this->add_card('loginusers', 'fa-solid fa-users', self::number($logins['users']['total']),
            $logins['users']['total'], $logins['users']['prev'], self::number($logins['users']['prev']),
            get_string('hint_loginusers', 'report_orgreports'));

        $access = $m->course_access();
        $this->add_card('courseaccess', 'fa-solid fa-door-open', self::number($access['count']['total']),
            $access['count']['total'], $access['count']['prev'], self::number($access['count']['prev']),
            $measured . ' · ' . get_string('hint_courseaccess', 'report_orgreports'));
        $this->add_spark('courseaccess', $access['count']['series']);
        $this->add_chart('courseaccess', 'line', $labels, [
            ['label' => get_string('card_courseaccess', 'report_orgreports'), 'data' => $access['count']['series']],
            ['label' => get_string('courseviewers', 'report_orgreports'), 'data' => $access['users']['series']],
        ], 'count', get_string('courseaccesssummary', 'report_orgreports', ['views' => self::number($access['count']['total']),
            'users' => self::number($access['users']['total'])]));

        $this->add_chart('logins', 'line', $labels, [
            ['label' => get_string('card_logins', 'report_orgreports'), 'data' => $logins['count']['series']],
            ['label' => get_string('card_loginusers', 'report_orgreports'), 'data' => $logins['users']['series']],
        ], 'count', get_string('totalvalue', 'report_orgreports', self::number($logins['count']['total'])));

        $active = $m->active_users();
        $this->add_chart('activeusers', 'area', $labels, [['label' => get_string('card_activeusers', 'report_orgreports'),
            'data' => $active['series']]], 'count',
            get_string('totalvalue', 'report_orgreports', self::number($active['total'])));

        if ($viewseries !== null) {
            $this->add_chart('videoviews', 'bar', $labels, [['label' => get_string('card_videoviews', 'report_orgreports'),
                'data' => $viewseries]], 'count');
        }

        // Learning time per course (estimated).
        $learning = $m->learning_time();
        $courses = $org->get_courses();
        arsort($learning['courses']);
        $top = array_slice($learning['courses'], 0, 10, true);
        $this->add_chart('learningbycourse', 'hbar', array_map(fn($id) => format_string($courses[$id]->fullname ?? $id),
            array_keys($top)), [['label' => get_string('hours', 'report_orgreports'),
            'data' => array_map(fn($s) => $s / HOURSECS, array_values($top))]], 'hours',
            get_string('estimated', 'report_orgreports') . ' · ' . self::duration($learning['total']));

        // Storage split.
        if ($configured) {
            $this->add_chart('storagesplit', 'doughnut', [get_string('card_filestorage', 'report_orgreports'),
                get_string('card_videostorage', 'report_orgreports')], [['label' => get_string('megabytes',
                'report_orgreports'), 'data' => [$filebytes / 1048576, $videobytes / 1048576]]], 'megabytes',
                display_size($filebytes + $videobytes));
        }

        // Sparklines.
        $this->add_spark('logins', $logins['count']['series']);
        $this->add_spark('loginusers', $logins['users']['series']);
        if ($viewseries !== null) {
            $this->add_spark('videoviews', $viewseries);
        }
        if (isset($series) && $usage !== null) {
            $this->add_spark('bandwidth', $series);
        }

        // Where every usage figure comes from.
        $s = fn($k, $a = null) => get_string($k, 'report_orgreports', $a);
        $this->add_availability($s('card_filestorage'), 'measured', $s('avail_filestorage'));
        $this->add_availability($s('card_videostorage'), $configured ? 'measured' : 'unavailable',
            $configured ? $s('avail_videostorage') : $s('avail_vdonotconfigured'));
        if (!$configured) {
            $this->add_availability($s('card_bandwidth'), 'unavailable', $s('avail_vdonotconfigured'));
        } else if (!$org->is_all()) {
            $this->add_availability($s('card_bandwidth'), 'unavailable', $s('avail_bandwidth_org'));
        } else {
            $this->add_availability($s('card_bandwidth'), $usage !== null ? 'measured' : 'unavailable',
                $usage !== null ? $s('avail_bandwidth_all') : $s('avail_vdoerror', s(vdocipher::get_error() ?? '')));
        }
        $this->add_availability($s('avail_watchtime'), 'unavailable', $s('avail_watchtime_desc'));
        $this->add_availability($s('avail_webtransfer'), 'unavailable', $s('avail_webtransfer_desc'));
        $this->add_availability($s('card_videoviews'), 'measured', $s('avail_videoviews'));
        $this->add_availability($s('card_logins'), 'measured', $s('avail_logins'));
        $this->add_availability($s('card_courseaccess'), 'measured', $s('avail_courseaccess'));
        $this->add_availability($s('card_learningtime'), 'estimated',
            $s('avail_learningtime', (int)(metrics::IDLE / MINSECS)));

        if ($configured && $org->is_all() && $usage !== null) {
            $this->add_notice('warning', get_string('notice_vdounits', 'report_orgreports'),
                get_string('notice_vdounits_desc', 'report_orgreports'));
        }
        if ($configured && $unreadable) {
            $this->add_notice('warning', get_string('notice_vdoerror', 'report_orgreports'),
                get_string('notice_vdoerror_desc', 'report_orgreports', s(vdocipher::get_error() ?? '')));
        }
    }
}


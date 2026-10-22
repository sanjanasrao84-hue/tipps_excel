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

namespace report_orgreports\local\tables;

use report_orgreports\local\vdocipher;

/**
 * Storage per course: Moodle files (measured from the files table) and VdoCipher videos (sizes from the API).
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class storage extends table {

    /** @var array|null cached rows. */
    private ?array $rows = null;

    public function get_columns(): array {
        return [
            'course' => ['label' => get_string('course'), 'format' => 'name', 'sort' => true],
            'files' => ['label' => get_string('col_files', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'filebytes' => ['label' => get_string('card_filestorage', 'report_orgreports'), 'format' => 'bytes',
                'sort' => true],
            'videos' => ['label' => get_string('card_videos', 'report_orgreports'), 'format' => 'int', 'sort' => true],
            'videobytes' => ['label' => get_string('card_videostorage', 'report_orgreports'), 'format' => 'bytes',
                'sort' => true],
            'videolength' => ['label' => get_string('col_videolength', 'report_orgreports'), 'format' => 'duration',
                'sort' => true],
        ];
    }

    protected function get_default_sort(): array {
        return ['filebytes', 'DESC'];
    }

    /**
     * Stored files per course, each distinct file content counted once per course.
     *
     * @param \report_orgreports\org_context $org
     * @param int $courseid
     * @return array courseid => ['files' => int, 'bytes' => int]
     */
    public static function file_storage(\report_orgreports\org_context $org, int $courseid = 0): array {
        global $DB;
        [$csql, $params] = $org->course_sql('cctx.instanceid', $courseid);
        // Context paths only hold digits and slashes, so a plain LIKE on the parent path is exact.
        $inside = 'fctx.path LIKE ' . $DB->sql_concat('cctx.path', "'/%'");
        $rs = $DB->get_recordset_sql("SELECT cctx.instanceid AS courseid, f.contenthash, MAX(f.filesize) AS filesize,
                COUNT(1) AS files
            FROM {files} f
            JOIN {context} fctx ON fctx.id = f.contextid
            JOIN {context} cctx ON cctx.contextlevel = " . CONTEXT_COURSE . " AND (fctx.id = cctx.id OR $inside)
            WHERE $csql AND f.filename <> '.' AND f.filesize > 0
            GROUP BY cctx.instanceid, f.contenthash", $params);
        $result = [];
        foreach ($rs as $row) {
            $result[$row->courseid] ??= ['files' => 0, 'bytes' => 0];
            $result[$row->courseid]['files'] += (int)$row->files;
            $result[$row->courseid]['bytes'] += (int)$row->filesize;
        }
        $rs->close();
        return $result;
    }

    /**
     * VdoCipher storage per course.
     *
     * @param \report_orgreports\org_context $org
     * @return array [courseid => ['videos', 'bytes', 'length']], plus whether every video could be read
     */
    public static function video_storage(\report_orgreports\org_context $org): array {
        $result = [];
        $complete = true;
        if (!vdocipher::is_configured()) {
            return [$result, false];
        }
        foreach (vdocipher::get_org_videos($org) as $videoid => $video) {
            $info = vdocipher::get_video($videoid);
            if ($info === null) {
                $complete = false;
            }
            foreach (array_keys($video['courses']) as $courseid) {
                $result[$courseid] ??= ['videos' => 0, 'bytes' => 0, 'length' => 0];
                $result[$courseid]['videos']++;
                $result[$courseid]['bytes'] += $info['bytes'] ?? 0;
                $result[$courseid]['length'] += $info['length'] ?? 0;
            }
        }
        return [$result, $complete];
    }

    /**
     * Rows.
     *
     * @return array[]
     */
    private function build(): array {
        if ($this->rows === null) {
            $files = self::file_storage($this->org, $this->request->courseid);
            [$videos, $complete] = self::video_storage($this->org);
            $rows = [];
            foreach ($this->org->get_courses() as $id => $course) {
                if (($this->request->courseid && $id != $this->request->courseid) || !$this->matches($course->fullname)) {
                    continue;
                }
                $rows[] = [
                    'course' => $course->fullname,
                    'files' => $files[$id]['files'] ?? 0,
                    'filebytes' => $files[$id]['bytes'] ?? 0,
                    'videos' => $videos[$id]['videos'] ?? 0,
                    'videobytes' => isset($videos[$id]) ? $videos[$id]['bytes'] : null,
                    'videolength' => isset($videos[$id]) ? $videos[$id]['length'] : null,
                ];
            }
            $this->rows = $rows;
        }
        return $this->rows;
    }

    public function count_rows(): int {
        return count($this->build());
    }

    public function get_rows(int $offset, int $limit): iterable {
        return $this->sort_and_slice($this->build(), $offset, $limit);
    }
}

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

namespace report_orgreports\local;

use report_orgreports\org_context;

/**
 * VdoCipher data: the videos embedded in an organisation's courses and what the VdoCipher API reports about them.
 *
 * filter_vdocipher stores nothing in Moodle; videos are the [vdo id="..."] shortcodes of course content. The API key
 * is the one configured for the filter, and responses are cached for an hour (cache report_orgreports/vdocipher).
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vdocipher {

    /** @var string API base. */
    const API = 'https://dev.vdocipher.com/api/';

    /** @var string|null last API error. */
    private static ?string $error = null;

    /**
     * Whether the filter is installed, enabled and has an API key.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        if (\core_component::get_component_directory('filter_vdocipher') === null) {
            return false;
        }
        $active = filter_get_globally_enabled();
        return isset($active['vdocipher']) && (string)get_config('filter_vdocipher', 'csk') !== '';
    }

    /**
     * Last API error, if any.
     *
     * @return string|null
     */
    public static function get_error(): ?string {
        return self::$error;
    }

    /**
     * Video ids in a text, read the way filter_vdocipher reads them.
     *
     * @param string|null $text
     * @return string[]
     */
    public static function extract_ids(?string $text): array {
        if ($text === null || strpos($text, '[vdo ') === false) {
            return [];
        }
        preg_match_all('/\[vdo\s+([A-Za-z0-9\=\s\"\']+)\]/', $text, $codes);
        $ids = [];
        foreach ($codes[1] as $attributes) {
            if (preg_match('/\bid\s*=\s*["\']?([A-Za-z0-9]+)/', $attributes, $m)) {
                $ids[] = $m[1];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Videos embedded in the organisation's courses (pages, labels, books, section summaries).
     *
     * @param org_context $org
     * @return array videoid => ['courses' => [courseid => true], 'cmids' => [cmid => true]]
     */
    public static function get_org_videos(org_context $org): array {
        global $DB;
        $dbman = $DB->get_manager();
        [$csql, $baseparams] = $org->course_sql('cm.course');
        $videos = [];
        $add = function(array $ids, int $courseid, int $cmid) use (&$videos) {
            foreach ($ids as $id) {
                $videos[$id]['courses'][$courseid] = true;
                if ($cmid) {
                    $videos[$id]['cmids'][$cmid] = true;
                }
            }
        };
        $sources = [
            'page' => ['table' => 'page', 'fields' => ['intro', 'content'], 'instance' => 't.id'],
            'label' => ['table' => 'label', 'fields' => ['intro'], 'instance' => 't.id'],
            'book' => ['table' => 'book_chapters', 'fields' => ['content'], 'instance' => 't.bookid'],
        ];
        foreach ($sources as $modname => $source) {
            if (!$dbman->table_exists($source['table']) || !$DB->record_exists('modules', ['name' => $modname])) {
                continue;
            }
            $params = $baseparams + ['modname' => $modname];
            $likes = [];
            foreach ($source['fields'] as $i => $field) {
                $likes[] = $DB->sql_like("t.$field", ":vdo$i", false);
                $params["vdo$i"] = '%[vdo %';
            }
            $fields = implode(', ', array_map(fn($f) => "t.$f", $source['fields']));
            $rs = $DB->get_recordset_sql("SELECT t.id, cm.id AS cmid, cm.course, $fields
                FROM {{$source['table']}} t
                JOIN {course_modules} cm ON cm.instance = {$source['instance']} AND cm.deletioninprogress = 0
                JOIN {modules} md ON md.id = cm.module AND md.name = :modname
                WHERE $csql AND (" . implode(' OR ', $likes) . ")", $params);
            foreach ($rs as $row) {
                foreach ($source['fields'] as $field) {
                    $add(self::extract_ids($row->$field), (int)$row->course, (int)$row->cmid);
                }
            }
            $rs->close();
        }
        [$csql, $params] = $org->course_sql('s.course');
        $rs = $DB->get_recordset_sql("SELECT s.id, s.course, s.summary FROM {course_sections} s
            WHERE $csql AND " . $DB->sql_like('s.summary', ':vdos', false), $params + ['vdos' => '%[vdo %']);
        foreach ($rs as $row) {
            $add(self::extract_ids($row->summary), (int)$row->course, 0);
        }
        $rs->close();
        return $videos;
    }

    /**
     * GET an API path, cached.
     *
     * @param string $path
     * @return mixed decoded JSON, or null on error ({@see self::get_error()})
     */
    private static function get(string $path) {
        global $CFG;
        $cache = \cache::make('report_orgreports', 'vdocipher');
        $key = sha1($path);
        $cached = $cache->get($key);
        if ($cached !== false) {
            return $cached;
        }
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $response = $curl->get(self::API . $path, [], [
            'CURLOPT_HTTPHEADER' => ['Accept: application/json',
                'Authorization: Apisecret ' . get_config('filter_vdocipher', 'csk')],
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_CONNECTTIMEOUT' => 5,
        ]);
        $info = $curl->get_info();
        $code = (int)($info['http_code'] ?? 0);
        if ($curl->get_errno() || $code !== 200) {
            self::$error = $curl->error ?: "HTTP $code";
            return null;
        }
        $data = json_decode($response, true);
        if ($data === null) {
            self::$error = 'Invalid JSON';
            return null;
        }
        $cache->set($key, $data);
        return $data;
    }

    /**
     * Title, duration and stored bytes of a video as VdoCipher reports them.
     *
     * Stored bytes are the sizes of the video's distinct files (original upload, encrypted renditions, posters,
     * subtitles) from GET /videos/{id}/files.
     *
     * @param string $videoid
     * @return array|null ['title', 'length' => seconds, 'bytes']
     */
    public static function get_video(string $videoid): ?array {
        if (!preg_match('/^[A-Za-z0-9]+$/', $videoid)) {
            return null;
        }
        $meta = self::get("videos/$videoid");
        $files = self::get("videos/$videoid/files");
        if (!is_array($meta) || !is_array($files)) {
            return null;
        }
        $sizes = [];
        foreach ($files as $file) {
            if (isset($file['id'], $file['size'])) {
                $sizes[$file['id']] = (int)$file['size'];
            }
        }
        return ['title' => (string)($meta['title'] ?? $videoid), 'length' => (int)($meta['length'] ?? 0),
            'bytes' => array_sum($sizes)];
    }

    /**
     * Daily account usage from GET /account/usage, as returned: bandwidth and storage values per date.
     *
     * VdoCipher reports these for the whole account, and its documentation does not state their units.
     *
     * @return array|null date (Y-m-d) => ['bandwidth' => int, 'storage' => int]
     */
    public static function get_account_usage(): ?array {
        $data = self::get('account/usage');
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
            return null;
        }
        $result = [];
        foreach ($data['data'] as $row) {
            if (isset($row['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'])) {
                $result[$row['date']] = ['bandwidth' => (int)($row['bandwidth'] ?? 0), 'storage' => (int)($row['storage'] ?? 0)];
            }
        }
        ksort($result);
        return $result;
    }
}

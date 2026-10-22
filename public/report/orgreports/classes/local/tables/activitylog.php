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

/**
 * Activity log of the organisation's courses from the standard log store.
 *
 * Anonymous events, guests and IP addresses are never shown.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activitylog extends table {

    /** @var string[] event names by class. */
    private static array $eventnames = [];

    public function get_columns(): array {
        global $DB;
        return [
            'time' => ['label' => get_string('time'), 'format' => 'datetime', 'sort' => 'l.timecreated'],
            'user' => ['label' => get_string('user'), 'format' => 'text',
                'sort' => $DB->sql_fullname('u.firstname', 'u.lastname')],
            'course' => ['label' => get_string('course'), 'format' => 'name', 'sort' => 'c.fullname'],
            'activity' => ['label' => get_string('col_activitytype', 'report_orgreports'), 'format' => 'text',
                'sort' => 'l.eventname'],
            'component' => ['label' => get_string('col_component', 'report_orgreports'), 'format' => 'text',
                'sort' => 'l.component'],
            'type' => ['label' => get_string('col_type', 'report_orgreports'), 'format' => 'text', 'sort' => 'l.edulevel'],
            'status' => ['label' => get_string('col_action', 'report_orgreports'), 'format' => 'status', 'sort' => 'l.crud'],
        ];
    }

    protected function get_default_sort(): array {
        return ['time', 'DESC'];
    }

    public function get_statuses(): array {
        return [
            'c' => get_string('crud_c', 'report_orgreports'),
            'r' => get_string('crud_r', 'report_orgreports'),
            'u' => get_string('crud_u', 'report_orgreports'),
            'd' => get_string('crud_d', 'report_orgreports'),
        ];
    }

    /**
     * Query parts.
     *
     * @return array [from, where, params]
     */
    private function get_sql(): array {
        global $DB;
        $range = $this->metrics->range;
        [$logsql, $params] = $this->metrics->course_log_sql('l', $this->request->courseid);
        $where = [$logsql, 'l.timecreated >= :als', 'l.timecreated < :ale', 'u.deleted = 0'];
        $params += ['als' => $range->start, 'ale' => $range->end];
        if ($this->request->status !== '') {
            $where[] = 'l.crud = :acrud';
            $params['acrud'] = $this->request->status;
        }
        $this->add_search([$DB->sql_fullname('u.firstname', 'u.lastname'), 'c.fullname', 'l.eventname', 'l.component'],
            $where, $params);
        $from = '{logstore_standard_log} l JOIN {user} u ON u.id = l.userid JOIN {course} c ON c.id = l.courseid';
        return [$from, implode(' AND ', $where), $params];
    }

    public function count_rows(): int {
        global $DB;
        [$from, $where, $params] = $this->get_sql();
        return $DB->count_records_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
    }

    /**
     * Readable name of an event class.
     *
     * @param string $class
     * @return string
     */
    private static function event_name(string $class): string {
        if (!isset(self::$eventnames[$class])) {
            $name = $class;
            try {
                if (class_exists($class) && is_subclass_of($class, \core\event\base::class)) {
                    $name = $class::get_name();
                }
            } catch (\Throwable $e) {
                $name = $class;
            }
            self::$eventnames[$class] = $name;
        }
        return self::$eventnames[$class];
    }

    public function get_rows(int $offset, int $limit): iterable {
        global $DB;
        [$from, $where, $params] = $this->get_sql();
        [$sort, $dir] = $this->get_sort();
        $levels = [
            \core\event\base::LEVEL_TEACHING => get_string('edulevel_teaching', 'report_orgreports'),
            \core\event\base::LEVEL_PARTICIPATING => get_string('edulevel_participating', 'report_orgreports'),
            \core\event\base::LEVEL_OTHER => get_string('edulevel_other', 'report_orgreports'),
        ];
        $statuses = $this->get_statuses();
        $rs = $DB->get_recordset_sql("SELECT l.id, l.timecreated, l.eventname, l.component, l.edulevel, l.crud,
                c.fullname, " . self::name_selects() . "
            FROM $from WHERE $where
            ORDER BY " . $this->get_columns()[$sort]['sort'] . " $dir, l.id DESC", $params, $offset, $limit);
        foreach ($rs as $r) {
            $component = $r->component;
            if (get_string_manager()->string_exists('pluginname', $component)) {
                $component = get_string('pluginname', $component);
            }
            yield [
                'time' => $r->timecreated,
                'user' => self::fullname($r),
                'course' => $r->fullname,
                'activity' => self::event_name($r->eventname),
                'component' => $component,
                'type' => $levels[$r->edulevel] ?? $r->edulevel,
                'status' => $statuses[$r->crud] ?? $r->crud,
                'statuskey' => 'crud' . $r->crud,
            ];
        }
        $rs->close();
    }
}

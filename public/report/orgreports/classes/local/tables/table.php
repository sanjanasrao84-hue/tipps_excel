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

use report_orgreports\local\metrics;
use report_orgreports\local\sections\section;
use report_orgreports\org_context;

/**
 * A dashboard table over live data of one organisation and period.
 *
 * The page and the CSV download take their rows from the same {@see self::get_rows()} with the same request.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class table {

    /** @var string[] table keys. */
    const ALL = ['transactions', 'itemsales', 'salesbydate', 'salesbyorg', 'courseactivity', 'activitylog', 'storage',
        'orgactivity', 'payments'];

    /** @var metrics */
    protected metrics $metrics;

    /** @var org_context */
    protected org_context $org;

    /** @var table_request */
    protected table_request $request;

    /**
     * Constructor.
     *
     * @param metrics $metrics
     * @param table_request $request
     */
    public function __construct(metrics $metrics, table_request $request) {
        $this->metrics = $metrics;
        $this->org = $metrics->org;
        $this->request = $request;
        // Only the organisation's own courses can be used as a filter.
        if ($request->courseid && !isset($this->org->get_courses()[$request->courseid])) {
            $request->courseid = 0;
        }
        if ($request->status !== '' && !isset($this->get_statuses()[$request->status])) {
            $request->status = '';
        }
    }

    /**
     * Table class for a key.
     *
     * @param string $key
     * @return string
     */
    public static function class_for(string $key): string {
        return __NAMESPACE__ . '\\' . $key;
    }

    /**
     * Key.
     *
     * @return string
     */
    public static function get_key(): string {
        $parts = explode('\\', static::class);
        return end($parts);
    }

    /**
     * Whether the user may see the table.
     *
     * @param org_context $org
     * @return bool
     */
    public static function is_allowed(org_context $org): bool {
        return true;
    }

    /**
     * Columns: key => ['label', 'format', 'sort' => bool|sql].
     *
     * @return array
     */
    abstract public function get_columns(): array;

    /**
     * Default sort.
     *
     * @return array [key, dir]
     */
    abstract protected function get_default_sort(): array;

    /**
     * Status filter options, empty when the table has none.
     *
     * @return string[]
     */
    public function get_statuses(): array {
        return [];
    }

    /**
     * Whether the course filter applies.
     *
     * @return bool
     */
    public function has_course_filter(): bool {
        return true;
    }

    /**
     * Number of rows.
     *
     * @return int
     */
    abstract public function count_rows(): int;

    /**
     * Raw rows.
     *
     * @param int $offset
     * @param int $limit 0 for all
     * @return iterable
     */
    abstract public function get_rows(int $offset, int $limit): iterable;

    /**
     * Sort in use, always a sortable column.
     *
     * @return array [key, dir]
     */
    public function get_sort(): array {
        $columns = $this->get_columns();
        if ($this->request->sort !== '' && !empty($columns[$this->request->sort]['sort'])) {
            return [$this->request->sort, $this->request->dir];
        }
        return $this->get_default_sort();
    }

    /**
     * One page for the dashboard.
     *
     * @return array
     */
    public function get_page(): array {
        $columns = $this->get_columns();
        $rows = [];
        foreach ($this->get_rows($this->request->page * $this->request->perpage, $this->request->perpage) as $row) {
            $cells = [];
            foreach (array_keys($columns) as $key) {
                $cells[] = ['value' => $this->format_cell($key, $row, false),
                    'badge' => ($columns[$key]['format'] ?? '') === 'status' ? (string)($row[$key . 'key'] ?? '') : ''];
            }
            $rows[] = ['cells' => $cells];
        }
        [$sort, $dir] = $this->get_sort();
        $statuses = [];
        foreach ($this->get_statuses() as $value => $label) {
            $statuses[] = ['value' => $value, 'label' => $label, 'selected' => $value === $this->request->status];
        }
        return [
            'key' => static::get_key(),
            'columns' => array_map(fn($k, $c) => ['key' => $k, 'label' => $c['label'], 'sortable' => !empty($c['sort']),
                'numeric' => in_array($c['format'] ?? 'text', ['int', 'money', 'bytes', 'duration', 'percent', 'totals']),
                'sorted' => $k === $sort ? strtolower($dir) : ''], array_keys($columns), $columns),
            'rows' => $rows,
            'total' => $this->count_rows(),
            'page' => $this->request->page,
            'perpage' => $this->request->perpage,
            'statuses' => $statuses,
            'coursefilter' => $this->has_course_filter(),
        ];
    }

    /**
     * Header and rows for a CSV.
     *
     * @return array [headers, iterable of rows]
     */
    public function get_export(): array {
        // Columns marked 'export' => false (row actions) are only for the page.
        $columns = array_filter($this->get_columns(), fn($c) => $c['export'] ?? true);
        $rows = (function() use ($columns) {
            foreach ($this->get_rows(0, 0) as $row) {
                $out = [];
                foreach (array_keys($columns) as $key) {
                    $out[] = $this->format_cell($key, $row, true);
                }
                yield $out;
            }
        })();
        return [array_column($columns, 'label'), $rows];
    }

    /**
     * Cell value for the page (HTML-safe) or the CSV (plain).
     *
     * @param string $key
     * @param array $row
     * @param bool $export
     * @return string
     */
    public function format_cell(string $key, array $row, bool $export): string {
        $format = $this->get_columns()[$key]['format'] ?? 'text';
        $value = $row[$key] ?? null;
        if ($value === null || $value === '') {
            return $export ? '' : '—';
        }
        switch ($format) {
            case 'action':
                // A button the page wires up; the value is the row key, escaped here.
                return $export ? '' : '<button type="button" class="orgr-btn orgr-btn-ghost orgr-btn-sm" ' .
                    'data-action="detail" data-txid="' . s($value) . '"><i class="fa-solid fa-eye" aria-hidden="true"></i> ' .
                    get_string('view') . '</button>';
            case 'bucket':
                // A chart bucket: its label on screen, its ISO date in the CSV.
                return $export ? (string)($row[$key . 'date'] ?? $value) : s($value);
            case 'datetime':
                return $value ? userdate($value, $export ? '%Y-%m-%d %H:%M' :
                    get_string('strftimedatetimeshort', 'langconfig')) : ($export ? '' : '—');
            case 'int':
                return $export ? (string)(int)$value : section::number((float)$value);
            case 'percent':
                return $export ? sprintf('%.1f', $value) : section::number((float)$value, 1) . '%';
            case 'money':
                return $export ? sprintf('%.2f', $value) : section::money([$row['currency'] => (float)$value]);
            case 'totals':
                if ($export) {
                    ksort($value);
                    return implode('; ', array_map(fn($c, $a) => sprintf('%s %.2f', $c, $a), array_keys($value), $value));
                }
                return section::money($value);
            case 'bytes':
                return $export ? (string)(int)$value : display_size((int)$value);
            case 'duration':
                return $export ? sprintf('%.2f', $value / HOURSECS) : section::duration((int)$value);
            case 'name':
                $value = format_string($value, true, ['context' => $this->org->context]);
                return $export ? self::csv_safe(html_entity_decode($value, ENT_QUOTES, 'UTF-8')) : $value;
            default:
                return $export ? self::csv_safe((string)$value) : s($value);
        }
    }

    /**
     * Keep spreadsheet programs from reading a cell as a formula.
     *
     * @param string $value
     * @return string
     */
    protected static function csv_safe(string $value): string {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    /**
     * Search condition over columns.
     *
     * @param string[] $columns
     * @param array $where
     * @param array $params
     */
    protected function add_search(array $columns, array &$where, array &$params): void {
        global $DB;
        if ($this->request->search === '') {
            return;
        }
        $likes = [];
        foreach (array_values($columns) as $i => $column) {
            $likes[] = $DB->sql_like($column, ":tsearch$i", false, false);
            $params["tsearch$i"] = '%' . $DB->sql_like_escape($this->request->search) . '%';
        }
        $where[] = '(' . implode(' OR ', $likes) . ')';
    }

    /**
     * Whether some texts match the search.
     *
     * @param string ...$values
     * @return bool
     */
    protected function matches(string ...$values): bool {
        if ($this->request->search === '') {
            return true;
        }
        $needle = \core_text::strtolower($this->request->search);
        foreach ($values as $value) {
            if (str_contains(\core_text::strtolower(strip_tags(format_string($value))), $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sort and page rows built in PHP.
     *
     * @param array[] $rows
     * @param int $offset
     * @param int $limit
     * @return array[]
     */
    protected function sort_and_slice(array $rows, int $offset, int $limit): array {
        [$key, $dir] = $this->get_sort();
        $column = $this->get_columns()[$key];
        $format = $column['format'] ?? 'text';
        $numeric = !in_array($format, ['text', 'name', 'status']) || isset($column['sortkey']);
        $key = $column['sortkey'] ?? $key;
        usort($rows, function($a, $b) use ($key, $numeric, $dir) {
            $cmp = $numeric ? ((float)($a[$key] ?? 0) <=> (float)($b[$key] ?? 0)) :
                strcmp(\core_text::strtolower((string)($a[$key] ?? '')), \core_text::strtolower((string)($b[$key] ?? '')));
            return $dir === 'DESC' ? -$cmp : $cmp;
        });
        return $limit ? array_slice($rows, $offset, $limit) : $rows;
    }

    /**
     * Full name from a record with name fields.
     *
     * @param \stdClass $record
     * @return string
     */
    protected static function fullname(\stdClass $record): string {
        $user = new \stdClass();
        foreach (\core_user\fields::get_name_fields() as $field) {
            $user->$field = $record->$field ?? '';
        }
        return fullname($user);
    }

    /**
     * SELECT of the user name fields.
     *
     * @param string $alias
     * @return string
     */
    protected static function name_selects(string $alias = 'u'): string {
        return implode(', ', array_map(fn($f) => "$alias.$f", \core_user\fields::get_name_fields()));
    }
}

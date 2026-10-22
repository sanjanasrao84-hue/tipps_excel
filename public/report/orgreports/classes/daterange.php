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

namespace report_orgreports;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The reporting period, its previous equivalent period and the chart buckets, all in the user's timezone.
 *
 * Every metric uses [start, end) of this class. Database values are aggregated per hour from the start of the period
 * ({@see self::hour_sql()}), which is a local midnight, and hours are then placed in day or month buckets here, so day
 * and month boundaries follow the user's timezone (including daylight saving changes) on any database.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class daterange {

    /** @var string[] presets. */
    const PRESETS = ['today', 'last7', 'last30', 'thismonth', 'lastmonth', 'thisyear', 'custom'];

    /** @var string preset. */
    public readonly string $preset;

    /** @var int start (inclusive). */
    public readonly int $start;

    /** @var int end (exclusive). */
    public readonly int $end;

    /** @var int previous period start. */
    public readonly int $prevstart;

    /** @var int previous period end. */
    public readonly int $prevend;

    /** @var string hour, day or month. */
    public readonly string $granularity;

    /** @var string Y-m-d of the first day, for the date inputs. */
    public readonly string $from;

    /** @var string Y-m-d of the last day. */
    public readonly string $to;

    /** @var array[] buckets: ['start' => int, 'end' => int, 'label' => string]. */
    private array $buckets = [];

    /** @var int[] bucket starts for the binary search. */
    private array $starts = [];

    /**
     * Build a period.
     *
     * @param string $preset one of PRESETS
     * @param string $from Y-m-d for custom
     * @param string $to Y-m-d for custom
     * @param int|null $now for tests
     */
    public function __construct(string $preset, string $from = '', string $to = '', ?int $now = null) {
        $tz = \core_date::get_user_timezone_object();
        $now = $now ?? time();
        $nowdt = (new DateTimeImmutable('@' . $now))->setTimezone($tz);
        $today = $nowdt->setTime(0, 0);
        if (!in_array($preset, self::PRESETS)) {
            $preset = 'last30';
        }
        if ($preset === 'custom') {
            $fromdt = self::parse_date($from, $tz);
            $todt = self::parse_date($to, $tz);
            if (!$fromdt || !$todt) {
                $preset = 'last30';
            } else {
                if ($fromdt > $todt) {
                    [$fromdt, $todt] = [$todt, $fromdt];
                }
                // At most ten years, so the hourly aggregation stays bounded.
                if ($fromdt < $todt->modify('-10 years')) {
                    $fromdt = $todt->modify('-10 years');
                }
            }
        }
        $this->preset = $preset;

        switch ($preset) {
            case 'today':
                [$start, $end, $shift] = [$today, $nowdt, '-1 day'];
                break;
            case 'last7':
                [$start, $end, $shift] = [$today->modify('-6 days'), $nowdt, '-7 days'];
                break;
            case 'thismonth':
                [$start, $end, $shift] = [$today->modify('first day of this month'), $nowdt, '-1 month'];
                break;
            case 'lastmonth':
                $start = $today->modify('first day of last month');
                [$end, $shift] = [$today->modify('first day of this month'), '-1 month'];
                break;
            case 'thisyear':
                [$start, $end, $shift] = [$today->setDate((int)$today->format('Y'), 1, 1), $nowdt, '-1 year'];
                break;
            case 'custom':
                $start = $fromdt;
                $end = $todt->modify('+1 day');
                $days = (int)$start->diff($end)->days;
                $shift = "-$days days";
                break;
            default:
                [$start, $end, $shift] = [$today->modify('-29 days'), $nowdt, '-30 days'];
        }
        // Periods that run until now include the current second (the end is exclusive).
        if ($end == $nowdt) {
            $end = $nowdt->modify('+1 second');
        }
        $this->start = $start->getTimestamp();
        $this->end = max($this->start + 1, $end->getTimestamp());
        // The previous equivalent period: the same window one period earlier, never overlapping this one.
        $prevstart = $start->modify($shift);
        $this->prevstart = $prevstart->getTimestamp();
        $this->prevend = min($end->modify($shift)->getTimestamp(), $this->start);
        $this->from = $start->format('Y-m-d');
        $this->to = (new DateTimeImmutable('@' . ($this->end - 1)))->setTimezone($tz)->format('Y-m-d');

        $days = ($this->end - $this->start) / DAYSECS;
        $this->granularity = $days <= 2 ? 'hour' : ($days <= 92 ? 'day' : 'month');
        $step = ['hour' => '+1 hour', 'day' => '+1 day', 'month' => 'first day of next month'][$this->granularity];
        $format = ['hour' => 'H:00', 'day' => 'd M', 'month' => 'M Y'][$this->granularity];
        $cursor = $start;
        while ($cursor->getTimestamp() < $this->end) {
            $next = $cursor->modify($step);
            if ($this->granularity === 'hour' && $days > 1) {
                $label = $cursor->format('d M H:00');
            } else {
                $label = $cursor->format($format);
            }
            $this->buckets[] = ['start' => $cursor->getTimestamp(), 'end' => min($next->getTimestamp(), $this->end),
                'label' => $label,
                'date' => $cursor->format(['hour' => 'Y-m-d H:i', 'day' => 'Y-m-d', 'month' => 'Y-m'][$this->granularity])];
            $this->starts[] = $cursor->getTimestamp();
            $cursor = $next;
        }
    }

    /**
     * Read the period from the request or given values.
     *
     * @param string $preset
     * @param string $from
     * @param string $to
     * @return self
     */
    public static function from_values(string $preset, string $from, string $to): self {
        return new self($preset, $from, $to);
    }

    /**
     * Y-m-d to a local midnight, or null.
     *
     * @param string $value
     * @param DateTimeZone $tz
     * @return DateTimeImmutable|null
     */
    private static function parse_date(string $value, DateTimeZone $tz): ?DateTimeImmutable {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return null;
        }
        return (new DateTimeImmutable('now', $tz))->setDate((int)$m[1], (int)$m[2], (int)$m[3])->setTime(0, 0);
    }

    /**
     * Chart buckets.
     *
     * @return array[]
     */
    public function get_buckets(): array {
        return $this->buckets;
    }

    /**
     * Bucket labels.
     *
     * @return string[]
     */
    public function get_labels(): array {
        return array_column($this->buckets, 'label');
    }

    /**
     * ISO dates of the buckets (Y-m-d, Y-m-d H:i for hours, Y-m for months), for exports.
     *
     * @return string[]
     */
    public function get_dates(): array {
        return array_column($this->buckets, 'date');
    }

    /**
     * Index of the bucket holding a time, or null outside the period.
     *
     * @param int $time
     * @return int|null
     */
    public function bucket_of(int $time): ?int {
        if ($time < $this->start || $time >= $this->end) {
            return null;
        }
        $lo = 0;
        $hi = count($this->starts) - 1;
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi + 1, 2);
            if ($this->starts[$mid] <= $time) {
                $lo = $mid;
            } else {
                $hi = $mid - 1;
            }
        }
        return $lo;
    }

    /**
     * SQL of the hour since the start of the period, to GROUP BY.
     *
     * The period start is an integer computed here, so it is written into the SQL (it appears in both SELECT and
     * GROUP BY, and Moodle does not allow a named parameter twice).
     *
     * @param string $column timestamp column
     * @param array $params unused, kept for call symmetry
     * @return string
     */
    public function hour_sql(string $column, array &$params): string {
        return "FLOOR(($column - " . (int)$this->start . ") / 3600)";
    }

    /**
     * Bucket of an hour returned by {@see self::hour_sql()}.
     *
     * @param int $hour
     * @return int|null
     */
    public function bucket_of_hour(int $hour): ?int {
        return $this->bucket_of($this->start + $hour * 3600);
    }

    /**
     * The period as text, e.g. "1 Oct 2026 – 16 Oct 2026".
     *
     * @param bool $previous the previous period instead
     * @return string
     */
    public function get_label(bool $previous = false): string {
        $format = get_string('strftimedate', 'langconfig');
        [$start, $end] = $previous ? [$this->prevstart, $this->prevend] : [$this->start, $this->end];
        return userdate($start, $format) . ' – ' . userdate($end - 1, $format);
    }

    /**
     * Parameters for URLs.
     *
     * @return array
     */
    public function to_params(): array {
        return $this->preset === 'custom' ? ['range' => 'custom', 'from' => $this->from, 'to' => $this->to] :
            ['range' => $this->preset];
    }
}

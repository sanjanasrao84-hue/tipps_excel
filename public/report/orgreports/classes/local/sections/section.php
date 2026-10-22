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

/**
 * A dashboard section: summary cards, charts, insights, notices about unavailable data and its tables.
 *
 * The payload is plain data so the same object feeds the web service, the CSV and the PDF statistical report.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class section {

    /** @var string[] section keys in display order. */
    const ALL = ['overview', 'sales', 'usage', 'activity'];

    /** @var metrics */
    protected metrics $metrics;

    /** @var array[] cards. */
    private array $cards = [];

    /** @var array[] charts. */
    private array $charts = [];

    /** @var string[] insights. */
    private array $insights = [];

    /** @var array[] notices. */
    private array $notices = [];

    /** @var array[] where each metric comes from: metric, status (measured, estimated, unavailable), source. */
    private array $availability = [];

    /** @var string[] chart key => layout size (full, wide, half, narrow); unlisted charts are half. */
    const SIZES = [];

    /** @var string[] chart keys in display order; unlisted charts follow in the order they were added. */
    const ORDER = [];

    /**
     * Constructor.
     *
     * @param metrics $metrics
     */
    public function __construct(metrics $metrics) {
        $this->metrics = $metrics;
    }

    /**
     * Section class for a key.
     *
     * @param string $key
     * @return string
     */
    public static function class_for(string $key): string {
        return __NAMESPACE__ . '\\' . $key;
    }

    /**
     * Whether the user may open the section.
     *
     * @param \report_orgreports\org_context $org
     * @return bool
     */
    public static function is_allowed(\report_orgreports\org_context $org): bool {
        return true;
    }

    /**
     * Tables of the section: key => title.
     *
     * @return string[]
     */
    public function get_tables(): array {
        return [];
    }

    /**
     * Fill cards, charts, insights and notices.
     */
    abstract protected function build(): void;

    /**
     * The section payload.
     *
     * @return array
     */
    public function get_data(): array {
        $this->build();
        $tables = [];
        foreach ($this->get_tables() as $key => $title) {
            $tables[] = ['key' => $key, 'title' => $title];
        }
        $range = $this->metrics->range;
        $order = array_flip(static::ORDER);
        $position = array_flip(array_keys($this->charts));
        uksort($this->charts, fn($a, $b) => [$order[$this->charts[$a]['key']] ?? 999, $position[$a]] <=>
            [$order[$this->charts[$b]['key']] ?? 999, $position[$b]]);
        $this->charts = array_values($this->charts);
        foreach ($this->charts as &$chart) {
            $chart['size'] = static::SIZES[$chart['key']] ?? 'half';
        }
        unset($chart);
        return [
            'organisation' => $this->metrics->org->name,
            'preset' => $range->preset,
            'from' => $range->from,
            'to' => $range->to,
            'granularitylabel' => get_string('granularity_' . $range->granularity, 'report_orgreports'),
            'period' => $range->get_label(),
            'previousperiod' => $range->get_label(true),
            'granularity' => $range->granularity,
            'cards' => $this->cards,
            'charts' => $this->charts,
            'insights' => $this->insights,
            'notices' => $this->notices,
            'availability' => $this->availability,
            'tables' => $tables,
        ];
    }

    /**
     * Add a summary card.
     *
     * @param string $key
     * @param string $icon Font Awesome classes
     * @param string $value formatted value
     * @param float|null $current number for the comparison
     * @param float|null $previous previous period number, null when no comparison can be made
     * @param string|null $previousformatted
     * @param string $hint how the value is calculated
     * @param bool $lowerisbetter
     */
    protected function add_card(string $key, string $icon, string $value, ?float $current = null, ?float $previous = null,
            ?string $previousformatted = null, string $hint = '', bool $lowerisbetter = false): void {
        $change = ($current !== null && $previous !== null) ? metrics::change($current, $previous) : null;
        $comparison = null;
        if ($previous !== null && $previousformatted !== null) {
            $comparison = $change === null ?
                get_string('comparenone', 'report_orgreports', $previousformatted) :
                get_string('comparechange', 'report_orgreports', ['change' => ($change > 0 ? '+' : '') .
                    format_float($change, 1) . '%', 'previous' => $previousformatted]);
        }
        $trend = $change === null || $change == 0 ? 'flat' : ($change > 0 ? 'up' : 'down');
        $good = $change === null || $change == 0 ? 'neutral' : (($change > 0) !== $lowerisbetter ? 'good' : 'bad');
        $this->cards[] = [
            'key' => $key,
            'label' => get_string('card_' . $key, 'report_orgreports'),
            'description' => get_string_manager()->string_exists('desc_' . $key, 'report_orgreports') ?
                get_string('desc_' . $key, 'report_orgreports') : '',
            'icon' => $icon,
            'value' => $value,
            'raw' => $current,
            'previous' => $previousformatted,
            'change' => $change,
            'comparison' => $comparison,
            'trend' => $trend,
            'tone' => $good,
            'hint' => $hint,
            'spark' => null,
            'unavailable' => $value === get_string('unavailable', 'report_orgreports'),
        ];
    }

    /**
     * Give a card a small trend line.
     *
     * @param string $key card key
     * @param array $series values per bucket
     */
    protected function add_spark(string $key, array $series): void {
        foreach ($this->cards as &$card) {
            if ($card['key'] === $key) {
                $card['spark'] = array_map(fn($v) => round((float)$v, 2), array_values($series));
            }
        }
        unset($card);
    }

    /**
     * Record where a metric comes from.
     *
     * @param string $metric
     * @param string $status measured, estimated or unavailable
     * @param string $source
     */
    protected function add_availability(string $metric, string $status, string $source): void {
        $this->availability[] = ['metric' => $metric, 'status' => $status,
            'statuslabel' => get_string('status_' . $status, 'report_orgreports'), 'source' => $source];
    }

    /**
     * Add a chart.
     *
     * @param string $key
     * @param string $type line, area, bar, stacked or doughnut
     * @param string[] $labels
     * @param array[] $datasets ['label' => string, 'data' => number[]]
     * @param string $unit count, hours, bytes, number or a currency code
     * @param string $summary text under the title
     */
    protected function add_chart(string $key, string $type, array $labels, array $datasets, string $unit = 'count',
            string $summary = ''): void {
        $empty = true;
        foreach ($datasets as $dataset) {
            if (array_filter($dataset['data'], fn($v) => (float)$v != 0)) {
                $empty = false;
            }
        }
        $this->charts[] = [
            'key' => $key,
            'title' => get_string('chart_' . $key, 'report_orgreports'),
            'type' => $type,
            'labels' => array_values($labels),
            'datasets' => array_map(fn($d) => ['label' => $d['label'],
                'data' => array_map(fn($v) => round((float)$v, 2), array_values($d['data']))], $datasets),
            'unit' => $unit,
            // Time series also carry the ISO date of every point, so exports hold real dates.
            'dates' => array_values($labels) === $this->metrics->range->get_labels() ? $this->metrics->range->get_dates() : null,
            'summary' => $summary,
            'empty' => $empty,
        ];
    }

    /**
     * Add an insight sentence.
     *
     * @param string $text
     */
    protected function add_insight(string $text): void {
        $this->insights[] = $text;
    }

    /**
     * Add a notice, e.g. that a metric is unavailable and why.
     *
     * @param string $type info, warning or unavailable
     * @param string $title
     * @param string $text
     */
    protected function add_notice(string $type, string $title, string $text): void {
        $this->notices[] = ['type' => $type, 'title' => $title, 'text' => $text];
    }

    /**
     * Insight about a change, only when it can be calculated.
     *
     * @param string $what
     * @param float $current
     * @param float $previous
     */
    protected function insight_change(string $what, float $current, float $previous): void {
        $change = metrics::change($current, $previous);
        if ($change === null) {
            return;
        }
        $key = $change > 0 ? 'insight_up' : ($change < 0 ? 'insight_down' : 'insight_same');
        $this->add_insight(get_string($key, 'report_orgreports', ['what' => $what,
            'change' => format_float(abs($change), 1)]));
    }

    /**
     * Number with thousands separators.
     *
     * @param float $value
     * @param int $decimals
     * @return string
     */
    public static function number(float $value, int $decimals = 0): string {
        return format_float($value, $decimals, true);
    }

    /**
     * Amounts per currency.
     *
     * @param float[] $totals
     * @return string
     */
    public static function money(array $totals): string {
        if (!$totals) {
            return get_string('nopayments', 'report_orgreports');
        }
        ksort($totals);
        $parts = [];
        foreach ($totals as $currency => $amount) {
            $parts[] = \core_payment\helper::get_cost_as_string((float)$amount, (string)$currency);
        }
        return implode(' · ', $parts);
    }

    /**
     * Seconds as hours and minutes.
     *
     * @param int $seconds
     * @return string
     */
    public static function duration(int $seconds): string {
        if ($seconds < HOURSECS) {
            return get_string('minutesvalue', 'report_orgreports', self::number(round($seconds / MINSECS)));
        }
        return get_string('hoursvalue', 'report_orgreports', self::number($seconds / HOURSECS, 1));
    }

    /**
     * Single currency of two totals, or null when a comparison is not meaningful (none or several currencies).
     *
     * @param array $current
     * @param array $previous
     * @return string|null
     */
    protected static function single_currency(array $current, array $previous): ?string {
        $currencies = array_unique(array_merge(array_keys($current), array_keys($previous)));
        return count($currencies) === 1 ? reset($currencies) : null;
    }
}

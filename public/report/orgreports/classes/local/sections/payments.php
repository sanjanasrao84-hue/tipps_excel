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
use report_orgreports\local\payment_query;
use report_orgreports\local\payment_source;

/**
 * Payment Reports: summary cards and charts of the filtered transactions.
 *
 * Counts and amounts are kept apart: "Total transactions" counts attempts, "Successful transaction amount" sums only
 * completed payments, and the net collected amount is that minus the refunds of those payments.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payments extends section {

    /** @var string[] chart sizes. */
    const SIZES = ['volume' => 'wide', 'pstatusdistribution' => 'narrow', 'amounttrend' => 'half',
        'successvsfailed' => 'half'];

    /** @var payment_query */
    private payment_query $query;

    /** @var string|null currency of the report, for amounts that are zero. */
    private ?string $currency = null;

    /**
     * Constructor.
     *
     * @param metrics $metrics
     * @param payment_query $query
     */
    public function __construct(metrics $metrics, payment_query $query) {
        parent::__construct($metrics);
        $this->query = $query;
    }

    public static function is_allowed(\report_orgreports\org_context $org): bool {
        return $org->cansales;
    }

    public function get_tables(): array {
        return ['payments' => get_string('table_payments', 'report_orgreports')];
    }

    /**
     * Totals (and per-bucket series for the current period) of the filtered transactions between two times.
     *
     * @param int $start
     * @param int $end
     * @param bool $series
     * @return array
     */
    private function aggregate(int $start, int $end, bool $series): array {
        global $DB;
        $statuses = [payment_source::COMPLETED, payment_source::PENDING, payment_source::INITIATED, payment_source::FAILED];
        $zero = $series ? $this->metrics->zeros() : [];
        $out = [
            'count' => array_fill_keys($statuses, 0),
            'amount' => [], 'success' => [], 'refunded' => [], 'refundcount' => 0, 'all' => 0,
            'series' => array_fill_keys($statuses, $zero),
            'amountseries' => [], 'successseries' => [],
        ];
        [$from, $where, $params] = $this->query->sql($start, $end);
        $rs = $DB->get_recordset_sql("SELECT " . payment_query::select_sql() . " FROM $from WHERE $where", $params);
        foreach ($rs as $r) {
            $status = $r->status;
            $cur = $r->currency;
            $amount = (float)$r->amount;
            $out['all']++;
            $out['count'][$status] = ($out['count'][$status] ?? 0) + 1;
            $out['amount'][$cur] = ($out['amount'][$cur] ?? 0) + $amount;
            if ($status === payment_source::COMPLETED) {
                $out['success'][$cur] = ($out['success'][$cur] ?? 0) + $amount;
                if ((float)$r->refunded > 0) {
                    $out['refunded'][$cur] = ($out['refunded'][$cur] ?? 0) + (float)$r->refunded;
                    $out['refundcount']++;
                }
            }
            if ($series && ($b = $this->metrics->range->bucket_of((int)$r->timecreated)) !== null) {
                $out['series'][$status][$b]++;
                $out['amountseries'][$cur] ??= $zero;
                $out['amountseries'][$cur][$b] += $amount;
                $out['successseries'][$cur] ??= $zero;
                if ($status === payment_source::COMPLETED) {
                    $out['successseries'][$cur][$b] += $amount;
                }
            }
        }
        $rs->close();
        return $out;
    }

    /**
     * Card value and comparison for an amount per currency.
     *
     * @param string $key
     * @param string $icon
     * @param array $now
     * @param array $prev
     * @param string $hint
     * @param bool $lowerisbetter
     */
    private function money_card(string $key, string $icon, array $now, array $prev, string $hint,
            bool $lowerisbetter = false): void {
        $currency = self::single_currency($now, $prev) ?? (!$now && !$prev ? $this->currency : null);
        $this->add_card($key, $icon, $now ? self::money($now) : self::money_zero($currency),
            $currency ? ($now[$currency] ?? 0) : null, $currency ? ($prev[$currency] ?? 0) : null,
            $currency ? self::money_zero($currency, $prev) : null, $hint, $lowerisbetter);
    }

    /**
     * Amount text, "0" in the currency when there is nothing.
     *
     * @param string|null $currency
     * @param array $totals
     * @return string
     */
    private static function money_zero(?string $currency, array $totals = []): string {
        if ($totals) {
            return self::money($totals);
        }
        return $currency ? \core_payment\helper::get_cost_as_string(0, $currency) : '0';
    }

    protected function build(): void {
        $m = $this->metrics;
        $range = $m->range;
        $now = $this->aggregate($range->start, $range->end, true);
        $prev = $this->aggregate($range->prevstart, $range->prevend, false);
        $c = $now['count'];
        $p = $prev['count'];
        // Zero amounts are shown in the currency the organisation's transactions use, when there is only one.
        $currencies = array_unique(array_merge(array_keys($now['amount']), array_keys($prev['amount'])));
        $this->currency = count($currencies) === 1 ? reset($currencies) : null;

        $this->add_card('ptotal', 'fa-solid fa-list-ol', self::number($now['all']), $now['all'], $prev['all'],
            self::number($prev['all']), get_string('hint_ptotal', 'report_orgreports'));
        $this->add_card('psuccess', 'fa-solid fa-circle-check', self::number($c['completed']), $c['completed'],
            $p['completed'], self::number($p['completed']), get_string('hint_psuccess', 'report_orgreports'));
        $this->money_card('pamount', 'fa-solid fa-coins', $now['amount'], $prev['amount'],
            get_string('hint_pamount', 'report_orgreports'));
        $this->money_card('psuccessamount', 'fa-solid fa-wallet', $now['success'], $prev['success'],
            get_string('hint_psuccessamount', 'report_orgreports'));
        $this->add_card('ppending', 'fa-solid fa-hourglass-half', self::number($c['pending']), $c['pending'],
            $p['pending'], self::number($p['pending']), get_string('hint_ppending', 'report_orgreports'), true);
        $this->add_card('pfailed', 'fa-solid fa-circle-xmark', self::number($c['failed']), $c['failed'], $p['failed'],
            self::number($p['failed']), get_string('hint_pfailed', 'report_orgreports'), true);
        $this->add_card('pabandoned', 'fa-solid fa-cart-arrow-down', self::number($c['initiated']), $c['initiated'],
            $p['initiated'], self::number($p['initiated']), get_string('hint_pabandoned', 'report_orgreports'), true);
        if (payment_query::has_refunds()) {
            $this->money_card('prefunded', 'fa-solid fa-rotate-left', $now['refunded'], $prev['refunded'],
                get_string('hint_prefunded', 'report_orgreports', $now['refundcount']), true);
            $net = [];
            $prevnet = [];
            foreach ($now['success'] as $cur => $amount) {
                $net[$cur] = $amount - ($now['refunded'][$cur] ?? 0);
            }
            foreach ($prev['success'] as $cur => $amount) {
                $prevnet[$cur] = $amount - ($prev['refunded'][$cur] ?? 0);
            }
            $this->money_card('pnet', 'fa-solid fa-scale-balanced', $net, $prevnet,
                get_string('hint_pnet', 'report_orgreports'));
        }

        foreach (['ptotal' => null, 'psuccess' => 'completed', 'ppending' => 'pending', 'pfailed' => 'failed',
                'pabandoned' => 'initiated'] as $card => $status) {
            $series = $status ? $now['series'][$status] : array_map(fn(...$v) => array_sum($v), ...array_values($now['series']));
            $this->add_spark($card, $series);
        }
        if (count($now['successseries']) === 1) {
            $this->add_spark('psuccessamount', reset($now['successseries']));
            $this->add_spark('pamount', reset($now['amountseries']));
        }

        // Charts.
        $labels = $range->get_labels();
        $names = [
            'completed' => get_string('pstatus_completed', 'report_orgreports'),
            'pending' => get_string('pstatus_pending', 'report_orgreports'),
            'initiated' => get_string('pstatus_initiated', 'report_orgreports'),
            'failed' => get_string('pstatus_failed', 'report_orgreports'),
        ];
        $this->add_chart('volume', 'stacked', $labels, array_map(fn($s) => ['label' => $names[$s],
            'data' => $now['series'][$s]], array_keys($names)), 'count',
            get_string('ptotalsummary', 'report_orgreports', self::number($now['all'])));

        // Distribution: refunded payments are their own slice, so the slices add up to all transactions.
        $refundedcount = $now['refundcount'];
        $distlabels = [get_string('pstatus_completed', 'report_orgreports')];
        $distvalues = [$c['completed'] - $refundedcount];
        if (payment_query::has_refunds()) {
            $distlabels[] = get_string('pstatus_refunded', 'report_orgreports');
            $distvalues[] = $refundedcount;
        }
        foreach (['pending', 'initiated', 'failed'] as $s) {
            $distlabels[] = $names[$s];
            $distvalues[] = $c[$s];
        }
        $this->add_chart('pstatusdistribution', 'doughnut', $distlabels, [['label' =>
            get_string('card_ptotal', 'report_orgreports'), 'data' => $distvalues]], 'count');

        $currencies = array_keys($now['amountseries']);
        $amountsets = [];
        foreach ($currencies as $cur) {
            $amountsets[] = ['label' => get_string('successfulin', 'report_orgreports', $cur),
                'data' => $now['successseries'][$cur]];
            $amountsets[] = ['label' => get_string('attemptedin', 'report_orgreports', $cur),
                'data' => $now['amountseries'][$cur]];
        }
        $this->add_chart('amounttrend', 'line', $labels, $amountsets ?: [['label' =>
            get_string('card_psuccessamount', 'report_orgreports'), 'data' => $m->zeros()]],
            count($currencies) === 1 ? $currencies[0] : 'number', self::money($now['success']));
        $unsuccessful = array_map(fn($a, $b) => $a + $b, $now['series']['failed'], $now['series']['initiated']);
        $this->add_chart('successvsfailed', 'line', $labels, [
            ['label' => get_string('pstatus_completed', 'report_orgreports'), 'data' => $now['series']['completed']],
            ['label' => get_string('unsuccessful', 'report_orgreports'), 'data' => $unsuccessful],
        ], 'count');

        // Insights only from values that can be compared.
        $currency = self::single_currency($now['success'], $prev['success']);
        if ($currency) {
            $this->insight_change(get_string('card_psuccessamount', 'report_orgreports'), $now['success'][$currency] ?? 0,
                $prev['success'][$currency] ?? 0);
        }
        if ($now['all']) {
            $this->add_insight(get_string('insight_psuccessrate', 'report_orgreports', [
                'rate' => self::number($c['completed'] / $now['all'] * 100, 1), 'success' => self::number($c['completed']),
                'total' => self::number($now['all'])]));
        }
        if (count($now['amount']) > 1) {
            $this->add_insight(get_string('insight_currencies', 'report_orgreports'));
        }

        // How the figures are calculated, and what the data cannot tell.
        $s = fn($k, $a = null) => get_string($k, 'report_orgreports', $a);
        $this->add_notice('info', $s('pnotice_rules'), $s('pnotice_rules_desc'));
        $this->add_availability($s('card_psuccess'), 'measured', $s('pavail_success'));
        $this->add_availability($s('card_ppending') . ' / ' . $s('card_pfailed'), 'measured', $s('pavail_attempts'));
        $this->add_availability($s('card_pabandoned'), 'measured', $s('pavail_abandoned'));
        $this->add_availability($s('pavail_cancelled'), 'unavailable', $s('pavail_cancelled_desc'));
        if (payment_query::has_refunds()) {
            $this->add_availability($s('card_prefunded'), 'measured', $s('pavail_refunds'));
            $this->add_availability($s('pavail_partial'), 'unavailable', $s('pavail_partial_desc'));
        } else {
            $this->add_availability($s('card_prefunded'), 'unavailable', $s('pavail_norefunds'));
        }
        $this->add_availability($s('pavail_webhooks'), 'measured', $s('pavail_webhooks_desc'));
        if (!$m->org->is_all()) {
            $this->add_notice('info', $s('notice_siteplans'), $s('notice_siteplans_desc'));
        }
    }
}

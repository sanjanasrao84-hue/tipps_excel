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

use report_orgreports\local\payment_source;

/**
 * Sales: revenue from completed payments, payment attempts by status, refunds and sales per course or plan.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sales extends section {

    /** @var string[] chart sizes. */
    const SIZES = ['cumulativerevenue' => 'wide', 'statusdistribution' => 'narrow', 'attempts' => 'wide',
        'gateways' => 'narrow', 'itemsales' => 'full'];

    public static function is_allowed(\report_orgreports\org_context $org): bool {
        return $org->cansales;
    }

    public function get_tables(): array {
        $tables = [
            'salesbydate' => get_string('table_salesbydate', 'report_orgreports'),
            'itemsales' => get_string('table_itemsales', 'report_orgreports'),
        ];
        if ($this->metrics->org->is_all()) {
            $tables['salesbyorg'] = get_string('table_salesbyorg', 'report_orgreports');
        }
        $tables['transactions'] = get_string('table_transactions', 'report_orgreports');
        return $tables;
    }

    protected function build(): void {
        global $DB;
        $m = $this->metrics;
        $labels = $m->range->get_labels();
        $revenue = $m->revenue();
        $payments = $m->payments();
        [$series, $totals, $prev] = $m->attempts();
        $currency = self::single_currency($revenue['total'], $revenue['prev']);

        $this->add_card('revenue', 'fa-solid fa-wallet', self::money($revenue['total']),
            $currency ? ($revenue['total'][$currency] ?? 0) : null, $currency ? ($revenue['prev'][$currency] ?? 0) : null,
            $currency ? self::money($revenue['prev']) : null, get_string('hint_revenue', 'report_orgreports'));
        $this->add_card('completed', 'fa-solid fa-circle-check', self::number($payments['total']), $payments['total'],
            $payments['prev'], self::number($payments['prev']), get_string('hint_transactions', 'report_orgreports'));
        // Subscriptions (local_lmssub): purchases are completed plan payments, paid subscriptions are live now.
        if (payment_source::has_plans()) {
            [$from, $where, $params] = payment_source::payments($m->org, 0, PHP_INT_MAX);
            $purchases = $m->count('p.timecreated', $from, "$where AND p.component = 'local_lmssub'", $params);
            $this->add_card('subpurchases', 'fa-solid fa-bag-shopping', self::number($purchases['total']),
                $purchases['total'], $purchases['prev'], self::number($purchases['prev']),
                get_string('hint_subpurchases', 'report_orgreports'));
            $this->add_spark('subpurchases', $purchases['series']);
            [$plansql, $planparams] = $DB->get_in_or_equal($m->org->get_plan_ids(), SQL_PARAMS_NAMED, 'psp');
            $now = time();
            $paidsubs = $DB->count_records_sql("SELECT COUNT(1) FROM {local_lmssub_sub} s JOIN {user} u ON u.id = s.userid
                WHERE s.planid $plansql AND u.deleted = 0 AND s.source = 'payment' AND s.status IN ('active', 'grace')
                  AND s.timestart <= :n1 AND (s.timeend = 0 OR s.graceend > :n2 OR s.timeend > :n3)",
                $planparams + ['n1' => $now, 'n2' => $now, 'n3' => $now]);
            $this->add_card('paidsubs', 'fa-solid fa-id-badge', self::number($paidsubs), null, null, null,
                get_string('hint_paidsubs', 'report_orgreports'));
        }
        $this->add_card('pending', 'fa-solid fa-hourglass-half', self::number($totals['pending']), $totals['pending'],
            $prev['pending'], self::number($prev['pending']), get_string('hint_pending', 'report_orgreports'), true);
        $this->add_card('initiated', 'fa-solid fa-cart-arrow-down', self::number($totals['initiated']),
            $totals['initiated'], $prev['initiated'], self::number($prev['initiated']),
            get_string('hint_initiated', 'report_orgreports'), true);
        $this->add_card('failed', 'fa-solid fa-circle-xmark', self::number($totals['failed']), $totals['failed'],
            $prev['failed'], self::number($prev['failed']), get_string('hint_failed', 'report_orgreports'), true);

        $refunds = payment_source::refunds($m->org, $m->range->start, $m->range->end);
        if ($refunds !== null) {
            $prevrefunds = payment_source::refunds($m->org, $m->range->prevstart, $m->range->prevend);
            $this->add_card('refunds', 'fa-solid fa-rotate-left', self::number($refunds[0]) .
                ($refunds[1] ? ' · ' . self::money($refunds[1]) : ''), $refunds[0], $prevrefunds[0],
                self::number($prevrefunds[0]), get_string('hint_refunds', 'report_orgreports'), true);
        }
        $attempts = array_sum($totals);
        if ($attempts) {
            $rate = $totals['completed'] / $attempts * 100;
            $prevattempts = array_sum($prev);
            $prevrate = $prevattempts ? $prev['completed'] / $prevattempts * 100 : null;
            $this->add_card('successrate', 'fa-solid fa-chart-pie', self::number($rate, 1) . '%', $rate, $prevrate,
                $prevrate === null ? null : self::number($prevrate, 1) . '%', get_string('hint_successrate', 'report_orgreports'));
        }
        if ($currency && $payments['total']) {
            $aov = ($revenue['total'][$currency] ?? 0) / $payments['total'];
            $prevaov = $payments['prev'] ? ($revenue['prev'][$currency] ?? 0) / $payments['prev'] : null;
            $this->add_card('avgorder', 'fa-solid fa-scale-balanced', self::money([$currency => $aov]), $aov, $prevaov,
                $prevaov === null ? null : self::money([$currency => $prevaov]), get_string('hint_avgorder', 'report_orgreports'));
        }

        // Sparklines.
        $this->add_spark('revenue', count($revenue['series']) === 1 ? reset($revenue['series']) : $m->zeros());
        $this->add_spark('completed', $payments['series']);
        $this->add_spark('pending', $series['pending']);
        $this->add_spark('initiated', $series['initiated']);
        $this->add_spark('failed', $series['failed']);

        // Charts.
        $statuses = payment_source::get_statuses();
        $datasets = [];
        foreach ($revenue['series'] as $cur => $data) {
            $running = 0;
            $datasets[] = ['label' => get_string('revenuein', 'report_orgreports', $cur),
                'data' => array_map(function($v) use (&$running) {
                    return $running += $v;
                }, $data)];
        }
        $this->add_chart('cumulativerevenue', 'line', $labels, $datasets ?: [['label' =>
            get_string('card_revenue', 'report_orgreports'), 'data' => $m->zeros()]],
            count($revenue['series']) === 1 ? array_key_first($revenue['series']) : 'number', self::money($revenue['total']));
        $this->add_chart('statusdistribution', 'doughnut', array_values($statuses), [['label' =>
            get_string('col_transactions', 'report_orgreports'), 'data' => array_values($totals)]], 'count',
            get_string('totalvalue', 'report_orgreports', self::number($attempts)));

        $this->add_chart('attempts', 'stacked', $labels, array_map(fn($s) => ['label' => $statuses[$s],
            'data' => $series[$s]], array_keys($statuses)), 'count',
            get_string('totalvalue', 'report_orgreports', self::number($attempts)));
        // Gateways by number of completed payments.
        [$from, $where, $params] = payment_source::payments($m->org, $m->range->start, $m->range->end);
        $gateways = $DB->get_records_sql_menu("SELECT p.gateway, COUNT(1) FROM $from WHERE $where GROUP BY p.gateway",
            $params);
        $names = array_map(fn($g) => get_string_manager()->string_exists('pluginname', 'paygw_' . $g) ?
            get_string('pluginname', 'paygw_' . $g) : $g, array_keys($gateways));
        $this->add_chart('gateways', 'doughnut', $names, [['label' => get_string('card_completed', 'report_orgreports'),
            'data' => array_values($gateways)]], 'count');

        // Top items by number of sales.
        $items = (new \report_orgreports\local\tables\itemsales($m, new \report_orgreports\local\tables\table_request()))
            ->get_items();
        usort($items, fn($a, $b) => $b['sales'] <=> $a['sales']);
        $items = array_slice($items, 0, 10);
        $itemsets = [['label' => get_string('col_sales', 'report_orgreports'), 'data' => array_column($items, 'sales')]];
        $this->add_chart('itemsales', 'hbar', array_column($items, 'name'), $itemsets, 'count');

        if ($currency) {
            $this->insight_change(get_string('card_revenue', 'report_orgreports'), $revenue['total'][$currency] ?? 0,
                $revenue['prev'][$currency] ?? 0);
        } else if (count($revenue['total']) > 1) {
            $this->add_insight(get_string('insight_currencies', 'report_orgreports'));
        }
        $this->insight_change(get_string('card_completed', 'report_orgreports'), $payments['total'], $payments['prev']);
        if ($items && $items[0]['sales']) {
            $this->add_insight(get_string('insight_topitem', 'report_orgreports', ['name' => format_string($items[0]['name']),
                'sales' => self::number($items[0]['sales'])]));
        }
        $this->add_notice('info', get_string('notice_salessource', 'report_orgreports'),
            get_string('notice_salessource_desc', 'report_orgreports'));
        if ($refunds === null || !$refunds[0]) {
            $this->add_notice('unavailable', get_string('notice_refunds', 'report_orgreports'),
                get_string('notice_refunds_desc', 'report_orgreports'));
        }
        if (!$m->org->is_all()) {
            $this->add_notice('info', get_string('notice_siteplans', 'report_orgreports'),
                get_string('notice_siteplans_desc', 'report_orgreports'));
        }
    }
}

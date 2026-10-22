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

/**
 * Overview: headline figures and trends.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends section {

    /** @var string[] chart sizes. */
    const SIZES = ['revenue' => 'wide', 'activepaid' => 'narrow'];

    /** @var string[] chart order. */
    const ORDER = ['revenue', 'activepaid', 'signups', 'enrolments', 'activeusers', 'learningtime'];

    protected function build(): void {
        $m = $this->metrics;
        $org = $m->org;
        $labels = $m->range->get_labels();

        if ($org->cansales) {
            $revenue = $m->revenue();
            $currency = self::single_currency($revenue['total'], $revenue['prev']);
            $this->add_card('revenue', 'fa-solid fa-wallet', self::money($revenue['total']),
                $currency ? ($revenue['total'][$currency] ?? 0) : null, $currency ? ($revenue['prev'][$currency] ?? 0) : null,
                $currency ? self::money($revenue['prev']) : null, get_string('hint_revenue', 'report_orgreports'));
            $payments = $m->payments();
            $this->add_card('transactions', 'fa-solid fa-receipt', self::number($payments['total']), $payments['total'],
                $payments['prev'], self::number($payments['prev']), get_string('hint_transactions', 'report_orgreports'));
        }
        $enrolments = $m->enrolments();
        $this->add_card('enrolments', 'fa-solid fa-user-plus', self::number($enrolments['total']), $enrolments['total'],
            $enrolments['prev'], self::number($enrolments['prev']), get_string('hint_enrolments', 'report_orgreports'));
        $signups = $m->signups();
        $this->add_card('signups', 'fa-solid fa-id-card', self::number($signups['total']), $signups['total'],
            $signups['prev'], self::number($signups['prev']), get_string('hint_signups', 'report_orgreports'));
        $active = $m->active_users();
        $this->add_card('activeusers', 'fa-solid fa-user-check', self::number($active['total']), $active['total'],
            $active['prev'], self::number($active['prev']), get_string('hint_activeusers', 'report_orgreports'));
        if ($org->cansales) {
            $paid = $m->paid_users();
            $this->add_card('paidusers', 'fa-solid fa-crown', self::number($paid['total']), $paid['total'], $paid['prev'],
                self::number($paid['prev']), get_string('hint_paidusers', 'report_orgreports'));
        }
        $learning = $m->learning_time();
        $this->add_card('learningtime', 'fa-solid fa-clock', self::duration($learning['total']), $learning['total'],
            $learning['prev'], self::duration($learning['prev']), get_string('hint_learningtime', 'report_orgreports'));

        // Sparklines on the cards.
        if ($org->cansales) {
            $this->add_spark('revenue', count($revenue['series']) === 1 ? reset($revenue['series']) : $m->zeros());
            $this->add_spark('transactions', $payments['series']);
            $this->add_spark('paidusers', $paid['series']);
        }
        $this->add_spark('enrolments', $enrolments['series']);
        $this->add_spark('signups', $signups['series']);
        $this->add_spark('activeusers', $active['series']);
        $this->add_spark('learningtime', $learning['series']);

        // Charts: a wide trend next to a doughnut, then pairs.
        if ($org->cansales) {
            $datasets = [];
            foreach ($revenue['series'] as $cur => $data) {
                $datasets[] = ['label' => get_string('revenuein', 'report_orgreports', $cur), 'data' => $data];
            }
            $unit = count($revenue['series']) === 1 ? array_key_first($revenue['series']) : 'number';
            $this->add_chart('revenue', 'area', $labels, $datasets ?: [['label' => get_string('card_revenue',
                'report_orgreports'), 'data' => $m->zeros()]], $unit, self::money($revenue['total']));
            $paidactive = count(array_intersect($paid['ids'], $active['ids']));
            $this->add_chart('activepaid', 'doughnut', [
                get_string('paidandactive', 'report_orgreports'),
                get_string('activenotpaid', 'report_orgreports'),
                get_string('paidnotactive', 'report_orgreports'),
            ], [['label' => get_string('users'), 'data' => [$paidactive, $active['total'] - $paidactive,
                $paid['total'] - $paidactive]]], 'count', get_string('activepaidsummary', 'report_orgreports',
                ['active' => self::number($active['total']), 'paid' => self::number($paid['total'])]));
        }
        $this->add_chart('activeusers', 'area', $labels, [['label' => get_string('card_activeusers', 'report_orgreports'),
            'data' => $active['series']]], 'count',
            get_string('totalvalue', 'report_orgreports', self::number($active['total'])));
        $this->add_chart('learningtime', 'area', $labels, [['label' => get_string('hours', 'report_orgreports'),
            'data' => array_map(fn($s) => $s / HOURSECS, $learning['series'])]], 'hours',
            self::duration($learning['total']));
        $this->add_chart('enrolments', 'bar', $labels, [['label' => get_string('card_enrolments', 'report_orgreports'),
            'data' => $enrolments['series']]], 'count',
            get_string('totalvalue', 'report_orgreports', self::number($enrolments['total'])));
        $this->add_chart('signups', 'line', $labels, [['label' => get_string('card_signups', 'report_orgreports'),
            'data' => $signups['series']]], 'count',
            get_string('totalvalue', 'report_orgreports', self::number($signups['total'])));

        // Insights, only from values that can be compared.
        if ($org->cansales && $currency) {
            $this->insight_change(get_string('card_revenue', 'report_orgreports'), $revenue['total'][$currency] ?? 0,
                $revenue['prev'][$currency] ?? 0);
        }
        $this->insight_change(get_string('card_enrolments', 'report_orgreports'), $enrolments['total'], $enrolments['prev']);
        $this->insight_change(get_string('card_activeusers', 'report_orgreports'), $active['total'], $active['prev']);
        $this->insight_change(get_string('card_learningtime', 'report_orgreports'), $learning['total'], $learning['prev']);
        if ($org->cansales && $active['total'] > 0) {
            $this->add_insight(get_string('insight_paidshare', 'report_orgreports',
                format_float(count(array_intersect($paid['ids'], $active['ids'])) / $active['total'] * 100, 1)));
        }
        if (!$org->cansales) {
            $this->add_notice('info', get_string('salesrestricted', 'report_orgreports'),
                get_string('salesrestricted_desc', 'report_orgreports'));
        }
    }
}

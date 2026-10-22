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
use report_orgreports\local\tables\courseactivity;
use report_orgreports\local\tables\table_request;

/**
 * School activity: learners, participation, enrolments, completions, learner and teacher activity.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity extends section {

    /** @var string[] chart sizes. */
    const SIZES = ['activitytrend' => 'wide', 'engagement' => 'narrow', 'participation' => 'wide',
        'enrolmentdistribution' => 'narrow', 'completions' => 'full'];

    /** @var string[] chart order. */
    const ORDER = ['activitytrend', 'engagement', 'participation', 'enrolmentdistribution', 'completions'];

    public function get_tables(): array {
        $tables = [];
        if ($this->metrics->org->is_all()) {
            $tables['orgactivity'] = get_string('table_orgactivity', 'report_orgreports');
        }
        $tables['courseactivity'] = get_string('table_courseactivity', 'report_orgreports');
        $tables['activitylog'] = get_string('table_activitylog', 'report_orgreports');
        return $tables;
    }

    protected function build(): void {
        global $DB;
        $m = $this->metrics;
        $org = $m->org;
        $labels = $m->range->get_labels();
        $stats = (new courseactivity($m, new table_request()))->get_stats();

        // Learners enrolled now, distinct across courses.
        $currentids = $m->current_learner_ids();
        $learners = count($currentids);
        $this->add_card('totallearners', 'fa-solid fa-user-graduate', self::number($learners), null, null, null,
            get_string('hint_totallearners', 'report_orgreports'));

        $activelearners = $m->active_learners();
        $this->add_card('activelearners', 'fa-solid fa-user-check', self::number($activelearners['total']),
            $activelearners['total'], $activelearners['prev'], self::number($activelearners['prev']),
            get_string('hint_activelearners', 'report_orgreports'));

        $signups = $m->signups();
        $this->add_card('registrations', 'fa-solid fa-id-card', self::number($signups['total']), $signups['total'],
            $signups['prev'], self::number($signups['prev']), get_string('hint_signups', 'report_orgreports'));
        $enrolments = $m->enrolments();
        $this->add_card('enrolments', 'fa-solid fa-user-plus', self::number($enrolments['total']), $enrolments['total'],
            $enrolments['prev'], self::number($enrolments['prev']), get_string('hint_enrolments', 'report_orgreports'));
        $completions = $m->completions();
        $this->add_card('completions', 'fa-solid fa-award', self::number($completions['total']), $completions['total'],
            $completions['prev'], self::number($completions['prev']), get_string('hint_completions', 'report_orgreports'));
        $learnerevents = $m->role_events(false);
        $this->add_card('learnerevents', 'fa-solid fa-book-open-reader', self::number($learnerevents['total']),
            $learnerevents['total'], $learnerevents['prev'], self::number($learnerevents['prev']),
            get_string('hint_learnerevents', 'report_orgreports'));
        $teacherevents = $m->role_events(true);
        $this->add_card('teacheractions', 'fa-solid fa-chalkboard-user', self::number($teacherevents['total']),
            $teacherevents['total'], $teacherevents['prev'], self::number($teacherevents['prev']),
            get_string('hint_teacheractions', 'report_orgreports'));

        $learning = $m->learning_time();
        $prevactive = $activelearners['prev'];
        $avg = $activelearners['total'] ? (int)round($learning['total'] / $activelearners['total']) : 0;
        $prevavg = $prevactive ? (int)round($learning['prev'] / $prevactive) : null;
        $this->add_card('avglearning', 'fa-solid fa-hourglass-half', self::duration($avg), $avg, $prevavg,
            $prevavg === null ? null : self::duration($prevavg), get_string('hint_avglearning', 'report_orgreports'));

        // Sparklines.
        $this->add_spark('activelearners', $activelearners['series']);
        $this->add_spark('registrations', $signups['series']);
        $this->add_spark('enrolments', $enrolments['series']);
        $this->add_spark('completions', $completions['series']);
        $this->add_spark('learnerevents', $learnerevents['series']);
        $this->add_spark('teacheractions', $teacherevents['series']);

        // Charts.
        $this->add_chart('activitytrend', 'stacked', $labels, [
            ['label' => get_string('card_learnerevents', 'report_orgreports'), 'data' => $learnerevents['series']],
            ['label' => get_string('card_teacheractions', 'report_orgreports'), 'data' => $teacherevents['series']],
        ], 'count', get_string('totalvalue', 'report_orgreports',
            self::number($learnerevents['total'] + $teacherevents['total'])));
        $this->add_chart('completions', 'line', $labels, [['label' => get_string('card_completions', 'report_orgreports'),
            'data' => $completions['series']]], 'count',
            get_string('totalvalue', 'report_orgreports', self::number($completions['total'])));

        uasort($stats, fn($a, $b) => $b['learners'] <=> $a['learners']);
        $top = array_slice($stats, 0, 10);
        $names = array_map(fn($r) => format_string($r['course']), $top);
        $this->add_chart('participation', 'hbar', $names, [
            ['label' => get_string('col_learners', 'report_orgreports'), 'data' => array_column($top, 'learners')],
            ['label' => get_string('card_activelearners', 'report_orgreports'), 'data' => array_column($top, 'activelearners')],
        ], 'count');

        // Enrolment distribution: the eight largest courses and the rest together.
        $distribution = array_slice($stats, 0, 8);
        $labelsd = array_map(fn($r) => format_string($r['course']), $distribution);
        $values = array_column($distribution, 'learners');
        $rest = array_sum(array_column(array_slice($stats, 8), 'learners'));
        if ($rest) {
            $labelsd[] = get_string('othercourses', 'report_orgreports');
            $values[] = $rest;
        }
        $this->add_chart('enrolmentdistribution', 'doughnut', $labelsd, [['label' => get_string('col_learners',
            'report_orgreports'), 'data' => $values]], 'count');

        // Engagement of current learners: active in the period or not (distinct over all courses).
        if ($learners) {
            $engaged = count(array_intersect($currentids, $activelearners['ids']));
            $this->add_chart('engagement', 'doughnut', [get_string('engaged', 'report_orgreports'),
                get_string('notengaged', 'report_orgreports')], [['label' => get_string('col_learners', 'report_orgreports'),
                'data' => [$engaged, count($currentids) - $engaged]]], 'count',
                get_string('engagementsummary', 'report_orgreports', self::number($engaged / count($currentids) * 100, 1)));
            $this->add_insight(get_string('insight_engagement', 'report_orgreports',
                ['engaged' => self::number($engaged), 'total' => self::number(count($currentids))]));
        } else {
            $this->add_chart('engagement', 'doughnut', [], [['label' => '', 'data' => []]], 'count');
        }

        $this->insight_change(get_string('card_activelearners', 'report_orgreports'), $activelearners['total'],
            $activelearners['prev']);
        $this->insight_change(get_string('card_completions', 'report_orgreports'), $completions['total'],
            $completions['prev']);
        $this->insight_change(get_string('card_teacheractions', 'report_orgreports'), $teacherevents['total'],
            $teacherevents['prev']);
        $this->add_notice('info', get_string('notice_activitysource', 'report_orgreports'),
            get_string('notice_activitysource_desc', 'report_orgreports'));
    }
}

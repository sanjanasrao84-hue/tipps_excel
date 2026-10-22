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

/**
 * Subscription plans: every plan a learner can buy. The learner picks a plan and a duration, then pays on the next page.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_lmssub\access_engine;
use local_lmssub\plan_manager;
use local_lmssub\subscription_manager as subs;

$courseid = optional_param('courseid', 0, PARAM_INT);

require_login(null, false);
if (isguestuser()) {
    redirect(get_login_url());
}

$url = new moodle_url('/local/lmssub/index.php', $courseid ? ['courseid' => $courseid] : []);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
// The base layout has no blocks and no content width limit, so the plans use the whole screen.
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('subscriptionplans', 'local_lmssub'));
$PAGE->set_heading(get_string('subscriptionplans', 'local_lmssub'));

$course = $courseid ? $DB->get_record('course', ['id' => $courseid], 'id, fullname') : null;
$plans = plan_manager::get_purchasable_plans($course ? (int) $course->id : 0);
$types = plan_manager::get_types();
$dateformat = get_string('strftimedate');

// The learner's live subscriptions, shown above the plans.
$active = [];
[$insql, $params] = $DB->get_in_or_equal(subs::LIVE_STATUSES, SQL_PARAMS_NAMED);
$params['userid'] = $USER->id;
$livesubs = $DB->get_records_select('local_lmssub_sub', "userid = :userid AND status $insql", $params, 'timeend');
foreach ($livesubs as $sub) {
    $plan = plan_manager::get_plan((int) $sub->planid);
    $until = access_engine::effective_end($sub, $plan);
    if ($until > time()) {
        $active[] = [
            'name' => format_string($plan->name),
            'until' => userdate($until, $dateformat),
            'ingrace' => $sub->status === subs::STATUS_GRACE,
        ];
    }
}

$cards = [];
$typecounts = [];
foreach ($plans as $plan) {
    // The learner's current subscription to this plan, if any.
    $current = subs::find_current((int) $USER->id, (int) $plan->id);
    $until = $current ? access_engine::effective_end($current, $plan) : 0;
    $owned = $until > time();
    $expired = $current && !$owned;
    $isall = $plan->type === plan_manager::TYPE_ALLACCESS;
    $typecounts[$plan->type] = ($typecounts[$plan->type] ?? 0) + 1;

    // Durations, with the saving against the shortest duration's daily rate.
    $prices = array_values($plan->prices);
    usort($prices, fn($a, $b) => $a->durationdays <=> $b->durationdays);
    $baserate = $prices[0]->amount / max(1, $prices[0]->durationdays);
    $priceitems = [];
    $bestsaving = 0;
    foreach ($prices as $i => $price) {
        $days = max(1, (int) $price->durationdays);
        $saving = $i ? (int) floor((1 - ($price->amount / $days) / $baserate) * 100) : 0;
        $saving = $saving >= 5 ? $saving : 0;
        $bestsaving = max($bestsaving, $saving);
        $priceitems[] = [
            'id' => $price->id,
            'duration' => $days % 30 === 0 || $days === 365
                ? get_string($days === 30 ? 'duration_onemonth' : 'duration_months', 'local_lmssub', $days === 365 ? 12 : $days / 30)
                : plan_manager::duration_label($days),
            'amount' => plan_manager::format_amount((int) $price->amount, $price->currency),
            'permonth' => plan_manager::format_amount((int) round($price->amount * 30 / $days), $price->currency),
            // The monthly rate only adds information for durations longer than a month.
            'showpermonth' => $days > 31,
            'saving' => $saving,
            'best' => false,
            'checked' => $i === 0,
        ];
    }
    foreach ($priceitems as &$item) {
        $item['best'] = $bestsaving && $item['saving'] === $bestsaving;
    }
    unset($item);

    // What it opens: a few courses with their images, the rest by name.
    $courseids = array_values($plan->courseids);
    $shown = $isall ? 3 : 4;
    [$insql, $params] = $DB->get_in_or_equal(array_slice($courseids, 0, $shown + 30));
    $records = array_values($DB->get_records_select('course', "id $insql", $params, 'sortorder', 'id, fullname'));
    $courses = [];
    foreach (array_slice($records, 0, $shown) as $c) {
        $image = \core_course\external\course_summary_exporter::get_course_image($c);
        $courses[] = [
            'name' => format_string($c->fullname),
            'image' => $image ?: $OUTPUT->get_generated_image_for_id($c->id),
        ];
    }
    $more = array_map(fn($c) => format_string($c->fullname), array_slice($records, $shown));
    $extra = count($courseids) - $shown;

    // What the plan gives, from the settings the platform applies.
    $features = [get_string($isall ? 'opens_all_count' : 'opens_count', 'local_lmssub', count($courseids))];
    if ((int) $plan->gracedays > 0) {
        $features[] = get_string('feature_grace', 'local_lmssub', (int) $plan->gracedays);
    }
    if (count($priceitems) > 1) {
        $features[] = get_string('feature_durations', 'local_lmssub', count($priceitems));
    }

    $cards[] = [
        'id' => $plan->id,
        'name' => format_string($plan->name),
        'typekey' => $plan->type,
        'typelabel' => $types[$plan->type],
        'isallaccess' => $isall,
        'owned' => $owned,
        'ingrace' => $owned && $current->status === subs::STATUS_GRACE,
        'until' => $owned ? userdate($until, $dateformat) : '',
        'expired' => $expired,
        // An ended subscription no longer has an access end, so show when it actually ended:
        // its end date, or the moment it was ended early.
        'expiredon' => $expired ? userdate(min(max((int) $current->timeend, (int) $current->graceend),
            (int) $current->timemodified), $dateformat) : '',
        'features' => $features,
        'hasdescription' => trim((string) $plan->description) !== '',
        'description' => format_text($plan->description, FORMAT_PLAIN),
        'openslabel' => get_string($isall ? 'opens_all_count' : 'opens_count', 'local_lmssub', count($courseids)),
        'courses' => $courses,
        'hasmore' => $extra > 0,
        'more' => $more,
        'morelabel' => get_string('showmorecourses', 'local_lmssub', $extra),
        'prices' => $priceitems,
        'buttonlabel' => get_string($owned ? 'renewplan' : 'subscribenow', 'local_lmssub'),
        'buttonlabelaria' => get_string($owned ? 'renewplan_aria' : 'subscribenow_aria', 'local_lmssub', format_string($plan->name)),
    ];
}

// Filter pills, only when there is more than one kind of plan.
$filters = [['key' => 'all', 'label' => get_string('allplans', 'local_lmssub'), 'count' => count($cards), 'selected' => true]];
foreach ($typecounts as $type => $count) {
    $filters[] = ['key' => $type, 'label' => get_string('filter_' . $type, 'local_lmssub'), 'count' => $count,
        'selected' => false];
}

$context = [
    'intro' => get_string('plans_intro', 'local_lmssub'),
    'coursename' => $course ? format_string($course->fullname) : '',
    'showallurl' => $course ? (new moodle_url('/local/lmssub/index.php'))->out(false) : '',
    'myurl' => (new moodle_url('/local/lmssub/my.php'))->out(false),
    'dashboardurl' => (new moodle_url('/my/'))->out(false),
    'checkouturl' => (new moodle_url('/local/lmssub/checkout.php'))->out(false),
    // Keep the course the learner came from, so checkout can link back to this filtered list.
    'courseid' => $course ? $course->id : 0,
    'hasactive' => (bool) $active,
    'active' => $active,
    'showfilters' => count($typecounts) > 1,
    'filters' => $filters,
    'hasplans' => (bool) $cards,
    'plans' => $cards,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_lmssub/plans_page', $context);
echo $OUTPUT->footer();

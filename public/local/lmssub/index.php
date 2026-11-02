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
// A bundle the learner bought before, to tick its courses again when they renew it.
$renewbundle = optional_param('bundle', 0, PARAM_INT);

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

// Duration choices of a plan, with the saving against the shortest duration's daily rate.
$priceitems = function(array $prices): array {
    $prices = array_values(plan_manager::prices_by_duration($prices));
    $baserate = $prices[0]->amount / max(1, $prices[0]->durationdays);
    $items = [];
    $bestsaving = 0;
    foreach ($prices as $i => $price) {
        $days = max(1, (int) $price->durationdays);
        $saving = $i ? (int) floor((1 - ($price->amount / $days) / $baserate) * 100) : 0;
        $saving = $saving >= 5 ? $saving : 0;
        $bestsaving = max($bestsaving, $saving);
        $items[] = [
            'id' => $price->id,
            'days' => $days,
            'duration' => local_lmssub_duration_name($days),
            'amount' => plan_manager::format_amount((int) $price->amount, $price->currency),
            'permonth' => plan_manager::format_amount((int) round($price->amount * 30 / $days), $price->currency),
            // The monthly rate only adds information for durations longer than a month.
            'showpermonth' => $days > 31,
            'saving' => $saving,
            'best' => false,
            'checked' => $i === 0,
        ];
    }
    foreach ($items as &$item) {
        $item['best'] = $bestsaving && $item['saving'] === $bestsaving;
    }
    return $items;
};

/**
 * Name of a duration as shown on the plan cards.
 *
 * @param int $days
 * @return string
 */
function local_lmssub_duration_name(int $days): string {
    return $days % 30 === 0 || $days === 365
        ? get_string($days === 30 ? 'duration_onemonth' : 'duration_months', 'local_lmssub', $days === 365 ? 12 : $days / 30)
        : plan_manager::duration_label($days);
}

// The learner's subscription to a plan, for the status lines of the cards.
$planstatus = function(\stdClass $plan) use ($USER, $dateformat): array {
    $current = subs::find_current((int) $USER->id, (int) $plan->id);
    $until = $current ? access_engine::effective_end($current, $plan) : 0;
    $owned = $until > time();
    $expired = $current && !$owned;
    return [
        'owned' => $owned,
        'ingrace' => $owned && $current->status === subs::STATUS_GRACE,
        'until' => $owned ? userdate($until, $dateformat) : '',
        'expired' => $expired,
        // An ended subscription no longer has an access end, so show when it actually ended:
        // its end date, or the moment it was ended early.
        'expiredon' => $expired ? userdate(min(max((int) $current->timeend, (int) $current->graceend),
            (int) $current->timemodified), $dateformat) : '',
    ];
};

// Card 1, all in one: the first full access plan (covering the course, when the learner came from one).
$allaccess = null;
foreach ($plans as $plan) {
    if ($plan->type !== plan_manager::TYPE_ALLACCESS) {
        continue;
    }
    $courseids = array_values($plan->courseids);
    [$insql, $params] = $DB->get_in_or_equal(array_slice($courseids, 0, 33));
    $records = array_values($DB->get_records_select('course', "id $insql", $params, 'sortorder', 'id, fullname'));
    $courses = [];
    foreach (array_slice($records, 0, 3) as $c) {
        $image = \core_course\external\course_summary_exporter::get_course_image($c);
        $courses[] = ['name' => format_string($c->fullname), 'image' => $image ?: $OUTPUT->get_generated_image_for_id($c->id)];
    }
    $extra = count($courseids) - 3;
    $items = $priceitems($plan->prices);
    $features = [get_string('opens_all_count', 'local_lmssub', count($courseids))];
    if ((int) $plan->gracedays > 0) {
        $features[] = get_string('feature_grace', 'local_lmssub', (int) $plan->gracedays);
    }
    if (count($items) > 1) {
        $features[] = get_string('feature_durations', 'local_lmssub', count($items));
    }
    $status = $planstatus($plan);
    $allaccess = $status + [
        'id' => $plan->id,
        'name' => format_string($plan->name),
        'typelabel' => $types[$plan->type],
        'features' => $features,
        'hasdescription' => trim((string) $plan->description) !== '',
        'description' => format_text($plan->description, FORMAT_PLAIN),
        'courses' => $courses,
        'hasmore' => $extra > 0,
        'more' => array_map(fn($c) => format_string($c->fullname), array_slice($records, 3)),
        'morelabel' => get_string('showmorecourses', 'local_lmssub', $extra),
        'prices' => $items,
        'buttonlabel' => get_string($status['owned'] ? 'renewplan' : 'subscribenow', 'local_lmssub'),
        'buttonlabelaria' => get_string($status['owned'] ? 'renewplan_aria' : 'subscribenow_aria', 'local_lmssub',
            format_string($plan->name)),
    ];
    break;
}

// Cards 2 and 3: the courses sold on their own, picked for a bundle (check boxes) or alone (radio buttons).
$options = plan_manager::get_course_options();
// Courses ticked in the bundle card: the course the learner came from, or the bundle they are renewing.
$prechecked = $course && isset($options[$course->id]) ? [(int) $course->id] : [];
if ($renewbundle) {
    $renew = $DB->get_record('local_lmssub_plan', ['id' => $renewbundle], 'id, bundlekey');
    if ($renew && $renew->bundlekey) {
        $prechecked = array_map('intval', explode(',', $renew->bundlekey));
    }
}
$singleselected = $course && isset($options[$course->id]) ? (int) $course->id : (int) array_key_first($options);
$records = [];
if ($options) {
    [$insql, $params] = $DB->get_in_or_equal(array_keys($options));
    $records = $DB->get_records_select('course', "id $insql", $params, '', 'id, fullname');
}
$bundlecourses = [];
$singlecourses = [];
$singleheadline = null;
$bundledays = [];
$bundlecurrency = 'INR';
foreach ($options as $optioncourseid => $plan) {
    $c = $records[$optioncourseid];
    $image = \core_course\external\course_summary_exporter::get_course_image($c);
    $image = $image ?: $OUTPUT->get_generated_image_for_id($c->id);
    $bydays = plan_manager::prices_by_duration($plan->prices);
    $cheapest = reset($bydays);
    foreach ($bydays as $price) {
        $cheapest = $price->amount < $cheapest->amount ? $price : $cheapest;
    }
    $from = get_string('fromprice', 'local_lmssub', plan_manager::format_amount((int) $cheapest->amount, $cheapest->currency));
    $bundledays += array_fill_keys(array_keys($bydays), true);
    $bundlecurrency = $cheapest->currency;

    $bundlecourses[] = [
        'courseid' => $optioncourseid,
        'name' => format_string($c->fullname),
        'image' => $image,
        'from' => $from,
        'checked' => in_array($optioncourseid, $prechecked, true),
        'pricesjson' => json_encode(array_map(fn($p) => (int) $p->amount, $bydays)),
    ];

    $selected = $optioncourseid === $singleselected;
    $items = $priceitems($plan->prices);
    if ($selected) {
        $singleheadline = $items[0];
    } else {
        // Only the chosen course's durations are sent with the form.
        foreach ($items as &$item) {
            $item['checked'] = false;
            $item['disabled'] = true;
        }
        unset($item);
    }
    $singlecourses[] = $planstatus($plan) + [
        'courseid' => $optioncourseid,
        'planid' => $plan->id,
        'name' => format_string($c->fullname),
        'image' => $image,
        'from' => $from,
        'selected' => $selected,
        'prices' => $items,
    ];
}
ksort($bundledays);
$bundleenabled = isset(plan_manager::get_enabled_types()[plan_manager::TYPE_BUNDLE]) && count($options) >= 2;
$discount = plan_manager::bundle_discount();

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
    'hasplans' => $allaccess || $bundleenabled || $singlecourses,
    'allaccess' => $allaccess,
    'bundle' => $bundleenabled ? [
        'url' => (new moodle_url('/local/lmssub/bundle.php'))->out(false),
        'sesskey' => sesskey(),
        'courses' => $bundlecourses,
        'durations' => array_map(fn($days) => ['days' => $days, 'duration' => local_lmssub_duration_name($days)],
            array_keys($bundledays)),
        'hasdiscount' => $discount > 0,
        'discount' => $discount,
        // A formatted sample amount: the page shows bundle totals in exactly the format the server uses.
        'moneysample' => plan_manager::format_amount(123456789, $bundlecurrency),
    ] : null,
    'single' => $singlecourses ? ['courses' => $singlecourses, 'headline' => $singleheadline] : null,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_lmssub/plans_page', $context);
echo $OUTPUT->footer();

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

namespace local_lmssub\task;

use local_lmssub\plan_manager;
use local_lmssub\subscription_manager as subs;

/**
 * Moves ended subscriptions to grace or expired.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_expiry extends \core\task\scheduled_task {

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_processexpiry', 'local_lmssub');
    }

    /**
     * Runs the task.
     */
    public function execute() {
        global $DB;
        $now = time();

        // Active, period ended: grace if the plan has one, otherwise expired.
        $ended = $DB->get_records_select('local_lmssub_sub', 'status = :status AND timeend <= :now',
            ['status' => subs::STATUS_ACTIVE, 'now' => $now]);
        foreach ($ended as $sub) {
            $plan = plan_manager::get_plan((int) $sub->planid);
            if ($plan->gracedays > 0 && $sub->timeend + $plan->gracedays * DAYSECS > $now) {
                subs::start_grace($sub, (int) $plan->gracedays);
                mtrace("Subscription {$sub->id}: grace period started");
            } else {
                subs::expire($sub);
                mtrace("Subscription {$sub->id}: expired");
            }
        }

        // Grace over, still unpaid.
        $ended = $DB->get_records_select('local_lmssub_sub', 'status = :status AND graceend <= :now',
            ['status' => subs::STATUS_GRACE, 'now' => $now]);
        foreach ($ended as $sub) {
            subs::expire($sub);
            mtrace("Subscription {$sub->id}: grace ended, expired");
        }

        // Cancelled, end date reached.
        $ended = $DB->get_records_select('local_lmssub_sub', 'status = :status AND timeend <= :now',
            ['status' => subs::STATUS_CANCELLED, 'now' => $now]);
        foreach ($ended as $sub) {
            subs::expire($sub);
            mtrace("Subscription {$sub->id}: cancelled period ended, expired");
        }
    }
}

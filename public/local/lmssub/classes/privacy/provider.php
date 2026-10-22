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

namespace local_lmssub\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider: describes the personal data stored.
 *
 * Export and deletion of this data are added with the payment stage, as orders and
 * subscriptions are financial records that may need to be kept.
 *
 * @package    local_lmssub
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {

    /**
     * Returns metadata about this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_lmssub_sub', [
            'userid' => 'privacy:metadata:sub:userid',
            'planid' => 'privacy:metadata:sub:planid',
            'status' => 'privacy:metadata:sub:status',
            'timestart' => 'privacy:metadata:sub:timestart',
            'timeend' => 'privacy:metadata:sub:timeend',
        ], 'privacy:metadata:sub');
        $collection->add_database_table('local_lmssub_order', [
            'userid' => 'privacy:metadata:order:userid',
            'amount' => 'privacy:metadata:order:amount',
            'timecreated' => 'privacy:metadata:order:timecreated',
        ], 'privacy:metadata:order');
        $collection->add_database_table('local_lmssub_event', [
            'userid' => 'privacy:metadata:event:userid',
            'actorid' => 'privacy:metadata:event:actorid',
            'reason' => 'privacy:metadata:event:reason',
        ], 'privacy:metadata:event');
        $collection->add_database_table('local_lmssub_override', [
            'userid' => 'privacy:metadata:override:userid',
            'reason' => 'privacy:metadata:override:reason',
        ], 'privacy:metadata:override');
        $collection->add_database_table('local_lmssub_device', [
            'userid' => 'privacy:metadata:device:userid',
            'useragent' => 'privacy:metadata:device:useragent',
            'ip' => 'privacy:metadata:device:ip',
        ], 'privacy:metadata:device');
        return $collection;
    }
}

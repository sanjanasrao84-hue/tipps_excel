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
 * Upgrade steps of the Online payment gateway.
 *
 * @package    paygw_lmspay
 * @copyright  2026 LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_paygw_lmspay_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026101401) {
        // Orders can now be paid through Razorpay.
        $table = new xmldb_table('paygw_lmspay_order');
        $fields = [
            new xmldb_field('provider', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'model', 'paymentid'),
            new xmldb_field('rp_orderid', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'provider'),
            new xmldb_field('rp_paymentid', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'rp_orderid'),
            new xmldb_field('rp_qrid', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'rp_paymentid'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $index = new xmldb_index('rp_paymentid', XMLDB_INDEX_UNIQUE, ['rp_paymentid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026101401, 'paygw', 'lmspay');
    }

    if ($oldversion < 2026101502) {
        // UPI payments straight to the account's UPI ID, approved by an admin.
        $table = new xmldb_table('paygw_lmspay_order');
        $fields = [
            new xmldb_field('utr', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'rp_qrid'),
            new xmldb_field('reviewedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'utr'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        foreach ([new xmldb_index('utr', XMLDB_INDEX_NOTUNIQUE, ['utr']),
                new xmldb_index('status', XMLDB_INDEX_NOTUNIQUE, ['status'])] as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        upgrade_plugin_savepoint(true, 2026101502, 'paygw', 'lmspay');
    }

    return true;
}

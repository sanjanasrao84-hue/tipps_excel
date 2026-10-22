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
 * Organisation reports dashboard: web services used by the dashboard and Payment Reports.
 *
 * @package    report_orgreports
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'report_orgreports_get_section' => [
        'classname' => \report_orgreports\external\get_section::class,
        'description' => 'Summary cards, charts and notices of one dashboard section.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'report_orgreports_get_table' => [
        'classname' => \report_orgreports\external\get_table::class,
        'description' => 'One page of a dashboard table.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'report_orgreports_get_payment_report' => [
        'classname' => \report_orgreports\external\get_payment_report::class,
        'description' => 'Summary cards, charts and filter options of the Payment Reports page.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'report_orgreports_get_payment_detail' => [
        'classname' => \report_orgreports\external\get_payment_detail::class,
        'description' => 'Everything recorded about one transaction of an organisation.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];

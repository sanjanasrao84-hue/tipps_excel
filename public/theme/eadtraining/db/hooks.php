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
 * Hooks
 *
 * @package   theme_eadtraining
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_html_attributes;
use core\hook\output\before_http_headers;
use core\hook\output\before_import_map_config;
use core\hook\navigation\primary_extend;
use theme_eadtraining\hook_callbacks;

defined('MOODLE_INTERNAL') || die;

$callbacks = [
    [
        "hook" => before_html_attributes::class,
        "callback" => [hook_callbacks::class, "before_html_attributes"],
    ],
    [
        "hook" => before_footer_html_generation::class,
        "callback" => [hook_callbacks::class, "before_footer_html_generation"],
    ],
    [
        "hook" => before_http_headers::class,
        "callback" => [hook_callbacks::class, "before_http_headers"],
    ],
    [
        "hook" => before_import_map_config::class,
        "callback" => [hook_callbacks::class, "before_import_map_config"],
    ],
    [
        // After the plugins have added their tabs (default priority 100), before core picks the active tab.
        "hook" => primary_extend::class,
        "callback" => [hook_callbacks::class, "primary_extend"],
        "priority" => 0,
    ],
];

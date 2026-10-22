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
 * Date range toolbar of the organisation reports dashboard: presets, From / To fields and their validation.
 *
 * The server checks the dates again; this gives the user an immediate, specific message.
 *
 * @module     report_orgreports/date_filter
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['report_orgreports/format'], function(F) {
    'use strict';

    /** @var {Number} longest custom range accepted by the server, in years. */
    var MAXYEARS = 10;

    /**
     * Parse Y-m-d into a UTC date, or null.
     *
     * @param {String} value
     * @returns {Date|null}
     */
    var parse = function(value) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!m) {
            return null;
        }
        var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
        return d.getUTCFullYear() === +m[1] && d.getUTCMonth() === +m[2] - 1 && d.getUTCDate() === +m[3] ? d : null;
    };

    return {
        /**
         * Validation message for a custom range, or '' when it is valid.
         *
         * @param {String} from
         * @param {String} to
         * @param {String} today Y-m-d in the user's timezone
         * @returns {String}
         */
        validate: function(from, to, today) {
            if (!from || !to) {
                return F.str('err_required');
            }
            var f = parse(from);
            var t = parse(to);
            if (!f || !t) {
                return F.str('err_invalid');
            }
            if (f > t) {
                return F.str('err_order');
            }
            if (today && to > today) {
                return F.str('err_future');
            }
            var limit = new Date(t.getTime());
            limit.setUTCFullYear(limit.getUTCFullYear() - MAXYEARS);
            if (f < limit) {
                return F.str('err_toolong', MAXYEARS);
            }
            return '';
        },

        /**
         * Mark the active preset.
         *
         * @param {HTMLElement} root
         * @param {String} range
         */
        setPreset: function(root, range) {
            root.querySelectorAll('[data-range]').forEach(function(button) {
                var on = button.dataset.range === range;
                button.classList.toggle('active', on);
                button.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        },

        /**
         * Show the dates a period resolved to.
         *
         * @param {HTMLElement} root
         * @param {String} from
         * @param {String} to
         */
        setDates: function(root, from, to) {
            var fromfield = root.querySelector('[data-field="from"]');
            var tofield = root.querySelector('[data-field="to"]');
            if (fromfield && from) {
                fromfield.value = from;
            }
            if (tofield && to) {
                tofield.value = to;
            }
        },

        /**
         * Show or clear the validation message.
         *
         * @param {HTMLElement} root
         * @param {String} message
         */
        showError: function(root, message) {
            var region = root.querySelector('[data-region="dateerror"]');
            region.textContent = message;
            region.classList.toggle('d-none', !message);
            root.querySelectorAll('[data-field]').forEach(function(field) {
                field.setAttribute('aria-invalid', message ? 'true' : 'false');
            });
        }
    };
});

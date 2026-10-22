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
 * Shared helpers of the organisation reports dashboard: page configuration, strings, escaping and number formats.
 *
 * @module     report_orgreports/format
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    var config = {locale: undefined, strings: {}};

    /**
     * A number format; falls back to the browser language when the configured tag is not accepted.
     *
     * @param {Object} options
     * @returns {Intl.NumberFormat}
     */
    var numberFormat = function(options) {
        try {
            return new Intl.NumberFormat(config.locale, options);
        } catch (e) {
            try {
                return new Intl.NumberFormat(undefined, options);
            } catch (e2) {
                return new Intl.NumberFormat(undefined, {maximumFractionDigits: options.maximumFractionDigits});
            }
        }
    };

    /**
     * Turn anything into a finite number (charts must never receive objects or strings).
     *
     * @param {*} value
     * @returns {Number}
     */
    var toNumber = function(value) {
        var n = typeof value === 'number' ? value : parseFloat(value);
        return isFinite(n) ? n : 0;
    };

    return {
        /**
         * Keep the page configuration.
         *
         * @param {Object} pageconfig
         */
        init: function(pageconfig) {
            config = pageconfig;
            config.strings = config.strings || {};
        },

        /**
         * Page configuration.
         *
         * @returns {Object}
         */
        config: function() {
            return config;
        },

        /**
         * A language string from the page configuration, with an optional {$a} or {$a->x} replacement.
         *
         * @param {String} key
         * @param {*} a
         * @returns {String}
         */
        str: function(key, a) {
            var text = String(config.strings[key] !== undefined ? config.strings[key] : key);
            if (a !== undefined && a !== null && typeof a === 'object') {
                Object.keys(a).forEach(function(k) {
                    text = text.split('{$a->' + k + '}').join(String(a[k]));
                });
            } else if (a !== undefined) {
                text = text.split('{$a}').join(String(a));
            }
            return text;
        },

        /**
         * Escape text for HTML.
         *
         * @param {*} text
         * @returns {String}
         */
        esc: function(text) {
            var div = document.createElement('div');
            div.textContent = text === null || text === undefined ? '' : String(text);
            return div.innerHTML;
        },

        /**
         * Decode entities of server-formatted names (canvas labels are plain text).
         *
         * @param {*} html
         * @returns {String}
         */
        decode: function(html) {
            var area = document.createElement('textarea');
            area.innerHTML = html === null || html === undefined ? '' : String(html);
            return area.value;
        },

        toNumber: toNumber,
        numberFormat: numberFormat,

        /**
         * Formatter for a chart unit: count, number, hours, megabytes or a currency code. Always given numbers.
         *
         * @param {String} unit
         * @param {Boolean} compact short axis labels
         * @returns {Function}
         */
        formatter: function(unit, compact) {
            var self = this;
            if (/^[A-Z]{3}$/.test(unit)) {
                var money = numberFormat(compact ? {style: 'currency', currency: unit, notation: 'compact',
                    maximumFractionDigits: 1} : {style: 'currency', currency: unit, maximumFractionDigits: 2});
                return function(v) {
                    return money.format(toNumber(v));
                };
            }
            if (unit === 'hours') {
                var hours = numberFormat({maximumFractionDigits: 1});
                return function(v) {
                    v = toNumber(v);
                    return v > 0 && v < 1 && !compact ? Math.round(v * 60) + ' ' + self.str('minutesunit') :
                        hours.format(v) + ' ' + self.str('hoursunit');
                };
            }
            if (unit === 'megabytes') {
                var mb = numberFormat({maximumFractionDigits: 1});
                return function(v) {
                    return mb.format(toNumber(v)) + ' ' + self.str('mbunit');
                };
            }
            var plain = numberFormat(compact ? {notation: 'compact', maximumFractionDigits: 1} :
                {maximumFractionDigits: unit === 'count' ? 0 : 2});
            return function(v) {
                return plain.format(toNumber(v));
            };
        },

        /**
         * Current time as text.
         *
         * @returns {String}
         */
        timeNow: function() {
            var options = {hour: '2-digit', minute: '2-digit'};
            try {
                return new Date().toLocaleTimeString(config.locale, options);
            } catch (e) {
                return new Date().toLocaleTimeString(undefined, options);
            }
        },

        /**
         * Log a technical error for debugging (the page only shows a friendly message).
         *
         * @param {String} what
         * @param {*} error
         */
        logError: function(what, error) {
            if (window.console && window.console.error) {
                window.console.error('[report_orgreports] ' + what, error);
            }
        }
    };
});

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
 * Export dropdown of the organisation reports dashboard.
 *
 * Every link carries the organisation, period and table filters shown on screen; export.php checks permissions again.
 *
 * @module     report_orgreports/export_menu
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['report_orgreports/format'], function(F) {
    'use strict';

    /**
     * Download URL.
     *
     * @param {Object} period org, course, range, from, to
     * @param {Object} extra
     * @returns {String}
     */
    var url = function(period, extra) {
        var params = new URLSearchParams();
        var all = Object.assign({}, period, extra);
        Object.keys(all).forEach(function(k) {
            var v = all[k];
            if (v !== '' && v !== null && v !== undefined && !(k !== 'org' && v === 0)) {
                params.set(k, String(v));
            }
        });
        params.set('sesskey', M.cfg.sesskey);
        return F.config().exporturl + '?' + params.toString();
    };

    /**
     * Download URL of a table with its filters.
     *
     * @param {Object} period
     * @param {String} key
     * @param {Object} state
     * @returns {String}
     */
    var tableUrl = function(period, key, state) {
        return url(period, {kind: 'table', table: key, search: state.search, courseid: state.courseid,
            status: state.status, sort: state.sort, dir: state.sort ? state.dir : ''});
    };

    return {
        url: url,
        tableUrl: tableUrl,

        /**
         * Fill the menu for the current section.
         *
         * @param {HTMLElement} root
         * @param {Object} period
         * @param {String} section
         * @param {Array} tables [{key, title}]
         * @param {Object} tablestate
         */
        build: function(root, period, section, tables, tablestate) {
            var list = root.querySelector('[data-region="exportlist"]');
            if (!list) {
                return;
            }
            var item = function(href, icon, title, desc) {
                return '<a href="' + F.esc(href) + '" role="menuitem" class="orgr-menu-item"><i class="fa-solid ' + icon +
                    '" aria-hidden="true"></i><span><strong>' + F.esc(title) + '</strong>' +
                    (desc ? '<small>' + F.esc(desc) + '</small>' : '') + '</span></a>';
            };
            var html = '<div class="orgr-menu-group">' + F.esc(F.str('exportstats')) + '</div>' +
                item(url(period, {kind: 'stats', section: section, format: 'csv'}), 'fa-file-csv', F.str('exportcsv'),
                    F.str('exportcsv_desc')) +
                item(url(period, {kind: 'stats', section: section, format: 'pdf'}), 'fa-file-pdf', F.str('exportpdf'),
                    F.str('exportpdf_desc'));
            if (tables.length) {
                html += '<div class="orgr-menu-group">' + F.esc(F.str('exporttables')) + '</div>' +
                    tables.map(function(t) {
                        return item(tableUrl(period, t.key, tablestate[t.key] || {}), 'fa-table', t.title,
                            F.str('exporttable_desc'));
                    }).join('');
            }
            list.innerHTML = html;
        },

        /**
         * Open or close.
         *
         * @param {HTMLElement} root
         * @param {Boolean} open
         */
        toggle: function(root, open) {
            var region = root.querySelector('[data-region="export"]');
            if (!region) {
                return;
            }
            region.querySelector('[data-region="exportlist"]').hidden = !open;
            region.querySelector('[data-action="menu"]').setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                var first = region.querySelector('.orgr-menu-item');
                if (first) {
                    first.focus();
                }
            }
        },

        /**
         * Whether the menu is open.
         *
         * @param {HTMLElement} root
         * @returns {Boolean}
         */
        isOpen: function(root) {
            var list = root.querySelector('[data-region="exportlist"]');
            return !!list && !list.hidden;
        }
    };
});

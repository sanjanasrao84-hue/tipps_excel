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
 * Metric cards and the period summary table of the organisation reports dashboard.
 *
 * @module     report_orgreports/metric_cards
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['report_orgreports/format'], function(F) {
    'use strict';

    /**
     * Small inline SVG trend line.
     *
     * @param {Array} values
     * @returns {String}
     */
    var sparkline = function(values) {
        if (!Array.isArray(values) || values.length < 2) {
            return '';
        }
        values = values.map(F.toNumber);
        var w = 120;
        var h = 30;
        var max = Math.max.apply(null, values);
        var min = Math.min.apply(null, values);
        var span = max - min || 1;
        var step = w / (values.length - 1);
        var points = values.map(function(v, i) {
            var y = max === min ? h - 3 : h - 3 - ((v - min) / span) * (h - 7);
            return (i * step).toFixed(1) + ',' + y.toFixed(1);
        }).join(' ');
        return '<svg class="orgr-spark' + (max === min ? ' orgr-spark-flat' : '') + '" viewBox="0 0 ' + w + ' ' + h +
            '" preserveAspectRatio="none" aria-hidden="true" focusable="false"><polygon points="0,' + h + ' ' + points +
            ' ' + w + ',' + h + '"></polygon><polyline points="' + points + '"></polyline></svg>';
    };

    /**
     * How a card compares with the previous period, without overstating it.
     *
     * - previous value known and above zero: percentage change with direction;
     * - previous value zero: absolute change (from nothing to the current value), or "No change" when both are zero;
     * - no previous value (a current snapshot such as storage): "Current total".
     *
     * @param {Object} card
     * @returns {Object} {kind, html, text}
     */
    var compare = function(card) {
        if (card.previous === null || card.previous === undefined || card.previous === '') {
            return {kind: 'none', text: F.str('currenttotal'),
                html: '<span class="orgr-change orgr-change-none">' + F.esc(F.str('currenttotal')) + '</span>'};
        }
        var vs = '<span class="orgr-change-vs">' + F.esc(F.str('vsprevious', card.previous)) + '</span>';
        if (card.change !== null && card.change !== undefined && isFinite(card.change)) {
            var change = Number(card.change);
            var dir = change > 0 ? 'up' : (change < 0 ? 'down' : 'flat');
            var icon = {up: 'fa-arrow-trend-up', down: 'fa-arrow-trend-down', flat: 'fa-equals'}[dir];
            var text = (change > 0 ? '+' : (change < 0 ? '−' : '')) +
                F.numberFormat({maximumFractionDigits: 1}).format(Math.abs(change)) + '%';
            var srtext = F.str(dir === 'up' ? 'srup' : (dir === 'down' ? 'srdown' : 'srflat'), text);
            return {kind: dir, text: text, html: '<span class="orgr-change orgr-change-' + dir + ' orgr-tone-' +
                F.esc(card.tone || 'neutral') + '" title="' + F.esc(srtext) + '"><i class="fa-solid ' + icon +
                '" aria-hidden="true"></i><span class="visually-hidden">' + F.esc(srtext) + '</span><span aria-hidden="true">' +
                F.esc(text) + '</span></span>' + vs};
        }
        if (F.toNumber(card.raw) > 0) {
            var abs = '+' + card.value;
            return {kind: 'new', text: abs, html: '<span class="orgr-change orgr-change-new" title="' +
                F.esc(F.str('fromzero')) + '"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>' + F.esc(abs) +
                '</span>' + vs};
        }
        return {kind: 'flat', text: F.str('nochange'), html: '<span class="orgr-change orgr-change-flat">' +
            '<i class="fa-solid fa-equals" aria-hidden="true"></i>' + F.esc(F.str('nochange')) + '</span>' + vs};
    };

    return {
        compare: compare,

        /**
         * Grid of metric cards.
         *
         * @param {Array} cards
         * @returns {String}
         */
        render: function(cards) {
            if (!cards.length) {
                return '';
            }
            return '<section class="orgr-kpis" aria-label="' + F.esc(F.str('keymetrics')) + '">' +
                cards.map(function(card) {
                    var unavailable = !!card.unavailable;
                    var info = card.hint ? '<span class="orgr-info" tabindex="0" role="img" aria-label="' + F.esc(card.hint) +
                        '" title="' + F.esc(card.hint) + '"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></span>' :
                        '';
                    return '<article class="orgr-kpi' + (unavailable ? ' orgr-kpi-unavailable' : '') + '">' +
                        '<header class="orgr-kpi-head"><span class="orgr-kpi-icon"><i class="' + F.esc(card.icon) +
                        '" aria-hidden="true"></i></span><h3 class="orgr-kpi-label">' + F.esc(card.label) + '</h3>' + info +
                        '</header><div class="orgr-kpi-value">' + F.esc(card.value) + '</div>' +
                        '<p class="orgr-kpi-desc">' + F.esc(unavailable ? card.hint : card.description) + '</p>' +
                        (unavailable ? '' : '<div class="orgr-kpi-foot">' + compare(card).html + '</div>' +
                            sparkline(card.spark)) + '</article>';
                }).join('') + '</section>';
        },

        /**
         * Compact table: every card for this and the previous period.
         *
         * @param {Array} cards
         * @param {String} period
         * @param {String} previous
         * @returns {String}
         */
        summaryTable: function(cards, period, previous) {
            var rows = cards.filter(function(c) {
                return !c.unavailable;
            }).map(function(card) {
                var cmp = compare(card);
                return '<tr><th scope="row">' + F.esc(card.label) + '</th><td class="orgr-num">' + F.esc(card.value) +
                    '</td><td class="orgr-num">' + F.esc(card.previous === null || card.previous === undefined ? '—' :
                    card.previous) + '</td><td class="orgr-num"><span class="orgr-change orgr-change-' + cmp.kind +
                    (card.tone ? ' orgr-tone-' + F.esc(card.tone) : '') + '">' + F.esc(cmp.kind === 'none' ? '—' : cmp.text) +
                    '</span></td></tr>';
            }).join('');
            return '<section class="orgr-panel orgr-summary"><div class="orgr-panel-head"><h3 class="orgr-panel-title">' +
                '<i class="fa-solid fa-table-list" aria-hidden="true"></i> ' + F.esc(F.str('periodsummary')) + '</h3></div>' +
                '<div class="orgr-table-scroll"><table class="orgr-table orgr-table-compact"><thead><tr><th scope="col">' +
                F.esc(F.str('metric')) + '</th><th scope="col" class="orgr-num">' + F.esc(period) + '</th>' +
                '<th scope="col" class="orgr-num">' + F.esc(previous) + '</th><th scope="col" class="orgr-num">' +
                F.esc(F.str('change')) + '</th></tr></thead><tbody>' + rows + '</tbody></table></div></section>';
        }
    };
});

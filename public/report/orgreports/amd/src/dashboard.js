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
 * Organisation reports dashboard: state, tabs, organisation, course and date filters, loading, errors and retry.
 *
 * Sections and tables come from the report_orgreports_get_section / _get_table web services; the components
 * (metric_cards, chart_panel, data_table, date_filter, export_menu) draw them. Written as AMD modules because this
 * installation has no JavaScript build step; amd/build holds the same files.
 *
 * @module     report_orgreports/dashboard
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'report_orgreports/format', 'report_orgreports/metric_cards', 'report_orgreports/chart_panel',
        'report_orgreports/data_table', 'report_orgreports/date_filter', 'report_orgreports/export_menu'],
function(Ajax, F, Cards, Charts, Tables, DateFilter, ExportMenu) {
    'use strict';

    var ICONS = {overview: 'fa-gauge-high', sales: 'fa-cart-shopping', usage: 'fa-server', activity: 'fa-school'};

    var root;
    var state;
    var current = {tables: []};
    var tablestate = {};
    var requestid = 0;

    /**
     * Call a web service and parse its JSON payload.
     *
     * @param {String} name
     * @param {Object} args
     * @returns {Promise}
     */
    var call = function(name, args) {
        return Ajax.call([{methodname: name, args: args}])[0].then(function(result) {
            if (!result || typeof result.json !== 'string') {
                throw new Error('unexpected response from ' + name);
            }
            return JSON.parse(result.json);
        });
    };

    /**
     * Organisation, course and period of the current state.
     *
     * @returns {Object}
     */
    var period = function() {
        var custom = state.range === 'custom';
        return {org: state.org, course: state.course, range: state.range, from: custom ? state.from : '',
            to: custom ? state.to : ''};
    };

    /**
     * Keep the address bar in step, so the tab and filters survive a reload and can be shared.
     */
    var updateUrl = function() {
        var params = new URLSearchParams({org: state.org});
        if (state.course) {
            params.set('course', state.course);
        }
        params.set('section', state.section);
        params.set('range', state.range);
        if (state.range === 'custom') {
            params.set('from', state.from);
            params.set('to', state.to);
        }
        window.history.replaceState(null, '', F.config().pageurl + '?' + params.toString());
    };

    /**
     * Check the section payload before anything is drawn.
     *
     * @param {Object} data
     * @returns {Object}
     */
    var validateSection = function(data) {
        if (!data || typeof data !== 'object' || !data.access || !Array.isArray(data.access.sections)) {
            throw new Error('unexpected section response');
        }
        ['cards', 'charts', 'insights', 'notices', 'availability', 'tables'].forEach(function(key) {
            if (!Array.isArray(data[key])) {
                data[key] = [];
            }
        });
        data.cards = data.cards.filter(function(c) {
            return c && typeof c === 'object' && typeof c.label === 'string';
        });
        return data;
    };

    /**
     * Header: the organisation shown and the courses it can be narrowed to.
     *
     * @param {Object} data
     */
    var renderHeader = function(data) {
        root.querySelector('[data-region="orgname"]').textContent = F.decode(data.organisation);
        var select = root.querySelector('[data-action="org"]');
        if (select) {
            select.value = String(data.access.org);
        }
        var courses = data.access.orgcourses || [];
        state.course = data.access.course || 0;
        root.querySelector('[data-region="coursefilter"]').classList.toggle('d-none', !courses.length);
        root.querySelector('[data-action="course"]').innerHTML = '<option value="0">' + F.esc(F.str('allcourses')) +
            '</option>' + courses.map(function(c) {
                return '<option value="' + F.esc(c.id) + '"' + (c.id === state.course ? ' selected' : '') + '>' +
                    F.esc(F.decode(c.name)) + '</option>';
            }).join('');
    };

    /**
     * Tabs after the organisation (and so the permissions) changed.
     *
     * @param {Object} access
     */
    var renderTabs = function(access) {
        root.querySelector('[data-region="sections"]').innerHTML = access.sections.map(function(s) {
            var active = s.key === state.section;
            return '<button type="button" role="tab" id="orgr-tab-' + F.esc(s.key) + '" class="orgr-tab' +
                (active ? ' active' : '') + '" data-section="' + F.esc(s.key) + '" aria-selected="' + active +
                '" aria-controls="orgr-panel" tabindex="' + (active ? '0' : '-1') + '"><i class="fa-solid ' +
                (ICONS[s.key] || 'fa-chart-simple') + '" aria-hidden="true"></i><span>' + F.esc(s.name) + '</span></button>';
        }).join('');
        root.querySelector('[data-region="body"]').setAttribute('aria-labelledby', 'orgr-tab-' + state.section);
        var exportregion = root.querySelector('[data-region="export"]');
        if (exportregion) {
            exportregion.classList.toggle('d-none', !access.canexport);
        }
        // Payment Reports link follows the organisation and period.
        var payments = root.querySelector('[data-region="paymentslink"]');
        if (payments) {
            payments.classList.toggle('d-none', !access.cansales);
            var url = new URL(payments.href, window.location.href);
            var p = period();
            ['org', 'range', 'from', 'to'].forEach(function(k) {
                if (p[k] !== '' && p[k] !== undefined) {
                    url.searchParams.set(k, String(p[k]));
                } else {
                    url.searchParams.delete(k);
                }
            });
            payments.href = url.toString();
        }
    };

    /**
     * Insights strip.
     *
     * @param {Array} insights server text, already escaped
     * @returns {String}
     */
    var renderInsights = function(insights) {
        if (!insights.length) {
            return '';
        }
        return '<section class="orgr-insights"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i><div>' +
            '<h3 class="orgr-insights-title">' + F.esc(F.str('insights')) + '</h3><ul>' + insights.map(function(i) {
                return '<li>' + i + '</li>';
            }).join('') + '</ul></div></section>';
    };

    /**
     * Measured / estimated / unavailable panel.
     *
     * @param {Array} rows
     * @returns {String}
     */
    var renderAvailability = function(rows) {
        if (!rows.length) {
            return '';
        }
        var icons = {measured: 'fa-circle-check', estimated: 'fa-wave-square', unavailable: 'fa-ban'};
        return '<section class="orgr-panel orgr-availability"><div class="orgr-panel-head"><h3 class="orgr-panel-title">' +
            '<i class="fa-solid fa-list-check" aria-hidden="true"></i> ' + F.esc(F.str('availability')) + '</h3></div>' +
            '<div class="orgr-avail-grid">' + rows.map(function(r) {
                return '<div class="orgr-avail orgr-avail-' + F.esc(r.status) + '"><div class="orgr-avail-top">' +
                    '<span class="orgr-avail-metric">' + F.esc(r.metric) + '</span><span class="orgr-status orgr-status-' +
                    F.esc(r.status) + '"><i class="fa-solid ' + (icons[r.status] || 'fa-circle') + '" aria-hidden="true"></i> ' +
                    F.esc(r.statuslabel) + '</span></div><p class="orgr-avail-source">' + r.source + '</p></div>';
            }).join('') + '</div></section>';
    };

    /**
     * "About this data" disclosure.
     *
     * @param {Array} notices server text, already escaped
     * @returns {String}
     */
    var renderAbout = function(notices) {
        if (!notices.length) {
            return '';
        }
        var warning = notices.some(function(n) {
            return n.type === 'warning';
        });
        return '<details class="orgr-about"' + (warning ? ' open' : '') + '><summary><i class="fa-solid fa-circle-info" ' +
            'aria-hidden="true"></i><span>' + F.esc(F.str('aboutdata')) + '</span><span class="orgr-count">' + notices.length +
            '</span><i class="fa-solid fa-chevron-down orgr-caret" aria-hidden="true"></i></summary>' +
            '<div class="orgr-about-list">' + notices.map(function(n) {
                var icon = n.type === 'unavailable' ? 'fa-ban' : (n.type === 'warning' ? 'fa-triangle-exclamation' :
                    'fa-circle-info');
                return '<div class="orgr-note orgr-note-' + F.esc(n.type) + '"><i class="fa-solid ' + icon +
                    '" aria-hidden="true"></i><div><strong>' + n.title + '</strong><p>' + n.text + '</p></div></div>';
            }).join('') + '</div></details>';
    };

    /**
     * Skeleton for the first load of a section.
     *
     * @returns {String}
     */
    var skeleton = function() {
        var cards = '';
        for (var i = 0; i < 4; i++) {
            cards += '<div class="orgr-kpi orgr-skeleton"><span></span><span></span><span></span></div>';
        }
        return '<div class="visually-hidden">' + F.esc(F.str('loading')) + '</div><div class="orgr-kpis">' + cards +
            '</div><div class="orgr-charts"><div class="orgr-panel orgr-size-wide orgr-skeleton orgr-skeleton-chart"></div>' +
            '<div class="orgr-panel orgr-size-narrow orgr-skeleton orgr-skeleton-chart"></div></div>';
    };

    /**
     * Busy indicator.
     *
     * @param {Boolean} busy
     */
    var setBusy = function(busy) {
        root.classList.toggle('orgr-busy', busy);
        root.querySelector('[data-region="body"]').setAttribute('aria-busy', busy ? 'true' : 'false');
        var refresh = root.querySelector('[data-action="refresh"]');
        refresh.classList.toggle('spinning', busy);
        refresh.disabled = busy;
    };

    /**
     * Current filters of a table, kept while switching tabs.
     *
     * @param {String} key
     * @returns {Object}
     */
    var tableState = function(key) {
        if (!tablestate[key]) {
            tablestate[key] = {search: '', courseid: 0, status: '', sort: '', dir: 'ASC', page: 0, perpage: 25};
        }
        return tablestate[key];
    };

    /**
     * Load one table page.
     *
     * @param {String} key
     */
    var loadTable = function(key) {
        var wrapper = root.querySelector('[data-table="' + key + '"]');
        if (!wrapper) {
            return;
        }
        var t = tableState(key);
        var body = wrapper.querySelector('[data-region="tablebody"]');
        body.setAttribute('aria-busy', 'true');
        body.classList.add('orgr-dim');
        var mine = requestid;
        call('report_orgreports_get_table', Object.assign(period(), {table: key, search: t.search, courseid: t.courseid,
            status: t.status, sort: t.sort, dir: t.dir, page: t.page, perpage: t.perpage})).then(function(data) {
            if (mine === requestid) {
                Tables.render(wrapper, data, t, ExportMenu.tableUrl(period(), key, t), F.config().access.courses.length);
                ExportMenu.build(root, period(), state.section, current.tables, tablestate);
            }
            return;
        }).catch(function(error) {
            if (mine === requestid) {
                F.logError('table ' + key + ' failed', error);
                Tables.error(wrapper);
            }
        });
    };

    /**
     * Load the current section (cards, charts, notes) and its tables.
     */
    var load = function() {
        requestid++;
        var mine = requestid;
        var body = root.querySelector('[data-region="body"]');
        DateFilter.showError(root, '');
        setBusy(true);
        if (!body.querySelector('.orgr-kpis') || body.querySelector('.orgr-skeleton')) {
            Charts.destroyAll();
            body.innerHTML = skeleton();
        } else {
            body.classList.add('orgr-dim');
        }
        updateUrl();
        call('report_orgreports_get_section', Object.assign(period(), {section: state.section})).then(function(raw) {
            if (mine !== requestid) {
                return;
            }
            var data = validateSection(raw);
            F.config().access = data.access;
            if (!data.access.sections.some(function(s) {
                return s.key === state.section;
            })) {
                // The organisation changed and this section is not available there: show its overview instead.
                state.section = 'overview';
                load();
                return;
            }
            state.from = data.from || state.from;
            state.to = data.to || state.to;
            DateFilter.setDates(root, data.from, data.to);
            renderHeader(data);
            renderTabs(data.access);
            Charts.destroyAll();
            current.tables = data.tables;
            var allempty = data.charts.length && data.charts.every(function(c) {
                return c.empty;
            });
            body.classList.remove('orgr-dim');
            body.innerHTML = (allempty ? '<div class="orgr-banner orgr-banner-empty"><i class="fa-regular fa-calendar-xmark" ' +
                    'aria-hidden="true"></i><span>' + F.esc(F.str('periodempty')) + '</span></div>' : '') +
                Cards.render(data.cards) + renderInsights(data.insights) +
                '<div class="orgr-charts" data-region="charts"></div>' +
                (state.section === 'overview' && data.cards.length ? Cards.summaryTable(data.cards, data.period,
                    data.previousperiod) : '') +
                renderAvailability(data.availability) +
                data.tables.map(function(t) {
                    return Tables.shell(t, tableState(t.key), data.access.courses, data.access.canexport);
                }).join('') + renderAbout(data.notices);
            Charts.render(body.querySelector('[data-region="charts"]'), data.charts);
            ExportMenu.build(root, period(), state.section, data.tables, tablestate);
            setBusy(false);
            data.tables.forEach(function(t) {
                loadTable(t.key);
            });
            return;
        }).catch(function(error) {
            if (mine !== requestid) {
                return;
            }
            F.logError('section ' + state.section + ' failed', error);
            Charts.destroyAll();
            setBusy(false);
            body.classList.remove('orgr-dim');
            // Friendly text only: technical details are in the browser console and the server log.
            var denied = error && (error.errorcode === 'nopermissions' || error.errorcode === 'noaccess');
            body.innerHTML = '<div class="orgr-panel orgr-state orgr-state-error" role="alert"><span class="orgr-empty-icon ' +
                'orgr-empty-icon-error" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span><div>' +
                '<strong>' + F.esc(F.str(denied ? 'nopermission' : 'loadfailed')) + '</strong><span>' +
                F.esc(F.str(denied ? 'nopermissionhint' : 'loadfailedhint')) + '</span>' +
                (denied ? '' : '<button type="button" class="orgr-btn orgr-btn-primary" data-action="retry">' +
                    '<i class="fa-solid fa-rotate-right" aria-hidden="true"></i> ' + F.esc(F.str('retry')) + '</button>') +
                '</div></div>';
        });
    };

    /**
     * Wait for typing to stop.
     *
     * @param {Function} fn
     * @param {Number} wait
     * @returns {Function}
     */
    var debounce = function(fn, wait) {
        var timer;
        return function() {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function() {
                fn.apply(null, args);
            }, wait);
        };
    };

    var searchTable = debounce(function(key, value) {
        var t = tableState(key);
        t.search = value;
        t.page = 0;
        loadTable(key);
    }, 350);

    /**
     * A new period applies to every table from its first page.
     */
    var resetPages = function() {
        Object.keys(tablestate).forEach(function(k) {
            tablestate[k].page = 0;
        });
    };

    /**
     * Apply the From / To fields.
     */
    var applyCustom = function() {
        var from = root.querySelector('[data-field="from"]').value;
        var to = root.querySelector('[data-field="to"]').value;
        var message = DateFilter.validate(from, to, F.config().today);
        DateFilter.showError(root, message);
        if (message) {
            root.querySelector('[data-field="' + (from ? 'to' : 'from') + '"]').focus();
            return;
        }
        state.range = 'custom';
        state.from = from;
        state.to = to;
        DateFilter.setPreset(root, 'custom');
        resetPages();
        load();
    };

    /**
     * Event wiring (delegated, so re-rendered content keeps working).
     */
    var listen = function() {
        root.addEventListener('change', function(e) {
            var target = e.target;
            if (target.dataset.action === 'org' || target.dataset.action === 'course') {
                // Another organisation starts with all its courses; either way the table filters start over.
                state.course = target.dataset.action === 'course' ? parseInt(target.value, 10) : 0;
                if (target.dataset.action === 'org') {
                    state.org = parseInt(target.value, 10);
                }
                tablestate = {};
                load();
                return;
            }
            var card = target.closest('[data-table]');
            var filter = target.dataset.filter;
            if (card && filter && filter !== 'search') {
                var t = tableState(card.dataset.table);
                t[filter] = filter === 'status' ? target.value : parseInt(target.value, 10);
                t.page = 0;
                loadTable(card.dataset.table);
            }
        });
        root.addEventListener('input', function(e) {
            var card = e.target.closest('[data-table]');
            if (card && e.target.dataset.filter === 'search') {
                searchTable(card.dataset.table, e.target.value);
            }
            if (e.target.dataset.field) {
                DateFilter.showError(root, '');
            }
        });
        root.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.dataset.field) {
                e.preventDefault();
                applyCustom();
            } else if (e.key === 'Escape' && ExportMenu.isOpen(root)) {
                ExportMenu.toggle(root, false);
                root.querySelector('[data-action="menu"]').focus();
            } else if ((e.key === 'ArrowRight' || e.key === 'ArrowLeft') && e.target.classList.contains('orgr-tab')) {
                var tabs = Array.prototype.slice.call(root.querySelectorAll('.orgr-tab'));
                var next = tabs[(tabs.indexOf(e.target) + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                next.focus();
                next.click();
            }
        });
        document.addEventListener('click', function(e) {
            if (ExportMenu.isOpen(root) && !(e.target.closest && e.target.closest('[data-region="export"]'))) {
                ExportMenu.toggle(root, false);
            }
        });
        root.addEventListener('click', function(e) {
            var target = e.target.closest('[data-section],[data-range],[data-action],[data-sort],[data-page]');
            if (!target || target.disabled) {
                return;
            }
            var card = target.closest('[data-table]');
            var action = target.dataset.action;
            if (target.dataset.section) {
                if (target.dataset.section !== state.section) {
                    state.section = target.dataset.section;
                    root.querySelector('[data-region="body"]').innerHTML = '';
                    load();
                }
            } else if (target.dataset.range) {
                if (target.dataset.range === 'custom') {
                    DateFilter.setPreset(root, 'custom');
                    root.querySelector('[data-field="from"]').focus();
                } else {
                    state.range = target.dataset.range;
                    DateFilter.setPreset(root, state.range);
                    resetPages();
                    load();
                }
            } else if (action === 'applycustom') {
                applyCustom();
            } else if (action === 'refresh' || action === 'retry') {
                load();
            } else if (action === 'retrytable' && card) {
                loadTable(card.dataset.table);
            } else if (action === 'menu') {
                ExportMenu.toggle(root, !ExportMenu.isOpen(root));
            } else if (action === 'tablecsv' && target.classList.contains('disabled')) {
                e.preventDefault();
            } else if (target.dataset.sort && card) {
                var t = tableState(card.dataset.table);
                t.dir = t.sort === target.dataset.sort && t.dir === 'ASC' ? 'DESC' : 'ASC';
                t.sort = target.dataset.sort;
                t.page = 0;
                loadTable(card.dataset.table);
            } else if (target.dataset.page !== undefined && card) {
                tableState(card.dataset.table).page = Math.max(0, parseInt(target.dataset.page, 10));
                loadTable(card.dataset.table);
            }
        });
    };

    return {
        /**
         * Start the dashboard.
         *
         * @param {String} selector
         */
        init: function(selector) {
            root = document.querySelector(selector);
            if (!root) {
                return;
            }
            var config;
            try {
                config = JSON.parse(root.dataset.config);
            } catch (e) {
                F.logError('page configuration', e);
                return;
            }
            F.init(config);
            state = {org: config.org, course: config.course, section: config.section, range: config.range, from: config.from,
                to: config.to};
            listen();
            load();
        }
    };
});

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
 * Calendar page: month, week, day and agenda views (FullCalendar) of the user's academic events and
 * Google Calendar, with connection actions and event management. Around the calendar: a summary banner
 * (events this month, active days, days until the next event) and an agenda panel (the selected day,
 * the next event and what is coming up), all read from the same events service as the calendar.
 *
 * Event times are shown in the user's Moodle time zone: timestamps are converted to wall-clock times
 * in that zone and given to FullCalendar in UTC mode, so the browser's own time zone does not matter.
 *
 * @module     local_gcal/calendar
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax', 'core/notification', 'core/templates', 'core/toast', 'core/modal', 'core_form/modalform',
], function(Ajax, Notification, Templates, Toast, ModalModule, ModalFormModule) {
    'use strict';

    const Modal = ModalModule.default || ModalModule;
    const ModalForm = ModalFormModule.default || ModalFormModule;
    const SMALL_SCREEN = 768;
    /** Days ahead searched for the next and coming-up events (the events service allows up to 100). */
    const UPCOMING_DAYS = 90;
    /** Events listed under "Coming up", after the "Next up" one. */
    const UPCOMING_COUNT = 6;
    const DAYSECS = 86400;

    const str = (key, param) => M.util.get_string(key, 'local_gcal', param);

    /**
     * Wall-clock time of a timestamp in a time zone, as "YYYY-MM-DDTHH:mm:ss" (no offset).
     *
     * @param {Number} timestamp
     * @param {String} timezone
     * @returns {String}
     */
    const wallClock = (timestamp, timezone) => {
        const parts = {};
        new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
        }).formatToParts(new Date(timestamp * 1000)).forEach((part) => {
            parts[part.type] = part.value;
        });
        return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}`;
    };

    /**
     * Midnight UTC of a date (YYYY-MM-DD), in milliseconds.
     *
     * @param {String} date
     * @returns {Number}
     */
    const utcDay = (date) => Date.parse(date + 'T00:00:00Z');

    /**
     * A date (YYYY-MM-DD) moved by a number of days.
     *
     * @param {String} date
     * @param {Number} days
     * @returns {String}
     */
    const addDays = (date, days) => new Date(utcDay(date) + days * DAYSECS * 1000).toISOString().slice(0, 10);

    /**
     * First and last day (YYYY-MM-DD) of an event in the user's time zone.
     *
     * @param {Object} event
     * @param {String} timezone
     * @returns {String[]}
     */
    const eventSpan = (event, timezone) => {
        const first = wallClock(event.start, timezone).slice(0, 10);
        // The end is exclusive (midnight after the last day for all-day events).
        const last = event.end > event.start ? wallClock(event.end - 1, timezone).slice(0, 10) : first;
        return [first, last < first ? first : last];
    };

    /**
     * Readable date and time of an event in the user's time zone.
     *
     * @param {Object} event
     * @param {Object} config
     * @returns {String}
     */
    const describeWhen = (event, config) => {
        const date = new Intl.DateTimeFormat(config.locale, {
            timeZone: config.timezone, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        });
        const time = new Intl.DateTimeFormat(config.locale, {timeZone: config.timezone, hour: '2-digit', minute: '2-digit'});
        const start = new Date(event.start * 1000);
        const end = new Date(event.end * 1000);
        if (event.allday) {
            return `${date.format(start)} · ${str('allday')}`;
        }
        if (event.deadline || event.end <= event.start) {
            return `${date.format(start)} · ${time.format(start)} (${str('deadline')})`;
        }
        return `${date.format(start)} · ${time.format(start)}–${time.format(end)}`;
    };

    /**
     * Short date and time of an event, for the agenda panel.
     *
     * @param {Object} event
     * @param {Object} config
     * @param {Boolean} withdate Include the date (not needed in the selected day's list)
     * @returns {String}
     */
    const describeShort = (event, config, withdate) => {
        const start = new Date(event.start * 1000);
        const time = event.allday ? str('allday') : new Intl.DateTimeFormat(config.locale, {
            timeZone: config.timezone, hour: '2-digit', minute: '2-digit',
        }).format(start);
        if (!withdate) {
            return time;
        }
        const date = new Intl.DateTimeFormat(config.locale, {
            timeZone: config.timezone, weekday: 'short', day: 'numeric', month: 'short',
        }).format(start);
        return `${date} · ${time}`;
    };

    /**
     * Waits for the FullCalendar library, loaded as a global script.
     *
     * @returns {Promise}
     */
    const fullCalendarReady = () => new Promise((resolve) => {
        const check = () => (window.FullCalendar ? resolve(window.FullCalendar) : setTimeout(check, 50));
        check();
    });

    /**
     * Initialises the page.
     *
     * @param {Object} config timezone, locale, initialdate, cancreate, firstday
     */
    const init = (config) => {
        const root = document.querySelector('[data-region="local-gcal"]');
        if (!root) {
            return;
        }
        const filters = {
            course: root.querySelector('[data-filter="course"]'),
            organisation: root.querySelector('[data-filter="organisation"]'),
        };
        const filterValue = (name) => parseInt(filters[name] ? filters[name].value : 0, 10) || 0;

        const warnings = root.querySelector('[data-region="warnings"]');
        let calendar = null;

        // Today, and the day shown in the agenda panel (YYYY-MM-DD in the user's time zone).
        const today = () => wallClock(Date.now() / 1000, config.timezone).slice(0, 10);
        let selected = config.initialdate || today();
        let shownmonth = '';
        let panelrequest = 0;

        const showWarnings = (messages) => {
            warnings.innerHTML = '';
            messages.forEach((message) => {
                const div = document.createElement('div');
                div.className = 'alert alert-warning py-2';
                div.textContent = message;
                warnings.appendChild(div);
            });
        };

        // Request for the events from the first day up to the last one (exclusive, YYYY-MM-DD), with the current filters.
        // The range is padded by a day on each side for time zones; callers filter by the events' own days.
        const eventsRequest = (first, last) => ({
            methodname: 'local_gcal_get_events',
            args: {
                start: utcDay(first) / 1000 - DAYSECS,
                end: utcDay(last) / 1000 + DAYSECS,
                courseid: filterValue('course'),
                organisationid: filterValue('organisation'),
            },
        });

        const loadEvents = (info, success, failure) => {
            const pad = DAYSECS;
            Ajax.call([{
                methodname: 'local_gcal_get_events',
                args: {
                    start: Math.floor(info.start.valueOf() / 1000) - pad,
                    end: Math.floor(info.end.valueOf() / 1000) + pad,
                    courseid: filterValue('course'),
                    organisationid: filterValue('organisation'),
                },
            }])[0].then((result) => {
                showWarnings(result.warnings);
                success(result.events.map((event) => ({
                    id: event.id,
                    title: event.title,
                    start: event.allday ? wallClock(event.start, config.timezone).slice(0, 10)
                        : wallClock(event.start, config.timezone),
                    end: event.end > event.start
                        ? (event.allday ? wallClock(event.end, config.timezone).slice(0, 10)
                            : wallClock(event.end, config.timezone))
                        : null,
                    allDay: event.allday,
                    classNames: ['lgc-event', `lgc-event--${event.source}`, event.deadline ? 'lgc-event--deadline' : ''],
                    extendedProps: event,
                })));
                return result;
            }).catch((error) => {
                failure(error);
                Notification.exception(error);
            });
        };

        const setText = (selector, text) => {
            const node = root.querySelector(selector);
            if (node) {
                node.textContent = text;
            }
        };

        const span = (className, text) => {
            const node = document.createElement('span');
            node.className = className;
            node.textContent = text;
            return node;
        };

        // A list entry (coloured bar, title, date and time) that opens the event's details.
        const eventItem = (event, withdate) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'lgc-item';
            button.title = event.title;
            button.style.setProperty('--lgc-c', event.color);
            button.append(
                span('lgc-item__title', event.title),
                span('lgc-item__meta', describeShort(event, config, withdate) + (event.coursename ? ' · ' + event.coursename : ''))
            );
            button.addEventListener('click', () => showDetails(event));
            item.appendChild(button);
            return item;
        };

        const fillList = (region, events, withdate, emptytext) => {
            const list = root.querySelector(`[data-region="${region}"]`);
            list.innerHTML = '';
            if (!events.length && emptytext) {
                const empty = document.createElement('li');
                empty.className = 'lgc-list__empty';
                empty.textContent = emptytext;
                list.appendChild(empty);
            }
            events.forEach((event) => list.appendChild(eventItem(event, withdate)));
        };

        const byStart = (a, b) => a.start - b.start || a.title.localeCompare(b.title);

        // Banner figures and agenda panel, from the events of the shown month, the coming days and the selected day.
        const refreshPanels = () => {
            if (!calendar) {
                return;
            }
            const request = ++panelrequest;
            const now = Date.now() / 1000;
            const first = today();
            const monthstart = calendar.getDate().toISOString().slice(0, 7) + '-01';
            const [year, month] = monthstart.split('-').map(Number);
            const monthend = new Date(Date.UTC(year, month, 1)).toISOString().slice(0, 10);
            const day = selected;
            Promise.all(Ajax.call([
                eventsRequest(monthstart, monthend),
                eventsRequest(first, addDays(first, UPCOMING_DAYS)),
                eventsRequest(day, addDays(day, 1)),
            ])).then(([monthresult, upcomingresult, dayresult]) => {
                if (request !== panelrequest) {
                    return null;
                }

                // Events in the shown month, and the days of the month that have at least one.
                const activedays = new Set();
                let count = 0;
                monthresult.events.forEach((event) => {
                    const [start, end] = eventSpan(event, config.timezone);
                    if (end < monthstart || start >= monthend) {
                        return;
                    }
                    count++;
                    for (let d = start < monthstart ? monthstart : start; d <= end && d < monthend; d = addDays(d, 1)) {
                        activedays.add(d);
                    }
                });
                setText('[data-stat="events"]', String(count));
                setText('[data-stat="days"]', String(activedays.size));

                // Events that have not started yet (all-day events until their day is over).
                const upcoming = upcomingresult.events.filter((event) => (event.allday
                    ? eventSpan(event, config.timezone)[0] >= first : event.start >= now)).sort(byStart);
                const next = upcoming[0];
                const nextup = root.querySelector('[data-region="nextup"]');
                nextup.hidden = !next;
                if (next) {
                    const days = Math.round((utcDay(eventSpan(next, config.timezone)[0]) - utcDay(first)) / (DAYSECS * 1000));
                    setText('[data-stat="next"]', days <= 0 ? str('today') : String(days));
                    const card = root.querySelector('[data-region="nextupevent"]');
                    card.innerHTML = '';
                    card.title = next.title;
                    card.append(span('lgc-nextup__title', next.title), span('lgc-nextup__meta', describeWhen(next, config)));
                    if (next.coursename) {
                        card.appendChild(span('lgc-nextup__meta', next.coursename));
                    }
                    card.onclick = () => showDetails(next);
                } else {
                    setText('[data-stat="next"]', '–');
                }
                fillList('upcoming', upcoming.slice(1, 1 + UPCOMING_COUNT), true, next ? '' : str('nothingcomingup'));
                root.querySelector('[data-region="upcomingsection"]').hidden = upcoming.length === 1;

                // The selected day.
                setText('[data-region="dayheading"]', new Intl.DateTimeFormat(config.locale, {
                    timeZone: 'UTC', weekday: 'long', day: 'numeric', month: 'long',
                }).format(new Date(utcDay(day))) + (day === first ? ` · ${str('today')}` : ''));
                fillList('dayevents', dayresult.events.filter((event) => {
                    const [start, end] = eventSpan(event, config.timezone);
                    return start <= day && end >= day;
                }).sort(byStart), false, str('nothingonday'));
                return null;
            }).catch(Notification.exception);
        };

        const selectDay = (day) => {
            selected = day;
            root.querySelectorAll('.fc-daygrid-day.lgc-selected').forEach((cell) => cell.classList.remove('lgc-selected'));
            const cell = root.querySelector(`.fc-daygrid-day[data-date="${day}"]`);
            if (cell) {
                cell.classList.add('lgc-selected');
            }
            refreshPanels();
        };

        // Events changed or filters changed: the calendar and the panels load again.
        const reload = () => {
            calendar.refetchEvents();
            refreshPanels();
        };

        // The toolbar follows the calendar: title, active view, and Today only when today is not shown.
        const updateToolbar = () => {
            const view = calendar.view;
            setText('[data-region="title"]', view.title);
            root.querySelectorAll('[data-view]').forEach((button) => {
                const active = button.dataset.view === view.type;
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                button.classList.toggle('is-active', active);
            });
            const now = utcDay(today());
            root.querySelector('[data-nav="today"]').disabled =
                now >= view.currentStart.valueOf() && now < view.currentEnd.valueOf();
            const month = calendar.getDate().toISOString().slice(0, 7);
            if (month !== shownmonth) {
                shownmonth = month;
                refreshPanels();
            }
        };

        const showDetails = (event) => {
            const location = event.location || '';
            const context = Object.assign({}, event, {
                when: describeWhen(event, config),
                personal: event.source === 'google',
                // Events created in the calendar link to their course, even with an activity category.
                isactivity: !!event.editdatesurl || /\/mod\//.test(event.url || ''),
                locationurl: /^https?:\/\//i.test(location),
            });
            Templates.renderForPromise('local_gcal/event_details', context).then(({html}) => Modal.create({
                title: event.title,
                body: html,
                show: true,
                removeOnClose: true,
            })).then((modal) => {
                const body = modal.getRoot()[0];
                body.addEventListener('click', (e) => {
                    const button = e.target.closest('[data-action]');
                    if (!button) {
                        return;
                    }
                    if (button.dataset.action === 'edit') {
                        modal.destroy();
                        openForm(parseInt(button.dataset.eventid, 10), '');
                    } else if (button.dataset.action === 'delete') {
                        Notification.saveCancelPromise(str('cancelevent'), str('cancelconfirm', event.title), str('cancelevent'))
                            .then(() => Ajax.call([{methodname: 'local_gcal_delete_event',
                                args: {eventid: parseInt(button.dataset.eventid, 10)}}])[0])
                            .then(() => {
                                modal.destroy();
                                Toast.add(str('eventcancelled'), {type: 'success'});
                                reload();
                                return null;
                            })
                            .catch(() => null);
                    }
                });
                return modal;
            }).catch(Notification.exception);
        };

        // Calendar suggested in the form for the current filter (the server ignores it if the user cannot create there).
        const filterTarget = () => {
            if (filterValue('course')) {
                return 'course:' + filterValue('course');
            }
            return filterValue('organisation') ? 'category:' + filterValue('organisation') : '';
        };

        // Clears the filters that would hide a just-saved event.
        const reveal = (saved) => {
            if (!saved) {
                return;
            }
            if (filters.course && filterValue('course') && filterValue('course') !== saved.courseid) {
                filters.course.value = '0';
            }
            const organisation = filterValue('organisation');
            if (filters.organisation && organisation && organisation !== saved.organisationid) {
                filters.organisation.value = '0';
            }
        };

        const openForm = (eventid, start) => {
            const form = new ModalForm({
                formClass: '\\local_gcal\\form\\event_form',
                args: {id: eventid || 0, start: start || '', target: eventid ? '' : filterTarget()},
                modalConfig: {title: eventid ? str('editevent') : str('createevent')},
            });
            form.addEventListener(form.events.FORM_SUBMITTED, (e) => {
                Toast.add(str('eventsaved'), {type: 'success'});
                reveal(e.detail);
                reload();
                refreshStatus();
            });
            form.show();
        };

        const renderStatus = (status) => Templates.renderForPromise('local_gcal/status', status).then(({html, js}) => {
            Templates.replaceNodeContents(root.querySelector('[data-region="status"]'), html, js);
            return null;
        });

        const refreshStatus = () => Ajax.call([{methodname: 'local_gcal_get_status', args: {}}])[0]
            .then(renderStatus)
            .catch(() => null);

        root.addEventListener('click', (e) => {
            const button = e.target.closest('[data-action], [data-nav], [data-view]');
            if (!button || !root.contains(button)) {
                return;
            }
            if (button.dataset.view) {
                if (calendar) {
                    calendar.changeView(button.dataset.view);
                }
            } else if (button.dataset.nav) {
                if (!calendar) {
                    return;
                }
                if (button.dataset.nav === 'today') {
                    calendar.today();
                    selectDay(today());
                } else if (button.dataset.nav === 'prev') {
                    calendar.prev();
                } else {
                    calendar.next();
                }
            } else if (button.dataset.action === 'sync') {
                button.disabled = true;
                button.textContent = str('syncing');
                Ajax.call([{methodname: 'local_gcal_sync_now', args: {}}])[0].then((result) => {
                    Toast.add(result.message, {type: result.status.lastsyncstatus === 'ok' ? 'success' : 'warning'});
                    reload();
                    return refreshStatus();
                }).catch((error) => {
                    button.disabled = false;
                    Notification.exception(error);
                });
            } else if (button.dataset.action === 'create') {
                openForm(0, '');
            } else if (button.dataset.action === 'createonday') {
                openForm(0, selected + 'T09:00');
            }
        });

        root.addEventListener('change', (e) => {
            const input = e.target;
            if (input.dataset.filter) {
                reload();
            } else if (input.dataset.action === 'personal') {
                Ajax.call([{methodname: 'local_gcal_set_preferences', args: {showpersonal: input.checked}}])[0]
                    .then(() => reload())
                    .catch(Notification.exception);
            }
        });

        fullCalendarReady().then((FullCalendar) => {
            const small = window.innerWidth < SMALL_SCREEN;
            calendar = new FullCalendar.Calendar(root.querySelector('[data-region="calendar"]'), {
                timeZone: 'UTC',
                locale: config.locale,
                firstDay: config.firstday,
                initialView: small ? 'listWeek' : 'dayGridMonth',
                initialDate: config.initialdate || undefined,
                // The toolbar is part of the page template (real buttons with labels), driven from here.
                headerToolbar: false,
                allDayText: str('allday'),
                noEventsText: str('noevents'),
                height: 'auto',
                nowIndicator: true,
                dayMaxEvents: 2,
                moreLinkContent: (arg) => str('moreevents', arg.num),
                eventDisplay: 'block',
                eventTimeFormat: {hour: '2-digit', minute: '2-digit', meridiem: false},
                events: loadEvents,
                dayCellClassNames: (arg) => (arg.date.toISOString().slice(0, 10) === selected ? ['lgc-selected'] : []),
                eventDidMount: (info) => {
                    info.el.style.setProperty('--lgc-c', info.event.extendedProps.color);
                    info.el.title = `${info.event.title} · ${describeWhen(info.event.extendedProps, config)}`;
                },
                datesSet: () => {
                    if (calendar) {
                        updateToolbar();
                    }
                },
                eventClick: (info) => {
                    info.jsEvent.preventDefault();
                    showDetails(info.event.extendedProps);
                },
                dateClick: (info) => {
                    // Wall-clock time of the clicked slot (09:00 for a day), converted by the server in the user's time zone.
                    const wall = info.date.toISOString().slice(0, 16);
                    selectDay(wall.slice(0, 10));
                    // A day of the month view is selected (its events show in the agenda panel); a time slot creates an event.
                    if (config.cancreate && info.view.type !== 'dayGridMonth') {
                        openForm(0, info.allDay ? wall.slice(0, 10) + 'T09:00' : wall);
                    }
                },
                windowResize: () => {
                    const isSmall = window.innerWidth < SMALL_SCREEN;
                    if (isSmall && calendar.view.type !== 'listWeek') {
                        calendar.changeView('listWeek');
                    }
                },
            });
            calendar.render();
            updateToolbar();
            return calendar;
        }).catch(Notification.exception);
    };

    return {init: init};
});
